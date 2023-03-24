// Chart
var showCohortGraph = function(data_array, options) {
var data_length = data_array.length;
if (data_length) {

    var interpolate = "basis",
        stack_type = "zero";

    if (options) {
        if (options.hasOwnProperty("interpolate")) {
            interpolate = options.interpolate;
        }
        if (options.hasOwnProperty("stack_type")) {
            stack_type = options.stack_type;
        }
    }

    // Variables
    var storage = {
        wrapper: $(".cohort-wrapper"),
        hint_wrapper: $(".hint-wrapper"),
        padding: {
            top: 24,
            bottom: 24,
            left: 24,
            right: 24
        },
        getTickLength: function() {
            var width = this.getWidth(),
                data_x_length = data_array[0].data.length,
                max_tick_width = 70,
                max_tick_length = Math.floor(width/max_tick_width),
                new_tick_length;

            // If desktop has wide space => return
            if (max_tick_length > data_x_length) {
                return 1;
            } else {
                new_tick_length = Math.ceil(data_x_length/max_tick_length);
                return new_tick_length;
            }
        },
        getTickFormat: function() {
            var data = data_array[0].data,
                first = data[0].x,
                second = data[1].x,
                length = data.length,
                delta = Math.abs(second - first),
                hour = 60 * 60 * 1000,
                day = hour * 24,
                month = day * 30,
                year = month * 12,
                tick_format;

            if (delta >= year) {
                tick_format = d3.time.year;
            } else if (delta > day) {
                tick_format = d3.time.month;
            } else {
                tick_format = d3.time.day;
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
            var width = storage.getWidth();
            return (width > 989) ? "350" : (width * 9)/(16 * 1.7);
        },
        getInnerWidth: function() {
            return this.getWidth() - this.padding.left - this.padding.right;
        },
        getInnerHeight: function() {
            return this.getHeight() - this.padding.top - this.padding.bottom;
        }
    };

    // Stack All Values
    var stack = d3.layout.stack().offset(stack_type),
        layers = stack( d3.range(data_array.length).map( function(d) {
            var data_item = data_array[d];
            return d3.range(data_item.data.length).map( function(d) {
                return data_item.data[d];
            });
        }));

    var xScale = d3.time.scale()
        .domain([
            d3.min(data_array, function(d) {
                return d3.min(d.data, function(d) {
                    return d.x;
                });
            }),
            d3.max(data_array, function(d) {
                return d3.max(d.data, function(d) {
                    return d.x;
                });
            })
        ])
        .range([0, storage.getInnerWidth()]);

    var yScale = d3.scale.linear()
        .domain([0, d3.max(layers, function(layer) { return d3.max(layer, function(d) { return d.y0 + d.y; }); })])
        .range([storage.getInnerHeight(), 0]);

    var area = d3.svg.area()
        .interpolate(interpolate)
        .x(function(d) { return xScale(d.x); })
        .y0(function(d) { return yScale(d.y0); })
        .y1(function(d) { return yScale(d.y0 + d.y); });

    var svg = d3.select(".cohort-wrapper").append("svg")
        .attr("width", storage.getWidth())
        .attr("height", storage.getHeight());

    var cohort_type_name = $('#s-cohorts-type-selector li.selected a i').text();
    var onHover = function(data, event) {
        var $wrapper = storage.hint_wrapper,
            position = getHintPosition(event),
            html = "";

        html += "<div class=\"line date\">" + data.name + "</div>";
        html += "<div class=\"line sales\">"+cohort_type_name+": <b>" + data.cash_type + "</b></div>";

        $wrapper
            .html(html)
            .addClass("is-shown")
            .css(position);
    };

    // Hint Moving
    var onMove = function(event) {
        var $wrapper = storage.hint_wrapper,
            position = getHintPosition(event);

        if (position) {
            $wrapper.css(position);
        } else {
            console.log("cant get mouse position");
        }
    };

    // Hide Hint
    var onHoverOut = function() {
        var $wrapper = storage.hint_wrapper;

        $wrapper.removeClass("is-shown")
    };

    // On Layer click
    $(".cohort-wrapper").on("click", ".layers .layer", function() {
        onClick( $(this) );
        return false;
    });

    // On Layer click
    var onClick = function( $target ) {
        var activeClass = "active-layer",
            is_active =  ( $target.attr("class").indexOf(activeClass) >= 0 ),
            $table_td = $("#s-report-cohorts-table .column-" + $target.attr("data-layer-id") + " .td-with-data"),
            default_color = $target.attr("data-fill"),
            active_color = "#ffec48";

        // Clear
        clearLayer(active_color);

        if (!is_active) {
            // Marking
            $target.attr("class","layer active-layer");

            // Table Marking
            $table_td.each( function() {
                var style = $(this).attr("style").replace(new RegExp(default_color,'g'),active_color);
                $(this).attr("style", style);
            });

            $table_td.closest("tr").addClass("active-cohort");
        }
    };

    // Clearing
    var clearLayer = function(active_color) {
        var $tr = $("#s-report-cohorts-table .active-cohort"),
            $td =  $tr.find(".td-with-data"),
            default_color = $td.data("default-color");

        $("svg .layers .active-layer").attr("class","layer");

        // Table Marking
        $td.each( function() {
            var style = $(this).attr("style").replace(new RegExp(active_color,'g'), default_color);
            $(this).attr("style", style);
        });

        $tr.removeClass("active-cohort");
    };

    var getHintPosition = function(event) {
        var margin = {
            top: - parseInt( $("#maincontent").css("margin-top")) - 32,
            left: 24
        };

        var mouse_left = event.pageX,
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
            top: event.pageY + margin.top,
            left: hint_left,
            right: hint_right
        }
    };

    // Render фона
    ( function(storage, svg) {
        var width = storage.getInnerWidth(),
            height = storage.getInnerHeight(),
            xTicks = 30,
            yTicks = 10,
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

    // Render Осей
    ( function(storage, svg, xScale, yScale) {

        // Обрезаем текст
        function wrap(text) {
            var word_size = 8,
                length = $(".x.axis .tick").length,
                width = storage.getInnerWidth(),
                text_count = (width/length)/word_size;

            if (text_count < 4) { text_count = 4 }

            text.each( function() {
                var text = d3.select(this),
                    words = text.text();

                if (words.length > text_count) {
                    text.text(words.substr( 0, text_count));
                }
            });
        }

        var xAxis = d3.svg.axis()
            .scale(xScale)
            .orient("bottom")
            .ticks(storage.getTickFormat(), storage.getTickLength());

        svg.append("g")
            .attr("class","x axis")
            .attr("transform","translate(" + storage.padding.left + "," + (storage.getHeight() - storage.padding.bottom) + ")")
            .call(xAxis)
            .selectAll(".tick text")
            .call(wrap);

        //var yAxis = d3.svg.axis()
        //    .scale(yScale)
        //    .orient("left")
        //    .ticks(storage.getTicks().x);

        //svg.append("g")
        //    .attr("class","y axis")
        //    .attr("transform","translate(" + storage.padding.left + "," + storage.padding.top + ")")
        //    .call(yAxis);

    })(storage, svg, xScale, yScale);

    $("#change-cohort-type").on("change", function() {
        onChangeCohortType($(this).val());
    });

    var onChangeCohortType = function( type ) {
        if (type === "center") {
            interpolate = "basis";
            stack_type = "silhouette";
        } else if (type === "bottom") {
            interpolate = "basis";
            stack_type = "zero";
        } else {
            return;
        }

        $.storage.set('shop/reports/cohorts/type', type);
        $("#change-cohort-type").val(type);

        stack = d3.layout.stack().offset(stack_type);

        layers = stack( d3.range(data_array.length).map( function(d) {
            var data_item = data_array[d];
            return d3.range(data_item.data.length).map( function(d) {
                return data_item.data[d];
            });
        }));

        area = d3.svg.area()
            .interpolate(interpolate)
            .x(function(d) { return xScale(d.x); })
            .y0(function(d) { return yScale(d.y0); })
            .y1(function(d) { return yScale(d.y0 + d.y); });

        renderCohort(storage, svg, layers, area);
    };

    svg.append("g")
        .attr("class","layers")
        .attr("transform", "translate("+ storage.padding.left +","+ storage.padding.top +")");

    // Render Графика
    var renderCohort = function(storage, svg, layers, area) {
        var layer = svg.select(".layers").selectAll(".layer")
            .data(layers);

        layer.transition()
            .duration(1000)
            .attr("d", area);

        layer.enter()
            .append("path")
            .attr("class","layer")
            .attr("d", area)
            .attr("data-fill", function(d,i) {
                return data_array[i].color;
            })
            .attr("data-layer-id", function(d,i) {
                return (i + 1);
            })
            .style("fill", function(d,i) {
                return data_array[i].color;
            })
            .on("mouseover", function(d, i) {
                onHover(data_array[i], d3.event);
            })
            .on("mousemove", function() {
                onMove(d3.event);
            })
            .on("mouseout", function() {
                onHoverOut();
            })
        ;
    };

    onChangeCohortType($.storage.get('shop/reports/cohorts/type'));
    renderCohort(storage, svg, layers, area);

}
};