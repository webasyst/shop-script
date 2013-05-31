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
     * Collection of xhr-deffered objects for synchronization
     * {Object}
     */
    xhrs: {
        lazy_load: null,
        update_load: null
    },

    init: function(options) {
        this.options = options || {};
        this.filter_params = options.filter_params || {};
        this.filter_params_str = options.filter_params_str || '';
        this.container = $('#order-list');
        if (options.view == 'table') {
            this.select_all_input = this.container.find('.s-select-all');
            this.container = $('#order-list').find('tbody:first');
        }
        this.sidebar = $('#s-sidebar');
        this.id = options.id || 0;

        if (options.orders && options.orders.length && options.view) {
            try {
                this.container.append(
                    tmpl('template-order-list-' + this.options.view, {
                        orders: options.orders
                    }, true
                ));
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

        this.updateCounters({
            state_counters: {
                'new': options.count_new
            }
        });

        if (options.update_process && options.count) {
            this.updateProcess('run', options.update_process);
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
                            ).error(function() {
                                if (console) {
                                    console.log('Error when loading orders: ' + r.errors);
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
        this.initSidebar();
        /*
        if (this.options.view == 'table') {
            this.initSelecting();
        }
        */
        if (this.options.id) {
            this.loadOrder(this.options.id);
        }
        var orders_view_ul = $('#s-orders-views');
        orders_view_ul.find('li.selected').removeClass('selected');
        orders_view_ul.find('li[data-view='+this.options.view+']').addClass('selected');
        orders_view_ul.find('li a').each(function() {
            var self = $(this);
            var li = $(this).parents('li:first');
            self.attr('href', '#/orders/view=' + li.attr('data-view') + ($.order_list.options.filter_params_str ? '&'+$.order_list.options.filter_params_str : '') + '/');
        });

        /*
        $('#order-list').find('.wf-actions a').click(function() {
            var self = $(this);
            var id = self.attr('data-action-id');
            $.shop.jsonPost('?module=orders&action=performAction&id='+id, $.order_list.getSelectedOrders(), function() {
                $.orders.dispatch();
            });
            return false;
        });
        */
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

    /*
    getSelectedOrders: function(serialize) {
        serialize = serialize || false;
        var data = { count: 0 };
        if (this.select_all_input.attr('checked')) {
            var filter_params = $.order_list.filter_params;
            if (serialize) {
                data.serialized = [];
                if ($.isEmptyObject(filter_params)) {
                    data.serialized.push({ name: 'filter_params', value: '' });
                } else {
                    for (var i = 0, n = hash.length; i < n; i += 1) {
                        data.serialized.push({ name: 'filter_params[]', value: filter_params[i] });
                    }
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
    */

    initSidebar: function() {
        var sidebar = this.sidebar;
        sidebar.find('li.selected').removeClass('selected');
        if (this.filter_params.state_id) {
            if ($.isArray(this.filter_params.state_id)) {
                $('#s-pending-orders').addClass('selected');
            } else {
                sidebar.find('li[data-state-id="'+this.filter_params.state_id+'"]').addClass('selected');
            }
        } else if (this.filter_params.contact_id) {
            sidebar.find('li[data-contact-id='+this.filter_params.contact_id+']').addClass('selected');
        } else {
            $('#s-all-orders').addClass('selected');
        }
        var view = this.options.view;
        sidebar.find('li.list a').each(function() {
            var item = $(this);
            var href = item.attr('href');
            var match = href.match(/view=((.*)&|(.*)\/|(.*))/);
            if (match) {
                item.attr('href', href.replace('view='+(match[2]||match[3]||match[4]), 'view='+view));
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
        sidebar.find('li.list a').unbind('click.order_list').
            bind('click.order_list',
                function() {
                    var hash = $(this).attr('href').replace(/(^[^#]*#\/*|\/$)/g, ''); /* fix syntax highlight*/
                    if (hash == $.orders.hash) {
                        var params = $.orders.hash.replace('orders/', '');
                        $.order_list.dispatch(params, true);
                }
            });
    },

    /*
    initSelecting: function() {
        var container = this.container;
        var select_all_input = this.select_all_input;

        select_all_input.click(function() {
            $(this).trigger('select', this.checked);
        });

        // when 'shift' held on prevent default browser selecting
        $(document).keydown(function(e) {
            if (e.keyCode == 16) {
                document.body.onselectstart = function() { return false; };
            }
        }).keyup(function(e) {
            if (e.keyCode == 16) {
                document.body.onselectstart = null;
            }
        });

        var onSelectItem = function() {
            if ($.order_list.filter_params && !$.order_list.filter_params.length) {
                var table = $('#order-list');
                if (select_all_input.attr('checked')) {
                    table.find('.s-with-selected').show();
                    return;
                } else {
                    var selected = table.find('.order.selected:first');
                    if (selected.length) {
                        table.find('.s-with-selected').show();
                        return;
                    }
                }
                table.find('.s-with-selected').hide();
                return;
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
        });
    },

*/

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
                            item = sidebar.find('li[data-'+name.replace('_counters', '')+'-id='+id+'] .count');
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

    updateListItem: function(data, id, select) {
        select = typeof select === 'undefined' ? true : select;
        var self = this;

        var tmpl_name = 'template-order-list-'+this.options.view;
        if (document.getElementById(tmpl_name)) {
            var container = this.container;
            var li = container.find('li.order[data-order-id='+id+']');
            if (li.length) {
                li.replaceWith(tmpl(tmpl_name, {
                    orders: [data],
                    check_all: self.options.view == 'table' ? self.select_all_input.attr('checked') : false
                }));
                if (select) {
                    container.find('li.selected').removeClass('selected');
                    container.find('li.order[data-order-id='+id+']').addClass('selected');
                }
            }
        }
    },

    hideListItem: function(id) {
        // deffered fiber
        (function(id) {
            setTimeout(function() {
                if ($.order_list && $.order_list.container && $.order_list.container.length) {
                    var li = $.order_list.container.find('li.order[data-order-id='+id+']');
                    li.slideUp(450);
                }
            }, 1000);
        })(id);
    },

    dispatch: function(params, hard) {
        if (typeof params === 'string') {
            params = $.shop.helper.parseParams(params);
        }
        var loaders = {
            list: function() {
                $.order_list.finit();
                $.orders.load('?module=orders&' + $.orders.buildProductsUrlComponent(params), function() {
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
            (this.options.view ? '&view='+this.options.view : '');
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
     *
     * @param {Boolean} destruct If true destructing object (delete)
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