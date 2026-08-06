(function ($) { "use strict";
    $.storage = new $.store();
    $.customers = {
        options: {},
        locales: {},

        // last list view user has visited: {title: "...", hash: "..."}
        lastView: null,

        init: function (options) {
            $(document).off("wa_loaded.sidebar");

            var that = this;
            that.options = options;
            if (typeof($.History) != "undefined") {
                $.History.bind(function () {
                    that.dispatch();
                });
            }
            $.wa.errorHandler = function (xhr) {
                if ((xhr.status === 403) || (xhr.status === 404) ) {
                    var text = $(xhr.responseText);
                    if (text.find('.dialog-content').length) {
                        text = $('<div class="block double-padded"></div>').append(text.find('.dialog-content'));

                    } else {
                        text = $('<div class="block double-padded"></div>').append(text.find(':not(style)'));
                    }
                    $("#s-content").empty().append(text);
                    return false;
                }
                return true;
            };
            var hash = this.getHash();
            if (hash === '#/' || !hash) {
                this.dispatch();
            } else {
                $.wa.setHash(hash);
            }
            this.lastView = $.storage.get('shop/customers/lastview') || {
                title: '',
                hash: ''
            };

            $(document).on('wa_loaded', () => {
                this.initMobileSidebar();
                this.initMassActions();
                this.initSearch();
                this.initLoyalty();
            });

        },

        /** Global customers search above content block */
        initSearch: function() {
            const search_field = $('#s-customers-search');
            if (search_field.length) {
                const hash = $.customers.getHash();
                let m = hash.match(/^(?:#\/)?search\/([\s\S]*)/);
                if (m) {
                    m[1] = m[1].replace(/(\/)+$/, '');
                    m = m[1].match(/^(?:phone|email|name\|email|email\|name)\*=([\s\S]*)/);
                    if (m) {
                        let val = m[1];
                        try {
                            val = decodeURIComponent(val);
                        } catch (e) {}
                        search_field.val(val);
                    }
                }

                const search = (event) => {
                    if (event) event.preventDefault();
                    let q = search_field.val().trim();
                    let query = '';

                    if (q.match(/^\+*[0-9\s\-\(\)]+$/)) {
                        try {
                            q = encodeURIComponent(q);
                        } catch (e) {
                        }

                        query = 'phone|id_code*=' + q;
                    } else if (q.indexOf('@') !== -1) {
                        query = 'email*=' + q;
                    } else {
                        query = 'email|name*=' + q;
                    }
                    location.hash = '#/search/' + query;
                };

                // Test if HTML5 search event is supported
                let isSupported = ('onsearch' in search_field[0]); // works for everyone except firefox
                if (!isSupported) {
                    // firefox testing
                    search_field[0].setAttribute('onsearch', 'return;');
                    isSupported = typeof search_field[0]['onsearch'] == 'function';
                }

                // Use HTML5 search event if suppotred. Otherwise fallback to keydown.
                if (isSupported) {
                    search_field.unbind('search').bind('search', search);
                } else {
                    search_field.unbind('keydown').bind('keydown', function(event) {
                        if (event.keyCode == 13 || event.keyCode == 10) {
                            return search.call(this);
                        }
                    });

                }

                // Use jQuery autocomplete to show suggestions.
                search_field.autocomplete({
                    source: '?action=autocomplete&type=customer',
                    minLength: 3,
                    delay: 300,
                    select: function(event, ui) {
                        if (ui.item.autocomplete_item_type === 'coupon') {
                            $.wa.setHash('#/search/app.coupon=' + ui.item.id);
                        } else if (ui.item.autocomplete_item_type === 'shipping') {
                            $.wa.setHash('#/search/app.shipment_method=' + ui.item.id);
                        } else if (ui.item.autocomplete_item_type === 'payment') {
                            $.wa.setHash('#/search/app.payment_method=' + ui.item.id);
                        } else if (ui.item.autocomplete_item_type === 'city') {
                            $.wa.setHash('#/search/contact_info.address.city=' + ui.item.value);
                        } else if (ui.item.autocomplete_item_type === 'region') {
                            $.wa.setHash('#/search/contact_info.address.region=' + ui.item.value);
                        } else if (ui.item.autocomplete_item_type === 'country') {
                            $.wa.setHash('#/search/contact_info.address.country=' + ui.item.value);
                        } else {
                            $.wa.setHash('#/id/' + ui.item.id);
                        }
                        search_field.val('');
                        return false;
                    }
                }).bind('keydown', function(e) {
                    if (e.keyCode == 13) {
                        var self = $(this);
                        setTimeout(function() {
                            self.autocomplete("close");
                        }, 300);
                    }
                });
            }
        },

        initLoyalty: function() {
            $('.js-loyalty-card').each((index, card) => {
                const $card = $(card);
                if ($card.data('loyalty-initialized')) {
                    return;
                }
                $card.data('loyalty-initialized', true);

                const customerId = parseInt($card.data('customer-id'), 10) || 0;
                const initialIdCode = this.normalizeLoyaltyCodeValue($card.data('id-code'));
                const $view = $card.find('.js-loyalty-view');
                const $empty = $card.find('.js-loyalty-empty');
                const $form = $card.find('.js-loyalty-form');
                const $input = $card.find('.js-loyalty-input');
                const $error = $card.find('.js-loyalty-error');
                const $success = $card.find('.js-loyalty-success');
                const $loading = $card.find('.js-loyalty-loading');
                const $save = $card.find('.js-loyalty-save');
                const $cancel = $card.find('.js-loyalty-cancel');
                const $code = $card.find('.js-loyalty-code');
                const $result = $card.find('.js-loyalty-result');
                const $barcode = $card.find('.js-loyalty-barcode');
                const $barcodeWrapper = $card.find('.js-loyalty-barcode-wrapper');
                const $barcodeText = $card.find('.js-loyalty-barcode-text');
                const $barcodePlaceholder = $card.find('.js-loyalty-barcode-placeholder');
                const invalidCodeText = String($.customers.locales.invalid_loyalty_code || '');
                const barcodeUnavailableText = String($.customers.locales.loyalty_barcode_unavailable || '');
                const barcodePreviewText = String($.customers.locales.loyalty_barcode_preview || '');
                const saveFailedText = String($.customers.locales.loyalty_save_failed || '');
                const getErrorText = (errors) => {
                    if ($.isArray(errors)) {
                        return String(errors[0] || '');
                    }
                    return String(errors || '');
                };

                const renderBarcode = (idCode, options) => {
                    const normalized = $.customers.normalizeLoyaltyCodeValue(idCode);
                    const ean13 = $.customers.getLoyaltyEan13(normalized);
                    const placeholderText = options && options.placeholderText ? options.placeholderText : barcodeUnavailableText;

                    if (ean13) {
                        $barcode.html($.customers.renderEan13Svg(ean13));
                        $barcodeText.text(ean13);
                        $barcodeWrapper.show();
                        $barcodePlaceholder.hide().text('');
                    } else {
                        $barcode.empty();
                        $barcodeText.text('');
                        $barcodeWrapper.hide();
                        $barcodePlaceholder.text(placeholderText).show();
                    }
                };

                const renderView = (idCode) => {
                    const normalized = $.customers.normalizeLoyaltyCodeValue(idCode);
                    const formatted = $.customers.formatLoyaltyCode(normalized);
                    if (formatted) {
                        $code.text(formatted);
                    }
                    renderBarcode(normalized);
                };

                const openEditor = (idCode) => {
                    const normalized = $.customers.normalizeLoyaltyCodeValue(idCode);
                    $empty.hide();
                    $view.show();
                    $result.hide();
                    $error.hide().text('');
                    $success.hide().text('');
                    $input.val(normalized);
                    renderBarcode(normalized, {
                        placeholderText: barcodePreviewText
                    });
                    $form.show();
                    $input.trigger('focus').trigger('select');
                };

                const closeEditor = (idCode) => {
                    const normalized = $.customers.normalizeLoyaltyCodeValue(idCode);
                    $card.data('id-code', normalized);
                    $form.hide();
                    if (normalized) {
                        renderView(normalized);
                        $view.show();
                        $result.show();
                        $empty.hide();
                    } else {
                        $view.hide();
                        $result.show();
                        $empty.show();
                    }
                };

                const cancelEditor = () => {
                    if ($save.prop('disabled')) {
                        return;
                    }

                    $error.hide().text('');
                    $success.hide().text('');
                    closeEditor($card.data('id-code') || '');
                };

                if (initialIdCode) {
                    renderView(initialIdCode);
                }

                $card.on('click', '.js-loyalty-generate', function () {
                    openEditor($.customers.generateLoyaltyCode(customerId, 0));
                    return false;
                });

                $card.on('click', '.js-loyalty-edit', function () {
                    openEditor($card.data('id-code') || '');
                    return false;
                });

                $card.on('click', '.js-loyalty-copy', function () {
                    const $self = $(this);
                    const $success_icon = $self.find('.js-success-icon');
                    const $text = $self.find('[data-default-text]');
                    try {
                        const id_code = $code.text().trim();
                        $.wa.copyToClipboard(id_code).then(() => {
                            $self.prop('disabled', true);
                            $success_icon.show();
                            $text.text($text.data('success-text'));
                            setTimeout(() => {
                                $success_icon.hide();
                                $text.text($text.data('default-text'));
                                $self.prop('disabled', false);
                            }, 1000)
                        });
                    } catch {}
                    return false;
                });

                $input.on('input', function () {
                    const value = $.customers.normalizeLoyaltyCodeValue($(this).val());
                    if ($(this).val() !== value) {
                        $(this).val(value);
                    }
                    $error.hide().text('');
                    $success.hide().text('');
                    renderBarcode(value, {
                        placeholderText: barcodePreviewText
                    });
                });

                $input.on('keydown', function (event) {
                    if (event.key === 'Escape' || event.keyCode === 27) {
                        event.preventDefault();
                        cancelEditor();
                    }
                });

                $card.on('click', '.js-loyalty-cancel', function () {
                    cancelEditor();
                    return false;
                });

                $card.on('click', '.js-loyalty-save', function () {
                    const idCode = $.customers.normalizeLoyaltyCodeValue($input.val());
                    $input.val(idCode);
                    $error.hide().text('');
                    $success.hide().text('');

                    if (!$.customers.isValidLoyaltyCode(idCode)) {
                        $error.text(invalidCodeText).show();
                        $input.trigger('focus');
                        return false;
                    }

                    $save.prop('disabled', true);
                    $loading.show();

                    $.post($card.data('save-url'), {
                        customer_id: customerId,
                        id_code: idCode
                    }, function (response) {
                        const data = response && response.data ? response.data : null;

                        if (response && response.status === 'ok' && data) {
                            if (data.id_code !== null && data.message) {
                                if (data.id_code != idCode) {
                                    $error.text(data.message).show();
                                }else{
                                    $success.text(data.message).show();
                                }
                            }
                            closeEditor(data.id_code);
                        } else if (response && response.errors) {
                            $error.text(getErrorText(response.errors)).show();
                        } else {
                            $error.text(saveFailedText).show();
                        }
                    }, 'json').fail(function () {
                        $error.text(saveFailedText).show();
                    }).always(function () {
                        $save.prop('disabled', false);
                        $loading.hide();
                    });

                    return false;
                });
            });
        },

        normalizeLoyaltyCodeValue: function(value) {
            return String(value || '').replace(/\D+/g, '').substr(0, 12);
        },

        formatLoyaltyCode: function(value) {
            const normalized = this.normalizeLoyaltyCodeValue(value);
            if (!normalized) {
                return '';
            }
            return normalized.replace(/(\d{4})(?=\d)/g, '$1 ').trim();
        },

        isValidLoyaltyCode: function(value) {
            return !value || /^[1-9][0-9]{11}$/.test(value);
        },

        getLoyaltyEan13: function(value) {
            const normalized = this.normalizeLoyaltyCodeValue(value);
            if (!this.isValidLoyaltyCode(normalized)) {
                return '';
            }
            return normalized + this.getEan13Checksum(normalized);
        },

        getEan13Checksum: function(value) {
            const normalized = this.normalizeLoyaltyCodeValue(value);
            if (normalized.length !== 12) {
                return '';
            }

            let sum = 0;
            for (let i = 0; i < normalized.length; i += 1) {
                const digit = parseInt(normalized[i], 10);
                sum += digit * (i % 2 === 0 ? 1 : 3);
            }

            return String((10 - (sum % 10)) % 10);
        },

        renderEan13Svg: function(value) {
            const normalized = String(value || '').replace(/\D+/g, '');
            if (!/^\d{13}$/.test(normalized)) {
                return '';
            }

            const sets = {
                A: ['0001101', '0011001', '0010011', '0111101', '0100011', '0110001', '0101111', '0111011', '0110111', '0001011'],
                B: ['0100111', '0110011', '0011011', '0100001', '0011101', '0111001', '0000101', '0010001', '0001001', '0010111'],
                C: ['1110010', '1100110', '1101100', '1000010', '1011100', '1001110', '1010000', '1000100', '1001000', '1110100']
            };
            const structure = ['AAAAAA', 'AABABB', 'AABBAB', 'AABBBA', 'ABAABB', 'ABBAAB', 'ABBBAA', 'ABABAB', 'ABABBA', 'ABBABA'];
            const firstDigit = parseInt(normalized[0], 10);
            const leftDigits = normalized.slice(1, 7).split('');
            const rightDigits = normalized.slice(7).split('');

            let bits = '101';
            const leftStructure = structure[firstDigit];

            leftDigits.forEach((digit, index) => {
                bits += sets[leftStructure[index]][parseInt(digit, 10)];
            });

            bits += '01010';

            rightDigits.forEach((digit) => {
                bits += sets.C[parseInt(digit, 10)];
            });

            bits += '101';

            const moduleWidth = 2;
            const height = 72;
            const guardHeight = 78;
            const width = bits.length * moduleWidth;
            let rects = '';

            for (let i = 0; i < bits.length; i += 1) {
                if (bits[i] !== '1') {
                    continue;
                }

                const isGuard = i < 3 || (i >= 45 && i < 50) || i >= 92;
                rects += `<rect x="${i * moduleWidth}" y="0" width="${moduleWidth}" height="${isGuard ? guardHeight : height}" fill="#000"></rect>`;
            }

            return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${width} 92" width="100%" height="92" role="img" aria-label="EAN-13 ${normalized}"><rect width="${width}" height="92" fill="#fff"></rect>${rects}<text x="0" y="90" font-size="12" font-family="monospace" fill="#000">${normalized[0]}</text><text x="16" y="90" font-size="12" font-family="monospace" fill="#000">${normalized.slice(1, 7)}</text><text x="${width - 84}" y="90" font-size="12" font-family="monospace" fill="#000">${normalized.slice(7)}</text></svg>`;
        },

        generateLoyaltyCode: function(customerId, attempt) {
            let seed = this.hashLoyaltySeed(String(customerId) + ':' + String(attempt || 0) + ':shop-customer-id-code');
            let code = '';

            for (let i = 0; i < 12; i += 1) {
                seed = this.nextLoyaltySeed(seed, i);
                let digit = seed % 10;

                if (i === 0) {
                    digit = (seed % 9) + 1;
                }

                code += String(digit);
            }

            return code;
        },

        hashLoyaltySeed: function(value) {
            let hash = 2166136261;

            for (let i = 0; i < value.length; i += 1) {
                hash ^= value.charCodeAt(i);
                hash = Math.imul(hash, 16777619) >>> 0;
            }

            return hash >>> 0;
        },

        nextLoyaltySeed: function(seed, step) {
            seed = (Math.imul((seed ^ (step + 1)) >>> 0, 1597334677) + 12345) >>> 0;
            return seed || 1;
        },

        initLazyLoad: function(options) {
            var count = options.count;
            var offset = count;
            var total_count = options.total_count;
            var url = options.url;
            var container = $(options.container);
            var auto = typeof options.auto === 'undefined' ? true : options.auto;

            $(window).lazyLoad('stop'); // stop previous lazy-load implementation

            if (offset < total_count) {
                $(window).lazyLoad({
                    container: container,
                    state: auto ? 'wake' : 'stop',
                    load: function() {
                        $(window).lazyLoad('sleep');
                        $('.lazyloading-link').hide();
                        $('.lazyloading-progress').show();
                        $.get(url + '&lazy=1&offset=' + offset + '&total_count=' + total_count, function(data) {

                            var html = $('<div></div>').html(data);
                            var list = html.find('.s-customers tbody tr');
                            if (list.length) {
                                offset += list.length;
                                $('.s-customers tbody', container).append(list);
                                if (offset >= total_count) {
                                    $(window).lazyLoad('stop');
                                    $('.lazyloading-progress').hide();
                                } else {
                                    $(window).lazyLoad('wake');
                                    $('.lazyloading-link').show();
                                    if (!auto) {
                                        $('.lazyloading-progress').hide();
                                    }
                                }
                            } else {
                                $(window).lazyLoad('stop');
                                $('.lazyloading-progress').hide();
                            }

                            $('.lazyloading-progress-string', container).
                                    replaceWith(
                                        $('.lazyloading-progress-string', html)
                                    );
                            $('.lazyloading-chunk', container).
                                    replaceWith(
                                        $('.lazyloading-chunk', html)
                                    );

                            html.remove();

                        });
                    }
                });
                $('.lazyloading-link').off('click').on('click', function() {
                    $(window).lazyLoad('force');
                    return false;
                });
            }
        },


        // * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
        // *   Dispatch-related
        // * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *

        // if this is > 0 then this.dispatch() decrements it and ignores a call
        skipDispatch: 0,

        /** Cancel the next n automatic dispatches when window.location.hash changes */
        stopDispatch: function (n) {
            this.skipDispatch = n;
        },

        /** Force reload current hash-based 'page'. */
        redispatch: function() {
            this.currentHash = null;
            this.dispatch();
        },

        /**
          * Called automatically when window.location.hash changes.
          * Call a corresponding handler by concatenating leading non-int parts of hash,
          * e.g. for #/aaa/bbb/111/dd/12/ee/ff
          * a method $.customers.AaaBbbAction('111', 'dd', '12', 'ee', 'ff') will be called.
          */
        dispatch: function (hash) {
            if (this.skipDispatch > 0) {
                this.skipDispatch--;
                return false;
            }

            if (hash === undefined || hash === null) {
                hash = this.getHash();
            }
            if (this.currentHash == hash) {
                return;
            }

            this.currentHash = hash;
            hash = hash.replace('#/', '');

            if (hash) {
                hash = hash.replace(/\\\//g, 'ESCAPED_SLASH');
                hash = hash.split('/');
                for (var i = 0; i < hash.length; i += 1) {
                    hash[i] = hash[i].replace(/ESCAPED_SLASH/g, '/');
                }
                if (hash[0]) {
                    var actionName = "";
                    var attrMarker = hash.length;

                    for (var i = 0; i < hash.length; i++) {
                        var h = hash[i];
                        if (i < 2) {
                            if (i === 0) {
                                actionName = h;
                                if (this.testActionExists(actionName)) {
                                    this.execute(actionName, hash.slice(i + 1));
                                    return;
                                }
                            } else if (parseInt(h, 10) != h && h.indexOf('=') == -1) {
                                actionName += h.substr(0,1).toUpperCase() + h.substr(1);
                            } else {
                                attrMarker = i;
                                break;
                            }
                        } else {
                            attrMarker = i;
                            break;
                        }
                    }
                    if (this.testActionExists(actionName)) {
                        this.execute(actionName, hash.slice(attrMarker));
                        return;
                    }
                } else {
                    if (this.testActionExists('default')) {
                        this.execute('default');
                    }
                }
            } else {
                if (this.testActionExists('default')) {
                    this.execute('default');
                }
            }
        },

        testActionExists: function(actionName) {
            if (typeof(this[actionName + 'Action']) !== 'function') {
                $.shop.error('Invalid action name:', actionName+'Action');
                return false;
            }
            return true;
        },

        execute: function(actionName, attr) {
            actionName = actionName || 'default';
            this.preExecute(actionName);
            $.shop.trace('$.customers.dispatch',[actionName + 'Action',attr]);
            this[actionName + 'Action'].apply(this, attr);
            this.postExecute(actionName);
        },

        preExecute: function(actionName, attr) {
            const $skeleton = $('.skeleton').filter(`.js-action-${actionName}`);
            if($skeleton.length) {
                $('#s-customers').addClass('hidden');
                $skeleton.removeClass('hidden');
            }

            $(document).one('wa_loaded', () => {
                $('#s-customers').removeClass('hidden');
                $skeleton.addClass('hidden');
            });

            this.highlightSidebar();
        },

        postExecute: function(actionName, attr) {
            this.actionName = actionName;
        },

        defaultAction: function () {
            $.wa.setHash('#/all/');
        },

        //
        // Pages
        //

        allAction: function(order) {
            order = this.getSortOrder(order);
            this.load(this.getUrl() + order);
        },

        shopAction: function(order) {
            order = this.getSortOrder(order);
            this.load(this.getUrl() + '&only_customers=1' + order);
        },

        categoryAction: function(id, order) {
            order = this.getSortOrder(order);
            this.load(this.getUrl() + '&category='+id+order);
        },

        searchAction: function(q, order) {
            order = this.getSortOrder(order);
            this.load(this.getUrl() + '&search=' + encodeURIComponent(q) + order);
        },

        filterAction: function(filter_id, order) {
            order = this.getSortOrder(order);
            this.load(this.getUrl() + '&filter_id=' + filter_id + order);
        },

        searchformAction: function(hash) {
            this.load('?module=customers&action=searchForm&hash=' + encodeURIComponent(hash));
        },

        idAction: function(id) {
            this.load('?module=customers&action=info&id='+id);
        },

        addAction: function() {
            this.load('?module=customers&action=add');
        },

        editcategoryAction: function(id) {
            if (id) {
                $('#customer-categories').find('li[data-category-id=' + id + ']').addClass('selected');
            }
            this.load('?module=customers&action=categoryEditor&id='+(id || ''));
        },

        //
        // Helpers
        //

        getUrl: function() {
            return '?module=customers&action=list';
        },

        getSortOrder: function(order) {
            if (!order) {
                order = $.storage.get('shop/customers/sort_order');
            }
            if (order) {
                return '&order='+order;
            } else {
                return '';
            }
        },

        reloadSidebar: function() {
            this.load('?module=customers&action=sidebar', { content: $('#s-sidebar'), check: false }, function() {
                $.customers.highlightSidebar();
            });
        },

        /** Add .selected css class to li with <a> whose href attribute matches current hash.
          * If no such <a> found, then the first partial match is highlighted.
          * Hashes are compared after this.cleanHash() applied to them. */
        highlightSidebar: function(hash) {
            $(document).on('wa_loaded', () => {
                const currentHash = this.cleanHash(hash || window.location.hash);
                if (currentHash.indexOf('search/') !== -1) {
                    $('#s-sidebar .selected').removeClass('selected');
                    $('#s-sidebar a[href="#/searchform/"]').closest('li').addClass('selected');
                    return;
                }
                let partialMatch = false;
                let partialMatchLength = 2;
                let match = false;
                $('#s-sidebar a').each(function(k, v) {
                    v = $(v);
                    if (!v.attr('href')) {
                        return;
                    }
                    const h = $.customers.cleanHash(v.attr('href'));

                    // Perfect match?
                    if (h === currentHash) {
                        match = v;
                        return false;
                    }

                    // Partial match? (e.g. for urls that differ in paging only)
                    if (h.length > partialMatchLength && currentHash.substr(0, h.length) === h) {
                        partialMatch = v;
                        partialMatchLength = h.length;
                    }
                });


                if (!match && partialMatch) {
                    match = partialMatch;
                }

                if (match) {
                    $('#s-sidebar .selected').removeClass('selected');

                    if (match.closest('li').length && !match.closest('.dropdown').length) {
                        match.closest('li').addClass('selected');
                        const $filterToggleButton = $('#s-customer-filters').find('.dropdown-toggle');
                        const filterToggleButtonText = $filterToggleButton.data('default-text');
                        $filterToggleButton.text(filterToggleButtonText);
                    }

                    // select active element in dropdown
                    if (match.closest('.dropdown').length) {
                        match.click();
                    }
                } else if (!hash && this.lastView && this.lastView.hash) {
                    // When no match found, try to highlight based on last view
                    this.highlightSidebar(this.lastView.hash);
                }
            });
        },

        /** Current hash */
        getHash: function () {
            return this.cleanHash();
        },

        /** Make sure hash has a # in the begining and exactly one / at the end.
          * For empty hashes (including #, #/, #// etc.) return an empty string.
          * Otherwise, return the cleaned hash.
          * When hash is not specified, current hash is used. */
        cleanHash: function (hash) {
            if(typeof hash == 'undefined') {
                hash = window.location.hash.toString();
            }

            if (!hash.length) {
                hash = ''+hash;
            }
            while (hash.length > 0 && hash[hash.length-1] === '/') {
                hash = hash.substr(0, hash.length-1);
            }
            hash += '/';

            if (hash[0] != '#') {
                if (hash[0] != '/') {
                    hash = '/' + hash;
                }
                hash = '#' + hash;
            } else if (hash[1] && hash[1] != '/') {
                hash = '#/' + hash.substr(1);
            }

            if(hash == '#/') {
                return '';
            }

            return hash;
        },

        load: function (url, options, fn) {
            if (typeof options === 'function') {
                fn = options;
                options = {};
            } else {
                options = options || {};
            }
            var r = Math.random();
            this.random = r;
            var self = this;
            return  $.get(url, function(result) {
                if ((typeof options.check === 'undefined' || options.check) && self.random != r) {
                    // too late: user clicked something else.
                    return;
                }
                (options.content || $("#s-content")).html(result);
                if (typeof fn === 'function') {
                    fn.call(this);
                }
                $(document).trigger('wa_loaded');
                $('html, body').animate({scrollTop:0}, 200);
            });
        },

        initMassActions: function() {
            const base_url = '?module=customersMass&action=';
            const $wrapper = $('.s-customers-list-menu');
            const $dropdown = $wrapper.find('.js-mass-actions-dropdown');
            const $dropdown_actions = $dropdown.find('.dropdown-item');
            const $show_actions_button = $wrapper.find('.js-show-mass-actions');
            const $hide_actions_button = $wrapper.find('.js-hide-mass-actions');
            const $top_buttons = $wrapper.children('a,button,input').not($hide_actions_button).not($dropdown);

            const $list = $('table.s-customers');
            const $toggle_all_checkboxes = $list.find('.js-toggle-checkboxes');
            const checkboxes = () => $list.find('td.s-col-checkbox :checkbox');
            const checked = () => checkboxes().filter(':checked');

            const updateCount = () => {
                const count = checked().length;
                $dropdown.find('.js-counter').toggle(!!count).text(count);
                $dropdown_actions.toggleClass('disabled', !count);
            };

            const sendRequest = (action_id) => {
                if (!action_id) return;
                const ids = $.map(checked().closest('[data-customer-id]'), (el) => el.dataset.customerId);
                if (!ids.length) return;

                const url = `${base_url}${action_id}Dialog`;
                $.post(url, { ids }, html => {
                    if (!html) return;
                    $.waDialog({ html });
                });
            }

            // init events
            $dropdown.waDropdown({ hover: false });
            $dropdown_actions.addClass('disabled');

            $show_actions_button.on('click', () => {
                $top_buttons.hide();

                $dropdown.show();
                $list.addClass('is-mass-actions');
                $hide_actions_button.show();
                $wrapper.addClass('is-sticky');
            });

            $hide_actions_button.on('click', () => {
                $top_buttons.show();

                $dropdown.hide();
                $list.removeClass('is-mass-actions');
                $hide_actions_button.hide();
                $toggle_all_checkboxes.prop('checked', false).trigger('change');
                $wrapper.removeClass('is-sticky');
            });

            $list.on('click', '.s-col-checkbox :checkbox', function(e) {
                const $self = $(this);
                if (e.shiftKey) {
                    const checked = $self.is(':checked');
                    let stop = false;
                    if (checked) {
                        const $items_prev_all = $self.closest('[data-customer-id]').prevAll();
                        if (!$items_prev_all.find(':checkbox:checked').length) return;
                        for (let i = 0; i < $items_prev_all.length; i++) {
                            if (stop) break;
                            const $item = $items_prev_all.eq(i);
                            const $checkbox = $item.find(':checkbox');
                            stop = $checkbox.is(':checked');
                            $checkbox.prop('checked', true);
                        }
                    } else {
                        const $items_next_all = $self.closest('[data-customer-id]').nextAll();
                        $items_next_all.find(':checkbox:checked').prop('checked', false);
                    }
                }
                updateCount();
            });

            $toggle_all_checkboxes.on('change', function() {
                const checked = $(this).is(':checked');
                checkboxes().prop('checked', checked);
                updateCount();
            });

            $dropdown_actions.on('click', function() {
                const $self = $(this);
                if ($self.hasClass('disabled')) return false;

                const action_id = $self.data('action');
                sendRequest(action_id);
            });
        },

        initMobileSidebar() {
            $.shop.initMobileSidebar({
                $sidebar: $("#s-sidebar"),
                $content: $("#s-customers .article"),
                $additionalLinks: $('#s-sidebar .count a'),
                openAfterReload: true,
                storageId: 'customers',
                appendButtonBack ($defaultButtonBack) {
                    return $defaultButtonBack.insertBefore('#s-customers h1.s-header');
                }
            });
        }
    };
})(jQuery);
