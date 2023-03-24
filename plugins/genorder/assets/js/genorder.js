(function($) { "use strict";

    const MIN_DELAY_BETWEEN_API_CALLS = 1050; // ms

    $.genorder = {
        initGeneratorPage: function(opts) {

            var $wrapper = $(opts.wrapper_id);
            var $form = $wrapper.closest('form');
            var generator = null;
            var $generation_log = $('#generation-log');
            var $cancel_button = $('#cancel-button');

            // Clear error when user changes something in the form
            $form.on('change keyup', ':input', function() {
                $(this).removeClass('error').siblings('.errormsg').remove();
            });

            // Form submit
            $form.find('.value.submit').remove();
            $form.off('submit').submit(function() {
                if (!generator) {
                    if (!validateForm()) {
                        return false;
                    }

                    var initial_count = parseInt($('#orders-count').val());
                    if (!initial_count || initial_count <= 0) {
                        return false;
                    }

                    var $log_loading = $('<i class="icon16 loading"></i>').appendTo($generation_log.find('pre').empty());
                    var $template = $generation_log.find('.log-record.template');
                    var form_data = $form.serializeArray();
                    var error_count = 0;
                    var success_count = 0;

                    $form.find(':submit').prop('disabled', true);
                    $cancel_button.show().prop('disabled', false);
                    $generation_log.slideDown();

                    // Start generation process
                    generator = generate(initial_count, form_data);
                    generator.always(function() {
                        $cancel_button.hide().off();
                        $form.find(':submit').prop('disabled', false);
                        var $record = $template.clone().removeClass('hidden template');
                        $record.find('.text').text(opts.finish_msg.replace('%d', success_count));
                        $record.insertBefore($log_loading);
                        $log_loading.remove();
                        generator = null;

                        if (error_count > 0) {
                            $record = $template.clone().removeClass('hidden template');
                            $record.find('.text').text(opts.finish_error_msg.replace('%d', error_count));
                            $generation_log.find('pre').append($record);
                        }
                    }).progress(function(r) {
                        var $record = $template.clone().removeClass('hidden template');
                        if (r.status != 'ok') {
                            error_count++;
                            $record.addClass('errormsg');
                        } else {
                            success_count++;
                        }
                        $record.find('.text').html(r.data || r.errors);
                        $record.insertBefore($log_loading);
                    });

                    $cancel_button.click(function() {
                        generator.abort();
                    });

                }
                return false;
            });

            // Set default values for fields
            var storefront = (($('#mainmenu .s-openstorefront a')[0]||{ }).href||'').replace(/^https?:\/\//, '').replace(/\/$/, '');
            if (storefront.indexOf('/') >= 0) {
                storefront += '/';
            }
            $form.find('[name="settings[storefront]"]').val(storefront);
            $form .find('.js-datepicker').datepicker({
                'dateFormat': 'yy-mm-dd'
            }).datepicker('widget').hide();

            // 'http://' substitution for source field
            $form.find('[name="settings[source_type]"]').change(function() {
                var regexp = /^https?:\/\//;
                var $field = $form.find('[name="settings[source]"]');
                if ($form.find('[name="settings[source_type]"]:checked').val() == 'campaign') {
                    $field.val($field.val().replace(regexp, ''));
                } else {
                    if (!$field.val().match(regexp)) {
                        $field.val('http://'+$field.val());
                    }
                }
            }).change();

            function validateForm() {
                var errors = [];

                $form.find('.numeric:input').each(function() {
                    var val = parseInt(this.value);
                    if (isNaN(val) || val < 0) {
                        errors.push([$(this), '']);
                    }
                });

                $form.find('.js-datepicker').each(function() {
                    if (!/^[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]$/.test(this.value)) {
                        errors.push([$(this), 'YYYY-mm-dd']);
                    }
                });

                $.each(errors, function(i, e) {
                    var $fld = e[0];
                    if ($fld && !$fld.length) {
                        $fld = $form.find(':submit:first');
                    } else {
                        $fld.addClass('error');
                    }
                    $fld.parent().append($('<em class="errormsg"></em>').text(e[1]));
                });

                return errors.length <= 0;
            }

            function generate(initial_count, data) {

                var canceled = false;
                var orders_failed = 0;
                var last_start = null;
                var is_api = data.filter(function(item){
                    return item.name == 'settings[customer_api]';
                })[0]?.value !== '';

                var dfd = $.Deferred();
                var promise = dfd.promise();
                promise.abort = function() {
                    canceled = true;
                    return promise;
                }
                setTimeout(function() {
                    generateMany(initial_count, data);
                }, 0);
                return promise;

                function generateMany(count, data) {
                    last_start = new Date();
                    $.post('?plugin=genorder&module=backend&action=generate', data, null, 'json').done(function(r) {
                        // Notify callbacks about a single order generated
                        dfd.notify(r);
                    }).fail(function(jqXHR, textStatus, errorThrown) {
                        // Notify callbacks about a single order failed to generate
                        orders_failed++;
                        dfd.notify({
                            status: 'fail',
                            testStatus: textStatus,
                            data: 'XHR failed with a message: '+(errorThrown || textStatus)
                        });
                    }).always(function(r) {
                        if (count <= 1) {
                            // Notify callbacks that everything generated successfully
                            dfd.resolve(initial_count - orders_failed);
                        } else if (canceled) {
                            // Notify callbacks that some orders were not generated
                            dfd.reject([initial_count - orders_failed - count + 1]);
                        } else {

                            var delay = 0;
                            if (is_api) {
                                delay = Math.max(0, MIN_DELAY_BETWEEN_API_CALLS - (new Date()).getTime() + last_start.getTime());
                            }

                            // Continue generation
                            setTimeout(function(){
                                generateMany(count - 1, data);
                            }, delay);
                        }
                    });
                }
            }
        }
    };

}(jQuery));