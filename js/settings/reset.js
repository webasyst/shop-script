/**
 * {literal}
 *
 * @names reset*
 * @property {} reset_options
 * @method resetInit
 * @method resetAction
 * @method resetBlur
 * @todo flush unavailable hash (edit/delete/etc)
 */
if (typeof($) != 'undefined') {

    $.extend($.settings = $.settings || {}, {

        reset_options : {
            'confirm' : false
        },

        reset_data : {},

        /**
         * Init section
         *
         */
        resetInit : function() {
            $.shop.trace('$.settings.resetInit');
            /* init settings */
            var self = this;
            $('#s-settings-content').on('submit', '#s-settings-reset', function() {
                return self.resetSubmit($(this));
            });
        },

        resetBlur : function() {
            $('#s-settings-content').off('submit', '#s-settings-reset');
        },

        resetAction : function() {

        },
        resetSubmit : function($form) {
            const data = $form.serialize();
            const url = $form.attr('action');
            $.shop.trace('$.settings.resetSubmit', [url, data]);

            $form.find(':submit:first').append('<i class="fas fa-spinner fa-spin custom-ml-4"></i>');
            $form.find(':input').attr('disabled', true);
            this.resetBlur();
            $.ajax({
                'url' : url,
                'data' : data,
                'type' : 'POST',
                'dataType': 'html',
                'success' : function(response, textStatus, jqXHR) {
                    //remove tutorial_step_link it is used for onboarding
                    const step_link = localStorage.getItem('tutorial_step_link');

                    if (step_link) {
                        localStorage.removeItem('tutorial_step_link');
                    }

                    $.shop.trace('$.settings.resetSubmit response', [textStatus, response]);

                    let data = false;
                    try {
                        data = $.parseJSON(response);
                    } catch (e) {

                    }
                    if (data && data.redirect) {
                        window.location = data.redirect;
                    } else {
                        $('#s-settings-content').html(response);
                    }
                }
            });
            return false;
        }

    });
} else {
    //
}
/**
 * {/literal}
 */
