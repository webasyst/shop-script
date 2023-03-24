/**
 * @names editTabServices*
 * @property {} services_options
 * @property {} services_values
 * @method editTabServicesInit
 * @method editTabServicesAction
 * @method editTabServicesBlur
 */
$.product = $.extend(
    true,
    $.product,
    {
        services_options: {},

        services_data: {
            service_id: 0,

            $container: null,
            $sidebar: null,
            $form: null
        },

        editTabServicesFocus: function () {
        },

        editTabServicesServiceFocus: function (service_id) {
            $.shop.trace('$.product.editTabServicesServiceFocus', service_id);

            this.editTabServicesServiceBlur();
            this.setData('services', 'service_id', service_id);

            var item = this.editTabServicesHelper.getServiceMenuItem(service_id);

            item.addClass('selected');


            var container = this.editTabServicesHelper.getServiceContainer(service_id);
            container.show();

            $.shop.helper.setEventHandler(container,
                'change.product_services_' + service_id + ' keyup.product_services_' + service_id,
                function () {
                    try {
                        $.product.helper.checkChanges(
                            container,
                            false,
                            function (changed) {
                                $.product.editTabServicesHelper.changesHandler(service_id, changed);
                            }
                        );
                    } catch (e) {
                        $.shop.error('XXX', e);
                    }
                },
                'input[type=checkbox], input[type=radio], input[type=text]'
            );
        },

        editTabServicesServiceBlur: function () {
            var container = this.getData('services', '$container');
            var sidebar = this.getData('services', '$sidebar');

            sidebar.find('li.selected').removeClass('selected');
            container.find('div.js-product-service:visible').hide();
        },

        editTabServicesServiceLoad: function (product_id, service_id) {


            var $menu_item = this.editTabServicesHelper.getServiceMenuItem(service_id);
            $menu_item.find('.icon16').removeClass('ss service').addClass('loading');

            this.editTabServicesHelper.getServiceContainer(service_id).remove();

            //create empty container
            var $form = this.getData('services', '$form');
            $form.append('<div id="s-product-edit-service-' + service_id + '" class="block double-padded js-product-service s-product-form-chunk"><i class="icon16 loading"></i></div');

            var url = '?module=product&action=services&id=' + product_id + '&param[]=' + service_id;
            var self = this;

            $.get(url,
                function (html) {
                    var $html = $($.parseHTML(html));
                    var target = $html.filter('div.content').find('div.js-product-service');

                    self.editTabServicesHelper.getServiceContainer(service_id).remove();
                    $form.append(target);
                    $menu_item.find('.icon16').removeClass('loading').addClass('ss service');
                    self.editTabServicesServiceFocus(service_id);
                }
            );
        },

        editTabServicesServiceOnLoad: function () {

        },

        editTabServicesInit: function (path) {
            var $container = $('.s-product-form.services');
            var $form = $container.find('form');
            var $sidebar = $container.find('.s-inner-sidebar');
            if (!$sidebar) {
                return;
            }

            this.setData('services', '$container', $container);
            this.setData('services', '$form', $form);
            this.setData('services', '$sidebar', $sidebar);

            if (path.tail) {
                this.setData('services', 'service_id', parseInt(path.tail, 10));
            } else {
                var $selected = $sidebar.find('>ul>li.selected');
                if ($selected.length) {
                    this.setData('services', 'service_id', parseInt($selected.data('service-id'), 10));
                }
            }

            $.product.editTabServicesHelper.updateCounter();

            // keep track of changing checkboxes and radiobuttons
            $form.on(
                'change.product_services keyup.product_services',
                'input[type=checkbox], input[type=radio], input[type=text]',
                function () {
                    $.product.helper.checkChanges(
                        $container,
                        false,
                        function (changed) {
                            $.product.helper.onTabChanged('services', changed);
                            $.product.editTabServicesHelper.updateCounter();
                        }
                    );
                }
            );

            $form.submit(function () {
                this.editTabServicesSave();
            });

            this.editTabServicesHelper.initHandlers($container, $form);
        },

        editTabServicesAction: function (path) {

            if (path.tail) {

                var service_id = parseInt(path.tail, 10);
                if (service_id != this.getData('services', 'service_id')) {
                    this.setData('services', 'service_id', service_id);
                    this.editTabServicesServiceBlur();

                    var self = this;
                    var $form = this.getData('services', '$form');

                    var target = this.editTabServicesHelper.getServiceContainer(service_id);

                    if (target.length) {
                        this.editTabServicesServiceFocus(service_id);
                    } else {
                        this.editTabServicesServiceLoad(path.id, service_id)
                    }
                } else {
                    this.editTabServicesServiceFocus(service_id);
                }
            } else {
                this.editTabServicesServiceFocus(this.getData('services', 'service_id'));
            }
        },


        editTabServicesBlur: function () {
            var changed = false;

            if (changed) {
                this.editTabServicesHelper.save();
            }
        },

        /**
         * Post save handler
         */
        editTabServicesSaved: function () {
            var $form = this.getData('services', '$form');
            $form.find('div.js-product-service').remove();
            this.editTabServicesServiceLoad(this.path.id, this.getData('services', 'service_id'));
        },

        editTabServicesSave: function () {
            var $form = $.product.getData('services', '$form');
            // there is might too many "emptiness" in post data,
            // so to optimize (and to prevent exceed php.ini 'max_input_vars' parameter) we prepare sparse data array
            var data = $form.serializeArray(),
                len = data.length,
                sparse_data = [];
            for (var i = 0; i < len; i++) {
                var val = $.trim(data[i].value);
                if (val.length > 0) {
                    sparse_data.push(data[i]);
                }
            }

            var self = this;

            return $.shop.jsonPost(
                $form.attr('action'),
                sparse_data,
                function (r) {
                    $.each(r.data.status, function (service_id, status) {
                        self.editTabServicesHelper.changesHandler(service_id, false, status);
                    });

                    $.product.editTabServicesHelper.updateCounter(r.data.count);
                }
            );
        },

        // unfold skus to this service variant
        editTabServicesSku: function (service_id, variant_id) {
            var container = this.editTabServicesHelper.getServiceContainer(service_id);
            container.find('tr.s-services-variant-sku[data-variant-id=' + variant_id + ']').toggle();
        },
        editTabServicesHelper: {
            getServiceContainer: function (service_id) {
                var $form = $.product.getData('services', '$form');
                return $form.find('div#s-product-edit-service-' + service_id + ':first');
            },
            getServiceMenuItem: function (service_id) {
                var sidebar = $.product.getData('services', '$sidebar');
                return sidebar.find('li[data-service-id="' + service_id + '"]');
            },

            updateCounter: function (count) {
                var $counter = $('#s-product-edit-menu li.services span.hint');
                if (count === undefined) {
                    var $sidebar = $.product.getData('services', '$sidebar');
                    count = $sidebar.find('li:not(.gray)').length;
                }
                $counter.text(count);
            },

            changesHandler: function (service_id, changed, status) {
                $.shop.trace('$.product.editTabServicesHelper.changesHandler ' + service_id, changed);
                var $menu_item = this.getServiceMenuItem(service_id);
                if (changed) {
                    $menu_item.find('.icon10').show();
                } else {
                    $menu_item.find('.icon10').hide();
                }

                if (status === undefined) {

                    var $scope = this.getServiceContainer(service_id);
                    // if all unchecked then turn highlighting off on proper sidebar item
                    var test = $scope.find('input:checked:first');
                    status = test.length;
                }
                this.updateServiceStatus(service_id, status);
            },

            updateServiceStatus: function (service_id, status) {
                var $scope = this.getServiceContainer(service_id);
                var $menu_item = this.getServiceMenuItem(service_id);

                $.shop.trace('$.proudct.editTabServicesHelper.updateServiceStatus ' + service_id, [status, $menu_item, $scope]);

                var $controls = $scope.find('.js-toggle-gray');

                var $menu_item = this.getServiceMenuItem(service_id);
                if (status) {
                    $menu_item.removeClass('gray');
                    $controls.removeClass('gray');
                } else {
                    $menu_item.addClass('gray');
                    $controls.addClass('gray');
                }
            },

            initHandlers: function (container, form) {

                var reset_radio = function (radio) {
                    if (radio.attr('checked')) {
                        radio.attr('checked', false);
                        var first = container.find('tr.s-services-variant-product input[type=checkbox]:checked:first');
                        if (first.length) {
                            first.parents('tr:first').find('input[type=radio]').attr('checked', true);
                        }
                    }
                };

                // helper that maintains: if there is no any checked radio makes checked first radio
                var check_radio = function () {
                    var checked_radio = container.find('tr.s-services-variant-product input[type=radio]:checked:first');
                    if (!checked_radio.length) {
                        var first = container.find('tr.s-services-variant-product input[type=checkbox]:checked:first');
                        if (first.length) {
                            first.parents('tr:first').find('input[type=radio]').attr('checked', true);
                        }
                    }
                };

                // service variant checkbox handler: if service variant is checked then all proper skus are checked
                var check_checkbox_handler = function () {
                    var self = $(this);
                    var tr = self.parents('tr:first');
                    var variant_id = tr.attr('data-variant-id');
                    var tr_skus = container.find('tr.s-services-variant-sku[data-variant-id=' + variant_id + ']');
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


                // if check service variant then check all skus for it
                $.shop.helper.setEventHandler(
                    container,
                    'click',
                    check_checkbox_handler,
                    'tr.s-services-variant-product input[type=checkbox]'
                );
                // if check some sku then must be checked also service variant
                $.shop.helper.setEventHandler(
                    container,
                    'click',
                    function () {
                        var self = $(this);
                        var tr = self.parents('tr:first');
                        var variant_id = tr.attr('data-variant-id');
                        var tr_variant = container.find('tr.s-services-variant-product[data-variant-id=' + variant_id + ']');
                        if (self.attr('checked')) {
                            tr_variant.find('input[type=checkbox]').attr('checked', true);
                            check_radio();
                        } else {
                            // if there is not any checked sku for this service variant then uncheck service variant checkbox and radio
                            var any_checked =
                                tr_variant.nextAll('.s-services-variant-sku[data-variant-id=' + variant_id + ']').find('input[type=checkbox]:checked:first');
                            if (!any_checked.length) {
                                tr_variant.find('input[type=checkbox]').attr('checked', false);
                                tr_variant.find('input[type=radio]').attr('checked', false);
                                check_radio();
                            }
                        }
                    },
                    'tr.s-services-variant-sku input[type=checkbox]'
                );

                // when choose another service variant make proper checkbox checked
                $.shop.helper.setEventHandler(
                    container,
                    'click',
                    function () {
                        var $tr = $(this).parents('tr:first');
                        var $checkbox = $tr.find('input[type=checkbox]');
                        if (!$checkbox.attr('checked')) {
                            $checkbox.attr('checked', true);
                            check_checkbox_handler.call($checkbox.get(0));
                        }
                    },
                    'tr.s-services-variant-product input[type=radio]'
                );

                // update sku default prices (placeholdres)
                $.shop.helper.setEventHandler(
                    container,
                    'keyup change',
                    function () {
                        var self = $(this);
                        var $tr = self.closest('tr');

                        var placeholder, value = self.val().replace(/[^,\.0-9\-]/g, '');
                        if (!value.length) {
                            placeholder = self.attr('placeholder');
                            value = '';
                        } else {
                            placeholder = value;
                        }
                        self.val(value);


                        var variant_id = $tr.attr('data-variant-id');
                        var $checkbox = $tr.find('input[type=checkbox]');
                        if (!$checkbox.attr('checked') && value.length) {
                            $checkbox.attr('checked', true);
                            check_checkbox_handler.call($checkbox.get(0));
                        }


                        container.find(
                            'tr.s-services-variant-sku[data-variant-id=' + variant_id + '] input[type=text]'
                        ).attr('placeholder', placeholder);

                    },
                    'tr.s-services-variant-product input[type=text]'
                );

                $.unique(
                    container
                        .find('.s-services-variant-sku input[name^=variant_sku_price]')
                        .map(
                            function () {
                                if ($(this).val()) {
                                    return $(this).closest('.s-services-variant-sku').data('variantId')
                                }
                            }
                        )
                ).each(function (i, variant_id) {
                    container.find('.s-services-by-sku[data-variant-id="' + variant_id + '"]').click();
                });
            }
        }
    }
);