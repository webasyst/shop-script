/**
 *
 * @names currencies*
 * @method currenciesInit
 * @method currenciesAction
 * @method currenciesBlur
 */
$.extend($.settings = $.settings || {}, {

    currenciesInit: function(options) {
        this.currencies_options = options;
        const content = $('#s-settings-currencies');
        const form = content.find('form');
        const $submitButton = form.find('.js-submit-button');
        const change_dialog = $('#s-settings-change-primary-currency-dialog');

        const table = content.find('table.s-settings-currencies');
        const sortable_context = table.find('tbody:first');

        sortable_context.sortable({
            group: 'currencies-rows',
            handle: '.js-sort',
            animation: 100,
            removeCloneOnHide: true,
            onMove: function (evt) {
                const $dragged = $(evt.dragged);
                const $related = $(evt.related);
                if ($dragged.attr('data-code') == options.primary || $related.attr('data-code') == options.primary) {
                    return false;
                }
            },
            onUpdate: function (evt) {
                const $item = $(evt.item);
                const item_code = $item.attr('data-code');

                const next = $item.next();
                const next_code = next.attr('data-code');

                jsonPost('?module=settings&action=currencyMove', { item: item_code, before: next_code },
                    function () {
                        const select = change_dialog.find('select');

                        if (next.length) {
                            select.find('option[value=' + next_code + ']').before(select.find('option[value=' + item_code + ']'));
                        } else {
                            select.append(select.find('option[value=' + item_code + ']'));
                        }
                    },
                    function () {
                        return false;
                    }
                );
            }
        });

        const currencySave = function($tr) {
            const $btn = $tr.find('button.save .icon')
            const change_class = 'text-yellow';
            const default_class = 'text-blue';

            return {
                modify: function() {
                     $btn.removeClass(default_class).addClass(change_class);
                },
                default: function() {
                    $btn.removeClass(change_class).addClass(default_class);
                }
            }
        }

        table.on('input', ':input', function() {
            currencySave($(this).closest('tr')).modify();
        });

        const formChanged = () => $submitButton.removeClass('green').addClass('yellow');
        form.on('input', ':input', formChanged);

        const jsonPost = function(url, data, success, error) {
            const default_error_handler = function(r) {
                if (console) {
                    if (r && r.errors) {
                        console.error(r.errors);
                    } else if (r && r.responseText) {
                        console.error(r.responseText);
                    } else if (r) {
                        console.error(r);
                    } else {
                        console.error('Error when posting');
                    }
                }
            };
            const xhr = $.post(url, data,
                function(r) {
                    if (r.status !== 'ok') {
                        if (typeof error === 'function') {
                            if (error(r) !== false) {
                                default_error_handler(r);
                            }
                        } else {
                            default_error_handler(r);
                        }

                        return;
                    }

                    if (typeof success === 'function') {
                      success(r);
                    }
                }, 'json');

            if (typeof error === 'function') {
                xhr.fail(function(r) {
                    if (error(r) !== false) {
                        default_error_handler(r);
                    }
                });
            } else {
                xhr.fail(default_error_handler);
            }
        };

        table.off('click', '.delete').on('click', '.delete', function(event) {
            event.preventDefault();

            const $this = $(this);
            $this.attr('disabled', true);
            $this.find('.js-delete-trash').hide();
            $this.find('.js-delete-spinner').show();
            const tr = $(this).parents('tr:first');
            const code = tr.attr('data-code');

            $.get('?module=dialog&action=currencyDelete&code='+code, function(html) {
                $this.attr('disabled', false);
                $this.find('.js-delete-trash').show();
                $this.find('.js-delete-spinner').hide();

                $.waDialog({
                    html,
                    onOpen($dialog, dialog) {
                        const form = dialog.$block.find('form');
                        const $deleteButton = dialog.$block.find('.js-confirm-delete');

                        $deleteButton.on('click', function(event) {
                            event.preventDefault();
                            $deleteButton.attr('disabled', true);
                            $deleteButton.append('<i class="fas fa-spinner fa-spin custom-ml-4"></i>');

                            jsonPost(form.attr('action')+'&code='+code, form.serialize(), function() {
                                $.settings.dispatch('#/currencies/', true);
                                dialog.close();
                            }, function() {
                                dialog.close();
                            });
                        });
                    }
                });
            });
        });

        const $changeCurrencyButton = $('.js-settings-change-primary-currency');
        $changeCurrencyButton.on('click', (event) => {
            event.preventDefault();

            this.changeCurrencyDialog = $.waDialog({
                html: change_dialog[0].outerHTML,
                onOpen($dialog, dialog) {
                    dialog.$block.find('select').change(function() {
                        const self = $(this).find('option:selected');
                        const code = self.val();
                        const rate = '1 ' + code + ' = ' + self.attr('data-rate') + ' ' + options.primary;
                        const text = options.convert_text.replace('%s', code).replace('%s', rate);

                        dialog.$block.find('.s-convert-text').text(text);
                    }).trigger('change');

                    const $form = dialog.$block.find('form');
                    $form.on('submit', function(event) {
                        event.preventDefault();

                        const loading = $form.find('.loading').show();

                        jsonPost($form.attr('action'), $form.serialize(),
                          function() {
                              $.settings.dispatch('#/currencies/', true);
                              loading.hide();
                              dialog.close();
                          }
                        );
                    });
                }
            });
        });

        table.find('select.add-new-currency').change(function() {
            const self = $(this);
            const $spinner = self.closest('.wa-select').next('.js-new-currency-spinner');
            const value = self.val();

            $spinner.show();

            if (value === '0') {
                return;
            }

            const old_tr = table.find('tr[data-code='+value+']');
            old_tr.remove();

            const option = self.find('option[value='+value+']');
            option.attr('disabled', true).hide();
            self.val(self.find('option:first').val());

            const tr = self.parents('tr:first').before(
                tmpl('template-new-currency', {
                    code: value, title: option.attr('data-title'), sign: option.attr('data-sign')
                })
            );

            const tr_new = tr.prev();
            jsonPost('?module=settings&action=currencyAdd', { code: value },
                function(response) {
                    if (response.status !== 'ok') {
                        tr_new.remove();
                        option.attr('disabled', false).show();
                        return;
                    }

                    $.shop.trace('response', response.data);
                    tr_new.show();
                    tr_new.find('.settings').trigger('click');
                    tr_new.find('select[name^="rounding\['+response.data.code+'\]"]').val(response.data.rounding);
                    change_dialog.find('select').append('<option value="' + value + '" data-rate="1">' + value + '</option>');

                    if (self.find('option:not(:disabled)').length < 2) {
                        self.remove();
                    }

                    $spinner.hide();
                },
                function() {
                    tr_new.remove();
                    option.attr('disabled', false).show();
                }
            );
        });

        const updateRoundingReadonly = function($tr) {
            const $wrapper = $tr.find('.rounding');
            const $select = $wrapper.find('select');
            const $readonly_wrapper = $tr.find('.rounding-readonly').show();

            // Copy rounding state from selector to read-only container
            $readonly_wrapper.find('.rounding-value').html(
                $select.find('option:selected').html()
            );

            // Show or hide '(up only)' depending on checkbox state
            const $up_only = $readonly_wrapper.find('.rounding-up-only-enabled').hide();
            if ($select.val()) {
                if ($wrapper.find(':checkbox').prop('checked')) {
                    $up_only.show();
                }
            }
        };

        table.find('tr').each(function() {
            updateRoundingReadonly($(this));
        });

        // for primary currency
        table.off('edit_rate', 'tr.primary').on('edit_rate', 'tr.primary', function() {
            const $tr = $(this).closest('tr');
            $tr.find('.rounding-readonly').hide();
            $tr.find('.rounding').show();

        }).off('readable', 'tr.primary').on('readable', 'tr.primary', function() {
            const $tr = $(this).closest('tr');

            jsonPost('?module=settings&action=currencyChangeRate', $tr.find(':input').serialize(), function() {
                $tr.find('.s-actions').removeClass('activate');
                $tr.find('.rounding').hide();
                updateRoundingReadonly($tr);
                currencySave($tr).default();
            });
        });

        table.off('edit_rate', '.s-rate.editable span[id]').on('edit_rate', '.s-rate.editable span[id]', function() {
            $(this).inlineEditable({
                size: {
                    width: 50
                },
                makeReadableBy: ['esc', 'enter'],
                afterMakeEditable: function(input) {
                    const $tr = $(input).closest('tr');
                    $tr.find('.rounding-readonly').hide();
                    $tr.find('.rounding').show();
                },
                beforeBackReadable: function(input, data) {
                    input = $(input);
                    const rate = $(input).val();
                    const rate_num = parseFloat(rate.replace(',', '.'));
                    input.removeClass('state-error');
                    if (isNaN(rate_num) || (rate_num <= 0) || (Math.round(rate_num * 1000000) <= 0)) {
                        input.addClass('state-error');
                        return false;
                    }
                },
                afterBackReadable: function(input, data) {
                    const self = $(this);
                    self.closest('tr').find('.rounding').hide();
                    const $tr = self.closest('tr');
                    const code = $tr.data('code');
                    const post = $tr.find(':input').serialize();

                    $tr.find('.s-actions').removeClass('activate');
                    updateRoundingReadonly($tr);

                    const $input = $(input);
                    const rate = $input.val();
                    const rate_num = parseFloat(rate.replace(',', '.'));

                    if (isNaN(rate_num) || rate_num <= 0) {
                        return;
                    }

                    jsonPost('?module=settings&action=currencyChangeRate', post,
                        function(r) {
                            const data = r.data;
                            self.text(data[code].rate);
                            $input.val(data[code].rate);

                            const select = change_dialog.find('select');
                            select.find('option[value='+code+']').attr('data-rate', data[code].rate);
                            select.trigger('change');
                            currencySave($tr).default();
                        },
                        function() {
                            $input.val(data.old_text);
                            self.text(data.old_text);
                            currencySave($tr).default();
                        }
                    );
                }
            }).trigger('editable');
        });

        table.off('click', 'td .save').on('click', 'td .save', function(e) {
            e.preventDefault();

            const self = $(this);
            const tr = self.parents('tr:first');

            let $wrapper = tr.find('.s-rate.editable span');
            if (tr.hasClass('primary')) {
                $wrapper = tr;
            }

            $wrapper.trigger('readable');
        });

        table.off('click', '.settings').on('click', '.settings', function(event) {
            event.preventDefault();

            const self = $(this);
            const tr = self.parents('tr:first');
            tr.find('.s-actions').addClass('activate');

            let $wrapper = tr.find('span');
            if (tr.hasClass('primary')) {
                $wrapper = tr;
            }

            $wrapper.trigger('edit_rate');
        });

        form.on('submit', function(event) {
            event.preventDefault();

            $submitButton.append('<span class="s-msg-after-button"><i class="fas fa-spinner fa-spin custom-ml-4"></i></span>');

            jsonPost(form.attr('action'), form.serialize(), function(r) {
                $.settings.dispatch('#/currencies/', true);
            });
        });
    },

    currenciesAction: function() {

    },
    currenciesBlur: function() {

    }
});
