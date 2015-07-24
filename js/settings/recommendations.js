$.extend($.settings = $.settings || {}, {
    recommendationsInit : function (options) {
        this.recommendations_options = options;
        // cross-selling
        $(".cross-selling .i-button-mini").iButton({
            labelOn : "",
            labelOff : "",
            classContainer: 'ibutton-container mini'
        }).change(function () {
            $(this).closest("div.s-ibutton-checkbox").find('span.status')
                .html(this.checked ? $_("On") : $_("Off")).toggleClass('s-off');
            var f = $(this).closest('div.field');
            if (this.checked) {
                f.find('.field-settings').show().find('select').removeAttr('disabled').val('alsobought');
            } else {
                f.find('.field-settings').hide().find('select').attr('disabled', 'disabled');
            }
            self.recommendationsSaveCrossSelling(this, this.checked ? 'alsobought': '');
        });

        var self = this;
        $(".cross-selling select").change(function () {
            self.recommendationsSaveCrossSelling(this, $(this).val());
        });
        // upselling
        $(".upselling .i-button-mini").iButton({
            labelOn : "",
            labelOff : "",
            className: 'mini'
        }).change(function () {
                $(this).closest("label.s-ibutton-and-label").children('span')
                    .html(this.checked ? $_("On") : $_("Off")).toggleClass('s-off');
                var f = $(this).closest('div.field');
                if (this.checked) {
                    f.children('.field-settings').show();
                    self.recommendationsRenderEdit(f);
                } else {
                    // save off
                    self.recommendationsSaveUpSelling(this);
                    f.children('.field-settings').empty().hide();
                }
        });
        $("div.upselling").on('click', 'a.customize', function () {
            self.recommendationsRenderEdit($(this).closest('div.field'));
            return false;
        });
    },

    recommendationsRenderEdit: function (elem) {
        var type_id = elem.data('type-id');
        var data = this.recommendations_options.data[type_id];
        var table = $('<table class="zebra"></table>');
        var self = this;
        var form = $('<form method="post"><input type="hidden" name="value" value="1"><input type="hidden" name="type_id" value="' + type_id + '"></form>').submit(function () {
            $.post("?module=settings&action=recommendationsSave&setting=upselling", $(this).serialize(), function (response) {
                if (response.status == 'ok') {
                    elem.children('.field-settings').html('<p class="small">' + response.data.html +
                        ' <a href="#" class="customize inline-link"><b><i>' + $_('Customize') + '</i></b></a>' + '</p>');
                    self.recommendations_options.data[response.data.type_id] = response.data.data;
                }
            }, "json");

            return false;
        });
        elem.children('.field-settings').html('<p class="small">' + $_('Upsell products will be offered for a particular base product according to the following criteria:') + '</p>').append(form.append(table));
        for (var i = 0; i < data.length; i++) {
            this.recommendationsRenderEditFeature(data[i], table, type_id);
        }
        table.append('<tr class="white"><td colspan="4"><input type="submit" value="' + $_("Save") + '"></td></tr>');
    },

    recommendationsRenderEditFeature: function (data, table, type_id) {

        var f = this.recommendationsGetFeature(data);
        var tr = $('<tr></tr>');
        var checkbox = $('<input name="data[' + data.feature + '][feature]" id="checkbox-' + type_id + '-' + data.feature + '" value="' + data.feature + '" type="checkbox" ' + (data.cond ? 'checked' : '') +'>').click(function () {
            var p = $(this).closest('tr');
            if ($(this).is(':checked')) {
                p.find('.v').show();
                p.find('select, input:hidden').removeAttr('disabled');
                p.find('select').change();
            } else {
                p.find('.v').hide();
                p.find('select, input:hidden').attr('disabled', 'disabled');
            }
        });
        tr.append($('<td class="min-width"></td>').append(checkbox));
        tr.append('<td><label for="checkbox-' + type_id + '-' + data.feature + '">' + f.name + '</label>' + (f.id ? '<input type="hidden" name="data[' + data.feature + '][feature_id]" value="' + f.id + '">' : '') + '</td>');
        if (f.type == 'int' || f.type == 'float') {
            var td = $('<td><div class="v" ' + (data.cond ? '' : 'style="display:none"') + '></div></td>');
            tr.append(td);
            var slider = $('<div class="v" ' + (data.cond ? '' : 'style="display:none"') + '></div>');
            var value_td = $('<td></td>');
            value_td.append('<input ' + (data.cond ? '' : 'disabled="disabled"') + ' type="hidden" name="data[' + data.feature + '][cond]" value="between">');
            var value_input = $('<input ' + (data.cond ? '' : 'disabled="disabled"') + ' type="hidden" name="data[' + data.feature + '][value]" value="">');
            value_td.append(value_input);
            tr.append(value_td.append(slider));
            table.append(tr);
            var values = data.value ? data.value.split(',') : [ -10, 20 ];
            slider.slider({
                range: true,
                min: -100,
                max: 100,
                values: values,
                slide: function( event, ui ) {
                    td.find('div.v').html(ui.values[ 0 ] + "% &mdash; " + ui.values[1] + '%');
                    value_input.val(ui.values[ 0 ] + ',' + ui.values[ 1 ]);
                },
                change: function (event, ui) {
                    var max = $(this).slider('option', 'max');
                    var min = $(this).slider('option', 'min');
                    if (ui.values[1] == max) {
                        $(this).slider('option', 'max', Math.round(max * 1.3));
                        $(this).slider('option', 'values', ui.values);
                    }
                    if (ui.values[0] == min) {
                        $(this).slider('option', 'min', Math.round(min * 1.3));
                        $(this).slider('option', 'values', ui.values);
                    }
                }
            });
            td.find('div.v').html(values[ 0 ] + "% &mdash; " + values[1] + '%');
            value_input.val(values[ 0 ] + ',' + values[ 1 ]);
        } else {
            var conds = [];
            if (data.feature == 'tag') {
                conds.push(['contain', $_('contain')]);
            } else {
                conds.push(['same', $_('matches base product value')]);
                conds.push(['notsame', $_('differs from base product value')]);
                if (f.selectable && f.multiple) {
                    conds.push(['all', $_('all of selected values (AND)')]);
                    conds.push(['any', $_('any of selected values (OR)')]);
                } else {
                    conds.push(['is', $_('is')]);
                }
            }
            var elem_values = $('<div class="v"></div>');
            var select = $('<select ' + (data.cond ? '' : 'disabled="disabled"') + ' name="data[' + data.feature + '][cond]"></select>');
            for (var i = 0; i < conds.length; i++) {
                select.append('<option ' + (data.cond == conds[i][0] || conds.length == 1 ? 'selected' : '') + ' value="' + conds[i][0] + '">' + conds[i][1] + '</option>')
            }
            select.change(function () {
                var v  = $(this).val();
                if (v == 'same' || v == 'notsame') {
                    elem_values.html('&nbsp;');
                } else {
                    if (!f.selectable) {
                        elem_values.html($('<input type="text" name="data[' + data.feature + '][value]">').val(data.value || ''));
                    } else {
                        if (f.multiple) {
                            var vs = data.value ? data.value.split(',') : [];
                            var html = '';
                            for (var i = 0; i < f.values.length; i++) {
                                html += '<label><input ' + ($.inArray('' + f.values[i][0], vs) != -1 ? 'checked' : '') + ' name="data[' + data.feature + '][value][]" value="' + f.values[i][0] + '" type="checkbox">' + f.values[i][1] + '</label> ';
                            }
                            elem_values.html(html);
                        } else {
                            var html = '<select name="data[' + data.feature + '][value]">';
                            for (var i = 0; i < f.values.length; i++) {
                                html += '<option value="' + f.values[i][0] + '">' + f.values[i][1] + '</option>';
                            }
                            html += '</select>';
                            elem_values.html(html);
                        }
                    }
                }
            });
            if (data.cond) {
                select.change();
            }
            tr.append($('<td></td>').append(select));
            tr.append($('<td></td>').append(elem_values));
            table.append(tr);
        }


    },

    recommendationsGetFeature: function (data) {
        if (data.feature_id && data.feature_id != '0') {
            return this.recommendations_options.features[data.feature_id];
        } else {
            if (data.feature == 'price') {
                return {'name': $_('Price'), 'type': 'float'};
            } else if (data.feature == 'tag') {
                return {'name': $_('Tag'), 'type': 'varchar'};
            } else if (data.feature == 'type_id') {
                return this.recommendations_options.features['type_id'];
            }
        }
    },


    recommendationsSaveCrossSelling: function (elem, value) {
        var f = $(elem).closest('.field');
        $.post("?module=settings&action=recommendationsSave&setting=cross-selling", {
            type_id: f.data('type-id'),
            value: value
        }, function (response) {
            var icon = $('<i style="display: none; margin-left: 20px" class="icon10 yes"></i>');
            var s = f.find('div.field-settings select');
            if (!s.next('i.icon10').length) {
                icon.insertAfter(s);
                icon.fadeIn('slow', function () {
                    icon.fadeOut(1000, function () {
                        $(this).remove();
                    });
                });
            }
        }, "json");
    },

    recommendationsSaveUpSelling: function (elem) {
        var f = $(elem).closest('.field');
        $.post("?module=settings&action=recommendationsSave&setting=upselling", {
            type_id: f.data('type-id'),
            value: 0
        }, function (response) {
            var icon = $('<i style="display: none; margin-left: 20px" class="icon10 yes"></i>');
            var s = f.find('label.s-ibutton-and-label > span');
            if (!s.find('i.icon10').length) {
                s.append(icon);
                icon.fadeIn('slow', function () {
                    icon.fadeOut(1000, function () {
                        $(this).remove();
                    });
                });
            }
        }, "json");
    }

});