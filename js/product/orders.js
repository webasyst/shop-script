(function($) {
    $.product_orders = {

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
            if (options.container) {
                this.container = $(options.container);
            } else {
                this.container = $('body');
            }

            if (this.options.lazy_loading) {
                this.initLazyLoad(this.options.lazy_loading);
            }
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
