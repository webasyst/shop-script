( function ($) {
    $.storage = new $.store();
    $.reports = {
        $content: $("#reportscontent"),
        init: function (options) {
            const that = this;

            if (typeof($.History) != "undefined") {
                $.History.bind(function () {
                    that.dispatch();
                });
            }

            $.wa.errorHandler = function (xhr) {
                if ((xhr.status === 403) || (xhr.status === 404) ) {
                    $("#s-content").html('<div class="content"><div class="box">' + xhr.responseText + '</div></div>');
                    return false;
                }
                return true;
            };

            $(document).trigger('shop_reports_init');

            const hash = window.location.hash;
            if (hash === '#/' || !hash) {
                this.dispatch();
            } else {
                $.wa.setHash(hash);
            }

            document.documentElement.setAttribute('lang', options.lang);

            that.loading = options.loading;

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

        initTypeSourceSelector: function() {
            // Loading indicator when user clicks on a link in sidebar
            $('.js-reports-source-dropdown').waDropdown({
                items: '.menu > li > a',
                ready(dropdown) {
                    const default_type = 'sources';
                    const hash = window.location.hash || `#/sales/type=${default_type}`;

                    let match = hash.match(new RegExp('type=(\\w+)'));
                    let type = default_type;
                    if (match && match[1]) {
                        type = match[1];
                    }

                    const activeItem = dropdown.$menu.find(`li[data-type-id="${type}"]`).addClass('selected');

                    dropdown.setTitle(activeItem.text());
                },
                change(_, element) {
                    const item = $(element).closest('li');
                    item.addClass('selected').siblings().removeClass('selected');
                    const href = $(element).attr('href');

                    if (href !== window.location.hash) {
                        window.location.hash = href;
                    }
                }
            });

            $('.js-reports-channel-dropdown').waDropdown({
                items: '.menu > li > a',
                ready($dropdown) {
                    const el = $dropdown.$menu.find('li.selected');
                    $dropdown.setTitle(el.children().text());
                },
                change($dropdown, element) {
                    const item = $(element).closest('li');
                    item.addClass('selected').siblings().removeClass('selected');
                    $.reports.dispatch();
                }
            });
        },

        // Timeframe selector logic
        initTimeframeSelector: function() {
            const wrapper = $('.js-reports-timeframe');
            const $dropdown = wrapper.find('.js-reports-timeframe-dropdown');
            const custom_wrapper = $('.js-custom-timeframe').hide();

            const setPeriodDescription = function (title) {
                $('#period-description').html(title);
            }

            // Helper to get timeframe data from <li> element
            const getTimeframeData = function(li) {
                return {
                    timeframe: (li && li.data('timeframe')) || 30,
                    groupby: (li && li.data('groupby')) || 'days'
                };
            };

            // Helper to set active timeframe <li>
            const setActiveTimeframe = function(li) {
                const tf = getTimeframeData(li);
                if (tf.timeframe !== 'custom') {
                    $.storage.set('shop/reports/timeframe', tf);
                }
            }

            // Helper to set up custom period selector
            let initCustomSelector = function() {

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

                        $.reports.dispatchWithSpinner();
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
                    const tf = $.reports.getTimeframe();

                    if (tf.timeframe === 'custom') {
                        from.datepicker('setDate', tf.from ? new Date(tf.from*1000) : null);
                        to.datepicker('setDate', tf.to ? new Date(tf.to*1000) : null);
                    } else if (tf.timeframe === 'all') {
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

            $dropdown.waDropdown({
                items: '.menu > li > a',
                ready($dropdown) {
                    const { $menu } = $dropdown;

                    // Initial selection in dropdown menu
                    const data = $.storage.get('shop/reports/timeframe') || getTimeframeData(wrapper.find('ul li[data-default-choice]:first'));
                    const li = $menu.find(`li[data-timeframe="${data.timeframe}"]`).addClass('selected');
                    const title = li.children().text();
                    $dropdown.setTitle(title);

                    // Human-readable period description in page header
                    if (data.timeframe === 'custom') {
                        setTimeout(function() {
                            custom_wrapper.show();
                            initCustomSelector();
                            setActiveTimeframe(wrapper.find('ul li[data-timeframe="custom"]'));
                        }, 100);
                    } else {
                        setPeriodDescription(title);

                        const tf = getTimeframeData(li);
                        if (tf.timeframe == data.timeframe && tf.groupby == data.groupby) {
                            setActiveTimeframe(li);
                        }
                    }
                },
                change(dropdown, active) {
                    const item = $(active).closest('li');
                    item.addClass('selected').siblings().removeClass('selected');

                    const tf = getTimeframeData(item);

                    if (tf.timeframe === 'custom') {
                        setPeriodDescription($.wa.locale['Custom range']);
                        custom_wrapper.show();
                        initCustomSelector();
                        setActiveTimeframe(item);
                    } else {
                        custom_wrapper.hide();
                        setActiveTimeframe(item);

                        $.reports.dispatchWithSpinner();
                    }
                }
            });
        },

        dispatchWithSpinner: function(hash) {
            this.with_spinner = true;
            this.dispatch(hash);
        },

        dispatch: function (hash) {
            if (hash === undefined) {
                hash = window.location.hash;
            }
            hash = hash.replace(/(^[^#]*#\/*|\/$)/g, ''); /* fix syntax highlight*/
            var original_hash = this.hash
            this.hash = hash;

            var e = new $.Event('wa_before_dispatched');
            $(window).trigger(e);

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

            const hash = '#/' + action + '/';
            const $li = $('ul.s-reports a[href="' + hash + '"]').closest('li').addClass('selected');
            $li.length && $li.siblings().removeClass('selected');
        },

        defaultAction: function () {
            this.setActiveTop('sales');
            this.salesAction();
        },

        parseParams: function (params) {
            var map = { };
            var sort = 0;
            $.each((params || '').split('&'), function (i, val) {
                val = (val || '');
                var exp = val.split('=');
                var left = exp[0] || '';
                var right = exp[1] || '';
                if (left) {
                    map[left] = {
                        value: right,
                        sort: sort++
                    };
                }
            });
            return map;
        },

        unparseParams: function (map) {
            var params_ar = $.map(map, function (item, key) {
                if (key && item !== undefined) {
                    var sort = 0, value = '';
                    if ($.isPlainObject(item)) {
                        sort = parseInt(item.sort, 10) || 0;
                        value = '' + (item.value || '');
                    } else {
                        value = '' + (item || '');
                    }
                    return { key: key, value: value, sort: sort };
                }
            });
            params_ar = params_ar.sort(function (a, b) {
                return a.value === 'type' && (a.sort > b.sort || a.value > b.value);
            });
            return $.map(params_ar, function (item) { return item.key + '=' + item.value; }).join('&');
        },

        preContent: function() {
            this.with_spinner && this.$content.html(this.loading);
        },

        initHeaderControlls: function() {
            this.with_spinner = false;
            $.reports.initTypeSourceSelector();
            $.reports.initTimeframeSelector();
        },

        salesAction: function (params) {
            const that = this;

            const action_url = '?module=reports&action=sales'+this.getTimeframeParams();

            const params_map = $.reports.parseParams(params);

            const redirect = function() {
                const hash = 'sales/' + $.reports.unparseParams(params_map);
                $.wa.setHash(hash);
            };

            if (!params_map['details'] && !params_map['filter[name]'] && $.storage.get('shop/reports/sales-details')) {
                params_map['details'] = 1;
                redirect();
                return;
            }

            if (params_map['details'] && params_map['filter[name]']) {
                delete params_map['details'];
                redirect();
            }

            const rnd_protect = $.reports.rnd_protect = Math.random();

            this.preContent();

            $.post(action_url, $.reports.unparseParams(params_map), function(r) {
                if (rnd_protect != $.reports.rnd_protect) {
                    return; // too late, user clicked something else
                }

                that.replaceContent(r);
            });
        },

        salesAbtestingAction: function(id) {
            this.setActiveTop('sales');
            this.load('?module=reports&action=abtesting'+(id ? '&id='+id : '')+this.getTimeframeParams());
        },

        customersAction: function() {
            this.load('?module=reports&action=customers'+this.getTimeframeParams());
        },

        cohortsAction: function() {
            const params = {};
            const source = $('#s-cohorts-source-selector').val();
            if (source) {
                params.source = source;
            }

            const type = $('#s-cohorts-type-selector li.selected').data('type');
            if (type) {
                params.type = type;
            }

            const params_str = Object.keys(params).length ? '&' + $.param(params) : '';

            this.load('?module=reports&action=cohorts'+this.getTimeframeParams()+params_str);
        },

        summaryAction: function() {
            this.setActiveTop('summary');
            this.load('?module=reportsproducts&action=default&show_sales=1'+this.getTimeframeParams());
        },

        productsAction: function() {
            this.productsBestsellersAction();
        },
        productsBestsellersAction: function(params) {
            this.setActiveTop('products');
            this.load('?module=reportsproducts&action=default'+this.getTimeframeParams()+(params ? '&'+params : ''));
        },
        productsAssetsAction: function() {
            this.setActiveTop('products');
            const params = {};
            const limit = $.storage.get('shop/reports/assets/limit');
            if (limit) {
                params.limit = limit;
            }

            const stock = $('#stock-selector').val();
            if (stock) {
                params.stock = stock;
            }

            const params_str = Object.keys(params).length ? '&' + $.param(params) : '';

            this.load('?module=reportsproducts&action=assets'+this.getTimeframeParams()+params_str);
        },
        productsWhattosellAction: function() {
            this.setActiveTop('products');
            const limit = $.storage.get('shop/reports/whattosell/limit');
            const only_sold = $.storage.get('shop/reports/whattosell/only_sold');
            this.load('?module=reportsproducts&action=whattosell'+this.getTimeframeParams()+(limit ? '&limit='+limit : '')+(only_sold ? '&only_sold=1' : ''));
        },

        checkoutflowAction: function() {
            this.load('?module=reports&action=checkoutflow'+this.getTimeframeParams());
        },

        load: function(path) {
            const that = this;

            that.preContent();
            that.$content.load(path, function() {
                $(document).trigger('wa_loaded');
                that.initHeaderControlls();
            });
        },

        replaceContent: function(html) {
            this.$content.html(html);
            this.initHeaderControlls();
        },

        setSalesChannel: function(obj) {
            const sales_channel = $('.js-reports-channel-dropdown li.selected').data('value');
            if (sales_channel) {
                obj.sales_channel = sales_channel;
            }
        },

        // Helper
        getTimeframe: function() {
            var result = $.storage.get('shop/reports/timeframe') || {
                timeframe: 90,
                groupby: 'days'
            };

            this.setSalesChannel(result);

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
                    if (isNaN(value)) {
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
