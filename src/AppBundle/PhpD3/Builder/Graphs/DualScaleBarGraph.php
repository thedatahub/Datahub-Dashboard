<?php
namespace AppBundle\PhpD3\Builder\Graphs;

use AppBundle\PhpD3\Builder\Builder;

class DualScaleBarGraph extends Builder
{
    public $completeChart;

    protected $data_file = '';
    protected $height = '';
    protected $width = '';
    protected $marginRight = '';
    protected $marginLeft = '';
    protected $marginTop = '';
    protected $marginBottom = '';
    protected $ticks = 10;
    protected $xAxisOrient = "bottom";
    protected $yAxisLeftOrient = "left";
    protected $yAxisRightOrient = "right";
    protected $xAxisLabel;
    protected $yAxisLabel;
    protected $yAxis2Label;
    protected $xAxisKey;
    protected $yAxisKey;
    protected $yAxis2Key;
    protected $colors;
    protected $renderElement;
    protected $fileType;
    protected $data;
    protected $seriesNum;
    protected $ranges;


    function __construct($fullDataArray = array())
    {

        parent::__construct();

        $this->data_file = $fullDataArray['data_file'];
        $this->fileType = isset($fullDataArray['file_type']) ? $fullDataArray['file_type'] : 'tsv';
        
        $this->data = isset($fullDataArray['chart_data']) ? $fullDataArray['chart_data'] : $this->prepData->run($this->data_file, $this->fileType);
        $this->ranges = $this->prepData->findDataRanges($this->data);

        $this->autosize = isset($fullDataArray['autosize']) ? $fullDataArray['autosize'] : false;

        $this->height= isset($fullDataArray['dimensions']['height']) ? $fullDataArray['dimensions']['height'] : 500;
        $this->width= isset($fullDataArray['dimensions']['width']) ? $fullDataArray['dimensions']['width'] : 960;
        $this->xAxisLabel = $fullDataArray['axis_data']['xAxis']['label'];
        $this->yAxisLabel = $fullDataArray['axis_data']['yAxis']['label'];
        $this->yAxis2Label = $fullDataArray['axis_data']['y2Axis']['label'];
        $this->xAxisKey = $fullDataArray['axis_data']['xAxis']['key'];
        $this->yAxisKey = $fullDataArray['axis_data']['yAxis']['key'];
        $this->yAxis2Key = $fullDataArray['axis_data']['y2Axis']['key'];
        $this->marginTop = (isset($fullDataArray['margins']['top'])) ? $fullDataArray['margins']['top'] : 40;
        $this->marginBottom = (isset($fullDataArray['margins']['bottom'])) ? $fullDataArray['margins']['bottom'] : 40;
        $this->marginLeft = (isset($fullDataArray['margins']['left'])) ? $fullDataArray['margins']['left'] : 70;
        $this->marginRight = (isset($fullDataArray['margins']['right'])) ? $fullDataArray['margins']['right'] : 40;


        $this->renderElement = '';
        if(isset($fullDataArray['render_element']['value'])) {
            $type = '#';
            if($fullDataArray['render_element']['type'] == 'class') {
                $type='.';
            }

            $this->renderElement = $type.$fullDataArray['render_element']['value'];
            $this->renderElementId = $fullDataArray['render_element']['value'];
        }

        if(isset($fullDataArray['colors'])) {
            $this->colors = '["'.implode('","', $fullDataArray['colors']).'"]';
        } else {
            $this->colors = '["#98abc5", "#8a89a6", "#7b6888", "#6b486b", "#a05d56", "#d0743c", "#ff8c00"]';
        }

        $this->completeChart = $this->buildGraph();
    }

    public function __toString()
    {
        return $this->completeChart;
    }


