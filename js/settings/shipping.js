/**
 * {literal}
 *
 * @names shipping*
 * @property {} shipping_options
 * @method shippingInit
 * @method shippingAction
 * @method shippingBlur
 * @todo flush unavailable hash (edit/delete/etc)
 */
if (typeof($) != 'undefined') {

    $.extend($.settings = $.settings || {}, {

        shipping_options: {
            null: null,
            loading: 'Loading...<i class="icon16 loading"><i>'
        },
        /**
         * Init section
         *
         * @param string tail
         */
        shippingInit: function () {
            $.shop.trace('$.settings.shippingInit');
            /* init settings */
            var self = this;
            $('#s-settings-content').on('click', 'a.js-action', function () {
                return self.click($(this));
            });

            $('#s-settings-shipping').sortable({
                distance: 5,
                opacity: 0.75,
                items: '> tbody > tr:visible',
                handle: '.sort',
                cursor: 'move',
                tolerance: 'pointer',
                update: function (event, ui) {
                    var id = parseInt($(ui.item).data('id'), 10);
                    var after_id = $(ui.item).prev().data('id');
                    if (after_id === undefined) {
                        after_id = 0;
                    } else {
                        after_id = parseInt(after_id, 10);
                    }
                    self.shippingSort(id, after_id, $(this));
                }
            }).find(':not:input').disableSelection();

            $('#s-settings-shipping-setup').on('submit', 'form', function () {
                return self.shippingPluginSave($(this));
            });

            var dropdownSetStyle = function (ul) {
                dropdownResetStyle(ul).show();
                var win = $(window);
                if (ul.height() + ul.offset().top > win.scrollTop() + win.height()) {
                    ul.css({
                        'height': Math.max(win.scrollTop() + win.height() - ul.offset().top - 10, 100),
                        'overflow-y': 'scroll'
                    });
                }
            };
            var dropdownResetStyle = function (ul) {
                return ul.css({
                    'height': '',
                    'overflow-y': ''
                });
            };

            $('#s-shipping-menu').mouseover(function () {
                dropdownSetStyle($(this).find('ul:first'));
            }).mouseout(function () {
                    dropdownResetStyle($(this).find('ul:first')).hide();
                });
        },

        shipping_data: {
            'null': null

        },

        /**
         * Disable section event handlers
         */
        shippingBlur: function () {
            $('#s-settings-shipping-type-dialog').off('click', 'a.js-action');
            $('#s-settings-shipping-type-dialog').remove();
            $('#s-settings-content').off('click', 'a.js-action');
            $('#s-settings-shipping').off('change, click');
        },

        /**
         *
         * @param {String} tail
         */
        shippingAction: function (tail) {
            var method = $.shop.getMethod(tail.split('/'), this, 'shipping');
            $.shop.trace('$.settings.shippingAction', [method, this.path, tail]);
            if (method.name) {
                this[method.name].apply(this, method.params);
            } else {
                $('#s-settings-content #s-shipping-menu').show();
                $('#s-settings-content #s-settings-shipping').show();
                $('#s-settings-shipping-setup').html(this.shipping_options.loading).hide();
                $('#s-settings-content h1.js-bread-crumbs:not(:first)').remove();
                $('#s-settings-content h1:first').show();
            }
        },

        shippingSort: function (id, after_id, list) {
            $.post('?module=settings&action=shippingSort', {
                'module_id': id,
                'after_id': after_id
            },function (response) {
                $.shop.trace('$.settings.shippingSort result', response);
                if (response.error) {
                    $.shop.error('Error occurred while sorting shipping plugins', 'error');
                    list.sortable('cancel');
                } else if (response.status != 'ok') {
                    $.shop.error('Error occurred while sorting shipping plugins', response.errors);
                    list.sortable('cancel');
                }
            }, 'json').error(function (response) {
                    $.shop.trace('$.settings.shippingSort cancel', [list, response]);
                    list.sortable('cancel');
                    $.shop.error('Error occurred while sorting shipping plugins', 'error');
                    return false;
                });
        },

        shippingPluginAdd: function (plugin_id, $el) {
            $.wa.dropdownsClose();
            this.shippingPluginShow(plugin_id, function () {
                var $title = $('#s-settings-content h1.js-bread-crumbs:first');
                $title.hide();
                var $plugin_name = $('#s-settings-shipping-setup .field-group:first h1.js-bread-crumbs:first');
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
        shippingPluginSetup: function (plugin_id, $el) {
            this.shippingPluginShow(plugin_id, function () {
                var $title = $('#s-settings-content h1.js-bread-crumbs:first');
                var $plugin_name = $('#s-settings-shipping-setup .field-group:first h1.js-bread-crumbs:first');
                $title.after($plugin_name);
                $title.hide();
            });

        },

        shippingPluginShow: function (plugin_id, callback) {
            $('#s-settings-content #s-shipping-menu').hide();
            var $plugins = $('#s-settings-content #s-settings-shipping');
            $plugins.hide();
            var url = '?module=settings&action=shippingSetup&plugin_id=' + plugin_id;
            $('#s-settings-shipping-setup').show().html(this.shipping_options.loading).load(url, function () {
                if (typeof(callback) == 'function') {
                    callback();
                }
            });
        },

        /**
         * @param {JQuery} $el
         */
        shippingPluginSave: function ($form) {
            var self = this;
            var url = '?module=settings&action=shippingSave';
            self.shippingHelper.message('submit');
            $.ajax({
                type: 'POST',
                url: url,
                data: $form.serialize(),
                dataType: 'json',
                success: function (data, textStatus, jqXHR) {
                    if (data && (data.status == 'ok')) {
                        var message = 'Saved';
                        if (data.data && data.data.message) {
                            message = data.data.message;
                        }
                        self.shippingHelper.message('success', message);
                        setTimeout(function () {
                            self.dispatch('#/shipping/', true);
                        }, 500);
                    } else {
                        self.shippingHelper.message('error', data.errors || []);
                    }
                },
                error: function (jqXHR, errorText) {
                    self.shippingHelper.message('error', [
                        [errorText]
                    ]);
                }
            });
            return false;
        },

        shippingPluginDelete: function (plugin_id) {
            var url = '?module=settings&action=shippingDelete';
            var self = this;
            $.post(url, {
                'plugin_id': plugin_id
            }, function (data, textStatus, jqXHR) {
                self.dispatch('#/shipping/', true);
            });

        },

        shippingControlOptionAdd: function ($el) {
            var parent_selector, container_selector;
            var $parent, $container;
            if (container_selector = $el.data('container')) {

                if (($container = $el.parents(container_selector)) && $container.length) {
                    if (parent_selector = $el.data('parent')) {

                        if (($parent = $container.find(parent_selector + ':last')) && $parent.length) {
                            var $added = $parent.clone(false);
                            $added.find(':input').each(function (i, input) {
                                var type = $(this).attr('type');
                                var name = $(this).attr('name');
                                if (matches = name.match(/\[(\d+)\]/)) {
                                    var id = parseInt(matches[1], 10) + 1;
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
            var parent_selector = $el.data('parent');
            if (parent_selector) {
                var $parent = $el.parents(parent_selector);
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
            $('#s-settings-content #s-settings-shipping').hide();
            var url = this.options.backend_url+'installer/?module=plugins&action=view&slug=wa-plugins/shipping';
            $('#s-settings-shipping-setup').show().html(this.shipping_options.loading).load(url);
        },

        shippingHelper: {
            parent: $.settings,
            timer: null,
            icon: {
                submit: '<i style="vertical-align:middle" class="icon16 loading"></i>',
                success: '<i style="vertical-align:middle" class="icon16 yes"></i>',
                error: '<i style="vertical-align:middle" class="icon16 no"></i>'
            },
            message: function (status, message) {
                /* enable previos disabled inputs */

                var $container = $('#settings-shipping-form-status');
                $container.empty().show();
                var $parent = $container.parents('div.value');
                $parent.removeClass('errormsg successmsg status');

                if (this.timer) {
                    clearTimeout(this.timer);
                }
                var timeout = null;
                $container.append(this.icon[status] || '');
                switch (status) {
                    case 'submit':
                        $parent.addClass('status');
                        break;
                    case 'error':
                        $parent.addClass('errormsg');
                        for (var i = 0; i < message.length; i++) {
                            $container.append(message[i][0]);
                        }
                        timeout = 20000;
                        break;
                    case 'success':
                        if (message) {
                            $parent.addClass('successmsg');
                            $container.append(message);
                        }
                        timeout = 3000;
                        break;
                }
                if (timeout) {
                    this.timer = setTimeout(function () {
                        $parent.removeClass('errormsg successmsg status');
                        $container.empty().show();
                    }, timeout);
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
