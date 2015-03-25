(function($) {

    // private helper methods in closure

    var getId = function(el) {
        var regexp = /^category-(.*?)-handler$/;
        return regexp.test(el.attr('id')) ?
                el.attr('id').replace(regexp, function() {
                    return parseInt(arguments[1], 10) || 0;
                }) : 0;
    };

    var getContext = function(el) {
        if (!getId(el)) {
            var p = el.parent(), t = p.next(), u = t.find('ul:first');
        } else {
            var p = el.parents('li:not(.drag-newposition):first'), t = p.find('ul:first'), u = t;
        }
        return {
            parent: p,
            target: t,
            ul: u
        };
    };

    var onCollapse = function(el, func) {
        var context = getContext(el);
        if (context.parent.attr('data-type') == 'category' && !context.parent.hasClass('dynamic')) {
            context.parent.trigger('count_subtree', true);
        }
        el.removeClass('darr').addClass('rarr');
        context.target.hide();
        if (typeof func === 'function') {
            func(el);
        }
    };

    var onExpand = function(el, func) {
        var context = getContext(el);
        if (context.parent.attr('data-type') == 'category') {
            context.parent.trigger('count_subtree', false);
        }
        el.removeClass('rarr').addClass('darr');
        context.target.show();
        if (typeof func === 'function') {
            func(el);
        }
    };

    /**
     * @param context
     * @param {Boolean} status
     */
    var setLoadingIcon = function(context, status) {
        var icon = context.parent.find('.loading:first');
        var counters = context.parent.find('.counters');
        if (status) {
            icon.show();
            counters.hide();
        } else {
            icon.hide();
            counters.show();
        }
    };

    var collapse = function(el, func) {
        onCollapse(el, func);
        $.get('?action=categoryExpand&id=' + getId(el) + '&collapsed=1');
    };

    var expand = function(el, onExpandFunc, afterExpandFunc) {
        if (el.data('loading_content')) {
            return;
        }
        var context = getContext(el);
        if (!context.ul.length) {
            setLoadingIcon(context, true);
        } else {
            onExpand(el, onExpandFunc);
        }

        var loading_content = !context.ul.length;
        el.data('loading_content', loading_content);
        $.get('?action=categoryExpand&id=' + getId(el) + (loading_content ? '&tree=1' : ''),
            function(html) {
                if (loading_content) {
                    if (context.target.length) {
                        context.target.append(html);
                    } else {
                        context.parent.append(html);
                    }
                    setLoadingIcon(context, false);
                    onExpand(el, onExpandFunc);
                    el.data('loading_content', false);
                    if (typeof afterExpandFunc === 'function') {
                        afterExpandFunc();
                    }
                } else {
                    if (typeof afterExpandFunc === 'function') {
                        afterExpandFunc();
                    }
                }
            }
        );
    };

    $.categories_tree = {

        init: function() {
            $('#s-category-list-block').off('click', '.collapse-handler-ajax').on('click', '.collapse-handler-ajax', function() {
                var self = $(this);
                if (self.hasClass('darr')) {
                    collapse(self);
                } else {
                    expand(self);
                }
            });
            $('#s-category-list-block .heading').off('click').click(function(e) {
                var $collapse_handler = $(this).find('.collapse-handler-ajax');
                if (!$collapse_handler.is(e.target)) {
                    $collapse_handler.click();
                }
            });
        },

        collapse: function(handler, func) {
            handler = $(handler);
            if (handler.hasClass('darr')) {
                collapse(handler, func);
            } else if (typeof func === 'function') {
                func(handler);
            }
        },

        expand: function(handler, onExpand, afterExpand) {
            handler = $(handler);
            if (handler.hasClass('rarr')) {
                expand(handler, onExpand, afterExpand);
            } else {
                if (typeof onExpand === 'function') {
                    onExpand(handler);
                }
                if (typeof afterExpand === 'function') {
                    afterExpand(handler);
                }
            }
        },

        isCollapsed: function(handler) {
            return $(handler).hasClass('rarr');
        },

        setExpanded: function(category_id) {
            $.get('?action=categoryExpand&id=' + category_id);
        },

        setCollapsed: function(category_id) {
            $.get('?action=categoryExpand&id=' + category_id + '&collapsed=1');
        },

        getHandlerByCategoryId: function(category_id) {
            var handler = $();
            category_id = parseInt(category_id, 10) || 0;
            if (!category_id) {
                handler = $('#s-category-list-handler');
            } else {
                handler = $('#category-' + category_id + '-handler');
            }
            return handler;
        }
    };
})(jQuery);