    function buildGraph()
    {
        $low_y_axis = $this->ranges[$this->yAxisKey]['low'] - 200;
        $low_y2_axis = $this->ranges[$this->yAxis2Key]['low'] - 15;

        $high_y_axis = $this->ranges[$this->yAxisKey]['high'] + 100;
        $high_y2_axis = $this->ranges[$this->yAxis2Key]['high'] + 5;

        $dimensions = "
            var margin = {top: " . $this->marginTop.", right: " . $this->marginRight.", bottom: " . $this->marginBottom . ", left: " . $this->marginLeft . "},
            width = ".$this->width." - margin.left - margin.right,
            height = ".$this->height." - margin.top - margin.bottom;";

            $graph = "
            var x = d3.scaleBand().range([0, width]).paddingInner(0.25).paddingOuter(0.25);
        
            var y0 = d3.scaleLinear().domain([".$low_y_axis.",".$high_y_axis."]).range([height, 0]),
                y1 = d3.scaleLinear().domain([".$low_y2_axis.",".$high_y2_axis."]).range([height, 0]);
        
            var xAxis = d3.axisBottom(x);
                
            var data = ".$this->data."
        
            // create left yAxis
            var yAxisLeft = d3.axisLeft(y0);
            // create right yAxis
            var yAxisRight = d3.axisRight(y1);
            
            var max = d3.max(data, function(d) { return d.".$this->yAxisKey."; });
        
            var svg = d3.select(\"".$this->renderElement."\").append(\"svg\")
            .attr(\"width\", width + margin.left + margin.right)
            .attr(\"height\", height + margin.top + margin.bottom)
            .append(\"g\")
            .attr(\"class\", \"graph\")
            .attr(\"transform\", \"translate(\" + margin.left + \",\" + margin.top + \")\");
        
            x.domain(data.map(function(d) { return d.".$this->xAxisKey."; }));
            y0.domain([0, max]);
        
            svg.append(\"g\")
            .attr(\"class\", \"x axis axis--x\")
            .attr(\"transform\", \"translate(0,\" + height + \")\")
            .call(xAxis)
            .selectAll(\"text\")
            .style(\"text-anchor\", \"end\")
            .attr(\"dx\", \"-.8em\")
            .attr(\"dy\", \".15em\")
            .attr(\"transform\", \"rotate(-65)\");
            
            svg.append(\"g\")
            .attr(\"class\", \"y axis axisLeft\")
            .attr(\"transform\", \"translate(0,0)\")
            .call(yAxisLeft)
            .append(\"text\")
            .attr(\"y\", 6)
            .attr(\"dy\", \"-2em\")
            .style(\"text-anchor\", \"end\")
            .text(\"".$this->yAxisLabel."\");
            
            svg.append(\"g\")
            .attr(\"class\", \"y axis axisRight\")
            .attr(\"transform\", \"translate(\" + (width) + \",0)\")
            .call(yAxisRight)
            .append(\"text\")
            .attr(\"y\", 6)
            .attr(\"dy\", \"-2em\")
            .attr(\"dx\", \"2em\")
            .style(\"text-anchor\", \"end\")
            .text(\"".$this->yAxis2Label."\");
    
            bars = svg.selectAll(\".bar\").data(data).enter();
    
            bars.append(\"rect\")
                .attr(\"class\", \"bar1\")
                .attr(\"x\", function(d) { return x(d.".$this->xAxisKey."); })
                .attr(\"width\", x.bandwidth()/2)
                .attr(\"y\", function(d) { return y0(d.".$this->yAxisKey."); })
                .attr(\"height\", function(d,i,j) { return height - y0(d.".$this->yAxisKey."); });
    
            bars.append(\"rect\")
                .attr(\"class\", \"bar2\")
                .attr(\"x\", function(d) { return x(d.".$this->xAxisKey.") + x.bandwidth()/2; })
                .attr(\"width\", x.bandwidth() / 2)
                .attr(\"y\", function(d) { return y1(d.".$this->yAxis2Key."); })
                .attr(\"height\", function(d,i,j) { return height - y1(d.".$this->yAxis2Key."); });
        
            
            function type(d) {
                d.money = +d.money;
                return d;
            }"
        ;

        $return = $dimensions.$graph;

        if($this->autosize) {
            $margins = [
                'margin_top' => $this->marginTop,
                'margin_left' => $this->marginLeft,
                'margin_right' => $this->marginRight,
                'margin_bottom' => $this->marginBottom
            ];
            $return = $this->resize($this->renderElementId, $graph, $margins);
        }

        return $return;
    }
}
