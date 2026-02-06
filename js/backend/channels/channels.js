/**
 * Controller for sales channel settings form.
 * Should be usable both for generic version (generic_form.include.html)
 * as well as custom-designed pages.
 */
window.SalesChannelForm = (function() { "use strict";

    var SalesChannelForm = function(options) {
        var that = this;
        that.$form = options.$form;
        that.save_url = options.save_url || that.$form.attr('action') || '?module=channels&action=save';
        that.skip_display_errors = options.skip_display_errors || false;
        that.$place_for_errors = options.$place_for_errors || null;
        if (!that.$place_for_errors || !that.$place_for_errors.length) {
            that.$place_for_errors = this.getDefaultPlaceForErrors();
        }

        that.init();
    };

    SalesChannelForm.prototype.init = function() {
        var that = this;

        // when the form is submitted for any reason, class runs validation + submit
        // highlights validation errors if came from the server
        // throws events on that.$form
        that.$form.submit(function(submit_event) {
            submit_event.preventDefault();

            // event that saving triggered (e.g. for loading indicator)
            var e = that.trigger('save_init');
            if (e.isDefaultPrevented()) {
                return;
            }
            if (!that.skip_display_errors) {
                that.clearValidationErrors();
            }

            // event allowing to cancel submit (e.g. for validation)
            var e = that.trigger('save_validate');
            if (e.isDefaultPrevented()) {
                return;
            }

            // event that saving initialized (e.g. for loading indicator)
            that.trigger('save_request');

            $.post(that.save_url, that.$form.serialize(), 'json').then(function(r) {
                if (r?.status == 'ok') {
                    that.trigger('save_success', { result: r.data });
                } else if (r?.errors) {
                    if (!that.skip_display_errors) {
                        that.showValidationErrors(r.errors);
                    }
                    that.trigger('save_fail', { errors: r.errors });
                }
            }, function() {
                console.error('Unable to save sales channel data', arguments);
                that.trigger('save_fail', { server_error: arguments });
            });

        });
    };

    SalesChannelForm.prototype.trigger = function(name, data) {
        data = data || {};
        data.controller = this;
        var evt = $.Event(name, data);
        this.$form.trigger(evt, [data]);
        return evt;
    };

    SalesChannelForm.prototype.clearValidationErrors = function() {
        this.$form.find('.state-error,.state-caution').removeClass('state-error state-caution');
        this.$form.find('.state-error-hint,.state-caution-hint').remove();
    };

    SalesChannelForm.prototype.showValidationErrors = function(errors) {
        var that = this;
        errors?.forEach((e, i) => {
            var $field;
            if (e.field) {
                $field = that.$form.find('[name="'+e.field+'"]').addClass('state-error');
                if (i === 0) {
                    that.scrollToEl($field);
                }
            }
            if (e.error_description) {
                var $error = $('<div class="state-error-hint"></div>').html(e.error_description);
                if ($field && $field.length && $field.is(':visible')) {
                    $field.after($error);
                } else {
                    that.$place_for_errors.append($error);
                }
            }
        });
    };

    SalesChannelForm.prototype.getDefaultPlaceForErrors = function() {
        var $result = this.$form.find(':submit').last().parent();
        if ($result.length) {
            return $result;
        }
        return this.$form;
    };

    SalesChannelForm.prototype.scrollToEl = function($field) {
        if (!$field?.length) return;
        $('html, body').animate({
            scrollTop: $field.offset().top - ($('#wa-header').height() || 0) - 30
        }, 500);
    };

    return SalesChannelForm;
})();
