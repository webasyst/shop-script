(function($) {
    $.product_services = {

        /**
         * {Number}
         */
        service_id: 0,

        /**
         * {Number}
         */
        product_id: 0,

        /**
         * {Jquery object}
         */
        form: null,

        /**
         * Keep track changing of form
         * {String}
         */
        form_serialized_data: '',

        /**
         * {Jquery object}
         */
        container: null,

        button_color: null,

        /**
         * {Object}
         */
        options: {},

        init: function(options) {
            this.options = options;
            if (options.container) {
                if (typeof options.container === 'object') {
                    this.container = options.container;
                } else {
                    this.container = $(options.container);
                }
            }
            if (options.counter) {
                if (typeof options.counter === 'object') {
                    this.counter = options.counter;
                } else {
                    this.counter = $(options.counter);
                }
            }

            this.service_id = parseInt(this.options.service_id, 10) || 0;
            this.product_id = parseInt(this.options.product_id, 10) || 0;
            this.form = this.product_id ? this.container.find('form') : $('#s-services-save');

            if (this.product_id) {

                // maintain intearaction with $.product object

                $.product.editTabServicesAction = function(path) {
                    var that = $.product_services;
                    var param = '';
                    if (path.tail) {
                        param = '&param[]=' + path.tail;
                        var service_id = parseInt(path.tail, 10);
                        if (service_id != $.product_services.service_id) {

                            // load another service info
                            var load = function() {
                                $.get('?module=product&action=services&id=' + path.id + param, function(html) {
                                    that.container.html(html);
                                });
                            };

                            // if needed save before load another service info
                            if (that.form_serialized_data != that.form.serialize()) {
                                $.product_services.save().done(load);
                            } else {
                                load();
                            }
                        }
                    }
                };

                $.product.editTabServicesBlur = function() {
                    var that = $.product_services;

                    if (that.form_serialized_data != that.form.serialize()) {
                        $.product_services.save();
                    }
                };

                $.product.editTabServicesSave = function() {
                    $.product_services.save();
                };

                var that = this;
                var button = $('#s-product-save-button');

                // some extra initializing
                that.container.addClass('ajax');
                that.form_serialized_data = that.form.serialize();
                that.counter.text(that.options.count);

                // keep track of changing checkboxes and radiobuttons
                that.form.on(
                    'change.product_services',
                    'input[type=checkbox], input[type=radio]',
                    function() {
                        // it's needed when current handler triggered after all handlers.
                        // Emulate this
                        setTimeout(function() {
                            if (that.form_serialized_data != that.form.serialize()) {
                                button.removeClass('green').addClass('yellow');
                            } else {
                                button.removeClass('yellow').addClass('green');
                            }

                            var sidebar = that.container.find('.s-inner-sidebar');

                            // if all unchecked then turn highlighting off on proper sidebar item
                            var test = that.form.find('input:checked:first');
                            if (test.length) {
                                sidebar.find('li.selected').removeClass('gray');
                                that.form.parent().find('.toggle-gray').removeClass('gray');
                            } else {
                                sidebar.find('li.selected').addClass('gray');
                                that.form.parent().find('.toggle-gray').addClass('gray');
                            }

                            // update counter
                            var counter = $(that.options.counter);
                            var count   = sidebar.find('li:not(.gray)').length;
                            counter.text(count);
                            that.options.count = count;

                        }, 150);
                    }
                );

                // keep track of changing text inputs
                var timer_id = null;
                that.form.on('keyup.product_services', 'input[type=text]', function() {
                    // kill previous process
                    if (timer_id) {
                        clearTimeout(timer_id);
                    }
                    // optimization: check changing only once at interval
                    timer_id = setTimeout(function() {
                        if (that.form_serialized_data != that.form.serialize()) {
                            button.removeClass('green').addClass('yellow');
                        } else {
                            button.removeClass('yellow').addClass('green');
                        }
                    }, 150);
                });
            }

            this.initView();
        },

        // save product-service info for services tab in product page
        save: function() {

            var form = $.product_services.form;
            $.product.refresh('submit');

            return $.shop.jsonPost(
                form.attr('action'),
                form.serialize(),
                function(r) {
                    var that = $.product_services;
                    var sidebar = that.container.find('.s-inner-sidebar');
                    var li = sidebar.find('li[data-service-id='+that.service_id+']');
                    var status = parseInt(r.data.status, 10);
                    if (!status && !li.hasClass('gray')) {
                        li.addClass('gray');
                    } else if (status && li.hasClass('gray')) {
                        li.removeClass('gray');
                    }
                    that.options.count = r.data.count;
                    that.counter.text(r.data.count);

                    $.product.refresh();
                    $('#s-product-save-button').removeClass('yellow green').addClass('green');

                    that.form_serialized_data = form.serialize();

                    $.products.dispatch();
                }
            );
        },

        initView: function() {
            if (!this.container) {
                return;
            }

            var form = this.form;
            form.submit(function() {
                return false;
            });

            if (!this.product_id) {
                $('#s-sidebar').find('.selected').removeClass('selected');
                $('#s-services').addClass('selected').find('.count').text(this.options.count || 0);
                this.initEditable();

                var product_autocomplete = $('.product-autocomplete').autocomplete({
                    source: '?action=autocomplete',
                    minLength: 3,
                    delay: 300,
                    select: function(event, ui) {
                        var self = $(this);
                        var id = ui.item.id;
                        self.parents('tr:first').before(
                            tmpl('template-services-new-product', {
                                product: {id: ui.item.id, name: ui.item.value}
                            })
                        );

                        // exclude product ID from delete_product[]
                        var delete_product = form.find('input[name=delete_product\\[\\]]');
                        var product_ids = $.unique(
                            delete_product.map(function() {
                                var val = $(this).val();
                                if (id != val) {
                                    return $(this).val();
                                }
                            }
                        ));
                        delete_product.remove();
                        for (var i = 0, n = product_ids.length; i < n; i += 1) {
                            form.append('<input type="hidden" name="delete_product[]" value="'+product_ids[i]+'">');
                        }

                        self.val('');

                        var autocomplete = self.data('autocomplete');
                        autocomplete.do_not_close_autocomplete = 1;
                        window.setTimeout(function() {
                            autocomplete.do_not_close_autocomplete = false;
                            autocomplete.menu.element.position($.extend({
                                of: autocomplete.element
                            }, autocomplete.options.position || { my: "left top", at: "left bottom", collision: "none" }));
                        }, 0);

                        $.product_services.calcProductCount();
                        $.product_services.testProductsCheckbox();

                        return false;
                    }
                });
                var autocomplete = product_autocomplete.data('autocomplete');
                var oldClose = autocomplete.close;
                autocomplete.close = function(e) {
                    if (this.do_not_close_autocomplete) {
                        return false;
                    }
                    oldClose.apply(this, arguments);
                };


                $('#s-services-list').sortable({
                    distance: 5,
                    opacity: 0.75,
                    items: 'li:not(:last)',
                    handle: '.sort',
                    cursor: 'move',
                    tolerance: 'pointer',
                    update: function (event, ui) {
                        var item = ui.item;
                        var next = item.next();
                        var id = item.attr('data-service-id');
                        var before_id = next.attr('data-service-id');
                        $.shop.jsonPost('?module=service&action=move', {
                            id: id, before_id: before_id
                        });
                    }
                });

                $('.s-services-variants').sortable({
                    distance: 5,
                    opacity: 0.75,
                    items: 'tr.s-services-variant',
                    handle: '.sort',
                    cursor: 'move',
                    tolerance: 'pointer',
                    update: function (event, ui) {
                        var item = ui.item;
                        var next = item.next();
                        var id = item.find('input[name="variant[]"]').val();
                        var before_id = next.find('input[name="variant[]"]').val();
                        $.shop.jsonPost('?module=service&action=move&service_id=' + $.product_services.service_id, {
                            id: id, before_id: before_id
                        });
                    }
                });

                // save service info for services page
                $('#s-save-service-submit').click(function() {
                    $(this).attr('disabled', true);
                    var showSuccessIcon = function() {
                        var icon = $('#s-save-service-submit').parent().find('i.yes').show();
                        setTimeout(function() {
                            icon.hide();
                        }, 3000);
                    };
                    var showLoadingIcon = function() {
                        var p = $('#s-save-service-submit').parent();
                        p.find('i.yes').hide();
                        p.find('i.loading').show();
                    };
                    // after update services hash, dispathing and load proper content
                    // 'afterServicesAction' will be called. Extend this handler
                    var prevHandler = $.products.afterServicesAction;
                    $.products.afterServicesAction = function() {
                        showSuccessIcon();
                        if (typeof prevHandler == 'function') {
                            prevHandler.apply($.products, arguments);
                        }
                        $.products.afterServicesAction = prevHandler;
                    };
                    // send post
                    showLoadingIcon();
                    $.products.jsonPost(form.attr('action'), form.serialize(),
                        function(r) {
                            if ($.product_services.service_id) {
                                $.products.dispatch();
                            } else {
                                $.wa.setHash('#/services/' + r.data.id + '/');
                            }
                        }
                    );
                    return false;
                });
                form.off('change', '.s-service-currency').on('change', '.s-service-currency', function() {
                    var self = $(this), val = self.val();
                    form.find('.s-service-currency').val(val);
                    $('#s-service-currency-code').val(val);
                });
            }
            this.initHandlers();
        },

        initEditable: function() {
            var title = $('#s-service-title');
            title.inlineEditable({
                minSize: { width: 350 },
                maxSize: { width: 400 },
                size: { height: 30 },
                inputClass: 's-title-h1-edit',
                afterBackReadable: function(input, data) {
                    if (!data.changed) {
                        return false;
                    }
                    if (!$.product_services.service_id) {
                        $('#s-save-service-submit').click();
                    } else {
                        var name = $(input).val();
                        $.products.jsonPost('?module=service&action=save&edit=name&id='+$.product_services.service_id, {
                            name: name
                        }, function() {
                            $.product_services.container.find('#s-services-list li.selected .name').text(name);
                        });
                    }
                },
                afterMakeEditable: function(input) {
                    input.select();
                }
            });
            if (!this.service_id) {
                title.trigger('editable');
            }
        },

        initHandlers: function() {
            var form = this.form;
            var container = this.container;

            if (this.product_id) {
                var reset_radio = function(radio) {
                    if (radio.attr('checked')) {
                        radio.attr('checked', false);
                        var first = container.find('tr.s-services-variant-product input[type=checkbox]:checked:first');
                        if (first.length) {
                            first.parents('tr:first').find('input[type=radio]').attr('checked', true);
                        }
                    }
                };

                // helper that maintains: if there is no any checked radio makes checked first radio
                var check_radio = function() {
                    var checked_radio = container.find('tr.s-services-variant-product input[type=radio]:checked:first');
                    if (!checked_radio.length) {
                        var first = container.find('tr.s-services-variant-product input[type=checkbox]:checked:first');
                        if (first.length) {
                            first.parents('tr:first').find('input[type=radio]').attr('checked', true);
                        }
                    }
                };

                // service variant checkbox handler: if service variant is checked then all proper skus are checked
                var check_checkbox_handler = function() {
                    var self = $(this);
                    var tr = self.parents('tr:first');
                    var variant_id = tr.attr('data-variant-id');
                    var tr_skus = container.find('tr.s-services-variant-sku[data-variant-id='+variant_id+']');
                    tr_skus.find('input[type=checkbox]').attr('checked', this.checked);

                    // unfold
                    if (this.checked && tr_skus.length > 1) {
                        tr_skus.show();
                    }
                    if (!this.checked) {
                        reset_radio(tr.find('input[type=radio]'));
                    } else {
                        check_radio();
                    }
                };

                // unfold skus to this service variant
                container.off('click', '.s-services-by-sku').
                    on('click', '.s-services-by-sku', function() {
                        var variant_id = $(this).attr('data-variant-id');
                        container.find('tr.s-services-variant-sku[data-variant-id='+variant_id+']').toggle();
                        return false;
                    });

                // if check service variant then check all skus for it
                container.off('click', 'tr.s-services-variant-product input[type=checkbox]').
                    on('click', 'tr.s-services-variant-product input[type=checkbox]', check_checkbox_handler);

                // if check some sku then must be checked also service variant
                container.off('click', 'tr.s-services-variant-sku input[type=checkbox]').
                    on('click', 'tr.s-services-variant-sku input[type=checkbox]', function() {
                        var self = $(this);
                        var tr = self.parents('tr:first');
                        var variant_id = tr.attr('data-variant-id');
                        var tr_variant = container.find('tr.s-services-variant-product[data-variant-id='+variant_id+']');
                        if (self.attr('checked')) {
                            tr_variant.find('input[type=checkbox]').attr('checked', true);
                            check_radio();
                        } else {
                            // if there is not any checked sku for this service variant then uncheck service variant checkbox and radio
                            var any_checked =
                                tr_variant.nextAll('.s-services-variant-sku[data-variant-id='+variant_id+']').
                                find('input[type=checkbox]:checked:first');
                            if (!any_checked.length) {
                                tr_variant.find('input[type=checkbox]').attr('checked', false);
                                tr_variant.find('input[type=radio]').attr('checked', false);
                                check_radio();
                            }
                        }
                    });

                // when choose another service variant make proper checkbox checked
                container.off('click', 'tr.s-services-variant-product input[type=radio]').
                    on('click', 'tr.s-services-variant-product input[type=radio]', function() {
                        var self = $(this);
                        var tr = self.parents('tr:first');
                        var checkbox = tr.find('input[type=checkbox]');
                        if (!checkbox.attr('checked')) {
                            checkbox.attr('checked', true);
                            check_checkbox_handler.call(checkbox.get(0));
                        }
                    });

                // update sku default prices (placeholdres)
                container.off('keyup change', 'tr.s-services-variant-product input[type=text]').
                        on('keyup change', 'tr.s-services-variant-product input[type=text]', function() {
                            var self = $(this);
                            var tr = self.closest('tr');
                            var variant_id = tr.attr('data-variant-id');
                            var sku_inputs = container.find(
                                    'tr.s-services-variant-sku[data-variant-id='+variant_id+'] input[type=text]'
                            );
                            var val = self.val();
                            sku_inputs.attr('placeholder', val ? val : self.attr('placeholder'));
                        });

                $.unique(container.find('.s-services-variant-sku input[name^=variant_sku_price]').map(function() {
                    if ($(this).val()) {
                        return $(this).closest('.s-services-variant-sku').data('variantId')
                    }
                })).each(function(i, variant_id) {
                    container.find('.s-services-by-sku[data-variant-id="' + variant_id + '"]').click();
                });

            }

            if (!this.product_id) {

                var addNewOption = function() {
                    var row = container.find('.s-services-variant:last').clone();
                    row.find('input[name=name\\[\\]]').val('');
                    row.find('input[name=variant\\[\\]]').val(0);
                    row.find('.s-services-type-of-price input[type=radio]').each(function() {
                        var item = $(this);
                        var index = item.attr('name').replace('type_of_price_', '');
                        item.attr('name', 'type_of_price_' + (index + 1));
                    });
                    var input = row.find('input[name=default]');
                    input.attr('checked', false);
                    input.val((parseInt(input.val()) || 0) + 1);
                    $(this).parents('tr:first').before(row);
                };

                container.off('click', '.s-multiple-options-toggle').
                    on('click', '.s-multiple-options-toggle', function() {
                        $(this).hide();

                        // expand row
                        $.when(container.find('.s-services-ext-cell').show('fast')).done(
                            function() {
                                container.find('.s-delete-product').show();
                                container.find('.s-delete-option').show();
                                container.find('.s-add-row').show();
                                $(this).find('input').attr('disabled', false);
                                addNewOption.call(container.find('.s-add-new-option'));
                                container.find('input[name=name\\[\\]]:first').focus();

                                container.find('.s-service-currency:first').change();

                                // process rows of deleted variants
                                container.find('tr.s-services-variant-deleted').each(function() {
                                    var tds = $(this).find('td');
                                    tds.eq(0).show();
                                    tds.eq(2).attr('colspan', 2);
                                });
                            }
                        );
                        return false;
                    }
                );
                container.find('.s-add-new-option').unbind('click').
                    bind('click', function() {
                        addNewOption.call(this);
                        return false;
                    }
                );
                container.off('click', '.s-delete-service').
                    on('click', '.s-delete-service', function() {
                        var d = $('#s-delete-service');
                        d.waDialog({
                            disableButtonsOnSubmit: true,
                            onLoad: function() {
                            },
                            onSubmit: function() {
                                var self = $(this);
                                $.products.jsonPost(self.attr('action'), self.serialize(), function(r) {
                                    var list = $.product_services.container.find('#s-services-list');
                                    var li = list.find('li.selected');
                                    var near = li.prev();
                                    if (!near.length) {
                                        near = li.next();
                                    }
                                    if (near.length) {
                                        location.hash = near.find('a').attr('href');
                                    } else if ($.products.hash == 'services') {
                                        $.products.dispatch();
                                    } else {
                                        location.hash = '#/services/';
                                    }
                                    d.trigger('close');
                                });
                                return false;
                            }
                        });
                        return false;
                    }
                );

                container.off('click', '.s-delete-option').
                    on('click', '.s-delete-option',
                        function() {
                            var self = $(this);
                            var tr = self.parents('tr.s-services-variant:first');
                            var checked = tr.find('input[name=default]').attr('checked');
                            var count = tr.parent().find('tr.s-services-variant').length;

                            if (count > 1) {

                                // process row and mark it as deleted
                                var text =
                                    tr.find('input[name="name[]"]').val() + ' ' +
                                    tr.find('input[name="price[]"]').val() + ' ' +
                                    tr.find('select.s-service-currency').val() || tr.find('.s-service-currency').text();
                                var html =
                                    "<td class='min-width strike'></td>" +
                                    "<td class='bold strike'>" + text + "</td>" +
                                    "<td colspan='2'>" + $_('Click “Save” button below to commit the delete.') + "</td>";
                                tr.html(html).
                                    addClass('gray highlighted s-services-variant-deleted').
                                    removeClass('s-services-variant');

                            } else {
                                self.hide();

                                // squeeze row
                                $.when(container.find('.s-services-ext-cell').hide('fast')).done(function() {
                                    container.find('.s-add-row').hide();
                                    container.find('.s-multiple-options-toggle').show();
                                    container.find('.s-services-ext-cell').find('input').attr('disabled', true);
                                    //form.find('input[name=variant\\[\\]]').val(0);

                                    // process rows of deleted variants
                                    container.find('tr.s-services-variant-deleted').each(function() {
                                        var tds = $(this).find('td');
                                        tds.eq(0).hide();
                                        tds.eq(2).attr('colspan', 1);
                                    });
                                });
                                form.find('input[name=multiple]').val(0);
                            }
                            if (checked) {
                                container.find('input[name=default]:first').attr('checked', true);
                            }
                            return false;
                        }
                    );

                container.off('click', '.s-delete-product').
                    on('click', '.s-delete-product',
                        function() {
                            var self = $(this);
                            var tr = self.parents('tr:first');
                            tr.remove();
                            if (!tr.hasClass('s-new-product')) {
                                // accumulate ids for deleting
                                form.append('<input type="hidden" name="delete_product[]" value="'+tr.attr('data-product-id')+'">');
                            }
                            $.product_services.calcProductCount();
                            $.product_services.testProductsCheckbox();
                            return false;
                        }
                    );

                var types = $('#s-services-types');
                types.off('click', 'input').
                    on('click', 'input', function() {
                        $.product_services.calcProductCount();
                    }
                );

            }
        },

        calcProductCount: function() {
            var products = $('#s-services-products');
            var products_count = $('#s-services-products-count');
            var type_id = $('#s-services-types').find('input:checked').map(function() {
                return $(this).val();
            }).toArray();
            var product_id = products.find('input[name="product[]"]').map(function() {
                return $(this).val();
            }).toArray();
            $.products.jsonPost('?module=services&action=productsCount', { type_id: type_id, product_id: product_id },
                function(r) {
                    products_count.text(r.data.count_text);
                }
            );
        },

        testProductsCheckbox: function() {
            if ($('#s-services-products').find('input[name="product[]"]:first').length) {
                $('#s-services-products-choosen').attr('checked', true);
            } else {
                $('#s-services-products-choosen').attr('checked', false);
            }
        }

    };
})(jQuery);