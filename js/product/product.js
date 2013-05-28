(function($) {
    $.product = {
        options: {
            'duration': 200,
            'container_selector': '#shop-productprofile',
            'message_selector': '#product-save-message',
            'form_selector': '#s-product-save',
            'update_delay': 100
        },
        message: {
            'submit': '<i class="icon16 loading"></i>',
            'success': '<i class="icon16 yes"></i>',
            'error': '<i class="icon16 no"></i>'
        },

        path: {
            'id': null,/* new or int product ID */
            'mode': null,/* null|profile|edit */
            'tab': null,
            'tail': null,
            'raw': null,
            'params': {}
            /* main */
        },
        ajax: {
            random: null,
            target: null,
            link: null,
            save: false,
            cached: {}

        },
        data: {
            'main': {}
        },
        getData: function(section, name) {
            return (this.data[section] || {})[name];
        },
        setData: function(section, name, value) {
            this.data[section] = this.data[section] || {};
            this.data[section][name] = value;
        },

        changes: {},

        standalone_tabs: ['images', 'services', 'pages', 'reviews'],

        /**
         * Setup options
         *
         * @param {} options
         * @param String tab
         */
        init: function(options, tab) {
            this.setOptions(options, tab);
        },

        setOptions: function(options, tab) {
            if (tab) {
                this[tab + '_options'] = $.extend(
                    this[tab + '_options'] || {}, options || {}
                );
            } else {
                this.options = $.extend(this.options, options || {});
            }
        },

        get: function(type) {
            if (this.options[type + '_selector']) {
                return $(this.options[type + '_selector']);
            } else {
                throw new exception('');
            }
        },

        /**
         *
         * @param String path
         * @return {'id':Int,'mode':String,'tab':String,'tail':String,'raw':String,'params':{}}
         */
        parsePath: function(path) {
            path = path.replace(/^.*#\//, '').replace(/(^\/|\/$)/, '');

            var matches = path.split('/');
            var tail = matches.pop();
            var params = {};

            if (tail.match(/^[\w_\-]+=/)) {
                params = $.shop.helper.parseParams(tail);
            } else {
                matches.push(tail);
            }
            return {
                'id': matches[0] || 'new',
                'mode': matches[1] || 'profile',
                'tab': matches[1] ? matches[2] || 'main' : false,
                'tail': matches.slice(3).join('/') || '',
                'raw': path,
                'params': params
            };
        },

        /**
         *
         * @param String path
         */
        dispatch: function(path) {
            if (typeof(path) == 'string') {
                path = this.parsePath(path);
            }
            var queue = [];
            $.shop.trace('$.product.dispatch', [this.path, path, path.raw]);
            if (!this.path.id) {
                var container = this.get('container');
                if (container.length) {
                    this.path.id = container.data('product-id');
                    queue.push('load');
                }
            }
            if (this.path.id != path.id) {
                if (this.path.id) {
                    queue.push('blur');

                }
                queue.push('load');

            } else {
                var tab = 'undefined';
                if (this.path.mode != path.mode) {
                    $.shop.trace('$.product.dispatch mode', this.path.mode + '->' + path.mode);
                    if (this.path.mode && this.path.tab) {
                        $.shop.trace('$.product.dispatch tabBlur', this.path.tab + '->' + path.tab);
                        tab = this.path.tab.substr(0, 1).toUpperCase() + this.path.tab.substr(1);
                        queue.push(this.path.mode + 'Tab' + tab + 'Blur');
                        queue.push(this.path.mode + 'TabBlur');
                        queue.push(this.path.mode + 'Blur');
                    } else {
                        queue.push('focus');
                    }
                    queue.push(path.mode + 'Init');
                    queue.push(path.mode + 'Focus');
                } else if (this.path.tab && (path.tab != this.path.tab)) {
                    $.shop.trace('$.product.dispatch tabBlur', this.path.tab + '->' + path.tab);
                    tab = this.path.tab.substr(0, 1).toUpperCase() + this.path.tab.substr(1);
                    queue.push(path.mode + 'Tab' + tab + 'Blur');
                    queue.push(path.mode + 'TabBlur');
                }
                queue.push(path.mode + 'Action');
                var loaded = this.tabIsLoaded(path);
                if (path.tab) {
                    tab = path.tab.substr(0, 1).toUpperCase() + path.tab.substr(1);
                }

                if (path.tab && (path.tab != this.path.tab)) {
                    $.shop.trace('$.product.dispatch tabFocus', this.path.tab + '->' + path.tab);

                    if (loaded) {
                        queue.push(path.mode + 'TabShow');
                        queue.push(path.mode + 'TabInit');
                        queue.push(path.mode + 'Tab' + tab + 'Init');
                        queue.push(path.mode + 'TabFocus');
                        queue.push(path.mode + 'Tab' + tab + 'Focus');
                    } else {
                        // Check if it need save
                        queue.push(path.mode + 'TabFocus');
                        var loader = path.mode + 'Tab' + tab + 'Load';
                        if (this.isCallable(loader)) {
                            queue.push(loader);
                        } else {
                            queue.push(path.mode + 'TabLoad');
                        }
                    }

                }
                if (path.tab && loaded) {
                    queue.push(path.mode + 'Tab' + tab + 'Action');
                }
            }
            for (var i = 0; i < queue.length; i++) {
                // standard convention: if method return false than stop bubble up
                if (this.call(queue[i], [path]) === false) {
                    return false;
                }
            }
        },

        termination: function() {
            var queue = [];
            if (this.path.mode && this.path.tab) {
                var tab = this.path.tab.substr(0, 1).toUpperCase() + this.path.tab.substr(1);
                queue.push(this.path.mode + 'Tab' + tab + 'Blur');
                queue.push(this.path.mode + 'TabBlur');
                queue.push(this.path.mode + 'Blur');
            }
            queue.push('blur');

            for (var i = 0; i < queue.length; i++) {
                this.call(queue[i], []);
            }
        },

        isCallable: function(name) {
            return (typeof(this[name]) == 'function');
        },
        tabIsLoaded: function(path) {
            var $tab = $('#s-product-edit-forms .s-product-form.' + path.tab);
            return ($tab.length ? true : false);
        },

        call: function(name, args, callback) {
            var result = null;
            var callable = this.isCallable(name);
            args = args || [];
            $.shop.trace('$.product.call', [name, args, callable]);
            if (callable) {
                try {
                    result = this[name].apply(this, args);
                } catch (e) {
                    $.shop.error(
                        "Error at method $.product." + name +
                        ". Original message: " + e.message,
                        e
                    );
                }
            }
            return result;
        },
        load: function(path) {
            var $container = this.get('container');
            if (!$container.length || ($container.data('product-id') != path.id)) {
                var self = this;
                var url = '?module=product&id=' + path.id;
                $.shop.trace('$.product.load product', url);
                $.products.load(url, function() {
                    self.dispatch(path);
                });
            }

            this.editTabMainData.sku_id = (path.id == 'new') ? -1 : 0;
            this.editTabMainData.stocks = {};
        },

        focus: function() {
            $('*').off('.product');
            this.helper.init();
            // var self = this;
            /**
             * @todo use generic error handler for ajax pages
             */
            // $.wa.errorHandler = function(xhr) {
            // if ($.product.ajax.target && xhr.responseText) {
            // $.shop.trace('errorHandler', self.ajax.target);
            // $.product.ajax.target.empty().append($(xhr.responseText).find(':not(style)'));
            // }
            // if ($.product.ajax.link) {
            // $.product.ajax.link.find('.s-product-edit-tab-status').html('<i class="icon10 exclamation"></i>');
            // }
            // $.product.ajax.target = null;
            // $.product.ajax.link = null;
            // $.shop.error('ajax error', xhr);
            // return false;
            // };
        },

        blur: function() {
            this.path.id = null;
            this.path.mode = null;
            this.path.tab = null;
            this.path.tail = null;
            $('*').off('.product');

            $('#mainmenu .s-level2').show();
            $('#s-product-edit-menu, #s-product-edit-save-panel').hide();
            var duration = 'fast';

            $('#s-sidebar, #s-toolbar').show().animate({
                width: '200px'
            }, duration).queue(function() {
                $(this).dequeue();
            });
            $('#maincontent').animate({
                'margin-top': '84px'
            }, duration);
            $('#s-content').animate({
                'margin-left': '200px'
            }, duration);
        },

        saveData: function(mode, tab, callback) {
            var self = this;
            var form = self.get('form');

            var sku_type_input_type = 'radio';
            var sku_type_input = form.find('input[name="product[sku_type]"]:first');
            if (sku_type_input.is(':radio')) {
                sku_type = form.find('input[name="product[sku_type]"]:checked').val();
                sku_type_input = form.find('input[name="product[sku_type]"]');
            } else {
                sku_type_input_type = 'hidden';
                sku_type = sku_type_input.val();
            }

            if (sku_type == '1') {
                var any_checked = $('#s-product-feature-superposition').find('input:checked:first').length;
                if (!any_checked) {
                    if (sku_type_input_type == 'radio') {
                        sku_type_input.filter('[value=0]').attr('checked', true);
                        $.product.onSkuTypeChange(0);
                    }
                    return false;
                }
            }

            waEditorUpdateSource({
                id: 's-product-description-content'
            });

            if (self.ajax.save) {
                setTimeout(function() {
                    self.saveData(mode, tab, callback);
                }, 100);
                return false;
            }
            if (this.path.tab) {
                var save_method = 'editTab' + this.path.tab.substr(0, 1).toUpperCase() + this.path.tab.substr(1) + 'Save';
                if (this.call(save_method) === false) {
                    return false;
                }
            }
            self.ajax.save = true;

            // cut out all spaces for prices
            form.find('.s-price').find('input').each(function() {
                this.value = this.value.replace(/\s+/g, '');
            });

            $.shop.trace('$.product.saveData(' + mode + ',' + tab + ')');
            /* disable not changed data */
            $(form).find(':input[name^="product\["]:not(:disabled)').each(function() {
                var type = $(this).attr('type');
                if ((type != 'text') && (type != 'textarea')) {
                    return true;
                }

                if (this.defaultValue == this.value) {
                    $(this).attr('disabled', true).addClass('js-ajax-disabled');
                } else if ($(this).hasClass('js-ajax-disabled')) {
                    $(this).removeAttr('disabled').removeClass('js-ajax-disabled');
                }
            });
            this.refresh('submit');

            $('#product-tags_tag').trigger(jQuery.Event("keypress", {
                which: 13
            }));

            $.ajax({
                'url': $(form).attr('action'),
                'data': $(form).serialize(),
                'dataType': 'json',
                'type': 'post',
                success: function(response) {
                    if (response.status == 'fail') {
                        self.refresh('error', response.errors);
                    } else if (response.data.redirect) {
                        $.shop.trace('$.product.saveData redirect', response.data.redirect);
                        window.location.href = response.data.redirect;
                    } else {
                        self.refresh('success', response.data.message || '');
                        $.shop.trace('$.product.saveData updateData', [mode, tab]);
                        self.updateData(response.data, mode, tab);
                        if (callback && (typeof(callback) == 'function')) {
                            callback();
                        }

                        if (self.path.tab) {
                            var method = 'editTab' + self.path.tab.substr(0, 1).toUpperCase() + self.path.tab.substr(1) + 'Saved';
                            if (self.call(method) === false) {
                                return false;
                            }
                        }
                    }
                    self.ajax.save = false;
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    self.ajax.save = false;
                    self.refresh('error', textStatus);
                }
            });
            return false;
            // force reload data
            // this.container.data('product-id',this.path.id + '-edited');
        },

        updateData: function(data, mode, tab) {

            $.shop.trace('$.product.updateData(' + mode + ',' + tab + ')', data);
            var tab_content = $('#s-product-edit-forms .s-product-form.main');

            var old_id = this.path.id;

            if (!this.path.id || (this.path.id == 'new')) {
                this.path.id = data.id;
                $(this.options.container_selector).data('product-id', this.path.id);
                var hash = '/product/' + this.path.id + '/';
                if ((mode !== null) && (mode = mode || this.path.mode || 'profile')) {
                    if (mode != 'profile') {
                        hash += mode + '/';
                    }
                    if ((tab !== null) && (tab = tab || this.path.tab || '')) {
                        if (tab.toLowerCase() != 'main') {
                            hash += tab + '/';
                        }
                    }
                }
                $.shop.trace('update location.hash=' + hash);
                var pattern = /#\/product\/new\//;
                var replace = '#/product/' + this.path.id + '/';
                $('#shop-productprofile, #s-product-edit-menu, #s-product-edit-forms, #s-product-profile-page').find('[href*="#/product/new/"]').each(
                function() {
                    $(this).attr('href', $(this).attr('href').replace(pattern, replace));
                });
                $('#s-product-edit-forms').find(':input[name="product\[id\]"]').val(this.path.id);

                window.location.hash = hash;
            } else if (this.path.id != data.id) {
                $.shop.error('Abnormal product ID change', [this.path.id, data]);
            }

            var h1 = $('#shop-productprofile h1');
            h1.find('.s-product-name:first').text(data.name);
            h1.find('.s-product-id').show().text('id: ' + data.id);
            document.title = data.name + $.product.options.title_suffix;

            var $container = tab_content.find(':input[name="product\[type_id\]"]').parents('.value');
            $container.data('type', data.type_id);

            if (data.frontend_url) {

                // update frontend url widget in product edit page
                var frontend_url       = $('#s-product-frontend-url');
                var frontend_url_input = $('#s-product-frontend-url-input');
                frontend_url.text(data.url);
                frontend_url_input.text(data.url);
                frontend_url.trigger('readable');
                frontend_url.parent().attr('href', data.frontend_url);

                // update frontend base url mentions
                frontend_url.parents('div.value:first').
                    find('.s-frontend-base-url').each(
                        function() {
                            // update internals of link
                            if (this.tagName == 'A') {
                                var self = $(this);
                                var children = self.children();
                                self.text(data.fontend_base_url).append(children);
                            } else {
                                // just update text
                                $(this).text(data.fontend_base_url);
                            }
                        }
                    );

                $('.s-product-frontend-url-not-empty').show();
                $('.s-product-frontend-url-empty').hide();

                // update fronted url in product profile page
                $('#s-product-frontend-links').find('a').attr('href', data.frontend_url).text(data.frontend_url);

            } else {
                $('.s-product-frontend-url-not-empty').hide();
                $('.s-product-frontend-url-empty').show();
            }

            /*
             * $($.product.options.form_selector).on('change.product, keyup.product', ':input', function(e) {
             * self.onChange($(this).parents('div.s-product-form')); });
             */
            // XXX extract it into editTab%Tab%Update(data)
            this.editTabMainUpdate(
                data.raw || {},
                data.features_selectable_strings,
                old_id
            );

            // category select items
            tab_content.find('select.s-product-categories').each(function() {
                var item = $(this);
                var parent = item.parent();
                var val = item.val();
                if (val == 'select' || val == '---') {
                    var categories = $('#s-product-edit-forms .s-product-form.main select.s-product-categories');
                    if (categories.length > 1) {
                        parent.remove();
                    } else {
                        parent.find('.s-product-delete-from-category').hide();
                    }
                } else if (val == 'create') {
                    var prev_val = parent.find('input.val').val();
                    if (prev_val == '0') {
                        var categories = $('#s-product-edit-forms .s-product-form.main select.s-product-categories');
                        if (categories.length > 1) {
                            parent.remove();
                        } else {
                            item.val(prev_val); // restore previous value
                            item.show().attr('disabled', false);
                            parent.find('input.val').attr('disabled', true);
                            parent.find('.s-new-category').hide();
                            parent.find('.s-product-delete-from-category').hide();
                        }
                    } else {
                        item.val(prev_val); // restore previous value
                        item.show().attr('disabled', false);
                        parent.find('input.val').attr('disabled', true);
                        parent.find('.s-new-category').hide();
                        parent.find('.s-product-delete-from-category').show();
                    }
                }
            });

            $('#s-product-save table.s-product-skus > tbody > tr').each(function() {
                var sku_id = $(this).data('id');
                $(this).find('td.s-stock .s-product-stock-icon').each(function() {
                    var stock_id = $(this).data('stock');
                    if (data.raw && data.raw.skus && data.raw.skus[sku_id] && data.raw.skus[sku_id].stock_icon[stock_id]) {
                        $(this).html(data.raw.skus[sku_id].stock_icon[stock_id]);
                    }
                });
            });

            // skus setttings
            tab_content.find('tr.js-sku-settings').each(function() {
                var self = $(this);
                var sku_id = self.attr('data-id');
                var sku = data.raw.skus[sku_id];
                if (sku) {
                    if (sku.file_name !== undefined && !sku.file_name) {
                        self.find('.s-sku-attachment').hide();
                        self.find('.fileupload').show();
                    }
                }
            });

            this.helper.checkChanges(this.get('form'), true, data.raw || {});
            $('#s-product-edit-menu li a i.icon10.status-yellow-tiny').remove();
            $('#s-product-edit-save-panel :submit').removeClass('yellow').addClass('green');

            // if sku type is flat (0) blank selectable features info
            if (data.raw.sku_type == '0') {
                var feature_superposition = $('#s-product-feature-superposition');

                // blank values
                feature_superposition.find('input[type=checkbox]').attr('checked', false);

                // blank features
                var feature_li = feature_superposition.find('ul.features li');
                feature_li.find('.count').text('');
                feature_li.find('i').
                    removeClass('status-blue-tiny status-gray-tiny').
                    addClass('status-gray-tiny');

                // blank counter
                var counters = feature_superposition.find('.superposition-count');
                counters.find('.options').text('');
                counters.find('.skus').text('');

                // hidden link
                //tab_content.find('.s-product-skus .all-skus').text('').hide();
            }

            // TODO update name/description/etc fields
        },

        refresh: function(status, message) {
            /* enable previos disabled inputs */
            $(this.options.form_selector).find(':input[name^="product\["]:disabled.js-ajax-disabled').each(function() {
                $(this).removeAttr('disabled').removeClass('js-ajax-disabled');
            });

            var $container = $(this.options.message_selector);
            $container.removeClass('errormsg successmsg status').empty().show();

            if (this.timer) {
                clearTimeout(this.timer);
            }
            var timeout = null;
            $container.append(this.message[status] || '');
            switch (status) {
                case 'submit': {
                    $container.addClass('status');
                    break;
                }
                case 'error': {
                    $container.addClass('errormsg');
                    for (var i = 0; i < message.length; i++) {
                        $container.append(message[i][0]);
                    }
                    timeout = 20000;
                    break;
                }
                case 'success': {
                    if (message) {
                        $container.addClass('successmsg').append(message);
                    }
                    timeout = 3000;
                    $('#s-product-edit-save-panel :submit').removeClass('yellow').addClass('green');
                    break;
                }
            }
            if (timeout) {
                this.timer = setTimeout(function() {
                    $container.removeClass('errormsg successmsg status').empty().show();
                }, timeout);
            }
        },

        editTabLoad: function(path, force) {
            var self = this;
            var $tab = $('#s-product-edit-forms .s-product-form.' + path.tab);
            if (force || (!$tab.length && (path.id == 'new'))) {
                // XXX
                $.shop.trace('product.profileTabHandler: create', [path.tab + ' — create', path]);
                this.saveData(this.path.mode, path.tab, function() {
                    if (path.tab) {
                        //var tab = path.tab.substr(0, 1).toUpperCase() + path.tab.substr(1);
                        self.call(path);
                    }
                });
            } else {
                this.editTabLoadContent(path);
            }
        },

        editTabLoadContent: function(path, params) {
            var self = this;
            path = path || this.path;
            var url = '?module=product&action=' + path.tab + '&id=' + path.id;
            if (path.tail && (typeof(path.tail) != 'undefined')) {
                url += '&param[]=' + path.tail.split('/').join('&param[]=');
            }
            var r = Math.random();
            this.ajax.random = r;
            var $tab = $('#s-product-edit-forms .s-product-form.' + path.tab);
            if ($tab.length) {
                $tab.remove();
            }
            $('#s-product-edit-forms > form').append(tmpl('template-productprofile-tab', {
                id: path.tab
            }));
            $tab = $('#s-product-edit-forms .s-product-form.' + path.tab);
            this.ajax.target = $tab;
            this.ajax.link = $('#s-product-edit-menu li.' + path.tab);
            $.shop.trace('$.product.editTabLoadContent', [path, url, params]);
            $.ajax({
                'url': url,
                'type': params ? 'POST' : 'GET',
                'data': params || {},
                'success': function(data, textStatus, jqXHR) {
                    $.shop.trace('$.product.loadTab status=' + textStatus);
                    if (self.ajax.random != r) {
                        // too late: user clicked something else.
                        return;
                    }
                    $tab.empty().append(data);
                    self.ajax.target = null;
                    self.ajax.link = null;
                    var hash = '#/product/' + path.id + '/edit/';
                    if (path.tab) {
                        hash += path.tab + '/';
                    }
                    if (path.tail) {
                        hash += path.tail + '/';
                    }
                    window.location.hash = hash;
                    self.dispatch(path);
                }
            });

        },

        editTabInit: function(path) {
            $('html, body').animate({
                scrollTop: 0
            }, 200);
            $('#shop-productprofile').find('.s-product-name').removeClass('editable');
        },

        editTabFocus: function(path) {
            var $tab_link = $('#s-product-edit-menu li.' + path.tab);
            $tab_link.addClass('selected');
            this.refresh();
        },

        editTabBlur: function(path) {
            if (!path) {
                return;
            }

            var self = this;
            var tab  = path.tab;

            if (this.standalone_tabs.indexOf(tab) !== -1) {
                if (this.helper.checkChanges(this.get('form'), true)) {
                    this.saveData(this.path.mode, tab, function() {
                        if (tab) {
                            self.call(path);
                        }
                    });
                }
            }

            $('#s-product-edit-forms .s-product-form').hide();
            $('#s-product-edit-menu li.selected').removeClass('selected');
            this.path.tab = null;
            this.path.tail = null;
        },

        editTabShow: function(path) {
            var $tab = $('#s-product-edit-forms .s-product-form.' + path.tab);
            if ($tab.length) {
                $tab.show();
                $.shop.trace('$.product.showTab', path);
                this.path.tab = path.tab;
            } else {
                $.shop.error('$.product.showTab', path);
            }
        },

        editClick: function($el) {
            // #/product/{$product.id}/edit/stock/{$sku_id}/
            var args = $el.attr('href').replace(/.*#\/product\/(\d+|new)\/edit\//, '').replace(/\/$/, '').split('/');
            var params = [];
            var action;

            if (args.length) {
                $.shop.trace('$.product.editClick', args);
                var actionNameChunk, callable, actionName = 'editTab';
                while (actionNameChunk = args.shift()) {
                    actionName += actionNameChunk.substr(0, 1).toUpperCase() + actionNameChunk.substr(1);
                    callable = (typeof(this[actionName]) == 'function');
                    $.shop.trace('$.settings.featuresClick try', [actionName, callable, args]);
                    if (callable === true) {
                        action = actionName;
                        params = args.slice(0);
                    }
                }
            }
            if (action) {
                $.shop.trace('$.product.editClick', [action, params]);
                if (!$el.hasClass('js-confirm') || confirm($el.attr('title') || 'Are you sure?')) {
                    params.push($el);
                    try {
                        this[action].apply(this, params);
                    } catch (e) {
                        $.shop.error('Error: ' + e.message, e);
                    }
                }
            } else {
                $.shop.error('Not found js handler for link', [action, $el]);
            }
            return false;
        },

        switchSubMenu: function(menu) {
            menu = menu || 'default';
            $('#mainmenu .s-submenu > *').hide();
            $('#mainmenu .s-submenu .s-submenu-' + menu).show();
        },

        profileFocus: function() {
            var duration = this.options.duration;
            var self = this;

            // back to product profile view
            $('#s-product-edit-menu, #s-product-edit-save-panel').hide();
            self.switchSubMenu();

            $('#s-sidebar, #s-toolbar').show().animate({
                width: '200px'
            }, duration).queue(function() {
                $(this).dequeue();
                $('#s-product-edit-forms').hide();
                $('#s-product-profile-page, h1 .s-product-edit-link, #mainmenu .s-level2, #s-product-frontend-links').show();
                self.get('container').find('.back').attr('href', '#/' + $.products.list_hash);
            });
            $('#maincontent').animate({
                'margin-top': '84px'
            }, duration);
            $('#s-content').animate({
                'margin-left': '200px'
            }, duration);

            $('#shop-productprofile').
                off('click.edit-product', 'a.js-action').
                on('click.edit-product', 'a.js-action', function() {
                    return self.editClick($(this));
                });

        },

        profileAction: function() {
            this.path.mode = 'profile';
        },

        disableSkus: function(virtual, disabled) {
            var target = $(this.options.form_selector).find('.s-product-skus');
            if (virtual) {
                if (disabled) {
                    target.find('.s-sku-virtual').hide().find('input').attr('disabled', true);
                } else {
                    target.find('.s-sku-virtual').show().find('input').attr('disabled', false);
                }
            } else {
                if (disabled) {
                    target.hide();
                } else {
                    target.show();
                }
                target.find('input').attr('disabled', disabled);
            }
        },

        onSkuTypeChange: function(sku_type) {
            var feature_superposition = $('#s-product-feature-superposition-field-group');
            var product_skus = $(this.options.form_selector).find('.s-product-skus tbody');

            // selectable features case
            if (sku_type == '1') {
                feature_superposition.show();
                feature_superposition.find('input').attr('disabled', false);

                if ($.product.path.id == 'new') {
                    $.product.disableSkus(false, true);
                } else {
                    $.product.disableSkus(true, false);
                }

            // flat sku case
            } else {
                feature_superposition.hide();
                feature_superposition.find('input').attr('disabled', true);
                //product_skus.find('.alist .all-skus').hide();

                if ($.product.path.id == 'new') {
                    $.product.disableSkus(false, false);
                } else {
                    $.product.disableSkus(true, true);
                    // empty skus - emulate add new sku
                    if (!product_skus.find('tr:not(.s-sku-virtual)').length) {
                        $.product.editTabMainSkuAdd();
                        // make default
                        product_skus.find('tr:not(.s-sku-virtual):first').find('input[name="product[sku_id]"]').attr('checked', true);
                    }
                }
            }
        },

        // feature superposition (selectable features) handlers
        featureSelectableInit: function() {
            var form = $(this.options.form_selector);
            var feature_superposition = $('#s-product-feature-superposition');
            var product_skus = form.find('.s-product-skus');

            // change salling mode (sku type)
            var sku_type = $.product.getSkyType();
            $.product.onSkuTypeChange(sku_type);
            form.on('change', 'input[name="product[sku_type]"]', function() {
                $.product.onSkuTypeChange(this.value);
            });

            // click to feature item
            feature_superposition.on('click', 'ul.features>li', function() {
                // make selected
                var li = $(this);
                li.parent().find('li.selected').removeClass('selected');
                li.addClass('selected');

                // show proper div with values
                var feature_id = li.attr('data-feature-id');
                var feature_values = feature_superposition.find('.feature-values[data-feature-id=' + feature_id + ']');
                feature_superposition.find('.feature-values').hide();
                feature_values.show();

                return false;
            });

            // click to feature value checkbox
            feature_superposition.on('change', 'ul.values>li input', function() {

                var self = $(this);
                var li = self.parents('li:first');
                var ul = li.parent();
                var count = ul.find('>li input:checked').length;

                // update count
                var feature_li = feature_superposition.find('ul.features li.selected');
                feature_li.find('.count').text(count || '');

                // update icon
                var icon_class = count ? 'status-blue-tiny' : 'status-gray-tiny';
                feature_li.find('i').
                    removeClass('status-blue-tiny status-gray-tiny').
                    addClass(icon_class);

                updateSuperpositionCount();
            });

            // if at least one input of sku is changed this sku turn into not virtual
            product_skus.on('change', 'input, textarea', function() {
                var self   = $(this);
                // ignore defulat sku ID
                if (self.attr('name') == 'product[sku_id]') {
                    return;
                }
                var sku_tr = self.parents('tr:first');
                if (!sku_tr.length) {
                    return;
                }
                var sku_id = parseInt(sku_tr.attr('data-id'), 10);
                if (!sku_id) {
                    return;
                }
                if (sku_tr.hasClass('js-sku-settings')) {
                    sku_tr = product_skus.find('tr[data-id='+sku_id+']:first');
                    if (!sku_tr.length) {
                        return;
                    }
                }
                sku_tr.find('input.s-input-virtual').val(0);
                sku_tr.removeClass('s-sku-virtual');

                $('#s-product-view').find('tr[data-id='+sku_id+']').removeClass('s-sku-virtual');
            });

            // update superposition count texts helper
            var updateSuperpositionCount = function() {
                var factors = [];
                feature_superposition.find('ul.features>li').each(function() {
                    var li  = $(this);
                    var cnt = parseInt(li.find('.count').text(), 10);
                    if (cnt) {
                        factors.push(cnt);
                    }
                });

                var counter = feature_superposition.find('.superposition-count');
                if (factors.length) {
                    counter.find('.options').text(factors.join(' x ') + ' ' + $_('options'));

                    var count = 1;
                    for (var i = 0, n = factors.length; i < n; i += 1) {
                        count *= factors[i];
                    }
                    counter.find('.skus').html(
                        '<span class="highlighted">' + $_('%d SKUs in total').replace('%d', count) + '</span>'
                    );

                } else {
                    counter.find('.options').text('');
                    counter.find('.skus').html('');
                }
            };
        },

        editInit: function() {
            // check rights
            if (!this.options.edit_rights) {
                location.hash = '#/product/' + this.path.id + '/';
                return false;
            }
            var form = $(this.options.form_selector);
            form.bind('submit.product', function(e) {
                return $.product.saveData('profile', null);
            });
            form.on('change.product, keyup.product', 'div.s-product-form:not(.ajax) :input', function(e) {
                $.product.helper.onChange($(this).parents('div.s-product-form'));
            });
            form.on('change.product, keyup.product, keypress.product', ':input[name="product\[name\]"]', function(e) {
                $.product.helper.onNameChange($(this), false, $.product.options.update_delay || 500);
            });

            var product_tags = $('#product-tags');
            if (!product_tags.data('tags_input_init')) {
                product_tags.tagsInput({
                    autocomplete_url: '?module=product&action=tagsAutocomplete',
                    height: 120,
                    defaultText: ''
                }).data('tags_input_init', true);
            }

            form.on('change.product', '.s-product-currency', function() {
                var $self = $(this), val = $self.val();
                form.find('.s-product-currency').val(val);
                form.find('.s-product-currency-readonly').text(val);
                $('#s-product-currency-code').val(val);
            });

            form.on('change.product', ':input[name="product\[type_id\]"]', function() {
                return $.product.editTabMainTypeChange($(this));
            });

            form.on('change', 'select[name="product[status]"]', function () {
                if ($(this).val() == '1') {
                    $(this).prev().removeClass('no-bw').addClass('yes');
                    $('.s-product-status-text').hide();
                } else {
                    $(this).prev().removeClass('yes').addClass('no-bw');
                    $('.s-product-status-text').show();
                }
            });

            this.featureSelectableInit();

        },

        editFocus: function() {
            var duration = this.options.duration;
            var self = this;
            $('h1 .s-product-edit-link, #mainmenu .s-level2, #s-product-frontend-links').hide();
            $('#s-sidebar, #s-toolbar').animate({
                width: 0
            }, duration).queue(function() {
                $(this).hide();
                $(this).dequeue();
                // show product navigation menu
                $('#s-product-edit-menu, #s-product-edit-forms, #s-product-edit-save-panel').show();
                if (self.path.id != 'new') {
                    self.get('container').find('.back').attr('href', '#/product/' + self.path.id + '/');
                } else {
                    self.get('container').find('.back').attr('href', '#/' + $.products.list_hash);
                }
                // hide profile page and show editing forms
                $('#s-product-profile-page').hide();
                self.switchSubMenu('productprofile');
            });

            // stretch product page for the entire width
            $('#maincontent').animate({
                'margin-top': '40px'
            }, duration);
            $('#s-content').animate({
                'margin-left': 0
            }, duration);

            $('#shop-productprofile').
                off('click.edit-product', 'a.js-action').
                on('click.edit-product', 'a.js-action', function() {
                    return self.editClick($(this));
                });
        },

        editBlur: function() {
            this.path.tab = false;
            this.path.mode = false;
            $($.product.options.form_selector).off('submit.product');
            $(this.options.form_selector).off('change.product, keyup.product');
            $('#s-product-edit-forms').off('click.edit-product');
        },

        /**
         *
         * @param {'id':int,'mode':String,'tab':String} path
         */
        editAction: function(path) {
            this.path.mode = path.mode;
        },

        /**
         *
         * @method edit%Tab%Init after first loading or force reloaded content
         * @method edit%Tab%Focus Tab get focus
         * @method edit%Tab%Action Tab interactions (provide extra params into tab)
         * @method edit%Tab%Blur Tab leave focus
         * @method edit%Tab%* Tab "namespace" for internal purpose functions
         *
         */

        editTabMainData: {
            'sku_id': -1,
            'stocks': {}
        },

        editTabMainInit: function(path) {
            $('#s-product-type').each(function() {
                if ($(this).parent().get(0) != document.body) {
                    $('body > #s-product-type').remove();
                    $(document.body).append($(this));
                }
            });
            var main_tab_content = $('#s-product-edit-forms .s-product-form.main');
            var self = this;
            var $table = main_tab_content.find('table.s-product-skus:last > tbody');
            $table.sortable({
                distance: 5,
                helper: 'original',
                items: '> tr',
                handle: 'i.sort',
                opacity: 0.75,
                tolerance: 'pointer',
                update: function(event, ui) {
                    var id = parseInt($(ui.item).data('id'), 10);
                    var after_id = $(ui.item).prev().data('id');
                    if (after_id === undefined) {
                        after_id = 0;
                    } else {
                        after_id = parseInt(after_id, 10);
                    }
                    self.editTabMainSkuSort(id, after_id, $(this));
                    var $s = $table.find('> tr.js-sku-settings[data-id="' + id + '"]');
                    if ($s.length) {
                        var $settings = $s.detach();
                        $.shop.trace('detach', $settings);
                        if ($settings) {
                            $.shop.trace('detach', $table.find('> tr[data-id="' + id + '"]').length);
                            $table.find('> tr[data-id="' + id + '"]:first').after($settings);
                        }
                    }
                },
                start: function(event, ui) {
                    $table.find('> tr.js-sku-settings').hide();
                },
                stop: function(event, ui) {
                    $table.find('> tr.js-sku-settings').show();
                }
            });

            var frontend_url = $('#s-product-frontend-url');
            frontend_url.inlineEditable({
                editLink: '#s-product-frontend-url-edit-link',
                editOnItself: false,
                minSize: {
                    height: 15,
                    width: 100
                },
                makeReadableBy: [],
                beforeMakeEditable: function(input) {
                    var self = $(this);
                    var parent_div = self.parents('div:first');
                    var slash = parent_div.find('span.slash');
                    var new_window = parent_div.find('i.new-window');
                    $(input).after(slash);
                    new_window.hide();

                    parent_div.find('span.s-frontend-base-url').html(parent_div.find('a.s-frontend-base-url').hide().contents()).show();
                },
                beforeBackReadable: function(input, data) {
                    var self = $(this);
                    var parent_div = self.parents('div:first');
                    var slash = parent_div.find('span.slash');
                    var new_window = parent_div.find('i.new-window');
                    self.after(slash);
                    new_window.show();

                    parent_div.find('a.s-frontend-base-url').html(parent_div.find('span.s-frontend-base-url').hide().contents()).show();
                }
            });
            if (!parseInt(path.id, 10)) {
                frontend_url.trigger('editable');
            }

            main_tab_content.off('change.product', 'select.s-product-categories').on('change.product', 'select.s-product-categories', function(e) {
                var self = $(this);
                var val = self.val();
                var parent = self.parent();
                var del_button = parent.find('.s-product-delete-from-category');

                if (!parseInt(val, 10)) {
                    del_button.hide();
                } else {
                    del_button.show();
                }

                // create new category functionality
                if (val == 'create') {
                    parent.find('.s-new-category').show();
                    parent.find('input.val').attr('disabled', false);
                    self.hide().attr('disabled', true).addClass('js-ignore-change');
                } else {
                    parent.find('input.val').val(parseInt(val, 10) || 0);
                }
            });

            // Enter-press handler when foucus on input
            main_tab_content.off('keydown.product', 'input.new-category').on('keydown.product', 'input.new-category', function(e) {
                if (e.keyCode == 13) {
                    $(this).parent().find('input[type=button]').click();
                    return false;
                }
            });

            // saving new category
            main_tab_content.off('click.product', '.s-new-category input[type=button]').on('click.product', '.s-new-category input[type=button]', function(e) {
                var self = $(this).parent();
                var parent = self.parent();
                var input = self.find('input[name=new_category]');
                var value = input.val();
                $.shop.jsonPost('?module=products&action=saveListSettings&category_id=0&parent_id=0', {
                    name: value
                }, function(r) {
                    var select = parent.find('select');
                    var place = select.find('option.separator:first').show();
                    $.when(place.after('<option class="category" value="' + r.data.id + '">' + r.data.name + '</option>')).then(function() {
                        select.find('option:first').text($_('Please select a category'));
                        select.val(r.data.id).attr('disabled', false).show().removeClass('js-ignore-change');
                        self.find('input.val').val(r.data.id).attr('disabled', true);
                        self.hide();
                        parent.find('.s-product-delete-from-category').show();
                        input.val('');
                        $.product.helper.onTabChanged('main', true);
                        $('#s-product-save-button').removeClass('green').addClass('yellow');
                        $('#s-category-list>ul').trigger('add', [r.data, 'category']);
                    });
                });
            });

            // delete category
            main_tab_content.
                off('click.product', '.s-product-delete-from-category').
                on('click.product', '.s-product-delete-from-category',
                    function() {
                        var self = $(this);
                        var parent = self.parent();
                        var select = parent.find('select');

                        var categories = $('#s-product-edit-forms .s-product-form.main select.s-product-categories');
                        if (categories.length > 1) {
                            select.attr('disabled', true);
                            parent.remove();
                        } else {
                            select.val('selected').attr('disabled', false);
                            parent.find('input.val').val(0).attr('disabled', true).parent().hide();
                            parent.find('.s-product-delete-from-category').hide();
                        }
                        return false;
                    }
                );
        },
        editTabMainAction: function(path) {
            var selector = false;
            var name = null;
            if (path && path.params && path.params.focus) {
                name = path.params.name || '';
                if (!name.length) {
                    if (path.params.sku) {
                        name = 'product[skus][' + parseInt(path.params.sku, 10) + ']';
                        if (path.params.focus) {
                            name += '[' + path.params.focus + ']';
                        }
                        if (path.params.focus == 'stock') {
                            name += '[' + (parseInt(path.params.stock, 10) || 0) + ']';
                        }
                    }
                }
                selector = ':input[name$="' + name.replace(/(\[|\]|\|\-)/g, '\\$1') + '"]:first';
            } else if (path && path.id == 'new') {
                selector = ':input[name$="\[name\]"]:first';
            }
            if (selector) {
                $.shop.trace('$.product.editTabMainFocus', [name, $(selector).length]);
                setTimeout(function() {
                    $(selector).focus();
                    window.location.hash = '#/product/' + path.raw.replace(/\/focus=.*$/, '/');
                }, 100);
            }
        },
        editTabMainBlur: function() {
        },
        editTabMainSave: function() {
            if (this.call('editTabMainSkuEproductSave') === false) {
                return false;
            }
        },

        helper: {
            options: {
                'tab_changed': '<i class="icon10 status-yellow-tiny"></i>'
            },

            data: {
                url_helper: {
                    url: '',
                    name: '',
                    timer: null
                }
            },
            init: function() {
                this.data.url_helper = {
                    url: '',
                    name: '',
                    timer: null
                };
            },
            onTabChanged: function(tab, changed) {

                $.shop.trace('$.product.onChange id=' + tab + ' changed=' + changed);
                $('#s-product-edit-menu li.' + tab + ' .s-product-edit-tab-status').html(changed ? this.options.tab_changed : '');
            },

            /**
             * Get current product type id
             *
             * @param {} type
             * @return {}
             */
            type: function(type) {
                return parseInt(type, 10) || parseInt($('#s-product-edit-forms .s-product-form.main :input[name="product\[type_id\]"]').val(), 10) || 0;
            },

            onNameChange: function(element, animate, delay) {
                if (this.data.url_helper.timer) {
                    clearTimeout(this.data.url_helper.timer);
                }
                var target = $($.product.options.form_selector).find(':input[name="product\[url\]"]');
                var parent = target.parent();
                if (($.product.path.id && ($.product.path.id != 'new')) || (target.val() != this.data.url_helper.url)) {
                    $.shop.trace('$.product.onNameChange stop ' + this.data.url_helper.url + ' != ' + target.val());
                    $($.product.options.form_selector).off('.product', ':input[name="product\[name\]"]');
                    parent.find('.js-url-helper').hide();
                } else {
                    if (animate) {
                        if (!parent.find('.js-url-helper').length) {
                            parent.append('<i class="icon16 loading js-url-helper"></i>');
                        } else {
                            parent.find('.js-url-helper').show();
                        }
                    }
                    var self = this;
                    this.data.url_helper.timer = setTimeout(function() {
                        self.urlHelper(element, target, delay);
                    }, delay || 500);

                }
            },
            onChange: function(container) {
                var changed = this.checkChanges(container.parents('form'), false);
                var id = this.getContainerId(container);

                /**
                 * @todo update class on state change only
                 */
                $.shop.trace('$.product.onChange changed=' + changed);
                $('#s-product-edit-save-panel :submit').removeClass(changed ? 'green' : 'yellow').addClass(changed ? 'yellow' : 'green');
                if (changed) {
                    changed = this.checkChanges(container, false);
                }
                this.onTabChanged(id, changed);
            },
            urlHelper: function(element, target) {
                if (this.data.url_helper.timer) {
                    clearTimeout(this.data.url_helper.timer);
                }
                var data = {
                    'str': $(element).val()
                };
                $.shop.trace('$.product.urlHelper ', data);
                if (data.url != this.data.url_helper.name) {
                    var self = this;
                    this.data.url_helper.url = data.url;
                    $.ajax({
                        'url': '?action=transliterate',
                        'data': data
                    }).done(function(response) {
                        if ((response = $.parseJSON(response)) && (response.status == "ok")) {
                            self.data.url_helper.url = response.data;
                            target.val(response.data);
                            target.parent().find('.js-url-helper').hide();
                        }
                    });
                } else {
                    target.parent().find('.js-url-helper').hide();
                }
            },
            getValueByName: function(data, name) {
                var value = data;
                var chunk, chunks = name.replace(/\]/, '').split('[');
                while (chunk = chunks.shift()) {
                    if (value[name] !== undefined) {
                        value = value[name];
                    } else {
                        value = '';
                        break;
                    }
                }
                $.shop.trace('$.product.helper.getValueByName', [value, name, data]);
                return value;
            },
            checkChanges: function($container, update) {
                /**
                 * @todo add update relataed text tags
                 * @todo extract it into separate plugin
                 */
                var changed = false;
                var self = this;
                var selector = ':input:not(.js-ignore-change)';
                if ($container.hasClass('s-product-form')) {
                    if ($container.hasClass('ajax')) {
                        return false;
                    }
                } else {
                    selector = '.s-product-form:not(.ajax) ' + selector;
                }
                $container.find(selector).each(function() {
                    var type = ($(this).attr('type') || this.tagName).toLowerCase();
                    switch (type) {
                        case 'input':/* stuped case */
                        case 'text':
                        case 'textarea': {
                            if (this.defaultValue != this.value) {
                                changed = true;
                                if (update) {
                                    this.defaultValue = this.value;
                                    self.updateInput(this.name, this.value, type == 'textarea');
                                }
                            }
                            break;
                        }
                        case 'radio':
                        case 'checkbox': {
                            if (this.defaultChecked != this.checked) {
                                changed = true;
                                if (update) {
                                    this.defaultChecked = this.checked;
                                }
                            }
                            break;
                        }
                        case 'select': {
                            if (this.length) {
                                $(this).find('option').each(function() {
                                    if (this.defaultSelected != this.selected) {
                                        changed = true;
                                        if (update) {
                                            this.defaultSelected = this.selected;
                                        }
                                    }
                                    return update || !changed;
                                });
                            }
                            break;
                        }
                        case 'file': {
                            if (this.value) {
                                changed = true;
                                if (update) {
                                    this.value = null;
                                }
                            }
                            break;
                        }
                        case 'reset':
                        case 'button':
                        case 'submit': {
                            // ignore it
                            break;
                        }
                        case 'hidden': {
                            // do nothing
                            break;
                        }
                        default: {
                            $.shop.error('$.product.checkChanges unsupported type ' + type, [type, this]);
                            break;
                        }
                    }
                    if (!update && changed) {
                        $.shop.trace('$.product.helper.checkChanges', [this, changed]);
                    }
                    return update || !changed;
                });
                return changed;
            },
            getContainerId: function(container) {
                var c = ($(container).attr('class') || '').split(' ');
                var id = false;
                for (var i in c) {
                    if (c[i] != 's-product-form') {
                        id = c[i];
                        break;
                    }
                }
                return id;
            },
            updateInput: function(name, value, html, id) {
                if (name) {
                    var selector = '.s-' + name.replace(/\[(.+?)\]/g, '-$1') + '-input';
                    $.shop.trace('update field: ' + name + ' ' + selector + ' value=' + value);
                    if (html) {
                        $(selector).html(value);
                    } else {
                        $(selector).text(value);
                    }
                    if (id) {
                        var input_name = $(selector).attr('name');
                        input_name = input_name.replaceAll(/\[-[\d]+\]/, '[' + id + ']');
                        $.shop.trace('$.product.helper.updateInput name', [input_name]);
                    }
                }
            },
            count: function(obj) {
                var size = 0;
                for (var key in obj) {
                    if (obj.hasOwnProperty(key))
                        size++;
                }
                return size;
            },

            loadScript: function(src) {
                var name  = src.replace(/^.*\//, '');
                var count = 10;
                if (!$.product.ajax.cached[name]) {
                    $.ajax({
                        url: wa_url + src,
                        dataType: "script",
                        cache: true,
                        complete: function(jqXHR, textStatus) {
                            if (textStatus == 'success') {
                                $.product.ajax.cached[name] = true;
                                $.shop.trace('$.product.helper.loadScript loaded', [name, src]);
                            } else {
                                $.shop.trace('$.product.helper.loadScript error', [textStatus, name]);
                                count -= 1;
                                if (count > 0) {
                                    setTimeout(function() {
                                        $.product.helper.loadScript(src);
                                    }, 200);
                                }
                            }
                        }
                    });
                }
            }
        },

        multiSkus: function(count) {
            var table = $('#s-product-edit-forms .s-product-form.main table.s-product-skus:first');
            if (count > 1) {
                table.find('thead tr').show();
                table.find('.s-name,.s-sku-sort').show('slow');
                if (count == 2) {
                    table.find('> tbody:first > tr:first .s-name :input').focus();
                } else {
                    table.find('> tbody:first > tr:last .s-name :input').focus();
                }
            } else {
                table.find('thead tr').hide();
                table.find('.s-name,.s-sku-sort').hide();
            }
        },

        editTabMainSkuAdd: function() {
            try {
                var $table = $('#s-product-edit-forms .s-product-form.main table.s-product-skus:first');
                var $skus = $table.find('tbody:first');
                var price = 0;

                $skus.find(':input[name$="\[price\]"]').each(function() {
                    price = Math.max(price, parseFloat($(this).val()) || 0);
                });
                var purchase_price = 0;
                $skus.find(':input[name$="\[purchase_price\]"]').each(function() {
                    purchase_price = Math.max(purchase_price, parseFloat($(this).val()) || 0);
                });

                $.product.multiSkus($skus.find('tr:not(.js-sku-settings)').length + 1);

                //$skus.parents('table').find('tr:hidden').show();
                var sku = {
                    'id': --$.product.editTabMainData.sku_id,
                    'sku': '',
                    'available': 1,
                    'name': '',
                    'price': '' + price,
                    'purchase_price': '' + purchase_price,
                    'stock_icon': {
                        0: "<i class='icon10 status-green' ></i>"
                    },
                    'stock': {},
                    'count': null

                };
                $.shop.trace('$.product.editTabMainSkuAdd', [$.product.editTabMainData.sku_id, sku]);

                $skus.append(tmpl('template-sku-edit', {
                    'sku_id': $.product.editTabMainData.sku_id,
                    'sku': sku,
                    'stocks': this.getData('main', 'stocks')
                }));
                $skus.find('.s-product-currency').trigger('change');

            } catch (e) {
                $.shop.error(e.message, e);
            }
            return false;
        },

        editTabMainSkuSort: function(id, after_id, $list) {
            try {
                $.post('?module=product&action=skuSort', {
                    product_id: this.path.id,
                    sku_id: id,
                    after_id: after_id
                }, function(response) {
                    $.shop.trace('$.product.editTabMainSkuSort result', response);
                    if (response.error) {
                        $.shop.error('Error occurred while sorting product SKUs', 'error');
                        $list.sortable('cancel');
                    }
                }, function(response) {
                    $.shop.trace('$.product.editTabMainSkuSort cancel', {
                        'data': response
                    });
                    $list.sortable('cancel');
                    $.shop.error('Error occurred while sorting product SKUs', 'error');
                });
            } catch (e) {
                $.shop.error(e.message, e);
            }
            return false;
        },

        editTabMainProductDelete: function(el) {
            var showDialog = function() {
                $('#s-product-list-delete-products-dialog').waDialog({
                    disableButtonsOnSubmit: true,
                    onLoad: function() {
                        $(this).find('.dialog-buttons i.loading').hide();
                    },
                    onSubmit: function(d) {
                        $(this).find('.dialog-buttons i.loading').show();
                        $.shop.jsonPost('?module=products&action=deleteList', {
                            product_id: [$.product.path.id],
                            get_lists: 1
                        }, function(r) {
                            if ($.product_list) {
                                $('#s-sidebar').trigger('update', r.data.lists);
                            }
                            d.trigger('close');
                            location.hash = '#/products/';
                        });

                        return false;
                    }
                });
            };
            var d = $('#s-product-list-delete-products-dialog');
            var p = d.parent();
            if (!d.length) {
                p = $('<div></div>').appendTo('body');
            }
            p.load('?module=dialog&action=productsDelete&count=' + 1, showDialog);
            return false;
        },

        /**
         *
         * @param {Integer} sku_id
         * @param {jQuery} $el
         * @todo real sku delete
         */
        editTabMainSkuDelete: function(sku_id, $el) {
            var $table = $('#s-product-edit-forms .s-product-form.main table.s-product-skus:first');
            var $skus = $table.find('tbody:first');

            var $sku = $el.parents('tbody').find('> tr[data-id="' + sku_id + '"]');
            var self = this;
            if (sku_id > 0) {
                var skus_count = $el.parents('tbody').find('tr[data-id]:not(.js-sku-settings):not([data-id^="-"])').length;
                if (skus_count > 1) {
                    $.ajax({
                        'url': '?module=product&action=skuDelete',
                        'data': {
                            'sku_id': sku_id,
                            'product_id': this.path.id
                        },
                        'dataType': 'json',
                        'type': 'post',
                        success: function(response) {
                            if (response.status == 'fail') {
                                self.refresh('error', response.errors);
                            } else if (response.data.redirect) {
                                window.location.href = response.data.redirect;
                            } else {
                                self.refresh('success', response.data.message || 'Success');
                                $sku.hide('normal', function() {
                                    $sku.remove();
                                    $('#s-product-view table.s-product-skus > tbody > tr[data-id="' + sku_id + '"]').remove();
                                    $.product.multiSkus($skus.find('tr:not(.js-sku-settings)').length);
                                });
                                $('#s-product-edit-forms .s-product-form.main').find('input[name=product\\[sku_id\\]][value=' + response.data.sku_id + ']')
                                .attr('checked', true);
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            self.refresh('error', textStatus);
                        }
                    });
                } else {
                    self.refresh('error', $_('At least one SKU per product must exists'));
                }

            } else {
                $sku.hide('normal', function() {
                    $sku.remove();
                    $('#s-product-view table.s-product-skus > tbody >tr[data-id="' + sku_id + '"]').remove();
                    $.product.multiSkus($skus.find('tr:not(.js-sku-settings)').length);
                });
            }
        },

        editTabMainCategoriesAdd: function() {
            var control = $('#s-product-edit-forms .s-product-form.main select.s-product-categories:last').parent();
            var clone = control.clone(false);
            clone.find('select').val('select').attr('disabled', false).show();
            clone.find('input.val').val(0).attr('disabled', true).parent().hide();
            clone.find('.s-product-delete-from-category').hide();
            control.after(clone);
        },

        editTabMainSkuStock: function(sku_id, $el) {
            var $container = $('#s-product-sku-' + sku_id);
            $container.hide().find(':input:enabled').attr('disabled', true);

            var $stock_container = $('#s-product-sku-stock-' + sku_id);
            $stock_container.show().find(':input:disabled').removeAttr('disabled');
            $stock_container.find(':input:first').val($container.find(':input:first').val());
        },

        editTabMainSkuSettings: function(sku_id, $el) {
            $el.hide();
            var $sku = $el.parents('tr');
            var sku = $sku.data();
            var self = this;
            $.when($sku.after(tmpl('template-sku-settings', {
                'sku_id': sku_id,
                'sku': sku
            }))).done(function() {
                var url = '?module=product&action=skuSettings';
                url += '&product_id=' + self.path.id;
                url += '&sku_id=' + sku_id;
                var $target = $('#s-product-edit-forms .s-product-form.main tr.js-sku-settings[data-id="' + sku_id + '"] > td:first');
                $target.load(url, function() {
                    $sku.find(':input[name$="\[available\]"]').remove();
                    if (sku_id > 0) {
                        $.shop.trace('fileupload', [$target.find('.fileupload').length, typeof($target.find('.fileupload').fileupload)]);
                        try {
                            var matches = document.cookie.match(new RegExp("(?:^|; )_csrf=([^;]*)"));
                            var csrf = matches ? decodeURIComponent(matches[1]) : '';

                            $target.find('.fileupload:first').fileupload({
                                dropZone: null,
                                url: '?module=product&action=skuEproductUpload',
                                acceptFileTypes: /(\.|\/)(gif|jpe?g|png)$/i,
                                start: function() {
                                    $target.find('.fileupload:first').hide();
                                },
                                progress: function(e, data) {
                                    $.shop.trace('fileupload progress', data);
                                    var $progress = $target.find('.js-progressbar-container');
                                    $progress.show();
                                    $progress.find('.progressbar-inner:first').css('width', Math.round((100 * data.loaded / data.total), 0) + '%');

                                },
                                done: function(e, data) {
                                    $.shop.trace('fileupload done', [data.result, typeof(data.result)]);
                                    var file = (data.result.files || []).shift();
                                    $target.find('.js-progressbar-container').hide();
                                    if (!file || file.error) {
                                        $target.find('.error-message').text(file.error).show();
                                        $target.find('.fileupload:first').addClass('error').show();
                                    } else {
                                        var attachment_block = $target.find('.s-sku-attachment');
                                        attachment_block.find('input.s-input-file-name').val(file.name);
                                        attachment_block.find('.s-file-name').text(file.name);
                                        attachment_block.find('.s-file-size').text(file.size);
                                        attachment_block.find('.s-file-description').val('');
                                        attachment_block.find('input[type=checkbox]').attr('checked', true);
                                        attachment_block.show();

                                        $sku.find('input.s-input-virtual').val(0);
                                        /*
                                        $target.find('.value .hint').text(file.name + ' ' + file.size);
                                        $target.find('.value :checkbox[name$="\[eproduct\]"]').attr('checked', true);
                                        */

                                    }

                                },
                                fail: function(e, data) {
                                    $.shop.trace('fileupload fail', [data.textStatus, data.errorThrown]);
                                    $target.find('.error-message').text('error').show().text(data.errorThrown || 'error');
                                    $target.find('.js-progressbar-container').hide();
                                    $target.find('.fileupload:first').addClass('error').show();
                                },
                                formData: {
                                    'product_id': $.product.path.id,
                                    'sku_id': sku_id,
                                    '_csrf': csrf
                                }
                            });
                        } catch (e) {
                            $.shop.error('Exception ' + e.message, e);
                        }
                    }
                });

            });
        },

        editTabMainSkuImageSelect: function(sku_id, image_id, $el) {
            var li       = $el.parents('li:first');
            var selected = li.hasClass('selected');
            var parent   = $el.parents('div.value:first');

            parent.find('ul.s-product-image-crops li.selected').removeClass('selected');
            if (!selected) {
                li.addClass('selected');
                parent.find('input[name$="\[image_id\]"]').val(image_id);
            } else {
                parent.find('input[name$="\[image_id\]"]').val(0);
            }

            $.shop.trace('$.product.editTabMainSkuImageSelect', [parent, parent.find(':input[name$="\[image_id\]"]')]);
            $.product.helper.onChange($el.parents('div.s-product-form'));
        },

        editTabMainSkuEproductSave: function(sku_id) {
            // upload eproduct files for existing skus
            var $sku_files = $('#s-product-edit-forms .s-product-form.main tr.js-sku-settings' + ((sku_id && sku_id > 0) ? ('[data-id="' + sku_id + '"]') : '')
            + ' > td:first .fileupload');
            $.shop.trace('$.product.editTabMainSkuEproductSave', $sku_files.length);
            if ($sku_files.length) {
                $sku_files.fileupload('start');
            }

        },

        editTabMainSkuEproductDelete: function(sku_id) {

        },

        editTabMainLinkEdit: function($el) {
            return false;
        },

        /**
         * Show select input for change product type
         */
        editTabMainTypeSelect: function($el) {
            var $container = $el.parents('form').find(':input[name="product\[type_id\]"]').parents('.value');
            if (this.path.tab == 'main') {
                $el.hide();
            } else {
                $container.find('.js-action').hide();
                window.location.hash = '/product/' + this.path.id + '/' + this.path.mode + '/';
            }
            $container.find('.js-type-name').hide();
            $container.find(':input').show().focus();
            setTimeout(function() {
                $container.find(':input').focus();
            }, 100);
        },

        getSkyType: function() {
            var form = $(this.options.form_selector);
            var sku_type_input = form.find('input[name="product[sku_type]"]:first');
            var sku_type = '';
            if (sku_type_input.is(':radio')) {
                sku_type = form.find('input[name="product[sku_type]"]:checked').val();
            } else {
                sku_type = sku_type_input.val();
            }
            return sku_type;
        },

        editTabMainTypeChange: function($el) {

            var $container = $el.parents('.value');
            var $type = $el.find(':selected:first');
            var type = $type.val();
            var tab = 'features';
            var $tab_link;
            var href;

            $.shop.trace('debug', $el);
            if (type != $container.data('type')) {
                $container.find('.js-type-icon').html($type.data('icon'));
                $container.find('.js-type-name').html($type.text());

                var $tab = $('#s-product-edit-forms .s-product-form.' + tab);
                if ($tab.length && typeof(this.editTabFeaturesReload) != 'undefined') {
                    this.editTabFeaturesReload(type);
                } else {
                    $tab_link = $('#s-product-edit-menu > li.' + tab + ' > a');
                    href = '/features/' + type + '/';
                    $tab_link.attr('href', $tab_link.attr('href').replace(/\/features\/.*$/, href));
                }
            } else {
                $tab_link = $('#s-product-edit-menu > li.' + tab + ' > a');
                href = '/features/';
                $tab_link.attr('href', $tab_link.attr('href').replace(/\/features\/.*$/, href));
            }

            var sku_type = $.product.getSkyType();

            /*
            var sku_type_field_group = $('#s-sku-type-field-group');
            var currency_control = sku_type_field_group.find('.s-base-price-selectable-currency').contents();

            // ajax for features selectable
            sku_type_field_group.
                load(
                    '?module=product&action=featuresSelectable&id=' + $.product.path.id +
                    '&type_id='  + $type.val() +
                    '&sku_type=' + sku_type,
                    function() {
                        $('#s-sku-type-field-group').
                            find('.s-base-price-selectable-currency').
                            append(currency_control);
                        $.product.featureSelectableInit();
                    }
                );
                */

            // ajax for features selectable
            var currency_control = $(
                '#s-sku-type-field-group .s-base-price-selectable-currency'
            ).contents();
            $.get(
                '?module=product&action=featuresSelectable&id=' + $.product.path.id, {
                    type_id: $type.val(), sku_type: sku_type
                },
                function(html) {
                    var wrapper = $('<div></div>');

                    wrapper.html(html);
                    wrapper.find('.s-base-price-selectable-currency').append(currency_control);

                    $('#s-sku-type-field-group').replaceWith(
                        $('#s-sku-type-field-group', wrapper)
                    );

                    $('#s-product-feature-superposition-field-group').replaceWith(
                        $('#s-product-feature-superposition-field-group', wrapper)
                    );
                    $.product.featureSelectableInit();
                }
            );
        },

        editTabMainUpdate: function(data, features_selectable_strings, old_id) {

            var $skus = $('#s-product-edit-forms .s-product-form.main table.s-product-skus tbody');
            var $skus_view = $('#s-product-view table.s-product-skus tbody');
            $skus.find('tr[data-id^="\-"]').remove();
            $skus_view.find('tr[data-id^="\-"]').remove();
            $skus.parents('table').find('tr:hidden').show();

            // take into account sort field of skus
            var skus = [];
            for (var sku_id in data.skus || {}) {
                skus.push($.extend({ id: sku_id }, data.skus[sku_id]));
            }
            skus = skus.sort(function(a, b) {
                return a.sort - b.sort;
            });

            var html = '';

            // render skus in edit tab
            for (var i = 0, n = skus.length; i < n; i += 1) {
                html += tmpl('template-sku-edit', {
                    'sku_id':  skus[i].id,
                    'sku':     skus[i],
                    'stocks':  this.getData('main', 'stocks'),
                    'checked': skus[i].id == data.sku_id
                });
            }
            $skus.html(html);
            $skus.find('select.s-product-currency').val(data.currency);
            $skus.parent().show();
            this.multiSkus($skus.find('tr:not(.js-sku-settings)').length);

            // render skus in profile page
            html = '';
            for (var i = 0, n = skus.length; i < n; i += 1) {
                html += tmpl('template-sku', {
                    'sku_id': skus[i].id,
                    'sku':    skus[i],
                    'stocks': this.getData('main', 'stocks')
                });
            }
            $skus_view.html(html);

            if (!old_id || old_id == 'new') {
                $skus.parents('div.field-group:first').after(
                    $('#s-sku-type-field-group')
                );
            }

            if (features_selectable_strings) {
                var counter = $('#s-product-feature-superposition .superposition-count');
                counter.find('.options').text(features_selectable_strings.options);
                counter.find('.skus').html('<span class="highlighted">' + features_selectable_strings.skus + '</span>');
            }

            var pattern = /#\/product\/new\//;
            var replace = '#/product/' + data.id + '/';
            $('#s-product-edit-forms .s-product-form.main table.s-product-skus tbody, #s-product-view table.s-product-skus tbody')
            .find('[href*="#/product/new/"]').each(function() {
                $(this).attr('href', $(this).attr('href').replace(pattern, replace));
            });
        },

        editTabDescriptionsInit: function() {
            var scripts = [];
            scripts.push('wa-content/js/elrte/elrte.min.js');
            scripts.push('wa-content/js/elrte/elrte-wa.js');
            if (wa_lang != 'en') {
                scripts.push('wa-content/js/elrte/i18n/elrte.' + wa_lang + '.js');
            }

            for (var i = 0; i < scripts.length; i++) {
                $.product.helper.loadScript(scripts[i]);
            }
        },

        editTabPagesInit: function() {
            var scripts = [];
            scripts.push('wa-content/js/elrte/elrte.min.js');
            scripts.push('wa-content/js/elrte/elrte-wa.js');
            if (wa_lang != 'en') {
                scripts.push('wa-content/js/elrte/i18n/elrte.' + wa_lang + '.js');
            }

            for (var i = 0; i < scripts.length; i++) {
                $.product.helper.loadScript(scripts[i]);
            }
        },

        editTabDescriptionsBlur: function() {
            waEditorUpdateSource({
                id: 's-product-description-content'
            });
        },

        editTabDescriptionsAction: function () {
            var count = 10;
            (function init() {
                try {
                    if ($.product.ajax.cached['elrte-wa.js'] && $.product.ajax.cached['elrte.min.js']) {

                        var $element = $('#s-product-description-content');
                        $('#s-product-description').html(tmpl('template-product-description', {
                            description: $element.val()
                        }));
                        $element.remove();

                        waEditorInit({
                            id: 's-product-description-content',
                            prefix: 's-product-description-',
                            upload_url: "",
                            lang: wa_lang,
                            save_button: 's-product-save-button',
                            change_callback: function() {
                                if ($.product.path.tab == 'descriptions') {
                                    $.product.helper.onTabChanged($.product.path.tab, true);
                                }
                            }
                        });
                    } else {
                        count -= 1;
                        if (count > 0) {
                            $.shop.trace('$.product.editTabDescriptionsAction wait while js are loading', $.product.ajax);
                            setTimeout(function() {
                                init();
                            }, 500);
                        }
                    }
                } catch (e) {
                    console.error(e.message);
                    console.dir(e.trace);
                }
            })();
        },

        profileInit: function() {

            // wa_editor global variable, so has previous context
            if (wa_editor) {
                wa_editor = undefined;
            }

            if (this.options.edit_rights) {

                var $product_name = $('#shop-productprofile').find('.s-product-name');
                if (parseInt(this.path.id, 10)) {
                    $product_name.addClass('editable');
                } else {
                    $product_name.removeClass('editable');
                }

                $product_name.inlineEditable({
                    minSize: {
                        width: 150
                    },
                    maxSize: {
                        width: 550
                    },
                    inputClass: 's-title-h1-edit',
                    beforeMakeEditable: function() {
                        $('#s-edit-product').hide();
                    },
                    afterBackReadable: function(input, data) {
                        $('#s-edit-product').show();
                        if (!data.changed) {
                            return false;
                        }
                        $.shop.jsonPost('?module=product&action=save&id=' + $.product.path.id, {
                            update: {
                                name: $(input).val()
                            }
                        }, function() {
                            document.title = $(input).val() + $.product.options.title_suffix;
                        });
                    },
                    hold: function() {
                        return !$(this).hasClass('editable');
                    }
                });
            }
            var self = this;

            setTimeout(function() {
                self.call('profileLazyInit', []);
            }, 2000);

        },
        profileLazyInit: function() {
            if (!$('#product-sales-plot').empty().length) {
                return;
            }
            if (!$('#product-sku-plot').empty().length) {
                return;
            }
            if (sales_plot_data && sales_plot_data.length) {
                $.jqplot('product-sales-plot', sales_plot_data, {
                    seriesColors: ["#3b7dc0", "#129d0e", "#a38717", "#ac3562", "#1ba17a", "#87469f", "#6b6b6b", "#686190", "#b2b000", "#00b1ab", "#76b300"],
                    grid: {
                        borderWidth: 0,
                        shadow: false,
                        background: '#ffffff',
                        gridLineColor: '#eeeeee'
                    },
                    series: [{
                                color: '#129d0e',
                                yaxis: 'y2axis',
                                shadow: false,
                                lineWidth: 3,
                                fill: true,
                                fillAlpha: 0.1,
                                fillAndStroke: true,
                                markerOptions: {
                                    show: false
                                },
                                rendererOptions: {
                                    highlightMouseOver: false
                                }
                            }],
                    axes: {
                        y2axis: {
                            min: 0,
                            tickOptions: {
                                markSize: 0
                            }
                        },
                        xaxis: {
                            // renderer:$.jqplot.DateAxisRenderer,
                            pad: 1,
                            showTicks: false
                        }
                    },
                    highlighter: {
                        lineWidthAdjust: 12.5
                    }
                });
            } else {
                $('#product-sales-plot').remove();
            }
            if (sku_plot_data && sku_plot_data.length && sku_plot_data[0] && sku_plot_data[0].length) {

                $.jqplot('product-sku-plot', sku_plot_data, {
                    seriesColors: ["#0077CC", "#33BB11", "#EE5500", "#EEBB11", "#44DDDD", "#6b6b6b", "#686190", "#b2b000", "#00b1ab", "#76b300"],
                    grid: {
                        borderWidth: 0,
                        background: '#ffffff',
                        shadow: false
                    },
                    legend: {
                        show: true,
                        location: 's'
                    },
                    seriesDefaults: {
                        shadow: false,
                        renderer: $.jqplot.PieRenderer,
                        rendererOptions: {
                            padding: 0,
                            sliceMargin: 1,
                            showDataLabels: false
                        }
                    }
                });
            } else {
                $('#product-sku-plot').remove();
            }
        }

    };
})(jQuery);
