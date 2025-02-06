( function($) {

    var PromosPage = ( function($) {

        PromosPage = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];

            // CONST
            that.urls = options["urls"];
            that.templates = options["templates"];
            that.sort_enabled = options["sort_enabled"];

            // DYNAMIC VARS

            // INIT
            that.init();
        };

        PromosPage.prototype.init = function() {
            var that = this;

            var ready_promise = that.$wrapper.data("ready");
            ready_promise.resolve(that);
            that.$wrapper.trigger("ready", that);

            that.initSortable();

            that.initStatusChange();
        };

        PromosPage.prototype.initPromo = function(options) {
            var that = this;

            var $wrapper = options["$wrapper"];

            renderChart($wrapper.find(".js-chart-wrapper"), options.chart_data);

            function renderChart($chart_section, chart_data) {

                var Chart = ( function($) {

                    Chart = function(options) {
                        var that = this;

                        //

                        // DOM
                        that.$wrapper = options["$wrapper"];
                        that.node = that.$wrapper.find(".s-chart")[0];
                        that.d3node = d3.select(that.node);

                        // DATA
                        that.data = getData(options["data"]);

                        // VARS
                        that.margin = {
                            top: 1,
                            right: 1,
                            bottom: 1,
                            left: 1
                        };
                        that.area = getArea(that.node, that.margin);

                        // DYNAMIC VARS
                        that.svg = false;
                        that.x = false;
                        that.y = false;
                        that.xDomain = false;
                        that.yDomain = false;
                        that.svgLine = false;

                        // INIT
                        that.initGraph();
                    };

                    Chart.prototype.initGraph = function() {
                        var that = this,
                            graphArea = that.area;

                        that.initCore();

                        that.svg = that.d3node
                            .append("svg")
                            .attr("width", graphArea.outer_width)
                            .attr("height", graphArea.outer_height);

                        $.each(that.data, function(index, data) {
                            if (data && data.length) {
                                that.renderChart(data, index);
                            }
                        });
                    };

                    Chart.prototype.initCore = function() {
                        var that = this,
                            data = that.data,
                            graphArea = that.area;

                        var x = that.x = d3.scale.linear().range([0, graphArea.inner_width]);
                        var y = that.y = d3.scale.linear().range([graphArea.inner_height, 0]);

                        that.xDomain = getTimeDomain();
                        that.yDomain = getValueDomain();

                        x.domain(that.xDomain);
                        y.domain(that.yDomain);

                        that.svgLine = d3.svg.line()
                            .interpolate("monotone")
                            .x(function(d) { return x(d.date); })
                            .y(function(d) { return y(d.value); });

                        function getValueDomain() {
                            if (!data[0][0]) {
                                return [0, 100];
                            }
                            var min = d3.min(data, function(chart) {
                                return d3.min(chart, function(point) {
                                    return point.value;
                                });
                            });
                            if (min > 0) {
                                min = 0;
                            }
                            var max = d3.max(data, function(chart) {
                                return d3.max(chart, function(point) {
                                    return (point.value);
                                });
                            });

                            return [min, max];
                        }

                        function getTimeDomain() {
                            if (!data[0][0]) {
                                return [new Date(2019, 5, 1), new Date(2019,6,1)];
                            }
                            var min, max,
                                points_length = data[0].length,
                                first_point = data[0][0].date,
                                second_point = data[0][1].date,
                                last_point = data[0][points_length-1].date;

                            min = new Date( first_point.getTime() );
                            max = new Date( last_point.getTime() );

                            return [min, max];
                        }
                    };

                    Chart.prototype.renderChart = function(data, index) {
                        var that = this;

                        var group = that.svg.append("g")
                            .attr("class", "group")
                            .attr("transform", "translate(" + that.margin.left + "," + that.margin.top + ")")
                            .datum(data);

                        var area = group.append("path")
                            .attr("class", "area")
                            .attr("d", that.svgLine(data) )
                    };

                    return Chart;

                    // Получаем размеры для графика
                    function getArea(node, margin) {
                        var width = node.offsetWidth,
                            height = node.offsetHeight;

                        return {
                            outer_width: width,
                            outer_height: height,
                            inner_width: width - margin.left - margin.right,
                            inner_height: height - margin.top - margin.bottom
                        };
                    }

                    function getData(charts) {
                        var result = [];

                        for (var i = 0; i < charts.length; i++) {
                            var chart = charts[i].data,
                                chartData = [];

                            for (var j = 0; j < chart.length ; j++) {
                                var point = chart[j];

                                chartData.push({
                                    date: getDate( point.date ),
                                    value: parseInt( point.value )
                                });
                            }

                            result.push(chartData);
                        }

                        return result;

                        function getDate(date_string) {
                            var date_array = date_string.split("-");

                            var year = date_array[0],
                                mount = Math.floor(date_array[1]) - 1,
                                day = date_array[2];

                            return new Date(year, mount, day);
                        }
                    }

                })(jQuery);

                new Chart({
                    $wrapper: $chart_section,
                    data: chart_data
                });
            }
        };

        PromosPage.prototype.initSortable = function() {
            const that = this;
            const $promos_lists = that.$wrapper.find(".js-active-promos-list");

            $promos_lists.sortable({
                distance: 5,
                opacity: 0.75,
                items: "> .s-promo-wrapper",
                handle: ".js-sort",
                cursor: "move",
                tolerance: "pointer",
                onEnd: function(ui) {

                    if (that.sort_enabled) {
                        var promo_ids = $promos_lists.find(".s-promo-wrapper").map(function() {
                            return $(this).data("id");
                        }).get();

                        $.post(that.urls['sort'], {
                            ids: promo_ids
                        });
                    } else {

                        var $sort_notice_dialog = $(that.templates["sort_notice_dialog"]);
                        $.waDialog({
                            html: $sort_notice_dialog.html(),
                            onOpen: function () {
                                var $dialog_wrapper = $(this);

                                // Close
                                $dialog_wrapper.on('click', '.js-cancel', function () {
                                    $dialog_wrapper.trigger('close');
                                });
                            },
                            onClose: function () {
                                $(this).remove();
                            }
                        });

                        if (ui.oldIndex > 0) {
                            const idx = ui.oldIndex > ui.newIndex ? ui.oldIndex : ui.oldIndex - 1;
                            $promos_lists.children().eq(idx).after(ui.item);
                        } else {
                            $promos_lists.prepend(ui.item);
                        }
                    }

                }
            });
        };

        PromosPage.prototype.initStatusChange = function() {
            var that = this;

            var pause_class = "is-paused",
                stop_class = "is-stopped",
                active_class = "is-active";

            that.$wrapper.on("click", ".js-pause-promo", function(event) {
                event.preventDefault();

                var $button = $(this),
                    $bar = $button.closest(".s-bar-section"),
                    $promo = $button.closest(".s-promo-wrapper");

                pausePromo($promo).then( function() {
                    $button.removeClass("js-pause-promo").addClass("js-play-promo");
                    $button.find(".play").removeClass("play").addClass("ss pause");
                    $bar.removeClass(active_class).addClass(stop_class);
                    $promo.removeClass(active_class).addClass(pause_class);
                });
            });

            that.$wrapper.on("click", ".js-play-promo", function(event) {
                event.preventDefault();

                var $button = $(this),
                    $bar = $button.closest(".s-bar-section"),
                    $promo = $button.closest(".s-promo-wrapper");

                playPromo($promo).then( function() {
                    $button.removeClass("js-play-promo").addClass("js-pause-promo");
                    $button.find(".ss.pause").removeClass("ss pause").addClass("play");
                    $bar.removeClass(stop_class).addClass(active_class);
                    $promo.removeClass(pause_class).addClass(active_class);
                });
            });

            function pausePromo($promo) {
                var template = that.templates["pause"],
                    promo_name = $.trim($promo.find(".s-promo-header .s-title").text()),
                    promo_id = $promo.data("id");

                var $template = $(template.replace("%promo_name%", promo_name));

                return showDialog($template, {
                    promo_id: promo_id,
                    enabled: 0
                });
            }

            function playPromo($promo) {
                var template = that.templates["play"],
                    promo_name = $.trim($promo.find(".s-promo-header .s-title").text()),
                    promo_id = $promo.data("id");

                var $template = $(template.replace("%promo_name%", promo_name));

                return showDialog($template, {
                    promo_id: promo_id,
                    enabled: 1
                });
            }

            function showDialog($template, data) {
                var is_locked = false;

                var deferred = $.Deferred();

                $template.waDialog({
                    onLoad: function() {
                        var $dialog_wrapper = $(this);

                        // Submit confirm
                        $dialog_wrapper.on('click', '.js-submit', function(event) {
                            event.preventDefault();

                            var $button = $(this);

                            if (!is_locked) {
                                $button.attr("disabled", true);
                                is_locked = true;

                                $.post(that.urls["status"], data)
                                    .always( function() {
                                        $button.attr("disabled", false);
                                        is_locked = false;
                                    }).done( function() {
                                        deferred.resolve();
                                        $dialog_wrapper.trigger('close');
                                    }).fail( function () {
                                        deferred.reject();
                                    });
                            }
                        });

                        // Close
                        $dialog_wrapper.on('click', '.js-cancel', function (event) {
                            event.preventDefault();
                            deferred.reject();
                            $dialog_wrapper.trigger('close');
                        });
                    }
                });

                return deferred.promise();
            }
        };

        return PromosPage;

    })($);

    $.shop.marketing.init.promosPage = function(options) {
        return new PromosPage(options);
    };

})(jQuery);
