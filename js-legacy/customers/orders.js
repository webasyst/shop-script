var ShopCustomerOrders = ( function($) {

    ShopCustomerOrders = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options.$wrapper;

        // VARS
        that.contact = options.contact || { id: 0 };


        // INIT
        that.init();
    };

    ShopCustomerOrders.prototype.init = function() {
        var that = this;
        that.initPagination();
    };

    ShopCustomerOrders.prototype.initPagination = function () {
        var that = this,
            $wrapper = that.$wrapper,
            url = '?module=customers&action=orders';

        $wrapper.on('click', '.s-customer-orders-pagination-wrapper a', function (e) {
            e.preventDefault();
            var $link = $(this),
                href = $link.attr('href');

            var res = href.match(/page=(\d+)/),
                page = res ? res[1] : 1;

            if (page < 1) {
                page = 1;
            }

            $.get(url, { page: page, id: that.contact.id, include_js: 0 })
                .done(function (html) {
                    var blocks = ['.s-customer-orders', '.s-customer-orders-pagination-wrapper'],
                        $html = $('<div>').html(html);
                    $.each(blocks, function (i, block) {
                        var $old_block = $wrapper.find(block),
                            $new_block = $html.find(block);
                        $old_block.replaceWith($new_block);
                    })
                })
        });
    };

    return ShopCustomerOrders;

})($);
