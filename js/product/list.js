(function ($) {
    $.product_list = {
        /**
         * {Object}
         */
        options: {},

        /**
         * {Array} Hash of list(collection)
         */
        collection_hash: [],

        /**
         * {String} Param in url for specification list(collection)
         */
        collection_param: '',

        /**
         * {Number}
         */
        total_count: 0,

        /**
         * {Number}
         */
        category_count: 0,

        /**
         * {String|null} Sorting of list (name, rate, datetime, etc.)
         */
        sort: null,

        /**
         * {String|null} Ordering or sorting (asc, desc)
         */
        order: null,

        /**
         * {Object|null} Jquery object related to list container
         */
        container: null,

        /**
         * {Object|null} Jquery object related to right toolbar
         */
        toolbar: null,

        /**
         * {Boolean}
         */
        sortable: true,

        cached: {},

        /**
         * {Oject} Manager of fixed blocks
         * */
        fixed_blocks: {},

        init: function (options) {
            var canonical_hash = [];
            options.collection_param && canonical_hash.push(options.collection_param);
            canonical_hash.push('view='+options.view);
            options.sort && canonical_hash.push('sort='+options.sort);
            options.order && canonical_hash.push('order='+options.order);
            options.page && options.page > 1 && canonical_hash.push('page='+options.page);
            canonical_hash = '#/products/' + canonical_hash.join('&');
            $.products.forceHash(canonical_hash);

            try {
                this.options = options;
                this.container = $('#product-list');
                this.toolbar = $('.js-product-list-toolbar');
                this.sidebar = $('#s-sidebar');
                var products = this.options.products;
                delete this.options.products;
                this.total_count = this.options.total_count || 0;
                this.category_count = parseInt(this.options.category_count, 10);
                if (isNaN(this.category_count)) {
                    this.category_count = this.total_count;
                }
                this.sortable = typeof options.sortable === 'undefined' ? this.sortable : options.sortable;

                this.sort = options.sort || null;

                if (options.enable_2x) {
                    this.container.on('append_product_list', function() {
                        $.fn.retina && $.product_list.container.find('img').not(".is-empty").retina();
                    });
                }

                if (products && options.view) {
                    try {
                        this.container.append(tmpl('template-product-list-' + this.options.view, {
                            products: products,
                            sort: this.sort,
                            primary_currency: this.options.primary_currency,
                            stocks: this.options.stocks
                        }, this.options.view != 'thumbs'));
                        this.container.trigger('append_product_list', [products]);
                    } catch (e) {
                        console.log('Error: ' + e.message);
                        return this;
                    }
                    delete options.products;
                }

                if ($.isArray(this.options.collection_hash) && this.options.collection_hash.length) {
                    this.collection_hash = this.options.collection_hash;
                } else {
                    this.collection_hash = [];
                }

                if (this.options.collection_param) {
                    this.collection_param = this.options.collection_param;
                } else {
                    this.collection_param = '';
                }

                if (options.order) {
                    this.order = options.order;
                }
                if (this.options.lazy_loading) {
                    this.initLazyLoad(this.options.lazy_loading);
                } else {
                    var hash_with_page = canonical_hash.replace(/&page=\d+/, '') + '&page=';
                    $('.pagination a').each(function () {
                        var m = $(this).attr('href').match(/\?page=(\d+)/);
                        $(this).attr('href', hash_with_page + (m ? m[1] : 1));
                    });
                }

                this.initSelecting();
                this.initView(this.options.view);
                this.initDragndrop();
                // this.initSortingNotice();
                this.rubberTable();

                if (this.options.view == 'table') {
                    this.initInlineEditor();
                }
            } catch (e) {
                $.shop.error('$.product_list.init error: ' + e.message, e);
            }

            return this;
        },

        /**
         * @description see at "checkAlerts" (301 line) in products.js
         * */
        // initSortingNotice: function() {
        //     if ($.storage.get('shop/list-sorting-notice-closed')) {
        //         return;
        //     }
        //     var $notice = $('#custom-backend-order-notice');
        //     if ($notice.length) {
        //         $notice.show().on('click', '.close', function() {
        //             $.storage.set('shop/list-sorting-notice-closed', '1');
        //             $notice.remove();
        //         });
        //     }
        // },

        initLazyLoad: function (options) {
            var count = options.count;
            var offset = count;
            var total_count = this.total_count;

            $(window).lazyLoad('stop'); // stop previous lazy-load implementation

            if (offset < total_count) {
                var self = this;

                var enabled_columns = self.container.find('tr.header th[data-additional-column]').map(function() {
                    return $(this).data('additional-column');
                }).get().join(',');

                $(window).lazyLoad({
                    container: self.container,
                    state: (typeof options.auto === 'undefined' ? true : options.auto) ? 'wake' : 'stop',
                    hash: ['', 'products'], // ['products']
                    load: function () {
                        $(window).lazyLoad('sleep');
                        $('.lazyloading-link').hide();
                        $('.lazyloading-progress').show();
                        $.get('?module=products&action=loadList&offset=' + offset + ('&total_count=' + total_count) +
                        (self.collection_param ? '&' + self.collection_param : '') + (self.sort ? '&sort=' + self.sort : '') +
                        (self.order ? '&order=' + self.order : '') + ('&enabled_columns='+(enabled_columns||'')), function (r) {
                            if (r.status == 'ok' && r.data.count) {
                                offset += r.data.count;
                                var product_list = self.container;
                                try {
                                    self.container.append(tmpl('template-product-list-' + self.options.view, {
                                        products: r.data.products,
                                        check_all: product_list.find('.s-select-all:first').length ? product_list.find('.s-select-all:first').attr('checked') : false,
                                        sort: $.product_list.sort,
                                        primary_currency: $.product_list.options.primary_currency,
                                        stocks: $.product_list.options.stocks
                                    }));
                                    self.container.trigger('append_product_list', [r.data.products]);
                                } catch (e) {
                                    console.log('Error: ' + e.message);
                                    $(window).lazyLoad('stop');
                                    return;
                                }

                                product_list.find('.last').removeClass('last');
                                product_list.find('.product:last').addClass('last');
                                $('.lazyloading-progress-string').text(r.data.progress.loaded + ' ' + r.data.progress.of);

                                $('.lazyloading-progress').hide();
                                $('.lazyloading-chunk').text(r.data.progress.chunk);

                                if (offset >= total_count) {
                                    $(window).lazyLoad('stop');
                                    $('.lazyloading-link').hide();
                                } else {
                                    $('.lazyloading-link').show();
                                    $(window).lazyLoad('wake');
                                }
                            } else if (r.data && !r.data.count) {
                                if (console) {
                                    console.log('Count of again loaded products is 0');
                                    $('.lazyloading-progress').hide();
                                }
                            } else {
                                if (r.errors && console) {
                                    console.log('Error when loading products: ' + r.errors);
                                }
                                $(window).lazyLoad('stop');
                            }
                        }, "json");
                    }
                });
                $('.lazyloading-link').off('click').on('click', function () {
                    $(window).lazyLoad('force');
                    return false;
                });
            }
        },

        initSelecting: function () {
            var product_list = this.container;
            var toolbar = this.toolbar;
            var toolbar_count = toolbar.find('.count');
            var select_all_input = product_list.find('.s-select-all:first');
            var recount = function () {
                var count = parseInt(product_list.find('> .product.selected').length, 10);
                if (count) {
                    toolbar_count.text(count).show();
                } else {
                    toolbar_count.text('').hide();
                }
            };

            // when 'shift' held on prevent default browser selecting
            $(document).keydown(function (e) {
                if (e.keyCode == 16 && !$(e.target).closest('.redactor-box').length) {
                    document.body.onselectstart = function () {
                        return false;
                    };
                }
            }).keyup(function (e) {
                if (e.keyCode == 16) {
                    document.body.onselectstart = null;
                }
            });

            // handler of triggerable 'select' event
            product_list.off('select', '.product').on('select', '.product', function (e, selected, need_count) {
                selected = selected !== undefined ? selected : true;
                need_count = need_count !== undefined ? need_count : true;
                if (selected) {
                    $(this).addClass('selected').find('input:first').attr('checked', true);
                } else {
                    $(this).removeClass('selected').find('input:first').attr('checked', false);
                    if (select_all_input.is(':checked')) {
                        select_all_input.attr('checked', false);
                    }
                }
                if (need_count) {
                    recount();
                }
                return false;
            });

            select_all_input.unbind('select').bind('select', function (e, selected) {
                selected = selected !== undefined ? selected : true;
                var self = $(this);
                if (selected) {
                    self.attr('checked', true);
                    product_list.find('> .product').trigger('select', true, false);
                    toolbar_count.text(self.attr('data-count')).show();
                } else {
                    self.attr('checked', false);
                    product_list.find('> .product').trigger('select', false, false);
                    toolbar_count.text('').hide();
                }
            });

            product_list.off('click', '.product input').on('click', '.product input', function (e, ext) {
                var shiftKey = e.shiftKey, checked = this.checked;
                if (ext) {
                    shiftKey = ext.shiftKey;
                    checked = ext.checked;
                }
                var self = $(this).parents('.product:first');
                if (checked) {
                    self.addClass('selected');
                } else {
                    if (select_all_input.is(':checked')) {
                        select_all_input.attr('checked', false);
                    }
                    self.removeClass('selected');
                }

                if (shiftKey && checked) { // when hold shift
                    // look before current
                    var started = product_list.data('last_checked');
                    if (!started) {
                        started = product_list.find('> .product:first').trigger('select', true);
                    }

                    // try find started before current
                    var found = self.prevAll('.selected[data-product-id=' + started.attr('data-product-id') + ']');
                    var li;
                    if (found.length) {
                        li = self.prev();
                        started = started.get(0);
                        while (li.length && started != li.get(0)) {
                            li.addClass('selected').find('input').attr('checked', true);
                            li = li.prev();
                        }
                    } else {
                        found = self.nextAll('.selected[data-product-id=' + started.attr('data-product-id') + ']');
                        if (found.length) {
                            li = self.next();
                            started = started.get(0);
                            while (li.length && started != li.get(0)) {
                                li.addClass('selected').find('input').attr('checked', true);
                                li = li.next();
                            }
                        }
                    }
                    if (!product_list.data('last_checked') && !found.length) {
                        started.trigger('selected', false);
                    }
                }
                if (checked) {
                    product_list.data('last_checked', self);
                }
                recount();
            });

            // when select product by hand (see below). Firefox version
            // In Firefox shift+click on label don't delegate to corresponding input
            if ($.browser.mozilla) {
                product_list.off('click', '.product label').on('click', '.product label', function (e) {
                    if (!e.shiftKey) {
                        return;
                    }
                    var item = $(e.target), input;
                    if (!$(item).is('label')) {
                        item = $(item).parents('label:first');
                    }
                    input = $('#' + item.attr('for'));
                    input.trigger('click', {
                        shiftKey: e.shiftKey,
                        checked: !input.attr('checked')
                    });
                });
            }
        },

        initView: function (view) {

            try {
                var product_list = this.container;
                var sidebar = this.sidebar;

                if (product_list.find('.s-select-all:first').length) {
                    product_list.find('.s-select-all:first').click(function () {
                        $(this).trigger('select', this.checked);
                    });
                }

                if (view == 'table') {
                    // Click on a table view icon toggles product name view in table: single-lined or multi-lined
                    $('#s-content .list-view-mode-table').click(function() {
                        product_list.toggleClass('single-lined');
                        $.storage.set('shop/product_list/multi-lined', !product_list.hasClass('single-lined'));
                        return false;
                    });
                    if ($.storage.get('shop/product_list/multi-lined')) {
                        product_list.removeClass('single-lined');
                    }
                }

                // var param = 'view=' + view + (this.sort ? '&sort=' + this.sort : '');
                var param = 'view=' + view;
                sidebar.find('.s-collection-list li.dr').each(function () {
                    var self = $(this);
                    self.find('a:first').attr('href', '#/products/' + self.attr('id').replace('-', '_id=') + '&' + param);
                });
                $('#s-products-search').autocomplete('disable').val(this.options.text || '').autocomplete('enable');

                var li_id = 's-all-products',
                    collection_condition = $.product_list.collection_hash.length
                        && $.product_list.collection_hash[0] !== 'search'
                        && $.product_list.collection_hash[0] !== 'tag'
                        && $.product_list.collection_hash[0] !== 'id';
                if (collection_condition) {
                    li_id = $.product_list.collection_hash.join('-');
                }
                $.shop.trace('$.product_list.initView', [view, li_id]);
                sidebar.find('li.selected').removeClass('selected');

                var active_element = sidebar.find('#' + li_id);
                if (active_element.length <= 0 && window.location.hash) {
                    active_element = sidebar.find('a[href="' + window.location.hash + '"]').parent();
                }
                if (active_element.length <= 0) {
                    active_element = sidebar.find('#s-all-products');
                }
                active_element.addClass('selected');
                if (collection_condition) {
                    active_element.find('.count:first').text(this.category_count);
                }

                this.initEditingControls();
                this.initToolbar();

                $(document).trigger('product_list_init_view');
            } catch (e) {
                $.shop.error('$.product_list.initView error: ' + e.message, e);
            }
        },

        highlightInlineEditorCells: function() {

            // Highlight all non-editable cells with a CSS class
            $.product_list.container.find('tr').each(function() {
                var $tr = $(this);
                var has_edit_rights = $tr.data('edit-rights');
                if(!has_edit_rights || $tr.data('min-price') != $tr.data('max-price')) {
                    $tr.find('.s-product-price .editable').filter(function() { return !$(this).data('plugin'); }).addClass('not-editable');
                }
                if(!has_edit_rights || $tr.data('sku-count') != 1) {
                    $tr.find('.s-product-stock .editable').filter(function() { return !$(this).data('plugin'); }).addClass('not-editable');
                }
            });

        },

        initInlineEditor: function() { "use strict";
            var self = this;
            var stock_count = 0;
            if (self.options.stocks && self.options.stocks.length) { stock_count = self.options.stocks.length; }

            $.product_list.highlightInlineEditorCells();
            $.product_list.container.on('append_product_list', function() {
                $.product_list.highlightInlineEditorCells();
            });

            $.product_list.container.on('click', '.s-product-price,.s-product-stock', function() {
                var $td = $(this);
                if ($td.data('plugin')) {
                    return;
                }
                var $tr = $td.closest('tr');
                var is_price_cell = $td.hasClass('s-product-price');

                if (is_price_cell) {
                    if($tr.data('min-price') != $tr.data('max-price')) {
                        return playDeniedAnimation($td, $_('Inline editing is available only for products with a single SKU'));
                    }
                } else {
                    if ($tr.data('sku-count') != '1') {
                        return playDeniedAnimation($td, $_('Inline editing is available only for products with a single SKU'));
                    }
                }

                if (!$tr.data('edit-rights')) {
                    return playDeniedAnimation($td, $_('Insufficient access rights'));
                }

                if ($td.hasClass('editor-on')) {
                    return;//backToReadOnly($td);
                }

                $td.addClass('editor-on').data('read-only-state-html', $td.html());
                $.product_list.container.removeClass('single-lined');

                if ($tr.data('original-price') !== undefined) {
                    // Show the editor right away when we previously loaded all data we need
                    showEditor();
                } else {
                    $td.html('<i class="icon16 loading"></i>');
                    loadInlineEditorData(showEditor);
                }

                function showEditor() {
                    if (is_price_cell) {
                        $td.html(tmpl('template-list-price-editor-one', {
                            product_id: $tr.data('product-id'),
                            currency: $tr.data('currency'),
                            value: $tr.data('original-price')
                        }));
                    } else {
                        var stocks = $tr.data('stocks');
                        if (!stock_count) {
                            if (!stocks || typeof stocks === "object") { stocks = ""; }
                            $td.html(tmpl('template-list-stock-editor-one', {
                                product_id: $tr.data('product-id'),
                                value: stocks
                            }));
                        } else {
                            if (!stocks || typeof stocks !== "object") { stocks = {}; }
                            $td.html(tmpl('template-list-stock-editor-many', {
                                product_id: $tr.data('product-id'),
                                values: stocks
                            }));
                        }
                    }

                    $td.find('.cancel').click(function() {
                        backToReadOnly($td);
                    });

                    var $form = $td.find('form').submit(function() {
                        if ($form.find('.loading').length) {
                            return false;
                        }
                        $form.find(':submit').after('<i class="icon16 loading"></i>');
                        $.post('?module=products&action=inlineSave', $form.serialize(), function(r) {
                            $form.find('.loading').remove();

                            // update the whole line
                            try {
                                r.data && r.data[0] && (r.data[0].alien = $tr.hasClass('s-alien'));
                                var $div = $('<table>').html(tmpl('template-product-list-table', {
                                    products: r.data,
                                    check_all: $.product_list.container.find('.s-select-all:first').prop('checked'),
                                    sort: $.product_list.sort
                                }));
                                $tr.replaceWith($div.find('tr'));
                                self.container.trigger('append_product_list', [r.data]);
                            } catch (e) {
                                console.log('Error: ' + e.message);
                            }
                        }, 'json');
                        return false;
                    });
                    $form.on('dblclick', false);

                    setTimeout(function() {
                        $td.find(':input:visible:first').focus();
                    }, 0);
                }
            });

            // Visually show the user that he cannot edit given table cell
            function playDeniedAnimation($td, reason) {
                if ($td.data('denied-reason') == reason) {
                    $td.data('denied-reason', '');
                    alert(reason);
                } else {
                    $td.data('denied-reason', reason);
                    setTimeout(function() {
                        $td.data('denied-reason', '');
                    }, 3000);

                    $td.wrapInner('<div class="animation-container" style="position:relative;"></div>');
                    var $div = $td.find('.animation-container').animate({ left: '3px' }, 100, function() {
                        $div.animate({ left: '0px' }, 100, function() {
                            $div.animate({ left: '3px' }, 100, function() {
                                $div.animate({ left: '0px' }, 100, function() {
                                    $div.children().first().unwrap();
                                });
                            });
                        });
                    });
                }
            }

            // Turn off editor for given cell
            function backToReadOnly($td) {
                $td.removeClass('editor-on').html($td.data('read-only-state-html'));
                $td.data('read-only-state-html', '');
                $td.data('denied-reason', '');
            }

            // Load data required for inline editors to work, assign them to <tr> and <td> data properties, then call callback
            function loadInlineEditorData(callback) {
                var product_ids = [];
                $.product_list.container.find('tr.product').each(function() {
                    var $tr = $(this);
                    if ($tr.data('original-price') !== undefined) {
                        return;
                    }
                    if (!$tr.data('edit-rights')) {
                        return;
                    }
                    product_ids.push($(this).data('product-id'));
                });

                if (!product_ids.length) {
                    return callback();
                }

                // Load data required and then show the editor
                $.post('?module=products&action=inlineEditorData', { ids: product_ids }, function(r) {
                    if (r.data) {
                        $.product_list.container.find('tr.product').each(function() {
                            var $tr = $(this);
                            var p = r.data[parseInt($tr.data('product-id'), 10)];
                            if (p) {
                                $tr.data('stocks', p.stocks || null);
                                $tr.data('original-price', p.price.replace(/\.?0+$/g, ''));
                            }
                        });
                    }
                    callback();
                }, 'json');
            }
        },

        initEditingControls: function () {
            var list_title = $('#s-product-list-title'),
                type = $.product_list.collection_hash[0],
                save_url = '?module=category&action=save&';

            if (type === 'set') {
                save_url = '?module=set&action=save&'
            }

            if (list_title.hasClass('editable')) {
                list_title.inlineEditable({
                    minSize: {
                        width: 350
                    },
                    maxSize: {
                        width: 600
                    },
                    size: {
                        height: 30
                    },
                    inputClass: 's-title-h1-edit',
                    beforeMakeEditable: function () {
                        $('.s-product-list-manage').hide();
                    },
                    afterBackReadable: function (input, data) {
                        $('.s-product-list-manage').show();
                        if (!data.changed) {
                            return false;
                        }
                        $.shop.jsonPost(save_url + $.product_list.collection_param + '&edit=name', {
                            name: $(input).val()
                        }, function (r) {
                            $.product_list.sidebar.find('#' + type + '-' + r.data.id).find('.name:first').html(r.data.name);
                        });
                    }
                });
                if (this.options.edit == 'name') {
                    list_title.trigger('editable');
                }
            }

            // category settings
            var list_settings_link = $('#s-product-list-settings');
            if (list_settings_link.length && (type == 'category' || type == 'set')) {
                list_settings_link.unbind('click').bind('click', function () {

                    //new mode
                    if (type == 'category') {
                        shopDialogProductsCategory.staticDialog($.product_list.collection_hash[1], null, 'edit');
                    } else {
                        shopDialogProductsSet.staticDialog($.product_list.collection_hash[1], 'edit');
                    }

                    return false;
                });
            }

            var list_delete_link = $('#s-product-list-delete');
            if (list_delete_link.length && (type == 'category' || type == 'set')) {
                list_delete_link.unbind('click').bind('click', function () {
                    var d = $("#s-product-list-delete-dialog");
                    d.waDialog({
                        disableButtonsOnSubmit: true,
                        onLoad: function () {
                            $(this).find('.dialog-buttons i.loading').hide();
                        },
                        onSubmit: function () {
                            var self = $(this);
                            var category_id = parseInt($.product_list.collection_hash[1]);
                            self.find('.dialog-buttons i.loading').show();
                            var remove_products = self.find('input[name=s-delete-products]:checked').val() == '1';

                            if (type == 'category' && self.find('input[name="s-delete-sub"]').is(':checked')) {

                                // Get all children of this category by fetching its expanded HTML
                                $.get('?action=categoryExpand&tree=1&recurse=1&id=' + category_id, function(html) {
                                    var ids = $('<div>').html(html).find('li[id^="category-"]').map(function() {
                                        return parseInt(this.id.substr(9), 10);
                                    }).get().reverse();

                                    // Delete all children, then delete category itself
                                    ids.push(category_id);
                                    removeMany(ids, function() {
                                        location.href = '#/';
                                    });
                                });

                            } else {
                                // Delete category and attach children to parent of deleted category
                                removeOne($.product_list.collection_hash, function() {
                                    location.href = '#/';
                                });
                            }

                            return false;

                            function removeMany(ids, fn) {
                                if (!ids.length) {
                                    return fn && fn();
                                }

                                // Delete first category from the list, then delete the rest of them
                                var id = ids.shift();
                                removeOne(['category', id], function() {
                                    removeMany(ids, fn);
                                });
                            }

                            function removeOne(hash, fn) {
                                var params = {
                                    hash: hash.join('/') || 'all',
                                    remove: ['list']
                                };
                                if (remove_products) {
                                    params.remove.push('products');
                                }

                                var remove = function () {
                                    $.product_list.remove(params, function (r, not_allowed_ids) {
                                        removeFromSidebar(hash, r);

                                        // update counters
                                        $.product_list.sidebar.trigger('update', r.data.lists);
                                        fn && fn();
                                    });
                                };

                                // collapse/expand handler (suitable for category)
                                var handler = $('#' + hash.join('-') + '-handler');
                                if (handler.length) {
                                    $.categories_tree.expand(handler, function (h) {
                                        h.parent().find('ul:first').hide();
                                        remove();
                                    });
                                } else {
                                    remove();
                                }

                                return false;
                            }

                            function removeFromSidebar(hash, r) { "use strict";
                                var type = hash[0];
                                var li = $('#' + hash.join('-'));
                                var sep = li.prev('.drag-newposition');
                                var tree = $('#s-' + hash[0] + '-list');
                                var tree_ul = tree.find('ul:first');
                                var ul;

                                if (type == 'set') {
                                    li.remove();
                                    sep.remove();
                                } else {
                                    ul = li.find('ul:first');
                                    if (ul.length) { // curent item has children
                                        ul.find('>li.drag-newposition:first').remove();
                                        li.hide();
                                        ul.find('>li').each(function () {
                                            li.after(this);
                                        });
                                    }
                                    ul = li.parents('ul:first'); // now going to check parent
                                    li.remove();
                                    sep.remove();
                                    if (!ul.find('li.dr').length) {
                                        if (tree_ul.get(0) != ul.get(0)) {
                                            ul.parents('li:first').find('i.collapse-handler').remove();
                                            ul.closest('li').find('.collapse-handler-ajax:first').remove();
                                            ul.remove();
                                        } else {
                                            tree_ul.closest('.block').find('.collapse-handler-ajax:first').remove();
                                        }
                                    }
                                }

                                // if tree list is empty - hide it
                                if (!tree_ul.find('li.dr:first').length) {
                                    tree_ul.hide();
                                    tree.find('.s-empty-list').show();
                                } else {
                                    tree_ul.show();
                                    tree.find('.s-empty-list').hide();
                                }

                                if (r.data.old_parent_category &&
                                    typeof r.data.old_parent_category.children_count !== 'undefined' && !parseInt(r.data.old_parent_category.children_count, 10)) {
                                    $('#category-' + r.data.old_parent_category.id + '-handler').remove();
                                }
                            }
                        }
                    });
                    return false;
                });
            }

            if (type === 'set') {
                $('#s-embed-code').click(function () {
                    $('#s-product-list-embed-dialog').waDialog();
                    return false;
                });
            }

        },

        initDragndrop: function () {
            $.product_dragndrop.init({
                products: true,
                view: this.options.view,
                sort: this.options.sort
            }).bind('move_product', function (options) {
                $.shop.jsonPost('?module=product&action=move&' + $.product_list.collection_param, {
                    product_ids: options.product_ids,
                    before_id: options.before_id || null
                }, options.success, options.error);
            }).bind('is_product_sortable', function () {
                return $.product_list.sortable;
            }).bind('add_to_list', function (options) {
                var data = {};
                if (options.whole_list) {
                    data.hash = $.product_list.collection_hash.join('/') || 'all';
                } else {
                    data.product_id = options.product_ids;
                }
                $.shop.jsonPost('?module=product&action=addToList&' + options.collection_param, data, options.success, options.error);
            });
        },

        initToolbar: function () {
            var toolbar = this.toolbar;
            toolbar.find('li').unbind('click.product_list').bind('click.product_list', function(event) {
                event.preventDefault();

                var $li = $(this);
                var action = $li.attr('data-action');
                if (!action) {
                    return;
                }

                // products == { count: 2, serialized: (result of $form.serializeArray() }
                // in `serialized` there may be a single `hash`, or many `product_id[]` entities
                var products = $.product_list.getSelectedProducts(action != 'delete');
                if (!products.count) {
                    alert($_('Please select at least one product'));
                    return false;
                }

                switch (action) {
                    case 'new-set':
                        /*
                         * $.shop.jsonPost('?module=products&action=saveListSettings&set_id=0&parent_id=0', products.serialized.concat({ name : 'name', value : 'New
                         * set' }), function(r) { var url = '#/products/set_id=' + r.data.id + '&view=' + $.product_list.options.view; $('#s-set-list
                         * ul:first').trigger('add', [r.data, 'set', url]); location.href = url + '&edit=name'; });
                         */
                        break;
                    case 'category':
                        $.product_list.categoriesDialog(products);
                        break;
                    case 'set':
                        $.product_list.setsDialog(products);
                        break;
                    case 'type':
                        $.product_list.typesDialog(products);
                        break;
                    case 'assign-tags':
                        $.product_list.assignTagsDialog(products);
                        break;
                    case 'delete':
                        $.product_list.deleteProductsDialog(products);
                        break;
                    case 'delete-from-set':
                        if ($.product_list.collection_hash[0] == 'set') {
                            $.product_list.deleteFromSet(products);
                        }
                        break;
                    case 'delete-from-category':
                        if ($.product_list.collection_hash[0] == 'category') {
                            $.product_list.deleteFromCategory(products);
                        }
                        break;
                    case 'visibility':
                        $.product_list.visibilityDialog(products, $li);
                        break;
                    case 'export':
                        $.product_list.exportProducts(products, $(this).attr('data-plugin'));
                        break;
                    case 'duplicate':
                        $.product_list.duplicateProducts(products, $(this));
                        break;
                    case 'promo':
                        $.product_list.associatePromo(products, $(this));
                        break;
                    case 'coupon':
                        $.product_list.createCoupon(products);
                        break;
                    case 'set-badge':
                    case 'delete-badge':
                    //case 'set-custom-badge':
                        $.product_list.setBadge(products, $(this));
                        break;
                    default:
                        return;
                }
                return false;
            });

            // 'set-custom-badge' action with a compound control
            toolbar.find('li[data-action="set-custom-badge"] a').click(function() {
                $(this).parent().find('.textarea-wrapper').slideToggle('fast');
                return false;
            }).closest('li').find('.textarea-wrapper').click(function() {
                return false;
            }).find('input').click(function() {
                var products = $.product_list.getSelectedProducts(true);
                if (!products.count) {
                    alert($_('Please select at least one product'));
                } else {
                    $.product_list.setBadge(products, $(this).closest('li'));
                }
                return false;
            });

            // Make right toolbar stick to the top of the page
            // if its height is less than height of the window
            (function() { "use strict";
                var h;
                var menu_height = $('#mainmenu').height();
                var toolbar_top = toolbar.find(':first').offset().top - menu_height;
                var $document = $(document);
                var $window = $(window);
                $document.bind('scroll', h = function () {
                    if (!jQuery.contains(document.documentElement, toolbar[0])) {
                        $document.off('scroll', h);
                        $window.off('resize', h);
                        return;
                    }

                    if ($document.scrollTop() > toolbar_top && toolbar.height() + menu_height < $window.height()) {
                        toolbar.addClass('s-fixed').css({
                            top: menu_height
                        });
                    } else {
                        toolbar.removeClass('s-fixed');
                    }
                });
                $window.bind('resize', h);
            })();
        },

        /**
         * Get special key-value object for mass operations (delete, delete-from-set and etc) Taking into account all-products checkbox (s-select-all)
         *
         * Object say how many products are selected (key 'count'), and info about products or hash If all-products checkbox is activated than object has hash
         * info, else object has products info
         *
         * @param {Boolean} serialize If true than hash/product info packed into field with key 'serialized' else hash info corresponds 'hash'-key and products
         *        info corresponds 'products'-key
         * @returns {Object}
         */
        getSelectedProducts: function (serialize) {
            serialize = serialize || false;
            var product_list = this.container;
            var data = {
                count: 0
            };
            if (product_list.find('.s-select-all:first').attr('checked')) {
                var hash = $.product_list.collection_hash.join('/') || 'all';
                if (serialize) {
                    data.serialized = [
                        {
                            name: 'hash',
                            value: hash
                        }
                    ];
                } else {
                    data.hash = hash;
                }
                data.count = this.total_count;
            } else {
                if (serialize) {
                    data.serialized = $.product_list.container.find('.product.selected').map(function () {
                        data.count += 1;
                        return {
                            name: 'product_id[]',
                            value: $(this).attr('data-product-id')
                        };
                    }).toArray();
                } else {
                    data.product_id = $.product_list.container.find('.product.selected').map(function () {
                        data.count += 1;
                        return $(this).attr('data-product-id');
                    }).toArray();
                }
            }
            return data;
        },

        visibilityDialog: function (products, $li) {
            // Sanity check...
            if (!$.isArray(products.serialized)) {
                return false;
            }

            var $icon = $li.find('i.icon16');
            if (!$icon.hasClass('loading')) {
                var $wrapper = $('#visibility-dialog-wrapper');
                if (!$wrapper.length) {
                    $wrapper = $('<div id="visibility-dialog-wrapper">').appendTo('#s-content');
                }

                var old_icon_class = $icon.attr('class');
                $icon.attr('class', 'icon16 loading');
                $wrapper.data('products', products).load('?module=dialog&action=visibility', function() {
                    $icon.attr('class', old_icon_class);
                });
            }
        },

        categoriesDialog: function (products) {
            var d = $('#s-categories');
            var sidebar = this.sidebar;
            var product_list = this.container;
            var showDialog = function () {
                $('#s-categories').waDialog({
                    disableButtonsOnSubmit: true,
                    onLoad: function () {
                        var self = $(this);
                        self.find('.dialog-content h1 span').text('(' + products.count + ')').show();
                        self.find('.dialog-buttons i.loading').hide();
                        self.find('input[name=new_category_name]').val('');
                        self.find('input[name=new_category]').attr('checked', false);
                    },
                    onSubmit: function (d) {
                        // addToCategories
                        var form = d.find('form');
                        form.find('.dialog-buttons i.loading').show();
                        $.shop.jsonPost(form.attr('action'), form.serializeArray().concat(products.serialized), function (r) {

                            // add new category to sidebar
                            if (r.data.new_category) {
                                $('#s-category-list ul:first').trigger('add',
                                    [r.data.new_category, 'category', '#/products/category_id=' + r.data.new_category + '&view=' + $.product_list.options.view]);
                            }

                            // update cagegories in sidebar
                            if (r.data.categories) {
                                sidebar.trigger('update', [
                                    {
                                        category: r.data.categories
                                    }
                                ]);
                                product_list.find('.s-select-all:first').trigger('select', false);
                            }


                            form.find('input:checked').attr('checked', false);
                            d.trigger('close');
                        });
                        return false;
                    }
                });
            };

            // no cache dialog
            if (d.length) {
                d.parent().remove();
            }

            var p = $('<div></div>').appendTo('body');
            p.load('?module=dialog&action=categories', showDialog);
        },

        setsDialog: function (products) {
            var d = $('#s-sets');
            var sidebar = this.sidebar;
            var product_list = this.container;
            var showDialog = function () {
                $('#s-sets').waDialog({
                    disableButtonsOnSubmit: true,
                    onLoad: function () {
                        var self = $(this);
                        self.find('.dialog-content h1 span').text('(' + products.count + ')').show();
                        self.find('.dialog-buttons i.loading').hide();
                        self.find('input[name=new_set_name]').val('');
                        self.find('input[name=new_set]').attr('checked', false);
                    },
                    onSubmit: function (d) {
                        // addToSets
                        var form = d.find('form');
                        form.find('.dialog-buttons i.loading').show();
                        $.shop.jsonPost(form.attr('action'), form.serializeArray().concat(products.serialized), function (r) {

                            // add new category to sidebar
                            if (r.data.new_set) {
                                $('#s-set-list ul:first').trigger('add',
                                    [r.data.new_set, 'set', '#/products/set_id=' + r.data.new_set + '&view=' + $.product_list.options.view]);
                            }

                            // update cagegories in sidebar
                            if (r.data.sets) {
                                sidebar.trigger('update', [
                                    {
                                        set: r.data.sets
                                    }
                                ]);
                                product_list.find('.s-select-all:first').trigger('select', false);
                            }
                            form.find('input:checked').attr('checked', false);
                            d.trigger('close');
                        });
                        return false;
                    }
                });
            };

            // no cache dialog
            if (d.length) {
                d.parent().remove();
            }

            var p = $('<div></div>').appendTo('body');
            p.load('?module=dialog&action=sets', showDialog);
        },

        assignTagsDialog: function (products) {
            var d = $('#s-assign-tags');
            var showDialog = function () {
                $('#s-assign-tags').waDialog({
                    disableButtonsOnSubmit: true,
                    onLoad: function () {
                        var self = $(this);
                        self.find('.dialog-content h1 span').text('(' + products.count + ')').show();
                        self.find('.dialog-buttons i.loading').hide();
                    },
                    onSubmit: function (d) {
                        var self = $(this);
                        var $tags_input = self.find('#s-assign-tags-list_tag');
                        if ($tags_input.length) {
                            var e = jQuery.Event("keypress", {
                                which: 13
                            });
                            $tags_input.trigger(e);
                        }

                        self.find('.dialog-buttons i.loading').show();
                        var url = self.attr('action');
                        setTimeout(function () {
                            // assignTags
                            var data = self.serializeArray().concat(products.serialized);
                            $.shop.jsonPost(url, data, function (r) {
                                // Show error for items that are denied access
                                if (r.data.denied_message) {
                                    alert(r.data.denied_message)
                                }

                                if (r.data.cloud === 'search') {
                                    d.trigger('close');
                                } else if(r.data.cloud) {
                                    $('#s-tag-cloud').trigger('update', [r.data.cloud]);
                                }
                                d.trigger('close');
                                $.products.dispatch();
                            }, function () {
                                d.trigger('close');
                            });
                        }, 10);
                        return false;
                    }
                });
            };

            // no cache dialog
            if (d.length) {
                d.remove();
            }

            // use post-method instead of get-method because of potential long list of product ids
            $.post('?module=dialog&action=assignTags', products.serialized, function (html) {
                $('body').append(html);
                showDialog();
            });
        },

        typesDialog: function (products) {
            var d = $('#s-types');
            var product_list = this.container;
            var sidebar = this.sidebar;
            var showDialog = function () {
                $('#s-types').waDialog({
                    disableButtonsOnSubmit: true,
                    onLoad: function () {
                        $(this).find('.dialog-buttons i.loading').hide();
                    },
                    onSubmit: function (d) {
                        var form = $(this);
                        form.find('.dialog-buttons i.loading').show();
                        $.shop.jsonPost(form.attr('action'), form.serializeArray().concat(products.serialized), function (r) {
                            sidebar.trigger('update', {
                                type: r.data.types
                            });
                            product_list.find('.s-select-all:first').trigger('select', false);
                            form.find('input:checked').attr('checked', false);
                            d.trigger('close');
                            $.products.dispatch();
                        });
                        return false;
                    }
                });
            };
            var p = d.parent();
            if (!d.length) {
                p = $('<div></div>').appendTo('body');
                p.load('?module=dialog&action=types', showDialog);
            } else {
                showDialog();
            }
        },

        deleteFromSet: function (products) {
            if (products.count > 0) {
                $.shop.jsonPost('?module=products&action=deleteFromSet&id=' + this.collection_hash[1], products.serialized, function () {
                    $.products.dispatch();
                });
            }
        },

        deleteFromCategory: function (products) {
            if (products.count > 0) {
                $.shop.jsonPost('?module=products&action=deleteFromCategory&id=' + this.collection_hash[1], products.serialized, function () {
                    $.products.dispatch();
                });
            }
        },

        deleteProductsDialog: function (products) {
            var showDialog = function () {
                $('#s-product-list-delete-products-dialog').waDialog({
                    disableButtonsOnSubmit: true,
                    onLoad: function () {
                        $(this).find('.dialog-buttons i.loading').hide();
                    },
                    onSubmit: function (d) {
                        $(this).find('.dialog-buttons i.loading').show();
                        $.product_list.remove($.extend(products, {
                            remove: ['products']
                        }), function (r, not_allowed_ids) {
                            $.product_list.sidebar.trigger('update', r.data.lists);
                            $.products.dispatch();
                            d.trigger('close');
                            if (not_allowed_ids.length) {
                                alert((d.data('not-allowed-string')||'').replace('%d', not_allowed_ids.length));
                            }
                        });
                        return false;
                    }
                });
            };
            var d = $('#s-product-list-delete-products-dialog');
            var p = d.parent();
            if (!d.length) {
                p = $('<div></div>').appendTo('body');
            }
            if (products.count > 0) {
                p.load('?module=dialog&action=productsDelete', products, showDialog);
            }
        },

        exportProducts: function (products, plugin) {
            var ids = [];
            var product;
            var hash = false;
            while (product = products.serialized.pop()) {
                if (product.name) {
                    if (product.name == 'product_id[]') {
                        ids.push(parseInt(product.value, 10));
                    } else if (product.name == 'hash') {
                        hash = product.value;
                    }
                }
            }
            var url = '?action=importexport#/';
            url += plugin;
            if (hash) {
                url += '/hash/' + hash;
                window.location = url;
            } else if (ids.length) {
                url += '/hash/id/' + ids.join(',');
                window.location = url;
            }
        },

        createCoupon: function (products) {
            var ids = [];
            var product;
            var hash = false;
            while (product = products.serialized.pop()) {
                if (product.name) {
                    if (product.name == 'product_id[]') {
                        ids.push(parseInt(product.value, 10));
                    } else if (product.name == 'hash') {
                        hash = product.value;
                    }
                }
            }

            var url = this.options['marketing_url'] +'coupons/create/';
            if (hash) {
                url += '?products_hash=' + hash;
                window.location = url;
            } else if (ids.length) {
                url += '?products_hash=id/' + ids.join(',');
                window.location = url;
            }
        },

        duplicateProducts: function (products, $link) {
            var ids = [];
            var product;
            var hash = false;
            while (product = products.serialized.pop()) {
                if (product.name) {
                    if (product.name == 'product_id[]') {
                        ids.push(parseInt(product.value, 10));
                    } else if (product.name == 'hash') {
                        hash = product.value;
                    }
                }
            }
            if (!hash && ids.length) {
                hash = 'id/' + ids.join(',');
            }
            if (hash) {
                $link.find('i.icon16').removeClass('split').addClass('loading');
                this.duplicate(hash, {
                    'progress': function (data) {
                        $link.attr('title', Math.round(100.0 * data.offset / data.total_count) + '%');
                    },
                    'finish': function (data, new_ids) {
                        $link.attr('title', null);
                        var $icon = $link.find('i.icon16');
                        $icon.removeClass('loading').addClass('yes');
                        setTimeout(function () {
                            $icon.removeClass('yes').addClass('split');
                        }, 3000);

                        $(document).one('product_list_init_view', function() {
                            var is_new = {};
                            $.each(new_ids, function(i, id) {
                                is_new[id] = 1;
                            });
                            $('#product-list [data-product-id]').each(function() {
                                var $this = $(this);
                                var id = $(this).data('product-id');
                                if (id && is_new[id]) {
                                    $this.addClass('highlighted');
                                }
                            });
                        });

                        if (data || new_ids) {
                            $.products.dispatch();
                        }
                    },
                    'error': function (data) {

                    }
                });
            }
        },

        setBadge: function(products, $li) {
            var action = $li.data('action');
            if (action != 'set-custom-badge') {
                $li.parent().find('.textarea-wrapper').slideUp('fast');
            }

            $li.find('.loading').remove();
            $li.find('a').append('<span class="count"><i class="icon16 loading"></i></span>');

            // Hashmap of product ids used in jsonComplete()
            var is_selected = {};
            var everything_selected = false;
            $.each(products.serialized, function() {
                everything_selected = everything_selected || this.name == 'hash';
                is_selected[this.value] = 1;
            });

            // Badge deletion has a separate controller
            if (action == 'delete-badge') {
                $li.find('.loading').remove();
                $.shop.jsonPost('?module=product&action=badgeDelete', products.serialized, jsonComplete);
                return;
            }

            // Prepare data for badge saving contoller
            var badge_code;
            if (action == 'set-custom-badge') {
                badge_code = $li.find('textarea').val();
            } else {
                badge_code = $li.data('type');
            }
            var data = products.serialized;
            data.push({
                name: 'code',
                value: badge_code
            });

            // Save badge
            $.shop.jsonPost('?module=product&action=badgeSet', data, jsonComplete, errorHandler);

            // Helper to update DOM after badge has been saved
            function jsonComplete(r) {
                var badge_html = (action == 'delete-badge' ? null : r.data);
                $('#product-list [data-product-id]').each(function() {
                    var $li = $(this);
                    if (everything_selected || is_selected[$li.data('product-id')]) {
                        if ($li.is('tr')) {
                            var $a = $li.find('.s-image a');
                            $a.find('.s-image-corner').remove();
                            badge_html && $a.prepend($('<div class="s-image-corner"></div>').html(badge_html));
                        } else {
                            var $a = $li.find('.s-product-image a');
                            $a.find('.s-image-corner.top.right').remove();
                            badge_html && $a.append($('<div class="s-image-corner top right"></div>').html(badge_html));
                        }
                        $li.trigger('badge', [badge_html]);
                    }
                });

                action == 'set-custom-badge' && $li.parent().find('.textarea-wrapper').slideUp('fast');
                $li.find('.loading').remove();
                $li.find('a').append(
                    $('<span class="count"><i class="icon16 yes"></i></span>').animate({ opacity: 0 }, function() {
                        $(this).remove();
                    })
                );

                $.product_list.container.find('.s-select-all:first').trigger('select', false);
            }
            function errorHandler(r) {
                if (typeof r.errors === 'object' && typeof r.errors[0] === "string") {
                    alert(r.errors[0])
                }
                $li.find('.loading').remove();
            }
        },

        duplicate: function (hash, options) {
            var params = {
                'hash': hash,
                'limit': 50,
                'offset': options.offset || 0
            };
            var url = '?module=products&action=duplicate';
            var self = this;
            var new_ids = [];

            $.shop.jsonPost(url, params, function (response) {
                if ((response.status || 'error') == 'ok') {
                    new_ids = new_ids.concat(response.data.new_ids || []);
                    if (response.data.offset < response.data.total_count) {
                        options.offset = response.data.offset;
                        self.duplicate(hash, options);
                        options.progress(response.data || {});
                    } else {
                        options.finish(response.data || {}, new_ids);
                    }
                } else if (response.status == 'fail' && response.errors.message) {
                    var $error_dialog = $("#s-product-list-duplicate-error-dialog");
                    $error_dialog.waDialog();
                }
            }, function (r) {
                if (r.errors) {
                    options.finish();
                    if (r.errors.message) {
                        var $error_dialog = $("#s-product-list-duplicate-error-dialog");
                        $error_dialog.find('.js-content').html(r.errors.message);
                        $error_dialog.waDialog();
                    } else {
                        alert(r.errors);
                    }
                }
            });
        },

        remove: function (options, finish) {
            var count = 100;
            var params = {};
            var url = '?module=products&action=deleteList';
            var not_allowed_ids = [];
            var process;
            if (options.product_id) {
                process = function () {
                    if (options.product_id.length <= count) {
                        params.get_lists = true;
                    }
                    params.product_id = options.product_id.splice(0, count);
                    $.shop.jsonPost(url, params, function (r) {
                        r.data.not_allowed && r.data.not_allowed.length && (not_allowed_ids = not_allowed_ids.concat(r.data.not_allowed));
                        if (options.product_id.length) {
                            process();
                        } else if (typeof finish === 'function') {
                            finish(r, not_allowed_ids);
                        }
                    });
                };
            } else {
                params.hash = options.hash || (this.collection_hash.join('/') || 'all');
                params.remove = $.isArray(options.remove) && options.remove.length ? options.remove : ['list'];
                if (params.remove.length == 1 && params.remove[0] == 'list') {
                    process = function () {
                        $.shop.jsonPost(url, params, finish);
                    };
                } else {
                    params.count = count;
                    var rest_count = null; // previous rest count
                    process = function () {
                        $.shop.jsonPost(url, params, function (r) {
                            r.data.not_allowed && r.data.not_allowed.length && (not_allowed_ids = not_allowed_ids.concat(r.data.not_allowed));
                            if (r.data.rest_count > 0 && rest_count != r.data.rest_count) {
                                process();
                            } else if (typeof finish === 'function') {
                                finish(r, not_allowed_ids);
                            }
                        });
                    };
                }
            }

            process();

        },

        associatePromo: function(products, $link) {
            var is_locked = $link.data("locked");
            if (!is_locked) {
                $link.data("locked", true);
                showDialog().always( function() {
                    $link.removeData("locked");
                });
            }

            function showDialog() {
                var href = location.pathname + "?module=productsAssociatePromoDialog",
                    data = {
                        products_hash: getHash(products.serialized)
                    };

                var lock_scroll_class = "is-scroll-locked";

                return $.post(href, data).done( function(html) {
                    $(html).waDialog({
                        onLoad: function () {
                            $("body").addClass(lock_scroll_class);
                        },
                        onCancel: function () {
                            $("body").removeClass(lock_scroll_class);
                            $(this).remove();
                        }
                    });
                });
            }
        },

        // Fix for long table
        rubberTable: function() {
            var $wrapper = $("#wa"),
                $content_wrapper = $("#s-content"),
                $content = $("#s-content > .content"),
                left_margin = parseInt( $content_wrapper.css("margin-left")),
                right_margin = parseInt( $content.css("margin-right")),
                $table = $(".s-product-list-table-container table"),
                table_width = $table.width(),
                old_styles = $wrapper.attr("style"),
                content_width,
                page_width;

            // Save old styles
            if (old_styles) { $wrapper.data("style", old_styles); }

            content_width = parseInt( $content.width() - $(".s-product-list-table-container").width() ) + table_width;
            page_width = content_width + left_margin + right_margin;

            if ($wrapper.width() < page_width) {
                $wrapper.css({ "min-width": page_width + "px" });
            }

            $(window).resize();
        }
    };

    function getHash(products_array) {
        var ids = [];
        var hash = "";

        $.each(products_array, function(i, product) {
            if (product.name) {
                if (product.name === 'product_id[]') {
                    ids.push(parseInt(product.value, 10));
                } else if (product.name === 'hash') {
                    hash = product.value;
                }
            }
        });

        if (!hash && ids.length) {
            hash = 'id/' + ids.join(',');
        }

        return hash;
    }

})(jQuery);
