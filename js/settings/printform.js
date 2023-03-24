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
            'loading': 'Loading... <i class="fas fa-spinner fa-spin"><i>',
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

            var $printforms = $('#printforms');
            $printforms.on('click', 'a', function () {
                var $li =  $(this).closest('li');

                if ($li.hasClass('selected')) {
                    return;
                }

                $printforms.find("li.selected").removeClass('selected');

                var tail = $li.data('id');

                self.printformAction(tail);
            });

            $('#link_browse_plugins').on('click', function() {
                $printforms.find("li.selected").removeClass('selected');
            });
        },

        printform_data: {
            'null': null

        },


        printformPlugins: function () {
            const $content = $('#s-settings-content #s-printform-content');
            const url = this.options.backend_url + 'installer/?module=plugins&action=view&slug=shop&filter[tag]=printform&return_hash=/printform/';
            const self = this;
            $content.html(this.printform_options.loading).show().load(url, function () {
                $content.prepend(self.printform_options.plugins_header);
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
            const firstPrintforms = $('#printforms');

            if (!tail) {
                const link = firstPrintforms.find('a:first')
                if (link.length) {
                    const href = link.attr('href');
                    if (href) {
                        link.closest('li').addClass('selected');
                        location.href = href;
                        return;
                    }
                }
            } else {
                firstPrintforms.find('li[data-id="' + tail + '"]').addClass('selected');
            }

            const tail_parts = tail.split('/');
            const plugin_id = tail_parts[0];
            const tab = tail_parts[1] || '';
            $.shop.trace('$.settings.printformAction', tail);
            if (plugin_id) {
                if (this.printform_options.plugin_id !== plugin_id) {
                    $('.js-printform-dropdown li.selected').removeClass('selected');
                    $('.js-printform-dropdown li a[href$="\/' + plugin_id + '\/"]').click();
                    if (plugin_id == 'plugins') {
                        $.settings.printform_options.plugin_id = plugin_id;
                        this.printformPlugins();
                    } else {

                        const url = '?&module=settings&action=printformSetup&id=' + plugin_id + '&tab=' + tab;

                        $('#s-printform-content').show().html(this.printform_options.loading).load(url, function () {

                            const ns = 'printform-' + plugin_id;
                            const content = $(this);
                            const $form = content.find('form:first');

                            $.settings.printform_options.plugin_id = plugin_id;
                            const getSaveButton = function () {
                                return $('#s-printform-content .s-plugin-template-save');
                            };
                            let template_modified = false;
                            const templateModified = function () {
                                if (!template_modified) {
                                    template_modified = true;
                                    getSaveButton().removeClass('green').addClass('yellow');
                                }
                            };
                            const resetTemplateModified = function () {
                                template_modified = false;
                                getSaveButton().removeClass('yellow').addClass('green');
                            };

                            if (wa_editor) {
                                wa_editor.on('change', templateModified);
                            }

                            const modifyData = function () {
                                $form.find(':submit').removeClass('green').addClass('yellow');
                            };

                            $form.on('change', modifyData);
                            $form.on('input', ':input', modifyData);

                            const onEvent = function (event, selector, handler) {
                                return content.off(event, selector).on(event, selector, handler);
                            };
                            const onClick = function (selector, handler) {
                                return onEvent('click', selector, handler);
                            };

                            onClick('.s-plugin-template-reset',
                                function (event) {
                                    event.preventDefault();

                                    $.waDialog.confirm({
                                        title: $.wa.locale['confirmText'] || 'Are you sure?',
                                        success_button_title: $.wa.locale['Delete'] || 'Delete',
                                        success_button_class: 'danger',
                                        cancel_button_title: $.wa.locale['Cancel'] || 'Cancel',
                                        cancel_button_class: 'light-gray',
                                        onSuccess() {
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
                                            }, 'json');
                                        }
                                    });
                                });

                            onClick('.s-plugin-template-save',
                                function (event) {
                                    event.preventDefault();

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
            const $content = $('#s-printform-content');
            const $bottombar = $('.plugins-settings-form-bottombar');
            $bottombar.find(".plugins-settings-form-status").remove();
            $bottombar.find(".loading-wrapper").show();

            if ($form.find(':input[type="file"]').length) {
                $("#plugins-settings-iframe").one('load', function () {
                    try {
                        const response = $(this).contents().find('body').html();
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
