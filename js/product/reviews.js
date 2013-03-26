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
            this.options  = options;
            this.statuses = options.statuses;
            if (options.product_id) {
                this.product_id = options.product_id;
            }
            this.form = $('#s-review-add-form');
            this.sidebar_counter = $('#s-all-reviews').find('.count');
            if (options.container) {
                if (typeof options.container === 'object') {
                    this.container = options.container;
                } else {
                    this.container = $(options.container);
                }
                this.container.find('.s-reviews').off('click', '.s-review-reply, .s-review-delete, .s-review-restore').
                    on('click', '.s-review-reply, .s-review-delete, .s-review-restore',
                        function() {
                            var self = $(this),
                                li = self.parents('li:first'),
                                parent_id = parseInt(li.attr('data-id'), 10) || 0;
                            if (self.hasClass('s-review-reply')) {
                                if ($.product_reviews.options.reply == 'ignore') {
                                    return;
                                }
                                $.product_reviews.prepareAddingForm.call(self, $.product_reviews.form, parent_id);
                            } else if (self.hasClass('s-review-delete')) {
                                $.product_reviews.deleteReview(parent_id, options.afterDelete);
                            } else if (self.hasClass('s-review-restore')) {
                                $.product_reviews.restoreReview(parent_id, options.afterRestore);
                            }
                            return false;
                        }
                    );
            }

            if (this.product_id) {
                this.container.addClass('ajax');
            }

            var addReview = function() {
                $.product_reviews.addReview('backend', options.afterAdd);
            };
            this.addHotkeyHandler('textarea', 'ctrl+enter', addReview);
            this.form.find('input.save').unbind('click').bind('click', addReview);

            this.initView();

//            $.product_reviews.form.find('a:first').rateWidget({
//                onUpdate: function(rate) {
//                    $.product_reviews.form.find('input[name=rate]').val(rate);
//                }
//            });

            if (this.options.lazy_loading) {
                this.initLazyLoad(this.options.lazy_loading);
            }

            if ($.product) {
                $.product.editTabReviewsAction = function(path) {
                    if (path.tail) {
                        setTimeout(function() {
                            $.product_reviews.activeReplyToForm(path.tail);
                        }, 200);
                    }
                };
            }
        },

        activeReplyToForm: function(id) {
            var li = this.container.find('.s-reviews li[data-id='+id+']');
            if (li.length) {
                var reply_link = li.find('.s-review-reply:first');
                this.prepareAddingForm.call(reply_link, this.form, id);
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

        prepareAddingForm: function(form, review_id)
        {
            var self = this; // clicked link
            if (review_id) {
                self.after(form);
                form.find('.rate a:first').hide();
            } else {
                var acceptor = $('#s-review-add li:first');
                if (!acceptor.find('form').length) {
                    acceptor.append(form);
                }
                form.find('.rate a:first').show();
            }
            $.product_reviews.clear(false);
            $('input[name=parent_id]', form).val(review_id);
        },

        addHotkeyHandler: function(item_selector, hotkey_name, handler) {
            var hotkey = this.hotkeys[hotkey_name];
            this.form.off('keydown', item_selector).on('keydown', item_selector,
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

        addReview: function(env, success) {
            var sidebar_counter = this.sidebar_counter;
            $.post(
                (env == 'backend' || !env) ? '?module=reviews&action=add' : '#',
                $.product_reviews.form.serialize(),
                function (r) {
                    if (r.status == 'fail') {
                        $.product_reviews.clear(false);
                        $.product_reviews.showErrors(r.errors);
                        return;
                    }
                    if (r.status != 'ok' || !r.data.html) {
                        if (console) {
                            console.log('Error occured.');
                        }
                        return;
                    }
                    var parent_id_input = $('input[name=parent_id]', $.product_reviews.form);
                    var parent_li = $.product_reviews.form.parents('.s-review:first').parent('li');
                    var html = r.data.html;
                    var ul = $('ul:first', parent_li);
                    var reviews_block = $.product_reviews.container;

                    if (!parent_li.length) {
                        reviews_block.show();
                        ul = $('ul:first', reviews_block).show();
                    }
                    if (!ul.length) {
                        ul = $('<ul class="menu-v with-icon"></ul>');
                        parent_li.append(ul);
                    }
                    ul.append($('<li data-id="'+r.data.id+'" data-parent-id="'+r.data.parent_id+'"></li>').append(html));

                    // back form to 's-review-add' place and clear
                    $('textarea', $.product_reviews.form).val('');
                    var acceptor = $('#s-review-add');
                    if (!acceptor.find('form').length) {
                        acceptor.append($.product_reviews.form);
                        parent_id_input.val(0);
                    }
                    if (sidebar_counter.length) {
                        sidebar_counter.text(parseInt(sidebar_counter.text(), 10) + 1);
                    }
                    if (typeof success === 'function') {
                        success(r);
                    }
                    $.product_reviews.clear();
                },
            'json')
            .error(function(r) {
                if (console) {
                    console.log(r.responseText ? 'Error occured: ' + r.responseText : 'Error occured.');
                }
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
                        review_div.addClass('s-deleted');
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
                        review_div.removeClass('s-deleted');
                        review_div.find('.s-review-delete').show();
                        review_div.find('.s-review-restore').hide();
                        if (sidebar_counter.length) {
                            sidebar_counter.text(parseInt(sidebar_counter.text(), 10) + 1);
                        }
                        if (typeof success === 'function') {
                            success();
                        }
                    }
                },
            'json');
        },

        clear: function(clear_inputs) {
            clear_inputs = typeof clear_inputs === 'undefined' ? true : clear_inputs;
            $('.errormsg', this.form).remove();
            $('.error',    this.form).removeClass('error');
            $('.wa-captcha-refresh', this.form).click();
            if (clear_inputs) {
                $('input[name=captcha], textarea', this.form).val('');
                $('input[name=rate]', this.form).val(0);
                $('.rate a:first', this.form).trigger('clear');
            }
        },

        showErrors: function(errors) {
            for (var i = 0, n = errors.length, errs = errors[i]; i < n; errs = errors[++i]) {
                for (var name in errs) {
                    $('[name='+name+']', this.form).
                        after($('<em class="errormsg"></em>').text(errs[name])).
                        addClass('error');
                }
            }
        }
    };
})(jQuery);