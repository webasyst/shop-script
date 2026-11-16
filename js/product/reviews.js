(function($) {
    $.product_reviews = {

        /**
         * {Jquery object}
         */
        container: null,

        /**
         * {Jquery object}
         */

        sidebar_counter: null,

        /**
         * {Object}
         */
        statuses: {},

        /**
         * {Jquery object}
         * */
        form: null,

        /**
         * Hotkey combinations
         * {Object}
         */
        hotkeys: {
            'alt+enter': {
                ctrl:false, alt:true, shift:false, key:13
            },
            'ctrl+enter': {
                ctrl:true, alt:false, shift:false, key:13
            },
            'ctrl+s': {
                ctrl:true, alt:false, shift:false, key:17
            }
        },

        /**
         * {Object}
         * */
        options: {},

        init: function(options) {
            const that = this;
            this.options  = options;
            this.statuses = options.statuses;
            if (options.product_id) {
                this.product_id = options.product_id;
            }
            this.form = $('#s-review-add-form');
            this.sidebar_counter = $('#s-all-reviews').find('.count');

            this.getForm = () => this.form.clone();

            if (options.container) {
                if (typeof options.container === 'object') {
                    this.container = options.container;
                } else {
                    this.container = $(options.container);
                }
                this.container.find('.s-reviews')
                   .off('click', '.s-review-reply, .s-review-delete, .s-review-restore, .js-review-publish')
                    .on('click', '.s-review-reply, .s-review-delete, .s-review-restore, .js-review-publish',
                        function() {
                            var self = $(this),
                                li = self.parents('li:first'),
                                parent_id = parseInt(li.attr('data-id'), 10) || 0;
                            if (self.hasClass('s-review-reply')) {
                                if (that.options.reply === 'ignore') {
                                    return;
                                }
                                that.prepareAddingForm(self, parent_id);
                            } else if (self.hasClass('s-review-delete')) {
                                that.deleteReview(parent_id, options.afterDelete);
                            } else if (self.hasClass('s-review-restore')) {
                                that.restoreReview(parent_id, options.afterRestore);
                            } else if (self.hasClass('js-review-publish')) {
                                that.publishReview(parent_id);
                            }
                            return false;
                        }
                    );
            }

            if (this.product_id) {
                this.container.addClass('ajax');
            }

            this.initView();

            if (this.options.lazy_loading) {
                this.initLazyLoad(this.options.lazy_loading);
            }

            if ($.product) {
                $.product.editTabReviewsAction = function(path) {
                    if (path.tail) {
                        setTimeout(function() {
                            that.activeReplyToForm(path.tail);
                        }, 200);
                    }
                };
            }
        },

        activeReplyToForm: function(id) {
            var li = this.container.find('.s-reviews li[data-id='+id+']');
            if (li.length) {
                var reply_link = li.find('.s-review-reply:first');
                this.prepareAddingForm(reply_link, id);
                $(document).scrollTop(li.offset().top);
            }
        },

        initView: function() {
            if ($.products.hash.substr(0, 7) == 'reviews') {
                var sidebar = $('#s-sidebar');
                sidebar.find('li.selected').removeClass('selected');
                sidebar.find('#s-all-reviews').addClass('selected');
            }
        },

        initReview: function(options) {
            var $document = $(document),
                $review = options["$wrapper"];

            var review_id = $review.data("id"),
                locales = options["locales"],
                urls = options["urls"];

            var delete_locked = false;

            $review.on("click", ".js-show-image", function(event) {
                event.preventDefault();

                var $image = $(this),
                    images = [];

                $review.find(".js-show-image").each(function () {
                    var $_image = $(this);
                    images.push({
                        href: $_image.attr("href"),
                        title: escape($_image.attr("title"))
                    });
                });

                var k = $image.closest(".s-image-wrapper").prevAll('.s-image-wrapper').length;
                if (k) { images = images.slice(k).concat(images.slice(0, k)); }

                $.swipebox(images, {
                    useSVG : false,
                    hideBarsDelay: false,
                    afterOpen: function() {
                        $document.on("scroll", closeSwipe);
                        function closeSwipe() {
                            var $closeButton = $("#swipebox-close");
                            if ($closeButton.length) {
                                $closeButton.trigger("click");
                            }
                            $document.off("scroll", closeSwipe);
                        }
                    }
                });
            });

            $review.on("click", ".js-delete-image", function(event) {
                event.preventDefault();

                var $image_w = $(this).closest(".s-image-wrapper"),
                    image_id = $image_w.data("image-id");

                if (!delete_locked && confirm(locales["confirm"]) ) {
                    delete_locked = true;

                    var href = urls["delete"],
                        data = {
                            review_id: review_id,
                            image_id: image_id
                        };

                    $.post(href, data, "json")
                        .always( function() {
                            delete_locked = false;
                        })
                        .done( function() {
                            $image_w.remove();
                        });
                }
            });

            function escape(string) {
                return $("<div />").text(string).html();
            }
        },

        initLazyLoad: function(options) {
            var count = options.count;
            var offset = count;
            var total_count = options.total_count;
            var url = options.url || '?module=reviews';
            var target = $(options.target || '.s-reviews:first ul:first');

            $(window).lazyLoad('stop');  // stop previous lazy-load implementation

            if (offset < total_count) {
                $(window).lazyLoad({
                    container: target,
                    state: (typeof options.auto === 'undefined' ? true: options.auto) ? 'wake' : 'stop',
                    load: function() {
                        $(window).lazyLoad('sleep');
                        $('.lazyloading-link').hide();
                        $('.lazyloading-progress').show();
                        $.get(
                            url+'&lazy=1&offset='+offset+'&total_count='+total_count,
                            function (html) {
                                var data = $('<div></div>').html(html);
                                var children = data.find('.s-reviews').children();
                                offset += children.length;
                                target.append(children);
                                $('.lazyloading-progress-string').replaceWith(data.find('.lazyloading-progress-string'));
                                $('.lazyloading-progress').replaceWith(data.find('.lazyloading-progress'));
                                if (offset >= total_count) {
                                    $(window).lazyLoad('stop');
                                    $('.lazyloading-link').hide();
                                } else {
                                    $('.lazyloading-link').show();
                                    $(window).lazyLoad('wake');
                                }
                                data.remove();
                            },
                            "html"
                        );
                    }
                });
                $('.lazyloading-link').unbind('click').bind('click', function(){
                    $(window).lazyLoad('force');
                    return false;
                });
            }
        },

        prepareAddingForm: function($add_review_button, review_id)
        {
            const that = this;
            const $form = that.getForm();

            $add_review_button.prop('disabled', true);

            const addReview = () => {
                that.addReview($form, 'backend', that.options.afterAdd);
            };
            that.addHotkeyHandler($form, 'textarea', 'ctrl+enter', addReview);
            $form.find('input.save').off('click').on('click', addReview);
            $form.find('.js-ai-answer-button').off('click').on('click', function () {
                that.addAnswerAi.call(this, $form);
            });
            $form.find('.s-review-close').off('click').one('click', () => {
                $form.remove();
                $add_review_button.prop('disabled', false);
            });

            if (review_id) {
                $add_review_button.after($form);
                $form.find('.rate a:first').hide();
            }

            $('input[name=parent_id]', $form).val(review_id);
        },

        addHotkeyHandler: function($form, item_selector, hotkey_name, handler) {
            var hotkey = this.hotkeys[hotkey_name];
            $form.off('keydown', item_selector).on('keydown', item_selector,
                function(e) {
                    if (e.keyCode == hotkey.key &&
                        e.altKey  == hotkey.alt &&
                        e.ctrlKey == hotkey.ctrl &&
                        e.shiftKey == hotkey.shift)
                    {
                        return handler();
                    }
                }
            );
        },

        addReview: function($form, env, success) {
            const sidebar_counter = this.sidebar_counter;
            const $loading = $form.find('.s-review-add-loading').show();
            const $hint = $form.find('.s-review-add-hint').hide();
            const $bottom_butons = $form.find('.bottom-buttons .button').prop('disabled', true);
            $.product_reviews.clear($form, false);
            $.post(
                (env == 'backend' || !env) ? '?module=reviews&action=add' : '#',
                $form.serialize(),
                function (r) {
                    if (r.status == 'fail') {
                        $.product_reviews.showErrors($form, r.errors);
                        return;
                    }
                    if (r.status != 'ok' || !r.data.html) {
                        if (console) {
                            console.log('Error occured.');
                        }
                        return;
                    }

                    var parent_li = $form.parents('.s-review:first').parent('li');
                    var html = r.data.html;
                    var ul = $('ul:first', parent_li);
                    var reviews_block = $.product_reviews.container;

                    if (!parent_li.length) {
                        reviews_block.show();
                        ul = $('ul:first', reviews_block).show();
                    }
                    if (!ul.length) {
                        ul = $('<ul class="menu"></ul>');
                        parent_li.append(ul);
                    }
                    ul.append($('<li data-id="'+r.data.id+'" data-parent-id="'+r.data.parent_id+'"></li>').append(html));

                    $form.remove();

                    if (sidebar_counter.length) {
                        sidebar_counter.text(parseInt(sidebar_counter.text(), 10) + 1);
                    }
                    if (typeof success === 'function') {
                        success(r);
                    }
                    $.product_reviews.clear($form);
                },
            'json')
            .error(function(r) {
                if (console) {
                    console.log(r.responseText ? 'Error occured: ' + r.responseText : 'Error occured.');
                }
            }).always(() => {
                $loading.hide();
                $hint.show();
                $bottom_butons.prop('disabled', false);
            });
        },

        deleteReview: function(review_id, success)
        {
            var container = this.container;
            var sidebar_counter = this.sidebar_counter;
            $.post('?module=reviews&action=changeStatus',
                { review_id: review_id, status: this.statuses.deleted },
                function(r) {
                    if (r.status == 'ok') {
                        var review_li  = container.find('li[data-id='+review_id+']');
                        var review_div = review_li.find('div:first');
                        review_div
                            .removeClass("is-published")
                            .removeClass("is-unpublished")
                            .addClass('is-deleted');
                        review_div.find('.s-review-delete').hide();
                        review_div.find('.s-review-restore').show();
                        if (sidebar_counter.length) {
                            sidebar_counter.text(parseInt(sidebar_counter.text(), 10) - 1);
                        }
                        if (typeof success === 'function') {
                            success(r);
                        }
                    }
                },
            'json');
        },

        restoreReview: function(review_id)
        {
            var container = this.container;
            var sidebar_counter = this.sidebar_counter;
            $.post('?module=reviews&action=changeStatus',
                { review_id: review_id, status: this.statuses.published },
                function(r) {
                    if (r.status == 'ok') {
                        var review_li  = container.find('li[data-id='+review_id+']');
                        var review_div = review_li.find('div:first');
                        review_div
                            .addClass("is-published")
                            .removeClass("is-unpublished")
                            .removeClass('is-deleted');
                        review_div.find('.s-review-delete').show();
                        review_div.find('.s-review-restore').hide();
                        if (sidebar_counter.length) {
                            sidebar_counter.text(parseInt(sidebar_counter.text(), 10) + 1);
                        }
                    }
                },
            'json');
        },

        publishReview: function(review_id)
        {
            var container = this.container;
            var sidebar_counter = this.sidebar_counter;
            $.post('?module=reviews&action=changeStatus',
                { review_id: review_id, status: this.statuses.published },
                function(r) {
                    if (r.status == 'ok') {
                        var review_li  = container.find('li[data-id='+review_id+']');
                        var review_div = review_li.find('div:first');
                        review_div
                            .addClass("is-published")
                            .removeClass("is-unpublished")
                            .removeClass('is-deleted');
                        review_div.find('.s-review-delete').show();
                        review_div.find('.js-review-publish').remove();
                        if (sidebar_counter.length) {
                            sidebar_counter.text(parseInt(sidebar_counter.text(), 10) + 1);
                        }
                    }
                },
            'json');
        },

        addAnswerAi: function($form) {
            const self = $(this);
            self.prop('disabled', true);
            $.post(
                '?module=reviews&action=addAi',
                $form.serialize(),
                function (r) {
                    if (r.status == 'fail') {
                        r.errors.forEach(({ error, error_description }) => {
                            if (error ==='payment_required') {
                                error_description = error_description.replace('%s', 'href="javascript:void(0)"');
                            }
                            if(self.next().hasClass('state-error-hint')) {
                                self.next().remove();
                            }
                            const $error = $('<span class="state-error-hint" />').html(error_description);
                            self.after($error);
                            setTimeout(() => $error.remove(), 5000);
                        });
                        return;
                    }

                    const $textarea = $('textarea', $form);
                    $textarea.val($textarea.val() ? $textarea.val() + '\n' + r.data.text : r.data.text);

                },
            'json')
            .error(function(r) {
                if (console) {
                    console.log(r.responseText ? 'Error occured: ' + r.responseText : 'Error occured.');
                }
            })
            .always(function() {
                self.prop('disabled', false);
            });
        },

        clear: function($form, clear_inputs) {
            clear_inputs = typeof clear_inputs === 'undefined' ? true : clear_inputs;
            $('.state-error-hint', $form).remove();
            $('.state-error', $form).removeClass('state-error');
            $('.wa-captcha-refresh', $form).click();
            if (clear_inputs) {
                $('input[name=captcha], textarea', $form).val('');
                $('input[name=rate]', $form).val(0);
                $('.rate a:first', $form).trigger('clear');
            }
        },

        showErrors: function($form, errors) {
            if (!errors) return;
            if (!Array.isArray(errors)) {
                errors = [errors];
            }
            errors.forEach(err => {
                for (const key in err) {
                    if (typeof err[key] !== 'string') return;
                    // $form.find('[name='+key+']', $form).addClass('state-error');
                    $form.append($('<div class="state-error-hint custom-mt-4"></div>').text(err[key]));
                }
            });
        }
    };
})(jQuery);
