( function($) {

    var Section = ( function($) {

        Section = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];

            // CONST
            that.components = options["components"];
            that.tooltips = options["tooltips"];
            that.urls = options["urls"];

            // VUE JS MODELS
            that.product = options["product"];
            that.cross_selling = formatData(options["cross_selling"]);
            that.upselling = formatData(options["upselling"]);

            that.errors = {};
            that.errors_global = [];

            // DYNAMIC VARS

            // INIT
            that.vue_model = that.initVue();
            that.init();

            console.log( that );

            function formatData(data) {
                if (data.products && data.products.length) {
                    $.each(data.products, function(i, product) {
                        formatProduct(product);
                    });
                }

                return data;
            }
        };

        Section.prototype.init = function() {
            var that = this;

            var page_promise = that.$wrapper.closest(".s-product-page").data("ready");
            page_promise.done( function(product_page) {
                var $footer = that.$wrapper.find(".js-sticky-footer");
                product_page.initProductDelete($footer);
                product_page.initStickyFooter($footer);
            });

            that.initSave();

            if (that.tooltips && that.tooltips.length) {
                $.each(that.tooltips, function(i, tooltip) {
                    $.wa.new.Tooltip(tooltip);
                });
            }
        };

        Section.prototype.initVue = function() {
            var that = this;

            // DOM
            var $view_section = that.$wrapper.find(".js-product-related-section");

            var vue_model = new Vue({
                el: $view_section[0],
                data: {
                    errors       : that.errors,
                    errors_global: that.errors_global,

                    product      : that.product,
                    upselling    : that.upselling,
                    cross_selling: that.cross_selling
                },
                components: {
                    "component-products-list": {
                        props: ["products"],
                        template: that.components["component-products-list"],
                        delimiters: ['{ { ', ' } }'],
                        components: {
                            "component-add-product": {
                                props: ["placeholder", "products"],
                                data: function () {
                                    return {
                                        is_loading: false
                                    };
                                },
                                template: '<div class="vue-component-add-product" style="position: relative;"><input class="wa-small js-autocomplete" type="text" v-bind:placeholder="placeholder"><span class="s-loading-icon" v-if="is_loading"><i class="fas fa-spinner fa-spin"></i></span></div>',
                                delimiters: ['{ { ', ' } }'],
                                mounted: function() {
                                    var self = this;

                                    var $wrapper = $(self.$el),
                                        $field = $wrapper.find(".js-autocomplete");

                                    initAutocomplete($field);

                                    function initAutocomplete($field) {
                                        if (!$.fn.autocomplete) {
                                            console.error( "UI Autocomplete is REQUIRED" );
                                            return false;
                                        }

                                        $field.autocomplete({
                                            source: function(request, response) {
                                                self.is_loading = true;
                                                $.get(that.urls["search_products"], { term: request.term }, function(data) {
                                                    self.is_loading = false;
                                                    response(data);
                                                }, "json");
                                            },
                                            minLength: 3,
                                            delay: 300,
                                            open: function() {
                                                setPosition();
                                            },
                                            create: function() {
                                                $wrapper.append($field.autocomplete("widget"));
                                            },
                                            focus: function() { return false; },
                                            select: function(event, ui) {
                                                self.$emit("add_product", {
                                                    "id": ui.item.id,
                                                    "name": ui.item.value,
                                                    "price": ui.item.price_html
                                                });

                                                // Do not close autocompletion list after select
                                                var autocomplete = $(this).data('uiAutocomplete');
                                                autocomplete.do_not_close_autocomplete = 1;
                                                window.setTimeout(function() {
                                                    setPosition();
                                                    autocomplete.do_not_close_autocomplete = false;
                                                    autocomplete.menu.element.position($.extend({
                                                        of: autocomplete.element
                                                    }, autocomplete.options.position || { my: "left top", at: "left bottom", collision: "none" }));
                                                }, 0);

                                                return false;
                                            }
                                        }).data("uiAutocomplete")._renderItem = function( ul, item ) {
                                            return $("<li />").addClass("ui-menu-item-html").append("<div>"+ item.value + "</div>").appendTo( ul );
                                        };

                                        // Do not close autocompletion list after select
                                        var autocomplete = $field.data('uiAutocomplete');
                                        var oldClose = autocomplete.close;
                                        autocomplete.close = function(e) {
                                            if (this.do_not_close_autocomplete) { return false; }
                                            oldClose.apply(this, arguments);
                                        };

                                        function setPosition() {
                                            var position = $wrapper.offset(),
                                                left = 0,
                                                top = $wrapper.outerHeight();

                                            var widget = $field.autocomplete("widget");
                                            $(widget).css({
                                                top: top,
                                                left: left,
                                                width: $field.outerWidth() + "px",
                                                "box-sizing": "border-box"
                                            });
                                        }
                                    }
                                }
                            }
                        },
                        methods: {
                            addProduct: function(product, products) {
                                formatProduct(product);

                                if (!product.url) {
                                    product.url = that.urls["product_url"].replace("%id%", product.id);
                                }

                                var added_product = products.filter( function(added_product) {
                                    return (added_product.id === product.id);
                                });
                                if (!added_product.length) {
                                    products.push(product);
                                }
                            },
                            removeProduct: function(product, products) {
                                var index = products.indexOf(product);
                                if (index >= 0) {
                                    products.splice(index, 1);
                                }
                            }
                        },
                        mounted: function() {
                            var self = this;

                            that.initDragAndDrop(self);
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

                        var white_list = ["product_video_add", "file_add"];

                        if (error.id && white_list.indexOf(error.id) >= 0) {
                            self.$set(that.errors, error.id, error);
                        } else {
                            that.errors.global.push(error);
                        }
                    },
                    removeErrors: function(errors) {
                        var self = this;

                        // Очистка всех ошибок
                        if (errors === null) {
                            $.each(that.errors, function(key) {
                                if (key !== "global") {
                                    self.$delete(that.errors, key);
                                } else {
                                    that.errors.global.splice(0, that.errors.length);
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
                            var error_index = that.errors.global.indexOf(error_id);
                            if (typeof error_index >= 0) {
                                that.errors.splice(error_index, 1);
                            }
                        } else if (typeof error_id === "string") {
                            if (that.errors[error_id]) {
                                self.$delete(that.errors, error_id);
                            }
                        }
                    },

                    onTypeChange: function(section) {
                        if (section.view_type === "manual") {
                            if (section.value === "1") {
                                section.value = "0";
                            }
                        } else {
                            if (section.value !== "1") {
                                section.value = "1";
                            }
                        }
                    }
                },
                delimiters: ['{ { ', ' } }'],
                created: function () {
                    $view_section.css("visibility", "");
                },
                mounted: function() {
                    var self = this;
                }
            });

            return vue_model;
        };

        Section.prototype.initDragAndDrop = function(vue_model) {
            var that = this;

            var $document = $(document);

            var drag_data = {},
                over_locked = false,
                timer = 0;

            var $wrapper = $(vue_model.$el),
                products = vue_model.products;

            var move_class = "is-moving";

            $wrapper.on("dragstart", ".js-product-move-toggle", function(event) {
                var $move = $(this).closest(".s-product-wrapper");

                var product_id = "" + $move.attr("data-id"),
                    product = getProduct(product_id);

                if (!product) {
                    console.error("ERROR: product isn't exist");
                    return false;
                }

                event.originalEvent.dataTransfer.setDragImage($move[0], 20, 20);

                $.each(products, function(i, _product) {
                    _product.is_moving = (_product.id === product_id);
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
                    moveProduct($(this).closest(".s-product-wrapper"));
                    setTimeout( function() {
                        over_locked = false;
                    }, 100);
                }
            }

            function onDragEnd() {
                drag_data.move_product.is_moving = false;
                drag_data = {};
                $document.off("dragover", ".s-product-wrapper", onDragOver);
                $document.off("dragend", onDragEnd);
            }

            function moveProduct($over) {
                var product_id = "" + $over.attr("data-id"),
                    product = getProduct(product_id);

                if (!product) {
                    console.error("ERROR: product isn't exist");
                    return false;
                }

                if (drag_data.move_product === product) { return false; }

                var move_index = products.indexOf(drag_data.move_product),
                    over_index = products.indexOf(product),
                    before = (move_index > over_index);

                if (over_index !== move_index) {
                    products.splice(move_index, 1);

                    over_index = products.indexOf(product);
                    var new_index = over_index + (before ? 0 : 1);

                    products.splice(new_index, 0, drag_data.move_product);

                    $wrapper.trigger("change");
                }
            }

            //

            function getProduct(product_id) {
                var result = null;

                if (products) {
                    $.each(products, function(i, product) {
                        product.id = (typeof product.id === "number" ? "" + product.id : product.id);
                        if (product.id === product_id) {
                            result = product;
                            return false;
                        }
                    });
                }

                return result;
            }
        };

        // Сохранение

        Section.prototype.initSave = function() {
            var that = this;

            that.$wrapper.on("click", ".js-product-save", function (event, options) {
                options = (options || {});

                event.preventDefault();

                // Останавливаем сохранение если во фронте есть ошибки
                /*
                var errors = that.validate();
                if (errors.length) {
                    var $error = $(".wa-error-text:first");
                    if ($error.length) {
                        $(window).scrollTop($error.offset().top - 100);
                    }
                    return false;
                }
                */

                var loading = "<span class=\"icon top\" style=\"margin-left: .5rem\"><i class=\"fas fa-spinner fa-spin\"></i></span>";

                var $button = $(this),
                    $loading = $(loading).appendTo($button.attr("disabled", true));

                // Очищаем ошибки
                $.each(that.errors, function(key, error) {
                    // Точечные ошибки
                    if (key !== "global") { Vue.delete(that.errors, key); }
                    // Общие ошибки
                    else if (error.length) {
                        error.splice(0, error.length);
                    }
                });

                sendRequest()
                    .done( function() {
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
                                deferred.resolve(response);
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
                        "name": "product[id]",
                        "value": that.product.id
                    }
                ];

                result.push({
                    "name": "product[cross_selling][value]",
                    "value": (that.cross_selling.view_type === 'auto' ? 1 : that.cross_selling.value)
                });

                if (that.cross_selling.products.length) {
                    $.each(that.cross_selling.products, function(i, product) {
                        result.push({
                            name: "product[cross_selling][products][]",
                            value: product.id
                        });
                    });
                }

                result.push({
                    "name": "product[upselling][value]",
                    "value": (that.upselling.view_type === 'auto' ? 1 : that.upselling.value)
                });

                if (that.upselling.products.length) {
                    $.each(that.upselling.products, function(i, product) {
                        result.push({
                            name: "product[upselling][products][]",
                            value: product.id
                        });
                    });
                }

                return result;
            }
        };

        return Section;

        //

        function formatProduct(product) {
            if (typeof product.is_moving !== "boolean") {
                product.is_moving = false;
            }

            return product;
        }

    })($);

    $.wa_shop_products.init.initProductRelatedSection = function(options) {
        return new Section(options);
    };

})(jQuery);