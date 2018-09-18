<?php
namespace AppBundle\Controller;

use AppBundle\Entity\Graph;
use AppBundle\Entity\Report;
use AppBundle\Util\RecordUtil;
use MongoDB\BSON\UTCDateTime;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReportController extends Controller
{
    private $dataDef;
    private $provider;

    /**
     * @Route("/report/{provider}/{aspect}/{parameter}/{question}", name="report", requirements={"provider"="[^/]+", "aspect"="[^/]+", "parameter"="[^/]+", "question"="[^/]+"})
     */
    public function report($provider = '', $aspect = 'volledigheid', $parameter = 'minimaal', $question = 'overzicht')
    {
        $this->provider = $provider;

        $serviceName = $this->getParameter('service_name');
        $serviceAddress = $this->getParameter('service_address');
        $leftMenu = $this->getParameter('left_menu');
        $this->dataDef = $this->getParameter('data_definition');

        $providers = $this->getDocumentManager()->getRepository('ProviderBundle:Provider')->findAll();
        $providerName = null;
        foreach($providers as $provider) {
            if($provider->getIdentifier() == $this->provider) {
                $providerName = $provider->getName();
            }
        }

        $route = $this->generateUrl('report', array('provider' => $this->provider));
        $download = $this->generateUrl('download', array('provider' => $this->provider));

        $functionCall = null;
        $parameters = $leftMenu[ucfirst($aspect)];
        foreach($parameters as $param) {
            if($param['url'] === $parameter) {
                foreach ($param['list'] as $quest) {
                    if($quest['url'] === $question) {
                        $functionCall = $quest['function'];
                        break;
                    }
                }
            }
        }
        if(!$functionCall) {
            throw $this->createNotFoundException('Deze pagina bestaat niet.');
        }

        $report = $this->$functionCall();
        $data = array(
            'service_name' => $serviceName,
            'service_address' => $serviceAddress,
            'route' => $route,
            'download' => $download,
            'provider_id' => $this->provider,
            'provider_name' => $providerName,
            'providers' => $providers,
            'left_menu' => $leftMenu,
            'active_aspect' => $aspect,
            'active_parameter' => $parameter,
            'active_question' => $question,
            'report' => $report
        );
        return $this->render('report.html.twig', $data);
    }

    private function getDocumentManager()
    {
        return $this->get('doctrine_mongodb')->getManager();
    }

    private function getAllRecords()
    {
        return $this->getDocumentManager()->getRepository('RecordBundle:Record')->findBy(array('provider' => $this->provider));
    }

    private function generateBarChart($csvData, $header)
    {
        $csvData = '"field","name","value"' . $csvData;
        return new Graph('barchart', $csvData, $header);
    }

    private function generatePieChart($pieces)
    {
        $pieChartData = '';
        foreach($pieces as $key => $value) {
            if(strlen($pieChartData) > 0) {
                $pieChartData .= ",";
            }
            $pieChartData .= '{"label":"' . $key . ' (' . $value . ')", "value":"' . $value . '"}';
        }
        return new Graph('piechart', '[' . $pieChartData . ']');
    }

    private function generateLineGraph($lineChartData, $header)
    {
        return new Graph('linegraph', $lineChartData, $header);
    }

    private function getTrend($repository)
    {
        $maxMonths = $this->getParameter('trends.max_history_months');

        $curTime = new UTCDateTime();
        $curTs = $curTime->toDateTime()->getTimestamp() * 1000;
        $trend = $this->getDocumentManager()->getRepository($repository)->findBy(array(
            'provider' => $this->provider,
            "timestamp" => array('$lte' => new UTCDateTime(), '$gte' => new UTCDateTime($curTs - $maxMonths * 30 * 24 * 3600 * 1000))
        ));

        return $trend;
    }

    private function generateCompletenessTrendGraph($isMinimum, $isBasic, $header)
    {
        $trend = $this->getTrend('ReportBundle:CompletenessTrend');

        $lineChartData = 'date,value';
        foreach($trend as $dataPoint) {
            if($isMinimum) {
                $value = $dataPoint->getMinimum();
            } else if($isBasic) {
                $value = $dataPoint->getBasic();
            }
            $lineChartData .= '\n' . $dataPoint->getTimestamp()->format('Y-m-d') . ' 00:00:00,' . $value;
        }
        return new Graph('linegraph', $lineChartData, $header);
    }

    private function generateFieldTrendGraph($type, $header)
    {
        $trend = $this->getTrend('ReportBundle:FieldTrend');

        $lineChartData = 'date,value';
        foreach($trend as $dataPoint) {
            $lineChartData .= '\n' . $dataPoint->getTimestamp()->format('Y-m-d') . ' 00:00:00,' . $dataPoint->getCounts()[$type];
        }
        return $this->generateLineGraph($lineChartData, $header);
    }

    private function fieldOverview($isMinimum, $isBasic, $isExtended, $title, $description)
    {
        $reports = $this->getDocumentManager()->getRepository('ReportBundle:FieldReport')->findBy(array('provider' => $this->provider));
        $csvData = '';
        $total = 0;
        if($reports && count($reports) > 0) {
            $report = $reports[0];
            $total = $report->getTotal();
            if($isMinimum) {
                $data = $report->getMinimum();
            } else if($isBasic) {
                $data = $report->getBasic();
            } else if($isExtended) {
                $data = $report->getExtended();
            }
            foreach ($data as $key => $value) {
                $label = null;
                if (strpos($key, '/')) {
                    $parts = explode('/', $key);
                    $label = $this->dataDef[$parts[0]][$parts[1]]['label'];
                    $key = $parts[0];
                } else {
                    $label = $this->dataDef[$key]['label'];
                }
                $csvData .= PHP_EOL . '"' . $key . '","' . $label . '","' . count($value) . '"';
            }
        }
        $barChart = $this->generateBarChart($csvData, 'Ingevulde records');
        $barChart->canDownload = true;
        $barChart->max = $total;
        return new Report($title, $title, $description, array($barChart));
    }

    private function fullRecords($isBasic, $isMinimum, $title, $description)
    {
        $reports = $this->getDocumentManager()->getRepository('ReportBundle:CompletenessReport')->findBy(array('provider' => $this->provider));
        $done = 0;
        if($reports && count($reports) > 0) {
            $report = $reports[0];
            $total = $report->getTotal();
            if ($isBasic) {
                $done = $report->getbasic();
            } else if ($isMinimum) {
                $done = $report->getMinimum();
            }
        }
        $pieces = array('Volledige records' => $done, 'Onvolledige records' => $total - $done);
        $pieChart = $this->generatePieChart($pieces);
        if($total - $done == 0 && $done > 0) {
            $pieChart->isFull = true;
            $pieChart->fullText = 'Alle records zijn volledig ingevuld.';
        }
        elseif($done == 0) {
            $pieChart->isEmpty = true;
            $pieChart->emptyText = 'Er zijn geen volledig ingevulde records.';
        }
        return new Report($title, $title, $description, array($pieChart));
    }

    private function minFieldOverview()
    {
        return $this->fieldOverview(
            true,
            false,
            false,
            'Minimale registratie - Overzicht velden',
            'Korte beschrijving (todo)'
        );
    }

    private function minFullRecords()
    {
        return $this->fullRecords(
            false,
            true,
            'Minimale registratie - Volledig ingevulde records',
            'Korte beschrijving (todo)'
        );
    }

    private function minTrend()
    {
        $title = 'Historiek minimale registratie';
        return new Report(
            $title,
            $title,
            'Korte beschrijving (todo)',
            array($this->generateCompletenessTrendGraph(
                true, false, 'Volledig ingevulde records'
            ))
        );
    }

    private function basicFieldOverview()
    {
        return $this->fieldOverview(
            false,
            true,
            false,
            'Basisregistratie - Overzicht velden',
            'Korte beschrijving (todo)'
        );
    }

    private function basicFullRecords()
    {
        return $this->fullRecords(
            true,
            false,
            'Basisregistratie - Volledig ingevulde records',
            'Korte beschrijving (todo)'
        );
    }

    private function basicTrend()
    {
        $title = 'Historiek basisregistratie';
        return new Report(
            $title,
            $title,
            'Korte beschrijving (todo)',
            array($this->generateCompletenessTrendGraph(
                false, true, 'Volledig ingevulde records'
            ))
        );
    }

    private function extendedFieldOverview()
    {
        return $this->fieldOverview(
            false,
            false,
            true,
            'Uitgebreide registratie - Overzicht velden',
            'Korte beschrijving (todo)');
    }

    private function ambigIds($field, $label)
    {
        $allRecords = $this->getAllRecords();
        $counts = array();
        if($allRecords) {
            $ids = array();
            foreach ($allRecords as $record) {
                $data = $record->getData();
                if ($data[$field] && count($data[$field]) > 0) {
                    $id = $data[$field][0];
                    if (!array_key_exists($id, $ids)) {
                        $ids[$id] = 1;
                    } else {
                        $ids[$id]++;
                    }
                }
            }
            foreach ($ids as $id => $count) {
                $count = $label . ' die ' . $count . 'x voorkomen';
                if (!array_key_exists($count, $counts)) {
                    $counts[$count] = 1;
                } else {
                    $counts[$count]++;
                }
            }
        }

        $pieChart = $this->generatePieChart($counts);
        $pieChart->canDownload = true;
        $isGood = false;
        if(count($counts) == 1 && array_key_exists($label . ' die 1x voorkomen', $counts)) {
            $isGood = true;
        }
        if($isGood) {
            $pieChart->isFull = true;
            $pieChart->fullText = 'Alle ' . $label . ' komen exact 1x voor.';
        }
        $title = 'Ondubbelzinnigheid ' . $label;
        return new Report($title, $title, 'Korte beschrijving (todo)', array($pieChart));
    }

    private function ambigWorkPids()
    {
        return $this->ambigIds('work_pid', 'Work PID\'s');
    }

    private function ambigDataPids()
    {
        return $this->ambigIds('data_pid', 'Data PID\'s');
    }

    private function ambigTerms($field)
    {
        $allRecords = $this->getAllRecords();
        $termsWithId = array();
        $termsWithoutId = array();
        $authorities = array();
        if($allRecords) {
            foreach ($allRecords as $record) {
                $data = $record->getData();
                if ($data[$field] && count($data[$field]) > 0) {
                    $fieldValues = $data[$field];
                    foreach ($fieldValues as $fieldValue) {
                        if ($fieldValue['term'] && count($fieldValue['term']) > 0) {
                            $preferredTerm = RecordUtil::getPreferredTerm($fieldValue['term']);
                            if($preferredTerm) {
                                $firstPurlId = null;
                                if ($fieldValue['id'] && count($fieldValue['id']) > 0) {
                                    foreach ($fieldValue['id'] as $termId) {
                                        if (array_key_exists('source', $termId)) {
                                            $id = $termId['id'];
                                            $authority = $termId['source'];
                                            if ($termId['type'] === 'purl') {
                                                if (!$firstPurlId) {
                                                    $firstPurlId = $id;
                                                }
                                                if (array_key_exists($authority, $authorities)) {
                                                    if (!in_array($id, $authorities[$authority])) {
                                                        $authorities[$authority][] = $id;
                                                    }
                                                } else {
                                                    $authorities[$authority] = array($id);
                                                }
                                            }
                                        }
                                    }
                                    if ($firstPurlId) {
                                        if(!array_key_exists($preferredTerm, $termsWithId)) {
                                            $termsWithId[$preferredTerm] = $firstPurlId;
                                        }
                                    } else {
                                        if (!array_key_exists($preferredTerm, $termsWithoutId)) {
                                            $termsWithoutId[$preferredTerm] = '';
                                        }
                                    }
                                } else {
                                    if (!array_key_exists($preferredTerm, $termsWithoutId)) {
                                        $termsWithoutId[$preferredTerm] = '';
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        $pieces = array('Termen met ID' => count($termsWithId), 'Termen zonder ID' => count($termsWithoutId));
        $pieChart = $this->generatePieChart($pieces);
        $pieChart->canDownload = true;
        if(count($termsWithoutId) == 0 && count($termsWithId) > 0) {
            $pieChart->isFull = true;
            $pieChart->fullText = 'Alle termen hebben een ID.';
        }
        elseif(count($termsWithId) == 0) {
            $pieChart->isEmpty = true;
            if(count($termsWithoutId) == 0) {
                $pieChart->emptyText = 'Er zijn geen records waarvoor dit veld is ingevuld.';
                $pieChart->canDownload = false;
            } else {
                $pieChart->emptyText = 'Er zijn geen termen met een ID.';
            }
        }

        $csvData = '';
        foreach($authorities as $key => $value) {
            $csvData .= PHP_EOL . '"' . $key . '","' . $key . '","' . count($value) . '"';
        }
        $barChart = $this->generateBarChart($csvData, 'ID\'s voor deze authority');
        $barChart->canDownload = true;
        if(count($authorities) == 0) {
            $barChart->isEmpty = true;
            if(count($termsWithId) > 0) {
                $barChart->emptyText = "Er zijn geen authorities voor deze termen";
            }
        } else {
            $barChart->max = count($termsWithId);
        }

        $lineChart = $this->generateFieldTrendGraph($field, 'Termen met ID');

        $title = 'Ondubbelzinnigheid ' . RecordUtil::getFieldLabel($field, $this->dataDef);
        return new Report($title, $title, 'Korte beschrijving (todo)', array($pieChart, $barChart, $lineChart));
    }

    private function ambigObjectName()
    {
        return $this->ambigTerms('object_name');
    }

    private function ambigCategory()
    {
        return $this->ambigTerms('classification');
    }

    private function ambigMainMotif()
    {
        return $this->ambigTerms('main_motif');
    }

    private function ambigCreator()
    {
        return $this->ambigTerms('creator');
    }

    private function ambigMaterial()
    {
        return $this->ambigTerms('material');
    }

    private function ambigConcept()
    {
        return $this->ambigTerms('displayed_concept');
    }

    private function ambigSubject()
    {
        return $this->ambigTerms('displayed_subject');
    }

    private function ambigLocation()
    {
        return $this->ambigTerms('displayed_location');
    }

    private function ambigEvent()
    {
        return $this->ambigTerms('displayed_event');
    }

    private function richRecs($field)
    {
        $allRecords = $this->getAllRecords();
        $counts = array();
        if($allRecords) {
            foreach ($allRecords as $record) {
                $data = $record->getData();
                if ($data[$field] && count($data[$field]) > 0) {
                    $count = count($data[$field]);
                    if (array_key_exists($count, $counts)) {
                        $counts[$count]++;
                    } else {
                        $counts[$count] = 1;
                    }
                }
            }
        }

        ksort($counts);

        $csvData = '';
        foreach($counts as $key => $value) {
            $csvData .= PHP_EOL . '"' . $field . '","' . $key . '","' . $value . '"';
        }
        $barChart = $this->generateBarChart($csvData, 'Aantal records');
        if(count($counts) == 0) {
            $barChart->isEmpty = true;
            $barChart->emptyText = 'Er zijn geen records waarvoor dit veld werd ingevuld.';
        }

        $title = 'Rijkheid ' . RecordUtil::getFieldLabel($field, $this->dataDef) . ' in records';
        return new Report($title, $title, 'Korte beschrijving (todo)', array($barChart));
    }

    private function richRecProviderName()
    {
        return $this->richRecs('provider');
    }

    private function richRecObjectId()
    {
        return $this->richRecs('database_id');
    }

    private function richRecDataPid()
    {
        return $this->richRecs('data_pid');
    }

    private function richRecTitle()
    {
        return $this->richRecs('title');
    }

    private function richRecShortDesc()
    {
        return $this->richRecs('short_description');
    }

    private function richRecObjectName()
    {
        return $this->richRecs('object_name');
    }

    private function richRecObjectCat()
    {
        return $this->richRecs('classification');
    }

    private function richRecMainMotif()
    {
        return $this->richRecs('main_motif');
    }

    private function richRecCreator()
    {
        return $this->richRecs('creator');
    }

    private function richRecMaterial()
    {
        return $this->richRecs('material');
    }

    private function richRecConcept()
    {
        return $this->richRecs('displayed_concept');
    }

    private function richRecSubject()
    {
        return $this->richRecs('displayed_subject');
    }

    private function richRecLocation()
    {
        return $this->richRecs('displayed_location');
    }

    private function richRecEvent()
    {
        return $this->richRecs('displayed_event');
    }

    private function richTerms($field)
    {
        $undefinedKey = 'niet gedefinieerd';

        $allRecords = $this->getAllRecords();
        $counts = array();
        if($allRecords) {
            foreach ($allRecords as $record) {
                $data = $record->getData();
                if ($data[$field] && count($data[$field]) > 0) {
                    $fieldValues = $data[$field];
                    foreach ($fieldValues as $fieldValue) {
                        if ($fieldValue['term'] && count($fieldValue['term']) > 0) {
                            $preferredTerm = RecordUtil::getPreferredTerm($fieldValue['term']);
                            if($preferredTerm) {
                                if (array_key_exists($preferredTerm, $counts)) {
                                    $counts[$preferredTerm]++;
                                } else {
                                    $counts[$preferredTerm] = 1;
                                }
                            }
                        }
                    }
                } else {
                    if (array_key_exists($undefinedKey, $counts)) {
                        $counts[$undefinedKey]++;
                    } else {
                        $counts[$undefinedKey] = 1;
                    }
                }
            }
        }

        arsort($counts);

        $csvData = '';
        foreach($counts as $key => $value) {
            $csvData .= PHP_EOL . '"' . $field . '","' . $key . '","' . $value . '"';
        }
        $barChart = $this->generateBarChart($csvData, 'Aantal records');
        if(count($counts) == 0) {
            $barChart->isEmpty = true;
            $barChart->emptyText = 'Er zijn geen termen voor dit veld.';
        }

        $title = 'Rijkheid ' . RecordUtil::getFieldLabel($field, $this->dataDef);
        return new Report($title, $title, 'Korte beschrijving (todo)', array($barChart));
    }

    private function richTermObjectName()
    {
        return $this->richTerms('object_name');
    }

    private function richTermMainMotif()
    {
        return $this->richTerms('main_motif');
    }

    private function richTermCreator()
    {
        return $this->richTerms('creator');
    }

    private function richTermMaterial()
    {
        return $this->richTerms('material');
    }

    private function richTermConcept()
    {
        return $this->richTerms('displayed_concept');
    }

    private function richTermSubject()
    {
        return $this->richTerms('displayed_subject');
    }

    private function richTermLocation()
    {
        return $this->richTerms('displayed_location');
    }

    private function richTermEvent()
    {
        return $this->richTerms('displayed_event');
    }

    private function openWorkRecords()
    {
        $title = 'Openheid werk - records';
        return new Report($title, $title, 'Korte beschrijving (todo)', array());
    }

    private function openWorkTerms()
    {
        $title = 'Openheid werk - termen';
        return new Report($title, $title, 'Korte beschrijving (todo)', array());
    }

    private function openDigRepRecords()
    {
        $title = 'Openheid digitale representatie - records';
        return new Report($title, $title, 'Korte beschrijving (todo)', array());
    }

    private function openDigRepTerms()
    {
        $title = 'Openheid digitale representatie - termen';
        return new Report($title, $title, 'Korte beschrijving (todo)', array());
    }

    private function openRecordRecords()
    {
        $title = 'Openheid record - records';
        return new Report($title, $title, 'Korte beschrijving (todo)', array());
    }

    private function openRecordTerms()
    {
        $title = 'Openheid record - termen';
        return new Report($title, $title, 'Korte beschrijving (todo)', array());
    }
}
