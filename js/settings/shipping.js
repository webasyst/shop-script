/**
 * {literal}
 *
 * @names shipping*
 * @property shipping_options
 * @method shippingInit
 * @method shippingAction
 * @method shippingBlur
 * @todo flush unavailable hash (edit/delete/etc)
 */
if (typeof ($) != 'undefined') {

    $.extend($.settings = $.settings || {}, {

        shipping_options: {
            null: null,
            loading: $_('Loading') + '...<i class="fas fa-spinner fa-spin"><i>'
        },

        locales: null,
        /**
         * Init section
         *
         */
        shippingInit: function (options) {
            /* init settings */
            const self = this;
            const $form = $('#s-settings-shipping-params-form');

            this.$shipping_plugins_container = this.$container.find('#s-settings-shipping-plugins');
            this.$shipping_plugin_container = $('#s-settings-shipping-setup');

            this.formChanged($form);

            $('#s-settings-content').on('click', 'a.js-action', function () {
                return self.click($(this));
            });

            this.updateDropdownSecondaryActions();

            $('#s-settings-shipping tbody').sortable({
              group: 'shipping-rows',
              handle: '.js-sort',
              animation: 100,
              removeCloneOnHide: true,
              onEnd: function (evt) {
                const $item = $(evt.item);
                const id = parseInt($item.data('id'), 10);
                let after_id = $item.prev().data('id');

                if (after_id === undefined) {
                  after_id = 0;
                } else {
                  after_id = parseInt(after_id, 10);
                }

                self.shippingSort(id, after_id, () => {
                    $item.swap(evt.oldIndex);
                });
              }
            });

            $form.on('submit', function () {
              const $this = $(this);
              return self.shippingParamsSave($this);
            });

            this.$shipping_plugin_container.on('submit', 'form', function () {
                const $this = $(this);

                if ($this.hasClass('js-installer')) {
                    let confirmValue = false;

                    $.waDialog.confirm({
                        title: $el.attr('title'),
                        text: $el.data('confirm-text'),
                        success_button_title: $_('Are you sure?'),
                        success_button_class: 'danger',
                        cancel_button_title: $el.data('cancel') || $.wa.locale['cancel'] || 'Cancel',
                        cancel_button_class: 'light-gray',
                        onSuccess() {
                            confirmValue = true;
                        }
                    });

                    return (!$this.hasClass('js-confirm') || confirmValue);
                } else {
                    const event = self.shippingPluginSaveEvent($this);

                    if (event) {
                        return self.shippingPluginSave($this);
                    } else {
                        return false;
                    }
                }
            });

            self.locales = options.locales;
            //init clone plugin
            self.shippingPluginClone();

            $(document).on('installer_after_install_go_to_settings', function(e, data) {
                if (data.type === 'plugin' && data.is_shipping) {
                    location.replace(`#/shipping/plugin/add/${data.id}/`);
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

        shippingPluginSaveEvent: function ($form) {
            const self = this;
            let result = true;
            const beforeSaveEvent = new $.Event('shop_save_shipping');

            beforeSaveEvent.errors = [];
            $form.trigger(beforeSaveEvent);

            if (beforeSaveEvent.isDefaultPrevented()) {
                let message = [[self.locales.save_error]];

                if (beforeSaveEvent.errors.length > 0) {
                    message = beforeSaveEvent.errors;
                }

                self.shippingHelper.message('error', message);
                return false;
            }
            return result;
        },

        shipping_data: {
            'null': null
        },

        /**
         * Disable section event handlers
         */
        shippingBlur: function () {
            const $dialog = $('#s-settings-shipping-type-dialog');
            $dialog.off('click', 'a.js-action');
            $dialog.remove();
            $('#s-settings-content').off('click', 'a.js-action');
            $('#s-settings-shipping').off('change, click');
        },

        /**
         *
         * @param {String} tail
         */
        shippingAction: function (tail) {
            const method = $.shop.getMethod(tail.split('/'), this, 'shipping');

            $.shop.trace('$.settings.shippingAction', [method, this.path, tail]);

            if (method.name) {
                this[method.name].apply(this, method.params);
            } else {
                const $content = $('#s-settings-content');

                $content.find('#s-settings-shipping-params, #s-settings-shipping-rounding,' +
                              '#s-shipping-menu, #s-settings-shipping, #s-settings-shipping-cron,' +
                              '#shipping-methods-title').show();

                $content.find('#s-settings-shipping-params div.js-fields-group').slideUp();
                this.$shipping_plugin_container.html(this.shipping_options.loading).hide();
                $content.find('h1.js-bread-crumbs:not(:first)').remove();
                $content.find('h1:first').show();
                if (this.$shipping_plugins_container.is(':empty')) {
                    this.loadInstallerPlugins();
                } else {
                    this.$shipping_plugins_container.show();
                }
            }
        },

        shippingSort: function (id, after_id, callRevert) {
            $.post('?module=settings&action=shippingSort', {
                'module_id': id,
                'after_id': after_id
            }, function (response) {
                $.shop.trace('$.settings.shippingSort result', response);

                if (response.error) {
                    $.shop.error('Error occurred while sorting shipping plugins', 'error');
                    callRevert();
                } else if (response.status !== 'ok') {
                    $.shop.error('Error occurred while sorting shipping plugins', response.errors);
                    callRevert();
                }
            }, 'json').fail(function (response) {
                $.shop.trace('$.settings.shippingSort cancel', [response]);
                callRevert();
                $.shop.error('Error occurred while sorting shipping plugins', 'error');
                return false;
            });
        },

        shippingParams: function () {
            const $content = $('#s-settings-content #s-settings-shipping-params');

            $content.find('div.js-fields-group').each(function (index, elem) {
                const $elem = $(elem);

                if ($elem.is(':visible')) {
                    $elem.slideUp();
                } else {
                    $elem.slideDown();
                }
            });
        },

        /**
         * @param {jQuery} $form
         */
        shippingParamsSave: function ($form) {
            const self = this;

            const url = '?module=settings&action=shippingSave';

            self.shippingHelper.message('submit', null, $form);

            const $submit = $form.find(':submit').prop('disabled', true);

            $.ajax({
                type: 'POST',
                url: url,
                data: $form.serialize(),
                dataType: 'json',
                success: function (data, textStatus, jqXHR) {
                    if (data && (data.status !== 'ok')) {
                        self.shippingHelper.message('error', data.errors || [], $form);
                        return;
                    }

                    let message = 'Saved';

                    if (data.data && data.data.message) {
                        message = data.data.message;
                    }
                    if (data.data.params) {
                        const params = data.data.params;

                        for (let param in params) {
                            $('.js-shipping-' + param).each(
                                function () {
                                    const $param = $(this);

                                    if ($param.data('state') == params[param]) {
                                        $param.show();
                                    } else {
                                        $param.hide();
                                    }
                                }
                            )
                        }
                    }

                    self.shippingHelper.message('success', message, $form);
                    setTimeout(function () {
                        $('#s-settings-content #s-settings-shipping-params div.js-fields-group').slideUp();
                    }, 1000);
                },
                fail: function (jqXHR, errorText) {
                    self.shippingHelper.message(
                        'error',
                        [[errorText]],
                        $form
                    );

                },
                complete: function () {
                  $submit.prop('disabled', false);
                  $form.find(".js-submit-button").removeClass('yellow').addClass('green');
                }
            });
            return false;
        },

        shippingPluginAdd: function (plugin_id, $el) {
            const self = this;
            this.shippingPluginShow(plugin_id, function () {
                const $plugin_name = self.$shipping_plugin_container.find('.fields-group:first h1.js-bread-crumbs:first');
                const $title = $('#s-settings-content').find('h1.js-bread-crumbs:first');
                $title.hide();
                $title.after($plugin_name);
            });
        },

        /**
         * Show plugin setup options
         *
         * @param {String} plugin_id
         * @param {jQuery} $el
         */
        shippingPluginSetup: function (plugin_id, $el) {
            const self = this;
            this.shippingPluginShow(plugin_id, function () {
                const $plugin_name = self.$shipping_plugin_container.find('.fields-group:first h1.js-bread-crumbs:first');
                const $title = $('#s-settings-content').find('h1.js-bread-crumbs:first');
                $title.after($plugin_name);
                $title.hide();
                if ($el === 'enable') {
                    self.$shipping_plugin_container.find('[name="shipping[status]"]').prop('checked', true);
                }
            });

        },

        shippingPluginShow: function (plugin_id, callback) {
            this.$shipping_plugins_container.hide();

            const $content = $('#s-settings-content');
            $content.find('#s-shipping-menu, #s-settings-shipping-params, #s-settings-shipping-rounding,' +
                          '#s-settings-shipping-cron, #shipping-methods-title').hide();

            const $plugins = $content.find('#s-settings-shipping');
            $plugins.hide();

            const self = this;
            const url = '?module=settings&action=shippingSetup&plugin_id=' + plugin_id;
            this.$shipping_plugin_container.show().html(this.shipping_options.loading).load(url, function () {
                if (typeof (callback) == 'function') {
                    callback();
                }
                self.formChanged($(this).find('form:first'));
            });
        },

        shippingPluginClone: function () {
            const that = this;
            const $plugin_list = $('#s-settings-shipping').find('table');

            $plugin_list.on('click', '.js-shipping-plugin-clone', function (e) {
                e.preventDefault();
                const $self = $(this);
                const $tr = $self.closest('tr');
                const original_id = $tr.data('id');

                $.post('?module=settings&action=systemPluginClone', {original_id: original_id, type: 'shipping'}).done(function (r) {
                    if (!r && !r.data && !r.data.plugin_id) {
                        return;
                    }

                    const id = r.data.plugin_id;
                    const $new_plugin = $tr.clone().attr('data-id', id);
                    const $title = $new_plugin.find('.js-plugin-title');
                    const is_off = $title.hasClass('gray');
                    const $setup = $new_plugin.find('.js-shipping-plugin-setup');
                    const $delete = $new_plugin.find('.js-shipping-plugin-delete');

                    //if plugin now off not need add text
                    if (!is_off) {
                        $title.addClass('gray').text($title.text() + '(' + that.locales['disabled'] + ')');
                    }

                    //change id in url
                    $setup.attr('href', '#/shipping/plugin/setup/' + id + '/');
                    $delete.attr('href', '#/shipping/plugin/delete/' + id + '/');

                    //add new node
                    $plugin_list.append($new_plugin);
                    $new_plugin[0].scrollIntoView({
                        behavior: "smooth"
                    });

                    that.updateDropdownSecondaryActions();
                });
            })
        },

        /**
         * @param {jQuery} $form
         */
        shippingPluginSave: function ($form) {
            const self = this;
            const url = '?module=settings&action=shippingSave';

            self.shippingHelper.message('submit');
            const $submit = $form.find(':submit').prop('disabled', true);

            $.ajax({
                type: 'POST',
                url: url,
                data: $form.serialize(),
                dataType: 'json',
                success: function (data, textStatus, jqXHR) {
                    if (data && (data.status !== 'ok')) {
                        self.shippingHelper.message('error', data.errors || []);
                        return;
                    }

                    let message = 'Saved';

                    if (data.data && data.data.message) {
                        message = data.data.message;
                    }

                    self.shippingHelper.message('success', message);

                    setTimeout(function () {
                        self.dispatch('#/shipping/', true);
                    }, 500);
                },
                fail: function (jqXHR, errorText) {
                    self.shippingHelper.message('error', [
                        [errorText]
                    ]);
                },
                always: function () {
                    $submit.prop('disabled', false);
                }
            });

            return false;
        },

        shippingPluginDelete: function (plugin_id) {
            const url = '?module=settings&action=shippingDelete';
            const self = this;

            $.post(url, {
                'plugin_id': plugin_id
            }, function (data, textStatus, jqXHR) {
                console.log(self)
                self.dispatch('#/shipping/', true);
            });

        },

        shippingControlOptionAdd: function ($el) {
            let parent_selector;
            let container_selector;

            let $parent;
            let $container;

            if (container_selector = $el.data('container')) {
                if (($container = $el.parents(container_selector)) && $container.length) {
                    if (parent_selector = $el.data('parent')) {
                        if (($parent = $container.find(parent_selector + ':last')) && $parent.length) {
                            const $added = $parent.clone(false);

                            $added.find(':input').each(function (i, input) {
                                const type = $(this).attr('type');
                                const name = $(this).attr('name');
                                let matches;

                                if (matches = name.match(/\[(\d+)\]/)) {
                                    const id = parseInt(matches[1], 10) + 1;
                                    $(this).attr('name', name.replace(/\[(\d+)\]/, '[' + id + ']'));
                                }

                                if ((type != 'text') && (type != 'textarea')) {
                                    return true;
                                }

                                this.value = this.defaultValue;
                            });

                            $.shop.trace('add', $added);
                            $parent.after($added);
                        }
                    }
                }
            }
        },

        shippingControlOptionRemove: function ($el) {
            const parent_selector = $el.data('parent');

            if (parent_selector) {
                const $parent = $el.parents(parent_selector);

                if ($parent.parent().find(parent_selector).length > 1) {
                    if ($parent.length) {
                        $parent.remove();
                    }
                } else {
                    if ($parent.length) {
                        $parent.find('input').val(0);
                    }
                }
            }
        },

        shippingPlugins: function () {
            $('#s-settings-content').find('#s-settings-shipping').hide();
            this.loadInstallerPlugins();
        },

        shippingHelper: {
            parent: $.settings,
            timer: null,
            icon: {
                submit: '<span class="loading"><i class="fas fa-spinner fa-spin text-gray"></i></span>',
                success: '<span class="yes"><i class="fas fa-check-circle text-green"></i></span>',
                error: '<span class="no"><i class="fas fa-ban text-red"></i></span>'
            },
          message: function (status, message, $container) {
            /* enable previous disabled inputs */
            $container = $container ? $container.find('.js-form-status:first') : $('#settings-shipping-form-status');
            $container.empty().show();
            const $parent = $container.parents('div.value');
            $parent.removeClass('state-error status');

            if (this.timer) {
              clearTimeout(this.timer);
            }

            let timeout = null;

            $container.append('<i class="custom-mr-4">'+this.icon[status]+'</i>' || '');

            switch (status) {
              case 'submit':
                $parent.addClass('status');
                break;
              case 'error':
                $parent.addClass('state-error');
                for (var i = 0; i < message.length; i++) {
                  $container.append(message[i][0]);
                }
                timeout = 20000;
                break;
              case 'success':
                if (message) {
                  $container.append(message);
                }
                timeout = 3000;
                break;
            }

            if (timeout) {
                this.timer = setTimeout(function () {
                    $parent.removeClass('state-error status');
                    $container.empty().show();
                }, timeout);
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

            $submit.one('click', function() {
                $form.off('change input');
                $submit.removeClass('yellow').addClass('green');
            });
        },

        loadInstallerPlugins: function () {
            if (!$.settings.options.installer_access) {
                return;
            }
            const url = this.options.backend_url + 'installer/?module=plugins&ui=2.0&action=view&slug=wa-plugins/shipping&return_hash=/shipping/plugin/add/%plugin_id%/';
            $.get(url, (html) => {
                this.$shipping_plugins_container.show().html(html);
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
