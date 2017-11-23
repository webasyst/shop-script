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
         * @param el HTMLSelectElement
         */
        selectChangeHandler: function (el) {
            var $el = $(el);
            var $parent = $el.parent('div');

            if (el.value === 'feature:%s') {
                $parent.find('select').hide();
                $parent.find('.js-value-custom:visible').hide();
                $parent.find('a.js-action').show();
                $parent.find('.ui-autocomplete-input:hidden').val('').show().focus();
                if (el.name.match(/\[param\.[^\]]+]\[source]$/)) {
                    this.setParamName($parent, '', '');
                }
            } else if (el.value === 'text:%s') {
                $parent.find('select').hide();
                $parent.find('.ui-autocomplete-input:visible').hide();
                $parent.find('a.js-action').show();
                $parent.find('.js-value-custom:hidden').show().focus();
            } else {
                $parent.find('.ui-autocomplete-input:visible').hide();
                $parent.find('.js-value-custom:visible').hide();
                $parent.find('a.js-action').hide();
                $parent.find('select').show();

                if (el.name.match(/\[param\.[^\]]+]\[source]$/)) {
                    var $selected = $el.find(':selected');
                    var value = $selected.val();
                    this.setParamName($parent, value === 'skip:' ? '' : $selected.text(), $selected.data('unit') || '', value);
                }
            }
        },

        invalidInputChangeHandler: function () {
            var $el = $(this);
            var value = $el.val();
            if (!value || (value === 'skip:')) {
                $el.addClass('error');
            } else {
                $el.removeClass('error');
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
                /**
                 * @var this HTMLSelectElement
                 */
                self.selectChangeHandler(this);
            });

            this.$form.find('a.js-action').unbind('click').bind('click.yandexmarket', function () {
                /**
                 * @var this HTMLInputElement
                 */
                return self.actionHandler($(this));
            });

            /**
             * delivery price control
             */
            this.$form.find(':input[name$="\\[deliveryIncluded\\]"]').change(function (event) {
                /**
                 * @this HTMLInputElement
                 */
                self.helpers.toggle(self.$form.find('div.js-delivery-included'), event, !this.checked);
            }).change();

            /**
             * delivery settings control
             */
            this.$form.find(':input[name$="\\[delivery\\]"]').change(function (event) {
                /**
                 * @this HTMLSelectElement
                 */
                self.helpers.toggle(self.$form.find('div.js-delivery-options, div.js-delivery-included'), event, this.value !== 'false');
                self.$form.find(':input[name$="\\[deliveryIncluded\\]"]').change();
            }).change();

            this.$form.find('div.js-shipping-method :input[type="checkbox"][name$="\\[enabled\\]"]').change(function (event) {
                /**
                 * @this HTMLInputElement
                 */
                var checked = this.checked;


                self.helpers.toggle($(this).parents('div.js-shipping-method').find('>div.value:not(:first)'), event, checked);
            }).change();

            this.$form.find('a.js-cheatsheet:first').click(function (event) {
                var show = self.helpers.toggle(self.$form.find('div.js-cheatsheet:first'), event, null);
                var $icon = $(this).find('.icon10');
                if (show) {
                    $icon.addClass('darr').removeClass('rarr');
                } else {
                    $icon.addClass('rarr').removeClass('darr');
                }
                return false;
            });

            /**
             * type map helper
             */
            var $type_map = this.$form.find(':input.js-type-map');
            $type_map.unbind('change.yandexmarket').bind('change.yandexmarket', function () {
                /**
                 * @this HTMLInputElement
                 */

                var $this = $(this);
                var $sku_group_container = self.$form.find(':input[name="export\\[sku_group\\]"]:first').parents('div.field:first');
                var checked = $this.is(':checked');
                $type_map.filter('[value="' + this.value + '"]').each(function () {
                    var $input = $(this);
                    $input.attr('readonly', checked ? true : null);
                    $input.attr('disabled', checked ? true : null);
                    $sku_group_container.find(':input').attr('disabled', checked ? null : true);

                });
                $this.attr('readonly', null);
                $this.attr('disabled', null);
            });

            /**
             * product export types control
             */
            this.$form.find(':input.js-types').unbind('change.yandexmarket').bind('change.yandexmarket', function (event) {

                var $container = $(this).parents('div.field-group:first');
                var show = $(this).is(':checked');
                if (show) {
                    $container.find('> div.field:hidden :input:not(.js-type-map)').attr('disabled', null);
                } else {
                    $container.find('> div.field :input:not(.js-type-map)').attr('disabled', true);
                    $container.find(':input.js-type-map:checked').each(function () {
                        /**
                         * @this HTMLInputElement
                         */
                        this.checked = false;
                        $(this).trigger('change');
                    });
                }
                self.helpers.toggle($container.find('> div.field' + (show ? ':hidden' : ':visible')), event, show);
            });

            /**
             * product export skus group control
             */
            this.$form.find(':input[name="export\\[sku\\]"]').unbind('change.yandexmarket').bind('change.yandexmarket', function (event) {
                var checked = $(this).is(':checked');

                var $container = self.$form.find(':input[name="export\\[sku_group\\]"]:first').parents('div.field:first');
                var $purchase_container = self.$form.find(':input[name="export\\[purchase_price\\]"]:first').parents('div.field:first');

                self.helpers.toggle($container, event, checked);
                self.helpers.toggle($purchase_container, event, checked);

            }).trigger('change');

            this.$form.find(':text[readonly="readonly"]').unbind('click.yandexmarket focus.yandexmarket keypress.yandexmarket focus.yandexmarket').bind('click.yandexmarket focus.yandexmarket keypress.yandexmarket focus.yandexmarket', function () {
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

            var $link = $('#s-plugin-yandexmarket-last-export');
            if ($link.length && $link.is(':visible')) {
                window.scrollTo(0, 0);
                $link.focus();
            }
        },

        reloadShipping: function () {
            /**
             * delivery settings control
             */
            this.$form.find(':input[name$="\\[delivery\\]"]').change();

            /**
             * delivery price control
             */
            this.$form.find(':input[name$="\\[deliveryIncluded\\]"]').change();

            var self = this;
            this.$form.find('div.js-shipping-method :input[type="checkbox"][name$="\\[enabled\\]"]').change(function (event) {
                /**
                 * @this HTMLInputElement
                 */
                var checked = this.checked;
                self.helpers.toggle($(this).parents('div.js-shipping-method').find('>div.value:not(:first)'), event, checked);
            }).change();
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
            $scope.find(':input.js-autocomplete-feature-param').autocomplete({
                source: '?action=autocomplete&type=feature',
                minLength: 2,
                delay: 300,
                select: self.autocompleteFeature,
                focus: self.autocompleteFeature
            });

        },

        onInit: function () {
            this.initForm();
        },

        regionEdit: function () {
            this.$form.find('div.js-edit-region').slideDown();
            this.$form.find('div.js-edit-region:first').html('<i class="icon16 loading"></i>').load('?plugin=yandexmarket&action=region');
            $(el).hide();
        },

        regionApply: function () {
            var region_id = this.$form.find(':input.js-home-region-id:first').val();

            var profile_id = this.$form.find(':input[name="profile\[id\]"]:first').val();

            var region = this.$form.find('span.js-home-region-name:last').html();
            this.$form.find('span.js-home-region-name:first').html(region);
            $.shop.trace('region_id', [region_id, region]);
            this.$form.find(':input[name="home_region_id"]').val(region_id);

            this.$form.find('a.js-edit-region').show();
            this.$form.find('div.js-edit-region').slideUp();
            this.$form.find('div.js-edit-region:first').html('');
            var self = this;
            this.$form.find('div.field-group.js-delivery-options:first').html('<i class="icon16 loading"></i>').load('?plugin=yandexmarket&action=shipping&profile=' + profile_id + '&region_id=' + region_id, function () {
                self.reloadShipping();
            });
        },
        regionCancel: function () {
            this.$form.find('a.js-edit-region').show();
            this.$form.find('div.js-edit-region').slideUp();
            this.$form.find('div.js-edit-region:first').html('');
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

            if ($input.attr('name').match(/\[param\.[^\]]+]\[feature]$/)) {
                $.shop.trace('unit', ui.item);
                var label = ui.item.label;
                var unit = '';
                var name = '';
                var matches = null;
                if (label.match(/^<span\s+/)) {
                    matches = label.match(/\s+title="([^"]+?:)([^;]+\s*\([^\)]+\));[^"^;]+"/);
                } else { /* ss6 compatibility */
                    matches = label.match(/([^;]*)\(([^\)]+)\);/);
                }

                if (matches) {
                    name = matches[2];
                } else {
                    name = ui.item.name || this.value;
                }

                matches = name.match(/\s*\(([^\)]+)\)\s*$/);
                if (matches) {
                    unit = matches[1];
                }

                $.importexport.plugins.yandexmarket.setParamName($div, this.value, unit);
            }

            if (event && (event.type === 'autocompleteselect')) {
                var $select = $this.parent('div').find('select:first');


                var $option = $select.find('option[value="feature:' + ui.item.value + '"]');
                if (!$option.length) {
                    $option = $select.find('option[value^="feature:' + ui.item.value + ':"]');
                }
                $.shop.trace('autocomplete select', [$option.length, $select]);
                if ($option.length) {
                    $select.val($option.attr('value')).trigger('change');
                } else {
                    $option = $('<option></option>');
                    $option.val('feature:' + ui.item.value);
                    $option.text(ui.item.name);
                    $option.attr('title', ui.item.value);
                    $option.data('unit', unit);
                    $select.find('option[value="feature:%s"]').after($option);
                    $select.val($option.attr('value')).trigger('change');
                }
            }
            return false;
        },

        paramAdd: function (el, type_id) {
            var $target = $(el).parents('div.field');
            if (typeof this.data.params[type_id] === 'undefined') {
                this.data.params[type_id] = 0;
            }
            $.tmpl('yandexmarket-' + type_id, {id: ++this.data.params[type_id]}).insertBefore($target);
            var $scope = $target.prev('div');
            var self = this;
            $scope.find(':input[name$="\\[source\\]"]').change(function () {
                /**
                 * @var HTMLSelectElement this
                 */
                self.selectChangeHandler(this);
            }).trigger('change');

            $scope.find('a.js-action').unbind('click').bind('click.yandexmarket', function () {
                return self.actionHandler($(this));
            });
            this.initFormLazy($scope);
            return false;
        },

        setParamName: function ($div, name, unit, value) {
            var $container = $div.find('.js-target');
            if (name) {
                $container.removeClass('grey');
            } else {
                $container.addClass('grey');
            }
            var pattern = /\s*\(([^\)]+)\)\s*$/;
            var matches = name.match(pattern);
            var suggest = !!matches;
            $container.find('.js-target-name').text(name.replace(pattern, ''));

            var $text = $container.find('.js-target-unit');
            var $input = $container.find(':input');
            $input.unbind('change, keyup');
            if (suggest) {
                $input.attr('placeholder', matches[1]);
            } else {
                $input.attr('placeholder', '');
            }

            if (suggest || !(unit) || ('' + value).match(/^[^:]+:[^:]+:[^:]+/)) {
                $input.val(unit);
                $input.show();
                var self = this;
                $input.bind('change.yandexmarket, keyup.yandexmarket', function () {
                    return self.paramUnitHandler($div, $(this));
                });
                $text.hide();
            } else {
                $text.text(unit);
                $text.show();
                $input.hide();
            }
            $.shop.trace('setParamName', [name, unit, suggest]);
        },

        paramUnitHandler: function ($scope, $element) {
            var unit = $element.val();
            var $option = $scope.find('option:selected:first');
            var value = $option.val();
            value = value.replace(/(feature:)([^:]+)(:[^:]*)?$/, '$1$2:' + unit);
            $.shop.trace('paramUnitHandler', [unit, $option.val(), value]);
            $option.val(value);
        },

        sourceSelect: function ($el) {
            $el.parent('div').find(':input[name$="\\[source\\]"]:first').val('slip:').trigger('change');
        },

        yandexmarketHandler: function (element) {
            var self = this;
            this.form = $(element);
            /**
             * reset required form fields errors
             */
            this.form.find('.value.js-required :input.error').unbind('change.yandexmarket').removeClass('error');

            /**
             * verify form
             */
            var valid = true;

            this.form.find('.value.js-required :input:visible:not(:disabled)').each(function () {
                var $this = $(this);
                var value = $this.val();
                if (!value || (value === 'skip:')) {
                    $this.addClass('error');
                    $this.bind('change.yandexmarket', self.invalidInputChangeHandler);
                    valid = false;
                }
            });
            if (!valid) {
                var $target = this.form.find('.value.js-required :input.error:first');

                $('html, body').animate({
                    scrollTop: $target.offset().top - 10
                }, 1000, function () {
                    $target.focus();
                });
                this.form.find(':input, :submit').attr('disabled', null);
                return false;
            }

            this.progress = true;

            var data = this.form.serialize();
            this.form.find('.errormsg').text('');
            this.form.find(':input').attr('disabled', true);
            this.form.find('a.js-action:visible').data('visible', 1).hide();
            this.form.find(':submit').hide();
            this.form.find('.progressbar .progressbar-inner').css('width', '0%');
            this.form.find('.progressbar').show();
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
                        self.form.find('a.js-action:hidden').each(function () {
                            var $this = $(this);
                            if ($this.data('visible')) {
                                $this.show();
                                $this.data('visible', null);
                            }
                        });
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
                                return !((xhr.status >= 500) || (xhr.status === 0));
                            };
                            self.progressHandler(url, response.processId, response);
                        }, 2100));
                        self.ajax_pull[response.processId].push(setTimeout(function () {
                            self.progressHandler(url, response.processId, null);
                        }, 5500));
                    }
                },
                error: function () {
                    self.form.find(':input').attr('disabled', false);
                    self.form.find('a.js-action:hidden').each(function () {
                        var $this = $(this);
                        if ($this.data('visible')) {
                            $this.show();
                            $this.data('visible', null);
                        }
                    });
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
                if (response && (typeof(response.progress) !== 'undefined')) {
                    $bar = self.form.find('.progressbar .progressbar-inner');
                    var progress = parseFloat(response.progress.replace(/,/, '.'));
                    $bar.animate({
                        'width': progress + '%'
                    });
                    self.debug.memory = Math.max(0.0, self.debug.memory, parseFloat(response.memory) || 0);
                    self.debug.memory_avg = Math.max(0.0, self.debug.memory_avg, parseFloat(response.memory_avg) || 0);

                    var title = 'Memory usage: ' + self.debug.memory_avg + '/' + self.debug.memory + 'MB';
                    title += ' (' + (1 + response.stage_num) + '/' + (parseInt(response.stage_count)) + ')';

                    var message = response.progress + ' — ' + response.stage_name;

                    $bar.parents('.progressbar').attr('title', response.progress);
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
                }, 2000));
            }
        },
        getLink: function () {
            window.location.reload();

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
            },
            toggle: function ($item, event, show) {
                if (show === null) {
                    show = !$item.is(':visible');
                }
                if (show) {
                    if (event.originalEvent) {
                        $item.slideDown();
                    } else {
                        $item.show();
                    }
                } else {
                    if (event.originalEvent) {
                        $item.slideUp();
                    } else {
                        $item.hide();
                    }
                }
                return show;
            }
        }
    }
});
