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

        // checkout steps
        $("#checkout-steps td div.float-right").on('click', 'a.link-options', function() {
            var td = $(this).closest('td');
            var step_id = $(this).closest('tr').data('step-id');
            var form = td.find('form');
            if (form.is(':hidden')) {
                $.post('?module=settings&action=checkoutOptions', {step_id: step_id}, function (html) {
                    $(html).insertAfter(form.find('div.field.system:first'));
                    form.show(200);
                });
            } else {
                form.hide(200, function() {
                    form.find('div.field:not(.system)').remove();
                });
            }
            return false;
        });

        $("#checkout-steps td div.float-right").on('click', 'a.link-enable', function() {
            var tr = $(this).closest('tr');
            var step_id = tr.data('step-id');
            $.post("?module=settingsCheckoutSave&action=toggle", {status: 1, step_id: step_id}, function () {
               tr.find('div.links').html('<a href="#" class="link-options inline-link inline"><i class="icon16 settings"></i><b><i>' + $_('Configure') + '</i></b></a>');
               tr.find('h3').removeClass('gray');
               tr.removeClass('disabled');
            }, "json");
            return false;
        });

        $("#checkout-steps td div.float-right").on('click', 'a.link-disable', function() {
            var tr = $(this).closest('tr');
            var step_id = tr.data('step-id');
            $.post("?module=settingsCheckoutSave&action=toggle", {status: 0, step_id: step_id}, function () {
                tr.find('a.link-options').click();
                tr.find('div.links').html($_('Disabled') +
                    ' <a href="#" class="link-enable inline-link"><b><i>' + $_('Turn on') + '</i></b></a>');
                tr.find('h3').addClass('gray');
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
                        form.closest('td').find('.links').after(
                            $('<div class="float-right" style="margin-right:20px;"><i class="icon16 yes"></i> '+$.settings.checkoutOptions.loc.saved+'</div>').animate({
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

        $("#checkout-steps").sortable({
            distance: 5,
            helper: function(e, tr) {
                var $originals = tr.children();
                var $helper = tr.clone();
                $helper.children().each(function(index){
                    // Set helper cell sizes to match the original sizes
                    $(this).width($originals.eq(index).width())
                });
                return $helper;
            },
            handle: '.checkout-steps-handle',
            items: 'tr',
            cancel: '.disabled',
            opacity: 0.75,
            tolerance: 'pointer',
            stop: function (event, ui) {
                var tr = $(ui.item);
                var id = tr.data('step-id');
                var prev = tr.prev('tr');
                if (prev.length) {
                    prev = prev.data('step-id');
                } else {
                    prev = '';
                }
                $.post("?module=settingsCheckoutSave&action=move" , {step_id: id, prev_id: prev}, function () {
                }, "json");
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
            $('<div></div>').appendTo('body').load(url, function() {
                $('#s-field-values').waDialog({
                    disableButtonsOnSubmit: true,

                    onLoad: function() {
                        var d = $(this);
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
                    },

                    onSubmit: function(d) {
                        var self = $(this);
                        var data = self.serializeArray();

                        // Validation
                        var validation_passed = true;
                        self.find('.errormsg').remove();
                        self.find('.error').removeClass('error');
                        self.find('[name^="parent_value["]:not(:disabled)').each(function() {
                            if (!this.value) {
                                validation_passed = false;
                                $(this).addClass('error').after($('<em class="errormsg"></em>').text($.settings.checkoutOptions.loc.field_is_required));
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
                        $.shop.jsonPost(self.attr('action'), data, function(r) {
                            d.trigger('close');
                            var wrapper = input_hide_unmatched.closest('.field-advanced-settings');
                            wrapper.find('.show-when-modified').show();
                            wrapper.find('.hide-when-modified').hide();
                        });

                        return false;
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