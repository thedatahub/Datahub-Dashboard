<?php
/**
 * Created by PhpStorm.
 * User: mike
 * Date: 6/28/18
 * Time: 3:25 PM
 */

namespace App\Controller;


use App\Entity\Report;
use App\Repository\DatahubData;
use Exception;
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
        $category = urldecode($request->query->get('category'));
        $menu = urldecode($request->query->get('menu'));
        $question = urldecode($request->query->get('question'));
        if(!$category || !$menu || !$question) {
            $category = 'Volledigheid';
            $menu = 'Minimale registratie';
            $question = 'Overzicht van alle velden';
        }
        $title = $this->getParameter('title');
        $email = $this->getParameter('email');
        $leftMenu = $this->getParameter('left_menu');
        if(!$this->dataDef)
            $this->dataDef = $this->getParameter('data_definition');
        $providers = DatahubData::getAllProviders();
        $route = str_replace('%20', '+', $this->generateUrl('report', array('provider' => $this->provider)));
        $functionCall = $leftMenu[$category][$menu][$question];
        $reports = $this->$functionCall();
        $data = array(
            'title' => $title,
            'email' => $email,
            'route' => $route,
            'provider' => $this->provider,
            'providers' => $providers,
            'left_menu' => $leftMenu,
            'active_category' => $category,
            'active_menu' => $menu,
            'active_question' => $question,
            'reports' => $reports
        );
        return $this->render('report.html.twig', $data);
    }

    private function generateBarChart($csvData, $header) {
        return new Report('barchart.html.twig', 'name,value' . $csvData, $header);
    }

    private function generatePieChart($pieces) {
        $pieChartData = '';
        foreach($pieces as $key => $value) {
            if(strlen($pieChartData) > 0)
                $pieChartData .= ",";
            $pieChartData .= '{"label":"' . $key . ' (' . $value . ')", "value":"' . $value . '"}';
        }
        return new Report('piechart.html.twig', '[' . $pieChartData . ']');
    }

    private function generateLineChart($name, $type, $header) {
        $maxMonths = $this->getParameter('trends.max_history_months');
        $data = DatahubData::getTrend($this->provider, $name, $maxMonths);

        $lineChartData = 'date,value';
        foreach($data as $dataPoint)
            $lineChartData .= '\n' . $dataPoint['timestamp']->toDateTime()->format('Y-m-d') . ' 00:00:00,' . $dataPoint[$type];
        return new Report('linegraph.html.twig', $lineChartData, $header);
    }

    private function fieldOverview($type) {
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
            $csvData .= '\n' . $label . ',' . count($value);
        }
        return array($this->generateBarChart($csvData, 'Ingevulde records'));
    }

    private function fullRecords($name) {
        $data = DatahubData::getCompleteness($this->provider);
        $total = $data['total'];
        $done = $data[$name];
        $pieces = array('Volledige records' => $done, 'Onvolledige records' => $total - $done);
        $pieChart = $this->generatePieChart($pieces);
        if($total - $done == 0 && $done > 0) {
            $pieChart->isFull = true;
            $pieChart->fullText = 'Dit veld is ingevuld in alle records.';
        }
        else if($done == 0) {
            $pieChart->isEmpty = true;
            $pieChart->emptyText = 'Er zijn geen records aanwezig waarvoor dit veld is ingevuld.';
        }
        return array($pieChart);
    }

    private function minFieldOverview() {
        return $this->fieldOverview('minimum');
    }

    private function minFullRecords() {
        return $this->fullRecords('minimum');
    }

    private function minTrend() {
        return array($this->generateLineChart('completeness', 'minimum', 'Volledig ingevulde records'));
    }

    private function basicFieldOverview() {
        return $this->fieldOverview('basic');
    }

    private function basicFullRecords() {
        return $this->fullRecords('basic');
    }

    private function basicTrend() {
        return array($this->generateLineChart('completeness', 'basic', 'Volledig ingevulde records'));
    }

    private function extendedFieldOverview() {
        return $this->fieldOverview('extended');
    }

    private function ambigIds($name, $label) {
        $data = DatahubData::getAllData($this->provider);
        $ids = array();
        foreach ($data as $record) {
            if($record->{$name} && count($record->{$name}) > 0) {
                $id = $record->{$name}[0];
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
        $report = $this->generatePieChart($counts);
        $isGood = false;
        if(count($counts) == 1 && array_key_exists($label . ' die 1x voorkomen', $counts))
            $isGood = true;
        if($isGood) {
            $report->isFull = true;
            $report->fullText = 'Alle ' . $label . ' komen exact 1x voor.';
        }
        return array($report);
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
                            $pos = strrpos($id, '/');
                            if($pos) {
                                $authority = substr($id, 0, $pos);
                                $expl = explode('/', $authority);
                                $authority = end($expl);
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
            $csvData .= '\n' . $key . ',' . count($value);
        $barChart = $this->generateBarChart($csvData, 'ID\'s voor deze authority');
        if(count($authorities) == 0)
            $barChart->isEmpty = true;

        $lineChart = $this->generateLineChart('terms_with_ids', $field, 'Termen met ID');

        return array($pieChart, $barChart, $lineChart);
    }

    private function ambigObjectName() {
        return $this->ambigTerms('object_name');
    }

    private function ambigCatagory() {
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
}
