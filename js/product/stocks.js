(function($) {
    $.product_stocks = {
        /**
         * {Object}
         */
        options: {},

        container: null,

        init: function(options) {
            this.options = options;
            this.stocks = options.stocks;
            this.container = $('#s-product-stocks');

            this.initView();
            if (options.stocks && options.stocks.length > 1) {
                this.initDragndrop();
            }
            if (this.options.lazy_loading && this.options.product_stocks.length > 0) {
                this.initLazyLoad(this.options.lazy_loading);
            }
        },

        initLazyLoad: function(options) {
            var count = options.count;
            var offset = count;
            var total_count = options.total_count;
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
                        $.get(
                            '?module=stocksBalance&offset=' + offset +
                                '&total_count=' + total_count + 
                                (self.options.order ? '&order=' + self.options.order : ''),
                            function(r) {
                                if (r && r.status == 'ok') {
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
            var sidebar = $('#s-sidebar');
            sidebar.find('li.selected').removeClass('selected');
            sidebar.find('#s-product-stocks-info').addClass('selected');
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
            try {
                this.container.append(tmpl('template-product-stocks', data));
            } catch (e) {
                console.error('Error: ' + e.message);
                return false;
            }
            return true;
        }
    };
})(jQuery);