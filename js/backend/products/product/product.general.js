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
            that.tooltips = options["tooltips"];
            that.locales = options["locales"];
            that.urls = options["urls"];
            that.lang = options["lang"];

            //
            that.max_file_size = options["max_file_size"];
            that.max_post_size = options["max_post_size"];

            // VUE JS MODELS
            that.stocks_array = options["stocks"];
            that.stocks = $.wa.construct(that.stocks_array, "id");
            that.product = formatProduct(options["product"]);
            that.currencies = options["currencies"];
            that.errors = {
                // сюда будут добавться точечные ключи ошибок
                // ключ global содержит массив с общими ошибками страницы
                global: []
            };

            // DYNAMIC VARS
            that.categories_tree = options["categories_tree"];
            that.categories = formatCategories(that.categories_tree);
            that.product_category_id = options["product_category_id"];
            that.is_changed = false;
            that.is_locked = false;

            console.log( that );

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

                if (typeof sku_mod.stock !== "object" || Array.isArray(sku_mod.stock)) {
                    sku_mod.stocks_mode = false;
                    sku_mod.stock = {};
                }

                $.each(stocks, function(stock_id, stock_count) {
                    if (typeof sku_mod.stock[stock_id] === "undefined") {
                        sku_mod.stock[stock_id] = "";
                    }
                });

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

                        sku_mod.stock[stock.id] = value;
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

        Section.prototype.initSkuSection = function() {
            var that = this;

            var $section = that.$wrapper.find("#vue-sku-section");

            var vue_model = new Vue({
                el: $section[0],
                data: {
                    errors: that.errors,
                    stocks: that.stocks,
                    stocks_array: that.stocks_array,
                    product: that.product,
                    currencies: that.currencies
                },
                components: {
                    "component-switch": {
                        props: ["value", "disabled"],
                        data: function() {
                            return {
                                checked: (typeof this.value === "boolean" ? this.value : false),
                                disabled: (typeof this.disabled === "boolean" ? this.disabled : false)
                            };
                        },
                        template: '<div class="switch"><input type="checkbox" v-bind:checked="checked" v-bind:disabled="disabled"></div>',
                        delimiters: ['{ { ', ' } }'],
                        mounted: function() {
                            var self = this;

                            $(self.$el).waSwitch({
                                change: function(active, wa_switch) {
                                    self.$emit("change", active);
                                    self.$emit("input", active);
                                }
                            });
                        }
                    },
                    "component-dropdown-currency": {
                        props: ["currency_code", "currencies", "wide"],
                        template: that.templates["component-dropdown-currency"],
                        data: function() {
                            return {
                                wide: (typeof wide === "boolean" ? wide : false)
                            }
                        },
                        delimiters: ['{ { ', ' } }'],
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
                            var self = this;
                            return {
                                "badge": self.product.badges[self.product.badges.length - 1]
                            }
                        },
                        template: that.templates["component-product-badge-form"],
                        methods: {
                            updateForm: function() {
                                var self = this;

                                self.badge.code = self.badge.code_model;
                                self.product.badge_form = false;

                                that.$wrapper.trigger("change");
                            },
                            revertForm: function() {
                                var self = this;

                                if (self.product.badge_prev_id) {
                                    self.product.badge_id = self.product.badge_prev_id;
                                    that.$wrapper.trigger("change");
                                }

                                self.product.badge_form = false;
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
                                props: ["value", "placeholder"],
                                template: '<textarea v-bind:placeholder="placeholder" v-bind:value="value" v-on:input="$emit(\'input\', $event.target.value)"></textarea>',
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

                                            new Vue({
                                                el: $section[0],
                                                delimiters: ['{ { ', ' } }'],
                                                created: function() {
                                                    $section.css("visibility", "");
                                                },
                                                mounted: function() {
                                                    dialog.resize();
                                                }
                                            });

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
                    }
                },
                methods: {
                    // Misc
                    changeProductMode: function(event) {
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
                        var self = this;

                        self.$set(sku_mod, "photo", photo);
                        self.$set(sku_mod, "image_id", photo.id);
                        that.$wrapper.trigger("change");
                    },
                    removeProductPhoto: function(sku_mod) {
                        var self = this;

                        $.waDialog({
                            html: that.templates["dialog_sku_delete_photo"],
                            options: {
                                onUnpin: function() {
                                    self.$set(sku_mod, "photo", null);
                                    self.$set(sku_mod, "image_id", null);
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
                                                self.$set(_sku_mod, "photo", null);
                                                self.$set(_sku_mod, "image_id", null);
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

                            sku_mod.stock[stock.id] = value;
                        });

                        sku_mod.stocks_mode = is_set;
                        sku_mod.count = (is_set && !is_infinite ? stocks_count : "");

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
                                    limit_tail = 3,
                                    parts = value.replace(",", ".").split(".");

                                var error_key = "product[skus][" + data.id + "]["+ key + "]";

                                if (parts[0].length > limit_body || (parts[1] && parts[1].length > limit_tail)) {
                                    self.$set(self.errors, error_key, {
                                        id: "price_error",
                                        text: "price_error"
                                    });
                                } else {
                                    if (self.errors[error_key]) {
                                        self.$delete(that.errors, error_key);
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
                            Vue.set(data, key, value);
                        }
                    }
                },
                created: function() {
                    that.$wrapper.css("visibility", "");
                },
                mounted: function() {

                }
            });

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

        Section.prototype.initTypeSection = function() {
            var that = this;

            var $section = that.$wrapper.find(".js-product-type-section");
            if (!$section.length) { return false; }

            var $select = $section.find(".js-product-type-select"),
                $link = $section.find(".js-setup-type-link"),
                $input = $select.find("input");

            var href = $link.data("href");

            $select.waDropdown({
                hover: false,
                items: ".dropdown-item",
                change: function(event, target, dropdown) {
                    var id = ($(target).data("id") || "");
                    $link.attr("href", href.replace("%type_id%", id));
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

                    var form_data = getData();

                    var no_errors = beforeSavePluginHook(form_data);

                    if (!no_errors || form_data.errors.length) {
                        that.renderErrors(form_data.errors);
                        that.is_locked = false;

                    } else {

                        var $loading = $(loading).appendTo($submit_button.attr("disabled", true));

                        request(that.urls["save"], form_data.data)
                            .done( function(server_data) {
                                if (options.redirect_url) {
                                    $.wa_shop_products.router.load(options.redirect_url).fail( function() {
                                        location.href = options.redirect_url;
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

                                afterSaveFailPluginHook(form_data.data, reason == 'errors' ? errors : []);

                                if (reason == 'errors' && errors && errors.length) {
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
                                    var value = parseFloat(stock_value);
                                    if (value >= 0) {
                                        is_stocks_mode = true;
                                    } else {
                                        value = "";
                                    }

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
                    sku_mod.count = stocks_count;
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

            new Vue({
                el: $vue_section[0],
                data: {
                    photo_id: photo_id,
                    photos: photos,
                    active_photo: active_photo,
                    files: [],
                    errors: []
                },
                delimiters: ['{ { ', ' } }'],
                components: {
                    "component-loading-file": {
                        props: ['file'],
                        template: '<div class="vue-component-loading-file"><div class="wa-progressbar" style="display: inline-block;"></div></div>',
                        mounted: function() {
                            var self = this;

                            var $bar = $(self.$el).find(".wa-progressbar").waProgressbar({ type: "circle", "stroke-width": 4.8, display_text: false }),
                                instance = $bar.data("progressbar");

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
                                "data[description]": photo.description
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
                        self.photos.unshift(photo);
                        dialog.options.onPhotoAdd(photo);

                        self.setPhoto(photo);
                    },

                    //
                    loadFile: function(file) {
                        var self = this;

                        var file_size = file.size,
                            image_type = /^image\/(png|jpe?g|gif)$/,
                            is_image_type = (file.type.match(image_type)),
                            is_image = false;

                        var name_array = file.name.split("."),
                            ext = name_array[name_array.length - 1];

                        ext = ext.toLowerCase();

                        var white_list = ["png", "jpg", "jpeg", "gif"];
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
            });

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

        return Section;

        function clone(data) {
            return JSON.parse(JSON.stringify(data));
        }

    })($);

    $.wa_shop_products.init.initProductGeneralSection = function(options) {
        return new Section(options);
    };

})(jQuery);