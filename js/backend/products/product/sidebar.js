( function($) {

    var Sidebar = ( function($) {

        Sidebar = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];

            // CONST
            that.app_url = $.wa_shop_products.app_url;

            // DYNAMIC VARS
            that.$active_menu_item = that.$wrapper.find("li.selected:first");

            // INIT
            that.init();
        };

        Sidebar.prototype.init = function() {
            var that = this;

            var $document = $(document);

            that.setActive();

            $document.on("wa_loaded", loadWatcher);
            function loadWatcher(event) {
                var is_exist = $.contains(document, that.$wrapper[0]);
                if (is_exist) {
                    that.setActive(location.pathname);
                } else {
                    $document.off("wa_loaded", loadWatcher);
                }
            }
        };

        /**
         * @param {Object} $item
         * */
        Sidebar.prototype.setItem = function($item) {
            var that = this,
                active_menu_class = "selected";

            if (that.$active_menu_item.length) {
                if (that.$active_menu_item[0] === $item[0]) {
                    return false;
                }
                that.$active_menu_item.removeClass(active_menu_class);
            }

            that.$active_menu_item = $item.addClass(active_menu_class);
        };

        /**
         * @param {String?} uri
         * */
        Sidebar.prototype.setActive = function(uri) {
            var that = this,
                $link;

            if (uri) {
                $link = that.$wrapper.find('a[href="' + uri + '"]:first');
                if ($link.length) {
                    that.setItem($link.closest("li"));
                }

            } else {
                var $links = that.$wrapper.find("a[href^='" + that.app_url + "']"),
                    relative_path = location.pathname + location.search,
                    location_string = location.pathname,
                    max_length = 0,
                    link_index = 0;

                $links.each(function (index) {
                    var $link = $(this),
                        href = $link.attr("href"),
                        href_length = href.length;

                    var is_absolute_coincidence = (href === relative_path);
                    if (is_absolute_coincidence) {
                        link_index = index;
                        return false;

                    } else if (location_string.indexOf(href) >= 0) {
                        if (href_length > max_length) {
                            max_length = href_length;
                            link_index = index;
                        }
                    }
                });

                if (link_index || link_index === 0) {
                    $link = $links.eq(link_index);
                    that.setItem($link.closest("li"));
                }
            }
        };

        return Sidebar;

    })($);

    $.wa_shop_products.init.initProductSidebar = function(options) {
        return new Sidebar(options);
    };

})(jQuery);