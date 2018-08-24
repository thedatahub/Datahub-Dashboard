<?php
namespace AppBundle\Command;

use AppBundle\Repository\DatahubData;
use Exception;
use Phpoaipmh\Endpoint;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FetchDataCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('app:fetch-data')
            ->addArgument("url", InputArgument::OPTIONAL, "The URL of the Datahub")

            // the short description shown while running "php bin/console list"
            ->setDescription('Fetches all data from the Datahub and stores the relevant information in a local database.')

            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('This command fetches all data from the Datahub and stores the relevant information in a local database.\nOptional parameter: the URL of the datahub. If the URL equals "skip", it will not fetch data and use whatever is currently in the database.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $url = $input->getArgument("url");
        $skip = false;
        if(!$url) {
            $url = $this->getContainer()->getParameter('datahub.url');
        } elseif($url === 'skip') {
            $skip = true;
        }

        $namespace = $this->getContainer()->getParameter('datahub.namespace');
        $metadataPrefix = $this->getContainer()->getParameter('datahub.metadataprefix');
        $dataDef = $this->getContainer()->getParameter('data_definition');
        $providerIds = $this->getContainer()->getParameter('providers');

        $providers = null;
        if(!$skip) {
            $myEndpoint = Endpoint::build($url);
            $recs = $myEndpoint->listRecords($metadataPrefix);

            DatahubData::clearData();
            $prov = array();
            $i = 0;
            foreach ($recs as $rec) {
                $i++;
                $data = $rec->metadata->children($namespace, true);
                $fetchedData = $this->fetchData($dataDef, $namespace, $data, $prov, $providerIds);
                DatahubData::storeData($fetchedData);
                if($i % 1000 === 0) {
                    echo 'At ' . $i . PHP_EOL;
                }
            }
            DatahubData::storeProviders($prov);
            $providers = array();
            foreach($prov as $provider) {
                $providers[] = $provider;
            }
        }
        else {
            $providers = DatahubData::getAllProviders();
        }

        $this->generateAndStoreReport($dataDef, $providers);
    }

    private function fetchData($dataDef, $namespace, $data, &$providers, $providerIds) {
        $result = array();
        foreach ($dataDef as $key => $value) {
            if($key === 'parent_xpath') {
                continue;
            }
            if(array_key_exists('xpath', $value)) {
                $xpath = $this->buildXpath($value['xpath'], $namespace);
                try {
                    $res = $data->xpath($xpath);
                    if ($res) {
                        $arr = array();
                        $sourceArr = array();
                        foreach ($res as $resChild) {
                            if($key === 'id') {
                                $attributes = $resChild->attributes($namespace, true);
                                if ($attributes && $attributes->source) {
                                    $sourceArr[] = (string)$attributes->source;
                                }
                            }

                            $child = (string)$resChild;
                            if (strlen($child) > 0 && strtolower($child) !== 'n/a') {
                                if ($key === 'provider') {
                                    $arr[] = $this->addToProviders($child, $providers, $providerIds);
                                } else {
                                    $arr[] = $child;
                                }
                            }
                        }
                        $result[$key] = $arr;
                        if($key === 'id') {
                            $result['source'] = $sourceArr;
                        }
                    } else {
                        $result[$key] = null;
                    }
                } catch (Exception $e) {
                    $result[$key] = null;
                }
            }
            elseif(array_key_exists('parent_xpath', $value)) {
                $xpath = $this->buildXpath($value['parent_xpath'], $namespace);
                try {
                    $res = $data->xpath($xpath);
                    if ($res) {
                        foreach($res as $r) {
                            $result[$key][] = $this->fetchData($value, $namespace, $r, $providers, $providerIds);
                        }
                    } else {
                        $result[$key] = null;
                    }
                } catch (Exception $e) {
                    $result[$key] = null;
                }
            }
        }
        return $result;
    }

    private function buildXpath($xpath, $namespace)
    {
        $xpath = str_replace('[@', '[@' . $namespace . ':', $xpath);
        $xpath = preg_replace('/\[([^@])/', '[' . $namespace . ':${1}', $xpath);
        $xpath = preg_replace('/\/([^\/])/', '/' . $namespace . ':${1}', $xpath);
        if(strpos($xpath, '/') !== 0) {
            $xpath = $namespace . ':' . $xpath;
        }
        $xpath = 'descendant::' . $xpath;
        return $xpath;
    }

    private function addToProviders($providerName, &$providers, $providerIds)
    {
        foreach ($providers as $provider) {
            if ($provider['name'] === $providerName) {
                return $provider['id'];
            }
        }
        if(array_key_exists($providerName, $providerIds))
            $providerId = $providerIds[$providerName];
        else {
            $providerId = preg_replace("/[^A-Za-z0-9 ]/", '', $providerName);
            while(strpos($providerId, '  ') > -1) {
                $providerId = str_replace('  ', ' ', $providerId);
            }
            $providerId = str_replace(' ', '_', $providerId);
            $providerId = strtolower($providerId);
            if(strlen($providerId) > 25) {
                $providerId = substr($providerId, 0, 25);
            }
        }
        $providers[] = array('id' => $providerId, 'name' => $providerName);
        echo 'Provider added: ' . $providerName . PHP_EOL;
        return $providerId;
    }

    private function generateAndStoreReport($dataDef, $providers)
    {
        foreach($providers as $provider) {
            $providerId = $provider['id'];

            $data = DatahubData::getAllData($providerId);

            $completeness = array(
                'provider' => $providerId,
                'total' => 0,
                'minimum' => 0,
                'basic' => 0
            );

            $fields = array('provider' => $providerId, 'minimum' => array(), 'basic' => array(), 'extended' => array());
            foreach ($dataDef as $key => $value) {
                if (array_key_exists('xpath', $value)) {
                    $fields[$value['class']][$key] = array();
                }
                elseif (array_key_exists('parent_xpath', $value)) {
                    foreach ($value as $k => $v) {
                        if ($k === 'parent_xpath') {
                            continue;
                        }
                        if (array_key_exists('xpath', $v)) {
                            $fields[$v['class']][$key . '/' . $k] = array();
                        }
                    }
                }
            }

            $termsWithIds = array('provider' => $providerId);
            $termIds = array();
            $termsWithIdFields = $this->getContainer()->getParameter('terms_with_ids');
            foreach($termsWithIdFields as $field) {
                $termIds[$field] = array();
            }

            foreach ($data as $record) {
                $minimumComplete = true;
                $basicComplete = true;
                foreach ($dataDef as $key => $value) {
                    if (array_key_exists('xpath', $value)) {
                        try {
                            if ($record->{$key} && count($record->{$key}) >= 1) {
                                $fields[$value['class']][$key][] = $record->_id;
                            }
                            else {
                                if ($value['class'] == 'minimum') {
                                    $minimumComplete = false;
                                    $basicComplete = false;
                                } elseif ($value['class'] == 'basic') {
                                    $basicComplete = false;
                                }
                            }
                        }
                        catch(Exception $e) {
                            if ($value['class'] == 'minimum') {
                                $minimumComplete = false;
                                $basicComplete = false;
                            } elseif ($value['class'] == 'basic') {
                                $basicComplete = false;
                            }
                        }
                    } elseif (array_key_exists('parent_xpath', $value)) {
                        foreach ($value as $k => $v) {
                            if ($k === 'parent_xpath') {
                                continue;
                            }
                            if (array_key_exists('xpath', $v)) {
                                $found = false;
                                foreach ($record as $recKey => $reco) {
                                    if($recKey === $key && $reco) {
                                        foreach ($reco as $rec) {
                                            if ($rec) {
                                                try {
                                                    if ($rec->{$k} && count($rec->{$k}) > 0) {
                                                        $found = true;
                                                        if($k == 'term') {
                                                            if($rec->id && count($rec->id) > 0) {
                                                                if(!array_key_exists($rec->term[0], $termIds[$key])) {
                                                                    $termIds[$key][$rec->term[0]] = $rec->id[0];
                                                                }
                                                            }
                                                        }
                                                    }
                                                } catch (Exception $e) {
                                                }
                                            }
                                        }
                                    }
                                }
                                if($found) {
                                    $fields[$v['class']][$key . '/' . $k][] = $record->_id;
                                }
                                else {
                                    if ($v['class'] == 'minimum') {
                                        $minimumComplete = false;
                                        $basicComplete = false;
                                    } elseif ($v['class'] == 'basic') {
                                        $basicComplete = false;
                                    }
                                }
                            }
                        }
                    }
                }
                $completeness['total']++;
                if ($minimumComplete) {
                    $completeness['minimum']++;
                }
                if ($basicComplete) {
                    $completeness['basic']++;
                }
            }

            foreach($termIds as $key => $terms) {
                $termsWithIds[$key] = count($terms);
            }

            DatahubData::storeReport($providerId, $completeness, $fields, $termsWithIds);
        }
    }
}
