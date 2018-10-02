<?php

namespace AppBundle\Entity;


class Report
{
    public $title;
    public $description;
    public $graphs;

    public function __construct($title, $description, $graphs)
    {
        $this->title = $title;
        $this->description = $description;
        $this->graphs = $graphs;
    }
}
