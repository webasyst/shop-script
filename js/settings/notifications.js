(function ($) {
    $.extend($.settings = $.settings || {}, {
        notififcationsSetEditor: function(transport) {
            if (transport === 'email') {
                var options = {
                    'prefix': 'n-send-',
                    'id': 'n-email-body',
                    'ace_editor_container': 'n-email-body-container'
                };
            } else {
                var options = {
                    'prefix': 'n-send-',
                    'id': 'n-sms-text',
                    'ace_editor_container': 'n-sms-text-container'
                };
            }
            waEditorAceInit(options);
        },

        notificationsAction: function (tail) {
            if (!tail) {
                $.wa.setHash($('#notifications a:first').attr('href'));
            } else if (tail == 'add') {
                this.notificationsAddAction();
            } else {
                this.notificationsEditAction(tail);
            }

        },
        notificationsAddAction : function() {
            $("#notifications-content").load("?module=settings&action=NotificationsAdd", this.notificationsLoad('add'));
        },

        notificationsEditAction : function(id) {
            $("#notifications-content").load("?module=settings&action=NotificationsEdit&id=" + id, this.notificationsLoad(id));
        },
        initElasticFooter:function() {
            // DOM
            var $window = $(window),
                $wrapper = this.$container,
                $header = $wrapper.find(".js-footer-block"),
                $dummy = false,
                is_set = false;

            var active_class = "is-fixed-to-bottom";

            var header_o, header_w, header_h;

            clear();

            $window.on("scroll", useWatcher);
            $window.on("resize", onResize);

            onScroll();

            function useWatcher() {
                var is_exist = $.contains(document, $header[0]);
                if (is_exist) {
                    onScroll();
                } else {
                    $window.off("scroll", useWatcher);
                }
            }

            function onScroll() {
                var scroll_top = $window.scrollTop(),
                    use_scroll = header_o.top + header_h > scroll_top + $window.height();

                if (use_scroll) {
                    if (!is_set) {
                        is_set = true;
                        $dummy = $("<div />");

                        $dummy.height(header_h).insertAfter($header);

                        $header
                            .css("left", header_o.left - 20)// Because parents are doing padding 20
                            .width(header_w)
                            .addClass(active_class);
                    }

                } else {
                    clear();
                }
            }

            function onResize() {
                clear();
                $window.trigger("scroll");
            }

            function clear() {
                if ($dummy && $dummy.length) {
                    $dummy.remove();
                }
                $dummy = false;

                $header
                    .removeAttr("style")
                    .removeClass(active_class);

                header_o = $header.offset();
                header_w = $header.outerWidth() + 40; // Because parents are doing padding 20
                header_h = $header.outerHeight();

                is_set = false;
            }
        },
        notificationsLoad: function (tail) {
            var that = this;

            return function () {
                var form = $("#notification-form");

                var send_test_button = $('#send-test-button');
                var send_button = $('#n-send-button');
                var form_modified = false;

                $("#notifications li.selected").removeClass('selected');

                if (tail == 'add') {
                    $("#notifications li.small").addClass('selected');

                    var transportHandler = function(item) {
                        $(".transport-content").hide().find('input,select,textarea').attr('disabled', 'disabled');
                        $('#' + item.val() + '-content').show().find('input,select,textarea').removeAttr('disabled', 'disabled');
                        $('#' + item.val() + '-content .body').change();
                       $.settings.notififcationsSetEditor(item.val);
                    };
                    var transport_input = $("#notifications-settings-content input.transport");
                    transport_input.change(function () {
                        transportHandler($(this));
                    });
                    transportHandler(transport_input);

                } else {
                    $("#notification-" + tail).addClass('selected');
                }

                $("#notification-form").submit(function () {
                    var form = $(this);
                    form.find(':submit').prop('disabled', true).parent().append('<span class="s-msg-after-button"><i class="icon16 loading"></i></span>');
                    send_test_button.prop('disabled', true);

                    // find out transport in add and edit mode
                    var transport = form.find('input[name="data[transport]"]:checked').val();
                    if (transport === undefined) {
                        transport = $('#n-email-body').length ? 'email' : 'sms';
                    }

                    var prev_wa_editor = wa_editor;
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

                        if (response.status == 'ok') {
                            var n = response.data;
                            if ($("#notification-" + n.id).length) {
                                $("#notification-" + n.id + ' a').html('<i class="icon16 ' + n.icon + '"></i>' + n.name);
                                form.find(':submit').prop('disabled', false);
                                send_test_button.prop('disabled', false);
                            } else {
                                $('<li id="notification-' + n.id + '">' +
                                '<a href="#/notifications/' + n.id + '/">' +
                                '<i class="icon16 ' + n.icon + '"></i>' + n.name + '</a></li>').insertBefore($("#notifications li.small"));
                                $.wa.setHash('#/notifications/' + n.id + '/');
                            }

                            if (n.status == '0') {
                                $("#notification-" + n.id).addClass('gray');
                            } else {
                                $("#notification-" + n.id).removeClass('gray');
                            }

                            form.find('span.s-msg-after-button')
                                .html('<i class="icon16 yes"></i>'+ $_('Saved') +'</span>')
                                .animate({ opacity: 0 }, 1500, function() {
                                    $(this).remove();
                            });

                            $('#n-send-button').removeClass('yellow').addClass('green');
                            form_modified = false;
                        }

                    }, "json");
                    return false;
                });
                if ($(".notification-to").length) {
                    $(".notification-to").change(function () {
                        if (!$(this).val()) {
                            $('<input type="text" name="to" value="">').insertAfter(this).focus();
                        } else {
                            $(this).next('input').remove();
                        }
                    });
                }
                if ($(".notification-from").length) {
                    $(".notification-from").change(function() {
                        if ($(this).val() === 'other') {
                            $('<input type="text" name="from" value="">').insertAfter(this).focus();
                        } else {
                            $(this).next('input').remove();
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
                    $('#n-email-body').data('wa_editor').on('change', formModified);
                }
                if ($('#n-sms-text').length) {
                    $('#n-sms-text').data('wa_editor').on('change', formModified);
                }

                $('select', form).change(formModified);
                $('input', form).change(formModified).keyup(formModified);
                that.initElasticFooter();

            // Controller for sending tests
            (function() {
                var dialog = $('#send-test-dialog');

                // Select row when user clicks on it
                dialog.find('table').on('click', 'tr', function() {
                    var tr = $(this).addClass('selected');
                    tr.siblings('.selected').removeClass('selected');
                    tr.find(':radio').attr('checked', true);
                });

                dialog.find(':submit').click(function() {
                    sendTest($(this).data('n-id'));
                    return false;
                });

                // Actual send: called when user clicks "send test" button in dialog
                var sendTest = function(id) {
                    dialog.find('.select-order-message').removeClass('errormsg');

                    var to_field = dialog.find('input:text').removeClass('error');
                    var to = to_field.val();
                    if (!to) {
                        to_field.addClass('error');
                        return false;
                    }

                    var order_id = dialog.find('input:radio:checked').val();
                    if (!order_id) {
                        dialog.find('.select-order-message').addClass('errormsg');
                        return false;
                    }

                    dialog.find(':input').attr('disabled', true);
                    dialog.find('.s-msg-after-button').show();
                    $.post("?module=settings&action=notificationsTest&id="+id, {
                        order_id: order_id,
                        to: to
                    }, function(response) {
                        dialog.find(':input').attr('disabled', false);
                        dialog.find('.s-msg-after-button').hide();
                        dialog.find('.before-send').hide();
                        dialog.find('.after-send').show();
                        if(response.status ==='ok'){
                            dialog.find('.after-send .errormsg').hide();
                            dialog.find('.after-send .successmsg').show();
                        } else {
                            var error = dialog.find('.after-send .errormsg');
                            error.text(response.errors);
                            error.show();
                            dialog.find('.after-send .successmsg').hide();
                        }

                    });

                    return false;
                };

                // Show dialog when user clicks "Send test" button in main form
                send_test_button.click(function() {
                    if (form_modified) {
                        alert($_("Please save changes to be able to send tests."));
                        return false;
                    }
                    dialog.waDialog({
                        onLoad: function() {
                            dialog.find(':input').attr('disabled', false);
                            dialog.find('.before-send').show();
                            dialog.find('.after-send').hide();
                        }
                    });
                    return false;
                });


            })();

            };
        }
    });
})(jQuery);