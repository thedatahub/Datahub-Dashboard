<?php
namespace AppBundle\PhpD3\Builder\Graphs;

use AppBundle\PhpD3\Builder\Builder;

class LineGraph extends Builder
{
    public $completeChart;

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
    protected $pointCount;

    function __construct($fullDataArray = array())
    {
        parent::__construct();

        $this->dataFile = $fullDataArray['data_file'];
        $this->fileType = isset($fullDataArray['file_type']) ? $fullDataArray['file_type'] : 'tsv';

        $this->autosize = isset($fullDataArray['autosize']) ? $fullDataArray['autosize'] : false;

        $this->data = isset($fullDataArray['chart_data']) ? $fullDataArray['chart_data'] : $this->prepData->run($this->dataFile, $this->fileType);
        $this->pointCount = count(json_decode($this->data));

        $this->height= isset($fullDataArray['dimensions']['height']) ? $fullDataArray['dimensions']['height'] : 500;
        $this->width= isset($fullDataArray['dimensions']['width']) ? $fullDataArray['dimensions']['width'] : 960;
        $this->xAxisLabel = $fullDataArray['axis_data']['x_axis_label'];
        $this->yAxisLabel = $fullDataArray['axis_data']['y_axis_label'];
        $this->marginTop = isset($fullDataArray['margins']['top']) ? $fullDataArray['margins']['top'] : 20;
        $this->marginBottom = isset($fullDataArray['margins']['bottom']) ? $fullDataArray['margins']['bottom'] : 40;
        $this->marginLeft = isset($fullDataArray['margins']['left']) ? $fullDataArray['margins']['left'] : 80;
        $this->marginRight = isset($fullDataArray['margins']['right']) ? $fullDataArray['margins']['right'] : 40;


        $this->renderElement = '';
        if(isset($fullDataArray['render_element']['value'])) {
            $type = '#';

            if($fullDataArray['render_element']['type'] == 'class') {
                $type = '.';
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
        $dimensions = "
        
            // set the dimensions and margins of the graph
            var margin = {top: ".$this->marginTop.", right: ".$this->marginRight.", bottom: ".$this->marginBottom.", left: ".$this->marginLeft."},
            width = ".$this->width." - margin.left - margin.right,
            height = ".$this->height." - margin.top - margin.bottom;";

            $graph ="
            
            var x = d3.scaleBand().rangeRound([0, width]).padding(0.1),
            y = d3.scaleLinear().rangeRound([height, 0]);
            
            var data = ".$this->data.";
                      
            
            var svg = d3.select(\"".$this->renderElement."\").append(\"svg\")
            .attr(\"width\", width + margin.left + margin.right)
            .attr(\"height\", height + margin.top + margin.bottom)    
            
            
            var g = svg.append(\"g\")
            .attr(\"transform\", \"translate(\" + margin.left + \",\" + margin.top + \")\");
            
            var line = d3.line()
            .x(function(d) { return x(d.".$this->xAxisLabel.") + 15; })
            .y(function(d) { return y(d.".$this->yAxisLabel."); })
            
            x.domain(data.map(function(d) { return d.".$this->xAxisLabel."; }));
            y.domain([0, d3.max(data, function(d) { return d.".$this->yAxisLabel."; })]);
            
            g.append(\"g\")
            .attr(\"class\", \"axis axis--x\")
            .attr(\"transform\", \"translate(0,\" + height + \")\")
            .call(d3.axisBottom(x))
            .selectAll(\"text\")
            .style(\"text-anchor\", \"end\")
            .attr(\"dx\", \"-.8em\")
            .attr(\"dy\", \".15em\")
            .attr(\"transform\", \"rotate(-65)\");
            
            g.append(\"g\")
            .attr(\"class\", \"axis axis--y\")
            .call(d3.axisLeft(y).tickValues(y.ticks(10).concat(y.domain())))
            .append(\"text\")
            .attr(\"transform\", \"rotate(-90)\")
            .attr(\"y\", 6)
            .attr(\"dy\", \"0.71em\")
            .attr(\"text-anchor\", \"end\")
            .text(\"".$this->yAxisLabel."\");
            
            // add y axis label
            g.append(\"text\")
            .attr(\"transform\", \"rotate(-90)\")
            .attr(\"y\", 0 - margin.left)
            .attr(\"x\",0 - (height / 2))
            .attr(\"dy\", \"1em\")
            .style(\"text-anchor\", \"middle\")
            .attr(\"class\", \"axis-label\")
            .text(\"".ucfirst($this->yAxisLabel)."\");      
            
            g.append(\"path\")
            .datum(data)
            .attr(\"class\", \"line\")
            .attr(\"d\", line);
            
            var div = d3.select(\"body\").append(\"div\")
            .attr(\"class\", \"tooltip\")
            .style(\"opacity\", 0);
            
            g.selectAll(\"circle\")
            .data(data)
            .enter().append(\"circle\")
            .attr(\"class\", \"circle\")
            .attr(\"cx\", function(d) { return x(d.".$this->xAxisLabel.")+ 15; })
            .attr(\"cy\", function(d) { return y(d.".$this->yAxisLabel."); })
            .attr(\"r\", 4)
            .on(\"mouseover\", function(d) {
                div.transition()
                .duration(200)
                .style(\"opacity\", .9);
                div.html(d.".$this->xAxisLabel." + \"<br\/>\" + d.".$this->yAxisLabel.")
                .style(\"left\", (d3.event.pageX) + \"px\")
                .style(\"top\", (d3.event.pageY - 28) + \"px\");
            })
            .on(\"mouseout\", function(d) {
                div.transition()
                .duration(500)
                .style(\"opacity\", 0);
            });
            
            // add x axis label
            g.append(\"text\")             
            .attr(\"transform\",\"translate(\" + (width/2) + \" ,\" + (height + margin.top + 20) + \")\")
            .style(\"text-anchor\", \"middle\")
            .attr(\"class\", \"axis-label\")
            .text(\"".ucfirst($this->xAxisLabel)."\");"
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
