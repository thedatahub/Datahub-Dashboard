<?php
/**
 * Created by PhpStorm.
 * User: mike
 * Date: 7/31/18
 * Time: 4:22 PM
 */

namespace App\Controller;


use App\Repository\DatahubData;
use Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DownloadController extends AbstractController {

    private $provider = null;
    private $dataDef = null;
    private $field = null;

    /**
     * @Route("/download", name="download")
     */
    public function download(Request $request) {
        $this->provider = urldecode($request->query->get('provider'));
        $aspect = urldecode($request->query->get('aspect'));
        $parameter = urldecode($request->query->get('parameter'));
        $question = urldecode($request->query->get('question'));
        $chart = urldecode($request->query->get('chart'));
        if($request->query->get('field')) {
            $this->field = urldecode($request->query->get('field'));
            $this->dataDef = $this->getParameter('data_definition');
        }
        $leftMenu = $this->getParameter('left_menu');
        try {
            $functionCall = $leftMenu[$aspect][$parameter][$question] . ucfirst($chart);
            $csvData = $this->$functionCall();

            // Generate response
            $response = new Response();
            $filename = $aspect . '_' . $parameter . '_' . $question . '.csv';

            // Set headers
            $response->headers->set('Cache-Control', 'private');
            $response->headers->set('Content-type', 'text/csv');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '";');
            $response->headers->set('Content-length', strlen($csvData));
            $response->sendHeaders();

            $response->setContent($csvData);

            return $response;
        }
        catch(Exception $e) {
            throw $e;
//            throw $this->createNotFoundException('Ongeldige URL voor CSV-data: ');
        }
    }

    private function fieldOverview($type) {
        $data = DatahubData::getAllData($this->provider);

        $label = null;
        if(strpos($this->field, '/')) {
            $parts = explode('/', $this->field);
            $label = $this->dataDef[$parts[0]][$parts[1]]['label'];
        }
        else
            $label = $this->dataDef[$this->field]['label'];
        $csvData = '';

        foreach($data as $record) {
            $applicationId = '';
            if ($record->application_id && count($record->application_id) > 0)
                $applicationId = $record->application_id[0];
            $objectNumber = '';
            if ($record->object_number && count($record->object_number) > 0)
                $objectNumber = $record->object_number[0];
            $csvData .= PHP_EOL . $applicationId . ',' . $objectNumber . ',' . ($record->{$this->field} && count($record->{$this->field}) > 0 ? 'ingevuld' : 'niet ingevuld');
        }
        return 'Applicatie ID,Objectnummer,' . $label . $csvData;
    }

    private function minFieldOverviewBarchart() {
        return $this->fieldOverview('minimum');
    }

    private function basicFieldOverviewBarchart() {
        return $this->fieldOverview('basic');
    }

    private function extendedFieldOverviewBarchart() {
        return $this->fieldOverview('extended');
    }

    private function ambigWorkPidsPiechart() {
        return 'WPID';
    }

    private function ambigDataPidsPiechart() {
        return "DPID";
    }

    private function ambigObjectNamePiechart() {
        return 'np';
    }

    private function ambigObjectNameBarchart() {
        return 'nb';
    }

    private function ambigCategoryPiechart() {
        return 'cp';
    }

    private function ambigCategoryBarchart() {
        return 'cb';
    }

    private function ambigMainMotifPiechart() {
        return 'mmp';
    }

    private function ambigMainMotifBarchart() {
        return 'mmb';
    }

    private function ambigCreatorPiechart() {
        return 'cp';
    }

    private function ambigCreatorBarchart() {
        return 'cb';
    }

    private function ambigMaterialPiechart() {

    }

    private function ambigMaterialBarchart() {

    }

    private function ambigConceptPiechart() {

    }

    private function ambigConceptBarchart() {

    }

    private function ambigSubjectPiechart() {

    }

    private function ambigSubjectBarchart() {

    }

    private function ambigLocationPiechart() {

    }

    private function ambigLocationBarchart() {

    }

    private function ambigEventPiechart() {

    }

    private function ambigEventBarchart() {

    }
}