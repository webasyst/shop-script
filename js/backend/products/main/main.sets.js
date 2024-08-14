( function($) {

    let Page = ( function($) {

        function Page(options) {
            let that = this;
            const { reactive } = Vue;

            // DOM
            that.$wrapper = options["$wrapper"];

            // CONST
            that.components = options["components"];
            that.templates  = options["templates"];
            that.tooltips   = options["tooltips"];
            that.locales    = options["locales"];
            that.urls       = options["urls"];


            // VUE VARS
            that.sets = reactive({});
            that.groups = reactive({});
            that.model = reactive(getModel(options["model"]));
            that.rule_options = options["rule_options"];
            that.sort_options = options["sort_options"];
            that.header_columns = reactive(options["header_columns"]);
            that.header_columns_object = $.wa.construct(that.header_columns, "id");

            that.states = reactive({
                sort_locked: false,
                move_locked: false,
                add_set_locked: false,
                add_group_locked: false,
                count_locked: true
            });

            // INIT
            that.vue_model = that.initVue();

            function getModel(model) {
                let storage = that.storage(),
                    expanded_groups = (storage && storage.expanded_groups && storage.expanded_groups.length ? storage.expanded_groups : []);

                formatModel(model);

                function formatModel(items) {
                    $.each(items, function(i, item) {
                        that.formatModelItem(item);
                        if (item.is_group) {
                            if (expanded_groups.indexOf(item.id) >= 0) {
                                item.states.expanded = true;
                            }
                            if (item.sets.length) {
                                formatModel(item.sets);
                            }
                        }
                    });
                }

                return model;
            }
        }

        Page.prototype.init = function() {
            let that = this;

            that.initDragAndDrop();

            that.updateSetsCount().done( function() {
                that.states.count_locked = false;
            });

            $.each(that.tooltips, function(i, tooltip) {
                $.wa.new.Tooltip(tooltip);
            });
        };

        Page.prototype.initVue = function() {
            let that = this;

            let $vue_section = that.$wrapper.find("#js-vue-section");

            if (typeof $.vue_app === "object" && typeof $.vue_app.unmount === "function") {
                $.vue_app.unmount();
            }

            that.vue_components = {
                "component-radio": {
                    props: ["modelValue", "name", "value", "label", "disabled"],
                    emits: ["update:modelValue", "change"],
                    template: that.components["component-radio"],
                    delimiters: ['{ { ', ' } }'],
                    computed: {
                        tag() { return (this.label !== false ? "label" : "span") },
                        prop_name() { return (typeof this.name === "string" ? this.name : "") },
                        prop_disabled() { return (typeof this.disabled === "boolean" ? this.disabled : false) },
                        checked() { return this.modelValue === this.value }
                    },
                    methods: {
                        onChange: function() {
                            this.$emit("update:modelValue", this.value);
                            this.$emit("change", this.value);
                        }
                    }
                },

                "component-dropdown-set-sorting": {
                    props: ["modelValue", "options", "button_class", "disabled"],
                    emits: ["update:modelValue", "change"],
                    data: function () {
                        let self = this;
                        return {
                            items: (typeof self.options !== "undefined" ? self.options : that.sort_options)
                        };
                    },
                    template: that.components["component-dropdown-set-sorting"],
                    delimiters: ['{ { ', ' } }'],
                    computed: {
                        prop_button_class() { return (typeof this.button_class !== "undefined" ? this.button_class : "") },
                        prop_disabled() { return (typeof this.disabled !== "undefined" ? this.disabled : false) },
                        active_item: function() {
                            let self = this,
                                active_item = self.items[0];

                            if (typeof self.modelValue === "string") {
                                let filter_item_search = self.items.filter(function (item) {
                                    return (item.value === self.modelValue);
                                });
                                active_item = (filter_item_search.length ? filter_item_search[0] : active_item);
                            }

                            return active_item;
                        }
                    },
                    methods: {
                        change: function(item) {
                            let self = this;
                            self.$emit("update:modelValue", item.value);
                            self.$emit("change", item.value);
                            self.dropdown.hide();
                        }
                    },
                    mounted: function () {
                        let self = this;

                        self.dropdown = $(self.$el).waDropdown({
                            hover : false,
                            protect: { box_limiter: '.s-sets-table-section', bottom: 20 }
                        }).waDropdown("dropdown");
                    }
                }
            }

            $.vue_app = Vue.createApp({
                data() {
                    return {
                        model : that.model,
                        states: that.states
                    }
                },
                components: {
                    "component-dropdown-sets-sorting": {
                        emits: ["change"],
                        template: that.components["component-dropdown-sets-sorting"],
                        delimiters: ['{ { ', ' } }'],
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
                    "component-search-sets": {
                        data: function() {
                            return {
                                search_string: "",
                                position: 0,
                                selection: []
                            };
                        },
                        template: that.components["component-search-sets"],
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
                                let self = this;

                                let selection = [],
                                    groups = that.groups,
                                    groups_selection = [];

                                // Сбрасываем позицию
                                self.position = 0;

                                // Делаем выборку
                                checkItems(that.model);

                                // Сохраняем выборку
                                self.selection = selection;

                                // Подсвечиваем родителей
                                $.each(groups, function(i, group) {
                                    group.states.is_wanted_inside = (groups_selection.indexOf(group.id) >= 0);
                                });

                                function checkItems(items) {
                                    $.each(items, function(i, item) {
                                        if (!item.is_group) {
                                            let is_wanted = (self.search_string.length > 0 && item.name.toLowerCase().indexOf( self.search_string.toLowerCase() ) >= 0 );
                                            if (is_wanted) {
                                                selection.push(item);
                                                if (item.group_id && item.group_id !== "0") {
                                                    groups_selection.push(item.group_id);
                                                }
                                            }
                                            item.states.is_wanted = is_wanted;
                                        } else if (item.sets && item.sets.length) {
                                            checkItems(item.sets);
                                        }
                                    });
                                }
                            },
                            moveUp: function() {
                                let self = this;

                                if (self.position > 1) {
                                    self.position -= 1;
                                    self.move(self.selection[self.position - 1]);
                                }
                            },
                            moveDown: function() {
                                let self = this;

                                if (self.position < self.selection.length) {
                                    self.position += 1;
                                    self.move(self.selection[self.position - 1]);
                                }
                            },
                            move: function(set) {
                                let self = this;

                                showTree(set);

                                self.$nextTick(moveTo);

                                function showTree(set) {
                                    if (set.group_id && set.group_id !== "0") {
                                        let group = that.groups[set.group_id];
                                        if (group) { group.states.expanded = true; }
                                    }
                                }

                                function moveTo() {
                                    let $set = that.$wrapper.find(".s-item-wrapper[data-set-id=\""+set.id+"\"]");
                                    if ($set.length) {
                                        let $wrapper = $set.closest(".s-sets-table-section");

                                        let scroll_top = 0,
                                            lift = 300;

                                        if ($set[0].offsetTop > lift) { scroll_top = $set[0].offsetTop - lift; }

                                        $wrapper.scrollTop(scroll_top);

                                        let active_class = "is-highlighted";
                                        $set.addClass(active_class);
                                        setTimeout( function() {
                                            $set.removeClass(active_class);
                                        }, 1000);
                                    }
                                }
                            },
                            revert: function() {
                                let self = this;
                                self.search_string = "";
                                self.search();
                            }
                        },
                        mounted: function() {
                            let self = this;
                        }
                    },
                    "component-sets": {
                        props: ["model"],
                        emits: ["column_change_width"],
                        data: function() {
                            var storage_name = "wa_shop_sets_columns",
                                storage = getStorage();

                            $.each(that.header_columns, function(i, column) {
                                var column_storage = storage[column.id];
                                if (column_storage && column_storage.width > 0) {
                                    column.width = column_storage.width;
                                }
                            });

                            return {
                                columns: that.header_columns,
                                storage_name: storage_name,
                                storage: storage
                            };

                            function getStorage() {
                                var storage = localStorage.getItem(storage_name);
                                if (!storage) { storage = {}; }
                                else { storage = JSON.parse(storage); }
                                return storage;
                            }
                        },
                        template: that.components["component-sets"],
                        delimiters: ['{ { ', ' } }'],
                        components: {
                            "component-sets-header-column": {
                                props: {
                                    column: {
                                        type: Object,
                                        default: () => {}
                                    }
                                },
                                data: function() {
                                    return {
                                        states: {
                                            locked: false,
                                            width_timer: 0
                                        }
                                    };
                                },
                                template: that.components["component-sets-header-column"],
                                delimiters: ['{ { ', ' } }'],
                                computed: {
                                    column_width: function() {
                                        let self = this,
                                            result = "";

                                        if (self.column.width > 0) {
                                            result = self.column.width + "px";
                                        } else if (self.column.min_width > 0) {
                                            result = self.column.min_width + "px";
                                        }

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
                            },
                            "component-model-item" : {
                                name: "component-model-item",
                                props: ["item"],
                                data: function() {
                                    return {
                                        root_states: that.states,
                                        states: {
                                            name_locked: false,
                                            edit_locked: false,
                                            clone_locked: false,
                                            delete_locked: false,
                                            highlighted: false
                                        }
                                    }
                                },
                                template: that.components["component-model-item"],
                                delimiters: ['{ { ', ' } }'],
                                components: {
                                    "component-set-sorting": {
                                        props: ["set"],
                                        template: that.components["component-set-sorting"],
                                        delimiters: ['{ { ', ' } }'],
                                        components: { "component-dropdown-set-sorting": that.vue_components["component-dropdown-set-sorting"] },
                                        data: function() {
                                            let self = this,
                                                disabled = false;

                                            return {
                                                options: getOptions(),
                                                disabled: disabled,
                                                states: {
                                                    sort_locked: false
                                                }
                                            };

                                            function getOptions() {
                                                let result;

                                                if (self.set.type === "1") {
                                                    result = that.sort_options.filter( function(option) {
                                                        return (!!option.value.length);
                                                    });
                                                } else {
                                                    disabled = true;
                                                    result = that.sort_options.filter( function(option) {
                                                        return (!option.value.length);
                                                    });
                                                }

                                                return result;
                                            }
                                        },
                                        methods: {
                                            setup: function() {
                                                let self = this;

                                                self.states.sort_locked = true;
                                                $.post(that.urls["set_sort_dialog"], { set_id: self.set.id })
                                                    .always( function() {
                                                        self.states.sort_locked = false;
                                                    })
                                                    .done( function(html) {
                                                        $.waDialog({ html: html })
                                                    });
                                            },
                                            change: function(value) {
                                                let self = this,
                                                    data = { set_id: self.set.id, sort_products: value };

                                                self.states.sort_locked = true;

                                                request(data)
                                                    .always( function() {
                                                        self.states.sort_locked = false;
                                                    })
                                                    .done( function() {
                                                        self.set.sort_products = value;
                                                    });

                                                function request() {
                                                    let deferred = $.Deferred();

                                                    $.post(that.urls["set_sort"], data, "json")
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
                                        }
                                    }
                                },
                                computed: {
                                    item_class: function() {
                                        let self = this,
                                            item = self.item,
                                            result = [];

                                        if (item.states.move) { result.push("is-moving"); }
                                        if (item.states.is_wanted) { result.push("is-wanted"); }
                                        if (item.states.is_wanted_inside && !item.states.expanded) { result.push("is-wanted-inside"); }
                                        if (item.states.highlighted) { result.push("is-highlighted"); }

                                        if (item.states.drop) {
                                            result.push("is-drop");
                                            if (item.is_group && item.states.drop_inside) {
                                                result.push("is-drop-inside");
                                            }
                                        }

                                        return result.join(" ");
                                    }
                                },
                                methods: {
                                    getColumnWidth: function(column_id) {
                                        let self = this,
                                            column = that.header_columns_object[column_id],
                                            result = "";

                                        if (column.width > 0) {
                                            result = column.width + "px";
                                        } else if (column.min_width > 0) {
                                            result = column.min_width + "px";
                                        }

                                        return result;
                                    },
                                    itemExtend: function() {
                                        let self = this;
                                        self.item.states.expanded = !self.item.states.expanded;
                                        that.storage(true);
                                    },
                                    onChangeName: function() {
                                        let self = this;

                                        self.states.name_locked = true;

                                        request()
                                            .always( function () {
                                                self.states.name_locked = false;
                                            });

                                        function request() {
                                            let deferred = $.Deferred();

                                            let data = { name: self.item.name };

                                            if (self.item.is_group) {
                                                data.group_id = self.item.group_id;
                                            } else {
                                                data.set_id = self.item.set_id;
                                            }

                                            $.post(that.urls["set_rename"], data, "json")
                                                .fail( function() { deferred.reject(); })
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
                                    setEdit: function() {
                                        let self = this;

                                        if (self.states.edit_locked) { return false; }

                                        self.states.edit_locked = true;

                                        $.post(that.urls["set_edit_dialog"], { set_id: self.item.set_id }, "json")
                                            .always( function() {
                                                self.states.edit_locked = false;
                                            })
                                            .done( function(html) {
                                                $.waDialog({
                                                    html: html,
                                                    options: {
                                                        initDialog: function(options) {
                                                            that.initSetEditDialog(options);
                                                        },
                                                        onSuccess: function(set) {
                                                            self.item.id = self.item.set_id = set.id;
                                                            self.item.name = set.name;
                                                            self.item.count = set.count;
                                                            self.item.sort_products = set.sort_products;
                                                            // self.item.render_key += 1;
                                                        }
                                                    }
                                                });
                                            });
                                    },
                                    setClone: function() {
                                        let self = this;

                                        if (self.states.clone_locked) { return false; }

                                        if (self.item.type === "0") {
                                            setCloneConfirm().done(runCopy);
                                        } else {
                                            runCopy();
                                        }

                                        function runCopy(copy_products) {
                                            self.states.clone_locked = true;

                                            let data = { set_id: self.item.set_id };

                                            if (typeof copy_products === "string") {
                                                data["copy_products"] = copy_products;
                                            }

                                            request(data)
                                                .always( function() {
                                                    self.states.clone_locked = false;
                                                })
                                                .done( function(new_set) {
                                                    new_set = that.formatModelItem(new_set);
                                                    new_set.states.highlighted = true;

                                                    let index = that.model.indexOf(self.item);
                                                    that.model.splice(index + 1, 0, new_set);

                                                    setTimeout( function() {
                                                        new_set.states.highlighted = false;
                                                    }, 2000);
                                                });
                                        }

                                        1

                                        function request(data) {
                                            let deferred = $.Deferred();

                                            /*
                                            setTimeout(function () {
                                                let new_item = $.wa.clone(self.item);
                                                new_item.id = new_item.set_id = "new_set";
                                                new_item.name = "Новый сет";
                                                deferred.resolve(new_item);
                                            }, 2000);
                                            */

                                            $.post(that.urls["set_clone"], data, "json")
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
                                    itemDelete: function() {
                                        let self = this;

                                        if (self.states.delete_locked) { return false; }

                                        self.states.delete_locked = true;

                                        deleteAction()
                                            .always( function() {
                                                self.states.delete_locked = false;
                                            })
                                            .done( function() {
                                                console.log( "DELETE" );
                                            });

                                        function deleteAction() {
                                            let deferred = $.Deferred();

                                            deleteConfirm()
                                                .fail( function() {
                                                    deferred.reject();
                                                })
                                                .done( function(dialog, locker) {
                                                    deleteRequest()
                                                        .fail( function(errors) {
                                                            console.log("ERROR: server remove error");
                                                            deferred.reject(errors);
                                                        })
                                                        .done( function() {
                                                            deferred.resolve();
                                                            $.wa_shop_products.router.reload().done( function() {
                                                                dialog.close();
                                                            });
                                                        });
                                                });

                                            return deferred.promise();

                                            function deleteConfirm() {
                                                let deferred = $.Deferred(),
                                                    confirmed = false;

                                                $.waDialog({
                                                    html: that.templates["set-delete-confirm"],
                                                    onOpen: initDialog,
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
                                                            let $button = dialog.$wrapper.find(".js-delete-button"),
                                                                $icon = $button.find(".s-icon");

                                                            if (lock) { $icon.show(); } else { $icon.hide(); }

                                                            $button.attr("disabled", lock);
                                                        }
                                                    }
                                                });

                                                function initDialog($dialog, dialog) {
                                                    var $vue_section = $dialog.find(".js-vue-section");

                                                    Vue.createApp({
                                                        data() {
                                                            return {
                                                                set: self.item,
                                                            }
                                                        },
                                                        delimiters: ['{ { ', ' } }'],
                                                        computed: {},
                                                        methods: {
                                                            deleteSet: function() {
                                                                confirmed = true;
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

                                            function deleteRequest() {
                                                let deferred = $.Deferred();

                                                let href = (self.item.is_group ? that.urls["group_remove"] : that.urls["set_remove"]),
                                                    data = {};

                                                if (self.item.is_group) {
                                                    data.group_id = self.item.group_id;
                                                } else {
                                                    data.set_id = self.item.set_id;
                                                }

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
                    addSet: function() {
                        let self = this;

                        if (self.states.add_set_locked) { return false; }

                        self.states.add_set_locked = true;

                        $.post(that.urls["set_edit_dialog"], "json")
                            .always( function() {
                                self.states.add_set_locked = false;
                            })
                            .done( function(html) {
                                $.waDialog({
                                    html: html,
                                    options: {
                                        initDialog: function(options) {
                                            that.initSetEditDialog(options);
                                        },
                                        onSuccess: function(set) {
                                            set = that.formatModelItem(set);
                                            set.states.count_locked = false;
                                            that.model.unshift(set);
                                        }
                                    }
                                });
                            });
                    },
                    addGroup: function() {
                        let self = this;

                        if (self.states.add_group_locked) { return false; }

                        self.states.add_group_locked = true;

                        request()
                            .always( function() {
                                self.states.add_group_locked = false;
                            })
                            .done( function(model_item) {
                                model_item = that.formatModelItem(model_item);
                                that.model.unshift(model_item);
                            });

                        function request() {
                            let deferred = $.Deferred();

                            $.post(that.urls["group_add"], {}, "json")
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
                    sortSets: function(sort) {
                        let self = this;

                        if (self.states.sort_locked) { return false; }

                        self.states.sort_locked = true;

                        request({ sort: sort })
                            .fail( function(errors) {
                                self.states.sort_locked = false;
                                console.log("ERROR: server remove error");
                            })
                            .done( function() {
                                $.wa_shop_products.router.reload();
                            });

                        function request(data) {
                            let deferred = $.Deferred();

                            $.post(that.urls["sets_sort"], data, "json")
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
                delimiters: ['{ { ', ' } }'],
                created: function () {
                    $vue_section.css("visibility", "");
                },
                mounted: function() {
                    that.init();
                }
            });

            return $.vue_app.mount($vue_section[0]);
        };

        Page.prototype.formatModelItem = function(item) {
            let that = this;

            item.states = {
                expanded: false,
                count_locked: !item.is_group,
                // Поля для поиска
                is_wanted: false,
                is_wanted_inside: false,
                // Поля для перетаскивания
                move_locked: false,
                move: false,
                //
                drop: false,
                drop_inside: false
            };
            item.render_key = 0;

            if (!item.is_group && typeof item.sort_products !== "string") {
                item.sort_products = "";
            }

            if (item.set_id) {
                that.sets[item.set_id] = item;
            } else if (item.group_id) {
                that.groups[item.group_id] = item;
            }

            return item;
        };

        Page.prototype.initSetEditDialog = function(options) {
            let that = this;

            let $dialog    = options.$dialog,
                dialog     = options.dialog,
                set        = options.set,
                is_new     = options.is_new,
                components = options.components;

            let $vue_section = $dialog.find(".js-vue-section");

            dialog.vue_model = Vue.createApp({
                delimiters: ['{ { ', ' } }'],
                data() {
                    return {
                        set: formatSet(set),
                        init_set: null,
                        states: {
                            locked: false,
                            transliterate_xhr: null,
                            transliterate_timer: null
                        },
                        errors: {}
                    }
                },
                components: {
                    "component-radio": that.vue_components["component-radio"],
                    "component-datepicker": {
                        props: ["modelValue", "disabled", "name"],
                        emits: ["update:modelValue", "change"],
                        template: that.components["component-datepicker"],
                        delimiters: ['{ { ', ' } }'],
                        computed: {
                            prop_name() { return (typeof this.name === "string" ? this.name : "") },
                            prop_disabled() { return (typeof this.disabled === "boolean" ? this.disabled : false) }
                        },
                        mounted: function() {
                            let self = this;

                            if (self.prop_disabled) { return false; }

                            $(self.$el).find(".js-date-picker").each( function(i, field) {
                                let $field = $(field),
                                    $alt_field = $field.parent().find("input[type='hidden']");

                                $field.datepicker({
                                    altField: $alt_field,
                                    altFormat: "yy-mm-dd",
                                    changeMonth: true,
                                    changeYear: true,
                                    beforeShow:function(field, instance){
                                        let $calendar = $(instance.dpDiv[0]);
                                        let index = 2001;
                                        setTimeout( function() {
                                            $calendar.css("z-index", index);
                                        }, 10);
                                    },
                                    onClose: function(field, instance) {
                                        let $calendar = $(instance.dpDiv[0]);
                                        $calendar.css("z-index", "");
                                    }
                                });

                                if (self.modelValue) {
                                    let date = self.formatDate(self.modelValue);
                                    $field.datepicker( "setDate", date);
                                }

                                $field.on("change", function() {
                                    let field_value = $field.val();
                                    if (!field_value) { $alt_field.val(""); }
                                    let value = $alt_field.val();
                                    self.$emit("update:modelValue", value);
                                    self.$emit("change");
                                });
                            })
                        },
                        methods: {
                            formatDate(date_string) {
                                if (typeof date_string !== "string") { return null; }

                                let date_array = date_string.split("-"),
                                    year = date_array[0],
                                    mount = date_array[1] - 1,
                                    day = date_array[2];

                                return new Date(year, mount, day);
                            }
                        }
                    },
                    "component-dropdown-rules": {
                        props: ["modelValue"],
                        emits: ["update:modelValue", "change"],
                        data: function() {
                            return { items: that.rule_options };
                        },
                        template: components["component-dropdown-rules"],
                        delimiters: ['{ { ', ' } }'],
                        computed: {
                            active_item: function() {
                                let self = this,
                                    active_item = self.items[0];

                                if (typeof self.modelValue === "string") {
                                    let filter_item_search = self.items.filter(function (item) {
                                        return (item.value === self.modelValue);
                                    });
                                    active_item = (filter_item_search.length ? filter_item_search[0] : active_item);
                                }

                                return active_item;
                            }
                        },
                        methods: {
                            change: function(item) {
                                let self = this;
                                self.$emit("update:modelValue", item.value);
                                self.$emit("change", item.value);
                                self.dropdown.hide();
                            }
                        },
                        mounted: function() {
                            this.dropdown = $(this.$el).waDropdown({ hover : false }).waDropdown("dropdown");
                        }
                    },
                    "component-flex-textarea": {
                        props: ["modelValue", "placeholder", "cancel", "focus", "rows"],
                        emits: ["update:modelValue", "change", "ready", "cancel", "blur", "focus"],
                        data: function() {
                            return {
                                offset: 0,
                                textarea: null,
                                start_value: null
                            };
                        },
                        template: `<textarea v-bind:placeholder="placeholder" v-bind:value="modelValue" v-on:input="onInput" v-on:keydown.esc="onEsc" v-on:blur="onBlur" v-on:focus="onFocus"></textarea>`,
                        delimiters: ['{ { ', ' } }'],
                        computed: {
                            prop_focus() { return (typeof this.focus === "boolean" ? this.focus : false); },
                            prop_cancel() { return (typeof this.cancel === "boolean" ? this.cancel : false); },
                            prop_rows() { return (typeof this.rows === "string" ? this.rows : ""); }
                        },
                        methods: {
                            onInput: function($event) {
                                var self = this;
                                self.update();
                                self.$emit('update:modelValue', $event.target.value);
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
                            onFocus: function($event) {
                                var self = this;
                                self.start_value = $event.target.value;
                                self.$emit("focus");
                            },
                            onEsc: function($event) {
                                var self = this;
                                if (self.prop_cancel) {
                                    $event.target.value = self.value = self.start_value;
                                    self.$emit('update:modelValue', self.value);
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
                                if (self.prop_rows === '1') {
                                    textarea.css("white-space", "nowrap");
                                }
                            }
                        },
                        mounted: function() {
                            var self = this,
                                textarea = self.textarea = $(self.$el);

                            setTimeout(function() {
                                self.offset = textarea[0].offsetHeight - textarea[0].clientHeight;
                                self.update();
                                textarea.trigger("focus");
                                self.$emit("ready", self.$el.value);
                            }, 0);
                        }
                    },
                    "component-dropdown-set-edit-sorting": {
                        props: ["modelValue", "disabled"],
                        emits: ["update:modelValue", "change"],
                        template: that.components["component-dropdown-set-sorting"],
                        delimiters: ['{ { ', ' } }'],
                        data() {
                            return {
                                button_class: ""
                            }
                        },
                        computed: {
                            prop_disabled() { return (typeof this.disabled === "boolean" ? this.disabled : false) },
                            active_item: function() {
                                let self = this,
                                    active_item = self.items[0];

                                if (typeof self.modelValue === "string") {
                                    let filter_item_search = self.items.filter(function (item) {
                                        return (item.value === self.modelValue);
                                    });
                                    active_item = (filter_item_search.length ? filter_item_search[0] : active_item);
                                }

                                return active_item;
                            },
                            items: function() {
                                let self = this, result;

                                if (!self.prop_disabled) {
                                    result = that.sort_options.filter( function(option) {
                                        return (!!option.value.length);
                                    });
                                } else {
                                    result = that.sort_options.filter( function(option) {
                                        return (!option.value.length);
                                    });
                                }

                                return result;
                            }
                        },
                        methods: {
                            change: function(item) {
                                let self = this;
                                self.$emit("update:modelValue", item.value);
                                self.$emit("change", item.value);
                                self.dropdown.hide();
                            }
                        },
                        mounted: function () {
                            let self = this;

                            self.dropdown = $(self.$el).waDropdown({ hover : false }).waDropdown("dropdown");
                        }
                    },
                },
                computed: {
                    refresh_key: function() {
                        // for reloading component
                        return JSON.stringify(this.set);
                    },
                    use_transliterate: function() {
                        const self = this;
                        return self.set.id === null || !(self.set.id.length > 0);
                    },
                    has_errors: function() {
                        const self = this;

                        var result = !!Object.keys(self.errors).length;

                        if (!self.set.name.length) { result = true; }

                        return result;
                    },
                    status_change_class: function() {
                        return this.init_set === JSON.stringify(this.set) ? 'green' : 'yellow';
                    }
                },
                watch: {
                    "set.date_start": function(newVal, oldVal) {
                        if (!newVal || !this.set.date_end) {
                            return;
                        }

                        if (new Date(newVal) > new Date(this.set.date_end)) {
                            this.set.date_start = this.set.date_end;
                            this.set.date_end = newVal;
                        }
                    },
                    "set.date_end": function(newVal, oldVal) {
                        if (!newVal || !this.set.date_start) {
                            return;
                        }

                        if (new Date(newVal) < new Date(this.set.date_start)) {
                            this.set.date_end = this.set.date_start;
                            this.set.date_start = newVal;
                        }
                    },
                    "set.type": function() {
                        let self = this;
                        self.$nextTick( function() {
                            dialog.resize();
                        });
                    },
                    "set.name": function(value) {
                        const self = this;

                        if (!value.length) {
                            self.errors["set_name"] = {
                                id: "set_name",
                                text: that.locales["error_set_name_empty"]
                            };
                        } else if (value.length > 255) {
                            self.errors["set_name"] = {
                                id: "set_name",
                                text: that.locales["error_set_name"]
                            };
                        } else {
                            delete self.errors["set_name"];
                        }
                    },
                    "set.id": function(value) {
                        const self = this;

                        if (!value.length) {
                            self.errors["set_id"] = {
                                id: "set_id",
                                text: that.locales["error_set_id_empty"]
                            };
                        } else if (value.length > 64) {
                            self.errors["set_id"] = {
                                id: "set_id",
                                text: that.locales["error_set_id"]
                            };
                        } else {
                            delete self.errors["set_id"];
                        }
                    }
                },
                methods: {
                    onChangeName: function() {
                        var self = this;
                        if (self.use_transliterate) {
                            self.getIdByName(1000)
                                .done( function(id) {
                                    if (self.use_transliterate) {
                                        self.set.id = id;
                                    }
                                });
                        }
                    },
                    onBlurName: function() {
                        var self = this;
                        if (!self.set.name.length && !self.errors["set_name"]) {
                            self.errors["set_name"] = {
                                id  : "set_name",
                                text: that.locales["error_set_name_empty"]
                            };
                        }
                    },
                    onBlurId: function() {
                        var self = this;
                        if (!self.set.id.length && !self.errors["set_id"]) {
                            self.errors["set_id"] = {
                                id  : "set_id",
                                text: that.locales["error_set_id_empty"]
                            };
                        }
                    },
                    getIdByName: function(time) {
                        const self = this;
                        var deferred = $.Deferred();

                        var time = (typeof time === "number" ? time : 0);

                        clearTimeout(self.states.transliterate_timer);

                        if (!$.trim(self.set.name).length) {
                            deferred.reject();
                        } else {
                            if (self.states.transliterate_xhr) {
                                self.states.transliterate_xhr.abort();
                            }

                            self.states.transliterate_timer = setTimeout( function() {
                                self.states.transliterate_xhr = $.get(that.urls["transliterate"], { str: self.set.name }, "json")
                                    .always( function() { self.states.transliterate_xhr = null; })
                                    .fail( function() { deferred.reject(); })
                                    .done( function(response) { deferred.resolve(response.data); });
                            }, time);
                        }

                        return deferred.promise();
                    },

                    save: function() {
                        let self = this,
                            $form = $dialog.find("form:first");

                        if (!self.states.locked) {
                            self.states.locked = true;
                            let data = $form.serializeArray();
                            request(data)
                                .always( function() {
                                    self.states.locked = false;
                                })
                                .fail( function(errors) {
                                    if (!Array.isArray(errors) || !errors.length) {
                                        return;
                                    }

                                    const typesErrors = {
                                        set_name: ['incorrect_length_name'],
                                        set_id: ['incorrect_length_id', 'incorrect_id', 'set_not_found', 'id_in_use']
                                    };
                                    errors.forEach(error => {
                                        for (const [type, typeErrors] of Object.entries(typesErrors)) {
                                            if (typeErrors.includes(error.id)) {
                                                self.errors[type] = {
                                                    id: type,
                                                    text: error.text
                                                };
                                            }
                                        }
                                    });
                                })
                                .done( function(set) {
                                    dialog.options.onSuccess(set);
                                    dialog.close();
                                });
                        }

                        function request(data) {
                            let deferred = $.Deferred();

                            let href = (is_new ? that.urls["set_add"] : that.urls["set_edit"]);

                            $.post(href, data, "json")
                                .fail( function() { deferred.reject(); })
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
                    resizeDialog: function() {
                        const self = this;
                        self.$nextTick( function() {
                            setTimeout( function() {
                                dialog.resize();
                            }, 100);
                        });
                    }
                },
                created: function () {
                    $vue_section.css("visibility", "");
                    this.init_set = JSON.stringify(this.set);
                },
                mounted: function() {
                    var self = this;

                    self.resizeDialog();
                }
            }).mount($vue_section[0]);

            console.log( dialog );

            function formatSet(set) {
                set.date_start = "";
                set.date_end = "";
                if (set.json_params) {
                    set.date_start = (set.json_params["date_start"] ? set.json_params["date_start"] : "");
                    set.date_end = (set.json_params["date_end"] ? set.json_params["date_end"] : "");
                }

                return set;
            }
        };

        Page.prototype.initDragAndDrop = function() {
            let that = this;

            let $document = $(document);

            let drag_data = {},
                over_locked = false,
                is_changed = false,
                timer = 0;

            let $wrapper = that.$wrapper,
                $droparea = $("<div />", { class: "s-drop-area js-drop-area" });

            // Drop по зоне триггерит дроп по привязанной категории
            $droparea
                .on("dragover", function(event) {
                    event.preventDefault();
                })
                .on("drop", function() {
                    let $drop_item = $droparea.data("drop_item");
                    if ($drop_item) {
                        $drop_item.trigger("drop");
                    } else {
                        console.log( $droparea );
                        console.log("Drop ERROR #1");
                    }
                });

            $wrapper.on("dragstart", ".js-item-move-toggle", function(event) {
                let $move = $(this).closest(".s-item-wrapper");

                let item = getItem($move);

                if (!item) {
                    console.error("ERROR: item isn't exist");
                    return false;
                }

                event.originalEvent.dataTransfer.setDragImage($move[0], 20, 20);

                item.states.move = true;
                drag_data.move_item = item;

                $document.on("drop", ".s-item-wrapper", onDrop);
                $document.on("dragover", ".s-item-wrapper", onDragOver);
                $document.on("dragend", onDragEnd);
            });

            function onDrop(event) {
                let $item = $(this);
                moveItem($item);
                off();
            }

            function onDragOver(event) {
                event.preventDefault();

                if (!drag_data.move_item) { return false; }

                if (!over_locked) {
                    over_locked = true;

                    let $item = $(this),
                        drop_item = getItem($item);

                    if (drag_data.drop_item && drag_data.drop_item !== drop_item) {
                        drag_data.drop_item.states.drop = false;
                        drag_data.drop_item.states.drop_inside = false;
                    }
                    drag_data.drop_item = drop_item;

                    let mouse_y = event.originalEvent.pageY,
                        item_offset = $item.offset(),
                        item_height = $item.outerHeight(),
                        item_y = item_offset.top,
                        x = (mouse_y - item_y),
                        before = ( x < (item_height * (drop_item.is_group ? 0.3 : 0.5)) );

                    drop_item.states.drop = true;
                    drop_item.states.drop_inside = !before;

                    if (before !== drag_data.before) {
                        if (before) {
                            $item.before($droparea);
                        } else {
                            if (drop_item.is_group) {
                                var is_exist = $.contains(document, $droparea[0]);
                                if (is_exist) { $droparea.detach(); }
                            } else {
                                $item.after($droparea);
                            }
                        }
                        $droparea.data("drop_item", $item);
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

            function moveItem($drop_item) {
                let drop_item = getItem($drop_item),
                    move_item = drag_data.move_item,
                    before = drag_data.before;

                if (!drop_item) {
                    console.error("ERROR: set isn't exist");
                    return false;
                }

                if (move_item === drop_item) {
                    return false;
                } else {
                    set(move_item, drop_item, drag_data.before);

                    moveRequest(move_item);
                    onDragEnd();
                }

                function set(move_item, drop_item, before) {
                    let new_index;

                    // Удаляем item из модели
                    remove(move_item);

                    // Если кидаем список на группу
                    if (drop_item.is_group && !move_item.is_group) {
                        // если кидаем на верхнюю часть, то вставляем перед группой
                        if (before) {
                            move_item.group_id = null;
                            new_index = that.model.indexOf(drop_item);
                            that.model.splice(new_index, 0, move_item);
                        // если кидаем на нижнуюю часть, то вставляем внутрь группы
                        } else {
                            move_item.group_id = drop_item.group_id;
                            drop_item.sets.unshift(move_item);
                            drop_item.states.expanded = true;
                        }
                    // если кидаем список на список или группу на список
                    } else {
                        if (!move_item.is_group) {
                            move_item.group_id = null;
                        }
                        let target_array = that.model;
                        new_index = target_array.indexOf(drop_item);
                        new_index += (before ? 0 : 1);
                        target_array.splice(new_index, 0, move_item);
                    }

                    function remove(move_item) {
                        let target_array = that.model;
                        if (!move_item.is_group && move_item.group_id) {
                            target_array = that.groups[move_item.group_id]["sets"];
                        }

                        let index = target_array.indexOf(move_item);
                        if (index >= 0) {
                            target_array.splice(index, 1);
                        } else {
                            console.log( "ERROR: remove group from array" );
                        }
                    }
                }
            }

            function moveRequest(move_item) {
                let deferred = $.Deferred();

                let data = getData(move_item);

                that.states.move_locked = true;

                $.post(that.urls["set_move"], data, "json")
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
                            alert("Error: set move");
                            console.log(response.errors);
                            deferred.reject(response.errors);
                        }
                    });

                return deferred.promise();

                function getData(move_item) {
                    let result = [];

                    result.push({
                        name: "id",
                        value: (move_item.is_group ? move_item.group_id : move_item.set_id)
                    });
                    result.push({
                        name: "parent_id",
                        value: move_item.group_id
                    });
                    result.push({
                        name: "is_group",
                        value: (move_item.is_group ? 1: 0)
                    });

                    let target_array = that.model;
                    if (!move_item.is_group && move_item.group_id) {
                        target_array = that.groups[move_item.group_id]["sets"];
                    }

                    $(target_array).each( function(i, item) {
                        let tail = "sort["+i+"]";
                        result.push({
                            name: tail + "[type]",
                            value: (item.is_group ? "group" : "set")
                        });
                        result.push({
                            name: tail + "[id]",
                            value: (item.is_group ? item.group_id : item.set_id)
                        });
                    });

                    return result;
                }
            }

            function getItem($item) {
                let result = null;

                let set_id = $item.data("set-id"),
                    group_id = $item.data("group-id"),
                    is_group = $item.data("is-group");

                if (is_group) {
                    result = (that.groups[group_id] ? that.groups[group_id] : null);
                } else {
                    result = (that.sets[set_id] ? that.sets[set_id] : null);
                }

                return result;
            }

            function off() {
                let is_exist = $.contains(document, $droparea[0]);
                if (is_exist) { $droparea.detach(); }

                drag_data = {};
                $document.off("drop", ".s-item-wrapper", onDrop);
                $document.off("dragover", ".s-item-wrapper", onDragOver);
                $document.off("dragend", onDragEnd);

                $.each(that.sets, function(i, set) {
                    set.states.move = false;
                    set.states.drop = false;
                    set.states.drop_inside = false;
                });

                $.each(that.groups, function(i, group) {
                    group.states.move = false;
                    group.states.drop = false;
                    group.states.drop_inside = false;
                });
            }
        };

        Page.prototype.storage = function(update) {
            let that = this,
                storage_name = "shop/products/sets";

            update = (typeof update === "boolean" ? update : false);
            return (update ? setter() : getter());

            function setter() {
                var value = getValue();
                value = JSON.stringify(value);

                localStorage.setItem(storage_name, value);

                function getValue() {
                    let result = { expanded_groups: [] };

                    $.each(that.groups, function(i, group) {
                        if (group.states.expanded) {
                            result.expanded_groups.push(group.id);
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

        Page.prototype.updateSetsCount = function(sets_ids) {
            sets_ids = (Array.isArray(sets_ids) ? sets_ids : []);

            var that = this,
                deferred = $.Deferred();

            var get_ids = getIds(sets_ids),
                static_ids = get_ids.static_ids,
                dynamic_ids = get_ids.dynamic_ids,
                limit = 100,
                recount_offset = 0,
                is_static = true;

            if (static_ids.length) {
                recountSets();
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
                    recountSets();
                }
            }

            function recountSets() {
                var data = is_static ? {
                    static_ids: static_ids.slice(recount_offset, recount_offset + limit)
                } : {
                    dynamic_ids: dynamic_ids.slice(recount_offset, recount_offset + limit)
                };
                recount_offset += limit;
                request(data).done( function() {
                    if ((is_static ? static_ids.length : dynamic_ids.length) > recount_offset) {
                        recountSets();
                    } else {
                        deferred.resolve();
                    }
                });
            }

            function request(data) {
                var ids = static_ids;
                if (data.dynamic_ids) { ids = data.dynamic_ids; }
                else if (data.static_ids) { ids = data.static_ids; }

                $.each(ids, function(i, set_id) {
                    var set = that.sets[set_id];
                    if (set) { set.states.count_locked = true; }
                });

                return $.post(that.urls["set_recount"], data, "json")
                    .done( function(response) {
                        var count_data = response.data.sets;

                        $.each(count_data, function(i, set_data) {
                            var set = that.sets[set_data.id];
                            if (set) { set.count = set_data.count; }
                        });

                        $.each(ids, function(i, set_id) {
                            var set = that.sets[set_id];
                            if (set) { set.states.count_locked = false; }
                        });
                    });
            }

            function getIds(sets_ids) {
                var static_ids = [],
                    dynamic_ids = [];

                $.each(that.sets, function(i, set) {
                    if (!sets_ids.length || sets_ids.indexOf(set.id) >= 0) {
                        if (set.type === "1") {
                            dynamic_ids.push(set.id);
                        } else {
                            static_ids.push(set.id);
                        }
                    }
                });

                return {
                    static_ids: static_ids,
                    dynamic_ids: dynamic_ids
                }
            }
        };

        return Page;

    })($);

    $.wa_shop_products.init.initProductsSetsPage = function(options) {
        return new Page(options);
    };

})(jQuery);
