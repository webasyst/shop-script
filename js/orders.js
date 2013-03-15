(function ($) {
    $.storage = new $.store();
    $.orders = {
        options: {
            view: 'table'      // default view
        },
        prevHash: null,
        hash: null,
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

        // if this is > 0 then this.dispatch() decrements it and ignores a call
        skipDispatch: 0,

        /** Cancel the next n automatic dispatches when window.location.hash changes */
        stopDispatch: function (n) {
            this.skipDispatch = n;
        },

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

        defaultAction: function () {
            this.ordersAction();
        },

        buildProductsUrlComponent: function(params) {
            var params_str = '';
            for (var name in params) {
                if (params.hasOwnProperty(name)) {
                    params_str += '&' + name + '=' + params[name];
                }
            }
            return 'view=' + (params.view || this.options.view) + params_str;
        },

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
            if (this.actionName !== 'orders' || !$.order_list) {
                if ($.order_list) {
                    $.order_list.finit();
                }
                this.load('?module=orders&'+this.buildProductsUrlComponent(params), function() {
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
            this.load('?module=order&id='+encodeURIComponent(id)+'&'+this.buildProductsUrlComponent(params), function() {
                $("#s-content").find('h1 .back.order-list').show();
            });
            if ($.order_list) {
                $.order_list.finit();
            }
        },

        initSearch: function() {
            var search_input = $("#s-orders-search");

            search_input.unbind('keydown').
                bind('keydown', function(event) {
                    if (event.keyCode == 13 || event.keyCode == 10) { // 'Enter'
                        var self = $(this);
                        var order_id = self.val();
                        if (order_id) {
                            location.hash = '#/order/'+order_id+'/';
                        }
                        self.autocomplete("close");
                        return false;
                    }
                });

            search_input.autocomplete({
                source: '?action=autocomplete&type=order',
                minLength: 1,
                delay: 300,
                html: true,
                select: function(event, ui) {
                    location.hash = '#/order/'+ui.item.id+'/';
                    search_input.val('');
                    return false;
                }
            });
        },

        onPageNotFound: function() {
            if ($.order_list) {
                $.order_list.finit();
            }
        }
    };
})(jQuery);