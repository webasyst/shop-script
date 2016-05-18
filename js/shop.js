(function($) {

    var default_error_handler = function(r) {
        if (console) {
            if (r && r.errors) {
                console.error(r.errors);
            } else if (r && r.responseText) {
                console.error(r.responseText);
            } else if (r) {
                console.error(r);
            } else {
                console.error('Error on query');
            }
        }
    };

    $.shop = {
        options: {
            'debug': true
        },
        time: {
            start: new Date(),
            /**
             * @return int
             */
            interval: function(relative) {
                var d = new Date();
                return (parseFloat(d - this.start) / 1000.0 - (parseFloat(relative) || 0)).toFixed(3);
            }
        },
        init: function(options) {
            this.options = $.extend(this.options, options || {});

            if (options["menu_floating"]) {
                initFixedMenu();

                $('body').on('click', 'a.js-print', function() {
                    return $.shop.helper.print(this);
                });
            }

            if (options.page != 'orders') {
                // sync mainmenu orders count with app counter
                $(document).bind('wa.appcount', function(event, data) {
                    $.shop.updateOrdersCounter(parseInt(data.shop, 10) || 0);
                });
            }

            function initFixedMenu() {
                // DOM
                var $window = $(window),
                    $app = $("#wa-app"),
                    $main_menu = $('#mainmenu');

                // VARS
                var app_top = getAppTop();

                // EVENTS
                $window
                    .on("scroll", onScroll)
                    .on("resize", onResize);

                $("#wa-moreapps").on("click", function() {
                    setTimeout( function() {
                        onResize();
                    }, 4);
                });

                // FUNCTIONS

                function getScrollTop() {
                    return $window.scrollTop();
                }

                function getAppTop() {
                    return $app.offset().top;
                }

                function onScroll() {
                    var scroll_top = getScrollTop(),
                        fixed_class = "s-fixed";

                    if ( scroll_top > app_top) {
                        $main_menu.addClass(fixed_class);
                    } else {
                        $main_menu.removeClass(fixed_class);
                    }
                }

                function onResize() {
                    app_top = getAppTop();
                    onScroll();
                }
            }
        },

        /**
         * @param {Array} args
         * @param {Object} scope
         * @param {String=} name
         * @return {'name':{String},'params':[]}
         */
        getMethod: function(args, scope, name) {
            var chunk, callable;
            var method = {
                'name': false,
                'params': []
            };
            if (args.length) {
                $.shop.trace('$.getMethod', args);
                name = name || args.shift();
                while (chunk = args.shift()) {
                    name += chunk.substr(0, 1).toUpperCase() + chunk.substr(1);
                    callable = (typeof(scope[name]) == 'function');
                    $.shop.trace('$.getMethod try', [name, callable, args]);
                    if (callable === true) {
                        method.name = name;
                        method.params = args.slice(0);
                    }
                }
            }
            return method;
        },
        /**
         * Debug trace helper
         *
         * @param String message
         * @param {} data
         */
        trace: function(message, data) {
            var timestamp = null;
            if (this.options.debug && console) {
                timestamp = this.time.interval();
                console.log(timestamp + ' ' + message, data);
            }
            return timestamp;
        },

        /**
         * Handler error messages
         *
         * @param String message
         * @param {} data
         */
        error: function(message, data) {
            if (console) {
                console.error(message, data);
            }
        },

        jsonPost: function(url, data, success, error) {
            if (typeof data === 'function') {
                success = data;
                error = success;
            }
            var xhr = $.post(url, data, function(r) {
                if (r.status != 'ok') {
                    if (typeof error === 'function') {
                        if (error(r) !== false) {
                            default_error_handler(r);
                        }
                    } else {
                        default_error_handler(r);
                    }
                    return;
                }
                if (typeof success === 'function') {
                    success(r);
                }
            }, 'json');
            if (typeof error === 'function') {
                xhr.error(function(r) {
                    if (error(r) !== false) {
                        default_error_handler(r);
                    }
                });
            } else {
                xhr.error(default_error_handler);
            }
            return xhr;
        },

        getJSON: function(url, data, success, error) {
            if (typeof data !== 'object') {
                success = data;
                error = success;
            }
            var xhr = $.ajax({
                url: url,
                dataType: 'json',
                data: data,
                success: function(r) {
                    if (r.status != 'ok') {
                        if (typeof error === 'function') {
                            if (error(r) !== false) {
                                default_error_handler(r);
                            }
                        } else {
                            default_error_handler(r);
                        }
                        return;
                    }
                    if (typeof success === 'function') {
                        success(r);
                    }
                }
            });
            if (typeof error === 'function') {
                xhr.error(function(r) {
                    if (error(r) !== false) {
                        default_error_handler(r);
                    }
                });
            } else {
                xhr.error(default_error_handler);
            }
            return xhr;
        },

        updateOrdersCounter: function(count) {
            count = parseInt(count, 10) || '';
            var counter = $('#mainmenu-orders-tab').find('sup');
            counter.text(count);
            if (count) {
                counter.show();
            } else {
                counter.hide();
            }
        },

        updateAppCounter: function(count) {
            count = parseInt(count, 10) || '';
            var counter = $('#wa-app-shop').find('.indicator');
            if (!counter.length) {
                $('#wa-app-shop').find('a').append('<span class="indicator" style="display:none;"></span>');
                counter = $('#wa-app-shop').find('.indicator');
            }
            counter.text(count);
            if (count) {
                counter.show();
            } else {
                counter.hide();
            }
        },

        /**
         * @param jQuery object el
         * @param function|string handler
         * @param function|string delegate_context
         * @returns jQuery object el
         */
        changeListener: function(el, handler, delegate_context) {

            if (typeof delegate_context === 'function' && typeof handler === 'string') {
                var swap = delegate_context;
                delegate_context = handler;
                handler = swap;
            }

            var timeout = 450;
            var timer_id = null;
            var ns = 'change_listener';
            var keydown_handler = function() {
                var item = this;
                if (timer_id) {
                    clearTimeout(timer_id);
                    timer_id = null;
                }
                timer_id = setTimeout(function() {
                    handler.call(item, el);
                }, timeout);
            };
            var change_handler = function() {
                handler.call(this, el);
            };
            if (delegate_context) {
                el.on('keydown.' + ns, delegate_context, keydown_handler)
                    .on('change.' + ns, delegate_context, change_handler);
            } else {
                el.bind('keydown.' + ns, keydown_handler)
                    .bind('change.' + ns, change_handler);
            }
            return el;
        },

        /**
        * Make input (or textarea) with field_id flexible,
        * what means that depends on length and threshold this field turn into input or textarea and back
        *
        * @param String field_id
        * @param Number threshold (default 50)
        */
        makeFlexibleInput: function(field_id, threshold) {
            var timeout = 250;
            threshold = threshold || 50;
            var height = 45;
            var timer_id = null;
            field_id = '#' + field_id;
            var field = $(field_id);

            var onFocus = function() {
                this.selectionStart = this.selectionEnd = this.value.length;
            };
            var handler = function() {
                if (timer_id) {
                    clearTimeout(timer_id);
                    timer_id = null;
                }
                timer_id = setTimeout(function() {
                    var val = field.val();
                    if (val.length > threshold && field.is('input')) {
                        var textarea = $.shop.input2textarea(field);
                        textarea.css('height', height);
                        field.replaceWith(textarea);
                        field = textarea;
                        field.focus();
                    } else if (val.length <= threshold && field.is('textarea')) {
                        var input = $.shop.textarea2input(field);
                        input.css('height', '');
                        field.replaceWith(input);
                        field = input;
                        field.focus();
                    }
                }, timeout);
            };

            var p = field.parent();
            p.off('keydown', field_id).
                on('keydown',  field_id, handler);
            p.off('focus',    field_id).
                on('focus',     field_id, onFocus);

            // initial shot
            handler();
        },

        input2textarea: function(input) {
            var p = input.parent();
            var rm = false;
            if (!p.length) {
                p = $('<div></div>');
                p.append(input);
                rm = true;
            }
            var val = input.val();

            var html = p.html();
            html = html.replace(/value(\s*?=\s*?['"][\s\S]*?['"])*/, '');
            html = html.replace(/type\s*?=\s*?['"]text['"]/, '');
            html = html.replace('input', 'textarea');
            html = html.replace(/(\/\s*?>|>)/, '>' + val  + '</textarea>');

            if (rm) {
                p.remove();
            }

            return $(html);

        },

        textarea2input: function(textarea) {
            var p = textarea.parent();
            var rm = false;
            if (!p.length) {
                p = $('<div></div>');
                p.append(textarea);
                rm = true;
            }
            var val = textarea.val();

            var html = p.html();
            html = html.replace('textarea', 'input type="text" value="' + val + '"');
            html = html.replace('</textarea>', '');

            if (rm) {
                p.remove();
            }

            return $(html);
        },

        confirmLeave: function (options) {
            var keepListen = options.keepListen;
            var confirmIf = options.confirmIf;
            var message = options.message || '';
            var ns = options.ns || ('shop-confirm-leave-' + ('' + Math.random()).slice(2));
            var win = $(window);

            var stop = function () {
                win.off('.' + ns);
            };

            stop();

            win.on('beforeunload.' + ns, function (e) {
                if (!keepListen || !keepListen()) {
                    stop();
                    return;
                }
                if (!confirmIf || !confirmIf()) {
                    return;
                }
                return message;
            });

            win.on('shop_before_dispatched.' + ns, function (e, options) {
                if (!keepListen || !keepListen()) {
                    stop();
                    return;
                }
                if (!confirmIf || !confirmIf()) {
                    return;
                }
                if (!confirm(message)) {
                    e.preventDefault();
                }
            });

            return { ns: ns, stop: stop };
        },

        initElasticMenu: function(options) {

            var ElasticMenu = function(options) {
                var that = this;

                // DOM
                that.$wrapper = options["$wrapper"];
                that.$moreWrapper = that.$wrapper.find("#s-hidden-list");
                that.$items = that.$wrapper.find(".tabs > li");

                // VARS

                // DYNAMIC VARS
                that.items = false;
                that.staticItems = false;

                // INIT
                that.initClass();
            };

            ElasticMenu.prototype.initClass = function() {
                var that = this;

                // Set zIndex on menu
                that.$wrapper.addClass("is-elastic");
                //
                that.setItemsData();
                //
                that.prepareMenu();
                //
                that.menuWatcher();
                //
                that.bindEvents();
            };

            ElasticMenu.prototype.bindEvents = function() {
                var that = this,
                    $window = $(window);

                // Launch watcher after page loading
                setTimeout( function() {
                    $window.on("resize", onResize);
                }, 2000);

                function onResize() {
                    if ($.contains(document, that.$wrapper[0])) {
                        that.menuWatcher();
                    } else {
                        console.log( "off" );
                        $window.off("resize", onResize);
                    }
                }
            };

            ElasticMenu.prototype.menuWatcher = function() {
                var that = this,
                    menu_width = parseInt( that.$wrapper.width() ),
                    static_place_width = that.getEmptySpace(),
                    empty_space = menu_width - static_place_width,
                    is_someone_hidden = false;

                for (var i = 0; i < that.items.length; i++) {
                    var item = that.items[i],
                        item_width = item.$item.outerWidth();

                    if (empty_space - item_width > 0) {
                        empty_space -= item_width;
                        showItem( item );
                    } else {
                        hideItem( item );
                        is_someone_hidden = true;
                    }
                }

                if (is_someone_hidden) {
                    that.$moreWrapper.show();
                } else {
                    that.$moreWrapper.hide();
                }

                function showItem( item ) {
                    item.$item.show();
                    item.$clone.hide();
                    item.is_shown = true;
                }

                function hideItem() {
                    item.$item.hide();
                    item.$clone.show();
                    item.is_shown = false;
                }
            };

            ElasticMenu.prototype.setItemsData = function() {
                var that = this,
                    items = [],
                    staticItems = [];

                that.$items.each( function() {
                    // DOM
                    var $item = $(this);

                    // CASES
                    var is_right_menu_item = $item.hasClass("float-right"),
                        is_store_button = $item.hasClass("s-openstorefront"),
                        is_hidden_list = ($item[0] === that.$moreWrapper[0]);

                    // SET STATIS PLACE
                    if (is_right_menu_item || is_store_button || is_hidden_list) {
                        staticItems.push($item);
                    // PUSH
                    } else {
                        items.push({
                            $item: $item,
                            $clone: false, // dynamic var, $item at hidden list
                            is_shown: true  // dynamic var, visible flag
                        });
                    }
                });

                that.items = items;
                that.staticItems = staticItems;
            };

            ElasticMenu.prototype.prepareMenu = function() {
                var that = this,
                    $list = that.$moreWrapper.find(".menu-v"),
                    selected_class = "selected";

                for (var i = 0; i < that.items.length; i++) {
                    var item = that.items[i],
                        $clone = item.$item.clone();

                    var is_selected = ( $clone.hasClass(selected_class) );

                    $clone
                        .removeAttr("id")
                        .removeAttr("class");

                    if (is_selected) {
                        $clone.addClass(selected_class)
                    }

                    $list.append($clone);

                    item.$clone = $clone;
                }
            };

            ElasticMenu.prototype.getEmptySpace = function() {
                var that = this,
                    result = 0,
                    correction = 50;

                for (var i = 0; i < that.staticItems.length; i++) {
                    var $item = that.staticItems[i];

                    result += parseInt( $item.outerWidth( true ) );
                }

                return result + correction;
            };

            new ElasticMenu(options);
        },

        helper: {
            /**
             * @param {String} params
             * @return {object}
             */
            parseParams: function(params) {
                if (!params) {
                    return {};
                }
                var p = params.split('&');
                var result = {};
                for (var i = 0; i < p.length; i++) {
                    var t = p[i].split('=');
                    result[t[0]] = t.length > 1 ? t[1] : '';
                }
                return result;
            },
            /**
             * Number of items in key-value object
             *
             * @param {Object}
             * @return Number
             */
            size: function(obj) {
                var counter = 0;
                for (var k in obj) {
                    if (obj.hasOwnProperty(k)) {
                        counter += 1;
                    }
                }
                return counter;
            },
            print: function(el) {
                var $head = $('head').clone(false);
                $head.find('script').remove();

                var $body = $(el).parents($(el).data('selector') || 'div.block').parent().clone(false);
                $body.find('a.js-print').remove();

                var wnd = window.open('', 'printversion', 'scrollbars=1,width=600,height=600');

                // Hack to fix yandex.maps widget on print page
                if ($body.find('.map[id^="yandex-map"]').length) {
                    $body.find('.map[id^="yandex-map"]').empty();
                    $head.append('<script src="'+$.shop.options.jquery_url+'"></script>');
                    wnd.ymaps = undefined;
                }

                var html = '<html><head>' + $head.html() + '</head><body class="s-printable">' + $body.html()
                + '<i class="icon16 loading" style="top: 20px; left: 20px; position: relative;display: none;"></i>' + '</body></html>';

                setTimeout(function() {
                    var $w = $(wnd.document);
                    $w.find('div:first').hide();
                    $w.find('i.icon16.loading:last').show();
                }, 50);
                setTimeout(function() {
                    var $w = $(wnd.document);
                    $w.find('div:hidden:first').show();
                    $w.find('i.icon16.loading:last').hide();
                }, 1000);
                wnd.document.open();
                wnd.document.write(html);
                wnd.document.close();

                return false;
            }
        },

        iterator: function (collection, with_key) {
            var isArr = $.isArray(collection);
            var len = isArr ? collection.length : $.shop.helper.size(collection);
            var keys = [];
            var idx = -1;
            for (var k in collection) {
                if (collection.hasOwnProperty(k)) {
                    keys.push(k);
                }
            }
            return {

                next: function () {
                    idx++;
                    var k = idx;
                    if (k >= len) {
                        return null;
                    }

                    if (!isArr) {
                        k = keys[k];
                    }
                    return with_key ? [k, collection[k]] : collection[k];
                },

                len: function() {
                    return len;
                },

                key: function() {
                    k = idx;
                    if (!isArr) {
                        k = keys[k];
                    }
                    return k;
                },

                isFirst: function () {
                    return idx <= 0;
                },

                isLast: function () {
                    return idx >= len - 1;
                },

                reset: function () {
                    idx = 0;
                }
            }
        }

    };
})(jQuery);
$.storage = new $.store();