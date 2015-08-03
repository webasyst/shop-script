editClick:(function ($) {
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
            'id': null, /* new or int product ID */
            'mode': null, /* null|profile|edit */
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
        getData: function (section, name) {
            return (this.data[section] || {})[name];
        },
        setData: function (section, name, value) {
            this.data[section] = this.data[section] || {};
            this.data[section][name] = value;
        },

        standalone_tabs: ['images', 'services', 'pages', 'reviews'],

        /**
         * Setup options
         *
         * @param {object} options
         * @param {String} tab
         */
        init: function (options, tab) {
            this.setOptions(options, tab);
        },

        setOptions: function (options, tab) {
            if (tab) {
                this[tab + '_options'] = $.extend(
                    this[tab + '_options'] || {}, options || {}
                );
            } else {
                this.options = $.extend(this.options, options || {});
            }
            this.options.sidebar_width = this.options.sidebar_width || 250;
        },

        get: function (type) {
            if (this.options[type + '_selector']) {
                return $(this.options[type + '_selector']);
            } else {
                throw new exception('');
            }
        },

        /**
         * @param {String} path
         * @return {{id:number,mode:string,tab:string,tail:string,raw:string,params:{}}}
         */
        parsePath: function (path) {
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
                id: matches[0] || 'new',
                mode: matches[1] || 'profile',
                tab: matches[1] ? matches[2] || 'main' : false,
                tail: matches.slice(3).join('/') || '',
                raw: path,
                params: params
            };
        },

        /**
         *
         * @param {String} path
         */
        dispatch: function (path) {
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

        termination: function () {
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

        isCallable: function (name) {
            return (typeof(this[name]) == 'function');
        },
        tabIsLoaded: function (path) {
            var $tab = $('#s-product-edit-forms').find('.s-product-form.' + path.tab);
            return ($tab.length ? true : false);
        },

        call: function (name, args, callback) {
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
        load: function (path) {
            var $container = this.get('container');
            if (!$container.length || ($container.data('product-id') != path.id)) {
                var self = this;
                var url = '?module=product&id=' + path.id;
                $.shop.trace('$.product.load product', url);
                $.products.load(url, function () {
                    self.dispatch(path);
                });
            }

            this.editTabMainData.sku_id = (path.id == 'new') ? -1 : 0;
            this.editTabMainData.stocks = {};
        },

        focus: function () {
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

        blur: function () {
            this.path.id = null;
            this.path.mode = null;
            this.path.tab = null;
            this.path.tail = null;
            $('*').off('.product');
            $('#mainmenu').find('.s-level2').show();
            $('#s-product-edit-menu, #s-product-edit-save-panel').hide();
            var duration = 'fast';

            $('#s-sidebar').show().animate({
                width: $.product.options.sidebar_width
            }, duration).queue(function () {
                $(this).dequeue();
            });
            $('#s-toolbar').show().animate({
                width: '200px'
            });
            $('#maincontent').animate({
                'margin-top': '84px'
            }, duration);
            $('#s-content').animate({
                'margin-left': $.product.options.sidebar_width
            }, duration);
        },

        saveData: function (mode, tab, callback) {
            var self = this;
            var form = self.get('form');
            var sku_type;

            var sku_type_input_type = 'radio';
            var sku_type_input = form.find('input[name="product[sku_type]"]:first');
            if (sku_type_input.is(':radio')) {
                sku_type = form.find('input[name="product[sku_type]"]:checked').val();
                sku_type_input = form.find('input[name="product[sku_type]"]');
            } else {
                sku_type_input_type = 'hidden';
                sku_type = form.find('input[name="product[sku_type]"]:not(:disabled)').val();
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

            if (self.ajax.save) {
                setTimeout(function () {
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

            if ($('#s-product-description-content').length) {
                $('#s-product-description-content').waEditor('sync');
            }

            // cut out all spaces for prices
            form.find('.s-price').find('input').each(function () {
                /**
                 *
                 * @this HTMLInputElement
                 */
                this.value = this.value.replace(/\s+/g, '');
            });

            $.shop.trace('$.product.saveData(' + mode + ',' + tab + ')');

            var $tags_input = $('#product-tags_tag');
            if ($tags_input.length) {
                var e = jQuery.Event("keypress", {
                    which: 13
                });
                $tags_input.trigger(e);
            }
            /* disable not changed data */
            $(form).find(':input[name^="product\\["]:not(:disabled)').each(function () {
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

            $.ajax({
                'url': $(form).attr('action'),
                'data': $(form).serializeArray(),
                'dataType': 'json',
                'type': 'post',
                success: function (response) {
                    if (response.status == 'fail') {
                        self.refresh('error', response.errors);
                    } else if (response.data.redirect) {
                        $.shop.trace('$.product.saveData redirect', response.data.redirect);
                        //$.product.helper.options.defaultChangedStatus = false;
                        window.location.href = response.data.redirect;
                    } else {
                        //$.product.helper.options.defaultChangedStatus = false;
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
                error: function (jqXHR, textStatus, errorThrown) {
                    self.ajax.save = false;
                    self.refresh('error', textStatus);
                }
            });
            return false;
            // force reload data
            // this.container.data('product-id',this.path.id + '-edited');
        },

        updateData: function (data, mode, tab) {

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
                    function () {
                        $(this).attr('href', $(this).attr('href').replace(pattern, replace));
                    });
                $('#s-product-edit-forms').find(':input[name="product\\[id\\]"]').val(this.path.id);

                window.location.hash = hash;
            } else if (this.path.id != data.id) {
                $.shop.error('Abnormal product ID change', [this.path.id, data]);
            }

            var h1 = $('#shop-productprofile h1');
            h1.find('.s-product-name:first').text(data.name);
            h1.find('.s-product-id').show().text('id: ' + data.id);
            document.title = data.name + $.product.options.title_suffix;

            $('#product-stock-stat').html(tmpl('template-product-stock-stat', data.raw));

            var $container = tab_content.find(':input[name="product\\[type_id\\]"]').parents('.value');
            $container.data('type', data.type_id);

            if (!$.isEmptyObject(data.frontend_urls)) {
                // update frontend url widget in product edit page
                var frontend_url = $('#s-product-frontend-url');
                frontend_url.text(data.url);

                var frontend_url_input = $('#s-product-frontend-url-input');
                frontend_url_input.val(data.url);

                frontend_url.trigger('readable');

                var frontend_url_link = frontend_url.parent();
                frontend_url_link.attr('href', data.frontend_urls[0].url);

                frontend_url_link.find('span:first').text(data.frontend_urls[0].base);

                // update other frontend url
                frontend_url.closest('div.value').find('.s-product-frontend-url').each(function (i) {
                    if (data.frontend_urls[i + 1]) {
                        $(this).attr('href', data.frontend_urls[i + 1].url).text(data.frontend_urls[i + 1].url);
                    }
                });

                $('.s-product-frontend-url-not-empty').show();
                $('.s-product-frontend-url-empty').hide();

                // update fronted url in product profile page
                var html = '';
                for (var i = 0, len = data.frontend_urls.length; i < len; i += 1) {
                    html += ' <span class="s-product-frontend-url-not-empty">' +
                    '<a href="' + data.frontend_urls[i].url + '" target="_blank">' + data.frontend_urls[i].url + '</a><i class="icon10 new-window"></i>' +
                    '</span> ';
                }
                if (html) {
                    $('#s-product-frontend-links').find('.s-product-frontend-url-not-empty').
                        wrapAll('<div></div>').closest('div').replaceWith(html);
                }


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
            tab_content.find('select.s-product-categories').each(function () {
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

                // storefront links
                var category_id = parseInt(val, 10);
                if (!$.isEmptyObject(data.storefront_map[category_id])) {
                    var html = '';
                    var storefront_links = data.storefront_map[category_id];
                    for (var i = 0; i < storefront_links.length; i += 1) {
                        html += '<a class="hint" href="' + storefront_links[i] + '" target="_blank">' + storefront_links[i] + '</a> ';
                    }
                    parent.find('.s-storefront-map').html(html);
                }

            });

            $('#s-product-categories').html(tmpl('template-product-categories', {
                categories: data.categories || []
            }));

            $('#s-product-tags').html(tmpl('template-product-tags', {
                tags: data.tags || []
            }));

            $('#s-product-save table.s-product-skus > tbody > tr').each(function () {
                var sku_id = $(this).data('id');
                $(this).find('td.s-stock .s-product-stock-icon').each(function () {
                    var stock_id = $(this).data('stock');
                    if (data.raw && data.raw.skus && data.raw.skus[sku_id] && data.raw.skus[sku_id].stock_icon[stock_id]) {
                        $(this).html(data.raw.skus[sku_id].stock_icon[stock_id]);
                    }
                });
            });

            // skus setttings
            tab_content.find('tr.js-sku-settings').each(function () {
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

            this.helper.checkChanges(this.get('form'), true);
            /*, data.raw || {}*/
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

            $('#s-product-meta-title').attr('placeholder', data.default_meta_title);
            $('#s-product-meta-keywords').attr('placeholder', data.default_meta_keywords);
            $('#s-product-meta-description').attr('placeholder', data.default_meta_description);

            $('#s-product-profile-tabs .s-tab-block[data-tab="stock-logs"]').trigger('refresh');

            this.setData('main', 'category_name', data.category_name);

            // TODO update name/description/etc fields
        },

        refresh: function (status, message) {
            /* enable previos disabled inputs */
            $(this.options.form_selector).find(':input[name^="product\\["]:disabled.js-ajax-disabled').each(function () {
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
                case 'submit':
                {
                    $container.addClass('status');
                    break;
                }
                case 'error':
                {
                    $container.addClass('errormsg');
                    for (var i = 0; i < message.length; i++) {
                        $container.append(message[i][0]);
                    }
                    timeout = 20000;
                    break;
                }
                case 'success':
                {
                    if (message) {
                        $container.addClass('successmsg').append(message);
                    }
                    timeout = 3000;
                    $('#s-product-edit-save-panel :submit').removeClass('yellow').addClass('green');
                    break;
                }
            }
            if (timeout) {
                this.timer = setTimeout(function () {
                    $container.removeClass('errormsg successmsg status').empty().show();
                }, timeout);
            }
        },

        editTabLoad: function (path, force) {
            var self = this;
            var $tab = $('#s-product-edit-forms .s-product-form.' + path.tab);
            if (force || (!$tab.length && (path.id == 'new'))) {
                // XXX
                $.shop.trace('product.profileTabHandler: create', [path.tab + ' — create', path]);
                this.saveData(this.path.mode, path.tab, function () {
                    if (path.tab) {
                        //var tab = path.tab.substr(0, 1).toUpperCase() + path.tab.substr(1);
                        self.call(path);
                    }
                });
            } else {
                this.editTabLoadContent(path);
            }
        },

        editTabLoadContent: function (path, post) {
            var self = this;
            path = path || this.path;
            var url = '?module=product&action=' + path.tab + '&id=' + path.id;
            if (path.tail) {
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
            $.shop.trace('$.product.editTabLoadContent', [path, url, path.params]);
            if (path.params && post) {
                var type = typeof(path.params);
                switch (type) {
                    case 'String':
                        url += path.params;
                        break;
                    case 'Array':
                        url += path.params.serialize();
                        break;
                    default:
                        $.shop.error('unexpected type ' + type, path.params);
                }
            }
            $.ajax({
                url: url,
                type: post ? 'POST' : 'GET',
                data: post ? (post || {}) : (path.params || {}),
                success: function (data, textStatus) {
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
                    if (path.params) {
                        if (!$.isEmptyObject(path.params)) {
                            var ar = [];
                            for (var k in path.params) {
                                if (path.params.hasOwnProperty(k)) {
                                    ar.push(k + '=' + path.params[k]);
                                }
                            }
                            hash += ar.join('&');
                        }
                    }
                    window.location.hash = hash;
                    self.dispatch(path);
                }
            });

        },

        editTabInit: function (path) {
            $('html, body').animate({
                scrollTop: 0
            }, 200);
            $('#shop-productprofile').find('.s-product-name').removeClass('editable');
        },

        editTabFocus: function (path) {
            var $tab_link = $('#s-product-edit-menu li.' + path.tab);
            $tab_link.addClass('selected');
            this.refresh();
        },

        editTabBlur: function (path) {
            if (!path) {
                return;
            }

            var self = this;
            var tab = path.tab;

            if (this.standalone_tabs.indexOf(tab) !== -1) {
                this.helper.checkChanges(this.get('form'), true, function () {
                    self.saveData(self.path.mode, tab, function () {
                        if (tab) {
                            self.call(path);
                        }
                    });
                });
            }

            $('#s-product-edit-forms .s-product-form').hide();
            $('#s-product-edit-menu li.selected').removeClass('selected');
            this.path.tab = null;
            this.path.tail = null;
        },

        editTabShow: function (path) {
            var $tab = $('#s-product-edit-forms .s-product-form.' + path.tab);
            if ($tab.length) {
                $tab.show();
                $.shop.trace('$.product.showTab', path);
                this.path.tab = path.tab;
            } else {
                $.shop.error('$.product.showTab', path);
            }
        },

        editClick: function ($el) {
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

        switchSubMenu: function (menu) {
            menu = menu || 'default';
            $('#mainmenu .s-submenu > *').hide();
            $('#mainmenu .s-submenu .s-submenu-' + menu).show();
        },

        profileFocus: function () {
            var duration = this.options.duration;
            var self = this;

            // back to product profile view
            $('#s-product-edit-menu, #s-product-edit-save-panel').hide();
            self.switchSubMenu();

            $('#s-sidebar').show().animate({
                width: $.product.options.sidebar_width
            });

            $('#s-toolbar').show().animate({
                width: '200px'
            }, duration).queue(function () {
                $(this).dequeue();
                $('#s-product-edit-forms').hide();
                $('#s-product-profile-page, h1 .s-product-edit-link, #mainmenu .s-level2, #s-product-frontend-links').show();
                self.get('container').find('.back').attr('href', '#/' + $.products.list_hash);
            });
            $('#maincontent').animate({
                'margin-top': '84px'
            }, duration);
            $('#s-content').animate({
                'margin-left': $.product.options.sidebar_width
            }, duration);

            $('#shop-productprofile').
                off('click.edit-product', 'a.js-action').
                on('click.edit-product', 'a.js-action', function () {
                    return self.editClick($(this));
                });

        },

        profileAction: function () {
            this.path.mode = 'profile';
        },

        disableSkus: function (virtual, disabled) {
            var target = $(this.options.form_selector).find('.s-sku-list');
            if (virtual) {
                if (disabled) {
                    target.find('.s-sku-virtual').hide().find(':input').attr('disabled', true);
                } else {
                    target.find('.s-sku-virtual').show().find(':input').attr('disabled', false);
                }
            } else {
                if (disabled) {
                    target.hide();
                } else {
                    target.show();
                }
                target.find(':input').attr('disabled', disabled);
            }
        },

        /**
         *
         * @param {Number} type_id
         * @param {object=} data
         */
        skuTypeFeaturesSelectableLoad: function (type_id, data) {
            var self = this;
            // ajax for features selectable
            var url = '?module=product&action=featuresSelectable&id=' + this.path.id;
            var post = {
                type_id: type_id,
                sku_type: 1
            };

            $('#s-product-feature-superposition-field-group').load(url, post, function () {
                //TODO XXX update base price & currency from data
                var $this = self.featureSelectableInit();
                if (data) {
                    if (data.base_price_selectable) {
                        $this.find(':input[name="product[base_price_selectable]"]').val(data.base_price_selectable);
                    }
                    if (data.currency) {
                        $this.find('select.s-product-currency').val(data.currency);
                    }
                }
                self.onSkuTypeEnabled(type_id);
            });
        },

        onSkuTypeEnabled: function (type_id) {
            var $features = $('#s-product-feature-superposition-field-group');
            if (type_id) {
                $features.data('type', type_id);
            }
            $features.show();
            $features.find('input').attr('disabled', false);

            if ($.product.path.id == 'new') {
                $.product.disableSkus(false, true);
            } else {
                $.product.disableSkus(true, false);
            }

            $(this.options.form_selector).find('.s-product-skus').closest('.field').find('.name').hide();
        },


        onSkuTypeChange: function (sku_type) {
            var $features = $('#s-product-feature-superposition-field-group');
            $.shop.trace('onSkuTypeChange', {sku_type: sku_type});

            // selectable features case
            if (sku_type == '1') {
                var product_type = $(this.options.form_selector + ' :input[name="product[type_id]"]').val();
                if ($features.data('type') != product_type) {
                    var data = {
                        base_price_selectable: $features.find(':input[name="product[base_price_selectable]"]').val(),
                        currency: $features.find('select.s-product-currency').val()
                    };
                    $.product.skuTypeFeaturesSelectableLoad(product_type, data);
                } else {
                    $.product.onSkuTypeEnabled();
                }
                // flat sku case
            } else {
                $features.hide();
                $features.find('input').attr('disabled', true);
                //product_skus.find('.alist .all-skus').hide();

                var $scope = $(this.options.form_selector).find('.s-product-skus');

                if ($.product.path.id == 'new') {
                    $.product.disableSkus(false, false);
                } else {

                    var $product_skus = $scope.find('tbody');
                    $.product.disableSkus(true, true);
                    // empty skus - emulate add new sku
                    if (!$product_skus.find('>tr:not(.s-sku-virtual)').length) {
                        $.product.editTabMainSkuAdd();
                        // make default
                        $product_skus.find('>tr:not(.s-sku-virtual):first').find('input[name="product[sku_id]"]').attr('checked', true);
                    }
                }
                $scope.closest('.field').find('.name').show();
            }
        },

        // feature superposition (selectable features) handlers
        featureSelectableInit: function () {
            var form = $(this.options.form_selector);
            var $this = $('#s-product-feature-superposition');
            var product_skus = form.find('.s-product-skus');
            var base_price_selectable = form.find(':input[name="product[base_price_selectable]"]');

            // change salling mode (sku type)

            // click to feature item
            $this.on('click', 'ul.features>li', function () {
                // make selected
                var li = $(this);
                li.parent().find('li.selected').removeClass('selected');
                li.addClass('selected');

                // show proper div with values
                var feature_id = li.attr('data-feature-id');
                var feature_values = $this.find('.feature-values[data-feature-id=' + feature_id + ']');
                $this.find('.feature-values').hide();
                feature_values.show();

                return false;
            });

            // click to feature value checkbox
            $this.on('change', 'ul.values>li input', function () {

                var self = $(this);
                var li = self.parents('li:first');
                var ul = li.parent();
                var count = ul.find('>li input:checked').length;

                // update count
                var feature_li = $this.find('ul.features li.selected');
                feature_li.find('.count').text(count || '');

                // update icon
                var icon_class = count ? 'status-blue-tiny' : 'status-gray-tiny';
                feature_li.find('i').
                    removeClass('status-blue-tiny status-gray-tiny').
                    addClass(icon_class);

                updateSuperpositionCount();
            });


            $.shop.changeListener(base_price_selectable, function() {
                product_skus.find('.s-sku-virtual .s-price input').val($(this).val());
            });

            // if at least one input of sku is changed this sku turn into not virtual
            $.shop.changeListener(product_skus, function () {
                var self = $(this);
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
                    sku_tr = product_skus.find('tr[data-id=' + sku_id + ']:first');
                    if (!sku_tr.length) {
                        return;
                    }
                }
                sku_tr.find('input.s-input-virtual').val(0);
                sku_tr.removeClass('s-sku-virtual');

                $('#s-product-view').find('tr[data-id=' + sku_id + ']').removeClass('s-sku-virtual');
            }, 'input, textarea');

            // update superposition count texts helper
            var updateSuperpositionCount = function () {
                var factors = [];
                $this.find('ul.features>li').each(function () {
                    var li = $(this);
                    var cnt = parseInt(li.find('.count').text(), 10);
                    if (cnt) {
                        factors.push(cnt);
                    }
                });

                var counter = $this.find('.superposition-count');
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

            $this.find('i.status-blue-tiny:first').parents('li').trigger('click');
            return $this;
        },

        editInit: function () {

            // Ctrl+S hotkey handler
            $('#s-product-edit-forms').unbind('keydown.product').
                bind('keydown.product', function (e) {
                    if ($(e.target).is(':input') && e.ctrlKey && e.keyCode == 83) {
                        $('#s-product-save-button').click();
                        return false;
                    }
                });

            $.shop.makeFlexibleInput('s-product-meta-title');

            // check rights
            if (!this.options.edit_rights) {
                window.location.hash = '#/product/' + this.path.id + '/';
                return false;
            }
            var $form = $(this.options.form_selector);
            $form.bind('submit.product', function (e) {
                return $.product.saveData('profile', null);
            });
            $form.on('change.product, keyup.product', 'div.s-product-form:not(.ajax) :input', function (e) {
                $.product.helper.onChange($(this).parents('div.s-product-form'));
            });
            $form.on('change.product, keyup.product, keypress.product', ':input[name="product\[name\]"]', function (e) {
                $.product.helper.onNameChange($(this), false, $.product.options.update_delay || 500);
            });

            var product_tags = $('#product-tags');
            if (!product_tags.data('tags_input_init')) {
                product_tags.tagsInput({
                    autocomplete_url: '?module=product&action=tagsAutocomplete',
                    height: 120,
                    onChange: function() {
                        $.product.updateMetaFields();
                    },
                    defaultText: ''
                }).data('tags_input_init', true);

                $('#s-product-popular-tags').off('click.product', 'a').
                    on('click.product', 'a', function () {
                        var name = $(this).text();
                        product_tags.removeTag(name);
                        product_tags.addTag(name);
                    }
                );

            }

            $form.on('change.product', '.s-product-currency', function () {
                var $self = $(this), val = $self.val();
                $form.find('.s-product-currency').val(val);
                $form.find('.s-product-currency-readonly').text(val);
                $('#s-product-currency-code').val(val);
            });

            $form.on('change.product', ':input[name="product\[type_id\]"]', function () {
                return $.product.editTabMainTypeChange($(this));
            });

            $form.on('change', 'select[name="product[status]"]', function () {
                if ($(this).val() == '1') {
                    $(this).prev().removeClass('no-bw').addClass('yes');
                    $('.s-product-status-text').hide();
                } else {
                    $(this).prev().removeClass('yes').addClass('no-bw');
                    $('.s-product-status-text').show();
                }
            });

            $form.on('change', 'input[name="product[sku_type]"]', function () {
                $.product.onSkuTypeChange(this.value);
            }).change();

            $.product.featureSelectableInit();
        },

        editFocus: function () {
            var duration = this.options.duration;
            var self = this;
            $('h1 .s-product-edit-link, #mainmenu .s-level2, #s-product-frontend-links').hide();

            $('#s-sidebar, #s-toolbar').animate({
                width: 0
            }, duration).queue(function () {
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
                on('click.edit-product', 'a.js-action', function () {
                    return self.editClick($(this));
                });
        },

        editBlur: function () {
            this.path.tab = false;
            this.path.mode = false;
            $($.product.options.form_selector).off('submit.product');
            $(this.options.form_selector).off('change.product, keyup.product');
            $('#s-product-edit-forms').off('click.edit-product');
        },

        /**
         *
         * @param {{id:number,mode:{String},tab:{String}}} path
         */
        editAction: function (path) {
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

        editTabMainInit: function (path) {
            $('#s-product-type').each(function () {
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
                update: function (event, ui) {
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
                start: function (event, ui) {
                    $table.find('> tr.js-sku-settings').hide();
                },
                stop: function (event, ui) {
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
                beforeMakeEditable: function (input) {
                    var self = $(this);
                    var parent = self.closest('span.s-product-frontend-url-not-empty');
                    var slash = parent.find('span.slash');
                    var new_window = parent.find('i.new-window');
                    $(input).after(slash);
                    new_window.hide();

                    parent.find('a.s-frontend-base-url').hide()
                    parent.find('span.s-frontend-base-url').show();
                },
                beforeBackReadable: function (input, data) {
                    var self = $(this);
                    var parent = self.closest('span.s-product-frontend-url-not-empty');
                    var slash = parent.find('span.slash');
                    var new_window = parent.find('i.new-window');
                    new_window.show();

                    parent.find('span.s-frontend-base-url').hide();
                    parent.find('a.s-frontend-base-url').show().append(slash);
                }
            });
            if (!parseInt(path.id, 10)) {
                frontend_url.trigger('editable');
            }

            // select last static category on create new product
            if (path.id == 'new') {
                if ($.product_list && $.isArray($.product_list.collection_hash)) {
                    if ($.product_list.collection_hash[0] == 'category') {
                        main_tab_content.find('select.s-product-categories').val(
                            $.product_list.collection_hash[1]
                        );
                    } else if ($.product_list.collection_hash[0] == 'set') {
                        main_tab_content.find('.add-set-button').click().closest('.field').find('select:first').val($.product_list.collection_hash[1]);
                    }
                }
            }

            main_tab_content.off('change.product', 'select.s-product-categories').on('change.product', 'select.s-product-categories', function (e) {
                var self = $(this);
                var val = self.val();
                var parent = self.parent();
                var del_button = parent.find('.s-product-delete-from-category');

                var category_id = parseInt(val, 10) || 0;
                if (!category_id) {
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
                    parent.find('input.val').val(category_id);
                }
            });

            // Enter-press handler when foucus on input
            main_tab_content.off('keydown.product', 'input.new-category').on('keydown.product', 'input.new-category', function (e) {
                if (e.keyCode == 13) {
                    $(this).parent().find('input[type=button]').click();
                    return false;
                }
            });

            // saving new category
            main_tab_content.off('click.product', '.s-new-category input[type=button]').on('click.product', '.s-new-category input[type=button]', function (e) {
                var self = $(this).parent();
                var parent = self.parent();
                var input = self.find('input[name=new_category]');
                var value = input.val();
                $.shop.jsonPost('?module=products&action=saveListSettings&category_id=0&parent_id=0', {
                    name: value
                }, function (r) {
                    var select = parent.find('select');
                    var place = select.find('option.separator:first').show();
                    $.when(place.after('<option class="category" value="' + r.data.id + '">' + r.data.name + '</option>')).then(function () {
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
                function () {
                    var self = $(this);
                    var parent = self.parent('div');
                    var select = parent.find('select');

                    if (select.length) {
                        var categories = $('#s-product-edit-forms .s-product-form.main select.s-product-categories');
                        if (categories.length > 2) {
                            select.attr('disabled', true);
                            var deleted = parent.parents('div.field:first').find('input[name="product\[categories\.deleted\]"]');
                            if (parseInt(select.val()) > 0) {
                                var deleted_list = deleted.val().split(',');
                                deleted_list.push(select.val());
                                deleted_list.sort();
                                deleted.val(deleted_list.join(','));
                                $.product.helper.onChange($.product.get('form').find('div.main:first'));
                            }
                            parent.remove();
                        } else {
                            select.val('selected').attr('disabled', false);
                            parent.find('input.val').val(0).attr('disabled', true).parent().hide();
                            parent.find('.s-storefront-map').html('');
                            parent.find('.s-product-delete-from-category').hide();
                        }
                    } else {
                        var deleted = parent.parents('div.field:first').find('input[name="product\[categories\.deleted\]"]');
                        select = self.parents('.value:first').find(':input:first');
                        if (parseInt(select.val()) > 0) {
                            var deleted_list = deleted.val().split(',');
                            deleted_list.push(select.val());
                            deleted_list.sort();
                            deleted.val(deleted_list.join(','));
                            $.product.helper.onChange($.product.get('form').find('div.main:first'));
                        }
                        self.parents('.value:first').remove();
                    }
                    return false;
                }
            );
        },
        editTabMainAction: function (path) {
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
                setTimeout(function () {
                    $(selector).focus();
                    window.location.hash = '#/product/' + path.raw.replace(/\/focus=.*$/, '/');
                }, 100);
            }
        },
        editTabMainBlur: function () {
        },
        editTabMainSave: function () {
            if (this.call('editTabMainSkuEproductSave') === false) {
                return false;
            }
        },

        helper: {
            options: {
                tab_changed: '<i class="icon10 status-yellow-tiny"></i>',
                defaultChangedStatus: false
            },

            data: {
                url_helper: {
                    url: '',
                    name: '',
                    timer: null
                }
            },
            init: function () {
                this.data.url_helper = {
                    url: '',
                    name: '',
                    timer: null
                };
            },
            onTabChanged: function (tab, changed) {
                $.shop.trace('$.product.onTabChanged id=' + tab + ' changed=' + changed);
                $('#s-product-edit-menu li.' + tab + ' .s-product-edit-tab-status').html(changed ? this.options.tab_changed : '');
            },

            /**
             * Get current product type id
             *
             * @param {} type
             * @return {}
             */
            type: function (type) {
                return parseInt(type, 10) || parseInt($('#s-product-edit-forms .s-product-form.main :input[name="product\\[type_id\\]"]').val(), 10) || 0;
            },

            onNameChange: function (element, animate, delay) {
                if (this.data.url_helper.timer) {
                    clearTimeout(this.data.url_helper.timer);
                }
                var target = $($.product.options.form_selector).find(':input[name="product\\[url\\]"]');
                var parent = target.parent();
                if (($.product.path.id && ($.product.path.id != 'new')) || (target.val() != this.data.url_helper.url)) {
                    $.shop.trace('$.product.onNameChange stop ' + this.data.url_helper.url + ' != ' + target.val());
                    $($.product.options.form_selector).off('.product', ':input[name="product\\[name\\]"]');
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
                    this.data.url_helper.timer = setTimeout(function () {
                        self.urlHelper(element, target, delay);
                    }, delay || 500);

                }
            },
            onChange: function (container) {
                var id = this.getContainerId(container);
                var self = this;
                this.checkChanges(container.parents('form'), false, function (changed) {
                    $('#s-product-edit-save-panel :submit').removeClass(changed ? 'green' : 'yellow').addClass(changed ? 'yellow' : 'green');
                    if (changed) {
                        self.checkChanges(container, false, function (changed) {
                            self.onTabChanged(id, changed);
                        });
                    } else {
                        self.onTabChanged(id, changed);
                    }
                });
            },
            urlHelper: function (element, target) {
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
                        'dataType': 'html',
                        'data': data
                    }).done(function (response) {
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
            getValueByName: function (data, name) {
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

            checkChangesStack: {},

            /**
             *
             * @param {jQuery} $container
             * @param {Boolean} update
             */
            checkChanges: function ($container, update, onChange) {
                var id = $container.attr('id');
                if (this.checkChangesStack[id]) {
                    clearTimeout(this.checkChangesStack[id]);
                    this.checkChangesStack[id] = null;
                }
                $.shop.trace('$.product.helper.checkChanges', [$container, update]);
                var self = this;
                this.checkChangesStack[id] = setTimeout(function () {
                    var changed = self.checkChangesDelayed($container, update);
                    if (onChange) {
                        onChange(changed);
                    }
                }, update ? 50 : 500);
            },
            checkChangesDelayed: function ($container, update) {
                /**
                 * @todo add update relataed text tags
                 * @todo extract it into separate plugin
                 */
                var changed = this.options.defaultChangedStatus;
                var self = this;
                var selector = ':input:not(.js-ignore-change)';
                if ($container.hasClass('s-product-form')) {
                    if ($container.hasClass('ajax')) {
                        return false;
                    }
                } else {
                    selector = '.s-product-form:not(.ajax) ' + selector;
                }
                $container.find(selector).each(function () {
                    var type = ($(this).attr('type') || this.tagName).toLowerCase();
                    switch (type) {
                        case 'input':
                        case 'text':
                        case 'textarea':
                            /**
                             * @this HTMLInputElement
                             */
                            if ($(this).hasClass('ace_text-input')) {
                                break;
                            }
                            if (this.defaultValue != this.value) {
                                changed = true;
                                if (update) {
                                    this.defaultValue = this.value;
                                    self.updateInput(this.name, this.value, type == 'textarea');
                                }
                            }
                            break;
                        case 'radio':
                        case 'checkbox':
                            /**
                             * @this HTMLInputElement
                             */
                            if (this.defaultChecked != this.checked) {
                                changed = true;
                                if (update) {
                                    this.defaultChecked = this.checked;
                                }
                            }
                            break;
                        case 'select':
                            if (this.length) {
                                $(this).find('option').each(function () {
                                    /**
                                     * @this HTMLSelectElement
                                     */
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
                        case 'file':
                            /**
                             * @this HTMLInputElement
                             */
                            if (this.value) {
                                changed = true;
                                if (update) {
                                    this.value = null;
                                }
                            }
                            break;
                        case 'reset':
                        case 'button':
                        case 'submit':
                            // ignore it
                            break;
                        case 'hidden':
                            // do nothing
                            break;
                        default:
                            $.shop.error('$.product.checkChangesDelayed unsupported type ' + type, [type, this]);
                            break;
                    }
                    if (!update && changed) {
                        $.shop.trace('$.product.helper.checkChangesDelayed', [this, changed, this.defaultValue, this.value]);
                    }
                    return update || !changed;
                });
                return changed;
            },

            getContainerId: function (container) {
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
            updateInput: function (name, value, html, id) {
                if (name) {
                    var selector = '.s-' + name.replace(/\[(.+?)\]/g, '-$1') + '-input';
                    $.shop.trace('update field: ' + name + ' ' + selector, value);
                    var el = $(selector);
                    if (el.is('input,textarea')) {
                        el.val(value);
                    } else if (html) {
                        if (name === "product[summary]") {
                            el.text(value);     // escaped
                        } else {
                            el.html(value);     // not escaped
                        }
                    } else {
                        el.text(value);
                    }
                    if (id) {
                        var input_name = $(selector).attr('name');
                        input_name = input_name.replaceAll(/\[-[\d]+\]/, '[' + id + ']');
                        $.shop.trace('$.product.helper.updateInput name', [input_name]);
                    }
                }
            },
            count: function (obj) {
                var size = 0;
                for (var key in obj) {
                    if (obj.hasOwnProperty(key))
                        size++;
                }
                return size;
            }
        },

        multiSkus: function (count) {
            var table = $('#s-product-edit-forms .s-product-form.main table.s-product-skus:first');
            if (count > 1) {
                table.find('thead tr').show();
                table.find('.s-name,.s-sku-sort').show('slow');
                table.find('.delete').parent('a').show();
                if (count == 2) {
                    table.find('> tbody:first > tr:first .s-name :input').focus();
                } else {
                    table.find('> tbody:first > tr:last .s-name :input').focus();
                }
            } else {
                table.find('thead tr').hide();
                table.find('.s-name,.s-sku-sort').hide();
                table.find('.delete').parent('a').hide();
            }
        },

        editTabMainSkuAdd: function () {
            try {
                var $table = $('#s-product-edit-forms .s-product-form.main table.s-product-skus:first');
                var $skus = $table.find('tbody:first');

                var price = 0;
                var price_loc = '0';     // price in light of l18n (with ',' or '.')

                $skus.find(':input[name$="\[price\]"]').each(function () {
                    var self = $(this);
                    var v = self.val() ? self.val().replace(',', '.') : 0;
                    var p = parseFloat(v);
                    if (p > price) {
                        price = p;
                        price_loc = self.val() || '0';
                    }
                });
                var purchase_price = 0;
                $skus.find(':input[name$="\[purchase_price\]"]').each(function () {
                    purchase_price = Math.max(purchase_price, parseFloat($(this).val()) || 0);
                });

                $.product.multiSkus($skus.find('tr:not(.js-sku-settings)').length + 1);

                //$skus.parents('table').find('tr:hidden').show();
                var sku = {
                    'id': --$.product.editTabMainData.sku_id,
                    'product_id': $('input:hidden[name="product[id]"]').val() || 'new',
                    'sku': '',
                    'available': 1,
                    'name': '',
                    'price': '' + price,
                    'price_loc': price_loc,
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
                    'stocks': this.getData('main', 'stocks'),
                    'stock_ids': this.getData('main', 'stock_ids')
                }, true));
                $skus.find('.s-product-currency').trigger('change');

            } catch (e) {
                $.shop.error(e.message, e);
            }
            return false;
        },

        editTabMainSkuSort: function (id, after_id, $list) {
            try {
                $.post('?module=product&action=skuSort', {
                    product_id: this.path.id,
                    sku_id: id,
                    after_id: after_id
                }, function (response) {
                    $.shop.trace('$.product.editTabMainSkuSort result', response);
                    if (response.error) {
                        $.shop.error('Error occurred while sorting product SKUs', 'error');
                        $list.sortable('cancel');
                    }
                }, function (response) {
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

        editTabMainProductDelete: function (el) {
            var showDialog = function () {
                $('#s-product-list-delete-products-dialog').waDialog({
                    disableButtonsOnSubmit: true,
                    onLoad: function () {
                        $(this).find('.dialog-buttons i.loading').hide();
                    },
                    onSubmit: function (d) {
                        $(this).find('.dialog-buttons i.loading').show();
                        $.shop.jsonPost('?module=products&action=deleteList', {
                            product_id: [$.product.path.id],
                            get_lists: 1
                        }, function (r) {
                            if ($.product_list) {
                                $('#s-sidebar').trigger('update', r.data.lists);
                            }
                            d.trigger('close');
                            window.location.hash = '#/products/';
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
         * @param {Number} sku_id
         * @param {jQuery} $el
         * @todo real sku delete
         */
        editTabMainSkuDelete: function (sku_id, $el) {
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
                        success: function (response) {
                            if (response.status == 'fail') {
                                self.refresh('error', response.errors);
                            } else if (response.data.redirect) {
                                window.location.href = response.data.redirect;
                            } else {
                                self.refresh('success', response.data.message || 'Success');
                                $sku.hide('normal', function () {
                                    $sku.remove();
                                    $('#s-product-view table.s-product-skus > tbody > tr[data-id="' + sku_id + '"]').remove();
                                    $.product.multiSkus($skus.find('tr:not(.js-sku-settings)').length);
                                });
                                $('#s-product-edit-forms .s-product-form.main').find('input[name=product\\[sku_id\\]][value=' + response.data.sku_id + ']')
                                    .attr('checked', true);
                            }
                        },
                        error: function (jqXHR, textStatus, errorThrown) {
                            self.refresh('error', textStatus);
                        }
                    });
                } else {
                    self.refresh('error', $_('A product must have at least one SKU.'));
                }

            } else {
                $sku.hide('normal', function () {
                    $sku.remove();
                    $('#s-product-view table.s-product-skus > tbody >tr[data-id="' + sku_id + '"]').remove();
                    $.product.multiSkus($skus.find('tr:not(.js-sku-settings)').length);
                });
            }
        },

        editTabMainCategoriesAdd: function () {
            var control = $('#s-product-edit-forms .s-product-form.main select.s-product-categories:last').parent('div');
            var clone = control.clone(false);
            clone.find('select').val('select').attr('disabled', false).show();
            clone.find('input.val').val(0).attr('disabled', true).parent().hide();
            clone.find('.s-product-delete-from-category').hide();
            clone.find('.s-storefront-map').html('');
            clone.show();
            control.after(clone);
        },

        editTabMainSkuStock: function (sku_id, $el) {
            var $container = $('#s-product-sku-' + sku_id);
            $container.hide().find(':input:enabled').attr('disabled', true);

            var $stock_container = $('#s-product-sku-stock-' + sku_id);
            $stock_container.show().find(':input:disabled').removeAttr('disabled');
            $stock_container.find(':input:first').val($container.find(':input:first').val());
        },

        editTabMainSkuSettings: function (sku_id, $el) {
            $el.hide();
            var $sku = $el.parents('tr');
            var sku = $sku.data();
            var self = this;
            $.when($sku.after(tmpl('template-sku-settings', {
                'sku_id': sku_id,
                'sku': sku
            }))).done(function () {
                var url = '?module=product&action=skuSettings';
                url += '&product_id=' + self.path.id;
                url += '&sku_id=' + sku_id;
                var $target = $('#s-product-edit-forms .s-product-form.main tr.js-sku-settings[data-id="' + sku_id + '"] > td:first');
                $target.load(url, function () {
                    $sku.find(':input[name$="\[available\]"]').remove();
                    $.product_images && $.product_images.options && $.product_images.options.enable_2x && $.fn.retina && $target.find('.s-product-image-crops img').retina();

                    if (sku_id > 0) {
                        $.shop.trace('fileupload', [$target.find('.fileupload').length, typeof($target.find('.fileupload').fileupload)]);
                        try {
                            var matches = document.cookie.match(new RegExp("(?:^|; )_csrf=([^;]*)"));
                            var csrf = matches ? decodeURIComponent(matches[1]) : '';

                            $target.find('.fileupload:first').fileupload({
                                dropZone: null,
                                url: '?module=product&action=skuEproductUpload',
                                acceptFileTypes: /(\.|\/)(gif|jpe?g|png)$/i,
                                start: function () {
                                    $target.find('.fileupload:first').hide();
                                },
                                progress: function (e, data) {
                                    $.shop.trace('fileupload progress', data);
                                    var $progress = $target.find('.js-progressbar-container');
                                    $progress.show();
                                    $progress.find('.progressbar-inner:first').css('width', Math.round((100 * data.loaded / data.total), 0) + '%');

                                },
                                done: function (e, data) {
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
                                fail: function (e, data) {
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

        editTabMainSkuImageSelect: function (sku_id, image_id, $el) {
            var li = $el.parents('li:first');
            var selected = li.hasClass('selected');
            var parent = $el.parents('div.value:first');

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

        editTabMainSkuEproductSave: function (sku_id) {
            // upload eproduct files for existing skus
            var $sku_files = $('#s-product-edit-forms .s-product-form.main tr.js-sku-settings' + ((sku_id && sku_id > 0) ? ('[data-id="' + sku_id + '"]') : '')
            + ' > td:first .fileupload');
            $.shop.trace('$.product.editTabMainSkuEproductSave', $sku_files.length);
            if ($sku_files.length) {
                $sku_files.fileupload('start');
            }

        },

        editTabMainSkuEproductDelete: function (sku_id) {

        },

        editTabMainLinkEdit: function ($el) {
            return false;
        },

        /**
         * Show select input for change product type
         */
        editTabMainTypeSelect: function ($el) {
            var $container = $el.parents('form').find(':input[name="product\[type_id\]"]').parents('.value');
            if (this.path.tab == 'main') {
                $el.hide();
            } else {
                $container.find('.js-action').hide();
                window.location.hash = '/product/' + this.path.id + '/' + this.path.mode + '/';
            }
            $container.find('.js-type-name').hide();
            $container.find(':input').show().focus();
            setTimeout(function () {
                $container.find(':input').focus();
            }, 100);
        },

        editTabMainTypeChange: function ($el) {
            var $container = $el.parents('.value');
            var $type = $el.find(':selected:first');
            var type = $type.val();
            var sku_type = parseInt($type.data('sku-type'));
            var tab = 'features';
            var $tab_link;
            var href;
            $container.find('.js-type-icon').html($type.data('icon'));
            $container.find('.js-type-name').html($type.text());
            if (type != $container.data('type')) {
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

            var $sku_type = $(this.options.form_selector + ' :input[name="product[sku_type]"]');
            $.shop.trace('sku_type', [sku_type, $sku_type]);
            $sku_type.filter('[value="1"]').trigger('disabled', [sku_type ? null : true]);

            var $sku_type_container = $sku_type.parents('ul');

            if (!sku_type) {
                $sku_type.filter('[value="0"]').trigger('checked', true).change();
                $sku_type_container.find('[data-sku-type="1"]').show();
            } else {
                $sku_type.filter(':checked').change();
                $sku_type_container.find('[data-sku-type="1"]').hide();
            }

        },

        editTabMainUpdate: function (data, features_selectable_strings, old_id) {
            var $skus = $('#s-product-edit-forms .s-product-form.main table.s-product-skus tbody');
            var $skus_view = $('#s-product-view table.s-product-skus tbody');
            $skus.find('tr[data-id^="\-"]').remove();
            $skus_view.find('tr[data-id^="\-"]').remove();
            $skus.parents('table').find('tr:hidden').show();

            // take into account sort field of skus
            var skus = [];
            for (var sku_id in data.skus || {}) {
                skus.push($.extend({id: sku_id}, data.skus[sku_id]));
            }
            skus = skus.sort(function (a, b) {
                return a.sort - b.sort;
            });

            var stocks = this.getData('main', 'stocks');
            var stock_ids = this.getData('main', 'stock_ids');

            var html = '';

            // render skus in edit tab
            tmpl.cache['template-sku-edit'] = tmpl(tmpl.load('template-sku-edit')); // reset template cache
            for (var i = 0, n = skus.length; i < n; i += 1) {
                html += tmpl('template-sku-edit', {
                    'sku_id': skus[i].id,
                    'sku': skus[i],
                    'stocks': stocks,
                    'stock_ids': stock_ids,
                    'checked': skus[i].id == data.sku_id
                });
            }
            $skus.html(html);
            $skus.find('select.s-product-currency').val(data.currency);
            $skus.parent().show();
            $skus.closest('.s-sku-list').show();
            this.multiSkus($skus.find('tr:not(.js-sku-settings)').length);

            // render skus in profile page
            html = '';
            for (var i = 0, n = skus.length; i < n; i += 1) {
                html += tmpl('template-sku', {
                    'sku_id': skus[i].id,
                    'sku': skus[i],
                    'stocks': stocks,
                    'stock_ids': stock_ids,
                    'runout': data.runout || {}
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
                .find('[href*="#/product/new/"]').each(function () {
                    $(this).attr('href', $(this).attr('href').replace(pattern, replace));
                });
        },

        editTabDescriptionsBlur: function () {
            var $description = $('#s-product-description-content');
            if ($description.length && $description.data('redactor')) {
                $description.waEditor('sync');
            }
        },

        editTabDescriptionsAction: function () {
            var $description = $('#s-product-description-content');

            if ($description.length && !$description.data('redactor')) {
                var $container = $description.parents('div.s-product-form');
                $description.waEditor({
                    lang: wa_lang,
                    toolbarFixedTopOffset: $('#mainmenu').length ? $('#mainmenu').height() : 0,
                    uploadFields: $description.data('uploadFields'),
                    changeCallback: function () {
                        if ($description.length) {
                            $description.waEditor('sync');
                            $.product.helper.onChange($container);
                        }
                    }
                });
            }
        },

        profileInit: function () {

            if ($.product_list !== undefined && $.product_list.fixed_blocks !== undefined) {
                if ($.product_list.fixed_blocks.set) {
                    $.product_list.fixed_blocks.set.unsetFixed();
                }
                if ($.product_list.fixed_blocks.category) {
                    $.product_list.fixed_blocks.category.unsetFixed();
                }
            }

            if (this.options.edit_rights) {

                var $product_name = $('#shop-productprofile').find('.s-product-name');
                if (parseInt(this.path.id, 10)) {
                    $product_name.addClass('editable');
                } else {
                    $product_name.removeClass('editable');
                }

                var form = this.get('form');

                $product_name.inlineEditable({
                    minSize: {
                        width: 150
                    },
                    maxSize: {
                        width: 550
                    },
                    inputClass: 's-title-h1-edit',
                    beforeMakeEditable: function () {
                        $('#s-edit-product').hide();
                    },
                    afterBackReadable: function (input, data) {
                        $('#s-edit-product').show();
                        if (!data.changed) {
                            return false;
                        }
                        $.shop.jsonPost('?module=product&action=save&id=' + $.product.path.id, {
                            update: {
                                name: $(input).val()
                            }
                        }, function (r) {
                            var input_val = $(input).val();
                            document.title = input_val + $.product.options.title_suffix;
                            if (r.status === 'ok' && r.data) {
                                $('#s-product-meta-title').attr('placeholder', r.data.default_meta_title);
                            }
                            form.find('input[name="product[name]"]').val(input_val);
                        });
                    },
                    hold: function () {
                        return !$(this).hasClass('editable');
                    }
                });
            }
            var self = this;

            self.call('profileLazyInit', []);
        },
        profileLazyInit: function () {
            var $salesChart = $('#product-sales-plot');
            
            if (sales_data && sales_data.length) {
                if (!$salesChart.data('graph_rendered')) {

                    var renderChart = function() {
                        var is_rendered = ( $salesChart.length && $salesChart.width() > 0 );
                        if (is_rendered) {
                            //Render graph
                            showSalesGraph(sales_data, typeof cash_type == 'undefined' ? null : cash_type);
                            $salesChart.data('graph_rendered', 1);
                        } else {
                            setTimeout( renderChart, 1000 );
                        }
                    };
                    renderChart();
                }

            } else {
                $salesChart.remove();
            }

            if (sku_plot_data && sku_plot_data.length && sku_plot_data[0] && sku_plot_data[0].length) {

                if (!$('#product-sku-plot').data('graph_rendered')) {
                    var data_array = [];
                    $.each(sku_plot_data[0], function(i, el) {
                        data_array.push({
                            label: el[0],
                            value: el[1]
                        });
                    });
                    showPieGraph(data_array, {
                        color_type: "products"
                    });
                    $('#product-sku-plot').data('graph_rendered', 1);
                }

            } else {
                $('#product-sku-plot').remove();
            }
        },
        updateMetaFields: function() {
            var product_name = $('.s-product-name-input').val();
            $('#s-product-meta-title').attr('placeholder', product_name);
            var keywords = [
                product_name
            ];

            var max = 5;
            $('.s-sku-name-input').each(function() {
                if (max <= 0) {
                    return false;
                }
                var val = $.trim($(this).val());
                if (val) {
                    keywords.push(val);
                }
                max -= 1;
            });

            var category_name = this.getData('main', 'category_name');
            if (category_name !== null) {
                keywords.push(category_name);
            }

            var all_tags = $('#product-tags').val().split(',');
            var max = 5;
            for (var i = 0; i < all_tags.length; i += 1) {
                if (max <= 0) {
                    break;
                }
                var val = $.trim(all_tags[i]);
                if (val) {
                    keywords.push(val);
                }
                max -= 1;
            }
            $('#s-product-meta-keywords').attr('placeholder', keywords.join(', '));
            $('#s-product-meta-description').attr('placeholder', $('#s-product-summary').val());
        },

        getId: function() {
            return this.path.id;
        }

    };
})(jQuery);
