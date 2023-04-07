( function($) {

    var ABTesting = ( function($) {

        ABTesting = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];

            // CONST
            that.locales = options["locales"];
            that.urls = options["urls"];

            that.test_id = options["test_id"];
            that.errors = options["errors"];

            // DYNAMIC VARS

            // INIT
            that.init();
        };

        ABTesting.prototype.init = function() {
            var that = this;

            var errors = that.errors;

            var $form = $('#s-reports-abtesting-form');
            var $add_variant_link = $('#add-variant-link');
            var $submit_button = $form.find('input:submit:first');
            var $get_code_button = $('#get-code-button');
            var $smarty_code = $('#smarty-code');

            // Remove old dialogs
            $('body > .dialog').trigger('close').remove();

            // Show validation errors
            var errors_count = 0;
            $.each(errors, function(fld_name, error) {
                errors_count++;
                var $fld = $form.find('[name="'+fld_name+'"]');
                if (!$fld.length) {
                    $fld = $submit_button;
                } else {
                    $fld.addClass('error');
                }
                $fld.parent().append($('<em class="errormsg"></em>').text(error));
            });
            // Highlight empty fields in variants
            if (errors_count > 0) {
                $form.find('[name="new_variants[]"][value=""]').addClass('error');
            }

            // Add option when user clicks on a "New version" link
            $add_variant_link.on("click", function() {
                var $field = $add_variant_link.closest('.fields-group').siblings('.variant-option.template').clone().removeClass('hidden template');
                $field.insertBefore($add_variant_link.closest('.field')).find(':text').attr("required", "required").change();
            });

            // Remove version when user clicks on a delete link
            $('#editable-variants').on('click', '.remove-variant-link', function() {
                var $field = $(this).closest('.field'),
                    variant_count = $field.parent().find("> .variant-option").length;

                if (variant_count > 2) {
                    $(this).closest('.field').remove();
                } else {
                    $.waDialog.alert({
                        title: that.locales["count_error"],
                        button_title: $_("OK"),
                        button_class: 'yellow',
                    });
                }
            });

            // Clear error when user changes something in the form
            ( function() { "use strict";
                var timeout = null;
                var initial_form_state = $form.serialize();
                $form.on('change keyup', ':input', function() {
                    $(this).removeClass('error').siblings('.errormsg').remove();
                    timeout && clearTimeout(timeout);
                    timeout = setTimeout(function() {
                        if (initial_form_state !== $form.serialize()) {
                            $submit_button.show().removeClass('green').addClass('yellow');
                            $get_code_button.removeClass('green').prop('disabled', true);
                            $smarty_code.trigger('close');
                        }
                    }, 300);
                });
            })();

            // When user clicks on 'Get code' button,
            // show smarty code highlighted with ace.js
            ace.config.set("basePath", that.urls["ace_direction"]);
            $get_code_button.on("click", function() {
                if ($get_code_button.prop('disabled')) {
                    return false;
                }
                $.waDialog({
                    $wrapper: $smarty_code,
                    buttons: $('<div><input type="button" class="button cancel" value="'+ that.locales["button_close_text"] +'"></div>'),
                    onOpen: function() {
                        var editor = ace.edit('smarty-code-block');
                        editor.setTheme("ace/theme/eclipse");
                        var session = editor.getSession();
                        session.setMode("ace/mode/css");
                        session.setMode("ace/mode/javascript");
                        session.setMode("ace/mode/smarty");
                        session.setUseWrapMode(true);
                        editor.renderer.setShowGutter(false);
                        editor.setShowPrintMargin(false);
                        editor.setFontSize(13);
                        editor.setHighlightActiveLine(false);
                        editor.setReadOnly(true);

                        $smarty_code.on('click', '.js-copy-code', function() {
                            const $this = $(this)
                                initial_title = $this.data('initial-title')
                                title_copied = $this.data('title-copied');

                            $this.html(`<i class="fas fa-check"></i> ${title_copied}`)
                                .attr('disabled', true)
                                .addClass('green');

                            $.wa.copyToClipboard($('#smarty-code-text').val());

                            setTimeout(() => {
                                $this.text(initial_title)
                                    .removeClass('green')
                                    .attr('disabled', false);
                            }, 1000);
                        });
                    }
                });
                return false;
            });
            $('#smarty-code-block').on("click", function() {
                ace.edit('smarty-code-block').selectAll();
            });

            // Deletion link
            $('#delete-link').on("click", function() {
                var id = $form.find('input[name="id"]').val();
                if (!id) {
                    return;
                }

                $.waDialog.confirm({
                    title: that.locales["delete_confirmation"],
                    success_button_title: $_('Delete'),
                    success_button_class: 'danger',
                    cancel_button_title: $_('Cancel'),
                    cancel_button_class: 'light-gray',
                    onSuccess: function() {
                        var href = that.urls["delete"],
                            data = { "id": id };

                        $.post(href, data, function(response) {
                            if (response.status === "ok") {
                                $.shop.marketing.content.load( that.urls["root"] );
                            } else if (response.errors) {
                                renderErrors(response.errors);
                            }
                        }, "json");
                    }
                });
            });

            // Save when user submits the form
            $form.on("submit", function(event) {
                event.preventDefault();

                var href = that.urls["submit"],
                    data = $form.serializeArray();

                $.post(href, data, function(response) {
                    if (response.status === "ok") {
                        var redirect_uri = that.urls["test"].replace("%id%", response.data.id);
                        $.shop.marketing.content.load(redirect_uri);
                    } else if (response.errors) {
                        renderErrors(response.errors);
                    }
                }, "json");
            });

            function renderErrors(errors) {
                alert("ERRORS");
                console.log(errors);
            }
        };

        return ABTesting;

    })($);

    $.shop.marketing.init.abTestingPage = function(options) {
        return new ABTesting(options);
    };

})(jQuery);
