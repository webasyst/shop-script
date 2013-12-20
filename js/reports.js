(function ($) {
    $.storage = new $.store();
    $.reports = {
        init: function (options) {
            var that = this;
            if (typeof($.History) != "undefined") {
                $.History.bind(function () {
                    that.dispatch();
                });
            }
            $.wa.errorHandler = function (xhr) {
                if ((xhr.status === 403) || (xhr.status === 404) ) {
                    $("#s-content").html('<div class="content left200px"><div class="block double-padded">' + xhr.responseText + '</div></div>');
                    return false;
                }
                return true;
            };
            var hash = window.location.hash;
            if (hash === '#/' || !hash) {
                this.dispatch();
            } else {
                $.wa.setHash(hash);
            }
            document.documentElement.setAttribute('lang', options.lang);
            $.reports.initTimeframeSelector();
        },

        // Timeframe selector logic
        initTimeframeSelector: function() {
            var wrapper = $('#mainmenu .s-reports-timeframe');
            var visible_option = wrapper.children(':first').children('a');
            var custom_wrapper = wrapper.children(':last').hide();

            // Helper to get timeframe data from <li> element
            var getTimeframeData = function(li) {
                return {
                    timeframe: (li && li.data('timeframe')) || 30,
                    groupby: (li && li.data('groupby')) || 'days'
                };
            };

            // Helper to set active timeframe <li>
            var setActiveTimeframe = function(li) {
                visible_option.find('b i').text(li.text());
                li.addClass('selected').siblings('.selected').removeClass('selected');
                var tf = getTimeframeData(li);
                if (tf.timeframe != 'custom') {
                    $.storage.set('shop/reports/timeframe', tf);
                }
            }

            // Helper to set up custom period selector
            var initCustomSelector = function() {

                var inputs = custom_wrapper.find('input');
                var from = inputs.filter('[name="from"]');
                var to = inputs.filter('[name="to"]');
                var groupby = custom_wrapper.find('select');

                // One-time initialization
                (function() {
                    var updatePage = function() {
                        var from_date = from.datepicker('getDate');
                        var to_date = to.datepicker('getDate');
                        if (!from_date || !to_date) {
                            return false;
                        }
                        $.storage.set('shop/reports/timeframe', {
                            timeframe: 'custom',
                            groupby: groupby.val(),
                            from: Math.floor(from_date.getTime() / 1000),
                            to: Math.floor(to_date.getTime() / 1000)
                        });
                        $('#reportscontent').html('<div class="double-padded block"><i class="icon16 loading"></i></div>');
                        $.reports.dispatch();
                    };

                    // Datepickers
                    inputs.datepicker().change(updatePage).keyup(function(e) {
                        if (e.which == 13 || e.which == 10) {
                            updatePage();
                        }
                    });
                    inputs.datepicker('widget').hide();
                    groupby.change(updatePage);
                })();

                // Code to run each time 'Custom' is selected
                initCustomSelector = function(){
                    // Set datepicker values depending on previously selected options
                    var tf = $.reports.getTimeframe();
                    if (tf.timeframe == 'custom') {
                        from.datepicker('setDate', tf.from ? new Date(tf.from*1000) : null);
                        to.datepicker('setDate', tf.to ? new Date(tf.to*1000) : null);
                    } else if (tf.timeframe == 'all') {
                        from.datepicker('setDate', null);
                        to.datepicker('setDate', null);
                    } else {
                        from.datepicker('setDate', '-'+parseInt(tf.timeframe, 10)+'d');
                        to.datepicker('setDate', new Date());
                    }
                    groupby.val(tf.groupby);
                };
                initCustomSelector();
            };

            // Change selection when user clicks on dropdown list item
            wrapper.children().first().on('click', 'ul li:not(.selected)', function() {
                var li = $(this);
                var tf = getTimeframeData(li);
                if (tf.timeframe == 'custom') {
                    custom_wrapper.show();
                    initCustomSelector();
                    setActiveTimeframe(li);
                } else {
                    custom_wrapper.hide();
                    setActiveTimeframe(li);
                    $('#reportscontent').html('<div class="double-padded block"><i class="icon16 loading"></i></div>');
                    $.reports.dispatch();
                }
            });

            // Initial selection in dropdown menu
            var timeframe = $.storage.get('shop/reports/timeframe') || getTimeframeData(wrapper.find('ul li:first'));
            if (timeframe.timeframe == 'custom') {
                // Delay initialization to allow datepicker locale to set up properly.
                // Kinda paranoid, but otherwise localization sometimes fail in FF.
                $(function() {
                    setTimeout(function() {
                        custom_wrapper.show();
                        initCustomSelector();
                        setActiveTimeframe(wrapper.find('ul li[data-timeframe="custom"]'));
                    }, 100);
                });
            } else {
                wrapper.find('ul li').each(function() {
                    var li = $(this);
                    var tf = getTimeframeData(li);
                    if (tf.timeframe == timeframe.timeframe && tf.groupby == timeframe.groupby) {
                        setActiveTimeframe(li);
                        timeframe = null;
                        return false;
                    }
                });
                if (timeframe) {
                    setActiveTimeframe(wrapper.find('ul li:first'));
                }
            }
        },

        dispatch: function (hash) {
            if (hash === undefined) {
                hash = window.location.hash;
            }
            hash = hash.replace(/(^[^#]*#\/*|\/$)/g, ''); /* fix syntax highlight*/
            var original_hash = this.hash
            this.hash = hash;
            if (hash) {
                hash = hash.split('/');
                if (hash[0]) {
                    var actionName = "";
                    var attrMarker = hash.length;
                    for (var i = 0; i < hash.length; i++) {
                        var h = hash[i];
                        if (i < 2) {
                            if (i === 0) {
                                actionName = h;
                            } else if (parseInt(h, 10) != h && h.indexOf('=') == -1) {
                                actionName += h.substr(0,1).toUpperCase() + h.substr(1);
                            } else {
                                attrMarker = i;
                                break;
                            }
                        } else {
                            attrMarker = i;
                            break;
                        }
                    }
                    var attr = hash.slice(attrMarker);
                    this.preExecute(actionName, attr);
                    if (typeof(this[actionName + 'Action']) == 'function') {
                        $.shop.trace('$.products.dispatch',[actionName + 'Action',attr]);
                        this.setActiveTop(actionName);
                        this[actionName + 'Action'].apply(this, attr);
                    } else {
                        $.shop.error('Invalid action name:', actionName+'Action');
                    }
                } else {
                    this.preExecute();
                    this.defaultAction();
                }
            } else {
                this.preExecute();
                this.defaultAction();
            }
        },

        preExecute: function () {

        },

        setActiveTop: function (action) {
            if (!action) {
                action = 'sales';
            }
            var hash = '#/' + action + '/';
            $("ul.s-reports li.selected").removeClass('selected');
            $('ul.s-reports a[href="' + hash + '"]').parent('li').addClass('selected');
        },

        defaultAction: function () {
            this.setActiveTop('sales');
            this.salesAction();
        },

        salesAction: function () {
            $("#reportscontent").load('?module=reports&action=sales'+this.getTimeframeParams());
        },

        profitAction: function () {
            $("#reportscontent").load('?module=reports&action=profit'+this.getTimeframeParams());
        },

        topAction: function () {
            $("#reportscontent").load('?module=reports&action=top'+this.getTimeframeParams());
        },
        topSalesAction: function () {
            this.setActiveTop('top');
            $("#reportscontent").load('?module=reports&action=top&mode=sales'+this.getTimeframeParams());
        },
        topProfitsAction: function () {
            this.setActiveTop('top');
            $("#reportscontent").load('?module=reports&action=top&mode=profits'+this.getTimeframeParams());
        },

        checkoutflowAction: function() {
            $("#reportscontent").load('?module=reports&action=checkoutflow'+this.getTimeframeParams());
        },

        // Helper
        getTimeframe: function() {
            return $.storage.get('shop/reports/timeframe') || {
                timeframe: 30,
                groupby: 'days'
            };
        },
        // Helper
        getTimeframeParams: function() {
            return '&' + $.param(this.getTimeframe());
        },

        // Helper to draw pie charts
        pie: function(id, data) {
            if (!data || !data.length) {
                $('#'+id).remove();
                return;
            }
            $.jqplot(id, data, {
                seriesColors : ["#0077CC", "#33BB11", "#EE5500", "#EEBB11", "#44DDDD", "#6b6b6b", "#686190", "#b2b000", "#00b1ab", "#76b300"],
                grid : {
                    borderWidth : 0,
                    background : '#ffffff',
                    shadow : false
                },
                legend : {
                    show : true,
                    location : 's'
                },
                seriesDefaults : {
                    shadow : false,
                    renderer : $.jqplot.PieRenderer,
                    rendererOptions : {
                        padding : 0,
                        sliceMargin : 1,
                        showDataLabels : false
                    }
                }
            });
        },

        // Helper to draw line charts
        graph: function (id, data, period_month, tickInterval) {
            if (tickInterval === undefined) {
                tickInterval = period_month ? '1 month' : '1 day';
            }
            $.jqplot(id, [data], {
                seriesColors : ["#3b7dc0", "#129d0e", "#a38717", "#ac3562", "#1ba17a", "#87469f", "#6b6b6b", "#686190", "#b2b000", "#00b1ab", "#76b300"],
                series : [{
                    color : '#129d0e',
                    yaxis : 'y2axis',
                    shadow : false,
                    lineWidth : 3,
                    fill : true,
                    fillAlpha : 0.1,
                    fillAndStroke : true,
                    rendererOptions : {
                        highlightMouseOver : false,
                    }
                }],
                grid : {
                    borderWidth : 0,
                    shadow : false,
                    background : '#ffffff',
                    gridLineColor : '#eeeeee'
                },
                axes:{
                    xaxis:{
                        renderer: $.jqplot.DateAxisRenderer,
                        showTickMarks: false,
                        tickOptions:{
                            formatString: period_month ? '%b %Y' : '%b %d'
                        },
                        tickInterval: tickInterval
                    },
                    y2axis:{
                        min: 0,
                        shopTicks: false,
                        showTickMarks: false,
                        tickOptions:{
                            formatString:'%.2f'
                        }
                    }
                },
                highlighter: {
                    show: true,
                    sizeAdjust: 7.5
                },
                cursor: {
                    show: false
                }
            });

        }
    }
})(jQuery);