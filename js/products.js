(function ($) {
    $(function () {
        $("#s-content").on('click', '.s-alert-close', function () {
            var alerts = $.storage.get('shop/alerts');
            if (!alerts) {
                alerts = [];
            }
            alerts.push($(this).parent().data('alert'));
            $.storage.set('shop/alerts', alerts);
            $(this).parent().remove();
            return false;
        });
    });

    $.storage = new $.store();
    $.products = {
        hash: '',
        list_hash: '', // hash of last list
        list_params: {}, // params of last list
        options: {},
        random: '',
        init: function (options) {
            $.extend(this.options, options);
            this.initRouting();
            this.initSearch();
            $.product_sidebar.init();
            $.categories_tree.init();
            this.tagsHandler();
        },

        data: {
            'prev_action': null
        },

        initRouting: function () {
            if (typeof($.History) != "undefined") {
                $.History.bind(function () {
                    $.products.dispatch();
                });
            }
            $.wa.errorHandler = function (xhr) {
                if ((xhr.status === 403) || (xhr.status === 404)) {
                    var text = $(xhr.responseText);
                    if (text.find('.dialog-content').length) {
                        text = $('<div class="block double-padded"></div>').append(text.find('.dialog-content'));

                    } else {
                        text = $('<div class="block double-padded"></div>').append(text.find(':not(style)'));
                    }
                    $("#s-content").empty().append(text);
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
        },

        // Set hash without triggering dispatch
        skip_dispatch: 0,
        forceHash: function(hash) {
            if (hash != location.hash) {
                $.products.skip_dispatch++;
                $.wa.setHash(hash);
            }
        },

        dispatch: function (hash) {
            if ($.products.skip_dispatch > 0) {
                $.products.skip_dispatch--;
                return;
            }
            if (hash === undefined) {
                hash = window.location.hash;
            }

            hash = hash.replace(/(^[^#]*#\/*|\/$)/g, '');

            var beforeDispatchEvent = new $.Event('shop_before_dispatched');
            $(window).trigger(beforeDispatchEvent);
            if (beforeDispatchEvent.isDefaultPrevented()) {
                $.products.skip_dispatch = 1;
                window.location.hash = $.products.list_hash;
                return false;
            }

            /* fix syntax highlight */
            this.hash = hash;
            try {
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
                                } else if (actionName == 'product' || actionName == 'tag' || actionName == 'search' || actionName == 'plugins'
                                    || actionName == 'pages' || actionName == 'stocks') {
                                    attrMarker = i;
                                    break;
                                } else if (parseInt(h, 10) != h && h.indexOf('=') == -1) {
                                    actionName += h.substr(0, 1).toUpperCase() + h.substr(1);
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
                        if (typeof(this[actionName + 'Action']) === 'function') {
                            $.shop.trace('$.products.dispatch', [actionName + 'Action', attr]);
                            this[actionName + 'Action'].apply(this, attr);
                        } else {
                            $.shop.error('Invalid action name:', actionName + 'Action');
                        }
                    } else {
                        this.preExecute();
                        this.defaultAction();
                    }
                } else {
                    this.preExecute();
                    this.defaultAction();
                }
            } catch (e) {
                $.shop.error(e.message, e);
            }
        },

        load: function (url, callback) {
            var r = Math.random();
            this.random = r;
            var self = this;
            $.get(url, function (result) {
                if (self.random != r) {
                    // too late: user clicked something else.
                    return;
                }
                $("#s-content").removeClass('bordered-left').html(result);
                $('html, body').animate({
                    scrollTop: 0
                }, 200);
                if (callback) {
                    try {
                        callback.call(this);
                    } catch (e) {
                        $.shop.error('$.products.load callback error: ' + e.message, e);
                    }
                }
                $(document).trigger("wa_loaded");
            });
        },

        addOptions: function (options) {
            this.options = $.extend(this.options, options || {});
        },

        preExecute: function (action, args) {
            try {
                if (this.data.prev_action && (this.data.prev_action != action)) {
                    var actionName = this.data.prev_action + 'Termination';
                    $.shop.trace('$.products.preExecute', [actionName, action]);
                    if (typeof(this[actionName]) == 'function') {
                        this[actionName].apply(this, []);
                    }
                }
                this.data.prev_action = action;

                // Clear "min-width" fix /  $.product_list.rubberTable();
                ( function() {
                    var wrapper = $("#wa"),
                        old_style = wrapper.data("style");

                    if ( old_style ) {
                        wrapper.attr("style", old_style);
                    } else {
                        wrapper.removeAttr("style");
                    }

                    $(window).resize();

                })();

                $('body > .dialog').trigger('close').remove();

            } catch (e) {
                $.shop.error('preExecute error: ' + e.message, e);
            }
        },

        defaultAction: function () {
            this.productsAction();
        },

        welcomeAction: function () {
            this.load('?module=products&action=welcome');
        },

        paramsFromSession: function(params) {
            if (!window.sessionStorage || !window.JSON || !window.JSON.stringify || !window.JSON.parse) {
                return params;
            }

            if (params.category_id) {
                storage = sessionStorage.getItem('shop/list_categories') || '{}';
                try {
                    storage = JSON.parse(storage);
                } catch (e) {
                    storage = {};
                }

                var prms = storage[params.category_id] = storage[params.category_id] || {};
                $.each(['sort', 'order'], function(i, key) {
                    if (params[key] === undefined) {
                        params[key] = prms[key];
                    } else {
                        prms[key] = params[key];
                    }
                });

                try {
                    sessionStorage.setItem('shop/list_categories', JSON.stringify(storage));
                } catch (e) { }
            }

            return params;

            // Note that list view (thumbs or table) is stored server-side in PHP,
            // as well as sort and order for 'All Products' view. Mostly for legacy reasons.
        },

        buildProductsUrlComponent: function (params) {

            params = this.paramsFromSession(params);

            return ((params.view ? '&view=' + params.view : '')
                + (params.category_id ? '&category_id=' + params.category_id : '')
                + (params.set_id ? '&set_id=' + params.set_id : '')
                + (params.tag ? '&tag=' + params.tag : '')
                + (params.sort ? '&sort=' + params.sort : '')
                + (params.order ? '&order=' + params.order : '')
                + (params.text ? '&text=' + params.text : '')
                + (params.edit ? '&edit=' + params.edit : '')
                + (params.hash ? '&hash=' + params.hash : '')
                + (params.page ? '&page=' + params.page : '')
                + (params.type_id ? '&type_id=' + params.type_id : '')).
                slice(1) // cut of first '&'
            ;
        },

        productsAction: function () {
            var params = Array.prototype.join.call(arguments, '/');
            params = $.shop.helper.parseParams(params || '');
            this.list_hash = this.hash;
            this.list_params = params;

            if ($.product_list !== undefined && $.product_list.fixed_blocks !== undefined) {
                if ($.product_list.fixed_blocks.set) {
                    $.product_list.fixed_blocks.set.unsetFixed();
                }
                if ($.product_list.fixed_blocks.category) {
                    $.product_list.fixed_blocks.category.unsetFixed();
                }
            }
            window.location.href = this.options.shop_url + 'products/';
        },

        checkAlerts: function () {
            var alerts = $.storage.get('shop/alerts');
            $('.s-alert').each(function () {
                if ($.inArray($(this).data('alert'), alerts) == -1) {
                    $(this).show();
                }
            });
        },

        productAction: function (id, action, tab) {
            if (typeof tab !== 'undefined' && tab === 'force-old') {
                tab = 'main/'+ tab;
            }
            var path = Array.prototype.slice.call(arguments).filter(function (chunk) {
                return chunk.length;
            }).join('/');
            $.shop.trace('$.products.productAction', [path, arguments]);
            var url = '?module=product';
            if (id) {
                url += '&id=' + id;
            }
            if (typeof($.product) != 'undefined') {
                $.product.dispatch(path);
            } else {
                this.load(url, function (response) {
                    $.product.dispatch(path);
                });
            }
        },

        productTermination: function () {
            if (typeof($.product) != 'undefined') {
                $.product.termination();
            }
        },

        reviewsAction: function(params_string, callback) {
            var request_uri = "?module=reviews" + (params_string ? "&" + params_string : "");

            this.load(request_uri);
        },

        stocksAction: function (path, params) {
            switch (path) {
                case 'log':
                    if (params && isInt(params)) {
                        params = 'stock_id=' + params;
                    }
                    this.load('?module=stocks&action=log' + (params ? '&' + params : ''));
                    break;

                case 'transfers':
                    this.load('?module=stocks' + (params ? '&' + params : ''));
                    break;

                default:
                    var stock_id = path && isInt(path) ? path : null;
                    if (stock_id) {
                        order = params;
                    } else {
                        order = path;
                    }

                    var sort = '';
                    if (!order || order === 'desc' || order === 'asc') {
                        sort = 'count';
                    } else {
                        order = String(order || '');
                        var m = order.match(/stock_count_(\d*)_*(desc|asc)*/);
                        if (m) {
                            sort = 'stock_count_' + m[1];
                            order = m[2] === 'desc' ? 'desc' : 'asc';
                        }
                    }

                    var params_str = [
                        ...(stock_id ? ['&stock_id='+stock_id] : []),
                        ...(order ? ['&order='+order]  : []),
                        ...(sort ? ['&sort='+sort]  : [])
                    ].join('');
                    this.load('?module=stocks&action=balance' + params_str);
                    break;
            }

            function isInt (value) {
                var x = parseFloat(value);
                return !isNaN(value) && (x | 0) === x;
            }
        },

        transferInfo: function (transfer_id) {
            return $.get('?module=transferInfo&id=' + transfer_id);
        },

        /*transfersAction: function (params) {
            this.load('?module=stocks' + (params ? '&' + params : '') + '&tab=transfers');
            //this.load('?module=transferList' + (params ? '&' + params : ''));
        },*/

        servicesAction: function (id) {
            this.load('?module=services' + (id ? '&id=' + id : ''), function () {
                $("#s-content").addClass('bordered-left');
                if (typeof $.products.afterServicesAction === 'function') {
                    $.products.afterServicesAction();
                }
            });
        },

        tagsHandler:function () {
                $('#s-products-all-tags').autocomplete({
                    source: '?module=product&action=tagsAutocomplete&type=search',
                    minLength: 1,
                    delay: 300,
                    select: function(event, ui) {
                        $.wa.setHash('#/products/tag=' + ui.item.value);
                        return false;
                    }
                });
        },

        initSearch: function () {
            var search = function () {
                // encodeURIComponent ?..
                $.wa.setHash('#/products/'+($.products.list_params && $.products.list_params.view ? 'view='+$.products.list_params.view+'&' : '')+'text='+encodeURIComponent(this.value));
            };

            var $products_search = $('#s-products-search');

            // HTML5 search input search-event isn't supported
            $products_search.unbind('keydown').bind('keydown', function (event) {
                if (event.keyCode == 13) { // 'Enter'
                    search.call(this);

                    var self = $(this);
                    self.autocomplete("close");
                    // sometimes "close" has done earlier than list has shown
                    setTimeout(function () {
                        self.autocomplete("close");
                    }, 300);

                    return false;
                }
            });

            $products_search.unbind('search').bind('search', function () {
                search.call(this);
                return false;
            });

            $products_search.autocomplete({
                source: '?action=autocomplete',
                minLength: 3,
                delay: 300,
                select: function (event, ui) {
                    $products_search.val('');
                    $.wa.setHash('#/product/' + ui.item.id);

                    return false;
                }
            });
        },

        jsonPost: function (url, data, success, error) {
            $.shop.jsonPost(url, data, success, error);
        },

        _iframePost: function (form, success, error) {
            var form_id = form.attr('id');
            var iframe_id = form_id + '-iframe';

            // add hidden iframe if need
            if (!$('#' + iframe_id).length) {
                form.after("<iframe id=" + iframe_id + " name=" + iframe_id + " style='display:none;'></iframe>");
            }

            var iframe = $('#' + iframe_id);
            form.attr('target', iframe_id);

            iframe.one('load', function () {
                var r;
                try {
                    var data = $(this).contents().find('body').html();
                    r = $.parseJSON(data);
                } catch (e) {
                    error(data);
                    return;
                }
                if (r.status == 'ok') {
                    success(r);
                } else {
                    error(r);
                }
            });
        }

    };
})(jQuery);
