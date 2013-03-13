/**
 * {literal}
 * 
 * @names features*
 * @property {} features_options
 * @method featuresInit
 * @method featuresAction
 * @method featuresBlur
 * @todo flush unavailable hash (edit/delete/etc)
 */
if (typeof($) != 'undefined') {

    $.extend($.settings = $.settings || {}, {
        features_options : {
            /**
             * template map by value type
             */
            'value_templates' : {
                '' : ''
            },
            /**
             * set true to enable edit canceling
             */
            'revert' : false,
            'show_all' : true

        },
        /**
         * Init section
         * 
         * @param string tail
         */
        featuresInit : function() {
            $.shop.trace('$.settings.featuresInit');
            /* init settings */
            var self = this;
            this.featuresHelper.featureCountByType();
            $('#s-settings-content').on('click', 'a.js-action', function() {
                return self.click($(this));
            });
            $('#s-settings-features-type-dialog').on('click', 'a.js-action', function() {
                return self.featuresTypeIcon($(this));
            });

            setTimeout(function() {
                self.call('featuresLazyInit', []);
            }, 50);
        },
        featuresLazyInit : function() {
            var self = this;

            $.shop.trace('$.settings.featuresLazyInit');
            $('#s-settings-feature-types').sortable({
                'distance' : 5,
                'opacity' : 0.75,
                'items' : '> li:not(.not-sortable)',
                'axis' : 'y',
                'containment' : 'parent',
                'update' : function(event, ui) {
                    var id = parseInt($(ui.item).data('type'));
                    var after_id = $(ui.item).prev().data('type');
                    if (after_id === undefined) {
                        after_id = 0;
                    } else {
                        after_id = parseInt(after_id);
                    }
                    self.featuresTypeSort(id, after_id, $(this));
                }
            });

            $('#s-settings-features').sortable({
                'distance' : 5,
                'opacity' : 0.75,
                'items' : '> tbody > tr:visible',
                'handle' : '.sort, .js-feature-name',
                'cursor' : 'move',
                'tolerance' : 'pointer',
                'update' : function(event, ui) {
                    if (self.featuresHelper.type()) {
                        var $feature = $(ui.item);
                        var $after = $feature.prev(':visible');
                        self.featuresFeatureSort($feature, $after, $(this));
                    } else {
                        $(this).sortable('cancel');
                    }
                },
                'start' : function() {
                    $('.block.drop-target').addClass('drag-active');
                },
                'stop' : function() {
                    $('.block.drop-target').removeClass('drag-active');
                }
            }).find(':not:input').disableSelection();

            this.featuresInitDroppable($('#s-settings-feature-types li:not(.not-sortable)'));
            $('#s-settings-features-type-dialog').prependTo('#wa-app');

            $('#s-settings-features').on('change, click', ':input:checkbox[name$="\]\[types\]\[0\]"][name^="feature\["]', function() {
                self.featuresFeatureTypesChange($(this));
            });

            $('#s-settings-features').on('keypress', ':input[name$="\]\[name\]"][name^="feature\["]', function(e) {

                try {
                    if (e.which && e.which == 13) {
                        var feauture_id = $(this).parents('tr').data('feature');
                        self.featuresFeatureSave(feauture_id);
                    }
                } catch (e) {
                    $.shop.error(e);
                }
            });

            $('#s-settings-features').on('change', ':input.js-feature-types-control:first', function() {
                self.featuresFeatureValueTypeChange($(this));
            });

            $('#s-settings-features').on('change', ':input.js-feature-subtypes-control:first', function() {
                self.featuresFeatureValueTypeChainChange($(this));
            });

            $('#s-settings-features .color').hover(function() {
                $(this).css('cursor', 'pointer');
            }, function() {
                $(this).css('cursor', 'default');
            });

            if (this.helpers) {
                this.helpers.compileTemplates('#s-settings-content');
            }
        },

        /**
         * @deprecated
         * @param {JQuery} $feature
         * @param {} feature
         */
        featuresFeatureChange : function($feature, feature) {
            var $list = $feature.find('ul.js-feature-values:first');
            var $container = $list.parent();
            if (feature.selectable) {
                var self = this;
                $container.show();
                feature.values_template = feature.values_template || (this.features_options.value_templates[feature.type] || '');
                $feature.find('ul.js-feature-values:first').sortable({
                    'distance' : 5,
                    'opacity' : 0.75,
                    'items' : '> li[data-value-id]',
                    'handle' : '.sort',
                    'cursor' : 'move',
                    'tolerance' : 'pointer'
                })/* .find(':not:input').disableSelection() */;

                if (feature.values_template != $list.data('values_template')) {
                    $.shop.trace('$.settings.featuresFeatureChange template changed', [feature.values_template, $list.data('values_template')]);
                    $list.find(':input[name^="feature\[' + feature.id + '\]\[values\]"]').each(function() {
                        $(this).parents('li').remove();
                    });
                    $list.data('values_template', feature.values_template);
                    this.featuresFeatureValueAdd(feature.id);
                }
            } else {
                $feature.find('ul.js-feature-values:first').sortable("destroy");
                $container.hide();
            }

            $.shop.trace('$.settings.featuresFeatureChange', [feature, $feature]);
        },

        /**
         * @param {JQuery} $el
         */
        featuresFeatureTypesChange : function($el) {
            var checked = $el.attr('checked') || false;
            $.shop.trace('all type changed', [checked, $el]);
            var $container = $el.parents('ul');
            if (checked) {
                $container.find('li[data-type!="0"]').hide();
                $container.find('li[data-type!="0"] :checkbox').each(function() {
                    this.checked = checked;
                });
            } else {
                var type = this.featuresHelper.type();
                $container.find('li[data-type!="0"]').show();
                $container.find('li[data-type!="0"] :checkbox').each(function(index, el) {
                    $(this).attr('checked', el.defaultChecked || (type == $(this).val()));
                });
            }
        },

        features_data : {
            'feature_id' : 0,
            'value_id' : 0
        },

        /**
         * Disable section event handlers
         */
        featuresBlur : function() {
            $('#s-settings-features-type-dialog').off('click', 'a.js-action');
            $('#s-settings-features-type-dialog').remove();
            $('#s-settings-content').off('click', 'a.js-action');
            $('#s-settings-features').off('change, click');
        },

        /**
         * 
         * @param {String} tail
         */
        featuresAction : function(tail) {
            $.shop.trace('$.settings.featuresAction', [this.path, tail]);
            $('div.s-settings-form:first > div:hidden').show();
            this.featuresTypeSelect(parseInt(tail) || 0);
            $('div.s-settings-form:first > div.js-loading').remove();
        },

        /**
         * @param {JQuery} $el
         */
        featuresInitDroppable : function($el) {
            var self = this;
            $el.droppable({

                'accept-deleted' : function($el) {
                    var $this = $(this);
                    // $.shop.trace('accept', [$this, $this.is('li:not(.not-sortable)')]);
                    if (!$this.is('li:not(.not-sortable)')) {
                        return false;
                    }
                    return true;
                    var current = $el.data('types');
                    var accept = false;
                    var type = '' + $this.data('type');
                    if (current === undefined) {
                        accept = false;
                    } else {
                        accept = self.featuresFeatureTypeChangeAllowed(('' + current).split(' '), type);
                    }
                    if (type === 'null') {
                        if (accept) {
                            $this.show();
                        } else {
                            $this.hide();
                        }
                    }
                    return accept;
                },
                'activeClass-deleted' : "ui-state-hover",
                'hoverClass' : 'drag-newparent',
                'tolerance' : 'pointer',
                'drop' : function(event, ui) {
                    return self.featuresFeatureTypeChange(event, ui, this);
                }
            });
        },

        /**
         * Select feature types and filter data
         * 
         * @param {Integer} type
         */
        featuresTypeSelect : function(type) {
            /* change selected type and filter features rows */
            $.shop.trace('$.settings.featuresTypeSelect', type);
            $('#s-settings-feature-types li.selected').removeClass('selected');

            if ($('#s-settings-feature-types li[data-type="' + type + '"]').length) {
                var name = $('#s-settings-feature-types li[data-type="' + type + '"]').addClass('selected').find('span.js-type-name').text();
                $('#s-settings-features-type-name').text(name.replace(/(^[\r\n\s]+|[\r\n\s]+$)/mg, ''));
                $.when(this.featuresFilter(type)).done(function() {
                    // $('#s-settings-features').sortable(type ? 'enable' : 'disable');
                });
            } else {
                window.location.hash = '#/features/';
            }
        },

        featuresFeatureTypeChangeAllowed : function(current, target) {
            if (target != 'null') {
                return (current.indexOf(target) < 0);
            } else {
                return (current.indexOf(target) < 0) && (current.indexOf('' + this.path.tail) >= 0 || current.length == 1);
            }
        },

        featuresFeatureTypeChange : function(event, ui, el) {
            var $feature = ui.draggable;
            var target = parseInt($(el).data('type')) || 0;
            var current = this.featuresHelper.featureTypes($feature);
            $.shop.trace('$.settings.featuresFeatureTypeChange', [target, current, current.indexOf(target)]);
            /**
             * @todo remove from type
             * @todo move to all types
             */

            var self = this;
            if (current.indexOf(target) < 0) {

                $.post('?module=settings&action=featuresFeatureType', {
                    'feature' : $feature.data('feature'),
                    'type' : target
                }, function(data, textStatus, jqXHR) {
                    $.shop.trace('$.settings.featuresFeatureTypeChange ajax', data);

                    var index = current.indexOf(NaN);
                    if (index >= 0) {
                        current.splice(index, 1);
                    }
                    if (target) {
                        index = current.indexOf(0);
                        if (index >= 0) {
                            current.splice(index, 1);
                        }
                        current.push(target);
                    } else {
                        current = [target];
                    }
                }, 'json').complete(function() {
                    $feature.data('types', '' + current.join(' '));
                    $.shop.trace('$.settings.featuresFeatureTypeChange tmpl', current);
                    self.featuresHelper.featureCountByType();

                    if (current.length == 1) {
                        self.featuresFilter(self.featuresHelper.type(), true);
                    }
                }).error(function() {

                });
            }
        },

        /**
         * Filter visible features list by type
         * 
         * @param {Integer} type
         * @param {Boolean} animate use animation
         */
        featuresFilter : function(type, animate) {
            $.shop.trace('$.settings.featuresFilter', [type, animate]);
            if (type) {
                $('#s-settings-features-type-menu:hidden, #s-settings-features > tbody > tr > td > .sort').show();
            } else {
                $('#s-settings-features-type-menu:visible, #s-settings-features > tbody > tr > td > .sort').hide()
            }
            if (type || !this.features_options.show_all) {
                type = parseInt(type) || 0;
                var self = this;
                $('#s-settings-features tbody > tr:visible').filter(function() {
                    var types = self.featuresHelper.featureTypes($(this));
                    return (type && !types.length) || (types.length && types.indexOf(type) < 0);
                }).hide(animate ? 'slow' : null);
                $('#s-settings-features tbody > tr:hidden').filter(function() {
                    var types = self.featuresHelper.featureTypes($(this));
                    return (!type && !types.length) || types.indexOf(type) >= 0;
                }).show(animate ? 'slow' : null);

                /**
                 * @todo test speed
                 */

                $('#s-settings-features tbody:first').append($("#s-settings-features tbody:first > tr:visible").get().sort(function(a, b) {
                    if (type) {
                        a = $(a).data('sort') || {};
                        a = parseInt(a[type]) || 0;

                        b = $(b).data('sort') || {};
                        b = parseInt(b[type]) || 0;
                    } else {
                        a = parseInt($(a).data('feature')) || 0
                        b = parseInt($(b).data('feature')) || 0
                    }
                    return parseInt(a - b);
                }));

            } else {
                $('#s-settings-features tbody tr:hidden').show();
                $('#s-settings-features-type-menu:visible').hide();

                $('#s-settings-features tbody:first').append($("#s-settings-features tbody:first > tr:visible").get().sort(function(a, b) {
                    a = -parseInt($(a).data('feature')) || -1
                    b = -parseInt($(b).data('feature')) || -1
                    var sign = ((a * b > 0) && (a > 0)) ? -1 : a * b;
                    return parseInt(a - b) * sign;
                }));

            }
        },

        /**
         * @param {JQuery} $el
         */
        featuresTypeIcon : function($el) {
            var $item = $el.parents('li');
            var $container = $el.parents('ul');
            $container.find('li.selected').removeClass('selected');
            var icon = $item.addClass('selected').data('icon');
            var $form = $container.parents('form');

            $form.find(':input[name="icon"]').val(icon);
            $form.find(':input[name="icon_url"]').val('');
            $.shop.trace('$.settings.featuresTypeIcon', [icon, $el]);
            return false;
        },

        featuresTypeAdd : function() {
            var self = this;
            $('#s-settings-features-type-dialog').waDialog({
                onLoad : function(d) {
                    var $this = $(this);
                    var form = $this.find('form');

                    $(this).find('.js-add-type').show();
                    $(this).find('.js-edit-type').hide();

                    $this.find('ul li.selected').removeClass('selected');
                    var icon = $this.find('ul li:first').addClass('selected').data('icon');

                    $this.find(':input[name="icon"]').val(icon);
                    $this.find(':input[name="icon_url"]').val('');
                    $this.find(':input[name="id"]').val(null);
                    $this.find(':input[name="name"]').val('').focus();

                },
                onSubmit : function(d) {
                    var form = d.find('form');
                    $.post(form.attr('action'), form.serialize(), function(response) {
                        try {
                            if (response && (response.status == 'ok')) {
                                $.shop.trace('response', [response, response.data]);
                                var type = response.data;
                                $.tmpl('feature-type', type).insertBefore('#s-settings-feature-types li:last');
                                var $type = $('#s-settings-feature-types li[data-type="' + type.id + '"]');
                                self.featuresInitDroppable($type);
                                d.trigger('close');
                                window.location.hash = '#/features/' + type.id + '/';
                            } else {
                                // display error;
                            }
                        } catch (e) {
                            $.shop.error(e);
                            d.trigger('close');
                        }
                    }, 'json');
                    return false;
                }
            });
        },

        featuresTypeEdit : function() {
            var type = this.featuresHelper.type();
            $.shop.trace('$.settings.featuresTypeEdit', type);
            if (type) {
                var self = this;
                var $type = $('#s-settings-feature-types li[data-type="' + type + '"]');
                $('#s-settings-features-type-dialog').waDialog({
                    onLoad : function(d) {
                        var $this = $(this);
                        $.shop.trace('$.settings.featuresTypeEdit', this);
                        var name = $type.find('.js-type-name').text().replace(/(^[\r\n\s]+|[\r\n\s]+$)/mg, '');
                        var icon = $type.data('icon').replace(/^icon\.([\w\d\-_]+)$/, '$1');

                        $this.find('ul li.selected').removeClass('selected');
                        if ($this.find('ul li[data-icon="' + icon + '"]').length) {
                            $this.find('ul li[data-icon="' + icon + '"]').addClass('selected');
                            $this.find(':input[name="icon"]').val(icon);
                            $this.find(':input[name="icon_url"]').val('');
                        } else {
                            $this.find(':input[name="icon"]').val('');
                            $this.find(':input[name="icon_url"]').val(icon);
                        }
                        $this.find('.js-add-type').hide();
                        $this.find('.js-edit-type').show().find('.js-type-name').text(name);
                        $this.find(':input[name="id"]').val(type);
                        $this.find(':input[name="name"]').val(name).focus();

                    },
                    onSubmit : function(d) {
                        var $form = d.find('form');
                        $.post($form.attr('action'), $form.serialize(), function(response) {
                            try {
                                if (response && (response.status == 'ok')) {
                                    d.trigger('close');
                                    $.shop.trace('response', [response, response.data]);
                                    $type.replaceWith($.tmpl('feature-type', response.data));

                                    self.featuresTypeSelect(type);
                                    $('#s-settings-features span[data-type="' + response.data.id + '"]').each(function() {
                                        $(this).text(response.data.name);
                                    });
                                    $type = $('#s-settings-feature-types li[data-type="' + type + '"]');

                                    self.featuresInitDroppable($type);

                                    $('#s-settings-features tr.js-inline-edit ul li[data-type="' + type + '"]').each(function() {
                                        response.data['feature'] = {
                                            'id' : $(this).parents('tr').data('feature'),
                                            'types' : []
                                        };
                                        if ($(this).find(':checkbox:checked').length) {
                                            response.data.feature.types.push(type);
                                        }
                                        $.shop.trace('$.settings.featuresTypeEdit rename', response.data);
                                        $(this).html($.tmpl('edit-feature-type', response.data));
                                    });
                                } else {
                                    // display error;
                                }
                            } catch (e) {
                                $.shop.error(e);
                            }
                        }, 'json');
                        return false;
                    }
                });
            }
        },

        /**
         * Delete feature type
         * 
         * @param {Integer} type Type's ID
         * @return {Boolean}
         */
        featuresTypeDelete : function(type) {
            type = this.featuresHelper.type(type);
            $.shop.trace('$.settings.featuresTypeDelete', [type, this.path]);
            var self = this;
            if (type && confirm) {
                $.post('?module=settings&action=featuresTypeDelete', {
                    'id' : type
                }, function(response) {
                    try {
                        if (response && (response.status == 'ok')) {
                            $.shop.trace('response', [response, response.data, type]);
                            $('#s-settings-feature-types li[data-type="' + type + '"]').remove();
                            $('#s-settings-features span[data-type="' + type + '"]').remove();
                            $('#s-settings-features tr').filter(function() {
                                return self.featuresHelper.featureTypes($(this)).indexOf(type) > 0;
                            }).each(function() {
                                var pattern = new RegExp('\b' + type + '\b\s+');
                                $(this).data('types', $(this).data('types').replace(pattern, ''));
                            });

                            $('#s-settings-features li[data-type="' + type + '"]').remove();

                            window.location.hash = '#/features/';
                        } else {
                            // display error;
                        }
                    } catch (e) {
                        $.shop.error(e);
                    }
                }, 'json');
            }
            return false;
        },

        featuresTypeSort : function(id, after_id, list) {
            $.post('?module=settings&action=featuresTypeSort', {
                id : id,
                after_id : after_id
            }, function(response) {
                $.shop.trace('$.settings.featuresTypeSort result', response);
                if (response.error) {
                    $.shop.error('Error occurred while sorting product types', 'error');
                    list.sortable('cancel');
                }
            }, function(response) {
                $.shop.trace('$.settings.featuresTypeSort cancel', {
                    'data' : response
                });
                list.sortable('cancel');
                $.shop.error('Error occurred while sorting product types', 'error');
            });
        },

        featuresFeatureAdd : function() {
            try {
                $.shop.trace('featuresFeatureAdd env', this);
                var feature = {
                    'id' : --this.features_data.feature_id,
                    'type' : 'varchar',
                    'types' : [],
                    'selectable' : 0,
                    'multiple' : 0,
                    'values' : [],
                    'values_template' : this.features_options.value_templates['varchar'] || ''
                };
                var type = this.featuresHelper.type();
                if (type) {
                    feature.types.push(type);
                } else {
                    feature.types.push(0);
                    var selector = '#s-settings-feature-types li:not(.not-sortable)';
                    if (type) {
                        selector += '[data-type="' + type + '"]';
                    }
                    $(selector).each(function() {
                        var id = $(this).data('type');
                        if (id && id != undefined) {
                            feature.types.push(id);
                        }
                    });
                }
                feature.values.push({
                    'id' : --this.features_data.value_id,
                    'value' : ''
                });

                $.shop.trace('$.settings.featuresFeatureAdd', feature);
                var self = this;
                $.when($.tmpl('edit-feature', {
                    'types' : this.featuresHelper.types(),
                    'feature' : feature
                }).prependTo('#s-settings-features tbody')).done(function() {
                    var $feature = $('#s-settings-features tbody tr[data-feature="' + self.features_data.feature_id + '"]');
                    self.featuresFeatureChange($feature, feature);
                    if (!self.featuresHelper.type()) {
                        $feature.find(' > td > .sort').hide()
                    }

                    var $el = $feature.find(':input:checkbox[name$="\]\[types\]\[0\]"][name^="feature\["]');
                    self.featuresFeatureTypesChange($el);
                    $feature.find(':input[name$="\[name\]"]').focus();
                });
            } catch (e) {
                $.shop.error('exception', e);
            }
        },

        featuresFeatureEdit : function(feature_id) {
            feature_id = parseInt(feature_id);
            var $feature = $('#s-settings-features tbody tr[data-feature="' + feature_id + '"]');
            var type = $feature.data('type');
            var feature = {
                'id' : feature_id,
                'name' : $feature.find('.js-feature-name').text(),
                'code' : $feature.find('.js-feature-code').text(),
                'type' : type,
                'type_name' : $feature.find('.js-feature-type-name').text(),
                'types' : this.featuresHelper.featureTypes($feature),
                'selectable' : $feature.data('selectable'),
                'multiple' : $feature.data('multiple'),
                'values' : [],
                'values_template' : this.features_options.value_templates[type] || ''
            };

            $feature.find('ul.js-feature-values li').each(function() {
                var id = parseInt($(this).data('value-id'));
                if (id && id != undefined) {
                    feature.values.push({
                        'id' : id,
                        'value' : $(this).text().replace(/(^[\r\n\s]+|[\r\n\s]+$)/mg, '')
                    });
                }
            });
            $.shop.trace('$.settings.featuresFeatureEdit', [$feature.find('ul.js-feature-values li').length, feature.values.length]);
            $.shop.trace('$.settings.featuresFeatureEdit', feature);
            try {
                var self = this;
                var data = {
                    'types' : this.featuresHelper.types(),
                    'feature' : feature
                };
                $.when($feature.replaceWith($.tmpl('edit-feature', data))).done(function() {
                    var $edit_feature = $('#s-settings-features tbody tr[data-feature="' + feature_id + '"]');
                    if (self.features_options.revert) {
                        $edit_feature.data('cancel', $feature);
                    }
                    if (!self.featuresHelper.type()) {
                        $edit_feature.find(' > td > .sort').hide()
                    }
                    self.featuresFeatureChange($edit_feature, feature);
                    self.featuresFeatureTypesChange($(':checkbox[name="feature\[' + feature_id + '\]\[types\]\[0\]"]'));
                });
            } catch (e) {
                $.shop.error('exception', e);
            }
        },

        featuresFeatureCodeEdit : function(feature_id, $el) {
            var $container = $el.parents('td');
            $container.find('span.js-feature-code:first').hide();
            $el.hide();
            $container.find(':input[name$="\[code\]"]').show().focus();
        },

        featuresFeatureValueTypeChange : function($el) {
            var $selected = $el.find('option:selected');
            var $feature = $el.parents('td');
            var feature = {
                'id' : parseInt($feature.parents('tr').data('feature')),
                'type' : $selected.data('type'),
                'selectable' : $selected.data('selectable'),
                'multiple' : $selected.data('multiple')
            };
            // update hidden input value
            $feature.find(':input[name$="\[type\]"]').val(feature.type);
            $feature.find(':input[name$="\[selectable\]"]').val(feature.selectable);
            $feature.find(':input[name$="\[multiple\]"]').val(feature.multiple);

            this.featuresFeatureChange($feature, feature);

            var $select = $feature.find(':input.js-feature-subtypes-control:first');

            if (feature.type.match(/\*$/)) {
                var self = this;
                $select.show().trigger('change').focus();
            } else {
                $select.hide();
            }
            // retrieve input value controls
            var values = [];
            $.shop.trace('$.settings.featuresFeatureValueTypeChange', feature);
            return false;

            $feature.find(':input[name^="feature\[' + feature_id + '\]\[values\]"]').each(function() {
                var input_name = $(this).attr('name');
                var matches = input_name.match(/\[-?\d+\](\[value\])?$/);
                if (matches && matches.length) {
                    values.push({
                        'name' : input_name,
                        'value' : $(this).val()
                    });
                    $(this).parents('li').remove();
                }
            });
        },

        featuresFeatureValueTypeChainChange : function($el) {

            var $feature = $el.parents('td');
            var feature = {
                'id' : parseInt($feature.parents('tr').data('feature')),
                'type' : $feature.find(':input[name$="\[type\]"]').val(),
                'selectable' : $feature.find(':input[name$="\[selectable\]"]').val(),
                'multiple' : $feature.find(':input[name$="\[multiple\]"]').val()
            };

            var $selected = $el.find('option:selected');
            if (!$selected.length) {
                $selected = $el.find('option:first');
            }
            feature.type = $selected.data('type');
            $selected.select();
            // update hidden input value
            $feature.find(':input[name$="\[type\]"]').val(feature.type);
            $.shop.trace('$.settings.featuresFeatureValueTypeChainChange', feature);
            this.featuresFeatureChange($feature, feature);
        },

        featuresFeatureSave : function(feature_id) {
            var self = this;
            var $feature = $('#s-settings-features tbody tr[data-feature="' + feature_id + '"].js-inline-edit');
            var feature_raw = $feature.find(':input').serialize();
            $.shop.trace('$.settings.featuresFeatureSave', feature_raw);
            $.post('?module=settings&action=featuresFeatureSave', feature_raw, function(data, textStatus, jqXHR) {
                if (data.status == 'ok') {
                    var feature = data.data[feature_id];
                    feature.values_template = feature.values_template || (self.features_options.value_templates[feature.type] || '');
                    var error = false;

                    for (var v = 0; v < feature.values.length; v++) {
                        if (feature.values[v].error) {
                            error = true;
                            break;
                        }
                    }

                    $.shop.trace('response', feature.values);
                    $feature.replaceWith($.tmpl(error ? 'edit-feature' : 'feature', {
                        'types' : self.featuresHelper.types(!error ? true : false),
                        'feature' : feature
                    }));
                    if (error) {
                        $feature = $('#s-settings-features tbody tr[data-feature="' + feature_id + '"]');
                        $feature.on('click focus', 'input', function() {
                            var $this = $(this);
                            var $parent = $(this).parents('ul');
                            $parent.find('input.red').removeClass('red');
                            if ($this.hasClass('error')) {
                                var original = parseInt($(this).data('original-id')) || 0;
                                if (original) {
                                    $parent.find('input[name$="\[' + original + '\]"]').addClass('red');
                                }
                            }

                        });
                    }
                    var type = self.featuresHelper.type();
                    if ((type && !feature.types.length) || (feature.types.length && feature.types.indexOf(type) < 0)) {
                        self.featuresFilter(type, true);
                    } else {
                        if (type) {
                            $('#s-settings-features > tbody > tr > td > .sort').show();
                        } else {
                            $('#s-settings-features > tbody > tr > td > .sort').hide()
                        }
                    }

                }
            }, 'json').complete(function() {
                self.featuresHelper.featureCountByType();
            });
            return false;
        },
        featuresFeatureSort : function($feature, $after, $features) {

            var id = parseInt($feature.data('feature'));
            var after_id = $after.data('feature');
            if (after_id === undefined) {
                after_id = 0;
            } else {
                after_id = parseInt(after_id);
            }

            var type = this.featuresHelper.type();
            $.shop.trace('$.settings.featuresFeatureSort', [id, after_id, type, $features]);
            $.post('?module=settings&action=featuresFeatureSort', {
                feature_id : id,
                after_id : after_id,
                type_id : type
            }, function(data, textStatus, jqXHR) {
                if (data.status == 'ok') {
                    // update internal sort data
                    // use code like SQL
                    var new_sort = parseInt(($after.data('sort') || {})[type]) || 0;
                    if (new_sort) {
                        ++new_sort;
                    }
                    var current_sort = ($feature.data('sort') || {});
                    current_sort[type] = current_sort[type] || 0;
                    $.shop.trace('sort ' + id + '->' + after_id, [current_sort[type], new_sort]);

                    $features.find('tr:visible').each(function(index, el) {
                        var each_id = parseInt($(el).data('feature')) || 0;
                        if (each_id) {
                            var sort = $(el).data('sort') || {};
                            sort[type] = parseInt(sort[type]) || 0;
                            var changed = false;
                            if (new_sort > current_sort[type]) {
                                if ((new_sort >= sort[type]) && (sort[type] > current_sort[type])) {
                                    changed = [sort[type]];
                                    changed.push(--sort[type]);
                                }
                            } else if (new_sort < current_sort[type]) {
                                if ((new_sort <= sort[type]) && (sort[type] < current_sort[type])) {
                                    changed = [sort[type]];
                                    changed.push(++sort[type]);
                                }
                            }
                            if (changed) {
                                $.shop.trace('sort ' + each_id, changed);
                                $(el).data('sort', sort);
                            }
                        }
                    });

                    $.shop.trace('sort ' + $feature.data('feature'), [current_sort[type], new_sort]);
                    current_sort[type] = new_sort;
                    $feature.data('sort', current_sort);
                } else {
                    $.shop.trace('FeatureSort result: ' + textStatus, data);
                    $.shop.error('Error occurred while sorting features', data.errors || data);
                    $features.sortable('cancel');
                }
            }, 'json').error(function(jqXHR, errorText) {
                $features.sortable('cancel');
                $.shop.error('featuresFeatureSort ajaxError' + errorText, jqXHR);
            });
        },

        featuresFeatureDelete : function(feature_id) {
            var self = this;
            var $feature = $('#s-settings-features tbody tr[data-feature="' + feature_id + '"]');
            if (feature_id > 0) {
                $.post('?module=settings&action=featuresFeatureDelete', {
                    'feature_id' : feature_id
                }, function(data, textStatus, jqXHR) {
                    if (data.status == 'ok') {
                        $feature.hide('slow', function() {
                            $(this).remove();
                            self.featuresHelper.featureCountByType();
                        });

                    }
                }, 'json');
            } else {
                $feature.hide('slow', function() {
                    $(this).remove();
                    self.featuresHelper.featureCountByType();
                });
            }
        },

        /**
         * @param int feature_id
         */
        featuresFeatureValueAdd : function(feature_id) {
            $.shop.trace('featuresFeatureValueAdd env', this);
            var $feature = $('#s-settings-features tbody tr[data-feature="' + feature_id + '"].js-inline-edit');
            if (!$feature.length) {
                this.featuresFeatureEdit(feature_id);
                $feature = $('#s-settings-features tbody tr[data-feature="' + feature_id + '"].js-inline-edit');
            }
            var type = $feature.find(':input[name$="\[type\]"]').val();
            var template = (this.features_options.value_templates[type] || '');
            $.tmpl('edit-feature-value' + template, {
                'feature' : {
                    'id' : feature_id,
                    'value_template' : template
                },
                'id' : --this.features_data.value_id,
                'feature_value' : ''
            }).insertBefore($feature.find('ul.js-feature-values li:last'));
            $feature.find('ul.js-feature-values:first').sortable('refresh').find(':input:last').focus();
        },

        featuresFeatureValuesShow : function(feature_id) {
            var $container = $('#s-settings-features tbody tr[data-feature="' + feature_id + '"] ul.js-feature-values');
            $container.find('li:hidden').show();
            $container.find('li.js-more-link').hide();
        },

        featuresFeatureValueDelete : function(feature_id, value_id) {
            var $feature = $('#s-settings-features tbody tr[data-feature="' + feature_id + '"].js-inline-edit');
            var $input = $feature.find(':input[name^="feature\[' + feature_id + '\]\[values\]\[' + value_id + '\]"]');
            var $values = $input.parents('li');
            $values.hide('normal', function() {
                $values.remove();
            });
        },

        featuresFeatureRevert : function(feature_id) {
            if (this.features_options.revert) {
                var $feature = $('#s-settings-features tbody tr[data-feature="' + feature_id + '"].js-inline-edit');
                if (feature_id > 0) {
                    $.shop.trace('$.settings.featuresFeatureRevert', $feature.data('cancel'));
                    $feature.replaceWith($feature.data('cancel'));
                } else {
                    $feature.remove();
                }
            }
        },

        featuresHelper : {
            parent : $.settings,
            /**
             * Get current selected features type
             * 
             * @return int
             */
            type : function() {
                return 0 + parseInt($.settings.path.tail) || 0;
            },
            /**
             * Get list available types
             * 
             * @param boolean name_only
             * @return {}
             */
            types : function(name_only, type) {
                var types = {};
                var selector = '#s-settings-feature-types li:not(.not-sortable)';
                if (type) {
                    selector += '[data-type="' + type + '"]';
                }
                $(selector).each(function() {
                    var id = $(this).data('type');
                    if (id && id != undefined) {
                        if (name_only) {
                            types[id] = $(this).find('a').find('span.js-type-name').text();
                        } else {
                            types[id] = $(this).find('a').html().replace(/<span\s+class="count">\d*<\/span>/, '');
                        }
                        types[id] = types[id].replace(/(^[\r\n\s]+|[\r\n\s]+$)/mg, '');
                    }
                });
                return types;
            },
            /**
             * Get feature types
             * 
             * @param {} $feature
             * @return [int,...] array of feature types
             */
            featureTypes : function($feature) {
                var types = $feature.data('types');
                if (!types) {
                    return [];
                }
                return ('' + types).split(' ').map(function(a, b) {
                    return parseInt(a);
                });
            },
            featureCountByType : function() {
                var helper = this;
                var counter = {
                    0 : 0
                };
                $('#s-settings-features tbody tr').each(function() {
                    var types = helper.featureTypes($(this));
                    if (!types.length) {
                        types.push(0);
                    }
                    for (var i = 0; i < types.length; i++) {
                        if (typeof(counter[types[i]]) == 'undefined') {
                            counter[types[i]] = 0;
                        }
                        if (types[i] || !helper.parent.features_options.show_all) {
                            ++counter[types[i]];
                        }
                    }
                    if (helper.parent.features_options.show_all) {
                        ++counter[0];
                    }
                });

                $('#s-settings-feature-types li').each(function() {
                    var $this = $(this);
                    var id = $this.data('type');
                    var count = '0';
                    if (counter[id] != undefined) {
                        count = '' + counter[id];
                    }
                    $this.find('span.count').text(count);
                });
            },
            value : function(value, field) {
                if (typeof(value) != 'object') {
                    if (field) {
                        value = {
                            'value' : value.replace(/\s+.*$/, ''),
                            'unit' : value.replace(/^[^\s]\s+/, '')
                        };
                    } else {
                        value = {
                            'value' : value
                        }
                    }
                } else {
                    value['unit'] = value['unit'] || value.value.replace(/^[^\s]+\s+/, '');
                    value['value'] = value.value.replace(/\s+.*$/, '');
                }
                $.shop.trace('$.settings.featuresHelper.value', value);
                return field ? (value[field] || '') : value.value;
            }
        }

    });
} else {
    //
}
/**
 * {/literal}
 */
