<?php
/**
 * Created by PhpStorm.
 * User: mike
 * Date: 7/9/18
 * Time: 12:51 PM
 */

namespace App\Entity;


class Report {

    public $chart;
    public $template;
    public $data;
    public $header;

    public $isEmpty = false;
    public $emptyText = '';
    public $isFull = false;
    public $fullText = '';
    public $canDownload = false;

    public function __construct($chart, $data, $header = '') {
        $this->chart = $chart;
        $this->template = $chart . '.html.twig';
        $this->data = $data;
        $this->header = $header;
    }
}
