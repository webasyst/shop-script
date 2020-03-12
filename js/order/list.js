( function($) {

    $.order_list = {

        /**
         * Current order
         * {Number}
         */
        id: 0,

        /**
         * {Object}
         * */
        options: {},

        /**
         * Jquery object related to list container
         * {Object|null}
         */
        container: null,

        /**
         * Jquery object
         * {Object|null}
         */
        $selectionMenu: null,

        /**
         * Jquery object related to sidebar
         * {Object|null}
         */
        sidebar: null,

        /**
         * Jquery object related to 'select all checkbox input'
         * {Object|null}
         */
        select_all_input: null,

        /**
         * Params by which list is filtered
         * {Object}
         */
        filter_params: {},

        /**
         * Params by which list is filtered (for passing to lazy-loader)
         * {String}
         */
        filter_params_str: '',

        /**
         * Params of dispatching
         * {Object}|null
         */
        params: null,

        /**
         * Id of timer
         * {Number}
         */
        timer_id: 0,

        /**
         * Jquery object to to which lazy_loader is bined
         * {Object}
         */
        lazy_load_win: null,

        /**
         * Cache of all printforms
         * {Object}|null
         */
        all_printforms: null,

        /**
         * Sort of list
         */
        sort: ['create_datetime', 'desc'],

        /**
         * Collection of xhr-deffered objects for synchronization
         * {Object}
         */
        xhrs: {
            lazy_load: null,
            update_load: null,
            printforms: null
        },


        init: function(options) {
            this.options = options = options || {};
            this.filter_params = options.filter_params || {};
            this.filter_params_str = options.filter_params_str || '';
            this.container = $('#order-list');

            if (options.view == 'table') {
                this.container = $('#order-list').find('tbody:first');
                this.$selectionMenu = this.options["$selectionMenu"];
                if (this.$selectionMenu) {
                    $("#mainmenu .s-level2").append(this.$selectionMenu);
                    this.select_all_input = this.$selectionMenu.find('.s-order-list-select-all');
                    this.select_all_input.attr('checked', false);
                }
            }

            // for define which actions available for whole order list (see onSelectItem)
            this.options.all_order_state_ids = this.options.all_order_state_ids || null;

            this.sidebar = $('#s-sidebar');
            this.id = options.id || 0;

            if (options.orders && options.orders.length && options.view) {
                try {
                    // template variants:
                    // template-order-list-table
                    // template-order-list-split
                    this.container.append(
                        tmpl('template-order-list-' + this.options.view, {
                            orders: options.orders
                        }, true
                    ));
                    this.container.trigger('append_order_list', [options.orders]);
                } catch (e) {
                    if (console) {
                        console.log(e.stack);
                    }
                    return this;
                }
                delete options.orders;
                delete this.options.orders;
            }

            if (options.lazy_loading) {
                this.initLazyLoad(options.lazy_loading);
            }

            if (options.counters) {
                this.updateCounters(options.counters);
            }

            if (options.update_process && options.count) {
                this.updateProcess('run', options.update_process);
            }

            if (options.sort && options.sort[0]) {
                options.sort[0] = '' + options.sort[0];
                var available_sort_values = $('#s-orders-sort .s-sort').map(function () {
                    var sort = $(this).data('sort') || '';
                    return sort ? sort : false;
                }).toArray();
                if (available_sort_values.indexOf(options.sort[0]) < 0) {
                    options.sort[0] = available_sort_values[0];
                }
                options.sort[1] = (options.sort[1] || '');
                options.sort[1] = options.sort[1].toLowerCase() === 'desc' ? 'desc' : 'asc';
                this.sort = options.sort;
            }

            this.initView();
        },

        initLazyLoad: function(options) {
            var self = this;
            var count = self.options.count;
            var total_count = self.options.total_count;

            var win = $(window);

            win.lazyLoad('stop');
            if (count < total_count) {
                win.lazyLoad({
                    container: this.options.view ? self.container : win,
                    state: (typeof options.auto === 'undefined' ? true: options.auto) ? 'wake' : 'stop',
                    hash: ['orders', ''],   // ['', 'orders']
                    load: function() {
                        win.lazyLoad('sleep');
                        $('.lazyloading-link').hide();
                        $('.lazyloading-progress').show();
                        var last_li = self.container.find('.order:last');
                        var id = parseInt(last_li.attr('data-order-id'), 10);
                        if (!id) {
                            console.log("Unkown last item");
                            win.lazyLoad('stop');
                            return;
                        }
                        var process = function() {
                            return $.getJSON(self.buildLoadListUrl(id),
                                    function (r) {

                                        if (r.status == 'ok') {
                                            try {
                                                self.container.append(
                                                    tmpl('template-order-list-' + self.options.view, {
                                                        orders: r.data.orders,
                                                        check_all: self.options.view == 'table' ? self.select_all_input.attr('checked') : false
                                                    }
                                                ));
                                                self.container.trigger('append_order_list', [r.data.orders]);
                                                var order = self.container.find('[data-order-id='+self.options.id+']');
                                                if (order.length) {
                                                    self.container.find('.selected').removeClass('selected');
                                                    self.container.find('[data-order-id='+self.options.id+']').addClass('selected');
                                                }
                                            } catch (e) {
                                                if (console) {
                                                    console.log(e.stack);
                                                }
                                                win.lazyLoad('stop');
                                                return;
                                            }

                                            $('.lazyloading-progress-string').text(r.data.progress.loaded + ' ' + r.data.progress.of);
                                            $('.lazyloading-progress').hide();
                                            $('.lazyloading-chunk').text(r.data.progress.chunk);

                                            if (r.data.loaded >= r.data.total_count) {
                                                win.lazyLoad('stop');
                                                $('.lazyloading-link').hide();
                                            } else {
                                                $('.lazyloading-link').show();
                                                win.lazyLoad('wake');
                                            }
                                        } else {
                                            if (console) {
                                                console.log('Error when loading orders: ' + r.errors);
                                            }
                                            win.lazyLoad('stop');
                                        }
                                    }
                                ).error(function(r) {
                                    if (console) {
                                        if (r && r.errors) {
                                            console.log('Error when loading orders: ' + r.errors);
                                        } else if (r) {
                                            console.log(r);
                                        } else {
                                            console.log('Error when loading orders');
                                        }
                                    }
                                    win.lazyLoad('stop');
                                });
                        };
                        if (self.xhrs.update_load === null) {
                            self.xhrs.lazy_load = process();
                        } else {
                            self.xhrs.update_load.then(function() {
                                self.xhrs.lazy_load = process();
                            });
                        }
                    }
                });
                $('#s-orders').off('click', '.lazyloading-link').
                    on('click', '.lazyloading-link', function() {
                        win.lazyLoad('force');
                        return false;
                    }
                );
                this.lazy_load_win = win;
            }
        },

        initView: function() {
            var that = this;

            this.initSortMenu();

            this.initSidebar();
            if (this.options.view == 'table' && that.$selectionMenu && that.$selectionMenu.length) {
                this.initSelecting();
            }
            if (this.options.id) {
                this.loadOrder(this.options.id);
            }
            var orders_view_ul = $('#s-orders-views');
            orders_view_ul.find('li.selected').removeClass('selected');
            orders_view_ul.find('li[data-view="'+this.options.view+'"]').addClass('selected');
            orders_view_ul.find('li a').each(function() {
                var self = $(this);
                var li = $(this).parents('li:first');
                self.attr('href', '#/orders/view=' + li.attr('data-view') + ($.order_list.options.filter_params_str ? '&'+$.order_list.options.filter_params_str : '') + '/');
            });


            var performAction = function(action_id, selected_orders, onFinish) {

                // ensure that we load chunk of order list to common list of orders
                var ensureOrderListChunkLoaded = function () {
                    var $win = $(window),
                        def = $.Deferred();

                    // make sense only for all selected orders and lazy loading initialized and not yet loaded all list of orders
                    if (!selected_orders.all || !that.options.lazy_loading || $win.lazyLoad('get', 'stopped')) {
                        def.resolve();
                        return def;
                    }

                    var event_name = 'append_order_list.ensureOrderListChunkLoaded';

                    that.container.one(event_name, function () {
                        def.resolve();
                    });

                    // safe timeout - after 2 seconds conclude that we load chunk anyway
                    setTimeout(function () {
                        that.container.off(event_name);
                        def.resolve();
                    }, 2000);

                    // load chunk
                    $win.lazyLoad('force');

                    return def;
                };

                // update chunk of order list (change status)
                var updateOrderListChunk = function (r) {
                    var def = $.Deferred();

                    if ($.isEmptyObject(r.data.orders)) {
                        def.resolve();
                        return def;
                    }

                    var event_name = 'append_order_list.updateOrderListChunk';

                    that.container.one(event_name, function () {
                        def.resolve();
                    });

                    // safe timeout - after 1 second conclude that we load chunk anyway
                    setTimeout(function () {
                        that.container.off(event_name);
                        def.resolve();
                    }, 1000);

                    that.updateListItems(r.data.orders);

                    return def;
                };

                // hide chunk of order list after some timeout
                var hideOrderListChunk = function (r) {
                    var def = $.Deferred();

                    var showEmptyListHtml = function () {
                        if (!that.container.find('.order:not(:hidden):first').length) {
                            var html = '<div class="block double-padded align-center blank"><br><br><br><br><span class="gray large">'+$_("There are no orders in this view.")+'</span><div class="clear-left"></div></div></div>';
                            $('#s-content').html(html);
                        }
                    };

                    var ids = [];
                    var filter_params = that.filter_params;
                    if (!$.isEmptyObject(r.data.orders)) {
                        for (var i = 0, n = r.data.orders.length; i < n; i++) {
                            if (!$.isEmptyObject(filter_params) && filter_params.state_id) {
                                if ($.type(filter_params.state_id) === 'string' && filter_params.state_id !== r.data.orders[i].state_id) {
                                    ids.push(r.data.orders[i].id);
                                } else if ($.type(filter_params.state_id) === 'array' && filter_params.state_id.indexOf(r.data.orders[i].state_id) === -1) {
                                    ids.push(r.data.orders[i].id);
                                }
                            }
                        }
                    }

                    if (!$.isEmptyObject(ids)) {
                        that.hideListItems(ids).done(function() {
                            showEmptyListHtml();
                            def.resolve();
                        });

                        // safe timeout - after 1 second conclude that we load chunk anyway
                        setTimeout(function () {
                            def.resolve();
                        }, 1000);

                    } else {
                        showEmptyListHtml();
                        def.resolve();
                    }

                    return def;
                };

                var updateSidebarCounters = function (r) {
                    $.order_list.updateCounters({
                        state_counters: r.data.state_counters,
                        common_counters: {
                            pending: r.data.pending_count
                        }
                    });
                };

                var eachStep = function (r) {
                    var def = $.Deferred();

                    ensureOrderListChunkLoaded().done(function () {
                        updateOrderListChunk(r).done(function () {
                            hideOrderListChunk(r).done(function () {
                                updateSidebarCounters(r);
                                //that.count
                                def.resolve();
                            });
                        })
                    });

                    return def;
                };

                var step = function(offset, onFinish) {
                    offset = offset || 0;

                    $.shop.jsonPost(
                        '?module=orders&action=performAction' +
                            '&id='+action_id +
                            '&offset=' + offset +
                            '&' + that.options.filter_params_str,
                        selected_orders,
                        function(r) {
                            if (r.status === 'ok') {
                                eachStep(r).done(function () {
                                    if (r.data.offset < r.data.total_count) {
                                        step(r.data.offset, onFinish);
                                    } else {
                                        onFinish(r);
                                    }
                                });
                            } else {
                                onFinish(r);
                            }
                        }
                    );
                };

                step(0, onFinish);
            };

            if (that.$selectionMenu) {
                that.$selectionMenu
                    .off("click.order_list", ".js-wf-action-item")
                    .on("click.order_list", ".js-wf-action-item",
                        function () {
                            var $item = $(this),
                                action_id = $item.data("action-id");

                            // disabled menu item
                            if ($item.hasClass('s-disabled')) {
                                return;
                            }

                            if (action_id) {
                                onActionClick(action_id);
                            }
                            return false;
                        }
                    );
            }

            function onActionClick( action_id ) {

                var selected_orders = $.order_list.getSelectedOrders();

                var perform = function () {

                    that.$selectionMenu.addClass('s-disabled');
                    that.$selectionMenu.find('.js-selection-menu-loading').show();
                    that.select_all_input.attr('disabled', true);

                    performAction(action_id, selected_orders, function () {
                        that.$selectionMenu.removeClass('s-disabled');
                        that.$selectionMenu.find('.js-selection-menu-loading').hide();
                        that.select_all_input.removeAttr('disabled');
                        that.select_all_input.trigger('select', false);

                        $.orders.dispatch();
                    });
                };

                if (selected_orders.all) {
                    if (confirm($_('Perform action to all selected orders?'))) {
                        perform();
                    }
                } else {
                    perform();
                }
            }

        },

        loadOrder: function(order_id) {
            this.container.find('.selected').removeClass('selected');
            this.container.find('[data-order-id=' + order_id + ']').addClass('selected');

            var order_title = $('#s-order-title');
            order_title.find('.loading').show();
            $.orders.load(
                '?module=order&id= ' + order_id + '&' + this.filter_params_str,
                { content: $('#s-order') },
                function() {
                    this.id = order_id;
                }
            );
        },

        getSelectedOrders: function(serialize) {
            serialize = serialize || false;
            var data = { count: 0, all: false };
            if (this.select_all_input.attr('checked')) {
                data.all = true;
                var filter_params = $.order_list.filter_params;
                if (serialize) {
                    data.serialized = [];
                    if ($.isEmptyObject(filter_params)) {
                        data.serialized.push({ name: 'filter_params', value: '' });
                    } else {
                        $.each(filter_params, function (key, value) {
                            if ($.isArray(value)) {
                                $.each(value, function (k, v) {
                                    data.serialized.push({ name: 'filter_params[' + key + '][' + k + ']', value: v });
                                });
                            } else {
                                data.serialized.push({ name: 'filter_params[' + key + ']', value: value });
                            }
                        });
                    }
                } else {
                    data.filter_params = $.isEmptyObject(filter_params) ? '' : filter_params;
                }
                data.count = this.options.total_count;
            } else {
                if (serialize) {
                    data.serialized = $.order_list.container.find('.order.selected').map(function() {
                        data.count += 1;
                        return { name: 'order_id[]', value: $(this).attr('data-order-id') };
                    }).toArray();
                } else {
                    data.order_id = $.order_list.container.find('.order.selected').map(function() {
                        data.count += 1;
                        return $(this).attr('data-order-id');
                    }).toArray();
                }
            }
            return data;
        },

        initSortMenu: function () {

            var $menu = $('#s-orders-sort');

            // update ui menu helper
            var update = function (sort, change_hash) {
                var sort_field = sort[0];
                var sort_order = sort[1];
                var text = $('.s-sort[data-sort="' + sort_field + '"] a', $menu).text();
                $menu.find('.s-current-sort').data('sort', sort_field);
                $menu.find('.s-current-sort').data('order', sort_order);
                $menu.find('.s-current-sort .f-text').text(text);
                $menu.find('.s-sort-order').hide().filter('[data-order="' + sort_order + '"]').show();
                if (change_hash) {

                    var hash = window.location.hash || '';

                    hash = hash.replace(/(^[^#]*#\/*|\/$)/g, ''); /* fix syntax highlight*/

                    if (!hash.split('/')[1]) {
                        hash = '#/orders/state_id=new|processing|auth|paid'
                    }

                    // clear hash, delete substring like sort[0]=foo and sort[1]=bar
                    hash = hash.replace(/(&*sort\[[01]\]=.*?[&\/]|&*sort\[[01]\]=.*?$)/g, '');

                    // check if exists any params in hash
                    var params_tail_exists = hash.indexOf('=') > 0;

                    if (params_tail_exists) {
                        // delete / and & in the end of hash
                        hash = hash.replace(/[\/&]$/, '');
                    }
                    // append new sort params
                    hash += (params_tail_exists ? '&' : '') + 'sort[0]=' + sort_field + '&sort[1]=' + sort_order + '/';

                    // update page
                    $.wa.setHash(hash);

                }
            };

            update(this.sort);

            // prevent binding events twice, cause menu item located in layout block and it isn't updated when inner content changed
            if (!$menu.data('inited')) {

                // when click to option click, change sort and order
                $menu.find('.s-sort a').click(function () {
                    var el = $(this);
                    var data = $menu.find('.s-current-sort').data();
                    var sort_field = data.sort;
                    var sort_order = data.order;
                    if (el.data('sort') === sort_field) {
                        sort_order = sort_order === 'desc' ? 'asc' : 'desc';
                    } else {
                        sort_field = el.data('sort');
                        sort_order = 'desc';
                    }
                    update([sort_field, sort_order], true);
                });

                $menu.data('inited', 1);
            }
        },

        initSidebar: function() {
            var sidebar = this.sidebar;

            // Replace list view type in all links in sidebar
            var view = this.options.view;
            sidebar.find('li.list a').each(function() {
                var item = $(this);
                var href = item.attr('href');
                var match = href.match(/view=((.*)&|(.*)\/|(.*))/);
                if (match) {
                    item.attr('href', href.replace('view='+(match[2]||match[3]||match[4]), 'view='+view));
                } else if ( ( match = href.match(/^#\/orders\/hash\/(.*?)\/?$/))) {/* */
                    item.attr('href', '#/orders/view='+view +'&hash='+encodeURIComponent(match[1])+'/');
                } else {
                    match = href.match(/orders\/((.*)\/|(.*))/);
                    var chunk = '';
                    if (match) {
                        if (match[1]) {
                            chunk = match[2] || match[3];
                            chunk += '&view=' + view;
                        } else {
                            chunk = 'view=' + view;
                        }
                        item.attr('href', '#/orders/' + chunk + '/');
                    }
                }
            });

            // Change active list view selection
            var $prev_li_selected = sidebar.find('li.selected').removeClass('selected');
            if (this.filter_params.state_id) {
                // Highlight a state
                if ($.isArray(this.filter_params.state_id)) {
                    $('#s-pending-orders').addClass('selected');
                } else {
                    sidebar.find('li[data-state-id="'+this.filter_params.state_id+'"]').addClass('selected');
                }
            } else if (this.filter_params.contact_id) {
                sidebar.find('li[data-contact-id='+this.filter_params.contact_id+']').addClass('selected');
            } else if (this.filter_params.storefront) {
                // Highlight storefront
                var decoded_url = decodeURIComponent(this.filter_params.storefront);
                if (decoded_url.slice(-1) === '/') {
                    decoded_url = decoded_url.slice(0, -1);
                }
                sidebar.find('li[data-storefront="'+decoded_url+'"]').addClass('selected');
            } else if (!$.order_list.filter_params_str) {
                $('#s-all-orders').addClass('selected');
            } else {
                // Do our best to highlight based on hash match
                var $li = sidebar.find('[href="'+window.location.hash+'"]:first').closest('li').addClass('selected');

                // When everything failed, leave the old selection
                if (!$li.length) {
                    $prev_li_selected.addClass('selected');
                }
            }

            sidebar.find('li.list a').unbind('click.order_list').bind('click.order_list', function() {
                // Highlight link in sidebar right avay after a click to be responsive.
                sidebar.find('li.selected').removeClass('selected');
                $(this).closest('li').addClass('selected');

                // Reload view even if user clicked on the active link again
                var hash = $(this).attr('href').replace(/(^[^#]*#\/*|\/$)/g, ''); /* fix syntax highlight*/
                if (hash == $.orders.hash) {
                    var params = $.orders.hash.replace('orders/', '');
                    $.order_list.dispatch(params, true);
                }
            });
        },

        /**
         * Update UI enable/disable statuses of selection menu items by workflow states
         * @param {Array|String} state_id One or list of workflow states available now
         */
        updateSelectionMenuActionItemsByStates: function(state_id) {
            var that = this;

            // state_ids, typecast input argument
            var state_ids = [];
            if (typeof state_id === 'string') {
                state_ids.push(state_id);
            } else if ($.isArray(state_id)) {
                state_ids = [].concat(state_id)
            } else {
                // unsupported input type
                return;
            }

            var $action_items = that.$selectionMenu.find('.wf-actions .js-wf-action-item');

            // all items disabled at beginning
            $action_items.addClass('s-disabled');

            // state_id => [action_id]
            var enabled_states_actions = {};

            // collect { state_id => [action_id] } map, for each state get list of available actions
            $action_items.each(function () {
                var $item = $(this),
                    action_id = $item.data('actionId'),
                    available_for_states_str = $item.data('availableForStates'),
                    available_for_states = available_for_states_str.split(',');

                // loop over state_ids and define available actions for each state
                for (var i = 0; i < state_ids.length; i++) {
                    var current_state_id = state_ids[i];

                    // ensure that state is string (cause state could be possible integer and indexOf will not works)
                    current_state_id = '' + current_state_id;

                    if (available_for_states.indexOf(current_state_id) !== -1) {
                        enabled_states_actions[current_state_id] = enabled_states_actions[current_state_id] || [];
                        enabled_states_actions[current_state_id].push(action_id);
                    }
                }
            });

            // now calculate intersection for all states we has
            var enable_actions = $.shop.intersectArrays($.shop.getValues(enabled_states_actions), true);

            // and UI enable actions itself
            if (enable_actions.length > 0) {
                var $enabled_items = $action_items.filter(function () {
                    return $.inArray($(this).data('actionId'), enable_actions) > -1;
                });
                $enabled_items.removeClass('s-disabled');
            }
        },

        enableAllSelectionMenuActionItems: function() {
            var that = this;
            that.$selectionMenu.find('.wf-actions .js-wf-action-item').removeClass('s-disabled');
        },

        disableAllSelectionMenuActionItems: function() {
            var that = this;
            that.$selectionMenu.find('.wf-actions .js-wf-action-item').addClass('s-disabled');
        },

        initSelecting: function() {
            var that = this,
                container = this.container,
                select_all_input = this.select_all_input;


            // update ui state on action items in selection menu
            if (that.filter_params && that.filter_params.state_id && select_all_input.is(':checked')) {
                that.updateSelectionMenuActionItemsByStates(that.filter_params.state_id);
            }

            select_all_input.click(function() {
                $(this).trigger('select', this.checked);
            });


            // Hide action panel if user open other window
            $(window).one('wa_before_dispatched',function (e) {
                that.$selectionMenu.hide();
            });

            that.xhrs.printforms = null;

            var renderPrintforms = function(data) {
                var html = tmpl('template-order-list-printforms-menu-items', data);
                var $ul = that.$selectionMenu.find('.wf-actions');
                $ul.find('.s-printform-item,.s-printform-item-sep,.s-printform-item-button').remove();
                $ul.append(html)
            };

            // when 'shift' held on prevent default browser selecting
            $(document).keydown(function(e) {
                if (e.keyCode == 16 && !$(e.target).closest('.redactor-box').length) {
                    document.body.onselectstart = function() { return false; };
                }
            }).keyup(function(e) {
                if (e.keyCode == 16) {
                    document.body.onselectstart = null;
                }
            });

            var onSelectItem = function() {

                var $table = $('#order-list'),
                    is_all_selected = select_all_input.attr("checked"),
                    is_one_selected = ( $table.find('.order.selected:first').length ),
                    is_selected = is_all_selected || is_one_selected;

                // guard case - hide menu and this just it
                if (!is_selected) {
                    that.$selectionMenu.hide();
                    return;
                }

                // below a little bit more complicated logic

                // first of all show menu
                that.$selectionMenu.show();

                // current list of order states (null means not defined for some reason)
                var state_ids = null;

                if (is_all_selected) {
                    if (that.options.all_order_state_ids) {                             // option from controller
                        state_ids = that.options.all_order_state_ids;
                    } else if (that.filter_params && that.filter_params.state_id) {     // or filter by state_id
                        state_ids = that.filter_params.state_id;
                    } else {
                        state_ids = null;                                               // or undefined (select all actions)
                    }
                } else {
                    // Extract all state ids from DOM items
                    // Not so effective solution, but simple to understand
                    // For example effective solution could be: keep track { state_id => count } map to dynamically has all "selected" state ids in current moment
                    state_ids = that.container.find('.order.selected').map(function() {
                        return $(this).data('stateId');
                    }).toArray();
                }

                // update UI state of menu
                if (state_ids !== null) {
                    state_ids = $.shop.getUniqueValues(state_ids);
                    that.updateSelectionMenuActionItemsByStates(state_ids);
                } else {
                    that.enableAllSelectionMenuActionItems();
                }

            };

            // handler of triggerable 'select' event
            container.off('select', '.order').on('select', '.order', function(e, selected) {
                selected = selected !== undefined ? selected : true;
                if (selected) {
                    $(this).addClass('selected').find('input:first').attr('checked', true);
                } else {
                    $(this).removeClass('selected').find('input:first').attr('checked', false);
                    if (select_all_input.is(':checked')) {
                        select_all_input.attr('checked', false);
                    }
                }
                onSelectItem();
                return false;
            });

            var loadPrintforms = function () {
                that.xhrs.printforms && that.xhrs.printforms.abort();

                renderPrintforms({
                    printforms: []
                });
                that.xhrs.printforms = null;
                if (select_all_input.is(':checked') && $.order_list.all_printforms !== null) {
                    renderPrintforms({
                        printforms: $.order_list.all_printforms
                    });
                    return;
                }

                that.xhrs.printforms = $.shop.getJSON(
                    '?module=orders&action=printforms',
                    that.getSelectedOrders(),
                    function (r) {
                        if (!$.isEmptyObject(r.data.printforms)) {
                            if (select_all_input.is(':checked')) {
                                $.order_list.all_printforms = r.data.printforms;
                            }
                            renderPrintforms(r.data);
                            that.xhrs.printforms = null;
                        }
                    }
                );
            };

            select_all_input.unbind('select').bind('select', function(e, selected) {
                selected = selected !== undefined ? selected : true;
                var self = $(this);
                if (selected) {
                    self.attr('checked', true);
                    container.find('.order').trigger('select', true, false);
                } else {
                    self.attr('checked', false);
                    container.find('.order').trigger('select', false, false);
                }
                loadPrintforms();
            });

            container.off('click', '.order input').on('click', '.order input', function(e) {
                var shiftKey = e.shiftKey,
                    checked = this.checked;
                var self = $(this).parents('.order:first');

                if (checked) {
                    self.addClass('selected');
                } else {
                    if (select_all_input.is(':checked')) {
                        select_all_input.attr('checked', false);
                    }
                    self.removeClass('selected');
                }

                if (shiftKey && checked) {   // when hold shift
                    var started = container.data('last_checked');
                    if (!started) {
                        started = container.find('.order:first').trigger('select', true);
                    }

                    // try find started before current
                    var found = self.prevAll('.selected[data-order-id='+started.attr('data-order-id')+']');
                    var item;
                    if (found.length) {
                        item = self.prev();
                        started = started.get(0);
                        while (item.length && started != item.get(0)) {
                            item.addClass('selected').find('input').attr('checked', true);
                            item = item.prev();
                        }
                    } else {
                        found = self.nextAll('.selected[data-order-id='+started.attr('data-order-id')+']');
                        if (found.length) {
                            item = self.next();
                            started = started.get(0);
                            while (item.length && started != item.get(0)) {
                                item.addClass('selected').find('input').attr('checked', true);
                                item = item.next();
                            }
                        }
                    }
                    if (!container.data('last_checked') && !found.length) {
                        started.trigger('selected', false);
                    }
                }
                if (checked) {
                    container.data('last_checked', self);
                }
                onSelectItem();
                loadPrintforms();

            });

            var printPrintforms = function(printforms, selected_orders) {

                var limit = 100;
                if (selected_orders.count > limit) {
                    alert($_('Maximum of %d orders is allowed for bulk form printing.').replace('%d', limit));
                    return;
                }

                var url = '?module=orders&action=printformsDisplay';

                var params_str = $.map(selected_orders.serialized, function (item) {
                    return item.name + '=' + item.value;
                }).join('&');
                var forms_str = $.map(printforms, function (form) {
                    return 'form[]=' + form;
                }).join('&');

                url += '&' + params_str + '&' + forms_str;

                var target_id = ('' + Math.random()).slice(2);
                window.open(url, target_id);
            };

            that.$selectionMenu.off('click.printforms').on('click.printforms', '.s-printform-item-button', function (e) {
                e.preventDefault();

                that.xhrs.printforms && that.xhrs.printforms.abort();
                that.xhrs.printforms = null;

                // collect forms
                var forms = that.$selectionMenu.find('.s-printform-item :checkbox').map(function () {
                    var $this = $(this);
                    var checked = $this.is(':checked');
                    if (!checked) {
                        return false;
                    }
                    return $this.data('id');
                }).toArray();

                if (!forms.length) {
                    return;
                }

                var selected_orders = that.getSelectedOrders(true);
                var count = selected_orders.count;
                if (count <= 0) {
                    return;
                }

                printPrintforms(forms, selected_orders);

                return false;
            });

        },

        updateTitle: function(title_suffix, count) {
            count = parseInt(count, 10) || 0;

            // context is mentioned in title
            var context = '';
            if (this.options.state_names) {
                if (this.filter_params.state_id) {
                    if (typeof this.filter_params.state_id === 'string') {
                        var state_id = this.filter_params.state_id;
                        context = this.options.state_names[state_id];
                    } else if ($.isArray(this.filter_params.state_id)) {
                        context = $_('Processing');
                    }
                } else {
                    context = $_('All orders');
                }
            }

            if (count) {
                document.title = '(' + count + ') ' + context + (title_suffix || '');
            } else {
                document.title = context + title_suffix;
            }
        },

        /**
         * @param {Object} counters
         *
         * Format: {
         *     state_counters: {
         *         new: '10' // string. Support incrementing, i.e.: '+5', '-7',
         *         processing: '10' // ....
         *     },
         *     common_counters: {
         *         pending: '5' // pending counter
         *     }
         * }
         *
         */
        updateCounters: function(counters) {
            var sidebar = this.sidebar;
            if (!sidebar) {
                sidebar = $('#s-sidebar');
            }
            var ext_new_counter = $('#s-pending-orders .small');
            for (var name in counters) {
                if (counters.hasOwnProperty(name)) {
                    var cntrs = counters[name];
                    for (var id in cntrs) {
                        if (cntrs.hasOwnProperty(id)) {
                            var item;
                            if (name == 'common_counters') {
                                item = $('#s-' + id + '-orders .count');
                            } else {
                                if (name === 'storefront_counters') {
                                    item = sidebar.find('li[data-'+name.replace('_counters', '')+'="'+id+'"] .count');
                                } else {
                                    item = sidebar.find('li[data-'+name.replace('_counters', '')+'-id='+id+'] .count');
                                }
                            }
                            var prev_cnt = parseInt(item.text(), 10) || 0;
                            var cnt = 0;
                            cntrs[id] = '' + cntrs[id];
                            if (cntrs[id].substr(0, 1) == '+') {
                                cnt = prev_cnt + (parseInt(cntrs[id].substr(1), 10) || 0);
                                item.text(cnt);
                            } else if (cntrs[id].substr(0, 1) == '-') {
                                cnt = prev_cnt - (parseInt(cntrs[id].substr(1), 10) || 0);
                                cnt = cnt < 0 ? 0 : cnt;
                                item.text(cnt);
                            } else {
                                cnt = parseInt(cntrs[id], 10) || 0;
                                item.text(cnt);
                            }
                            if (id == 'new') {
                                ext_new_counter.text(cnt ? '+' + cnt : '');
                                $.shop.updateAppCounter(cnt);
                                this.updateTitle(this.options.title_suffix, parseInt(cnt, 10));
                            }
                        }
                    }
                }
            }
        },

        updateListItems: function(data) {
            var self = this;
            var tmpl_name = 'template-order-list-'+this.options.view;
            if (document.getElementById(tmpl_name)) {
                var container = this.container;
                if (!$.isArray(data)) {
                    data = [data];
                }
                var rendered = $('<div></div>').append(tmpl(tmpl_name, {
                    orders: data,
                    check_all: self.options.view == 'table' ? self.select_all_input.attr('checked') : false
                }));
                var context = $('.order', container);
                $('.order', rendered).each(function() {
                    var item = $(this);
                    context.filter('[data-order-id='+item.attr('data-order-id')+']').replaceWith(item);
                });
                rendered.remove();
                self.container.trigger('append_order_list', [data]);
            }
        },

        hideListItems: function(id) {
            // deffered fiber
            var d = $.Deferred();
            setTimeout(function() {
                if ($.order_list && $.order_list.container && $.order_list.container.length) {
                    if (!$.isArray(id)) {
                        var items = $.order_list.container.find('.order[data-order-id='+id+']');
                    } else {
                        var context = $.order_list.container.find('.order');
                        var items = $();
                        for (var i = 0; i < id.length; i++) {
                            var item = context.filter('[data-order-id='+id[i]+']');
                            items = items.add(item);
                        }
                    }
                    var length = items.length;
                    var count = 0;
                    items.slideUp(450, function() {
                        count++;
                        if (count >= length) {
                            d.resolve();
                        }
                    });
                }
            }, 1000);
            return d.promise();
        },

        dispatch: function(params, hard) {
            if (typeof params === 'string') {
                params = $.shop.helper.parseParams(params);
            }
            var loaders = {
                list: function() {
                    $.order_list.finit();
                    $.orders.load('?module=orders&' + $.orders.buildOrdersUrlComponent(params), function() {
                        $.order_list.dispatch(params);
                    });
                },
                order: function() {
                    $.order_list.loadOrder(params.id);
                }
            };

            if (this.params === null) {     // order-list is just loaded
                this.params = params;
                if (hard) {
                    loaders.list();
                }
                return;
            }
            if (params.id) {  // order-list is loaded and id is changing
                if (hard || params.id != this.params.id) {
                    loaders.order();
                    this.params = params;
                    return;
                }
            }
            // params of order-list is changing
            if (hard || this.diff(params, this.params) || this.diff(this.params, params)) {
                loaders.list();
            }
        },

        diff: function(params1, params2) {
            for (var name in params1) {
                if (!params1.hasOwnProperty(name) || name == 'id') {
                    continue;
                }
                if (params1[name] !== params2[name]) {
                    return true;
                }
            }
            return false;
        },

        /**
         * @param {Number} id
         * @param {Boolean} lt (less than order with id, i.e. new orders). Default: falsy
         * @param {Boolean} counters (load list counters). Default: falsy
         * @returns {String}
         */
        buildLoadListUrl: function(id, lt, counters) {
            return '?module=orders&action=loadList&id=' + id +
                (this.filter_params_str ? '&' + this.filter_params_str : '') +
                (lt ? '&lt=1' : '') +
                (counters ? '&counters=1' : '') +
                (this.options.view ? '&view='+this.options.view : '') +
                ('&sort[0]=' + this.sort[0] + '&sort[1]=' + this.sort[1]);
        },

        updateProcess: function(status, options) {
            status = status || 'run';
            options = options || {};
            timeout = options.timeout || 60000;
            var self = this;
            var killProcess = function() {
                if (self.timer_id !== null) {
                    clearTimeout(self.timer_id);
                    self.timer_id = null;
                }
                if (self.xhrs.update_load !== null) {
                    self.xhrs.update_load.abort();
                    self.xhrs.update_load = null;
                }
            };
            killProcess();

            if (status == 'run') {
                var process = function(success, error) {
                    var first_li = self.container.find('.order:first');
                    var id = parseInt(first_li.attr('data-order-id'), 10) || 0;
                    return $.getJSON(self.buildLoadListUrl(id, true, true),
                            function (r) {
                                if (r.status == 'ok') {
                                    if (!$.isEmptyObject(r.data.counters)) {
                                        self.updateCounters(r.data.counters);
                                    }

                                    if (r.data.count) {
                                        try {
                                            self.container.prepend(
                                                tmpl('template-order-list-' + self.options.view, {
                                                    orders: r.data.orders,
                                                    check_all: self.options.view == 'table' ? self.select_all_input.attr('checked') : false
                                                }
                                            ));
                                            $('.lazyloading-progress-string').text(r.data.progress.loaded + ' ' + r.data.progress.of);
                                            self.container.trigger('append_order_list', [r.data.orders]);
                                        } catch (e) {
                                            if (console) {
                                                console.log('Error: ' + e.message);
                                                error();
                                            }
                                            return;
                                        }
                                    }
                                    if (typeof success === 'function') {
                                        success(r);
                                    }
                                } else {
                                    if (console) {
                                        console.log('Error when loading new orders: ' + r.errors);
                                    }
                                    if (typeof error === 'function') {
                                        error();
                                    }
                                }
                            }
                        ).error(function(r) {
                            if (console) {
                                if (r && r.errors) {
                                    console.log('Error when loading new orders: ' + r.errors);
                                } else {
                                    console.log(['Error when loading new orders', r]);
                                }
                            }
                            if (typeof error === 'function') {
                                error();
                            }
                        });
                };
                var runProcess = function() {
                    self.timer_id = setTimeout(function() {
                        var pr = function() {
                            return process(runProcess, killProcess);
                        };
                        if (self.xhrs.lazy_load === null) {
                            self.xhrs.update_load = pr();
                        } else {
                            self.xhrs.lazy_load.then(function() {
                                self.xhrs.update_load = pr();
                            });
                        }
                    }, timeout);
                };
                runProcess();
            }
        },

        /**
         * Finiting inited process
         * @param {Boolean?} destruct If true destructing object (delete)
         */
        finit: function(destruct) {
            var xhrs = this.xhrs, win = this.lazy_load_win;
            for (var k in xhrs) {
                if (xhrs.hasOwnProperty(k) && xhrs[k] !== null) {
                    xhrs[k].abort();
                }
            }
            this.updateProcess('kill');
            if (win) {
                win.lazyLoad('stop');
            }
            this.params = null;
        }
    };

})(jQuery);
