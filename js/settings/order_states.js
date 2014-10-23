/**
 *
 * @names  orderStatuses*
 * @method orderStatusesInit
 * @method orderStatusesAction
 * @method orderStatusesBlur
 */
$.extend($.settings = $.settings || {}, {
    /**
     * {Object}
     */
    order_states_options: {},

    orderStatesInit: function(options) {
        this.order_states_options = options;

        var color_picker = $('#s-colorpicker');
        var color_replacer = $('.s-color-replacer');
        var color_input = $('#s-color');
        var farbtastic = $.farbtastic(color_picker, function(color) {
            color_replacer.find('i').css('background', color);
            color_input.val(color.substr(1));
            color_input.css('color', color);
        });
        farbtastic.setColor('#'+color_input.val());
        $('.s-color-replacer').click(function() {
            color_picker.slideToggle(200);
            return false;
        });

        var timer_id;
        color_input.unbind('keydown').bind('keydown', function() {
            if (timer_id) {
                clearTimeout(timer_id);
            }
            timer_id = setTimeout(function() {
                farbtastic.setColor('#'+color_input.val());
            }, 250);
        });

        var icons = $('.s-icons');
        icons.off('click', 'a').on('click', 'a', function() {
            icons.find('.selected').removeClass('selected');
            $(this).parents('li:first').addClass('selected');
            return false;
        });

        var sidebar = $('.s-settings-order-states.sidebar');
        $('#s-delete-state').unbind('click').bind('click', function() {
            if (confirm($_('This will delete this order state. Are you sure?'))) {
                var self = $(this);
                $.post(self.attr('href'), { id: options.id }, function(r) {
                    if (r.status == 'ok') {
                        var selected = sidebar.find('li.selected');
                        var prev = selected.prev('.dr');
                        if (prev.length) {
                            $.settings.dispatch(prev.find('a').attr('href'),  true);
                        } else {
                            var next = selected.next('.dr');
                            if (next.length) {
                                $.settings.dispatch(next.find('a').attr('href'),  true);
                            } else {
                                $.settings.dispatch('#/orderStates/',  true);
                                sidebar.find('li:not(.dr):first').addClass('selected');
                            }
                        }
                    } else if (r.status == 'fail') {
                        alert(r.errors);
                    }
                }, "json");
            }
            return false;
        });

        $('#s-add-action').unbind('click').bind('click', function() {
            var new_action = $('.s-new-action');
            var clone = new_action.clone();

            new_action.find('input, select').each(function() {
                var item = $(this);
                var name = item.attr('name');
                var match = name.match(/^(.*)\[(\d+)\]$/);
                if (match) {
                    var index = parseInt(match[2], 10) + 1 || 1;
                    item.attr('name', match[1] + '['+index+']');
                }
            });
            clone.find('input, select').attr('disabled', false);
            new_action.before(clone.show());
            clone.removeClass('s-new-action');
            return false;
        });

        var form = $('#s-save-order-state');
        form.submit(function() {
            var self = $(this);
            var data = self.serializeArray();
            var icon = $('.s-icons .selected i');
            if (icon.length) {
                data.push({
                    name: 'icon', value: icon.attr('class')
                });
            }
            form.find('input[name^=new_action_id]').each(function() {
                var input = $(this);
                input.attr('data-action-id', input.val().toLowerCase());
            });
            var clear = function() {
                form.find('input.error').removeClass('error');
                form.find('.errormsg').remove();
            };
            
            var showSuccessIcon = function() {
                var icon = $('#s-settings-order-states-submit').parent().find('i.yes').show();
                setTimeout(function() {
                    icon.hide();
                }, 3000);
            };
            var showLoadingIcon = function() {
                var p = $('#s-settings-order-states-submit').parent();
                p.find('i.yes').hide();
                p.find('i.loading').show();
            };
            
            // after update services hash, dispathing and load proper content
            // 'afterOrderStatesInit' will be called. Extend this handler
            var prevHandler = $.settings.afterOrderStatesInit;
            $.settings.afterOrderStatesInit = function() {
                showSuccessIcon();
                if (typeof prevHandler == 'function') {
                    prevHandler();
                }
                $.settings.afterOrderStatesInit = prevHandler;
            };
            
            // send post
            showLoadingIcon();
            $.shop.jsonPost(self.attr('action'), data,
                function(r) {
                    clear();
                    $.settings.dispatch('#/orderStates/'+r.data.id+'/',  true);
                },
                function(r) {
                    if (r && r.errors) {
                        clear();
                        if (!$.isEmptyObject(r.errors.state)) {
                            for (var name in r.errors.state) {
                                var input = form.find('input[name='+name+']');
                                input.addClass('error').after('<em class="errormsg">'+r.errors.state[name]+'</em>');
                            }
                        }
                        if (!$.isEmptyObject(r.errors.actions)) {
                            for (var id in r.errors.actions) {
                                var input = form.find('input[name^=new_action_id]').filter('[data-action-id='+id+']');
                                input.parent().find('input').addClass('error');
                                input.after('<em class="errormsg">'+r.errors.actions[id]+'</em>');
                            }
                        }
                    }
                }
            );
            return false;
        });

        this.orderStatesSortableInit();

        if (typeof $.settings.afterOrderStatesInit == "function") {
            $.settings.afterOrderStatesInit();
        }
    },

    orderStatesAction: function(path) {
        if ($.settings.path.tail !== null && $.settings.path.tail != path) {
            if (path) {
                this.dispatch('#/orderStates/'+path+'/',  true);
            } else {
                this.dispatch('#/orderStates/',  true);
            }
        }
    },

    orderStatesSortableInit: function() {
        var containment = $('.s-settings-order-states.sidebar');
        var ul = containment.find('ul:first');
        ul.sortable({
            distance: 5,
            helper: 'clone',
            items: 'li.dr',
            opacity: 0.75,
            tolerance: 'pointer',
            update: function(event, ui) {
                var li = ui.item;
                var id = li.attr('id').replace('state-', '');
                var next, before_id = '';
                if (id) {
                    next = li.nextAll('li.dr:first');
                    if (next.length) {
                        before_id = next.attr('id').replace('state-', '');
                    }
                    $.shop.jsonPost('?module=settings&action=orderStateMove', { id: id, before_id: before_id }, null,
                        function(r) {
                            ul.sortable('cancel');
                        }
                    );
                }
            }
        });
    }
});