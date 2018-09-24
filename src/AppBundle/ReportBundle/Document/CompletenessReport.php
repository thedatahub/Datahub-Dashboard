<?php

namespace AppBundle\ReportBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * @ODM\Document(collection="reports_completeness")
 */
class CompletenessReport
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
     * @ODM\Field(type="int")
     */
    private $total;

    /**
     * @ODM\Field(type="int")
     */
    private $minimum;

    /**
     * @ODM\Field(type="int")
     */
    private $basic;

    /**
     * @ODM\Field(type="int")
     */
    private $rightsWork;

    /**
     * @ODM\Field(type="int")
     */
    private $rightsDigitalRepresentation;

    /**
     * @ODM\Field(type="int")
     */
    private $rightsData;

    public function __construct()
    {
        $this->total = 0;
        $this->minimum = 0;
        $this->basic = 0;
        $this->rightsWork = 0;
        $this->rightsDigitalRepresentation = 0;
        $this->rightsData = 0;
    }

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

    public function getTotal()
    {
        return $this->total;
    }

    public function setTotal($total)
    {
        $this->total = $total;
    }

    public function incrementTotal()
    {
        $this->total++;
    }

    public function getMinimum()
    {
        return $this->minimum;
    }

    public function setMinimum($minimum)
    {
        $this->minimum = $minimum;
    }

    public function incrementMinimum()
    {
        $this->minimum++;
    }

    public function getBasic()
    {
        return $this->basic;
    }

    public function setBasic($basic)
    {
        $this->basic = $basic;
    }

    public function incrementBasic()
    {
        $this->basic++;
    }

    public function getRightsWork()
    {
        return $this->rightsWork;
    }

    public function setRightsWork($rightsWork)
    {
        $this->rightsWork = $rightsWork;
    }

    public function incrementRightsWork()
    {
        $this->rightsWork++;
    }

    public function getRightsDigitalRepresentation()
    {
        return $this->rightsDigitalRepresentation;
    }

    public function setRightsDigitalRepresentation($rightsDigitalRepresentation)
    {
        $this->rightsDigitalRepresentation = $rightsDigitalRepresentation;
    }

    public function incrementRightsDigitalRepresentation()
    {
        $this->rightsDigitalRepresentation++;
    }

    public function getRightsData()
    {
        return $this->rightsData;
    }

    public function setRightsData($rightsData)
    {
        $this->rightsData = $rightsData;
    }

    public function incrementRightsData()
    {
        $this->rightsData++;
    }
}
