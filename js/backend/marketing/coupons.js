( function($) {

    var Coupons = ( function($) {

        Coupons = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];

            // CONST
            that.coupon_id = options["coupon_id"];
            that.urls = options["urls"];
            that.locales = options["locales"];

            // DYNAMIC VARS

            // INIT
            that.init();
        };

        Coupons.prototype.init = function() {
            var that = this;

            that.initCouponEditor();
        };

        Coupons.prototype.initCouponEditor = function() {
            var that = this;

            var $form = $('#coupon-editor-form'),
                $submit_button = $form.find('.js-submit-button'),
                $coupon_name = that.$wrapper.find('#coupon-name'),
                $coupon_code = that.$wrapper.find('.js-coupon-code');

            $.importexport.products.init($form);

            // When user types in code input, change the <h1> on the fly
            $form.find('[name="coupon[code]"]').on('input', function() {
                $coupon_code.text($(this).val());
            });

            if ($coupon_name.length) {
                $coupon_name.on("change", function() {
                    var coupon_name = $.trim($(this).val());
                    var href = that.urls["first_page"];

                    if (coupon_name.length) {
                        href = that.urls["coupons"].replace("%page_number%", 1).replace("%coupon_name%", coupon_name);
                    }

                    $.shop.marketing.content.load(href);
                });

                $coupon_name.on("search", function() {
                    $(this).trigger("change");
                });
            }

            // Form validation
            var isValid = function(is_init = true) {
                $form.find('.state-error-hint').remove();
                $form.find('.state-error').removeClass('state-error');

                var valid = true;
                var code_field = $('[name="coupon[code]"]');
                if (!code_field.val()) {
                    valid = false;
                    code_field.addClass('state-error').after($('<em class="state-error-hint custom-ml-4"></em>').text(that.locales["required"]));
                }

                if (!is_init) {
                    var discount_value = 0;
                    var discount_input = $('[name="coupon[value]"]');
                    if ($('[name="coupon[type]"]').val() === '%') {
                        discount_value = parseInt(discount_input.val(), 10);
                        if (isNaN(discount_value) || discount_value < 0 || discount_value > 100) {
                            valid = false;
                            discount_input.addClass('state-error').nextAll().after($('<em class="state-error-hint custom-ml-4"></em>').text(that.locales["incorrect_1"]));
                        }
                    }
                }

                // URL validation
                var $url_field = $('[name="coupon[url]"]');
                var url_val    = $url_field.val().trim();
                if (url_val !== '') {
                    if (url_val.length > 2048) {
                        valid = false;
                        $url_field.addClass('state-error').after($('<em class="state-error-hint custom-ml-4"></em>').text(that.locales['url_max_len']));
                    } else if (/https?:\/\/[^\s]*\.[^\s]*/.exec(url_val) === null) {
                        valid = false;
                        $url_field.addClass('state-error').after($('<em class="state-error-hint custom-ml-4"></em>').text(that.locales['url_no_valid']));
                    }
                }

                return valid;
            };

            // When user changes type, change how value input looks
            $('select[name="coupon[type]"]').change(function() {
                var select = $(this);
                var type = select.val();
                if (type === '$FS') {
                    $('#value-input-wrapper').hide();
                    $('#free-shipping-message').show();
                    $form.find('.js-product-selector-field').hide();
                } else {
                    var wr = $('#value-input-wrapper').show();
                    $('#free-shipping-message').hide();
                    if (type === '%') {
                        wr.children('span').text('%');
                    } else {
                        var t = $.trim(select.children('[value="'+type+'"]').text());
                        wr.children('span').text(t.substr(0, t.length-3));
                    }
                    $form.find('.js-product-selector-field').show();
                }

                isValid();
            }).change();

            // TODO: migrate to lib
            function debounce(callback, timeout = 0, atStartRun = false) {
                if (typeof callback !== "function") {
                    return true;
                }

                let timer = null;

                return () => {
                    if (atStartRun && null === timer) {
                        callback();
                    }

                    if (timer) {
                        clearTimeout(timer);
                    }

                    timer = setTimeout(callback, timeout);
                }
            }

            ('[name="coupon[code]"]', $form).on('input', debounce(isValid, 250));

            // Datepicker
            var datetime_input = $('input[name="coupon[expire_datetime]"]');
            datetime_input.datepicker({
                'dateFormat': 'yy-mm-dd'
            });
            datetime_input.next('a').click(function() {
                $('input[name="coupon[expire_datetime]"]').datepicker('show');
            });
            // widget appears in bottom left corner for some reason, so we hide it
            datetime_input.datepicker('widget').hide();

            if (that.coupon_id) {
                initDelete();
            }

            initSubmit();

            function initSubmit() {
                var is_locked = false;

                // Form submission
                $form.on("submit", function(event) {
                    event.preventDefault();

                    var is_valid = isValid(false);
                    if (!is_locked && is_valid) {
                        is_locked = true;

                        $submit_button.attr('disabled', true);

                        var form_data = $form.serializeArray();

                        if ($coupon_name.length) {
                            var coupon_name = $.trim($coupon_name.val());
                            if (coupon_name) {
                                form_data.push({
                                    name: "coupon_name",
                                    value: coupon_name
                                });
                            }
                        }

                        var class_swing = 'wa-animation-swing';
                        $submit_button.removeClass(class_swing);
                        var $icon = $submit_button.after('<span><i class="fas fa-spinner wa-animation-spin"></i></span>').next();
                        $.post($form.attr('action'), form_data)
                            .always( function() {
                                $submit_button.attr('disabled', false);
                                $icon.remove();
                                is_locked = false;
                            })
                            .done( function(response) {
                                if (response.status === "ok") {
                                    var href = that.urls["coupon"].replace("%id%", response.data.id)
                                        .replace("%page_number%", response.data.page_number)
                                        .replace("&coupon_name=%coupon_name%", response.data.coupon_name ? "&coupon_name=" + response.data.coupon_name : "");
                                    $.shop.marketing.content.load(href);
                                } else if (response.errors) {
                                    renderErrors(response.errors);
                                    $submit_button.addClass(class_swing);
                                }

                            }).fail(function () {
                                $submit_button.addClass(class_swing);
                            });
                    }
                });
            }

            function initDelete() {
                var is_locked = false;

                var code = that.$wrapper.find('[name="coupon[code]"]').val();
                if (code) {
                    $form.on("click", "#delete-coupon-link", function(event) {
                        event.preventDefault();
                        $.waDialog.confirm({
                            title: that.locales["delete"].replace('%s', code),
                            success_button_title: $_('Delete'),
                            success_button_class: 'danger',
                            cancel_button_title: $_('Cancel'),
                            cancel_button_class: 'light-gray',
                            onSuccess: function($dialog, dialog_instance) {
                                deleteCoupon();
                            }
                        });
                    });
                }

                function deleteCoupon() {
                    var href = that.urls["delete"],
                        data = {
                            "coupon_id": that.coupon_id,
                            "delete": 1
                        };

                    var coupon_name = $.trim($coupon_name.val());
                    if (coupon_name) {
                        data.coupon_name = coupon_name;
                    }

                    $.post(href, data)
                        .always( function() {
                            is_locked = false;
                        })
                        .done( function(response) {
                            if (response.status === "ok") {
                                var href = that.urls["coupons"].replace("%page_number%", response.data.page_number)
                                    .replace("&coupon_name=%coupon_name%", response.data.coupon_name ? "&coupon_name=" + response.data.coupon_name : "");
                                $.shop.marketing.content.load(href);
                            } else if (response.errors) {
                                renderErrors(response.errors);
                            }
                        });
                }

            }

            function renderErrors(errors) {
                $.each(errors, function(i, error) {
                    if (error.name && error.text) {
                        var $field = $form.find("[name=\"" + error.name + "\"]");
                        if ($field.length) {
                            error.$field = $field;
                            renderError(error);
                        }
                    }
                });

                function renderError(error) {
                    var $error = $("<div class=\"state-error-hint\" />").text(error.text);
                    var error_class = "state-error";

                    if (error.$field) {
                        var $field = error.$field;

                        if (!$field.hasClass(error_class)) {
                            $field.addClass(error_class);
                            $error.insertAfter($field);
                            $field.on("change keyup", removeFieldError);
                        }
                    }

                    function removeFieldError() {
                        $field.removeClass(error_class);
                        $error.remove();

                        $field.off("change", removeFieldError);
                    }
                }
            }

            formChanged($form);

            function formChanged($form) {
                const $submit = $form.find('[type="submit"]');
                let is_changed = false;

                const submitChanged = () => {
                    if (is_changed) return true;

                    $submit.removeClass('green').addClass('yellow');
                    is_changed = true;
                };

                $form.on('change', submitChanged);
                $(':input:not(:submit)', $form).on('input', submitChanged);

                $submit.on('click', function() {
                    $submit.removeClass('yellow').addClass('green');
                    is_changed = false;
                });
            }
        };

        return Coupons;

    })($);

    $.shop.marketing.init.couponsPage = function(options) {
        return new Coupons(options);
    };

})(jQuery);
