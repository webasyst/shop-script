// Chart
var showSalesGraph = function( data, cash_type ) {
if (data.length) {

    var storage = {
        wrapper: $(".sales-wrapper"),
        hint_wrapper: $(".hint-wrapper"),
        document_width: false,
        padding: {
            top: 24,
            bottom: 24,
            right: 8,
            left: 8
        },
        point_radius: {
            default: 4,
            hover: 6
        },
        xTicks: 10,
        yTicks: 3,
        colorsArray: ["#a7e0a7","#009900","#ae4410"],
        hovered_point: false, // Container for points logic
        getStepWidth: function() {
            var that = this.getStepWidth,
                point_step;
            // Cache
            if (that.data) {
                point_step = that.data;
            } else {
                var $svg = this.wrapper.find("svg"),
                    offset = $svg.offset().left,
                    point_offset,
                    point_steps = [],
                    points_sum = 0;

                $svg.find(".profit-points-wrapper .point").each( function(index) {
                    point_offset = $(this).offset().left;
                    point_step = ( point_offset  + storage.point_radius.default - offset - storage.padding.left ) / index;
                    if (point_step > 0) {
                        points_sum += point_step;
                        point_steps.push(point_step);
                    }
                });

                point_step = points_sum/point_steps.length;
                that.data = point_step;
            }
            return point_step;
        },
        getTickFormat: function() {
            var first = data[0].date.getTime(),
                second = data[1].date.getTime(),
                delta = Math.abs(second - first),
                hour = 60 * 60 * 1000,
                day = hour * 24,
                month = day * 30,
                year = month * 12,
                tick_format;

            if (delta >= year) {
                tick_format = "year";
            } else if (delta > day) {
                tick_format = "month";
            } else {
                tick_format = "day";
            }

            return tick_format;
        },
        getWidth: function() {
            var that = this.getWidth,
                width;

            if (that.data) {
                width = that.data;
            } else {
                width = this.wrapper.width();
                that.data = width;
            }
            return width;
        },
        getHeight: function() {
            var width = this.getWidth();
            return (width > 989) ? "350" : (width * 9)/(16 * 1.7);
        },
        getInnerWidth: function() {
            return this.getWidth() - this.padding.left - this.padding.right;
        },
        getInnerHeight: function() {
            return this.getHeight() - this.padding.top - this.padding.bottom;
        }
    };

    // Переводим дату в нужный формат
    for ( var d in data) {
        if (data.hasOwnProperty(d)) {
            data[d].date = d3.time.format("%Y%m%d").parse( data[d].date );
        }
    }

    var x = d3.time.scale()
        .range([0, storage.getInnerWidth()]);

    var y = d3.scale.linear()
        .range([storage.getInnerHeight(), 0]);

    var line = d3.svg.line()
        .interpolate("monotone")
        .x(function(d) { return x(d.date); })
        .y(function(d) { return y(d.sales); });

    var area = d3.svg.area()
        .interpolate("monotone")
        .x(function(d) { return x(d.date); })
        .y(function(d) { return y(d.sales); })
        .y0( function() {
            return y(0);
        });

    // Hack for clear svg place
    $(".sales-wrapper").find("svg").remove();

    var svg = d3.select(".sales-wrapper").append("svg")
        .attr("width", storage.getWidth())
        .attr("height", storage.getHeight());
        //.attr("transform", "translate(" + storage.padding.left + "," + storage.padding.top + ")");

    // Set My Colors
    var color = d3.scale.category10();
    for ( var i = 0; i < storage.colorsArray.length; i++) {
        color.range()[i] = storage.colorsArray[i];
    }
    color.domain(d3.keys(data[0]).filter(function(key) { return key !== "date"; }));

    // Stacked Data
    var graphArray = color.domain().map(function(name) {
        return {
            name: name,
            values: data.map(function(d) {
                return {date: d.date, sales: +d[name]};
            })
        };
    });
    var minSales = d3.min(graphArray, function(c) { return d3.min(c.values, function(v) { return v.sales; }); });
    var maxSales = d3.max(graphArray, function(c) { return d3.max(c.values, function(v) { return v.sales; }); });

    // Increase Max on 10%
    maxSales += maxSales/9;

    x.domain(d3.extent(data, function(d) { return d.date; }));
    y.domain([minSales,maxSales]);

    // Render Patterns
    ( function(storage, svg, x, y) {
        // Generate Patterns DOM elements
        var defs = svg.append("defs"),
            topClipPath = defs.append("clipPath")
                .attr("id","top-cut-clip")
                .append("rect"),
            topClipPath2 = defs.append("clipPath")
                .attr("id","top-cut-clip2")
                .append("rect"),
            bottomClipPath = defs.append("clipPath")
                .attr("id","bottom-cut-clip")
                .append("rect"),
            bottomClipPath2 = defs.append("clipPath")
                .attr("id","bottom-cut-clip2")
                .append("rect");

        topClipPath
            .attr("width", storage.getInnerWidth())
            .attr("height", function() {
                return y(0);
            });

        topClipPath2
            .attr("width", storage.getInnerWidth())
            .attr("height", function() {
                return y(0) + 1.5;
            });

        bottomClipPath
            .attr("width", storage.getInnerWidth())
            .attr("height", function() {
                return storage.getInnerHeight() - y(0);
            })
            .attr("transform", function() {
                return "translate(0," + y(0) +")";
            });

        bottomClipPath2
            .attr("width", storage.getInnerWidth())
            .attr("height", function() {
                return storage.getInnerHeight() - y(0);
            })
            .attr("transform", function() {
                return "translate(0," + (y(0) + 1.5) + ")";
            });

    })(storage, svg, x, y);

    // Render фона
    ( function(storage, svg) {
        var width = storage.getInnerWidth(),
            height = storage.getInnerHeight(),
            xTicks = 31,
            yTicks = 5,
            i;

        var background = svg.append("g")
            .attr("class", "background")
            .attr("transform", "translate(" + storage.padding.left + "," + storage.padding.top + ")");

        background.append("rect")
            .attr("width", width)
            .attr("height", height);

        for ( i = 0; i <= yTicks; i++) {
            var yVal = 1 + (height - 2) / yTicks * i;
            background.append("line")
                .attr("x1", 1)
                .attr("x2", width)
                .attr("y1", yVal)
                .attr("y2", yVal)
            ;
        }

        //for ( i = 0; i <= xTicks; i++) {
        //    var xVal = 1 + (width - 2) / xTicks * i;
        //    background.append("line")
        //        .attr("x1", xVal)
        //        .attr("x2", xVal)
        //        .attr("y1", 1)
        //        .attr("y2", height)
        //    ;
        //}
    })(storage, svg);

    // Рендер Осей
    ( function(storage, svg, xScale, yScale) {

        // Обрезаем текст
        function wrap(text) {
            var length = $(".x.axis .tick").length,
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

        var xAxis = d3.svg.axis()
            .scale(xScale)
            .ticks(storage.xTicks)
            .orient("bottom");

        var yAxis = d3.svg.axis()
            .scale(yScale)
            .ticks(storage.yTicks)
            .orient("right");

        svg.append("g")
            .attr("class", "x axis")
            .attr("transform","translate(" + storage.padding.left + "," + (yScale(0) + storage.padding.top) + ")")
            .call(xAxis)
            .selectAll(".tick text")
            .call(wrap);

        svg.append("g")
            .attr("class", "y axis")
            .attr("transform","translate(" + storage.padding.left + "," + storage.padding.top + ")")
            .call(yAxis);

    })(storage, svg, x, y);

    var chart = svg.append("g")
        .attr("class", "chart")
        .attr("transform", "translate(" + storage.padding.left + "," + storage.padding.top + ")");

    // Рендер графиков
    ( function() {
        var graph = chart.selectAll(".chart")
            .data(graphArray)
            .enter()
                .append("g")
                .attr("class", function(d) {
                    return d.name + " graph";
                });

        // Строим тело
        graph.append("path")
            .attr("class", "area")
            .attr("opacity","0")
            .attr("d", function(d) {
                return area(d.values.filter( function(d) {
                    return d;
                }));
            })
            .attr("clip-path", function(d) {
                return (d.name === "loss") ? "url(#bottom-cut-clip)" : "url(#top-cut-clip)";
            })
            .style("fill", function(d) { return color(d.name); });

        // Строим линию
        graph.append("path")
            .attr("class", "line")
            .attr("d", function(d) {
                return line(d.values.filter( function(d) {
                    //if (d.sales <= 0) {
                    //    d.sales = 0;
                    //}
                    return d;
                }));
            })
            .attr("clip-path", function(d) {
                return (d.name === "loss") ? "url(#bottom-cut-clip2)" : "url(#top-cut-clip2)";
            })
            .style("stroke", function(d) { return color(d.name); });

    })();

    // Рендер точек
    ( function() {
        var graph = chart.selectAll(".chart")
            .data(graphArray)
            .enter()
            .append("g")
            .attr("class", function(d) {
                return d.name + "-points-wrapper graph";
            })
            .style("fill", function(d) { return color(d.name); });

        // Строим точки
        graph.selectAll("circle")
            .data( function(d) {
                return d.values;
            })
            .enter()
            .append("circle")
            .attr("cx", function(d) { return x(d.date); })
            .attr("cy", function(d) { return y(d.sales); })
            .attr("r", storage.point_radius.default)
            .attr("class", function(d, i) {
                var point_class = "point point-" + i;
                if ( y(d.sales) === y(0)) {
                    point_class += " invisible";
                } else if ( y(d.sales) < y(0)) {
                    point_class += " positive";
                } else if ( y(d.sales) > y(0)) {
                    point_class += " negative";
                }
                return point_class;
            })
            .attr("data-point-class", function(d, i) {
                return "point-" + i;
            })
            .attr("data-value", function(d) {
                return d.sales;
            });
    })();

    var onHover = function(data, $target, event) {
        var $wrapper = storage.hint_wrapper,
            point_class = $target.data("point-class"),
            tickFormat = storage.getTickFormat(),
            position = getHintPosition(event, $target.offset()),
            month_array,
            profit_class,
            html = "",
            profit,
            sales,
            new_date = "";

        // Animate Point
        d3.selectAll("." + point_class)
            .transition()
            .attr("r", storage.point_radius.hover)
            .duration(150);

        // Generate Date
        month_array = [ "January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December" ];
        if (navigator.language === "ru") {
            month_array = [ "Январь", "Февраль", "Март", "Апрель", "Май", "Июнь", "Июль", "Август", "Сентябрь", "Октябрь", "Ноябрь", "Декабрь" ];
        }

        if (tickFormat === "day") {
            if (navigator.language === "ru") {
                month_array = [ "Января", "Февраля", "Марта", "Апреля", "Мая", "Июня", "Июля", "Августа", "Сентября", "Октября", "Ноября", "Декабря" ];
            }

            new_date += data.date.getDate() + " ";
        }
        // Month
        new_date += month_array[data.date.getMonth()] + " ";
        // Year
        new_date += data.date.getFullYear();
        profit = parseInt( $(".profit-points-wrapper").find("."+point_class).data("value") );
        sales = parseInt( $(".sales-points-wrapper").find("."+point_class).data("value") );

        // Get data for hint
        if (!cash_type) { cash_type = "$ %s"; }
        profit_class = (profit >= 0) ? "profit" : "negative";
        sales = cash_type.replace("%s", "<span class=\"volume\">" + sales + "</span>");
        profit = cash_type.replace("%s", "<span class=\"volume\">" + profit + "</span>");

        // Set data
        html += "<div class=\"line date\">" + new_date + "</div>";
        html += "<div class=\"line sales\">" + $_('Sales')  + ": " + sales + "</div>";
        html += "<div class=\"line " + profit_class + "\">" + $_('Profit') + ": " + profit + "</div>";

        $wrapper
            .html(html)
            .addClass("is-shown")
            .css(position);
    };

    var onHoverOut = function( $target ) {
        var $wrapper = storage.hint_wrapper;
        $wrapper.removeClass("is-shown");

        if ($target.length) {
            var point_class = $target.data("point-class");

            d3.selectAll("." + point_class)
                .transition()
                .attr("r", storage.point_radius.default)
                .duration(150);
        }
    };

    var getHintPosition = function( event, offset ) {
        var margin = {
            top: - parseInt( $("#maincontent").css("margin-top")) - 32,
            left: 36
        };

        var mouse_left = offset.left,
            hint_left = mouse_left + margin.left,
            hint_right = "auto",
            hint_width = 200;

        // set document width
        if (!storage.document_width) {
            storage.document_width = $(document).width();
        }

        // Check mouse place
        if (mouse_left >= (storage.document_width - hint_width)) {
            hint_left = "auto";
            hint_right = storage.document_width - mouse_left + margin.left
        }

        return {
            top: offset.top + margin.top,
            left: hint_left,
            right: hint_right
        }
    };

    storage.wrapper.on("mousemove ", "svg", function(event) {
        var current_x = event.clientX - $(this).offset().left - storage.padding.left,
            length = data.length,
            step_width,
            step_id,
            $point;

        if (current_x >= 0) {
            step_width = storage.getStepWidth();
            step_id = Math.round(current_x/step_width);
            if (step_id >= length) {
                step_id = length - 1
            }
            $point = $(".profit-points-wrapper .point-" + step_id).first();

            // Clearing
            if (storage.hovered_point) {
                onHoverOut(storage.hovered_point);
            }

            // Marking
            storage.hovered_point = $point;
            onHover(graphArray[0].values[step_id], $point, event);
        }
    });

    $(".graph-wrapper").on("mouseout", function() {
        onHoverOut(storage.hovered_point);
    });

}
};