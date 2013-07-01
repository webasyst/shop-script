/**
 * {literal}
 *
 * @names features*
 * @property {} features_options
 * @method featuresInit
 * @method featuresAction
 * @method featuresBlur
 * @todo flush unavailable hash (edit/delete/etc)
 */
if (typeof($) != 'undefined') {

    $.extend($.settings = $.settings || {}, {
        features_options: {
            /**
             * template map by value type
             */
            'value_templates': {
                '': ''
            },
            'filter': null,
            /**
             * set true to enable edit canceling
             */
            'revert': false,
            'show_all_features': true,
            'show_all_types': true,
            'types_per_page':null

        },
        features_timer: {
            'loading': null,
            'filter': null,
            'type_filter': null
        },
        /**
         * @var {jQuery} $('#s-settings-features')
         */
        $features_list: null,
        /**
         * @var {jQuery} $('#s-settings-feature-types'
         */
        $features_types: null,
        /**
         * Init section
         */
        featuresInit: function () {
            $.shop.trace('$.settings.featuresInit');
            /* init settings */
            this.$features_types = $('#s-settings-feature-types');
            this.$features_list = $('#s-settings-features');
            var self = this;

            //this.featuresHelper.featureCountByType();
            $('#s-settings-content').on('click', 'a.js-action', function () {
                return self.click($(this));
            });
            $('#s-settings-features-type-dialog').on('click', 'a.js-action', function () {
                return self.featuresTypeIcon($(this));
            });

            setTimeout(function () {
                self.call('featuresLazyInit', []);
            }, 50);
        },
        featuresLazyInit: function () {
            var self = this;

            $.shop.trace('$.settings.featuresLazyInit');
            if (this.features_options.show_all_types) {
                this.featuresTypeInit();
            } else {
                $('#s-settings-feature-type-filter').on('keyup change', ':input', function () {
                    self.featuresTypeFilter($(this).parents('li'), this.value.toLowerCase());
                });
            }

            if (this.helpers) {
                this.helpers.compileTemplates('#s-settings-content');
            }
        },
        featuresTypeInit: function () {
            var self = this;
            this.$features_types.sortable({
                'distance': 5,
                'opacity': 0.75,
                'items': '> li:not(.not-sortable)',
                'axis': 'y',
                'containment': 'parent',
                'update': function (event, ui) {
                    var id = parseInt($(ui.item).data('type'));
                    var after_id = $(ui.item).prev().data('type');
                    if (after_id === undefined) {
                        after_id = 0;
                    } else {
                        after_id = parseInt(after_id);
                    }
                    self.featuresTypeSort(id, after_id, $(this));
                }
            });
        },

        featuresTypeFilter: function ($input, val) {
            var self = this;
            $input.find('.icon16').removeClass('search').addClass('loading');
            clearTimeout(this.features_timer.type_filter);
            self.features_timer.type_filter = setTimeout(function () {
                self.featuresTypeFilterApply(val);
                $input.find('.icon16').removeClass('loading').addClass('search');
            }, 500);
        },

        featuresTypeFilterApply: function (val) {
            val = val.toLowerCase();
            $.shop.trace('$.settings.featuresTypeFilterApply', [val,this.features_options.filter]);
            if (val != this.features_options.filter) {
                this.features_options.filter = val;
                if (val) {
                    this.$features_types.find('>li.not-sortable.js-type-show-all:visible').hide();
                    var reg = new RegExp(val, 'i');
                    var type = this.featuresHelper.type();
                    this.$features_types.find('>li.js-type-item').each(function (index, el) {
                        var $this = $(el);
                        if (type && ($this.data('type') == type)) {
                            $this.show();
                        } else {
                            if (reg.test($this.data('name'))) {
                                $this.show();
                            } else {
                                $this.hide();
                            }
                        }
                    });
                } else {
                    if (this.features_options.show_all_types) {
                        this.$features_types.find('>li.js-type-item:hidden').show();
                    } else {
                        this.$features_types.find('>li.js-type-item:visible').hide();
                        var counter = this.features_options.types_per_page;
                        this.$features_types.find('>li.js-type-item:hidden').each(function () {
                            $(this).show();
                            $.shop.trace('$.settings.featuresTypeFilterApply cnt',counter);
                            return (--counter > 0);
                        });
                        this.featuresTypeShow(false);
                    }
                }
            }
        },

        featuresInitList: function () {
            this.$features_list = $('#s-settings-features');
            var self = this;
            this.$features_list.sortable({
                'distance': 5,
                'opacity': 0.75,
                'items': '> tbody:first > tr:visible',
                'handle': '.sort, .js-feature-name',
                'cursor': 'move',
                'tolerance': 'pointer',
                'update': function (event, ui) {
                    if (self.featuresHelper.type()) {
                        var $feature = $(ui.item);
                        var $after = $feature.prev(':visible');
                        self.featuresFeatureSort($feature, $after, $(this));
                    } else {
                        $(this).sortable('cancel');
                    }
                },
                'start': function () {
                    $('.block.drop-target').addClass('drag-active');
                },
                'stop': function () {
                    $('.block.drop-target').removeClass('drag-active');
                }
            }).find(':not:input').disableSelection();

            this.featuresInitDroppable(this.$features_types.find('li:not(.not-sortable)'));
            $('#s-settings-features-type-dialog').prependTo('#wa-app');

            this.$features_list.on('change, click', ':input:checkbox[name$="\]\[types\]\[0\]"][name^="feature\["]', function () {
                self.featuresFeatureTypesChange($(this));
            });

            this.$features_list.on('keypress', ':input[name$="\]\[name\]"][name^="feature\["]', function (e) {

                try {
                    if (e.which && e.which == 13) {
                        var feauture_id = $(this).parents('tr').data('feature');
                        self.featuresFeatureSave(feauture_id);
                    }
                } catch (e) {
                    $.shop.error(e);
                }
            });

            this.$features_list.on('change', ':input.js-feature-types-control:first', function () {
                self.featuresFeatureValueTypeChange($(this));
            });

            this.$features_list.on('change', ':input.js-feature-subtypes-control:first', function () {
                self.featuresFeatureValueTypeChainChange($(this));
            });

            this.$features_list.find('.color').hover(function () {
                $(this).css('cursor', 'pointer');
            }, function () {
                $(this).css('cursor', 'default');
            });
        },

        /**
         * @param {jQuery} $feature
         * @param {Object} feature
         */
        featuresFeatureChange: function ($feature, feature) {
            var $list = $feature.find('ul.js-feature-values:first');
            var $container = $list.parent();
            if (feature.selectable) {
                $container.show();
                feature.values_template = feature.values_template || (this.features_options.value_templates[feature.type] || '');
                $feature.find('ul.js-feature-values:first').sortable({
                    'distance': 5,
                    'opacity': 0.75,
                    'items': '> li[data-value-id]',
                    'handle': '.sort',
                    'cursor': 'move',
                    'tolerance': 'pointer'
                });

                if (feature.values_template != $list.data('values_template')) {
                    $.shop.trace('$.settings.featuresFeatureChange template changed', [feature.values_template, $list.data('values_template')]);
                    $list.find(':input[name^="feature\[' + feature.id + '\]\[values\]"]').each(function () {
                        $(this).parents('li').remove();
                    });
                    $list.data('values_template', feature.values_template);
                    this.featuresFeatureValueAdd(feature.id);
                }
            } else {
                $feature.find('ul.js-feature-values:first').sortable("destroy");
                $container.hide();
            }

            $.shop.trace('$.settings.featuresFeatureChange', [feature, $feature]);
        },

        /**
         * @param {jQuery} $el
         */
        featuresFeatureTypesChange: function ($el) {
            var checked = $el.attr('checked') || false;
            $.shop.trace('all type changed', [checked, $el]);
            var $container = $el.parents('ul');
            if (checked) {
                $container.find('> li:gt(0)').hide();
            } else {
                $container.find('> li').show();
            }
            var self = this;
            setTimeout(function () {
                if (checked) {
                    $container.find('> li[data-type!="0"] :checkbox').each(function () {
                        this.checked = checked;
                    });
                } else {
                    var type = self.featuresHelper.type();
                    $container.find('> li[data-type!="0"] :checkbox').each(function (index, /* Element */ el) {
                        this.checked = el.defaultChecked || (type == this.valueOf());
                    });
                }
            }, 10);
        },

        features_data: {
            'feature_id': 0,
            'value_id': 0
        },

        /**
         * Disable section event handlers
         */
        featuresBlur: function () {
            $('#s-settings-features-type-dialog').off('click', 'a.js-action').remove();
            $('#s-settings-content').off('click', 'a.js-action');
            this.container.off('change, click');
        },

        /**
         *
         * @param {String} tail
         */
        featuresAction: function (tail) {
            $.shop.trace('$.settings.featuresAction', [this.path, tail]);
            var type = parseInt(tail) || 0;
            if (type || this.features_options.show_all_features) {
                $('div.s-settings-form:first > div:hidden:not(.js-loading)').show();
            }
            this.featuresTypeSelect(type);
        },

        /**
         * @param  $el {jQuery}
         */
        featuresInitDroppable: function ($el) {
            var self = this;
            $el.droppable({
                'accept-deleted': function () {
                    return $(this).is('li:not(.not-sortable)');
                },
                'activeClass-deleted': "ui-state-hover",
                'hoverClass': 'drag-newparent',
                'tolerance': 'pointer',
                'drop': function (event, ui) {
                    return self.featuresFeatureTypeChange(event, ui, this);
                }
            });
        },

        /**
         * Select feature types and filter data
         *
         * @param {Number} type
         */
        featuresTypeSelect: function (type, lazy) {
            /* change selected type and filter features rows */
            $.shop.trace('$.settings.featuresTypeSelect', type);
            this.$features_types.find('> li.selected:first').removeClass('selected');
            if (!type && !this.features_options.show_all_features) {
                $('div.s-settings-form:first > div:visible:not(.clear)').hide();
            } else {

                if (this.$features_types.find('> li[data-type="' + type + '"]:first').length) {
                    var name = this.$features_types.find('> li[data-type="' + type + '"]:first').addClass('selected').find('span.js-type-name').text();
                    $('#s-settings-features-type-name').text(name.replace(/(^[\r\n\s]+|[\r\n\s]+$)/mg, ''));
                    if (lazy) {
                        this.featuresFilter(type);
                    } else {
                        this.$features_list.hide();
                        $('div.s-settings-form:first > div.js-loading:first').show();
                        var self = this;
                        //TODO show loading
                        $.get('?module=settings&action=featuresFeatureList',
                            {'type': type},function (data) {
                                $.shop.trace('$.settings.featuresTypeSelect ajax');
                                self.$features_list.find('> tbody:first').html(data);
                            }, 'html').complete(function () {
                                self.$features_list.show();
                                $('div.s-settings-form:first > div:hidden:not(.js-loading)').show();
                                $('div.s-settings-form:first > div.js-loading:first').hide();
                                $('html, body').animate({
                                    scrollTop: 0
                                }, 200);
                                setTimeout(function () {
                                    self.call('featuresInitList', []);
                                }, 50);
                                self.featuresHelper.featureCountByType(type);
                            }).error(function () {

                            });
                    }
                } else {
                    window.location.hash = '#/features/';
                }
            }
        },

        featuresFeatureTypeChangeAllowed: function (current, target) {
            if (target != 'null') {
                return (current.indexOf(target) < 0);
            } else {
                return (current.indexOf(target) < 0) && (current.indexOf('' + this.path.tail) >= 0 || current.length == 1);
            }
        },

        /**
         *
         * @param {Event} event
         * @param ui
         * @param {Element} el
         */
        featuresFeatureTypeChange: function (event, ui, el) {
            var $feature = ui.draggable;
            var target = parseInt($(el).data('type')) || 0;
            var current = this.featuresHelper.featureTypes($feature);
            $.shop.trace('$.settings.featuresFeatureTypeChange', [target, current, current.indexOf(target)]);
            /**
             * @todo remove from type
             * @todo move to all types
             */

            var self = this;
            if (current.indexOf(target) < 0) {

                $.post('?module=settings&action=featuresFeatureType', {
                    'feature': $feature.data('feature'),
                    'type': target
                },function (data) {
                    $.shop.trace('$.settings.featuresFeatureTypeChange ajax', data);

                    var index = current.indexOf(NaN);
                    if (index >= 0) {
                        current.splice(index, 1);
                    }
                    if (target) {
                        index = current.indexOf(0);
                        if (index >= 0) {
                            current.splice(index, 1);
                        }
                        current.push(target);
                    } else {
                        current = [target];
                    }
                }, 'json').complete(function () {
                        $feature.data('types', '' + current.join(' '));
                        $.shop.trace('$.settings.featuresFeatureTypeChange tmpl', current);
                        self.featuresHelper.featureCountByType();

                        if (current.length == 1) {
                            self.featuresFilter(self.featuresHelper.type(), true);
                        }
                    }).error(function () {

                    });
            }
        },

        /**
         * Filter visible features list by type
         *
         * @param {Number} type
         * @param {boolean=} animate use animation
         */
        featuresFilter: function (type, animate) {
            $.shop.trace('$.settings.featuresFilter', [type, animate]);
            this.featuresFilterStop();
            var self = this;
            this.features_timer.loading = setTimeout(function () {
                self.features_timer.loading = null;
                $.shop.trace('show', 409);
                $('div.s-settings-form:first > div.js-loading:first').show();
            }, 100);

            if (type) {
                $('#s-settings-features-type-menu:hidden').show();
            } else {
                $('#s-settings-features-type-menu:visible').hide()
            }

            if ((type = parseInt(type) || 0) || !this.features_options.show_all_features) {
                this.$features_list.find('> tbody:first > tr:visible').hide();
            }

            setTimeout(function () {
                self.featuresFilterApply(type);
            }, 10);
        },

        /**
         * Recursive apply filter conditions
         * @private
         * @param {Number} type
         */
        featuresFilterApply: function (type) {
            var counter = 50;
            var selector;
            if (type) {
                selector = '> tbody:first > tr[data-types~=' + type + ']:hidden';
            } else {
                selector = '> tbody:first > tr:hidden';
            }

            $.shop.trace('$.settings.featuresFilterApply', [type, selector]);
            this.$features_list.find(selector).each(function () {
                var $this = $(this);
                $this.show();
                if (type) {
                    $this.find('.sort:hidden').show();
                } else {
                    $this.find('.sort:visible').hide();
                }
                return !!(counter--);
            });
            if (counter >= 0) {
                this.featuresSort(type);
                this.featuresFilterStop(true);
                $.shop.trace('$.settings.featuresFilter stop', [counter, selector]);
            } else {
                var self = this;
                this.features_timer.filter = setTimeout(function () {
                    self.featuresFilterApply(type);
                }, 50);
            }
        },

        /**
         *
         * @param {boolean=} scroll
         */
        featuresFilterStop: function (scroll) {
            if (this.features_timer.filter) {
                clearTimeout(this.features_timer.filter);
                this.features_timer.filter = null;
            }
            if (this.features_timer.loading) {
                clearTimeout(this.features_timer.loading);
                this.features_timer.loading = null;
            }
            $('div.s-settings-form:first > div.js-loading:first').hide();
            if (scroll) {
                $('html, body').animate({
                    scrollTop: 0
                }, 200);
            }
        },


        /**
         *
         * @param {Number} type
         */
        featuresSort: function (type) {
            if (type || !this.features_options.show_all_features) {
                type = parseInt(type) || 0;
                /**
                 * @todo test speed
                 */
                this.$features_list.find('> tbody:first').append(this.$features_list.find('> tbody:first > tr:visible').get().sort(function (a, b) {
                    if (type) {
                        a = $(a).data('sort') || {};
                        a = parseInt(a[type]) || 0;

                        b = $(b).data('sort') || {};
                        b = parseInt(b[type]) || 0;
                    } else {
                        a = parseInt($(a).data('feature')) || 0;
                        b = parseInt($(b).data('feature')) || 0;
                    }
                    return parseInt(a - b);
                }));

            } else {
                this.$features_list.find('> tbody:first > tr:hidden').show();
                $('#s-settings-features-type-menu:visible').hide();

                this.$features_list.find('> tbody:first').append(this.$features_list.find('> tbody:first > tr:visible').get().sort(function (a, b) {
                    a = -parseInt($(a).data('feature')) || -1;
                    b = -parseInt($(b).data('feature')) || -1;
                    var sign = ((a * b > 0) && (a > 0)) ? -1 : a * b;
                    return parseInt(a - b) * sign;
                }));
            }
            $.shop.trace('$.settings.featuresSort stop', [type]);
        },

        /**
         * @param {jQuery} $el
         */
        featuresTypeIcon: function ($el) {
            var $item = $el.parents('li');
            var $container = $el.parents('ul');
            $container.find('li.selected').removeClass('selected');
            var icon = $item.addClass('selected').data('icon');
            var $form = $container.parents('form');

            $form.find(':input[name="icon"]').val(icon);
            $form.find(':input[name="icon_url"]').val('');
            $.shop.trace('$.settings.featuresTypeIcon', [icon, $el]);
            return false;
        },

        /**
         *
         * @param {jQuery=} silent
         */
        featuresTypeShow: function ($el) {

            if ($el && ($el !== false)) {
                this.$features_types.find('>li.not-sortable.js-type-show-all:visible').html('<i class="icon16 loading"></i>');
                var self = this;
                this.features_options.show_all_types = true;
                setTimeout(function () {
                    self.$features_types.find('>li.js-type-item:hidden').show();
                    self.$features_types.find('>li.not-sortable.js-type-show-all:visible').hide();
                    self.featuresTypeInit();
                }, 50);
            } else {
                if ($el === false) {
                    this.$features_types.find('>li.not-sortable.js-type-show-all:hidden').show();
                } else {
                    this.$features_types.find('>li.not-sortable.js-type-show-all:visible').hide();
                }
            }
        },

        featuresTypeAdd: function () {
            var self = this;
            $('#s-settings-features-type-dialog').waDialog({
                onLoad: function () {
                    var $this = $(this);
                    var form = $this.find('form');

                    $(this).find('.js-add-type').show();
                    $(this).find('.js-edit-type').hide();

                    $this.find('ul li.selected').removeClass('selected');
                    var icon = $this.find('ul li:first').addClass('selected').data('icon');

                    $this.find(':input[name="icon"]').val(icon);
                    $this.find(':input[name="icon_url"]').val('');
                    $this.find(':input[name="id"]').val(null);
                    $this.find(':input[name="name"]').val('').focus();

                },
                onSubmit: function (d) {
                    var form = d.find('form');
                    $.post(form.attr('action'), form.serialize(), function (response) {
                        try {
                            if (response && (response.status == 'ok')) {
                                $.shop.trace('response', [response, response.data]);
                                var type = response.data;
                                $.tmpl('feature-type', type).insertBefore(self.$features_types.find('> li:last'));
                                var $type = self.$features_types.find('>  li[data-type="' + type.id + '"]');
                                self.featuresInitDroppable($type);
                                d.trigger('close');
                                window.location.hash = '#/features/' + type.id + '/';
                            } else {
                                // display error;
                            }
                        } catch (e) {
                            $.shop.error(e);
                            d.trigger('close');
                        }
                    }, 'json');
                    return false;
                }
            });
        },

        featuresTypeEdit: function () {
            var type = this.featuresHelper.type();
            $.shop.trace('$.settings.featuresTypeEdit', type);
            if (type) {
                var self = this;
                var $type = this.$features_types.find('> li[data-type="' + type + '"]');
                $('#s-settings-features-type-dialog').waDialog({
                    onLoad: function () {
                        var $this = $(this);
                        $.shop.trace('$.settings.featuresTypeEdit', this);
                        var name = $type.find('.js-type-name').text().replace(/(^[\r\n\s]+|[\r\n\s]+$)/mg, '');
                        var icon = $type.data('icon').replace(/^icon\.([\w\d\-_]+)$/, '$1');

                        $this.find('ul li.selected').removeClass('selected');
                        if ($this.find('ul li[data-icon="' + icon + '"]').length) {
                            $this.find('ul li[data-icon="' + icon + '"]').addClass('selected');
                            $this.find(':input[name="icon"]').val(icon);
                            $this.find(':input[name="icon_url"]').val('');
                        } else {
                            $this.find(':input[name="icon"]').val('');
                            $this.find(':input[name="icon_url"]').val(icon);
                        }
                        $this.find('.js-add-type').hide();
                        $this.find('.js-edit-type').show().find('.js-type-name').text(name);
                        $this.find(':input[name="id"]').val(type);
                        $this.find(':input[name="name"]').val(name).focus();

                    },
                    onSubmit: function (d) {
                        var $form = d.find('form');
                        $.post($form.attr('action'), $form.serialize(), function (response) {
                            try {
                                if (response && (response.status == 'ok')) {
                                    d.trigger('close');
                                    $.shop.trace('response', [response, response.data]);
                                    $type.replaceWith($.tmpl('feature-type', response.data));

                                    self.featuresTypeSelect(type, true);
                                    self.$features_list.find('span[data-type="' + response.data.id + '"]').each(function () {
                                        $(this).text(response.data.name);
                                    });
                                    $type = self.$features_types.find('> li[data-type="' + type + '"]');

                                    self.featuresInitDroppable($type);

                                    self.$features_list.find('tr.js-inline-edit ul li[data-type="' + type + '"]').each(function () {
                                        response.data['feature'] = {
                                            'id': $(this).parents('tr').data('feature'),
                                            'types': []
                                        };
                                        if ($(this).find(':checkbox:checked').length) {
                                            response.data.feature.types.push(type);
                                        }
                                        $.shop.trace('$.settings.featuresTypeEdit rename', response.data);
                                        $(this).html($.tmpl('edit-feature-type', response.data));
                                    });
                                } else {
                                    // display error;
                                }
                            } catch (e) {
                                $.shop.error(e);
                            }
                        }, 'json');
                        return false;
                    }
                });
            }
        },

        /**
         * Delete feature type
         *
         * @param {Number} type Type's ID
         * @return {Boolean}
         */
        featuresTypeDelete: function (type) {
            type = this.featuresHelper.type(type);
            $.shop.trace('$.settings.featuresTypeDelete', [type, this.path]);
            var self = this;
            if (type && confirm) {
                $.post('?module=settings&action=featuresTypeDelete', {
                    'id': type
                }, function (response) {
                    try {
                        if (response && (response.status == 'ok')) {
                            $.shop.trace('response', [response, response.data, type]);
                            self.$features_types.find('> li[data-type="' + type + '"]').remove();
                            self.$features_list.find('span[data-type="' + type + '"]').remove();
                            self.$features_list.find('> tbody > tr').filter(function () {
                                return self.featuresHelper.featureTypes($(this)).indexOf(type) > 0;
                            }).each(function () {
                                    var pattern = new RegExp('\b' + type + '\b\s+');
                                    $(this).data('types', $(this).data('types').replace(pattern, ''));
                                });

                            self.$features_list.find('li[data-type="' + type + '"]').remove();

                            window.location.hash = '#/features/';
                        } else {
                            // display error;
                        }
                    } catch (e) {
                        $.shop.error(e);
                    }
                }, 'json');
            }
            return false;
        },

        /**
         *
         * @param {Number} id
         * @param {Number} after_id
         * @param {jQuery} $list
         */
        featuresTypeSort: function (id, after_id, $list) {
            $.post('?module=settings&action=featuresTypeSort', {
                id: id,
                after_id: after_id
            }, function (response) {
                $.shop.trace('$.settings.featuresTypeSort result', response);
                if (response.error) {
                    $.shop.error('Error occurred while sorting product types', 'error');
                    $list.sortable('cancel');
                }
            }, function (response) {
                $.shop.trace('$.settings.featuresTypeSort cancel', {
                    'data': response
                });
                $list.sortable('cancel');
                $.shop.error('Error occurred while sorting product types', 'error');
            });
        },

        featuresFeatureAdd: function () {
            try {
                $.shop.trace('featuresFeatureAdd env', this);
                var feature = {
                    'id': --this.features_data.feature_id,
                    'type': 'varchar',
                    'types': [],
                    'selectable': 0,
                    'multiple': 0,
                    'values': [],
                    'values_template': this.features_options.value_templates['varchar'] || ''
                };
                var type = this.featuresHelper.type();
                if (type) {
                    feature.types.push(type);
                } else {
                    feature.types.push(0);
                    var selector = '> li:not(.not-sortable)';
                    if (type) {
                        selector += '[data-type="' + type + '"]';
                    }
                    this.$features_types.find(selector).each(function () {
                        var id = $(this).data('type');
                        if (id && id != undefined) {
                            feature.types.push(id);
                        }
                    });
                }
                feature.values.push({
                    'id': --this.features_data.value_id,
                    'value': ''
                });

                $.shop.trace('$.settings.featuresFeatureAdd', feature);
                var self = this;
                $.when($.tmpl('edit-feature', {
                        'types': this.featuresHelper.types(),
                        'feature': feature
                    }).prependTo(self.$features_list.find('> tbody:first'))).done(function () {
                        var $feature = self.$features_list.find('> tbody:first > tr[data-feature="' + self.features_data.feature_id + '"]:first');
                        self.featuresFeatureChange($feature, feature);
                        if (!self.featuresHelper.type()) {
                            $feature.find(' > td > .sort').hide()
                        }

                        var $el = $feature.find(':input:checkbox[name$="\]\[types\]\[0\]"][name^="feature\["]');
                        self.featuresFeatureTypesChange($el);
                        $feature.find(':input[name$="\[name\]"]').focus();
                    });
            } catch (e) {
                $.shop.error('exception', e);
            }
        },

        /**
         *
         * @param {Number} feature_id
         */
        featuresFeatureEdit: function (feature_id) {
            feature_id = parseInt(feature_id);
            var self = this;
            var $feature = this.$features_list.find('> tbody:first > tr[data-feature="' + feature_id + '"]:first');
            var $more = $feature.find('ul.js-feature-values:first li.js-more-link:first');
            if ($more.length) {
                var $actions = $feature.find('a[href^="\#/features/feature/delete/"]:first').parents('ul');
                $actions.after('<i class="icon16 loading"></i>');
                $actions.hide();
                return this.featuresFeatureValuesShow(feature_id, function (feature_id) {
                    self.featuresFeatureEdit(feature_id);
                });
            }
            var type = $feature.data('type');
            var feature = {
                'id': feature_id,
                'name': $feature.find('.js-feature-name').text(),
                'code': $feature.find('.js-feature-code').text(),
                'type': type,
                'type_name': $feature.find('.js-feature-type-name').text(),
                'types': this.featuresHelper.featureTypes($feature),
                'selectable': $feature.data('selectable'),
                'multiple': $feature.data('multiple'),
                'values': [],
                'values_template': this.features_options.value_templates[type] || ''
            };

            $feature.find('ul.js-feature-values li').each(function () {
                var id = parseInt($(this).data('value-id'));
                if (id) {
                    feature.values.push({
                        'id': id,
                        'value': $(this).text().replace(/(^[\r\n\s]+|[\r\n\s]+$)/mg, '')
                    });
                }
            });
            $.shop.trace('$.settings.featuresFeatureEdit', [$feature.find('ul.js-feature-values li').length, feature.values.length]);
            $.shop.trace('$.settings.featuresFeatureEdit', feature);
            try {
                var data = {
                    'types': this.featuresHelper.types(),
                    'feature': feature
                };
                $.when($feature.replaceWith($.tmpl('edit-feature', data))).done(function () {
                    var $edit_feature = self.$features_list.find('> tbody:first > tr[data-feature="' + feature_id + '"]:first');
                    if (self.features_options.revert) {
                        $edit_feature.data('cancel', $feature);
                    }
                    if (!self.featuresHelper.type()) {
                        $edit_feature.find(' > td > .sort').hide()
                    }
                    self.featuresFeatureChange($edit_feature, feature);
                    self.featuresFeatureTypesChange($(':checkbox[name="feature\[' + feature_id + '\]\[types\]\[0\]"]'));
                });
            } catch (e) {
                $.shop.error('exception', e);
            }
        },

        /**
         *
         * @param {Number} feature_id
         * @param {jQuery} $el
         */
        featuresFeatureCodeEdit: function (feature_id, $el) {
            var $container = $el.parents('td');
            $container.find('span.js-feature-code:first').hide();
            $el.hide();
            $container.find(':input[name$="\[code\]"]').show().focus();
        },

        /**
         *
         * @param {jQuery} $el
         * @returns {boolean}
         */
        featuresFeatureValueTypeChange: function ($el) {
            var $selected = $el.find('option:selected');
            var $feature = $el.parents('td');
            var feature = {
                'id': parseInt($feature.parents('tr').data('feature')),
                'type': $selected.data('type'),
                'selectable': $selected.data('selectable'),
                'multiple': $selected.data('multiple')
            };
            // update hidden input value
            $feature.find(':input[name$="\[type\]"]').val(feature.type);
            $feature.find(':input[name$="\[selectable\]"]').val(feature.selectable);
            $feature.find(':input[name$="\[multiple\]"]').val(feature.multiple);

            this.featuresFeatureChange($feature, feature);

            var $select = $feature.find(':input.js-feature-subtypes-control:first');

            if (feature.type.match(/\*$/)) {
                $select.show().trigger('change').focus();
            } else {
                $select.hide();
            }
            // retrieve input value controls
            $.shop.trace('$.settings.featuresFeatureValueTypeChange', feature);
            return false;
        },

        /**
         *
         * @param {jQuery} $el
         */
        featuresFeatureValueTypeChainChange: function ($el) {

            var $feature = $el.parents('td');
            var feature = {
                'id': parseInt($feature.parents('tr').data('feature')),
                'type': $feature.find(':input[name$="\[type\]"]').val(),
                'selectable': $feature.find(':input[name$="\[selectable\]"]').val(),
                'multiple': $feature.find(':input[name$="\[multiple\]"]').val()
            };

            var $selected = $el.find('option:selected');
            if (!$selected.length) {
                $selected = $el.find('option:first');
            }
            feature.type = $selected.data('type');
            $selected.select();
            // update hidden input value
            $feature.find(':input[name$="\[type\]"]').val(feature.type);
            $.shop.trace('$.settings.featuresFeatureValueTypeChainChange', feature);
            this.featuresFeatureChange($feature, feature);
        },

        featuresFeatureSave: function (feature_id) {
            var self = this;
            var $feature = this.$features_list.find('> tbody:first > tr[data-feature="' + feature_id + '"].js-inline-edit');
            var feature_raw = $feature.find(':input').serialize();
            $.shop.trace('$.settings.featuresFeatureSave', feature_raw);
            $.post('?module=settings&action=featuresFeatureSave', feature_raw,function (data) {
                if (data.status == 'ok') {
                    var feature = data.data[feature_id];
                    feature.values_template = feature.values_template || (self.features_options.value_templates[feature.type] || '');
                    var error = false;

                    for (var v = 0; v < feature.values.length; v++) {
                        if (feature.values[v].error) {
                            error = true;
                            break;
                        }
                    }

                    $.shop.trace('response', feature.values);
                    $feature.replaceWith($.tmpl(error ? 'edit-feature' : 'feature', {
                        'types': self.featuresHelper.types(!error),
                        'feature': feature
                    }));
                    if (error) {
                        $feature = self.$features_list.find('> tbody:first > tr[data-feature="' + feature_id + '"]:first');
                        $feature.on('click focus', 'input', function () {
                            var $this = $(this);
                            var $parent = $(this).parents('ul');
                            $parent.find('input.red').removeClass('red');
                            if ($this.hasClass('error')) {
                                var original = parseInt($(this).data('original-id')) || 0;
                                if (original) {
                                    $parent.find('input[name$="\[' + original + '\]"]').addClass('red');
                                }
                            }

                        });
                    }
                    var type = self.featuresHelper.type();
                    if ((type && !feature.types.length) || (feature.types.length && feature.types.indexOf(type) < 0)) {
                        self.featuresFilter(type, true);
                    } else {
                        if (type) {
                            self.$features_list.find('> tbody:first > tr > td > .sort').show();
                        } else {
                            self.$features_list.find('> tbody:first > tr > td > .sort').hide()
                        }
                    }

                }
            }, 'json').complete(function () {
                    self.featuresHelper.featureCountByType();
                });
            return false;
        },

        featuresFeatureSort: function ($feature, $after, $features) {
            var id = parseInt($feature.data('feature'));
            var after_id = $after.data('feature');
            if (after_id === undefined) {
                after_id = 0;
            } else {
                after_id = parseInt(after_id);
            }

            var type = this.featuresHelper.type();
            $.shop.trace('$.settings.featuresFeatureSort', [id, after_id, type, $features]);
            $.post('?module=settings&action=featuresFeatureSort', {
                feature_id: id,
                after_id: after_id,
                type_id: type
            },function (data, textStatus) {
                if (data.status == 'ok') {
                    // update internal sort data
                    // use code like SQL
                    var new_sort = parseInt(($after.data('sort') || {})[type]) || 0;
                    if (new_sort) {
                        ++new_sort;
                    }
                    var current_sort = ($feature.data('sort') || {});
                    current_sort[type] = current_sort[type] || 0;
                    $.shop.trace('sort ' + id + '->' + after_id, [current_sort[type], new_sort]);

                    $features.find('tr:visible').each(function (index, el) {
                        var each_id = parseInt($(el).data('feature')) || 0;
                        if (each_id) {
                            var sort = $(el).data('sort') || {};
                            sort[type] = parseInt(sort[type]) || 0;
                            var changed = false;
                            if (new_sort > current_sort[type]) {
                                if ((new_sort >= sort[type]) && (sort[type] > current_sort[type])) {
                                    changed = [sort[type]];
                                    changed.push(--sort[type]);
                                }
                            } else if (new_sort < current_sort[type]) {
                                if ((new_sort <= sort[type]) && (sort[type] < current_sort[type])) {
                                    changed = [sort[type]];
                                    changed.push(++sort[type]);
                                }
                            }
                            if (changed) {
                                $.shop.trace('sort ' + each_id, changed);
                                $(el).data('sort', sort);
                            }
                        }
                    });

                    $.shop.trace('sort ' + $feature.data('feature'), [current_sort[type], new_sort]);
                    current_sort[type] = new_sort;
                    $feature.data('sort', current_sort);
                } else {
                    $.shop.trace('FeatureSort result: ' + textStatus, data);
                    $.shop.error('Error occurred while sorting features', data.errors || data);
                    $features.sortable('cancel');
                }
            }, 'json').error(function (jqXHR, errorText) {
                    $features.sortable('cancel');
                    $.shop.error('featuresFeatureSort ajaxError' + errorText, jqXHR);
                });
        },

        featuresFeatureDelete: function (feature_id) {
            var self = this;
            var $feature = this.$features_list.find('> tbody:first > tr[data-feature="' + feature_id + '"]:first');

            if (feature_id > 0) {
                var $actions = $feature.find('a[href^="\#/features/feature/delete/"]:first').parents('ul');
                $actions.after('<i class="icon16 loading"></i>');
                $actions.hide();
                $.post('?module=settings&action=featuresFeatureDelete', {
                    'feature_id': feature_id
                }, function (data) {
                    if (data.status == 'ok') {
                        $feature.hide('slow', function () {
                            $(this).remove();
                            self.featuresHelper.featureCountByType();
                        });

                    } else {
                        $actions.show();
                    }
                }, 'json');
            } else {
                $feature.hide('slow', function () {
                    $(this).remove();
                    self.featuresHelper.featureCountByType();
                });
            }
        },

        /**
         * @param feature_id
         */
        featuresFeatureValueAdd: function (feature_id) {
            $.shop.trace('featuresFeatureValueAdd env', this);
            var $feature = this.$features_list.find('> tbody:first > tr[data-feature="' + feature_id + '"].js-inline-edit:first');
            if (!$feature.length) {
                this.featuresFeatureEdit(feature_id);
                $feature = this.$features_list.find('> tbody:first > tr[data-feature="' + feature_id + '"].js-inline-edit:first');
            }
            var type = $feature.find(':input[name$="\[type\]"]').val();
            var template = (this.features_options.value_templates[type] || '');
            $.tmpl('edit-feature-value' + template, {
                'feature': {
                    'id': feature_id,
                    'value_template': template
                },
                'id': --this.features_data.value_id,
                'feature_value': ''
            }).insertBefore($feature.find('ul.js-feature-values li:last'));
            $feature.find('ul.js-feature-values:first').sortable('refresh').find(':input:last').focus();
        },

        featuresFeatureValuesShow: function (feature_id, callback) {
            var $container = this.$features_list.find('> tbody:first > tr[data-feature="' + feature_id + '"] ul.js-feature-values');
            if (!callback || (typeof(callback) != 'function')) {
                $container.find('li.js-more-link').html('<i class="icon16 loading"></i>');
            }
            $.get('?module=settings&action=featuresFeatureValues',
                {'id': feature_id},function (data) {
                    $.shop.trace('$.settings.featuresFeatureValuesShow ajax');
                    $container.html(data);
                }, 'html').complete(function () {
                    if (typeof(callback) == 'function') {
                        try {
                            callback(feature_id);
                        } catch (e) {
                            $.shop.error(e.message, e);
                        }
                    }
                });
        },

        featuresFeatureValueDelete: function (feature_id, value_id) {
            var $feature = this.$features_list.find('> tbody:first > tr[data-feature="' + feature_id + '"].js-inline-edit:first');
            var $input = $feature.find(':input[name^="feature[' + feature_id + '\]\[values\]\[' + value_id + '\]"]');
            var $values = $input.parents('li');
            $values.hide('normal', function () {
                $values.remove();
            });
        },

        featuresFeatureRevert: function (feature_id) {
            if (this.features_options.revert) {
                var $feature = this.$features_list.find('> tbody:first > tr[data-feature="' + feature_id + '"].js-inline-edit');
                if (feature_id > 0) {
                    $.shop.trace('$.settings.featuresFeatureRevert', $feature.data('cancel'));
                    $feature.replaceWith($feature.data('cancel'));
                } else {
                    $feature.remove();
                }
            }
        },

        featuresHelper: {
            parent: $.settings,
            /**
             * Get current selected features type
             *
             * @return int
             */
            type: function (type) {
                return 0 + parseInt(type || $.settings.path.tail) || 0;
            },
            /**
             * Get list available types
             * @param {boolean=} name_only
             * @param {Number=} type
             * @return {Object}
             */
            types: function (name_only, type) {
                var types = {};
                var selector = '> li:not(.not-sortable)';
                if (type) {
                    selector += '[data-type="' + type + '"]';
                }
                this.parent.$features_types.find(selector).each(function () {
                    var id = $(this).data('type');
                    if (id && id != undefined) {
                        if (name_only) {
                            types[id] = $(this).find('a').find('span.js-type-name').text();
                        } else {
                            types[id] = $(this).find('a').html().replace(/<span\s+class="count">\d*<\/span>/, '');
                        }
                        types[id] = types[id].replace(/(^[\r\n\s]+|[\r\n\s]+$)/mg, '');
                    }
                });
                return types;
            },
            /**
             * Get feature types
             *
             * @param {jQuery} $feature
             * @return [int,...] array of feature types
             */
            featureTypes: function ($feature) {
                //return $feature.data('types')||[];
                var types = $feature.data('types');
                if (!types) {
                    return [];
                }
                return ('' + types).split(' ').map(function (a, b) {
                    return parseInt(a);
                });
            },
            /**
             *
             * @param {Number=} type
             */
            featureCountByType: function (type) {

                type = type || this.type();
                $.shop.trace('featureCountByType', [type]);
                var helper = this;
                var counter = {
                    0: 0
                };
                var selector = '> tbody:first > tr';
                if (type) {
                    selector += ':visible';
                }
                this.parent.$features_list.find(selector).each(function () {
                    var types = helper.featureTypes($(this));
                    if (!types.length) {
                        types.push(0);
                    }
                    for (var i = 0; i < types.length; i++) {
                        if (typeof(counter[types[i]]) == 'undefined') {
                            counter[types[i]] = 0;
                        }
                        if (types[i] || !helper.parent.features_options.show_all_features) {
                            ++counter[types[i]];
                        }
                    }
                    if (helper.parent.features_options.show_all_features) {
                        ++counter[0];
                    }
                });

                selector = '> li.js-type-item';
                if (type) {
                    selector += '[data-type="' + type + '"]';
                }
                this.parent.$features_types.find(selector).each(function () {
                    var $this = $(this);
                    var id = $this.data('type');
                    var count = '0';
                    if (counter[id] != undefined) {
                        count = '' + counter[id];
                    }
                    $this.find('span.count').text(count);
                });
            },
            value: function (value, field) {
                if (typeof(value) != 'object') {
                    if (field) {
                        value = {
                            'value': value.replace(/\s+.*$/, ''),
                            'unit': value.replace(/^[^\s]\s+/, '')
                        };
                    } else {
                        value = {
                            'value': value
                        }
                    }
                } else {
                    value['unit'] = value['unit'] || value.value.replace(/^[^\s]+\s+/, '');
                    value['value'] = value.value.replace(/\s+.*$/, '');
                }
                $.shop.trace('$.settings.featuresHelper.value', value);
                return field ? (value[field] || '') : value.value;
            }
        }

    });
} else {
    //
}
/**
 * {/literal}
 */
