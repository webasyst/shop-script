/**
 * {literal}
 **/
(function ($) {
    /**
     *
     * @names cml1c_*
     *
     *
     * @property {{key: string}} cml1c_options
     * @method cml1cInit
     * @method cml1cAction
     * @method cml1cBlur
     */

    /**
     * @typedef {object} cml1cUploadResponseFiles
     * @property {string}  name
     * @property {number} size

     /**
     * @typedef {object} cml1cUploadResponse
     * @property {cml1cUploadResponseFiles[]}  files
     * @property {string} filename
     * @property {string} original_name
     * @property {number} size
     */

    /**
     * @typedef {object} cml1cProgressResponse
     * @property {string} file
     * @property {boolean} ready
     * @property {float} progress
     * @property {string} processId
     * @property {number} memory
     * @property {number} memory_avg
     * @property {number} stage_num
     * @property {number} stage_count
     * @property {string} stage_name
     * @property {number} report_id
     * @property {number} time
     *
     */

    /**
     * @typedef {object} cml1cErrorResponse
     * @property {string} error
     *
     */
    /**
     * @typedef {object} cml1cWarningResponse
     * @property {string} warning
     *
     */
    $.extend($.importexport.plugins, {
        cml1c: {
            options: {
                "ignore_class": 'gray',
                "tab": 'auto'
            },
            dom: {
                "container": null,
                "exportform": null,
                "importform": null,
                "null": null
            },
            data: {
                direction: null,
                upload_time: null
            },
            form: null,
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
                // $.shop.trace('$.importexport.cml1c', 'Init');
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

                $('#s-cml1c-auto input[readonly=readonly]').click(function () {
                    $(this).select()
                });

                var button = $('#s-cml1c-auto').find(':checkbox[name="enabled"]:first').iButton({
                    labelOn: "",
                    labelOff: "",
                    className: 'mini'
                }).change(function () {
                    var self = $(this);
                    var enabled = self.is(':checked');
                    if (!enabled
                        && !confirm('При отключении автоматического обмена текущий адрес скрипта синхронизации более не будет работать (после повторного включения будет создан новый адрес). Вы уверены?')
                    ) {
                        self.attr('checked', true);
                        button.iButton('toggle', true);
                        return false;
                    }
                    var $value = self.parents('.value');
                    var $field = self.closest('.field').siblings();
                    // $.shop.trace('$field', $field);
                    $value.find('span.gray').removeClass('gray');
                    enabled = self.is(':checked');
                    button.iButton('repaint');
                    $.post('?plugin=cml1c&action=save', {
                        enabled: enabled ? '1' : '0'
                    }, function (data) {
                        if (data && (data.status === 'ok')) {
                            if (enabled) {
                                $value.find('span.s-cml1c-disable').addClass('gray');
                                $field.show(200);
                                if (data.data.url) {
                                    $field.find(":input:first").val(data.data.url);
                                    $field.find(":input:last").val(data.data.url + 'moysklad/');

                                    $field.find('.cml1c-url').show();
                                    $field.find('.js-cml1c-settlement').hide();
                                    if (data.data.url.match(/^https:\/\//)) {
                                        $field.find('.js-cml1c-url-ssl').show();
                                    } else {
                                        $field.find('.js-cml1c-url-ssl').hide();
                                    }
                                } else {
                                    $field.find(":input:first").val('');
                                    $field.find(":input:last").val('');

                                    $field.find('.js-cml1c-settlement').show();
                                    $field.find('.cml1c-url').hide();
                                }
                            } else {
                                $value.find('span.s-cml1c-disable').addClass('gray');
                                $field.hide(200);
                            }
                        }
                    }, 'json');
                    return true;
                });

                var self = this;
                this.dom.importform.find(':checkbox[name="configure"]').change(function () {
                    self.configureHandler(this);
                }).change();
            },

            tabAction: function (tab) {
                if (true || this.options.tab !== tab) {
                    this.options.tab = tab;
                    var $form = $('#s-cml1c-form');
                    $form.find('ul.tabs li.selected').removeClass('selected');
                    var $tab = $form.find('ul.tabs a[href$="\/' + tab + '\/"]');
                    if ($tab.hasClass('js-invalidated')) {
                        $.importexport.dispatch(undefined, true);
                    } else {
                        $tab.parent().addClass('selected');
                        $form.find('.tab-content > div:visible').hide();
                        $form.find('.tab-content > div#s-cml1c-' + tab).show();
                    }

                }
            },

            action: function () {
                this.tabAction(this.options.tab);
            },
            blur: function () {

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
            showRepeat: function (configure) {
                var $form = this.dom.importform;
                var $submit = $form.find(':submit');
                if (configure) {
                    $form.find('div.plugin-cml1c-submit').show();
                    $form.find('div.js-progressbar-container').hide();

                    $form.find('div.plugin-cml1c-report').show();
                    $form.find('div.plugin-cml1c-report .js-cml1c-repeat').hide();
                    $form.find('div.plugin-cml1c-submit .js-cml1c-repeat, :submit').show();

                    $submit.attr('disabled', null).val($submit.data('save'));
                    $form.find(':input:not([type="radio"][name="filename"])').attr('disabled', null);
                    $form.find(':input[name="filename"][type="hidden"]').attr('disabled', null);
                } else {
                    $form.find('div.plugin-cml1c-report .js-cml1c-repeat').show();
                    $form.find('div.plugin-cml1c-submit .js-cml1c-repeat, :submit').hide();
                }

                var $configure = $form.find('input[name="configure"]');
                $configure.attr('checked', null).attr('disabled', true);
                $configure.parents('div.field').slideUp();
                return false;
            },

            uploadRepeat: function () {
                var $form = this.dom.importform;
                $form.attr('action', $form.attr('action').replace(/\baction=run\b/, 'action=upload'));
                $form.find('div.js-cml1c-zip:first, div.js-progressbar-container, div.plugin-cml1c-report, #s-plugin-cml1c-import-upload-status, div.plugin-cml1c-submit .js-cml1c-repeat').hide();
                $form.find('div.plugin-cml1c-submit .errormsg').text('');
                $form.find(':input').attr('disabled', null);
                var $configure = $form.find('input[name="configure"]');
                $configure.parents('div.field').slideDown();
                $configure.attr('checked', true).trigger('change');
                $form.find(':input:not(:submit):not([name^="_"]):not([type="checkbox"])').val('');
                $form.find('div.plugin-cml1c-submit,:submit, :input[type="file"]').show();

                $form.find('div.js-cml1c-zip:first li.js-cml1c-template:not(:first)').remove();
                $form.find('div.js-cml1c-zip:first :input').attr('disabled', true);
                $form.find('#plugin-cml1c-report-import .value:not(.js-cml1c-repeat)').html('');

                $form.find(':input[name="filename"][type="hidden"]').val('').attr('disabled', null);
                $form.find(':input[name="zipfile"]').val('').attr('disabled', null);
                return false;
            },

            configureHandler: function (checkbox) {
                var $form = this.dom.importform;
                var $submit = $form.find(':submit');
                $submit.val(checkbox.checked ? $submit.data('configure') : $submit.data('import'));
                var $expert = $form.find(':input[name="expert"]').parents('div.value');
                if (checkbox.checked) {
                    $expert.slideDown();
                } else {
                    $expert.slideUp();
                }
            },

            submitHandler: function (form) {
                var $form = $(form);
                try {
                    // $.shop.trace('cml1c.submitHandler', $form);
                    $form.find(':input:visible, :submit').attr('disabled', false).show();
                    this.data.direction = $form.find(':input[name="direction"]').val();
                    this.handler(form);
                } catch (e) {
                    $form.find(':input:visible, :submit').attr('disabled', false).show();
                    $.shop.error('Exception: ' + e.message, e);
                }
                return false;
            },

            mapReset: function ($el) {
                $el.after('<i class="icon16 loading"></i>');
                $el.hide();

                $.ajax({
                    url: '?plugin=cml1c&action=remap',
                    data: {
                        "map": 'reset'
                    },
                    dataType: 'json',
                    type: 'post',
                    success: function () {
                        $.importexport.dispatch(undefined, true);
                    },
                    error: function () {
                        $el.show();
                        $el.after('<i class="icon16 no"></i>');
                        $el.parent().find('i.icon16.loading').remove();
                    }
                });

                return false;
            },

            uploadHandler: function (form) {

                var result = true;
                var self = this;
                var $form = $(form);
                var $status = $('#s-plugin-cml1c-import-upload-status');
                var $filename = $form.find(':input[name="filename"][type="hidden"]');
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
                            // $.shop.trace('raw', raw);
                            var r;
                            try {
                                r = $.parseJSON(raw);
                                /**
                                 * @type {cml1cUploadResponse} r
                                 */
                            } catch (e) {
                                r = {
                                    errors: e.message + '; ' + $raw.find('h1:first').text()
                                };
                                $.shop.error('Exception: ' + e.message, e);
                            }

                            $status.fadeOut(200, function () {


                                if (r.files && r.files.length && !r.files[0].error) {

                                    var response = r.files[0];

                                    $form.find(':input[type="file"]').attr('disabled', true).hide();
                                    $status.find('i.icon16').removeClass('loading no').addClass('yes');

                                    $form.attr('action', $form.attr('action').replace(/\baction=upload/, 'action=run'));
                                    var submit = true;
                                    if (response.files.length) {
                                        $status.find('span').text(response.original_name).attr('title', response.size);
                                        // $.shop.trace('files', [response.files.length, response]);
                                        $status.find('i.icon16').removeClass('no loading').addClass('yes');
                                        $zipfile.val(response.filename);
                                        var $container = $form.find('div.js-cml1c-zip:first ul:first');
                                        $container.find('li.js-cml1c-template:not(:first)').remove();

                                        var $item = $container.find('li:first');
                                        // $.shop.trace('$item', [$item.length, $item]);
                                        $item.show().find(':input').attr('disabled', null);
                                        // $.shop.trace('$item', $item);
                                        for (var i = 0; i < response.files.length; i++) {
                                            $item.attr('title', response.files[i]['size']);
                                            $item.find(':input').val(response.files[i]['name']).attr('checked', i === 0 ? true : null);
                                            $item.find('span').text(response.files[i]['name']);

                                            $item.clone().appendTo($container);
                                            // $.shop.trace('list', $item)
                                        }
                                        $container.find('li.js-cml1c-template:not(:first) input').change(function () {
                                            if (this.checked) {
                                                $form.find(':input[name="filename"][type="hidden"]').val(this.value);
                                            }
                                        }).change();

                                        $item.find(':input').attr('disabled', true);
                                        $item.hide();
                                        $container.find(':input:not(:disabled):first').attr('checked', true);
                                        $form.find('div.js-cml1c-zip:first').hide().toggle("highlight");
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
                    $.shop.error('wtf???');
                    result = this.submitHandler(form);
                }
                return result;

            },

            handler: function (element) {
                $('#s-cml1c-form').find('ul.tabs a[href$="\/map\/"]').addClass('js-invalidated');

                var self = this;
                self.progress = true;
                self.form = $(element);
                var data = self.form.serialize();
                self.form.find('.errormsg').text('');
                self.form.find(':input').attr('disabled', true);//:not([type="hidden"])
                self.form.find(':submit').hide();
                self.form.find('.progressbar .progressbar-inner').css('width', '0%');
                self.form.find('.progressbar').show();
                self.form.find('.js-cml1c-repeat').hide();
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
                                    return !((xhr.status > 400) || (xhr.status === 0));
                                };
                                self.progressHandler(url, response.processId, response);
                            }, 1000));

                            self.ajax_pull[response.processId].push(setTimeout(function () {
                                self.progressHandler(url, response.processId);
                            }, 4000));
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

            /**
             *
             * @param url
             * @param processId
             * @param {cml1cProgressResponse|cml1cWarningResponse|cml1cErrorResponse} response
             */
            progressHandler: function (url, processId, response) {
                // display progress
                // if not completed do next iteration

                if (this.progress) {
                    var self = this;
                    if (response && response.ready) {
                        $.wa.errorHandler = null;
                        self.progress = false;
                        var timer;
                        while (timer = self.ajax_pull[processId].pop()) {
                            if (timer) {
                                clearTimeout(timer);
                            }
                        }

                        self.form.find('.progressbar .progressbar-inner').css({
                            'width': '100%'
                        });
                        // $.shop.trace('cleanup', response.processId);
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
                                // $.shop.trace('report', response);
                                self.form.find('.plugin-cml1c-submit').hide();
                                self.form.find('.progressbar').hide();
                                self.form.find('.plugin-cml1c-report').show();
                                if (response) {
                                    if (response.report) {
                                        var $report = self.form.find('.plugin-cml1c-report .value:first');
                                        if (!response.report_id || ($report.data('report-id') !== response.report_id)) {
                                            if (response.report_id) {
                                                $report.data('report-id', response.report_id);
                                            }
                                            $report.html(response.report);
                                        }
                                    }

                                    self.showRepeat(response.configure);
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
                        if (response && (typeof(response.progress) !== 'undefined')) {
                            var $bar = self.form.find('.progressbar .progressbar-inner');
                            var progress = parseFloat(('' + response.progress).replace(/,/, '.'));
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
                        if (response && (typeof(response.warning) !== 'undefined')) {
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
            },
            initMapControlRow: function (name) {
                var self = this;
                var $scope = this.dom.importform.find(':input[name^="' + name + '"]');

                if (!$scope.length) {
                    $.shop.error('bad selector', ':input[name^="' + name + '"]');
                }

                var $other = $scope.filter(':not(:input[name$="\[target\]"])');
                $other.change(function (event) {
                    if (event.originalEvent) {
                        $(this).parents('tr').find(':input[name$="\[target\]"]').attr('checked', true);
                    }
                });

                var $params = $scope.filter(':input[name$="\[p\]"]:first');

                if ($params.length) {
                    var $params_td = $params.parent('td');
                    var $param = $params_td.find(':input[name$="\[param\]"]');
                    var $param_cancel = $params_td.find('a.js-customparams-cml1c-cancel');
                    $param.hide();
                    $params.unbind('change.migrate').bind('change.migrate', function () {
                        // $.shop.trace('initMapControlRow', [this.value]);
                        if (this.value === 'p:%s') {
                            $param.val('');
                            $param.show();
                            $param_cancel.show();
                            $params.hide();
                        } else {
                            $param.hide();
                            $param_cancel.hide();
                            $params.show();
                        }
                    }).trigger('change');

                    $param_cancel.click(function () {
                        $params.val('p:');
                        $params.trigger('change');
                        return false;
                    });
                }


                var $features = $scope.filter(':input[name$="\[f\]"]:first');
                var $dimension = $scope.filter(':input[name$="\[dimension\]"]:first');

                /** features row **/
                if ($features.length) {
                    var autocomplete = false;


                    var $td = $features.parent('td');
                    var $cancel = $td.find('a.js-autocomplete-cml1c-cancel:first');

                    $features.unbind('change.migrate').bind('change.migrate', function () {
                        var type = 'none';
                        // $.shop.trace('initMapControlRow', [this.value]);
                        if (this.value === 'f:%s') {
                            $features.hide();
                            if (!autocomplete) {
                                // $.shop.trace('autocomplete', $td.find(':input.js-autocomplete-cml1c:first'));
                                $td.find(':input.js-autocomplete-cml1c:first').autocomplete({
                                    source: '?action=autocomplete&type=feature',
                                    minLength: 2,
                                    delay: 300,
                                    select: function (event, ui) {
                                        self.helpers.addFeature(event, ui, $features);
                                    }/*,
                                     focus: function (event, ui) {
                                     self.helpers.addFeature(event, ui, $features);
                                     }*/
                                });
                                autocomplete = true;
                            }
                            $cancel.show();
                            $td.find('.ui-autocomplete-input:hidden').val('').show().focus();
                            $dimension.hide();
                        } else {
                            $td.find('.ui-autocomplete-input:visible').hide();
                            $cancel.hide();
                            $features.show();
                            $dimension.show();
                            type = self.helpers.getFeatureType($features);
                            self.helpers.filterDimensionType($dimension, type);
                        }

                    }).trigger('change');

                    $cancel.click(function () {
                        $features.val('p:').trigger('change');
                        return false;
                    });
                }

            },
            helpers: {
                getFeatureType: function ($input) {
                    var type = $input.val().match(/(dimension\.)+([^:]+)/);
                    var selected_option = $input.find('option:selected:first');
                    if (type && type[2]) {
                        type = type[2]
                    } else if (selected_option.length
                        && (type = (''+ selected_option.prop('class')).match(/\bjs-type-(?:2d\.|3d\.)?dimension\.([\w]+)\b/))
                        && type[1]
                    ) {
                        type = type[1];
                    } else {
                        type = 'none';
                    }
                    return type;
                },
                filterDimensionType: function ($dimension, type) {
                    var selected = false;
                    $dimension.find('option').each(
                        /**
                         *
                         * @param {number} index
                         * @param {HTMLOptionElement} element
                         */
                        function (index, element) {
                            var option = $(element);
                            var disabled = (option.hasClass('js-type-null') || option.hasClass('js-type-' + type)) ? null : true;

                            option.attr('disabled', disabled);
                            if (disabled) {
                                option.hide();
                            } else {
                                option.show();
                                if (!selected) {
                                    if (element.defaultSelected) {
                                        selected = true;
                                    }
                                    if (selected || (!selected && option.hasClass('js-base-type'))) {
                                        $dimension.val(element.value);
                                    }
                                }
                            }
                        }
                    );
                    if (!selected) {
                        $dimension.val('');
                    }
                },
                addFeature: function (event, ui, $features) {
                    /**
                     * @this {HTMLInputElement}
                     */
                    // $.shop.trace('autocomplete', ui.item);

                    var value = 'f:' + ui.item.value;

                    if (!$features.find('option[value="' + value + '"]:first').length) {
                        var $option = $('<option></option>');
                        $option.text(ui.item.name);
                        $option.attr('value', value);
                        $option.attr('title', ui.item.value);
                        if (ui.item.type) {
                            $option.attr('class', 'js-type-' + ui.item.type);
                        }
                        $features.find('option[value="f:%s"]:first').after($option);
                    }

                    $features.val(value).change();
                    return false;
                }

            }
        }
    });
})(jQuery);
/**
 * {/literal}
 */