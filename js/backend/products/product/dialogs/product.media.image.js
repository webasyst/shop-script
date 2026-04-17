( function($) {

    var Dialog = ( function($) {

        Dialog = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.dialog = options["dialog"];

            // CONST
            that.mode = options["mode"] || "single_product";
            that.product = options["product"];
            that.photo = options["photo"];
            that.photos = options["photos"];
            that.products = options["products"] || [];
            that.initial_mode = options["initial_mode"] || "enhance";
            that.scope = options["scope"];
            that.scope_model = options["scope_model"];
            that.allowed_styles = options["allowed_styles"] || {};

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

            var products = [],
                product = null,
                photos = [],
                photo = null,
                photos_for_slider = null,
                ai_progressbar = null,
                ai_progress_timer = null,
                dummy_photo = createDummyPhoto();

            function normalizePhotoForDialog(_photo) {
                _photo.id = String(_photo.id);
                _photo.is_changed = false;
                _photo.image_is_changed = false;
                return _photo;
            }

            function normalizePhotoForRoot(_photo) {
                _photo.id = String(_photo.id);
                _photo.expanded = false;
                _photo.is_checked = false;
                _photo.is_moving = false;
                if (typeof _photo.description !== "string") { _photo.description = ""; }
                _photo.description_before = _photo.description;
                return _photo;
            }

            function normalizeProductForDialog(_product) {
                _product.id = String(_product.id);
                _product.image_id = (_product.image_id ? String(_product.image_id) : null);
                _product.photos = $.map((_product.photos || []), function(_photo) {
                    return normalizePhotoForDialog(_photo);
                });
                _product.ai_style = _product.ai_style || "auto";
                _product.ai_prompt = _product.ai_prompt || "";
                _product.ai_loading = false;
                _product.ai_error = "";

                return _product;
            }

            function getPlainAiError(value) {
                if (!value) {
                    return "";
                }

                var div = document.createElement("div");
                div.innerHTML = String(value);

                return (div.textContent || div.innerText || "")
                    .replace(/\s+/g, " ")
                    .trim();
            }

            function getAiErrorHtml(value) {
                var container, fragment, result;

                if (!value) {
                    return "";
                }

                container = document.createElement("div");
                container.innerHTML = String(value);
                fragment = document.createDocumentFragment();

                appendNodes(container.childNodes, fragment);

                result = document.createElement("div");
                result.appendChild(fragment);

                return result.innerHTML;

                function appendNodes(nodes, target) {
                    Array.prototype.forEach.call(nodes, function(node) {
                        appendNode(node, target);
                    });
                }

                function appendNode(node, target) {
                    var element, href, protocol;

                    if (!node) {
                        return;
                    }

                    if (node.nodeType === 3) {
                        appendTextWithBreaks(node.textContent, target);
                        return;
                    }

                    if (node.nodeType !== 1) {
                        return;
                    }

                    if (node.tagName === "BR") {
                        target.appendChild(document.createElement("br"));
                        return;
                    }

                    if (node.tagName === "A") {
                        href = node.getAttribute("href") || "";

                        if (href) {
                            href = href.trim();
                            protocol = "";

                            if (!/^(\/|#)/.test(href)) {
                                try {
                                    protocol = new URL(href, window.location.origin).protocol;
                                } catch (e) {
                                    protocol = "";
                                }
                            }

                            if (/^(\/|#)/.test(href) || protocol === "http:" || protocol === "https:") {
                                element = document.createElement("a");
                                element.setAttribute("href", href);
                                element.setAttribute("target", "_blank");
                                element.setAttribute("rel", "noopener noreferrer");
                                appendNodes(node.childNodes, element);
                                target.appendChild(element);
                                return;
                            }
                        }
                    }

                    appendNodes(node.childNodes, target);
                }

                function appendTextWithBreaks(text, target) {
                    var parts;

                    if (!text) {
                        return;
                    }

                    parts = String(text).split(/\r?\n/);

                    parts.forEach( function(part, index) {
                        if (index > 0) {
                            target.appendChild(document.createElement("br"));
                        }

                        if (part) {
                            target.appendChild(document.createTextNode(part));
                        }
                    });
                }
            }

            function getProductPhoto(_product, photo_id) {
                if (!_product) {
                    return null;
                }

                if (photo_id) {
                    photo_id = String(photo_id);
                    return _product.photos.filter( function(_photo) {
                        return _photo.id === photo_id;
                    })[0] || null;
                }

                if (_product.image_id) {
                    return _product.photos.filter( function(_photo) {
                        return _photo.id === _product.image_id;
                    })[0] || null;
                }

                return _product.photos[0] || null;
            }

            if (that.mode === "mass_products") {
                products = $.map($.wa.clone(that.products), function(_product) {
                    return normalizeProductForDialog(_product);
                });
                product = products[0] || null;
                photos = (product ? product.photos : []);
                photo = (that.initial_mode === "generate" ? dummy_photo : getProductPhoto(product, that.photo && that.photo.id));
            } else {
                photos = $.wa.clone(that.photos);

                $.each(photos, function(i, _photo) {
                    _photo = normalizePhotoForDialog(_photo);

                    if (that.photo && _photo.id === String(that.photo.id)) {
                        photo = _photo;
                    }
                });
            }

            photos_for_slider = photos.concat([dummy_photo]);

            if (that.initial_mode === "generate") {
                photo = dummy_photo;
            } else if (!photo) {
                photo = photos[0] || dummy_photo;
            }

            return Vue.createApp({
                data() {
                    return {
                        mode: that.mode,
                        products: products,
                        product: product,
                        photo: photo,
                        photos: photos,
                        photos_for_slider: photos_for_slider,
                        dummy_photo: dummy_photo,
                        image: null,            // Промежуточные данные для рендера в канвасе
                        //
                        edit_mode: false,       // Режим редактирования
                        is_changed: false,      // Наличие изменений в одном из фото
                        photo_is_ready: false,  // Фотка загрузилась и готова к работе
                        //
                        is_locked: false,       // Маркер сохранения
                        allowed_styles: that.allowed_styles,
                        ai_style: "auto",
                        ai_prompt: "",
                        ai_loading: false,
                        ai_error: "",
                        is_mass_generating: false,
                        is_mass_cancel_requested: false,
                        mass_generation_queue: [],
                        mass_generation_index: -1,
                        mass_generation_errors: {},
                        mass_generation_source: null,
                        ai_progress_percentage: 0,
                        ai_progress_started_at: null
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
                    is_mass_mode: function() {
                        return this.mode === "mass_products";
                    },
                    is_dummy_selected: function() {
                        return !!(this.photo && this.photo.is_dummy);
                    },
                    is_prev_disabled: function() {
                        var self = this;
                        var photo_index = self.photos_for_slider.indexOf(self.photo);
                        return (photo_index === 0);
                    },
                    is_next_disabled: function() {
                        var self = this;
                        var photo_index = self.photos_for_slider.indexOf(self.photo);
                        return (photo_index === self.photos_for_slider.length - 1);
                    },
                    use_in_sku_html: function() {
                        var self = this,
                            count = (self.photo && self.photo.uses_count ? self.photo.uses_count : 0);

                        var locale = $.wa.locale_plural(count, that.scope.locales["use_in_sku_forms"], false);

                        return locale.replace("%d", "<span class=\"bold color-gray\">" + count + "</span>");
                    },
                    ai_style_data: function() {
                        var self = this;

                        return self.allowed_styles[self.ai_style] || {};
                    },
                    ai_style_description: function() {
                        var self = this;

                        return self.ai_style_data.description || "";
                    },
                    mass_generate_products_count: function() {
                        return (this.is_mass_mode ? this.products.length : 0);
                    },
                    mass_generate_button_text: function() {
                        var self = this,
                            template = that.scope.locales["ai_improve_all_selected"] || "Generate for all";

                        return template.replace("%d", self.mass_generate_products_count);
                    },
                    mass_generate_hint_text: function() {
                        var self = this,
                            template = that.scope.locales["ai_improve_all_selected_hint"] || "for <strong>%d</strong> selected products";

                        return template.replace("%d", self.mass_generate_products_count);
                    },
                    mass_cancel_button_text: function() {
                        return that.scope.locales["ai_abort_process"] || "Abort process";
                    },
                    ai_error_html: function() {
                        return getAiErrorHtml(this.ai_error);
                    }
                },
                methods: {
                    getPlainAiError: function(value) {
                        return getPlainAiError(value);
                    },
                    getProductPreview: function(product) {
                        var self = this;

                        if (!product || !product.photos || !product.photos.length) {
                            return self.dummy_photo;
                        }

                        if (product.image_id) {
                            return product.photos.filter( function(photo) {
                                return photo.id === String(product.image_id);
                            })[0] || product.photos[0];
                        }

                        return product.photos[0];
                    },
                    syncAiStateToProduct: function() {
                        var self = this;

                        if (!self.product) {
                            return;
                        }

                        self.product.ai_style = self.ai_style;
                        self.product.ai_prompt = self.ai_prompt;
                        self.product.ai_loading = self.ai_loading;
                        self.product.ai_error = self.ai_error;
                    },
                    syncAiStateFromProduct: function(product) {
                        var self = this;

                        self.ai_style = (product ? product.ai_style : "auto") || "auto";
                        self.ai_prompt = (product ? product.ai_prompt : "") || "";
                        self.ai_loading = !!(product && product.ai_loading);
                        self.ai_error = (product ? product.ai_error : "") || "";
                    },
                    getOriginalProduct: function(product_id) {
                        var self = this;

                        if (!self.is_mass_mode) {
                            return null;
                        }

                        product_id = String(product_id);

                        return that.products.filter( function(product) {
                            return String(product.id) === product_id;
                        })[0] || null;
                    },
                    getOriginalPhoto: function(product_id, photo_id) {
                        var product = this.getOriginalProduct(product_id);
                        if (!product) {
                            return null;
                        }

                        photo_id = String(photo_id);

                        return (product.photos || []).filter( function(photo) {
                            return String(photo.id) === photo_id;
                        })[0] || null;
                    },
                    setCurrentPhoto: function(photo) {
                        var self = this;

                        self.photo = photo;
                        self.edit_mode = false;
                        self.photo_is_ready = !!(photo && photo.is_dummy);
                        self.image = null;
                        self.ai_error = (self.product ? self.product.ai_error : "") || "";
                        that.dialog.resize();
                    },
                    setActiveProduct: function(product, photo_id, options) {
                        var self = this,
                            next_photo = null;

                        options = options || {};

                        self.product = product;
                        self.photos = (product ? product.photos : []);
                        self.photos_for_slider = self.photos.concat([self.dummy_photo]);
                        self.is_changed = false;
                        self.syncAiStateFromProduct(product);

                        if (product) {
                            if (options.force_dummy) {
                                next_photo = self.dummy_photo;
                            }

                            if (!next_photo && typeof options.photo_index === "number" && options.photo_index >= 0) {
                                next_photo = product.photos[options.photo_index] || product.photos[0] || null;
                            }

                            if (!next_photo && photo_id) {
                                next_photo = product.photos.filter( function(_photo) {
                                    return _photo.id === String(photo_id);
                                })[0] || null;
                            }

                            if (!next_photo && product.image_id) {
                                next_photo = product.photos.filter( function(_photo) {
                                    return _photo.id === String(product.image_id);
                                })[0] || null;
                            }

                            if (!next_photo) {
                                next_photo = product.photos[0] || self.dummy_photo;
                            }
                        } else {
                            next_photo = self.dummy_photo;
                        }

                        self.setCurrentPhoto(next_photo);
                    },
                    getPhotoPosition: function(photo) {
                        var self = this;

                        if (!photo || photo.is_dummy) {
                            return -1;
                        }

                        return self.photos.indexOf(photo);
                    },
                    buildMassGenerationQueue: function() {
                        var self = this,
                            result = [],
                            start_index = -1;

                        if (!self.is_mass_mode || !self.products.length) {
                            return result;
                        }

                        if (self.product) {
                            $.each(self.products, function(index, product) {
                                if (String(product.id) === String(self.product.id)) {
                                    start_index = index;
                                    return false;
                                }
                            });
                        }

                        if (start_index < 0) {
                            start_index = 0;
                        }

                        $.each(self.products, function(i, product) {
                            if (i >= start_index) {
                                result.push(product);
                            }
                        });

                        $.each(self.products, function(i, product) {
                            if (i < start_index) {
                                result.push(product);
                            }
                        });

                        return result;
                    },
                    getMassGenerationSource: function() {
                        var self = this,
                            photo_index = self.getPhotoPosition(self.photo),
                            is_new = self.is_dummy_selected || photo_index < 0;

                        return {
                            is_new: is_new,
                            photo_index: (is_new ? -1 : photo_index),
                            ai_style: self.ai_style || "auto",
                            ai_prompt: self.ai_prompt || ""
                        };
                    },
                    applyMassGenerationContext: function(product, source) {
                        var self = this;

                        if (!product) {
                            return false;
                        }

                        self.setActiveProduct(product, null, {
                            force_dummy: !!source.is_new,
                            photo_index: (source.is_new ? null : source.photo_index)
                        });

                        self.ai_style = source.ai_style;
                        self.ai_prompt = source.ai_prompt;
                        self.ai_error = (product.ai_error || "");
                        self.syncAiStateToProduct();

                        return true;
                    },
                    finishMassAiGenerate: function() {
                        this.is_mass_generating = false;
                        this.is_mass_cancel_requested = false;
                        this.mass_generation_queue = [];
                        this.mass_generation_index = -1;
                        this.mass_generation_source = null;
                    },
                    hasMassGenerationError: function(product) {
                        var product_id = (product ? String(product.id) : "");

                        return !!(product_id && this.mass_generation_errors[product_id]);
                    },
                    cancelMassAiGenerate: function() {
                        if (!this.is_mass_generating) {
                            return false;
                        }

                        this.is_mass_cancel_requested = true;
                        return true;
                    },
                    closeDialog: function() {
                        if (this.is_mass_generating) {
                            this.cancelMassAiGenerate();
                        }

                        that.dialog.close();
                    },
                    processMassAiGenerateStep: function(index) {
                        var self = this,
                            source = self.mass_generation_source,
                            product = self.mass_generation_queue[index],
                            is_new = false;

                        if (!self.is_mass_generating || !source || !product) {
                            self.finishMassAiGenerate();
                            return false;
                        }

                        self.mass_generation_index = index;
                        self.applyMassGenerationContext(product, source);
                        is_new = (source.is_new || self.is_dummy_selected);

                        return self.runAiGeneration({
                            is_new: is_new
                        }).then( function() {
                            delete self.mass_generation_errors[String(product.id)];
                            return self.processMassAiGenerateNext(index + 1);
                        }, function(error_data) {
                            var error_text = (error_data && error_data.error ? error_data.error : that.scope.locales["ai_generate_image_error"]);

                            self.mass_generation_errors[String(product.id)] = error_text;
                            product.ai_error = error_text;
                            if (self.product && String(self.product.id) === String(product.id)) {
                                self.ai_error = error_text;
                            }

                            return self.processMassAiGenerateNext(index + 1);
                        });
                    },
                    processMassAiGenerateNext: function(next_index) {
                        var self = this;

                        if (!self.is_mass_generating) {
                            return false;
                        }

                        if (self.is_mass_cancel_requested || next_index >= self.mass_generation_queue.length) {
                            self.finishMassAiGenerate();
                            that.dialog.resize();
                            return true;
                        }

                        return self.processMassAiGenerateStep(next_index);
                    },
                    startMassAiGenerate: function() {
                        var self = this,
                            queue = self.buildMassGenerationQueue();

                        if (!self.is_mass_mode || self.ai_loading || self.is_mass_generating || self.is_locked || !self.product || self.photo.is_changed || !queue.length) {
                            return false;
                        }

                        self.mass_generation_errors = {};
                        $.each(self.products, function(i, product) {
                            product.ai_error = "";
                        });
                        self.mass_generation_queue = queue;
                        self.mass_generation_index = 0;
                        self.mass_generation_source = self.getMassGenerationSource();
                        self.is_mass_cancel_requested = false;
                        self.is_mass_generating = true;

                        return self.processMassAiGenerateStep(0);
                    },
                    confirmContextChange: function(onContinue) {
                        var self = this;

                        if (!(self.photo && self.photo.is_changed)) {
                            onContinue();
                            return;
                        }

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
                                            onContinue();
                                            dialog.close();
                                        });
                                });

                                $dialog.on("click", ".js-leave-button", function(event) {
                                    event.preventDefault();

                                    var photo_original = (self.is_mass_mode
                                        ? self.getOriginalPhoto(self.product.id, self.photo.id)
                                        : that.photos.filter( function(photo) {
                                            return (photo.id === self.photo.id);
                                        })[0]);

                                    if (photo_original) {
                                        self.photo.url = photo_original.url;
                                        self.photo.description = photo_original.description;
                                    }
                                    self.photo.is_changed = self.photo.image_is_changed = false;

                                    onContinue();
                                    dialog.close();
                                });
                            },
                            onClose: function() {
                                that.dialog.show();
                            }
                        });
                    },
                    onPhotoLoad: function(event) {
                        var self = this;

                        if (self.is_dummy_selected) {
                            self.image = null;
                            self.photo_is_ready = true;
                            that.dialog.resize();
                            return;
                        }

                        var clone_image = event.target.cloneNode(true);
                        clone_image.width = clone_image.naturalWidth;
                        clone_image.height = clone_image.naturalHeight;

                        self.image = clone_image;
                        self.photo_is_ready = true;

                        that.dialog.resize();
                    },
                    cropUse: function () {
                        var self = this;
                        if (self.is_dummy_selected || self.is_mass_generating) { return false; }
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

                        if (self.is_dummy_selected || self.is_mass_generating) { return false; }

                        that.dialog.hide();

                        var request_xhr = null;

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

                                            if (self.is_mass_mode) {
                                                $loading.remove();
                                                dialog.close();
                                            } else {
                                                $('#wa-app .js-product-save').trigger("click");
                                                $('#wa-app').one('wa_loaded wa_load_fail', function () {
                                                    $loading.remove();
                                                    dialog.close();
                                                });
                                            }
                                        })
                                        .fail( function () {
                                            $loading.remove();
                                        });
                                });
                            },
                            onClose: function() {
                                that.dialog.show();
                                if (!self.photos.length && !self.is_mass_mode) {
                                    that.dialog.close();
                                }
                            }
                        });

                        function request() {
                            var deferred = $.Deferred();

                            request_xhr = $.post(that.scope.urls["delete_images"], [{
                                name: "id[]",
                                value: self.photo.id
                            }], "json")
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

                        function removePhoto() {
                            var index = self.photos.indexOf(self.photo),
                                slider_index = self.photos_for_slider.indexOf(self.photo),
                                original_photos = (self.is_mass_mode
                                    ? ((self.getOriginalProduct(self.product.id) || {}).photos || [])
                                    : that.photos);

                            if (index >= 0) {
                                self.photos.splice(index, 1);
                            }

                            $.each(original_photos, function(i, photo) {
                                if (String(photo.id) === String(self.photo.id)) {
                                    original_photos.splice(i, 1);
                                    return false;
                                }
                            });

                            if (slider_index >= 0) {
                                self.photos_for_slider.splice(slider_index, 1);
                            }

                            if (self.product && String(self.product.image_id) === String(self.photo.id)) {
                                self.product.image_id = (self.photos[0] ? self.photos[0].id : null);
                            }

                            if (self.photos.length) {
                                self.changePhoto(self.photos[index + (self.photos.length < index + 1 ? -1 : 0)]);
                            } else {
                                self.photos_for_slider = [self.dummy_photo];
                                self.changePhoto(self.dummy_photo);
                            }
                        }
                    },
                    selectProduct: function(product) {
                        var self = this;

                        if (!self.is_mass_mode || !product || self.ai_loading || self.is_mass_generating || self.is_locked || (self.product && self.product.id === product.id)) {
                            return;
                        }

                        self.confirmContextChange( function() {
                            self.setActiveProduct(product);
                        });
                    },
                    changePhoto: function(photo) {
                        var self = this;

                        if (self.is_mass_generating) {
                            return false;
                        }

                        self.confirmContextChange( function() {
                            self.setCurrentPhoto(photo);
                        });
                    },
                    prevPhoto: function() {
                        var self = this,
                            photo_index = self.photos_for_slider.indexOf(self.photo);

                        if (photo_index > 0) {
                            self.changePhoto(self.photos_for_slider[photo_index - 1]);
                        }
                    },
                    nextPhoto: function() {
                        var self = this,
                            photo_index = self.photos_for_slider.indexOf(self.photo);

                        if (photo_index < self.photos_for_slider.length - 1) {
                            self.changePhoto(self.photos_for_slider[photo_index + 1]);
                        }
                    },
                    generateAiImageNew: function() {
                        return this.doGenerate(true);
                    },
                    generateAiImage: function() {
                        return this.doGenerate(false);
                    },
                    doGenerate: function(is_new) {
                        return this.runAiGeneration({
                            is_new: is_new
                        });
                    },
                    runAiGeneration: function(options) {
                        var self = this;

                        options = options || {};

                        var is_new = !!options.is_new,
                            deferred = $.Deferred();

                        if (self.ai_loading || self.photo.is_changed || (!is_new && self.is_dummy_selected)) {
                            return false;
                        }

                        self.ai_error = "";
                        self.ai_loading = true;
                        self.syncAiStateToProduct();
                        self.startAiProgress();

                        $.post(that.scope.urls["ai_generate_image"], {
                            image_id: is_new ? '' : self.photo.id,
                            product_id: (self.product ? self.product.id : that.product.id),
                            image_style: self.ai_style || "auto",
                            image_prompt: self.ai_prompt || ""
                        }, "json")
                            .done( function(response) {
                                var image = (response && response.data && response.data.image ? response.data.image : null);
                                if (response && response.status === "ok" && image) {
                                    var root_photo = normalizePhotoForRoot($.wa.clone(image)),
                                        dialog_photo = normalizePhotoForDialog($.wa.clone(image));

                                    if (self.is_mass_mode) {
                                        var original_product = self.getOriginalProduct(self.product.id);
                                        if (original_product) {
                                            if (!$.isArray(original_product.photos)) {
                                                original_product.photos = [];
                                            }
                                            original_product.photos.unshift(root_photo);
                                            original_product.image_id = root_photo.id;
                                        }
                                    } else {
                                        that.photos.push(root_photo);
                                    }

                                    if (self.is_mass_mode) {
                                        self.photos.unshift(dialog_photo);
                                        self.photos_for_slider = self.photos.concat([self.dummy_photo]);
                                        if (self.product) {
                                            self.product.image_id = dialog_photo.id;
                                        }
                                    } else {
                                        self.photos.push(dialog_photo);
                                        self.photos_for_slider.splice(self.photos_for_slider.length - 1, 0, dialog_photo);
                                        if (self.product && !self.product.image_id) {
                                            self.product.image_id = dialog_photo.id;
                                        }
                                    }
                                    self.setCurrentPhoto(dialog_photo);
                                    deferred.resolve({
                                        product_id: (self.product ? self.product.id : that.product.id),
                                        image: dialog_photo
                                    });
                                } else {
                                    self.ai_error = getErrorText(response);
                                    self.syncAiStateToProduct();
                                    deferred.reject({
                                        product_id: (self.product ? self.product.id : that.product.id),
                                        error: self.ai_error
                                    });
                                }
                            })
                            .fail( function() {
                                self.ai_error = that.scope.locales["ai_generate_image_error"];
                                self.syncAiStateToProduct();
                                deferred.reject({
                                    product_id: (self.product ? self.product.id : that.product.id),
                                    error: self.ai_error
                                });
                            })
                            .always( function() {
                                self.stopAiProgress();
                                self.ai_loading = false;
                                self.syncAiStateToProduct();
                                that.dialog.resize();
                            });

                        return deferred.promise();

                        function getErrorText(response) {
                            if (!response) {
                                return that.scope.locales["ai_generate_image_error"];
                            }

                            if ($.isArray(response.errors) && response.errors.length) {
                                return response.errors
                                    .map( function(error) {
                                        return error.text || error.error_description || error.error || "";
                                    })
                                    .filter(Boolean)
                                    .join("\n");
                            }

                            return that.scope.locales["ai_generate_image_error"];
                        }
                    },
                    initAiProgressbar: function() {
                        var self = this,
                            $wrapper = $(self.$el).find(".js-ai-progressbar");

                        if (!$wrapper.length) {
                            ai_progressbar = null;
                            return null;
                        }

                        if (!$wrapper.data("progressbar")) {
                            $wrapper.waProgressbar({
                                type: "circle",
                                "stroke-width": 4.8,
                                "display-text": false
                            });
                        }

                        ai_progressbar = $wrapper.data("progressbar") || null;

                        return ai_progressbar;
                    },
                    startAiProgress: function() {
                        var self = this;

                        self.stopAiProgress();

                        self.ai_progress_percentage = 0;
                        self.ai_progress_started_at = Date.now();

                        self.$nextTick( function() {
                            if (!self.ai_loading || !self.ai_progress_started_at) {
                                return;
                            }

                            self.initAiProgressbar();
                            self.syncAiProgress();

                            ai_progress_timer = setInterval( function() {
                                self.syncAiProgress();
                            }, 250);
                        });
                    },
                    syncAiProgress: function() {
                        var self = this;

                        if (!self.ai_loading || !self.ai_progress_started_at) {
                            return false;
                        }

                        if (!$.contains(document, self.$el)) {
                            self.stopAiProgress();
                            return false;
                        }

                        var instance = (ai_progressbar || self.initAiProgressbar());

                        if (!instance) {
                            return false;
                        }

                        var elapsed = Date.now() - self.ai_progress_started_at,
                            percentage = Math.min(90, (elapsed / 72000) * 90),
                            display_percentage = Math.floor(percentage);

                        if (percentage >= 90) {
                            percentage = 90;
                            display_percentage = 90;

                            if (ai_progress_timer) {
                                clearInterval(ai_progress_timer);
                                ai_progress_timer = null;
                            }
                        }

                        self.ai_progress_percentage = display_percentage;
                        instance.set({
                            percentage: percentage,
                            text: display_percentage + "%"
                        });

                        return true;
                    },
                    stopAiProgress: function() {
                        var self = this;

                        if (ai_progress_timer) {
                            clearInterval(ai_progress_timer);
                            ai_progress_timer = null;
                        }

                        self.ai_progress_started_at = null;
                        ai_progressbar = null;
                    },
                    rotateImage: function(degrees) {
                        var self = this;

                        if (!self.image || self.is_dummy_selected || self.is_mass_generating) { return false; }

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
                        if (self.is_dummy_selected || self.is_mass_generating) { return false; }
                        self.photo.url = self.photo.url_backup;
                        self.photo.image_is_changed = true;
                        self.photo.is_changed = self.is_changed = true;
                    },
                    save: function() {
                        if (this.is_mass_generating) {
                            return false;
                        }

                        this.syncAiStateToProduct();
                        that.save()
                            .done( function() {
                                that.dialog.close();
                            });
                    }
                },
                created: function () {
                    if (this.product) {
                        this.syncAiStateFromProduct(this.product);
                    }
                    $section.css("visibility", "");
                },
                mounted: function () {
                    var self = this;

                    $(self.$el).on("input", ".s-description-section textarea", function() {
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
                },
                beforeUnmount: function () {
                    this.stopAiProgress();
                    this.finishMassAiGenerate();
                }
            }).mount($section[0]);

            function createDummyPhoto() {
                return normalizePhotoForDialog({
                    id: "__dummy__",
                    name: "",
                    description: "",
                    url: that.scope.dummy_image_url,
                    url_backup: that.scope.dummy_image_url,
                    width: null,
                    height: null,
                    size: "",
                    uses_count: 0,
                    is_dummy: true
                });
            }
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
                var root_photos = null;

                if (that.mode === "mass_products") {
                    var root_product = that.products.filter( function(product) {
                        return String(product.id) === String(vue_model.product.id);
                    })[0];
                    root_photos = $.wa.construct((root_product ? root_product.photos : []), "id");
                } else {
                    root_photos = $.wa.construct(that.photos, "id");
                }

                $.each(vue_model.photos, function(i, photo) {
                    if (photo.is_changed) {
                        var root_photo = root_photos[photo.id];
                        if (root_photo) {
                            root_photo.url = photo.url;
                            root_photo.description = photo.description;
                        }

                        photo.is_changed = false;
                        photo.image_is_changed = false;
                    }
                });

                vue_model.is_changed = false;
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
