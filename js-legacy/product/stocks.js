( function($) {
    $.product_stocks = {
        /**
         * {Object}
         */
        options: {},

        container: null,

        products_ids: [],

        init: function(options) {
            this.options = options;
            this.stocks = options.stocks;
            this.container = $('#s-product-stocks');
            this.products_ids = [];

            this.initView();
            if (options.stocks && options.stocks.length > 1) {
                this.initDragndrop();
            }
            if (this.options.lazy_loading && this.options.product_stocks.length > 0) {
                this.initLazyLoad(this.options.lazy_loading);
            }
        },

        initLazyLoad: function(options) {
            var count = options.count,
                offset = count,
                total_count = options.total_count,
                sort = options.sort;

            $(window).lazyLoad('stop');  // stop previous lazy-load implementation
            if (offset < total_count) {
                var self = this;
                $(window).lazyLoad({
                    container: self.container,
                    state: (typeof options.auto === 'undefined' ? true: options.auto) ? 'wake' : 'stop',
                    hash: 'stocks',
                    load: function() {
                        $(window).lazyLoad('sleep');
                        $('.lazyloading-link').hide();
                        $('.lazyloading-progress').show();

                        var onError = function(r) {
                            if (console) {
                                if (r && r.errors) {
                                    console.error('Error when loading products: ' + r.errors);
                                } else if (r && r.responseText) {
                                    console.error('Error when loading products: ' + r.responseText);
                                } else {
                                    console.error(r);
                                }
                            }
                            $(window).lazyLoad('stop');
                        };

                        var params = [
                            "module=stocksBalance",
                            "offset=" + offset,
                            "total_count=" + total_count
                        ]

                        if (self.options.order) {
                            params.push('order=' + self.options.order);
                        }

                        if (sort) {
                            params.push("sort=" + sort);
                        }

                        $.get("?" + params.join("&"), function(r) {
                            if (r && r.status === 'ok') {
                                offset += r.data.count;

                                if (!self.append({ product_stocks: r.data.product_stocks, stocks: self.stocks })) {
                                    $(window).lazyLoad('stop');
                                    return;
                                }

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
                            } else {
                                onError(r);
                            }
                            },
                            'json'
                        ).error(onError);
                    }
                });
                $('.lazyloading-link').die('click').live('click',function(){
                    $(window).lazyLoad('force');
                    return false;
                });
            }
        },

        initView: function() {
            if (!this.append({ product_stocks: this.options.product_stocks, stocks: this.stocks })) {
                return this;
            }
        },

        initDragndrop: function() {
            this.container.find('td.s-stock-cell li.item').liveDraggable({
                containment: this.container,
                distance: 5,
                cursor: 'move',
                helper: function() {
                    return $(this).clone().append('<i class="icon10 no-bw" style="margin-left: 0; margin-right: 0; display: none;"></i>');
                },
                refreshPositions: true,
                start: function() {
                    $(this).parents('tr:first').find('td.s-stock-cell').addClass('drag-active').filter(':first').addClass('first');
                },
                stop: function() {
                    $(this).parents('tr:first').find('td.s-stock-cell').removeClass('drag-active').filter(':first').removeClass('first');
                }
            });
            this.container.find('td').liveDroppable({
                disabled: false,
                greedy: true,
                tolerance: 'pointer',
                over: function(event, ui) {
                    var self = $(this);
                    if (self.hasClass('drag-active')) {
                        ui.helper.find('.no-bw').hide();
                    } else {
                        ui.helper.find('.no-bw').show();
                    }
                },
                drop: function(event, ui) {
                    var self = $(this);
                    if (!self.hasClass('drag-active')) {
                        return false;
                    }
                    var dr = ui.draggable;
                    var td = dr.parents('td:first');
                    if (self.get(0) == td.get(0)) {
                        return false;
                    }
                    var src_item_id = dr.attr('id').replace('s-item-', '').split('-');
                    var src_stock_id = src_item_id[1];
                    var dst_stock_id = self.attr('data-stock-id');
                    var dst_item_id = [src_item_id[0], dst_stock_id];
                    var dst_item = $('#s-item-' + dst_item_id.join('-'));

                    // filter out item that marked as infinity
                    if (dst_item.length && dst_item.hasClass('infinity')) {
                        return false;
                    }

                    var d = $('#s-transfer-product-dialog');
                    if (d.length) {
                        d.parent().remove();
                    }
                    var p = $('<div></div>').appendTo('body');
                    p.load(
                        '?module=transfer',
                        {
                            sku_id: src_item_id[0],
                            from: src_stock_id,
                            to: dst_stock_id
                        }
                    );
                }
            });
        },

        append: function(data) {
            var that = this;

            data = formatData(data);

            try {
                this.container.append(tmpl('template-product-stocks', data));

            } catch (e) {
                console.error('Error: ' + e.message);
                return false;
            }

            return true;

            function formatData(result) {
                var products = [];

                $.each(result.product_stocks, function(i, product) {
                    if (that.products_ids.indexOf(product.id) < 0) {
                        products.push(product);
                        that.products_ids.push(product.id);
                    }
                });

                result.product_stocks = products;

                return result;
            }
        }
    };
})(jQuery);