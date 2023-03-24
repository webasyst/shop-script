(function ($) {

    var DiscountsPage = (function ($) {

        DiscountsPage = function (options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];

            // CONST
            that.locales = (options["locales"] || {});
            that.urls = (options["urls"] || {});
            that.loading = '<i class="fas fa-spinner wa-animation-spin custom-ml-4"></i>';

            // DYNAMIC VARS
            that.custom_type = options["custom_type"];

            // INIT
            that.init();
        };

        DiscountsPage.prototype.init = function () {
            var that = this;

            //
            that.initSidebar();
            //
            if (that.custom_type) {
                that.initCustomType();
            }
        };

        DiscountsPage.prototype.initCustomType = function () {
            var that = this,
                $page_content = that.$wrapper.find('.js-page-content'),
                $types_wrapper = that.$wrapper.find('#discount-types');

            var $custom_type_link = $types_wrapper.find('a[rel="' + that.custom_type + '"][data-custom-url]');

            if ($custom_type_link.length) {
                var content_url = $custom_type_link.data('custom-url');
                $.get(content_url, function (html) {
                    $page_content.html(html);
                });
            }
        };

        DiscountsPage.prototype.initSidebar = function () {
            var that = this;

            var $sidebar_section = that.$wrapper.find(".js-discounts-sidebar");

            initDiscountCombiner($sidebar_section);

            initDiscountDescription($sidebar_section);

            // Controller for combiner radio
            function initDiscountCombiner($wrapper) {
                var $radios = $wrapper.find('input:radio[name="combiner"]'),
                    $button = $wrapper.find('#combiner-save-button');

                $radios.on("change", function () {
                    $button.show();
                });

                $button.on("click", function () {
                    $button.hide();
                    $radios.attr('disabled', true);
                    $.post('?module=marketingDiscountsCombineSave', {value: $radios.filter(':checked').val()}, function () {
                        $radios.attr('disabled', false);
                    });
                });
            }

            // Discount description radios
            function initDiscountDescription($wrapper) {
                var $radios = $wrapper.find('input:radio[name="discount_description"]'),
                    $button = $wrapper.find('#discount-desc-save-button');

                $radios.on("change", function () {
                    $button.show();
                });

                $button.on("click", function () {
                    $button.hide();
                    $radios.attr('disabled', true);
                    $.post('?module=settings&action=configSave', {discount_description: $radios.filter(':checked').val()}, function () {
                        $radios.attr('disabled', false);
                    });
                });
            }

        };

        DiscountsPage.prototype.initCoupons = function (options) {
            var that = this;

            initToggle();

            function initToggle() {
                const current_type = 'coupons';

                let xhr = null;

                $(".js-switch-discount-type-status").waSwitch({
                    ready: function (wa_switch) {
                        let $label = wa_switch.$wrapper.siblings('label');
                        wa_switch.$label = $label;
                        wa_switch.active_text = $label.data('active-text');
                        wa_switch.inactive_text = $label.data('inactive-text');
                    },
                    change: function (active, wa_switch) {
                        wa_switch.$label.text(active ? wa_switch.active_text : wa_switch.inactive_text);

                        wa_switch.$wrapper.closest('.fields-group').siblings().toggleClass('hidden', !active);
                        $('#discount-types a[rel="' + current_type + '"] .js-icon').toggleClass('fa-check text-green fa-times text-light-gray');

                        if (xhr) {
                            xhr.abort();
                        }

                        xhr = $.post(that.urls["discounts_enable"], {type: current_type, enable: active ? '1' : '0'})
                            .always(function () {
                                xhr = null;
                            });
                    }
                });
            }
        };

        DiscountsPage.prototype.initCategories = function () {
            const that = this;

            initToggle();

            initSubmit();

            function initToggle() {
                const current_type = 'category';

                let xhr = null;

                $(".js-switch-discount-type-status").waSwitch({
                    ready: function (wa_switch) {
                        let $label = wa_switch.$wrapper.siblings('label');
                        wa_switch.$label = $label;
                        wa_switch.active_text = $label.data('active-text');
                        wa_switch.inactive_text = $label.data('inactive-text');
                    },
                    change: function (active, wa_switch) {
                        wa_switch.$label.text(active ? wa_switch.active_text : wa_switch.inactive_text);

                        wa_switch.$wrapper.closest('.fields-group').siblings().toggleClass('hidden', !active);
                        $('#discount-types a[rel="' + current_type + '"] .js-icon').toggleClass('fa-check text-green fa-times text-light-gray');

                        if (xhr) {
                            xhr.abort();
                        }

                        xhr = $.post(that.urls["discounts_enable"], {type: current_type, enable: active ? '1' : '0'})
                            .always(function () {
                                xhr = null;
                            });
                    }
                });
            }

            function initSubmit() {
                let is_locked = false;

                const $form = $('#s-discounts-category-form'),
                    $submit_button = $form.find(".js-submit-button");

                that.initFormChanged($form);
                $form.on("submit", function (event) {
                    event.preventDefault();

                    var errors = checkErrors();
                    if (!errors.length && !is_locked) {
                        is_locked = true;

                        const $loading = $(that.loading);

                        $submit_button
                            .attr("disabled", true)
                            .after($loading);

                        $.post(that.urls["save"], $form.serialize(), "json")
                            .fail(function () {
                                is_locked = false;
                                $submit_button.attr("disabled", false);
                                $loading.remove();
                            })
                            .done(function (response) {
                                if (response.status === "ok") {
                                    $.shop.marketing.content.reload();
                                } else if (response.errors) {
                                    renderErrors(response.errors);
                                }
                            });
                    }
                });

                function checkErrors() {
                    var result = [];

                    $form.find('.state-error').removeClass('error');
                    $form.find('.state-error-hint').remove();

                    $form.find('.rate-row:not(.template) input').each(function () {
                        var item = $(this),
                            name = item.attr('name'),
                            val = 0;

                        if (name) {
                            if (name.indexOf('categories') >= 0) {
                                val = parseInt(item.val(), 10);
                                if (isNaN(val) || val < 0 || val > 100) {
                                    item.addClass('state-error');
                                    item.parent().append('<div class="state-error-hint">' + that.locales["incorrect_1"] + '</div>');
                                    result.push("categories_error");
                                }
                            }
                        }
                    });

                    return result;
                }

                function renderErrors(errors) {
                    console.log("ERRORS:", errors);
                }
            }
        };

        DiscountsPage.prototype.initOrderTotal = function () {
            const that = this;

            const $form = $('#s-discounts-order-total-form'),
                $submit_button = $form.find(".js-submit-button");

            initToggle();
            initTable();
            initSubmit();

            const submitChanged = that.initFormChanged($form);

            function initToggle() {
                const current_type = 'order_total';

                let xhr = null;

                $(".js-switch-discount-type-status").waSwitch({
                    ready: function (wa_switch) {
                        let $label = wa_switch.$wrapper.siblings('label');
                        wa_switch.$label = $label;
                        wa_switch.active_text = $label.data('active-text');
                        wa_switch.inactive_text = $label.data('inactive-text');
                    },
                    change: function (active, wa_switch) {
                        wa_switch.$label.text(active ? wa_switch.active_text : wa_switch.inactive_text);

                        wa_switch.$wrapper.closest('.fields-group').siblings().toggleClass('hidden', !active);
                        $('#discount-types a[rel="' + current_type + '"] .js-icon').toggleClass('fa-check text-green fa-times text-light-gray');

                        if (xhr) {
                            xhr.abort();
                        }

                        xhr = $.post(that.urls["discounts_enable"], {type: current_type, enable: active ? '1' : '0'})
                            .always(function () {
                                xhr = null;
                            });
                    }
                });
            }

            function initSubmit() {
                let is_locked = false;

                $form.on("submit", function (event) {
                    event.preventDefault();

                    checkErrors();

                    if (!$form.find('.state-error').length) {
                        if (!is_locked) {
                            is_locked = true;

                            const $loading = $(that.loading);

                            $submit_button
                                .attr("disabled", true)
                                .after($loading);

                            $.post(that.urls["save"], $form.serialize(), "json")
                                .fail(function () {
                                    is_locked = false;
                                    $loading.remove();
                                    $submit_button.attr('disabled', false);
                                })
                                .done(function (response) {
                                    if (response.status === "ok") {
                                        $.shop.marketing.content.reload();
                                    } else if (response.errors) {
                                        renderErrors(response.errors);
                                    }
                                });
                        }
                    }
                });

                function checkErrors() {
                    $form.find('.state-error').removeClass('state-error');
                    $form.find('.state-error-hint').remove();

                    $form.find('.rate-row:not(.template) input').each(function () {
                        const item = $(this), name = item.attr('name');
                        let val = 0;
                        if (name && name.indexOf('discount') !== -1) {
                            val = parseInt(item.val(), 10);
                            if (isNaN(val) || val < 0 || val > 100) {
                                item.addClass('state-error');
                                item.after('<div class="state-error-hint">' + that.locales["incorrect_1"] + '</div>');
                            }
                        }
                        if (name && name.indexOf('sum') !== -1) {
                            val = parseInt(item.val(), 10);
                            if (isNaN(val) || val < 0) {
                                item.addClass('state-error');
                                item.after('<div class="state-error-hint">' + that.locales["incorrect_2"] + '</div>');
                            }
                        }
                    });
                }

                function renderErrors(errors) {
                    console.log("ERRORS:", errors);
                }
            }

            function initTable() {
                const $table = $form.find('table');

                $table.on('click', '.js-row-delete', function () {
                    $(this).closest('tr').remove();
                    if ($table.find('tbody tr.rate-row:not(.template)').length <= 0) {
                        $table.find('tbody tr.hint').show();
                    }
                    submitChanged();
                });

                $table.on('click', '.js-add-template', function () {
                    const $target = $table.find('tr.template'),
                        $clone = $target.clone();

                    $clone
                        .removeClass('template')
                        .removeClass('hidden')
                        .insertBefore($target);

                    $clone.find("input").attr("required", true);

                    $target.siblings('tr.hint').hide();

                    submitChanged();
                });
            }
        };

        DiscountsPage.prototype.initCustomerTotal = function () {
            const that = this;

            const $form = $('#s-discounts-customer-total-form'),
                $submit_button = $form.find(".js-submit-button");

            initToggle();
            initTable();
            initSubmit();

            const submitChanged = that.initFormChanged($form);

            function initToggle() {
                const current_type = 'customer_total';

                let xhr = null;

                $(".js-switch-discount-type-status").waSwitch({
                    ready: function (wa_switch) {
                        let $label = wa_switch.$wrapper.siblings('label');
                        wa_switch.$label = $label;
                        wa_switch.active_text = $label.data('active-text');
                        wa_switch.inactive_text = $label.data('inactive-text');
                    },
                    change: function (active, wa_switch) {
                        wa_switch.$label.text(active ? wa_switch.active_text : wa_switch.inactive_text);

                        wa_switch.$wrapper.closest('.fields-group').siblings().toggleClass('hidden', !active);
                        $('#discount-types a[rel="' + current_type + '"] .js-icon').toggleClass('fa-check text-green fa-times text-light-gray');

                        if (xhr) {
                            xhr.abort();
                        }

                        xhr = $.post(that.urls["discounts_enable"], {type: current_type, enable: active ? '1' : '0'})
                            .always(function () {
                                xhr = null;
                            });
                    }
                });
            }

            function initSubmit() {
                let is_locked = false;

                $form.on("submit", function (event) {
                    event.preventDefault();

                    checkErrors();

                    if (!$form.find('.state-error').length) {

                        if (!is_locked) {
                            is_locked = true;

                            const $loading = $(that.loading);
                            $submit_button
                                .attr("disabled", true)
                                .after($loading);

                            $.post(that.urls["save"], $form.serialize(), "json")
                                .fail(function () {
                                    is_locked = false;
                                    $loading.remove();
                                    $submit_button.attr('disabled', false);
                                })
                                .done(function (response) {
                                    if (response.status === "ok") {
                                        $.shop.marketing.content.reload();
                                    } else if (response.errors) {
                                        renderErrors(response.errors);
                                    }
                                });
                        }
                    }
                });

                function checkErrors() {
                    $form.find('.state-error').removeClass('state-error');
                    $form.find('.state-error-hint').remove();

                    $form.find('.rate-row:not(.template) input').each(function () {
                        const item = $(this),
                            name = item.attr('name');
                        let val = 0;

                        if (name) {
                            if (name.indexOf('discount') >= 0 || name.indexOf('categories') >= 0) {
                                val = parseInt(item.val(), 10);
                                if (isNaN(val) || val < 0 || val > 100) {
                                    item.addClass('state-error');
                                    item.after('<div class="state-error-hint">' + that.locales["incorrect_1"] + '</div>');
                                }
                            }
                            if (name.indexOf('sum') !== -1) {
                                val = parseInt(item.val(), 10);
                                if (isNaN(val) || val < 0) {
                                    item.addClass('state-error');
                                    item.after('<div class="state-error-hint">' + that.locales["incorrect_2"] + '</div>');
                                }
                            }
                        }
                    });
                }

                function renderErrors(errors) {
                    console.log("ERRORS:", errors);
                }
            }

            function initTable() {
                const $table = $form.find('table');

                $table.on('click', '.js-row-delete', function () {
                    $(this).closest('tr').remove();
                    if ($table.find('tbody tr.rate-row:not(.template)').length <= 0) {
                        $table.find('tbody tr.hint').show();
                    }
                    submitChanged();
                });

                $table.on('click', '.js-add-template', function () {
                    const $target = $table.find('tr.template'),
                        $clone = $target.clone();

                    $clone
                        .removeClass('template')
                        .removeClass('hidden')
                        .insertBefore($target);

                    $clone.find("input").attr("required", true);

                    $target.siblings('tr.hint').hide();

                    submitChanged();
                });
            }
        };

        DiscountsPage.prototype.initFormChanged = function ($form) {
            const $submit = $form.find('[type="submit"]');
            const submitChanged = () => {
                $submit.removeClass('green').addClass('yellow');
            };

            let is_changed = false;
            $(':input:not(:submit):not([name="enabled"])', $form).on('input', function() {
                if (is_changed) return true;

                submitChanged();

                is_changed = true;
            });

            $submit.on('click', function() {
                $submit.removeClass('yellow').addClass('green');
                is_changed = false;
            });

            return submitChanged;
        }

        return DiscountsPage;

    })($);

    $.shop.marketing.init.discountsPage = function (options) {
        return new DiscountsPage(options);
    };

})(jQuery);
