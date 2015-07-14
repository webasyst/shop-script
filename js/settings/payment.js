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
if (typeof($) != 'undefined') {

    $.extend($.settings = $.settings || {}, {

        payment_options: {
            null: null,
            loading: 'Loading...<i class="icon16 loading"><i>'
        },
        /**
         * Init section
         *
         * @param string tail
         */
        paymentInit: function () {
            $.shop.trace('$.settings.paymentInit');
            /* init settings */
            var self = this;
            $('#s-settings-content').on('click', 'a.js-action', function () {
                return self.click($(this));
            });

            $('#s-settings-payment').sortable({
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

            $('#s-settings-payment-setup').on('submit', 'form', function () {
                var $this = $(this);
                if ($this.hasClass('js-installer')) {
                    return (!$this.hasClass('js-confirm') || confirm($this.data('confirm-text') || $this.attr('title') || $_('Are you sure?')));
                } else {
                    return self.paymentPluginSave($this);
                }
            })

        },

        payment_data: {
            'null': null

        },

        /**
         * Disable section event handlers
         */
        paymentBlur: function () {
            $('#s-settings-payment-type-dialog').off('click', 'a.js-action');
            $('#s-settings-payment-type-dialog').remove();
            $('#s-settings-content').off('click', 'a.js-action');
            $('#s-settings-payment').off('change, click');
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
                $('#s-settings-content #s-payment-menu').show();
                $('#s-settings-content #s-settings-payment').show();
                $('#s-settings-payment-setup').html(this.payment_options.loading).hide();
                $('#s-settings-content h1.js-bread-crumbs:not(:first)').remove();
                $('#s-settings-content h1:first').show();
            }
        },

        paymentSort: function (id, after_id, list) {
            $.post('?module=settings&action=paymentSort', {
                module_id: id,
                after_id: after_id
            },function (response) {
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

        paymentPluginAdd: function (plugin_id, $el) {
            $.wa.dropdownsClose();
            this.paymentPluginShow(plugin_id, function () {
                var $title = $('#s-settings-content h1.js-bread-crumbs:first');
                $title.hide();
                var $plugin_name = $('#s-settings-payment-setup .field-group:first h1.js-bread-crumbs:first');
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
            this.paymentPluginShow(plugin_id, function () {
                var $title = $('#s-settings-content h1.js-bread-crumbs:first');
                var $plugin_name = $('#s-settings-payment-setup .field-group:first h1.js-bread-crumbs:first');
                $title.after($plugin_name);
                $title.hide();
            });

        },

        paymentPluginShow: function (plugin_id, callback) {
            $('#s-settings-content #s-payment-menu').hide();
            var $plugins = $('#s-settings-content #s-settings-payment');
            $plugins.hide();
            var url = '?module=settings&action=paymentSetup&plugin_id=' + plugin_id;
            $('#s-settings-payment-setup').show().html(this.payment_options.loading).load(url, function () {
                if (typeof(callback) == 'function') {
                    callback();
                }
            });
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
            $('#s-settings-content #s-settings-payment').hide();
            var url = this.options.backend_url + 'installer/?module=plugins&action=view&options[no_confirm]=1&slug=wa-plugins/payment&return_hash=/payment/plugin/add/%plugin_id%/';
            $('#s-settings-payment-setup').show().html(this.payment_options.loading).load(url);
        },

        paymentHelper: {
            parent: $.settings
        }

    });
} else {
    //
}
/**
 * {/literal}
 */
