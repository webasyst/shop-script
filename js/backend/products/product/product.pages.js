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
            that.can_use_smarty = options["can_use_smarty"];

            // VUE JS MODELS
            that.lang = options["lang"];
            that.product = options["product"];
            that.pages = formatPages(options["pages"]);
            that.empty_page = formatPage(options["empty_page"]);
            that.errors = {};
            that.errors_global = [];

            // DYNAMIC VARS

            // INIT
            that.vue_model = that.initVue();
            that.init();

            function formatPages(pages) {
                var result = [];

                $.each(pages, function(i, page) {
                    result.push(formatPage(page));
                });

                return result;
            }
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

            that.initDragAndDrop();

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
            var $view_section = that.$wrapper.find(".js-product-pages-section");

            var vue_model = Vue.createApp({
                data() {
                    return {
                        errors       : that.errors,
                        errors_global: that.errors_global,
                        product      : that.product,
                        pages        : that.pages,
                        is_locked    : false
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

                        var white_list = [];

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

                    createPage: function() {
                        that.initEditDialog();
                    },
                    editPage: function(page) {
                        that.initEditDialog(page);
                    },
                    deletePage: function(page) {
                        var self = this;
                        var index = self.pages.indexOf(page);
                        if (index >= 0 && !page.is_locked) {
                            page.is_locked = true;
                            showDeleteConfirm()
                                .always(function () {
                                    page.is_locked = false;
                                })
                                .done(function () {
                                    deleteRequest().done(function () {
                                        page.is_locked = false;
                                        self.pages.splice(index, 1);
                                    });
                                });
                        }

                        function showDeleteConfirm() {
                            var deferred = $.Deferred();
                            var is_success = false;

                            $.waDialog({
                                html: that.templates["delete-confirm-dialog"],
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

                        function deleteRequest() {
                            var data = {
                                page_id: page.id,
                                product_id: that.product.id
                            };

                            return $.post(that.urls["delete"], data, "json");

                        }

                    }
                },
                delimiters: ['{ { ', ' } }'],
                created: function () {
                    $view_section.css("visibility", "");
                },
                mounted: function() {
                    var self = this;

                    that.$wrapper.trigger("section_mounted", ["pages", that]);
                }
            }).mount($view_section[0]);

            return vue_model;
        };

        //

        Section.prototype.initDragAndDrop = function() {
            var that = this;

            var $document = $(document);

            var drag_data = {},
                over_locked = false,
                timer = 0;

            var move_class = "is-moving";

            that.$wrapper.on("dragstart", ".js-page-move-toggle", function(event) {
                var $move = $(this).closest(".s-page-wrapper");

                var page_id = "" + $move.attr("data-id"),
                    page = getPage(page_id);

                if (!page) {
                    console.error("ERROR: page isn't exist");
                    return false;
                }

                event.originalEvent.dataTransfer.setDragImage($move[0], 20, 20);

                $.each(that.pages, function(i, page) {
                    page.is_moving = (page.id === page_id);
                });

                drag_data.move_page = page;

                $document.on("dragover", ".s-page-wrapper", onDragOver);
                $document.on("dragend", onDragEnd);
            });

            function onDragOver(event) {
                event.preventDefault();
                if (!drag_data.move_page) { return false; }

                if (!over_locked) {
                    over_locked = true;
                    movePhoto($(this).closest(".s-page-wrapper"));
                    setTimeout( function() {
                        over_locked = false;
                    }, 100);
                }
            }

            function onDragEnd() {
                drag_data.move_page.is_moving = false;
                drag_data = {};
                $document.off("dragover", ".s-page-wrapper", onDragOver);
                $document.off("dragend", onDragEnd);
                save();
            }

            function movePhoto($over) {
                var page_id = "" + $over.attr("data-id"),
                    page = getPage(page_id);

                if (!page) {
                    console.error("ERROR: page isn't exist");
                    return false;
                }

                if (drag_data.move_page === page) { return false; }

                var move_index = that.pages.indexOf(drag_data.move_page),
                    over_index = that.pages.indexOf(page),
                    before = (move_index > over_index);

                if (over_index !== move_index) {
                    that.pages.splice(move_index, 1);

                    over_index = that.pages.indexOf(page);
                    var new_index = over_index + (before ? 0 : 1);

                    that.pages.splice(new_index, 0, drag_data.move_page);
                }
            }

            function save() {
                that.vue_model.is_locked = true;

                var data = {
                    product_id: that.product.id,
                    pages: getPagesIDs()
                };

                $.post(that.urls["move"], data, "json")
                    .always( function() {
                        that.vue_model.is_locked = false;
                    });

                function getPagesIDs() {
                    var result = [];

                    $.each(that.pages, function(i, page) {
                        result.push(page.id);
                    });

                    return result;
                }
            }

            //

            function getPage(page_id) {
                var result = null;

                $.each(that.pages, function(i, page) {
                    page.id = (typeof page.id === "number" ? "" + page.id : page.id);
                    if (page.id === page_id) {
                        result = page;
                        return false;
                    }
                });

                return result;
            }
        };

        // Сохранение
        Section.prototype.validate = function() {
            var that = this;

            var errors = [];

            $.each(that.errors, function(i, error) {
                if (that.social.is_auto) {
                    var white_list = [];
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

        // Other

        Section.prototype.initEditDialog = function(origin_page) {
            var that = this;

            var page = (typeof origin_page === "object" ? $.wa.clone(origin_page) : $.wa.clone(that.empty_page) );

            var states = {
                reload: true,
                change: false,
                close: false
            };

            $.waDialog({
                html: that.templates["edit-page-dialog"],
                options: {
                    vue_model: null,
                    reload_xhr: null,
                    states: states
                },
                onOpen: initDialog,
                onClose: function(edit_dialog) {
                    if (edit_dialog.options.states.change) {
                        // edit_dialog.hide();

                        $.waDialog({
                            html: that.templates["unsaved-confirm-dialog"],
                            onOpen: function($confirm_dialog, confirm_dialog) {
                                $confirm_dialog.on("click", ".js-save-button", function(event) {
                                    event.preventDefault();
                                    edit_dialog.options.states.change = false;
                                    confirm_dialog.close();
                                    edit_dialog.vue_model.save().done( function() {
                                        edit_dialog.close();
                                    });
                                });

                                $confirm_dialog.on("click", ".js-leave-button", function(event) {
                                    event.preventDefault();
                                    edit_dialog.options.states.change = false;
                                    confirm_dialog.close();
                                    edit_dialog.close();
                                });
                            }
                        });

                        return false;
                    } else if (edit_dialog.options.states.reload) {
                        if (!edit_dialog.options.reload_xhr) {
                            edit_dialog.$wrapper.addClass("is-locked");
                            edit_dialog.options.states.close = true;
                            edit_dialog.options.reload_xhr = $.wa_shop_products.router.reload()
                                .always( function() {
                                    edit_dialog.options.states.close = false;
                                    edit_dialog.options.reload_xhr = null;
                                    edit_dialog.$wrapper.removeClass("is-locked");
                                })
                                .done( function() {
                                    edit_dialog.options.states.reload = false;
                                    edit_dialog.close();
                                });
                        }
                        return false;
                    }
                }
            });

            function initDialog($dialog, dialog) {
                var $section = $dialog.find(".js-vue-node-wrapper");

                var transliterate_timer = 0,
                    transliterate_xhr = null;

                dialog.vue_model = Vue.createApp({
                    data() {
                        return {
                            page: page,
                            pages: that.pages,
                            origin_page: (origin_page ? origin_page : null),
                            use_transliterate: !page.url,
                            is_locked: false,
                            is_delete: false,
                            is_changed: false,
                            states: dialog.options.states,
                            errors: {}
                        }
                    },
                    delimiters: ['{ { ', ' } }'],
                    components: {
                        "component-flex-textarea": {
                            props: ["modelValue", "placeholder"],
                            emits: ["update:modelValue", "blur", "ready"],
                            data: function() {
                                return {
                                    offset: 0
                                };
                            },
                            template: `<textarea v-bind:placeholder="placeholder" v-bind:value="modelValue" v-on:input="$emit('update:modelValue', $event.target.value)" v-on:blur="$emit('blur', $event.target.value)"></textarea>`,
                            delimiters: ['{ { ', ' } }'],
                            updated: function() {
                                var self = this;
                                var $textarea = $(self.$el);

                                $textarea.css("min-height", 0);
                                var scroll_h = $textarea[0].scrollHeight + self.offset;
                                $textarea.css("min-height", scroll_h + "px");
                            },
                            mounted: function() {
                                var self = this;
                                var $textarea = $(self.$el);

                                self.offset = $textarea[0].offsetHeight - $textarea[0].clientHeight;

                                $textarea.css("min-height", 0);
                                var scroll_h = $textarea[0].scrollHeight + self.offset;
                                $textarea.css("min-height", scroll_h + "px");

                                self.$emit("ready", self.$el.value);
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
                            if (error.id) {
                                self.errors[error.id] = error;
                            }
                        },
                        removeErrors: function(errors) {
                            var self = this;

                            // Очистка всех ошибок
                            if (errors === null) {
                                $.each(self.errors, function(key) {
                                    delete self.errors[key];
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

                            if (self.errors[error_id]) {
                                delete self.errors[error_id];
                            }
                        },
                        validate: function(render_errors) {
                            var self = this,
                                result = [];

                            var page_url = $.trim(self.page.url);
                            if (!page_url.length || page_url.substr(0, 1) === "/") {
                                var error = {
                                    "id": "url_required",
                                    "text": ""
                                };

                                result.push(error);

                                if (render_errors) { self.renderError(error); }
                            }

                            dialog.resize();

                            return result;
                        },

                        onChangeUrl: function(event) {
                            var self = this;

                            self.use_transliterate = !self.page.url;

                            var value = event.target.value;
                            if (value) {
                                if (self.errors["url_required"]) {
                                    delete self.errors["url_required"];
                                    dialog.resize();
                                }
                            }
                        },
                        onChangeName: function() {
                            var self = this;

                            if (!$.trim(self.page.name).length) { return false; }

                            if (transliterate_xhr) {
                                transliterate_xhr.abort();
                                transliterate_xhr = null;
                            }

                            if (self.use_transliterate) {
                                clearTimeout(transliterate_timer);
                                transliterate_timer = setTimeout( function() {
                                    transliterate_xhr = request()
                                        .always( function() {
                                            transliterate_xhr = null;
                                        })
                                        .done( function(response) {
                                            if (self.use_transliterate) {
                                                self.page.url = response.data;
                                                if (self.page.url) {
                                                    self.removeError("url_required");
                                                    self.$nextTick( function() {
                                                        dialog.resize();
                                                    });
                                                }
                                                $dialog.trigger("change");
                                            }
                                        });
                                }, 1000);
                            }

                            function request() {
                                return $.get(that.urls["transliterate"], { str: self.page.name }, "json");
                            }
                        },
                        onChangeStatus: function() {
                            var self = this;
                            self.is_changed = dialog.options.states.change = true;
                        },
                        toggleOptions: function() {
                            var self = this;
                            self.page.expanded = !self.page.expanded;
                            self.$nextTick( function() {
                                dialog.resize();
                            });
                        },
                        save: function() {
                            var self = this;

                            var errors = self.validate(true);
                            if (errors.length) { return false; }

                            if (self.is_changed && !self.is_locked) {
                                return saveRequest(self);
                            } else {
                                var deferred = $.Deferred();
                                deferred.reject();
                                return deferred.promise();
                            }
                        },
                        deletePage: function(page) {
                            var self = this;

                            var index = self.pages.indexOf(page);
                            if (index >= 0)  {
                                dialog.hide();
                                showDeleteConfirm()
                                    .always(function (confirm_res) {
                                        if (!confirm_res) {
                                            dialog.show();
                                        }
                                    })
                                    .done( function() {
                                        self.is_locked = true;
                                        self.is_delete = true;
                                        deleteRequest().done( function() {
                                            self.is_locked = false;
                                            self.is_delete = false;
                                            self.pages.splice(index, 1);
                                        });
                                    });
                            }

                            function showDeleteConfirm() {
                                var deferred = $.Deferred();
                                var is_success = false;

                                $.waDialog({
                                    html: that.templates["delete-confirm-dialog"],
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
                                            deferred.resolve(is_success);
                                        } else {
                                            deferred.reject(is_success);
                                        }
                                    }
                                });

                                return deferred.promise();
                            }

                            function deleteRequest() {
                                var data = {
                                    page_id: page.id,
                                    product_id: that.product.id
                                };

                                return $.post(that.urls["delete"], data, "json");
                            }
                        }
                    },
                    created: function() {
                        $section.css("visibility", "");
                    },
                    mounted: function() {
                        var self = this;

                        initEditor();

                        dialog.resize();

                        var is_ready = false;
                        setTimeout( function() {
                            is_ready = true;
                        }, 500);

                        $dialog.on("input change is_changed", function() {
                            if (is_ready) {
                                self.is_changed = dialog.options.states.change = true;
                            }
                        });

                        // Это хак, ибо непонятно как определить внутри редактора момент когда ресурс загрузился.
                        autoResize();

                        function autoResize() {
                            var stop_time = 5000,
                                time = 500;

                            var interval = setInterval( function() {
                                if (stop_time > 0) {
                                    stop_time -= time;
                                    dialog.resize();
                                } else {
                                    clearInterval(interval);
                                }
                            }, time);
                        }

                    }
                }).mount($section[0]);

                function initEditor() {
                    var $section = $dialog.find(".js-editor-section"),
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

                    $section.find(".js-editor-type-toggle").waToggle({
                        type: "tabs",
                        ready: function(toggle) {
                            toggle.$wrapper.find("[data-id=\"" + active_type_id + "\"]:first").trigger("click");
                        },
                        change: function(event, target, toggle) {
                            onTypeChange($(target).data("id"));
                        }
                    });

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
                            var value = editor.getValue();
                            page.content = value;
                            $textarea.val(value).trigger("change");
                            dialog.resize();
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
                                imageUploadFields: {
                                    '_csrf': getCsrf()
                                },
                                callbacks: {}
                            }, (options || {}));

                            if (options.uploadFields || options.uploadImageFields) {
                                options['imageUploadFields'] = options.uploadFields || options.uploadImageFields;
                            }

                            var resize_timer = 0;

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
                                sync: function(html) {
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

                                    page.content = html;
                                    this.$textarea.val(html).trigger("change");

                                    clearTimeout(resize_timer);
                                    resize_timer = setTimeout( function() {
                                        var $content = $dialog.find(".dialog-content:first");
                                        if ($content.length) {
                                            var top = $content.scrollTop();
                                            dialog.resize();
                                            $content.scrollTop(top);
                                        }
                                    }, 50);
                                },
                                syncClean: function(html) {
                                    // Unescape '->' in smarty tags
                                    return html.replace(/\{[a-z\$'"_\(!+\-][^\}]*\}/gi, function (match) {
                                        return match.replace(/-&gt;/g, '->');
                                    });
                                }
                            }, (options.callbacks || {}));

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
                                    html_editor.session.setValue($textarea.val());
                                    activeType(type_id);
                                    break;
                                case "wysiwyg":
                                    $html_wrapper.hide();
                                    $wysiwyg_wrapper.show();
                                    if (!wysiwyg_redactor) {
                                        wysiwyg_redactor = initRedactor($wysiwyg_wrapper, $textarea);
                                    }
                                    $textarea.redactor("code.set", $textarea.val());
                                    activeType(type_id);
                                    break;
                                default:
                                    break;
                            }
                            active_type_id = type_id;
                            dialog.resize();
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
                }

                function saveRequest(self) {
                    if (!self.is_locked) {
                        self.is_locked = true;

                        var data = getData();

                        return $.post(that.urls["save"], data, "json")
                            .always( function() {
                                self.is_locked = false;
                            })
                            .done( function(response) {
                                if (response.status === "ok") {
                                    self.is_changed = dialog.options.states.change = false;
                                    var page_data = response.data;

                                    // edit
                                    if (origin_page && origin_page.id) {
                                        origin_page.id = page_data.id;
                                        origin_page.url = page_data.url;
                                        origin_page.name = page_data.name;
                                        origin_page.title = page_data.title;
                                        origin_page.status = (page_data.status !== "0");
                                        origin_page.content = page_data.content;
                                        origin_page.description = page_data.description;
                                        origin_page.keywords = page_data.keywords;

                                    // create
                                    } else {
                                        page.id = page_data.id;
                                        page.url = page_data.url;
                                        page.name = page_data.name;
                                        page.title = page_data.title;
                                        page.status = (page_data.status !== "0");
                                        page.content = page_data.content;
                                        page.description = page_data.description;
                                        page.keywords = page_data.keywords;
                                        that.pages.push(page);
                                    }
                                } else if (response.errors) {
                                    self.renderErrors(response.errors);
                                }
                            });
                    }
                }

                function getData() {
                    // var $textarea = $dialog.find(".js-product-description-textarea:first");
                    // page.content = $textarea.val();

                    return [
                        {
                            "name" : "product_id",
                            "value": that.product.id
                        },
                        {
                            "name" : "page[id]",
                            "value": page.id
                        },
                        {
                            "name" : "page[status]",
                            "value": (page.status ? "1" : "0")
                        },
                        {
                            "name" : "page[url]",
                            "value": page.url
                        },
                        {
                            "name" : "page[name]",
                            "value": page.name
                        },
                        {
                            "name" : "page[title]",
                            "value": page.title
                        },
                        {
                            "name" : "page[content]",
                            "value": page.content
                        },
                        {
                            "name" : "page[description]",
                            "value": page.description
                        },
                        {
                            "name" : "page[keywords]",
                            "value": page.keywords
                        }
                    ];
                }
            }
        };

        return Section;

        function formatPage(page) {
            page.is_moving = false;
            page.is_locked = false;
            page.expanded = false;

            if (typeof page.status !== "boolean") {
                page.status = (page.status !== "0");
            }

            page.expanded = false;

            return page;
        }

    })($);

    $.wa_shop_products.init.initProductPagesSection = function(options) {
        return new Section(options);
    };

})(jQuery);
