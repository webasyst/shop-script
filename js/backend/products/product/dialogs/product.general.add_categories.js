( function($) {

    var Dialog = ( function($) {

        Dialog = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];

            // CONST
            that.dialog = that.$wrapper.data("dialog");
            that.templates = options["templates"];
            that.urls = options["urls"];

            that.categories = options["categories"];

            // DYNAMIC VARS

            // INIT
            that.init();
        };

        Dialog.prototype.init = function() {
            var that = this;

            var $list = that.$wrapper.find(".js-categories-list");

            that.$wrapper.on("keyup change", ".js-filter-field", function(event) {
                var value = $(this).val().toLowerCase();

                $list.find(".s-category .s-name").each( function() {
                    var $text = $(this),
                        $category = $text.closest(".s-category"),
                        is_good = ($text.text().toLowerCase().indexOf(value) >= 0);

                    if (value.length) {
                        if (is_good) {
                            $category.show();
                        } else {
                            $category.hide();
                        }
                    } else {
                        $category.show();
                    }
                });
            });

            that.$wrapper.on("click", ".js-create-category", function(event) {
                event.preventDefault();

                var $category = $(this).closest(".s-category"),
                    category_id = $category.data("id");

                if ($category.data("locked")) {
                    return false;
                } else {
                    $category.data("locked", true);
                }

                var form = that.templates["add_category_form"]
                    .replace("%parent_id%", (category_id ? category_id : ""));

                var $form = $(form).appendTo($category);

                that.dialog.resize();

                initForm($form)
                    .always( function() {
                        $category.removeData("locked");
                    })
                    .done( function(category) {
                        that.categories[category.id] = category;

                        var $category_wrapper = $category.closest(".s-category-wrapper"),
                            $new_category = renderCategory(category);

                        if (category_id) {
                            var $category_group = $category_wrapper.find(".s-categories-group");
                            if (!$category_group.length) {
                                $category_group = $("<div class=\"s-categories-group\" />").appendTo($category_wrapper);
                            }
                            $category_group.append($new_category);
                        } else {
                            $new_category.insertAfter($category_wrapper);
                        }
                    });
            });

            that.$wrapper.on("click", ".js-add-categories", function(event) {
                event.preventDefault();
                that.dialog.options.onSuccess(getActiveCategories());
                that.dialog.close();
            });

            function getActiveCategories() {
                var result = [];

                $list.find(".js-category-field:checked").each( function() {
                    var $category = $(this).closest(".s-category");

                    var category_id = $category.data("id"),
                        category = that.categories[category_id];

                    result.push(category);
                });

                return result;
            }

            function initForm($form) {
                var deferred = $.Deferred();

                var $submit_button = $form.find(".js-submit-button"),
                    $icon = $submit_button.find(".s-icon");

                var loading = "<span class=\"icon\"><i class=\"fas fa-spinner fa-spin\"></i></span>";

                var is_locked = false;

                $form.on("submit", function(event) {
                    event.preventDefault();

                    if (!is_locked) {
                        is_locked = true;

                        var $loading = $(loading).insertAfter( $icon.hide() );
                        $submit_button.attr("disabled", true);

                        var data = $form.serializeArray();

                        createCategory(data)
                            .always( function() {
                                $submit_button.attr("disabled", false);
                                $loading.remove();
                                $icon.show();
                                is_locked = false;
                            })
                            .done( function(category) {
                                $form.remove();
                                deferred.resolve(category);
                                that.dialog.options.onCreateCategory(category);
                            });
                    }
                });

                $form.on("click", ".js-cancel", function(event) {
                    event.preventDefault();
                    $form.remove();
                    that.dialog.resize();
                    deferred.reject();
                });

                return deferred.promise();

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

            function renderCategory(category) {
                var new_category = that.templates["new_category"]
                        .replace(/\%category_id\%/g, $.wa.escape(category.id))
                        .replace(/\%category_name\%/g, $.wa.escape(category.name))
                        .replace(/\%category_parent_id\%/g, $.wa.escape(category.parent_id));

                var $new_category_wrapper = $("<div />", {
                    class: "s-category-wrapper"
                });

                return $new_category_wrapper.prepend(new_category);
            }
        };

        return Dialog;

    })($);

    $.wa_shop_products.init.initProductGeneralAddCategoriesDialog = function(options) {
        return new Dialog(options);
    };

})(jQuery);