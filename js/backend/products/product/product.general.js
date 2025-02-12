( function($) {

    var Section = ( function($) {

        Section = function(options) {
            var that = this;
            const { reactive } = Vue;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$form = that.$wrapper.find("form:first");

            // CONST
            that.product_id = options["product_id"];
            that.components = options["components"];
            that.templates = options["templates"];
            that.tooltips = options["tooltips"];
            that.locales = options["locales"];
            that.urls = options["urls"];
            that.lang = options["lang"];
            that.can_use_smarty = options["can_use_smarty"];

            //
            that.max_file_size = options["max_file_size"];
            that.max_post_size = options["max_post_size"];

            // VUE JS MODELS
            that.stocks_array = options["stocks"];
            that.stocks = $.wa.construct(that.stocks_array, "id");
            that.product = reactive(formatProduct(options["product"]));
            that.currencies = options["currencies"];
            // Ошибки sku vue model
            that.errors = {};

            // DYNAMIC VARS
            that.categories_tree = options["categories_tree"];
            that.categories = formatCategories(that.categories_tree);
            that.product_category_id = options["product_category_id"];
            that.is_changed = false;
            that.is_locked = false;
            that.keys = {
                fractional: 0
            };
            that.fractional_vue_model = null;

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

            function formatProduct(product) {
                // product.normal_mode = false;
                // product.normal_mode_switch = false;

                product.badge_form = false;
                product.badge_id = (typeof product.badge_id === "string" ? product.badge_id : null);
                product.badge_prev_id = null;

                // Проставляю параметры с дефолтными значениями
                $.each(product.features, function(i, feature) {
                    if (feature.can_add_value) {
                        var white_list = ["select", "checkbox"];
                        if (white_list.indexOf(feature.render_type) >= 0) {
                            feature.show_form = false;
                            feature.form = { value: "" }

                            if (feature.type === "color") { feature.form.code = ""; }
                            if (feature.type.indexOf("dimension") >= 0) { feature.form.unit = feature.active_unit.value; }
                        }
                    }
                });

                $.each(product.skus, function(i, sku) {
                    sku.errors = {};

                    $.each(sku.modifications, function(i, sku_mod) {
                        formatModification(sku_mod);

                        if (!product.normal_mode) {
                            sku_mod.expanded = false;
                        }
                    });
                });

                return product;
            }

            function formatModification(sku_mod) {
                var stocks = that.stocks;

                sku_mod.expanded = false;
                sku_mod.stocks_expanded = false;
                sku_mod.stocks_mode = true;
                sku_mod.stocks_indicator = 1;
                sku_mod.count = (sku_mod.count > 0 ? $.wa.validate("float", sku_mod.count) : sku_mod.count);

                if (typeof sku_mod.stock !== "object" || Array.isArray(sku_mod.stock)) {
                    sku_mod.stocks_mode = false;
                    sku_mod.stock = {};
                }

                $.each(stocks, function(stock_id, stock_count) {
                    if (typeof sku_mod.stock[stock_id] === "undefined") {
                        sku_mod.stock[stock_id] = "";
                    }
                });

                if ($.wa_shop_products && !$.wa_shop_products.stockVerification(sku_mod.stock, that.stocks)) {
                    return;
                }

                updateVirtualStockValue(sku_mod);

                that.updateModificationStocks(sku_mod);

                return sku_mod;

                function updateVirtualStockValue(sku_mod) {
                    var virtual_stocks = [];

                    $.each(sku_mod.stock, function(stock_id, stock_value) {
                        var stock = that.stocks[stock_id];
                        if (stock.is_virtual) {
                            virtual_stocks.push(stock);
                        }
                    });

                    $.each(virtual_stocks, function(i, stock) {
                        var value = "";

                        if (stock.substocks) {
                            $.each(stock.substocks, function(j, sub_stock_id) {
                                var sub_stock_value = sku_mod.stock[sub_stock_id];
                                sub_stock_value = parseFloat(sub_stock_value);
                                if (!isNaN(sub_stock_value)) {
                                    if (!value) { value = 0; }
                                    value += sub_stock_value;
                                } else {
                                    value = "";
                                    return false;
                                }
                            });
                        }

                        sku_mod.stock[stock.id] = $.wa.validate("float", value);
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

            initSimulateFocus();

            that.initStorefrontsSection();

            that.initStatusSection();

            that.initFractionalSection();

            that.initSkuSection();

            that.initTypeSection();

            that.initMainCategory();

            that.initAdditionalCategories();

            that.initSetsSection();

            that.initTags();

            that.initEditor();

            that.initSave();

            function initAreaAutoHeight() {
                that.$wrapper.find("textarea.js-auto-height").each( function() {
                    var $textarea = $(this);
                    var offset = $textarea[0].offsetHeight - $textarea[0].clientHeight,
                        start_height = $textarea.outerHeight();

                    toggleHeight($textarea);

                    $textarea.on("keyup", function() {
                        toggleHeight($textarea);
                    });

                    function toggleHeight($textarea) {
                        var textarea = $textarea[0];
                        textarea.style.minHeight = "";
                        var scroll_top = textarea.scrollHeight + offset;
                        textarea.style.minHeight = scroll_top + "px";
                    }
                });
            }

            function initSimulateFocus() {
                that.$wrapper.on("focus", ".js-simulate-border-lighting, .tagsinput", function(event) {
                    $(this).addClass("is-focus");
                });

                that.$wrapper.on("blur", ".js-simulate-border-lighting, .tagsinput", function(event) {
                    $(this).removeClass("is-focus");
                });
            }

            $.each(that.tooltips, function(i, tooltip) {
                $.wa.new.Tooltip(tooltip);
            });

            var ready_promise = that.$wrapper.data("ready");
            ready_promise.resolve(that);

            if (that.product.name) {
                that.$wrapper.trigger("change_product_name", [that.product.name]);
            } else {
                setTimeout( function() {
                    that.$wrapper.find(".js-product-name-field").trigger("focus");
                }, 100);
            }
        };

        Section.prototype.renderErrors = function(errors) {
            errors = (typeof errors === "object" ? errors : []);

            var that = this,
                $errors_place = that.$wrapper.find(".js-errors-place"),
                $focus_message = null;

            $errors_place.html("");

            $.each(errors, function(index, item) {
                if (!item || !item.text) {
                    return;
                }

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

            } else {
                var $errors = that.$wrapper.find(".state-error, .state-error-hint");
                if ($errors.length) {
                    var top = $errors.first().offset().top - 100;
                    top = (top > 0 ? top : 0);
                    $(window).scrollTop(top);
                }
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

            var $error_html = null;

            $name_field.on("input", function() {
                var value = $.trim($name_field.val()),
                    show_length_error = (value.length > 255),
                    error_class = "state-error";

                if (show_length_error) {
                    if (!$error_html) {
                        $error_html = $("<div />", { class: "state-error-hint"}).html(that.locales["max_length_error"]);
                        $error_html.insertAfter($name_field);
                    }
                    $name_field.addClass(error_class);
                } else {
                    if ($error_html) {
                        $error_html.remove();
                        $error_html = null;
                    }
                    $name_field.removeClass(error_class);
                }

                validateNameField();
            });

            var input_timer = 0;

            that.$wrapper.on("product-name-error", validateNameField);

            $url_field
                .on("input", function(event) {
                    var value = $url_field.val();
                    var forbidden_symbols = new RegExp("[/|#|\\\\|?]");
                    value = value.replace(forbidden_symbols, "");
                    $url_field.val(value);
                })
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
                var $list_extended_items = $list.find(".s-extended-item"),
                    $toggle = $list.find(".js-list-toggle");

                var is_list_extended = false;

                $toggle.on("click", function(event) {
                    event.preventDefault();
                    toggle(!is_list_extended);
                });

                $list.on("click", ".js-list-close", function(event) {
                    event.preventDefault();
                    toggle(false);
                    $(window).scrollTop(0);
                });

                function toggle(show) {
                    if (show) {
                        $list_extended_items.show();
                        $toggle.text(that.locales["storefronts_hide"]);
                    } else {
                        $list_extended_items.hide();
                        $toggle.text(that.locales["storefronts_show"]);
                    }

                    is_list_extended = show;
                }
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
                    $('<div class="s-message-change">').text(that.locales["storefront_changed"]).prependTo($list);
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
                        $error = $("<div />", { class: "state-error-hint"}).html(html),
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

            var $product_error = null;

            function validateNameField() {
                var value = $.trim($name_field.val()),
                    error_class = "state-error";

                if (value) {
                    $name_field.removeClass(error_class);
                    if ($product_error) {
                        $product_error.remove();
                        $product_error = null;
                    }

                } else {
                    if (!$product_error) {
                        $product_error = $('<div class="state-error-hint"></div>').html(that.locales["product_name_required"]);
                        $name_field.addClass(error_class).after($product_error);
                    }
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
                $redirect_input = $redirect_select.find("input"),
                $url_validate_error = $redirect_section.find(".js-validate-email");

            $redirect_select.waDropdown({
                hover: false,
                items: ".dropdown-item",
                change: function(event, target) {
                    var status_id = $(target).data("id");
                    $redirect_section.attr("data-id", status_id);
                    $redirect_input.val(status_id).trigger("change");

                    if (status_id !== 'url') {
                        ["error_url_required", "error_url_incorrect"].forEach(err_key => {
                            delete that.errors[err_key];
                        });
                    }
                }
            });

            var $error = null,
                timer = 0;

            $redirect_section.on("input", ".js-validate-email", function() {
                var $field = $(this);

                clearTimeout(timer);
                timer = setTimeout( function() { onUrlChange($field); }, 100);
            });

            function onUrlChange($field) {
                var value = $.trim($field.val()),
                    is_valid = $.wa.isValid("url_absolute", value),
                    error_class = "state-error";

                // Рендер ошибки на поле
                if (!value.length) {
                    renderError($(that.templates["error_url_required"]));
                    $field.addClass(error_class);
                } else if (!is_valid) {
                    renderError($(that.templates["error_url_incorrect"]));
                    $field.addClass(error_class);
                } else {
                    $field.removeClass(error_class);
                    if ($error) { $error.remove(); $error = null; }
                }

                // Добавление ошибки в модель чтобы блочить сохранение
                if (value.length) {
                    delete that.errors["error_url_required"];
                } else {
                    that.errors["error_url_required"] = {
                        "id": "error_url_required",
                        "text": "error_url_required"
                    };
                }

                // Добавление ошибки в модель чтобы блочить сохранение
                if (is_valid) {
                    delete that.errors["error_url_incorrect"];
                } else {
                    that.errors["error_url_incorrect"] = {
                        "id": "error_url_incorrect",
                        "text": "error_url_incorrect"
                    };
                }

                function renderError($_error) {
                    if ($error) {
                        $_error.insertAfter($error);
                        $error.remove();
                        $error = $_error;
                    } else {
                        $error = $_error.insertAfter($field);
                    }
                }
            }

            $redirect_section.find('[name="product[params][redirect_code]"]').on("change", function() {
                if ($(this).val() === "301") {
                    $redirect_section.find('.redirect-message').show();
                } else {
                    $redirect_section.find('.redirect-message').hide();
                }
            });
        };

        Section.prototype.initSkuSection = function() {
            var that = this;

            var $section = that.$wrapper.find("#vue-sku-section");

            if (typeof $.vue_app === "object" && typeof $.vue_app.unmount === "function") {
                $.vue_app.unmount();
            }

            $.vue_app = Vue.createApp({
                data() {
                    return {
                        errors: that.errors,
                        stocks: that.stocks,
                        stocks_array: that.stocks_array,
                        product: that.product,
                        currencies: that.currencies
                    }
                },
                components: {
                    "component-switch": {
                        props: ["modelValue", "disabled"],
                        emits: ["update:modelValue", "change"],
                        template: '<div class="switch"><input v-bind:id="$attrs.input_id" type="checkbox" v-bind:checked="prop_checked" v-bind:disabled="prop_disabled"></div>',
                        delimiters: ['{ { ', ' } }'],
                        computed: {
                            prop_checked() { return (typeof this.modelValue === "boolean" ? this.modelValue : false) },
                            prop_disabled() { return (typeof this.disabled === "boolean" ? this.disabled : false) }
                        },
                        mounted: function() {
                            var self = this;

                            $(self.$el).waSwitch({
                                change: function(active, wa_switch) {
                                    self.$emit("change", active);
                                    self.$emit("update:modelValue", active);
                                }
                            });
                        }
                    },
                    "component-dropdown-currency": {
                        props: ["currency_code", "currencies", "wide"],
                        template: that.templates["component-dropdown-currency"],
                        delimiters: ['{ { ', ' } }'],
                        computed: {
                            prop_wide() { return (typeof this.wide === "boolean" ? this.wide : false) }
                        },
                        mounted: function() {
                            var self = this;

                            $(self.$el).waDropdown({
                                hover: false,
                                items: ".dropdown-item",
                                change: function(event, target, dropdown) {
                                    self.$emit("change", $(target).data("id"));
                                }
                            });
                        }
                    },
                    "component-product-badge-form": {
                        props: ["product"],
                        data: function() {
                            return {
                                "badge": this.product.badges[this.product.badges.length - 1]
                            }
                        },
                        template: that.templates["component-product-badge-form"],
                        methods: {
                            updateForm: function() {
                                this.product.badge_form = false;

                                that.$wrapper.trigger("change");
                            },
                            revertForm: function() {
                                this.badge.code = this.badge.code_model;

                                if (this.product.badge_prev_id) {
                                    this.product.badge_id = this.product.badge_prev_id;
                                    that.$wrapper.trigger("change");
                                }

                                this.product.badge_form = false;
                            }
                        },
                        mounted: function() {
                            var self = this;

                            var $textarea = $(self.$el).find("textarea");

                            toggleHeight($textarea);

                            $textarea.on("input", function() {
                                toggleHeight($textarea);
                            });

                            function toggleHeight($textarea) {
                                $textarea.css("min-height", 0);
                                var scroll_h = $textarea[0].scrollHeight;
                                $textarea.css("min-height", scroll_h + "px");
                            }
                        }
                    },
                    "component-file-manager": {
                        props: ["sku_mod"],
                        data: function() {
                            var self = this;
                            return {
                                file: self.sku_mod.file,
                                files_to_upload: [],
                                is_locked: false,
                                errors: []
                            };
                        },
                        template: that.templates["component-file-manager"],
                        components: {
                            "component-flex-textarea": {
                                props: ["modelValue", "placeholder"],
                                emits: ["update:modelValue", "blur", "ready"],
                                template: `<textarea v-bind:placeholder="placeholder" v-bind:value="modelValue" v-on:input="$emit('update:modelValue', $event.target.value)" v-on:blur="$emit('blur', $event.target.value)"></textarea>`,
                                delimiters: ['{ { ', ' } }'],
                                updated: function() {
                                    var self = this;
                                    var $textarea = $(self.$el);

                                    $textarea.css("min-height", 0);
                                    var scroll_h = $textarea[0].scrollHeight;
                                    $textarea.css("min-height", scroll_h + "px");
                                },
                                mounted: function() {
                                    var self = this;
                                    var $textarea = $(self.$el);

                                    $textarea.css("min-height", 0);
                                    var scroll_h = $textarea[0].scrollHeight;
                                    $textarea.css("min-height", scroll_h + "px");
                                }
                            },
                            "component-loading-file": {
                                props: ["file", "sku_mod"],
                                template: '<div class="vue-component-loading-file"><div class="wa-progressbar"></div></div>',
                                mounted: function() {
                                    var self = this;

                                    var $bar = $(self.$el).find(".wa-progressbar").waProgressbar({}),
                                        instance = $bar.data("progressbar");

                                    loadFile(self.file)
                                        .done( function(response) {
                                            if (response.status === "ok") {
                                                self.$emit("file_load_success", {
                                                    file: self.file,
                                                    file_data: response.data
                                                });
                                            } else if (response.errors) {
                                                self.$emit("file_load_fail", {
                                                    file: self.file,
                                                    errors: response.errors
                                                });
                                            }
                                        });

                                    function loadFile(file) {
                                        var formData = new FormData();

                                        formData.append("product_id", that.product.id);
                                        formData.append("sku_id", self.sku_mod.id);
                                        formData.append("files", file);

                                        // Ajax request
                                        return $.ajax({
                                            xhr: function() {
                                                var xhr = new window.XMLHttpRequest();
                                                xhr.upload.addEventListener("progress", function(event){
                                                    if (event.lengthComputable) {
                                                        var percent = parseInt( (event.loaded / event.total) * 100 );
                                                        instance.set({ percentage: percent });
                                                    }
                                                }, false);
                                                return xhr;
                                            },
                                            url: that.urls["sku_file_upload"],
                                            data: formData,
                                            cache: false,
                                            contentType: false,
                                            processData: false,
                                            type: 'POST'
                                        });
                                    }
                                }
                            }
                        },
                        delimiters: ['{ { ', ' } }'],
                        computed: {},
                        methods: {
                            fileDelete: function(event) {
                                var self = this;
                                if (!self.is_locked) {
                                    var loading = "<span class=\"icon color-gray\"><i class=\"fas fa-spinner fa-spin\"></i></span>";

                                    var $icon = $(event.currentTarget).find(".s-icon"),
                                        $loading = $(loading).insertAfter( $icon.hide() );

                                    self.is_locked = true;
                                    showDeleteConfirm()
                                        .always( function() {
                                            self.is_locked = false;
                                            $loading.remove();
                                            $icon.show();
                                        })
                                        .done( function() {
                                            var data = {
                                                sku_id: self.sku_mod.id,
                                                product_id: that.product.id
                                            };

                                            $.post(that.urls["sku_file_delete"], data, "json")
                                                .done( function() {
                                                    $.each(Object.keys(self.file), function(i, key) {
                                                        self.file[key] = null;
                                                    });
                                                    that.$wrapper.trigger("change");
                                                });
                                        });
                                }

                                function showDeleteConfirm() {
                                    var deferred = $.Deferred();
                                    var is_success = false;

                                    $.waDialog({
                                        html: that.templates["sku-file-delete-confirm-dialog"],
                                        onOpen: function($dialog, dialog) {

                                            var $section = $dialog.find(".js-vue-node-wrapper");

                                            Vue.createApp({
                                                delimiters: ['{ { ', ' } }'],
                                                created: function() {
                                                    $section.css("visibility", "");
                                                },
                                                mounted: function() {
                                                    dialog.resize();
                                                }
                                            }).mount($section[0]);

                                            $dialog.on("click", ".js-success-action", function(event) {
                                                event.preventDefault();
                                                is_success = true;
                                                dialog.close();
                                            });
                                        },
                                        onClose: function() {
                                            if (is_success) {
                                                deferred.resolve();
                                            } else {
                                                deferred.reject();
                                            }
                                        }
                                    });

                                    return deferred.promise();
                                }
                            },
                            onFileChange: function(event) {
                                var self = this,
                                    files = event.target.files;

                                if (files.length) {
                                    // $.each(files, function(i, file) {
                                    self.loadFile(files[0]);
                                    // });
                                }

                                // clear
                                $(event.target).val("");
                                self.errors.splice(0, self.errors.length);
                            },
                            loadFile: function(file) {
                                var self = this,
                                    file_size = file.size;

                                if (file_size >= that.max_file_size) {
                                    self.renderError({ id: "big_size", text: "ERROR: big file size" });
                                } else if (file_size >= that.max_post_size) {
                                    self.renderError({ id: "big_post", text: "ERROR: big POST file size" });
                                } else {
                                    self.files_to_upload.push(file);
                                }
                            },
                            onFileLoadSuccess: function(data) {
                                var self = this;

                                self.file.id = data.file_data.id;
                                self.file.url = data.file_data.url;
                                self.file.name = data.file_data.name;
                                self.file.size = data.file_data.size;
                                self.file.description = data.file_data.description;

                                // удаляем UI загрузки
                                var index = self.files_to_upload.indexOf(data.file);
                                if (index >= 0) { self.files_to_upload.splice(index, 1); }
                            },
                            onFileLoadFail: function(data) {
                                var self = this;

                                // Показываем ошибки
                                $.each(data.errors, function(i, error) {
                                    self.renderError(error);
                                });

                                // Убираем загрузку
                                $.each(Object.keys(self.file), function(i, key) {
                                    self.file[key] = null;
                                });

                                // удаляем UI загрузки
                                var index = self.files_to_upload.indexOf(data.file);
                                if (index >= 0) { self.files_to_upload.splice(index, 1); }
                            },
                            renderError: function(error) {
                                var self = this;
                                self.errors.push(error);
                            }
                        },
                        mounted: function() {
                            var self = this;
                        }
                    }
                },
                delimiters: ['{ { ', ' } }'],
                computed: {
                    sku: function() {
                        return this.product.skus[0];
                    },
                    getSkuModAvailableState: function() {
                        var self = this;

                        return function(sku, target_state) {
                            var sku_mods_count = sku.modifications.length,
                                active_sku_mods_count = sku.modifications.filter( function(sku_mod) {
                                    return sku_mod.available;
                                }).length;

                            var state = "";

                            if (sku_mods_count === active_sku_mods_count) {
                                state = "all";
                            } else if (active_sku_mods_count === 0) {
                                state = "none";
                            } else {
                                state = "part";
                            }

                            if (typeof target_state === "string") {
                                return (target_state === state);
                            } else {
                                return state;
                            }
                        }
                    },
                    getSkuModAvailableTitle: function() {
                        var self = this;

                        return function(sku, locales) {
                            var state = self.getSkuModAvailableState(sku),
                                result = "";

                            if (state === "all") {
                                result = locales[2];
                            } else if (state === "part") {
                                result = locales[1];
                            } else {
                                result = locales[0];
                            }

                            return result;
                        }
                    },
                    stock_icon_class: function() {
                        return function(stock_value, stock) {
                            stock_value = (stock_value ? parseInt(stock_value) : null);

                            var result = "";
                            if (stock_value > parseInt(stock.low_count) || stock_value === null) {
                                result = "text-green";
                            } else if (stock_value > parseInt(stock.critical_count) && stock_value <= parseInt(stock.low_count)) {
                                result = "text-orange";
                            } else if (stock_value <= parseInt(stock.critical_count)) {
                                result = "text-red";
                            }

                            return result;
                        }
                    }
                },
                methods: {
                    // Misc
                    changeProductMode: function(event) {
                        that.keys.fractional += 1;
                    },
                    addProductPhoto: function(event, sku_mod) {
                        var self = this;

                        $.waDialog({
                            html: that.templates["dialog_photo_manager"],
                            options: {
                                onPhotoAdd: function(photo) {
                                    that.product.photos.push(photo);
                                    if (that.product.photos.length === 1) {
                                        self.setProductPhoto(that.product.photos[0], sku_mod);
                                    }
                                },
                                onSuccess: function(image_id) {
                                    var photo_array = that.product.photos.filter( function(photo) { return (photo.id === image_id); });
                                    if (photo_array.length) {
                                        self.setProductPhoto(photo_array[0], sku_mod);
                                    }
                                }
                            },
                            onOpen: function($dialog, dialog) {
                                that.initPhotoManagerDialog($dialog, dialog, sku_mod);
                            }
                        });
                    },
                    setProductPhoto: function(photo, sku_mod) {
                        sku_mod["photo"] = photo;
                        sku_mod["image_id"] = photo.id;
                        that.$wrapper.trigger("change");
                    },
                    removeProductPhoto: function(sku_mod) {
                        var self = this;

                        $.waDialog({
                            html: that.templates["dialog_sku_delete_photo"],
                            options: {
                                onUnpin: function() {
                                    sku_mod["photo"] = null;
                                    sku_mod["image_id"] = null;
                                    that.$wrapper.trigger("change");
                                },
                                onDelete: function() {
                                    var photo = sku_mod.photo;

                                    // удаляем фотку на сервере
                                    $.get(that.urls["delete_image"], { id: photo.id }, "json");

                                    // удаляем фотку из модели модификаций
                                    $.each(that.product.skus, function(i, _sku) {
                                        $.each(_sku.modifications, function(i, _sku_mod) {
                                            if (_sku_mod.photo && _sku_mod.photo.id === photo.id) {
                                                _sku_mod["photo"] = null;
                                                _sku_mod["image_id"] = null;
                                            }
                                        });
                                    });

                                    // удаляем фотку из модели продукта
                                    var index = null;
                                    $.each(that.product.photos, function(i, _photo) {
                                        if (_photo.id === photo.id) { index = i; return false; }
                                    });
                                    if (typeof index === "number") {
                                        that.product.photos.splice(index, 1);
                                    }

                                    // проставляем первую фотку как главную если простой режим.
                                    if (!self.product.normal_mode_switch) {
                                        if (that.product.photos.length) {
                                            self.setProductPhoto(that.product.photos[0], sku_mod);
                                        }
                                    }

                                    that.$wrapper.trigger("change");
                                }
                            },
                            onOpen: initDialog
                        });

                        function initDialog($dialog, dialog) {
                            if (self.product.normal_mode_switch) {
                                $dialog.on("click", ".js-unpin-button", function(event) {
                                    event.preventDefault();
                                    dialog.options.onUnpin();
                                    dialog.close();
                                });
                            } else {
                                $dialog.find(".js-unpin-button").remove();
                            }

                            $dialog.on("click", ".js-delete-button", function(event) {
                                event.preventDefault();
                                dialog.options.onDelete();
                                dialog.close();
                            });
                        }
                    },

                    // Badges
                    changeProductBadge: function(badge) {
                        var self = this;

                        self.product.badge_prev_id = self.product.badge_id;

                        if (badge) {
                            self.product.badge_form = (badge.id === "");
                            self.product.badge_id = badge.id;
                        } else {
                            self.product.badge_id = null;
                            self.product.badge_form = false;
                        }

                        that.$wrapper.trigger("change");
                    },

                    // SKU
                    changeSkuSku: function(sku, sku_index) {
                        var self = this;

                        $.each(sku.modifications, function(i, sku_mod) {
                            sku_mod.sku = sku.sku;
                        });
                    },
                    changeSkuName: function(sku, sku_index) {
                        $.each(sku.modifications, function(i, sku_mod) {
                            sku_mod.name = sku.name;
                        });
                    },

                    // MODIFICATIONS
                    skuMainToggle: function(sku, sku_index) {
                        if (sku.sku_id !== that.product.sku_id) {
                            $.each(that.product.skus, function(i, _sku) {
                                if (_sku !== sku) {
                                    _sku.sku_id = null;
                                }
                            });

                            var sku_mod = sku.modifications[0];

                            updateProductMainPhoto( sku_mod.image_id ? sku_mod.image_id : null );
                            that.product.sku_id = sku.sku_id = sku_mod.id;

                            that.$wrapper.trigger("change");
                        }
                    },
                    skuStatusToggle: function(sku, sku_index) {
                        var is_active = !!(sku.modifications.filter( function(sku_mod) { return (sku_mod.status === 'enabled'); }).length);

                        $.each(sku.modifications, function(i, sku_mod) {
                            sku_mod.status = (is_active ? "disabled" : "enabled");
                        });

                        that.$wrapper.trigger("change");
                    },
                    skuAvailableToggle: function(sku, sku_index) {
                        var is_active = !!(sku.modifications.filter( function(sku_mod) { return sku_mod.available; }).length);

                        $.each(sku.modifications, function(i, sku_mod) {
                            sku_mod.available = !is_active;
                        });

                        that.$wrapper.trigger("change");
                    },
                    changeModificationCurrency: function(currency_code) {
                        that.product.currency = currency_code;
                    },
                    changeSkuModStocks: function(sku_mod) {
                        var stocks_count = 0,
                            is_infinite = false,
                            is_set = false;

                        var virtual_stocks = [];
                        $.each(sku_mod.stock, function(stock_id, stock_value) {
                            var value = parseFloat(stock_value);
                            if (!isNaN(value)) {
                                is_set = true;
                                stocks_count += value;
                            } else {
                                is_infinite = true;
                                value = "";
                            }
                            sku_mod.stock[stock_id] = value;

                            var stock = that.stocks[stock_id];
                            if (stock.is_virtual) {
                                virtual_stocks.push(stock);
                            }
                        });

                        $.each(virtual_stocks, function(i, stock) {
                            var value = "";

                            if (stock.substocks) {
                                $.each(stock.substocks, function(j, sub_stock_id) {
                                    var sub_stock_value = sku_mod.stock[sub_stock_id];
                                    sub_stock_value = parseFloat(sub_stock_value);
                                    if (!isNaN(sub_stock_value)) {
                                        if (!value) { value = 0; }
                                        value += sub_stock_value;
                                    } else {
                                        value = "";
                                        return false;
                                    }
                                });
                            }

                            sku_mod.stock[stock.id] = $.wa.validate("float", value);
                        });

                        sku_mod.stocks_mode = is_set;
                        sku_mod.count = (is_set && !is_infinite ? $.wa.validate("float", stocks_count) : "");

                        that.updateModificationStocks(sku_mod);
                    },
                    focusSkuModStocks: function(sku_mod) {
                        if (Object.keys(that.stocks).length) {
                            this.modificationStocksToggle(sku_mod, true);
                        }
                    },
                    modificationMainToggle: function(sku_mod, sku_mod_index, sku, sku_index) {
                        if (sku.sku_id !== sku_mod.id) {
                            $.each(that.product.skus, function(i, _sku) {
                                if (_sku !== sku) {
                                    _sku.sku_id = null;
                                }
                            });

                            updateProductMainPhoto( sku_mod.image_id ? sku_mod.image_id : null );
                            that.product.sku_id = sku.sku_id = sku_mod.id;

                            that.$wrapper.trigger("change");
                        }
                    },
                    modificationStatusToggle: function(sku_mod, sku_mod_index, sku, sku_index) {
                        sku_mod.status = (sku_mod.status === "enabled" ? "disabled" : "enabled");
                        that.$wrapper.trigger("change");
                    },
                    modificationAvailableToggle: function(sku_mod, sku_mod_index, sku, sku_index) {
                        sku_mod.available = !sku_mod.available;
                        that.$wrapper.trigger("change");
                    },
                    modificationStocksToggle: function(sku_mod, expanded) {
                        sku_mod.stocks_expanded = (typeof expanded === "boolean" ? expanded : !sku_mod.stocks_expanded);
                    },
                    getStockTitle: function(stock) {
                        var result = "";

                        if (stock.is_virtual) {
                            var stocks_array = [];

                            if (stock.substocks) {
                                $.each(stock.substocks, function(i, sub_stock_id) {
                                    var sub_stock = that.stocks[sub_stock_id];
                                    stocks_array.push(sub_stock.name);
                                });
                            }

                            result = that.locales["stock_title"].replace("%s", stocks_array.join(", "));
                        }

                        return result;
                    },

                    // OTHER
                    validate: function(event, type, data, key) {
                        var self = this,
                            $field = $(event.target),
                            target_value = $field.val(),
                            value = (typeof target_value === "string" ? target_value : "" + target_value);

                        value = $.wa.validate(type, value);

                        switch (type) {
                            case "price":
                                value = $.wa.validate("number", value);

                                var limit_body = 11,
                                    limit_tail = 4,
                                    parts = value.replace(",", ".").split(".");

                                var error_key = "product[skus][" + data.id + "]["+ key + "]";

                                if (parts[0].length > limit_body || (parts[1] && parts[1].length > limit_tail)) {
                                    self.errors[error_key] = {
                                        id: "price_error",
                                        text: "price_error"
                                    };
                                } else {
                                    if (self.errors[error_key]) {
                                        delete that.errors[error_key];
                                    }
                                }

                                break;
                            case "number":
                                value = $.wa.validate("number", value);
                                break;
                            case "integer":
                                value = $.wa.validate("integer", value);
                                break;
                            default:
                                break;
                        }

                        // set
                        set(value);

                        function set(value) {
                            data[key] = value;
                        }
                    }
                },
                created: function() {
                    that.$wrapper.css("visibility", "");
                },
                mounted: function() {
                    var self = this;

                    that.$wrapper.trigger("section_mounted", ["services", that]);
                }
            });

            $.vue_app.config.compilerOptions.whitespace = 'preserve';
            $.vue_app.mount($section[0]);

            function updateProductMainPhoto(image_id) {
                if (!that.product.photos.length) { return false; }

                if (!image_id) {
                    image_id = (that.product.image_id ? that.product.image_id : that.product.photos[0].id);
                }

                var photo_array = that.product.photos.filter( function(image) {
                    return (image.id === image_id);
                });

                that.product.photo = (photo_array.length ? photo_array[0] : that.product.photos[0]);
            }
        };

        Section.prototype.initFractionalSection = function() {
            var that = this;
            var $section = that.$wrapper.find(".js-fractional-section:first");

            Vue.createApp({
                data() {
                    return {
                        product: that.product,
                        keys: that.keys
                    }
                },
                components: {
                    "component-fractional-section": {
                        props: ["sku_mod"],
                        data: function() {
                            var self = this,
                                is_product = true,
                                is_simple = !that.product.normal_mode_switch;

                            if (that.product.fractional.stock_unit_id !== '0') {
                                that.product.fractional.units = that.product.fractional.units.filter(item => item.value !== '0');
                            }

                            return {
                                section: "general",
                                normal_mode: that.product.normal_mode_switch,
                                fractional: that.product.fractional,
                                errors: that.errors,
                                states: {
                                    is_product: is_product,
                                    is_locked: false
                                }
                            }
                        },
                        template: that.components["component-fractional-section"],
                        delimiters: ['{ { ', ' } }'],
                        components: {
                            "component-fractional-dropdown": {
                                props: ["units", "default_value", "show_empty", "readonly"],
                                template: that.components["component-fractional-dropdown"],
                                data: function() {
                                    var self = this,
                                        active_unit = null,
                                        max_length = 0;

                                    $.each(self.units, function(i, unit) {
                                        if (unit.value === self.default_value) {
                                            active_unit = unit;
                                        }

                                        if (unit.name.length > max_length) {
                                            max_length = unit.name.length;
                                        }
                                    });

                                    var letter_width = max_length * 9,
                                        body_width = 200;

                                    if (letter_width < 80) {
                                        body_width = 80;
                                    } else if (letter_width < 200) {
                                        body_width = letter_width + 40;
                                    }

                                    return {
                                        body_width: (body_width > 80 ? body_width : 80) + "px",
                                        active_unit: active_unit
                                    }
                                },
                                delimiters: ['{ { ', ' } }'],
                                computed: {
                                    show_empty_item: function() {
                                        var self = this;
                                        return (self.show_empty === true);
                                    }
                                },
                                mounted: function() {
                                    var self = this;

                                    var $dropdown = $(self.$el).find(".dropdown");
                                    $dropdown.waDropdown({
                                        hover: false,
                                        items: ".dropdown-item",
                                        change: function(event, target, dropdown) {
                                            var value = $(target).data("value");

                                            if (typeof value !== "undefined") {
                                                value = value + "";
                                            } else {
                                                console.error("Unit undefined");
                                                return false;
                                            }

                                            self.$emit("change", value);
                                        }
                                    });
                                }
                            },
                            "component-fractional-changes": {
                                props: ["name", "changes", "revert"],
                                template: that.components["component-fractional-changes"],
                                delimiters: ['{ { ', ' } }'],
                                computed: {
                                    prop_revert() { (typeof this.revert === "boolean" ? this.revert : false) },
                                    has_changes: function() {
                                        return this.changes[this.name];
                                    },
                                    show_indicator: function() {

                                        return (that.product.normal_mode_switch && that.product.fractional.rights[this.name] === "enabled");
                                    },
                                    show_refresh: function() {
                                        return (this.show_indicator && this.has_changes);
                                    }
                                },
                                methods: {
                                    revertMods: function(event) {
                                        var self = this;

                                        $.each(that.product.skus, function(i, sku) {
                                            $.each(sku.modifications, function(j, sku_mod) {
                                                sku_mod[self.name] = "";
                                            });
                                        });

                                        $(event.currentTarget).trigger("mouseleave");
                                        $(self.$el).trigger("change");
                                    }
                                }
                            }
                        },
                        computed: {
                            stock_unit_tooltip: function() {
                                var self = this;
                                return self.getTooltip('stock_unit_id', 'stock-unit-locked');
                            },
                            base_unit_tooltip: function() {
                                var self = this;
                                return self.getTooltip('base_unit_id', 'base-unit-locked');
                            },
                            order_multiplicity_factor_tooltip: function() {
                                var self = this;
                                return self.getTooltip('order_multiplicity_factor', 'count-denominator-locked');
                            },

                            sku_mods_changes: function() {
                                var result = {};

                                $.each(that.product.skus, function(i, sku) {
                                    $.each(sku.modifications, function(j, sku_mod) {
                                        $.each(["stock_base_ratio", "order_count_min", "order_count_step"], function(k, name) {
                                            var has_value = (parseFloat(sku_mod[name]) >= 0);
                                            result[name] = (result[name] ? result[name] : has_value);
                                        });
                                    });
                                });

                                return result;
                            },

                            show_fractional: function () {
                                var self = this,
                                    result = false;

                                $.each(self.fractional.rights, function(name, value) {
                                    if (value !== "disabled") {
                                        result = true;
                                        return false;
                                    }
                                });

                                return result;
                            },
                            show_stock_base_ratio: function() {
                                var self = this;
                                return (self.fractional.rights.base_unit_id !== 'disabled' && self.fractional.rights.stock_base_ratio !== 'disabled' && self.fractional.stock_unit_id && self.fractional.base_unit_id);
                            },
                            show_section_1: function () {
                                var self = this,
                                    result = false;

                                $.each(self.fractional.rights, function(name, value) {
                                    var white_list = ["stock_unit_id", "base_unit_id"];
                                    if (white_list.indexOf(name) >= 0 && value !== "disabled") {
                                        result = true;
                                        return false;
                                    }
                                });

                                return result;
                            },
                            show_section_2: function () {
                                var self = this,
                                    result = false;

                                $.each(self.fractional.rights, function(name, value) {
                                    var white_list = ["order_multiplicity_factor", "order_count_min", "order_count_step"];
                                    if (white_list.indexOf(name) >= 0 && value !== "disabled") {
                                        result = true;
                                        return false;
                                    }
                                });

                                return result;
                            },
                            selected_stock_unit: function () {
                                var self = this,
                                    result = null;

                                if (self.fractional.stock_unit_id) {
                                    var unit_search = self.fractional.units.filter( function(unit) {
                                        return (unit.value === self.fractional.stock_unit_id);
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

                                if (self.fractional.base_unit_id) {
                                    var unit_search = self.fractional.units.filter( function(unit) {
                                        return (unit.value === self.fractional.base_unit_id);
                                    });

                                    if (unit_search.length) {
                                        result = unit_search[0].name_short;
                                    }
                                }

                                return result;
                            }
                        },
                        methods: {
                            isReadonly: function(name) {
                                var self = this;
                                return (self.fractional.rights[name] === "readonly");
                            },
                            getTooltip: function(name, tooltip_id) {
                                var self = this,
                                    is_readonly = self.isReadonly(name);
                                return (is_readonly ? "component-fractional-" + tooltip_id : "");
                            },
                            getPlaceholder: function(name) {
                                return "";
                            },

                            onChangeStockUnit: function(value) {
                                var self = this;

                                that.product.fractional.stock_unit_id = self.fractional.stock_unit_id = value;

                                self.checkStockUnit();
                                self.checkUnits();
                            },
                            onChangeBaseUnit: function(value) {
                                var self = this;

                                that.product.fractional.base_unit_id = self.fractional.base_unit_id = value;

                                self.checkUnits();
                            },
                            onChangeCountDenominator: function(value) {
                                var self = this;

                                self.checkCountDenominator();
                                self.checkOrderCountStep();
                                self.checkOrderCountMin();
                            },
                            onChangeStockBaseRatio: function() {
                                var self = this;

                                self.checkStockBaseRatio();
                            },
                            onChangeOrderCountStep: function() {
                                var self = this;

                                self.checkOrderCountStep();
                            },
                            onChangeOrderCountMin: function() {
                                var self = this;

                                self.checkOrderCountMin();
                            },

                            checkUnits: function() {
                                var self = this,
                                    result = null;

                                var error_id = "units_error";

                                if (self.fractional.rights.stock_unit_id === "enabled" || self.fractional.rights.base_unit_id === "enabled") {
                                    var case_1 = (self.fractional.stock_unit_id && self.fractional.base_unit_id && self.fractional.stock_unit_id === self.fractional.base_unit_id);
                                    if (case_1) {
                                        self.errors[error_id] = { id: error_id, text: that.locales["units_unique_error"] };
                                        result = "units_error";
                                    } else {
                                        delete self.errors[error_id];
                                    }
                                } else {
                                    delete self.errors[error_id];
                                }

                                return result;
                            },
                            checkStockUnit: function() {
                                var self = this,
                                    result = null;

                                var error_id = "stock_unit";

                                if (self.fractional.rights.stock_unit_id === "enabled") {
                                    var case_1 = (!self.fractional.stock_unit_id);
                                    if (case_1) {
                                        self.errors[error_id] = { id: error_id, text: that.locales["stock_unit_required"] };
                                        result = "stock_unit_error";
                                    } else {
                                        delete self.errors[error_id];
                                    }
                                } else {
                                    delete self.errors[error_id];
                                }

                                return result;
                            },
                            checkStockBaseRatio: function() {
                                var self = this,
                                    result = null;

                                var error_id = "stock_base_ratio";

                                // VALIDATE
                                self.fractional.stock_base_ratio =  self.validate("number", self.fractional.stock_base_ratio);

                                if (self.fractional.rights.base_unit_id !== 'disabled' &&
                                    self.fractional.rights.stock_base_ratio === "enabled" &&
                                    self.fractional.stock_unit_id &&
                                    self.fractional.base_unit_id) {
                                    var case_1 = (!self.fractional.stock_base_ratio || !(parseFloat(self.fractional.stock_base_ratio) > 0)),
                                        case_2 = isInvalidRatio(self.fractional.stock_base_ratio);

                                    if (case_1) {
                                        self.errors[error_id] = {id: error_id, text: that.locales["stock_base_ratio_error"]};
                                        result = "stock_base_ratio_error";
                                    } else
                                    if (case_2) {
                                        self.errors[error_id] = {id: error_id, text: that.locales["stock_base_ratio_invalid"]};
                                        result = "stock_base_ratio_invalid";
                                    } else {
                                        delete self.errors[error_id];
                                    }
                                } else {
                                    delete self.errors[error_id];
                                }

                                return result;

                                function isInvalidRatio(value) {
                                    if (!(parseFloat(value) > 0)) { return true; }

                                    value = $.wa.validate("number", value);

                                    const limit_body = 8,
                                          limit_tail = 8,
                                          parts      = value.replace(",", ".").split(".");

                                    return !!(parts[0].length > limit_body || (parts[1] && parts[1].length > limit_tail));
                                }
                            },
                            checkCountDenominator: function() {
                                var self = this,
                                    result = null;

                                var error_id = "order_multiplicity_factor";

                                // VALIDATE
                                self.fractional.order_multiplicity_factor =  self.validate("number", self.fractional.order_multiplicity_factor);

                                if (self.fractional.rights.order_multiplicity_factor === "enabled") {
                                    var case_1 = (!self.fractional.order_multiplicity_factor || !checkValue(self.fractional.order_multiplicity_factor));
                                    if (case_1) {
                                        self.errors[error_id] = {id: error_id, text: that.locales["order_multiplicity_factor_required"]};
                                        result = "order_multiplicity_factor_required";
                                    } else {
                                        delete self.errors[error_id];
                                    }
                                } else {
                                    delete self.errors[error_id];
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

                                var error_id = "order_count_step";

                                // VALIDATE
                                self.fractional.order_count_step =  self.validate("number", self.fractional.order_count_step);

                                if (self.fractional.rights.order_count_step === "enabled" && self.fractional.order_multiplicity_factor) {
                                    var case_1 = (!self.fractional.order_count_step || !self.checkValue(self.fractional.order_count_step));
                                    if (case_1) {
                                        self.errors[error_id] = { id: error_id, text: that.locales["order_count_step_error"] };
                                        result = "order_count_step_error";
                                    } else {
                                        delete self.errors[error_id];
                                    }
                                } else {
                                    delete self.errors[error_id];
                                }

                                return result;
                            },
                            checkOrderCountMin: function() {
                                var self = this,
                                    result = null;

                                var error_id = "order_count_min";

                                // VALIDATE
                                self.fractional.order_count_min =  self.validate("number", self.fractional.order_count_min);

                                if (self.fractional.rights.order_count_min === "enabled" && self.fractional.order_multiplicity_factor) {
                                    var case_1 = (!self.fractional.order_count_min || !self.checkValue(self.fractional.order_count_min));
                                    if (case_1) {
                                        self.errors[error_id] = { id: error_id, text: that.locales["order_count_min_error"] };
                                        result = "order_count_min_error";
                                    } else {
                                        delete self.errors[error_id];
                                    }
                                } else {
                                    delete self.errors[error_id];
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
                                    return (self.fractional.order_multiplicity_factor ? self.fractional.order_multiplicity_factor : null);
                                }
                            },
                            validate: $.wa.validate
                        },
                        mounted: function() {
                            var self = this;
                            that.fractional_vue_model = self;
                            that.validate({ fractional_vue_model: self });
                        }
                    }
                },
                delimiters: ['{ { ', ' } }'],
                created: function() {
                    $section.css("visibility", "");
                }
            }).mount($section[0]);
        };

        Section.prototype.initTypeSection = function() {
            var that = this;

            var $section = that.$wrapper.find(".js-product-type-section");
            if (!$section.length) { return false; }

            var $select = $section.find(".js-product-type-select"),
                $link = $section.find(".js-setup-type-link"),
                $input = $select.find('input[name="product[type_id]"]');

            var href = $link.data("href");

            $select.waDropdown({
                hover: false,
                items: ".dropdown-item",
                change: function(event, target, dropdown) {
                    var id = ($(target).data("id") || "");
                    $link.attr("href", href.replace("%type_id%", id));
                    $input.val(id).trigger("change");
                    changeType(id);
                }
            });

            function changeType(type_id) {
                var locked_class = "is-locked";

                $section.addClass(locked_class);
                that.fractional_vue_model.states.is_locked = true;

                getTypeData(type_id)
                    .always( function() {
                        $section.removeClass(locked_class);
                        that.fractional_vue_model.states.is_locked = false;
                    })
                    .done( function(response) {
                        updateProductFraction(response.data);
                    });

                function getTypeData(type_id) {
                    return $.post(that.urls["type_settings"], { type_id: type_id }, "json");
                }

                function updateProductFraction(type_fractional) {
                    that.product.fractional.units = type_fractional.units;
                    that.product.fractional.rights = type_fractional.rights;
                    that.product.fractional.denominators = type_fractional.denominators;
                    that.product.fractional.base_unit_id = type_fractional.base_unit_id;
                    that.product.fractional.stock_unit_id = type_fractional.stock_unit_id;
                    that.product.fractional.stock_base_ratio = formatValue(type_fractional.stock_base_ratio);
                    that.product.fractional.order_multiplicity_factor = formatValue(type_fractional.order_multiplicity_factor);
                    that.product.fractional.order_count_step = formatValue(type_fractional.order_count_step);
                    that.product.fractional.order_count_min = formatValue(type_fractional.order_count_min);
                    that.keys.fractional += 1;

                    function formatValue(value) {
                        var result = value;

                        var float_value = $.wa.validate("float", value);
                        if (float_value > 0) {
                            result = $.wa.validate("number", float_value); }
                        else {
                            result = "1";
                        }

                        return result;
                    }
                }
            }
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
                $input = $select.find('input[name="product[category_id]"]'),
                $button_remove = $section.find(".js-main-category-remove");

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
                        $button_remove.addClass('hidden');
                    } else {
                        $button_remove.removeClass('hidden');
                    }
                }
            });
            var dropdown = $select.waDropdown("dropdown");

            that.$wrapper.on("category.added", function(event, category) {
                renderCategory(category);
            });

            if ($input.val()) {
                $button_remove.removeClass('hidden');
            }

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

            $button_remove.on("click", function(e) {
                e.preventDefault();

                var $additional_categories_list = $(".s-additional-categories-section .js-categories-list");
                $additional_categories_list.find(".is-main").remove();

                var $additional_categories = $additional_categories_list.find('> [data-id]');
                if ($additional_categories.length > 0) {
                    var $category =$additional_categories.first();
                    $category.addClass('is-main');
                    dropdown.setValue('id', String($category.data('id')));
                } else {
                    dropdown.setTitle(that.locales["select_category"]);
                    $input.val(null).trigger("change");
                    $(this).addClass('hidden');
                }
            });
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
                updateTitleButtonRemoveMainCategory();
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

            function updateTitleButtonRemoveMainCategory() {
                var $button_remove = that.$wrapper.find(".js-main-category-remove");
                var locale_remove = that.locales["remove_main_category"];

                if ($list.find(".s-category-wrapper:not(.is-main)").length > 0) {
                    locale_remove = that.locales["take_main_category_from_additional"];
                }

                $button_remove.attr('data-title', locale_remove);
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

        Section.prototype.initEditor = function() {
            var that = this;

            var $section = that.$wrapper.find(".js-editor-section"),
                $textarea = $section.find(".js-product-description-textarea"),
                $html_wrapper = $section.find(".js-html-editor"),
                $wysiwyg_wrapper = $section.find(".js-wysiwyg-editor");

            var html_editor = null,
                wysiwyg_redactor = null,
                active_type_id = activeType(),
                confirmed = false;

            if (that.can_use_smarty) {
                const val = String($textarea.val()).trim();
                if (val && $.wa_shop_products.containsSmartyCode(val)) {
                    active_type_id = 'html';
                }
            }

            let editor_type_initialized = false;
            $section.find(".js-editor-type-toggle").waToggle({
                type: "tabs",
                ready: function(toggle) {
                    toggle.$wrapper.find("[data-id=\"" + active_type_id + "\"]:first").trigger("click");
                },
                change: function(event, target, toggle) {
                    onTypeChange($(target).data("id"));
                }
            });

            onTypeChange(active_type_id);
            editor_type_initialized = true;

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
                        lang: that.lang,
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
                        imageTypes: ['image/png', 'image/jpeg', 'image/gif', 'image/webp'],
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
                    $.showModificationWysiwygConfirm().done(function () {
                        changeType(type_id);
                        confirmed = true;
                    }).fail(function () {
                        $section.find('.js-editor-type-toggle [data-id="html"]').trigger('click');
                    });
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

                            if (editor_type_initialized || (html_editor.session.getValue() || '').trim() !== $textarea.val().trim()) {
                                html_editor.session.setValue($textarea.val());
                            }
                            activeType(type_id);
                            break;
                        case "wysiwyg":
                            $html_wrapper.hide();
                            $wysiwyg_wrapper.show();
                            if (!wysiwyg_redactor) {
                                wysiwyg_redactor = initRedactor($wysiwyg_wrapper, $textarea);
                            }
                            $textarea.redactor("code.set", $textarea.val());
                            if (editor_type_initialized) {
                                $textarea.change();
                            }
                            activeType(type_id);
                            break;
                        default:
                            break;
                    }
                    active_type_id = type_id;
                }
            }

            function activeType(value) {
                var white_list = ["wysiwyg", "html"];

                value = (typeof value === "string" ? value : null);

                if (value) {
                    if (white_list.indexOf(value) >= 0) {
                        localStorage.setItem("shop/editor", value);
                    }
                } else {
                    var result = white_list[0];

                    var storage = localStorage.getItem("shop/editor");
                    if (storage && (white_list.indexOf(storage) >= 0)) {
                        result = storage;
                    }

                    return result;
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

        //

        Section.prototype.initSave = function() {
            var that= this,
                $form = that.$form,
                $submit_button = that.$wrapper.find(".js-product-save");

            var loading = "<span class=\"icon top\" style=\"margin-left: .5rem\"><i class=\"fas fa-spinner fa-spin\"></i></span>";

            $form.on("submit", function(event) {
                event.preventDefault();
            });

            $submit_button.on("click", function(event, options) {
                options = (options || {});
                $form.trigger("submit");
                onSubmit(options);
            });

            function onSubmit(options) {
                if (!that.is_locked) {
                    that.is_locked = true;

                    var vue_errors = that.validate({ before_submit: true }),
                        form_data = getData(),
                        no_errors = beforeSavePluginHook(form_data);

                    var vue_has_errors = (vue_errors.length || Object.keys(that.errors).length > 0);
                    if (vue_has_errors) {
                        console.log( vue_errors );
                        // Есть разница во времени рендера Vue ошибок. Скролл к ошибке следует запускать чуть позже
                        setTimeout( function() {
                            var $errors = that.$wrapper.find(".state-error-hint");
                            if ($errors.length > 0) {
                                $(window).scrollTop( $errors.first().offset().top - 128 );
                                that.is_locked = false;
                            }
                        }, 200);

                    } else if (!no_errors || form_data.errors.length) {
                        that.renderErrors(form_data.errors);
                        that.is_locked = false;

                    } else {

                        var $loading = $(loading).appendTo($submit_button.attr("disabled", true));

                        request(that.urls["save"], form_data.data)
                            .done( function(server_data) {
                                var product_id = server_data.id;
                                if (product_id) {
                                    that.$wrapper.trigger("change_product_name", [server_data.name]);
                                    var is_new = location.href.indexOf("/new/general/") >= 0;
                                    if (is_new) {
                                        var url = location.href.replace("/new/general/", "/"+product_id+"/general/");
                                        history.replaceState(null, null, url);
                                        that.$wrapper.trigger("product_created", [product_id]);
                                    }
                                }
                                if (options.redirect_url) {
                                    var redirect_url = options.redirect_url.replace("/new/", "/"+product_id+"/");
                                    $.wa_shop_products.router.load(redirect_url).fail( function() {
                                        location.href = redirect_url;
                                    });
                                } else {
                                    $.wa_shop_products.router.reload();
                                }

                                afterSavePluginHook(form_data.data, server_data);
                            })
                            .fail(function(reason, errors) {
                                $loading.remove();
                                $submit_button.attr("disabled", false);
                                that.is_locked = false;

                                afterSaveFailPluginHook(form_data.data, reason == 'errors' ? clone(errors) : []);

                                if (reason == 'errors' && errors && errors.length) {
                                    var error_search = errors.filter( error => (error.id === "not_found"));
                                    if (error_search.length) { $.wa_shop_products.showDeletedProductDialog(); }

                                    that.renderErrors(errors);
                                }
                            });

                        savePluginHook(form_data);

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
                        if (item.name === "product[url]") {
                            item.value = item.value.replace(/\//g, "");
                        } else if (item.name === "product[name]") {
                            if (item.value.length > 255) {
                                result.errors.push({
                                    id: "product_name_length_error",
                                    text: ""
                                });
                            }
                        }
                        if (item.name === "product[sets][]") {
                            sets_is_set = true;
                        }
                        if (item.name === "product[name]") {
                            if (!item.value.length) {
                                result.errors.push({
                                    id: "product_name_required",
                                    text: ""
                                });
                                that.$wrapper.trigger("product-name-error");
                            }
                        }

                        result.data.push(item);
                    });

                    if (!sets_is_set) {
                        result.data.push({
                            name: "product[sets]",
                            value: ""
                        });
                    }

                    var product_data = getProductData();

                    result.data = [].concat(result.data, product_data);

                    return result;

                    function getProductData() {
                        var data = [
                            {
                                "name": "product[currency]",
                                "value": that.product.currency
                            },
                            {
                                "name": "product[params][multiple_sku]",
                                "value": (that.product.normal_mode_switch ? 1 : 0)
                            },
                            {
                                name: "product[base_unit_id]",
                                value: that.product.fractional.base_unit_id
                            },
                            {
                                name: "product[stock_unit_id]",
                                value: that.product.fractional.stock_unit_id
                            },
                            {
                                name: "product[order_multiplicity_factor]",
                                value: that.product.fractional.order_multiplicity_factor
                            },
                            {
                                name: "product[order_count_min]",
                                value: that.product.fractional.order_count_min
                            },
                            {
                                name: "product[order_count_step]",
                                value: that.product.fractional.order_count_step
                            },
                            {
                                name: "product[stock_base_ratio]",
                                value: that.product.fractional.stock_base_ratio
                            }
                        ];

                        setBadge();

                        setSKUS();

                        return data;

                        function setBadge() {
                            var value = "";

                            if (typeof that.product.badge_id === "string") {
                                var active_badges = that.product.badges.filter( function(badge) {
                                    return (badge.id === that.product.badge_id);
                                });

                                if (active_badges.length) {
                                    var active_badge = active_badges[0];
                                    value = (active_badge.id === "" ? active_badge.code : active_badge.id);
                                }
                            }

                            data.push({
                                name: "product[badge]",
                                value: value
                            });
                        }

                        function setSKUS() {
                            $.each(that.product.skus, function(i, sku) {
                                $.each(sku.modifications, function(i, sku_mod) {
                                    var prefix = "product[skus][" + sku_mod.id + "]";
                                    data.push({
                                        name: prefix + "[name]",
                                        value: sku_mod.name
                                    });
                                    data.push({
                                        name: prefix + "[sku]",
                                        value: sku_mod.sku
                                    });
                                    data.push({
                                        name: prefix + "[status]",
                                        value: (sku_mod.status ? 1 : 0)
                                    });
                                    data.push({
                                        name: prefix + "[available]",
                                        value: (sku_mod.available ? 1 : 0)
                                    });
                                    data.push({
                                        name: prefix + "[image_id]",
                                        value: sku_mod.image_id
                                    });
                                    data.push({
                                        name: prefix + "[price]",
                                        value: sku_mod.price
                                    });
                                    data.push({
                                        name: prefix + "[compare_price]",
                                        value: sku_mod.compare_price
                                    });
                                    data.push({
                                        name: prefix + "[purchase_price]",
                                        value: sku_mod.purchase_price
                                    });
                                    data.push({
                                        name: prefix + "[order_count_min]",
                                        value: (that.product.normal_mode_switch ? sku_mod.order_count_min : "")
                                    });
                                    data.push({
                                        name: prefix + "[order_count_step]",
                                        value: (that.product.normal_mode_switch ? sku_mod.order_count_step : "")
                                    });
                                    data.push({
                                        name: prefix + "[stock_base_ratio]",
                                        value: (that.product.normal_mode_switch ? sku_mod.stock_base_ratio : "")
                                    });

                                    if (sku_mod.file && sku_mod.file.id) {
                                        data.push({
                                            name: prefix + "[file_description]",
                                            value: sku_mod.file.description
                                        });
                                    }

                                    setStocks(sku_mod, prefix);
                                });
                            });

                            function setStocks(sku_mod, prefix) {
                                var stocks_data = [];

                                var is_stocks_mode = false;

                                $.each(sku_mod.stock, function(stock_id, stock_value) {
                                    is_stocks_mode = true;

                                    var value = parseFloat(stock_value);
                                    if (isNaN(value)) { value = ""; }

                                    stocks_data.push({
                                        name: prefix + "[stock][" + stock_id + "]",
                                        value: value
                                    });
                                });

                                if (!is_stocks_mode) {
                                    stocks_data = [{
                                        name: prefix + "[stock][0]",
                                        value: sku_mod.count
                                    }];
                                }

                                data = data.concat(stocks_data);
                            }
                        }
                    }
                }

                /**
                 * Event allows to perform validation before sending data to server.
                 * To show a generic message below the form:

                   event.form_errors.push({
                     text: 'Please correct errors'
                   })

                 * `event` being jQuery event that triggered the hook.
                 *
                 * You may show validation message directly below your fields, too.
                 * In this case, if you choose not to add data.errors, cancel form submit like this:

                   event.preventDefault();

                 *
                 * You may add data to be sent to ProdSaveGeneral controller like this example:

                   event.form_data.push({
                        name: "yourplugin[key]",
                        value: "value"
                   });

                 * You may use any name. Whatever you send has to be processed on the server,
                 * see PHP hooks `backend_prod_presave`, `backend_prod_save`
                 */
                function beforeSavePluginHook(data) {
                    return triggerHook($.Event('wa_before_save', {
                        product_id: that.product.id,
                        section_controller: that,
                        form_errors: data.errors,
                        form_data: data.data
                    }));
                }

                /**
                 * Triggered just after form data are sent to server (ProdSaveGeneral controller).
                 * This may be a good place to send data to your own plugin PHP controller.
                 * Note that for new products, product_id may not be known at this time.
                 */
                function savePluginHook(data) {
                    triggerHook($.Event('wa_save', {
                        product_id: that.product.id,
                        section_controller: that,
                        form_data: data.data
                    }));
                }

                /**
                 * Triggers `wa_after_save` after a successfull save (ProdSaveGeneral controller).
                 *
                 * Successfull event contains `server_data` and contains no `server_errors`.
                 *
                 * Can be used to send additional data to plugin's own controllers.
                 * Product_id is always known at this time, even for new products.
                 */
                function afterSavePluginHook(form_data, server_data) {
                    triggerHook($.Event('wa_after_save', {
                        product_id: that.product.id || (server_data && server_data.data && server_data.data.id),
                        section_controller: that,
                        server_data: server_data,
                        form_data: form_data
                    }));
                }

                /**
                 * Triggers `wa_after_save` when server controller returned validation errors.
                 *
                 * Unsuccessfull event contains `server_errors` and contains no `server_data` key.
                 *
                 * Successfull event will contain `server_data` and contain no `server_errors`.
                 * Use this to show custom validation message returned by your plugin via `backend_prod_presave`.
                 */
                function afterSaveFailPluginHook(form_data, server_errors) {
                    triggerHook($.Event('wa_after_save', {
                        product_id: that.product.id,
                        section_controller: that,
                        server_errors: server_errors,
                        form_data: form_data
                    }));
                }

                function triggerHook(event) {
                    try {
                        $('#js-product-general-section').trigger(event);
                    } catch(e) {
                        console.log(e);
                    }
                    return !event.isDefaultPrevented();
                }

                function request(href, data) {
                    var deferred = $.Deferred();

                    $.post(href, data, "json")
                        .done( function(response) {
                            if (response.status === "ok") {
                                deferred.resolve(response.data);
                            } else {
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

        Section.prototype.validate = function(options) {
            options = (typeof options !== "undefined" ? options : {});

            var that = this;

            var errors = [];

            // Проверка ошибок секции с дробностью
            var fractional_vue_model = (typeof options["fractional_vue_model"] !== "undefined" ? options["fractional_vue_model"] : that.fractional_vue_model);
            if (fractional_vue_model) {
                if (options.before_submit) {
                    var stock_unit_error = fractional_vue_model.checkStockUnit();
                    if (stock_unit_error) { errors.push(stock_unit_error); }

                    var order_multiplicity_factor_error = fractional_vue_model.checkCountDenominator();
                    if (order_multiplicity_factor_error) { errors.push(order_multiplicity_factor_error); }
                }

                var unit_error = fractional_vue_model.checkUnits();
                if (unit_error) { errors.push(unit_error); }

                var ratio_error = fractional_vue_model.checkStockBaseRatio();
                if (ratio_error) { errors.push(ratio_error); }

                var step_error = fractional_vue_model.checkOrderCountStep();
                if (step_error) { errors.push(step_error); }

                var min_error = fractional_vue_model.checkOrderCountMin();
                if (min_error) { errors.push(min_error); }
            }

            return errors;
        };

        // VUE Methods

        Section.prototype.updateModificationStocks = function(sku_mod) {
            var that = this;

            if (sku_mod) {
                update(sku_mod);
            } else {
                $.each(that.product.skus, function(i, sku) {
                    $.each(sku.modifications, function(i, sku_mod) {
                        update(sku_mod);
                    });
                });
            }

            function update(sku_mod) {
                var is_good = false;
                var is_warn = false;
                var is_critical = false;

                var stocks_count = 0,
                    is_set = false;

                $.each(sku_mod.stock, function(stock_id, stock_value) {
                    var value = parseFloat(stock_value);

                    var stock_data = that.stocks[stock_id];
                    if (stock_data && stock_data.is_virtual) { return true; }

                    if (!isNaN(value)) {
                        is_set = true;
                        stocks_count += value;

                        var stock = that.stocks[stock_id];
                        if (value > stock.critical_count) {
                            if (value > stock.low_count) {
                                is_good = true;
                            } else {
                                is_warn = true;
                            }
                        } else {
                            is_critical = true;
                        }
                    } else {
                        is_set = false;
                        return false;
                    }
                });

                if (is_critical) {
                    sku_mod.stocks_indicator = -1;
                } else if (is_warn) {
                    sku_mod.stocks_indicator = 0;
                } else {
                    sku_mod.stocks_indicator = 1;
                }

                if (is_set) {
                    sku_mod.count = $.wa.validate("float", stocks_count);
                }
            }
        };

        Section.prototype.initPhotoManagerDialog = function($dialog, dialog, sku_mod) {
            var that = this;

            var $vue_section = $dialog.find("#vue-photo-manager-section");

            var photo_id = sku_mod.image_id,
                active_photo = null,
                photos = clone(that.product.photos);

            photos.forEach( function(photo) {
                photo = formatPhoto(photo);
                if (photo_id && photo_id === photo.id) { active_photo = photo; }
            });

            if (!active_photo && photos.length) {
                active_photo = photos[0];
            }

            if (!that.product.normal_mode && active_photo && photos.indexOf(active_photo) > 0) {
                photos.splice(photos.indexOf(active_photo), 1);
                photos.unshift(active_photo);
            }

            Vue.createApp({
                data() {
                    return {
                        photo_id: photo_id,
                        photos: photos,
                        active_photo: active_photo,
                        files: [],
                        errors: []
                    }
                },
                delimiters: ['{ { ', ' } }'],
                components: {
                    "component-loading-file": {
                        props: ['file'],
                        template: '<div class="vue-component-loading-file"><div class="wa-progressbar" style="display: inline-block;"></div></div>',
                        mounted: function() {
                            var self = this;

                            var $wrapper = $(self.$el);

                            var $bar = $wrapper.find(".wa-progressbar").waProgressbar({ type: "circle", "stroke-width": 4.8, display_text: false }),
                                instance = $bar.data("progressbar");

                            var $content = $dialog.find(".dialog-content");
                            self.$nextTick( function() {
                                $content
                                    .addClass("is-scroll-animated")
                                    .scrollTop( $content[0].offsetHeight + 1000 )
                                    .removeClass("is-scroll-animated");
                            });

                            loadPhoto(self.file)
                                .done( function(response) {
                                    $.each(response.files, function(i, photo) {
                                        // remove loading photo
                                        self.$emit("photo_added", {
                                            file: self.file,
                                            photo: photo
                                        });
                                    });
                                });

                            function loadPhoto(file) {
                                var formData = new FormData();

                                formData.append("product_id", that.product.id);
                                formData.append("files", file);

                                // Ajax request
                                return $.ajax({
                                    xhr: function() {
                                        var xhr = new window.XMLHttpRequest();
                                        xhr.upload.addEventListener("progress", function(event){
                                            if (event.lengthComputable) {
                                                var percent = parseInt( (event.loaded / event.total) * 100 );
                                                instance.set({ percentage: percent });
                                            }
                                        }, false);
                                        return xhr;
                                    },
                                    url: that.urls["add_product_image"],
                                    data: formData,
                                    cache: false,
                                    contentType: false,
                                    processData: false,
                                    type: 'POST'
                                });
                            }
                        }
                    }
                },
                methods: {
                    setPhoto: function(photo) {
                        var self = this;
                        self.active_photo = photo;
                    },
                    useChanges: function() {
                        var self = this;
                        if (self.active_photo) {
                            dialog.options.onSuccess(self.active_photo.id);
                            dialog.close();
                        }
                    },
                    showDescriptionForm: function(event, photo) {
                        var self = this;

                        var $photo = $(event.currentTarget).closest(".s-photo-wrapper");

                        photo.expanded = true;

                        self.$nextTick( function() {
                            var $textarea = $photo.find("textarea:first");
                            if ($textarea) {
                                $textarea.trigger("focus");
                            }
                        });
                    },
                    changeDescription: function(event, photo) {
                        var $button = $(event.currentTarget);
                        $button.attr("disabled", true);

                        var href = that.urls["change_image_description"],
                            data = {
                                "id": photo.id,
                                "description": photo.description
                            }

                        $.post(href, data, "json")
                            .always( function() {
                                $button.attr("disabled", false);
                            })
                            .done( function() {
                                photo.description_before = photo.description;
                                photo.expanded = false;
                            });
                    },
                    revertDescription: function(photo) {
                        photo.expanded = false;
                        photo.description = photo.description_before;
                    },
                    onAreaOver: function(event) {
                        var $area = $(event.currentTarget);

                        var active_class = "is-over";

                        var timer = $area.data("timer");
                        if (typeof timer === "number") { clearTimeout(timer); }

                        $area.addClass(active_class);
                        timer = setTimeout( clear, 100);
                        $area.data("timer", timer);

                        function clear() {
                            $area.removeClass(active_class);
                        }
                    },
                    onAreaDrop: function(event) {
                        var self = this,
                            files = event.dataTransfer.files;

                        if (files.length) {
                            $.each(files, function(i, file) {
                                self.loadFile(file);
                            });
                        }
                    },
                    onAreaChange: function(event) {
                        var self = this,
                            files = event.target.files;

                        if (files.length) {
                            $.each(files, function(i, file) {
                                self.loadFile(file);
                            });
                        }

                        // clear
                        $(event.target).val("");
                    },
                    onAddedPhoto: function(data) {
                        var self = this;

                        var photo = formatPhoto(data.photo);

                        // удаляем UI загрузки
                        var index = self.files.indexOf(data.file);
                        if (index >= 0) { self.files.splice(index, 1); }

                        // Добавляем фотку в модели данных
                        self.photos.push(photo);
                        dialog.options.onPhotoAdd(photo);

                        self.setPhoto(photo);
                    },

                    //
                    loadFile: function(file) {
                        var self = this;

                        var file_size = file.size,
                            image_type = /^image\/(png|jpe?g|gif|webp)$/,
                            is_image_type = (file.type.match(image_type)),
                            is_image = false;

                        var name_array = file.name.split("."),
                            ext = name_array[name_array.length - 1];

                        ext = ext.toLowerCase();

                        var white_list = ["png", "jpg", "jpeg", "gif", "webp"];
                        if (is_image_type && white_list.indexOf(ext) >= 0) {
                            is_image = true;
                        }

                        if (!is_image) {
                            renderError({ id: "not_image", text: "ERROR: NOT IMAGE" });
                        } else if (file_size >= that.max_file_size) {
                            renderError({ id: "big_size", text: "ERROR: big file size" });
                        } else if (file_size >= that.max_post_size) {
                            renderError({ id: "big_post", text: "ERROR: big POST file size" });
                        } else {
                            file.id = that.getUniqueIndex("file_load_id");
                            self.files.push(file);
                        }

                        function renderError(error) {
                            self.errors.push(error);

                            setTimeout( function() {
                                var index = null;
                                $.each(self.errors, function(i, _error) {
                                    if (_error === error) { index = i; return false; }
                                });
                                if (typeof index === "number") { self.errors.splice(index, 1); }
                            }, 2000);
                        }
                    }
                },
                created: function () {
                    $vue_section.css("visibility", "");
                },
                updated: function() {
                    var self = this;

                    $dialog.find("textarea.s-description-field").each( function() {
                        toggleHeight( $(this) );
                    });

                    var $content = $dialog.find(".dialog-content"),
                        content_top = $content.scrollTop();

                    dialog.resize();
                    $content.scrollTop(content_top);
                },
                mounted: function () {
                    var self = this;

                    dialog.resize();
                }
            }).mount($vue_section[0]);

            function formatPhoto(photo) {
                photo.expanded = false;
                if (typeof photo.description !== "string") { photo.description = ""; }
                photo.description_before = photo.description;
                return photo;
            }

            function toggleHeight($textarea) {
                $textarea.css("min-height", 0);
                var scroll_h = $textarea[0].scrollHeight;
                $textarea.css("min-height", scroll_h + "px");
            }
        };

        Section.prototype.getUniqueIndex = function(name, iterator) {
            var that = this;

            name = (typeof name === "string" ? name : "") + "_index";
            iterator = (typeof iterator === "number" ? iterator : 1);

            if (typeof that.getUniqueIndex[name] !== "number") { that.getUniqueIndex[name] = 0; }

            that.getUniqueIndex[name] += iterator;

            return that.getUniqueIndex[name];
        };

        return Section;

        function clone(data) {
            return JSON.parse(JSON.stringify(data));
        }

    })($);

    $.wa_shop_products.init.initProductGeneralSection = function(options) {
        return new Section(options);
    };

})(jQuery);
