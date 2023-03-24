( function($) {

    var CostsPage = ( function($) {

        CostsPage = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$table = that.$wrapper.find("#s-reports-marketing-costs-table");
            that.$add_button = that.$wrapper.find("#s-new-expense-button");

            // CONST
            that.locales = options["locales"];
            that.urls = options["urls"];

            that.request_options = options["request_options"];
            that.chart_data = options["chart_data"];
            that.limit = options["limit"];

            that.cost_id = options["cost_id"];
            that.promo_id = options["promo_id"];
            that.start_date = options["start_date"];
            that.finish_date = options["finish_date"];

            // DYNAMIC VARS

            // INIT
            that.init();
        };

        CostsPage.prototype.init = function() {
            var that = this;

            that.initRoot();
            that.initTimeFilter();
            that.initChart();

            if (that.cost_id) {
                var $edit_link = that.$table.find(".expense-row[data-expense-id=\"" + that.cost_id + "\"] .js-edit-expense");
                if ($edit_link.length) {
                    $edit_link.trigger("click");
                }
            } else if (that.promo_id) {
                that.$add_button.trigger("click");
            }
        };

        CostsPage.prototype.initRoot = function() {
            var that = this;

            var request_options = that.request_options;
            var limit = that.limit;

            var $table = that.$table;
            var $editor_wrapper = $('#s-mcosts-editor-wrapper');
            var $load_more_link = $('#load-more-link');

            // when user clicks on an edit link in a table, load that row into editor
            $table.on('click', '.js-edit-expense', function() {
                var expense_id = $(this).closest('.expense-row').data('expense-id');
                expense_id && showEditor(expense_id);
            });

            // Show empty editor when user clicks on a "new expense" button
            that.$add_button.on("click", function(event) {
                event.preventDefault();
                showEditor('');
            });

            // "Load more" link fetches more rows into table
            $load_more_link.on("click", function(event) {
                event.preventDefault();

                if ($load_more_link.find('.loading').length) {
                    return false;
                }
                var count = $table.children().length;
                $load_more_link.append('<i class="icon16 loading"></i>');
                $.post('?module=marketingCostRows', { start: count }, function(r) {
                    $load_more_link.find('.loading').remove();
                    $table.append($.parseHTML(r));
                    updatePeriods();
                    if ($table.children().length === count) {
                        $load_more_link.hide();
                    }
                });
            });

            updatePeriods();

            // Helper to load HTML for expense editor
            var editor_xhr = null;
            function showEditor(expense_id) {
                $editor_wrapper.show().html('<div class="block"><i class="icon16 loading"></i></div>');

                if (editor_xhr) {
                    editor_xhr.abort();
                }

                var data = {
                    expense_id: expense_id
                };
                if (that.promo_id) {
                    data.promo_id = that.promo_id;
                }
                if (that.start_date) {
                    data.start_date = that.start_date;
                }
                if (that.finish_date) {
                    data.finish_date = that.finish_date;
                }

                editor_xhr = $.get("?module=marketingCostEdit", data, function(html) {
                    $editor_wrapper.html(html);
                    that.initEditCosts();

                }).always( function() {
                    editor_xhr = null;
                });
            }

            // Helper to update period bars in table
            function updatePeriods() {

                var MAX_BAR_WIDTH = 100;

                var $periods = $table.find('> .expense-row > td.period-td > .period');

                var min_ts = (new Date()).getTime() / 1000;
                var max_ts = 0;
                $periods.each(function() {
                    var $period = $(this);
                    var end_ts = $period.data('end-ts');
                    end_ts > max_ts && (max_ts = end_ts);
                    var start_ts = $period.data('start-ts');
                    start_ts < min_ts && (min_ts = start_ts);
                });

                var ts_diff = max_ts - min_ts;
                if (ts_diff <= 0) {
                    $periods.css('width', '250px').children('.period-bar').css('width', '0%');
                    return;
                }

                $periods.each(function() {
                    var $period = $(this);
                    var end_ts = $period.data('end-ts');
                    var start_ts = $period.data('start-ts');

                    var period_width = ((max_ts - start_ts)*MAX_BAR_WIDTH / ts_diff);
                    $period.css('width', period_width+'%');

                    var bar_pos = ((max_ts - end_ts)*MAX_BAR_WIDTH / ts_diff);
                    var bar_width = (period_width - bar_pos)*100 / period_width;
                    $period.children('.period-bar').css('width', bar_width+'%');
                });
            }
        };

        CostsPage.prototype.initTimeFilter = function() {
            var that = this;

            var $section = that.$wrapper.find(".js-timeframe-filter"),
                $custom = $section.find(".js-timeframe-custom");

            initDatePickers($section);

            $custom.on("submit", "form", function(event) {
                event.preventDefault();

                var $form = $(this);

                var data = $form.serialize(),
                    redirect_uri = that.urls["dir_root"] + "?" + data;

                $.shop.marketing.content.load(redirect_uri);
            });

            $section.on("click", ".js-set-timeframe", function() {
                var $link = $(this),
                    id = $link.data("id"),
                    name = $link.text();

                if (id === "custom") {
                    $custom.show();

                    // set active name
                    $section.find(".js-active-name").text(name);

                    // hide menu
                    var $list = $link.closest(".menu-v");
                    $list.hide();
                    setTimeout( function() {
                        $list.removeAttr("style");
                    }, 100);

                } else {
                    $custom.hide();
                }
            });

            function initDatePickers($section) {
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
        };

        CostsPage.prototype.initChart = function() {
            var that = this;

            if (!d3) {
                console.log("D3 is required");
                return false;
            }

            if (!that.chart_data) {
                console.log("Chart data is required");
                return false;
            }

            var resize_timer = null;
            $(window).on("resize", resizeWatcher);
            function resizeWatcher() {
                var is_exist = $.contains(document, that.$wrapper[0]);
                if (is_exist) {
                    clearTimeout(resize_timer);
                    setTimeout( function () {
                        renderChart(that.chart_data, "reload");
                    }, 100);

                } else {
                    $(window).off("resize", resizeWatcher);
                }
            }

            // INIT
            return renderChart(that.chart_data);

            // CONSTRUCTOR
            function renderChart(dataset, type) {
                var groups,rect_ornament,rect;

                var storage = {
                    wrapper: that.$wrapper.find(".s-graph-wrapper"),
                    hint_wrapper: that.$wrapper.find(".s-hint-wrapper"),
                    document_width: false,
                    padding: {
                        top: 8,
                        bottom: 28,
                        right: 8,
                        left: 8
                    },
                    xTicks: 10,
                    yTicks: 3,
                    colorsArray: ["#a7e0a7","#009900","#ae4410"],
                    hovered_point: false, // Container for points logic
                    getColumnWidth: function() {
                        return parseInt( (this.getInnerWidth() * 0.8)/data_count );
                    },
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
                        var data_array = data[0].date,
                            first = data_array[0],
                            second = data_array[1],
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

                //costs-wrapper
                var data_count = dataset[0].data.length;

                //Set up stack method
                var stack = d3.layout.stack();

                //Data, stacked
                stack( d3.range(dataset.length).map( function(d) {
                    var data = dataset[d].data;
                    return d3.range(data.length).map( function(d) {
                        return data[d];
                    });
                }));

                var first_data = dataset[0].data,
                    xMin = first_data[0].time,
                    xMax = first_data[first_data.length-1].time,
                    yMax = d3.max(dataset, function(d) {
                        d = d.data;
                        return d3.max(d, function(d) {
                            return d.y0 + d.y;
                        });
                    });

                xMin -= (xMax - xMin)/(9 * 5);
                xMax += (xMax - xMin)/(9);
                yMax += yMax/9;

                //Set up scales
                var xScale = d3.time.scale()
                    .domain([new Date(xMin),new Date(xMax)])
                    .rangeRound([0, storage.getInnerWidth()]);

                var yScale = d3.scale.linear()
                    .domain([0,yMax])
                    .range([storage.getInnerHeight(),0]);

                var xAxis = d3.svg.axis()
                    .scale(xScale)
                    .orient("bottom")
                    .ticks(storage.xTicks);

                var yAxis = d3.svg.axis()
                    .scale(yScale)
                    .innerTickSize(2)
                    .orient("right")
                    .ticks(storage.yTicks);

                if ( type && type === "refresh" ) {
                    svg = d3.select( storage.wrapper.find("svg")[0] );

                } else {
                    storage.wrapper.html("");

                    //Create SVG element
                    var svg = d3.select( storage.wrapper[0] )
                        .append("svg")
                        .attr("width", storage.getWidth())
                        .attr("height", storage.getHeight());

                    // Паттерны
                    ( function() {

                        var width = 15,
                            stroke_width = 6,
                            stroke_color = "rgba(0,0,0,0.5)",
                            getD = function( width ) {
                                return "M-"+ width/4 +","+ width/4 +" l"+ width/2 +",-"+ width/2 +" M0,"+ width +" l"+ width +",-"+ width +" M"+ width*3/4 +","+ width*5/4 +" l"+ width/2 +",-"+ width/2;
                            };

                        var defs = svg.append("defs");

                        defs.append('pattern')
                            .attr('id', 'diagonalHatch')
                            .attr('patternUnits', 'userSpaceOnUse')
                            .attr('width', width)
                            .attr('height', width)
                            .append('path')
                            .attr('d', getD(width))
                            .attr("stroke", stroke_color)
                            .attr("stroke-width", stroke_width);

                    })();

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

                    // Render Осей
                    svg.append("g")
                        .attr("class","x axis")
                        .attr("transform","translate(" + storage.padding.left + "," + (storage.getHeight() - storage.padding.bottom) + ")");

                    svg.append("g")
                        .attr("class","y axis")
                        .attr("transform","translate(" + storage.padding.left + "," + storage.padding.top + ")");

                }

                // Render Осей
                var $x = svg.select(".x.axis"),
                    $y = svg.select(".y.axis");

                $x.call(xAxis);
                $y.call(yAxis);

                var yFirst = $y.select(".tick:first-child");
                var yFirstTranslateXY = yFirst.attr("transform").match(new RegExp('(\\d+),(\\d+)'));
                var yFirstPosX = parseFloat(yFirstTranslateXY[1]) - 6;
                var yFirstPosY = parseFloat(yFirstTranslateXY[2]) - 8;

                yFirst.attr("transform","translate(" + yFirstPosX + "," + yFirstPosY + ")");

                // RENDERING RECT
                //UPDATE GROUP
                groups = svg.selectAll(".rgroups")
                    .data(dataset)
                    .attr("class", function(d) {
                        return "rgroups " + d.type;
                    });

                // UPDATE RECT
                rect = groups.selectAll(".rect")
                    .data( function(d) {
                        return d.data;
                    });

                rect
                    .transition()
                    .duration(1000)
                    .attr("width", storage.getColumnWidth())
                    .attr("class","rect")
                    .attr("x", function(d) {
                        return xScale( new Date( +d.time ) ) - storage.getColumnWidth()/2;
                    })
                    .attr("y", function(d) {
                        return -(-yScale(d.y0) - yScale(d.y) + (storage.getHeight() - storage.padding.top - storage.padding.bottom)*2);
                    })
                    .attr("height", function(d) {
                        return -yScale(d.y) + (storage.getHeight() - storage.padding.top - storage.padding.bottom);
                    })
                    .attr("fill", function(d) {
                        return d.color;
                    });

                // UPDATE ORNAMENT
                rect_ornament = groups.selectAll(".ornament")
                    .data( function(d) {
                        return d.data;
                    });

                rect_ornament
                    .transition()
                    .duration(1000)
                    .attr("width", storage.getColumnWidth())
                    .attr("class","ornament")
                    .attr("x", function(d) {
                        return xScale( new Date( +d.time ) ) - storage.getColumnWidth()/2;
                    })
                    .attr("y", function(d) {
                        return -(-yScale(d.y0) - yScale(d.y) + (storage.getHeight() - storage.padding.top - storage.padding.bottom)*2);
                    })
                    .attr("height", function(d,i,j) {
                        var type = rect[j].parentNode.__data__.type,
                            height = (storage.getHeight() - storage.padding.top - storage.padding.bottom) -yScale(d.y);
                        if (type !== "campaign") {
                            height = 0;
                        }
                        return height;
                    })
                    .attr("fill", "url(#diagonalHatch)");

                // RENDER NEW
                groups.enter()
                    .append("g")
                    .attr("class", function(d) {
                        return "rgroups " + d.type;
                    })
                    .attr("transform","translate("+ storage.padding.left + "," + (storage.getHeight() - storage.padding.bottom) +")");

                // NEW RECT
                rect = groups.selectAll(".rect")
                    .data( function(d) {
                        return d.data;
                    });

                rect.enter()
                    .append("rect")
                    .attr("width", storage.getColumnWidth())
                    .attr("class","rect")
                    .attr("x", function(d) {
                        return xScale( new Date( +d.time ) ) - storage.getColumnWidth()/2;
                    })
                    .attr("y", function(d) {
                        return -(-yScale(d.y0) - yScale(d.y) + (storage.getHeight() - storage.padding.top - storage.padding.bottom)*2);
                    })
                    .attr("height", function(d) {
                        return -yScale(d.y) + (storage.getHeight() - storage.padding.top - storage.padding.bottom);
                    })
                    .attr("fill", function(d) {
                        return d.color;
                    })
                    .on("mouseover", function(data, i, j) {
                        onHover(data, i, j,  d3.select(this), d3.event);
                    })
                    .on("mousemove", function() {
                        onMove(d3.event);
                    })
                    .on("mouseout", function(data) {
                        onHoverOut(data, d3.select(this));
                    });
                // NEW ORNAMENT
                rect_ornament = groups.selectAll(".ornament")
                    .data( function(d) {
                        return d.data;
                    });

                rect_ornament
                    .enter()
                    .append("rect")
                    .attr("width", storage.getColumnWidth())
                    .attr("class","ornament")
                    .attr("x", function(d) {
                        return xScale( new Date( +d.time ) ) - storage.getColumnWidth()/2;
                    })
                    .attr("y", function(d) {
                        return -(-yScale(d.y0) - yScale(d.y) + (storage.getHeight() - storage.padding.top - storage.padding.bottom)*2);
                    })
                    .attr("height", function(d,i,j) {
                        var type = rect[j].parentNode.__data__.type,
                            height = (storage.getHeight() - storage.padding.top - storage.padding.bottom) -yScale(d.y);
                        if (type !== "campaign") {
                            height = 0;
                        }
                        return height;
                    })
                    .attr("fill", "url(#diagonalHatch)")
                    .on("mouseover", function(data, i, j) {
                        onHover(data, i, j,  d3.select(this), d3.event);
                    })
                    .on("mousemove", function() {
                        onMove(d3.event);
                    })
                    .on("mouseout", function(data) {
                        onHoverOut(data, d3.select(this));
                    });

                var onHover = function( data, i, j, $target, event) {

                    var $wrapper = storage.hint_wrapper,
                        position = getHintPosition(event),
                        html = "";

                    // Show Hint
                    html += "<div class=\"line\">" + data.header + "</div>";
                    html += "<div class=\"line\">" + dataset[j].label + ": " + data.amount_html + "</div>";

                    $wrapper
                        .html(html)
                        .addClass("is-shown")
                        .css(position);

                    // Marking column
                    $target.attr("class","is-hovered");
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

                var onHoverOut = function( data, $target ) {
                    // Hide Hint
                    storage.hint_wrapper.removeClass("is-shown");

                    // Clear column opacity
                    $target.attr("class","");
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

        CostsPage.prototype.initEditCosts = function() {
            var that = this;

            var $wrapper = that.$wrapper.find(".js-edit-costs-form-section");

            var $form = $wrapper.children('form');
            var $button = $form.find(':submit:first');
            var $channel_selector = $wrapper.find('select.channel-selector');
            var $channel_input_text = $channel_selector.parent().find('input[name="expense[name]"]');
            var $channel_input_hidden = $channel_selector.parent().find('input[name="expense[type]"]');
            var $channel_input_color = $channel_selector.parent().find('input[name="expense[color]"]');
            var $period_radios = $wrapper.find('input[name="expense_period_type"]');
            var expense_id = $form.find('[name="expense_id"]').val();

            $form.find('input[name="expense[amount]"]').focus();

            if (!expense_id) {
                // Remember last selected storefront in local storage
                (function() { "use strict";
                    var $selector = $form.find('[name="expense[storefront]"]').change(function() {
                        $.storage.set('shop/marketingcosts/storefront', $selector.val());
                    });

                    if ($selector.children().length === 2) {
                        $selector.val($selector.children().last().attr('value'));
                    } else {
                        $selector.val($.storage.get('shop/marketingcosts/storefront')||'');
                    }
                })();
            }

            // Clear error messages when user modifies something
            $form.on('change keyup', '.error', function() {
                var $field = $(this).closest('.field');
                $field.find('.errormsg').remove();
                $field.find('.error').removeClass('error');
                return true;
            });

            // init datepickers
            $period_radios.closest('.field').find('input:text').datepicker({
                'dateFormat': 'yy-mm-dd'
            }).datepicker('hide');

            // Open datepickers when user clicks on a calendar icon
            $period_radios.closest('.field').on('click', '.calendar', function() {
                $(this).siblings('input').datepicker('show');
            });

            // Open colorpicker when user clicks on its icon
            var setColor = (function() { "use strict";
                var $colorpicker_wrapper = $wrapper.find('.js-colorpicker');
                var $icon = $colorpicker_wrapper.closest('.value').find('i.icon16.color');
                var $hiden = $channel_input_color;

                var farbtastic = $.farbtastic($colorpicker_wrapper, setColor);

                $icon.css('cursor', 'pointer').click(function() {
                    $colorpicker_wrapper.slideToggle();
                });

                return setColor;

                function setColor(color) {
                    $hiden.val(color);
                    $icon.css('background', color);
                    farbtastic.setColor(color);
                    $channel_selector.children(':selected').data('color', color);
                }
            })();

            // Show/hide form elements depending on radios, etc.
            var animation_speed = 0;
            updateElements();
            $channel_selector.change(updateElements);
            $period_radios.change(function() {
                updateElements();
                clearErrors($(this).closest('.field'));
            });
            animation_speed = 'fast';

            // Close the editor when user clicks 'cancel' link
            $button.siblings('.cancel-button').click(function() {
                $wrapper.slideUp();
            });

            // Link to delete expense
            $wrapper.on("click", ".js-delete-expense-link", function(event) {
                event.preventDefault();

                if (confirm(that.locales["confirm"])) {
                    $.post('?module=marketingCostDelete', { expense_id: expense_id }, function() {
                        setTimeout(function() {
                            $wrapper.remove();
                        }, 0);

                        $.shop.marketing.content.reload();
                    });
                }
            });

            var is_locked = false;
            // Validate and save via XHR
            $form.on("submit", function(event) {
                clearErrors($form);
                event.preventDefault();
                var $loading = $('<i class="icon16 loading"></i>').insertAfter($button);
                if (!is_locked) {
                    is_locked = true;
                    $button.attr("disabled", true);
                    var form_data = $form.serialize();
                    $.post($form.attr("action"), form_data)
                        .always( function() {
                            is_locked = false;
                            $button.attr("disabled", false);
                        })
                        .done( function(response) {
                            if (response.status === "ok") {
                                $loading.removeClass("loading").addClass("yes").after("<span>"+ that.locales["saved"] +"</span>");
                                $.shop.marketing.content.reload();
                            } else if (response.errors) {
                                $loading.remove();
                                renderErrors(response.errors);
                            }
                        });
                }
            });

            function clearErrors($field_wrapper) {
                $field_wrapper.find('.errormsg').remove();
                $field_wrapper.find('.error').removeClass('error');
            }

            function renderErrors(errors) {
                $.each(errors, function(i, error) {
                    if (error.id && error.text) {
                        var $field = $form.find('[name="' + error.id + '"]');
                        if ($field.length) {
                            renderError(error, $field);
                        }
                    }
                });

                function renderError(error, $field) {
                    var $error = $('<em class="errormsg"></em>').text(error.text);
                    var error_class = "error";

                    if (!$field.hasClass(error_class)) {
                        $field.on("change keyup", removeFieldError).addClass(error_class).closest('.value').append($error);
                    }

                    function removeFieldError() {
                        $field.off("change keyup", removeFieldError).removeClass(error_class);
                        $error.remove();
                    }
                }
            }

            // Helper to update visibility of form elements and values of hidden fields
            // depending on what's selected in radios and selects
            function updateElements() {
                // Channel selector logic
                var $option = $channel_selector.children(':selected');
                var channel_name = $channel_selector.val();
                var channel_type = $option.data('channel-type');
                var channel_color = $option.data('color');

                $channel_input_hidden.val(channel_type);
                channel_color && setColor(channel_color);
                if (channel_name || !channel_type) {
                    $channel_input_text.val(channel_name).hide();
                } else {
                    $channel_input_text.show();
                }

                // Period selector logic
                var $field = $period_radios.closest('.field');
                if ($period_radios.filter(':checked').val() === 'one_time') {
                    $field.find('[name="expense_period_single"]').parent().show();
                    $field.find('[name="expense_period_from"], [name="expense_period_to"]').datepicker('hide').closest('div').slideUp(animation_speed);
                } else {
                    var $i = $field.find('[name="expense_period_single"]').datepicker('hide').parent().hide();
                    $field.find('[name="expense_period_from"], [name="expense_period_to"]').closest('div').slideDown(animation_speed);
                }
            }
        };

        return CostsPage;

    })($);

    $.shop.marketing.init.costsPage = function(options) {
        return new CostsPage(options);
    };

})(jQuery);
