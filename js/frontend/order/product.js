( function($) { "use strict";

    var Product = ( function($) {

        Product = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$skus = that.$wrapper.find(".wa-skus-wrapper");
            that.$services = that.$wrapper.find(".wa-services-wrapper");
            that.$amount = that.$wrapper.find(".js-quantity-field");
            that.$price = that.$wrapper.find(".js-product-price");
            that.$comparePrice = that.$wrapper.find(".js-product-price-compare");
            that.$button = that.$wrapper.find(".js-submit-button");

            // VARS
            that.dialog = that.$wrapper.data("dialog");
            that.scope = that.dialog.options.scope;
            that.currency = options["currency"];
            that.services = options["services"];
            that.features = options["features"];
            that.locales = options["locales"];
            that.images = options["images"];
            that.skus = options["skus"];
            that.added_class = "is-added";

            // DYNAMIC VARS
            that.sku_id = parseFloat( options["sku_id"] );
            that.price = parseFloat( options["price"] );
            that.compare_price = parseFloat( options["price"] );

            // INIT
            that.init();

            console.log( that );
        };

        Product.prototype.init = function() {
            var that = this;

            // Change Feature
            that.$wrapper.on("click", ".wa-feature-wrapper .wa-variant", function(event) {
                event.preventDefault();
                that.changeFeature( $(this) );
            });

            // Change SKU
            that.$skus.on("change", ".js-sku-field", function() {
                var $input = $(this),
                    is_disabled = $input.data("disabled"),
                    is_active = ($input.attr("checked") === "checked");

                if (is_active) {
                    var sku_id = $input.val(),
                        sku = that.skus[sku_id];

                    that.changeSKU(sku, !is_disabled);
                }
            });

            // Change Service
            that.$services.on("change", ".js-service-field", function() {
                that.changeService( $(this) );
            });

            // Change Variant
            that.$services.on("change", ".js-variant-select", function() {
                that.updatePrice();
            });

            //
            // initFirstSku();

            that.initSubmit();

            //

            function initFirstSku() {
                var $features = that.$wrapper.find(".wa-features-wrapper"),
                    is_buttons_view_type = !!$features.length;

                // for features type
                if (is_buttons_view_type) {
                    var $selected = $features.find(".wa-variant.selected");
                    if ($selected.length) {
                        $selected.trigger("click");
                    } else {
                        that.DEBUG("First SKU is missing", "error");
                    }

                    // for radio type
                } else {
                    var $radio = that.$skus.find(".js-sku-field:checked");
                    if ($radio.length) {
                        $radio.trigger("change");
                    } else {
                        that.DEBUG("First SKU is missing", "error");
                    }
                }
            }
        };

        Product.prototype.DEBUG = function() {
            var that = this,
                log_function = console.log;

            var styles = {
                "hint": "font-weight: bold; color: #666;",
                "info": "font-weight: bold; font-size: 1.25em; color: blue;",
                "warn": "font-weight: bold; font-size: 1.25em;",
                "error": "font-weight: bold; font-size: 1.25em;"
            };

            if (that.scope && that.scope.options && that.scope.options.DEBUG) {
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

        Product.prototype.initSubmit = function() {
            var that = this,
                is_locked = false;

            that.$button.on("click", function(event) {
                event.preventDefault();

                if (!is_locked) {
                    is_locked = true;

                    that.dialog.lock(true);

                    that.dialog.options.onSubmit(that.sku_id)
                        .always( function() {
                            is_locked = false;
                            that.dialog.lock(false);
                        })
                        .done( function() {
                            that.dialog.close();
                        });
                }
            });
        };

        Product.prototype.changeSKU = function(sku, available) {
            var that = this;

            if (!sku) {
                that.DEBUG("SKU is missing", "error");
            }

            that.$amount.val(sku.order_count_min);

            //
            renderSKU(sku.sku);
            //
            changeImage(sku.image_id);
            //
            that.updateStocks(sku.id);
            //
            that.updateServices(sku.id);
            //
            that.updatePrice(sku.price, sku.compare_price);

            if (available) {
                that.$button.removeAttr("disabled");
            } else {
                that.$button.attr("disabled", true);
            }

            that.sku_id = parseFloat(sku.id);

            that.dialog.resize();

            //

            function renderSKU(sku_name) {
                var $sku = $(".js-product-sku"),
                    $wrapper = $sku.closest(".wa-sku-wrapper");

                if (sku_name) {
                    $sku.text(sku_name);
                    $wrapper.show();
                } else {
                    $sku.text("");
                    $wrapper.hide();
                }
            }

            function changeImage(image_id) {
                var image = that.images["default"];

                if (image_id && that.images[image_id]) {
                    image = that.images[image_id];
                }

                $("img#js-product-image").attr("src", image.uri_200);
            }
        };

        Product.prototype.changeFeature = function($link) {
            var that = this;

            var $feature = $link.closest('.wa-feature-wrapper'),
                $field = $feature.find(".js-feature-field"),
                variant_id = $link.data("variant-id"),
                active_class = "selected";

            $feature.find(".wa-variant." + active_class).removeClass(active_class);

            $link.addClass(active_class);

            $field.val(variant_id).trigger("change");

            var feature = getFeature();
            if (feature) {
                var sku_id = feature.id,
                    sku = that.skus[sku_id];

                if (sku) {
                    that.updateAvailable(true);
                    that.changeSKU(sku, feature.available);
                } else {
                    that.DEBUG("SKU id error", "error");
                }
            } else {
                that.updateAvailable(false);
            }

            function getFeature() {
                var feature_id = "",
                    result = null;

                that.$wrapper.find(".wa-feature-wrapper .js-feature-field").each( function () {
                    var $input = $(this);

                    feature_id += $input.data("feature-id") + ':' + $input.val() + ';';
                });

                var feature = that.features[feature_id];
                if (feature) {
                    result = feature;
                }

                return result;
            }
        };

        Product.prototype.changeService = function($input) {
            var that = this,
                $service = $input.closest(".wa-service-wrapper"),
                $select = $service.find(".js-variant-select");

            if ($select.length) {
                var is_active = $input.is(":checked");
                if (is_active) {
                    $select.removeAttr("disabled");
                } else {
                    $select.attr("disabled", "disabled");
                }
            }

            that.updatePrice();
        };

        Product.prototype.updateServices = function(sku_id) {
            var that = this;

            var services = that.services[sku_id];

            $.each(services, function(service_id, service) {
                var $service = that.$wrapper.find(".wa-service-wrapper[data-id='" + service_id + "']");
                if (!$service.length) {
                    that.DEBUG("Service is missing");
                    return true;
                }

                if (!service) {
                    $service.hide()
                        .find("input, select").attr("disabled", true).removeAttr("checked");

                } else {
                    $service.show()
                        .find("input").removeAttr("disabled");

                    if (typeof service === "string") {
                        $service.find(".js-service-price").html( window.waOrder.ui.formatPrice(service) );
                        $service.find(".js-service-field").data("price", service);

                    } else {

                        var $select = $service.find(".js-variant-select"),
                            selected_variant_id = $select.val(),
                            has_active = false;

                        $select.html("");

                        $.each(service, function(variant_id, variant) {
                            if (variant) {
                                if (variant_id === selected_variant_id) {
                                    has_active = true;
                                }

                                var option = '<option data-price="%price%" value="%value%">%name% (+%formatted_price%)</option>',
                                    name = variant[0],
                                    price = variant[1];

                                option = option
                                    .replace("%value%", variant_id)
                                    .replace("%price%", price)
                                    .replace("%name%", name)
                                    .replace("%formatted_price%", window.waOrder.ui.formatPrice(price, true));

                                $select.append(option);
                            }
                        });

                        if (has_active) {
                            $select.val(selected_variant_id);
                        } else if ($select.length) {
                            $select[0].selectedIndex = 0;
                        }
                    }
                }
            });
        };

        Product.prototype.updateStocks = function(sku_id) {
            var that = this;

            var $wrapper = that.$wrapper.find(".wa-stocks-wrapper"),
                $stocks = $wrapper.find(".wa-stock-wrapper");

            $stocks.each( function() {
                var $stock = $(this),
                    stock_sku_id = $stock.data("sku-id");

                if (sku_id && (stock_sku_id + "" === sku_id + "")) {
                    $stock.show();
                } else {
                    $stock.hide();
                }
            });
        };

        Product.prototype.updatePrice = function(price, compare_price) {
            var that = this;

            price = parseFloat(price);
            compare_price = parseFloat(compare_price);

            var hidden_class = "is-hidden";

            // DOM
            var $price = that.$price,
                $compare = that.$comparePrice;

            // VARS
            var services_price = 0,
                // services_price = getServicePrice(),
                price_sum,
                compare_sum;

            //
            if (price) {
                that.price = price;
                $price.data("price", price);
            } else {
                price = that.price;
            }

            //
            if (compare_price >= 0) {
                that.compare_price = compare_price;
                $compare.data("price", compare_price);
            } else {
                compare_price = that.compare_price;
            }

            //
            price_sum = (price + services_price);
            compare_sum = (compare_price + services_price);

            // Render Price
            $price.html( window.waOrder.ui.formatPrice(price_sum) );
            $compare.html( window.waOrder.ui.formatPrice(compare_sum) );

            // Render Compare
            if (compare_price > 0) {
                $compare.show();
            } else {
                $compare.hide();
            }

            //
            function getServicePrice() {
                // DOM
                var $checked_services = that.$services.find(".js-service-field:checked");

                // DYNAMIC VARS
                var services_price = 0;

                $checked_services.each( function () {
                    var $field = $(this),
                        $service = $field.closest(".wa-service-wrapper"),
                        $variant_select = $service.find(".js-variant-select");

                    var service_value = $field.val(),
                        service_price = 0;

                    if ($variant_select.length) {
                        service_price = parseFloat( $variant_select.find(":selected").data("price") );
                    } else {
                        service_price = parseFloat( $field.data("price") );
                    }

                    services_price += service_price;
                });

                return services_price;
            }
        };

        Product.prototype.updateAvailable = function(available) {
            var that = this;

            that.updateStocks(null);

            that.$wrapper.find(".sku-not-available").css("display", (available ? "none" : ""));

            that.$wrapper.find(".wa-prices-wrapper").css("display", (!available ? "none" : ""));

            that.$button.attr("disabled", !available);
        };

        return Product;

    })($);

    window.waOrder = (window.waOrder || {});

    window.waOrder.CartProductDialog = Product;

})(jQuery);
