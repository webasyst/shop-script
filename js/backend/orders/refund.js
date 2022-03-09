var OrderRefundSection = ( function($) {

    OrderRefundSection = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form");
        that.$products = that.$wrapper.find(".s-products-table");
        that.$submit_button = that.$form.find(".js-submit-button");
        that.$messages_place = that.$form.find(".s-messages-place");

        // CONST
        that.templates = options["templates"];
        that.currency_info = options["currency_info"];
        that.total_price = options["total_price"];
        that.locales = options["locales"];

        // DYNAMIC VARS

        // INIT
        that.init();
    };

    OrderRefundSection.prototype.init = function() {
        var that = this;

        that.initSubmit();

        if (that.$products.length) {
            that.initModeToggle();
        }

        that.initRefundToggle();

        that.$wrapper.on("click", ".js-close-section", function(event) {
            event.preventDefault();
            $('#workflow-content').empty().hide();
            $('.workflow-actions').show();
        });
    };

    OrderRefundSection.prototype.initModeToggle = function() {
        var that = this;

        var $check_all = that.$products.find(".js-check-all"),
            $total_price = that.$wrapper.find(".js-total-price"),
            $amount_fields = that.$products.find(".js-quantity-field:not(.is-disabled)");

        var disabled_class = "is-disabled";

        var $refund_plugins_message = null;

        // EVENTS

        that.$wrapper.on("change", ".js-mode-toggle", function () {
            var $toggle = that.$wrapper.find(".js-mode-toggle:checked"),
                is_active = $toggle.is(":checked");

            if (is_active) {
                var value = $toggle.val();
                if (value === "full") {
                    that.$products.hide()
                        .find("input:not(.is-disabled)").attr("disabled", true);

                    $total_price.html(that.total_price);
                    that.$submit_button.attr("disabled", false);

                    checkPlugins(true);

                } else {
                    that.$products.show()
                        .find("input:not(.is-disabled)").attr("disabled", false);

                    $amount_fields.val(0).trigger("change");

                    checkPlugins(false);
                }
            }

            that.$wrapper.trigger("refresh");

            function checkPlugins(full_refund) {
                if (full_refund) {
                    if ($refund_plugins_message) {
                        $refund_plugins_message.remove();
                        $refund_plugins_message = null;
                    }
                } else {
                    if (that.templates["uncorrected_refund_plugins_message"]) {
                        $refund_plugins_message = $(that.templates["uncorrected_refund_plugins_message"]).appendTo(that.$messages_place);
                    }
                }
            }
        });

        that.$products.on("keydown", ".js-quantity-field", function(event) {
            var key = event.keyCode,
                is_enter = ( key === 13 );

            if (is_enter) {
                event.preventDefault();
                $(this).trigger("change");
            }
        });

        that.$products.on("keyup click", ".js-quantity-field", function() {
            var $invalid_quantity = $(this).parents('.s-product-wrapper').find('.s-product-invalid-quantity');

            if ($(this).is(':invalid')) {
                $invalid_quantity.show();
            } else {
                $invalid_quantity.hide();
            }
        });

        that.$products.on("change", ".js-quantity-field", function(event, force) {
            var $field = $(this),
                $product = $field.closest(".s-product-wrapper"),
                value = validate($field);

            $field.val(value);

            $product
                .attr("data-quantity", value)
                .data("quantity", value);

            if (!force) {
                var $checkbox = $product.find(".js-product-checkbox");
                $checkbox.attr("checked", value > 0).trigger("change", true);
            }

            $product.find(".js-quantity-text").html(value);

            renderServicesQuantity($product, value);

            updatePrices();

            function validate($field) {
                var value = $field.val(),
                    max = parseFloat( $field.attr("max") );

                if (!parseFloat(value)) { value = 0; }
                if (value < 0 ) { value = 0; }
                if (max > 0 && value > max) { value = max; }

                return value;
            }
        });

        that.$products.on("change", ".js-product-checkbox", function(event, force) {
            var $field = $(this),
                $product = $field.closest(".s-product-wrapper"),
                $quantity = $product.find(".js-quantity-field");

            var is_active = $field.is(":checked");
            if (is_active) {
                $product.removeClass(disabled_class);
            } else {
                $product.addClass(disabled_class);
            }

            if (!force) {
                var max = ( is_active ? ( parseFloat($quantity.attr("max")) || 1 ) : 0 );
                $quantity.val(max).trigger("change", true);
            }
            $quantity.click();

            var $checkboxes = that.$products.find(".js-product-checkbox:not(.is-disabled)"),
                count = $checkboxes.length,
                active_count = $checkboxes.filter(":checked").length;

            $check_all.attr("checked", (active_count === count));
        });

        $check_all.on("change", function() {
            var is_active = $(this).is(":checked");

            if (is_active) {
                $amount_fields.each( function() {
                    var $field = $(this),
                        max = ( parseFloat($field.attr("max")) || 1 );

                    $field.val(max);
                });
            } else {
                $amount_fields.val(0);
            }

            $amount_fields.trigger("change");
        });

        // FUNCTIONS

        function updatePrices() {
            var total_price = 0,
                total_quantity = 0;

            that.$products.find(".s-product-wrapper").each( function() {
                var $product = $(this),
                    $product_total = $product.find(".js-product-total-price"),
                    is_disabled = $product.hasClass(disabled_class),
                    price = parseFloat($product.data("price")),
                    quantity = parseFloat($product.data("quantity"));

                if (is_disabled) {
                    quantity = 0;
                }

                var product_price = price * quantity;

                $product_total.html( formatPrice(product_price) );

                total_price += product_price;
                total_quantity += quantity;
            });

            that.$submit_button.attr("disabled", !(total_quantity > 0));

            $total_price.html( formatPrice(total_price) );
        }

        /**
         * @param {object} $product
         * @param {string|number} amount
         * */
        function renderServicesQuantity($product, amount) {
            var product_ident = $product.data("product-ident");

            var $product_services = findProducts(product_ident).filter(".is-service");
            $product_services.each( function() {
                var $service = $(this),
                    $amount = $service.find(".js-quantity-text"),
                    $total = $service.find(".js-product-total-price");

                var price = parseFloat($product.data("price")),
                    service_price = price * amount;

                $service
                    .attr("data-quantity", amount)
                    .data("quantity", amount);

                if (amount) {
                    $service.removeClass(disabled_class);
                } else {
                    $service.addClass(disabled_class);
                }

                $amount.html(amount);
                $total.html( formatPrice(service_price, that.currency_info) );
            });
        }

        /**
         * @param {string} product_ident
         * */
        function findProducts(product_ident) {
            return that.$products.find(".s-product-wrapper[data-product-ident='" + product_ident + "']");
        }

        /**
         * @param {string|number} total_price
         * */
        function formatPrice(total_price) {
            return formatPriceCurrency(total_price, that.currency_info);
        }

        /**
         * @param {string|number} price
         * @param {Object} format
         * @param {boolean?} text
         * @return {string}
         * */
        function formatPriceCurrency(price, format, text) {
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
    };

    OrderRefundSection.prototype.initSubmit = function() {
        var that = this,
            is_locked = false;

        that.$form.on("submit", function(event) {
            event.preventDefault();

            if (!is_locked) {
                is_locked = true;
                that.$submit_button.attr('disabled', true);

                var href = that.$form.attr('action'),
                    data = that.$form.serializeArray();

                $.post(href, data,"json")
                    .always( function() {
                        that.$submit_button.attr("disabled", false);
                        is_locked = false;
                    }).done( function() {
                        // this event is used in CRM app
                        that.$form.trigger("formSend");

                        if ($.order && $.order.reload) {
                            $.order.reload();
                        }
                    });
            }
        });
    };

    OrderRefundSection.prototype.initRefundToggle = function() {
        var that = this;

        var $total_label = that.$wrapper.find(".js-total-label");

        that.$wrapper.on("change", ".js-refund-checkbox", function() {
            if (this.checked) {
                that.$messages_place.append(that.templates["warning_message"]);
                $total_label.html(that.locales["auto_refund"]);
            } else {
                that.$messages_place.html("");
                $total_label.html(that.locales["manual_refund"]);
            }
        });
    };

    return OrderRefundSection;

})(jQuery);