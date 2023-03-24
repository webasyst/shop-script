( function($) { "use strict";

    var CrossSelling = ( function($) {

        CrossSelling = function(options) {
            var that = this;

            if ( !(options.outer_options && (typeof options.outer_options.wrapper === "string") && options.outer_options.wrapper.length > 0) ) {
                throw new Error('Checkout wrapper element not specified.');
            }

            var $outer_wrapper = $(options.outer_options["wrapper"]);
            if ($outer_wrapper.length !== 1) {
                throw new Error('Error: Checkout wrapper element must be exactly one on page (found '+ $outer_wrapper.length + ')');
            }

            // DOM
            that.$outer_wrapper = $outer_wrapper;
            that.$wrapper = options["$wrapper"];
            that.options = options["outer_options"];

            // CONST
            that.urls = options["urls"];
            that.templates = options["templates"];

            // DYNAMIC VARS

            // XHR
            that.reload_xhr = null;

            // INIT
            that.init();
        };

        CrossSelling.prototype.init = function() {
            var that = this,
                $document = $(document);

            var invisible_class = "js-invisible-content";
            that.$wrapper.find(".wa-cross_selling-body > .wa-cross_selling-loader").remove();
            that.$wrapper.removeClass("is-not-ready")
                .find("." + invisible_class).removeAttr("style").removeClass(invisible_class);

            that.$outer_wrapper.data("controller", that);
            that.trigger("ready", that);

            that.initSlider();

            that.initAddProduct();

            new window.waOrder.ui.Styler({
                $wrapper: that.$wrapper,
                periods: [
                    {
                        min: null,
                        max: 420,
                        class: "width-s"
                    },
                    {
                        min: 421,
                        max: 580,
                        class: "width-m"
                    },
                    {
                        min: 581,
                        max: null,
                        class: "width-l"
                    }
                ]
            });
        };

        CrossSelling.prototype.DEBUG = function() {
            var that = this,
                log_function = console.log;

            var styles = {
                "hint": "font-weight: bold; color: #666;",
                "info": "font-weight: bold; font-size: 1.25em; color: blue;",
                "warn": "font-weight: bold; font-size: 1.25em;",
                "error": "font-weight: bold; font-size: 1.25em;"
            };

            if (that.options && that.options.DEBUG) {
                if (styles[arguments[1]]) {
                    arguments[0] = (typeof arguments[0] === "string" ? "%c" + arguments[0] : arguments[0]);

                    switch (arguments[1]) {
                        case "info":
                            log_function = console.info;
                            break;
                        case "error":
                            log_function = console.error;
                            break;
                        case "warn":
                            log_function = console.warn;
                            break;
                    }

                    arguments[1] = styles[arguments[1]];
                }

                return log_function.apply(console, arguments);
            }
        };

        CrossSelling.prototype.initSlider = function() {
            var that = this,
                $slider = that.$wrapper.find(".js-slider-wrapper");

            var Slider = ( function($) {

                Slider = function(options) {
                    var that = this;

                    // DOM
                    that.$wrapper = options["$wrapper"];
                    that.$list = options["$list"];
                    that.$items = options["$slides"];
                    that.$next = options["$next"];
                    that.$prev = options["$prev"];

                    // VARS
                    that.items_count = that.$items.length;
                    that.touch_enabled = ("ontouchstart" in window);

                    // DYNAMIC VARS
                    that.left = 0;
                    that.visible_w = null;
                    that.full_w = null;
                    that.item_w = null;

                    // INIT
                    that.init();
                };

                Slider.prototype.init = function() {
                    var that = this;

                    var $window = $(window);

                    if (that.touch_enabled) {
                        that.$wrapper.addClass("with-touch");
                    }

                    setTimeout( function() {
                        that.update();
                    }, 10);

                    var scroll_timer = 0;
                    that.$list.on("scroll", function() {
                        that.left = that.$list.scrollLeft();

                        clearTimeout(scroll_timer);
                        scroll_timer = setTimeout( function() {
                            that.showArrows();
                        }, 100);
                    });

                    that.$prev.on("click", function(event) {
                        event.preventDefault();
                        that.move(false);
                    });

                    that.$next.on("click", function(event) {
                        event.preventDefault();
                        that.move(true);
                    });

                    $window.on("resize", onResize);
                    function onResize() {
                        var is_exist = $.contains(document, that.$wrapper[0]);
                        if (is_exist) {
                            setTimeout( function() {
                                that.reset();
                            }, 10);
                        } else {
                            $window.off("resize", onResize);
                        }
                    }
                };

                Slider.prototype.update = function() {
                    var that = this;

                    that.visible_w = that.$list.outerWidth();
                    that.full_w = that.$list[0].scrollWidth;
                    that.item_w = ( that.$items.length ? that.full_w/that.$items.length : 0 );

                    that.showArrows();
                };

                Slider.prototype.showArrows = function() {
                    var that = this;

                    var disable_class = "disabled",
                        short_class = "is-short",
                        is_short = false;

                    if (that.left > 0) {
                        that.$prev.removeClass(disable_class);

                        if (that.left + that.visible_w >= that.full_w) {
                            that.$next.addClass(disable_class);
                        } else {
                            that.$next.removeClass(disable_class);
                        }

                    } else {
                        that.$prev.addClass(disable_class);

                        if (that.visible_w >= that.full_w) {
                            that.$next.addClass(disable_class);
                            is_short = true;
                        } else {
                            that.$next.removeClass(disable_class);
                        }
                    }

                    if (is_short) {
                        that.$wrapper.addClass(short_class);
                    } else {
                        that.$wrapper.removeClass(short_class);
                    }
                };

                Slider.prototype.set = function( left ) {
                    var that = this;

                    that.$list.scrollLeft(left);

                    that.left = left;
                };

                Slider.prototype.move = function( right ) {
                    var that = this,
                        step = 1;

                    var new_left = that.left + that.item_w * ( right ? step : -step );

                    if (new_left < 0) {
                        new_left = 0;
                    } else if (new_left + that.visible_w >= that.full_w) {
                        new_left = that.full_w - that.visible_w;
                    }

                    new_left = Math.round(new_left);

                    that.set(new_left);
                    that.showArrows();
                };

                Slider.prototype.reset = function() {
                    var that = this;

                    that.set(0);
                    that.update();
                };

                return Slider;

            })($);

            new Slider({
                $wrapper: $slider,
                $list: $slider.find(".wa-slider-list"),
                $slides: $slider.find(".wa-slide-wrapper"),
                $next: $slider.find(".js-scroll-next"),
                $prev: $slider.find(".js-scroll-prev")
            });
        };

        CrossSelling.prototype.initAddProduct = function() {
            var that = this;

            that.$wrapper.on("click", ".js-add-product", function(event) {
                event.preventDefault();

                var $product = $(this).closest(".wa-product-wrapper"),
                    show_dialog = $product.data("show-dialog");

                if (show_dialog) {
                    showDialog($product);
                } else {
                    addProduct($product)
                }
            });

            function showDialog($product) {
                that.lock(true);

                var href = that.urls["product_dialog"],
                    data = getData($product);

                $.post(href, data)
                    .always( function() {
                        that.lock(false);
                    })
                    .done( function(html) {
                        new window.waOrder.ui.Dialog({
                            $wrapper: $(html),
                            options: {
                                scope: that,
                                onSubmit: function(sku_id) {
                                    return addProduct($product, sku_id);
                                }
                            }
                        });
                });

                function getData($product) {
                    var result = [];

                    var product_id = $product.data("product-id");
                    if (product_id) {
                        result.push({
                            name: "id",
                            value: product_id
                        });
                    } else {
                        that.DEBUG("Product ID required","error");
                    }

                    var sku_id = $product.data("sku-id");
                    if (sku_id) {
                        result.push({
                            name: "sku_id",
                            value: sku_id
                        });
                    }

                    return result;
                }
            }

            /**
             * @param {Object} $product
             * @param {String?} sku_id
             * */
            function addProduct($product, sku_id) {
                var quantity = $product.find(".js-quantity-field").val(),
                    add_promise = $product.data("add_promise");

                if (!add_promise) {
                    sku_id = ( sku_id ? sku_id : $product.data("sku-id") );
                    add_promise = addProductRequest(sku_id, $product.data("product-id"));

                    $product.data("add_promise", add_promise);

                    add_promise
                        .done( function() {
                            var $submit_button = $product.find(".js-add-product"),
                                $success = $(that.templates["success_button"]);

                            $submit_button.hide().before($success);

                            setTimeout( function() {
                                $success.remove();
                                $submit_button.show();
                            }, 2000);
                        })
                        .always( function () {
                            $product.data("add_promise", null);
                            add_promise = null;
                        });
                }

                return add_promise;

                function addProductRequest(sku_id, product_id) {
                    var href = that.urls["add_product"],
                        data = {
                            "item[product_id]": product_id,
                            "item[sku_id]": sku_id,
                            "item[quantity]": quantity
                        };

                    return $.post(href, data, "json")
                        .done( function(response) {
                            $(document).trigger("wa_order_product_added");
                        });
                }
            }
        };

        /**
         * @param {string} event_name
         * @param {Object|Array?} data
         * */
        CrossSelling.prototype.trigger = function(event_name, data) {
            var that = this;

            var cart_event_name = "wa_order_cross_selling_" + event_name;

            that.$wrapper.triggerHandler(event_name, (data || null));
            that.$outer_wrapper.trigger(cart_event_name, (data || null));
        };

        /**
         * @return {promise}
         * */
        CrossSelling.prototype.reload = function() {
            var that = this,
                deferred = $.Deferred();

            var href = that.urls["refresh"],
                data = {
                    opts: that.options
                };

            if (!that.reload_xhr) {
                that.lock(true);

                that.trigger("before_reload", that);

                that.reload_xhr = $.post(href, data, function(html) {

                    that.$outer_wrapper.one("wa_order_cart_ready", function() {
                        var new_controller = that.$outer_wrapper.data("controller");
                        deferred.resolve(new_controller);
                    });

                    that.$wrapper.replaceWith(html);

                    that.trigger("reloaded", that);

                }).always( function() {
                    that.lock(false);
                    that.reload_xhr = false;
                });
            }

            return deferred.promise();
        };

        CrossSelling.prototype.lock = function(do_lock) {
            var that = this,
                locked_class = "is-locked";

            if (do_lock) {
                that.$wrapper.addClass(locked_class);

            } else {
                that.$wrapper.removeClass(locked_class);
            }
        };

        return CrossSelling;

    })($);

    window.waOrder = (window.waOrder || {});

    window.waOrder.CrossSelling = CrossSelling;

})(jQuery);