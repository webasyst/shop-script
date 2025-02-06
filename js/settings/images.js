if (typeof($) != 'undefined') {

    $.extend($.settings = $.settings || {}, {

        /**
         * Init section
         */
        imagesInit: function () {

            const form = $('#s-settings-form');

            const formChanged = function (show_message) {
                $(':submit', form).removeClass('green').addClass('yellow');
                if (show_message) {
                    $('.js-updated-settings-alert', form).show();
                }
            };

            form.off('change', '.s-size-set input.js-radio', onImageSizeClick)
                .on('change', '.s-size-set input.js-radio', onImageSizeClick);

            $('#s-image-filename').on('change', function () {
                $('#s-image-filename-hint').toggle();
            });

            $('#s-add-action').on('click', function(event) {
                event.preventDefault();

                const size_set = $('#s-size-set');

                if (size_set.is(':hidden')) {
                    size_set.show();
                    if ($('#s-saved-size').length) {
                        size_set.before('<br>');
                    }

                    return;
                }

                const last_set = form.find('.s-size-set:last');
                const new_set = last_set.clone();
                new_set.attr('id', null);

                new_set.find('input[type=radio], input[type=text]').each(function () {
                    this.name = this.name.replace(/(\d+)/, function (m) {
                        return parseInt(m[0], 10) + 1;
                    });
                });

                last_set.after(new_set).after('<br>');
            });

            form.off('click', '.s-delete-action').on('click', '.s-delete-action', function(event) {
                event.preventDefault();

                const li = $(this).closest('li');

                if (li.find('span.strike').length) {
                    return;
                }

                form.append('<input type="hidden" name="delete[]" value="' + $(this).attr('data-key') + '">');

                const inner_html = li.html();

                li
                  .html(`<span class="strike gray">${inner_html}</span>`)
                  .append(`<em class="small">${$_('Click “Save” button below to apply this change.')}</em>`);
            });

            $('#s-thumbs_on_demand').unbind('click').bind('click', function() {
                if (this.checked) {
                    $('#s-max-size').slideDown(200);
                } else {
                    $('#s-max-size').slideUp(200);
                }
            });

            $(':input', form).change(function () {
                if ($(this).attr('name') !== 'image_save_original') {
                    formChanged(true);
                } else {
                    formChanged(false);
                }
            });

            form.unbind('submit').bind('submit', function(event) {
                event.preventDefault();

                const self = $(this);

                const $submitButton = self.find("button:submit");

                $submitButton.append('<i class="fas fa-spinner fa-spin"></i>');
                $submitButton.attr("disabled", "disabled");

                $.post(self.attr('action'), self.serialize(), function (html) {
                    formChanged();
                    $('#s-settings-content').html(html);
                });
            });

            const demand_checkbox = $('[name="image_thumbs_on_demand"]');

            if (!demand_checkbox.attr('checked')) {
                $('[name=create_thumbnails]').attr('checked', true).attr('disabled', true);
            } else {
                $('[name=create_thumbnails]').attr('checked', false).attr('disabled', false);
            }
            demand_checkbox.on('click', function() {
                if (!$(this).attr('checked')) {
                    $('[name=create_thumbnails]').attr('checked', true).attr('disabled', true);
                } else {
                    $('[name=create_thumbnails]').attr('checked', false).attr('disabled', false);
                }
            });

            $('#s-regenerate-thumbs').on('click', (event) => {
                event.preventDefault();

                if (this.regenerateDialog) {
                    this.regenerateDialog.show();
                    return;
                }

                const $dialogTemplate = $('#s-regenerate-thumbs-dialog').parent().html();
                const pull = [];

                this.regenerateDialog = $.waDialog({
                    html: $dialogTemplate,
                    onOpen($dialog, dialog) {
                        dialog.$block.find('#s-regenerate-progressbar').hide();
                        dialog.$block.find("#s-regenerate-report").hide();

                        const disabled = dialog.$content.find('input[name=create_thumbnails]').prop('disabled');

                        const $create_thumbnails_checkbox = dialog.$block.find('input[name=create_thumbnails]');
                        const $with_2x_checkbox = dialog.$block.find('input[name=with_2x]');

                        $create_thumbnails_checkbox.prop('disabled', disabled);

                        $create_thumbnails_checkbox.on('change', function () {
                            $with_2x_checkbox.prop('disabled', !$create_thumbnails_checkbox.is(':checked'));
                        });

                        const $regenerateButton = dialog.$block.find('.js-regenerate-button');
                        $regenerateButton.on('click', regenerate);

                        function regenerate(event) {
                            event.preventDefault();

                            dialog.$block.find('.dialog-buttons').hide();

                            //Off global handler, so that image processing is not interrupted
                            $.ajaxSetup({'global': false});

                            const create_thumbnails_input = dialog.$block.find('input[name=create_thumbnails]');
                            const restore_originals_input = dialog.$block.find('input[name=restore_originals]');
                            create_thumbnails_input.prop('disabled', true);
                            restore_originals_input.prop('disabled', true);

                            dialog.$block.find('#s-regenerate-progressbar').show();
                            dialog.$block.find('.progressbar .progressbar-inner').css('width', '0%');
                            dialog.$block.find('.progressbar-text').text('0.000%');
                            dialog.$block.find('.progressbar').show();
                            dialog.$block.find("#s-regenerate-report").hide();

                            dialog.resize();

                            const create_thumbnails = create_thumbnails_input.prop('checked') || '';
                            const restore_originals = restore_originals_input.prop('checked') || '';
                            const form = dialog.$block.find('form');
                            const url = form.attr('action');
                            let processId;

                            const cleanup = function () {
                                $.post(url, {processId: processId, cleanup: 1}, function (r) {
                                    // show statistic
                                    create_thumbnails_input.prop('disabled', false);
                                    restore_originals_input.prop('disabled', false);

                                    form.find('#s-regenerate-progressbar').hide();

                                    const $report = form.find("#s-regenerate-report");
                                    $report.show();

                                    if (r.report) {
                                        $report.html(r.report);
                                        $report.find('.close').on('click', function () {
                                            dialog.hide();
                                        });
                                    }
                                }, 'json');
                            };

                            const step = function (delay) {
                                delay = delay || 2000;
                                let timer_id = setTimeout(function () {
                                    $.post(url, {
                                            processId: processId,
                                            create_thumbnails: create_thumbnails,
                                            restore_originals: restore_originals
                                        },
                                        function (r) {
                                            if (!r) {
                                                step(3000);
                                            } else if (r && r.ready) {
                                                form.find('.progressbar .progressbar-inner').css({
                                                    width: '100%'
                                                });
                                                form.find('.progressbar-text').text('100%');
                                                cleanup();
                                            } else if (r && r.error) {
                                                form.find('.state-error').text(r.error);
                                            } else {
                                                if (r && r.progress) {
                                                    const progress = parseFloat(r.progress.replace(/,/, '.'));
                                                    form.find('.progressbar .progressbar-inner').animate({
                                                        'width': progress + '%'
                                                    });
                                                    form.find('.progressbar-text').text(r.progress);
                                                }

                                                if (r && r.warning) {
                                                    form.find('.progressbar-text').append('<i class="fas fa-exclamation-triangle"></i><p>' + r.warning + '</p>');
                                                }

                                                step();
                                            }
                                        },
                                        'json')
                                    .fail(function () {
                                        step(3000);
                                    });
                                }, delay);
                                pull.push(timer_id);
                            };

                            $.post(url, {create_thumbnails: create_thumbnails, restore_originals: restore_originals},
                                function (r) {
                                    if (r && r.processId) {
                                        processId = r.processId;
                                        step(1000);   // invoke Runner
                                        step();         // invoke Messenger
                                    } else if (r && r.error) {
                                        form.find('state-error').text(r.error);
                                    } else {
                                        form.find('state-error').text('Server error');
                                    }
                                },
                                'json')
                            .fail(function () {
                                form.find('state-error').text('Server error');
                            });
                        }
                    },
                    onClose(dialog) {
                        console.log(dialog);
                        let timer_id = pull.pop();

                        while (timer_id) {
                            clearTimeout(timer_id);
                            timer_id = pull.pop();
                        }

                        dialog.$block.find('#s-regenerate-progressbar').hide();
                        dialog.$block.find("#s-regenerate-report").hide();
                        dialog.hide();

                        return false;
                    }
                });
            });

            function onImageSizeClick(event) {
                const $radio = $(this);
                const $wrapper = $radio.closest('.s-size-set');

                $wrapper.find('.js-size-option').each( function() {
                    const $wrapper = $(this);
                    const $_radio = $wrapper.find('.js-radio');
                    const is_active = ($radio[0] === $_radio[0]);

                    if (is_active) {
                        $wrapper.find('span.star').hide();
                        $wrapper.find('input.js-input').show().attr('disabled', false);

                    } else {
                        $wrapper.find('span.star').show();
                        $wrapper.find('input.js-input').hide().attr('disabled', true);
                    }
                });
            }
        }
    });
} else {
    //
}
/**
 * {/literal}
 */
