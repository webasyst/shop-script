if (typeof($) != 'undefined') {

    $.extend($.settings = $.settings || {}, {

        /**
         * Init section
         *
         * @param string tail
         */
        searchInit: function() {
            const form = $('#s-settings-form');
            const $rangeInput = $('.js-weight-range');
            const $submitButton = $('.js-form-submit');
            const $submitButtonMessage = $submitButton.find('.js-form-submit-message');
            const $submitMessage = $('.js-submit-message');
            const $toggle = $('.js-toggle-status');
            const $disabledSection = $('.js-smart-disabled');
            const $enabledSection = $('.js-smart-enabled');
            const $searchByPartCheckbox = $('.js-search-by-part-checkbox');
            const $searchByPart = $('.js-search-by-part');
            const $reindexButton = $('.js-reindex');
            const reindexHtml = $('.js-reindex-dialog')[0].outerHTML;
            const formChanged = (isChange = true) => {
              const default_class = "green";
              const active_class = "yellow";

              if (isChange) {
                $submitButton.removeClass(default_class).addClass(active_class);
              } else {
                $submitButton.removeClass(active_class).addClass(default_class);
              }
            }

            const $toggleStatus = $('#s-toggle-status');
            $toggle.waSwitch({
                change(enabled, switcher) {
                    const $oldStatus = switcher.$wrapper.siblings('.gray');
                    $oldStatus.removeClass('gray').siblings('span').addClass('gray');
                    $toggleStatus.val(Number(enabled));
                    if (enabled) {
                        $disabledSection.hide();
                        $enabledSection.fadeIn(200);
                    } else {
                        $disabledSection.fadeIn(200);
                        $enabledSection.hide();
                    }

                    $.post('?module=settings&action=searchSave', { smart: enabled ? '1' : '0' });
                }
            });

            $(':input').on('input', formChanged);

            const isValid = () => {
                const removeErrors = () => {
                    form.find('.state-error').removeClass('state-error');
                    form.find('.state-error-hint').remove();
                };
                removeErrors();

                if ($('.js-search-by-part-checkbox').is(':checked')) {
                    const $by_part = $('input[name="by_part"]');
                    const by_part_value = $by_part.val();
                    if (!String(by_part_value).trim() || isNaN(by_part_value) || parseInt(by_part_value) <= 0) {
                        const invalid_text = $by_part.data('invalid-text');
                        $('<span class="state-error-hint"></span>').text(invalid_text).insertAfter($by_part.parent());
                        $by_part.addClass('state-error');

                        form.one('change', function () { removeErrors(); });
                        return false;
                    }
                }

                return true;
            };
            $submitButton.on('click', function(event) {
                event.preventDefault();
                if (!isValid()) {
                    return;
                };

                $.post(form.attr('action'), form.serialize(), function() {
                    formChanged(false);
                    if ($toggleStatus.val() === '1') {
                        $submitMessage.show().fadeOut(5000);
                    }

                    $submitButtonMessage.removeClass('hidden');

                    setTimeout(() => {
                        $submitButtonMessage.addClass('hidden');
                    }, 2000)
                });
            });

            $searchByPartCheckbox.on('change', function() {
               if ($(this).is(':checked')) {
                   $searchByPart.show().find('input').removeAttr('disabled');
               }  else {
                   $searchByPart.hide().find('input').attr('disabled', 'disabled');
               }
            });

            $('.js-slider-range').waSlider({
                hide: { min: true, max: false },
                limit: { min: 0, max: 100 },
                move: function (values, slider) {
                    const $input = $(`[name="${slider.$wrapper.data('input')}"]`);
                    $input.val(Math.floor(values[1])).trigger('input');
                }
            });

            $rangeInput.each(function() {
                const input_name = $(this).attr('name');
                const val = $(this).val();
                const slider = $(`[data-input="${input_name}"]`).data("slider");

                $(this).closest('.js-range').find('.js-range-weight-value').html(val);
                slider.setValues([0, parseFloat(val)]);
            });

            $rangeInput.on('input', function() {
                $(this).closest('.js-range').find('.js-range-weight-value').html($(this).val());
            });

            $reindexButton.on('click', (event) => {
                event.preventDefault();

                if (this.reindexDialog) {
                    this.reindexDialog.show();
                    return;
                }

                const pull = [];

                this.reindexDialog = $.waDialog({
                    html: reindexHtml,
                    onOpen($dialog, dialog) {
                        const $progressbar = dialog.$block.find('#s-reindex-progressbar');
                        const $report = dialog.$block.find("#s-reindex-report");

                        $progressbar.hide();
                        $report.hide();

                        dialog.resize();

                        const $submitButton = dialog.$block.find('.js-dialog-submit');
                        $submitButton.on('click', function(event) {
                            event.preventDefault();

                            const form = dialog.$block.find('form');

                            $progressbar.show();

                            const url = form.attr('action');
                            let processId;

                            const enableSubmit = () => $submitButton.prop("disabled", false);

                            const cleanup = () => {
                                $.post(url, { processId: processId, cleanup: 1 }, function(r) {
                                    // show statistic
                                    $progressbar.hide();
                                    $report.show();

                                    if (r.report) {
                                        $report.html(r.report);
                                        $report.find('.close').on('click', function(event) {
                                            event.preventDefault();
                                            $report.hide();
                                            // dialog.hide();
                                        });
                                    }
                                    enableSubmit();
                                    dialog.resize();
                                }, 'json');
                            };

                            const step = (delay) => {
                                delay = delay || 2000;
                                let timer_id = setTimeout(function() {
                                    $.post(url, { processId:processId },
                                        function(r) {
                                            if (!r) {
                                                step(3000);
                                            } else if (r && r.ready) {
                                                $progressbar.find('.progressbar .progressbar-inner').css({
                                                    width: '100%'
                                                });

                                                cleanup();
                                            } else if (r && r.error) {
                                                form.find('.state-error').text(r.error);
                                                enableSubmit();
                                            } else {
                                                if (r && r.progress) {
                                                    const progress = parseFloat(r.progress.replace(/,/, '.'));

                                                    $progressbar.find('.progressbar .progressbar-inner').css({
                                                        'width': progress + '%'
                                                    });

                                                    $progressbar.find('.progressbar-text').text(r.progress);
                                                }

                                                if (r && r.warning) {
                                                    $progressbar.append('<i class="fas fa-exclamation-triangle"></i>' + r.warning + '</p>');
                                                }

                                              step();

                                          }
                                      },
                                      'json').fail(function() {
                                        step(3000);
                                    });
                                }, delay);

                                pull.push(timer_id);
                            };

                            $progressbar.find('.progressbar .progressbar-inner').css({  width: '0%' });
                            $progressbar.find('.progressbar-text').text('0%');
                            $.post(url, { processId: processId },
                                function(r) {
                                    if (r && r.processId) {
                                        processId = r.processId;
                                        step(1000);   // invoke Runner
                                        step();         // invoke Messenger
                                        $submitButton.prop("disabled", true);
                                    } else if (r && r.error) {
                                        form.find('state-error').text(r.error);
                                    } else {
                                        form.find('state-error').text('Server error');
                                    }
                                },
                            'json').fail(function() {
                                form.find('state-error').text('Server error');
                            });
                        });
                    },
                    onClose(dialog) {
                        let timer_id = pull.pop();

                        while (timer_id) {
                            clearTimeout(timer_id);
                            timer_id = pull.pop();
                        }

                        dialog.$block.find('#s-reindex-progressbar').hide();
                        dialog.$block.find("#s-reindex-report").hide();

                        dialog.hide();
                        return false;
                    }
                });
            });

        }
    });
}
