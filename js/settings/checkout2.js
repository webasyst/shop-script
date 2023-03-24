var ShopSettingsCheckout2 = ( function($) {

    ShopSettingsCheckout2 = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find('form');
        that.$footer_actions = that.$form.find('.js-footer-actions');
        that.$button = that.$footer_actions.find('.js-submit-button');
        that.$loading = that.$footer_actions.find('.js-loading');
        that.$big_table = that.$form.find('.js-big-table');
        that.$design_block = that.$form.find('.js-block-wrapper[data-block="design"]');
        that.$order_block = that.$big_table.find('.js-block-wrapper[data-block="order"]');
        that.$customer_block = that.$big_table.find('.js-block-wrapper[data-block="customer"]');
        that.$shipping_block = that.$big_table.find('.js-block-wrapper[data-block="shipping"]');
        that.$payment_block = that.$big_table.find('.js-block-wrapper[data-block="payment"]');
        that.$confirmation_block = that.$big_table.find('.js-block-wrapper[data-block="confirmation"]');

        // VARS
        that.domain = options["domain"];
        that.route_id = options["route_id"];
        that.storefront_id = options["storefront_id"];
        that.shipping_location_template = options["shipping_location_template"];
        that.shipping_no_locations_template = options["shipping_no_locations_template"];
        that.schedule_extra_workday_template = options["schedule_extra_workday_template"];
        that.schedule_extra_weekend_template = options["schedule_extra_weekend_template"];
        that.demo_terms = options["demo_terms"];
        that.date_format = options['date_format'];
        that.locale = options["locale"];

        // DYNAMIC VARS
        that.extra_dates = [];
        that.is_locked = false;

        // INIT
        that.initClass();
    };

    ShopSettingsCheckout2.prototype.initClass = function() {
        var that = this,
            $shop_settings_sidebar = $('#s-settings-menu'),
            $checkout_sidebar = $('.s-settings-checkout-sidebar'),
            $storefronts = $checkout_sidebar.find('.js-storefronts-list');

        $shop_settings_sidebar.find('a[href="?action=settings#/checkout/"]').parent().addClass('selected');
        $storefronts.find('li[data-domain-id="'+ that.domain +'"][data-route-id="'+ that.route_id +'"]').addClass('selected');

        //
        that.initSortTables();
        //
        that.initDesignBlock();
        //
        that.initBigTable();
        //
        that.initOrderBlock();
        //
        that.initScheduleBlock();
        //
        that.initCustomerBlock();
        //
        that.initShippingBlock();
        //
        that.initConfirmationBlock();
        //
        that.initSubmit();
        //
        $(window).trigger('scroll');
    };

    ShopSettingsCheckout2.prototype.initSortTables = function() {
        var that = this,
            $sort_tables = that.$wrapper.find('.js-sort-table tbody');

        $sort_tables.sortable({
                animation: 150
            });
    };

    ShopSettingsCheckout2.prototype.initDesignBlock = function() {
        const that = this;
        const $block = that.$design_block;
        const $design_toggle = $block.find('.js-design-toggle');
        const $design_values = $block.find('.js-design-values');

        $design_toggle.waSwitch({
            change(active) {
                if (active) {
                    $design_values.show();
                } else {
                    $design_values.hide();
                }

                $(window).trigger('scroll');
            }
        });

        // Logo upload
        const $logo_wrapper = $block.find('.js-logo-wrapper');
        const $delete_logo = $logo_wrapper.find('.js-delete-design-logo');
        const $loading = $logo_wrapper.find('.loading');
        const $input_file = $logo_wrapper.find('.js-design-logo');
        const $logo_field = $logo_wrapper.find('.js-design-logo-field');
        const $logo_preview_wrapper = $logo_wrapper.find('.js-design-logo-preview-wrapper');
        const $logo_preview = $logo_preview_wrapper.find('.js-design-logo-preview');

        $input_file.on('change', function (e) {
            e.preventDefault();

            if (!$(this).val()) {
                return;
            }

            const href = "?module=settingsCheckout2UploadLogo";
            const data = new FormData();

            data.append("storefront_id", that.storefront_id);
            data.append("logo", $(this)[0].files[0]);

            $loading.show();
            $input_file.removeClass('state-error wa-animation-swing');
            that.$button.prop('disabled', true);

            const waloadingContainer = $.settings.$container;
            waloadingContainer.trigger("wa_before_load");

            $.ajax({
                url: href,
                type: 'POST',
                data: data,
                cache: false,
                contentType: false,
                processData: false,
                xhr: function() {
                    var xhr = new window.XMLHttpRequest();

                    xhr.addEventListener("progress", function(event) {
                        waloadingContainer.trigger("wa_loading", event);
                    }, false);

                    xhr.addEventListener("abort", function(event) {
                        waloadingContainer.trigger("wa_abort");
                    }, false);

                    return xhr;
                }
            }).done(function(res) {
                if (res.status !== 'ok') {
                    $logo_field.val('');
                    $logo_preview_wrapper.hide();
                    $logo_preview.removeAttr('src');
                    $loading.removeClass('loading');
                    $input_file.addClass('state-error shake animated');
                    setTimeout(function () {
                        $loading.hide();
                    });

                    return;
                }

                $logo_field.val(res.data.name);
                $logo_preview.attr('src', res.data.url);
                $logo_preview_wrapper.show();
                $loading.hide();
                waloadingContainer.trigger("wa_loaded");
            });

            that.$button.prop('disabled', false);
            $(this).val('');
        });

        // Delete logo
        $delete_logo.on('click', function (event) {
            event.preventDefault();

            $input_file.val('');
            $logo_field.val('');
            $logo_preview_wrapper.hide();
            $logo_preview.removeAttr('src');
            that.buttonViewChange(true);
        });

        // Colorpickers
        $block.find('.js-color-input').each(function() {
            const $input = $(this);
            const $replacer = $('<span class="s-color-replacer"><i class="icon rounded bordered-top bordered-right bordered-bottom bordered-left s-color" style="background: #'+$input.val().substr(1)+'"></i></span>').insertAfter($input);
            const $picker = $('<div class="s-color-picker" style="display:none;"></div>').insertAfter($replacer);
            const farbtastic = $.farbtastic($picker, function(color) {
                $replacer.find('i').css('background', color);
                $input.val(color);
            });

            farbtastic.setColor($input.val());

            $replacer.click(function() {
                $picker.slideToggle(200);
                return false;
            });

            $picker.on('click', function () {
                that.buttonViewChange(true);
            });

            let timer_id;
            $input.unbind('keydown').bind('keydown', function() {
                if (timer_id) {
                    clearTimeout(timer_id);
                }
                timer_id = setTimeout(function() {
                    farbtastic.setColor($input.val());
                }, 250);
            });
        });
    };

    ShopSettingsCheckout2.prototype.initBigTable = function () {
        var that = this,
            $big_table = that.$wrapper.find('.js-big-table'),
            $block_toggles = $big_table.find('.js-block-toggle-wrapper');

        $big_table.on('click', '.js-settings-link', function () {
            var $block_wrapper = $(this).parents('.js-block-wrapper'),
                $content_wrapper = $block_wrapper.find('.js-block-content');

            if ($content_wrapper.is(':visible')) {
                $content_wrapper.hide();
            } else {
                $content_wrapper.show();
            }
            $(window).trigger('scroll');
        });

        // Init block toggles
        $.each($block_toggles, function () {
            var $toggle_wrapper = $(this),
                $toggle = $toggle_wrapper.find('.js-toggle'),
                $block_wrapper = $toggle_wrapper.parents('.js-block-wrapper'),
                $block_content = $block_wrapper.find('.js-block-content'),
                $settings_link = $block_wrapper.find('.js-settings-link');

            $toggle.waSwitch({
                ready(wa_switch) {
                    let $label = wa_switch.$wrapper.siblings('label');
                    wa_switch.$label = $label;
                    wa_switch.active_text = $label.data('active-text');
                    wa_switch.inactive_text = $label.data('inactive-text');
                },
                change(active, wa_switch) {
                    wa_switch.$label.text(active ? wa_switch.active_text : wa_switch.inactive_text);
                    $block_content.toggle(active);
                    $settings_link.toggle(active);
                    $(window).trigger('scroll');
                }
            });
        });
    };

    ShopSettingsCheckout2.prototype.initOrderBlock = function() {
        var that = this,
            $block = that.$order_block;

    };

    ShopSettingsCheckout2.prototype.initScheduleBlock = function () {
        var that = this,
            $schedule_block = that.$order_block.find('.js-schedule-block'),
            $custom_schedule = $schedule_block.find('.js-custom-schedule');

        // Change mode
        $schedule_block.on('change', '.js-schedule-mode-variant', function () {
            var $current_radio = $schedule_block.find('.js-schedule-mode-variant:checked'),
                current_value = $current_radio.val();

            if (current_value === 'custom') {
                $custom_schedule.show();
            } else {
                $custom_schedule.hide();
            }
        });

        // Week
        var $week_wrapper = $custom_schedule.find('.js-week-wrapper');
        $week_wrapper.on('change', '.js-work', function () {
            var $work = $(this),
                $day_wrapper = $work.parents('.js-day-wrapper');

            if (this.checked) {
                $day_wrapper.addClass('worked');
                $day_wrapper.find('.js-time').each(function () {
                    $(this).prop('disabled', false).attr('placeholder', $(this).data('placeholder'));
                });
            } else {
                $day_wrapper.removeClass('worked').find('.js-time').val('').prop('disabled', true).removeAttr('placeholder');
            }
        });

        //
        initExtra('workday');
        //
        initExtra('weekend');

        function initExtra(extra_type) {
            var wrapper_class = (extra_type === 'workday') ? '.js-extra-workdays-wrapper' : '.js-extra-weekends-wrapper',
                $extra_wrapper = $custom_schedule.find(wrapper_class),
                $days_list = $extra_wrapper.find('.js-days-list'),
                $add_day = $extra_wrapper.find('.js-add-day');

            initDatepickers();

            // Add
            $add_day.on('click', function () {
                var template = (extra_type === 'workday') ? that.schedule_extra_workday_template : that.schedule_extra_weekend_template,
                    $template = $(template).clone();

                $extra_wrapper.find('thead').show();
                $days_list.append($template);
                initDatepickers();
            });

            // Remove
            $extra_wrapper.on('click', '.js-remove', function () {
                $(this).parents('tr.js-day-wrapper').remove();
                if (!$days_list.find('tr').length) {
                    $extra_wrapper.find('thead').hide();
                }
                initDatepickers();
            });

            $schedule_block.on('change', '.js-datepicker', function () {
                initDatepickers();
            });

            function initDatepickers() {
                parseDates();
                $custom_schedule.find('.js-datepicker').each(function () {
                    var $input = $(this);
                    $input.datepicker({
                        dateFormat: "dd.mm.yy",
                        beforeShowDay: function(date){
                            var string = $.datepicker.formatDate(that.date_format, date);
                            return [ that.extra_dates.indexOf(string) == -1 ]
                        },
                        create: parseDates()
                    });
                });
                $('#ui-datepicker-div').hide();
            }

            function parseDates() {
                that.extra_dates = [];
                $custom_schedule.find('.js-datepicker').each(function () {
                    that.extra_dates.push($(this).val());
                });
            }
        }

    };

    ShopSettingsCheckout2.prototype.initCustomerBlock = function() {
        var that = this,
            $block = that.$customer_block,
            $type_wrapper = $block.find('.js-customer-type-wrapper'),
            $person_company_settings = $type_wrapper.find('.js-person-company-settings').detach(),
            $company_settings = $type_wrapper.find('.js-company-settings').detach(),
            $person_fields_editor = $type_wrapper.find('.js-fields-editor[data-type="person"]').detach(),
            $company_fields_editor = $type_wrapper.find('.js-fields-editor[data-type="company"]').detach();

        initNamesFieldChecking($block, 'person', ['firstname', 'middlename', 'lastname']);

        ensureFieldEditorsConsistency();
        $type_wrapper.on('change', '.js-customer-type-variant', ensureFieldEditorsConsistency);

        function ensureFieldEditorsConsistency() {
            var $current_radio = $type_wrapper.find('.js-customer-type-variant:checked'),
                current_value = $current_radio.val();

            $type_wrapper.find('.js-variant-params').html('');

            if (current_value == 'person') {
                $type_wrapper.find('.js-variant-params').html($person_fields_editor);
            }

            if (current_value == 'company') {
                $type_wrapper.find('.js-variant-params')
                    .append($company_settings)
                    .append($company_fields_editor);
            }

            if (current_value == 'person_and_company') {
                $type_wrapper.find('.js-variant-params')
                    .append($person_company_settings)
                    .append($company_settings)
                    .append($person_fields_editor)
                    .append($company_fields_editor);

                $type_wrapper.find('.js-contact-type').show();
            } else {
                $type_wrapper.find('.js-contact-type').hide();
            }

            // Reinit sortable
            that.initSortTables();

            //
            $block.trigger('on_render_fields');
        }

        /**
         * Helper that helps implement that logic:
         *   - If we check NAME-field checkbox, alternative NAME-field(s) then will be disabled and un-checked
         *   - If we check one of alternative NAME-field(s), NAME-field then will be disabled and un-checked
         * @param {jQuery} $block - customer block where are all editors
         * @param {String} type - type of editor
         * @param {String|String[]} alt_names - alternative name field(s)
         */
        function initNamesFieldChecking($block, type, alt_names) {

            var buidQuerySelector = function (field_id) {
                var field_ids = $.isArray(field_id) ? field_id : [field_id];
                return $.map(field_ids, function (id) {
                    return '.js-field[data-id="' + id + '"] :checkbox[data-name="used"]';
                }).join(',');
            };

            // Query selector for checkbox of name field
            var name_qs = buidQuerySelector('name');

            // Query selector for checkboxes of alternative name fields
            var alt_names_qs = buidQuerySelector(alt_names);

            //
            var editor_qs = '.js-fields-editor[data-type="' + type + '"]';

            var isChecked = function(qs) {
                var checked = false;
                $block.find(editor_qs).find(qs).each(function () {
                    checked = checked || $(this).is(':checked');
                });
                return checked;
            };

            var onClick = function() {
                var field_id = $(this).closest('.js-field').data('id'),
                    disabled = null,
                    other_qs = null,
                    $other = null;

                if (field_id === 'name') {
                    other_qs = alt_names_qs;
                    disabled = isChecked(name_qs);
                } else {
                    other_qs = name_qs;
                    disabled = isChecked(alt_names_qs);
                }

                $other = $block.find(editor_qs).find(other_qs);

                // if checked name - disable and un-check alt-name(s)
                // if checked alt-name(s) - disable and un-check name

                $other.attr('disabled', disabled);
                if (disabled) {
                    $other.attr('checked', false);
                }

            };

            $block.on('click', editor_qs + ' ' + name_qs + ',' + alt_names_qs, onClick);

            // when we append fields into block, handle init states of checking
            $block.on('on_render_fields', function () {

                if (!$block.find(editor_qs).length) {
                    return;
                }

                var name_is_checked = isChecked(name_qs),
                    alt_name_is_checked = isChecked(alt_names_qs);

                if (name_is_checked && !alt_name_is_checked) {
                    onClick.call($block.find(editor_qs).find(name_qs).get(0));
                } else if (alt_name_is_checked && !name_is_checked) {
                    onClick.call($block.find(editor_qs).find(alt_name_is_checked).get(0));
                }
            });
        }

        // Customer service agreement
        var $service_agreement_wrapper = $block.find('.js-customer-service-agreement-wrapper'),
            $fake_checkbox = $service_agreement_wrapper.find('.js-fake-checkbox').detach(),
            $service_agreement_editor = $service_agreement_wrapper.find('.js-customer-service-agreement-edtior').detach();

        // Text generator
        $service_agreement_wrapper.on('click', '.js-generate-example', function () {
            var $current_variant = $service_agreement_wrapper.find('.js-customer-service-agreement-variant:checked').parents('.js-variant'),
                text = $current_variant.data('default-text');

            $service_agreement_wrapper.find('.js-customer-service-agreement-hint').val(text);
        });

        ensureServiceAgreementConsistency();

        $service_agreement_wrapper.on('change', '.js-customer-service-agreement-variant', ensureServiceAgreementConsistency);

        function ensureServiceAgreementConsistency() {
            var $current_radio = $service_agreement_wrapper.find('.js-customer-service-agreement-variant:checked'),
                $current_variant = $current_radio.parents('.js-customer-service-agreement-wrapper'),
                current_value = $current_radio.val();

            $service_agreement_wrapper.find('.js-variant-params').removeClass('block-padded').html('');

            if (current_value == 'notice') {
                $current_variant.find('.js-variant-params')
                    .addClass('block-padded')
                    .append($service_agreement_editor);
            }

            if (current_value == 'checkbox') {
                $current_variant.find('.js-variant-params')
                    .addClass('block-padded')
                    .append($fake_checkbox)
                    .append($service_agreement_editor);
            }
        }
    };

    ShopSettingsCheckout2.prototype.initShippingBlock = function () {
        var that = this,
            $block = that.$shipping_block,
            $service_agreement_wrapper = $block.find('.js-shipping-service-agreement-wrapper'),
            $service_agreement = $service_agreement_wrapper.find('.js-shipping-service-agreement'),
            $service_agreement_hint = $service_agreement_wrapper.find('.js-shipping-service-agreement-hint');

        //
        initLocationsType();
        //
        initMapVariants();

        // Init service agreement
        $service_agreement.on('change', function () {
            if (this.checked) {
                $service_agreement_hint.show();
            } else {
                $service_agreement_hint.hide();
            }
            $(window).trigger('scroll');
        });

        // Zip
        var $ask_zip = $block.find('.js-shipping-ask-zip'),
            $zip_field = $block.find('.js-field[data-id="zip"]'),
            $zip_used = $zip_field.find('input[data-name="used"]'),
            $zip_required = $zip_field.find('input[data-name="required"]'),
            $zip_width = $zip_field.find('.js-field-width');

        ensureFieldZipConsistency();
        $ask_zip.on('change', function () {
            ensureFieldZipConsistency();
        });

        // FUNCTIONS

        function ensureFieldZipConsistency() {
            if ($ask_zip.is(':checked')) {
                $zip_used.prop('disabled', false).prop('checked', true).prop('disabled', true);
                $zip_required.prop('disabled', false).prop('checked', true).prop('disabled', true);
                $zip_width.hide();
            } else {
                $zip_used.prop('disabled', false);
                $zip_required.prop('disabled', false);
                $zip_width.show();
            }
            $(window).trigger('scroll');
        }

        function initLocationsType() {
            var $shipping_mode_wrapper = $block.find('.js-shipping-mode-wrapper'),
                $locations_table = $shipping_mode_wrapper.find('.js-locations-table');

            // Change mode
            $shipping_mode_wrapper.on('change', '.js-shipping-mode-variant', function () {
                var $current_radio = $shipping_mode_wrapper.find('.js-shipping-mode-variant:checked'),
                    $current_variant = $current_radio.parents('.js-variant');

                $shipping_mode_wrapper.find('.js-variant-params').hide(); // Close all
                $current_variant.next('.js-variant-params').show();    // Show current

                var value = $(this).val();
                if (value === "minimum") {
                    var locations_count = $locations_table.find('.js-location').length;
                    if (!locations_count) {
                        $current_variant.find(".js-add-location").trigger("click");
                    }
                }
            });

            // Edit fixed location item
            $locations_table.on('click', '.js-edit-location', function () {
                var $location = $(this).parents('tr.js-location');

                $(this).remove();
                $location.find('.js-preview').remove();
                $location.find('.js-editor').show();
            });

            // Delete fixed location item
            $locations_table.on('click', '.js-delete-location', function () {
                var locations_count = $locations_table.find('.js-location').length;
                if (locations_count <= 1) { return false; }

                $(this).parents('tr.js-location').remove();

                if (!locations_count) {
                    var $no_locations = $(that.shipping_no_locations_template).clone();
                    $locations_table.append($no_locations);
                }

                that.buttonViewChange(true);
            });

            // Ensure regions with country consistency
            // in fix delivery
            var $fix_delivery_country = $block.find('.js-fix-delivery-country'),
                $fix_delivery_region = $block.find('.js-fix-delivery-region'),
                $fix_delivery_city = $block.find('.js-fix-delivery-city');

            that.syncRegionsWithCountry($fix_delivery_country, $fix_delivery_region, $fix_delivery_city);
            $block.on('change', '.js-fix-delivery-country, .js-fix-delivery-region', function () {
                that.syncRegionsWithCountry($fix_delivery_country, $fix_delivery_region, $fix_delivery_city);
            });

            // in locations list
            syncLocations();

            // add fixed location
            $block.on('click', '.js-add-location', function () {
                var $empty_location = $(that.shipping_location_template).clone();

                $locations_table.find('.js-no-locations').remove();
                $locations_table.append($empty_location);
                $empty_location.find('.js-edit-location').click();
                syncLocations();
            });

            function syncLocations() {
                $locations_table.find('tr.js-location').each(function () {
                    var $location = $(this),
                        $location_country = $location.find('.js-location-country'),
                        $location_region = $location.find('.js-location-region'),
                        $location_city = $location.find('.js-location-city');

                    that.syncRegionsWithCountry($location_country, $location_region, $location_city);
                    $location.on('change', '.js-location-country, .js-location-region', function () {
                        that.syncRegionsWithCountry($location_country, $location_region, $location_city);
                    });
                });
            }
        }

        function initMapVariants() {
            var $wrapper = $block.find('.js-map-mode-wrapper');

            // Change mode
            $wrapper.on("change", ".js-radio", function() {
                $wrapper.find('.js-variant-params').hide();

                var $_radio = $wrapper.find(".js-radio:checked");
                $_radio.parents(".js-variant").find('.js-variant-params').show();
            });
        }
    };

    ShopSettingsCheckout2.prototype.initConfirmationBlock = function () {
        var that = this,
            $terms_wrapper = that.$confirmation_block.find('.js-confirmation-terms-wrapper'),
            $terms_text_wrapper = $terms_wrapper.find('.js-confirmation-terms-text-wrapper'),
            $terms_checkbox = $terms_wrapper.find('.js-confirmation-terms'),
            $terms_textarea = $terms_text_wrapper.find('.js-confirmation-terms-text'),
            $terms_generete = $terms_text_wrapper.find('.js-confirmation-terms-generate');

        // Terms and terms text
        $terms_checkbox.on('change', function () {
            if ($(this).is(':checked')) {
                $terms_text_wrapper.show();
            } else {
                $terms_text_wrapper.hide();
            }
            $(window).trigger('scroll');
        });

        $terms_generete.on('click', function () {
            $terms_textarea.val(that.demo_terms);
        });

        // Order without auth
        var $order_without_auth_wrapper = that.$confirmation_block.find('.js-order-without-auth-wrapper'),
            $hint = $order_without_auth_wrapper.find('.js-auth-with-code-hint').detach();

        ensureOrderWithoutAuthConsistency();

        $order_without_auth_wrapper.on('change', '.js-order-without-auth-variant', function () {
            ensureOrderWithoutAuthConsistency();
        });

        function ensureOrderWithoutAuthConsistency() {
            var $current_radio = $order_without_auth_wrapper.find('.js-order-without-auth-variant:checked'),
                $current_variant = $current_radio.parents('.js-variant');

            // Remove params from all variants
            $order_without_auth_wrapper.find('.js-variant-params').html('');

            if ($current_radio.val() == 'confirm_contact') {
                $current_variant.find('.js-variant-params').html($hint);
            } else {
                $current_variant.append($hint);
            }
            $(window).trigger('scroll');
        }
    };

    ShopSettingsCheckout2.prototype.syncRegionsWithCountry = function ($country_select, $region_select, $city_input) {
        var counry_code = $country_select.val();

        // Hide all
        $region_select.find('option[data-country]').hide();
        // Show regions by selected country
        $region_select.find('option[data-country="'+ counry_code +'"]').show();

        // If the region does not match the country - reset it
        var selected_region_country = $region_select.find('option:selected').data('country');
        if (counry_code !== selected_region_country) {
            $region_select.val('');
            $city_input.val('');
        }

        $region_select.prop('disabled', !(counry_code));
        $city_input.prop('disabled', !($region_select.val()));
    };

    ShopSettingsCheckout2.prototype.initFixedActions = function() {
        var that = this;

        /**
         * @class FixedBlock
         * @description used for fixing form buttons
         * */
        var FixedBlock = ( function($) {

            FixedBlock = function(options) {
                var that = this;

                // DOM
                that.$window = $(window);
                that.$wrapper = options["$section"];
                that.$wrapperW = options["$wrapper"];
                that.$form = that.$wrapper.parents('form');

                // VARS
                that.type = (options["type"] || "bottom");
                that.lift = (options["lift"] || 0);

                // DYNAMIC VARS
                that.offset = {};
                that.$clone = false;
                that.is_fixed = false;

                // INIT
                that.initClass();
            };

            FixedBlock.prototype.initClass = function() {
                var that = this,
                    $window = that.$window,
                    resize_timeout = 0;

                $window.on("resize", function() {
                    clearTimeout(resize_timeout);
                    resize_timeout = setTimeout( function() {
                        that.resize();
                    }, 100);
                });

                $window.on("scroll", watcher);

                that.$wrapper.on("resize", function() {
                    that.resize();
                });

                that.$form.on("input", function () {
                    that.resize();
                });

                that.init();

                function watcher() {
                    var is_exist = $.contains($window[0].document, that.$wrapper[0]);
                    if (is_exist) {
                        that.onScroll($window.scrollTop());
                    } else {
                        $window.off("scroll", watcher);
                    }
                }

                that.$wrapper.data("block", that);
            };

            FixedBlock.prototype.init = function() {
                var that = this;

                if (!that.$clone) {
                    var $clone = $("<div />").css("margin", "0");
                    that.$wrapper.after($clone);
                    that.$clone = $clone;
                }

                that.$clone.hide();

                var offset = that.$wrapper.offset();

                that.offset = {
                    left: offset.left,
                    top: offset.top,
                    width: that.$wrapper.outerWidth(),
                    height: that.$wrapper.outerHeight()
                };
            };

            FixedBlock.prototype.resize = function() {
                var that = this;

                switch (that.type) {
                    case "top":
                        that.fix2top(false);
                        break;
                    case "bottom":
                        that.fix2bottom(false);
                        break;
                }

                var offset = that.$wrapper.offset();
                that.offset = {
                    left: offset.left,
                    top: offset.top,
                    width: that.$wrapper.outerWidth(),
                    height: that.$wrapper.outerHeight()
                };

                that.$window.trigger("scroll");
            };

            /**
             * @param {Number} scroll_top
             * */
            FixedBlock.prototype.onScroll = function(scroll_top) {
                var that = this,
                    window_w = that.$window.width(),
                    window_h = that.$window.height();

                // update top for dynamic content
                that.offset.top = (that.$clone && that.$clone.is(":visible") ? that.$clone.offset().top : that.$wrapper.offset().top);

                switch (that.type) {
                    case "top":
                        var use_top_fix = (that.offset.top - that.lift < scroll_top);

                        that.fix2top(use_top_fix);
                        break;
                    case "bottom":
                        var use_bottom_fix = (that.offset.top && scroll_top + window_h < that.offset.top + that.offset.height);
                        that.fix2bottom(use_bottom_fix);
                        break;
                }

            };

            /**
             * @param {Boolean|Object} set
             * */
            FixedBlock.prototype.fix2top = function(set) {
                var that = this,
                    fixed_class = "is-top-fixed";

                if (set) {
                    that.$wrapper
                        .css({
                            position: "fixed",
                            top: that.lift,
                            left: that.offset.left
                        })
                        .addClass(fixed_class);

                    that.$clone.css({
                        height: that.offset.height
                    }).show();

                } else {
                    that.$wrapper.removeClass(fixed_class).removeAttr("style");
                    that.$clone.removeAttr("style").hide();
                }

                that.is_fixed = !!set;
            };

            /**
             * @param {Boolean|Object} set
             * */
            FixedBlock.prototype.fix2bottom = function(set) {
                var that = this,
                    fixed_class = "is-bottom-fixed";

                if (set) {
                    that.$wrapper
                        .css({
                            position: "fixed",
                            bottom: 0,
                            left: that.offset.left,
                            width: that.offset.width
                        })
                        .addClass(fixed_class);

                    that.$clone.css({
                        height: that.offset.height
                    }).show();

                } else {
                    that.$wrapper.removeClass(fixed_class).removeAttr("style");
                    that.$clone.removeAttr("style").hide();
                }

                that.is_fixed = !!set;
            };

            return FixedBlock;

        })(jQuery);

        new FixedBlock({
            $wrapper: that.$wrapper,
            $section: that.$wrapper.find(".js-footer-actions"),
            type: "bottom"
        });

    };

    ShopSettingsCheckout2.prototype.initSubmit = function () {
        var that = this;

        that.$form.on('submit', function (e) {
            e.preventDefault();
            if (that.is_locked) {
                return;
            }
            that.is_locked = true;
            that.$button.prop('disabled', true);
            that.$loading.show();
            that.$wrapper.find('.js-submit-error').remove();
            that.$wrapper.find('.state-error').removeClass('state-error shake animated');

            setNames();

            var href = that.$form.attr('action'),
                data = that.$form.serialize();

            $.post(href, data, function (res) {
                if (res.status === 'ok') {
                    that.buttonViewChange(false);
                } else {
                    if (res.errors) {
                        $.each(res.errors, function (i, error) {
                            if (error.field) {
                                fieldError(error);
                            }
                        });
                    }
                }
                setTimeout(function(){
                    that.$loading.hide();
                });
                that.is_locked = false;
                that.$button.prop('disabled', false);
            });

            function fieldError(error) {
                var $field = that.$form.find('*[name="data'+ error.field +'"]');

                if (!$field.length) {
                    $field = that.$form.find('*[data-block-name="'+ error.field +'"]');
                    if (error.message) {
                        $field.after('<div class="js-submit-error" style="color: red;">' + error.message + '</div>');
                    }
                }

                if (error.interrelated_field) {
                    var $interrelated_field = that.$form.find('input[name="data'+ error.interrelated_field +'"]'),
                        $interrelated_field_block_wrapper = $interrelated_field.parents('tr.js-block-wrapper'),
                        $interrelated_field_content_wrapper = $interrelated_field_block_wrapper.find('.js-block-content');
                    $interrelated_field_content_wrapper.show();
                    $interrelated_field.addClass('state-error shake animated');
                }

                var $field_block_wrapper = $field.parents('tr.js-block-wrapper'),
                    $field_content_wrapper = $field_block_wrapper.find('.js-block-content');
                $field_content_wrapper.show();

                $field.addClass('state-error shake animated');

                console.log(error);
            }
        });

        that.$form.on('input', '.state-error', function () {
            if ($(this).hasClass('state-error')) {
                $(this).removeClass('state-error');
            }
            if ($(this).next().hasClass('js-submit-error')) {
                $(this).next().remove();
            }
        });

        that.$form.on('change input', function () {
            that.buttonViewChange(true);
        });

        function setNames() {
            setScheduleExtraWorkdayNames();
            setScheduleWeekendNames();
            setCustomerNames('person');
            setCustomerNames('company');
            setShippingAddressNames();
            setShippingLocationNames();
        }

        function setScheduleExtraWorkdayNames() {
            var $table = that.$wrapper.find('.js-extra-workdays-wrapper');

            $table.find('.js-day-wrapper').each(function (i, tr) {
                var $tr = $(tr);
                $tr.find('input[data-name]').each(function () {
                    $(this).attr('name', 'data[schedule][extra_workdays]['+ i +']['+ $(this).data('name') +']');
                })
            });
        }

        function setScheduleWeekendNames() {
            var $table = that.$wrapper.find('.js-extra-weekends-wrapper');

            $table.find('.js-day-wrapper').each(function (i, tr) {
                var $tr = $(tr);
                $tr.find('.js-extra-weekend').each(function () {
                    $(this).attr('name', 'data[schedule][extra_weekends]['+ i +']');
                })
            });
        }

        function setCustomerNames(type) {
            var $wrapper = that.$wrapper.find('.js-fields-editor[data-type="'+ type +'"]'),
                $fields = $wrapper.find('.js-field');

            $fields.each(function (i, field) {
                var $field = $(field),
                    field_id = $field.data('id');

                $field.find('*[data-name]').each(function () {
                    $(this).attr('name', 'data[customer][fields_'+ type +']['+ field_id +']['+ $(this).data('name') +']');
                });
            });
        }

        function setShippingAddressNames() {
            var $wrapper = that.$wrapper.find('.js-fields-editor[data-type="address"]'),
                $fields = $wrapper.find('.js-field');

            $fields.each(function (i, field) {
                var $field = $(field),
                    field_id = $field.data('id');

                $field.find('*[data-name]').each(function () {
                    $(this).attr('name', 'data[shipping][address_fields]['+ field_id +']['+ $(this).data('name') +']');
                });
            });
        }

        function setShippingLocationNames() {
            var $shipping_block = that.$shipping_block,
                $wrapper = $shipping_block.find('.js-locations-table'),
                $fields = $wrapper.find('.js-location');

            $fields.each(function (i, field) {
                var $field = $(field);

                $field.find('*[data-name]').each(function () {
                    $(this).attr('name', 'data[shipping][locations_list]['+ i +']['+ $(this).data('name') +']');
                });
            });
        }
    };

    ShopSettingsCheckout2.prototype.buttonViewChange = function(changed) {
        var that = this,
            default_class = "green",
            active_class = "yellow";

        if (changed) {
            that.$button
                .removeClass(default_class)
                .addClass(active_class);

        } else {
            that.$button
                .removeClass(active_class)
                .addClass(default_class);
        }
    };

    return ShopSettingsCheckout2;

})(jQuery);
