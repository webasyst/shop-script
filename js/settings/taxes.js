(function() {
    "use strict";

    if ($.settings && $.settings.taxesAction) {
        return;
    }

    $.extend($.settings = $.settings || {}, {

        taxes_id : null,

        // Called when SettingsTaxes.html has finished loading
        taxesInitForm : function(tax_id) {

            const form = $('#s-tax-form');
            const settings_content = $('#s-settings-content');
            const submitButton = form.find('.js-form-submit');
            const dropdown = form.find('.js-tax-dropdown');
            const formChanged = () => submitButton.removeClass('green').addClass('yellow');

            this.taxes_id = (tax_id || 'new');

            // Fix the hash. Needed when new tax is just created, or user clicks sidebar link with no tax_id in URL.
            // This will not trigger redispatch since taxesPreLoad() is smart enough to detect it.
            window.location.hash = '#/taxes/' + this.taxes_id;

            dropdown.waDropdown();

            $(':input').on('input', formChanged);
            form.on('change', formChanged);

            // Form submission via XHR
            form.on('submit', function(event) {
                event.preventDefault();

                submitButton.append('<span class="s-msg-after-button"><i class="fas fa-spinner fa-spin custom-ml-4"></i></span>');

                $.post(form.attr('action'), form.serialize(), function(r) {
                    settings_content.html(r);
                });
            });

            //
            // Controller for zip-codes interface
            //
            (function() {
                const table = $('#s-tax-zip-codes-table');

                // Link to add new zip code row
                $('.js-add-zip-code-link').on('click', function(event) {
                    event.preventDefault();

                    const tr_tmpl = table.find('.template');
                    const tr = tr_tmpl.clone().insertBefore(tr_tmpl).removeClass('template').removeClass('hidden').addClass('zip-row');
                    tr.siblings('.empty-row').hide();
                });

                // Links to delete zip-codes
                table.on('click', '.delete', function(event) {
                    event.preventDefault();

                    const tr = $(this).parents('tr');

                    if (tr.is('.just-added')) {
                        tr.remove();
                        if (table.find('tbody tr.zip-row:visible').length <= 0) {
                            table.find('.empty-row').show();
                        }
                    } else {
                        tr.addClass('deleted').addClass('gray').addClass('highlighted').find('input').attr('disabled', true);
                    }
                    formChanged();
                });

                // Drag-and-drop for zip-code rows
                table.children('tbody').sortable({
                    items : ".zip-row",
                    handle : ".sort"
                });
            })();

            //
            // Controller for tax countries interface
            //
            (function() {
                const table = $('#s-tax-regions-table');
                const select = $('#s-add-new-tax-country');

                // <select> to add new country to the list
                select.on('change', function() {
                    const country_iso3 = select.val();

                    if (country_iso3) {
                        select.attr('disabled', true).after('<span class="s-msg-after-button"><i class="fas fa-spinner fa-spin"></i></span>');

                        $.get('?module=settings&action=taxesCountry', {
                            country : country_iso3
                        }, function(r) {
                            table.find('tr[rel^="' + country_iso3 + '"]').remove();
                            $('<table></table>').html(r).find('tr').insertBefore(table.find('.empty-row'));
                            select.find('option[value="' + country_iso3 + '"], option[value="%AL"]').attr('disabled', true);
                            select.val('').attr('disabled', false).siblings('.s-msg-after-button').remove();
                            table.find('.empty-row').hide();
                        });
                    }
                });

                // Links to remove countries from the list
                table.on('click', '.delete', function(event) {
                    event.preventDefault();

                    const tr = $(this).parents('tr');
                    const country_iso3 = tr.attr('rel');

                    // Remove regions
                    table.find('tr[rel="' + country_iso3 + '-region"]').remove();

                    // Allow to add the country again
                    select.find('option[value="' + country_iso3 + '"]').attr('disabled', false);

                    // Remove country <tr> or mark it as deleted
                    if (tr.is('.just-added')) {
                        tr.remove();

                        if (table.find('tbody tr.s-country').length <= 0) {
                            table.find('.empty-row').show();
                        }
                    } else {
                        tr.addClass('deleted').addClass('gray').addClass('highlighted');

                        setTimeout(function() {
                            // remove inputs so that serialized form data does not include them
                            tr.find('td > *').filter(':hidden').remove();
                        }, 0);
                    }

                    // Enable 'All countries' shorthand option again, if there are no countries in list
                    if (table.find('tbody tr.s-country:not(.deleted)').length <= 0) {
                        select.find('option[value="%AL"]').attr('disabled', false);
                    }
                    formChanged();
                });

                // Link to switch from one-rate mode to by-regions simple mode
                table.on('click', '.setup-by-regions-link', function(event) {
                    event.preventDefault();

                    const tr = $(this).parents('tr').addClass('regions_simple').removeClass('one_rate');
                    const country_iso3 = tr.attr('rel');

                    tr.find('input:text').val('');
                    table.find('tr[rel="' + country_iso3 + '-region"]').attr('class',
                    'small s-region regions_simple' + (tr.is('.just-added') ? ' just-added' : ''));
                });

                // Link to switch from by-regions simple mode to by-regions advanced mode
                table.on('click', '.advanced-settings-link', function(event) {
                    event.preventDefault();

                    const tr = $(this).parents('tr').addClass('regions_advanced').removeClass('regions_simple');
                    const country_iso3 = tr.attr('rel');

                    table.find('tr[rel="' + country_iso3 + '-region"]').attr('class',
                    'small s-region regions_advanced' + (tr.is('.just-added') ? ' just-added' : ''));
                });

                // Link to switch from by-regions advanced mode to by-regions simple mode
                table.on('click', '.back-to-simple-mode-link', function(event) {
                    event.preventDefault();

                    const tr = $(this).parents('tr').addClass('regions_simple').removeClass('regions_advanced');
                    const country_iso3 = tr.attr('rel');

                    tr.find('input:text').val('');
                    const region_trs = table.find('tr[rel="' + country_iso3 + '-region"]').attr('class',
                                'small s-region regions_simple' + (tr.is('.just-added') ? ' just-added' : ''));
                    region_trs.find('select, .s-tax-name').val('').filter('select').val('+');
                });
            })();
        },

        taxesAction : function(tail) {
            // Not used. See taxesInitForm()
        },

        // Called when user leaves the tax settings page or goes from one tax to another
        taxesBlur : function(tail) {
            this.taxes_id = null;
        }
    });
})();
