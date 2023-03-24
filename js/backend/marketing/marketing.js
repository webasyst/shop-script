( function($) { "use strict";

    var Sidebar = ( function($) {

        Sidebar = function(options) {
            const that = this;

            // DOM
            that.$document = $(document);
            that.$app = $('#wa-app');
            that.$wrapper = options["$wrapper"];
            that.$sidebarSub = that.$app.find('.js-marketing-submenu');
            that.$sidebarMainLink = that.$app.find('.js-marketing-main-link');

            // CONST
            that.urls = options["urls"];

            // DYNAMIC VARS
            that.xhr_reload = null;
            that.reload_timer = 0;
            that.$active_menu_item = false;

            // INIT
            that.init();
        };

        Sidebar.prototype.init = function() {
            const that = this;

            that.setActive();

            that.$document.on("wa_loaded", onLoadWatcher);

            that.toggleSubmenu();

            function onLoadWatcher() {
                const is_exist = $.contains(document, that.$wrapper[0]);

                if (is_exist) {
                    that.setActive();
                } else {
                    that.$document.off("wa_loaded", onLoadWatcher);
                }
            }

            that.initAutoReload();
            that.initDropdown();

            /**
             * @description So it is necessary for compatibility with the promo page
             *  */
            that.$app.on("click", "li > a", clickWatcher);

            function clickWatcher() {
                const is_exist = $.contains(document, that.$wrapper[0]);
                const is_subnav = $.contains(that.$wrapper[0], this);

                if (!is_exist) {
                    that.$document.off("click", "li > a", clickWatcher);

                    return;
                }

                if (is_subnav) {
                    const $link = $(this);
                    const $name = $link.find(".s-name");

                    that.$wrapper.find('.selected').removeClass('selected');
                    if (!$link.closest('.js-marketing-category-dropdown').length) {
                        that.$optionsDropdown.find('.dropdown-toggle').text(that.$dropdownDefaultText)
                    }
                    that.setItem($link.closest("li"));

                    if ($name.length) {
                        $.shop.marketing.setTitle($name.text());
                    }

                    that.$sidebarSub.find('.selected').removeClass('selected');
                } else {
                    const $link = $(this).closest('li');
                    $link.closest('.sidebar').find('.selected').removeClass('selected');
                    $link.addClass('selected');
                }

                that.toggleSubmenu();
            }
        };

        Sidebar.prototype.toggleSubmenu = function() {
            const that = this;

            const isActiveSubmenuItem = that.$sidebarSub.find('.selected').length;
            if (isActiveSubmenuItem) {
                that.$wrapper.hide();
                that.$sidebarMainLink.removeClass('selected');
            } else {
                that.$wrapper.show();
            }
        }

        /**
         * @param {Object} $item
         * */
        Sidebar.prototype.setItem = function($item) {
            const that = this;
            const active_menu_class = "selected";

            that.$active_menu_item = $item.addClass(active_menu_class).siblings().removeClass(active_menu_class);
        };

        /**
         * @param {String?} uri
         * */
        Sidebar.prototype.setActive = function(uri) {
            const that = this;
            let $link;

            if (uri) {
                $link = that.$app.find('a[href="' + uri + '"]');

                if ($link.length) {
                    that.setItem($link.closest("li"));
                }

                return;
            }

            const $links = that.$app.find("a[href^='" + that.urls["app_url"] + "']");
            const relative_path = location.pathname + location.search;

            $links.each(function (index) {
                const $link = $(this);
                const href = $link.attr("href");

                const is_absolute_coincidence = (href === relative_path);
                if (is_absolute_coincidence) {
                    that.setItem($link.closest("li"));
                    return;
                }
            });
        };

        Sidebar.prototype.reload = function() {
            var that = this;

            clearTimeout(that.reload_timer);

            if (that.xhr_reload) {
                that.xhr_reload.abort();
            }

            that.xhr_reload = $.get(that.urls.reload, function(html) {
                    that.$wrapper.replaceWith(html);
                }).always( function() {
                    that.xhr_reload = null;
                });
        };

        Sidebar.prototype.initAutoReload = function() {
            var that = this,
                reload_time = 1000 * 60 * 5;

            that.reload_timer = setTimeout( function() {
                var is_exist = $.contains(document, that.$wrapper[0]);
                if (is_exist) {
                    that.reload();
                }
            }, reload_time);
        };

        Sidebar.prototype.initDropdown = function() {
            const that = this;

            that.$optionsDropdown = this.$wrapper.find('.js-marketing-category-dropdown').waDropdown({
                items: '.menu > li > a',
                ready(dropdown) {
                    that.$dropdownDefaultText = dropdown.$button.data('default-text');

                    const activeItem = dropdown.$menu.find('.selected');

                    if (activeItem.length) {
                        const titleHtml = dropdown.$menu.find('.selected a').html();
                        dropdown.setTitle(titleHtml);
                    }
                }
            });
        };

        return Sidebar;

    })($);

    function loadSources(sources) {
        var deferred = $.Deferred();

        loader(sources).then( function() {
            deferred.resolve();
        }, function(bad_sources) {
            if (console && console.error) {
                console.error("Error loading resource", bad_sources);
            }
            deferred.reject(bad_sources);
        });

        return deferred.promise();

        function loader(sources) {
            var deferred = $.Deferred(),
                counter = sources.length;

            var bad_sources = [];

            $.each(sources, function(i, source) {
                switch (source.type) {
                    case "css":
                        loadCSS(source).then(onLoad, onError);
                        break;
                    case "js":
                        loadJS(source).then(onLoad, onError);
                        break;
                }
            });

            return deferred.promise();

            function loadCSS(source) {
                var deferred = $.Deferred(),
                    promise = deferred.promise();

                var $link = $("#" + source.id);
                if ($link.length) {
                    promise = $link.data("promise");

                } else {
                    $link = $("<link />", {
                        id: source.id,
                        rel: "stylesheet"
                    }).appendTo("head")
                        .data("promise", promise);

                    $link
                        .on("load", function() {
                            deferred.resolve(source);
                        }).on("error", function() {
                        deferred.reject(source);
                    });

                    $link.attr("href", source.uri);
                }

                return promise;
            }

            function loadJS(source) {
                var deferred = $.Deferred(),
                    promise = deferred.promise();

                var $script = $("#" + source.id);
                if ($script.length) {
                    promise = $script.data("promise");

                } else {
                    var script = document.createElement("script");
                    document.getElementsByTagName("head")[0].appendChild(script);

                    $script = $(script)
                        .attr("id", source.id)
                        .data("promise", promise);

                    $script
                        .on("load", function() {
                            deferred.resolve(source);
                        }).on("error", function() {
                        deferred.reject(source);
                    });

                    $script.attr("src", source.uri);
                }

                return promise;
            }

            function onLoad(source) {
                counter -= 1;
                watcher();
            }

            function onError(source) {
                bad_sources.push(source);
                counter -= 1;
                watcher();
            }

            function watcher() {
                if (counter === 0) {
                    if (!bad_sources.length) {
                        deferred.resolve();
                    } else {
                        deferred.reject(bad_sources);
                    }
                }
            }
        }
    }

    /**
     * @param {string|number} price
     * @param {boolean?} text
     * @return {string}
     * */
    function formatPrice(price, text) {
        var result = price,
            format = $.shop.marketing.price_format;

        if (!format) { return result; }

        try {
            price = parseFloat(price).toFixed(format.fraction_size);

            if ( (price >= 0) && format) {
                var price_floor = Math.floor(price),
                    price_string = getGroupedString("" + price_floor, format.group_size, format.group_divider),
                    fraction_string = getFractionString(price - price_floor);

                result = ( text ? format.pattern_text : format.pattern_html ).replace("%s", price_string + fraction_string );
            }

        } catch(e) {
            if (console && console.log) {
                console.log(e.message, price);
            }
        }

        return result;

        function getGroupedString(string, size, divider) {
            var result = "";

            if (!(size && string && divider)) {
                return string;
            }

            var string_array = string.split("").reverse();

            var groups = [];
            var group = [];

            for (var i = 0; i < string_array.length; i++) {
                var letter = string_array[i],
                    is_first = (i === 0),
                    is_last = (i === string_array.length - 1),
                    delta = (i % size);

                if (delta === 0 && !is_first) {
                    groups.unshift(group);
                    group = [];
                }

                group.unshift(letter);

                if (is_last) {
                    groups.unshift(group);
                }
            }

            for (i = 0; i < groups.length; i++) {
                var is_last_group = (i === groups.length - 1),
                    _group = groups[i].join("");

                result += _group + ( is_last_group ? "" : divider );
            }

            return result;
        }

        function getFractionString(number) {
            var result = "";

            if (number > 0) {
                number = number.toFixed(format.fraction_size + 1);
                number = Math.round(number * Math.pow(10, format.fraction_size))/Math.pow(10, format.fraction_size);
                var string = number.toFixed(format.fraction_size);
                result = string.replace("0.", format.fraction_divider);
            }

            return result;
        }
    }

    $.shop = ($.shop || {});

    $.shop.marketing = $.extend($.shop.marketing || {}, {
        content: null, // init at templates\layouts\BackendMarketing.html
        init: {}, // this object will be expanded in content templates.

        sidebar: null, // init at templates\actions\marketing\MarketingSidebar.html
        initSidebar: function(options) {
            return ($.shop.marketing.sidebar = new Sidebar(options));
        },

        title_pattern: "%s", // init at templates\layouts\BackendMarketing.html
        setTitle: function(title_string) {
            if (title_string) {
                var state = history.state;
                if (state) {
                    state.title = title_string;
                }
                document.title = $.shop.marketing.title_pattern.replace("%s", title_string);
            }
        },

        price_format: {
            'code': 'RUB',
            'fraction_divider': ',',
            'fraction_size': 2,
            'group_divider': ' ',
            'group_size': 3,
            'pattern_html': '%s <span class="ruble">₽</span>',
            'pattern_text': '%s руб.',
            'is_primary': true,
            'rate': 1.0,
            'rounding': 0.01,
            'round_up_only': '1'
        },
        formatPrice: formatPrice,

        load: loadSources
    });

})(jQuery);
