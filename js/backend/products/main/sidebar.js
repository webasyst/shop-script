let initMainWaSidebar;
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
            that.app_url = options["app_url"] || $.wa_shop_products.app_url;

            // DYNAMIC VARS
            that.$active_menu_item = that.$wrapper.find("li.selected:first");
            that.states = {
                pinned: options["sidebar_menu_state"]
            };

            // INIT
            that.init();
        };

        Sidebar.prototype.init = function() {
            let that = this,
                $document = $(document);

            const active_class = "is-restricted";
            const selected_class = "selected";

            that.setActive();

            that.initPin();

            const $menu_toggle = that.$active_menu_item.parent('ul').parent('li').find('.js-group-toggle');

            // Скрываем меню у остальных
            that.$wrapper.find(".js-group-toggle").each( function() {
                groupExpand($(this));
            });

            // Показываем меню у каталога
            if ($menu_toggle.length) {
                groupExpand($menu_toggle);
            }

            // Открывашки
            that.$wrapper.on("click", ".js-group-toggle", function(event) {
                event.preventDefault();
                groupExpand($(this));
                // переход в первый пункт меню
                //$(this).parent('li').find('a:first').get(0).click();
            });

            // Открывашки по наведению
            if ($menu_toggle.length) {
                that.$wrapper.on("mouseenter", function (event) {
                    if (!that.states.pinned) {
                        groupExpand($menu_toggle, true);
                    }
                });
            }

            // Подсказки
            $.each(that.tooltips, function(i, tooltip) {
                $.wa.new.Tooltip(tooltip);
            });

            // Активный пункт при AJAX обновлнении контента
            $document.on("wa_loaded", loadWatcher);
            function loadWatcher(event) {
                var is_exist = $.contains(document, that.$wrapper[0]);
                if (is_exist) {
                    that.setActive(location.pathname + location.search + location.hash);
                } else {
                    $document.off("wa_loaded", loadWatcher);
                }
            }

            // V2 temporary solution for now
            $(() => {
                const $submenu = $menu_toggle.siblings();
                $submenu.on('click touchstart', 'li', function() {
                    const $li = $(this);

                    $('li', $submenu).removeClass('selected');
                    setTimeout(() => {
                        $li.addClass('selected');
                    }, 200);
                });
            });

            // Показываем готовый DOM
            that.$wrapper.css("visibility", "");

            //

            function groupExpand($toggle, show) {
                let $group = $toggle.closest("li");

                show = (typeof show === "boolean" ? show : $group.hasClass(active_class));

                if (show) {
                    $group.removeClass(active_class);
                    $group.addClass(selected_class);
                } else {
                    $group.addClass(active_class);
                    $group.removeClass(selected_class);
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

        Sidebar.prototype.specifyRuleCheck = function(href, relative_path) {
            let result = href === relative_path;
            const { hash, pathname, search } = location;

            if (!hash) {
                return result;
            }

            const hashTest = (pattern = '#') => new RegExp(pattern).test(hash);

            switch (href) {
                case pathname + '?action=products#/services/':
                    result = search === '?action=products' && hashTest('#\\/services\\/');
                    break;
                case pathname + '?action=reports':
                    result = search === '?action=reports' && hashTest('#sales');
                    break;
                case pathname + '?action=storefronts':
                    result = search === '?action=storefronts' && hashTest('#\\/design\\/theme');
                    break;
                case pathname + '?action=storefronts#/design/pages/':
                    result = search === '?action=storefronts' && hashTest('\\/pages\\/');
                    break;
                default:
                    break;
            }

            return result;
        }

        /**
         * @param {String?} uri
         * */
        Sidebar.prototype.setSidebarLink = function(uri) {
            var that = this,
                max_check = 3,
                count_check = 0;

            var isSettedLink = function(uri) {
                var $link = that.$wrapper.find('a[href="' + uri + '"]:first');
                if ($link.length) {
                    that.setItem($link.closest("li"));

                    return true;
                }

                return false;
            };

            // one more try
            if (!isSettedLink(uri)) {
                var moreTry = function(uri) {
                    if (count_check === max_check || uri.indexOf('#/') !== -1) {
                        return;
                    }

                    var uri_array = uri.split('/').filter(str => !!str) || [];
                    if (uri_array.length < 3) {
                        return;
                    }

                    var new_uri;
                    var shortenURI = () => {
                        uri_array.pop();
                        return '/' + uri_array.join('/') + '/';
                    };

                    if (count_check > 0) {
                        new_uri = shortenURI();
                    } else {
                        var uri_without_query = uri.split('?')
                            uri_length = uri_without_query.length;
                        if (uri_length > 1) {
                            new_uri = uri_without_query[0];
                        } else if(uri_length) {
                            new_uri = shortenURI();
                        } else {
                            count_check = max_check;
                            return;
                        }
                    }

                    count_check += 1;

                    if (isSettedLink(new_uri)) {
                        count_check = max_check;
                    } else {
                        moreTry(new_uri);
                    }
                };
                moreTry(uri);
            }
        }

        /**
         * @param {String?} uri
         * */
        Sidebar.prototype.setActive = function(uri) {
            var that = this;

            if (uri) {
                that.setSidebarLink(uri);
            } else {
                var $links = that.$wrapper.find("a[href^='" + that.app_url + "']"),
                    relative_path = location.pathname + location.search + location.hash,
                    location_string = location.pathname,
                    location_search = location.search,
                    max_length = 0,
                    link_index = 0;

                $links.each(function (index) {
                    var $link = $(this),
                        href = $link.attr("href"),
                        href_length = href.length;

                    var is_absolute_coincidence = that.specifyRuleCheck(href, relative_path);
                    if (is_absolute_coincidence) {
                        link_index = index;
                        return false;

                    } else if (location_string.indexOf(href) >= 0) {
                        if (href_length > max_length) {
                            max_length = href_length;
                            link_index = index;
                        }
                    } else if (location_search && href.includes(location_search)) {
                        link_index = index;
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
                window.parent.document.documentElement.style.setProperty('--main-sidebar-width', that.$wrapper.width() + 'px');

                setTimeout( function() {
                    that.$wrapper.removeClass(disabled_class);
                }, 100);

                that.states.pinned = pin;

                // update button text
                var text = (that.states.pinned ? that.locales["unpin_menu"] : that.locales["pin_menu"]);
                $name.text(text);

                if (send_request) { request(pin); }

                $(document).trigger('wa_toggle_products_page_sidebar', pin);

                $(function() {
                    that.signSidebarPinned('.js-main-content', pin);
                });
            }

            function request(pin) {
                var deferred = $.Deferred();

                var data = { sidebar_menu_state: (pin ? "1" : "0") };

                $.post(that.urls["sidebar_menu_state"], data, "json")
                    .always( function() { deferred.resolve(); });

                return deferred.promise();
            }
        };

        Sidebar.prototype.signSidebarPinned = function(class_container, is_pin) {
            var container = $(class_container, document);
            if (!container.length) {
                return;
            }

            if (is_pin) {
                container.addClass('sidebar-pinned');
            } else {
                container.removeClass('sidebar-pinned');
            }
        }

        return Sidebar;

    })($);

    if ($.wa_shop_products) {
        $.wa_shop_products.init.initProductsSidebar = function(options) {
            return new Sidebar(options);
        };
    }else{
        initMainWaSidebar = function(options) {
            return new Sidebar(options);
        };
    }


})(jQuery);
