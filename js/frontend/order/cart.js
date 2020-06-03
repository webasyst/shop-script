( function($) { "use strict";

    var Cart = ( function($) {

        Cart = function(options) {
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
            that.$products = that.$wrapper.find(".wa-products");
            that.$coupon_section = that.$wrapper.find(".wa-coupon-section");
            that.$affiliate_section = that.$wrapper.find(".wa-affiliate-section");
            that.options = options["outer_options"];

            // CONST
            that.weight_enabled = options["weight_enabled"];
            that.templates = options["templates"];
            that.locales = options["locales"];
            that.urls = options["urls"];

            // DYNAMIC VARS
            that.add_services_count = 0;
            that.add_services_deferred = $.Deferred().resolve();
            that.cart_save_promise = null;
            that.render_scheduled = false;

            // XHR
            that.clear_xhr = null;
            that.reload_xhr = null;
            that.update_xhr = null;

            // INIT
            that.initClass();
        };

        Cart.prototype.initClass = function() {
            var that = this,
                $document = $(document);

            var invisible_class = "js-invisible-content";
            that.$wrapper.find(".wa-cart-body > .wa-cart-loader").remove();
            that.$wrapper.removeClass("is-not-ready")
                .find("." + invisible_class).removeAttr("style").removeClass(invisible_class);

            that.$outer_wrapper.data("controller", that);
            that.trigger("ready", that);

            // START

            that.initEditProduct();

            that.initDeleteProduct();

            that.initChangeQuantity();

            that.initChangeService();

            that.initCoupon();

            that.initAffiliate();

            $document.on("wa_order_product_added", addProductWatcher);
            function addProductWatcher(event, data) {
                var is_exist = $.contains(document, that.$wrapper[0]);
                if (is_exist) {
                    that.reload();
                } else {
                    $document.off("wa_order_product_added", addProductWatcher);
                }
            }

            $document.on("wa_order_cart_invalid", updateWatcher);
            function updateWatcher() {
                var is_exist = $.contains(document, that.$wrapper[0]);
                if (is_exist) {
                    var $target = that.$wrapper,
                        $errors = that.$wrapper.find(".wa-error-text");

                    if ($errors.length) {
                        $target = $errors.first();
                    }

                    var top = $target.offset().top;
                    if (top > $(window).height - 100) {
                        top = top - 40;
                    } else {
                        top = 0;
                    }
                    $("html, body").scrollTop(top - 40);
                    that.reload();

                } else {
                    $document.off("wa_order_cart_invalid", updateWatcher);
                }
            }

            $document.on("wa_auth_contact_logged", loginWatcher);
            function loginWatcher() {
                var is_exist = $.contains(document, that.$wrapper[0]);
                if (is_exist) {
                    // These code were used to update the cart block. Now used reload page
                    // that.reload();
                    that.lock(true);
                } else {
                    $(document).off("wa_auth_contact_logged", loginWatcher);
                }
            }

            $document.on("wa_auth_contact_logout", logoutWatcher);
            function logoutWatcher() {
                var is_exist = $.contains(document, that.$wrapper[0]);
                if (is_exist) {
                    // These code were used to update the cart block. Now used reload page
                    // that.reload();
                    that.lock(true);
                } else {
                    $document.off("wa_auth_contact_logout", logoutWatcher);
                }
            }

            $document.on("wa_order_reload_start", reloadWatcher);
            function reloadWatcher() {
                var is_exist = $.contains(document, that.$wrapper[0]);
                if (is_exist) {
                    that.lock(true);
                } else {
                    $document.off("wa_order_reload_start", reloadWatcher);
                }
            }
        };

        Cart.prototype.DEBUG = function() {
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

        // PRIVATE METHODS

        Cart.prototype.initDeleteProduct = function() {
            var that = this;

            that.$wrapper.on("click", ".js-delete-product", function(event) {
                event.preventDefault();
                var $product = $(this).closest(".wa-product");

                that.deleteProduct($product).then( function(api) {
                    $product.remove();
                    that.updateCart(api);
                });
            });
        };

        Cart.prototype.initEditProduct = function() {
            var that = this;

            that.$wrapper.on("click", ".js-edit-product", function(event) {
                event.preventDefault();
                showDialog($(this).closest(".wa-product"));
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

                $product.find(".wa-service.is-active").each( function() {
                    var $service = $(this),
                        service_id = $service.data("service-id");

                    var $variant = $service.find(".js-variant-field"),
                        variant_id = $variant.val();

                    if (service_id && variant_id) {
                        result.push({
                            name: "service[" + service_id + "]",
                            value: variant_id
                        });
                    }
                });

                return result;
            }

            function showDialog($product) {
                var data = getData($product),
                    item_id = $product.data("id");

                that.lock(true);

                var href = that.urls["product_edit"];

                $.post(href, data)
                    .always( function() {
                        that.lock(false);
                    }).done( function(html) {
                        new window.waOrder.ui.Dialog({
                            $wrapper: $(html),
                            options: {
                                scope: that,
                                item_id: item_id,
                                $product: $product,
                                onSubmit: function(sku_id) {
                                    var deferred = $.Deferred();

                                    $product.data("sku-id", sku_id);
                                    var result = that.saveCart();

                                    deferred.resolve(result);

                                    result.then( function(api) {
                                        that.reload();
                                    });

                                    return deferred.promise();
                                }
                            }
                        });
                    });
            }
        };

        Cart.prototype.initChangeQuantity = function() {
            var that = this,
                toggle_disable_class = "is-disabled";

            that.$products.on("click", ".js-increase", function(event) {
                event.preventDefault();
                var $toggle = $(this);
                if (!$toggle.hasClass(toggle_disable_class)) {
                    var $product = $toggle.closest(".wa-product");
                    set($product, true);
                }
            });

            that.$products.on("click", ".js-decrease", function(event) {
                event.preventDefault();
                var $toggle = $(this);
                if (!$toggle.hasClass(toggle_disable_class)) {
                    var $product = $toggle.closest(".wa-product");
                    set($product, false);
                }
            });

            that.$products.find("input.js-product-quantity").each( function() {
                var $input = $(this);

                $input.data("before_value", $input.val());

                $input
                    .on("change", function() {
                        that.hideErrors( $(this).closest(".wa-quantity-section") );
                        onChange($(this));
                    })
                    .on("update", function(event, data) { onUpdate($(this), data); });
            });

            function set($product, increase) {
                // DOM
                var $input = $product.find(".js-product-quantity");

                // VARS
                var is_disabled = ($input.is(":disabled")),
                    current_val = parseFloat($input.val()),
                    new_val = current_val + ( increase ? 1 : -1 );

                if (!is_disabled) {
                    if (new_val > 0) {
                        $input.val(new_val).trigger("change");
                    } else {
                        that.deleteProduct($product).then( function(api) {
                            $product.remove();
                            that.updateCart(api);
                        });
                    }
                }
            }

            function onChange($input) {
                // DOM
                var $product = $input.closest(".wa-product");

                // VARS
                var value = getValue($input.val()),
                    item_id = $product.data("id");

                $input.val(value).attr("disabled", true);
                updateToggles($input);

                if (value > 0) {
                    $input.data("before_value", value);

                    that.saveCart()
                        .always( function() {
                            $input.attr("disabled", false);
                        })
                        .then( function(api) {
                            that.updateCart(api);
                        });

                } else {
                    that.deleteProduct($product).then( function(api) {
                        $product.remove();
                        that.updateCart(api);
                    }, function(state) {
                        var before_value = $input.data("before_value");
                        if (before_value) {
                            $input.val(before_value);
                        }
                    });
                }

                function getValue(value) {
                    var result = 1;

                    value = value.replace(",",".");

                    var converted_value = parseFloat(value);
                    if (!isNaN(converted_value)) {
                        result = converted_value;
                    }

                    return result;
                }
            }

            function onUpdate($input, data) {
                var $section = $input.closest(".wa-quantity-section"),
                    $product = $section.closest(".wa-product"),
                    $price = $product.find(".js-product-price"); // quantity price

                var disabled_product_class = "is-out-of-stock",
                    error_product_class = "is-more-than-limit",
                    field_error_class = "wa-error";

                var value = parseFloat($input.val());

                // set data
                if (data.max > 0) {
                    $input.attr("data-max", data.max).data("max", data.max);
                } else {
                    $input.removeAttr("data-max").removeData("max");
                }

                // render errors
                if (data.max === 0) {
                    $input.addClass(field_error_class);
                    $price.hide();

                    getError().text(that.locales["quantity_empty"]).appendTo($section);
                    $product.addClass(disabled_product_class);

                } else if (data.max < value) {
                    $input.addClass(field_error_class);
                    $price.hide();

                    var text = that.locales["quantity_stock_error"].replace("%s", data.max);
                    getError().text(text).appendTo($section);
                    $product.addClass(error_product_class);

                } else if (data.errors) {

                    if (data.errors["quantity"]) {
                        $input.addClass(field_error_class);
                        $price.hide();

                        getError().text(data.errors["quantity"]).appendTo($section);
                        $product.addClass(error_product_class);
                    }

                } else {

                    if (value !== data.quantity) {
                        $input.val(data.quantity).data("before_value", data.quantity);
                        updateToggles($input);
                    }

                    if (data.quantity > 1) { $price.show(); } else { $price.hide(); }
                    $input.removeClass(field_error_class);
                }

                setTooltip();

                function getError() {
                    return $(that.templates["error"]);
                }

                function setTooltip() {
                    var reduce_text = that.locales["quantity_decrease"],
                        add_text = that.locales["quantity_increase"];

                    if (data.max === 0 || value === 1) {
                        reduce_text = that.locales["quantity_delete"];
                    }
                    if (value >= data.max) {
                        add_text = that.locales["quantity_limit"];
                    }

                    $section.find(".js-decrease .wa-tooltip").attr("data-title", reduce_text);
                    $section.find(".js-increase .wa-tooltip").attr("data-title", add_text);
                }
            }

            function updateToggles($input) {
                var $box = $input.closest(".wa-quantity-box"),
                    $right_toggle = $box.find(".js-increase");

                var value = $input.val(),
                    max = $input.data("max");

                if (max > 0 && value >= max) {
                    $right_toggle.addClass(toggle_disable_class);
                } else {
                    $right_toggle.removeClass(toggle_disable_class);
                }
            }
        };

        Cart.prototype.initChangeService = function() {
            var that = this;

            that.$wrapper.on("change", ".wa-service input.js-service-field", onChangeService);

            that.$wrapper.on("change", ".wa-service .js-variant-field", onChangeVariant);

            //

            function onChangeService() {
                // DOM
                var $input = $(this),
                    $product = $input.closest(".wa-product"),
                    $service = $input.closest(".wa-service"),
                    $select = $service.find(".wa-variant select.js-variant-field");

                // VARS
                var is_checked = $input.is(":checked"),
                    item_id = $product.data("id"),
                    service_id = $service.data("service-id"),
                    service_variant_id = $service.find(".js-variant-field:first").val();

                var active_class = "is-active";

                if (is_checked) {

                    var data = {
                        "item[parent_id]": item_id,
                        "item[service_id]": service_id,
                        "item[service_variant_id]": service_variant_id
                    };

                    $input.attr("disabled", true).trigger("disabled");

                    that.addService(data)
                        .always( function() {
                            $input.attr("disabled", false).trigger("enabled");
                        })
                        .then(function(api) {

                            if (!api.just_added_item) {
                                that.DEBUG(api.errors, "error");
                                that.reload();

                            } else {
                                var new_id = api.just_added_item.id;
                                $service
                                    .addClass(active_class)
                                    .attr("data-id", new_id).data("id", new_id)
                                    .attr("data-enabled", "1").data("enabled", 1);

                                if ($select.length) { $select.attr("disabled", false).trigger("enabled"); }

                                that.DEBUG("Service added.", "info", $service[0], api);

                                if (that.cart_save_promise) {
                                    that.cart_save_promise.then( function(api) {
                                        that.updateCart(api);
                                    });
                                } else {
                                    that.updateCart(api);
                                }
                            }
                        }, function(state, data) {
                            that.DEBUG("Add service is aborted.", "warn", state, data);
                            $input.attr("checked", false);
                        });

                } else {

                    $service.removeClass(active_class).attr("data-enabled", "0").data("enabled", 0);
                    if ($select.length) { $select.attr("disabled", true).trigger("disabled"); }

                    $input.attr("disabled", true).trigger("disabled");

                    that.saveCart()
                        .always( function() {
                            $input.attr("disabled", false).trigger("enabled");
                        })
                        .then( function(api) {
                            that.updateCart(api);
                        });
                }
            }

            function onChangeVariant() {
                var $select = $(this);

                $select.attr("disabled", true).trigger("disabled");

                that.saveCart()
                    .always( function() {
                        $select.attr("disabled", false).trigger("enabled");
                    })
                    .then( function(api) {
                        that.DEBUG("Service variant Changed.", "info", $select[0]);
                        that.updateCart(api);
                    });
            }

        };

        Cart.prototype.initCoupon = function() {
            var that = this;

            var $wrapper = that.$coupon_section;
            if (!$wrapper.length) { return false; }

            var $input = $wrapper.find("input.js-coupon-code");

            $wrapper.on("click", ".js-use-coupon", function(event) {
                event.preventDefault();
                var value = $input.val();
                value.length ? use() : cancel();
            });

            $wrapper.on("click", ".js-cancel-coupon", function(event) {
                event.preventDefault();
                cancel();
            });

            that.$wrapper.on("before_update", removeErrors);

            $input.on("focus", removeErrors);

            function use() {
                $wrapper.attr("data-enabled", "1").data("enabled", 1);

                that.saveCart().then( function(api) {
                    that.updateCart(api);
                });
            }

            function cancel() {
                $wrapper.attr("data-enabled", "0").data("enabled", 0);
                $input.val("");

                that.saveCart().then( function(api) {
                    that.updateCart(api);
                });
            }

            function removeErrors() {
                $wrapper.find(".js-error-text").remove();
            }
        };

        Cart.prototype.initAffiliate = function() {
            var that = this;

            var $wrapper = that.$affiliate_section;
            if (!$wrapper.length) { return false; }

            $wrapper.on("click", ".js-use-bonus", function(event) {
                event.preventDefault();
                $wrapper.attr("data-enabled", "1").data("enabled", 1);

                that.saveCart().then( function(api) {
                    that.updateCart(api);
                });
            });

            $wrapper.on("click", ".js-cancel-bonus", function(event) {
                event.preventDefault();
                $wrapper.attr("data-enabled", "0").data("enabled", 0);

                that.saveCart().then( function(api) {
                    that.updateCart(api);
                });
            });
        };

        // PROTECTED METHODS

        Cart.prototype.updateCart = function(api) {
            var that = this;
            // Do not do anything if render is scheduled already
            // (update timer if we have newer api data though)
            if (that.render_scheduled) {
                if (that.render_scheduled !== true) {
                    clearTimeout(that.render_scheduled);
                    schedule();
                }
                return;
            }

            /*
            // Delay rendering until cart/add requests are finished
            if (that.add_services_count > 0) {
                that.render_scheduled = true;
                that.add_services_deferred.then(function() {
                    that.render_scheduled = false;
                    that.updateCart(api);
                });
                return;
            }

            // Delay rendering until cart/save requests are finished
            if (that.cart_save_promise) {
                that.render_scheduled = true;
                that.cart_save_promise.then(function(api) {
                    that.render_scheduled = false;
                    that.updateCart(api);
                });
                return;
            }
            */

            // schedule rendering (no more often than once every 50ms)
            schedule();

            function schedule() {
                that.render_scheduled = setTimeout(function() {
                    that.render_scheduled = false;
                    that.renderCart(api);
                }, 50);
            }
        };

        /**
         * @param {Object} api
         * @return {Boolean}
         * */
        Cart.prototype.renderCart = function(api) {
            var that = this;

            if (!api) {
                that.DEBUG("API is required!", "error");
                return false;
            }

            that.hideErrors();

            // Get products
            var $products = that.$products.find(".wa-product"),
                products = {};

            $products.each( function() {
                var $product = $(this),
                    item_id = $product.data("id");

                products[item_id] = $product;
            });

            updateItems(api.cart.items).then( function() {
                renderCouponSection();
                renderAffiliateSection();
                updateTotals();

                if (api.errors) {
                    renderErrors(api.errors);
                }

                that.DEBUG("UI updated.", "info");
                that.trigger("rendered", api);

            }, function() {
                that.DEBUG("UI and Data API distinctions", "error");
                that.reload();
            });

            function updateItems(items) {
                var deferred = $.Deferred(),
                    items_count = Object.keys(items).length;

                if ($products.length !== items_count) {
                    deferred.reject();

                } else {

                    $.each(items, function(i, item) {
                        var item_id = item["id"],
                            $product = products[item_id];

                        if ($product) {
                            setProductData($product, item);
                        } else {
                            deferred.reject();
                        }
                    });

                    deferred.resolve();
                }

                return deferred.promise();

                function setProductData($product, api_product) {
                    var quantity = parseFloat(api_product.quantity);

                    //quantity
                    var $quantity = $product.find("input.js-product-quantity");
                    $quantity.trigger("update", {
                        quantity: quantity,
                        max: parseFloat(api_product.stock_count),
                        errors: ( api_product.errors ? api_product.errors : null )
                    });

                    // price
                    var $full_price = $product.find(".js-product-full-price"),
                        full_price = api_product.full_price + (api_product.discount > 0 ? -api_product.discount : 0);
                    $full_price.html( that.formatPrice(full_price) );

                    // discount
                    var $discount_wrapper = $product.find(".js-product-discount");
                    if ($discount_wrapper.length) {
                        var $discount = $discount_wrapper.find(".js-discount");
                        if (api_product.discount > 0) {
                            $discount_wrapper.show();
                            $discount.html( that.formatPrice(api_product.discount) );
                        } else {
                            $discount_wrapper.hide();
                            $discount.html( that.formatPrice(0) );
                        }
                    }

                    // compare
                    var $compare_wrapper = $product.find(".js-product-compare");
                    if ($compare_wrapper.length) {
                        var compare_price = ( api_product.discount > 0 ? api_product.full_price : null );
                        if (compare_price > 0) {
                            $compare_wrapper.show()
                                .html( that.formatPrice(compare_price) );
                        } else {
                            $compare_wrapper.hide()
                                .html( that.formatPrice(0) );
                        }
                    }

                    // services
                    var $services = $product.find(".wa-service"),
                        services = {},
                        api_services = api_product.services,
                        api_services_count = ( api_services ? Object.keys(api_services).length : 0 );

                    if ($services.length !== api_services_count) {
                        that.DEBUG("DOM services !== server services", "error", $product[0]);
                    }

                    $services.each( function() {
                        var $service = $(this),
                            service_id = $service.data("service-id");
                        if (service_id) { services[service_id] = $service; }
                    });

                    $.each(api_services, function(api_service_id, api_service) {
                        if (!services[api_service_id]) { return; }

                        var $service = services[api_service_id],
                            $checkbox = $service.find("input.js-service-field"),
                            $select = $service.find(".wa-variant select.js-variant-field"),
                            item_id = api_service["id"],
                            is_active = !!item_id;

                        var active_class = "is-active";

                        if (is_active) {
                            $checkbox.attr("checked", true);
                            $service
                                .addClass(active_class)
                                .attr("data-id", item_id).data("id", item_id)
                                .attr("data-enabled", "1").data("enabled", 1);
                            if ($select.length) { $select.attr("disabled", false).trigger("enabled"); }

                        } else {
                            $checkbox.attr("checked", false);
                            $service
                                .removeClass(active_class)
                                .attr("data-id", "").removeData("id")
                                .attr("data-enabled", "0").data("enabled", 0);
                            if ($select.length) { $select.attr("disabled", true).trigger("disabled"); }
                        }
                    });
                }
            }

            function updateTotals() {
                var cart = api.cart;

                $("#wa-cart-subtotal .js-price").html( that.formatPrice(cart.subtotal) );
                $("#wa-cart-total .js-price").html( that.formatPrice(cart.total) );

                // discount
                var $full_discount_wrapper = $("#wa-cart-full-discount");
                if ($full_discount_wrapper) {
                    var $full_discount = $full_discount_wrapper.find(".js-price");

                    if (cart.discount > 0) {
                        $full_discount_wrapper.show();
                        $full_discount.html( that.formatPrice(cart.discount) );
                    } else {
                        $full_discount_wrapper.hide();
                        $full_discount.html( that.formatPrice(0) );
                    }
                }

                // discount
                var $discount_wrapper = $("#wa-cart-discount");
                if ($discount_wrapper.length) {
                    var $discount = $discount_wrapper.find(".js-price");
                    if (cart.discount > 0) {
                        var partial_discount = cart.discount;

                        if (api.coupon_discount > 0) {
                            partial_discount -= api.coupon_discount;
                        }

                        if (api.affiliate && api.affiliate.use_affiliate && api.affiliate.affiliate_discount > 0) {
                            partial_discount -= api.affiliate.affiliate_discount;
                        }

                        $discount_wrapper.show();
                        $discount.html( that.formatPrice(partial_discount) );
                    } else {
                        $discount_wrapper.hide();
                        $discount.html( that.formatPrice(0) );
                    }
                }

                // coupon
                var $coupon_wrapper = $("#wa-cart-discount-coupon");
                if ($coupon_wrapper.length) {
                    var $coupon = $coupon_wrapper.find(".js-price");
                    if (api.coupon_discount > 0) {
                        $coupon_wrapper.show();
                        $coupon.html( that.formatPrice(api.coupon_discount) );
                    } else {
                        $coupon_wrapper.hide();
                        $coupon.html( that.formatPrice(0) );
                    }
                }

                // affiliate
                var $affiliate_wrapper = $("#wa-cart-discount-affiliate");
                if ($affiliate_wrapper.length) {
                    var $affiliate = $affiliate_wrapper.find(".js-price");
                    if (api.affiliate && api.affiliate.use_affiliate && api.affiliate.affiliate_discount > 0) {
                        $affiliate_wrapper.show();
                        $affiliate.html( that.formatPrice(api.affiliate.affiliate_discount) );
                    } else {
                        $affiliate_wrapper.hide();
                        $affiliate.html( that.formatPrice(0) );
                    }
                }

                var $affiliate_text = $("#wa-affiliate-order-bonus");
                if ($affiliate_text.length) {
                    if (api.affiliate && api.affiliate.add_affiliate_bonus > 0) {
                        $affiliate_text.html( that.locales["affiliate_bonus"].replace("%s", api.affiliate.add_affiliate_bonus)).show();
                    } else {
                        $affiliate_text.hide().html("");
                    }
                }

                var $affiliate_discount = $("#wa-affiliate-order-discount");
                if ($affiliate_discount.length) {
                    var affiliate_discount = (api.affiliate && api.affiliate.affiliate_discount > 0 ? api.affiliate.affiliate_discount : 0);
                    $affiliate_discount.html( that.formatPrice(affiliate_discount) );
                }

                // weight
                var $weight = that.$wrapper.find("#wa-cart-weight"),
                    weight_locale = getWeightLocale();

                if (that.weight_enabled && weight_locale) {
                    $weight.html(weight_locale).show();
                } else {
                    $weight.hide().html("");
                }

                function getWeightLocale() {
                    var result = false,
                        template = that.locales["weight_total"];

                    if (cart.count_html && cart.total_weight_html) {
                        result = template.replace("%s", cart.count_html).replace("%s", cart.total_weight_html);
                    }

                    return result;
                }
            }

            function renderErrors(errors) {
                that.DEBUG("ERRORS:", "warn", errors);
            }

            function renderCouponSection() {
                var $wrapper = that.$coupon_section;
                if (!$wrapper.length) { return false; }

                if (api.coupon_code && api.coupon_code.length) {
                    if (!api.coupon_discount && !api.coupon_free_shipping) {
                        toggleCoupon(false, [that.locales["coupon_invalid"]]);
                    } else {
                        toggleCoupon(true);
                    }
                } else {
                    toggleCoupon(false);
                }

                function toggleCoupon(show, errors) {
                    var active_class = "is-active";

                    if (typeof show !== "boolean") {
                        show = !$wrapper.hasClass(active_class);
                    }

                    if (show) {
                        $wrapper.addClass(active_class);
                    } else {
                        $wrapper.removeClass(active_class);
                    }

                    if (errors && errors.length) {
                        $.each(errors, function(i, text) {
                            showErrors(text);
                        });
                    }

                    function showErrors(text) {
                        $(that.templates["error"]).text(text).appendTo($wrapper);
                    }
                }
            }

            function renderAffiliateSection() {
                var $wrapper = that.$affiliate_section;
                if (!$wrapper.length) { return false; }

                if (api.affiliate.use_affiliate) {
                    toggle(true);
                } else {
                    toggle(false);
                }

                function toggle(show) {
                    var active_class = "is-active";

                    if (typeof show !== "boolean") {
                        show = !$wrapper.hasClass(active_class);
                    }

                    if (show) {
                        $wrapper.addClass(active_class);
                        $wrapper.attr("data-enabled", "1").data("enabled", 1);

                    } else {
                        $wrapper.removeClass(active_class);
                        $wrapper.attr("data-enabled", "0").data("enabled", 0);
                    }
                }
            }

            return true;
        };

        /**
         * @return {Promise}
         */
        Cart.prototype.saveCart = function () {
            var that = this;

            // there's no more that one cart/save "thread"
            if (that.cart_save_promise) {
                // previous existing "thread" will restart after abort
                if (that.update_xhr) {
                    that.update_xhr.abort();
                }

                // but the promise is still correct
                return that.cart_save_promise;
            }

            // this deferred is resolved once cart/save request is completed
            // (may take more than one request if some are cancelled)
            var result_deferred = $.Deferred();
            that.cart_save_promise = result_deferred.promise();
            that.cart_save_promise.always( function() {
                that.cart_save_promise = null;
                that.update_xhr = null;
            });

            restart();

            return that.cart_save_promise;

            // attempt to run cart/save until it completes
            // restarts itself if aborted by addService()
            function restart() {
                that.add_services_deferred.promise().then( function() {
                    // get data from DOM
                    var cart_data = that.getCartData();

                    that.trigger("before_update", that);

                    that.update_xhr = $.post(that.urls["save"], cart_data, "json")
                        .done( function(response) {
                            // save succeeded
                            if (response.status === "ok") {
                                var api = formatAPI(response.data);
                                that.DEBUG("API received:", "info");
                                that.DEBUG(api);

                                that.trigger("updated", api);
                                that.trigger("changed", api);

                                result_deferred.resolve(api);

                            // validation error
                            } else {
                                that.DEBUG("API not received.", "error", ( response.errors ? response.errors : "No error description") );
                                result_deferred.reject("fail", response);
                            }
                        })
                        .fail( function(jqXHR, state) {
                            if (state === "abort") {
                                // this can only be aborted if cart/add just started
                                // restart after cart/add completes
                                that.add_services_deferred.promise().then(restart);

                                // server error
                            } else {
                                that.DEBUG("Getting API aborted.", "error", state);
                                result_deferred.reject(state);
                            }
                        });
                });
            }

        };

        /**
         * @description collect cart data for server requests
         * @return {object}
         * */
        Cart.prototype.getCartData = function() {
            var that = this;

            var result = {
                items: getItems(),
                coupon: getCoupon(),
                affiliate: getAffiliate()
            };

            that.DEBUG("Cart data for save:", "info");
            that.DEBUG(result);

            return result;

            function getItems() {
                var result = [];

                that.$products.find(".wa-product").each( function() {
                    var $product = $(this),
                        item_id = $product.data("id"),
                        sku_id = $product.data("sku-id");

                    var $quantity = $product.find("input.js-product-quantity"),
                        quantity = parseFloat($quantity.val());

                    quantity = ( quantity > 0 ? quantity : 0 );

                    result.push({
                        id: item_id,
                        sku_id: sku_id,
                        quantity: quantity,
                        services: getServices($product)
                    });
                });

                return result;

                function getServices($product) {
                    var result = [];

                    $product.find(".wa-service").each( function() {
                        var $service = $(this),
                            service_id = $service.data("id");

                        if (service_id) {
                            var $field = $service.find(".js-variant-field"),
                                variant_id = $field.val();

                            var is_enabled = ( $service.data("enabled") === 1 ? 1 : 0 );

                            result.push({
                                id: service_id,
                                service_variant_id: variant_id,
                                enabled: is_enabled
                            });
                        }
                    });

                    return result;
                }
            }

            function getCoupon() {
                var result = {
                    enabled: 0,
                    code: ""
                };

                if (that.$coupon_section.length) {
                    result["enabled"] = (that.$coupon_section.data("enabled") === 1 ? 1 : 0);

                    var $input = that.$coupon_section.find(".js-coupon-code");
                    if ($input.length) {
                        var code = $input.val();
                        if (code.length) {
                            result["code"] = code;
                        }
                    }
                }

                return result;
            }

            function getAffiliate() {
                var result = {
                    enabled: 0
                };

                if (that.$affiliate_section.length) {
                    result["enabled"] = (that.$affiliate_section.data("enabled") === 1 ? 1 : 0);
                }

                return result;
            }
        };

        Cart.prototype.hideErrors = function($wrapper) {
            var that = this;

            $wrapper = ($wrapper && $wrapper.length ? $wrapper : that.$wrapper);

            var error_field_class = "wa-error",
                error_text_class = "js-error-text";

            if ($wrapper.hasClass(error_field_class)) {
                $wrapper.removeClass(error_field_class);

            } else if ($wrapper.hasClass(error_text_class)) {
                $wrapper.remove();

            } else {
                $wrapper.find("." + error_field_class).removeClass(error_field_class);
                $wrapper.find(".js-error-text").remove();
            }

            if ($wrapper[0] === that.$wrapper[0]) {
                var disabled_product_class = "is-more-than-limit",
                    error_product_class = "is-out-of-stock";

                $wrapper.find(".wa-product")
                    .removeClass(disabled_product_class)
                    .removeClass(error_product_class);
            }
        };

        /**
         * @param {string} event_name
         * @param {Object|Array?} data
         * */
        Cart.prototype.trigger = function(event_name, data) {
            var that = this;

            var cart_event_name = "wa_order_cart_" + event_name;

            that.$wrapper.triggerHandler(event_name, (data || null));
            that.$outer_wrapper.trigger(cart_event_name, (data || null));
        };

        // PUBLIC METHODS

        /**
         * @param {string|number} price
         * @param {boolean?} text
         * @return {string}
         * */
        Cart.prototype.formatPrice = function(price, text) {
            var that = this,
                result = price;

            if (window.waOrder.ui.formatPrice) {
                result = window.waOrder.ui.formatPrice(price, text);
            }

            return result;
        };

        /**
         * @param {Object} data
         * @return {Promise}
         * */
        Cart.prototype.addService = function(data) {
            var that = this;

            if (that.add_services_count === 0) {
                /**
                 * This deferred is resolved once ALL add requests are complete.
                 * Contains no data. Cannot be rejected, it always resolves.
                 * Used in saveCart() to run cart/save requests only when it's safe.
                 * */
                that.add_services_deferred = $.Deferred();
            }
            that.add_services_count += 1;

            /**
             * cancel ongoing cart/save request
             * see saveCart() for how variable that.update_xhr is updated
             */
            if (that.update_xhr) {
                that.update_xhr.abort();
            }

            // this deferred is resolved once THIS request is complete
            var result_deferred = $.Deferred();

            $.post(that.urls["add"], data, "json")
                .done( function(response) {
                    if (response.status === "ok") {
                        var api = formatAPI(response.data);
                        that.DEBUG("Service successfully added.", "info", api);
                        that.trigger("changed", api);
                        result_deferred.resolve(api);
                    } else {
                        that.DEBUG("Add service canceled.", "warn", response);
                        result_deferred.reject("fail", response);
                    }
                })
                .fail( function(jqXHT, state) {
                    that.DEBUG("Add service is failed.", "error", state);
                    result_deferred.reject(state);
                })
                .always(function() {
                    that.add_services_count -= 1;
                    if (that.add_services_count === 0) {
                        that.add_services_deferred.resolve();
                    }
                });

            return result_deferred.promise();
        };

        /**
         * @param {Object} $product jQuery product node
         * @return {Promise}
         * */
        Cart.prototype.deleteProduct = function($product) {
            var that = this,
                deferred = $.Deferred(),
                $quantity = $product.find("input.js-product-quantity");

            getConfirmation().then( function() {
                var item_id = $product.data("id");

                $quantity.val(0).attr("disabled", true).trigger("disabled");

                that.saveCart()
                    .always( function() {
                        $quantity.attr("disabled", false).trigger("enabled");
                    })
                    .then( function(api) {
                        var items_count = 0;
                        if (api && api.cart && (typeof api.cart.items === "object")) {
                            items_count = Object.keys(api.cart.items).length;
                        }

                        if (items_count > 0) {
                            deferred.resolve(api);
                        } else {
                            location.reload();
                        }

                    }, function(state) {
                        deferred.reject(state);
                    });

            }, function() {
                var value = parseFloat($quantity.val());
                if ( !(value > 0) ) { $quantity.val("1"); }

                $quantity.attr("disabled", false).trigger("enabled");
                deferred.reject("cancel");
            });

            return deferred.promise();

            /**
             * @return boolean
             * */
            function getConfirmation() {
                var deferred = $.Deferred(),
                    result = false;

                var template = that.templates["delete_product_dialog"],
                    name = $product.find(".wa-details-section .wa-details .wa-name").text();

                template = template.replace("%message%", name);

                new window.waOrder.ui.Dialog({
                    $wrapper: $(template),
                    onOpen: function($wrapper, dialog) {
                        $wrapper.on("click", ".js-confirm", function() {
                            result = true;
                            dialog.close();
                        });

                        $wrapper.on("click", ".js-cancel", function() {
                            dialog.close();
                        });
                    },
                    onClose: function() {
                        if (result) {
                            deferred.resolve();
                        } else {
                            deferred.reject();
                        }
                    }
                });

                return deferred.promise();
            }
        };

        /**
        * @param {object?} options
        * */
        Cart.prototype.clear = function(options) {
            var that = this,
                deferred = $.Deferred();

            options = (options ? options : {});

            if (that.clear_xhr) {
                deferred.reject("running");

            } else {
                if (options["confirm"]) {
                    getConfirmation().then( function() {
                        init();
                    }, function() {
                        deferred.reject("cancel");
                    });
                } else {
                    init();
                }
            }

            return deferred.promise();

            function init() {
                var cart_data = that.getCartData();

                $.each(cart_data.items, function(i, item) {
                    item.quantity = 0;
                });

                if (cart_data.affiliate) {
                    cart_data.affiliate.enabled = 0;
                }

                if (cart_data.coupon) {
                    cart_data.coupon.code = "";
                    cart_data.coupon.enabled = 0;
                }

                that.clear_xhr = $.post(that.urls["save"], cart_data, "json")
                    .done( function(response) {
                        if (response.status === "ok") {
                            var api = formatAPI(response.data);
                            that.DEBUG("Cart is cleared.", "info", response);
                            that.trigger("cleared", api);
                            deferred.resolve(response);

                        } else {
                            that.DEBUG("Cart isn't cleared.", "error", ( response.errors ? response.errors : "No error description") );
                            deferred.reject("fail");
                        }
                    })
                    .fail( function(jqXHR, state) {
                        that.DEBUG("Getting API aborted.", "error", state);
                        deferred.reject(state);
                    })
                    .always( function() {
                        that.clear_xhr = null;
                    });
            }

            /**
             * @return boolean
             * */
            function getConfirmation() {
                var deferred = $.Deferred(),
                    result = false;

                new window.waOrder.ui.Dialog({
                    $wrapper: $(that.templates["clear_cart_dialog"]),
                    onOpen: function($wrapper, dialog) {
                        $wrapper.on("click", ".js-confirm", function() {
                            result = true;
                            dialog.close();
                        });

                        $wrapper.on("click", ".js-cancel", function() {
                            dialog.close();
                        });
                    },
                    onClose: function() {
                        if (result) {
                            deferred.resolve();
                        } else {
                            deferred.reject();
                        }
                    }
                });

                return deferred.promise();
            }
        };

        /**
         * @return {promise}
         * */
        Cart.prototype.reload = function() {
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

        Cart.prototype.lock = function(do_lock) {
            var that = this,
                locked_class = "is-locked";

            if (do_lock) {
                that.$wrapper.addClass(locked_class);

            } else {
                that.$wrapper.removeClass(locked_class);
            }
        };

        return Cart;

        function formatAPI(api) {
            var result = api;

            if (result.errors) {
                if (result.errors.items) {
                    $.each(result.errors.items, function(item_id, errors) {
                        if (result.cart.items && result.cart.items[item_id]) {
                            result.cart.items[item_id]["errors"] = errors;
                        }
                    });
                }
            }

            return result;
        }

    })($);

    window.waOrder = (window.waOrder || {});

    window.waOrder.Cart = Cart;

})(jQuery);
