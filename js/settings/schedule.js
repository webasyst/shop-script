ShopSettingsSchedule = (function ($) {
    return class {
        constructor($wrapper, options) {
            // DOM
            this.$document = $(document);
            this.$form = $wrapper;
            this.$submitButton = this.$form.find('.js-submit-button');
            this.$submitOkIcon = this.$submitButton.find('.js-submit-ok')
            this.$submitSpinner = this.$submitButton.find('.js-submit-spinner');

            this.$week_wrapper = this.$form.find('.js-week-wrapper');
            this.$day_add = this.$form.find('.js-add-day');
            this.$day_remove = this.$form.find('.js-day-remove');

            // VARS
            this.options = options;

            // DYNAMIC VARS
            this.is_locked = false;

            // INIT
            this.bindEvents();
        }

        bindEvents() {
            this.$week_wrapper.on('change', '.js-work', $.proxy(this.initWeek, this));
            this.$form.on('change', '.js-datepicker', $.proxy(this.formatDate, this));
            this.$submitButton.on('click', $.proxy(this.submitForm, this));
            this.$form.on('change', $.proxy(this.detectFormChange, this));
            this.$day_add.on('click', $.proxy(this.addExtraDay, this));
            this.$form.on('click', '.js-day-remove', $.proxy(this.removeExtraDay, this));
        }

        initWeek(event) {
            const $day_wrapper = $(event.target).closest('.js-day-wrapper');

            if (event.target.checked) {
                $day_wrapper.addClass('worked');
                $day_wrapper.find('.js-time').each(function () {
                    $(this).prop('disabled', false);
                });
            } else {
                $day_wrapper.removeClass('worked').find('.js-time').val('').prop('disabled', true);
            }
        }

        addExtraDay(event) {
            event.preventDefault();

            const wrapper_class = (event.currentTarget.dataset.type === 'workday') ? '.js-extra-workdays-wrapper' : '.js-extra-weekends-wrapper';
            const $extra_wrapper = this.$form.find(wrapper_class);
            const $days_list = $extra_wrapper.find('.js-days-list');

            const template = (event.currentTarget.dataset.type === 'workday') ? this.options.extra_workday_template : this.options.extra_weekend_template;
            const $template = $(template).clone();

            $extra_wrapper.find('thead').show();
            $days_list.append($template);
        }

        removeExtraDay(event) {
            const $parentTable = $(event.target).closest('table');

            $(event.target).closest('tr').remove();

            if (!$parentTable.find('tbody tr').length) {
                $parentTable.find('thead').hide();
            }
        }

        formatDate(event) {
            const currentLoc = this.options.lang.replace('_', '-');
            const dateValue = new Date(event.target.valueAsDate);
            const localizedDate = dateValue.toLocaleDateString(currentLoc);
            $(event.target).siblings('input').val(localizedDate);
        }

        submitForm(event) {
            event.preventDefault();

            if (this.is_locked) {
                return;
            }

            this.is_locked = true;

            this.$submitButton.prop('disabled', true);
            this.$submitSpinner.removeClass('hidden');

            this.$form.find('.js-submit-error').remove();
            this.$form.find('.state-error').removeClass('state-error wa-animation-swing');

            this.setExtraWorkdayNames();
            this.setExtraWeekendNames();

            const data = this.$form.serialize();

            $.post(this.options.api.save, data, (res) => {
                this.is_locked = false;

                this.$submitButton.prop('disabled', false);
                this.$submitButton.removeClass('yellow').addClass('green');
                this.$submitSpinner.addClass('hidden');

                if (res.status !== 'ok') {
                    if (!res.errors) {
                        return;
                    }

                    $.each(res.errors, (i, error) => {
                        if (error.field) {
                            return;
                        }

                        this.fieldError(error);
                    });

                    return;
                }

                this.$submitOkIcon.removeClass('hidden');
                setTimeout(() => {
                    this.$submitOkIcon.addClass('hidden');
                },2000);
            });
        }

        detectFormChange() {
            this.$submitButton.removeClass('green').addClass('yellow');
        }

        setExtraWorkdayNames() {
            const $table = this.$form.find('.js-extra-workdays-wrapper');

            $table.find('.js-day-wrapper').each(function (i, tr) {
                const $tr = $(tr);

                $tr.find('input[data-name]').each(function () {
                    $(this).attr('name', 'data[extra_workdays]['+ i +']['+ $(this).data('name') +']');
                });
            });
        }

        setExtraWeekendNames() {
            const $table = this.$form.find('.js-extra-weekends-wrapper');

            $table.find('.js-day-wrapper').each(function (i, tr) {
                const $tr = $(tr);

                $tr.find('.js-extra-weekend').each(function () {
                    $(this).attr('name', 'data[extra_weekends]['+ i +']');
                });
            });
        }

        fieldError(error) {
            let $field = this.$form.find(`*[name="data${error.field}"]`);

            if (!$field.length) {
                $field = this.$form.find(`*[data-block-name="${error.field}"]`);

                if (error.message) {
                    $field.after(`<div class="state-error js-submit-error">${error.message}</div>`);
                }
            }

            if (error.interrelated_field) {
                this.$form.find('input[name="data'+ error.interrelated_field +'"]').addClass('state-error wa-animation-swing');
            }

            $field.addClass('state-error wa-animation-swing');

            console.trace(error);
        }
    }
})(jQuery);
