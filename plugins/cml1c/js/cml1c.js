/**
 *
 * @names cml1c_*
 * @property {} cml1c_options
 * @method cml1cInit
 * @method cml1cAction
 * @method cml1cBlur
 */
$.extend($.importexport.plugins, {
    cml1c: {
        options: {
            'ignore_class': 'gray',
            'tab': 'auto'
        },
        dom: {
            container: null,
            exportform: null,
            importform: null,
            'null': null
        },
        data: {
            direction: null,
            upload_time: null
        },
        upload: null,
        uploadgroup: null,
        ajax_pull: {},
        progress: false,
        id: null,
        debug: {
            'memory': 0.0,
            'memory_avg': 0.0
        },

        onInit: function () {
            $.shop.trace('$.importexport.cml1cInit');
            var self = this;
            this.dom.container = $('#s-cml1c-form');

            this.dom.exportform = $("#s-plugin-cml1c-export");
            this.dom.importform = $("#s-plugin-cml1c-import");

            this.dom.exportform.submit(function () {
                return $.importexport.call('submitHandler', [this]);
            });

            this.dom.importform.submit(function () {
                var result = null;
                switch ($(this).attr('action').match(/\baction=\b(\w+)\b/)[1] || '') {
                    case 'run':
                        result = $.importexport.call('submitHandler', [this]);
                        break;
                    default:
                        result = $.importexport.call('uploadHandler', [this]);
                        break;
                }
                return result;
            });

            var button = $('#s-cml1c-auto').find(':checkbox[name="enabled"]:first').iButton({
                labelOn: "",
                labelOff: "",
                className: 'mini'
            }).change(function () {
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
                }, function (data) {
                    if (enabled && data && (data.status == 'ok')) {
                        $field.find(":input:first").val(data.data.url);
                    }
                }, 'json');
                return true;
            });
        },

        tabAction: function (tab) {
            if (true || this.options.tab != tab) {
                this.options.tab = tab;
                var $form = $('#s-cml1c-form');
                $form.find('ul.tabs li.selected').removeClass('selected');
                $form.find('ul.tabs a[href$="\/' + tab + '\/"]').parent().addClass('selected');
                $form.find('.tab-content > div:visible').hide();
                $form.find('.tab-content > div#s-cml1c-' + tab).show();

            }
        },

        action: function () {
            this.tabAction(this.options.tab);
        },
        blur: function(){

        },
        hashAction: function (hash) {
            this.action();
            var options = (hash || '').split('/');
            var select = this.form.find(':input[name="hash"][value="' + options[0] + '"]:first');
            if (select.length) {
                select.parents('li').show();
                select.attr('checked', true).trigger('change');
                var ids = options[1] || '0';
                this.form.find(':input[name="product_ids"]:first').val(ids);
                var label = select.parents('label').find('span:first');
                label.text(label.text().replace('%d', ids.split(',').length));
            }
            window.location.hash = '/csv:product:export/';
        },
        uploadRepeat: function () {
            var $form = this.dom.importform;
            $form.attr('action', $form.attr('action').replace(/\baction=run\b/, 'action=upload'));
            $form.find('div.js-cml1c-zip:first, div.js-progressbar-container, div.plugin-cml1c-report, #s-plugin-cml1c-import-upload-status').hide();
            $form.find('div.plugin-cml1c-submit .errormsg').text('');
            $form.find(':input').attr('disabled', null);
            $form.find(':input:not(:submit):not([name^="_"])').val('');
            $form.find('div.plugin-cml1c-submit,:submit, :input[type="file"]').show();
            $form.find('div.js-cml1c-zip:first li.js-cml1c-template:not(:first)').remove();
            $form.find('div.js-cml1c-zip:first :input').attr('disabled', true);
            return false;
        },

        submitHandler: function (form) {
            try {
                var $form = $(form);
                $.shop.trace('cml1c.submitHandler', $form);
                $form.find(':input:visible, :submit').attr('disabled', false).show();
                this.data.direction = $form.find(':input[name="direction"]').val();
                this.handler(form);
            } catch (e) {
                $('#s-csvproduct-transport-group').find(':input').attr('disabled', false);
                $.shop.error('Exception: ' + e.message, e);
            }
            return false;
        },

        uploadHandler: function (form) {

            var result = true;
            var self = this;
            var $form = $(form);
            var $status = $('#s-plugin-cml1c-import-upload-status');
            var $filename = $form.find(':input[name="filename"]');
            var $zipfile = $form.find(':input[name="zipfile"]');
            if (!$filename.val()) {
                $status.find('i.icon16').removeClass('yes no').addClass('loading');
                $status.find('span').text('');
                $form.find(':submit').attr('disabled', true);
                $status.fadeIn(400);
                $('#s-plugin-cml1c-import-iframe').one('load', function () {
                    try {
                        var $raw = $(this).contents().find('body > *');
                        var raw = $raw.html();
                        $.shop.trace('raw', raw);
                        var r;
                        try {
                            r = $.parseJSON(raw);
                        } catch (e) {
                            r = {
                                errors: e.message + '; ' + $raw.find('h1:first').text()
                            };
                            $.shop.error('Exception: ' + e.message, e);
                        }
                        $.shop.trace('json', r.files);
                        $status.fadeOut(200, function () {
                            if (r.files && r.files.length && !r.files[0].error) {
                                var response = r.files[0];
                                $form.find(':input[type="file"]').attr('disabled', true).hide();
                                $status.find('i.icon16').removeClass('loading no').addClass('yes');

                                $form.attr('action', $form.attr('action').replace(/\baction=upload/, 'action=run'));
                                var submit = true;
                                if (response.files.length) {
                                    $status.find('span').text(response.original_name).attr('title', response.size);
                                    $.shop.trace('files', [response.files.length, response]);
                                    $status.find('i.icon16').removeClass('no loading').addClass('yes');
                                    $zipfile.val(response.filename);
                                    var $container = $form.find('div.js-cml1c-zip:first ul:first');
                                    $container.find('li.js-cml1c-template:not(:first)').remove();

                                    var $item = $container.find('li:first');
                                    $.shop.trace('$item', [$item.length, $item])
                                    $item.show().find(':input').attr('disabled', null);
                                    $.shop.trace('$item', $item)
                                    for (var i = 0; i < response.files.length; i++) {
                                        $item.attr('title', response.files[i]['size']);
                                        $item.find(':input').val(response.files[i]['name']);
                                        $item.find('span').text(response.files[i]['name']);
                                        $item.clone().appendTo($container);
                                        $.shop.trace('list', $item)
                                    }
                                    $item.find(':input').attr('disabled', true);
                                    $item.hide();
                                    $container.find(':input:not(:disabled):first').attr('checked', true);
                                    $form.find('div.js-cml1c-zip:first').show();
                                    if (response.files.length > 1) {
                                        submit = false;
                                        $form.find(':submit').attr('disabled', null);
                                    }

                                } else {
                                    $status.find('span').text(response.original_name).attr('title', response.size);
                                    $filename.val(response.filename);
                                }
                                $status.fadeIn("slow");

                                if (submit) {
                                    setTimeout(function () {
                                        self.submitHandler(form);
                                    }, 50);
                                }
                            } else {
                                $form.find(':submit').attr('disabled', null);
                                $status.find('i.icon16').removeClass('loading yes').addClass('no');
                                var error = r.errors ? r.errors : '';
                                if (r.files && r.files.length && r.files[0].error) {
                                    error += ' ' + r.files[0].error;
                                }

                                $status.find('span').text(error);
                                $status.fadeIn("slow");
                            }
                        });
                    } catch (e) {
                        $status.html(e.message).css('color', 'red');
                        $form.find(':submit').attr('disabled', null);
                        $.shop.error('Exception: ' + e.message, e);
                    }
                });

                return result;
            } else {
                $.shop.trace('wtf???');
                result = this.submitHandler(form);
            }
            return result;

        },

        handler: function (element) {
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
                success: function (response) {
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
                        self.ajax_pull[response.processId].push(setTimeout(function () {
                            $.wa.errorHandler = function (xhr) {
                                return !((xhr.status > 400) || (xhr.status == 0));
                            };
                            self.progressHandler(url, response.processId, response);
                        }, 3000));
                        self.ajax_pull[response.processId].push(setTimeout(function () {
                            self.progressHandler(url, response.processId);
                        }, 2000));
                    }
                },
                error: function () {
                    self.form.find(':input:visible').attr('disabled', false);
                    self.form.find(':submit').show();
                    self.form.find('.js-progressbar-container').hide();
                    self.form.find('.shop-ajax-status-loading').remove();
                    self.form.find('.progressbar').hide();
                }
            });
            return false;
        },
        progressHandler: function (url, processId, response) {
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
                        'direction': self.data.direction,
                        'cleanup': 1
                    },
                    dataType: 'json',
                    type: 'post',
                    success: function (response) {
                        // show statistic
                        $.shop.trace('report', response);
                        self.form.find('.plugin-cml1c-submit').hide();
                        self.form.find('.progressbar').hide();
                        self.form.find('.plugin-cml1c-report').show();
                        if (response && response.report) {
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
                    var progress = parseFloat((''+response.progress).replace(/,/, '.'));
                    $bar.animate({
                        'width': progress + '%'
                    });
                    self.debug.memory = Math.max(0.0, self.debug.memory, parseFloat(response.memory) || 0);
                    self.debug.memory_avg = Math.max(0.0, self.debug.memory_avg, parseFloat(response.memory_avg) || 0);

                    var title = 'Memory usage: ' + self.debug.memory_avg + '/' + self.debug.memory + 'MB';

                    var message = response.progress + ' ' + (1 + parseInt(response.stage_num)) + '/' + response.stage_count + ' ' + response.stage_name;

                    $bar.parents('.progressbar').attr('title', message);
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

                self.ajax_pull[id].push(setTimeout(function () {
                    $.ajax({
                        url: ajax_url,
                        data: {
                            'processId': id,
                            'direction': self.data.direction
                        },
                        dataType: 'json',
                        type: 'post',
                        success: function (response) {
                            self.progressHandler(url, response ? response.processId || id : id, response);
                        },
                        error: function () {
                            self.progressHandler(url, id, null);
                        }
                    });
                }, 1000));
            }
        }
    }
});
