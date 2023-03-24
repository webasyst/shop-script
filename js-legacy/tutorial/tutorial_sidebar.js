var TutorialSidebar = ( function($) {

    var disabled_class = "is-disabled",
        complete_class = "is-complete",
        active_class = "is-active";

    TutorialSidebar = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.active_step = options["active_step"];
        that.actions = options["$actions"];

        // VARS

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    TutorialSidebar.prototype.initClass = function() {
        var that = this;

        var $tutorial_menu_item = $("#js-menu-tutorial-item .s-tutorial-quick-start");
        if ($tutorial_menu_item.length) { $tutorial_menu_item.addClass("is-active"); }

        that.setActiveStep();
        that.setCompleteStep();
        that.setStatus();

    };

    TutorialSidebar.prototype.setActiveStep = function() {
        var that = this,
            $step_links = that.$wrapper.find('.js-step-link');

        if (that.active_step) {
            $step_links.each(function () {
                var $link = $(this);

                if ($link.data('step') === that.active_step) {
                    $link
                        .addClass(active_class)
                        .removeClass(disabled_class);
                } else {
                    if ($link.hasClass(active_class)) {
                        $link.removeClass(active_class);
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
                var $step = that.$wrapper.find('li[data-step="'+index+'"]');
                if ($step.length) {
                    if (steps[index].complete) {
                        $step
                            .removeClass(disabled_class)
                            .addClass(complete_class);

                    } else {
                        $step
                            .removeClass(complete_class);
                    }
                }
            });

            $('.js-step-count').html( $('#tutorial-actions li.' + complete_class).length );
        }

    };

    TutorialSidebar.prototype.setStatus = function() {
        var $step_link = $('#tutorial-actions .js-actions-link');

        $step_link.each(function (i, elem) {
            if ($(elem).data('step') == 'welcome' && $(elem).hasClass(complete_class)) {
                $('#tutorial-actions li').removeClass(disabled_class);
            }
        });

        //Enable Step Profit After all steps complete
        if ($('#tutorial-actions .js-actions-link.' + complete_class).length == $step_link.length) {
            $('#tutorial-actions li[data-step="profit"]').removeClass(disabled_class);
            $('.js-nextstep-link').show();
            $('#tutorial-actions li[data-step="profit"] a').attr('href', '?module=tutorial#/profit/');
            //js-nextstep-link
            $('.js-nextstep-link').attr('href', '?module=tutorial#/profit/');
        }else{
            $('#tutorial-actions li[data-step="profit"]').addClass(disabled_class);
            $('#tutorial-actions li[data-step="profit"] a').attr('href', 'javascript:void(0)');
            $('.js-nextstep-link').hide();
        }

    };

    return TutorialSidebar;

})(jQuery);