var ShopSettingsCheckoutSidebar = ( function($) {

    ShopSettingsCheckoutSidebar = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];

        // VARS

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    ShopSettingsCheckoutSidebar.prototype.initClass = function() {
        var that = this;

        that.initExpandCollapse();
    };

    ShopSettingsCheckoutSidebar.prototype.initExpandCollapse = function() {
        var that = this;

        that.$wrapper.on('click', '.js-list-header', function(event) {
            var $target = $(event.target),
                cancel_class = "js-link",
                cancel_target = !!($target.hasClass(cancel_class) || $target.closest("." + cancel_class).length);

            if (!cancel_target) {
                var version = $(this).data('version'),
                    $arrow = $(this).find('.icon16'),
                    $list = that.$wrapper.find('.js-storefronts-list[data-version="'+ version +'"]');

                if ($list.is(':visible')) {
                    $list.hide();
                    $arrow.removeClass('darr').addClass('rarr');
                    $.storage.set('shop/checkout_sidebar_hidden_'+version, 1);
                } else {
                    $list.show();
                    $arrow.removeClass('rarr').addClass('darr');
                    $.storage.del('shop/checkout_sidebar_hidden_'+version);
                }
            }
        });

        that.$wrapper.find('.js-list-header').each(function () {
            var version = $(this).data('version'),
                is_hidden = $.storage.get('shop/checkout_sidebar_hidden_'+version);

            if (is_hidden) {
                $(this).click();
            }
        });
    };

    return ShopSettingsCheckoutSidebar;

})(jQuery);