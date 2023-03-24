/**
 * @used at wa-apps/shop/templates/actions/settings/SettingsTypefeatList.html
 * Controller for Features Settings Page.
 * */
var ShopFeatureSettingsPage = ( function($) { "use strict";

    // COMPONENTS

    var Switch = ( function($) {

        Switch = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$toggle = $("<span class=\"switch-toggle\" />").appendTo(that.$wrapper);
            that.$field = that.$wrapper.find("input:checkbox:first");

            // VARS
            that.on = {
                ready: (typeof options["ready"] === "function" ? options["ready"] : function() {}),
                change: (typeof options["change"] === "function" ? options["change"] : function() {})
            };

            // DYNAMIC VARS
            that.is_active = (that.$field.length ? that.$field.is(":checked") : false);
            that.is_active = (typeof options["active"] === "boolean" ? options["active"] : that.is_active);

            that.is_disabled = (that.$field.length ? that.$field.is(":disabled") : false);
            that.is_disabled = (typeof options["disabled"] === "boolean" ? options["disabled"] : that.is_disabled);

            // INIT
            that.init();
        };

        Switch.prototype.init = function() {
            var that = this;

            that.set(that.is_active, false);
            that.disable(that.is_disabled);

            that.$wrapper.data("switch", that).trigger("ready", [that]);
            that.on.ready(that);

            that.$wrapper.on("click", function(event) {
                event.preventDefault();
                if (!that.is_disabled) {
                    that.set(!that.is_active);
                }
            });
        };

        /**
         * @param {Boolean} active
         * @param {Boolean?} trigger_change
         * */
        Switch.prototype.set = function(active, trigger_change) {
            var that = this,
                active_class = "is-active";

            trigger_change = (typeof trigger_change === "boolean" ? trigger_change : true);

            if (active) {
                that.$wrapper.addClass(active_class);
            } else {
                that.$wrapper.removeClass(active_class);
            }

            if (that.$field.length) {
                that.$field.attr("checked", active);
            }

            if (trigger_change) {
                if (that.$field.length) {
                    that.$field.trigger("change", [active, that]);
                } else {
                    that.$wrapper.trigger("change", [active, that]);
                }

                that.on.change(active, that);
            }

            that.is_active = active;

            return that.is_active;
        };

        /**
         * @param {Boolean} disable
         * */
        Switch.prototype.disable = function(disable) {
            var that = this,
                disabled_class = "is-disabled";

            if (disable) {
                that.$wrapper.addClass(disabled_class);
            } else {
                that.$wrapper.removeClass(disabled_class);
            }

            that.is_disabled = disable;
        };

        return Switch;

    })($);

    // SECTIONS

    /**
     * @used at wa-apps/shop/templates/actions/settings/SettingsTypefeatSidebar.html
     * Controller for Sidebar at Features Settings Page.
     * */
    var Sidebar = ( function($) {

        Sidebar = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$types_list = that.$wrapper.find(".js-types-list");
            that.$body = that.$wrapper.find("> .s-section-body");

            // CONST
            that.urls = options["urls"];
            that.scope = options["scope"];

            // DYNAMIC VARS
            that.$active_menu_item = false;
            that.body_scroll_top = 0;
            that.search_string = "";

            // INIT
            that.init();
        };

        Sidebar.prototype.init = function() {
            var that = this,
                is_locked = false;

            /** type add */

            that.$wrapper.on("click", ".js-type-add", function(event) {
                event.preventDefault();
                if (!is_locked) {
                    is_locked = true;
                    that.editType(null).always( function () {
                        is_locked = false;
                    });
                }
            });

            /** type edit */

            that.$wrapper.on("click", ".js-type-edit", function(event) {
                event.preventDefault();
                if (!is_locked) {
                    is_locked = true;

                    var $type = $(this).closest(".js-type-wrapper"),
                        type_id = $type.data("id");

                    that.editType(type_id).always( function () {
                        is_locked = false;
                    });
                }
            });

            /** type copy */

            that.$wrapper.on("click", ".js-type-copy", function(event) {
                event.preventDefault();
                copyType($(this));
            });

            function copyType($link) {
                if (!is_locked) {
                    is_locked = true;

                    var $type = $link.closest(".s-type-wrapper"),
                        $icon = $link.find(".icon16"),
                        type_id = $type.data("id");

                    var $loading = $("<i class=\"icon16 loading\" />").insertAfter( $icon.hide() );

                    $.post(that.urls["type_copy"], { id: type_id }, "json")
                        .fail( function() {
                            alert("ERROR: COPY TYPE");
                            that.scope.reload({
                                type_id: that.scope.type_id
                            });
                        })
                        .done( function(response) {
                            if (response.status === "ok") {
                                that.scope.reload({
                                    type_id: that.scope.type_id
                                });
                            } else {
                                alert("ERROR: COPY TYPE");
                                that.scope.reload({
                                    type_id: that.scope.type_id
                                });
                            }
                        });
                }
            }

            /** type delete */

            that.$wrapper.on("click", ".js-type-delete", function(event) {
                event.preventDefault();
                if (!is_locked) {
                    is_locked = true;

                    var $type = $(this).closest(".js-type-wrapper"),
                        type_id = $type.data("id");

                    that.deleteType(type_id).always( function() {
                        is_locked = false;
                    });
                }
            });

            /** other type actions */

            that.$wrapper.on("click", ".js-type-wrapper > a", function(event) {
                event.preventDefault();

                var $link = $(this),
                    $type = $link.closest("li"),
                    $icon = $link.find(".s-icon .icon16"),
                    type_id = $type.data("id");

                $icon.hide().after("<i class=\"icon16 loading\" />");

                that.setItem($type);

                var options = {};
                if (type_id) { options["type_id"] = type_id; }
                that.scope.reload(options);
            });

            that.$body.on("scroll", function() {
                that.body_scroll_top = this.scrollTop;
            });

            that.setActive();
            that.initSearch();

            if (that.body_scroll_top) {
                that.$body.scrollTop(that.body_scroll_top);
            } else {
                that.scrollToActive();
            }
        };

        Sidebar.prototype.initSearch = function() {
            var that = this;

            var $section = that.$wrapper.find(".s-search-section"),
                $field = $section.find(".js-search-field");

            $section.on("click", ".js-run-search", function() {
                search();
            });

            $field.on("keyup", function() {
                that.search_string = $.trim($field.val());
                search();
            });

            function search() {
                var $items = that.$types_list.find(".js-type-wrapper"),
                    value = $.trim($field.val());

                if (value.length) {
                    value = value.toLowerCase();

                    $items.each( function() {
                        var $item = $(this),
                            text = $.trim($item.find(".js-name").text());

                        text = text
                            .replace(/[\s]{2,}/g, "")
                            .replace(/[\n\r ]{2,}/g, "")
                            .toLowerCase();

                        if (text.indexOf(value) >= 0) {
                            $item.show();
                        } else {
                            $item.hide();
                        }
                    });
                } else {
                    $items.show();
                }
            }
        };

        /**
         * @param {Object} $item
         * */
        Sidebar.prototype.setItem = function($item) {
            var that = this,
                active_menu_class = "selected";

            if (that.$active_menu_item) {
                if (that.$active_menu_item[0] === $item[0]) {
                    return false;
                }
                that.$active_menu_item.removeClass(active_menu_class);
            }

            that.$active_menu_item = $item.addClass(active_menu_class);

            try {
                var href = $item.find("> a").attr("href"),
                    hash_index = href.indexOf("#") + 1,
                    hash = href.substr(hash_index);
                $.settings.forceHash(hash);
            } catch(e) {
                alert("Set Hash Error");
            }
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
                var $links = that.$wrapper.find("a[href^='" + that.urls["app_url"] + "']"),
                    relative_path = location.pathname + location.search + location.hash,
                    location_string = location.pathname,
                    max_length = 0,
                    link_index = 0;

                $links.each( function(index) {
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

        Sidebar.prototype.refresh = function(options) {
            var that = this;

            if (options.search_string) {
                var $field = that.$wrapper.find(".s-search-section .js-search-field");
                $field.val(options.search_string).trigger("keyup");
            }

            if (options.body_scroll_top) {
                that.$body.scrollTop(options.body_scroll_top);
            }
        };

        Sidebar.prototype.deleteType = function(type_id) {
            var that = this;

            var href = that.urls["type_delete"],
                data = { type: type_id };

            return $.post(href, data).done( function(html) {
                var dialog = $.waDialog({
                    html: html
                });

                var type_delete_dialog = new TypeDeleteDialog({
                    $wrapper: dialog.$wrapper,
                    dialog: dialog,
                    scope: that,
                    type_id: type_id
                });
            });
        };

        Sidebar.prototype.editType = function(type_id) {
            var that = this;

            var href = that.urls["type_edit"],
                data = {};

            if (type_id) {
                data.type = type_id
            }

            return $.post(href, data).done( function(html) {
                var dialog = $.waDialog({
                    html: html,
                    options: {
                        initDialog: function(options) {
                            options["scope"] = that;
                            return new TypeEditDialog(options);
                        }
                    },
                    debug_output: 1
                });
            });
        };

        Sidebar.prototype.scrollToActive = function() {
            var that = this;

            var $selected = that.$types_list.find(".js-type-wrapper.selected:first");
            if (!$selected.length) { return false; }

            that.$body.scrollTop(0);

            var element_top = $selected.offset().top,
                list_top = that.$types_list.offset().top;

            that.$body.scrollTop(element_top - list_top);
        };

        return Sidebar;

    })($);

    var Content = ( function($) {

        Content = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$features_list = that.$wrapper.find(".js-features-list");
            that.$codes_list = that.$wrapper.find(".js-codes-list");
            that.$search_area = that.$wrapper.find(".js-features-list, .js-codes-list");
            that.$body = that.$wrapper.find("> .s-section-body");

            // CONST
            that.scope = options["scope"];
            that.urls = that.scope["urls"];
            that.type_id = that.scope["type_id"];

            // DYNAMIC VARS
            that.body_scroll_top = 0;
            that.search_string = "";

            // INIT
            that.init();
        };

        Content.prototype.init = function() {
            var that = this;

            that.initFeatureActions();

            that.$body.on("scroll", function() {
                that.body_scroll_top = this.scrollTop;
            });

            that.initTypeActions();
            that.initSearch();
            that.initSortable();
        };

        Content.prototype.initTypeActions = function() {
            var that = this,
                is_locked = false;

            that.$wrapper.on("click", ".js-type-edit", function(event) {
                event.preventDefault();

                var $link = $(this),
                    $icon = $link.find(".icon16"),
                    $loading = $("<i class=\"icon16 loading\" />").insertAfter($icon.hide());

                if (!is_locked) {
                    is_locked = true;
                    window.shop_feature_settings_page.sidebar.editType(that.type_id).always( function() {
                        $loading.remove();
                        $icon.show();
                        is_locked = false;
                    });
                }
            });

            that.$wrapper.on("click", ".js-type-delete", function(event) {
                event.preventDefault();

                var $link = $(this),
                    $icon = $link.find(".icon16"),
                    $loading = $("<i class=\"icon16 loading\" />").insertAfter($icon.hide());

                if (!is_locked) {
                    is_locked = true;
                    window.shop_feature_settings_page.sidebar.deleteType(that.type_id).always( function() {
                        $loading.remove();
                        $icon.show();
                        is_locked = false;
                    });
                }
            });
        };

        Content.prototype.initSearch = function() {
            var that = this;

            var $section = that.$wrapper.find(".s-search-section"),
                $field = $section.find(".js-search-field");

            $field.on("keyup", function() {
                that.search_string = $.trim($field.val());
                search();
            });

            function search() {
                var $items = that.$search_area.find(".js-feature-wrapper, .js-code-wrapper"),
                    $features_empty_search = that.$features_list.find('.js-empty-search'),
                    $codes_empty_search = that.$codes_list.find('.js-empty-search'),
                    value = $.trim($field.val()),
                    filtered_class = "is-filtered";

                if (value.length) {
                    that.$wrapper.addClass(filtered_class);

                    value = value.toLowerCase();

                    $items.each( function() {
                        var $item = $(this),
                            text = "" + $.trim($item.find(".js-search-place").text());
                        text = text
                            .replace(/[\s]{2,}/g, "")
                            .replace(/[\n\r ]{2,}/g, "")
                            .toLowerCase();

                        if (text.indexOf(value) >= 0) {
                            $item.show();
                        } else {
                            $item.hide();
                        }
                    });

                    var finds_features = that.$features_list.children().filter(':not(.js-empty-search):visible').length,
                        finds_codes = that.$codes_list.children().filter(':not(.js-empty-search):visible').length;

                    if (finds_features === 0) {
                        $features_empty_search.show();
                    } else {
                        $features_empty_search.hide();
                    }

                    if (finds_codes === 0) {
                        $codes_empty_search.show();
                    } else {
                        $codes_empty_search.hide();
                    }

                } else {
                    $features_empty_search.hide();
                    $codes_empty_search.hide();
                    that.$wrapper.removeClass(filtered_class);
                    $items.show();
                }
            }
        };

        Content.prototype.initFeatureActions = function() {
            var that = this,
                is_locked = false,
                add_is_locked = false,
                code_add_is_locked = false;

            var disabled_class = "is-disabled";

            /** FEATURE */

            /** feature add */

            that.$wrapper.on("click", ".js-feature-add-existing", function(event) {
                event.preventDefault();
                showExistingFeatureAddDialog({
                    $link: $(this)
                });
            });

            that.$wrapper.on("click", ".js-feature-add-new", function(event) {
                event.preventDefault();
                showFeatureAddDialog($(this));
            });

            that.$wrapper.on("click", ".js-feature-add-divider", function(event) {
                event.preventDefault();
                showFeatureAddDialog($(this));
            });

            function showFeatureAddDialog($link) {
                if (!add_is_locked) {
                    add_is_locked = true;

                    var $loading = $("<i class=\"icon16 loading\" />").appendTo($link);

                    $link
                        // always
                        .on("feature_dialog_load_fail feature_dialog_load_done", function() {
                            $loading.remove();
                            add_is_locked = false;
                        })
                        // success create
                        .on("feature_dialog_feature_created", function(event, feature_data, data) {
                            data.animation.lock();
                            that.scope.reload({
                                type_id: that.scope.type_id
                            }).always( function() {
                                window.shop_feature_settings_page.content.highlightFeatures([feature_data.id]);
                                data.animation.unlock();
                                data.dialog.close();
                            });
                        });
                }
            }

            function showExistingFeatureAddDialog(options) {
                if (add_is_locked) {
                    return;
                }
                add_is_locked = true;

                var $link = options.$link;

                var $loading = $("<i class=\"icon16 loading\" />").appendTo($link);

                var href = that.urls["feature_add_existing"],
                    data = { type_id: that.type_id };

                $.post(href, data)
                    .always( function () {
                        $loading.remove();
                        add_is_locked = false;
                    })
                    .done( function(html) {
                        var dialog = $.waDialog({
                            html: html,
                            debug_output: true,
                            options: {
                                scope: that,
                                onSuccess: function(feature_ids) {
                                    window.shop_feature_settings_page.content.highlightFeatures(feature_ids);
                                }
                            }
                        });
                    });
            }

            /** feature visibility */

            that.$wrapper.on("click", ".js-feature-visibility-toggle", function(event) {
                event.preventDefault();
                visibilityToggle($(this));
            });

            function visibilityToggle($link) {
                var $feature = $link.closest(".js-feature-wrapper"),
                    $icon = $link.find(".icon16"),
                    feature_id = $feature.data("id"),
                    is_locked = !!$feature.data("visibility-locked"),
                    is_enabled = !!$feature.data("visibility-enabled");

                $feature.data("visibility-locked", !is_locked);

                if (!is_locked) {
                    $feature.data("visibility-locked", true);

                    var $loading = $("<i class=\"icon16 loading\" />").insertAfter( $icon.hide() );

                    var href = that.urls["toggle"],
                        data = {
                            param: "visibility",
                            feature_id: feature_id,
                            value: (is_enabled ? 0 : 1)
                        };

                    if (that.type_id) { data["type_id"] = that.type_id; }

                    $.post(href, data, "json")
                        .always( function() {
                            $loading.remove();
                            $icon.show();
                            $feature.data("visibility-locked", "");
                        })
                        .done( function() {
                            $feature
                                .attr("data-visibility-enabled", (is_enabled ? 0 : 1) )
                                .data("visibility-enabled", (is_enabled ? 0 : 1));

                            if (is_enabled) { $feature.addClass(disabled_class) }
                            else { $feature.removeClass(disabled_class) }

                            $icon
                                .removeClass(is_enabled ? "visibility-on" : "ss visibility")
                                .addClass(!is_enabled ? "visibility-on" : "ss visibility");
                        });
                }
            }

            /** feature toggle */

            that.$wrapper.on("click", ".js-feature-sku-toggle", function(event) {
                event.preventDefault();
                skuToggle($(this));
            });

            that.$wrapper.on("click", ".js-feature-sku-toggle-always", function(event) {
                event.preventDefault();
                alert($(this).attr('title'));
            });

            function skuToggle($link) {
                var $feature = $link.closest(".js-feature-wrapper"),
                    $icon = $link.find(".icon16"),
                    feature_id = $feature.data("id"),
                    is_locked = !!$feature.data("sku-locked"),
                    is_enabled = !!$feature.data("sku-enabled");

                if (!is_locked) {
                    $feature.data("sku-locked", true);

                    var $loading = $("<i class=\"icon16 loading\" />").insertAfter( $icon.hide() );

                    var data = {
                            param: "sku",
                            feature_id: feature_id,
                            value: (is_enabled ? 0 : 1)
                        };

                    if (that.type_id) { data["type_id"] = that.type_id; }

                    sendSkuToggleRequest(data).then(function(result) {

                        if (result === "ok") {
                            // Remember new state of the toggle if it changed
                            $feature.data("sku-enabled", (is_enabled ? '' : '1'));
                            $icon
                                .removeClass(is_enabled ? "hierarchical" : "hierarchical-off")
                                .addClass(!is_enabled ? "hierarchical" : "hierarchical-off");
                        }

                        // Remove loading indicator and unlock feature row
                        $loading.remove();
                        $icon.show();
                        $feature.data("sku-locked", "");
                    });
                }

                // XHR request to toggle SKU setting may return error or warning.
                // Error is to be shown as alert(). Warning is to be shown as confirm()
                // and if user confirms, we need to send another XHR with confirmation.
                function sendSkuToggleRequest(data) {
                    var href = that.urls["toggle"];

                    var deferred = $.Deferred();

                    $.post(href, data, "json").then(function(result) {

                        // First request succeded, no problem
                        if (result.status === "ok") {
                            deferred.resolve('ok');
                            return;
                        }

                        // Server returns something strange: just dump to console. This should not happen normally.
                        if (!result.errors || !result.errors[0] || !result.errors[0].text) {
                            console.log('Unable to toggle feature SKU status: unknown server error', result);
                            deferred.resolve('fail');
                            return;
                        }

                        var err = result.errors[0];

                        // Server returns a warning. Ask user confirmation and send another XHR if user accepts.
                        if (err.type === 'warning') {
                            if (!confirm(err.text)) {
                                // User did not confirm operation after warning.
                                deferred.resolve('fail');
                                return;
                            }

                            // User confirmed operation even after warning, let's force it.
                            data.force = 1;
                            $.post(href, data, 'json').then(function(result) {

                                if (result.status === 'ok') {
                                    // Second request succeded
                                    deferred.resolve('ok');
                                } else {
                                    // Second request failed. This should not happen normally.
                                    console.log('Unable to force-toggle feature SKU status: unknown server error', arguments);
                                    deferred.resolve('fail');
                                }
                            }, function() {
                                // Unknown server error (non-200 status, can not parse JSON etc.)
                                // This should not happen normally.
                                console.log('Unable to force-toggle feature SKU status: unknown server XHR error', arguments);
                                deferred.resolve('fail');
                            });
                            return;
                        }

                        // Server returns an error. Show it to user and bail.
                        if (err.type === 'fatal') {
                            alert(err.text);
                        } else {
                            // This should not happen normally.
                            console.log('Unable to toggle feature SKU status - server error:', err.text);
                        }
                        deferred.resolve('fail');

                    }, function() {
                        // Unknown server error (non-200 status, can not parse JSON etc.)
                        // This should not happen normally.
                        console.log('Unable to toggle feature SKU status: unknown server XHR error', arguments);
                        deferred.resolve('fail');
                    });

                    return deferred.promise();
                }
            }

            /** feature delete */

            that.$wrapper.on("click", ".js-feature-delete", function(event) {
                event.preventDefault();
                showFeatureDeleteDialog($(this));
            });

            that.$wrapper.on("click", ".js-feature-undeletable, .js-code-undeletable", function(event) {
                event.preventDefault();
                alert($(this).attr('title'));
            });

            function showFeatureDeleteDialog($link) {
                var $feature = $link.closest(".js-feature-wrapper"),
                    $icon = $link.find(".icon16"),
                    feature_id = $feature.data("id");

                var is_locked = !!$feature.data("locked");
                if (!is_locked) {
                    $feature.data("locked", true);

                    var $loading = $("<i class=\"icon16 loading\" />").insertAfter( $icon.hide() );

                    $.post(that.urls["feature_delete_dialog"], { id: feature_id }, "json")
                        .always( function() {
                            $loading.remove();
                            $icon.show();
                            $feature.data("locked", false);
                        })
                        .done( function(html) {
                            var dialog = $.waDialog({
                                html: html,
                                onOpen: function($wrapper, dialog) {
                                    var feature_delete_dialog = new FeatureDeleteDialog({
                                        $wrapper: dialog.$wrapper,
                                        dialog: dialog,
                                        scope: that,
                                        feature_id: feature_id
                                    });
                                },
                                options: {
                                    onSuccess: function() {
                                        $feature.remove();
                                    }
                                }
                            });
                        });

                }
            }

            /** feature edit */

            that.$wrapper.on("click", ".js-feature-edit", function(event) {
                event.preventDefault();
                showFeatureEditDialog($(this));
            });

            function showFeatureEditDialog($link) {
                var $feature = $link.closest(".js-feature-wrapper"),
                    $icon = $link.find(".icon16"),
                    feature_code = $feature.data("code"),
                    feature_id = $feature.data("id");

                var is_locked = !!$feature.data("locked");
                if (!is_locked) {
                    $feature.data("locked", true);

                    var $loading = $("<i class=\"icon16 loading\" />").insertAfter( $icon.hide() );

                    $link
                        // always
                        .on("feature_dialog_load_fail feature_dialog_load_done", function() {
                            $loading.remove();
                            $icon.show();
                            $feature.data("locked", false);
                        })
                        // success update
                        .on("feature_dialog_feature_updated", function(event, feature_data, data) {
                            data.animation.lock();
                            that.scope.reload({
                                type_id: that.scope.type_id
                            }).always( function() {
                                data.animation.unlock();
                                data.dialog.close();
                            });
                        });
                }
            }

            /** feature copy */

            that.$wrapper.on("click", ".js-feature-copy", function(event) {
                event.preventDefault();
                copyFeature($(this));
            });

            function copyFeature($link) {
                if (!is_locked) {
                    is_locked = true;

                    var $feature = $link.closest(".js-feature-wrapper"),
                        $icon = $link.find(".icon16"),
                        feature_code = $feature.data("code"),
                        feature_id = $feature.data("id");

                    var $loading = $("<i class=\"icon16 loading\" />").insertAfter( $icon.hide() );

                    $.post(that.urls["feature_copy"], { id: feature_id }, "json")
                        .fail( function() {
                            alert("ERROR: COPY FEATURE");
                            that.scope.reload({
                                type_id: that.type_id
                            });
                        })
                        .done( function(response) {
                            if (response.status === "ok") {
                                that.scope.reload({
                                    type_id: that.type_id
                                });
                            } else {
                                alert("ERROR: COPY FEATURE");
                                that.scope.reload({
                                    type_id: that.type_id
                                });
                            }
                        });
                }
            }

            /** CODE */

            /** code add */

            that.$wrapper.on("click", ".js-code-add", function(event) {
                event.preventDefault();
                showCodeAddDialog({
                    $link: $(this),
                    mode: "new"
                });
            });

            function showCodeAddDialog(options) {
                var $link = options.$link;

                if (!code_add_is_locked) {
                    code_add_is_locked = true;

                    var $loading = $("<i class=\"icon16 loading\" />").appendTo($link);

                    var href = that.urls["code_edit"],
                        data = {
                             type_id: (options.mode === "new" ? "all_existing" : that.type_id)
                        };

                    $.post(href, data)
                        .always( function () {
                            $loading.remove();
                            code_add_is_locked = false;
                        })
                        .done( function(html) {
                            var dialog = $.waDialog({
                                html: html,
                                debug_output: true,
                                options: {
                                    scope: that
                                }
                            });
                        });
                }
            }

            /** code edit */

            that.$wrapper.on("click", ".js-code-edit", function(event) {
                event.preventDefault();
                showCodeEditDialog($(this));
            });

            function showCodeEditDialog($link) {
                var $feature = $link.closest(".js-code-wrapper"),
                    $icon = $link.find(".icon16"),
                    code_code = $feature.data("code"),
                    code_id = $feature.data("id");

                var is_locked = !!$feature.data("locked");
                if (!is_locked) {
                    $feature.data("locked", true);

                    var $loading = $("<i class=\"icon16 loading\" />").insertAfter( $icon.hide() );

                    var href = that.urls["code_edit"],
                        data = { id: code_id, code: code_code };

                    $.post(href, data)
                        .always( function () {
                            $loading.remove();
                            $icon.show();
                            $feature.data("locked", false);
                        })
                        .done( function(html) {
                            var dialog = $.waDialog({
                                html: html,
                                debug_output: true,
                                options: {
                                    scope: that
                                }
                            });
                        });
                }
            }

            /** code copy */

            that.$wrapper.on("click", ".js-code-copy", function(event) {
                event.preventDefault();
                copyCode($(this));
            });

            function copyCode($link) {
                if (!is_locked) {
                    is_locked = true;

                    var $code = $link.closest(".js-code-wrapper"),
                        $icon = $link.find(".icon16"),
                        code_id = $code.data("id");

                    var $loading = $("<i class=\"icon16 loading\" />").insertAfter( $icon.hide() );

                    $.post(that.urls["code_copy"], { id: code_id }, "json")
                        .fail( function() {
                            alert("ERROR: COPY CODE");
                            that.scope.reload({
                                type_id: that.type_id
                            });
                        })
                        .done( function(response) {
                            if (response.status === "ok") {
                                that.scope.reload({
                                    type_id: that.type_id
                                });
                            } else {
                                alert("ERROR: COPY CODE");
                                that.scope.reload({
                                    type_id: that.type_id
                                });
                            }
                        });
                }
            }

            /** code delete */

            that.$wrapper.on("click", ".js-code-delete", function(event) {
                event.preventDefault();
                showCodeDeleteDialog($(this));
            });

            function showCodeDeleteDialog($link) {
                var $code = $link.closest(".js-code-wrapper"),
                    $icon = $link.find(".icon16"),
                    code_id = $code.data("id");

                var is_locked = !!$code.data("locked");
                if (!is_locked) {
                    $code.data("locked", true);

                    var $loading = $("<i class=\"icon16 loading\" />").insertAfter( $icon.hide() );

                    $.post(that.urls["code_delete_dialog"], { id: code_id }, "json")
                        .always( function() {
                            $loading.remove();
                            $icon.show();
                            $code.data("locked", false);
                        })
                        .done( function(html) {
                            var dialog = $.waDialog({
                                html: html,
                                onOpen: function($wrapper, dialog) {
                                    var code_delete_dialog = new CodeDeleteDialog({
                                        $wrapper: dialog.$wrapper,
                                        dialog: dialog,
                                        scope: that,
                                        code_id: code_id
                                    });
                                },
                                options: {
                                    onSuccess: function() {
                                        $code.remove();
                                    }
                                }
                            });
                        });

                }
            }
        };

        Content.prototype.initSortable = function() {
            var that = this;
            var $features_list = that.$wrapper.find(".js-features-list");

            if ($features_list.find('.js-feature-sort-toggle').length <= 0) { return false; }

            var xhr = null;

            $features_list.sortable({
                distance: 5,
                opacity: 0.75,
                containment: "parent",
                items: "> .js-feature-wrapper",
                handle: ".js-feature-sort-toggle",
                cursor: "move",
                tolerance: "pointer",
                update: function(event, ui) {
                    var feature_ids = $features_list.find(".js-feature-wrapper").map( function() {
                        return $(this).data("id");
                    }).get();

                    if (xhr) { xhr.abort(); }

                    xhr = $.post(that.urls["feature_sort"], {
                        type_id: that.type_id,
                        ids: feature_ids
                    }, "json")
                        .always( function() {
                            xhr = null;
                        });
                }
            });
        };

        Content.prototype.refresh = function(options) {
            var that = this;

            if (options.search_string) {
                var $field = that.$wrapper.find(".s-search-section .js-search-field");
                $field.val(options.search_string).trigger("keyup");
            }

            if (options.body_scroll_top) {
                that.$body.scrollTop(options.body_scroll_top);
            }
        };

        Content.prototype.highlightFeatures = function(feature_ids) {
            var that = this;

            if (feature_ids.length) {
                $.each(feature_ids, function(i, feature_id) {
                    markFeature(feature_id, (i === 0));
                });
            }

            function markFeature(feature_id, use_scroll) {
                var $new_feature = that.$features_list.find(".js-feature-wrapper[data-id=\"" + feature_id + "\"]"),
                    active_class = "highlighted";

                if ($new_feature.length) {
                    $new_feature
                        .addClass(active_class)
                        .one("hover", function() {
                            $new_feature.removeClass(active_class);
                        });

                    if (use_scroll) {
                        var body_top = that.$body.offset().top,
                            feature_top = $new_feature.offset().top,
                            scroll_top = that.$body.scrollTop();

                        that.$body.scrollTop(scroll_top + feature_top - body_top);
                    }
                }
            }
        };

        Content.prototype.initExistingFeatureAddDialog = function(options) {
            options.scope = this;
            return new ExistingFeatureAddDialog(options);
        };

        Content.prototype.initCodeEditDialog = function(options) {
            options.scope = this;
            return new CodeEditDialog(options);
        };

        return Content;

    })(jQuery);

    // DIALOGS

    /**
     * @used at wa-apps/shop/templates/actions/settings/SettingsTypefeatTypeEdit.html
     * */
    var TypeEditDialog = ( function($) {

        var Dialog = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];

            // CONST
            that.scope = options["scope"];
            that.urls = that.scope["urls"];
            that.dialog = options["dialog"];
            that.locales = options["locales"];
            that.templates = options["templates"];
            that.type = options["type"];
            that.is_premium = options["is_premium"];
            that.fractional = formatFractional(options["fractional"]);

            // CALLBACKS
            that.onSuccess = (options["onSuccess"] || function() {});

            // VARS
            that.errors = {};

            // INIT
            that.vue_model = that.initFractional();
            that.init();

            console.log( that );

            function formatFractional(fractional) {
                if (fractional.stock_base_ratio.value === 0) { fractional.stock_base_ratio.value = ""; }

                if (fractional.order_multiplicity_factor.value === "") { fractional.order_multiplicity_factor.value = fractional.order_multiplicity_factor.default; }
                if (fractional.order_count_step.value === 0) { fractional.order_count_step.value = fractional.order_count_step.default; }
                if (fractional.order_count_min.value === 0) { fractional.order_count_min.value = fractional.order_count_min.default; }

                fractional.stock_base_ratio.value = formatValue(fractional.stock_base_ratio.value);
                fractional.count_denominator.value = formatValue(fractional.count_denominator.value);
                fractional.order_multiplicity_factor.value = formatValue(fractional.order_multiplicity_factor.value);
                fractional.order_count_step.value = formatValue(fractional.order_count_step.value);
                fractional.order_count_min.value = formatValue(fractional.order_count_min.value);

                fractional.stock_unit_before = clone(fractional.stock_unit);
                fractional.base_unit_before = clone(fractional.base_unit);
                fractional.order_multiplicity_factor_before = clone(fractional.order_multiplicity_factor);
                fractional.stock_base_ratio_before = clone(fractional.stock_base_ratio);
                fractional.order_count_min_before = clone(fractional.order_count_min);
                fractional.order_count_step_before = clone(fractional.order_count_step);

                return fractional;

                function formatValue(value) {
                    value = parseFloat(value);
                    return (value >= 0 ? "" + value : "");
                }
            }
        };

        Dialog.prototype.init = function() {
            var that = this;

            that.initFrontList();
            that.initToggle();
            that.initSubmit();
        };

        Dialog.prototype.initFractional = function() {
            var that = this;

            var $section = that.$wrapper.find(".js-fractional-section");
            if (!$section.length) { return null; }

            return new Vue({
                el: $section[0],
                data: {
                    fractional: that.fractional,
                    errors: that.errors
                },
                components: {
                    "component-switch": {
                        props: ["value", "disabled", "title", "class_switch"],
                        data: function() {
                            var self = this,
                                title_data = (typeof self.title === "string" ? self.title : null);

                            self.title = null;

                            return {
                                checked: (typeof this.value === "boolean" ? this.value : false),
                                disabled: (typeof this.disabled === "boolean" ? this.disabled : false),
                                class_switch: (typeof this.class_switch === "string" ? this.class_switch : null),
                                title_data: title_data
                            };
                        },
                        template: that.templates["component-switch"],
                        delimiters: ['{ { ', ' } }'],
                        mounted: function() {
                            var self = this,
                                $switch = $(self.$el).find(".wa-switch");

                            new Switch({
                                $wrapper: $switch,
                                change: function(active, wa_switch) {
                                    self.$emit("change", active);
                                    self.$emit("input", active);
                                }
                            });
                        }
                    }
                },
                computed: {
                    selected_stock_unit: function () {
                        var self = this,
                            result = null;

                        if (self.fractional.stock_unit.value) {
                            var unit_search = self.fractional.units.filter( function(unit) {
                                return (unit.value === self.fractional.stock_unit.value);
                            });

                            if (unit_search.length) {
                                result = unit_search[0].name_short;
                            }
                        }

                        return result;
                    },
                    selected_base_unit: function () {
                        var self = this,
                            result = null;

                        if (self.fractional.base_unit.value) {
                            var unit_search = self.fractional.units.filter( function(unit) {
                                return (unit.value === self.fractional.base_unit.value);
                            });

                            if (unit_search.length) {
                                result = unit_search[0].name_short;
                            }
                        }

                        return result;
                    },

                    stock_unit_fixed: function () {
                        var self = this,
                            result = 2;

                        if (self.fractional.stock_unit.enabled) {
                            result = (self.fractional.stock_unit.editable ? 0 : 1);
                        }

                        return result;
                    },
                    base_unit_fixed: function () {
                        var self = this,
                            result = 2;

                        if (self.fractional.base_unit.enabled) {
                            result = (self.fractional.base_unit.editable ? 0 : 1);
                        }

                        return result;
                    },
                    stock_base_ratio_fixed: function () {
                        var self = this,
                            result = 2;

                        if (self.fractional.stock_base_ratio.enabled) {
                            result = (self.fractional.stock_base_ratio.editable ? 0 : 1);
                        }

                        return result;
                    },
                    order_multiplicity_factor_fixed: function () {
                        var self = this,
                            result = 2;

                        if (self.fractional.order_multiplicity_factor.enabled) {
                            result = (self.fractional.order_multiplicity_factor.editable ? 0 : 1);
                        }

                        return result;
                    },
                    order_count_step_fixed: function () {
                        var self = this,
                            result = 2;

                        if (self.fractional.order_count_step.enabled) {
                            result = (self.fractional.order_count_step.editable ? 0 : 1);
                        }

                        return result;
                    },
                    order_count_min_fixed: function () {
                        var self = this,
                            result = 2;

                        if (self.fractional.order_count_min.enabled) {
                            result = (self.fractional.order_count_min.editable ? 0 : 1);
                        }

                        return result;
                    }
                },
                methods: {
                    onChangeStockUnitEnabled: function(value) {
                        var self = this;
                        self.fractional.stock_unit.enabled = value;
                        if (value) {
                            self.fractional.stock_unit.default = (typeof self.fractional.units[1] === 'undefined' ? '0' : self.fractional.units[1]['value']);
                            that.$wrapper.find('[name="stock_unit_id"] > option[value="0"]').hide();

                            self.fractional.stock_unit.editable = true;
                            self.onChangeStockUnitEditable();
                        } else {
                            self.fractional.stock_unit.default = 0;
                            that.$wrapper.find('[name="stock_unit_id"] > option[value="0"]').show();
                        }
                        self.fractional.stock_unit.value = self.fractional.stock_unit.default;

                        if ((self.fractional.stock_unit.enabled !== self.fractional.base_unit.enabled) && !self.fractional.stock_unit.enabled) {
                            that.$wrapper.find(".js-switch-base-unit").trigger("click");
                        }

                        self.checkUnits();
                    },
                    onChangeBaseUnitEnabled: function(value) {
                        var self = this;
                        self.fractional.base_unit.enabled = value;
                        that.$wrapper.find('[name="base_unit_id"] > option[value="0"]').hide();
                        if (value) {
                            self.fractional.base_unit.editable = true;
                            self.onChangeBaseUnitEditable();
                            self.fractional.base_unit.value = "";
                        } else {
                            self.fractional.base_unit.value = self.fractional.base_unit.default;
                        }

                        if ((self.fractional.stock_unit.enabled !== self.fractional.base_unit.enabled) && self.fractional.base_unit.enabled) {
                            that.$wrapper.find(".js-switch-stock-unit").trigger("click");
                        }

                        self.checkUnits();
                    },
                    onChangeCountDenominatorEnabled: function(value) {
                        var self = this;
                        self.fractional.order_multiplicity_factor.enabled = value;
                        if (value) {
                            self.fractional.order_multiplicity_factor.editable = true;
                            self.onChangeCountDenominatorEditable();
                        }
                        self.fractional.order_multiplicity_factor.value = self.fractional.order_multiplicity_factor.default;

                        self.checkOrderCountStep();
                        self.checkOrderCountMin();
                    },
                    onChangeOrderCountStepEnabled: function(value) {
                        var self = this;
                        self.fractional.order_count_step.enabled = value;
                        if (value) {
                            self.fractional.order_count_step.editable = true;
                            self.onChangeOrderCountStepEditable();
                        }
                        self.fractional.order_count_step.value = (value && self.fractional.order_multiplicity_factor.value ? self.fractional.order_multiplicity_factor.value : self.fractional.order_count_step.default);

                        self.checkOrderCountStep();
                    },
                    onChangeOrderCountMinEnabled: function(value) {
                        var self = this;
                        self.fractional.order_count_min.enabled = value;
                        if (value) {
                            self.fractional.order_count_min.editable = true;
                            self.onChangeOrderCountMinEditable();
                        }
                        self.fractional.order_count_min.value = (value && self.fractional.order_multiplicity_factor.value ? self.fractional.order_multiplicity_factor.value : self.fractional.order_count_min.default);

                        self.checkOrderCountMin();
                    },

                    onChangeStockUnitEditable: function() {
                        var self = this,
                            editable = self.fractional.stock_unit.editable;

                        if (!editable) {
                            if (!self.fractional.stock_unit.value) {
                                self.fractional.stock_unit.value = self.fractional.units[0].value;
                                self.checkUnits();
                            }
                        }
                    },
                    onChangeBaseUnitEditable: function() {
                        var self = this,
                            editable = self.fractional.base_unit.editable;

                        var $select = that.$wrapper.find("select[name=\"base_unit_id\"]"),
                            $option = $select.find("option[value=\"\"]");

                        if (editable) {
                            $option.show();
                        } else {
                            $option.hide();
                            if (!self.fractional.base_unit.value) {
                                self.fractional.base_unit.value = self.fractional.units[0].value;
                                self.checkUnits();
                            }
                        }

                        self.checkStockBaseRatio();
                    },
                    onChangeCountDenominatorEditable: function() {
                        var self = this,
                            editable = self.fractional.order_multiplicity_factor.editable;

                        if (!editable) {
                            if (!self.fractional.order_multiplicity_factor.value) {
                                self.fractional.order_multiplicity_factor.value = self.fractional.denominators[0].value;
                            }
                        }

                        self.fractional.order_count_step.editable = true;
                        self.fractional.order_count_min.editable = true;

                        self.checkOrderCountStep();
                        self.checkOrderCountMin();
                    },
                    onChangeOrderCountStepEditable: function() {
                        var self = this;
                        self.checkOrderCountStep();
                    },
                    onChangeOrderCountMinEditable: function() {
                        var self = this;
                        self.checkOrderCountMin();
                    },

                    onChangeStockUnit: function() {
                        var self = this;
                        self.checkUnits();
                    },
                    onChangeBaseUnit: function() {
                        var self = this;
                        self.checkUnits();
                    },
                    onInputCountDenominator: function() {
                        var self = this;

                        self.checkCountDenominator();
                        self.checkOrderCountStep();
                        self.checkOrderCountMin();
                    },
                    onChangeCountDenominator: function() {
                        var self = this;

                        self.fractional.order_multiplicity_factor.value = "" + self.validate('float', self.fractional.order_multiplicity_factor.value);
                        self.onInputCountDenominator();
                    },
                    onChangeStockBaseRatio: function() {
                        var self = this;

                        self.fractional.stock_base_ratio.value = "" + self.validate('float', self.fractional.stock_base_ratio.value);
                        self.checkStockBaseRatio();
                    },
                    onChangeOrderCountStepValue: function() {
                        var self = this;

                        self.fractional.order_count_step.value = "" + self.validate('float', self.fractional.order_count_step.value);
                        self.checkOrderCountStep();
                    },
                    onChangeOrderCountMinValue: function() {
                        var self = this;

                        self.fractional.order_count_min.value = "" + self.validate('float', self.fractional.order_count_min.value);
                        self.checkOrderCountMin();
                    },

                    checkUnits: function() {
                        var self = this,
                            result = null;

                        var error_id = "units_error";

                        if (self.fractional.stock_unit.status && self.fractional.stock_unit.enabled && self.fractional.base_unit.status && self.fractional.base_unit.enabled) {
                            var case_1 = (self.fractional.stock_unit.value && self.fractional.base_unit.value && self.fractional.stock_unit.value === self.fractional.base_unit.value);
                            if (case_1) {
                                self.$set(self.errors, error_id, { id: error_id, text: that.locales["units_unique_error"] });
                                result = "units_error";
                            } else {
                                self.$delete(self.errors, error_id);
                            }
                        } else {
                            self.$delete(self.errors, error_id);
                        }

                        return result;
                    },
                    checkStockBaseRatio: function() {
                        var self = this,
                            result = null;

                        var error_id = "stock_base_ratio_error";

                        // VALIDATE
                        self.fractional.stock_base_ratio.value =  self.validate("number", self.fractional.stock_base_ratio.value);

                        if (self.fractional.base_unit.status && self.fractional.base_unit.enabled && self.fractional.stock_unit.value && self.fractional.base_unit.value) {
                            var case_1 = (!self.fractional.stock_base_ratio.value || !(parseFloat(self.fractional.stock_base_ratio.value) > 0));
                                //case_2 = (!self.fractional.stock_base_ratio.value && !self.fractional.base_unit.editable);

                            if (case_1) {
                                self.$set(self.errors, error_id, { id: error_id, text: that.locales["stock_base_ratio_required"] });
                                result = "stock_base_ratio_error";
                            } else {
                                self.$delete(self.errors, error_id);
                            }
                        } else {
                            self.$delete(self.errors, error_id);
                        }

                        return result;
                    },
                    checkCountDenominator: function() {
                        var self = this,
                            result = null;

                        var error_id = "order_multiplicity_factor_error";

                        // VALIDATE
                        self.fractional.order_multiplicity_factor.value =  self.validate("number", self.fractional.order_multiplicity_factor.value);

                        if (self.fractional.order_multiplicity_factor.status && self.fractional.order_multiplicity_factor.enabled) {
                            var case_1 = (!self.fractional.order_multiplicity_factor.value || !checkValue(self.fractional.order_multiplicity_factor.value));
                            if (case_1) {
                                self.$set(self.errors, error_id, { id: error_id, text: that.locales[error_id] });
                                result = error_id;
                            } else {
                                self.$delete(self.errors, error_id);
                            }
                        } else {
                            self.$delete(self.errors, error_id);
                        }

                        return result;

                        function checkValue(value_string) {
                            var result = false,
                                value = parseFloat(value_string),
                                tail_length = (value_string.indexOf(".") >= 0 ? (value_string.length - (value_string.indexOf(".") + 1)) : 0);

                            if (value > 0) {
                                var min = 0.001,
                                    max = 999999.999,
                                    max_tail_length = 3;

                                result = (tail_length <= max_tail_length && value >= min && value <= max);
                            }

                            return result;
                        }
                    },
                    checkOrderCountStep: function() {
                        var self = this,
                            result = null;

                        var error_id = "order_count_step_error";

                        // VALIDATE
                        self.fractional.order_count_step.value =  self.validate("number", self.fractional.order_count_step.value);

                        if (self.fractional.order_count_step.status && self.fractional.order_count_step.enabled && self.fractional.order_multiplicity_factor.value) {
                            var case_1 = (!self.fractional.order_count_step.value || !self.checkValue(self.fractional.order_count_step.value));
                            if (case_1) {
                                self.$set(self.errors, error_id, { id: error_id, text: that.locales["order_count_step_error"] });
                                result = "order_count_step_error";
                            } else {
                                self.$delete(self.errors, error_id);
                            }
                        } else {
                            self.$delete(self.errors, error_id);
                        }

                        return result;
                    },
                    checkOrderCountMin: function() {
                        var self = this,
                            result = null;

                        var error_id = "order_count_min_error";

                        // VALIDATE
                        self.fractional.order_count_min.value =  self.validate("number", self.fractional.order_count_min.value);

                        if (self.fractional.order_count_min.status && self.fractional.order_count_min.enabled && self.fractional.order_multiplicity_factor.value) {
                            var case_1 = (!self.fractional.order_count_min.value || !self.checkValue(self.fractional.order_count_min.value));
                            if (case_1) {
                                self.$set(self.errors, error_id, { id: error_id, text: that.locales["order_count_min_error"] });
                                result = "order_count_min_error";
                            } else {
                                self.$delete(self.errors, error_id);
                            }
                        } else {
                            self.$delete(self.errors, error_id);
                        }

                        return result;
                    },
                    checkValue: function(value) {
                        var self = this;

                        value = parseFloat(value);

                        var result = false,
                            divider = getDivider();

                        if (value > 0 && divider > 0) {
                            var x1 = parseFloat((value/divider).toFixed(3)),
                                tail = value - parseFloat((parseInt(x1) * divider).toFixed(3));
                            result = (tail === 0);
                        }

                        return result;

                        function getDivider() {
                            return (self.fractional.order_multiplicity_factor.value ? self.fractional.order_multiplicity_factor.value : null);
                        }
                    },

                    /**
                     * @param {String} type
                     * @param {Number|String} value
                     * @return value
                     * */
                    validate: function(type, value) {
                        value = (typeof value === "string" ? value : "" + value);

                        var result = value;

                        switch (type) {
                            case "float":
                                var float_value = parseFloat(value);
                                if (float_value >= 0) {
                                    result = float_value.toFixed(3) * 1;
                                }
                                break;

                            case "number":
                                var white_list = ["0", "1", "2", "3", "4", "5", "6", "7", "8", "9", ".", ","],
                                    letters_array = [],
                                    divider_exist = false;

                                $.each(value.split(""), function(i, letter) {
                                    letter = (letter !== "," ? letter : ".");

                                    if (letter === "." || letter === ",") {
                                        if (!divider_exist) {
                                            divider_exist = true;
                                            letters_array.push(letter);
                                        }
                                    } else {
                                        if (white_list.indexOf(letter) >= 0) {
                                            letters_array.push(letter);
                                        }
                                    }
                                });

                                result = letters_array.join("");
                                break;

                            case "integer":
                                var white_list = ["0", "1", "2", "3", "4", "5", "6", "7", "8", "9"],
                                    letters_array = [];

                                $.each(value.split(""), function(i, letter) {
                                    if (white_list.indexOf(letter) >= 0) {
                                        letters_array.push(letter);
                                    }
                                });

                                result = letters_array.join("");
                                break;

                            default:
                                break;
                        }

                        return result;
                    }
                },
                delimiters: ['{ { ', ' } }'],
                mounted: function() {
                    var self = this,
                        $section = $(self.$el);

                    if (self.fractional.stock_unit.enabled && self.fractional.stock_unit.value && self.fractional.stock_unit.value !== "0") {
                        that.$wrapper.find('[name="stock_unit_id"] > option[value="0"]').hide();
                    }
                    if (self.fractional.base_unit.enabled && self.fractional.base_unit.value && self.fractional.base_unit.value !== "0") {
                        that.$wrapper.find('[name="base_unit_id"] > option[value="0"]').hide();
                    }

                    that.validate(self);
                }
            });
        }

        Dialog.prototype.initFrontList = function() {
            var that = this;

            var active_class = "is-active";

            // storefront group field
            var $active_storefront_group = null;
            var $active_storefront_group_field = that.$wrapper.find(".js-storefront-group-field:checked");
            if ($active_storefront_group_field.length) {
                $active_storefront_group = $active_storefront_group_field.closest(".s-radio-wrapper");
            }
            that.$wrapper.on("change", ".js-storefront-group-field", function() {
                var $group = $(this).closest(".s-radio-wrapper");
                if ($active_storefront_group) {
                    $active_storefront_group.removeClass(active_class);
                }
                if ($(this).val() === 'all') {
                    that.$wrapper.find(".js-storefront-field").attr("checked", ":checked");
                }
                $active_storefront_group = $group.toggleClass(active_class);
                that.dialog.resize();
            });

            initCheckAllTypes();

            function initCheckAllTypes() {
                var $all_storefronts_field = that.$wrapper.find(".js-all-storefronts-field"),
                    $storefront_fields = that.$wrapper.find(".js-storefront-field");

                var checked_storefronts = getCheckedTypesCount(),
                    storefronts_count = $storefront_fields.length;

                $all_storefronts_field.on("change", function() {
                    var $input = $(this),
                        is_active = $input.is(":checked");

                    checked_storefronts = (is_active ? storefronts_count : 0);
                    $storefront_fields.attr("checked", is_active);
                });

                that.$wrapper.on("change", ".js-storefront-field", function() {
                    var $input = $(this),
                        is_active = $input.is(":checked");

                    checked_storefronts += (is_active ? 1 : -1);
                    $all_storefronts_field.attr("checked", (checked_storefronts >= storefronts_count));
                });

                function getCheckedTypesCount() {
                    var result = 0;

                    $storefront_fields.each( function() {
                        var $input = $(this),
                            is_active = $input.is(":checked");
                        if (is_active) { result += 1; }
                    });

                    return result;
                }
            }
        };

        Dialog.prototype.initToggle = function() {
            var that = this,
                $section = that.$wrapper.find(".js-type-toggle-section");

            if (!$section.length) { return false; }

            $section.on("change", ".s-field", function() {
                toggleContent($(this).val());
            });

            function toggleContent(active_content_id) {
                that.$wrapper.find(".s-fields-wrapper .field-group").each( function() {
                    var $content = $(this),
                        content_id = $content.data("content-id");

                    if (active_content_id === content_id) {
                        $content.show();
                    } else {
                        $content.hide();
                    }
                });

                that.dialog.resize();
            }
        };

        Dialog.prototype.initSubmit = function() {
            var that = this,
                $form = that.$wrapper.find("form:first"),
                $submit_button = $form.find(".js-submit-button"),
                $errorsPlace = that.$wrapper.find(".js-errors-place"),
                is_locked = false;

            that.$wrapper.one("change", function() {
                $submit_button.removeClass("green").addClass("yellow");
            });

            $form.on("submit", function(event) {
                event.preventDefault();

                var confirm_values = that.checkConfirmValues(),
                    vue_errors = that.validate();

                if (vue_errors.length) {
                    console.log( vue_errors );
                } else if (confirm_values) {
                    that.showConfirmFractionalDialog(confirm_values);
                } else {
                    onSubmit();
                }
            });

            function onSubmit() {
                var formData = getData();

                if (formData.errors.length) {
                    showErrors(formData.errors);
                } else {
                    request(formData.data);
                }
            }

            function getData() {
                var result = {
                        data: [],
                        errors: []
                    },
                    data = $form.serializeArray();

                $.each(data, function(index, item) {
                    result.data.push(item);
                });

                return result;
            }

            function showErrors(errors) {
                var error_class = "error";

                if (!errors || !errors[0]) {
                    errors = [];
                }

                $.each(errors, function(index, item) {
                    var name = item.name,
                        text = item.value;

                    var $field = that.$wrapper.find("[name=\"" + name + "\"]"),
                        $text = $("<span class='s-error' />").addClass("errormsg").text(text);

                    if ($field.length && !$field.hasClass(error_class)) {
                        $field.parent().append($text);

                        $field
                            .addClass(error_class)
                            .one("focus click change", function() {
                                $field.removeClass(error_class);
                                $text.remove();
                            });
                    } else {
                        $errorsPlace.append($text);

                        $form.one("submit", function() {
                            $text.remove();
                        });
                    }
                });
            }

            function request(data) {
                if (!is_locked) {
                    is_locked = true;
                    $submit_button.attr("disabled", true).append("<i class=\"icon16 loading\" />");

                    var href = that.urls["type_save"];

                    $.post(href, data, "json")
                        .always( function() {
                            is_locked = false;
                            $submit_button.attr("disabled", false).find(".loading").remove();
                        })
                        .done( function(response) {
                            if (response.status === "ok") {
                                if (response.data && response.data.id) {
                                    $submit_button.attr("disabled", true).append("<i class=\"icon16 loading\" />");

                                    $.settings.forceHash("/typefeat/" + response.data.id + "/");

                                    that.scope.scope.reload({
                                        type_id: response.data.id
                                    }).fail( function() {
                                        $submit_button.attr("disabled", false).find(".loading").remove();
                                    }).done( function() {
                                        that.onSuccess(response.data.id);
                                        that.dialog.close();
                                    });

                                } else {
                                    alert($_('Product type adding error.'));
                                }

                            } else {
                                showErrors(response.errors);
                            }
                        })
                        .fail( function() {

                        });
                }
            }
        };

        Dialog.prototype.validate = function(vue_model) {
            var that = this;

            var errors = [];

            if (!that.is_premium) { return errors; }

            vue_model = (typeof vue_model !== "undefined" ? vue_model : that.vue_model);
            if (vue_model) {
                var unit_error = vue_model.checkUnits();
                if (unit_error) { errors.push(unit_error); }

                var ratio_error = vue_model.checkStockBaseRatio();
                if (ratio_error) { errors.push(ratio_error); }

                var count_denominator_error = vue_model.checkCountDenominator();
                if (count_denominator_error) { errors.push(count_denominator_error); }

                var step_error = vue_model.checkOrderCountStep();
                if (step_error) { errors.push(step_error); }

                var min_error = vue_model.checkOrderCountMin();
                if (min_error) { errors.push(min_error); }
            }

            return errors;
        };

        Dialog.prototype.checkConfirmValues = function() {
            var that = this,
                result = null,
                enabled = {},
                enabled_locked = 0,
                disabled = {};

            if (!that.is_premium) { return result; }

            var case_1, case_2;

            if (that.type.count) {
                if (that.fractional.stock_unit.enabled !== that.fractional.stock_unit_before.enabled) {
                    if (that.fractional.stock_unit.enabled) {
                        enabled.stock_unit = true;
                        if (!that.fractional.stock_unit.editable) { enabled_locked += 1; }
                    } else {
                        disabled.stock_unit = true;
                    }
                // Если неразрешено менять в товаре
                } else if (!that.fractional.stock_unit.editable) {
                    case_1 = (that.fractional.stock_unit.editable !== that.fractional.stock_unit_before.editable);
                    case_2 = (that.fractional.stock_unit.value !== that.fractional.stock_unit_before.value);

                    if (case_1 || case_2) { enabled.stock_unit = true; }
                }

                if (that.fractional.base_unit.enabled !== that.fractional.base_unit_before.enabled) {
                    if (that.fractional.base_unit.enabled) {
                        enabled.base_unit = true;
                        enabled.stock_base_ratio = true;
                        if (!that.fractional.base_unit.editable) { enabled_locked += 1; }
                        if (!that.fractional.stock_base_ratio.editable) { enabled_locked += 1; }
                    } else {
                        disabled.base_unit = true;
                        disabled.stock_base_ratio = true;
                    }
                // Если неразрешено менять в товаре
                } else if (!that.fractional.base_unit.editable) {
                    case_1 = (that.fractional.base_unit.editable !== that.fractional.base_unit_before.editable);
                    case_2 = (that.fractional.base_unit.value !== that.fractional.base_unit_before.value);

                    if (case_1 || case_2) { enabled.base_unit = true; }
                }

                if (that.fractional.order_multiplicity_factor.enabled !== that.fractional.order_multiplicity_factor_before.enabled) {
                    if (that.fractional.order_multiplicity_factor.enabled) {
                        enabled.order_multiplicity_factor = true;
                        if (!that.fractional.order_multiplicity_factor.editable) { enabled_locked += 1; }
                    } else {
                        disabled.order_multiplicity_factor = true;
                    }
                // Если неразрешено менять в товаре
                } else if (!that.fractional.order_multiplicity_factor.editable) {
                    case_1 = (that.fractional.order_multiplicity_factor.editable !== that.fractional.order_multiplicity_factor_before.editable);
                    case_2 = (that.fractional.order_multiplicity_factor.value !== that.fractional.order_multiplicity_factor_before.value);

                    if (case_1 || case_2) {
                        enabled.order_multiplicity_factor = true;
                        if (that.fractional.order_count_step.enabled && that.fractional.order_count_step.editable) { enabled.order_count_step = true; }
                        if (that.fractional.order_count_min.enabled && that.fractional.order_count_min.editable) { enabled.order_count_min = true; }
                    }
                }

                if (that.fractional.order_count_step.enabled !== that.fractional.order_count_step_before.enabled) {
                    if (that.fractional.order_count_step.enabled) {
                        enabled.order_count_step = true;
                        if (!that.fractional.order_count_step.editable) { enabled_locked += 1; }
                    } else {
                        disabled.order_count_step = true;
                    }
                // Если неразрешено менять в товаре
                } else if (!that.fractional.order_count_step.editable) {
                    case_1 = (that.fractional.order_count_step.editable !== that.fractional.order_count_step_before.editable);
                    case_2 = (that.fractional.order_count_step.value !== that.fractional.order_count_step_before.value);

                    if (case_1 || case_2) { enabled.order_count_step = true; }
                }

                if (that.fractional.order_count_min.enabled !== that.fractional.order_count_min_before.enabled) {
                    if (that.fractional.order_count_min.enabled) {
                        enabled.order_count_min = true;
                        if (!that.fractional.order_count_min.editable) { enabled_locked += 1; }
                    } else {
                        disabled.order_count_min = true;
                    }
                // Если неразрешено менять в товаре
                } else if (!that.fractional.order_count_min.editable) {
                    case_1 = (that.fractional.order_count_min.editable !== that.fractional.order_count_min_before.editable);
                    case_2 = (that.fractional.order_count_min.value !== that.fractional.order_count_min_before.value);

                    if (case_1 || case_2) { enabled.order_count_min = true; }
                }
            }

            // Исключаем кейс когда поле есть и там и там
            $.each(disabled, function(key, value) {
                if (enabled[key]) { delete enabled[key]; }
            });

            if (Object.keys(enabled).length > 0 || Object.keys(disabled).length > 0) {
                result = {
                    enabled: (Object.keys(enabled).length > 0 && Object.keys(enabled).length !== enabled_locked ? enabled : null),
                    disabled: (Object.keys(disabled).length > 0 ? disabled : null)
                };
            }

            if (result && !result.enabled && !result.disabled) { result = null; }

            return result;
        };

        Dialog.prototype.showConfirmFractionalDialog = function(confirm_values) {
            var that = this,
                deferred = $.Deferred();

            var dialog = $.waDialog({
                html: that.templates["dialog-confirm-fractional"],
                options: {
                    confirm_values: confirm_values,

                    initDialog: function(options) {
                        options["scope"] = that;
                        return new TypeEditConfirmDialog(options);
                    },
                    onSubmit: function() {
                        deferred.resolve();
                        that.dialog.close();
                    },
                    onCancel: function () {
                        deferred.reject("cancel");
                    },
                    onClose: function () {
                        that.dialog.close();
                    }
                },
                onOpen: function() {
                    that.dialog.hide();
                },
                onClose: function() {
                    that.dialog.show();
                }
            });

            return deferred.promise();
        };

        return Dialog;

    })($);

    /**
     * @used at wa-apps/shop/templates/actions/settings/SettingsTypefeatTypeEdit.html
     * */
    var TypeEditConfirmDialog = ( function($) {

        var Dialog = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$form = that.$wrapper.find("form:first");

            // CONST
            that.scope = options["scope"];
            that.dialog = options["dialog"];
            that.locales = that.scope.locales;
            that.fractional = that.scope.fractional;

            // DYNAMIC VARS
            that.errors = {};

            // INIT
            that.vue_model = that.initVue();
            that.init();

            console.log( that );
        };

        Dialog.prototype.init = function() {
            var that = this;

            that.initSubmit();
        };

        Dialog.prototype.initVue = function() {
            var that= this;

            var $section = that.$wrapper.find(".js-vue-section");
            if (!$section.length) { return false; }

            return new Vue({
                el: $section[0],
                data: {
                    fractional: getFractional(that.dialog.options.confirm_values),
                    confirm_values: that.dialog.options.confirm_values,
                    errors: that.errors
                },
                components: {
                },
                computed: {
                    selected_stock_unit: function () {
                        var self = this,
                            result = null;

                        if (self.fractional.stock_unit.migrate) {
                            var unit_search = self.fractional.units.filter( function(unit) {
                                return (unit.value === self.fractional.stock_unit.migrate);
                            });

                            if (unit_search.length) {
                                result = unit_search[0].name_short;
                            }
                        }

                        return result;
                    },
                    selected_base_unit: function () {
                        var self = this,
                            result = null;

                        if (self.fractional.base_unit.migrate) {
                            var unit_search = self.fractional.units.filter( function(unit) {
                                return (unit.value === self.fractional.base_unit.migrate);
                            });

                            if (unit_search.length) {
                                result = unit_search[0].name_short;
                            }
                        }

                        return result;
                    }
                },
                methods: {
                    onChangeStockUnit: function() {
                        var self = this;
                        self.checkUnits();
                    },
                    onChangeBaseUnit: function() {
                        var self = this;
                        self.checkUnits();
                    },
                    onInputCountDenominator: function() {
                        var self = this;

                        self.checkCountDenominator();
                        self.checkOrderCountStep();
                        self.checkOrderCountMin();
                    },
                    onChangeCountDenominator: function() {
                        var self = this;

                        self.fractional.order_multiplicity_factor.migrate = "" + self.validate('float', self.fractional.order_multiplicity_factor.migrate);
                        self.onInputCountDenominator();
                    },
                    onChangeStockBaseRatio: function() {
                        var self = this;

                        self.fractional.stock_base_ratio.migrate = "" + self.validate('float', self.fractional.stock_base_ratio.migrate);
                        self.checkStockBaseRatio();
                    },
                    onChangeOrderCountStep: function() {
                        var self = this;

                        self.fractional.order_count_step.migrate = "" + self.validate('float', self.fractional.order_count_step.migrate);
                        self.checkOrderCountStep();
                    },
                    onChangeOrderCountMin: function() {
                        var self = this;

                        self.fractional.order_count_min.migrate = "" + self.validate('float', self.fractional.order_count_min.migrate);
                        self.checkOrderCountMin();
                    },

                    checkUnits: function() {
                        var self = this,
                            result = null;

                        var error_id = "units_error";

                        if (self.fractional.stock_unit.status && self.fractional.stock_unit.enabled && self.fractional.base_unit.status && self.fractional.base_unit.enabled) {
                            var case_1 = (self.fractional.stock_unit.migrate && self.fractional.base_unit.migrate && self.fractional.stock_unit.migrate === self.fractional.base_unit.migrate);
                            if (case_1) {
                                self.$set(self.errors, error_id, { id: error_id, text: that.locales["units_unique_error"] });
                                result = "units_error";
                            } else {
                                self.$delete(self.errors, error_id);
                            }
                        } else {
                            self.$delete(self.errors, error_id);
                        }

                        return result;
                    },
                    checkStockBaseRatio: function(event) {
                        var self = this,
                            result = null;

                        var error_id = "stock_base_ratio_required";

                        // VALIDATE
                        self.fractional.stock_base_ratio.migrate =  self.validate("number", self.fractional.stock_base_ratio.migrate);

                        var case_1 = (!self.fractional.stock_base_ratio.migrate || !(parseFloat(self.fractional.stock_base_ratio.migrate) > 0));
                        if (case_1) {
                            self.$set(self.errors, error_id, { id: error_id, text: that.locales["stock_base_ratio_required"] });
                            result = "stock_base_ratio_required";
                        } else {
                            self.$delete(self.errors, error_id);
                        }

                        return result;
                    },
                    checkCountDenominator: function() {
                        var self = this,
                            result = null;

                        var error_id = "order_multiplicity_factor_error";

                        // VALIDATE
                        self.fractional.order_multiplicity_factor.migrate =  self.validate("number", self.fractional.order_multiplicity_factor.migrate);

                        if (self.fractional.order_multiplicity_factor.status && self.fractional.order_multiplicity_factor.enabled) {
                            var case_1 = (!self.fractional.order_multiplicity_factor.migrate || !checkValue(self.fractional.order_multiplicity_factor.migrate));
                            if (case_1) {
                                self.$set(self.errors, error_id, { id: error_id, text: that.locales[error_id] });
                                result = error_id;
                            } else {
                                self.$delete(self.errors, error_id);
                            }
                        } else {
                            self.$delete(self.errors, error_id);
                        }

                        return result;

                        function checkValue(value_string) {
                            var result = false,
                                value = parseFloat(value_string),
                                tail_length = (value_string.indexOf(".") >= 0 ? (value_string.length - (value_string.indexOf(".") + 1)) : 0);

                            if (value > 0) {
                                var min = 0.001,
                                    max = 999999.999,
                                    max_tail_length = 3;

                                result = (tail_length <= max_tail_length && value >= min && value <= max);
                            }

                            return result;
                        }
                    },
                    checkOrderCountStep: function(event) {
                        var self = this,
                            result = null;

                        var error_id = "order_count_step_error";

                        // VALIDATE
                        self.fractional.order_count_step.migrate =  self.validate("number", self.fractional.order_count_step.migrate);

                        var case_1 = (!self.fractional.order_count_step.migrate || !self.checkValue(self.fractional.order_count_step.migrate));
                        if (self.fractional.order_count_step.enabled && case_1) {
                            self.$set(self.errors, error_id, { id: error_id, text: that.locales["order_count_step_error"] });
                            result = "order_count_step_error";
                        } else {
                            self.$delete(self.errors, error_id);
                        }

                        return result;
                    },
                    checkOrderCountMin: function() {
                        var self = this,
                            result = null;

                        var error_id = "order_count_min_error";

                        // VALIDATE
                        self.fractional.order_count_min.migrate =  self.validate("number", self.fractional.order_count_min.migrate);

                        var case_1 = (!self.fractional.order_count_min.migrate || !self.checkValue(self.fractional.order_count_min.migrate));
                        if (self.fractional.order_count_min.enabled && case_1) {
                            self.$set(self.errors, error_id, { id: error_id, text: that.locales["order_count_min_error"] });
                            result = "order_count_min_error";
                        } else {
                            self.$delete(self.errors, error_id);
                        }

                        return result;
                    },
                    checkValue: function(value) {
                        var self = this;

                        value = parseFloat(value);

                        var result = false,
                            divider = getDivider();

                        if (value > 0 && divider > 0) {
                            var x1 = parseFloat((value/divider).toFixed(3)),
                                tail = value - parseFloat((parseInt(x1) * divider).toFixed(3));
                            result = (tail === 0);
                        }

                        return result;

                        function getDivider() {
                            return (self.fractional.order_multiplicity_factor.migrate ? self.fractional.order_multiplicity_factor.migrate : null);
                        }
                    },

                    validate: that.scope.vue_model.validate
                },
                delimiters: ['{ { ', ' } }'],
                mounted: function() {
                    var self = this,
                        $section = $(self.$el);

                    that.validate(self);
                }
            });

            function getFractional(confirm_values) {
                var fractional = clone(that.fractional);

                fractional.stock_unit.migrate = (fractional.stock_unit.value ? fractional.stock_unit.value : fractional.stock_unit.default);
                fractional.base_unit.migrate = (fractional.base_unit.value ? fractional.base_unit.value : fractional.base_unit.default);
                fractional.order_multiplicity_factor.migrate = (fractional.order_multiplicity_factor.value ? fractional.order_multiplicity_factor.value : fractional.order_multiplicity_factor.default);
                fractional.stock_base_ratio.migrate = (fractional.stock_base_ratio.value ? fractional.stock_base_ratio.value : fractional.stock_base_ratio.default);
                fractional.order_count_min.migrate = (fractional.order_count_min.value ? fractional.order_count_min.value : fractional.order_count_min.default);
                fractional.order_count_step.migrate = (fractional.order_count_step.value ? fractional.order_count_step.value : fractional.order_count_step.default);

                return fractional;
            }
        }

        Dialog.prototype.initSubmit = function() {
            var that = this,
                $submit_button = that.$wrapper.find(".js-submit-button"),
                is_locked = false;

            that.$wrapper.on("click", ".js-close-button", function(event) {
                event.preventDefault();
                that.dialog.close();
                that.dialog.options.onClose();
            });

            that.$wrapper.on("click", ".js-cancel-button", function(event) {
                event.preventDefault();
                that.dialog.close();
                that.dialog.options.onCancel();
            });

            that.$form.on("submit", function(event) {
                event.preventDefault();

                if (!is_locked) {
                    var vue_errors = that.validate();
                    if (vue_errors.length) {
                        console.log( vue_errors );
                        return false;
                    }

                    is_locked = true;
                    $submit_button.attr("disabled", true).append("<i class=\"icon16 loading\" />");

                    onSubmit()
                        .always(function () {
                            is_locked = false;
                            $submit_button.attr("disabled", false).find(".loading").remove();
                        })
                        .done( function(data) {
                            that.dialog.close();
                            that.dialog.options.onSubmit(data);
                        });
                }
            });

            function onSubmit() {
                var root_dialog_data = that.scope.$wrapper.find("form:first").serializeArray(),
                    dialog_data = that.$form.serializeArray(),
                    data = [].concat(root_dialog_data, dialog_data);

                return request(data);
            }

            function request(data) {
                var deferred = $.Deferred();

                $.post(that.scope.urls["type_save"], data, "json")
                    .done( function(response) {
                        if (response.status === "ok") {
                            if (response.data && response.data.id) {
                                $.settings.forceHash("/typefeat/" + response.data.id + "/");

                                that.scope.scope.scope.reload({
                                    type_id: response.data.id
                                }).fail( function() {
                                    deferred.reject();
                                    alert("ERROR: #4. FAIL");
                                }).done( function() {
                                    deferred.resolve(response.data);
                                });

                            } else {
                                deferred.reject();
                                alert("ERROR: #1. Product type adding error.");
                            }
                        } else {
                            deferred.reject();
                            alert("ERROR: #2. ERRORS");
                            console.log("ERRORS:", response.errors);
                        }
                    })
                    .fail( function() {
                        deferred.reject();
                        alert("ERROR: #3. FAIL");
                    });

                return deferred.promise();
            }
        };

        Dialog.prototype.validate = function(vue_model) {
            var that = this;

            vue_model = (typeof vue_model !== "undefined" ? vue_model : that.vue_model);

            var errors = [];

            if (vue_model) {
                var unit_error = vue_model.checkUnits();
                if (unit_error) { errors.push(unit_error); }

                var ratio_error = vue_model.checkStockBaseRatio();
                if (ratio_error) { errors.push(ratio_error); }

                var count_denominator_error = vue_model.checkCountDenominator();
                if (count_denominator_error) { errors.push(count_denominator_error); }

                var step_error = vue_model.checkOrderCountStep();
                if (step_error) { errors.push(step_error); }

                var min_error = vue_model.checkOrderCountMin();
                if (min_error) { errors.push(min_error); }
            }

            return errors;
        };

        return Dialog;

    })($);

    /**
     * @used at wa-apps/shop/templates/actions/settings/SettingsTypefeatTypeDeleteDialog.html
     * */
    var TypeDeleteDialog = ( function($) {

        var Dialog = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];

            // CONST
            that.scope = options["scope"];
            that.urls = that.scope["urls"];
            that.dialog = options["dialog"];
            that.type_id = options["type_id"];

            // DYNAMIC VARS

            // INIT
            that.init();
        };

        Dialog.prototype.init = function() {
            var that = this,
                $submit_button = that.$wrapper.find(".js-submit-button"),
                is_locked = false;

            $submit_button.on("click", function(event) {
                event.preventDefault();

                if (!is_locked) {
                    is_locked = true;
                    $submit_button.attr("disabled", true).append("<i class=\"icon16 loading\" />");

                    var href = that.urls["type_delete_controller"],
                        data = {
                            id: that.type_id
                        };

                    $.post(href, data, "json")
                        .always( function() {
                            is_locked = false;
                            $submit_button.attr("disabled", false).find(".loading").remove();
                        })
                        .done( function(response) {
                            if (response.status === "ok") {
                                $.settings.forceHash("/typefeat/");

                                $submit_button.attr("disabled", true).append("<i class=\"icon16 loading\" />");

                                that.scope.scope.reload()
                                    .fail( function() {
                                        $submit_button.attr("disabled", false).find(".loading").remove();
                                    }).done( function() {
                                        that.dialog.close();
                                    });

                            } else {
                                alert("Delete type Error");
                            }
                        });
                }
            });
        };

        return Dialog;

    })($);

    /**
     * @used at wa-apps/shop/templates/actions/settings/SettingsTypefeatFeatureAddExisting.html
     * */
    var ExistingFeatureAddDialog = ( function($) {

        var Dialog = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$form = that.$wrapper.find("form:first");

            // CONST
            that.dialog = options["dialog"];
            that.scope = options["scope"];
            that.locales = options["locales"];
            that.templates = options["templates"];
            that.urls = options["urls"];
            that.features = formatFeaturesData(options["features"]);
            that.type_id = that.scope.type_id;

            // DYNAMIC VARS
            that.added_features = {};

            // INIT
            that.init();

            function formatFeaturesData(features) {
                $.each(features, function(i, feature) {
                    feature.lowername = (feature.name||'').toLowerCase();
                    feature.lowercode = (feature.code||'').toLowerCase();
                });

                return features;
            }
        };

        Dialog.prototype.init = function() {
            var that = this;

            that.initAutocomplete();
            that.initSubmit();

            setTimeout( function() {
                that.$wrapper.find(".js-feature-autocomplete-input:first").trigger("focus");
            }, 100);
        };

        Dialog.prototype.initAutocomplete = function() {
            var that = this,
                $features_list = that.$wrapper.find(".js-features-list");

            // DYNAMIC VARS
            var $empty_message = null,
                content_is_visible = false;

            var $input = that.$wrapper.find(".js-feature-autocomplete-input:first");

            $input.autocomplete({
                source: function(request, response) {
                    var str = request.term.toLowerCase();
                    var result = [];

                    $.each(that.features, function(i, feature) {
                        var found = feature.lowername.indexOf(str) >= 0;
                        found = found || feature.lowercode.indexOf(str) >= 0;
                        if (!found) {
                            return true;
                        }

                        if (!feature.value) {
                            feature.value = feature.id;
                            feature.label = "<span class='nowrap'>" + escape(feature.name) + " (" + escape(feature.code) + ")" + "</span>";
                        }

                        if (feature.enabled) {
                            feature.label = "<span class='nowrap'>" + escape(feature.name) + " (" + escape(feature.code) + ") <span class=\"hint\">" + that.locales["already_exist"] + "</span></span>";

                        } else if (that.added_features[feature.id]) {
                            feature.label = "<span class='nowrap'>" + escape(feature.name) + " (" + escape(feature.code) + ") <span class=\"hint\">" + that.locales["already_added"] + "</span></span>";
                        }

                        result.push(feature);

                        if (result.length >= 10) {
                            return false;
                        }
                    });

                    if (!result.length) {
                        toggleSearchMessage(true);
                    }

                    response(result);
                },
                select: function(event, ui) {
                    if (that.added_features[ui.item.id]) {
                        var $added_feature = $features_list.find(".s-feature-wrapper[data-feature-id=\"" + ui.item.id + "\"]"),
                            active_class = "highlighted";

                        $added_feature.addClass(active_class);
                        setTimeout( function() {
                            $added_feature.removeClass(active_class);
                        }, 1000);

                    } else {
                        that.added_features[ui.item.id] = renderFeature(ui.item);
                    }
                    $input.val("");
                    return false;
                },
                focus: function() {
                    return false;
                }
            });

            $input.on("keyup", function() {
                toggleSearchMessage(false);
            });

            function renderFeature(feature) {
                var feature_html = that.templates["feature_item"]
                    .replace(/%feature_id%/g, escape(feature.id))
                    .replace("%feature_name%", escape(feature.name))
                    .replace("%feature_code%", escape(feature.code));

                var $feature = $(feature_html).appendTo($features_list);

                if (!content_is_visible) {
                    $features_list.closest(".wa-dialog-content").show();
                    content_is_visible = true;
                }

                that.dialog.resize();

                return $feature;
            }

            function toggleSearchMessage(show) {
                if (show) {
                    if (!$empty_message) {
                        $empty_message = $(that.templates["empty_search"]).appendTo($input.closest(".s-search-section"));
                    }
                } else {
                    if ($empty_message) {
                        $empty_message.remove();
                        $empty_message = null;
                    }
                }
            }
        };

        Dialog.prototype.initSubmit = function() {
            var that = this,
                $form = that.$form,
                $errorsPlace = that.$wrapper.find(".js-errors-place"),
                is_locked = false;

            var $submit_button = that.$wrapper.find(".js-submit-button");

            $form.on("submit", onSubmit);
            function onSubmit(event) {
                event.preventDefault();

                var formData = getData();

                if (formData.errors.length) {
                    renderErrors(formData.errors);
                } else {
                    request(formData.data);
                }
            }

            function getData() {
                var result = {
                        data: [],
                        errors: []
                    },
                    data = $form.serializeArray();

                $.each(data, function(index, item) {
                    result.data.push(item);
                });

                return result;
            }

            function renderErrors(errors) {
                var result = [];

                $errorsPlace.html("");

                if (!errors || !errors[0]) { errors = []; }

                $.each(errors, function(i, error) {
                    if (!error.text) { alert("error"); return true; }

                    if (error.id) {
                        switch (error.id) {
                            case "kind_value_error":
                                var $value_fields = that.$wrapper.find(".js-field-value-section .js-field-value"),
                                    field = $value_fields[error.data.index];

                                if (field) {
                                    error.$field = $(field);
                                    renderError(error, error.$field.closest(".s-fields").parent());
                                } else {
                                    renderError(error);
                                }
                                break;

                            case "some_error":
                                renderError(error);
                                break;

                            default:
                                renderError(error);
                                break;
                        }

                    } else if (error.name) {
                        error.$field = that.$wrapper.find("[name=\"" + error.name + "\"]").first();
                        renderError(error);
                    }

                    result.push(error);
                });

                that.dialog.resize();

                return result;

                function renderError(error, $error_wrapper) {
                    var $error = $("<div class=\"s-error errormsg\" />").text(error.text);
                    var error_class = "error";

                    if (error.$field) {
                        var $field = error.$field;

                        if (!$field.hasClass(error_class)) {
                            $field.addClass(error_class);

                            if ($error_wrapper) {
                                $error_wrapper.append($error);
                            } else {
                                $error.insertAfter($field);
                            }

                            $field
                                .trigger("error")
                                .on("change keyup", removeFieldError);
                        }
                    } else {
                        $errorsPlace.append($error);
                    }

                    function removeFieldError() {
                        $error.remove();
                        $field
                            .removeClass(error_class)
                            .off("change keyup", removeFieldError);
                        that.dialog.resize();
                    }
                }

            }

            function request(data) {
                if (!is_locked) {
                    is_locked = true;
                    var animation = animateUI();
                    animation.lock();

                    $.post(that.urls["submit"], data, "json")
                        .always( function() {
                            animation.unlock();
                            is_locked = false;
                        })
                        .done( function(response) {
                            if (response.status === "ok") {
                                animation.lock();
                                that.scope.scope.reload({
                                    type_id: that.scope.type_id
                                }).always( function() {
                                    animation.unlock();
                                    var feature_ids = getFeatureIds(data);
                                    that.dialog.options.onSuccess(feature_ids);
                                    that.dialog.close();
                                });
                            } else {
                                renderErrors(response.errors);
                            }
                        });
                }

                function animateUI() {
                    var $loading = $("<i class=\"icon16 loading\" />"),
                        locked_class = "is-locked",
                        is_displayed = false;

                    return {
                        lock: lock,
                        unlock: unlock
                    };

                    function lock() {
                        that.$wrapper.addClass(locked_class);
                        $submit_button.attr("disabled", true);
                        if (!is_displayed) {
                            $loading.appendTo($submit_button);
                            is_displayed = true;
                        }
                    }

                    function unlock() {
                        that.$wrapper.removeClass(locked_class);
                        $submit_button.attr("disabled", false);
                        if (is_displayed) {
                            $loading.detach();
                            is_displayed = false;
                        }
                    }
                }

                function getFeatureIds(data) {
                    var result = [];

                    $.each(data, function(i, item) {
                        if (item.name === "features[]") {
                            result.push(item.value);
                        }
                    });

                    return result;
                }
            }
        };

        return Dialog;

    })(jQuery);

    /**
     * @used at wa-apps/shop/templates/actions/settings/SettingsTypefeatFeatureDeleteDialog.html
     * */
    var FeatureDeleteDialog = ( function($) {

        var Dialog = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];

            // CONST
            that.feature_id = options["feature_id"];
            that.dialog = options["dialog"];
            that.scope = options["scope"];

            // DYNAMIC VARS

            // INIT
            that.init();
        };

        Dialog.prototype.init = function() {
            var that = this,
                is_locked = false;

            that.$wrapper.on("click", ".js-submit-button", function(event) {
                event.preventDefault();

                var $button = $(this);

                if (!is_locked) {
                    is_locked = true;
                    $button.attr("disabled", true);
                    var $loading = $("<i class=\"icon16 loading\" />").appendTo($button);

                    deleteRequest()
                        .always( function() {
                            $loading.remove();
                            $button.attr("disabled", false);
                            is_locked = false;
                        }).done( function() {
                            that.dialog.options.onSuccess();
                            that.dialog.close()
                        });
                }
            });

            function deleteRequest() {
                var href = that.scope.urls["feature_delete"],
                    data = { feature_id: that.feature_id };

                return $.post(href, data, "json");
            }
        };

        return Dialog;

    })($);

    /**
     * @used at wa-apps/shop/templates/actions/settings/SettingsTypefeatCodeEdit.html
     * */
    var CodeEditDialog = ( function($) {

        var Dialog = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$form = that.$wrapper.find("form:first");

            // CONST
            that.scope = options["scope"];
            that.urls = that.scope["urls"];
            that.dialog = options["dialog"];
            that.code_id = options["code_id"];

            // DYNAMIC VARS

            // INIT
            that.init();
        };

        Dialog.prototype.init = function() {
            var that = this;

            var active_class = "is-active";

            // visibility field
            var $visibility_field = that.$wrapper.find(".js-visibility-field");
            $visibility_field.on("change", function() {
                var $icon = $visibility_field.closest(".s-checkbox-wrapper").find(".s-icon"),
                    is_enabled = $(this).is(":checked");

                $icon
                    .removeClass(!is_enabled ? "visibility-on" : "ss visibility")
                    .addClass(is_enabled ? "visibility-on" : "ss visibility");
            });

            // available field
            var $available_field = that.$wrapper.find(".js-available-field");
            $available_field.on("change", function() {
                var $icon = $available_field.closest(".s-checkbox-wrapper").find(".s-icon"),
                    is_enabled = $(this).is(":checked");

                $icon
                    .removeClass(!is_enabled ? "hierarchical" : "hierarchical-off")
                    .addClass(is_enabled ? "hierarchical" : "hierarchical-off");
            });

            // type group field
            var $active_type_group = null;
            var $active_type_group_field = that.$wrapper.find(".js-type-group-field:checked");
            if ($active_type_group_field.length) {
                $active_type_group = $active_type_group_field.closest(".s-radio-wrapper");
            }
            that.$wrapper.on("change", ".js-type-group-field", function() {
                var $group = $(this).closest(".s-radio-wrapper");
                if ($active_type_group) {
                    $active_type_group.removeClass(active_class);
                }
                $active_type_group = $group.toggleClass(active_class);
                that.dialog.resize();
            });

            initCheckAllTypes();

            that.initTransliterate();
            that.initSubmit();

            function initCheckAllTypes() {
                var $all_types_field = that.$wrapper.find(".js-all-types-field"),
                    $type_fields = that.$wrapper.find(".js-type-field");

                var checked_types = getCheckedTypesCount(),
                    types_count = $type_fields.length;

                $all_types_field.on("change", function() {
                    var $input = $(this),
                        is_active = $input.is(":checked");

                    checked_types = (is_active ? types_count : 0);
                    $type_fields.attr("checked", is_active);
                });

                that.$wrapper.on("change", ".js-type-field", function() {
                    var $input = $(this),
                        is_active = $input.is(":checked");

                    checked_types += (is_active ? 1 : -1);
                    $all_types_field.attr("checked", (checked_types >= types_count));
                });

                function getCheckedTypesCount() {
                    var result = 0;

                    $type_fields.each( function() {
                        var $input = $(this),
                            is_active = $input.is(":checked");
                        if (is_active) { result += 1; }
                    });

                    return result;
                }
            }
        };

        Dialog.prototype.initTransliterate = function() {
            var that = this;

            var $name_field = that.$wrapper.find(".js-name-field"),
                $code_field = that.$wrapper.find(".js-code-field"),
                $loading = null;

            var use_transliterate = !$.trim($code_field.val()).length,
                keyup_timer = 0,
                time = 500,
                xhr = null;

            $code_field.on("keyup", function() {
                var value = !!($(this).val().length);
                use_transliterate = !value;
            });

            $name_field.on("keyup", function() {
                if (use_transliterate) { onKeyUp(); }
            });

            function onKeyUp() {
                var name = $.trim($name_field.val());

                if (!$loading) {
                    $loading = $("<i class=\"icon16 loading\" />").insertAfter($code_field);
                }

                getCodeName(name)
                    .always( function() {
                        if ($loading) {
                            $loading.remove();
                            $loading = null;
                        }
                    })
                    .done( function(code_name) {
                        $code_field.val(code_name).trigger("change");
                    });

                function getCodeName(name) {
                    var deferred = $.Deferred();

                    clearTimeout(keyup_timer);

                    if (!name) {
                        deferred.resolve("");

                    } else {
                        keyup_timer = setTimeout( function() {
                            if (xhr) { xhr.abort(); }

                            $.post(that.urls["transliterate"], { str: name }, "json")
                                .always( function() {
                                    xhr = null;
                                })
                                .done( function(response) {
                                    var text = ( response.data ? response.data : "");
                                    deferred.resolve(text);
                                });
                        }, time);
                    }

                    return deferred.promise();
                }
            }

            var value = $name_field.val();
            if (!value.length) {
                setTimeout( function() {
                    $name_field.trigger("focus");
                }, 100);
            }
        };

        Dialog.prototype.initSubmit = function() {
            var that = this,
                $form = that.$form,
                $errorsPlace = that.$wrapper.find(".js-errors-place"),
                is_locked = false;

            var $submit_button = that.$wrapper.find(".js-submit-button");

            that.$wrapper.one("change", function() {
                $submit_button.removeClass("green").addClass("yellow");
            });

            $form.on("submit", onSubmit);
            function onSubmit(event) {
                event.preventDefault();

                var formData = getData();

                if (formData.errors.length) {
                    renderErrors(formData.errors);
                } else {
                    request(formData.data);
                }
            }

            function getData() {
                var result = {
                        data: [],
                        errors: []
                    },
                    data = $form.serializeArray();

                $.each(data, function(index, item) {
                    result.data.push(item);
                });

                return result;
            }

            function renderErrors(errors) {
                var result = [];

                $errorsPlace.html("");

                if (!errors || !errors[0]) { errors = []; }

                $.each(errors, function(i, error) {
                    if (!error.text) { alert("error"); return true; }

                    if (error.id) {
                        switch (error.id) {
                            case "some_error":
                                renderError(error);
                                break;
                            case "some_error2":
                                renderError(error);
                                break;

                            default:
                                renderError(error);
                                break;
                        }

                    } else if (error.name) {
                        error.$field = that.$wrapper.find("[name=\"" + error.name + "\"]").first();
                        renderError(error);
                    }

                    result.push(error);
                });

                that.dialog.resize();

                return result;

                function renderError(error, $error_wrapper) {
                    var $error = $("<div class=\"s-error errormsg\" />").text(error.text);
                    var error_class = "error";

                    if (error.$field) {
                        var $field = error.$field;

                        if (!$field.hasClass(error_class)) {
                            $field.addClass(error_class);

                            if ($error_wrapper) {
                                $error_wrapper.append($error);
                            } else {
                                $error.insertAfter($field);
                            }

                            $field
                                .trigger("error")
                                .on("change keyup", removeFieldError);
                        }
                    } else {
                        $errorsPlace.append($error);
                    }

                    function removeFieldError() {
                        $error.remove();
                        $field
                            .removeClass(error_class)
                            .off("change keyup", removeFieldError);
                        that.dialog.resize();
                    }
                }

            }

            function request(data) {
                if (!is_locked) {
                    is_locked = true;
                    var animation = animateUI();
                    animation.lock();

                    $.post(that.urls["code_save"], data, "json")
                        .always( function() {
                            animation.unlock();
                            is_locked = false;
                        })
                        .done( function(response) {
                            if (response.status === "ok") {
                                animation.lock();
                                that.scope.scope.reload({
                                    type_id: that.scope.type_id
                                }).always( function() {
                                    animation.unlock();
                                    that.dialog.close();
                                });
                            } else {
                                renderErrors(response.errors);
                            }
                        });
                }

                function animateUI() {
                    var $loading = $("<i class=\"icon16 loading\" />"),
                        locked_class = "is-locked",
                        is_displayed = false;

                    return {
                        lock: lock,
                        unlock: unlock
                    };

                    function lock() {
                        that.$wrapper.addClass(locked_class);
                        $submit_button.attr("disabled", true);
                        if (!is_displayed) {
                            $loading.appendTo($submit_button);
                            is_displayed = true;
                        }
                    }

                    function unlock() {
                        that.$wrapper.removeClass(locked_class);
                        $submit_button.attr("disabled", false);
                        if (is_displayed) {
                            $loading.detach();
                            is_displayed = false;
                        }
                    }
                }
            }
        };

        return Dialog;

    })($);

    /**
     * @used at wa-apps/shop/templates/actions/settings/SettingsTypefeatCodeDeleteDialog.html
     * */
    var CodeDeleteDialog = ( function($) {

        var Dialog = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];

            // CONST
            that.code_id = options["code_id"];
            that.dialog = options["dialog"];
            that.scope = options["scope"];

            // DYNAMIC VARS

            // INIT
            that.init();
        };

        Dialog.prototype.init = function() {
            var that = this,
                is_locked = false;

            that.$wrapper.on("click", ".js-submit-button", function(event) {
                event.preventDefault();

                var $button = $(this);

                if (!is_locked) {
                    is_locked = true;
                    $button.attr("disabled", true);
                    var $loading = $("<i class=\"icon16 loading\" />").appendTo($button);

                    deleteRequest()
                        .always( function() {
                            $loading.remove();
                            $button.attr("disabled", false);
                            is_locked = false;
                        }).done( function() {
                            that.dialog.options.onSuccess();
                            that.dialog.close()
                        });
                }
            });

            function deleteRequest() {
                var href = that.scope.urls["code_delete"],
                    data = { id: that.code_id };

                return $.post(href, data, "json");
            }
        };

        return Dialog;

    })($);

    // PAGE

    var Page = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$content = that.$wrapper.find(".js-content-section");

        // CONST
        that.urls = options["urls"];
        that.type_id = options["type_id"];

        // DYNAMIC VARS
        that.sidebar = null;
        that.content = null;
        that.xhr_reload = null;

        // INIT
        that.init();
    };

    Page.prototype.init = function() {
        var that = this,
            $window = $(window);

        that.setHeight();
        that.initContent();

        that.$wrapper
            .data("controller", that)
            .data("ready")
                .resolve(that);

        $window.on("resize", resizeWatcher);
        function resizeWatcher() {
            var is_exist = $.contains(document, that.$wrapper[0]);
            if (is_exist) {
                that.setHeight();
            } else {
                $window.off("resize", resizeWatcher);
            }
        }
    };

    Page.prototype.setHeight = function() {
        var that = this;

        var wrapper_top = that.$wrapper.offset().top,
            display_h = $(window).height(),
            lift = 20;

        var height = display_h - wrapper_top - lift;

        var min_height = 800,
            indent = 20,
            sidebar_h = $(".sidebar .s-inner-sidebar").first().outerHeight();

        min_height = ( sidebar_h > min_height ? sidebar_h : min_height);

        if (height < min_height) {
            height = min_height - indent;
        }

        that.$wrapper.height(height);

        var $sidebar = that.$wrapper.find(".js-sidebar-section"),
            $content = that.$wrapper.find(".js-content-section");

        $([$sidebar, $content]).each( function(i, $wrapper) {
            var $header = $wrapper.find("> .s-section-header"),
                $body = $wrapper.find("> .s-section-body");

            var padding = $wrapper.outerHeight() - $wrapper.height();

            var body_h = height - $header.outerHeight() - padding;
            $body.height(body_h);
        });

        that.$wrapper.closest("#s-settings-content").css("margin-bottom", lift - 5 + "px");
    };

    Page.prototype.initSidebar = function(options) {
        var that = this;

        options.scope = this;
        that.sidebar = new Sidebar(options);

        return that.sidebar;
    };

    Page.prototype.initContent = function() {
        var that = this;

        that.content = new Content({
            $wrapper: that.$content,
            scope: that
        });

        return that.content;
    };

    Page.prototype.reload = function(options) {
        var that = this,
            data = {};

        options = ( options ? options : {});

        if (that.xhr_reload) {
            that.xhr_reload.abort();
        }

        if (options.type_id) {
            data.type = options.type_id;
        }

        that.xhr_reload = $.post(that.urls["reload"], data, function(html) {
            $("#s-settings-content").html(html);

            // sidebar
            var new_sidebar = window.shop_feature_settings_page.sidebar;
            new_sidebar.refresh({
                search_string: that.sidebar.search_string,
                body_scroll_top: that.sidebar.body_scroll_top
            });

            // content
            var new_content = window.shop_feature_settings_page.content;
            new_content.refresh({
                search_string: that.content.search_string,
                body_scroll_top: that.content.body_scroll_top
            });
        });

        return that.xhr_reload;
    };

    return Page;

    function escape(string) {
        return $("<div />").text(string).html();
    }

    function clone(data) {
        return JSON.parse(JSON.stringify(data));
    }

})(jQuery);
