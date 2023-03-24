/**
 * @used at wa-apps/shop/templates/actions/settings/SettingsCompatibility.html
 * Controller for Compatibility Settings Page.
 **/
( function($) { "use strict";

    // PAGE

    var ShopCompatibilitySettingsPage = ( function($) {
        // PAGE

        function Page(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$content = that.$wrapper.find(".js-page-content-section");

            // CONST
            that.templates = options["templates"];
            that.tooltips = options["tooltips"];
            that.locales = options["locales"];
            that.urls = options["urls"];
            that.is_premium = options["is_premium"];

            // INIT
            that.init();
        }

        Page.prototype.init = function() {
            var that = this;

            that.initEditDialog();

            that.$wrapper.on("click", ".s-text-wrapper .js-toggle", function(event) {
                event.preventDefault();

                var $toggle = $(this),
                    $text = $toggle.closest(".s-text-wrapper");

                var is_open = $text.hasClass("is-extended");

                $toggle.text((is_open ? that.locales["show"] : that.locales["hide"]));
                $text.toggleClass("is-extended");
            });

            $.each(that.tooltips, function(i, tooltip) {
                new Tooltip(tooltip);
            });

            var $iButtons = that.$wrapper.find(".js-ibutton");
            if ($iButtons.length) {
                $iButtons.each( function() {
                    initField($(this));
                });
            }

            function initField($field) {
                $field.iButton({
                    labelOn: "",
                    labelOff: "",
                    classContainer: "ibutton-container mini"
                });

                $field.on("change", function(event, ignore) {
                    if (ignore) { return false; }

                    var $wrapper = $field.closest(".s-checkbox-wrapper"),
                        $ibutton = $field.closest(".ibutton-container"),
                        $label = $wrapper.find(".js-label"),
                        $loading = $("<i class=\"icon16 loading\" />");

                    var plugin_id = $field.closest(".s-item-row").data("plugin-id"),
                        app_id = $field.closest(".s-item-row").data("app-id") || $field.closest(".s-app-row").data("app-id"),
                        is_checked = $(this).is(":checked");

                    var data = {
                        app_id: app_id,
                        plugin_id: plugin_id,
                        enabled: (is_checked ? "1" : "0")
                    };

                    $ibutton.after($loading);

                    request(data)
                        .always( function() {
                            $loading.remove();
                        })
                        .fail( function(errors) {
                            $field.attr("checked", !is_checked).trigger("change", true);
                            // TODO: display errors
                            alert(errors);
                        })
                        .done( function() {
                            $label.text(is_checked ? that.locales["on"] : that.locales["off"]);
                        });

                    function request(data) {
                        var deferred = $.Deferred();

                        $.post(that.urls["asset_status"], data, "json")
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
                });
            }
        };

        Page.prototype.initEditDialog = function() {
            var that = this;

            that.$wrapper.on("click", ".s-interaction-section .js-interaction-dialog", function(event) {
                var $section = $(this).closest(".s-interaction-section"),
                    $row = $section.closest(".s-item-row"),
                    data = {
                        id: $row.data("plugin-id"),
                        type: $row.data("plugin-type")
                    };

                $.post(that.urls["compatibility_dialog"], data, "json")
                    .done( function(html) {
                        $.waDialog({
                            html: html,
                            onOpen: initDialog,
                            options: Object.assign(data, {
                                plugin_name: $row.find('.s-name-section .s-name a').text(),
                                onSuccess: function(messages) {
                                    $section.find(".frac-mode-text").html(messages.frac_mode_text);
                                    $section.find(".units-mode-text").html(messages.units_mode_text);
                                }
                            })
                        });
                    });
            });

            function initDialog($dialog, dialog) {
                var is_locked = false,
                    $button = $dialog.find(".js-submit-button"),
                    $fields = $dialog.find("input[type='radio']");

                $fields.on("change", function () {
                    $button.removeClass("green").addClass("yellow");
                });

                $dialog.find(".js-help-message a").on("click", function() {
                    dialog.close();
                });

                if (!that.is_premium) {
                    $fields.prop("checked", false).prop("disabled", true);
                    $button.removeClass("green").addClass("gray").prop("disabled", true);
                } else {
                    var $help_message = $dialog.find(".js-help-message");
                    $help_message.html($help_message.html().replace(/%s/gmi, dialog.options.plugin_name));

                    $dialog.on("submit", function (event) {
                        event.preventDefault();

                        if (!is_locked) {
                            is_locked = true;
                            $button.attr("disabled", true);
                            var $loading = $("<i class=\"icon16 loading\" />").appendTo($button);

                            var href = that.urls["compatibility_edit"],
                                $frac_mode_selected = $dialog.find('[name="frac_mode"]:checked'),
                                $units_mode_selected = $dialog.find('[name="units_mode"]:checked'),
                                data = {
                                    plugin_id: dialog.options.id,
                                    plugin_type: dialog.options.type,
                                    frac_mode: $frac_mode_selected.val(),
                                    units_mode: $units_mode_selected.val(),
                                };

                            $.post(href, data, "json")
                                .always(function () {
                                    $loading.remove();
                                    $button.attr("disabled", false);
                                    is_locked = false;
                                })
                                .done(function (response) {
                                    if (response.status === "ok") {
                                        dialog.options.onSuccess({
                                            frac_mode_text: $frac_mode_selected.data('mode-text'),
                                            units_mode_text: $units_mode_selected.data('mode-text')
                                        });
                                        dialog.close();
                                    }
                                });
                        }
                    });
                }
            }
        };

        //

        return Page;

    })($);

    window.initShopCompatibilitySettingsPage = function(options) {
        return new ShopCompatibilitySettingsPage(options);
    };

})(jQuery);