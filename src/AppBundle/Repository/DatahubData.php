<?php
/**
 * Created by PhpStorm.
 * User: mike
 * Date: 7/4/18
 * Time: 3:44 PM
 */

namespace AppBundle\Repository;

use MongoDB\BSON\UTCDateTime;

class DatahubData
{
    public static function clearData()
    {
        $client = new \MongoDB\Client();
        $collection = $client->datahub_dashboard->data;
        $collection->deleteMany([]);
    }

    public static function storeData($data)
    {
        $client = new \MongoDB\Client();
        $collection = $client->datahub_dashboard->data;
        $collection->insertOne($data);
    }

    public static function getAllData($provider)
    {
        $client = new \MongoDB\Client();
        $collection = $client->datahub_dashboard->data;
        $iterator = $collection->find(array('provider' => $provider));
        $result = array();
        foreach($iterator as $key => $value) {
            $result[] = $value;
        }
        return $result;
    }

    public static function getRecordCount($provider)
    {
        $client = new \MongoDB\Client();
        $collection = $client->datahub_dashboard->data;
        return $collection->count(array('provider' => $provider));
    }

    public static function storeProviders($providers)
    {
        $client = new \MongoDB\Client();
        $collection = $client->datahub_dashboard->providers;
        $collection->deleteMany([]);
        $collection->insertMany($providers);
    }

    public static function getAllProviders()
    {
        $client = new \MongoDB\Client();
        $collection = $client->datahub_dashboard->providers;
        $iterator = $collection->find();
        $result = array();
        foreach($iterator as $key => $value) {
            $result[] = array('id' => $value->id, 'name' => $value->name);
        }
        return $result;
    }

    public static function storeReport($provider, $completeness, $fields, $termsWithIds)
    {
        $client = new \MongoDB\Client();

        $collection = $client->datahub_dashboard->trend_completeness;
        $collection->insertOne(array('timestamp' => new UTCDateTime()) + $completeness);

        //TODO remove on release, hack to mock 3 months of data
        $collection->deleteMany(array('provider' => $provider));
        $curTime = new UTCDateTime();
        $curTs = $curTime->toDateTime()->getTimestamp() * 1000;
        for($i = 0; $i < 90; $i++) {
            $comp = array('timestamp' => new UTCDateTime($curTs - $i * 24 * 3600 * 1000)) + $completeness;
            $collection->insertOne($comp);
        }

        $collection = $client->datahub_dashboard->trend_terms_with_ids;
        $collection->insertOne(array('timestamp' => new UTCDateTime()) + $termsWithIds);

        //TODO remove on release, hack to mock 3 months of data
        $collection->deleteMany(array('provider' => $provider));
        $curTime = new UTCDateTime();
        $curTs = $curTime->toDateTime()->getTimestamp() * 1000;
        for($i = 0; $i < 90; $i++) {
            $comp = array('timestamp' => new UTCDateTime($curTs - $i * 24 * 3600 * 1000)) + $termsWithIds;
            $collection->insertOne($comp);
        }

        $collection = $client->datahub_dashboard->report_completeness;
        $collection->deleteMany(array('provider' => $provider));
        $collection->insertOne($completeness);

        $collection = $client->datahub_dashboard->report_fields;
        $collection->deleteMany(array('provider' => $provider));
        $collection->insertOne($fields);
    }

    public static function getCompleteness($provider)
    {
        $client = new \MongoDB\Client();
        $collection = $client->datahub_dashboard->report_completeness;
        return $collection->findOne(array('provider' => $provider));
    }

    public static function getReport($provider, $type)
    {
        $client = new \MongoDB\Client();
        $collection = $client->datahub_dashboard->report_fields;
        return $collection->findOne(array('provider' => $provider))[$type];
    }

    public static function getTrend($provider, $name, $maxMonths)
    {
        $client = new \MongoDB\Client();
        $trend = 'trend_' . $name;
        $collection = $client->datahub_dashboard->{$trend};
        $curTime = new UTCDateTime();
        $curTs = $curTime->toDateTime()->getTimestamp() * 1000;
        $cursor = $collection->find(array('provider' => $provider, "timestamp" => array('$lte' => new UTCDateTime(), '$gte' => new UTCDateTime($curTs - $maxMonths * 30 * 24 * 3600 * 1000))));
        $result = array();
        foreach ($cursor as $doc) {
            $result[] = $doc;
        }
        return $result;
    }
}
