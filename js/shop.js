(function($) {
    if (typeof $.ui.autocomplete !== "undefined") {
        $.extend($.ui.autocomplete.prototype, {
            _renderItem: function(ul, item) {
                return $("<li></li>")
                    .append("<a>" + item.label + "</a>")
                    .appendTo(ul);
            }
        });
    }

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

    function sourceLoader(sources) {
        var deferred = $.Deferred();

        loader(sources).then( function() {
            deferred.resolve();
        }, function(bad_sources) {
            if (console && console.error) {
                console.error("Error loading resource", bad_sources);
            }
            deferred.reject(bad_sources);
        });

        return deferred.promise();

        function loader(sources) {
            var deferred = $.Deferred(),
                counter = sources.length;

            var bad_sources = [];

            $.each(sources, function(i, source) {
                switch (source.type) {
                    case "css":
                        loadCSS(source).then(onLoad, onError);
                        break;
                    case "js":
                        loadJS(source).then(onLoad, onError);
                        break;
                }
            });

            return deferred.promise();

            function loadCSS(source) {
                var deferred = $.Deferred(),
                    promise = deferred.promise();

                var $link = $("#" + source.id);
                if ($link.length) {
                    promise = $link.data("promise");

                } else {
                    $link = $("<link />", {
                        id: source.id,
                        rel: "stylesheet",
                        ...source.attrs
                    }).appendTo("head")
                        .data("promise", promise);

                    $link
                        .on("load", function() {
                            deferred.resolve(source);
                        }).on("error", function() {
                        deferred.reject(source);
                    });

                    $link.attr("href", source.uri);
                }

                return promise;
            }

            function loadJS(source) {
                var deferred = $.Deferred(),
                    promise = deferred.promise();

                var $script = $("#" + source.id);
                if ($script.length) {
                    promise = $script.data("promise");

                } else {
                    var script = document.createElement("script");
                    document.getElementsByTagName("head")[0].appendChild(script);

                    $script = $(script)
                        .attr({
                            "id": source.id,
                            ...source.attrs
                        })
                        .data("promise", promise);

                    $script
                        .on("load", function() {
                            deferred.resolve(source);
                        }).on("error", function() {
                        deferred.reject(source);
                    });

                    $script.attr("src", source.uri);
                }

                return promise;
            }

            function onLoad(source) {
                counter -= 1;
                watcher();
            }

            function onError(source) {
                bad_sources.push(source);
                counter -= 1;
                watcher();
            }

            function watcher() {
                if (counter === 0) {
                    if (!bad_sources.length) {
                        deferred.resolve();
                    } else {
                        deferred.reject(bad_sources);
                    }
                }
            }
        }
    }

    /**
     * @param {string|number} price
     * @param {Object} format
     * @param {boolean?} text
     * @return {string}
     * */
    function formatPrice(price, format, text) {
        var result = price;

        if (!format) { return result; }

        try {
            price = parseFloat(price).toFixed(format.fraction_size);

            if ( (price >= 0) && format) {
                var price_floor = Math.floor(price),
                    price_string = getGroupedString("" + price_floor, format.group_size, format.group_divider),
                    fraction_string = getFractionString(price - price_floor);

                result = ( text ? format.pattern_text : format.pattern_html ).replace("%s", price_string + fraction_string );
            }

        } catch(e) {
            if (console && console.log) {
                console.log(e.message, price);
            }
        }

        return result;

        function getGroupedString(string, size, divider) {
            var result = "";

            if (!(size && string && divider)) {
                return string;
            }

            var string_array = string.split("").reverse();

            var groups = [];
            var group = [];

            for (var i = 0; i < string_array.length; i++) {
                var letter = string_array[i],
                    is_first = (i === 0),
                    is_last = (i === string_array.length - 1),
                    delta = (i % size);

                if (delta === 0 && !is_first) {
                    groups.unshift(group);
                    group = [];
                }

                group.unshift(letter);

                if (is_last) {
                    groups.unshift(group);
                }
            }

            for (i = 0; i < groups.length; i++) {
                var is_last_group = (i === groups.length - 1),
                    _group = groups[i].join("");

                result += _group + ( is_last_group ? "" : divider );
            }

            return result;
        }

        function getFractionString(number) {
            var result = "";

            if (number > 0) {
                number = number.toFixed(format.fraction_size + 1);
                number = Math.round(number * Math.pow(10, format.fraction_size))/Math.pow(10, format.fraction_size);
                var string = number.toFixed(format.fraction_size);
                result = string.replace("0.", format.fraction_divider);
            }

            return result;
        }
    }

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

            if (options.page === 'products') {
                redirect();
            }

            if (options["menu_floating"]) {
                initFixedMenu();

                $('body').on('click', 'a.js-print', function() {
                    return $.shop.helper.print(this);
                });
            }

            if (options.page !== 'orders') {
                // sync mainmenu orders count with app counter
                $(document).bind('wa.appcount', function(event, data) {
                    if (data && typeof data.shop !== "undefined") {
                        $.shop.updateOrdersCounter(parseInt(data.shop, 10) || 0);
                    }
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

            function redirect() {
                var $app_block = $('#wa-app'),
                    changed_href = false,
                    hash = parseHash(window.location.href),
                    app_url = options.app_url;

                if (hash.section === 'products') {
                    if ($.isEmptyObject(hash.params) === true) {
                        window.location.href = app_url + 'products/';
                        changed_href = true;
                    } else if (hash.params.hasOwnProperty('category_id')) {
                        window.location.href = app_url + 'products/categories/';
                        changed_href = true;
                    } else if (hash.params.hasOwnProperty('set_id')) {
                        window.location.href = app_url + 'products/sets/';
                        changed_href = true;
                    }
                } else if (hash.section === 'product') {
                    var split_tail = hash.tail.split('/');
                    if (split_tail.length > 0 && Number(split_tail[0]) > 0) {
                        var redirect_href = app_url + 'products/' + split_tail[0] + '/';
                        if (split_tail.length === 1) {
                            tab = $.storage.get('shop/product-profile/tab');
                            if (tab === 'reviews') {
                                redirect_href += tab + '/';
                            } else if (tab === 'profile') {
                                redirect_href += 'prices/';
                            }
                        } else if (split_tail.length === 2 && split_tail[1] === 'edit') {
                            redirect_href += 'general/';
                        } else if (split_tail.length === 3) {
                            switch (split_tail[2]) {
                                case 'descriptions':
                                    redirect_href += 'seo/';
                                    break;
                                case 'images':
                                    redirect_href += 'media/';
                                    break;
                                case 'features':
                                    redirect_href += 'sku/';
                                    break;
                                case 'services':
                                case 'related':
                                case 'pages':
                                    redirect_href += split_tail[2] + '/';
                                    break;
                            }
                        }
                        window.location.href = redirect_href;
                        changed_href = true;
                    }
                }

                if (changed_href === false) {
                    $app_block.css('visibility', '');
                }

                function parseHash(path) {
                    path = path.replace(/^.*#\//, '');
                    var hash = {
                        section: path.replace(/\/.*$/, '') || 'general',
                        tail: path.replace(/^[^\/]+\//, '').replace(/[\w_\-]+=.*$/, '').replace(/\/$/, ''),
                        params: path.match(/(^|\/)[\w_\-]+=/) ? $.shop.helper.parseParams(path.replace(/(^|^.*\/)([\w_\-]+=.*$)/, '$2').replace(/\/$/, '')) : {},
                        raw: path
                    };
                    ['view', 'sort', 'order'].forEach(function(key) {
                        delete hash.params[key];
                    });
                    return hash;
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

        jsonPost: function(url, data, success, error, always) {
            if (typeof data === 'function') {
                success = data;
                error = success;
                always = error;
                data = {};
            }
            var xhr = $.post(url, data, 'json');
            if (always) {
                xhr.always(always);
            }
            xhr.done(function(r) {
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
            });
            if (typeof error === 'function') {
                xhr.fail(function(r) {
                    if (error(r) !== false) {
                        default_error_handler(r);
                    }
                });
            } else {
                xhr.fail(default_error_handler);
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
            var counter = $('#wa-app-shop').find('.badge');
            if (!counter.length) {
                $('#wa-app-shop').find('a').append('<span class="badge" style="display:none;"></span>');
                counter = $('#wa-app-shop').find('.badge');
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
        changeListener: function(el, handler, delegate_context, ns) {

            if (typeof delegate_context === 'function' && typeof handler === 'string') {
                var swap = delegate_context;
                delegate_context = handler;
                handler = swap;
            }

            var timeout = 450;
            var timer_id = null;
            ns = ns || 'change_listener';
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
            input.attr('value', '').val('');

            var html = p.html();
            html = html.replace(/value(\s*?=\s*?['"][\s\S]*?['"])*/, '');
            html = html.replace(/type\s*?=\s*?['"]text['"]/, '');
            html = html.replace('input', 'textarea');
            html = $.trim(html).replace(/\/\s*>$/, '>') + '</textarea>';
            if (rm) {
                p.remove();
            }

            return $(html).val(val);

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
            textarea.html('').val('');

            var html = p.html();
            html = html.replace('textarea', 'input type="text" value=""');
            html = html.replace('</textarea>', '');

            if (rm) {
                p.remove();
            }

            return $(html).val(val);
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

        alertError: function(error_msg, log_msg) {
            $.waDialog.alert({
                title: error_msg,
                button_title: 'OK',
                button_class: 'warning',
            });

            if (log_msg) {
                this.logError(log_msg);
            }
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

                var $wrapper = $body.find(".s-split-order-block");
                var $blocks = $wrapper.find(".s-order-comment, .s-order-readable");
                $body.find("#s-split-order-wrapper").removeAttr("style");
                $blocks.insertAfter($wrapper);

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
            },
            escape: function(selector) {
                if (typeof selector === "undefined") { return ""; }

                var reserved = new RegExp('([!"#\$%&\'\(\)\*\+,\./:;<=>\?@\[\\\]\^`\{|\}~])','g');
                var escaped_selector = selector.replace(reserved,'\\$1');
                return escaped_selector;
            },
            /**
             *
             * @param $scope jQuery
             * @param event string event name with optional namespace, e.g. `click.myspace`
             * @param handler function
             * @param selector String
             */
            setEventHandler: function ($scope, event, handler, selector) {
                $.shop.trace('$.shop.helper.setEventHandler [' + event + ']', [$scope, selector]);
                $scope.off(event, selector).on(event, selector, handler);
            },

            /**
             * @returns {Boolean}
             */
            isMobile: function () {
                var reg = new RegExp('Android|webOS|iPhone|iPad|iPod|BlackBerry','i');
                return reg.test(navigator.userAgent);
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
        },

        loadSources: function(options) {
            return sourceLoader(options);
        },

        formatPrice: formatPrice,

        /**
         * Intersect arrays
         * Function with polymorphic signature
         *
         * Function can be in variadic form or form of collected array (array of arrays)
         *
         * @param {...Array} input
         * @param {Boolean} [collected] - form of function, if TRUE means on collected array form (not variadic), if skipped - variadic form
         *
         * @example
         *
         * $.shop.intersectArrays([1,2,3], [1,2,4]) => [1,2]                // variadic form
         * $.shop.intersectArrays([ [1,2,3], [1,2,4] ], true) => [1,2]      // collected form
         *
         * $.shop.intersectArrays([1,2,3], [1,2,4], [2,5,6]) => [2]                 // variadic form
         * $.shop.intersectArrays([ [1,2,3], [1,2,4], [2,5,6] ], true) => [1,2]     // collected form
         */
        intersectArrays: function (input, collected) {

            // variadic form
            if (typeof collected !== 'boolean') {
                input = Array.prototype.slice.call(arguments, 0);
            }

            // otherwise collected form


            // clone first array
            var result = [].concat(input[0]);

            // intersect loop
            for (var argI = 1; argI < input.length; argI++) {
                var ar = input[argI];

                // if element in result not in current array throw it from result (intersect definition)
                result = $.grep(result, function (el) {
                    return $.inArray(el, ar) > -1;
                });
            }

            return result;
        },

        /**
         * Get values of object
         * Like array_values in php
         * @param {Object} object
         */
        getValues: function (object) {
            return $.map(object, function (value) {
                // map automatically flatten inner array, so check for array and wrap into array
                return $.isArray(value) ? [value] : value;
            });
        },

        /**
         * Get keys of object
         * Like array_keys in php
         * @param object
         */
        getKeys: function (object) {
            return $.map(object, function (_, key) {
                return key;
            });
        },

        /**
         * Get unique values of plain object or array
         * Like array_unique in php
         * @param {Object|Array} collection
         */
        getUniqueValues: function (collection) {
            if ($.isPlainObject(collection)) {
                return this.getUniqueValues(this.getValues(collection));
            } else if ($.isArray(collection)) {
                return $.grep(collection, function (el, index) {
                    return index === $.inArray(el, collection);
                });
            } else {
                // not applicable
                return collection;
            }
        },

        /**
         * @param {String} type
         * @param {Number|String} value
         * @return {String} value
         * */
        validate: function(type, value) {
            value = (typeof value === "string" ? value : "" + value);

            var result = value;

            switch (type) {
                case "number":
                    var white_list = ["0", "1", "2", "3", "4", "5", "6", "7", "8", "9", ".", ","],
                        letters_array = [],
                        divider_exist = false;

                    $.each(value.split(""), function(i, letter) {
                        if (letter === "." || letter === ",") {
                            letter = ".";
                            if (!divider_exist) {
                                divider_exist = true;
                                letters_array.push(letter);
                            }
                        } else {
                            if (white_list.indexOf(letter) >= 0) {
                                letters_array.push(letter);
                            }
                        }
                    });

                    result = letters_array.join("");
                    break;

                case "number-negative":
                    var white_list = ["0", "1", "2", "3", "4", "5", "6", "7", "8", "9", ".", ",", "-"],
                        letters_array = [],
                        divider_exist = false;

                    $.each(value.split(""), function(i, letter) {
                        if (letter === "-") {
                            if (letters_array.length === 0) {
                                letters_array.push(letter);
                            }
                        } else if (letter === "." || letter === ",") {
                            letter = ".";
                            if (!divider_exist) {
                                divider_exist = true;
                                letters_array.push(letter);
                            }
                        } else {
                            if (white_list.indexOf(letter) >= 0) {
                                letters_array.push(letter);
                            }
                        }
                    });

                    result = letters_array.join("");
                    break;

                case "integer":
                    var white_list = ["0", "1", "2", "3", "4", "5", "6", "7", "8", "9"],
                        letters_array = [];

                    $.each(value.split(""), function(i, letter) {
                        if (white_list.indexOf(letter) >= 0) {
                            letters_array.push(letter);
                        }
                    });

                    result = letters_array.join("");
                    break;

                default:
                    break;
            }

            return result;
        },

        /**
         * @description Popup alert notifications with any info
         * @param options
         */
        notification(options) {

            const $appendTo = options.appendTo || document.body,
                isCloseable = options.isCloseable ?? true,
                alertTimeout = options.timeout || 0;
            let $alertWrapper = $appendTo.querySelector('#s-notifications');

            // Create notification
            const $alert = document.createElement('div');
            $alert.classList.add('alert', options.class || 'info');
            $alert.innerHTML = options.content || '';

            if(isCloseable){
                const closeClass = options.closeClass || 'js-alert-error-close',
                    $alertClose = document.createElement('a');

                $alertClose.classList.add('alert-close', closeClass);
                $alertClose.setAttribute('href', 'javascript:void(0)');
                $alertClose.innerHTML = '<i class="fas fa-times"></i>';
                $alert.insertAdjacentElement('afterbegin', $alertClose);
                // Event listener for close notification
                $alertClose.addEventListener('click', function() {
                    this.closest('.alert').remove();
                });
            }

            if(!$alertWrapper) {
                // Create notification wrapper
                $alertWrapper = document.createElement('div');
                $alertWrapper.className = 'alert-fixed-box';
                if (options.placement) {
                    $alertWrapper.classList.add(options.placement);
                }
                if (options.size) {
                    $alertWrapper.classList.add(options.size);
                }
                $alertWrapper.id = 't-notifications';
                $appendTo.append($alertWrapper);
            }

            if (options.placement) {
                $alertWrapper.prepend($alert);
            }else{
                $alertWrapper.append($alert);
            }

            if(alertTimeout > 0) {
                setTimeout(() => $alert.remove(), alertTimeout)
            }
        }

    };
})(jQuery);
$.storage = new $.store();
