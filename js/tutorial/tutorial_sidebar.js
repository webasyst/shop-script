var TutorialSidebar = ( function($) {

    TutorialSidebar = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.active = options["$active"];
        that.actions = options["$actions"];

        // VARS

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    TutorialSidebar.prototype.initClass = function() {
        var that = this;

        that.setActiveStep();
        that.setCompleteStep();
        that.setStatus();

    };

    TutorialSidebar.prototype.setActiveStep = function() {
        var that = this,
            $step_links = that.$wrapper.find('.js-step-link');

        if (that.active) {
            $step_links.each(function () {
                if ($(this).data('step') == that.active) {
                    $(this).addClass('active-step');
                    $(this).removeClass('disabled');
                } else {
                    if ($(this).hasClass('active-step')) {
                        $(this).removeClass('active-step');
                    }
                }
            });
        }

    };

    TutorialSidebar.prototype.setCompleteStep = function() {
        var that = this,
            steps = that.actions;

        if (steps) {
            $.each(steps, function(index) {
                if ($('#tutorial-actions li[data-step="'+index+'"]').length) {
                    if (steps[index].complete) {
                        $('#tutorial-actions li[data-step="'+index+'"]').removeClass('disabled').addClass('complete');
                        $('#tutorial-actions li[data-step="'+index+'"]').find('.js-status').show();
                    }else{
                        $('#tutorial-actions li[data-step="'+index+'"]').removeClass('complete');
                        $('#tutorial-actions li[data-step="'+index+'"]').find('.js-status').hide();
                    }
                }
            });
            $('.js-step-count').html($('#tutorial-actions li.complete').length);
        }

    };

    TutorialSidebar.prototype.setStatus = function() {
        var $step_link = $('#tutorial-actions .js-actions-link');

        $step_link.each(function (i, elem) {
            if ($(elem).data('step') == 'welcome' && $(elem).hasClass('complete')) {
                $('#tutorial-actions li').removeClass('disabled');
            }
        });

        //Enable Step Profit After all steps complete
        if ($('#tutorial-actions .js-actions-link.complete').length == $step_link.length) {
            $('#tutorial-actions li[data-step="profit"]').removeClass('disabled');
            $('.js-nextstep-link').show();
            $('#tutorial-actions li[data-step="profit"] a').attr('href', '?module=tutorial#/profit/');
            //js-nextstep-link
            $('.js-nextstep-link').attr('href', '?module=tutorial#/profit/');
        }else{
            $('#tutorial-actions li[data-step="profit"]').addClass('disabled');
            $('#tutorial-actions li[data-step="profit"] a').attr('href', 'javascript:void(0)');
            $('.js-nextstep-link').hide();
        }

    };

    return TutorialSidebar;

})(jQuery);