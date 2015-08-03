var SalesGraph;

( function($) {

    var getGraphArea = function(that) {
        var $wrapper = that.$wrapper,
            margin = that.margin,
            width = $wrapper.outerWidth(),
            height = $wrapper.outerHeight();

        return {
            outerWidth: width,
            outerHeight: height,
            innerWidth: width - margin.left - margin.right,
            innerHeight: height - margin.top - margin.bottom
        }
    };

    var getGraphData = function(data) {
        // Переводим дату в нужный формат
        for ( var d in data) {
            if (data.hasOwnProperty(d)) {
                data[d].date = d3.time.format("%Y%m%d").parse( data[d].date );
            }
        }
        return data;
    };

    SalesGraph = function(options) {
        var that = this;

        // DOM
        that.node = document.getElementById(options.graph_id);
        that.d3_wrapper = d3.select(that.node);
        that.$wrapper = $(that.node);

        // Vars
        that.margin = {
            top: 0,
            right: 0,
            bottom: 0,
            left: 0
        };
        that.indent = 2;
        that.heightPercent = .6;  // 50%
        that.data = getGraphData(options.graph_data);
        that.area = getGraphArea(that);
        that.colors = [
            "#a7e0a7",
            "#009900"
        ];

        // Functions
        that.renderSalesGraph();
    };

    SalesGraph.prototype.renderSalesGraph = function() {
        var that = this,
            data = that.data,
            graphArea = that.area;

        var color = d3.scale.category10();

        for ( var i = 0; i < that.colors.length; i++) {
            color.range()[i] = that.colors[i];
        }
        color.domain( d3.keys(data[0]).filter( function(key) {
            return key !== "date";
        }));

        // Stacked Data
        var formatted_data = color.domain().map(function(name) {
            return {
                name: name,
                values: data.map(function(d) {
                    return {
                        date: d.date,
                        sales: parseInt(d[name]) // + parseInt( Math.random() * 10 )
                    };
                })
            };
        });

        var minSales = d3.min(formatted_data, function(c) {
            return d3.min(c.values, function(v) {
                return v.sales;
            });
        });

        var maxSales = d3.max(formatted_data, function(c) {
            return d3.max(c.values, function(v) {
                return v.sales;
            });
        });

        maxSales = maxSales/that.heightPercent;
        var deltaSales = (maxSales - minSales);
        var bottomPercent = 0;
        if (maxSales === minSales) {
            deltaSales = 1;
            maxSales += minSales + deltaSales * (1 - bottomPercent);
        }
        minSales -= deltaSales * bottomPercent;

        var x = d3.time.scale().range([0, graphArea.innerWidth]);

        var y = d3.scale.linear().range([graphArea.innerHeight, 0]);

        var line = d3.svg.line()
            .interpolate("monotone")
            .x(function(d) { return x(d.date); })
            .y(function(d) { return y(d.sales); });

        var area = d3.svg.area()
            .interpolate("monotone")
            .x(function(d) { return x(d.date); })
            .y(function(d) { return ( y(d.sales) - that.indent ); })
            .y0( function() { return y(minSales); });

        var svg = that.d3_wrapper
            .append("svg")
            .attr("width", graphArea.outerWidth)
            .attr("height", graphArea.outerHeight);

        x.domain(d3.extent(data, function(d) {
            return d.date;
        }));

        y.domain([minSales, maxSales]);

        var chart = svg
            .selectAll(".path-wrapper")
            .data(formatted_data)
            .enter()
                .append("g")
                .attr("class", "path-wrapper")
                .attr("transform", "translate(" + that.margin.left + "," + that.margin.top + ")");

        chart
            .append("path")
            .attr("class", "area")
            .attr("d", function(d) {
                return area(d.values.filter( function (d) {
                    if (d.sales <= 0) {
                        d.sales = 0;
                    }
                    return d;
                }));
            })
            .style("fill", function(d) { return color(d.name); });

        // Строим линию
        chart.append("path")
            .attr("class", "line")
            .attr("d", function(d) {
                return line(d.values.filter( function(d) {
                    if (d.sales <= 0) {
                        d.sales = 0;
                    }
                    return d;
                }));
            })
            .style("stroke", function(d) { return color(d.name); })
            .attr("transform", "translate(0,-" + that.indent + ")");

    };

})(jQuery);