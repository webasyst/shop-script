/**
 *
 * @names checkout*
 * @method checkoutInit
 * @method checkoutAction
 * @method checkoutBlur
 */
$.extend($.settings = $.settings || {}, {
    checkoutInit: function(options) {

        $.settings.checkoutOptions = options || {};
        $.settings.checkoutOptions.loc = this.checkoutOptions.loc || {};

        // Init checkout recommendations alert
        var $alert_link = $('.js-checkout-recommendations-link'),
            $alert = $('.js-checkout-recommendations'),
            storage_key = 'shop/checkout_alert_hidden';

        function showAlert() {
            $alert.show();
            $alert_link.find('.js-arrow').toggleClass('hidden');
            $.storage.del(storage_key);
        }

        function hideAlert() {
            $alert.hide();
            $alert_link.find('.js-arrow').toggleClass('hidden');
            $.storage.set(storage_key, 1);
        }

        if (!$.storage.get(storage_key)) {
            showAlert();
        }

        $alert.on('click', '.close', hideAlert);
        $alert_link.on('click', function () {
            if ($.storage.get(storage_key)) {
                showAlert();
            } else {
                hideAlert();
            }
        });

        $('.js-old-checkout').addClass('selected');
        $('#s-settings-menu').find('a[href="?action=settings#/checkout/"]').parent().addClass('selected');

        // checkout steps
        $("#checkout-steps td").on('click', 'a.link-options', function(e) {
            e.preventDefault();
            var td = $(this).closest('td');
            var step_id = $(this).closest('tr').data('step-id');
            var form = td.find('form');
            if (form.is(':hidden')) {
                $.post('?module=settings&action=checkoutOptions', {step_id: step_id}, function (html) {
                    $(html).insertAfter(form.find('div.field.system:first'));
                    form.show();

                    var markAsChanged = function() {
                        form.find(':submit')
                            .removeClass("green")
                            .addClass("yellow");
                    };

                    form.off('input', markAsChanged);
                    form.on('input', markAsChanged);
                });
            } else {
                form.hide();
                form.find('div.field:not(.system)').remove();
            }
            return false;
        });

        $("#checkout-steps td").on('click', 'a.link-enable', function(e) {
            e.preventDefault();
            var tr = $(this).closest('tr');
            var step_id = tr.data('step-id');
            $.post("?module=settingsCheckoutSave&action=toggle", {status: 1, step_id: step_id}, function () {
               tr.find('.js-links').html($.settings.checkoutOptions.templates.settings_button);
               tr.find('h4').removeClass('gray');
               tr.find('.js-checkout-steps-handle').removeClass('s-disable-grip');
               tr.removeClass('disabled');
            }, "json");
            return false;
        });

        $("#checkout-steps td").on('click', 'a.link-disable', function(e) {
            e.preventDefault();
            var tr = $(this).closest('tr');
            var step_id = tr.data('step-id');
            $.post("?module=settingsCheckoutSave&action=toggle", {status: 0, step_id: step_id}, function () {
                tr.find('a.link-options').click();
                tr.find('.js-links').html($_('Disabled') +
                    ' <a href="#" class="link-enable inline-link">' + $_('Turn on') + '</a>');
                tr.find('h4').addClass('gray');
                tr.find('.js-checkout-steps-handle').addClass('s-disable-grip');
                tr.addClass('disabled');
            }, "json");
            return false;
        });

        $("#checkout-steps td form").submit(function (e) {
            var form = $(this);
            setTimeout(function() {
                if (!e.validation_failed) {
                    $.post(form.attr('action'), form.serialize(), function (response) {
                        var tr = form.closest('tr');
                        tr.find('a.link-options').click();
                        tr.find("h3.name").text(response.data.name);
                        form.find(':submit').removeClass('yellow').addClass('green');
                        form.closest('td').find('.js-links').before(
                            $('<div style="margin-right:10px;"><i class="fas fa-check-circle text-green"></i> '+$.settings.checkoutOptions.loc.saved+'</div>').animate({
                                opacity: 0
                            }, 1000, function() {
                                $(this).remove();
                            })
                        );
                    }, "json");
                }
            }, 1);
            return false;
        });

        $("#checkout-steps > tbody").sortable({
            animate: 150,
            handle: '.js-checkout-steps-handle',
            onEnd(event) {
                const tr = event.item;
                const id = tr.dataset?.stepId;
                let prev = tr.previousElementSibling;
                if (prev) {
                    prev = prev.dataset?.stepId;
                } else {
                    prev = '';
                }

                $.post("?module=settingsCheckoutSave&action=move" , {step_id: id, prev_id: prev}, function () {
                }, "json")
            }
        });
    },

    /**
     * JS logic for settings dialog for conditional fields.
     * See SettingsCheckoutFieldValues.html, SettingsCheckoutContactFormEditor.html
     */
    checkoutFieldValuesDialog: function(url, input_hide_unmatched) {
        var dialog = function(url, input_hide_unmatched) {
            var old_dialog = $('#s-field-values');
            if (old_dialog.length) {
                old_dialog.parent().remove();
            }
            $.get(url, function(html) {
                $.waDialog({
                    html,
                    onOpen: function(d, waDialog) {
                        var form = d.find('form');

                        // Link to add new value
                        d.on('click', '.s-add-value', function() {
                            var self = $(this);
                            var parent = self.parents('table:first');
                            var item_tmpl = parent.find('.s-new-value');
                            if (item_tmpl.length) {
                                var new_item = item_tmpl.clone();
                                new_item.addClass('sortable').removeClass('s-new-value').show().insertBefore(item_tmpl);
                                new_item.find('input').attr('disabled', false);

                                // increment index of new_item (that indexes <= 0)
                                var name = item_tmpl.find('input:first').attr('name');
                                var pos = name.lastIndexOf('[');
                                var index = (parseInt(name.substr(pos + 1), 10) || 0) - 1;
                                name = name.substr(0, pos) + '['+index+']';
                                item_tmpl.find('input:first').attr('name', name);
                            }
                            return false;
                        });

                        // Link to add new rule
                        var f;
                        d.on('click', '.s-add-rule', f = function() {
                            var item_tmpl = d.find('.s-new-rule');
                            if (item_tmpl.length) {
                                var new_item = item_tmpl.clone();
                                new_item.removeClass('s-new-rule').show().insertBefore(item_tmpl);
                                new_item.find('input[name^="parent"]').attr('disabled', false);
                                new_item.find('.s-add-value').click();
                                sortable(d);

                                var index = parseInt(item_tmpl.find('input[name="parent[]"]').val(), 10) + 1 || 1;
                                item_tmpl.find('input[name="parent[]"]').val(index);
                                item_tmpl.find('input[name^="parent_value"]').attr('name', 'parent_value['+index+']');
                                item_tmpl.find('input[name^="value"]').attr('name', 'value['+index+'][0]');
                            }
                            return false;
                        });
                        // Add new rule right away when there's no rules yet
                        if (d.find('.s-new-rule').siblings().length <= 0) {
                            f();
                        }

                        // Link to delete value
                        d.on('click', '.s-delete-value', function() {
                            var self = $(this);
                            var id = self.attr('data-id');
                            var tr = self.parents('tr:first');
                            var table = tr.parents('table:first');
                            if (id) {
                                form.append('<input type="hidden" name="delete[]" value="'+id+'">');
                            }
                            tr.remove();
                            if (!table.find('tr.sortable:first').length) {
                                table.parents('div.field:first').remove();
                            }
                            return false;
                        });
                        sortable(d);

                        if (input_hide_unmatched.val()) {
                            d.find('select.otherwise-options').val('hide');
                        } else {
                            d.find('select.otherwise-options').val('input');
                        }

                        d.find(':submit').on('click', function() {
                            var data = form.serializeArray();

                            // Validation
                            var validation_passed = true;
                            form.find('.state-error-hint').remove();
                            form.find('.state-error').removeClass('state-error');
                            form.find('[name^="parent_value["]:not(:disabled)').each(function() {
                                if (!this.value) {
                                    validation_passed = false;
                                    $(this).addClass('state-error').after($('<em class="state-error-hint"></em>').text($.settings.checkoutOptions.loc.field_is_required));
                                }
                            });
                            if (!validation_passed) {
                                $('#s-field-values').closest('.dialog').find('.dialog-buttons :submit').attr('disabled', false);
                                return false;
                            }

                            // Copy to main form the data that is to be saved to ContactField config
                            if (d.find('select.otherwise-options').val() == 'input') {
                                input_hide_unmatched.val('');
                            } else {
                                input_hide_unmatched.val('1').closest('td').find('input:checkbox[name$="[required]"]').attr('checked', false);
                            }

                            // Save data to DB via a separate controller
                            $.shop.jsonPost(form.attr('action'), data, function(r) {
                                waDialog.close();
                                var wrapper = input_hide_unmatched.closest('.field-advanced-settings');
                                wrapper.find('.show-when-modified').show();
                                wrapper.find('.hide-when-modified').hide();
                            });

                        });
                    }
                });
            });
        };

        // Helper to init/reinit sortable list of values
        var sortable = function(d, refresh) {
            d.find('.value table>tbody').sortable({
                distance: 5,
                helper: 'clone',
                items: 'tr.sortable',
                opacity: 0.75,
                tolerance: 'pointer'
            });
        };

        this.checkoutFieldValuesDialog = dialog;
        return dialog.call(this, url, input_hide_unmatched);
    }
});
