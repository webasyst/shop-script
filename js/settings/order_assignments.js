/**
 *
 * @names assignment
 * @method assignmentsInit
 */
$.extend($.settings = $.settings || {}, {

    assignmentsInit: function (options) {
        let that = this;
        let new_rule_id = -1;
        let $form = $('#s-settings-assignment-rules-form');
        const $table_tbody = $('#s-settings-order-assignments tbody').first();

        const toggleUserAssignment = function ($div_user_search, is_user_assignment) {
            const $autocomplete = $div_user_search.find('.js-user-autocomplete');
            const $hidden_input = $div_user_search.find("input[type='hidden']");

            if (is_user_assignment) {
                $div_user_search.removeClass('hidden');
                $autocomplete.prop('disabled', false);
                $hidden_input.prop('disabled', false);
                that.initUserAutocomplete($div_user_search);
            } else {
                $div_user_search.addClass('hidden');
                $autocomplete.prop('disabled', true).val('');
                $hidden_input.prop('disabled', true).val('');
            }
        };

        // Add new rule group when user clicks the Add link
        $('#s-settings-add-rule').on('click', function (event) {
            event.preventDefault();

            const tmpl = options.new_assignment_rule
                .replace(/%%RULE_ID%%/g, new_rule_id);
            $table_tbody.prepend(tmpl);
            new_rule_id--;
        });

        (function () {
            let new_condition_id = 0;
            $table_tbody.on('change', '.add-condition-selector', function () {
                let rule_id = $(this).closest('tr').data('rule-id');
                const tmpl = options.new_condition
                    .replace(/%%RULE_ID%%/g, rule_id)
                    .replace(/%%CONDITION_ID%%/g, new_condition_id)
                    .replace(/%%RULE_NAME%%/g, $(this).find('option:selected').text());
                const $new_condition = $($.parseHTML(tmpl)[0]);
                $new_condition.find('.'+ $(this).val()).prop('disabled', false).removeClass('hidden');
                $(this).closest('.wa-select').before($new_condition);

                let $prod_search = $(this).closest('td').find('.by_sku_id').first();
                if ($(this).val() === 'by_sku_id') {
                    that.initProductAutocomplete($prod_search);
                } else {
                    $prod_search.prop('disabled', true);
                }

                $(this).val('');
                new_condition_id++;
            });

            $table_tbody.on('change', '.add-assignment-selector', function () {
                let $div_user_search = $(this).closest('td').find('.user_id');
                toggleUserAssignment($div_user_search, $(this).val() === 'user_id');

                let $div_user_group = $(this).closest('td').find('.user_low_busy');
                if ($(this).val() === 'user_low_busy') {
                    $div_user_group.removeClass('hidden');
                    $div_user_group.find('.js-user-group-selector').prop('disabled', false);
                } else {
                    $div_user_group.addClass('hidden');
                    $div_user_group.find('.js-user-group-selector').prop('disabled', true);
                }
            });
        })();


        // Link to delete a row
        $table_tbody.on('click', '.s-delete-assignment', function (event) {
            event.preventDefault();

            let that = $(this).closest('tr');
            let rule_id = that.data('rule-id');
            $.post('?module=settingsOrderAssignmentsDelete', {rule_id: rule_id}, function () {
                that.remove();
            });
        });

        $table_tbody.on('click', '.s-delete-condition', function (event) {
            event.preventDefault();

            $(this).closest('div.js-condition-block').remove();
            $('.js-form-submit').removeClass('green').addClass('yellow');
        });

        $form.on('change', function (event) {
            event.preventDefault();
            $('.js-form-submit').removeClass('green').addClass('yellow');
        });

        $form.on('submit', function (event) {
            event.preventDefault();

            const form_data = $form.serializeArray();

            $form.find(':submit').append('<span class="s-msg-after-button"><i class="fas fa-spinner fa-spin"></i></span>');
            $.post($form.attr('action'), form_data, function () {
                $.settings.dispatch('#/orderAssignments', true);
            });
        });

        // Make existing rows sortable
        $table_tbody.sortable({
            handle:'.fa-grip-vertical.assignment-rows-handle',
            onEnd: function (event) {
                let sort = 0;
                $.each($('.js-sort-rule'), function (key, value) {
                    $(value).val(sort);
                    sort++;
                });
            }
        });

        $table_tbody.find('.add-assignment-selector').each(function () {
            const $div_user_search = $(this).closest('td').find('.user_id');
            toggleUserAssignment($div_user_search, $(this).val() === 'user_id');
        });
    },

    initUserAutocomplete: function ($div_user_search) {
        const $autocomplete = $div_user_search.find('.js-user-autocomplete');
        const $hidden_input = $div_user_search.find("input[type='hidden']");

        $autocomplete.off('.orderAssignmentsUserAutocomplete').on('input.orderAssignmentsUserAutocomplete', function () {
            $hidden_input.val('');
        });

        if ($autocomplete.hasClass('ui-autocomplete-input')) {
            return;
        }

        $autocomplete.prop('disabled', false).autocomplete({
            source: '?action=autocomplete&type=user',
            delay: 300,
            minLength: 3,
            focus: function() {
                return false;
            },
            select: function (event, ui) {
                $(this).val(ui.item.name);
                $hidden_input.val(ui.item.id);
                return false;
            }
        });
    },

    initProductAutocomplete: function ($prod_search) {
        $prod_search.autocomplete({
            source: '?action=autocomplete&with_counts=1',
            delay: 300,
            minLength: 3,
            select: function (event, ui) {
                $(this).val(ui.item.value);
                $(this).siblings("input[type='hidden']").val(ui.item.sku_id);
                return false;
            }
        });
    }
});
