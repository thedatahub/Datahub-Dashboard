<?php

namespace AppBundle\Entity;


class Report
{

    public $title;
    public $printTitle;
    public $description;
    public $graphs;

    public function __construct($title, $printTitle, $description, $graphs)
    {
        $this->title = $title;
        $this->printTitle = $printTitle;
        $this->description = $description;
        $this->graphs = $graphs;
    }
}
