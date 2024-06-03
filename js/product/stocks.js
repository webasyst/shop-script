( function($) {
    $.product_stocks = {
        /**
         * {Object}
         */
        options: {},

        container: null,

        products_ids: [],

        stock_id: null,

        waLoading: $.waLoading(),

        init: function(options) {
            this.options = options;
            this.stocks = options.stocks;
            this.stock_id = options.stock_id;
            this.container = $('#s-product-stocks');
            this.products_ids = [];

            this.initView();

            if (this.options.lazy_loading && this.options.product_stocks.length > 0) {
                this.initLazyLoad(this.options.lazy_loading);
            }
        },

        initLazyLoad: function(options) {
            let count = options.count,
                offset = count,
                total_count = options.total_count,
                sort = options.sort;

            $(window).lazyLoad('stop');  // stop previous lazy-load implementation
            if (offset < total_count) {
                const self = this;
                $(window).lazyLoad({
                    container: self.container.find('tbody'),
                    state: (typeof options.auto === 'undefined' ? true: options.auto) ? 'wake' : 'stop',
                    hash: 'stocks',
                    load: function() {
                        $(window).lazyLoad('sleep');
                        $('.lazyloading-link').hide();
                        $('.lazyloading-progress').show();

                        const onError = function(r) {
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

                        const params = [
                            "module=stocksBalance",
                            "offset=" + offset,
                            "total_count=" + total_count
                        ];
                        if (self.stock_id) {
                            params.push("stock_id=" + self.stock_id);
                        }
                        if (self.options.order) {
                            params.push('order=' + self.options.order);
                        }
                        if (sort) {
                            params.push("sort=" + sort);
                        }

                        $.get("?" + params.join("&"), function(r) {
                            if (r && r.status === 'ok') {
                                offset += r.data.count;

                                if (!self.append({
                                    product_stocks: r.data.product_stocks,
                                    stocks: self.stocks,
                                    is_single_stock: !!self.stock_id
                                })) {
                                    $(window).lazyLoad('stop');
                                    return;
                                }

                                $('.lazyloading-progress-string').text(r.data.progress.loaded + ' ' + r.data.progress.of);
                                $('.lazyloading-progress').hide();
                                $('.lazyloading-chunk').text(r.data.progress.chunk);

                                if (offset >= total_count || r.data.count < 1) {
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
                $('.lazyloading-link').off('click').on('click',function(){
                    $(window).lazyLoad('force');
                    return false;
                });
            }
        },

        initView: function() {
            if (!this.append({ product_stocks: this.options.product_stocks, stocks: this.stocks, is_single_stock: !!this.stock_id })) {
                return this;
            }
        },

        append: function(data) {
            const that = this;

            data = formatData(data);

            try {
                this.container.find('tbody').append(tmpl('template-product-stocks', data));
            } catch (e) {
                console.error('Error: ' + e.message);
                return false;
            }

            return true;

            function formatData(result) {
                const products = [];

                $.each(result.product_stocks, function(i, product) {
                    if (that.products_ids.indexOf(product.id) < 0) {
                        products.push(product);
                        that.products_ids.push(product.id);
                    }
                });

                result.product_stocks = products;

                return result;
            }
        },

        showLoading: function () {
            this.waLoading.show();
            this.waLoading.animate(10000, 95, false);
        },
        doneLoading: function () {
            this.waLoading.done();
        }
    };
})(jQuery);
