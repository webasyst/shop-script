/*
 * jQuery File Upload User Interface Plugin 7.4.4
 * https://github.com/blueimp/jQuery-File-Upload
 *
 * Copyright 2010, Sebastian Tschan
 * https://blueimp.net
 *
 * Licensed under the MIT license:
 * http://www.opensource.org/licenses/MIT
 */

/*jslint nomen: true, unparam: true, regexp: true */
/*global define, window, URL, webkitURL, FileReader */

(function (factory) {
    'use strict';
    if (typeof define === 'function' && define.amd) {
        // Register as an anonymous AMD module:
        define([
            'jquery',
            'tmpl',
            'load-image',
            './jquery.fileupload-fp'
        ], factory);
    } else {
        // Browser globals:
        factory(
            window.jQuery,
            window.tmpl,
            window.loadImage
        );
    }
}(function ($, tmpl, loadImage) {
    'use strict';

    // The UI version extends the file upload widget
    // and adds complete user interface interaction:
    $.widget('blueimp.fileupload', $.blueimp.fileupload, {

        options: {
            // By default, files added to the widget are uploaded as soon
            // as the user clicks on the start buttons. To enable automatic
            // uploads, set the following option to true:
            autoUpload: false,
            // To limit the number of concurrent uploads,
            // set the following option to an integer greater than 0:
            limitConcurrentUploads: 1,
            // The following option limits the number of files that are
            // allowed to be uploaded using this widget:
            maxNumberOfFiles: undefined,
            // The maximum allowed file size:
            maxFileSize: undefined,
            // The minimum allowed file size:
            minFileSize: undefined,
            // The regular expression for allowed file types, matches
            // against either file type or file name:
            acceptFileTypes:  /.+$/i,
            // The regular expression to define for which files a preview
            // image is shown, matched against the file type:
            previewSourceFileTypes: /^image\/(gif|jpeg|png)$/,
            // The maximum file size of images that are to be displayed as preview:
            previewSourceMaxFileSize: 5000000, // 5MB
            // The maximum width of the preview images:
            previewMaxWidth: 80,
            // The maximum height of the preview images:
            previewMaxHeight: 80,
            // By default, preview images are displayed as canvas elements
            // if supported by the browser. Set the following option to false
            // to always display preview images as img elements:
            previewAsCanvas: true,
            // The ID of the upload template:
            uploadTemplateId: 'template-upload',
            // The ID of the preload template:
            preloadTemplateId: 'template-preload',
            // The ID of the download template:
            downloadTemplateId: 'template-download',
            // The ID of the group container template:
            groupContainerTemplateId: 'template-group-container',
            // The container for the list of files. If undefined, it is set to
            // an element with class "files" inside of the widget element:
            filesContainer: undefined,
            // Preloading container of main UI for manage images and products
            // If undefined, it is set to an element with class "preload-files"
            // inside of the widget element
            preloadContainer: undefined,
            // By default, files are appended to the files container.
            // Set the following option to true, to prepend files instead:
            prependFiles: false,
            // The expected data type of the upload response, sets the dataType
            // option of the $.ajax upload requests:
            dataType: 'json',

            // The add callback is invoked as soon as files are added to the fileupload
            // widget (via file input selection, drag & drop or add API call).
            // See the basic file upload widget for more information:
            add: function (e, data) {
                var that = $(this).data('blueimp-fileupload') ||
                        $(this).data('fileupload'),
                    options = that.options,
                    files = data.files;

                $(this).fileupload('process', data).done(function () {
                    that._adjustMaxNumberOfFiles(-files.length);
                    data.maxNumberOfFilesAdjusted = true;
                    data.files.valid = data.isValidated = that._validate(files);

                    if (data.files.valid) {

                        $('#s-product-type-container').show();
                        $('#s-image-upload-dropbox').hide();
                        $('#s-image-upload-explanation').show();

                        // Will save two contexts.
                        // First in main UI table (render preload)
                        // Second in progress dialog
                        data.context = [];

                        that._renderPreload(e, data).done(
                            function () {
                                data.context.push(that._renderUpload(files).data('data', data));
                                options.filesContainer[
                                   options.prependFiles ? 'prepend' : 'append'
                                ](data.context[1]);
                                that._forceReflow(data.context[1]);
                                if ((that._trigger('added', e, data) !== false) &&
                                        (options.autoUpload || data.autoUpload) &&
                                        data.autoUpload !== false && data.isValidated) {
                                    data.submit();
                                }
                            }
                        );
                    }
                });

            },

            // Callback for the start of each file upload request:
            send: function (e, data) {
                var that = $(this).data('blueimp-fileupload') ||
                        $(this).data('fileupload');
                if (!data.isValidated) {
                    if (!data.maxNumberOfFilesAdjusted) {
                        that._adjustMaxNumberOfFiles(-data.files.length);
                        data.maxNumberOfFilesAdjusted = true;
                    }
                    if (!that._validate(data.files)) {
                        return false;
                    }
                }
                if (data.context[1] && data.dataType &&
                        data.dataType.substr(0, 6) === 'iframe') {
                    // Iframe Transport does not support progress events.
                    // In lack of an indeterminate progress bar, we set
                    // the progress to 100%, showing the full animated bar:
                    data.context[1]
                        .find('.progress').addClass(
                            !$.support.transition && 'progress-animated'
                        )
                        .attr('aria-valuenow', 100)
                        .find('.bar').css(
                            'width',
                            '100%'
                        );
                }
                return that._trigger('sent', e, data);
            },


            // Callback for successful uploads:
            done: function (e, data) {
                var that = $(this).data('blueimp-fileupload') ||
                        $(this).data('fileupload'),
                    files = that._getFilesFromResponse(data),
                    template,
                    deferred;
                if (data.context[1]) {
                    data.context[1].each(function (index) {
                        var file = files[index] ||
                                {error: 'Empty file upload result'},
                            deferred = that._addFinishedDeferreds();
                        if (file.error) {
                            that._adjustMaxNumberOfFiles(1);
                        } else {
                            that.options.report.file_count += 1;
                            $('#s-imagesproduct-report').html(
                                file.report_message
                            );
                        }
                        that._transition($(this)).done(
                            function () {
                                var node = $(this);
                                template = that._renderDownload([file])
                                    .replaceAll(node);
                                that._forceReflow(template);
                                that._transition(template).done(
                                    function () {
                                        data.context[1] = $(this);
                                        that._trigger('completed', e, data);
                                        that._trigger('finished', e, data);
                                        if (!file.error) {
                                            setTimeout(function() {
                                                data.context[0].hide(200, function() {
                                                    var tr = $(this).parents('tr:first');
                                                    if (!tr.find('li:visible:first').length) {
                                                        tr.hide(200);
                                                    }
                                                });
                                                data.context[1].hide(200, function() {
                                                    var ul = data.context[1].parent();
                                                    if (!ul.find('li:visible:first').length) {
                                                        that._hideDialog();
                                                        $('.fileupload-buttonbar .start').hide();
                                                    }
                                                });
                                            }, 5000);
                                        } else {
                                            data.context[0].find('.error').show().text(file.error);
                                            data.context[0].addClass('error');
                                        }
                                        deferred.resolve();
                                    }
                                );
                            }
                        );
                    });
                } else {
                    if (files.length) {
                        $.each(files, function (index, file) {
                            if (!file.error) {
                                that.options.report.file_count += 1;
                                $('#s-imagesproduct-report').html(
                                    file.report_message
                                );
                            }
                            if (data.maxNumberOfFilesAdjusted && file.error) {
                                that._adjustMaxNumberOfFiles(1);
                            } else if (!data.maxNumberOfFilesAdjusted &&
                                    !file.error) {
                                that._adjustMaxNumberOfFiles(-1);
                            }
                        });
                        data.maxNumberOfFilesAdjusted = true;
                    }
                    template = that._renderDownload(files)
                        .appendTo(that.options.filesContainer);
                    that._forceReflow(template);
                    deferred = that._addFinishedDeferreds();
                    that._transition(template).done(
                        function () {
                            data.context[1] = $(this);
                            that._trigger('completed', e, data);
                            that._trigger('finished', e, data);
                            if (!file.error) {
                                setTimeout(function() {
                                    data.context[0].hide(200, function() {
                                        var tr = $(this).parents('tr:first');
                                        if (!tr.find('li:visible:first').length) {
                                            tr.hide(200);
                                        }
                                    });
                                    data.context[1].hide(200, function() {
                                        var ul = data.context[1].parent();
                                        if (!ul.find('li:visible:first').length) {
                                            that._hideDialog();
                                            $('.fileupload-buttonbar .start').hide();
                                        }
                                    });
                                }, 5000);
                            } else {
                                data.context[0].find('.error').show().text(file.error);
                                data.context[0].addClass('error');
                            }
                            deferred.resolve();
                        }
                    );
                }
            },

            // Callback for failed (abort or error) uploads:
            fail: function (e, data) {
                var that = $(this).data('blueimp-fileupload') ||
                        $(this).data('fileupload'),
                    template,
                    deferred;
                if (data.maxNumberOfFilesAdjusted) {
                    that._adjustMaxNumberOfFiles(data.files.length);
                }
                if (data.context[1]) {
                    data.context[1].each(function (index) {
                        if (data.errorThrown !== 'abort') {
                            var file = data.files[index];
                            file.error = file.error || data.errorThrown ||
                                true;
                            deferred = that._addFinishedDeferreds();
                            that._transition($(this)).done(
                                function () {
                                    var node = $(this);
                                    template = that._renderDownload([file])
                                        .replaceAll(node);
                                    that._forceReflow(template);
                                    that._transition(template).done(
                                        function () {
                                            data.context[1] = $(this);
                                            that._trigger('failed', e, data);
                                            that._trigger('finished', e, data);
                                            deferred.resolve();
                                        }
                                    );
                                }
                            );
                        } else {
                            deferred = that._addFinishedDeferreds();
                            that._transition($(this)).done(
                                function () {
                                    $(this).remove();
                                    that._trigger('failed', e, data);
                                    that._trigger('finished', e, data);
                                    deferred.resolve();
                                }
                            );
                        }
                    });
                } else if (data.errorThrown !== 'abort') {
                    data.context[1] = that._renderUpload(data.files)
                        .appendTo(that.options.filesContainer)
                        .data('data', data);
                    that._forceReflow(data.context[1]);
                    deferred = that._addFinishedDeferreds();
                    that._transition(data.context[1]).done(
                        function () {
                            data.context[1] = $(this);
                            that._trigger('failed', e, data);
                            that._trigger('finished', e, data);
                            deferred.resolve();
                        }
                    );
                } else {
                    that._trigger('failed', e, data);
                    that._trigger('finished', e, data);
                    that._addFinishedDeferreds().resolve();
                }
            },
            // Callback for upload progress events:
            progress: function (e, data) {
//                if (data.context) {
//                    var progress = Math.floor(data.loaded / data.total * 100);
//                    data.context.find('.progress')
//                        .attr('aria-valuenow', progress)
//                        .find('.bar').css(
//                            'width',
//                            progress + '%'
//                        );
//                }
                if (data.context[1]) {
                    data.context[1].find('.s-upload-oneimage-progress').css(
                        'width',
                        parseInt(data.loaded / data.total * 90, 10) + '%'
                    );
                }
            },
            // Callback for global upload progress events:
            progressall: function (e, data) {
//                var $this = $(this),
//                    progress = Math.floor(data.loaded / data.total * 100),
//                    globalProgressNode = $this.find('.fileupload-progress'),
//                    extendedProgressNode = globalProgressNode
//                        .find('.progress-extended');
//                if (extendedProgressNode.length) {
//                    extendedProgressNode.html(
//                        ($this.data('blueimp-fileupload') || $this.data('fileupload'))
//                            ._renderExtendedProgress(data)
//                    );
//                }
//                globalProgressNode
//                    .find('.progress')
//                    .attr('aria-valuenow', progress)
//                    .find('.bar').css(
//                        'width',
//                        progress + '%'
//                    );
                var $this = $(this);
                $this.find('.fileupload-progressbar').css(
                    'width',
                    parseInt(data.loaded / data.total * 95, 10) + '%'
                );
                $("#s-upload-filescount").html(parseInt(data.loaded / data.total * 95, 10) + '%');
                $this.find('.progress-extended').each(function () {
                        $(this).html(
                            $this.data('fileupload')
                                ._renderExtendedProgress(data)
                        );
                });
            },
            // Callback for uploads start, equivalent to the global ajaxStart event:
            start: function (e) {
                var that = $(this).data('blueimp-fileupload') ||
                        $(this).data('fileupload');
                that._resetFinishedDeferreds();

                /*
                that._transition($(this).find('.fileupload-progress')).done(
                    function () {
                        that._trigger('started', e);
                    }
                );
                */

                // remove containers with empty list of images
                that.options.preloadContainer.find('tr').each(function() {
                    var self = $(this);
                    if (!$(this).find('ul li:first').length) {
                        self.remove();
                    }
                });

                $("#s-upload-filescount").show().empty();
                $('#s-upload-step1-buttons .cancel').text($_('Stop upload'));
                $("#s-upload-error").hide();

                var progressbar = $("#s-upload-progressbar").css('width', '0%');
                progressbar.parents('.progressbar:first').show();
                that._showDialog();
                return false;
            },

            // Callback for uploads stop, equivalent to the global ajaxStop event:
            stop: function (e) {
                var that = $(this).data('blueimp-fileupload') ||
                        $(this).data('fileupload'),
                    deferred = that._addFinishedDeferreds();

                $.when.apply($, that._getFinishedDeferreds())
                    .done(function () {
                        that._trigger('stopped', e);
                    });
                that._transition($(this).find('.fileupload-progress')).done(
                    function () {
//                        $(this).find('.progress')
//                            .attr('aria-valuenow', '0')
//                            .find('.bar').css('width', '0%');
//                        $(this).find('.progress-extended').html('&nbsp;');
                        $("#s-upload-filescount").html('100%');
                        $('#s-upload-progressbar').animate({
                            'width': '100%'
                        });
                        deferred.resolve();
                    }
                );
            },

            change: function() {
                ($(this).data('blueimp-fileupload') || $(this).data('fileupload'))._clear();
            },

            paste: function(e) {
                if (!$(e.srcElement).is(':input')) {
                    return false;    
                }
                //($(this).data('blueimp-fileupload') || $(this).data('fileupload'))._clear();
            },

            drop: function() {
                ($(this).data('blueimp-fileupload') || $(this).data('fileupload'))._clear();
            },

            // Callback for file deletion:
            destroy: function (e, data) {
                var that = $(this).data('blueimp-fileupload') ||
                        $(this).data('fileupload');
                if (data.url) {
                    $.ajax(data).done(function () {
                        that._transition(data.context[1]).done(
                            function () {
                                $(this).remove();
                                that._adjustMaxNumberOfFiles(1);
                                that._trigger('destroyed', e, data);
                            }
                        );
                    });
                }
            }
        },

        _clear: function() {
            var options = this.options;
            options.preloadContainer.empty();
            options.filesContainer.empty();
            $('#add-new-group-container').show();
            this._hideReport();
        },

        _hideReport: function() {
            this._emptyReport();
            $('#s-imagesproduct-report').html('').hide();
        },

        _showReport: function() {
            var msg = [];
            if (this.options.report.product_count) {
                msg.push($_('Products added:') + ' '  + this.options.report.product_count + '<br>');
            }
            if (this.options.report.file_count) {
                msg.push($_('Images uploaded:') + ' ' + this.options.report.file_count + '<br>');
            }
            if (msg.length) {
                $('#s-imagesproduct-report').html('<div class="successmsg">' + msg.join(' ') + '</div>').show();
            }
        },

        _groupId: function(name) {
            var token = name.toLowerCase();

            token = token.
                replace(/\..*$/, '').
                replace(/[\d+-_]+$/, '').
                replace(/^(DSC|JPG|JPEG)_*/)
            ;

            // extra cleaning. clean in production
            // token = token.replace(/_(enl|thm)$/, '').replace(/[\d+-_]+$/, '');

            if (token.length < 2) {
                return this._groupCount++;
            }
            if (typeof this._groups[token] === 'undefined') {
                return this._groups[token] = this._groupCount++;
            } else {
                return this._groups[token];
            }
        },

        _grouping: function(files) {
            var n  = files.length;
            var id = this._groupId(files[0].name);
            for (var i = 0; i < n; i += 1) {
                files[i].group_id = id;
            }
        },

        _resetFinishedDeferreds: function () {
            this._finishedUploads = [];
        },

        _addFinishedDeferreds: function (deferred) {
            if (!deferred) {
                deferred = $.Deferred();
            }
            this._finishedUploads.push(deferred);
            return deferred;
        },

        _getFinishedDeferreds: function () {
            return this._finishedUploads;
        },

        _getFilesFromResponse: function (data) {
            if (data.result && $.isArray(data.result.files)) {
                return data.result.files;
            }
            return [];
        },

        // Link handler, that allows to download files
        // by drag & drop of the links to the desktop:
        _enableDragToDesktop: function () {
            var link = $(this),
                url = link.prop('href'),
                name = link.prop('download'),
                type = 'application/octet-stream';
            link.bind('dragstart', function (e) {
                try {
                    e.originalEvent.dataTransfer.setData(
                        'DownloadURL',
                        [type, name, url].join(':')
                    );
                } catch (err) {}
            });
        },

        _adjustMaxNumberOfFiles: function (operand) {
            if (typeof this.options.maxNumberOfFiles === 'number') {
                this.options.maxNumberOfFiles += operand;
                if (this.options.maxNumberOfFiles < 1) {
                    this._disableFileInputButton();
                } else {
                    this._enableFileInputButton();
                }
            }
        },

        _formatFileSize: function (bytes) {
            if (typeof bytes !== 'number') {
                return '';
            }
            if (bytes >= 1000000000) {
                return (bytes / 1000000000).toFixed(2) + ' GB';
            }
            if (bytes >= 1000000) {
                return (bytes / 1000000).toFixed(2) + ' MB';
            }
            return (bytes / 1000).toFixed(2) + ' KB';
        },

        _formatBitrate: function (bits) {
            if (typeof bits !== 'number') {
                return '';
            }
            if (bits >= 1000000000) {
                return (bits / 1000000000).toFixed(2) + ' Gbit/s';
            }
            if (bits >= 1000000) {
                return (bits / 1000000).toFixed(2) + ' Mbit/s';
            }
            if (bits >= 1000) {
                return (bits / 1000).toFixed(2) + ' kbit/s';
            }
            return bits.toFixed(2) + ' bit/s';
        },

        _formatTime: function (seconds) {
            var date = new Date(seconds * 1000),
                days = Math.floor(seconds / 86400);
            days = days ? days + 'd ' : '';
            return days +
                ('0' + date.getUTCHours()).slice(-2) + ':' +
                ('0' + date.getUTCMinutes()).slice(-2) + ':' +
                ('0' + date.getUTCSeconds()).slice(-2);
        },

        _formatPercentage: function (floatValue) {
            return (floatValue * 100).toFixed(2) + ' %';
        },

        _renderExtendedProgress: function (data) {
            return this._formatBitrate(data.bitrate) + ' | ' +
                this._formatTime(
                    (data.total - data.loaded) * 8 / data.bitrate
                ) + ' | ' +
                this._formatPercentage(
                    data.loaded / data.total
                ) + ' | ' +
                this._formatFileSize(data.loaded) + ' / ' +
                this._formatFileSize(data.total);
        },

        _hasError: function (file) {
            if (file.error) {
                return file.error;
            }
            // The number of added files is subtracted from
            // maxNumberOfFiles before validation, so we check if
            // maxNumberOfFiles is below 0 (instead of below 1):
            if (this.options.maxNumberOfFiles < 0) {
                return 'Maximum number of files exceeded';
            }
            // Files are accepted if either the file type or the file name
            // matches against the acceptFileTypes regular expression, as
            // only browsers with support for the File API report the type:
            if (!(this.options.acceptFileTypes.test(file.type) ||
                    this.options.acceptFileTypes.test(file.name))) {
                return 'Filetype not allowed';
            }
            if (this.options.maxFileSize &&
                    file.size > this.options.maxFileSize) {
                return 'File is too big';
            }
            if (typeof file.size === 'number' &&
                    file.size < this.options.minFileSize) {
                return 'File is too small';
            }
            return null;
        },

        _validate: function (files) {
            var that = this,
                valid = !!files.length;
            $.each(files, function (index, file) {
                file.error = that._hasError(file);
                if (file.error) {
                    valid = false;
                }
            });
            return valid;
        },

        _renderTemplate: function (func, files) {
            if (!func) {
                return $();
            }
            var result = func({
                files: files,
                formatFileSize: this._formatFileSize,
                options: this.options
            });
            if (result instanceof $) {
                return result;
            }
            return $(this.options.templatesContainer).html(result).children();
        },

        _renderPreview: function (file, node) {
            var that = this,
                options = this.options,
                dfd = $.Deferred();
            return ((loadImage && loadImage(
                file,
                function (img) {
                    node.append(img);
                    that._forceReflow(node);
                    that._transition(node).done(function () {
                        dfd.resolveWith(node);
                    });
                    if (!$.contains(that.document[0].body, node[0])) {
                        // If the element is not part of the DOM,
                        // transition events are not triggered,
                        // so we have to resolve manually:
                        dfd.resolveWith(node);
                    }
                    node.on('remove', function () {
                        // If the element is removed before the
                        // transition finishes, transition events are
                        // not triggered, resolve manually:
                        dfd.resolveWith(node);
                    });
                },
                {
                    maxWidth: options.previewMaxWidth,
                    maxHeight: options.previewMaxHeight,
                    canvas: options.previewAsCanvas
                }
            )) || dfd.resolveWith(node)) && dfd;
        },

        _renderPreviews: function (data, context) {
            var that = this,
                options = this.options;
            context.find('.preview span').each(function (index, element) {
                var file = data.files[index];
                if (options.previewSourceFileTypes.test(file.type) &&
                        ($.type(options.previewSourceMaxFileSize) !== 'number' ||
                        file.size < options.previewSourceMaxFileSize)) {
                    that._processingQueue = that._processingQueue.pipe(function () {
                        var dfd = $.Deferred(),
                            ev = $.Event('previewdone', {
                                target: element
                            });
                        that._renderPreview(file, $(element)).done(
                            function () {
                                that._trigger(ev.type, ev, data);
                                dfd.resolveWith(that);
                            }
                        );
                        return dfd.promise();
                    });
                }
            });
            return this._processingQueue;
        },

        // Preloading render draw main UI table
        _renderPreload: function(event, data) {
            // Rendering files in proper grouping containers.

            // For it group files (define for each file group ID and apply this info to files)
            this._grouping(data.files);

            var options = this.options,
                insertMethod = options.prependFiles ? 'prepend' : 'append',
                group_id = data.files[0].group_id,
                isAddedNewContainer = false;

            var container = options.preloadContainer.find(
                '[data-group-id=' + group_id + '] ul'
            );
            if (!container.length) {
                options.preloadContainer[insertMethod]
                    (this._renderGroupContainer(group_id));
                options.preloadContainer = $(options.preloadContainer[0]);
                container = options.preloadContainer.find(
                    '[data-group-id=' + group_id + '] ul'
                );
                isAddedNewContainer = true;
            }

            data.context = [
                this._renderTemplate(this.options.preloadTemplate, data.files)
            ];
            container[insertMethod](data.context[0].data('data', data));

            this._renderPreviews(data, data.context[0]);
            this._forceReflow(data.context[0]);

            var that = this;
            return this._transition(data.context[0]).done(
                function () {
                    if (isAddedNewContainer) {
                        that._initGroupContainer(options.preloadContainer.find(
                            '[data-group-id=' + group_id + ']'
                        ));
                    }
                }
            );
        },

        _renderUpload: function (files) {
            return this._renderTemplate(
                this.options.uploadTemplate,
                files
            );
        },

        _renderDownload: function (files) {
            return this._renderTemplate(
                this.options.downloadTemplate,
                files
            ).find('a[download]').each(this._enableDragToDesktop).end();
        },

        _renderGroupContainer: function(id) {
            var result = this.options.groupContainerTemplate({ id: id });
            return $(this.options.templatesContainer).html(result).children();
        },

        _initGroupContainer: function(container) {
            var that = this,
                term = '';
            container.find('input.s-product-name').autocomplete({
                source: function(request, response) {
                    term = request.term;
                    $.getJSON('?action=autocomplete&with_counts=1', request, function(r) {
                        (r = r || []).push({
                            id: 0,
                            label: $_('New product'),
                            value: term
                        });
                        response(r);
                    });
                },
                minLength: 3,
                delay: 300,
                select: function(event, ui) {
                    var item = ui.item;
                    if (!item.id) {
                        that._unrelateProductInfo($(this).parent().parents('tr:first'));
                        return false;
                    } else {
                        that._relateProductInfo($(this).parent().parents('tr:first'), item);
                    }
                }
            });
            container.find('ul').sortable({
                distance: 5,
                items: 'li',
                opacity: 0.75,
                connectWith: 'ul',
                tolerance: 'pointer',
                placeholder: 'ui-state-highlight',
                dropOnEmpty: true,
                forceHelperSize: true,
                forcePlaceholderSize: true
            });
            $('#submit').show();
            $('#add-new-group-container').show();
        },

        _startHandler: function (e) {
            e.preventDefault();
            var button = $(e.currentTarget),
                template = button.closest('.template-upload'),
                data = template.data('data');
            if (data && data.submit && !data.jqXHR) {
                this.element.
                    find('input[name=product_id]').val(
                        data.context[0].parents('tr:first').attr('data-product-id')
                    );
                /*
                that.element.
                    find('input[name=product_count]').val(
                        this.options.report.product_count
                    );
                that.element.find('input[name=file_count]').val(
                    this.options.report.file_count
                );
                */

                if (data.submit()) {
                    button.prop('disabled', true);
                }
            }
            /*
            if (data && data.submit && !data.jqXHR && data.submit()) {
                button.prop('disabled', true);
            }
            */
        },

        _cancelHandler: function (e) {
            e.preventDefault();
            var template = $(e.currentTarget).closest('.template-upload'),
                data = template.data('data') || {};
            if (!data.jqXHR) {
                data.errorThrown = 'abort';
                this._trigger('fail', e, data);
            } else {
                data.jqXHR.abort();
            }
        },

        _deleteHandler: function (e) {
            e.preventDefault();
            var button = $(e.currentTarget);
            this._trigger('destroy', e, $.extend({
                context: button.closest('.template-download'),
                type: 'DELETE'
            }, button.data()));
        },

        _forceReflow: function (node) {
            return $.support.transition && node.length &&
                node[0].offsetWidth;
        },

        _transition: function (node) {
            var dfd = $.Deferred();
            if ($.support.transition && node.hasClass('fade') && node.is(':visible')) {
                node.bind(
                    $.support.transition.end,
                    function (e) {
                        // Make sure we don't respond to other transitions events
                        // in the container element, e.g. from button elements:
                        if (e.target === node[0]) {
                            node.unbind($.support.transition.end);
                            dfd.resolveWith(node);
                        }
                    }
                ).toggleClass('in');
            } else {
                node.toggleClass('in');
                dfd.resolveWith(node);
            }
            return dfd;
        },

        _initButtonBarEventHandlers: function () {
            var form = this.element,
                fileUploadButtonBar = this.element.find('.fileupload-buttonbar'),
                filesList = this.options.filesContainer,
                preloadContainer = this.options.preloadContainer;
            this._on(fileUploadButtonBar.find('.start'), {
                click: function (e) {
                    e.preventDefault();

                    var that = this;
                    var postData = [];
                    preloadContainer.find('tr[data-product-id=0]').each(function() {
                        var item = $(this);
                        var data = [];
                        var empty = true;
                        if (item.find('ul li:first').length) {
                            empty = false;
                        }
                        item.find('input').each(function() {
                            var input = $(this),
                                name = input.attr('name'),
                                value = input.val();
                            if (value && value != "0") {
                                empty = false;
                            }
                            data.push({
                                name: name,
                                value: value
                            });
                        });
                        if (!empty) {
                            postData = postData.concat(data);
                        }
                    });

                    if (postData.length) {
                        postData.push({
                            name: 'type_id', value: that.element.find('select[name=type_id]').val()
                        });
                        $.post('?module=images&action=productcreates', postData, function(r) {
                            if (r.status == 'ok' && r.data) {
                                var count = 0;
                                for (var group_id in r.data) {
                                    if (r.data.hasOwnProperty(group_id)) {
                                        preloadContainer.find(
                                            'tr[data-group-id=' + group_id + ']'
                                        ).attr('data-product-id', r.data[group_id]);
                                        count += 1;
                                    }
                                }
                                that.options.report.product_count = count;
                                //$('#s-imagesproduct-report').html(r.data.report_message);
                                filesList.find('.start').click();
                            }
                        }, 'json');
                    } else {
                        filesList.find('.start').click();
                    }
                }
            });
            this._on(fileUploadButtonBar.find('.cancel'), {
                click: function (e) {
                    e.preventDefault();
                    filesList.find('.cancel').click();
                }
            });
            this._on(fileUploadButtonBar.find('.delete'), {
                click: function (e) {
                    e.preventDefault();
                    filesList.find('.toggle:checked')
                        .closest('.template-download')
                        .find('.delete').click();
                    fileUploadButtonBar.find('.toggle')
                        .prop('checked', false);
                }
            });
            this._on(fileUploadButtonBar.find('.toggle'), {
                change: function (e) {
                    filesList.find('.toggle').prop(
                        'checked',
                        $(e.currentTarget).is(':checked')
                    );
                }
            });
        },

        // reset product info related with this tr container
        _unrelateProductInfo: function(tr_container) {
            tr_container.find('input.s-product-id').val(0);
            tr_container.find('input.s-product-price').show().val('');
            tr_container.find('span.s-product-price').hide().text('');
            tr_container.find('.s-product-currency').show();
            tr_container.attr('data-product-id', 0);
        },

        // Relate product info with tr container
        _relateProductInfo: function(tr_container, product_info) {
            tr_container.find('input.s-product-id').val(product_info.id);
            tr_container.find('input.s-product-price').hide();
            tr_container.find('span.s-product-price').show().html(product_info.price_html || product_info.price_str);
            tr_container.find('.s-product-currency').hide();
            tr_container.attr('data-product-id', product_info.id);
        },

        _initPreloadContainerEventHandlers: function() {
            var that = this,
                options = this.options,
                preloadContainer = this.options.preloadContainer;
            this._on(preloadContainer, {
                'click .s-group-delete': function(e) {
                    e.preventDefault();
                    var target = $(e.currentTarget).parents('tr:first');
                    target.find('.template-preload').each(function() {
                        var item = $(this);
                        if (item.length && item.data('data')) {
                            item.data('data').context[1].find('.cancel').click();
                        }
                    });
                    target.remove();

                    if (!preloadContainer.find('tr:first').length) {
                        $('#s-image-upload-dropbox').show();
                        $('#s-image-upload-explanation').hide();
                        $('#s-product-type-container').hide();
                        $('#submit').hide();
                        $('#add-new-group-container').hide();
                    }
                }
            });

            // handler for unrelating product info if product input is empty
            var timer_id = null;
            this._on(preloadContainer, {
                'keydown input.s-product-name': function(e) {
                    var input = $(e.currentTarget);
                    if (timer_id !== null) {
                        clearTimeout(timer_id);
                    }
                    var that = this;
                    timer_id = setTimeout(function() {
                        if (!input.val()) {
                            that._unrelateProductInfo(input.parents('tr:first'));
                        }
                    }, 400);
                }
            });

            $('#add-new-group').bind('click', function() {
                var group_id = that._groupCount++,
                    insertMethod = options.prependFiles ? 'prepend' : 'append',
                    container = options.preloadContainer.find(
                        '[data-group-id=' + group_id + '] ul'
                    );
                if (!container.length) {
                    options.preloadContainer[insertMethod]
                        (that._renderGroupContainer(group_id));
                    options.preloadContainer = $(options.preloadContainer[0]);
                    container = options.preloadContainer.find(
                        '[data-group-id=' + group_id + '] ul'
                    );
                    that._initGroupContainer(options.preloadContainer.find(
                        '[data-group-id=' + group_id + ']'
                    ));
                }
                return false;
            });
        },

        _destroyButtonBarEventHandlers: function () {
            this._off(
                this.element.find('.fileupload-buttonbar')
                    .find('.start, .cancel, .delete'),
                'click'
            );
            this._off(
                this.element.find('.fileupload-buttonbar .toggle'),
                'change.'
            );
        },

        _destroyPreloadContainerEventHandlers: function() {
            this._off(
                this.options.preloadContainer.find('.s-group-delete'),
                'click'
            );
        },

        _initEventHandlers: function () {
            this._super();
            this._on(this.options.filesContainer, {
                'click .start': this._startHandler,
                'click .cancel': this._cancelHandler,
                'click .delete': this._deleteHandler
            });
            this._initButtonBarEventHandlers();
            this._initPreloadContainerEventHandlers();
        },

        _destroyEventHandlers: function () {
            this._destroyButtonBarEventHandlers();
            this._destroyPreloadContainerEventHandlers();
            this._off(this.options.filesContainer, 'click');
            this._super();
        },

        _enableFileInputButton: function () {
            this.element.find('.fileinput-button input')
                .prop('disabled', false)
                .parent().removeClass('disabled');
        },

        _disableFileInputButton: function () {
            this.element.find('.fileinput-button input')
                .prop('disabled', true)
                .parent().addClass('disabled');
        },

        _initTemplates: function () {
            var options = this.options;
            options.templatesContainer = this.document[0].createElement(
                options.filesContainer.prop('nodeName')
            );
            if (tmpl) {
                if (options.uploadTemplateId) {
                    options.uploadTemplate = tmpl(options.uploadTemplateId);
                }
                if (options.downloadTemplateId) {
                    options.downloadTemplate = tmpl(options.downloadTemplateId);
                }
                if (options.preloadTemplateId) {
                    options.preloadTemplate = tmpl(options.preloadTemplateId);
                }
                if (options.groupContainerTemplateId) {
                    options.groupContainerTemplate = tmpl(options.groupContainerTemplateId);
                }
            }
        },

        _initFilesContainers: function () {
            var options = this.options;
            if (options.filesContainer === undefined) {
                options.filesContainer = this.element.find('.files');
            } else if (!(options.filesContainer instanceof $)) {
                options.filesContainer = $(options.filesContainer);
            }
            if (options.preloadContainer === undefined) {
                options.preloadContainer = this.element.find('.preload-files');
            } else if (!(options.preloadContainer instanceof $)) {
                options.preloadContainer = $(options.preloadContainer);
            }
        },

        _stringToRegExp: function (str) {
            var parts = str.split('/'),
                modifiers = parts.pop();
            parts.shift();
            return new RegExp(parts.join('/'), modifiers);
        },

        _initRegExpOptions: function () {
            var options = this.options;
            if ($.type(options.acceptFileTypes) === 'string') {
                options.acceptFileTypes = this._stringToRegExp(
                    options.acceptFileTypes
                );
            }
            if ($.type(options.previewSourceFileTypes) === 'string') {
                options.previewSourceFileTypes = this._stringToRegExp(
                    options.previewSourceFileTypes
                );
            }
        },

        _emptyReport: function() {
            this.options.report = {
                product_count: 0,
                file_count: 0
            };
        },

        _initSpecialOptions: function () {
            this._super();
            this._initGroups();
            this._initFilesContainers();
            this._initTemplates();
            this._initRegExpOptions();
            this._initDialog();
        },

        _showDialog: function() {
            this.dialog.waDialog({
                onSubmit: function() {
                    return false;
                },
                onCancel: function() {
                    $('#s-product-type-container').hide();
                    $('#s-image-upload-dropbox').show();
                    $('#s-image-upload-explanation').hide();
                    $('#submit').hide();
                    $('#add-new-group-container').hide();
                }
            });
        },

        _hideDialog: function() {
            this.dialog.trigger('close');
            $('#s-product-type-container').hide();
            $('#s-image-upload-dropbox').show();
            $('#s-image-upload-explanation').hide();
            this._showReport();
            $('#submit').hide();
            $('#add-new-group-container').hide();
        },

        _initDialog: function() {
            var that = this;
            that.dialog = $('#s-image-uploader');
            /*
            $('.cancel', that.dialog).click(function() {
                that.dialog.trigger('close');
            });
            */
        },

        // There is grouping idea for rendering fileinfo in proper container.
        // Group id is consequence integer number indicates container for file rendering in
        // GroupCount is common count of groups
        // Groups object maps file name of 'token' to group id
        _initGroups: function() {
            this._groupCount = 0;
            this._groups = {};
        },

        _setOption: function (key, value) {
            this._super(key, value);
            if (key === 'maxNumberOfFiles') {
                this._adjustMaxNumberOfFiles(0);
            }
        },

        _create: function () {
            this._super();
            this._refreshOptionsList.push(
                'filesContainer',
                'uploadTemplateId',
                'downloadTemplateId'
            );
            if (!this._processingQueue) {
                this._processingQueue = $.Deferred().resolveWith(this).promise();
                this.process = function () {
                    return this._processingQueue;
                };
            }
            this._resetFinishedDeferreds();
        },

        enable: function () {
            var wasDisabled = false;
            if (this.options.disabled) {
                wasDisabled = true;
            }
            this._super();
            if (wasDisabled) {
                this.element.find('input, button').prop('disabled', false);
                this._enableFileInputButton();
            }
        },

        disable: function () {
            if (!this.options.disabled) {
                this.element.find('input, button').prop('disabled', true);
                this._disableFileInputButton();
            }
            this._super();
        }

    });

}));
