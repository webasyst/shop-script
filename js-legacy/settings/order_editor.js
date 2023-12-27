var ShopSettingsOrderEditor = ( function($) {

    ShopSettingsOrderEditor = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find('form');

        that.$general_block = that.$form.find('.js-block-wrapper[data-block="general"]');
        that.$person_block = that.$form.find('.js-block-wrapper[data-block="person"]');
        that.$company_block = that.$form.find('.js-block-wrapper[data-block="company"]');
        that.$address_block = that.$form.find('.js-block-wrapper[data-block="address"]');

        that.$button = that.$form.find('.js-submit-button');
        that.$loading = that.$wrapper.find('.js-loading');

        // VARS
        that.name_format_full_fields = ['firstname', 'middlename', 'lastname'];
        that.name_format_one_fields = ['name'];

        // DYNAMIC VARS
        that.is_locked = false;

        // INIT
        that.initClass();
    };

    ShopSettingsOrderEditor.prototype.initClass = function() {
        var that = this;

        //
        that.initFixDeliveryArea();
        //
        that.initBigTable();
        //
        that.ensureNameFieldsConsistency();
        //
        that.initSortTables();
        //
        that.initFixedActions();
        //
        that.initSubmit();
        //
        $(window).trigger('scroll');
    };

    ShopSettingsOrderEditor.prototype.initFixDeliveryArea = function() {
        var that = this,
            $fix_delivery_field = that.$wrapper.find('.js-fixed-delivery-area'),
            $fix_delivery_country = $fix_delivery_field.find('.js-fix-delivery-country'),
            $fix_delivery_region = $fix_delivery_field.find('.js-fix-delivery-region'),
            $fix_delivery_city = $fix_delivery_field.find('.js-fix-delivery-city');

        syncRegionsWithCountry($fix_delivery_country, $fix_delivery_region, $fix_delivery_city);
        $fix_delivery_field.on('change', '.js-fix-delivery-country, .js-fix-delivery-region', function () {
            syncRegionsWithCountry($fix_delivery_country, $fix_delivery_region, $fix_delivery_city);
        });

        function syncRegionsWithCountry ($country_select, $region_select, $city_input) {
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
        }
    };

    ShopSettingsOrderEditor.prototype.initBigTable = function () {
        var that = this,
            $big_table = that.$wrapper.find('.js-big-table');

        $big_table.on('click', '.js-settings-link', function () {
            var $block_wrapper = $(this).parents('tr.js-block-wrapper'),
                $content_wrapper = $block_wrapper.find('.js-block-content');

            if ($content_wrapper.is(':visible')) {
                $content_wrapper.hide();
            } else {
                $content_wrapper.show();
            }
            $(window).trigger('scroll');
        });
    };

    ShopSettingsOrderEditor.prototype.ensureNameFieldsConsistency = function() {
        var that = this;

        that.$person_block.on('change', '.js-name-format-variant', function () {
            var $current_radio = that.$person_block.find('.js-name-format-variant:checked'),
                current_value = $current_radio.val();

            if (current_value == 'full') {
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
            var $field = that.$person_block.find('.js-field[data-id="'+field_id+'"]');

            $field.show();
            $field.find('.js-used-checkbox').prop('checked', true);
        }

        function personFieldHide(field_id) {
            var $field = that.$person_block.find('.js-field[data-id="'+field_id+'"]');

            $field.hide();
            $field.find('.js-used-checkbox').prop('checked', false);
            $field.find('.js-required-checkbox').prop('checked', false);
        }

    };

    ShopSettingsOrderEditor.prototype.initSortTables = function() {
        var that = this,
            $sort_tables = that.$wrapper.find('.js-sort-table');

        $.each($sort_tables, function () {
            var $sort_table = $(this);

            $sort_table.sortable({
                axis: 'y',
                distance: 5,
                helper: 'clone',
                items: 'tr',
                opacity: 0.75,
                handle: '.sort',
                tolerance: 'pointer',
                containment: $sort_table
            });
        });
    };

    ShopSettingsOrderEditor.prototype.initFixedActions = function() {
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

    ShopSettingsOrderEditor.prototype.initSubmit = function () {
        var that = this;

        that.$form.on('submit', function (e) {
            e.preventDefault();
            if (that.is_locked) {
                return;
            }
            that.is_locked = true;
            that.$button.prop('disabled', true);
            that.$loading.removeClass('yes').removeClass('no').addClass('loading').show();
            that.$wrapper.find('.js-submit-error').remove();
            that.$wrapper.find('.error').removeClass('error shake animated');

            var href = that.$form.attr('action'),
                data = that.$form.serialize();

            $.post(href, data, function (res) {
                if (res.status === 'ok' && res.data === true) {
                    that.buttonViewToggle(false);
                    that.$loading.removeClass('loading').addClass('yes');
                } else {
                    that.$loading.removeClass('loading').addClass('no');
                }

                setTimeout(function () {
                    that.$loading.hide();
                }, 2000);

                that.is_locked = false;
                that.$button.prop('disabled', false);
            });
        });

        that.$form.on('input', function () {
            that.buttonViewToggle(true);
        });
    };

    ShopSettingsOrderEditor.prototype.buttonViewToggle = function(show) {
        var that = this,
            default_class = "green",
            active_class = "yellow";

        if (show) {
            that.$button
                .removeClass(default_class)
                .addClass(active_class);

        } else {
            that.$button
                .removeClass(active_class)
                .addClass(default_class);
        }
    };

    return ShopSettingsOrderEditor;

})(jQuery);
