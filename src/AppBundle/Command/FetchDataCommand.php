<?php
namespace AppBundle\Command;

use AppBundle\ProviderBundle\Document\Provider;
use AppBundle\RecordBundle\Document\Record;
use AppBundle\ReportBundle\Document\CompletenessReport;
use AppBundle\ReportBundle\Document\CompletenessTrend;
use AppBundle\ReportBundle\Document\FieldReport;
use AppBundle\ReportBundle\Document\FieldTrend;
use AppBundle\Util\RecordUtil;
use Phpoaipmh\Endpoint;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This command fetches data from a datahub repository, for which the URL is defined in parameters.yml.
 * The data to be fetched is defined in dashboard.yml and stored in a local database.
 * Lastly, the command generates a report & trend base on this data and stores these in a local database
 */
class FetchDataCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:fetch-data')
            ->addArgument("url", InputArgument::OPTIONAL, "The URL of the Datahub")
            ->setDescription('Fetches all data from the Datahub and stores the relevant information in a local database.')
            ->setHelp('This command fetches all data from the Datahub and stores the relevant information in a local database.\nOptional parameter: the URL of the datahub. If the URL equals "skip", it will not fetch data and use whatever is currently in the database.')
        ;
    }

    private function getDocumentManager()
    {
        return $this->getContainer()->get('doctrine_mongodb')->getManager();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $url = $input->getArgument("url");
        $skip = false;
        if(!$url) {
            $url = $this->getContainer()->getParameter('datahub_url');
        } elseif($url === 'skip') {
            $skip = true;
        }
        $verbose = $input->getOption('verbose');

        $namespace = $this->getContainer()->getParameter('datahub.namespace');
        $metadataPrefix = $this->getContainer()->getParameter('datahub.metadataprefix');
        $dataDef = $this->getContainer()->getParameter('data_definition');
        $providerDef = $this->getContainer()->getParameter('providers');

        $providers = null;
        if(!$skip) {
            // Build the OAI-PMH client
            $myEndpoint = Endpoint::build($url);

            // List the OAI-PMH records
            $recs = $myEndpoint->listRecords($metadataPrefix);

            $dm = $this->getDocumentManager();

            // Remove all current data in the local database
            $dm->getDocumentCollection('RecordBundle:Record')->remove([]);

            $providers = array();
            $recordIds = array();
            $i = 0;
            foreach ($recs as $rec) {
                $i++;
                $data = $rec->metadata->children($namespace, true);

                //Fetch the data from this record based on data_definition in dashboard.yml
                $fetchedData = $this->fetchData($dataDef, $namespace, $data, $providers, $providerDef, $verbose);

                // Create & store a new record based on this data
                if(array_key_exists('provider', $fetchedData)) {
                    if(count($fetchedData['provider']) > 0) {
                        $providerId = $fetchedData['provider'][0];
                        $record = new Record();
                        $record->setProvider($providerId);
                        $record->setData($fetchedData);
                        $dm->persist($record);

                        if (!array_key_exists($providerId, $recordIds)) {
                            $recordIds[$providerId] = array();
                        }
                        $recordIds[$providerId][] = $record->getId();
                    }
                }
                if($i % 100 === 0) {
                    $dm->flush();
                    $dm->clear();
                    if($verbose && $i % 1000 === 0) {
                        echo 'At ' . $i . PHP_EOL;
                    }
                }
            }
            $dm->flush();
            $dm->clear();
            $this->storeProviders($providers);
        }
        else {
            $providers = $this->getDocumentManager()->getRepository('ProviderBundle:Provider')->findAll();
        }

        // Generate & store the report & trends
        $this->generateAndStoreReport($dataDef, $providers, $recordIds);
    }

    private function fetchData($dataDef, $namespace, $data, &$providers, $providerDef, $verbose)
    {
        $result = array();
        foreach ($dataDef as $key => $value) {
            if(RecordUtil::excludeKey($key)) {
                continue;
            }
            if(array_key_exists('xpath', $value)) {
                $xpath = $this->buildXpath($value['xpath'], $namespace);
                $res = $data->xpath($xpath);
                if ($res) {
                    $arr = array();
                    foreach ($res as $resChild) {
                        $child = (string)$resChild;
                        if($key === 'id') {
                            $idArr = array('id' => $child);
                            $attributes = $resChild->attributes($namespace, true);
                            if ($attributes) {
                                foreach ($attributes as $attributeKey => $attributeValue) {
                                    $idArr[(string)$attributeKey] = (string)$attributeValue;
                                }
                            }
                            $arr[] = $idArr;
                        } elseif($key === 'term') {
                            $termArr = array('term' => $child);
                            $attributes = $resChild->attributes($namespace, true);
                            if ($attributes) {
                                foreach ($attributes as $attributeKey => $attributeValue) {
                                    $termArr[(string)$attributeKey] = (string)$attributeValue;
                                }
                            }
                            $arr[] = $termArr;
                        }
                        else {
                            if (strlen($child) > 0 && strtolower($child) !== 'n/a') {
                                if ($key === 'provider') {
                                    $arr[] = $this->addToProviders($child, $providers, $providerDef, $verbose);
                                } else {
                                    $arr[] = $child;
                                }
                            }
                        }
                    }
                    $result[$key] = $arr;
                } else {
                    $result[$key] = null;
                }
            }
            elseif(array_key_exists('parent_xpath', $value)) {
                $xpath = $this->buildXpath($value['parent_xpath'], $namespace);
                $res = $data->xpath($xpath);
                if ($res) {
                    foreach($res as $r) {
                        $result[$key][] = $this->fetchData($value, $namespace, $r, $providers, $providerDef, $verbose);
                    }
                } else {
                    $result[$key] = null;
                }
            }
        }
        return $result;
    }

    // Build the xpath based on the provided namespace
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

    // Add a newly found provider to the list of known data providers
    private function addToProviders($providerName, &$providers, $providerDef, $verbose)
    {
        foreach ($providers as $provider) {
            if ($provider->getName() === $providerName) {
                return $provider->getIdentifier();
            }
        }
        if(array_key_exists($providerName, $providerDef)) {
            $providerId = $providerDef[$providerName];
        } else {
            // Generate a new ID for this provider by removing non-alphanumeric characters and cutting off at 25 characters
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

        $provider = new Provider();
        $provider->setIdentifier($providerId);
        $provider->setName($providerName);
        $providers[] = $provider;
        if($verbose) {
            echo 'Provider added: ' . $providerName . PHP_EOL;
        }

        return $providerId;
    }

    private function storeProviders($providers)
    {
        $dm = $this->getDocumentManager();
        $dm->getDocumentCollection('ProviderBundle:Provider')->remove([]);
        foreach($providers as $provider) {
            $dm->persist($provider);
        }
        $dm->flush();
        $dm->clear();
    }

    private function getAllRecords($providerId, $field)
    {
        // Clear the document manager cache, otherwise it will just return old results with the wrong data
        $this->getDocumentManager()->flush();
        $this->getDocumentManager()->clear();

        $qb = $this->getDocumentManager()->createQueryBuilder('RecordBundle:Record')->field('provider')->equals($providerId)->select('data.' . $field);
        $query = $qb->getQuery();
        $data = $query->execute();
        return $data;
    }

    private function unsetCompleteness(&$complete, $value, $id) {
        $class = $value['class'];
        if(array_key_exists($class, $complete)) {
            $exclude = false;
            if(array_key_exists('exclude', $value)) {
                if($value['exclude'] === true) {
                    $exclude = true;
                }
            }
            if(!$exclude) {
                $index = array_search($id, $complete[$class]);
                if($index !== false) {
                    unset($complete[$class][$index]);
                }
                // If minimum registration is not fulfilled, then basic registration isn't either
                if($value['class'] === 'minimum') {
                    $index = array_search($id, $complete['basic']);
                    if($index !== false) {
                        unset($complete['basic'][$index]);
                    }
                }
            }
        }
    }

    private function generateAndStoreReport($dataDef, $providers, $recordIds)
    {
        $dm = $this->getDocumentManager();
        $dm->getDocumentCollection('ReportBundle:CompletenessReport')->remove([]);
        $dm->getDocumentCollection('ReportBundle:FieldReport')->remove([]);
        $dm->flush();
        $dm->clear();

        foreach($providers as $provider) {
            $providerId = $provider->getIdentifier();

            $fields = array(
                'minimum' => array(),
                'basic' => array(),
                'extended' => array(),
                'rights_data' => array(),
                'rights_work' => array(),
                'rights_digital_representation' => array()
            );

            $complete = array(
                'minimum' => array(),
                'basic' => array(),
                'rights_data' => array(),
                'rights_work' => array(),
                'rights_digital_representation' => array()
            );
            foreach($complete as $class => $arr) {
                foreach($recordIds[$providerId] as $recordId) {
                    $complete[$class][] = $recordId;
                }
            }

            $termIds = array();
            $termsWithIdFields = $this->getContainer()->getParameter('terms_with_ids');
            foreach($termsWithIdFields as $field) {
                $termIds[$field] = array();
            }

            foreach ($dataDef as $field => $value) {
                if (array_key_exists('xpath', $value)) {
                    if(array_key_exists('class', $value)) {
                        $fields[$value['class']][$field] = array();
                        $allRecords = $this->getAllRecords($providerId, $field);
                        if($allRecords) {
                            foreach ($allRecords as $record) {
                                $data = $record->getData();
                                $remove = true;
                                if (array_key_exists($field, $data)) {
                                    if(count($data[$field]) > 0) {
                                        $fields[$value['class']][$field][] = $record->getId();
                                        $remove = false;
                                    }
                                }
                                if($remove) {
                                    $this->unsetCompleteness($complete, $value, $record->getId());
                                }
                            }
                        }
                    }
                }
                elseif (array_key_exists('parent_xpath', $value)) {
                    $allRecords = $this->getAllRecords($providerId, $field);
                    if($allRecords) {
                        foreach ($value as $subField => $v) {
                            if (RecordUtil::excludeKey($subField) || !array_key_exists('class', $v)) {
                                continue;
                            }
                            if (array_key_exists('xpath', $v)) {
                                $fields[$v['class']][$field . '/' . $subField] = array();
                                foreach ($allRecords as $record) {
                                    $data = $record->getData();
                                    $remove = true;
                                    if (array_key_exists($field, $data)) {
                                        if(count($data[$field]) > 0) {
                                            if(array_key_exists($subField, $data[$field])) {
                                                if(count($data[$field][$subField]) > 0) {
                                                    $fields[$v['class']][$field . '/' . $subField][] = $record->getId();
                                                    $remove = false;

                                                    if($subField === 'id') {
                                                        // Consider record incomplete unless there is a purl id
                                                        $remove = true;
                                                        foreach($data[$field][$subField] as $id) {
                                                            if($id['type'] === 'purl') {
                                                                $remove = false;
                                                                break;
                                                            }
                                                        }
                                                    }

                                                    if ($subField === 'term') {
                                                        $term = RecordUtil::getPreferredTerm($data[$field][$subField]);
                                                        if ($term && array_key_exists('id', $data[$field])) {
                                                            if (is_array($data[$field]['id'])) {
                                                                if (count($data[$field]['id']) > 0 && !array_key_exists($term, $termIds[$field])) {
                                                                    $firstPurlId = null;
                                                                    foreach ($data[$field]['id'] as $termId) {
                                                                        if ($termId['type'] === 'purl') {
                                                                            $firstPurlId = $termId['id'];
                                                                            break;
                                                                        }
                                                                    }
                                                                    if ($firstPurlId != null) {
                                                                        $termIds[$field][$term] = $firstPurlId;
                                                                    }
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    if($remove) {
                                        $this->unsetCompleteness($complete, $v, $record->getId());
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $completenessReport = new CompletenessReport();
            $completenessReport->setProvider($providerId);
            $completenessReport->setTotal(count($recordIds[$providerId]));
            $completenessReport->setMinimum(count($complete['minimum']));
            $completenessReport->setBasic(count($complete['basic']));
            $completenessReport->setRightsData(count($complete['rights_data']));
            $completenessReport->setRightsWork(count($complete['rights_work']));
            $completenessReport->setRightsDigitalRepresentation(count($complete['rights_digital_representation']));
            $dm->persist($completenessReport);

            $completenessTrend = new CompletenessTrend();
            $completenessTrend->setProvider($providerId);
            $completenessTrend->setTimestamp(new \MongoDate());
            $completenessTrend->setTotal($completenessReport->getTotal());
            $completenessTrend->setMinimum($completenessReport->getMinimum());
            $completenessTrend->setBasic($completenessReport->getBasic());
            $completenessTrend->setRightsWork($completenessReport->getRightsWork());
            $completenessTrend->setRightsDigitalRepresentation($completenessReport->getRightsDigitalRepresentation());
            $completenessTrend->setRightsData($completenessReport->getRightsData());
            $dm->persist($completenessTrend);

            $fieldReport = new FieldReport();
            $fieldReport->setTotal($completenessReport->getTotal());
            $fieldReport->setProvider($providerId);
            $fieldReport->setMinimum($fields['minimum']);
            $fieldReport->setBasic($fields['basic']);
            $fieldReport->setExtended($fields['extended']);
            $dm->persist($fieldReport);

            $termsWithIds = array();
            foreach($termIds as $field => $terms) {
                $termsWithIds[$field] = count($terms);
            }
            $fieldTrend = new FieldTrend();
            $fieldTrend->setProvider($providerId);
            $fieldTrend->setTimestamp(new \MongoDate());
            $fieldTrend->setCounts($termsWithIds);
            $dm->persist($fieldTrend);

            $dm->flush();
            $dm->clear();
        }
    }
}
