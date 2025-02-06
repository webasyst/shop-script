( function($) {

    var Page = ( function($) {

        function Page(options) {
            var that = this;
            const { reactive, ref } = Vue;

            // DOM
            that.$wrapper = options["$wrapper"];

            // CONST
            that.components = options["components"];
            that.templates  = options["templates"];
            that.tooltips   = options["tooltips"];
            that.locales    = options["locales"];
            that.urls       = options["urls"];

            // VUE VARS
            that.paging               = options["paging"];
            that.mass_actions         = formatActions(options["mass_actions"]);
            that.stocks               = options["stocks"];
            that.columns_array        = formatColumns(options["columns"]);
            that.columns              = $.wa.construct(that.columns_array, "id");
            that.page_url             = options["page_url"];
            that.products             = formatProducts(options["products"]);
            that.products_total_count = options["products_total_count"];
            that.filter               = formatActiveFilter(options["filter"]);
            that.filters              = formatFilters(options["filters"]);
            that.filter_options       = formatFilterOptions(options["filter_options"], that.filter.rules);
            that.presentations        = formatPresentations(options["presentations"]);
            that.presentation         = formatActivePresentation(options["presentation"]);
            that.products_selection   = ref("visible_products");

            that.model_data = {};
            that.states = reactive({
                $animation: null
            });
            that.keys = {
                table: 0,
                mass_actions: 0
            };
            that.broadcast = reactive(that.initBroadcast());

            /* @see component-tree-menu, component-table-filters-categories-sets-types */
            that.sidebarFiltersToggleManager = that.sidebarFiltersToggleManagerInit(that.filter_options);
            that.bus_sidebar_tree_menu = (new class {
                constructor () {
                    this.cbOnExpand = null;
                    this.cbOnSelectItem = null;
                }

                expand (menu_id, item, is_expanded) {
                    if (this.cbOnExpand) {
                        this.cbOnExpand(menu_id, item, is_expanded);
                    }
                }
                selectItem (menu_id, item) {
                    if (this.cbOnSelectItem) {
                        this.cbOnSelectItem(menu_id, item);
                    }
                }
                onExpand (callback) {
                    if (typeof callback === 'function') {
                        this.cbOnExpand = callback;
                    }
                }
                onSelectItem (callback) {
                    if (typeof callback === 'function') {
                        this.cbOnSelectItem = callback;
                    }
                }
            });

            // INIT
            that.vue_model = that.initVue();

            function formatActiveFilter(filter) {
                filter.states = {
                    is_changed: false
                    // is_changed: !filter.is_copy_template
                };

                var rule_groups = [];

                $.each(filter.rules, function(i, group) {
                    group.states = {
                        visible: false
                    }
                });

                return filter;
            }

            function formatFilters(filters) {
                $.each(filters, function(i, filter) {
                    formatPresentation(filter);
                });

                return filters;
            }

            function formatFilterOptions(options, active_rules) {
                options.categories_object = getCategoriesObject(options.categories);
                options.sets_object = getSetsObject(options.sets);

                const selectAndExpandParentsRecursively = (child, groupType) => {
                    child.is_selected = true;

                    const groupMap = {
                        categories: {
                            parentProp: 'parent_id',
                            items: options.categories_object
                        },
                        sets: {
                            parentProp: 'group_id',
                            items: options.sets.filter(s => s.group_id).reduce((acc, s) => ({ ...acc, [s.group_id]: s }), {})
                        }
                    };
                    const group = groupMap[groupType];
                    let parentId = child[group.parentProp];
                    while (parentId && parentId !== "0") {
                        const parent = group.items[parentId];

                        parent.is_open = true;
                        if (parentId === parent[group.parentProp]) {
                            break;
                        }
                        parentId = parent[group.parentProp];
                    }
                };

                $.each(active_rules, function(i, group) {
                    $.each(group.rules, function(i, rule) {
                        switch (group.type) {
                            case "categories":
                                var category = options.categories_object[rule.rule_params];
                                category.is_locked = true;
                                selectAndExpandParentsRecursively(category, group.type);
                                break;
                            case "sets":
                                var set = options.sets_object[rule.rule_params];
                                set.is_locked = true;
                                selectAndExpandParentsRecursively(set, group.type);
                                break;
                            case "types":
                                var type_search = options.types.filter( type => (type.id === rule.rule_params));
                                if (type_search.length) {
                                    type_search[0].is_locked = true;
                                    type_search[0].is_selected = true;
                                }
                                break;
                            case "storefronts":
                                var store_search = options.storefronts.filter( store => (store.id === rule.rule_params));
                                if (store_search.length) {
                                    store_search[0].is_locked = true;
                                }
                                break;
                            case "tags":
                                var tag_search = options.tags.filter( tag => (tag.id === rule.rule_params));
                                if (tag_search.length) {
                                    tag_search[0].is_locked = true;
                                }
                                break;
                            case "features":
                                break;
                        }
                    });
                });

                return options;

                function getCategoriesObject(categories) {
                    var result = {};

                    addCategoryToObject(categories);

                    return result;

                    function addCategoryToObject(categories) {
                        $.each(categories, function(i, category) {
                            result[category.id] = category;
                            if (category.categories) {
                                addCategoryToObject(category.categories);
                            }
                        });
                    }
                }

                function getSetsObject(sets) {
                    var result = {};

                    addSetToObject(sets);

                    return result;

                    function addSetToObject(sets) {
                        $.each(sets, function(i, set) {
                            if (!set.is_group) {
                                result[set.id] = set;
                            } else if (set.sets && set.sets.length) {
                                addSetToObject(set.sets);
                            }
                        });
                    }
                }
            }

            function formatPresentations(presentations) {
                $.each(presentations, function(i, presentation) {
                    formatPresentation(presentation);
                });

                return presentations;
            }

            function formatColumns(columns) {
                $.each(columns, function(i, column) {
                    if (!column.settings || Array.isArray(column.settings)) {
                        column.settings = {};
                    }
                });
                return columns;
            }

            function formatActivePresentation(presentation, columns_info) {
                presentation.states = {
                    is_changed: !presentation.is_copy_template
                };

                $.each(presentation.columns, function(i, column) {
                    const column_info = that.columns[column.column_type];
                    if (column_info.width > 0) {
                        column.width = column_info.width;

                        if (column_info.min_width && column_info.min_width > Number(column.width)) {
                            column.width = column_info.min_width;
                        }
                    }

                    presentation.sort_order = presentation.sort_order.toUpperCase();

                    column.states = {
                        width_timeout: 0,
                        width_locked: (typeof column_info.width_locked === "boolean" ? column_info.width_locked : false),
                        locked: false
                    };
                });

                return presentation;
            }

            function formatActions(actions) {
                $.each(actions, function(i, group) {
                    $.each(group.actions, function(j, action) {
                        action.states = {
                            visible: true,
                            is_locked: false
                        };
                    });
                });

                return actions;
            }
        }

        Page.prototype.sidebarFiltersToggleManagerInit = function(options) {
            const STORAGE_KEY = "shop/products/sidebar-filters-expanded";

            const get = () => {
                let storage = localStorage.getItem(STORAGE_KEY);
                if (storage) {
                    storage = JSON.parse(storage);
                }
                return storage;
            };
            const set = (data) => {
                if (!data) {
                    return;
                }
                data = JSON.stringify(data);
                localStorage.setItem(STORAGE_KEY, data);
            };
            const commit = (callback, type) => {
                const filters = get();
                if (type && filters && filters[type] && typeof callback === 'function') {
                    filters[type] = callback(filters[type]);
                    set(filters);
                }
            };
            // init
            (() => {
                const filters = get();
                if (filters) {
                    for (const key in filters) {
                        const $filters = (['categories'].includes(key) ? options[`${key}_object`] : options[key]);
                        if (!$filters) {
                            continue;
                        }

                        const filter_obj = Array.isArray($filters) ? $filters.reduce((acc, opt) => ({ ...acc, [opt.id]: opt }), {}) : $filters;
                        if (!Array.isArray(filters[key].expanded_items)) {
                            filters[key].expanded_items = [];
                        }
                        const expanded_items = filters[key].expanded_items;
                        if (expanded_items.length) {
                            const items_for_deleting = [];
                            for (const filter_id of expanded_items) {
                                if (filter_obj[filter_id]) {
                                    filter_obj[filter_id].is_open = true;
                                } else {
                                    items_for_deleting.push(filter_id);
                                }
                            }
                            // clear, if not found ids
                            if (items_for_deleting.length) {
                                filters[key].expanded_items = expanded_items.filter(id => !items_for_deleting.includes(id));
                            }
                        }
                    }
                    set(filters);

                } else {
                    set({
                        categories: {
                            is_expanded: true,
                            expanded_items: []
                        },
                        sets: {
                            is_expanded: false,
                            expanded_items: []
                        },
                        types: {
                            is_expanded: false,
                            expanded_items: []
                        }
                    });
                }
            })();

            return {
                get,
                setFilterType (type, is_expanded) {
                    commit((filter) => {
                        return { ...filter, is_expanded }
                    }, type);
                },
                addFilterItem (type, id) {
                    commit((filter) => {
                        filter.expanded_items.push(id);
                        return {
                            ...filter,
                            expanded_items: Array.from(new Set(filter.expanded_items))
                        }
                    }, type);
                },
                removeFilterItem (type, ids) {
                    if (!ids) {
                        return;
                    }
                    if (!Array.isArray(ids)) {
                        ids = [ids];
                    }
                    commit((filter) => {
                        return {
                            ...filter,
                            expanded_items: filter.expanded_items.filter(id => !ids.includes(id))
                        }
                    }, type);
                }
            }
        };

        Page.prototype.init = function() {
            $.each(this.tooltips, (i, tooltip) => {
                $.wa.new.Tooltip(tooltip);
            });

            this.updatePageURL();
        };

        Page.prototype.initVue = function() {
            var that = this;

            var $vue_section = that.$wrapper.find("#js-vue-section");

            if (typeof $.vue_app === "object" && typeof $.vue_app.unmount === "function") {
                $.vue_app.unmount();
            }

            // TODO: migrate to file
            that.vue_components = {
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

                "component-checkbox": {
                    props: ["modelValue", "label", "disabled", "field_id"],
                    emits: ["update:modelValue", "change", "click-input"],
                    data: function() {
                        return {
                            tag: (this.label !== false ? "label" : "span"),
                            id: (typeof this.field_id === "string" ? this.field_id : "")
                        }
                    },
                    computed: {
                        prop_disabled() { return (typeof this.disabled === "boolean" ? this.disabled : false); }
                    },
                    template: that.components["component-checkbox"],
                    delimiters: ['{ { ', ' } }'],
                    methods: {
                        onChange: function(event) {
                            var val = event.target.checked;
                            this.$emit("update:modelValue", val);
                            this.$emit("change", val);
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
                            value: self.val
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
                    props: ["modelValue", "options", "disabled", "button_class", "body_width", "body_class", "empty_option", "box_limiter_selector"],
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
                            protect: { box_limiter: self.box_limiter_selector, bottom: 40 },
                            open: function() {
                                self.$emit("focus");
                            },
                            close: function() {
                                self.$emit("blur");
                            }
                        }).waDropdown("dropdown");
                    }
                },

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

                "component-color-picker": {
                    props: ["modelValue", "readonly", "disabled", "field_class"],
                    emits: ["update:modelValue", "change", "focus"],
                    data: function() {
                        let start_value = null;

                        if (this.modelValue) {
                            start_value = this.modelValue.toLowerCase();
                            this.$emit("update:modelValue", start_value);
                        }

                        return {
                            is_ready: true,
                            extended: false,
                            start_value
                        };
                    },
                    template: that.components["component-color-picker"],
                    delimiters: ['{ { ', ' } }'],
                    computed: {
                        prop_readonly() { return (typeof this.readonly === "boolean" ? this.readonly : false) },
                        prop_disabled() { return (typeof this.disabled === "boolean" ? this.disabled : false) },
                        is_changed: function() { return (this.start_value !== this.modelValue); }
                    },
                    methods: {
                        onFocus: function() {
                            if (!this.extended) {
                                this.toggle(true);
                            }
                        },
                        onInput: function(e) {
                            this.$emit("update:modelValue", e.target.value);
                            this.setColor();
                        },
                        onChange: function(e) {
                            this.change();
                        },
                        toggle: function(show) {
                            if (this.prop_readonly || this.prop_disabled) { return false; }

                            show = (typeof show === "boolean" ? show : !this.extended);
                            this.extended = show;

                            if (show) {
                                this.$document.on("mousedown", this.watchOff);
                                this.setColor();
                                this.$emit("focus");

                            } else {
                                this.$document.off("mousedown", this.watchOff);
                                this.change();
                            }
                        },
                        setColor: function() {
                            var color = (this.modelValue ? this.modelValue : "#000000");

                            this.is_ready = false;
                            this.farbtastic.setColor(color);
                            this.is_ready = true;
                        },
                        watchOff: function(event) {
                            var is_target = this.$wrapper[0].contains(event.target);
                            if (!is_target) {
                                if (this.extended) { this.toggle(false); }
                                this.$document.off("mousedown", this.watchOff);
                            }
                        },
                        change: function() {
                            if (this.is_changed) {
                                this.$emit('change', this.modelValue);
                            }
                        }
                    },
                    mounted: function() {
                        const self = this;

                        self.$document = $(document);
                        self.$wrapper = $(self.$el);

                        const $picker = self.$wrapper.find(".js-color-picker");

                        self.farbtastic = $.farbtastic($picker, onChangeColor);

                        function onChangeColor(color) {
                            if (self.is_ready && self.modelValue !== color) {
                                self.$emit("update:modelValue", color);
                            }
                        }
                    }
                },

                "component-textarea": {
                    props: ["modelValue", "placeholder", "readonly", "cancel", "focus", "rows"],
                    emits: ["update:modelValue", "change", "focus", "blur", "cancel", "ready"],
                    data: function() {
                        return {
                            offset: 0,
                            textarea: null,
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
                                self.textarea.trigger("blur");
                            }
                        },
                        update: function() {
                            var self = this;
                            var textarea = $(self.$el);

                            textarea
                                .css("overflow", "hidden")
                                .css("min-height", 0);
                            var scroll_h = textarea[0].scrollHeight + self.offset;
                            textarea
                                .css("min-height", scroll_h + "px")
                                .css("overflow", "");
                        }
                    },
                    mounted: function() {
                        var self = this,
                            textarea = self.textarea = $(self.$el);

                        self.offset = textarea[0].offsetHeight - textarea[0].clientHeight;
                        self.update();

                        if (self.rows === '1') {
                            textarea.css("white-space", "nowrap");
                        }

                        self.$emit("ready", self.$el.value);

                        if (self.focus) { textarea.trigger("focus"); }
                    }
                },

                "component-input": {
                    props: ["modelValue", "placeholder", "readonly", "required", "cancel", "focus", "validate", "fraction_size", "format"],
                    emits: ["update:modelValue", "input", "change", "focus", "blur", "cancel", "ready"],
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
                            input: null,
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

                            self.$emit('input', $event.target.value);
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
                                self.input.trigger("blur");
                            }
                        },
                        onEnter: function($event) {
                            this.input.trigger("blur");
                        },
                        onChange: function(value) {
                            var self = this;
                            if (self.prop_required && self.modelValue === "") {
                                self.input.val(self.start_value);
                                self.$emit('update:modelValue', self.start_value);
                            } else {
                                self.$emit("change", value);
                            }
                        }
                    },
                    mounted: function() {
                        var self = this,
                            input = self.input = $(self.$el);

                        self.$emit("ready", self.$el.value);

                        if (self.prop_focus) {
                            input.trigger("focus");
                        }
                    }
                },

                "component-product-column-tags": {
                    props: ["product", "column", "column_info", "column_data", "ignore_limit"],
                    data: function() {
                        var self = this,
                            tags_states = {},
                            settings = (self.column.data && self.column.data.settings ? self.column.data.settings : {});

                        $.each(self.column_data.options, function(i, tag) {
                            tags_states[tag.value] = { is_locked: false }
                        });

                        return {
                            limit: (settings.visible_count && !self.ignore_limit ? settings.visible_count : null),
                            tags_states: tags_states
                        };
                    },
                    template: that.components["component-product-column-tags"],
                    delimiters: ['{ { ', ' } }'],
                    computed: {
                        prop_ignore_limit() { return (typeof self.ignore_limit === "boolean" ? self.ignore_limit : false); },
                        over_limit: function() {
                            var self = this,
                                result = null;

                            if (self.limit > 0 && self.limit < self.column_data.options.length) {
                                result = (self.column_data.options.length - self.limit);
                            }

                            return result;
                        },
                        tags: function() {
                            var self = this,
                                result = self.column_data.options;

                            if (self.over_limit) {
                                result = self.column_data.options.slice(0, self.limit);
                            }

                            return result;
                        }
                    },
                    methods: {
                        deleteTag: function(tag) {
                            var self = this,
                                tag_states = self.tags_states[tag.value],
                                type = "";

                            switch(self.column.column_type) {
                                case "tags":
                                    type = "tag";
                                    break;
                                case "categories":
                                    type = "category";
                                    break;
                                case "sets":
                                    type = "set";
                                    break;
                            }

                            var data = {
                                product_id: self.product.id,
                                dataset_id: tag.value,
                                type: type
                            };

                            tag_states.is_locked = true;

                            $.post(that.urls["product_exclude"], data, "json")
                                .always( function() {
                                    tag_states.is_locked = false;
                                })
                                .done( function(response) {
                                    if (response.status === "ok") {
                                        self.column_data.options.splice(self.column_data.options.indexOf(tag), 1);
                                    } else {
                                        alert("Server error");
                                        console.log( response.errors );
                                    }
                                });
                        },
                        showDialog: function() {
                            var self = this;

                            $.waDialog({
                                html: that.templates["dialog-product-column-tags"],
                                onOpen: initDialog
                            });

                            function initDialog($dialog, dialog) {
                                console.log( dialog );

                                var $section = $dialog.find(".js-vue-section");

                                Vue.createApp({
                                    data() {
                                        return {
                                            product: self.product,
                                            column: self.column,
                                            column_info: self.column_info,
                                            column_data: self.column_data
                                        }
                                    },
                                    delimiters: ['{ { ', ' } }'],
                                    components: {
                                        "component-product-column-tags": that.vue_components["component-product-column-tags"]
                                    },
                                    created: function () {
                                        $section.css("visibility", "");
                                    },
                                    mounted: function () {
                                        dialog.resize();
                                    }
                                }).mount($section[0]);
                            }
                        }
                    }
                },
                "component-sidebar-section": {
                    props: ["expanded", "label", "useButtonNew", "titleForNew"],
                    emits: ["expand"],
                    template: that.components["component-sidebar-section"],
                    delimiters: ['%', '%'],
                    data () {
                        return {
                            isExpanded: !!this.expanded
                        }
                    }
                },

                "component-tree-menu": {
                    props: {
                        menuId: String,
                        item: Object,
                        childrenProp: String,
                        searchString: String,
                        depth: {
                            type: Number,
                            default: 0
                        }
                    },
                    delimiters: ['%', '%'],
                    template: that.components["component-tree-menu"],
                    data() {
                        return { showChildren: false }
                    },
                    created() {
                        this.$.components = this.$parent.$.components;
                        this.showChildren = !!this.item.is_open;
                    },
                    computed: {
                        indent() {
                            return { paddingLeft: `${(this.depth + 1) * 22}px !important` }
                        },
                        displayItem() {
                            return (this.searchString === "" || this.item.states.is_wanted || this.item.states.is_wanted_inside);
                        },
                        displayChildren() {
                            return !!this.childrenProp && (this.showChildren || !!this.searchString?.trim())
                        },
                        itemClass() {
                            const result = [];
                            if (this.searchString !== "") {
                                if (this.item.states.is_wanted) {
                                    result.push("is-wanted");
                                }
                                if (this.item.states.is_wanted_inside) {
                                    result.push("is-wanted-inside");
                                }
                            }

                            if (!this.item.is_selected && this.item.is_locked) {
                                result.push("is-locked");
                            }

                            return result;
                        }
                    },
                    methods: {
                        toggleChildren() {
                            this.showChildren = !this.showChildren;
                            that.bus_sidebar_tree_menu.expand(this.menuId, this.item, this.showChildren);
                        },
                        onClick() {
                            if (this.item.is_group) {
                                this.toggleChildren();
                            } else {
                                that.bus_sidebar_tree_menu.selectItem(this.menuId, this.item);
                            }
                        },
                    }
                },
            };

            Object.assign(that.vue_components, {
                // Используется в различных частях (раздед, диалоги)
                "component-table-filters-search": {
                    props: ["modelValue", "search_icon", "placeholder"],
                    emits: ["update:modelValue", "search_input", "search_change"],
                    template: that.components["component-table-filters-search"],
                    delimiters: ['{ { ', ' } }'],
                    components: { "component-input": that.vue_components["component-input"] },
                    computed: {
                        prop_search_icon() { return (typeof this.search_icon === "boolean" ? this.search_icon : false) }
                    },
                    methods: {
                        onInput: function(val) {
                            this.$emit("update:modelValue", val);
                            this.$emit("search_input", val);
                        },
                        onChange: function(val) {
                            this.$emit("update:modelValue", val);
                            this.$emit("search_change", val);
                        },
                        revert: function() {
                            this.onChange("");
                        }
                    }
                },

                "component-category-search": {
                    props: ["modelValue"],
                    emits: ["update:modelValue", "search_input", "search_change"],
                    template: that.components["component-category-search"],
                    delimiters: ['{ { ', ' } }'],
                    components: { "component-input": that.vue_components["component-input"] },
                    methods: {
                        onInput: function(val) {
                            this.$emit("update:modelValue", val);
                            this.$emit("search_input", val);
                        },
                        onChange: function(val) {
                            this.$emit("update:modelValue", val);
                            this.$emit("search_change", val);
                        },
                        revert: function() {
                            this.onChange("");
                        }
                    }
                },

                "component-set-search": {
                    props: ["modelValue"],
                    emits: ["update:modelValue", "search_input", "search_change"],
                    template: that.components["component-set-search"],
                    delimiters: ['{ { ', ' } }'],
                    components: { "component-input": that.vue_components["component-input"] },
                    methods: {
                        onInput: function(val) {
                            this.$emit("update:modelValue", val);
                            this.$emit("search_input", val);
                        },
                        onChange: function() {
                            this.$emit("update:modelValue", val);
                            this.$emit("search_change", val);
                        },
                        revert: function() {
                            this.onChange("");
                        }
                    }
                }
            });

            const FilterApiMixin = {
                methods: {
                    save(values) {
                        that.animate(true);

                        that.broadcast.getPresentationsIds().done((ids) => {
                            const data = $.extend({
                                filter_id: that.filter.id,
                                presentation_id: that.presentation.id,
                                open_presentations: ids
                            }, values);

                            $.post(that.urls["filter_rule_add"], data, "json")
                                .done((response) => {
                                    that.clearTableScrollPosition();
                                    if (response.status === "ok") {
                                        that.reload({
                                            page: 1,
                                            presentation: (response.data.new_presentation_id || that.presentation.id)
                                        });
                                    } else {
                                        that.reload({ page: 1 });
                                    }
                                });
                        });
                    }
                }
            };
            const FilterSearchMixin = {
                data() {
                    return {
                        items: [],
                        search_string: ""
                    };
                },
                watch: {
                    search_string(q) {
                        this.items.forEach(item => {
                            if (q.trim() && item.name.toLowerCase().indexOf(this.search_string.toLowerCase()) < 0) {
                                item.is_hide = true;
                            } else {
                                delete item.is_hide;
                            }
                        });
                    }
                },
                computed: {
                    has_value: function() {
                        return !!this.items.filter(item => item.states.enabled).length;
                    },
                    empty_search_result: function() {
                        return this.items.every(t => t.is_hide);
                    }
                },
                mounted() {
                    const $wrapper = $(this.$el);

                    this.dropbox = $.wa.new.Dropbox({
                        $wrapper: $wrapper,
                        protect: false,
                        open: function() {
                            $wrapper.find("input.js-autofocus").trigger("focus");
                        }
                    });
                }
            };
            const SelectWithShiftMixin = {
                data() {
                    return {
                        last_checked_index: null
                    };
                },
                methods: {
                    onClickItem: function (e, index) {
                        const is_selected = e.target.checked;
                        if (!e.shiftKey || this.last_checked_index === index) {
                            this.last_checked_index = is_selected ? index : null;
                            return;
                        }

                        if (this.last_checked_index === null) {
                            for (let i = 0; i !== index; i++) {
                                this.products[i].states.selected = is_selected;
                            }

                        } else {
                            const dir = index > this.last_checked_index ? 1 : -1;
                            for (let i = this.last_checked_index; i !== index; i += dir) {
                                this.products[i].states.selected = is_selected;
                            }
                        }
                        this.last_checked_index = is_selected ? index : null;
                    }
                }
            };

            $.vue_app = Vue.createApp({
                data() {
                    return {
                        paging       : that.paging,
                        products     : that.products,
                        presentation : that.presentation,
                        states       : that.states,
                        keys         : that.keys
                    }
                },
                mixins: [SelectWithShiftMixin],
                components: {
                    "component-dropdown-presentations": {
                        emits: ["change", "success"],
                        data: function() {
                            return {
                                active_presentation: that.presentation,
                                presentations: that.presentations,
                                states: { show_add_form: false }
                            };
                        },
                        template: that.components["component-dropdown-presentations"],
                        delimiters: ['{ { ', ' } }'],
                        components: {
                            "component-presentation-add-form": {
                                emits: ["form_success"],
                                data: function() {
                                    return {
                                        name: "",
                                        states: {
                                            locked: false
                                        }
                                    }
                                },
                                template: that.components["component-presentation-add-form"],
                                delimiters: ['{ { ', ' } }'],
                                components: {
                                    "component-input": that.vue_components["component-input"]
                                },
                                computed: {},
                                methods: {
                                    cancel: function() {
                                        var self = this;
                                        if (!self.states.locked) {
                                            setTimeout( function() {
                                                self.$emit("form_cancel");
                                            }, 0);
                                        }
                                    },
                                    create: function() {
                                        var self = this;
                                        if (!self.states.locked) {
                                            // Делаем через таймаут, т.к. дропдаун реагирует на event.target которого после рендера не будет в DOM (дропдаун закроется)
                                            setTimeout( function() {
                                                self.states.locked = true;

                                                that.broadcast.getPresentationsIds().done( function(ids) {
                                                    var data = {
                                                        name: self.name,
                                                        presentation: that.presentation.id,
                                                        open_presentations: ids
                                                    };

                                                    $.post(that.urls["presentation_create"], data, "json")
                                                        .always( function() {
                                                            self.states.locked = false;
                                                        })
                                                        .done( function(response) {
                                                            self.name = "";
                                                            self.$emit("form_success", response.data);
                                                        });
                                                });

                                            }, 0);
                                        }
                                    }
                                }
                            },
                            "component-presentation-rename-form": {
                                props: ["presentation"],
                                emits: ["form_success"],
                                data: function() {
                                    return {
                                        name: this.presentation.name,
                                        states: {
                                            locked: false
                                        }
                                    }
                                },
                                template: that.components["component-presentation-rename-form"],
                                delimiters: ['{ { ', ' } }'],
                                methods: {
                                    cancel: function() {
                                        var self = this;
                                        if (!self.states.locked) {
                                            self.presentation.states.show_rename_form = false;
                                        }
                                    },
                                    create: function() {
                                        var self = this;
                                        if (!self.states.locked) {
                                            // Делаем через таймаут, т.к. дропдаун реагирует на event.target которого после рендера не будет в DOM (дропдаун закроется)
                                            setTimeout( function() {
                                                self.states.locked = true;

                                                var data = {
                                                    name: self.name,
                                                    presentation_id: self.presentation.id
                                                };

                                                $.post(that.urls["presentation_rename"], data, "json")
                                                    .always( function() { self.states.locked = false; })
                                                    .done( function(response) {
                                                        self.presentation.name = response.data.name;
                                                        self.presentation.states.show_rename_form = false;
                                                        self.$emit("form_success", response.data);
                                                    });
                                            }, 0);
                                        }
                                    }
                                }
                            }
                        },
                        computed: {
                            active_presentation_name: function() {
                                let result = null;
                                let session_name = sessionPresentationName();
                                if (session_name && !that.presentation.states.is_changed) {
                                    result = session_name;
                                    sessionPresentationName(null);
                                }

                                return result;
                            }
                        },
                        methods: {
                            onCopyUrl: function(event, presentation) {
                                let url = event.currentTarget.href;
                                let dummy = document.createElement('input');
                                document.body.appendChild(dummy);
                                dummy.value = url;
                                dummy.select();
                                document.execCommand('copy');
                                document.body.removeChild(dummy);

                                presentation.states.copy_locked = true;
                                setTimeout( function() {
                                    presentation.states.copy_locked = false;
                                }, 1000)
                            },

                            presentationAdd: function(new_presentation) {
                                var self = this;
                                new_presentation = formatPresentation(new_presentation);
                                self.presentations.unshift(new_presentation);
                                self.toggleForm(false);
                            },
                            presentationUse: function(presentation) {
                                var self = this;
                                self.$emit("change", presentation);
                            },
                            rename: function(presentation) {
                                presentation.states.show_rename_form = true;
                            },
                            rewrite: function(presentation) {
                                var self = this;

                                if (!presentation.states.rewrite_locked) {
                                    // Делаем через таймаут, т.к. дропдаун реагирует на event.target которого после рендера не будет в DOM (дропдаун закроется)
                                    setTimeout( function() {
                                        presentation.states.rewrite_locked = true;

                                        that.broadcast.getPresentationsIds().done( function(ids) {
                                            var data = {
                                                active_presentation_id: that.presentation.id,
                                                update_presentation_id: presentation.id,
                                                open_presentations: ids
                                            };

                                            $.post(that.urls["presentation_rewrite"], data, "json")
                                                .always( function() {
                                                    presentation.states.rewrite_locked = false;
                                                })
                                                .done( function(response) {
                                                    self.$emit("success", response.data);
                                                });
                                        });

                                    }, 0);
                                }
                            },
                            remove: function(presentation) {
                                var self = this;

                                if (!presentation.states.remove_locked) {
                                    self.dropdown.is_locked = true;

                                    showConfirm()
                                        .always( function() {
                                            setTimeout( function() {
                                                self.dropdown.is_locked = false;
                                            }, 0);
                                        })
                                        .done( function() {
                                            presentation.states.remove_locked = true;

                                            $.post(that.urls["presentation_remove"], { presentation_id: presentation.id }, "json")
                                                .always( function() {
                                                    presentation.states.remove_locked = false;
                                                })
                                                .done( function(response) {
                                                    var index = self.presentations.indexOf(presentation);
                                                    self.presentations.splice(index, 1);
                                                });
                                        });
                                }

                                function showConfirm() {
                                    var deferred = $.Deferred();
                                    var is_success = false;

                                    $.waDialog({
                                        html: that.templates["dialog-presentation-delete-confirm"],
                                        onOpen: initDialog,
                                        onClose: function() {
                                            if (is_success) {
                                                deferred.resolve();
                                            } else {
                                                deferred.reject();
                                            }
                                        }
                                    });

                                    function initDialog($dialog, dialog) {
                                        var $vue_section = $dialog.find(".js-vue-section");

                                        Vue.createApp({
                                            data() {
                                                return {
                                                    presentation: presentation,
                                                }
                                            },
                                            delimiters: ['{ { ', ' } }'],
                                            computed: {},
                                            methods: {
                                                deletePresentation: function() {
                                                    is_success = true;
                                                    dialog.close();
                                                }
                                            },
                                            created: function () {
                                                $vue_section.css("visibility", "");
                                            }
                                        }).mount($vue_section[0]);
                                    }

                                    return deferred.promise();
                                }
                            },
                            toggleForm: function(toggle) {
                                var self = this;
                                self.states.show_add_form = toggle;
                            },

                            initDragAndDrop: function($wrapper) {
                                var self = this;

                                var $document = $(document);

                                var drag_data = {},
                                    over_locked = false,
                                    is_change = false,
                                    timer = 0;

                                var presentations = self.presentations;

                                $wrapper.on("dragstart", ".js-presentation-move-toggle", function(event) {
                                    var $move = $(this).closest(".s-presentation-wrapper");
                                    is_change = false;

                                    var presentation_id = "" + $move.attr("data-id"),
                                        presentation = getPresentation(presentation_id);

                                    if (!presentation) {
                                        console.error("ERROR: presentation isn't exist");
                                        return false;
                                    }

                                    event.originalEvent.dataTransfer.setDragImage($move[0], 20, 20);

                                    drag_data.move_presentation = presentation;
                                    presentation.states.is_moving = true;

                                    $document.on("dragover", ".s-presentation-wrapper", onOver);
                                    $document.on("dragend", onEnd);
                                });

                                function onDrop() {
                                    if (is_change) {
                                        var presentation = drag_data.move_presentation;
                                        presentation.states.move_locked = true;
                                        moveRequest(drag_data.move_presentation)
                                            .always( function() {
                                                presentation.states.move_locked = false;
                                            });
                                    }
                                }

                                function onOver(event) {
                                    if (drag_data.move_presentation) {
                                        event.preventDefault();
                                        if (!over_locked) {
                                            over_locked = true;
                                            movePresentation($(event.currentTarget));
                                            setTimeout( function() {
                                                over_locked = false;
                                            }, 100);
                                        }
                                    }
                                }

                                function onEnd() {
                                    onDrop();
                                    off();
                                }


                                function movePresentation($over) {
                                    var presentation_id = "" + $over.attr("data-id"),
                                        presentation = getPresentation(presentation_id);

                                    if (!presentation) {
                                        console.error("ERROR: presentation isn't exist");
                                        return false;
                                    }

                                    if (drag_data.move_presentation === presentation) { return false; }

                                    var move_index = presentations.indexOf(drag_data.move_presentation),
                                        over_index = presentations.indexOf(presentation),
                                        before = (move_index > over_index);

                                    is_change = over_index !== move_index;
                                    if (is_change) {
                                        presentations.splice(move_index, 1);

                                        over_index = presentations.indexOf(presentation);
                                        var new_index = over_index + (before ? 0 : 1);

                                        presentations.splice(new_index, 0, drag_data.move_presentation);

                                        $over.trigger("dragend");
                                    }
                                }

                                function moveRequest(presentation) {
                                    var deferred = $.Deferred();

                                    that.broadcast.getPresentationsIds().done( function(ids) {
                                        var data = {
                                            presentation_id: presentation.id,
                                            presentations: getPresentations(),
                                            open_presentations: ids
                                        };

                                        $.post(that.urls["presentation_move"], data, "json")
                                            .fail( function() {
                                                deferred.reject();
                                            })
                                            .done( function(response) {
                                                if (response.status === "ok") {
                                                    deferred.resolve();
                                                } else {
                                                    alert("Error: presentation move");
                                                    console.log(response.errors);
                                                    deferred.reject(response.errors);
                                                }
                                            });
                                    });

                                    return deferred.promise();

                                    function getPresentations() {
                                        return self.presentations.map( function(presentation) {
                                            return presentation.id;
                                        });
                                    }
                                }

                                function getPresentation(presentation_id) {
                                    return presentations.find(p => p.id === presentation_id) || null;
                                }

                                function off() {
                                    drag_data = {};

                                    $document.off("dragover", ".s-presentation-wrapper", onOver);
                                    $document.off("dragend", onEnd);

                                    $.each(presentations, function(i, presentation) {
                                        presentation.states.is_moving = false;
                                    });
                                }
                            }
                        },
                        mounted: function() {
                            let self = this,
                                $dropdown = $(self.$el);

                            self.dropdown = $dropdown.waDropdown({
                                hover: false,
                                protect: false
                            }).waDropdown("dropdown");

                            self.initDragAndDrop($dropdown);
                        }
                    },
                    "component-view-toggle": {
                        props: ["modelValue"],
                        emits: ["update:modelValue", "change"],
                        template: that.components["component-view-toggle"],
                        delimiters: ['{ { ', ' } }'],
                        mounted: function() {
                            var self = this;
                            $(self.$el).waToggle({
                                change: function(event, target) {
                                    var value = $(target).data("id");
                                    self.$emit("update:modelValue", value);
                                    self.$emit("change", value);
                                }
                            });
                        }
                    },
                    "component-paging": {
                        props: ["modelValue", "count"],
                        emits: ["update:modelValue", "change"],
                        template: that.components["component-paging"],
                        data: function() {
                            var self = this,
                                page = (self.modelValue > 0 && self.modelValue <= self.count ? self.modelValue : 1);

                            return { page: page };
                        },
                        delimiters: ['{ { ', ' } }'],
                        methods: {
                            set: function(page) {
                                var self = this;

                                if (page > 0 && page <= self.count && self.page !== page) {
                                    self.$emit("update:modelValue", page);
                                    self.$emit("change", page);
                                    // Каунтер страницы должен обновиться при смене AJAX контента
                                    // self.page = page;
                                }
                            },
                            onInput: function(event) {
                                var self = this;

                                self.validate(event.target);
                            },
                            onChange: function(event) {
                                var self = this,
                                    page = 1,
                                    value = self.validate(event.target);

                                value = parseInt(value);
                                if (value > 0) {
                                    if (value <= self.count) {
                                        page = value;
                                    } else {
                                        page = self.count;
                                    }
                                }

                                self.set(page);

                                that.reload({ page: page });
                            },
                            validate: function(field) {
                                var self = this,
                                    $field = $(field),
                                    target_value = $field.val(),
                                    value = (typeof target_value === "string" ? target_value : "" + target_value);

                                value = $.wa.validate("integer", value);

                                if (value !== target_value) { $field.val(value); }

                                return value;
                            },
                            getHref: function(page) {
                                return that.getPageURL({
                                    page: page
                                });
                            }
                        }
                    },
                    "component-paging-dropdown": {
                        props: ["modelValue"],
                        emits: ["update:modelValue", "change"],
                        data: function() {
                            var self = this,
                                active_option = null,
                                options = [
                                    { "name": "10", "value": 10 },
                                    { "name": "30", "value": 30 },
                                    { "name": "50", "value": 50 },
                                    { "name": "100", "value": 100 }
                                ];

                            var active_search = options.filter( function(option) {
                                return option.value === parseInt(self.modelValue);
                            });

                            if (active_search.length) {
                                active_option = active_search[0];
                            }

                            return {
                                active_option: active_option,
                                options: options
                            };
                        },
                        template: that.components["component-paging-dropdown"],
                        delimiters: ['{ { ', ' } }'],
                        mounted: function() {
                            var self = this;

                            $(self.$el).waDropdown({
                                hover: false,
                                items: ".dropdown-item",
                                update_title: false,
                                protect: { right: 30 },
                                change: function(event, target, dropdown) {
                                    var $target = $(target),
                                        option_value = $target.attr("data-value");

                                    self.$emit("update:modelValue", option_value);
                                    self.$emit("change", option_value);
                                }
                            });
                        }
                    },
                    "component-table-filters": {
                        data: function() {
                            let search_rule = getSearchRule();

                            return {
                                search_rule: search_rule,
                                search_string: getSearchString()
                            };

                            function getSearchRule() {
                                var result = null;

                                if (that.filter.rules.length) {
                                    $.each(that.filter.rules, function(i, rule) {
                                        if (rule.type === "search") {
                                            result = rule;
                                            return false;
                                        }
                                    });
                                }

                                return result;
                            }

                            function getSearchString() {
                                var result = "";

                                if (search_rule) {
                                    result = search_rule.rules[0].rule_params;
                                }

                                return result;
                            }
                        },
                        template: that.components["component-table-filters"],
                        delimiters: ['{ { ', ' } }'],
                        mixins: [FilterApiMixin],
                        components: {
                            "component-table-product-search": {
                                props: ["modelValue", "placeholder"],
                                emits: ["update:modelValue", "search_input", "search_change"],
                                components: { "component-input": that.vue_components["component-input"] },
                                data: function() {
                                    return {
                                        states: {
                                            is_loading: false,
                                            show_reset: (this.value !== "")
                                        }
                                    };
                                },
                                template: that.components["component-table-product-search"],
                                delimiters: ['{ { ', ' } }'],
                                methods: {
                                    onInput: function(val) {
                                        this.$emit("update:modelValue", val);
                                        this.$emit("search_input", val);
                                    },
                                    onEnter: function(val) {
                                        this.$emit("update:modelValue", val);
                                        this.$emit("search_change", val);
                                    },
                                    revert: function() {
                                        this.onEnter("");
                                    },

                                    initAutocomplete: function($input) {
                                        const self = this;

                                        $input.autocomplete({
                                            source: function (request, response) {
                                                var data = {
                                                    term: request.term,
                                                    with_image: 1
                                                };

                                                self.states.is_loading = true;

                                                $.post(that.urls["product_search"], data, function(response_data) {
                                                    if (!response_data.length) {
                                                        response_data = [{
                                                            id: null,
                                                            value: that.locales["no_results"]
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
                                            position: {
                                                my: "left-38 top+14",
                                            },
                                            create: function() {
                                                //move autocomplete container
                                                $input.autocomplete("widget").appendTo($input.parent());
                                                $(".ui-helper-hidden-accessible").appendTo($input.parent());
                                            },
                                            select: function(event, ui) {
                                                if (ui.item.id) {
                                                    self.goToProduct(ui.item);
                                                    $input.trigger("blur");
                                                }
                                                return false;
                                            },
                                            focus: function(e, ui) {
                                                if (ui.item) {
                                                    // Запоминаем последний выбранный (с клавиатуры) товар, чтобы открыть его, если
                                                    // пользователь нажмёт Enter. По какой-то причине autocomplete не вызывает select,
                                                    // когда выбираешь товар стрелочками и нажимаешь Enter, поэтому нужен такой изврат.
                                                    self._last_focused_ui_item = ui.item;
                                                }
                                                return false;
                                            }
                                        }).data("ui-autocomplete")._renderItem = function(ul, item) {
                                            var html = "";

                                            if (!item.id) {
                                                html = that.templates["autocomplete-product-empty"].replace("%name%", item.value);

                                            } else if (item.image_url) {
                                                html = that.templates["autocomplete-product-with-image"]
                                                    .replace("%image_url%", item.image_url)
                                                    .replace("%name%", item.value)
                                                    .replace("%id%", item.id);

                                            } else {
                                                html = that.templates["autocomplete-product"]
                                                    .replace("%name%", item.value)
                                                    .replace("%id%", item.id);
                                            }

                                            return $("<li />").addClass("ui-menu-item-html").append(html).appendTo(ul);
                                        };
                                    },
                                    goToProduct: function(product) {
                                        const url = $.wa_shop_products.section_url + product.id + "/";
                                        $.wa_shop_products.router.load(url);
                                    }
                                },
                                mounted: function() {
                                    const self = this;
                                    var $wrapper = $(self.$el),
                                        $input = $wrapper.find('.js-autocomplete');

                                    $input.on("keydown", function(event) {
                                        var key = event.keyCode,
                                            is_enter = ( key === 13 );

                                        if (is_enter) {
                                            // Если юзер выбрал стрелочками товар, то открыть его.
                                            // Если не выбрал, то открыть список товаров с фильтрацией по строке поиска.
                                            if (self._last_focused_ui_item) {
                                                self.goToProduct(self._last_focused_ui_item);
                                                $input.trigger("blur");
                                            } else {
                                                self.onEnter($input.val());
                                            }
                                        } else {
                                            self._last_focused_ui_item = null;
                                        }
                                    });

                                    // Инициализация автокомплита должна быть после $input.on("keydown")
                                    // чтобы наш обработчик выполнялся ДО логики автокомплита.
                                    // Сначала мы должны сбросить self._last_focused_ui_item и только потом
                                    // автокомплит focus() выставит его в новое значение.
                                    self.initAutocomplete($input);
                                }
                            },
                            "component-table-filters-storefronts": {
                                template: that.components["component-table-filters-storefronts"],
                                delimiters: ['{ { ', ' } }'],
                                mixins: [FilterSearchMixin],
                                components: {
                                    "component-checkbox": that.vue_components["component-checkbox"],
                                    "component-table-filters-search": that.vue_components["component-table-filters-search"],
                                },
                                data: function() {
                                    let options = $.wa.clone(that.filter_options);

                                    return {
                                        items: formatOptions(options.storefronts),
                                        states: {
                                            is_changed: false
                                        }
                                    };

                                    function formatOptions(items) {
                                        $.each(items, function(i, item) {
                                            item.states = {
                                                enabled: false,
                                                locked: false
                                            }
                                        });
                                        return items;
                                    }
                                },
                                computed: { },
                                methods: {
                                    onChange: function() {
                                        if (!this.states.is_changed) {
                                            this.states.is_changed = true;
                                        }
                                    },
                                    changeStorefront: function(storefront) {
                                        if (storefront.is_locked) { return false; }

                                        storefront.states.enabled = !storefront.states.enabled;
                                        this.onChange();
                                    },
                                    reset: function() {
                                        $.each(this.items, function(i, storefront) {
                                            storefront.states.enabled = false;
                                        });

                                        this.onChange();
                                    },
                                    save: function() {
                                        this.dropbox.hide();
                                        this.$emit("success", this.items);
                                    }
                                }
                            },
                            "component-table-filters-tags": {
                                components: {
                                    "component-checkbox": that.vue_components["component-checkbox"],
                                    "component-table-filters-search": that.vue_components["component-table-filters-search"],
                                },
                                mixins: [FilterSearchMixin],
                                data: function() {
                                    let options = $.wa.clone(that.filter_options);

                                    return {
                                        items: formatOptions(options.tags),
                                        states: {
                                            is_changed: false
                                        }
                                    };

                                    function formatOptions(items) {
                                        $.each(items, function(i, item) {
                                            item.states = {
                                                enabled: false,
                                                locked: false
                                            }
                                        });
                                        return items;
                                    }
                                },
                                template: that.components["component-table-filters-tags"],
                                delimiters: ['{ { ', ' } }'],
                                computed: { },
                                methods: {
                                    onChange: function() {
                                        if (!this.states.is_changed) {
                                            this.states.is_changed = true;
                                        }
                                    },
                                    changeTag: function(tag) {
                                        if (tag.is_locked) { return false; }
                                        tag.states.enabled = !tag.states.enabled;
                                        this.onChange();
                                    },
                                    reset: function() {
                                        $.each(this.items, function(i, tag) {
                                            tag.states.enabled = false;
                                        });

                                        this.onChange();
                                    },
                                    save: function() {
                                        this.dropbox.hide();
                                        this.$emit("success", this.items);
                                    }
                                }
                            },
                            "component-table-filters-features": {
                                template: that.components["component-table-filters-features"],
                                delimiters: ['{ { ', ' } }'],
                                mixins: [FilterSearchMixin],
                                data: function() {
                                    let options = $.wa.clone(that.filter_options);

                                    return {
                                        items: formatOptions(options.features),
                                        states: {}
                                    };

                                    function formatOptions(items) {
                                        return items;
                                    }
                                },
                                components: {
                                    "component-table-filters-search": that.vue_components["component-table-filters-search"],
                                    "component-table-filters-features-item": {
                                        props: ["item"],
                                        emits: ["feature_dialog", "success"],
                                        template: that.components["component-table-filters-features-item"],
                                        delimiters: ['{ { ', ' } }'],
                                        methods: {
                                            toggle: function() {
                                                const dialog = $.waDialog({
                                                    html: that.templates["dialog-list-feature-value"],
                                                    options: {
                                                        item_data: this.item,
                                                        onSuccess: (data) => {
                                                            this.$emit("success", data);
                                                        }
                                                    },
                                                    onOpen: function($dialog, dialog) {
                                                        that.initDialogFeatureValue($dialog, dialog);
                                                    }
                                                });

                                                this.$emit("feature_dialog", dialog);
                                            }
                                        }
                                    }
                                },
                                computed: { },
                                methods: {
                                    hide: function(item) {
                                        this.search_string = "";
                                        this.dropbox.hide();
                                    },
                                    success: function(data) {
                                        this.$emit("success", data);
                                    }
                                }
                            },
                            "component-table-filters-list": {
                                data: function() {
                                    var formatted_filters = that.filter.rules.filter( filter => (filter.type !== "search") );

                                    return {
                                        filters: that.filters,
                                        result_filters: that.filter.rules,
                                        states: {
                                            show_add_form: false,
                                            disabled: !formatted_filters.length
                                        }
                                    };
                                },
                                template: that.components["component-table-filters-list"],
                                delimiters: ['{ { ', ' } }'],
                                components: {
                                    "component-filter-add-form": {
                                        emits: ["form_cancel", "form_success"],
                                        data: function() {
                                            return {
                                                name: "",
                                                states: {
                                                    locked: false
                                                }
                                            }
                                        },
                                        template: that.components["component-filter-add-form"],
                                        delimiters: ['{ { ', ' } }'],
                                        components: { "component-input": that.vue_components["component-input"] },
                                        methods: {
                                            cancel: function() {
                                                var self = this;
                                                if (!self.states.locked) {
                                                    setTimeout( function() {
                                                        self.$emit("form_cancel");
                                                    }, 0);
                                                }
                                            },
                                            create: function() {
                                                var self = this;
                                                if (!self.states.locked) {
                                                    // Делаем через таймаут, т.к. дропдаун реагирует на event.target которого после рендера не будет в DOM (дропдаун закроется)
                                                    setTimeout( function() {
                                                        self.states.locked = true;

                                                        that.broadcast.getPresentationsIds().done( function(ids) {
                                                            var data = {
                                                                name: self.name,
                                                                filter_id: that.filter.id,
                                                                open_presentations: ids
                                                            };

                                                            $.post(that.urls["filter_create"], data, "json")
                                                                .always( function() {
                                                                    self.states.locked = false;
                                                                })
                                                                .done( function(response) {
                                                                    self.name = "";
                                                                    self.$emit("form_success", response.data);
                                                                });
                                                        });
                                                    }, 0);
                                                }
                                            }
                                        }
                                    },
                                    "component-filter-rename-form": {
                                        props: ["filter"],
                                        emits: ["form_success"],
                                        data: function() {
                                            return {
                                                name: this.filter.name,
                                                states: {
                                                    locked: false
                                                }
                                            }
                                        },
                                        template: that.components["component-filter-rename-form"],
                                        delimiters: ['{ { ', ' } }'],
                                        computed: {},
                                        methods: {
                                            cancel: function() {
                                                var self = this;
                                                if (!self.states.locked) {
                                                    self.filter.states.show_rename_form = false;
                                                }
                                            },
                                            create: function() {
                                                var self = this;
                                                if (!self.states.locked) {
                                                    // Делаем через таймаут, т.к. дропдаун реагирует на event.target которого после рендера не будет в DOM (дропдаун закроется)
                                                    setTimeout( function() {
                                                        self.states.locked = true;

                                                        var data = {
                                                            name: self.name,
                                                            filter_id: self.filter.id
                                                        };

                                                        $.post(that.urls["filter_rename"], data, "json")
                                                            .always( function() { self.states.locked = false; })
                                                            .done( function(response) {
                                                                self.filter.name = response.data.name;
                                                                self.filter.states.show_rename_form = false;
                                                                self.$emit("form_success", response.data);
                                                            });
                                                    }, 0);
                                                }
                                            }
                                        }
                                    }
                                },
                                computed: {
                                    active_filter_name: function() {
                                        let self = this,
                                            result = null;

                                        if (self.filter && self.filter.name && !that.filter.states.is_changed) {
                                            result = self.filter.name;
                                        }

                                        return result;
                                    }
                                },
                                methods: {
                                    onCopyUrl: function(event, filter) {
                                        var url = event.currentTarget.href;
                                        var dummy = document.createElement('input');
                                        document.body.appendChild(dummy);
                                        dummy.value = url;
                                        dummy.select();
                                        document.execCommand('copy');
                                        document.body.removeChild(dummy);

                                        filter.states.copy_locked = true;
                                        setTimeout( function() {
                                            filter.states.copy_locked = false;
                                        }, 1000);
                                    },
                                    filterAdd: function(new_filter) {
                                        var self = this;
                                        new_filter = formatFilter(new_filter);
                                        self.filters.unshift(new_filter);
                                        self.toggleForm(false);
                                    },
                                    filterUse: function(filter) {
                                        that.broadcast.getPresentationsIds().done( function(ids) {
                                            that.clearTableScrollPosition();
                                            var data = {
                                                filter: filter.id,
                                                presentation: that.presentation.id,
                                                open_presentations: ids
                                            };

                                            that.reload(data);
                                        });
                                    },
                                    rename: function(filter) {
                                        filter.states.show_rename_form = true;
                                    },
                                    rewrite: function(filter) {
                                        var self = this;

                                        if (self.states.disabled) { return false; }

                                        if (!filter.states.rewrite_locked) {
                                            // Делаем через таймаут, т.к. дропдаун реагирует на event.target которого после рендера не будет в DOM (дропдаун закроется)
                                            setTimeout( function() {
                                                filter.states.rewrite_locked = true;

                                                that.broadcast.getPresentationsIds().done( function(ids) {
                                                    var data = {
                                                        active_filter_id: that.filter.id,
                                                        update_filter_id: filter.id,
                                                        open_presentations: ids
                                                    };

                                                    $.post(that.urls["filter_rewrite"], data, "json")
                                                        .always( function() {
                                                            filter.states.rewrite_locked = false;
                                                        })
                                                        .done( function(response) {
                                                            self.$emit("success", response.data);
                                                        });
                                                });

                                            }, 0);
                                        }
                                    },
                                    remove: function(filter) {
                                        var self = this;

                                        if (!filter.states.remove_locked) {
                                            self.dropbox.is_locked = true;

                                            showConfirm()
                                                .always( function() {
                                                    setTimeout( function() {
                                                        self.dropbox.is_locked = false;
                                                    }, 0);
                                                })
                                                .done( function() {
                                                    filter.states.remove_locked = true;

                                                    that.broadcast.getPresentationsIds().done( function(ids) {
                                                        var data = {
                                                            filter_id: filter.id,
                                                            open_presentations: ids
                                                        };

                                                        $.post(that.urls["filter_remove"], data, "json")
                                                            .always( function() {
                                                                filter.states.remove_locked = false;
                                                            })
                                                            .done( function(response) {
                                                                var index = self.filters.indexOf(filter);
                                                                self.filters.splice(index, 1);
                                                            });
                                                    });
                                                });
                                        }

                                        function showConfirm() {
                                            var deferred = $.Deferred();
                                            var is_success = false;

                                            $.waDialog({
                                                html: that.templates["dialog-filter-delete-confirm"],
                                                onOpen: initDialog,
                                                onClose: function() {
                                                    if (is_success) {
                                                        deferred.resolve();
                                                    } else {
                                                        deferred.reject();
                                                    }
                                                }
                                            });

                                            function initDialog($dialog, dialog) {
                                                var $vue_section = $dialog.find(".js-vue-section");

                                                Vue.createApp({
                                                    data() {
                                                        return {
                                                            filter: filter,
                                                        }
                                                    },
                                                    delimiters: ['{ { ', ' } }'],
                                                    computed: {},
                                                    methods: {
                                                        deleteFilter: function() {
                                                            is_success = true;
                                                            dialog.close();
                                                        }
                                                    },
                                                    created: function () {
                                                        $vue_section.css("visibility", "");
                                                    }
                                                }).mount($vue_section[0]);
                                            }

                                            return deferred.promise();
                                        }
                                    },
                                    toggleForm: function(toggle) {
                                        this.states.show_add_form = toggle;
                                    },

                                    initDragAndDrop: function($wrapper) {
                                        var self = this;

                                        var $document = $(document);

                                        var drag_data = {},
                                            over_locked = false,
                                            is_change = false,
                                            timer = 0;

                                        var filters = self.filters;

                                        $wrapper.on("dragstart", ".js-filter-move-toggle", function(event) {
                                            var $move = $(this).closest(".s-filter-wrapper");
                                            is_change = false;

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

                                        function onDrop() {
                                            if (is_change) {
                                                var filter = drag_data.move_filter;
                                                filter.states.move_locked = true;
                                                moveRequest(drag_data.move_filter)
                                                    .always( function() {
                                                        filter.states.move_locked = false;
                                                    });
                                            }
                                        }

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
                                            onDrop();
                                            off();
                                        }

                                        function moveFilter($over) {
                                            var filter_id = "" + $over.attr("data-id"),
                                                filter = getFilter(filter_id);

                                            if (!filter) {
                                                console.error("ERROR: filter isn't exist");
                                                return false;
                                            }

                                            if (drag_data.move_filter === filter) { return false; }

                                            var move_index = filters.indexOf(drag_data.move_filter),
                                                over_index = filters.indexOf(filter),
                                                before = (move_index > over_index);

                                            is_change = over_index !== move_index;
                                            if (is_change) {
                                                filters.splice(move_index, 1);

                                                over_index = filters.indexOf(filter);
                                                var new_index = over_index + (before ? 0 : 1);

                                                filters.splice(new_index, 0, drag_data.move_filter);

                                                $over.trigger("dragend");
                                            }
                                        }

                                        function moveRequest(filter) {
                                            var deferred = $.Deferred();

                                            var data = {
                                                filter_id: filter.id,
                                                filters: getFilters()
                                            }

                                            $.post(that.urls["filter_move"], data, "json")
                                                .fail( function() {
                                                    deferred.reject();
                                                })
                                                .done( function(response) {
                                                    if (response.status === "ok") {
                                                        deferred.resolve();
                                                    } else {
                                                        alert("Error: filter move");
                                                        console.log(response.errors);
                                                        deferred.reject(response.errors);
                                                    }
                                                });

                                            return deferred.promise();

                                            function getFilters() {
                                                return filters.map( function(filter) {
                                                    return filter.id;
                                                });
                                            }
                                        }


                                        function getFilter(filter_id) {
                                            return filters.find(f => f.id === filter_id) || null;
                                        }

                                        function off() {
                                            drag_data = {};

                                            $document.off("dragover", ".s-filter-wrapper", onOver);
                                            $document.off("dragend", onEnd);

                                            $.each(filters, function(i, filter) {
                                                filter.states.is_moving = false;
                                            });
                                        }
                                    }
                                },
                                mounted: function() {
                                    const self = this,
                                        $wrapper = $(self.$el);

                                    self.dropbox = $.wa.new.Dropbox({
                                        $wrapper: $wrapper,
                                        protect: false
                                    });

                                    self.initDragAndDrop($wrapper);
                                }
                            },

                            "component-table-filters-rules": {
                                data: function() {
                                    return {
                                        result_filters: that.filter.rules,
                                        formatted_filters: that.filter.rules.filter( filter => (filter.type !== "search") ),

                                        states: {
                                            resize_timeout: 0
                                        }
                                    };
                                },
                                template: that.components["component-table-filters-rules"],
                                delimiters: ['{ { ', ' } }'],
                                components: {
                                    "component-table-filters-rules-item": {
                                        props: ["group"],
                                        emits: ["remove_group"],
                                        data: function() {
                                            return {
                                                feature_data: getFeatureData(this.group)
                                            };

                                            function getFeatureData(group) {
                                                var result = null,
                                                    is_feature = (group.type.indexOf("feature_") >= 0);

                                                if (is_feature) {
                                                    var feature_id = group.type.replace("feature_", "");
                                                    var search = that.filter_options.features.filter( option => (option.id === feature_id));
                                                    if (search.length) {
                                                        result = search[0];
                                                    }
                                                }

                                                return result;
                                            }
                                        },
                                        template: that.components["component-table-filters-rules-item"],
                                        delimiters: ['{ { ', ' } }'],
                                        computed: {
                                            label: function() {
                                                const self = this;
                                                var result = null;

                                                if (self.feature_data) {
                                                    result = self.feature_data.name;
                                                } else if (self.group.name) {
                                                    result = self.group.name;
                                                }

                                                return result;
                                            },
                                            names: function() {
                                                const self = this,
                                                      result = [];

                                                if (self.group.label) {
                                                    result.push(self.group.label);

                                                } else {
                                                    $.each(self.group["rules"], function(i, rule) {
                                                        var name = getName(rule);
                                                        if (typeof name == 'string' && name.length > 0) {
                                                            result.push(name);
                                                        }
                                                    });
                                                }

                                                return result;

                                                function getName(rule) {
                                                    var result = "";

                                                    var options = that.filter_options[self.group.type];
                                                    if (!options) {
                                                        var fts = that.filter_options.features.filter(function(ft) {
                                                            return ft.rule_type == self.group.type;
                                                        });
                                                        if (fts.length) {
                                                            options = fts[0].options;
                                                        }
                                                    }
                                                    if (options) {
                                                        switch (self.group.type) {
                                                            case "categories":
                                                                options = that.filter_options.categories_object;
                                                                break;
                                                            case "sets":
                                                                options = that.filter_options.sets_object;
                                                                break;
                                                        }

                                                        $.each(options, function(i, option) {
                                                            if ((option.id||option.value) === rule.rule_params) {
                                                                result = option.name;
                                                            }
                                                        });
                                                    }

                                                    return result;
                                                }
                                            },
                                            name_tooltip: function() {
                                                const self = this;
                                                var result = "";

                                                if (self.group.hint) {
                                                    result = self.group.hint;
                                                } else {
                                                    var rule_name = getRuleName();
                                                    if (rule_name && self.group.label) {
                                                        var value = $.wa.unescape(self.group.label);
                                                        result = rule_name + ": " + value;
                                                    } else {
                                                        result = self.names.map($.wa.unescape).join(" | ");
                                                    }
                                                }

                                                return result;

                                                function getRuleName() {
                                                    var result = null;

                                                    var white_list = ["features"],
                                                        display_type = self.group.display_type;

                                                    if (display_type && white_list.indexOf(display_type) >= 0) {
                                                        if (self.group.name) {
                                                            result = self.group.name;
                                                        } else if (self.feature_data) {
                                                            result = self.feature_data.name;
                                                        }
                                                    }

                                                    return result;
                                                }
                                            },
                                            is_folder_dynamic: function() {
                                                const self = this;
                                                var result = false;
                                                if (self.group.type === 'categories') {
                                                    try {
                                                        var category_id = self.group.rules[0].rule_params,
                                                            category = that.filter_options.categories_object[category_id];
                                                        result = (category.type === "1");
                                                    } catch (e) {};
                                                }
                                                return result;
                                            },
                                            is_list_dynamic: function() {
                                                const self = this;
                                                var result = false;
                                                if (self.group.type === 'sets') {
                                                    try {
                                                        var set_id = self.group.rules[0].rule_params,
                                                            set = that.filter_options.sets_object[set_id];
                                                        result = (set.type === "1");
                                                    } catch (e) {};
                                                }
                                                return result;
                                            }
                                        },
                                        methods: {
                                            remove: function(group) {
                                                const self = this;
                                                self.$emit("remove_group", self.group);
                                            }
                                        }
                                    }
                                },
                                computed: {
                                    visible_filters: function() {
                                        var self = this;
                                        return self.formatted_filters.filter( filter => filter.states.visible );
                                    },
                                    invisible_filters: function() {
                                        var self = this;
                                        return self.formatted_filters.filter( filter => !filter.states.visible );
                                    }
                                },
                                methods: {
                                    resize: function(entry) {
                                        var self = this,
                                            $wrapper = $(self.$el);

                                        $wrapper.css("visibility", "hidden");
                                        clearTimeout(self.states.resize_timeout);
                                        self.states.resize_timeout = setTimeout(function() {
                                            resize();
                                            $wrapper.css("visibility", "");
                                        }, 100);

                                        function resize() {
                                            // Включаем всё
                                            $.each(self.formatted_filters, function(i, action) {
                                                action.states.visible = true;
                                            });

                                            // Дожидаемся ре-рендера
                                            self.$nextTick( function() {
                                                var list_w = self.$list.width();

                                                var width = 0,
                                                    visible_count = 0;

                                                // Считаем сколько пунктов влезает
                                                self.$list.find(".s-filter-item-wrapper").each( function() {
                                                    var $action = $(this),
                                                        action_w = $action.outerWidth(true);

                                                    width += action_w;
                                                    if (width <= list_w) {
                                                        visible_count += 1;
                                                    } else {
                                                        return false;
                                                    }
                                                });

                                                // Показываем часть пунктов, что влезли
                                                $.each(self.formatted_filters, function(i, action) {
                                                    action.states.visible = (i < visible_count);
                                                });
                                            });
                                        }
                                    },
                                    removeDropdownGroup: function(group) {
                                        const self = this;
                                        self.dropdown.hide();
                                        self.removeGroup(group);
                                    },
                                    removeGroup: function(group) {
                                        that.animate(true);

                                        that.broadcast.getPresentationsIds().done( function(ids) {
                                            var data = {
                                                filter_id: that.filter.id,
                                                rule_group: group.id,
                                                presentation_id: that.presentation.id,
                                                open_presentations: ids
                                            };

                                            $.post(that.urls["filter_rule_delete"], data, "json")
                                                .done( function(response) {
                                                    that.clearTableScrollPosition();
                                                    that.reload({
                                                        page: 1,
                                                        presentation: (response.data.new_presentation_id || that.presentation.id)
                                                    });
                                                });
                                        });
                                    },
                                    resetFilters: function(group) {
                                        that.animate(true);

                                        that.broadcast.getPresentationsIds().done( function(ids) {
                                            var data = {
                                                filter_id: that.filter.id,
                                                presentation_id: that.presentation.id,
                                                open_presentations: ids
                                            };

                                            $.post(that.urls["filter_rule_delete_all"], data, "json")
                                                .done( function(response) {
                                                    that.clearTableScrollPosition();
                                                    that.reload({
                                                        page: 1,
                                                        presentation: (response.data.new_presentation_id || that.presentation.id)
                                                    });
                                                });
                                        });
                                    }
                                },
                                mounted: function() {
                                    var self = this;

                                    if (!self.formatted_filters.length) { return false; }

                                    var $wrapper = $(self.$el),
                                        $dropdown = $wrapper.find(".js-dropdown");

                                    self.$list = $wrapper.find(".js-filters-list");
                                    self.dropdown = $dropdown.waDropdown({ hover: false }).waDropdown("dropdown");

                                    initObserver(self.$el);

                                    function initObserver(wrapper) {
                                        var resizeObserver = new ResizeObserver(onSizeChange);
                                        resizeObserver.observe(wrapper);
                                        function onSizeChange(entries) {
                                            var is_exist = $.contains(document, wrapper);
                                            if (is_exist) {
                                                var entry = entries[0].contentRect;
                                                self.resize(entry);
                                            } else {
                                                resizeObserver.unobserve(wrapper);
                                                resizeObserver.disconnect();
                                            }
                                        }
                                    }
                                }
                            },
                        },
                        methods: {
                            applySearch: function(value) {
                                const self = this;

                                // Фраза присутствует
                                if (value) {
                                    var data = {
                                        rule_type: "search",
                                        rule_params: [value]
                                    };

                                    self.save(data);

                                // Очищаем поиск
                                } else if (self.search_rule) {

                                    that.animate(true);

                                    that.broadcast.getPresentationsIds().done( function(ids) {
                                        var data = {
                                            filter_id: that.filter.id,
                                            rule_group: self.search_rule.id,
                                            presentation_id: that.presentation.id,
                                            open_presentations: ids
                                        };

                                        $.post(that.urls["filter_rule_delete"], data, "json")
                                            .done( function(response) {
                                                that.clearTableScrollPosition();
                                                that.reload({
                                                    page: 1,
                                                    presentation: (response.data.new_presentation_id || that.presentation.id)
                                                });
                                            });
                                    });
                                }
                            },
                            applyStorefronts: function(storefronts) {
                                const self = this;

                                var data = {
                                    rule_type: "storefronts",
                                    rule_params: []
                                }

                                $.each(storefronts, function(i, item) {
                                    if (item.states.enabled) { data.rule_params.push(item.id); }
                                });

                                self.save(data);
                            },
                            applyTags: function(tags) {
                                const self = this;

                                var data = {
                                    rule_type: "tags",
                                    rule_params: []
                                };

                                $.each(tags, function(i, tag) {
                                    if (tag.states.enabled) { data.rule_params.push(tag.id); }
                                });

                                self.save(data);
                            },
                            applyFeatures : function(features_data) {
                                this.save(features_data);
                            }
                        }
                    },

                    "component-empty-content": {
                        props: ["type"],
                        template: that.components["component-empty-content"],
                    },
                    "component-product-thumb": {
                        props: ["product", "index"],
                        template: that.components["component-product-thumb"],
                        delimiters: ['{ { ', ' } }'],
                        components: {
                            "component-checkbox": that.vue_components["component-checkbox"],
                            "component-product-slider": {
                                props: ["photos", "photo_id", "product_states"],
                                data: function() {
                                    var self = this,
                                        index = 0;

                                    // Активное фото
                                    if (self.photo_id) {
                                        $.each(self.photos, function(i, photo) {
                                            if (photo.id === self.photo_id) {
                                                index = i;
                                                return false;
                                            }
                                        });
                                    }

                                    return {
                                        index: index,
                                        root_index: index,
                                        timer: 0
                                    }

                                    // Фильтрация точек
                                    function getControls(index, count) {
                                        count = (typeof count === "number" ? count : 7);

                                        var photos       = self.photos,
                                            before_count = 0,
                                            after_count  = 0;

                                        if (photos.length > count) {
                                            var step = Math.floor((count - 1)/2),
                                                start = (index - step),
                                                end   = start + (count-1);

                                            before_count = start;
                                            after_count  = (photos.length - (end + 1));

                                            if (start < 0) {
                                                start = 0;
                                                end   = (count-1);
                                                before_count = 0;
                                                after_count  = (photos.length - count);
                                            } else if (end > (photos.length - 1)) {
                                                end   = (photos.length - 1);
                                                start = end - (count-1);
                                                before_count = (photos.length - count);
                                                after_count  = 0;
                                            }

                                            photos = photos.slice(start, end+1);
                                        }

                                        return {
                                            count       : count,
                                            photos      : photos,
                                            before_count: before_count,
                                            after_count : after_count
                                        }
                                    }
                                },
                                template: that.components["component-product-slider"],
                                delimiters: ['{ { ', ' } }'],
                                computed: {
                                    active_photo: function() {
                                        var self = this;
                                        return self.photos[self.index];
                                    },
                                    controls: function() {
                                        var self = this;

                                        var index = self.index,
                                            count = 7;

                                        var photos       = self.photos,
                                            before_count = 0,
                                            after_count  = 0;

                                        if (photos.length > count) {
                                            var step = Math.floor((count - 1)/2),
                                                start = (index - step),
                                                end   = start + (count-1);

                                            before_count = start;
                                            after_count  = (photos.length - (end + 1));

                                            if (start < 0) {
                                                start = 0;
                                                end   = (count-1);
                                                before_count = 0;
                                                after_count  = (photos.length - count);
                                            } else if (end > (photos.length - 1)) {
                                                end   = (photos.length - 1);
                                                start = end - (count-1);
                                                before_count = (photos.length - count);
                                                after_count  = 0;
                                            }

                                            photos = photos.slice(start, end+1);
                                        }

                                        return {
                                            count       : count,
                                            photos      : photos,
                                            before_count: before_count,
                                            after_count : after_count
                                        }
                                    },
                                    product_url: function() {
                                        return this.$parent.product_url;
                                    },
                                },
                                methods: {
                                    next: function() {
                                        var self = this,
                                            index = (self.index >= (self.photos.length - 1) ? 0 : self.index + 1);
                                        self.move(index);
                                    },
                                    prev: function() {
                                        var self = this,
                                            index = (self.index >= 1 ? self.index - 1 : self.photos.length - 1);
                                        self.move(index);
                                    },
                                    change: function(photo) {
                                        var self = this,
                                            index = self.photos.indexOf(photo);
                                        self.move(index);
                                    },
                                    move: function(index) {
                                        var self = this,
                                            left = index * self.$list.width();
                                        self.index = index;
                                        self.$list.scrollLeft(left);
                                    },
                                    onMouseEnter: function() {
                                        clearTimeout(this.timer);
                                    },
                                    onMouseLeave: function() {
                                        var self = this;

                                        self.timer = setTimeout( function() {
                                            if (self.index !== self.root_index) {
                                                self.move(self.root_index);
                                            }
                                        }, 1000);
                                    }
                                },
                                mounted: function() {
                                    var self = this;
                                    self.$list = $(self.$el).find(".s-slider-list");
                                    if (self.index > 0) { self.move(self.index); }
                                    self.$list.addClass("is-animated");
                                }
                            }
                        },
                        computed: {
                            product_url: function() {
                                return "products/" + this.product.id + "/?presentation=" + that.presentation.id;
                            },
                            product_code: function() {
                                var self = this,
                                    result = "",
                                    sku_count = self.product.skus.length,
                                    sku_mod_count = self.product.sku_count;

                                if (self.product.normal_mode) {
                                    var string = $.wa.locale_plural(sku_count, that.locales["product_sku_forms"]),
                                        sku_mod_string = $.wa.locale_plural(sku_mod_count, that.locales["product_sku_mod_forms"]);

                                    result = string.replace("%s", sku_mod_string);

                                } else if (self.product.sku.sku) {
                                    result = self.product.sku.sku;
                                }

                                return result;
                            },
                            product_status: function() {
                                var self = this,
                                    result = "";

                                switch (self.product.status) {
                                    case "1":
                                        result = that.locales["published"];
                                        break;
                                    case "0":
                                        result = that.locales["hidden"];
                                        break;
                                    case "-1":
                                        result = that.locales["unpublished"];
                                        break;
                                }

                                return result;
                            },
                            product_prices: function() {
                                var self = this,
                                    result = "&nbsp;",
                                    prices = [],
                                    price_strings = {};

                                $.each(self.product.skus, function(i, sku) {
                                    $.each(sku.modifications, function(i, sku_mod) {
                                        var price = parseFloat(sku_mod.price);
                                        prices.push(price);
                                        price_strings[price] = sku_mod.price_string;
                                    });
                                });

                                var min = Math.min.apply(null, prices),
                                    max = Math.max.apply(null, prices);

                                if (min >= 0 && max >= 0) {
                                    if (min === max) {
                                        result = price_strings[max];
                                    } else {
                                        result = "<span class='price-min'>" + price_strings[min] + "</span>" + " ... " + "<span class='price-max'>" + price_strings[max] + "</span>";
                                    }
                                }

                                return result;
                            },
                            name_class: function() {
                                let result = [];

                                var name_column = that.columns["name"],
                                    settings = name_column.settings;

                                if (settings) {
                                    var name_format = (settings.long_name_format ? settings.long_name_format : "");
                                    if (name_format === "hide_end") {
                                        result.push("one-line");
                                    }
                                }

                                return result.join(" ");
                            },
                            image_hovered: function() {
                                return this.product.states.image_hovered;
                            },
                        },
                        methods: {
                            onClickItem: function () {
                                const [ event, index ] = arguments;
                                this.$parent.onClickItem.call(null, event, index);

                                setTimeout(() => {
                                    const container = document.querySelector('.s-products-thumbs-section'),
                                        el = event.target.closest('.s-checkbox-wrapper');
                                    if (!this.elemInVisibleArea(container, el)) {
                                        event.target.closest('.s-product-section').scrollIntoView();
                                    }
                                });
                            },
                            elemInVisibleArea(container, element) {
                                const containerBounds = container.getBoundingClientRect(),
                                    containerTop = containerBounds.top,
                                    containerBottom = containerBounds.bottom;

                                const elementBounds = element.getBoundingClientRect(),
                                    elementTop = elementBounds.top,
                                    elementBottom = elementBounds.bottom;

                                const isVisible = elementTop >= containerTop && elementTop <= containerBottom &&
                                    elementBottom >= containerTop && elementBottom <= containerBottom;

                                return isVisible;
                            }
                        }
                    },
                    "component-products-table": {
                        props: ["products"],
                        data: function() {
                            return {
                                columns : that.columns,
                                presentation : that.presentation,
                                states: {
                                    has_image_column: hasImageColumn()
                                }
                            }

                            function hasImageColumn() {
                                var columns = that.presentation.columns.map( function(column) { return column.column_type; });
                                return (columns.indexOf("image_crop_small") >= 0);
                            }
                        },
                        template: that.components["component-products-table"],
                        delimiters: ['{ { ', ' } }'],
                        components: {
                            "component-checkbox": that.vue_components["component-checkbox"],
                            "component-products-table-header": {
                                props: ["column", "columns", "presentation"],
                                template: that.components["component-products-table-header"],
                                delimiters: ['{ { ', ' } }'],
                                computed: {
                                    is_stock: function() {
                                        return (this.column.column_type.indexOf('stocks_') === 0);
                                    },
                                    is_virtual_stock: function() {
                                        return (this.column.column_type.indexOf('stocks_v') === 0);
                                    },
                                    is_feature: function() {
                                        return (this.column.column_type.indexOf('feature_') === 0);
                                    },
                                    column_name: function() {
                                        var self = this,
                                            column = self.column,
                                            result = "";

                                        let hide_column_names = ["image_crop_small"],
                                            hide_name = (hide_column_names.indexOf(column.column_type) >= 0);

                                        if (!hide_name) {
                                            result = (self.columns[column.column_type] ? self.columns[column.column_type].name : "COLUMN NOT EXIST")
                                        }

                                        return result;
                                    },
                                    column_class_name: function() {
                                        const result = [];

                                        switch (this.column.column_type) {
                                            case "name":
                                                result.push("s-column-name");
                                                break;
                                            case "image_crop_small":
                                                result.push("s-column-photo");
                                                break;
                                        }

                                        return result.join(" ");
                                    },
                                    column_sortable: function() {
                                        var self = this;
                                        return (self.columns[self.column.column_type] ? self.columns[self.column.column_type].sortable : false);
                                    },
                                    column_available_for_sku: function() {
                                        var self = this,
                                            column_info = that.columns[self.column.column_type];

                                        return column_info.available_for_sku;
                                    },
                                    column_width: function() {
                                        let self = this,
                                            result = "";

                                        if (self.column.width > 0) {
                                            result = self.column.width + "px";

                                        } else {
                                            var column_info = that.columns[self.column.column_type];
                                            if (column_info.min_width) {
                                                result = column_info.min_width + "px";
                                            };
                                        }

                                        return result;
                                    },
                                },
                                methods: {
                                    onMouseEnter: function() {
                                        var self = this,
                                            column = self.column;

                                        var html = getHTML(that.columns[column.column_type]);

                                        setHTML(html);

                                        function getHTML(column_info) {
                                            var column_name = self.column_name,
                                                html = column_name;

                                            if (self.is_feature) {
                                                var template = (column_info.available_for_sku ? that.templates["table_tooltip_feature_sku"] : that.templates["table_tooltip_feature"]);
                                                html = template
                                                    .replace("%visible_in_frontend%", (column_info.visible_in_frontend ? '<i class="fas fa-eye color-gray"></i>' : '<i class="fas fa-eye-slash color-gray-lighter"></i>'))
                                                    .replace("%name%", column_name);
                                            } else if (self.is_virtual_stock) {
                                                html = that.templates["table_tooltip_virtual_stock"]
                                                    .replace("%name%", column_name);
                                            } else if (self.is_stock) {
                                                html = that.templates["table_tooltip_stock"]
                                                    .replace("%name%", column_name);
                                            } else {
                                                switch(self.column.column_type) {
                                                    case "categories":
                                                        html = that.locales["column_categories_hint"];
                                                        break;
                                                    case "sets":
                                                        html = that.locales["column_sets_hint"];
                                                        break;
                                                }
                                            }
                                            return html;
                                        }

                                        function setHTML(html) {
                                            var tooltips = $.wa.new.Tooltip(),
                                                tooltip = tooltips["table-column"];

                                            tooltip.update({ html: html });
                                        }
                                    },
                                    onColumnSort: function() {
                                        var self = this;

                                        if (!self.column_sortable) { return false; }

                                        that.animate(true);

                                        let sort_order = "ASC";
                                        if (self.column.id === that.presentation.sort_column_id && that.presentation.sort_order.toUpperCase() === "ASC") {
                                            sort_order = "DESC";
                                        }

                                        that.broadcast.getPresentationsIds().done( function(ids) {
                                            var data = {
                                                presentation_id: that.presentation.id,
                                                open_presentations: ids,
                                                column_id: self.column.id,
                                                sort_order: sort_order
                                            };

                                            $.post(that.urls["presentation_edit_settings"], data, "json")
                                                .always( function() {
                                                    that.presentation.states.is_changed = true;
                                                })
                                                .done( function(response) {
                                                    if (response.status === "ok") {
                                                        that.reload({
                                                            page: 1,
                                                            presentation: (response.data.new_presentation_id || that.presentation.id)
                                                        });
                                                    } else {
                                                        that.reload({ page: 1 });
                                                    }
                                                });
                                        });
                                    },
                                    onDragColumn: function(start_event) {
                                        var self = this,
                                            presentation_column = self.column;

                                        if (presentation_column.states.width_locked) { return false; }

                                        var column = that.columns[presentation_column.column_type],
                                            min_width = (column.min_width > 0 ? column.min_width : 50);

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
                                            presentation_column.width = new_width;
                                        }
                                    },
                                    saveColumnWidth: function() {
                                        var self = this,
                                            presentation_column = self.column;

                                        clearTimeout(presentation_column.states.width_timer);
                                        presentation_column.states.width_timer = setTimeout( function() {
                                            request();
                                        }, 100);

                                        function request() {
                                            if (presentation_column.states.locked) { return false; }

                                            presentation_column.states.locked = true;

                                            that.broadcast.getPresentationsIds().done( function(ids) {
                                                var data = {
                                                    presentation_id   : that.presentation.id,
                                                    open_presentations: ids,
                                                    column_id         : presentation_column.id,
                                                    width             : presentation_column.width
                                                };

                                                $.post(that.urls["presentation_edit_settings"], data, "json")
                                                    .always(function () {
                                                        that.presentation.states.is_changed = true;
                                                        presentation_column.states.locked   = false;
                                                    })
                                                    .done( function(response) {
                                                        if (response.status === "ok" && response.data.new_presentation_id) {
                                                            that.reload({
                                                                presentation: response.data.new_presentation_id
                                                            });
                                                        }
                                                    });
                                            });
                                        }
                                    }
                                }
                            },
                            "component-products-table-field": {
                                props: {
                                    "column": null,
                                    "product": null,
                                    "sku": null,
                                    "sku_mod": null
                                },
                                data: function() {
                                    let self = this;

                                    const is_product_col = !self.sku,
                                          is_sku_col     = (self.sku && !self.sku_mod),
                                          is_sku_mod_col = (self.sku && self.sku_mod),
                                          is_feature_col = (self.column.column_type.indexOf('feature_') >= 0),
                                          is_stock_col = (self.column.column_type.indexOf('stocks_') >= 0);

                                    const is_normal_mode = self.product.normal_mode,
                                          is_extended = (that.presentation.view === "table_extended");

                                    var column_info = (that.columns[self.column.column_type] ? that.columns[self.column.column_type] : null),
                                        column_data = getColumnData();

                                    return {
                                        column_info: column_info,
                                        column_data: column_data,
                                        states: {
                                            display: showColumn(column_data),
                                            is_locked: false,

                                            is_extended   : is_extended,
                                            is_product_col: is_product_col,
                                            is_sku_col    : is_sku_col,
                                            is_sku_mod_col: is_sku_mod_col,
                                            is_hovered    : false
                                        },
                                        errors: {}
                                    };

                                    function showColumn(column_data) {
                                        var result = true;

                                        var product_columns = [
                                            "image_crop_small", "image_count",

                                            "id", "name", "name", "summary", "meta_title", "meta_keywords",
                                            "meta_description", "description", "create_datetime", "edit_datetime",
                                            "status", "type_id", "image_id", "video_url", "sku_id", "url",
                                            "rating", "currency", "tax_id",
                                            "order_multiplicity_factor", "stock_unit_id", "base_unit_id", "stock_base_ratio",
                                            "order_count_min", "order_count_step", "rating_count", "total_sales",
                                            "category_id", "badge", "sku_type", "sku_count",

                                            "tags", "sets", "categories", "params",

                                            "sales_30days", "stock_worth"
                                        ];

                                        var sku_columns = [
                                            "sku", "name", "image_id", "price", "purchase_price", "compare_price", "base_price", "visibility",
                                            "count", "stock_base_ratio", "order_count_min", "order_count_step"
                                        ];

                                        // table_extended
                                        if (is_extended) {
                                            // ячейка продукта
                                            if (is_product_col) {
                                                if (is_normal_mode) {
                                                    // var white_list = ["image_crop_small", "name", "status", "badge", "currency"];
                                                    // result = (white_list.indexOf(self.column.column_type) >= 0 || is_feature_col);
                                                    result = (product_columns.indexOf(self.column.column_type) >= 0 || is_feature_col);
                                                }
                                            // ячейка артикула
                                            } else if (is_sku_col) {
                                                result = (self.column.column_type === 'name');

                                            // ячейка модификации
                                            } else if (is_sku_mod_col) {
                                                // var black_list = ["image_crop_small", "status", "badge", "currency"];
                                                // result = (black_list.indexOf(self.column.column_type) < 0);
                                                result = (sku_columns.indexOf(self.column.column_type) >= 0 || is_feature_col || is_stock_col);
                                            }
                                        // table
                                        } else {

                                        }

                                        // Запрещаем показ и редактирование характеристики если её нет в типе товаров.
                                        if (result) {
                                            switch (column_data.can_be_edited) {
                                                case "no":
                                                    result = false;
                                                    break;

                                                case "partial":
                                                    result = false;

                                                    switch (column_data.render_type) {
                                                        case "field":
                                                        case "field.date":
                                                        case "color":
                                                        case "range":
                                                        case "range.date":
                                                            $.each(column_data.options, function(i, option) {
                                                                if (option.value) { result = true; }
                                                            });
                                                            break;

                                                        case "select":
                                                        case "textarea":
                                                        case "checkbox":
                                                            if (column_data.value.length) { result = true; }
                                                            break;
                                                    }
                                                    break;
                                            }
                                        }

                                        return result;
                                    }

                                    function getColumnData() {
                                        var column_data = null;

                                        if (self.product.columns[self.column.column_type]) {
                                            // return self.product.columns[self.column.column_type];
                                            column_data = $.wa.clone(self.product.columns[self.column.column_type]);
                                            var name = "["+self.product.id+"]["+self.column.column_type+"]";
                                            if (self.sku_mod && column_data.skus) {
                                                var sku_mod_data = column_data.skus[self.sku_mod.id];
                                                if (sku_mod_data) {
                                                    // Если это характеристика
                                                    if (self.column.column_type.indexOf('feature_') >= 0) {
                                                        $.each(["value", "options", "active_unit", "active_option", "can_be_edited"], function(i, key) {
                                                            if (typeof sku_mod_data[key] !== "undefined") {
                                                                column_data[key] = sku_mod_data[key];
                                                            }
                                                        });

                                                    // Всё остальное
                                                    } else {
                                                        column_data.value = sku_mod_data.value;
                                                    }
                                                    name = "["+self.product.id+"]["+self.sku_mod.id+"]["+self.column.column_type+"]";
                                                    delete column_data.skus;
                                                } else {
                                                    console.error("ERROR: sku_mod_data isn't found");
                                                }
                                            }

                                            that.model_data[name] = column_data;
                                        }

                                        return column_data;
                                    }
                                },
                                template: that.components["component-products-table-field"],
                                delimiters: ['{ { ', ' } }'],
                                watch: {
                                    'states.is_hovered': function(val) {
                                        this.product.states.image_hovered = val;
                                    }
                                },
                                computed: {
                                    product_photo: function() {
                                        var self = this,
                                            result = null;

                                        if (self.product.photos.length) {
                                            result = self.product.photos[0];
                                            $.each(self.product.photos, function(i, photo) {
                                                if (self.product.image_id === photo.id) {
                                                    result = photo;
                                                    return false;
                                                }
                                            });
                                        }

                                        return result;
                                    },
                                    align_right: function() {
                                        var self = this,
                                            align_right_array = ['price', 'compare_price', 'purchase_price', 'total_sales', 'stock_worth', 'sales_30days', 'rating_count', 'sku_count', 'image_count'];
                                        return (align_right_array.indexOf(self.column.column_type) >= 0);
                                    },
                                    product_url: function() {
                                        return "products/" + this.product.id + "/?presentation=" + that.presentation.id;
                                    },
                                },
                                components: {
                                    "component-input": that.vue_components["component-input"],
                                    "component-textarea": that.vue_components["component-textarea"],
                                    "component-product-column-tags": that.vue_components["component-product-column-tags"],
                                    "component-product-column-name": {
                                        props: ["product", "column", "column_info", "column_data", "sku", "sku_mod"],
                                        data: function() {
                                            var self = this;

                                            const is_product_col = !self.sku,
                                                  is_sku_col     = (self.sku && !self.sku_mod),
                                                  is_sku_mod_col = (self.sku && self.sku_mod),
                                                  is_feature_col = (self.column.column_type.indexOf('feature_') >= 0);

                                            const is_normal_mode = self.product.normal_mode,
                                                  is_extended = (that.presentation.view === "table_extended");

                                            return {
                                                states: {
                                                    edit_key: 0,
                                                    is_edit: false,
                                                    is_locked: false,
                                                    name_on_focus: self.column_data.value,

                                                    is_extended   : is_extended,
                                                    is_product_col: is_product_col,
                                                    is_sku_col    : is_sku_col,
                                                    is_sku_mod_col: is_sku_mod_col
                                                }
                                            };
                                        },
                                        template: that.components["component-product-column-name"],
                                        delimiters: ['{ { ', ' } }'],
                                        components: {
                                            "component-textarea": that.vue_components["component-textarea"]
                                        },
                                        computed: {
                                            product_url: function() {
                                                return this.$parent.product_url;
                                            },
                                            sku_mod_photo: function() {
                                                var self = this,
                                                    result = null;

                                                var presentation_columns = $.wa.construct(that.presentation.columns, "column_type"),
                                                    display_photo = !!presentation_columns["image_crop_small"];

                                                if (display_photo) {
                                                    if (self.product.photos.length) {
                                                        var photos = $.wa.construct(self.product.photos, "id");
                                                        if (photos[self.sku_mod.image_id]) {
                                                            result = photos[self.sku_mod.image_id];
                                                        // } else if (photos[self.product.image_id]) {
                                                        //     result = photos[self.product.image_id];
                                                        } else {
                                                            result = {};
                                                        }
                                                    } else {
                                                        result = {};
                                                    }
                                                }

                                                return result;
                                            },
                                            name_class: function() {
                                                let result = [];

                                                var name_column = that.columns["name"],
                                                    settings = name_column.settings;

                                                if (settings) {
                                                    var name_format = (settings.long_name_format ? settings.long_name_format : "");
                                                    if (name_format === "hide_end") {
                                                        result.push("one-line");
                                                    }
                                                }

                                                return result.join(" ");
                                            },
                                            image_hovered: function() {
                                                return this.product.states.image_hovered;
                                            }
                                        },
                                        methods: {
                                            toggle: function(edit) {
                                                var self = this,
                                                    is_edit = (typeof edit === "boolean" ? edit : !self.states.is_edit);

                                                if (is_edit) { self.edit_key += 1; }

                                                self.states.is_edit = is_edit;
                                            },

                                            onFocus: function() {
                                                const self = this;
                                                self.states.name_on_focus = self.column_data.value;
                                            },
                                            onCancel: function() {
                                                var self = this;
                                                self.toggle(false);
                                            },
                                            onChange: function(value) {
                                                var self = this;

                                                if (typeof value === "undefined") {
                                                    console.error("ERROR: value isn't exist");
                                                    return false;

                                                } else if ($.trim(value) === "") {
                                                    self.column_data.value = self.states.name_on_focus;
                                                    self.toggle(false);
                                                    return false;
                                                }

                                                self.toggle(false);
                                                self.states.is_locked = true;

                                                var data = {
                                                    presentation_id: that.presentation.id,
                                                    product_id: self.product.id,
                                                    field_id: self.column.column_type,
                                                    value: value
                                                };

                                                $.post(that.urls["product_update"], data, "json")
                                                    .always( function() {
                                                        self.states.is_locked = false;
                                                    })
                                                    .done( function(response) {
                                                        if (response.status !== "ok") {
                                                            var error_search = response.errors.filter( error => (error.id === "not_found"));
                                                            if (error_search.length) {
                                                                $.wa_shop_products.showDeletedProductDialog();
                                                            } else {
                                                                console.error("ERROR", response.errors);
                                                            }
                                                        }
                                                    });
                                            }
                                        }
                                    },
                                    "component-product-column-summary": {
                                        props: ["product", "column", "column_info", "column_data"],
                                        data: function() {
                                            return {
                                                states: {
                                                    is_locked: false
                                                }
                                            };
                                        },
                                        template: that.components["component-product-column-summary"],
                                        delimiters: ['{ { ', ' } }'],
                                        components: {
                                            "component-textarea": that.vue_components["component-textarea"]
                                        },
                                        methods: {
                                            onChange: function(value) {
                                                var self = this;

                                                if (self.states.is_locked) { return false; }

                                                var deferred = $.Deferred();

                                                self.states.is_locked = true;

                                                var data = {
                                                    presentation_id: that.presentation.id,
                                                    product_id: self.product.id,
                                                    field_id: self.column.column_type,
                                                    value: value
                                                };

                                                $.post(that.urls["product_update"], data, "json")
                                                    .always( function() {
                                                        self.states.is_locked = false;
                                                    })
                                                    .fail( function() {
                                                        deferred.reject();
                                                    })
                                                    .done( function(response) {
                                                        if (response.status === "ok") {
                                                            deferred.resolve(response);
                                                        } else {
                                                            var error_search = response.errors.filter( error => (error.id === "not_found"));
                                                            if (error_search.length) {
                                                                $.wa_shop_products.showDeletedProductDialog();
                                                            } else {
                                                                deferred.reject(response.errors);
                                                                console.error("ERROR", response.errors);
                                                            }
                                                        }
                                                    });

                                                return deferred.promise();
                                            },
                                            showFullContent: function() {
                                                var self = this;

                                                $.waDialog({
                                                    html: that.templates["dialog-product-column-summary"],
                                                    onOpen: initDialog
                                                });

                                                function initDialog($dialog, dialog) {
                                                    console.log( dialog );

                                                    var $section = $dialog.find(".js-vue-section");

                                                    Vue.createApp({
                                                        data() {
                                                            return {
                                                                product: self.product,
                                                                column: self.column,
                                                                column_data: $.wa.clone(self.column_data),
                                                                states: {
                                                                    is_locked: false,
                                                                    is_changed: false
                                                                }
                                                            }
                                                        },
                                                        delimiters: ['{ { ', ' } }'],
                                                        components: {
                                                            "component-textarea": that.vue_components["component-textarea"]
                                                        },
                                                        methods: {
                                                            onInput: function(value) {
                                                                var dialog_self = this;
                                                                dialog_self.states.is_changed = true;
                                                            },
                                                            onSave: function() {
                                                                var dialog_self = this;
                                                                dialog_self.states.is_locked = true;
                                                                self.onChange(dialog_self.column_data.value)
                                                                    .always( function() {
                                                                        dialog_self.states.is_locked = false;
                                                                    })
                                                                    .done( function() {
                                                                        self.column_data.value = dialog_self.column_data.value;
                                                                        dialog.close();
                                                                    });
                                                            }
                                                        },
                                                        created: function () {
                                                            $section.css("visibility", "");
                                                        },
                                                        mounted: function () {
                                                            dialog.resize();
                                                        }
                                                    }).mount($section[0]);
                                                }
                                            }
                                        }
                                    },
                                    "component-product-column-description": {
                                        props: ["product", "column", "column_info", "column_data"],
                                        data: function() {
                                            return {
                                                states: {
                                                    is_locked: false
                                                }
                                            };
                                        },
                                        template: that.components["component-product-column-description"],
                                        delimiters: ['{ { ', ' } }'],
                                        computed: {},
                                        methods: {
                                            showFullContent: function() {
                                                var self = this;

                                                $.waDialog({
                                                    html: that.templates["dialog-product-column-description"],
                                                    onOpen: initDialog
                                                });

                                                function initDialog($dialog, dialog) {
                                                    console.log( dialog );

                                                    var $section = $dialog.find(".js-vue-section");

                                                    Vue.createApp({
                                                        data() {
                                                            return {
                                                                product: self.product,
                                                                column: self.column,
                                                                column_data: $.wa.clone(self.column_data),
                                                                states: {}
                                                            }
                                                        },
                                                        delimiters: ['{ { ', ' } }'],
                                                        methods: {
                                                        },
                                                        created: function () {
                                                            $section.css("visibility", "");
                                                        },
                                                        mounted: function () {
                                                            dialog.resize();
                                                        }
                                                    }).mount($section[0]);
                                                }
                                            }
                                        }
                                    },
                                    "component-product-column-status": {
                                        props: ["product", "column", "column_info", "column_data"],
                                        data: function() {
                                            return {
                                                states: { is_locked: false }
                                            };
                                        },
                                        template: that.components["component-product-column-status"],
                                        components: {
                                            "component-dropdown": that.vue_components["component-dropdown"]
                                        },
                                        computed: {
                                            statuses: function() {
                                                var self = this,
                                                    column = that.columns[self.column["column_type"]];
                                                return column.options["statuses"];
                                            },
                                            types: function() {
                                                var self = this,
                                                    column = that.columns[self.column["column_type"]];
                                                return column.options["types"];
                                            },
                                            codes: function() {
                                                var self = this,
                                                    column = that.columns[self.column["column_type"]];
                                                return column.options["codes"];
                                            },
                                            type: function() {
                                                var self = this;

                                                var search = self.types.filter( function(type) {
                                                    return (type.value === self.column_data.redirect.type);
                                                });

                                                return (search.length ? search[0] : null);
                                            }
                                        },
                                        delimiters: ['{ { ', ' } }'],
                                        methods: {
                                            onChange: function() {
                                                var self = this;

                                                if (self.states.is_locked) { return false; }

                                                var deferred = $.Deferred();

                                                self.states.is_locked = true;

                                                var data = {
                                                    presentation_id: that.presentation.id,
                                                    field_id: self.column.column_type,
                                                    product_id: self.product.id,
                                                    product_data: {
                                                        status: self.column_data.value
                                                    }
                                                };

                                                if (self.column_data.value === "-1") {
                                                    data.product_data.params = {
                                                        redirect_type: self.column_data.redirect.type,
                                                        redirect_code: self.column_data.redirect.code,
                                                        redirect_url : self.column_data.redirect.url
                                                    }
                                                }

                                                $.post(that.urls["product_status"], data, "json")
                                                    .always( function() {
                                                        self.states.is_locked = false;
                                                    })
                                                    .fail( function() {
                                                        deferred.reject();
                                                    })
                                                    .done( function(response) {
                                                        if (response.status === "ok") {
                                                            deferred.resolve(response);
                                                        } else {
                                                            deferred.reject(response.errors);
                                                            console.error("ERROR", response.errors);
                                                        }
                                                    });

                                                return deferred.promise();
                                            },
                                            showDialog: function() {
                                                var self = this;

                                                $.waDialog({
                                                    html: that.templates["dialog-product-column-status"],
                                                    onOpen: initDialog
                                                });

                                                function initDialog($dialog, dialog) {
                                                    console.log( dialog );

                                                    var $section = $dialog.find(".js-vue-section");

                                                    Vue.createApp({
                                                        data() {
                                                            return {
                                                                product: self.product,
                                                                column: self.column,
                                                                column_data: $.wa.clone(self.column_data),
                                                                types: self.types,
                                                                codes: self.codes,
                                                                states: {
                                                                    is_locked: false,
                                                                    is_changed: false,
                                                                    show_errors: false
                                                                }
                                                            }
                                                        },
                                                        components: {
                                                            "component-dropdown": that.vue_components["component-dropdown"],
                                                            "component-radio": that.vue_components["component-radio"]
                                                        },
                                                        delimiters: ['{ { ', ' } }'],
                                                        computed: {
                                                            errors: function() {
                                                                var self = this,
                                                                    errors = {};

                                                                checkURL(errors);

                                                                return errors;

                                                                function checkURL(errors) {
                                                                    var value = $.trim(self.column_data.redirect.url),
                                                                        is_valid = $.wa.isValid("url_absolute", value),
                                                                        error_class = "url_error";

                                                                    if (self.column_data.redirect.type === 'url') {
                                                                        if (!value.length) {
                                                                            errors[error_class] = {
                                                                                "id": error_class,
                                                                                "text": that.locales["error_url_required"]
                                                                            };
                                                                        } else if (!is_valid) {
                                                                            errors[error_class] = {
                                                                                "id": error_class,
                                                                                "text": that.locales["error_url_incorrect"]
                                                                            };
                                                                        }
                                                                    }
                                                                }
                                                            }
                                                        },
                                                        methods: {
                                                            onSave: function() {
                                                                var dialog_self = this;

                                                                // Проверка
                                                                if (dialog_self.states.is_locked) { return false; }

                                                                // Проверка на ошибки
                                                                if (Object.keys(dialog_self.errors).length) {
                                                                    dialog_self.states.show_errors = true;
                                                                    return false;
                                                                }

                                                                self.column_data.redirect.type = dialog_self.column_data.redirect.type;
                                                                self.column_data.redirect.code = dialog_self.column_data.redirect.code;
                                                                self.column_data.redirect.url = dialog_self.column_data.redirect.url;
                                                                dialog_self.states.is_locked = true;

                                                                self.onChange()
                                                                    .always( function() {
                                                                        dialog_self.states.is_locked = false;
                                                                    })
                                                                    .fail( function(errors) {
                                                                        alert("ERROR");
                                                                        console.log(errors);
                                                                    })
                                                                    .done( function() {
                                                                        dialog.close();
                                                                    });
                                                            }
                                                        },
                                                        watch: {
                                                            "column_data.redirect.type": function() {
                                                                var self = this;
                                                                self.states.is_changed = true;
                                                                self.$nextTick( function() {
                                                                    dialog.resize();
                                                                });
                                                            },
                                                            "column_data.redirect.code": function() {
                                                                var self = this;
                                                                self.states.is_changed = true;
                                                                self.$nextTick( function() {
                                                                    dialog.resize();
                                                                });
                                                            },
                                                            "column_data.redirect.url": function() {
                                                                var self = this;
                                                                self.states.is_changed = true;
                                                            }
                                                        },
                                                        created: function () {
                                                            $section.css("visibility", "");
                                                        },
                                                        mounted: function () {
                                                            dialog.resize();
                                                        }
                                                    }).mount($section[0]);
                                                }
                                            }
                                        }
                                    },
                                    "component-product-column-price": {
                                        props: ["product", "sku_mod", "column", "column_info", "column_data"],
                                        data: function() {
                                            var self = this;

                                            if (!self.column_data.value) {
                                                self.column_data.value = "0";
                                            }

                                            return {
                                                format: getFormat(),
                                                errors: {},
                                                states: {
                                                    is_edit: false,
                                                    is_locked: false,
                                                }
                                            };

                                            function getFormat() {
                                                var result = null;

                                                if (self.column.data && self.column.data.settings && Object.keys(self.column.data.settings).length) {
                                                    var settings = self.column.data.settings;
                                                    if (settings.format) {
                                                        switch (settings.format) {
                                                            case "int":
                                                            case "float":
                                                            case "origin":
                                                                result = settings.format;
                                                                break;
                                                        }
                                                    }
                                                }

                                                return result;
                                            }
                                        },
                                        template: that.components["component-product-column-price"],
                                        delimiters: ['{ { ', ' } }'],
                                        computed: {
                                            show_readonly: function() {
                                                var self = this;
                                                if (this.$el && (self.states.is_edit)) {
                                                    this.$nextTick(() => {
                                                        this.$el.querySelector('input').focus()
                                                    })
                                                }

                                                return (self.format && !self.states.is_edit);
                                            },
                                            formatted_value: function() {
                                                var self = this,
                                                    value = self.column_data.value;

                                                if (self.format) {
                                                    value = parseFloat(value);
                                                    if (Math.abs(value) >= 0) {
                                                        switch (self.format) {
                                                            case "int":
                                                                value = $.wa.price.format(value, { html: false, only_number: true, fraction_size: 0 })
                                                                break;
                                                            case "float":
                                                                value = $.wa.price.format(value, { html: false, only_number: true, fraction_size: 2 });
                                                                break;
                                                            case "origin":
                                                                value = $.wa.price.format(value, { html: false, only_number: true, fraction_size: null });
                                                                break;
                                                        }
                                                    } else {
                                                        value = "";
                                                    }
                                                }

                                                return value;
                                            },
                                            show_price_range: function() {
                                                let self = this,
                                                    result = false;

                                                if (that.presentation.view === "table" && self.product.normal_mode && !self.sku_mod) {
                                                    result = true;
                                                }

                                                return result;
                                            },
                                            price_range: function() {
                                                var self = this,
                                                    result = "&nbsp;",
                                                    prices = [],
                                                    price_strings = {};

                                                var price_key = "price";
                                                switch (self.column.column_type) {
                                                    case "price":
                                                    case "compare_price":
                                                    case "purchase_price":
                                                        price_key = self.column.column_type;
                                                        break;
                                                }

                                                $.each(self.product.skus, function(i, sku) {
                                                    $.each(sku.modifications, function(i, sku_mod) {
                                                        var price = parseFloat(sku_mod[price_key]);
                                                        prices.push(price);
                                                        price_strings[price] = sku_mod.price_string;
                                                    });
                                                });

                                                var min = Math.min.apply(null, prices),
                                                    max = Math.max.apply(null, prices);

                                                var fraction_size = getFractionSize();

                                                if (min >= 0 && max >= 0) {
                                                    if (min === max) {
                                                        if (max > 0) {
                                                            result = $.wa.price.format(max, { currency: self.product.currency, fraction_size: fraction_size });
                                                        }
                                                    } else {
                                                        var min_value = $.wa.price.format(min, { currency: self.product.currency, only_number: true, fraction_size: fraction_size }),
                                                            max_value = $.wa.price.format(max, { currency: self.product.currency, fraction_size: fraction_size });
                                                        result = "<span class='price-min nowrap'>" + min_value + "</span>" + " ... " + "<span class='price-max nowrap'>" + max_value + "</span>";
                                                    }
                                                }

                                                return result;

                                                function getFractionSize() {
                                                    var result = null;

                                                    if (self.format) {
                                                        switch (self.format) {
                                                            case "int":
                                                                result = 0;
                                                                break;
                                                            case "float":
                                                                result = 2;
                                                                break;
                                                            case "origin":
                                                                result = null;
                                                                break;
                                                        }
                                                    }

                                                    return result;
                                                }
                                            }
                                        },
                                        methods: {
                                            onChange: function() {
                                                var self = this;

                                                if (self.states.is_locked) {
                                                    return false;
                                                } else if (Object.keys(self.errors).length > 0) {
                                                    console.error("Validate errors:", self.errors);
                                                    return false;
                                                }

                                                self.states.is_locked = true;

                                                var sku_id = (self.sku_mod ? self.sku_mod.id : self.product.sku_id);

                                                if (!self.column_data.value) {
                                                    self.column_data.value = 0;
                                                } else {
                                                    self.column_data.value = $.wa.validate("number", self.column_data.value, {
                                                        remove_start_nulls: true
                                                    });
                                                }

                                                var data = {
                                                    presentation_id: that.presentation.id,
                                                    product_id: self.product.id,
                                                    sku_id: sku_id,
                                                    field_id: self.column.column_type,
                                                    value: self.column_data.value
                                                };

                                                $.post(that.urls["product_update"], data, "json")
                                                    .always( function() {
                                                        self.states.is_edit = false;
                                                        self.states.is_locked = false;
                                                    })
                                                    .done( function(response) {
                                                        if (response.status !== "ok") {
                                                            var error_search = response.errors.filter( error => (error.id === "not_found"));
                                                            if (error_search.length) {
                                                                $.wa_shop_products.showDeletedProductDialog();
                                                            } else {
                                                                console.error("ERROR", response.errors);
                                                            }
                                                        } else {
                                                            self.column_data.value = response.data.value;
                                                        }
                                                    });
                                            },
                                            validate: function(value, type, data, key) {
                                                var self = this;

                                                value = (typeof value === "string" ? value : "" + value);

                                                switch (type) {
                                                    case "price":
                                                        value = $.wa.validate("number", value);

                                                        var limit_body = 11,
                                                            limit_tail = 4,
                                                            parts = value.replace(",", ".").split(".");

                                                        var error_key = "price_error";

                                                        if (parts[0].length > limit_body || (parts[1] && parts[1].length > limit_tail)) {
                                                            self.errors[error_key] = {
                                                                id: error_key,
                                                                text: "price_error"
                                                            };
                                                        } else {
                                                            if (self.errors[error_key]) {
                                                                delete self.errors[error_key];
                                                            }
                                                        }

                                                        break;
                                                    default:
                                                        value = $.wa.validate(type, value);
                                                        break;
                                                }

                                                // set
                                                data[key] = value;
                                            },
                                            edit: function(show) {
                                                var self = this;
                                                show = (typeof show === "boolean" ? show : true);
                                                if (!Object.keys(self.errors).length) {
                                                    self.states.is_edit = show;
                                                }
                                            }
                                        }
                                    },
                                    "component-product-column-url": {
                                        props: ["product", "column", "column_info", "column_data"],
                                        data: function() {
                                            return {
                                                errors: {},
                                                states: {
                                                    is_locked: false
                                                }
                                            };
                                        },
                                        template: that.components["component-product-column-url"],
                                        delimiters: ['{ { ', ' } }'],
                                        methods: {
                                            onInput: function() {
                                                var self = this;
                                                if (self.errors["url_error"]) { delete self.errors["url_error"]; }

                                                var forbidden_symbols = new RegExp("[/|#|\\\\|?]");
                                                self.column_data.value = self.column_data.value.replace(forbidden_symbols, "");
                                            },
                                            onChange: function() {
                                                var self = this;

                                                if (self.states.is_locked) {
                                                    return false;
                                                } else if (Object.keys(self.errors).length > 0) {
                                                    console.error("Validate errors:", self.errors);
                                                    return false;
                                                }

                                                self.states.is_locked = true;

                                                checkUnique(self.column_data.value)
                                                    .fail( function(html) {
                                                        self.states.is_locked = false;

                                                        if (html) {
                                                            self.errors["url_error"] = {
                                                                id: "url_error",
                                                                text: html
                                                            };
                                                        }
                                                    })
                                                    .done( function() {
                                                        save()
                                                            .always( function() {
                                                                self.states.is_locked = false;
                                                            });
                                                    });

                                                function checkUnique(value) {
                                                    var deferred = $.Deferred();

                                                    var data = { id: self.product.id, url: value }

                                                    $.post(that.urls["product_url_checker"], data, "json")
                                                        .done( function(response) {
                                                            if (response.status === "ok") {
                                                                if (response.data.url_in_use.length) {
                                                                    deferred.reject(response.data.url_in_use);
                                                                } else {
                                                                    deferred.resolve();
                                                                }
                                                            } else {
                                                                deferred.reject(null);
                                                            }
                                                        })
                                                        .fail( function() {
                                                            deferred.reject(null);
                                                        });

                                                    return deferred.promise();
                                                }

                                                function save() {
                                                    var data = {
                                                        presentation_id: that.presentation.id,
                                                        product_id: self.product.id,
                                                        field_id: self.column.column_type,
                                                        value: self.column_data.value
                                                    };

                                                    return $.post(that.urls["product_update"], data, "json")
                                                        .done( function(response) {
                                                            if (response.status !== "ok") {
                                                                var error_search = response.errors.filter( error => (error.id === "not_found"));
                                                                if (error_search.length) {
                                                                    $.wa_shop_products.showDeletedProductDialog();
                                                                } else {
                                                                    console.error("ERROR", response.errors);
                                                                }
                                                            }
                                                        });
                                                }
                                            }
                                        }
                                    },
                                    "component-product-column-visibility": {
                                        props: ["column", "column_info", "column_data", "product", "sku_mod"],
                                        data: function() {
                                            var self = this;

                                            let editable = false;
                                            if (that.presentation.view === "table_extended" || !self.product.normal_mode) {
                                                editable = true;
                                            }

                                            return {
                                                editable: editable,
                                                states: {
                                                    sku_locked: false,
                                                    available_locked: false,
                                                    visibility_locked: false
                                                },
                                                errors: []
                                            };
                                        },
                                        template: that.components["component-product-column-visibility"],
                                        delimiters: ['{ { ', ' } }'],
                                        computed: {
                                            active_sku: function() {
                                                var self = this,
                                                    result = null;

                                                $.each(self.product.skus, function(i, sku) {
                                                    $.each(sku.modifications, function(i, sku_mod) {
                                                        if (sku_mod.id === self.product.sku_id) {
                                                            result = sku_mod;
                                                            return false;
                                                        }
                                                    });
                                                    if (result) { return false; }
                                                });

                                                return result;
                                            },
                                            is_main_sku: function() {
                                                var self = this,
                                                    result = false;

                                                if (self.editable) {
                                                    if (self.sku_mod) {
                                                        if (self.sku_mod.id === self.product.sku_id) {
                                                            result = true;
                                                        }
                                                    }
                                                }

                                                return result;
                                            },
                                            visibility_state: function() {
                                                var self = this,
                                                    result = "disabled";

                                                if (self.editable && self.sku_mod) {
                                                    result = (self.sku_mod.status === "1" ? "enabled" : "disabled");
                                                }

                                                return result;
                                            },
                                            available_state: function() {
                                                var self = this,
                                                    result = "part";

                                                var sku_mod = (self.sku_mod ? self.sku_mod : null);
                                                if (!self.product.normal_mode) {
                                                    sku_mod = self.product.skus[0].modifications[0];
                                                }

                                                if (self.editable && sku_mod) {
                                                    result = (sku_mod.available === "1" ? "all" : "none");
                                                }

                                                return result;
                                            }
                                        },
                                        methods: {
                                            onChangeSku: function() {
                                                var self = this;

                                                if (!self.sku_mod || !self.editable || self.states.sku_locked) { return false; }

                                                // data
                                                var data = {
                                                    presentation_id: that.presentation.id,
                                                    product_id: self.product.id,
                                                    sku_id: null,
                                                    field_id: "sku_id",
                                                    value: self.sku_mod.id
                                                };

                                                self.states.sku_locked = true;

                                                self.save(data)
                                                    .always( function() {
                                                        self.states.sku_locked = false;
                                                    })
                                                    .done( function() {
                                                        self.product.sku_id = self.sku_mod.id;
                                                    });
                                            },
                                            onChangeVisibility: function() {
                                                var self = this;

                                                if (!self.editable || self.states.visibility_locked) { return false; }

                                                var sku_mod = (self.sku_mod ? self.sku_mod : self.getFirstSkuMod());

                                                var status = (sku_mod.status === "1" ? "0" : "1");

                                                // data
                                                var data = {
                                                    presentation_id: that.presentation.id,
                                                    product_id: self.product.id,
                                                    sku_id: sku_mod.id,
                                                    field_id: "status",
                                                    value: status
                                                };

                                                self.states.visibility_locked = true;

                                                self.save(data)
                                                    .always( function() {
                                                        self.states.visibility_locked = false;
                                                    })
                                                    .done( function() {
                                                        sku_mod.status = status;
                                                    });
                                            },
                                            onChangeAvailable: function() {
                                                var self = this;

                                                if (!self.editable || self.states.available_locked) { return false; }

                                                var sku_mod = (self.sku_mod ? self.sku_mod : self.getFirstSkuMod());

                                                // change value
                                                var available = (sku_mod.available === "1" ? "0" : "1");

                                                // data
                                                var data = {
                                                    presentation_id: that.presentation.id,
                                                    product_id: self.product.id,
                                                    sku_id: sku_mod.id,
                                                    field_id: "available",
                                                    value: available
                                                };

                                                self.states.available_locked = true;

                                                self.save(data)
                                                    .always( function() {
                                                        self.states.available_locked = false;
                                                    })
                                                    .done( function() {
                                                        sku_mod.available = available;
                                                    });
                                            },
                                            getFirstSkuMod: function() {
                                                var self = this;
                                                return self.product.skus[0]["modifications"][0];
                                            },
                                            save: function(data) {
                                                var self = this;

                                                self.errors.splice(0);

                                                var deferred = $.Deferred();

                                                $.post(that.urls["product_update"], data, "json")
                                                    .fail( function() {
                                                        deferred.reject();
                                                    })
                                                    .done( function(response) {
                                                        if (response.status === "ok") {
                                                            deferred.resolve(response);
                                                        } else {
                                                            deferred.reject(response.errors);

                                                            var error_search = response.errors.filter( error => (error.id === "not_found"));
                                                            if (error_search.length) {
                                                                $.wa_shop_products.showDeletedProductDialog();
                                                            } else {
                                                                var html = "";

                                                                $.each(response.errors, function(i, error) {
                                                                    html += '<div class="state-error-hint">'+error.text+'</div>';
                                                                    self.errors.push(error);
                                                                });

                                                                $.waDialog({
                                                                    html: that.templates["errors_dialog"].replace("%content%", html)
                                                                });
                                                            }
                                                        }
                                                    });

                                                return deferred.promise();
                                            }
                                        }
                                    },
                                    "component-product-column-dropdown": {
                                        props: ["product", "column", "column_info", "column_data"],
                                        data: function() {
                                            var self = this;

                                            let options = (self.column_info.options || self.column_data.options);
                                            if (!options) {
                                                options = [{ name: "ERROR: Варианты отсутствуют", value: "" }];
                                            }

                                            return {
                                                options: $.wa.clone(options),
                                                states: {
                                                    is_locked: false
                                                }
                                            };
                                        },
                                        template: that.components["component-product-column-dropdown"],
                                        delimiters: ['{ { ', ' } }'],
                                        components: { "component-dropdown": that.vue_components["component-dropdown"]},
                                        watch: {
                                            "column_data.value": function(newVal, oldVal) {
                                                if (this.column.column_type === "category_id" && oldVal === null) {
                                                    this.options = this.options.filter(opt => opt.value !== null);
                                                }
                                            }
                                        },
                                        methods: {
                                            onChange: function() {
                                                var self = this;

                                                self.states.is_locked = true;

                                                var data = {
                                                    presentation_id: that.presentation.id,
                                                    product_id: self.product.id,
                                                    field_id: self.column.column_type,
                                                    value: self.column_data.value
                                                };

                                                $.post(that.urls["product_update"], data, "json")
                                                    .always( function() {
                                                        self.states.is_locked = false;
                                                    })
                                                    .done( function(response) {
                                                        if (response.status !== "ok") {
                                                            var error_search = response.errors.filter( error => (error.id === "not_found"));
                                                            if (error_search.length) {
                                                                $.wa_shop_products.showDeletedProductDialog();
                                                            } else {
                                                                console.error("ERROR", response.errors);
                                                            }
                                                        }
                                                    });
                                            }
                                        },
                                        created() {
                                            if (
                                                this.column.column_type === "category_id"
                                                && !this.product.columns.category_id.value
                                                && Array.isArray(this.options)
                                                && this.options[0].value !== null
                                            ) {
                                                this.options.unshift({ name: that.locales["select_category"], value: null });
                                            }
                                        }
                                    },
                                    "component-product-column-stock": {
                                        props: ["column", "column_info", "column_data", "product", "sku_mod"],
                                        data: function() {
                                            var self = this,
                                                has_many_stocks = !!Object.keys(self.column_info.stocks).length;

                                            let editable = self.column_data.editable;

                                            var stock = null;
                                            if (self.column_data.stock_id && self.column_info.stocks[self.column_data.stock_id]) {
                                                stock = self.column_info.stocks[self.column_data.stock_id];
                                                // Виртуальные склады редактировать нельзя
                                                if (stock.is_virtual) { editable = false; }
                                            }

                                            // В сложном режиме, в простой таблице, в продукте запрещено редактировать значения самих складов (можно в артикулах)
                                            if (that.presentation.view === "table" && self.product.normal_mode && !self.sku_mod) { editable = false; }

                                            // Запрещаем редактировать общее кол-во если много складов
                                            if (!stock && has_many_stocks) { editable = false; }

                                            var display_value = true;
                                            if (that.presentation.view === "table" && self.product.normal_mode) { display_value = false; }

                                            return {
                                                stock: stock,
                                                editable: editable,
                                                display_value: display_value,
                                                errors: {},
                                                states: {
                                                    is_preview: true,
                                                    is_locked: false
                                                }
                                            };
                                        },
                                        template: that.components["component-product-column-stock"],
                                        delimiters: ['{ { ', ' } }'],
                                        components: { "component-input": that.vue_components["component-input"] },
                                        computed: {
                                            icon_class: function() {
                                                var self = this,
                                                    class_name = "color-green-dark",
                                                    value = parseFloat(self.column_data.value);

                                                if (!isNaN(value)) {
                                                    if (self.stock) {
                                                        var low = parseFloat(self.stock.low_count),
                                                            crit = parseFloat(self.stock.critical_count);

                                                        if (value > low) {
                                                            class_name = "color-green-dark";
                                                        } else
                                                        if (value > crit && value <= low) {
                                                            class_name = "color-orange";
                                                        } else
                                                        if (value <= crit) {
                                                            class_name = "color-red";
                                                        }
                                                    } else if (value > 0) {
                                                        class_name = "color-green-dark";
                                                    } else {
                                                        class_name = "color-red";
                                                    }
                                                }

                                                return class_name;
                                            },
                                            formatted_value: function() {
                                                var self = this,
                                                    value = self.column_data.value;

                                                if (Math.abs(parseFloat(value)) >= 0) {
                                                    value = $.wa.validate("number-negative", value, {
                                                        fraction_size: 3,
                                                        group_size: 3,
                                                        delimiter: ($.wa_shop_products.lang === "ru" ? ",": null)
                                                    });
                                                } else {
                                                    value = "";
                                                }

                                                return value;
                                            }
                                        },
                                        methods: {
                                            togglePreview: function(show) {
                                                var self = this;
                                                show = (typeof show === "boolean" ? show : false);
                                                self.states.is_preview = show;
                                            },
                                            onInput: function(value) {
                                                var self = this,
                                                    error_key = "stock_error";

                                                if (Math.abs(value) >= 0) {
                                                    delete self.errors[error_key];
                                                } else {
                                                    self.errors[error_key] = {
                                                        id: error_key,
                                                        text: "price_error"
                                                    };
                                                }
                                            },
                                            onChange: function() {
                                                var self = this;

                                                if (self.states.is_locked) {
                                                    return false;
                                                } else if (Object.keys(self.errors).length > 0) {
                                                    console.error("Validate errors:", self.errors);
                                                    return false;
                                                }

                                                self.states.is_locked = true;

                                                if (self.column_data.value !== "") {
                                                    self.column_data.value = $.wa.validate("number-negative", self.column_data.value, {
                                                        remove_start_nulls: true
                                                    });
                                                }

                                                var data = {
                                                    presentation_id: that.presentation.id,
                                                    product_id: self.product.id,
                                                    sku_id: (self.sku_mod ? self.sku_mod.id : self.product.sku_id),
                                                    field_id: self.column.column_type,
                                                    value: self.column_data.value
                                                };

                                                $.post(that.urls["product_update"], data, "json")
                                                    .always( function() {
                                                        self.states.is_locked = false;
                                                        self.togglePreview(true);
                                                    })
                                                    .done( function(response) {
                                                        if (response.status !== "ok") {
                                                            var error_search = response.errors.filter( error => (error.id === "not_found"));
                                                            if (error_search.length) {
                                                                $.wa_shop_products.showDeletedProductDialog();
                                                            } else {
                                                                console.error("ERROR", response.errors);
                                                            }
                                                        } else {
                                                            self.column_data.value = response.data.value;
                                                        }
                                                    });
                                            }
                                        }
                                    },
                                    "component-product-column-badge": {
                                        props: ["product", "column", "column_info", "column_data"],
                                        data: function() {
                                            const self = this;
                                            return {
                                                value: getValue(),
                                                options: self.column_info.options,
                                                states: { is_locked: false }
                                            };

                                            function getValue() {
                                                var value = self.column_data.value;

                                                switch (self.column_data.value) {
                                                    case "":
                                                    case "new":
                                                    case "bestseller":
                                                    case "lowprice":
                                                    case "custom":
                                                        break;
                                                    default:
                                                        value = "custom";
                                                        break;
                                                }

                                                return value;
                                            }
                                        },
                                        template: that.components["component-product-column-badge"],
                                        components: {
                                            "component-dropdown": that.vue_components["component-dropdown"]
                                        },
                                        computed: {
                                        },
                                        delimiters: ['{ { ', ' } }'],
                                        methods: {
                                            onChange: function(value) {
                                                var self = this;

                                                // Корректируем значение селекта если в диалоге ввели пустоту или ID бейджа
                                                switch (value) {
                                                    case "":
                                                    case "new":
                                                    case "bestseller":
                                                    case "lowprice":
                                                        self.column_data.value = self.value = value;
                                                        break;
                                                    case "custom":
                                                        let badge = self.column_info.badges["custom"];
                                                        self.column_data.value = badge.code;
                                                        break;
                                                }

                                                if (self.states.is_locked) { return false; }

                                                var deferred = $.Deferred();

                                                self.states.is_locked = true;

                                                var data = {
                                                    presentation_id: that.presentation.id,
                                                    field_id: self.column.column_type,
                                                    product_id: self.product.id,
                                                    value: self.column_data.value
                                                };

                                                $.post(that.urls["product_update"], data, "json")
                                                    .always( function() {
                                                        self.states.is_locked = false;
                                                    })
                                                    .fail( function() {
                                                        deferred.reject();
                                                    })
                                                    .done( function(response) {
                                                        if (response.status === "ok") {
                                                            deferred.resolve(response);
                                                        } else {
                                                            var error_search = response.errors.filter( error => (error.id === "not_found"));
                                                            if (error_search.length) {
                                                                $.wa_shop_products.showDeletedProductDialog();
                                                            } else {
                                                                deferred.reject(response.errors);
                                                                console.error("ERROR", response.errors);
                                                            }
                                                        }
                                                    });

                                                return deferred.promise();
                                            },
                                            showDialog: function() {
                                                var self = this;

                                                $.waDialog({
                                                    html: that.templates["dialog-product-column-badge"],
                                                    onOpen: initDialog
                                                });

                                                function initDialog($dialog, dialog) {
                                                    var $section = $dialog.find(".js-vue-section");

                                                    var app = Vue.createApp({
                                                        data() {
                                                            return {
                                                                product: self.product,
                                                                column: self.column,
                                                                column_info: self.column_info,
                                                                column_data: $.wa.clone(self.column_data),
                                                                states: {
                                                                    is_locked: false
                                                                }
                                                            }
                                                        },
                                                        delimiters: ['{ { ', ' } }'],
                                                        components: { "component-textarea": that.vue_components["component-textarea"] },
                                                        computed: {
                                                            badge: function() {
                                                                let self = this;
                                                                return self.column_info.badges["custom"];
                                                            },
                                                            is_changed: function() {
                                                                let dialog_self = this;
                                                                return (self.column_data.value !== dialog_self.column_data.value);
                                                            }
                                                        },
                                                        methods: {
                                                            onSave: function() {
                                                                let dialog_self = this;

                                                                // Проверка
                                                                if (dialog_self.states.is_locked) { return false; }

                                                                self.column_data.value = dialog_self.column_data.value;

                                                                dialog_self.states.is_locked = true;

                                                                self.onChange(self.column_data.value)
                                                                    .always( function() {
                                                                        dialog_self.states.is_locked = false;
                                                                    })
                                                                    .fail( function(errors) {
                                                                        alert("ERROR");
                                                                        console.error(errors);
                                                                    })
                                                                    .done( function() {
                                                                        dialog.close();
                                                                    });
                                                            }
                                                        },
                                                        watch: {
                                                            "column_data.redirect.type": function() {
                                                                var self = this;
                                                                self.states.is_changed = true;
                                                                self.$nextTick( function() {
                                                                    dialog.resize();
                                                                });
                                                            },
                                                            "column_data.redirect.code": function() {
                                                                var self = this;
                                                                self.states.is_changed = true;
                                                                self.$nextTick( function() {
                                                                    dialog.resize();
                                                                });
                                                            },
                                                            "column_data.redirect.url": function() {
                                                                var self = this;
                                                                self.states.is_changed = true;
                                                            }
                                                        },
                                                        created: function () {
                                                            $section.css("visibility", "");
                                                        },
                                                        mounted: function () {
                                                            dialog.resize();
                                                        }
                                                    });

                                                    app.mount($section[0]);
                                                    dialog.onClose = () => app.unmount();
                                                }
                                            }
                                        }
                                    },
                                    "component-product-column-feature": {
                                        props: ["product", "sku_mod", "column", "column_info", "column_data"],
                                        data: function() {
                                            return {
                                                timer: 0,
                                                states: {
                                                    is_available: true,
                                                    is_timer_start: false,
                                                    is_changed: false,
                                                    is_locked: false
                                                }
                                            };
                                        },
                                        template: that.components["component-product-column-feature"],
                                        delimiters: ['{ { ', ' } }'],
                                        components: {
                                            "component-dropdown": that.vue_components["component-dropdown"],
                                            "component-textarea": that.vue_components["component-textarea"],
                                            "component-date-picker": that.vue_components["component-date-picker"],
                                            "component-product-feature-color": {
                                                props: ["data", "readonly", "disabled"],
                                                data: function() {
                                                    return {
                                                        timer: 0,
                                                        color_xhr: null,
                                                        states: {
                                                            is_locked: false
                                                        }
                                                    }
                                                },
                                                template: that.components["component-product-feature-color"],
                                                delimiters: ['{ { ', ' } }'],
                                                components: {
                                                    "component-input": that.vue_components["component-input"],
                                                    "component-color-picker": that.vue_components["component-color-picker"],
                                                },
                                                methods: {
                                                    onChangeCode: function() {
                                                        var self = this;
                                                        self.setColorInfo();
                                                        self.onChange();
                                                    },
                                                    onChangeValue: function() {
                                                        var self = this;
                                                        self.setColorInfo();
                                                        self.onChange();
                                                    },
                                                    onFocus: function() {
                                                        var self = this;
                                                        // console.log( "clear" );
                                                        clearTimeout(self.timer);
                                                    },
                                                    onChange: function() {
                                                        var self = this,
                                                            time = 500;
                                                        // console.log( "start_change" );

                                                        self.timer = setTimeout( function() {
                                                            if (self.color_xhr) {
                                                                self.states.is_locked = true;
                                                                self.color_xhr.done( function() {
                                                                    self.states.is_locked = false;
                                                                    // console.log( "change" );
                                                                    self.$emit("change");
                                                                });
                                                            } else {
                                                                // console.log( "change" );
                                                                self.$emit("change");
                                                            }
                                                        }, time);
                                                    },
                                                    setColorInfo: function() {
                                                        var self = this;

                                                        var value = self.data.value,
                                                            code = self.data.code;

                                                        if (!value || !code) {
                                                            self.getColorInfo(value, code)
                                                                .done( function(data) {
                                                                    if (!value && data.name) { self.data.value = data.name; }
                                                                    if (!code && data.color) { self.data.code = data.color; }
                                                                });
                                                        }
                                                    },
                                                    getColorInfo: function(name, color) {
                                                        var self = this;

                                                        var href = that.urls["color_transliterate"],
                                                            data = {};

                                                        if (color) { data.code = color2magic(color); }
                                                        if (name) { data.name = name; }

                                                        var deferred = $.Deferred();

                                                        if (self.color_xhr) { self.color_xhr.abort(); }

                                                        self.color_xhr = $.get(href, data, "json")
                                                            .always( function() {
                                                                self.color_xhr = null;
                                                            })
                                                            .done( function(response) {
                                                                if (response.status === "ok") {
                                                                    deferred.resolve({
                                                                        name: response.data.name,
                                                                        color: magic2color(response.data.code)
                                                                    });
                                                                } else {
                                                                    deferred.reject();
                                                                }
                                                            })
                                                            .fail( function() {
                                                                deferred.reject();
                                                            });

                                                        return deferred.promise();

                                                        function color2magic(color) {
                                                            return 0xFFFFFF & parseInt(('' + color + '000000').replace(/[^0-9A-F]+/gi, '').substr(0, 6), 16);
                                                        }

                                                        function magic2color(magic) {
                                                            return (0xF000000 | magic).toString(16).toLowerCase().replace(/^f/, "#");
                                                        }
                                                    }
                                                }
                                            },
                                            "component-product-feature-checkbox": {
                                                props: ["product", "sku_mod", "column_info", "column_data"],
                                                emits: ["change"],
                                                data: function() {
                                                    var self = this;
                                                    return {
                                                        value: self.column_data.value,
                                                        options: self.column_info.options
                                                    };
                                                },
                                                template: that.components["component-product-feature-checkbox"],
                                                delimiters: ['{ { ', ' } }'],
                                                components: {
                                                    "component-dropdown": that.vue_components["component-dropdown"]
                                                },
                                                computed: {
                                                    active_options: function() {
                                                        var self = this;
                                                        return self.options.filter( function(option) {
                                                            return (self.value.indexOf(option.value) >= 0);
                                                        });
                                                    },
                                                    sku_options: function() {
                                                        var self = this,
                                                            result = [];

                                                        if (self.column_data.skus) {
                                                            var values = [];

                                                            $.each(self.column_data.skus, function(i, sku) {
                                                                values.push(sku.value);
                                                            });

                                                            result = self.options.filter( function(option) {
                                                                return (values.indexOf(option.value) >= 0);
                                                            });
                                                        }

                                                        return result;
                                                    }
                                                },
                                                methods: {
                                                    reset: function() {
                                                        var self = this;
                                                        self.value.splice(0);
                                                        self.onChange();
                                                    },
                                                    onChange: function() {
                                                        var self = this;
                                                        self.$emit("change");
                                                    },
                                                    onSkuChange: function(value) {
                                                        var self = this;

                                                        var product_data = self.product.columns[self.column_info.id],
                                                            sku_data = product_data["skus"][self.sku_mod.id];

                                                        sku_data.value = value;
                                                        self.product.states.product_key += 1;

                                                        self.$emit("change");
                                                    },
                                                    showDialog: function() {
                                                        var self = this;

                                                        $.waDialog({
                                                            html: that.templates["dialog-product-feature-checkbox"],
                                                            options: {
                                                                onSuccess: function(changed_options) {
                                                                    self.value.splice(0);
                                                                    $.each(changed_options, function(i, option) {
                                                                        if (option.active) {
                                                                            self.value.push(option.value);
                                                                        }
                                                                    });
                                                                    self.onChange();
                                                                }
                                                            },
                                                            onOpen: initDialog
                                                        });

                                                        function initDialog($dialog, dialog) {
                                                            var $section = $dialog.find(".js-vue-section");

                                                            Vue.createApp({
                                                                data() {
                                                                    var dialog_self = this;
                                                                    var units = null;

                                                                    if (self.column_info.units && self.column_info.units.length) {
                                                                        units = self.column_info.units;
                                                                    }

                                                                    return {
                                                                        column_data: self.column_data,
                                                                        units: units,
                                                                        feature: {
                                                                            id: self.column_data.id,
                                                                            type: self.column_data.type,
                                                                            name: self.column_data.name
                                                                        },
                                                                        options: getOptions()
                                                                    };

                                                                    function getOptions() {
                                                                        var options = $.wa.clone(self.options),
                                                                            result = [];

                                                                        $.each(options, function(i, option) {
                                                                            option = dialog_self.formatOption(option);
                                                                            option.active = (self.value.indexOf(option.value) >= 0)
                                                                            result.push(option);
                                                                        });

                                                                        return result;
                                                                    }
                                                                },
                                                                delimiters: ['{ { ', ' } }'],
                                                                components: {
                                                                    "component-checkbox": that.vue_components["component-checkbox"],
                                                                    "component-feature-option-search": {
                                                                        props: ["options"],
                                                                        data: function() {
                                                                            return {
                                                                                search_string: "",
                                                                                selection: []
                                                                            };
                                                                        },
                                                                        template: that.components["component-feature-option-search"],
                                                                        delimiters: ['{ { ', ' } }'],
                                                                        methods: {
                                                                            search: function() {
                                                                                let self = this;

                                                                                let selection = [];

                                                                                // Делаем выборку
                                                                                checkItems(self.options);

                                                                                // Сохраняем выборку
                                                                                self.selection = selection;

                                                                                function checkItems(options) {
                                                                                    $.each(options, function(i, option) {
                                                                                        let display = true;
                                                                                        if (self.search_string.length > 0) {
                                                                                            let is_wanted = (option.name.toLowerCase().indexOf( self.search_string.toLowerCase() ) >= 0 );
                                                                                            if (is_wanted) {
                                                                                                selection.push(option);
                                                                                            } else {
                                                                                                display = false;
                                                                                            }
                                                                                        }
                                                                                        option.states.visible = display;
                                                                                    });
                                                                                }
                                                                            },
                                                                            revert: function() {
                                                                                let self = this;
                                                                                self.search_string = "";
                                                                                self.search();
                                                                            }
                                                                        }
                                                                    },
                                                                    "component-feature-option-form": {
                                                                        props: ["column_data", "feature", "units"],
                                                                        emits: ["add"],
                                                                        data: function() {
                                                                            var self = this;
                                                                            return {
                                                                                show_form: false,
                                                                                data: {
                                                                                    value: "",
                                                                                    code: "",
                                                                                    unit: (self.column_data.default_unit ? self.column_data.default_unit : "")
                                                                                },
                                                                                color_xhr: null,
                                                                                states: {
                                                                                    is_locked: false
                                                                                }
                                                                            }
                                                                        },
                                                                        template: that.components["component-feature-option-form"],
                                                                        delimiters: ['{ { ', ' } }'],
                                                                        components: {
                                                                            "component-input": that.vue_components["component-input"],
                                                                            "component-color-picker": that.vue_components["component-color-picker"],
                                                                            "component-dropdown": that.vue_components["component-dropdown"],
                                                                        },
                                                                        methods: {
                                                                            onChangeValue: function() {
                                                                                var self = this;
                                                                                if (self.feature.type === "color") {
                                                                                    self.setColorInfo();
                                                                                }
                                                                            },
                                                                            onChangeCode: function() {
                                                                                var self = this;
                                                                                self.setColorInfo();
                                                                            },
                                                                            setColorInfo: function() {
                                                                                var self = this;

                                                                                var value = self.data.value,
                                                                                    code = self.data.code;

                                                                                if (self.color_xhr) { self.color_xhr.abort(); }

                                                                                if (!value || !code) {
                                                                                    self.color_xhr = that.getColorInfo(value, code)
                                                                                        .always( function() {
                                                                                            self.color_xhr = null;
                                                                                        })
                                                                                        .done( function(data) {
                                                                                            if (!self.data.value && data.name) { self.data.value = data.name; }
                                                                                            if (!self.data.code && data.color) { self.data.code = data.color; }
                                                                                        });
                                                                                }
                                                                            },
                                                                            success: function() {
                                                                                var self = this;

                                                                                if (!self.data.value.length) { return false; }

                                                                                if (self.states.is_locked) { return false; }
                                                                                self.states.is_locked = true;

                                                                                var data = {
                                                                                    "feature_id": self.feature.id,
                                                                                    "value[value]": self.data.value
                                                                                };

                                                                                if (self.feature.type === "color" && self.data.code.length) {
                                                                                    data["value[code]"] = self.data.code;
                                                                                }

                                                                                if (self.data.unit.length) {
                                                                                    data["value[unit]"] = self.data.unit;
                                                                                }

                                                                                request(data)
                                                                                    .always( function() {
                                                                                        self.states.is_locked = false;
                                                                                    })
                                                                                    .done( function(data) {
                                                                                        self.$emit("add", data.option);
                                                                                        self.cancel();
                                                                                    });

                                                                                function request(request_data) {
                                                                                    var deferred = $.Deferred();

                                                                                    $.post(that.urls["add_feature_value"], request_data, "json")
                                                                                        .done( function(response) {
                                                                                            if (response.status === "ok") {
                                                                                                deferred.resolve(response.data);
                                                                                            } else {
                                                                                                deferred.reject(response.errors);
                                                                                            }
                                                                                        })
                                                                                        .fail( function() {
                                                                                            deferred.reject([]);
                                                                                        });

                                                                                    return deferred.promise();
                                                                                }
                                                                            },
                                                                            cancel: function() {
                                                                                var self = this;
                                                                                self.show_form = false;
                                                                                self.data.value = "";
                                                                                self.data.code = "";
                                                                                self.data.unit = "";
                                                                            }
                                                                        }
                                                                    }
                                                                },
                                                                methods: {
                                                                    formatOption: function(option) {
                                                                        option.active = false;
                                                                        option.states = {
                                                                            visible: true
                                                                        };
                                                                        return option;
                                                                    },
                                                                    onAdd: function(option) {
                                                                        var dialog_self = this;

                                                                        let option_data = { name: option.name, value: option.value }
                                                                        if (typeof option.code === "string") { option_data.code = option.code; }
                                                                        if (typeof option.unit === "string") { option_data.unit = option.unit; }

                                                                        self.column_info.options.unshift(option_data);

                                                                        var new_option = dialog_self.formatOption(option_data);
                                                                        new_option.active = true;
                                                                        dialog_self.options.unshift(new_option);
                                                                    },
                                                                    success: function() {
                                                                        var dialog_self = this;
                                                                        dialog.options.onSuccess(dialog_self.options);
                                                                        dialog.close();
                                                                    },
                                                                    close: function() {
                                                                        dialog.close();
                                                                    }
                                                                },
                                                                created: function() {
                                                                    $section.css("visibility", "");
                                                                },
                                                                mounted: function() {
                                                                    dialog.resize();
                                                                }
                                                            }).mount($section[0]);
                                                        }

                                                    },
                                                }
                                            },
                                            "component-feature-input": {
                                                props: ["option", "column_data"],
                                                emits: ["blur"],
                                                data: function() {
                                                    var self = this;

                                                    var is_number = (self.column_data.format === "number"),
                                                        format = (is_number ? "number-negative" : null);

                                                    return {
                                                        states: {
                                                            format: format,
                                                            is_number: is_number,
                                                            is_preview: (self.column_data.format === "number")
                                                        }
                                                    };
                                                },
                                                template: that.components["component-feature-input"],
                                                delimiters: ['{ { ', ' } }'],
                                                components: {
                                                    "component-input": that.vue_components["component-input"]
                                                },
                                                computed: {
                                                    show_preview: function() {
                                                        let self = this;
                                                        return (self.column_data.format === "number" && self.states.is_preview);
                                                    },
                                                    formatted_value: function() {
                                                        var self = this,
                                                            value = self.option.value;

                                                        if (Math.abs(parseFloat(value)) >= 0) {
                                                            value = $.wa.validate(self.states.format, value, {
                                                                group_size: 3,
                                                                delimiter: ($.wa_shop_products.lang === "ru" ? ",": null)
                                                            });
                                                        } else {
                                                            value = "";
                                                        }

                                                        return value;
                                                    }
                                                },
                                                methods: {
                                                    togglePreview: function(show) {
                                                        var self = this;
                                                        show = (typeof show === "boolean" ? show : false);
                                                        self.states.is_preview = show;
                                                    },
                                                    onBlur: function(event) {
                                                        let self = this;
                                                        self.togglePreview(true);
                                                        self.$emit("blur", event);
                                                    }
                                                }
                                            }
                                        },
                                        computed: {
                                            units: function() {
                                                var self = this;
                                                return (self.column_info.units ? self.column_info.units : null);
                                            },
                                            options: function() {
                                                var self = this,
                                                    options = null;

                                                if (self.column_data.render_type === 'select') {
                                                    options = (self.column_data.options || self.column_info.options);
                                                    if (!options) {
                                                        options = [{ name: "ERROR: Варианты отсутствуют", value: "" }];
                                                    }
                                                }

                                                return options;
                                            }
                                        },
                                        methods: {
                                            onFieldFocus: function() {
                                                var self = this;
                                                //console.log( "clear" );
                                                if (self.states.is_timer_start) {
                                                    clearTimeout(self.timer);
                                                    self.states.is_timer_start = false;
                                                }
                                            },
                                            onFieldBlur: function() {
                                                var self = this;
                                                //console.log( "blur" );
                                                if (self.states.is_changed) {
                                                    self.onFieldChange();
                                                }
                                            },
                                            onFieldChange: function() {
                                                var self = this,
                                                    time = 500;

                                                //console.log( "start_change" );

                                                if (self.states.is_timer_start) {
                                                    clearTimeout(self.timer);
                                                }

                                                self.states.is_changed = true;
                                                self.states.is_timer_start = true;

                                                self.timer = setTimeout( function() {
                                                    //console.log( "change" );

                                                    self.onChange();
                                                    self.states.is_changed = false;
                                                    self.states.is_timer_start = false;
                                                }, time);
                                            },
                                            onChange: function() {
                                                var self = this;

                                                if (self.states.is_locked) { return false; }
                                                self.states.is_locked = true;

                                                var data = getData();

                                                $.post(that.urls["product_update"], data, "json")
                                                    .always( function() {
                                                        self.states.is_locked = false;
                                                    })
                                                    .done( function(response) {
                                                        if (response.status !== "ok") {
                                                            var error_search = response.errors.filter( error => (error.id === "not_found"));
                                                            if (error_search.length) {
                                                                $.wa_shop_products.showDeletedProductDialog();
                                                            } else {
                                                                console.error("ERROR", response.errors);
                                                            }
                                                        } else {
                                                            var data = null;
                                                            if (response.data.value && response.data.value[self.column_data.code]) {
                                                                data = response.data.value[self.column_data.code];
                                                            }
                                                            updateData(data, self.column_data);
                                                        }
                                                    });

                                                function getData() {
                                                    var result = {
                                                        presentation_id: that.presentation.id,
                                                        product_id: self.product.id,
                                                        field_id: self.column.column_type,
                                                        value: {}
                                                    };

                                                    if (self.sku_mod) {
                                                        result.sku_id = self.sku_mod.id;
                                                    }

                                                    switch (self.column_data.render_type) {
                                                        case "field":
                                                            $.each(self.column_data.options, function(i, option) {
                                                                let name = self.column_data.code + (self.column_data.options.length > 1 ? "." + i : "");
                                                                let value = option.value;

                                                                if (self.column_data.type.indexOf("double") >= 0) {
                                                                    if (value.substr(0, 1) === ".") { value = "0" + value; }
                                                                }

                                                                result.value[name] = { "value": value };
                                                            });

                                                            if (self.column_data.active_unit) {
                                                                let name = self.column_data.code + (self.column_data.options.length > 1 ? ".0" : "");
                                                                result.value[name].unit = self.column_data.active_unit.value;
                                                            }
                                                            break;
                                                        case "color":
                                                            result.value[self.column_data.code] = {
                                                                "code": self.column_data.options[0].code,
                                                                "value": self.column_data.options[0].value
                                                            };
                                                            break;
                                                        case "select":
                                                        case "textarea":
                                                        case "checkbox":
                                                            result.value[self.column_data.code] = self.column_data.value;
                                                            break;
                                                        case "field.date":
                                                            result.value[self.column_data.code] = self.column_data.options[0].value;
                                                            break;
                                                        case "range":
                                                            var min = self.column_data.options[0].value,
                                                                max = self.column_data.options[1].value;

                                                            if (min.substr(0, 1) === ".") { min = "0" + min; }
                                                            if (max.substr(0, 1) === ".") { max = "0" + max; }

                                                            if (min > 0 && max > 0 && max < min) {
                                                                self.column_data.options[0].value = max;
                                                                self.column_data.options[1].value = min;
                                                            }
                                                        case "range.date":
                                                            result.value[self.column_data.code] = {
                                                                "value": {
                                                                    begin: self.column_data.options[0].value,
                                                                    end  : self.column_data.options[1].value
                                                                }
                                                            };
                                                            if (self.column_data.active_unit) {
                                                                result.value[self.column_data.code].unit = self.column_data.active_unit.value;
                                                            }
                                                            break;
                                                    }

                                                    return result;
                                                }

                                                function updateData(data, column_data) {
                                                    if (data) {
                                                        switch (column_data.render_type) {
                                                            case "field":
                                                                column_data.options[0].value = data.value;
                                                                break;
                                                            case "range":
                                                                column_data.options[0].value = data.value.begin;
                                                                column_data.options[1].value = data.value.end;
                                                                break;
                                                        }
                                                    }

                                                    if (column_data.can_be_edited === "partial") {
                                                        var result = false;

                                                        switch (column_data.render_type) {
                                                            case "field":
                                                            case "field.date":
                                                            case "color":
                                                            case "range":
                                                            case "range.date":
                                                                $.each(column_data.options, function(i, option) {
                                                                    if (option.value) { result = true; }
                                                                });
                                                                break;

                                                            case "select":
                                                            case "textarea":
                                                            case "checkbox":
                                                                if (column_data.value.length) { result = true; }
                                                                break;
                                                        }

                                                        if (!result) {
                                                            self.states.is_available = false;
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    },
                                    "component-product-column-sbratio": {
                                        props: ["column", "column_info", "column_data", "product", "sku_mod"],
                                        emits: ["change"],
                                        data: function() {
                                            var self = this;
                                            var placeholder = "";
                                            if (self.sku_mod) {
                                                var product_column_data = self.product.columns[self.column.column_type];
                                                if (product_column_data.value) {
                                                    placeholder = product_column_data.value;
                                                }
                                            }
                                            return {
                                                required: !self.sku_mod,
                                                placeholder: placeholder,
                                                states: {
                                                    is_preview: true
                                                }
                                            }
                                        },
                                        template: that.components["component-product-column-sbratio"],
                                        delimiters: ['{ { ', ' } }'],
                                        components: { "component-input": that.vue_components["component-input"] },
                                        computed: {
                                            formatted_value: function() {
                                                var self = this,
                                                    value = self.column_data.value;

                                                if (Math.abs(parseFloat(value)) >= 0) {
                                                    value = $.wa.validate("number", value, {
                                                        fraction_size: 8,
                                                        group_size: 3,
                                                        delimiter: ($.wa_shop_products.lang === "ru" ? ",": null)
                                                    });
                                                } else {
                                                    value = "";
                                                }

                                                return value;
                                            }
                                        },
                                        methods: {
                                            onChange: function(value) {
                                                var self = this;
                                                self.$emit("change", value);
                                            },
                                            togglePreview: function(show) {
                                                var self = this;
                                                show = (typeof show === "boolean" ? show : false);
                                                self.states.is_preview = show;
                                            },
                                        }
                                    },
                                    "component-product-column-sku": {
                                        props: ["column", "column_info", "column_data", "product"],
                                        template: that.components["component-product-column-sku"],
                                        delimiters: ['{ { ', ' } }'],
                                        computed: {
                                            value: function() {
                                                const self = this;
                                                var result = "";

                                                if (self.product.normal_mode) {
                                                    if (that.presentation.view === "table") {
                                                        result = getSmartString();
                                                    } else {
                                                        result = self.column_data.value;
                                                    }
                                                } else if (self.product.sku.sku) {
                                                    result = self.product.sku.sku;
                                                }

                                                return result;

                                                function getSmartString() {
                                                    var result = "";

                                                    if (self.product.normal_mode) {
                                                        var sku_count = self.product.skus.length,
                                                            sku_mod_count = self.product.sku_count;

                                                        var string = $.wa.locale_plural(sku_count, that.locales["product_sku_forms"]),
                                                            sku_mod_string = $.wa.locale_plural(sku_mod_count, that.locales["product_sku_mod_forms"]);

                                                        result = string.replace("%s", sku_mod_string);
                                                    }

                                                    return result;
                                                }
                                            }
                                        },
                                        methods: {}
                                    }
                                },
                                methods: {
                                    onChange: function(value) {
                                        var self = this;

                                        if (Object.keys(self.errors).length > 0) {
                                            console.error( "TODO: имеются ошибки валидации" );
                                        } else {

                                            if (self.states.is_locked) { return false; }
                                            self.states.is_locked = true;

                                            var data = {
                                                presentation_id: that.presentation.id,
                                                product_id: self.product.id,
                                                field_id: self.column.column_type,
                                                value: value
                                            };

                                            // Если это модификация
                                            if (self.sku_mod) { data["sku_id"] = self.sku_mod.id; }

                                            $.post(that.urls["product_update"], data, "json")
                                                .always( function() {
                                                    self.states.is_locked = false;
                                                })
                                                .done( function(response) {
                                                    if (response.status !== "ok") {
                                                        var error_search = response.errors.filter( error => (error.id === "not_found"));
                                                        if (error_search.length) {
                                                            $.wa_shop_products.showDeletedProductDialog();
                                                        } else {
                                                            console.error("ERROR", response.errors);
                                                        }
                                                    }
                                                });
                                        }
                                    }
                                }
                            },
                            "component-products-table-mass-selection": {
                                props: ["products"],
                                data: function() {
                                    return {
                                        all_products: 'true',
                                        states: {
                                            is_disabled: false
                                        }
                                    }
                                },
                                template: that.components["component-products-table-mass-selection"],
                                delimiters: ['{ { ', ' } }'],
                                components: {
                                    "component-checkbox": that.vue_components["component-checkbox"],
                                    "component-radio": that.vue_components["component-radio"]
                                },
                                computed: {
                                    products_length: function() {
                                        return this.products.length;
                                    },
                                    current_page_smart_string: function() {
                                        return $.wa.locale_plural(that.products.length, that.locales["products_forms"]);
                                    },
                                    selected_products: function() {
                                        return this.products.filter( function(product) {
                                            return product.states.selected;
                                        });
                                    },
                                    selected_all_products: function() {
                                        return (this.selected_products.length === this.products_length);
                                    },
                                    bool_all_products: function () {
                                        return this.all_products === 'true';
                                    }
                                },
                                methods: {
                                    onChange: function(value) {
                                        var self = this;

                                        self.checkAll(true);

                                        that.keys.mass_actions += 1;
                                        self.states.is_disabled = true;

                                        setTimeout( function() {
                                            self.states.is_disabled = false;
                                        }, 100);
                                    },
                                    checkAll: function(check_all) {
                                        var self = this;

                                        $.each(self.products, function(i, product) {
                                            product.states.selected = check_all;
                                        });

                                        self.setSelection((check_all ? self.bool_all_products : false));
                                    },
                                    setSelection: function(all_products) {
                                        var self = this;

                                        all_products = (typeof all_products === "boolean" ? all_products : self.bool_all_products);
                                        var products_selection = (all_products ? "all_products" : "visible_products");
                                        if (that.products_selection.value !== products_selection) {
                                            that.products_selection.value = products_selection;
                                        }
                                    }
                                }
                            }
                        },
                        computed: {
                            selected_products: function() {
                                var self = this;

                                return self.products.filter( function(product) {
                                    return product.states.selected;
                                });
                            }
                        },
                        methods: {
                            getColumnWidth: function(column) {
                                var result = "";

                                if (column.width > 0) {
                                    result = column.width + "px";

                                } else {
                                    var column_info = that.columns[column.column_type];
                                    if (column_info.min_width) {
                                        result = column_info.min_width + "px";
                                    };
                                }

                                return result;
                            },
                            getColumnClassName: function(column) {
                                var result = [];

                                switch (column.column_type) {
                                    case "name":
                                        result.push("s-column-name");
                                        break;
                                    case "image_crop_small":
                                        result.push("s-column-photo");
                                        break;
                                }

                                return result.join(" ");
                            },
                            showColumnManager: function() {
                                $.waDialog({
                                    html: that.templates["dialog-list-column-manager"],
                                    options: {
                                        columns_ready: getColumns(),
                                    },
                                    onOpen: function($dialog, dialog) {
                                        that.initDialogColumnManager($dialog, dialog);
                                    }
                                });

                                function getColumns() {
                                    var ready = $.Deferred();

                                    (function(resolve) {
                                        if (that.full_columns_array_loaded) {
                                            resolve();
                                        } else {
                                            $.get(that.urls["presentation_get_columns"], { presentation_id: that.presentation.id }, function(result) {
                                                that.full_columns_array_loaded = true;
                                                that.columns_array = result.data;
                                                that.columns = $.wa.construct(that.columns_array, "id");
                                                resolve();
                                            }, "json");
                                        }
                                    }(function() {
                                        ready.resolve(prepareColumns());
                                    }));

                                    return ready.promise();
                                }

                                function prepareColumns() {
                                    let columns = $.wa.clone(that.columns_array);
                                    $.each(columns, function(i, column) {
                                        column.settings = (column.settings ? column.settings : {});
                                        column.states = {
                                            move: false,
                                            highlighted: false,
                                            display: true,
                                            ready: false
                                        }
                                    });
                                    return columns;
                                }
                            },
                            onClickItem: function () {
                                this.$parent.onClickItem.call(null, ...arguments);
                            }
                        }
                    },

                    "component-table-filters-categories-sets-types": {
                        data() {
                            // define contentType from request by filter
                            if (that.filter.rules && that.filter.rules.length) {
                                const FILTER_TYPES = ['categories', 'sets', 'types'];
                                const rule = that.filter.rules.find(r => FILTER_TYPES.includes(r.type))
                                if (rule) {
                                    that.sidebarFiltersToggleManager.setFilterType(rule.type, true);
                                }
                            }

                            that.bus_sidebar_tree_menu.onExpand((menu_id, obj, is_expanded) => {
                                that.sidebarFiltersToggleManager[is_expanded ? 'addFilterItem' : 'removeFilterItem'](menu_id, obj.id);
                            });

                            const MENU_ID_TO_TYPE = {
                                categories: "category",
                                sets: "set",
                                type: "type"
                            };
                            that.bus_sidebar_tree_menu.onSelectItem((menu_id, item) => {
                                if (item.is_locked) { return false; }
                                item.states.enabled = !item.states.enabled;

                                this.success({
                                    type: MENU_ID_TO_TYPE[menu_id],
                                    item: item
                                });
                            })

                            return {
                                filters_expanded: that.sidebarFiltersToggleManager.get()
                            }
                        },
                        template: that.components["component-table-filters-categories-sets-types"],
                        delimiters: ['{ { ', ' } }'],
                        mixins: [FilterApiMixin],
                        components: {
                            "component-sidebar-section": that.vue_components["component-sidebar-section"],
                            "component-table-filters-categories": {
                                emits: ["success"],
                                data: function() {
                                    const options = $.wa.clone(that.filter_options),
                                        categories = options.categories,
                                        categories_object = formatCategories(getCategoriesObject(categories));
                                    return {
                                        search_string: "",
                                        categories: categories,
                                        categories_object: categories_object
                                    };

                                    function formatCategories(items) {
                                        $.each(items, function(i, item) {
                                            item.states = {
                                                is_wanted: false,
                                                is_wanted_inside: false
                                            }
                                        });

                                        return items;
                                    }

                                    function getCategoriesObject(categories) {
                                        const result = {};

                                        addCategoryToObject(categories);

                                        return result;

                                        function addCategoryToObject(categories) {
                                            $.each(categories, function(i, category) {
                                                result[category.id] = category;
                                                if (category.categories) {
                                                    addCategoryToObject(category.categories);
                                                }
                                            });
                                        }
                                    }
                                },
                                template: that.components["component-table-filters-categories"],
                                delimiters: ['{ { ', ' } }'],
                                components: {
                                    "component-table-filters-search": that.vue_components["component-table-filters-search"],
                                    "component-tree-menu": that.vue_components["component-tree-menu"]
                                },
                                computed: {
                                    items: function() {
                                        const checkCategories = (categories) => {
                                            let result = false;
                                            for (let i = 0; i < categories.length; i++) {
                                                if (checkCategory(categories[i])) {
                                                    result = true;
                                                    break;
                                                }
                                            }

                                            return result;
                                        };

                                        const checkCategory = (category) => {
                                            const is_wanted = (this.search_string === "" || category.name.toLowerCase().indexOf( this.search_string.toLowerCase() ) >= 0);

                                            category.states.is_wanted = is_wanted;
                                            category.states.is_wanted_inside = false;

                                            if (category.categories.length) {
                                                category.states.is_wanted_inside = checkCategories(category.categories);
                                            }

                                            return category.states.is_wanted || category.states.is_wanted_inside;
                                        };

                                        checkCategories(this.categories);

                                        if (this.search_string.trim()) {
                                            return this.categories.filter(c => c.states.is_wanted || c.states.is_wanted_inside);
                                        }
                                        return this.categories;
                                    },
                                }
                            },
                            "component-table-filters-sets": {
                                data: function() {
                                    const options = $.wa.clone(that.filter_options);

                                    return {
                                        search_string: "",
                                        sets: formatSets(options.sets)
                                    };

                                    function formatSets(items) {
                                        $.each(items, function(i, item) {
                                            if (item.is_group) {
                                                item.states = {
                                                    is_open: false,
                                                    is_wanted: false,
                                                    is_wanted_inside: false,
                                                }
                                                if (item.sets.length) {
                                                    formatSets(item.sets);
                                                }
                                            } else {
                                                item.states = {
                                                    is_wanted: false,
                                                    is_wanted_inside: false,
                                                }
                                            }
                                        });
                                        return items;
                                    }
                                },
                                template: that.components["component-table-filters-sets"],
                                delimiters: ['{ { ', ' } }'],
                                components: {
                                    "component-table-filters-search": that.vue_components["component-table-filters-search"],
                                    "component-tree-menu": that.vue_components["component-tree-menu"],
                                },
                                computed: {
                                    items: function() {
                                        /**
                                         * @param {Object} set
                                         * @return {Boolean}
                                         * */
                                        const checkSet = (set) => {
                                            const is_wanted = (this.search_string === "" || set.name.toLowerCase().indexOf( this.search_string.toLowerCase() ) >= 0);

                                            set.states.is_wanted = is_wanted;
                                            set.states.is_wanted_inside = false;

                                            if (set.is_group && set.sets.length) {
                                                set.states.is_wanted_inside = checkSets(set.sets);
                                            }

                                            return is_wanted || set.states.is_wanted_inside;
                                        };
                                        /**
                                         * @param {Object|Array} sets
                                         * @return {Boolean}
                                         * */
                                        const checkSets = (sets) => {
                                            let result = false;

                                            for (let i = 0; i < sets.length; i++) {
                                                if (checkSet(sets[i])) {
                                                    result = true;
                                                    break;
                                                }
                                            }

                                            return result;
                                        };

                                        checkSets(this.sets);

                                        if (this.search_string.trim()) {
                                            return this.sets.filter(s => s.states.is_wanted || s.states.is_wanted_inside);
                                        }
                                        return this.sets;
                                    }
                                }
                            },
                            "component-table-filters-types": {
                                emits: ["success"],
                                data: function() {
                                    const options = $.wa.clone(that.filter_options);

                                    return {
                                        search_string: "",
                                        types: formatOptions(options.types)
                                    };


                                    function formatOptions(items) {
                                        $.each(items, function(i, item) {
                                            item.states = {
                                                enabled: false,
                                                locked: false,
                                                is_wanted: false
                                            }
                                        });
                                        return items;
                                    }
                                },
                                template: that.components["component-table-filters-types"],
                                delimiters: ['{ { ', ' } }'],
                                components: {
                                    "component-table-filters-search": that.vue_components["component-table-filters-search"],
                                    "component-tree-menu": that.vue_components["component-tree-menu"]
                                },
                                computed: {
                                    items: function() {
                                        return this.types.filter((item) => {
                                            item.states.is_wanted = (item.name.toLowerCase().indexOf(this.search_string.toLowerCase()) >= 0)
                                            return item.states.is_wanted;
                                        });
                                    }
                                },
                            }
                        },
                        methods: {
                            setFilterType: function(type, is_expanded) {
                                that.sidebarFiltersToggleManager.setFilterType(type, is_expanded);
                            },
                            success: function(data) {
                                this.applyCategories(data);
                            },
                            autofocus: function() {
                                this.$nextTick(() => {
                                    $(this.$el).find("input.js-autofocus").trigger("focus");
                                });
                            },
                            applyCategories: function(data) {
                                const result = {
                                    rule_params: []
                                };

                                switch (data.type) {
                                    case "set":
                                        var set_id = (data.item.set_id || data.item.id);
                                        result.rule_type = "sets";
                                        result.rule_params = [set_id];
                                        break;

                                    case "type":
                                        result.rule_type = "types";
                                        result.rule_params = [data.item.id];
                                        break;

                                    case "category":
                                        result.rule_type = "categories";
                                        result.rule_params = [data.item.id];
                                        break;
                                }
                                this.save(result);
                            }
                        }
                    },

                    "component-mass-actions": {
                        props: ["products"],
                        data: function() {
                            return {
                                actions: that.mass_actions
                            };
                        },
                        template: that.components["component-mass-actions"],
                        delimiters: ['{ { ', ' } }'],
                        components: {
                            "component-mass-actions-aside": {
                                props: ["products", "actions"],
                                emits: ["call_action"],
                                template: that.components["component-mass-actions-aside"],
                                delimiters: ['{ { ', ' } }'],
                                components: { "component-checkbox": that.vue_components["component-checkbox"] },
                                computed: {
                                    products_length: function() {
                                        let self = this,
                                            result = self.products.length,
                                            unselected = that.products.length - self.products.length;

                                        if (that.products_selection.value === "all_products") {
                                            result = that.products_total_count - unselected;
                                        }

                                        return result;
                                    }
                                },
                                methods: {
                                    close: function() {
                                        var self = this;

                                        $.each(self.products, function(i, product) {
                                            product.states.selected = false;
                                        });

                                        that.products_selection.value = 'visible_products';
                                    },

                                    callAction: function(action) {
                                        const self = this;
                                        self.$emit("call_action", action);
                                    }
                                }
                            }
                        },
                        methods: {
                            callAction: function(action) {
                                const self = this;
                                that.callMassAction(action, self.products, that.products_selection.value);
                            }
                        }
                    }
                },
                computed: {
                    selected_products: function() {
                        var self = this;

                        return self.products.filter( function(product) {
                            return product.states.selected;
                        });
                    },
                    show_mass_actions: function() {
                        const self = this;
                        return (self.selected_products.length || that.products_selection.value === "all_products");
                    }
                },
                methods: {
                    changeView: function(view_id) {
                        that.animate(true);

                        that.broadcast.getPresentationsIds().done( function(ids) {
                            var data = {
                                presentation_id: that.presentation.id,
                                open_presentations: ids,
                                view: view_id
                            };

                            $.post(that.urls["presentation_edit_settings"], data, "json")
                                .always( function() {
                                    that.animate(false);
                                })
                                .done( function(response) {
                                    if (response.status === "ok" && response.data.new_presentation_id) {
                                        that.reload({
                                            presentation: response.data.new_presentation_id
                                        });
                                    } else {
                                        that.reload();

                                        // that.presentation.view = view_id;
                                        // that.keys.table += 1;
                                        //
                                        // self.$nextTick(function () {
                                        //     self.setScrollProductsSection(0);
                                        // });
                                    }
                                });
                        });
                    },
                    changePage: function(page) {
                        // console.log("TODO: change page", page);
                    },
                    changeLimit: function(limit) {
                        that.animate(true);

                        that.broadcast.getPresentationsIds().done( function(ids) {
                            var data = {
                                presentation_id: that.presentation.id,
                                open_presentations: ids,
                                rows_on_page: limit
                            };

                            $.post(that.urls["presentation_edit_settings"], data, "json")
                                .always( function() {
                                    that.presentation.states.is_changed = true;
                                }).done( function(response) {
                                    if (response.status === "ok") {
                                        that.reload({
                                            page: 1,
                                            presentation: (response.data.new_presentation_id || that.presentation.id)
                                        });
                                    } else {
                                        that.reload({ page: 1 });
                                    }
                                });
                        });
                    },
                    changePresentation: function(presentation) {
                        sessionPresentationName(presentation.name);

                        that.broadcast.getPresentationsIds().done( function(ids) {
                            that.reload({
                                open_presentations: ids,
                                active_presentation: that.presentation.id,
                                presentation: presentation.id
                            });
                        });
                    },
                    onScrollProductsSection: function(event) {
                        var scroll_top = $(event.target).scrollTop();
                        if (scroll_top > 0) {
                            sessionStorage.setItem("shop_products_table_scroll_top", scroll_top);
                        } else {
                            that.clearTableScrollPosition();
                        }
                    },
                    setScrollProductsSection: function(scroll_top) {
                        var self = this;

                        var $wrapper = $(self.$el),
                            $table_section = $wrapper.find(".s-products-table-section, .s-products-thumbs-section"),
                            page = that.paging.page > 1 ? that.paging.page : 1,
                            last_page_scrolled = Number(sessionStorage.getItem("shop_products_table_scroll_page"));

                        sessionStorage.setItem("shop_products_table_scroll_page", page);

                        if ($table_section.length) {
                            scroll_top = (typeof scroll_top === "number" ? scroll_top : sessionStorage.getItem("shop_products_table_scroll_top"));
                            if (last_page_scrolled === page) {
                                var scroll_to_product_id = Number(sessionStorage.getItem("shop_products_table_scroll_product_id"));
                                if (scroll_to_product_id > 0) {
                                    sessionStorage.removeItem("shop_products_table_scroll_product_id");
                                    var $product_section = $table_section.find('div[data-product-id="' + scroll_to_product_id + '"]');
                                    if ($product_section.length) {
                                        $table_section.scrollTop($product_section.offset().top - $table_section.offset().top - 40);
                                    }
                                } else {
                                    $table_section.scrollTop(scroll_top);
                                }
                            }
                        }
                    }
                },
                delimiters: ['{ { ', ' } }'],
                created: function () {
                    $vue_section.css("visibility", "");
                },
                mounted: function() {
                    var self = this;

                    that.init();

                    self.$nextTick( function() {
                        self.setScrollProductsSection();
                    });
                }
            });

            $.vue_app.config.compilerOptions.whitespace = 'preserve';

            return $.vue_app.mount($vue_section[0]);
        };

        /**
         * @param {Object?} options
         * @return {String}
         * @description Возвращает URL страницы с параметрами фильтрации, которые можно откорректировать.
         * */
        Page.prototype.getPageURL = function(options) {
            options = (typeof options !== "undefined" ? options : {});

            var that = this,
                data = {
                    page: (that.paging.page > 1 ? that.paging.page : null),
                    filter: null,
                    presentation: (that.presentation.id ? that.presentation.id : null),

                    active_presentation: null,
                    open_presentations: null,

                    active_filter: null
                },
                result = [];

            $.each(data, function(name, value) {
                if (options[name] || options[name] === null || options[name] === "") { value = options[name]; }
                if (value) {
                    result.push(name+"="+value);
                }
            });

            return (result.length ? that.page_url + "?"+result.join("&") : null);
        };

        /**
         * @return {String}
         * @description Обновляет адресную строку.
         * */
        Page.prototype.updatePageURL = function() {
            var that = this;

            let presentation_id = that.presentation.id,
                filter_id = that.filter.id;

            if (typeof presentation_id !== "string") {
                console.error("ERROR: Can't update page url because presentation ID is missing.");
                presentation_id = null;
            }

            if (typeof filter_id !== "string") {
                console.error("ERROR: Can't update page url because filter ID is missing.");
                filter_id = null;
            }

            let location_href = window.location.href,
                parsed_url = new URL(location_href);

            if (presentation_id) {
                parsed_url.searchParams.set("presentation", presentation_id);
            } else {
                parsed_url.searchParams.delete("presentation");
            }

            if (filter_id) {
                parsed_url.searchParams.set("filter", filter_id);
            } else {
                parsed_url.searchParams.delete("filter");
            }

            // Очищаем урл от мусора
            parsed_url.searchParams.delete("active_presentation");
            parsed_url.searchParams.delete("open_presentations");
            parsed_url.searchParams.delete("filter");
            parsed_url.searchParams.delete("active_filter");
            parsed_url.searchParams.delete("category_id");
            parsed_url.searchParams.delete("set_id");

            const url = parsed_url.toString();
            if (location_href !== url) {
                window.history.replaceState(null, "", url);
            }
        };

        /**
         * @param {Object?} options
         * @return {Null}
         * @description обновляет контентную часть страницы
         * */
        Page.prototype.reload = function(options) {
            var that = this;

            // Триггер нативного перехода по ссылке для роутинга
            var $link = $("<a />", { href: that.getPageURL(options)});
            that.$wrapper.prepend($link);
            $link.trigger("click").remove();

            that.animate(true);
            $(document).on("wa_loaded", ()=> that.animate(false));
        };

        Page.prototype.animate = function(show) {
            var that = this;

            const locked_class = "is-locked";
            if (show) {
                if (!that.states.$animation) {
                    that.states.$animation = $.waLoading({top: "4rem"});
                    that.$wrapper.addClass(locked_class)
                    that.states.$animation.animate(500, 98, false);
                }
            } else {
                if (that.states.$animation) {
                    that.states.$animation.hide();
                    that.states.$animation = null;
                    that.$wrapper.removeClass(locked_class);
                }
            }
        };

        Page.prototype.getColorInfo = function(name, color) {
            var that = this;

            var href = that.urls["color_transliterate"],
                data = {};

            if (color) { data.code = color2magic(color); }
            if (name) { data.name = name; }

            var deferred = $.Deferred();

            $.get(href, data, "json")
                .done( function(response) {
                    if (response.status === "ok") {
                        deferred.resolve({
                            name: response.data.name,
                            color: magic2color(response.data.code)
                        });
                    } else {
                        deferred.reject();
                    }
                })
                .fail( function() {
                    deferred.reject();
                });

            return deferred.promise();

            function color2magic(color) {
                return 0xFFFFFF & parseInt(('' + color + '000000').replace(/[^0-9A-F]+/gi, '').substr(0, 6), 16);
            }

            function magic2color(magic) {
                return (0xF000000 | magic).toString(16).toLowerCase().replace(/^f/, "#");
            }
        };

        Page.prototype.initBroadcast = function() {
            var that = this;

            var broadcast;
                presentation_data = [],
                presentation_promise = null,
                filter_data = [],
                filter_promise = null;

            try {
                var time = 200;
                broadcast = new BroadcastChannel("presentation_channel");
                broadcast.onmessage = function(event) {
                    // Закрываем если страница уже в прошлом
                    if (!$.contains(document, that.$wrapper[0])) {
                        broadcast.close();
                        return false;
                    }

                    switch (event.data.action) {
                        case "get_presentation_id":
                            broadcast.postMessage({
                                action: "presentation_id",
                                value: that.presentation.id
                            });
                            break;
                        case "presentation_id":
                            if (presentation_data.indexOf(event.data.value) < 0) {
                                presentation_data.push(event.data.value);
                            }
                            break;

                        case "get_filter_id":
                            broadcast.postMessage({
                                action: "filter_id",
                                value: that.filter.id
                            });
                            break;
                        case "filter_id":
                            if (filter_data.indexOf(event.data.value) < 0) {
                                filter_data.push(event.data.value);
                            }
                            break;
                        default:
                            break;
                    }
                }
            } catch (e) {
                console.log('BroadcastChannel is not supported', e);
                broadcast = null;
            }

            return {
                getPresentationsIds: function() {
                    if (presentation_promise) { return presentation_promise; }

                    var deferred = $.Deferred();

                    presentation_data = [];

                    if (broadcast) {
                        broadcast.postMessage({ action: "get_presentation_id" });
                        setTimeout( function() {
                            var ids = presentation_data;
                            deferred.resolve(ids);
                            presentation_promise = null;
                        }, time);
                    } else {
                        deferred.resolve(presentation_data);
                    }

                    presentation_promise = deferred.promise();

                    return presentation_promise;
                },
                getFiltersIds: function() {
                    if (filter_promise) { return filter_promise; }

                    var deferred = $.Deferred();

                    filter_data = [];

                    if (broadcast) {
                        broadcast.postMessage({ action: "get_filter_id" });
                        setTimeout( function() {
                            var ids = filter_data;
                            deferred.resolve(ids);
                            filter_promise = null;
                        }, time);
                    } else {
                        deferred.resolve(filter_data);
                    }

                    filter_promise = deferred.promise();

                    return filter_promise;
                }
            };
        };

        Page.prototype.updateRootVariables = function(options) {
            var that = this;

            // Tooltips
            if (options.tooltips) {
                $.each(options.tooltips, function(i, tooltip) { $.wa.new.Tooltip(tooltip); });
            }

            // Components
            if (options.components) {
                that.components = $.extend(that.components, options.components);
            }

            // Templates
            if (options.templates) {
                that.templates = $.extend(that.templates, options.templates);
            }

            // Locales
            if (options.locales) {
                that.locales = $.extend(that.locales, options.locales);
            }

            // Locales
            if (options.urls) {
                that.urls = $.extend(that.urls, options.urls);
            }
        };

        // DIALOGS

        Page.prototype.initDialogFeatureValue = function($dialog, dialog) {
            const that = this;
            const $section = $dialog.find(".js-vue-section");

            Vue.createApp({
                data() {
                    return {
                        custom_html_error: Vue.ref(false),
                        item_data: getItemData(),
                        items_keys: {},
                        states: {
                            locked: false,
                            is_loading: false,
                            is_fetching: false
                        }
                    }
                },
                delimiters: ['{ { ', ' } }'],
                components: {
                    "component-radio": that.vue_components["component-radio"],
                    "component-input": that.vue_components["component-input"],
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
                                components: {
                                    "component-switch": that.vue_components["component-switch"]
                                },
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
                            }
                        }
                    }
                },
                computed: {
                    format: function() {
                        const self = this;
                        var result = null;

                        if (self.item_data.type.indexOf("double") >= 0 || self.item_data.type.indexOf("dimension") >= 0 || self.item_data.type.indexOf("range") >= 0) {
                            result = "number";
                        }

                        return result;
                    },
                    validate: function() {
                        const self = this;

                        var validate_format = self.format;

                        if (validate_format === "number") {
                            if (self.item_data.display_type === "feature") {
                                validate_format = "number-negative";
                            }
                        }

                        return validate_format;
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
                    custom_html: function() {
                        const self = this;
                        if (self.item_data.render_type !== 'custom') {
                            return '';
                        }
                        if (self.item_data.html) {
                            return self.item_data.html;
                        }
                        if (self.item_data.dialog_url && !self.custom_html_xhr) {
                            self.custom_html_xhr = $.get(self.item_data.dialog_url).then(function(html) {
                                self.item_data.html = html;

                                self.$nextTick(function () {
                                    // This will run <script>s provided by plugins. Vue does not attach them inside HTML.
                                    var $script_wrapper = $('<div style="display:none">').html(html);
                                    $script_wrapper.find(':not(script)').remove();
                                    $script_wrapper.appendTo(that.$wrapper);
                                });
                            }, function() {
                                self.custom_html_error.value = true;
                            });
                        } else {
                            self.custom_html_error.value = true;
                        }
                        return '';
                    },
                    disabled: function() {
                        const self = this;
                        var result = false;

                        var has_values = true;
                        switch (self.item_data.render_type) {
                            case "tags":
                                has_values = !!self.item_data.options.length;
                                break;
                        }

                        if (self.states.locked || Object.keys(self.errors).length || !has_values) {
                            result = true;
                        }

                        return result;
                    }
                },
                methods: {
                    onChange: function() {
                        const self = this;
                    },

                    save: function() {
                        const self = this;

                        dialog.options.onSuccess({
                            rule_type: self.item_data.rule_type,
                            rule_params: getData()
                        });

                        dialog.close();

                        function getData() {
                            var result = [];

                            switch (self.item_data.render_type) {
                                case "range":
                                case "range.date":
                                    result = {
                                        start: self.item_data.options[0].value,
                                        end: self.item_data.options[1].value
                                    }
                                    if (self.item_data.active_unit) {
                                        result.unit = self.item_data.active_unit.value;
                                    }
                                    break;
                                case "checkbox":
                                    $.each(self.item_data.options, function(i, option) {
                                        if (option.states.enabled) {
                                            var value = (option.id || option.value);
                                            if (value) { result.push(value); }
                                            else { console.error("ERROR: value is not exist"); }
                                        }
                                    });
                                    break;
                                case "tags":
                                    $.each(self.item_data.options, function(i, option) {
                                        result.push(option.id);
                                    });
                                    break;
                                case "boolean":
                                    result.push(self.item_data.value);
                                    break;
                                case "custom":
                                    result.push($(self.$el.parentElement).find('[name="rule_params"]').first().val());
                                    break;
                            }

                            return result;
                        }
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

                                $.post(that.urls["filter_feature_value"], data, function(response_data) {
                                    if (!response_data.length) {
                                        response_data = [{
                                            name: that.locales["no_results"],
                                            value: null
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
                                if (ui.item.value) {
                                    self.addItem(ui.item);
                                    $input.val("");
                                }
                                return false;
                            },
                            focus: function() { return false; }
                        }).data("ui-autocomplete")._renderItem = function( ul, item ) {
                            return $("<li />").addClass("ui-menu-item-html").append("<div>"+item.name+"</div>").appendTo(ul);
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
                created: function () {
                    $section.css("visibility", "");

                    const { id } = this.item_data;
                    if (!id) {
                        return;
                    }

                    this.states.is_fetching = true;
                    $.post(that.urls["features_get"], { id }, "json")
                        .always(() => {
                            this.states.is_fetching = false;
                        })
                        .done((r) => {
                            this.$nextTick(() => {
                                dialog.resize();
                            });

                            if (r?.status === "ok" && Array.isArray(r?.data) && r.data[0]) {
                                this.item_data = getItemData(r.data[0]);
                            } else {
                                console.error(r);
                            }
                        });
                },
                mounted: function() {
                    var self = this,
                        $wrapper = $(self.$el);

                    var $autocomplete = $wrapper.find('.js-autocomplete');
                    if ($autocomplete.length) {
                        self.initAutocomplete($autocomplete);
                    }

                    dialog.resize();
                }
            }).mount($section[0]);

            function getItemData(item_data) {
                if (!item_data) {
                    item_data = $.wa.clone(dialog.options.item_data);
                }

                // Если НЕ характеристика
                if (!item_data.type) {
                    item_data.type = "";
                }

                switch (item_data.type) {
                    // Одинарное числовое поле превращаем в диапазон
                    case "double":
                        if (item_data.render_type === "field") {
                            item_data.render_type = "range";
                            item_data.options = [{ name: "", value: "" }, { name: "", value: "" }];
                        }
                        break;

                    // Одинарное текстовое поле превращаем в теги
                    case "varchar":
                    case "color":
                        if (item_data.render_type === "field" || item_data.render_type === "color") {
                            item_data.render_type = "tags";
                            item_data.options = [];
                        }
                        break;

                    case "date":
                        if (item_data.render_type === "field.date" || item_data.render_type === "range") {
                            item_data.render_type = "range.date";
                            item_data.options = [{ name: "", value: "" }, { name: "", value: "" }];
                        }
                        break;

                    // Особый вид для "да/нет"
                    case "boolean":
                        item_data.render_type = "boolean";
                        item_data.value = "";
                        break;

                    default:
                        if (item_data.type.indexOf("dimension") >= 0) {
                            if (item_data.render_type === "field") {
                                item_data.render_type = "range";
                                item_data.options = [{ name: "", value: "" }, { name: "", value: "" }];
                            }
                        }
                        break;
                }

                if (item_data.render_type === "select") {
                    item_data.render_type = "checkbox";
                }

                if (item_data.render_type === "checkbox") {
                    $.each(item_data.options, function(i, option) {
                        option.states = {
                            enabled: false,
                            display: true,
                            ready: false
                        };
                    })
                }

                return item_data;
            }
        };

        Page.prototype.initDialogColumnManager = function($dialog, dialog) {
            const that = this;
            const $section = $dialog.find(".js-vue-section");
            const app = Vue.createApp({
                data() {
                    return {
                        columns: [],
                        states: {
                            locked: false,
                            column_expand: false,
                            is_fetching: false
                        }
                    }
                },
                delimiters: ['{ { ', ' } }'],
                components: {
                    "component-search-column": {
                        data: function() {
                            return {
                                search_string: "",
                                selection: []
                            };
                        },
                        template: that.components["component-search-column"],
                        delimiters: ['{ { ', ' } }'],
                        methods: {
                            search: function() {
                                const self = this;
                                const columns = self.$root.columns;
                                const selection = [];

                                // Делаем выборку
                                checkItems(columns);

                                // Сохраняем выборку
                                self.selection = selection;

                                function checkItems(columns) {
                                    $.each(columns, function(i, column) {
                                        let display = true;

                                        if (!column.enabled) {
                                            if (self.search_string.length > 0) {
                                                let is_wanted = (column.name.toLowerCase().indexOf( self.search_string.toLowerCase() ) >= 0 );
                                                if (is_wanted) {
                                                    selection.push(column);
                                                } else {
                                                    display = false;
                                                }
                                            }
                                        }

                                        if (column.states.display !== display) {
                                            column.states.display = display;
                                        }
                                    });
                                }
                            },
                            revert: function() {
                                let self = this;
                                self.search_string = "";
                                self.search();
                            }
                        }
                    },
                    "component-list-column-item": {
                        props: ["column"],
                        emits: ["column_enabled", "column_expanded"],
                        data: function() {
                            switch (this.column.id) {
                                case "name":
                                    if (!this.column.settings.long_name_format) {
                                        this.column.settings["long_name_format"] = "";
                                    }
                                    break;
                                case "summary":
                                    if (!this.column.settings.display) {
                                        this.column.settings["display"] = "text";
                                    }
                                    break;
                                case "tags":
                                case "categories":
                                case "sets":
                                    if (!this.column.settings.visible_count) {
                                        this.column.settings["visible_count"] = "3";
                                    }
                                case "price":
                                case "compare_price":
                                case "purchase_price":
                                case "base_price":
                                    if (!this.column.settings.format) {
                                        this.column.settings["format"] = "origin";
                                    }
                                    break;
                            }

                            var settings = null;
                            if (Object.keys(this.column.settings).length) {
                                settings = this.column.settings;
                            }

                            return {
                                column_info: that.columns[this.column.id],
                                settings: settings,
                                states: {
                                    timer_enable: 0,
                                    show_settings: false,
                                    hidden: false,
                                    jump_animation: false,
                                    ready: false
                                }
                            };
                        },
                        template: that.components["component-list-column-item"],
                        delimiters: ['{ { ', ' } }'],
                        watch: {
                            "column.enabled": function(value) {
                                if (value === true) {
                                    this.$emit("column_enabled", this.column);
                                }
                            }
                        },
                        components: {
                            "component-radio": that.vue_components["component-radio"],
                            "component-switch": that.vue_components["component-switch"]
                        },
                        computed: {
                            is_stock: function() {
                                return (this.column_info.id.indexOf('stocks_') === 0);
                            },
                            is_virtual_stock: function() {
                                return (this.column_info.id.indexOf('stocks_v') === 0);
                            },
                            is_feature: function() {
                                return (this.column_info.id.indexOf('feature_') >= 0);
                            },
                            item_class: function() {
                                const column = this.column,
                                    result = [];

                                if (column.states.move) { result.push("is-moving"); }
                                if (column.states.highlighted) { result.push("is-highlighted"); }
                                if (this.states.show_settings) { result.push("is-expanded"); }
                                if (this.column.states.ready) { result.push("is-ready"); }
                                if (this.states.hidden) { result.push("fade-out"); }
                                if (this.states.jump_animation) { result.push("is-jump-enabled"); }
                                if (this.column.enabled) { result.push("jump-down"); }
                                if (!this.column.enabled) { result.push("jump-up"); }

                                // Включает возможность перемещения
                                if (column.enabled) { result.push("js-column-wrapper"); }

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
                                    self.column.enabled = value;
                                }, 500);
                            },
                            setup: function() {
                                var self = this;
                                self.states.show_settings = !self.states.show_settings;
                                self.$emit("column_expanded", (self.states.show_settings ? self.column : null));
                            }
                        },
                        mounted: function() {
                            if (this.column.states.ready) {
                                this.states.hidden = true;
                                this.column.states.highlighted = true;
                                setTimeout(() => {
                                    this.states.hidden = false;
                                }, 10);
                                setTimeout(() => {
                                    this.column.states.highlighted = false;
                                }, 1000);

                            } else {
                                this.column.states.ready = true;
                            }
                        }
                    }
                },
                computed: {
                    active_columns: function() {
                        return this.columns.filter(col => col.enabled);
                    },
                    inactive_columns: function() {
                        return this.columns.filter(col => !col.enabled);
                    }
                },
                methods: {
                    initDragAndDrop: function($wrapper) {
                        var self = this;

                        var $document = $(document);

                        var drag_data = {},
                            over_locked = false;

                        var columns = self.columns,
                            columns_object = $.wa.construct(columns, "id");

                        $wrapper.on("dragstart", ".js-column-move-toggle", function(event) {
                            var $move = $(this).closest(".js-column-wrapper");

                            var column_id = "" + $move.attr("data-id"),
                                column = getColumn(column_id);

                            if (!column) {
                                console.error("ERROR: column isn't exist");
                                return false;
                            }

                            event.originalEvent.dataTransfer.setDragImage($move[0], 20, 20);

                            drag_data.move_column = column;
                            column.states.is_moving = true;

                            $document.on("dragover", ".js-column-wrapper", onOver);
                            $document.on("dragend", onEnd);
                        });

                        function onOver(event) {
                            if (drag_data.move_column) {
                                event.preventDefault();
                                if (!over_locked) {
                                    over_locked = true;
                                    moveColumn($(event.currentTarget));
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

                        function moveColumn($over) {
                            var column_id = "" + $over.attr("data-id"),
                                column = getColumn(column_id);

                            if (!column) {
                                console.error("ERROR: column isn't exist");
                                return false;
                            }

                            if (drag_data.move_column === column) { return false; }

                            var move_index = columns.indexOf(drag_data.move_column),
                                over_index = columns.indexOf(column),
                                before = (move_index > over_index);

                            if (over_index !== move_index) {
                                columns.splice(move_index, 1);

                                over_index = columns.indexOf(column);
                                var new_index = over_index + (before ? 0 : 1);

                                columns.splice(new_index, 0, drag_data.move_column);
                            }
                        }

                        function getColumn(column_id) {
                            return (columns_object[column_id] ? columns_object[column_id] : null);
                        }

                        function off() {
                            drag_data = {};
                            $document.off("dragover", ".js-column-wrapper", onOver);
                            $document.off("dragend", onEnd);

                            $.each(columns, function(i, column) {
                                column.states.is_moving = false;
                            });
                        }
                    },
                    save: function() {
                        var self = this;
                        var data = getData();

                        self.states.locked = true;

                        that.broadcast.getPresentationsIds().done( function(ids) {
                            data["open_presentations"] = ids;

                            $.post(that.urls["presentation_edit_columns"], data, "json")
                                .always(function () {
                                    self.states.locked = false;
                                })
                                .done(function (response) {
                                    if (response.status === "ok") {
                                        dialog.close();
                                        that.presentation.states.is_changed = true;
                                        that.reload({
                                            presentation: (response.data.new_presentation_id || that.presentation.id)
                                        });
                                    } else {
                                        alert("ERROR");
                                        console.log(response);
                                    }
                                });
                        });

                        function getData() {
                            let result = {
                                presentation_id: that.presentation.id,
                                view_id: that.presentation.view,
                                columns: [],
                                settings: {}
                            };

                            $.each(self.active_columns, function(i, column) {
                                var data = {
                                    column_type: column.id,
                                    settings: {}
                                };

                                if (Object.keys(column.settings).length) {
                                    $.each(column.settings, function(name, value) {
                                        data.settings[name] = value;
                                    });
                                }

                                result.columns.push(data);
                            });

                            return result;
                        }
                    },
                    onColumnExpand: function(column) {
                        var self = this;
                        self.states.column_expand = !!column;
                    },
                    onColumnEnabled: function(move_column) {
                        var self = this,
                            columns = self.columns;

                        if (self.active_columns.length > 1) {
                            var over_column = self.active_columns[self.active_columns.length - 1];
                            if (move_column !== over_column) {
                                var move_index = columns.indexOf(move_column),
                                    over_index = columns.indexOf(over_column);

                                if (over_index !== move_index) {
                                    columns.splice(move_index, 1);
                                    var new_index = columns.indexOf(over_column) + 1;
                                    columns.splice(new_index, 0, move_column);
                                }
                            }
                        }
                    }
                },
                created: function () {
                    $section.css("visibility", "");

                    this.states.is_fetching = true;
                    dialog.options.columns_ready.then((columns) => {
                        this.columns = columns;
                        this.states.is_fetching = false;
                        this.$nextTick(() => {
                            dialog.resize()
                            this.initDragAndDrop($(this.$el.parentElement));
                        });
                    });
                }
            });
            app.mount($section[0]);

            dialog.onClose = () => app.unmount();
        };

        Page.prototype.callMassAction = function(action, products, products_selection) {
            var that = this;

            console.log(products_selection, action.id);

            if (action.states.is_locked) { return false; }

            var product_ids = [],
                is_all_products = (products_selection === "all_products");

            if (is_all_products) {
                product_ids = that.products.filter(product => !product.states.selected).map(product => product.id);
            } else {
                product_ids = products.map(product => product.id);
            }

            var dialog_data = { product_ids: product_ids };
            if (is_all_products) {
                dialog_data.presentation_id = that.presentation.id;
            }

            switch (action.id) {
                case "export_csv":
                    var redirect_url = action.redirect_url;
                    if (is_all_products) {
                        var url_path = that.filter.rules.length > 0 ? `presentation/${that.presentation.id}/` : "all/";
                        redirect_url = action.redirect_url.replace("id/", url_path);
                    }else{
                        redirect_url = redirect_url + product_ids.join(",");
                    }

                    var $link = $("<a />", { href: redirect_url });
                    that.$wrapper.prepend($link);
                    $link.trigger("click").remove();
                    break;

                case "add_to_categories":
                    action.states.is_locked = true;
                    $.post(action.action_url, dialog_data, "json")
                        .always( function() {
                            action.states.is_locked = false;
                        })
                        .done( function(html) {
                            addToCategoryDialog(html, action);
                        });
                    break;

                case "exclude_from_categories":
                    action.states.is_locked = true;
                    $.post(action.action_url, dialog_data, "json")
                        .always( function() {
                            action.states.is_locked = false;
                        })
                        .done( function(html) {
                            excludeFromCategoriesDialog(html, action);
                        });
                    break;

                case "add_to_sets":
                    action.states.is_locked = true;
                    $.post(action.action_url, dialog_data, "json")
                        .always( function() {
                            action.states.is_locked = false;
                        })
                        .done( function(html) {
                            addToSetsDialog(html, action);
                        });
                    break;

                case "exclude_from_sets":
                    action.states.is_locked = true;
                    $.post(action.action_url, dialog_data, "json")
                        .always( function() {
                            action.states.is_locked = false;
                        })
                        .done( function(html) {
                            excludeFromSetsDialog(html, action);
                        });
                    break;

                case "assign_tags":
                    action.states.is_locked = true;
                    $.post(action.action_url, dialog_data, "json")
                        .always( function() {
                            action.states.is_locked = false;
                        })
                        .done( function(html) {
                            assignTagsDialog(html, action);
                        });
                    break;

                case "remove_tags":
                    action.states.is_locked = true;
                    $.post(action.action_url, dialog_data, "json")
                        .always( function() {
                            action.states.is_locked = false;
                        })
                        .done( function(html) {
                            removeTagsDialog(html, action);
                        });
                    break;

                case "set_badge":
                    action.states.is_locked = true;
                    $.post(action.action_url, dialog_data, "json")
                        .always( function() {
                            action.states.is_locked = false;
                        })
                        .done( function(html) {
                            setBadgeDialog(html, action);
                        });
                    break;

                case "set_publication":
                    action.states.is_locked = true;
                    $.post(action.action_url, dialog_data, "json")
                        .always( function() {
                            action.states.is_locked = false;
                        })
                        .done( function(html) {
                            setPublicationDialog(html, action);
                        });
                    break;

                case "set_type":
                    action.states.is_locked = true;
                    $.post(action.action_url, dialog_data, "json")
                        .always( function() {
                            action.states.is_locked = false;
                        })
                        .done( function(html) {
                            setTypeDialog(html, action);
                        });
                    break;

                case "duplicate":
                    action.states.is_locked = true;

                    var data = getSubmitData();

                    $.post(action.action_url, data, "json")
                        .fail( function() {
                            action.states.is_locked = false;
                        })
                        .done( function(response) {
                            action.states.is_locked = false;
                            duplicateProductsDialog(response.data.html);
                        });
                    break;

                case "delete":
                    action.states.is_locked = true;
                    $.post(action.action_url, dialog_data, "json")
                        .always( function() {
                            action.states.is_locked = false;
                        })
                        .done( function(html) {
                            removeProductsDialog(html, action);
                        });
                    break;

                case "discount_coupons":
                    var redirect_url = action.redirect_url;
                    if (is_all_products) {
                        redirect_url = action.redirect_url.replace("id/", "presentation/");
                        product_ids.unshift(that.presentation.id);
                    }

                    var href = redirect_url + product_ids.join(",");
                    var $link = $("<a />", { href: href });
                    that.$wrapper.prepend($link);
                    $link.trigger("click").remove();
                    break;

                case "associate_promo":
                    action.states.is_locked = true;
                    $.post(action.action_url, dialog_data, "json")
                        .always( function() {
                            action.states.is_locked = false;
                        })
                        .done( function(html) {
                            associatePromoDialog(html, action);
                        });
                    break;

                default:
                    var submit_data = getSubmitData();
                    var products_hash;
                    if (is_all_products) {
                        products_hash = 'presentation/'+that.presentation.id;
                        if (submit_data.product_ids) {
                            products_hash += ','+submit_data.product_ids.join(",");
                        }
                    } else {
                        products_hash = 'id/'+product_ids.join(",");
                    }
                    submit_data.products_hash = products_hash;

                    if (action.action_url) {
                        action.states.is_locked = true;
                        $.post(action.action_url, submit_data, "json")
                            .always( function() {
                                action.states.is_locked = false;
                            })
                            .done( function() {

                            });
                        break;
                    } else if (action.redirect_url) {
                        var redirect_url = action.redirect_url;
                        redirect_url += redirect_url.indexOf('?') < 0 ? '?' : '&';
                        redirect_url += 'products_hash=' + encodeURIComponent(products_hash);

                        var $link = $('<a />', { href: redirect_url });
                        that.$wrapper.prepend($link);
                        $link.trigger("click").remove();
                        break;
                    } else if (action.dialog_url) {
                        $.post(action.dialog_url, submit_data, "json")
                            .always( function() {
                                action.states.is_locked = false;
                            })
                            .done( function(html) {
                                $.waDialog({
                                    html: html,
                                    options: {},
                                    onOpen: function($dialog, dialog) {
                                        $dialog.trigger('wa_dialog_ready', [action, submit_data, dialog, $dialog]);
                                    }
                                });
                            });
                        break;
                    } else if (action.custom_handler) {
                        $('#js-products-page-content').trigger('wa_custom_mass_action', [action, submit_data]);
                        break;
                    }

                    // Диалог показывет сообщение "действие ещё не сделано"
                    $.waDialog({
                        html: that.templates["dialog-category-clone"],
                        onOpen: function($dialog, dialog) {
                            var $section = $dialog.find(".js-vue-section");

                            var vue_model = Vue.createApp({
                                data() {
                                    return {
                                        action: action
                                    }
                                },
                                delimiters: ['{ { ', ' } }'],
                                created: function () {
                                    $section.css("visibility", "");
                                },
                                mounted: function() {
                                    dialog.resize();
                                }
                            }).mount($section[0]);
                        }
                    });
                    break;
            }

            // Functions

            function addToCategoryDialog(html, action) {
                var ready = $.Deferred(),
                    vue_ready = $.Deferred();

                return $.waDialog({
                    html: html,
                    options: {
                        ready: ready.promise(),
                        vue_ready: vue_ready.promise(),
                        initDialog: initDialog
                    },
                    onOpen: function($dialog, dialog) {
                        ready.resolve($dialog, dialog);
                    }
                });

                function initDialog($dialog, dialog, options) {
                    var $section = $dialog.find(".js-vue-section");

                    that.updateRootVariables(options);

                    var categories_object = getCategoryObject(options.categories);

                    var component_category_form = {
                        props: ["category"],
                        emits: ["cancel"],
                        data: function() {
                            return {
                                name: "",
                                parent_id: (this.category ? this.category.id : ""),
                                errors: {},
                                states: {
                                    is_locked: false
                                }
                            };
                        },
                        template: that.components["component-category-form"],
                        delimiters: ['{ { ', ' } }'],
                        computed: {},
                        methods: {
                            save: function() {
                                const self = this;

                                var errors = self.validate();
                                if (errors.length) { return false; }

                                if (self.states.is_locked) { return false; }
                                self.states.is_locked = true;

                                var data = {
                                    name: self.name,
                                    parent_id: self.parent_id
                                };

                                $.post(that.urls["create_category"], data, "json")
                                    .always( function() {
                                        self.states.is_locked = false;
                                    })
                                    .done( function(response) {
                                        if (response.status === "ok") {
                                            self.$emit("success", response.data);
                                        } else {
                                            alert("ERROR: create category");
                                        }
                                    });
                            },
                            onInput: function() {
                                const self = this;
                                self.validate();
                            },
                            validate: function() {
                                const self = this;
                                var errors = [];

                                var error_id = "name_required";
                                if (!$.trim(self.name).length) {
                                    var error = { id: error_id, text: error_id };
                                    self.errors[error_id] = error;
                                    errors.push(error);
                                } else if (self.errors[error_id]) {
                                    delete self.errors[error_id];
                                }

                                return errors;
                            },
                            cancel: function() {
                                const self = this;
                                self.$emit("cancel");
                            }
                        },
                        mounted: function() {
                            const self = this;
                            self.$nextTick( function() {
                                $(self.$el).find("input.js-autofocus").trigger("focus");
                            });
                        }
                    };

                    that.vue_components["component-category-search"]["template"] = that.components["component-category-search"];
                    Vue.createApp({
                        data() {
                            return {
                                categories_array: options.categories,
                                categories_object: categories_object,
                                search_string: "",
                                states: {
                                    is_locked: false,
                                    show_form: false
                                }
                            }
                        },
                        delimiters: ['{ { ', ' } }'],
                        components: {
                            "component-category-search": that.vue_components["component-category-search"],
                            "component-category-form": component_category_form,
                            "component-categories-group": {
                                name: "component-categories-group",
                                props: ["categories"],
                                template: that.components["component-categories-group"],
                                delimiters: ['{ { ', ' } }'],
                                components: {
                                    "component-category": {
                                        props: ["category"],
                                        data: function() {
                                            return {
                                                states: {
                                                    show_form: false
                                                }
                                            };
                                        },
                                        template: that.components["component-category"],
                                        delimiters: ['{ { ', ' } }'],
                                        components: {
                                            "component-category-form": component_category_form
                                        },
                                        computed: {
                                            category_class: function() {
                                                const self = this;
                                                var result = [];

                                                if (self.category.states.is_wanted) {
                                                    result.push("is-wanted");
                                                }

                                                return result.join(" ");
                                            }
                                        },
                                        methods: {
                                            toggleForm: function(show) {
                                                const self = this;
                                                show = (typeof show === "boolean" ? show : false);
                                                self.states.show_form = show;
                                            },
                                            onFormSuccess: function(new_category) {
                                                const self = this;
                                                new_category = formatCategory(new_category);
                                                self.category.categories.unshift(new_category);
                                                categories_object[new_category.id] = new_category;
                                                self.toggleForm(false);
                                                self.$nextTick( function() {
                                                    dialog.resize();
                                                });
                                            },
                                            onFormCancel: function() {
                                                const self = this;
                                                self.toggleForm(false);
                                            }
                                        }
                                    }
                                }
                            }
                        },
                        computed: {
                            categories: function() {
                                const self = this;

                                checkCategories(self.categories_array);

                                return self.categories_array;

                                /**
                                 * @param {Object|Array} categories
                                 * @return {Boolean}
                                 * */
                                function checkCategories(categories) {
                                    var result = false;

                                    $.each(categories, function(i, category) {
                                        var is_wanted = checkCategory(category);
                                        if (is_wanted) {
                                            result = true;
                                        }
                                    });

                                    return result;
                                }

                                /**
                                 * @param {Object} category
                                 * @return {Boolean}
                                 * */
                                function checkCategory(category) {
                                    var is_wanted = (category.name.toLowerCase().indexOf( self.search_string.toLowerCase() ) >= 0),
                                        is_wanted_inside = (category.categories.length ? checkCategories(category.categories) : false),
                                        is_display = true;

                                    if (self.search_string === "") {
                                        is_wanted = is_wanted_inside = false;
                                    } else {
                                        is_display = !!(is_wanted || is_wanted_inside);
                                    }

                                    category.states.is_wanted = is_wanted;
                                    category.states.is_wanted_inside = is_wanted_inside;
                                    category.states.display_category = is_display;

                                    return is_display;
                                }
                            },
                        },
                        methods: {
                            toggleForm: function(show) {
                                const self = this;
                                show = (typeof show === "boolean" ? show : false);
                                self.states.show_form = show;
                            },
                            onFormSuccess: function(new_category) {
                                const self = this;
                                new_category = formatCategory(new_category);
                                self.categories.unshift(new_category);
                                categories_object[new_category.id] = new_category;
                                self.toggleForm(false);

                                self.$nextTick( function() {
                                    dialog.resize();
                                });
                            },
                            onFormCancel: function() {
                                const self = this;
                                self.toggleForm(false);
                            },

                            save: function() {
                                const self = this;

                                if (self.states.is_locked) { return false; }

                                var category_ids = [];
                                $.each(self.categories_object, function(i, category) {
                                    if (category.states.checked) {
                                        category_ids.push(category.id);
                                    }
                                });
                                if (!category_ids.length) { return false; }

                                self.states.is_locked = true;

                                var data = getSubmitData();
                                data.category_ids = category_ids;

                                $.post(that.urls["add_to_categories"], data, "json")
                                    .fail( function() {
                                        self.states.is_locked = false;
                                    }).done( function() {
                                    $.wa_shop_products.router.reload().done( function() {
                                        dialog.close();
                                    });
                                });
                            }
                        },
                        created: function () {
                            $section.css("visibility", "");
                        },
                        mounted: function() {
                            var self = this;

                            self.$nextTick( function() {
                                dialog.resize();
                                vue_ready.resolve(dialog);
                                $(self.$el).find("input.js-autofocus").trigger("focus");
                            });
                        }
                    }).mount($section[0]);
                }

                function formatCategory(category) {
                    if (!category.categories) {
                        category.categories = [];
                    }

                    if (!category.states) {
                        category.states = {
                            checked: false,

                            is_wanted: false,
                            is_wanted_inside: false,
                            display_category: false
                        }
                    }

                    return category;
                }

                function getCategoryObject(categories) {
                    var categories_object = {};

                    setCategory(categories);

                    formatCategories(categories_object);

                    return categories_object;

                    function setCategory(categories) {
                        $.each(categories, function(i, category) {
                            categories_object[category.id] = category;
                            if (category.categories.length) {
                                setCategory(category.categories);
                            }
                        });
                    }

                    function formatCategories(items) {
                        $.each(items, function(i, item) {
                            formatCategory(item);
                        });
                        return items;
                    }
                }
            }

            function excludeFromCategoriesDialog(html, action) {
                var ready = $.Deferred(),
                    vue_ready = $.Deferred();

                return $.waDialog({
                    html: html,
                    options: {
                        ready: ready.promise(),
                        vue_ready: vue_ready.promise(),
                        initDialog: initDialog
                    },
                    onOpen: function($dialog, dialog) {
                        ready.resolve($dialog, dialog);
                    }
                });

                function initDialog($dialog, dialog, options) {
                    var $section = $dialog.find(".js-vue-section");

                    that.updateRootVariables(options);

                    var categories_object = getCategoryObject(options.categories);

                    that.vue_components["component-category-search"]["template"] = that.components["component-category-search"];
                    const app = Vue.createApp({
                        data() {
                            return {
                                categories_array: options.categories,
                                categories_object: categories_object,
                                search_string: "",
                                states: {
                                    is_locked: false,
                                    show_form: false
                                }
                            }
                        },
                        delimiters: ['{ { ', ' } }'],
                        components: {
                            "component-category-search": that.vue_components["component-category-search"],
                            "component-categories-group": {
                                name: "component-categories-group",
                                props: ["categories"],
                                template: that.components["component-categories-group"],
                                delimiters: ['{ { ', ' } }'],
                                components: {
                                    "component-category": {
                                        props: ["category"],
                                        data: function() {
                                            return {
                                                states: {
                                                    show_form: false
                                                }
                                            };
                                        },
                                        template: that.components["component-category"],
                                        delimiters: ['{ { ', ' } }'],
                                        computed: {
                                            category_class: function() {
                                                const self = this;
                                                var result = [];

                                                if (self.category.states.is_wanted) {
                                                    result.push("is-wanted");
                                                }

                                                return result.join(" ");
                                            }
                                        }
                                    }
                                }
                            }
                        },
                        computed: {
                            categories: function() {
                                const self = this;

                                checkCategories(self.categories_array);

                                return self.categories_array;

                                /**
                                 * @param {Object|Array} categories
                                 * @return {Boolean}
                                 * */
                                function checkCategories(categories) {
                                    var result = false;

                                    $.each(categories, function(i, category) {
                                        var is_wanted = checkCategory(category);
                                        if (is_wanted) {
                                            result = true;
                                        }
                                    });

                                    return result;
                                }

                                /**
                                 * @param {Object} category
                                 * @return {Boolean}
                                 * */
                                function checkCategory(category) {
                                    var is_wanted = (self.search_string === "" || category.name.toLowerCase().indexOf( self.search_string.toLowerCase() ) >= 0),
                                        is_wanted_inside = false;

                                    if (category.categories.length) {
                                        is_wanted_inside = checkCategories(category.categories);
                                    }

                                    if (self.search_string !== "") {
                                        category.states.is_wanted = is_wanted;
                                        category.states.is_wanted_inside = is_wanted_inside;
                                    }
                                    category.states.display_category = (is_wanted || is_wanted_inside);

                                    return !!(is_wanted || is_wanted_inside);
                                }
                            },
                        },
                        methods: {
                            save: function() {
                                const self = this;

                                if (self.states.is_locked) { return false; }

                                var category_ids = [];
                                $.each(self.categories_object, function(i, category) {
                                    if (category.states.checked) {
                                        category_ids.push(category.id);
                                    }
                                });
                                if (!category_ids.length) { return false; }

                                self.states.is_locked = true;

                                var data = getSubmitData();
                                data.category_ids = category_ids;

                                $.post(that.urls["exclude_from_categories"], data, "json")
                                    .fail( function() {
                                        self.states.is_locked = false;
                                    }).done( function() {
                                    $.wa_shop_products.router.reload().done( function() {
                                        dialog.close();
                                    });
                                });
                            }
                        },
                        created: function () {
                            $section.css("visibility", "");
                        },
                        mounted: function() {
                            var self = this;

                            self.$nextTick( function() {
                                dialog.resize();
                                vue_ready.resolve(dialog);
                                $(self.$el).find("input.js-autofocus").trigger("focus");
                            });
                        }
                    }).mount($section[0]);
                }

                function formatCategory(category) {
                    if (!category.categories) {
                        category.categories = [];
                    }

                    if (!category.states) {
                        category.states = {
                            checked: false,

                            is_wanted: false,
                            is_wanted_inside: false,
                            display_category: false
                        }
                    }

                    return category;
                }

                function getCategoryObject(categories) {
                    var categories_object = {};

                    setCategory(categories);

                    formatCategories(categories_object);

                    return categories_object;

                    function setCategory(categories) {
                        $.each(categories, function(i, category) {
                            categories_object[category.id] = category;
                            if (category.categories.length) {
                                setCategory(category.categories);
                            }
                        });
                    }

                    function formatCategories(items) {
                        $.each(items, function(i, item) {
                            formatCategory(item);
                        });
                        return items;
                    }
                }
            }

            function addToSetsDialog(html, action) {
                var ready = $.Deferred(),
                    vue_ready = $.Deferred();

                return $.waDialog({
                    html: html,
                    options: {
                        ready: ready.promise(),
                        vue_ready: vue_ready.promise(),
                        initDialog: initDialog
                    },
                    onOpen: function($dialog, dialog) {
                        ready.resolve($dialog, dialog);
                    }
                });

                function initDialog($dialog, dialog, options) {
                    var $section = $dialog.find(".js-vue-section");

                    that.updateRootVariables(options);

                    var object = getObject(options.items),
                        sets_object = object.sets,
                        groups_object = object.groups;

                    var component_set_form = {
                        props: ["item"],
                        emits: ["cancel"],
                        data: function() {
                            return {
                                name: "",
                                parent_id: (this.item ? this.item.group_id : ""),
                                errors: {},
                                states: {
                                    is_locked: false
                                }
                            };
                        },
                        template: that.components["component-set-form"],
                        delimiters: ['{ { ', ' } }'],
                        methods: {
                            save: function() {
                                const self = this;

                                var errors = self.validate();
                                if (errors.length) { return false; }

                                if (self.states.is_locked) { return false; }
                                self.states.is_locked = true;

                                var data = {
                                    name: self.name,
                                    parent_id: self.parent_id
                                };

                                $.post(that.urls["create_set"], data, "json")
                                    .always( function() {
                                        self.states.is_locked = false;
                                    })
                                    .done( function(response) {
                                        if (response.status === "ok") {
                                            self.$emit("success", response.data);
                                        } else {
                                            alert("ERROR: create set");
                                        }
                                    });
                            },
                            onInput: function() {
                                this.validate();
                            },
                            validate: function() {
                                const self = this;
                                var errors = [];

                                var error_id = "name_required";
                                if (!$.trim(self.name).length) {
                                    var error = { id: error_id, text: error_id };
                                    self.errors[error_id] = error;
                                    errors.push(error);
                                } else if (self.errors[error_id]) {
                                    delete self.errors[error_id];
                                }

                                return errors;
                            },
                            cancel: function() {
                                this.$emit("cancel");
                            }
                        },
                        mounted: function() {
                            const self = this;
                            self.$nextTick( function() {
                                $(self.$el).find("input.js-autofocus").trigger("focus");
                            });
                        }
                    };

                    that.vue_components["component-set-search"]["template"] = that.components["component-set-search"];
                    var vue_model = Vue.createApp({
                        data() {
                            return {
                                items_array: options.items,
                                sets: sets_object,
                                groups: groups_object,
                                search_string: "",
                                states: {
                                    is_locked: false,
                                    show_form: false
                                }
                            }
                        },
                        delimiters: ['{ { ', ' } }'],
                        components: {
                            "component-set-form": component_set_form,
                            "component-set-search": that.vue_components["component-set-search"],
                            "component-set-item-group": {
                                name: "component-set-item-group",
                                props: ["items"],
                                template: that.components["component-set-item-group"],
                                delimiters: ['{ { ', ' } }'],
                                components: {
                                    "component-set-item": {
                                        props: ["item"],
                                        data: function() {
                                            return {
                                                states: {
                                                    show_form: false
                                                }
                                            };
                                        },
                                        template: that.components["component-set-item"],
                                        delimiters: ['{ { ', ' } }'],
                                        components: {
                                            "component-set-form": component_set_form
                                        },
                                        computed: {
                                            item_class: function() {
                                                const self = this;
                                                var result = [];

                                                if (self.item.states.is_wanted) {
                                                    result.push("is-wanted");
                                                }

                                                return result.join(" ");
                                            }
                                        }
                                    }
                                }
                            }
                        },
                        computed: {
                            items: function() {
                                const self = this;

                                checkItems(self.items_array);

                                return self.items_array;

                                /**
                                 * @param {Object|Array} items
                                 * @return {Boolean}
                                 * */
                                function checkItems(items) {
                                    var result = false;

                                    $.each(items, function(i, item) {
                                        var is_wanted = checkItem(item);
                                        if (is_wanted) {
                                            result = true;
                                        }
                                    });

                                    return result;
                                }

                                /**
                                 * @param {Object} item
                                 * @return {Boolean}
                                 * */
                                function checkItem(item) {
                                    var is_wanted = (item.name.toLowerCase().indexOf( self.search_string.toLowerCase() ) >= 0),
                                        is_wanted_inside = (item.is_group && item.sets.length ? checkItems(item.sets) : false),
                                        is_display = true;

                                    if (self.search_string === "") {
                                        is_wanted = is_wanted_inside = false;
                                    } else {
                                        is_display = !!(is_wanted || is_wanted_inside);
                                    }

                                    item.states.is_wanted = is_wanted;
                                    item.states.is_wanted_inside = is_wanted_inside;
                                    item.states.display_item = is_display;

                                    return is_display;
                                }
                            },
                        },
                        methods: {
                            toggleForm: function(show) {
                                const self = this;
                                show = (typeof show === "boolean" ? show : false);
                                self.states.show_form = show;
                            },
                            onFormSuccess: function(new_item) {
                                const self = this;
                                new_item = formatItem(new_item);
                                self.items_array.unshift(new_item);
                                sets_object[new_item.set_id] = new_item;
                                self.toggleForm(false);
                                self.$nextTick( function() {
                                    dialog.resize();
                                });
                            },
                            onFormCancel: function() {
                                const self = this;
                                self.toggleForm(false);
                            },

                            save: function() {
                                const self = this;

                                if (self.states.is_locked) { return false; }

                                var set_ids = [];
                                $.each(self.sets, function(i, set) {
                                    if (set.states.checked) { set_ids.push(set.set_id); }
                                });
                                if (!set_ids.length) { return false; }

                                self.states.is_locked = true;

                                var data = getSubmitData();
                                data.set_ids = set_ids;

                                $.post(that.urls["add_to_sets"], data, "json")
                                    .fail( function() {
                                        self.states.is_locked = false;
                                    }).done( function() {
                                    $.wa_shop_products.router.reload().done( function() {
                                        dialog.close();
                                    });
                                });
                            }
                        },
                        created: function () {
                            $section.css("visibility", "");
                        },
                        mounted: function() {
                            var self = this;

                            self.$nextTick( function() {
                                dialog.resize();
                                vue_ready.resolve(dialog);
                                $(self.$el).find("input.js-autofocus").trigger("focus");
                            });
                        }
                    }).mount($section[0]);
                }

                function formatItem(item) {
                    if (item.is_group && !item.sets) {
                        item.sets = [];
                    }

                    if (!item.states) {
                        item.states = {
                            is_wanted: false,
                            is_wanted_inside: false,
                            display_item: false
                        }
                        if (item.is_group) {
                            item.states.expanded = true;
                        } else {
                            item.states.checked = false;
                        }
                    }

                    return item;
                }

                function getObject(items) {
                    var result = {
                        sets: {},
                        groups: {}
                    };

                    setItem(items);

                    formatItems(result.sets);
                    formatItems(result.groups);

                    return result;

                    function setItem(items) {
                        $.each(items, function(i, item) {
                            if (item.is_group) {
                                result.groups[item.group_id] = item;
                                if (item.sets.length) {
                                    setItem(item.sets);
                                }
                            } else {
                                result.sets[item.set_id] = item;
                            }
                        });
                    }

                    function formatItems(items) {
                        $.each(items, function(i, item) {
                            formatItem(item);
                        });
                        return items;
                    }
                }
            }

            function excludeFromSetsDialog(html, action) {
                var ready = $.Deferred(),
                    vue_ready = $.Deferred();

                return $.waDialog({
                    html: html,
                    options: {
                        ready: ready.promise(),
                        vue_ready: vue_ready.promise(),
                        initDialog: initDialog
                    },
                    onOpen: function($dialog, dialog) {
                        ready.resolve($dialog, dialog);
                    }
                });

                function initDialog($dialog, dialog, options) {
                    var $section = $dialog.find(".js-vue-section");

                    that.updateRootVariables(options);

                    var object = getObject(options.items),
                        sets_object = object.sets,
                        groups_object = object.groups;

                    that.vue_components["component-set-search"]["template"] = that.components["component-set-search"];
                    var vue_model = Vue.createApp({
                        data() {
                            return {
                                items_array: options.items,
                                sets: sets_object,
                                groups: groups_object,
                                search_string: "",
                                states: {
                                    is_locked: false,
                                    show_form: false
                                }
                            }
                        },
                        delimiters: ['{ { ', ' } }'],
                        components: {
                            "component-set-search": that.vue_components["component-set-search"],
                            "component-set-item-group": {
                                name: "component-set-item-group",
                                props: ["items"],
                                template: that.components["component-set-item-group"],
                                delimiters: ['{ { ', ' } }'],
                                components: {
                                    "component-set-item": {
                                        props: ["item"],
                                        data: function() {
                                            return {
                                                states: {
                                                    show_form: false
                                                }
                                            };
                                        },
                                        template: that.components["component-set-item"],
                                        delimiters: ['{ { ', ' } }'],
                                        computed: {
                                            item_class: function() {
                                                const self = this;
                                                var result = [];

                                                if (self.item.states.is_wanted) {
                                                    result.push("is-wanted");
                                                }

                                                return result.join(" ");
                                            }
                                        }
                                    }
                                }
                            }
                        },
                        computed: {
                            items: function() {
                                const self = this;

                                checkItems(self.items_array);

                                return self.items_array;

                                /**
                                 * @param {Object|Array} items
                                 * @return {Boolean}
                                 * */
                                function checkItems(items) {
                                    var result = false;

                                    $.each(items, function(i, item) {
                                        var is_wanted = checkItem(item);
                                        if (is_wanted) {
                                            result = true;
                                        }
                                    });

                                    return result;
                                }

                                /**
                                 * @param {Object} item
                                 * @return {Boolean}
                                 * */
                                function checkItem(item) {
                                    var is_wanted = (item.name.toLowerCase().indexOf( self.search_string.toLowerCase() ) >= 0),
                                        is_wanted_inside = (item.is_group && item.sets.length ? checkItems(item.sets) : false),
                                        is_display = true;

                                    if (self.search_string === "") {
                                        is_wanted = is_wanted_inside = false;
                                    } else {
                                        is_display = !!(is_wanted || is_wanted_inside);
                                    }

                                    item.states.is_wanted = is_wanted;
                                    item.states.is_wanted_inside = is_wanted_inside;
                                    item.states.display_item = is_display;

                                    return is_display;
                                }
                            },
                        },
                        methods: {
                            save: function() {
                                const self = this;

                                if (self.states.is_locked) { return false; }

                                var set_ids = [];
                                $.each(self.sets, function(i, set) {
                                    if (set.states.checked) { set_ids.push(set.set_id); }
                                });
                                if (!set_ids.length) { return false; }

                                self.states.is_locked = true;

                                var data = getSubmitData();
                                data.set_ids = set_ids;

                                $.post(that.urls["exclude_from_sets"], data, "json")
                                    .fail( function() {
                                        self.states.is_locked = false;
                                    }).done( function() {
                                    $.wa_shop_products.router.reload().done( function() {
                                        dialog.close();
                                    });
                                });
                            }
                        },
                        created: function () {
                            $section.css("visibility", "");
                        },
                        mounted: function() {
                            var self = this;

                            self.$nextTick( function() {
                                dialog.resize();
                                vue_ready.resolve(dialog);
                                $(self.$el).find("input.js-autofocus").trigger("focus");
                            });
                        }
                    }).mount($section[0]);
                }

                function formatItem(item) {
                    if (item.is_group && !item.sets) {
                        item.sets = [];
                    }

                    if (!item.states) {
                        item.states = {
                            is_wanted: false,
                            is_wanted_inside: false,
                            display_item: false
                        }
                        if (item.is_group) {
                            item.states.expanded = true;
                        } else {
                            item.states.checked = false;
                        }
                    }

                    return item;
                }

                function getObject(items) {
                    var result = {
                        sets: {},
                        groups: {}
                    };

                    setItem(items);

                    formatItems(result.sets);
                    formatItems(result.groups);

                    return result;

                    function setItem(items) {
                        $.each(items, function(i, item) {
                            if (item.is_group) {
                                result.groups[item.group_id] = item;
                                if (item.sets.length) {
                                    setItem(item.sets);
                                }
                            } else {
                                result.sets[item.set_id] = item;
                            }
                        });
                    }

                    function formatItems(items) {
                        $.each(items, function(i, item) {
                            formatItem(item);
                        });
                        return items;
                    }
                }
            }

            function assignTagsDialog(html, action) {
                var ready = $.Deferred(),
                    vue_ready = $.Deferred();

                return $.waDialog({
                    html: html,
                    options: {
                        ready: ready.promise(),
                        vue_ready: vue_ready.promise(),
                        initDialog: initDialog
                    },
                    onOpen: function($dialog, dialog) {
                        ready.resolve($dialog, dialog);
                    }
                });

                function initDialog($dialog, dialog, options) {
                    var $section = $dialog.find(".js-vue-section");

                    that.updateRootVariables(options);

                    var vue_model = Vue.createApp({
                        data() {
                            return {
                                tags: [],
                                tags_keys: {},
                                states: {
                                    is_locked: false
                                }
                            }
                        },
                        delimiters: ['{ { ', ' } }'],
                        components: {
                        },
                        methods: {
                            initAutocomplete: function($input) {
                                const self = this;

                                $input.on("keypress", function(event) {
                                    var is_enter = (event.keyCode === 13),
                                        value = $input.val();

                                    if (is_enter) {
                                        if (!value) { return false; }
                                        self.addItem({ name: value, value: value });
                                        $input.val("").trigger("blur");
                                    }
                                });

                                $input.autocomplete({
                                    source: function (request, response) {
                                        var search_string = request.term,
                                            data = { term: search_string, type: "" };

                                        self.states.is_loading = true;

                                        $.post(that.urls["autocomplete_tags"], data, function(response_data) {
                                            var result = [];
                                            if (response_data.length) {
                                                $.each(response_data, function(i, tag) {
                                                    var is_wanted = !!(search_string === "" || tag.label.toLowerCase().indexOf( search_string.toLowerCase() ) >= 0);
                                                    if (is_wanted) {
                                                        result.push({
                                                            name: tag.label,
                                                            value: tag.value
                                                        });
                                                    }
                                                });
                                            }

                                            result.unshift({
                                                description: that.locales["products_add_tag"].replace("%s", request.term),
                                                name: request.term,
                                                value: request.term
                                            });
                                            response(result);
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
                                        if (ui.item.value) {
                                            self.addItem(ui.item);
                                            $input.val("");
                                        }
                                        return false;
                                    },
                                    focus: function() { return false; }
                                }).data("ui-autocomplete")._renderItem = function( ul, item ) {
                                    var name = (item.description || item.name);
                                    return $("<li />").addClass("ui-menu-item-html").append("<div>"+name+"</div>").appendTo(ul);
                                };

                                $dialog.on("dialog-closed", function() {
                                    $input.autocomplete("destroy");
                                });
                            },
                            addItem: function(tag) {
                                const self = this;
                                if (!self.tags_keys[tag.value]) {
                                    self.tags.push(tag);
                                    self.tags_keys[tag.value] = tag;
                                    dialog.resize();
                                }
                            },
                            removeTag: function(tag) {
                                const self = this;
                                self.tags.splice(self.tags.indexOf(tag), 1);
                                delete self.tags_keys[tag.value];
                                dialog.resize();
                            },

                            save: function() {
                                const self = this;

                                if (self.states.is_locked) { return false; }

                                var tags = self.tags.map(tag => tag.value);
                                if (!tags.length) { return false; }

                                self.states.is_locked = true;

                                var data = getSubmitData();
                                data.tags = tags;

                                $.post(that.urls["assign_tags"], data, "json")
                                    .fail( function() {
                                        self.states.is_locked = false;
                                    }).done( function() {
                                    $.wa_shop_products.router.reload().done( function() {
                                        dialog.close();
                                    });
                                });
                            }
                        },
                        created: function () {
                            $section.css("visibility", "");
                        },
                        mounted: function() {
                            var self = this,
                                $wrapper = $(self.$el.parentElement);

                            var $autocomplete = $wrapper.find('.js-autocomplete');
                            if ($autocomplete.length) {
                                self.initAutocomplete($autocomplete);
                            }

                            self.$nextTick( function() {
                                dialog.resize();
                                vue_ready.resolve(dialog);
                                $(self.$el).find("input.js-autofocus").trigger("focus");
                            });
                        }
                    }).mount($section[0]);
                }
            }

            function removeTagsDialog(html, action) {
                var ready = $.Deferred(),
                    vue_ready = $.Deferred();

                return $.waDialog({
                    html: html,
                    options: {
                        ready: ready.promise(),
                        vue_ready: vue_ready.promise(),
                        initDialog: initDialog
                    },
                    onOpen: function($dialog, dialog) {
                        ready.resolve($dialog, dialog);
                    }
                });

                function initDialog($dialog, dialog, options) {
                    var $section = $dialog.find(".js-vue-section");

                    that.updateRootVariables(options);

                    var vue_model = Vue.createApp({
                        data() {
                            return {
                                search_string: "",
                                tags_array: formatTags(options.tags),
                                states: {
                                    is_locked: false
                                }
                            }
                        },
                        delimiters: ['{ { ', ' } }'],
                        components: {
                        },
                        computed: {
                            tags: function() {
                                const self = this;
                                $.each(self.tags_array, function(i, tag) {
                                    tag.states.is_wanted = !!(self.search_string === "" || tag.name.toLowerCase().indexOf( self.search_string.toLowerCase() ) >= 0);
                                });
                                return self.tags_array;
                            }
                        },
                        methods: {
                            toggleTag: function(tag, show) {
                                tag.states.is_active = show;
                            },

                            save: function() {
                                const self = this;

                                if (self.states.is_locked) { return false; }

                                var tags = [];
                                $.each(self.tags, function(i, tag) {
                                    if (tag.states.is_active) { tags.push(tag.value); }
                                });
                                if (!tags.length) { return false; }

                                self.states.is_locked = true;

                                var data = getSubmitData();
                                data.tag_ids = tags;

                                $.post(that.urls["remove_tags"], data, "json")
                                    .fail( function() {
                                        self.states.is_locked = false;
                                    }).done( function() {
                                    $.wa_shop_products.router.reload().done( function() {
                                        dialog.close();
                                    });
                                });
                            }
                        },
                        created: function () {
                            $section.css("visibility", "");
                        },
                        mounted: function() {
                            var self = this;

                            self.$nextTick( function() {
                                dialog.resize();
                                vue_ready.resolve(dialog);
                                $(self.$el).find("input.js-autofocus").trigger("focus");
                            });
                        }
                    }).mount($section[0]);

                    function formatTags(tags) {
                        $.each(tags, function(i, tag) {
                            tag.states = {
                                is_wanted: false,
                                is_active: false
                            }
                        });
                        return tags;
                    }
                }
            }


            function setBadgeDialog(html, action) {
                var ready = $.Deferred(),
                    vue_ready = $.Deferred();

                return $.waDialog({
                    html: html,
                    options: {
                        ready: ready.promise(),
                        vue_ready: vue_ready.promise(),
                        initDialog: initDialog
                    },
                    onOpen: function($dialog, dialog) {
                        ready.resolve($dialog, dialog);
                    }
                });

                function initDialog($dialog, dialog, options) {
                    var $section = $dialog.find(".js-vue-section");

                    that.updateRootVariables(options);

                    const app = Vue.createApp({
                        data() {
                            return {
                                active_badge: null,
                                badges: options.badges,
                                states: {
                                    is_locked: false
                                }
                            }
                        },
                        delimiters: ['{ { ', ' } }'],
                        components: { "component-textarea": that.vue_components["component-textarea"] },
                        computed: {
                            disabled: function() {
                                return !!(this.states.is_locked || this.active_badge === null);
                            }
                        },
                        methods: {
                            setBadge: function(badge) {
                                this.active_badge = badge;
                            },
                            save: function() {
                                const self = this;

                                if (self.states.is_locked) { return false; }

                                self.states.is_locked = true;

                                var data = getSubmitData(),
                                    href = that.urls["products_set_badge"];

                                if (self.active_badge.id !== "remove") {
                                    data.code = self.active_badge.value;
                                } else {
                                    href = that.urls["products_delete_badge"];
                                }

                                $.post(href, data, "json")
                                    .fail( function() {
                                        self.states.is_locked = false;
                                    }).done( function() {
                                    $.wa_shop_products.router.reload().done( function() {
                                        dialog.close();
                                    });
                                });
                            }
                        },
                        created: function () {
                            $section.css("visibility", "");
                        },
                        mounted: function() {
                            var self = this;

                            self.$nextTick( function() {
                                dialog.resize();
                                vue_ready.resolve(dialog);
                            });
                        }
                    });

                    app.mount($section[0]);
                }
            }

            function setPublicationDialog(html, action) {
                var ready = $.Deferred(),
                    vue_ready = $.Deferred();

                return $.waDialog({
                    html: html,
                    options: {
                        ready: ready.promise(),
                        vue_ready: vue_ready.promise(),
                        initDialog: initDialog
                    },
                    onOpen: function($dialog, dialog) {
                        ready.resolve($dialog, dialog);
                    }
                });

                function initDialog($dialog, dialog, options) {
                    var $section = $dialog.find(".js-vue-section");

                    that.updateRootVariables(options);

                    Vue.createApp({
                        data() {
                            return {
                                active_option: null,
                                options: options.options,
                                states: {
                                    is_locked: false
                                }
                            }
                        },
                        delimiters: ['{ { ', ' } }'],
                        components: {
                            "component-radio": that.vue_components["component-radio"],
                            "component-checkbox": that.vue_components["component-checkbox"]
                        },
                        computed: {
                            disabled: function() {
                                const self = this;
                                return !!(self.states.is_locked || self.active_option === null);
                            }
                        },
                        methods: {
                            save: function() {
                                const self = this;

                                if (self.states.is_locked) { return false; }

                                self.states.is_locked = true;

                                var data = getSubmitData();

                                data.status = self.active_option.value;
                                if (self.active_option.update_skus) {
                                    data.update_skus = "1";
                                }

                                $.post(that.urls["products_set_publication"], data, "json")
                                    .fail( function() {
                                        self.states.is_locked = false;
                                    }).done( function() {
                                    $.wa_shop_products.router.reload().done( function() {
                                        dialog.close();
                                    });
                                });
                            }
                        },
                        created: function () {
                            $section.css("visibility", "");
                        },
                        mounted: function() {
                            var self = this;

                            self.$nextTick( function() {
                                dialog.resize();
                                vue_ready.resolve(dialog);
                            });
                        }
                    }).mount($section[0]);
                }
            }

            function setTypeDialog(html, action) {
                var ready = $.Deferred(),
                    vue_ready = $.Deferred();

                return $.waDialog({
                    html: html,
                    options: {
                        ready: ready.promise(),
                        vue_ready: vue_ready.promise(),
                        initDialog: initDialog
                    },
                    onOpen: function($dialog, dialog) {
                        ready.resolve($dialog, dialog);
                    }
                });

                function initDialog($dialog, dialog, options) {
                    var $section = $dialog.find(".js-vue-section");

                    that.updateRootVariables(options);

                    Vue.createApp({
                        data() {
                            return {
                                type: "",
                                states: {
                                    is_locked: false
                                }
                            }
                        },
                        delimiters: ['{ { ', ' } }'],
                        components: {
                            "component-radio": that.vue_components["component-radio"]
                        },
                        methods: {
                            save: function() {
                                const self = this;

                                if (self.states.is_locked) { return false; }

                                self.states.is_locked = true;

                                var data = getSubmitData();
                                data.type_id = self.type;

                                $.post(that.urls["products_set_type"], data, "json")
                                    .fail( function() {
                                        self.states.is_locked = false;
                                    }).done( function() {
                                    $.wa_shop_products.router.reload().done( function() {
                                        dialog.close();
                                    });
                                });
                            }
                        },
                        created: function () {
                            $section.css("visibility", "");
                        },
                        mounted: function() {
                            var self = this;

                            self.$nextTick( function() {
                                dialog.resize();
                                vue_ready.resolve(dialog);
                            });
                        }
                    }).mount($section[0]);
                }
            }

            function duplicateProductsDialog(html) {
                var ready = $.Deferred(),
                    vue_ready = $.Deferred();

                return $.waDialog({
                    html: html,
                    options: {
                        ready: ready.promise(),
                        vue_ready: vue_ready.promise(),
                        initDialog: initDialog
                    },
                    onOpen: function($dialog, dialog) {
                        ready.resolve($dialog, dialog);
                    }
                });

                function initDialog($dialog, dialog) {
                    var $section = $dialog.find(".js-vue-section");

                    var vue_model = Vue.createApp({
                        delimiters: ['{ { ', ' } }'],
                        methods: {
                            close: function() {
                                $.wa_shop_products.router.reload().done( function() {
                                    dialog.close();
                                });
                            }
                        },
                        created: function () {
                            $section.css("visibility", "");
                        },
                        mounted: function() {
                            var self = this;

                            self.$nextTick( function() {
                                dialog.resize();
                                vue_ready.resolve(dialog);
                            });
                        }
                    }).mount($section[0]);
                }
            }

            function removeProductsDialog(html, action) {
                var ready = $.Deferred(),
                    vue_ready = $.Deferred();

                return $.waDialog({
                    html: html,
                    options: {
                        ready: ready.promise(),
                        vue_ready: vue_ready.promise(),
                        initDialog: initDialog
                    },
                    onOpen: function($dialog, dialog) {
                        ready.resolve($dialog, dialog);
                    }
                });

                function initDialog($dialog, dialog, options) {
                    var $section = $dialog.find(".js-vue-section");

                    that.updateRootVariables(options);

                    Vue.createApp({
                        data() {
                            return {
                                states: {
                                    is_locked: false
                                }
                            }
                        },
                        delimiters: ['{ { ', ' } }'],
                        methods: {
                            save: function() {
                                const self = this;

                                if (self.states.is_locked) { return false; }

                                self.states.is_locked = true;

                                var data = getSubmitData();

                                $.post(that.urls["delete_products"], data, "json")
                                    .fail( function() {
                                        self.states.is_locked = false;
                                    }).done( function() {
                                    $.wa_shop_products.router.reload().done( function() {
                                        dialog.close();
                                    });
                                });
                            }
                        },
                        created: function () {
                            $section.css("visibility", "");
                        },
                        mounted: function() {
                            var self = this;

                            self.$nextTick( function() {
                                dialog.resize();
                                vue_ready.resolve(dialog);
                            });
                        }
                    }).mount($section[0]);
                }
            }

            function associatePromoDialog(html, action) {
                var ready = $.Deferred(),
                    vue_ready = $.Deferred();

                return $.waDialog({
                    html: html,
                    options: {
                        ready: ready.promise(),
                        vue_ready: vue_ready.promise(),
                        initDialog: initDialog
                    },
                    onOpen: function($dialog, dialog) {
                        ready.resolve($dialog, dialog);
                    }
                });

                function initDialog($dialog, dialog, options) {
                    var $section = $dialog.find(".js-vue-section");

                    var vue_model = Vue.createApp({
                        data() {
                            return {
                                promo: "",
                                states: {
                                    is_locked: false
                                }
                            }
                        },
                        delimiters: ['{ { ', ' } }'],
                        components: { "component-radio": that.vue_components["component-radio"] },
                        methods: {
                            save: function() {
                                const self = this;

                                if (self.states.is_locked) { return false; }
                                self.states.is_locked = true;

                                var promo_id = self.promo ? self.promo : "create";
                                location.href = options.redirect_pattern.replace("%id%", promo_id);
                            }
                        },
                        created: function () {
                            $section.css("visibility", "");
                        },
                        mounted: function() {
                            var self = this;

                            self.$nextTick( function() {
                                dialog.resize();
                                vue_ready.resolve(dialog);
                            });
                        }
                    }).mount($section[0]);
                }
            }

            function getSubmitData() {
                var result = {
                    product_ids: product_ids
                }

                if (is_all_products) {
                    result.presentation_id = that.presentation.id;
                }

                return result;
            }
        };

        Page.prototype.clearTableScrollPosition = function () {
            sessionStorage.removeItem("shop_products_table_scroll_top");
        };

        return Page;

        function formatProducts(products) {
            $.each(products, function(i, product) {
                var sku_count = 0;
                $.each(product.skus, function(i, sku) {
                    $.each(sku.modifications, function(i, sku_mod) {
                        if (product.sku_id === sku_mod.id) {
                            product.sku = sku_mod;
                        }
                        sku_count += 1;
                    });
                });

                product.status = (typeof product.status === "string" ? product.status : "" + product.status);
                product.sku_count = sku_count;
                product.states = {
                    selected: false,
                    product_key: '['+product.id+']'
                };
            });

            return products;
        }

        function formatPresentation(presentation) {
            presentation.states = {
                is_moving: false,
                move_locked: false,
                copy_locked: false,
                remove_locked: false,
                rewrite_locked: false,
                show_rename_form: false
            };

            return presentation;
        }

        function formatFilter(filter) {
            filter.states = {
                is_moving: false,
                move_locked: false,
                copy_locked: false,
                remove_locked: false,
                rewrite_locked: false,
                show_rename_form: false
            };

            return filter;
        }
    })($);

    $.wa_shop_products.init.initProductsListPage = function(options) {
        return new Page(options);
    };

    function sessionPresentationName(value) {
        var data_key = "shop_products_presentation_template_name",
            result = null;

        if (typeof value !== "undefined") {
            if (typeof value !== "string") {
                sessionStorage.removeItem(data_key);
            } else {
                result = sessionStorage.setItem(data_key, value);
            }
        } else {
            result = sessionStorage.getItem(data_key);
        }

        return result;
    }

})(jQuery);
