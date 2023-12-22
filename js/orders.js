(function ($) {
    $.storage = new $.store();
    $.orders = {
        $wrapper: null,
        pre_search_hash: null,
        search_item_param: null,
        options: {
            view: 'table'      // default view
        },

        //
        // Init-related
        //

        init: function (options) {

            var that = this;
            that.options = options;
            that.$wrapper = $('#s-content');

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
                        text = $('<div class="box contentbox"></div>').append(text.find('.dialog-content'));

                    } else {
                        text = $('<div class="box contentbox"></div>').append(text.find(':not(style)'));
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
            if (!hash || hash === '#/orders/') {
                hash = '/orders/' + this.getFiltersHashStorage();
            }

            if (hash === '#/' || !hash) {
                this.dispatch();
            } else {
                $.wa.setHash(hash);
            }

            this.initEventsVisibilityFilters();

            this.initDropdown();

            // sync with app counters
            $(document).bind('wa.appcount', function(event, data) {
                // data could be undefined
                data = data || {};

                var cnt = parseInt(data.shop, 10);

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
            this.initWaLoading();

            this.$wrapper.on('wa_init_orders_nav_before', (e, options) => {
                if (['orders/all', 'orders/state_id=new|processing|auth|paid'].includes(this.hash)) {
                    this.setFiltersHashStorage(this.hash);
                }
            });

            this.$wrapper.on('wa_init_orders_nav_after', (e, options) => {
                this.initSearch(options.filter_params_extended);

                if (this.hasFiltersInUrl()) {
                    if (options.view === 'split') {
                        this.visibilityFilters(true);
                    }
                }
            });
        },

        initEventsVisibilityFilters: function() {
            $(document).on('click', '.js-orders-show-filters', () => {
                this.visibilityFilters(true);
                this.clearFilters();
            });

            $(document).on('click', '.js-orders-hide-filters', () => {
                this.visibilityFilters(false);
                this.clearFilters();
            });
        },

        initSearch: function(filter_params_extended) {
            var that = this;
            var $search_input = $("#s-orders-search");
            var autocomplete_url = '?action=autocomplete&type=order';
            var last_response = [];

            var search_xhr = null;

            var onSelect = function(autocomplete_item) {
                if (!that.pre_search_hash) {
                    that.pre_search_hash = window.location.hash;

                    if ($.order_list.options && $.order_list.options.view === 'split') {
                        that.clearFilters();
                    }
                }

                switch (autocomplete_item.autocomplete_item_type) {
                    case 'order':
                        that.search_item_param = null;
                        $.wa.setHash('#/order/' + autocomplete_item.id + '/');
                        $search_input.val(autocomplete_item.value);
                        break;
                    case 'contact':
                        that.search_item_param = 'contact_id=' + autocomplete_item.id;
                        $.wa.setHash('#/orders/' + that.search_item_param + '/');
                        $search_input.val(autocomplete_item.value);
                        break;
                    case 'product':
                        that.search_item_param = 'product_id=' + autocomplete_item.id;
                        $.wa.setHash('#/orders/' + that.search_item_param + '/');
                        $search_input.val(autocomplete_item.value);
                        break;
                    case 'coupon':
                        that.search_item_param = 'coupon_id=' + autocomplete_item.id;
                        $.wa.setHash('#/orders/' + that.search_item_param + '/');
                        break;
                    case 'tracking_number':
                        that.search_item_param = 'tracking_number=' + autocomplete_item.value;
                        $.wa.setHash('#/orders/' + that.search_item_param + '/');
                        break;
                    case 'shipping':
                        that.search_item_param = 'shipping_id=' + autocomplete_item.id;
                        $.wa.setHash('#/orders/' + that.search_item_param + '/');
                        break;
                    case 'payment':
                        that.search_item_param = 'payment_id=' + autocomplete_item.id;
                        $.wa.setHash('#/orders/' + that.search_item_param + '/');
                        break;
                    case 'city':
                        that.search_item_param = 'city=' + autocomplete_item.value;
                        $.wa.setHash('#/orders/' + that.search_item_param + '/');
                        break;
                    case 'region':
                        that.search_item_param = 'region=' + ((autocomplete_item.value || '').split(':')[1] || '');
                        $.wa.setHash('#/orders/' + that.search_item_param + '/');
                        break;
                    case 'country':
                        that.search_item_param = 'country=' + autocomplete_item.value;
                        $.wa.setHash('#/orders/' + that.search_item_param + '/');
                        break;
                    case 'item_code':
                        that.search_item_param = 'item_code=' + autocomplete_item.value;
                        $.wa.setHash('#/orders/' + that.search_item_param + '/');
                        break;
                    default:
                        // search
                        that.pre_search_hash = null;
                        break;
                }
            };

            $search_input.unbind('keydown').
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

            $search_input.autocomplete({
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

                    $.orders.waLoadingBeforeLoad();
                    search_xhr = $.getJSON(autocomplete_url, request, (r) => {
                        last_response = r;
                        response(r);
                        $.orders.waLoadingLoaded();
                    }).always(() => {
                        search_xhr = null;
                    });
                }
            });

            const searchShowSelector = '.js-orders-search-show';
            const searchHideSelector = '.js-orders-search-hide';
            const $wrapper = $('.js-orders-search');
            const hideSearchIcon = () => {
                if ($wrapper.hasClass('js-orders-search-split-and-mobile')) {
                    $(searchShowSelector).removeClass('hidden');
                } else {
                    $(searchShowSelector).addClass('hidden');
                }
            };
            const hideSearchInput = () => {
                $(searchShowSelector).removeClass('hidden')
                $wrapper.removeClass('show');
                $wrapper.removeClass('hide');
                $wrapper.addClass('hidden');
            };

            if (filter_params_extended && typeof filter_params_extended === "object") {
                if (Array.isArray(filter_params_extended)) {
                    $search_input.val('');
                    hideSearchInput();
                } else {
                    let hash = that.pre_search_hash || window.location.hash;
                    for (let key in filter_params_extended) {
                        const param = filter_params_extended[key];
                        that.search_item_param = `${key}=${param.id}`;
                        that.pre_search_hash = hash.replace(`/${that.search_item_param}`, '');

                        // add only first value to search input
                        hideSearchIcon();
                        $search_input.val(param.name);
                        $wrapper.addClass('show').removeClass('hidden');
                        break;
                    }
                }
            }

            // show search
            $(document).on('click', searchShowSelector, function(e) {
                e.preventDefault();

                hideSearchIcon();
                $wrapper.removeClass('hidden');
                setTimeout(() => {
                    $wrapper.addClass('show');
                    $search_input.focus();
                }, 5)
            });

            // hide search
            $(document).on('click', searchHideSelector, function(e) {
                e.preventDefault();
                if (!$wrapper.hasClass('show')) {
                    return false;
                }

                $wrapper.addClass('hide');
                $search_input.val('');
                setTimeout(() => hideSearchInput(), 180);

                if (that.pre_search_hash && that.search_item_param) {
                    let prevHash = that.pre_search_hash;
                    if ($.order_list.options.view && prevHash.includes('view=')) {
                        prevHash = prevHash.replace(new RegExp('view=(split|table|kanban)'), `view=${$.order_list.options.view}`)
                    }
                    if (prevHash.includes(that.search_item_param)) {
                        prevHash = '#/orders/';
                    }

                    $.wa.setHash(prevHash);
                    that.search_item_param = null;
                    that.pre_search_hash = null;
                }
            });
        },

        initDropdown: function() {
            const that = this;
            const search_hash = 'hash=search';
            const allowed_filters_mask = ['params.', 'state_id=', 'courier_contact_id='];
            const states_processing = 'state_id=new|processing|auth|paid';

            const highlightedButton = (dropdown) => {
                dropdown.$button.css({
                    'background': 'var(--highlighted-blue)'
                })
            };
            const removeLastSlash = (hash) => (hash && hash.slice(-1) === '/' ? hash.slice(0, -1) : hash);
            const emptyHashSearch = (params) => params.every(param => !(allowed_filters_mask.some(key => param.startsWith(key))));
            let computedEmptyFilters = false;

            $('.js-orders-dropdown').waDropdown({
                hover: false,
                items: ".menu > li > a",
                ready(dropdown) {
                    let hash = removeLastSlash(window.location.hash);
                    if (!hash || !hash.includes(search_hash)) {
                        const hash_from_storage = decodeURIComponent(that.getFiltersHashStorage());
                        if (hash_from_storage) {
                            hash = hash_from_storage;
                        }
                    }
                    if (hash) {
                        let params = decodeURIComponent(hash)
                            .replace(new RegExp(`#(\\/?)orders(\\/?)|${search_hash}\\/$`, 'g'), "")
                            .split('&')
                            .filter(Boolean);

                        if (params.length) {
                            if (computedEmptyFilters || emptyHashSearch(params)) {
                                computedEmptyFilters = true;
                                return false;
                            }

                            const $orders_links = dropdown.$menu.find('.js-orders-link');
                            if ($orders_links.length) {
                                $orders_links.each(function () {
                                    const $item = $(this);
                                    if(params.some(item => item == $item.data('param'))) {
                                        dropdown.$button.html($item.html());
                                        highlightedButton(dropdown);
                                    }

                                    if (params.some(item => decodeURIComponent($item.attr('href')).includes(item))) {
                                        dropdown.$button.html($item.html());
                                        highlightedButton(dropdown);
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
                change(event, target, dropdown) {
                    const $target = $(target);
                    const sales_channels = ['params.storefront', 'params.sales_channel'];
                    let hash = removeLastSlash(window.location.hash);
                    let param = $target.data('param');
                    let param_is_clear = false;

                    // если хэш уже имеет замиксованные параметры и отсутствует явный параметр у выбранного фильтра
                    if (hash.includes(search_hash) && !param) {
                        param = decodeURIComponent($target.attr('href').replace(/#\/orders\/withoutSearch|\/$/g, ''))
                    }

                    if (param) {
                        // remove parameter from search query
                        if (typeof hash === "string" && hash.includes(that.search_item_param)) {
                            hash = hash.replace(that.search_item_param, '');
                            that.pre_search_hash = that.search_item_param = null;
                            $('.js-orders-search-hide').trigger('click');
                        }

                        // готовим массив параметров
                        const params = decodeURIComponent(hash)
                            .replace(new RegExp(`#(\\/?)orders(\\/?)|${search_hash}\\/|&id=\\d+|$`, 'g'), "")
                            .split('&')
                            .filter(Boolean);

                        const state_processing_index = params.findIndex(item => item === states_processing);
                        if (state_processing_index !== -1) {
                            params.splice(state_processing_index, 1);
                        }

                        // если среди параметров есть существующий, но с другим значением, то обновляем значение
                        const [_param, _value] = param.split('=');
                        const existed_param_key = params.findIndex(item => item.includes(_param));
                        param_is_clear = _param === 'all';
                        if (param_is_clear) {
                            const values = _value.split('|');
                            let has_param_filter = false;

                            values.forEach(val => {
                                params.forEach((param, index) => {
                                    if (param.startsWith(val)) {
                                        has_param_filter = true;
                                        params.splice(index, 1);
                                    }
                                });
                            });

                            that.clearFilter(dropdown.$wrapper);

                            if (dropdown.$before) {
                                dropdown.$before.parent().removeClass('selected');
                            }

                            if (!has_param_filter) {
                                return false;
                            }

                        } else {
                            if (existed_param_key !== -1) {
                                params[existed_param_key] = _param + '=' + _value
                            } else {
                                params.push(param)
                            }

                            $('.js-remove-filters-link').toggleClass('hidden', false);

                            $('.js-order-nav-brick').each(function () {
                                $(this).removeClass('selected');
                            });
                        }

                        // find and remove a courier of another parameter
                        let existed_courier_id = -1;
                        if (_param === 'courier_contact_id') {
                            existed_courier_id = params.findIndex(item => item.includes('params.courier_id'));
                        } else if (_param === 'params.courier_id') {
                            existed_courier_id = params.findIndex(item => item.includes('courier_contact_id'));
                        }
                        if (existed_courier_id !== -1) {
                            params.splice(existed_courier_id, 1);
                        }

                        // оставляем в параметрах только один какой-то канал продаж
                        params.reduceRight((channel_last_index, item, index) => {

                            if (sales_channels.some(channel => item.includes(channel))) {
                                if (channel_last_index !== null) {
                                    params.splice(index, 1);
                                } else {
                                    channel_last_index = index;
                                }
                            }

                            return channel_last_index;
                        }, null);

                        const params_filters = [];
                        const params_sorts = params.filter(param => {
                            if ((new RegExp('^sort\\[\\d+\\]')).test(param)) {
                                return true;
                            }

                            // filter parameters only
                            if (allowed_filters_mask.some(filter_mask => param.startsWith(filter_mask))) {
                                params_filters.push(param);
                            }

                            return false;
                        });

                        let params_filters_str = params_filters.join('&');
                        if (params_filters.length) {
                            params_filters_str = '&' + params_filters_str;
                        }
                        params_filters_str = encodeURIComponent(params_filters_str);

                        let params_sorts_str = '';
                        if (params_sorts.length) {
                            params_sorts_str = '&' + params_sorts.join('&');
                        }

                        let _hash = '';
                        if (emptyHashSearch(params_filters)) {
                            _hash += 'all/';
                            that.clearFilters();
                        } else {
                            _hash += `${search_hash}/${params_filters_str}${params_sorts_str}/`
                        }

                        that.setFiltersHashStorage(_hash);
                        $.wa.setHash(`/orders/${_hash}`);

                    } else {
                        location.replace($target.attr('href'));
                    }

                    if (!param_is_clear) {
                        highlightedButton(dropdown);
                        dropdown.$button.html($target.html());
                    }
                }
            });

            $('.js-remove-filters-link').on('click', () => {
                this.clearFilters();
            })
        },

        visibilityFilters: function(state) {
            const $filters_wrapper = $('.js-order-nav-block-secondary');

            if (state) {
                $filters_wrapper.show();
            } else {
                $filters_wrapper.hide();
            }
        },

        clearFilter: function($dropdown) {
            $btn = $dropdown
                .find('.dropdown-toggle')
                .removeAttr('style');

            const text = $btn.data('text');
            if (text) {
                $btn.text(text);
            }
        },

        clearFilters: function() {
            $('.js-orders-dropdown').each((i, dropdown) => {
                this.clearFilter($(dropdown));
            });

            $('.js-remove-filters-link').addClass('hidden');
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
            if (!params) {
                arguments = this.argsLastFilters();
                if (arguments[0] === 'all') {
                    arguments = [];
                    params = 'all';
                }
            }

            let show_filters = true;
            const states_processing = "new|processing|auth|paid";
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
                    params = `state_id=${states_processing}`;
                } else if (params === 'all' || params === 'all&view=split') {
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

            show_filters = params && params.state_id !== states_processing;
            if (this.actionName !== 'orders' || !$.order_list) {
                if ($.order_list) {
                    $.order_list.finit();
                }
                this.load('?module=orders&'+this.buildOrdersUrlComponent(params), () => {
                    $(window).trigger('wa_loaded', [['table', 'kanban']]);
                    if ($.order_list) {
                        $.order_list.dispatch(params);
                    }
                    this.visibilityFilters(show_filters);
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


            return $.ajax({
                method: 'GET',
                url: url,
                global: false,
                cache: false,
                xhr: function() {
                    var xhr = new window.XMLHttpRequest();

                    xhr.addEventListener("progress", function(event) {
                        self.$wrapper.trigger("wa_loading.wa_loading", event);
                    }, false);

                    xhr.addEventListener("abort", function(event) {
                        self.$wrapper.trigger("wa_abort.wa_loading");
                    }, false);

                    return xhr;
                }
            })
            .done(function(result) {
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

                showOrdersSortMenu();

                self.checkAlerts();

                self.waLoadingLoaded()
            })
            .fail( function(data) {
                if (data.responseText) {
                    if (data.status === 403) {
                        $.wa.errorHandler(data);
                    } else {
                        console.error(data.responseText);
                    }
                }
                self.$wrapper.trigger("wa_load_fail.wa_loading");
            });

            function showOrdersSortMenu() {
                const $ordersSortMenu = $('.js-orders-sort');
                const is_orders_page = $("#s-order-nav").length;

                if ($ordersSortMenu.length) {
                    if (is_orders_page) {
                        $ordersSortMenu.removeClass('hidden');
                    } else {
                        $ordersSortMenu.addClass('hidden');
                    }
                }
            }
        },


        checkAlerts() {
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
        },

        initWaLoading() {
            const waLoading = $.waLoading();

            const $wrapper = $("#wa"),
                locked_class = "is-locked";

            this.$wrapper
                .on("wa_before_load.wa_loading", function() {
                    waLoading.show();
                    waLoading.animate(10000, 95, false);
                    $wrapper.addClass(locked_class);
                })
                .on("wa_loading.wa_loading", function(event, xhr_event) {
                    const percent = (xhr_event.loaded / xhr_event.total) * 100;
                    waLoading.set(percent);
                })
                .on("wa_abort.wa_loading", function() {
                    waLoading.abort();
                    $wrapper.removeClass(locked_class);
                })
                .on("wa_loaded.wa_loading", function() {
                    waLoading.done();
                    $wrapper.removeClass(locked_class);
                });
        },

        waLoadingBeforeLoad() {
            this.$wrapper.trigger("wa_before_load.wa_loading");
        },
        waLoadingLoaded() {
            this.$wrapper.trigger("wa_loaded.wa_loading", [['waLoading']]);
        },

        hasFiltersInUrl() {
            return window.location.hash ? window.location.hash.includes('hash=search') : false;
        },

        setFiltersHashStorage (hash) {
            if (hash) {
                $.storage.set('shop/orders/filters_hash', hash.replace('orders/', ''));
            }
        },

        getFiltersHashStorage () {
            let filters_hash = $.storage.get('shop/orders/filters_hash');
            if (filters_hash) {
                return filters_hash.replace('orders/', '');
            }

            return '';
        },

        argsLastFilters () {
            let args = [];
            const hash_from_storage = this.getFiltersHashStorage();
            if (hash_from_storage) {
                args = hash_from_storage.split('/').filter(Boolean);

                $.wa.setHash('/orders/' + hash_from_storage);
            }

            return args;
        },

        orderNav () {
            const $order_nav = $('#s-order-nav');
            const class_hide = 'hidden';
            return {
                hide () {
                    $order_nav.addClass(class_hide);
                },
                show () {
                    $order_nav.removeClass(class_hide);
                }
            }
        }
    };
})(jQuery);
