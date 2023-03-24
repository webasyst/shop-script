ShopSettingsOrderEditor = ( function($) {
    return class {
        constructor($wrapper, options) {
            // DOM
            this.$wrapper = $wrapper;

            this.$general_block = this.$wrapper.find('.js-block-wrapper[data-block="general"]');
            this.$person_block = this.$wrapper.find('.js-block-wrapper[data-block="person"]');
            this.$address_block = this.$wrapper.find('.js-block-wrapper[data-block="address"]');

            this.$button = this.$wrapper.find('.js-submit-button');
            this.$loading = this.$button.find('.js-loading');

            // VARS
            this.options = options;
            this.name_format_full_fields = ['firstname', 'middlename', 'lastname'];
            this.name_format_one_fields = ['name'];
            this.is_locked = false;

            this.initClass();
        };

        initClass() {
            this.initCustomConfigToggle();
            this.initFixDeliveryArea();
            this.initBigTable();
            this.ensureNameFieldsConsistency();
            this.initSortTables();
            this.initSubmit();
        }

        initCustomConfigToggle() {
            const that = this;
            const $toggle = that.$wrapper.find('.js-custom-config-toggle');
            const $big_table = that.$wrapper.find('.js-big-table');

            $toggle.waSwitch({
                change(checked, switcher) {
                    if (checked) {
                        switcher.$wrapper.next('label').text($.wa.locale['use']).removeClass('gray');
                        $big_table.show();
                    } else {
                        switcher.$wrapper.next('label').text($.wa.locale['dont_use']).addClass('gray');
                        $big_table.hide();
                    }
                }
            });
        }

        initFixDeliveryArea() {
            const $fix_delivery_field = this.$wrapper.find('.js-fixed-delivery-area');
            const $fix_delivery_country = $fix_delivery_field.find('.js-fix-delivery-country');
            const $fix_delivery_region = $fix_delivery_field.find('.js-fix-delivery-region');
            const $fix_delivery_city = $fix_delivery_field.find('.js-fix-delivery-city');

            syncRegionsWithCountry($fix_delivery_country, $fix_delivery_region, $fix_delivery_city);
            $fix_delivery_field.on('change', '.js-fix-delivery-country, .js-fix-delivery-region', function () {
                syncRegionsWithCountry($fix_delivery_country, $fix_delivery_region, $fix_delivery_city);
            });

            function syncRegionsWithCountry ($country_select, $region_select, $city_input) {
                const counry_code = $country_select.val();

                // Hide all
                $region_select.find('option[data-country]').hide();
                // Show regions by selected country
                $region_select.find('option[data-country="'+ counry_code +'"]').show();

                // If the region does not match the country - reset it
                const selected_region_country = $region_select.find('option:selected').data('country');
                if (counry_code !== selected_region_country) {
                    $region_select.val('');
                    $city_input.val('');
                }

                $region_select.prop('disabled', !(counry_code));
                $city_input.prop('disabled', !($region_select.val()));
            }
        }

        initBigTable() {
            const $big_table = this.$wrapper.find('.js-big-table');

            $big_table.on('click', '.js-settings-link', function (event) {
                event.preventDefault();

                const $block_wrapper = $(this).closest('tr.js-block-wrapper');
                const $content_wrapper = $block_wrapper.find('.js-block-content');

                if ($content_wrapper.is(':visible')) {
                    $content_wrapper.hide();
                } else {
                    $content_wrapper.show();
                }
            });
        };

        ensureNameFieldsConsistency() {
            const that = this;

            that.$person_block.on('change', '.js-name-format-variant', function () {
                const $current_radio = that.$person_block.find('.js-name-format-variant:checked');
                const current_value = $current_radio.val();

                if (current_value === 'full') {
                    $.each(that.name_format_full_fields, function (i, field_id) {
                        personFieldShow(field_id);
                    });
                    $.each(that.name_format_one_fields, function (i, field_id) {
                        personFieldHide(field_id);
                    });
                } else {
                    $.each(that.name_format_one_fields, function (i, field_id) {
                        personFieldShow(field_id);
                    });
                    $.each(that.name_format_full_fields, function (i, field_id) {
                        personFieldHide(field_id);
                    });
                }
            });

            that.$general_block.find('.js-name-format-variant').change();

            function personFieldShow(field_id) {
                const $field = that.$person_block.find('.js-field[data-id="'+field_id+'"]');

                $field.show();
                $field.find('.js-used-checkbox').prop('checked', true);
            }

            function personFieldHide(field_id) {
                const $field = that.$person_block.find('.js-field[data-id="'+field_id+'"]');

                $field.hide();
                $field.find('.js-used-checkbox').prop('checked', false);
                $field.find('.js-required-checkbox').prop('checked', false);
            }
        }

        initSortTables() {
            const $sort_tables = this.$wrapper.find('.js-sort-table');

            $sort_tables.each(function () {
                const $sort = $(this).find('tbody');

                $sort.sortable({
                    axis: 'y',
                    distance: 5,
                    helper: 'clone',
                    items: 'tr',
                    opacity: 0.75,
                    handle: '.js-sort',
                    tolerance: 'pointer',
                    containment: $sort
                });
            });
        };

        initSubmit() {
            const that = this;

            that.$wrapper.on('submit', function(event) {
                event.preventDefault();

                if (that.is_locked) {
                    return;
                }

                that.is_locked = true;
                that.$button.prop('disabled', true);
                that.$loading.removeClass('yes').removeClass('no').addClass('loading').show();
                that.$wrapper.find('.js-submit-error').remove();
                that.$wrapper.find('.state-error').removeClass('state-error');

                const href = that.$wrapper.attr('action');
                const data = that.$wrapper.serialize();

                $.post(href, data, function (res) {
                    if (res.status === 'ok' && res.data === true) {
                        that.buttonSubmitToggle(false);
                        that.$loading.removeClass('loading').addClass('yes');
                    } else {
                        that.$loading.removeClass('loading').addClass('no');
                    }

                    setTimeout(function () {
                        that.$loading.hide();
                    });

                    that.is_locked = false;
                    that.$button.prop('disabled', false);
                });
            });

            that.$wrapper.on('change', function () {
                that.buttonSubmitToggle(true);
            });
            that.$wrapper.on('input', ':input', function () {
                that.buttonSubmitToggle(true);
            });
        }

        buttonSubmitToggle(show) {
            const default_class = "green";
            const active_class = "yellow";

            if (show) {
                this.$button.removeClass(default_class).addClass(active_class);
            } else {
                this.$button.removeClass(active_class).addClass(default_class);
            }
        }
    }
})(jQuery);
