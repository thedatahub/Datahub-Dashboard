<?php
namespace AppBundle\PhpD3;

use AppBundle\PhpD3\Builder\Graphs\DualScaleBarGraph;
use AppBundle\PhpD3\Builder\Graphs\LineGraph;
use AppBundle\PhpD3\Builder\Charts\PieChart;
use AppBundle\PhpD3\Builder\Graphs\BarGraph;

class Draw
{
    private $data;
    public $chart;

    function __construct($type, $chartData = array())
    {
        $this->data = $chartData;
        if($type) {
            switch($type) {
            case 'simple_pie_chart';
                $builtChart = $this->simplePieChart();
                $this->chart = $this->load($builtChart);
                break;
            case 'simple_bar_graph';
                $builtChart = $this->simpleBarGraph();
                $this->chart = $this->load($builtChart);
                break;
            case 'dual_scale_bar_graph';
                $builtChart = $this->dualScaleBarGraph();
                $this->chart = $this->load($builtChart);
                break;
            case 'simple_line_graph';
                $builtChart = $this->simpleLineGraph();
                $this->chart = $this->load($builtChart);
                break;
    
            }
        }
    }

    public function __toString()
    {
        return $this->chart;
    }

    /**
     * Render the finished chart
     * @return string
     */
    public function render()
    {
        return $this->chart;
    }

    /**
     * Add the "<script>" wrapper
     * @param string $built_chart
     * @return string
     */
    function load($built_chart = '')
    {
        $load = '<script type="text/javascript">';
        $load .= $built_chart;
        $load .= '</script>';
        return $load;
    }
    
    /**
     * create simple pie chart
     *
     * @return PieChart
     */
    private function simplePieChart()
    {
        $render = new PieChart($this->data);
        return $render;
    }

    /**
     * Create a simple Bar Graph
     * https://bl.ocks.org/mbostock/3885304
     * 
     * @return BarGraph
     */
    private function simpleBarGraph()
    {
        $render = new BarGraph($this->data);
        return $render;
    }

    /**
     * Create a Dual Scale Bar Graph
     * https://bl.ocks.org/mbostock/3885304
     *
     * @return DualScaleBarGraph
     */
    private function dualScaleBarGraph()
    {
        $render = new DualScaleBarGraph($this->data);
        return $render;
    }

    /**
     * Create a simple Line Graph
     * https://bl.ocks.org/mbostock/3885304
     *
     * @return LineGraph
     */
    private function simpleLineGraph()
    {
        $render = new LineGraph($this->data);
        return $render;
    }
}
