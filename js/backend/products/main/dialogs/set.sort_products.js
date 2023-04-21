( function($) {

    var Dialog = ( function($) {

        Dialog = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];

            // CONST
            that.dialog = that.$wrapper.data("dialog");
            that.components = options["components"];
            that.urls = options["urls"];

            // VUE MODEL
            that.set = options["set"];
            that.products = formatProducts(options["products"]);
            that.render_products = options["render_products"];
            that.sort_options = options["sort_options"];
            that.states = {
                move_locked: false,
                force_locked: false,
                reload_locked: false,
                sort_locked: false
            };

            // INIT
            that.vue_model = that.initVue();

            function formatProducts(products) {
                $.each(products, function(i, product) {
                    product.states = {
                        moving: false,
                        selected: false,
                        move_locked: false
                    };
                });

                return products;
            }
        };

        Dialog.prototype.initVue = function() {
            var that = this;

            var $vue_section = that.$wrapper.find(".js-vue-section");

            const app = Vue.createApp({
                data() {
                    return {
                        set: that.set,
                        products: that.products,
                        render_products: that.render_products,
                        sort: "",
                        states: that.states
                    }
                },
                delimiters: ['{ { ', ' } }'],
                components: {
                    "component-checkbox": {
                        props: ["modelValue", "label", "disabled", "field_id"],
                        emits: ["update:modelValue", "change"],
                        data: function() {
                            var self = this;
                            return {
                                tag: (self.label !== false ? "label" : "span"),
                                id: (typeof self.field_id === "string" ? self.field_id : ""),
                                prop_disabled: (typeof self.disabled === "boolean" ? self.disabled : false)
                            }
                        },
                        template: that.components["component-checkbox"],
                        delimiters: ['{ { ', ' } }'],
                        methods: {
                            onChange: function(checked) {
                                var self = this;
                                self.$emit("update:modelValue", checked);
                                self.$emit("change", checked);
                            }
                        }
                    },

                    "component-dropdown-products-sorting": {
                        props: ["modelValue"],
                        emits: ["update:modelValue", "change"],
                        data: function() {
                            return {
                                options:  that.sort_options
                            }
                        },
                        template: that.components["component-dropdown-products-sorting"],
                        delimiters: ['{ { ', ' } }'],
                        computed: {
                            active_option: function() {
                                var self = this,
                                    option_search = self.options.filter( function(option) { return (self.modelValue === option.value); });
                                return (option_search ? option_search[0] : self.options[0]);
                            }
                        },
                        methods: {
                            change: function(option) {
                                var self = this;
                                self.dropdown.hide();
                                self.$emit("update:modelValue", option.value);
                                self.$emit("change", option.value);
                            }
                        },
                        mounted: function() {
                            var self = this,
                                $dropdown = $(self.$el).find(".js-dropdown");

                            self.dropdown = $dropdown.waDropdown({ hover: false }).waDropdown("dropdown");
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
                watch: {
                    "selected_products": function(value, old_value) {
                        if (value.length === 0 || old_value.length === 0) {
                            this.resize();
                        }
                    }
                },
                methods: {
                    onSelect: function(product) {
                    },
                    revert: function() {
                        var self = this;

                        $(self.selected_products).each( function(i, product) {
                            product.states.selected = false;
                        });
                    },

                    moveUp: function() {
                        var self = this,
                            products = [];

                        $.each(self.selected_products, function(i, product) {
                            products.unshift(product);
                        });

                        $.each(products, function(i, product) {
                            self.moveProduct(product, false);
                            product.states.selected = false;
                            product.states.move_locked = true;
                        });

                        self.moveRequest();
                    },
                    moveDown: function() {
                        var self = this,
                            products = [];

                        $.each(self.selected_products, function(i, product) {
                            products.push(product);
                        });

                        $.each(products, function(i, product) {
                            self.moveProduct(product, true);
                            product.states.selected = false;
                            product.states.move_locked = true;
                        });

                        self.moveRequest();
                    },
                    moveProduct: function(product, push) {
                        var self = this;

                        var move_index = self.products.indexOf(product);
                        self.products.splice(move_index, 1);

                        if (push) {
                            self.products.push(product);
                        } else {
                            self.products.unshift(product);
                        }
                    },
                    moveRequest: function() {
                        var self = this;

                        if (!self.states.sort_locked) {
                            self.states.sort_locked = true;

                            var product_ids = self.products.map( function(product) {
                                return product.id;
                            });

                            request({ set_id: self.set.id, products: product_ids })
                                .fail( function(errors) {
                                    self.states.sort_locked = false;
                                    console.log("ERRORS:", errors);
                                })
                                .always( function() {
                                    self.states.sort_locked = false;
                                    $.each(self.products, function(i, product) {
                                        product.states.move_locked = false;
                                    });

                                });
                        }

                        function request(data) {
                            var deferred = $.Deferred();

                            $.post(that.urls["sort"], data, "json")
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

                    changeSort: function(sort_id) {
                        var self = this;

                        if (!self.states.locked) {
                            self.states.locked = true;
                            self.states.sort_locked = true;

                            request({ set_id: self.set.id, sort_products: sort_id })
                                .fail( function(errors) {
                                    self.states.locked = false;
                                    self.states.sort_locked = false;
                                    console.log("ERRORS:", errors);
                                })
                                .always( function() {
                                    self.reload({ set_id: self.set.id, force: (self.render_products ? 1 : 0) });
                                });

                            function request(data) {
                                var deferred = $.Deferred();

                                $.post(that.urls["sort"], data, "json")
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
                    forceRender: function() {
                        var self = this;

                        if (!self.states.locked) {
                            self.states.locked = true;
                            self.states.force_locked = true;
                            self.reload({ set_id: self.set.id, force: 1 });
                        }
                    },

                    resize: function() {
                        var self = this;

                        self.$nextTick( function() {
                            var $content = that.$wrapper.find(".dialog-content"),
                                scroll_top = $content.scrollTop();
                            that.dialog.resize();
                            $content.scrollTop(scroll_top);
                        });
                    },
                    reload: function(data) {
                        var self = this;

                        if (!self.states.reload_locked) {
                            self.states.reload_locked = true;

                            $.post(that.urls["set_sort_dialog"], data)
                                .always( function() {
                                    self.states.reload_locked = false;
                                })
                                .done( function(html) {
                                    that.dialog.animate = false;
                                    that.dialog.close();
                                    $.waDialog({
                                        html: html,
                                        animate: false,
                                        onOpen: function($dialog, dialog) {
                                            setTimeout( function() {
                                                dialog.animate = true;
                                                $dialog.addClass("is-animated");
                                            }, 2000);
                                        }
                                    });
                                });
                        }
                    }
                },
                created: function () {
                    $vue_section.css("visibility", "");
                },
                mounted: function () {
                    that.init(this);
                }
            })

            app.config.compilerOptions.whitespace = 'preserve';

            return app.mount($vue_section[0]);
        };

        Dialog.prototype.init = function(vue_model) {
            var that = this;

            that.initDragAndDrop(vue_model);
        };

        Dialog.prototype.initDragAndDrop = function(vue_model) {
            var that = this;

            var $document = $(document);

            var drag_data = {},
                over_locked = false,
                timer = 0;

            var move_class = "is-moving";

            that.$wrapper.on("dragstart", ".js-product-move-toggle", function(event) {
                var $product = $(this).closest(".s-product-wrapper");

                var product_id = "" + $product.attr("data-id"),
                    product = getProduct(product_id);

                if (!product) {
                    console.error("ERROR: product isn't exist");
                    return false;
                }

                event.originalEvent.dataTransfer.setDragImage($product[0], 20, 20);

                $.each(vue_model.products, function(i, product) {
                    product.states.moving = (product.id === product_id);
                });

                drag_data.move_product = product;

                $document.on("dragover", ".s-product-wrapper", onDragOver);
                $document.on("dragend", onDragEnd);
            });

            function onDragOver(event) {
                event.preventDefault();

                if (!drag_data.move_product) { return false; }

                if (!over_locked) {
                    over_locked = true;
                    moveProduct( $(event.currentTarget) );
                    setTimeout( function() {
                        over_locked = false;
                    }, 100);
                }
            }

            function onDragEnd() {
                drag_data.move_product.states.moving = false;
                drag_data.move_product.states.move_locked = true;
                drag_data = {};
                $document.off("dragover", ".s-product-wrapper", onDragOver);
                $document.off("dragend", onDragEnd);

                vue_model.moveRequest();
            }

            function moveProduct($over) {
                var product_id = "" + $over.attr("data-id"),
                    product = getProduct(product_id);

                if (!product) {
                    console.error("ERROR: product isn't exist");
                    return false;
                }

                if (drag_data.move_product === product) { return false; }

                var move_index = vue_model.products.indexOf(drag_data.move_product),
                    over_index = vue_model.products.indexOf(product),
                    before = (move_index > over_index);

                if (over_index !== move_index) {
                    vue_model.products.splice(move_index, 1);

                    over_index = vue_model.products.indexOf(product);
                    var new_index = over_index + (before ? 0 : 1);

                    vue_model.products.splice(new_index, 0, drag_data.move_product);

                    that.$wrapper.trigger("change");
                }
            }

            function getProduct(product_id) {
                var result = null;

                $.each(vue_model.products, function(i, product) {
                    product.id = (typeof product.id === "number" ? "" + product.id : product.id);
                    if (product.id === product_id) {
                        result = product;
                        return false;
                    }
                });

                return result;
            }
        };

        return Dialog;

    })($);

    $.wa_shop_products.init.initSetSortDialog = function(options) {
        return new Dialog(options);
    };

})(jQuery);
