/**
 * @used at wa-apps/shop/templates/actions/backend/BackendThemes.html
 * Controller for Themes List Page.
 **/
( function($) { "use strict";

    // PAGE

    var ShopThemesListPage = ( function($) {
        // PAGE

        function Page(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];

            // CONST

            // INIT
            that.init();
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

    window.initShopThemesListPage = function(options) {
        return new ShopThemesListPage(options);
    };

})(jQuery);
