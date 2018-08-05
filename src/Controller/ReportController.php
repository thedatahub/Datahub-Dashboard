<?php
/**
 * Created by PhpStorm.
 * User: mike
 * Date: 6/28/18
 * Time: 3:25 PM
 */

namespace App\Controller;


use App\Entity\Graph;
use App\Entity\Report;
use App\Repository\DatahubData;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

class ReportController extends AbstractController {

    private $dataDef;
    private $provider;

    /**
     * @Route("/report", name="report")
     */
    public function reports(Request $request) {
        $this->provider = urldecode($request->query->get('provider'));
        $aspect = urldecode($request->query->get('aspect'));
        $parameter = urldecode($request->query->get('parameter'));
        $question = urldecode($request->query->get('question'));
        if(!$aspect || !$parameter || !$question) {
            $aspect = 'Volledigheid';
            $parameter = 'Minimale registratie';
            $question = 'Overzicht van alle velden';
        }
        $title = $this->getParameter('title');
        $email = $this->getParameter('email');
        $leftMenu = $this->getParameter('left_menu');
        $this->dataDef = $this->getParameter('data_definition');
        $providers = DatahubData::getAllProviders();
        $route = str_replace('%20', '+', $this->generateUrl('report', array('provider' => $this->provider)));
        $download = str_replace('%20', '+', $this->generateUrl('download', array('provider' => $this->provider)));
        $functionCall = $leftMenu[$aspect][$parameter][$question];
        $report = $this->$functionCall();
        $data = array(
            'title' => $title,
            'email' => $email,
            'route' => $route,
            'download' => $download,
            'provider' => $this->provider,
            'providers' => $providers,
            'left_menu' => $leftMenu,
            'active_aspect' => $aspect,
            'active_parameter' => $parameter,
            'active_question' => $question,
            'report' => $report
        );
        return $this->render('report.html.twig', $data);
    }

    private function generateBarChart($csvData, $header) {
        $csvData = '"field","name","value"' . $csvData;
        return new Graph('barchart', $csvData, $header);
    }

    private function generatePieChart($pieces) {
        $pieChartData = '';
        foreach($pieces as $key => $value) {
            if(strlen($pieChartData) > 0)
                $pieChartData .= ",";
            $pieChartData .= '{"label":"' . $key . ' (' . $value . ')", "value":"' . $value . '"}';
        }
        return new Graph('piechart', '[' . $pieChartData . ']');
    }

    private function generateLineGraph($name, $type, $header) {
        $maxMonths = $this->getParameter('trends.max_history_months');
        $data = DatahubData::getTrend($this->provider, $name, $maxMonths);

        $lineChartData = 'date,value';
        foreach($data as $dataPoint)
            $lineChartData .= PHP_EOL . $dataPoint['timestamp']->toDateTime()->format('Y-m-d') . ' 00:00:00,' . $dataPoint[$type];
        return new Graph('linegraph', $lineChartData, $header);
    }

    private function fieldOverview($type, $title, $description) {
        $data = DatahubData::getReport($this->provider, $type);
        $csvData = '';
        foreach($data as $key => $value) {
            $label = null;
            if(strpos($key, '/')) {
                $parts = explode('/', $key);
                $label = $this->dataDef[$parts[0]][$parts[1]]['label'];
            }
            else
                $label = $this->dataDef[$key]['label'];
            $csvData .= PHP_EOL . "'" . $key . '","' . $label . '","' . count($value) . '"';
        }
        $barChart = $this->generateBarChart($csvData, 'Ingevulde records');
        $barChart->canDownload = true;
        return new Report($title, $title, $description, array($barChart));
    }

    private function fullRecords($name, $title, $description) {
        $data = DatahubData::getCompleteness($this->provider);
        $total = $data['total'];
        $done = $data[$name];
        $pieces = array('Volledige records' => $done, 'Onvolledige records' => $total - $done);
        $pieChart = $this->generatePieChart($pieces);
        if($total - $done == 0 && $done > 0) {
            $pieChart->isFull = true;
            $pieChart->fullText = 'Alle records zijn volledig ingevuld.';
        }
        else if($done == 0) {
            $pieChart->isEmpty = true;
            $pieChart->emptyText = 'Er zijn geen volledig ingevulde records.';
        }
        return new Report($title, $title, $description, array($pieChart));
    }

    private function minFieldOverview() {
        return $this->fieldOverview('minimum',
            'Minimale registratie - Overzicht velden',
            'Korte beschrijving (todo)');
    }

    private function minFullRecords() {
        return $this->fullRecords('minimum',
            'Minimale registratie - Volledig ingevulde records',
            'Korte beschrijving (todo)');
    }

    private function minTrend() {
        $title = 'Historiek minimale registratie';
        return new Report($title, $title, 'Korte beschrijving (todo)',
            array($this->generateLineGraph('completeness', 'minimum', 'Volledig ingevulde records')));
    }

