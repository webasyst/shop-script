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
        var content = $('#s-settings-currencies');
        var form = content.find('form');
        var submit_button = form.find('input[type=submit]');
        var change_dialog = $('#s-settings-change-primary-currency-dialog');

        var table = content.find('table.s-settings-currencies');
        var sortable_context = table.find('tbody:first');
        sortable_context.sortable({
            distance: 5,
            helper: 'clone',
            items: 'tr.s-settings-currency',
            handle: 'i.sort',
            opacity: 0.75,
            tolerance: 'pointer',
            update: function(event, ui) {
                var item = $(ui.item);
                var item_code = item.attr('data-code');
                if (item_code == options.primary) {
                    sortable_context.sortable('cancel');
                    return;
                }
                var next = item.next();
                var next_code = next.attr('data-code');
                if (next_code == options.primary) {
                    sortable_context.sortable('cancel');
                    return;
                }
                jsonPost('?module=settings&action=currencyMove', { item: item_code, before: next_code },
                    function() {
                        var select = change_dialog.find('select');
                        if (next.length) {
                            select.find('option[value='+next_code+']').before(select.find('option[value='+item_code+']'));
                        } else {
                            select.append(select.find('option[value='+item_code+']'));
                        }
                    },
                    function() {
                        sortable_context.sortable('cancel');
                    }
                );
            }
        });

        table.off('click', '.delete').on('click', '.delete', function() {
            var tr = $(this).parents('tr:first');
            var code = tr.attr('data-code');
            var dialog = function(d) {
                d.waDialog({
                    disableButtonsOnSubmit: true,
                    onSubmit: function() {
                        var self = $(this);
                        jsonPost(self.attr('action')+'&code='+code, self.serialize(), function() {
                            $.settings.dispatch('#/currencies/', true);
                            d.trigger('close');
                        }, function() { d.trigger('close'); });
                        return false;
                    }
                });
            };
            var d = $('#s-settings-delete-currency');
            if (!d.length) {
                $('<div></div>').load('?module=dialog&action=currencyDelete&code='+code, function() {
                    $(document.body).append(this);
                    dialog($('#s-settings-delete-currency'));
                });
            } else {
                d.parent().load('?module=dialog&action=currencyDelete&code='+code, function() {
                    dialog($('#s-settings-delete-currency'));
                });
            }

            return false;
        });

        $('#s-settings-change-primary-currency').click(function() {
            var d = change_dialog;
            if (d.parent().get(0) != document.body) {
                $(document.body).append(d);
                d.find('select').change(function() {
                    var self = $(this).find('option:selected');
                    var code = self.val();
                    var rate = '1 ' + code + ' = ' + self.attr('data-rate') + ' ' + options.primary;
                    var text = options.convert_text.replace('%s', code).replace('%s', rate);

                    d.find('.s-convert-text').text(text);
                }).trigger('change');
            }
            d.waDialog({
                disableButtonsOnSubmit: true,
                onSubmit: function() {
                    var self = $(this);
                    var loading = self.find('i.loading').show();
                    jsonPost(self.attr('action'), self.serialize(),
                        function() {
                            $.settings.dispatch('#/currencies/', true);
                            loading.hide();
                            d.trigger('close');
                        }
                    );
                    return false;
                }
            });
            return false;
        });

        table.find('select.add-new-currency').change(function() {
            var self = $(this);
            var value = self.val();
            if (value != '0') {
                var old_tr = table.find('tr[data-code='+value+']');
                old_tr.remove();

                var option = self.find('option[value='+value+']');
                option.attr('disabled', true).hide();
                self.val(self.find('option:first').val());

                var tr = self.parents('tr:first').before(
                    tmpl('template-new-currency', {
                        code: value, title: option.attr('data-title'), sign: option.attr('data-sign')
                    })
                );

                var tr_new = tr.prev();
                jsonPost('?module=settings&action=currencyAdd', { code: value },
                    function(response) {
                        if(response.status==='ok') {
                            $.shop.trace('response', response.data);
                            tr_new.show();
                            tr_new.find('.settings').trigger('click');
                            tr_new.find('select[name^="rounding\['+response.data.code+'\]"]').val(response.data.rounding);
                            change_dialog.find('select').append('<option value="' + value + '" data-rate="1">' + value + '</option>');

                            if (self.find('option:not(:disabled)').length < 2) {
                                self.remove();
                            }
                        } else {
                            tr_new.remove();
                            option.attr('disabled', false).show();
                        }
                    },
                    function() {
                        tr_new.remove();
                        option.attr('disabled', false).show();
                    }
                );
            }
        });

        var updateRoundingReadonly = function($tr) {
            var $wrapper = $tr.find('.rounding');
            var $select = $wrapper.find('select');
            var $readonly_wrapper = $tr.find('.rounding-readonly').show();

            // Copy rounding state from selector to read-only container
            $readonly_wrapper.find('.rounding-value').html(
                $select.find('option:selected').html()
            );

            // Show or hide '(up only)' depending on checkbox state
            var $up_only = $readonly_wrapper.find('.rounding-up-only-enabled').hide();
            if ($select.val()) {
                if ($wrapper.find(':checkbox').prop('checked')) {
                    $up_only.show();
                }
            }
        };
        table.find('tr').each(function() {
            updateRoundingReadonly($(this));
        });

        table.off('edit_rate', '.s-rate.editable span:not([id])').on('edit_rate', '.s-rate.editable span:not([id])', function() {
            var $tr = $(this).closest('tr');
            $tr.find('.rounding-readonly').hide();
            $tr.find('.rounding').show();
        });
        table.off('readable', '.s-rate.editable span:not([id])').on('readable', '.s-rate.editable span:not([id])', function() {
            var $tr = $(this).closest('tr');
            jsonPost('?module=settings&action=currencyChangeRate', $tr.find(':input').serialize(), function(r) {
                $tr.find('.s-actions').removeClass('activate');
                $tr.find('.rounding').hide();
                updateRoundingReadonly($tr);
            });
        });

        table.off('edit_rate', '.s-rate.editable span[id]').on('edit_rate', '.s-rate.editable span[id]', function() {
            $(this).inlineEditable({
                maxSize: {
                    width: 40
                },
                makeReadableBy: ['esc', 'enter'],
                afterMakeEditable: function(input) {
                    var $tr = $(input).closest('tr');
                    $tr.find('.rounding-readonly').hide();
                    $tr.find('.rounding').show();
                },
                beforeBackReadable: function(input, data) {
                    var input = $(input);
                    var rate = $(input).val();
                    var rate_num = parseFloat(rate.replace(',', '.'));
                    input.removeClass('error');
                    if (isNaN(rate_num) || (rate_num <= 0) || (Math.round(rate_num * 1000000) <= 0)) {
                        input.addClass('error');
                        return false;
                    }
                },
                afterBackReadable: function(input, data) {
                    var self = $(this);
                    self.closest('tr').find('.rounding').hide();
                    var $tr = self.closest('tr');
                    var code = $tr.data('code');
                    var post = $tr.find(':input').serialize();

                    $tr.find('.s-actions').removeClass('activate');
                    updateRoundingReadonly($tr);

                    var $input = $(input);
                    var rate = $input.val();
                    var rate_num = parseFloat(rate.replace(',', '.'));
                    if (isNaN(rate_num) || rate_num <= 0) {
                        return;
                    }

                    jsonPost('?module=settings&action=currencyChangeRate', post,
                        function(r) {
                            var data = r.data;
                            self.text(data[code].rate);
                            $input.val(data[code].rate);

                            var select = change_dialog.find('select');
                            select.find('option[value='+code+']').attr('data-rate', data[code].rate);
                            select.trigger('change');
                        },
                        function() {
                            $input.val(data.old_text);
                            self.text(data.old_text);
                        }
                    );
                }
            }).trigger('editable');
        });
        table.off('click', 'td .save').on('click', 'td .save', function() {
            var self = $(this);
            var tr = self.parents('tr:first');
            tr.find('.s-rate.editable span').trigger('readable');
        });
        table.off('click', '.settings').on('click', '.settings', function() {
            var self = $(this);
            var tr = self.parents('tr:first');
            tr.find('.s-actions').addClass('activate');
            tr.find('span').trigger('edit_rate');
            return false;
        });

        form.find(':input').change(function() {
            submit_button.parents('.submit:first').show();
        });

        var jsonPost = function(url, data, success, error) {
            var default_error_handler = function(r) {
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
            var xhr = $.post(url, data,
                function(r) {
                    if (r.status != 'ok') {
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
                },
            'json');
            if (typeof error === 'function') {
                xhr.error(function(r) {
                    if (error(r) !== false) {
                        default_error_handler(r);
                    }
                });
            } else {
                xhr.error(default_error_handler);
            }
        };

        form.submit(function() {
            jsonPost(form.attr('action'), form.serialize(), function(r) {
                $.settings.dispatch('#/currencies/', true);
            });
            return false;
        });
    },

    currenciesAction: function() {

    },
    currenciesBlur: function() {

    }
});