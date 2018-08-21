<?php
namespace AppBundle\Controller;

use AppBundle\Repository\DatahubData;
use AppBundle\Util\RecordUtil;
use Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DownloadController extends Controller
{

    private $provider = null;
    private $dataDef = null;
    private $field = null;

    /**
     * @Route("/download", name="download")
     */
    public function download(Request $request)
    {
        $this->provider = urldecode($request->query->get('provider'));
        $aspect = urldecode($request->query->get('aspect'));
        $parameter = urldecode($request->query->get('parameter'));
        $question = urldecode($request->query->get('question'));
        $graph = urldecode($request->query->get('graph'));
        if($request->query->get('field'))
            $this->field = urldecode($request->query->get('field'));

        $this->dataDef = $this->getParameter('data_definition');
        $leftMenu = $this->getParameter('left_menu');

        try {
            $functionCall = $leftMenu[$aspect][$parameter][$question] . ucfirst($graph);
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

    private function extractFieldFromRecord($record, $field)
    {
        if(strpos($field, '/')) {
            $parts = explode('/', $field);
            if($record->{$parts[0]} && count($record->{$parts[0]}) > 0) {
                foreach($record->{$parts[0]} as $part) {
                    if($part->{$parts[1]} && count($part->{$parts[1]})) {
                        return $part->{$parts[1]};
                    }
                }
            }
        }
        elseif($record->{$field} && count($record->{$field})) {
            return $record->{$field};
        }
        return null;
    }

    private function getRecordIds($record)
    {
        $applicationId = '';
        if ($record->application_id && count($record->application_id) > 0) {
            $applicationId = $record->application_id[0];
        }
        $objectNumber = '';
        if ($record->object_number && count($record->object_number) > 0) {
            $objectNumber = $record->object_number[0];
        }
        return array($applicationId, $objectNumber);
    }

    private function fieldOverview()
    {
        $data = DatahubData::getAllData($this->provider);

        $csvData = '';
        foreach($data as $record) {
            $recordIds = $this->getRecordIds($record);
            $part = $this->extractFieldFromRecord($record, $this->field);
            $csvData .= PHP_EOL . $recordIds[0] . ',' . $recordIds[1] . ',' . ($part ? 'ingevuld' : 'niet ingevuld');
        }

        $label = RecordUtil::getFieldLabel($this->field, $this->dataDef);

        return 'Applicatie ID,Objectnummer,' . $label . $csvData;
    }

    private function minFieldOverviewBarchart()
    {
        return $this->fieldOverview();
    }

    private function basicFieldOverviewBarchart()
    {
        return $this->fieldOverview();
    }

    private function extendedFieldOverviewBarchart()
    {
        return $this->fieldOverview();
    }

    private function ambigIdsCmp($a, $b)
    {
        if($a['count'] == 0) {
            return 1;
        } elseif($b['count'] == 0) {
            return -1;
        } elseif ($a['count'] == $b['count']) {
            return 0;
        } else {
            return ($a['count'] < $b['count']) ? 1 : -1;
        }
    }

    private function ambigIds($field, $label)
    {
        $data = DatahubData::getAllData($this->provider);
        $ids = array();
        foreach ($data as $record) {
            if($record->{$field} && count($record->{$field}) > 0) {
                $id = $record->{$field}[0];
                if (!array_key_exists($id, $ids)) {
                    $ids[$id] = 1;
                }
                else {
                    $ids[$id]++;
                }
            }
        }

        $csvArray = array();
        foreach ($data as $record) {
            if($record->{$field} && count($record->{$field}) > 0) {
                $id = $record->{$field}[0];
                $count = $ids[$id];
            }
            else {
                $id = '';
                $count = 0;
            }
            $recordIds = $this->getRecordIds($record);
            $csvArray[] = array('app_id' => $recordIds[0], 'obj_number' => $recordIds[1], 'id' => $id, 'count' => $count);
        }
        usort($csvArray, array('App\Controller\DownloadController', 'ambigIdsCmp'));

        $csvData = '';
        foreach($csvArray as $csvLine) {
            $csvData .= PHP_EOL . '"' . $csvLine['app_id'] . '","' . $csvLine['obj_number'] . '","' . $csvLine['id'] . '","' . $csvLine['count'] . '"';
        }

        return 'Applicatie ID,Objectnummer,' . $label . ',Aantal voorkomens' . $csvData;
    }

    private function ambigWorkPidsPiechart()
    {
        return $this->ambigIds('work_pid', 'Work PID');
    }

    private function ambigDataPidsPiechart()
    {
        return $this->ambigIds('data_pid', 'Data PID');
    }

    private function ambigtermPie($field)
    {
        $data = DatahubData::getAllData($this->provider);

        $termsWithId = array();
        $termsWithoutId = array();
        foreach ($data as $record) {
            $part = $this->extractFieldFromRecord($record, $field);
            if($part) {
                foreach($part as $r) {
                    if ($r->term && count($r->term) > 0) {
                        if($r->id && count($r->id) > 0) {
                            $id = $r->id[0];
                            if(!array_key_exists($r->term[0], $termsWithId)) {
                                $termsWithId[$r->term[0]] = $id;
                            }
                        }
                        else {
                            if(!array_key_exists($r->term[0], $termsWithoutId)) {
                                $termsWithoutId[$r->term[0]] = '';
                            }
                        }
                    }
                }
            }
        }

        $csvData = '';
        foreach($termsWithId as $term => $id) {
            $csvData .= PHP_EOL . '"' . $term . '","' . $id . '",ingevuld';
        }
        foreach($termsWithoutId as $term => $id) {
            $csvData .= PHP_EOL . '"' . $term . '",,niet ingevuld';
        }

        $label = RecordUtil::getFieldLabel($field, $this->dataDef);

        return $label . ',Persistente ID,Aanwezig' . $csvData;
    }

    private function ambigtermBar($field)
    {
        $data = DatahubData::getAllData($this->provider);

        $termsWithId = array();
        foreach ($data as $record) {
            $part = $this->extractFieldFromRecord($record, $field);
            if($part) {
                foreach($part as $r) {
                    if ($r->term && count($r->term) > 0) {
                        if($r->id && count($r->id) > 0) {
                            $id = $r->id[0];
                            if(!array_key_exists($r->term[0], $termsWithId)) {
                                $termsWithId[$r->term[0]] = array($id);
                            }
                            elseif(!in_array($id, $termsWithId[$r->term[0]])) {
                                $termsWithId[$r->term[0]][] = $id;
                            }
                        }
                    }
                }
            }
        }

        $csvData = '';
        foreach($termsWithId as $term => $id) {
            foreach($id as $i) {
                $csvData .= PHP_EOL . '"' . $term . '","' . $i . '",';
            }
        }

        $label = RecordUtil::getFieldLabel($field, $this->dataDef);

        return $label . ',Persistente ID' . $csvData;
    }

    private function ambigObjectNamePiechart()
    {
        return $this->ambigtermPie('object_name');
    }

    private function ambigObjectNameBarchart()
    {
        return $this->ambigtermBar('object_name');
    }

    private function ambigCategoryPiechart()
    {
        return $this->ambigtermPie('category');
    }

    private function ambigCategoryBarchart()
    {
        return $this->ambigtermBar('category');
    }

    private function ambigMainMotifPiechart()
    {
        return $this->ambigtermPie('main_motif');
    }

    private function ambigMainMotifBarchart()
    {
        return $this->ambigtermBar('main_motif');
    }

    private function ambigCreatorPiechart()
    {
        return $this->ambigtermPie('creator');
    }

    private function ambigCreatorBarchart()
    {
        return $this->ambigtermBar('creator');
    }

    private function ambigMaterialPiechart()
    {
        return $this->ambigtermPie('material');
    }

    private function ambigMaterialBarchart()
    {
        return $this->ambigtermBar('material');
    }

    private function ambigConceptPiechart()
    {
        return $this->ambigtermPie('displayed_concept');
    }

    private function ambigConceptBarchart()
    {
        return $this->ambigtermBar('displayed_concept');
    }

    private function ambigSubjectPiechart()
    {
        return $this->ambigtermPie('displayed_subject');
    }

    private function ambigSubjectBarchart()
    {
        return $this->ambigtermBar('displayed_subject');
    }

    private function ambigLocationPiechart()
    {
        return $this->ambigtermPie('displayed_location');
    }

    private function ambigLocationBarchart()
    {
        return $this->ambigtermBar('displayed_location');
    }

    private function ambigEventPiechart()
    {
        return $this->ambigtermPie('displayed_event');
    }

    private function ambigEventBarchart()
    {
        return $this->ambigtermBar('displayed_event');
    }
}
