/**
 *
 * @names csv_product*
 * @property csv_product_options
 * @method csv_productInit
 * @method csv_productAction
 * @method csv_productBlur
 *
 * @todo partial read & etc
 */

if (typeof($) != 'undefined') {
    $.extend($.importexport = $.importexport || {}, {
        csv_product: true,
        csv_product_options: {
            ignore_class: 'gray',
            control: '',
            columns_offset: 0
        },
        csv_product_form: null,
        csv_product_form_primary: null,
        csv_product_form_map: null,
        csv_product_upload: null,
        csv_product_uploadgroup: null,
        csv_product_table_cells: null,
        csv_product_data: {
            ajax_pull: {},
            progress: false,
            upload_time: null,
            primary_callback: null
        },
        csv_product_cleanup: true,

        csv_productOnInit: function () {

        },

        csv_productAction: function () {

        },

        csv_productBlur: function () {

        },

        csv_productInit: function () {
            var self = this;

            this.csv_product_form = $("#s-csvproduct");

            this.csv_product_form.unbind('submit').bind('submit', function () {
                return self.call('SubmitHandler', [this]);
            });

            $.importexport.products.init(this.csv_product_form);

            if (this.csv_product_form.find('.fileupload:first').length) {
                this.csv_product_form.find(':submit').hide();
                this.csv_productUploadInit();
            } else {
                this.csv_product_form.find(':input[name="encoding"]').change(function () {
                    var $hint = self.csv_product_form.find('.js-encoding-hint');
                    if (this.value == 'UTF-8') {
                        $hint.hide();
                    } else {
                        $hint.show();
                    }
                }).trigger('change');
            }

        },

        csv_productHashAction: function (hash) {
            $.shop.trace('csv_productHashAction', hash);
            $.importexport.products.action(hash);
            window.location.hash = window.location.hash.replace(/\/hash\/.+$/, '/');
        },

        csv_productUploadInit: function () {

            var url = ('' + this.csv_product_form.attr('action')).replace(/\b(action=(.*))(run)\w*\b/, '$1upload');

            this.csv_product_upload = this.csv_product_form.find('.fileupload:first').parents('div.field');
            this.csv_product_uploadgroup = this.csv_product_upload.parents('div.field-group');

            this.csv_product_uploadgroup.next('div.field-group').hide();
            var self = this;
            this.csv_product_form.find('.fileupload:first').fileupload({
                url: url,
                start: function () {
                    self.csv_product_upload.find('.fileupload:first').hide();
                    self.csv_product_upload.find('.js-fileupload-progress').show();
                    self.csv_product_uploadgroup.find('.field:not(:last) :input:visible').attr('disabled', true);

                    self.csv_productUploadError(false);
                    var date = new Date();
                    self.csv_product_data.upload_time = date.getTime() / 1000;
                },
                progress: function (e, data) {
                    self.call('uploadProgress', [e, data]);
                },
                done: function (e, data) {
                    self.call('uploadDone', [e, data]);

                },
                fail: function (e, data) {
                    self.call('uploadFail', [e, data]);
                }
            });

        },

        csv_productUploadProgress: function (e, data) {
            var $progress = this.csv_product_upload.find('.js-fileupload-progress');
            $progress.show();
            var value = Math.round(100 * data.loaded / data.total) + '%';
            $progress.find('i:first').attr('title', value);
            var date = new Date();
            var interval = parseInt(date.getTime() / 1000 - this.csv_product_data.upload_time);
            $progress.find('span:first').text((interval > 2) ? value : '');

        },

        csv_productUploadDone: function (e, data) {
            $.shop.trace('fileupload done', [data.result, typeof(data.result)]);
            var file = (data.result.files || []).shift();
            this.csv_product_upload.find('.js-fileupload-progress').hide();

            if (!file || file.error) {
                this.csv_productUploadError((file && file.error) ? file.error : 'Invalid server response');
                this.csv_product_upload.find('.fileupload:first').show();
                this.csv_product_uploadgroup.find('.field :input').attr('disabled', null);
            } else if (file && file.controls) {
                this.csv_productUploadError(false);
                this.csv_product_uploadgroup.find('.field:not(:last) :input:visible').attr('disabled', true);
                try {
                    var description = tmpl('file-info-template-js', {
                        'file': file
                    });
                    this.csv_product_upload.find('.value').empty().append(description);
                } catch (e) {
                    $.shop.error('Exception ' + e.message, e);
                }
                if (file.control) {
                    this.csv_product_options.control = file.control;
                }
                if (file.columns_offset) {
                    this.csv_product_options.columns_offset = file.columns_offset;
                }

                this.csv_product_uploadgroup.next('div.field-group').show();
                this.csv_product_form.find('#s-csvproduct-submit, #s-csvproduct-submit :submit').show();


                if (file.encoding) {
                    this.csv_product_form.find(':input[name="encoding"]').val(file.encoding);
                }
                if (file.delimiter) {
                    this.csv_product_form.find(':input[name="delimiter"]').val(file.delimiter);
                }

                var header = '';
                try {
                    header = tmpl('file-header-template-js', {
                        'file': file
                    });
                } catch (e) {
                    $.shop.error('Exception ' + e.message, e);
                }
                this.csv_product_form.find('#s_import_csv_table').html($(file.controls));
                this.csv_product_form.find('#s-csvproduct-info').show().prepend(header);
                var self = this;
                setTimeout(function () {
                    self.csv_productInitControls();
                    self.form = self.csv_product_form;
                    self.csv_productMode('setup', 1);
                }, 50);
            }
        },

        csv_productInitControls: function () {
            this.csv_product_table_cells = this.csv_product_form.find('table.s-csv:first td');
            this.csv_product_form_primary = this.csv_product_form.find('select[name="primary"], select[name="secondary"]');

            var self = this;
            this.csv_product_form_primary.change(function () {
                var $this = $(this);
                self.csv_productMapPrimaryHandler($this, $this.val());
            });

            this.csv_product_form_map = this.csv_product_form.find('select[name^="csv_map\["]').change(function () {
                var $this = $(this);
                var value;
                switch (self.csv_product_options.control) {
                    case 'Csvtable':
                        value = $(this).val();
                        break;
                    case 'Csvmap':
                        value = parseInt($this.val(), 10);
                        break;
                    default:
                        /*do nothing*/
                        break;
                }
                self.csv_productMapHandler($this, value);
            });
            this.csv_product_form_map.filter(function (index, el) {
                return (index == 0) || ($(el).val() == '-1');
            }).change();
            if (this.csv_product_data.primary_callback) {
                clearTimeout(this.csv_product_data.primary_callback);
            }
            this.csv_product_data.primary_callback = setTimeout(function () {
                self.csv_product_form_primary.change();
            }, 100);

        },

        csv_productInitAutocomplete: function ($el) {
            var self = this;
            ($el || this.csv_product_form).find(':input:visible.js-autocomplete-csv:not(.ui-autocomplete-input)').autocomplete({
                source: '?action=autocomplete&type=feature',
                minLength: 2,
                delay: 300,
                select: self.csv_productAutocompleteFeature/*,
                 focus: self.csv_productAutocompleteFeature*/
            });
        },

        csv_productSettingsAdvanced: function ($el) {
            $el.parents('.field').slideUp();
            $el.parents('.field-group').next('.field-group').slideDown(200);
        },

        /**
         *
         * @param event
         * @param ui
         * @returns {boolean}
         */
        csv_productAutocompleteFeature: function (event, ui) {
            $.shop.trace('autocomplete', ui.item);
            var $select = $(this).parent('td').find('select:first');
            var $option = $select.find('option[value="features:' + ui.item.value + '"]');
            if ($option.length) {
                $select.val('features:' + ui.item.value).trigger('change');
            } else {
                $option = $('<option></option>');
                $option.val('features:' + ui.item.value);
                $option.text(ui.item.name);
                $option.attr('title', ui.item.label);
                $select.find('option[value="features:%s"]').before($option);
                $select.val('features:' + ui.item.value).trigger('change');
            }
            return false;
        },

        csv_productAutocompleteReset: function ($el) {
            $el.parent('td').find('select:first').val('-1').trigger('change');
        },

        csv_productUploadFail: function (e, data) {
            this.csv_product_uploadgroup.find('.field :input').attr('disabled', null);
            $.shop.trace('fileupload fail', [data.textStatus, data.errorThrown]);
            this.csv_productUploadError(data.errorThrown || 'error');
            this.csv_product_upload.find('.js-fileupload-progress').hide();
            this.csv_product_upload.find('.fileupload:first').show();
        },

        csv_productUploadError: function (error) {
            var $error_container = this.csv_product_upload.find('.errormsg');
            if (error) {
                $error_container.show().find('span:first').text(error).show();
            } else {
                $error_container.hide().find('span:first').text('').hide();
            }
        },

        csv_productMapHandler: function ($this, value) {
            switch (this.csv_product_options.control) {
                case 'Csvtable':
                    var $parent = $this.parent('td');
                    var changed = $parent.hasClass('selected') || $parent.hasClass('ignored');
                    if (value.match(/^features:%s/)) {
                        $parent.find('select').hide();
                        $parent.find('a.js-action').show();
                        $parent.find('input:hidden').show().focus();
                        this.csv_productInitAutocomplete($parent);
                    } else {
                        $parent.find('input:visible').hide();
                        $parent.find('a.js-action').hide();

                        $parent.find('select').show();
                    }
                    var columns = $this.attr('name').match(/\[(\d+(:[^\]]+)?)]$/)[1].split(':') || [-1];
                    var selector = '[data-column="' + columns.join(':') + '"]';
                    var table_selector = [];
                    var i;
                    for (i = 0; i < columns.length; i++) {
                        table_selector.push(':nth-child(' + (2 + this.csv_product_options.columns_offset + parseInt(columns[i])) + ')');
                    }

                    var $header = $('#s_import_csv_header').find('>li');
                    if ((value !== null) && value != '-1') {
                        $header.filter(selector).removeClass('ignored');
                        for (i = 0; i < table_selector.length; i++) {
                            this.csv_product_table_cells.filter(table_selector[i]).removeClass('ignored');
                        }
                    } else {
                        $header.filter(selector).addClass('ignored');
                        for (i = 0; i < table_selector.length; i++) {
                            this.csv_product_table_cells.filter(table_selector[i]).addClass('ignored');
                        }
                    }

                    var counter = {
                        'selected': 0,
                        'total': 0,
                        'ignored': 0
                    };

                    counter.total = $header.length;
                    counter.ignored = $header.filter('.ignored').length;
                    counter.selected = counter.total - counter.ignored;

                    var text = $_(counter.selected, '%d column will be processed').replace(/%d/g, counter.selected);
                    if (counter.ignored) {
                        text += ', ';
                        text += $_(counter.ignored, '%d column will be ignored').replace(/%d/g, counter.ignored);
                    }
                    this.csv_product_form.find('#s-csvproduct-info .js-csv-columns-counter').text(text);
                    if (changed || true) {
                        if (this.csv_product_data.primary_callback) {
                            clearTimeout(this.csv_product_data.primary_callback);
                        }
                        var self = this;
                        this.csv_product_data.primary_callback = setTimeout(function () {
                            self.csv_product_form_primary.change();
                        }, 100);
                    }
                    break;
                case 'Csvmap':
                    var $container = $this.parents('tr');
                    if (value < 0) {
                        $container.addClass(this.csv_product_options.ignore_class);
                    } else {
                        $container.removeClass(this.csv_product_options.ignore_class);
                    }
                    break;
                default:
                    /*do nothing*/
                    break;
            }
        },

        csv_productMapPrimaryHandler: function ($this, value) {

            var sub_class = $this.attr('name');
            switch (this.csv_product_options.control) {
                case 'Csvtable':
                    this.csv_product_table_cells.filter('.selected.' + sub_class).removeClass('selected ' + sub_class);
                    var $header = this.csv_product_form.find('#s_import_csv_header > li');
                    $header.filter('.selected.' + sub_class).removeClass('selected ' + sub_class);
                    var self = this;
                    var exists = false;
                    this.csv_product_form_map.each(function (index, el) {
                        var $el = $(el);
                        if ($el.val() == value) {
                            exists = true;
                            var column = parseInt(($el.attr('name').match(/\[(\d+)(:[^\]]+)?]$/) || [-1, -1])[1]);
                            var offset = 2 + self.csv_product_options.columns_offset;
                            var selector = ':nth-child(' + (offset + column) + ')';
                            self.csv_product_table_cells.filter(selector).addClass('selected ' + sub_class);
                            $header.filter('[data-column="' + column + '"]:first').addClass('selected ' + sub_class);
                            return false;
                        }
                        return true;
                    });

                    var $select = $this.parent();
                    $select.find('i.icon16.exclamation').remove();
                    if (!exists) {
                        $select.prepend('<i class="icon16 exclamation"></i>');
                    }
                    break;
                case 'Csvmap':
                    this.csv_product_form.find('tr.selected.' + sub_class + ':first').parents('tbody').find('tr.selected').removeClass('selected ' + sub_class);
                    var selector = '[name$="' + value + '\\]"]:first';
                    var $selected = this.csv_product_form_map.filter(selector);
                    var $non_sku = this.csv_product_form.find('tbody:not(.sku)');
                    if ($selected.length) {
                        $selected.parents('tr').addClass('selected ' + sub_class);
                        switch (sub_class) {
                            case 'primary':
                                $non_sku.show();
                                $non_sku.find(':input').attr('disabled', null);
                                break;
                        }
                    } else {
                        switch (sub_class) {
                            case 'primary':
                                $non_sku.find(':input').attr('disabled', true);
                                $non_sku.hide();
                                break;

                        }
                    }
                    break;
                default:
                    /*do nothing*/
                    break;
            }
        },

        csv_productSubmitHandler: function (form) {
            try {
                var $form = $(form);
                $.shop.trace('csv_productSubmitHandler', $form);
                this.csv_productHandler(form);
                this.csv_product_cleanup = false;
            } catch (e) {
                $('#s-csvproduct-transport-group').find(':input').attr('disabled', false);
                $.shop.error('Exception: ' + e.message, e);
            }
            return false;
        },

        csv_productHandler: function (element) {
            var self = this;
            self.csv_product_data.progress = true;
            self.form = $(element);
            var data = self.form.serialize();
            this.csv_productMode('run', null);
            var url = $(element).attr('action');
            $.ajax({
                url: url,
                data: data,
                type: 'post',
                dataType: 'json',
                success: function (response) {
                    // response = $.parseJSON(response);
                    if (response.error) {
                        self.csv_product_data.progress = false;
                        self.form.find('.errormsg').text(response.error);
                        self.csv_productMode('error', null);

                    } else {
                        self.form.find('.progressbar').attr('title', '0.00%');
                        self.form.find('.progressbar-description').text('0.00%');
                        self.form.find('.js-progressbar-container').show();

                        if (response.file) {
                            var $link = $('#s-csvproduct-report').find('.value a:first');
                            $link.attr('href', ('' + $link.attr('href')).replace(/&file=.*$/, '') + '&file=' + response.file);
                        }

                        self.csv_product_data.ajax_pull[response.processId] = [];
                        self.csv_product_data.ajax_pull[response.processId].push(setTimeout(function () {
                            $.wa.errorHandler = function (xhr) {
                                return !((xhr.status >= 500) || (xhr.status == 0));
                            };
                            self.csv_productProgressHandler(url, response.processId, response);
                        }, 3000));
                        self.csv_product_data.ajax_pull[response.processId].push(setTimeout(function () {
                            self.csv_productProgressHandler(url, response.processId);
                        }, 2000));
                    }
                },
                error: function () {
                    self.csv_productError();
                }
            });
            return false;
        },

        csv_productProgressHandler: function (url, processId, response) {
            // display progress
            // if not completed do next iteration
            var self = this;
            var $bar = null;
            if (response && response.ready) {
                $.wa.errorHandler = null;
                var timer;
                while (timer = self.csv_product_data.ajax_pull[processId].pop()) {
                    if (timer) {
                        clearTimeout(timer);
                    }
                }

                $bar = self.form.find('.progressbar .progressbar-inner');
                $bar.css({
                    'width': '100%'
                });
                $.shop.trace('cleanup', response.processId);
                if (response.report) {
                    $('#s-csvproduct-report').find('.value:first').html(response.report);
                }
                if (response.file) {
                    var $link = $('#s-csvproduct-report').find('.value a:first');
                    $link.attr('href', ('' + $link.attr('href')).replace(/&file=.*$/, '') + '&file=' + response.file);
                }

                $.shop.trace('response.collision@progress', response.collision || false);
                if (response && response.collision) {
                    self.csv_productCollision(response.collision);
                }

                if (response.rows_count) {
                    self.csv_product_form.find('table.s-csv > tfoot .js-csv-total-count').html(response.rows_count);
                }

                if (!this.csv_product_cleanup) {
                    this.csv_product_cleanup = true;
                    $.ajax({
                        url: url,
                        data: {
                            'processId': response.processId,
                            'cleanup': 1
                        },
                        dataType: 'json',
                        type: 'post',
                        success: function (response) {
                            self.csv_productOnComplete(response);
                        },
                        error: function (jqXHR, textStatus, errorThrown) {
                            $.shop.trace('report error', [textStatus, errorThrown, jqXHR.response]);
                        }
                    });
                }

            } else if (response && response.error) {
                self.csv_productError(response.error);
            } else {
                if (response) {
                    self.csv_productProgress(response);
                }

                var ajax_url = url;
                var id = processId;

                self.csv_product_data.ajax_pull[id].push(setTimeout(function () {
                    $.ajax({
                        url: ajax_url,
                        data: {
                            'processId': id
                        },
                        dataType: 'json',
                        type: 'post',
                        success: function (response) {
                            self.csv_productProgressHandler(url, response ? response.processId || id : id, response);
                        },
                        error: function () {
                            self.csv_productProgressHandler(url, id, null);
                        }
                    });
                }, 2500));
            }
        },

        csv_productProgress: function (response) {

            var $description = this.form.find('.progressbar-description');
            if (response && (typeof(response.progress) != 'undefined')) {
                var $bar = this.form.find('.progressbar .progressbar-inner');
                var progress = parseFloat(response.progress.replace(/,/, '.'));
                $bar.animate({
                    'width': progress + '%'
                });

                var message = response.progress;
                $bar.parents('.progressbar').attr('title', response.progress);
                $description.text(message);

            }
            if (response && (typeof(response.warning) != 'undefined')) {
                $description.append('<i class="icon16 exclamation"></i><p>' + response.warning + '</p>');
            }
        },

        csv_productError: function (error) {
            if (error) {
                this.form.find('.errormsg').text(error).show();
            } else {
                this.form.find('.errormsg').text('').hide();
            }
            this.csv_productMode('error', null);
        },

        csv_productCollision: function (collisions) {

            var collision = [];
            var i, n, title, $td, matches;
            for (i = 0; i < collisions.length; i++) {
                collision = collisions[i];
                title = $_('Collision at rows #') + collision.rows.join(', ');
                for (n = 0; n < collision.rows.length; n++) {
                    $td = $('tr.js-row-' + collision.rows[n] + ' > td:nth-child(3)');
                    $td.data('collision', collision.rows);
                    $td.text('');
                    $td.append('<i class="icon16 exclamation js-collision" title="' + title + '"></i>');
                    if (matches = collision.key.match(/^(c|p):u:(\d+)$/)) {
                        var href = '';
                        switch (matches[1]) {
                            case 'c':
                                href = '?action=products#/products/category_id=' + matches[2] + '&view=table';
                                break;
                            case 'p':
                                href = '?action=products#/product/' + matches[2] + '/';
                                break;
                        }
                        $td.append('<a href="' + href + '" target="_blank"><i class="icon16 new-window" title="' + title + '"></i></a>');
                    }
                }
            }
            var self = this;
            $('i.js-collision').hover(function () {
                self.csv_productCollisionHighlight($(this), true);
            }, function () {
                self.csv_productCollisionHighlight($(this), false);
            })
        },
        csv_productCollisionHighlight: function ($el, hover) {
            var collision = $el.parent().data('collision') || [];
            var $context = $el.parents('tbody:first');
            for (var n = 0; n < collision.length; n++) {
                $context.find('tr.js-row-' + collision[n] + '').css('color', hover ? 'red' : '');
            }
        },

        /**
         *
         * @param stage
         * @param mode
         */
        csv_productMode: function (stage, mode) {
            var $emulate = $(':input[name="emulate"]');
            if (mode === null) {
                mode = parseInt($emulate.val());
            } else if (mode === true) {
                mode = parseInt($emulate.val());
                $emulate.val(0);
            } else {
                stage = 'setup';
                $emulate.val(mode);
            }
            var mode_name_ = !mode ? 'emulate' : 'import';
            var mode_name = mode ? 'emulate' : 'import';

            $.shop.trace('csv_productMode', [stage, mode, mode_name]);

            var $submit = this.form.find(':submit');
            var $navigator = this.form.find('.s-csv-import-navigator');
            var $progressbar = this.form.find('.progressbar');
            switch (stage) {
                case 'run':

                    this.form.find('.errormsg').text('');
                    this.form.find(':input:not(:disabled)').attr('disabled', true);
                    $submit.hide().attr('disabled', true);
                    $progressbar.css('width', (mode_name == 'emulate') ? '40%' : '70%');
                    $progressbar.find('.progressbar-inner').css('width', '0%');
                    $progressbar.show();
                    this.form.find('a[href="\#/csv_product/settings/advanced/"]').parents('.field').slideUp();
                    var $container = this.form.find('#s-csvproduct-submit');
                    $container.find('span[data-mode="' + mode_name + '"]').show();
                    $container.find('span[data-mode="' + mode_name_ + '"]').hide();
                    if (mode_name == 'import') {
                        $navigator.find('>li[data-mode="finish"]').addClass('s-current');
                    }

                    break;

                case 'complete':
                    this.form.find('.js-progressbar-container').hide();
                    $progressbar.hide();
                    this.form.find('.shop-ajax-status-loading').remove();

                    if (mode_name == 'emulate') {
                        this.csv_productMode('setup', 0);
                    } else {
                        $.storage.del('shop/hash');
                        $navigator.find('>li[data-mode="' + mode_name + '"]').removeClass('s-current').addClass('s-passed');
                        $navigator.find('>li[data-mode="finish"]').removeClass('s-current').addClass('s-passed');
                    }

                    break;
                case 'error':
                    this.form.find(':input:not(.js-ignore-change)').attr('disabled', false);
                    this.form.find(':submit').show();
                    this.form.find('.js-progressbar-container').hide();
                    this.form.find('.shop-ajax-status-loading').remove();

                    this.csv_productMode('setup', mode);
                    break;
                case 'setup':
                default://reset into setup stage
                    this.form.find(':input:not(.js-ignore-change)').attr('disabled', false);
                    $submit.val($submit.data(mode_name + '-value'));
                    $submit.removeClass($submit.data(mode_name_ + '-class')).addClass($submit.data(mode_name + '-class'));
                    $submit.show().attr('disabled', false);

                    var $el = this.form.find('a[href="\#/csv_product/settings/advanced/"]');
                    $navigator.find('>li[data-mode="' + mode_name + '"]').removeClass('s-passed').addClass('s-current');
                    $navigator.find('>li[data-mode="finish"]').removeClass('s-passed s-current');
                    if (mode_name == 'emulate') {

                        if (!$el.parents('.field-group').next('.field-group').is(':visible')) {
                            $el.parents('.field').slideDown();
                        }
                        $navigator.find('>li[data-mode="' + mode_name_ + '"]').removeClass('s-current');
                    } else {
                        $navigator.find('>li[data-mode="' + mode_name_ + '"]').removeClass('s-current').addClass('s-passed');
                        $el.parents('.field').slideUp();
                        this.csv_productFormChanged(this.form, true);
                        var self = this;
                        this.form.unbind('change.mode').bind('change.mode', function () {
                            if (self.csv_productFormChanged($(this))) {
                                self.form.find('#s-csvproduct-report').slideUp();
                                self.csv_productMode('setup', 1);
                            } else {
                                self.form.find('#s-csvproduct-report').slideDown();
                                self.csv_productMode('setup', 0);
                            }
                        })
                    }

                    break;
            }
        },

        csv_productFormChanged: function ($scope, update) {
            var changed = false;
            var selector = ':input:not(.js-ignore-change)';
            $.shop.trace('csv_productFormChanged', [$scope, ($scope.find(selector)).length]);
            ($scope.find(selector)).each(function () {
                /**
                 * @this HTMLInputElement
                 */

                var type = this.name ? ($(this).attr('type') || this.tagName).toLowerCase() : 'noname';

                switch (type) {
                    case 'input':
                    case 'text':
                    case 'search':
                    case 'textarea':
                        /**
                         * @this HTMLInputElement
                         */
                        if (this.defaultValue != this.value) {
                            changed = true;
                            if (update) {
                                this.defaultValue = this.value;
                            }
                        }
                        break;
                    case 'radio':
                    case 'checkbox':
                        /**
                         * @this HTMLInputElement
                         */
                        if (this.defaultChecked != this.checked) {
                            changed = true;
                            if (update) {
                                this.defaultChecked = this.checked;
                            }
                        }
                        break;
                    case 'select':
                        if (this.length) {
                            $(this).find('option').each(function () {
                                /**
                                 * @this HTMLSelectElement
                                 */
                                if (this.defaultSelected != this.selected) {
                                    changed = true;
                                    if (update) {
                                        this.defaultSelected = this.selected;
                                    }
                                }
                                return update || !changed;
                            });
                        }
                        break;
                    case 'file':
                        /**
                         * @this HTMLInputElement
                         */
                        if (this.value) {
                            changed = true;
                            if (update) {
                                this.value = null;
                            }
                        }
                        break;
                    case 'reset':
                    case 'button':
                    case 'submit':
                        // ignore it
                        break;
                    case 'noname':
                        break;
                    case 'hidden':
                        // do nothing
                        break;
                    default:
                        $.shop.error('$.importexport.csv_productFormChanged unsupported type ' + type, [type, this]);
                        break;
                }
                if (!update && changed) {
                    $.shop.trace('$.importexport.csv_productFormChanged', [this, changed, this.defaultValue, this.value]);
                }
                return update || !changed;
            });
            return changed;
        },

        csv_productOnComplete: function (response) {
            var $report = $("#s-csvproduct-report");
            $report.show();
            if (response && response.report) {
                $report.find('.value:first').html(response.report);
            }

            this.csv_productMode('complete', true);
        },

        csv_productRows: function ($el) {
            var self = this;
            var data = {
                'file': this.csv_product_form.find(':input[name="file"]').val(),
                'row': self.csv_product_form.find('table.s-csv > tbody >tr:last').attr('class').replace(/^js-row-/, ''),
                'limit': 50
            };
            var $icon = $el.find('.icon16:first');
            if ($icon.length) {
                $el.find('.icon16:first').removeClass('plus').addClass('loading');
            } else {
                $el.append('<i class="icon16 loading js-row-text"></i>');
                $icon = $el.find('.icon16:first');
                $el.find('b').hide();
            }
            var url = '?module=csv&action=productview';
            $.ajax({
                url: url,
                data: data,
                type: 'post',
                dataType: 'json',
                success: function (response) {
                    // response = $.parseJSON(response);
                    if (response.error) {
                        // self.form.find('.errormsg').text(response.error);
                    } else {
                        if (response.data && response.data.tbody) {
                            self.csv_product_form.find('table.s-csv > tbody').append(response.data.tbody);
                            self.csv_product_table_cells = self.csv_product_form.find('table.s-csv:first td');
                            self.csv_product_form_map.filter('[value="-1"],:first').change();

                            if (response.data.rows_count) {
                                var $foot = self.csv_product_form.find('table.s-csv > tfoot');
                                $foot.find('.js-csv-total-count').html(response.data.rows_count);
                                $foot.find('.js-csv-current-count').html(response.data.current);
                                if (parseInt(response.data.current) == parseInt(response.data.rows_count)) {
                                    $foot.find('.js-csv-more').hide();
                                }
                            }

                            $('i.js-collision').hover(function () {
                                self.csv_productCollisionHighlight($(this), true);
                            }, function () {
                                self.csv_productCollisionHighlight($(this), false);
                            });

                            if (s_csv_setsize && (typeof(s_csv_setsize) == 'function')) {
                                setTimeout(function () {
                                    s_csv_setsize();
                                }, 50);
                            }
                        }
                        //append response
                    }

                    if ($icon.hasClass('js-row-text')) {
                        $icon.remove();
                        $el.find('b').show();
                    } else {
                        $icon.removeClass('loading').addClass('plus');
                    }
                },
                error: function () {
                    //     self.csv_productError();

                    if ($icon.hasClass('js-row-text')) {
                        $icon.remove();
                    } else {
                        $icon.removeClass('loading').addClass('plus');
                    }
                }
            });
            //load html via ajax & append it to tbody
            //update total/current rows count
        },

        csv_product_helper: {
            id2name: function (id) {
                if (id.match(/:/)) {
                    id = id.split(":");
                    for (var i = 0; i < id.length; i++) {
                        id[i] = this.id2name(id[i]);
                    }
                    return id.join(', ');
                } else {
                    var name = '';
                    id = parseInt(id);
                    ++id;
                    while (id > 0) {
                        var mod = (id - 1) % 26;
                        name = String.fromCharCode(65 + mod) + '' + name;
                        id = Math.floor((id - mod) / 26);
                    }
                    return name;
                }
            }
        }
    });
}
