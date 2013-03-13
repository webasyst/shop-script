/**
 * @names editTabFeatures*
 * @property {} features_options
 * @property {} features_values
 * @method editTabFeaturesInit
 * @method editTabFeaturesAction
 * @method editTabFeaturesBlur
 */
$.product = $.extend(true, $.product, {
    features_options : {
        'inline_values' : false,
        'value_templates' : {
            '' : '',
            'text' : '-text'
        }
    },

    features_data : {
        'feature_id' : 0,
        'value_id' : 0
    },
    editTabFeaturesHelper : {

}   ,

    editTabFeaturesInit : function() {
        var $tab = $('#s-product-edit-menu > li.features > a');
        $tab.attr('href', $tab.attr('href').replace(/\/features\/.*$/, '/features/'));
    },

    editTabFeaturesAction : function(path) {
        for (field in this.features_values) {
            var value = this.features_values[field];
            if (typeof(value) == 'object') {
                if ((typeof(value.indexOf) !== 'function') && (typeof(value.value) != 'undefined')) {
                    value = value.value;
                }
                $(':input[name^="product\[features\]\[' + field + '\]"]').each(function() {
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
    editTabFeaturesFeatureType : function($el) {
        var self = this;
        $el.hide();
        var $select = $el.parent().find('select.js-feature-types-control:first');
        $el.parent().find('.hint:hidden').show();
        $el.parents('.value').find(':input[name*="\[value\]"]').hide();
        $select.show().change(function() {
            self.editTabFeaturesFeatureTypeChange($(this));
        }).focus();
    },

    editTabFeaturesFeatureTypeChange : function($el) {
        var $selected = $el.find('option:selected');
        var $feature = $el.parents('div.field');
        var feature = {
            'id' : parseInt($feature.parents('tr').data('feature')),
            'code' : $feature.data('code'),
            'input' : '',
            'name' : $feature.find(':input[name$="\[name\]"]').val(),
            'type' : $selected.data('type'),
            'type_name' : $selected.text(),
            'value_template' : '',
            'selectable' : $selected.data('selectable'),
            'multiple' : $selected.data('multiple')
        };
        var val = $el.val();
        feature.input = '' + feature.code;
        feature.value_template = 'feature-value' + (this.features_options.value_templates[feature.type] || '') + '-template-js';

        $feature.find(':input[name$="\[type\]"]').val(feature.type);
        var $container = $feature.parent();

        try {
            var data = {
                'feature' : feature
            };
            $.when($feature.replaceWith(tmpl('feature-add-template-js', data))).done(function($c) {
                $feature = $container.find('> div[data-code="' + feature.code + '"]:first');
                $feature.find('.js-feature-type-name').text(feature.type_name);
                $feature.find('div.value select.js-feature-types-control').val(val);
                $feature.find('div.value :text:first').focus();
            });
        } catch (e) {
            $.shop.error('Exception ' + e.message, e);
        }
        return;

        $feature.find(':input[name$="\[type\]"]').val(feature.type);

        // change input control

        var $select = $feature.find(':input.js-feature-subtypes-control:first');
        if (feature.type.match(/\*$/)) {
            var self = this;
            $select.show().trigger('change').focus();
        } else {
            $select.hide();
        }
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

        return false;
        $.wa.dropdownsClose();
        // update text value
        var name = $el.html();
        $el.parents('ul').find('.js-feature-type:first').html(name)
        // update hidden input value
        // $('input[name="feature\[' + feature_id + '\]\[type\]"]').val(type);

        $.shop.trace('$.product.editTabFeaturesType', [feature_id, type, name]);
        var $target = $el.parents('div.value');
        // replace values by loading
        $.ajax('?module=settings&action=featuresFeatureControl', {
            'data' : {
                'value_type' : type
            },
            'dataType' : 'json',
            'type' : 'POST',
            'success' : function(data, textStatus, jqXHR) {
                if (data.status == 'ok') {

                    $target.find(':input').remove();
                    for (var i = 0; i < data.data.length; i++) {
                        $target.prepend(data.data[i]);
                    }

                } else {
                    $.shop.error('Error at $.settings.featuresFeatureValueTypeEdit', data.error);
                }
            },
            'error' : function(jqXHR, textStatus, errorThrown) {
                $.shop.error('Error at $.settings.featuresFeatureValueTypeEdit', errorThrown);
            }
        });
    },
    editTabFeaturesSave : function() {
        // convert extra features inputs
        /*
         * [][name] -> code; [][value] -> value || [][value] && [][unit]-> [value];
         */
    },
    editTabFeaturesSaved : function() {
        // reload tab while extra features added
        $.shop.trace('$.product.editTabFeaturesSaved', this.features_data);
        if ((this.features_data.feature_id < 0) || (this.features_data.value_id < 0)) {
            this.editTabFeaturesReload(null, true);
        }
    },

    editTabFeaturesReload : function(type, force) {
        type = this.helper.type(type);
        var params;
        if (!force) {
            params = $('.s-product-form.features:first :input').serialize();
            params += '&param[]=' + type;
        }

        var path = {
            id : this.path.id,
            mode : 'edit',
            tab : 'features'

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
    editTabFeaturesAppendValue : function(code, value) {
        var selector = ':input[name^="product\[features\]\[' + code + '\]"]:first';
        var $target = $(selector);
        var template = 'feature-value-template-js';
        if ($target.attr('type') == 'checkbox') {
            $target = $target.parents('div.value');
            template = 'feature-value-multiple-template-js';
        }
        try {
            $target.prepend(tmpl(template, {
                'code' : code,
                'value' : value
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
    editTabFeaturesValueAdd : function($el) {
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
                    'feature' : feature
                };
                if (feature.multiple) {
                    $.when($feature.find('div.value:first :checkbox:last').parents('label').after(tmpl('feature-value-multiple-template-js', data))).done(
                    function() {
                        $feature.find('div.value:first :checkbox:last+:input:first').focus();
                    });
                } else {
                    $.when($feature.find('div.value :input:first').attr('disabled', true).after(tmpl(feature.value_template, data))).done(function() {
                        $feature.find('div.value :input:first').focus();
                    });
                    $el.hide();
                }
            } catch (e) {
                $.shop.error('Exception ' + e.message, e);
            }
        }

    },

    editTabFeaturesValueDelete : function($el) {
        if ($el) {
            $el.parents('label').remove();
        }
    },

    editTabFeaturesAdd : function($el) {
        if ($el) {
            try {
                var data = {
                    'feature' : {
                        'name' : '',
                        'selectable' : '0',
                        'multiple' : '0',
                        'type' : 'varchar',
                        'code' : --this.features_data.feature_id,
                        'input' : this.features_data.feature_id,
                        'value_template' : 'feature-value-template-js'
                    }
                }
                // TODO check uniqie code
                var $container = $el.parents('div.field');
                $.when($container.before(tmpl('feature-add-template-js', data))).done(function() {
                    $container.parent().find('div.field[data-code="' + data.feature.code + '"]:last :text:first').focus();

                })
            } catch (e) {
                $.shop.error('Exception ' + e.message, e);
            }
        }
    }
});