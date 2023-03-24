( function($) {

    var FollowupsPage = ( function($) {

        FollowupsPage = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];

            // CONST
            that.templates = options["templates"];
            that.locales = options["locales"];
            that.urls = options["urls"];

            that.cron_enabled = options["cron_enabled"];
            that.followup_id = options["followup_id"];
            that.transport = options["transport"];

            // DYNAMIC VARS

            // INIT
            that.init();
        };

        FollowupsPage.prototype.init = function() {
            var that = this;

            var $form = $('#s-followup-form'),
                $submit_button = that.$wrapper.find('.js-submit-button'),
                $send_test_button = $('#send-test-button');

            var template_modified = false;

            var followup_id = that.followup_id;

            if (that.followup_id) {
                // Link to delete rule
                var is_delete_locked = false;
                $("#s-delete-followup-link").on("click", function(event) {
                    event.preventDefault();

                    if (!is_delete_locked) {
                        is_delete_locked = true;

                        $.waDialog.confirm({
                            title: that.locales["confirm_text"],
                            success_button_title: $_('Delete'),
                            success_button_class: 'danger',
                            cancel_button_title: $_('Cancel'),
                            cancel_button_class: 'light-gray',
                            onSuccess: function() {
                                var data = {
                                    id: that.followup_id
                                };

                                $.post(that.urls["delete"], data, "json")
                                    .fail( function () {
                                        is_delete_locked = false;
                                    })
                                    .done( function () {
                                        $.shop.marketing.content.load( that.urls["dir_url"] );
                                    });
                            },
                            onCancel: function() {
                                is_delete_locked = false;
                            }
                        });
                    }
                });

                initDialog();
            }

            // Dialog with info how to set up cron
            $('#cron-message-link').on("click", function() {
                const $dialog_w = $(that.templates["cron_dialog"]);

                $.waDialog({
                    $wrapper: $dialog_w,
                    'height': '200px',
                    'width': '400px',
                    'buttons': $('<button class="button"></button>').text(that.locales["locale_3"]).on("click", function(event) {
                        event.preventDefault();
                        $(this).closest(".dialog ").remove();
                    })
                });
            });

            $form
                .on("keyup", formModified)
                .on("change", formModified);

            $form.find('[name="followup[transport]"]').on("change", function() {
                onChangeTransport( $(this).val() );
            });

            onChangeTransport( getTransport() );

            // Form submission via XHR
            $form.on("submit", function(event) {
                event.preventDefault();

                var transport = getTransport();

                if (window.wa_editor) {
                    var prev_editor = window.wa_editor;

                    window.wa_editor = getEditor(transport);

                    waEditorUpdateSource({
                        'id': 'f-' + transport + '-body'
                    });
                }

                window.wa_editor = prev_editor;

                var $loading = $('<span class="s-msg-after-button"><i class="fas fa-spinner fa-spin"></i></span>');

                // Submit
                $submit_button.removeClass('yellow').addClass('green');
                $submit_button.attr("disabled", true).append($loading);

                $.post($form.attr('action'), $form.serialize(), "json")
                    .fail( function() {
                        $submit_button.attr("disabled", false);
                        $loading.remove();
                    })
                    .done( function(response) {
                        $loading
                            .html('<i class="fas fa-check-circle"></i>')
                            .animate({ opacity: 0 }, 1500, function() {
                                $(this).remove();
                            });
                        if (response.status === "ok") {
                            if (response.data.id) {
                                var redirect_uri = that.urls["id_page"].replace("%id%", response.data.id);
                                $.shop.marketing.content.load(redirect_uri);
                            } else {
                                $.shop.marketing.content.reload();
                            }
                        }

                    });
            });

            that.$wrapper.on("change", ".followup-from", function() {
                if ($(this).val() === 'other') {
                    $('<input type="text" class="small" name="from" value="">').insertAfter($(this).parent()).focus();
                } else {
                    $(this).parent().next('input').remove();
                }
            });

            // Controller for sending tests
            function initDialog() {
                // Show dialog when user clicks "Send test" button in main form
                $send_test_button.on("click", function(e) {
                    e.preventDefault();

                    if (template_modified) {
                        $.waDialog.alert({
                            title: that.locales["locale_1"],
                            button_title: $_("Close"),
                            button_class: 'light-gray',
                        });
                        return;
                    }

                    $.waDialog({
                        html: $('#send-test-dialog').clone()[0],
                        onOpen: function($dialog) {
                            // Select row when user clicks on it
                            $dialog.find('table').on('click', 'tr', function() {
                                var tr = $(this).addClass('selected');
                                tr.siblings('.selected').removeClass('selected');
                                tr.find(':radio').prop('checked', true);
                            });

                            // Actual send: called when user clicks "send test" button in dialog
                            var sendTest = function() {
                                var message = $dialog.find('.message');
                                message.text(that.locales["locale_4"]).removeClass('state-error').hide();

                                var to_field = $dialog.find('input:text').removeClass('state-error');
                                var to = to_field.val();
                                if (!to) {
                                    to_field.addClass('state-error');
                                    to_field.focus();
                                    $dialog.scrollTop(to_field.position().top);
                                    return;
                                }

                                var order_id = $dialog.find('input:radio:checked').val();
                                if (!order_id) {
                                    message.addClass('state-error').show();
                                    $dialog.scrollTop(message.position().top);
                                    return;
                                }

                                var $inputs = $dialog.find(':input').attr('disabled', true);
                                var $msg_after_button = $dialog.find('.s-msg-after-button').show();

                                $.post('?module=marketingFollowupsTest', { order_id: order_id, followup_id: followup_id, to: to }, function(r) {
                                    $inputs.attr('disabled', false);
                                    $msg_after_button.hide();
                                    $dialog.find('.before-send').hide();

                                    var $after_send = $dialog.find('.after-send').show();
                                    if (r.status !== 'ok' && r.errors) {
                                        var $state_error = $after_send.find('.state-error').show();
                                        $state_error.find('.error').text(r.errors);
                                    } else {
                                        $after_send.find('.state-success').show();
                                    }
                                }, 'json');
                            };

                            $dialog.find('.js-submit-button').on("click", function(event) {
                                event.preventDefault();
                                sendTest();
                            });
                            $dialog.find('.after-send').hide();
                            $dialog.find('.before-send').show();
                            $dialog.find(':input').attr('disabled', false);
                        }
                    });
                });
            }

            function setEditor(transport) {
                var el = $('#f-' + transport + '-body');
                if (!el.data('wa_editor')) {
                    wa_url = that.urls["root"];
                    waEditorAceInit({
                        'prefix': 'f-' + transport + '-',
                        'id': 'f-' + transport + '-body',
                        'ace_editor_container': 'f-' + transport + '-body-container'
                    });
                    el.data('wa_editor', wa_editor);
                    wa_editor.on('change', formModified);
                }
                wa_editor = el.data('wa_editor');
                return el;
            }

            function getEditor(transport) {
                var el = $('#f-' + transport + '-body');
                if (!el.data('wa_editor')) {
                    el = setEditor(transport);
                }
                return el.data('wa_editor');
            }

            function getTransport() {
                var result = "";

                if (that.followup_id && that.transport) {
                    result = that.transport;

                } else {
                    result = $form.find('[name="followup[transport]"]:checked').val()
                }

                return result;
            }

            // Disallow sending tests when email template is modified
            function formModified() {
                if (!template_modified) {
                    template_modified = true;
                    $send_test_button.removeClass('blue');
                    $submit_button.removeClass('green').addClass('yellow');
                }
            }

            function onChangeTransport(transport) {
                setEditor(transport);
                var fields = $form.find('.f-transport-fields');
                fields.filter(':not([data-transport=' + transport + '])').addClass('hidden').
                find(':input').attr('disabled', true);
                fields.filter('[data-transport=' + transport + ']').removeClass('hidden').
                find(':input').attr('disabled', false);
            }
        };

        return FollowupsPage;

    })($);

    $.shop.marketing.init.initFollowupsPage = function(options) {
        return new FollowupsPage(options);
    };

})(jQuery);
