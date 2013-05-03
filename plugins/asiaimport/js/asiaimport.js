/**
 * {literal}
 */

$("#plugin-migrate-transport").change(function() {
    $("#s-plugin-migrate .plugin-migrate-transport-description:visible").hide();
    $("#plugin-migrate-submit").hide();
    if ($(this).val()) {
        $('#s-plugin-migrate .plugin-migrate-transport-description:visible').hide();
        $("#plugin-migrate-transport-" + $(this).val()).show();
        $("#plugin-migrate-transport-fields").html($_('Loading...') + '<i class="icon16 loading"></i>').load("?plugin=migrate&action=transport", {
            'transport': $(this).val()
        }, function() {
            $("#plugin-migrate-submit").show();
        });

    } else {

        $("#plugin-migrate-transport-fields").empty();
    }

});

// Set up AJAX to never use cache
$.ajaxSetup({
    cache: false
});

$.importexport.plugins.migrate = {
    form: null,
    ajax_pull: {},
    progress: false,
    id: null,
    debug: {
        'memory': 0.0,
        'memory_avg': 0.0
    },
    date: new Date(),
    validate: false,
    migrateHandler: function(element) {
        var self = this;
        self.progress = true;
        self.form = $(element);
        $.shop.trace('$.importexport.plugins.migrate.migrateHandler', [element]);
        var data = self.form.serialize();
        self.form.find('.errormsg').text('');
        self.form.find(':input').attr('disabled', true);
        self.form.find(':submit').hide();
        self.form.find('.progressbar .progressbar-inner').css('width', '0%');
        self.form.find('.progressbar').show();
        var url = $(element).attr('action');
        $.ajax({
            url: url+'&t='+this.date.getTime(),
            data: data,
            dataType: 'json',
            type: 'post',
            success: function(response) {
                if (response.error) {
                    self.form.find(':input').attr('disabled', false);
                    self.form.find(':submit').show();
                    self.form.find('.js-progressbar-container').hide();
                    self.form.find('.shop-ajax-status-loading').remove();
                    self.progress = false;
                    self.form.find('.errormsg').text(response.error);
                } else {
                    self.form.find('.progressbar').attr('title', '0.00%');
                    self.form.find('.progressbar-description').text('0.00%');
                    self.form.find('.js-progressbar-container').show();

                    self.ajax_pull[response.processId] = [];
                    self.ajax_pull[response.processId].push(setTimeout(function() {
                        $.wa.errorHandler = function(xhr) {
                            if ((xhr.status >= 500) || (xhr.status == 0)) {
                                return false;
                            }
                            return true;
                        };
                        self.progressHandler(url, response.processId, response);
                    }, 1000));
                    self.ajax_pull[response.processId].push(setTimeout(function() {
                        self.progressHandler(url, response.processId);
                    }, 2000));
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                self.form.find(':input').attr('disabled', false);
                self.form.find(':submit').show();
                self.form.find('.js-progressbar-container').hide();
                self.form.find('.shop-ajax-status-loading').remove();
                self.form.find('.progressbar').hide();
            }
        });
        return false;
    },
    progressHandler: function(url, processId, response) {
        // display progress
        // if not completed do next iteration
        var self = $.importexport.plugins.migrate;

        if (response && response.ready) {
            $.wa.errorHandler = null;
            var timer;
            while (timer = self.ajax_pull[processId].pop()) {
                if (timer) {
                    clearTimeout(timer);
                }
            }
            // self.form.find(':input').attr('disabled', false);
            // self.form.find(':submit').show();
            var $bar = self.form.find('.progressbar .progressbar-inner');
            $bar.css({
                'width': '100%'
            });
            // self.form.find('.progressbar').hide();
            // self.form.find('.progressbar-description').hide();
            $.shop.trace('cleanup', response.processId);

            $.ajax({
                url: url+'&t='+this.date.getTime(),
                data: {
                    'processId': response.processId,
                    'cleanup': 1
                },
                dataType: 'json',
                type: 'post',
                success: function(response) {
                    // show statistic
                    $.shop.trace('report', response);
                    $("#plugin-migrate-submit").hide();
                    self.form.find('.progressbar').hide();
                    $("#plugin-migrate-report").show();
                    if (response.report) {
                        $("#plugin-migrate-report .value:first").html(response.report);
                    }
                    $.storage.del('shop/hash');
                }
            });

        } else if (response && response.error) {

            self.form.find(':input').attr('disabled', false);
            self.form.find(':submit').show();
            self.form.find('.js-progressbar-container').hide();
            self.form.find('.shop-ajax-status-loading').remove();
            self.form.find('.progressbar').hide();
            self.form.find('.errormsg').text(response.error);

        } else {
            var $description;
            if (response && (typeof(response.progress) != 'undefined')) {
                var $bar = self.form.find('.progressbar .progressbar-inner');
                var progress = parseFloat(response.progress.replace(/,/, '.'));
                $bar.animate({
                    'width': progress + '%'
                });;
                self.debug.memory = Math.max(0.0, self.debug.memory, parseFloat(response.memory) || 0);
                self.debug.memory_avg = Math.max(0.0, self.debug.memory_avg, parseFloat(response.memory_avg) || 0);

                var title = 'Memory usage: ' + self.debug.memory_avg + '/' + self.debug.memory + 'MB';
                title += ' (' + (1 + parseInt(response.stage_num)) + '/' + response.stage_count + ')'

                var message = response.progress + ' — ' + response.stage_name;

                $bar.parents('.progressbar').attr('title', response.progress);
                $description = self.form.find('.progressbar-description');
                $description.text(message);
                $description.attr('title', title);
            }
            if (response && (typeof(response.warning) != 'undefined')) {
                $description = self.form.find('.progressbar-description');
                $description.append('<i class="icon16 exclamation"></i><p>' + response.warning + '</p>');
            }

            var ajax_url = url;
            var id = processId;

            self.ajax_pull[id].push(setTimeout(function() {
                $.ajax({
                    url: ajax_url+'&t='+self.date.getTime(),
                    data: {
                        'processId': id
                    },
                    dataType: 'json',
                    type: 'post',
                    success: function(response) {
                        self.progressHandler(url, response ? response.processId || id : id, response);
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        self.progressHandler(url, id, null);
                    }
                });
            }, 1000));
        }
    }
}
$("#s-plugin-migrate").submit(function() {
    try {
        var $form = $(this);
        if (!$.importexport.plugins.migrate.validate) {
            $('#plugin-migrate-transport-group :input').attr('disabled', false);
            var item, data = $form.serializeArray();
            var params = {};
            while (item = data.shift()) {
                params[item.name] = item.value;
            }
            var loading = '<i class="icon16 loading"></i>';
            var url = "?plugin=migrate&action=transport";
            $.shop.trace('validate', params);
            $form.find(':submit:first').after(loading);
            $form.find(':input, :submit').attr('disabled', true);
            $('#plugin-migrate-transport-group :input').attr('disabled', true);

            $("#plugin-migrate-transport-fields").load(url, params, function() {
                $("#plugin-migrate-submit").show();
                $form.find(':submit:first ~ i.loading').remove();
                $form.find('#plugin-migrate-transport-fields :input, :submit').attr('disabled', false);
            });
        } else {
            $form.find(':input, :submit').attr('disabled', false);
            $.importexport.plugins.migrate.migrateHandler(this);
        }
    } catch (e) {
        $('#plugin-migrate-transport-group :input').attr('disabled', false);
        $.shop.error('Exception: ', e.message, e);
    }
    return false;
});

/**
 * {/literal}
 */
