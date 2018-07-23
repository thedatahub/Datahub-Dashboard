<?php
/**
 * Created by PhpStorm.
 * User: mike
 * Date: 7/4/18
 * Time: 3:44 PM
 */

namespace App\Repository;

use MongoDB\BSON\UTCDateTime;

class DatahubData {

    public static function clearData() {
        $client = new \MongoDB\Client();
        $collection = $client->datahub->data;
        $collection->deleteMany([]);
    }

    public static function storeData($data) {
        $client = new \MongoDB\Client();
        $collection = $client->datahub->data;
        $collection->insertOne($data);
    }

    public static function getAllData($provider) {
        $client = new \MongoDB\Client();
        $collection = $client->datahub->data;
        $iterator = $collection->find(array('provider_name' => $provider));
        $result = array();
        foreach($iterator as $key => $value)
            $result[] = $value;
        return $result;
    }

    public static function storeProviders($providers) {
        $client = new \MongoDB\Client();
        $collection = $client->datahub->providers;
        $collection->deleteMany([]);
        $collection->insertMany($providers);
    }

    public static function getAllProviders() {
        $client = new \MongoDB\Client();
        $collection = $client->datahub->providers;
        $iterator = $collection->find();
        $result = array();
        foreach($iterator as $key => $value)
            $result[] = $value->name;
        return $result;
    }

    public static function storeReport($provider, $completeness, $fields) {
        $completeness = array('timestamp' => new UTCDateTime()) + $completeness;
        $client = new \MongoDB\Client();
        $collection = $client->trends->completeness;
        $collection->insertOne($completeness);

        //TODO remove on release, hack to mock 3 months of data
        $collection->deleteMany(array('provider' => $provider));
        $curTime = new UTCDateTime();
        $curTs = $curTime->toDateTime()->getTimestamp() * 1000;
        for($i = 0; $i < 90; $i++) {
            $comp = array('timestamp' => new UTCDateTime($curTs - $i * 24 * 3600 * 1000)) + $completeness;
            $collection->insertOne($comp);
        }

        $collection = $client->report->completeness;
        $collection->deleteMany(array('provider' => $provider));
        $collection->insertOne($completeness);

        $collection = $client->report->fields;
        $collection->deleteMany(array('provider' => $provider));
        $collection->insertOne($fields);
    }

    public static function getCompleteness($provider) {
        $client = new \MongoDB\Client();
        $collection = $client->report->completeness;
        return $collection->findOne(array('provider' => $provider));
    }

    public static function getReport($provider, $type) {
        $client = new \MongoDB\Client();
        $collection = $client->report->fields;
        return $collection->findOne(array('provider' => $provider))[$type];
    }

    public static function getTrend($provider, $name, $maxMonths) {
        $client = new \MongoDB\Client();
        $collection = $client->trends->{$name};
        $curTime = new UTCDateTime();
        $curTs = $curTime->toDateTime()->getTimestamp() * 1000;
        $cursor = $collection->find(array('provider' => $provider, "timestamp" => array('$lte' => new UTCDateTime(), '$gte' => new UTCDateTime($curTs - $maxMonths * 30 * 24 * 3600 * 1000))));
        $result = array();
        foreach ($cursor as $doc)
            $result[] = $doc;
        return $result;
    }
}
