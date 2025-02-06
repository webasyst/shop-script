( function($) {

    var Dialog = ( function($) {

        Dialog = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.dialog = options["dialog"];

            // CONST
            that.photo = options["photo"];
            that.photos = options["photos"];
            that.scope = options["scope"];
            that.scope_model = options["scope_model"];

            // DYNAMIC VARS

            // INIT
            that.vue_model = that.initVue();
            that.init();
        };

        Dialog.prototype.init = function() {
            var that = this;
        };

        Dialog.prototype.initVue = function() {
            var that = this;

            var $section = that.$wrapper.find("#js-vue-node");

            var photos = $.wa.clone(that.photos),
                photo = null;

            // Format photos
            $.each(photos, function(i, _photo) {
                _photo.is_changed = false;
                _photo.image_is_changed = false;

                if (_photo.id === that.photo.id) {
                    photo = _photo;
                }
            });

            return Vue.createApp({
                data() {
                    return {
                        photo: photo,
                        photos: photos,
                        image: null,            // Промежуточные данные для рендера в канвасе
                        //
                        edit_mode: false,       // Режим редактирования
                        is_changed: false,      // Наличие изменений в одном из фото
                        photo_is_ready: false,  // Фотка загрузилась и готова к работе
                        //
                        is_locked: false        // Маркер сохранения
                    }
                },
                emits: ["success", "cancel"],
                delimiters: ['{ { ', ' } }'],
                components: {
                    "component-crop-section": {
                        props: ["photo"],
                        data: function() {
                            return {
                                canvas: null,
                                image: null,
                                area: null,
                                selection_area: {
                                    top: 0,
                                    left: 0,
                                    width: null,
                                    height: null
                                },
                                //
                                photo_is_ready: false,
                                photo_edited: false
                            }
                        },
                        template: that.scope.components["component-crop-section"],
                        delimiters: ['{ { ', ' } }'],
                        computed: {
                            selection_bg_styles: function() {
                                var self = this;

                                return {
                                    'clip-path': getPath(),
                                    visibility: (self.photo_is_ready ? '' : "hidden")
                                };

                                function getPath() {
                                    var result = "polygon(0% 0%)";

                                    if (self.selection_area.width && self.selection_area.height) {
                                        var x1 = self.selection_area.left + "px",
                                            x2 = (self.selection_area.left + self.selection_area.width) + "px",
                                            y1 = self.selection_area.top + "px",
                                            y2 = (self.selection_area.top + self.selection_area.height) + "px";

                                        result = "polygon(0% 0%, 0% 100%, x1 100%, x1 y1, x2 y1, x2 y2, x1 y2, x1 100%, 100% 100%, 100% 0%)"
                                            .replace(/x1/g, x1)
                                            .replace(/x2/g, x2)
                                            .replace(/y1/g, y1)
                                            .replace(/y2/g, y2);
                                    }

                                    return result;
                                }
                            },
                            selection_styles: function() {
                                var self = this;

                                var top = (self.selection_area.top > 0 ? self.selection_area.top + "px" : ""),
                                    left = (self.selection_area.left > 0 ? self.selection_area.left + "px" : ""),
                                    width = (self.selection_area.width > 0 ? self.selection_area.width + "px" : ""),
                                    height = (self.selection_area.height > 0 ? self.selection_area.height + "px" : "");

                                return {
                                    top: top,
                                    left: left,
                                    width: width,
                                    height: height,
                                    visibility: (self.photo_is_ready ? '' : "hidden")
                                }
                            },
                            selection_size: function() {
                                var self = this;

                                var w = 0,
                                    h = 0;

                                if (self.area && self.image) {
                                    var $editor = $(self.area).closest(".s-editor-block");

                                    var editor_w = $editor.outerWidth(),
                                        editor_h = $editor.outerHeight();

                                    var scale_w = self.selection_area.width/editor_w,
                                        scale_h = self.selection_area.height/editor_h;

                                    w = parseInt(self.image.naturalWidth * scale_w);
                                    h = parseInt(self.image.naturalHeight * scale_h);
                                }

                                return (w + "x" + h);
                            }
                        },
                        methods: {
                            save: function() {
                                var self = this;
                                self.photo.url = self.canvas.toDataURL();
                                self.$emit("success", {});
                            },
                            cancel: function() {
                                var self = this;
                                self.$emit("cancel");
                            },
                            onPhotoLoad: function(event) {
                                var self = this;

                                self.photo_is_ready = true;
                                self.$nextTick( function() {
                                    initPhoto(event.currentTarget);
                                });

                                that.dialog.resize();

                                function initPhoto(image) {
                                    var $wrapper = $(self.$el);

                                    // Работаем только с уже загруженным изображением
                                    var $area = $wrapper.find(".js-selection-area"),
                                        canvas = document.createElement("canvas");

                                    self.image = image;
                                    self.canvas = canvas;
                                    self.area = $area[0];
                                    self.selection_area.width = $area.outerWidth();
                                    self.selection_area.height = $area.outerHeight();

                                    initMoveSelection($wrapper, self);

                                    that.showPreviewCanvas(canvas);

                                    function initMoveSelection($wrapper, vue_model) {
                                        var $area = $wrapper.find(".js-selection-area"),
                                            $editor = $area.closest(".s-editor-block"),
                                            $document = $(document);

                                        var update_timer = 0;

                                        initMove();

                                        initResize();

                                        function initMove() {
                                            $area.on("mousedown", onMouseDown);

                                            function onMouseDown(event) {
                                                var $area = $(this);

                                                var start_top = event.originalEvent.pageY,
                                                    start_left = event.originalEvent.pageX;

                                                var editor_w = $editor.outerWidth(),
                                                    editor_h = $editor.outerHeight(),
                                                    editor_offset = $editor.offset();

                                                var area_w = $area.outerWidth(),
                                                    area_h = $area.outerHeight(),
                                                    area_offset = $area.offset(),
                                                    area_top = area_offset.top - editor_offset.top,
                                                    area_left = area_offset.left - editor_offset.left;

                                                //

                                                $document.on("mousemove", onMouseMove);
                                                $document.on("mouseup", onMouseUp);

                                                //

                                                function onMouseMove(event) {
                                                    var move_top = event.originalEvent.pageY,
                                                        move_left = event.originalEvent.pageX,
                                                        delta_top = move_top - start_top,
                                                        delta_left = move_left - start_left;

                                                    var new_top = area_top + delta_top,
                                                        new_left = area_left + delta_left;

                                                    if (new_top + area_h > editor_h) {
                                                        new_top = editor_h - area_h;
                                                    } else if (new_top < 0) {
                                                        new_top = 0;
                                                    }

                                                    if (new_left + area_w > editor_w) {
                                                        new_left = editor_w - area_w;
                                                    } else if (new_left < 0){
                                                        new_left = 0;
                                                    }

                                                    vue_model.selection_area.left = new_left;
                                                    vue_model.selection_area.top = new_top;

                                                    update();
                                                }

                                                function onMouseUp() {
                                                    $document.off("mousemove", onMouseMove);
                                                    $document.off("mouseup", onMouseUp);
                                                }
                                            }
                                        }

                                        function initResize() {
                                            $area.on("mousedown", ".s-area-corner", onMouseDown);

                                            function onMouseDown(event) {
                                                event.stopPropagation();

                                                var $toggle = $(event.currentTarget),
                                                    type = $toggle.attr("data-type");

                                                var start_top = event.originalEvent.pageY,
                                                    start_left = event.originalEvent.pageX;

                                                var editor_w = $editor.outerWidth(),
                                                    editor_h = $editor.outerHeight(),
                                                    ratio = editor_w/editor_h;

                                                var selection_area = {
                                                    top: vue_model.selection_area.top,
                                                    left: vue_model.selection_area.left,
                                                    width: vue_model.selection_area.width,
                                                    height: vue_model.selection_area.height
                                                }

                                                //

                                                $document.on("mousemove", onMouseMove);
                                                $document.on("mouseup", onMouseUp);

                                                //

                                                function onMouseMove(event) {
                                                    var is_shift_pressed = event.shiftKey,
                                                        is_alt_pressed = event.altKey;

                                                    var move_top = event.originalEvent.pageY,
                                                        move_left = event.originalEvent.pageX,
                                                        delta_top = move_top - start_top,
                                                        delta_left = move_left - start_left;

                                                    var min_width = 100,
                                                        min_height = 100;

                                                    switch (type) {
                                                        case "top-left":
                                                            if (is_shift_pressed) { delta_top = delta_left/ratio;}
                                                            setTop(selection_area.top + delta_top);
                                                            setLeft(selection_area.left + delta_left);
                                                            break;
                                                        case "top":
                                                            setTop(selection_area.top + delta_top);
                                                            break;
                                                        case "top-right":
                                                            if (is_shift_pressed) { delta_top = -delta_left/ratio;}
                                                            setTop(selection_area.top + delta_top);
                                                            setWidth(selection_area.width + delta_left);
                                                            break;
                                                        case "right":
                                                            setWidth(selection_area.width + delta_left);
                                                            break;
                                                        case "bottom-right":
                                                            if (is_shift_pressed) { delta_top = delta_left/ratio;}
                                                            setWidth(selection_area.width + delta_left);
                                                            setHeight(selection_area.height + delta_top);
                                                            break;
                                                        case "bottom":
                                                            setHeight(selection_area.height + delta_top);
                                                            break;
                                                        case "bottom-left":
                                                            if (is_shift_pressed) { delta_top = -delta_left/ratio; }
                                                            setHeight(selection_area.height + delta_top);
                                                            setLeft(selection_area.left + delta_left);
                                                            break;
                                                        case "left":
                                                            setLeft(selection_area.left + delta_left);
                                                            break;
                                                        default:
                                                            break;
                                                    }

                                                    update();

                                                    function setWidth(width) {
                                                        if (width + selection_area.left > editor_w) {
                                                            width = editor_w - selection_area.left;
                                                        }

                                                        if (width < min_width) { width = min_width; }

                                                        vue_model.selection_area.width = width;
                                                    }

                                                    function setHeight(height) {
                                                        if (height + selection_area.top > editor_h) {
                                                            height = editor_h - selection_area.top;
                                                        }

                                                        if (height < min_height) { height = min_height; }

                                                        vue_model.selection_area.height = height;
                                                    }

                                                    function setTop(top) {
                                                        if (top < 0) { top = 0; }

                                                        var height = selection_area.height + (selection_area.top - top);
                                                        if (height >= min_height) {
                                                            vue_model.selection_area.height = height;
                                                            vue_model.selection_area.top = top;
                                                        }
                                                    }

                                                    function setLeft(left) {
                                                        if (left < 0) { left = 0; }

                                                        var width = selection_area.width + (selection_area.left - left);
                                                        if (width >= min_width) {
                                                            vue_model.selection_area.width = selection_area.width + (selection_area.left - left);
                                                            vue_model.selection_area.left = left;
                                                        }
                                                    }
                                                }

                                                function onMouseUp() {
                                                    $document.off("mousemove", onMouseMove);
                                                    $document.off("mouseup", onMouseUp);
                                                }
                                            }
                                        }

                                        function update() {
                                            clearTimeout(update_timer);
                                            update_timer = setTimeout( function() {
                                                vue_model.updateCanvas();
                                            }, 100);
                                        }
                                    }
                                }
                            },
                            updateCanvas: function() {
                                var self = this;

                                var image = self.image,
                                    canvas = self.canvas,
                                    context = canvas.getContext("2d");

                                if (!self.image) {
                                    console.log("Image not ready");
                                    return false;
                                }

                                setImage(self.selection_area.left, self.selection_area.top, self.selection_area.width, self.selection_area.height);

                                self.photo_edited = true;

                                function setImage(sx, sy, sw, sh) {
                                    var scale_x = image.width/image.naturalWidth,
                                        scale_y = image.height/image.naturalHeight;

                                    context.clearRect(0, 0, context.width, context.height);

                                    canvas.width = sw/scale_x;
                                    canvas.height = sh/scale_y;

                                    context.drawImage(image, sx/scale_x, sy/scale_y, sw/scale_x, sh/scale_y, 0, 0, sw/scale_x, sh/scale_y);
                                }
                            }
                        },
                        mounted: function() {
                            var self = this;

                            that.dialog.resize();
                        }
                    }
                },
                computed: {
                    is_prev_disabled: function() {
                        var self = this;
                        var photo_index = self.photos.indexOf(self.photo);
                        return (photo_index === 0);
                    },
                    is_next_disabled: function() {
                        var self = this;
                        var photo_index = self.photos.indexOf(self.photo);
                        return (photo_index === photos.length - 1);
                    },
                    use_in_sku_html: function() {
                        var self = this,
                            count = self.photo.uses_count;

                        var locale = $.wa.locale_plural(count, that.scope.locales["use_in_sku_forms"], false);

                        return locale.replace("%d", "<span class=\"bold color-gray\">" + count + "</span>");
                    }
                },
                methods: {
                    onPhotoLoad: function(event) {
                        var self = this;

                        var clone_image = event.target.cloneNode(true);
                        clone_image.width = clone_image.naturalWidth;
                        clone_image.height = clone_image.naturalHeight;

                        self.image = clone_image;
                        self.photo_is_ready = true;

                        that.dialog.resize();
                    },

                    cropUse: function () {
                        var self = this;
                        self.edit_mode = true;

                        self.$nextTick( function() {
                            that.dialog.resize();
                        });
                    },
                    cropCancel: function() {
                        var self = this;

                        self.edit_mode = false;
                        self.$nextTick( function() {
                            that.dialog.resize();
                        });
                    },
                    cropSuccess: function() {
                        var self = this;

                        self.photo.is_changed = self.is_changed = self.photo.image_is_changed = true;

                        self.edit_mode = false;
                        self.$nextTick( function() {
                            that.dialog.resize();
                        });
                    },

                    deletePhoto: function() {
                        var self = this;

                        that.dialog.hide();

                        var request_xhr = null,
                            data = getData(photos);

                        $.waDialog({
                            html: that.scope.templates["dialog_media_image_delete_confirm"],
                            onOpen: function($dialog, dialog) {

                                var $section = $dialog.find(".js-vue-node");

                                Vue.createApp({
                                    data() {
                                        return {
                                            photo: self.photo
                                        }
                                    },
                                    delimiters: ['{ { ', ' } }'],
                                    created: function() {
                                        $section.css("visibility", "");
                                    },
                                    mounted: function() {
                                        dialog.resize();
                                    }
                                }).mount($section[0]);

                                $dialog.on("click", ".js-delete-button", function(event) {
                                    event.preventDefault();

                                    var loading = "<span class=\"icon top\" style='margin-right: .5rem;'><i class=\"fas fa-spinner fa-spin\"></i></span>";

                                    var $button = $(this).attr("disabled", true),
                                        $loading = $(loading).prependTo($button);

                                    request()
                                        .always( function() {
                                            $button.attr("disabled", false);
                                        })
                                        .done( function() {
                                            removePhoto();
                                            $('#wa-app .js-product-save').trigger("click");
                                            $('#wa-app').one('wa_loaded wa_load_fail', function () {
                                                $loading.remove();
                                                dialog.close();
                                            });
                                        })
                                        .fail( function () {
                                            $loading.remove();
                                        });
                                });
                            },
                            onClose: function() {
                                that.dialog.show();
                                if (!self.photos.length) {
                                    that.dialog.close();
                                }
                            }
                        });

                        function request() {
                            var deferred = $.Deferred();

                            request_xhr = $.post(that.scope.urls["delete_images"], data, "json")
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
                            return [
                                {
                                    name: "id[]",
                                    value: self.photo.id
                                }
                            ];
                        }

                        function removePhoto() {
                            var index = self.photos.indexOf(self.photo);

                            that.photos.splice(index, 1);
                            self.photos.splice(index, 1);

                            if (self.photos.length) {
                                self.changePhoto(self.photos[index + (self.photos.length < index + 1 ? -1 : 0)]);
                            }
                        }
                    },
                    changePhoto: function(photo) {
                        var self = this;

                        if (self.photo.is_changed) {
                            that.dialog.hide();

                            $.waDialog({
                                html: that.scope.templates["dialog_media_image_unsaved_changes_confirm"],
                                onOpen: function($dialog, dialog) {
                                    $dialog.on("click", ".js-save-button", function(event) {
                                        event.preventDefault();

                                        var loading = "<span class=\"icon top\" style='margin-right: .5rem;'><i class=\"fas fa-spinner fa-spin\"></i></span>";

                                        var $button = $(this).attr("disabled", true),
                                            $loading = $(loading).prependTo($button);

                                        that.save()
                                            .always( function() {
                                                $button.attr("disabled", false);
                                                $loading.remove();
                                            })
                                            .done( function() {
                                                changePhoto();
                                                dialog.close();
                                            });
                                    });

                                    $dialog.on("click", ".js-leave-button", function(event) {
                                        event.preventDefault();

                                        var photo_original = that.photos.filter( function(photo) {
                                            return (photo.id === self.photo.id);
                                        })[0];

                                        self.photo.url = photo_original.url;
                                        self.photo.description = photo_original.description;
                                        self.photo.is_changed = self.photo.image_is_changed = false;

                                        changePhoto();
                                        dialog.close();
                                    });
                                },
                                onClose: function() {
                                    that.dialog.show();
                                }
                            });

                        } else {
                            changePhoto();
                        }

                        function changePhoto() {
                            self.photo = photo;
                        }
                    },
                    prevPhoto: function() {
                        var self = this,
                            photo_index = self.photos.indexOf(self.photo);

                        if (photo_index > 0) {
                            self.changePhoto(self.photos[photo_index - 1]);
                        }
                    },
                    nextPhoto: function() {
                        var self = this,
                            photo_index = self.photos.indexOf(self.photo);

                        if (photo_index < self.photos.length - 1) {
                            self.changePhoto(self.photos[photo_index + 1]);
                        }
                    },

                    rotateImage: function(degrees) {
                        var self = this;

                        if (!self.image) { return false; }

                        var canvas = document.createElement("canvas"),
                            context = canvas.getContext("2d"),
                            image = self.image;

                        var width = image.naturalHeight,
                            height = image.naturalWidth;

                        canvas.width = width;
                        canvas.height = height;

                        context.clearRect(0,0, width, height);
                        context.save();
                        context.translate(width/2,height/2);
                        context.rotate(degrees * Math.PI/180);
                        context.drawImage(image, height/-2, width/-2);
                        context.restore();

                        that.showPreviewCanvas(canvas);

                        self.image = false;
                        self.photo_is_ready = false;
                        self.photo.is_changed = self.is_changed = self.photo.image_is_changed = true;
                        self.photo.url = canvas.toDataURL();
                    },
                    restoreImage: function() {
                        var self = this;
                        self.photo.url = self.photo.url_backup;
                        self.photo.image_is_changed = true;
                        self.photo.is_changed = self.is_changed = true;
                    },

                    save: function() {
                        that.save()
                            .done( function() {
                                that.dialog.close();
                            });
                    }
                },
                created: function () {
                    $section.css("visibility", "");
                },
                mounted: function () {
                    var self = this;

                    $(self.$el).on("input", function() {
                        self.photo.is_changed = self.is_changed = true;
                    });

                    initSlider( $(self.$el).find(".js-photos-list-wrapper") );

                    that.dialog.resize();

                    function initSlider($slider_w) {
                        var $list = $slider_w.find(".s-photos-list"),
                            $prev_button = $slider_w.find(".js-prev-move"),
                            $next_button = $slider_w.find(".js-next-move");

                        $prev_button.on("click", function(event) {
                            event.preventDefault();
                            move(false);
                        });

                        $next_button.on("click", function(event) {
                            event.preventDefault();
                            move(true);
                        });

                        $list.on("scroll", function() {
                            update();
                        });

                        update();

                        function move(next) {
                            var list_w = $list.outerWidth(),
                                list_left = $list[0].scrollLeft,
                                list_scroll = $list[0].scrollWidth;

                            var shift = parseInt(list_w/2);

                            var new_left = list_left + (next ? shift : -shift);

                            if (new_left < 0) { new_left = 0; }
                            if (new_left + list_w > list_scroll) { new_left = list_scroll - list_w; }

                            set(new_left);
                        }

                        function set(left) {
                            $list.scrollLeft(left);
                        }

                        function update() {
                            var list_w = $list.outerWidth(),
                                list_left = $list[0].scrollLeft,
                                list_scroll = $list[0].scrollWidth;

                            $prev_button.attr("disabled", !(list_left > 0));
                            $next_button.attr("disabled", !(list_scroll - list_left - list_w > 0));
                        }
                    }
                }
            }).mount($section[0]);
        };

        Dialog.prototype.save = function() {
            var that = this,
                vue_model = that.vue_model;

            vue_model.is_locked = true;

            var data = getData(vue_model);

            return request(data)
                .always( function() {
                    vue_model.is_locked = false;
                })
                .done( function() {
                    update();
                });

            function getData(vue_model) {
                var result = new FormData();
                var something_changed = false;

                $.each(vue_model.photos, function(i, photo) {
                    if (photo.is_changed) {
                        something_changed = true;
                        result.append('id', photo.id);
                        result.append('description', photo.description);

                        if (photo.image_is_changed) {
                            if (photo.url === photo.url_backup) {
                                result.append("state", "restored");
                            } else {
                                result.append("state", "changed");
                                var blob = dataUrlToBlob(photo.url);
                                result.append('image', blob, 'image.'+blob.type.split('/')[1]);
                            }
                        }
                    }
                });

                return something_changed ? result : null;
            }

            function request(data) {
                if (!data) {
                    // nothing to save (should never happen though; user should not be able to click save when nothing has changed)
                    var deferred = $.Deferred();
                    deferred.resolve();
                    return deferred.promise();
                }

                return $.ajax({
                    url: that.scope.urls["save_photo_changes"],
                    data: data,
                    // this is needed because `data` is a FormData object
                    processData: false,
                    // and this one because otherwise jQ breaks file uploads
                    contentType: false,
                    type: 'POST',
                    dataType: 'json',
                    error: function(xhr, text_status, error) {
                        console.log('unable to save image data', text_status, error);
                    }
                });
            }

            function dataUrlToBlob(data_url) {
                var block = data_url.split(";");
                var content_type = block[0].split(":")[1] || '';
                var byteCharacters = atob(block[1].split(",")[1]);
                var byteArrays = [];

                var sliceSize = 512;
                for (var offset = 0; offset < byteCharacters.length; offset += sliceSize) {
                    var slice = byteCharacters.slice(offset, offset + sliceSize);

                    var byteNumbers = new Array(slice.length);
                    for (var i = 0; i < slice.length; i++) {
                        byteNumbers[i] = slice.charCodeAt(i);
                    }

                    var byteArray = new Uint8Array(byteNumbers);

                    byteArrays.push(byteArray);
                }

                return new Blob(byteArrays, { type: content_type });
            }

            function update() {
                var root_photos = $.wa.construct(that.photos, "id");

                $.each(vue_model.photos, function(i, photo) {
                    if (photo.is_changed) {
                        var root_photo = root_photos[photo.id];
                        root_photo.url = photo.url;
                        root_photo.description = photo.description;
                    }
                });
            }
        };

        /**
         * @description Метод показывает содержимое над страницей. Нужно на этапе тестирования, чтобы видеть что содержить изображение.
         * */
        Dialog.prototype.showPreviewCanvas = function(canvas) {
            var that = this;
            var debug = false;

            if (!debug || !canvas) { return false; }

            var $preview = $("#js-preview-wrapper");
            if (!$preview.length) {
                $preview = $("<div>", { id: "js-preview-wrapper" }).css({
                    position: "fixed",
                    top: 0,
                    left: 0,
                    "z-index": 10000
                });
                $("body").append($preview);
            }
            $preview.html("").append(canvas);
        };

        return Dialog;

    })($);

    $.wa_shop_products.init.initProductMediaImageDialog = function(options) {
        return new Dialog(options);
    };

})(jQuery);
