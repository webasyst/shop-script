(function ($) {
    $.extend($.settings = $.settings || {}, {
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

        notificationsLoad: function (tail) {
            return function () {
                $("#notifications li.selected").removeClass('selected');
                if (tail == 'add') {
                    $("#notifications li.small").addClass('selected');
                    $("#notifications-settings-content input.transport").change(function () {
                        $(".transport-content").hide().find('input,select,textarea').attr('disabled', 'disabled');
                        $('#' + $(this).val() + '-content').show().find('input,select,textarea').removeAttr('disabled', 'disabled');
                        $('#' + $(this).val() + '-content .body').change();
                    });
                } else {
                    $("#notification-" + tail).addClass('selected');
                }
                $("#notification-form").submit(function () {
                    var form = $(this);
                    form.find(':submit').after('<span class="s-mgs-after-button"><i class="icon16 loading"></i></span>');
                    $.post(form.attr('action'), form.serialize(), function (response) {
                        if (response.status == 'ok') {
                            var n = response.data
                            if ($("#notification-" + n.id).length) {
                                $("#notification-" + n.id + ' a').html('<i class="icon16 ' + n.icon + '"></i>' + n.name);
                            } else {
                                $('<li id="notification-' + n.id + '">' +
                                '<a href="#/notifications/' + n.id + '/">' +
                                '<i class="icon16 ' + n.icon + '"></i>' + n.name + '</a></li>').insertBefore($("#notifications li.small"));
                                $.wa.setHash('#/notifications/' + n.id + '/');
                            }
                            form.find('span.s-mgs-after-button')
                                .html('<i class="icon16 yes"></i>'+ $_('Saved') +'</span>')
                                .animate({ opacity: 0 }, 1500, function() {
                                    $(this).remove();
                            });
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
            }
        }
    });
})(jQuery);