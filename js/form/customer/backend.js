/**
 * @class {ShopBackendCustomerForm}
 */
var ShopBackendCustomerForm = ( function($) {

    /**
     * NOTICE: html section of customer form can be reloaded
     * SO DON NOT BIND EVENT HANDLERS RIGHT TO CONCRETE DOM ELEMENT,
     * USE delegation: $wrapper.on('<event>', '<selector>', function() {} );
     * @param {jQuery} $wrapper
     * @param options
     * @constructor
     */
    ShopBackendCustomerForm = function($wrapper, options) {

        /**
         * @class {ShopBackendCustomerForm}
         */
        var that = this;

        that.options = options || {};

        // DOM
        that.$wrapper = $wrapper;

        // VAR
        that.dom_ns = that.$wrapper.attr('id');
        that.namespace = options.namespace || null;
        that.locales = options["locales"];
        that.fields_config = options.fields_config || {};

        // bind with this DOM current instance of FORM
        that.$wrapper.data('ShopBackendCustomerForm', that);

        // Activate right aways in DOM in Document
        // When DOM in Memory only, not activate (to prevent set handlers), need for reloadForm
        if ($.contains(document, that.$wrapper.get(0))) {
            that.activate();
        }
    };

    /**
     * All HANDLERS inits MUST BE HERE
     */
    ShopBackendCustomerForm.prototype.activate = function() {
        var that = this;

        // We can reload form (see reloadForm method), so in init MUST OFF all inner handlers
        // EXCEPT of handlers for custom events (see bind method)
        that.$wrapper.off('.' + that.dom_ns);

        that.initConfirmedCheckboxes();

        that.initContactTypeSelector();

        that.initShippingAddress();

        that.initBillingAddress();

        that.initAdditionalAddressSubfields();

        that.fixStyles();
    };

    /**
     * Release all handlers
     */
    ShopBackendCustomerForm.prototype.deactivate = function() {
        var that = this;

        // release inner handlers
        that.$wrapper.off('.' + that.dom_ns);

        // release custom events handlers
        that.unbindAll();
    };

    ShopBackendCustomerForm.prototype.getOptions = function() {
        return this.options || {};
    };

    ShopBackendCustomerForm.prototype.setOptions = function(options) {
        this.options = options || {};
    };

    ShopBackendCustomerForm.prototype.getContact = function(clone) {
        var contact = this.options.contact || {};
        return clone ? $.extend({}, contact, true) : contact;
    };

    ShopBackendCustomerForm.prototype.initConfirmedCheckboxes = function() {
        var that = this,
            $wrapper = that.$wrapper,
            $email_field = $wrapper.find('.field-email:eq(0)'),
            $phone_field = $wrapper.find('.field-phone:eq(0)'),
            $email_confirmed_control = $wrapper.find('.s-customer-email-confirmed-control'),
            $phone_confirmed_control = $wrapper.find('.s-customer-phone-confirmed-control');

        $email_field.find('.value').children().first().after($email_confirmed_control);
        $phone_field.find('.value').children().first().after($phone_confirmed_control);
    };

    ShopBackendCustomerForm.prototype.initContactTypeSelector = function() {
        var that = this,
            ns = that.dom_ns,
            $wrapper = that.$wrapper;

        // If this is a selector then we deals with new contact (not existed)
        $wrapper.on('click.' + ns, '.s-order-form-contact-type-selector-wrapper :radio', function () {
            var $input = $(this),
                $loading = that.$wrapper.find('.s-order-form-contact-type-selector-wrapper .loading'),
                type = $.trim($input.val());
            $loading.show();
            that.reloadForm({ type: type }, {
                afterReload: function () {
                    $loading.hide();
                }
            });
        })
    };

    /**
     * This method must be called after initShippingAddress and initBillingAddress
     */
    ShopBackendCustomerForm.prototype.initAdditionalAddressSubfields = function() {
        var that = this,
            fields_config = that.fields_config,
            $wrapper = that.$wrapper;

        var getControlSelector = function () {
            return '.s-customer-more-address-subfields-control';
        };

        var getControl = function () {
            return $wrapper.find(getControlSelector());
        };

        var getAddressBlockSelector = function () {
            return '.s-address-block';
        };

        var getAddressBlocks = function () {
            return $wrapper.find(getAddressBlockSelector());
        };

        getAddressBlocks().each(function () {
            var $address_block = $(this),
                $address_field = $address_block.closest('.field-address'),
                address_ext = '';

            if ($address_field.hasClass('field-address-shipping')) {
                address_ext = 'shipping';
            } else if ($address_field.hasClass('field-address-billing')) {
                address_ext = 'billing';
            }

            if (!address_ext) {
                return;
            }

            var $subfields = $address_block.find('.field').hide();
            var $last_field = $();
            var shown_fields = 0;
            $.each(fields_config['address.' + address_ext]['fields'] || {}, function (field_id) {
                $last_field = $subfields.filter('.field-address-' + field_id).show();
                shown_fields++;
            });

            // after last shown field put control link to unfold (show) other (additional) fields
            if (shown_fields != $subfields.length) {
                $last_field.after(getControl().first().clone());
            }
        });

        $wrapper.on('click', getControlSelector(), function () {
            var $link = $(this);
            // Address fields are not always inside a block with class .s-address-block
            if ($link.siblings(getAddressBlockSelector()).length) {
                $link.siblings('.field, input').appendTo($link.siblings(getAddressBlockSelector())).show();
            } else {
                $link.closest(getAddressBlockSelector()).find('.field').show();
            }
            $link.remove();
        });
    };

    ShopBackendCustomerForm.prototype.initShippingAddress = function() {
        var that = this,
            $wrapper = that.$wrapper;

        var getShippingAddressBlocks = function () {
            return $wrapper.find('.field-address-shipping').find('.value').children();
        };

        var getControlSelector = function () {
            return '.s-customer-more-shipping-addresses-control';
        };

        var getControl = function () {
            return $wrapper.find(getControlSelector());
        };

        getShippingAddressBlocks().each( function(index) {
            var $block = $(this);

            var locale = ( index > 0 ? that.locales["extra_shipping_address"] : that.locales["main_shipping_address"] );
            var $header = $("<span />").addClass("s-block-header bold").text(locale);

            $block.addClass("s-address-block").prepend($header);
        });

        var extra_addresses_present = getShippingAddressBlocks().length > 1,
            errors_in_extra_addresses_present = getShippingAddressBlocks().not(':first').find('.errormsg,.error').length > 0;

        if (extra_addresses_present && !errors_in_extra_addresses_present) {
            // hide extra addresses
            getShippingAddressBlocks().not(':first').hide();
            // relocate control in right place
            getShippingAddressBlocks().first().after(getControl());
        }

        // Toggle is actual only when there are several shipping addresses

        $wrapper.on("click", getControlSelector() + " .js-toggle-button", function(event) {
            event.preventDefault();
            getShippingAddressBlocks().not(':first').show();
            getControl().hide();
        });

    };

    ShopBackendCustomerForm.prototype.initBillingAddress = function() {
        var that = this,
            $wrapper = that.$wrapper;

        var getBillingAddressBlocks = function () {
            return $wrapper.find('.field-address-billing').find('.value').children();
        };

        var getControlSelector = function () {
            return '.s-customer-more-billing-addresses-control';
        };

        var getControl = function () {
            return $wrapper.find(getControlSelector());
        };

        getBillingAddressBlocks().each( function(index) {
            var $block = $(this);

            var locale = ( index > 0 ? that.locales["extra_billing_address"] : that.locales["main_billing_address"] );
            var $header = $("<span />").addClass("s-block-header bold").text(locale);

            $block.addClass("s-address-block").prepend($header);
        });

        var extra_addresses_present = getBillingAddressBlocks().length > 1,
            errors_in_extra_addresses_present = getBillingAddressBlocks().not(':first').find('.errormsg,.error').length > 0;

        if (extra_addresses_present && !errors_in_extra_addresses_present) {
            // hide extra addresses
            getBillingAddressBlocks().not(':first').hide();
            // relocate control in right place
            getBillingAddressBlocks().first().after(getControl());
        }

        // Toggle is actual only when there are several shipping addresses

        $wrapper.on("click", getControlSelector() + " .js-toggle-button", function(event) {
            event.preventDefault();
            getBillingAddressBlocks().not(':first').show();
            getControl().hide();
        });
    };

    ShopBackendCustomerForm.prototype.fixStyles = function() {
        var that = this,
            namespace = that.namespace,
            $wrapper = that.$wrapper;
        // reset inline system styles
        
        $wrapper.find('.field-birthday input[name="' + namespace + '[birthday][year]"]').css({
            width: '',
            minWidth: ''
        })
    };

    ShopBackendCustomerForm.prototype.updateInnerHtml = function(html) {
        // new ShopBackendCustomerForm() will be called, but instance will not be activated
        var that = this,
            $wrapper = that.$wrapper,
            $div = $('<div>').hide().appendTo('body').html(html),
            $wrp = $div.find('.s-customer-form-wrapper'),
            inst = ShopBackendCustomerForm.getInstance($wrp);

        // set new options for current (that) instance
        that.setOptions(inst.getOptions());

        // new options we had - now just update content
        $wrapper.html($div.children(0).html());

        // dont forget clean after yourself
        $div.remove();
    };

    /**
     * Load new contact form by contact ID and replace current form
     *
     * @param {Object}  [options]      - Associative array of options
     * @param {Number}  [options.id]   - Contact ID
     * @param {String}  [options.type] - Contact type, if ID passed, contact type will be get from proper contact
     * @param {Boolean} [options.new]  - Default is False. If 'new' passed, form will be reload fully, without passing any form input data
     *
     * @param {Object}   [callbacks]
     * @param {Function} [callbacks.afterReload]
     */
    ShopBackendCustomerForm.prototype.reloadForm = function (options, callbacks) {
        var that = this,
            $wrapper = that.$wrapper;

        // typecast callbacks
        callbacks = callbacks || {};

        // prepare data for ajax request
        var data = $.extend({}, options || {});

        if (!data.type) {
            data.type = that.getContact().type;
        }

        // loading to show that we are in process
        var $loading = $wrapper.find('.loading').show();

        callbacks.beforeReload && callbacks.beforeReload(data);

        $wrapper.trigger('beforeReload', [ data ]);

        // If need load new form, we don't pass form input values
        if (!options['new']) {
            var field_data = $wrapper.find(':input:not(:disabled)').serializeArray();
            var address_types = ['shipping', 'billing'];
            $.each(field_data, function (index, field) {
                if (field.name.indexOf(that.namespace)) {
                    for (var address_type of address_types) {
                        var $region = $('[name="' + that.namespace + '[address.' + address_type + '][region]"]');
                        if ($region.length > 0 && $region.data('country') != "") {
                            data[that.namespace + '[address.' + address_type + '][data][country]'] = $region.data('country');
                        }
                    }
                }
                data[field.name] = field.value;
            });
        }
        $.post('?action=customerForm', data, function (html) {

            that.updateInnerHtml(html);

            // hide loading
            $loading.hide();

            var contact = that.getContact(true);

            callbacks.afterReload && callbacks.afterReload(contact);

            // pass in trigger clone of contact info, to prevent outer influence
            $wrapper.trigger('afterReload', [ contact ]);
        });
    };

    ShopBackendCustomerForm.prototype.bind = function(event, handler) {
        var that = this,
            ns = that.dom_ns,
            $wrapper = that.$wrapper;
        $wrapper.on(event + '.custom' + ns, handler);
    };

    ShopBackendCustomerForm.prototype.unbindAll = function() {
        var that = this,
            ns = that.dom_ns,
            $wrapper = that.$wrapper;
        $wrapper.off(event + '.custom' + ns);
    };

    /**
     * @param {jQuery} $wrapper
     * @returns ShopBackendCustomerForm|null
     */
    ShopBackendCustomerForm.getInstance = function ($wrapper) {
        if ($wrapper.data('ShopBackendCustomerForm') instanceof ShopBackendCustomerForm) {
            return $wrapper.data('ShopBackendCustomerForm');
        } else {
            return null;
        }
    };

    return ShopBackendCustomerForm;

})(jQuery);
