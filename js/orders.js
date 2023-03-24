(function ($) {
    $.storage = new $.store();
    $.orders = {
        options: {
            view: 'table'      // default view
        },

        //
        // Init-related
        //

        init: function (options) {

            var that = this;
            that.options = options;
            if (typeof($.History) != "undefined") {
                $.History.bind(function () {
                    that.dispatch();
                });
            }

            // Make sure we can access the original request URL from any jqXHR objects
            // So we could in $.wa.errorHandler find to what url was request
            $.ajaxPrefilter(function(options, originalOptions, jqXHR) {
                jqXHR.originalRequestOptions = originalOptions;
            });

            $.wa.errorHandler = function (xhr) {
                // see ajaxPrefilter above
                var originalRequestOptions = xhr.originalRequestOptions || {};


                if ((xhr.status === 403) || (xhr.status === 404) ) {
                    var text = $(xhr.responseText);
                    if (text.find('.dialog-content').length) {
                        text = $('<div class="block double-padded"></div>').append(text.find('.dialog-content *'));

                    } else {
                        text = $('<div class="block double-padded"></div>').append(text.find(':not(style)'));
                    }

                    var container_type = 'content';

                    var request_url = originalRequestOptions.url || '';
                    if (request_url.length > 0) {
                        var params_start_pos = request_url.indexOf('?');
                        if (params_start_pos !== -1) {
                            var params_str = request_url.substr(params_start_pos + 1);
                            var params = $.shop.helper.parseParams(params_str) || {};
                            if (params.module === 'order' && params.view !== 'table' && $('#s-order').length) {
                                container_type = 'order';
                            }
                        }
                    }

                    if (container_type === 'order') {
                        // just render content about error
                        $('#s-order').empty().append(text);
                    } else {
                        // render content in common container and call destructor of order_list object, cause nothing we can do about it already
                        $("#s-content").empty().append(text);
                        if ($.order_list) {
                            $.order_list.finit();
                        }
                    }

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

            this.initSearch();
            this.initDropdown();

            // sync with app counters
            $(document).bind('wa.appcount', function(event, data) {
                // data could be undefined
                data = data || {};

                var cnt = parseInt(data.shop, 10);
                $('#s-pending-orders .small').text(cnt ? '+' + cnt : '');

                // update prefix of document.title. Prefix looks like: (\d+).
                // Take into account extreme cases: cnt=0 or no prefix yet
                var match = document.title.match(/\(\d+\) /);
                if (match) {
                    document.title = document.title.replace(match[0], cnt ? '(' + cnt + ') ' : '');
                } else {
                    if (cnt) {
                        document.title = '(' + cnt + ') ' + document.title;
                    }
                }
            });

            $(function () {
                $("#maincontent").on('click', '.s-alert-close', function (event) {
                    event.preventDefault();

                    var alerts = $.storage.get('shop/alerts');
                    var $item = $(this).parent();
                    if (!alerts) {
                        alerts = [];
                    }
                    alerts.push($item.data('alert'));
                    alerts = alerts.filter(function (elem, index, self) {
                        return (elem != null) && (index == self.indexOf(elem));
                    });
                    $.storage.set('shop/alerts', alerts);
                    $item.remove();
                });
            });

            this.checkAlerts();
            this.ordersNavDetach();
        },

        initSearch: function() {
            var search_input = $("#s-orders-search");

            var autocomplete_url = '?action=autocomplete&type=order';
            var last_response = [];

            var search_xhr = null;

            var onSelect = function(autocomplete_item) {
                switch (autocomplete_item.autocomplete_item_type) {
                    case 'order':
                        $.wa.setHash('#/order/' + autocomplete_item.id + '/');
                        search_input.val(autocomplete_item.value);
                        break;
                    case 'contact':
                        $.wa.setHash('#/orders/contact_id=' + autocomplete_item.id + '/');
                        search_input.val(autocomplete_item.value);
                        break;
                    case 'product':
                        $.wa.setHash('#/orders/product_id=' + autocomplete_item.id + '/');
                        search_input.val(autocomplete_item.value);
                        break;
                    case 'coupon':
                        $.wa.setHash('#/orders/coupon_id=' + autocomplete_item.id + '/');
                        break;
                    case 'tracking_number':
                        $.wa.setHash('#/orders/tracking_number=' + autocomplete_item.value + '/');
                        break;
                    case 'shipping':
                        $.wa.setHash('#/orders/shipping_id=' + autocomplete_item.id + '/');
                        break;
                    case 'payment':
                        $.wa.setHash('#/orders/payment_id=' + autocomplete_item.id + '/');
                        break;
                    case 'city':
                        $.wa.setHash('#/orders/city=' + autocomplete_item.value + '/');
                        break;
                    case 'region':
                        $.wa.setHash('#/orders/region=' + ((autocomplete_item.value || '').split(':')[1] || '') + '/');
                        break;
                    case 'country':
                        $.wa.setHash('#/orders/country=' + autocomplete_item.value + '/');
                        break;
                    case 'item_code':
                        $.wa.setHash('#/orders/item_code=' + autocomplete_item.value + '/');
                        break;
                    default:
                        // search
                        break;
                }
            };

            search_input.unbind('keydown').
                bind('keydown', function(event) {
                    if (event.keyCode == 13 || event.keyCode == 10) { // 'Enter'
                        // search is running...
                        if (search_xhr) { return false; }

                        var self = $(this);
                        if (!$(this).val()) {
                            location.hash = '#/orders/all/';
                            self.autocomplete("close");
                            return false;
                        } else {
                            if (last_response && $.isArray(last_response) && last_response.length) {
                                onSelect(last_response[0]);
                                setTimeout(function() {
                                    self.autocomplete("close");
                                }, 150);
                                return false;
                            }
                        }
                    }
                });

            search_input.autocomplete({
                minLength: 1,
                delay: 300,
                html: true,
                select: function(event, ui) {
                    //location.hash = '#/order/'+ui.item.id+'/';
                    onSelect(ui.item);
                    return false;
                },
                source : function(request, response) {
                    if (search_xhr) { search_xhr.abort(); }

                    search_xhr = $.getJSON(autocomplete_url, request, function(r) {
                        last_response = r;
                        response(r);

                    }).always( function() {
                        search_xhr = null;
                    });
                }
            });
        },

        initDropdown: function() {
            $('.js-orders-dropdown').waDropdown({
                ready(dropdown) {
                    const hash = window.location.hash;
                    if (hash) {
                        const search_hash = 'hash=search';
                        let params = decodeURIComponent(hash)
                            .replace(new RegExp(`#\\/orders\\/|${search_hash}\\/|\\/$`, 'g'), "")
                            .split('&')
                            .filter(Boolean);

                        if (params.length) {
                            const $orders_links = dropdown.$menu.find('.js-orders-link');

                            if ($orders_links.length) {
                                $orders_links.each(function () {
                                    const $item = $(this);
                                    if(params.some(item => item == $item.data('param'))) {
                                        dropdown.$button.html($item.html());
                                    }

                                    if (params.some(item => decodeURIComponent($item.attr('href')).includes(item))) {
                                        dropdown.$button.html($item.html());
                                    }

                                })
                            }
                        }

                        $('.js-remove-filters-link').toggleClass('hidden', !hash.includes(search_hash));
                    }

                    const $selected_item = dropdown.$menu.find(`[href="${hash}"]`);
                    if ($selected_item.length) {
                        dropdown.$button.html($selected_item.html());
                    }
                },
                items: ".menu > li > a",
                change(event, target, dropdown) {
                    let hash = window.location.hash;
                    let param = $(target).data('param');
                    const search_hash = 'hash=search'
                    const sales_channels = ['storefront', 'sales_channel']

                    // если хэш уже имеет замиксованные параметры и отсутствует явный параметр у выбранного фильтра
                    if (hash.includes(search_hash) && !param) {
                        param = decodeURIComponent($(target).attr('href').replace(/#\/orders\/|\/$/g, ''))
                    }

                    if (param) {
                        // готовим массив параметров
                        const params = decodeURIComponent(hash)
                            .replace(new RegExp(`#\\/orders\\/|${search_hash}\\/|&id=\\d+|\\/$`, 'g'), "")
                            .split('&')
                            .filter(Boolean);

                        // если среди параметров есть статусы заказов, то приводим их к нужному виду
                        const state_id_param_key = params.findIndex(item => item.includes('state_id'));
                        if(state_id_param_key >= 0) {
                            params[state_id_param_key] = params[state_id_param_key]?.replaceAll('|', '||');
                        }

                        // оставляем в параметрах только один какой-то канал продаж
                        const existed_sales_channel_key = params.findIndex(param => sales_channels.some(channel => param.includes(channel)));
                        if (existed_sales_channel_key !== -1) {

                            if (!params[existed_sales_channel_key].includes('params.')) {
                                // если среди параметров есть каналы продаж, то приводим их к нужному виду
                                params[existed_sales_channel_key] = params[existed_sales_channel_key].replace(new RegExp(`(${sales_channels.join('|')})=`, 'g'), 'params.$1=');

                                // возвращаем отрезанный слэш к имени витрины
                                if (params[existed_sales_channel_key].includes('storefront')) {
                                    params[existed_sales_channel_key] = params[existed_sales_channel_key] + '/'
                                }
                            }

                            if (sales_channels.some(channel => param.includes(channel))) {
                                params[existed_sales_channel_key] = `params.${param}`;
                            }
                        }

                        // если среди параметров есть существующий, но с другим значением, то обновляем значение
                        const [_param, _value] = param.split('=');
                        const existed_param_key = params.findIndex(item => item.includes(_param));
                        if (existed_param_key !== -1) {
                            params[existed_param_key] = _param + '=' + _value
                        } else {
                            params.push(param)
                        }

                        const encoded_params = encodeURIComponent(`/${params.join('&')}`);

                        hash = '/orders/' + search_hash.concat(encoded_params);

                        $.wa.setHash(hash);

                        $('.js-remove-filters-link').toggleClass('hidden', false);
                    } else {
                        location.replace($(target).attr('href'));
                    }

                    dropdown.$button.html($(target).html());
                }
            });

            $('.js-remove-filters-link').on('click', function () {
                $('.js-orders-dropdown').find('.dropdown-toggle').each(function () {
                    const text = $(this).data('text')
                    if (text) {
                        $(this).text(text);
                    }
                })
            })
        },

        //
        // Dispatch-related
        //

        // dispatch() ignores the call if prevHash == hash
        prevHash: null,
        hash: null,

        // if this is > 0 then this.dispatch() decrements it and ignores a call
        skipDispatch: 0,

        /** Cancel the next n automatic dispatches when window.location.hash changes */
        stopDispatch: function (n) {
            this.skipDispatch = n;
        },

        // Change location hash without triggering dispatch
        forceHash: function(hash) {
            if (location.hash != hash) {
                this.skipDispatch++;
                $.wa.setHash(hash);
            }
        },

        /** Implements #hash-based navigation. Called every time location.hash changes. */
        dispatch: function (hash) {
            if (this.skipDispatch > 0) {
                this.skipDispatch--;
                return false;
            }
            if (hash === undefined || hash === null) {
                hash = window.location.hash;
            }
            hash = hash.replace(/(^[^#]*#\/*|\/$)/g, ''); /* fix syntax highlight*/
            if (this.hash !== null) {
                this.prevHash = this.hash;
            }
            this.hash = hash;
            var e = new $.Event('wa_before_dispatched');
            $(window).trigger(e);

            if (hash) {
                hash = hash.split('/');
                if (hash[0]) {
                    var actionName = "";
                    var attrMarker = hash.length;
                    var lastValidActionName = null;
                    var lastValidAttrMarker = null;
                    for (var i = 0; i < hash.length; i++) {
                        var h = hash[i];
                        if (i < 2) {
                            if (i === 0) {
                                actionName = h;
                            } else if (parseInt(h, 10) != h && h.indexOf('=') == -1) {
                                actionName += h.substr(0,1).toUpperCase() + h.substr(1);
                            } else {
                                break;
                            }
                            if (typeof(this[actionName + 'Action']) == 'function') {
                                lastValidActionName = actionName;
                                lastValidAttrMarker = i + 1;
                            }
                        } else {
                            break;
                        }
                    }
                    attrMarker = i;

                    if (typeof(this[actionName + 'Action']) !== 'function' && lastValidActionName) {
                        actionName = lastValidActionName;
                        attrMarker = lastValidAttrMarker;
                    }

                    var attr = hash.slice(attrMarker);
                    this.preExecute(actionName);
                    if (typeof(this[actionName + 'Action']) == 'function') {
                        $.shop.trace('$.orders.dispatch',[actionName + 'Action',attr]);
                        this[actionName + 'Action'].apply(this, attr);
                    } else {
                        $.shop.error('Invalid action name:', actionName+'Action');
                    }
                    this.postExecute(actionName);
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

        back: function() {
            var prevHash = ($.orders.prevHash || '');
            location.hash = prevHash ? prevHash + '/' : '';
        },

        preExecute: function(actionName, attr) {
        },

        postExecute: function(actionName, attr) {
            this.actionName = actionName;
        },

        //
        // Action handlers.
        // Called by dispatch when corresponding location.hash is set.
        //

        defaultAction: function () {
            this.ordersAction();
        },

        couponsAction: function() {
            if ($.order_list) {
                $.order_list.finit();
            }
            this.load('?module=coupons');
        },

        ordersEditAction: function(id) {
            this.load('?module=order&action=edit&id='+id, function() {
                if ($.order_list) {
                    $.order_list.finit();
                }
            });
        },

        ordersNewAction: function (params) {
            this.load('?module=order&action=edit'+(params ? '&'+params : ''), function() {
                if ($.order_list) {
                    $.order_list.finit();
                }
            });
        },

        ordersAction: function(params) {

            if (params === 'hash') {
                params = {
                    hash: Array.prototype.slice.call(arguments, 1).join('/')
                };
            } else {
                var search_hash = '';
                if (params === 'search') {
                    search_hash = Array.prototype.slice.call(arguments, 1);
                }
                if (arguments.length > 1) {
                    params = Array.prototype.join.call(arguments, '/');
                }
                if (!params) {
                    // default params
                    params = "state_id=new|processing|auth|paid";
                } else if (params === 'all') {
                    params = '';
                }
                params = $.shop.helper.parseParams(params || '');
                if (!params.view) {
                    params.view = $.storage.get('shop/orders/view') || this.options.view;
                }
                $.storage.set('shop/orders/view', params.view);
                if ($.order_edit) {
                    $.order_edit.slideBack();
                }
                if (search_hash) {
                    params.search = search_hash;
                }
            }

            if (this.actionName !== 'orders' || !$.order_list) {
                if ($.order_list) {
                    $.order_list.finit();
                }
                this.load('?module=orders&'+this.buildOrdersUrlComponent(params), function() {
                    $(window).trigger('wa_loaded', [['table']]);
                    if ($.order_list) {
                        $.order_list.dispatch(params);
                    }
                });
            } else {
                $.order_list.dispatch(params);
            }
        },

        orderAction: function(id, params) {
            params = $.shop.helper.parseParams(params || '');
            if (!params.view) {
                params.view = $.storage.get('shop/orders/view') || this.options.view;
            }
            $.storage.set('shop/orders/view', params.view);

            if ($.order_edit) {
                $.order_edit.slideBack();
            }

            this.load('?module=order&id='+encodeURIComponent(id)+'&'+this.buildOrdersUrlComponent(params), function() {
                // back link at order content
                var $back_link = $("#s-content").find('#s-order-title a.back.order-list');

                $back_link.show();

                // list item at sidebar menu
                var $menu_item = $("#s-all-orders");
                if ($menu_item.length && $menu_item.hasClass("shadowed")) {
                    var $link = $menu_item.find("a:first");
                    if ($link.length) {
                        $back_link.attr("href", $link.attr("href"));
                    }
                }
            });

            if ($.order_list) {
                $.order_list.finit();
            }
        },

        //
        // Various helpers
        //

        buildOrdersUrlComponent: function(params) {
            var params_str = '';
            for (var name in params) {
                if (params.hasOwnProperty(name) && name !== 'view') {
                    params_str += '&' + name + '=' + params[name];
                }
            }
            return 'view=' + (params.view || this.options.view) + params_str;
        },

        /** Helper to load data into main content area. */
        load: function (url, options, fn) {

            if (typeof options === 'function') {
                fn = options;
                options = {};
            } else {
                options = options || {};
            }
            var r = Math.random();
            this.random = r;
            var self = this;
            return  $.get(url, function(result) {
                if ((typeof options.check === 'undefined' || options.check) && self.random != r) {
                    // too late: user clicked something else.
                    return;
                }
                (options.content || $("#s-content")).removeClass('bordered-left').html(result);
                if (typeof fn === 'function') {
                    fn.call(this);
                }

                // $('html, body').animate({scrollTop:0}, 200);
                var $window = $(window);
                setTimeout( function () {
                    $window.trigger("scroll");
                }, 250);

                showOrdersViewToggle();
                showOrdersSortMenu();

                self.checkAlerts();
            });

            function showOrdersViewToggle() {
                const $ordersViewToggle = $('.js-order-view');
                const is_orders_page = $("#s-order, #s-orders").length;

                if ($ordersViewToggle.length) {
                    if (is_orders_page) {
                        $ordersViewToggle.removeClass('hidden');
                        //$('#s-order-nav').removeClass('hidden');
                    } else {
                        $ordersViewToggle.addClass('hidden');
                        //$('#s-order-nav').addClass('hidden');
                    }
                }
            }

            function showOrdersSortMenu() {
                const $ordersSortMenu = $('.js-orders-sort');
                const is_orders_page = $("#s-order, #s-orders").length;

                if ($ordersSortMenu.length) {
                    if (is_orders_page) {
                        $ordersSortMenu.removeClass('hidden');
                    } else {
                        $ordersSortMenu.addClass('hidden');
                    }
                }
            }
        },


        checkAlerts: function () {
            var alerts = $.storage.get('shop/alerts');
            $.shop.trace('checkAlerts',alerts);
            $('.s-alert').each(function () {
                if ($.inArray($(this).data('alert'), alerts) == -1) {
                    $(this).show();
                }
            });
        },

        ordersNavDetach() {
            if (window.ordersNav === undefined) {
                window.ordersNav = $('#s-order-nav').detach();
            }
        }
    };
})(jQuery);
