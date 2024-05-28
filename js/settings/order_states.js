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
    orderStatesInit: function(options) {

        // payment_allowed checkbox
        const $wrapper = $('#s-save-order-state');
        const $section = $wrapper.find(".s-payment-section");
        const $textarea = $section.find(".js-textarea");
        const $hidden = $section.find(".js-hidden");
        const $status = $wrapper.find('input[name="name"]');
        const $submitButton = $('.js-settings-order-states-submit');
        let deleteStateDialogOpened = false;
        const formChanged = () => $submitButton.removeClass('green').addClass('yellow');

        if ('' === $status.val().trim()) {
            $status.focus();
        }

        $(':input').on('input', formChanged);
        $wrapper.on('change', formChanged);

        // choose status dropdown
        $('.js-settings-order-states').waDropdown({
            update_title: false
        })

        if (options.id === 'new_state') {
          $('.js-delete-state').hide();
        }

        $section.on("change", ".js-checkbox", function () {
            var $checkbox = $(this),
                is_active = $checkbox.is(':checked');

            if (is_active) {
                $hidden.hide();
                $textarea.attr('disabled', true);
            } else {
                $textarea.attr('disabled', false);
                $hidden.show();
            }
        });

        // State border color picker
        initColorPicker($wrapper);

        // Icon selector
        const $icons = $('.js-select-icon');
        $icons.off('click').on('click', function(event) {
            event.preventDefault();
            $(this).closest('li').addClass('selected').siblings().removeClass('selected');
            formChanged();
        });

        // Link to delete custom state
        $('.js-delete-state').unbind('click').bind('click', function(e) {
            if (deleteStateDialogOpened) {
                return;
            }
            e.preventDefault();
            deleteStateDialogOpened = true;
            const url = $(this).data('href');
            $.waDialog.confirm({
                title: $_('This will delete this order state. Are you sure?'),
                success_button_title: $.wa.locale['Delete'],
                success_button_class: 'danger',
                cancel_button_title: $.wa.locale['Cancel'],
                cancel_button_class: 'light-gray',
                onSuccess(dialog) {
                      $.post(url, { id: options.id }, function(r) {
                        if (r.status !== 'ok') {
                            alert(r.errors);
                            $.waDialog.alert({
                                title: $.wa.locale['An error occurred'],
                                text: r.errors,
                                button_title: $.wa.locale['Close'],
                                button_class: 'warning',
                            });

                            return;
                        }

                        dialog.close();
                        deleteStateDialogOpened = false;
                        const $dropdown = $('.s-settings-order-states');
                        const $selected = $dropdown.find('li.selected');
                        const $prev = $selected.prev('.dr');

                        if ($prev.length) {
                            $.settings.dispatch($prev.find('a').attr('href'),  true);
                        } else {
                            const next = $selected.next('.dr');
                            if (next.length) {
                                $.settings.dispatch(next.find('a').attr('href'),  true);
                            } else {
                                $.settings.dispatch('#/orderStates/',  true);
                                $dropdown.find('li:not(.dr):first').addClass('selected');
                            }
                        }
                    }, "json");
                },
                onCancel() {
                    deleteStateDialogOpened = false;
                }
            });
        });

        // Hide/Show the sort icon if the action is disabled/enabled
        $('.s-order-action input[type="checkbox"]').on('change', function() {
            const elem = $(this);

            if (elem.is(':checked')) {
                elem.closest('.s-order-action').removeClass('unsortable');
                elem.closest('.s-order-action').find('.js-sort').show();
            } else {
                elem.closest('.s-order-action').addClass('unsortable');
                elem.closest('.s-order-action').find('.js-sort').hide();
            }
        });

        // Link to add new action
        const $templateNew = $('.s-new-action');
        $('.js-add-action').unbind('click').bind('click', function(event) {
            event.preventDefault();

            const $new_action_block = $templateNew.clone().show().removeClass('s-new-action').insertBefore($templateNew);
            $new_action_block.find(':input').attr('disabled', false);
            initColorPicker($new_action_block);
            initActionIcons($new_action_block);

            // Update template increasing indices in field names
            $templateNew.find(':input').each(function() {
                const item = $(this);
                const name = item.attr('name');
                if (!name) {
                  return;
                }
                const match = name.match(/^(.*)\[(\d+)\]$/);
                if (match) {
                    const index = parseInt(match[2], 10) + 1 || 1;
                    item.attr('name', match[1] + '['+index+']');
                }
            });
        });

        // Links to edit custom actions
        const $templateAction = $('.s-new-action');
            $wrapper.off('click', '.s-edit-action').on('click', '.s-edit-action', function(event) {
            event.preventDefault();

            const $edit_link = $(this);
            const $block = $edit_link.closest('.s-order-action');
            const action_id = $block.data('id');

            // Second click closes editor form
            const $wrapper = $edit_link.nextAll('.s-edit-action-block[data-id="' + action_id + '"]:first');
            if ($wrapper.length) {
                $wrapper.remove();
                return;
            }

            if (action_id !== 'message') {
                // Render action settings form
                const $actionWrapper = $templateAction.clone().show().appendTo($block)
                    .attr('data-id', action_id)
                    .removeClass('s-new-action')
                    .addClass('s-edit-action-block');

                // Update field names
                $actionWrapper.find(':input').each(function () {
                    const item = $(this).attr('disabled', false);
                    const name = item.attr('name');
                    if (!name) {
                      return;
                    }
                    const match = name.match(/^(.*)\[(\d+)\]$/);
                    if (match) {
                        item.attr('name', match[1].replace('new_action', 'edit_action') + '[' + action_id + ']');
                        const type = match[1].replace('new_action_', '');
                        const value = $block.data(type);
                        if (item.is(':radio')) {
                            item.prop('checked', item.val() == value);
                        } else {
                            item.val(value);
                        }
                    }
                });

                if ($block.data('extends') === false) {
                    $actionWrapper.find(':input[name^="edit_action_extends\["]:first').parents('div.field:first').remove();
                }

                // Replace id field with read-only text
                const id_text = $block.find('[name="edit_action_id[' + action_id + ']"]').val();
                $block.find('[name="edit_action_id[' + action_id + ']"]').replaceWith('<div class="small text-gray custom-pl-4">' +
                    id_text +
                    '<input type="hidden" name="edit_action_id[' + action_id + ']" value="' + id_text + '"></div>');

                // Link to delete custom action
                $block.find('.js-delete-action').removeClass('hidden').find('a').on('click', function(event) {
                    event.preventDefault();

                    $.waDialog.confirm({
                        title: $_('Order action will be deleted. Are you sure?'),
                        success_button_title: $.wa.locale['Delete'],
                        success_button_class: 'danger',
                        cancel_button_title: $.wa.locale['Cancel'],
                        cancel_button_class: 'light-gray',
                        onSuccess(dialog) {
                             $.post('?module=settings&action=orderActionDelete', {
                                id: action_id
                            }, function () {
                                dialog.close();
                                $.settings.redispatch();
                            });
                        }
                    });
                });

                initColorPicker($block);
                initActionIcons($block);
            }
        });

        // Settings form for built-in Message action
        const $wrapperEditor = $('#s-message-action-editor');

        // Link to show form
        $('#s-edit-message-action').on('click', function(event) {
            event.preventDefault();

            $(this).closest('.s-order-action').append($wrapperEditor.slideToggle(200));
        });

        // Link to show template vars helper
        $wrapperEditor.find('.template-vars-link').on('click', function(event) {
            event.preventDefault();

            $wrapperEditor.find('.template-vars-wrapper').slideToggle(200);
        });

        if (!$.isEmptyObject(options.edit_actions_map)) {
            let offset_top = null;

            $wrapper.find('.s-edit-action').each(function () {
                const el = $(this);
                const id = el.data('id');

                if (options.edit_actions_map[id]) {
                    if (offset_top === null) {
                        offset_top = el.offset().top;
                    }
                    offset_top = Math.min(offset_top, el.offset().top);
                    el.click();
                }
            });

            if (offset_top !== null) {
                setTimeout(function () {
                    $(window).scrollTop(offset_top - 10);
                }, 250);

            }
        }

        // Initialize drag-and-drop of states in dropdown
        orderStatesSortableInit();

        // Initialize sortable of available actions
        orderActionsSortableInit();

        // Form submit handler
        let is_locked = false;

        const formSerialize = () => {
            const data = $wrapper.serializeArray();
            const selected = $('.s-icons .selected');

            if (selected.length) {
                data.push({
                  name: 'icon', value: 'icon16 ss ' + selected.data('icon')
                });
            }

            return data;
        };

        $wrapper.on('submit', function(event) {
            event.preventDefault();

            const data = formSerialize();

            if (is_locked) {
                return;
            }

            // Disable submit button
            $submitButton.attr("disabled", true);
            is_locked = true;

            // send post
            showLoadingIcon();

            $.shop.jsonPost($wrapper.attr('action'), data, function (r) {
                clearErrors();

                // One-time callback after URL-hash-based dispatching.
                $(document).one('order_states_init', showSuccessIcon);

                if (r.data.add) {
                    $.settings.dispatch('#/orderStates/' + r.data.id + '/', true);
                } else if (r.data.new_id) {
                    $.settings.dispatch('#/orderStates/' + r.data.new_id + '/', true);
                } else {
                    $.settings.redispatch();
                }

                // Enable submit button
                $submitButton.attr("disabled", false);
                is_locked = false;
            },
            function (r) {
                if (r && r.errors) {
                    clearErrors();

                    // State-related errors
                    if (!$.isEmptyObject(r.errors.state)) {
                        for (let name in r.errors.state) {
                            const input = $wrapper.find('input[name=' + name + ']');
                            input.addClass('state-error').after('<div class="state-error">' + r.errors.state[name] + '</div>');
                        }
                    }

                    // Action-related errors
                    if (!$.isEmptyObject(r.errors.actions)) {

                        // Mark inputs with action id to simplify filtering
                        $wrapper.find('input[name^=new_action_id]').each(function () {
                            const input = $(this);
                            input.attr('data-action-id', input.val().toLowerCase());
                        });

                        // Highlight fields with errors
                        for (let id in r.errors.actions) {
                            const input = $wrapper.find('input[name^=new_action_id][data-action-id=' + id + ']');
                            input.parent().find('input').addClass('state-error');
                            input.after('<div class="errormsg">' + r.errors.actions[id] + '</div>');
                        }
                    }

                    // Enable submit button
                    $submitButton.attr("disabled", false);
                    is_locked = false;
                    showErrorIcon();
                }
            });

            function clearErrors() {
                $wrapper.find('input.state-error').removeClass('state-error');
                $wrapper.find('.state-error').remove();
            }

            function showErrorIcon() {
                const $icon = $submitButton.find('.no').show();

                $submitButton.find('.loading').hide();
                setTimeout(function () {
                    $icon.hide();
                }, 3000);
            }

            function showSuccessIcon() {
                const $icon = $submitButton.find('.yes').show();

                setTimeout(function () {
                    $icon.hide();
                }, 3000);
            }

            function showLoadingIcon() {
                $submitButton.find('.loading').show();
                $submitButton.find('.yes').hide();
                $submitButton.find('.no').hide();
            }
        }); // end of form submit handler

        const changeListener = (handler) => {
            const ns = '.shop-settings-order-states';

            $('.s-settings-form')
                .off(ns)
                .on('change' + ns, '[name="action[]"]', handler)
                .on('change' + ns, '.s-action-icon', handler)
                .on('change' + ns, '[name^="new_action["]', handler)
                .on('click' + ns, ':radio[name^="edit_action_link["]', handler)
                .on('click' + ns, ':radio[name^="new_action_link["]', handler)
            ;

            $.shop.changeListener($('.s-settings-form'), '[name^="edit_action_border_color["]', handler, ns);
            $.shop.changeListener($('.s-settings-form'), '[name^="new_action_border_color["]', handler, ns);
            $.shop.changeListener($('.s-settings-form'), '[name^="edit_action_name["]', handler, ns);
            $.shop.changeListener($('.s-settings-form'), '[name^="new_action_name["]', handler, ns);

            $('.s-order-allowed-actions').unbind(ns).bind('change' + ns, handler);
        };

        changeListener(function () {
            $submitButton.removeClass('green').addClass('yellow');
        });

        // init buttons preview
        (function () {
            $('.s-settings-order-states').on('click', '.wf-action, .show-alert', function (e) {
                e.preventDefault();

                $.waDialog.alert({
                  title: $_('This is a preview of actions available for orders in this state'),
                  button_title: $.wa.locale['ok'],
                  button_class: 'warning',
                });
            });

            const updatePreview = function fn() {
                fn.xhr && fn.xhr.abort();
                const url = '?module=settings&action=orderStates&id=' + options.id;
                const place = $('.s-workflow-action-buttons-preview');
                const tmp = $('<div style="display: none;">').insertAfter(place);
                const data = formSerialize();
                fn.xhr = $.post(url, data)
                    .done(
                        function (html) {
                            const t = $('<div>').html(html);
                            const new_preview = t.find('.s-workflow-action-buttons-preview');
                            t.remove();
                            tmp.html(new_preview.html());
                            const new_height = tmp.show().height();
                            place.height(place.height());       // fix height
                            place.html(tmp.html());
                            tmp.remove();
                            place.animate({
                                height: new_height
                            });
                        }
                    )
                    .always(function () {
                        fn.xhr = null;
                    });
            };
            const updatePreviewRadio = function () {
                const el = $(this);
                if (el.is(':checked')) {
                    updatePreview();
                }
            };

            changeListener(function () {
                $submitButton.removeClass('green').addClass('yellow');
                ($(this).is(':radio') ? updatePreviewRadio : updatePreview).call(this);
            });

        })();

        $(document).trigger('order_states_init');

        // Helper to init icon selector in custom action form
        function initActionIcons($block) {
            const $input = $block.find('.s-action-icon');
            const $color_picker = $block.find('.js-color-picker');

            const $icons = $block.find('.s-action-icons').on('click', 'a', function(event) {
                event.preventDefault();

                $(this).closest('li').addClass('selected').siblings().removeClass('selected');
                $input.val($icons.find('.selected').data('icon'));
                $input.trigger('change');
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
                $color_picker.hide();
            }
            function hideIcons() {
                $icons.hide();
                $input.attr('disabled', true);
                $color_picker.show();
            }
        }

        function orderStatesSortableInit() {
          const $ul = $('#s-settings-order-states-list');

          $ul.sortable({
            group: 'order-states-list',
            handle: '.js-sort-handle',
            animation: 100,
            removeCloneOnHide: true,
            onEnd: function (evt) {
              const $li = $(evt.item);
              const id = $li.attr('id').replace('state-', '');

              if (id) {
                let before_id = '';
                const $next = $li.nextAll('li.dr:first');
                if ($next.length) {
                  before_id = $next.attr('id').replace('state-', '');
                }
                $.shop.jsonPost('?module=settings&action=orderStateMove', { id: id, before_id: before_id }, null,
                  function (r) {
                    $ul.sortable('cancel');
                  }
                );
              }
            },

          });
        }

        function orderActionsSortableInit() {
            const $block = $('.s-order-allowed-actions');

            $block.sortable($block, {
                handle: '.js-sort',
                draggable: '.s-order-action:not(.unsortable)',
                // ghostClass: '.sortable-ghost',
                animation: 100,
                removeCloneOnHide: true
            });

        }

      function initColorPicker($block) {
        const el = $block.find('.js-color-picker')[0];
        const $colorPicker = $(el);

        let initColor = $colorPicker.next('.js-color-value').val();
        if (initColor) {
          initColor = '#' + initColor;
          $colorPicker.css('background-color', initColor);
          $colorPicker.attr('data-color', initColor);
        }

        const getColorPicker = (pickr) => {
          if (pickr.hasOwnProperty('toHEXA')) {
            return pickr.toHEXA().toString(0);
          }

          return pickr.target.value;
        }

        $colorPicker.on('click', (event) => {
          event.preventDefault();

          const defaultColor = $colorPicker.attr('data-color');

          const colorPicker = Pickr.create({
            el,
            theme: 'classic',
            swatches: [
              '#ed2509',
              '#22d13d',
              '#1a9afe',
              '#f3c200',
              '#ff6c00',
              '#7256ee',
              '#996e4d',
              '#ff7a99',
              '#fff',
              '#000',
              '#89a',
              'rgba(0, 20, 65, 0.2)',
              '#568',

            ],
            appClass: 'wa-pcr-app small',
            lockOpacity: false,
            position: 'bottom-middle',
            useAsButton: true,
            default: defaultColor,
            components: {
              palette: true,
              hue: true,
              interaction: {
                input: true,
                save: true
              }
            },
            i18n: {
              'btn:save': 'OK',
            }
          }).on('change', (pickr) => {
            const color_hex = getColorPicker(pickr);
            $colorPicker.css('background-color', color_hex);
            $colorPicker.attr('data-color', color_hex);

          }).on('save', (color, pickr) => {
            $colorPicker.next('.js-color-value').val(getColorPicker(color).toLowerCase().slice(1));
            pickr.hide();
            formChanged();

          }).on('hide', (pickr) => {
            const color_hex = '#' + $colorPicker.next('.js-color-value').val();
            $colorPicker.css('background-color', color_hex);
            $colorPicker.attr('data-color', color_hex);

            pickr.setColor(color_hex);
            pickr.destroyAndRemove();
          });

          colorPicker.show();
        });
      }

    } // end of orderStatesInit()

});