    private function basicFieldOverview() {
        return $this->fieldOverview('basic',
            'Basisregistratie - Overzicht velden',
            'Korte beschrijving (todo)');
    }

    private function basicFullRecords() {
        return $this->fullRecords('basic',
            'Basisregistratie - Volledig ingevulde records',
            'Korte beschrijving (todo)');
    }

    private function basicTrend() {
        $title = 'Historiek basisregistratie';
        return new Report($title, $title, 'Korte beschrijving (todo)',
            array($this->generateLineGraph('completeness', 'basic', 'Volledig ingevulde records')));
    }

    private function extendedFieldOverview() {
        return $this->fieldOverview('extended',
            'Uitgebreide registratie - Overzicht velden',
            'Korte beschrijving (todo)');
    }

    private function ambigIds($field, $label) {
        $data = DatahubData::getAllData($this->provider);
        $ids = array();
        foreach ($data as $record) {
            if($record->{$field} && count($record->{$field}) > 0) {
                $id = $record->{$field}[0];
                if (!array_key_exists($id, $ids))
                    $ids[$id] = 1;
                else
                    $ids[$id]++;
            }
        }
        $counts = array();
        foreach($ids as $id => $count) {
            $count = $label . ' die ' . $count . 'x voorkomen';
            if(!array_key_exists($count, $counts))
                $counts[$count] = 1;
            else
                $counts[$count]++;
        }
        $pieChart = $this->generatePieChart($counts);
        $pieChart->canDownload = true;
        $isGood = false;
        if(count($counts) == 1 && array_key_exists($label . ' die 1x voorkomen', $counts))
            $isGood = true;
        if($isGood) {
            $pieChart->isFull = true;
            $pieChart->fullText = 'Alle ' . $label . ' komen exact 1x voor.';
        }
        $title = 'Ondubbelzinnigheid ' . $label;
        return new Report($title, $title, 'Korte beschrijving (todo)', array($pieChart));
    }

    private function ambigWorkPids() {
        return $this->ambigIds('work_pid', 'Work PID\'s');
    }

    private function ambigDataPids() {
        return $this->ambigIds('data_pid', 'Data PID\'s');
    }

