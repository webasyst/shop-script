var CustomersGraph = ( function($, d3) { "use strict";

    function getGraphArea(that) {
        var margin = that.margin,
            width = that.node.offsetWidth,
            height = that.node.offsetHeight;

        return {
            outerWidth: width,
            outerHeight: height,
            innerWidth: width - margin.left - margin.right,
            innerHeight: height - margin.top - margin.bottom
        };
    }

    function getData(data, is_tv) {
        return [getData("customers")];

        function getData(chart_name) {
            var gradient = getColor(chart_name);

            var result = data.map( function(day_data) {
                return {
                    date: getDate(day_data.date),
                    value: day_data[chart_name]
                };
            });

            if (gradient) {
                result.gradient = gradient;
            }

            return result;
        }

        function getDate(date_string) {
            var year = date_string.substr(0,4),
                mount = parseInt(date_string.substr(4,2), 10) - 1,
                day = date_string.substr(6,2);

            return new Date(year, mount, day);
        }

        function getColor(chart_name) {
            var colors = {
                    "customers": {
                        "default": $.is_wa2 ? ["rgba(0, 192, 237, 0.4)","rgba(0, 192, 237, 0.1)"] : ["#cad7e8","#eaeff6"],
                        "tv": ["#6d8896","#293338"]
                    }
                },
                result = false;

            if (colors.hasOwnProperty(chart_name)) {
                var chart_gradient = (!is_tv) ? colors[chart_name]["default"] : colors[chart_name]["tv"];

                result = {
                    "name": chart_name,
                    "start": chart_gradient[0],
                    "end": chart_gradient[1]
                }
            }

            return result;
        }

    }

    function Graph(options) {
        var that = this;

        // DOM
        that.node = options.node;
        that.d3node = d3.select(that.node);

        // VARS
        that.lineHeight = 3;
        that.is_tv = $("body").hasClass("tv");
        that.indent = Math.ceil(that.lineHeight/2);
        that.margin = {
            top: ( that.indent + Math.ceil(that.lineHeight/2) ),
            right: 0,
            bottom: 0,
            left: 0
        };
        that.heightPercent = ( options.height || (100/100) );  // 50%
        that.data = getData(options.data, that.is_tv);
        that.area = getGraphArea(that);
        that.widget_id = options.widget_id;
        that.refreshTime = 30 * 60 * 1000;
        that.animate_time = 1000;
        that.widget_id = options.widget_id;

        // DYNAMIC VARS
        that.uniqid = '' + (new Date).getTime() + Math.random();
        that.svg = false;
        that.defs = false;
        that.svgArea = false;
        that.svgLine = false;
        that.xDomain = false;
        that.yDomain = false;
        that.path = {
            area: false,
            line: false
        };
        that.timer = 0;

        // INIT
        that.initGraph();
        that.setupAutoReload();
    }

    Graph.prototype.initGraph = function() {
        var that = this,
            data = that.data,
            graphArea = that.area;

        var minSales = d3.min(data[0], function(d) {
            return d.value;
        });

        var maxSales = d3.max(data[0], function(d) {
            return ( d.value );
        });

        minSales -= (maxSales - minSales);
        maxSales = maxSales/that.heightPercent;

        var x = d3.time.scale().range([0, graphArea.innerWidth]);

        var y = d3.scale.linear().range([graphArea.innerHeight, 0]);

        that.svgLine = d3.svg.line()
            .interpolate("monotone")
            .x(function(d) { return x(d.date); })
            .y(function(d) { return ( y(d.value) - that.indent ); });

        that.svgArea = d3.svg.area()
            .interpolate("monotone")
            .x(function(d) { return x(d.date); })
            .y(function(d) { return ( y(d.value) - that.indent ); })
            .y0( function() { return y(minSales); });

        that.svg = that.d3node
            .append("svg")
            .attr("width", graphArea.outerWidth)
            .attr("height", graphArea.outerHeight);

        that.xDomain = d3.extent(data[0], function(d) {
            return d.date;
        });

        that.yDomain = [minSales, maxSales];

        x.domain(that.xDomain);
        y.domain(that.yDomain);

        that.defs = that.svg.append("defs");

        // Render Graphs
        for (var index = 0; index < that.data.length; index++) {
            var chartData = that.data[index],
                chartGradient = chartData.gradient,
                gradient_name;

            if (chartGradient) {

                gradient_name = "gradient_" + chartGradient.name;

                var gradient = that.defs.append("linearGradient")
                    .attr("id", gradient_name)
                    .attr("x1", "0%")
                    .attr("y1", "0%")
                    .attr("x2", "0")
                    .attr("y2", "100%")
                    .attr("spreadMethod", "pad");

                gradient.append("stop")
                    .attr("offset", "0%")
                    .attr("stop-color", chartGradient.start)
                    .attr("stop-opacity", 1);

                gradient.append("stop")
                    .attr("offset", "100%")
                    .attr("stop-color", chartGradient.end)
                    .attr("stop-opacity", 1);
            }

            that.renderChart( index, chartData, gradient_name );
        }
    };

    Graph.prototype.renderChart = function( chart_index, data, gradient_name ) {
        var that = this,
            svg = that.svg,
            area = that.svgArea,
            line = that.svgLine,
            start_indent_percent = ( data.start_indent_percent || 0 );

        if (start_indent_percent) {
            data = angular.copy(data);
            var percent = start_indent_percent/100, // .25
                min = that.yDomain[0],
                max = that.yDomain[1],
                delta = max - min,
                lift = delta * percent; // 10 * .5 = 5

            for (var i = 0; i < data.length; i++) {
                var item = data[i],
                    item_value = item.value;

                item.value = lift + item_value * (1 - percent);
            }
        }

        var path = svg.append("g")
            .attr("class", "path-wrapper chart-" + ( parseInt(chart_index) + 1))
            .attr("transform", "translate(" + that.margin.left + "," + that.margin.top + ")")
            .datum(data);

        that.path.area = path
            .append("path")
            .attr("class", "area")
            .attr("d", area(data) );

        if (gradient_name) {
            that.path.area.style("fill", "url(#" + gradient_name + ")");
        }

        that.path.line = path.append("path")
            .attr("class", "line")
            .attr("d", line(data) );

    };

    Graph.prototype.refreshChart = function( data ) {
        var that = this,
            area = that.svgArea,
            line = that.svgLine,
            pathArea = that.path.area,
            pathLine = that.path.line;

        pathArea
            .transition()
            .duration(that.animate_time)
            .attr("d", area(data));

        pathLine
            .transition()
            .duration(that.animate_time)
            .attr("d", line(data));
    };

    Graph.prototype.setupAutoReload = function() {
        var that = this;
        setTimeout(function() {
            try {
                DashboardWidgets[that.widget_id].uniqid = that.uniqid;
                setTimeout(function() {
                    try {
                        if (that.uniqid == DashboardWidgets[that.widget_id].uniqid) {
                            DashboardWidgets[that.widget_id].renderWidget();
                        }
                    } catch (e) {
                        console && console.log('Error updating Sales widget', e);
                    }
                }, 30*60*1000);
            } catch (e) {
                console && console.log('Error setting up Sales widget updater', e);
            }
        }, 0);
    };

    return Graph;

})(jQuery,d3);
