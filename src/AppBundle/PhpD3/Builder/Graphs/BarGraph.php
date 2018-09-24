<?php
namespace AppBundle\PhpD3\Builder\Graphs;

use AppBundle\PhpD3\Builder\Builder;

class BarGraph extends Builder
{
    public $chartComplete;

    protected $dataFile = '';
    protected $height = '';
    protected $width = '';
    protected $marginRight = '';
    protected $marginLeft = '';
    protected $marginTop = '';
    protected $marginBottom = '';
    protected $ticks = 10;
    protected $xAxisOrient = "bottom";
    protected $yAxisOrient = "left";
    protected $xAxisLabel;
    protected $yAxisLabel;
    protected $colors;
    protected $renderElement;
    protected $fileType;
    protected $data;
    protected $renderElementId;

    function __construct($fullDataArray = array())
    {

        parent::__construct();

        $this->dataFile = $fullDataArray['data_file'];
        $this->fileType = isset($fullDataArray['file_type']) ? $fullDataArray['file_type'] : 'tsv';

        $this->data = isset($fullDataArray['chart_data']) ? $fullDataArray['chart_data'] : $this->prepData->run($this->dataFile, $this->fileType);

        $this->height= isset($fullDataArray['dimensions']['height']) ? $fullDataArray['dimensions']['height'] : 500;
        $this->width= isset($fullDataArray['dimensions']['width']) ? $fullDataArray['dimensions']['width'] : 960;
        $this->xAxisLabel = $fullDataArray['axis_data']['x_axis_label'];
        $this->yAxisLabel = $fullDataArray['axis_data']['y_axis_label'];
        $this->marginTop = isset($fullDataArray['margins']['top']) ? $fullDataArray['margins']['top'] : 20;
        $this->marginBottom = isset($fullDataArray['margins']['bottom']) ? $fullDataArray['margins']['bottom'] : 30;
        $this->marginLeft = isset($fullDataArray['margins']['left']) ? $fullDataArray['margins']['left'] : 40;
        $this->marginRight = isset($fullDataArray['margins']['right']) ? $fullDataArray['margins']['right'] : 20;

        $this->autosize = isset($fullDataArray['autosize']) ? $fullDataArray['autosize'] : false;

        $this->renderElement = '';
        if(isset($fullDataArray['render_element']['value'])) {
            $type = '#';

            if($fullDataArray['render_element']['type'] === 'class') {
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

        $this->chartComplete = $this->buildGraph();
    }

    public function __toString()
    {
        return $this->chartComplete;
    }

    function buildGraph()
    {
        $dimensions = "
            // set the dimensions and margins of the graph
            var margin = {top: ".$this->marginTop.", right: ".$this->marginRight.", bottom: ".$this->marginBottom.", left: ".$this->marginLeft."},
            width = ".$this->width." - margin.left - margin.right,
            height = ".$this->height." - margin.top - margin.bottom;";

            $graph="
            
            var data = ".$this->data.";
            
            // set the ranges
            var x = d3.scaleBand().range([0, width]).padding(0.1);
            var y = d3.scaleLinear().range([height, 0]);
                      
            // append the svg object to the body of the page
            // append a 'group' element to 'svg'
            // moves the 'group' element to the top left margin
            var svg = d3.select(\"".$this->renderElement."\").append(\"svg\")
            .attr(\"width\", width + margin.left + margin.right)
            .attr(\"height\", height + margin.top + margin.bottom)
            .append(\"g\")
            .attr(\"transform\", 
                  \"translate(\" + margin.left + \",\" + margin.top + \")\");      
            
            function type(d) {
                d.".$this->yAxisLabel." = +d.".$this->yAxisLabel.";
                return d;
            };
            
            // Scale the range of the data in the domains
            x.domain(data.map(function(d) { return d.".$this->xAxisLabel."; }));
            y.domain([0, d3.max(data, function(d) { return d.".$this->yAxisLabel."; })]);
            
            // append the rectangles for the bar chart
            svg.selectAll(\".bar\")
            .data(data)
            .enter().append(\"rect\")
            .attr(\"class\", \"bar\")
            .attr(\"x\", function(d) { return x(d.".$this->xAxisLabel."); })
            .attr(\"width\", x.bandwidth())
            .attr(\"y\", function(d) { return y(d.".$this->yAxisLabel."); })
            .attr(\"height\", function(d) { return height - y(d.".$this->yAxisLabel."); });
            
            // add the x Axis
            svg.append(\"g\")
            .attr(\"transform\", \"translate(0,\" + height + \")\")
            .call(d3.axisBottom(x))
            .selectAll(\"text\")
            .style(\"text-anchor\", \"end\")
            .attr(\"dx\", \"-.8em\")
            .attr(\"dy\", \".15em\")
            .attr(\"transform\", \"rotate(-65)\");
            
            // add the y Axis
            svg.append(\"g\").call(d3.axisLeft(y));"
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
