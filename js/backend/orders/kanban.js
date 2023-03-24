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
                        response.data.orders.forEach(order => this.$listFooter.before(this.orderTmpl(order)));
                    }

                    this.listLength = this.$list.find("[data-order-id]").length;
                    this.lastOrderId = this.$list.find("[data-order-id]:last").data('order-id') || 0;
                })
                .always(() => {
                    this.$loader.hide();
                });
        }

        orderTmpl(order) {
            return `<div data-order-id="${order.id}" class="order_ s-kanban__list__body__item custom-p-8 custom-mb-8 blank">
                    <div class="flexbox">
                        <div class="s-kanban-item-title custom-mr-8">
                            <span class="userpic userpic48 ${(order.state_id == 'new') ? 'highlighted' : ''} bold" title="">
                                <img src="${order.contact.photo_50x50}" alt="">
                            </span>
                        </div>
                        <div class="flexbox vertical s-kanban-item-body wide">
                            <p ${(order.style) ? 'style="' + order.style + '"' : ''} class="flexbox full-width custom-m-0">
                                <a ${(order.style) ? 'style="' + order.style + '"' : ''} class="custom-mr-8" href="?action=orders#/order/${order.id}/">${order.id_str}</a>
                                <span>${order.total_str}</span>
                            </p>
                            <p class="custom-m-0">${order.contact.name}</p>
                            <p class="hint custom-m-0">${order.create_datetime_str}</p>
                        </div>
                    </div>


                    <div class="flexbox vertical s-kanban-item-body wide">
                        <a href="?action=orders#/order/${order.id}/"><i class="fas fa-truck"></i> ${order.shipping_name}</a>
                        ${(order.courier_name) ? '<a href="?action=orders#/order/' + order.id + '/" class="custom-mb-8"><span class="gray">' + order.courier_name + order.shipping_interval + '</span></a>' : ''}
                        <a href="?action=orders#/order/${order.id}/"><i class="fas fa-money-check-alt"></i> ${order.payment_name}</a>
                    </div>
                </div>`;
        }

        buildLoadListUrl(id, lt, counters) {

            const url = `?module=orders
                    &action=loadList
                    &state_id=${this.statusId}
                    &id=${id}${(lt ? '&lt=1' : '')}${(counters ? '&counters=1' : '')}
                    &view=kanban
                    &sort[0]=0
                    &sort[1]=desc`;

            return url.replace(/\s+/g, '');
        }
    }

    const updateOrders = () => {

        function buildUpdateListUrl(id, lt, counters) {
            // Получить текущую дату и время
            const currentDate = new Date();

            // Вычесть интервал обновления
            currentDate.setTime(currentDate.getTime() - timeout);

            // Год, месяц и день в нужном формате
            const year = currentDate.getFullYear();
            const month = ('0' + (currentDate.getMonth() + 1)).slice(-2);
            const day = ('0' + currentDate.getDate()).slice(-2);

            // Часы, минуты и секунды в нужном формате
            const hours = ('0' + currentDate.getHours()).slice(-2);
            const minutes = ('0' + currentDate.getMinutes()).slice(-2);
            const seconds = ('0' + currentDate.getSeconds()).slice(-2);

            // Строка в нужном формате
            const formattedDate = `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;

            const url = `?module=orders
                    &action=loadList
                    &state_id=${this.statusId}
                    &id=${id}${(lt ? '&lt=1' : '')}${(counters ? '&counters=1' : '')}
                    &search=update_datetime>=${formattedDate}
                    &view=kanban
                    &sort[0]=0
                    &sort[1]=desc`;

            return url.replace(/\s+/g, '');
        }
    };

    const addLazyLoad = (cols) => {
        cols.each((i, list) => new Column(list).observe());
    };

    const addSortable = (cols) => {
        cols.each((i, list) => {
            new Sortable(list, {
                group:{ name: "statuses", put: false },
                animation: 150,
                sort: false,
                delay: 1500,
                delayOnTouchOnly: true,
                onEnd: () => {
                    $.shop.notification({
                        class: 'danger',
                        timeout: 3000,
                        content: 'Temporarily blocked. The ability to drag orders under the terms of basic workflow limitations will be available by the time of Shop-Script X release.'
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
            addSortable($kanbanCols);
            addLazyLoad($kanbanCols);
        });
    };

    return { init };
})(jQuery);

window.Kanban = Kanban;
