( function($) {

    var DiscountsPage = ( function($) {

        DiscountsPage = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];

            // CONST
            that.locales = ( options["locales"] || {});
            that.urls = ( options["urls"] || {});

            // DYNAMIC VARS
            that.custom_type = options["custom_type"];

            // INIT
            that.init();
        };

        DiscountsPage.prototype.init = function() {
            var that = this;

            //
            that.initSidebar();
            //
            if (that.custom_type) {
                that.initCustomType();
            }
        };

        DiscountsPage.prototype.initCustomType = function() {
            var that = this,
                $page_content = that.$wrapper.find('.js-page-content'),
                $types_wrapper = that.$wrapper.find('#discount-types');

            var $custom_type_link = $types_wrapper.find('a[rel="'+ that.custom_type +'"][data-custom-url]');

            if ($custom_type_link.length) {
                var content_url = $custom_type_link.data('custom-url');
                $.get(content_url, function (html) {
                    $page_content.html(html);
                });
            }
        };

        DiscountsPage.prototype.initSidebar = function() {
            var that = this;

            var $sidebar_section = that.$wrapper.find(".js-discounts-sidebar");

            initDiscountCombiner($sidebar_section);

            initDiscountDescription($sidebar_section);

            // Controller for combiner radio
            function initDiscountCombiner($wrapper) {
                var $radios = $wrapper.find('input:radio[name="combiner"]'),
                    $button = $wrapper.find('#combiner-save-button');

                $radios.on("change", function() {
                    $button.show();
                });

                $button.on("click", function() {
                    $button.hide();
                    $radios.attr('disabled', true);
                    $.post('?module=marketingDiscountsCombineSave', { value: $radios.filter(':checked').val() }, function() {
                        $radios.attr('disabled', false);
                    });
                });
            }

            // Discount description radios
            function initDiscountDescription($wrapper) {
                var $radios = $wrapper.find('input:radio[name="discount_description"]'),
                    $button = $wrapper.find('#discount-desc-save-button');

                $radios.on("change", function() {
                    $button.show();
                });

                $button.on("click", function() {
                    $button.hide();
                    $radios.attr('disabled', true);
                    $.post('?module=settings&action=configSave', { discount_description: $radios.filter(':checked').val() }, function() {
                        $radios.attr('disabled', false);
                    });
                });
            }

        };

        DiscountsPage.prototype.initCoupons = function(options) {
            var that = this;

            initToggle();

            function initToggle() {
                var current_type = 'coupons';

                var xhr = null;

                var $iButton = that.$wrapper.find('#s-discount-type-status').iButton({
                    labelOn : "",
                    labelOff : "",
                    className: 'mini'
                });

                $iButton.on("change", function() {
                    var self = $(this);
                    var enabled = self.is(':checked');
                    if (enabled) {
                        self.closest('.field-group').siblings().show();
                        $('#discount-types a[rel="'+current_type+'"] i.icon16').attr('class', 'icon16 status-blue-tiny');
                    } else {
                        self.closest('.field-group').siblings().hide();
                        $('#discount-types a[rel="'+current_type+'"] i.icon16').attr('class', 'icon16 status-gray-tiny');
                    }

                    if (xhr) {
                        xhr.abort();
                    }

                    xhr = $.post(that.urls["discounts_enable"], { type: current_type, enable: enabled ? '1' : '0' })
                        .always( function() {
                            xhr = null;
                        });
                });
            }
        };

        DiscountsPage.prototype.initCategories = function() {
            var that = this;

            initToggle();

            initSubmit();

            function initToggle() {
                var current_type = 'category';

                var xhr = null;

                var $iButton = that.$wrapper.find('#s-discount-type-status').iButton({
                    labelOn : "",
                    labelOff : "",
                    className: 'mini'
                });

                $iButton.on("change", function() {
                    var self = $(this);
                    var enabled = self.is(':checked');
                    if (enabled) {
                        self.closest('.field-group').siblings().show();
                        $('#discount-types a[rel="'+current_type+'"] i.icon16').attr('class', 'icon16 status-blue-tiny');
                    } else {
                        self.closest('.field-group').siblings().hide();
                        $('#discount-types a[rel="'+current_type+'"] i.icon16').attr('class', 'icon16 status-gray-tiny');
                    }

                    if (xhr) {
                        xhr.abort();
                    }

                    xhr = $.post(that.urls["discounts_enable"], { type: current_type, enable: enabled ? '1' : '0' })
                        .always( function() {
                            xhr = null;
                        });
                });
            }

            function initSubmit() {
                var is_locked = false;

                var $form = $('#s-discounts-category-form'),
                    $submit_button = $form.find(".js-submit-button");

                $form.on("submit", function(event) {
                    event.preventDefault();

                    var errors = checkErrors();
                    if (!errors.length && !is_locked) {
                        is_locked = true;

                        var $loading = $('<i style="vertical-align:middle" class="icon16 loading"></i>');

                        $submit_button
                            .attr("disabled", true)
                            .after($loading);

                        $.post(that.urls["save"], $form.serialize(), "json")
                            .fail( function() {
                                is_locked = false;
                                $submit_button.attr("disabled", false);
                                $loading.remove();
                            })
                            .done( function(response) {
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

                    $form.find('.error').removeClass('error');
                    $form.find('.errormsg').remove();

                    $form.find('.rate-row:not(.template) input').each(function() {
                        var item = $(this),
                            name = item.attr('name'),
                            val = 0;

                        if (name) {
                            if (name.indexOf('categories') >= 0) {
                                val = parseInt(item.val(), 10);
                                if (isNaN(val) || val < 0 || val > 100) {
                                    item.addClass('error');
                                    item.parent().append('<span class="errormsg">' + that.locales["incorrect_1"] + '</span>');
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

        DiscountsPage.prototype.initOrderTotal = function() {
            var that = this;

            var $form = $('#s-discounts-order-total-form'),
                $submit_button = $form.find(".js-submit-button");

            initToggle();
            initTable();
            initSubmit();

            function initToggle() {
                var current_type = 'order_total';

                var xhr = null;

                var $iButton = that.$wrapper.find('#s-discount-type-status').iButton({
                    labelOn : "",
                    labelOff : "",
                    className: 'mini'
                });

                $iButton.on("change", function() {
                    var self = $(this);
                    var enabled = self.is(':checked');
                    if (enabled) {
                        self.closest('.field-group').siblings().show();
                        $('#discount-types a[rel="'+current_type+'"] i.icon16').attr('class', 'icon16 status-blue-tiny');
                    } else {
                        self.closest('.field-group').siblings().hide();
                        $('#discount-types a[rel="'+current_type+'"] i.icon16').attr('class', 'icon16 status-gray-tiny');
                    }

                    if (xhr) {
                        xhr.abort();
                    }

                    xhr = $.post(that.urls["discounts_enable"], { type: current_type, enable: enabled ? '1' : '0' })
                        .always( function() {
                            xhr = null;
                        });
                });
            }

            function initSubmit() {
                var is_locked = false;

                $form.on("submit", function(event) {
                    event.preventDefault();

                    checkErrors();

                    if (!$form.find('.error').length) {
                        $submit_button.attr('disabled', true);

                        if (!is_locked) {
                            is_locked = true;

                            $.post(that.urls["save"], $form.serialize(), "json")
                                .fail( function() {
                                    is_locked = false;
                                    $submit_button.attr('disabled', false);
                                })
                                .done( function(response) {
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
                    $form.find('.error').removeClass('error');
                    $form.find('.errormsg').remove();

                    $form.find('.rate-row:not(.template) input').each(function() {
                        var item = $(this), name = item.attr('name'), val = 0;
                        if (name && name.indexOf('discount') !== -1) {
                            val = parseInt(item.val(), 10);
                            if (isNaN(val) || val < 0 || val > 100) {
                                item.addClass('error');
                                item.after('<span class="errormsg">' + that.locales["incorrect_1"] + '</span>');
                            }
                        }
                        if (name && name.indexOf('sum') !== -1) {
                            val = parseInt(item.val(), 10);
                            if (isNaN(val) || val < 0) {
                                item.addClass('error');
                                item.after('<span class="errormsg">' + that.locales["incorrect_2"] + '</span>');
                            }
                        }
                    });
                }

                function renderErrors(errors) {
                    console.log("ERRORS:", errors);
                }
            }

            function initTable() {
                var $table = $form.find('table');

                $table.on('click', 'i.delete', function() {
                    $(this).closest('tr').remove();
                    if ($table.find('tbody tr.rate-row:not(.template)').length <= 0) {
                        $table.find('tbody tr.hint').show();
                    }
                });

                $table.on('click', '.js-add-template', function() {
                    var $target = $table.find('tr.template'),
                        $clone = $target.clone();

                    $clone
                        .removeClass('template')
                        .removeClass('hidden')
                        .insertBefore($target);

                    $clone.find("input").attr("required", true);

                    $target.siblings('tr.hint').hide();
                });
            }
        };

        DiscountsPage.prototype.initCustomerTotal = function() {
            var that = this;

            var $form = $('#s-discounts-customer-total-form'),
                $submit_button = $form.find(".js-submit-button");

            initToggle();
            initTable();
            initSubmit();

            function initToggle() {
                var current_type = 'customer_total';

                var xhr = null;

                var $iButton = that.$wrapper.find('#s-discount-type-status').iButton({
                    labelOn : "",
                    labelOff : "",
                    className: 'mini'
                });

                $iButton.on("change", function() {
                    var self = $(this);
                    var enabled = self.is(':checked');
                    if (enabled) {
                        self.closest('.field-group').siblings().show();
                        $('#discount-types a[rel="'+current_type+'"] i.icon16').attr('class', 'icon16 status-blue-tiny');
                    } else {
                        self.closest('.field-group').siblings().hide();
                        $('#discount-types a[rel="'+current_type+'"] i.icon16').attr('class', 'icon16 status-gray-tiny');
                    }

                    if (xhr) {
                        xhr.abort();
                    }

                    xhr = $.post(that.urls["discounts_enable"], { type: current_type, enable: enabled ? '1' : '0' })
                        .always( function() {
                            xhr = null;
                        });
                });
            }

            function initSubmit() {
                var is_locked = false;

                $form.on("submit", function(event) {
                    event.preventDefault();

                    checkErrors();

                    if (!$form.find('.error').length) {
                        $submit_button.attr('disabled', true);

                        if (!is_locked) {
                            is_locked = true;

                            $.post(that.urls["save"], $form.serialize(), "json")
                                .fail( function() {
                                    is_locked = false;
                                    $submit_button.attr('disabled', false);
                                })
                                .done( function(response) {
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
                    $form.find('.error').removeClass('error');
                    $form.find('.errormsg').remove();

                    $form.find('.rate-row:not(.template) input').each(function() {
                        var item = $(this),
                            name = item.attr('name'),
                            val = 0;

                        if (name) {
                            if (name.indexOf('discount') >= 0 || name.indexOf('categories') >= 0) {
                                val = parseInt(item.val(), 10);
                                if (isNaN(val) || val < 0 || val > 100) {
                                    item.addClass('error');
                                    item.after('<span class="errormsg">' + that.locales["incorrect_1"] + '</span>');
                                }
                            }
                            if (name.indexOf('sum') !== -1) {
                                val = parseInt(item.val(), 10);
                                if (isNaN(val) || val < 0) {
                                    item.addClass('error');
                                    item.after('<span class="errormsg">' + that.locales["incorrect_2"] + '</span>');
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
                var $table = $form.find('table');

                $table.on('click', 'i.delete', function() {
                    $(this).closest('tr').remove();
                    if ($table.find('tbody tr.rate-row:not(.template)').length <= 0) {
                        $table.find('tbody tr.hint').show();
                    }
                });

                $table.on('click', '.js-add-template', function() {
                    var $target = $table.find('tr.template'),
                        $clone = $target.clone();

                    $clone
                        .removeClass('template')
                        .removeClass('hidden')
                        .insertBefore($target);

                    $clone.find("input").attr("required", true);

                    $target.siblings('tr.hint').hide();
                });
            }
        };

        return DiscountsPage;

    })($);

    $.shop.marketing.init.discountsPage = function(options) {
        return new DiscountsPage(options);
    };

})(jQuery);