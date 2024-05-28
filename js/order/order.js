( function($) {

    var MarkingDialog = ( function($) {

        function Dialog(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$form = that.$wrapper.find("form:first");

            // CONST
            that.user_has_rights = options["user_has_rights"];
            that.dialog = that.$wrapper.data("dialog");
            that.locales = options["locales"];
            that.scope = options["scope"];
            that.urls = options["urls"];

            that.item_id = (that.dialog.options && that.dialog.options.item_id ? that.dialog.options.item_id : null);
            that.code_id = (that.dialog.options && that.dialog.options.code_id ? that.dialog.options.code_id : null);

            that.is_changed = false;

            // VARS
            that.plugins = {};

            // INIT
            that.init();
        }

        Dialog.prototype.init = function() {
            var that = this;

            var ready_promise = that.$wrapper.data("ready");
            ready_promise.resolve(that);
            that.$wrapper
                .data("controller", that)
                .trigger("ready", that);

            if (!that.user_has_rights) {
                that.$wrapper.find('.s-field').prop('disabled', true);
                return;
            }

            that.$form.on("change", function() {
                that.is_changed = true;
            });

            that.dialog.onClose = function() {
                var result = true;

                if (that.is_changed) {
                    result = confirm(that.locales["unsaved"]);
                }

                return result;
            };

            initFocusActions();

            that.initSubmit();

            function initFocusActions() {
                var $products = that.$wrapper.find(".s-product-wrapper");

                that.$form.on("keydown", "input", function(event) {
                    var $field = $(this),
                        key = event.keyCode,
                        is_enter = ( key === 13 );

                    if (is_enter) {
                        event.preventDefault();

                        var use_focus = null;

                        var $fields = $products.find(".s-field");
                        $fields.each( function(i) {
                            var $_field = $(this),
                                is_last = ($fields.length === i + 1);

                            // is next, use focus
                            if (use_focus) {
                                $_field.trigger("focus");
                                use_focus = false;
                                return false;

                                // is current, set flag
                            } else {

                                if ($_field[0] === $field[0]) {
                                    use_focus = !is_last;
                                }
                            }
                        });

                        // next is not exist, blur
                        if (use_focus === false) {
                            $field.trigger("blur");
                        }
                    }
                });

                var $active_product = $products.first();

                if (that.item_id) {
                    // When user opens dialog by clicking under a particular order item, jump right to it
                    $products.each( function() {
                        var $_product = $(this),
                            item_id = $_product.data("item-id");

                        if (that.item_id === item_id) {
                            $active_product = $_product;
                            return false;
                        }
                    });
                }

                setTimeout( function() {
                    var $focus_field = getFocusField($active_product, that.code_id);
                    if ($focus_field) {
                        $focus_field.trigger("focus");
                    }
                }, 100);

                function getFocusField($product, focus_code_id) {
                    var $codes = $product.find(".s-code-wrapper"),
                        $focus_field = null;

                    $codes.each(function() {
                        var $code = $(this),
                            $field = $code.find(".s-field"),
                            code_id = $code.data("code-id"),
                            is_empty = !$.trim($field.val()).length;

                        if (is_empty) {
                            if (code_id === focus_code_id) {
                                $focus_field = $field;
                                return false;

                            } else {
                                if (!$focus_field) {
                                    $focus_field = $field;
                                }
                            }
                        }
                    });

                    return $focus_field;
                }
            }
        };

        Dialog.prototype.initSubmit = function() {
            var that = this,
                $form = that.$form,
                $submit_button = that.$wrapper.find(".js-submit-button"),
                $errorsPlace = that.$wrapper.find(".js-errors-place"),
                is_locked = false;

            $form.on("submit", onSubmit);

            function onSubmit(event) {
                event.preventDefault();

                var $prepare_message = null;

                if (!is_locked) {
                    var counter = Object.keys(that.plugins).length;
                    if (counter) {
                        var reject_count = 0,
                            promises = [];

                        is_locked = true;

                        $.each(that.plugins, function(i, plugin) {
                            if (typeof plugin.onSubmit === "function") {
                                plugin.onSubmit().then(onSuccess, onReject);
                            }
                        });

                    } else {
                        onSubmit();
                    }
                }

                function onSuccess() {
                    counter -= 1;
                    watcher();
                }

                function onReject() {
                    reject_count += 1;
                    counter -= 1;
                    watcher();
                }

                function watcher() {
                    if (counter === 0) {
                        is_locked = false;

                        if (!reject_count) {
                            onSubmit();
                        } else {
                            //console.log("REJECT");
                        }
                    }
                }

                function onSubmit() {
                    is_locked = true;

                    $submit_button.attr("disabled", true);
                    var $loading = $('<i class="fas fa-spinner fa-spin custom-ml-4 loading" />').appendTo($submit_button);

                    var formData = getData();
                    if (formData.errors.length) {
                        showErrors(formData.errors);

                    } else {
                        request(formData.data)
                            .always( function() {
                                $submit_button.attr("disabled", false);
                                $loading.remove();
                                is_locked = false;
                            })
                            .done( function() {
                                that.is_changed = false;
                                that.dialog.close();
                                that.scope.reload();
                            });
                    }
                }
            }

            function getData() {
                var result = {
                        data: [],
                        errors: []
                    },
                    data = $form.serializeArray();

                $.each(data, function(index, item) {
                    result.data.push(item);
                });

                return result;
            }

            function showErrors(errors) {
                var error_class = "error";

                if (!errors || !errors[0]) {
                    errors = [];
                }

                $.each(errors, function(index, item) {
                    var name = item.name,
                        text = item.value;

                    var $field = that.$wrapper.find("[name=\"" + name + "\"]"),
                        $text = $("<span class='s-error errormsg' />").text(text);

                    if ($field.length && !$field.hasClass(error_class)) {
                        $field.parent().append($text);

                        $field
                            .addClass(error_class)
                            .one("focus click change", function() {
                                $field.removeClass(error_class);
                                $text.remove();
                            });
                    } else {
                        $errorsPlace.append($text);

                        $form.one("submit", function() {
                            $text.remove();
                        });
                    }
                });
            }

            function request(data) {
                var deferred = $.Deferred();

                $.post(that.urls["submit"], data, "json")
                    .done( function(response) {
                        if (response.status === "ok") {
                            deferred.resolve();
                        } else {
                            showErrors(response.errors);
                            deferred.reject();
                        }
                    })
                    .fail( function() {
                        deferred.reject();
                    });


                return deferred.promise();
            }
        };

        Dialog.prototype.initPlugin = function(options) {
            var that = this;

            //console.log("PLUGIN INIT", options);

            if (!options.plugin_id) {
                var message_1 = "Error registering plugin. Plugin Identifier is missing.";
                console.log(message_1);
                return false;
            }

            var onSubmit = ( typeof options["onSubmit"] === "function" ? options["onSubmit"] : null);
            if (!onSubmit) {
                var message_2 = "Error registering plugin. Promise (onSubmit) is required to register a rule.";
                console.log(message_2);
                return false;
            }

            that.plugins[options.plugin_id] = {
                onSubmit: onSubmit,
                onErrors: (typeof options.onErrors === "function" ? options.onErrors : null)
            };
        };

        return Dialog;

    })(jQuery);

    $.order = {

        $wrapper: null,

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
            var that = this;

            that.$wrapper = options["$wrapper"];

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

            this.initAdaptiveAuxSidebar();

            this.initNameControl();

            this.initChecklistItems(options);

            // workflow
            // action buttons click handler
            $('.wf-action').on('click', function () {
                var self = $(this);
                if (self.data('running')) {
                    return false;
                }
                if (self.data('action-unavailable-reason')) {
                    alert(self.data('action-unavailable-reason'));
                    return false;
                }
                if (self.data('confirm') && !confirm(self.data('confirm'))) {
                    return false;
                }

                self.data('running', true);
                self.addClass('opacity-70');
                var $icon = $('.js-icon', self).hide().after('<i class="fas fa-spinner wa-animation-spin custom-mr-4 small text-gray"></i>');
                var action_id = self.attr('data-action-id');
                $.post('?module=workflow&action=prepare', {
                    action_id,
                    id: $.order.id
                }, function (response) {
                    $('.wa-animation-spin', self).remove();
                    $icon.show();
                    self.removeClass('opacity-70');
                    self.data('running', false);

                    if (self.data('form-in-dialog')) {
                        $.waDialog({
                            html: response,
                            debug_output: true,
                            options: {
                                item_id: self.data("item-id"),
                                code_id: self.data("code-id")
                            }
                        });
                    } else {
                        var el,
                            has_form = $.trim(response) && $(response).prop("tagName") !== 'SCRIPT';
                        if (self.data('container')) {
                            el = $(self.data('container'));
                            el.prev('.workflow-actions').hide();
                        } else {
                            if (has_form) {
                                self.closest('.workflow-actions').hide();
                            }
                            el = self.closest('.workflow-actions').next();
                        }

                        el.empty().html(response);
                        if (has_form) {
                            el.show();
                        }

                        // set focus on comment field(textarea)
                        if (action_id === 'comment') {
                            el.find('textarea').focus();
                        }
                    }
                });
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

            $('.js-copy-btn').on('click', function() {
                const $self = $(this),
                    initial_title = $self.data('initial-title'),
                    title_copied = $self.data('title-copied'),
                    url = $self.data('url');

                $self.html(`<i class="fas fa-check"></i> ${title_copied}`)
                    .attr('disabled', true)
                    .addClass('green');

                $.wa.copyToClipboard(url);

                setTimeout(() => {
                    $self.text(initial_title)
                        .removeClass('green')
                        .attr('disabled', false);
                }, 1000);
            });
        },

        initView: function () {
            var edit_order_link = this.container.find('.s-edit-order');
            edit_order_link.attr('href', '#/orders/edit/' + $.order.id + '/');
            edit_order_link.click(function () {
                var $self = $(this);
                var alert_text = $self.data('action-unavailable-reason');
                if (alert_text) {
                    alert(alert_text);
                    return false;
                }
                var confirm_text = $self.data('confirm');
                if (!confirm_text || confirm(confirm_text)) {
                    edit_order_link.find('.loading').show();
                } else {
                    return false;
                }
            });
            this.container.find('#s-order-title .back.read-mode').click(function () {
                $.order.reload();
                return false;
            });
            if (this.options.order) {
                if ($.order_list) {
                    $.order_list.updateListItems(this.options.order);
                }
                var container = ($.order_list.container || $("#s-content"));
                container.find('.selected').removeClass('selected');
                container.find('.order[data-order-id=' + this.options.order.id + ']').
                addClass('selected');
                if (this.options.offset === false) {
                    $.order_list.hideListItems(this.id);
                }

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
            if (!$.order_list || ($.order_list && $.order_list.options && ['table', 'kanban'].includes($.order_list.options.view))) {
                $.orders.dispatch();
            } else if ($.order_list) {
                $.order_list.dispatch('id=' + $.order.id, true);
            }
        },

        initMarkingDialog: function(options) {
            options["scope"] = this;
            return new MarkingDialog(options);

        },

        initAdaptiveAuxSidebar: function() {
            const $aux_sidebar = this.$wrapper.find('.sidebar.s-order-aux');

            $('.js-more-details', this.$wrapper).on('click', function() {
                $aux_sidebar.removeClass('hide-animation').addClass('show');
                setTimeout(() => $aux_sidebar.addClass('show-animation'));
            })

            $('.js-close-aux-sidebar', $aux_sidebar).on('click', function() {
                $aux_sidebar.addClass('hide-animation');
                setTimeout(() => $aux_sidebar.removeClass('show').removeClass('show-animation'), 250);
            })
        },

        initNameControl: function () {
            var $js_edit_item_name = $('.js-edit-item-name'),
                $js_save_item_name = $('.js-save-item-name'),
                $js_close_item_name = $('.js-close-item-name'),
                $s_product_basic_info = null,
                $js_item_name_label = null,
                $js_item_name_field = null,
                $js_save_name_error = null;

            function findControls($that) {
                $s_product_basic_info = $that.parents('.s-product-basic-info'),
                $js_item_name_label = $s_product_basic_info.find('.js-item-name-label'),
                $js_item_name_field = $s_product_basic_info.find('.js-item-name-field'),
                $js_save_name_error = $s_product_basic_info.find('.js-save-name-error');
            }

            $js_edit_item_name.click(function() {
                var $that = $(this);

                findControls($that);
                $js_item_name_field.val($js_item_name_label.text());
                $that.add($js_item_name_label).hide();
                $js_item_name_field.add($that.siblings('.js-save-item-name')).add($that.siblings('.js-close-item-name')).show();
            });

            $js_save_item_name.click(function () {
                var $that = $(this);

                findControls($that);
                $.ajax({
                    type: "POST",
                    url: '?module=order&action=ItemNameSave',
                    data: {
                        id: $that.parents('tr.s-product-wrapper').data('id'),
                        name: $js_item_name_field.val()
                    },
                    success: function (response) {
                        if (response.data !== undefined) {
                            $js_item_name_label.html(response.data.name);
                        }
                        if (response.errors !== undefined) {
                            $js_save_name_error.html(response.errors[0].text).show();
                        } else {
                            $that.siblings('.js-edit-item-name').add($js_item_name_label).show();
                            $that.add($js_item_name_field).add($that.siblings('.js-close-item-name')).add($js_save_name_error).hide();
                        }
                    }
                });

            });

            $js_close_item_name.click(function () {
                var $that = $(this);

                findControls($that);
                $that.siblings('.js-edit-item-name').add($js_item_name_label).show();
                $that.add($js_item_name_field).add($that.siblings('.js-save-item-name')).add($js_save_name_error).hide();
            });
        },

        initChecklistItems: function (options) {
            const storage_key = `shop/order/${options.order.id}/fulfilmented_items`;
            const animation_timeout = 300;
            const $table = $('#s-order-items');
            const $counter = $table.find('.js-fulfilment-mode-counter');

            if (!$.order.hasOwnProperty('is_opened_items_checklist')) {
                $.order.is_opened_items_checklist = false;
            }

            init();

            const toggleFulfilmentMode = () => {
                $counter.toggleClass('hidden');
                $table.toggleClass('is-visible');
            };
            $('.js-show-fulfilment-mode').on('click', function () {
                const $self = $(this);
                let $overlay = $('.overlay-on-full-page');
                if ($overlay.length) {
                    $overlay.remove();
                    $.order.is_opened_items_checklist = false;
                } else {
                    $overlay = $('<div class="overlay-on-full-page" />');
                    $('body').prepend($overlay);
                    setTimeout(() => {
                        $overlay.addClass('is-visible');
                    }, 10);
                    $.order.is_opened_items_checklist = true;
                    $overlay.one('click', function () {
                        $self.trigger('click');
                    });

                    setTimeout(() => {
                        const scrollY = this.getBoundingClientRect().top + window.scrollY;
                        window.scrollTo({ top: scrollY - 80 });
                    });
                }
                toggleFulfilmentMode();
            });
            if ($.order.is_opened_items_checklist) {
                toggleFulfilmentMode();
            }

            getItems().find('[name="order_item"]:checkbox').on('change', function () {
                const $checkbox = $(this);
                const $cur_item = $checkbox.closest('tr[data-id]');
                const item_type = $cur_item.data('type');
                const current_id = $cur_item.data('id');
                const $cur_items = $cur_item.add(getItems().filter(`tr[data-parent="${current_id}"]`));
                let $items = getItems();
                if (item_type === 'service') {
                    $items = $items.filter(`[data-parent="${$cur_item.data('parent')}"]`);
                }

                const list_height = $('#s-order-items > tbody').innerHeight();
                const elem_top = $cur_items.first().position().top;
                let elem_height = getSumHeight($cur_items);

                const is_checked = $checkbox.is(':checked');
                if (!is_checked && $items.filter('.is-fulfilmented').length === 1) {
                    $cur_item.removeClass('is-fulfilmented');
                    removeItemAssembly(current_id);
                    return false;
                }

                moveItems($items, $.map($cur_items, item => item.dataset.id), elem_height, is_checked);

                const resetStyles = () => getItems().attr("style", "");
                const last_id = $cur_items.last().data('id');
                if (is_checked) {
                    $cur_item.addClass('is-fulfilmented');
                    saveItemAssembly(current_id);
                    const move_down = item_type === 'service' ? getServicesHeight($items, current_id, true) : (list_height - elem_top - elem_height);
                    $cur_items.animate({ top: '+=' + move_down }, animation_timeout, function() {
                        const $_item = $(this);
                        if (last_id === $_item.data('id')) {
                            resetStyles();
                        }
                        if ($_item.data('id') !== current_id) {
                            return;
                        }

                        const $last_item = $items.filter(':last');
                        if ($last_item.data('id') !== current_id && $last_item.data('parent') !== current_id) {
                            $last_item.after($cur_items.detach());
                        }
                    });

                } else {
                    removeItemAssembly(current_id);
                    const move_up = item_type === 'service' ? getServicesHeight($items, current_id) : list_height - (list_height - elem_top);
                    $cur_items.animate({ top: '-=' + move_up }, animation_timeout, function() {
                        const $_item = $(this);
                        if (last_id === $_item.data('id')) {
                            resetStyles();
                        }
                        if ($_item.data('id') !== current_id) {
                            return;
                        }

                        $_item.removeClass('is-fulfilmented');

                        const $first_item = $items.filter(':first');
                        if ($first_item.data('id') !== current_id) {
                            $first_item.before($cur_items.detach());
                        }
                    });
                }
            });

            /**
             *
             * @returns {jQuery}
             */
            function getItems () {
                return $table.find('tr[data-id]');
            }

            /**
             *
             * @returns {Array|null}
             */
            function getFulfilmentedItemsStorage () {
                return $.storage.get(storage_key);
            }

            function init () {
                let has_fulfilmented = false;
                let count_selected = 0;
                const fulfilmented_items = getFulfilmentedItemsStorage();
                if (Array.isArray(fulfilmented_items) && fulfilmented_items.length) {
                    const checked_items_map = fulfilmented_items.reduce((acc, id) => {
                        acc[id] = id;
                        return acc;
                    }, {});

                    const $order_items = getItems();
                    let $last_item = $order_items.filter(':last');
                    $order_items.each(function () {
                        const $item = $(this);
                        const id = $item.data('id');

                        if (checked_items_map[id]) {
                            $item.addClass('is-fulfilmented');
                            $item.find(':checkbox[name="order_item"]').prop('checked', true);

                            if ($item.data('type') === 'product' && $last_item.data('id') !== $item.data('id')) {
                                $item.detach();
                                $item.insertAfter($last_item);
                                $last_item = $item;
                            }

                            delete checked_items_map[id];
                            has_fulfilmented = true;
                            count_selected += 1;
                        }
                    });

                    sortServices();

                    const not_founded_ids = Object.values(checked_items_map);
                    if (not_founded_ids.length) {
                        if (not_founded_ids.length === fulfilmented_items.length) {
                            $.storage.del(storage_key);
                        } else {
                            $.storage.set(storage_key, fulfilmented_items.filter(id => !not_founded_ids.includes(id)));
                        }
                    }
                }
                updateCounter(count_selected);

                return has_fulfilmented;
            }

            function saveItemAssembly (id, remove = false) {
                let items = getFulfilmentedItemsStorage();
                if (!Array.isArray(items)) {
                    items = [];
                }

                if (remove) {
                    items = items.filter(_id => _id !== id);
                } else {
                    items.push(id);
                }

                if (!items.length) {
                    $.storage.del(storage_key);
                    updateCounter(0);
                    return null;
                }

                const unique_ids = Array.from(new Set(items));
                $.storage.set(storage_key, unique_ids);
                updateCounter(unique_ids.length);
            }
            function removeItemAssembly (id) {
                saveItemAssembly(id, true);
            }

            function moveItems ($items, current_ids, elem_height, direction_up = false) {
                if (direction_up) {
                    $($items.get().reverse()).each(function() {
                        if (current_ids.includes(this.dataset.id)) {
                            return false;
                        }
                        $(this).animate({ top: '-=' + elem_height }, animation_timeout);
                    });
                } else {
                    $items.each(function() {
                        if (current_ids.includes(this.dataset.id)) {
                            return false;
                        }
                        $(this).animate({ top: '+=' + elem_height }, animation_timeout);
                    });
                }
            }

            function sortServices () {
                const getParent = (parent_id) => {
                    return getItems().filter(`[data-id="${parent_id}"]`);
                };
                const getLastService = (parent_id, fulfilmented = false) => {
                    const $need_item = getItems()
                        .filter(`.is-sorted[data-parent="${parent_id}"]${fulfilmented ? '.is-fulfilmented' : ':not(.is-fulfilmented)'}:last`);
                    return $need_item;
                };

                let prev_parent_id = null;
                getItems().filter('[data-type="service"]:not(.is-fulfilmented)').each(function () {
                    const $service = $(this);
                    $service.addClass('is-sorted').detach();

                    const parent_id = $service.data('parent');
                    let $need_item;
                    if (parent_id === prev_parent_id) {
                        $need_item = getLastService(parent_id);
                    } else {
                        $need_item = getParent(parent_id);
                    }

                    prev_parent_id = parent_id;
                    $service.insertAfter($need_item);
                });

                prev_parent_id = null;
                getItems().filter('[data-type="service"].is-fulfilmented').each(function () {
                    const $service = $(this);
                    $service.addClass('is-sorted').detach();

                    const parent_id = $service.data('parent');
                    let $need_item;
                    if (parent_id === prev_parent_id) {
                        $need_item = getLastService(parent_id, true);
                    } else {
                        $need_item = getLastService(parent_id);
                        if (!$need_item.length) {
                            $need_item = getParent(parent_id);
                        }
                    }

                    prev_parent_id = parent_id;
                    $service.insertAfter($need_item);
                });
            }

            function getSumHeight ($items) {
                return $items.get().reduce((sum, item) => {
                    sum += item.getBoundingClientRect().height;
                    return sum;
                }, 0);
            }

            function getServicesHeight ($items, current_id, after_item = false) {
                let sum = 0;
                $items = after_item ? $($items.get().reverse()) : $items;
                $items.each(function () {
                    if ($(this).data('id') === current_id) {
                        return false;
                    }
                    sum += $(this).height();
                });

                return sum;
            }

            function updateCounter (count = 0) {
                const total = $table.find('> tbody > tr').length;
                let class_badge = 'gray';
                if (total === count) {
                    class_badge = 'green';
                } else if (count > 0) {
                    class_badge = 'blue';
                }
                $counter.text(`${count || 0}/${total}`);
                $counter.removeClass(['gray', 'blue', 'green']);
                $counter.addClass(class_badge);
            }
        },
    };

})(jQuery);
