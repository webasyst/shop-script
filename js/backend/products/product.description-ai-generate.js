(function ($) {
    "use strict";
    class ProductDescriptionAIGenerate {
        constructor(options) {
            options = options || {};
            this.wa_backend_url = options.wa_backend_url;
            this.product_ids = options.product_ids;
            this.lang = options.lang;
            this.locales = options.locales;
            this.templates = options.templates;
            this.per_transaction_seconds = options.per_transaction_seconds || 0;
            this.$wrapper = options.$wrapper;

            this.$form = this.$wrapper.find('form');
            this.dialog = this.$wrapper.data('dialog');
            this.$place_for_errors = this.$wrapper.find('.place-for-errors');

            this.products_length = this.product_ids.length;
            this.progressbar = null;
            this.progressbar_one_by_one_state = {};
            this.xhr = null;

            this.init();
        }

        init() {
            this.intiCollapsibleConfig();
            this.intiProgressbar();
            this.initEvents();
        }

        initEvents() {
            const $submit_mass = this.$wrapper.find('.js-submit-mass');
            const $submit_one_by_one = this.$wrapper.find('.js-submit-by-one');
            const $button_close = this.$wrapper.find('.js-dialog-close');

            const disableSubmit = (disabled = true) => {
                $submit_mass.prop('disabled', disabled);
                $submit_one_by_one.prop('disabled', disabled);
            };
            disableSubmit();

            const onSuccess = () => {
                $submit_mass.hide();
                $submit_one_by_one.hide();
                $button_close.show();
            };

            // with autosaving data
            $submit_mass.click((e) => {
                e.preventDefault();
                disableSubmit();
                $submit_one_by_one.hide();
                this.$form.css('pointer-events', 'none');
                this.submit({
                    params: {
                        save_to_product: 1
                    },
                    onSuccess
                });
            });

            // with one by one dialog
            $submit_one_by_one.click((e) => {
                e.preventDefault();
                disableSubmit();
                $submit_mass.hide();
                this.$form.css('pointer-events', 'none');
                this.submit({
                    onHandleResponse: this.initOneByOneDialog.bind(this),
                    onSuccess
                })
            });

            const $fields_to_fill = this.$form.find('.js-fields-to-fill :checkbox');
            const updateGenerationTimeEstimation = () => {
                const count = Array.from(new Set(
                    $fields_to_fill.filter((_, el) => el.checked).map((_, el) => el.dataset.requestId)
                )).length;
                disableSubmit(!count);
                this.updateGenerationTimeEstimation(count);
            };
            $fields_to_fill.on('change', updateGenerationTimeEstimation);
            updateGenerationTimeEstimation();

            this.dialog.onClose = () => {
                if (this.xhr) {
                    this.xhr.abort();
                    this.xhr = false;
                }
            }
        }

        intiCollapsibleConfig() {
            const $toggler = $('.js-toggle-collapsible-config');
            $toggler.click(() => {
                $toggler.remove();
                this.$form.find('.js-collapsible-config').toggle();
                this.dialog.resize();
            });
        }

        intiProgressbar() {
            const $bar = this.$form.find(".js-progressbar").waProgressbar({
                "color": "#1a9afe"
            });
            this.progressbar = $bar.data("progressbar");

            this.progressbar.setStyle = function () {
                this.$bar_inner.css(...arguments);
            };
            this.progressbar.setStyle('height', '1rem');
        }

        async submit({ onHandleResponse, onSuccess, params = {} }) {
            const $loading = this.$wrapper.find('.js-loading').show();
            const $content = this.$wrapper.find('.dialog-content');
            let has_error = false;
            let processed_count = 1;
            let that = this;
            const requestFinished = (callback) => {
                setTimeout(() => {
                    if (typeof onSuccess === 'function') {
                        onSuccess();
                    }
                    if (typeof callback === 'function') {
                        callback();
                    }
                    $loading.hide();
                    this.progressbar.setStyle('background-color', has_error ? 'var(--red)' : 'var(--green)');
                }, 500);
            };
            $content.scrollTop($content.prop('scrollHeight'));

            const payloads = this.prepareRequestData(params);
            for (const p of payloads) {
                if (this.xhr === false) {
                    break;
                }

                try {
                    const response = await this.submitAIGenerate(p);
                    if (response.status === 'ok') {
                        if (typeof onHandleResponse === 'function') {
                            await onHandleResponse(response.data);
                        }
                    } else if (Array.isArray(response.errors) && response.errors.length) {
                        if (response.errors[0].error === 'provider_censored' && payloads.length > 1) {
                            this.progressbar_one_by_one_state[p.product_id] = 'error';
                        } else {
                            has_error = true;
                            requestFinished(() => {
                                response.errors.forEach(({ error, error_description }) => {
                                    let $error = $('<span class="state-caution" />').html(error_description);
                                    if (error ==='payment_required') {
                                        $error = $('<span class="alert warning custom-m-0" />').html(error_description.replace('%s', 'href="javascript:void(0)"'));
                                        $error.on('click', 'a:not(.disabled)', ProductDescriptionAIGenerate.topUpBalanceHandler(that.wa_backend_url));
                                    }
                                    this.$place_for_errors.append($error);
                                });
                            });
                            return;
                        }
                    }
                } catch (e) {
                    has_error = true;
                    console.error(e);
                }

                this.progressbar.set({ percentage: Math.floor(100 / this.products_length * processed_count) });
                processed_count += 1;
                if (processed_count <= this.products_length) {
                    await new Promise((resolve) => setTimeout(() => resolve(), 1000));
                }
            }

            requestFinished();
        }

        submitAIGenerate(data) {
            this.xhr = $.post('?module=prod&action=aiGenerateDescription', data);
            return this.xhr;
        }

        updateGenerationTimeEstimation(count) {
            const $wrapper = this.$form.find('.js-generation-time-estimation');
            const $counter = $wrapper.find('.js-count');
            const $sum = $wrapper.find('.js-sum');
            const result = this.products_length * count;
            $counter.text(count);
            $sum.text(result);

            const duration = this.formatTime(result);
            $wrapper.find('.js-duration').text(duration ? `≈ ${duration}` : '');
        }

        formatTime(count) {
            let sec = Math.floor(this.per_transaction_seconds * count);
            let min = Math.floor(sec / 60);
            if (min > 0) {
                sec -= min * 60;
            }
            let hr = Math.floor(min / 60);
            if (hr > 0) {
                min -= hr * 60;
            }

            const result = `${hr > 0 ? `${hr} ${this.locales.hr}. ` : ''}${min > 0 ? `${min} ${this.locales.min}. ` : ''}${sec > 0 ? `${sec} ${this.locales.sec}. ` : ''}`;
            return result;
        }

        prepareRequestData(params) {
            const requests = [];
            const form_data = this.$form.serializeArray().reduce((obj, el) => (obj[el.name] = el.value, obj), {});

            this.product_ids.forEach(product_id => {
                requests.push({
                    product_id,
                    ...form_data,
                    ...params
                });
            });

            return requests;
        }

        async initOneByOneDialog({ product }) {
            this.progressbar_one_by_one_state[product.id] = 'current';
            const deferred = $.Deferred();
            const $wrapper = $(this.templates.one_by_one_dialog);
            const $form = $wrapper.find('form');
            const $textarea = $('[name="product[description]"]', $form);
            let is_saved = false;

            this.setFormData($wrapper, product);
            this.renderProgressbarByOne($wrapper);
            const resizeContentObserver = (() => {
                const $content = $wrapper.find('.dialog-content');
                let timer_id = null;
                const debounced = () => {
                    if (timer_id) {
                        clearTimeout(timer_id);
                    }
                    timer_id = setTimeout(() => {
                        if ($content[0].clientHeight === $content[0].scrollHeight) {
                            $wrapper.resize();
                        }
                        timer_id = null;
                    }, 100);
                };
                return new ResizeObserver(debounced);
            })();

            const initRedactor = ($textarea) => {
                const getOptions = () => {
                    const options = {
                        lang: this.lang,
                        focus: false,
                        deniedTags: false,
                        minHeight: 150,
                        maxHeight: 250,
                        linkify: false,
                        source: false,
                        paragraphy: false,
                        replaceDivs: false,
                        replaceTags: {
                            'b': 'strong',
                            'i': 'em',
                            'strike': 'del'
                        },
                        removeNewlines: false,
                        removeComments: false,
                        buttons: ['format', 'bold', 'italic', 'underline', 'deleted', 'lists',
                            'table', 'link', 'alignment',
                            'horizontalrule',  'fontcolor', 'fontsize', 'fontfamily'],
                        plugins: ['fontcolor', 'fontfamily', 'alignment', 'fontsize', 'table']
                    };
                    options.callbacks = {
                        sync: function (html) {
                            html = html.replace(/{[a-z$][^}]*}/gi, function (match, offset, full) {
                                var i = full.indexOf("</script", offset + match.length);
                                var j = full.indexOf('<script', offset + match.length);
                                if (i === -1 || (j !== -1 && j < i)) {
                                    match = match.replace(/&gt;/g, '>');
                                    match = match.replace(/&lt;/g, '<');
                                    match = match.replace(/&amp;/g, '&');
                                    match = match.replace(/&quot;/g, '"');
                                }
                                return match;
                            });
                            this.$textarea.val(html);
                        },
                        syncClean: function (html) {
                            // Unescape '->' in smarty tags
                            return html.replace(/\{[a-z\$'"_\(!+\-][^\}]*\}/gi, function (match) {
                                return match.replace(/-&gt;/g, '->');
                            });
                        }
                    };
                    return options;
                }
                return $textarea.redactor(getOptions());
            };
            initRedactor($textarea);

            $.waDialog({
                $wrapper,
                onOpen: (_, d) => {
                    /**
                     * Save product data
                     * product[description]
                     * product[summary]
                     * product[meta_title]
                     * product[meta_keywords]
                     * product[meta_description]
                     */
                    const submit = (e) => {
                        e.preventDefault();
                        const form_data = $form.serialize();
                        $.post('?module=prod&action=saveSeo', form_data, (r) => {
                            if (r?.status === 'ok') {
                                is_saved = true;
                                this.progressbar_one_by_one_state[product.id] = 'saved';
                                d.close();
                            }
                        });
                    };
                    d.$block.find('.js-save').click(submit);
                    resizeContentObserver.observe($form[0]);
                },
                onClose: () => {
                    if (!is_saved) {
                        this.progressbar_one_by_one_state[product.id] = 'skipped';
                    }
                    deferred.resolve();
                    if (resizeContentObserver) {
                        resizeContentObserver.disconnect();
                    }
                }
            });

            return deferred.promise();
        }

        setFormData($wrapper, product) {
            const $form = $wrapper.find('form');
            $form.find('.js-product-name').text(product.name);
            delete product.name;

            for (const key in product) {
                const $field = $form.find(`[name="product[${key}]"]`);
                $field.closest('.field').removeClass('hidden');
                $field.prop('disabled', false).val(product[key]);
            }
        }

        renderProgressbarByOne($wrapper) {
            const $chips = $wrapper.find('.js-status-progress-chips');
            const setIconByProductId = (product_id) => (html) => $chips.find(`[data-id="${product_id}"] .icon`).html(html);
            for (const product_id in this.progressbar_one_by_one_state) {
                const setIcon = setIconByProductId(product_id);
                switch (this.progressbar_one_by_one_state[product_id]) {
                    case 'saved':
                        setIcon('<i class="fas fa-check text-green" />').closest('[data-id]').addClass('tag');
                        break;
                    case 'skipped':
                        setIcon('<i class="fas fa-arrow-right text-light-gray" />');
                        break;
                    case 'error':
                        setIcon('<i class="fas fa-times-circle text-red" />');
                        break;
                    default:
                        setIcon('<i class="fas fa-arrow-down text-gray" />');
                }
            }
        }
    }

    ProductDescriptionAIGenerate.topUpBalanceHandler = function(wa_backend_url) {
        return function(e) {
            e.preventDefault();
            let $button = $(this).addClass('disabled');
            $.get(wa_backend_url+'webasyst/?module=services&action=balanceUrl&service=AI', function (data) {
                let resp = data.data.response;
                let status = data.data.status || '-';
                let err = resp.error_description || resp.error || resp.errors || null;
                $button.removeClass('disabled');
                if (data.status === 'fail' || err) {
                    console.warn('balance', data);
                    alert(status + ' ' + err?.toString());
                } else if (typeof resp.url !== 'undefined') {
                    document.location = resp.url;
                }
            });
        };
    };

    window.ProductDescriptionAIGenerate = ProductDescriptionAIGenerate;
})(jQuery);
