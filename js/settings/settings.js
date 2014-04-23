/**
 * @
 */

$.extend($.settings = $.settings || {}, {
    options: {
        backend_url: '/webasyst/',
        loading: '<i class="icon16 loading"></i>',
        path: '#/'
    },

    path: {
        /**
         * @type {String}
         */
        section: null,

        /**
         * @type {String}
         */
        tail: null,

        /**
         * @type {Object}
         */
        params: {},
        dispatch: null
    },

    ready: false,
    menu: null,

    /**
     * @param {Object=} options
     */
    init: function (options) {
        this.options = $.extend(this.options, options || {});
        if (!this.ready) {
            this.ready = true;
            this.menu = $('#s-settings-menu');

            // Set up AJAX to never use cache
            $.ajaxSetup({
                cache: false
            });

            if (typeof($.History) != "undefined") {
                $.History.bind(function () {
                    $.settings.dispatch();
                });
            }
            $.wa.errorHandler = function (xhr) {
                if ((xhr.status === 403) || (xhr.status === 404)) {
                    var $text = $(xhr.responseText);
                    var $message = $('<div class="block double-padded"></div>');
                    if ($text.find('.dialog-content').length) {
                        $message.append($text.find('.dialog-content *'));
                    } else {
                        $message.append($text.find(':not(style)'));
                    }
                    $("#s-settings-content").empty().append($message).append('<div class="clear-both"></div>');
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
     * @return {{section:String, tail:String,params:Object,raw:String}}
     */
    parsePath: function (path) {
        path = path.replace(/^.*#\//, '');
        return {
            section: path.replace(/\/.*$/, '') || 'general',
            tail: path.replace(/^[^\/]+\//, '').replace(/[\w_\-]+=.*$/, '').replace(/\/$/, ''),
            params: path.match(/(^|\/)[\w_\-]+=/) ? $.shop.helper.parseParams(path.replace(/(^|^.*\/)([\w_\-]+=.*$)/, '$2').replace(/\/$/, '')) : {},
            raw: path
        };
    },

    /** Force reload current content page. */
    redispatch: function () {
        this.dispatch(window.location.hash, true);
    },


    // if this is > 0 then this.dispatch() decrements it and ignores a call
    skipDispatch: 0,

    /** Cancel the next n automatic dispatches when window.location.hash changes */
    stopDispatch: function (n) {
        this.skipDispatch = n;
    },

    /**
     * Dispatch location hash changes
     *
     * @param {String=} hash
     * @param {Boolean=} load Force reload if need
     * @return {Boolean}
     */
    dispatch: function (hash, load) {
        if (load) {
            this.skipDispatch = 0;
        } else if (this.skipDispatch > 0) {
            this.skipDispatch--;
            return false;
        }
        if (hash === undefined) {
            hash = window.location.hash;
        }
        if (load) {
            window.location.hash = hash;
        }
        var path = this.parsePath(hash.replace(/^[^#]*#\/*/, ''));
        this.path.dispatch = path;
        $.shop && $.shop.trace('$.settings.dispatch ' + this.path.section + ' -> ' + path.section + ' # ' + path.tail);

        var pre_load_result = this.settingsPreLoad(path.section, path.tail, load);
        if (false === pre_load_result) {
            $.shop && $.shop.trace('$.settings.dispatch: PreLoad returned false, no further action required.');
            return true;
        }
        if (true === pre_load_result) {
            $.shop && $.shop.trace('$.settings.dispatch: PreLoad returned true, blur from previous page.');
            this.settingsBlur(this.path.section);
            $('#s-settings-content').html(this.options.loading);
            this.path.tail = path.tail;
            this.path.section = path.section || this.path.section;
            this.menu.find('a[href*="\\#\/' + this.path.section + '\/"]').parents('li').addClass('selected');
            return true;
        }

        /* change settings section */
        if (load || (path.section != this.path.section)) {
            $.shop && $.shop.trace('$.settings.dispatch: PreLoad returned nothing. Load section HTML, then call settingsAction().');
            var $content = $('#s-settings-content');
            this.settingsBlur(this.path.section, path);
            this.path.tail = null;
            if (!load) {
                $content.html(this.options.loading);
            }

            var self = this;
            var url = '?module=settings&action=' + path.section;
            for (var param in path.params) {
                if (path.params.hasOwnProperty(param)) {
                    url += '&' + param + '=' + path.params[param];
                }
            }

            if (path.tail && (typeof(path.tail) != 'undefined')) {
                url += '&param[]=' + path.tail.split('/').join('&param[]=');
            }

            $.shop && $.shop.trace('$.settings.dispatch: Load URL', [url, path.params]);
            $content.load(url, function () {
                self.path.section = path.section || self.path.section;
                self.menu.find('a[href*="\\#\/' + self.path.section + '\/"]').parents('li').addClass('selected');
                self.settingsAction(path.section, path.tail);
            });

            return true;
        }
        /* update settings section */
        else {
            $.shop && $.shop.trace('$.settings.dispatch: PreLoad returned nothing. Section is already loaded, just call settingsAction().');
            this.settingsAction(path.section, path.tail);
            return true;
        }
    },

    /**
     * Handle js section interactions
     *
     * @param {jQuery} $el
     * @return {Boolean}
     */
    click: function ($el) {
        var args = $el.attr('href').replace(/.*#\//, '').replace(/\/$/, '').split('/');
        var method = $.shop.getMethod(args, this);

        if (method.name) {
            $.shop.trace('$.settings.click', method);
            if (!$el.hasClass('js-confirm') || confirm($el.data('confirm-text') || $el.attr('title') || $_('Are you sure?'))) {
                method.params.push($el);
                this[method.name].apply(this, method.params);
            }
        } else {
            $.shop.error('Not found js handler for link', [method, $el])
        }
        return false;
    },

    /**
     * Setup section options
     *
     * @param {Object} options
     */
    settingsOptions: function (options) {
        var section = this.path.dispatch.section || this.path.section;
        $.shop && $.shop.trace('$.settings.settingsOptions', [section,options]);
        if (typeof(this[section + '_options']) == 'undefined') {
            this[section + '_options'] = {};
        }
        this[section + '_options'] = $.extend(this[section + '_options'], options || {});
    },

    /**
     * Handler call focus out section
     *
     * @param {String} section
     * @param {{section:String, tail:String,params:Object,raw:String}} path
     */
    settingsBlur: function (section, path) {
        section = section || this.path.section;
        if (!path || !path.section || (path.section != this.path.section)) {
            this.menu.find('li.selected').removeClass('selected');
        }

        $.shop && $.shop.trace('$.settings.settingsBlur', section);
        if (section) {
            this.call(section + 'Blur', []);
        } else {
            this.options.loading = $('#s-settings-content').html() || this.options.loading;
        }
    },

    /**
     * handle current section actions after HTML has being loaded
     *
     * @param {String} section
     * @param {String} tail
     */
    settingsAction: function (section, tail) {
        $.shop && $.shop.trace('$.settings.settingsAction', [section, tail]);
        this.call(section + 'Action', [tail]);
        this.path.tail = tail;
    },

    /**
     * Handle current section actions before HTML has being loaded. Pre-actions replace this.dispatch() behaviour in case we need to parse #-hash-string before
     * data load, e.g. to determine params to pass to PHP.
     *
     * Calls this.<section>PreLoad(tail, load).
     *
     * If it returns FALSE, then this.dispatch() behaves as if page had not been changed. No HTML loads, and none of settingsAction() and settingsBlur() are
     * called.
     *
     * If it returns TRUE, then this.dispatch() calls settingsBlur(), but loads no HTML and settingsAction() is NOT called. In this case PreLoad is responsible
     * for calling settingsAction() when HTML is loaded.
     *
     * If anything else is returned, this.dispatch() behaves as if there's no PreLoad at all. It calls settingsBlur(), loads HTML, then calls settingsAction().
     *
     * Note that, as opposed to Actions which are defined in their own files, PreLoads need to be defined in this file to be available at the time of
     * dispatching.
     *
     * @param {String} section
     * @param {String} tail
     * @param {Boolean=} load
     */
    settingsPreLoad: function (section, tail, load) {
        $.shop && $.shop.trace('$.settings.settingsPreLoad', [section, tail, load]);
        return this.call(section + 'PreLoad', [tail, load]);
    },

    /**
     *
     * @param {String} name
     * @param {Array=} args
     */
    call: function (name, args) {
        var callable = (typeof(this[name]) == 'function');
        var start = $.shop && $.shop.trace('$.settings.call', [name, args, callable]);
        var result = null;
        if (callable) {
            try {
                result = this[name].apply(this, args);
            } catch (e) {
                (($.shop && $.shop.error) || console.log)('Exception: ' + e.message, e);
            }
            $.shop && $.shop.trace('$.settings.call complete', [name, $.shop.time.interval(start) + 's', args, result]);
        }
        return result;
    },

    //
    // Pre-actions.
    // See settingsPreLoad()
    //

    taxesPreLoad: function (tail) {
        var id;
        if (tail !== '') {
            id = parseInt((tail || '').split('/')[0]) || 'new';
            // Do not load the same page again
            if (id === this.taxes_id) {
                return false;
            }
        } else {
            id = '';
        }

        // Load content
        var self = this;
        $('#s-settings-content').load('?module=settings&action=taxes&id=' + id, function () {
            self.settingsAction(self.path.section, tail);
        });
        return true;
    },

    followupsPreLoad: function (tail) {
        var id;
        if (tail === 'new') {
            id = '';
        } else if (tail !== '') {
            id = parseInt((tail || '').split('/')[0]) || 'first';
        } else {
            id = 'first';
        }

        // Load content
        var self = this;
        $('#s-settings-content').load('?module=settings&action=followups&id=' + id, function () {
            self.settingsAction(self.path.section, tail);
        });
        return true;
    },

    //
    // Helpers
    //
    helpers: {
        init: function () {
            this.compileTemplates();
        },
        /**
         * Compile jquery templates
         *
         * @param {String=} selector optional selector of template container
         */
        compileTemplates: function (selector) {
            var pattern = /<\\\/(\w+)/g;
            var replace = '</$1';

            $(selector || '*').find("script[type$='x-jquery-tmpl']").each(function () {
                var id = $(this).attr('id').replace(/-template-js$/, '');
                var template = $(this).html().replace(pattern, replace);
                try {
                    $.template(id, template);
                    $.shop && $.shop.trace('$.settings.helper.compileTemplates', [selector, id]);
                } catch (e) {
                    (($.shop && $.shop.error) || console.log)('compile template ' + id + ' at ' + selector + ' (' + e.message + ')', template);
                }
            });
        }
    }
});
