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
            $.wa.errorHandler = function (xhr) {
                if ((xhr.status === 403) || (xhr.status === 404) ) {
                    var text = $(xhr.responseText);
                    if (text.find('.dialog-content').length) {
                        text = $('<div class="block double-padded"></div>').append(text.find('.dialog-content *'));

                    } else {
                        text = $('<div class="block double-padded"></div>').append(text.find(':not(style)'));
                    }
                    $("#s-content").empty().append(text);
                    $.orders.onPageNotFound();
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

            // sync with app counters
            $(document).bind('wa.appcount', function(event, data) {
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
        },

        onPageNotFound: function() {
            if ($.order_list) {
                $.order_list.finit();
            }
        },

        initSearch: function() {
            var search_input = $("#s-orders-search");

            var autocomplete_url = '?action=autocomplete&type=order';
            var last_response = [];

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
                    default:
                        // search
                        break;
                }
            };

            search_input.unbind('keydown').
                bind('keydown', function(event) {
                    if (event.keyCode == 13 || event.keyCode == 10) { // 'Enter'
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
                    $.getJSON(autocomplete_url, request, function(r) {
                        last_response = r;
                        response(r);
                    });
                }
            });
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

        ordersNewAction: function () {
            this.load('?module=order&action=edit', function() {
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
                    params = "state_id=new|processing|paid";
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
                $("#s-content").find('h1 .back.order-list').show();
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
                $('html, body').animate({scrollTop:0}, 200);
                $('.level2').show();
                $('#s-sidebar').width(200).show();
            });
        }
    };
})(jQuery);