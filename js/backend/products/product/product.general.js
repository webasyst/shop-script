( function($) {

    var Section = ( function($) {

        Section = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$form = that.$wrapper.find("form:first");

            // CONST
            that.product_id = options["product_id"];
            that.templates = options["templates"];
            that.locales = options["locales"];
            that.urls = options["urls"];
            that.lang = options["lang"];

            // DYNAMIC VARS
            that.categories_tree = options["categories_tree"];
            that.categories = formatCategories(that.categories_tree);
            that.product_category_id = options["product_category_id"];
            that.is_changed = false;
            that.is_locked = false;

            // INIT
            that.init();

            function formatCategories(categories_tree) {
                var categories = {};

                getCategories(categories_tree);

                return categories;

                function getCategories(categories_tree) {
                    $.each(categories_tree, function(i, category) {
                        categories[category.id] = category;
                        if (category.categories) {
                            getCategories(category.categories);
                        }
                    });
                }
            }
        };

        Section.prototype.init = function() {
            var that = this;

            var page_promise = that.$wrapper.closest(".s-product-page").data("ready");
            page_promise.done(  function(product_page) {
                var $footer = that.$wrapper.find(".js-sticky-footer");
                product_page.initProductDelete($footer);
                product_page.initStickyFooter($footer);
            });

            initAreaAutoHeight();

            that.initStorefrontsSection();

            that.initStatusSection();

            that.initTypeSection();

            that.initMainCategory();

            that.initAdditionalCategories();

            that.initSetsSection();

            that.initTags();

            that.initEditor();

            that.initProductSave();

            function initAreaAutoHeight() {
                that.$wrapper.find("textarea.js-auto-height").each( function() {
                    var $textarea = $(this);
                    toggleHeight($textarea);

                    $textarea.on("keyup", function(event) {
                        toggleHeight($textarea);
                    });
                });

                function toggleHeight($textarea) {
                    $textarea.css("min-height", 0);
                    var scroll_h = $textarea[0].scrollHeight;
                    $textarea.css("min-height", scroll_h + "px");
                }
            }
        };

        Section.prototype.renderErrors = function(errors) {
            errors = (typeof errors === "object" ? errors : []);

            var that = this,
                $errors_place = that.$wrapper.find(".js-errors-place"),
                $focus_message = null;

            $errors_place.html("");

            $.each(errors, function(index, item) {
                var $message = getMessage(item.text);

                // if (item.name) {
                //     var error_class = "error";
                //
                //     var $field = that.$wrapper.find("[name=\"" + item.name + "\"]");
                //     if ($field.length && !$field.hasClass(error_class)) {
                //         $field.parent().append($message);
                //
                //         $field
                //             .addClass(error_class)
                //             .one("focus click change", function () {
                //                 $field.removeClass(error_class);
                //                 $message.remove();
                //             });
                //     }
                //
                // } else {

                    $errors_place.append($message);

                    if (!$focus_message) {
                        $focus_message = $message;
                    }

                    that.$form.one("submit", function() {
                        $message.remove();
                    });
                // }
            });

            if ($focus_message) {
                $(window).scrollTop( $focus_message.offset().top - 100 );
            }

            function getMessage(message) {
                return $("<div />", {
                    "class": "wa-message error"
                }).text(message);
            }

        };

        Section.prototype.initStorefrontsSection = function() {
            var that = this;

            var $section = that.$wrapper.find(".js-storefronts-section"),
                $list = $section.find(".js-storefronts-list"),
                // fields
                $name_field = that.$wrapper.find(".js-product-name-field"),
                $url_field = $section.find(".js-product-url-field"),
                // buttons
                $refresh_button = $section.find(".js-refresh-button");

            var use_transliterate = !$.trim($url_field.val()).length,
                refresh_button_is_active = false,
                change_message_is_displayed = false,
                keyup_timer = 0,
                unique_xhr = null,
                xhr = null;

            $name_field.data("value_before", $name_field.val());

            $name_field.on("keyup", function(event) {
                var value = $name_field.val();

                var active_button = !use_transliterate;
                if ($name_field.data("value_before") === value) {
                    active_button = false;
                }

                toggleRefreshButton(active_button);
                if (use_transliterate) { transliterate(); }
            });

            var input_timer = 0;

            $url_field
                .on("keyup", function() {
                    var value = !!($url_field.val().length);
                    use_transliterate = !value;

                    clearTimeout(input_timer);
                    input_timer = setTimeout( function() {
                        var value = $.trim($url_field.val());
                        if (value.length) { checkUnique(value); }
                    }, 500);
                })
                .on("change", function() {
                    if (!change_message_is_displayed) {
                        showChangeMessage();
                    }
                });

            $refresh_button.on("click", function(event) {
                event.preventDefault();
                if (refresh_button_is_active) {
                    transliterate();
                }
                toggleRefreshButton(false);
            });

            if ($list.length) { initList($list); }

            function initList($list) {
                var $list_extended_items = $list.find(".s-extended-item");

                var is_list_extended = false;

                $list.on("click", ".js-list-toggle", function(event) {
                    event.preventDefault();

                    var $toggle = $(this),
                        show = !is_list_extended;

                    if (show) {
                        $list_extended_items.show();
                        $toggle.text(that.locales["storefronts_hide"]);
                    } else {
                        $list_extended_items.hide();
                        $toggle.text(that.locales["storefronts_show"]);
                    }

                    is_list_extended = show;
                });
            }

            function toggleRefreshButton(active) {
                var active_class = "link",
                    inactive_class = "gray";

                if (active) {
                    $refresh_button.removeClass(inactive_class);
                    $refresh_button.addClass(active_class);
                } else {
                    $refresh_button.addClass(inactive_class);
                    $refresh_button.removeClass(active_class);
                }

                refresh_button_is_active = active;
            }

            function transliterate() {
                var time = 100,
                    animate_class = "is-loading";

                var name = $.trim($name_field.val());

                $refresh_button.addClass(animate_class);

                getURLName(name)
                    .always( function() {
                        $refresh_button.removeClass(animate_class);
                    })
                    .done( function(url_name) {
                        $url_field.val(url_name).trigger("change");
                    });

                function getURLName(name) {
                    var deferred = $.Deferred();

                    clearTimeout(keyup_timer);

                    if (!name) {
                        deferred.resolve("");

                    } else {
                        keyup_timer = setTimeout( function() {
                            if (xhr) { xhr.abort(); }

                            xhr = $.get(that.urls["transliterate"], { name: name }, "json")
                                .always( function() {
                                    xhr = null;
                                })
                                .done( function(response) {
                                    var text = ( response.data.url ? response.data.url : "");
                                    deferred.resolve(text);
                                });
                        }, time);
                    }

                    return deferred.promise();
                }
            }

            function showChangeMessage() {
                if ($list.length) {
                    $("<li>").text(that.locales["storefront_changed"]).prependTo($list);
                    change_message_is_displayed = true;
                }
            }

            function checkUnique(value) {
                request(value).fail( function(html) {
                    if (typeof html === "string" && html.length > 0) {
                        renderError(html);
                    }
                });

                function request(value) {
                    var deferred = $.Deferred();

                    if (unique_xhr) { unique_xhr.abort(); }

                    var data = { id: that.product_id, url: value }

                    unique_xhr = $.post(that.urls["product_url_checker"], data, "json")
                        .always( function() { unique_xhr = null; })
                        .done( function(response) {
                            if (response.status === "ok") {
                                if (response.data.url_in_use.length) {
                                    deferred.reject(response.data.url_in_use);
                                } else {
                                    deferred.resolve();
                                }
                            } else {
                                deferred.reject();
                            }
                        })
                        .fail( function() {
                            deferred.reject();
                        });

                    return deferred.promise();
                }

                function renderError(html) {
                    var $wrapper = $url_field.closest(".s-url-field-wrapper"),
                        $error = $("<div />", { class: "wa-error-text error"}).html(html),
                        error_class = "has-error";

                    $wrapper.addClass(error_class);
                    $error.insertAfter($wrapper.closest(".s-url-redactor"));
                    $url_field
                        .one("input", function() {
                            $wrapper.removeClass(error_class);
                            $error.remove();
                        });
                }
            }
        };

        Section.prototype.initStatusSection = function() {
            var that = this;

            var $section = that.$wrapper.find(".js-product-status-section");
            if (!$section.length) { return false; }

            var $status_select = $section.find("#js-product-status-select"),
                $status_input = $status_select.find("input");

            $status_select.waDropdown({
                hover: false,
                items: ".dropdown-item",
                change: function(event, target) {
                    var status_id = $(target).data("id"),
                        status_ident = $(target).data("ident");

                    $section.attr("data-id", status_ident);
                    $status_input.val(status_id).trigger("change");
                }
            });

            var $redirect_section = $section.find(".s-redirect-section"),
                $redirect_select = $redirect_section.find("#js-product-redirect-select"),
                $redirect_input = $redirect_select.find("input");

            $redirect_select.waDropdown({
                hover: false,
                items: ".dropdown-item",
                change: function(event, target) {
                    var status_id = $(target).data("id");
                    $redirect_section.attr("data-id", status_id);
                    $redirect_input.val(status_id).trigger("change");
                }
            });
        };

        Section.prototype.initTypeSection = function() {
            var that = this;

            var $section = that.$wrapper.find(".js-product-type-section");
            if (!$section.length) { return false; }

            var $select = $section.find(".js-product-type-select"),
                $input = $select.find("input");

            $select.waDropdown({
                hover: false,
                items: ".dropdown-item",
                change: function(event, target, dropdown) {
                    var id = ($(target).data("id") || "");
                    $input.val(id).trigger("change");
                }
            });
        };

        Section.prototype.initTags = function() {
            var that = this;

            var $section = that.$wrapper.find(".s-tags-section"),
                $field = $section.find("#js-product-tags");

            $field.tagsInput({
                height: "auto",
                width: "auto",
                defaultText: that.locales["add_tag"]
            });
        };

        Section.prototype.initMainCategory = function() {
            var that = this;

            var $section = that.$wrapper.find(".s-product-main-category-section"),
                $form = $section.find(".js-add-main-category-form"),
                $select = $section.find("#js-product-main-category-select"),
                $input = $select.find("input");

            $select.waDropdown({
                hover: false,
                items: ".dropdown-item",
                change: function(event, target, dropdown) {
                    var id = "" + ($(target).data("id") || ""),
                        before_id = "" + $input.val();

                    $input.val(id).trigger("change");
                    that.product_category_id = id;
                    that.$wrapper
                        .trigger("change")
                        .trigger("change.main_category", [before_id, id]);

                    if (!id) {
                        var action = $(target).data("action");
                        if (action === "create") {
                            toggleContent(true);
                        }

                        dropdown.setTitle(that.locales["select_category"]);
                    }
                }
            });
            var dropdown = $select.waDropdown("dropdown");

            that.$wrapper.on("category.added", function(event, category) {
                renderCategory(category);
            });

            initForm($form);

            function initForm($form) {
                var $submit_button = $form.find(".js-submit-button"),
                    $icon = $submit_button.find(".s-icon");

                var loading = "<span class=\"icon\"><i class=\"fas fa-spinner fa-spin\"></i></span>";

                var is_locked = false;

                $submit_button.on("click", function(event) {
                    event.preventDefault();

                    if (!is_locked) {
                        is_locked = true;

                        var $loading = $(loading).insertAfter( $icon.hide() );
                        $submit_button.attr("disabled", true);

                        var data = $form.find(":input").serializeArray();

                        createCategory(data)
                            .always( function() {
                                $submit_button.attr("disabled", false);
                                $loading.remove();
                                $icon.show();
                                is_locked = false;
                            })
                            .done( function(category) {
                                that.addCategoryToData(category);
                                dropdown.setValue("id", category.id);
                                toggleContent(false);
                            });
                    }
                });

                $form.on("click", ".js-cancel", function(event) {
                    event.preventDefault();

                    if (!is_locked) {
                        toggleContent(false, true);
                    }
                });

                function createCategory(request_data) {
                    var deferred = $.Deferred();

                    $.post(that.urls["create_category"], request_data, "json")
                        .done( function(response) {
                            if (response.status === "ok") {
                                deferred.resolve({
                                    id: response.data.id,
                                    name: response.data.name,
                                    parent_id: response.data.parent_id,
                                    categories: {}
                                });
                            } else {
                                deferred.reject(response.errors);
                            }
                        })
                        .fail( function() {
                            deferred.reject([]);
                        });

                    return deferred.promise();
                }
            }

            function toggleContent(show, open_dropdown) {
                var extended_class = "is-extended";
                if (show) {
                    $section.addClass(extended_class);
                    $form.find("input").attr("disabled", false);
                } else {
                    $section.removeClass(extended_class);
                    $form.find("input").attr("disabled", true);
                    if (open_dropdown) {
                        setTimeout( function() {
                            dropdown.open();
                        }, 4);
                    }
                }
            }

            function renderCategory(category) {
                var template = '<div class="dropdown-item" data-id="%id%"><div class="dropdown-item-name">%name%</div></div>',
                    item = template
                        .replace("%id%", $.wa.escape(category.id))
                        .replace("%name%", $.wa.escape(category.name));

                var $item = $(item);

                if (category.parent_id && category.parent_id !== "0") {
                    var $parent_item = dropdown.$menu.find(".dropdown-item[data-id=\"" + category.parent_id + "\"]");
                    if ($parent_item.length) {
                        var $parent_w = $parent_item.parent(),
                            $group = $parent_item.find("> .dropdown-group");

                        if (!$group.length) {
                            $group = $("<div />", { class: "dropdown-group"}).appendTo($parent_w);
                        }

                        $group.append($item);
                    } else {
                        console.error("ERROR: Parent category is not exist");
                        dropdown.$menu.prepend($item);
                    }
                } else {
                    dropdown.$menu.prepend($item);
                }
            }
        };

        Section.prototype.initAdditionalCategories = function() {
            var that = this;

            var $section = that.$wrapper.find(".s-additional-categories-section");
            if (!$section.length) { return false; }

            var $list = $section.find(".js-categories-list");

            var loading = "<span class=\"s-icon icon baseline shift-1 size-12\"><i class=\"fas fa-spinner fa-spin\"></i></span>";
            var is_locked = false;

            $section.on("click", ".js-add-categories", function(event) {
                event.preventDefault();

                if (!is_locked) {
                    is_locked = true;

                    var $button = $(this),
                        $icon = $button.find(".s-icon"),
                        added_categories = getCategoriesIds();

                    var $loading = $(loading).insertAfter($icon.hide());

                    showAddCategoryDialog(added_categories)
                        .always( function() {
                            $loading.remove();
                            $icon.show();
                            is_locked = false;
                        })
                        .done(addCategoryToList);
                }
            });

            $section.on("click", ".js-category-remove", function(event) {
                event.preventDefault();
                $(this).closest(".s-category-wrapper").remove();
                that.$wrapper.trigger("change");
            });

            that.$wrapper.on("change.main_category", function(event, before_id, new_id) {
                hideMainCategory(before_id, new_id);
            });

            function getCategoriesIds() {
                var result = [];

                $list.find(".s-category-wrapper").each( function() {
                    var $category = $(this),
                        category_id = $category.data("id");

                    if (category_id) { result.push(category_id); }
                });

                return result;
            }

            function showAddCategoryDialog(added_categories) {
                var deferred = $.Deferred();

                var data = [];

                if (added_categories.length) {
                    $.each(added_categories, function(i, category_id) {
                        data.push({
                            name: "added_categories[]",
                            value: category_id
                        });
                    });
                }

                var $main_category_field = that.$wrapper.find("[name=\"product[category_id]\"]");
                if ($main_category_field.length) {
                    var main_category_id = $main_category_field.val();
                    if (main_category_id.length) {
                        data.push({
                            name: "main_category_id",
                            value: main_category_id
                        });
                    }
                }

                var is_success = false;

                $.post(that.urls["add_category_dialog"], data, "json")
                    .done( function(dialog_html) {
                        $.waDialog({
                            html: dialog_html,
                            options: {
                                onCreateCategory: function(category) {
                                    that.addCategoryToData(category);
                                },
                                onSuccess: function(data) {
                                    is_success = true;
                                    deferred.resolve(data);
                                }
                            },
                            onClose: function() {
                                if (!is_success) {
                                    deferred.reject();
                                }
                            }
                        });
                    })
                    .fail( function() {
                        deferred.reject();
                    });

                return deferred.promise();
            }

            function addCategoryToList(categories) {
                $list.html("");

                $.each(categories, function(i, category) {
                    var $category = renderCategory(category);
                    $category.appendTo($list);
                });

                hideMainCategory();

                that.$wrapper.trigger("change");

                function renderCategory(category) {
                    var template = that.templates["additional_category_template"]
                        .replace(/\%category_id\%/g, $.wa.escape(category.id))
                        .replace(/\%category_name\%/g, $.wa.escape(category.name));

                    return $(template);
                }
            }

            function hideMainCategory(before_id, new_id) {
                var main_class = "is-main";

                $list.find(".s-category-wrapper").each( function() {
                    var $category = $(this),
                        category_id = "" + $category.data("id");

                    if (category_id === that.product_category_id) {
                        $category.addClass(main_class);
                    } else {
                        $category.removeClass(main_class);
                    }

                    if (typeof before_id === "string" && before_id === category_id) {
                        $category.remove();
                    }
                });
            }
        };

        Section.prototype.initSetsSection = function() {
            var that = this;

            var $section = that.$wrapper.find(".s-product-sets-section");
            if (!$section.length) { return false; }

            var $list = $section.find(".js-sets-list");

            var loading = "<span class=\"s-icon icon baseline shift-1 size-12\"><i class=\"fas fa-spinner fa-spin\"></i></span>";
            var is_locked = false;

            $section.on("click", ".js-add-sets", function(event) {
                event.preventDefault();

                if (!is_locked) {
                    is_locked = true;

                    var $button = $(this),
                        $icon = $button.find(".s-icon"),
                        added_sets = getSetsIds();

                    var $loading = $(loading).insertAfter($icon.hide());

                    showAddSetsDialog(added_sets)
                        .always( function() {
                            $loading.remove();
                            $icon.show();
                            is_locked = false;
                        })
                        .done(addSetsToList);
                }
            });

            $section.on("click", ".js-set-remove", function(event) {
                event.preventDefault();
                $(this).closest(".s-set-wrapper").remove();
                that.$wrapper.trigger("change");
            });

            function getSetsIds() {
                var result = [];

                $list.find(".s-set-wrapper").each( function() {
                    var $set = $(this),
                        set_id = $set.data("id");

                    if (set_id) { result.push(set_id); }
                });

                return result;
            }

            function showAddSetsDialog(added_sets) {
                var deferred = $.Deferred();

                var data = [];

                data.push({
                    name: "product_id",
                    value: that.product_id
                });

                if (added_sets.length) {
                    $.each(added_sets, function(i, set_id) {
                        data.push({
                            name: "added_sets[]",
                            value: set_id
                        });
                    });
                }

                var is_success = false;

                $.post(that.urls["add_set_dialog"], data, "json")
                    .done( function(dialog_html) {
                        $.waDialog({
                            html: dialog_html,
                            options: {
                                onSuccess: function(data) {
                                    is_success = true;
                                    deferred.resolve(data);
                                }
                            },
                            onClose: function() {
                                if (!is_success) {
                                    deferred.reject();
                                }
                            }
                        });
                    })
                    .fail( function() {
                        deferred.reject();
                    });

                return deferred.promise();
            }

            function addSetsToList(sets) {
                $list.html("");

                $.each(sets, function(i, set) {
                    var $set = renderSet(set);
                    $set.appendTo($list);
                });

                that.$wrapper.trigger("change");

                function renderSet(set) {
                    var template = that.templates["set_template"]
                        .replace(/\%set_id\%/g, $.wa.escape(set.id))
                        .replace(/\%set_name\%/g, $.wa.escape(set.name));

                    return $(template);
                }
            }
        };

        Section.prototype.initProductSave = function() {
            var that= this,
                $form = that.$form,
                $submit_button = that.$wrapper.find(".js-product-save");

            var loading = "<span class=\"icon top\" style=\"margin-left: .5rem\"><i class=\"fas fa-spinner fa-spin\"></i></span>";

            $form.on("submit", function(event) {
                event.preventDefault();
            });

            $submit_button.on("click", function() {
                $form.trigger("submit");
                onSubmit();
            });

            function onSubmit() {
                if (!that.is_locked) {
                    that.is_locked = true;

                    var form_data = getData();
                    if (form_data.errors.length) {
                        that.renderErrors(form_data.errors);
                        that.is_locked = false;

                    } else {

                        var $loading = $(loading).appendTo($submit_button.attr("disabled", true));

                        request(that.urls["save"], form_data.data)
                            .done( function() {
                                $.wa_shop_products.router.reload();
                            })
                            .fail( function() {
                                $loading.remove();
                                $submit_button.attr("disabled", false);
                                that.is_locked = false;
                            });
                    }
                }

                function getData() {
                    var result = {
                            data: [],
                            errors: []
                        },
                        data = $form.serializeArray();

                    var sets_is_set = false;

                    $.each(data, function(index, item) {
                        if (item.name === "product[sets][]") {
                            sets_is_set = true;
                        }
                        result.data.push(item);
                    });

                    if (!sets_is_set) {
                        result.data.push({
                            name: "product[sets]",
                            value: ""
                        });
                    }

                    return result;
                }

                function request(href, data) {
                    var deferred = $.Deferred();

                    $.post(href, data, "json")
                        .done( function(response) {
                            if (response.status === "ok") {
                                deferred.resolve(response.data);

                            } else {
                                if (response.errors) {
                                    that.renderErrors(response.errors);
                                }
                                deferred.reject("errors", (response.errors ? response.errors: null));
                            }
                        })
                        .fail( function() {
                            deferred.reject("server_error", arguments);
                        });

                    return deferred.promise();
                }
            }
        };

        Section.prototype.initEditor = function() {
            var that = this;

            var $section = that.$wrapper.find(".js-editor-section"),
                $textarea = $section.find(".js-product-description-textarea"),
                $html_wrapper = $section.find(".js-html-editor"),
                $wysiwyg_wrapper = $section.find(".js-wysiwyg-editor");

            var html_editor = null,
                wysiwyg_redactor = null,
                active_type_id = "wysiwyg",
                confirmed = false;

            $section.find(".js-editor-type-toggle").waToggle({
                type: "tabs",
                change: function(event, target, toggle) {
                    onTypeChange($(target).data("id"));
                }
            });

            onTypeChange(active_type_id);

            function initAce($wrapper, $textarea) {
                var $editor = $('<div />').appendTo($wrapper),
                    editor = ace.edit($editor[0]),
                    value = $textarea.val();

                editor.$blockScrolling = Infinity;
                editor.renderer.setShowGutter(false);
                editor.setAutoScrollEditorIntoView(true);
                editor.setShowPrintMargin(false);
                editor.setTheme("ace/theme/eclipse");

                editor.session.setMode("ace/mode/css");
                editor.session.setMode("ace/mode/smarty");
                editor.session.setMode("ace/mode/javascript");
                editor.session.setValue(value.length ? value : " ");
                editor.session.setUseWrapMode(true);

                $textarea.data("ace", editor);

                editor.session.on("change", function() {
                    $textarea.val(editor.getValue()).trigger("change");
                });

                var $window = $(window);
                $window.on("resize", resizeWatcher);
                function resizeWatcher() {
                    var is_exist = $.contains(document, $wrapper[0]);
                    if (is_exist) {
                        editor.resize();
                    } else {
                        $window.off("resize", resizeWatcher);
                    }
                }

                return editor;
            }

            function initRedactor($wrapper, $textarea) {
                var options = getOptions();

                return $textarea.redactor(options);

                function getOptions(options) {
                    options = $.extend({
                        focus: false,
                        deniedTags: false,
                        minHeight: 300,
                        linkify: false,
                        source: false,
                        paragraphy: false,
                        replaceDivs: false,
                        toolbarFixed: true,
                        replaceTags: {
                            'b': 'strong',
                            'i': 'em',
                            'strike': 'del'
                        },
                        removeNewlines: false,
                        removeComments: false,
                        imagePosition: true,
                        imageResizable: true,
                        imageFloatMargin: '1.5em',
                        buttons: ['format', /*'inline',*/ 'bold', 'italic', 'underline', 'deleted', 'lists',
                            /*'outdent', 'indent',*/ 'image', 'video', 'table', 'link', 'alignment',
                            'horizontalrule',  'fontcolor', 'fontsize', 'fontfamily'],
                        plugins: ['fontcolor', 'fontfamily', 'alignment', 'fontsize', /*'inlinestyle',*/ 'table', 'video'],
                        imageUpload: '?module=pages&action=uploadimage&r=2',
                        imageUploadFields: {
                            '_csrf': getCsrf()
                        },
                        callbacks: {}
                    }, (options || {}));

                    if (options.uploadFields || options.uploadImageFields) {
                        options['imageUploadFields'] = options.uploadFields || options.uploadImageFields;
                    }

                    options.callbacks = $.extend({
                        imageUploadError: function(json) {
                            console.log('imageUploadError', json);
                            alert(json.error);
                        },
                        keydown: function (e) {
                            // ctrl + s
                            if ((e.which === '115' || e.which === '83' ) && (e.ctrlKey || e.metaKey)) {
                                e.preventDefault();
                                if (options.saveButton) {
                                    $(options.saveButton).click();
                                }
                                return false;
                            }
                            return true;
                        },
                        sync: function (html) {
                            html = html.replace(/{[a-z$][^}]*}/gi, function (match, offset, full) {
                                var i = full.indexOf("</script", offset + match.length);
                                var j = full.indexOf('<script', offset + match.length);
                                if (i === -1 || (j !== -1 && j < i)) {
                                    match = match.replace(/&gt;/g, '>');
                                    match = match.replace(/&lt;/g, '<');
                                    match = match.replace(/&amp;/g, '&');
                                    match = match.replace(/&quot;/g, '"');
                                }
                                return match;
                            });
                            if (options.callbacks.syncBefore) {
                                html = options.callbacks.syncBefore(html);
                            }
                            this.$textarea.val(html);
                        },
                        syncClean: function (html) {
                            // Unescape '->' in smarty tags
                            return html.replace(/\{[a-z\$'"_\(!+\-][^\}]*\}/gi, function (match) {
                                return match.replace(/-&gt;/g, '->');
                            });
                        }
                    }, (options.callbacks || {}));

                    if (options.saveButton && !options.callbacks.change) {
                        options.callbacks.change = function (html) {
                            $(options.saveButton).removeClass('green').addClass('yellow');
                        };
                    }

                    return options;
                }

                function getCsrf() {
                    var matches = document.cookie.match(new RegExp("(?:^|; )_csrf=([^;]*)"));
                    if (matches && matches[1]) {
                        return decodeURIComponent(matches[1]);
                    }
                    return '';
                }
            }

            function onTypeChange(type_id) {
                if (!confirmed && active_type_id === "html" && type_id !== active_type_id) {
                    if (confirm(that.locales["modification_wysiwyg_message"])) {
                        changeType(type_id);
                        confirmed = true;
                    }
                } else {
                    changeType(type_id);
                }

                function changeType(type_id) {
                    switch (type_id) {
                        case "html":
                            $html_wrapper.show();
                            $wysiwyg_wrapper.hide();
                            if (!html_editor) {
                                html_editor = initAce($html_wrapper, $textarea);
                            }
                            html_editor.session.setValue($textarea.val());
                            break;
                        case "wysiwyg":
                            $html_wrapper.hide();
                            $wysiwyg_wrapper.show();
                            if (!wysiwyg_redactor) {
                                wysiwyg_redactor = initRedactor($wysiwyg_wrapper, $textarea);
                            }
                            $textarea.redactor("code.set", $textarea.val());
                            break;
                        default:
                            break;
                    }
                    active_type_id = type_id;
                }
            }
        };

        Section.prototype.addCategoryToData = function(category) {
            var that = this;

            that.categories[category.id] = category;

            if (category.parent_id && category.parent_id !== "0") {
                var parent_category = that.categories[category.parent_id];
                if (parent_category) {
                    parent_category.categories[category.id] = category;
                } else {
                    console.error("ERROR: parent category is not exist");
                }
            } else {
                that.categories_tree[category.id] = category;
            }

            that.$wrapper
                .trigger("change")
                .trigger("category.added", [category]);
        };

        return Section;

    })($);

    $.wa_shop_products.init.initProductGeneralSection = function(options) {
        return new Section(options);
    };

})(jQuery);