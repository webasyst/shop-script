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

            if (options.menu_floating) {
                var main_menu = $('#mainmenu');
                var top_offset = $('#wa-app').offset().top;
                var scroll_handler = function() {
                    if ($(this).scrollTop() > top_offset) {
                        main_menu.addClass('s-fixed');
                    } else {
                        main_menu.removeClass('s-fixed');
                    }
                };
                var recalc = function() {
                    top_offset = $('#wa-app').offset().top;
                    scroll_handler();
                };

                $(window).scroll(scroll_handler).resize(recalc);
                $('#wa-moreapps').click(function() {
                    setTimeout(function() {
                        recalc();
                    });
                });

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

        changeListener: function(el, handler, delegate_context) {
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
        }
    };
})(jQuery);
$.storage = new $.store();