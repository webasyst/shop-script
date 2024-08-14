/**
 * @
 */

$.extend($.settings = $.settings || {}, {
    options: {
        backend_url: '/webasyst/',
        shop_marketing_url: '/webasyst/shop/marketing/',
        loading: '<i class="fas fa-spinner fa-spin"></i>',
        path: '#/',
        installer_access: false
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

    $container: null,

    ready: false,
    menu: null,

    /**
     * @param {Object=} options
     */
    init: function (options) {
        this.xhr = false;
        this.options = $.extend(this.options, options || {});
        this.initAnimation();

        if (!this.ready) {
            this.ready = true;
            this.menu = $('#s-settings-menu');
            this.$container = $("#s-settings-content");

            // Set up AJAX to never use cache
            $.ajaxSetup({
                cache: false
            });

            if (typeof($.History) != "undefined") {
                $.History.bind(function () {
                    $.settings.dispatch();
                });
            }

            const self = this;

            $.wa.errorHandler = function (xhr) {
                if ((xhr.status === 403) || (xhr.status === 404)) {
                    var $text = $(xhr.responseText);
                    var $message = $('<div class="block double-padded"></div>');
                    if ($text.find('.dialog-content').length) {
                        $message.append($text.find('.dialog-content *'));
                    } else {
                        $message.append($text.find(':not(style)'));
                    }
                    self.$container.empty().append($message);
                    return false;
                }
                return true;
            };

            const hash = window.location.hash;
            if (hash === '#/' || !hash) {
                this.dispatch();
            } else {
                $.wa.setHash(hash);
            }
        }
    },

    initAnimation: function() {
        var waLoading = $.waLoading();
        var $wrapper = $("#wa"),
            locked_class = "is-locked";

        $wrapper
            .on("wa_before_load", function() {
                waLoading.show();
                waLoading.animate(3000, 100, false);
                $wrapper.addClass(locked_class);
            })
            .on("wa_loading", function(event, xhr_event) {
                var percent = (xhr_event.loaded / xhr_event.total) * 100;
                waLoading.set(percent);
            })
            .on("wa_abort", function() {
                waLoading.abort();
                $wrapper.removeClass(locked_class);
            })
            .on("wa_loaded", function() {
                waLoading.done();
                $wrapper.removeClass(locked_class);
            });
    },

    load: function(content_url, doneCallback, config) {
        config = $.extend({
            data: {}, wrapper: false, setHtml: true
        }, config);
        const deferred = $.Deferred();
        const self = this;
        const $wrapper = typeof config.wrapper === "object" ? config.wrapper : this.$container;

        if (this.xhr) { this.xhr.abort(); }

        $wrapper.trigger("wa_before_load", [{
            content_url: content_url,
            data: $.extend({}, config.data),
        }]);


        this.xhr = $.ajax({
                method: 'GET',
                url: content_url,
                data: config.data,
                dataType: 'html',
                global: false,
                cache: false,
                xhr: function() {
                    var xhr = new window.XMLHttpRequest();

                    xhr.addEventListener("progress", function(event) {
                        self.$container.trigger("wa_loading", event);
                    }, false);

                    xhr.addEventListener("abort", function(event) {
                        self.$container.trigger("wa_abort");
                    }, false);

                    return xhr;
                }
            })
            .always(function() {
                self.xhr = false;
            })
            .done(function(html) {
                $wrapper.trigger("wa_loaded");
                if (config.setHtml) {
                    $wrapper.html(html);
                }
                deferred.resolve(html);

                if (typeof doneCallback === "function") {
                   doneCallback();
                }
            })
            .fail(function(data, state) {
                if (data.responseText) {
                    console.log(data.responseText);
                }
                $wrapper.trigger("wa_load_fail");
                deferred.reject(state);
            });

        return deferred.promise();
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

    /** Change location hash without triggering dispatch */
    forceHash: function (hash) {
        if (hash && hash.length && hash[0] == '/') {
            hash = '#' + hash;
        }
        if (location.hash != hash) {
            this.skipDispatch++;
            $.wa.setHash(hash);
        }
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

        const path = this.parsePath(hash.replace(/^[^#]*#\/*/, ''));

        // Redirect to Marketing tab
        if (path.section === "discounts") {
            window.location.href = this.options["shop_marketing_url"] + path.raw;
            return false;
        }

        if (path.section === "affiliate") {
            window.location.href = this.options["shop_marketing_url"] + "affiliate/";
        }

        this.path.dispatch = path;
        $.shop && $.shop.trace('$.settings.dispatch ' + this.path.section + ' -> ' + path.section + ' # ' + path.tail);

        const pre_load_result = this.settingsPreLoad(path.section, path.tail, load);
        if (false === pre_load_result) {
            $.shop && $.shop.trace('$.settings.dispatch: PreLoad returned false, no further action required.');
            return true;
        }
        if (true === pre_load_result) {
            $.shop && $.shop.trace('$.settings.dispatch: PreLoad returned true, blur from previous page.');
            this.settingsBlur(this.path.section);
            this.$container.html(this.options.loading);
            this.path.tail = path.tail;
            this.path.section = path.section || this.path.section;
            this.menu.find('a[href*="\\#\/' + this.path.section + '\/"]').parents('li').addClass('selected');
            return true;
        }

        /* change settings section */
        if (load || (path.section != this.path.section)) {
            $.shop && $.shop.trace('$.settings.dispatch: PreLoad returned nothing. Load section HTML, then call settingsAction().');
            this.settingsBlur(this.path.section, path);
            this.path.tail = null;
            if (!load) {
                this.$container.html(this.options.loading);
            }

            const self = this;
            let url = '?module=settings&action=' + path.section;
            for (var param in path.params) {
                if (path.params.hasOwnProperty(param)) {
                    url += '&' + param + '=' + path.params[param];
                }
            }

            if (path.tail && (typeof(path.tail) != 'undefined')) {
                url += '&param[]=' + path.tail.split('/').join('&param[]=');
            }

            $.shop && $.shop.trace('$.settings.dispatch: Load URL', [url, path.params]);
            this.load(url, function () {
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
        const that = this;
        const args = $el.attr('href').replace(/.*#\//, '').replace(/\/$/, '').split('/');
        const method = $.shop.getMethod(args, this);

        if (method.name) {
            $.shop.trace('$.settings.click', method);

            if ($el.hasClass('js-confirm')) {
                $.waDialog.confirm({
                    title: $el.data('confirm-title') || $_('Are you sure?'),
                    text: $el.data('confirm-text'),
                    success_button_title: $el.data('title') || $el.attr('title') || 'OK',
                    success_button_class: 'danger',
                    cancel_button_title: $el.data('cancel') || $.wa.locale['cancel'] || 'Cancel',
                    cancel_button_class: 'light-gray',
                    onSuccess() {
                        method.params.push($el);
                        that[method.name].apply(that, method.params);
                    }
                });
            } else {
                method.params.push($el);
                that[method.name].apply(that, method.params);
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
        $.shop && $.shop.trace('$.settings.settingsOptions', [section, options]);
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
            this.options.loading = this.$container.html() || this.options.loading;
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
        if (typeof this.onPreLoad === "function") {
          this.onPreLoad(section);
        }

        return this.call(section + 'PreLoad', [tail, load]);
    },

    /**
     *
     * @param {String} name
     * @param {Array=} args
     */
    call: function (name, args) {
        var method = name;
        var matches = method.match(/^(\w+)&plugin=([a-z0-9_]+)(Action|Blur|)$/);
        if (matches) {
            method = 'plugin_' + matches[2] + matches[1].substr(0, 1).toUpperCase() + matches[1].substr(1) + matches[3];
        }
        var callable = (typeof(this[method]) == 'function');

        var start = $.shop && $.shop.trace('$.settings.call', [method, name, args, callable,this]);
        var result = null;
        if (callable) {
            try {
                result = this[method].apply(this, args);
            } catch (e) {
                (($.shop && $.shop.error) || console.log)('Exception: ' + e.message, e);
            }
            $.shop && $.shop.trace('$.settings.call complete', [method, $.shop.time.interval(start) + 's', args, result]);
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
        this.load('?module=settings&action=taxes&id=' + id, function () {
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
        this.load('?module=settings&action=followups&id=' + id, function() {
            self.settingsAction(self.path.section, tail);
        });
        return true;
    },

    couriersPreLoad: function (id) {
        this.load('?module=settings&action=couriers&id=' + (id || ''));
        return true;
    },

    typefeatPreLoad: function (id) {
        this.load('?module=settings&action=typefeatList&type=' + (id || ''));
        // this.$container.load('?module=settings&action=typefeatList&type=' + (id || ''))
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
