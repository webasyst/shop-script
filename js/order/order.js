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

        // workflow
        // action buttons click handler
        $('.wf-action').click(function () {
            var self = $(this);
            if (!self.data('confirm') || confirm(self.data('confirm'))) {
                self.after('<i class="icon16 loading"></i>');
                $.post('?module=workflow&action=prepare', {
                    action_id: self.attr('data-action-id'),
                    id: $.order.id
                }, function (response) {
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
            edit_order_link.find('.loading').show();
        });
        this.container.find('h1 .back.read-mode').click(function () {
            $.order.reload();
            return false;
        });
        if (this.options.order) {
            $.order_list.updateListItems(this.options.order);
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
    }
};