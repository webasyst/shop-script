var ExtendedSalesGraph = ( function($, d3) {

    ExtendedSalesGraph = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options.$wrapper;
        that.$tooltip = options.$tooltip;
        that.$divider = options.$divider;
        that.d3wrapper = d3.select(that.$wrapper[0]);

        // VARS
        that.data = getData(options.data);
        that.margin = {
            top: 8,
            right: 8,
            bottom: 20,
            left: 8
        };
        that.heightPercent = (98/100);  // 100%
        that.area = getGraphArea(that);
        that.chart_names = options["chart_names"];
        that.currency = options["currency"];
        that.shown_class = "is-shown";

        // DYNAMIC VARS
        that.svg = false;
        that.defs = false;
        that.svgArea = false;
        that.svgLine = false;
        that.x = false;
        that.y = false;
        that.colors = [];

        // INIT
        that.initGraph();
    };

    ExtendedSalesGraph.prototype.initGraph = function() {
        var that = this,
            data = that.data,
            graphArea = that.area;

        // DOMAINS

        var x = that.x = d3.time.scale().range([0, graphArea.inner_width]),
            y = that.y = d3.scale.linear().range([graphArea.inner_height, 0]);

        var borders = getBorders(data),
            min_date = borders.date.min,
            max_date = borders.date.max,
            min_value = borders.value.min,
            max_value = borders.value.max;

        max_value = max_value/that.heightPercent;

        x.domain([min_date, max_date]);
        y.domain([min_value, max_value]);

        // PATHs and LINEs

        that.svgLine = d3.svg.line()
            .interpolate("monotone")
            .x(function(d) { return x(d.date); })
            .y(function(d) { return y(d.value + d.y0); });

        that.svgArea = d3.svg.area()
            .interpolate("monotone")
            .x(function(d) {
                return x(d.date);
            })
            .y(function(d) {
                return y(d.value + d.y0);
            })
            .y0( function(d) { return y(d.y0); });

        // DOM

        that.svg = that.d3wrapper
            .append("svg")
                .attr("width", graphArea.outer_width)
                .attr("height", graphArea.outer_height);

        // that.defs = that.svg.append("defs");

        // RENDER GRAPHS

        that.renderBackground();

        var charts = that.svg.append("g")
            .attr("class", "charts")
            .attr("transform", "translate(" + that.margin.left + "," + that.margin.top + ")");

        for (var index = 0; index < data.length; index++) {
            var chartData = data[index];
            that.renderChart( index, chartData, charts );
        }

        that.renderAxis();

        // that.renderPoints();

        that.markTableColors();

        that.initHoverEvents();
    };

    ExtendedSalesGraph.prototype.renderChart = function( chart_index, data, wrapper ) {
        var that = this,
            area = that.svgArea,
            color = getColor(chart_index);

        var chart = wrapper.append("g")
            .attr("class", "chart-" + ( parseInt(chart_index) + 1));

        var path = chart.datum(data);

        path.append("path")
            .attr("class", "area")
            .attr("d", area(data) )
            .style("fill", color);

        // Save color
        that.colors[chart_index] = color;
    };

    ExtendedSalesGraph.prototype.renderBackground = function() {
        var that = this,
            width = that.area.inner_width,
            height = that.area.inner_height,
            y_tick_count = 5;

        var background = that.svg.append("g")
            .attr("class", "background");

        background.append("rect")
            .attr("width", width)
            .attr("height", height);

        for (var i = 0; i <= y_tick_count; i++) {
            var yVal = 1 + (height - 2) / y_tick_count * i;

            background.append("line")
                .attr("x1", 1)
                .attr("x2", width)
                .attr("y1", yVal)
                .attr("y2", yVal)
            ;
        }
    };

    ExtendedSalesGraph.prototype.renderAxis = function() {
        var that = this;

        var xAxis = d3.svg.axis()
            .scale(that.x)
            .ticks(10)
            .orient("bottom");

        var yAxis = d3.svg.axis()
            .scale(that.y)
            .ticks(3)
            .orient("right");

        var axis = that.svg.append("g")
            .attr("class", "axis");

        axis.append("g")
            .attr("class", "x")
            .attr("transform","translate(" + that.margin.left + "," + (that.area.outer_height - that.margin.bottom) + ")")
            .call(xAxis)
            .selectAll(".tick text")
            .call(wrap);

        axis.append("g")
            .attr("class", "y")
            .attr("transform","translate(" + that.margin.left + "," + that.margin.top + ")")
            .call(yAxis);

        // Обрезаем текст
        function wrap(text) {
            var length = that.$wrapper.find(".x.axis .tick").length,
                period = 12,
                delta = Math.floor(length/period);

            if (delta > 0) {
                var i = 0;
                text.each( function() {
                    if (i === delta) {
                        i = 0;
                    } else {
                        d3.select(this).text("");
                        i++;
                    }
                });
            }
        }
    };

    ExtendedSalesGraph.prototype.renderPoints = function() {
        var that = this,
            data = that.data;

        var points = that.svg.append("g")
            .attr("class", "points")
            .attr("transform","translate(" + that.margin.left + "," + that.margin.top + ")");

        for (var index = 0; index < data.length; index++) {
            var chartData = data[index];
            var chart = points.append("g")
                .attr("class", "chart chart-" + index);

            chart.selectAll(".point")
                .data(chartData)
                .enter()
                .append("circle")
                .attr("cx", function(d) { return that.x(d.date); })
                .attr("cy", function(d) { return that.y(d.value + d.y0); })
                .attr("r", 4)
                .attr("class", function(d, i) {
                    return "point point-" + i;
                })
                .attr("data-point-id", function(d, i) {
                    return i;
                })
                .attr("data-value", function(d) {
                    return d.value;
                })
                .style("fill", that.colors[index]);
        }

    };

    ExtendedSalesGraph.prototype.markTableColors = function() {
        var that = this,
            $points = $(".s-chart-color");

        if ($points.length) {
            $points.each( function() {
                var $point = $(this),
                    color_id = $point.data("color-id"),
                    color = that.colors[color_id];

                if (color) {
                    $point.css("background", color);
                }
            })
        } else {
            setTimeout( function () {
                that.markTableColors();
            }, 500);
        }
    };

    ExtendedSalesGraph.prototype.initHoverEvents = function() {
        var that = this;

        var inactive_radius = 4,
            active_radius = 6;

        // DYNAMIC VARS
        var current_step_id = false,
            $activePoints = false;

        // EVENTS

        that.$wrapper
            .closest(".extended-sales-graph-wrapper")
                .on("mousemove", onMouseMove)
                .on("mouseleave", onMouseLeave);

        // FUNCTIONS

        function onMouseMove(event) {
            var mouse_left = event.pageX,
                mouse_top = event.pageY,
                blockOffset = that.$wrapper.offset(),
                delta_x = parseInt(blockOffset.left - mouse_left),
                delta_y = parseInt(blockOffset.top - mouse_top);

            if (delta_x < 0 ) {
                var stepData = getStepData( Math.abs(delta_x) - that.margin.left ),
                    step_position = stepData.position,
                    step_id = stepData.id;

                if (step_id >= 0 && (step_id !== current_step_id)) {

                    showDivider( step_position + that.margin.left );

                    // hidePoints();
                    // showPoints(step_id);
                    renderTooltip( step_position + that.margin.left, Math.abs(delta_y), step_id ); // Math.abs(delta_x), Math.abs(delta_y), step_id
                    current_step_id = step_id;
                }
            }
        }

        function getStepData( position ) {
            if (position < 0) {
                return 0;
            }

            var step_count = that.data[0]?.length,
                width = that.area.inner_width,
                step_width = width/( step_count - 1),
                result;

            var prev_step_id = Math.floor(position/step_width),
                residue = position % step_width;

            result = ( residue * 2 > step_width ) ? prev_step_id + 1 : prev_step_id;

            if (result >= step_count) { result = step_count - 1; }

            return {
                id: result,
                position: result * step_width
            };
        }

        function onMouseLeave() {
            // hidePoints();
            hideDivider();
            hideTooltip();
        }

        function hidePoints() {
            if ($activePoints) {
                $activePoints.attr("r", inactive_radius);
            }
        }

        function showPoints( step_id ) {
            var $points = that.svg.selectAll(".points .point-" + step_id );
            $points.attr("r", active_radius);
            $activePoints = $points;
        }

        function renderTooltip( mouse_left, mouse_top, step_id ) {
            var hint_date = getHintDate( step_id ),
                values = getValues( step_id ),
                html = getHintHTML(hint_date, values);

            showTooltip(mouse_left, mouse_top, html);

            function getHintDate( step_id ) {
                var date = that.data[0][step_id].date,
                    year = date.getFullYear(),
                    day = date.getDate(),
                    month_id = date.getMonth(),
                    show_day = ( (that.data[0][1].date - that.data[0][0].date)/(1000 * 60 * 60 * 24) <= 2 ),
                    month_name = getMonthName( month_id, show_day );

                return ( (show_day) ? day + " " : "" ) + month_name + " " + year;

                function getMonthName( month_id, show_day ) {
                    var month_array = [ "January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December" ];
                    if (navigator.language === "ru" || "ru-RU") {
                        month_array = (!show_day) ?
                            [ "Январь", "Февраль", "Март", "Апрель", "Май", "Июнь", "Июль", "Август", "Сентябрь", "Октябрь", "Ноябрь", "Декабрь" ]:
                            [ "Января", "Февраля", "Марта", "Апреля", "Мая", "Июня", "Июля", "Августа", "Сентября", "Октября", "Ноября", "Декабря" ];
                    }

                    return month_array[month_id];
                }
            }

            function getValues(step_id) {
                var result = [];

                $.each(that.data, function(index, chart) {
                    result.push({
                        name: ( that.chart_names[index] || "No name" ),
                        value: that.currency.replace(":value:", chart[step_id].value),
                        color: that.colors[index]
                    });
                });

                return result;
            }

            function getHintHTML(date, values) {
                var date_html = '<div class="date">:date:</div>',
                    value_html = '<div class="chart-value"><span class="color" style="background: :color:;"></span><span class="value">:value:</span><span class="name">:name:</span></div>',
                    values_html = "";

                $.each(values, function(index, item) {
                    values_html += value_html
                        .replace(":name:", item.name)
                        .replace(":value:", item.value)
                        .replace(":color:", item.color);
                });

                return date_html.replace(":date:", date) + values_html;
            }

            function showTooltip(mouse_left, mouse_top, html) {
                that.$tooltip.html(html);

                var position = getPosition(mouse_left, that.$tooltip.outerWidth(), that.$tooltip.outerHeight());

                that.$tooltip
                    .css({
                        bottom: position.bottom,
                        left: position.left
                    })
                    .addClass(that.shown_class);

                function getPosition(mouse_left, width, height ) {
                    var space = 20,
                        bottom = that.margin.bottom,
                        left = 20;

                    var right_space = ( width < that.area.outer_width - mouse_left - space );
                    if (right_space) {
                        left = mouse_left + space;
                    } else {
                        left = mouse_left-(space + width);
                    }

                    // var bottom_space = ( height < that.area.outer_height - mouse_top - space );
                    // if (bottom_space) {
                    //     bottom = that.area.outer_height - mouse_top - height;
                    // } else {
                    //     bottom = space;
                    // }

                    return {
                        left: left,
                        bottom: bottom
                    }
                }
            }
        }

        function hideTooltip() {
            that.$tooltip.removeClass(that.shown_class);
        }

        function showDivider( left) {
            that.$divider
                .addClass(that.shown_class)
                .css("left", left)
        }

        function hideDivider() {
            that.$divider
                .removeClass(that.shown_class)
                .css("left", 0)
        }
    };

    return ExtendedSalesGraph;

    // FUNCTIONS

    function getData(data) {
        var cloneData = data.slice(0),
            result = [];

        $.each(cloneData, function(index) {
            var chartData = cloneData[index],
                resultPoint = [];

            $.each(chartData, function(index) {
                var chartPoint = chartData[index],
                    date_string = "" + chartPoint.date;

                resultPoint.push({
                    date: formatDate( date_string ),
                    value: formatValue(chartPoint["profit"])
                });
            });

            result.push(resultPoint);
        });

        var stack = d3.layout.stack()
            .offset("zero")
            .values( function(d) { return d; })
            .x(function(d) { return d.date; })
            .y(function(d) { return d.value; });

        return stack(result);

        function formatDate( date_string ) {
            var year = date_string.substr(0,4),
                mount = parseInt(date_string.substr(4,2)) - 1,
                day = date_string.substr(6,2);

            return new Date(year, mount, day);
        }

        function formatValue( value ) {
            return Math.floor(value * 100)/100;
        }
    }

    function getGraphArea(that) {
        var margin = that.margin,
            width = that.$wrapper.outerWidth(),
            height = that.$wrapper.outerHeight();

        return {
            outer_width: width,
            outer_height: height,
            inner_width: width - margin.left - margin.right,
            inner_height: height - margin.top - margin.bottom
        };
    }

    function getBorders(data) {
        var dateArray = [],
            valueArray = [];

        $.each(data, function(index, item) {
            $.each(item, function(index, item) {
                dateArray.push(item.date);
                valueArray.push(item.value + item.y0);
            });
        });

        return {
            date: {
                min: Math.min.apply(Math, dateArray),
                max: Math.max.apply(Math, dateArray)
            },
            value: {
                min: Math.min.apply(Math, valueArray),
                max: Math.max.apply(Math, valueArray)
            }
        };
    }

    function getColor(index) {
        var colors = [
                "#717cd3",
                "#4bc6f5",
                "#86ca65",
                "#f5ad5c",
                "#f36d57",
                "#f0f56d",
                "#a39f99",
                "#e3e3e3",
                "#9aa2df",
                "#83cfec",
                "#a8c59b",
                "#e4b580",
                "#d98c7f",
                "#eef0af",
                "#c8d0c9",
                "#ffffff"
            ],
            color_count = colors.length,
            result;

        if (index >= 0) {
            if (index < color_count) {
                result = colors[index];
            } else {
                result = getColor(index - color_count);
            }
        } else {
            result = colors[Math.round( Math.random() * (color_count - 1) )];
        }

        return result;
    }

})(jQuery, d3);
