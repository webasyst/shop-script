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
                $.products.jsonPost('?module=products&action=moveList', data, options.success, function(response) {
                    if (response.errors && response.errors.length) {
                        $.each(response.errors, function(i, error) {
                            var text = null;
                            if (typeof error === "string") { text = error; }
                            if (typeof error.text === "string") { text = error.text; }
                            if (text) { renderError(text); }
                        });
                    }
                    return (typeof options.error === "function" ? options.error(response) : options.error);
                });

                function renderError(text) {
                    var dialog_html = "<div class=\"dialog width650px height250px small\">\n" +
                        "    <div class=\"dialog-background\"></div>\n" +
                        "        <div class=\"dialog-window\">\n" +
                        "        <div class=\"dialog-content\">\n" +
                        "            <div class=\"dialog-content-indent\">\n" +
                        "                <p>%text%</p>\n" +
                        "            </div>\n" +
                        "        </div>\n" +
                        "        <div class=\"dialog-buttons\">\n" +
                        "            <div class=\"dialog-buttons-gradient\">\n" +
                        "                <input class=\"button gray cancel\" type=\"button\" value=\"%button_text%\">\n" +
                        "            </div>\n" +
                        "        </div>\n" +
                        "    </div>\n" +
                        "</div>";

                    dialog_html = dialog_html
                        .replace("%text%", text)
                        .replace("%button_text%", $.wa.locale["Close"]);

                    var $dialog = $(dialog_html);

                    $dialog.waDialog({
                        onClose: function() {
                            $dialog.remove();
                        }
                    });
                }
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
                        '</a> ';
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
                    var max_width = 1000;
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

                if (type === 'category') {
                    shopDialogProductsCategory.staticDialog($.product_list.collection_hash[1], parent_id, 'new');
                } else {
                    shopDialogProductsSet.staticDialog($.product_list.collection_hash[1], 'new');
                }

                return false;
            });
        },

        updateItemInCategoryList: function(r, hash) {

            var li = $('#category-' + r.data.id);

            li.find('.name:first').html(r.data.name);

            if ($.isArray(r.data.routes) && r.data.routes.length) {
                li.find('.routes:first').html(' ' + r.data.routes.join(' '));
            } else {
                li.find('.routes:first').html(' ');
            }

            if (r.data.subcategories_updated) {
                if (r.data.status == '0') {
                    li.find('a').addClass('gray');
                } else if (r.data.status == '1') {
                    li.find('a').removeClass('gray');
                }
            } else {
                if (r.data.status == '0') {
                    li.children('a').addClass('gray');
                } else if (r.data.status == '1') {
                    li.children('a').removeClass('gray');
                }
            }
            li.find('.id:first').html(r.data.id);
            li.attr('id', 'category-' + r.data.id);
            li.find('a').attr('href', hash);

            return null;
        },

        createNewElementInList: function (new_item, type) {
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

        },

        sortCategoryDialog: function () {
            $('#s-products-sort-categories').waDialog({
                disableButtonsOnSubmit: true,

                onSubmit: function (d) {
                    var form = $(this);

                }
            });
        },

        initCategoryDescriptionWysiwyg: function (d) {
            var field = d.find('.field.description');
            field.find('i').hide();
            field.find('.s-editor-core-wrapper').show();
            var height = (d.find('.dialog-window').height() * 0.8) || 350;
            var $textarea = $('#s-category-description-content');
            $textarea.waEditor({
                lang: wa_lang,
                toolbarFixed: false,
                maxHeight: height,
                minHeight: height,
                modification_wysiwyg_msg: $textarea.data('modification-wysiwyg-msg'),
                uploadFields: d.data('uploadFields')
            });
        }
    };
})(jQuery);