( function($) { "use strict";

    var Auth = ( function($) {

        Auth = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$form = that.$wrapper.find("form:first");

            // VARS
            that.templates = options["templates"];
            that.errors = options["errors"];
            that.scope = options["scope"];

            // DYNAMIC VARS
            that.reload = false;

            // INIT
            that.initClass();
        };

        Auth.prototype.initClass = function() {
            var that = this;

            if (typeof that.errors === "object" && Object.keys(that.errors).length ) {
                that.scope.DEBUG("Errors:", "error", that.errors);
            }

            that.$form.on("submit", function(event) {
                event.preventDefault();
            });

            that.$wrapper.on("change", "select, textarea, input", function(event) {
                var $field = $(this),
                    reload = !!$field.data("affects-rate") || !!$field.data("reload");

                var $field_wrapper = $field.closest(".wa-field-wrapper");
                if (!$field_wrapper.length) {
                    $field_wrapper = $field.parent();
                }

                var error = that.scope.validate($field_wrapper, true);
                if (!error.length) {
                    if (reload) {
                        that.update({reload: true});
                    }

                    // } else {
                    //     var errors = that.scope.validate(that.$wrapper);
                    //     if (!errors.length) {
                    //         that.update({ reload: true });
                    //     }
                    // }
                }
            });

            that.initType();

            that.initLogin();

            that.initDatepicker();
        };

        Auth.prototype.initLogin = function() {
            var that = this;

            that.$wrapper.on("click", ".js-show-login-dialog", function(event) {
                // event.preventDefault();
                //
                // new window.waOrder.ui.Dialog({
                //     $wrapper: $(that.templates["login"])
                // });
            });

            that.$wrapper.on("click", ".js-show-logout-dialog", function(event) {
                // event.preventDefault();
                //
                // new window.waOrder.ui.Dialog({
                //     $wrapper: $(that.templates["login"])
                // });
            });
        };

        Auth.prototype.initType = function() {
            var that = this;

            var $type_toggle = that.$wrapper.find(".js-type-toggle").first(),
                $type_field = that.$wrapper.find(".js-type-field");

            var toggle = new window.waOrder.ui.Toggle({
                $wrapper: $type_toggle,
                change: function(event, target, toggle) {
                    var id = $(target).data("id");
                    if (id) {
                        that.reload = true;
                        $type_field.val(id);
                        that.scope.update();
                    }
                }
            });
        };

        Auth.prototype.initDatepicker = function() {
            var that = this;

            that.$wrapper.find(".js-datepicker").each( function() {
                var $input = $(this);

                var options = {};

                var alt_selector = $input.data("alt");
                if (alt_selector) {
                    var $alt_input = $input.closest(".wa-field-wrapper").find(alt_selector);
                    if ($alt_input.length) {
                        options["altField"] = $alt_input;
                        options["altFormat"] = "yy-mm-dd";
                    }
                }

                $input.datepicker(options);

                $input.on("keydown keypress keyup", function(event) {
                    if ( event.which === 13 ) {
                        event.preventDefault();
                    }
                });
            });

            //

            function checkDate(value) {
                var format = $.datepicker._defaults.dateFormat,
                    is_valid = false;

                try {
                    $.datepicker.parseDate(format, value);
                    is_valid = true;
                } catch(e) {
                    is_valid = false;
                }

                return is_valid;
            }
        };

        // REQUIRED

        /**
         * @return {Array}
         * */
        Auth.prototype.getData = function(options) {
            var that = this,
                result = [];

            if (options.clean) {
                result.push({
                    name: "auth[html]",
                    value: 1
                });

            } else {
                var render_errors = (options.render_errors !== false);

                if (that.$form.length) {
                    result = that.$form.serializeArray();
                }

                if (that.reload) {
                    result.push({
                        name: "auth[html]",
                        value: 1
                    });
                }

                var errors = that.scope.validate(that.$form, render_errors);
                if (errors.length) {
                    result.errors = errors;
                }
            }

            return result;
        };

        Auth.prototype.render = function(api) {
            var that = this,
                is_changed = false;

            if (api.auth && api.auth["html"]) {
                that.$wrapper.replaceWith(api.auth["html"]);
                is_changed = true;
            }

            return is_changed;
        };

        /**
         * @param {Object} errors
         * @return {Array}
         * */
        Auth.prototype.renderErrors = function(errors) {
            var that = this,
                result = [];

            $.each(errors, function(i, error) {
                result.push(error);
            });

            return result;
        };

        // PROTECTED

        Auth.prototype.update = function(options) {
            var that = this;

            if (options.reload) {
                that.reload = true;
            }

            return that.scope.update();
        };

        return Auth;

    })($);

    var Region = ( function($) {

        Region = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$form = that.$wrapper.find("form:first");

            // VARS
            that.errors = options["errors"];
            that.scope = options["scope"];

            // DYNAMIC VARS
            that.reload = true;

            // INIT
            that.initClass();
        };

        Region.prototype.initClass = function() {
            var that = this;

            var key_timer = 0,
                key_timeout = 2000,
                change_timer = 0,
                change_timeout = 100,
                update_after_blur = false;

            if (typeof that.errors === "object" && Object.keys(that.errors).length ) {
                that.scope.DEBUG("Errors:", "error", that.errors);
            }

            that.$form.on("submit", function(event) {
                event.preventDefault();
            });

            var $location_field = that.$form.find(".js-location-field"),
                $country_field = that.$form.find(".js-country-field"),
                $region_field = that.$form.find(".js-region-field"),
                $city_field = that.$form.find(".js-city-field"),
                $zip_field = that.$form.find(".js-zip-field");

            fieldWatcher($country_field, [$region_field, $city_field, $zip_field]);
            fieldWatcher($region_field, [$city_field, $zip_field]);
            fieldWatcher($city_field, [$zip_field]);
            fieldWatcher($location_field);
            fieldWatcher($zip_field);

            /**
             * @param {Object} $field
             * @param {Array?} $dependent_fields
             * */
            function fieldWatcher($field, $dependent_fields) {
                if ($field.length) {

                    $field.on("keydown", function() {
                        onKeyDown($field);
                    });

                    $field.on("change", function() {
                        if ($dependent_fields && $dependent_fields.length) {
                            $.each($dependent_fields, function(i, $field) {
                                if ($field.length) {
                                    $field.attr("disabled", true);
                                }
                            });
                        }

                        onChange($field);
                    });
                }
            }

            /**
             * @param {Object} $field
             * */
            function onKeyDown($field) {
                clearTimeout(key_timer);

                key_timer = setTimeout( function() {
                    $field.trigger("change");
                }, key_timeout);
            }

            /**
             * @param {Object} $field
             * */
            function onChange($field) {
                clearTimeout(key_timer);

                var $field_wrapper = $field.closest(".wa-field-wrapper"),
                    error = that.scope.validate($field_wrapper, true);

                if (!error.length) {
                    var errors = that.scope.validate(that.$wrapper);
                    if (!errors.length) {
                        that.scope.trigger("region_change");
                    }
                }
            }
        };

        // REQUIRED

        /**
         * @return {Array}
         * */
        Region.prototype.getData = function(options) {
            var that = this,
                result = [];

            if (options.clean) {
                result.push({
                    name: "region[html]",
                    value: "only"
                });

            } else {
                var render_errors = (options.render_errors !== false);

                if (that.$form.length) {
                    result = that.$form.serializeArray();
                }

                if (that.reload) {
                    result.push({
                        name: "region[html]",
                        value: "only"
                    });
                }

                var errors = that.scope.validate(that.$form, render_errors);
                if (errors.length) {
                    result.errors = errors;
                }
            }

            return result;
        };

        Region.prototype.render = function(api) {
            var that = this,
                is_changed = false;

            if (api.region && api.region["html"]) {
                that.$wrapper.replaceWith(api.region["html"]);
                is_changed = true;
            }

            return is_changed;
        };

        /**
         * @param {Object} errors
         * @return {Array}
         * */
        Region.prototype.renderErrors = function(errors) {
            var that = this,
                result = [];

            $.each(errors, function(i, error) {
                result.push(error);
            });

            return result;
        };

        // PROTECTED

        Region.prototype.update = function(data) {
            var that = this;

            that.reload = !!data.reload;

            that.scope.update()
                .always( function() {
                    that.reload = true;
                });
        };

        return Region;

    })($);

    var Shipping = ( function($) {

        var Types = ( function($) {

            Types = function(options) {
                var that = this;

                // DOM
                that.$wrapper = options["$wrapper"];

                // VARS
                that.active_class = "is-active";
                that.onChange = (typeof options["onChange"] === "function" ? options["onChange"] : function() {});

                // DYNAMIC VARS
                that.$active = that.$wrapper.find(".wa-type-wrapper." + that.active_class);

                // INIT
                that.initClass();
            };

            Types.prototype.initClass = function() {
                var that = this;

                that.$wrapper.on("click", ".wa-type-wrapper", function(event) {
                    event.preventDefault();
                    that.changeType( $(this) );
                });
            };

            /**
             * @param {Object} $type
             * */
            Types.prototype.changeType = function($type) {
                var that = this;

                if ($type.hasClass(that.active_class)) { return false; }

                var id = $type.data("id");

                if (id) {
                    if (that.$active.length) { that.$active.removeClass(that.active_class); }
                    that.$active = $type.addClass(that.active_class);
                    that.onChange(id);
                }
            };

            return Types;

        })(jQuery);

        Shipping = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$form = that.$wrapper.find("form:first");

            // VARS
            that.errors = options["errors"];
            that.scope = options["scope"];

            // DYNAMIC VARS
            that.reload = true;

            // INIT
            that.initClass();
        };

        Shipping.prototype.initClass = function() {
            var that = this;

            if (typeof that.errors === "object" && Object.keys(that.errors).length ) {
                that.scope.DEBUG("Errors:", "error", that.errors);
            }

            that.$form.on("submit", function(event) {
                event.preventDefault();
            });

            var $variant_field = null;

            var $types_section = that.$wrapper.find("#js-delivery-types-section");
            if ($types_section.length) {
                var $type_field = $types_section.find(".js-type-field");

                new Types({
                    $wrapper: $types_section,
                    onChange: function (id) {
                        $type_field.val(id);
                        that.scope.DEBUG("Delivery type changed — " + id, "info");

                        if ($variant_field) {
                            $variant_field.val("");
                        }

                        that.update({
                            reload: true
                        });
                    }
                });
            }

            var $variants_section = that.$wrapper.find("#js-delivery-variants-section");
            if ($variants_section.length) {
                $variant_field = $variants_section.find(".js-variant-field");

                new window.waOrder.ui.Dropdown({
                    $wrapper: $variants_section.find(".js-variants-select"),
                    hover: false,
                    change_title: false,
                    change_selector: ".wa-dropdown-item",
                    change: function(event, target, dropdown) {
                        var $target = $(target),
                            variant_id = $target.data("id"),
                            name = $target.find(".wa-name").data("name");

                        $variant_field.val(variant_id);
                        dropdown.setTitle(name);

                        that.update({
                            region: true
                        });
                    }
                });
            }
        };

        // REQUIRED

        /**
         * @return {Array}
         * */
        Shipping.prototype.getData = function(options) {
            var that = this,
                result = [];

            if (options.clean) {
                result.push({
                    name: "shipping[html]",
                    value: "only"
                });

            } else {
                var render_errors = (options.render_errors !== false);

                if (that.$form.length) {
                    result = that.$form.serializeArray();
                }

                if (that.reload) {
                    result.push({
                        name: "shipping[html]",
                        value: "only"
                    });
                }

                var errors = that.scope.validate(that.$form, render_errors);
                if (errors.length) {
                    result.errors = errors;
                }
            }

            return result;
        };

        Shipping.prototype.render = function(api) {
            var that = this,
                is_changed = false;

            if (api.shipping && api.shipping["html"]) {
                that.$wrapper.replaceWith(api.shipping["html"]);
                is_changed = true;
            }

            return is_changed;
        };

        /**
         * @param {Object} errors
         * @return {Array}
         * */
        Shipping.prototype.renderErrors = function(errors) {
            var that = this,
                result = [];

            $.each(errors, function(i, error) {
                result.push(error);
            });

            return result;
        };

        // PROTECTED

        Shipping.prototype.update = function(data) {
            var that = this;

            that.reload = !!data.reload;

            that.scope.update()
                .always( function() {
                    that.reload = true;
                });
        };

        return Shipping;

    })($);

    var Details = ( function($) {

        Details = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$form = that.$wrapper.find("form:first");

            // VARS
            that.errors = options["errors"];
            that.scope = options["scope"];

            // DYNAMIC VARS
            that.reload = true;

            // INIT
            that.initClass();
        };

        Details.prototype.initClass = function() {
            var that = this;

            if (typeof that.errors === "object" && Object.keys(that.errors).length ) {
                that.scope.DEBUG("Errors:", "error", that.errors);
            }

            that.$form.on("submit", function(event) {
                event.preventDefault();
            });

            that.$wrapper.on("change", "select, textarea, input", function(event) {
                var $field = $(this),
                    reload = !!$field.data("affects-rate") || !!$field.data("reload");

                var $field_wrapper = $field.closest(".wa-field-wrapper");
                if (!$field_wrapper.length) {
                    $field_wrapper = $field.parent();
                }

                var error = that.scope.validate($field_wrapper, true);
                if (!error.length) {
                    if (reload) {
                        that.update({
                            reload: true
                        });
                    }

                    // } else {
                    //     var errors = that.scope.validate(that.$wrapper);
                    //     if (!errors.length) {
                    //         that.update({
                    //             reload: true
                    //         });
                    //     }
                    // }
                }
            });

            var $photos_section = that.$wrapper.find(".wa-photos-section");
            if ($photos_section.length) {
                $photos_section.on("click", ".js-show-photo", function(event) {
                    event.preventDefault();
                    window.open(this.href);
                });
            }
        };

        // REQUIRED

        /**
         * @return {Array}
         * */
        Details.prototype.getData = function(options) {
            var that = this,
                result = [];

            if (options.clean) {
                result.push({
                    name: "details[html]",
                    value: "only"
                });

            } else {
                var render_errors = (options.render_errors !== false);

                if (that.$form.length) {
                    result = that.$form.serializeArray();
                }

                if (that.reload) {
                    result.push({
                        name: "details[html]",
                        value: "only"
                    });
                }

                var errors = that.scope.validate(that.$form, render_errors);
                if (errors.length) {
                    result.errors = errors;
                }
            }

            return result;
        };

        Details.prototype.render = function(api) {
            var that = this,
                is_changed = false;

            if (api.details && api.details["html"]) {
                that.$wrapper.replaceWith(api.details["html"]);
                is_changed = true;
            }

            return is_changed;
        };

        /**
         * @param {Object} errors
         * @return {Array}
         * */
        Details.prototype.renderErrors = function(errors) {
            var that = this,
                result = [];

            $.each(errors, function(i, error) {
                result.push(error);
            });

            return result;
        };

        // PROTECTED

        Details.prototype.update = function(data) {
            var that = this;

            that.reload = !!data.reload;

            that.scope.update()
                .always( function() {
                    that.reload = true;
                });
        };

        return Details;

    })($);

    var Payment = ( function($) {

        Payment = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$form = that.$wrapper.find("form:first");

            // VARS
            that.errors = options["errors"];
            that.scope = options["scope"];
            that.locales = options["locales"];

            // DYNAMIC VARS
            that.reload = true;

            // INIT
            that.initClass();
        };

        Payment.prototype.initClass = function() {
            var that = this;

            if (typeof that.errors === "object" && Object.keys(that.errors).length ) {
                that.scope.DEBUG("Errors:", "error", that.errors);
            }

            that.$form.on("submit", function(event) {
                event.preventDefault();
            });

            that.$wrapper.on("change", "select, textarea, input", function(event) {
                var $field = $(this),
                    reload = !!$field.data("affects-rate") || !!$field.data("reload");

                var $field_wrapper = $field.closest(".wa-field-wrapper");
                if (!$field_wrapper.length) {
                    $field_wrapper = $field.parent();
                }

                var error = that.scope.validate($field_wrapper, true);
                if (!error.length) {
                    if (reload) {
                        that.update({
                            reload: true
                        });
                    }

                    // } else {
                    //     var errors = that.scope.validate(that.$wrapper);
                    //     if (!errors.length) {
                    //         that.update({
                    //             reload: true
                    //         });
                    //     }
                    // }
                }
            });

            that.initMethods();
        };

        Payment.prototype.initMethods = function() {
            var that = this;

            // DOM
            var $list = that.$wrapper.find(".js-methods-list");
            if (!$list.length) { return false; }

            // CONST
            var active_class = "is-active";

            // DYNAMIC VARS
            var $active_method = $list.find(".wa-method-wrapper." + active_class);

            $list.on("click", ".wa-method-wrapper", function(event) {
                event.preventDefault();

                var $method = $(this);

                if ($active_method.length) {
                    if ($active_method[0] !== $method[0]) {
                        setActive($method);
                    }
                } else {
                    setActive($method);
                }
            });

            function setActive($method) {
                var $field = $method.find(".js-method-field");

                if ($active_method.length) {
                    $active_method.removeClass(active_class);
                }

                $active_method = $method.addClass(active_class);

                $field.attr("checked", true).trigger("change");
            }
        };

        // REQUIRED

        /**
         * @return {Array}
         * */
        Payment.prototype.getData = function(options) {
            var that = this,
                result = [];

            if (options.clean) {
                result.push({
                    name: "payment[html]",
                    value: "only"
                });

            } else {
                var render_errors = (options.render_errors !== false);

                if (that.$form.length) {
                    result = that.$form.serializeArray();
                }

                if (that.reload) {
                    result.push({
                        name: "payment[html]",
                        value: 1
                    });
                }

                var errors = that.scope.validate(that.$wrapper, render_errors);

                var $list = that.$wrapper.find(".js-methods-list");
                if ($list.length) {
                    var $method_field = $list.find(".js-method-field:checked");
                    if ($method_field.length) {

                        result.push({
                            name: $method_field.attr("name"),
                            value: $method_field.val()
                        });

                    } else {

                        errors.push({
                            $wrapper: $list,
                            id: "method_required",
                            value: that.locales["method_required"]
                        });

                        if (render_errors) {
                            var error_class = "wa-error";

                            if (!$list.hasClass(error_class)) {
                                var $error = $("<div class=\"wa-error-text\" />").text(that.locales["method_required"]).insertAfter($list);

                                $list.
                                    addClass(error_class)
                                    .one("change", function() {
                                        $list.removeClass(error_class);
                                        $error.remove();
                                    });
                            }
                        }
                    }
                }

                if (errors.length) {
                    result.errors = errors;
                }
            }

            return result;
        };

        Payment.prototype.render = function(api) {
            var that = this,
                is_changed = false;

            if (api.payment && api.payment["html"]) {
                that.$wrapper.replaceWith(api.payment["html"]);
                is_changed = true;
            }

            return is_changed;
        };

        /**
         * @param {Object} errors
         * @return {Array}
         * */
        Payment.prototype.renderErrors = function(errors) {
            var that = this,
                result = [];

            $.each(errors, function(i, error) {
                result.push(error);
            });

            return result;
        };

        // PROTECTED

        Payment.prototype.update = function(options) {
            var that = this;

            if (options.reload) {
                that.reload = true;
            }

            return that.scope.update();
        };

        return Payment;

    })($);

    var Confirm = ( function($) {

        Confirm = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$form = that.$wrapper.find("form:first");

            // VARS
            that.errors = options["errors"];
            that.scope = options["scope"];
            that.templates = options["templates"];

            // DYNAMIC VARS
            that.reload = true;

            // INIT
            that.initClass();
        };

        Confirm.prototype.initClass = function() {
            var that = this;

            if (typeof that.errors === "object" && Object.keys(that.errors).length ) {
                that.scope.DEBUG("Errors:", "error", that.errors);
            }

            that.$form.on("submit", function(event) {
                event.preventDefault();
            });

            that.$wrapper.on("change", "select, input, textarea", function(event) {
                var $field = $(this),
                    $field_wrapper = $field.closest(".wa-field-wrapper"),
                    reload = $field.data("reload");

                if (reload) {
                    that.update({
                        reload: true
                    });

                } else {
                    if ($field_wrapper.length) {
                        that.scope.validate($field_wrapper, true);
                    }
                }
            });

            var $comment_section = that.$wrapper.find(".wa-comment-section");
            if ($comment_section.length) {
                var open_class = "is-opened";
                $comment_section.on("click", " .js-open-section", function(event) {
                    event.preventDefault();
                    $comment_section.toggleClass(open_class);
                });
            }

            that.$wrapper.on("click", ".js-show-terms-dialog", function(event) {
                event.preventDefault();
                if (that.templates["terms_dialog"]) {
                    new that.scope.ui.Dialog({
                        $wrapper: $(that.templates["terms_dialog"])
                    });
                }
            });

            that.$wrapper.on("click", ".js-submit-order-button", function(event) {
                event.preventDefault();
                that.create();
            });
        };

        // REQUIRED

        /**
         * @return {Array}
         * */
        Confirm.prototype.getData = function(options) {
            var that = this,
                result = [];

            if (options.clean) {
                result.push({
                    name: "confirm[html]",
                    value: "only"
                });

            } else {
                var render_errors = (options.render_errors !== false);

                if (that.$form.length) {
                    result = that.$form.serializeArray();
                }

                if (that.reload) {
                    result.push({
                        name: "confirm[html]",
                        value: 1
                    });
                }

                var errors = that.scope.validate(that.$wrapper, render_errors);
                if (errors.length) {
                    result.errors = errors;
                }
            }

            return result;
        };

        Confirm.prototype.render = function(api) {
            var that = this,
                is_changed = false;

            if (api.confirm && api.confirm["html"]) {
                that.$wrapper.replaceWith(api.confirm["html"]);
                is_changed = true;
            }

            return is_changed;
        };

        /**
         * @param {Object} errors
         * @return {Array}
         * */
        Confirm.prototype.renderErrors = function(errors) {
            var that = this,
                result = [];

            $.each(errors, function(i, error) {
                result.push(error);
            });

            return result;
        };

        // PROTECTED

        Confirm.prototype.update = function(options) {
            var that = this;

            if (options.reload) {
                that.reload = true;
            }

            return that.scope.update();
        };

        Confirm.prototype.create = function() {
            var that = this;

            var errors = that.scope.validate(that.scope.$wrapper, true);
            if (errors.length) {
                that.scope.DEBUG("Errors:", "error", errors);
                focus(errors[0]);

            } else {
                that.scope.update({
                    create: true,
                    render_errors: true
                }).then( function(api) {
                    if (api.order_id) {
                        location.href = location.origin + location.pathname + "?order_id=" + api.order_id;

                    } else {
                        var errors = that.scope.renderErrors(api);
                        if (errors.length) {
                            focus(errors[0]);
                        }
                    }
                }, function(state, response) {
                    if (state === "front_errors") {
                        if (response.length) {
                            focus(response[0]);
                        }
                    }
                });
            }

            function focus(error) {
                var scroll_top = 0,
                    lift = 40;

                if (error["$field"] && error["$field"].length) {
                    scroll_top = error["$field"].offset().top - lift;
                    error["$field"].focus();
                }

                if (error["$wrapper"] && error["$wrapper"].length) {
                    scroll_top = error["$wrapper"].offset().top - lift;
                }

                $("html, body").scrollTop(scroll_top);
            }
        };

        return Confirm;

    })($);

    var Form = ( function($) {

        /**
         * @description constructor
         * */
        Form = function(options) {
            var that = this;

            if ( !(options.outer_options && (typeof options.outer_options.wrapper === "string") && options.outer_options.wrapper.length > 0) ) {
                throw new Error('Checkout wrapper element not specified.');
            }

            var $outer_wrapper = $(options.outer_options["wrapper"]);
            if ($outer_wrapper.length !== 1) {
                throw new Error('Error: Checkout form wrapper element must be exactly one on page (found '+ $outer_wrapper.length + ')');
            }

            if (!options.urls["calculate"]) {
                throw new Error('Must specify url_calculate');
            }

            if (!options.urls["create"]) {
                throw new Error('Must specify url_create_order');
            }

            // DOM
            that.$outer_wrapper = $outer_wrapper;
            that.$wrapper = options["$wrapper"];

            // CONST
            that.options = options["outer_options"];
            that.locales = options["locales"];
            that.urls = options["urls"];
            that.ui = window.waOrder.ui;

            // VARS
            that.sections = {};
            that.render_scheduled = false;
            that.calculate_promise = null;
            that.create_promise = null;

            // XHR
            that.calculate_xhr = null;
            that.reload_xhr = null;
            that.create_xhr = null;

            // that.blocks_order = []; // list of string keys in this.blocks
            // that.blocks = {}; // block_id: block object

            that.initClass();
        };

        Form.prototype.initClass = function() {
            var that = this;

            that.$wrapper.removeAttr("style").removeClass("is-not-ready");

            that.$outer_wrapper
                .data("controller", that)
                .trigger("wa_order_form_ready");

            var ready_promise = that.$wrapper.data("ready");
            ready_promise.resolve(that);

            that.$wrapper.on("region_change", onRegionChange);

            function onRegionChange() {
                var data = that.getFormData({
                    sections: ["auth", "region"]
                });

                that.update({
                    data: data
                }).then();
            }

            $(document).on("wa_order_cart_changed", cartWatcher);

            function cartWatcher() {
                var is_exist = $.contains(document, that.$wrapper[0]);
                if (is_exist) {
                    that.update().then();
                } else {
                    $(document).off("click", cartWatcher);
                }
            }
        };

        Form.prototype.DEBUG = function() {
            var that = this,
                log_function = console.log;

            var styles = {
                "hint": "font-weight: bold; color: #666;",
                "info": "font-weight: bold; font-size: 1.25em; color: blue;",
                "warn": "font-weight: bold; font-size: 1.25em;",
                "error": "font-weight: bold; font-size: 1.25em;"
            };

            if (that.options && that.options.DEBUG) {
                if (styles[arguments[1]]) {
                    arguments[0] = (typeof arguments[0] === "string" ? "%c" + arguments[0] : arguments[0]);

                    switch (arguments[1]) {
                        case "info":
                            log_function = console.info;
                            break;
                        case "error":
                            log_function = console.error;
                            break;
                        case "warn":
                            log_function = console.warn;
                            break;
                    }

                    arguments[1] = styles[arguments[1]];
                }

                return log_function.apply(console, arguments);
            }
        };

        // CONSTRUCTORS

        Form.prototype.initAuth = function(options) {
            var that = this;

            options["scope"] = that;

            that.sections["auth"] = new Auth(options);

            that.DEBUG("Auth initialized", "info", that.sections["auth"]);
        };

        Form.prototype.initRegion = function(options) {
            var that = this;

            options["scope"] = that;

            that.sections["region"] = new Region(options);

            that.DEBUG("Region initialized", "info", that.sections["region"]);
        };

        Form.prototype.initShipping = function(options) {
            var that = this;

            options["scope"] = that;

            that.sections["shipping"] = new Shipping(options);

            that.DEBUG("Shipping initialized", "info", that.sections["shipping"]);
        };

        Form.prototype.initDetails = function(options) {
            var that = this;

            options["scope"] = that;

            that.sections["details"] = new Details(options);

            that.DEBUG("Details initialized", "info", that.sections["details"]);
        };

        Form.prototype.initPayment = function(options) {
            var that = this;

            options["scope"] = that;

            that.sections["payment"] = new Payment(options);

            that.DEBUG("Payment initialized", "info", that.sections["payment"]);
        };

        Form.prototype.initConfirm = function(options) {
            var that = this;

            options["scope"] = that;

            that.sections["confirm"] = new Confirm(options);

            that.DEBUG("Confirm initialized", "info", that.sections["confirm"]);
        };

        // PROTECTED

        /**
         * @param {string} event_name
         * @param {Object|Array?} data
         * */
        Form.prototype.trigger = function(event_name, data) {
            var that = this;

            var form_event_name = "wa_order_form_" + event_name;

            that.$wrapper.triggerHandler(event_name, (data || null));
            that.$outer_wrapper.trigger(form_event_name, (data || null));
        };

        /**
         * @param {Object} $wrapper
         * @param {Boolean?} render_errors
         * @return {Array} with errors
         * */
        Form.prototype.validate = function($wrapper, render_errors) {
            var that = this,
                errors = [];

            $wrapper.find(".wa-input").each( function() {
                var $input = $(this),
                    name = $input.attr("name"),
                    value = $input.val().trim(),
                    is_disabled = $input.is(":disabled");

                if (is_disabled) { return true; }

                if (!value.length) {
                    var is_required = !!$input.attr("required");
                    if (is_required) {
                        errors.push({
                            $field: $input,
                            name: name,
                            value: that.locales["required"]
                        });
                    }

                } else if (!name) {
                    return true;

                } else if ($input.hasClass("wa-email")) {
                    var is_email = that.ui.validate.email(value);
                    if (!is_email) {
                        errors.push({
                            $field: $input,
                            name: name,
                            value: that.locales["invalid"]
                        });
                    }

                } else if ($input.hasClass("wa-phone")) {
                    var is_phone = that.ui.validate.phone(value);
                    if (!is_phone) {
                        errors.push({
                            $field: $input,
                            name: name,
                            value: that.locales["invalid"]
                        });
                    }

                } else if ($input.hasClass("wa-url")) {
                    var is_url = that.ui.validate.url(value);
                    if (!is_url) {
                        errors.push({
                            $field: $input,
                            name: name,
                            value: that.locales["invalid"]
                        });
                    }

                } else if ($input.hasClass("wa-number")) {
                    var is_number = that.ui.validate.number(value);
                    if (!is_number) {
                        errors.push({
                            $field: $input,
                            name: name,
                            value: that.locales["invalid"]
                        });
                    }
                }
            });

            $wrapper.find(".wa-checkbox").each( function() {
                var $input = $(this),
                    name = $input.attr("name"),
                    is_active = $input.is(":checked"),
                    is_disabled = $input.is(":disabled");

                if (is_disabled) { return true; }

                if (!is_active) {
                    var is_required = !!$input.attr("required");
                    if (is_required) {
                        errors.push({
                            $field: $input,
                            name: name,
                            value: that.locales["required"]
                        });
                    }

                } else if (!name) {
                    return true;
                }
            });

            $wrapper.find(".wa-select, wa-textarea").each( function() {
                var $input = $(this),
                    name = $input.attr("name"),
                    value = $.trim($input.val()),
                    is_disabled = $input.is(":disabled");

                if (is_disabled) { return true; }

                if (!value.length) {
                    var is_required = !!$input.attr("required");
                    if (is_required) {
                        errors.push({
                            $field: $input,
                            name: name,
                            value: that.locales["required"]
                        });
                    }

                } else if (!name) {
                    return true;
                }
            });

            if (render_errors) {
                if (errors.length) {
                    $.each(errors, function(i, error) {
                        renderError(error);
                    });
                }
            }

            return errors;

            function renderError(error) {
                var $error = $("<div class=\"wa-error-text\" />").text(error.value);
                var error_class = "wa-error";

                if (error.$field) {
                    var $field = error.$field;

                    if (!$field.hasClass(error_class)) {
                        $field.addClass(error_class);

                        var $field_wrapper = $field.closest(".wa-field-wrapper");
                        if ($field_wrapper.length) {
                            $field_wrapper.append($error);
                        } else {
                            $error.insertAfter($field);
                        }

                        $field.on("change keyup", removeFieldError);
                    }
                }

                function removeFieldError() {
                    $field.removeClass(error_class);
                    $error.remove();

                    $field.off("change", removeFieldError);
                }
            }
        };

        /**
         * @param {Object} api
         * @return {Array}
         * */
        Form.prototype.renderErrors = function(api) {
            var that = this,
                result = [];

            if (api.error_step_id && api.errors) {
                $.each(that.sections, function(section_id, section) {
                    if (api.error_step_id === section_id) {
                        if (typeof section.renderErrors === "function") {
                            var errors = section.renderErrors(api.errors);
                            if (errors.length) {
                                result = result.concat(errors);
                            }
                        }
                    }
                });
            }

            return result;
        };

        /**
         * @param {Object?} options
         * @return {Array}
         * */
        Form.prototype.getFormData = function(options) {
            options = ( options ? options : {});

            var that = this,
                result = [],
                errors = [],
                render_errors = !!options.render_errors;

            $.each(that.sections, function(section_id, section) {
                var clean = false;

                if (options.sections && Array.isArray(options.sections)) {
                    if ( !(options.sections.indexOf(section_id) >= 0) ) {
                        clean = true;
                    }
                }

                if (typeof section.getData === "function") {
                    var render_section_errors = (render_errors && !errors.length),
                        data = section.getData({
                            render_errors: render_section_errors,
                            clean: clean
                        });

                    if (data.length) {
                        result = result.concat(data);
                    }
                    if (data.errors) {
                        errors = errors.concat(data.errors);
                    }
                }
            });

            if (errors.length) {
                result.errors = errors;
                that.DEBUG("Errors:", "warn", errors);
            }

            return result;
        };

        /**
         * @param {Object?} options
         * @return {Promise}
         * */
        Form.prototype.update = function(options) {
            options = (options ? options : {});

            var that = this,
                deferred = $.Deferred(),
                promise = deferred.promise(),
                form_data = [];

            var render_errors = !!options.render_errors,
                is_changed = false;

            if (options.data) {
                form_data = options.data;

            } else {
                form_data = that.getFormData({
                    render_errors: render_errors
                });
            }

            if (render_errors && form_data.errors) {
                deferred.reject("front_errors", form_data.errors);
                return promise;

            } else {

                promise = (options.create ? that.create(form_data) : that.calculate(form_data));

                var locked_class = "is-locked";

                $.each(that.sections, function(section_id, section) {
                    section.$wrapper.addClass(locked_class);

                    promise
                        .always( function() {
                            section.$wrapper.removeClass(locked_class);
                        })
                        .then( function(api) {
                            if (typeof section.render === "function") {
                                var section_changed = section.render(api);
                                if (section_changed) {
                                    is_changed = true;
                                    that.trigger(section_id + "_changed", api);
                                }
                            }
                        }, function(state, response) {
                            if (response) {
                                that.DEBUG("render errors:", "errors", response);
                            }
                        });
                });

                promise.then( function(api) {
                    if (is_changed) {
                        that.trigger("changed", api);
                    }
                });
            }

            return promise;
        };

        /**
         * @param {Object} form_data
         * */
        Form.prototype.calculate = function(form_data) {
            var that = this;

            // there's no more that one form/calculate "thread"
            if (that.calculate_promise) {
                // previous existing "thread" will restart after abort
                if (that.calculate_xhr) {
                    that.calculate_xhr.abort();
                }

                // but the promise is still correct
                return that.calculate_promise;
            }

            // this deferred is resolved once form/calculate request is completed
            // (may take more than one request if some are cancelled)
            var result_deferred = $.Deferred();
            that.calculate_promise = result_deferred.promise();
            that.calculate_promise.then( function() {
                that.calculate_promise = null;
                that.calculate_xhr = null;
            }, function() {
                that.calculate_promise = null;
                that.calculate_xhr = null;
            });

            restart();

            return that.calculate_promise;

            // attempt to run form/calculate until it completes
            // restarts itself if aborted by addService()
            function restart() {
                if (!form_data) {
                    that.DEBUG("Form data is required.", "error");
                    result_deferred.reject("error");
                    return false;
                }

                form_data.push({
                    name: "response",
                    value: "json"
                });

                that.DEBUG("Form data:", "info");
                that.DEBUG(form_data);

                that.calculate_xhr = $.post(that.urls["calculate"], form_data, "json")
                    .done( function(response) {
                        // save succeeded
                        if (response.status === "ok") {
                            var api = formatAPI(response.data);
                            that.DEBUG("API received:", "info");
                            that.DEBUG(api);

                            result_deferred.resolve(api);

                            // validation error
                        } else {
                            that.DEBUG("API not received.", "error", ( response.errors ? response.errors : "No error description") );
                            result_deferred.reject("fail", response);
                        }
                    })
                    .fail( function(jqXHR, state) {
                        if (state === "abort") {
                            restart();

                            // server error
                        } else {
                            that.DEBUG("Getting API aborted.", "error", state);
                            result_deferred.reject(state);
                        }
                    });
            }
        };

        /**
         * @param {Object} form_data
         * */
        Form.prototype.create = function(form_data) {
            var that = this;

            // there's no more that one form/create "thread"
            if (that.create_promise) {
                // previous existing "thread" will restart after abort
                if (that.create_xhr) {
                    that.create_xhr.abort();
                }

                // but the promise is still correct
                return that.create_promise;
            }

            var result_deferred = $.Deferred();
            that.create_promise = result_deferred.promise();
            that.create_promise.then( function() {
                that.create_promise = null;
                that.create_xhr = null;
            }, function() {
                that.create_promise = null;
                that.create_xhr = null;
            });

            request();

            return that.create_promise;

            function request() {
                if (!form_data) {
                    that.DEBUG("Form data is required.", "error");
                    result_deferred.reject("error");
                    return false;
                }

                form_data.push({
                    name: "response",
                    value: "json"
                });

                that.DEBUG("Form data for create:", "info");
                that.DEBUG(form_data);

                that.create_xhr = $.post(that.urls["create"], form_data, "json")
                    .done( function(response) {
                        // save succeeded
                        if (response.status === "ok") {
                            var api = formatAPI(response.data);
                            that.DEBUG("API received:", "info");
                            that.DEBUG(api);

                            result_deferred.resolve(api);

                        // validation error
                        } else {
                            that.DEBUG("API not received.", "error", ( response.errors ? response.errors : "No error description") );
                            result_deferred.reject("fail", response);
                        }
                    })
                    .fail( function(jqXHR, state) {
                        that.DEBUG("Getting API aborted.", "error", state);
                        result_deferred.reject(state);
                    });
            }

        };

        Form.prototype.reload = function() {
            var that = this,
                deferred = $.Deferred(),
                locked_class = "is-locked";

            if (!that.reload_xhr) {
                if (that.calculate_xhr) {
                    that.calculate_xhr.abort();
                    that.calculate_xhr = false;
                }

                that.$wrapper.addClass(locked_class);

                var form_data = that.getFormData();

                if (that.options) {
                    form_data.push({
                        name: "opts[DEBUG]",
                        value: (that.options.DEBUG ? 1 : 0 )
                    });

                    form_data.push({
                        name: "opts[wrapper]",
                        value: that.options.wrapper
                    });
                }

                form_data.push({
                    name: "response",
                    value: "html"
                });

                that.DEBUG("Form is reloading...", "info", form_data);
                that.trigger("before_reload", that);

                that.reload_xhr = $.post(that.urls["calculate"], form_data)
                    .done( function(html) {
                        that.$outer_wrapper.one("wa_order_form_ready", function() {
                            var new_controller = that.$outer_wrapper.data("controller");
                            deferred.resolve(new_controller);
                        });

                        that.$wrapper.replaceWith(html);

                        that.DEBUG("Form reloaded.", "info");
                        that.trigger("reloaded", that);
                    })
                    .fail( function(jqXHR, state) {
                        that.DEBUG("Reload is failed.", "error", state);
                        deferred.reject(state);
                    })
                    .always( function() {
                        that.$wrapper.removeClass(locked_class);
                        that.reload_xhr = false;
                    });
            }

            return deferred.promise();
        };

        // PUBLIC

        return Form;

        /**
         * @param {Object} api
         * @description correct data for you
         * */
        function formatAPI(api) {
            if (api.errors) {
                if (!Array.isArray(api.errors)) {
                    api.errors = destruct(api.errors);
                }
            }

            return api;

            function destruct(object) {
                var result = [];

                if (object) {
                    $.each(object, function(name, value) {
                        result.push({
                            name: name,
                            value: value
                        });
                    });
                }

                return result;
            }
        }

    })($);

    window.waOrder = (window.waOrder || {});

    window.waOrder.Form = Form;

})(jQuery);