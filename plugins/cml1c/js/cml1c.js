/**
 * 
 * @names cml1c_*
 * @property {} cml1c_options
 * @method cml1cInit
 * @method cml1cAction
 * @method cml1cBlur
 */

if (typeof($) != 'undefined') {
    $.extend($.importexport = $.importexport || {}, {

        cml1c_options: {
            'ignore_class': 'gray',
            'tab': 'auto'
        },
        cml1c_dom: {
            container: null,
            exportform: null,
            importform: null,
            'null': null
        },
        cml1c_data: {
            direction: null,
            upload_time: null
        },
        cml1c_upload: null,
        cml1c_uploadgroup: null,
        ajax_pull: {},
        progress: false,
        id: null,
        debug: {
            'memory': 0.0,
            'memory_avg': 0.0
        },

        cml1cInit: function() {
            $.shop.trace('$.importexport.cml1cInit');
            var self = this;
            this.cml1c_dom.container = $('#s-cml1c-form');

            this.cml1c_dom.exportform = $("#s-plugin-cml1c-export");
            this.cml1c_dom.importform = $("#s-plugin-cml1c-import");

            this.cml1c_dom.exportform.submit(function() {
                return self.call('SubmitHandler', [this]);
            });

            this.cml1c_dom.importform.submit(function() {
                return self.call('UploadHandler', [this]);
            });

            var button = $('#s-cml1c-auto').find(':checkbox[name="enabled"]:first').iButton({
                labelOn: "",
                labelOff: "",
                className: 'mini'
            }).change(function() {
                var self = $(this);
                var enabled = self.is(':checked');
                if (!enabled
                && !confirm('При отключении автоматического обмена текущий адрес скрипта синхронизации более не будет работать (после повторного включения будет создан новый адрес). Вы уверены?')) {
                    self.attr('checked', true);
                    button.iButton('toggle', true);
                    return false;
                }
                var $value = self.parents('.value');
                var $field = self.closest('.field').siblings();
                $value.find('span.gray').removeClass('gray');
                if (enabled = self.is(':checked')) {
                    $value.find('span.s-cml1c-disable').addClass('gray');
                    $field.show(200);
                } else {
                    $field.hide(200);
                    $value.find('span.s-cml1c-enable').addClass('gray');
                }
                button.iButton('repaint');
                $.post('?plugin=cml1c&action=save', {
                    enabled: enabled ? '1' : '0'
                }, function(data, textStatus, jqXHR) {
                    if (enabled && data && (data.status == 'ok')) {
                        $field.find(":input:first").val(data.data.url);
                    }
                }, 'json');
                return true;
            });
        },

        cml1cTabAction: function(tab) {
            if (true || this.cml1c_options.tab != tab) {
                this.cml1c_options.tab = tab;
                var $form = $('#s-cml1c-form');
                $form.find('ul.tabs li.selected').removeClass('selected');
                $form.find('ul.tabs a[href$="\/' + tab + '\/"]').parent().addClass('selected');
                $form.find('.tab-content > div:visible').hide();
                $form.find('.tab-content > div#s-cml1c-' + tab).show();

            }
        },

        cml1cAction: function() {
            this.cml1cTabAction(this.cml1c_options.tab);
        },
        cml1cHashAction: function(hash) {
            this.cml1cAction();
            var options = (hash || '').split('/');
            var select = this.cml1c_form.find(':input[name="hash"][value="' + options[0] + '"]:first');
            if (select.length) {
                select.parents('li').show();
                select.attr('checked', true).trigger('change');
                var ids = options[1] || '0';
                this.cml1c_form.find(':input[name="product_ids"]:first').val(ids);
                var label = select.parents('label').find('span:first');
                label.text(label.text().replace('%d', ids.split(',').length));
            }
            window.location.hash = '/csv:product:export/';
        },

        cml1cSubmitHandler: function(form) {
            try {
                var $form = $(form);
                $.shop.trace('cml1cSubmitHandler', $form);
                $form.find(':input:visible, :submit').attr('disabled', false).show();
                this.cml1c_data.direction = $form.find(':input[name="direction"]').val();
                this.cml1cHandler(form);
            } catch (e) {
                $('#s-csvproduct-transport-group').find(':input').attr('disabled', false);
                $.shop.error('Exception: ', e.message, e);
            }
            return false;
        },

        cml1cUploadHandler: function(form) {

            var result = true;
            var self = this;
            var $form = $(form);
            var $status = $('#s-plugin-cml1c-import-upload-status');
            var $filename = $form.find(':input[name="filename"]');
            if (!$filename.val()) {
                $status.find('i.icon16').removeClass('yes no').addClass('loading');
                $status.find('span').text('');
                $form.find(':submit').attr('disabled', true);
                $status.fadeIn(400);
                $('#s-plugin-cml1c-import-iframe').one('load', function() {
                    try {
                        $raw = $(this).contents().find('body > *');
                        var raw = $raw.html();
                        $.shop.trace('raw', raw);
                        var r;
                        try {
                            r= $.parseJSON(raw);
                        } catch (e) {
                            r = {
                                errors: e.message+'; '+$raw.find('h1:first').text()
                            };
                            $.shop.error('Exception: ', e.message, e);
                        }
                        $.shop.trace('json', r.files);
                        $status.fadeOut(200, function() {
                            if (r.files && r.files.length) {
                                $form.find(':input[type="file"]').attr('disabled', true).hide();
                                $status.find('i.icon16').removeClass('loading no').addClass('yes');
                                $status.find('span').text(r.files[0]['original_name']);

                                $filename.val(r.files[0]['filename']);
                                $form.attr('action', $form.attr('action').replace(/\baction=upload/, 'action=run'));
                                $status.fadeIn("slow");
                                setTimeout(function() {
                                    self.call('SubmitHandler', [form]);
                                }, 50)
                            } else {
                                $form.find(':submit').attr('disabled', null);
                                $status.find('i.icon16').removeClass('loading yes').addClass('no');
                                $status.find('span').text(r.errors ? r.errors : r);
                                $status.fadeIn("slow");
                            }
                        });
                    } catch (e) {
                        $status.html(e.message).css('color', 'red');
                        $form.find(':submit').attr('disabled', null);
                        $.shop.error('Exception: ', e.message, e);
                    }
                });

                return result;
            } else {
                result = this.cml1cSubmitHandler(form);
            }
            return result;

        },

        cml1cHandler: function(element) {
            var self = this;
            self.progress = true;
            self.form = $(element);
            var data = self.form.serialize();
            self.form.find('.errormsg').text('');
            self.form.find(':input').attr('disabled', true);
            self.form.find(':submit').hide();
            self.form.find('.progressbar .progressbar-inner').css('width', '0%');
            self.form.find('.progressbar').show();
            var url = $(element).attr('action');
            $.ajax({
                url: url,
                data: data,
                dataType: 'json',
                type: 'post',
                success: function(response) {
                    if (response.error) {
                        self.form.find(':input:visible').attr('disabled', false);
                        self.form.find(':submit').show();
                        self.form.find('.js-progressbar-container').hide();
                        self.form.find('.shop-ajax-status-loading').remove();
                        self.progress = false;
                        self.form.find('.errormsg').text(response.error);
                    } else {
                        self.form.find('.progressbar').attr('title', '0.00%');
                        self.form.find('.progressbar-description').text('0.00%');
                        self.form.find('.js-progressbar-container').show();

                        if (response.file) {
                            var $link = self.form.find('.plugin-cml1c-report .value a:first');
                            $link.attr('href', ('' + $link.attr('href')).replace(/&file=.*$/, '') + '&file=' + response.file);
                        }

                        self.ajax_pull[response.processId] = [];
                        self.ajax_pull[response.processId].push(setTimeout(function() {
                            $.wa.errorHandler = function(xhr) {
                                return !((xhr.status > 400) || (xhr.status == 0));
                            };
                            self.progressHandler(url, response.processId, response);
                        }, 3000));
                        self.ajax_pull[response.processId].push(setTimeout(function() {
                            self.progressHandler(url, response.processId);
                        }, 2000));
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    self.form.find(':input:visible').attr('disabled', false);
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
            var self = this;

            if (response && response.ready) {
                $.wa.errorHandler = null;
                var timer;
                while (timer = self.ajax_pull[processId].pop()) {
                    if (timer) {
                        clearTimeout(timer);
                    }
                }

                var $bar = self.form.find('.progressbar .progressbar-inner');
                $bar.css({
                    'width': '100%'
                });
                $.shop.trace('cleanup', response.processId);
                if (response.file) {
                    var $link = self.form.find('.plugin-cml1c-report .value a:first');
                    $link.attr('href', ('' + $link.attr('href')).replace(/&file=.*$/, '') + '&file=' + response.file);
                }

                $.ajax({
                    url: url,
                    data: {
                        'processId': response.processId,
                        'direction': self.cml1c_data.direction,
                        'cleanup': 1
                    },
                    dataType: 'json',
                    type: 'post',
                    success: function(response) {
                        // show statistic
                        $.shop.trace('report', response);
                        self.form.find('.plugin-cml1c-submit').hide();
                        self.form.find('.progressbar').hide();
                        self.form.find('.plugin-cml1c-report').show();
                        if (response.report) {
                            self.form.find('.plugin-cml1c-report .value:first').html(response.report);
                        }
                    }
                });

            } else if (response && response.error) {

                self.form.find(':input:visible').attr('disabled', false);
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
                    });
                    self.debug.memory = Math.max(0.0, self.debug.memory, parseFloat(response.memory) || 0);
                    self.debug.memory_avg = Math.max(0.0, self.debug.memory_avg, parseFloat(response.memory_avg) || 0);

                    var title = 'Memory usage: ' + self.debug.memory_avg + '/' + self.debug.memory + 'MB';

                    var message = response.progress;

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
                        url: ajax_url,
                        data: {
                            'processId': id,
                            'direction': self.cml1c_data.direction
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
    });
}
