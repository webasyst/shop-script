// Chart
var showPieGraph = function( data , options) {
if (data.length) {
// VARIABLES

    var storage = {
        wrapper: $(".pie-wrapper"),
        hint_wrapper: $(".hint-wrapper"),
        padding: {
            top: 30,
            bottom: 30,
            left: 10,
            right: 10
        },
        serviceFill: "#aaa",
        legend_icon_radius: 5,
        lineHeight: 1.35,
        getTextSize: function() {
            var text_size = 0;
            if (data.length > 3) {
                text_size = "12"
            } else {
                text_size = "14";
            }
            return text_size;
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
            var legend_height = this.getTextSize() * this.lineHeight, // +5;
                legends_height = legend_height * data.length,
                height = 0;

            height += this.padding.top + this.getRadius() * 2 + this.padding.bottom + legends_height;

            return height;
        },
        getInnerWidth: function() {
            return (this.getWidth() - this.padding.left - this.padding.right)
        },
        getInnerHeight: function() {
            return (this.getHeight() - this.padding.top - this.padding.bottom)
        },
        getRadius: function() {
            return (this.getInnerWidth()/2);
        }
    };

    // Detect SVG
    var svg_is_exist = (d3.select(".pie-wrapper svg")[0][0] !== null),
        svg;

    if (svg_is_exist) {
        svg = d3.select(".pie-wrapper svg")
            .attr("height", storage.getHeight());

        svg.select(".wrapper");

    } else {
        svg = d3.select(".pie-wrapper")
            .append("svg")
            .attr("width", storage.getWidth())
            .attr("height", storage.getHeight())
                .append("g")
                .attr("class","wrapper");
    }

    svg.append("g")
        .attr("class", "slices");
    svg.append("g")
        .attr("class", "legends-wrapper");
    svg.append("g")
        .attr("class", "percents");

    var pie = d3.layout.pie()
        .sort(null)
        .value(function (d) {
            return d.value;
        });

    var arc = d3.svg.arc()
        .outerRadius(storage.getRadius())
        .innerRadius(0); //storage.getRadius() *

    svg.select(".slices").attr("transform", "translate(" + storage.getWidth()/2 + ","+ ( storage.getRadius() + storage.padding.top ) + ")");

    var key = function (d) {
        return d.data.label;
    };

    var color = d3.scale.ordinal()
        .range(["#3fc0f0", "#ae6ed3", "#b768c5", "#ff8c00", "#d15dd5", "#6b486b", "#d0743c"]);

    if (options) {
        if (options.hasOwnProperty("color_type")) {
            if (options.color_type === "products") {
                color = d3.scale.ordinal()
                    .range(["#3491ee", "#aaa", "#f3f115", "#fa4343", "#31c5ca", "#f8af00", "#c572da", "#8fb64b", "#e75b30", "#a6a6a6"]);
            }
        }
    }

// PIE SLICES
    var slice = svg.select(".slices").selectAll("path.slice")
        .data(pie(data), key);

    slice.enter()
        .insert("path")
        .style("fill", function (d) {
            return color(d.data.label);
        })
        .attr("class", "slice")
        .on("mouseover", function(d, i) {
            onHover(data[i], d3.event);
        })
        .on("mousemove", function() {
            onMove(d3.event);
        })
        .on("mouseout", function() {
            onHoverOut();
        });

    slice
        .transition()
        .duration(1000)
        .attr("transform", function (d) {
            var translate = "0,0";
            if ( d.data.hasOwnProperty("service") && (d.data.service === true) ) {
                var center = arc.centroid(d),
                    delta = 7.5;
                translate = [center[0]/delta,center[1]/delta];
            }
            return "translate(" + translate + ")";
        })
        .style("fill", function (d) {
            var fill = color(d.data.label);
            if ( d.data.hasOwnProperty("service") && (d.data.service === true) ) {
                fill = storage.serviceFill;
            }
             return fill;
        })
        .attrTween("d", function (d) {
            this._current = this._current || d;
            var interpolate = d3.interpolate(this._current, d);
            this._current = interpolate(0);
            return function (t) {
                return arc(interpolate(t));
            };
        });

    slice.exit()
        .remove();

// TEXT LABELS
    ( function() {

        svg.select(".legends-wrapper")
            .attr("transform", "translate(" + (storage.getWidth() - storage.getRadius()*2)/2 + ", " + ( storage.padding.top + storage.getRadius() * 2 + storage.padding.bottom ) + ")");

        var legends = svg.select(".legends-wrapper").selectAll(".legend")
            .data(pie(data), key)
            .each( function(d,i) {
                var g = d3.select(this),
                    text_height = storage.getTextSize() * storage.lineHeight;

                g.selectAll("text")
                    .text(function (d) {
                        return d.data.label;
                    })
                    .attr("text-anchor", "start")
                    .style("font-size", storage.getTextSize() + "px")
                    .attr("transform", "translate(" + (storage.padding.left + storage.legend_icon_radius*3) + "," + i * text_height + ")");

                g.selectAll("circle")
                    .attr("cx", storage.padding.left)
                    .attr("cy", i * text_height - storage.legend_icon_radius )
                    .attr("r", storage.legend_icon_radius)
                    .style("fill", function (d) {
                        return color(d.data.label);
                    });

            });

            legends.enter()
                .append("g")
                .attr("class","legend")
                    .each( function(d,i) {
                        var g = d3.select(this),
                            text_height = storage.getTextSize() * storage.lineHeight;

                        g.append("text")
                            .text(function (d) {
                                return d.data.label;
                            })
                            .attr("text-anchor", "start")
                            .style("font-size", storage.getTextSize() + "px")
                            .attr("transform", "translate(" + (storage.padding.left + storage.legend_icon_radius*3) + "," + i * text_height + ")");

                        g.append("circle")
                            .attr("cx", storage.padding.left)
                            .attr("cy", i * text_height - storage.legend_icon_radius )
                            .attr("r", storage.legend_icon_radius)
                            .style("fill", function (d) {
                                var fill = color(d.data.label);
                                if ( d.data.hasOwnProperty("service") && (d.data.service === true) ) {
                                    fill = storage.serviceFill;
                                }
                                return fill;
                            });

                });

        legends.exit()
            .remove();
    })();

// PERCENT
    ( function() {
        var percent = svg.select(".percents")
                .attr("transform", "translate(" + storage.getWidth()/2 + ","+ ( storage.getRadius() + storage.padding.top ) + ")")
                    .selectAll("text")
                    .data(pie(data), key);

        percent
            .transition()
            .duration(1000)
            .attr("transform", function (d) {
                return "translate(" + arc.centroid(d) + ")";
            })
            .attr("opacity", function (d) {
                var percent = Math.round(1000 * (d.endAngle - d.startAngle) / (Math.PI * 2)) / 10;
                return (percent > 10) ? "1" : "0";
            })
            .text( function (d) {
                var percent = Math.round(1000 * (d.endAngle - d.startAngle) / (Math.PI * 2)) / 10;
                return percent + "%";
            });

        percent
            .enter()
            .append("text")
            .attr("class", "percent")
            .attr("dy", ".35em")
            .attr("text-anchor", "middle")
            .attr("transform", function (d) {
                return "translate(" + arc.centroid(d) + ")";
            })
            .attr("opacity", function (d) {
                var percent = Math.round(1000 * (d.endAngle - d.startAngle) / (Math.PI * 2)) / 10;
                return (percent > 10) ? "1" : "0";
            })
            .text(function (d) {
                var percent = Math.round(1000 * (d.endAngle - d.startAngle) / (Math.PI * 2)) / 10;
                return percent + "%";
            });

        percent.exit()
            .remove();
    })();

    var onHover = function(data, event) {
        var $wrapper = storage.hint_wrapper,
            position = getHintPosition(event),
            html = "";

        html += "<div class=\"line\">" + data.label + "</div>";

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
}
};