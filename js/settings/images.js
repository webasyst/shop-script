if (typeof($) != 'undefined') {

    $.extend($.settings = $.settings || {}, {

        /**
         * Init section
         *
         * @param string tail
         */
        imagesInit: function() {
            var form = $('#s-settings-form');

            var submit_message = $('#submit-message');
            var formChanged = function(show_message) {
                show_message = typeof show_message === 'undefined' ? true : show_message;
                $(':submit', form).removeClass('green').addClass('yellow');
                if (show_message) {
                    submit_message.show();
                }
            };
            var resetFormChanged = function() {
                $(':submit', form).removeClass('yellow').addClass('green');
                submit_message.hide();
            };

            form.off('click', '.s-size-set label').
                on('click', '.s-size-set label', function(e) {
                    var self = $(this);
                    var target = self.find('input:radio:first');
                    var parent = self.parents('.s-size-set:first');
                    var prev = parent.find('input:radio:checked');

                    prev.nextAll().filter('span.star').show().end().
                        filter('input').hide().
                        attr('disabled', true);
                    prev.attr('checked', false);

                    target.nextAll().filter('span.star').hide().end().
                        filter('input').show().
                        attr('disabled', false);
                    target.attr('checked', true);

                    return false;
                }
            );

            $('#s-image-filename').on('change', function () {
                $('#s-image-filename-hint').toggle();
            });

            $('#s-add-action').click(function() {
                var size_set = $('#s-size-set');
                if (size_set.is(':hidden')) {
                    size_set.show()
                    if ($('#s-saved-size').length) {
                        size_set.before('<br>');
                    }
                    return false;
                }
                var last_set = form.find('.s-size-set:last');
                var new_set = last_set.clone();
                new_set.attr('id', null);

                new_set.find('input[type=radio], input[type=text]').each(function() {
                    this.name = this.name.replace(/(\d+)/, function(m) {
                        return parseInt(m[0], 10) + 1;
                    });
                });
                last_set.after(new_set).after('<br>');
                return false;
            });

            form.off('click', '.s-delete-action').
                on('click', '.s-delete-action', function() {
                    var self = $(this),
                        li = self.parents('li:first');
                    if (li.find('span.strike').length) {
                        return;
                    }
                    form.append('<input type="hidden" name="delete[]" value="' + self.attr('data-key') + '">');

                    var inner_html = li.html();
                    li.html('<span class="strike gray">'+inner_html+'</span>').append('<em class="small">'+$_('Click “Save” button below to apply this change.')+'</em>');
                }
            );

            $('#s-thumbs_on_demand').unbind('click').bind('click', function() {
                if (this.checked) {
                    $('#s-max-size').slideDown(200);
                } else {
                    $('#s-max-size').slideUp(200);
                }
            });

            $(':input', form).change(function() {
                if ($(this).attr('name') !== 'image_save_original') {
                    formChanged();
                } else {
                    formChanged(false);
                }
            });

            form.unbind('submit').bind('submit', function() {
                var self = $(this);
                $.post(self.attr('action'), self.serialize(), function(html) {
                    formChanged();
                    $('#s-settings-content').html(html);
                });
                return false;
            });

            if (!$('[name="image_thumbs_on_demand"]').attr('checked')) {
                $('[name=create_thumbnails]').attr('checked', true).attr('disabled', true);
            } else {
                $('[name=create_thumbnails]').attr('checked', false).attr('disabled', false);
            }
            $('[name="image_thumbs_on_demand"]').click(function() {
                if (!$(this).attr('checked')) {
                    $('[name=create_thumbnails]').attr('checked', true).attr('disabled', true);
                } else {
                    $('[name=create_thumbnails]').attr('checked', false).attr('disabled', false);
                }
            });

            $('#s-regenerate-thumbs').click(function() {
                var dialog = $('#s-regenerate-thumbs-dialog');
                var pull = [];
                var disabled = dialog.find('input[name=create_thumbnails]').prop('disabled');
                dialog.waDialog({
                    onLoad: function() {
                        var d = $(this);
                        $('#s-regenerate-progressbar').hide();
                        $("#s-regenerate-report").hide();
                        d.find('.dialog-buttons').show();
                        dialog.find('input[name=create_thumbnails]').prop('disabled', disabled);
                    },
                    onSubmit: function(d) {
                        d.find('.dialog-buttons').hide();

                        var create_thumbnails_input = dialog.find('input[name=create_thumbnails]');
                        var restore_originals_input = dialog.find('input[name=restore_originals]');
                        create_thumbnails_input.prop('disabled', true);
                        restore_originals_input.prop('disabled', true);

                        var form = $(this);

                        $('#s-regenerate-progressbar').show();
                        form.find('.progressbar .progressbar-inner').css('width', '0%');
                        form.find('.progressbar-description').text('0.000%');
                        form.find('.progressbar').show();
                        $("#s-regenerate-report").hide();

                        var create_thumbnails = create_thumbnails_input.prop('checked') || '';
                        var restore_originals = restore_originals_input.prop('checked') || '';
                        var url = form.attr('action');
                        var processId;

                        var cleanup = function() {
                            $.post(url, { processId: processId, cleanup: 1 }, function(r) {
                                // show statistic
                                create_thumbnails_input.prop('disabled', false);
                                restore_originals_input.prop('disabled', false);
                                $('#s-regenerate-progressbar').hide();
                                $("#s-regenerate-report").show();
                                if (r.report) {
                                    $("#s-regenerate-report").html(r.report);
                                    $("#s-regenerate-report").find('.close').click(function() {
                                        dialog.trigger('close');
                                    });
                                }
                            }, 'json');
                        };

                        var step = function(delay) {
                            delay = delay || 2000;
                            var timer_id = setTimeout(function() {
                                $.post(url, { processId:processId, create_thumbnails: create_thumbnails, restore_originals: restore_originals },
                                    function(r) {
                                        if (!r) {
                                            step(3000);
                                        } else if (r && r.ready) {
                                            form.find('.progressbar .progressbar-inner').css({
                                                width: '100%'
                                            });
                                            form.find('.progressbar-description').text('100%');
                                            cleanup();
                                        } else if (r && r.error) {
                                            form.find('.errormsg').text(r.error);
                                        } else {
                                            if (r && r.progress) {
                                                var progress = parseFloat(r.progress.replace(/,/, '.'));
                                                form.find('.progressbar .progressbar-inner').animate({
                                                    'width': progress + '%'
                                                });
                                                form.find('.progressbar-description').text(r.progress);
                                            }
                                            if (r && r.warning) {
                                                form.find('.progressbar-description').append('<i class="icon16 exclamation"></i><p>' + r.warning + '</p>');
                                            }

                                            step();

                                        }
                                    },
                                'json').error(function() {
                                    step(3000);
                                });
                            }, delay);
                            pull.push(timer_id);
                        };

                        $.post(url, { create_thumbnails: create_thumbnails, restore_originals: restore_originals },
                            function(r) {
                                if (r && r.processId) {
                                    processId = r.processId;
                                    step(1000);   // invoke Runner
                                    step();         // invoke Messenger
                                } else if (r && r.error) {
                                    form.find('errormsg').text(r.error);
                                } else {
                                    form.find('errormsg').text('Server error');
                                }
                            },
                        'json').error(function() {
                            form.find('errormsg').text('Server error');
                        });

                        return false;
                    },
                    onClose: function() {
                        var timer_id = pull.pop();
                        while (timer_id) {
                            clearTimeout(timer_id);
                            timer_id = pull.pop();
                        }
                        $('#s-regenerate-progressbar').hide();
                        $("#s-regenerate-report").hide();
                    }
                });
                return false;
            });

        }
    });
} else {
    //
}
/**
 * {/literal}
 */
