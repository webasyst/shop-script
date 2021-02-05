$.order_edit = {

    /**
     * {Number}
     */
    id: 0,

    /**
     * {Jquery object}
     */
    container: null,

    /**
     * {Jquery object}
     */
    form: null,

    /**
     * {ShopBackendOrderEditorCustomerForm}
     */
    customer_form: null,

    /**
     * {jQuery}
     */
    $storefront_selector: null,

    /**
     * {Array}
     */
    stocks: [],

    /**
     * On/off edit mode
     * {Boolen}
     */
    slide_on: false,

    /**
     * {Object}
     */
    options: {},

    locales: {},

    init: function (options) {
        this.options = options;
        if (options.id) {
            this.id = options.id;
        }
        this.container = typeof options.container === 'string' ? $(options.container) : options.container;
        this.form = typeof options.form === 'string' ? $(options.form) : options.form;

        this.$storefront_selector = $('#order-storefront');

        options.stocks.sort(function (a, b) {
            return a.sort - b.sort;
        });
        this.stocks = options.stocks;

        if (options.title) {
            document.title = title;
        }

        if (!options.float_delimeter) {
            options.float_delimeter = '.';
        }

        if (options.locales) {
            this.locales = options.locales;
        }

        //VAL
        this.float_delimeter = options.float_delimeter;
        this.prev_ship_method = null;

        /**
         * @event order_edit_init
         */
        this.container.trigger('order_edit_init');

        //INIT
        this.initView();
        this.initShippingControl();
        this.initDiscountControl();
        this.initCouponControl();
        this.initCustomerSourceControl(options.customer_sources);
    },

    /**
     * It is external method to call,
     * If not call - customer form not be inited (will be NULL)
     * So inside of order_edit always check customer_form for the not NULL before call its methods
     * @param {Object} options
     */
    initCustomerForm: function (options) {
        var that = this;
        that.customer_form = new ShopBackendOrderEditorCustomerForm($('#s-order-edit-customer'), that, options);
    },

    initView: function () {
        var that = this,
            options = that.options;

        this.initStorefrontSelector();

        // helpers and handlers here
        var updateStockIcon = function (order_item) {
            var select = order_item.find('.s-orders-sku-stock-select');
            var option = select.find('option:selected');
            var sku_item = order_item.find('.s-orders-skus').find('input[type=radio]:checked').parents('li:first');

            order_item.find('.s-orders-stock-icon-aggregate').show();
            order_item.find('.s-orders-stock-icon').html('').hide();

            // choose item to work with
            var item = sku_item.length ?
                sku_item :   // sku case
                order_item;  // product case (one sku)

            if (option.attr('data-icon')) {
                item.find('.s-orders-stock-icon-aggregate').hide();
                item.find('.s-orders-stock-icon').html(
                    option.attr('data-icon')
                ).show();
                order_item.find('.s-orders-stock-icon .s-stock-left-text').show();
                item.find('.s-orders-stock-icon .s-stock-left-text').hide();
            }
        };

        $.order_edit.slide(true, options.mode == 'add');

        this.container.find('.back').click(function () {
            if ($.order_edit.id) {
                $.order_edit.slide(false);
            } else {
                $.order_edit.slide(false, true);
            }
            $.orders.back();
            return false;
        });

        //Set container.data('order-content')
        this.getOrderItems(this.container, true);

        $('.s-order-item').each(function () {
            var item = $(this);
            updateStockIcon(item);
        });

        $('#order-currency').change(function () {
            $('#order-items .currency').html($(this).val());
            $.order_edit.options.currency = $(this).val();
        });

        var price_edit = options.price_edit || false;

        //Added new product in order
        var add_order_input = $("#orders-add-autocomplete");
        add_order_input.autocomplete({
            source: '?action=autocomplete&with_counts=1',
            minLength: 3,
            delay: 300,
            select: function (event, ui) {

                $('.s-order-errors').empty();
                var url = '?module=orders&action=getProduct&product_id=' + ui.item.id + '&order_id=' + $.order_edit.id + '&currency=' + $.order_edit.options.currency,
                    storefront = that.getStorefront();
                if (storefront) {
                    url += '&storefront=' + storefront;
                }

                $.getJSON(url, function (r) {
                    var table = $('#order-items');
                    var index = parseInt(table.find('.s-order-item:last').attr('data-index'), 10) + 1 || 0;
                    var product = r.data.product;
                    if (product.sku_id && product.skus[product.sku_id]) {
                        product.skus[product.sku_id].checked = true;
                    }

                    if ($('#order-currency').length && !$('#order-currency').attr('disabled')) {
                        $('<input type="hidden" name="currency">').val($('#order-currency').val()).insertAfter($('#order-currency'));
                        $('#order-currency').attr('disabled', 'disabled');
                    }

                    var add_row = $('#s-orders-add-row');
                    add_row.before(tmpl('template-order', {
                        data: r.data, options: {
                            index: index,
                            currency: $.order_edit.options.currency,
                            stocks: $.order_edit.stocks,
                            price_edit: price_edit
                        }
                    }));
                    var item = add_row.prev();

                    $('#s-order-comment-edit').show();
                    $.order_edit.updateTotal();

                    updateStockIcon(item);

                });
                add_order_input.val('');

                return false;
            }
        });

        // Select product SKU
        this.container.off('change', '.s-orders-skus input[type=radio]').on('change', '.s-orders-skus input[type=radio]',
            function () {
                var self = $(this);
                var tr = self.parents('tr:first');
                var li = self.closest('li');
                var sku_id = this.value;
                var product_id = tr.attr('data-product-id');
                var index = tr.attr('data-index');
                var mode = $.order_edit.id ? 'edit' : 'add';
                var item_id = null;
                if (mode == 'edit') {
                    item_id = parseInt(self.attr('name').replace('sku[edit][', ''), 10);
                }

                var url = '?module=orders&action=getProduct&product_id=' + product_id + '&sku_id=' + sku_id + '&currency=' + $.order_edit.options.currency,
                    storefront = that.getStorefront();
                if (storefront) {
                    url += '&storefront=' + storefront;
                }

                $.getJSON(url, function (r) {
                    var ns;
                    if (tr.find('input:first').attr('name').indexOf('add') !== -1) {
                        ns = 'add';
                    } else {
                        ns = 'edit';
                    }

                    tr.find('.s-orders-services').replaceWith(
                        tmpl('template-order-services-' + ns, {
                            services: r.data.sku.services,
                            service_ids: r.data.service_ids,
                            product_id: product_id,
                            options: {
                                price_edit: price_edit,
                                index: index,
                                currency: $.order_edit.options.currency,
                                stocks: $.order_edit.stocks
                            }
                        })
                    );
                    tr.find('.s-orders-product-price').find('input').val(r.data.sku.price);

                    tr.find('.s-orders-sku-stock-place').empty();
                    li.find('.s-orders-sku-stock-place').html(
                        tmpl('template-order-stocks-' + ns, {
                            sku: r.data.sku,
                            index: index,
                            stocks: $.order_edit.stocks,
                            item_id: item_id   // use only for edit namespace
                        })
                    );

                    updateStockIcon(tr);
                    $.order_edit.updateTotal();

                });
            }
        );

        // change stocks select
        this.container.off('change', '.s-orders-sku-stock-select').on('change', '.s-orders-sku-stock-select', function () {
            var item = $(this).parents('tr.s-order-item:first');
            updateStockIcon(item);
        });

        var updateServicePriceInput = function (variant_option, service_input, update_val) {
            var price = variant_option.attr('data-price');
            var percent_price = variant_option.attr('data-percent-price');
            if (update_val) {
                service_input.val(price);
            }
            service_input.attr('data-price', price);
            service_input.attr('data-percent-price', percent_price);
        };
        this.container.off('change', '.s-orders-service-variant').on('change', '.s-orders-service-variant', function () {
            var self = $(this);
            var option = self.find('option:selected');
            var li = self.parents('li:first');
            updateServicePriceInput(option, li.find('.s-orders-service-price'), true);
        });
        this.container.find('.s-orders-service-variant').each(function () {
            var item = $(this);
            var option = item.find('option:selected');
            var li = item.parents('li:first');
            updateServicePriceInput(option, li.find('.s-orders-service-price'), false);
        });

        this.container.off('click', '.s-order-item-delete').on('click', '.s-order-item-delete', function () {
            var self = $(this);
            self.parents('tr:first').remove();

            if (!$('table#order-items').find('tr.s-order-item:first').length) {
                $('#s-order-comment-edit').hide();
            }

            $.order_edit.updateTotal();
            return false;
        });

        // calculations
        this.container.off('change', '.s-orders-services input').on('change', '.s-orders-services input', $.order_edit.updateTotal);
        this.container.off('change', '.s-orders-product-price input').on('change', '.s-orders-product-price input', function () {
            var $this = $(this),
                $scope = $this.parents('tr:first');
            $.order_edit.updateServicePriceInPercent($scope);
            $.order_edit.updateTotal();
        });
        this.container.off('change', '.s-orders-services .s-orders-service-variant').on('change', '.s-orders-services .s-orders-service-variant',
            $.order_edit.updateTotal
        );

        $("#payment_methods").change(function () {
            var pid = $(this).val();
            $("#payment-info > div").hide();
            $.order_edit.updateTotal();

            if ($('#payment-custom-' + pid).length) {
                $('#payment-custom-' + pid).show();
            }
        });


        this.container.off('keydown', '.s-orders-quantity').on('keydown', '.s-orders-quantity', function () {
            var self = $(this);
            var timer_id = self.data('timer_id');
            if (timer_id) {
                clearTimeout(timer_id);
            }
            self.data('timer_id', setTimeout(function () {
                $.order_edit.updateTotal();
            }, 450));
        });

        if (this.form && this.form.length) {

            var orderSaveSubmit = function () {

                var form = $(this);
                if (orderSaveSubmit.xhr) {
                    return false;
                }
                $.order_edit.showValidateErrors();

                // submit optimization
                // disable that services that aren't checked
                var selector = '.s-orders-services input[name^="service"][type="checkbox"]:not(:checked)'
                    + ',' + '.s-orders-services input.js-fake-service-selected[type="checkbox"]:not(:checked)';
                $(selector, this.form).each(function () {
                    var item = $(this);
                    item.closest('li').find(':input').attr('disabled', true);
                });

                if (!$.order_edit.container.find('.error').length) {

                    //Disable submit button
                    $.order_edit.switchSubmitButton('disable');

                    var onAlwaysSubmit = function () {
                        orderSaveSubmit.xhr = null;
                        //Allow submit button
                        $.order_edit.switchSubmitButton();
                        $('.s-orders-services input:disabled', form).attr('disabled', false);
                    };
                    if ($.order_edit.id) {
                        orderSaveSubmit.xhr = $.order_edit.saveSubmit(onAlwaysSubmit, 'edit');
                    } else {
                        orderSaveSubmit.xhr = $.order_edit.saveSubmit(onAlwaysSubmit, 'add');
                    }
                }
                return false;
            };

            this.form.unbind('sumbit').bind('submit', orderSaveSubmit);
        }

        //Formatting values from a database
        $("#subtotal").text(this.roundFloat($("#subtotal").text()));
        $("#total").text(this.roundFloat($("#total").text()));
        $('#discount').val(this.roundFloat($('#discount').val()));
        $('#tax').text(this.roundFloat($('#tax').text()));

        /**
         * @event order_edit_init_view
         */
        this.container.trigger('order_edit_init_view');
    },

    initShippingControl: function () {
        var that = this;
        that.prev_ship_method = $("#shipping_methods").val();

        $("#shipping-custom").on('change', ':input', function (e) {
            /**
             * handle only related changes
             */
            $.shop.trace('#shipping-custom change', [e, this]);
            if (e.originalEvent && $(this).data('affects-rate')) {
                $.order_edit.updateTotal();
            }
        });

        //First load set info.
        that.setShippingInfo();

        $("#shipping_methods").change(function () {
            var option = $("#shipping_methods option:selected"),
                rate = option.data('rate') || 0,
                $shipping_rate = $('#shipping-rate');

            // Update cost if it is not entered by hand
            if (!$shipping_rate.data('shipping') || option.val() != that.prev_ship_method) {
                $shipping_rate.val($.order_edit.formatFloat(rate));
                $shipping_rate.data('shipping', false);
                that.prev_ship_method = option.val();
            }

            that.setShippingInfo();
            $.order_edit.updateTotal();
        });

        //Prevent shipping cost updates
        $('#shipping-rate').change(function () {
            var $self = $(this);
            if ($self.val() < 0) {
                alert($.order_edit.locales.wrong_cost);
                $self.val(0);
                return false;
            }
            $('#shipping-rate').data('shipping', $(this).val());
            $.order_edit.updateTotal();
        });

        /**
         * @event order_edit_init_shipping
         */
        this.container.trigger('order_edit_init_shipping');
    },

    initDiscountControl: function () {
        var $discount_input = $('#discount');
        var $discount_description_input = $('#discount-description');
        var $update_discount_button = $('#update-discount');
        var $edit_discount_button = $('#edit-discount');
        var $tooltip_icon = $('#discount-tooltip-icon');

        // Tooltip to show how discounts were calculated
        $tooltip_icon.tooltip({
            showURL: false,
            bodyHandler: function () {
                return $discount_description_input.val() || $discount_description_input.data('updated-manually-msg');
            }
        });

        $update_discount_button.tooltip({
            showURL: false,
            bodyHandler: function () {
                return $update_discount_button.data('description') || $discount_description_input.data('updated-manually-msg');
            }
        });

        if ($edit_discount_button.length) {
            $tooltip_icon.show();
        } else {
            // Nothing to show yet
            $tooltip_icon.hide();
        }

        // When we get recalculated discount, update the fields accordingly
        $('#order-edit-form').on('order_total_updated', function (e, data) {
            $discount_input.show();
            $discount_input.parent().find('span.js-order-discount:first').hide();
            $edit_discount_button.hide();

            //Set Advanced information about discount
            $update_discount_button.data('description', data.discount_description);

            if (!data.discount) {
                return;
            }

            // Remember recalculated discount value and description
            $update_discount_button
                .data('value', data.discount)
                .data('description', data.discount_description)
                .data('items_discount', data.items_discount)
            ;

            //Update old discount
            if ($update_discount_button.data('discount') === 'calculate') {
                $discount_description_input.val($update_discount_button.data('description'));

                //Set or update discount html in all order position
                var items_discount = $update_discount_button.data('items_discount') || [];
                for (var index = 0; index < items_discount.length; index++) {
                    if (items_discount[index]) {
                        var selector = '#order-items span.js-item-total-discount';
                        selector += '[data-discount-id="' + items_discount[index]['selector'] + '"]';
                        var $discount = $(selector);
                        if ($discount.length) {
                            $discount.html(items_discount[index]['html']).show();
                        }
                    }
                }

                updateTooltip();
            }

            if ($update_discount_button.data('discount') === 'calculate') {
                // When value in discount input matches previous recalculation,
                // but new recalculated discount is different,
                // update visible fields immediately
                if ($.order_edit.parseFloat(data.discount) !== $.order_edit.parseFloat($discount_input.val())) {
                    $update_discount_button.click();
                }
            } else {
                // Otherwise, make user decide whether they want to recalculate the discount
                $update_discount_button.show();
            }
        });

        var hide_manual_edit = function () {
            $discount_input.show();
            $discount_input.parent().find('span.js-order-discount:first').hide();
            $edit_discount_button.hide();

            $('.js-item-total-discount').hide();
        };

        $edit_discount_button.click(function () {
            hide_manual_edit();

            $discount_description_input.val('');
            return false;
        });

        // When user clicks the update button, put discount value and description to fields
        // and hide the button itself
        $update_discount_button.click(function () {
            hide_manual_edit();

            $discount_input.val($update_discount_button.data('value') || 0).change();
            $discount_description_input.val($update_discount_button.data('description'));

            $discount_input.attr('title', $discount_description_input.data('edit-manually-msg'));

            $update_discount_button.hide().data('discount', 'calculate');
            $.order_edit.updateTotal();
            updateTooltip();
            return false;
        });

        // When user updates the discount field by hand, show the button to reset to calculated values
        $discount_input.on('change', function () {
            $edit_discount_button.click();
            $discount_description_input.val('');
            $('#order-items .js-item-total-discount').hide();
            $discount_input.attr('title', null);

            //Set discount value
            $update_discount_button.show().data('discount', $(this).val());

            $.order_edit.updateTotal();

            updateTooltip();
        });

        /**
         * Show/hide and animation discount calculate button
         */
        function updateTooltip() {
            if ($.order_edit.parseFloat($discount_input.val()) > 0) {
                $tooltip_icon.stop().show();
            } else {
                $tooltip_icon.stop().hide();
            }

            //Animation
            var duration = 25;
            var delta = 50;
            if ($tooltip_icon.is(':visible')) {
                $tooltip_icon.finish()
                    .fadeOut(duration += delta)
                    .fadeIn(duration += delta)
                    .fadeOut(duration += delta)
                    .fadeIn(duration += delta)
                    .fadeOut(duration += delta)
                    .fadeIn(duration += delta)
                    .fadeOut(duration += delta)
                    .fadeIn(duration += delta); // sorry doge
            }
        }

        /**
         * @event order_edit_init_discount
         */
        this.container.trigger('order_edit_init_discount');
    },

    initCouponControl: function () {
        var $coupon_id = $('#coupon_id'),
            $coupon_code = $('#coupon-code'),
            $coupon_code_label = $('.coupon-code-label'),
            $js_no_coupon_text = $('.js-no-coupon-text'),
            $js_coupon_icon = $('.js-coupon-icon'),
            $js_edit_coupon = $('.js-edit-coupon'),
            $js_close_coupon = $('.js-close-coupon'),
            $js_delete_coupon = $('.js-delete-coupon'),
            $update_discount_button = $('#update-discount'),
            $js_coupon_invalid_msg = $('#js-coupon-invalid-msg');

        $js_edit_coupon.click(function() {
            $(this).add($js_no_coupon_text).add($js_coupon_icon).add($coupon_code_label).hide();
            $coupon_code.data('value', $coupon_code.val());
            $coupon_code.val('').show();
            $js_close_coupon.show();
        });

        $js_close_coupon.click(function () {
            $coupon_code.val($coupon_code.data('value'));
            $js_edit_coupon.show();
            if ($coupon_id.val() === "") {
                $js_no_coupon_text.show();
            } else {
                $js_coupon_icon.add($coupon_code_label).show();
            }
            $(this).add($coupon_code).add($js_coupon_invalid_msg).hide();
        });

        $js_delete_coupon.click(function () {
            $(this).add($coupon_code).add($js_coupon_icon).add($coupon_code_label).add($js_edit_coupon).add($js_close_coupon).add($js_coupon_invalid_msg).hide();
            $coupon_id.val('');
            $update_discount_button.click();
            $js_no_coupon_text.add($js_edit_coupon).show();
        });

        $('.disabled-link').click(function(e) {
           e.preventDefault();
        });

        $coupon_code.autocomplete({
            source: function (request, response) {
                $.ajax({
                    type: "POST",
                    url: '?module=marketing&action=CouponsAutocomplete',
                    data: {
                        term: $coupon_code.val(),
                        products: $('input[name^="product["]').serializeArray()
                    },
                    success: function (data) {
                        response(data);
                        if (!data.length) {
                            showCouponInvalidMessage();
                        } else {
                            $js_coupon_invalid_msg.hide();
                        }
                    },
                    error: function () {
                        response([]);
                    }
                });
            },
            minLength: 1,
            delay: 300,
            select: function (event, ui) {
                if (ui.item.data.valid) {
                    $coupon_code.val(ui.item.label).hide();
                    $coupon_id.val(ui.item.value);
                    $coupon_code_label.html(ui.item.label).show();
                    $js_edit_coupon.add($js_coupon_icon).add($js_delete_coupon).show();
                    if (ui.item.data.right) {
                        $('.s-order-edit-coupon').attr('href', $('.s-order-edit-coupon').data('href') + ui.item.value);
                        if (ui.item.value.length != 0) {
                            $update_discount_button.click();
                            $('.s-order-edit-coupon').removeClass('disabled-link');
                        }
                    } else if (ui.item.value.length != 0) {
                        $update_discount_button.click();
                    }
                    $js_close_coupon.hide();
                } else {
                    $coupon_code.val(ui.item.label);
                    showCouponInvalidMessage();
                }
                return false;
            }
        });

        function showCouponInvalidMessage() {
            $js_coupon_invalid_msg.show();
            $js_coupon_invalid_msg.css('padding-right', $('.coupon-controls').width() + 4 + 'px');
        }
    },

    initCustomerSourceControl: function (customer_sources) {
        var $input = $('#customer-source');
        $input.autocomplete({
            delay: 0,
            minLength: 0,
            appendTo: '#order-edit-form',
            source: function (request, response) {
                var result = customer_sources.filter(function (v) {
                    return v && v.indexOf && v.indexOf(request.term) >= 0;
                }).slice(0, 10);
                if (result.length == 1 && result[0] == request.term) {
                    response([]);
                } else {
                    response(result);
                }
            }
        }).on('focus', function () {
            $input.autocomplete('search');
        });
    },

    /**
     * Enumerates all the product and tries to update the services as a percentage.
     */
    initUpdateServicePrice: function () {
        var items = this.container.find('.s-order-item');
        items.each(function () {
            $.order_edit.updateServicePriceInPercent($(this));
        })
    },

    /**
     * Updates services as a percentage
     * @param $product_row
     * @returns {null}
     */
    updateServicePriceInPercent: function ($product_row) {
        var price = $product_row.find('.js-order-edit-item-price').val();

        if (typeof price === "undefined") {
            return null;
        }

        $product_row.find('[data-currency="%"]').each(function () {
            var service = $(this),
                p = price * (service.data('percentPrice') / 100),
                p = $.order_edit.roundFloat(p);
            service.val($.order_edit.formatFloat(p));
            service.attr('data-price', p);
        });
    },

    setShippingInfo: function () {
        var $methods = $("#shipping_methods"),
            $option = $methods.children(':selected'),
            $shipping_input = $('#shipping-rate'),
            $shipping_info = $('#shipping-info'),
            sid = $methods.val(),
            prev_sid = $methods.data('_shipping_id'),
            address_has_picked = $.order_edit.checkSelectedAddressFields();

        var delivery_info = [];

        if ($option.length) {
            if ($option.data('error') && !address_has_picked) {
                delivery_info.push('<span class="error">' + $option.data('error') + '</span>');
            }
            if ($option.data('est_delivery')) {
                delivery_info.push('<span class="hint est_delivery">' + $option.data('est_delivery') + '</span>');
            }

            $("#shipping-custom > div").hide();

            if (sid !== null) {
                sid = ('' + sid).replace(/\..+$/, '');
                if ($('#shipping-custom-' + sid).length) {
                    $('#shipping-custom-' + sid).show();
                }
            }

            if ($option.data('comment')) {
                delivery_info.push('<span class="hint">' + $option.data('comment') + '</span>');
            }

            if (delivery_info) {
                if ($option.data('error') && !address_has_picked) {
                    $shipping_input.addClass('error');
                    $methods.addClass('error');
                } else {
                    $shipping_input.removeClass('error');
                    $methods.removeClass('error');
                }
                $shipping_info.html(delivery_info.join('<br>')).show();
            } else {
                $shipping_input.removeClass('error');
                $shipping_info.empty().hide();
            }
        } else {
            $methods.addClass('error');
        }

        $.shop.trace('check shipping_methods id', [prev_sid, sid, $option]);
    },

    checkSelectedAddressFields: function () {
        var $shipping_city = $('[name="customer[address.shipping][city]"]'),
            $shipping_region = $('[name="customer[address.shipping][region]"]'),
            address_fields = [{
                'exists': $shipping_city.length > 0,
                'selected': $shipping_city.val() != ""
            }, {
                'exists': $shipping_region.length > 0,
                'selected': $shipping_region.val() != ""
            }];

        var count_exists_address_fields = 0,
            count_selected_address_fields = 0;
        for (var key in address_fields) {
            var address_field = address_fields[key];
            if (address_field.exists) {
                count_exists_address_fields++;
            }
            if (address_field.exists && address_field.selected) {
                count_selected_address_fields++;
            }
        }

        return count_exists_address_fields == count_selected_address_fields;
    },

    getOrderItems: function (container) {
        var items = [];

        var order_content = [];

        container.find('.s-order-item').each(function () {
            var tr = $(this),
                product_id = tr.find('input[name^="product"]').val(),
                services = [],
                price = $.order_edit.parseFloat(tr.find('.s-orders-product-price input').val()),
                quantity = $.order_edit.parseFloat(tr.find('input.s-orders-quantity').val()),
                stock_id = tr.find('select.s-orders-sku-stock-select').val(),
                item_id = tr.data('item-id');

            // get SKU id
            var sku_input = tr.find('input[name^=sku]:not(:radio)').add(tr.find('input[name^=sku]:checked')).first();
            var sku_id = sku_input.val() || 0;

            var order_item = {
                "product_id": product_id,
                "sku_id": sku_id,
                "services": []
            };

            if (tr.find('.s-orders-services').length) {
                tr.find('.s-orders-services input:checkbox:checked').each(function () {
                    var li = $(this).closest('li');
                    var service_price = $.order_edit.parseFloat(li.find('input.s-orders-service-price').val());
                    var service_id = $(this).val();
                    var service_variant_id = li.find(':input[name^="variant\["]').val();

                    services.push({
                        "id": service_id,
                        "price": service_price,
                        "variant_id": service_variant_id
                    });

                    order_item.services.push(service_id);
                });
            }
            order_item.services.sort();
            order_item.services = order_item.services.join('_');

            items.push({
                id: item_id,
                product_id: product_id,
                quantity: quantity,
                price: price,
                sku_id: sku_id,
                services: services,
                stock_id: stock_id
            });

            order_content.push(order_item);
        });

        order_content = this.reduceOrderContent(order_content);
        container.data('order-content', order_content);

        return items;
    },

    /**
     * @param {Object[]} order_content
     * @returns {String}
     */
    reduceOrderContent: function (order_content) {
        order_content.sort(function (a, b) {
            var delta = a.product_id - b.product_id;
            if (delta === 0) {
                delta = a.sku_id - b.sku_id;
            }
            if (delta === 0) {
                delta = a.services.localeCompare(b.services);
            }
            return delta;
        });

        return order_content.map(function (order_item) {
            return '*' + order_item.product_id + ':' + order_item.sku_id + ':' + order_item.services;
        }).join(';');
    },

    /**
     * Collects frequent calls updateTotal
     */
    updateTotal: (function () {
        var timeout = null;
        return updateTotal;

        function updateTotal() {
            if (timeout) {
                clearTimeout(timeout);
                timeout = setTimeout(realUpdateTotal, 250);
            } else {
                timeout = setTimeout(realUpdateTotal, 500);
            }
        }

        function realUpdateTotal() {
            timeout = null;
            $.order_edit.realUpdateTotal();
        }
    })(),

    realUpdateTotal: function () {
        var container = $.order_edit.container,
            $subtotal = $("#subtotal"),
            $total = $("#total"),
            $tax = $("#tax");
        if (!container.find('.s-order-item').length) {
            $subtotal.text(0);
            $total.text(0);
            var address_has_picked = $.order_edit.checkSelectedAddressFields();
            if (address_has_picked) {
                $("#shipping_methods, #shipping-rate").removeClass('error');
                $("#shipping-info").empty().hide();
            }
            return;
        }
        var that = this;

        //Disable submit button
        $.order_edit.switchSubmitButton('disable');

        //clear errors.
        $.order_edit.showValidateErrors();

        // Data for orderTotal controller
        var data = {};

        // Customer data to recalculate shipping
        var customer = $("#s-order-edit-customer").find('[name^="customer["]').serializeArray();
        for (var i = 0; i < customer.length; i++) {
            data[customer[i].name] = customer[i].value;
        }

        // For customer form need also storefront, cause functionality of form depends of storefront also
        data['storefront'] = that.getStorefront();

        data.items = $.order_edit.getOrderItems(container);

        //see discount property in shopOrder
        var update_discount = $('#update-discount').data('discount');
        if (update_discount !== undefined) {
            if (update_discount !== 'calculate') {
                update_discount = $.order_edit.parseFloat(update_discount);
            }
            data.discount = update_discount;
        }

        if ($.order_edit.id) {
            data.order_id = $.order_edit.id;
        } else {
            data['currency'] = $('#order-edit-form input[name=currency]').val();
        }

        var shipping_id = $('#shipping_methods').val(),
            payment_id = $('#payment_methods').val(),
            coupon_id = $('#coupon_id').val();

        //Send the cost of delivery of entered by hands
        if ($('#shipping-rate').data('shipping')) {
            data.shipping = $('#shipping-rate').data('shipping');
        }

        data['params'] = {shipping_id: shipping_id, coupon_id: coupon_id, payment_id: payment_id};
        data['customer[id]'] = data['contact_id'] = $('#s-customer-id').val();
        data.tax = 'calculate';

        if (shipping_id) {
            shipping_id = parseInt(shipping_id.split('.')[0]);
            if (shipping_id > 0) {
                //Retrieve shipping parameters
                $('#shipping-custom').find('>#shipping-custom-' + shipping_id + ' :input').each(function () {
                    data[this.name] = this.value;
                });
            }
        }
        // Fetch shipping options and rates, and other info from orderTotal controller
        $.ajax({
            "type": 'POST',
            "url": '?module=order&action=total',
            "data": data,
            "success": function (response) {
                if (response && response.status === 'ok') {
                    var $shipping_rate = $('#shipping-rate'),
                        el = $("#shipping_methods"),
                        el_selected = $.trim(el.val()) || '',
                        el_selected_id = el_selected.replace(/\W.+$/, '');

                    if (response.data) {
                        response.data = $.order_edit.parseTotalResponse(response.data);
                    }

                    //clear shipping data.
                    el.empty();
                    el.prepend('<option value=""></option>');

                    if (response.data.shipping_method_ids.length > 0) {
                        var custom_html_container = $('#shipping-custom');
                        custom_html_container.empty();

                        var shipping_method_ids = response.data.shipping_method_ids;
                        var shipping_methods = response.data.shipping_methods;

                        // exact match
                        var found = false,
                            problem_shipping = false;

                        if (shipping_method_ids.indexOf(el_selected) !== -1) {
                            found = true;
                        }

                        //If the delivery is not available, it will return and will contain an error.
                        //Available only on id plugin
                        if (!found && shipping_method_ids.indexOf(Number(el_selected_id)) !== -1) {
                            found = true;
                            problem_shipping = true;
                        }

                        custom_html_container.find('>div.fields.form').hide();

                        for (var i = 0; i < shipping_method_ids.length; i += 1) {
                            var ship_id = '' + shipping_method_ids[i],
                                ship = shipping_methods[ship_id],
                                plugin_id = ship_id.replace(/\W.+$/, ''),
                                o = $('<option></option>');

                            o.html(ship.name).attr('value', ship_id).data('rate', ship.rate);
                            o.data('error', ship.error || undefined);
                            o.data('est_delivery', ship.est_delivery || undefined);
                            o.data('comment', ship.comment || undefined);
                            o.data('external', ship.external || false);

                            el.append(o);

                            //If shipping not selected, select first
                            if (!el_selected && i === 0) {
                                $("#shipping-rate").val($.order_edit.formatFloat(ship.rate));
                            }

                            //Custom fields same for all positions of one plug-in
                            //They are stored only in the last rates ¯\_(ツ)_/¯
                            if (el_selected_id == plugin_id) {
                                if (ship.custom_data) {
                                    for (var custom_field in ship.custom_data) {
                                        if (ship.custom_data.hasOwnProperty(custom_field)) {
                                            o.data(custom_field, ship.custom_data[custom_field]);
                                        }
                                    }
                                }

                                if (ship.custom_html) {
                                    var custom_html = custom_html_container.find('#shipping-custom-' + plugin_id);
                                    if (!custom_html.length) {
                                        custom_html = custom_html_container.append('<div id="shipping-custom-' + plugin_id + '" class="fields form"></div>');
                                        custom_html = custom_html_container.find('#shipping-custom-' + plugin_id);
                                    }
                                    custom_html.html(ship.custom_html);
                                }
                            }

                            //If the selected id is not in the server's response, then find the delivery that starts with the same id, select its value and recount
                            if (!found) {
                                var ship_id_parts = ('' + ship_id).split('.');
                                var el_selected_parts = el_selected.split('.');

                                //if user did not choose delivery, no need to change anything. Or user turn off delivery
                                if (el_selected_parts[0] !== '') {
                                    //Search delivery by ID. If found - select first delivery option, and recalculate delivery value.
                                    if (ship_id_parts[0] !== undefined && el_selected_parts[0] !== undefined &&
                                        ship_id_parts[0] == el_selected_parts[0]) {
                                        el_selected = ship_id;
                                        el.val(el_selected);
                                        $.order_edit.setShippingInfo();
                                        $.order_edit.realUpdateTotal();
                                        return;
                                    }
                                }
                            }
                        }

                        el.val(el_selected);

                        //If there is a problematic delivery, choose it.
                        if (problem_shipping) {
                            el.val(el_selected_id)
                        }

                        el.data('_shipping_id', el_selected_id);
                        $.shop.trace('set shipping_methods id', [el_selected_id, el_selected]);
                    }
                    //If user deleted discount value, don't need set zero discount value
                    if (update_discount != '') {
                        $('#discount').val(response.data.discount);
                    }

                    //Update shipping rate.
                    $shipping_rate.val(response.data.shipping);

                    $('#order-edit-form').trigger('order_total_updated', response.data);

                    //Update order value after calculate.
                    $subtotal.text(response.data.subtotal);
                    $total.text(response.data.total);
                    $tax.text(response.data.tax);

                    $.order_edit.showValidateErrors(response.data.errors);
                    that.setShippingInfo();

                    //Allow submit button
                    $.order_edit.switchSubmitButton();
                } else {
                    //Allow submit button
                    $.order_edit.switchSubmitButton();
                }
            },
            "dataType": 'json'
        });
    },

    /**
     * If type == edit, show main orders page.
     * If type == add, show new order for main page.
     * @param always
     * @param type {add|edit}
     * @returns {*|void}
     */
    saveSubmit: function (always, type) {
        var form = this.form,
            data = form.serializeArray();

        //Recalculate discount in server
        if ($('#update-discount').data('discount') === 'calculate') {
            $(data).each(function () {
                if (this.name == 'discount') {
                    this.value = 'calculate';
                }
            });
        } else if ($('#update-discount').data('discount') === undefined) {

            //If discount not changed, not need send discount value, but need send discount_description.
            data = data.filter(function (el) {
                return el.name !== 'discount';
            });

            //If discount previously did not install, need send discount_description.
            if ($('#discount').val() > 0) {
                //Discount Description contains info about old discounts
                data.discount_description = $('#edit-discount').val();
            } else {
                data = data.filter(function (el) {
                    return el.name !== 'discount_description';
                });
            }
        }

        if (type == 'add') {
            success = function (r) {
                $.order_edit.container.trigger('order_edit_save_success', {
                    data: r.data,
                    is_new: true
                });
                if (!$.order_edit.options.embedded_version && $.order_edit.slide(false, true)) {
                    location.href = '#/orders/state_id=new&view=split&id=' + r.data.order.id + '/';
                }
            };
        } else {
            success = function (r) {
                $.order_edit.container.trigger('order_edit_save_success', {
                    data: r.data,
                    is_new: false
                });
                if (!$.order_edit.options.embedded_version) {
                    $.order_edit.container.find('.back').click();
                }
            };
        }

        //$.shop.trace(type, form.serialize());

        /**
         * @event order_edit_save
         */
        this.container.trigger('order_edit_save', data);

        return $.shop.jsonPost(
            form.attr('action'),
            data,
            success,
            function (r) {
                $.shop.trace(type, r);
                if (r && r.errors && !$.isEmptyObject(r.errors)) {
                    $.order_edit.showValidateErrors(r.errors);
                }
            },
            always
        );
    },

    /**
     * Cuts values ​​to 4 decimal places, and then rounds
     * @param data
     * @returns {*}
     */
    parseTotalResponse: function (data) {
        var keys = [
            'total',
            'discount',
            'shipping',
            'tax',
            'subtotal'
        ];
        keys.forEach(function (key) {
            if (typeof data[key] === 'number' || typeof data[key] === 'string') {
                var value = +data[key];
                data[key] = $.order_edit.roundFloat(parseFloat(value.toFixed(2)))
            }
        });

        return data;
    },

    /**
     * @param val
     * @returns {*}
     */
    roundFloat: function (val) {
        if (!val) {
            return 0;
        } else {
            return this.formatFloat(Math.round(val * 100) / 100)
        }
    },

    formatFloat: function (f) {
        if (this.float_delimeter === ',') {
            return ('' + f).replace('.', ',');
        }
        return '' + f;
    },

    /**
     * @param {String} str
     * @returns {number}
     */
    parseFloat: function (str) {
        if (str) {
            return parseFloat(('' + str).replace(',', '.'));
        } else {
            return 0;
        }
    },

    slideBack: function () {
        this.slide(false, this.options.mode == 'add');
    },

    slide: function (on, add, done) {
        if (arguments.length == 0) {
            this.slide(true, this.options.mode == 'add');
            return;
        }
        on = typeof on === 'undefined' ? true : on; // on/off
        add = typeof add === 'undefined' ? false : add;
        var duration = this.options.duration || 200;
        var view = this.options.view;
        var deferreds = [];

        if (!this.slide_on && on) { // make editable
            deferreds.push($('#s-sidebar').animate({
                'width': 0
            }, duration, function () {
                $(this).hide();
            }));
            $('.s-level2').hide();
            deferreds.push($('#maincontent').animate({
                'margin-top': 45
            }, duration));
            deferreds.push($('#s-content').animate({
                'margin-left': 0
            }, duration));
            deferreds.push($('#s-orders').animate({
                'width': 0
            }, duration, function () {
                $(this).hide();
            }));
            deferreds.push($('#s-order').animate({
                'margin-left': 0
            }, duration).find('>div:first').removeClass('double-padded, bordered-left').find('h1 .back.order-list').hide().end().find('h1 .back.read-mode')
                .show());
            this.slide_on = true;
            $('.s-order-readable').hide();
            $('.s-order-editable').show();

            $.when(deferreds).done(function () {
                if ($.order_list && $.order_list.lazy_load_win) {
                    $.order_list.lazy_load_win.lazyLoad('sleep');
                }
                if (typeof done === 'function') {
                    done.call(this);
                }
            });

            return true;
        } else if (this.slide_on && !on) { // make readable
            deferreds
                .push($('#s-order').animate({
                    'margin-left': view != 'table' && !add ? 300 : 200
                }, duration).find('>div:first').addClass('double-padded, bordered-left').find('h1 .back.order-list').show().end().find('h1 .back.read-mode').hide());
            deferreds.push($('#s-orders').animate({
                'width': 300
            }, duration).show());
            deferreds.push($('#s-content').animate({
                'margin-left': 200
            }, duration));
            deferreds.push($('#maincontent').animate({
                'margin-top': 84
            }, duration));
            $('.s-level2').show();
            deferreds.push($('#s-sidebar').animate({
                'width': 200
            }, duration).show());
            this.slide_on = false;
            $('.s-order-editable').hide();
            $('.s-order-readable').show();

            $.when(deferreds).done(function () {
                if ($.order_list.lazy_load_win) {
                    $.order_list.lazy_load_win.lazyLoad('wake');
                }
                if (typeof done === 'function') {
                    done.call(this);
                }
            });

            return true;
        }
        return false;
    },

    /**
     * Disable or allow click to "save" button and show or hide loading icon
     */
    switchSubmitButton: function (condition) {
        var form = this.form,
            loading_icon = $(form).find('.s-order-items-edit td.save i.loading'),
            submit_button = $(form).find('[type=submit]');

        if (condition === 'disable') {
            loading_icon.css('display', 'inline-block');
            submit_button.attr('disabled', true);
        } else {
            loading_icon.css('display', 'none');
            submit_button.attr('disabled', false);
        }

    },

    showValidateErrors: function (validate_errors) {
        var that = this,
            common_errors = [];

        $.shop.trace('showValidateErrors', validate_errors);
        $('#shipping-info').find('.error').empty();
        $('.error').removeClass('error');
        $('#s-order-edit-customer .errormsg').empty();

        if (that.customer_form) {
            that.customer_form.showValidateErrors(validate_errors ? validate_errors.customer : {});
        }

        $('.s-order-errors').empty();
        if (validate_errors && validate_errors.order) {
            if (!$.isEmptyObject(validate_errors.order.items)) {
                for (var index in validate_errors.order.items) {
                    var tr = $('.s-order-item[data-index=' + index + ']');
                    var errors = validate_errors.order.items[index];
                    for (var name in errors) {
                        var message = tr.find('.s-error-item-' + name);
                        if (message.length) {
                            message.text(errors[name]);
                        } else {
                            common_errors.push(errors[name]);
                        }
                    }
                    delete validate_errors.order.items[index];
                }
            }


            if (!$.isEmptyObject(validate_errors.order.product)) {
                var p_errors = validate_errors.order.product;
                for (var p_id in p_errors) {
                    if (p_errors.hasOwnProperty(p_id)) {
                        if ('quantity' in p_errors[p_id]) {
                            $('.s-order-item[data-product-id=' + p_id + ']').each(function () {
                                if ($(this).find('ul.s-orders-skus input:radio:checked').val() == '' + p_errors[p_id]['sku_id'] || !$(this).find('ul.s-orders-skus').length) {
                                    $(this).find('.s-orders-quantity').addClass('error');
                                    common_errors.push(p_errors[p_id]['quantity']);
                                }
                            });
                        }
                    }
                }
            }
            if (validate_errors.order.common) {
                common_errors.push(validate_errors.order.common);
            }

            if (validate_errors.order.discount) {
                $('#discount').addClass('error');
                common_errors.push(validate_errors.order.discount);
            }

            if (common_errors.length) {
                $('.s-order-errors').html(common_errors.join("<br>"));
            }

        }
    },

    /**
     * Get current selected storefont
     * @param {Boolean} verbose
     * @returns {String|Object} If verbose is TRUE then return {Object} info
     */
    getSelectedStorefront: function (verbose) {
        var that = this,
            $selector = that.$storefront_selector,
            val = $.trim($selector.val());
        if (verbose) {
            return {
                storefront: val,
                data: $selector.find(":selected").data()
            }
        } else {
            return val;
        }
    },

    /**
     * @returns {jQuery}
     */
    getStorefrontSelector: function () {
        return this.$storefront_selector;
    },

    getStorefront: function () {
        return this.getStorefrontSelector().val();
    },

    initStorefrontSelector: function () {
        var that = this,
            $selector = that.$storefront_selector;

        $selector.on('change', function () {
            if (that.customer_form && that.customer_form.isEnabled()) {
                that.customer_form.reloadForm();
            }
        });
    },

    filterStorefrontSelector: function (contact_type) {
        var that = this,
            $selector = that.$storefront_selector;

        $selector.find('option').each(function () {
            var $option = $(this),
                val = $option.val(),
                data = $option.data();

            // show previously hided
            $option.show();

            // ignore option of current order storefront and 'manual' storefront - do not hide these options
            if (data.orderStorefront || !val) {
                return;
            }

            // this options is available for current contact type - do not hide this option
            if (!contact_type || data[contact_type]) {
                return;
            }

            // all other options make hidden
            $option.hide();

            // if turn out that hidden option is selected - than reset selector to '' value
            if ($option.is(':selected')) {
                $selector.val('');
            }
        });
    },
    getPercentSymbol: function () {
        return '%';
    }
};
