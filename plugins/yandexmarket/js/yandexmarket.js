$.extend($.importexport.plugins, {
    yandexmarket: {
        form: null,
        ajax_pull: {},
        progress: false,
        id: null,
        debug: {
            'memory': 0.0,
            'memory_avg': 0.0
        },
        data: {
            'params': {}
        },
        $form: null,
        init: function (data) {
            this.$form = $("#s-plugin-yandexmarket");
            $.extend(this.data, data);
        },

        hashAction: function (hash) {
            $.importexport.products.action(hash);
            window.location.hash = window.location.hash.replace(/\/hash\/.+$/, '/');
        },

        action: function () {

        },

        blur: function () {

        },

        /**
         *
         * @param el {HTMLSelectElement}
         */
        selectChangeHandler: function (el) {
            var $el = $(el);
            var $parent = $el.parent('div');

            if (el.value == 'feature:%s') {
                $parent.find('select').hide();
                $parent.find('.js-value-custom:visible').hide();
                $parent.find('a.js-action').show();
                $parent.find('.ui-autocomplete-input:hidden').show().focus();
                if (el.name.match(/\[param\.[^\]]+\]\[source\]$/)) {
                    this.setParamName($parent, '', '');
                }
            } else if (el.value == 'text:%s') {
                $parent.find('select').hide();
                $parent.find('.ui-autocomplete-input:visible').hide();
                $parent.find('a.js-action').show();
                $parent.find('.js-value-custom:hidden').show().focus();
            } else {
                $parent.find('.ui-autocomplete-input:visible').hide();
                $parent.find('.js-value-custom:visible').hide();
                $parent.find('a.js-action').hide();
                $parent.find('select').show();

                if (el.name.match(/\[param\.[^\]]+\]\[source\]$/)) {
                    var $selected = $el.find(':selected');
                    this.setParamName($parent, $selected.val() == 'skip:' ? '' : $selected.text(), $selected.data('unit') || '');
                }
            }
        },

        actionHandler: function ($el) {
            try {
                var args = $el.attr('href').replace(/.*#\/?/, '').replace(/\/$/, '').split('/');
                args.shift();
                var method = $.shop.getMethod(args, this);

                if (method.name) {
                    $.shop.trace('$.importexport.plugins.yandexmarket', method);
                    if (!$el.hasClass('js-confirm') || confirm($el.data('confirm-text') || $el.attr('title') || 'Are you sure?')) {
                        method.params.unshift($el);
                        this[method.name].apply(this, method.params);
                    }
                } else {
                    $.shop.error('Not found js handler for link', [method, args, $el])
                }
            } catch (e) {
                $.shop.error('Exception ' + e.message, e);
            }
            return false;
        },


        initForm: function () {
            /**
             * collection control
             * @todo use generic import & export control instead
             */
            $.importexport.products.init(this.$form);

            var self = this;

            /**
             * source mapping control
             */
            this.$form.find(':input[name$="\\[source\\]"]').change(function () {
                self.selectChangeHandler(this);
            });

            this.$form.find('a.js-action').unbind('click').bind('click.yandexmarket', function () {
                return self.actionHandler($(this));
            });

            /**
             * type map helper
             */
            var $type_map = this.$form.find(':input.js-type-map');
            $type_map.change(function () {
                /**
                 * @this HTMLInputElement
                 */

                var $this = $(this);
                var checked = $this.is(':checked');
                $type_map.filter('[value="' + this.value + '"]').each(function () {
                    var $input = $(this);
                    $input.attr('readonly', checked ? true : null);
                    $input.attr('disabled', checked ? true : null);

                });
                $this.attr('readonly', null);
                $this.attr('disabled', null);
            });

            /**
             * product export types control
             */
            this.$form.find(':input.js-types').change(function () {
                var $container = $(this).parents('div.field-group:first');
                if ($(this).is(':checked')) {
                    $container.find('> div.field:hidden :input:not(.js-type-map)').attr('disabled', null);
                    $container.find('> div.field:hidden').slideDown();
                } else {
                    $container.find('> div.field :input:not(.js-type-map)').attr('disabled', true);
                    $container.find(':input.js-type-map:checked').each(function () {
                        /**
                         *
                         * @this HTMLInputElement
                         */
                        this.checked = false;
                        $(this).trigger('change');
                    });
                    $container.find('> div.field:visible').slideUp();

                }
            });


            this.$form.find(':text[readonly="readonly"]').bind('click focus keypress focus', function () {
                var $this = $(this);
                window.setTimeout(function () {
                    $this.select();
                }, 100);
            });

            this.$form.unbind('submit.yandexmarket').bind('submit.yandexmarket', function (event) {
                $.shop.trace('submit.yandexmarket ' + event.namespace, event);
                try {
                    var $form = $(this);
                    $form.find(':input, :submit').attr('disabled', false);
                    $.importexport.plugins.yandexmarket.yandexmarketHandler(this);
                } catch (e) {
                    $('#plugin-yandexmarket-transport-group').find(':input').attr('disabled', false);
                    $.shop.error('Exception: ' + e.message, e);
                }
                return false;
            });

            $type_map.filter(':checked').each(function () {
                $(this).trigger('change');
            });

            this.$form.find(':input.js-types').trigger('change');
            this.$form.find(':input[name$="\\[source\\]"]').trigger('change');

            setTimeout(function () {
                self.initFormLazy();
            }, 500);
        },

        initFormLazy: function ($scope) {
            var self = this;
            /**
             * feature autocomplete handler
             */

            if (!$scope) {
                $scope = this.$form;
                this.helpers.compileTemplates();
            }
            $scope.find(':input.js-autocomplete-feature').autocomplete({
                source: '?action=autocomplete&type=feature&options[single]=1',
                minLength: 2,
                delay: 300,
                select: self.autocompleteFeature,
                focus: self.autocompleteFeature
            });

        },

        onInit: function () {
            this.initForm();
        },
        /**
         *
         * @param event
         * @param ui
         * @returns {boolean}
         */
        autocompleteFeature: function (event, ui) {
            /**
             * @this {HTMLInputElement}
             */
            $.shop.trace('autocomplete', ui.item);
            this.value = ui.item.name;
            var $this = $(this);
            $this.attr('title', ui.item.value);
            var $div = $this.parent('div');
            var $input = $div.find(':input[name$="\\[feature\\]"]');
            $input.val(ui.item.value);
            $.shop.trace('aut', $input.attr('name'));
            if ($input.attr('name').match(/\[param\.[^\]]+\]\[feature\]$/)) {
                var unit = ui.item.label.match(/\(([^\)]+)\);/);
                $.importexport.plugins.yandexmarket.setParamName($div, this.value, unit[1] || '');
            }

            return false;
        },

        paramAdd: function (el, type_id) {
            var $target = $(el).parents('div.field');
            $.tmpl('yandexmarket-' + type_id, {id: ++this.data.params[type_id]}).insertBefore($target);
            var $scope = $target.prev('div');
            var self = this;
            $scope.find(':input[name$="\\[source\\]"]').change(function () {
                self.selectChangeHandler(this);
            }).trigger('change');

            $scope.find('a.js-action').unbind('click').bind('click.yandexmarket', function () {
                return self.actionHandler($(this));
            });
            this.initFormLazy($scope);
            return false;
        },

        setParamName: function ($div, name, unit) {
            var $container = $div.find('.js-target');
            if (name) {
                $container.removeClass('grey');
            } else {
                $container.addClass('grey');
            }
            $container.find('.js-target-name').text(name.replace(/\([^\)]+\)$/, ''));
            $container.find('.js-target-unit').text(unit);
            $.shop.trace('setParamName', [name, unit]);
        },


        sourceSelect: function ($el) {
            $el.parent('div').find(':input[name$="\\[source\\]"]:first').val('slip:').trigger('change');
        },

        yandexmarketHandler: function (element) {
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
                        self.ajax_pull[response.processId].push(setTimeout(function () {
                            $.wa.errorHandler = function (xhr) {
                                return !((xhr.status >= 500) || (xhr.status == 0));
                            };
                            self.progressHandler(url, response.processId, response);
                        }, 100));
                        self.ajax_pull[response.processId].push(setTimeout(function () {
                            self.progressHandler(url, response.processId, null);
                        }, 2000));
                    }
                },
                error: function () {
                    self.form.find(':input').attr('disabled', false);
                    self.form.find(':submit').show();
                    self.form.find('.js-progressbar-container').hide();
                    self.form.find('.shop-ajax-status-loading').remove();
                    self.form.find('.progressbar').hide();
                }
            });
            return false;
        },

        onDone: function (url, processId, response) {

        },

        progressHandler: function (url, processId, response) {
            // display progress
            // if not completed do next iteration
            var self = $.importexport.plugins.yandexmarket;
            var $bar;
            if (response && response.ready) {
                $.wa.errorHandler = null;
                var timer;
                while (timer = self.ajax_pull[processId].pop()) {
                    if (timer) {
                        clearTimeout(timer);
                    }
                }
                $bar = self.form.find('.progressbar .progressbar-inner');
                $bar.css({
                    'width': '100%'
                });
                $.shop.trace('cleanup', response.processId);


                $.ajax({
                    url: url,
                    data: {
                        'processId': response.processId,
                        'cleanup': 1
                    },
                    dataType: 'json',
                    type: 'post',
                    success: function (response) {
                        $.shop.trace('report', response);
                        $("#plugin-yandexmarket-submit").hide();
                        self.form.find('.progressbar').hide();
                        var $report = $("#plugin-yandexmarket-report");
                        $report.show();
                        if (response.report) {
                            $report.find(".value:first").html(response.report);
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
                    $bar = self.form.find('.progressbar .progressbar-inner');
                    var progress = parseFloat(response.progress.replace(/,/, '.'));
                    $bar.animate({
                        'width': progress + '%'
                    });
                    self.debug.memory = Math.max(0.0, self.debug.memory, parseFloat(response.memory) || 0);
                    self.debug.memory_avg = Math.max(0.0, self.debug.memory_avg, parseFloat(response.memory_avg) || 0);

                    var title = 'Memory usage: ' + self.debug.memory_avg + '/' + self.debug.memory + 'MB';
                    title += ' (' + (1 + response.stage_num) + '/' + (0 + response.stage_count) + ')';

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

                self.ajax_pull[id].push(setTimeout(function () {
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
                }, 500));
            }
        },
        helpers: {
            /**
             * Compile jquery templates
             *
             * @param {String=} selector optional selector of template container
             */
            compileTemplates: function (selector) {
                var pattern = /<\\\/(\w+)/g;
                var replace = '</$1';

                $(selector || '*').find("script[type$='x-jquery-tmpl']").each(function () {
                    var id = $(this).attr('id').replace(/-template-js$/, '');
                    var template = $(this).html().replace(pattern, replace);
                    try {
                        $.template(id, template);
                        $.shop && $.shop.trace('$.importexport.plugins.helper.compileTemplates', [selector, id]);
                    } catch (e) {
                        (($.shop && $.shop.error) || console.log)('compile template ' + id + ' at ' + selector + ' (' + e.message + ')', template);
                    }
                });
            }
        }
    }
});
