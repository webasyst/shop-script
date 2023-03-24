/**
 *
 */
(function ($) {
    $.plugins = {
        options: {
            loading: '<i class="icon16 loading"></i>',
            path: '#/',
            useIframeTransport: false
        },
        path: {
            plugin: false,
            tail: null,
            params: {}
        },
        icon: {
            submit: '<i style="vertical-align:middle" class="icon16 loading"></i>',
            success: '<i style="vertical-align:middle" class="icon16 yes"></i>',
            error: '<i style="vertical-align:middle" class="icon16 no"></i>'
        },

        ready: false,
        $menu: null,
        timer: null,

        /**
         * @param options {object}
         */
        init: function (options) {
            this.options = $.extend(this.options, options || {});
            if (!this.ready) {
                this.ready = true;
                this.$menu = $('#plugin-list');

                // Set up AJAX to never use cache
                $.ajaxSetup({
                    cache: false
                });

                if (typeof($.History) != "undefined") {
                    $.History.bind(function () {
                        $.plugins.dispatch();
                    });
                }
                $.wa.errorHandler = function (xhr) {
                    if ((xhr.status === 403) || (xhr.status === 404)) {
                        var text = $(xhr.responseText);
                        if (text.find('.dialog-content').length) {
                            text = $('<div class="block double-padded"></div>').append(text.find('.dialog-content *'));

                        } else {
                            text = $('<div class="block double-padded"></div>').append(text.find(':not(style)'));
                        }
                        $("#shop-plugins-content").empty().append(text);
                        return false;
                    }
                    return true;
                };
                var hash = window.location.hash;
                if (hash === '#/' || !hash) {
                    this.dispatch();
                } else {
                    $.wa.setHash(hash);
                }

                if (this.$menu.find('> li:not(#plugins-list) > a').length) {

                    this.$menu.sortable({
                        containment: 'parent',
                        distance: 5,
                        items: '> li:not(#plugins-list)',
                        tolerance: 'pointer',
                        update: $.plugins.sortHandler
                    });
                }
            }
        },

        /**
         *
         * @param {string} path
         * @return {}
         */
        parsePath: function (path) {
            path = path.replace(/^.*#\//, '');
            return {
                plugin: path.replace(/\/.*$/, '') || null,
                tail: path.replace(/^[^\/]+\//, '').replace(/[\w_\-]+=.*$/, '').replace(/\/$/, '') || null,
                raw: path
            };
        },

        /**
         * Dispatch location hash changes
         *
         * @param {String=} hash
         * @param {Boolean=} force Force reload if need
         * @return {void}
         */
        dispatch: function (hash, force) {
            var $plugin;
            // in specific plugin inline script set it flag to true for iframe form posting
            this.options.useIframeTransport = false;

            if (hash === undefined) {
                hash = window.location.hash;
            }
            if (!hash) {
                $plugin = this.$menu.find('li:first > a:first');
                if ($plugin.length) {
                    window.location.hash = hash = $plugin.attr('href');
                }
            }

            var path = this.parsePath(hash.replace(/^[^#]*#\/*/, ''));
            this.path.dispatch = path;
            var load = force || (path.plugin !== this.path.plugin);

            $.shop.trace('$.plugins.dispatch ' + this.path.plugin + ' -> ' + path.plugin + ' # ' + path.tail, load);

            /* change plugins section */
            if (load) {
                var $content = $('#s-plugins-content');
                this.path.tail = null;

                $plugin = $(path.plugin ? ("#plugin-" + path.plugin) : '#plugins-list');
                if ($plugin.length) {
                    var url = this.helper.getContentUrl($plugin, path);
                    if (url) {
                        var self = this;
                        $content.html(this.options.loading);
                        $.shop.trace('$.plugins.dispatch: Load URL', [url, $content.length]);
                        $content.load(url, function () {
                            self.path.plugin = path.plugin;

                            // update title
                            if (self.path.plugin) {
                                document.title = self.options.plugin_names[self.path.plugin] + self.options.title_suffix;
                            } else {
                                document.title = $_('Plugins') + self.options.title_suffix;
                            }

                            self.$menu.find('li.selected').removeClass('selected');
                            var href = '\\#\\/' + (self.path.plugin ? self.path.plugin + '\\/' : '');
                            self.$menu.find('a[href="' + href + '"]').parents('li').addClass('selected');

                            if (!self.options.useIframeTransport) {
                                $('#plugins-settings-form').submit(function () {
                                    self.saveHandlerAjax(this);
                                    return false;
                                });
                            } else {
                                $('#plugins-settings-form').submit(function () {
                                    self.saveHandlerIframe(this);
                                });
                            }

                        });
                    }
                }
            }
        },

        saveHandlerIframe: function (form) {
            var self = this;
            this.message('submit');
            $("#plugins-settings-iframe").one('load', function () {
                var r = null;
                try {
                    r = $.parseJSON($(this).contents().find('body').html());
                } catch (e) {
                }
                if (r && r.status == 'ok') {
                    var message = 'Saved';
                    if (r.data && r.data.message) {
                        message = r.data.message;
                    }
                    self.message('success', message);
                    $(self).trigger('success', [r]);
                    $(form).trigger('success', [r])
                } else {
                    self.message('error', r && r.errors || 'parsererror');
                    $(self).trigger('error', [r]);
                }
            });
        },

        saveHandlerAjax: function (form) {
            var self = this;
            this.message('submit');
            var $form = $(form);
            $.ajax({
                type: 'POST',
                url: $form.attr('action'),
                data: $form.serializeArray(),
                iframe: true,
                dataType: 'json',
                success: function (data, textStatus, jqXHR) {
                    if (data && (data.status == 'ok')) {
                        var message = 'Saved';
                        if (data.data && data.data.message) {
                            message = data.data.message;
                        }
                        self.message('success', message);
                        $(self).trigger('success', [data]);
                        $form.trigger('success', [data])
                    } else {
                        self.message('error', data.errors || []);
                        $(self).trigger('error', [data]);
                    }
                },
                error: function (jqXHR, errorText) {
                    self.message('error', [
                        [errorText]
                    ]);
                    $(self).trigger('error', [errorText]);
                }
            });
        },

        helper: {
            getContentUrl: function ($item, path) {

                var url = '';
                if ($item.data('url')) {
                    url = $item.data('url');
                } else if ($item.data('settings')) {
                    url = '?plugin=' + path.plugin + '&module=settings';
                } else if (path.plugin) {
                    url = '?module=plugins&id=' + path.plugin;
                }
                return url;
            }
        },

        message: function (status, message) {
            /* enable previos disabled inputs */

            var $container = $('#plugins-settings-form-status');
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
        },
        sortHandler: function (event, ui) {
            var self = this;
            $.ajax({
                type: 'POST',
                url: '?module=plugins&action=sort',
                data: {
                    slug: $(ui.item).attr('id').replace(/^plugin-/, ''),
                    pos: $(ui.item).index()
                },
                success: function (data, textStatus, jqXHR) {
                    if (!data || !data.status || (data.status != "ok") || !data.data || (data.data != "ok")) {
                        self.$menu.sortable('cancel');
                    }
                },
                error: function () {
                    self.$menu.sortable('cancel');
                }
            });
        }
    };

})(jQuery);
