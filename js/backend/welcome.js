/**
 * 
 */

(function($) {
    $('.js-bold :radio:checked').each(function() {
        $(this).parents('.value').addClass('bold');
    });
    $('.js-bold :radio, .js-bold :checkbox').change(function() {
        if ($(this).is(':radio')) {
            $(':radio[name="' + $(this).attr('name').replace(/([\:\@\[\]])/, '\\$1') + '"]').each(function() {
                $(this).parents('.value').removeClass('bold');
            })
        }
        if ($(this).is(':checked')) {
            $(this).parents('.value').addClass('bold');
        } else {
            $(this).parents('.value').removeClass('bold');
        }
    });
})(jQuery);