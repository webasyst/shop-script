/**
 * 
 */
// (function($) {
var shop_plugin_list = $('#plugin-list');
shop_plugin_list.sortable({
    'containment' : shop_plugin_list.parent(),
    'distance' : 5,
    'tolerance' : 'pointer',
    'update' : function(event, ui) {
        $.ajax({
            type : 'POST',
            url : '?module=plugins&action=sort',
            data : {
                slug : $(ui.item).attr('id').replace(/^plugin-/, ''),
                pos : $(ui.item).index()
            },
            success : function(data, textStatus, jqXHR) {
                if (!data || !data.status || (data.status != "ok") || !data.data || (data.data != "ok")) {
                    shop_plugin_list.sortable('cancel');
                }
            },
            error : function() {
                shop_plugin_list.sortable('cancel');
            }
        });
    }
});

/**
 * @
 */

$.plugins = {
    options : {
        'loading' : '<i class="icon16 loading"></i>',
        'path' : '#/'
    },
    path : {
        'plugin' : false,
        'tail' : null,
        'params' : {}
    },

    ready : false,
    menu : null,

    /**
     * @param {} options
     */
    init : function(options) {
        this.options = $.extend(this.options, options || {});
        if (!this.ready) {
            this.ready = true;
            this.menu = $('#plugin-list');

            // Set up AJAX to never use cache
            $.ajaxSetup({
                cache : false
            });

            if (typeof($.History) != "undefined") {
                $.History.bind(function() {
                    $.plugins.dispatch();
                });
            }
            $.wa.errorHandler = function(xhr) {
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
        }
    },

    /**
     * 
     * @param {String} path
     * @return { 'section':String, 'tail':String,'raw':String,'params':object }
     */
    parsePath : function(path) {
        path = path.replace(/^.*#\//, '');
        return {
            'plugin' : path.replace(/\/.*$/, '') || '',
            'tail' : path.replace(/^[^\/]+\//, '').replace(/[\w_\-]+=.*$/, '').replace(/\/$/, ''),
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
    dispatch : function(hash) {

        if (hash === undefined) {
            hash = window.location.hash;
        }
        if (!hash) {
            var $plugin = this.menu.find('li:first > a:first');
            if ($plugin.length) {
                window.location.hash = hash = $plugin.attr('href');
            }
        }

        var path = this.parsePath(hash.replace(/^[^#]*#\/*/, ''));
        this.path.dispatch = path;

        $.shop.trace('$.plugins.dispatch ' + this.path.plugin + ' -> ' + path.plugin + ' # ' + path.tail);

        /* change plugins section */
        if (path.plugin && (path.plugin != this.path.plugin)) {
            var $content = $('#s-plugins-content');
            this.path.tail = null;
            $content.html(this.options.loading);

            var self = this;

            if ($("#plugin-" + path.plugin).attr('data-settings')) {
                var url = '?plugin=' + path.plugin + '&module=settings';
            } else {
                var url = '?module=plugins&id=' + path.plugin;
            }

            $.shop.trace('$.plugins.dispatch: Load URL', [url, $content.length]);
            $content.load(url, function() {
                self.path.plugin = path.plugin || self.path.plugin;
                self.menu.find('li.selected').removeClass('selected');
                self.menu.find('a[href*="\\#\/' + self.path.plugin + '\/"]').parents('li').addClass('selected');
            });
            return true;
        }
    }
};

// })(jQuery);
