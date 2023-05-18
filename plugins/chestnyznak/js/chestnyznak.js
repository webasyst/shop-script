var ShopChestnyznakPlugin = ( function($) {

    var ShopChestnyznakPlugin = function(options) {
        var that = this;
        // DOM
        that.$dialog = options.$dialog;
        if (options.$form.length) {
            that.$form = options.$form;
        }else{
            that.$form = options.$dialog.find('form');
        }

        // dialog's controller - for init plugin
        that.controller = options.controller;

        // id of product code of this plugin
        that.product_code_id = options.product_code_id;

        // localized messages
        that.messages = options.messages || {};

        // we need shop app url, because UI could be injected to CRM
        that.app_url = options.app_url || '';

        // parsed codes for existing codes
        that.parsed_codes = !$.isEmptyObject(options.parsed_codes) ? options.parsed_codes : {};

        that.init();
    };

    ShopChestnyznakPlugin.prototype.init = function () {
        var that = this;

        // init this plugin (register onSubmit handler)
        that.controller.initPlugin({
            plugin_id: "chestnyznak",
            onSubmit: function() {
                return that.onSubmit();
            }
        });

        that.initCodesParsing();
    };

    ShopChestnyznakPlugin.prototype.initCodesParsing = function() {
        var that = this,
            $form = that.$form,
            product_code_id = that.product_code_id;

        if (!product_code_id) {
            // nothing to do - there is not inputs with chestnyznak inputs
            return;
        }

        var selector = 'input[name^="code[' + product_code_id + ']"]',
            $inputs = $form.find(selector);

        // render DOM items for parsing results
        $inputs.each(function () {
            $(this).after(that.createParsingResultDOM());
        });

        // render already parsed codes
        that.renderParsedCodes(that.parsed_codes);

        // UI: On fly parse codes and render its where user change codes in inputs

        // for define has input value changed during 'keyup'
        var input_values = {};
        var initInputValues = function () {
            $inputs.each(function () {
                var $input = $(this),
                    name = $input.attr('name') || '',
                    index = $input.data('index'),
                    extended_name = name.replace('[]', '[' + index + ']');
                input_values[extended_name] = $input.val();
            });
        };
        initInputValues();

        var isInputValChanged = function ($input) {
            var name = $input.attr('name') || '',
                index = $input.data('index'),
                val = $input.val(),
                extended_name = name.replace('[]', '[' + index + ']'),
                old_val = input_values[extended_name] || '';
            if (val !== old_val) {
                input_values[name] = val;
                return true;
            }
            return false;
        };

        var changeHandler = function($input) {
            var name = $input.attr('name'),
                index = $input.data('index');

            that.parseCodes([
                {
                    name: name.replace('[]', '[' + index + ']'),
                    value: $input.val()
                }
            ]);
        };

        // on keyup change with timeout
        var timer_id = $form.on('keyup', selector, function () {
            var $input = $(this);
            timer_id && clearTimeout(timer_id);
            timer_id = setTimeout(function () {
                if (isInputValChanged($input)) {
                    changeHandler($input);
                }
            }, 500);
        });

        // on change
        $form.on('change', selector, function () {
            var $input = $(this);
            if (isInputValChanged($input)) {
                changeHandler($input);
            }
        });
    };

    ShopChestnyznakPlugin.prototype.renderParsedCodes = function(data) {
        var that = this,
            $form = that.$form,
            product_code_id = that.product_code_id;

        $.each(data || {}, function (order_item_id, result) {
            $.each(result, function (sort, result) {
                var selector = 'input[name^="code[' + product_code_id + '][' + order_item_id + ']"]:eq(' + sort + ')',
                    $input = $form.find(selector),
                    $parsing_result = $input.parent().find('.js-parsing-result');

                if (!$input.length) {
                    return;
                }

                // if code have been converted from cyrillic to latin keyboard layout
                if (result.converted) {
                    $input.val(result.converted);
                }

                // clear previous render result
                $parsing_result.find('.js-validation .js-error-not-match').text('');
                $parsing_result.find('.js-parsed-params').removeClass('s-cs-invalid');

                // parsed fail, means it is not correct chestnyznak UID
                if (!result.parsed) {
                    $parsing_result.find('.js-parsed-params').addClass('s-cs-invalid');
                    $parsing_result.find('.js-gtin-value').text("invalid");
                    $parsing_result.find('.js-serial-value').text("invalid");
                    return;
                }

                // parsed ok - render parsing result
                $parsing_result.find('.js-gtin-value').text(result.parsed.gtin);
                $parsing_result.find('.js-serial-value').text(result.parsed.serial);

                // show warnings
                if (!$.isEmptyObject(result.warnings) && result.warnings.separator_missed) {
                    $parsing_result.find('.js-warning').show();
                    $parsing_result.find('.js-warning .js-message').text(result.warnings.separator_missed);
                } else {
                    $parsing_result.find('.js-warning').hide();
                }

                // case when GTIN is not match with product GTIN
                if (!$.isEmptyObject(result.validation) && result.validation.not_match) {
                    $parsing_result.find('.js-validation').show();
                    $parsing_result.find('.js-validation .js-error-not-match').text(result.validation.not_match);
                } else {
                    $parsing_result.find('.js-validation').hide();
                }

            });
        })
    };

    ShopChestnyznakPlugin.prototype.parseCodes = function(request_data) {
        var that = this,
            app_url = that.app_url;
        return $.post(app_url + '?plugin=chestnyznak&module=order&action=codesValidate', request_data, function (r) {
            var data = r && r.data || {};
            that.renderParsedCodes(data.parsed || {});
        });
    };

    ShopChestnyznakPlugin.prototype.createParsingResultDOM = function() {
        return $('<div class="js-parsing-result s-cz-parsing-result">' +
            '<div class="js-parsed-params s-cz-parsed-params">' +
                '<span> <span>GTIN:</span> <span class="js-gtin-value s-cz-gtin-value"></span> </span>' +
                '<span> <span>S/N:</span> <span class="js-serial-value s-cz-serial-value"></span> </span>' +
            '</div>' +
            '<div class="js-warning" style="display: none">' +
                '<i class="icon16 exclamation"></i> <span class="js-message hint"></span>' +
            '</div>' +
            '<div class="js-validation" style="display: none">' +
                '<span class="s-cs-errormsg js-error-not-match"></span>' +
            '</div>' +
        '</div>');
    };

    ShopChestnyznakPlugin.prototype.onSubmit = function () {
        var that = this,
            deferred = $.Deferred(),
            $form = that.$form,
            result = {
                data: [],
                errors: []
            },
            data = $form.serializeArray(),
            error_class = "error",
            text_error_class = ".s-error",
            product_code_id = that.product_code_id,
            messages = that.messages;

        $form.find('.error').removeClass(error_class);
        $form.find(text_error_class).remove();

        if (!product_code_id) {
            return deferred.promise();
        }

        var chestnyznak = 'code[' + product_code_id + ']';
        for (var i = 0; i < data.length - 1; i++) {
            if (data[i].name.indexOf(chestnyznak) != -1) {
                var has_same = false;
                for (var j = i + 1; j < data.length; j++) {
                    if (data[i].value == data[j].value && data[i].value != "") {
                        has_same = true;
                        break;
                    }
                }
                if (has_same) {
                    var is_prop = false;
                    for (var key in result.errors) {
                        if (result.errors[key].value == data[i].value) {
                            is_prop = true;
                        }
                    }
                    if (is_prop == false) {
                        result.errors.push(data[i]);
                    }
                } else {
                    result.data.push(data[i]);
                }
            }
        }

        for (var i = 0; i < data.length; i++) {
            if (data[i].name.indexOf(chestnyznak) == -1) {
                result.data.push(data[i]);
            }
        }

        if (result.errors.length) {
            var $text = $("<span class='s-error errormsg'></span>").text(messages.unique);

            $.each(result.errors, function(index, item) {
                $.each($form.find('input[name^="' + chestnyznak + '"]'), function(subindex, subitem) {
                    if (item.value == $(subitem).val()) {
                        $(subitem)
                            .addClass(error_class)
                            .one("focus click change", function() {
                                $(subitem).removeClass(error_class);
                                $(subitem).siblings(text_error_class).remove();
                            });
                        $(subitem).after($text.clone());
                    }
                });
            });

            deferred.reject(result.errors);
        } else {
            deferred.resolve(result.data);
        }

        return deferred.promise();
    };

    return ShopChestnyznakPlugin;

})(jQuery);

(function ($) {
    var $dialog = $("#js-order-marking-dialog"),
        ready_promise = $dialog.data('ready');

    ready_promise.then(function(controller) {
        var $form = $dialog.find(".wa-dialog-body form:first"),
            options = $dialog.data('shop_chestnyznak_plugin_options') || {};

        options = $.extend({
            $dialog: $dialog,
            $form: $form,
            controller: controller
        }, options);

        new ShopChestnyznakPlugin(options);
    });
})(jQuery);

