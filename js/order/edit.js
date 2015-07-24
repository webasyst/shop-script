$.order_edit = {

    /**
     * {Number}
     */
    id : 0,

    /**
     * {Jquery object}
     */
    container : null,

    /**
     * {Jquery object}
     */
    form : null,

    customer_fields : null,
    customer_inputs : null,

    /**
     * {Array}
     */
    stocks: [],

    /**
     * On/off edit mode
     * {Boolen}
     */
    slide_on : false,

    /**
     * {Object}
     */
    options : {},

    init : function(options) {
        this.options = options;
        if (options.id) {
            this.id = options.id;
        }
        this.container = typeof options.container === 'string' ? $(options.container) : options.container;
        this.form = typeof options.form === 'string' ? $(options.form) : options.form;
        this.customer_fields = $('#s-order-edit-customer');
        this.customer_inputs = this.customer_fields.find(':input');

        options.stocks.sort(function(a, b) {
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
    },

    initView : function() {
        var options = this.options;
        this.initCustomerForm(this.id ? 'edit' : 'add');

        // helpers and handlers here
        var updateStockIcon = function(order_item) {
            var select   = order_item.find('.s-orders-stock');
            var option   = select.find('option:selected');
            var sku_item = order_item.find('.s-orders-skus').
                find('input[type=radio]:checked').
                parents('li:first');

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
            }
        };

        $.order_edit.slide(true, options.mode == 'add');

        this.container.find('.back').click(function() {
            if ($.order_edit.id) {
                $.order_edit.slide(false);
            } else {
                $.order_edit.slide(false, true);
            }
            $.orders.back();
            return false;
        });

        this.updateTotal(false);
        $('.s-order-item').each(function() {
            var item = $(this);
            updateStockIcon(item);
        });

        $('#order-currency').change(function () {
            $('#order-items .currency').html($(this).val());
            $.order_edit.options.currency = $(this).val();
        });

        var price_edit = options.price_edit || false;

        var add_order_input = $("#orders-add-autocomplete");
        add_order_input.autocomplete({
            source : '?action=autocomplete&with_counts=1&with_sku_name=1',
            minLength : 3,
            delay : 300,
            select : function(event, ui) {

                $('.s-order-errors').empty();

                var url = '?module=orders&action=getProduct&product_id=' + ui.item.id;
                $.getJSON(url + ($.order_edit.id ? '&order_id=' + $.order_edit.id : '&currency=' + $.order_edit.options.currency), function(r) {
                    var table = $('#order-items');
                    var index = parseInt(table.find('.s-order-item:last').attr('data-index'), 10) + 1 || 1;
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

        this.container.
            off('change', '.s-orders-skus input[type=radio]').
            on('change', '.s-orders-skus input[type=radio]',
                function() {
                    var self = $(this);
                    var tr = self.parents('tr:first');
                    var sku_id = this.value;
                    var product_id = tr.attr('data-product-id');
                    var index = tr.attr('data-index');
                    var mode = $.order_edit.id ? 'edit' : 'add';
                    var item_id = null;
                    if (mode == 'edit') {
                        item_id = parseInt(self.attr('name').replace('sku[edit][', ''), 10);
                    }

                    var url = '?module=orders&action=getProduct&product_id='+product_id+'&sku_id='+sku_id;
                    $.getJSON(url + ($.order_edit.id ? '&order_id=' + $.order_edit.id : '&currency=' + $.order_edit.options.currency), function(r) {
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
                        //tr.find('.s-orders-services .s-orders-service-variant').trigger('change');
                        tr.find('.s-orders-product-price').
                            //find('span').html(r.data.sku.price_html || r.data.sku.price_str).end().
                            find('input').val(r.data.sku.price);
                        //.trigger('change');

                        var ns;
                        if (tr.find('input:first').attr('name').indexOf('add') !== -1) {
                            ns = 'add';
                        } else {
                            ns = 'edit';
                        }

                        tr.find('.s-orders-product-stocks').replaceWith(
                            tmpl('template-order-stocks-' + ns, {
                                sku:     r.data.sku,
                                index:   index,
                                stocks:  $.order_edit.stocks,
                                item_id: item_id   // use only for edit namespace
                            })
                        );

                        updateStockIcon(tr);
                        $.order_edit.updateTotal(tr);

                    });
                }
            );

        // change stocks select
        this.container.
            off('change', '.s-orders-stock').
            on( 'change', '.s-orders-stock', function() {
                var item = $(this).parents('tr.s-order-item:first');
                updateStockIcon(item);
            });

        var updateServicePriceInput = function(variant_option, service_input, update_val) {
                var price = variant_option.attr('data-price');
                var percent_price = variant_option.attr('data-percent-price');
                if (update_val) {
                    service_input.val(price);
                }
                service_input.attr('data-price', price);
                service_input.attr('data-percent-price', percent_price);
        };
        this.container.
            off('change', '.s-orders-service-variant').
            on('change', '.s-orders-service-variant', function() {
                var self = $(this);
                var option = self.find('option:selected');
                var li = self.parents('li:first');
                updateServicePriceInput(option, li.find('.s-orders-service-price'), true);
        });
        this.container.find('.s-orders-service-variant').each(function() {
                var item = $(this);
                var option = item.find('option:selected');
                var li = item.parents('li:first');
                updateServicePriceInput(option, li.find('.s-orders-service-price'), false);
        });

        this.container.off('click', '.s-order-item-delete').on('click', '.s-order-item-delete', function() {
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
        this.container.
            off('change', '.s-orders-services input').
            on('change', '.s-orders-services input', $.order_edit.updateTotal);
        this.container.
            off('change', '.s-orders-product-price input').
            on('change', '.s-orders-product-price input', function() {
                var price = $.order_edit.parseFloat($(this).val());
                $.order_edit.container.find('.s-orders-service-price').each(function() {
                    var item = $(this);
                    if (item.data('currency') === '%' && item.attr('data-price') === item.val()) {
                        var p = price * (item.data('percentPrice') / 100);
                        item.val($.order_edit.formatFloat(p));
                        item.attr('data-price', p);
                    }
                });
                $.order_edit.updateTotal.apply(this);
            });
        this.container.
            off('change', '.s-orders-services .s-orders-service-variant').
            on('change', '.s-orders-services .s-orders-service-variant',
                    $.order_edit.updateTotal
        );

        $(".s-order-customer-details").on('change', 'input[type=text],select', function () {
            if  ($(this).attr('name').indexOf('address')) {
                $.order_edit.updateTotal();
            }
        });

        $("#shipping_methods").change(function(e) {
            var o = $(this).children(':selected');
            var rate = o.data('rate') || 0;
            $("#shipping-rate").val($.order_edit.formatFloat(rate));
            var delivery_info = [];
            if(o.data('error')) delivery_info.push('<span class="error">' + o.data('error') + '</span>');
            if(o.data('est_delivery')) delivery_info.push('<span class="hint est_delivery">' + o.data('est_delivery') + '</span>');
            if(o.data('comment')) delivery_info.push('<span class="hint">' + o.data('comment') + '</span>');
            if (delivery_info) {
                if(o.data('error')) {
                    $("#shipping-rate").addClass('error');
                } else {
                    $("#shipping-rate").removeClass('error');
                }
                $("#shipping-info").html(delivery_info.join('<br>')).show();
            } else {
                $("#shipping-rate").removeClass('error');
                $("#shipping-info").empty().hide();
            }
            $.order_edit.updateTotal(false);
            if (o.data('external') && !e.triggered_by_updateTotal) {
                $.order_edit.updateTotal();
            }
        });

        $("#payment_methods").change(function() {
            var pid = $(this).val();
            $("#payment-info > div").hide();
            if ($('#payment-custom-' + pid).length) {
                $('#payment-custom-' + pid).show();
            }
        });

        $("#discount,#shipping-rate").change(function() {
            $.order_edit.updateTotal(false);
        });

        this.container.off('keydown', '.s-orders-quantity').on('keydown', '.s-orders-quantity', function() {
            var self = $(this);
            var timer_id = self.data('timer_id');
            if (timer_id) {
                clearTimeout(timer_id);
            }
            self.data('timer_id', setTimeout(function() {
                $.order_edit.updateTotal();
            }, 450));
        });

        if (this.form && this.form.length) {
            this.form.unbind('sumbit').bind('submit', function() {
                $.order_edit.showValidateErrors();

                // submit optimization
                // disable that services that aren't checked
                $('.s-orders-services input[name^=service]:not(:checked)', this.form).each(function() {
                    var item = $(this);
                    item.closest('li').find(':input').attr('disabled', true);
                });

                if (!$.order_edit.container.find('.error').length) {
                    $('.s-order-items-edit td.save i.loading').css('display', 'inline-block');
                    if ($.order_edit.id) {
                        $.order_edit.editSubmit();
                    } else {
                        $.order_edit.addSubmit();
                    }
                }
                return false;
            });
        }

        this.initDiscountControl();
    },

    initDiscountControl: function() {
        var $discount_input = $('#discount');
        var $discount_description_input = $('#discount-description');
        var $update_discount_button = $('#update-discount');
        var $tooltip_icon = $('#discount-tooltip-icon');

        // Tooltip to show how discounts were calculated
        $(document).tooltip
        $tooltip_icon.tooltip({
            bodyHandler: function() {
                return $discount_description_input.val() || $discount_description_input.data('updated-manually-msg');
            }
        });
        $update_discount_button.tooltip({
            bodyHandler: function() {
                return $update_discount_button.data('description') || $discount_description_input.data('updated-manually-msg');
            }
        });

        // Nothing to show yet
        $tooltip_icon.hide();

        // When we get recalculated discount, update the fields accordingly
        $('#order-edit-form').on('order_total_updated', function(e, data) {
            if (!data.discount) {
                return;
            }

            // Remember recalculated discount value and description
            $update_discount_button.data('value', data.discount).data('description', data.discount_description);

            if ($update_discount_button.data('just-recalculated')) {
                // When value in discount input matches previous recalculation,
                // but new recalculated discount is different,
                // update visible fields immidiately
                if (data.discount+'' != $discount_input.val()) {
                    $update_discount_button.click();
                }
            } else {
                // Otherwise, make user decide whether they want to recalculate the discount
                $update_discount_button.show();
            }
        });

        // When user clicks the update button, put discount value and description to fields
        // and hide the button itself
        $update_discount_button.click(function () {
            $discount_description_input.val($update_discount_button.data('description')||'');
            $discount_input.val($update_discount_button.data('value')||0).change();
            $update_discount_button.hide().data('just-recalculated', true);
            updateTooltipVisibility();
            if ($tooltip_icon.is(':visible')) {
                $tooltip_icon.fadeOut(50, function(){
                    $tooltip_icon.fadeIn(50, function(){
                        $tooltip_icon.fadeOut(50, function(){
                            $tooltip_icon.fadeIn(50, function(){
                                $tooltip_icon.fadeOut(50, function(){
                                    $tooltip_icon.fadeIn(50, function(){
                                        $tooltip_icon.fadeOut(50, function(){
                                            $tooltip_icon.fadeIn(50, function(){
                                                // many animation
                                                //          @    such blinks
                                                //   wow
                                            });
                                        });
                                    });
                                });
                            });
                        });
                    });
                });
            }
            return false;
        });

        // When user updates the discount field by hand, show the button to reset to calculated values
        $discount_input.on('keyup', function() {
            $discount_description_input.val('');
            $update_discount_button.show().data('just-recalculated', false);
            updateTooltipVisibility();
        });

        function updateTooltipVisibility() {
            if ($discount_input.val() > 0) {
                $tooltip_icon.stop().show();
            } else {
                $tooltip_icon.stop().hide();
            }
        }
    },

    updateTotal : function(ajax) {
        var ajax = ajax === undefined ? true : ajax;
        var container = $.order_edit.container;
        if (!container.find('.s-order-item').length) {
            $("#subtotal").text(0);
            $("#total").text(0);
            return;
        }
        var subtotal = 0;
        var $discount_input = $('#discount');

        // Data for orderTotal controller
        var data = {};
        var customer = $("#s-order-edit-customer").find('[name^="customer["]').serializeArray();
        for (var i = 0; i < customer.length; i++) {
            data[customer[i].name] = customer[i].value;
        }
        data.items = [];

        container.find('.s-order-item').each(function() {
            var tr = $(this);
            var product_id = tr.find('input[name^="product"]').val();
            var services = [];
            var price = $.order_edit.parseFloat(tr.find('.s-orders-product-price input').val());
            var quantity = $.order_edit.parseFloat(tr.find('input.s-orders-quantity').val());

            subtotal += price * quantity;

            if (tr.find('.s-orders-services').length) {
                tr.find('.s-orders-services input:checkbox:checked').each(function() {
                    var li = $(this).closest('li');
                    var service_price = $.order_edit.parseFloat(li.find('input.s-orders-service-price').val());

                    services.push({
                        id: $(this).val(),
                        price: service_price
                    });

                    subtotal   += service_price * quantity;

                });
            }

            // get SKU id
            var sku_input = tr.find('input[name^=sku]:not(:radio)').add(tr.find('input[name^=sku]:checked')).first();
            var sku_id = sku_input.val() || 0;

            data.items.push({
                product_id: product_id,
                quantity: quantity,
                price: price,
                sku_id: sku_id,
                services: services
            });
        });
        data.subtotal = subtotal;
        var discount = $.order_edit.parseFloat($discount_input.val() || 0);
        data.discount = discount;
        if ($.order_edit.id) {
            data.order_id =  $.order_edit.id;
        } else {
            data['currency'] = $('#order-edit-form input[name=currency]').val();
        }
        data['shipping_id'] = ($('#shipping_methods').val()||'').split('.')[0];
        data['customer[id]'] = $('#s-customer-id').val();

        if (ajax) {
            // Fetch shipping options and rates, and other info from orderTotal controller
            $.post('?module=order&action=total', data, function(response) {
                if (response.status == 'ok') {
                    var el = $("#shipping_methods");
                    var el_selected = el.val();
                    el.empty();

                    var shipping_method_ids = response.data.shipping_method_ids;
                    var shipping_methods = response.data.shipping_methods;

                    // exact match
                    var found = shipping_method_ids.indexOf(el_selected) !== -1;

                    for (var i = 0; i < shipping_method_ids.length; i += 1) {
                        var ship_id = shipping_method_ids[i];
                        var ship =  shipping_methods[ship_id];
                        var o = $('<option></option>');
                        o.html(ship.name).attr('value', ship_id).data('rate', ship.rate);
                        o.data('error', ship.error || undefined);
                        o.data('est_delivery', ship.est_delivery || undefined);
                        o.data('comment', ship.comment || undefined);
                        o.data('external', ship.external || false);
                        el.append(o);

                        // unexact match, but close
                        if (!found) {
                            var ship_id_parts = ('' + ship_id).split('.');
                            var el_selected_parts = el_selected.split('.');
                            if (ship_id_parts[0] !== undefined && el_selected_parts[0] !== undefined &&
                                    ship_id_parts[0] == el_selected_parts[0])
                            {
                                    found = true;
                                    el_selected = ship_id;
                            }
                        }
                    }

                    if (found) {
                        el.val(el_selected);
                        if (!shipping_methods[el_selected].external || data['shipping_id'] == el_selected.split('.')[0]) {
                            el.trigger($.Event('change', {
                                triggered_by_updateTotal: 1
                            }));
                        }
                    }

                    $('#order-edit-form').trigger('order_total_updated', response.data);
                }
            }, 'json');
        }

        $("#subtotal").text($.order_edit.formatFloat(Math.round(subtotal * 100) / 100));
        var shipping = $.order_edit.parseFloat($("#shipping-rate").val().replace(',', '.')) || 0;
        var undiscounted_total = subtotal + shipping;

        // correct discout by constraint: total must be >= 0
        if (discount < 0) {
            discount = 0;
            $discount_input.val('');
        } else {
            if (undiscounted_total - discount < 0) {
                discount = undiscounted_total;
            }
            $discount_input.val($.order_edit.formatFloat(Math.round(discount * 100) / 100));
        }

        var total = undiscounted_total - discount;
        $("#total").text($.order_edit.formatFloat(Math.round(total * 100) / 100));
    },

    editSubmit : function() {
        var form = this.form;
        $.shop.jsonPost(form.attr('action'), form.serialize(), function(r) {
            $.order_edit.container.find('.back').click();
        }, function(r) {
            $.shop.trace('editSubmit', r);
            $('.s-order-items-edit td.save i.loading').hide();
            if (r && r.errors && !$.isEmptyObject(r.errors)) {
                $.order_edit.showValidateErrors(r.errors);
                $('.s-orders-services input:disabled', this.form).attr('disabled', false);
                return false;
            }
        });
    },

    addSubmit : function() {
        var form = this.form;
        $.shop.trace('addSubmit', $(form));
        $.shop.jsonPost(form.attr('action'), form.serialize(), function(r) {
            if ($.order_edit.slide(false, true)) {
                location.href = '#/orders/state_id=new&view=split&id=' + r.data.order.id + '/';
            }
        }, function(r) {
            $.shop.trace('addSubmit', r);
            $('.s-order-items-edit td.save i.loading').hide();
            if (r && r.errors && !$.isEmptyObject(r.errors)) {
                $.order_edit.showValidateErrors(r.errors);
                $('.s-orders-services input:disabled', this.form).attr('disabled', false);
                return false;
            }
        });
    },

    formatFloat: function(f) {
        if (this.float_delimeter === ',') {
            return ('' + f).replace('.', ',');
        }
        return '' + f;
    },

    parseFloat: function(str) {
        if (!str) {
            return 0;
        } else {
            return parseFloat(('' + str).replace(',', '.'));
        }
    },

    slideBack : function() {
        this.slide(false, this.options.mode == 'add');
    },

    slide : function(on, add, done) {
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
                'width' : 0
            }, duration, function() {
                $(this).hide();
            }));
            $('.s-level2').hide();
            deferreds.push($('#maincontent').animate({
                'margin-top' : 45
            }, duration));
            deferreds.push($('#s-content').animate({
                'margin-left' : 0
            }, duration));
            deferreds.push($('#s-orders').animate({
                'width' : 0
            }, duration, function() {
                $(this).hide();
            }));
            deferreds.push($('#s-order').animate({
                'margin-left' : 0
            }, duration).find('>div:first').removeClass('double-padded, bordered-left').find('h1 .back.order-list').hide().end().find('h1 .back.read-mode')
            .show());
            this.slide_on = true;
            $('.s-order-readable').hide();
            $('.s-order-editable').show();

            $.when(deferreds).done(function() {
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
                'margin-left' : view != 'table' && !add ? 300 : 200
            }, duration).find('>div:first').addClass('double-padded, bordered-left').find('h1 .back.order-list').show().end().find('h1 .back.read-mode').hide());
            deferreds.push($('#s-orders').animate({
                'width' : 300
            }, duration).show());
            deferreds.push($('#s-content').animate({
                'margin-left' : 200
            }, duration));
            deferreds.push($('#maincontent').animate({
                'margin-top' : 84
            }, duration));
            $('.s-level2').show();
            deferreds.push($('#s-sidebar').animate({
                'width' : 200
            }, duration).show());
            this.slide_on = false;
            $('.s-order-editable').hide();
            $('.s-order-readable').show();

            $.when(deferreds).done(function() {
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

    showValidateErrors : function(validate_errors) {
        $('.error').removeClass('error');
        $('#s-order-edit-customer .errormsg').empty();
        if (validate_errors && validate_errors.customer) {
            var errors = validate_errors.customer;
            $.order_edit.customer_fields.find('.field-group:first').html(errors.html);
            delete errors.html;
            for (var name in errors) {
                $.order_edit.customer_fields.find('.s-error-customer-' + name).each(function() {
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
                    var tr = $('.s-order-item[data-index='+index+']');
                    var errors = validate_errors.order.items[index];
                    for (var name in errors) {
                        tr.find('.s-error-item-' + name).text(errors[name]);
                    }
                    delete validate_errors.order.items[index];
                }
            }


            if (!$.isEmptyObject(validate_errors.order.product)) {
                var p_errors = validate_errors.order.product;
                for (var p_id in p_errors) {
                    if (p_errors.hasOwnProperty(p_id)) {
                        if ('quantity' in p_errors[p_id]) {
                            $('.s-order-item[data-product-id='+p_id+']').each(function () {
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

            if (common_errors.length) {
                $('.s-order-errors').html(common_errors.join("<br>"));
            }

        }
    },

    bindValidateErrorPlaceholders : function() {
        // firstname, middlename, lastname mark as having one name of error
        var names = ['firstname', 'middlename', 'lastname'];
        for (var i = 0, n = names.length; i < n; i += 1) {
            var name = names[i];
            $.order_edit.customer_inputs.filter('[name=' + $.order_edit.inputName(name) + ']').addClass('s-error-customer-name');
        }
        $.order_edit.customer_fields.find('.s-error-customer-name:last').after('<em class="errormsg s-error-customer-name"></em>');
    },

    initCustomerForm : function(editing_mode) {
        editing_mode = typeof editing_mode === 'undefined' ? 'add' : editing_mode;

        // editing mode (no autocomplete)
        if (editing_mode !== 'add') {
            $.order_edit.bindValidateErrorPlaceholders();
            return;
        }

        // adding mode (with autocomplete);
        var autocompete_input = $("#customer-autocomplete");

        // utility-functions
        var disable = function(disabled) {
            disabled = typeof disabled === 'undefined' ? true : disabled;
            $('#s-customer-id').attr('disabled', disabled);
            if (disabled) {
                $('#s-order-edit-customer').addClass('s-opaque');
            } else {
                $('#s-order-edit-customer').removeClass('s-opaque');
            }
        };
        var activate = function(activate) {
            activate = typeof activate === 'undefined' ? true : activate;
            if (activate) {
                disable(false);
                autocompete_input.val('').hide(200);
            } else {
                disable();
                $.order_edit.customer_inputs.val('');
                autocompete_input.val('').show();
            }
        };
        var testPhone = function(str) {
            return parseInt(str, 10) || str.substr(0, 1) == '+' || str.indexOf('(') !== -1;
        };
        var testEmail = function(str) {
            return str.indexOf('@') !== -1;
        };

        $.order_edit.bindValidateErrorPlaceholders();

        // mark whole form as disabled (disactivated) to user want use autocomplete
        activate(false);
        $.order_edit.customer_fields.off('focus', ':input').on('focus', ':input', function() {
            disable(false);
        });

        if (editing_mode == 'add') {
            $('#s-order-new-customer').click(function() {
                $.order_edit.customer_fields.find('.field-group:first').find(':input').val('');
                activate();
                $.order_edit.customer_inputs.first().focus();
                $('#s-customer-id').val(0);
                return false;
            });
        }


        var term = '';

        // autocomplete
        autocompete_input.autocomplete({
            source : function(request, response) {
                term = request.term;
                $.getJSON($.order_edit.options.autocomplete_url, request, function(r) {
                    (r = r || []).push({
                        label : $_('New customer'),
                        name  : $_('New customer'),
                        value : 0
                    });
                    response(r);
                });
            },
            delay : 300,
            minLength : 3,
            select : function(event, ui) {
                var item = ui.item;

                var focusFirstEmptyInput = function() {
                    var focused = false;
                    $.order_edit.customer_inputs.filter('input[type=text], textarea').each(function() {
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
                    $.get('?action=contactForm&id=' + item.value, function(html) {
                        $.order_edit.customer_fields.find('.field-group:first').html(html);
                        $.order_edit.customer_inputs = $.order_edit.customer_fields.find(':input');
                        $('#s-customer-id').val(item.value);
                        activate();

                        // autocomplete make focus for its input. That brakes out plan!
                        // setTimout-hack for fix it
                        setTimeout(function() {
                            focusFirstEmptyInput();
                        }, 200);
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
                    setTimeout(function() {
                        focusFirstEmptyInput();
                    }, 200);
                }

                return false;
            },
            focus: function(event, ui) {
                this.value = ui.item.name;
                return false;
            }
        });

        $('#order-add-form').submit(function() {
            disable(false);
        });
    },

    inputName : function(name) {
        return '"customer[' + name + ']"';
    },

    getPercentSymbol: function() {
        return '%';
    }
};
