(function ($) {
    $.store && !$.storage && ($.storage = new $.store());
    $.tutorial = {
        hash: null,
        random: '',
        options: {},
        prev_action: '',

        init: function (options) {
            $.extend(this.options, options);
            $.store && !$.storage && ($.storage = new $.store());

            this.initRouting();
        },

        initRouting: function () {
            if (typeof($.History) != "undefined") {
                $.History.bind(function () {
                    $.tutorial.dispatch();
                });
            }
            $.wa.errorHandler = function (xhr) {
                if ((xhr.status === 403) || (xhr.status === 404)) {
                    var text = $(xhr.responseText);
                    console.log(text);
                    if (text.find('.dialog-content').length) {
                        text = $('<div class="block double-padded"></div>').append(text.find('.dialog-content'));

                    } else {
                        text = $('<div class="block double-padded"></div>').append(text.find(':not(style)'));
                    }
                    $("#s-content").empty().append(text);
                    return false;
                }
                return true;
            };
            this.dispatch();
        },

        // Set hash without triggering dispatch
        skip_dispatch: 0,
        forceHash: function(hash) {
            if (hash != location.hash) {
                $.tutorial.skip_dispatch++;
                $.wa.setHash(hash);
            }
        },

        redispatch: function() {
            this.hash = null;
            this.dispatch();
        },

        dispatch: function (hash) {
            if ($.tutorial.skip_dispatch > 0) {
                $.tutorial.skip_dispatch--;
                return;
            }

            hash = 'profit';

            if (this.hash === hash) {
                return;
            }
            this.hash = hash;

            try {
                this.preExecute();
                this.defaultAction();
            } catch (e) {
                $.shop.error(e.message, e);
            }
        },

        load: function (url, callback) {
            var r = Math.random();
            this.random = r;
            var self = this;
            $.get(url, function (result) {
                if (self.random != r) {
                    // too late: user clicked something else.
                    return;
                }
                $("#s-content").removeClass('bordered-left').html(result);
                $('html, body').animate({
                    scrollTop: 0
                }, 200);
                if (callback) {
                    try {
                        callback.call(this);
                    } catch (e) {
                        $.shop.error('$.tutorial.load callback error: ' + e.message, e);
                    }
                }
            });
        },

        addOptions: function (options) {
            this.options = $.extend(this.options, options || {});
        },

        preExecute: function (action, args) {
            try {
                if (this.prev_action && (this.prev_action != action)) {
                    var actionName = this.prev_action + 'Termination';
                    $.shop.trace('$.tutorial.preExecute', [actionName, action]);
                    if (typeof(this[actionName]) == 'function') {
                        this[actionName].apply(this, []);
                    }
                }
                this.prev_action = action;

                $('body > .dialog').trigger('close').remove();

            } catch (e) {
                $.shop.error('preExecute error: ' + e.message, e);
            }
        },

        defaultAction: function () {
            this.load('?module=tutorial&action=profit');
            $.wa.setHash(`/${this.hash}/`);
        },

        installAction: function () {
            this.load('?module=tutorial&action=install');
        },
    };

})(jQuery);
