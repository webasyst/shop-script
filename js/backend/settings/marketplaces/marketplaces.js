/**
 * @used at wa-apps/shop/templates/actions/settings/SettingsMarketplaces.html
 * Controller for Marketplaces Settings Page.
 **/
( function($) { "use strict";

    // PAGE

    var ShopMarketplacesSettingsPage = ( function($) {
        // PAGE

        function Page(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];

            // CONST

            // INIT
            that.init();

            console.log( that );
        }

        Page.prototype.init = function() {
            var that = this;

            that.$wrapper
                .css("visibility", "")
                .data("controller", that);
        };

        //

        return Page;

    })($);

    window.initShopMarketplacesSettingsPage = function(options) {
        return new ShopMarketplacesSettingsPage(options);
    };

})(jQuery);