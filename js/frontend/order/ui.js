( function($) { "use strict";

    var Toggle = ( function($) {

        Toggle = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];

            // VARS
            that.on = {
                ready: (typeof options["ready"] === "function" ? options["ready"] : function() {}),
                change: (typeof options["change"] === "function" ? options["change"] : function() {})
            };
            that.active_class = (options["active_class"] || "selected");

            // DYNAMIC VARS
            that.$before = null;
            that.$active = that.$wrapper.find("> *." + that.active_class);

            // INIT
            that.initClass();
        };

        Toggle.prototype.initClass = function() {
            var that = this,
                active_class = that.active_class;

            that.$wrapper.on("click", "> *", onClick);

            that.$wrapper.trigger("ready", that);

            that.on.ready(that);

            //

            function onClick(event) {
                event.preventDefault();

                var $target = $(this),
                    is_active = $target.hasClass(active_class);

                if (is_active) { return false; }

                if (that.$active.length) {
                    that.$before = that.$active.removeClass(active_class);
                }

                that.$active = $target.addClass(active_class);

                that.$wrapper.trigger("change", [this, that]);
                that.on.change(event, this, that);
            }
        };

        return Toggle;

    })($);

    var Dialog = ( function($) {

        Dialog = function(options) {
            var that = this;

            that.$wrapper = options["$wrapper"];
            if (that.$wrapper && that.$wrapper.length) {
                // DOM
                that.$block = that.$wrapper.find(".wa-dialog-body");
                try {
                    that.$body = $(window.top.document).find("body");
                    that.$window = $(window.top);
                } catch (e) {
                    // This is for theme showcase when site runs in foreign origin iframe
                    that.$body = $(document).find("body");
                    that.$window = $(window);
                }

                // VARS
                that.position = (options["position"] || false);
                that.userPosition = (options["setPosition"] || false);
                that.options = (options["options"] || false);
                that.scroll_locked_class = "is-scroll-locked";
                that.height_limit = (options["height_limit"] || 640);

                // DYNAMIC VARS
                that.is_visible = false;
                that.is_removed = false;

                // HELPERS
                that.onBgClick = (options["onBgClick"] || false);
                that.onOpen = (options["onOpen"] || function() {});
                that.onClose = (options["onClose"] || function() {});
                that.onResize = (options["onResize"] || false);

                // INIT
                that.initClass();
            } else {
                console.error("Error: bad data for dialog");
            }
        };

        Dialog.prototype.initClass = function() {
            var that = this;
            // save link on dialog
            that.$wrapper.data("dialog", that);
            //
            that.render();

            // Delay binding close events so that dialog does not close immidiately
            // from the same click that opened it.
            setTimeout(function() {
                that.bindEvents();
            }, 0);

            that.$wrapper
                .data("dialog", that)
                .trigger("wa_order_dialog_open", [that.$wrapper, that]);
        };

        Dialog.prototype.bindEvents = function() {
            var that = this,
                $document = $(document),
                $block = (that.$block) ? that.$block : that.$wrapper;

            that.$wrapper.on("close", close);

            // Click on background, default nothing
            that.$wrapper.on("click", ".dialog-background", function(event) {
                if (typeof that.onBgClick === "function") {
                    that.onBgClick(event);
                } else {
                    event.stopPropagation();
                }
            });

            //

            $block.on("click", ".js-close-dialog", close);
            function close() {
                var result = that.close();
                if (result === true) {
                    $document.off("click", close);
                    $document.off("wa_before_load", close);
                }
            }

            //

            $(window).on("resize", onResize);
            $document.on("resize", onResize);
            function onResize() {
                var is_exist = $.contains(document, that.$wrapper[0]);
                if (is_exist) {
                    that.resize();
                } else {
                    $(window).off("resize", onResize);
                    $document.off("resize", onResize);
                }
            }

            //

            // refresh dialog position
            $document.on("resizeDialog", resizeDialog);
            function resizeDialog() {
                var is_exist = $.contains(document, that.$wrapper[0]);
                if (is_exist) {
                    that.resize();
                } else {
                    $(document).off("resizeDialog", resizeDialog);
                }
            }

            //

            $(document).on("keyup", keyWatcher);
            function keyWatcher(event) {
                if (!that.is_removed) {
                    var escape_code = 27,
                        enter_code = 13;

                    switch (event.keyCode) {
                        case escape_code:
                            that.close();
                            break;

                        case enter_code:
                            var $focus_button = that.$wrapper.find(".js-focus-button");
                            if ($focus_button.length === 1) {
                                $focus_button.trigger("click");
                            }
                            break;
                    }

                } else {
                    $(document).off("keyup", keyWatcher);
                }
            }
        };

        Dialog.prototype.render = function() {
            var that = this;

            try {
                that.show();
            } catch(e) {
                console.error("Error: " + e.message);
                console.log(e.stack);
            }

            //
            that.setPosition();
            //
            that.$body.addClass(that.scroll_locked_class);
            //
            that.onOpen(that.$wrapper, that);
        };

        Dialog.prototype.setPosition = function() {
            var that = this,
                $window = that.$window,
                $block = that.$block,
                $content = $block.find(".wa-dialog-content");

            var tall_class = "is-tall",
                with_big_content = "with-tall-content";

            $block.removeClass(tall_class);
            $block.removeClass(with_big_content);
            $content.removeAttr("style");

            var window_w = $window.width(),
                window_h = $window.height(),
                wrapper_w = $block.outerWidth(),
                wrapper_h = $block.outerHeight(),
                pad = 20,
                css;

            if (that.position) {
                css = that.position;

            } else {
                var getPosition = (that.userPosition) ? that.userPosition : getDefaultPosition;
                css = getPosition({
                    width: wrapper_w,
                    height: wrapper_h
                });
            }

            if (css.left > 0) {
                if (css.left + wrapper_w > window_w) {
                    css.left = window_w - wrapper_w - pad;
                }
            }

            if (css.top > 0) {

                if (wrapper_h >= that.height_limit) {
                    $block.addClass(with_big_content);
                }

                if (css.top + wrapper_h > window_h) {
                    css.top = window_h - wrapper_h - pad;
                }

            } else {
                css.top = pad;

                $block.addClass(tall_class);

                $content.hide();

                var block_h = $block.outerHeight(),
                    content_h = window_h - block_h - pad * 2;

                $content
                    .css({
                        height: content_h + "px"
                    })
                    .show();
            }

            $block.css(css);

            function getDefaultPosition(area) {
                return {
                    left: Math.floor( (window_w - area.width)/2 ),
                    top: Math.floor( (window_h - area.height)/2 )
                };
            }
        };

        Dialog.prototype.close = function() {
            var that = this,
                result = null;

            if (that.is_visible) {
                //
                result = that.onClose(that);
                //
                if (result !== false) {
                    that.$wrapper.remove();
                    that.is_removed = true;
                }
            }

            that.$body.removeClass(that.scroll_locked_class);

            return result;
        };

        Dialog.prototype.resize = function() {
            var that = this,
                animate_class = "is-animated",
                do_animate = true;

            if (do_animate) {
                that.$block.addClass(animate_class);
            }

            that.setPosition();

            if (that.onResize) {
                that.onResize(that.$wrapper, that);
            }
        };

        Dialog.prototype.hide = function() {
            var that = this;

            $("<div />").append(that.$wrapper.hide());

            that.$body.removeClass(that.scroll_locked_class);

            that.is_visible = false;
        };

        Dialog.prototype.show = function() {
            var that = this,
                is_exist = $.contains(document, that.$wrapper[0]);

            if (!is_exist) {
                that.$body.append(that.$wrapper);
            }

            that.$wrapper.show();
            that.is_visible = true;

            that.$body.addClass(that.scroll_locked_class);

            // update vars
            $(window).trigger("scroll");
        };

        /**
         * @param {Boolean} do_lock
         * */
        Dialog.prototype.lock = function(do_lock) {
            var that = this,
                locked_class = "is-locked";

            if (typeof do_lock === "boolean") {
                if (do_lock) {
                    that.$wrapper.addClass(locked_class);
                } else {
                    that.$wrapper.removeClass(locked_class);
                }
            }
        };

        return Dialog;

    })($);

    var Dropdown = ( function($) {

        Dropdown = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$button = that.$wrapper.find("> .wa-dropdown-toggle");
            that.$menu = that.$wrapper.find("> .wa-dropdown-area");

            // VARS
            that.on = {
                ready: (typeof options["ready"] === "function" ? options["ready"] : function() {}),
                open: (typeof options["open"] === "function" ? options["open"] : function() {}),
                change: (typeof options["change"] === "function" ? options["change"] : function() {})
            };
            that.data = getData(that.$wrapper, options);

            // DYNAMIC VARS
            that.is_locked = false;
            that.is_opened = false;
            that.$before = null;
            that.$active = null;

            // INIT
            that.initClass();
        };

        Dropdown.prototype.initClass = function() {
            var that = this;

            if (that.data.hover) {
                that.$button.on("mouseenter", function() {
                    that.toggleMenu(true);
                });

                that.$wrapper.on("mouseleave", function() {
                    that.toggleMenu(false);
                });
            }

            that.$button.on("click", function(event) {
                event.preventDefault();
                that.toggleMenu(!that.is_opened);
            });

            if (that.data.change_selector) {
                that.initChange(that.data.change_selector);
            }

            $(document).on("click", clickWatcher);

            $(document).on("keyup", keyWatcher);

            that.on.ready(that);

            function keyWatcher(event) {
                var is_exist = $.contains(document, that.$wrapper[0]);
                if (is_exist) {
                    var is_escape = (event.keyCode === 27);
                    if (that.is_opened && is_escape) {
                        that.hide();
                    }
                } else {
                    $(document).off("click", keyWatcher);
                }
            }

            function clickWatcher(event) {
                var wrapper = that.$wrapper[0],
                    is_exist = $.contains(document, wrapper);

                if (is_exist) {
                    var is_target = (event.target === wrapper || $.contains(wrapper, event.target));
                    if (that.is_opened && !is_target) {
                        that.hide();
                    }
                } else {
                    $(document).off("click", clickWatcher);
                }
            }
        };

        Dropdown.prototype.toggleMenu = function(open) {
            var that = this,
                active_class = "is-opened";

            if (open) {
                if (that.is_locked) { return false; }

                var open_result = that.on.open(that);
                if (open_result !== false) {
                    that.$wrapper
                        .addClass(active_class)
                        .trigger("open", that);
                }

            } else {
                that.$wrapper
                    .removeClass(active_class)
                    .trigger("close", that);

            }

            that.is_opened = open;
        };

        Dropdown.prototype.initChange = function(selector) {
            var that = this,
                change_class = that.data.change_class;

            that.$active = that.$menu.find(selector + "." + change_class);

            that.$wrapper.on("click", selector, onChange);

            function onChange(event) {
                event.preventDefault();

                var $target = $(this);

                if (that.$active.length) {
                    that.$before = that.$active.removeClass(change_class);
                }

                that.$active = $target.addClass(change_class);

                if (that.data.change_title) {
                    that.setTitle($target.html());
                }

                if (that.data.change_hide) {
                    that.hide();
                }

                that.$wrapper.trigger("change", [$target[0], that]);
                that.on.change(event, this, that);
            }
        };

        Dropdown.prototype.hide = function() {
            var that = this;

            that.toggleMenu(false);
        };

        Dropdown.prototype.setTitle = function(html) {
            var that = this;

            that.$button.html( html );
        };

        Dropdown.prototype.lock = function(lock) {
            var that = this;

            var locked_class = "is-locked";

            if (lock) {
                that.$wrapper.addClass(locked_class);
                that.is_locked = true;
            } else {
                that.$wrapper.removeClass(locked_class);
                that.is_locked = false;
            }
        };

        return Dropdown;

        function getData($wrapper, options) {
            var result = {
                hover: true,
                change_selector: "",
                change_class: "selected",
                change_title: true,
                change_hide: true
            };

            var hover = ( typeof options["hover"] !== "undefined" ? options["hover"] : $wrapper.data("hover") );
            if (hover === false) { result.hover = false; }

            result.change_selector = (options["change_selector"] || $wrapper.data("change-selector") || "");
            result.change_class = (options["change_class"] || $wrapper.data("change-class") || "selected");

            var change_title = ( typeof options["change_title"] !== "undefined" ? options["change_title"] : $wrapper.data("change-title") );
            if (change_title === false) { result.change_title = false; }

            var hide = ( typeof options["change_hide"] !== "undefined" ? options["change_hide"] : $wrapper.data("change-hide") );
            if (hide === false) { result.change_hide = false; }

            return result;
        }

    })($);

    var Styler = ( function($) {

        Styler = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];

            // CONST
            that.periods = options["periods"];

            // DYNAMIC VARS

            // INIT
            that.init();
        };

        Styler.prototype.init = function() {
            var that = this;

            var $window = $(window),
                $document = $(document);

            that.set();

            $document.on("refresh", refreshWatcher);
            function refreshWatcher() {
                var is_exist = $.contains(document, that.$wrapper[0]);
                if (is_exist) {
                    that.set();
                } else {
                    $document.off("refresh", refreshWatcher);
                }
            }

            $window.on("resize", resizeWatcher);
            function resizeWatcher() {
                var is_exist = $.contains(document, that.$wrapper[0]);
                if (is_exist) {
                    that.set();
                } else {
                    $window.off("refresh", resizeWatcher);
                }
            }
        };

        Styler.prototype.set = function() {
            var that = this;

            if (!that.periods.length) { return false; }

            var width = that.$wrapper.outerWidth();

            $.each(that.periods, function(i, period) {
                var set_class = false;

                if (period.min && period.max) {
                    set_class = (width >= period.min && width <= period.max);

                } else if (period.max) {
                    set_class = (width <= period.max);

                } else if (period.min) {
                    set_class = (width >= period.min);

                } else {
                    set_class = true;
                }

                render(period.class, set_class);
            });

            function render(class_name, add_class) {
                if (add_class) {
                    that.$wrapper.addClass(class_name);
                } else {
                    that.$wrapper.removeClass(class_name);
                }
            }
        };

        return Styler;

    })(jQuery);

    var load = function(sources) {
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
                        rel: "stylesheet"
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
                        .attr("id", source.id)
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
    };

    /**
     * @param {String} string
     * @return {Boolean}
     * */
    var isNumber = function(string) {
        var result = false,
            exp = /^[0-9]+(\.+[0-9]+)?$/i;

        if (string.length > 0 && (string.match(exp) || []).length >= 1) {
            result = true;
        }

        return result;
    };

    /**
     * @param {String} string
     * @return {Boolean}
     * */
    var isEmail = function(string) {
        var result = false,
            exp = /^.+@+.+\.+.+$/i;

        if (string.length > 0 && (string.match(exp) || []).length >= 1) {
            result = true;
        }

        return result;
    };

    /**
     * @param {String} string
     * @return {Boolean}
     * */
    var isURL = function(string) {
        var result = false,
            regexp = /^(http:\/\/|https:\/\/)?.+\.+.+$/i;

        if (string.length > 0 && (string.match(regexp) || []).length >= 1) {
            result = true;
        }

        return result;
    };

    /**
     * @param {String} string
     * @return {Boolean}
     * */
    var isPhone = function(string) {
        var result = false,
            regexp = /^(\+?)+([0-9]|\s|-|\(|\))+$/i;

        if (string.length > 0 && (string.match(regexp) || []).length >= 1) {
            result = true;
        }

        return result;
    };

    var price_format = null;

    /**
     * @param {string|number} price
     * @param {boolean?} text
     * @return {string}
     * */
    var formatPrice = function(price, text) {
        var result = price,
            format = price_format;

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
    };

    var initFormatPrice = function(format) {
        if (format) { price_format = format; }
    };

    window.waOrder = (window.waOrder || {});

    window.waOrder.ui = {
        Toggle: Toggle,
        Dialog: Dialog,
        Dropdown: Dropdown,
        Styler: Styler,
        validate: {
            url: isURL,
            email: isEmail,
            phone: isPhone,
            number: isNumber
        },
        load: load,
        formatPrice: formatPrice,
        initFormatPrice: initFormatPrice
    };

    if ("ontouchstart" in window) {
        $("html")
            .addClass("is-mobile")
            .addClass("with-touch");
    }

})(jQuery);