if (typeof($) != 'undefined') {

    $.extend($.settings = $.settings || {}, {

        /**
         * Init section
         *
         * @param string tail
         */
        searchInit: function() {
            var form = $('#s-settings-form');

            var form_changed = false;
            var submit_message = $('#submit-message');
            var formChanged = function() {
                if (!form_changed) {
                    form_changed = true;
                    $(':submit', form).removeClass('green').addClass('yellow');
                    submit_message.show();
                }
            };

            form.submit(function() {
                var self = $(this);
                $.post(self.attr('action'), self.serialize(), function() {
                    if (form_changed) {
                        form_changed = false;
                        $(':submit', form).removeClass('yellow').addClass('green');
                        submit_message.show().fadeOut(5000);
                    }
                    $('.s-msg-after-button').show().animate({ opacity: 0 }, 2000, function() {
                        $(this).hide();
                    });
                });
                return false;
            });

            $('#s-toggle-status').iButton( { labelOn : "", labelOff : "", className: 'mini' } ).change(function() {
                var self = $(this);
                var enabled = self.is(':checked');
                if (enabled) {
                    $('#smart-disabled').hide();
                    $('#s-toggle-enabled-label').removeClass('gray');
                    $('#s-toggle-disabled-label').addClass('gray');
                    $('#smart-enabled').show(200);
                } else {
                    $('#smart-enabled').hide();
                    $('#s-toggle-enabled-label').addClass('gray');
                    $('#s-toggle-disabled-label').removeClass('gray');
                    $('#smart-disabled').show(200);
                }
                $.post('?module=settings&action=searchSave', { smart: enabled ? '1' : '0' });
            });

            $('#search_by_part').change(function () {
               if ($(this).is(':checked')) {
                   $('#search_by_part_div').show().find('input').removeAttr('disabled');
               }  else {
                   $('#search_by_part_div').hide().find('input').attr('disabled', 'disabled');
               }
            });
            
            $('input[name^=weights]:text', form).each(function() {
                var item = $(this).hide();

                var field_name = item.attr('name').replace(/(weights|\[|\])/g, '');
                var slider = $('<div style="margin-top: 10px" class="weight" data-field="' + field_name + '" title=""></div>').insertAfter(item);
                var span = item.parent().find('.weight-value').html(item.val());
                slider.slider({
                    min: 0,
                    max: 100,
                    slide: function(event, ui) {
                        $(this).attr('title', ui.value);
                        span.html(ui.value);
                    },
                    start: function() {
                        span.closest('.value').find('.default-weight').show();
                    },
                    stop: function() {
                        span.closest('.value').find('.default-weight').hide();
                    },
                    change: function(event, ui) {
                        $('input[name="weights['+$(this).data('field')+']"]').val(ui.value);
                        formChanged();
                    },
                    value: item.val()
                });
            });
            
            $(':input', form).change(formChanged);
            
            $('#s-reindex').click(function() {
                var dialog = $('#s-reindex-dialog');
                var pull = [];
                dialog.waDialog({
                    onLoad: function() {
                        var d = $(this);
                        $('#s-reindex-success').hide();
                        $('#s-reindex-progressbar').hide();
                        $("#s-reindex-report").hide();
                        d.find('.dialog-buttons').show();
                        d.removeClass('height400px').addClass('height300px');
                        dialog.find('input[name=create_thumbnails]').attr('disabled', false);                        
                    },
                    onSubmit: function(d) {
                
                        d.find('.dialog-buttons').hide();
                        d.removeClass('height300px').addClass('height400px');
                        var form = $(this);
                        
                        $('#s-reindex-progressbar').show();
                        form.find('.progressbar .progressbar-inner').css('width', '0%');
                        form.find('.progressbar-description').text('0.000%');
                        form.find('.progressbar').show();
                        $("#s-reindex-report").hide();
                        
                        var url = form.attr('action');
                        var processId;
                        
                        var cleanup = function() {
                            $.post(url, { processId: processId, cleanup: 1 }, function(r) {
                                // show statistic
                                $('#s-reindex-progressbar').hide();
                                $("#s-reindex-report").show();
                                if (r.report) {
                                    $("#s-reindex-report").html(r.report);
                                    $("#s-reindex-report").find('.close').click(function() {
                                        dialog.trigger('close');
                                    });
                                }
                                dialog.removeClass('height400px').addClass('height350px');
                            }, 'json');
                        };
                        
                        var step = function(delay) {
                            delay = delay || 2000;
                            var timer_id = setTimeout(function() {
                                $.post(url, { processId:processId }, 
                                    function(r) {
                                        if (!r) {
                                            step(3000);
                                        } else if (r && r.ready) {
                                            form.find('.progressbar .progressbar-inner').css({
                                                width: '100%'
                                            });
                                            cleanup();
                                        } else if (r && r.error) {
                                            form.find('.errormsg').text(r.error);
                                        } else {
                                            if (r && r.progress) {
                                                var progress = parseFloat(r.progress.replace(/,/, '.'));
                                                form.find('.progressbar .progressbar-inner').animate({
                                                    'width': progress + '%'
                                                });
                                                form.find('.progressbar-description').text(r.progress);
                                            }
                                            if (r && r.warning) {
                                                form.find('.progressbar-description').append('<i class="icon16 exclamation"></i><p>' + r.warning + '</p>');
                                            }

                                            step();
                                            
                                        }
                                    }, 
                                'json').error(function() {
                                    step(3000);
                                });
                            }, delay);
                            pull.push(timer_id);
                        };
                        
                        $.post(url, { processId: processId }, 
                            function(r) {
                                if (r && r.processId) {
                                    processId = r.processId;
                                    step(1000);   // invoke Runner
                                    step();         // invoke Messenger
                                } else if (r && r.error) {
                                    form.find('errormsg').text(r.error);
                                } else {
                                    form.find('errormsg').text('Server error');
                                }
                            }, 
                        'json').error(function() {
                            form.find('errormsg').text('Server error');
                        });
                        
                        return false;
                    },
                    onClose: function() {
                        var timer_id = pull.pop();
                        while (timer_id) {
                            clearTimeout(timer_id);
                            timer_id = pull.pop();
                        }
                        $('#s-reindex-progressbar').hide();
                        $("#s-reindex-report").hide();
                        dialog.removeClass('height400px').addClass('height300px');
                    }
                });
                return false;
            });
            
        }
    });
}