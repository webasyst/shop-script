/**
 *
 * @names csv_product*
 * @property {} csv_product_options
 * @method csv_productInit
 * @method csv_productAction
 * @method csv_productBlur
 */

if (typeof($) != 'undefined') {
    $.extend($.importexport = $.importexport || {}, {
        csv_product: true,
        csv_product_options: {
            'ignore_class': 'gray'
        },
        csv_product_form: null,
        csv_product_upload: null,
        csv_product_uploadgroup: null,
        csv_product_data: {
            ajax_pull: {},
            progress: false,
            upload_time: null
        },

        csv_productOnInit: function () {

        },

        csv_productInit: function () {
            $.shop.trace('$.importexport.csv_productInit');
            var self = this;

            this.csv_product_form = $("#s-csvproduct");

            this.csv_product_form.submit(function () {
                return self.call('SubmitHandler', [this]);
            });

            $.importexport.products.init(this.csv_product_form);

            if (this.csv_product_form.find('.fileupload:first').length) {
                this.csv_product_form.find(':submit').hide();
                this.csv_productUploadInit();
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
                    self.csv_product_uploadgroup.find('.field:not(:last) :input').attr('disabled', true);

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
            } else if (file && file.controls) {
                this.csv_productUploadError(false);
                this.csv_product_uploadgroup.find('.field:not(:last) :input').attr('disabled', true);
                try {
                    var description = tmpl('file-info-template-js', {
                        'file': file
                    });
                    this.csv_product_upload.find('.value').empty().append(description);
                } catch (e) {
                    $.shop.error('Exception ' + e.message, e);
                }
                this.csv_product_uploadgroup.next('div.field-group').show().find('.field:last').after($(file.controls));
                this.csv_product_form.find(':submit').show();
                var self = this;

                this.csv_product_form.find('select[name="primary"], select[name="secondary"]').change(function () {
                    var $this = $(this);
                    self.call('MapPrimaryHandler', [$this, $this.val()])
                }).change();

                this.csv_product_form.find('select[name^="csv_map\["]').change(function () {
                    var $this = $(this);
                    self.csv_productMapHandler($this, parseInt($this.val(), 10))
                }).change();
            }
        },
        csv_productUploadFail: function (e, data) {
            this.csv_product_uploadgroup.find('.field:not(:last) :input').attr('disabled', null);
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
            var $container = $this.parents('tr');
            if (value < 0) {
                $container.addClass(this.csv_product_options.ignore_class);
            } else {
                $container.removeClass(this.csv_product_options.ignore_class);
            }
        },

        csv_productMapPrimaryHandler: function ($this, value) {
            var sub_class = $this.attr('name');
            this.csv_product_form.find('tr.selected.' + sub_class + ':first').parents('tbody').find('tr.selected').removeClass('selected ' + sub_class);
            var selector = ':input[name^="csv_map\["][name$="' + value + '\]"]:first';
            var $selected = this.csv_product_form.find(selector);
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
        },

        csv_productSubmitHandler: function (form) {
            try {
                var $form = $(form);
                $.shop.trace('csv_productSubmitHandler', $form);
                $form.find(':input:visible, :submit').attr('disabled', false).show();
                this.csv_productHandler(form);
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
            self.form.find('.errormsg').text('');
            self.form.find(':input').attr('disabled', true);
            self.form.find(':submit').hide();
            self.form.find('.progressbar .progressbar-inner').css('width', '0%');
            self.form.find('.progressbar').show();
            var url = $(element).attr('action');
            $.ajax({
                url: url,
                data: data,
                type: 'post',
                dataType: 'json',
                success: function (response) {
                    // response = $.parseJSON(response);
                    if (response.error) {
                        self.form.find(':input:visible').attr('disabled', false);
                        self.form.find(':submit').show();
                        self.form.find('.js-progressbar-container').hide();
                        self.form.find('.shop-ajax-status-loading').remove();
                        self.csv_product_data.progress = false;
                        self.form.find('.errormsg').text(response.error);
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
                            self.progressHandler(url, response.processId, response);
                        }, 3000));
                        self.csv_product_data.ajax_pull[response.processId].push(setTimeout(function () {
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

                $.ajax({
                    url: url,
                    data: {
                        'processId': response.processId,
                        'cleanup': 1
                    },
                    dataType: 'json',
                    type: 'post',
                    success: function (response) {
                        // show statistic
                        $.shop.trace('report', response);
                        $("#s-csvproduct-submit").hide();
                        self.form.find('.progressbar').hide();
                        $("#s-csvproduct-report").show();
                        if (response && response.report) {
                            $('#s-csvproduct-report').find('.value:first').html(response.report);
                        }

                        $.storage.del('shop/hash');
                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        $.shop.trace('report error', [textStatus, errorThrown, jqXHR.response]);
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
                    $bar = self.form.find('.progressbar .progressbar-inner');
                    var progress = parseFloat(response.progress.replace(/,/, '.'));
                    $bar.animate({
                        'width': progress + '%'
                    });

                    var message = response.progress;

                    $bar.parents('.progressbar').attr('title', response.progress);
                    $description = self.form.find('.progressbar-description');
                    $description.text(message);

                }
                if (response && (typeof(response.warning) != 'undefined')) {
                    $description = self.form.find('.progressbar-description');
                    $description.append('<i class="icon16 exclamation"></i><p>' + response.warning + '</p>');
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
                            self.progressHandler(url, response ? response.processId || id : id, response);
                        },
                        error: function () {
                            self.progressHandler(url, id, null);
                        }
                    });
                }, 2500));
            }
        }
    });
}
