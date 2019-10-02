( function($) {

    var TabToggle = ( function($) {

        TabToggle = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];

            // CONST
            that.toggle_selector = ( options["toggle_class"] || ".js-toggle[data-id]" );
            that.content_selector = ( options["content_class"] || ".js-content[data-id]" );
            that.active_toggle_class = ( options["active_toggle_class"] || "is-active" );
            that.onChange = ( typeof options["onChange"] === "function" ? options["onChange"] : function() {});

            // DYNAMIC VARS

            // INIT
            that.init();
        };

        TabToggle.prototype.init = function() {
            var that = this;

            that.$wrapper.on("click", that.toggle_selector, function(event) {
                event.preventDefault();

                var $toggle = $(this),
                    toggle_id = $toggle.data("id");

                if (!toggle_id || $toggle.hasClass(that.active_toggle_class)) {
                    return false;
                }

                renderToggles($toggle);
                var $content = renderContent(toggle_id);
                that.onChange($toggle, $content);
            });

            function renderToggles($toggle) {
                that.$wrapper.find(that.toggle_selector).each( function() {
                    var $_toggle = $(this);
                    if ($toggle[0] === $_toggle[0]) {
                        $_toggle.addClass(that.active_toggle_class);
                    } else {
                        $_toggle.removeClass(that.active_toggle_class);
                    }
                });
            }

            function renderContent(toggle_id) {
                var result = null;

                that.$wrapper.find(that.content_selector).each( function() {
                    var $_content = $(this),
                        content_id = $_content.data("id");

                    if (toggle_id === content_id) {
                        result = $_content.show();
                    } else {
                        $_content.hide();
                    }
                });

                return result;
            }
        };

        return TabToggle;

    })(jQuery);

    var Chart = ( function($) {

        Chart = function(options) {
            var that = this;

            //

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$chart = options["$chart"];
            that.node = that.$chart[0];
            that.d3node = d3.select(that.node);
            if (!that.node) {
                return;
            }

            // DATA
            that.charts = options["data"];
            that.scope = options["scope"];
            that.data = getData(that.charts);

            // VARS
            that.margin = {
                top: 6,
                right: 6,
                bottom: 28,
                left: 70
            };
            that.area = getArea(that.node, that.margin);

            // DYNAMIC VARS
            that.svg = false;
            that.x = false;
            that.y = false;
            that.xDomain = false;
            that.yDomain = false;
            that.svgLine = false;
            that.svgArea = null;

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

            that.renderBackground();

            that.renderAxis();

            $.each(that.data, function(index, data) {
                that.renderChart(data, index);
            });
        };

        Chart.prototype.initCore = function() {
            var that = this,
                data = that.data,
                graphArea = that.area;

            var x = that.x = d3.time.scale().range([0, graphArea.inner_width]);
            var y = that.y = d3.scale.linear().range([graphArea.inner_height, 0]);

            that.xDomain = getTimeDomain();
            that.yDomain = getValueDomain();

            x.domain(that.xDomain);
            y.domain(that.yDomain);

            that.svgLine = d3.svg.line()
                .interpolate("linear")
                .x(function(d) { return x(d.date); })
                .y(function(d) { return y(d.value); });

            that.svgArea = d3.svg.area()
                .interpolate("linear")
                .x( function(d) {
                    return x(d.date);
                })
                .y( function(d) {
                    return y(d.value + d.y0);
                })
                .y0( function(d) { return y(d.y0); });

            function getValueDomain() {
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

                if (min === max) { max += 1; }

                return [min, max];
            }

            function getTimeDomain() {
                var min, max,
                    points_length = data[0].length,
                    first_point = data[0][0].date,
                    second_point = (data[0][1]||data[0][0]).date,
                    last_point = data[0][points_length-1].date;

                min = new Date( first_point.getTime() );
                max = new Date( last_point.getTime() );

                return [min, max];
            }
        };

        Chart.prototype.renderChart = function(data, index) {
            var that = this;

            var chart = that.charts[index];

            var group = that.svg.append("g")
                .attr("class", "s-chart-group " + ( chart.class ? chart.class : "" ))
                .attr("transform", "translate(" + that.margin.left + "," + that.margin.top + ")")
                .datum(data);

            var area = group.append("path")
                .attr("class", "s-chart-area")
                .attr("d", that.svgArea(data) );

            var line = group.append("path")
                .attr("class", "s-chart-line")
                .attr("d", that.svgLine(data) );

            if (chart.points) {
                var divider_group = group.append("g")
                    .attr("class", "s-divider-group");

                var divider = divider_group.append("line")
                    .attr("class", "s-divider")
                    .attr("x1", that.x(that.scope.current_date))
                    .attr("x2", that.x(that.scope.current_date))
                    .attr("y1", that.y(that.yDomain[0]))
                    .attr("y2", that.y(that.yDomain[1]));

                var points_group = group.append("g")
                    .attr("class", "s-points-group");

                $.each(data, function(i, point_data) {
                    var point = points_group.append("line")
                        .attr("class", "s-chart-point")
                        .attr("x1", that.x(point_data.date))
                        .attr("y1", that.y(point_data.value))
                        .attr("x2", that.x(point_data.date))
                        .attr("y2", that.y(point_data.value))
                        .on("mouseover", function() {
                            that.scope.$wrapper.trigger("point_mouseover", [point.node(), point_data, chart]);
                        })
                        .on("mouseout", function() {
                            that.scope.$wrapper.trigger("point_mouseout", [point.node(), point_data, chart]);
                        });
                });
            }
        };

        Chart.prototype.renderBackground = function() {
            var that= this,
                width = that.area.inner_width,
                height = that.area.inner_height,
                y_ticks = 5,
                i;

            var background = that.svg.append("g")
                .attr("class", "background")
                .attr("transform", "translate(" + that.margin.left + "," + that.margin.top + ")");

            background.append("rect")
                .attr("width", width)
                .attr("height", height);

            for (i = 0; i <= y_ticks; i++) {
                var y_val = 1 + (height - 2) / y_ticks * i;
                background.append("line")
                    .attr("x1", 1)
                    .attr("x2", width)
                    .attr("y1", y_val)
                    .attr("y2", y_val)
                ;
            }
        };

        Chart.prototype.renderAxis = function() {
            var that = this,
                x = that.x,
                y = that.y,
                svg = that.svg;

            var xAxis = d3.svg.axis()
                .scale(x)
                .orient("bottom")
                .ticks(10);

            var yAxis = d3.svg.axis()
                .scale(y)
                .innerTickSize(2)
                .orient("right")
                .tickValues( getValueTicks(6, that.yDomain) )
                .tickFormat(function(d) { return d + ""; });

            // Render Осей
            var axis = svg.append("g")
                .attr("class","axis");

            axis.append("g")
                .attr("transform","translate(2," + that.margin.top + ")")
                .attr("class","y")
                .call(yAxis);

            axis.append("g")
                .attr("class","x")
                .attr("transform","translate(" + that.margin.left + "," + (that.area.outer_height - that.margin.bottom ) + ")")
                .call(xAxis);

            function getValueTicks(length, domain) {
                var min = domain[0],
                    max = ( domain[1] || 1 ),
                    delta = (max - min) + 1,
                    period = delta/(length - 1),
                    result = [];

                for (var i = 0; i < length; i++) {
                    var label = (delta > 10) ? Math.round( i * period ) : (parseInt(  i * period * 10 ) / 10 );
                    result.push(label);
                }

                return result.reverse();
            }
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
                var chart = charts[i].data || [],
                    chartData = [];

                for (var j = 0; j < chart.length ; j++) {
                    var point = chart[j];

                    chartData.push({
                        date: getDate( point.date ),
                        value: parseInt( point.value ),
                        y0: 0
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

    })($);

    var Promo = ( function($) {

        Promo = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$form = that.$wrapper.find("form:first");
            that.$submit_button = that.$wrapper.find(".js-submit-button");

            that.$window = $(window);

            // CONST
            that.current_date = getDate(options["current_date_string"]);
            that.active_tab = options["active_tab"];
            that.promo_id = options["promo_id"];
            that.promo_options = options["promo_options"];
            that.rule_types = options["rule_types"];

            that.templates = options["templates"];
            that.locales = options["locales"];
            that.urls = options["urls"];

            // DYNAMIC VARS
            that.chart_data = options["chart_data"];

            // INIT
            that.init();
        };

        Promo.prototype.init = function() {
            var that = this;

            that.initClone();
            that.initDelete();
            that.initChartSection();
            that.initOptionsSection();
            that.initRulesSection();
            that.initTabs();
            that.initSubmit();
            that.initFixedSubmitWrapper();
            that.initEditName();

            that.$wrapper.on("change", function() {
                that.$submit_button.removeClass("green").addClass("yellow");
            });

            var orders = that.initOrders();
            if (that.active_tab === "orders") {
                orders.init();
            }

            var costs = that.initCosts();
            if (that.active_tab === "costs") {
                costs.init();
            }

            var ready_promise = that.$wrapper.data("ready");
            ready_promise.resolve(that);
            that.$wrapper.trigger("ready", that);
        };

        Promo.prototype.initClone = function() {
            var that = this,
                $clone_confirm_template = $(that.templates["clone_confirm"]).clone(),
                is_locked = false;

            if (!that.promo_id) {
                return false;
            }

            that.$wrapper.on('click', '.js-promo-clone', function () {
                var $icon = $(this).find('.icon16'),
                    href = that.urls["clone_promo"],
                    data = {promo_id: that.promo_id};

                if (is_locked) {
                    return false;
                }

                is_locked = true;

                $clone_confirm_template.waDialog({
                    onLoad: function () {
                        var $dialog_wrapper = $(this);

                        // Submit confirm
                        $dialog_wrapper.on('click', '.js-submit', function () {
                            $icon.removeClass('split').removeClass('yes').removeClass('no').addClass('loading');

                            $.post(href, data, function (response) {
                                if (response.status === "ok") {
                                    $icon.removeClass('loading').addClass('yes');
                                    var href = that.urls["edit_promo"].replace("%id%", response.data.id);
                                    $.shop.marketing.content.load(href);
                                    $.shop.marketing.sidebar.reload();
                                } else {
                                    $icon.removeClass('loading').addClass('no');
                                }

                                is_locked = false;
                                $dialog_wrapper.trigger('close');
                            });
                        });

                        // Close
                        $dialog_wrapper.on('click', '.js-cancel', function () {
                            is_locked = false;
                            $dialog_wrapper.trigger('close');
                        });
                    }
                });
            });
        };

        Promo.prototype.initDelete = function() {
            var that = this,
                $delete_confirm_template = $(that.templates["delete_confirm"]).clone(),
                is_locked = false;

            if (!that.promo_id) {
                return false;
            }

            that.$wrapper.on('click', '.js-promo-delete', function () {
                var $icon = $(this).find('.icon16'),
                    href = that.urls["delete_promo"],
                    data = {promo_id: that.promo_id};

                if (is_locked) {
                    return false;
                }

                is_locked = true;

                $delete_confirm_template.waDialog({
                    onLoad: function () {
                        var $dialog_wrapper = $(this);

                        // Submit confirm
                        $dialog_wrapper.on('click', '.js-submit', function () {
                            $icon.removeClass('delete').removeClass('yes').removeClass('no').addClass('loading');

                            $.post(href, data, function (response) {
                                if (response.status === "ok") {
                                    $icon.removeClass('loading').addClass('yes');
                                    var href = that.urls["marketing_url"];
                                    $.shop.marketing.content.load(href);
                                    $.shop.marketing.sidebar.reload();
                                } else {
                                    $icon.removeClass('loading').addClass('no');
                                }

                                is_locked = false;
                                $dialog_wrapper.trigger('close');
                            });
                        });

                        // Close
                        $dialog_wrapper.on('click', '.js-cancel', function () {
                            is_locked = false;
                            $dialog_wrapper.trigger('close');
                        });
                    }
                });
            });
        };

        Promo.prototype.initOrders = function() {
            var that = this;

            // DOM
            var $window = $(window),
                $orders_section = that.$wrapper.find(".js-orders-section"),
                $table = null;

            // DYNAMIC VARS
            var pages = 1;
            var page = 0;
            var is_loaded = false;
            var is_locked = false;

            that.$wrapper.on("init_promo_orders", function() {
                init();
            });

            $window.on("scroll", scrollWatcher);
            function scrollWatcher() {
                var is_exist = $.contains(document, $orders_section[0]),
                    is_done = (page === pages);

                if (is_loaded) {
                    if (is_exist && !is_done) {
                        onScroll();
                    } else {
                        $window.off("scroll", scrollWatcher);
                    }
                }
            }

            return {
                init: init
            };

            function onScroll() {
                var scroll_top = $window.scrollTop(),
                    section_bottom = $orders_section.offset().top + $orders_section.outerHeight(),
                    is_visible = $orders_section.is(":visible");

                if (is_visible && scroll_top >= section_bottom - $window.height()) {
                    if (is_loaded && !is_locked) {
                        is_locked = true;

                        load()
                            .then( function(html) {
                                $table.append(html);
                            })
                            .always( function() {
                                is_locked = false;
                            });
                    }
                }
            }

            function init() {
                if (!is_loaded) {
                    is_locked = true;

                    load()
                        .always( function() {
                            is_locked = false;
                        })
                        .then( function(html) {
                            $orders_section.append(html);

                            var $_table = $orders_section.find("table");
                            if ($_table.length) {
                                $table = $_table;

                                pages = $table.data("pages");
                            }

                            is_loaded = true;
                        });
                }
            }

            function load() {
                page += 1;

                var href = that.urls["orders"],
                    data = {
                        promo_id: that.promo_id,
                        page: page
                    };

                var $loading = $('<i class="icon16 loading" />');

                $orders_section.append($loading);

                return $.post(href, data)
                        .always( function() {
                            $loading.remove();
                        });
            }
        };

        Promo.prototype.initCosts = function() {
            var that = this;

            // DOM
            var $costs_section = that.$wrapper.find(".js-costs-section");

            // DYNAMIC VARS
            var is_loaded = false,
                is_locked = false;

            that.$wrapper.on("init_promo_costs", function() {
                init();
            });

            return {
                init: init
            };

            function init() {
                if (!is_loaded) {
                    is_locked = true;

                    load()
                        .always( function() {
                            is_locked = false;
                        })
                        .then( function(html) {
                            $costs_section.append(html);
                            is_loaded = true;
                        });
                }
            }

            function load() {
                var href = that.urls["costs"],
                    data = {
                        promo_id: that.promo_id
                    };

                var $loading = $('<i class="icon16 loading" />');

                $costs_section.append($loading);

                return $.post(href, data)
                        .always( function() {
                            $loading.remove();
                        });
            }
        };

        Promo.prototype.initChartSection = function() {
            var that = this;

            var $chart_section = that.$wrapper.find(".s-chart-section"),
                $chart = $chart_section.find(".js-chart");

            new Chart({
                $wrapper: $chart_section,
                $chart: $chart,
                scope: that,
                data: that.chart_data
            });

            initHints();

            function initHints() {
                var $wrapper = $chart_section.find(".js-hint-wrapper");

                that.$wrapper.on("point_mouseover", function(event, point, data, chart) {
                    var value_visibility = true;
                    if (that.current_date && that.current_date < data.date && data.value <= 0) {
                        value_visibility = false;
                    }

                    var html = that.templates["hint"]
                        .replace("%date%", dateToString(data.date))
                        .replace("%value%", $.shop.marketing.formatPrice(data.value) )
                        .replace("%value_style%", value_visibility ? "" : "display: none;" );

                    toggle(html);

                    var point_offset = $(point).offset(),
                        chart_section_offset = $chart_section.offset(),
                        wrapper_w = $wrapper.outerWidth(),
                        wrapper_h = $wrapper.outerHeight(),
                        indent = 10;

                    $wrapper.css({
                        top: point_offset.top - chart_section_offset.top - wrapper_h - indent,
                        left: point_offset.left - chart_section_offset.left - (wrapper_w/2)
                    });
                });

                that.$wrapper.on("point_mouseout", function(event) {
                    toggle(null);
                });

                function toggle(html) {
                    var active_class = "is-shown";

                    if (html) {
                        $wrapper.html(html).addClass(active_class);
                    } else {
                        $wrapper.removeClass(active_class).html("");
                    }
                }

                function dateToString(date) {
                    var day = date.getDate();
                    day = ( day < 10 ) ? "0" + day : day;

                    var month = parseInt( date.getMonth() ) + 1;
                    month = ( month < 10 ) ? "0" + month : month;

                    var year = date.getFullYear();

                    return [day, month, year].join(".");
                }
            }
        };

        Promo.prototype.initOptionsSection = function() {
            var that = this;

            // DOM
            var $root_section = that.$wrapper.find(".s-options-section");

            // EVENTS

            that.$wrapper.find(".s-datetime-section").each( function() {
                var $datetime_section = $(this),
                    $timer = $datetime_section.find(".js-date-timer");

                if ($timer.length) {
                    var $date_field = $datetime_section.find(".s-datepicker-wrapper input:hidden"),
                        $time_field = $datetime_section.find(".s-time-wrapper input");

                    $datetime_section.on("change", function() {
                        var date = $date_field.val(),
                            time = $time_field.val();

                        if (!date) { return; }
                        if (!time) { time = "00:00"; }

                        var date_time = date + "-" + time.replace(":", "-");

                        var timer = $timer.data("timer");
                        timer.setDate(date_time);
                    });
                }
            });

            // INIT

            initBannerSection();
            initColorPickers();
            initDatePickers();
            initDateTimers();
            initStorefrontsSection();
            initStatusSection();
            initCountdownSection();

            // FUNCTIONS

            function initStorefrontsSection() {
                var $storefronts_section = $root_section.find(".s-storefronts-section"),
                    $storefronts_mass_toggle = $storefronts_section.find(".js-storefront-mass-toggle"),
                    $counter = $storefronts_section.find(".js-counter");

                var $storefronts = $storefronts_section.find(".js-storefront-toggle"),
                    storefronts_count = $storefronts.length;

                $storefronts_section.on("click",".js-show-all", function(event) {
                    event.preventDefault();
                    $storefronts_section.toggleClass("is-extended");
                });

                $storefronts_section.on("change", ".js-storefront-toggle", function(event) {
                    var count = $storefronts_section.find(".js-storefront-toggle:checked").length;
                    if (count < storefronts_count) {
                        $storefronts_mass_toggle.attr("checked", false);
                    } else {
                        $storefronts_mass_toggle.attr("checked", true);
                    }
                    $counter.text(count);
                });

                $storefronts_mass_toggle.on("change", function(event) {
                    var $toggle = $(this),
                        is_checked = $toggle.is(":checked");

                    $storefronts.attr("checked", is_checked);
                    $storefronts.first().trigger("change");
                });
            }

            function initStatusSection() {
                var $section = $root_section.find(".js-status-section"),
                    $checkbox = $section.find(".s-checkbox");

                $section.on("click", ".js-stop-status", function(event) {
                    event.preventDefault();
                    toggle(false);
                });

                $section.on("click", ".js-restore-status", function(event) {
                    event.preventDefault();
                    toggle(true);
                });

                function toggle(enabled) {
                    var disabled_class = "is-disabled";

                    if (enabled) {
                        $section.removeClass(disabled_class);
                        $checkbox.attr("checked", true).trigger("change");
                    } else {
                        $section.addClass(disabled_class);
                        $checkbox.attr("checked", false).trigger("change");
                    }
                }
            }

            function initDatePickers() {
                var $section = $root_section.find(".s-datepicker-wrapper");

                $section.find(".js-datepicker").each( function() {
                    var $input = $(this),
                        alt_field_selector = $input.data("alt");

                    if (alt_field_selector) {
                        var $alt_field = $section.find(alt_field_selector);

                        var options = {
                            changeMonth: true,
                            changeYear: true
                        };

                        if ($alt_field.length) {
                            options = $.extend(options, {
                                altField: $alt_field,
                                altFormat: "yy-mm-dd"
                            });
                        }

                        $input.datepicker(options);
                    }

                });
            }

            function initCountdownSection() {
                var $countdown_section = $root_section.find(".s-countdown-section"),
                    $timer = $countdown_section.find(".js-date-timer"),
                    $inputs = $countdown_section.find(".s-hidden input"),
                    $enable_field = $countdown_section.find(".js-countdown-toggle");

                var $countdown_datepicker = $countdown_section.find(".js-datepicker"),
                    $countdown_hours = $countdown_section.find(".js-hours"),
                    $countdown_minutes = $countdown_section.find(".js-minutes"),
                    $start_datepicker_section = that.$wrapper.find('input[name="promo[start_date]"]').closest(".s-datetime-section"),
                    $start_datepicker = $start_datepicker_section.find(".js-datepicker"),
                    $start_time = that.$wrapper.find('input[name="promo[start_time]"]');

                var active_class = "is-extended";

                $enable_field.on("change", function(event) {
                    var is_active = $enable_field.is(":checked");
                    if (is_active) {
                        $inputs.removeAttr("disabled");
                        $countdown_section.addClass(active_class);
                        setDatepicker();
                    } else {
                        $inputs.attr("disabled", true);
                        $countdown_section.removeClass(active_class);
                    }
                });

                that.$wrapper.on("promo_countdown_error", function() {
                    $countdown_section.addClass(active_class);
                });

                if ($timer.length) {
                    var $date_field = $countdown_section.find("[name='promo[countdown_datetime][date]']"),
                        $hour_field = $countdown_section.find("[name='promo[countdown_datetime][hour]']"),
                        $minute_field = $countdown_section.find("[name='promo[countdown_datetime][minute]']");

                    $countdown_section.on("change", function() {
                        var date = $date_field.val(),
                            hour = $hour_field.val(),
                            minute = $minute_field.val();

                        if (!date) { return; }
                        if (!hour) { hour = "00"; }
                        if (!minute) { minute = "00"; }

                        var date_time = date + "-" + hour + "-" + minute;

                        var timer = $timer.data("timer");
                        timer.setDate(date_time);
                    });
                }

                function setDatepicker() {
                    var is_countdown_set = $countdown_datepicker.val();
                    if (!is_countdown_set) {
                        var start_date = $start_datepicker.val();
                        if (start_date) {
                            $countdown_datepicker.datepicker("setDate", start_date).trigger("change");
                        }
                    }

                    var start_time = $start_time.val(),
                        hours = "",
                        minutes = "";

                    if (start_time) {
                        var time = start_time.split(":");
                        hours = time[0];
                        minutes = time[1];
                    }

                    var is_hours_set = $countdown_hours.val();
                    if (!is_hours_set && hours) {
                        $countdown_hours.val(hours);
                    }

                    var is_minutes_set = $countdown_minutes.val();
                    if (!is_minutes_set && minutes) {
                        $countdown_minutes.val(minutes);
                    }
                }
            }

            function initColorPickers() {
                $root_section.find('.js-colorpicker').each(function () {
                    var $colorpicker_wrapper = $(this).hide(),
                        $icon = $colorpicker_wrapper.closest('.value').find('i.icon16.color'),
                        $input = $colorpicker_wrapper.closest('.value').find(':input');

                    var farbtastic = $.farbtastic($colorpicker_wrapper, setColor);
                    farbtastic.widgetCoords = function (event) {
                        var offset = $(farbtastic.wheel).offset();
                        return { x: (event.pageX - offset.left) - farbtastic.width / 2, y: (event.pageY - offset.top) - farbtastic.width / 2 };
                    };

                    $icon.css('cursor', 'pointer').click(function() {
                        $colorpicker_wrapper.slideToggle();
                    });

                    setColor($input.val() || '#ffffff');

                    $input.on('change keyup', function() {
                        $icon.css('background', $input.val());
                        farbtastic.setColor($input.val());
                    });

                    function setColor(color) {
                        $icon.css('background', color);
                        farbtastic.setColor(color);
                        $input.val(color);
                    }
                });
            }

            function initDateTimers() {
                var DateTimer = ( function($) {

                    DateTimer = function(options) {
                        var that = this;

                        // DOM
                        that.$wrapper = options["$wrapper"];

                        // CONST
                        that.locales = options["locales"];

                        // DYNAMIC VARS
                        that.date = that.$wrapper.data("date");

                        // INIT
                        that.init();
                    };

                    DateTimer.prototype.init = function() {
                        var that = this;

                        var timeout = 0;

                        run();

                        function runner() {
                            timeout = setTimeout(run, 1000 * 60);
                        }

                        function run() {
                            var is_exits = $.contains(document, that.$wrapper[0]);
                            if (is_exits) {
                                var result = that.setTime();
                                if (result !== false) { runner(); }
                            }
                        }

                        that.$wrapper.data("timer", that);
                    };

                    DateTimer.prototype.setTime = function() {
                        var that = this;

                        var date_string = that.date;
                        if (date_string) {
                            var target_date = getDate(date_string);
                            var current_date = new Date();

                            var delta = target_date - current_date;

                            var time = formatTime( Math.abs(delta)),
                                time_array = [];

                            if (time.days) {
                                time_array.push(time.days + "&nbsp;" + that.locales["days"]);
                            }

                            if (time.hours) {
                                time_array.push(time.hours + "&nbsp;" + that.locales["hours"]);
                            }

                            if (time.minutes) {
                                time_array.push(time.minutes + "&nbsp;" + that.locales["minutes"]);
                            }

                            // if (time.seconds) {
                            //     time_array.push(time.seconds + "&nbsp;" + that.locales["seconds"]);
                            // }

                            var pattern = that.$wrapper.data("pattern");
                            if (!pattern) {
                                pattern = that.locales[ (delta > 0 ? "before" : "after" )];
                            }

                            var result = pattern.replace("%s", time_array.join(" "));

                            that.render(result);
                        }

                        function getDate(date_string) {
                            var date_array = date_string.replace(":", "-").replace(" ", "-").split("-");

                            var year = date_array[0],
                                mount = Math.floor(date_array[1]) - 1,
                                day = date_array[2],
                                hour = date_array[3],
                                minute = date_array[4];

                            return new Date(year, mount, day, hour, minute);
                        }

                        function formatTime(time) {
                            var second = 1000,
                                minute = second * 60,
                                hour = minute * 60,
                                day = hour * 24,
                                tail;

                            var days = Math.floor(time/day);
                            tail = ( time - days * day );
                            var hours = Math.floor(tail/hour);
                            tail = ( tail - hours * hour );
                            var minutes = Math.floor(tail/minute);
                            tail = ( tail - minutes * minute );
                            var seconds = Math.floor(tail/second);

                            return {
                                days: days,
                                hours: hours,
                                minutes: minutes,
                                seconds: seconds
                            };
                        }
                    };

                    DateTimer.prototype.setDate = function(date_string) {
                        var that = this;

                        that.date = date_string;

                        that.setTime();
                    };

                    DateTimer.prototype.render = function(time_string) {
                        var that = this;

                        that.$wrapper.html(time_string);
                    };

                    return DateTimer;

                })($);

                $root_section.find(".js-date-timer").each( function() {
                   var $timer = $(this);

                   var date_timer = new DateTimer({
                       $wrapper: $timer,
                       locales: that.locales
                   });
                });
            }

            function initBannerSection() {
                var $_banner_section = $root_section.find(".s-banner-section");

                $_banner_section.on("click", ".js-section-toggle", function(event) {
                    event.preventDefault();
                    $_banner_section.toggleClass("is-extended");
                });
            }
        };

        Promo.prototype.initTabs = function() {
            var that = this,
                $section = that.$wrapper.find(".s-tabs-section");

            new TabToggle({
                $wrapper: $section,
                toggle_selector: ".js-toggle[data-id]",
                content_selector: ".js-content[data-id]",
                active_toggle_class: "selected",
                onChange: function($active_toggle, $active_content) {
                    var id = $active_toggle.data("id"),
                        $link = $active_toggle.find("a");

                    if ($link.length) {
                        var href = $link[0].href;
                        history.replaceState({
                            reload: true,
                            content_uri: href
                        }, null, href);
                    }

                    if (id === "orders") {
                        that.$wrapper.trigger("init_promo_orders");
                    } else if (id === "costs") {
                        that.$wrapper.trigger("init_promo_costs");
                    }
                }
            });
        };

        Promo.prototype.initRulesSection = function() {
            var that = this;

            // DOM
            var $section = that.$wrapper.find(".js-rules-section");

            // CONST
            var edit_class = "is-edit",
                extended_class = "is-extended";

            // VARS
            var create_iterator = 0;

            ruleCountWatch();

            // CREATE
            $section.on("click", ".js-create-rule", function(event, options) {
                event.preventDefault();

                var $link = $(this),
                    $rule = $link.closest(".s-rule-section"),
                    $clone_rule = $(that.templates["new_rule_template"]);

                var rule_type = $link.data("rule-type"),
                    type_data = that.rule_types[rule_type];

                // set title
                var title = ( type_data.css_class ? '<i class="icon16 ' + type_data.css_class + '"></i>' : "") + type_data.name;
                $clone_rule.find(".s-section-header .js-title").html(title);

                // set data and render
                $clone_rule
                    .attr("data-ident", "" + create_iterator)
                    .attr("data-type", rule_type)
                    .toggleClass(edit_class)
                    .insertAfter($rule);

                $clone_rule.find(".js-edit-rule").trigger("click", [options]);

                create_iterator += 1;

                ruleCountWatch();
            });

            // EDIT
            $section.on("click", ".js-edit-rule", function(event, options) {
                event.preventDefault();

                var $rule = $(this).closest(".s-rule-section"),
                    is_locked = $rule.data("is-locked");

                $rule.addClass(edit_class);

                if (!is_locked) {
                    $rule.data("is-locked", true);
                    var type = $rule.data("type");
                    load($rule, type, options).always( function() {
                        $rule.data("is-locked", false);
                    });
                }
            });

            // REMOVE
            $section.on("click", ".js-delete-rule", function(event) {
                event.preventDefault();

                var $rule = $(this).closest(".s-rule-section"),
                    id = $rule.data("id"),
                    is_locked = $rule.data("is-locked");

                $rule.addClass(edit_class);

                if (!is_locked) {
                    if (confirm(that.locales["rule_delete_confirm"])) {
                        $rule.data("is-locked", true).remove();
                        var $input = $('<input type="hidden" name="delete_rules[]">').val(id);
                        $section.append($input);
                        ruleCountWatch();
                    }
                }
            });

            // CANCEL
            $section.on("click", ".js-cancel-edit-rule", function(event) {
                event.preventDefault();

                var $rule = $(this).closest(".s-rule-section"),
                    $body = $rule.find("> .s-section-body");

                $rule
                    .removeClass(extended_class)
                    .removeClass(edit_class);

                var rule_id = $rule.data("id");
                if (rule_id) {
                    $body.html("");
                } else {
                    $rule.remove();
                }

                ruleCountWatch();

                that.$window.trigger('resize');
            });

            if (that.promo_options && that.promo_options["action"] === "associate") {
                var options = [];
                if (that.promo_options.products_hash) {
                    options.push({
                        products_hash: that.promo_options.products_hash
                    });
                }

                var $exist_edit_link = $section.find(".s-rule-section[data-type='custom_price'] .js-edit-rule");
                if ($exist_edit_link.length) {
                    $exist_edit_link.trigger("click", options);

                } else {
                    var $create_rule = $section.find(".js-create-rule[data-rule-type='custom_price']");
                    $create_rule.trigger("click", options);
                }

                setTimeout( function () {
                    $(window).scrollTop( $section.offset().top );
                }, 20);
            }

            function load($rule, rule_type, options) {
                var $body = $rule.find("> .s-section-body"),
                    $loading = $('<i class="icon16 loading"></i>');

                var rule_id = $rule.data("id"),
                    rule_ident = $rule.data("ident");

                rule_id = (typeof rule_id !== "undefined" ? "" + rule_id : null);
                rule_ident = (typeof rule_ident !== "undefined" ? "" + rule_ident : null);

                var href = that.urls["edit_rule"],
                    data = {
                        options: (options ? options : {})
                    };

                if (rule_id) {
                    data["rule_id"] = rule_id;
                }

                if (rule_type) {
                    data["rule_type"] = rule_type;
                }

                if (rule_ident) {
                    data["options"]["ident"] = rule_ident;
                }

                $rule.addClass(extended_class);
                $body.html("").append($loading);

                that.$window.trigger('resize');

                return $.post(href, data)
                    .always( function() {
                        $loading.remove();
                    })
                    .done( function(html) {
                        $body.html(html);
                        that.$window.trigger('resize');

                        $("html, body").animate({
                            scrollTop: $body.offset().top
                        }, 400);
                    });
            }

            function ruleCountWatch() {
                var $list = $section.find(".js-add-rules-list"),
                    $links = $list.find(".js-create-rule");

                var hidden_class = "is-hidden";
                var empty_class = "is-empty";

                $links.each( function() {
                    var $link = $(this),
                        rule_type = $link.data("rule-type"),
                        max_count = $link.data("max-count");

                    var $_rules = $section.find(".s-rule-section[data-type='" + rule_type + "']");
                    if (max_count && $_rules.length >= max_count) {
                        $link.hide().addClass(hidden_class);
                    } else {
                        $link.removeClass(hidden_class).show();
                    }
                });

                var $visible_links = $links.filter(":not(." + hidden_class + ")");
                if ($visible_links.length) {
                    $list.removeClass(empty_class);
                } else {
                    $list.addClass(empty_class);
                }
            }
        };

        Promo.prototype.initSubmit = function() {
            var that = this,
                $form = that.$form,
                is_locked = false;

            that.$submit_button.on("click", function() {
                that.$wrapper.addClass("with-errors");
            });

            $form.on("submit", function(event) {
                event.preventDefault();

                var form_data = getData();

                if (form_data.errors.length) {
                    renderErrors(form_data.errors);

                } else if (!is_locked) {
                    is_locked = true;

                    var $promise = $.ajax({
                        url: that.urls["submit"],
                        data: form_data.data,
                        type: "post",
                        iframe: true,
                        dataType: 'json',
                        cache: false,
                        contentType: false,
                        processData: false
                    });

                    var $message = $(that.templates["saving"]);
                    that.$submit_button.attr("disabled", true);
                    $message.insertAfter(that.$submit_button);

                    $promise
                        .always( function() {
                            is_locked = false;
                        })
                        .done( function(response) {
                            if (response.status === "ok") {
                                var href = that.urls["edit_promo"].replace("%id%", response.data.id);
                                $.shop.marketing.content.load(href).done( function() {
                                    var $new_button = $("#js-promo-page .js-submit-button");
                                    if ($new_button.length) {
                                        var $message = $(that.templates["saved"]);
                                        $new_button.after($message);
                                        setTimeout( function() {
                                            $message.remove();
                                        }, 2000);
                                    }
                                });

                                $.shop.marketing.sidebar.reload();
                            } else if (response.errors) {
                                renderErrors(response.errors);
                                that.$submit_button.attr("disabled", false);
                                $message.remove();
                            }
                        })
                        .fail( function(state, errors) {
                            try {
                                renderErrors(errors);
                            } catch(error) {

                                alert( that.locales["server_error"] );

                                renderErrors([{
                                    id: "server_error",
                                    text: that.locales["server_error"]
                                }]);

                                $(window).scrollTop( that.$wrapper.find(".js-page-errors-wrapper").offset().top );
                            }

                            that.$submit_button.attr("disabled", false);
                            $message.remove();
                        });
                }
            });

            function getData() {
                var result = {
                        data: new FormData(),
                        errors: []
                    },
                    data = $form.serializeArray();

                $.each(data, function(index, item) {
                    result.data.append(item.name, item.value);
                });

                var $file_controls = $form.find('input[type="file"]');
                $file_controls.each( function(i, input) {
                    var $input = $(this);

                    if (input["files"].length) {
                        $.each(input["files"], function(i, file) {
                            result.data.append($input.attr('name'), file);
                        });
                    }
                });

                return result;
            }

            function renderErrors(errors) {
                var result = [];

                $.each(errors, function(i, error) {
                    var $field = [],
                        $error_wrapper = null;

                    if (!error.text) {
                        alert("error");
                        return true;
                    }

                    if (error.name) {
                        // open hidden part
                        if (error.name.substr(0,25) === "promo[countdown_datetime]") {
                            that.$wrapper.trigger("promo_countdown_error", [error]);
                        }

                        $field = that.$wrapper.find("[name=\"" + error.name + "\"]").first();

                        if ($field.is(":checkbox") || $field.is(":radio")) {
                            var $_rule = $field.closest(".s-rule-section");
                            $field = $_rule;
                            $error_wrapper = $_rule.find("> .js-section-footer");
                        }

                        if (error.name === "promo[countdown_datetime][date]") {
                            $error_wrapper = $field.closest(".s-hidden");
                            $field = $field.parent().find(".js-datepicker");
                        }

                    } else if (error.id) {
                        if (error.id === "storefronts") {
                            $field = $error_wrapper = that.$wrapper.find(".s-storefronts-section");

                        } else if (error.id === "server_error") {
                            $error_wrapper = that.$wrapper.find(".js-page-errors-wrapper");
                            $field = that.$wrapper;

                        } else if (error.id === "rule_error") {
                            var field_name = error.rule + "[rule_type]";
                            var $rule_field = that.$wrapper.find('[name="' + field_name + '"]');
                            if ($rule_field.length) {
                                var $rule = $rule_field.closest(".s-rule-section");
                                $field = $rule;
                                $error_wrapper = $rule.find("> .js-section-footer");
                            }
                        }
                    }

                    if ($field.length) {
                        error.$field = $field;
                        renderError(error, $error_wrapper);
                    }

                    result.push(error);
                });

                return result;

                function renderError(error, $error_wrapper) {
                    var $error = $("<div class=\"s-error-message errormsg\" />").text(error.text);
                    var error_class = "error";

                    if (error.$field) {
                        var $field = error.$field;

                        if (!$field.hasClass(error_class)) {
                            $field.addClass(error_class);

                            if ($error_wrapper) {
                                $error_wrapper.append($error);
                            } else {
                                $error.insertAfter($field);
                            }

                            $field.on("change keyup", removeFieldError);
                        }
                    }

                    function removeFieldError() {
                        $field.removeClass(error_class);
                        $error.remove();

                        $field.off("change keyup", removeFieldError);
                    }
                }

            }
        };

        Promo.prototype.initFixedSubmitWrapper = function() {
            var that = this;

            /**
             * @class FixedBlock
             * @description used for fixing form buttons
             * */
            var FixedBlock = ( function($) {

                FixedBlock = function(options) {
                    var that = this;

                    // DOM
                    that.$window = $(window);
                    that.$wrapper = options["$section"];
                    that.$wrapperW = options["$wrapper"];
                    that.$form = that.$wrapper.parents('form');

                    // VARS
                    that.type = (options["type"] || "bottom");
                    that.lift = (options["lift"] || 0);

                    // DYNAMIC VARS
                    that.offset = {};
                    that.$clone = false;
                    that.is_fixed = false;

                    // INIT
                    that.initClass();
                };

                FixedBlock.prototype.initClass = function() {
                    var that = this,
                        $window = that.$window,
                        resize_timeout = 0;

                    $window.on("resize", function() {
                        clearTimeout(resize_timeout);
                        resize_timeout = setTimeout( function() {
                            that.resize();
                        }, 100);
                    });

                    $window.on("scroll", watcher);

                    that.$wrapper.on("resize", function() {
                        that.resize();
                    });

                    that.$form.on("input", function () {
                        that.resize();
                    });

                    that.init();

                    function watcher() {
                        var is_exist = $.contains($window[0].document, that.$wrapper[0]);
                        if (is_exist) {
                            that.onScroll($window.scrollTop());
                        } else {
                            $window.off("scroll", watcher);
                        }
                    }

                    that.$wrapper.data("block", that);
                };

                FixedBlock.prototype.init = function() {
                    var that = this;

                    if (!that.$clone) {
                        var $clone = $("<div />").css("margin", "0");
                        that.$wrapper.after($clone);
                        that.$clone = $clone;
                    }

                    that.$clone.hide();

                    var offset = that.$wrapper.offset();

                    that.offset = {
                        left: offset.left,
                        top: offset.top,
                        width: that.$wrapper.outerWidth(),
                        height: that.$wrapper.outerHeight()
                    };
                };

                FixedBlock.prototype.resize = function() {
                    var that = this;

                    switch (that.type) {
                        case "top":
                            that.fix2top(false);
                            break;
                        case "bottom":
                            that.fix2bottom(false);
                            break;
                    }

                    var offset = that.$wrapper.offset();
                    that.offset = {
                        left: offset.left,
                        top: offset.top,
                        width: that.$wrapper.outerWidth(),
                        height: that.$wrapper.outerHeight()
                    };

                    that.$window.trigger("scroll");
                };

                /**
                 * @param {Number} scroll_top
                 * */
                FixedBlock.prototype.onScroll = function(scroll_top) {
                    var that = this,
                        window_w = that.$window.width(),
                        window_h = that.$window.height();

                    // update top for dynamic content
                    that.offset.top = (that.$clone && that.$clone.is(":visible") ? that.$clone.offset().top : that.$wrapper.offset().top);

                    switch (that.type) {
                        case "top":
                            var use_top_fix = (that.offset.top - that.lift < scroll_top);

                            that.fix2top(use_top_fix);
                            break;
                        case "bottom":
                            var use_bottom_fix = (that.offset.top && scroll_top + window_h < that.offset.top + that.offset.height);
                            that.fix2bottom(use_bottom_fix);
                            break;
                    }

                };

                /**
                 * @param {Boolean|Object} set
                 * */
                FixedBlock.prototype.fix2top = function(set) {
                    var that = this,
                        fixed_class = "is-top-fixed";

                    if (set) {
                        that.$wrapper
                            .css({
                                position: "fixed",
                                top: that.lift,
                                left: that.offset.left
                            })
                            .addClass(fixed_class);

                        that.$clone.css({
                            height: that.offset.height
                        }).show();

                    } else {
                        that.$wrapper.removeClass(fixed_class).removeAttr("style");
                        that.$clone.removeAttr("style").hide();
                    }

                    that.is_fixed = !!set;
                };

                /**
                 * @param {Boolean|Object} set
                 * */
                FixedBlock.prototype.fix2bottom = function(set) {
                    var that = this,
                        fixed_class = "is-bottom-fixed";

                    if (set) {
                        that.$wrapper
                            .css({
                                position: "fixed",
                                bottom: 0,
                                left: that.offset.left,
                                width: that.offset.width
                            })
                            .addClass(fixed_class);

                        that.$clone.css({
                            height: that.offset.height
                        }).show();

                    } else {
                        that.$wrapper.removeClass(fixed_class).removeAttr("style");
                        that.$clone.removeAttr("style").hide();
                    }

                    that.is_fixed = !!set;
                };

                return FixedBlock;

            })(jQuery);

            new FixedBlock({
                $wrapper: that.$wrapper,
                $section: that.$wrapper.find(".js-page-footer"),
                type: "bottom"
            });

        };

        Promo.prototype.initCouponRulesSection = function(options) {
            var that = this;

            var $wrapper = options["$wrapper"],
                $list = $wrapper.find(".s-coupons-list");

            var urls = options["urls"],
                templates = options["templates"];

            var $field = $wrapper.find(".js-autocomplete");
            if ($field.length) {
                initAutocomplete($field);
            }

            function initAutocomplete($field) {
                var coupon_ids_string = getCouponIdsString();

                $field.autocomplete({
                    source: urls["autocomplete"] + coupon_ids_string,
                    appendTo: $wrapper,
                    minLength: 0,
                    focus: function() {
                        return false;
                    },
                    select: function(event, ui) {
                        addCoupon(ui.item.data);
                        $field.val("");
                        return false;
                    }
                });

                function addCoupon(coupon_data) {
                    var expire_html = ( coupon_data.expire_datetime_string ? templates["coupon_expire"].replace("%expire%", coupon_data.expire_datetime_string) : "" );

                    var template = templates["coupon"]
                        .replace(/%coupon_id%/g, coupon_data.id)
                        .replace("%code%", coupon_data.code)
                        .replace("%discount%", coupon_data.discount_string)
                        .replace("%expire%", expire_html);

                    $list.append(template);

                    coupon_ids_string = getCouponIdsString();
                }

                function getCouponIdsString() {
                    var coupon_ids = getCouponIds(),
                        result = "";

                    if (coupon_ids.length) {
                        $.each(coupon_ids, function(i, id) {
                            result += "&coupon_id[]=" + id;
                        });
                    }

                    return result;

                    function getCouponIds() {
                        var result = [];

                        $wrapper.find(".s-coupon-wrapper").each( function() {
                            var $coupon = $(this),
                                id = $coupon.data("id") + "";

                            if (id.length) {
                                result.push(id);
                            }
                        });

                        return result;
                    }
                }
            }
        };

        Promo.prototype.initPriceRulesSection = function(options) {
            var that = this;

            var $wrapper = options["$wrapper"],
                $products_wrapper = $wrapper.find(".s-products-wrapper"),
                $list = $products_wrapper.find(".s-products-list");

            var urls = options["urls"],
                rule_name = options["rule_name"];

            var product_ids = getProductIds();

            var $field = $wrapper.find(".js-autocomplete");
            if ($field.length) {
                initAutocomplete($field);
            }

            $products_wrapper.on("click", ".js-delete-sku", function(event) {
                event.preventDefault();
                var $product = $(this).closest(".s-product-wrapper"),
                    product_id = $product.data("id");

                var $_products = $products_wrapper.find(".s-product-wrapper[data-id=\"" + product_id + "\"]");
                $_products.each( function() {
                    deleteProduct( $(this) );
                });

                productsToggle();
            });

            function deleteProduct($product) {
                var product_id = $product.data("id"),
                    sku_id = $product.data("sku-id");

                if (sku_id) {
                    delete product_ids[product_id][sku_id];
                }

                $product.remove();
                product_ids = getProductIds();
            }

            function initAutocomplete($field) {
                var product_xhr = null;

                $field.autocomplete({
                    source: urls["autocomplete"],
                    appendTo: $wrapper,
                    minLength: 0,
                    focus: function() {
                        return false;
                    },
                    select: function(event, ui) {
                        addProduct(ui.item);
                        $field.val("");
                        return false;
                    }
                });

                function addProduct(product_data) {
                    var product_id = product_data.id;

                    getProductData(product_id).then( function(response) {
                        if (response.status === "ok") {
                            $list.append( formatProducts(response.data.html) );
                            product_ids = getProductIds();
                            productsToggle(true);
                        }
                    });
                }

                function getProductData(product_id) {
                    var href = urls["product"],
                        data = {
                            "product_id": product_id,
                            "options[rule_name]": rule_name
                        };

                    if (product_xhr) {
                        product_xhr.abort();
                    }

                    product_xhr = $.post(href, data).always( function() {
                        product_xhr = null;
                    });

                    return product_xhr;
                }

                function formatProducts(html) {
                    var $div = $("<div />").html(html);

                    $div.find(".s-product-wrapper").each( function() {
                        var $product = $(this),
                            product_id = $product.data("id"),
                            sku_id = $product.data("sku-id"),
                            is_exist = (product_ids[product_id] && product_ids[product_id][sku_id]);

                        if (is_exist)  { $product.remove(); }
                    });

                    return $div.html();
                }
            }

            function getProductIds() {
                var result = {};

                $list.find(".s-product-wrapper").each( function() {
                    var $product = $(this),
                        product_id = $product.data("id"),
                        sku_id = $product.data("sku-id");

                    if (sku_id) {
                        if (!result[product_id]) { result[product_id] = {} }
                        result[product_id][sku_id] = true;
                    }
                });

                return result;
            }

            function productsToggle(show) {
                var empty_class = "is-empty";

                show = ( typeof show === "boolean" ? show : null);

                if (show === null) {
                    show = !!$products_wrapper.find(".s-product-wrapper").length;
                }

                if (show) {
                    $products_wrapper.removeClass(empty_class);
                } else {
                    $products_wrapper.addClass(empty_class);
                }
            }
        };

        Promo.prototype.initEditName = function() {
            var that = this;

            var $wrapper = that.$wrapper.find(".s-page-header .s-title"),
                $promo_name = $wrapper.find(".js-name-editable"),
                $field = $wrapper.find(".js-title-field"),
                $form_title_field = that.$wrapper.find('input[name="promo[title]"]');

            var edit_class = "is-edit";

            $promo_name.on("click", function(event) {
                event.preventDefault();
                $wrapper.addClass(edit_class);
                $field.trigger("focus");
            });

            $field.on("blur", function() {
                $wrapper.removeClass(edit_class);
            });

            $field.on("change", function() {
                var value = $(this).val();
                $promo_name.text(value);
                $form_title_field.val(value);
            });

            $form_title_field.on("keyup change", function() {
                var value = $(this).val();
                $promo_name.text(value);
                $field.val(value);
            });
        };

        return Promo;

        function getDate(date_string) {
            var date_array = date_string.split("-");

            var year = date_array[0],
                mount = Math.floor(date_array[1]) - 1,
                day = date_array[2];

            return new Date(year, mount, day);
        }

    })($);

    $.shop.marketing.init.promoPage = function(options) {
        return new Promo(options);
    };

})(jQuery);