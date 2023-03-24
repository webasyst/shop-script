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
            loading: $_('Loading') + '...<i class="icon16 loading"><i>'
        },

        locales: null,
        /**
         * Init section
         *
         */
        shippingInit: function (options) {
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
                axis: 'y',
                tolerance: 'pointer',
                update: function (event, ui) {
                    var $item = $(ui.item);
                    var id = parseInt($item.data('id'), 10);
                    var after_id = $item.prev().data('id');
                    if (after_id === undefined) {
                        after_id = 0;
                    } else {
                        after_id = parseInt(after_id, 10);
                    }
                    self.shippingSort(id, after_id, $(this));
                }
            });

            $('#s-settings-shipping-setup').on('submit', 'form', function () {
                var $this = $(this);
                if ($this.hasClass('js-installer')) {
                    return (!$this.hasClass('js-confirm') || confirm($this.data('confirm-text') || $this.attr('title') || $_('Are you sure?')));
                } else {
                    var event = self.shippingPluginSaveEvent($this);

                    if (event) {
                        return self.shippingPluginSave($this);
                    } else {
                        return false;
                    }
                }
            });

            $('#s-settings-shipping-params').on('submit', 'form', function () {
                var $this = $(this);
                return self.shippingParamsSave($this);
            });

            self.locales = options.locales;
            //init clone plugin
            self.shippingPluginClone();

            self.shippingResizeSetupList();
        },

        shippingPluginSaveEvent: function ($form) {
            var self = this,
                result = true,
                beforeSaveEvent = new $.Event('shop_save_shipping');

            beforeSaveEvent.errors = [];
            $form.trigger(beforeSaveEvent);

            if (beforeSaveEvent.isDefaultPrevented()) {
                var message = [[self.locales.save_error]];

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
            var $dialog = $('#s-settings-shipping-type-dialog');
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
            var method = $.shop.getMethod(tail.split('/'), this, 'shipping');
            $.shop.trace('$.settings.shippingAction', [method, this.path, tail]);
            if (method.name) {
                this[method.name].apply(this, method.params);
            } else {
                var $content = $('#s-settings-content');
                $content.find('#s-settings-shipping-params, #s-settings-shipping-rounding,' +
                              '#s-shipping-menu, #s-settings-shipping, #s-settings-shipping-cron,' +
                              '#shipping-methods-title').show();


                $content.find('#s-settings-shipping #s-settings-shipping-params div.field-group').slideUp();
                $('#s-settings-shipping-setup').html(this.shipping_options.loading).hide();
                $content.find('h1.js-bread-crumbs:not(:first)').remove();
                $content.find('h1:first').show();
            }
        },

        shippingSort: function (id, after_id, list) {
            $.post('?module=settings&action=shippingSort', {
                'module_id': id,
                'after_id': after_id
            }, function (response) {
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

        shippingParams: function () {
            var $content = $('#s-settings-content #s-settings-shipping-params');
            $content.find('div.field-group').each(function (index, elem) {
                var $elem = $(elem);
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
            var self = this;
            var url = '?module=settings&action=shippingSave';
            self.shippingHelper.message('submit', null, $form);
            var $submit = $form.find(':submit').prop('disabled', true);
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
                        if (data.data.params) {
                            var params = data.data.params;
                            for (var param in params) {
                                $('.js-shipping-' + param).each(
                                    function () {
                                        var $param = $(this);
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
                            $('#s-settings-content #s-settings-shipping-params div.field-group').slideUp();
                        }, 1000);
                    } else {
                        self.shippingHelper.message('error', data.errors || [], $form);
                    }
                },
                error: function (jqXHR, errorText) {
                    self.shippingHelper.message(
                        'error',
                        [[errorText]],
                        $form
                    );

                },
                complete: function () {
                    $submit.prop('disabled', false);
                }
            });
            return false;
        },

        shippingPluginAdd: function (plugin_id, $el) {
            $.wa.dropdownsClose();
            this.shippingPluginShow(plugin_id, function () {
                var $title = $('#s-settings-content').find('h1.js-bread-crumbs:first');
                $title.hide();
                var $plugin_name = $('#s-settings-shipping-setup').find('.field-group:first h1.js-bread-crumbs:first');
                $title.after($plugin_name);
                $title.hide();
            });
        },

        /**
         * Show plugin setup options
         *
         * @param {String} plugin_id
         * @param {jQuery} $el
         */
        shippingPluginSetup: function (plugin_id, $el) {
            this.shippingPluginShow(plugin_id, function () {
                var $title = $('#s-settings-content').find('h1.js-bread-crumbs:first');
                var $plugin_name = $('#s-settings-shipping-setup').find('.field-group:first h1.js-bread-crumbs:first');
                $title.after($plugin_name);
                $title.hide();
            });

        },

        shippingPluginShow: function (plugin_id, callback) {
            var $content = $('#s-settings-content'),
                that = this;
            $content.find('#s-shipping-menu, #s-settings-shipping-params, #s-settings-shipping-rounding,' +
                          '#s-settings-shipping-cron, #shipping-methods-title').hide();

            var $plugins = $content.find('#s-settings-shipping');
            $plugins.hide();
            var url = '?module=settings&action=shippingSetup&plugin_id=' + plugin_id;
            $('#s-settings-shipping-setup').show().html(this.shipping_options.loading).load(url, function () {
                if (typeof (callback) == 'function') {
                    callback();
                    that.initElasticFooter();
                }
            });
        },

        shippingPluginClone: function () {
            var that = this,
                $plugin_list = $('#s-settings-shipping');

            $plugin_list.on('click', '.js-shipping-plugin-clone', function (e) {
                e.preventDefault();
                var $self = $(this),
                    $tr = $self.closest('tr'),
                    original_id = $tr.data('id');

                $.post('?module=settings&action=systemPluginClone', {original_id: original_id, type: 'shipping'}).success(function (r) {
                    if (r && r.data && r.data.plugin_id) {
                        var id = r.data.plugin_id,
                            $new_plugin = $tr.clone().attr('data-id', id),
                            $title = $new_plugin.find('.js-plugin-title'),
                            is_off = $title.hasClass('gray'),
                            $setup = $new_plugin.find('.js-shipping-plugin-setup'),
                            $delete = $new_plugin.find('.js-shipping-plugin-delete');

                        //if plugin now off not need add text
                        if (!is_off) {
                            $title.addClass('gray').text($title.text() + '(' + that.locales['disabled'] + ')');
                        }

                        //change id in url
                        $setup.attr('href', '#/shipping/plugin/setup/' + id + '/');
                        $delete.attr('href', '#/shipping/plugin/delete/' + id + '/');

                        //add new node
                        $plugin_list.append($new_plugin);
                    }
                });
            })
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
                            .css("left", header_o.left - 20) // Because parents are doing padding 20
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

        /**
         * @param {jQuery} $form
         */
        shippingPluginSave: function ($form) {
            var self = this;
            var url = '?module=settings&action=shippingSave';
            self.shippingHelper.message('submit');
            var $submit = $form.find(':submit').prop('disabled', true);
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
                },
                complete: function () {
                    $submit.prop('disabled', false);
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
                                var matches;
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
            $('#s-settings-content').find('#s-settings-shipping').hide();
            var url = this.options.backend_url + 'installer/?module=plugins&action=view&slug=wa-plugins/shipping&return_hash=/shipping/plugin/add/%plugin_id%/';
            $('#s-settings-shipping-setup').show().html(this.shipping_options.loading).load(url);
        },

        /**
         * Needed to ensure that the list of delivery services is always placed on the screen.
         */
        shippingResizeSetupList: function () {
            $('.s-add-shipping-method').on('hover', function () {
                var scrollHeight = Math.max(
                    document.documentElement.scrollHeight,
                    document.documentElement.offsetHeight,
                    document.documentElement.clientHeight
                ) - $('.s-add-shipping-method').offset().top - 35;
                $('.js-shipping-window-height').css('max-height', scrollHeight);
            })
        },

        shippingHelper: {
            parent: $.settings,
            timer: null,
            icon: {
                submit: '<i style="vertical-align:middle" class="icon16 loading"></i>',
                success: '<i style="vertical-align:middle" class="icon16 yes"></i>',
                error: '<i style="vertical-align:middle" class="icon16 no"></i>'
            },
            message: function (status, message, $container) {
                /* enable previos disabled inputs */

                $container = $container ? $container.find('.js-form-status:first') : $('#settings-shipping-form-status');
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
