var shopDialogProductsSet = (function ($) {

    shopDialogProductsSet = function (options) {
        var that = this;

        // DOM
        that.$wrapper = options['$wrapper'];
        that.is_new = options['is_new'];

        // INIT
        that.initClass();
    };
    shopDialogProductsSet.prototype.initClass = function () {
        var that = this;

        if (that.is_new) {
            that.initTransliterateSetID();
        }
        that.initTypeSwitch();
        that.initDatepickers();
    };

    shopDialogProductsSet.prototype.initTypeSwitch = function () {
        var that = this,
            $wrapper = that.$wrapper;

        $wrapper.find('input[name=type]').on('click', function () {
            if ($(this).val() == '0') {
                $wrapper.find('.js-set-dynamic-settings').hide();
            } else {
                $wrapper.find('.js-set-dynamic-settings').show();
            }
        });
    };

    shopDialogProductsSet.prototype.initDatepickers = function () {
        var that = this;

        that.$wrapper.find('.js-datepicker').each( function() {
            var $field = $(this);
            $field.datepicker({});
            $field.datepicker('widget').hide();
        });

        validateStartFinish();

        function validateStartFinish() {
            var $start_field = that.$wrapper.find(".js-start-date"),
                $finish_field = that.$wrapper.find(".js-finish-date");

            var error_class = "error";

            $start_field.on("change", function() {
                var $field = $(this),
                    value = $.trim( $field.val() ),
                    date = null;

                if (value) {
                    var is_valid = checkDate(value);
                    if (is_valid) {
                        date = new Date( $field.datepicker("getDate").getTime() + 1000 * 60 * 60 * 24 );
                    } else {
                        $field.val("").addClass(error_class);
                    }
                }

                $finish_field.datepicker("option", "minDate", date);
            });

            $finish_field.on("change", function() {
                var $field = $(this),
                    value = $.trim( $field.val() ),
                    date = null;

                if (value) {
                    var is_valid = checkDate(value);
                    if (is_valid) {
                        date = new Date( $field.datepicker("getDate").getTime() - 1000 * 60 * 60 * 24 );
                    } else {
                        $field.val("").addClass(error_class);
                    }
                }

                $start_field.datepicker("option", "maxDate", date);
            });

            $([$start_field, $finish_field]).on("keydown", function(event) {
                var $field = $(this),
                    has_error = $field.hasClass(error_class);

                if (has_error) {
                    $field.removeClass(error_class);
                }
            });

            $start_field.trigger("change").datepicker("refresh");
            $finish_field.trigger("change").datepicker("refresh");

            function checkDate(date) {
                var format = $.datepicker._defaults.dateFormat,
                    is_valid = null;

                try {
                    $.datepicker.parseDate(format, date);
                    is_valid = true;

                } catch(e) {
                    is_valid = false;
                }

                return is_valid;
            }
        }
    };

    shopDialogProductsSet.prototype.initTransliterateSetID = function () {
        var that = this,
            $wrapper = that.$wrapper,
            $id_input = $wrapper.find('.js-product-set-id'),
            $name_input = $wrapper.find('.js-product-list-name'),
            state = {val: "", changed: false},
            delay = 200,
            id_input_timer,
            name_input_timer;

        $id_input.bind('keydown', function () {
            var self = $(this);
            if (id_input_timer !== null) {
                clearTimeout(id_input_timer);
                id_input_timer = null;
            }
            id_input_timer = setTimeout(function () {
                var val = self.val();
                if (state.val !== val) {
                    state.changed = true;
                    state.val = val;
                    self.unbind('keydown');
                }
            }, delay);
        });

        $name_input.bind('keydown', function () {
            var self = $(this);
            if (state.changed) {
                self.unbind('keydown');
                return;
            }
            if (name_input_timer !== null) {
                clearTimeout(name_input_timer);
                name_input_timer = null;
            }
            name_input_timer = setTimeout(function () {
                var val = self.val();
                if (!val) {
                    return;
                }
                $.getJSON('?action=transliterate&str=' + val, function (r) {
                    if (state.changed) {
                        self.unbind('keydown');
                        return;
                    }
                    if (r.status == 'ok') {
                        state.val = r.data;
                        $id_input.val(state.val);
                    }
                });
            }, delay);
        });
    };

    shopDialogProductsSet.staticDialog = function (set_id, status) {
        var $wrapperDialog = $('#s-product-set-dialog');

        var showDialog = function () {
            $wrapperDialog = $('#s-product-set-dialog');
            $wrapperDialog.waDialog({
                esc: false,
                disableButtonsOnSubmit: true,
                onLoad: function () {
                    new shopDialogProductsSet({
                        $wrapper: $wrapperDialog,
                        is_new: status === 'new'
                    });

                    setTimeout(function () {
                        $('.js-product-list-name').focus();
                    }, 50);
                },
                onSubmit: function (d) {
                    var form = $(this);
                    var success = function (r) {
                        var hash = null;

                        if (location.hash.indexOf('set_id') > 0) {
                            hash = location.hash.replace('set_id=' + set_id, 'set_id=' + r.data.id);
                            //reset sort
                            if ('rule' in r.data) {
                                hash = hash.replace(/&sort=[^&]*/, '&sort=');
                                hash = hash.replace(/&order=[^&]*/, '&order=');
                            }
                        } else {
                            hash = '#/products/set_id=' + r.data.id;
                        }

                        if (status === 'new') {
                            $.product_sidebar.createNewElementInList(r.data, 'set');
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
                            var errors = r.errors,
                                rendered_errors = 0;

                            for (var name in errors) {
                                var $field = d.find('input[name=' + name + ']');
                                if ($field.length) {
                                    $field.addClass('error');
                                    rendered_errors += 1;

                                    var $wrapper = $field.parent().find('.errormsg');
                                    if ($wrapper.length) {
                                        $wrapper.text(errors[name]);
                                    }

                                    $field.one("keyup", function () {
                                        rendered_errors -= 1;
                                        showSubmit();

                                        $field.removeClass('error');
                                        if ($wrapper.length) {
                                            $wrapper.text("");
                                        }
                                    });
                                }
                            }

                            function showSubmit() {
                                if (!rendered_errors) {
                                    var $submit = form.find("[type='submit']");
                                    $submit.removeAttr("disabled", false);
                                }
                            }

                            return false;
                        }
                    };

                    if (form.find('input:file').length) {
                        $.products._iframePost(form, success, error);
                    } else {
                        $.shop.jsonPost(form.attr('action'), form.serialize(), success, error);
                        return false;
                    }
                },
                onClose: function () {
                    $wrapperDialog.find('.js-datepicker').each(function () {
                        $(this).datepicker('widget').hide();
                    });
                }
            });
        };


        var $p;
        if (!$wrapperDialog.length) {
            $p = $('<div class="s-products-set-dialog-wrapper"></div>').appendTo('body');
        } else {
            $p = $wrapperDialog.closest('.s-products-set-dialog-wrapper').empty();
        }

        if (status === 'new') {
            $p.load('?module=set&action=Create', showDialog);
        } else if (status === 'edit') {
            $p.load('?module=set&action=Edit&set_id=' + set_id, showDialog);
        }
    };
    return shopDialogProductsSet;

})(jQuery);
