<?php
namespace AppBundle\PhpD3\Builder;

class DataPrep
{
    protected $type;
    protected $data;

    function __construct()
    {
    }

    public function run($dataFile, $fileType = false)
    {
        switch($fileType) {
            case 'tsv':
                return $this->prepTsv($dataFile);
                break;
            case 'csv':
                return $this->prepCsv($dataFile);
                break;
            default;
                return $this->prepArray($dataFile);
                break;
        }
    }

    public function prepTsv($dataFile)
    {
        $handle = fopen($dataFile,'r');
        $dataArray = [];
        $headerArray = [];
        if($handle !== false) {
            $i = 0;
            $header = true;
            while (($data = fgetcsv($handle, 0, "\t")) !== false) {
                if($header) {
                    $headerArray = $data;
                    $header = false;
                } else {
                    foreach($headerArray as $key => $value){
                        if(isset($data[$key])){
                            $dataArray[$i][$value] = $data[$key];
                        }
                    }
                    $i++;
                }
            }
        }
        fclose($handle);
        return json_encode($dataArray);
    }

    public function prepCsv($dataFile)
    {
        $handle = fopen($dataFile,'r');
        $dataArray = [];
        $headerArray = [];
        if($handle !== false) {
            $i = 0;
            $header = true;
            while (($data = fgetcsv($handle, 0, ",")) !== false) {
                if($header) {
                    $headerArray = $data;
                    $header = false;
                } else {
                    foreach($headerArray as $key => $value) {
                        $dataArray[$i][$value] = $data[$key];
                    }
                    $i++;
                }
            }
        }
        fclose($handle);
        return json_encode($dataArray);
    }

    public function prepArray($array)
    {
        return json_encode($array);
    }

    public function findDataRanges($data)
    {
        $reorg = [];
        $ranges = [];
        $data = json_decode($data, true);

        foreach($data as $key => $value) {
            foreach($value as $subkey => $subvalue) {
                $reorg[$subkey][] = $subvalue;
            }
        }

        foreach($reorg as $key => $value) {
            asort($value);
            $low = reset($value);
            $high = end($value);

            $ranges[$key] = array('low' => (int)$low, 'high' => (int)$high);
        }

        return $ranges;

    }
}
