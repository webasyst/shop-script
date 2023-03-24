/**
 * @used at wa-apps/shop/templates/actions/settings/SettingsUnit.html
 * Controller for Unit Settings Page.
 **/
( function($) { "use strict";

    // FLEX TOOLTIP

    var Tooltip = ( function($) {

        // OBSERVER

        var observer = null;

        var Observer = ( function($) {

            Observer = function(options) {
                var that = this;

                this.init();
            };

            Observer.prototype.init = function() {
                var that = this,
                    $document = $(document);

                $document.on("mouseenter", "[data-tooltip-id]", function(event, force_show) {
                    var $target = $(this),
                        tooltip_id = $.trim($target.attr("data-tooltip-id"));

                    if (!tooltip_id.length) { return false; }

                    if (tooltips[tooltip_id]) {
                        var tooltip = tooltips[tooltip_id];
                        if (!tooltip.hover) { return false; }

                        if (tooltip.action === "hover") {
                            var start_time = tooltip.start_time,
                                hide_time = tooltip.hide_time,
                                animate = tooltip.animate;

                            // Принудительно закрываем другие подсказки если они показаны
                            $.each(tooltips, function(id, _tooltip) {
                                if (_tooltip.hover && _tooltip !== tooltip && _tooltip.is_open) {
                                    _tooltip.close();
                                }
                            });

                            $target.on("click change", updateTooltip);

                            if (force_show) {
                                tooltip.start_time = 0;
                                tooltip.animate = false;
                            }

                            tooltip.show($target);

                            tooltip.animate = animate;
                            tooltip.start_time = start_time;

                            $target.one("mouseleave", function(event, force_hide) {
                                $target.off("click change", updateTooltip);
                                if (force_hide) {
                                    tooltip.hide_time = 0;
                                    tooltip.animate = false;
                                }

                                tooltip.hide();

                                tooltip.animate = animate;
                                tooltip.hide_time = hide_time;
                            });
                        }

                    } else {
                        console.error("Tooltip is not found.");
                    }

                    function updateTooltip() {
                        var new_tooltip_id = $.trim($target.attr("data-tooltip-id"));
                        if (tooltip_id !== new_tooltip_id) {
                            $target
                                .trigger("mouseleave", true)
                                .trigger("mouseenter", true);
                        }
                    }
                });

                $document.on("click", "[data-tooltip-id]", function(event) {
                    var $target = $(this),
                        tooltip_id = $.trim($target.attr("data-tooltip-id"));

                    if (!tooltip_id.length) { return; }

                    if (tooltips[tooltip_id]) {
                        var tooltip = tooltips[tooltip_id];

                        if (tooltip.hover) { return; }

                        if (tooltip.is_open) {
                            // console.log("off");
                            tooltip.close();
                            $document.off("click", clickWatcher);
                        } else {
                            // console.log("on");
                            tooltip.open($target);
                            $document.on("click", clickWatcher);
                        }
                    } else {
                        console.error("Tooltip is not found.");
                    }

                    function clickWatcher(event) {
                        if (tooltip.is_open) {
                            var tooltip_target = event.target.closest("[data-tooltip-id]"),
                                tooltip_body = event.target.closest(".wa-flex-tooltip");

                            // click on button. no reaction
                            if (tooltip_target === $target[0]) {
                                // console.log( "is_target" );
                                // click on body. no reaction
                            } else if (tooltip_body === tooltip.$tooltip[0]) {
                                // console.log( "is_body" );
                                // click overside. close
                            } else {
                                // console.log( "click overside" );
                                tooltip.close();
                                $document.off("click", clickWatcher);
                            }
                        } else {
                            $document.off("click", clickWatcher);
                        }
                    }
                });

                $(window).on("resize scroll", function() {
                    // Принудительно закрываем другие подсказки если они показаны
                    $.each(tooltips, function(id, tooltip) {
                        if (tooltip.hover && tooltip.is_open) {
                            tooltip.close();
                        }
                    });
                });
            };

            return Observer;

        })(jQuery);

        // TOOLTIPS

        var tooltips = {};

        var Tooltip = ( function($) {

            function Tooltip(options) {
                var that = this;

                // CONST
                that.id = options["id"];
                that.html = options["html"];
                that.width = (typeof options["width"] === "string" ? options["width"] : null);
                that.index = (typeof options["index"] === "number" ? options["index"] : null);
                that.class = (typeof options["class"] === "string" ? options["class"] : null);
                that.hover = (typeof options["hover"] === "boolean" ? options["hover"] : true);
                that.action = (typeof options["action"] === "string" ? options["action"] : "hover");
                that.animate = (typeof options["animate"] === "boolean" ? options["animate"] : false);
                that.position = (typeof options["position"] === "string" ? options["position"] : "right");
                that.start_time = (typeof options["start_time"] === "number" ? options["start_time"] : 500);
                that.hide_time = (typeof options["hide_time"] === "number" ? options["hide_time"] : 500);

                // DOM
                that.$tooltip = getTooltip(that.html);

                // DYNAMIC VARS
                that.timeout = 0;
                that.is_open = false;

                // INIT
                that.init();

                function getTooltip() {
                    var tooltip_html = '<div class="wa-flex-tooltip"></div>';
                    var $tooltip = $(tooltip_html)
                        .attr("data-id", that.id)
                        .html(that.html);

                    if (that.width) {
                        $tooltip.css("width", that.width);
                    }

                    if (that.index) {
                        $tooltip.css("z-index", that.index);
                    }

                    if (that.class) {
                        $tooltip.addClass(that.class);
                    }

                    return $tooltip;
                }
            }

            Tooltip.prototype.init = function() {
                var that = this;

                // Если режим "понаведению", то при возврате назад на подсказку, отменяем закрытые
                if (that.hover) {
                    that.$tooltip.on("mouseenter", function() {
                        clearTimeout(that.timeout);
                        that.$tooltip.one("mouseleave", function() {
                            that.hide();
                        });
                    });
                }
            };

            /**
             * @description Открывает подсказку по таймеру. Таймер может отменяться
             * */
            Tooltip.prototype.show = function($target) {
                var that = this;

                // Кейс для ситуаций когда текст подсказки генерируется
                if (!that.html) {
                    var html = $target.attr("data-title");
                    if (html) {
                        that.$tooltip.html(html);
                    } else {
                        return false;
                    }
                }

                clearTimeout(that.timeout);
                that.timeout = setTimeout( function() {
                    that.open($target);
                }, that.start_time);
            };

            /**
             * @description Закрывает подсказку по таймеру. Таймер может отменяться
             * */
            Tooltip.prototype.hide = function() {
                var that = this;

                clearTimeout(that.timeout);
                that.timeout = setTimeout( function() {
                    that.close();
                }, that.hide_time);
            };

            /**
             * @description Принудительное открытие подсказки без таймаута
             * */
            Tooltip.prototype.open = function($target) {
                var that = this;

                var is_target_rendered = $.contains(document, $target[0]);
                if (!is_target_rendered) { return false; }

                that.$tooltip.appendTo($("body"));

                that.setPosition($target);

                if (that.animate) {
                    that.animateTooltip(true);
                }

                that.is_open = true;
            };

            /**
             * @description Принудительное закрытие подсказки без таймаута
             * */
            Tooltip.prototype.close = function() {
                var that = this;

                if (that.is_open) {
                    clearTimeout(that.timeout);

                    if (that.animate) {
                        that.animateTooltip(false).then( function() {
                            that.$tooltip.detach();
                        });
                    } else {
                        that.$tooltip.detach();
                    }

                    that.is_open = false;
                }
            };

            /**
             * @param {Boolean} animate
             * */
            Tooltip.prototype.animateTooltip = function(animate) {
                var that = this,
                    deferred = $.Deferred(),
                    time = 200;

                var shifted_class = "is-shifted",
                    animate_class = "is-animated";

                if (animate) {
                    that.$tooltip.addClass(shifted_class);
                    that.$tooltip[0].offsetHeight;
                    that.$tooltip
                        .addClass(animate_class)
                        .removeClass(shifted_class);

                    setTimeout( function() {
                        deferred.resolve();
                    }, time);

                } else {
                    that.$tooltip.addClass(shifted_class);
                    setTimeout( function() {
                        deferred.resolve();
                        that.$tooltip.removeClass(animate_class);
                    }, time);
                }

                return deferred.promise();
            };

            Tooltip.prototype.setPosition = function($target) {
                var that = this;

                var position = that.position;

                // hack для того чтобы узнать реальные размеры после рендера
                that.$tooltip[0].offsetHeight;

                var target_offset = $target.offset(),
                    target_left = target_offset.left,
                    target_top = target_offset.top,
                    target_w = $target.outerWidth(),
                    target_h = $target.outerHeight();

                var tooltip_w = that.$tooltip.outerWidth(),
                    tooltip_h = that.$tooltip.outerHeight();

                var page_w = $(window).width(),
                    page_h = $(document).height();

                var css = getCSS(position, true);

                that.$tooltip.css(css);

                function getCSS(position, correct) {
                    var indent = 8;

                    var top = "",
                        left = "";

                    switch (position) {
                        case "top-left":
                            top = target_top - indent - tooltip_h;
                            left = target_left;
                            break;
                        case "top":
                            top = target_top - indent - tooltip_h;
                            left = target_left + (target_w/2) - (tooltip_w/2);
                            break;
                        case "top-right":
                            top = target_top - indent - tooltip_h;
                            left = target_left + target_w - tooltip_w;
                            break;
                        case "right":
                            top = target_top + (target_h/2) - (tooltip_h/2);
                            left = target_left + target_w + indent;
                            break;
                        case "bottom-right":
                            top = target_top + target_h + indent;
                            left = target_left + target_w - tooltip_w;
                            break;
                        case "bottom":
                            top = target_top + target_h + indent;
                            left = target_left + (target_w/2) - (tooltip_w/2);
                            break;
                        case "bottom-left":
                            top = target_top + target_h + indent;
                            left = target_left;
                            break;
                        case "left":
                            top = target_top + (target_h/2) - (tooltip_h/2);
                            left = target_left - tooltip_w - indent;
                            break;
                        case "middle":
                            top = target_top + (target_h/2) - (tooltip_h/2);
                            left = target_left + (target_w/2) - (tooltip_w/2);
                            break;
                    }

                    var result = {
                        top: top,
                        left: left
                    };

                    if (correct) {
                        var new_position = position;
                        if (top + tooltip_h + indent > page_h) {
                            new_position = new_position.replace("bottom", "top");
                        } else if (top < indent) {
                            new_position = new_position.replace("top", "bottom");
                        }

                        if (left + tooltip_w + indent > page_w) {
                            new_position = new_position.replace("right", "left");
                        } else if (left < indent) {
                            new_position = new_position.replace("left", "right");
                        }

                        if (new_position !== position) {
                            return getCSS(new_position, false);
                        }
                    }

                    return result;
                }
            };

            return Tooltip;

        })($);

        // RESULT

        return function(options) {
            var result = null;

            if (typeof options === "undefined") {
                return tooltips;
            }

            if (!options["id"]) {
                console.error("Tooltip ID is required");
                return result;
            }

            if (tooltips[options["id"]]) {
                delete tooltips[options["id"]];
            }

            // Создаём и регистрируем tooltip
            var tooltip = new Tooltip(options);
            tooltips[tooltip.id] = tooltip;
            result = tooltip;

            // Создаём observer, если он не был создан ранее
            if (!observer) {
                observer = new Observer({});
            }

            return result;
        };

    })($);

    // DIALOGS

    /**
     * @used at wa-apps/shop/templates/actions/settings/SettingsUnitEdit.html
     * */
    var UnitEditDialog = ( function($) {

        var Dialog = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$form = that.$wrapper.find("form:first");

            // CONST
            that.scope = options["scope"];
            that.urls = that.scope["urls"];
            that.dialog = options["dialog"];
            that.unit_id = options["unit_id"];

            // DYNAMIC VARS

            // INIT
            that.init();

            console.log( that );
        };

        Dialog.prototype.init = function() {
            var that = this;

            // Autofocus
            setTimeout( function() {
                that.$wrapper.find(".js-autofocus:first").trigger("focus");
            }, 100);

            var $field_storefront = that.$wrapper.find(".js-field-storefront-name");
            that.$wrapper.on("input", ".js-field-short-name", function() {
                $field_storefront.attr("placeholder", $(this).val());
            });

            that.initSubmit();
        };

        Dialog.prototype.initSubmit = function() {
            var that = this,
                $form = that.$form,
                $errorsPlace = that.$wrapper.find(".js-errors-place"),
                is_locked = false;

            var $submit_button = that.$wrapper.find(".js-submit-button");

            that.$wrapper.one("change", function() {
                $submit_button.removeClass("green").addClass("yellow");
            });

            $form.on("submit", onSubmit);
            function onSubmit(event) {
                event.preventDefault();

                var formData = getData();

                if (formData.errors.length) {
                    renderErrors(formData.errors);
                } else {
                    request(formData.data);
                }
            }

            function getData() {
                var result = {
                        data: [],
                        errors: []
                    },
                    data = $form.serializeArray();

                $.each(data, function(index, item) {
                    result.data.push(item);
                });

                return result;
            }

            function renderErrors(errors) {
                var result = [];

                $errorsPlace.html("");

                if (!errors || !errors[0]) { errors = []; }

                $.each(errors, function(i, error) {
                    if (!error.text) { alert("error"); return true; }

                    if (error.id) {
                        switch (error.id) {
                            case "some_error":
                                renderError(error);
                                break;
                            case "some_error2":
                                renderError(error);
                                break;

                            default:
                                renderError(error);
                                break;
                        }

                    } else if (error.name) {
                        error.$field = that.$wrapper.find("[name=\"" + error.name + "\"]").first();
                        renderError(error);
                    }

                    result.push(error);
                });

                that.dialog.resize();

                return result;

                function renderError(error, $error_wrapper) {
                    var $error = $("<div class=\"s-error errormsg\" />").text(error.text);
                    var error_class = "error";

                    if (error.$field) {
                        var $field = error.$field;

                        if (!$field.hasClass(error_class)) {
                            $field.addClass(error_class);

                            if ($error_wrapper) {
                                $error_wrapper.append($error);
                            } else {
                                $error.insertAfter($field);
                            }

                            $field
                                .trigger("error")
                                .on("change keyup", removeFieldError);
                        }
                    } else {
                        $errorsPlace.append($error);
                    }

                    function removeFieldError() {
                        $error.remove();
                        $field
                            .removeClass(error_class)
                            .off("change keyup", removeFieldError);
                        that.dialog.resize();
                    }
                }

            }

            function request(data) {
                if (!is_locked) {
                    is_locked = true;
                    var animation = animateUI();
                    animation.lock();

                    $.post(that.urls["unit_save"], data, "json")
                        .always( function() {
                            animation.unlock();
                            is_locked = false;
                        })
                        .done( function(response) {
                            if (response.status === "ok") {
                                that.dialog.options.onSuccess(response.data);
                                that.dialog.close();
                            } else {
                                renderErrors(response.errors);
                            }
                        });
                }

                function animateUI() {
                    var $loading = $("<i class=\"icon16 loading\" />"),
                        locked_class = "is-locked",
                        is_displayed = false;

                    return {
                        lock: lock,
                        unlock: unlock
                    };

                    function lock() {
                        that.$wrapper.addClass(locked_class);
                        $submit_button.attr("disabled", true);
                        if (!is_displayed) {
                            $loading.appendTo($submit_button);
                            is_displayed = true;
                        }
                    }

                    function unlock() {
                        that.$wrapper.removeClass(locked_class);
                        $submit_button.attr("disabled", false);
                        if (is_displayed) {
                            $loading.detach();
                            is_displayed = false;
                        }
                    }
                }
            }
        };

        return Dialog;

    })($);

    /**
     * @used at wa-apps/shop/templates/actions/settings/SettingsUnitDeleteDialog.html
     * */
    var UnitDeleteDialog = ( function($) {

        var Dialog = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];

            // CONST
            that.unit_id = options["unit_id"];
            that.dialog = options["dialog"];
            that.scope = options["scope"];

            // INIT
            that.init();

            console.log( that );
        };

        Dialog.prototype.init = function() {
            var that = this,
                is_locked = false;

            that.$wrapper.on("click", ".js-submit-button", function(event) {
                event.preventDefault();

                var $button = $(this);

                if (!is_locked) {
                    is_locked = true;
                    $button.attr("disabled", true);
                    var $loading = $("<i class=\"icon16 loading\" />").appendTo($button);

                    deleteRequest()
                        .always( function() {
                            $loading.remove();
                            $button.attr("disabled", false);
                            is_locked = false;
                        })
                        .fail( function(errors) {
                            if (errors) {
                                // text
                                var $content = that.$wrapper.find(".wa-dialog-content").html("");
                                $.each(errors, function(i, error) {
                                    var $error = $("<div>", { class: "wa-error errormsg" }).html(error.text);
                                    $content.append($error);
                                });
                                // button
                                var $footer = that.$wrapper.find(".wa-dialog-footer").html("");
                                var $close_button = $("<button class=\"s-button button gray js-close-dialog\" type=\"submit\"></button>").text(that.scope.locales["close"])
                                $footer.append($close_button);

                                that.dialog.options.onLocked();
                            }
                        })
                        .done( function() {
                            that.dialog.options.onSuccess();
                            that.dialog.close()
                        });
                }
            });

            function deleteRequest() {
                var href = that.scope.urls["unit_delete"],
                    data = { id: that.unit_id };

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
        };

        return Dialog;

    })($);

    // PAGE

    var ShopUnitSettingsPage = ( function($) {
        // PAGE

        function Page(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$content = that.$wrapper.find(".js-page-content-section");

            // CONST
            that.components = options["components"];
            that.templates = options["templates"];
            that.tooltips = options["tooltips"];
            that.locales = options["locales"];
            that.urls = options["urls"];

            // VUE
            that.unit_search = (typeof options["unit_search"] === "string" ? options["unit_search"] : "");
            that.units = formatUnits(options["units"]);
            that.fractional = formatFractional(options["fractional"]);
            that.states = {
                locked: false,
                change_type: false,
                change_base_units: false,
                change_stock_units: false,
                help_display: false
            };

            // INIT
            that.vue_model = that.initVue( function(vue_model) {
                that.init(vue_model);
            });

            console.log( that );

            function formatUnits(units) {
                var result = [];

                $.each(units, function(i, unit) {
                    result.push(formatUnit(unit));
                });

                return result;
            }

            function formatFractional(fractional) {
                fractional.enabled = "" + fractional.enabled;
                fractional.stock_units.enabled = "" + fractional.stock_units.enabled;
                fractional.base_units.enabled = "" + fractional.base_units.enabled;

                return fractional;
            }
        }

        /**
         * @param {Function?} callback
         * @return {Vue}
        * */
        Page.prototype.initVue = function(callback) {
            var that = this;

            var $vue_section = that.$content;

            return new Vue({
                el: $vue_section[0],
                data: {
                    fractional: that.fractional,
                    units: that.units,
                    unit_search: that.unit_search,
                    states: that.states,
                    errors: {
                        fractional: [],
                        stock_units: [],
                        base_units: []
                    }
                },
                delimiters: ['{ { ', ' } }'],
                components: {
                    "component-units-table": {
                        props: ["units", "sort"],
                        template  : that.components["component_units_table"],
                        delimiters: ['{ { ', ' } }'],
                        components: {
                            "component-switch": {
                                props: ["value", "disabled"],
                                data: function() {
                                    return {
                                        disabled: (typeof this.disabled === "boolean" ? this.disabled : false)
                                    };
                                },
                                template: that.components["component_switch"],
                                delimiters: ['{ { ', ' } }'],
                                mounted: function() {
                                    var self = this,
                                        $wrapper = $(self.$el),
                                        $field = $wrapper.find("input");

                                    $field
                                        .on("change", function() {
                                            var active = $(this).is(":checked");
                                            self.$emit("input", active);
                                            self.$emit("change", active);
                                        })
                                        .iButton({
                                            classContainer: 'ibutton-container mini',
                                            labelOff : "",
                                            labelOn : ""
                                        });
                                }
                            }
                        },
                        methods: {
                            unitEdit: function(event, unit) {
                                var self = this;

                                if (!that.states.locked) {
                                    that.states.locked = true;

                                    var $target = $(event.currentTarget),
                                        $icon = $target.find(".icon16").hide(),
                                        $loading = $("<i class=\"icon16 loading\" />").prependTo($target);

                                    var href = that.urls["unit_edit_dialog"],
                                        data = { id: unit.id };

                                    $.post(href, data)
                                        .always( function () {
                                            $loading.remove();
                                            $icon.show();
                                            that.states.locked = false;
                                        })
                                        .done( function(html) {
                                            var dialog = $.waDialog({
                                                html: html,
                                                options: {
                                                    onSuccess: function(unit_data) {
                                                        unit["name"] = unit_data["name"];
                                                        unit["okei_code"] = unit_data["okei_code"];
                                                        unit["short_name"] = unit_data["short_name"];
                                                        unit["storefront_name"] = unit_data["storefront_name"];
                                                    },
                                                    initUnitEditDialog: function(options) {
                                                        options.scope = that;
                                                        return new UnitEditDialog(options);
                                                    }
                                                }
                                            });
                                        });
                                }
                            },
                            unitDelete: function(event, unit) {
                                var self = this;

                                if (!that.states.locked) {
                                    that.states.locked = true;

                                    var $target = $(event.currentTarget),
                                        $icon = $target.find(".icon16").hide(),
                                        $loading = $("<i class=\"icon16 loading\" />").prependTo($target);

                                    var href = that.urls["unit_delete_dialog"],
                                        data = { id: unit.id };

                                    $.post(href, data)
                                        .always( function () {
                                            $loading.remove();
                                            $icon.show();
                                            that.states.locked = false;
                                        })
                                        .done( function(html) {
                                            $.waDialog({
                                                html: html,
                                                onOpen: function($dialog, dialog) {
                                                    new UnitDeleteDialog({
                                                        $wrapper: $dialog,
                                                        dialog: dialog,
                                                        scope: that,
                                                        unit_id: unit.id
                                                    });
                                                },
                                                options: {
                                                    onLocked: function () {
                                                        unit.states.locked = true;
                                                    },
                                                    onSuccess: function(unit_data) {
                                                        var index = that.units.indexOf(unit);
                                                        that.units.splice(index, 1);
                                                    }
                                                }
                                            });
                                        });
                                }
                            },
                            unitStatusChange: function(unit) {
                                var href = that.urls["unit_status"],
                                    status = (unit.states.active ? "1" : "0"),
                                    data = { unit_id: unit.id, status: status };

                                if (!unit.states.status_loading) {
                                    unit.states.status_loading = true;

                                    request(href, data)
                                        .always( function() {
                                            unit.states.status_loading = false;
                                        })
                                        .done( function(response) {
                                            if (response.status === "ok") {
                                                unit.status = status;
                                                that.units.splice(that.units.indexOf(unit), 1);
                                                if (status === "0") {

                                                    var index = null;
                                                    $.each(that.units, function(i, _unit) {
                                                        if (_unit.id > unit.id) {
                                                            index = i;
                                                            return false;
                                                        }
                                                    });
                                                    if (index) {
                                                        that.units.splice(index, 0, unit);
                                                    } else {
                                                        that.units.unshift(unit);
                                                    }
                                                } else {
                                                    that.units.push(unit);
                                                }

                                            } else if (response.errors) {
                                                alert("Error: unit status");
                                                unit.states.locked = true;
                                                console.log(response.errors);
                                            }
                                        });
                                }

                                function request(href, data) {
                                    return $.post(href, data, "json");
                                }
                            }
                        }
                    }
                },
                computed: {
                    active_units: function() {
                        var self = this;

                        return self.units.filter( function(unit) {
                            return (unit.status !== "0");
                        });
                    },
                    inactive_units: function() {
                        var self = this;

                        return self.units.filter( function(unit) {
                            return (unit.status === "0");
                        });
                    },
                    visible_inactive_units: function() {
                        var self = this;

                        return self.inactive_units.filter( function(unit) {
                            return unit.states.visible;
                        });
                    }
                },
                methods: {
                    unitAdd: function(event) {
                        var self = this;

                        if (!self.states.locked) {
                            self.states.locked = true;

                            var $target = $(event.currentTarget),
                                $icon = $target.find(".icon16").hide(),
                                $loading = $("<i class=\"icon16 loading\" />").prependTo($target);

                            var href = that.urls["unit_edit_dialog"],
                                data = {};

                            $.post(href, data)
                                .always( function () {
                                    $loading.remove();
                                    $icon.show();
                                    self.states.locked = false;
                                })
                                .done( function(html) {
                                    var dialog = $.waDialog({
                                        html: html,
                                        options: {
                                            onSuccess: function(unit_data) {
                                                var new_unit = formatUnit(unit_data);
                                                that.units.push(new_unit);
                                                new_unit.states.highlight = true;
                                                setTimeout( function() {
                                                    new_unit.states.highlight = false;
                                                }, 2000);
                                            },
                                            initUnitEditDialog: function(options) {
                                                options.scope = that;
                                                return new UnitEditDialog(options);
                                            }
                                        }
                                    });
                                });
                        }
                    },
                    searchUnits: function () {
                        var self = this;

                        $.each(self.units, function(i, unit) {
                            var visible = true;

                            if (self.unit_search.length) {
                                var name = unit.name.toLowerCase(),
                                    short = unit.short_name.toLowerCase(),
                                    code = unit.okei_code.toLowerCase(),
                                    search = self.unit_search.toLowerCase();

                                visible = (name.indexOf(search) >= 0 || short.indexOf(search) >= 0 || code.indexOf(search) >= 0);
                            }

                            if (unit.states.visible !== visible) {
                                unit.states.visible = visible;
                            }
                        });
                    },

                    onFractionalChange: function() {
                        var self = this;
                        if (self.fractional.enabled === "1") {
                            that.showConfirmDialog(that.templates["dialog-fractional-change-confirm"])
                                .done(request)
                                .fail( function() {
                                    self.fractional.enabled = "0";
                                });
                        } else {
                            request();
                        }

                        function request() {
                            self.states.change_type = true;
                            if (self.errors.fractional.length) {
                                self.errors.fractional.splice(0, self.errors.fractional.length);
                            }

                            self.request(that.urls["change_type"], { unit_type: self.fractional.enabled })
                                .fail( function(errors) {
                                    if (errors.length) {
                                        self.errors.fractional = self.errors.fractional.concat(errors);
                                    }
                                    self.fractional.enabled = (self.fractional.enabled === "1" ? "0" : "1");
                                })
                                .always( function() {
                                    self.states.change_type = false;
                                });
                        }
                    },
                    onStockUnitsChange: function() {
                        var self = this;
                        if (self.fractional.stock_units.enabled === "1") {
                            that.showConfirmDialog(that.templates["dialog-stock-change-confirm"])
                                .done(request)
                                .fail( function() {
                                    self.fractional.stock_units.enabled = "0";
                                });
                        } else {
                            request();

                            if (self.fractional.base_units.enabled === "1") {
                                self.fractional.base_units.enabled = "0";
                            }
                        }

                        function request() {
                            self.states.change_stock_units = true;
                            self.request(that.urls["change_stock_units"], { stock_units: self.fractional.stock_units.enabled })
                                .always( function() {
                                    self.states.change_stock_units = false;
                                });
                        }
                    },
                    onBaseUnitsChange: function(event) {
                        var self = this;
                        if (self.fractional.base_units.enabled === "1") {
                            that.showConfirmDialog(that.templates["dialog-base-change-confirm"])
                                .done(request)
                                .fail( function() {
                                    self.fractional.stock_units.enabled = "0";
                                });
                        } else {
                            request();
                        }

                        function request() {
                            self.states.change_base_units = true;
                            self.request(that.urls["change_base_units"], { base_units: self.fractional.base_units.enabled })
                                .always( function() {
                                    self.states.change_base_units = false;
                                });
                        }
                    },
                    request: function(href, data) {
                        var deferred = $.Deferred();

                        $.post(href, data, "json")
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
                    },

                    closeHelp: function() {
                        var self = this;
                        $(window).scrollTop(0);
                        self.states.help_display = false;
                    }
                },
                mounted: function() {
                    var self = this;
                    if (typeof callback === "function") { callback(self); }
                }
            });
        };

        Page.prototype.init = function(vue_model) {
            var that = this,
                $window = $(window);

            that.initTooltips(that.tooltips);

            that.initDragAndDrop(vue_model);

            that.$wrapper
                .css("visibility", "")
                .data("controller", that);
        };

        //
        Page.prototype.initDragAndDrop = function(vue_model) {
            var that = this;

            var $document = $(document);

            var drag_data = {},
                over_locked = false,
                is_changed = false,
                timer = 0;

            var $wrapper = $(vue_model.$el),
                units = vue_model.units;

            $wrapper.on("dragstart", ".js-unit-move-toggle", function(event) {
                var $move = $(this).closest(".s-unit-wrapper");

                var unit_id = "" + $move.attr("data-id"),
                    unit = getUnit(unit_id);

                if (!unit) {
                    console.error("ERROR: unit isn't exist");
                    return false;
                }

                event.originalEvent.dataTransfer.setDragImage($move[0], 20, 20);

                $.each(units, function(i, _unit) {
                    _unit.states.move = (_unit.id === unit_id);
                });

                drag_data.move_unit = unit;

                $document.on("dragover", ".s-unit-wrapper", onDragOver);
                $document.on("dragend", onDragEnd);
            });

            function onDragOver(event) {
                event.preventDefault();

                if (!drag_data.move_unit) { return false; }

                if (!over_locked) {
                    over_locked = true;
                    moveUnit($(this).closest(".s-unit-wrapper"));
                    setTimeout( function() {
                        over_locked = false;
                    }, 100);
                }
            }

            function onDragEnd() {
                drag_data.move_unit.states.move = false;
                moveRequest(drag_data.move_unit);
                drag_data = {};
                $document.off("dragover", ".s-unit-wrapper", onDragOver);
                $document.off("dragend", onDragEnd);
            }

            function moveUnit($over) {
                var unit_id = "" + $over.attr("data-id"),
                    unit = getUnit(unit_id);

                if (!unit) {
                    console.error("ERROR: unit isn't exist");
                    return false;
                }

                if (drag_data.move_unit === unit) { return false; }

                var move_index = units.indexOf(drag_data.move_unit),
                    over_index = units.indexOf(unit),
                    before = (move_index > over_index);

                if (over_index !== move_index) {
                    units.splice(move_index, 1);

                    over_index = units.indexOf(unit);
                    var new_index = over_index + (before ? 0 : 1);

                    units.splice(new_index, 0, drag_data.move_unit);

                    is_changed = true;
                }
            }

            function moveRequest(unit) {
                var href = that.urls["unit_move"],
                    data = { ids: getActiveUnitsIds() };

                if (is_changed && !unit.states.move_loading) {
                    unit.states.move_loading = true;

                    request(href, data)
                        .always( function() {
                            unit.states.move_loading = false;
                        })
                        .done( function(response) {
                            is_changed = false;
                            if (response.status !== "ok") {
                                alert("Error: unit move");
                                console.log(response.errors);
                            }
                        });

                }

                function request(href, data) {
                    return $.post(href, data, "json");
                }

                function getActiveUnitsIds() {
                    var result = [];

                    $.each(vue_model.active_units, function(i, unit) {
                        result.push(unit.id);
                    });

                    return result;
                }
            }

            //

            function getUnit(unit_id) {
                var result = null;

                if (units) {
                    $.each(units, function(i, unit) {
                        unit.id = (typeof unit.id === "number" ? "" + unit.id : unit.id);
                        if (unit.id === unit_id) {
                            result = unit;
                            return false;
                        }
                    });
                }

                return result;
            }
        };

        Page.prototype.initTooltips = function(tooltips) {
            var that = this;

            $.each(tooltips, function(i, tooltip) {
                new Tooltip(tooltip);
            });
        };

        Page.prototype.showConfirmDialog = function(html) {
            var that = this;

            var deferred = $.Deferred(),
                result = false;

            $.waDialog({
                html: html,
                onOpen: function($dialog, dialog) {
                    $dialog.on("click", ".js-confirm-button", function(event) {
                        event.preventDefault();
                        result = true;
                        dialog.close();
                    });
                },
                onClose: function() {
                    if (result) {
                        deferred.resolve();
                    } else {
                        deferred.reject();
                    }
                }
            });

            return deferred.promise();
        };

        //

        return Page;

        function formatUnit(unit) {
            unit.states = {
                active: (unit.status !== "0"),
                locked: (unit.locked || unit.status === "2"),
                builtin: (unit.builtin !== "0"),
                move: false,
                visible: true,
                highlight: false,
                move_loading: false,
                status_loading: false
            }
            return unit;
        }

    })($);

    window.initShopUnitSettingsPage = function(options) {
        return new ShopUnitSettingsPage(options);
    };

})(jQuery);