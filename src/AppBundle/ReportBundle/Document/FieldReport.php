<?php

namespace AppBundle\ReportBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * @ODM\Document(collection="reports_field")
 */
class FieldReport
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
     * @ODM\Field(type="hash")
     */
    private $minimum;

    /**
     * @ODM\Field(type="hash")
     */
    private $basic;

    /**
     * @ODM\Field(type="hash")
     */
    private $extended;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getTotal()
    {
        return $this->total;
    }

    public function setTotal($total)
    {
        $this->total = $total;
    }

    public function getProvider()
    {
        return $this->provider;
    }

    public function setProvider($provider)
    {
        $this->provider = $provider;
    }

    public function getMinimum()
    {
        return $this->minimum;
    }

    public function setMinimum($minimum)
    {
        $this->minimum = $minimum;
    }

    public function getBasic()
    {
        return $this->basic;
    }

    public function setBasic($basic)
    {
        $this->basic = $basic;
    }

    public function getExtended()
    {
        return $this->extended;
    }

    public function setExtended($extended)
    {
        $this->extended = $extended;
    }
}
