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
            loading: $_('Loading') + '... <i class="fas fa-spinner fa-spin"><i>'
        },

        payment_data: {
            'null': null
        },

        $payment_plugin_container: null,
        $payment_container: null,
        $payment_plugins_container: null,
        $payment_menu: null,
        locales: null,
        /**
         * Init section
         *
         * @param string tail
         */
        paymentInit: function (options) {
            $.shop.trace('$.settings.paymentInit');
            const self = this;
            /* init settings */

            this.$payment_plugin_container = this.$container.find('#s-settings-payment-setup');
            this.$payment_container = this.$container.find('#s-settings-payment');
            this.$payment_plugins_container = this.$container.find('#s-settings-payment-plugins');
            this.$payment_menu = this.$container.find('.js-payment-menu');

            this.$container.on('click', 'a.js-action', function () {
                return self.click($(this));
            });

            this.$payment_menu.waDropdown();
            this.updateDropdownSecondaryActions();

            if ($.fn.sortable) {
                this.$payment_container.find('tbody').sortable({
                    group: 'payments-rows',
                    handle: '.js-sort',
                    animation: 100,
                    removeCloneOnHide: true,
                    onEnd: function (evt) {
                      const $item = $(evt.item);
                      const id = parseInt($item.data('id'));
                      let after_id = $item.prev().data('id');

                      if (after_id === undefined) {
                        after_id = 0;
                      } else {
                        after_id = parseInt(after_id);
                      }

                      self.paymentSort(id, after_id, () => {
                          $item.swap(evt.oldIndex);
                      });
                    },
                });
            }

            this.$payment_plugin_container.on('submit', 'form', function () {
                const $this = $(this);

                if ($this.hasClass('js-installer')) {
                    let confirmValue = false;

                    $.waDialog.confirm({
                         title: $el.attr('title'),
                        text: $el.data('confirm-text'),
                        success_button_title: $_('Are you sure?'),
                        success_button_class: 'danger',
                        cancel_button_title: $el.data('cancel') || $_('Cancel'),
                        cancel_button_class: 'light-gray',
                        onSuccess() {
                            confirmValue = true;
                        }
                    });

                    return (!$this.hasClass('js-confirm') || confirmValue);
                } else {
                    return self.paymentPluginSave($this);
                }
            });

            this.$payment_plugin_container.on('change', ':input.js-settings-payment-customer-type', function () {
                /** @this HTMLInputElement */
                const shipping_type = self.paymentHelper.selectedShippingTypes();
                const customer_type = this.value;
                self.paymentFilterShippingPlugins(customer_type, shipping_type);
            });

            this.$payment_plugin_container.on('change', ':input[name^="payment\\[shipping_type\\]"]', function () {
                /** @this HTMLInputElement */
                const shipping_type = self.paymentHelper.selectedShippingTypes();
                const customer_type = self.$payment_plugin_container.find(':input[name="payment\[options\]\[customer_type\]"]:checked').val();
                self.paymentFilterShippingPlugins(customer_type, shipping_type);
            });

            self.locales = options.locales;
            //init clone plugin
            self.paymentPluginClone();

            $(document).on('installer_after_install_go_to_settings', function(e, data) {
                if (data.type === 'plugin' && data.is_payment) {
                    location.replace(`#/payment/plugin/add/${data.id}/`);
                }
            });
        },

        updateDropdownSecondaryActions: function () {
            $(".dropdown.secondary-actions").waDropdown({
              hover: true,
              items: ".menu > li > a",
              update_title: false
            });
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
            const method = $.shop.getMethod(tail.split('/'), this, 'payment');
            $.shop.trace('$.settings.paymentAction', [method, this.path, tail]);

            if (method.name) {
                this[method.name].apply(this, method.params);
            } else {
                this.$payment_menu.show();
                this.$payment_container.show();
                this.$payment_plugin_container.html(this.payment_options.loading).hide();
                if (this.$payment_plugins_container.is(':empty')) {
                    this.loadInstallerPlugins();
                } else {
                    this.$payment_plugins_container.show();
                }
                $('#s-settings-content h1.js-bread-crumbs:not(:first)').remove();
                $('#s-settings-content h1:first').show();
                $('#s-settings-content .s-settings-payment-header-hint').show();
            }
        },

        paymentSort: function (id, after_id, callRevert) {
            $.post('?module=settings&action=paymentSort', {
                module_id: id,
                after_id: after_id
            }, function (response) {
                $.shop.trace('$.settings.paymentSort result', response);
                if (response.error) {
                    $.shop.error('Error occurred while sorting payment plugins', 'error');
                    callRevert();
                } else if (response.status !== 'ok') {
                    $.shop.error('Error occurred while sorting payment plugins', response.errors);
                    callRevert();
                }
            }, 'json').fail(function (response) {
                $.shop.trace('$.settings.paymentSort cancel', [response]);
                callRevert();
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
                const $this = $(this);
                const plugin_customer_type = $this.data('customer-type');
                const plugin_shipping_type = $this.data('shipping-type');
                const available = {
                    'customer': (plugin_customer_type === '') || (customer_type === '') || (plugin_customer_type === customer_type),
                    'shipping': (plugin_shipping_type === '') || (shipping_type.length == 0) || (shipping_type.indexOf(plugin_shipping_type) >= 0)
                };

                const $hint = $this.parents('label').next('.hint');
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
            const self = this;
            this.paymentPluginShow(plugin_id, function () {
                const $plugin_name = self.$payment_plugin_container.find('.fields-group:first h1.js-bread-crumbs:first');
                const $title = $('#s-settings-content h1.js-bread-crumbs:first');
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
            const self = this;

            $('#s-settings-content .s-settings-payment-header-hint').hide();
            this.paymentPluginShow(plugin_id, function () {
                const $plugin_name = self.$payment_plugin_container.find('.fields-group:first h1.js-bread-crumbs:first');
                const $title = $('#s-settings-content h1.js-bread-crumbs:first');
                $title.after($plugin_name);
                $title.hide();
                if ($el === 'enable') {
                    self.$payment_plugin_container.find('[name="payment[status]"]').prop('checked', true);
                }
            });
        },

        paymentPluginShow: function (plugin_id, callback) {
            this.$payment_plugins_container.hide();
            this.$payment_menu.hide();
            this.$payment_container.hide();
            $('#s-settings-content .s-settings-payment-header-hint').hide();

            const self = this;
            const url = '?module=settings&action=paymentSetup&plugin_id=' + plugin_id;
            this.$payment_plugin_container.show().html(this.payment_options.loading).load(url, function () {
                if (typeof (callback) == 'function') {
                    callback();
                }

                self.$payment_plugin_container.find(':input[name="payment[options][customer_type]"]:checked, :input[name^="payment\[shipping_type\]"]').trigger('change');
                self.paymentTypeHandler();

                self.formChanged($(this).find('form:first'));
            });
        },

        paymentPluginClone: function () {
            const that = this;
            const $plugin_list = $('#s-settings-payment').find('table');

            $plugin_list.on('click', '.js-payment-plugin-clone', function (e) {
                e.preventDefault();

                const $self = $(this);
                const $tr = $self.closest('tr');
                const original_id = $tr.data('id');

                $.post('?module=settings&action=systemPluginClone', {original_id: original_id, type: 'payment'}).done(function (r) {
                    if (r && r.data && r.data.plugin_id) {
                        const id = r.data.plugin_id;
                        const $new_plugin = $tr.clone().attr('data-id', id);
                        const $title = $new_plugin.find('.js-plugin-title');
                        const is_off = $title.hasClass('gray');
                        const $setup = $new_plugin.find('.js-payment-plugin-setup');
                        const $delete = $new_plugin.find('.js-payment-plugin-delete');

                        //if plugin now off not need add text
                        if (!is_off) {
                            $title.addClass('gray').text($title.text() + '(' + that.locales['disabled'] + ')');
                        }

                        //change id in url
                        $setup.attr('href', '#/payment/plugin/setup/' + id + '/');
                        $delete.attr('href', '#/payment/plugin/delete/' + id + '/');

                        //add new node
                        $plugin_list.append($new_plugin);
                        $new_plugin[0].scrollIntoView({
                            behavior: "smooth"
                        });

                        that.updateDropdownSecondaryActions();
                    }
                });
            })
        },

        /**
         * @param {JQuery} $el
         */
        paymentPluginSave: function ($el) {
            const data = $el.serialize();
            const self = this;
            const url = '?module=settings&action=paymentSave';

            const $submit = $el.find(':submit');
            const $spinner = $('<span><i class="fas fa-spinner fa-spin text-gray"></i></span>');
            $submit.prop('disabled', true).after($spinner).removeClass('wa-animation-swing');

            $.post(url, data, function (data, textStatus, jqXHR) {
                self.dispatch('#/payment/', true);

                $submit.prop('disabled', false);
            }).fail(function () {
                $spinner.remove();
                $submit.addClass('wa-animation-swing');
                $submit.prop('disabled', false);
            });
            return false;
        },

        paymentPluginDelete: function (plugin_id) {
            const url = '?module=settings&action=paymentDelete';
            const self = this;

            $.post(url, {
                plugin_id: plugin_id
            }, function (data, textStatus, jqXHR) {
                self.dispatch('#/payment/', true);
            });
        },

        paymentPlugins: function () {
            this.$payment_container.hide();
            this.loadInstallerPlugins();
        },

        paymentHelper: {
            parent: $.settings,
            selectedShippingTypes: function () {
                const shipping_types = [];
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
            const that = this;
            const $type_variants = that.$payment_plugin_container.find('.js-payment-type-variant');
            const $prepaid = that.$payment_plugin_container.find(".js-payment-type-variant[data-payment-type='prepaid']");
            const $card = that.$payment_plugin_container.find(".js-payment-type-variant[data-payment-type='card']");
            const $cash = that.$payment_plugin_container.find(".js-payment-type-variant[data-payment-type='cash']");

            $type_variants.on('click', function () {
                const $self = $(this);
                const value = $self.val();
                update(value);
            });

            // Onload update
            $type_variants.each(function () {
                const $self = $(this);

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
        },

        formChanged: function($form) {
            const $submit = $form.find('[type="submit"]');
            const submitChanged = () => {
                $submit.removeClass('green').addClass('yellow');
            };

            $(':input', $form).on('input', submitChanged);
            $form.on('change', submitChanged);

            $submit.on('click', function() {
                $form.off('change input');
                $submit.removeClass('yellow').addClass('green');
            });
        },

        loadInstallerPlugins: function () {
            if (!$.settings.options.installer_access) {
                return;
            }
            const url = this.options.backend_url + 'installer/?module=plugins&ui=2.0&action=view&slug=wa-plugins/payment&return_hash=/payment/plugin/add/%plugin_id%/';
            $.get(url, (html) => {
                this.$payment_plugins_container.show().html(html);
                const $iframe = $('iframe.js-store-frame');
                const changeIframeTheme = () => {
                    const message = JSON.stringify({ theme: (document.documentElement.dataset.theme || 'light') });
                    $iframe[0].contentWindow.postMessage(message, '*');
                }

                const observer = new MutationObserver((mutationList) => {
                    for (const mutation of mutationList) {
                        if (mutation.type === "attributes" && mutation.attributeName === 'data-theme') {
                            changeIframeTheme();
                            break;
                        }
                    }
                });

                const prev_title = document.title;
                $iframe.on('load', () => {
                    document.title = prev_title;
                    changeIframeTheme();
                    observer.observe(document.documentElement, { attributes: true });
                })

                $.settings.$container.find('> :first').one('remove', function () {
                    observer.disconnect();
                })
            });
        }
    });
} else {
    //
}
/**
 * {/literal}
 */
