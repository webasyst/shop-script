/**
 * {literal}
 */
(function ($) {
    "use strict";
    $("#plugin-migrate-transport").change(function () {
        var $container = $("#s-plugin-migrate");
        $container.find(".plugin-migrate-transport-description:visible").hide();
        var $submit_container = $("#plugin-migrate-submit");
        $submit_container.hide();
        var $submit = $submit_container.find(':submit:last');
        $submit.val($submit.data('validate'));
        $submit.removeClass('green');

        if ($(this).val()) {
            $container.find('.plugin-migrate-transport-description:visible').hide();
            $.importexport.plugins.migrate.validate = false;
            $("#plugin-migrate-transport-" + $(this).val()).show();
            $("#plugin-migrate-transport-fields").removeClass('hidden').html($_('Loading...') + '<i class="icon16 loading"></i>').load("?plugin=migrate&action=transport", {
                'transport': $(this).val()
            }, function () {
                $("#plugin-migrate-submit").show();
            });

        } else {

            $("#plugin-migrate-transport-fields").empty();
        }

    });

    $.migrateOzon = {
        init: function ($root) {
            if (!$root || !$root.length) {
                return;
            }
            this.$root = $root;
            this.is_auto_mode = false;
            this.featureSwitchActive = true;
            this.manualFeatureSwitchActive = true;
            this.featureSwitchInstance = null;
            this.$featureSwitchLabel = null;
            this.$featureSwitchTooltip = null;
            this.$modeSwitchLabel = null;
            this.$modeSwitchTooltip = null;
            this.$mainButtonWrapper = null;
            this.initModeSwitch();
            this.initFeatureSwitch();
            this.bind();
            this.attachMainButton();
            this.setPrimaryState(this.$root.data('state') || 'load');
        },

        bind: function () {
            var self = this;
            this.$root.on('click', '.js-ozon-save', function () {
                var data = {
                    client_id: $.trim(self.$root.find('[name="ozon_client_id"]').val()),
                    api_key: $.trim(self.$root.find('[name="ozon_api_key"]').val()),
                    log_mode: self.$root.find('[name="ozon_log_mode"]').val()
                };
                self.post(self.$root.data('save-url'), data, function () {
                    self.status('js-ozon-status', '<i class="icon16 yes text-green"></i> '+$_('Saved'), true);
                });
            });

            this.$root.on('click', '.js-ozon-test', function () {
                var data = {
                    client_id: $.trim(self.$root.find('[name="ozon_client_id"]').val()),
                    api_key: $.trim(self.$root.find('[name="ozon_api_key"]').val())
                };
                self.post(self.$root.data('test-url'), data, function () {
                    self.status('js-ozon-status', '<i class="icon16 yes text-green"></i> '+$_('Connection successful'), true);
                });
            });

            this.$root.on('change', '.js-ozon-feature-mode', function () {
                self.updateFeatureMode();
            });

            this.$root.on('change', '.js-ozon-type-map', function () {
                var $select = $(this);
                var data = {
                    description_category_id: $select.data('category'),
                    type_id: $select.data('type'),
                    shop_type_id: $select.val()
                };
                self.post($select.data('url'), data);
            });

            this.$root.on('change', '.js-ozon-category-map', function () {
                var $select = $(this);
                var data = {
                    description_category_id: $select.data('category'),
                    value: $select.val()
                };
                self.post($select.data('url'), data);
            });

            this.$root.on('change', '.js-ozon-stock-map', function () {
                var $select = $(this);
                var data = {
                    warehouse_id: $select.data('warehouse'),
                    value: $select.val()
                };
                self.post($select.data('url'), data);
            });

            this.$root.on('click', '.js-ozon-clean-open', function (e) {
                e.preventDefault();
                self.openCleanupDialog();
            });
        },

        updateFeatureMode: function () {
            if (!this.$root || !this.$root.length) {
                return;
            }
            var $select = this.$root.find('.js-ozon-feature-mode');
            if (!$select.length) {
                return;
            }
            var url = $select.data('url');
            if (!url) {
                return;
            }
            var force_text = 0;
            if (!this.is_auto_mode) {
                force_text = this.featureSwitchActive ? 0 : 1;
            }
            this.post(url, {
                mode: $select.val(),
                force_text: force_text
            });
        },

        attachMainButton: function () {
            var self = this;
            var $button = this.$root.find('.js-ozon-primary').first();
            if (!$button.length) {
                $button = $('#plugin-migrate-submit .button').first();
            }
            if (!$button.length) {
                return;
            }
            var loadLabel = this.$root.data('load-label');
            var importLabel = this.$root.data('import-label');
            if (loadLabel) {
                $button.data('load-label', loadLabel);
            }
            if (importLabel) {
                $button.data('import-label', importLabel);
            }
            this.$mainButton = $button;
            $button.off('click.ozon').on('click.ozon', function (e) {
                if (!self.$root || !self.$root.length || !$.contains(document, self.$root[0])) {
                    return;
                }
                e.preventDefault();
                var state = self.$root.data('state') || 'load';
                var url = state === 'import' ? self.$root.data('import-url') : self.$root.data('load-url');
                self.runOperation(url, state, $button);
            });
        },

        initModeSwitch: function () {
            var self = this;
            var $switch = this.$root.find('.js-ozon-mode-switch');
            var $modeLabel = $switch.closest('.switch-with-text').find('.js-ozon-mode-switch-label').first();
            var $modeLabelText = $modeLabel.find('.js-ozon-mode-switch-label-text').first();
            var $modeTooltip = $modeLabel.find('.js-ozon-mode-switch-tooltip').first();
            this.$modeSwitchLabel = $modeLabel;
            this.$modeSwitchTooltip = $modeTooltip;
            if ($modeTooltip.length && typeof $modeTooltip.waTooltip === 'function') {
                $modeTooltip.waTooltip();
            }
            if (!$switch.length || typeof $switch.waSwitch !== 'function') {
                this.toggleManual(true, true);
                this.updateModeSwitchTooltip(true);
                return;
            }
            var $checkbox = $switch.find('input[type="checkbox"]').first();
            var $label = $switch.closest('.switch-with-text').find('label').first();
            $switch.waSwitch({
                ready: function (wa_switch) {
                    var active = true;
                    $checkbox.prop('checked', true);
                    wa_switch.set(active);
                    wa_switch.$label = $modeLabelText.length ? $modeLabelText : $label;
                    wa_switch.active_text = $label.data('active-text');
                    wa_switch.inactive_text = $label.data('inactive-text');
                    if (wa_switch.$label && wa_switch.$label.length) {
                        wa_switch.$label.text(active ? wa_switch.active_text : wa_switch.inactive_text);
                    }
                    self.toggleManual(active, true);
                    self.updateModeSwitchTooltip(active);
                },
                change: function (active, wa_switch) {
                    self.toggleManual(active);
                    if (wa_switch.$label) {
                        wa_switch.$label.text(active ? wa_switch.active_text : wa_switch.inactive_text);
                    }
                    self.updateModeSwitchTooltip(active);
                    self.post($switch.data('url'), {mode: active ? 'auto' : 'manual'});
                }
            });
        },

        initFeatureSwitch: function () {
            if (!this.$root || !this.$root.length) {
                return;
            }
            var self = this;
            var $switch = this.$root.find('.js-ozon-feature-switch').first();
            if (!$switch.length) {
                return;
            }
            var $checkbox = $switch.find('input[type="checkbox"]').first();
            var $label = this.$root.find('.js-ozon-feature-switch-label').first();
            var $labelText = $label.find('.js-ozon-feature-switch-label-text').first();
            var $tooltip = $label.find('.js-ozon-feature-switch-tooltip').first();
            this.$featureSwitchLabel = $label;
            this.$featureSwitchTooltip = $tooltip;
            if ($tooltip.length && typeof $tooltip.waTooltip === 'function') {
                $tooltip.waTooltip();
            }
            this.featureSwitchActive = !$checkbox.length || $checkbox.is(':checked');
            this.manualFeatureSwitchActive = this.featureSwitchActive;
            this.updateFeatureSwitchLabelVisual(this.featureSwitchActive);
            if (typeof $switch.waSwitch !== 'function') {
                $checkbox.on('change', function () {
                    self.featureSwitchActive = $(this).is(':checked');
                    if (!self.is_auto_mode) {
                        self.manualFeatureSwitchActive = self.featureSwitchActive;
                    }
                    self.updateFeatureSwitchLabelVisual(self.featureSwitchActive);
                    self.updateFeatureMode();
                });
                return;
            }
            $switch.waSwitch({
                ready: function (wa_switch) {
                    wa_switch.set(self.featureSwitchActive);
                    wa_switch.$label = $labelText.length ? $labelText : $label;
                    wa_switch.active_text = $label.data('active-text');
                    wa_switch.inactive_text = $label.data('inactive-text');
                    if (wa_switch.$label && wa_switch.$label.length) {
                        wa_switch.$label.text(self.featureSwitchActive ? wa_switch.active_text : wa_switch.inactive_text);
                    }
                    self.featureSwitchInstance = wa_switch;
                    self.updateFeatureSwitchTooltip(self.featureSwitchActive);
                    self.updateFeatureSwitchAvailability();
                    self.updateFeatureMode();
                },
                change: function (active, wa_switch) {
                    self.featureSwitchActive = !!active;
                    if (!self.is_auto_mode) {
                        self.manualFeatureSwitchActive = self.featureSwitchActive;
                    }
                    if (wa_switch.$label) {
                        wa_switch.$label.text(active ? wa_switch.active_text : wa_switch.inactive_text);
                    }
                    self.updateFeatureSwitchTooltip(self.featureSwitchActive);
                    self.updateFeatureMode();
                }
            });
        },

        updateFeatureSwitchAvailability: function () {
            var switcher = this.featureSwitchInstance;
            var target = (typeof this.manualFeatureSwitchActive === 'boolean') ? this.manualFeatureSwitchActive : true;
            this.featureSwitchActive = target;
            if (switcher && typeof switcher.set === 'function') {
                switcher.set(target);
                if (typeof switcher.disable === 'function') {
                    switcher.disable(false);
                }
                if (switcher.$label) {
                    switcher.$label.text(target ? switcher.active_text : switcher.inactive_text);
                }
            } else {
                this.updateFeatureSwitchLabelVisual(this.featureSwitchActive);
            }
            this.updateFeatureSwitchTooltip(this.featureSwitchActive);
        },

        toggleManual: function (is_auto, silent) {
            this.is_auto_mode = !!is_auto;
            this.$root.find('.js-ozon-manual-section').css('display', is_auto ? 'none' : '');
            this.updateFeatureSwitchAvailability();
            if (!silent) {
                this.updateFeatureMode();
            }
            this.updateModeSwitchTooltip(is_auto);
        },

        updateFeatureSwitchLabelVisual: function (is_active) {
            var $label = this.$featureSwitchLabel || this.$root.find('.js-ozon-feature-switch-label').first();
            if ($label && $label.length) {
                var $text = $label.find('.js-ozon-feature-switch-label-text').first();
                if ($text.length) {
                    $text.text(is_active ? $label.data('active-text') : $label.data('inactive-text'));
                }
                this.updateFeatureSwitchTooltip(is_active);
            }
        },

        updateModeSwitchTooltip: function (is_auto) {
            var $label = this.$modeSwitchLabel || this.$root.find('.js-ozon-mode-switch-label').first();
            if (!$label || !$label.length) {
                return;
            }
            var $tooltip = this.$modeSwitchTooltip || $label.find('.js-ozon-mode-switch-tooltip').first();
            if (!$tooltip.length) {
                return;
            }
            var tooltipText = is_auto ? ($label.data('auto-tooltip') || '') : '';
            $tooltip.attr('data-wa-tooltip-content', tooltipText);
            if (typeof $tooltip.waTooltip === 'function') {
                var instance = $tooltip.waTooltip('tooltip');
                if (instance && instance.tippy) {
                    instance.tippy.setContent(tooltipText);
                    if (tooltipText) {
                        instance.tippy.enable();
                    } else {
                        instance.tippy.disable();
                    }
                } else if (tooltipText) {
                    $tooltip.waTooltip();
                }
            }
        },

        toggleMainButtonLoader: function (enable) {
            var $btn = this.$mainButton;
            if (!$btn || !$btn.length) {
                return;
            }
            if (!$btn.parent().hasClass('s-ozon-button-wrapper')) {
                $btn.wrap('<span class="s-ozon-button-wrapper"></span>');
            }
            var $wrapper = $btn.parent();
            this.$mainButtonWrapper = $wrapper;
            if (enable) {
                $wrapper.addClass('is-loading');
                if (!$wrapper.find('.s-ozon-button-pulsar').length) {
                    $('<span class="pulsar s-ozon-button-pulsar"></span>').appendTo($wrapper);
                }
            } else {
                $wrapper.removeClass('is-loading');
                $wrapper.find('.s-ozon-button-pulsar').remove();
            }
        },

        updateFeatureSwitchTooltip: function (is_active) {
            var $label = this.$featureSwitchLabel || this.$root.find('.js-ozon-feature-switch-label').first();
            if (!$label || !$label.length) {
                return;
            }
            var $tooltip = this.$featureSwitchTooltip || $label.find('.js-ozon-feature-switch-tooltip').first();
            if (!$tooltip.length) {
                return;
            }
            var tooltipText = is_active ? ($label.data('active-tooltip') || '') : ($label.data('inactive-tooltip') || '');
            $tooltip.attr('data-wa-tooltip-content', tooltipText);
            if (typeof $tooltip.waTooltip === 'function') {
                var instance = $tooltip.waTooltip('tooltip');
                if (instance && instance.tippy && typeof instance.tippy.setContent === 'function') {
                    instance.tippy.setContent(tooltipText);
                } else {
                    $tooltip.waTooltip();
                }
            }
        },

        runOperation: function (url, state, $button) {
            var self = this;
            if (!url) { return; }
            var $btn = $button || this.$mainButton;
            if (!$btn || !$btn.length) { return; }
            var loading_html = '<i class="icon16 loading"></i>';
            if (state === 'load') {
                this.toggleMainButtonLoader(true);
            }
            $btn.prop('disabled', true).after(loading_html);
            this.post(url, {}, function () {
                if (state === 'load') {
                    self.setPrimaryState('import');
                    self.reload(true);
                } else {
                    self.showImportSuccess();
                }
            }, {
                target: 'js-ozon-progress',
                always: function () {
                    $btn.prop('disabled', false);
                    $btn.next('.icon16.loading').remove();
                    if (state === 'load') {
                        self.toggleMainButtonLoader(false);
                    }
                }
            });
        },

        setPrimaryState: function (state) {
            var $btn = this.$mainButton;
            if (!$btn || !$btn.length) { return; }
            var loadLabel = $btn.data('validate') || $btn.data('load-label') || $_('Load data');
            var importLabel = $btn.data('import') || $btn.data('import-label') || $_('Import data');
            var label = state === 'import' ? importLabel : loadLabel;
            if (state === 'import') {
                $btn.removeClass('blue').addClass('green');
            } else {
                $btn.removeClass('green').addClass('blue');
            }
            if ($btn.is('input')) {
                $btn.val(label);
            } else {
                $btn.text(label);
            }
            this.$root.data('state', state);
        },

        showImportSuccess: function () {
            var $btn = this.$mainButton;
            var message = this.$root.data('import-success') || 'Импорт успешно завершен';
            if ($btn && $btn.length) {
                this.toggleMainButtonLoader(false);
                var $wrapper = $btn.parent('.s-ozon-button-wrapper');
                var $message = $('<span class="s-ozon-success-message"></span>').text(message);
                if ($wrapper.length) {
                    $wrapper.replaceWith($message);
                } else {
                    $btn.replaceWith($message);
                }
                this.$mainButton = null;
            }
            this.status('js-ozon-progress', message, true);
        },

        openCleanupDialog: function () {
            var template = $('#js-ozon-clean-dialog-template');
            if (!template.length) {
                return;
            }
            var html = template.html();
            var self = this;
            if (this.cleanupDialog) {
                this.cleanupDialog.close();
            }
            this.cleanupDialog = $.waDialog({
                html: html,
                onOpen: function ($dialog, dialog_instance) {
                    self.bindCleanupDialog($dialog, dialog_instance);
                },
                onClose: function () {
                    self.cleanupDialog = null;
                }
            });
        },

        bindCleanupDialog: function ($dialog, dialog_instance) {
            var self = this;
            $dialog.on('click', '.js-close-dialog', function (e) {
                e.preventDefault();
                dialog_instance.close();
            });
            $dialog.on('click', '.js-ozon-clean-submit', function (e) {
                e.preventDefault();
                var tables = [];
                $dialog.find('input[name="tables[]"]:checked').each(function () {
                    tables.push($(this).val());
                });
                if (!tables.length) {
                    alert('Выберите хотя бы одну таблицу');
                    return;
                }
                var warning = $_('Это действие необратимо очищает все таблицы миграции Озона. Выполняйте только если знаете что делаете!');
                if (!confirm(warning)) {
                    return;
                }
                dialog_instance.close();
                var url = self.$root.data('clean-url');
                $.post(url, { tables: tables }, function (response) {
                    if (response && response.status === 'ok') {
                        var message = 'Выбранные таблицы очищены';
                        self.status('js-ozon-progress', message, true);
                    } else if (response && response.errors) {
                        alert(response.errors.join(', '));
                    } else if (response && response.error) {
                        alert(response.error);
                    }
                }, 'json').fail(function (xhr) {
                    var text = xhr && xhr.responseText ? xhr.responseText : $_('Request failed');
                    alert(text);
                });
            });
        },

        status: function (className, message, positive) {
            var $target = this.$root.find('.'+className);
            $target.removeClass('text-red text-green');
            if (positive) {
                $target.addClass('text-green');
            } else {
                $target.addClass('text-red');
            }
            $target.html(message);
        },

        post: function (url, data, onSuccess, options) {
            var self = this;
            if (!url) { return; }
            options = options || {};
            var target = options.target || 'js-ozon-status';
            var alwaysCallback = options.always;
            $.post(url, data, function (response) {
                if (response && response.status === 'ok') {
                    if (onSuccess) { onSuccess(response); }
                } else if (response && response.errors) {
                    self.status(target, response.errors.join(', '), false);
                } else if (response && response.error) {
                    self.status(target, response.error, false);
                }
            }, 'json').fail(function (xhr) {
                var text = xhr && xhr.responseText ? xhr.responseText : $_('Request failed');
                self.status(target, text, false);
            }).always(function () {
                if (typeof alwaysCallback === 'function') {
                    alwaysCallback();
                }
            });
        },

        reload: function (showSnapshot) {
            var $fields = $("#plugin-migrate-transport-fields");
            var transport = $("#plugin-migrate-transport").val();
            if (!transport) { return; }
            var url = "?plugin=migrate&action=transport";
            var params = {transport: transport};
            if (showSnapshot) {
                params.ozon_show_snapshot = 1;
            }
            var $overlayRoot = this.$root && this.$root.length ? this.$root : null;
            if ($overlayRoot) {
                $overlayRoot.css('min-height', $overlayRoot.outerHeight());
                $overlayRoot.addClass('is-loading');
            }
            $.ajax({
                url: url,
                data: params,
                method: 'GET',
                dataType: 'html'
            }).done(function (response) {
                $fields.html(response);
                $("#plugin-migrate-submit").show();
                $fields.find('script').each(function () {
                    var $script = $(this);
                    var src = $script.attr('src');
                    if (src) {
                        $.ajax({
                            url: src,
                            dataType: 'script',
                            cache: true,
                            async: false
                        });
                    } else {
                        $.globalEval($script.html());
                    }
                });
                if (window.$ && $.migrateOzon) {
                    var $newRoot = $fields.find('#js-ozon-root');
                    if ($newRoot.length) {
                        $.migrateOzon.init($newRoot);
                    }
                }
            }).fail(function (xhr) {
                var text = xhr && xhr.responseText ? xhr.responseText : $_('Request failed');
                $fields.html('<div class="errormsg">'+text+'</div>');
            }).always(function () {
                if ($overlayRoot) {
                    $overlayRoot.removeClass('is-loading').css('min-height', '');
                }
            });
        }
    };
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
        action: function () {

        },
        onInit: function (path) {
            if (path.action) {
                setTimeout(function () {
                    $("#plugin-migrate-transport").val(path.action).change();
                }, 1000);
            }
        },
        blur: function () {

        },
        /**
         *
         * @param {submit} element
         * @returns {boolean}
         */
        migrateHandler: function (element) {
            var self = this;
            self.progress = true;
            self.form = $(element);
            $.shop.trace('$.importexport.plugins.migrate.migrateHandler', [element]);
            var data = self.form.serialize();
            self.form.find('.errormsg').text('');
            self.form.find(':input').attr('disabled', true);
            self.form.find(':submit:last').hide();
            self.form.find('.progressbar .progressbar-inner').css('width', '0%');
            self.form.find('.progressbar').show();
            var url = $(element).attr('action');
            $.ajax({
                url: url + '&t=' + this.date.getTime(),
                data: data,
                dataType: 'json',
                type: 'post',
                success: function (response) {
                    if (response.error) {
                        self.form.find(':input').attr('disabled', false);
                        self.form.find(':submit:last').show();
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
                        }, 1000));
                        self.ajax_pull[response.processId].push(setTimeout(function () {
                            self.progressHandler(url, response.processId, response);
                        }, 2000));
                    }
                },
                error: function () {
                    self.form.find(':input').attr('disabled', false);
                    self.form.find(':submit:last').show();
                    self.form.find('.js-progressbar-container').hide();
                    self.form.find('.shop-ajax-status-loading').remove();
                    self.form.find('.progressbar').hide();
                }
            });
            return false;
        },
        /**
         *
         * @param {string} url
         * @param {string} processId
         * @param {{ready:boolean=, memory:string,memory_avg:string,processId:string, report:string, stage_num:number, stage_count:number, stage_name:string, warning:string=, error:string=,progress:number}} response
         */
        progressHandler: function (url, processId, response) {
            // display progress
            // if not completed do next iteration
            var self = $.importexport.plugins.migrate;
            var $bar;
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
                $bar = self.form.find('.progressbar .progressbar-inner');
                $bar.css({
                    'width': '100%'
                });
                // self.form.find('.progressbar').hide();
                // self.form.find('.progressbar-description').hide();
                $.shop.trace('cleanup', response.processId);

                $.ajax({
                    url: url + '&t=' + this.date.getTime(),
                    data: {
                        'processId': response.processId,
                        'cleanup': 1
                    },
                    dataType: 'json',
                    type: 'post',
                    success: function (response) {
                        // show statistic
                        $.shop.trace('report', response);
                        $("#plugin-migrate-submit").hide();
                        self.form.find('.progressbar').hide();
                        var $report = $("#plugin-migrate-report");
                        $report.show();
                        if (response.report) {
                            $report.find(".value:first").html(response.report);
                        }
                        $.storage.del('shop/hash');
                    }
                });

            } else if (response && response.error) {

                self.form.find(':input').attr('disabled', false);
                self.form.find(':submit:last').show();
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
                    title += ' (' + (1 + parseInt(response.stage_num)) + '/' + response.stage_count + ')';

                    var message = response.progress + ' вЂ” ' + response.stage_name;

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
                        url: ajax_url + '&t=' + self.date.getTime(),
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
                }, 1000));
            }
        }
    };
    $("#s-plugin-migrate").submit(function () {
        var $group = $('#plugin-migrate-transport-group');
        try {
            var $form = $(this);
            if (!$.importexport.plugins.migrate.validate) {
                $group.find(':input').attr('disabled', false);
                var item, data = $form.serializeArray();
                var params = {};
                while (item = data.shift()) {
                    params[item.name] = item.value;
                }
                var loading = '<i class="icon16 loading"></i>';
                var url = "?plugin=migrate&action=transport";
                $.shop.trace('validate', params);
                $form.find(':submit:last').after(loading);
                $form.find(':input, :submit').attr('disabled', true);
                $group.find(':input').attr('disabled', true);

                $("#plugin-migrate-transport-fields").load(url, params, function () {
                    $("#plugin-migrate-submit").show();
                    $form.find(':submit:last ~ i.loading').remove();
                    $form.find('#plugin-migrate-transport-fields :input, :submit').attr('disabled', false);
                    var $submit = $form.find(':submit:last');
                    if ($.importexport.plugins.migrate.validate) {
                        $submit.val($submit.data('import'));
                        $submit.addClass('green');
                        $group.find(':input').attr('disabled', true);
                    } else {
                        $submit.val($submit.data('validate'));
                        $submit.removeClass('green');
                        $group.find(':input').attr('disabled', false);
                    }
                });
            } else {
                $form.find(':input, :submit').attr('disabled', false);
                $.importexport.plugins.migrate.migrateHandler(this);
            }
        } catch (e) {
            $group.find(':input').attr('disabled', false);
            $.shop.error('Exception: ' + e.message, e);
        }
        return false;
    });
})(jQuery);
/**
 * {/literal}
 */

