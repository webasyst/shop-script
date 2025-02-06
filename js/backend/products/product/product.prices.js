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
            that.stocks_array = options["stocks"];
            that.stocks = $.wa.construct(that.stocks_array, "id");
            that.product = formatProduct(options["product"]);
            that.filters = options["filters"];
            that.prices_model = formatPricesModel(options["prices_model"]);
            that.promos_model = formatPromosModel(options["promos_model"]);
            that.errors = {};
            that.errors_global = [];

            // INIT
            that.vue_model = that.initVue();
            that.init();

            function formatProduct(product) {
                $.each(product.skus, function(i, sku) {
                    sku.is_visible = true;
                    $.each(sku.modifications, function(i, sku_mod) {
                        formatModification(sku_mod);
                    });
                });

                return product;
            }

            function formatModification(sku_mod) {
                var stocks = that.stocks;

                sku_mod.is_visible = true;
                sku_mod.is_highlighted = false;
                sku_mod.expanded = false;
                sku_mod.stocks_data = {
                    expanded: false,
                    render_key: 1,
                    indicator: 1
                };

                // Контейнер для акций в которых участвует модификация
                sku_mod.promos = {};

                sku_mod.count = (sku_mod.count > 0 ? $.wa.validate("float", sku_mod.count) : sku_mod.count);

                if (typeof sku_mod.stock !== "object" || Array.isArray(sku_mod.stock)) {
                    sku_mod.stock = {};
                }

                $.each(stocks, function(stock_id, stock_count) {
                    if (typeof sku_mod.stock[stock_id] === "undefined") {
                        sku_mod.stock[stock_id] = "";
                    }
                });

                if (!$.wa_shop_products.stockVerification(sku_mod.stock, that.stocks)) {
                    return;
                }

                updateVirtualStockValue(sku_mod);

                that.updateModificationStocks(sku_mod);

                return sku_mod;

                function updateVirtualStockValue(sku_mod) {
                    var virtual_stocks = [];

                    $.each(sku_mod.stock, function(stock_id, stock_value) {
                        var stock = that.stocks[stock_id];
                        if (stock.is_virtual) {
                            virtual_stocks.push(stock);
                        }
                    });

                    $.each(virtual_stocks, function(i, stock) {
                        var value = "";

                        if (stock.substocks) {
                            $.each(stock.substocks, function(j, sub_stock_id) {
                                var sub_stock_value = sku_mod.stock[sub_stock_id];
                                sub_stock_value = parseFloat(sub_stock_value);
                                if (!isNaN(sub_stock_value)) {
                                    if (!value) { value = 0; }
                                    value += sub_stock_value;
                                } else {
                                    value = "";
                                    return false;
                                }
                            });
                        }

                        sku_mod.stock[stock.id] = $.wa.validate("float", value);
                    });
                }
            }

            function formatPricesModel(prices_model) {
                var storage_data = that.storage("prices_model");
                prices_model.is_extended = (typeof storage_data === "boolean" ? storage_data : true);
                prices_model.skus = that.product.skus;
                return prices_model;
            }

            function formatPromosModel(promos_model) {
                var storage_data = that.storage("promos_model");
                promos_model.is_extended = (typeof storage_data === "boolean" ? storage_data : true);

                $.each(promos_model.promos, function(i, promo) {
                    promo.skus = [];
                    promo.is_visible = true;

                    // поля для раскрывашки витрин
                    promo.storefronts_limit = 3;
                    var storefronts_extended = that.storage("promo["+promo.id+"]storefronts_extended");
                    promo.storefronts_extended = (typeof storefronts_extended === "boolean" ? storefronts_extended : false);

                    $.each(that.product.skus, function(i, sku) {
                        var promo_sku_mods = [];
                        $.each(sku.modifications, function(i, sku_mod) {
                            if (sku_mod.promos_values) {
                                $.each(sku_mod.promos_values, function(promo_id, promo_values) {
                                    if (promo_id === promo.id) {
                                        // Добавляем ссылки на промоакции, где участвует модификация
                                        if (!sku_mod.promos[promo.status]) { sku_mod.promos[promo.status] = [];}
                                        sku_mod.promos[promo.status].push(promo.id);

                                        var clone_sku_mod = $.wa.clone(sku_mod);
                                        clone_sku_mod.stock = sku_mod.stock;
                                        clone_sku_mod.is_visible = true;
                                        clone_sku_mod.promo_status = promo.status;
                                        clone_sku_mod.price_promo = (promo_values.price ? promo_values.price : null);
                                        clone_sku_mod.compare_price_promo = (promo_values.compare_price ? promo_values.compare_price : null);
                                        clone_sku_mod.promos = null;
                                        promo_sku_mods.push(clone_sku_mod);
                                    }
                                });
                            }
                        });

                        if (promo_sku_mods.length) {
                            promo.skus.push({
                                sku: sku.sku,
                                name: sku.name,
                                is_visible: true,
                                modifications: promo_sku_mods
                            });
                        }
                    });
                });

                return promos_model;
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

            $.each(that.tooltips, function(i, tooltip) {
                $.wa.new.Tooltip(tooltip);
            });
        };

        Section.prototype.initVue = function() {
            var that = this;

            if (typeof $.vue_app === "object" && typeof $.vue_app.unmount === "function") {
                $.vue_app.unmount();
            }

            // VARS
            var storefronts_extended = that.storage("storefronts_extended");

            // DOM
            var $view_section = that.$wrapper.find(".js-product-prices-section");

            // COMPONENTS
            var component_price_table = {
                props: { skus: null, promo: { type: Object, default: null } },
                data: function() {
                    return {
                        product: that.product
                    };
                },
                components: {
                    "component-stocks-manager": {
                        props: ["sku_mod"],
                        data: function() {
                            var self = this;
                            return {
                                stocks_array: that.stocks_array,
                                sku_mod_stocks: $.wa.clone(self.sku_mod.stock),
                                can_edit: that.product.can_edit,
                                values: {
                                    sku_mod_count: $.wa.clone(self.sku_mod.count)
                                },
                                states: {
                                    is_locked: false,
                                    is_changed: false
                                }
                            };
                        },
                        template: that.components["component-stocks-manager"],
                        delimiters: ['{ { ', ' } }'],
                        computed: {
                            stock_icon_class: function() {
                                return function(stock_value, stock) {
                                    stock_value = (stock_value ? parseInt(stock_value) : null);

                                    var result = "";
                                    if (stock_value > parseInt(stock.low_count) || stock_value === null) {
                                        result = "text-green";
                                    } else if (stock_value > parseInt(stock.critical_count) && stock_value <= parseInt(stock.low_count)) {
                                        result = "text-orange";
                                    } else if (stock_value <= parseInt(stock.critical_count)) {
                                        result = "text-red";
                                    }

                                    return result;
                                }
                            }
                        },
                        watch: {
                            "sku_mod.stocks_data.expanded": function(expanded, old_value) {
                                var self = this;

                                if (expanded) {
                                    // таймаут чтобы не срабатывал ивент-клика открытия
                                    setTimeout( function() {
                                        $(document).on("click", self.closeHandler);
                                    }, 100);
                                } else {
                                    $(document).off("click", self.closeHandler);
                                    // Эта штука полностью перерисует компонент ( и очистит данные при закрытии окна
                                    self.sku_mod.stocks_data.render_key += 1;
                                }
                            }
                        },
                        methods: {
                            changeSkuModStocks: function(sku_mod) {
                                var self = this,
                                    sku_mod_stocks = self.sku_mod_stocks;

                                var stocks_count = 0,
                                    is_infinite = false,
                                    is_set = false;

                                var virtual_stocks = [];
                                $.each(sku_mod_stocks, function(stock_id, stock_value) {
                                    var value = parseFloat(stock_value);
                                    if (!isNaN(value)) {
                                        is_set = true;
                                        stocks_count += value;
                                    } else {
                                        is_infinite = true;
                                        value = "";
                                    }
                                    // sku_mod_stocks[stock_id] = stock_value;

                                    var stock = that.stocks[stock_id];
                                    if (stock.is_virtual) {
                                        virtual_stocks.push(stock);
                                    }
                                });

                                $.each(virtual_stocks, function(i, stock) {
                                    var value = "";

                                    if (stock.substocks) {
                                        $.each(stock.substocks, function(j, sub_stock_id) {
                                            var sub_stock_value = sku_mod_stocks[sub_stock_id];
                                            sub_stock_value = parseFloat(sub_stock_value);
                                            if (!isNaN(sub_stock_value)) {
                                                if (!value) { value = 0; }
                                                value += sub_stock_value;
                                            } else {
                                                value = "";
                                                return false;
                                            }
                                        });
                                    }

                                    sku_mod_stocks[stock.id] = (typeof value === "number" ? value.toFixed(3) * 1 : value);
                                });

                            },
                            focusSkuModStocks: function(sku_mod) {
                                var self = this;

                                if (self.stocks_array.length || self.can_edit) {
                                    self.toggleStocks(true);
                                }
                            },
                            toggleStocks: function(expanded) {
                                var self = this,
                                    sku_mod = self.sku_mod;

                                if (self.states.is_locked) { return false; }

                                expanded = (typeof expanded === "boolean" ? expanded : !sku_mod.stocks_data.expanded);

                                var is_changed = (sku_mod.stocks_data.expanded !== expanded);
                                if (is_changed) {
                                    sku_mod.stocks_data.expanded = expanded;
                                }
                            },
                            getStockTitle: function(stock) {
                                var result = "";

                                if (stock.is_virtual) {
                                    var stocks_array = [];

                                    if (stock.substocks) {
                                        $.each(stock.substocks, function(i, sub_stock_id) {
                                            var sub_stock = that.stocks[sub_stock_id];
                                            stocks_array.push(sub_stock.name);
                                        });
                                    }

                                    result = that.locales["stock_title"].replace("%s", stocks_array.join(", "));
                                }

                                return result;
                            },
                            saveStocksChanges: function() {
                                var self = this;

                                self.states.is_locked = true;

                                request()
                                    .always( function() {
                                        self.states.is_locked = false;
                                    })
                                    .done( function(response) {
                                        if (response.status !== "ok") {
                                            alert("We have a problem sir");
                                        }

                                        if (self.stocks_array.length) {
                                            $.each(self.sku_mod.stock, function(stock_id, stock_value) {
                                                self.sku_mod.stock[stock_id] = self.sku_mod_stocks[stock_id];
                                            });
                                            that.updateModificationStocks(self.sku_mod);
                                        } else {
                                            var server_data = response.data[self.sku_mod.id];
                                            self.sku_mod.count = server_data.count;
                                        }

                                        self.toggleStocks(false);
                                    });

                                function request() {
                                    var data = {
                                        "product_id": that.product.id,
                                        "sku_id": self.sku_mod.id,
                                        "stocks": (self.stocks_array.length ? self.sku_mod_stocks : null),
                                        "count": (self.stocks_array.length ? null : self.values.sku_mod_count)
                                    }

                                    return $.post(that.urls["save_stocks"], data, "json");
                                }
                            },

                            // OTHER
                            validate: function(event, type, data, key) {
                                var self = this,
                                    $field = $(event.target),
                                    target_value = $field.val(),
                                    value = (typeof target_value === "string" ? target_value : "" + target_value);

                                value = $.wa.validate(type, value);

                                switch (type) {
                                    case "price":
                                        value = $.wa.validate("number", value);

                                        var limit_body = 11,
                                            limit_tail = 4,
                                            parts = value.replace(",", ".").split(".");

                                        var error_key = "product[skus][" + data.id + "]["+ key + "]";

                                        if (parts[0].length > limit_body || (parts[1] && parts[1].length > limit_tail)) {
                                            self.errors[error_key] = {
                                                id: "price_error",
                                                text: "price_error"
                                            };
                                        } else {
                                            if (self.errors[error_key]) {
                                                delete self.errors[error_key];
                                            }
                                        }

                                        break;
                                    case "number":
                                        value = $.wa.validate("number", value);
                                        break;
                                    case "integer":
                                        value = $.wa.validate("integer", value);
                                        break;
                                    default:
                                        break;
                                }

                                data[key] = value;
                            },

                            // Close Magic
                            closeHandler: function(event) {
                                var self = this,
                                    is_target = self.$el.contains(event.target);

                                if (!is_target) {
                                    self.toggleStocks(false);
                                }
                            }
                        },
                        mounted: function() {
                            var self = this;

                            $(self.$el).on("input change", function() {
                                self.states.is_changed = true;
                            });
                        }
                    }
                },
                template: that.components["component-price-table"],
                delimiters: ['{ { ', ' } }'],
                computed: {
                    promo_icon_class: function() {
                        return function(sku_mod) {
                            var result = "";

                            if (sku_mod.promos.active) {
                                result = "color-green-dark";
                            } else if (sku_mod.promos.stopped) {
                                result = "color-yellow";
                            } else if (sku_mod.promos.scheduled) {
                                result = "color-gray-light";
                            }

                            return result;
                        };
                    },
                    promo_tooltip: function() {
                        return function(sku_mod) {
                            var result = "";

                            if (sku_mod.promos.active) {
                                result = "prices-tooltip-promo-" + sku_mod.promos.active[0];
                            } else if (sku_mod.promos.stopped) {
                                result = "prices-tooltip-5";
                            } else if (sku_mod.promos.scheduled) {
                                result = "prices-tooltip-6";
                            }

                            return result;
                        };
                    },
                    skus_is_displayed: function() {
                        var self = this,
                            result = false;

                        $.each(self.skus, function(i, sku) {
                            $.each(sku.modifications, function(i, sku_mod) {
                                if (sku_mod.is_visible) {
                                    result = true;
                                    return false;
                                }
                            });

                            if (result) { return false; }
                        });

                        return result;
                    },
                    sku_mod_price: function() {
                        var self = this;
                        return function(sku_mod, type) {
                            var result = sku_mod[type];

                            if (self.promo) {
                                var tooltip_id = "10";
                                if (sku_mod[type + "_promo"]) {
                                    tooltip_id = "11";
                                    result = sku_mod[type + "_promo"];
                                }
                                result = '<span data-tooltip-id="prices-tooltip-'+tooltip_id+'">'+result+"</span>";
                            }

                            return result;
                        }
                    },
                    sku_mod_price_class: function() {
                        return function(sku_mod, type) {
                            var class_name = "";
                            if (sku_mod.promo_status) {
                                var is_overwritten = (sku_mod[type + "_promo"]);
                                if (is_overwritten) {
                                    switch (sku_mod.promo_status) {
                                        case "active":
                                            class_name = "color-green-dark";
                                            break;
                                        case "stopped":
                                            class_name = "color-yellow";
                                            break;
                                        case "scheduled":
                                            class_name = "color-gray-light";
                                            break;
                                    }
                                }
                            }

                            return class_name;
                        }
                    },
                    show_percentage: function() {
                        var result = false;
                        return function(sku_mods) {
                            $.each(sku_mods, function(i, sku_mod) {
                                if (sku_mod.promos_values) {
                                    result = true;
                                    return false;
                                }
                            });
                            return result;
                        }
                    },
                    sku_mod_visible: function() {
                        return function(sku_mod) {
                            if (that.product.can_edit) {
                                return sku_mod.is_visible;
                            } else {
                                return (sku_mod.is_visible && (sku_mod.status || sku_mod.available));
                            }
                        };
                    }
                },
                methods: {
                    scrollToPromo: function(sku_mod) {
                        var promo_id = null;
                        if (sku_mod.promos.active) {
                            promo_id = sku_mod.promos.active[0];
                        } else if (sku_mod.promos.stopped) {
                            promo_id = sku_mod.promos.stopped[0];
                        } else if (sku_mod.promos.scheduled) {
                            promo_id = sku_mod.promos.scheduled[0];
                        }

                        if (promo_id) {
                            scrollToSkuMod(promo_id);
                            focusSkuMod(promo_id);
                        }

                        function scrollToSkuMod(promo_id) {
                            var $promo = $("#js-promo-" + promo_id);
                            if ($promo.length) {
                                var $sku = $promo.find(".s-modification-wrapper[data-id=\"" + sku_mod.id + "\"]");
                                if ($sku.length) {
                                    var $window = $(window),
                                        top = $sku.offset().top - ($window.height() * 0.2);

                                    $window.scrollTop(top);
                                }
                            }
                        }

                        function focusSkuMod(promo_id) {
                            var promo_search = that.promos_model.promos.filter( function(promo) {
                                return (promo.id === promo_id);
                            });

                            if (promo_search.length) {
                                var promo = promo_search[0],
                                    target_sku_mod = null;

                                $.each(promo.skus, function(i, _sku) {
                                    $.each(_sku.modifications, function(i, _sku_mod) {
                                        if (_sku_mod.id === sku_mod.id) {
                                            target_sku_mod = _sku_mod;
                                            return false;
                                        }
                                    });

                                    if (target_sku_mod) { return false; }
                                });

                                if (target_sku_mod) {
                                    target_sku_mod.is_highlighted = true;
                                    setTimeout( function() {
                                        target_sku_mod.is_highlighted = false;
                                    }, 2000);
                                }
                            }
                        }
                    }
                }
            };

            var component_dropdown = {
                // props: ["items", "active_item_id", "button_class", "body_width", "body_class"],
                props: {
                    items: { type: Array, default: [] },
                    active_item_id: { type: String },
                    button_class: { type: String, default: "" },
                    body_width: { type: String, default: "" },
                    body_class: { type: String, default: "" }
                },
                data: function() {
                    var self = this;

                    var active_item = self.items[0];
                    if (self.active_item_id) {
                        var filter_item_search = self.items.filter( function(item) {
                            return (item.id === self.active_item_id);
                        });
                        active_item = (filter_item_search.length ? filter_item_search[0] : active_item);
                    }

                    return {
                        active_item: active_item
                    }
                },
                template: that.components["component-dropdown"],
                delimiters: ['{ { ', ' } }'],
                mounted: function() {
                    var self = this;

                    $(self.$el).waDropdown({
                        hover : false,
                        items : ".dropdown-item",
                        change: function (event, target, dropdown) {
                            var $target        = $(target),
                                active_item_id = $target.attr("data-id");

                            if (self.active_item.id !== active_item_id) {
                                self.$emit("change", active_item_id);

                                var active_item_search = self.items.filter(function (item) {
                                    return (item.id === active_item_id);
                                });

                                if (active_item_search.length) {
                                    self.active_item = active_item_search[0];
                                    self.$emit("change_item", active_item_search[0]);
                                }
                            }
                        }
                    });
                }
            };

            // MAIN VUE
            var vue_model = Vue.createApp({
                data() {
                    return {
                        errors              : that.errors,
                        errors_global       : that.errors_global,
                        product             : that.product,
                        filters             : that.filters,
                        prices_model        : that.prices_model,
                        promos_model        : that.promos_model,
                        storefronts_extended: (typeof storefronts_extended === "boolean" ? storefronts_extended : false)
                    }
                },
                components: {
                    "component-dropdown": component_dropdown,
                    "component-prices-section": {
                        props: ["prices_model"],
                        data: function() {
                            return {
                                filters: that.filters
                            }
                        },
                        template: that.components["component-prices-section"],
                        delimiters: ['{ { ', ' } }'],
                        components: {
                            "component-dropdown": component_dropdown,
                            "component-price-table": component_price_table
                        },
                        computed: {},
                        methods: {
                            onChangeFilter: function(filter_name, filter_value) {
                                var self = this;
                                // Проставляет значение фильтра
                                self.prices_model.filters[filter_name] = filter_value;
                                // Фильтруем
                                filterSkus(self.prices_model.skus, self.prices_model.filters);
                            },
                            pricesToggle: function() {
                                var self = this;
                                self.prices_model.is_extended = !self.prices_model.is_extended;
                                that.storage("prices_model", self.prices_model.is_extended);
                            }
                        }
                    },
                    "component-promo-section": {
                        props: ["promo"],
                        data: function() {
                            return {
                                filters: that.filters
                            }
                        },
                        template: that.components["component-promo-section"],
                        delimiters: ['{ { ', ' } }'],
                        components: {
                            "component-dropdown": component_dropdown,
                            "component-price-table": component_price_table
                        },
                        computed: {
                            promo_storefronts_toggle: function() {
                                var self = this;

                                return function(promo) {
                                    var result = "";

                                    var count = (promo.storefronts.length);
                                    if (count > 0) {
                                        if (promo.storefronts_extended) {
                                            result = $.wa.locale_plural(count, that.locales["storefronts_toggle_hide_forms"]);
                                        } else {
                                            result = $.wa.locale_plural(count, that.locales["storefronts_toggle_open_forms"]);
                                        }
                                    }

                                    return result;
                                };
                            }
                        },
                        methods: {
                            onChangeFilter: function(filter_name, filter_value) {
                                var self = this;
                                // Проставляет значение фильтра
                                self.promo.filters[filter_name] = filter_value;
                                // Фильтруем
                                filterSkus(self.promo.skus, self.promo.filters);
                            },
                            storefrontsToggle: function() {
                                var self = this;
                                self.promo.storefronts_extended = !self.promo.storefronts_extended;
                                that.storage("promo["+self.promo.id+"]storefronts_extended", self.promo.storefronts_extended);
                            }
                        }
                    }
                },
                methods: {
                    // ERRORS
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
                                    self.errors_global.splice(0, self.errors.length);
                                }
                            });

                            // Рендер ошибок
                        } else if (errors && errors.length) {
                            $.each(errors, function(i, error_id) {
                                self.removeError(error_id);
                            });
                        }
                    },
                    removeError: function(error_id, error) {
                        var self = this;

                        if (typeof error === "object") {
                            var error_index = self.errors_global.indexOf(error);
                            if (error_index >= 0) {
                                self.errors_global.splice(error_index, 1);
                            }
                        } else if (typeof error_id === "string") {
                            if (self.errors[error_id]) {
                                delete self.errors[error_id];
                            }
                        }
                    },
                    // METHODS
                    onChangeFilter: function(filter_name, filter_value) {
                        var self = this;

                        // Проставляет значение фильтра
                        self.promos_model.filters[filter_name] = filter_value;

                        // Фильтруем
                        switch (filter_name) {
                            case "promos_activity":
                                $.each(self.promos_model.promos, function(i, promo) {
                                    var is_visible = true;
                                    if (filter_value && filter_value !== "all") {
                                        is_visible = (promo.status === filter_value);
                                    }
                                    promo.is_visible = is_visible;
                                });
                                break;
                        }
                    },
                    promosToggle: function() {
                        var self = this;
                        self.promos_model.is_extended = !self.promos_model.is_extended;
                        that.storage("promos_model", self.promos_model.is_extended);
                    },
                    storefrontsToggle: function() {
                        var self = this;
                        self.storefronts_extended = !self.storefronts_extended;
                        that.storage("storefronts_extended", self.storefronts_extended);
                        if (!self.storefronts_extended) { $(window).scrollTop(0); }
                    }
                },
                delimiters: ['{ { ', ' } }'],
                created: function () {
                    $view_section.css("visibility", "");
                },
                mounted: function() {
                    var self = this;

                    that.$wrapper.trigger("section_mounted", ["prices", that]);
                }
            }).mount($view_section[0]);

            return vue_model;

            function filterSkus(skus, filters) {
                var result = [];

                $.each(skus, function(i, sku) {
                    var sku_is_visible = false;

                    $.each(sku.modifications, function(i, sku_mod) {
                        var is_visible = true;

                        $.each(filters, function(filter_name, filter_value) {
                            var filter_is_visible = checkFilter(sku_mod, filter_name, filter_value);
                            if (!filter_is_visible) {
                                is_visible = false;
                                return false;
                            }
                        });

                        if (is_visible) { sku_is_visible = true; }
                        sku_mod.is_visible = is_visible;
                    });

                    sku.is_visible = sku_is_visible;
                });

                function checkFilter(sku_mod, filter_name, filter_value) {
                    var is_visible = true;

                    switch (filter_name) {
                        case "promos":
                            switch (filter_value) {
                                case "promos":
                                    is_visible = (Object.keys(sku_mod.promos).length);
                                    break;
                                case "promos_active":
                                    is_visible = (Object.keys(sku_mod.promos).length && sku_mod.promos.active);
                                    break;
                            }
                            break;

                        case "available":
                            switch (filter_value) {
                                case "available":
                                    is_visible = !!sku_mod.available;
                                    break;
                                case "unavailable":
                                    is_visible = !sku_mod.available;
                                    break;
                            }
                            break;

                        case "visibility":
                            switch (filter_value) {
                                case "visible":
                                    is_visible = !!sku_mod.status;
                                    break;
                                case "hidden":
                                    is_visible = !sku_mod.status;
                                    break;
                            }
                            break;
                    }

                    return is_visible;
                }
            }
        };

        //

        Section.prototype.updateModificationStocks = function(target_sku_mod) {
            var that = this;

            if (target_sku_mod) {
                update(target_sku_mod);
            } else {
                $.each(that.product.skus, function(i, sku) {
                    $.each(sku.modifications, function(i, sku_mod) {
                        update(sku_mod);
                    });
                });
            }

            if (that.promos_model) {
                $.each(that.promos_model.promos, function(i, promo) {
                    $.each(promo.skus, function(i, sku) {
                        $.each(sku.modifications, function(i, sku_mod) {
                            if (!target_sku_mod || target_sku_mod.id === sku_mod.id) {
                                update(sku_mod);
                            }
                        });
                    });
                });
            }

            function update(sku_mod) {
                var is_good = false;
                var is_warn = false;
                var is_critical = false;

                var stocks_count = 0,
                    is_set = false;

                $.each(sku_mod.stock, function(stock_id, stock_value) {
                    var value = parseFloat(stock_value);

                    var stock_data = that.stocks[stock_id];
                    if (stock_data && stock_data.is_virtual) { return true; }

                    if (!isNaN(value)) {
                        is_set = true;
                        stocks_count += value;

                        var stock = that.stocks[stock_id];
                        if (value > stock.critical_count) {
                            if (value > stock.low_count) {
                                is_good = true;
                            } else {
                                is_warn = true;
                            }
                        } else {
                            is_critical = true;
                        }
                    } else {
                        is_set = false;
                        return false;
                    }
                });

                if (is_good || !is_set) {
                    sku_mod.stocks_data.indicator = 1;
                } else if (is_warn) {
                    sku_mod.stocks_data.indicator = 0;
                } else if (is_critical) {
                    sku_mod.stocks_data.indicator = -1;
                }

                if (is_set) {
                    sku_mod.count = $.wa.validate("float", stocks_count);
                }
            }
        };

        Section.prototype.storage = function(key, value) {
            var that = this,
                storage_name = "shop/product["+that.product.id+"]/prices";

            var storage = getStorage();

            if (typeof value === "undefined") {
                if (typeof key === "undefined") {
                    return storage;
                } else {
                    return (typeof storage[key] !== "undefined" ? storage[key] : null);
                }
            } else {
                if (typeof key !== "undefined") {
                    storage[key] = value;
                    setStorage(storage);
                }
            }

            function getStorage() {
                var result = {};

                var storage = sessionStorage.getItem(storage_name);
                if (storage) { result = JSON.parse(storage); }

                return result;
            }

            function setStorage(storage) {
                var string = JSON.stringify(storage);

                sessionStorage.setItem(storage_name, string);
            }
        };

        //

        return Section;

    })($);

    $.wa_shop_products.init.initProductPricesSection = function(options) {
        return new Section(options);
    };

})(jQuery);
