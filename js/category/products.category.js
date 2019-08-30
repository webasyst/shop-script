var shopDialogProductsCategory = (function ($) {

    shopDialogProductsCategory = function (options) {
        var that = this;

        // DOM
        that.$wrapper = options['$wrapper'];

        // VARS
        that.SEARCH_STEP = options['SEARCH_STEP'];
        that.category_id = options['category_id'];
        that.category_type = options['category_type'];
        that.account_name = options['account_name'];
        that.filter_count = options['filter_count'];

        that.filter_ignore_id = [];
        that.ignore_id = [];

        // TEMPLATES
        that.filter_element_html = options['templates']['filter_element_html'];

        // TEXT
        that.show_more_filters_text = options['texts']['show_more_filters_text'];

        // INIT
        that.initClass();
    };

    shopDialogProductsCategory.prototype.initClass = function () {
        var that = this;
        that.initAutocomplete();
        that.initEvent();
        that.initFilter();
        that.initMetaTagEdit();
        that.initFrontendUrlEdit();
        that.initCategoryVisibility();
        that.initSocialMetaTag();
        that.initShowMoreFilters();
        that.initFilterIgnoreId();
        that.updateShowFilterButton();
        that.initFilterAutocomplete();

        var $range = that.$wrapper.find('*[data-slider="range"]');
        if ($range.length) {
            $range.each(function () {
                that.initRangeSlider('.js-feature-slider-' + $(this).data('code'));
            });
        }
    };

    shopDialogProductsCategory.prototype.initEvent = function () {
        var that = this;

        that.initDynamicSettingsEvent();
        that.disableManualSorting();
        that.initShowMore();
        that.initShowAllFeatureValue();
        that.initGetFeatures();
    };


    shopDialogProductsCategory.prototype.initShowMoreFilters = function () {
        var that = this,
            url = '?module=categoryGetFeatures';

        that.$wrapper.on('click', '.js-show-more-filters', function (e) {
            e.preventDefault();
            var data = {
                category: that.category_id,
                offset: that.getFilterOffset(),
                ignore_id: that.filter_ignore_id,
                category_type: that.category_type,
            };

            $.post(url, data).success(function (response) {
                if (!response || !response.data.features) {
                    return false;
                }
                var features = response.data.features;
                $.each(features, function (i, filter) {
                    that.setFilterElement(filter)
                });

                that.updateShowFilterButton();
            });
        })
    };

    shopDialogProductsCategory.prototype.updateShowFilterButton = function () {
        var that = this,
            left = that.SEARCH_STEP,
            show_link = that.$wrapper.find('.js-show-more-filters'),
            filters = that.$wrapper.find('.js-category-filters .js-filter-checkbox').length,
            text = that.show_more_filters_text;


        filters -= 1;
        filters -= that.filter_ignore_id.length;
        if (filters + that.SEARCH_STEP > that.filter_count) {
            left = that.filter_count - filters;
        }

        if (left >= 1) {
            var total_left = that.filter_count - filters;

            text = text.replace(/%d/g, left);
            text = text.replace(/%all/g, total_left);
            show_link.text(text);
        } else {
            show_link.hide();
        }
    };

    shopDialogProductsCategory.prototype.setFilterElement = function (filter, checked) {
        var that = this,
            template = that.filter_element_html,
            category_filters = that.$wrapper.find('.js-category-filters');

        checked = (checked ? checked : false);

        template = template.replace(/%id/g, filter.id);
        template = template.replace(/%name/g, filter.name);
        template = template.replace(/%code/g, filter.code);
        template = template.replace(/%type_name/g, filter.type_name);

        category_filters.append(template);

        if (checked) {
            that.$wrapper.find('.js-category-filter input[data-filter-id="' + filter.id + '"]').prop("checked", true);
        }
    };

    shopDialogProductsCategory.prototype.getFilterOffset = function () {
        var that = this,
            offset = that.$wrapper.find('.js-category-filters .js-filter-checkbox').length;

        // удаляем price
        offset -= 1;

        // Удаляем сохраненные фильтры и добавленные через автокомплит
        offset -= that.filter_ignore_id.length;

        if (offset < 0) {
            offset = 0;
        }

        return offset;
    };

    shopDialogProductsCategory.prototype.initFilterIgnoreId = function () {
        var that = this,
            result = [],
            filters = that.$wrapper.find('.js-category-filters .js-filter-checkbox:checked');

        filters.each(function () {
            var val = $(this).val();
            if (val !== 'price') {
                result.push(val);
            }
        });

        that.filter_ignore_id = result;
    };

    shopDialogProductsCategory.prototype.initFilterAutocomplete = function () {
        var that = this,
            $input = that.$wrapper.find('.js-filter-autocomplete'),
            url = "?action=autocomplete&type=filter";

        $input.autocomplete({
            source: function (request, response) {
                var data = {
                    term: request.term,
                    category_id: that.category_id,
                    options: {
                        ignore_id: that.filter_ignore_id,
                        category_type: that.category_type,
                    }
                };
                $.post(url, data, function (data) {
                    response(data);
                }, 'json');
            },
            minLength: 1,
            delay: 300,
            create: function () {
                //move autocomplete container
                $input.autocomplete("widget").appendTo(".js-filter-autocomplete-block")
            },
            select: function (event, ui) {
                event.preventDefault();
                that.setFilterElement(ui.item, true);
                that.filter_ignore_id.push(parseInt(ui.item.id));
                $(this).val("");
                return false;
            },
            focus: function () {
                return false;
            }
        });
    };

    shopDialogProductsCategory.prototype.initAutocomplete = function () {
        var that = this,
            $input = that.$wrapper.find('.js-autocomplete'),
            ignore_id = [];

        if ($input.length) {
            // Init autocomplete
            $input.autocomplete({
                source: function (request, response) {
                    // add the used features to ignore
                    that.$wrapper.find('.js-condition-feature').each(function () {
                        var f_id = $(this).data('feature_id');
                        if ($.isNumeric(f_id)) {
                            if ($.inArray(f_id, ignore_id) == -1) {
                                ignore_id.push(f_id);
                            }
                        }
                    });

                    $.ajax({
                        type: "POST",
                        url: "?action=autocomplete&type=feature",
                        data: {
                            term: request.term,
                            options: {
                                ignore_id: ignore_id,
                                count: 1
                            }
                        },
                        success: function (data) {
                            var error_field = that.$wrapper.find('.js-autocomplete-wrapper .errormsg');

                            response(data);
                            if (data.length === 0) {
                                // Do logic for empty result.
                                error_field.html('').html($_('Matching features were not found or are already selected.'));
                            } else {
                                error_field.html('');
                            }
                        },
                        dataType: 'json'
                    });
                },
                minLength: 1,
                delay: 300,
                create: function () {
                    //move autocomplete container
                    $input.autocomplete("widget").appendTo(".js-autocomplete-block")
                },
                select: function (event, ui) {
                    that.renderFeature(ui.item.id);
                    //add feature.id to ignore array
                    that.ignore_id.push(parseInt(ui.item.id));
                    return false;
                },
                focus: function () {
                    return false;
                }
            });

        }
    };
    shopDialogProductsCategory.prototype.initRangeSlider = function (wrapper) {
        var begin = $(wrapper).find('.js-begin'),
            end = $(wrapper).find('.js-end'),
            begin_value = parseFloat(begin.attr('placeholder')),
            end_value = parseFloat(end.attr('placeholder')),
            step = 1,
            $slider = $(wrapper).find('.js-range-slider');

        if ($slider.data('step')) {
            step = parseFloat($slider.data('step'));
        } else {
            var diff = end_value - begin_value;
            if (Math.round(begin_value) != begin_value || Math.round(end_value) != end_value) {
                step = diff / 10;
                var tmp = 0;
                while (step < 1) {
                    step *= 10;
                    tmp += 1;
                }
                step = Math.pow(10, -tmp);
                tmp = Math.round(100000 * Math.abs(Math.round(begin_value) - begin_value)) / 100000;
                if (tmp && tmp < step) {
                    step = tmp;
                }
                tmp = Math.round(100000 * Math.abs(Math.round(end_value) - end_value)) / 100000;
                if (tmp && tmp < step) {
                    step = tmp;
                }
            }
        }

        var base_slider_value = 100,
            min_val = parseFloat(begin.val() ? begin.val() : 0),
            max_val = parseFloat(end.val() ? end.val() : (begin.val() > base_slider_value ? parseFloat(begin.val()) + base_slider_value : base_slider_value));

        $slider.slider({
            range: true,
            min: min_val - 3,
            max: max_val + 5,
            step: step,
            values: [min_val, max_val],
            slide: function (event, ui) {
                var s_min_option = $(this).slider('option', 'min'),
                    s_max_option = $(this).slider('option', 'max');

                //$(ui.handle).index() The numeric index of the handle being moved.

                if ($(ui.handle).index() != 2) {
                    if (ui.values[0] == s_min_option) {
                        $(this).slider("option", "min", s_min_option - step);
                    }
                    begin.val(ui.values[0]);
                }

                if ($(ui.handle).index() != 1) {
                    if (ui.values[1] == s_max_option) {
                        $(this).slider("option", "max", s_max_option + step);
                    }
                    end.val(ui.values[1]);
                }
            },
            stop: function (event, ui) {
                end.change();
            }
        });

        begin.add(end).change(function () {
            var v_min = begin.val() === '' ? $slider.slider('option', 'min') : parseFloat(begin.val());
            var v_max = end.val() === '' ? $slider.slider('option', 'max') : parseFloat(end.val());
            if (v_max >= v_min) {
                $slider.slider('option', 'values', [v_min, v_max]);
                // set min max options slider
                $slider.slider('option', 'max', parseFloat(v_max) + 5);
                $slider.slider('option', 'min', parseFloat(v_min) - 3);
            }
        });

    };

    /*
     * Function render feature field or feature value
     * @param feature info as in db
     * @param feature_block jquery-container where to put DOM
     * @param template jquery-template to use in rendering
     * @param checked selected feature (use in autocomplete)
     * @param show_all shows all features values ​​(use in editing dynamic category)
     */
    shopDialogProductsCategory.prototype.initShowFeatures = function (feature, feature_block, template, checked, show_all) {
        var that = this,
            f_type = feature.type.substr(0, 5);


        if (f_type == 'range') {
            var range_values_min = [],
                range_values_max = [];

            // find the min and max value for the range values
            for (var key in feature.values) {
                if (feature.values.hasOwnProperty(key)) {
                    range_values_min.push(parseInt(feature.values[key].begin));
                    range_values_max.push(parseInt(feature.values[key].end));
                }
            }

            // clear feature.values and set begin&end values
            feature.values = {};
            feature.values[0] = {
                "begin": Math.min.apply(Math, range_values_min) ? Math.min.apply(Math, range_values_min) : "",
                "end": Math.max.apply(Math, range_values_max) ? Math.max.apply(Math, range_values_max) : ""
            }

        }

        //render field template
        feature_block.append(
            tmpl(template, {
                feature: feature,
                default_checked: checked,
                show_all: show_all
            })
        );

        //render slider
        if (f_type === 'range') {
            that.initRangeSlider('.js-feature-slider-' + feature.code);
        }

    };

    shopDialogProductsCategory.prototype.initGetFeatures = function () {
        var that = this,
            $wrapper = this.$wrapper;

        $wrapper.on('change', '.js-condition-feature', function () {
            var $feature_block = $(this).closest('tr').find('.js-condition-feature-block');

            if (this.checked) {
                var feature_id = $(this).data('feature_id'),
                    action_url = "?module=category&action=getFeatures&feature_id=" + feature_id,
                    loading = $(this).attr('data-loading');

                if (that.category_id === 'new') {
                    if (!loading) {
                        var this_elem = this;

                        //show loading
                        $(this_elem).closest('.js-condition-feature-names').append('<i class="icon16 loading"></i>');

                        $.get(action_url, function (r) {
                            if (r.status === "ok") {
                                if (r.data.features) {
                                    var feature = r.data.features[feature_id];
                                    that.initShowFeatures(feature, $feature_block, 'template_feature_field_values', true, false)
                                }
                                $(this_elem).attr('data-loading', 'ok');
                                //remove loading
                                $(this_elem).closest('.js-condition-feature-names').find('i.loading').remove();
                            }
                        });
                    }
                }

                $feature_block.show();
                //enabled all input elements in feature block
                $feature_block.find(':input').each(function () {
                    $(this).attr('disabled', false)
                });

            } else {
                $feature_block.hide();
                //disabled all input elements in feature block
                $feature_block.find(':input').each(function () {
                    $(this).attr('disabled', true)
                });
            }
        });
    };

    shopDialogProductsCategory.prototype.renderFeature = function (item_id) {
        var that = this,
            $wrapper = that.$wrapper,
            checked = true,
            offset_count = $wrapper.find('.js-show-more').attr('data-offset'),
            data = "feature_id=" + item_id,
            type = "GET";

        if (!item_id) {
            checked = false;
            data = {
                ignore_id: that.ignore_id,
                category: that.category_id,
                category_type: that.category_type,
                offset: offset_count
            };
            type = "POST";
        }

        $.ajax({
            type: type,
            url: "?module=categoryGetFeatures",
            data: data,
            dataType: "json",
            success: function (r) {
                // remove loading
                $wrapper.find('.js-show-more').find('i.loading').remove();
                if (r.status === "ok") {
                    var $before = $wrapper.find(".js-condition-list .js-feature-insertion-block"),
                        feature_count = $wrapper.find('.js-show-more').attr('data-count'),
                        count = 0;

                    for (var id in r.data.features) {
                        if (r.data.features.hasOwnProperty(id)) {
                            var feature = r.data.features[id];
                            that.initShowFeatures(feature, $before, 'template_feature_field', checked, false);
                            ++count;
                        }
                    }
                    // Update feature_count and show-more text
                    var count_item = feature_count - count;

                    if (count_item > 0) {
                        var show_more_text = '<b><i>' + (count_item > 20 ? $_("Show %d more").replace('%d', 20) + ' ' + $_("From").toLowerCase() + ' ' + count_item : $_("Show %d more").replace('%d', count_item)) + '</i></b>';
                        $wrapper.find('.js-show-more').html(show_more_text).attr('data-count', count_item);
                        if (!item_id) {
                            offset_count = parseInt(offset_count) + parseInt(count);
                            $wrapper.find('.js-show-more').attr('data-offset', offset_count);
                        }
                    } else {
                        $wrapper.find('.js-autocomplete-wrapper').hide();
                    }
                } else if (r.status === "fail") {
                    console.log(r.errors);
                }
            }
        });
    };

    shopDialogProductsCategory.prototype.initShowAllFeatureValue = function () {
        var that = this,
            $wrapper = that.$wrapper;

        $wrapper.on("click", ".js-show-all-feature", function () {
            var show_all = this,
                feature_id = $(this).data('feature_id');


            $(this).html('<i class="icon16 loading"></i>');

            if (typeof feature_id == "undefined") {
                $(this).parent().find('.js-feature-hidden').show();
                $(this).hide();
            } else {
                var action_url = "?module=category&action=getFeatures&feature_id=" + feature_id,
                    $feature_block = $(this).parent();

                //get all feature values only save feature
                $.get(action_url, function (r) {
                    if (r.status === "ok") {
                        if (r.data.features) {
                            var feature = r.data.features[feature_id];

                            //find the feature that are already displayed
                            $feature_block.find('.js-feature-value').each(function () {
                                var f_used_val = parseInt($(this).data('f_val'));

                                for (var key in feature.values) {
                                    if (feature.values.hasOwnProperty(key)) {
                                        if (key == f_used_val) {
                                            delete feature.values[key];
                                        }
                                    }
                                }
                            });
                            that.initShowFeatures(feature, $feature_block, 'template_feature_field_values', true, true);
                            $(show_all).html('');
                        }
                    }
                });
            }
        });
    };

    shopDialogProductsCategory.prototype.initShowMore = function () {
        var that = this,
            $wrapper = that.$wrapper,
            count_item = $wrapper.find('.js-show-more').attr('data-count');

        // add the used features to ignore & update Show more count
        if (!that.ignore_id.length) {
            that.$wrapper.find('.js-condition-feature').each(function () {
                if ($.isNumeric($(this).data('feature_id'))) {
                    that.ignore_id.push($(this).data('feature_id'));
                }
            });
        }

        count_item = count_item - that.ignore_id.length;

        if (count_item <= 0) {
            $wrapper.find('.js-autocomplete-wrapper').hide();
        } else {
            var show_more_text = '<b><i>' + (count_item > 20 ? $_("Show %d more").replace('%d', 20) + ' ' + $_("From").toLowerCase() + ' ' + count_item : $_("Show %d more").replace('%d', count_item)) + '</i></b>';
            $wrapper.find('.js-show-more').html(show_more_text).attr('data-count', count_item);
        }


        $wrapper.find('.js-show-more').click(function () {
            $(this).html('<i class="icon16 loading"></i>');
            that.renderFeature();
        });
    };

    shopDialogProductsCategory.prototype.disableManualSorting = function () {
        var that = this,
            $wrapper = that.$wrapper;

        // disable manual sort is show subcategory products
        $wrapper.find('.js-subcategory-toggle').change(function () {
            var self = this;
            if (self.checked) {
                $wrapper.find('.js-sort-type option[value=""]').attr('disabled', true).next().attr('selected', 'selected');
            } else {
                $wrapper.find('.js-sort-type option[value=""]').attr('disabled', false);
            }
        });
    };

    shopDialogProductsCategory.prototype.initDynamicSettingsEvent = function () {
        var that = this,
            $wrapper = that.$wrapper;

        $wrapper.find('input[name=type]').on('click', function () {
            if ($(this).val() == '0') {
                $wrapper.find('.js-category-dynamic-settings').hide();
                //disabled all input elements in hide block
                $wrapper.find('.js-category-dynamic-settings').find(':input').each(function () {
                    $(this).attr('disabled', true)
                });
            } else {
                $wrapper.find('.js-category-dynamic-settings').show();
                //enabled all input elements in show block
                $wrapper.find('.js-category-dynamic-settings').find(':input').each(function () {
                    if (!$(this).hasClass('js-always-disabled')) {
                        $(this).attr('disabled', false);
                    }
                });
            }
        });

        //initialization rating widget
        $wrapper.find('.js-category-rate').rateWidget({
            withClearAction: false,
            onUpdate: function (rate) {
                $wrapper.find('.js-c-category-rate-value').val(rate);
            }
        });

        // check corresponding checkbox when change control
        $wrapper.find('select[name^=rating]').change(function () {
            $wrapper.find('.js-condition-rate').attr('checked', true);
        });
        $wrapper.find('.js-category-rate').click(function () {
            $wrapper.find('.js-condition-rate').attr('checked', true);
        });
        $wrapper.find('select[name^=count]').change(function () {
            $wrapper.find('.js-condition-count').attr('checked', true);
        });
        $wrapper.find('.js-category-count-value').click(function () {
            $wrapper.find('.js-condition-count').attr('checked', true);
        });
        $wrapper.on('change', 'input[name^=tag]', function () {
            $wrapper.find('.js-condition-tag').attr('checked', true);
        });
        $wrapper.on('keyup', 'input[name^=price]', function () {
            $wrapper.find('.js-condition-price-interval').attr('checked', true);
        });
        $wrapper.on('change', '.js-feature-value', function () {
            if (this.checked) {
                $(this).closest('tr').find('.js-condition-feature').attr('checked', true);
            }

            // Disabled/Enabled filtering parameters case( that more 2 options are selected)
            var feature_id = $(this).closest('tr').find('.js-condition-feature').attr('data-feature_id'),
                count = parseInt($(this).closest('.js-condition-feature-block').find('input:checkbox:checked').length),
                filter_element = $wrapper.find('.js-category-filter input[data-filter-id="' + feature_id + '"]');

            if (filter_element.length) {
                if (count >= 2) {
                    if (filter_element.is(':disabled')) {
                        filter_element.attr('disabled', false);
                    }
                } else {
                    filter_element.attr('disabled', true);
                }
            }
        });
    };

    shopDialogProductsCategory.prototype.initFrontendUrlEdit = function () {
        var that = this,
            frontend_url = $('#s-settings-frontend-url');

        frontend_url.inlineEditable({
            editLink: '.js-settings-frontend-url-edit-link',
            editOnItself: false,
            minSize: {
                width: 100
            },
            makeReadableBy: [],
            beforeMakeEditable: function (input) {
                var self = $(this);
                var parent_div = self.parents('div:first');
                var slash = parent_div.find('span.slash');
                $(input).after(slash);

                parent_div.find('span.s-frontend-base-url').html(parent_div.find('a.s-frontend-base-url').hide().contents()).show();
            },
            beforeBackReadable: function (input, data) {
                var self = $(this);
                var parent_div = self.parents('div:first');
                var slash = parent_div.find('span.slash');
                self.after(slash);

                parent_div.find('a.s-frontend-base-url').html(parent_div.find('span.s-frontend-base-url').hide().contents()).show();
            }
        });

        //CREATE CATEGORY EDIT

        var product_list_name = that.$wrapper.find(".js-product-list-name");
        var product_list_url = that.$wrapper.find(".js-product-list-url");

        var state = {name: '', url: '', timer_id: null};
        product_list_name.unbind('.create_product_list').bind('change.create_product_list, keyup.create_product_list', function () {
            if (state.time_id) {
                clearTimeout(state.time_id);
            }

            if (product_list_url.val() !== state.url) {
                product_list_name.unbind('.create_product_list');
                return;
            }

            var name = product_list_name.val();
            if (name !== state.name) {
                state.time_id = setTimeout(function () {
                    $.getJSON('?action=transliterate', {str: name}, function (r) {
                        if (r.status === 'ok') {
                            product_list_url.val(r.data);
                            state = {name: name, url: r.data, time_id: null}
                        } else if (console) {
                            if (r.errors) {
                                console.error(r.errors);
                            } else if (r) {
                                console.error(r);
                            }
                        }
                    });
                }, 300);
            }
        });

    };

    shopDialogProductsCategory.prototype.initMetaTagEdit = function () {
        var that = this,
            $wrapper = that.$wrapper,
            account_name = that.account_name;

        // change meta title input placeholder automaticly on changing name of category
        (function () {
            var title_input = $('input[name=meta_title]', $wrapper);
            $.shop.changeListener($('input[name=name]', $wrapper), function (name_input) {
                title_input.attr('placeholder', name_input.val());
            });
        })();

        // change meta keywords input placeholder automaticly on changing name of category
        (function () {
            var keywords_input = $('[name=meta_keywords]', $wrapper);
            $.shop.changeListener($('input[name=name]', $wrapper), function (name_input) {
                keywords_input.attr('placeholder', [account_name, name_input.val()].join(', '));
            });
        })();

        $.shop.makeFlexibleInput('s-meta-title');
    };

    shopDialogProductsCategory.prototype.initSocialMetaTag = function () {
        var that = this,
            $checkbox = that.$wrapper.find('.js-category-social-metatag'),
            $fieldgroup = $checkbox.closest('.field-group'),
            $disablers = $fieldgroup.find('.editable-og-disabler');

        $checkbox.closest('.field').appendTo($fieldgroup.closest('form').find('[name="meta_title"]').closest('.field-group'));
        $checkbox.prop('checked') || $fieldgroup.show();

        $checkbox.change(function () {
            if ($checkbox.prop('checked')) {
                $fieldgroup.slideUp(200);
                $disablers.prop('disabled', false);
            } else {
                $fieldgroup.slideDown(200);
                $disablers.prop('disabled', true);
            }
        });
    };

    shopDialogProductsCategory.prototype.initFilter = function () {
        var that = this,
            $wrapper = that.$wrapper,
            $category_filter = $wrapper.find('.js-category-filter');

        // EVENTS

        $wrapper.on("click", ".js-category-allow-filter", function () {
            if (this.checked) {
                //enabled all input elements in filter category
                $category_filter.find(':input').each(function () {
                    if (!$(this).data('disabled')) {
                        $(this).attr('disabled', false)
                    }
                });
                $category_filter.show();
            } else {
                //disabled all input elements in filter category
                $category_filter.find(':input').each(function () {
                    $(this).attr('disabled', true)
                });
                $category_filter.hide();
            }
        });

        $category_filter.sortable({
            distance: 5,
            opacity: 0.75,
            items: 'li:not(.unsortable)',
            handle: '.sort',
            cursor: 'move',
            tolerance: 'pointer'
        });

        // enabled/disabled filter item
        $wrapper.on("change", ".js-filter-checkbox", function () {
            showSortHandles();
        });

        // INIT
        showSortHandles();

        function showSortHandles() {
            var $active_filters = $wrapper.find(".js-filter-item .js-filter-checkbox:checked"),
                force_hide = !($active_filters.length > 1);

            $wrapper.find('.js-filter-item').each(function () {
                var $filter = $(this);
                sortToggle($filter, force_hide);
            });

            $category_filter.sortable("refresh");

            //

            function sortToggle($filter, force_hide) {
                // DOM
                var $field = $filter.find(".js-filter-checkbox"),
                    $icon = $filter.find('i.sort');

                // CONST
                var sort_class = "unsortable";
                var is_checked = (!force_hide && $field.is(":checked"));

                if (is_checked) {
                    if ($filter.hasClass(sort_class)) {
                        $filter.removeClass(sort_class);
                        $icon.show();
                    }
                } else {
                    if (!$filter.hasClass(sort_class)) {
                        $filter.addClass(sort_class);
                        $icon.hide();
                    }
                }
            }
        }
    };

    shopDialogProductsCategory.prototype.initCategoryVisibility = function () {
        var that = this,
            block = that.$wrapper.find('.js-product-category-visibility-block');

        $('input[name=storefront]', block).change(function () {
            if (this.value == 'route') {
                $('input[name="routes[]"]', block).attr('disabled', false);
            } else {
                $('input[name="routes[]"]', block).attr('disabled', true);
            }
        });
    };

    shopDialogProductsCategory.staticDialog = function (category_id, parent_id, status) {
        var showDialog = function () {

            $('#s-product-category-dialog').waDialog({
                esc: false,
                disableButtonsOnSubmit: false,
                onLoad: function () {
                    if ($('#s-category-description-content').length) {
                        $.product_sidebar.initCategoryDescriptionWysiwyg($(this));
                    }
                    setTimeout(function () {
                        $('.js-product-list-name').focus();
                    }, 150);
                },
                onSubmit: function (d) {
                    var form = $(this);

                    var errors = validateFeatureValues();
                    if (errors) {
                        return false;
                    }

                    function validateFeatureValues() {
                        var errors = false,
                            $fields = form.find('.js-condition-feature:checked, .js-condition-tag:checked');

                        $fields.each(function () {
                            var $feature_block = $(this).closest('tr').find('.js-condition-feature-block, .js-condition-tag-block'),
                                feature_checkbox = $feature_block.find('.js-feature-value:checkbox, .js-tag-value:checkbox');

                            if (feature_checkbox.length && !feature_checkbox.is(':checked')) {
                                errors = true;
                                $feature_block.find('.errormsg').html('').html($_('Nothing selected'));
                                form.find('.js-category-error').html('').html($_('Nothing selected'));
                            }

                        });
                        setTimeout(function () {
                            form.find(".errormsg").html('');
                        }, 5000);

                        return errors;
                    }

                    var success = function (r) {
                        var hash = null;

                        if (location.hash.indexOf('category_id') > 0) {
                            hash = location.hash.replace('category_id=' + category_id, 'category_id=' + r.data.id);
                            //reset sort
                            if (('sort_products' in r.data) || ('rule' in r.data)) {
                                hash = hash.replace(/&sort=[^&]*/, '&sort=');
                                hash = hash.replace(/&order=[^&]*/, '&order=');
                            }
                        } else {
                            hash = '#/products/category_id=' + r.data.id;
                        }

                        if (status === 'new') {
                            $.product_sidebar.createNewElementInList(r.data, 'category');
                        }

                        $.product_sidebar.updateItemInCategoryList(r, hash);

                        if (location.hash != hash) {
                            location.hash = hash;
                        } else {
                            $.products.dispatch();
                        }

                        d.trigger('close');
                    };

                    var error = function (r) {
                        if (r && r.errors) {
                            var errors = r.errors;
                            for (var name in errors) {
                                d.find('input[name=' + name + ']').addClass('error').parent().find('.errormsg').text(errors[name]);
                            }
                            return false;
                        }
                    };

                    if ($('#s-category-description-content').length) {
                        $('#s-category-description-content').waEditor('sync');
                    }

                    if (form.find('input:file').length) {
                        $.products._iframePost(form, success, error);
                    } else {
                        $.shop.jsonPost(form.attr('action'), form.serialize(), success, error);
                        return false;
                    }
                }
            });
        };

        var d = $('#s-product-category-dialog');
        var p;
        if (!d.length) {
            p = $('<div></div>').appendTo('body');
        } else {
            p = d.parent();
        }

        if (status === 'new') {
            p.load('?module=category&action=Create&parent_id=' + parent_id, showDialog);
        }

        if (status === 'edit') {
            p.load('?module=category&action=Edit&category_id=' + category_id, showDialog);
        }
    };

    return shopDialogProductsCategory;

})(jQuery);
