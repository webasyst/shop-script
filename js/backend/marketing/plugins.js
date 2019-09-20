( function($) {

    var PluginsPage = ( function($) {

        PluginsPage = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$installer_wrapper = that.$wrapper.find('.js-installer-wrapper');

            // CONST
            that.installer_url = options["installer_url"];

            // VARS

            // DYNAMIC VARS

            // INIT
            that.init();
        };

        PluginsPage.prototype.init = function() {
            var that = this;

            that.initInstaller();
        };

        PluginsPage.prototype.initInstaller = function() {
            var that = this;


            that.$installer_wrapper.html('<i class="icon16 loading"></i>').show().load(that.installer_url);
        };

        return PluginsPage;

    })($);

    $.shop.marketing.init.pluginsPage = function(options) {
        return new PluginsPage(options);
    };

})(jQuery);


