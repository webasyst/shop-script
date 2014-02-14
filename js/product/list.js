(function($) {
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

        init: function(options) {
            try {
                this.options = options;
                this.container = $('#product-list');
                this.toolbar = $('#s-product-list-toolbar');
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

                if (products && options.view) {
                    try {
                        this.container.append(tmpl('template-product-list-' + this.options.view, {
                            products: products,
                            sort: this.sort
                        }, this.options.view == 'table'));
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
                }

                this.initSelecting();
                this.initView(this.options.view);
                this.initDragndrop();
            } catch (e) {
                $.shop.error('$.product_list.init error: ' + e.message, e);
            }

            return this;
        },

        initLazyLoad: function(options) {
            var count = options.count;
            var offset = count;
            var total_count = this.total_count;

            $(window).lazyLoad('stop'); // stop previous lazy-load implementation

            if (offset < total_count) {
                var self = this;
                $(window).lazyLoad({
                    container: self.container,
                    state: (typeof options.auto === 'undefined' ? true : options.auto) ? 'wake' : 'stop',
                    hash: ['', 'products'], // ['products']
                    load: function() {
                        $(window).lazyLoad('sleep');
                        $('.lazyloading-link').hide();
                        $('.lazyloading-progress').show();
                        $.get('?module=products&action=loadList&offset=' + offset + ('&total_count=' + total_count) + 
                        (self.collection_param ? '&' + self.collection_param : '') + (self.sort ? '&sort=' + self.sort : '') + 
                        (self.order ? '&order=' + self.order : ''), function(r) {
                            if (r.status == 'ok' && r.data.count) {
                                offset += r.data.count;

                                var product_list = self.container;
                                try {
                                    self.container.append(tmpl('template-product-list-' + self.options.view, {
                                        products: r.data.products,
                                        check_all: self.options.view == 'table' ? product_list.find('.s-select-all').attr('checked') : false,
                                        sort: $.product_list.sort
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
                $('.lazyloading-link').die('click').live('click', function() {
                    $(window).lazyLoad('force');
                    return false;
                });
            }
        },

        initSelecting: function() {
            var product_list = this.container;
            var toolbar = this.toolbar;
            var toolbar_count = toolbar.find('.count');
            var select_all_input = product_list.find('.s-select-all');
            var recount = function() {
                var count = parseInt(product_list.find('.product.selected').length, 10);
                if (count) {
                    toolbar_count.text(count).show();
                } else {
                    toolbar_count.text('').hide();
                }
            };

            // when 'shift' held on prevent default browser selecting
            $(document).keydown(function(e) {
                if (e.keyCode == 16) {
                    document.body.onselectstart = function() {
                        return false;
                    };
                }
            }).keyup(function(e) {
                if (e.keyCode == 16) {
                    document.body.onselectstart = null;
                }
            });

            // handler of triggerable 'select' event
            product_list.off('select', '.product').on('select', '.product', function(e, selected, need_count) {
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

            select_all_input.unbind('select').bind('select', function(e, selected) {
                selected = selected !== undefined ? selected : true;
                var self = $(this);
                if (selected) {
                    self.attr('checked', true);
                    product_list.find('.product').trigger('select', true, false);
                    toolbar_count.text(self.attr('data-count')).show();
                } else {
                    self.attr('checked', false);
                    product_list.find('.product').trigger('select', false, false);
                    toolbar_count.text('').hide();
                }
            });

            product_list.off('click', '.product input').on('click', '.product input', function(e, ext) {
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
                        started = product_list.find('.product:first').trigger('select', true);
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
                product_list.off('click', '.product label').on('click', '.product label', function(e) {
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

        initView: function(view) {
    
            try {
                var product_list = this.container;
                var sidebar = this.sidebar;
                if (view == 'table') {
                    product_list.find('.s-select-all').click(function() {
                        $(this).trigger('select', this.checked);
                    });
                }

                // var param = 'view=' + view + (this.sort ? '&sort=' + this.sort : '');
                var param = 'view=' + view;
                sidebar.find('.s-collection-list li.dr').each(function() {
                    var self = $(this);
                    self.find('a:first').attr('href', '#/products/' + self.attr('id').replace('-', '_id=') + '&' + param);
                });
                $('#s-products-search').val(this.options.text || '');

                var li_id = 's-all-products';
                if ($.product_list.collection_hash.length && $.product_list.collection_hash[0] !== 'search' && $.product_list.collection_hash[0] !== 'tag') {
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
                if ($.product_list.collection_hash.length && $.product_list.collection_hash[0] !== 'search' && $.product_list.collection_hash[0] !== 'tag') {
                    active_element.find('.count:first').text(this.category_count);
                }

                $('#s-content').find('.sort').unbind('click').bind('click', function() {
                    location.href = $(this).find('a').attr('href');
                    return false;
                });
                
                $.product_list.fixed_blocks = $.product_list.initFixedBlocks();
                
                this.initEditingControls();
                this.initToolbar();
            } catch (e) {
                $.shop.error('$.product_list.initView error: ' + e.message, e);
            }
        },
        
        initFixedBlocks: function() {
            var mainmenu_offset = $('#mainmenu').height();
            var sidebar_width = $('#s-sidebar').width();
            var sensitivity = 5;

            var win = $(window);

            var fixed_blocks = $('#s-fixed-blocks');
            if (!fixed_blocks.length) {
                var fixed_blocks = $('<div id="s-fixed-blocks"></div>').css({
                    display: 'none',
                    position: 'fixed',
                    background: '#fff',
                    top: mainmenu_offset,
                    width: sidebar_width,
                    'float': 'left'
                }).appendTo('#s-sidebar');
            }

            var category_list = $('#s-category-list');
            var category_list_block = $('#s-category-list-block');

            var set_list = $('#s-set-list');
            var set_list_block = $('#s-set-list-block');

            // FIXED CATEGORY BLOCK
            var fixed_category_block = (function() {

                var top_offset = category_list_block.offset().top;

                var block_id    = 's-category-list-block';
                var dummy_id    = 's-category-list-dummy';
                var handler_id = 's-category-list-handler';

                var fixed = false;
                var is_list_hidden;
                var is_collapsed;

                function setFixed() {
                    if (fixed) {
                        return;
                    }
                    
                    var h = $('#' + handler_id);
                    is_collapsed = $.categories_tree.isCollapsed(h);
                    is_list_hidden = category_list.is(':hidden');
                    
                    // insert instead of original block dummy with proper height
                    var d = $('<div id="' + dummy_id + '" class="block"></div>').css({
                        height: category_list_block.height()
                    });
                    category_list_block.replaceWith(d);

                    fixed_blocks.show().append(category_list_block.hide());
                    category_list_block.find('.heading').before('<span class="hint float-right" style="margin-top: 3px;">' + $_('Drag products here') + '</span>');

                    if (is_collapsed) {
                        $.categories_tree.expand(h, null, function() {
                            $.categories_tree.collapse(h);
                        });
                    }
                    if (!is_list_hidden) {
                        category_list.hide();
                    }

                    category_list_block.show();

                    // hide control (icon) for adding
                    $('i.add', category_list_block).hide();

                    // hide main collapse/expand handler
                    $('#' + handler_id).hide();

                    category_list_block.css({
                        'overflow-x': 'hidden',
                        'overflow-y': 'auto'
                    });

                    (function() {
                        var is_hidden = category_list.is(':hidden');     // for optimization reason
                        var timer_id;
                        category_list_block.bind('mousemove.fixed_blocks.category', function(e) {
                            if (!isProductDragging()) {
                                return;
                            }
                            if (is_hidden) {
                                if (timer_id) {
                                    clearTimeout(timer_id);
                                    timer_id = null;
                                }
                                timer_id = setTimeout(function() {
                                    if (is_hidden) {
                                        category_list_block.css({
                                            height: ''
                                        });
                                        category_list.show();
                                        var current_height = category_list_block.height();
                                        var proposed_height = (win.height() - mainmenu_offset) / 2;
                                        category_list_block.css({
                                            height: Math.min(current_height, proposed_height)
                                        });
                                        category_list_block.find('.heading').prev('.hint').hide();
                                        is_hidden = false;
                                    }
                                }, 250);
                            }
                        });
                        win.bind('mouseout.fixed_blocks.category', function(e) {
                            if (e.pageX > sidebar_width + 10) {
                                if (timer_id) {
                                    clearTimeout(timer_id);
                                }
                                timer_id = null;
                                if (!is_hidden) {
                                    category_list.hide();
                                    category_list_block.css({
                                        height: ''
                                    });
                                    category_list_block.find('.heading').prev('.hint').show();
                                    is_hidden = true;
                                }
                            }
                        });
                    })();
                    
                    fixed = true;
                    
                }

                function unsetFixed() {
                    if (!fixed) {
                        return;
                    }
                    // place back original block
                    var d = $('#' + dummy_id);
                    d.replaceWith(category_list_block);

                    category_list_block.css({
                        'overflow-x': '',
                        'overflow-y': ''
                    });

                    // back to previous collapse/expand status
                    var h = $('#' + handler_id);
                    if (is_collapsed) {
                        $.categories_tree.collapse(h);
                    }
                    if (!is_list_hidden) {
                        category_list.show();
                    }

                    category_list_block.find('.heading').prev('.hint').remove();

                    // show back collapse/expand handler
                    $('#' + handler_id).show();

                    // show back adding control
                    $('i.add', category_list_block).show();

                    // remove dummy
                    d.remove();

                    // unbind events
                    category_list_block.unbind('mousemove.fixed_blocks.category');
                    win.unbind('mouseout.fixed_blocks.category');

                    if (!fixed_blocks.children().length) {
                        fixed_blocks.hide();
                    }
                    
                    fixed = false;

                }

                function dummyExists() {
                    return document.getElementById(dummy_id);
                }

                function isProductDragging() {
                    return document.getElementById('products-helper');
                }

                win.unbind('scroll.fixed_blocks.category').bind('scroll.fixed_blocks.category', function() {
                    if (!fixed) {
                        top_offset = category_list_block.offset().top;
                    }
                    var bottom = top_offset;
                    if (dummyExists()) {
                        bottom += $('#' + dummy_id).height();
                    } else {
                        bottom += $('#' + block_id).height();
                    }
                    if (win.scrollTop() + mainmenu_offset + sensitivity >= bottom) {
                        setFixed();
                    } else {
                        unsetFixed();
                    }
                });
                
                return {
                    setFixed: setFixed, unsetFixed: unsetFixed
                };
                
            })();

            // FIXED SET BLOCK
            var fixed_set_block = (function() {

                var top_offset = set_list_block.offset().top;

                var block_id    = 's-set-list-block';
                var dummy_id    = 's-set-list-dummy';

                var is_list_hidden;
                var fixed = false;
                
                //var is_collapsed = $.categories_tree.isCollapsed('#' + handler_id);

                function setFixed() {
                    if (fixed) {
                        return;
                    }
                    
                    is_list_hidden = set_list.is(':hidden');
                    
                    // insert instead of original block dummy with proper height
                    var d = $('<div id="' + dummy_id + '" class="block"></div>').css({
                        height: set_list_block.height()
                    });
                    set_list_block.replaceWith(d);

                    fixed_blocks.show().append(set_list_block);
                    set_list_block.find('.heading').before('<span class="hint float-right" style="margin-top: 3px;">' + $_('Drag products here') + '</span>');

                    if (!is_list_hidden) {
                        set_list.hide();
                    }

                    // hide control (icon) for adding
                    $('i.add', set_list_block).hide();

                    // hide handler control
                    $('i.collapse-handler', set_list_block).hide();

                    set_list_block.css({
                        'overflow-x': 'hidden',
                        'overflow-y': 'auto'
                    });

                    (function() {
                        var is_hidden = set_list.is(':hidden');     // for optimization reason
                        var timer_id;
                        set_list_block.bind('mousemove.fixed_blocks.set', function(e) {
                            if (!isProductDragging()) {
                                return;
                            }
                            if (is_hidden) {
                                if (timer_id) {
                                    clearTimeout(timer_id);
                                    timer_id = null;
                                }
                                timer_id = setTimeout(function() {
                                    if (is_hidden) {
                                        set_list_block.css({
                                            height: ''
                                        });
                                        set_list.show();
                                        var current_height = set_list_block.height();
                                        var proposed_height = (win.height() - mainmenu_offset) / 2;
                                        set_list_block.css({
                                            height: Math.min(current_height, proposed_height)
                                        });
                                        set_list_block.find('.heading').prev('.hint').hide();
                                        is_hidden = false;
                                    }
                                }, 250);
                            }
                        });
                        win.bind('mouseout.fixed_blocks.set', function(e) {
                            if (e.pageX > sidebar_width + 10) {
                                if (timer_id) {
                                    clearTimeout(timer_id);
                                }
                                timer_id = null;
                                if (!is_hidden) {
                                    set_list.hide();
                                    set_list_block.css({
                                        height: ''
                                    });
                                    set_list_block.find('.heading').prev('.hint').show();
                                    is_hidden = true;
                                }
                            }
                        });
                    })();
                    
                    fixed = true;

                }

                function unsetFixed() {
                    if (!fixed) {
                        return;
                    }
                    // place back original block
                    var d = $('#' + dummy_id);
                    d.replaceWith(set_list_block);

                    set_list_block.css({
                        'overflow-x': '',
                        'overflow-y': ''
                    });

                    set_list_block.find('.heading').prev('.hint').remove();

                    // back to previous collapse/expand status
                    if (!is_list_hidden) {
                        set_list.show();
                    }

                    // show back adding control
                    $('i.add', set_list_block).show();

                    // show back handler control
                    $('i.collapse-handler', set_list_block).show();

                    // remove dummy
                    d.remove();

                    // unbind events
                    set_list_block.unbind('mousemove.fixed_blocks.category');
                    win.unbind('mouseout.fixed_blocks.category');

                    if (!fixed_blocks.children().length) {
                        fixed_blocks.hide();
                    }
                    
                    fixed = false;

                }

                function dummyExists() {
                    return document.getElementById(dummy_id);
                }

                function isProductDragging() {
                    return document.getElementById('products-helper');
                }

                win.unbind('scroll.fixed_blocks.set').bind('scroll.fixed_blocks.set', function() {
                    if (!fixed) {
                        top_offset = set_list_block.offset().top;
                    }
                    var bottom = top_offset;
                    if (dummyExists()) {
                        bottom += $('#' + dummy_id).height();
                    } else {
                        bottom += $('#' + block_id).height();
                    }
                    if (win.scrollTop() + mainmenu_offset + sensitivity >= bottom) {
                        setFixed();
                    } else {
                        unsetFixed();
                    }
                });

                return {
                    setFixed: setFixed, unsetFixed: unsetFixed
                };

            })();
            
            return {
                category: fixed_category_block,
                set: fixed_set_block
            };
            
        },
        
        initEditingControls: function() {
            var list_title = $('#s-product-list-title');
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
                    beforeMakeEditable: function() {
                        $('.s-product-list-manage').hide();
                    },
                    afterBackReadable: function(input, data) {
                        $('.s-product-list-manage').show();
                        if (!data.changed) {
                            return false;
                        }
                        $.shop.jsonPost('?module=products&action=saveListSettings&' + $.product_list.collection_param + '&edit=name', {
                            name: $(input).val()
                        }, function(r) {
                            $.product_list.sidebar.find('#' + $.product_list.collection_hash[0] + '-' + r.data.id).find('.name:first').html(r.data.name);
                        });
                    }
                });
                if (this.options.edit == 'name') {
                    list_title.trigger('editable');
                }
            }

            var type = $.product_list.collection_hash[0];

            // category settings
            var list_settings_link = $('#s-product-list-settings');
            if (list_settings_link.length && (type == 'category' || type == 'set')) {
                list_settings_link.unbind('click').bind('click', function() {
                    $.product_list.settingsListDialog();
                    return false;
                });
            }

            var list_delete_link = $('#s-product-list-delete');
            if (list_delete_link.length && (type == 'category' || type == 'set')) {
                list_delete_link.unbind('click').bind('click', function() {
                    var d = $("#s-product-list-delete-dialog");
                    d.waDialog({
                        disableButtonsOnSubmit: true,
                        onLoad: function() {
                            $(this).find('.dialog-buttons i.loading').hide();
                        },
                        onSubmit: function() {
                            var self = $(this);
                            self.find('.dialog-buttons i.loading').show();
                            var params = {
                                hash: $.product_list.collection_hash.join('/') || 'all',
                                remove: ['list']
                            };
                            if (self.find('input[name=s-delete-products]:checked').val() == '1') {
                                params.remove.push('products');
                            }

                            var remove = function() {
                                $.product_list.remove(params, function(r) {
                                    var li = $('#' + $.product_list.collection_hash.join('-'));
                                    var sep = li.prev('.drag-newposition');
                                    var tree = $('#s-' + $.product_list.collection_hash[0] + '-list');
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
                                            ul.find('>li').each(function() {
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

                                    // update counters
                                    $.product_list.sidebar.trigger('update', r.data.lists);


                                    if (r.data.old_parent_category &&
                                            typeof r.data.old_parent_category.children_count !== 'undefined' &&
                                            !parseInt(r.data.old_parent_category.children_count, 10))
                                    {
                                        $('#category-' + r.data.old_parent_category.id + '-handler').remove();
                                    }

                                    location.href = '#/';

                                });
                            };

                            // collapse/expand handler (suitable for category)
                            var handler = $('#' +
                                    $.product_list.collection_hash.join('-') +
                                    '-handler'
                                );
                            if (handler.length) {
                                $.categories_tree.expand(handler, function(h) {
                                    h.parent().find('ul:first').hide();
                                    remove();
                                });
                            } else {
                                remove();
                            }

                            return false;
                        }
                    });
                    return false;
                });
            }
            
            if (type === 'set') {
                $('#s-embed-code').click(function() {
                    $('#s-product-list-embed-dialog').waDialog();
                    return false;
                });
            }
            
        },

        initDragndrop: function() {
            $.product_dragndrop.init({
                products: true,
                view: this.options.view,
                sort: this.options.sort
            }).bind('move_product', function(options) {
                $.shop.jsonPost('?module=product&action=move&' + $.product_list.collection_param, {
                    product_ids: options.product_ids,
                    before_id: options.before_id || null
                }, options.success, options.error);
            }).bind('is_product_sortable', function() {
                return $.product_list.sortable;
            }).bind('add_to_list', function(options) {
                var data = {};
                if (options.whole_list) {
                    data.hash = $.product_list.collection_hash.join('/') || 'all';
                } else {
                    data.product_id = options.product_ids;
                }
                $.shop.jsonPost('?module=product&action=addToList&' + options.collection_param, data, options.success, options.error);
            });
        },

        initToolbar: function() {
            var toolbar = this.toolbar;
            toolbar.find('li').unbind('click.product_list').bind('click.product_list', function() {
                var action = $(this).attr('data-action');
                if (!action) {
                    return;
                }
                var products = $.product_list.getSelectedProducts(action != 'delete');
                if (!products.count) {
                    alert($_('Please select at least one product'));
                    return false;
                }
                if (action == 'new-set') {
                    /*
                     * $.shop.jsonPost('?module=products&action=saveListSettings&set_id=0&parent_id=0', products.serialized.concat({ name : 'name', value : 'New
                     * set' }), function(r) { var url = '#/products/set_id=' + r.data.id + '&view=' + $.product_list.options.view; $('#s-set-list
                     * ul:first').trigger('add', [r.data, 'set', url]); location.href = url + '&edit=name'; });
                     */
                } else if (action == 'category') {
                    $.product_list.categoriesDialog(products);
                } else if (action == 'set') {
                    $.product_list.setsDialog(products);
                } else if (action == 'type') {
                    $.product_list.typesDialog(products);
                } else if (action == 'assign-tags') {
                    $.product_list.assignTagsDialog(products);
                } else if (action == 'delete') {
                    $.product_list.deleteProductsDialog(products);
                } else if (action == 'delete-from-set' && $.product_list.collection_hash[0] == 'set') {
                    $.product_list.deleteFromSet(products);
                } else if (action == 'delete-from-category' && $.product_list.collection_hash[0] == 'category') {
                    $.product_list.deleteFromCategory(products);
                } else if (action == 'export') {
                    $.product_list.exportProducts(products, $(this).attr('data-plugin'));
                }
                return false;
            });
            var menu_height = $('#mainmenu').height();
            var toolbar_top = toolbar.find(':first').offset().top - menu_height;
            $(document).bind('scroll', function() {
                if ($(this).scrollTop() > toolbar_top) {
                    toolbar.addClass('s-fixed').css({
                        top: menu_height
                    });
                } else {
                    toolbar.removeClass('s-fixed');
                }
            });
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
        getSelectedProducts: function(serialize) {
            serialize = serialize || false;
            var product_list = this.container;
            var data = {
                count: 0
            };
            if (product_list.find('.s-select-all').attr('checked')) {
                var hash = $.product_list.collection_hash.join('/') || 'all';
                if (serialize) {
                    data.serialized = [{
                                name: 'hash',
                                value: hash
                            }];
                } else {
                    data.hash = hash;
                }
                data.count = this.total_count;
            } else {
                if (serialize) {
                    data.serialized = $.product_list.container.find('.product.selected').map(function() {
                        data.count += 1;
                        return {
                            name: 'product_id[]',
                            value: $(this).attr('data-product-id')
                        };
                    }).toArray();
                } else {
                    data.product_id = $.product_list.container.find('.product.selected').map(function() {
                        data.count += 1;
                        return $(this).attr('data-product-id');
                    }).toArray();
                }
            }
            return data;
        },

        categoriesDialog: function(products) {
            var d = $('#s-categories');
            var sidebar = this.sidebar;
            var product_list = this.container;
            var showDialog = function() {
                $('#s-categories').waDialog({
                    disableButtonsOnSubmit: true,
                    onLoad: function() {
                        var self = $(this);
                        self.find('.dialog-content h1 span').text('(' + products.count + ')').show();
                        self.find('.dialog-buttons i.loading').hide();
                        self.find('input[name=new_category_name]').val('');
                        self.find('input[name=new_category]').attr('checked', false);
                    },
                    onSubmit: function(d) {
                        // addToCategories
                        var form = d.find('form');
                        form.find('.dialog-buttons i.loading').show();
                        $.shop.jsonPost(form.attr('action'), form.serializeArray().concat(products.serialized), function(r) {

                            // add new category to sidebar
                            if (r.data.new_category) {
                                $('#s-category-list ul:first').trigger('add',
                                [r.data.new_category, 'category', '#/products/category_id=' + r.data.new_category + '&view=' + $.product_list.options.view]);
                            }

                            // update cagegories in sidebar
                            if (r.data.categories) {
                                sidebar.trigger('update', [{
                                            category: r.data.categories
                                        }]);
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

        setsDialog: function(products) {
            var d = $('#s-sets');
            var sidebar = this.sidebar;
            var product_list = this.container;
            var showDialog = function() {
                $('#s-sets').waDialog({
                    disableButtonsOnSubmit: true,
                    onLoad: function() {
                        var self = $(this);
                        self.find('.dialog-content h1 span').text('(' + products.count + ')').show();
                        self.find('.dialog-buttons i.loading').hide();
                        self.find('input[name=new_set_name]').val('');
                        self.find('input[name=new_set]').attr('checked', false);
                    },
                    onSubmit: function(d) {
                        // addToSets
                        var form = d.find('form');
                        form.find('.dialog-buttons i.loading').show();
                        $.shop.jsonPost(form.attr('action'), form.serializeArray().concat(products.serialized), function(r) {

                            // add new category to sidebar
                            if (r.data.new_set) {
                                $('#s-set-list ul:first').trigger('add',
                                [r.data.new_set, 'set', '#/products/set_id=' + r.data.new_set + '&view=' + $.product_list.options.view]);
                            }

                            // update cagegories in sidebar
                            if (r.data.sets) {
                                sidebar.trigger('update', [{
                                            set: r.data.sets
                                        }]);
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

        assignTagsDialog: function(products) {
            var d = $('#s-assign-tags');
            var showDialog = function() {
                $('#s-assign-tags').waDialog({
                    disableButtonsOnSubmit: true,
                    onLoad: function() {
                        var self = $(this);
                        self.find('.dialog-content h1 span').text('(' + products.count + ')').show();
                        self.find('.dialog-buttons i.loading').hide();
                    },
                    onSubmit: function(d) {
                        var form = $(this);
                        form.find('.dialog-buttons i.loading').show();
                        // assignTags
                        $.shop.jsonPost(form.attr('action'), form.serializeArray().concat(products.serialized), function(r) {
                            if (r.data.cloud) {
                                $('#s-tag-cloud').trigger('update', [r.data.cloud]);
                            }
                            d.trigger('close');
                            $.products.dispatch();
                        }, function() {
                            d.trigger('close');
                        });
                        return false;
                    }
                });
            };

            // no cache dialog
            if (d.length) {
                d.remove();
            }

            // use post-method instead of get-method because of potential long list of product ids
            $.post('?module=dialog&action=assignTags', products.serialized, function(html) {
                $('body').append(html);
                showDialog();
            });
        },

        typesDialog: function(products) {
            var d = $('#s-types');
            var product_list = this.container;
            var sidebar = this.sidebar;
            var showDialog = function() {
                $('#s-types').waDialog({
                    disableButtonsOnSubmit: true,
                    onLoad: function() {
                        $(this).find('.dialog-buttons i.loading').hide();
                    },
                    onSubmit: function(d) {
                        var form = $(this);
                        form.find('.dialog-buttons i.loading').show();
                        $.shop.jsonPost(form.attr('action'), form.serializeArray().concat(products.serialized), function(r) {
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

        deleteFromSet: function(products) {
            if (products.count > 0) {
                $.shop.jsonPost('?module=products&action=deleteFromSet&id=' + this.collection_hash[1], products.serialized, function() {
                    $.products.dispatch();
                });
            }
        },

        deleteFromCategory: function(products) {
            if (products.count > 0) {
                $.shop.jsonPost('?module=products&action=deleteFromCategory&id=' + this.collection_hash[1], products.serialized, function() {
                    $.products.dispatch();
                });
            }
        },

        deleteProductsDialog: function(products) {
            var showDialog = function() {
                $('#s-product-list-delete-products-dialog').waDialog({
                    disableButtonsOnSubmit: true,
                    onLoad: function() {
                        $(this).find('.dialog-buttons i.loading').hide();
                    },
                    onSubmit: function(d) {
                        $(this).find('.dialog-buttons i.loading').show();
                        $.product_list.remove($.extend(products, {
                            remove: ['products']
                        }), function(r) {
                            $.product_list.sidebar.trigger('update', r.data.lists);
                            $.products.dispatch();
                            d.trigger('close');
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
            p.load('?module=dialog&action=productsDelete&count=' + products.count, showDialog);
        },

        settingsListDialog: function() {
            var showDialog = function() {

                // remove conflict dialog
                var conflict_dialog = $('#s-product-list-create-dialog');
                if (conflict_dialog.length) {
                    conflict_dialog.parent().remove();
                    conflict_dialog.remove();
                }

                $('#s-product-list-settings-dialog').waDialog({
                    disableButtonsOnSubmit: true,
                    onLoad: function() {
                        if ($('#s-category-description-content').length) {
                            $.product_sidebar.initCategoryDescriptionWysiwyg($(this));
                        }
                        setTimeout(function() {
                            $('#s-product-list-name').focus();
                        }, 50);
                    },
                    onSubmit: function(d) {
                        var form = $(this);
                        var success = function(r) {
                            var collection_hash = $.product_list.collection_hash;
                            var hash = location.hash.replace(collection_hash[0] + '_id=' + collection_hash[1], collection_hash[0] + '_id=' + r.data.id);
                            var li = $('#' + collection_hash.join('-'));
                            li.find('.name:first').html(r.data.name);
                            
                            if ($.isArray(r.data.routes) && r.data.routes.length) {
                                li.find('.routes:first').html(' ' + r.data.routes.join(' '));
                            } else {
                                li.find('.routes:first').html(' ');
                            }
                            
                            if (r.data.status == '0') {
                                li.children('a').addClass('gray');
                            } else if (r.data.status == '1') {
                                li.children('a').removeClass('gray');
                            }
                            li.find('.id:first').html(r.data.id);
                            li.attr('id', collection_hash[0] + '-' + r.data.id);
                            li.find('a').attr('href', hash);

                            if (location.hash != hash) {
                                location.hash = hash;
                            } else {
                                $.products.dispatch();
                            }
                            wa_editor = undefined;
                            d.trigger('close');
                        };
                        var error = function(r) {
                            if (r && r.errors) {
                                var errors = r.errors;
                                for (var name in errors) {
                                    d.find('input[name=' + name + ']').addClass('error').parent().find('.errormsg').text(errors[name]);
                                }
                                return false;
                            }
                            wa_editor = undefined;
                        };
                        if ($('#s-category-description-content').length) {
                            waEditorUpdateSource({
                                id: 's-category-description-content'
                            });
                        }

                        if (form.find('input:file').length) {
                            $.products._iframePost(form, success, error);
                        } else {
                            $.shop.jsonPost(form.attr('action'), form.serialize(), success, error);
                            return false;
                        }
                    },
                    onCancel: function() {
                        wa_editor = undefined;
                    }
                });
            };
            var d = $('#s-product-list-settings-dialog');
            var p;
            if (!d.length) {
                p = $('<div></div>').appendTo('body');
            } else {
                p = d.parent();
            }
            p.load('?module=dialog&action=productListSettings&' + $.product_list.collection_param, showDialog);
        },

        exportProducts: function(products, plugin) {
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

        remove: function(options, finish) {
            var count = 100;
            var params = {};
            var url = '?module=products&action=deleteList';
            var process;
            if (options.product_id) {
                process = function() {
                    if (options.product_id.length <= count) {
                        params.get_lists = true;
                    }
                    params.product_id = options.product_id.splice(0, count);
                    $.shop.jsonPost(url, params, function(r) {
                        if (options.product_id.length) {
                            process();
                        } else if (typeof finish === 'function') {
                            finish(r);
                        }
                    });
                };
            } else {
                params.hash = options.hash || (this.collection_hash.join('/') || 'all');
                params.remove = $.isArray(options.remove) && options.remove.length ? options.remove : ['list'];
                if (params.remove.length == 1 && params.remove[0] == 'list') {
                    process = function() {
                        $.shop.jsonPost(url, params, finish);
                    };
                } else {
                    params.count = count;
                    var rest_count = null; // previous rest count
                    process = function() {
                        $.shop.jsonPost(url, params, function(r) {
                            if (r.data.rest_count > 0 && rest_count != r.data.rest_count) {
                                process();
                            } else if (typeof finish === 'function') {
                                finish(r);
                            }
                        });
                    };
                }
            }

            process();

        }
    };
})(jQuery);
