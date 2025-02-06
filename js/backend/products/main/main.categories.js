( function($) {

    var Page = ( function($) {

        function Page(options) {
            var that = this;
            const { reactive } = Vue;

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
            that.header_columns         = reactive(options["header_columns"]);

            // VUE VARS
            that.categories = reactive(formatCategories(options["categories"]));
            that.categories_object = getCategoriesObject(that.categories);
            that.storefronts = reactive(options["storefronts"]);
            that.states = reactive({
                move_locked: false,
                create_locked: false,
                count_locked: true
            });

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

            that.updateCategoriesCount().done( function() {
                that.states.count_locked = false;
            });

            $.each(that.tooltips, function(i, tooltip) {
                $.wa.new.Tooltip(tooltip);
            });
        };

        Page.prototype.initVue = function() {
            var that = this;

            if (typeof $.vue_app === "object" && typeof $.vue_app.unmount === "function") {
                $.vue_app.unmount();
            }

            var $vue_section = that.$wrapper.find("#js-vue-section");

            that.vue_components = {
                "component-date-picker": {
                    props: ["modelValue", "readonly", "field_class"],
                    emits: ["update:modelValue", "change"],
                    template: that.components["component-date-picker"],
                    delimiters: ['{ { ', ' } }'],
                    computed: {
                        prop_readonly() { return (typeof this.readonly === "boolean" ? this.readonly : false); }
                    },
                    mounted: function() {
                        var self = this;

                        $(self.$el).find(".js-date-picker").each( function(i, field) {
                            var $field = $(field),
                                $alt_field = $field.parent().find("input[type='hidden']");

                            $field.datepicker({
                                altField: $alt_field,
                                altFormat: "yy-mm-dd",
                                changeMonth: true,
                                changeYear: true,
                                beforeShow:function(field, instance){
                                    var $calendar = $(instance.dpDiv[0]);
                                    var index = 2001;
                                    setTimeout( function() {
                                        $calendar.css("z-index", index);
                                    }, 10);
                                },
                                onClose: function(field, instance) {
                                    var $calendar = $(instance.dpDiv[0]);
                                    $calendar.css("z-index", "");
                                }
                            });

                            if (self.modelValue) {
                                var date = formatDate(self.modelValue);
                                $field.datepicker( "setDate", date);
                            }

                            $field.on("change", function() {
                                var field_value = $field.val();
                                if (!field_value) { $alt_field.val(""); }
                                var value = $alt_field.val();
                                self.$emit("update:modelValue", value);
                                self.$emit("change");
                            });

                            function formatDate(date_string) {
                                if (typeof date_string !== "string") { return null; }

                                var date_array = date_string.split("-"),
                                    year = date_array[0],
                                    mount = date_array[1] - 1,
                                    day = date_array[2];

                                return new Date(year, mount, day);
                            }
                        });
                    }
                },

                "component-input": {
                    props: ["modelValue", "placeholder", "readonly", "required", "cancel", "focus", "validate", "fraction_size", "format"],
                    emits: ["update:modelValue", "change", "focus", "blur", "cancel", "ready"],
                    template: `<input type="text" v-bind:class="field_class"
                                    v-bind:placeholder="placeholder"
                                    v-bind:value="modelValue"
                                    v-bind:readonly="prop_readonly"
                                    v-on:focus="onFocus"
                                    v-on:input="onInput"
                                    v-on:keydown.esc="onEsc"
                                    v-on:keydown.enter="onEnter"
                                    v-on:blur="onBlur">`,
                    data: function() {
                        return {
                            $input: null,
                            start_value: null
                        };
                    },
                    computed: {
                        prop_readonly() { return (typeof this.readonly === "boolean" ? this.readonly : false); },
                        prop_required() { return (typeof this.required === "boolean" ? this.required : false); },
                        prop_cancel() { return (typeof this.cancel === "boolean" ? this.cancel : false); },
                        prop_focus() { return (typeof this.focus === "boolean" ? this.focus : false); },
                        prop_format() { return (typeof this.format === "string" ? this.format : "text"); },
                        field_class: function() {
                            var self = this,
                                result = [];

                            if (self.prop_required && !self.modelValue.length) {
                                result.push("state-error");
                            }
                            if (self.prop_format === "number" || self.prop_format === "number-negative") {
                                result.push("is-number");
                            }

                            return result.join(" ");
                        }
                    },
                    methods: {
                        onInput: function($event) {
                            var self = this;

                            if (self.validate) {
                                var options = {};
                                if (self.fraction_size > 0) { options.fraction_size = self.fraction_size; }

                                $event.target.value = $.wa.validate(self.validate, $event.target.value, options);
                            }

                            self.$emit('update:modelValue', $event.target.value);
                        },
                        onFocus: function($event) {
                            var self = this;
                            self.start_value = $event.target.value;
                            self.$emit("focus");
                        },
                        onBlur: function($event) {
                            var self = this,
                                value = $event.target.value;

                            if (value === self.start_value) {
                                if (self.prop_cancel) { self.$emit("cancel"); }
                            } else {
                                self.onChange(value);
                            }

                            self.$emit("blur", value);
                        },
                        onEsc: function($event) {
                            var self = this;
                            if (self.prop_cancel) {
                                $event.target.value = self.start_value;
                                self.$emit('update:modelValue', $event.target.value);
                                self.$input.trigger("blur");
                            }
                        },
                        onEnter: function($event) {
                            var self = this;
                            self.$input.trigger("blur");
                        },
                        onChange: function(value) {
                            var self = this;
                            if (!self.prop_required || self.modelValue.length) {
                                self.$emit("change", value);
                            }
                        }
                    },
                    mounted: function() {
                        var self = this,
                            $input = self.$input = $(self.$el);

                        self.$emit("ready", self.$el.value);

                        if (self.prop_focus) {
                            $input.trigger("focus");
                        }
                    }
                },

                "component-checkbox": {
                    props: ["modelValue", "label", "disabled", "field_id"],
                    emits: ["update:modelValue", "change", "click-input"],
                    data: function() {
                        var self = this;
                        return {
                            tag: (self.label !== false ? "label" : "span"),
                            id: (typeof self.field_id === "string" ? self.field_id : "")
                        }
                    },
                    computed: {
                        prop_disabled() { return (typeof this.disabled === "boolean" ? this.disabled : false); }
                    },
                    template: that.components["component-checkbox"],
                    delimiters: ['{ { ', ' } }'],
                    methods: {
                        onChange: function(event) {
                            var self = this;
                            var val = event.target.checked;
                            self.$emit("update:modelValue", val);
                            self.$emit("change", val);
                        },
                        onClickInput: function(event) {
                            this.$emit("click-input", event);
                        }
                    }
                },

                "component-radio": {
                    props: ["modelValue", "name", "val", "label", "disabled"],
                    emits: ["update:modelValue", "change"],
                    data: function() {
                        let self = this;
                        return {
                            tag: (self.label !== false ? "label" : "span"),
                            value: (typeof self.val === "string" ? self.val : "")
                        }
                    },
                    computed: {
                        prop_name() { return (typeof this.name === "string" ? this.name : ""); },
                        prop_disabled() { return (typeof this.disabled === "boolean" ? this.disabled : false); },
                        checked() { return this.modelValue === this.value }
                    },
                    template: that.components["component-radio"],
                    delimiters: ['{ { ', ' } }'],
                    methods: {
                        onChange: function(event) {
                            this.$emit("update:modelValue", this.value);
                            this.$emit("change", this.value);
                        }
                    }
                },

                "component-dropdown": {
                    props: ["modelValue", "options", "disabled", "button_class", "body_width", "body_class", "empty_option"],
                    emits: ["update:modelValue", "change", "focus", "blur"],
                    data: function() {
                        return {
                            states: { is_changed: false }
                        }
                    },
                    template: that.components["component-dropdown"],
                    delimiters: ['{ { ', ' } }'],
                    computed: {
                        prop_disabled() { return (typeof this.disabled === "boolean" ? this.disabled : false); },
                        prop_body_width() { return (typeof this.body_width === "string" ? this.body_width : ""); },
                        prop_body_class() { return (typeof this.body_class === "string" ? this.body_class : ""); },
                        prop_button_class() { return (typeof this.button_class === "string" ? this.button_class : ""); },
                        prop_empty_option() { return (typeof this.empty_option !== "undefined" ? this.empty_option : false); },
                        active_option: function() {
                            let self = this,
                                active_option = null;

                            if (self.modelValue) {
                                var filter_search = self.options.filter( function(option) {
                                    return (option.value === self.modelValue);
                                });
                                active_option = (filter_search.length ? filter_search[0] : active_option);
                            } else if (!self.prop_empty_option) {
                                active_option = self.options[0];
                            }

                            return active_option;
                        },
                        formatted_options: function() {
                            var self = this,
                                result = [];

                            if (self.prop_empty_option) {
                                result.push({
                                    name: (typeof self.prop_empty_option === "string" ? self.prop_empty_option : ""),
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

                            if (option.prop_disabled) { return false; }

                            if (self.active_option !== option) {
                                self.$emit("update:modelValue", option.value);
                                self.$emit("change", option.value);
                            }
                            self.dropdown.hide();
                        }
                    },
                    mounted: function() {
                        let self = this;

                        if (self.prop_disabled) { return false; }

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
                },

                "component-switch": {
                    props: ["modelValue", "disabled"],
                    emits: ["update:modelValue", "change"],
                    template: '<div class="switch small"></div>',
                    delimiters: ['{ { ', ' } }'],
                    mounted: function() {
                        var self = this;

                        $.waSwitch({
                            $wrapper: $(self.$el),
                            active: !!self.modelValue,
                            disabled: !!self.disabled,
                            change: function(active, wa_switch) {
                                self.$emit("update:modelValue", active);
                                self.$emit("change", active);
                            }
                        });
                    }
                },

                "component-textarea": {
                    props: ["modelValue", "placeholder", "readonly", "cancel", "focus", "rows"],
                    emits: ["update:modelValue", "change", "focus", "blur", "cancel", "ready"],
                    data: function() {
                        return {
                            offset: 0,
                            $textarea: null,
                            start_value: null
                        };
                    },
                    computed: {
                        prop_readonly() { return (typeof this.readonly === "boolean" ? this.readonly : false) },
                        prop_focus() { return (typeof this.focus === "boolean" ? this.focus : false) },
                        prop_cancel() { return (typeof this.cancel === "boolean" ? this.cancel : false) },
                    },
                    template: `<textarea v-bind:placeholder="placeholder"
                                    v-bind:value="modelValue"
                                    v-bind:readonly="prop_readonly"
                                    v-on:focus="onFocus"
                                    v-on:input="onInput"
                                    v-on:keydown.esc="onEsc"
                                    v-on:blur="onBlur"
                                ></textarea>`,
                    delimiters: ['{ { ', ' } }'],
                    methods: {
                        onInput: function($event) {
                            var self = this;
                            self.update();
                            self.$emit('update:modelValue', $event.target.value);
                        },
                        onFocus: function($event) {
                            var self = this;
                            self.start_value = $event.target.value;
                            self.$emit("focus");
                        },
                        onBlur: function($event) {
                            var self = this,
                                value = $event.target.value;

                            if (value === self.start_value) {
                                if (self.prop_cancel) { self.$emit("cancel"); }
                            } else {
                                self.$emit("change", value);
                            }
                            self.$emit("blur", value);
                        },
                        onEsc: function($event) {
                            var self = this;
                            if (self.prop_cancel) {
                                $event.target.value = self.start_value;
                                self.$emit('update:modelValue', $event.target.value);
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
                        if (self.rows === '1') {
                            $textarea.css("white-space", "nowrap");
                        }
                        self.$emit("ready", self.$el.value);

                        if (self.prop_focus) { $textarea.trigger("focus"); }
                    }
                },
            };

            $.vue_app = Vue.createApp({
                data() {
                    return {
                        categories: that.categories,
                        states    : that.states
                    }
                },
                components: {
                    "component-dropdown-categories-sorting": {
                        template: that.components["component-dropdown-categories-sorting"],
                        emits: ["change"],
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
                                return !(this.position > 1);
                            },
                            is_end: function() {
                                return (this.position === this.selection.length);
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
                                            create_locked: false,
                                            view_locked: false,
                                            delete_locked: false,
                                            name_on_focus: self.category.name
                                        }
                                    }
                                },
                                template: that.components["component-category"],
                                delimiters: ['{ { ', ' } }'],
                                components: {
                                    "component-category-sorting": {
                                        props: ["category"],
                                        data: function() {
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

                                            self.dropdown = $dropdown.waDropdown({
                                                hover: false,
                                                protect: { box_limiter: '.s-categories-table-section', bottom: 5 }
                                            }).waDropdown("dropdown");
                                        }
                                    },
                                    "component-category-filters": {
                                        props: ["category"],
                                        components: { "component-switch": that.vue_components["component-switch"] },
                                        data: function() {
                                            var self = this;
                                            return {
                                                keys: { switch: 0 },
                                                states: { locked: false }
                                            }
                                        },
                                        template: that.components["component-category-filters"],
                                        delimiters: ['{ { ', ' } }'],
                                        computed: {},
                                        methods: {
                                            onChange: function(value) {
                                                const self = this;

                                                self.states.locked = true;
                                                $.post(that.urls["category_filter_change"], { category_id: self.category.id, switch: (value ? 1 : 0) }, "json")
                                                    .always( function() {
                                                        self.states.locked = false;
                                                    }).done( function() {
                                                        if (value) { self.setup(); }
                                                    });
                                            },
                                            setup: function() {
                                                var self = this;

                                                if (!self.states.locked) {
                                                    self.states.locked = true;
                                                    $.post(that.urls["category_dialog"], { category_id: self.category.id }, "json")
                                                        .always( function() {
                                                            self.states.locked = false;
                                                        })
                                                        .done( function(html) {
                                                            var dialog = that.editCategoryDialog(html);

                                                            dialog.options.vue_ready.done( function(dialog, vue_model) {
                                                                var $dialog = dialog.$wrapper,
                                                                    $content = $dialog.find(".dialog-content"),
                                                                    $section = $dialog.find(".js-category-filter-section");

                                                                var active_class = "is-highlighted";

                                                                var content_offset = $content.offset(),
                                                                    section_offset = $section.offset(),
                                                                    scroll_top = (content_offset.top - section_offset.top + 50);

                                                                scroll_top = Math.abs(scroll_top);

                                                                if (scroll_top > 0) {
                                                                    $content.scrollTop(scroll_top);
                                                                    $section.addClass(active_class);
                                                                    setTimeout( function() {
                                                                        $section.removeClass(active_class);
                                                                    }, 2000);
                                                                }
                                                            });
                                                        });
                                                }
                                            }
                                        }
                                    },
                                    "component-dropdown-storefronts": {
                                        props: ["modelValue", "category"],
                                        emits: ["update:modelValue"],
                                        components: {
                                            "component-radio": that.vue_components["component-radio"],
                                            "component-checkbox": that.vue_components["component-checkbox"]
                                        },
                                        data: function() {
                                            var self = this;

                                            return {
                                                type: (!self.modelValue.length ? "all" : "selection"),
                                                storefronts: getStorefronts(self.modelValue),
                                                states: {
                                                    locked: false
                                                }
                                            };

                                            function getStorefronts(active_storefronts) {
                                                var storefronts = $.wa.clone(that.storefronts);
                                                $.each(storefronts, function(i, storefront) {
                                                    storefront.active = (active_storefronts.indexOf(storefront.url) >= 0);
                                                });
                                                return storefronts;
                                            }
                                        },
                                        template: that.components["component-dropdown-storefronts"],
                                        delimiters: ['{ { ', ' } }'],
                                        watch: {
                                            "type": function() {
                                                this.dropdown.resize();
                                            }
                                        },
                                        computed: {
                                            radio_name: function() {
                                                return "category["+this.category.id+"]radio_option";
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
                                                            self.$emit("update:modelValue", result);
                                                        } else {
                                                            console.log( "ERRORS: cant save storefronts", response.errors );
                                                        }
                                                    });
                                            }
                                        },
                                        mounted: function() {
                                            const $dropdown = $(this.$el).find(".dropdown");
                                            this.dropdown = $dropdown.waDropdown({
                                                hover: false,
                                                protect: { box_limiter: '.s-categories-table-section', bottom: 5 }
                                            }).waDropdown("dropdown");
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
                                    },
                                    category_depth: function() {
                                        const self = this;

                                        var depth = checkDepth(self.category);

                                        return (depth-1);

                                        function checkDepth(category) {
                                            var i = 1;

                                            if (category.parent_id !== "0") {
                                                var parent_category = that.categories_object[category.parent_id];
                                                if (!parent_category) {
                                                    console.error("Category not found.");
                                                } else {
                                                    i += checkDepth(parent_category);
                                                }
                                            }

                                            return i;
                                        }
                                    },
                                    category_view_url: function() {
                                        const self = this;
                                        var result = null;

                                        if (self.category.storefronts.length === 1 || that.storefronts.length === 1) {
                                            result = (that.urls["category_view"] + "&category_id=" + self.category.id);
                                        }

                                        return result;
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

                                    onFocusName: function() {
                                        const self = this;
                                        self.states.name_on_focus = self.category.name;
                                    },
                                    onChangeName: function() {
                                        var self = this;

                                        if ($.trim(self.category.name) === "") {
                                            self.category.name = self.states.name_on_focus;
                                            return false;
                                        }

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
                                                    that.editCategoryDialog(html);
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
                                                    that.editCategoryDialog(html);
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

                                            Vue.createApp({
                                                data() {
                                                    return {
                                                        category: category,
                                                        copy_categories: true,
                                                        copy_products: false,
                                                        locked: false
                                                    }
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
                                            }).mount($vue_section[0]);
                                        }
                                    },
                                    categoryView: function() {
                                        var self = this,
                                            category = self.category;

                                        var storefonts = getStorefronts(that.storefronts, category.storefronts);

                                        if (!storefonts.length) {
                                            console.error("Error: can't find storefronts");
                                            return false;
                                        } else {
                                            self.states.view_locked = true;
                                            $.post(that.urls["category_get_urls"], { category_id: self.category.id }, "json")
                                                .always( function() {
                                                    self.states.view_locked = false;
                                                })
                                                .done( function(response) {
                                                    $.waDialog({
                                                        html: that.templates["dialog-category-view"],
                                                        options: {
                                                            urls: response.data.urls
                                                        },
                                                        onOpen: initDialog
                                                    });
                                                });
                                        }

                                        function initDialog($dialog, dialog) {
                                            console.log( dialog, category );

                                            var $vue_section = $dialog.find(".js-vue-section");

                                            Vue.createApp({
                                                data() {
                                                    return {
                                                        category: category,
                                                        urls: dialog.options.urls
                                                    }
                                                },
                                                delimiters: ['{ { ', ' } }'],
                                                methods: {
                                                    onClick: function() {
                                                        dialog.close();
                                                    }
                                                },
                                                created: function () {
                                                    $vue_section.css("visibility", "");
                                                },
                                                mounted: function() {
                                                    dialog.resize();
                                                }
                                            }).mount($vue_section[0]);
                                        }

                                        function getStorefronts(all_fronts, cat_fronts) {
                                            var result = all_fronts;

                                            if (cat_fronts.length) {
                                                result = cat_fronts;
                                            }

                                            return result;
                                        }
                                    },
                                    categoryDelete: function() {
                                        var self = this;

                                        if (!self.states.delete_locked) {
                                            self.states.delete_locked = true;

                                            $.post(that.urls["category_delete_dialog"], { category_id: self.category.id }, "json")
                                                .always( function() {
                                                    self.states.delete_locked = false;
                                                })
                                                .done( function(html) {
                                                    that.deleteCategoryDialog(html, {
                                                        category: self.category
                                                    });
                                                });
                                        }
                                    }
                                }
                            },
                            "component-categories-header-column": {
                                props: ["column"],
                                data: function() {
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
                        }
                    },
                    "component-empty-content": {
                        props: ["type"],
                        template: that.components["component-empty-content"],
                    },
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
                                    that.editCategoryDialog(html);
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
                    /**
                     *  @deprecated
                      * @param event
                     */
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
                                // TODO change url
                                var href = '', //that.urls["callback_submit"],
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

            $.vue_app.config.compilerOptions.whitespace = 'preserve';

            return $.vue_app.mount($vue_section[0]);
        };

        Page.prototype.formatCategory = function(category) {
            var that = this;

            category.type    = (typeof category.type === "string" ? category.type : "" + category.type);
            category.count   = (typeof category.count === "string" ? parseInt(category.count) : category.count);
            category.depth   = (typeof category.depth === "string" ? parseInt(category.depth) : category.depth);
            category.states  = {
                visible : (category.status === "1"),
                expanded: false,
                count_locked: true,

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

        Page.prototype.initDragAndDrop = function() {
            var that = this;

            var $document = $(document);

            var drag_data = {},
                over_locked = false,
                is_changed = false,
                timer = 0;

            var $wrapper = that.$wrapper,
                $droparea = $("<div />", { class: "s-drop-area js-drop-area" });

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
                        is_dummy = $category.hasClass("is-dummy");

                    if (is_dummy) {
                        var before = null;
                        if (before !== drag_data.before) {
                            $category.before($droparea);
                            $droparea.data("drop_category", $category);
                            $droparea.data("before", before);
                            drag_data.before = before;
                        }

                    } else {
                        var category_id = "" + $category.attr("data-id"),
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
                    }

                    setTimeout( function() {
                        over_locked = false;
                    }, 100);
                }
            }

            function onDragEnd() {
                off();
            }

            function moveCategory($drop_category) {
                var category_id = "" + $drop_category.attr("data-id"),
                    drop_category = getCategory(category_id),
                    move_category = drag_data.move_category,
                    before = drag_data.before;

                if (before === null) {
                    drop_category = that.categories[that.categories.length-1];
                }

                if (!drop_category) {
                    console.error("ERROR: category isn't exist");
                    return false;
                }

                if (move_category === drop_category) {
                    return false;

                // В динамическую категорию нельзя
                } else if (move_category.type === "0" && drop_category.type === "1" && drag_data.before === false) {
                    return false;

                } else {
                    const { parent_id: prev_parent_id, depth: prev_depth } = move_category;
                    const { categories, index: prevIndex } = getNeedCategoriesAndIndex(move_category);

                    set(move_category, drop_category, drag_data.before);
                    move_category.states.move_locked = true;

                    moveRequest(move_category)
                        .always(function () {
                            move_category.states.move_locked = false;
                        })
                        .fail(function () {
                            remove(that.categories_object[move_category.id]);

                            categories.splice(prevIndex, 0, Object.assign(move_category, {
                                parent_id: prev_parent_id,
                                depth: prev_depth
                            }))
                        });
                }

                function set(move_category, drop_category, before) {
                    remove(move_category);

                    switch (before) {
                        // Вставляем в конец
                        case null:
                            move_category.parent_id = "0";
                            move_category.depth = 0;
                            that.categories.push(move_category);
                            break;

                        // Вставляем выше drop_category
                        case true:
                            const { parent_id, categories, index } = getNeedCategoriesAndIndex(drop_category);

                            categories.splice(index, 0, move_category);

                            move_category.parent_id = parent_id;
                            move_category.depth = drop_category.depth;

                            break;

                        // Вставляем внутрь drop_category
                        case false:
                            drop_category.categories.push(move_category);
                            drop_category.states.expanded = true;
                            move_category.parent_id = drop_category.id;
                            move_category.depth = drop_category.depth + 1;
                            break;
                    }

                }

                function remove(move_category) {
                    const { categories, index } = getNeedCategoriesAndIndex(move_category);
                    if (index >= 0) {
                        categories.splice(index, 1);
                    } else {
                        console.log( "ERROR: remove category from array" );
                    }
                }

                function getNeedCategoriesAndIndex (category) {
                    let categories = that.categories,
                        parent_id = "0";

                    if (category.parent_id !== "0") {
                        const parent_category = that.categories_object[category.parent_id];
                        categories = parent_category.categories;
                        parent_id = parent_category.id;
                    }

                    let index = categories.indexOf(category);

                    return { parent_id, categories, index }
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
                            if (response.errors && 'text' in response.errors) {
                                $.wa_shop_products.alert(that.locales["warn"], response.errors.text);
                            }

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

            var categories = that.categories_object;

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

        Page.prototype.updateRootVariables = function(options) {
            var that = this;

            // Tooltips
            $.each(options.tooltips, function(i, tooltip) { $.wa.new.Tooltip(tooltip); });

            // Components
            that.components = $.extend(that.components, options.components);

            // Templates
            that.templates = $.extend(that.templates, options.templates);

            // Locales
            that.locales = $.extend(that.locales, options.locales);

            // Locales
            that.urls = $.extend(that.urls, options.urls);
        };

        Page.prototype.updateCategoriesCount = function(categories_ids) {
            categories_ids = (Array.isArray(categories_ids) ? categories_ids : []);

            var that = this,
                deferred = $.Deferred();

            var get_ids = getIds(categories_ids),
                static_ids = get_ids.static_ids,
                dynamic_ids = get_ids.dynamic_ids,
                limit = 100,
                recount_offset = 0,
                is_static = true;

            if (static_ids.length) {
                recountCategories();
                deferred.done(function () {
                    recountDynamic();
                });
            } else {
                recountDynamic();
            }
            if (!static_ids.length && !dynamic_ids.length) {
                deferred.resolve();
            }

            return deferred.promise();

            function recountDynamic() {
                if (dynamic_ids.length) {
                    limit = 10;
                    recount_offset = 0;
                    is_static = false;
                    recountCategories();
                }
            }

            function recountCategories() {
                var data = is_static ? {
                    static_ids: static_ids.slice(recount_offset, recount_offset + limit)
                } : {
                    dynamic_ids: dynamic_ids.slice(recount_offset, recount_offset + limit)
                };
                recount_offset += limit;
                request(data).done( function() {
                    if ((is_static ? static_ids.length : dynamic_ids.length) > recount_offset) {
                        recountCategories();
                    } else {
                        deferred.resolve();
                    }
                });
            }

            function request(data) {
                var ids = static_ids;
                if (data.dynamic_ids) { ids = data.dynamic_ids; }
                else if (data.static_ids) { ids = data.static_ids; }

                $.each(ids, function(i, category_id) {
                    var category = that.categories_object[category_id];
                    if (category) { category.states.count_locked = true; }
                });

                return $.post(that.urls["category_recount"], data, "json")
                    .done( function(response) {
                        var count_data = response.data.categories;

                        $.each(count_data, function(i, category_data) {
                            var category = that.categories_object[category_data.id];
                            if (category) { category.count = category_data.count; }
                        });

                        $.each(ids, function(i, category_id) {
                            var category = that.categories_object[category_id];
                            if (category) { category.states.count_locked = false; }
                        });
                    });
            }

            function getIds(categories_ids) {
                var static_ids = [],
                    dynamic_ids = [];

                $.each(that.categories_object, function(i, category) {
                    if (!categories_ids.length || categories_ids.indexOf(category.id) >= 0) {
                        if (category.type === "1") {
                            dynamic_ids.push(category.id);
                        } else {
                            static_ids.push(category.id);
                        }
                    }
                });

                return {
                    static_ids: static_ids,
                    dynamic_ids: dynamic_ids
                }
            }
        };

        // Dialogs

        Page.prototype.deleteCategoryDialog = function(html, options) {
            var that = this,
                ready = $.Deferred(),
                ready_promise = ready.promise(),
                vue_ready = $.Deferred();

            $.waDialog({
                html: html,
                options: {
                    category: options.category,
                    ready: ready_promise,
                    vue_ready: vue_ready.promise(),
                    initDialog: initDialog
                },
                onOpen: function($dialog, dialog) {
                    ready.resolve($dialog, dialog);
                }
            });

            return ready.promise();

            function initDialog($dialog, dialog, options) {

                var category = formatCategory(dialog.options.category, options.category);

                // VUE
                var $section = $dialog.find(".js-vue-section");

                Vue.createApp({
                    components: {
                        "component-switch": that.vue_components["component-switch"],
                    },
                    data() {
                        return {
                            category: category,
                            remove_products: false,
                            remove_categories: false,
                            locked: false
                        }
                    },
                    delimiters: ['{ { ', ' } }'],
                    computed: {
                        products_count: function() {
                            const self = this;
                            var count = (self.remove_categories ? self.category.count_total : self.category.count);
                            return (count > 0 ? count : 0);
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
                        },
                        resizeDialog: function() {
                            const self = this;
                            self.$nextTick( function() {
                                setTimeout( function() {
                                    dialog.resize();
                                }, 100);
                            });
                        }
                    },
                    watch: {
                        "remove_categories": function() {
                            this.resizeDialog();
                        }
                    },
                    created: function () {
                        $section.css("visibility", "");
                    },
                    mounted: function () {
                        var self = this,
                            $wrapper = $(self.$el);

                        self.resizeDialog();

                        vue_ready.resolve(dialog);
                    }
                }).mount($section[0]);

                function formatCategory(category, category_data) {
                    category.count = category_data.count;
                    category.count_total = category_data.count_total;
                    return category;
                }
            }
        };

        Page.prototype.editCategoryDialog = function(html) {
            var that = this,
                vue_ready = $.Deferred(),
                ready = $.Deferred();

            return $.waDialog({
                html: html,
                options: {
                    vue_ready: vue_ready.promise(),
                    ready: ready.promise(),
                    initDialog: initDialog
                },
                onOpen: function($dialog, dialog) {
                    ready.resolve(dialog);
                },
                onClose: function(dialog) {
                    dialog.app.unmount();
                }
            });

            function initDialog($dialog, dialog, options) {
                var category = Vue.reactive(formatCategory(options.category)),
                    category_sort_variants = options.category_sort_variants,
                    lang = options.lang;

                that.updateRootVariables(options);

                // VUE
                var $section = $dialog.find(".js-vue-section");

                var category_name = category.name,
                    category_data = that.categories_object[category.id];

                dialog.app = Vue.createApp({
                    data() {
                        return {
                            category: category,
                            init_category: null,
                            category_data: category_data,
                            category_types: that.category_types,
                            storefronts: getStorefronts(that.storefronts),
                            init_storefronts: null,
                            states: {
                                locked: false,
                                category_url_focus: false,
                                copy_locked: false,

                                visible_menu_url: (category.status === "1"),
                                visible_menu_url_at_subcategories: false,
                                update_storefronts_at_subcategories: false,

                                url_sync_started: false,
                                transliterate_xhr: null,
                                transliterate_timer: null
                            },
                            init_states: null,
                            errors: {}
                        }
                    },
                    delimiters: ['{ { ', ' } }'],
                    components: {
                        "component-checkbox": that.vue_components["component-checkbox"],
                        "component-radio": that.vue_components["component-radio"],
                        "component-switch": that.vue_components["component-switch"],
                        "component-dropdown": that.vue_components["component-dropdown"],
                        "component-textarea": that.vue_components["component-textarea"],
                        "component-category-visible-storefronts": {
                            props: ["category", "storefronts"],
                            data: function() {
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
                            props: ["root_states", "category", "category_data", "storefronts"],
                            components: {
                                "component-radio": that.vue_components["component-radio"],
                                "component-checkbox": that.vue_components["component-checkbox"],
                            },
                            data: function() {
                                var type = (category.storefronts.length ? "selection" : "all");

                                return {
                                    type: type
                                }
                            },
                            template: that.components["component-category-storefronts-section"],
                            delimiters: ['{ { ', ' } }'],
                            watch: {
                                "type": function(val) {
                                    var self = this;
                                    if (val === 'all') {
                                        self.setStorefrontsStates(false);
                                    }
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
                                    return self.storefronts.filter(storefront => storefront.states.enabled);
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
                                    self.setStorefrontsStates(show);

                                },
                                setStorefrontsStates: function (enabled) {
                                    $.each(this.storefronts, function(i, storefront) {
                                        storefront.states.enabled = enabled;
                                    });
                                }
                            }
                        },
                        "component-category-description-redactor": {
                            props: ["modelValue"],
                            emits: ["update:modelValue"],
                            template: that.components["component-category-description-redactor"],
                            delimiters: ['{ { ', ' } }'],
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
                                            self.$emit("update:modelValue", self.value);
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
                                                fileUploadFields: {
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
                                                    self.$emit("update:modelValue", self.value);

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
                            }
                        },
                        "component-category-filter-section": {
                            data: function() {
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
                                            html = html.replace("%added%", "");
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
                                        category.allow_filter_data[filter.id] = filter;
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
                        },
                        "component-category-features-section": {
                            data: function() {
                                return {
                                    category: category,
                                    states: {
                                        is_loading: false
                                    }
                                };
                            },
                            template: that.components["component-category-features-section"],
                            delimiters: ['{ { ', ' } }'],
                            components: {
                                "component-category-feature-item": {
                                    props: ["item", "index"],
                                    data: function() {
                                        return {
                                            states: {
                                                is_locked: false
                                            }
                                        };
                                    },
                                    template: that.components["component-category-feature-item"],
                                    delimiters: ['{ { ', ' } }'],
                                    computed: {
                                        item_class: function() {
                                            const self = this;
                                            var result = [];

                                            if (self.item.states.highlighted) {
                                                result.push("is-highlighted");
                                            }

                                            return result.join(" ");
                                        },
                                        values: function() {
                                            const self = this;

                                            var item_data = self.item.data;

                                            var result = [];

                                            switch (item_data.render_type) {
                                                case "tags":
                                                case "select":
                                                case "checkbox":
                                                    var values_object = $.wa.construct(item_data.options, "value");
                                                    if (item_data.values) {
                                                        $.each(item_data.values, function(i, value_id) {
                                                            var option = values_object[value_id];
                                                            if (option) {
                                                                var item = { name: option.name };
                                                                if (option.code) { item.color = option.code; }
                                                                result.push(item);
                                                            }
                                                        });
                                                    }
                                                    break;

                                                case "range":
                                                    var min = (!isNaN(parseFloat(item_data.options[0].value)) ? parseFloat(item_data.options[0].value) : null),
                                                        max = (!isNaN(parseFloat(item_data.options[1].value)) ? parseFloat(item_data.options[1].value) : null);

                                                    var range_item = {};

                                                    if (min !== null && max !== null) {
                                                        range_item.name = min + " — " + max;

                                                    } else if (min !== null) {
                                                        range_item.name = "≥ " + min;

                                                    } else if (max !== null) {
                                                        range_item.name = "≤ " + max;
                                                    }

                                                    if (item_data.currency) {
                                                        range_item.currency = " " + item_data.currency.sign_html;
                                                    }

                                                    if (item_data.active_unit && item_data.active_unit.name) {
                                                        range_item.unit = " " + item_data.active_unit.name;
                                                    }

                                                    if (range_item.name) {
                                                        result.push(range_item);
                                                    }
                                                    break;

                                                case "range.date":
                                                    var min = formatDate(item_data.options[0].value),
                                                        max = formatDate(item_data.options[1].value);

                                                    var value = "";

                                                    if (min && max) {
                                                        value = min + " — " + max;

                                                    } else if (min) {
                                                        value = "≥ " + min;

                                                    } else if (max) {
                                                        value = "≤ " + max;
                                                    }

                                                    if (value.length) {
                                                        result.push({ name: value });
                                                    }
                                                    break;
                                            }

                                            return result;

                                            function formatDate(date_string) {
                                                var result = null;

                                                if (date_string) {
                                                    result = date_string;
                                                    try {
                                                        if ($.datepicker) {
                                                            var date = $.datepicker.parseDate("yy-mm-dd", date_string);
                                                            result = $.datepicker.formatDate($.wa_shop_products.date_format, date);
                                                        }
                                                    }
                                                    catch(error) {
                                                        console.error(error);
                                                    }
                                                }

                                                return result;
                                            }
                                        }
                                    },
                                    methods: {
                                        edit: function() {
                                            const self = this;

                                            if (self.states.is_locked) { return false; }

                                            self.states.is_locked = true;

                                            // Имитация загрузки диалога
                                            var dialog_load = $.waDialog({
                                                html: that.templates["dialog-load"],
                                                onOpen: function($dialog, dialog) {
                                                    dialog.animateToggle(false);
                                                }
                                            });

                                            var data = {
                                                id: self.item.data.id,
                                                type: self.item.type
                                            };

                                            $.post(that.urls["category_condition_edit"], data, "json")
                                                .always( function() {
                                                    self.states.is_locked = false;
                                                    dialog_load.close();
                                                })
                                                .done( function(html) {
                                                    $.waDialog({
                                                        html: html,
                                                        animate: false,
                                                        options: {
                                                            item: self.item,
                                                            onSuccess: function(item) {
                                                                switch (item.data.render_type) {
                                                                    case "select":
                                                                        self.item.data["values"] = [item.data.value];
                                                                        break;

                                                                    case "checkbox":
                                                                        if (!self.item.data.values) { self.item.data["values"] = []; }

                                                                        self.item.data.values.splice(0);
                                                                        self.item.data.options.splice(0);
                                                                        $.each(item.data.options, function(i, option) {
                                                                            if (option.states.enabled) {
                                                                                option.value += "";
                                                                                self.item.data.options.push(option);
                                                                                self.item.data.values.push(option.value);
                                                                            }
                                                                        });
                                                                        break;

                                                                    case "tags":
                                                                        if (!self.item.data.values) { self.item.data["values"] = []; }

                                                                        self.item.data.values.splice(0);
                                                                        self.item.data.options.splice(0);
                                                                        $.each(item.data.options, function(i, option) {
                                                                            self.item.data.options.push(option);
                                                                            self.item.data.values.push(""+option.value);
                                                                        });
                                                                        break;

                                                                    case "range":
                                                                    case "range.date":
                                                                        $.each(self.item.data.options, function(i, option) {
                                                                            var value = item.data.options[i].value + "";
                                                                            if (item.data.render_type === "range") {
                                                                                var parsed_value = parseFloat(value);
                                                                                if (parsed_value >= 0 ? parsed_value + "" : value);
                                                                            }

                                                                            option["value"] = value;
                                                                        });
                                                                        if (self.item.data.active_unit) {
                                                                            var active_unit_search = self.item.data.units.filter( unit => (unit.value === item.data.active_unit.value));
                                                                            if (active_unit_search.length) {
                                                                                self.item.data["active_unit"] = active_unit_search[0];
                                                                            }
                                                                        }
                                                                        break;
                                                                }
                                                            },
                                                            initDialog: function($dialog, dialog, options) {
                                                                that.initEditCategoryConditionDialog($dialog, dialog, options);
                                                            }
                                                        },
                                                        onOpen: function($dialog, dialog) {
                                                            dialog.animateToggle(true);
                                                        },
                                                        onClose: function(dialog) {
                                                            dialog.app.unmount();
                                                            self.$nextTick( function() {
                                                                if (self.item.edit_on_add) {
                                                                    if (!self.values.length) {
                                                                        self.remove();
                                                                    } else {
                                                                        self.item.edit_on_add = false;
                                                                    }
                                                                }
                                                            });
                                                        }
                                                    });
                                                });
                                        },
                                        remove: function() {
                                            const self = this;
                                            self.$emit("remove", self.item);
                                        }
                                    },
                                    mounted: function() {
                                        const self = this;
                                        if (self.item.edit_on_add) {
                                            self.edit();
                                        }
                                    }
                                }
                            },
                            computed: {
                                added_conditions: function() {
                                    const self = this,
                                          result = {};

                                    $.each(self.category.conditions, function(i, condition) {
                                        var id = self.getFeatureID(condition);
                                        result[id] = condition;
                                    });

                                    return result;
                                }
                            },
                            methods: {
                                initAutocomplete: function($input) {
                                    const self = this;

                                    $input.autocomplete({
                                        source: function (request, response) {
                                            var data = {
                                                term: request.term
                                            };

                                            self.states.is_loading = true;

                                            $.post(that.urls["autocomplete_feature"], data, function(response_data) {
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
                                            if (ui.item) {
                                                self.addItem(ui.item);
                                                $input.val("");
                                            }
                                            return false;
                                        },
                                        focus: function() { return false; }
                                    }).data("ui-autocomplete")._renderItem = function(ul, item) {
                                        var id = self.getFeatureID(item),
                                            condition = self.added_conditions[id];

                                        var html = "<li class='ui-menu-item-html'><div>%added%"+item.name+"</div></li>",
                                            added_html = "<span class='s-added small color-blue'>" + that.locales["added"] + "</span> ";

                                        if (item.type === "feature") {
                                            var template_name = (!item.data.available_for_sku ? "component-category-filter-autocomplete-item-sku" : "component-category-filter-autocomplete-item");
                                            html = that.templates[template_name].replace("%name%", item.name).replace("%code%", item.code);
                                        }

                                        html = html.replace("%added%", (condition ? added_html : ""));

                                        return $(html).appendTo(ul);
                                    };

                                    $dialog.on("dialog-closed", function() {
                                        $input.autocomplete( "destroy" );
                                    });
                                },
                                addItem: function(item) {
                                    const self = this;

                                    var id = self.getFeatureID(item),
                                        condition = self.added_conditions[id];

                                    if (condition) {
                                        condition.states.highlighted = true;
                                        setTimeout( function() {
                                            condition.states.highlighted = false;
                                        }, 2000);
                                        return false;

                                    } else {
                                        self.category.conditions.push(formatItem(item));
                                    }

                                    function formatItem(item) {
                                        item.edit_on_add = true;
                                        item.data = getItemData(item.data).item_data;
                                        item.states = {
                                            highlighted: false
                                        };
                                        console.log( item );
                                        return item;
                                    }
                                },
                                removeItem: function(item) {
                                    const self = this;

                                    var index = self.category.conditions.indexOf(item);
                                    if (index >= 0) { self.category.conditions.splice(index, 1); }
                                },
                                getFeatureID: function(condition) {
                                    var result = null;

                                    try {
                                        result = condition.data.id;
                                        if (condition.type === "feature") {
                                            result = condition.data.code;
                                        }
                                    } catch(e) {

                                    };

                                    return result;
                                }
                            },
                            mounted: function() {
                                const self = this,
                                      $wrapper = $(self.$el);

                                self.$input = $wrapper.find('.js-autocomplete');

                                self.initAutocomplete(self.$input);
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
                        },
                        status_change_class: function() {
                            return this.init_category === JSON.stringify(this.category)
                                && this.init_states === JSON.stringify(this.states)
                                && this.init_storefronts === JSON.stringify(this.storefronts)
                                ? 'green' : 'yellow';
                        }
                    },
                    watch: {
                        "category.name": function(value) {
                            const self = this;

                            if (!value.length) {
                                self.errors["category_name"] = {
                                    id: "category_name",
                                    text: that.locales["error_category_name_empty"]
                                };
                            } else if (value.length > 255) {
                                self.errors["category_name"] = {
                                    id: "category_name",
                                    text: that.locales["error_category_name"]
                                };
                            } else {
                                delete self.errors["category_name"];
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
                        onBlurName: function() {
                            var self = this;
                            if (!self.category.name.length && !self.errors["category_name"]) {
                                self.errors["category_name"] = {
                                    id  : "category_name",
                                    text: that.locales["error_category_name_empty"]
                                };
                            }
                        },

                        onChangeIncludeSubCategories: function(value) {
                            const self = this;

                            if (value && self.category.sort_products === "") {
                                self.category.sort_products = "name ASC";
                            }

                            self.resizeDialog();
                        },
                        onChangeAllowFilter: function(value) {
                            const self = this;
                            self.resizeDialog();

                            if (value) {
                                self.$nextTick( function() {
                                    $(self.$el).find(".s-category-filter-section .js-autocomplete").trigger("focus");
                                });
                            }
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
                            const $category_form = $dialog.find('.s-category-form');

                            var data = getData(self.category);

                            // Submit data from fields added by plugins
                            $category_form.find('label.js-do-not-submit input[type="radio"]:not(.js-do-not-submit)').addClass('js-do-not-submit');
                            var additional_data = $category_form.find(':input[name]:not(.js-do-not-submit)').serializeArray();
                            additional_data.forEach(function(pair) {
                                if (!Object.hasOwnProperty(data, pair.name)) {
                                    data[pair.name] = pair.value;
                                }
                            });

                            // plugin JS validation and data collection
                            var evt = $.Event('wa_before_save', {
                                category: self.category,
                                form_data: data
                            });
                            $category_form.trigger(evt);
                            if (evt.isDefaultPrevented()) {
                                return;
                            }

                            $category_form.trigger($.Event('wa_save', {
                                category: self.category,
                                form_data: data
                            }));

                            self.states.locked = true;

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

                                    $category_form.trigger($.Event('wa_after_save', {
                                        category: self.category,
                                        response: response,
                                        form_data: data
                                    }));
                                });

                            function getData(category) {
                                var data = {
                                    category: {
                                        id         : category.id,
                                        url        : category.url,
                                        name       : category.name,
                                        type       : category.type,
                                        parent_id  : category.parent_id,
                                        description: category.description,

                                        // url at menu
                                        status              : (self.states.visible_menu_url ? 1 : 0),
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

                                if (category.conditions.length) {
                                    data.condition = {};
                                    $.each(category.conditions, function(i, condition) {
                                        var values = getConditionValues(condition);
                                        if (condition.type === "feature") {
                                            var code = condition.data.code;
                                            if (!code) { console.error(condition); }
                                            if (!data.condition["features"]) { data.condition["features"] = {}; }
                                            data.condition["features"][code] = values;
                                        } else {
                                            data.condition[condition.data.id] = values;
                                        }
                                    });
                                }

                                return data;

                                function getRoutes() {
                                    var filtered_storefronts = self.storefronts.filter( storefront => storefront.states.enabled );
                                    return filtered_storefronts.map( storefront => storefront.url);
                                }

                                function getOG() {
                                    var result = $.wa.clone(self.category.og);
                                    result.enabled = (self.category.og.enabled ? 1 : 0);

                                    if (self.category.og.enabled) {
                                        result.title = "";
                                        result.description = "";
                                        result.image = "";
                                        result.video = "";
                                        result.type = "";
                                    }

                                    return result;
                                }

                                function getConditionValues(condition) {
                                    var result = null;

                                    switch (condition.data.render_type) {
                                        case "select":
                                            if (condition.data.values.length) {
                                                result = condition.data.values[0];
                                            }
                                            break;

                                        case "checkbox":
                                            if (condition.data.values.length) {
                                                result = condition.data.values;
                                            }
                                            break;

                                        case "range":
                                        case "range.date":
                                            result = {
                                                begin: condition.data.options[0].value,
                                                end: condition.data.options[1].value
                                            };
                                            if (condition.data.active_unit && condition.data.active_unit.value) {
                                                result.unit = condition.data.active_unit.value;
                                            }
                                            break;

                                        case "tags":
                                            if (condition.data.values.length) {
                                                result = condition.data.values;
                                            }
                                            break;
                                    }

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
                        this.init_category = JSON.stringify(this.category);
                        this.init_states = JSON.stringify(this.states);
                        this.init_storefronts = JSON.stringify(this.storefronts);
                    },
                    mounted: function () {
                        var self = this,
                            $wrapper = $(self.$el);

                        self.resizeDialog();

                        if (!self.category.name.length) {
                            $wrapper.find(".js-autofocus").trigger("focus");
                        }

                        vue_ready.resolve(dialog, self);
                    }
                });

                dialog.app.config.compilerOptions.whitespace = 'preserve';

                dialog.app.mount($section[0]);

                function formatCategory(category) {
                    category = that.formatCategory(options.category);

                    if (Array.isArray(category.allow_filter_data)) {
                        category.allow_filter_data = {};
                    } else {
                        $.each(category.allow_filter_data, function(i, filter) {
                            category.allow_filter_data[i] = formatFilter(filter);
                        });
                    }

                    if (category.conditions.length)  {
                        $.each(category.conditions, function(i, condition) {
                            var data_object = getItemData(condition.data);
                            condition.data = data_object.item_data;
                            condition.states = {
                                highlighted: false
                            }
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
                            enabled: !!(category.storefronts.length && category.storefronts.indexOf(storefront.url) >= 0)
                        };
                    });

                    return storefronts;
                }
            }
        };

        Page.prototype.initEditCategoryConditionDialog = function($dialog, dialog, options) {
            var that = this;

            that.updateRootVariables(options);

            // VUE
            var $section = $dialog.find(".js-vue-section");

            var item = $.wa.clone(dialog.options.item),
                data_object = getItemData(item.data, options.values);

            dialog.app = Vue.createApp({
                data() {
                    return {
                        item: item,
                        item_data: data_object.item_data,
                        items_keys: data_object.items_keys,
                        wrapper: $section,
                        states: {
                            locked: false
                        }
                    }
                },
                delimiters: ['{ { ', ' } }'],
                components: {
                    "component-input": that.vue_components["component-input"],
                    "component-radio": that.vue_components["component-radio"],
                    "component-dropdown": that.vue_components["component-dropdown"],
                    "component-date-picker": that.vue_components["component-date-picker"],
                    "component-feature-value-checkbox": {
                        props: ["item_data"],
                        data: function() {
                            return {
                                search_string: "",
                                selection: []
                            };
                        },
                        template: that.components["component-feature-value-checkbox"],
                        delimiters: ['{ { ', ' } }'],
                        components: {
                            "component-feature-value-checkbox-item": {
                                props: ["item"],
                                emits: ["change"],
                                components: {
                                    "component-switch": that.vue_components["component-switch"]
                                },
                                data: function() {
                                    return {
                                        states: {
                                            timer_enable: 0,
                                            jump_animation: false,
                                            hidden: false,
                                            highlighted: false
                                        }
                                    };
                                },
                                template: that.components["component-feature-value-checkbox-item"],
                                delimiters: ['{ { ', ' } }'],
                                computed: {
                                    item_class: function() {
                                        let self = this,
                                            item = self.item,
                                            result = [];

                                        if (self.states.highlighted) { result.push("is-highlighted"); }
                                        if (self.item.states.ready) { result.push("is-ready"); }
                                        if (self.states.hidden) { result.push("fade-out"); }
                                        if (self.states.jump_animation) { result.push("is-jump-enabled"); }
                                        if (item.states.enabled) {
                                            result.push("jump-down");
                                        } else {
                                            result.push("jump-up");
                                        }

                                        return result.join(" ");
                                    }
                                },
                                methods: {
                                    onToggleSwitch: function(value) {
                                        var self = this;
                                        self.states.hidden = true;
                                        self.states.jump_animation = true;
                                        clearTimeout(self.states.timer_enable);
                                        self.states.timer_enable = setTimeout( function() {
                                            self.item.states.enabled = value;
                                            self.states.hidden = false;
                                            self.states.jump_animation = false;
                                        }, 500);

                                        self.$emit("change", {
                                            value: value,
                                            item: self.item
                                        });
                                    }
                                },
                                mounted: function() {
                                    var self = this;

                                    if (self.item.states.ready) {
                                        self.states.hidden = true;
                                        self.states.highlighted = true;
                                        setTimeout( function () {
                                            self.states.hidden = false;
                                        }, 10);
                                        setTimeout( function() {
                                            self.$nextTick( function() {
                                                self.states.highlighted = false;
                                            });
                                        }, 1000);

                                    } else {
                                        self.item.states.ready = true;
                                    }
                                }
                            }
                        },
                        computed: {
                            active_items: function() {
                                var self = this;
                                return self.item_data.options.filter( function(item) {
                                    return item.states.enabled;
                                });
                            },
                            inactive_items: function() {
                                var self = this;
                                return self.item_data.options.filter( function(item) {
                                    return !item.states.enabled;
                                });
                            }
                        },
                        methods: {
                            search: function() {
                                let self = this;

                                let selection = [];

                                // Делаем выборку
                                checkItems(self.item_data.options);

                                // Сохраняем выборку
                                self.selection = selection;

                                function checkItems(items) {
                                    $.each(items, function(i, item) {
                                        let display = true;

                                        if (!item.states.enabled) {
                                            if (self.search_string.length > 0) {
                                                let is_wanted = (item.name.toLowerCase().indexOf( self.search_string.toLowerCase() ) >= 0 );
                                                if (is_wanted) {
                                                    selection.push(item);
                                                } else {
                                                    display = false;
                                                }
                                            }
                                        }

                                        if (item.states.display !== display) {
                                            item.states.display = display;
                                        }
                                    });
                                }
                            },
                            revert: function() {
                                let self = this;
                                self.search_string = "";
                                self.search();
                            },

                            onToggleSwitch: function(options) {
                                const self = this;
                                var id = options.item.value + "";
                                if (options.value) {
                                    self.item_data.values.push(id);
                                } else {
                                    self.item_data.values.splice(self.item_data.values.indexOf(id), 1);
                                }
                            }
                        }
                    }
                },
                computed: {
                    has_value: function() {
                        const self = this;

                        var result = false;

                        switch (self.item_data.render_type) {
                            case "checkbox":
                                if (self.item_data.values) {
                                    result = !!self.item_data.values.length;
                                }
                                break;

                            case "range":
                            case "range.date":
                                $.each(self.item_data.options, function(i, option) {
                                    if (option.value.length) {
                                        result = true;
                                        return false;
                                    }
                                });
                                break;

                            case "tags":
                                if (self.item_data.options) {
                                    result = !!self.item_data.options.length;
                                }
                                break;
                        }

                        return result;
                    },
                    format: function() {
                        const self = this;
                        var result = null;

                        if (self.item_data.type.indexOf("double") >= 0) {
                            result = "number";

                        } else {
                            switch (self.item_data.render_type) {
                                case "range":
                                    result = "number";
                                    break;
                            }
                        }

                        return result;
                    },
                    validate: function() {
                        const self = this;

                        return self.item.is_negative ? "number-negative" : self.format;
                    },
                    errors: function() {
                        const self = this;
                        var result = {};

                        if (self.item_data.render_type === 'range') {
                            $.each(self.item_data.options, function(i, option) {
                                var is_valid = isValid(option.value);
                                if (!is_valid) {
                                    var error_id = "value_error_" + i;
                                    result[error_id] = { id: error_id, text: error_id };
                                }
                            });
                        }

                        return result;

                        function isValid(value) {
                            var result = true;

                            // value = (typeof value === "string" ? value : "" + value);
                            // value = $.wa.validate("number", value);

                            var limit_body = 11,
                                limit_tail = 4,
                                parts = value.replace(",", ".").split(".");

                            if (parts[0].length > limit_body || (parts[1] && parts[1].length > limit_tail)) {
                                result = false;
                            }

                            return result;
                        }
                    },
                    disabled: function() {
                        const self = this;
                        return !!(self.states.locked || Object.keys(self.errors).length || !self.has_value);
                    }
                },
                methods: {
                    onChange: function() {
                        const self = this;
                    },

                    save: function() {
                        const self = this;
                        dialog.options.onSuccess(self.item);
                        dialog.close();
                    },

                    initAutocomplete: function($input) {
                        const self = this;

                        $input.autocomplete({
                            source: function (request, response) {
                                var data = {
                                    term: request.term,
                                    feature_id: self.item_data.id
                                };

                                self.states.is_loading = true;

                                $.post(that.urls["feature_value"], data, function(response_data) {
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
                                    self.addItem(ui.item);
                                    $input.val("");
                                }
                                return false;
                            },
                            focus: function() { return false; }
                        }).data("ui-autocomplete")._renderItem = function( ul, item ) {
                            var html = "<div>"+item.name+"</div>";
                            if (item.code) {
                                html = "<div class=\"flexbox space-4\"><span class=\"s-color\"><span class=\"s-color icon color shift-2 size-12\" style=\"background-color:"+item.code+";\"></span></span><span class=\"s-name wide\">"+item.name+"</span></div>";
                            }
                            return $("<li />").addClass("ui-menu-item-html").append(html).appendTo(ul);
                        };

                        $dialog.on("dialog-closed", function() {
                            $input.autocomplete( "destroy" );
                        });
                    },
                    addItem: function(item) {
                        const self = this;
                        if (!self.items_keys[item.value]) {
                            self.item_data.options.push(item);
                            self.items_keys[item.value] = item;
                            dialog.resize();
                        }
                    },
                    removeItem: function(item) {
                        const self = this;
                        self.item_data.options.splice(self.item_data.options.indexOf(item), 1);
                        delete self.items_keys[item.value];
                        dialog.resize();
                    }
                },
                created: function() {
                    $section.css("visibility", "");
                },
                mounted: function() {
                    const self = this;

                    const $autocomplete = self.wrapper.find('.js-autocomplete');
                    if ($autocomplete.length) {
                        self.initAutocomplete($autocomplete);
                    }

                    dialog.resize();

                    self.$nextTick( function() {
                        self.wrapper.find(":input").first().trigger("focus");
                    });
                }
            });

            dialog.app.mount($section[0]);
        };

        return Page;

        function getItemData(item_data, values) {
            var items_keys = {};

            values = (typeof values === "undefined" ? [] : values);

            if (item_data.type) {
                switch (item_data.type) {
                    // Одинарное числовое поле превращаем в диапазон
                    case "double":
                        var white_list = ["field"];
                        if (white_list.indexOf(item_data.render_type) >= 0) {
                            item_data.render_type = "range";
                        }
                        break;

                    // Одинарное текстовое поле превращаем в теги
                    case "varchar":
                    case "color":
                        if (item_data.render_type === "field" || item_data.render_type === "color") {
                            item_data.render_type = "tags";
                        }
                        break;

                    case "date":
                        if (item_data.render_type === "field.date" || item_data.render_type === "range") {
                            item_data.render_type = "range.date";
                        }
                        break;

                    default:
                        if (item_data.type.indexOf("dimension") >= 0) {
                            if (item_data.render_type === "field") { item_data.render_type = "range"; }
                        }
                        break;
                }
            }

            if (item_data.render_type === "select") {
                item_data.render_type = "checkbox";
            }

            switch (item_data.render_type) {
                case "range":
                case "range.date":
                    if (item_data.options.length !== 2) {
                        item_data.options = [{ name: "", value: "" }, { name: "", value: "" }];
                    }
                    break;

                case "tags":
                    var options = [];
                    if (item_data.options.length) {
                        $.each(item_data.options, function(i, option) {
                            if (option.value) {
                                options.push(option);
                                items_keys[option.value] = option;
                            }
                        });
                    }
                    item_data.options = options;
                    break;

                case "checkbox":
                    if (!item_data.values) { item_data.values = [] }

                    // Добавляем опции в модель
                    if (values.length) { item_data.options = values; }

                    $.each(item_data.options, function(i, option) {
                        var enabled = (item_data.values.indexOf(""+option.value) >= 0);
                        option.states = {
                            enabled: enabled,
                            display: true,
                            ready: false
                        };
                    });
                    break;
            }

            return {
                item_data: item_data,
                items_keys: items_keys
            }
        }

    })($);

    $.wa_shop_products.init.initProductsCategoriesPage = function(options) {
        return new Page(options);
    };

})(jQuery);
