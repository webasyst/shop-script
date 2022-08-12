( function($) {

    var Page = ( function($) {

        function Page(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];

            // CONST
            that.components = options["components"];
            that.templates  = options["templates"];
            that.tooltips   = options["tooltips"];
            that.locales    = options["locales"];
            that.urls       = options["urls"];

            // CONST
            that.category_sort_variants = options["category_sort_variants"];
            that.category_types         = options["category_types"];
            that.header_columns         = options["header_columns"];

            // VUE VARS
            that.categories = formatCategories(options["categories"]);
            that.categories_object = getCategoriesObject(that.categories);
            that.storefronts = options["storefronts"];
            that.states = {
                move_locked: false,
                create_locked: false
            };

            // INIT
            that.vue_model = that.initVue();

            function formatCategories(categories) {
                var result = [];

                $.each(categories, function(i, category) {
                    result.push(that.formatCategory(category));
                });

                return result;
            }

            function getCategoriesObject(categories) {
                let result = {},
                    storage = that.storage(),
                    expanded_categories = (storage && storage.expanded_categories && storage.expanded_categories.length ? storage.expanded_categories : []);

                setCategories(categories);

                function setCategories(categories) {
                    $.each(categories, function(i, category) {
                        result[category.id] = category;

                        if (expanded_categories.indexOf(category.id) >= 0) {
                            category.states.expanded = true;
                        }

                        if (category.categories.length) { setCategories(category.categories); }
                    });
                }

                return result;
            }
        }

        Page.prototype.init = function(vue_model) {
            var that = this;

            that.initDragAndDrop();

            $.each(that.tooltips, function(i, tooltip) {
                $.wa.new.Tooltip(tooltip);
            });

            console.log( that );
        };

        Page.prototype.initVue = function() {
            var that = this;

            var $vue_section = that.$wrapper.find("#js-vue-section");

            Vue.component("component-checkbox", {
                props: ["value", "label", "disabled", "field_id"],
                data: function() {
                    var self = this;
                    self.disabled = (typeof self.disabled === "boolean" ? self.disabled : false);
                    return {
                        tag: (self.label !== false ? "label" : "span"),
                        id: (typeof self.field_id === "string" ? self.field_id : "")
                    }
                },
                template: that.components["component-checkbox"],
                delimiters: ['{ { ', ' } }'],
                methods: {
                    onChange: function(event) {
                        var self = this;
                        self.$emit("input", self.value);
                        self.$emit("change", self.value);
                    }
                }
            });

            Vue.component("component-radio", {
                props: ["value", "name", "val", "label", "disabled"],
                data: function() {
                    let self = this;
                    return {
                        tag: (self.label !== false ? "label" : "span"),
                        val: (typeof self.val === "string" ? self.value : ""),
                        name: (typeof self.name === "string" ? self.name : ""),
                        disabled: (typeof self.disabled === "boolean" ? self.disabled : false)
                    }
                },
                template: that.components["component-radio"],
                delimiters: ['{ { ', ' } }'],
                methods: {
                    onChange: function(event) {
                        let self = this;
                        self.$emit("input", self.value);
                        self.$emit("change", self.value);
                    }
                }
            });

            Vue.component("component-dropdown", {
                props: ["value", "options", "disabled", "button_class", "body_width", "body_class", "empty_option"],
                data: function() {
                    var self = this;

                    self.disabled = (typeof self.disabled === "boolean" ? self.disabled : false);
                    self.body_width = (typeof self.body_width === "string" ? self.body_width : "");
                    self.body_class = (typeof self.body_class === "string" ? self.body_class : "");
                    self.button_class = (typeof self.button_class === "string" ? self.button_class : "");
                    self.empty_option = (typeof self.empty_option !== "undefined" ? self.empty_option : false);

                    return {
                        states: { is_changed: false }
                    }
                },
                template: that.components["component-dropdown"],
                delimiters: ['{ { ', ' } }'],
                computed: {
                    active_option: function() {
                        let self = this,
                            active_option = null;

                        if (self.value) {
                            var filter_search = self.options.filter( function(option) {
                                return (option.value === self.value);
                            });
                            active_option = (filter_search.length ? filter_search[0] : active_option);
                        } else if (!self.empty_option) {
                            active_option = self.options[0];
                        }

                        return active_option;
                    },
                    formatted_options: function() {
                        var self = this,
                            result = [];

                        if (self.empty_option) {
                            result.push({
                                name: (typeof self.empty_option === "string" ? self.empty_option : ""),
                                value: ""
                            });
                        }

                        $.each(self.options, function(i, option) {
                            result.push(option);
                        });

                        return result;
                    }
                },
                methods: {
                    change: function(option) {
                        let self = this;

                        if (option.disabled) { return false; }

                        if (self.active_option !== option) {
                            self.$emit("input", option.value);
                            self.$emit("change", option.value);
                        }
                        self.dropdown.hide();
                    }
                },
                mounted: function() {
                    let self = this;

                    if (self.disabled) { return false; }

                    self.dropdown = $(self.$el).waDropdown({
                        hover : false,
                        open: function() {
                            self.$emit("focus");
                        },
                        close: function() {
                            self.$emit("blur");
                        }
                    }).waDropdown("dropdown");
                }
            });

            Vue.component("component-switch", {
                props: ["value", "disabled"],
                data: function() {
                    return {
                        active: (typeof this.value === "boolean" ? this.value : false),
                        disabled: (typeof this.disabled === "boolean" ? this.disabled : false)
                    };
                },
                template: '<div class="switch wa-small"></div>',
                delimiters: ['{ { ', ' } }'],
                mounted: function() {
                    var self = this;

                    $.waSwitch({
                        $wrapper: $(self.$el),
                        active: self.active,
                        disabled: self.disabled,
                        change: function(active, wa_switch) {
                            self.$emit("change", active);
                            self.$emit("input", active);
                        }
                    });
                }
            });

            Vue.component("component-textarea", {
                props: ["value", "placeholder", "readonly", "cancel", "focus"],
                data: function() {
                    var self = this;
                    self.focus = (typeof self.focus === "boolean" ? self.focus : false);
                    self.cancel = (typeof self.cancel === "boolean" ? self.cancel : false);
                    self.readonly = (typeof self.readonly === "boolean" ? self.readonly : false);
                    return {
                        offset: 0,
                        $textarea: null,
                        start_value: null
                    };
                },
                template: '<textarea v-bind:placeholder="placeholder" v-bind:value="value" v-bind:readonly="readonly" v-on:focus="onFocus" v-on:input="onInput" v-on:keydown.esc="onEsc" v-on:blur="onBlur"></textarea>',
                delimiters: ['{ { ', ' } }'],
                methods: {
                    onInput: function($event) {
                        var self = this;
                        self.update();
                        self.$emit('input', $event.target.value);
                    },
                    onFocus: function($event) {
                        var self = this;
                        self.start_value = $event.target.value;
                    },
                    onBlur: function($event) {
                        var self = this,
                            value = $event.target.value;

                        if (value === self.start_value) {
                            if (self.cancel) { self.$emit("cancel"); }
                        } else {
                            self.$emit("change", value);
                        }
                    },
                    onEsc: function($event) {
                        var self = this;
                        if (self.cancel) {
                            $event.target.value = self.value = self.start_value;
                            self.$emit('input', self.value);
                            self.$textarea.trigger("blur");
                        }
                    },
                    update: function() {
                        var self = this;
                        var $textarea = $(self.$el);

                        $textarea
                            .css("overflow", "hidden")
                            .css("min-height", 0);
                        var scroll_h = $textarea[0].scrollHeight + self.offset;
                        $textarea
                            .css("min-height", scroll_h + "px")
                            .css("overflow", "");
                    }
                },
                mounted: function() {
                    var self = this,
                        $textarea = self.$textarea = $(self.$el);

                    self.offset = $textarea[0].offsetHeight - $textarea[0].clientHeight;

                    $textarea
                        .css("overflow", "hidden")
                        .css("min-height", 0);
                    var scroll_h = $textarea[0].scrollHeight + self.offset;
                    $textarea
                        .css("min-height", scroll_h + "px")
                        .css("overflow", "");

                    self.$emit("ready", self.$el.value);

                    if (self.focus) { $textarea.trigger("focus"); }
                }
            });

            return new Vue({
                el: $vue_section[0],
                data: {
                    categories: that.categories,
                    states    : that.states
                },
                components: {
                    "component-dropdown-categories-sorting": {
                        template: that.components["component-dropdown-categories-sorting"],
                        delimiters: ['{ { ', ' } }'],
                        computed: {},
                        methods: {
                            change: function(sort) {
                                let self = this;
                                self.dropdown.hide();
                                self.$emit("change", sort);
                            }
                        },
                        mounted: function() {
                            let self = this,
                                $dropdown = $(self.$el).find(".js-dropdown");

                            self.dropdown = $dropdown.waDropdown({ hover: false }).waDropdown("dropdown");
                        }
                    },
                    "component-search-categories": {
                        data: function() {
                            var self = this;
                            return {
                                search_string: "",
                                position: 0,
                                selection: []
                            };
                        },
                        template: that.components["component-search-categories"],
                        delimiters: ['{ { ', ' } }'],
                        computed: {
                            is_start: function() {
                                var self = this;
                                return !(self.position > 1);
                            },
                            is_end: function() {
                                var self = this;
                                return (self.position === self.selection.length);
                            }
                        },
                        methods: {
                            search: function() {
                                var self = this;

                                var categories = that.categories,
                                    categories_object = that.categories_object,
                                    selection = [];

                                // Сбрасываем позицию
                                self.position = 0;

                                // Делаем выборку
                                checkCategory(categories);

                                // Сохраняем выборку
                                self.selection = selection;

                                // Подсвечиваем родителей
                                $.each(selection, function(i, category) {
                                    markCategory(category);
                                });

                                function markCategory(category) {
                                    if (category.parent_id !== "0") {
                                        var parent_category = categories_object[category.parent_id];
                                        if (parent_category) {
                                            parent_category.states.is_wanted_inside = true;
                                            markCategory(parent_category);
                                        }
                                    }
                                }

                                function checkCategory(categories) {
                                    $.each(categories, function(i, category) {
                                        category.states.is_wanted_inside = false;

                                        var is_wanted = (self.search_string.length > 0 && category.name.toLowerCase().indexOf( self.search_string.toLowerCase() ) >= 0 );
                                        if (is_wanted) { selection.push(category); }
                                        category.states.is_wanted = is_wanted;

                                        if (category.categories.length) {
                                            checkCategory(category.categories);
                                        }
                                    });
                                }
                            },
                            moveUp: function() {
                                var self = this;

                                if (self.position > 1) {
                                    self.position -= 1;
                                    self.move(self.selection[self.position - 1]);
                                }
                            },
                            moveDown: function() {
                                var self = this;
                                if (self.position < self.selection.length) {
                                    self.position += 1;
                                    self.move(self.selection[self.position - 1]);
                                }
                            },
                            move: function(category) {
                                var self = this;

                                showTree(category);

                                self.$nextTick(moveTo);

                                function showTree(category) {
                                    if (category.parent_id !== "0") {
                                        var parent_category = that.categories_object[category.parent_id];
                                        if (parent_category) {
                                            parent_category.states.expanded = true;
                                            showTree(parent_category);
                                        }
                                    }
                                }

                                function moveTo() {
                                    var $category = that.$wrapper.find(".s-category-wrapper[data-id=\""+category.id+"\"]");
                                    if ($category.length) {
                                        var $wrapper = $category.closest(".s-categories-table-section");

                                        var scroll_top = 0,
                                            lift = 300;

                                        if ($category[0].offsetTop > lift) { scroll_top = $category[0].offsetTop - lift; }

                                        $wrapper.scrollTop(scroll_top);

                                        var active_class = "is-highlighted";
                                        $category.addClass(active_class);
                                        setTimeout( function() {
                                            $category.removeClass(active_class);
                                        }, 1000);
                                    }
                                }
                            },
                            revert: function() {
                                var self = this;
                                self.search_string = "";
                                self.search();
                            }
                        }
                    },
                    "component-categories": {
                        props: ["categories"],
                        data: function() {
                            var self = this;

                            var storage_name = "wa_shop_categories_columns",
                                storage = getStorage();

                            $.each(that.header_columns, function(i, column) {
                                var column_storage = storage[column.id];
                                if (column_storage && column_storage.width > 0) {
                                    column.width = column_storage.width;
                                }
                            });

                            return {
                                columns: that.header_columns,
                                storage: storage,
                                storage_name: storage_name
                            };

                            function getStorage() {
                                var storage = localStorage.getItem(storage_name);
                                if (!storage) { storage = {}; }
                                else { storage = JSON.parse(storage); }
                                return storage;
                            }
                        },
                        template: that.components["component-categories"],
                        delimiters: ['{ { ', ' } }'],
                        components: {
                            "component-category" : {
                                name: "component-category",
                                props: ["category"],
                                data: function() {
                                    var self = this;
                                    return {
                                        root_states: that.states,
                                        states: {
                                            edit_locked: false,
                                            name_locked: false,
                                            status_locked: false,
                                            create_locked: false
                                        }
                                    }
                                },
                                template: that.components["component-category"],
                                delimiters: ['{ { ', ' } }'],
                                components: {
                                    "component-category-sorting": {
                                        props: ["category"],
                                        data: function() {
                                            var self = this;
                                            return {
                                                options: that.category_sort_variants,
                                                states: {
                                                    sort_locked: false
                                                }
                                            }
                                        },
                                        template: that.components["component-category-sorting"],
                                        delimiters: ['{ { ', ' } }'],
                                        computed: {
                                            active_option: function() {
                                                var self = this,
                                                    option_search = self.options.filter( function(option) { return (self.category.sort_products === option.value); });
                                                return (option_search ? option_search[0] : self.options[0]);
                                            }
                                        },
                                        methods: {
                                            setup: function() {
                                                var self = this;

                                                self.states.sort_locked = true;
                                                $.post(that.urls["category_sort_dialog"], { category_id: self.category.id })
                                                    .always( function() {
                                                        self.states.sort_locked = false;
                                                    })
                                                    .done( function(html) {
                                                        $.waDialog({ html: html })
                                                    });
                                            },
                                            change: function(option) {
                                                var self = this,
                                                    data = { category_id: self.category.id, sort_products: option.value };

                                                self.dropdown.hide();
                                                self.states.sort_locked = true;

                                                request(data)
                                                    .always( function() {
                                                        self.states.sort_locked = false;
                                                    })
                                                    .done( function() {
                                                        self.active_option = option;
                                                        self.category.sort_products = option.value;
                                                    });

                                                function request() {
                                                    var deferred = $.Deferred();

                                                    $.post(that.urls["category_sort"], data, "json")
                                                        .fail( function() {
                                                            deferred.reject();
                                                        })
                                                        .done( function(response) {
                                                            if (response.status === "ok") {
                                                                deferred.resolve(response.data);
                                                            } else {
                                                                deferred.reject(response.errors);
                                                            }
                                                        });

                                                    return deferred.promise();
                                                }
                                            }
                                        },
                                        mounted: function () {
                                            var self = this;
                                            var $dropdown = $(self.$el).find(".js-dropdown");

                                            self.dropdown = $dropdown.waDropdown({ hover: false }).waDropdown("dropdown");
                                        }
                                    },
                                    "component-category-filters": {
                                        props: ["category"],
                                        data: function() {
                                            var self = this;
                                            return {}
                                        },
                                        template: that.components["component-category-filters"],
                                        delimiters: ['{ { ', ' } }'],
                                        computed: {},
                                        methods: {
                                            setup: function() {
                                                var self = this;
                                                console.log( 111 );
                                            }
                                        }
                                    },
                                    "component-dropdown-storefronts": {
                                        props: ["value", "category"],
                                        data: function() {
                                            var self = this,
                                                type = "";

                                            return {
                                                type: (!self.value.length ? "all" : "selection"),
                                                storefronts: getStorefronts(self.value),
                                                states: {
                                                    locked: false
                                                }
                                            };

                                            function getStorefronts(active_storefronts) {
                                                var storefronts = $.wa.clone(that.storefronts);
                                                $.each(storefronts, function(i, storefront) {
                                                    storefront.active = (active_storefronts.indexOf(storefront.url) >= 0);
                                                    if (storefront.active) { type = "selection"; }
                                                });
                                                return storefronts;
                                            }
                                        },
                                        template: that.components["component-dropdown-storefronts"],
                                        delimiters: ['{ { ', ' } }'],
                                        watch: {
                                            "type": function() {
                                                var self = this;
                                                self.dropdown.resize();
                                            }
                                        },
                                        computed: {
                                            radio_name: function() {
                                                var self = this;
                                                return "category["+self.category.id+"]radio_option";
                                            },
                                            selected_storefronts: function() {
                                                var self = this;
                                                return self.storefronts.filter( function(storefront) {
                                                    return storefront.active;
                                                });
                                            }
                                        },
                                        methods: {
                                            save: function() {
                                                var self = this,
                                                    result = [];

                                                if (self.type !== "all") {
                                                    $.each(self.selected_storefronts, function(i, storefront) {
                                                        result.push(storefront.url);
                                                    });
                                                }

                                                self.dropdown.hide();

                                                var data = { category_id: self.category.id };
                                                if (result.length) { data["storefronts"] = result; }

                                                self.states.locked = true;
                                                $.post(that.urls["category_storefronts"], data, "json")
                                                    .always( function() {
                                                        self.states.locked = false;
                                                    })
                                                    .done( function(response) {
                                                        if (response.status === "ok") {
                                                            self.$emit("input", result);
                                                        } else {
                                                            console.log( "ERRORS: cant save storefronts", response.errors );
                                                        }
                                                    });
                                            }
                                        },
                                        mounted: function() {
                                            var self = this,
                                                $dropdown = $(self.$el).find(".dropdown");

                                            self.dropdown = $dropdown.waDropdown({ hover: false, protect: { right: 150 } }).waDropdown("dropdown");
                                        }
                                    }
                                },
                                computed: {
                                    category_class: function() {
                                        const self = this,
                                            result = [];

                                        if (self.category.states.move) {
                                            result.push("is-moving");
                                        }
                                        if (self.category.states.is_wanted) {
                                            result.push("is-wanted");
                                        }
                                        if (self.category.states.is_wanted_inside && !self.category.states.expanded) {
                                            result.push("is-wanted-inside");
                                        }
                                        if (self.category.states.drop) {
                                            result.push("is-drop");
                                            if (self.category.states.drop_inside) {
                                                result.push("is-drop-inside");
                                                if (self.category.states.drop_error) {
                                                    result.push("is-drop-error");
                                                }
                                            }
                                        }

                                        return result.join(" ");
                                    }
                                },
                                methods: {
                                    extendCategory: function() {
                                        var self = this;
                                        self.category.states.expanded = !self.category.states.expanded;
                                        that.storage(true);
                                    },
                                    changeStatus: function() {
                                        var self = this;

                                        var visible = !self.category.states.visible,
                                            data = { category_id: self.category.id, status: (visible ? "1" : "0") };

                                        self.states.status_locked = true;
                                        request(data)
                                            .always( function() {
                                                self.states.status_locked = false;
                                            })
                                            .done( function() {
                                                self.category.states.visible = visible;
                                                self.category.status = (visible ? "1" : "0");
                                            });

                                        function request(data) {
                                            var deferred = $.Deferred();

                                            $.post(that.urls["category_status"], data, "json")
                                                .fail( function() {
                                                    deferred.reject();
                                                })
                                                .done( function(response) {
                                                    if (response.status === "ok") {
                                                        deferred.resolve();
                                                    } else {
                                                        deferred.reject(response.errors);
                                                    }
                                                });

                                            return deferred.promise();
                                        }
                                    },
                                    getColumnWidth: function(column_id) {
                                        let self = this,
                                            column = that.header_columns[column_id],
                                            result = "";

                                        if (column.width > 0) {
                                            result = column.width + "px";
                                        } else if (column.min_width > 0) {
                                            result = column.min_width + "px";
                                        };

                                        return result;
                                    },

                                    onChangeName: function() {
                                        var self = this;

                                        var data = { category_id: self.category.id, name: self.category.name };

                                        self.states.name_locked = true;
                                        $.post(that.urls["category_name"], data, "json")
                                            .always( function() {
                                                self.states.name_locked = false;
                                            })
                                            .done( function(response) {
                                                if (response.status !== "ok") {
                                                    console.log( "ERRORS: cant save name", response.errors );
                                                }
                                            });

                                    },
                                    edit: function() {
                                        var self = this;

                                        if (!self.states.edit_locked) {
                                            self.states.edit_locked = true;
                                            $.post(that.urls["category_dialog"], { category_id: self.category.id }, "json")
                                                .always( function() {
                                                    self.states.edit_locked = false;
                                                })
                                                .done( function(html) {
                                                    that.showCategoryDialog(html);
                                                });
                                        }
                                    },
                                    create: function() {
                                        var self = this;

                                        if (!self.states.create_locked) {
                                            self.states.create_locked = true;
                                            $.post(that.urls["category_dialog"], { parent_id: self.category.id }, "json")
                                                .always( function() {
                                                    self.states.create_locked = false;
                                                })
                                                .done( function(html) {
                                                    that.showCategoryDialog(html);
                                                });
                                        }
                                    },
                                    categoryClone: function() {
                                        var self = this,
                                            category = self.category;

                                        $.waDialog({
                                            html: that.templates["dialog-category-clone"],
                                            onOpen: initDialog
                                        });

                                        function initDialog($dialog, dialog) {
                                            var $vue_section = $dialog.find(".js-vue-section");

                                            new Vue({
                                                el: $vue_section[0],
                                                data: {
                                                    category: category,
                                                    copy_categories: true,
                                                    copy_products: false,
                                                    locked: false
                                                },
                                                delimiters: ['{ { ', ' } }'],
                                                computed: {},
                                                methods: {
                                                    cloneCategory: function() {
                                                        var self = this;

                                                        self.locked = true;
                                                        request()
                                                            .fail( function() { self.locked = false; })
                                                            .done( function() {
                                                                $.wa_shop_products.router.reload().done( function() {
                                                                    self.locked = false;
                                                                    dialog.close();
                                                                });
                                                            });

                                                        function request() {
                                                            var href = that.urls["category_clone"],
                                                                data = {
                                                                    category_id: self.category.id,
                                                                    copy_products: (self.copy_products ? "1" : "0"),
                                                                    copy_categories: (self.copy_categories ? "1" : "0")
                                                                };

                                                            var deferred = $.Deferred();

                                                            $.post(href, data, "json")
                                                                .fail( function() {
                                                                    deferred.reject();
                                                                })
                                                                .done( function(response) {
                                                                    if (response.status === "ok") {
                                                                        deferred.resolve();
                                                                    } else {
                                                                        deferred.reject(response.errors);
                                                                    }
                                                                });

                                                            return deferred.promise();
                                                        }
                                                    }
                                                },
                                                created: function () {
                                                    $vue_section.css("visibility", "");
                                                }
                                            });
                                        }
                                    },
                                    categoryDelete: function() {
                                        var self = this,
                                            category = self.category;

                                        $.waDialog({
                                            html: that.templates["dialog-category-delete"],
                                            onOpen: initDialog
                                        });

                                        function initDialog($dialog, dialog) {

                                            var $vue_section = $dialog.find(".js-vue-section");

                                            new Vue({
                                                el: $vue_section[0],
                                                data: {
                                                    category: category,
                                                    remove_products: false,
                                                    remove_categories: false,
                                                    locked: false
                                                },
                                                delimiters: ['{ { ', ' } }'],
                                                watch: {
                                                    "remove_categories": function() {
                                                        const self = this;
                                                        self.$nextTick( function() { dialog.resize(); });
                                                    }
                                                },
                                                computed: {
                                                    products_count: function() {
                                                        const self = this;

                                                        var result = (self.category.count > 0 ? self.category.count : 0);

                                                        if (self.remove_categories && self.category.categories.length) {
                                                            $.each(self.category.categories, function(i, category) {
                                                                if (category.type !== '1') {
                                                                    result += (category.count > 0 ? category.count : 0);
                                                                }
                                                            });
                                                        }

                                                        // if (self.category.type === '1') { result = 0; }

                                                        return result;
                                                    },
                                                    categories_count: function() {
                                                        const self = this;
                                                        return self.category.categories.length;
                                                    }
                                                },
                                                methods: {
                                                    removeCategory: function() {
                                                        var self = this;

                                                        self.locked = true;
                                                        request()
                                                            .fail( function() { self.locked = false; })
                                                            .done( function() {
                                                                $.wa_shop_products.router.reload().done( function() {
                                                                    self.locked = false;
                                                                    dialog.close();
                                                                });
                                                            });

                                                        function request() {
                                                            var href = that.urls["category_delete"],
                                                                data = {
                                                                    category_id: self.category.id,
                                                                    remove_products: (self.remove_products ? "1" : "0"),
                                                                    remove_categories: (self.remove_categories ? "1" : "0")
                                                                };

                                                            var deferred = $.Deferred();

                                                            $.post(href, data, "json")
                                                                .fail( function() {
                                                                    deferred.reject();
                                                                })
                                                                .done( function(response) {
                                                                    if (response.status === "ok") {
                                                                        deferred.resolve();
                                                                    } else {
                                                                        deferred.reject(response.errors);
                                                                    }
                                                                });

                                                            return deferred.promise();
                                                        }
                                                    }
                                                },
                                                created: function () {
                                                    $vue_section.css("visibility", "");
                                                },
                                                mounted: function() {
                                                    const self = this;
                                                    dialog.resize();
                                                }
                                            });
                                        }
                                    }
                                }
                            },
                            "component-categories-header-column": {
                                props: ["column"],
                                data: function() {
                                    var self = this;
                                    return {
                                        states: {
                                            locked: false,
                                            width_timer: 0
                                        }
                                    };
                                },
                                template: that.components["component-categories-header-column"],
                                delimiters: ['{ { ', ' } }'],
                                computed: {
                                    column_width: function() {
                                        let self = this,
                                            result = "";

                                        if (self.column.width > 0) {
                                            result = self.column.width + "px";
                                        } else if (self.column.min_width > 0) {
                                            result = self.column.min_width + "px";
                                        };

                                        return result;
                                    }
                                },
                                methods: {
                                    onDragColumn: function(start_event) {
                                        var self = this;

                                        if (self.column.width_locked) { return false; }

                                        var min_width = (self.column.min_width > 0 ? self.column.min_width : 50);

                                        var $target = $(start_event.currentTarget),
                                            $column = $target.closest(".s-column"),
                                            $document = $(document);

                                        var width = $column.width();

                                        // Включаем двигатель
                                        $document.on("mousemove", mouseMove);

                                        // Выключаем двигатель
                                        $document.one("mouseup", function() {
                                            self.saveColumnWidth();
                                            $document.off("mousemove", mouseMove);
                                        });

                                        function mouseMove(event) {
                                            var delta = (start_event.clientX - event.clientX),
                                                new_width = (width >= delta ? width - delta : 0);

                                            new_width = (new_width > min_width ? new_width : min_width);
                                            self.column.width = new_width;
                                        }
                                    },
                                    saveColumnWidth: function() {
                                        var self = this;

                                        clearTimeout(self.states.width_timer);
                                        self.states.width_timer = setTimeout( function() {
                                            self.$emit("column_change_width", self.column);
                                        }, 100);
                                    }
                                }
                            }
                        },
                        methods: {
                            updateStorage: function() {
                                const self = this;

                                var data = {};
                                $.each(self.columns, function(i, column) {
                                    if (column.width > 0) {
                                        data[column.id] = { width: column.width };
                                    }
                                });
                                data = JSON.stringify(data);

                                localStorage.setItem(self.storage_name, data);
                            }
                        },
                        mounted: function () {
                            var self = this;
                        }
                    }
                },
                computed: {
                },
                methods: {
                    categoryAdd: function() {
                        var self = this;

                        if (!self.states.create_locked) {
                            self.states.create_locked = true;

                            $.post(that.urls["category_dialog"], "json")
                                .always( function() {
                                    self.states.create_locked = false;
                                })
                                .done( function(html) {
                                    that.showCategoryDialog(html)
                                        .done( function(category) {
                                            console.log( "TODO: update category data" );
                                            that.categories.unshift(category);
                                            that.categories_object[category.id] = category;
                                        });
                                });
                        }
                    },
                    sortCategories: function(sort) {
                        let self = this;

                        if (self.states.sort_locked) { return false; }

                        requestConfirm()
                            .done( function(dialog, locker) {
                                request({ sort: sort })
                                    .fail( function(errors) {
                                        console.log("ERROR: server remove error");
                                    })
                                    .done( function() {
                                        $.wa_shop_products.router.reload().done( function() {
                                            dialog.close();
                                        });
                                    });
                            });

                        function requestConfirm() {
                            let deferred = $.Deferred(),
                                confirmed = false;

                            $.waDialog({
                                html: that.templates["categories-sort-confirm"],
                                onOpen: function($dialog, dialog) {
                                    $dialog.on("click", ".js-confirm-button", function(event) {
                                        event.preventDefault();
                                        confirmed = true;
                                        dialog.close();
                                    });
                                },
                                onClose: function(dialog) {
                                    if (typeof confirmed === "boolean") {
                                        if (confirmed) {
                                            locker(true);
                                            deferred.resolve(dialog, locker);
                                            confirmed = null;
                                            return false;
                                        } else {
                                            deferred.reject();
                                        }
                                    }

                                    function locker(lock) {
                                        let $button = dialog.$wrapper.find(".js-confirm-button"),
                                            $icon = $button.find(".s-icon");

                                        if (lock) { $icon.show(); } else { $icon.hide(); }

                                        $button.attr("disabled", lock);
                                    }
                                }
                            });

                            return deferred.promise();
                        }

                        function request(data) {
                            let deferred = $.Deferred();

                            // setTimeout( function() {
                            //     deferred.resolve();
                            // }, 2000);

                            $.post(that.urls["categories_sort"], data, "json")
                                .fail( function() {
                                    deferred.reject();
                                })
                                .done( function(response) {
                                    if (response.status === "ok") {
                                        deferred.resolve(response.data);
                                    } else {
                                        deferred.reject(response.errors);
                                    }
                                });

                            return deferred.promise();
                        }
                    },
                    showCallbackDialog: function(event) {
                        var self = this,
                            $button = $(event.currentTarget);

                        $.waDialog({
                            html: that.templates["callback_dialog"],
                            onOpen: initDialog,
                            options: {
                                onSuccess: function() {
                                    $button.removeClass("animation-pulse");
                                }
                            }
                        });

                        function initDialog($dialog, dialog) {
                            var is_locked = false;

                            var loading = "<span class=\"icon top\" style='margin-right: .5rem;'><i class=\"fas fa-spinner fa-spin\"></i></span>";

                            var $textarea = $dialog.find("textarea:first");

                            $textarea.on("focus", function() {
                                var $textarea = $(this);

                                var placeholder = $textarea.data("placeholder");
                                if (!placeholder) {
                                    placeholder = $textarea.attr("placeholder");
                                    $textarea.data("placeholder", placeholder);
                                }

                                $textarea.attr("placeholder", "");
                            });

                            $textarea.on("blur", function() {
                                var $textarea = $(this);

                                var placeholder = $textarea.data("placeholder");
                                if (placeholder) {
                                    $textarea.attr("placeholder", placeholder);
                                }
                            });

                            $dialog.on("click", ".js-success-button", function() {
                                var $submit_button = $(this);

                                var value = $.trim($textarea.val());
                                if (!value.length) { return false; }

                                if (!is_locked) {
                                    is_locked = true;
                                    var $loading = $(loading).prependTo( $submit_button.attr("disabled", true) );

                                    addCallback()
                                        .always( function() {
                                            is_locked = false;
                                            $submit_button.attr("disabled", false);
                                            $loading.remove();
                                        })
                                        .done( function() {
                                            $dialog.find(".dialog-header, .dialog-footer").remove();
                                            $dialog.find(".dialog-body").html( that.templates["callback_dialog_success"] );
                                            setTimeout( function() {
                                                if ($.contains(document, $dialog[0])) {
                                                    dialog.options.onSuccess();
                                                    dialog.close();
                                                }
                                            }, 3000);
                                        });
                                }
                            });

                            function addCallback() {
                                var href = that.urls["callback_submit"],
                                    data = $dialog.find(":input").serializeArray();

                                return $.post(href, data, "json");
                            }
                        }
                    }
                },
                delimiters: ['{ { ', ' } }'],
                created: function () {
                    $vue_section.css("visibility", "");
                },
                mounted: function() {
                    that.init(this);
                }
            });
        };

        Page.prototype.formatCategory = function(category) {
            var that = this;

            category.type    = (typeof category.type === "string" ? category.type : "" + category.type);
            category.count   = (typeof category.count === "string" ? parseInt(category.count) : category.count);
            category.depth   = (typeof category.depth === "string" ? parseInt(category.depth) : category.depth);
            category.filters = {
                enabled: true,
                filters: []
            };
            category.states  = {
                visible : (category.status === "1"),
                expanded: false,

                // Поля для поиска
                is_wanted       : false,
                is_wanted_inside: false,

                // Поля для перетаскивания
                move       : false,
                drop       : false,
                drop_inside: false,
                drop_error : false,
                move_locked: false
            };

            category.include_sub_categories = (category.include_sub_categories === "1");
            category.enable_sorting = (category.enable_sorting === "1");
            category.allow_filter = (category.allow_filter === "1");

            if (category.categories && category.categories.length) {
                var _categories = [];
                $.each(category.categories, function(i, _category) {
                    _categories.push(that.formatCategory(_category));
                });
                category.categories = _categories;
            } else {
                category.categories = [];
            }

            return category;
        };

        Page.prototype.showCategoryDialog = function(html) {
            var that = this,
                deferred = $.Deferred(),
                ready = $.Deferred(),
                updated_category = null;

            var dialog = $.waDialog({
                html: html,
                options: {
                    ready: ready.promise(),
                    initDialog: initDialog
                },
                onOpen: function($dialog, dialog) {
                    ready.resolve();
                },
                onClose: function() {
                    if (updated_category) {
                        deferred.resolve(updated_category);
                    } else {
                        deferred.reject();
                    }
                }
            });

            return deferred.promise();

            function initDialog($dialog, dialog, options) {
                console.log( dialog );

console.log( $.wa.clone(options.category) );

                var category = formatCategory(options.category),
                    category_sort_variants = options.category_sort_variants,
                    lang = options.lang;

                updateRootVariables(options);

                // VUE
                var $section = $dialog.find(".js-vue-section");

                var category_name = category.name,
                    category_data = that.categories_object[category.id];

                new Vue({
                    el: $section[0],
                    data: {
                        category: category,
                        category_data: category_data,
                        category_types: that.category_types,
                        storefronts: getStorefronts(that.storefronts),
                        states: {
                            locked: false,
                            category_url_focus: false,
                            copy_locked: false,

                            visible_menu_url: true,
                            visible_menu_url_at_subcategories: false,
                            update_storefronts_at_subcategories: !!(category.id && category_data.categories.length),

                            url_sync_started: false,
                            transliterate_xhr: null,
                            transliterate_timer: null
                        },
                        errors: {}
                    },
                    delimiters: ['{ { ', ' } }'],
                    components: {
                        "component-category-visible-storefronts": {
                            props: ["category", "storefronts"],
                            data: function() {
                                var self = this;
                                self.storefronts = $.wa.clone(self.storefronts);
                                return {
                                    states: {
                                        expanded: false
                                    }
                                }
                            },
                            template: that.components["component-category-visible-storefronts"],
                            delimiters: ['{ { ', ' } }'],
                            computed: {
                                selected_storefronts: function() {
                                    const self = this;
                                    return self.storefronts;
                                },
                                selected_storefronts_string: function() {
                                    const self = this;
                                    return $.wa.locale_plural(self.selected_storefronts.length, that.locales["show_visible_storefronts_forms"]);
                                }
                            },
                            methods: {
                                toggle: function(show) {
                                    const self = this;
                                    show = (typeof show === "boolean" ? show : !self.states.expanded);
                                    self.states.expanded = show;

                                    self.$nextTick( function() { dialog.resize(); });
                                }
                            }
                        },
                        "component-category-storefronts-section": {
                            props: ["root_states", "category", "storefronts"],
                            data: function() {
                                var self = this,
                                    type = (category.storefronts.length ? "selection" : "all");

                                return {
                                    type: type
                                }
                            },
                            template: that.components["component-category-storefronts-section"],
                            delimiters: ['{ { ', ' } }'],
                            watch: {
                                "type": function() {
                                    var self = this;
                                    self.$nextTick( function() { dialog.resize(); });
                                }
                            },
                            computed: {
                                radio_name: function() {
                                    var self = this;
                                    return "category["+self.category.id+"]radio_option";
                                },
                                selected_storefronts: function() {
                                    var self = this;
                                    return self.storefronts.filter(storefront => storefront.states.active);
                                },
                                all_selected: function() {
                                    const self = this;
                                    return (self.selected_storefronts.length === self.storefronts.length);
                                }
                            },
                            methods: {
                                selectAll: function(show) {
                                    const self = this;

                                    show = (typeof show === "boolean" ? show : !self.all_selected);

                                    $.each(self.storefronts, function(i, storefront) {
                                        storefront.states.active = show;
                                    });
                                }
                            }
                        },
                        "component-category-description-redactor": {
                            props: ["value"],
                            data: function() {
                                var self = this;
                                return {}
                            },
                            template: that.components["component-category-description-redactor"],
                            delimiters: ['{ { ', ' } }'],
                            computed: {},
                            methods: {},
                            mounted: function() {
                                const self = this,
                                    $wrapper = $(self.$el);

                                initEditor($wrapper);

                                function initEditor($wrapper) {
                                    var $section = $wrapper.find(".js-editor-section"),
                                        $textarea = $section.find(".js-product-description-textarea"),
                                        $html_wrapper = $section.find(".js-html-editor"),
                                        $wysiwyg_wrapper = $section.find(".js-wysiwyg-editor");

                                    var html_editor = null,
                                        wysiwyg_redactor = null,
                                        active_type_id = activeType(),
                                        confirmed = false;

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
                                            $textarea.val(value).trigger("change");
                                            self.value = value;
                                            self.$emit("input", self.value);
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
                                                lang: lang,
                                                focus: false,
                                                deniedTags: false,
                                                minHeight: 130,
                                                maxHeight: 130,
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

                                                    this.$textarea.val(html).trigger("change");
                                                    self.value = html;
                                                    self.$emit("input", self.value);

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
                            }
                        },
                        "component-category-filter-section": {
                            data: function() {
                                var self = this;
                                return {
                                    category: category,
                                    states: {
                                        is_loading: false
                                    }
                                };
                            },
                            template: that.components["component-category-filter-section"],
                            delimiters: ['{ { ', ' } }'],
                            components: {
                                "component-category-filter-item": {
                                    props: ["filter"],
                                    data: function() {
                                        var self = this;
                                        return {};
                                    },
                                    template: that.components["component-category-filter-item"],
                                    delimiters: ['{ { ', ' } }'],
                                    computed: {
                                        item_class: function() {
                                            const self = this;
                                            var result = [];

                                            if (self.filter.states.is_moving) {
                                                result.push("is_moving");
                                            } else if (self.filter.states.is_highlighted) {
                                                result.push("is-highlighted");
                                            }

                                            return result.join(" ");
                                        }
                                    },
                                    methods: {
                                        removeItem: function() {
                                            const self = this;
                                            var index = category.explode_feature_ids.indexOf(self.filter.id);
                                            if (index >= 0) {
                                                category.explode_feature_ids.splice(index, 1);
                                                delete category.allow_filter_data[self.filter.id];
                                            }
                                        }
                                    },
                                    mounted: function() {
                                        const self = this;
                                    }
                                }
                            },
                            computed: {},
                            methods: {
                                initAutocomplete: function($input) {
                                    const self = this;

                                    $input.autocomplete({
                                        source: function (request, response) {
                                            var data = {
                                                term: request.term,
                                                category_id: (self.category.id ? self.category.id : "new"),
                                                options: {
                                                    get_default_filters: 1,
                                                    category_type: self.category.type
                                                    // ignore_id: self.category.explode_feature_ids
                                                }
                                            };

                                            self.states.is_loading = true;

                                            $.post(that.urls["autocomplete_filter"], data, function(response_data) {
                                                if (!response_data.length) {
                                                    response_data = [{
                                                        id: null,
                                                        name: that.locales["no_results"]
                                                    }];
                                                }
                                                response(response_data);
                                            }, "json")
                                                .always( function() {
                                                    self.states.is_loading = false;
                                                });
                                        },
                                        minLength: 1,
                                        delay: 300,
                                        create: function () {
                                            //move autocomplete container
                                            $input.autocomplete("widget").appendTo($input.parent());
                                            $(".ui-helper-hidden-accessible").appendTo($input.parent());
                                        },
                                        select: function(event, ui) {
                                            if (ui.item.id) {
                                                self.addFilter(ui.item);
                                                $input.val("");
                                            }
                                            return false;
                                        },
                                        focus: function() { return false; }
                                    }).data("ui-autocomplete")._renderItem = function( ul, item ) {
                                        if (item.code && item.code.length) {
                                            var template_name = (!item.available_for_sku ? "component-category-filter-autocomplete-item-sku" : "component-category-filter-autocomplete-item"),
                                                html = that.templates[template_name].replace("%name%", item.name).replace("%code%", item.code);
                                            return $(html).appendTo(ul);
                                        } else {
                                            return $("<li />").addClass("ui-menu-item-html").append("<div>"+item.name+"</div>").appendTo(ul);
                                        }
                                    };

                                    $dialog.on("dialog-closed", function() {
                                        $input.autocomplete( "destroy" );
                                    });
                                },
                                addFilter: function(filter) {
                                    const self = this;
                                    var exist_filter = category.allow_filter_data[filter.id];

                                    if (exist_filter) {
                                        highlight(exist_filter);

                                    } else {
                                        filter = formatFilter(filter);
                                        // Добавляем в модель
                                        category.explode_feature_ids.push(filter.id);
                                        self.$set(category.allow_filter_data, filter.id, filter);
                                        // Анимашки
                                        highlight(filter);
                                    }

                                    function highlight(filter) {
                                        filter.states.is_highlighted = true;
                                        setTimeout( function() {
                                            filter.states.is_highlighted = false;
                                        }, 2000);
                                    }
                                },

                                initDragAndDrop: function($wrapper) {
                                    var self = this;

                                    var $document = $(document);

                                    var drag_data = {},
                                        over_locked = false,
                                        is_change = false,
                                        timer = 0;

                                    var filters = self.category.explode_feature_ids,
                                        filters_object = self.category.allow_filter_data;

                                    $wrapper.on("dragstart", ".js-filter-move-toggle", function(event) {
                                        var $move = $(this).closest(".s-filter-wrapper");

                                        var filter_id = "" + $move.attr("data-id"),
                                            filter = getFilter(filter_id);

                                        if (!filter) {
                                            console.error("ERROR: filter isn't exist");
                                            return false;
                                        }

                                        event.originalEvent.dataTransfer.setDragImage($move[0], 20, 20);

                                        drag_data.move_filter = filter;
                                        filter.states.is_moving = true;

                                        $document.on("dragover", ".s-filter-wrapper", onOver);
                                        $document.on("dragend", onEnd);
                                    });

                                    function onOver(event) {
                                        if (drag_data.move_filter) {
                                            event.preventDefault();
                                            if (!over_locked) {
                                                over_locked = true;
                                                moveFilter($(event.currentTarget));
                                                setTimeout( function() {
                                                    over_locked = false;
                                                }, 100);
                                            }
                                        }
                                    }

                                    function onEnd() {
                                        off();
                                    }

                                    //

                                    function moveFilter($over) {
                                        var filter_id = "" + $over.attr("data-id"),
                                            filter = getFilter(filter_id);

                                        if (!filter) {
                                            console.error("ERROR: filter isn't exist");
                                            return false;
                                        }

                                        if (drag_data.move_filter === filter) { return false; }

                                        var move_index = filters.indexOf(drag_data.move_filter.id),
                                            over_index = filters.indexOf(filter_id),
                                            before = (move_index > over_index);

                                        if (over_index !== move_index) {
                                            filters.splice(move_index, 1);

                                            over_index = filters.indexOf(filter.id);
                                            var new_index = over_index + (before ? 0 : 1);

                                            filters.splice(new_index, 0, drag_data.move_filter.id);
                                            is_change = true;
                                        }
                                    }

                                    //

                                    function getFilter(filter_id) {
                                        return (filters_object[filter_id] ? filters_object[filter_id] : null);
                                    }

                                    function off() {
                                        drag_data = {};

                                        $document.off("dragover", ".s-filter-wrapper", onOver);
                                        $document.off("dragend", onEnd);

                                        $.each(filters_object, function(i, filter) {
                                            filter.states.is_moving = false;
                                        });
                                    }
                                }
                            },
                            mounted: function() {
                                const self = this,
                                    $wrapper = $(self.$el);

                                self.$input = $wrapper.find('.js-autocomplete');

                                self.initAutocomplete(self.$input);
                                self.initDragAndDrop($wrapper);
                            }
                        }
                    },
                    computed: {
                        use_transliterate: function() {
                            const self = this;
                            return !(self.category.url.length > 0);
                        },
                        name_is_changed: function() {
                            const self = this;
                            return (self.category.name !== category_name);
                        },
                        url_base: function() {
                            const self = this;
                            var result = "";

                            if (self.category.frontend_urls.length) {
                                result = self.category.frontend_urls[0].base;
                            }

                            return result;
                        },
                        sort_products_variants: function() {
                            const self = this;
                            var result = $.wa.clone(category_sort_variants);

                            $.each(result, function(i, option) {
                                var disabled = false;
                                if (self.category.include_sub_categories && option.value === "") {
                                    disabled = true;
                                }
                                option.disabled = disabled;
                            });

                            return result;
                        },
                        has_errors: function() {
                            const self = this;

                            var result = !!Object.keys(self.errors).length;

                            if (!self.category.name.length) { result = true; }

                            return result;
                        }
                    },
                    watch: {
                        "category.name": function(value) {
                            const self = this;

                            if (!value.length) {
                                self.$set(self.errors, "category_name", {
                                    id: "category_name",
                                    text: that.locales["error_category_name_empty"]
                                });
                            } else if (value.length > 255) {
                                self.$set(self.errors, "category_name", {
                                    id: "category_name",
                                    text: that.locales["error_category_name"]
                                });
                            } else {
                                self.$delete(self.errors, "category_name");
                            }
                        }
                    },
                    methods: {
                        onChangeName: function() {
                            var self = this;
                            if (self.use_transliterate) {
                                self.getUrlByName(1000)
                                    .done( function(url) {
                                        if (self.use_transliterate) {
                                            self.category.url = url;
                                        }
                                    });
                            }
                        },
                        onChangeIncludeSubCategories: function(value) {
                            const self = this;

                            if (value && self.category.sort_products === "") {
                                self.category.sort_products = "name ASC";
                            }

                            self.resizeDialog();
                        },
                        getUrlByName: function(time) {
                            const self = this;
                            var deferred = $.Deferred();

                            var time = (typeof time === "number" ? time : 0);

                            clearTimeout(self.states.transliterate_timer);

                            if (!$.trim(self.category.name).length) {
                                deferred.reject();
                            } else {
                                if (self.states.transliterate_xhr) {
                                    self.states.transliterate_xhr.abort();
                                }

                                self.states.transliterate_timer = setTimeout( function() {
                                    self.states.transliterate_xhr = $.get(that.urls["transliterate"], { str: self.category.name }, "json")
                                        .always( function() { self.states.transliterate_xhr = null; })
                                        .fail( function() { deferred.reject(); })
                                        .done( function(response) { deferred.resolve(response.data); });
                                }, time);
                            }

                            return deferred.promise();
                        },
                        onCopyUrl: function() {
                            const self = this;
                            var url = self.category.frontend_base_url + self.category.url;
                            var dummy = document.createElement('input');
                            document.body.appendChild(dummy);
                            dummy.value = url;
                            dummy.select();
                            document.execCommand('copy');
                            document.body.removeChild(dummy);

                            self.states.copy_locked = true;
                            setTimeout( function() {
                                self.states.copy_locked = false;
                            }, 1000)
                        },
                        onSyncUrl: function() {
                            const self = this;
                            if (self.name_is_changed) {
                                self.states.url_sync_started = true;
                                self.getUrlByName()
                                    .always( function() {
                                        self.states.url_sync_started = false;
                                    })
                                    .done( function(url) { self.category.url = url; });
                            }
                        },

                        save: function() {
                            const self = this;

                            self.states.locked = true;

                            var data = getData(self.category);

console.log( data );

                            $.post(that.urls["category_dialog_save"], data, "json")
                                .fail( function() {
                                    self.states.locked = false;
                                })
                                .done( function(response) {
                                    if (response.status === "ok") {
                                        $.wa_shop_products.router.reload().done( function() {
                                            dialog.close();
                                        });

                                    } else {
                                        console.error( response.errors );
                                        self.states.locked = false;
                                    }
                                });

                            function getData(category) {
                                var data = {
                                    category: {
                                        id         : category.id,
                                        url        : category.url,
                                        name       : category.name,
                                        parent_id  : category.parent_id,
                                        description: category.description,

                                        // url at menu
                                        hidden              : (self.states.visible_menu_url ? 1 : 0),
                                        update_subcategories: (self.states.visible_menu_url_at_subcategories ? 1 : 0),

                                        // представление на витрине
                                        include_sub_categories: (self.category.include_sub_categories ? 1 : 0),
                                        sort_products: self.category.sort_products,
                                        enable_sorting: (self.category.enable_sorting ? 1 : 0),
                                        allow_filter: (self.category.allow_filter ? 1 : 0),
                                        filter: self.category.explode_feature_ids,

                                        // storefronts
                                        propagate_visibility: (self.states.update_storefronts_at_subcategories ? 1 : 0),
                                        routes              : getRoutes(),

                                        // meta
                                        meta_title      : category.meta_title,
                                        meta_description: category.meta_description,
                                        meta_keywords   : category.meta_keywords,

                                        // og
                                        og: getOG(),

                                        // params
                                        params: category.params
                                    }
                                };

                                return data;

                                function getRoutes() {
                                    var filtered_storefronts = self.storefronts.filter( storefront => storefront.states.enabled );
                                    return filtered_storefronts.map( storefront => storefront.url);
                                }

                                function getOG() {
                                    var result = $.wa.clone(self.category.og);
                                    result.enabled = (self.category.og.enabled ? 1 : 0);
                                    return result;
                                }
                            }
                        },
                        resizeDialog: function() {
                            const self = this;
                            self.$nextTick( function() {
                                setTimeout( function() {
                                    dialog.resize();
                                }, 100);
                            });
                        },

                        getLettersHTML: function(letters, min, max) {
                            letters = letters || "";

                            var self = this;

                            var count = letters.length,
                                locale = $.wa.locale_plural(count, that.locales["letter_forms"], false),
                                is_good = (count >= min && count <= max),
                                result = locale.replace("%d", '<span class="s-number ' + (is_good ? "color-green" : "color-orange") + '">' + count + '</span>');

                            return result;
                        },
                    },
                    created: function () {
                        $section.css("visibility", "");
                    },
                    mounted: function () {
                        var self = this,
                            $wrapper = $(self.$el);

                        self.resizeDialog();

                        if (!self.category.name.length) {
                            $wrapper.find(".js-autofocus").trigger("focus");
                        }
                    }
                });

                function updateRootVariables(options) {
                    // Tooltips
                    $.each(options.tooltips, function(i, tooltip) { $.wa.new.Tooltip(tooltip); });
                    // Components
                    that.components = $.extend(that.components, options.components);
                    // Templates
                    that.templates = $.extend(that.templates, options.templates);
                    // Locales
                    that.locales = $.extend(that.locales, options.locales);
                }

                function formatCategory(category) {
                    category = that.formatCategory(options.category);

                    if (Array.isArray(category.allow_filter_data)) {
                        category.allow_filter_data = {};
                    } else {
                        $.each(category.allow_filter_data, function(i, filter) {
                            category.allow_filter_data[i] = formatFilter(filter);
                        });
                    }

                    return category;
                }

                function formatFilter(filter) {
                    filter.states = {
                        is_moving: false,
                        is_highlighted: false
                    };
                    return filter;
                }

                function getStorefronts(storefronts) {
                    if (!storefronts.length) { return []; }

                    storefronts = $.wa.clone(storefronts);

                    $.each(storefronts, function(i, storefront) {
                        storefront.states = {
                            active: (!category.storefronts.length || category.storefronts.indexOf(storefront.url) >= 0)
                        };
                    });

                    return storefronts;
                }
            }
        };

        Page.prototype.initDragAndDrop = function() {
            var that = this;

            var $document = $(document);

            var drag_data = {},
                over_locked = false,
                is_changed = false,
                timer = 0;

            var $wrapper = that.$wrapper,
                $droparea = $("<div />", { class: "s-drop-area js-drop-area" });

            var categories = that.categories_object;

            // Drop по зоне триггерит дроп по привязанной категории
            $droparea
                .on("dragover", function(event) {
                    event.preventDefault();
                })
                .on("drop", function() {
                    var $drop_category = $droparea.data("drop_category");
                    if ($drop_category) {
                        $drop_category.trigger("drop");
                    } else {
                        console.log( $droparea );
                        console.log("Drop ERROR #1");
                    }
                });

            $wrapper.on("dragstart", ".js-category-move-toggle", function(event) {
                var $move = $(this).closest(".s-category-wrapper");

                var category_id = "" + $move.attr("data-id"),
                    category = getCategory(category_id);

                if (!category) {
                    console.error("ERROR: category isn't exist");
                    return false;
                }

                event.originalEvent.dataTransfer.setDragImage($move[0], 20, 20);

                drag_data.move_category = category;

                $document.on("drop", ".s-category-wrapper", onDrop);
                $document.on("dragover", ".s-category-wrapper", onDragOver);
                $document.on("dragend", onDragEnd);
            });

            function onDrop(event) {
                var $category = $(this);
                moveCategory($category);
                off();
            }

            function onDragOver(event) {
                event.preventDefault();

                if (!drag_data.move_category) { return false; }

                if (!drag_data.move_category.states.move) {
                    drag_data.move_category.states.move = true;
                }

                if (!over_locked) {
                    over_locked = true;

                    var $category = $(this),
                        category_id = "" + $category.attr("data-id"),
                        drop_category = getCategory(category_id);

                    if (drag_data.drop_category && drag_data.drop_category !== drop_category) {
                        drag_data.drop_category.states.drop = false;
                        drag_data.drop_category.states.drop_inside = false;
                        drag_data.drop_category.states.drop_error = false;
                    }
                    drag_data.drop_category = drop_category;

                    var mouse_y = event.originalEvent.pageY,
                        cat_offset = $category.offset(),
                        cat_height = $category.outerHeight(),
                        cat_y = cat_offset.top,
                        x = (mouse_y - cat_y),
                        before = ( x <= (cat_height * 0.3) );

                    drop_category.states.drop = true;
                    drop_category.states.drop_inside = !before;
                    drop_category.states.drop_error = (!before && drag_data.move_category.type === "0" && drop_category.type === "1");

                    if (before !== drag_data.before) {
                        if (before) {
                            $category.before($droparea);
                        } else {
                            var is_exist = $.contains(document, $droparea[0]);
                            if (is_exist) { $droparea.detach(); }
                        }
                        $droparea.data("drop_category", $category);
                        $droparea.data("before", before);
                        drag_data.before = before;
                    }
                    setTimeout( function() {
                        over_locked = false;
                    }, 100);
                }
            }

            function onDragEnd() {
                off();
            }

            //

            function moveCategory($drop_category) {
                var category_id = "" + $drop_category.attr("data-id"),
                    drop_category = getCategory(category_id),
                    move_category = drag_data.move_category,
                    before = drag_data.before;

                if (!drop_category) {
                    console.error("ERROR: category isn't exist");
                    return false;
                }

                if (move_category === drop_category) {
                    return false;

                // В динамическую категорию нельзя
                } else if (move_category.type === "0" && drop_category.type === "1" && !drag_data.before) {
                    return false;

                } else {
                    set(move_category, drop_category, drag_data.before);

                    move_category.states.move_locked = true;
                    moveRequest(move_category)
                        .always( function() {
                            move_category.states.move_locked = false;
                        });
                }

                function set(move_category, drop_category, before) {
                    remove(move_category);

                    // Вставляем выше drop_category
                    if (before) {
                        var categories = that.categories,
                            parent_id = "0";

                        if (drop_category.parent_id !== "0") {
                            var parent_category = that.categories_object[drop_category.parent_id];
                            categories = parent_category.categories;
                            parent_id = parent_category.id;
                        }

                        var index = categories.indexOf(drop_category);

                        categories.splice(index, 0, move_category);

                        move_category.parent_id = parent_id;
                        move_category.depth = drop_category.depth;

                    // Вставляем внутрь drop_category
                    } else {
                        drop_category.categories.push(move_category);
                        move_category.parent_id = drop_category.id;
                        move_category.depth = drop_category.depth + 1;
                    }

                    function remove(move_category) {
                        var categories = that.categories;

                        if (move_category.parent_id !== "0") {
                            var parent_category = that.categories_object[move_category.parent_id];
                            categories = parent_category.categories;
                        }

                        var index = categories.indexOf(move_category);
                        if (index >= 0) {
                            categories.splice(index, 1);
                        } else {
                            console.log( "ERROR: remove category from array" );
                        }
                    }
                }
            }

            function moveRequest(category) {
                var deferred = $.Deferred();

                var data = {
                    moved_category_id: category.id,
                    parent_category_id: category.parent_id,
                    categories: getCategories()
                }

                that.states.move_locked = true;

                $.post(that.urls["category_move"], data, "json")
                    .always( function() {
                        that.states.move_locked = false;
                    })
                    .fail( function() {
                        deferred.reject();
                    })
                    .done( function(response) {
                        if (response.status === "ok") {
                            deferred.resolve();
                        } else {
                            alert("Error: category move");
                            console.log(response.errors);
                            deferred.reject(response.errors);
                        }
                    });

                return deferred.promise();

                function getCategories() {
                    var result = [];

                    if (category.parent_id !== "0") {
                        var parent_category = that.categories_object[category.parent_id];
                        result = parent_category.categories.map( function(_category) {
                            return _category.id;
                        });
                    } else {
                        result = that.categories.map( function(_category) {
                            return _category.id;
                        });
                    }

                    return result;
                }
            }

            //

            function getCategory(category_id) {
                return (categories[category_id] ? categories[category_id] : null);
            }

            function off() {
                var is_exist = $.contains(document, $droparea[0]);
                if (is_exist) { $droparea.detach(); }

                drag_data = {};
                $document.off("drop", ".s-category-wrapper", onDrop);
                $document.off("dragover", ".s-category-wrapper", onDragOver);
                $document.off("dragend", onDragEnd);

                $.each(categories, function(i, category) {
                    category.states.move = false;
                    category.states.drop = false;
                    category.states.drop_inside = false;
                    category.states.drop_error = false;
                });
            }
        };

        Page.prototype.storage = function(update) {
            let that = this,
                storage_name = "shop/products/categories";

            update = (typeof update === "boolean" ? update : false);
            return (update ? setter() : getter());

            function setter() {
                var value = getValue();
                value = JSON.stringify(value);

                localStorage.setItem(storage_name, value);

                function getValue() {
                    let result = { expanded_categories: [] };

                    $.each(that.categories_object, function(i, category) {
                        if (category.states.expanded) {
                            result.expanded_categories.push(category.id);
                        }
                    });

                    return result;
                }
            }

            function getter() {
                let storage = localStorage.getItem(storage_name);
                if (storage) { storage = JSON.parse(storage); }
                return storage;
            }
        };

        return Page;

    })($);

    $.wa_shop_products.init.initProductsCategoriesPage = function(options) {
        return new Page(options);
    };

})(jQuery);