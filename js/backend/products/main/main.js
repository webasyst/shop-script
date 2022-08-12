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

            console.log( that );
        }

        Main.prototype.init = function() {
            var that = this;

            var ready_promise = that.$wrapper.data("ready");
            ready_promise.resolve(that);
            that.$wrapper.trigger("ready", [that]);

            var interval = setInterval( function() {
                var $target = $("#wa-nav .desktop-only");
                if ($target.length) {
                    $target.parent().remove();
                    clearInterval(interval);
                }
            }, 500);
            console.log( "TODO: убрать это после этапа разработки" );
        };

        return Main;

    })($);

    $.wa_shop_products.init.initProductsMain = function(options) {
        return new Main(options);
    };

})(jQuery);