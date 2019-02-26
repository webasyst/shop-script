( function($) { "use strict";

    var ShopOrderPage = ( function($) {

        ShopOrderPage = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$wa_order_cart = that.$wrapper.find("#js-order-cart");

            // VARS

            // DYNAMIC VARS

            // INIT
            that.initClass();
        };

        ShopOrderPage.prototype.initClass = function() {
            var that = this;

            that.$wrapper.on("click", ".js-clear-cart", function() {
                var wa_order_cart = that.$wa_order_cart.data("controller");
                if (wa_order_cart) {
                    wa_order_cart.clear({
                        confirm: true
                    }).then( function() {
                        location.reload();
                    });
                } else {
                    alert("Error");
                }
            });
        };

        return ShopOrderPage;

    })(jQuery);

    window.ShopOrderPage = ShopOrderPage;

})(jQuery);