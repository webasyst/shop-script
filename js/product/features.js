/**
 * @names editTabFeatures*
 * @property {} features_options
 * @property {} features_values
 * @method editTabFeaturesInit
 * @method editTabFeaturesAction
 * @method editTabFeaturesBlur
 */
$.product = $.extend(true, $.product, {
    features_options: {
        'inline_values': false,
        'value_templates': {
            '': '',
            'text': '-text'
        }
    },

    features_data: {
        'feature_id': 0,
        'value_id': 0
    },
    editTabFeaturesHelper: {

    },

    editTabFeaturesInit: function () {
        var $tab = $('#s-product-edit-menu > li.features > a');
        $tab.attr('href', $tab.attr('href').replace(/\/features\/.*$/, '/features/'));
    },

    editTabFeaturesAction: function (path) {
        for (field in this.features_values) {
            var value = this.features_values[field];
            if (typeof(value) == 'object') {
                if ((typeof(value.indexOf) !== 'function') && (typeof(value.value) != 'undefined')) {
                    value = value.value;
                }
                $(':input[name^="product\[features\]\[' + field + '\]"]').each(function () {
                    try {
                        if (value.indexOf($(this).val()) >= 0) {
                            $(this).attr('checked', true);
                        } else {
                            $(this).removeAttr('checked');
                        }
                    } catch (e) {
                        $.shop.error('Exception ' + e.message, e);
                        $.shop.trace('value', [typeof(value), value]);
                    }
                });
            } else {
                $(':input[name^="product\[features\]\[' + field + '\]"]').val([value]);
            }
            $.shop.trace('update input field', [field, value, typeof(value)]);
        }
        this.features_values = {};
        var params = (path.tail || '').split('/');
        if (params.length) {
            $.shop.trace('$.product.editTabFeaturesAction', params);
            var actionNameChunk, callable, actionName = 'editTabFeatures';
            while (actionNameChunk = params.shift()) {
                actionName += actionNameChunk.substr(0, 1).toUpperCase() + actionNameChunk.substr(1);
                callable = this.isCallable(actionName);
                $.shop.trace('$.product.editTabFeaturesAction try', [actionName, callable, params]);
                if (callable) {
                    this.call(actionName, params);
                }
            }
        }
    },

    /**
     * Show feature value type selector
     *
     * @param {} $el
     */
    editTabFeaturesFeatureType: function ($el) {
        $.shop.trace('$.product.editTabFeaturesFeatureType', $el);
        var self = this;
        $el.hide();
        var $feature = $el.parents('.value');
        var $select = $feature.find('select.js-feature-types-control:first');
        $el.parent().find('.hint:hidden').show();
        $feature.find(':input[name*="\[value\]"],.input').hide();
        $select.show().change(function () {
            self.editTabFeaturesFeatureTypeChange($(this));
        }).focus();
        $feature.on('change', ':input.js-feature-subtypes-control', function () {
            self.editTabFeaturesFeatureTypeChainChange($(this));
        });
    },

    editTabFeaturesFeatureTypeChange: function ($el) {
        var $selected = $el.find('option:selected');
        var $feature = $el.parents('div.field');
        var feature = {
            'code': $feature.data('code'),

            'type': $selected.data('type'),
            'selectable': $selected.data('selectable'),
            'multiple': $selected.data('multiple'),
            'type_name': $selected.text()
        };
        // feature.type = $el.val();

        $feature.find(':input[name$="\[type\]"]').val(feature.type);
        $feature.find(':input[name$="\[selectable\]"]').val(feature.selectable);
        $feature.find(':input[name$="\[multiple\]"]').val(feature.multiple);

        var subtype = false;
        var $select = $feature.find(':input.js-feature-subtypes-control').each(function () {
            if (feature.type == $(this).data('subtype')) {
                $(this).show().focus();
                subtype = true;
            } else {
                $(this).hide();
            }
        });
        return subtype ? false : this.editTabFeaturesFeatureChange($feature, feature);
    },


    /**
     *
     * @param {jQuery} $el
     */
    editTabFeaturesFeatureTypeChainChange: function ($el) {
        var $selected = $el.find('option:selected');
        var $feature = $el.parents('div.field');
        var feature = {
            'code': $feature.data('code'),
            'type': $feature.find(':input[name$="\[type\]"]').val(),
            'selectable': $feature.find(':input[name$="\[selectable\]"]').val(),
            'multiple': $feature.find(':input[name$="\[multiple\]"]').val(),


            'type_name': $selected.text()
        };

        var $selected = $el.find('option:selected');
        if (!$selected.length) {
            $selected = $el.find('option:first');
        }
        feature.type = $selected.data('type');
        $selected.select();
        // update hidden input value
        $feature.find(':input[name$="\[type\]"]').val(feature.type);
        $.shop.trace('$.product.editTabFeaturesFeatureTypeChainChange', feature);
        return this.editTabFeaturesFeatureChange($feature, feature);
    },


    /**
     * @param {jQuery} $feature
     * @param {Object} feature
     */
    editTabFeaturesFeatureChange: function ($feature, feature) {
        var $container = $feature.parent();
        try {
            feature.name = $feature.find(':input[name$="\[name\]"]').val();
            feature.input = '' + feature.code;
            feature.value_template = 'feature-value' + (this.features_options.value_templates[feature.type] || '') + '-template-js';
            var data = {
                'feature': feature
            };
            $.when($feature.replaceWith(tmpl('feature-add-template-js', data))).done(function ($c) {
                $feature = $container.find('> div[data-code="' + feature.code + '"]:first');
                $feature.find('.js-action').attr('title', feature.type_name);
                $feature.find('div.value :text:first').focus();
            });
        } catch (e) {
            $.shop.error('Exception ' + e.message, e);
        }

        $.shop.trace('$.product.editTabFeaturesFeatureChange', [feature, $feature]);
        return false;
    },

    editTabFeaturesSave: function () {
        // convert extra features inputs
        /*
         * [][name] -> code; [][value] -> value || [][value] && [][unit]-> [value];
         */
    },
    editTabFeaturesSaved: function () {
        // reload tab while extra features added
        $.shop.trace('$.product.editTabFeaturesSaved', this.features_data);
        if ((this.features_data.feature_id < 0) || (this.features_data.value_id < 0)) {
            this.editTabFeaturesReload(null, true);
        }
    },

    editTabFeaturesReload: function (type, force) {
        type = this.helper.type(type);
        var params;
        if (!force) {
            params = $('.s-product-form.features:first :input').serialize();
            params += '&param[]=' + type;
        }

        var path = {
            id: this.path.id,
            mode: 'edit',
            tab: 'features'

        }
        $.shop.trace('$.product.editTabFeaturesReload', [type, params]);
        $.product.editTabLoadContent(path, params);
    },

    /**
     * Append to feature selector new available value
     *
     * @param String code
     * @param String value
     */
    editTabFeaturesAppendValue: function (code, value) {
        var selector = ':input[name^="product\[features\]\[' + code + '\]"]:first';
        var $target = $(selector);
        var template = 'feature-value-template-js';
        if ($target.attr('type') == 'checkbox') {
            $target = $target.parents('div.value');
            template = 'feature-value-multiple-template-js';
        }
        try {
            $target.prepend(tmpl(template, {
                'code': code,
                'value': value
            }));
            // select it
            $(':input[name="product\[features\]\[' + code + '\]"]').val([value]);
        } catch (e) {
            $.shop.error('Error ' + e.message, [code, value]);
        }
    },

    /**
     * Show dialog for insert new feature value
     *
     * @param String code
     * @param data type
     */
    editTabFeaturesValueAdd: function ($el) {
        var self = this;
        if ($el) {
            var $feature = $el.parents('div.field');
            var feature = $feature.data();
            feature['value_template'] = 'feature-value' + (this.features_options.value_templates[feature.type] || '') + '-template-js';
            $.shop.trace('$.product.editTabFeaturesValueAdd', [feature]);

            try {
                if (feature.multiple) {
                    feature.input = '' + feature.code + '][' + (--this.features_data.value_id);
                } else {
                    feature.input = '' + feature.code;
                    --this.features_data.value_id;
                }

                var data = {
                    'feature': feature
                };
                if (feature.multiple) {
                    $.when($feature.find('div.value:first > a.js-action:first').before(tmpl('feature-value-multiple-template-js', data))).done(
                        function () {
                            $feature.find('div.value:first :checkbox:last+:input:first').focus();
                        });
                } else {
                    $.when($feature.find('div.value :input:first').attr('disabled', true).after(tmpl(feature.value_template, data))).done(function () {
                        $feature.find('div.value :input:first').focus();
                    });
                    $el.hide();
                }
            } catch (e) {
                $.shop.error('Exception ' + e.message, e);
            }
        }

    },

    editTabFeaturesValueDelete: function ($el) {
        if ($el) {
            $el.parents('label').remove();
        }
    },

    editTabFeaturesAdd: function ($el) {
        if ($el) {
            try {
                var data = {
                    'feature': {
                        'name': '',
                        'selectable': '0',
                        'multiple': '0',
                        'type': 'varchar',
                        'code': --this.features_data.feature_id,
                        'input': this.features_data.feature_id,
                        'value_template': 'feature-value-template-js'
                    }
                }
                // TODO check uniqie code
                var $container = $el.parents('div.field');
                $.when($container.before(tmpl('feature-add-template-js', data))).done(function () {
                    $container.parent().find('div.field[data-code="' + data.feature.code + '"]:last :text:first').focus();

                })
            } catch (e) {
                $.shop.error('Exception ' + e.message, e);
            }
        }
    }
});