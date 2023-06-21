(function ($) {
    $.product_services = {

        /**
         * {Number}
         */
        service_id: 0,

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

        init: function (options) {
            this.options = options;
            if (options.container) {
                if (typeof options.container === 'object') {
                    this.container = options.container;
                } else {
                    this.container = $(options.container);
                }
            }

            this.service_id = parseInt(this.options.service_id, 10) || 0;
            this.form = $('#s-services-save');

            this.initView();
        },

        initView: function () {
            if (!this.container) {
                return;
            }

            var form = this.form;
            form.submit(function () {
                return false;
            });

            $('#s-sidebar').find('.selected').removeClass('selected');
            $('#s-services').addClass('selected').find('.count').text(this.options.count || 0);
            this.initEditable();

            var product_autocomplete = $('.product-autocomplete').autocomplete({
                source: '?action=autocomplete',
                minLength: 3,
                delay: 300,
                select: function (event, ui) {
                    var self = $(this);
                    var id = ui.item.id;
                    self.parents('tr:first').before(
                        tmpl('template-services-new-product', {
                            product: {id: ui.item.id, name: ui.item.value},
                            service_id: $.product_services.service_id
                        })
                    );

                    // exclude product ID from delete_product[]
                    var delete_product = form.find('input[name=delete_product\\[\\]]');
                    var product_ids = $.unique(
                        delete_product.map(function () {
                                var val = $(this).val();
                                if (id != val) {
                                    return $(this).val();
                                }
                            }
                        ));
                    delete_product.remove();
                    for (var i = 0, n = product_ids.length; i < n; i += 1) {
                        form.append('<input type="hidden" name="delete_product[]" value="' + product_ids[i] + '">');
                    }

                    self.val('');

                    var autocomplete = self.data('autocomplete');
                    autocomplete.do_not_close_autocomplete = 1;
                    window.setTimeout(function () {
                        autocomplete.do_not_close_autocomplete = false;
                        autocomplete.menu.element.position($.extend({
                            of: autocomplete.element
                        }, autocomplete.options.position || {my: "left top", at: "left bottom", collision: "none"}));
                    }, 0);

                    $.product_services.calcProductCount();
                    $.product_services.testProductsCheckbox();

                    return false;
                }
            });
            var autocomplete = product_autocomplete.data('autocomplete');
            var oldClose = autocomplete.close;
            autocomplete.close = function (e) {
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
            $('#s-save-service-submit').click(function () {
                var that = this;
                $(that).attr('disabled', true);
                var showSuccessIcon = function () {
                    var icon = $(that).parent().find('i.yes').show();
                    setTimeout(function () {
                        icon.hide();
                    }, 3000);
                };
                var showLoadingIcon = function () {
                    var p = $(that).parent();
                    p.find('i.yes').hide();
                    p.find('i.loading').show();
                };
                var hideLoadingIcon = function () {
                    var p = $(that).parent();
                    p.find('i.yes').hide();
                    p.find('i.loading').hide();
                };
                // after update services hash, dispatching and load proper content
                // 'afterServicesAction' will be called. Extend this handler
                var prevHandler = $.products.afterServicesAction;
                $.products.afterServicesAction = function () {
                    showSuccessIcon();
                    if (typeof prevHandler == 'function') {
                        prevHandler.apply($.products, arguments);
                    }
                    $.products.afterServicesAction = prevHandler;
                };
                // send post
                showLoadingIcon();
                $.products.jsonPost(form.attr('action'), form.serialize(),
                    function (r) {
                        if ($.product_services.service_id) {
                            $.products.dispatch();
                        } else {
                            $.wa.setHash('#/services/' + r.data.id + '/');
                        }
                    }, function (e) {
                        $(that).attr('disabled', false);
                        hideLoadingIcon();
                        renderErrors(e.errors);
                    }
                );
                return false;
            });
            form.off('change', '.s-service-currency').on('change', '.s-service-currency', function () {
                var self = $(this), val = self.val();
                form.find('.s-service-currency').val(val);
                $('#s-service-currency-code').val(val);
            });
            this.initHandlers();

            function renderErrors(errors) {
                var $price = form.find('[name="price[]"]');

                $.each(errors, function(i, error) {
                    if (error.text) {
                        var $field = $price.eq(error.id);
                        if ($field.length) {
                            renderError(error, $field);
                        }
                    }
                });

                function renderError(error, $field) {
                    var $error = $('<em class="errormsg"></em>').text(error.text);
                    var error_class = 'error';

                    if (!$field.hasClass(error_class)) {
                        $field.on('change keyup', removeFieldError).addClass(error_class).closest('td').append($error);
                    }

                    function removeFieldError() {
                        $field.off('change keyup', removeFieldError).removeClass(error_class);
                        $error.remove();
                    }
                }
            }
        },

        initEditable: function () {
            var title = $('#s-service-title');
            title.inlineEditable({
                minSize: {width: 350},
                maxSize: {width: 400},
                size: {height: 30},
                inputClass: 's-title-h1-edit',
                afterBackReadable: function (input, data) {
                    if (!data.changed) {
                        return false;
                    }
                    if (!$.product_services.service_id) {
                        $('#s-save-service-submit').click();
                    } else {
                        var name = $(input).val();
                        $.products.jsonPost('?module=service&action=save&edit=name&id=' + $.product_services.service_id, {
                            name: name
                        }, function () {
                            $.product_services.container.find('#s-services-list li.selected .name').text(name);
                        });
                    }
                },
                afterMakeEditable: function (input) {
                    input.select();
                }
            });
            if (!this.service_id) {
                title.trigger('editable');
            }
        },

        initHandlers: function () {
            var form = this.form;
            var container = this.container;


            var addNewOption = function () {
                var original = container.find('.s-services-variant:last');
                var row = original.clone();

                row.find('input[name=name\\[\\]]').val('');
                row.find('input[name=variant\\[\\]]').val(0);
                row.find('.s-services-type-of-price input[type=radio]').each(function () {
                    var item = $(this);
                    var index = item.attr('name').replace('type_of_price_', '');
                    item.attr('name', 'type_of_price_' + (index + 1));
                });
                var input = row.find('input[name=default]');
                input.attr('checked', false);
                input.val((parseInt(input.val()) || 0) + 1);
                var currency = original.find('select.s-service-currency').val();
                row.find('select.s-service-currency').val(currency);
                $(this).parents('tr:first').before(row);
            };

            container.off('click', '.s-multiple-options-toggle').on('click', '.s-multiple-options-toggle', function () {
                    $(this).hide();

                    // expand row
                    $.when(container.find('.s-services-ext-cell').show('fast')).done(
                        function () {
                            container.find('.s-delete-product').show();
                            container.find('.s-delete-option').show();
                            container.find('.s-add-row').show();
                            $(this).find('input').attr('disabled', false);
                            addNewOption.call(container.find('.s-add-new-option'));
                            container.find('input[name=name\\[\\]]:first').focus();

                            container.find('.s-service-currency:first').change();

                            // process rows of deleted variants
                            container.find('tr.s-services-variant-deleted').each(function () {
                                var tds = $(this).find('td');
                                tds.eq(0).show();
                                tds.eq(2).attr('colspan', 2);
                            });
                        }
                    );
                    return false;
                }
            );
            container.find('.s-add-new-option').unbind('click').bind('click', function () {
                    addNewOption.call(this);
                    return false;
                }
            );
            container.off('click', '.s-delete-service').on('click', '.s-delete-service', function () {
                    var d = $('#s-delete-service');
                    d.waDialog({
                        disableButtonsOnSubmit: true,
                        onLoad: function () {
                        },
                        onSubmit: function () {
                            var self = $(this);
                            $.products.jsonPost(self.attr('action'), self.serialize(), function () {
                                var list = $.product_services.container.find('#s-services-list');
                                var li = list.find('li.selected');
                                var near = li.prev();
                                if (!near.length) {
                                    near = li.next();
                                }
                                if (near.length) {
                                    location.hash = near.find('a').attr('href');
                                } else if ($.products.hash === 'services') {
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

            container.off('click', '.s-delete-option').on('click', '.s-delete-option',
                function () {
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
                        tr.html(html).addClass('gray highlighted s-services-variant-deleted').removeClass('s-services-variant');

                    } else {
                        self.hide();

                        // squeeze row
                        $.when(container.find('.s-services-ext-cell').hide('fast')).done(function () {
                            container.find('.s-add-row').hide();
                            container.find('.s-multiple-options-toggle').show();
                            container.find('.s-services-ext-cell').find('input').attr('disabled', true);
                            //form.find('input[name=variant\\[\\]]').val(0);

                            // process rows of deleted variants
                            container.find('tr.s-services-variant-deleted').each(function () {
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

            container.off('click', '.s-delete-product').on('click', '.s-delete-product',
                function () {
                    var self = $(this);
                    var tr = self.parents('tr:first');
                    tr.remove();
                    if (!tr.hasClass('s-new-product')) {
                        // accumulate ids for deleting
                        form.append('<input type="hidden" name="delete_product[]" value="' + tr.attr('data-product-id') + '">');
                    }
                    $.product_services.calcProductCount();
                    $.product_services.testProductsCheckbox();
                    return false;
                }
            );

            var types = $('#s-services-types');
            types.off('click', 'input').on('click', 'input', function () {
                    $.product_services.calcProductCount();
                }
            );

        },

        calcProductCount: function () {
            var products = $('#s-services-products');
            var products_count = $('#s-services-products-count');
            var type_id = $('#s-services-types').find('input:checked').map(function () {
                return $(this).val();
            }).toArray();
            var product_id = products.find('input[name="product[]"]').map(function () {
                return $(this).val();
            }).toArray();
            $.products.jsonPost('?module=services&action=productsCount', {type_id: type_id, product_id: product_id},
                function (r) {
                    products_count.text(r.data.count_text);
                }
            );
        },

        testProductsCheckbox: function () {
            if ($('#s-services-products').find('input[name="product[]"]:first').length) {
                $('#s-services-products-choosen').attr('checked', true);
            } else {
                $('#s-services-products-choosen').attr('checked', false);
            }
        }

    };
})(jQuery);