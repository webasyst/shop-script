/**
 *
 * @names stock*
 * @method stocksInit
 * @method stocksAction
 * @method stocksBlur
 */
$.extend($.settings = $.settings || {}, {

    // Called from SettingsStocks.html template
    stocksInit: function (options) {
        this.stock_options = options;

        var form = $('#s-settings-stocks-form');
        var content = $('#s-settings-stocks');

        // Briefly show 'successfully saved' indicator after a save
        if ($.storage.get('shop/settings/stock/just-saved')) {
            $.storage.del('shop/settings/stock/just-saved');
            form.find(':submit').siblings('.s-msg-after-button').show().animate({opacity: 0}, 2000, function () {
                $(this).hide();
            });
        }

        // Link to add new stock
        $('#s-settings-add-stock').on('click', function () {
            // render new item
            var new_tr = $($.parseHTML($.settings.stock_options.new_stock));

            // .s-inventory-stock checkbox enable or disable
            if (content.find('tr.new-stock').length < 1) {
                new_tr.find('.s-inventory-stock').show().find('input').attr('disabled', false);
            } else {
                new_tr.find('.s-inventory-stock').hide().find('input').attr('disabled', true);
            }


            content.find('tbody:first').prepend(new_tr).sortable('refresh');
            new_tr.find('input[data-name="name"]').select();
            form.find(':submit').removeClass('green').addClass('yellow');
            return false;
        });

        // Link to add new virtual stock
        $('#s-settings-add-virtualstock').on('click', function () {
            // render new item
            var new_tr = $($.parseHTML($.settings.stock_options.new_virtualstock));
            content.find('tbody:first').prepend(new_tr).sortable('refresh');

            // drag-and-drop for sub-stocks
            new_tr.find('.sortable').sortable({
                distance: 5,
                helper: 'clone',
                items: '.value.substock-checkbox-wrapper',
                handle: 'i.sort.substocks-handle',
                opacity: 0.75,
                tolerance: 'pointer'
            });

            new_tr.find('input[data-name="name"]').select();
            form.find(':submit').removeClass('green').addClass('yellow');
            return false;
        });

        // Drag-and-drop for substocks of existing virtual stocks
        content.find('.sortable.substocks-wrapper').each(function () {
            $(this).sortable({
                distance: 5,
                helper: 'clone',
                items: '.value.substock-checkbox-wrapper',
                handle: 'i.sort.substocks-handle',
                opacity: 0.75,
                tolerance: 'pointer'
            });
        });

        // Link to delete virtual stock
        content.on('click', 'tr[data-virtualstock-id] .s-delete-stock', function () {
            var $tr = $(this).closest('tr');
            var stock_id = parseInt($tr.attr('data-virtualstock-id'), 10);
            if (stock_id) {
                $.post($('#s-settings-delete-stock form').attr('action'), {vid: stock_id});
            }
            $tr.remove();
            return false;
        });

        // Link to delete non-virtual stock shows a dialog with further options
        content.on('click', 'tr[data-id] .s-delete-stock', function () {
            var tr = $(this).closest('tr');
            var stock_id = parseInt(tr.attr('data-id'), 10);
            if (!stock_id) {
                tr.remove();
                return false;
            }

            var d = null;
            if (content.find('.s-stock').length > 1) {
                d = $("#s-settings-delete-stock");
            } else {
                d = $("#s-settings-delete-last-stock");
            }

            d.waDialog({
                disableButtonsOnSubmit: true,
                onLoad: function () {
                    var form = d.find('form:first');
                    var dst_stock = form.find('select[name=dst_stock]');
                    dst_stock.find('option').attr('disabled', false).show();
                    dst_stock.find('option[value=' + stock_id + ']').attr('disabled', true).hide();
                    var first = dst_stock.find('option:not(:disabled):first');
                    first.attr('selected', true);
                    if (!d.data('inited')) {
                        form.find('input[name=delete_stock]').change(function () {
                            if ($(this).val() == '1') {
                                dst_stock.attr('disabled', false);
                            } else {
                                dst_stock.attr('disabled', true);
                            }
                        });
                        d.data('inited', true);
                    }
                },
                onSubmit: function () {
                    tr.hide();
                    var dialog_form = d.find('form:first');
                    var dst_stock = dialog_form.find('select[name=dst_stock]');
                    var option = dst_stock.find('option[value=' + stock_id + ']');
                    $.post(dialog_form.attr('action') + '&id=' + stock_id, dialog_form.serializeArray(),
                        function (r) {
                            if (r.status == 'ok') {
                                if (dst_stock.find('option').length <= 1) {
                                    // need different dialog content, so reloading
                                    $.settings.dispatch('#/stock/', true);
                                } else {
                                    tr.remove();
                                    option.remove();
                                    form.find(':input[data-stock-id="' + stock_id + '"]').each(function () {
                                        $(this).parents('div.value').remove();
                                    });

                                    var virtualstock = $($.parseHTML($.settings.stock_options.new_virtualstock));
                                    virtualstock.find(':input[data-stock-id="' + stock_id + '"]').each(function () {
                                        $(this).parents('div.value').remove();
                                    });
                                    $.settings.stock_options.new_virtualstock = virtualstock.html();

                                }
                            } else {
                                tr.show();
                                if (console) {
                                    if (r && r.errors) {
                                        console.error(r.errors);
                                    }
                                    if (r && r.responseText) {
                                        console.error(r.responseText);
                                    }
                                }
                            }
                            d.trigger('close');
                        }, 'json'
                    ).error(function (r) {
                        tr.show();
                        option.show();
                        if (console) {
                            console.error(r && r.responseText ? 'Error:' + r.responseText : r);
                        }
                        d.trigger('close');
                    });
                    return false;
                }
            });
            return false;
        });

        // Edit stock link
        content.on('click', '.s-edit-stock', function () {
            var $tr = $(this).closest('tr');
            $tr.find('.hide-when-editable').addClass('hidden');
            $tr.find('.show-when-editable').removeClass('hidden');
            $tr.find('input').attr('disabled', false);
            form.find(':submit').removeClass('green').addClass('yellow');
            return false;
        });

        // Click on 'Visible in frontend' checkbox toggles checklist of storefronts
        content.on('change', '.is-public-checkbox', function () {
            var $checklist = $(this).closest('.field').find('.storefonts-checklist');
            if (this.checked) {
                $checklist.slideDown();
            } else {
                $checklist.slideUp();
            }
        });

        // Helper to make sure stock counts are fine
        var validateBoundary = function (input, name) {
            var val = parseInt(input.val(), 10);
            var tr = input.parents('tr:first');
            var other = name == 'low_count' ? tr.find('input[data-name=critical_count]') : tr.find('input[data-name=low_count]');
            var error = '';
            var validate_errors = $.settings.stock_options.validate_errors;
            if (
                (input.val() && isNaN(val)) ||
                (!input.val() && parseInt(other.val(), 10)) ||
                val < 0) {
                error = validate_errors.number;
            } else if (name == 'low_count' && val < parseInt(other.val(), 10)) {
                error = validate_errors.no_less;
            } else if (name == 'critical_count' && val > parseInt(other.val(), 10)) {
                error = validate_errors.no_greater;
            }
            if (error) {
                tr.addClass('has-errors');
                input.addClass('error').nextAll('.errormsg:first').text(error).show();
            } else {
                input.removeClass('error').nextAll('.errormsg:first').hide();
            }
            if (!tr.find('.error:first').length) {
                tr.removeClass('has-errors');
            }
        };

        // Validate stock name and counts while user types
        content.on('keydown', 'input[type=text]', function () {
            var item = $(this);
            var name = item.attr('data-name');
            var timer_id = item.data('timer_id');
            if (timer_id) {
                clearTimeout(timer_id);
            }
            item.data('timer_id', setTimeout(function () {
                if (name === 'name') {
                    if (!item.val()) {
                        item.addClass('error').nextAll('.errormsg:first').text(
                            $.settings.stock_options.validate_errors.empty
                        ).show();
                    } else {
                        item.removeClass('error').nextAll('.errormsg:first').text('').hide();
                    }
                } else if (name === 'low_count' || name === 'critical_count') {
                    var other = name == 'low_count' ?
                        item.parents('tr:first').find('input[data-name="critical_count"]') :
                        item.parents('tr:first').find('input[data-name="low_count"]');
                    validateBoundary(item, name);
                    validateBoundary(other, other.attr('data-name'));
                }

            }, 450));
        });

        // Submit form via XHR
        form.submit(function () {
            var form = $(this),
                is_locked = false;


            // Add hidden inputs specifying position of stocks
            var ids_order = content.find('tr').map(function () {
                var $tr = $(this);
                if ($tr.is('[data-virtualstock-id]')) {
                    return 'v' + $tr.data('virtualstock-id');
                } else if ($tr.is('[data-id]')) {
                    return $tr.data('id');
                }
            }).get();
            form.find('input[name="stocks_order"]').val(ids_order.join(','));

            content.find('.substocks-wrapper').removeClass('error').find('.errormsg').hide();

            // Sub-stocks of virtual stocks
            content.find('input.substocks:enabled').each(function () {
                var $input = $(this);
                var stock_ids = $input.closest('.field').find('[data-stock-id]:checked').map(function () {
                    return $(this).data('stock-id');
                }).get();
                $input.val(stock_ids.join(','));
                if (!stock_ids.length) {
                    $input.closest('.field').find('.substocks-wrapper').addClass('error').find('.errormsg').show();
                }
            });

            if (!form.find('.error:first').length) {
                if (!is_locked) {
                    form.find(':submit').attr("disabled", true);
                    is_locked = true;
                    form.find(':submit').parent().append('<i class="icon16 loading"></i>');
                    $.post(form.attr('action'), form.serialize(), function (r) {
                        if (r.status == 'ok') {
                            $.storage.set('shop/settings/stock/just-saved', true);
                            $.settings.dispatch('#/stock', true);
                        } else {
                            if (console) {
                                console.error(r && r.errors ? r.errors : r);
                            }
                        }
                    }, 'json').error(function (r) {
                        if (console) {
                            console.error(r && r.responseText ? r.responseText : r);
                        }
                    }).always(function () {
                        is_locked = false;
                        form.find(':submit').attr("disabled", false);
                    });
                }
            }

            return false;
        });

        // Make stock rows sortable
        content.find('tbody:first').sortable({
            distance: 5,
            helper: 'clone',
            items: 'tr',
            handle: 'i.sort.stock-rows-handle',
            opacity: 0.75,
            tolerance: 'pointer',
            update: function (event, ui) {
                form.find(':submit').removeClass('green').addClass('yellow');
            }
        });
    },

    stockRulesInit: function (new_rule_template, new_condition_template, err_condition_required) {
        "use strict";

        var $form = $('#s-settings-stock-rules-form');
        var $add_rule_link = $('#s-settings-add-rule');
        var $table_tbody = $form.find('table tbody').first();

        // Submit form via XHR
        $form.on('submit', function () {

            var evt = $.Event('rules:before_submit');
            $form.trigger(evt);
            if (evt.isDefaultPrevented()) {
                return false;
            }

            var prevent_submit = false;
            var form_data = $form.serializeArray();

            $form.find('.stock-selector').each(function () {
                var $stock_selector = $(this);
                var $tr = $stock_selector.closest('tr');
                var stock_id = $stock_selector.val();
                //var substock_id = $stock_selector.closest('td').find('.substock-selector[data-stock-id="'+stock_id+'"]').val();
                var rule_id = $tr.find('.stock-rule-condition:last').data('rule-id');
                if (rule_id) {
                    form_data.push({
                        name: 'rules[' + rule_id + '][parent_stock_id]',
                        value: stock_id
                    });
                    /*form_data.push({
                     name: 'rules['+rule_id+'][substock_id]',
                     value: substock_id
                     });*/
                } else {
                    var $type_selector = $tr.find('.add-condition-selector').addClass('error');
                    $type_selector.after($('<span class="errormsg"></span>').text(err_condition_required));
                    prevent_submit = true;
                }
            });
            if (prevent_submit) {
                return false;
            }

            $form.find(':submit').parent().append('<span class="s-msg-after-button"><i class="icon16 loading"></i></span>');
            $.post($form.attr('action'), form_data, function () {
                $.storage.set('shop/settings/stock/just-saved2', true);
                $.settings.dispatch('#/stock', true);
            });
            return false;
        });

        // Briefly show 'successfully saved' indicator after a save
        if ($.storage.get('shop/settings/stock/just-saved2')) {
            $.storage.del('shop/settings/stock/just-saved2');
            $form.find(':submit').siblings('.s-msg-after-button').show().animate({opacity: 0}, 2000, function () {
                $(this).hide();
            });
        }

        // Add new rule group when user clicks the Add link
        $add_rule_link.on('click', function () {
            var $new_tr = $($.parseHTML(new_rule_template)[0]);
            $new_tr.prependTo($table_tbody);
            $table_tbody.sortable('refresh');
            initRuleGroupRow(null, $new_tr);
            $form.change();
        });

        // Add new rule when user selects its type in Condition column
        (function () {
            var new_rule_id = -1;
            $table_tbody.on('change', '.add-condition-selector', function () {
                var $type_selector = $(this);
                var condition_type = $type_selector.val();
                var tmpl = new_condition_template
                    .replace(/%%RULE_ID%%/g, '' + new_rule_id)
                    .replace(/%%RULE_TYPE%%/g, condition_type)
                    .replace(/%%RULE_DATA%%/g, '');
                var $new_rule_div = $($.parseHTML(tmpl)[0]);
                $new_rule_div.attr('data-rule-id', '' + new_rule_id);
                $new_rule_div.insertBefore($type_selector);
                $type_selector.val('');

                var evt;
                $new_rule_div.trigger(evt = $.Event('rules:condition_init.' + condition_type, {
                    rule_id: new_rule_id,
                    rule_type: condition_type,
                    rule_data: ''
                }));
                if (!evt.isDefaultPrevented()) {
                    $type_selector.removeClass('error').siblings('.errormsg').remove();
                    $form.change();
                    new_rule_id--;
                } else {
                    $new_rule_div.remove();
                }
            });
        })();

        // Link to delete a condition
        $table_tbody.on('click', '.s-delete-condition', function () {
            $(this).closest('.stock-rule-condition').remove();
            $form.change();
        });

        // Controls for virtual stock and substock selectors in Stock column
        $table_tbody.on('change', '.stock-selector', function () {
            var $stock_selector = $(this);
            var $substock_selectors = $stock_selector.closest('td').find('.substock-selector').hide();
            $substock_selectors.parent().hide();
            $substock_selectors.filter('[data-stock-id="' + $stock_selector.val() + '"]').show().parent().show();
        });

        // Link to delete a row
        $table_tbody.on('click', '.s-delete-rule', function () {
            $(this).closest('.s-stock-rule').remove();
            $form.change();
        });

        // Init existing rows
        $table_tbody.children().each(initRuleGroupRow);

        // Make existing rows sortable
        $table_tbody.sortable({
            distance: 5,
            helper: 'clone',
            items: 'tr:not(.disabled)',
            handle: '.s-stock-sort-handle i.sort',
            opacity: 0.75,
            tolerance: 'pointer',
            update: function (event, ui) {
            }
        });

        // Remember initial form state when initialization finishes
        $form.one('rules:init_complete', function () {
            var h, form_data = $form.serialize();
            $form.one('change', '.stock-selector', markFormAsModified);
            $form.on('change', h = function () {
                if (form_data != $form.serialize()) {
                    markFormAsModified();
                }
            });

            function markFormAsModified() {
                $form.find(':submit.green').removeClass('green').addClass('yellow');
                $form.off('change', h);
            }
        });

        function initRuleGroupRow(i, tr) {
            $(tr).find('.stock-selector').change();
        }

        function formChanged() {
            // Replaced with real code after rules:init_complete
        }
    },

    stockConditionsInit: function () {
        "use strict";

        var $form = $('#s-settings-stock-rules-form');

        // Event for plugins to init existing condition editors
        $form.find('table tbody tr .stock-rule-condition').each(function () {
            var $conditon_wrapper = $(this);
            var rule_type = $conditon_wrapper.find('input[name$="[rule_type]"]').val();
            $conditon_wrapper.trigger($.Event('rules:condition_init.' + rule_type, {
                rule_data: $conditon_wrapper.find('input[name$="[rule_data]"]').val(),
                rule_id: $conditon_wrapper.data('rule-id'),
                rule_type: rule_type
            }));
        });

        $form.trigger('rules:init_complete');
    },

    stocksAction: function () {

    },

    stocksBlur: function () {

    }
});
