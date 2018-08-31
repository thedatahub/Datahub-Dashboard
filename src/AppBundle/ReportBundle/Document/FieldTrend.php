<?php

namespace AppBundle\ReportBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * @ODM\Document(collection="trends_field")
 */
class FieldTrend
{
    /**
     * @ODM\Id
     */
    private $id;

    /**
     * @ODM\Field(type="string")
     */
    private $provider;

    /**
     * @ODM\Field(type="date")
     */
    private $timestamp;

    /**
     * @ODM\Field(type="hash")
     */
    private $counts;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getProvider()
    {
        return $this->provider;
    }

    public function setProvider($provider)
    {
        $this->provider = $provider;
    }

    public function getTimestamp()
    {
        return $this->timestamp;
    }

    public function setTimestamp($timestamp)
    {
        $this->timestamp = $timestamp;
    }

    public function getCounts()
    {
        return $this->counts;
    }

    public function setCounts($counts)
    {
        $this->counts = $counts;
    }
}
