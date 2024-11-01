(function ($) {
    $.extend($.settings = $.settings || {}, {
        notificationsAction: function (tail) {
            if (!tail) {
                const firstNotify = $('#notifications a:first');
                if (firstNotify.length) {
                    $.wa.setHash(firstNotify.attr('href'));
                } else {
                    $.wa.setHash('#/notifications/add/');
                }
            } else if (tail === 'add') {
                this.notificationsAddAction();
            } else {
                if ($("#notification-" + tail).length) {
                    this.notificationsEditAction(tail);
                } else {
                    this.notificationsAddAction();
                }
            }

        },
        notificationsAddAction : function() {
            $("#notifications-content").load("?module=settings&action=NotificationsAdd", this.notificationsLoad('add'));
        },

        notificationsEditAction : function(id) {
            $("#notifications-content").load("?module=settings&action=NotificationsEdit&id=" + id, this.notificationsLoad(id));
        },
        notificationsLoad: function (tail) {
            return function () {
                const form = $("#notification-form");

                const send_test_button = $('#send-test-button');
                const send_button = $('#n-send-button');
                let form_modified = false;

                $("#notifications li.selected").removeClass('selected');

                if (tail == 'add') {
                    $("#notifications li.small").addClass('selected');

                    const transportHandler = function(item) {
                        $(".transport-content").hide().find('input,select,textarea').attr('disabled', 'disabled');
                        $('#' + item.val() + '-content').show().find('input,select,textarea').removeAttr('disabled', 'disabled');
                        $('#' + item.val() + '-content .body').change();
                    };

                    const transport_input = $("#notifications-settings-content input.transport");
                    transport_input.on('change', function () {
                        transportHandler($(this));
                    });
                    transportHandler(transport_input);

                } else {
                    $("#notification-" + tail).addClass('selected');
                }

                $("#notification-form").on('submit', function(event) {
                    event.preventDefault();

                    const form = $(this);
                    form.find('#n-send-button').prop('disabled', true).append('<span class="s-msg-after-button"><i class="fas fa-spinner fa-spin"></i></span>');

                    // find out transport in add and edit mode
                    let transport = form.find('input[name="data[transport]"]:checked').val();
                    if (transport === undefined) {
                        transport = $('#n-email-body').length ? 'email' : 'sms';
                    }

                    const prev_wa_editor = wa_editor;
                    if (transport === 'email') {
                        wa_editor = $('#n-email-body').data('wa_editor');
                        waEditorUpdateSource({
                            'id': 'n-email-body'
                        });
                    } else {
                        wa_editor = $('#n-sms-text').data('wa_editor');
                        waEditorUpdateSource({
                            'id': 'n-sms-text'
                        });
                    }
                    wa_editor = prev_wa_editor;

                    $.post(form.attr('action'), form.serialize(), function (response) {
                        if (response.status !== 'ok') {
                            console.warn(response);
                            return;
                        }

                        const n = response.data;
                        const $notification = $("#notification-" + n.id);

                        if ($notification.length) {
                            $notification.find('a').html( '<span class="icon">' + $.icon_convert[n.icon] + '</span><span class="name">' + n.name + ' </span>');
                            form.find(':submit').prop('disabled', false);
                        } else {
                            const $li = $('<li class="rounded" id="notification-' + n.id + '">' +
                                            '<a href="#/notifications/' + n.id + '/">' +
                                            '<span class="icon">' + $.icon_convert[n.icon] + '</span><span class="name">' + n.name + ' </span></a></li>');

                            $("#notifications").append($li);

                            $.wa.setHash('#/notifications/' + n.id + '/');
                        }

                        if (n.status == '0') {
                            $notification.find('.icon').addClass('opacity-50');
                            $notification.find('.name').addClass('gray');
                        } else {
                            $notification.find('span').removeClass('opacity-50');
                            $notification.find('.name').removeClass('gray');
                        }

                        form.find('span.s-msg-after-button')
                            .html('<i class="fas fa-check-circle"></i></span>')
                            .animate({ opacity: 0 }, 1500, function() {
                                $(this).remove();
                        });

                        $('#n-send-button').removeClass('yellow').addClass('green');
                        form_modified = false;

                    }, "json");
                });

                if ($(".notification-to").length) {
                    $(".notification-to > select").on('change', function () {
                        let parent = $(this).parent();

                        if (!$(this).val()) {
                            $('<input type="text" name="to" class="small" value="">').insertAfter(parent).focus();
                        } else {
                            parent.next('input').remove();
                        }
                    });
                }

                if ($(".notification-from").length) {
                    $(".notification-from > select").on('change', function() {
                        let parent = $(this).parent();

                        if ($(this).val() === 'other') {
                            $('<input type="text" name="from" class="small" value="">').insertAfter(parent).focus();
                        } else {
                            parent.next('input').remove();
                        }
                    });
                }

                // Disallow sending tests when email template is modified
                var formModified = function() {
                    if (!form_modified) {
                        form_modified = true;
                        send_button.removeClass('green').addClass('yellow');
                    }
                };

                if ($('#n-email-body').length) {
                    $('#n-email-body').on('change', formModified);
                }

                if ($('#n-sms-text').length) {
                    $('#n-sms-text').on('change', formModified);
                }

                $('textarea', form).on('input', formModified);
                $('select', form).change(formModified);
                $('input', form).change(formModified).keyup(formModified);

                // Controller for sending tests
                (function() {
                    // Show dialog when user clicks "Send test" button in main form
                    send_test_button.on('click', function(event) {
                        event.preventDefault();

                        if (form_modified) {
                            $.waDialog.alert({
                                title: $_("Please save changes to be able to send tests."),
                                button_title: $_("Close"),
                                button_class: 'light-gray',
                            });

                            return;
                        }

                        let dialog;

                        if (dialog) {
                            dialog.show();
                            return;
                        }

                        dialog = $.waDialog({
                            html: $('#send-test-dialog')[0].outerHTML,
                            onOpen($dialog, dialog) {
                                dialog.$block.find(':input').attr('disabled', false);
                                dialog.$block.find('.before-send').show();
                                dialog.$block.find('.after-send').hide();

                                // Select row when user clicks on it
                                dialog.$block.find('table').on('click', 'tr', function() {
                                    $(this).addClass('selected').siblings().removeClass('selected');
                                    $(this).find(':radio').prop('checked', true);
                                });

                                dialog.$block.find(':submit').on('click', function(e) {
                                    e.preventDefault();
                                    sendTest($(this).data('n-id'));
                                });

                                // Actual send: called when user clicks "send test" button in dialog
                                function sendTest(id) {
                                    dialog.$block.find('.select-order-message').removeClass('state-error');

                                    const to_field = dialog.$block.find('input:text').removeClass('state-error');
                                    const to = to_field.val();
                                    if (!to) {
                                        to_field.addClass('state-error');
                                        to_field.focus();
                                        $dialog.scrollTop(to_field.position().top);
                                        return;
                                    }

                                    const order_id = dialog.$block.find('input:radio:checked').val();
                                    if (!order_id) {
                                        const order_message = dialog.$block.find('.select-order-message').addClass('state-error');
                                        $dialog.scrollTop(order_message.position().top);
                                        return;
                                    }

                                    dialog.$block.find(':input').attr('disabled', true);
                                    dialog.$block.find('.s-msg-after-button').show();

                                    $.post("?module=settings&action=notificationsTest&id="+id, {
                                        order_id: order_id,
                                        to: to
                                    }, function(response) {
                                        dialog.$block.find(':input').attr('disabled', false);
                                        dialog.$block.find('.s-msg-after-button').hide();

                                        // for test the html
                                        if (response && response.status === 'test') {
                                            const w = window.open();
                                            w.document.open();
                                            w.document.write(response.html);
                                            return;
                                        }

                                        dialog.$block.find('.before-send').hide();
                                        dialog.$block.find('.after-send').show();
                                        if (response.status ==='ok') {
                                            dialog.$block.find('.after-send .state-error').hide();
                                            dialog.$block.find('.after-send .state-success').show();
                                        } else {
                                            dialog.$block.find('.after-send .state-success').hide();
                                            const error = dialog.$block.find('.after-send .state-error');
                                            error.find('.error').text(response.errors);
                                            error.show();
                                        }
                                    });
                                };
                            },
                            onClose(dialog) {
                                dialog.hide();
                                return false;
                            }
                        });
                    });
                })();

                // Filtering notifications
                $('#toggle-notifications-by-transport').waToggle({
                    change: function (e, target) {
                        const transport = $(target).data('id');
                        const $notifications = $('ul#notifications li');

                        $notifications.hide();
                        if (transport) {
                            $notifications.filter(`[data-transport="${transport}"]`).show();
                        } else {
                            $notifications.show();
                        }
                    }
                });

            };
        }
    });
})(jQuery);