    private function ambigTerms($field) {
        $data = DatahubData::getAllData($this->provider);
        $termsWithId = array();
        $termsWithoutId = array();
        $authorities = array();
        foreach ($data as $record) {
            if($record->{$field} && count($record->{$field}) > 0) {
                $rec = $record->{$field};
                foreach($rec as $r) {
                    if ($r->term && count($r->term) > 0) {
                        if($r->id && count($r->id) > 0) {
                            $id = $r->id[0];
                            if(!array_key_exists($r->term[0], $termsWithId))
                                $termsWithId[$r->term[0]] = $id;

                            if($r->source && count($r->source) > 0) {
                                $authority = $r->source[0];
                                if (array_key_exists($authority, $authorities)) {
                                    if(!in_array($id, $authorities[$authority]))
                                        $authorities[$authority][] = $id;
                                }
                                else
                                    $authorities[$authority] = array($id);
                            }
                        }
                        else {
                            if(!array_key_exists($r->term[0], $termsWithoutId))
                                $termsWithoutId[$r->term[0]] = '';
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
        else if(count($termsWithId) == 0) {
            $pieChart->isEmpty = true;
            $pieChart->emptyText = 'Er zijn geen termen met een ID.';
        }

        $csvData = '';
        foreach($authorities as $key => $value)
            $csvData .= PHP_EOL . '"' . $field . '","' . $key . '","' . count($value) . '"';
        $barChart = $this->generateBarChart($csvData, 'ID\'s voor deze authority');
        $barChart->canDownload = true;
        if(count($authorities) == 0)
            $barChart->isEmpty = true;

        $lineChart = $this->generateLineGraph('terms_with_ids', $field, 'Termen met ID');

        $title = 'Ondubbelzinnigheid ' . DownloadController::getFieldLabel($field, $this->dataDef);
        return new Report($title, $title, 'Korte beschrijving (todo)', array($pieChart, $barChart, $lineChart));
    }

    private function ambigObjectName() {
        return $this->ambigTerms('object_name');
    }

    private function ambigCategory() {
        return $this->ambigTerms('classification');
    }

    private function ambigMainMotif() {
        return $this->ambigTerms('main_motif');
    }

    private function ambigCreator() {
        return $this->ambigTerms('creator');
    }

    private function ambigMaterial() {
        return $this->ambigTerms('material');
    }

    private function ambigConcept() {
        return $this->ambigTerms('displayed_concept');
    }

    private function ambigSubject() {
        return $this->ambigTerms('displayed_subject');
    }

    private function ambigLocation() {
        return $this->ambigTerms('displayed_location');
    }

    private function ambigEvent() {
        return $this->ambigTerms('displayed_event');
    }

    private function richRecs($field) {
        $data = DatahubData::getAllData($this->provider);
        $counts = array();
        foreach ($data as $record) {
            if($record->{$field} && count($record->{$field}) > 0) {
                $count = count($record->{$field});
                if(array_key_exists($count, $counts))
                    $counts[$count]++;
                else
                    $counts[$count] = 1;
            }
        }

        ksort($counts);

        $csvData = '';
        foreach($counts as $key => $value)
            $csvData .= PHP_EOL . '"' . $field . '","'. $key . '","' . $value . '"';
        $barChart = $this->generateBarChart($csvData, 'Aantal records');
        if(count($counts) == 0) {
            $barChart->isEmpty = true;
            $barChart->emptyText = 'Er zijn geen records waarvoor dit veld werd ingevuld.';
        }

        $title = 'Rijkheid ' . DownloadController::getFieldLabel($field, $this->dataDef) . ' in records';
        return new Report($title, $title, 'Korte beschrijving (todo)', array($barChart));
    }

    private function richRecProviderName() {
        return $this->richRecs('provider_name');
    }

    private function richRecObjectId() {
        return $this->richRecs('database_id');
    }

    private function richRecDataPid() {
        return $this->richRecs('data_pid');
    }

    private function richRecTitle() {
        return $this->richRecs('title');
    }

    private function richRecShortDesc() {
        return $this->richRecs('short_description');
    }

    private function richRecObjectName() {
        return $this->richRecs('object_name');
    }

    private function richRecObjectCat() {
        return $this->richRecs('classification');
    }

    private function richRecMainMotif() {
        return $this->richRecs('main_motif');
    }

    private function richRecCreator() {
        return $this->richRecs('creator');
    }

    private function richRecMaterial() {
        return $this->richRecs('material');
    }

    private function richRecConcept() {
        return $this->richRecs('displayed_concept');
    }

    private function richRecSubject() {
        return $this->richRecs('displayed_subject');
    }

    private function richRecLocation() {
        return $this->richRecs('displayed_location');
    }

    private function richRecEvent() {
        return $this->richRecs('displayed_event');
    }

    private function richTerms($field) {
        $data = DatahubData::getAllData($this->provider);
        $counts = array();
        foreach ($data as $record) {
            if ($record->{$field} && count($record->{$field}) > 0) {
                $rec = $record->{$field};
                foreach ($rec as $r) {
                    if ($r->term && count($r->term) > 0) {
                        foreach($r->term as $term) {
                            if (array_key_exists($term, $counts))
                                $counts[$term]++;
                            else
                                $counts[$term] = 1;
                        }
                    }
                    else {
                        if (array_key_exists($rec, $counts))
                            $counts[$rec]++;
                        else
                            $counts[$rec] = 1;
                    }
                }
            }
        }

        arsort($counts);

        $csvData = '';
        foreach($counts as $key => $value)
            $csvData .= PHP_EOL . '"' . $field . '","' . $key . '","' . $value . '"';
        $barChart = $this->generateBarChart($csvData, 'Aantal records');
        if(count($counts) == 0) {
            $barChart->isEmpty = true;
            $barChart->emptyText = 'Er zijn geen termen voor dit veld.';
        }

        $title = 'Rijkheid ' . DownloadController::getFieldLabel($field, $this->dataDef);
        return new Report($title, $title, 'Korte beschrijving (todo)', array($barChart));
    }

    private function richTermObjectName() {
        return $this->richTerms('object_name');
    }

    private function richTermMainMotif() {
        return $this->richTerms('main_motif');
    }

    private function richTermCreator() {
        return $this->richTerms('creator');
    }

    private function richTermMaterial() {
        return $this->richTerms('material');
    }

    private function richTermConcept() {
        return $this->richTerms('displayed_concept');
    }

    private function richTermSubject() {
        return $this->richTerms('displayed_subject');
    }

    private function richTermLocation() {
        return $this->richTerms('displayed_location');
    }

    private function richTermEvent() {
        return $this->richTerms('displayed_event');
    }

    private function openWorkRecords() {
        $title = 'Openheid werk - records';
        return new Report($title, $title, 'Korte beschrijving (todo)', array());
    }

    private function openWorkTerms() {
        $title = 'Openheid werk - termen';
        return new Report($title, $title, 'Korte beschrijving (todo)', array());
    }

    private function openDigRepRecords() {
        $title = 'Openheid digitale representatie - records';
        return new Report($title, $title, 'Korte beschrijving (todo)', array());
    }

    private function openDigRepTerms() {
        $title = 'Openheid digitale representatie - termen';
        return new Report($title, $title, 'Korte beschrijving (todo)', array());
    }

    private function openRecordRecords() {
        $title = 'Openheid record - records';
        return new Report($title, $title, 'Korte beschrijving (todo)', array());
    }

    private function openRecordTerms() {
        $title = 'Openheid record - termen';
        return new Report($title, $title, 'Korte beschrijving (todo)', array());
    }
}
