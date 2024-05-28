var Kanban = (($) => {

    let filterParams = null;
    let filterParamsStr = null;

    class Column {
        constructor(el) {
            this.$list = $(el);
            this.$listFooter = this.$list.find(".s-kanban__list__body__footer");
            this.$loader = this.$listFooter.find(".js-kanban-spinner");
            this.statusId = this.$list.data("kanban-list-status-id");
            this.lastOrderId = this.$list.find("[data-order-id]:last").data('order-id') || 0;
            this.listLength = this.$list.find("[data-order-id]").length;

            // pagination
            this.total = this.$list.data("kanban-list-total");
        }

        observe () {
            this.intersectionObserver = new IntersectionObserver((entries) => {

                if (entries[0].intersectionRatio <= 0) return;
                if (this.listLength < this.total) {
                    this.fetch();
                }
            });
            this.intersectionObserver.observe(this.$listFooter.get(0));
        }

        fetch () {

            this.$loader.show();

            $.getJSON(this.buildLoadListUrl(this.lastOrderId))
                .done(response => {
                    if (response.data.count) {
                        response.data.orders.forEach(order => this.$listFooter.before(Column.getOrderHTML(order)));
                        $("#s-orders [data-wa-tooltip-content]").waTooltip();
                    }

                    this.listLength = this.$list.find("[data-order-id]").length;
                    this.lastOrderId = this.$list.find("[data-order-id]:last").data('order-id') || 0;
                })
                .always(() => {
                    this.$loader.hide();
                });
        }

        static getOrderHTML(order) {
            const state = order.state_id;
            const { name, options } = getStateById(state);

            return tmpl('template-order-list-kanban-card', {
                    order,
                    state,
                    state_name: name || '',
                    state_color: (options ? options.style.color : '') || '',
                }
            );
        }

        buildLoadListUrl(id, lt, counters) {
            if (filterParamsStr) {
                $.order_list.filter_params_str = filterParamsStr;
            }
            return $.order_list.buildLoadListUrl(id, lt, counters, null, null, this.statusId);
        }
    }

    const addLazyLoad = (cols) => {
        cols.each((i, list) => new Column(list).observe());
    };

    const changeStateName = ($order, new_state_id) => {
        // Need a more reliable way to get a status map
        const state = getStateById(new_state_id);
        if (!state) return;

        const { name, options } = state;
        const $order_state = $order.find('.js-state')
            .text(name)
            .css('background', options.style.color);

        if ($order_state.data('tooltip')) {
            $order_state.data('tooltip').$wrapper._tippy.setContent(name);
        }
    }

    const addSortable = (cols, sttr, locale) => {

        /**
         * Dragging start
         */

        // order_can_be_moved[order_id][status_id] = bool
        const order_can_be_moved = {};

        var xhr = null;

        let item_pos = -1;

        cols.each((i, list) => {
            $(list).sortable({
                group: {
                    name: "statuses",
                    put: true
                },
                animation: 150,
                sort: false,
                delay: 1500,
                delayOnTouchOnly: true,

                // Called on start of dragging. Asks server which columns given order_id can be moved into.
                onStart: (evt) => {
                    item_pos = $(evt.item).index();
                    const order_id = $(evt.item).data('order-id');
                    xhr = $.post('?module=orders&action=availableActions', { id: order_id }, 'json');
                },

                // Called when user drops order into another column.
                // Sortable is set up to put a clone of original order element in both columns at this point.
                // onAdd() waits until
                onAdd: (evt) => {

                    $.when().then(() => {
                        // Can any order theoretically be moved between given columns?
                        const $order = $(evt.item);
                        const order_id = $order.data('order-id');
                        const from_state_id = $(evt.from).data('kanban-list-status-id');
                        const to_state_id = $(evt.to).data('kanban-list-status-id');
                        if (!sttr[from_state_id] || !sttr[from_state_id][to_state_id]) {
                            $.shop.notification({
                                class: 'danger',
                                timeout: 3000,
                                content: locale.no_action_for_states
                            });
                            return false;
                        }

                        // Can this particular order be moved between given columns?
                        return xhr.then((r) => {
                            const state_data = r.data[to_state_id];
                            if (state_data && !state_data.use_form) {
                                // All is fine, perform given action.
                                return $.post('?module=workflow&action=perform', {
                                    id: order_id,
                                    action_id: state_data.action_id
                                }, 'json').then((r) => {
                                    if (typeof r === "object" && r.status === 'ok') {
                                        changeStateName($order, r.data.after_state_id);

                                        return true;
                                    }

                                    return false;

                                }).fail(() => {
                                    return false;
                                });
                            } else {
                                $.shop.notification({
                                    class: 'danger',
                                    timeout: 3000,
                                    content: locale.action_requires_user_input
                                });
                                return false;
                            }
                        });
                    }).then((all_fine) => {
                        // Sortable is set up to put a copy of original order element in both columns.
                        // We have to delete one or the other depending on whether operation is successfull.
                        if (!all_fine) {
                            const $item = $(evt.item).detach();
                            const sibling = $(evt.from).children().get(item_pos);

                            $item.insertBefore(sibling);
                        }
                    });
                },
            });
        });
    };

    const defineSortable = () => {
        const dfd = $.Deferred();

        if (typeof Sortable === "undefined") {
            const $script = $("#wa-header-js"),
                path = $script.attr('src').replace(/wa-content\/js\/jquery-wa\/wa.header.js.*$/, '');

            const urls = [
                "wa-content/js/sortable/sortable.min.js",
                "wa-content/js/sortable/jquery-sortable.min.js",
            ];

            const sortableDeferred = urls.reduce((dfd, url) => {
                return dfd.then(() => {
                    return $.ajax({
                        cache: true,
                        dataType: "script",
                        url: path + url
                    });
                });
            }, $.Deferred().resolve());

            sortableDeferred.done(() => {
                dfd.resolve();
            });

        } else {
            dfd.resolve();
        }

        return dfd.promise();
    };

    const setFilterParams = () => {
        filterParams = this.options.filter_params
        filterParamsStr = this.options.filter_params_str
    };

    const setWidth = () => {
        const $fix_max_width = $('.js-fix-max-width');
        const $app_sidebar= $('#js-app-sidebar');

        maxWidth();

        $(document).on('wa_toggle_products_page_sidebar', (e, is_pinned) => maxWidth(is_pinned));

        function maxWidth(is_pinned) {
            if (!is_pinned) {
                is_pinned = $app_sidebar.hasClass('is-pinned')
            }

            if (is_pinned) {
                $fix_max_width.css('max-width', 'calc(100vw - 18rem)');
            }else{
                $fix_max_width.css('max-width', 'calc(100vw - 7rem)');
            }
        }
    }

    const init = options => {

        this.options = options;

        setFilterParams();
        setWidth();

        $.when(defineSortable()).then(() => {
            const $kanbanCols = $("[data-kanban-list-status-id]");
            addSortable($kanbanCols, options.state_transitions, options.locale);
            addLazyLoad($kanbanCols);
        });
    };

    function getStateById(id) {
        return $.order_list.options.state_names[id] || {};
    }

    return { init };
})(jQuery);

window.Kanban = Kanban;
