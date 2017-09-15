( function($) {

    $.order = {

        /**
         * {Number}
         */
        id: 0,

        /**
         * {Number}
         */
        contact_id: null,

        /**
         * Jquery object related to order info container {Object|null}
         */
        container: null,

        /**
         * {Object}
         */
        options: {},

        init: function (options) {
            this.options = options || {};
            if (options.order) {
                this.id = parseInt(options.order.id, 10) || 0;
                this.contact_id = parseInt(options.order.contact_id, 10) || null;
            }
            this.container = $('#s-order');
            if (!this.container.length) {
                this.container = $('#s-content');
            }

            if (options.dependencies) {
                this.initDependencies(options.dependencies);
            }

            if (options.title) {
                document.title = options.title;
            }

            this.initView();

            if ( $("#order-list").length ) {
                this.initScroll();
            }

            // workflow
            // action buttons click handler
            $('.wf-action').click(function () {
                var self = $(this);
                if (!self.data('confirm') || confirm(self.data('confirm'))) {
                    if (!self.data('running')) {
                        self.data('running', true);
                        self.after('<i class="icon16 loading"></i>');
                        $.post('?module=workflow&action=prepare', {
                            action_id: self.attr('data-action-id'),
                            id: $.order.id
                        }, function (response) {
                            self.data('running', false);
                            var el;
                            self.parent().find('.loading').remove();
                            if (self.data('container')) {
                                el = $(self.data('container'));
                                el.prev('.workflow-actions').hide();
                            } else {
                                self.closest('.workflow-actions').hide();
                                el = self.closest('.workflow-actions').next();
                            }
                            el.empty().html(response).show();
                        });
                    }
                }
                return false;
            });

            $('#s-content .js-printable-docs :checkbox').each(function () {
                var $this = $(this);
                var checked = $.storage.get('shop/order/print/' + $this.data('name'));
                if (checked === null) {
                    checked = true;
                }
                $this.attr('checked', checked ? 'checked' : null);
            });

            $('#s-content .js-printable-docs .js-printable-docs-send').click(function () {
                var $this = $(this);
                var name = $this.parents('li:first').text().replace(/(^[\r\n\s]+|[\r\n]+|[\r\n\s]+$)/g,'');
                if(confirm($_('%s will be sent to customer by email. Are you sure?').replace(/%s/,name))) {
                    var plugin_id = $this.data('plugin');
                    var url = $this.data('url');
                    var $icon = $this.find('.icon16:first');
                    $icon.removeClass('email').addClass('loading');
                    $.post(
                        $this.data('url'),
                        {
                            order_id: $this.data('order-id')
                        },
                        function (data) {
                            $icon.removeClass('loading');
                            if (data.status == 'ok') {
                                $icon.addClass('yes');
                                setTimeout(function () {
                                    $.order.reload();
                                }, 1000);

                            } else {
                                if(data.status=='fail'){
                                    if(data.errors){
                                        var title = [];
                                        for(var i =0; i<=data.errors.length;i++){
                                            if(data.errors[i]) {
                                                title.push(data.errors[i][0]);
                                            }
                                        }
                                        $icon.attr('title',title.join(' \n'));
                                    }
                                }
                                $icon.addClass('no');
                            }
                            setTimeout(function () {
                                $icon.removeClass('yes no').addClass('email').attr('title',null);
                            }, 5000);
                        },
                        'json'
                    ).error(function () {
                        $icon.removeClass('loading').addClass('no');
                        setTimeout(function () {
                            $icon.removeClass('no').addClass('email');
                        }, 3000);
                    });
                }
                return false;
            });

            $(':button.js-printable-docs').click(function () {
                $('#s-content .js-printable-docs :checkbox').each(function () {
                    var $this = $(this);
                    var checked = $this.is(':checked');
                    $.storage.set('shop/order/print/' + $this.data('name'), checked);
                    if (checked) {
                        window.open($(this).val(), $(this).data('target').replace(/\./, '_'));
                    }
                });
                return false;
            });
        },

        initView: function () {
            var edit_order_link = this.container.find('.s-edit-order');
            edit_order_link.attr('href', '#/orders/edit/' + $.order.id + '/');
            edit_order_link.click(function () {
                var confirm_text = $(this).data('confirm');
                $.shop.trace('confirm',[!confirm_text,confirm_text]);
                if (!confirm_text || confirm(confirm_text)) {
                    edit_order_link.find('.loading').show();
                } else {
                    return false;
                }
            });
            this.container.find('h1 .back.read-mode').click(function () {
                $.order.reload();
                return false;
            });
            if (this.options.order) {
                if ($.order_list) $.order_list.updateListItems(this.options.order);
                var container = ($.order_list.container || $("#s-content"));
                container.find('.selected').removeClass('selected');
                container.find('.order[data-order-id=' + this.options.order.id + ']').
                addClass('selected');
                if (this.options.offset === false) {
                    $.order_list.hideListItems(this.id);
                }

            }

            // adjust order content height to height of view-port when 'split' order list
            $(window).unbind('resize.order');
            if ($.order_list && $.order_list.options.view == 'split') {
                (function () {
                    var win = $(window);
                    var container = $('#s-order').find('.s-order');
                    if (container.length) {
                        var top = container.offset().top;
                        var height = win.height() - top;   // height of view-port
                        if (height > container.height()) {
                            container.height(height);
                        }
                        win.bind('resize.order', function () {
                            var height = $(this).height() - top;   // height of view-port
                            if (height > container.height()) {
                                container.height(height);
                            }
                        });
                    }
                })();
            }
        },

        initDependencies: function (options) {
            for (var n in options) {
                if (options.hasOwnProperty(n)) {
                    if (!$[n]) {
                        var msg = "Can't find " + n;
                        if (console) {
                            console.log(msg);
                        } else {
                            throw msg;
                        }
                        return;
                    }
                    if (options[n] === true) {
                        options[n] = {};
                    }
                    $[n].init(options[n]);
                }
            }
        },

        reload: function () {
            $.order_edit.slide(false);
            if (!$.order_list || ($.order_list && $.order_list.options && $.order_list.options.view == 'table')) {
                $.orders.dispatch();
            } else if ($.order_list) {
                $.order_list.dispatch('id=' + $.order.id, true);
            }
        },

        initScroll: function() {
            // Class names
            var top_fix_class = "fixed-to-top",
                bottom_fix_class = "fixed-to-bottom";

            // DOM
            var $window = $(window),
                $wrapper = $("#maincontent"),
                $block = $("#s-split-order-wrapper");

            // VARS
            var display_width = Math.floor( $window.width() ),
                display_height = Math.floor( $window.height() ),
                block_top = $block.offset().top,
                wrapper_margin_top = ( parseInt( $wrapper.css("margin-top") ) || 0 ),
                set_force = true;

            // DYNAMIC VARS
            var is_top_set = false,
                is_fixed_to_bottom = false,
                is_fixed_to_top = false,
                is_fixed_top_set = false,
                scroll_value = 0,
                block_width;

            $window
                .on("scroll", setScrollWatcher)
                .on("resize", setResizeWatcher);

            function setScrollWatcher() {
                if ($.contains(document, $block[0])) {
                    onScroll();
                } else {
                    unsetScrollWatcher();
                }
            }

            function setResizeWatcher() {
                if ($.contains(document, $block[0])) {
                    onResize();
                } else {
                    unsetResizeWatcher();
                }
            }

            function unsetScrollWatcher() {
                $window.off("scroll", setScrollWatcher);
            }

            function unsetResizeWatcher() {
                $window.off("scroll", setResizeWatcher);
            }

            function setTop( top ) {
                $.shop.trace("Manual top scroll position");

                $block
                    .css("top", top)
                    .width(block_width)
                    .removeClass(top_fix_class)
                    .removeClass(bottom_fix_class);

                is_top_set = true;
                is_fixed_to_top = is_fixed_to_bottom = is_fixed_top_set = false;
            }

            function setFixTop( top ) {
                $.shop.trace("Fixed to top scroll position");

                $block
                    .removeAttr("style")
                    .width(block_width)
                    .removeClass(bottom_fix_class)
                    .addClass(top_fix_class);

                if (top) {
                    is_fixed_top_set = true;
                    $block.css("top", top);
                }

                is_top_set = is_fixed_to_bottom = false;
                is_fixed_to_top = true;
            }

            function setFixBottom() {
                $.shop.trace("Fixed to bottom scroll position");

                $block
                    .removeAttr("style")
                    .width(block_width)
                    .removeClass(top_fix_class)
                    .addClass(bottom_fix_class);

                is_top_set = is_fixed_to_top = is_fixed_top_set = false;
                is_fixed_to_bottom = true;
            }

            function setDefault() {
                $.shop.trace("Default scroll position");

                $block
                    .removeAttr("style")
                    .removeClass(bottom_fix_class)
                    .removeClass(top_fix_class);

                is_top_set = is_fixed_to_top = is_fixed_to_bottom = is_fixed_top_set = false;
            }

            function onScroll() {
                var block_height = Math.floor( $block.outerHeight() ),
                    wrapper_height = Math.floor( $wrapper.height() ),
                    scroll_top = $window.scrollTop(),
                    dynamic_block_top = Math.floor( $block.offset().top ),
                    direction = ( scroll_value > scroll_top ) ? 1 : -1,
                    delta = scroll_top - block_top,
                    min_width = 760;

                block_width = $block.width();

                var active_scroll = ( !set_force && wrapper_height > display_height && display_width >= min_width);

                if (!active_scroll) {
                    if (set_force) {
                        setForceTop(scroll_top, block_height);
                    } else {
                        setDefault();
                        unsetScrollWatcher();
                        unsetResizeWatcher();
                    }
                } else {

                    // wait while loading new content and increase height
                    if ( wrapper_height <= block_height ) {
                        return false;
                    }

                    var is_display_longer_block = ( display_height > block_height + wrapper_margin_top ),
                        is_above_block = (scroll_top <= block_top),
                        my_case = parseInt(dynamic_block_top + block_height - scroll_top - display_height),
                        is_middle_of_block = ( my_case > 0 ),
                        is_bottom_of_block = ( my_case <= 0 );

                    // If the height of the slider is smaller than the display, it's simple
                    if (is_display_longer_block) {

                        if (delta + wrapper_margin_top > 0) {
                            if (is_top_set || is_fixed_to_bottom || !is_fixed_to_top) {
                                setFixTop( wrapper_margin_top );
                            }
                        } else {
                            if (is_top_set || is_fixed_to_top || is_fixed_to_bottom || is_fixed_top_set) {
                                setDefault();
                            }
                        }

                    // If the height is larger than the screen
                    } else {

                        // If less than the original position to turn off
                        if (is_above_block) {
                            // console.log( 0 );
                            if (is_top_set || is_fixed_to_bottom || is_fixed_to_top) {
                                if (is_fixed_top_set) {
                                    var use_default = (dynamic_block_top <= block_top);
                                    if (use_default) {
                                        setDefault();
                                    }
                                } else {
                                    setDefault();
                                }
                            }

                        // If the above start after scrolling fix up
                        } else if (is_middle_of_block) {

                            if (direction > 0) {
                                var set_fix_top = (dynamic_block_top >= (wrapper_margin_top + scroll_top) );
                                if (set_fix_top && ( is_top_set || !is_fixed_to_top || is_fixed_to_bottom ) ) {
                                    if (wrapper_margin_top) {
                                        setFixTop( wrapper_margin_top );
                                    } else {
                                        setFixTop();
                                    }
                                }
                            } else {
                                if (!is_top_set || is_fixed_to_top || is_fixed_to_bottom) {
                                    setTop( dynamic_block_top - block_top );
                                }
                            }

                        // If the lower end
                        } else if (is_bottom_of_block) {
                            // If the direction of scrolling up
                            if (direction > 0) {
                                if (!is_top_set || is_fixed_to_top || is_fixed_to_bottom) {
                                    setTop( dynamic_block_top - block_top );
                                }

                            // If the direction of scrolling down
                            } else {
                                if (is_top_set || is_fixed_to_top || !is_fixed_to_bottom) {
                                    setFixBottom();
                                }
                            }
                        // In all other cases
                        } else {
                            if (!is_top_set || is_fixed_to_top || is_fixed_to_bottom) {
                                setTop( dynamic_block_top - block_top );
                            }
                        }

                    }
                }

                // Save New Data
                scroll_value = scroll_top;
            }

            function setForceTop(scroll_top, block_height) {
                var wrapper_height = Math.floor( $wrapper.height() ),
                    wrapper_top = Math.floor( $wrapper.offset().top ),
                    space_after = wrapper_height + wrapper_top - display_height - scroll_top,
                    hidden_block_part = block_height - display_height;

                set_force = false;

                var use_force = ( wrapper_height > block_height && scroll_top > block_top);

                if (use_force) {
                    if (hidden_block_part < space_after) {
                        setFixTop( wrapper_margin_top );
                    } else {
                        setFixBottom();
                    }
                }
            }

            function onResize() {
                display_width = Math.floor( $window.width() );
                display_height = Math.floor( $window.height() );
                setDefault();
                $window.trigger("scroll");
            }

        }
    };

})(jQuery);