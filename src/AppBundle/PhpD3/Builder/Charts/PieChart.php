<?php
namespace AppBundle\PhpD3\Builder\Charts;

use AppBundle\PhpD3\Builder\Builder;

class PieChart extends Builder
{
    public $chart_complete;
    protected $radius = '';

    function __construct($fullDataArray = array())
    {
        parent::__construct();

        $this->data_array = $fullDataArray['chart_data'];
        $this->height= $fullDataArray['dimensions']['height'];
        $this->width= $fullDataArray['dimensions']['width'];
        $this->radius= $fullDataArray['dimensions']['radius'];
        $this->autosize = isset($fullDataArray['autosize']) ? $fullDataArray['autosize'] : false;

        $this->data = $this->prepData->run($this->data_array);

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
        
        $this->chart_complete = $this->buildChart();
    }

    public function __toString()
    {
        return $this->chart_complete;
    }

    function buildChart()
    {
        $dimensions = "
        var width = ".$this->width.";
        var height = ".$this->height.";";

        $chart = "
            
            var radius = Math.min(width, height) / 2;
            
            var  data = ".$this->data.";
            
            var color = d3.scaleOrdinal()
            .range(".$this->colors.");
            
            var arc = d3.arc()
            .outerRadius(radius - 10)
            .innerRadius(0);
            
            var labelArc = d3.arc()
            .outerRadius(radius - 40)
            .innerRadius(radius - 40);
            
            var pie = d3.pie()
            .sort(null)
            .value(function(d) { return d.value; });
            
            var svg = d3.select(\"".$this->renderElement."\").append(\"svg\")
            .attr(\"width\", width)
            .attr(\"height\", height)
            .append(\"g\")
            .attr(\"transform\", \"translate(\" + width / 2 + \",\" + height / 2 + \")\");
            
            var g = svg.selectAll(\".arc\")
            .data(pie(data))
            .enter().append(\"g\")
            .attr(\"class\", \"arc\");
            
            g.append(\"path\")
            .attr(\"d\", arc)
            .attr(\"fill\", function(d, i) { return color(i); } )
            
            g.append(\"text\")
            .attr(\"transform\", function(d) { return \"translate(\" + labelArc.centroid(d) + \")\"; })
            .attr(\"dy\", \".35em\")
            .text(function(d, i) { return data[i].label; });"
        ;

        $return = $dimensions.$chart;

        if($this->autosize) {
            $return = $this->resize($this->renderElementId,$chart);
        }

        return $return;
    }
}
