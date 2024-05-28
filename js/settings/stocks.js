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

        const form = $('#s-settings-stocks-form');
        const content = $('#s-settings-stocks');
        const $submitButton = form.find('.js-form-submit');
        const formChanged = this.formChanged.bind($submitButton);

        // Briefly show 'successfully saved' indicator after a save
        if ($.storage.get('shop/settings/stock/just-saved')) {
            $.storage.del('shop/settings/stock/just-saved');
            form.find(':submit').find('.s-msg-after-button').show().animate({opacity: 0}, 2000, function () {
                $(this).hide();
            });
        }

        form.on('change', formChanged);

        // Link to add new stock
        $('#s-settings-add-stock').on('click', function(event) {
            event.preventDefault();

            // render new item
            const new_tr = $($.parseHTML($.settings.stock_options.new_stock));

            // .s-inventory-stock checkbox enable or disable
            if (content.find('tr.new-stock').length < 1) {
                new_tr.find('.s-inventory-stock').show().find('input').attr('disabled', false);
            } else {
                new_tr.find('.s-inventory-stock').hide().find('input').attr('disabled', true);
            }


            content.find('tbody:first').prepend(new_tr).sortable('refresh');
            new_tr.find('input[data-name="name"]').select();
            formChanged();
        });

        // Link to add new virtual stock
        $('#s-settings-add-virtualstock').on('click', function(event) {
            event.preventDefault();

            // render new item
            const new_tr = $($.parseHTML($.settings.stock_options.new_virtualstock));
            content.find('tbody:first').prepend(new_tr).sortable('refresh');

            // drag-and-drop for sub-stocks
            new_tr.find('.sortable').sortable({
                distance: 5,
                helper: 'clone',
                items: '.substock-checkbox-wrapper',
                handle: '.substocks-handle',
                opacity: 0.75,
                tolerance: 'pointer'
            });

            new_tr.find('input[data-name="name"]').select();
            formChanged();
        });

        // Drag-and-drop for substocks of existing virtual stocks
        content.find('.sortable.substocks-wrapper').each(function () {
            $(this).sortable({
                distance: 5,
                helper: 'clone',
                items: '.substock-checkbox-wrapper',
                handle: '.substocks-handle',
                opacity: 0.75,
                tolerance: 'pointer'
            });
        });

        // Link to delete virtual stock
        content.on('click', 'tr[data-virtualstock-id] .s-delete-stock', function(event) {
            event.preventDefault();

            const $tr = $(this).closest('tr');
            const stock_id = parseInt($tr.attr('data-virtualstock-id'), 10);

            if (stock_id) {
                $.post($('#s-settings-delete-stock form').attr('action'), {vid: stock_id});
            }

            $tr.remove();
        });

        // Link to delete non-virtual stock shows a dialog with further options
        content.on('click', 'tr[data-id] .s-delete-stock', function(event) {
            event.preventDefault();

            const tr = $(this).closest('tr');
            const stock_id = parseInt(tr.attr('data-id'), 10);

            if (!stock_id) {
                tr.remove();
                return;
            }

            let $parent;
            if (content.find('.s-stock').length > 1) {
                $parent = $("#s-settings-delete-stock");
            } else {
                $parent = $("#s-settings-delete-last-stock");
            }

            const parentDstStock = $parent.find('select[name=dst_stock]');
            parentDstStock.find('option').attr('disabled', false).show();
            const parentOption = parentDstStock.find('option[value=' + stock_id + ']').attr('disabled', true).hide();

            $.waDialog({
                html: $parent[0].outerHTML,
                onOpen($dialog, dialog) {
                    const form = dialog.$block.find('form:first');
                    const dst_stock = form.find('select[name=dst_stock]');

                    const first = dst_stock.find('option:not(:disabled):first');
                    first.attr('selected', true);

                    let inited = false;
                    if (!inited) {
                        form.find('input[name=delete_stock]').change(function () {
                            if ($(this).val() == '1') {
                                dst_stock.attr('disabled', false);
                            } else {
                                dst_stock.attr('disabled', true);
                            }
                        });

                        inited = true;
                    }

                    const $submitButton = dialog.$block.find('.js-dialog-submit');
                    $submitButton.on('click', function(event) {
                        event.preventDefault();

                        $(this).append('<i class="fas fa-spinner fa-spin custom-ml-4"></i>');
                        tr.hide();

                        const option = dst_stock.find('option[value=' + stock_id + ']');

                        $.post(form.attr('action') + '&id=' + stock_id, form.serializeArray(),
                            function (r) {
                                if (r.status !== 'ok') {
                                    tr.show();
                                    if (console) {
                                        if (r && r.errors) {
                                            console.error(r.errors);
                                        }
                                        if (r && r.responseText) {
                                            console.error(r.responseText);
                                        }
                                    }

                                    return;
                                }

                                if (dst_stock.find('option').length <= 2) {
                                    // need different dialog content, so reloading
                                    $.settings.dispatch('#/stock/', true);
                                } else {
                                    tr.remove();
                                    option.remove();
                                    parentOption.remove();

                                    const $rulesForm = $('#s-settings-stock-rules-form');
                                    $rulesForm.find('.stock-selector option[value=' + stock_id + ']:not(:selected)').remove();

                                    form.find(':input[data-stock-id="' + stock_id + '"]').each(function () {
                                        $(this).parents('div.value').remove();
                                    });

                                    const virtualstock = $($.parseHTML($.settings.stock_options.new_virtualstock));
                                    virtualstock.find(':input[data-stock-id="' + stock_id + '"]').each(function () {
                                        $(this).parents('div.value').remove();
                                    });
                                    $.settings.stock_options.new_virtualstock = virtualstock.html();
                                }

                                dialog.close();
                            }, 'json'
                        ).fail(function (r) {
                            tr.show();

                            if (console) {
                                console.error(r && r.responseText ? 'Error:' + r.responseText : r);
                            }

                            dialog.close();
                        });
                    });
                },
            });
        });

        // Edit stock link
        content.on('click', '.s-edit-stock', function(event) {
            event.preventDefault();

            const $tr = $(this).closest('tr');
            $tr.find('.hide-when-editable').addClass('hidden');
            $tr.find('.show-when-editable').removeClass('hidden');
            $tr.find('input').attr('disabled', false);
            formChanged();
        });

        // Click on 'Visible in frontend' checkbox toggles checklist of storefronts
        content.on('change', '.is-public-checkbox', function () {
            const $checklist = $(this).closest('.field').find('.storefonts-checklist');

            if (this.checked) {
                $checklist.slideDown();
            } else {
                $checklist.slideUp();
            }
        });

        // Helper to make sure stock counts are fine
        const validateBoundary = function (input, name) {
            const val = parseInt(input.val(), 10);
            const tr = input.parents('tr:first');
            const other = name == 'low_count' ? tr.find('input[data-name=critical_count]') : tr.find('input[data-name=low_count]');
            let error = '';
            const validate_errors = $.settings.stock_options.validate_errors;

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
            const item = $(this);
            const name = item.attr('data-name');
            const timer_id = item.data('timer_id');

            if (timer_id) {
                clearTimeout(timer_id);
            }

            item.data('timer_id', setTimeout(function () {
                if (name === 'name') {
                    if (!item.val()) {
                        item.addClass('error').nextAll('.state-error:first').text(
                            $.settings.stock_options.validate_errors.empty
                        ).show();
                    } else {
                        item.removeClass('error').nextAll('.state-error:first').text('').hide();
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
        form.on('submit', function(event) {
            event.preventDefault();

            let is_locked = false;

            // Add hidden inputs specifying position of stocks
            const ids_order = content.find('tr').map(function () {
                const $tr = $(this);
                if ($tr.is('[data-virtualstock-id]')) {
                    return 'v' + $tr.data('virtualstock-id');
                } else if ($tr.is('[data-id]')) {
                    return $tr.data('id');
                }
            }).get();

            form.find('input[name="stocks_order"]').val(ids_order.join(','));

            content.find('.substocks-wrapper').removeClass('error').find('.state-error').hide();

            // Sub-stocks of virtual stocks
            content.find('input.substocks:enabled').each(function () {
                const $input = $(this);
                const stock_ids = $input.closest('.field').find('[data-stock-id]:checked').map(function () {
                    return $(this).data('stock-id');
                }).get();

                $input.val(stock_ids.join(','));

                if (!stock_ids.length) {
                    $input.closest('.field').find('.substocks-wrapper').addClass('error').find('.errormsg').show();
                }
            });

            if (!form.find('.error:first').length) {
                if (!is_locked) {
                    $submitButton.attr("disabled", true);
                    is_locked = true;
                    $submitButton.append('<i class="fas fa-spinner fa-spin"></i>');

                    $.post(form.attr('action'), form.serialize(), function (r) {
                        if (r.status !== 'ok') {
                            if (console) {
                                console.error(r && r.errors ? r.errors : r);
                            }

                            return;
                        }

                        $.storage.set('shop/settings/stock/just-saved', true);
                        $.settings.dispatch('#/stock', true);
                    }, 'json').fail(function (r) {
                        if (console) {
                            console.error(r && r.responseText ? r.responseText : r);
                        }
                    }).always(function () {
                        is_locked = false;
                        $submitButton.attr("disabled", false);
                    });
                }
            }
        });

        // Make stock rows sortable
        content.find('tbody:first').sortable({
          group: 'stock-rows',
          handle: '.stock-rows-handle',
          animation: 100,
          removeCloneOnHide: true,
          onChange: function (evt) {
            formChanged();
          },
        });
    },

    stockRulesInit: function (new_rule_template, new_condition_template, err_condition_required) {
        const $form = $('#s-settings-stock-rules-form');
        const $add_rule_link = $('#s-settings-add-rule');
        const $table_tbody = $form.find('table tbody').first();
        const $submitButton = $form.find('.js-form-submit');
        const formChanged = this.formChanged.bind($submitButton);

        $form.on('change', formChanged);

        // Submit form via XHR
        $form.on('submit', function(event) {
            event.preventDefault();

            const evt = $.Event('rules:before_submit');

            $form.trigger(evt);

            if (evt.isDefaultPrevented()) {
                return;
            }

            let prevent_submit = false;
            const form_data = $form.serializeArray();

            $form.find('.stock-selector').each(function () {
                const $stock_selector = $(this);
                const $tr = $stock_selector.closest('tr');
                const stock_id = $stock_selector.val();
                const rule_id = $tr.find('.stock-rule-condition:last').data('rule-id');

                if (rule_id) {
                    form_data.push({
                        name: 'rules[' + rule_id + '][parent_stock_id]',
                        value: stock_id
                    });
                } else {
                    const $type_selector = $tr.find('.add-condition-selector').addClass('error');
                    $type_selector.after($('<span class="state-error"></span>').text(err_condition_required));
                    prevent_submit = true;
                }
            });

            if (prevent_submit) {
                return;
            }

            $form.find(':submit').append('<span class="s-msg-after-button"><i class="fas fa-spinner fa-spin"></i></span>');

            $.post($form.attr('action'), form_data, function () {
                $.storage.set('shop/settings/stock/just-saved2', true);
                $.settings.dispatch('#/stock', true);
            });
        });

        // Briefly show 'successfully saved' indicator after a save
        if ($.storage.get('shop/settings/stock/just-saved2')) {
            $.storage.del('shop/settings/stock/just-saved2');
            $form.find(':submit').find('.s-msg-after-button').show().animate({opacity: 0}, 2000, function () {
                $(this).hide();
            });
        }

        // Add new rule group when user clicks the Add link
        $add_rule_link.on('click', function(event) {
            event.preventDefault();

            const $new_tr = $($.parseHTML(new_rule_template)[0]);
            $new_tr.prependTo($table_tbody);
            $table_tbody.sortable('refresh');
            initRuleGroupRow(null, $new_tr);
            $form.change();
        });

        // Add new rule when user selects its type in Condition column
        (function () {
            let new_rule_id = -1;

            $table_tbody.on('change', '.add-condition-selector', function () {
                const $type_selector = $(this);
                const condition_type = $type_selector.val();
                const tmpl = new_condition_template
                    .replace(/%%RULE_ID%%/g, '' + new_rule_id)
                    .replace(/%%RULE_TYPE%%/g, condition_type)
                    .replace(/%%RULE_DATA%%/g, '');
                const $new_rule_div = $($.parseHTML(tmpl)[0]);
                $new_rule_div.attr('data-rule-id', '' + new_rule_id);
                $(this).closest('td').prepend($new_rule_div);
                $type_selector.val('');

                let evt;
                $new_rule_div.trigger(evt = $.Event('rules:condition_init.' + condition_type, {
                    rule_id: new_rule_id,
                    rule_type: condition_type,
                    rule_data: ''
                }));
                if (!evt.isDefaultPrevented()) {
                    $type_selector.removeClass('error').siblings('.state-error').remove();
                    $form.change();
                    new_rule_id--;
                } else {
                    $new_rule_div.remove();
                }
            });
        })();

        // Link to delete a condition
        $table_tbody.on('click', '.s-delete-condition', function (event) {
            event.preventDefault();

            $(this).closest('.stock-rule-condition').remove();
            $form.change();
        });

        // Controls for virtual stock and substock selectors in Stock column
        $table_tbody.on('change', '.stock-selector', function () {
            const $stock_selector = $(this);
            const $substock_selectors = $stock_selector.closest('td').find('.substock-selector').hide();
            $substock_selectors.parent().hide();
            $substock_selectors.filter('[data-stock-id="' + $stock_selector.val() + '"]').show().parent().show();
        });

        // Link to delete a row
        $table_tbody.on('click', '.s-delete-rule', function(event) {
            event.preventDefault();

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
            handle: '.stock-rows-handle',
            opacity: 0.75,
            tolerance: 'pointer'
        });

        // Remember initial form state when initialization finishes
        $form.one('rules:init_complete', function() {
            const form_data = $form.serialize();
            const handler = function () {
              if (form_data != $form.serialize()) {
                markFormAsModified();
              }
            };
            $form.one('change', '.stock-selector', markFormAsModified);
            $form.on('change', handler);

            function markFormAsModified() {
              formChanged();
              $form.off('change', handler);
            }
        });

        function initRuleGroupRow(i, tr) {
            $(tr).find('.stock-selector').change();
            formChanged(false); // fix
        }
    },

    stockConditionsInit: function () {
        "use strict";

        const $form = $('#s-settings-stock-rules-form');

        // Event for plugins to init existing condition editors
        $form.find('table tbody tr .stock-rule-condition').each(function () {
            const $conditon_wrapper = $(this);
            const rule_type = $conditon_wrapper.find('input[name$="[rule_type]"]').val();

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

    },

    formChanged: function (isChange = true) {
      const default_class = "green";
      const active_class = "yellow";

      if (isChange) {
        this.removeClass(default_class).addClass(active_class);
      } else {
        this.removeClass(active_class).addClass(default_class);
      }
    }
});
