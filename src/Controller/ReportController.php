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
        $curMenu = urldecode($request->query->get('menu'));
        $curSubMenu = urldecode($request->query->get('submenu'));
        $curSubSubMenu = urldecode($request->query->get('subsubmenu'));
        if(!$curMenu || !$curSubMenu || !$curSubSubMenu) {
            $curMenu = 'Volledigheid';
            $curSubMenu = 'Minimale registratie';
            $curSubSubMenu = 'Overzicht van alle velden';
        }
        $title = $this->getParameter('title');
        $email = $this->getParameter('email');
        $leftMenu = $this->getParameter('left_menu');
        if(!$this->dataDef)
            $this->dataDef = $this->getParameter('data_definition');
        $providers = DatahubData::getAllProviders();
        $route = str_replace('%20', '+', $this->generateUrl('report', array('provider' => $this->provider)));
        $functionCall = $leftMenu[$curMenu][$curSubMenu][$curSubSubMenu];
        $reports = $this->$functionCall();
        $data = array(
            'title' => $title,
            'email' => $email,
            'route' => $route,
            'provider' => $this->provider,
            'providers' => $providers,
            'left_menu' => $leftMenu,
            'cur_menu' => $curMenu,
            'cur_sub_menu' => $curSubMenu,
            'cur_sub_sub_menu' => $curSubSubMenu,
            'reports' => $reports
        );
        return $this->render('report.html.twig', $data);
    }

    private function fieldOverview($type) {
        $data = DatahubData::getReport($this->provider, $type);
        $csvData = "name,value";
        foreach($data as $key => $value) {
            $label = null;
            if(strpos($key, '/')) {
                $parts = explode('/', $key);
                $label = $this->dataDef[$parts[0]][$parts[1]]['label'];
            }
            else
                $label = $this->dataDef[$key]['label'];
            $csvData .= "\n" . $label . "," . count($value);
        }
        return array(new Report("barchart.html.twig", $csvData, "ingevulde records"));
    }

    private function fullRecords($name) {
        $data = DatahubData::getCompleteness($this->provider);
        $total = $data['total'];
        $done = $data[$name];
        $pieces = array("Onvolledige records" => $total - $done, "Volledige records" => $done);
        return array(new Report("piechart.html.twig", $this->generatePieChart($pieces)));
    }

    private function generatePieChart($pieces) {
        $pieChartData = '';
        $firstkey = null;
        foreach($pieces as $key => $value) {
            if(!$firstkey)
                $firstkey = $key;
            if($value > 0) {
                if(strlen($pieChartData) > 0)
                    $pieChartData .= ",";
                $pieChartData .= '{"label":"' . $key . ' (' . $value . ')", "value":"' . $value . '"}';
            }
        }
        if(strlen($pieChartData) == 0 && $firstkey)
            $pieChartData = '{"label":"' . $firstkey . ' (0)", "value":"1"}';
        return "[" . $pieChartData . "]";
    }

    private function trend($name) {
        $maxMonths = $this->getParameter('trends.max_history_months');
        $data = DatahubData::getTrend($this->provider, 'completeness', $maxMonths);

        $lineChartData = "date,value";
        foreach($data as $dataPoint)
            $lineChartData .= "\n" . $dataPoint['timestamp']->toDateTime()->format('Y-m-d') . ' 00:00:00,' . $dataPoint[$name];
        return array(new Report("linegraph.html.twig", $lineChartData, "Volledig ingevulde records"));
    }

    private function minFieldOverview() {
        return $this->fieldOverview('minimum');
    }

    private function minFullRecords() {
        return $this->fullRecords('minimum');
    }

    private function minTrend() {
        return $this->trend('minimum');
    }

    private function basicFieldOverview() {
        return $this->fieldOverview('basic');
    }

    private function basicFullRecords() {
        return $this->fullRecords('basic');
    }

    private function basicTrend() {
        return $this->trend('basic');
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
            $count = $label . " die " . $count . "x voorkomen";
            if(!array_key_exists($count, $counts))
                $counts[$count] = 1;
            else
                $counts[$count]++;
        }
        return array(new Report("piechart.html.twig", $this->generatePieChart($counts)));
    }

    private function ambigWorkPids() {
        return $this->ambigIds('work_pid', "Work PID's");
    }

    private function ambigDataPids() {
        return $this->ambigIds('data_pid', "Data PID's");
    }

    private function ambigTerms($parentKey, $termKey, $idKey) {
        $data = DatahubData::getAllData($this->provider);
        $termsWithId = 0;
        $termsWithoutId = 0;
        foreach ($data as $record) {
            if($record->{$parentKey} && count($record->{$parentKey}) > 0) {
                $rec = $record->{$parentKey};
                foreach($rec as $r) {
                    if ($r->{$termKey} && count($r->{$termKey}) > 0) {
                        if($r->{$idKey} && count($r->{$idKey}) > 0)
                            $termsWithId++;
                        else
                            $termsWithoutId++;
                    }
                }
            }
        }
        $pieces = array('Termen met ID' => $termsWithId, 'Termen zonder ID' => $termsWithoutId);
        return array(new Report("piechart.html.twig", $this->generatePieChart($pieces)));
    }

    private function ambigObjectName() {
        return $this->ambigTerms('object_name', 'term', 'id');
    }

    private function ambigCatagory() {
        return $this->ambigTerms('classification', 'term', 'id');
    }

    private function ambigMainMotif() {
        return $this->ambigTerms('main_motif', 'term', 'id');
    }

    private function ambigCreator() {
        return $this->ambigTerms('creator', 'name_term', 'name_id');
    }

    private function ambigMaterial() {
        return $this->ambigTerms('material', 'term', 'id');
    }

    private function ambigConcept() {
        return $this->ambigTerms('displayed_concept', 'term', 'id');
    }

    private function ambigSubject() {
        return $this->ambigTerms('displayed_subject', 'term', 'id');
    }

    private function ambigLocation() {
        return $this->ambigTerms('displayed_location', 'term', 'id');
    }

    private function ambigEvent() {
        return $this->ambigTerms('displayed_event', 'term', 'id');
    }


}
