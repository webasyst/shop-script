/**
 * @
 */

$.extend($.importexport = $.importexport || {}, {
    options : {
        'loading' : '<i class="icon16 loading"></i>',
        'path' : '#/'
    },
    path : {
        'plugin' : false,
        'action':'default',
        'tail' : null,
        'dispatch' : null
    },
    plugins: {

    },

    ready : false,
    menu   : null,

    /**
     * @param {} options
     */
    init : function(options) {
        this.options = $.extend(this.options, options || {});
        if (!this.ready) {
            this.ready = true;
            this.menu = $('#s-importexport-menu');

            // Set up AJAX to never use cache
            $.ajaxSetup({
                cache: false
            });

            if (typeof($.History) != "undefined") {
                $.History.bind(function() {
                    $.importexport.dispatch();
                });
            }
            $.wa.errorHandler = function(xhr) {
                if ((xhr.status === 403) || (xhr.status === 404)) {
                    var text = $(xhr.responseText);
                    var message = $('<div class="block double-padded"></div>');
                    if (text.find('.dialog-content').length) {
                        text =message.append(text.find('.dialog-content *'));
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
     *
     * @param {String} path
     * @return { 'plugin':String, 'action':String, 'tail':String,'raw':String }
     */
    parsePath : function(path) {
        path = path.replace(/^.*#\//, '');
        return {
            'plugin' : path.replace(/\/.*$/, '') || '',
            'action': path.replace(/^[^\/]+\//, '').replace(/\/.*$/, '') || 'setup',
            'tail' : path.replace(/^([^\/]+\/){1,2}/, '').replace(/\/$/, ''),
            'raw' : path
        };
    },

    /**
     * Dispatch location hash changes
     *
     * @param {String} hash
     * @param {Boolean} load Force reload if need
     * @return {Boolean}
     */
    dispatch : function(hash, load) {
        if (hash === undefined) {
            hash = window.location.hash;
        }
        if (!hash) {
            var $plugin = this.menu.find('li:first > a:first');
            if ($plugin.length) {
                window.location.hash = hash = $plugin.attr('href');
            }
        }
        if (load) {
            window.location.hash = hash;
        }
        var path = this.parsePath(hash.replace(/^[^#]*#\/*/, ''));
        this.path.dispatch = path;

        /* change importexport plugin */
        if (load || (path.plugin && ( path.plugin != this.path.plugin))) {
            var $content = $('#s-importexport-content');
            this.importexportBlur(this.path.plugin);
            $.shop && $.shop.trace('$.importexport.dispatch (default) ' + this.path.plugin + ' -> ' + path.plugin);
            this.path.tail = null;
            if (!load) {
                $content.empty().html(this.options.loading);
            }

            var self = this;
            $content.load('?plugin=' + path.plugin+'&action=setup', function() {
                self.path.plugin = path.plugin || self.path.plugin;

                // update title
                document.title = self.options.plugin_names[self.path.plugin] + self.options.title_suffix;

                self.menu.find('a[href*="\\#\/' + self.path.plugin + '\/"]').parents('li').addClass('selected');
                self.importexportAction(path.action, path.tail);
            });

            return true;
        } else if(!path.plugin) {
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
     * @param {} options
     */
    importexportOptions : function(options) {
        var plugin = this.path.dispatch.plugin || this.path.plugin;
        $.shop && $.shop.trace('$.importexport.importexportOptions', plugin);
        if (typeof(this[plugin + '_options']) == 'undefined') {
            this[plugin + '_options'] = {};
        }
        this[plugin + '_options'] = $.extend(this[plugin + '_options'], options || {});
    },

    /**
     * Handler call focus out plugin
     *
     * @param {String} plugin
     */
    importexportBlur : function(plugin) {
        plugin = plugin || this.path.plugin;
        this.menu.find('li.selected').removeClass('selected');
        $.shop && $.shop.trace('$.importexport.importexportBlur', plugin);
        if (plugin) {
            this.call(plugin + 'Blur', []);
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
    importexportAction : function(action, tail) {
        $.shop && $.shop.trace('$.importexport.importexportAction', [this.path.plugin, action, tail]);
        this.call(action + 'Action', [tail]);
        this.path.tail = tail;
    },

    /**
     * Handle current plugin actions before HTML has beed loaded.
     *
     * Calls this.<plugin>PreLoad(tail, load).
     *
     * If it returns false, this prevents HTML from loading, and both importexportAction() and importexportBlur() from being called. Correspinding PreLoad is
     * responsible for calling importexportBlur(this.path.plugin).
     *
     * @param {String} plugin
     * @param {String} tail
     */
    importexportPreLoad : function(plugin, action, tail, load) {
        $.shop && $.shop.trace('$.importexport.importexportPreLoad', [plugin, tail, load]);
        return this.call(plugin + 'PreLoad', [tail, load]);
    },

    /**
     *
     * @param {String} name
     * @param [] args
     */
    call : function(name, args) {
        var callable = (typeof(this[name]) == 'function');
        var start = $.shop && $.shop.trace('$.importexport.call', [name, args, callable]);
        var result = null;
        if (callable) {
            try {
                result = this[name].apply(this, args);
            } catch (e) {
                (($.shop && $.shop.error) || console.log)('Exception: ' + e.message, e);
            }
            $.shop && $.shop.trace('$.importexport.call complete', [name, $.shop.time.interval(start) + 'ms', args, result]);
        }
        return result;
    }
});
