/**
 * @class {ShopBackendOrderEditorCustomerForm}
 */
var ShopBackendOrderEditorCustomerForm = ( function($) {

    /**
     * @param {jQuery} $wrapper
     * @param editor
     * @param {Object} options
     * @constructor
     */
    ShopBackendOrderEditorCustomerForm = function($wrapper, editor, options) {

        /**
         * @class {ShopBackendOrderEditorCustomerForm}
         */
        var that = this;

        /**
         * @member {Object}
         */
        that.options = options || {};

        /**
         * @member {jQuery}
         */
        that.$wrapper = $wrapper && $wrapper.length > 0 ? $wrapper : $('#s-order-edit-customer');

        /**
         * @member {Object}
         */
        that.editor = editor;

        /**
         * @member {jQuery}
         */
        that.$customer_inputs = that.$wrapper.find(':input');

        /**
         * @member {Object}
         */
        that.initial_values = {};

        /**
         * @member {jQuery}
         */
        that.$customer_form_wrapper = that.$wrapper.find('.s-customer-form-wrapper');

        /**
         * @member {ShopBackendCustomerForm}
         */
        that.customer_form = ShopBackendCustomerForm.getInstance(that.$customer_form_wrapper);
        if (!that.customer_form) {
            console.error('There is not ShopBackendCustomerForm instance');
        }

        /**
         * @member {jQuery}
         */
        that.$autocomplete = $("#customer-autocomplete");

        /**
         * @member {jQuery}
         */
        that.$customer_id = $('#s-customer-id');

        /**
         * @member {jQuery}
         */
        that.$new_customer_link = $('#s-order-new-customer');

        that.contact = options.contact || {};

        that.init();

    };

    ShopBackendOrderEditorCustomerForm.prototype.init = function () {
        var that = this;

        that.initForm();

        that.rememberInitialValues();

        that.initAutocomplete();
    };

    ShopBackendOrderEditorCustomerForm.prototype.rememberInitialValues = function() {
        var that = this,
            $customer_inputs = that.$customer_inputs;
        $customer_inputs.each(function () {
            var $self = $(this),
                name = $self.attr('name'),
                val = $self.val();
            if ($self.is(':radio')) {
                if ($self.is(':checked')) {
                    that.initial_values[name] = val;
                }
            } else if ($self.is(':checkbox')) {
                that.initial_values[name] = $self.is(':checked');
            } else {
                that.initial_values[name] = val;
            }
        });
    };

    ShopBackendOrderEditorCustomerForm.prototype.resetInputValues = function() {
        var that = this,
            $customer_inputs = that.$customer_inputs;
        $customer_inputs.each(function () {
            var $self = $(this),
                name = $self.attr('name'),
                val = $self.val(),
                prev_val = that.initial_values[name] || '';
            if ($self.is(':radio')) {
                if (val === prev_val) {
                    $self.attr('checked', 'checked');
                }
            } else if ($self.is(':checkbox')) {
                if ($self.is(':checked')) {
                    $self.attr('checked', 'checked');
                } else {
                    $self.attr('checked', '');
                }
            } else {
                $self.val();
            }
        });
    };

    ShopBackendOrderEditorCustomerForm.prototype.disable = function(disabled) {
        var that = this,
            $customer_id = that.$customer_id,
            $wrapper = that.$wrapper;

        disabled = typeof disabled === 'undefined' ? true : disabled;

        $customer_id.attr('disabled', disabled);
        if (disabled) {
            $wrapper.addClass('s-opaque');
        } else {
            $wrapper.removeClass('s-opaque');
        }
    };

    /**
     * Check is form 'disabled'
     * @returns {Boolean}
     */
    ShopBackendOrderEditorCustomerForm.prototype.isDisabled = function() {
        return this.$wrapper.hasClass('s-opaque');
    };

    /**
     * Check is form 'enabled'
     * @returns {Boolean}
     */
    ShopBackendOrderEditorCustomerForm.prototype.isEnabled = function() {
        return !this.isDisabled();
    };

    ShopBackendOrderEditorCustomerForm.prototype.activate = function(activate) {
        var that = this,
            $new_customer_link = that.$new_customer_link,
            $autocomplete = that.$autocomplete;

        activate = typeof activate === 'undefined' ? true : activate;
        if (activate) {
            that.disable(false);
            $autocomplete.val('').hide(200);
            $new_customer_link.hide(200);
        } else {
            that.disable();
            $autocomplete.val('').show();
            $new_customer_link.show();
        }
    };

    ShopBackendOrderEditorCustomerForm.prototype.focusFirstEmptyInput = function() {
        var that = this,
            $customer_inputs = that.$customer_inputs,
            focused = false;

        $customer_inputs.filter('input[type=text], textarea').each(function () {
            var input = $(this);
            if (input.is(':not(:hidden)') && !this.value) {
                focused = true;
                input.focus();
                return false;
            }
        });

        if (!focused) {
            $customer_inputs.first().focus();
        }
    };

    ShopBackendOrderEditorCustomerForm.prototype.initAutocomplete = function() {
        var that = this,
            $autocomplete = that.$autocomplete;

        var looksLikePhone = function (str) {
            return parseInt(str, 10) || str.substr(0, 1) == '+' || str.indexOf('(') !== -1;
        };

        var looksLikeEmail = function (str) {
            return str.indexOf('@') !== -1;
        };

        var fillInputByTerm = function (term) {
            var selector = '[name=' + that.inputName(
                looksLikePhone(term) ? 'phone' : (
                    looksLikeEmail(term) ? 'email' : 'firstname'
                )) + ']';

            that.$customer_inputs.filter(selector).val(term);
        };

        var term = '';

        // autocomplete
        $autocomplete.autocomplete({
            source: function (request, response) {
                term = request.term;
                $.getJSON(that.options.autocomplete_url, request, function (r) {
                    (r = r || []).push({
                        label: $_('New customer'),
                        name: $_('New customer'),
                        value: 0
                    });
                    response(r);
                });
            },
            delay: 300,
            minLength: 3,
            select: function (event, ui) {
                var item = ui.item;

                if (item.value > 0) {
                    that.reloadForm({
                        id: item.value,
                        'new': true
                    });
                    return false;
                }

                // get currently selected storefront
                var storefront = that.editor.getSelectedStorefront();
                if (storefront) {
                    // concrete storefront chosen - reload form
                    // see initForm method around .bind('beforeReload')
                    that.reloadForm({}, {
                        afterReload: function () {
                            fillInputByTerm(term);
                        }
                    });
                    return false;
                }

                fillInputByTerm(term);
                
                that.$customer_id.val(0);
                that.activate();

                // autocomplete make focus for its input. That brakes out plan!
                // setTimout-hack for fix it
                setTimeout(function () {
                    that.focusFirstEmptyInput();
                }, 200);

                return false;
            },
            focus: function (event, ui) {
                this.value = ui.item.name;
                return false;
            }
        });

    };

    /**
     * Reload customer form
     * @param {Object} [data]
     * @param {Object} [callbacks]
     */
    ShopBackendOrderEditorCustomerForm.prototype.reloadForm = function(data, callbacks) {
        var that = this,
            customer_form = that.customer_form;

        callbacks = callbacks || {};

        that.disable(true);

        data = data || {};

        // Data object will be always mixed with current the context, see beforeReload
        customer_form.reloadForm(data, {
            afterReload: function (contact) {

                // after reload form inner DOM is different, need update $customer_inputs
                that.$customer_inputs = that.$wrapper.find(':input');

                // update contact info of that instance (+ some extra stuff)
                that.contact = contact || {};
                that.$customer_id.val(contact.id);

                // form must be activated and ready to inputs from user (in here will be un-disabled, so do not call disable(false))
                that.activate();

                // autocomplete make focus for its input. That brakes out plan!
                // setTimout-hack for fix it
                setTimeout(function () {
                    that.focusFirstEmptyInput();
                }, 200);

                // address can be changed - so shipping price must be recalculated and so total price
                that.editor.updateTotal();

                callbacks.afterReload && callbacks.afterReload(contact);
            }
        });
    };

    /**
     */
    ShopBackendOrderEditorCustomerForm.prototype.initForm = function () {
        var that = this,
            customer_form = that.customer_form,
            editor = that.editor,
            $wrapper = that.$wrapper;

        // Mark names fields (firstname, middlename, lastname) as having one name (class) of error
        var names = ['firstname', 'middlename', 'lastname'];
        for (var i = 0, n = names.length; i < n; i += 1) {
            var name = names[i];
            that.$customer_inputs.filter('[name=' + that.inputName(name) + ']').addClass('s-error-customer-name');
        }
        $wrapper.find('.s-error-customer-name:last').after('<em class="errormsg s-error-customer-name"></em>');

        // Link to choose a new customer mode (clean form, etc)
        that.$new_customer_link.on('click', function () {

            // not supported case - actual only for new customer more (new clean form)
            if (that.$customer_id.val() > 0) {
                return false;
            }

            var activate = function() {
                that.activate();
                that.$customer_inputs.first().focus();
            };

            editor.filterStorefrontSelector(that.getContact().type);

            // get currently selected storefront
            var storefront = editor.getSelectedStorefront();

            if (storefront) {
                // concrete storefront chosen - reload form
                // see initForm method around .bind('beforeReload')
                that.reloadForm();
            } else {
                // not concrete storefront chosen - just activate form for user
                activate();
            }

            return false;
        });


        if (that.getContact().id > 0) {
            // when there is existing contact, mark whole form as active (so no disabled inputs will be in contact form and no autocomplete)
            that.activate();
        } else {
            // when there is not contact (or some sort of "empty" contact), mark whole form as disabled, so user could only use autocomplete
            that.activate(false);
        }

        $wrapper.off('focus', ':input').on('focus', ':input', function () {
            that.disable(false);
        });

        // Before customer form will be reloaded mix-in context params
        customer_form.bind('beforeReload', function (event, data) {
            if (typeof data.id === 'undefined') {
                data.id = that.getContact().id;
            }
            data.storefront = editor.getSelectedStorefront();
        });

        // After customer form reloaded make sure that storefront selector filtered by contact type
        customer_form.bind('afterReload', function (event, contact) {
            editor.filterStorefrontSelector(contact.type);
        });

        // filter storefront
        editor.filterStorefrontSelector(that.getContact().type);


        // Update total if customer address edit
        $wrapper.on('change', ':input[name*="[address\.shipping]"]', function (e) {
            if (e.originalEvent) {
                $.shop.trace('Update total if customer address edit', [this, e]);
                editor.updateTotal();
            }
        });

        // When submit order form, disable contact form
        editor.form.submit(function () {
            that.disable(false);
        });

    };

    ShopBackendOrderEditorCustomerForm.prototype.showValidateErrors = function(customer_errors) {
        customer_errors = $.extend({}, customer_errors || {});

        if ($.isEmptyObject(customer_errors)) {
            return;
        }

        var that = this,
            customer_form = that.customer_form,
            new_html = $.trim(customer_errors.html);

        if (new_html.length > 0) {
            customer_form.updateInnerHtml(new_html);
        }
    };

    ShopBackendOrderEditorCustomerForm.prototype.inputName = function (name) {
        return '"customer[' + name + ']"';
    };


    ShopBackendOrderEditorCustomerForm.prototype.getContact = function() {
        var that = this;
        that.contact = that.contact || {};
        return that.contact;
    };

    return ShopBackendOrderEditorCustomerForm;

})(jQuery);
