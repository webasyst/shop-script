( function ($) {
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
            $.reports.initPaidOrdersNotice();
        },

        initPaidOrdersNotice: function() {
            var $wrapper = $('#reports-paid-orders-notice');
            if (!$.storage.get('shop/reports/paid-orders-notice-closed')) {
                $wrapper.show().one('click', '.close', function() {
                    $.storage.set('shop/reports/paid-orders-notice-closed', 1);
                    $wrapper.slideUp();
                });
            } else {
                $wrapper.remove();
            }
        },

        // Timeframe selector logic
        initTimeframeSelector: function() {
            var wrapper = $('#mainmenu .s-reports-timeframe');
            var visible_option = wrapper.children('.s-reports-timeframe-dropdown').children('a');
            var custom_wrapper = wrapper.children('.s-custom-timeframe').hide();

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
                initCustomSelector = function() {
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
            wrapper.children('.s-reports-timeframe-dropdown').on('click', 'ul li:not(.selected)', function() {
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
            var timeframe = $.storage.get('shop/reports/timeframe') || getTimeframeData(wrapper.find('ul li[data-default-choice]:first'));
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
                        $.shop.trace('$.reports.dispatch',[actionName + 'Action',attr]);
                        this.setActiveTop(actionName);
                        this[actionName + 'Action'].apply(this, attr);
                    } else {
                        $.shop.error('Invalid action name:', actionName+'Action');
                    }
                    this.postExecute(actionName, attr);
                } else {
                    this.preExecute();
                    this.defaultAction();
                    this.postExecute();
                }
            } else {
                this.preExecute();
                this.defaultAction();
                this.postExecute();
            }
        },

        preExecute: function () {
            $('body > .dialog').trigger('close').remove();
        },

        postExecute: function () {
            $('#s-reports-custom-controls').empty();
        },

        setActiveTop: function (action) {
            if (!action) {
                action = 'sales';
            }
            var hash = '#/' + action + '/';
            var $li = $('ul.s-reports a[href="' + hash + '"]').parent('li').addClass('selected');
            $li.length && $li.siblings().removeClass('selected');
        },

        defaultAction: function () {
            this.setActiveTop('sales');
            this.salesAction();
        },

        salesAction: function (params) {
            var action_url = '?module=reports&action=sales'+this.getTimeframeParams();

            var request_params = [];
            params && request_params.push(params);

            var $storefront_selector = $('#s-reports-custom-controls .storefront-selector');
            $storefront_selector.length && request_params.push('storefront='+$storefront_selector.val());

            var rnd_protect = $.reports.rnd_protect = Math.random();
            $.post(action_url, request_params.join('&'), function(r) {
                if (rnd_protect != $.reports.rnd_protect) {
                    return; // too late, user clicked something else
                }
                $('#reportscontent').html(r);
            });
        },

        salesCostsAction: function() {
            this.setActiveTop('sales');
            $("#reportscontent").load('?module=reportsmarketingcosts'+this.getTimeframeParams());
        },
        salesAbtestingAction: function(id) {
            this.setActiveTop('sales');
            $("#reportscontent").load('?module=reports&action=abtesting'+(id ? '&id='+id : '')+this.getTimeframeParams());
        },

        customersAction: function() {
            $("#reportscontent").load('?module=reports&action=customers'+this.getTimeframeParams());
        },

        cohortsAction: function() {
            $("#reportscontent").load('?module=reports&action=cohorts'+this.getTimeframeParams());
        },

        summaryAction: function() {
            this.setActiveTop('summary');
            $("#reportscontent").load('?module=reportsproducts&action=default&show_sales=1'+this.getTimeframeParams());
        },

        productsAction: function() {
            this.productsBestsellersAction();
        },
        productsBestsellersAction: function(params) {
            this.setActiveTop('products');
            $("#reportscontent").load('?module=reportsproducts&action=default'+this.getTimeframeParams()+(params ? '&'+params : ''));
        },
        productsAssetsAction: function() {
            this.setActiveTop('products');
            var limit = $.storage.get('shop/reports/assets/limit');
            $("#reportscontent").load('?module=reportsproducts&action=assets'+this.getTimeframeParams()+(limit ? '&limit='+limit : ''));
        },
        productsWhattosellAction: function() {
            this.setActiveTop('products');
            var limit = $.storage.get('shop/reports/whattosell/limit');
            var only_sold = $.storage.get('shop/reports/whattosell/only_sold');
            $("#reportscontent").load('?module=reportsproducts&action=whattosell'+this.getTimeframeParams()+(limit ? '&limit='+limit : '')+(only_sold ? '&only_sold=1' : ''));
        },

        checkoutflowAction: function() {
            $("#reportscontent").load('?module=reports&action=checkoutflow'+this.getTimeframeParams());
        },

        // Helper
        getTimeframe: function() {
            var result = $.storage.get('shop/reports/timeframe') || {
                timeframe: 90,
                groupby: 'days'
            };

            var $storefront_selector = $('#s-reports-custom-controls .storefront-selector');
            if ($storefront_selector.length && $storefront_selector.val()) {
                result.storefront = $storefront_selector.val();
            }

            return result;
        },
        // Helper
        getTimeframeParams: function() {
            return '&' + $.param(this.getTimeframe());
        },

        // Helper to sort the table by one of the columns
        sortTable: function($th, asc) {
            var col_index = $th.index();
            var $table = $th.closest('table');
            var $tbody = $table.children('tbody');

            // Detach all rows
            var $trs = $tbody.children().detach();

            // Prepare objects for faster sorting
            var sort_as_strings = false;
            var trs = $trs.map(function(i, tr) {
                var $tr = $(tr);
                var $td = $tr.children().eq(col_index);
                var data = $td.data('sort');
                var value;
                if (data !== undefined) {
                    value = parseFloat(data);
                    if (value === NaN) {
                        value = data;
                        sort_as_strings = true;
                    }
                }
                if (value === undefined) {
                    value = $.trim($tr.text());
                    sort_as_strings = true;
                }
                return {
                    tr: tr,
                    value: value
                };
            }).get();

            // Sort
            if (sort_as_strings) {
                trs.sort(function(a, b) {
                    if (a.value > b.value) {
                        return asc ? -1 : 1;
                    }
                    if (a.value < b.value) {
                        return asc ? 1 : -1;
                    }
                    return 0;
                });
            } else {
                trs.sort(function(a, b) {
                    if (asc) {
                        return a.value - b.value;
                    } else {
                        return b.value - a.value;
                    }
                });
            }

            // Attach rows back to DOM
            $tbody.append($.map(trs, function(o) {
                return o.tr;
            }));
        }

    }
})(jQuery);