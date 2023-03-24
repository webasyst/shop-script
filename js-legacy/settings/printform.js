/**
 * {literal}
 *
 * @names printform*
 * @property {} printform_options
 * @method printformInit
 * @method printformAction
 * @method printformBlur
 * @todo flush unavailable hash (edit/delete/etc)
 */
if (typeof($) != 'undefined') {

    $.extend($.settings = $.settings || {}, {

        printform_options: {
            'null': null,
            'loading': 'Loading...<i class="icon16 loading"><i>',
            'plugins_header': ''
        },
        /**
         * Init section
         *
         * @param string tail
         */
        printformInit: function () {
            $.shop.trace('$.settings.printformInit');
            /* init settings */
            var self = this;

            $('#s-printform-content').on('submit', 'form', function () {
                var $this = $(this);
                if ($this.hasClass('js-installer')) {
                    return (!$this.hasClass('js-confirm') || confirm($this.data('confirm-text') || $this.attr('title') || $_('Are you sure?')));
                } else {
                    return self.printformSave($this);
                }
            }).on('keydown', 'form', function (e) {
                if (e.ctrlKey && e.keyCode == 83) {
                    return self.printformSave($(this));
                }
            });
        },

        printform_data: {
            'null': null

        },


        printformPlugins: function () {
            $('#s-printform-menu > li.selected').removeClass('selected');
            $('#s-printform-menu > li > a[href$="\/plugins\/"]').parents('li').addClass('selected');
            var $content = $('#s-settings-content #s-printform-content');
            var url = this.options.backend_url + 'installer/?module=plugins&action=view&slug=shop&filter[tag]=printform&return_hash=/printform/';
            var self = this;
            $content.html(this.printform_options.loading).show().load(url, function () {
                $content.prepend(self.printform_options.plugins_header);
                $content.wrapInner('<div class="block double-padded"></div>');
            });
        },

        /**
         * Disable section event handlers
         */
        printformBlur: function () {
            $('#s-settings-printform-type-dialog').off('click', 'a.js-action');
            $('#s-settings-printform-type-dialog').remove();
            $('#s-settings-content').off('click', 'a.js-action');
            $('#s-settings-printform').off('change, click');
            $('#s-settings-printform-setup').off('submit');
        },

        /**
         *
         * @param {String} tail
         */
        printformAction: function (tail) {
            if (!tail) {
                var href = $('#s-printform-menu li:first a').attr('href');
                if (href) {
                    location.href = href;
                    return;
                }
            }
            var tail_parts = tail.split('/');
            var plugin_id = tail_parts[0];
            var tab = tail_parts[1] || '';
            $.shop.trace('$.settings.printformAction', tail);
            if (plugin_id) {
                if (this.printform_options.plugin_id !== plugin_id) {
                    $('#s-printform-menu > li.selected').removeClass('selected');
                    $('#s-printform-menu > li > a[href$="\/' + plugin_id + '\/"]').parents('li').addClass('selected');
                    if (plugin_id == 'plugins') {
                        $.settings.printform_options.plugin_id = plugin_id;
                        this.printformPlugins();
                    } else {

                        var url = '?&module=settings&action=printformSetup&id=' + plugin_id + '&tab=' + tab;

                        $('#s-printform-content').show().html(this.printform_options.loading).load(url, function () {

                            var ns = 'printform-' + plugin_id;
                            var content = $(this);

                            $.settings.printform_options.plugin_id = plugin_id;
                            var getSaveButton = function () {
                                return $('#s-printform-content .s-plugin-template-save');
                            };
                            var template_modified = false;
                            var templateModified = function () {
                                if (!template_modified) {
                                    template_modified = true;
                                    getSaveButton().removeClass('green').addClass('yellow');
                                }
                            };
                            var resetTemplateModified = function () {
                                template_modified = false;
                                getSaveButton().removeClass('yellow').addClass('green');
                            };

                            wa_editor.on('change', templateModified);

                            var onEvent = function (event, selector, handler) {
                                event += '.' + ns;
                                return content.off(event, selector).on(event, selector, handler);
                            };
                            var onClick = function (selector, handler) {
                                return onEvent('click', selector, handler);
                            };

                            onClick('.s-plugin-template-reset',
                                function () {
                                    if (confirm($('.plugins-settings-template-reset-confirm').text())) {
                                        $.post('?module=settingsPrintformTemplate&action=reset', {
                                                id: $.settings.printform_options.plugin_id
                                            },
                                            function (r) {
                                                if (r && r.status === 'ok') {
                                                    wa_editor.setValue(r.data.template);
                                                    wa_editor.clearSelection();
                                                    waEditorUpdateSource({
                                                        'id': 'plugins-settings-printform-template',
                                                        'ace_editor_container': 'plugins-settings-printform-template-container'
                                                    });
                                                    resetTemplateModified();
                                                    $('.s-plugin-template-reset').hide();
                                                }
                                            }, 'json'
                                        );
                                    }
                                    return false;
                                });

                            onClick('.s-plugin-template-save',
                                function () {
                                    $('.s-plugins-settings-form-status-process').show();
                                    $.post('?module=settingsPrintformTemplate&action=save', {
                                        id: $.settings.printform_options.plugin_id,
                                        template: wa_editor.getValue()
                                    }, function (r) {
                                        $('.s-plugins-settings-form-status-process').hide();
                                        if (r && r.status === 'ok') {
                                            waEditorUpdateSource({
                                                'id': 'plugins-settings-printform-template',
                                                'ace_editor_container': 'plugins-settings-printform-template-container'
                                            });
                                            resetTemplateModified();
                                            $('.s-plugin-template-reset').show();

                                            $('.s-plugins-settings-form-status-saved').show();
                                            setTimeout(function () {
                                                $('.s-plugins-settings-form-status-saved').hide()
                                            }, 500);

                                        }
                                    }, 'json');
                                });
                        });
                    }
                } else {
                    if (tab === 'template') {
                        $('.s-plugin-template').show();
                        $('.s-plugin-settings').hide();
                        $('.s-settings-tabs li.selected').removeClass('selected');
                        $('.s-settings-tabs li:eq(1)').addClass('selected');

                        // super magic, I don't why, but it does what I need - correct height of ace editor properly
                        setTimeout(function () {
                            $(window).resize();
                            setTimeout(function () {
                                $(window).resize();
                            });
                        }, 100);
                    } else {
                        $('.s-plugin-template').hide();
                        $('.s-plugin-settings').show();
                        $('.s-settings-tabs li.selected').removeClass('selected');
                        $('.s-settings-tabs li:first').addClass('selected');
                    }
                }
            }
        },
        printformSave: function ($form) {
            var $content = $('#s-printform-content');
            $form.find(".plugins-settings-form-status").remove();
            $form.find(".loading-wrapper").show();

            if ($form.find(':input[type="file"]').length) {
                $("#plugins-settings-iframe").one('load', function () {
                    try {
                        var response = $(this).contents().find('body').html();
                        $content.show().html($.settings.printform_options.loading).html(response);
                    } catch (e) {
                        $.shop.error(e);
                    }
                });
            } else {
                $.post($form.attr('action'), $form.serialize(), function (response) {
                    $content.show().html($.settings.printform_options.loading).html(response);
                });
                return false;
            }
        },

        printformHelper: {
            parent: $.settings,
            /**
             * Get current selected printform
             *
             * @return int
             */
            type: function () {
                return ($.settings.path.tail) || '';
            }
        }

    });
} else {
    //
}
/**
 * {/literal}
 */
