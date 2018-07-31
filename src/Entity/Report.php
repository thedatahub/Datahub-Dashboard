<?php
/**
 * Created by PhpStorm.
 * User: mike
 * Date: 7/9/18
 * Time: 12:51 PM
 */

namespace App\Entity;


class Report {

    public $template;
    public $data;
    public $header;

    public $isEmpty = false;
    public $emptyText = '';
    public $isFull = false;
    public $fullText = '';

    public function __construct($template, $data, $header = '') {
        $this->template = $template;
        $this->data = $data;
        $this->header = $header;
    }
}
