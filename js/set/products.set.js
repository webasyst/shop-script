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
                            var errors = r.errors;
                            for (var name in errors) {
                                d.find('input[name=' + name + ']').addClass('error').parent().find('.errormsg').text(errors[name]);
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
                }
            });
        };


        var p;
        if (!$wrapperDialog.length) {
            p = $('<div></div>').appendTo('body');
        } else {
            p = $wrapperDialog.parent();
        }

        if (status === 'new') {
            p.load('?module=set&action=Create', showDialog);
        }

        if (status === 'edit') {
            p.load('?module=set&action=Edit&set_id=' + set_id, showDialog);
        }
    };
    return shopDialogProductsSet;

})(jQuery);
