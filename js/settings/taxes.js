(function() {
    "use strict";

    if ($.settings && $.settings.taxesAction) {
        return;
    }

    $.extend($.settings = $.settings || {}, {

        taxes_id : null,

        // Called when SettingsTaxes.html has finished loading
        taxesInitForm : function(tax_id) {

            var form = $('#s-tax-form');
            var settings_content = $('#s-settings-content');

            this.taxes_id = (tax_id || 'new');

            // Fix the hash. Needed when new tax is just created, or user clicks sidebar link with no tax_id in URL.
            // This will not trigger redispatch since taxesPreLoad() is smart enough to detect it.
            window.location.hash = '#/taxes/' + this.taxes_id;

            // Highlight active row in inner sidebar
            settings_content.find('.s-inner-sidebar a[href="#/taxes/' + this.taxes_id + '"]').parent().addClass('selected');

            // Form submission via XHR
            form.submit(function() {
                // !!! Validation?..

                // Submit
                form.find(':submit').after('<span class="s-msg-after-button"><i class="icon16 loading"></i></span>');
                $.post(form.attr('action'), form.serialize(), function(r) {
                    settings_content.html(r);
                });

                return false;
            });

            //
            // Controller for zip-codes interface
            //
            (function() {
                var table = $('#s-tax-zip-codes-table');

                // Link to add new zip code row
                $('#s-add-zip-code-link').click(function() {
                    var tr_tmpl = table.find('.template');
                    var tr = tr_tmpl.clone().insertBefore(tr_tmpl).removeClass('template').removeClass('hidden').addClass('zip-row');
                    tr.siblings('.empty-row').hide();
                });

                // Links to delete zip-codes
                table.on('click', '.delete', function() {
                    var tr = $(this).parents('tr');
                    if (tr.is('.just-added')) {
                        tr.remove();
                        if (table.find('tbody tr.zip-row:visible').length <= 0) {
                            table.find('.empty-row').show();
                        }
                    } else {
                        tr.addClass('deleted').addClass('gray').addClass('highlighted').find('input').attr('disabled', true);
                    }
                });

                // Drag-and-drop for zip-code rows
                table.children('tbody').sortable({
                    items : ".zip-row",
                    handle : "i.sort"
                });
            })();

            //
            // Controller for tax countries interface
            //
            (function() {
                var table = $('#s-tax-regions-table');
                var select = $('#s-add-new-tax-country');

                // <select> to add new country to the list
                select.change(function() {
                    var country_iso3 = select.val();
                    if (country_iso3) {
                        select.attr('disabled', true).after('<span class="s-msg-after-button"><i class="icon16 loading"></i></span>');
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
                table.on('click', '.delete', function() {
                    var tr = $(this).parents('tr');
                    var country_iso3 = tr.attr('rel');

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
                });

                // Link to switch from one-rate mode to by-regions simple mode
                table.on('click', '.setup-by-regions-link', function() {
                    var tr = $(this).parents('tr').addClass('regions_simple').removeClass('one_rate');
                    var country_iso3 = tr.attr('rel');
                    tr.find('input:text').val('');
                    table.find('tr[rel="' + country_iso3 + '-region"]').attr('class',
                    'small s-region regions_simple' + (tr.is('.just-added') ? ' just-added' : ''));
                });

                // Link to switch from by-regions simple mode to by-regions advanced mode
                table.on('click', '.advanced-settings-link', function() {
                    var tr = $(this).parents('tr').addClass('regions_advanced').removeClass('regions_simple');
                    var country_iso3 = tr.attr('rel');
                    table.find('tr[rel="' + country_iso3 + '-region"]').attr('class',
                    'small s-region regions_advanced' + (tr.is('.just-added') ? ' just-added' : ''));
                });

                // Link to switch from by-regions advanced mode to by-regions simple mode
                table.on('click', '.back-to-simple-mode-link', function() {
                    var tr = $(this).parents('tr').addClass('regions_simple').removeClass('regions_advanced');
                    var country_iso3 = tr.attr('rel');
                    tr.find('input:text').val('');
                    var region_trs = table.find('tr[rel="' + country_iso3 + '-region"]').attr('class',
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
