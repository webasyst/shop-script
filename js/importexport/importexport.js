$.extend($.importexport = $.importexport || {}, $.importexport = {
    options: {
        loading: '<i class="icon16 loading"></i>',
        path: '#/',
        plugin_names: {}, /**
         * @type {Array} plugin_id=>bool
         */
        plugin_profiles: {},
        title_suffix: '',
        backend_url: '/webasyst/'
    },
    /**
     * @type {string}
     */
    hash: null,
    /**
     * @type {{plugin: string,module: string, prefix: string, direction: string,profile:number, action: string, tail: string, raw: string,dispatch:{}}}
     */
    path: {
        plugin: null,
        module: null,
        direction: null,
        profile: null,
        prefix: null,
        action: 'default',
        tail: null,
        dispatch: null
    },
    plugins: {},


    /**
     * @type {boolean}
     */
    ready: false,

    /**
     *
     * @type {*|jQuery|HTMLElement}
     */
    $menu: null,

    /**
     *
     * @type {*|jQuery|HTMLElement}
     */
    $content: null,
    /**
     *
     * @type {*|jQuery|HTMLElement}
     */
    $header: null,
    /**
     *
     * @type {*|jQuery|HTMLElement}
     */
    $profile: null,

    /**
     * @param {Object} options
     */
    init: function (options) {
        this.options = $.extend(this.options, options || {});
        if (!this.ready) {
            this.ready = true;
            this.$menu = $('#s-importexport-menu');
            this.$header = $('#s-importexport-header');
            this.$profile = $('#s-importexport-profile');

            // Set up AJAX to never use cache
            $.ajaxSetup({
                cache: false
            });

            if (typeof ($.History) != "undefined") {
                $.History.bind(function () {
                    $.importexport.dispatch();
                });
            }

            //setup error handler for ajax
            $.wa.errorHandler = function (xhr) {
                if ((xhr.status === 403) || (xhr.status === 404)) {
                    var text = $(xhr.responseText);
                    var $message = $('<div class="block double-padded"></div>');
                    if (text.find('.dialog-content').length) {
                        $message.append(text.find('.dialog-content *'));
                    } else {
                        $message.append(text.find(':not(style)'));
                    }
                    $("#s-importexport-content").empty().append($message).append('<div class="clear-both"></div>');
                    return false;
                }
                return true;
            };
            var self = this;
            this.$profile.on('click', 'a.js-action', function () {
                return self.click($(this));
            });
            var hash = window.location.hash;
            if (hash === '#/' || !hash) {
                this.dispatch();
            } else {
                $.wa.setHash(hash);
            }
        }
    },

    /**
     *
     * @param {string} path
     * @returns {{plugin: string, module: string, prefix: string, direction: string, action: string, tail: string, raw: string}}
     */
    parsePath: function (path) {
        path = (path || '').replace(/^.*#\//, '');
        var parsed = {
            plugin: path.replace(/\/.*$/, '') || '',
            module: 'backend',
            prefix: null,
            direction: null,
            profile: null,
            action: path.replace(/^[^\/]+\//, '').replace(/\/.*$/, '') || '',
            tail: path.replace(/^([^\/]+\/){1,2}/, '').replace(/\/$/, ''),
            raw: path
        };

        /**
         *
         * @type {Array}
         */
        var matches = parsed.plugin.split(':');
        if ((matches.length > 1) && (('' + matches[matches.length - 1]).match(/^-?\d*$/))) {
            parsed.profile = parseInt(matches.pop());
        }

        if (matches.length > 1) {
            parsed.plugin = null;
            parsed.module = matches[0];
            parsed.prefix = matches[1];
            parsed.direction = matches[2] ? matches[2] : null;
        } else {
            $.shop.trace('plugin', [matches.length, matches[0]]);
            parsed.plugin = matches[0];
        }
        $.shop.trace('$.importexport.parsePath', parsed);

        return parsed;
    },

    /**
     * Dispatch location hash changes
     *
     * @param {String=} hash
     * @param {Boolean=false} load Force reload if need
     */
    dispatch: function (hash, load) {
        if (hash === undefined) {
            hash = window.location.hash;
        }
        if (!hash) {
            var $plugin = this.$menu.find('li:first > a:first');
            if ($plugin.length) {
                window.location.hash = hash = $plugin.attr('href');
            }
        }

        // hard reload page because of the tricky conflict of several blueimp fileupload plugin
        if ((typeof $('').fileupload == 'function') &&
            this.hash && (this.hash != hash) && ((hash == '#/images:product/') || (hash == '#/csv:product/'))) {
            location.reload(true);
        }

        this.hash = hash;

        if (load) {
            window.location.hash = hash;
        }
        var path = this.parsePath(hash.replace(/^[^#]*#\/*/, ''));
        this.path.dispatch = path;

        /* change active plugin */
        if (path.plugin) {
            load = load
            || (path.plugin != this.path.plugin)
            || ((path.profile != this.path.profile) && (path.profile > 0))
            ;
        } else if (path.module && (path.module != 'backend')) {
            load = load
            || ((path.plugin + path.module) != (this.path.plugin + this.path.module))
            || (path.direction != this.path.direction)
            || ((path.profile != this.path.profile) && (path.profile > 0))
            ;
        }

        if (load) {
            var plugin = (path.plugin ? [path.plugin, path.prefix, path.direction] : [path.module, path.prefix, path.direction]).filter(function (v) {
                return v && (v.length > 0);
            }).join(':');
            if (!path.profile && this.options.plugin_profiles[plugin] && (path.action == 'hash') && path.tail) {

                this.profileAdd(plugin, path.action + '/' + path.tail);
            } else {
                var $content = $('#s-importexport-content');
                if ((path.plugin != this.path.plugin ) ||
                    (path.module != this.path.module ) ||
                    (path.direction != this.path.direction )
                ) {
                    this.$header.hide();
                    this.$profile.hide();
                }

                this.importexportBlur(this.path.plugin, plugin);
                $.shop && $.shop.trace('$.importexport.dispatch (default) ' + this.path.plugin + ' -> ' + path.plugin);
                this.path.tail = null;
                if (!load) {
                    $content.empty().html(this.options.loading);
                }

                var self = this;
                $content.load(this.helper.buildUrl(path), function (responseText, textStatus, XMLHttpRequest) {
                    if (!$content.find('>div.block.double-padded').length) {
                        $content.wrapInner('<div class="block double-padded"></div>');
                    }
                    self.importexportLoad($content, path);
                });
            }

        } else if (!path.plugin) {
            // do nothing
        } else {/* update active plugin */
            this.importexportAction(path.action, path.tail);
        }
    },

    /**
     * Handle js section interactions
     *
     * @param {jQuery} $el
     * @return {Boolean}
     */
    click: function ($el) {
        try {
            var args = $el.attr('href').replace(/.*#\/?/, '').replace(/\/$/, '').split('/');
            var name = args.shift();
            //TODO determine scope for plugins
            var matches;
            scope = this;
            if (matches = name.match(/^(\w+(:\w+)?)(:\d+)?$/)) {
                if (this.plugins[matches[1]]) {
                    scope = this.plugins[matches[1]];
                    name = null;
                }
            }

            var method = $.shop.getMethod(args, scope, name);

            if (method.name) {
                $.shop.trace('$.importexport.click', method);
                if (!$el.hasClass('js-confirm') || confirm($el.data('confirm-text') || $el.attr('title') || 'Are you sure?')) {
                    method.params.push($el);
                    scope[method.name].apply(scope, method.params);
                }
            } else {
                $.shop.error('Not found js handler for link', [method, args, $el])
            }
        } catch (e) {
            $.shop.error('Exception ' + e.message, e);
        }
        return false;
    },

    profileAdd: function (plugin, hash) {
        hash = hash || '';
        if ((typeof hash == 'string') && hash.match(/^hash\//)) {
            hash = hash.replace(/^hash\//, '');
        } else {
            hash = null;
        }
        $.ajax({
            url: '?module=importexport&action=add',
            type: 'POST',
            dataType: 'json',
            data: {plugin: plugin, hash: hash},
            success: function (response) {
                $.shop.trace('addAction', response.data);
                if (response && (response.status == 'ok')) {
                    var regexp = new RegExp('\\/(' + plugin + '):?\\d*\\/', 'g');
                    var replace = '/$1:' + response.data + '/';
                    window.location.hash = window.location.hash.replace(regexp, replace);
                }
            }
        });
    },

    profileUpdateName: function (name) {
        var selector = ('' + this.profiles.key() + ':' + this.path.profile ).replace(/([:#])/g, '\\\\$1');
        $('#s-importexport-profile').find('a[href="#/' + selector + '/"]:first').text(name);
    },

    profileDelete: function (plugin, $el) {
        var profile = this.path.profile;

        var selector = ('' + plugin + ':' + profile ).replace(/([:#])/g, '\\\\$1');
        $.shop.trace('profileDelete', [plugin, profile, selector]);
        var $li = $('#s-importexport-profile').find('a[href^="#/' + selector + '/"]:first').parents('li:first');
        var $delete = $el.find('i.icon16.delete');
        $delete.removeClass('delete').addClass('loading');
        var self = this;
        $.ajax({
            url: '?module=importexport&action=delete',
            type: 'POST',
            dataType: 'json',
            data: {plugin: plugin, profile: profile},
            success: function (response) {
                $.shop.trace('profileDelete', response.data);
                if (response && (response.status == 'ok')) {
                    $delete.removeClass('loading').addClass('delete');
                    $el.parents('li:first').hide();
                    $li.remove();
                    $.shop.trace('profileDelete', [self.path, profile]);
                    if (self.profiles.list[plugin][profile]) {
                        self.profiles.list[plugin][profile] = null;
                    }
                    if (self.path.profile == profile) {
                        var first = null;
                        for (profile in self.profiles.list[plugin]) {
                            if (self.profiles.list[plugin].hasOwnProperty(profile) && self.profiles.list[plugin][profile]) {
                                first = profile;
                                break;
                            }
                        }
                        if (first) {
                            window.location.hash = window.location.hash.replace(/:\d*\/$/, ':' + first + '/');
                        } else {
                            self.profileAdd(plugin);
                        }
                    }
                }
            }
        });
    },

    /**
     * Setup plugin options
     *
     * @param {Object} options
     */
    importexportOptions: function (options) {
        var plugin = this.path.dispatch.plugin || this.path.plugin;
        $.shop && $.shop.trace('$.importexport.importexportOptions', plugin);
        if (typeof (this[plugin + '_options']) == 'undefined') {
            this[plugin + '_options'] = {};
        }
        if (options) {
            this[plugin + '_options'] = $.extend(this[plugin + '_options'], options);
        }
    },

    /**
     * Handler call focus out plugin
     *
     * @param {String} plugin
     */
    importexportBlur: function (plugin, current_plugin) {
        this.$menu.find('li.selected').removeClass('selected');
        $.shop && $.shop.trace('$.importexport.importexportBlur', plugin);
        if (this.path.plugin || this.path.module) {
            this.call('Blur', []);
        } else {
            //this.options.loading = $('#s-importexport-content').html() || this.options.loading;
        }
        if (current_plugin && (plugin != plugin)) {

        }
        $('#s-importexport-content').html(this.options.loading);
    },

    importexportLoad: function ($content, path) {
        this.path.plugin = path.plugin;
        this.path.module = path.module;
        this.path.direction = path.direction;
        this.path.prefix = path.prefix;
        this.path.profile = path.profile;
        this.path.tail = path.tail;
        $.shop.trace('$.importexport.importexportLoad', path);

        //update selected plugin
        this.$menu.find('li.selected').removeClass('selected');
        var key = this.path.plugin ? this.path.plugin : (this.path.module + ':' + this.path.prefix + (this.path.direction ? ':' + this.path.direction : ''));
        var href = key;
        if (this.path.direction && this.path.plugin) {
            href += ':' + this.path.direction;
        }
        if (this.options.plugin_profiles[key]) {
            href += ':';
        } else {
            href += '/';
        }

        var $a = this.$menu.find('a[href^="\#\/' + href + '"]');
        $a.parents('li').addClass('selected');

        // update title
        var plugin_name = $content.find('h1:first').hide().text() || this.options.plugin_names[this.path.plugin] || ($a
                .clone()    //clone the element
                .children() //select all the children
                .remove()   //remove all the children
                .end()  //again go back to selected element
                .text()) || '';
        window.document.title = plugin_name + this.options.title_suffix;
        this.$header.find('> h1:first').text(plugin_name);

        this.$header.find('> p:first').html($content.find('p:first').hide().html());
        this.$header.show();
        this.profiles.init();
        this.profiles.draw(path);

        this.$content = $content;
        var self = this;

        this.products.init(this.$content.find('form'));
        this.$content.on('click', 'a.js-action', function () {
            try {
                return self.click($(this));
            } catch (e) {
                $.shop.error('Exception ' + e.message, e);
                return false;
            }
        });


        this.importexportProfileInit();

        this.onPluginLoad(path, function () {
            self.call('onInit', [path]);
            self.importexportAction(path.action, path.tail);
        }, 200);
    },

    importexportProfileInit: function () {

        var self = this;
        this.$content.find('form').bind('submit.profile', function () {
            if (self.path.profile != null) {
                try {
                    self.profiles.onSubmit();
                    var $this = $(this);
                    self.profileUpdateName($this.find(':input[name="profile\\[name\\]"]:first').val());
                    $this.find('.js-profile-notice').hide();
                } catch (e) {
                    $.shop.error('Exception ' + e.message, e);
                }
            }
            return true;
        });

        this.$content.off('click.profile').on('click.profile', 'h2.js-profile-collapsible', function () {
            var $this = $(this);
            var $container = $this.parents('div.field-group:first');
            if ($this.data('visible')) {
                $container.find('> div.field:visible').slideUp();
            } else {
                $container.find('> div.field:hidden').slideDown();
            }
            $this.data('visible', !$this.data('visible'));
            return false;
        });

    },

    /**
     * handle current plugin actions after HTML has bead loaded
     *
     * @param {String} action
     * @param {String} tail
     */
    importexportAction: function (action, tail) {
        var method = '';


        if (action) {
            method += action.substr(0, 1).toUpperCase() + action.substr(1);
        }
        method += 'Action';
        $.shop && $.shop.trace('$.importexport.importexportAction', [action, tail]);
        this.call(method, [tail]);
        this.path.tail = tail;
    },

    onPluginLoad: function (path, callback, timeout) {
        if (!timeout) {
            timeout = 50;
        } else {
            timeout += 50;
        }
        var loaded = false;
        if (path.plugin) {
            loaded = !!this.plugins[path.plugin];
        } else {
            loaded = !!this[path.module + '_' + path.prefix];
        }
        if (loaded) {
            callback();
        } else {
            setTimeout(function () {
                $.importexport.onPluginLoad(path, callback, timeout)
            }, timeout);
        }
    },


    /**
     * @param {String} name
     * @param {Array} args
     */
    call: function (name, args) {
        var plugin;
        if (this.path.plugin) {
            plugin = this.path.plugin;
        } else {
            plugin = this.path.module + '_' + this.path.prefix;
        }
        var action = plugin + name.substr(0, 1).toUpperCase() + name.substr(1);
        action = action.replace(/^(\d)/, 'a$1');
        var callable = (typeof (this[action]) == 'function');
        var start = $.shop && $.shop.trace('$.importexport.call ' + action, [callable, typeof (this[action]), args]);
        var result = null;
        if (callable) {
            try {
                result = this[action].apply(this, args);
            } catch (e) {
                (($.shop && $.shop.error) || console.log)('Exception at $.importexport.' + action + ': ' + e.message, e);
            }
            $.shop && $.shop.trace('$.settings.call complete ' + action, [$.shop.time.interval(start) + 's', result, args]);
        } else {
            action = name.substr(0, 1).toLowerCase() + name.substr(1);
            action = action.replace(/^(\d)/, 'a$1');
            callable = this.plugins[plugin] && (typeof (this.plugins[plugin][action]) == 'function');
            start = $.shop && $.shop.trace('$.importexport.call ' + plugin + '.' + action, [callable, args, plugin]);
            if (callable) {
                try {
                    result = this.plugins[plugin][action].apply(this.plugins[plugin], args);
                } catch (e) {
                    (($.shop && $.shop.error) || console.log)('Exception at $.importexport.' + action + ': ' + e.message, e);
                }
                $.shop && $.shop.trace('$.settings.call complete ' + plugin + '.' + action, [$.shop.time.interval(start) + 's', result, args]);
            } else {
                $.shop.error('method not found at call', [name, action]);
            }
        }
        return result;
    },

    profiles: {
        list: {},
        $profiles: null,
        $container: null,
        path: {},
        sortable: false,
        counter: 0,
        init: function () {
            this.$profiles = $('#s-importexport-profile');
            this.$container = $('#s-importexport-content');
            var self = this;
            if (this.sortable) {

                this.$profiles.sortable({
                    distance: 5,
                    opacity: 0.75,
                    items: '> li:not(.no-tab)',
                    axis: 'x',
                    forceHelperSize: true,
                    containment: 'parent',
                    update: function (event, ui) {
                        var id = parseInt($(ui.item).data('id'));
                        var after_id = $(ui.item).prev().data('id');
                        if (after_id === undefined) {
                            after_id = 0;
                        } else {
                            after_id = parseInt(after_id);
                        }
                        self.onSort(id, after_id, $(this));
                    }
                });
            }
        },
        onSubmit: function () {
            this.$profiles.find('> li.no-tab:last').hide();
        },
        onSort: function (id, after_id, $this) {
            $.shop.trace('$.importexport.profiles.onSort', [id, after_id, $this]);
        },
        set: function (plugin, profiles) {
            this.list[plugin] = profiles || {};
        },

        onRun: function () {
            if (this.sortable) {
                this.$profiles.sortable('disable');
            }
        },
        onComplete: function () {
            if (this.sortable) {
                this.$profiles.sortable('enable');
            }
        },

        draw: function (path) {
            this.path = path || this.path;
            //redraw list of available configs & load default one
            var id;
            var counter = 0;
            this.$profiles.find('> li:not(.no-tab)').remove();
            this.$profiles.find('> li.no-tab').show();
            var key = this.key();
            $.shop.trace('profiles.draw', [key, $.importexport.options.plugin_profiles[key]]);
            if ($.importexport.options.plugin_profiles[key]) {
                var $add = this.$profiles.find('> li.no-tab:first > a:first');
                $add.attr('href', $add.data('href').replace(/%plugin%/, key));

                var selected = null;
                if (this.list[key]) {
                    var $end = this.$profiles.find('> li.no-tab:first');
                    for (id in this.list[key]) {
                        if (this.list[key].hasOwnProperty(id)) {
                            $end.before(this.html(id, key));
                            if (id == path.profile) {
                                selected = id;
                            }
                            ++counter;
                        }
                    }
                }
                if (!counter) {
                    this.hide();
                    $.importexport.profileAdd(key);
                } else {
                    var $delete = this.$profiles.find('> li.no-tab:last').show().find('> a:first');
                    $delete.attr('href', $delete.data('href').replace(/%plugin%/, key));
                    $delete.find('i.icon16.loading').removeClass('loading').addClass('delete');
                    this.show();
                    if (!selected && id) {
                        window.location.hash = window.location.hash.replace(/:\d*\/$/, ':' + id + '/');
                    }
                    if (this.sortable) {
                        var self = this;
                        setTimeout(function () {
                            self.$profiles.sortable('refresh');
                            self.$profiles.sortable('enable');
                        }, 50);
                    }
                }
            } else {
                this.hide();
            }
            //remove obsolete
        },
        key: function () {
            return !!this.path.plugin ? this.path.plugin : [this.path.module, this.path.prefix, this.path.direction].filter(function (v) {
                return v && (v.length > 0);
            }).join(':');
        },
        show: function () {
            this.$profiles.show();
            this.$container.addClass('tab-content');
        },
        hide: function () {
            this.$profiles.hide();
            this.$container.removeClass('tab-content');
        },
        form: function (hidden) {
            return ('<div class="field"' + (hidden ? ' style="display:none;"' : '') + '>' +
            '<div class="name">' + $_('Profile name') + '</div>' +
            '<div class="value"><input type="text" name="profile[name]"></div>' +
            '</div>' );
        },

        html: function (id, key) {
            var $a, profile;
            if (id > 0) {
                profile = this.list[key][id];
            }

            $a = $('<a></a>');
            if (profile) {
                $a.text(profile.name);
                $a.attr('title', profile.description);
            } else {
                $a.text($_('New importexport profile'));
            }
            $a.attr('href', ['#', this.key() + ':' + ( id || --this.counter)].join('/') + '/');

            var $li = $('<li></li>');
            $li.append($a);

            if (!id || (id == this.path.profile)) {
                $li.addClass('selected');
            }
            return $li
        }

    },
    products: {
        $context: null,
        $inputs: null,
        init: function ($context) {
            this.$context = $context;
            this.$inputs = this.$context.find(':input[name="hash"]');

            var self = this;
            this.$inputs.change(function () {
                self.handler($(this));
            });

            this.$inputs.trigger('change');
        },

        /**
         *
         * @param hash {string} product collection hash
         */
        action: function (hash) {
            var options = (hash || '').split('/');

            var $select = this.$inputs.filter('[value="' + options[0] + '"]:first');

            if ($select.length) {
                var context = options[0] || '';
                var $label;

                $select.parents('li').show();
                $.shop.trace('hash/action/test', [context, options]);
                var label;
                switch (context) {
                    case 'id':
                        var ids = options[1] || '0';
                        this.$context.find(':input[name="product_ids"]:first').val(ids);
                        $label = $select.parents('li').find('span');
                        label = $label.data('text') || $label.text();
                        $label.text(label.replace(/%d/g, ids.split(',').length));
                        break;
                    case 'type':
                        var type = parseInt(options[1]);
                        this.$context.find(':input[name="type_id"]').val([type]);
                        break;
                    case 'set':
                        var set = options[1];
                        this.$context.find(':input[name="set_id"]').val([set]);
                        break;
                    case 'category':
                        var category_ids = options[1] || '0';
                        var $input = this.$context.find(':input[name="category_ids"]');
                        if ($input.length == 1) {

                            $input.val(category_ids);
                        } else {
                            $input.filter('[value="' + category_ids + '"]').attr('checked', true);
                        }
                        $label = $select.parents('li').find('span');
                        category_ids = category_ids.split(',');
                        label = $label.data('text') || $label.text();
                        $label.text(label.replace(/%d/g, category_ids.length));
                        break;
                }

                $select.attr('checked', true).trigger('change');
            }
        },
        handler: function ($this) {
            var $container = $this.parents('div.field');
            if ($this.is(':checked')) {
                var context = $this.val();
                var $context = $container.find('.js-hash-values.js-hash-' + context);

                $container.find('.js-hash-values:visible').hide();
                $context.show();
                switch (context) {
                    case 'category':
                        //select selected data & update counters
                        var $input = $context.find(':input[name="category_ids"]');
                        if ($input.length > 1) {
                            if (!$input.filter(':checked').length) {
                                $input.filter('[data-selected="selected"]').attr('checked', true);
                                if (!$input.filter(':checked').length) {
                                    $input.filter(':first').attr('checked', true)
                                }


                                var $label = $this.parents('li:first').find('span:first');
                                $.shop.trace('label', $label);
                                var label = $label.data('text') || $label.text();


                                $label.text($label.data('text').replace(/%d/g, $input.filter(':checked').length));
                            }
                        }
                        break;
                }
            }
        }
    },

    helper: {
        buildUrl: function (path) {
            var url = [];
            if (path.plugin) {
                if (path.plugin == 'plugins') {
                    return $.importexport.options.backend_url + 'installer/?module=plugins&action=view&options[no_confirm]=1&slug=shop&filter[tag]=importexport';
                }
                url.push('plugin=' + path.plugin);
            }
            if (path.module && (path.module != 'backend')) {
                url.push('module=' + path.module);
            }

            url.push('action=' + (path.prefix || '') + 'setup');

            if (path.direction) {
                url.push('direction=' + path.direction);
            }
            if (path.profile) {
                url.push('profile=' + path.profile);
            }

            return '?' + url.join('&');
        }
    },

    getPlugin: function () {

    }
});
