<?php
namespace AppBundle\Controller;

use AppBundle\RecordBundle\Document\Record;
use AppBundle\Util\RecordUtil;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Error\RuntimeError;

class DownloadController extends Controller
{
    private $provider = null;
    private $dataDef = null;
    private $question = null;
    private $questionLabel = null;
    private $field = null;
    private $translator;

    /**
     * @Route("/{_locale}/download/{provider}/{aspect}/{parameter}/{question}/{graph}/{field}", name="download", requirements={"_locale" = "%app.locales%", "provider"="[^/]+", "aspect"="[^/]+", "parameter"="[^/]+", "question"="[^/]+", "graph"="[^/]+", "field"="[^/]+"})
     */
    public function download(Request $request, $_locale = null, $provider, $aspect = '', $parameter = '', $question = '', $graph = '', $field = '')
    {
        $this->provider = $provider;
        $this->question = $question;
        if($field !== '')
            $this->field = $field;

        if(!$_locale) {
            $_locale = $this->getParameter('locale');
            $request->setLocale($_locale);
        }
        $this->translator = $this->get('translator');
        $this->translator->setLocale($_locale);

        $this->dataDef = $this->getParameter('data_definition');
        $leftMenu = $this->getParameter('left_menu');

        $functionCall = null;
        $parameters = $leftMenu[$aspect]['parameters'];
        $aspectLabel = $this->translator->trans($leftMenu[$aspect]['label']);
        foreach($parameters as $param) {
            if($param['url'] === $parameter) {
                foreach ($param['questions'] as $quest) {
                    if($quest['url'] === $question) {
                        $functionCall = $quest['function'];
                        $this->questionLabel = $this->translator->trans($quest['label']);
                        break;
                    }
                }
            }
        }

        $functionCall .= $graph;
        $csvData = $this->$functionCall();

        // Generate response
        $response = new Response();
        if($this->field) {
            $field = preg_replace("/[^A-Za-z0-9 _-]/", '', $field);
            if(array_key_exists($field, $this->dataDef) && array_key_exists('csv', $this->dataDef[$field])) {
                $label = $this->translator->trans($this->dataDef[$field]['csv']);
            }
            else {
                $label = $this->questionLabel . '_' . $this->translator->trans($field);
            }
            $filename = $provider . '_' . $aspectLabel . '_' . $label . '.csv';
        } else {
            $filename = $provider . '_' . $aspectLabel . '_' . $this->questionLabel . '.csv';
        }
        $filename = strtolower(str_replace(' ', '_', $filename));

        // Set headers
        $response->headers->set('Cache-Control', 'private');
        $response->headers->set('Content-type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '";');
        $response->headers->set('Content-length', strlen($csvData));
        $response->sendHeaders();

        $response->setContent($csvData);

        return $response;
    }

    private function getDocumentManager()
    {
        return $this->get('doctrine_mongodb')->getManager();
    }

    private function getAllRecords()
    {
        return $this->getDocumentManager()->getRepository('RecordBundle:Record')->findBy(array('provider' => $this->provider));
    }

    private function extractFieldFromRecord($record, $field)
    {
        if(strpos($field, '/')) {
            $parts = explode('/', $field);
            if($record[$parts[0]] && count($record[$parts[0]]) > 0) {
                foreach($record[$parts[0]] as $part) {
                    if($part[$parts[1]] && count($part[$parts[1]]) > 0) {
                        return $part[$parts[1]];
                    }
                }
            }
        }
        elseif($record[$field] && count($record[$field])) {
            return $record[$field];
        }
        return null;
    }

    private function getRecordIds($record)
    {
        $applicationId = '';
        if ($record['application_id'] && count($record['application_id']) > 0) {
            $applicationId = $record['application_id'][0];
        }
        $objectNumber = '';
        if ($record['object_number'] && count($record['object_number']) > 0) {
            $objectNumber = $record['object_number'][0];
        }
        return array($applicationId, $objectNumber);
    }

    private function fieldOverviewCmp($a, $b)
    {
        if($a['filled_in']) {
            if($b['filled_in']) {
                return 0;
            } else {
                return 1;
            }
        } elseif($b['filled_in']) {
            return -1;
        } else {
            return 0;
        }
    }

    private function fieldOverview()
    {
        $records = $this->getAllRecords();

        $csvArray = array();
        if($records) {
            foreach ($records as $record) {
                $data = $record->getData();
                $recordIds = $this->getRecordIds($data);
                $part = $this->extractFieldFromRecord($data, $this->field);
                $csvArray[] = array('app_id' => $recordIds[0], 'obj_number' => $recordIds[1], 'filled_in' => ($part ? true : false));
            }
        }

        usort($csvArray, array('AppBundle\Controller\DownloadController', 'fieldOverviewCmp'));

        $csvData = '';
        foreach($csvArray as $csvLine) {
            $csvData .= PHP_EOL . '"' . $csvLine['app_id'] . '","' . $csvLine['obj_number'] . '","' . $this->translator->trans($csvLine['filled_in'] ? 'filled_in' : 'not_filled_in') . '"';
        }

        $label = $this->translator->trans(RecordUtil::getFieldLabel($this->field, $this->dataDef));
        $applicationId = $this->translator->trans('application_id');
        $objectNumber = $this->translator->trans('object_number');

        return $applicationId . ',' . $objectNumber . ',' . $label . $csvData;
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
        if($a['count'] === 0) {
            return 1;
        } elseif($b['count'] === 0) {
            return -1;
        } elseif ($a['count'] === $b['count']) {
            $aLen = strlen($a['id']);
            $bLen = strlen($b['id']);
            if($aLen > $bLen) {
                return 1;
            } elseif($aLen < $bLen) {
                return -1;
            } else {
                return strcmp($a['id'], $b['id']);
            }
        } else {
            return ($a['count'] < $b['count']) ? 1 : -1;
        }
    }

    private function ambigIds($field, $label)
    {
        $records = $this->getAllRecords();
        $ids = array();
        $csvArray = array();
        if($records) {
            foreach ($records as $record) {
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

            foreach ($records as $record) {
                $data = $record->getData();
                if($data[$field] && count($data[$field]) > 0) {
                    $id = $data[$field][0];
                    $count = $ids[$id];
                }
                else {
                    $id = '';
                    $count = 0;
                }
                $recordIds = $this->getRecordIds($data);
                $csvArray[] = array('app_id' => $recordIds[0], 'obj_number' => $recordIds[1], 'id' => $id, 'count' => $count);
            }
            usort($csvArray, array('AppBundle\Controller\DownloadController', 'ambigIdsCmp'));
        }

        $csvData = '';
        foreach($csvArray as $csvLine) {
            $csvData .= PHP_EOL . '"' . $csvLine['app_id'] . '","' . $csvLine['obj_number'] . '","' . $csvLine['id'] . '","' . $csvLine['count'] . '"';
        }

        $applicationId = $this->translator->trans('application_id');
        $objectNumber = $this->translator->trans('object_number');
        $occurrences = $this->translator->trans('occurrence_amount');

        return $applicationId . ',' . $objectNumber . ',' . $label . ',' . $occurrences . $csvData;
    }

    private function ambigWorkPidsPiechart()
    {
        return $this->ambigIds('work_pid', 'Work PID');
    }

    private function ambigDataPidsPiechart()
    {
        return $this->ambigIds('data_pid', 'Data PID');
    }

    private function ambigTermPieCmp($a, $b)
    {
        $aLen = 0;
        foreach($a as $aPart) {
            $len = strlen($aPart['concept_id']);
            if($len > 0) {
                $aLen = $len;
                break;
            }
        }
        $bLen = 0;
        foreach($b as $bPart) {
            $len = strlen($bPart['concept_id']);
            if($len > 0) {
                $bLen = $len;
                break;
            }
        }
        if($aLen === 0) {
            if($bLen === 0) {
                return 0;
            } else {
                return -1;
            }
        } elseif($bLen === 0) {
            return 1;
        } else {
            return 0;
        }
    }

    private function checkIdsAndAuthorityForTermPie($term, $fieldValue, $localAuthority, &$termsWithId, &$termsWithoutId)
    {
        if ($fieldValue['id'] && count($fieldValue['id']) > 0) {
            $ids = $fieldValue['id'];
            $localId = '';
            foreach ($ids as $termId) {
                if ($termId['type'] === 'local') {
                    $localId = $termId['id'];
                    $localAuthority_ = $termId['source'];
                    if ($localAuthority) {
                        if ($localAuthority_ !== $localAuthority) {
                            $localAuthority = '';
                        }
                    } else {
                        $localAuthority = $localAuthority_;
                    }
                    break;
                }
            }
            $isEmpty = true;
            foreach ($ids as $termId) {
                if ($termId['type'] === 'purl') {
                    $isEmpty = false;
                    $authority = $this->getAuthority($termId);
                    if (!array_key_exists($term, $termsWithId)) {
                        $termsWithId[$term] = array(array('local_id' => $localId, 'concept_id' => $termId['id'], 'authority' => $authority));
                    } else {
                        $isIn = false;
                        foreach ($termsWithId[$term] as $knownId) {
                            if ($knownId['concept_id'] === $termId['id']) {
                                $isIn = true;
                                break;
                            }
                        }
                        if (!$isIn) {
                            $termsWithId[$term][] = array('local_id' => $localId, 'concept_id' => $termId['id'], 'authority' => $authority);
                        }
                    }
                }
            }
            if ($isEmpty) {
                if (!array_key_exists($term, $termsWithId)) {
                    $termsWithId[$term] = array(array('local_id' => $localId, 'concept_id' => '', 'authority' => ''));
                }
            }
        } else {
            if (!array_key_exists($term, $termsWithoutId)) {
                $termsWithoutId[$term] = '';
            }
        }
        return $localAuthority;
    }

    private function ambigtermPie($field)
    {
        $records = $this->getAllRecords();

        $termsWithId = array();
        $termsWithoutId = array();
        $localAuthority = null;
        if($records) {
            foreach ($records as $record) {
                $data = $record->getData();
                $fieldValues = $this->extractFieldFromRecord($data, $field);
                if ($fieldValues) {
                    foreach ($fieldValues as $fieldValue) {
                        if ($fieldValue['term'] && count($fieldValue['term']) > 0) {
                            $term = RecordUtil::getPreferredTerm($fieldValue['term']);
                            if ($term) {
                                $localAuthority = $this->checkIdsAndAuthorityForTermPie($term, $fieldValue, $localAuthority, $termsWithId, $termsWithoutId);
                            }
                        }
                    }
                }
            }
        }

        uasort($termsWithId, array('AppBundle\Controller\DownloadController', 'ambigTermPieCmp'));

        $csvData = '';
        foreach($termsWithoutId as $term => $id) {
            $csvData .= PHP_EOL . '"","' . $term . '",""';
        }
        foreach($termsWithId as $term => $ids) {
            foreach($ids as $termId) {
                $csvData .= PHP_EOL . '"' . $termId['local_id'] . '","' . $term . '","' . $termId['concept_id'] . '","' . $termId['authority'] . '"';
            }
        }

        $label = $this->translator->trans(RecordUtil::getFieldLabel($field, $this->dataDef));

        if(!$localAuthority) {
            $localAuthority = '';
        } else {
            $localAuthority .= '';
        }

        return $localAuthority . ' ID,' . $label . ',Concept ID,Authority' . $csvData;
    }

    private function getAuthority($id)
    {
        if($id['type'] === 'local') {
            $authority = $id['source'];
        } else {
            $parsedUrl = parse_url($id['id']);
            if(array_key_exists('host', $parsedUrl) && strlen($parsedUrl['host']) > 0) {
                $authority = $parsedUrl['host'];
            } else {
                $authority = $id['source'];
            }
        }
        return $authority;
    }

    private function checkIdsAndAuthorityForTermBar($termId, $term, &$idTerms, &$termsWithId)
    {
        $authority = $this->getAuthority($termId);
        $id = $termId['id'];
        if (!array_key_exists($term, $termsWithId)) {
            $count = 1;
            if(!array_key_exists($id, $idTerms)) {
                $idTerms[$id] = array($term);
            }
            else {
                $isIn = false;
                foreach ($idTerms[$id] as $term_) {
                    if($term_ === $term) {
                        $isIn = true;
                        break;
                    }
                }
                if(!$isIn) {
                    $idTerms[$id][] = $term;
                }
                $count = count($idTerms[$id]);
            }
            $termsWithId[$term] = array(array('id' => $id, 'authority' => $authority, 'count' => $count));
        } else {
            $isIn = false;
            foreach ($termsWithId[$term] as $knownId) {
                if ($knownId['id'] === $id) {
                    $isIn = true;
                    break;
                }
            }
            if (!$isIn) {
                $count = 1;
                if(!array_key_exists($id, $idTerms)) {
                    $idTerms[$id] = array($term);
                }
                else {
                    $isIn = false;
                    foreach ($idTerms[$id] as $term_) {
                        if($term_ === $term) {
                            $isIn = true;
                            break;
                        }
                    }
                    if(!$isIn) {
                        $idTerms[$id][] = $term;
                    }
                    if(array_key_exists('id', $idTerms)) {
                        $count = count($idTerms['id']);
                    } else {
                        $count = 0;
                    }
                }
                $termsWithId[$term][] = array('id' => $id, 'authority' => $authority, 'count' => $count);
            }
        }
    }

    private function ambigtermBar($field)
    {
        $records = $this->getAllRecords();

        $termsWithId = array();
        $termsWithoutId = array();
        $idTerms = array();
        if($records) {
            foreach ($records as $record) {
                $data = $record->getData();
                $fieldValues = $this->extractFieldFromRecord($data, $field);
                if ($fieldValues) {
                    foreach ($fieldValues as $fieldValue) {
                        if ($fieldValue['term'] && count($fieldValue['term']) > 0) {
                            $term = RecordUtil::getPreferredTerm($fieldValue['term']);
                            if ($term) {
                                if ($fieldValue['id'] && count($fieldValue['id']) > 0) {
                                    $ids = $fieldValue['id'];
                                    foreach ($ids as $termId) {
                                        if ($termId['source'] === $this->field) {
                                            $this->checkIdsAndAuthorityForTermBar($termId, $term, $idTerms, $termsWithId);
                                        }
                                    }
                                } else {
                                    if (!array_key_exists($term, $termsWithoutId)) {
                                        $termsWithoutId[$term] = '';
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }


        $initCsvData = array();
        foreach($termsWithId as $term => $ids) {
            foreach($ids as $termId) {
                $initCsvData[] = array(
                    'term' => $term,
                    'id' => $termId['id'],
                    'authority' => $termId['authority'],
                    'count' => count($idTerms[$termId['id']])*1000000 + count($ids));//hack to sort based on duplicate ID's, then on duplicate terms
            }
        }
        usort($initCsvData, array('AppBundle\Controller\DownloadController', 'ambigIdsCmp'));

        $csvData = '';
        foreach($termsWithoutId as $term => $termId) {
            $csvData .= PHP_EOL . '"' . $term . '","","ongekend"';
        }
        foreach($initCsvData as $data) {
            $csvData .= PHP_EOL . '"' . $data['term'] . '","' . $data['id'] . '","' . $data['authority'] . '"';
        }

        $label = $this->translator->trans(RecordUtil::getFieldLabel($field, $this->dataDef));
        $persistentId = $this->translator->trans('persistent_id');

        return $label . ',' . $persistentId . ',Authority' . $csvData;
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
        return $this->ambigtermPie('object_category');
    }

    private function ambigCategoryBarchart()
    {
        return $this->ambigtermBar('object_category');
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

    private function richOccurrencesBar($field)
    {
        $records = $this->getAllRecords();
        $csvArray = array();
        if($records) {
            foreach ($records as $record) {
                $data = $record->getData();
                if($this->field == 0) {
                    $add = false;
                    if (!array_key_exists($field, $data)) {
                        $add = true;
                    } elseif(!$data[$field]) {
                        $add = true;
                    } elseif(count($data[$field]) === 0) {
                        $add = true;
                    }
                    if($add) {
                        $recordIds = $this->getRecordIds($data);
                        $csvArray[] = array('app_id' => $recordIds[0], 'obj_number' => $recordIds[1], 'term' => '');
                    }
                } elseif (array_key_exists($field, $data)) {
                    if($data[$field] && count($data[$field]) == $this->field) {
                        $recordIds = $this->getRecordIds($data);
                        foreach ($data[$field] as $term) {
                            if (is_array($term)) {
                                if (array_key_exists('term', $term)) {
                                    foreach ($term['term'] as $t) {
                                        $csvArray[] = array('app_id' => $recordIds[0], 'obj_number' => $recordIds[1], 'term' => $t['term']);
                                    }
                                } else {
                                    foreach ($term as $t) {
                                        $csvArray[] = array('app_id' => $recordIds[0], 'obj_number' => $recordIds[1], 'term' => $t);
                                    }
                                }
                            } else {
                                $csvArray[] = array('app_id' => $recordIds[0], 'obj_number' => $recordIds[1], 'term' => $term);
                            }
                        }
                    }
                }
            }
        }

        $csvData = '';
        foreach($csvArray as $csvLine) {
            $csvData .= PHP_EOL . '"' . $csvLine['app_id'] . '","' . $csvLine['obj_number'] . '","' . $csvLine['term'] . '"';
        }

        $applicationId = $this->translator->trans('application_id');
        $objectNumber = $this->translator->trans('object_number');

        return $applicationId . ',' . $objectNumber . ',' . $this->questionLabel . $csvData;
    }

    private function richOccurrencesStorageInstitutionBarChart()
    {
        return $this->richOccurrencesBar('storage_institution');
    }

    private function richOccurrencesObjectIdBarChart()
    {
        return $this->richOccurrencesBar('object_number');
    }

    private function richOccurrencesDataPidBarChart()
    {
        return $this->richOccurrencesBar('data_pid');
    }

    private function richOccurrencesTitleBarChart()
    {
        return $this->richOccurrencesBar('title');
    }

    private function richOccurrencesShortDescBarChart()
    {
        return $this->richOccurrencesBar('short_description');
    }

    private function richOccurrencesObjectNameBarChart()
    {
        return $this->richOccurrencesBar('object_name');
    }

    private function richOccurrencesObjectCatBarChart()
    {
        return $this->richOccurrencesBar('object_category');
    }

    private function richOccurrencesMainMotifBarChart()
    {
        return $this->richOccurrencesBar('main_motif');
    }

    private function richOccurrencesCreatorBarChart()
    {
        return $this->richOccurrencesBar('creator');
    }

    private function richOccurrencesMaterialBarChart()
    {
        return $this->richOccurrencesBar('material');
    }

    private function richOccurrencesConceptBarChart()
    {
        return $this->richOccurrencesBar('displayed_concept');
    }

    private function richOccurrencesSubjectBarChart()
    {
        return $this->richOccurrencesBar('displayed_subject');
    }

    private function richOccurrencesLocationBarChart()
    {
        return $this->richOccurrencesBar('displayed_location');
    }

    private function richOccurrencesEventBarChart()
    {
        return $this->richOccurrencesBar('displayed_event');
    }

    private function richTermBar($field)
    {
        $records = $this->getAllRecords();
        $csvArray = array();
        if($records) {
            foreach ($records as $record) {
                $data = $record->getData();
                if(array_key_exists($field, $data)) {
                    if ($data[$field] && count($data[$field]) > 0) {
                        $add = false;
                        foreach ($data[$field] as $term) {
                            if (is_array($term)) {
                                if (array_key_exists('term', $term)) {
                                    foreach ($term['term'] as $t) {
                                        if ($t['term'] === $this->field) {
                                            $add = true;
                                            break;
                                        }
                                    }
                                } else {
                                    foreach ($term as $t) {
                                        if ($t['term'] === $this->field) {
                                            $add = true;
                                            break;
                                        }
                                    }
                                }
                            } else {
                                if ($term === $this->field) {
                                    $add = true;
                                    break;
                                }
                            }
                        }
                        if ($add) {
                            $recordIds = $this->getRecordIds($data);
                            $csvArray[] = array('app_id' => $recordIds[0], 'obj_number' => $recordIds[1], 'term' => $this->field);
                        }
                    } elseif($this->field === '(undefined)') {
                        $recordIds = $this->getRecordIds($data);
                        $csvArray[] = array('app_id' => $recordIds[0], 'obj_number' => $recordIds[1], 'term' => $this->translator->trans('undefined'));
                    }
                } elseif($this->field === '(undefined)') {
                    $recordIds = $this->getRecordIds($data);
                    $csvArray[] = array('app_id' => $recordIds[0], 'obj_number' => $recordIds[1], 'term' => $this->translator->trans('undefined'));
                }
            }
        }

        $csvData = '';
        foreach($csvArray as $csvLine) {
            $csvData .= PHP_EOL . '"' . $csvLine['app_id'] . '","' . $csvLine['obj_number'] . '","' . $csvLine['term'] . '"';
        }

        $applicationId = $this->translator->trans('application_id');
        $objectNumber = $this->translator->trans('object_number');

        return $applicationId . ',' . $objectNumber . ',' . $this->questionLabel . $csvData;
    }

    private function richTermObjectNameBarChart()
    {
        return $this->richTermBar('object_name');
    }

    private function richTermObjectCatBarChart()
    {
        return $this->richTermBar('object_category');
    }

    private function richTermMainMotifBarChart()
    {
        return $this->richTermBar('main_motif');
    }

    private function richTermCreatorBarChart()
    {
        return $this->richTermBar('creator');
    }

    private function richTermMaterialBarChart()
    {
        return $this->richTermBar('material');
    }

    private function richTermConceptBarChart()
    {
        return $this->richTermBar('displayed_concept');
    }

    private function richTermSubjectBarChart()
    {
        return $this->richTermBar('displayed_subject');
    }

    private function richTermLocationBarChart()
    {
        return $this->richTermBar('displayed_location');
    }

    private function richTermEventBarChart()
    {
        return $this->richTermBar('displayed_event');
    }

    private function openRecordRecordsPiechart()
    {

    }

    private function openRecordTermsPiechart()
    {

    }
}
