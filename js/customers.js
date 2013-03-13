(function ($) { "use strict";
    $.storage = new $.store();
    $.customers = {
        options: {},

        // last list view user has visited: {title: "...", hash: "..."}
        lastView: null,

        init: function (options) {
            var that = this;
            that.options = options;
            if (typeof($.History) != "undefined") {
                $.History.bind(function () {
                    that.dispatch();
                });
            }
            $.wa.errorHandler = function (xhr) {
                if ((xhr.status === 403) || (xhr.status === 404) ) {
                    var text = $(xhr.responseText);
                    if (text.find('.dialog-content').length) {
                        text = $('<div class="block double-padded"></div>').append(text.find('.dialog-content *'));

                    } else {
                        text = $('<div class="block double-padded"></div>').append(text.find(':not(style)'));
                    }
                    $("#s-content").empty().append(text);
                    that.onPageNotFound();
                    return false;
                }
                return true;
            };
            var hash = this.getHash();
            if (hash === '#/' || !hash) {
                this.dispatch();
            } else {
                $.wa.setHash(hash);
            }
            this.lastView = $.storage.get('shop/customers/lastview') || {
                title: '',
                hash: ''
            };

            this.initSearch();
        },

        /** Global customers search above content block */
        initSearch: function() {
            var search_field = $('#s-customers-search');

            var search = function() {
                var query = this.value;
                location.hash = '#/search/0/' + encodeURIComponent(query);
                $(this).autocomplete("close");
                return false;
            };

            // Test if HTML5 search event is supported
            var isSupported = ('onsearch' in search_field[0]); // works for everyone except firefox
            if (!isSupported) {
                // firefox testing
                search_field[0].setAttribute('onsearch', 'return;');
                isSupported = typeof search_field[0]['onsearch'] == 'function';
            }

            // Use HTML5 search event if suppotred. Otherwise fallback to keydown.
            if (isSupported) {
                search_field.unbind('search').bind('search', search);
            } else {
                search_field.unbind('keydown').bind('keydown', function(event) {
                    if (event.keyCode == 13 || event.keyCode == 10) {
                        return search.call(this);
                    }
                });
            }

            // Use jQuery autocomplete to show suggestions.
            search_field.autocomplete({
                source: '?action=autocomplete&type=customer',
                minLength: 3,
                delay: 300,
                select: function(event, ui) {
                    $.wa.setHash('#/id/' + ui.item.id);
                    return false;
                }
            });
        },

        // * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
        // *   Dispatch-related
        // * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *

        // if this is > 0 then this.dispatch() decrements it and ignores a call
        skipDispatch: 0,

        /** Cancel the next n automatic dispatches when window.location.hash changes */
        stopDispatch: function (n) {
            this.skipDispatch = n;
        },

        /** Force reload current hash-based 'page'. */
        redispatch: function() {
            this.currentHash = null;
            this.dispatch();
        },

        /**
          * Called automatically when window.location.hash changes.
          * Call a corresponding handler by concatenating leading non-int parts of hash,
          * e.g. for #/aaa/bbb/111/dd/12/ee/ff
          * a method $.customers.AaaBbbAction('111', 'dd', '12', 'ee', 'ff') will be called.
          */
        dispatch: function (hash) {
            if (this.skipDispatch > 0) {
                this.skipDispatch--;
                return false;
            }

            if (hash === undefined || hash === null) {
                hash = this.getHash();
            }
            if (this.currentHash == hash) {
                return;
            }

            this.currentHash = hash;
            hash = hash.replace('#/', '');

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
                            } else if (parseInt(h, 10) != h && h.indexOf('=') == -1) {
                                actionName += h.substr(0,1).toUpperCase() + h.substr(1);
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
                    this.preExecute(actionName);
                    if (typeof(this[actionName + 'Action']) == 'function') {
                        $.shop.trace('$.customers.dispatch',[actionName + 'Action',attr]);
                        this[actionName + 'Action'].apply(this, attr);
                    } else {
                        $.shop.error('Invalid action name:', actionName+'Action');
                    }
                    this.postExecute(actionName);
                } else {
                    this.preExecute();
                    this.defaultAction();
                    this.postExecute();
                }
            } else {
                this.preExecute();
                this.defaultAction();
                this.postExecute();
            }

            this.highlightSidebar();
        },

        preExecute: function(actionName, attr) {
        },

        postExecute: function(actionName, attr) {
            this.actionName = actionName;
        },

        defaultAction: function () {
            this.allAction();
        },

        //
        // Pages
        //

        allAction: function(dummy, order) {
            order = this.getSortOrder(order);
            this.load('?module=customers&action=list'+order);
            $('#s-sidebar a[href="#/all/"]').parent().addClass('selected');
        },

        categoryAction: function(id, order) {
            order = this.getSortOrder(order);
            this.load('?module=customers&action=list&category='+id+order);
        },

        searchAction: function(dummy, str, order) {
            order = this.getSortOrder(order);
            this.load('?module=customers&action=list&search='+str+order);
        },

        idAction: function(id) {
            this.load('?module=customers&action=info&id='+id);
        },

        addAction: function() {
            this.load('?module=customers&action=add');
        },

        editcategoryAction: function(id) {
            this.load('?module=customers&action=categoryEditor&id='+(id || ''));
        },

        //
        // Helpers
        //

        getSortOrder: function(order) {
            if (!order) {
                order = $.storage.get('shop/customers/sort_order');
            }
            if (order) {
                return '&order='+order;
            } else {
                return '';
            }
        },

        reloadSidebar: function() {
            this.load('?module=customers&action=sidebar', { content: $('#s-sidebar'), check: false }, function() {
                $.customers.highlightSidebar();
            });
        },

        /** Add .selected css class to li with <a> whose href attribute matches current hash.
          * If no such <a> found, then the first partial match is highlighted.
          * Hashes are compared after this.cleanHash() applied to them. */
        highlightSidebar: function(hash) {
            var currentHash = this.cleanHash(hash || window.location.hash);
            var partialMatch = false;
            var partialMatchLength = 2;
            var match = false;
            $('#s-sidebar a').each(function(k, v) {
                v = $(v);
                if (!v.attr('href')) {
                    return;
                }
                var h = $.customers.cleanHash(v.attr('href'));

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
                $('#s-sidebar .selected').removeClass('selected');

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
            } else if (!hash && this.lastView && this.lastView.hash) {
                // When no match found, try to highlight based on last view
                this.highlightSidebar(this.lastView.hash);
            }
        },

        /** Current hash */
        getHash: function () {
            return this.cleanHash();
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

        load: function (url, options, fn) {
            if (typeof options === 'function') {
                fn = options;
                options = {};
            } else {
                options = options || {};
            }
            var r = Math.random();
            this.random = r;
            var self = this;
            return  $.get(url, function(result) {
                if ((typeof options.check === 'undefined' || options.check) && self.random != r) {
                    // too late: user clicked something else.
                    return;
                }
                (options.content || $("#s-content")).removeClass('bordered-left').html(result);
                if (typeof fn === 'function') {
                    fn.call(this);
                }
                $('html, body').animate({scrollTop:0}, 200);
                $('.level2').show();
                $('#s-sidebar').width(200).show();
            });
        },

        onPageNotFound: function() {
            this.allAction();
        }
    };
})(jQuery);