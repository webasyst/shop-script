var ShopSettingsCheckoutSidebar = ( function($) {

    ShopSettingsCheckoutSidebar = function(options) {
        // DOM
        this.$wrapper = options["$wrapper"];

        this.initClass();
    };

    ShopSettingsCheckoutSidebar.prototype.initClass = function() {
        this.initExpandCollapse();
    };

    ShopSettingsCheckoutSidebar.prototype.initExpandCollapse = function() {
        const that = this;
        const hideSelectLinks = () => {
            that.$wrapper.find('.js-storefronts-list li').removeClass('selected');
        };

        that.$wrapper.on('click', '.js-list-header', function(e) {
            const $target = $(e.target);
            const cancel_class = 'js-link';
            const cancel_target = $target.hasClass(cancel_class) || $target.closest("." + cancel_class).length;

            if (cancel_target) {
                hideSelectLinks();
                return;
            }

            e.preventDefault();
            const version = $(this).data('version');
            const $list = that.$wrapper.find('.js-storefronts-list[data-version="'+ version +'"]');
            const $icon = $(this).find('.icon');

            if ($list.is(':visible')) {
                $icon.css({'transform':'rotate(-90deg)'});
                $list.hide();
                $.storage.set('shop/checkout_sidebar_hidden_'+version, 1);
            } else {
                $icon.css({'transform':'rotate(0deg)'});
                $list.show();
                $.storage.del('shop/checkout_sidebar_hidden_'+version);
            }
        });

        that.$wrapper.find('.js-list-header').each(function () {
            const version = $(this).data('version');
            const is_hidden = $.storage.get('shop/checkout_sidebar_hidden_'+version);

            if (is_hidden) {
                $(this).click();
            }
        });

        that.$wrapper.find('.js-storefronts-list').on('click', 'li.js-item', function () {
            const $el = $(this);

            hideSelectLinks();
            $el.addClass('selected');
        });
    };

    return ShopSettingsCheckoutSidebar;

})(jQuery);
