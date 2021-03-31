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
            that.errors = {
                // сюда будут добавться точечные ключи ошибок
                // ключ global содержит массив с общими ошибками страницы
                global: []
            };

            // DYNAMIC VARS

            // INIT
            that.vue_model = that.initVue();
            that.init();

            console.log( that );
        };

        Section.prototype.init = function() {
            var that = this;

            var page_promise = that.$wrapper.closest(".s-product-page").data("ready");
            page_promise.done( function(product_page) {
                var $footer = that.$wrapper.find(".js-sticky-footer");
                product_page.initProductDelete($footer);
                product_page.initStickyFooter($footer);
            });

            that.initDragAndDrop();

            that.initSave();

            var ready_promise = that.$wrapper.data("ready");
            ready_promise.resolve(that);

            $.each(that.tooltips, function(i, tooltip) {
                $.wa.new.Tooltip(tooltip);
            });
        };

        Section.prototype.initVue = function() {
            var that = this;

            // DOM
            var $view_section = that.$wrapper.find(".js-product-media-section");

            // VARS
            var is_root_locked = false;

            // Модель данных для видео секции
            var video = {
                // модель поля
                model: (that.product.video ? that.product.video.orig_url : ""),
                // урл для фрейма видео
                url: (that.product.video ? that.product.video.url : null),
                request_xhr: null
            }

            // Модели данных для фото секции
            var photo_id = that.product.image_id,
                photos = that.product.photos,
                active_photo = null;

            photos.forEach( function(photo) {
                photo = formatPhoto(photo);
                if (photo_id && photo_id === photo.id) { active_photo = photo; }
            });

            if (!active_photo && photos.length) {
                active_photo = photos[0];
            }

            var vue_model = new Vue({
                el: $view_section[0],
                data: {
                    files_errors: [],
                    errors: that.errors,
                    product: that.product,

                    video: video,

                    files: [],
                    photo_id: photo_id,
                    photos: photos,
                    active_photo: active_photo
                },
                components: {
                    "component-loading-file": {
                        props: ['file'],
                        data: function () {
                            return {
                                errors: []
                            }
                        },
                        template: that.templates["component-loading-file"],
                        delimiters: ['{ { ', ' } }'],
                        mounted: function() {
                            var self = this;

                            var $bar = $(self.$el).find(".wa-progressbar").waProgressbar({ type: "circle", "stroke-width": 4.8, display_text: false }),
                                instance = $bar.data("progressbar");

                            loadPhoto(self.file)
                                .done( function(response) {
                                    $.each(response.files, function(i, photo) {
                                        // remove loading photo
                                        if (photo.id) {
                                            self.$emit("photo_added", {
                                                file: self.file,
                                                photo: photo
                                            });
                                        } else {
                                            var error = {
                                                id: "server_error",
                                                text: "Error: server error"
                                            }

                                            if (photo.error) { error.text = photo.error; }

                                            self.errors.push(error);
                                        }

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
                                        xhr.upload.addEventListener("progress", function(event) {
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
                    },
                    "component-flex-textarea": {
                        props: ["value", "placeholder", "class_name"],
                        template: '<textarea v-bind:class="class_name" v-bind:placeholder="placeholder" v-bind:value="value" v-on:input="$emit(\'input\', $event.target.value)"></textarea>',
                        delimiters: ['{ { ', ' } }'],
                        updated: function() {
                            var self = this;
                            var $textarea = $(self.$el);
                            that.toggleHeight($textarea);
                        },
                        mounted: function() {
                            var self = this;
                            var $textarea = $(self.$el);
                            that.toggleHeight($textarea);
                        }
                    },
                    "component-checkbox": {
                        props: ["value"],
                        template: '<label class="wa-checkbox"><input type="checkbox" v-bind:checked="value" v-on:input.stop="onInput($event)" v-on:change.stop="onChange($event)"><span><span class="icon"><i class="fas fa-check"></i></span></span></label>',
                        delimiters: ['{ { ', ' } }'],
                        methods: {
                            onInput: function(event) {
                                var self = this;
                                self.$emit('input', event.target.checked);
                            },
                            onChange: function(event) {
                                var self = this;
                                self.$emit('change', event.target.checked);
                            }
                        }
                    }
                },
                computed: {
                    selected_photos: function() {
                        return this.photos.filter( function(photo) {
                            return !!photo.is_checked;
                        });
                    },
                    unused_photos: function() {
                        return this.photos.filter( function(photo) {
                            return !(photo.uses_count > 0);
                        });
                    },
                    unused_selected_photos: function() {
                        return this.unused_photos.filter( function(photo) {
                            return !!photo.is_checked;
                        });
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

                        var white_list = ["product_video_add", "file_add"];

                        if (error.id && white_list.indexOf(error.id) >= 0) {
                            self.$set(that.errors, error.id, error);
                        } else {
                            that.errors.global.push(error);
                        }
                    },
                    removeErrors: function(errors) {
                        var self = this;

                        // Очистка всех ошибок
                        if (errors === null) {
                            $.each(that.errors, function(key) {
                                if (key !== "global") {
                                    self.$delete(that.errors, key);
                                } else {
                                    that.errors.global.splice(0, that.errors.length);
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
                            var error_index = that.errors.global.indexOf(error_id);
                            if (typeof error_index >= 0) {
                                that.errors.splice(error_index, 1);
                            }
                        } else if (typeof error_id === "string") {
                            if (that.errors[error_id]) {
                                self.$delete(that.errors, error_id);
                            }
                        }
                    },

                    // Работа с видео
                    deleteVideo: function() {
                        var self = this;

                        if (video.request_xhr) { return false; }

                        var data = { id: that.product.id }

                        request(data)
                            .fail( function(errors) {
                                self.renderErrors(errors);
                            })
                            .done( function() {
                                video.model = "";
                                video.url = null;
                            });

                        function request(data) {
                            var deferred = $.Deferred();

                            video.request_xhr = $.post(that.urls["video_delete"], data, "json")
                                .done( function(response) {
                                    if (response.status === "ok") {
                                        deferred.resolve();
                                    } else {
                                        deferred.reject(response.errors);
                                    }
                                })
                                .fail( function() {
                                    deferred.reject([]);
                                })
                                .always( function() {
                                    video.request_xhr = null;
                                });

                            return deferred.promise();
                        }
                    },
                    setVideo: function() {
                        var self = this;

                        if (video.request_xhr) { return false; }

                        var data = {
                            id: that.product.id,
                            url: video.model
                        }

                        request(data)
                            .fail( function(errors) {
                                self.renderErrors(errors);
                            })
                            .done( function(video_data) {
                                video.url = video_data.url;
                            });

                        function request(data) {
                            var deferred = $.Deferred();

                            video.request_xhr = $.post(that.urls["video_add"], data, "json")
                                .done( function(response) {
                                    if (response.status === "ok") {
                                        deferred.resolve(response.data.product.video);
                                    } else {
                                        deferred.reject(response.errors);
                                    }
                                })
                                .fail( function() {
                                    deferred.reject([]);
                                })
                                .always( function() {
                                    video.request_xhr = null;
                                });

                            return deferred.promise();
                        }
                    },

                    // Работа с фото списком
                    setPhoto: function(photo) {
                        var self = this;

                        $.waDialog({
                            html: that.templates["dialog_media_image"],
                            onOpen: function($dialog, dialog) {
                                $.wa_shop_products.init.initProductMediaImageDialog({
                                    $wrapper: $dialog,
                                    dialog: dialog,
                                    photo: photo,
                                    photos: photos,
                                    scope_model: self,
                                    scope: that
                                });
                            }
                        });

                    },
                    selectPhoto: function(photo) {
                    },
                    selectPhotos: function() {
                        var self = this;

                        $.each(self.photos, function(i, photo) {
                            photo.is_checked = true;
                        });
                    },
                    unselectPhotos: function() {
                        var self = this;

                        $.each(self.photos, function(i, photo) {
                            photo.is_checked = false;
                        });
                    },
                    selectUnusedPhotos: function() {
                        var self = this;

                        $.each(self.photos, function(i, photo) {
                            photo.is_checked = !(photo.uses_count > 0);
                        });
                    },
                    unselectUnusedPhotos: function() {
                        var self = this;

                        $.each(self.unused_photos, function(i, photo) {
                            photo.is_checked = false;
                        });
                    },
                    deletePhotos: function(photos) {
                        var self = this;

                        if (is_root_locked) { return false; }

                        var request_xhr = null,
                            data = getData(photos);

                        $.waDialog({
                            html: that.templates["dialog_media_delete_photos"],
                            onOpen: function($dialog, dialog) {

                                var $section = $dialog.find(".js-vue-node");

                                new Vue({
                                    el: $section[0],
                                    data: {
                                        photos: photos
                                    },
                                    delimiters: ['{ { ', ' } }'],
                                    computed: {
                                        used_photos: function() {
                                            var self = this;

                                            return self.photos.filter( function(photo) {
                                                return (photo.uses_count > 0);
                                            });
                                        }
                                    },
                                    created: function () {
                                        $section.css("visibility", "");
                                    },
                                    mounted: function () {
                                        dialog.resize();
                                    }
                                });

                                $dialog.on("click", ".js-delete-button", function(event) {
                                    event.preventDefault();

                                    var loading = "<span class=\"icon top\" style='margin-right: .5rem;'><i class=\"fas fa-spinner fa-spin\"></i></span>";

                                    var $submit_button = $(event.currentTarget),
                                        $loading = $(loading).prependTo($submit_button.attr("disabled", true));

                                    request(data)
                                        .always( function(errors) {
                                            $submit_button.attr("disabled", false);
                                            $loading.remove();
                                        })
                                        .done( function() {
                                            removePhotos(photos);
                                            dialog.close();
                                        });
                                });
                            }
                        });

                        function request(data) {
                            var deferred = $.Deferred();

                            request_xhr = $.post(that.urls["delete_images"], data, "json")
                                .done( function(response) {
                                    if (response.status === "ok") {
                                        deferred.resolve();
                                    } else {
                                        deferred.reject(response.errors);
                                    }
                                })
                                .fail( function() {
                                    deferred.reject([]);
                                })
                                .always( function() {
                                    request_xhr = null;
                                });

                            return deferred.promise();
                        }

                        function getData(photos) {
                            var result = [];

                            $.each(photos, function(i, photo) {
                                result.push({
                                    name: "id[]",
                                    value: photo.id
                                });
                            });

                            return result;
                        }

                        function removePhotos(photos) {
                            $.each(photos, function(i, photo) {
                                var index = self.photos.indexOf(photo);
                                if (index >= 0) {
                                    self.photos.splice(index, 1);
                                }
                            });
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
                            if (self.files_errors.length) {
                                self.files_errors.splice(0, self.files_errors.length);
                            }

                            $.each(files, function(i, file) {
                                self.loadFile(file);
                            });
                        }
                    },
                    onAreaChange: function(event) {
                        var self = this,
                            files = event.target.files;

                        if (files.length) {
                            if (self.files_errors.length) {
                                self.files_errors.splice(0, self.files_errors.length);
                            }

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

                        // Добавляем фотку в модели данных
                        self.photos.push(photo);

                        // удаляем UI загрузки
                        var index = self.files.indexOf(data.file);
                        if (index >= 0) { self.files.splice(index, 1); }
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
                            self.files_errors.push({ id: "file_add", text: that.locales["file_type"], data: { file_name: file.name } });
                        } else if (file_size >= that.max_file_size) {
                            self.files_errors.push({ id: "file_add", text: that.locales["file_size"], data: { file_name: file.name } });
                        } else if (file_size >= that.max_post_size) {
                            self.files_errors.push({ id: "file_add", text: that.locales["post_size"], data: { file_name: file.name } });
                        } else {
                            file.id = that.getUniqueIndex("file_load_id");
                            self.files.push(file);
                        }
                    }
                },
                delimiters: ['{ { ', ' } }'],
                created: function () {
                    $view_section.css("visibility", "");
                },
                mounted: function() {
                    var self = this;
                }
            });

            return vue_model;

            function formatPhoto(photo) {
                photo.expanded = false;
                photo.is_checked = false;
                photo.is_moving = false;

                if (typeof photo.description !== "string") { photo.description = ""; }
                photo.description_before = photo.description;

                return photo;
            }
        };

        Section.prototype.initDragAndDrop = function() {
            var that = this;

            var $document = $(document);

            var drag_data = {},
                over_locked = false,
                timer = 0;

            var move_class = "is-moving";

            that.$wrapper.on("dragstart", ".js-photo-move-toggle", function(event) {
                var $move = $(this).closest(".s-photo-wrapper"),
                    $photo = $move.find(".s-photo");

                var photo_id = "" + $move.attr("data-id"),
                    photo = getPhoto(photo_id);

                if (!photo) {
                    console.error("ERROR: photo isn't exist");
                    return false;
                }

                event.originalEvent.dataTransfer.setDragImage($photo[0], 20, 20);

                $.each(that.product.photos, function(i, photo) {
                    photo.is_moving = (photo.id === photo_id);
                });

                drag_data.move_photo = photo;

                $document.on("dragover", ".s-photo-wrapper", onDragOver);
                $document.on("dragend", onDragEnd);
            });

            function onDragOver(event) {
                event.preventDefault();
                if (!drag_data.move_photo) { return false; }

                if (!over_locked) {
                    over_locked = true;
                    movePhoto($(this).closest(".s-photo-wrapper"));
                    setTimeout( function() {
                        over_locked = false;
                    }, 100);
                }
            }

            function onDragEnd() {
                drag_data.move_photo.is_moving = false;
                drag_data = {};
                $document.off("dragover", ".s-photo-wrapper", onDragOver);
                $document.off("dragend", onDragEnd);
            }

            function movePhoto($over) {
                var photo_id = "" + $over.attr("data-id"),
                    photo = getPhoto(photo_id);

                if (!photo) {
                    console.error("ERROR: photo isn't exist");
                    return false;
                }

                if (drag_data.move_photo === photo) { return false; }

                var move_index = that.product.photos.indexOf(drag_data.move_photo),
                    over_index = that.product.photos.indexOf(photo),
                    before = (move_index > over_index);

                if (over_index !== move_index) {
                    that.product.photos.splice(move_index, 1);

                    over_index = that.product.photos.indexOf(photo);
                    var new_index = over_index + (before ? 0 : 1);

                    that.product.photos.splice(new_index, 0, drag_data.move_photo);

                    that.$wrapper.trigger("change");
                }
            }

            //

            function getPhoto(photo_id) {
                var result = null;

                $.each(that.product.photos, function(i, photo) {
                    photo.id = (typeof photo.id === "number" ? "" + photo.id : photo.id);
                    if (photo.id === photo_id) {
                        result = photo;
                        return false;
                    }
                });

                return result;
            }
        };

        // Сохранение

        Section.prototype.initSave = function() {
            var that = this;

            that.$wrapper.on("click", ".js-product-save", function (event, options) {
                options = (options || {});

                event.preventDefault();

                // Останавливаем сохранение если во фронте есть ошибки
                /*
                var errors = that.validate();
                if (errors.length) {
                    var $error = $(".wa-error-text:first");
                    if ($error.length) {
                        $(window).scrollTop($error.offset().top - 100);
                    }
                    return false;
                }
                */

                var loading = "<span class=\"icon top\" style=\"margin-left: .5rem\"><i class=\"fas fa-spinner fa-spin\"></i></span>";

                var $button = $(this),
                    $loading = $(loading).appendTo($button.attr("disabled", true));

                // Очищаем ошибки
                $.each(that.errors, function(key, error) {
                    // Точечные ошибки
                    if (key !== "global") { Vue.delete(that.errors, key); }
                    // Общие ошибки
                    else if (error.length) {
                        error.splice(0, error.length);
                    }
                });

                sendRequest()
                    .done( function() {
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
                            that.vue_model.renderErrors(errors);
                            $(window).scrollTop(0);
                        }
                    });
            });

            function sendRequest() {
                var data = getData();

                return request(data);

                function request(data) {
                    var deferred = $.Deferred();

                    $.post(that.urls["save"], data, "json")
                        .done( function(response) {
                            if (response.status === "ok") {
                                deferred.resolve(response);
                            } else {
                                deferred.reject(response.errors);
                            }
                        })
                        .fail( function() {
                            deferred.reject();
                        });

                    return deferred.promise();
                }
            }

            function getData() {
                var data = [
                    {
                        "name": "product[id]",
                        "value": that.product.id
                    },
                    {
                        "name": "product[image_id]",
                        "value": (that.vue_model.active_photo ? that.vue_model.active_photo.id : null)
                    }
                ];

                setPhotos();

                return data;

                function setPhotos() {
                    $.each(that.product.photos, function(i, photo) {
                        data.push({
                            name: "product[photos][" + photo.id + "][id]",
                            value: photo.id
                        });
                    });
                }

            }
        };

        // Полезные функции

        Section.prototype.getUniqueIndex = function(name, iterator) {
            var that = this;

            name = (typeof name === "string" ? name : "") + "_index";
            iterator = (typeof iterator === "number" ? iterator : 1);

            if (typeof that.getUniqueIndex[name] !== "number") { that.getUniqueIndex[name] = 0; }

            that.getUniqueIndex[name] += iterator;

            return that.getUniqueIndex[name];
        };

        Section.prototype.toggleHeight = function($textarea) {
            $textarea.css("min-height", 0);
            var scroll_h = $textarea[0].scrollHeight;
            $textarea.css("min-height", scroll_h + "px");
        };

        return Section;

    })($);

    $.wa_shop_products.init.initProductMediaSection = function(options) {
        return new Section(options);
    };

})(jQuery);