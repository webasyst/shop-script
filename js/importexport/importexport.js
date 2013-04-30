$.extend($.importexport = $.importexport || {}, $.importexport = {
    options: {
        'loading': '<i class="icon16 loading"></i>',
        'path': '#/'
    },
    hash: null,
    path: {
        'plugin': null,
        'module': null,
        'direction': null,
        'prefix': null,
        'action': 'default',
        'tail': null,
        'dispatch': null
    },
    plugins: {},

    ready: false,
    menu: null,

    /**
     * @param {Object} options
     */
    init: function(options) {
        this.options = $.extend(this.options, options || {});
        if (!this.ready) {
            this.ready = true;
            this.menu = $('#s-importexport-menu');

            // Set up AJAX to never use cache
            $.ajaxSetup({
                cache: false
            });

            if (typeof ($.History) != "undefined") {
                $.History.bind(function() {
                    $.importexport.dispatch();
                });
            }
            $.wa.errorHandler = function(xhr) {
                if ((xhr.status === 403) || (xhr.status === 404)) {
                    var text = $(xhr.responseText);
                    var message = $('<div class="block double-padded"></div>');
                    if (text.find('.dialog-content').length) {
                        text = message.append(text.find('.dialog-content *'));
                    } else {
                        text = message.append(text.find(':not(style)'));
                    }
                    $("#s-importexport-content").empty().append(message).append('<div class="clear-both"></div>');
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
        }
    },

    /**
     * @param {String} path
     * @return { 'plugin':String, 'module':String, 'prefix':String,
     *         'direction':String, 'action':String, 'tail':String,'raw':String }
     */
    parsePath: function(path) {
        path = (path || '').replace(/^.*#\//, '');
        var parsed = {
            'plugin': path.replace(/\/.*$/, '') || '',
            'module': 'backend',
            'prefix': null,
            'direction': null,
            'action': path.replace(/^[^\/]+\//, '').replace(/\/.*$/, '') || '',
            'tail': path.replace(/^([^\/]+\/){1,2}/, '').replace(/\/$/, ''),
            'raw': path
        };
        var matches = parsed.plugin.match(/(^[^:]+):([^:]+)(:(.+))?$/);
        if (matches && matches[2]) {
            parsed.plugin = null;
            parsed.module = matches[1];
            parsed.prefix = matches[2];

            parsed.direction = matches[4] ? matches[4] : null;
        }
        $.shop.trace('$.importexport.parsePath',parsed);
        return parsed;
    },

    /**
     * Dispatch location hash changes
     *
     * @param {String} hash
     * @param {Boolean} load Force reload if need
     * @return {Boolean}
     */
    dispatch: function(hash, load) {
        if (hash === undefined) {
            hash = window.location.hash;
        }
        if (!hash) {
            var $plugin = this.menu.find('li:first > a:first');
            if ($plugin.length) {
                window.location.hash = hash = $plugin.attr('href');
            }
        }

        // hard reload page because of the tricky confict of several blueimpimageupload plugin
        if (this.hash && this.hash != hash && hash == '#/images:product/') {
            location.reload(location.hash);
        }

        // hard reload page because of the tricky confict of several blueimpimageupload plugin
        if (this.hash && this.hash != hash && hash == '#/csv:product/') {
            location.reload(location.hash);
        }

        this.hash = hash;

        if (load) {
            window.location.hash = hash;
        }
        var path = this.parsePath(hash.replace(/^[^#]*#\/*/, ''));
        this.path.dispatch = path;

        /* change importexport plugin */
        if (path.plugin) {
            load = load || (path.plugin != this.path.plugin);
        } else if (path.module && (path.module != 'backend')) {
            load = load || ((path.plugin + path.module) != (this.path.plugin + this.path.module)) || (path.direction != this.path.direction);
        }

        if (load) {
            var $content = $('#s-importexport-content');
            this.importexportBlur(this.path.plugin);
            $.shop && $.shop.trace('$.importexport.dispatch (default) ' + this.path.plugin + ' -> ' + path.plugin);
            this.path.tail = null;
            if (!load) {
                $content.empty().html(this.options.loading);
            }

            var url = new Array();
            if (path.plugin) {
                url.push('plugin=' + path.plugin);
            }
            if (path.module && (path.module != 'backend')) {
                url.push('module=' + path.module);
            }
            if (path.direction) {
                url.push('direction=' + path.direction);
            }
            url.push('action=' + (path.prefix || '') + 'setup');

            var self = this;
            $content.load('?' + url.join('&'), function() {
                self.path.plugin = path.plugin;
                self.path.module = path.module;
                self.path.direction = path.direction;
                self.path.prefix = path.prefix;

                // update title
                window.document.title = self.options.plugin_names[self.path.plugin] || $content.find('h1:first').text()
                        + self.options.title_suffix;
                self.menu.find('li.selected').removeClass('selected');
                var href = self.path.plugin ? self.path.plugin : (self.path.module + ':' + self.path.prefix);
                if(self.path.direction) {
                    href += ':'+self.path.direction;
                }
                self.menu.find('a[href*="\\#\/' + href + '\/"]').parents('li').addClass('selected');
                self.importexportAction(path.action, path.tail);
            });

            return true;
        } else if (!path.plugin) {
            // do nothing
        }
        /* update importexport plugin */
        else {
            this.importexportAction(path.action, path.tail);
        }
    },

    /**
     * Setup plugin options
     *
     * @param {Object} options
     */
    importexportOptions: function(options) {
        var plugin = this.path.dispatch.plugin || this.path.plugin;
        $.shop && $.shop.trace('$.importexport.importexportOptions', plugin);
        if (typeof (this[plugin + '_options']) == 'undefined') {
            this[plugin + '_options'] = {};
        }
        this[plugin + '_options'] = $.extend(this[plugin + '_options'], options || {});
    },

    /**
     * Handler call focus out plugin
     *
     * @param {String} plugin
     */
    importexportBlur: function(plugin) {
        this.menu.find('li.selected').removeClass('selected');
        $.shop && $.shop.trace('$.importexport.importexportBlur');
        if (this.path.plugin || this.path.module) {
            this.call('Blur', []);
        } else {
            this.options.loading = $('#s-importexport-content').html() || this.options.loading;
        }
    },

    /**
     * handle current plugin actions after HTML has beed loaded
     *
     * @param {String} plugin
     * @param {String} tail
     */
    importexportAction: function(action, tail) {
        var method = '';

        if (action) {
            method += action.substr(0, 1).toUpperCase() + action.substr(1);
        }
        method += 'Action';
        $.shop && $.shop.trace('$.importexport.importexportAction', [action, tail]);
        this.call(method, [tail]);
        this.path.tail = tail;
    },



    /**
     * @param {String} name
     * @param Array args
     */
    call: function(name, args) {
        var plugin;
        if (this.path.plugin) {
            plugin = this.path.plugin;
        } else {
            plugin = this.path.module + '_' + this.path.prefix;
        }
        var action = plugin + name.substr(0, 1).toUpperCase() + name.substr(1);
        action = action.replace(/^(\d)/,'a$1');
        var callable = (typeof (this[action]) == 'function');
        var start = $.shop && $.shop.trace('$.importexport.call', [action, args, callable]);
        var result = null;
        if (callable) {
            try {
                result = this[action].apply(this, args);
            } catch (e) {
                (($.shop && $.shop.error) || console.log)
                        ('Exception at $.importexport.' + action + ': ' + e.message, e);
            }
            $.shop
                    && $.shop.trace('$.settings.call complete', [action, $.shop.time.interval(start) + 'ms', args,
                            result]);
        } else {
            action = name.substr(0, 1).toUpperCase() + name.substr(1);
            action = action.replace(/^(\d)/,'a$1');
            callable = (typeof (this.plugins[action]) == 'function');
            start = $.shop && $.shop.trace('$.importexport.call', [plugin, action, args, callable]);
            if (callable) {
                try {
                    result = this.plugins[action].apply(this, args);
                } catch (e) {
                    (($.shop && $.shop.error) || console.log)('Exception at $.importexport.' + action + ': '
                            + e.message, e);
                }
                $.shop
                        && $.shop.trace('$.settings.call complete', [action, $.shop.time.interval(start) + 'ms', args,
                                result]);
            }
        }
        return result;
    },
    getPlugin: function() {

    }
});
