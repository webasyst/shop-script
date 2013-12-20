(function($) {
    $.product_stocks_log = {
        
        /**
         * Number
         */
        product_id: null,
        
        /**
         * Jquery object
         */
        container: null,
        
        /**
         * Object
         */
        options: {},
        
        init: function(options) {
            this.options = options;
            this.container = $('#s-product-edit-forms .s-product-form.stockslog');
            if (this.options.total_count) {
                $('#s-product-edit-menu .stocks-log .hint').text(this.options.total_count);
            }
            
            $('#s-product-edit-menu .stocks-log').addClass('selected');
            
            if (this.options.lazy_loading) {
                this.initLazyLoad(this.options.lazy_loading);
            }
            
            var that = this;
            $.product.editTabStockslogAction = function(path) {
                if (!that.product_id) {
                    that.product_id = path.id;
                    return;
                }
                var url = '?module=product&action=stocksLog&id=' + path.id;
                if (path.tail) {
                    url += '&param[]=' + path.tail.split('/').join('&param[]=');
                }
                
                var r = Math.random();
                $.product.ajax.random = r;
                $.get(url, path.params || {}, function(html) {
                    // too late: user clicked something else.
                    if ($.product.ajax.random != r) {
                        return;
                    }
                    that.container.empty().append(html);
                });
                
            };
            
            $.product.editTabStockslogBlur = function() {
                if (that.options.lazy_loading) {
                    $(window).lazyLoad('stop');
                }
            };
            
        },
        
        initLazyLoad: function(options) {
            var count = options.count;
            var offset = count;
            var total_count = this.options.total_count;
            var url = options.url;

            $(window).lazyLoad('stop'); // stop previous lazy-load implementation

            if (offset < total_count) {
                var self = this;
                $(window).lazyLoad({
                    container: self.container,
                    state: (typeof options.auto === 'undefined' ? true : options.auto) ? 'wake' : 'stop',
                    load: function() {
                        $(window).lazyLoad('sleep');
                        $('.lazyloading-link').hide();
                        $('.lazyloading-progress').show();
                        $.get(url + '&lazy=1&offset=' + offset + '&total_count=' + total_count, function(data) {

                            var html = $('<div></div>').html(data);
                            var list = html.find('table tr');

                            if (list.length) {
                                offset += list.length;
                                $('table', self.container).append(list);
                                if (offset >= total_count) {
                                    $(window).lazyLoad('stop');
                                    $('.lazyloading-progress').hide();
                                } else {
                                    $(window).lazyLoad('wake');
                                    $('.lazyloading-link').show();
                                }
                            } else {
                                $(window).lazyLoad('stop');
                                $('.lazyloading-progress').hide();
                            }
                            
                            $('.lazyloading-progress-string', self.container).
                                    replaceWith(
                                        $('.lazyloading-progress-string', html)
                                    );
                            $('.lazyloading-chunk', self.container).
                                    replaceWith(
                                        $('.lazyloading-chunk', html)
                                    );
                                        
                            html.remove();

                        });
                    }
                });
                $('.lazyloading-link').die('click').live('click', function() {
                    $(window).lazyLoad('force');
                    return false;
                });
            }
        }
    };
})(jQuery);
