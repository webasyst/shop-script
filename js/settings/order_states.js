/**
 *
 * @names  orderStatuses*
 * @method orderStatusesInit
 * @method orderStatusesAction
 * @method orderStatusesBlur
 */
$.extend($.settings || {}, {

    // Called by dispatch when url hash changes
    orderStatesAction: function(path) {
        if ($.settings.path.tail !== null && $.settings.path.tail != path) {
            if (path) {
                this.dispatch('#/orderStates/'+path+'/',  true);
            } else {
                this.dispatch('#/orderStates/',  true);
            }
        }
    },

    // Called from SettingsOrderStates.html template
    orderStatesInit: function(options) { "use strict";

        // State border color picker
        (function() {
            var $color_input = $('#s-color').addClass('s-color');
            var farbtastic = initColorPicker($('#s-colorpicker').addClass('s-colorpicker').parent());

            // Update state border color picker when user changes its text input
            var timer_id;
            $color_input.unbind('keydown').bind('keydown', function() {
                timer_id && clearTimeout(timer_id);
                timer_id = setTimeout(function() {
                    farbtastic.setColor('#'+$color_input.val());
                }, 250);
            });
        })();

        // Icon selector
        (function() {
            var $icons = $('.s-icons');
            $icons.off('click', 'a').on('click', 'a', function() {
                $icons.find('.selected').removeClass('selected');
                $(this).parents('li:first').addClass('selected');
                return false;
            });
        })();

        // Link to delete custom state
        $('#s-delete-state').unbind('click').bind('click', function() {
            if (confirm($_('This will delete this order state. Are you sure?'))) {
                var $self = $(this);
                $.post($self.attr('href'), { id: options.id }, function(r) {
                    if (r.status == 'ok') {
                        var $sidebar = $('.s-settings-order-states.sidebar');
                        var $selected = $sidebar.find('li.selected');
                        var $prev = $selected.prev('.dr');
                        if ($prev.length) {
                            $.settings.dispatch($prev.find('a').attr('href'),  true);
                        } else {
                            var next = $selected.next('.dr');
                            if (next.length) {
                                $.settings.dispatch(next.find('a').attr('href'),  true);
                            } else {
                                $.settings.dispatch('#/orderStates/',  true);
                                $sidebar.find('li:not(.dr):first').addClass('selected');
                            }
                        }
                    } else if (r.status == 'fail') {
                        alert(r.errors);
                    }
                }, "json");
            }
            return false;
        });

        // Link to add new action
        $('#s-add-action').unbind('click').bind('click', function() {
            var $template = $('.s-new-action');
            var $new_action_block = $template.clone().show().removeClass('s-new-action').insertBefore($template);
            $new_action_block.find(':input').attr('disabled', false);
            initColorPicker($new_action_block);
            initActionIcons($new_action_block);

            // Update template increasing indices in field names
            $template.find(':input').each(function() {
                var item = $(this);
                var name = item.attr('name');
                var match = name.match(/^(.*)\[(\d+)\]$/);
                if (match) {
                    var index = parseInt(match[2], 10) + 1 || 1;
                    item.attr('name', match[1] + '['+index+']');
                }
            });

            return false;
        });

        // Links to edit custom actions
        $('#s-save-order-state').off('click', '.s-edit-action').on('click', '.s-edit-action', function() {
            var $edit_link = $(this);
            var $block = $edit_link.closest('.s-order-action');
            var action_id = $block.data('id');

            // Second click closes editor form
            var $wrapper = $edit_link.nextAll('.s-edit-action-block[data-id="' + action_id + '"]:first');
            if ($wrapper.length) {
                $wrapper.remove();
                return false;
            }

            // Render action settings form
            var $template = $('.s-new-action');
            $wrapper = $template.clone().show().appendTo($block)
                .css('margin-left', 0)
                .attr('data-id', action_id)
                .removeClass('s-new-action')
                .addClass('s-edit-action-block');

            // Update field names
            $wrapper.find(':input').each(function() {
                var item = $(this).attr('disabled', false);
                var name = item.attr('name');
                var match = name.match(/^(.*)\[(\d+)\]$/);
                if (match) {
                    item.attr('name', match[1].replace('new_action', 'edit_action') + '[' + action_id + ']');
                    var type = match[1].replace('new_action_', '');
                    if (item.is(':radio')) {
                        item.attr('checked', item.val() == $block.data(type));
                    } else {
                        item.val($block.data(type));
                    }
                }
            });

            // Replace id field with read-only text
            var id_text = $block.find('[name="edit_action_id[' + action_id + ']"]').val();
            $block.find('[name="edit_action_id[' + action_id + ']"]').replaceWith('<span>' +
                    id_text +
                '<input type="hidden" name="edit_action_id[' + action_id + ']" value="' + id_text + '"></span>');

            // Link to delete custom action
            $block.find('.s-delete-action').show().find('a').click(function() {
                if (confirm($_('Order action will be deleted. Are you sure?'))) {
                    $.post('?module=settings&action=orderActionDelete', {
                        id: action_id
                    }, function() {
                        $.settings.redispatch();
                    });
                }
                return false;
            });

            initColorPicker($block);
            initActionIcons($block);

            return false;
        });

        // Link to modify id of existing custom state
        $('#c-state-id-edit').click(function() {
            $(this).closest('.hint').hide();
            $('#c-state-id').show().attr('disabled', false);
        });

        // Settings form for built-in Message action
        (function() {
            var $wrapper = $('#s-message-action-editor');

            // Link to show form
            $('#s-edit-message-action').click(function() {
                var $edit_link = $(this);
                $edit_link.closest('.value').after($wrapper.slideToggle(200));
            });

            // Link to show template vars helper
            $wrapper.find('.template-vars-link').click(function() {
                $wrapper.find('.template-vars-wrapper').slideToggle(200);
            });
        })();

        // Initialize drag-and-drop of states in sidebar
        orderStatesSortableInit();

        // Form submit handler
        var $form = $('#s-save-order-state');
        $form.submit(function() {
            var data = $form.serializeArray();

            // Selected icon
            var icon = $('.s-icons .selected i');
            if (icon.length) {
                data.push({
                    name: 'icon', value: icon.attr('class')
                });
            }

            // send post
            showLoadingIcon();
            $.shop.jsonPost($form.attr('action'), data,
                function(r) {
                    clearErrors();

                    // One-time callback after URL-hash-based dispatching.
                    $(document).one('order_states_init', showSuccessIcon);

                    if (r.data.add) {
                        $.settings.dispatch('#/orderStates/'+r.data.id+'/',  true);
                    } else if (r.data.new_id) {
                        $.settings.dispatch('#/orderStates/'+r.data.new_id+'/',  true);
                    } else {
                        $.settings.redispatch();
                    }
                },
                function(r) {
                    if (r && r.errors) {
                        clearErrors();

                        // State-related errors
                        if (!$.isEmptyObject(r.errors.state)) {
                            for (var name in r.errors.state) {
                                var input = $form.find('input[name='+name+']');
                                input.addClass('error').after('<em class="errormsg">'+r.errors.state[name]+'</em>');
                            }
                        }

                        // Action-related errors
                        if (!$.isEmptyObject(r.errors.actions)) {

                            // Mark inputs with action id to simplify filtering
                            $form.find('input[name^=new_action_id]').each(function() {
                                var input = $(this);
                                input.attr('data-action-id', input.val().toLowerCase());
                            });

                            // Highlight fields with errors
                            for (var id in r.errors.actions) {
                                var input = $form.find('input[name^=new_action_id][data-action-id='+id+']');
                                input.parent().find('input').addClass('error');
                                input.after('<em class="errormsg">'+r.errors.actions[id]+'</em>');
                            }
                        }
                    }
                }
            );

            return false;

            function clearErrors() {
                $form.find('input.error').removeClass('error');
                $form.find('.errormsg').remove();
            }

            function showSuccessIcon() {
                var $icon = $('#s-settings-order-states-submit').parent().find('i.yes').show();
                setTimeout(function() {
                    $icon.hide();
                }, 3000);
            }

            function showLoadingIcon() {
                var $p = $('#s-settings-order-states-submit').parent();
                $p.find('i.loading').show();
                $p.find('i.yes').hide();
            }
        }); // end of form submit handler

        $(document).trigger('order_states_init');
        return;

        function initColorPicker($block) {
            var $color_input = $('.s-color', $block);
            var $color_picker = $('.s-colorpicker', $block);
            var $color_replacer = $('.s-color-replacer', $block);
            var farbtastic = $.farbtastic($color_picker, function(color) {
                $color_replacer.find('i').css('background', color);
                $color_input.val(color.substr(1));
                $color_input.css('color', color);
            });
            farbtastic.setColor('#'+$color_input.val());
            $color_replacer.click(function() {
                $color_picker.slideToggle(200);
                return false;
            });
            return farbtastic;
        }

        // Helper to init icon selector in custom action form
        function initActionIcons($block) {
            var $input = $block.find('.s-action-icon');
            var $icons = $block.find('.s-action-icons').on('click', 'a', function() {
                $icons.find('.selected').removeClass('selected');
                $(this).parents('li:first').addClass('selected');
                $input.val($icons.find('.selected').data('icon'));
                return false;
            });
            $icons.find('li[data-icon="' + $input.val() + '"]').addClass('selected');
            $block.find('.s-action-link').click(showIcons);
            $block.find('.s-action-button').click(hideIcons);
            if ($block.find('.s-action-link').is(':checked')) {
                showIcons();
            }

            function showIcons() {
                $icons.show();
                $input.attr('disabled', false);
            }
            function hideIcons() {
                $icons.hide();
                $input.attr('disabled', true);
            }
        }

        function orderStatesSortableInit() {
            var $ul = $('.s-settings-order-states.sidebar').find('ul:first');
            $ul.sortable({
                distance: 5,
                helper: 'clone',
                items: 'li.dr',
                opacity: 0.75,
                tolerance: 'pointer',
                update: function(event, ui) {
                    var $li = ui.item;
                    var id = $li.attr('id').replace('state-', '');
                    if (id) {
                        var before_id = '';
                        var $next = $li.nextAll('li.dr:first');
                        if ($next.length) {
                            before_id = $next.attr('id').replace('state-', '');
                        }
                        $.shop.jsonPost('?module=settings&action=orderStateMove', { id: id, before_id: before_id }, null,
                            function(r) {
                                $ul.sortable('cancel');
                            }
                        );
                    }
                }
            });
        }

    } // end of orderStatesInit()

});