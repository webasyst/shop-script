var ShopSettingsSchedule = ( function($) {

    ShopSettingsSchedule = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find('form');
        that.$button = that.$form.find('.js-submit-button');
        that.$loading = that.$form.find('.js-loading');

        // VARS
        that.extra_workday_template = options["extra_workday_template"];
        that.extra_weekend_template = options["extra_weekend_template"];
        that.date_format = options['date_format'];

        // DYNAMIC VARS
        that.extra_dates = [];
        that.is_locked = false;

        // INIT
        that.initClass();
    };

    ShopSettingsSchedule.prototype.initClass = function() {
        var that = this;

        //
        that.initWeek();
        //
        that.initExtra('workday');
        //
        that.initExtra('weekend');
        //
        that.initSubmit();
    };

    ShopSettingsSchedule.prototype.initWeek = function() {
        var that = this,
            $week_wrapper = that.$wrapper.find('.js-week-wrapper');

        $week_wrapper.on('change', '.js-work', function () {
            var $work = $(this),
                $day_wrapper = $work.parents('.js-day-wrapper');

            if (this.checked) {
                $day_wrapper.addClass('worked');
                $day_wrapper.find('.js-time').each(function () {
                    $(this).prop('disabled', false).attr('placeholder', $(this).data('placeholder'));
                });
            } else {
                $day_wrapper.removeClass('worked').find('.js-time').val('').prop('disabled', true).removeAttr('placeholder');
            }
        });
    };

    ShopSettingsSchedule.prototype.initExtra = function(extra_type) {
        var that = this,
            wrapper_class = (extra_type === 'workday') ? '.js-extra-workdays-wrapper' : '.js-extra-weekends-wrapper',
            $extra_wrapper = that.$wrapper.find(wrapper_class),
            $days_list = $extra_wrapper.find('.js-days-list'),
            $add_day = $extra_wrapper.find('.js-add-day');

        initDatepickers();

        // Add
        $add_day.on('click', function () {
            var template = (extra_type === 'workday') ? that.extra_workday_template : that.extra_weekend_template,
                $template = $(template).clone();

            $extra_wrapper.find('thead').show();
            $days_list.append($template);
            initDatepickers();
        });

        // Remove
        $extra_wrapper.on('click', '.js-remove', function () {
            $(this).parents('tr').remove();
            if (!$days_list.find('tr').length) {
                $extra_wrapper.find('thead').hide();
            }
            initDatepickers();
        });

        that.$wrapper.on('change', '.js-datepicker', function () {
            initDatepickers();
        });

        function initDatepickers() {
            that.$wrapper.find('.js-datepicker').each(function () {
                var $input = $(this);
                $input.datepicker({
                    dateFormat: that.date_format,
                    beforeShowDay: function(date){
                        var string = $.datepicker.formatDate(that.date_format, date);
                        return [ that.extra_dates.indexOf(string) == -1 ]
                    },
                    create: parseDates()
                });
            });
            $('#ui-datepicker-div').hide();
        }

        function parseDates() {
            that.extra_dates = [];
            that.$wrapper.find('.js-datepicker').each(function () {
                that.extra_dates.push($(this).val());
            });
        }
    };

    ShopSettingsSchedule.prototype.initSubmit = function() {
        var that = this;

        that.$form.on('submit', function (e) {
            e.preventDefault();
            if (that.is_locked) {
                return;
            }
            that.is_locked = true;
            that.$button.prop('disabled', true);
            that.$loading.removeClass('yes').removeClass('no').addClass('loading').show();
            that.$wrapper.find('.js-submit-error').remove();
            that.$wrapper.find('.error').removeClass('error shake animated');

            setNames();

            var href = that.$form.attr('action'),
                data = that.$form.serialize();

            $.post(href, data, function (res) {
                if (res.status === 'ok') {
                    that.$button.removeClass('yellow').addClass('green');
                    that.$loading.removeClass('loading').addClass('yes');
                    setTimeout(function(){
                        that.$loading.hide();
                    },2000);
                } else {
                    if (res.errors) {
                        $.each(res.errors, function (i, error) {
                            if (error.field) {
                                fieldError(error);
                            }
                        });
                    }
                    that.$loading.removeClass('loading').addClass('no');
                    setTimeout(function(){
                        that.$loading.hide();
                    },2000);
                }
                that.is_locked = false;
                that.$button.prop('disabled', false);
            });

            function fieldError(error) {
                var $field = that.$form.find('*[name="data'+ error.field +'"]');

                if (!$field.length) {
                    $field = that.$form.find('*[data-block-name="'+ error.field +'"]');
                    if (error.message) {
                        $field.after('<div class="js-submit-error" style="color: red;">' + error.message + '</div>');
                    }
                }

                if (error.interrelated_field) {
                    var $interrelated_field = that.$form.find('input[name="data'+ error.interrelated_field +'"]');
                    $interrelated_field.addClass('error shake animated');
                }

                $field.addClass('error shake animated');

                console.log(error);
            }
        });

        that.$form.on('input', function () {
            that.$button.removeClass('green').addClass('yellow');
        });

        function setNames() {
            setExtraWorkdayNames();
            setExtraWeekendNames();
        }

        function setExtraWorkdayNames() {
            var $table = that.$wrapper.find('.js-extra-workdays-wrapper');

            $table.find('.js-day-wrapper').each(function (i, tr) {
                var $tr = $(tr);
                $tr.find('input[data-name]').each(function () {
                    $(this).attr('name', 'data[extra_workdays]['+ i +']['+ $(this).data('name') +']');
                })
            });
        }

        function setExtraWeekendNames() {
            var $table = that.$wrapper.find('.js-extra-weekends-wrapper');

            $table.find('.js-day-wrapper').each(function (i, tr) {
                var $tr = $(tr);
                $tr.find('.js-extra-weekend').each(function () {
                    $(this).attr('name', 'data[extra_weekends]['+ i +']');
                })
            });
        }
    };

    return ShopSettingsSchedule;

})(jQuery);