var KanbanUsers = (($) => {

    let filterParams = null;
    let filterParamsStr = null;
    const roleChipRootSelector = '#s-order-nav';
    const roleChipSelector = '.js-chip-user';
    const roleChipEventSelector = `${roleChipRootSelector} ${roleChipSelector}`;
    const roleChipNamespace = '.kanbanUsersRoleChips';
    const defaultRoleChip = 'all';

    class Column {
        constructor(el) {
            this.$list = $(el);
            this.$listFooter = this.$list.find('.s-kanban__list__body__footer');
            this.$loader = this.$listFooter.find('.js-kanban-spinner');
            this.user_id = this.$list.data('kanban-list-user-id');
            this.lastOrderId = this.$list.find('[data-order-id]:last').data('order-id') || 0;
            this.listLength = this.$list.find('[data-order-id]').length;

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
            const { name, options } = getStateById(order.state_id);

            return tmpl('template-order-list-kanban-users-card', {
                    order,
                    state_name: name || '',
                    state_color: (options ? options.style.color : '') || '',
                }
            );
        }

        buildLoadListUrl(id, lt, counters) {
            if (filterParamsStr) {
                $.order_list.filter_params_str = filterParamsStr;
            }
            return $.order_list.buildLoadListUrl(id, lt, counters, null, null, this.user_id);
        }
    }

    const addLazyLoad = (cols) => {
        cols.each((i, list) => new Column(list).observe());
    };

    const updateColumnCount = ($list, delta) => {
        const user_id = $list.data('kanban-list-user-id');
        const $count = $list
            .closest('.s-kanban__list')
            .find(`.s-kanban__list__count[data-status-id="${user_id}"]`);

        if (!$count.length) {
            return;
        }

        const count = parseInt($count.text(), 10) || 0;
        $count.text(Math.max(0, count + delta));
    };

    const rollbackMovedItem = (evt, item_pos) => {
        const $item = $(evt.item).detach();
        const sibling = $(evt.from).children().get(item_pos);

        if (sibling) {
            $item.insertBefore(sibling);
        } else {
            $(evt.from).append($item);
        }
    };

    const addSortable = (cols, locale) => {
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

                onStart: (evt) => {
                    item_pos = $(evt.item).index();
                },

                onAdd: (evt) => {
                    const $order = $(evt.item);
                    const order_id = $order.data('order-id');
                    const from_user_id = $(evt.from).data('kanban-list-user-id');
                    const to_user_id = $(evt.to).data('kanban-list-user-id');

                    if (!order_id || !to_user_id || from_user_id === to_user_id) {
                        rollbackMovedItem(evt, item_pos);
                        return;
                    }

                    $.post('?module=order&action=assignContact', {
                        order_id,
                        assigned_contact_id: to_user_id
                    }, 'json').done((r) => {
                        if (typeof r === 'object' && r.status === 'ok') {
                            $order.attr('data-assigned-contact-id', to_user_id).data('assigned-contact-id', to_user_id);
                            updateColumnCount($(evt.from), -1);
                            updateColumnCount($(evt.to), 1);
                            return;
                        }

                        rollbackMovedItem(evt, item_pos);
                        $.shop.notification({
                            class: 'danger',
                            timeout: 3000,
                            content: locale.assign_failed
                        });
                    }).fail(() => {
                        rollbackMovedItem(evt, item_pos);
                        $.shop.notification({
                            class: 'danger',
                            timeout: 3000,
                            content: locale.assign_failed
                        });
                    });
                },
            });
        });
    };

    const handleOrderClick = ($cols) => {
        $cols.on('click', '[data-order-id]', function(e) {
            e.preventDefault();
            e.stopPropagation();
            location.href = $(this).find('a:first').attr('href');
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

    const setActiveRoleChip = (role) => {
        const $roleChips = $(roleChipRootSelector).find(roleChipSelector);
        if (!$roleChips.length) {
            return;
        }

        const $defaultChip = $roleChips.filter(`[data-chip="${defaultRoleChip}"]`);

        
        const $activeChip = role
            ? $roleChips.filter(`[data-chip="${role}"]`)
            : $defaultChip;

        $roleChips.removeClass('accented');
        ($activeChip.length ? $activeChip : $defaultChip).addClass('accented');
    };

    const getHashParamValue = (name) => {
        const hash = decodeURIComponent(window.location.hash || '');
        const regExp = new RegExp(`(?:^|[?&#/&])${name}=([^&/]+)`);
        const match = hash.match(regExp);

        return match ? match[1] : '';
    };

    const syncRoleChipByHash = () => {
        const view = getHashParamValue('view');
        const role = getHashParamValue('role');

        if (view !== 'kanban-users') {
            setActiveRoleChip(defaultRoleChip);
            return;
        }

        setActiveRoleChip(role);
    };

    const initRoleChips = () => {
        $(document)
            .off(`click${roleChipNamespace}`, roleChipEventSelector)
            .on(`click${roleChipNamespace}`, roleChipEventSelector, function() {
                setActiveRoleChip($(this).data('chip'));
            });

        $(window)
            .off(`hashchange${roleChipNamespace}`)
            .on(`hashchange${roleChipNamespace}`, syncRoleChipByHash);

        if ($.orders && $.orders.$wrapper) {
            $.orders.$wrapper
                .off(`wa_init_orders_nav_after${roleChipNamespace}`)
                .on(`wa_init_orders_nav_after${roleChipNamespace}`, syncRoleChipByHash);
        }

        syncRoleChipByHash();
    };

    const init = options => {

        this.options = options;

        setFilterParams();
        setWidth();
        initRoleChips();

        $.when(defineSortable()).then(() => {
            const $kanbanCols = $('[data-kanban-list-user-id]');
            addSortable($kanbanCols, options.locale);
            addLazyLoad($kanbanCols);
            handleOrderClick($kanbanCols);
        });
    };

    function getStateById(id) {
        return $.order_list.options.state_names[id] || {};
    }

    return { init };
})(jQuery);

window.KanbanUsers = KanbanUsers;
