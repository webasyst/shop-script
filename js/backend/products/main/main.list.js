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
            that.products_selection   = "visible_products";

            that.model_data = {};
            that.states = {
                $animation: null,
                mass_actions_pinned: false,
                mass_actions_storage_key: "shop_products/mass_actions_pinned"
            };
            that.keys = {
                table: 0,
                mass_actions: 0
            };
            that.broadcast = that.initBroadcast();

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

                $.each(active_rules, function(i, group) {
                    $.each(group.rules, function(i, rule) {
                        switch (group.type) {
                            case "categories":
                                var category = options.categories_object[rule.rule_params];
                                category.is_locked = true;
                                break;
                            case "sets":
                                var set = options.sets_object[rule.rule_params];
                                set.is_locked = true;
                                break;
                            case "types":
                                var type_search = options.types.filter( type => (type.id === rule.rule_params));
                                if (type_search.length) {
                                    type_search[0].is_locked = true;
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
                    var column_info = that.columns[column.column_type];
                    if (column_info.width > 0) { column.width = column_info.width; }

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

        Page.prototype.init = function() {
            var that = this;

            // Режим отображения массовых действий
            var mass_storage = localStorage.getItem(that.states.mass_actions_storage_key);
            if (mass_storage) { that.states.mass_actions_pinned = true; }

            $.each(that.tooltips, function(i, tooltip) {
                $.wa.new.Tooltip(tooltip);
            });

            that.updatePageURL();

            console.log( that );
        };

        Page.prototype.initVue = function() {
            var that = this;

            var $vue_section = that.$wrapper.find("#js-vue-section");

            /**
             * @description Switch
             * */
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

            /**
             * @description Checkbox
             * */
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

            /**
             * @description Radio
             * */
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

            /**
             * @description Dropdown
             * */
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

            /**
             * @description Date Picker
             * */
            Vue.component("component-date-picker", {
                props: ["value", "readonly", "field_class"],
                data: function() {
                    var self = this;
                    self.readonly = (typeof self.readonly === "boolean" ? self.readonly : false);
                    return {};
                },
                template: that.components["component-date-picker"],
                delimiters: ['{ { ', ' } }'],
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

                        if (self.value) {
                            var date = formatDate(self.value);
                            $field.datepicker( "setDate", date);
                        }

                        $field.on("change", function() {
                            var field_value = $field.val();
                            if (!field_value) { $alt_field.val(""); }
                            var value = $alt_field.val();
                            self.$emit("input", value);
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
            });

            /**
             * @description Color Picker
             * */
            Vue.component("component-color-picker", {
                props: ["value", "readonly", "disabled", "field_class"],
                data: function() {
                    var self = this;
                    self.readonly = (typeof self.readonly === "boolean" ? self.readonly : false);
                    self.disabled = (typeof self.disabled === "boolean" ? self.disabled : false);
                    if (self.value) { self.value = self.value.toLowerCase() }
                    return {
                        is_ready: true,
                        extended: false,
                        start_value: self.value
                    };
                },
                template: that.components["component-color-picker"],
                delimiters: ['{ { ', ' } }'],
                computed: {
                    is_changed: function() {
                        var self = this;
                        return (self.start_value !== self.value);
                    }
                },
                methods: {
                    onFocus: function() {
                        var self = this;
                        if (!self.extended) {
                            self.toggle(true);
                        }
                    },
                    onInput: function() {
                        var self = this;
                        self.setColor();
                        self.$emit("input", self.value);
                    },
                    onChange: function() {
                        var self = this;
                        self.change();
                    },
                    toggle: function(show) {
                        var self = this;

                        if (self.readonly || self.disabled) { return false; }

                        show = (typeof show === "boolean" ? show : !self.extended);
                        self.extended = show;

                        if (show) {
                            self.$document.on("mousedown", self.watchOff);
                            self.setColor();
                            self.$emit("focus");

                        } else {
                            self.$document.off("mousedown", self.watchOff);
                            self.change();
                        }
                    },
                    setColor: function() {
                        var self = this,
                            color = (self.value ? self.value : "#000000");

                        self.is_ready = false;
                        self.farbtastic.setColor(color);
                        self.is_ready = true;
                    },
                    watchOff: function(event) {
                        var self = this,
                            is_target = self.$wrapper[0].contains(event.target);
                        if (!is_target) {
                            if (self.extended) { self.toggle(false); }
                            self.$document.off("mousedown", self.watchOff);
                        }
                    },
                    change: function() {
                        var self = this;
                        if (self.is_changed) {
                            self.$emit("change", self.value);
                            // self.start_value = self.value;
                        }
                    }
                },
                mounted: function() {
                    var self = this;

                    self.$document = $(document);
                    self.$wrapper = $(self.$el);

                    var $picker = self.$wrapper.find(".js-color-picker");

                    self.farbtastic = $.farbtastic($picker, onChangeColor);

                    function onChangeColor(color) {
                        if (self.is_ready && self.value !== color) {
                            self.value = color;
                            self.$emit("input", self.value);
                        }
                    }
                }
            });

            /**
             * @description Textarea
             * */
            Vue.component("component-textarea", {
                props: ["value", "placeholder", "readonly", "disabled", "cancel", "focus"],
                data: function() {
                    var self = this;
                    self.focus = (typeof self.focus === "boolean" ? self.focus : false);
                    self.cancel = (typeof self.cancel === "boolean" ? self.cancel : false);
                    self.readonly = (typeof self.readonly === "boolean" ? self.readonly : false);
                    self.disabled = (typeof self.disabled === "boolean" ? self.disabled : false);
                    return {
                        offset: 0,
                        $textarea: null,
                        start_value: null
                     };
                },
                template: '<textarea v-bind:placeholder="placeholder" v-bind:value="value" v-bind:readonly="readonly" v-bind:disabled="disabled" v-on:focus="onFocus" v-on:input="onInput" v-on:keydown.esc="onEsc" v-on:blur="onBlur"></textarea>',
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
                        self.$emit("focus");
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
                        var x = self.$el.offsetHeight;
                        var scroll_h = self.$el.scrollHeight + self.offset;
                        $textarea
                            .css("min-height", scroll_h + "px")
                            .css("overflow", "");
                    }
                },
                mounted: function() {
                    var self = this,
                        $textarea = self.$textarea = $(self.$el);

                    self.offset = $textarea[0].offsetHeight - $textarea[0].clientHeight;
                    self.update();

                    self.$emit("ready", self.$el.value);

                    if (self.focus) { $textarea.trigger("focus"); }
                }
            });

            /**
             * @description Input
             * */
            Vue.component("component-input", {
                props: ["value", "placeholder", "readonly", "disabled", "required", "cancel", "focus", "validate", "fraction_size", "format"],
                data: function() {
                    var self = this;
                    self.focus = (typeof self.focus === "boolean" ? self.focus : false);
                    self.cancel = (typeof self.cancel === "boolean" ? self.cancel : false);
                    self.required = (typeof self.required === "boolean" ? self.required : false);
                    self.readonly = (typeof self.readonly === "boolean" ? self.readonly : false);
                    self.disabled = (typeof self.disabled === "boolean" ? self.disabled : false);
                    self.format = (typeof self.format === "string" ? self.format : "text");
                    return {
                        $input: null,
                        start_value: null
                     };
                },
                template: '<input type="text" v-bind:class="field_class" v-bind:placeholder="placeholder" v-bind:value="value" v-bind:readonly="readonly" v-bind:disabled="disabled" v-on:focus="onFocus" v-on:input="onInput" v-on:keydown.esc="onEsc" v-on:keydown.enter="onEnter" v-on:blur="onBlur">',
                delimiters: ['{ { ', ' } }'],
                computed: {
                    field_class: function() {
                        var self = this,
                            result = [];

                        if (self.required && !self.value.length) {
                            result.push("wa-error-field");
                        }

                        if (self.format === "number" || self.format === "number-negative") {
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

                            $event.target.value = self.value = $.wa.validate(self.validate, $event.target.value, options);
                        }

                        self.$emit('input', $event.target.value);
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
                            if (self.cancel) { self.$emit("cancel"); }
                        } else {
                            self.onChange(value);
                        }

                        self.$emit("blur", value);
                    },
                    onEsc: function($event) {
                        var self = this;
                        if (self.cancel) {
                            $event.target.value = self.value = self.start_value;
                            self.$emit('input', self.value);
                            self.$input.trigger("blur");
                        }
                    },
                    onEnter: function($event) {
                        var self = this;
                        self.$input.trigger("blur");
                    },
                    onChange: function(value) {
                        var self = this;
                        if (self.required && self.value === "") {
                            self.value = self.start_value;
                            self.$input.val(self.value);
                            self.$emit('input', self.value);
                        } else {
                            self.$emit("change", value);
                        }
                    }
                },
                mounted: function() {
                    var self = this,
                        $input = self.$input = $(self.$el);

                    self.$emit("ready", self.$el.value);

                    if (self.focus) {
                        $input.trigger("focus");
                    }
                }
            });

            Vue.component("component-product-column-tags", {
                props: ["product", "column", "column_info", "column_data", "ignore_limit"],
                data: function() {
                    var self = this,
                        tags_states = {},
                        settings = (self.column.data && self.column.data.settings ? self.column.data.settings : {});

                    self.ignore_limit = (typeof self.ignore_limit === "boolean" ? self.ignore_limit : false);

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

                            new Vue({
                                el: $section[0],
                                data: {
                                    product: self.product,
                                    column: self.column,
                                    column_info: self.column_info,
                                    column_data: self.column_data
                                },
                                delimiters: ['{ { ', ' } }'],
                                created: function () {
                                    $section.css("visibility", "");
                                },
                                mounted: function () {
                                    var self = this;
                                    dialog.resize();
                                }
                            });
                        }
                    }
                }
            });

            /**
             * Используется в различных частях (раздед, диалоги)
             * */
            Vue.component("component-table-filters-search", {
                props: ["value", "search_icon", "placeholder"],
                data: function() {
                    let self = this;
                    self.search_icon = (typeof self.search_icon === "boolean" ? self.search_icon : false);
                    return {};
                },
                template: that.components["component-table-filters-search"],
                delimiters: ['{ { ', ' } }'],
                methods: {
                    onInput: function() {
                        let self = this;
                        self.$emit("input", self.value);
                        self.$emit("search_input", self.value);
                    },
                    onChange: function() {
                        let self = this;
                        self.$emit("input", self.value);
                        self.$emit("search_change", self.value);
                    },
                    revert: function() {
                        let self = this;
                        self.value = "";
                        self.onChange();
                    }
                }
            });

            return new Vue({
                el: $vue_section[0],
                data: {
                    paging       : that.paging,
                    products     : that.products,
                    presentation : that.presentation,
                    states       : that.states,
                    keys         : that.keys
                },
                components: {
                    "component-dropdown-presentations": {
                        props: [],
                        data: function() {
                            var self = this;
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
                                props: [],
                                data: function() {
                                    var self = this;
                                    return {
                                        name: "",
                                        states: {
                                            locked: false
                                        }
                                    }
                                },
                                template: that.components["component-presentation-add-form"],
                                delimiters: ['{ { ', ' } }'],
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
                                data: function() {
                                    var self = this;
                                    return {
                                        name: self.presentation.name,
                                        states: {
                                            locked: false
                                        }
                                    }
                                },
                                template: that.components["component-presentation-rename-form"],
                                delimiters: ['{ { ', ' } }'],
                                computed: {},
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
                                let self = this,
                                    result = null;

                                var session_name = sessionPresentationName();
                                if (session_name && !that.presentation.states.is_changed) {
                                    result = session_name;
                                    sessionPresentationName(null);
                                }

                                return result;
                            },
                            presentations_object: function() {
                                var self = this;
                                return $.wa.construct(self.presentations, "id");
                            }
                        },
                        methods: {
                            onCopyUrl: function(event, presentation) {
                                const self = this;
                                var url = event.currentTarget.href;
                                var dummy = document.createElement('input');
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
                                var self = this;
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
                                        onOpen: function($dialog, dialog) {
                                            $dialog.on("click", ".js-delete-button", function(event) {
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

                                //

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

                                    if (over_index !== move_index) {
                                        presentations.splice(move_index, 1);

                                        over_index = presentations.indexOf(presentation);
                                        var new_index = over_index + (before ? 0 : 1);

                                        presentations.splice(new_index, 0, drag_data.move_presentation);
                                        is_change = true;
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

                                //

                                function getPresentation(presentation_id) {
                                    var presentations_object = self.presentations_object;
                                    return (presentations_object[presentation_id] ? presentations_object[presentation_id] : null);
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
                        props: ["value"],
                        template: that.components["component-view-toggle"],
                        delimiters: ['{ { ', ' } }'],
                        mounted: function() {
                            var self = this;
                            $(self.$el).waToggle({
                                change: function(event, target) {
                                    var value = $(target).data("id");
                                    self.$emit("input", value);
                                    self.$emit("change", value);
                                }
                            });
                        }
                    },
                    "component-paging": {
                        props: ["value", "count"],
                        template: that.components["component-paging"],
                        data: function() {
                            var self = this,
                                page = (self.value > 0 && self.value <= self.count ? self.value : 1);

                            return { page: page };
                        },
                        delimiters: ['{ { ', ' } }'],
                        methods: {
                            set: function(page) {
                                var self = this;

                                if (page > 0 && page <= self.count && self.page !== page) {
                                    self.$emit("input", page);
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
                        },
                        mounted: function() {
                            var self = this;
                        }
                    },
                    "component-paging-dropdown": {
                        props: ["value"],
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
                                return option.value === parseInt(self.value);
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

                                    self.$emit("input", option_value);
                                    self.$emit("change", option_value);
                                }
                            });
                        }
                    },
                    "component-table-filters": {
                        props: [],
                        data: function() {
                            var self = this;

                            var search_rule = getSearchRule();

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
                        components: {
                            "component-table-product-search": {
                                props: ["value", "placeholder"],
                                    data: function() {
                                    let self = this;
                                    return {
                                        states: {
                                            is_loading: false,
                                            show_reset: (self.value !== "")
                                        }
                                    };
                                },
                                template: that.components["component-table-product-search"],
                                delimiters: ['{ { ', ' } }'],
                                methods: {
                                    onInput: function() {
                                        let self = this;
                                        self.$emit("input", self.value);
                                        self.$emit("search_input", self.value);
                                    },
                                    onEnter: function() {
                                        let self = this;
                                        self.$emit("input", self.value);
                                        self.$emit("search_change", self.value);
                                    },
                                    revert: function() {
                                        let self = this;
                                        self.value = "";
                                        self.onEnter();
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
                                            focus: function() { return false; }
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
                                        const self = this;
                                        var url = $.wa_shop_products.section_url + product.id + "/";
                                        $.wa_shop_products.router.load(url);
                                        that.animate(true);
                                    }
                                },
                                mounted: function() {
                                    const self = this;
                                    var $wrapper = $(self.$el),
                                        $input = $wrapper.find('.js-autocomplete');

                                    self.initAutocomplete($input);

                                    $input.on("keydown", function(event) {
                                        var key = event.keyCode,
                                            is_enter = ( key === 13 );

                                        if (is_enter) { self.onEnter(); }
                                    });
                                }
                            },
                            "component-table-filters-categories-sets-types": {
                                data: function() {
                                    let self = this;
                                    return {
                                        content_type: "category"
                                    };
                                },
                                template: that.components["component-table-filters-categories-sets-types"],
                                delimiters: ['{ { ', ' } }'],
                                components: {
                                    "component-table-filters-categories": {
                                        props: ["value", "placeholders"],
                                        data: function() {
                                            let self = this,
                                                options = $.wa.clone(that.filter_options);

                                            let categories = options.categories,
                                                categories_object = formatCategories(getCategoriesObject(categories));

                                            return {
                                                search_string: "",
                                                categories: categories,
                                                categories_object: categories_object
                                            };

                                            function formatCategories(items) {
                                                $.each(items, function(i, item) {
                                                    item.states = {
                                                        // enabled: false,
                                                        // locked: false,
                                                        is_wanted: false,
                                                        is_wanted_inside: false
                                                    }
                                                });

                                                return items;
                                            }

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
                                        },
                                        template: that.components["component-table-filters-categories"],
                                        delimiters: ['{ { ', ' } }'],
                                        components: {
                                            "component-table-filters-categories-category": {
                                                name: "component-table-filters-categories-category",
                                                props: ["category", "search_string"],
                                                data: function() {
                                                    var self = this;
                                                    return {
                                                        states: {
                                                            is_opened: false
                                                        }
                                                    };
                                                },
                                                template: that.components["component-table-filters-categories-category"],
                                                delimiters: ['{ { ', ' } }'],
                                                computed: {
                                                    display_item: function() {
                                                        const self = this;
                                                        return (self.search_string === "" || self.category.states.is_wanted || self.category.states.is_wanted_inside);
                                                    },
                                                    item_class: function() {
                                                        const self = this;

                                                        var result = [];

                                                        if (self.search_string !== "") {
                                                            if (self.category.states.is_wanted) {
                                                                result.push("is-wanted");
                                                            }
                                                            if (self.category.states.is_wanted_inside) {
                                                                result.push("is-wanted-inside");
                                                            }
                                                        }

                                                        if (self.category.is_locked) {
                                                            result.push("is-locked");
                                                        }

                                                        return result.join(" ");
                                                    }
                                                },
                                                methods: {
                                                    toggle: function() {
                                                        const self = this;
                                                        self.states.is_opened = !self.states.is_opened;
                                                    },
                                                    useCategory: function(category) {
                                                        const self = this;
                                                        self.$emit("use_category", category);
                                                    }
                                                }
                                            }
                                        },
                                        computed: {
                                            items: function() {
                                                const self = this;

                                                checkCategories(self.categories);

                                                return self.categories;

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
                                                            return false;
                                                        }
                                                    });

                                                    return result;
                                                }

                                                /**
                                                 * @param {Object} category
                                                 * @return {Boolean}
                                                 * */
                                                function checkCategory(category) {
                                                    var is_wanted = (self.search_string === "" || category.name.toLowerCase().indexOf( self.search_string.toLowerCase() ) >= 0);

                                                    category.states.is_wanted = is_wanted;
                                                    category.states.is_wanted_inside = false;

                                                    if (category.categories.length) {
                                                        category.states.is_wanted_inside = checkCategories(category.categories);
                                                    }

                                                    return is_wanted;
                                                }
                                            },
                                        },
                                        methods: {
                                            changeItem: function(item) {
                                                if (item.is_locked) { return false; }
                                                const self = this;
                                                item.states.enabled = !item.states.enabled;
                                                self.save(item);
                                            },
                                            save: function(item) {
                                                const self = this;
                                                self.$emit("success", {
                                                    type: "category",
                                                    item: item
                                                });
                                            }
                                        },
                                        mounted: function() {
                                            const self = this;
                                            self.dropbox = $.wa.new.Dropbox({
                                                $wrapper: $(self.$el),
                                                protect: false
                                            });
                                        }
                                    },
                                    "component-table-filters-sets": {
                                        data: function() {
                                            let self = this,
                                                options = $.wa.clone(that.filter_options);

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
                                                            // enabled: false,
                                                            // locked: false,
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
                                            "component-table-filters-sets-set": {
                                                name: "component-table-filters-sets-set",
                                                props: ["set", "search_string"],
                                                data: function() {
                                                    var self = this;
                                                    return {
                                                        states: {
                                                            is_opened: false
                                                        }
                                                    };
                                                },
                                                template: that.components["component-table-filters-sets-set"],
                                                delimiters: ['{ { ', ' } }'],
                                                computed: {
                                                    display_item: function() {
                                                        const self = this;
                                                        return (self.search_string === "" || self.set.states.is_wanted || self.set.states.is_wanted_inside);
                                                    },
                                                    item_class: function() {
                                                        const self = this;

                                                        var result = [];

                                                        if (self.search_string !== "") {
                                                            if (self.set.states.is_wanted) {
                                                                result.push("is-wanted");
                                                            }
                                                            if (self.set.states.is_wanted_inside) {
                                                                result.push("is-wanted-inside");
                                                            }
                                                        }

                                                        if (self.set.is_locked) {
                                                            result.push("is-locked");
                                                        }

                                                        return result.join(" ");
                                                    }
                                                },
                                                methods: {
                                                    toggle: function() {
                                                        const self = this;
                                                        self.states.is_opened = !self.states.is_opened;
                                                    },
                                                    onClick: function() {
                                                        const self = this;

                                                        if (self.set.is_group) {
                                                            self.set.states.is_open = !self.set.states.is_open;
                                                        } else {
                                                            self.useSet(self.set);
                                                        }
                                                    },
                                                    useSet: function(set) {
                                                        const self = this;
                                                        self.$emit("use_set", set);
                                                    }
                                                }
                                            }
                                        },
                                        computed: {
                                            items: function() {
                                                const self = this;

                                                checkSets(self.sets);

                                                return self.sets;

                                                /**
                                                 * @param {Object|Array} sets
                                                 * @return {Boolean}
                                                 * */
                                                function checkSets(sets) {
                                                    var result = false;

                                                    $.each(sets, function(i, set) {
                                                        var is_wanted = checkSet(set);
                                                        if (is_wanted) {
                                                            result = true;
                                                            return false;
                                                        }
                                                    });

                                                    return result;
                                                }

                                                /**
                                                 * @param {Object} set
                                                 * @return {Boolean}
                                                 * */
                                                function checkSet(set) {
                                                    var is_wanted = (self.search_string === "" || set.name.toLowerCase().indexOf( self.search_string.toLowerCase() ) >= 0);

                                                    set.states.is_wanted = is_wanted;
                                                    set.states.is_wanted_inside = false;

                                                    if (set.is_group && set.sets.length) {
                                                        set.states.is_wanted_inside = checkSets(set.sets);
                                                    }

                                                    return is_wanted;
                                                }
                                            }
                                        },
                                        methods: {
                                            changeItem: function(item) {
                                                if (item.is_locked) { return false; }
                                                const self = this;
                                                item.states.enabled = !item.states.enabled;
                                                self.save(item);
                                            },
                                            save: function(item) {
                                                const self = this;
                                                self.$emit("success", {
                                                    type: "set",
                                                    item: item
                                                });
                                            }
                                        },
                                        mounted: function() {
                                            const self = this;

                                            self.dropbox = $.wa.new.Dropbox({
                                                $wrapper: $(self.$el),
                                                protect: false
                                            });
                                        }
                                    },
                                    "component-table-filters-types": {
                                        data: function() {
                                            let self = this,
                                                options = $.wa.clone(that.filter_options);

                                            return {
                                                search_string: "",
                                                types: formatOptions(options.types)
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
                                        template: that.components["component-table-filters-types"],
                                        delimiters: ['{ { ', ' } }'],
                                        computed: {
                                            items: function() {
                                                const self = this;
                                                return self.types.filter( function(item) {
                                                    return (item.name.toLowerCase().indexOf(self.search_string.toLowerCase()) >= 0);
                                                });
                                            }
                                        },
                                        methods: {
                                            setType: function(type) {
                                                if (type.is_locked) { return false; }
                                                const self = this;
                                                type.states.enabled = true;
                                                self.save(type);
                                            },
                                            save: function(type) {
                                                const self = this;
                                                self.$emit("success", {
                                                    type: "type",
                                                    item: type
                                                });
                                            }
                                        },
                                        mounted: function() {
                                            const self = this;
                                            self.dropbox = $.wa.new.Dropbox({
                                                $wrapper: $(self.$el),
                                                protect: false
                                            });
                                        }
                                    }
                                },
                                methods: {
                                    setType: function(type) {
                                        const self = this;
                                        self.content_type = type;
                                        self.autofocus();
                                    },
                                    success: function(data) {
                                        const self = this;
                                        self.dropbox.hide();
                                        self.$emit("success", data);
                                    },
                                    autofocus: function() {
                                        const self = this;
                                        self.$nextTick( function() {
                                            $(self.$el).find("input.js-autofocus").trigger("focus");
                                        });
                                    }
                                },
                                mounted: function() {
                                    const self = this;
                                    self.dropbox = $.wa.new.Dropbox({
                                        $wrapper: $(self.$el),
                                        protect: false,
                                        open: function() {
                                            self.autofocus();
                                        }
                                    });
                                }
                            },
                            "component-table-filters-storefronts": {
                                data: function() {
                                    let self = this,
                                        options = $.wa.clone(that.filter_options);

                                    return {
                                        storefronts: formatOptions(options.storefronts),
                                        search_string: "",
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
                                template: that.components["component-table-filters-storefronts"],
                                delimiters: ['{ { ', ' } }'],
                                computed: {
                                    has_value: function() {
                                        const self = this;
                                        return !!self.storefronts.filter( storefront => storefront.states.enabled).length;
                                    },
                                    items: function() {
                                        const self = this;
                                        return self.storefronts.filter( function(item) {
                                            return (item.name.toLowerCase().indexOf(self.search_string.toLowerCase()) >= 0);
                                        });
                                    }
                                },
                                methods: {
                                    onChange: function() {
                                        const self = this;

                                        if (!self.states.is_changed) {
                                            self.states.is_changed = true;
                                        }
                                    },
                                    changeStorefront: function(storefront) {
                                        if (storefront.is_locked) { return false; }
                                        const self = this;
                                        storefront.states.enabled = !storefront.states.enabled;
                                        self.onChange();
                                    },
                                    reset: function() {
                                        const self = this;

                                        $.each(self.storefronts, function(i, storefront) {
                                            storefront.states.enabled = false;
                                        });

                                        self.onChange();
                                    },
                                    save: function() {
                                        const self = this;
                                        self.dropbox.hide();
                                        self.$emit("success", self.storefronts);
                                    }
                                },
                                mounted: function() {
                                    const self = this;
                                    var $wrapper = $(self.$el);

                                    self.dropbox = $.wa.new.Dropbox({
                                        $wrapper: $wrapper,
                                        protect: false,
                                        open: function() {
                                            $wrapper.find("input.js-autofocus").trigger("focus");
                                        }
                                    });
                                }
                            },
                            "component-table-filters-tags": {
                                data: function() {
                                    let self = this,
                                        options = $.wa.clone(that.filter_options);

                                    return {
                                        tags: formatOptions(options.tags),
                                        search_string: "",
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
                                computed: {
                                    has_value: function() {
                                        const self = this;
                                        return !!self.tags.filter( tag => tag.states.enabled).length;
                                    },
                                    items: function() {
                                        const self = this;
                                        return self.tags.filter( function(item) {
                                            return (item.name.toLowerCase().indexOf(self.search_string.toLowerCase()) >= 0);
                                        });
                                    }
                                },
                                methods: {
                                    onChange: function() {
                                        const self = this;

                                        if (!self.states.is_changed) {
                                            self.states.is_changed = true;
                                        }
                                    },
                                    changeTag: function(tag) {
                                        if (tag.is_locked) { return false; }
                                        const self = this;
                                        tag.states.enabled = !tag.states.enabled;
                                        self.onChange();
                                    },
                                    reset: function() {
                                        const self = this;

                                        $.each(self.tags, function(i, tag) {
                                            tag.states.enabled = false;
                                        });

                                        self.onChange();
                                    },
                                    save: function() {
                                        const self = this;
                                        self.dropbox.hide();
                                        self.$emit("success", self.tags);
                                    }
                                },
                                mounted: function() {
                                    const self = this;
                                    var $wrapper = $(self.$el);

                                    self.dropbox = $.wa.new.Dropbox({
                                        $wrapper: $wrapper,
                                        protect: false,
                                        open: function() {
                                            $wrapper.find("input.js-autofocus").trigger("focus");
                                        }
                                    });
                                }
                            },
                            "component-table-filters-features": {
                                data: function() {
                                    let self = this,
                                        options = $.wa.clone(that.filter_options);

                                    return {
                                        features: formatOptions(options.features),
                                        search_string: "",
                                        states: {}
                                    };

                                    function formatOptions(items) {
                                        return items;
                                    }
                                },
                                template: that.components["component-table-filters-features"],
                                delimiters: ['{ { ', ' } }'],
                                components: {
                                    "component-table-filters-features-item": {
                                        props: ["item"],
                                        data: function() {
                                            var self = this;
                                            return {};
                                        },
                                        template: that.components["component-table-filters-features-item"],
                                        delimiters: ['{ { ', ' } }'],
                                        methods: {
                                            toggle: function() {
                                                const self = this;

                                                var dialog = $.waDialog({
                                                    html: that.templates["dialog-list-feature-value"],
                                                    options: {
                                                        item_data: self.item,
                                                        onSuccess: function(data) {
                                                            self.$emit("success", data);
                                                        }
                                                    },
                                                    onOpen: function($dialog, dialog) {
                                                        that.initDialogFeatureValue($dialog, dialog);
                                                    }
                                                });

                                                self.$emit("feature_dialog", dialog);
                                            }
                                        }
                                    }
                                },
                                computed: {
                                    items: function() {
                                        const self = this;
                                        return self.features.filter( function(item) {
                                            var result = false;

                                            if (typeof item.name === "string") {
                                                result = (item.name.toLowerCase().indexOf(self.search_string.toLowerCase()) >= 0);
                                            }

                                            return result;
                                        });
                                    }
                                },
                                methods: {
                                    hide: function(item) {
                                        const self = this;
                                        self.search_string = "";
                                        self.dropbox.hide();
                                    },
                                    success: function(data) {
                                        const self = this;
                                        self.$emit("success", data);
                                    }
                                },
                                mounted: function() {
                                    const self = this;
                                    var $wrapper = $(self.$el);
                                    self.dropbox = $.wa.new.Dropbox({
                                        $wrapper: $wrapper,
                                        protect: false,
                                        open: function() {
                                            $wrapper.find("input.js-autofocus").trigger("focus");
                                        }
                                    });
                                }
                            },
                            "component-table-filters-list": {
                                data: function() {
                                    let self = this;
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
                                        props: [],
                                        data: function() {
                                            var self = this;
                                            return {
                                                name: "",
                                                states: {
                                                    locked: false
                                                }
                                            }
                                        },
                                        template: that.components["component-filter-add-form"],
                                        delimiters: ['{ { ', ' } }'],
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
                                        data: function() {
                                            var self = this;
                                            return {
                                                name: self.filter.name,
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
                                    },
                                    filters_object: function() {
                                        var self = this;
                                        return $.wa.construct(self.filters, "id");
                                    }
                                },
                                methods: {
                                    onCopyUrl: function(event, filter) {
                                        const self = this;
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
                                        var self = this;

                                        that.broadcast.getPresentationsIds().done( function(ids) {
                                            var data = {
                                                filter: filter.id,
                                                presentation: that.presentation.id,
                                                open_presentations: ids
                                            };

                                            that.reload(data);
                                        });
                                    },
                                    rename: function(filter) {
                                        var self = this;
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
                                                onOpen: function($dialog, dialog) {
                                                    $dialog.on("click", ".js-delete-button", function(event) {
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

                                        var filters = self.filters;

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

                                        //

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

                                            if (over_index !== move_index) {
                                                filters.splice(move_index, 1);

                                                over_index = filters.indexOf(filter);
                                                var new_index = over_index + (before ? 0 : 1);

                                                filters.splice(new_index, 0, drag_data.move_filter);
                                                is_change = true;
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
                                                return self.filters.map( function(filter) {
                                                    return filter.id;
                                                });
                                            }
                                        }

                                        //

                                        function getFilter(filter_id) {
                                            var filters_object = self.filters_object;
                                            return (filters_object[filter_id] ? filters_object[filter_id] : null);
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
                                props: [],
                                data: function() {
                                    const self = this;
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
                                        props: ["group", "show_tooltip"],
                                        data: function() {
                                            var self = this;
                                            self.show_tooltip = (typeof self.show_tooltip === "boolean" ? self.show_tooltip : false);
                                            return {
                                                feature_data: getFeatureData(self.group)
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
                                                        result.push(name);
                                                    });
                                                }

                                                return result;

                                                function getName(rule) {
                                                    var result = "";

                                                    var options = that.filter_options[self.group.type];
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
                                                            if (option.id === rule.rule_params) {
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
                                                        var name = rule_name,
                                                            value = self.group.label;
                                                        result = name + ": " + value;
                                                    } else {
                                                        result = self.names.join(" | ");
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
                                        const self = this;

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
                                                    that.reload({
                                                        page: 1,
                                                        presentation: (response.data.new_presentation_id || that.presentation.id)
                                                    });
                                                });
                                        });
                                    },
                                    resetFilters: function(group) {
                                        const self = this;

                                        that.animate(true);

                                        that.broadcast.getPresentationsIds().done( function(ids) {
                                            var data = {
                                                filter_id: that.filter.id,
                                                presentation_id: that.presentation.id,
                                                open_presentations: ids
                                            };

                                            $.post(that.urls["filter_rule_delete_all"], data, "json")
                                                .done( function(response) {
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

                                    //

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
                        computed: {},
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
                                                that.reload({
                                                    page: 1,
                                                    presentation: (response.data.new_presentation_id || that.presentation.id)
                                                });
                                            });
                                    });
                                }
                            },

                            applyCategories: function(data) {
                                const self = this;

                                var result = {
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

                                self.save(result);
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
                                const self = this;
                                self.save(features_data);
                            },

                            save: function(values) {
                                const self = this;

                                that.animate(true);

                                that.broadcast.getPresentationsIds().done( function(ids) {
                                    var data = $.extend({
                                        filter_id: that.filter.id,
                                        presentation_id: that.presentation.id,
                                        open_presentations: ids
                                    }, values);

                                    $.post(that.urls["filter_rule_add"], data, "json")
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
                            }
                        }
                    },

                    "component-product-thumb": {
                        props: ["product"],
                        template: that.components["component-product-thumb"],
                        delimiters: ['{ { ', ' } }'],
                        components: {
                            "component-product-slider": {
                                props: ["photos", "photo_id"],
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
                                    }
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
                                        var self = this;
                                        clearTimeout(self.timer);
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
                                const self = this;
                                return "products/" + self.product.id + "/?presentation=" + that.presentation.id;
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
                                let self = this,
                                    result = [];

                                var name_column = that.columns["name"],
                                    settings = name_column.settings;

                                if (settings) {
                                    var name_format = (settings.long_name_format ? settings.long_name_format : "");
                                    if (name_format === "hide_end") {
                                        result.push("one-line");
                                    }
                                }

                                return result.join(" ");
                            }
                        },
                        methods: {
                        },
                        mounted: function() {
                            var self = this;
                            // console.log( self );
                        }
                    },
                    "component-products-table": {
                        props: ["products"],
                        data: function() {
                            var self = this;
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
                            "component-products-table-header": {
                                props: ["column", "columns", "presentation"],
                                data: function() {
                                    var self = this;
                                    return {}
                                },
                                template: that.components["component-products-table-header"],
                                delimiters: ['{ { ', ' } }'],
                                computed: {
                                    is_stock: function() {
                                        let self = this;
                                        return (self.column.column_type.indexOf('stocks_') === 0);
                                    },
                                    is_virtual_stock: function() {
                                        let self = this;
                                        return (self.column.column_type.indexOf('stocks_v') === 0);
                                    },
                                    is_feature: function() {
                                        let self = this;
                                        return (self.column.column_type.indexOf('feature_') === 0);
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
                                        var self = this,
                                            result = [];

                                        switch (self.column.column_type) {
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
                                props: ["column", "product", "sku", "sku_mod"],
                                data: function() {
                                    let self = this;

                                    self.sku = (typeof self.sku !== "undefined" ? self.sku : null);
                                    self.sku_mod = (typeof self.sku_mod !== "undefined" ? self.sku_mod : null);

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
                                            is_sku_mod_col: is_sku_mod_col
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
                                    }
                                },
                                components: {
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
                                        computed: {
                                            product_url: function() {
                                                const self = this;
                                                return "products/" + self.product.id + "/?presentation=" + that.presentation.id;
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
                                                let self = this,
                                                    result = [];

                                                var name_column = that.columns["name"],
                                                    settings = name_column.settings;

                                                if (settings) {
                                                    var name_format = (settings.long_name_format ? settings.long_name_format : "");
                                                    if (name_format === "hide_end") {
                                                        result.push("one-line");
                                                    }
                                                }

                                                return result.join(" ");
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
                                            var self = this;
                                            return {
                                                states: {
                                                    is_locked: false
                                                }
                                            };
                                        },
                                        template: that.components["component-product-column-summary"],
                                        delimiters: ['{ { ', ' } }'],
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

                                                    new Vue({
                                                        el: $section[0],
                                                        data: {
                                                            product: self.product,
                                                            column: self.column,
                                                            column_data: $.wa.clone(self.column_data),
                                                            states: {
                                                                is_locked: false,
                                                                is_changed: false
                                                            }
                                                        },
                                                        delimiters: ['{ { ', ' } }'],
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
                                                            var dialog_self = this;
                                                            dialog.resize();
                                                        }
                                                    });
                                                }
                                            }
                                        }
                                    },
                                    "component-product-column-description": {
                                        props: ["product", "column", "column_info", "column_data"],
                                        data: function() {
                                            var self = this;
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

                                                    new Vue({
                                                        el: $section[0],
                                                        data: {
                                                            product: self.product,
                                                            column: self.column,
                                                            column_data: $.wa.clone(self.column_data),
                                                            states: {}
                                                        },
                                                        delimiters: ['{ { ', ' } }'],
                                                        methods: {
                                                        },
                                                        created: function () {
                                                            $section.css("visibility", "");
                                                        },
                                                        mounted: function () {
                                                            var dialog_self = this;
                                                            dialog.resize();
                                                        }
                                                    });
                                                }
                                            }
                                        }
                                    },
                                    "component-product-column-status": {
                                        props: ["product", "column", "column_info", "column_data"],
                                        data: function() {
                                            var self = this;
                                            return {
                                                states: { is_locked: false }
                                            };
                                        },
                                        template: that.components["component-product-column-status"],
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

                                                    new Vue({
                                                        el: $section[0],
                                                        data: {
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
                                                                            Vue.set(errors, error_class, {
                                                                                "id": error_class,
                                                                                "text": that.locales["error_url_required"]
                                                                            });
                                                                        } else if (!is_valid) {
                                                                            Vue.set(errors, error_class, {
                                                                                "id": error_class,
                                                                                "text": that.locales["error_url_incorrect"]
                                                                            });
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
                                                            var dialog_self = this;
                                                            dialog.resize();
                                                        }
                                                    });
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
                                                            self.$set(self.errors, error_key, {
                                                                id: error_key,
                                                                text: "price_error"
                                                            });
                                                        } else {
                                                            if (self.errors[error_key]) {
                                                                self.$delete(self.errors, error_key);
                                                            }
                                                        }

                                                        break;
                                                    default:
                                                        value = $.wa.validate(type, value);
                                                        break;
                                                }

                                                // set
                                                self.$set(data, key, value);
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
                                            var self = this;
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
                                                if (self.errors["url_error"]) { self.$delete(self.errors, "url_error"); }
                                                self.column_data.value = self.column_data.value.replace(/\//g, "");
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
                                                            self.$set(self.errors, "url_error", {
                                                                id: "url_error",
                                                                text: html
                                                            });
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
                                                                    html += '<div class="wa-error-text">'+error.text+'</div>';
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
                                                options: options,
                                                states: {
                                                    is_locked: false
                                                }
                                            };
                                        },
                                        template: that.components["component-product-column-dropdown"],
                                        delimiters: ['{ { ', ' } }'],
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
                                                    self.$delete(self.errors, error_key);
                                                } else {
                                                    self.$set(self.errors, error_key, {
                                                        id: error_key,
                                                        text: "price_error"
                                                    });
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
                                            var self = this;
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
                                                    console.log( dialog );

                                                    var $section = $dialog.find(".js-vue-section");

                                                    new Vue({
                                                        el: $section[0],
                                                        data: {
                                                            product: self.product,
                                                            column: self.column,
                                                            column_info: self.column_info,
                                                            column_data: $.wa.clone(self.column_data),
                                                            states: {
                                                                is_locked: false
                                                            }
                                                        },
                                                        delimiters: ['{ { ', ' } }'],
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
                                                            var dialog_self = this;
                                                            dialog.resize();
                                                        }
                                                    });
                                                }
                                            }
                                        }
                                    },
                                    "component-product-column-feature": {
                                        props: ["product", "sku_mod", "column", "column_info", "column_data"],
                                        data: function() {
                                            var self = this;
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
                                            "component-product-feature-color": {
                                                props: ["data", "readonly", "disabled"],
                                                data: function() {
                                                    var self = this;
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
                                                computed: {},
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
                                                data: function() {
                                                    var self = this;
                                                    return {
                                                        value: self.column_data.value,
                                                        options: self.column_info.options
                                                    };
                                                },
                                                template: that.components["component-product-feature-checkbox"],
                                                delimiters: ['{ { ', ' } }'],
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

                                                            new Vue({
                                                                el: $section[0],
                                                                data: function() {
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
                                                                    "component-feature-option-search": {
                                                                        props: ["options"],
                                                                        data: function() {
                                                                            let self = this;
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
                                                                        computed: {},
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
                                                            });
                                                        }

                                                    },
                                                }
                                            },
                                            "component-feature-input": {
                                                props: ["option", "column_data"],
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
                                                computed: {
                                                    show_preview: function() {
                                                        let self = this;
                                                        return (self.column_data.format === "number" && self.states.is_preview);
                                                    },
                                                    formatted_value: function() {
                                                        var self = this,
                                                            value = self.option.value;

                                                        if (Math.abs(parseFloat(value)) >= 0) {
                                                            value = $.wa.validate(self.format, value, {
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
                                        data: function() {
                                            var self = this;
                                            return {};
                                        },
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
                                },
                                mounted: function() {
                                    var self = this;
                                    // if (self.column.column_type.indexOf("feature_") === 0) {
                                    //     console.log( self.column_data );
                                    // }
                                }
                            },
                            "component-products-table-mass-selection": {
                                props: ["products"],
                                data: function() {
                                    var self = this;
                                    return {
                                        all_products: true,
                                        states: {
                                            is_disabled: false
                                        }
                                    }
                                },
                                template: that.components["component-products-table-mass-selection"],
                                delimiters: ['{ { ', ' } }'],
                                computed: {
                                    current_page_smart_string: function() {
                                        var self = this;
                                        return $.wa.locale_plural(that.products.length, that.locales["products_forms"]);
                                    },
                                    selected_products: function() {
                                        var self = this;
                                        return self.products.filter( function(product) {
                                            return product.states.selected;
                                        });
                                    },
                                    selected_all_products: function() {
                                        var self = this;
                                        return (self.selected_products.length === self.products.length);
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

                                        self.setSelection((check_all ? self.all_products : false));
                                    },
                                    setSelection: function(all_products) {
                                        var self = this;

                                        all_products = (typeof all_products === "boolean" ? all_products : self.all_products);
                                        var products_selection = (all_products ? "all_products" : "visible_products");
                                        if (that.products_selection !== products_selection) {
                                            that.products_selection = products_selection;
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
                                var self = this,
                                    result = [];

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
                                var self = this;

                                $.waDialog({
                                    html: that.templates["dialog-list-column-manager"],
                                    options: { vue_model: self },
                                    onOpen: function($dialog, dialog) {
                                        that.initDialogColumnManager($dialog, dialog);
                                    }
                                });
                            }
                        }
                    },

                    "component-mass-actions": {
                        props: ["products", "type"],
                        data: function() {
                            var self = this;

                            self.type = (typeof self.type !== "string" ? "footer" : self.type);
                            return {
                                actions: that.mass_actions
                            };
                        },
                        template: that.components["component-mass-actions"],
                        delimiters: ['{ { ', ' } }'],
                        components: {
                            "component-mass-actions-footer": {
                                props: ["products", "actions"],
                                data: function() {
                                    var self = this;
                                    return {
                                        states: {
                                            resize_timer: 0
                                        }
                                    }
                                },
                                template: that.components["component-mass-actions-footer"],
                                delimiters: ['{ { ', ' } }'],
                                computed: {
                                    products_length: function() {
                                        let self = this,
                                            result = self.products.length,
                                            unselected = that.products.length - self.products.length;

                                        if (that.products_selection === "all_products") {
                                            result = that.products_total_count - unselected;
                                        }

                                        return result;
                                    },
                                    pinned_actions: function() {
                                        var self = this,
                                            result = [];

                                        $.each(self.actions, function(i, group) {
                                            $.each(group.actions, function(j, action) {
                                                if (action.pinned) {
                                                    result.push(action);
                                                }
                                            });
                                        });

                                        return result;
                                    },
                                    dropdown_actions: function() {
                                        var self = this;
                                        return self.actions;
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
                                            $.each(self.pinned_actions, function(i, action) {
                                                action.states.visible = true;
                                            });

                                            // Дожидаемся ререндера
                                            self.$nextTick( function() {
                                                var list_w = self.$list.width();

                                                var width = 0,
                                                    visible_count = 0;

                                                // Считаем сколько пунктов влезает
                                                self.$list.find(".s-action").each( function() {
                                                    var $action = $(this),
                                                        action_w = $action.outerWidth(true);

                                                    width += action_w;
                                                    if (width <= list_w) {
                                                        visible_count += 1;
                                                    } else {
                                                        return false;
                                                    }
                                                });

                                                // Показываем часть пунктов что влезли
                                                $.each(self.pinned_actions, function(i, action) {
                                                    action.states.visible = (i < visible_count);
                                                });
                                            });
                                        }
                                    },
                                    pin: function() {
                                        that.states.mass_actions_pinned = true;
                                        localStorage.setItem(that.states.mass_actions_storage_key, true);
                                    },
                                    close: function() {
                                        var self = this;

                                        $.each(self.products, function(i, product) {
                                            product.states.selected = false;
                                        });
                                    },

                                    callAction: function(action) {
                                        const self = this;
                                        self.$emit("call_action", action);
                                    }
                                },
                                mounted: function() {
                                    var self = this,
                                        $wrapper = $(self.$el);

                                    self.$list = $wrapper.find(".js-actions-list");

                                    $wrapper.find(".dropdown").each( function() {
                                        $(this).waDropdown({ hover : false });
                                    });

                                    initObserver(self.$el);

                                    //

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
                            "component-mass-actions-aside": {
                                props: ["products", "actions"],
                                template: that.components["component-mass-actions-aside"],
                                delimiters: ['{ { ', ' } }'],
                                components: {
                                },
                                computed: {
                                    products_length: function() {
                                        let self = this,
                                            result = self.products.length,
                                            unselected = that.products.length - self.products.length;

                                        if (that.products_selection === "all_products") {
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
                                    },
                                    unpin: function() {
                                        that.states.mass_actions_pinned = false;
                                        localStorage.removeItem(that.states.mass_actions_storage_key);
                                    },

                                    callAction: function(action) {
                                        const self = this;
                                        self.$emit("call_action", action);
                                    }
                                },
                                mounted: function() {
                                    var self = this;
                                }
                            }
                        },
                        computed: {},
                        methods: {
                            callAction: function(action) {
                                const self = this;
                                that.callMassAction(action, self.products, that.products_selection);
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
                        return (self.selected_products.length || that.products_selection === "all_products");
                    }
                },
                methods: {
                    changeView: function(view_id) {
                        let self = this;

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
                        const self = this;

                        sessionPresentationName(presentation.name);

                        that.broadcast.getPresentationsIds().done( function(ids) {
                            that.reload({
                                open_presentations: ids,
                                active_presentation: that.presentation.id,
                                presentation: presentation.id
                            });
                        });
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
                    },
                    onScrollProductsSection: function(event) {
                        var scroll_top = $(event.target).scrollTop();
                        if (scroll_top > 0) {
                            sessionStorage.setItem("shop_products_table_scroll_top", scroll_top);
                        } else {
                            sessionStorage.removeItem("shop_products_table_scroll_top");
                        }
                    },
                    setScrollProductsSection: function(scroll_top) {
                        var self = this;

                        var $wrapper = $(self.$el),
                            $table_section = $wrapper.find(".s-products-table-section, .s-products-thumbs-section");

                        if ($table_section.length) {
                            scroll_top = (typeof scroll_top === "number" ? scroll_top : sessionStorage.getItem("shop_products_table_scroll_top"));
                            if (scroll_top > 0 ? scroll_top : 0);
                            $table_section.scrollTop(scroll_top);
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
        };

        Page.prototype.animate = function(show) {
            var that = this;

            const locked_class = "is-locked",
                  animation = "<div class=\"s-locked-animation\"><i class=\"fas fa-spinner fa-spin\"></i></div>";

            if (show) {
                if (!that.states.$animation) {
                    that.states.$animation = $(animation).appendTo( that.$wrapper.addClass(locked_class) );
                }
            } else {
                if (that.states.$animation) {
                    that.states.$animation.remove();
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

            var broadcast = new BroadcastChannel("presentation_channel"),
                presentation_data = [],
                presentation_promise = null,
                filter_data = [],
                filter_promise = null;

            var time = 200;

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

            return {
                getPresentationsIds: function() {
                    if (presentation_promise) { return presentation_promise; }

                    var deferred = $.Deferred();

                    presentation_data = [];

                    broadcast.postMessage({ action: "get_presentation_id" });

                    setTimeout( function() {
                        var ids = presentation_data;
                        deferred.resolve(ids);
                        presentation_promise = null;
                    }, time);

                    presentation_promise = deferred.promise();

                    return presentation_promise;
                },
                getFiltersIds: function() {
                    if (filter_promise) { return filter_promise; }

                    var deferred = $.Deferred();

                    filter_data = [];

                    broadcast.postMessage({ action: "get_filter_id" });

                    setTimeout( function() {
                        var ids = filter_data;
                        deferred.resolve(ids);
                        filter_promise = null;
                    }, time);

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
            var that = this;

            console.log( dialog.options.item_data );

            var $section = $dialog.find(".js-vue-section");

            var vue_model = new Vue({
                el: $section[0],
                data: {
                    item_data: getItemData(),
                    items_keys: {},
                    states: {
                        locked: false,
                        is_loading: false
                    }
                },
                delimiters: ['{ { ', ' } }'],
                components: {
                    "component-feature-value-checkbox": {
                        props: ["item_data"],
                        data: function() {
                            var self = this;
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
                                    var self = this;
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
            });

            function getItemData() {
                let item_data = $.wa.clone(dialog.options.item_data);

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
            var that = this;

            console.log( dialog );

            var $section = $dialog.find(".js-vue-section");

            var columns = getColumns();

            var vue_model = new Vue({
                el: $section[0],
                data: {
                    columns: columns,
                    states: {
                        locked: false,
                        column_expand: false
                    }
                },
                delimiters: ['{ { ', ' } }'],
                components: {
                    "component-search-column": {
                        data: function() {
                            let self = this;
                            return {
                                search_string: "",
                                selection: []
                            };
                        },
                        template: that.components["component-search-column"],
                        delimiters: ['{ { ', ' } }'],
                        computed: {
                        },
                        methods: {
                            search: function() {
                                let self = this;

                                let selection = [];

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
                        data: function() {
                            var self = this;

                            switch (self.column.id) {
                                case "name":
                                    if (!self.column.settings.long_name_format) {
                                        self.$set(self.column.settings, "long_name_format", "");
                                    }
                                    break;
                                case "summary":
                                    if (!self.column.settings.display) {
                                        self.$set(self.column.settings, "display", "text");
                                    }
                                    break;
                                case "tags":
                                case "categories":
                                case "sets":
                                    if (!self.column.settings.visible_count) {
                                        self.$set(self.column.settings, "visible_count", "3");
                                    }
                                case "price":
                                case "compare_price":
                                case "purchase_price":
                                case "base_price":
                                    if (!self.column.settings.format) {
                                        self.$set(self.column.settings, "format", "origin");
                                    }
                                    break;
                            }

                            var settings = null;
                            if (Object.keys(self.column.settings).length) {
                                settings = self.column.settings;
                            }

                            return {
                                column_info: that.columns[self.column.id],
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
                                var self = this;
                                if (value === true) {
                                    self.$emit("column_enabled", self.column);
                                }
                            }
                        },
                        components: {
                            "component-switch": {
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
                            }
                        },
                        computed: {
                            is_stock: function() {
                                let self = this;
                                return (self.column_info.id.indexOf('stocks_') === 0);
                            },
                            is_virtual_stock: function() {
                                let self = this;
                                return (self.column_info.id.indexOf('stocks_v') === 0);
                            },
                            is_feature: function() {
                                let self = this;
                                return (self.column_info.id.indexOf('feature_') >= 0);
                            },
                            item_class: function() {
                                let self = this,
                                    column = self.column,
                                    result = [];

                                if (column.states.move) { result.push("is-moving"); }
                                if (column.states.highlighted) { result.push("is-highlighted"); }
                                if (self.states.show_settings) { result.push("is-expanded"); }
                                if (self.column.states.ready) { result.push("is-ready"); }
                                if (self.states.hidden) { result.push("fade-out"); }
                                if (self.states.jump_animation) { result.push("is-jump-enabled"); }
                                if (self.column.enabled) { result.push("jump-down"); }
                                if (!self.column.enabled) { result.push("jump-up"); }

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
                                    // self.states.hidden = false;
                                }, 500);
                            },
                            setup: function() {
                                var self = this;
                                self.states.show_settings = !self.states.show_settings;
                                self.$emit("column_expanded", (self.states.show_settings ? self.column : null));
                            }
                        },
                        mounted: function() {
                            var self = this;

                            if (self.column.states.ready) {
                                self.states.hidden = true;
                                self.column.states.highlighted = true;
                                setTimeout( function () {
                                    self.states.hidden = false;
                                }, 10);
                                setTimeout( function() {
                                    self.column.states.highlighted = false;
                                }, 1000);

                            } else {
                                self.column.states.ready = true;
                            }
                        }
                    }
                },
                computed: {
                    active_columns: function() {
                        var self = this;
                        return self.columns.filter( function(column) {
                            return column.enabled;
                        });
                    },
                    inactive_columns: function() {
                        var self = this;
                        return self.columns.filter( function(column) {
                            return !column.enabled;
                        });
                    }
                },
                methods: {
                    initDragAndDrop: function($wrapper) {
                        var self = this;

                        var $document = $(document);

                        var drag_data = {},
                            over_locked = false,
                            is_change = false,
                            timer = 0;

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
                                is_change = true;
                            }
                        }

                        //

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
                    },

                    update: function() {
                        var self = this;
                    }
                },
                created: function () {
                    $section.css("visibility", "");
                },
                mounted: function () {
                    var self = this;

                    dialog.resize();

                    self.initDragAndDrop( $(self.$el) );
                }
            });

            function getColumns() {
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
                        redirect_url = action.redirect_url.replace("id/", "presentation/");
                        product_ids.unshift(that.presentation.id);
                    }

                    var href = redirect_url + product_ids.join(",");
                    var $link = $("<a />", { href: href });
                    that.$wrapper.prepend($link);
                    $link.trigger("click").remove();
                    break;

                //

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

                //

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
                            $.wa_shop_products.router.reload();
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

                //

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

                //

                default:

                    var products_hash;
                    if (is_all_products) {
                        products_hash = 'presentation/'+that.presentation.id;
                    } else {
                        products_hash = 'id/'+product_ids.join(",");
                    }

                    if (action.action_url) {
                        action.states.is_locked = true;
                        $.post(action.action_url, $.extend(getSubmitData(), {
                            products_hash: products_hash
                        }), "json")
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
                    }

                    // Диалог показывет сообщение "действие ещё не сделано"
                    $.waDialog({
                        html: that.templates["dialog-category-clone"],
                        onOpen: function($dialog, dialog) {
                            var $section = $dialog.find(".js-vue-section");

                            var vue_model = new Vue({
                                el: $section[0],
                                data: {
                                    action: action
                                },
                                delimiters: ['{ { ', ' } }'],
                                created: function () {
                                    $section.css("visibility", "");
                                },
                                mounted: function() {
                                    var self = this,
                                        $wrapper = $(self.$el);
                                    dialog.resize();
                                }
                            });
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
                        data: function() {
                            var self = this;
                            return {
                                name: "",
                                parent_id: (self.category ? self.category.id : ""),
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
                                    self.$set(self.errors, error_id, error);
                                    errors.push(error);
                                } else if (self.errors[error_id]) {
                                    self.$delete(self.errors, error_id);
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

                    var vue_model = new Vue({
                        el: $section[0],
                        data: {
                            categories_array: options.categories,
                            categories_object: categories_object,
                            search_string: "",
                            states: {
                                is_locked: false,
                                show_form: false
                            }
                        },
                        delimiters: ['{ { ', ' } }'],
                        components: {
                            "component-category-form": component_category_form,
                            "component-categories-group": {
                                name: "component-categories-group",
                                props: ["categories"],
                                data: function() {
                                    var self = this;
                                    return {};
                                },
                                template: that.components["component-categories-group"],
                                delimiters: ['{ { ', ' } }'],
                                components: {
                                    "component-category": {
                                        props: ["category"],
                                        data: function() {
                                            var self = this;
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
                                                self.$set(categories_object, new_category.id, new_category);
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
                            },
                            "component-category-search": {
                                props: ["value"],
                                data: function() {
                                    let self = this;
                                    return {};
                                },
                                template: that.components["component-category-search"],
                                delimiters: ['{ { ', ' } }'],
                                methods: {
                                    onInput: function() {
                                        let self = this;
                                        self.$emit("input", self.value);
                                        self.$emit("search_input", self.value);
                                    },
                                    onChange: function() {
                                        let self = this;
                                        self.$emit("input", self.value);
                                        self.$emit("search_change", self.value);
                                    },
                                    revert: function() {
                                        let self = this;
                                        self.value = "";
                                        self.onChange();
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
                                self.$set(categories_object, new_category.id, new_category);
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
                            var self = this,
                                $wrapper = $(self.$el);

                            self.$nextTick( function() {
                                dialog.resize();
                                vue_ready.resolve(dialog);
                                $(self.$el).find("input.js-autofocus").trigger("focus");
                            });
                        }
                    });
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

                    var vue_model = new Vue({
                        el: $section[0],
                        data: {
                            categories_array: options.categories,
                            categories_object: categories_object,
                            search_string: "",
                            states: {
                                is_locked: false,
                                show_form: false
                            }
                        },
                        delimiters: ['{ { ', ' } }'],
                        components: {
                            "component-categories-group": {
                                name: "component-categories-group",
                                props: ["categories"],
                                data: function() {
                                    var self = this;
                                    return {};
                                },
                                template: that.components["component-categories-group"],
                                delimiters: ['{ { ', ' } }'],
                                components: {
                                    "component-category": {
                                        props: ["category"],
                                        data: function() {
                                            var self = this;
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
                            },
                            "component-category-search": {
                                props: ["value"],
                                data: function() {
                                    let self = this;
                                    return {};
                                },
                                template: that.components["component-category-search"],
                                delimiters: ['{ { ', ' } }'],
                                methods: {
                                    onInput: function() {
                                        let self = this;
                                        self.$emit("input", self.value);
                                        self.$emit("search_input", self.value);
                                    },
                                    onChange: function() {
                                        let self = this;
                                        self.$emit("input", self.value);
                                        self.$emit("search_change", self.value);
                                    },
                                    revert: function() {
                                        let self = this;
                                        self.value = "";
                                        self.onChange();
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
                            var self = this,
                                $wrapper = $(self.$el);

                            self.$nextTick( function() {
                                dialog.resize();
                                vue_ready.resolve(dialog);
                                $(self.$el).find("input.js-autofocus").trigger("focus");
                            });
                        }
                    });
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
                        data: function() {
                            var self = this;
                            return {
                                name: "",
                                parent_id: (self.item ? self.item.group_id : ""),
                                errors: {},
                                states: {
                                    is_locked: false
                                }
                            };
                        },
                        template: that.components["component-set-form"],
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
                                const self = this;
                                self.validate();
                            },
                            validate: function() {
                                const self = this;
                                var errors = [];

                                var error_id = "name_required";
                                if (!$.trim(self.name).length) {
                                    var error = { id: error_id, text: error_id };
                                    self.$set(self.errors, error_id, error);
                                    errors.push(error);
                                } else if (self.errors[error_id]) {
                                    self.$delete(self.errors, error_id);
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

                    var vue_model = new Vue({
                        el: $section[0],
                        data: {
                            items_array: options.items,
                            sets: sets_object,
                            groups: groups_object,
                            search_string: "",
                            states: {
                                is_locked: false,
                                show_form: false
                            }
                        },
                        delimiters: ['{ { ', ' } }'],
                        components: {
                            "component-set-form": component_set_form,
                            "component-set-item-group": {
                                name: "component-set-item-group",
                                props: ["items"],
                                data: function() {
                                    var self = this;
                                    return {};
                                },
                                template: that.components["component-set-item-group"],
                                delimiters: ['{ { ', ' } }'],
                                components: {
                                    "component-set-item": {
                                        props: ["item"],
                                        data: function() {
                                            var self = this;
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
                            },
                            "component-set-search": {
                                props: ["value"],
                                data: function() {
                                    let self = this;
                                    return {};
                                },
                                template: that.components["component-set-search"],
                                delimiters: ['{ { ', ' } }'],
                                methods: {
                                    onInput: function() {
                                        let self = this;
                                        self.$emit("input", self.value);
                                        self.$emit("search_input", self.value);
                                    },
                                    onChange: function() {
                                        let self = this;
                                        self.$emit("input", self.value);
                                        self.$emit("search_change", self.value);
                                    },
                                    revert: function() {
                                        let self = this;
                                        self.value = "";
                                        self.onChange();
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
                                self.$set(sets_object, new_item.set_id, new_item);
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
                            var self = this,
                                $wrapper = $(self.$el);

                            self.$nextTick( function() {
                                dialog.resize();
                                vue_ready.resolve(dialog);
                                $(self.$el).find("input.js-autofocus").trigger("focus");
                            });
                        }
                    });
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

                    var vue_model = new Vue({
                        el: $section[0],
                        data: {
                            items_array: options.items,
                            sets: sets_object,
                            groups: groups_object,
                            search_string: "",
                            states: {
                                is_locked: false,
                                show_form: false
                            }
                        },
                        delimiters: ['{ { ', ' } }'],
                        components: {
                            "component-set-item-group": {
                                name: "component-set-item-group",
                                props: ["items"],
                                data: function() {
                                    var self = this;
                                    return {};
                                },
                                template: that.components["component-set-item-group"],
                                delimiters: ['{ { ', ' } }'],
                                components: {
                                    "component-set-item": {
                                        props: ["item"],
                                        data: function() {
                                            var self = this;
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
                            },
                            "component-set-search": {
                                props: ["value"],
                                data: function() {
                                    let self = this;
                                    return {};
                                },
                                template: that.components["component-set-search"],
                                delimiters: ['{ { ', ' } }'],
                                methods: {
                                    onInput: function() {
                                        let self = this;
                                        self.$emit("input", self.value);
                                        self.$emit("search_input", self.value);
                                    },
                                    onChange: function() {
                                        let self = this;
                                        self.$emit("input", self.value);
                                        self.$emit("search_change", self.value);
                                    },
                                    revert: function() {
                                        let self = this;
                                        self.value = "";
                                        self.onChange();
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
                            var self = this,
                                $wrapper = $(self.$el);

                            self.$nextTick( function() {
                                dialog.resize();
                                vue_ready.resolve(dialog);
                                $(self.$el).find("input.js-autofocus").trigger("focus");
                            });
                        }
                    });
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

                    var vue_model = new Vue({
                        el: $section[0],
                        data: {
                            tags: [],
                            tags_keys: {},
                            states: {
                                is_locked: false
                            }
                        },
                        delimiters: ['{ { ', ' } }'],
                        components: {
                        },
                        computed: {
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
                                    $input.autocomplete( "destroy" );
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
                                $wrapper = $(self.$el);

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
                    });
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

                    var vue_model = new Vue({
                        el: $section[0],
                        data: {
                            search_string: "",
                            tags_array: formatTags(options.tags),
                            states: {
                                is_locked: false
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
                                const self = this;
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
                            var self = this,
                                $wrapper = $(self.$el);

                            self.$nextTick( function() {
                                dialog.resize();
                                vue_ready.resolve(dialog);
                                $(self.$el).find("input.js-autofocus").trigger("focus");
                            });
                        }
                    });

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

            //

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

                    var vue_model = new Vue({
                        el: $section[0],
                        data: {
                            active_badge: null,
                            badges: options.badges,
                            states: {
                                is_locked: false
                            }
                        },
                        delimiters: ['{ { ', ' } }'],
                        computed: {
                            disabled: function() {
                                const self = this;
                                return !!(self.states.is_locked || self.active_badge === null);
                            }
                        },
                        methods: {
                            setBadge: function(badge) {
                                const self = this;
                                self.active_badge = badge;
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
                            var self = this,
                                $wrapper = $(self.$el);

                            self.$nextTick( function() {
                                dialog.resize();
                                vue_ready.resolve(dialog);
                            });
                        }
                    });
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

                    var vue_model = new Vue({
                        el: $section[0],
                        data: {
                            active_option: null,
                            options: options.options,
                            states: {
                                is_locked: false
                            }
                        },
                        delimiters: ['{ { ', ' } }'],
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
                            var self = this,
                                $wrapper = $(self.$el);

                            self.$nextTick( function() {
                                dialog.resize();
                                vue_ready.resolve(dialog);
                            });
                        }
                    });
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

                    var vue_model = new Vue({
                        el: $section[0],
                        data: {
                            type: "",
                            states: {
                                is_locked: false
                            }
                        },
                        delimiters: ['{ { ', ' } }'],
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
                            var self = this,
                                $wrapper = $(self.$el);

                            self.$nextTick( function() {
                                dialog.resize();
                                vue_ready.resolve(dialog);
                            });
                        }
                    });
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

                    var vue_model = new Vue({
                        el: $section[0],
                        data: {
                            states: {
                                is_locked: false
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
                            var self = this,
                                $wrapper = $(self.$el);

                            self.$nextTick( function() {
                                dialog.resize();
                                vue_ready.resolve(dialog);
                            });
                        }
                    });
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

                    var vue_model = new Vue({
                        el: $section[0],
                        data: {
                            promo: "",
                            states: {
                                is_locked: false
                            }
                        },
                        delimiters: ['{ { ', ' } }'],
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
                            var self = this,
                                $wrapper = $(self.$el);

                            self.$nextTick( function() {
                                dialog.resize();
                                vue_ready.resolve(dialog);
                            });
                        }
                    });
                }
            }

            //

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