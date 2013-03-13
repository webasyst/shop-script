/**
 * {literal}
 * 
 * @names printform*
 * @property {} printform_options
 * @method printformInit
 * @method printformAction
 * @method printformBlur
 * @todo flush unavailable hash (edit/delete/etc)
 */
if (typeof($) != 'undefined') {

    $.extend($.settings = $.settings || {}, {

        printform_options : {
            'null' : null,
            'loading' : 'Loading...<i class="icon16 loading"><i>'
        },
        /**
         * Init section
         * 
         * @param string tail
         */
        printformInit : function() {
            $.shop.trace('$.settings.printformInit');
            /* init settings */
            var self = this;
            $('#s-printform-content').on('submit', 'form', function() {
                return self.printformSave($(this));
            });

        },

        printform_data : {
            'null' : null

        },

        /**
         * Disable section event handlers
         */
        printformBlur : function() {
            $('#s-settings-printform-type-dialog').off('click', 'a.js-action');
            $('#s-settings-printform-type-dialog').remove();
            $('#s-settings-content').off('click', 'a.js-action');
            $('#s-settings-printform').off('change, click');
            $('#s-settings-printform-setup').off('submit');
        },

        /**
         * 
         * @param {String} tail
         */
        printformAction : function(tail) {
            $.shop.trace('$.settings.printformAction', tail);
            if (tail) {
                $('#s-printform-menu > li.selected').removeClass('selected');
                $('#s-printform-menu > li > a[href$="\/' + tail + '\/"]').parents('li').addClass('selected');
                var url = '?&module=settings&action=printformSetup&id=' + tail;
                $('#s-printform-content').show().html(this.printform_options.loading).load(url, function() {
                    //
                });
            }
        },
        printformSave : function($form) {
            $.shop.trace('d', this);
            $.post($form.attr('action'), $form.serialize(), function(response) {
                $('#s-printform-content').show().html($.settings.printform_options.loading).html(response);
            });
            return false;
        },

        printformHelper : {
            parent : $.settings,
            /**
             * Get current selected printform
             * 
             * @return int
             */
            type : function() {
                return ($.settings.path.tail) || '';
            }
        }

    });
} else {
    //
}
/**
 * {/literal}
 */
