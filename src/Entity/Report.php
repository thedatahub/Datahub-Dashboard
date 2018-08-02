<?php
/**
 * Created by PhpStorm.
 * User: mike
 * Date: 8/2/18
 * Time: 11:55 AM
 */

namespace App\Entity;


class Report {

    public $title;
    public $printTitle;
    public $description;
    public $graphs;

    public function __construct($title, $printTitle, $description, $graphs) {
        $this->title = $title;
        $this->printTitle = $printTitle;
        $this->description = $description;
        $this->graphs = $graphs;
    }
}
