(function ($) {
    $.product_sidebar = {
        options: {},
        init: function () {
            var sidebar = $('#s-sidebar');

            $.product_dragndrop.init({
                collections: true
            }).bind('move_list', function (options) {
                if (!options.type) {
                    if (typeof options.error === 'function') {
                        options.error('Unknown list type');
                    }
                    return;
                }
                var data = {
                    id: options.id,
                    type: options.type,
                    parent_id: options.parent_id || 0
                };
                if (options.before_id) {
                    data.before_id = options.before_id;
                }
                $.products.jsonPost('?module=products&action=moveList', data, options.success, options.error);
            });

            // SIDEBAR CUSTOM EVENT HANDLERS

            sidebar.off('add', '.s-collection-list ul').
                on('add', '.s-collection-list ul',
                /**
                 * @param {Object} e jquery event
                 * @param {Object} item describes inserting item. Will be passed to template
                 * @param {String} type 'category', 'set'
                 * @param {Boolean} replace if item exists already replace it or not?
                 */
                function (e, item, type, replace) {
                    var self = $(this), parent = self.parents('.s-collection-list:first');
                    var tmp = $('<ul></ul>');
                    tmp.append(tmpl('template-sidebar-list-item', {
                        type: type,
                        item: item
                    }));

                    var new_item = tmp.children(':not(.drag-newposition):first');
                    var id = new_item.attr('id');
                    var old_item = self.find('#' + id);
                    var children = tmp.children();

                    if (old_item.length) {
                        if (replace) {
                            old_item.replaceWith(new_item);
                        }
                    } else {
                        self.prepend(children).show();
                    }

                    children.each(function () {
                        var item = $(this);
                        if (item.hasClass('dr')) {
                            item.find('a').mouseover();
                        } else {
                            item.mouseover();
                        }
                    });
                    self.find('.drag-newposition').css({
                        height: '2px'
                    }).removeClass('dragging');

                    parent.find('.s-empty-list').hide();

                    tmp.remove();

                    return false;
                }
            );

            sidebar.unbind('update').bind('update', function (e, lists) {
                for (var type in lists) {
                    if (type == 'all') {
                        $('#s-all-products').find('.count:first').text(lists[type].count);
                        continue;
                    }
                    var prefix = '#' + type + '-';
                    for (var id in lists[type]) {
                        $(prefix + id).find('.count:first').text(lists[type][id].count);
                    }
                }
                return false;
            });

            $('#s-tag-cloud').unbind('update').bind('update', function (e, tag_cloud) {
                // redraw tag cloud
                var html = '<ul class="tags">' +
                    '<li class="block align-center">';
                for (var tag_id in tag_cloud) {
                    var tag = tag_cloud[tag_id];
                    html +=
                        '<a href="' + '#/products/tag=' + tag.uri_name +
                        '/" style="font-size: ' + tag.size +
                        '%; opacity: ' + tag.opacity +
                        '"  data-id="' + tag.id +
                        '"  class="s-product-list">' + tag.name +
                        '</a>';
                }
                html += '</li></ul>';
                $('#s-tag-cloud').html(html).parents('.block:first').show();
                return false;
            });

            sidebar.off('count_subtree', '.s-collection-list li').
                on('count_subtree', '.s-collection-list li',
                function (e, collapsed) {
                    var item = $(this);
                    if (typeof collapsed === 'undefined') {
                        collapsed = item.find('i.collapse-handler-ajax').hasClass('rarr');
                    }

                    // see update_counters also
                    var counter = item.find('>.counters .count:not(.subtree)');
                    var subtree_counter = item.find('>.counters .subtree');
                    if (!subtree_counter.length) {
                        subtree_counter = counter.clone().addClass('subtree').hide();
                        counter.after(subtree_counter);
                    }
                    if (collapsed) {
                        counter.hide();
                        subtree_counter.show();
                    } else {
                        subtree_counter.hide();
                        counter.show();
                    }
                    return false;
                }
            );

            sidebar.off('update_counters', '.s-collection-list li').
                on('update_counters', '.s-collection-list li',
                function (e, counts) {
                    var item = $(this);
                    // see count_subtree also
                    var counter = item.find('>.count:not(.subtree)');
                    var subtree_counter = item.find('>.subtree');
                    if (!subtree_counter.length) {
                        subtree_counter = counter.clone().addClass('subtree').hide();
                        counter.after(subtree_counter);
                    }

                    // update counters if proper key exists
                    if (typeof counts.item !== 'undefined') {
                        counter.text(parseInt(counts.item, 10) || 0);
                    }
                    if (typeof counts.subtree !== 'undefined') {
                        subtree_counter.text(parseInt(counts.subtree, 10) || 0);
                    }

                    return false;
                }
            );

            var arrows_panel = sidebar.find('#s-category-list-widen-arrows');
            arrows_panel.find('a.arrow').unbind('click').
                bind('click', function () {
                    var max_width = 400;
                    var min_width = 200;
                    var cls = sidebar.attr('class');
                    var width = 0;

                    var m = cls.match(/left([\d]{2,3})px/);
                    if (m && m[1] && (width = parseInt(m[1]))) {
                        var new_width = width + ($(this).is('.right') ? 50 : -50);
                        new_width = Math.max(Math.min(new_width, max_width), min_width);

                        if (new_width != width) {

                            arrows_panel.css({'width': new_width.toString() + 'px'});

                            var replace = ['left' + width + 'px', 'left' + new_width + 'px'];
                            sidebar.attr('class', cls.replace(replace[0], replace[1]));
                            sidebar.css('width', '');

                            var content = $('#s-content');
                            cls = content.attr('class');
                            content.attr('class', cls.replace(replace[0], replace[1]));
                            content.css('margin-left', '');

                            if ($.product) {
                                $.product.setOptions({
                                    'sidebar_width': new_width
                                });
                            }

                            $.shop.jsonPost('?action=sidebarSaveWidth', {width: new_width});
                            sidebar.trigger('change_width', [new_width]);

                        }
                    }

                    return false;
                });

            sidebar.off('click', '#s-forcesort-by-name').on('click', '#s-forcesort-by-name', function () {
                $.product_sidebar.sortCategoryDialog();
                return false;
            });

            sidebar.off('click', '.s-new-list').on('click', '.s-new-list', function () {
                var self = $(this);
                var id = self.attr('id');
                var parent_id = 0;
                var type;
                if (id) {
                    type = id.replace('s-new-', '');
                } else {
                    var splited = self.parents('li.dr:first').attr('id').split('-');
                    type = splited[0];
                    parent_id = splited[1];
                }
                $.product_sidebar.createListDialog(type, parent_id, function (new_item, type) {
                    var ctnr = $('#s-' + type + '-list');
                    var list = ctnr.find('ul:first');
                    if (!list.length) {
                        ctnr.prepend(
                            '<ul class="menu-v with-icons"><li class="drag-newposition" data-type="' + type + '"></li></ul>'
                        );
                        ctnr.find('.drag-newposition').mouseover();  // init droppable
                        list = ctnr.find('ul:first');
                    }

                    var parent_id = parseInt(new_item.parent_id, 10) || 0;
                    var handler = $.categories_tree.getHandlerByCategoryId(parent_id);

                    var add = function () {
                        if (parent_id) {
                            var parent = list.find('#' + type + '-' + new_item.parent_id);
                            if (!parent.find('>.collapse-handler-ajax').length) {
                                parent.append('<ul class="menu-v with-icons dr"><li class="drag-newposition" data-type="' + type + '"></li></ul>');
                                parent.find('.drag-newposition').mouseover(); // init droppable
                                parent.find('>a').before(
                                    '<i class="icon16 darr overhanging collapse-handler-ajax" id="' +
                                    type + '-' + parent_id + '-handler' +
                                    '"></i>'
                                );
                                $.categories_tree.setExpanded(parent_id);
                            }
                            list = parent.find('ul:first');
                        }
                        list.trigger('add', [new_item, type]);
                    };

                    if (type == 'category') {
                        $.categories_tree.expand(handler, function () {
                            add();
                        });
                    } else {
                        add();
                    }

                });
                return false;
            });
        },

        sortCategoryDialog: function () {
            $('#s-products-sort-categories').waDialog({
                disableButtonsOnSubmit: true,

                onSubmit: function (d) {
                    var form = $(this);

                }
            });
        },

        createListDialog: function (type, parent_id, onCreate) {
            var showDialog = function () {

                // remove conflict dialog
                var conflict_dialog = $('#s-product-list-settings-dialog');
                if (conflict_dialog.length) {
                    conflict_dialog.parent().remove();
                    conflict_dialog.remove();
                }

                $('#s-product-list-create-dialog').waDialog({
                    esc: false,
                    disableButtonsOnSubmit: true,
                    onLoad: function (d) {
                        if ($('#s-category-description-content').length) {
                            $.product_sidebar.initCategoryDescriptionWysiwyg($(this));
                        }
                        setTimeout(function () {
                            $("#s-c-product-list-name").focus();
                        }, 50);
                    },
                    onSubmit: function (d) {
                        var form = $(this);
                        var success = function (r) {
                            if (typeof onCreate === 'function') {
                                onCreate(r.data, type);
                            }
                            location.href = '#/products/' + type + '_id=' + r.data.id;
                            d.trigger('close');
                        };
                        var error = function (r) {
                            if (r && r.errors) {
                                var errors = r.errors;
                                for (var name in errors) {
                                    d.find('input[name=' + name + ']').addClass('error').parent().find('.errormsg').text(errors[name]);
                                }
                                return false;
                            }
                        };

                        if ($('#s-category-description-content').length) {
                            $('#s-category-description-content').waEditor('sync');
                        }

                        if (form.find('input:file').length) {
                            $.products._iframePost(form, success, error);
                        } else {
                            $.shop.jsonPost(form.attr('action'), form.serialize(), success, error);
                            return false;
                        }
                    }
                });
            };
            var d = $('#s-product-list-create-dialog');
            var p;
            if (!d.length) {
                p = $('<div></div>').appendTo('body');
            } else {
                p = d.parent();
            }
            p.load('?module=dialog&action=productListCreate&type=' + type + '&parent_id=' + parent_id, showDialog);
        },

        initCategoryDescriptionWysiwyg: function (d) {
            var field = d.find('.field.description');
            field.find('i').hide();
            field.find('.s-editor-core-wrapper').show();
            var height = (d.find('.dialog-window').height() * 0.8) || 350;
            $('#s-category-description-content').waEditor({
                lang: wa_lang,
                toolbarFixed: false,
                maxHeight: height,
                minHeight: height,
                uploadFields: d.data('uploadFields')
            });
        }

    };
})(jQuery);