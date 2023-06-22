( function($) {

    var Sidebar = ( function($) {

        Sidebar = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$nearest_products_wrapper = options["$nearest_products_wrapper"];

            // CONST
            that.app_url = $.wa_shop_products.app_url;
            that.context = (typeof options['context'] !== "undefined" ? options['context'] : {});

            // DYNAMIC VARS
            that.$active_menu_item = that.$wrapper.find("li.selected:first"),
            that.updated_links = false;

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

            $document.on("product_created", createWatcher);
            function createWatcher(event, product_id) {
                var is_exist = $.contains(document, that.$wrapper[0]);
                if (is_exist) {
                    var $links = that.$wrapper.find("a[href*=\"/new/\"]");
                    $links.each( function(i , link) {
                        link.href = link.href.replace("/new/", "/"+product_id+"/");
                    });
                } else {
                    $document.off("product_created", createWatcher);
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

            if (that.$nearest_products_wrapper.length && !$.isEmptyObject(that.context)) {
                var $prev = that.$nearest_products_wrapper.find('a:first-of-type'),
                    $next = that.$nearest_products_wrapper.find('a:last-of-type'),
                    tab_id = $item.find('a').data('tab-id'),
                    params = '';

                if (!tab_id) {
                    tab_id = $item.find('a').attr('href').match(/\d+\/([^\/]+)\/?$/);
                    if (tab_id) {
                        tab_id = tab_id[1];
                    } else {
                        tab_id = 'general';
                    }
                }

                if (that.context.presentation > 0) {
                    var context = {
                        presentation: that.context.presentation,
                    };
                    if (that.context.another_section_url.length) {
                        context.another_section_url = that.context.another_section_url;
                    }
                    params ='?' + $.param(context);
                }

                $prev.attr('href', $prev.data('nearest-product-id') + tab_id + '/' + params);
                $next.attr('href', $next.data('nearest-product-id') + tab_id + '/' + params);

                if (!that.updated_links) {
                    that.$wrapper.find("a[href^='" + that.app_url + "']").each(function () {
                        var $link = $(this);

                        $link.attr('href', $link.attr('href') + params);
                    });
                    that.updated_links = true;
                }
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
                $link = that.$wrapper.find('a[href^="' + uri + '"]:first');
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