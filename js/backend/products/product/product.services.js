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
            that.product = options["product"];
            that.product_type = options["product_type"];
            that.services = formatServices(options["services"]);
            that.errors = {};
            that.errors_global = [];

            // DYNAMIC VARS

            // INIT
            that.vue_model = that.initVue();
            that.init();

            function formatServices(services) {
                var result = [];

                $.each(services, function(i, service) {
                    result.push(that.formatService(service));
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

            that.initSave();

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
            var $view_section = that.$wrapper.find(".js-product-services-section");

            var vue_model = Vue.createApp({
                data() {
                    return {
                        errors       : that.errors,
                        errors_global: that.errors_global,
                        product      : that.product,
                        product_type : that.product_type,
                        services     : that.services
                    }
                },
                computed: {
                    placeholder_modification: function() {
                        var self = this;
                        return function(service, variant, mod) {
                            var result = mod.base_price;

                            if (variant.price) {
                                result = variant.price;
                            }
                            else if (variant.base_price) {
                                result = variant.base_price;
                            }

                            return result;
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

                        if (error.id && error.id.indexOf("][price]") >= 0) {
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
                    removeError: function(error_id, error) {
                        var self = this;

                        if (self.errors[error_id]) {
                            delete self.errors[error_id];
                        } else {
                            var error_index = self.errors_global.indexOf(error);
                            if (error_index >= 0) {
                                self.errors_global.splice(error_index, 1);
                            }
                        }
                    },
                    validate: function(event, type, data, key, error_key) {
                        var self = this,
                            $field = $(event.target),
                            target_value = $field.val(),
                            value = (typeof target_value === "string" ? target_value : "" + target_value);

                        switch (type) {
                            case "price":
                                value = $.wa.validate("number", value);

                                var limit_body = 11,
                                    limit_tail = 4,
                                    parts = value.replace(",", ".").split(".");

                                if (parts[0].length > limit_body || (parts[1] && parts[1].length > limit_tail)) {
                                    self.renderError({
                                        id: error_key,
                                        text: "price_error"
                                    });
                                } else {
                                    self.removeError(error_key);
                                }

                                break;
                            default:
                                value = $.wa.validate(type, value);
                                break;
                        }

                        // set
                        data[key] = value;
                    },

                    changeServicesList: function() {
                        that.initManagerDialog(this);
                    },

                    // toggleType: function(service) {
                    //     var self = this;
                    // if (service.type_id) {
                    //     service.type_id = null;
                    // } else {
                    //     service.type_id = self.product_type.id;
                    // }
                    // },
                    changeServiceStatus: function(service) {
                        // if (!service.type_id && !service.status_model) {
                        //     service.product_id = null;
                        // }

                        if (service.status_model && service.variants.length) {
                            var active_variants = service.variants.filter( function(_variant) {
                                return _variant.status_model;
                            });
                            if (!active_variants.length) {
                                service.variants[0].status_model = true;
                                service.variant_id = service.variants[0].id;
                            }
                        }
                    },
                    changeVariantStatus: function(service, variant) {
                        var self = this;

                        var active_variants = service.variants.filter( function(_variant) {
                            return _variant.status_model;
                        });
                        service.status_model = !!active_variants.length;

                        if (!variant.status_model && service.variant_id === variant.id && active_variants.length) {
                            service.variant_id = active_variants[0].id;
                        }

                        self.changeServiceStatus(service);
                    },
                    changeModificationStatus: function(service, variant, mod) {
                        var self = this;

                        var active_mods = variant.skus.filter( function(_mod) {
                            return _mod.status_model;
                        });
                        variant.status_model = !!active_mods.length;

                        self.changeVariantStatus(service, variant);
                    },
                    toggleProduct: function(service) {
                        var self = this;

                        if (service.product_id) {
                            service.product_id = null;
                            service.variant_id_changed = service.variant_id;
                            service.variant_id = service.variant_id_default;
                            if (service.variants.length) {
                                $.each(service.variants, function(i, variant) {
                                    variant.status_model = variant.status_model_default;
                                });
                            }

                        } else {
                            service.product_id = self.product.id;
                            service.variant_id = service.variant_id_changed;
                            if (service.variants.length) {
                                $.each(service.variants, function(i, variant) {
                                    variant.status_model = variant.status_model_changed;
                                });
                            }
                        }

                        that.$wrapper.trigger("change");
                    },
                    toggleService: function(service) {
                        service.is_expanded = !service.is_expanded;

                        if (service.variants.length) {
                            var variant = service.variants[0];
                            variant.is_expanded = service.is_expanded;
                        }
                    },
                    toggleVariant: function(service, variant) {
                        variant.is_expanded = !variant.is_expanded;
                    },

                    isOverwritten: function(service, variant) {
                        var result = false;

                        if (variant) {
                            return isSkuSet(variant);
                        } else {
                            if (service.variants[0]) {
                                result = isVariantSet(service.variants[0]);
                            } else {
                                return result;
                            }
                        }

                        return result;

                        function isVariantSet(variant) {
                            var result = isSkuSet(variant);

                            if (service.variants.length > 1 && !result) {
                                if (variant.price > 0) { result = true; }
                            }

                            return result;
                        }

                        function isSkuSet(variant) {
                            var result = false;

                            if (variant.skus) {
                                $.each(variant.skus, function(i, sku) {
                                    if (sku.price > 0) {
                                        result = true;
                                    }
                                });
                            }

                            return result;
                        }
                    }
                },
                delimiters: ['{ { ', ' } }'],
                created: function () {
                    $view_section.css("visibility", "");
                },
                mounted: function() {
                    var self = this;

                    that.$wrapper.trigger("section_mounted", ["services", that]);
                }
            }).mount($view_section[0]);

            return vue_model;
        };

        // Сохранение
        Section.prototype.validate = function() {
            var that = this;

            var errors = [];

            $.each(that.errors, function(i, error) {
                errors.push(error);
            });

            $.each(that.errors_global, function(i, error) {
                errors.push(error);
            });

            return errors;
        };

        Section.prototype.initSave = function() {
            var that = this;

            that.$wrapper.on("click", ".js-product-save", function (event, options) {
                options = (options || {});

                event.preventDefault();

                // Останавливаем сохранение если во фронте есть ошибки
                that.errors_global.splice(0, that.errors_global.length);
                var errors = that.validate();
                if (errors.length) {
                    var $error = $(".wa-error-text:first");
                    if ($error.length) {
                        $(window).scrollTop($error.offset().top - 100);
                    }
                    return false;
                }

                var loading = "<span class=\"icon top\" style=\"margin-left: .5rem\"><i class=\"fas fa-spinner fa-spin\"></i></span>";

                var $button = $(this),
                    $loading = $(loading).appendTo($button.attr("disabled", true));

                // Очищаем ошибки
                $.each(that.errors, function(key, error) {
                    // Точечные ошибки
                    if (key !== "global") { delete that.errors[key] }
                    // Общие ошибки
                    else if (error.length) {
                        error.splice(0, error.length);
                    }
                });

                sendRequest()
                    .done( function(server_data) {
                        var product_id = server_data.product_id;
                        if (product_id) {
                            var is_new = location.href.indexOf("/new/services/") >= 0;
                            if (is_new) {
                                var url = location.href.replace("/new/services/", "/"+product_id+"/services/");
                                history.replaceState(null, null, url);
                                that.$wrapper.trigger("product_created", [product_id]);
                            }
                        }

                        if (options.redirect_url) {
                            $.wa_shop_products.router.load(options.redirect_url).fail( function() {
                                location.href = options.redirect_url;
                            });
                        } else {
                            $.wa_shop_products.router.reload();
                        }
                    })
                    .fail( function(errors) {
                        $button.attr("disabled", false);
                        $loading.remove();

                        if (errors) {
                            that.vue_model.renderErrors(errors);
                            $(window).scrollTop(0);
                        }
                    });
            });

            function sendRequest() {
                var data = getData();

                return request(data);

                function request(data) {
                    var deferred = $.Deferred();

                    $.post(that.urls["save"], data, "json")
                        .done( function(response) {
                            if (response.status === "ok") {
                                deferred.resolve(response.data);
                            } else {
                                deferred.reject(response.errors);
                            }
                        })
                        .fail( function() {
                            deferred.reject();
                        });

                    return deferred.promise();
                }
            }

            function getData() {
                var result = [
                    {
                        "name": "product_id",
                        "value": that.product.id
                    }
                ];

                $.each(that.services, function(service_i, service) {
                    var s_tail = "services["+service.id+"]";

                    var is_bad_case = (!service.type_id && !service.product_id && service.status_model);
                    var service_price = (is_bad_case ? that.product.id : service.product_id);
                    var service_status = (service.status_model ? "1" : "0");

                    result = result.concat([
                        {
                            "name": s_tail + "[id]",
                            "value": service.id
                        },
                        {
                            "name": s_tail + "[product_id]",
                            "value": service_price
                        },
                        {
                            "name": s_tail + "[status]",
                            "value": service_status
                        },
                        {
                            "name": s_tail + "[type_id]",
                            "value": service.type_id
                        },
                        {
                            "name": s_tail + "[variant_id]",
                            "value": (is_bad_case ? service.variant_id_default : service.variant_id)
                        }
                    ]);

                    $.each(service.variants, function(variant_i, variant) {
                        var v_tail = s_tail + "[variants]["+variant.id+"]";

                        var variant_price = (is_bad_case ? "" : variant.price);
                        var variant_status = (service.status_model && variant.status_model ? "1" : "0");
                        if (is_bad_case) { variant_status = "1"; }

                        result = result.concat([
                            {
                                "name": v_tail + "[id]",
                                "value": variant.id
                            },
                            {
                                "name": v_tail + "[price]",
                                "value": variant_price
                            },
                            {
                                "name": v_tail + "[status]",
                                "value": variant_status
                            }
                        ]);

                        if (variant.skus) {
                            $.each(variant.skus, function(modification_i, modification) {
                                var m_tail = v_tail + "[skus]["+modification.sku_id+"]";

                                var mod_price = (is_bad_case ? "" : modification.price);
                                var mod_status = (service.status_model && variant.status_model && modification.status_model ? "1" : "0");
                                if (is_bad_case) { mod_status = "1"; }

                                result = result.concat([
                                    {
                                        "name": m_tail + "[sku_id]",
                                        "value": modification.sku_id
                                    },
                                    {
                                        "name": m_tail + "[price]",
                                        "value": mod_price
                                    },
                                    {
                                        "name": m_tail + "[status]",
                                        "value": mod_status
                                    }
                                ]);
                            });
                        }
                    });
                });

                return result;
            }
        };

        // Полезные функции

        Section.prototype.formatService = function(service) {
            var that = this;

            // Если данные не отличаются от услуги, закрываем замок
            // Однако при сохранении product_id отпавится для совместимости с сервером
            if (!service.is_changed) {
                if (!service.type_id && !!service.product_id) {
                    service.product_id = null;
                }
            }

            service.status_model = (service.status !== "0");
            service.is_expanded = false;
            service.is_enabled = !!(service.type_id || service.product_id || service.status_model);
            service.variant_id_changed = service.variant_id;

            if (service.variants) {
                $.each(service.variants, function(i, variant) {
                    variant.price = formatPrice(variant.price);
                    variant.base_price = formatPrice(variant.base_price);
                    variant.status_model = (variant.status !== "0");
                    variant.status_model_default = true;
                    variant.status_model_changed = variant.status_model;
                    variant.is_sku_set = false;
                    variant.is_expanded = false;

                    if (variant.price > 0) {
                        service.is_expanded = true;
                    }

                    if (!variant.name) {
                        variant.name = service.name;
                    }

                    if (variant.skus) {
                        $.each(variant.skus, function(i, sku) {
                            sku.status_model = (sku.status !== "0");
                            sku.price = formatPrice(sku.price);
                            sku.base_price = formatPrice(sku.base_price);

                            if (sku.price > 0) {
                                service.is_expanded = true;
                                variant.is_expanded = true;
                            }
                        });
                    }
                });
            }

            return service;

            function formatPrice(price) {
                var result = "";

                price = $.wa.validate("number", price);

                if (price.length) {
                    result += (price * 1);
                }

                return result;
            }
        };

        //

        Section.prototype.initManagerDialog = function(vue_model) {
            var that = this;

            $.waDialog({
                html: that.templates["dialog_services_manager"],
                options: {
                    services: that.services,
                    onSuccess: function(dialog_services) {
                        var services_object = $.wa.construct(vue_model.services, "id");

                        $.each(dialog_services, function(i, dialog_service) {
                            if (services_object[dialog_service.id]) {
                                var service = services_object[dialog_service.id];
                                if (service.is_enabled !== dialog_service.is_enabled) {
                                    enableService(service, dialog_service.is_enabled);
                                }
                            }
                        });

                        function enableService(service, enable) {
                            service.is_enabled = enable;
                            service.status_model = enable;
                            service.product_id = null;
                            if (service.variants) {
                                $.each(service.variants, function(i, variant) {
                                    variant.status_model = enable;
                                    if (variant.skus) {
                                        $.each(variant.skus, function(i, sku) {
                                            sku.status_model = enable;
                                        });
                                    }
                                });
                            }
                        }

                    }
                },
                onOpen: intiDialog
            });

            function intiDialog($dialog, dialog) {
                var $section = $dialog.find(".js-vue-wrapper");
                var services = getServices();

                var filter_timer = 0;

                Vue.createApp({
                    data() {
                        return {
                            services: services,
                            states: {
                                changed: false
                            }
                        }
                    },
                    delimiters: ['{ { ', ' } }'],
                    components: {
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
                    computed: {
                        emptySearchResult() {
                            if (!this.services.length) {
                                return false;
                            }

                            return this.services.every(s => !s.visible);
                        }
                    },
                    methods: {
                        filterServices: function(value) {
                            var self = this;

                            clearTimeout(filter_timer);
                            filter_timer = setTimeout( update, 200);

                            function update() {
                                value = value.toLowerCase();

                                $.each(self.services, function(i , service) {
                                    if (value.length) {
                                        service.visible = (service.name.toLowerCase().indexOf(value) >= 0);
                                    } else {
                                        service.visible = true;
                                    }
                                });

                                self.$nextTick( function() {
                                    dialog.resize();
                                });
                            }
                        },
                        switchService: function(service) {
                            var self = this;
                            self.states.changed = true;
                        },
                        onSuccess: function() {
                            var self = this;

                            if (self.states.changed) { that.$wrapper.trigger("change"); }

                            dialog.options.onSuccess(self.services);
                            dialog.close();
                        }
                    },
                    created: function () {
                        $section.css("visibility", "");
                    },
                    mounted: function () {
                        dialog.resize();
                    }
                }).mount($section[0]);

                function getServices() {
                    var services = $.wa.clone(dialog.options.services);

                    $.each(services, function(i, service) {
                        service.is_disabled = !!service.type_id;
                        service.visible = true;
                    });

                    return services;
                }
            }
        };

        return Section;

    })($);

    $.wa_shop_products.init.initProductServicesSection = function(options) {
        return new Section(options);
    };

})(jQuery);
