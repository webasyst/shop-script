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

    customer_fields: null,
    customer_inputs: null,

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

    init: function (options) {
        this.options = options;
        if (options.id) {
            this.id = options.id;
        }
        this.container = typeof options.container === 'string' ? $(options.container) : options.container;
        this.form = typeof options.form === 'string' ? $(options.form) : options.form;
        this.customer_fields = $('#s-order-edit-customer');
        this.customer_inputs = this.customer_fields.find(':input');

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

        this.float_delimeter = options.float_delimeter;
        this.initView();
        this.initShippingControl();
        this.initDiscountControl();
        this.initCustomerSourceControl(options.customer_sources);
    },

    initView: function () {
        var options = this.options;
        this.initCustomerForm(this.id ? 'edit' : 'add');

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

                var url = '?module=orders&action=getProduct&product_id=' + ui.item.id;
                $.getJSON(url + ($.order_edit.id ? '&order_id=' + $.order_edit.id : '&currency=' + $.order_edit.options.currency), function (r) {
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
                    //item.find('.s-orders-services .s-orders-service-variant').trigger('change');

                    $('#s-order-comment-edit').show();
                    $.order_edit.updateTotal();

                    updateStockIcon(item);

                });
                add_order_input.val('');

                return false;
            }
        });

        //Select product SKU
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

                var url = '?module=orders&action=getProduct&product_id=' + product_id + '&sku_id=' + sku_id;
                $.getJSON(url + ($.order_edit.id ? '&order_id=' + $.order_edit.id : '&currency=' + $.order_edit.options.currency), function (r) {
                    tr.find('.s-orders-services').replaceWith(
                        tmpl('template-order-services', {
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
                    tr.find('.s-orders-product-price').//find('span').html(r.data.sku.price_html || r.data.sku.price_str).end().
                    find('input').val(r.data.sku.price);
                    //.trigger('change');

                    var ns;
                    if (tr.find('input:first').attr('name').indexOf('add') !== -1) {
                        ns = 'add';
                    } else {
                        ns = 'edit';
                    }

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
//            $(this).parents('tr:first').replaceWith(
//                $.order_edit.container.find('.template-deleted').clone().show()
//            );

            var self = $(this);
            self.parents('tr:first').remove();
            if (!self.parents('table:first').find('tr.s-order-item:first').length) {
                $('#s-order-comment-edit').hide();
            }

            $.order_edit.updateTotal();
            return false;
        });

        // calculations
        this.container.off('change', '.s-orders-services input').on('change', '.s-orders-services input', $.order_edit.updateTotal);
        this.container.off('change', '.s-orders-product-price input').on('change', '.s-orders-product-price input', function () {
            var $this = $(this);
            var price = $.order_edit.parseFloat($this.val());
            var $scope = $this.parents('tr:first');
            $scope.find('.s-orders-service-price').each(function () {
                var item = $(this);
                if (item.data('currency') === '%' && item.attr('data-price') === item.val()) {
                    var p = price * (item.data('percentPrice') / 100);
                    item.val($.order_edit.formatFloat(p));
                    item.attr('data-price', p);
                }
            });
            $.order_edit.updateTotal();
        });
        this.container.off('change', '.s-orders-services .s-orders-service-variant').on('change', '.s-orders-services .s-orders-service-variant',
            $.order_edit.updateTotal
        );

        //Update total if customer address edit
        $(".s-order-customer-details").on('change', 'input,select,checkbox,textarea', function () {
            if ($(this).attr('name') && $(this).attr('name').indexOf('address') > 0) {
                $.order_edit.updateTotal();
            }
        });

        $("#payment_methods").change(function () {
            var pid = $(this).val();
            $("#payment-info > div").hide();
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

                    $('.s-order-items-edit td.save i.loading').css('display', 'inline-block');
                    form.find('[type=submit]').attr('disabled', true);

                    var onAlwaysSubmit = function () {
                        orderSaveSubmit.xhr = null;
                        form.find('[type=submit]').attr('disabled', false);
                        $('.s-orders-services input:disabled', form).attr('disabled', false);
                        $('.s-order-items-edit td.save i.loading').css('display', 'none');
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
    },

    initShippingControl: function() {
        var $shipping_rate = $('#shipping-rate'),
            previous_shipping_method = $("#shipping_methods").val();

        $("#shipping-custom").on('change', ':input', function (e) {
            /**
             * handle only related changes
             */
            $.shop.trace('#shipping-custom change', [e, this]);
            if (e.originalEvent && $(this).data('affects-rate')) {
                $.order_edit.updateTotal();
            }
        });

        $("#shipping_methods").change(function () {
            var $this = $(this);
            var option = $this.children(':selected');
            var rate = option.data('rate') || 0;

            // Update cost if it is not entered by hand
            if (!$shipping_rate.data('shipping') || option.val() != previous_shipping_method) {
                $shipping_rate.val($.order_edit.formatFloat(rate));
                $shipping_rate.data('shipping', false);
                previous_shipping_method = option.val();
            }

            var delivery_info = [];
            if (option.data('error')) delivery_info.push('<span class="error">' + option.data('error') + '</span>');
            if (option.data('est_delivery')) delivery_info.push('<span class="hint est_delivery">' + option.data('est_delivery') + '</span>');
            var sid = $this.val().replace(/\..+$/, '');
            var prev_sid = $this.data('_shipping_id');
            $("#shipping-custom > div").hide();
            if ($('#shipping-custom-' + sid).length) {
                $('#shipping-custom-' + sid).show();
            }
            if (option.data('comment')) delivery_info.push('<span class="hint">' + option.data('comment') + '</span>');
            if (delivery_info) {
                if (option.data('error')) {
                    $shipping_rate.addClass('error');
                } else {
                    $("#shipping-rate").removeClass('error');
                }
                $("#shipping-info").html(delivery_info.join('<br>')).show();
            } else {
                $shipping_rate.removeClass('error');
                $("#shipping-info").empty().hide();
            }

            $.shop.trace('check shipping_methods id', [prev_sid, sid]);
            $.order_edit.updateTotal();
        });

        //Prevent shipping cost updates
        $('#shipping-rate').keyup(function () {
            $('#shipping-rate').data('shipping', $(this).val());
            $.order_edit.updateTotal();
        })
    },

    initDiscountControl: function () {
        var $discount_input = $('#discount');
        var $discount_description_input = $('#discount-description');
        var $update_discount_button = $('#update-discount');
        var $edit_discount_button = $('#edit-discount');
        var $tooltip_icon = $('#discount-tooltip-icon');

        // Tooltip to show how discounts were calculated
        $(document).tooltip
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
            if ($update_discount_button.data('discount') == 'calculate') {
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
                // update visible fields immidiately
                if ($.order_edit.parseFloat(data.discount) + '' != $.order_edit.parseFloat($discount_input.val())) {
                    $update_discount_button.click();
                }
            } else {
                // Otherwise, make user decide whether they want to recalculate the discount
                $update_discount_button.show();
            }
        });

        var hide_manual_edit = function(){
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

            $discount_input.attr('title',$discount_description_input.data('edit-manually-msg'));

            $update_discount_button.hide().data('discount', 'calculate');
            $.order_edit.updateTotal();
            updateTooltip();
            return false;
        });

        // When user updates the discount field by hand, show the button to reset to calculated values
        $discount_input.on('keyup', function () {
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
    },

    initCustomerSourceControl: function (customer_sources) {
        var $input = $('#customer-source');
        $input.autocomplete({
            delay: 0,
            minLength: 0,
            appendTo: '#order-edit-form',
            source: function(request, response) {
                var result = customer_sources.filter(function(v) {
                    return v && v.indexOf && v.indexOf(request.term) >= 0;
                }).slice(0, 10);
                if (result.length == 1 && result[0] == request.term) {
                    response([]);
                } else {
                    response(result);
                }
            }
        }).on('focus', function() {
            $input.autocomplete('search');
        });
    },

    getOrderItems: function(container) {
        var items = [];

        var order_content = [];

        container.find('.s-order-item').each(function () {
            var tr = $(this),
                product_id = tr.find('input[name^="product"]').val(),
                services = [],
                price = $.order_edit.parseFloat(tr.find('.s-orders-product-price input').val()),
                quantity = $.order_edit.parseFloat(tr.find('input.s-orders-quantity').val()),
                stock_id = tr.find('select.s-orders-sku-stock-select').val();

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

        if (container.data('order-content') !== order_content) {
            var $discount_input = $('#discount');
            $discount_input.trigger('keyup');
        }

        return items;
    },

    reduceOrderContent: function (order_content) {
        order_content.sort(function (a, b) {
            var delta = a.product_id - b.product_id;
            if (delta == 0) {
                delta = a.sku_id - b.sku_id;
            }
            if (delta == 0) {
                delta = a.services.localeCompare(b.services);
            }
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
            $total = $("#total");
        if (!container.find('.s-order-item').length) {
            $subtotal.text(0);
            $total.text(0);
            return;
        }

        //clear errors.
        $.order_edit.showValidateErrors();

        // Data for orderTotal controller
        var data = {};
        var customer = $("#s-order-edit-customer").find('[name^="customer["]').serializeArray();
        for (var i = 0; i < customer.length; i++) {
            data[customer[i].name] = customer[i].value;
        }

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

        var shipping_id = $('#shipping_methods').val();

        //Send the cost of delivery of entered by hands
        if ($('#shipping-rate').data('shipping')) {
            data.shipping = $('#shipping-rate').data('shipping');
        }

        data['params'] = {shipping_id: shipping_id};
        data['customer[id]'] = data['contact_id'] = $('#s-customer-id').val();

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
                if (response.status === 'ok') {
                    if (response.data.shipping_method_ids.length > 0) {
                        var el = $("#shipping_methods"),
                            el_selected = el.val(),
                            el_selected_id = el_selected.replace(/\W.+$/, ''),
                            $shipping_rate = $('#shipping-rate');

                        //clear shipping data.
                        el.empty();
                        el.prepend('<option value=""></option>');

                        $('#shipping-custom').empty();

                        var shipping_method_ids = response.data.shipping_method_ids;
                        var shipping_methods = response.data.shipping_methods;

                        // exact match
                        var found = shipping_method_ids.indexOf(el_selected) !== -1;
                        var custom_html_container = $('#shipping-custom');

                        custom_html_container.find('>div.fields.form').hide();

                        for (var i = 0; i < shipping_method_ids.length; i += 1) {
                            var ship_id = shipping_method_ids[i];

                            var ship = shipping_methods[ship_id];

                            /**
                             *
                             * @type {*|JQuery|jQuery|HTMLElement}
                             */
                            var o = $('<option></option>');
                            o.html(ship.name).attr('value', ship_id).data('rate', ship.rate);
                            o.data('error', ship.error || undefined);
                            o.data('est_delivery', ship.est_delivery || undefined);
                            o.data('comment', ship.comment || undefined);
                            o.data('external', ship.external || false);
                            if (ship.custom_data) {
                                for (var custom_field in ship.custom_data) {
                                    if (ship.custom_data.hasOwnProperty(custom_field)) {
                                        o.data(custom_field, ship.custom_data[custom_field]);
                                    }
                                }
                            }

                            el.append(o);

                            //If shipping not selected, select first
                            if (!el_selected && i === 0) {
                                $("#shipping-rate").val($.order_edit.formatFloat(ship.rate));
                            }

                            if (ship.custom_html) {
                                var plugin_id = ship_id.replace(/\W.+$/, '');

                                var custom_html = custom_html_container.find('#shipping-custom-' + plugin_id);
                                if (!custom_html.length) {
                                    custom_html = custom_html_container.append('<div id="shipping-custom-' + plugin_id + '" style="display: none;" class="fields form"></div>');
                                    custom_html = custom_html_container.find('#shipping-custom-' + plugin_id);
                                }
                                custom_html.html(ship.custom_html);
                                if (el_selected_id == plugin_id) {
                                    custom_html.show();
                                } else {
                                    custom_html.hide();
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
                                        $.order_edit.realUpdateTotal();
                                        return;
                                    }
                                }
                            }
                        }

                        el.val(el_selected);
                        el.data('_shipping_id', el_selected_id);
                        $.shop.trace('set shipping_methods id', [el_selected_id, el_selected]);

                    }

                    //If user deleted discount value, don't need set zero discount value
                    if (update_discount != '') {
                        $('#discount').val($.order_edit.roundFloat(response.data.discount));
                    }

                    //Update shipping rate. If shipping rate not entered by hand
                    if (!$shipping_rate.data('shipping') ) {
                        $shipping_rate.val(($.order_edit.roundFloat(response.data.shipping)));
                    }

                    $('#order-edit-form').trigger('order_total_updated', response.data);

                    //Update order value after calculate.
                    $subtotal.text($.order_edit.roundFloat(response.data.subtotal));
                    $total.text($.order_edit.roundFloat(response.data.total));

                    $.order_edit.showValidateErrors(response.data.errors);
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
            data = data.filter(function(el) { return el.name !== 'discount'; });

            //If discount previously did not install, need send discount_description.
            if ($('#discount').val() > 0) {
                //Discount Description contains info about old discounts
                data.discount_description =  $('#edit-discount').val();
            } else {
                data = data.filter(function(el) { return el.name !== 'discount_description'; });
            }
        }

        if (type == 'add') {
            success = function (r) {
                if ($.order_edit.slide(false, true)) {
                    location.href = '#/orders/state_id=new&view=split&id=' + r.data.order.id + '/';
                }
            };
        } else {
            success = function (r) {
                $.order_edit.container.find('.back').click();
            };
        }

        $.shop.trace(type, form.serialize());

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
     * @param val
     * @returns {*}
     */
    roundFloat : function(val) {
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

    parseFloat: function (str) {
        if (!str) {
            return 0;
        } else {
            return parseFloat(('' + str).replace(',', '.'));
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
                if ($.order_list.lazy_load_win) {
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

    showValidateErrors: function (validate_errors) {
        $('.error').removeClass('error');
        $('#s-order-edit-customer .errormsg').empty();
        if (validate_errors && validate_errors.customer) {
            var errors = validate_errors.customer;
            $.order_edit.customer_fields.find('.field-group:first').html(errors.html);
            delete errors.html;
            for (var name in errors) {
                $.order_edit.customer_fields.find('.s-error-customer-' + name).each(function () {
                    var item = $(this);
                    if (this.tagName == 'EM') {
                        item.text(errors[name]);
                    } else {
                        item.addClass('error');
                    }
                });
            }
        }

        var common_errors = [];

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

    bindValidateErrorPlaceholders: function () {
        // firstname, middlename, lastname mark as having one name of error
        var names = ['firstname', 'middlename', 'lastname'];
        for (var i = 0, n = names.length; i < n; i += 1) {
            var name = names[i];
            $.order_edit.customer_inputs.filter('[name=' + $.order_edit.inputName(name) + ']').addClass('s-error-customer-name');
        }
        $.order_edit.customer_fields.find('.s-error-customer-name:last').after('<em class="errormsg s-error-customer-name"></em>');
    },

    initCustomerForm: function (editing_mode) {
        editing_mode = typeof editing_mode === 'undefined' ? 'add' : editing_mode;

        $.order_edit.bindValidateErrorPlaceholders();

        // editing mode (no autocomplete)
        if (editing_mode !== 'add') {
            return;
        }

        // adding mode (with autocomplete);
        var autocompete_input = $("#customer-autocomplete");

        // utility-functions
        var disable = function (disabled) {
            disabled = typeof disabled === 'undefined' ? true : disabled;
            $('#s-customer-id').attr('disabled', disabled);
            if (disabled) {
                $('#s-order-edit-customer').addClass('s-opaque');
            } else {
                $('#s-order-edit-customer').removeClass('s-opaque');
            }
        };
        var activate = function (activate) {
            activate = typeof activate === 'undefined' ? true : activate;
            if (activate) {
                disable(false);
                autocompete_input.val('').hide(200);
            } else {
                disable();
                resetInputValues();
                autocompete_input.val('').show();
            }
        };
        var testPhone = function (str) {
            return parseInt(str, 10) || str.substr(0, 1) == '+' || str.indexOf('(') !== -1;
        };
        var testEmail = function (str) {
            return str.indexOf('@') !== -1;
        };

        var resetInputValues = (function () {
            var initial_values = {};
            $.order_edit.customer_inputs.each(function () {
                var $self = $(this);
                initial_values[$self.attr('name')] = $self.val();
            });

            return function () {
                $.order_edit.customer_inputs.each(function () {
                    var $self = $(this);
                    $self.val(initial_values[$self.attr('name')] || '');
                });
            };
        });

        // mark whole form as disabled (disactivated) to user want use autocomplete
        activate(false);
        $.order_edit.customer_fields.off('focus', ':input').on('focus', ':input', function () {
            disable(false);
        });

        // Link to create a new customer resets fields even after something is selected in autocomplete
        $('#s-order-new-customer').click(function () {
            resetInputValues();
            activate();
            $.order_edit.customer_inputs.first().focus();
            $('#s-customer-id').val(0);
            return false;
        });

        var term = '';

        // autocomplete
        autocompete_input.autocomplete({
            source: function (request, response) {
                term = request.term;
                $.getJSON($.order_edit.options.autocomplete_url, request, function (r) {
                    (r = r || []).push({
                        label: $_('New customer'),
                        name: $_('New customer'),
                        value: 0
                    });
                    response(r);
                });
            },
            delay: 300,
            minLength: 3,
            select: function (event, ui) {
                var item = ui.item;

                var focusFirstEmptyInput = function () {
                    var focused = false;
                    $.order_edit.customer_inputs.filter('input[type=text], textarea').each(function () {
                        var input = $(this);
                        if (input.is(':not(:hidden)') && !this.value) {
                            focused = true;
                            input.focus();
                            return false;
                        }
                    });
                    if (!focused) {
                        $.order_edit.customer_inputs.first().focus();
                    }
                };

                if (item.value) {
                    $.get('?action=contactForm&id=' + item.value, function (html) {
                        $.order_edit.customer_fields.find('.field-group:first').html(html);
                        $.order_edit.customer_inputs = $.order_edit.customer_fields.find(':input');
                        $('#s-customer-id').val(item.value);
                        activate();
                        // autocomplete make focus for its input. That brakes out plan!
                        // setTimout-hack for fix it
                        setTimeout(function () {
                            focusFirstEmptyInput();
                        }, 200);
                        $.order_edit.updateTotal();
                    });
                } else {
                    var selector = '[name=' + $.order_edit.inputName(
                        testPhone(term) ? 'phone' : (
                            testEmail(term) ? 'email' : 'firstname'
                        )) + ']';
                    $.order_edit.customer_inputs.filter(selector).val(term);
                    $('#s-customer-id').val(0);
                    activate();

                    // autocomplete make focus for its input. That brakes out plan!
                    // setTimout-hack for fix it
                    setTimeout(function () {
                        focusFirstEmptyInput();
                    }, 200);
                }

                return false;
            },
            focus: function (event, ui) {
                this.value = ui.item.name;
                return false;
            }
        });

        $('#order-add-form').submit(function () {
            disable(false);
        });
    },

    inputName: function (name) {
        return '"customer[' + name + ']"';
    },

    getPercentSymbol: function () {
        return '%';
    }
};
