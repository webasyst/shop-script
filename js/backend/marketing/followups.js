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

            var $settings_content = $('#s-settings-content'),
                $form = $('#s-followup-form'),
                $submit_button = $form.find('.js-submit-button'),
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

                        if (confirm(that.locales["confirm_text"])) {
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
                        }
                    }
                });

                initDialog();
            }

            // Dialog with info how to set up cron
            $('#cron-message-link').on("click", function() {
                var $dialog_w = $(that.templates["cron_dialog"]);

                $dialog_w.waDialog({
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

                var $loading = $("<span class=\"s-msg-after-button\"><i class=\"icon16 loading\"></i></span>");

                // Submit
                $submit_button
                    .attr("disabled", true)
                    .parent().append($loading);

                $.post($form.attr('action'), $form.serialize(), "json")
                    .fail( function() {
                        $submit_button.attr("disabled", false);
                        $loading.remove();
                    })
                    .done( function(response) {
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
                    $('<input type="text" name="from" value="">').insertAfter(this).focus();
                } else {
                    $(this).next('input').remove();
                }
            });

            // Controller for sending tests
            function initDialog() {
                var dialog = $('#send-test-dialog');

                // Select row when user clicks on it
                dialog.find('table').on('click', 'tr', function() {
                    var tr = $(this).addClass('selected');
                    tr.siblings('.selected').removeClass('selected');
                    tr.find(':radio').attr('checked', true);
                });

                // Actual send: called when user clicks "send test" button in dialog
                var sendTest = function() {
                    dialog.find('.message').text(that.locales["locale_4"]).removeClass('errormsg');

                    var to_field = dialog.find('input:text').removeClass('error');
                    var to = to_field.val();
                    if (!to) {
                        to_field.addClass('error');
                        return false;
                    }

                    var order_id = dialog.find('input:radio:checked').val();
                    if (!order_id) {
                        dialog.find('.message').addClass('errormsg');
                        return false;
                    }

                    dialog.find('.s-msg-after-button').show();

                    $.post('?module=marketingFollowupsTest', { order_id: order_id, followup_id: followup_id, to: to }, function(r) {
                        dialog.find(':input').attr('disabled', false);
                        dialog.find('.s-msg-after-button').hide();
                        if (r.status !== 'ok' && r.errors) {
                            dialog.find('.message').text(r.errors).addClass('errormsg');
                        } else {
                            dialog.find('.before-send').hide();
                            dialog.find('.after-send').show();
                        }
                    }, 'json');
                };

                dialog.find(':submit').on("click", function(event) {
                    event.preventDefault();
                    sendTest();
                });

                // Show dialog when user clicks "Send test" button in main form
                $send_test_button.on("click", function(event) {
                    event.preventDefault();

                    if (template_modified) {
                        alert(that.locales["locale_1"]);

                    } else {
                        dialog.waDialog({
                            onLoad: function() {
                                dialog.find(':input').attr('disabled', false);
                                dialog.find('.before-send').show();
                                dialog.find('.after-send').hide();
                            }
                        });
                    }
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
                fields.filter(':not([data-transport=' + transport + '])').hide().
                find(':input').attr('disabled', true);
                fields.filter('[data-transport=' + transport + ']').show().
                find(':input').attr('disabled', false);
            }
        };

        return FollowupsPage;

    })($);

    $.shop.marketing.init.initFollowupsPage = function(options) {
        return new FollowupsPage(options);
    };

})(jQuery);