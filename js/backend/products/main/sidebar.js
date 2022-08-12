( function($) {

    var Sidebar = ( function($) {

        Sidebar = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];

            // CONST
            that.tooltips = options["tooltips"];
            that.locales = options["locales"];
            that.urls = options["urls"];
            that.app_url = $.wa_shop_products.app_url;

            // DYNAMIC VARS
            that.$active_menu_item = that.$wrapper.find("li.selected:first");
            that.states = {
                pinned: options["sidebar_menu_state"]
            };

            // INIT
            that.init();

            console.log( that );
        };

        Sidebar.prototype.init = function() {
            let that = this,
                $document = $(document),
                $catalog_toggle = that.$wrapper.find(".js-catalog-toggle");

            const active_class = "is-restricted";

            that.setActive();

            that.initPin();

            // Скрываем меню у остальных
            that.$wrapper.find(".js-group-toggle").each( function() {
                groupExpand($(this));
            });

            // Показываем меню у каталога
            groupExpand($catalog_toggle);

            // Открывашки
            that.$wrapper.on("click", ".js-group-toggle", function(event) {
                event.preventDefault();
                groupExpand($(this));
            });

            // Открывашки по наведению
            that.$wrapper.on("mouseenter", function(event) {
                if (!that.states.pinned) {
                    groupExpand($catalog_toggle, true);
                }
            });

            // Подсказки
            $.each(that.tooltips, function(i, tooltip) {
                $.wa.new.Tooltip(tooltip);
            });

            // Активный пункт при AJAX обновлнении контента
            $document.on("wa_loaded", loadWatcher);
            function loadWatcher(event) {
                var is_exist = $.contains(document, that.$wrapper[0]);
                if (is_exist) {
                    that.setActive(location.pathname);
                } else {
                    $document.off("wa_loaded", loadWatcher);
                }
            }

            // Показываем готовый DOM
            that.$wrapper.css("visibility", "");

            //

            function groupExpand($toggle, show) {
                let $group = $toggle.closest("li");

                show = (typeof show === "boolean" ? show : $group.hasClass(active_class));

                if (show) {
                    $group.removeClass(active_class);
                } else {
                    $group.addClass(active_class);
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

        Sidebar.prototype.initPin = function() {
            var that = this,
                $toggle = that.$wrapper.find(".js-toggle-products-page-sidebar"),
                $name = $toggle.find(".s-name");

            pin(that.states.pinned);

            $toggle.on("click", function(event) {
                event.preventDefault();
                pin(!that.states.pinned, true);
            });

            /**
             * @param {Boolean?} pin
             * @param {Boolean?} send_request
             * */
            function pin(pin, send_request) {
                var active_class = "is-pinned",
                    inactive_class = "is-unpinned",
                    disabled_class = "hover-is-disabled";

                pin = (typeof pin === "boolean" ? pin : !that.states.pinned);
                send_request = (typeof send_request === "boolean" ? send_request : false);

                that.$wrapper.addClass(disabled_class);

                if (pin) {
                    that.$wrapper.addClass(active_class);
                    that.$wrapper.removeClass(inactive_class);
                } else {
                    that.$wrapper.addClass(inactive_class);
                    that.$wrapper.removeClass(active_class);
                }

                setTimeout( function() {
                    that.$wrapper.removeClass(disabled_class);
                }, 100);

                that.states.pinned = pin;

                // update button text
                var text = (that.states.pinned ? that.locales["unpin_menu"] : that.locales["pin_menu"]);
                $name.text(text);

                if (send_request) { request(pin); }
            }

            function request(pin) {
                var deferred = $.Deferred();

                var data = { sidebar_menu_state: (pin ? "1" : "0") };

                $.post(that.urls["sidebar_menu_state"], data, "json")
                    .always( function() { deferred.resolve(); });

                return deferred.promise();
            }
        };

        return Sidebar;

    })($);

    $.wa_shop_products.init.initProductsSidebar = function(options) {
        return new Sidebar(options);
    };

})(jQuery);