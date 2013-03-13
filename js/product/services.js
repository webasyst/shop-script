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
                $.product.editTabServicesAction = function(path) {
                    var button = $('#s-product-save-button');
                    if (!$.product_services.button_color) {
                        $.product_services.button_color = button.hasClass('yellow') ? 'yellow' : 'green';
                    }
                    button.removeClass('yellow').addClass('green');

                    var param = '';
                    if (path.tail) {
                        param = '&param[]=' + path.tail;
                        if (parseInt(path.tail, 10) != $.product_services.service_id) {
                            $.get('?module=product&action=services&id=' + path.id + param, function(html) {
                                var that = $.product_services;
                                that.container.addClass('ajax').html(html);
                                that.counter.text(that.options.count);
                            });
                        }
                    }
                };
                $.product.editTabServicesBlur = function() {
                    var button = $('#s-product-save-button');
                    button.removeClass('yellow green').addClass($.product_services.button_color);
                    $.product_services.button_color = null;
                };

                $.product.editTabServicesSave = function() {
                    var form = $.product_services.form;
                    $.product.refresh('submit');
                    $.shop.jsonPost(form.attr('action'), form.serialize(),
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
                            $.products.dispatch();
                        }
                    );
                    return false;
                };
            }

            this.initView();
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

                $('.product-autocomplete').autocomplete({
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
                        self.val('');

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

                        return false;
                    }
                });
                $('#s-save-service-submit').click(function() {
                    $.products.jsonPost(form.attr('action'), form.serialize(),
                        function(r) {
                            if (r.data.id == $.product_services.service_id) {
                                $.products.dispatch();
                            } else {
                                location.hash = '#/services/'+r.data.id+'/';
                            }
                        }
                    );
                    return false;
                });
                form.off('change').on('change', '.s-service-currency', function() {
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
                    if ($.product_services.service_id) {
                        var name = $(input).val();
                        $.products.jsonPost('?module=service&action=save&edit=name&id='+$.product_services.service_id, {
                            name: name
                        }, function() {
                            $.product_services.container.find('#s-services-list li.selected .name').text(name);
                        });
                    }
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
                var check_radio = function() {
                    var checked_radio = container.find('tr.s-services-variant-product input[type=radio]:checked:first');
                    if (!checked_radio.length) {
                        var first = container.find('tr.s-services-variant-product input[type=checkbox]:checked:first');
                        if (first.length) {
                            first.parents('tr:first').find('input[type=radio]').attr('checked', true);
                        }
                    }
                };
                var check_checkbox_handler = function() {
                    var self = $(this);
                    var tr = self.parents('tr:first');
                    var variant_id = tr.attr('data-variant-id');
                    var tr_skus = container.find('tr.s-services-variant-sku[data-variant-id='+variant_id+']');
                    tr_skus.find('input[type=checkbox]').attr('checked', this.checked);
                    if (tr_skus.length > 1) {
                        tr_skus.show();
                    }
                    if (!this.checked) {
                        reset_radio(tr.find('input[type=radio]'));
                    } else {
                        check_radio();
                    }
                };
                container.off('click', '.s-services-by-sku').
                    on('click', '.s-services-by-sku', function() {
                        var variant_id = $(this).attr('data-variant-id');
                        container.find('tr.s-services-variant-sku[data-variant-id='+variant_id+']').toggle();
                        return false;
                    });
                container.off('click', 'tr.s-services-variant-product input[type=checkbox]').
                    on('click', 'tr.s-services-variant-product input[type=checkbox]', check_checkbox_handler);
                container.off('click', 'tr.s-services-variant-sku input[type=checkbox]').
                    on('click', 'tr.s-services-variant-sku input[type=checkbox]', function() {
                        var self = $(this);
                        var tr = self.parents('tr:first');
                        var variant_id = tr.attr('data-variant-id');
                        var tr_product = container.find('tr.s-services-variant-product[data-variant-id='+variant_id+']');
                        if (self.attr('checked')) {
                            tr_product.find('input[type=checkbox]').attr('checked', true);
                            check_radio();
                        }
                    });
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
            }

            if (!this.product_id) {
                var addNewOption = function() {
                    var row = container.find('.s-services-variant:last').clone();
                    row.find('input[name=name\\[\\]]').val('');
                    row.find('input[name=variant\\[\\]]').val(0);
                    var input = row.find('input[name=default]');
                    input.attr('checked', false);
                    input.val((parseInt(input.val()) || 0) + 1);
                    $(this).parents('tr:first').before(row);
                };

                container.off('click', '.s-multiple-options-toggle').
                    on('click', '.s-multiple-options-toggle', function() {
                        $(this).hide();
                        $.when(container.find('.s-services-ext-cell').show('fast')).done(
                            function() {
                                container.find('.s-delete-product').show();
                                container.find('.s-add-row').show();
                                $(this).find('input').attr('disabled', false);
                                addNewOption.call(container.find('.s-add-new-option'));
                                container.find('input[name=name\\[\\]]:first').focus();
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
                            var tr = self.parents('tr:first');
                            var checked = tr.find('input[name=default]').attr('checked');
                            if (tr.next('.s-services-variant').length || tr.prev('.s-services-variant').length) {
                                tr.remove();
                            } else {
                                self.hide();
                                $.when(container.find('.s-services-ext-cell').hide('fast')).done(function() {
                                    container.find('.s-add-row').hide();
                                    container.find('.s-multiple-options-toggle').show();
                                    container.find('.s-services-ext-cell').find('input').attr('disabled', true);
                                    form.find('input[name=variant\\[\\]]').val(0);
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
                            return false;
                        }
                    );

                var types = $('#s-services-types');
                types.off('click', 'input').
                    on('click', 'input', function() {
                        var products_count = $('#s-services-products-count');
                        var type_id = types.find('input:checked').map(function() {
                            return $(this).val();
                        }).toArray();
                        if (!type_id.length) {
                            products_count.text('');
                        } else {
                            $.products.jsonPost('?module=services&action=productsCount', { type_id: type_id},
                                function(r) {
                                    products_count.text(r.data.count_text);
                                }
                            );
                        }
                    }
                );
            }
        }
    };
})(jQuery);