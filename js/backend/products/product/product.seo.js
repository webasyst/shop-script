( function($) {

    var Section = ( function($) {

        Section = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];

            // CONST
            that.components = options["components"];
            that.templates = options["templates"];
            that.tooltips = options["tooltips"];
            that.locales = options["locales"];
            that.urls = options["urls"];

            // VUE JS MODELS
            that.product = options["product"];
            that.social = options["social"];
            that.errors = {};
            that.errors_global = [];

            // DYNAMIC VARS

            // INIT
            that.vue_model = that.initVue();
            that.init();
        };

        Section.prototype.init = function() {
            var that = this;

            var page_promise = that.$wrapper.closest(".s-product-page").data("ready");
            page_promise.done( function(product_page) {
                var $footer = that.$wrapper.find(".js-sticky-footer");
                product_page.initProductDelete($footer);
                product_page.initStickyFooter($footer);
                updateURL(that.product.id);

                function updateURL(product_id) {
                    if (product_id) {
                        var is_new = location.href.indexOf("/new/") >= 0;
                        if (is_new) {
                            var url = location.href.replace("/new/", "/"+product_id+"/");
                            history.replaceState(null, null, url);
                            that.$wrapper.trigger("product_created", [product_id]);
                        }
                    }
                }
            });

            that.initSave();

            $.each(that.tooltips, function(i, tooltip) {
                $.wa.new.Tooltip(tooltip);
            });
        };

        Section.prototype.initVue = function() {
            var that = this;

            if (typeof $.vue_app === "object" && typeof $.vue_app.unmount === "function") {
                $.vue_app.unmount();
            }

            // DOM
            var $view_section = that.$wrapper.find(".js-product-seo-section");

            var vue_model = Vue.createApp({
                data() {
                    return {
                        errors       : that.errors,
                        errors_global: that.errors_global,
                        product      : that.product,
                        social       : that.social
                    }
                },
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

                            self.$emit("ready", self.$el.value);
                        }
                    },
                    "component-dropdown-variables": {
                        template: that.components["component-dropdown-variables"],
                        delimiters: ['{ { ', ' } }'],
                        mounted: function() {
                            var self = this;

                            $(self.$el).waDropdown({
                                hover: false,
                                items: ".dropdown-item",
                                update_title: false,
                                change: function(event, target, dropdown) {
                                    var $target = $(target);
                                    $target.removeClass(dropdown.options.active_class);
                                    self.$emit("change", $target.attr("data-id"));
                                }
                            });
                        }
                    },
                    "component-switch": {
                        props: ["modelValue", "disabled"],
                        emits: ["update:modelValue", "change"],
                        template: '<div class="switch"><input type="checkbox" v-bind:checked="prop_checked" v-bind:disabled="prop_disabled"></div>',
                        delimiters: ['{ { ', ' } }'],
                        mounted: function() {
                            var self = this;

                            $(self.$el).waSwitch({
                                change: function(active, wa_switch) {
                                    self.$emit("change", active);
                                    self.$emit("update:modelValue", active);
                                }
                            });
                        },
                        computed: {
                            prop_checked() { return (typeof this.modelValue === "boolean" ? this.modelValue : false); },
                            prop_disabled() { return (typeof this.disabled === "boolean" ? this.disabled : false); }
                        }
                    },
                    "component-image-toggle": {
                        props: ["modelValue"],
                        emits: ["update:modelValue"],
                        template: that.components["component-image-toggle"],
                        delimiters: ['{ { ', ' } }'],
                        mounted: function() {
                            var self = this;

                            var $wrapper = $(self.$el),
                                $active = $wrapper.find("> [data-id='" + self.modelValue + "']");

                            if ($active.length) { $active.addClass("selected"); }

                            $wrapper.waToggle({
                                change: function(event, target, toggle) {
                                    var id = $(target).attr("data-id");

                                    if (typeof id !== "boolean") { id = (id === "true"); }

                                    self.$emit("update:modelValue", id);
                                }
                            });
                        }
                    },
                    "component-ai-toolbar": {
                        props: ["updateProp"],
                        emits: ["update"],
                        template: that.components["component-ai-toolbar"],
                        mounted: function() {
                            this.$el.dataset.updateProp = this.updateProp;

                            $.wa_shop_products.useComponentExperimentalAIToolbar({
                                $toolbar: $(this.$el),
                                productId: that.product.id,
                                onUpdate: (prop, product) => {
                                    this.$emit('update', product[prop]);
                                    that.$wrapper.trigger("change");
                                }
                            });
                        }
                    },
                },
                computed: {
                    social_image_url: function() {
                        var self = this,
                            image_array = [],
                            image;

                        if (self.social.is_auto) {
                            if (that.product.photos.length) {
                                if (that.product.image_id) {
                                    image_array = that.product.photos.filter( function(photo) {
                                        return (photo.id === that.product.image_id);
                                    });
                                }

                                if (image_array.length) {
                                    image = image_array[0];
                                } else {
                                    image = that.product.photos[0];
                                }
                                return image.url;

                            } else  {
                                return "";
                            }

                        } else {

                            if (self.social.use_url) {
                                return self.social.image_url;

                            } else if (that.product.photos.length) {
                                image_array = that.product.photos.filter( function(photo) {
                                    return (photo.id === self.social.image_id);
                                });

                                if (image_array.length) {
                                    image = image_array[0];
                                } else {
                                    image = that.product.photos[0];
                                }

                                return image.url;

                            } else {
                                return "";
                            }
                        }
                    },
                    getLettersHTML: function() {
                        var self = this;

                        return function(letters, min, max) {
                            letters = letters || '';
                            var count = letters.length;
                            var locale = $.wa.locale_plural(count, that.locales["letter_forms"], false);

                            var is_good = (count >= min && count <= max);
                            var result = locale.replace("%d", '<span class="s-number ' + (is_good ? "color-green" : "color-orange") + '">' + count + '</span>');

                            return result;
                        }
                    },
                    search_preview_title: function() {
                        var self = this;

                        var result = null;

                        if (that.product.meta_title) {
                            result = that.product.meta_title
                                .replace(/\{\$name\}/g, that.product.name)
                                .replace(/\{\$summary\}/g, that.product.summary)
                                .replace(/\{\$price\}/g, that.product.price)
                        } else {
                            result = that.product.name;
                        }

                        return result;
                    },
                    search_preview_description: function() {
                        var self = this;

                        var result = null;

                        if (that.product.meta_description) {
                            result = that.product.meta_description
                                .replace(/\{\$name\}/g, that.product.name)
                                .replace(/\{\$summary\}/g, that.product.summary)
                                .replace(/\{\$price\}/g, that.product.price)

                        } else if (that.product.summary) {
                            result = that.product.summary;
                        } else if (that.product.description) {
                            result = that.product.description;
                        }

                        return result;
                    },
                    social_preview_title: function() {
                        var self = this;

                        var result = null;

                        if (that.social.title) {
                            result = that.social.title
                                .replace(/\{\$name\}/g, that.product.name)
                                .replace(/\{\$summary\}/g, that.product.summary)
                                .replace(/\{\$price\}/g, that.product.price)
                        } else {
                            result = that.product.name;
                        }

                        return result;
                    },
                    social_preview_description: function() {
                        var self = this;

                        var result = null;

                        if (that.social.description) {
                            result = that.social.description
                                .replace(/\{\$name\}/g, that.product.name)
                                .replace(/\{\$summary\}/g, that.product.summary)
                                .replace(/\{\$price\}/g, that.product.price)

                        } else if (that.product.summary) {
                            result = that.product.summary;
                        } else if (that.product.description) {
                            result = that.product.description;
                        }

                        return result;
                    }
                },
                methods: {
                    renderErrors: function(errors) {
                        var self = this;

                        if (errors && errors.length) {
                            $.each(errors, function(i, error) {
                                self.renderError(error);
                            });
                        }
                    },
                    renderError: function(error) {
                        var self = this;

                        var white_list = ["product_video_add", "file_add", "social_image_url", "social_video_url"];

                        if (error.id && white_list.indexOf(error.id) >= 0) {
                            self.errors[error.id] = error;
                        } else {
                            self.errors_global.push(error);
                        }
                    },
                    removeErrors: function(errors) {
                        var self = this;

                        // Очистка всех ошибок
                        if (errors === null) {
                            $.each(self.errors, function(key) {
                                if (key !== "global") {
                                    delete self.errors[key];
                                } else {
                                    self.errors.global.splice(0, self.errors.length);
                                }
                            });

                            // Рендер ошибок
                        } else if (errors && errors.length) {
                            $.each(errors, function(i, error_id) {
                                self.removeError(error_id);
                            });
                        }
                    },
                    removeError: function(error_id) {
                        var self = this;

                        if (typeof error_id === "number") {
                            var error_index = self.errors.global.indexOf(error_id);
                            if (typeof error_index >= 0) {
                                self.errors.splice(error_index, 1);
                            }
                        } else if (typeof error_id === "string") {
                            if (self.errors[error_id]) {
                                delete self.errors[error_id];
                            }
                        }
                    },

                    //
                    addVariable: function(object, key, value) {
                        var self = this;
                        object[key] += value;
                    },

                    // PHOTO
                    addProductPhoto: function() {
                        var self = this;

                        $.waDialog({
                            html: that.templates["dialog_photo_manager"],
                            options: {
                                onPhotoAdd: function(photo) {
                                    that.product.photos.unshift(photo);
                                    if (that.product.photos.length === 1) {
                                        self.setPhoto(that.product.photos[0]);
                                    }
                                },
                                onSuccess: function(image_id) {
                                    var photo_array = that.product.photos.filter( function(photo) { return (photo.id === image_id); });
                                    if (photo_array.length) {
                                        self.setPhoto(photo_array[0]);
                                    }
                                }
                            },
                            onOpen: function($dialog, dialog) {
                                that.initPhotoManagerDialog($dialog, dialog, self.social.image_id);
                            }
                        });
                    },
                    setPhoto: function(photo) {
                        this.social["image_id"] = photo.id;

                        that.$wrapper.trigger("change");
                    },

                    // OTHER
                    onUrlBlur: function(value, error_id) {
                        var self = this;

                        // value = $.trim(value);
                        if (value.length) {
                            var is_valid = $.wa.isValid("url", value);
                            if (!is_valid) {
                                self.renderError({
                                    id: error_id,
                                    text: that.locales["incorrect_url"]
                                });
                            }
                        }
                    }
                },
                delimiters: ['{ { ', ' } }'],
                created: function () {
                    $view_section.css("visibility", "");
                },
                mounted: function() {
                    that.$wrapper.trigger("section_mounted", ["seo", that]);
                }
            }).mount($view_section[0]);

            return vue_model;
        };

        // Сохранение
        Section.prototype.validate = function() {
            var that = this;

            var errors = [];

            $.each(that.errors, function(i, error) {
                if (that.social.is_auto) {
                    var white_list = ["social_image_url", "social_video_url"];
                    if (white_list.indexOf(error.id) < 0) {
                        errors.push(error);
                    }
                } else {
                    errors.push(error);
                }
            });

            $.each(that.errors_global, function(i, error) {
                errors.push(error);
            });

            return errors;
        };

        Section.prototype.initSave = function() {
            var that = this;

            that.$wrapper.on("click", ".js-product-save", function (event, options) {
                options = (options || {});

                event.preventDefault();

                // Останавливаем сохранение если во фронте есть ошибки
                var errors = that.validate();
                if (errors.length) {
                    var $error = $(".state-error:first");
                    if ($error.length) {
                        $(window).scrollTop($error.offset().top - 100);
                    }
                    return false;
                }

                var loading = "<span class=\"icon top\" style=\"margin-left: .5rem\"><i class=\"fas fa-spinner fa-spin\"></i></span>";

                var $button = $(this),
                    $loading = $(loading).appendTo($button.attr("disabled", true));

                // Очищаем ошибки
                $.each(that.errors, function(key, error) {
                    // Точечные ошибки
                    if (key !== "global") { delete that.errors[key]; }
                    // Общие ошибки
                    else if (error.length) {
                        error.splice(0, error.length);
                    }
                });

                sendRequest()
                    .done( function(server_data) {
                        var product_id = server_data.product_id;
                        if (product_id) {
                            var is_new = location.href.indexOf("/new/seo/") >= 0;
                            if (is_new) {
                                var url = location.href.replace("/new/seo/", "/"+product_id+"/seo/");
                                history.replaceState(null, null, url);
                                that.$wrapper.trigger("product_created", [product_id]);
                            }
                        }

                        if (options.redirect_url) {
                            $.wa_shop_products.router.load(options.redirect_url).fail( function() {
                                location.href = options.redirect_url;
                            });
                        } else {
                            $.wa_shop_products.router.reload();
                        }
                    })
                    .fail( function(errors) {
                        $button.attr("disabled", false);
                        $loading.remove();

                        if (errors) {
                            var error_search = errors.filter( error => (error.id === "not_found"));
                            if (error_search.length) { $.wa_shop_products.showDeletedProductDialog(); }
                            that.vue_model.renderErrors(errors);
                            $(window).scrollTop(0);
                        }
                    });
            });

            function sendRequest() {
                var data = getData();
                beforeSavePluginHook(data);

                return request(data);

                function request(data) {
                    var deferred = $.Deferred();

                    $.post(that.urls["save"], data, "json")
                        .done( function(response) {
                            if (response.status === "ok") {
                                deferred.resolve(response.data);
                            } else {
                                deferred.reject(response.errors);
                            }
                            afterSavePluginHook(data, response);
                        })
                        .fail( function() {
                            deferred.reject();
                        });
                    savePluginHook(data);

                    return deferred.promise();
                }
            }

            function getData() {
                return [
                    {
                        "name": "product[id]",
                        "value": that.product.id
                    },
                    {
                        "name": "product[meta_title]",
                        "value": that.product.meta_title
                    },
                    {
                        "name": "product[meta_description]",
                        "value": that.product.meta_description
                    },
                    {
                        "name": "product[meta_keywords]",
                        "value": that.product.meta_keywords
                    },
                    {
                        "name": "product[og][title]",
                        "value": (that.social.is_auto ? "" : that.social.title)
                    },
                    {
                        "name": "product[og][video]",
                        "value": (that.social.is_auto ? "" : that.social.video_url)
                    },
                    {
                        "name": "product[og][image]",
                        "value": (that.social.is_auto ? "" : that.social.image_url)
                    },
                    {
                        "name": "product[og][image_id]",
                        "value": (that.social.is_auto ? "" : that.social.image_id)
                    },
                    {
                        "name": "product[og][description]",
                        "value": (that.social.is_auto ? "" : that.social.description)
                    },
                    {
                        "name": "product[og][type]",
                        "value": (that.social.is_auto ? "" : that.social.type)
                    }
                ];
            }

            function beforeSavePluginHook(data) {
                return triggerHook($.Event('wa_before_save', {
                    product_id: that.product.id,
                    section_controller: that,
                    form_errors: data.errors,
                    form_data: data.data
                }));
            }

            function savePluginHook(data) {
                triggerHook($.Event('wa_save', {
                    product_id: that.product.id,
                    section_controller: that,
                    form_data: data.data
                }));
            }

            function afterSavePluginHook(form_data, server_data) {
                triggerHook($.Event('wa_after_save', {
                    product_id: that.product.id || (server_data && server_data.data && server_data.data.id),
                    section_controller: that,
                    server_data: server_data,
                    form_data: form_data
                }));
            }

            function triggerHook(event) {
                try {
                    $('#js-product-seo-section-wrapper').trigger(event);
                } catch(e) {
                    console.warn(e);
                }
                return !event.isDefaultPrevented();
            }
        };

        // Полезные функции

        Section.prototype.initPhotoManagerDialog = function($dialog, dialog, photo_id) {
            var that = this;

            var $vue_section = $dialog.find("#vue-photo-manager-section");

            var active_photo = null,
                photos = $.wa.clone(that.product.photos);

            photos.forEach( function(photo) {
                photo = formatPhoto(photo);
                if (photo_id && photo_id === photo.id) { active_photo = photo; }
            });

            if (!active_photo && photos.length) {
                active_photo = photos[0];
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
                        self.photos.unshift(photo);
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

    })($);

    $.wa_shop_products.init.initProductSeoSection = function(options) {
        return new Section(options);
    };

})(jQuery);
