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

            var data = $form.serialize();
            var url = $form.attr('action');
            $.shop.trace('$.settings.resetSubmit', [url, data]);

            $form.find(':submit:first').after('<i class="icon16 loading"></i>');
            $form.find(':input').attr('disabled', true);
            this.resetBlur();
            $.ajax({
                'url' : url,
                'data' : data,
                'type' : 'POST',
                'dataType': 'html',
                'success' : function(response, textStatus, jqXHR) {
                    $.shop.trace('$.settings.resetSubmit response', [textStatus, response]);

                    var data = false;
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
