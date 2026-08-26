( function($) {

    var Section = ( function($) {

        Section = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$tabs = options["$tabs"];
            that.$tab_container = options["$tab_container"];

            // CONST
            // that.tooltips = options["tooltips"];
            // that.locales = options["locales"];
            that.templates = options["templates"];
            that.urls = options["urls"];
            that.product = options["product"];
            that.product_id = options["product_id"];
            that.report_rights = options["report_rights"];
            that.primary_currency_html = options["primary_currency_html"];
            that.sales_data = options["sales_data"];

            // INIT
            that.init();
        };

        Section.prototype.init = function() {
            var that = this;

            that.report_rights && that.initSalesGraph();
            that.initTabs();

            var page_promise = that.$wrapper.closest(".s-product-page").data("ready");
            page_promise.done( function(product_page) {
                var $footer = that.$wrapper.find(".js-sticky-footer");
                product_page.initProductDelete($footer);
                product_page.initStickyFooter($footer);
                updateURL(that.product.id);

                function updateURL(product_id) {
                    if (product_id) {
                        var is_new = location.href.indexOf("/new/") >= 0;
                        if (is_new) {
                            var url = location.href.replace("/new/", "/"+product_id+"/");
                            history.replaceState(null, null, url);
                            that.$wrapper.trigger("product_created", [product_id]);
                        }
                    }
                }
            });

            $.each(that.tooltips, function(i, tooltip) {
                $.wa.new.Tooltip(tooltip);
            });
        };

        Section.prototype.initTabs = function() {
            const that = this;
            const $tabs = that.$tabs.find('[data-url]');

            $tabs.on('click', function() {
                const $tab = $(this);
                if ($tab.hasClass('selected')) return false;

                that.$tab_container.html(that.templates.loading_tab);

                $tab.siblings().removeClass('selected');
                $tab.addClass('selected');
                const url = $tab.data('url');
                that.$tab_container.load(url);
            });

            $tabs.filter('[data-default]').trigger('click');
        };

        Section.prototype.initSalesGraph = function() {
            const that = this;

            showSalesGraph(that.sales_data, that.primary_currency_html);
        };

        return Section;
    })($);

    $.wa_shop_products.init.initProductSummarySection = function(options) {
        return new Section(options);
    };

})(jQuery);
