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
            this.initFinishTutorial();
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
                        text = $('<div class="block double-padded"></div>').append(text.find('.dialog-content *'));

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

        initFinishTutorial: function() {
            $('#wa-app').on('click', '.finish-tutorial', function() {
                $.post('?module=tutorial&action=done', function() {
                    $.tutorial.forceHash('#/products/');
                    window.location.search = '?action=products';
                });
                return false;
            });
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
            if (hash === undefined) {
                hash = window.location.hash;
            }
            hash = $.tutorial.cleanHash(hash).replace(/(^[^#]*#\/*|\/+$)/g, '');
            /* fix syntax highlight */

            if (this.hash === hash) {
                return;
            }
            this.hash = hash;

            try {
                if (hash) {
                    hash = hash.split('/');
                    if (hash[0]) {
                        var actionName = "";
                        var attrMarker = hash.length;
                        for (var i = 0; i < hash.length; i++) {
                            var h = hash[i];
                            if (i < 2) {
                                if (i === 0) {
                                    actionName = h;
                                } else if ({page:1}[actionName]) {
                                    attrMarker = i;
                                    break;
                                } else if (parseInt(h, 10) != h && h.indexOf('=') == -1) {
                                    actionName += h.substr(0, 1).toUpperCase() + h.substr(1);
                                } else {
                                    attrMarker = i;
                                    break;
                                }
                            } else {
                                attrMarker = i;
                                break;
                            }
                        }
                        var attr = hash.slice(attrMarker);
                        this.preExecute(actionName, attr);
                        if (typeof(this[actionName + 'Action']) == 'function') {
                            $.shop.trace('$.tutorial.dispatch', [actionName + 'Action', attr]);
                            this[actionName + 'Action'].apply(this, attr);
                        } else {
                            $.shop.error('Invalid action name:', actionName + 'Action');
                        }
                    } else {
                        this.preExecute();
                        this.defaultAction();
                    }
                } else {
                    this.preExecute();
                    this.defaultAction();
                }
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

                $.tutorial.highlightSidebar();
            } catch (e) {
                $.shop.error('preExecute error: ' + e.message, e);
            }
        },

        defaultAction: function () {
            // Find first non-checked action and load it.
            var $li = $('#tutorial-actions li:not(.complete):first');
            if ($li.length) {
                $.wa.setHash($li.find('a[href]').attr('href'));
            } else {
                this.profitAction();
            }
        },

        installAction: function () {
            this.load('?module=tutorial&action=install');
        },

        productsAction: function () {
            this.load('?module=tutorial&action=products');
        },

        pageAction: function (page) {
            this.load('?module=tutorial&action=custom&page='+page);
        },

        designAction: function () {
            this.load('?module=tutorial&action=design');
        },

        checkoutAction: function () {
            this.load('?module=tutorial&action=checkout');
        },

        profitAction: function () {
            this.load('?module=tutorial&action=profit');
        },

        /** Make sure hash has a # in the begining and exactly one / at the end.
          * For empty hashes (including #, #/, #// etc.) return an empty string.
          * Otherwise, return the cleaned hash.
          * When hash is not specified, current hash is used. */
        cleanHash: function (hash) {
            if(typeof hash == 'undefined') {
                hash = window.location.hash.toString();
            }

            if (!hash.length) {
                hash = ''+hash;
            }
            while (hash.length > 0 && hash[hash.length-1] === '/') {
                hash = hash.substr(0, hash.length-1);
            }
            hash += '/';

            if (hash[0] != '#') {
                if (hash[0] != '/') {
                    hash = '/' + hash;
                }
                hash = '#' + hash;
            } else if (hash[1] && hash[1] != '/') {
                hash = '#/' + hash.substr(1);
            }

            if(hash == '#/') {
                return '';
            }

            return hash;
        },

        /** Add .selected css class to li with <a> whose href attribute matches current hash.
          * If no such <a> found, then the first partial match is highlighted.
          * Hashes are compared after this.cleanHash() applied to them. */
        highlightSidebar: function(hash) {
            var currentHash = $.tutorial.cleanHash(hash || window.location.hash);
            var partialMatch = false;
            var partialMatchLength = 2;
            var match = false;
            var $sidebar = $('#maincontent > .s-tutorial > .sidebar');
            $sidebar.find('a').each(function(k, v) {
                v = $(v);
                if (!v.attr('href')) {
                    return;
                }
                var h = $.tutorial.cleanHash(v.attr('href'));

                // Perfect match?
                if (h == currentHash) {
                    match = v;
                    return false;
                }

                // Partial match? (e.g. for urls that differ in paging only)
                if (h.length > partialMatchLength && currentHash.substr(0, h.length) === h) {
                    partialMatch = v;
                    partialMatchLength = h.length;
                }
            });

            if (!match && partialMatch) {
                match = partialMatch;
            }

            if (match) {
                $sidebar.find('.selected').removeClass('selected');

                // Only highlight items that are inside <li>, but outside of dropdown menus
                if (match.parents('li').length > 0 && match.parents('ul.dropdown').size() <= 0) {
                    var p = match.parent();
                    while(p.size() > 0 && p[0].tagName.toLowerCase() != 'li') {
                        p = p.parent();
                    }
                    if (p.size() > 0) {
                        p.addClass('selected');
                    }
                }
            }
        }
    };

})(jQuery);
