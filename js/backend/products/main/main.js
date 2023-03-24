( function($) {

    var Main = ( function($) {

        function Main(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];

            // CONST
            that.templates = options["templates"];
            that.locales = options["locales"];
            that.urls = options["urls"];

            // VARS
            that.sidebar = null;

            // DYNAMIC VARS

            // INIT
            that.init();
        }

        Main.prototype.init = function() {
            var that = this;

            var ready_promise = that.$wrapper.data("ready");
            ready_promise.resolve(that);
            that.$wrapper.trigger("ready", [that]);
        };

        return Main;

    })($);

    $.wa_shop_products.init.initProductsMain = function(options) {
        return new Main(options);
    };

})(jQuery);
