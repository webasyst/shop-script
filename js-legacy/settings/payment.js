/**
 * {literal}
 *
 * @names payment*
 * @property {} payment_options
 * @method paymentInit
 * @method paymentAction
 * @method paymentBlur
 * @todo flush unavailable hash (edit/delete/etc)
 */
if (typeof ($) != 'undefined') {

    $.extend($.settings = $.settings || {}, {

        payment_options: {
            null: null,
            loading: $_('Loading') + '...<i class="icon16 loading"><i>'
        },

        payment_data: {
            'null': null
        },

        $payment_plugin_container: null,
        $payment_container: null,
        $payment_menu: null,
        locales: null,
        /**
         * Init section
         *
         * @param string tail
         */
        paymentInit: function (options) {
            $.shop.trace('$.settings.paymentInit');
            var self = this;
            /* init settings */

            this.$payment_plugin_container = this.$container.find('#s-settings-payment-setup');
            this.$payment_container = this.$container.find('#s-settings-payment');
            this.$payment_menu = this.$container.find('#s-payment-menu')


            this.$container.on('click', 'a.js-action', function () {
                return self.click($(this));
            });

            this.$payment_container.sortable({
                distance: 5,
                opacity: 0.75,
                items: '> tbody > tr:visible',
                handle: '.sort',
                cursor: 'move',
                axis: 'y',
                tolerance: 'pointer',
                update: function (event, ui) {
                    var id = parseInt($(ui.item).data('id'));
                    var after_id = $(ui.item).prev().data('id');
                    if (after_id === undefined) {
                        after_id = 0;
                    } else {
                        after_id = parseInt(after_id);
                    }
                    self.paymentSort(id, after_id, $(this));
                }
            });

            this.$payment_plugin_container.on('submit', 'form', function () {
                var $this = $(this);
                if ($this.hasClass('js-installer')) {
                    return (!$this.hasClass('js-confirm') || confirm($this.data('confirm-text') || $this.attr('title') || $_('Are you sure?')));
                } else {
                    return self.paymentPluginSave($this);
                }
            });

            this.$payment_plugin_container.on('change', ':input.js-settings-payment-customer-type', function () {
                /** @this HTMLInputElement */
                var shipping_type = self.paymentHelper.selectedShippingTypes();
                var customer_type = this.value;
                self.paymentFilterShippingPlugins(customer_type, shipping_type);
            });

            this.$payment_plugin_container.on('change', ':input[name^="payment\\[shipping_type\\]"]', function () {
                /** @this HTMLInputElement */
                var shipping_type = self.paymentHelper.selectedShippingTypes();
                var customer_type = self.$payment_plugin_container.find(':input[name="payment\[options\]\[customer_type\]"]:checked').val();
                self.paymentFilterShippingPlugins(customer_type, shipping_type);
            });

            self.locales = options.locales;
            //init clone plugin
            self.paymentPluginClone();
        },

        /**
         * Disable section event handlers
         */
        paymentBlur: function () {
            $('#s-settings-payment-type-dialog').off('click', 'a.js-action');
            $('#s-settings-payment-type-dialog').remove();
            this.$container.off('click', 'a.js-action');
            this.$payment_container.off('change, click');
        },


        /**
         *
         * @param {String} tail
         */
        paymentAction: function (tail) {
            var method = $.shop.getMethod(tail.split('/'), this, 'payment');
            $.shop.trace('$.settings.paymentAction', [method, this.path, tail]);
            if (method.name) {
                this[method.name].apply(this, method.params);
            } else {
                this.$payment_menu.show();
                this.$payment_container.show();
                this.$payment_plugin_container.html(this.payment_options.loading).hide();
                $('#s-settings-content h1.js-bread-crumbs:not(:first)').remove();
                $('#s-settings-content h1:first').show();
            }
        },

        paymentSort: function (id, after_id, list) {
            $.post('?module=settings&action=paymentSort', {
                module_id: id,
                after_id: after_id
            }, function (response) {
                $.shop.trace('$.settings.paymentSort result', response);
                if (response.error) {
                    $.shop.error('Error occurred while sorting payment plugins', 'error');
                    list.sortable('cancel');
                } else if (response.status != 'ok') {
                    $.shop.error('Error occurred while sorting payment plugins', response.errors);
                    list.sortable('cancel');
                }
            }, 'json').error(function (response) {
                $.shop.trace('$.settings.paymentSort cancel', [list, response]);
                list.sortable('cancel');
                $.shop.error('Error occurred while sorting payment plugins', 'error');
                return false;
            });
        },

        /**
         *
         * @param string customer_type
         * @param array shipping_type
         */
        paymentFilterShippingPlugins: function (customer_type, shipping_type) {
            $.shop.trace('$.settings.paymentFilterShippingPlugins', [customer_type, shipping_type]);
            this.$payment_plugin_container.find(':input[name^="payment\[shipping\]"]').each(function () {
                var $this = $(this);
                var plugin_customer_type = $this.data('customer-type');
                var plugin_shipping_type = $this.data('shipping-type');
                var available = {
                    'customer': (plugin_customer_type === '') || (customer_type === '') || (plugin_customer_type === customer_type),
                    'shipping': (plugin_shipping_type === '') || (shipping_type.length == 0) || (shipping_type.indexOf(plugin_shipping_type) >= 0)
                };

                var $hint = $this.parents('label').next('span.hint');
                if (available.customer && available.shipping) {
                    $hint.hide();
                } else {
                    $hint.show();
                }

                //$this.attr('disabled', (available.customer && available.shipping) ? null : true);

                $.shop.trace('$.settings.paymentFilterShippingPlugins ' + $this.parents('label').text(), [available.customer, available.shipping, $this]);
            });
        },

        paymentPluginAdd: function (plugin_id, $el) {
            $.wa.dropdownsClose();
            var self = this;
            this.paymentPluginShow(plugin_id, function () {
                var $plugin_name = self.$payment_plugin_container.find('.field-group:first h1.js-bread-crumbs:first');
                var $title = $('#s-settings-content h1.js-bread-crumbs:first');
                $title.hide();
                $title.after($plugin_name);
                $title.hide();
            });
        },

        /**
         * Show plugin setup options
         *
         * @param {String} plugin_id
         * @param {JQuery} $el
         */
        paymentPluginSetup: function (plugin_id, $el) {
            var self = this;
            this.paymentPluginShow(plugin_id, function () {
                var $plugin_name = self.$payment_plugin_container.find('.field-group:first h1.js-bread-crumbs:first');
                var $title = $('#s-settings-content h1.js-bread-crumbs:first');
                $title.after($plugin_name);
                $title.hide();
            });

        },

        paymentPluginShow: function (plugin_id, callback) {
            this.$payment_menu.hide();
            this.$payment_container.hide();
            var self = this;
            var url = '?module=settings&action=paymentSetup&plugin_id=' + plugin_id;
            this.$payment_plugin_container.show().html(this.payment_options.loading).load(url, function () {
                if (typeof (callback) == 'function') {
                    callback();
                    self.initElasticFooter();
                }

                self.$payment_plugin_container.find(':input[name="payment[options][customer_type]"]:checked, :input[name^="payment\[shipping_type\]"]').trigger('change');
                self.paymentTypeHandler();
            });
        },

        initElasticFooter: function () {
            var that = this;

            // DOM
            var $window = $(window),
                $wrapper = that.$container,
                $header = $wrapper.find(".js-footer-block"),
                $dummy = false,
                is_set = false;

            var active_class = "is-fixed-to-bottom";

            var header_o, header_w, header_h;

            clear();

            $window.on("scroll", useWatcher);
            $window.on("resize", onResize);

            onScroll();

            function useWatcher() {
                var is_exist = $.contains(document, $header[0]);
                if (is_exist) {
                    onScroll();
                } else {
                    $window.off("scroll", useWatcher);
                }
            }

            function onScroll() {
                var scroll_top = $window.scrollTop(),
                    use_scroll = header_o.top + header_h > scroll_top + $window.height();

                if (use_scroll) {
                    if (!is_set) {
                        is_set = true;
                        $dummy = $("<div />");

                        $dummy.height(header_h).insertAfter($header);

                        $header
                            .css("left", header_o.left - 20) //20 -  Because parents are doing padding 20
                            .width(header_w)
                            .addClass(active_class);
                    }

                } else {
                    clear();
                }
            }

            function onResize() {
                clear();
                $window.trigger("scroll");
            }

            function clear() {
                if ($dummy && $dummy.length) {
                    $dummy.remove();
                }
                $dummy = false;

                $header
                    .removeAttr("style")
                    .removeClass(active_class);

                header_o = $header.offset();
                header_w = $header.outerWidth() + 40; // Because parents are doing padding 20
                header_h = $header.outerHeight();

                is_set = false;
            }
        },


        paymentPluginClone: function () {
            var that = this,
                $plugin_list = $('#s-settings-payment');

            $plugin_list.on('click', '.js-payment-plugin-clone', function (e) {
                e.preventDefault();
                var $self = $(this),
                    $tr = $self.closest('tr'),
                    original_id = $tr.data('id');

                $.post('?module=settings&action=systemPluginClone', {original_id: original_id, type: 'payment'}).success(function (r) {
                    if (r && r.data && r.data.plugin_id) {
                        var id = r.data.plugin_id,
                            $new_plugin = $tr.clone().attr('data-id', id),
                            $title = $new_plugin.find('.js-plugin-title'),
                            is_off = $title.hasClass('gray'),
                            $setup = $new_plugin.find('.js-payment-plugin-setup'),
                            $delete = $new_plugin.find('.js-payment-plugin-delete');

                        //if plugin now off not need add text
                        if (!is_off) {
                            $title.addClass('gray').text($title.text() + '(' + that.locales['disabled'] + ')');
                        }

                        //change id in url
                        $setup.attr('href', '#/payment/plugin/setup/' + id + '/');
                        $delete.attr('href', '#/payment/plugin/delete/' + id + '/');

                        //add new node
                        $plugin_list.append($new_plugin);
                    }
                });
            })
        },

        /**
         * @param {JQuery} $el
         */
        paymentPluginSave: function ($el) {
            var data = $el.serialize();
            var self = this;
            var url = '?module=settings&action=paymentSave';
            $.post(url, data, function (data, textStatus, jqXHR) {
                self.dispatch('#/payment/', true);
            });
            return false;
        },

        paymentPluginDelete: function (plugin_id) {
            var url = '?module=settings&action=paymentDelete';
            var self = this;
            $.post(url, {
                plugin_id: plugin_id
            }, function (data, textStatus, jqXHR) {
                self.dispatch('#/payment/', true);
            });

        },

        paymentPlugins: function () {
            this.$payment_container.hide();
            var url = this.options.backend_url + 'installer/?module=plugins&action=view&slug=wa-plugins/payment&return_hash=/payment/plugin/add/%plugin_id%/';
            this.$payment_plugin_container.show().html(this.payment_options.loading).load(url);
        },

        paymentHelper: {
            parent: $.settings,
            selectedShippingTypes: function () {
                var shipping_types = [];
                var pattern = /\[(\w+)]$/;
                this.parent.$payment_plugin_container.find(':input[name^="payment\[options\]\[shipping_type\]"]:checked').each(function () {
                    shipping_types.push(this.value);
                });
                return shipping_types;
            }
        },

        /**
         * Does not allow to choose both prepayment and on-site payment
         */
        paymentTypeHandler: function () {
            var that = this,
                $type_variants = that.$payment_plugin_container.find('.js-payment-type-variant'),
                $prepaid = that.$payment_plugin_container.find(".js-payment-type-variant[data-payment-type='prepaid']"),
                $card = that.$payment_plugin_container.find(".js-payment-type-variant[data-payment-type='card']"),
                $cash = that.$payment_plugin_container.find(".js-payment-type-variant[data-payment-type='cash']");

            $type_variants.on('click', function () {
                var $self = $(this),
                    value = $self.val();
                update(value);
            });

            // Onload update
            $type_variants.each(function () {
                var $self = $(this);
                if ($self.attr('checked')) {
                    update($self.val());
                    return true;
                }
            });

            function update(value) {
                if (value === 'prepaid') {
                    // If you choose a prepayment, then turn off the remaining payment methods
                    if ($prepaid.attr('checked')) {
                        $card.attr('disabled', true).attr('checked', false);
                        $cash.attr('disabled', true).attr('checked', false);
                    } else {
                        $card.attr('disabled', false).attr('checked', false);
                        $cash.attr('disabled', false).attr('checked', false);
                    }
                } else {
                    // If payment on the spot is selected, then turn off prepayment
                    if (!$card.attr('checked') && !$cash.attr('checked')) {
                        $prepaid.attr('disabled', false);
                    } else {
                        $prepaid.attr('disabled', true).attr('checked', false);
                    }
                }
            }
        }
    });
} else {
    //
}
/**
 * {/literal}
 */
