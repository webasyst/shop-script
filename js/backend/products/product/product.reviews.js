( function($) {

    var Section = ( function($) {

        Section = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];

            // CONST
            that.components = options["components"];
            that.templates  = options["templates"];
            that.tooltips   = options["tooltips"];
            that.locales    = options["locales"];
            that.urls       = options["urls"];

            // VUE JS MODELS
            that.product        = options["product"];
            that.reviews        = formatReviews(options["reviews"]);
            that.review_states  = options["review_states"];
            that.pagination     = options["pagination"];
            that.filters        = options["filters"];
            that.active_filters = options["active_filters"];
            that.errors         = {};
            that.errors_global  = [];

            // DYNAMIC VARS

            // INIT
            that.vue_model = that.initVue();
            that.init();

            function formatReviews(reviews) {
                $.each(reviews, function(i, review) {
                    that.formatReview(review);
                });

                return reviews;
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

            // DOM
            var $view_section = that.$wrapper.find(".js-product-reviews-section");

            // VARS
            var states = {};

            var vue_model = Vue.createApp({
                data() {
                    return {
                        errors        : that.errors,
                        errors_global : that.errors_global,
                        product       : that.product,
                        reviews       : that.reviews,
                        pagination    : that.pagination,
                        filters       : that.filters,
                        active_filters: that.active_filters,
                        states        : states
                    }
                },
                components: {
                    "component-dropdown": {
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
                                hover: false,
                                items: ".dropdown-item",
                                change: function(event, target, dropdown) {
                                    var $target = $(target),
                                        active_item_id = $target.attr("data-id");

                                    if (self.active_item.id !== active_item_id) {
                                        self.$emit("change", active_item_id);

                                        var active_item_search = self.items.filter( function(item) {
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
                    },
                    "component-pagination": {
                        props: ["pagination"],
                        data: function() {
                            var self = this;
                            return {
                                page: self.pagination["page"],
                                pages: self.pagination["pages"],
                                range: 3
                            };
                        },
                        template: that.components["component-pagination"],
                        delimiters: ['{ { ', ' } }'],
                        computed: {
                            available_pages: function() {
                                var self = this;

                                var result = [];

                                var min = self.page - self.range,
                                    max = self.page + self.range;

                                if (min < 1) { max += (1 + Math.abs(min)); }
                                if (max > self.pages) { min -= Math.abs(self.pages - max); }

                                for (var $i = min; $i <= max; $i++) {
                                    if ($i > 0 && $i <= self.pages) {
                                        result.push($i);
                                    }
                                }

                                return result;
                            }
                        },
                        methods: {
                            changePage: function(page_number) {
                                var self = this;

                                if (page_number !== self.page) {
                                    // self.page = page_number;
                                    self.$emit("change", page_number);
                                }
                            }
                        }
                    },
                    "component-review": {
                        name: "component-review",
                        props: { review: { type: Object, requred: true, default: () => {} } },
                        data() {
                            return {
                                states: {
                                    reload_key: 0,
                                    is_locked: false,
                                    is_deleted: (this.review.status === that.review_states["deleted"]),
                                    is_wait: false
                                }
                            };
                        },
                        template: that.components["component-review"],
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
                                props: {
                                    modelValue: {
                                        type: Boolean,
                                        default: false
                                    },
                                    disabled: {
                                        type: Boolean,
                                        default: false
                                    },
                                    onChange: Function
                                },
                                emits: ["change"],
                                template: '<div class="switch wa-small"><input type="checkbox" v-bind:checked="modelValue" v-bind:disabled="prop_disabled"></div>',
                                delimiters: ['{ { ', ' } }'],
                                data() {
                                    return {
                                        prop_disabled: this.disabled
                                    };
                                },
                                mounted: function() {
                                    var self = this;

                                    $(self.$el).waSwitch({
                                        change: function(active, wa_switch, e) {
                                            if (typeof self.onChange === "function" && !self.prop_disabled) {
                                                self.prop_disabled = true;
                                                wa_switch.disable(self.prop_disabled);

                                                self.onChange(active)
                                                    .done( function() {
                                                        self.prop_disabled = false;
                                                        wa_switch.disable(self.prop_disabled);
                                                    });
                                            }
                                        }
                                    });
                                }
                            }
                        },
                        methods: {
                            htmlToText: function(html) {
                                if (typeof html !== "string") {
                                    return '';
                                }

                                return html.replace(/</g, '&lt;').replace(/>/g, '&gt;');
                            },
                            reviewDelete: function() {
                                var self = this;

                                if (!self.states.is_locked) {
                                    self.showDeleteConfirm(that.templates["dialog-review-delete"])
                                        .done( function() {
                                            self.states.is_locked = true;
                                            self.states.is_wait = true;

                                            self.reviewStatusChangeRequest(that.review_states["deleted"])
                                                .always( function() {
                                                    self.states.is_locked = false;
                                                    self.states.is_wait = false;
                                                })
                                                .done( function(response) {
                                                    self.states.is_deleted = true;
                                                    self.review.disabled = true;
                                                    self.review.status_model = false;
                                                    self.review.status = response.data.status;
                                                    self.states.reload_key += 1;
                                                });
                                        });
                                }
                            },
                            reviewRestore: function() {
                                var self = this;

                                if (!self.states.is_locked) {
                                    self.states.is_locked = true;
                                    self.states.is_wait = true;

                                    self.reviewStatusChangeRequest(that.review_states["published"])
                                        .always( function() {
                                            self.states.is_locked = false;
                                            self.states.is_wait = false;
                                        })
                                        .done( function(response) {
                                            self.states.is_deleted = false;
                                            self.review.disabled = false;
                                            self.review.status = response.data.status;
                                            self.review.status_model = response.data.status_model;
                                            self.states.reload_key += 1;
                                        });
                                }
                            },
                            onReviewStatusChange: function(change) {
                                var self = this;

                                self.states.is_locked = true;

                                var status = (change ? that.review_states["published"] : that.review_states["moderation"]);
                                return self.reviewStatusChangeRequest(status)
                                    .always( function() {
                                        self.states.is_locked = false;
                                    })
                                    .done( function(response) {
                                        self.review.status = response.data.status;
                                    });
                            },
                            reviewStatusChangeRequest: function(status) {
                                var self = this;

                                var data = {
                                    status: status,
                                    review_id: self.review.id
                                };

                                return $.post(that.urls["delete"], data, "json");
                            },
                            showDeleteConfirm: function(template) {
                                var deferred = $.Deferred();
                                var is_success = false;

                                $.waDialog({
                                    html: template,
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
                            },
                            imageView: function(image) {
                                var self = this;

                                var images = [],
                                    index = 0;

                                $.each(self.review.images, function(i, _image) {
                                    if (_image === image) { index = i; }
                                    images.push({
                                        href: _image.url,
                                        title: _image.title
                                    });
                                });

                                if (index) { images = images.slice(index).concat(images.slice(0, index)); }

                                $.swipebox(images, {
                                    useSVG : false,
                                    hideBarsDelay: false,
                                    afterOpen: function() {
                                        var $document = $($document);
                                        $document.on("scroll", closeSwipe);
                                        function closeSwipe() {
                                            var $close = $("#swipebox-close");
                                            if ($close.length) {
                                                $close.trigger("click");
                                            }
                                            $document.off("scroll", closeSwipe);
                                        }
                                    }
                                });
                            },
                            imageDelete: function(image) {
                                var self = this;

                                var index = self.review.images.indexOf(image);
                                if (index >= 0 && !image.states.is_locked) {
                                    self.showDeleteConfirm(that.templates["dialog-image-delete"])
                                        .done( function() {
                                            image.states.is_locked = true;
                                            image.states.is_delete = true;
                                            deleteRequest()
                                                .always( function() {
                                                    image.states.is_locked = false;
                                                    image.states.is_delete = false;
                                                })
                                                .done( function() {
                                                    self.review.images.splice(index, 1);
                                                });
                                        });
                                }

                                function deleteRequest() {
                                    var data = {
                                        image_id: image.id,
                                        review_id: self.review.id,
                                        product_id: that.product.id
                                    };

                                    return $.post(that.urls["image_delete"], data, "json");
                                }
                            },

                            formToggle: function(toggle) {
                                var self = this,
                                    form = self.review.form;

                                form.is_active = toggle;
                                form.description = "";
                            },
                            formAdd: function() {
                                var self = this,
                                    form = self.review.form;

                                if (!form.is_locked) {
                                    form.is_locked = true;

                                    reviewAdd()
                                        .always( function() {
                                            form.is_locked = false;
                                        })
                                        .done( function(response) {
                                            if (response.status === "ok") {
                                                var new_review = that.formatReview(response.data);
                                                self.formToggle(false);
                                                self.review.reviews.unshift(new_review);

                                                // highlight
                                                new_review.is_new = true;
                                                setTimeout( function() {
                                                    new_review.is_new = false;
                                                }, 2000);
                                            } else {
                                                console.log( "ERROR", response );
                                            }
                                        });
                                }

                                function reviewAdd() {
                                    var data = {
                                        text: self.review.form.description,
                                        parent_id: self.review.id,
                                        product_id: that.product.id
                                    };

                                    return $.post(that.urls["add"], data, "json");
                                }
                            }
                        }
                    }
                },
                methods: {
                    onChangeFilter: function(filter_id, data) {
                        var self = this;

                        switch (filter_id) {
                            case "page":
                                self.active_filters["page"] = data;
                                break;
                            case "limit":
                                self.active_filters["limit"] = data;
                                // сбрасываем каунтер страниц
                                self.active_filters["page"] = 1;
                                break;
                            case "sort_order":
                                self.active_filters["sort"] = data.sort;
                                self.active_filters["order"] = data.order;
                                self.active_filters["sort_order"] = data.sort + "_" + data.order;
                                break;
                            case "status":
                                self.active_filters["status"] = data;
                                self.active_filters["page"] = null;
                                self.active_filters["limit"] = null;
                                break;
                            case "images_count":
                                self.active_filters["images_count"] = data;
                                self.active_filters["page"] = null;
                                self.active_filters["limit"] = null;
                                break;
                        }

                        self.updateContent();
                    },
                    updateContent: function() {
                        var self = this,
                            filters = [];

                        if (self.active_filters["page"]) {
                            filters.push("page=" + self.active_filters["page"]);
                            if (self.active_filters["limit"]) {
                                filters.push("offset=" + (self.active_filters["page"] - 1) * self.active_filters["limit"]);
                            }
                        }
                        if (self.active_filters["limit"]) {
                            filters.push("limit=" + self.active_filters["limit"]);
                        }
                        if (self.active_filters["sort_order"]) {
                            filters.push("sort=" + self.active_filters["sort"]);
                            filters.push("order=" + self.active_filters["order"]);
                        }
                        if (self.active_filters["images_count"]) {
                            filters.push("filters[images_count]=" + self.active_filters["images_count"]);
                        }
                        if (self.active_filters["status"]) {
                            filters.push("filters[status]=" + self.active_filters["status"]);
                        }

                        var content_uri = that.urls["reviews_section"] + "?" + filters.join("&");
                        $.wa_shop_products.router.load(content_uri);

                        $(window).scrollTop(0);
                    }
                },
                delimiters: ['{ { ', ' } }'],
                created: function () {
                    $view_section.css("visibility", "");
                },
                mounted: function() {
                    that.$wrapper.trigger("section_mounted", ["reviews", that]);
                }
            }).mount($view_section[0]);

            return vue_model;
        };

        Section.prototype.formatReview = function(review) {
            var that = this;

            if (review.reviews.length) {
                $.each(review.reviews, function(i, inner_review) {
                    that.formatReview(inner_review);
                });
            }

            if (review.images.length) {
                $.each(review.images, function(i, image) {
                    image.states = {
                        is_locked: false,
                        is_delete: false
                    };
                });
            }

            review.is_new = false;

            review.form = {
                is_active: false,
                is_locked: false,
                description: ""
            }

            return review;
        };

        return Section;

    })($);

    $.wa_shop_products.init.initProductReviewsSection = function(options) {
        return new Section(options);
    };

})(jQuery);
