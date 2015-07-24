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
            value_templates: {
                'color': '-color',
                '': ''
            },
            filter: null,

            /**
             * set true to enable edit canceling
             */
            revert: false,
            /**
             *
             */
            show_all_features: true,
            show_all_types: true,
            types_per_page: null

        },
        features_data: {
            types_visible: true,
            feature_id: 0,
            value_id: 0
        },
        features_timer: {
            loading: null,
            filter: null,
            type_filter: null
        },
        /**
         * @var {jQuery} $('#s-settings-features')
         */
        $features_list: null,
        /**
         * @var {jQuery} $('#s-settings-feature-types')
         */
        $features_types: null,

        /**
         * Some types can't be convert each other, so this data-structure show it
         * Use for disable options in select
         */
        features_incompatible_types: {
            'varchar': 'boolean,2d.*,3d.*',
            'text': 'boolean,2d.*,3d.*',
            'boolean': 'range.*,2d.*,3d.*',
            'double': '2d.*,3d.*',
            'range.*': 'boolean,2d.*,3d.*',
            '2d.*': '^2d.*',
            '3d.*': '^3d.*'
        },

        /**
         * Init section
         */
        featuresInit: function () {
            $.shop.trace('$.settings.featuresInit');
            /* init settings */
            this.$features_types = $('#s-settings-feature-types');
            this.$features_list = $('#s-settings-features');
            this.features_data.types_visible = this.features_options.show_all_types;
            var self = this;


            $('#s-settings-content').on('click', 'a.js-action', function () {
                return self.click($(this));
            });
            $('#s-settings-features-type-dialog ul.js-type-icon').on('click', 'a.js-action', function () {
                return self.featuresTypeIcon($(this));
            });

            $('#s-settings-features-type-dialog ul.js-add-type').on('click', 'a.js-action', function () {
                return self.featuresTypeAddToggle($(this));
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

            this.featuresInitDroppable(this.$features_types.find('li:not(.not-sortable)'));
            $('#s-settings-features-type-dialog').prependTo('#wa-app');

            if (this.helpers) {
                this.helpers.compileTemplates('#s-settings-content');
            }
        },
        featuresTypeInit: function () {
            var self = this;
            this.$features_types.sortable({
                distance: 5,
                opacity: 0.75,
                items: '> li:not(.not-sortable)',
                axis: 'y',
                containment: 'parent',
                update: function (event, ui) {
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
            $.shop.trace('$.settings.featuresTypeFilterApply', [val, this.features_options.filter]);
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
                    if (this.features_data.types_visible) {
                        this.$features_types.find('>li.js-type-item:hidden').show();
                    } else {
                        this.$features_types.find('>li.js-type-item:visible').hide();
                        var counter = this.features_options.types_per_page;
                        this.$features_types.find('>li.js-type-item:hidden').each(function () {
                            $(this).show();
                            $.shop.trace('$.settings.featuresTypeFilterApply cnt', counter);
                            return (--counter > 0);
                        });
                        this.featuresTypeShow(false);
                    }
                }
            }
        },

        features_init: {
            list_sortable: false,
            list_dragable: false,
            list_interaction: false

        },

        featuresInitList: function (type, lazy) {
            this.$features_list = $('#s-settings-features');
            var self = this;
            $.shop.trace('$.features.featuresInitList', [type, type === 0]);
            if (type !== undefined) {
                if (( type === 0)) {
                    this.$features_list.find('> tbody:first > tr:visible i.sort').hide();
                } else if (lazy) {
                    this.$features_list.find('> tbody:first > tr:visible i.sort').show();
                }
            } else {
                type = self.featuresHelper.type();
            }
            if ((type !== 'empty') && (type !== '') && (type !== 0)) {
                this.$features_list.sortable({
                    'distance': 5,
                    'opacity': 0.75,
                    'items': '> tbody:first > tr:visible',
                    'handle': '.sort',
                    'cursor': 'move',
                    'tolerance': 'pointer',
                    'update': function (event, ui) {
                        var $feature = $(ui.item);
                        var $after = $feature.prev(':visible');
                        self.featuresFeatureSort($feature, $after, $(this));
                    },
                    'start': function () {
                        $('.block.drop-target').addClass('drag-active');
                    },
                    'stop': function () {
                        $('.block.drop-target').removeClass('drag-active');
                    }
                }); //.find(':not(:input)').disableSelection();
                this.features_init.list_sortable = true;
            } else if (lazy) {
                if (this.features_init.list_sortable) {
                    this.$features_list.sortable('destroy');
                    this.features_init.list_sortable = false;
                }
            }
            if ((type === 'empty') || (type === '')) {

                this.$features_list.find('> tbody:first > tr:visible').draggable({
                    'distance': 5,
                    'opacity': 0.75,
                    'handle': '.sort',
                    'cursor': 'move',
                    'helper': 'clone',
                    'start': function () {
                        $('.block.drop-target').addClass('drag-active');
                    },
                    'stop': function () {
                        $('.block.drop-target').removeClass('drag-active');
                    }
                }).find(':not(:input)').disableSelection();

                this.features_init.list_dragable = true;
            } else if (lazy) {
                if (this.features_init.list_dragable) {
                    this.$features_list.find('> tbody:first > tr:visible').draggable('destroy');
                    this.features_init.list_dragable = false;
                }
            }

            if (!lazy || !this.features_init.list_interaction) {
                this.$features_list.on('change, click', ':input:checkbox[name$="\\[types\\]\\[0\\]"][name^="feature"]', function () {
                    self.featuresFeatureTypesChange($(this));
                });

                this.$features_list.on('keypress', ':input[name$="\\]\\[name\\]"][name^="feature\\["]', function (e) {

                    try {
                        if (e.which && e.which == 13) {
                            var feauture_id = $(this).parents('tr').data('feature');
                            self.featuresFeatureSave(feauture_id);
                        }
                    } catch (e) {
                        $.shop.error(e);
                    }
                });

                this.$features_list.on('change', ':input.js-feature-types-control', function () {
                    self.featuresFeatureValueTypeChange($(this));
                });

                this.$features_list.on('change', ':input.js-feature-subtypes-control', function () {
                    self.featuresFeatureValueTypeChainChange($(this));
                });

                this.$features_list.find('.color').hover(function () {
                    $(this).css('cursor', 'pointer');
                }, function () {
                    $(this).css('cursor', 'default');
                });
                this.features_init.list_interaction = true;
            }
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
                    $list.find(':input[name^="feature\\[' + feature.id + '\\]\\[values\\]"]').each(function () {
                        $(this).parents('li').remove();
                    });
                    $list.data('values_template', feature.values_template);
                    this.featuresFeatureValueAdd(feature.id);
                }
            } else {
                $feature.find('ul.js-feature-values:first').sortable("destroy");
                $container.hide();
            }

            this.featuresFeatureValueChange($feature, feature);

            $.shop.trace('$.settings.featuresFeatureChange', [feature, $feature]);
        },

        /**
         * @param {jQuery} $container
         * @param {Object} feature
         */
        featuresFeatureValueChange: function ($container, feature) {
            switch (feature.type) {
                case 'color':
                    var timer_id = {};

                    $container.find(':input[name$="\\[value\\]"]').unbind('keydown.features').bind('keydown.features', function () {
                        if (timer_id[this.name]) {
                            clearTimeout(timer_id[this.name]);
                        }

                        if (this.value) {
                            var input = this;
                            timer_id[this.name] = setTimeout(function () {
                                var $input = $(input);
                                $input.data('changed', true);
                                var $container = $input.parent();
                                var $color = $container.find('a.js-action[href^="#/features/feature/value/color/"] > i.icon16');
                                var $code = $container.find(':input[name$="\\[code\\]"]:first');
                                $.settings.featuresHelper.codeByName(input.value, $code, function (code) {
                                    $color.css('background', code);
                                    $code.trigger('keydown.farbtastic');
                                });
                            }, 1000);
                        } else {
                            $(this).data('changed', false);
                        }
                    });


                    $container.find(':input[name$="\\[code\\]"]').unbind('keydown.features').bind('keydown.features', function () {

                        if (timer_id[this.name]) {
                            clearTimeout(timer_id[this.name]);
                        }

                        var input = this;
                        timer_id[this.name] = setTimeout(function () {
                            var color = 0xFFFFFF & parseInt(('' + input.value + '000000').replace(/[^0-9A-F]+/gi, '').substr(0, 6), 16);
                            var css = {
                                background: (0xF000000 | color).toString(16).toUpperCase().replace(/^F/, '#')
                            };
                            var $input = $(input);
                            $input.data('changed', !!input.value.length);
                            var $container = $input.parent();
                            var $color = $container.find('a.js-action[href^="#/features/feature/value/color/"] > i.icon16');
                            $color.css(css);
                            var $name = $container.find(':input[name$="\\[value\\]"]:first');
                            if (!$name.data('changed')) {
                                $.settings.featuresHelper.nameByCode(color, $name);
                            }
                        }, 300);
                    });
                    break;
            }
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
                    $container.find('> li[data-type!="0"] :checkbox').each(function (index, el) {
                        this.checked = el.defaultChecked || (type == this.valueOf());
                    });
                }
            }, 10);
        },

        /**
         * Disable section event handlers
         */
        featuresBlur: function () {
            $('#s-settings-features-type-dialog').off('click', 'a.js-action').remove();
            $('#s-settings-content').off('click', 'a.js-action');
        },

        /**
         *
         * @param {String} tail
         */
        featuresAction: function (tail) {
            $.shop.trace('$.settings.featuresAction', [this.path, tail]);
            var type = ((tail !== '') && (tail !== 'empty')) ? (parseInt(tail) || 0) : tail;
            if (!this.features_options.show_all_features) {
                $('div.s-settings-form:first > div:hidden:not(.js-loading)').show();
            }

            this.featuresTypeSelect(type, this.features_options.show_all_features);
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
                'accept': 'tr',
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
         * @param {boolean=} lazy
         */
        featuresTypeSelect: function (type, lazy) {
            /* change selected type and filter features rows */

            this.$features_types.find('> li.selected:first').removeClass('selected');
            var $type = this.$features_types.find('> li[data-type="' + type + '"]:first');
            $.shop.trace('$.settings.featuresTypeSelect', [type, lazy, $type.length]);
            if ($type.length) {
                var name = $type.addClass('selected').find('span.js-type-name').text();
                $('#s-settings-features-type-name').text(name.replace(/(^[\r\n\s]+|[\r\n\s]+$)/mg, ''));
                $type.show();

                //type edit menu
                if (type !== 'empty') {
                    $('#s-settings-features-feature-menu:hidden').show();
                } else {
                    $('#s-settings-features-feature-menu:visible').hide();
                }

                //type edit menu
                if (parseInt(type)) {
                    $('#s-settings-features-type-menu:hidden').show();
                } else {
                    $('#s-settings-features-type-menu:visible').hide();
                }

                //hide visible features
                if (!lazy || (type !== '')) {
                    this.$features_list.find('> tbody > tr').hide();
                    $.shop.trace('hide', [lazy, this.$features_list.find('> tbody > tr:visible').length]);
                }

                if (lazy) {
                    this.featuresFilter(type);
                } else {
                    this.featuresTypeLoadList(type);
                }
                this.path.tail = type;
            } else {
                var hash = '#/features/';
                var $types = this.$features_types.find('> li.js-type-item:first, > li[data-type=""]:first').last();

                if ($types.length) {
                    if ($types.data('type')) {
                        hash += $types.data('type') + '/';
                    }
                } else {
                    hash += 'empty/'
                }
                window.location.hash = hash;
            }
        },

        featuresTypeLoadList: function (type) {

            this.$features_list.hide();
            $('div.s-settings-form:first > div.js-loading:first').show();
            var self = this;
            $.get('?module=settings&action=featuresFeatureList',
                {'type': type}, function (data) {
                    self.$features_list.find('> tbody:first').html(data);
                }, 'html').complete(function () {
                    $.shop.trace('$.settings.featuresTypeLoadList complete');
                    self.$features_list.show();
                    $('div.s-settings-form:first > div:hidden:not(.js-loading)').show();
                    $('div.s-settings-form:first > div.js-loading:first').hide();
                    $('html, body').animate({
                        scrollTop: 0
                    }, 200);
                    setTimeout(function () {
                        self.call('featuresInitList', [type]);
                    }, 100);
                    self.featuresHelper.featureCountByType(type);
                }).error(function () {

                });
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
                }, function (data) {
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
                    var types = '' + current.sort().join(' ');
                    $feature.attr('data-types', types).data('types', types);
                    if (self.path.tail === 'empty') {

                        if (self.features_options.show_all_features) {
                            $feature.hide();
                            self.featuresFilter(self.path.tail, true);

                        } else {
                            $feature.remove();
                            self.featuresHelper.featureCountByType(self.path.tail);
                        }
                    } else {
                        self.featuresHelper.featureCountByType(self.features_options.show_all_features ? undefined : self.path.tail);
                    }
                    $.shop.trace('$.settings.featuresFeatureTypeChange tmpl', current);
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
                $('div.s-settings-form:first > div.js-loading:first').show();
            }, 100);

            var selector = '> tbody:first > tr:hidden';
            if (type === '') {
                selector += '';
            } else if (type === 'empty') {
                selector += ':not([data-types~=" "])';
            } else if (type) {
                selector += '[data-types~="' + type + '"]';
            } else {
                selector += '[data-types^="' + type + '"]';
            }

            setTimeout(function () {
                self.featuresFilterApply(type, selector);
            }, 20);
        },

        /**
         * Recursive apply filter conditions
         * @private
         * @param {Number} type
         * @param {String} selector
         */
        featuresFilterApply: function (type, selector) {
            var counter = 50;

            $.shop.trace('$.settings.featuresFilterApply', [type, selector, this.$features_list.find(selector).length]);
            this.$features_list.show();
            var self = this;
            this.$features_list.find(selector).each(function () {
                if (type === 'empty') {
                    var $this = $(this);
                    var types = self.featuresHelper.featureTypes($this);
                    if (!types.length) {
                        $this.show();
                    }
                } else {
                    $(this).show();
                }
                return !!(counter--);
            });
            if (counter >= 0) {
                this.featuresSort(type);
                this.featuresFilterStop(true, type);
                this.featuresHelper.featureCountByType(type);
                $.shop.trace('$.settings.featuresFilter stop', [type, counter, selector]);
            } else {
                var self = this;
                this.features_timer.filter = setTimeout(function () {
                    self.featuresFilterApply(type, selector);
                }, 20);
            }
        },

        /**
         *
         * @param {boolean=} scroll
         * @param {string=} type
         */
        featuresFilterStop: function (scroll, type) {
            if (this.features_timer.filter) {
                clearTimeout(this.features_timer.filter);
                this.features_timer.filter = null;
            }
            if (this.features_timer.loading) {
                clearTimeout(this.features_timer.loading);
                this.features_timer.loading = null;
            }
            $('div.s-settings-form:first > div.js-loading:first').hide();
            $('div.s-settings-form:first > div:hidden:not(.js-loading)').show();

            if (type !== undefined) {
                this.featuresInitList(type, true);
            }

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
            if ((type !== '') && (type !== 'empty')) {
                type = parseInt(type) || 0;
                /**
                 * @todo test speed
                 */
                this.$features_list.find('> tbody:first').append(this.$features_list.find('> tbody:first > tr:visible').get().sort(function (a, b) {
                    if (type || true) {
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
         * @param {boolean|jQuery=} $el
         */
        featuresTypeShow: function ($el) {

            if ($el && ($el !== false)) {
                this.$features_types.find('>li.not-sortable.js-type-show-all:visible').html('<i class="icon16 loading"></i>');
                var self = this;
                this.features_data.types_visible = true;
                setTimeout(function () {
                    $.shop.trace('featuresTypeShow', 'start');
                    self.$features_types.find('>li.js-type-item:hidden').show();
                    self.$features_types.find('>li.not-sortable.js-type-show-all:visible').hide();
                    $.shop.trace('featuresTypeShow', 'stop');
                    self.featuresTypeInit();
                }, 10);
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

                    $this.find('.js-add-type').show();
                    $this.find('.js-add-type-custom').show();
                    $this.find('.js-add-type-template').hide();
                    var $ul = $this.find('ul.js-add-type');
                    $ul.find('li.selected').removeClass('selected');
                    $ul.find('li:first').addClass('selected');

                    $this.find(':input[name="source"]').val('custom');
                    $this.find('.js-edit-type').hide();
                    $this.find('.js-edit-type').hide();

                    $this.find('ul.js-type-icon li.selected').removeClass('selected');
                    var icon = $this.find('ul.js-type-icon li:first').addClass('selected').data('icon');

                    $this.find(':input[name="icon"]').val(icon);
                    $this.find(':input[name="icon_url"]').val('');
                    $this.find(':input[name="id"]').val(null);
                    $this.find(':input[name="name"]').val('').focus();

                },
                onSubmit: function (d) {
                    var form = d.find('form');
                    var source = form.find(':input[name="source"]').val();
                    $.post(form.attr('action'), form.serialize(), function (response) {
                        try {
                            if (response && (response.status == 'ok')) {
                                $.shop.trace('response', [response, response.data]);
                                var type = response.data;
                                $.tmpl('feature-type', type).insertBefore(self.$features_types.find('> li:last'));
                                var $type = self.$features_types.find('>  li[data-type="' + type.id + '"]');
                                if (type.features) {
                                    for (var id in type.features) {
                                        var $feature_old = self.$features_list.find('> tbody:first > tr[data-feature="' + type.features[id]['id'] + '"]:first');

                                        var $feature = $.tmpl('feature', {
                                            'types': self.featuresHelper.types(),
                                            'feature': type.features[id]
                                        });
                                        if ($feature_old.length) {
                                            $feature_old.replaceWith($feature);
                                        } else {
                                            self.$features_list.find('> tbody:first').prepend($feature);
                                        }
                                        // self.featuresFeatureChange($feature, type.features[id]);
                                        if (!self.featuresHelper.type()) {
                                            $feature.find(' > td > .sort').hide()
                                        }
                                    }
                                    self.featuresHelper.featureCountByType();
                                }
                                self.featuresInitDroppable($type);
                                d.trigger('close');
                                window.location.hash = '#/features/' + type.id + '/';
                                if (source == 'template') {
                                    //TODO use features list in response
                                    //window.location.reload(true);
                                }
                            } else {
                                //todo
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
        featuresTypeAddToggle: function ($el) {
            var $dialog = $('#s-settings-features-type-dialog');
            var source = $el.attr('href').replace(/(^#\/features\/type\/add\/|\/$)/g, '');
            $el.parents('ul').find('li.selected').removeClass('selected');
            $el.parent('li').addClass('selected');
            $.shop.trace('featuresTypeAddToggle', [source, $dialog]);
            switch (source) {
                case 'custom':
                    $dialog.find('.js-add-type-custom').show();
                    $dialog.find('.js-add-type-template').hide();
                    break;
                case 'template':
                    $dialog.find('.js-add-type-template').show();
                    $dialog.find('.js-add-type-custom').hide();
                    break;
            }

            $dialog.find(':input[name="source"]').val(source);
            return false;

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


                        $this.find(':input[name="source"]').val('custom');
                        $this.find('.js-add-type-custom').show();
                        $this.find('.js-add-type-template').hide();
                        var name = $type.find('.js-type-name').text().replace(/(^[\r\n\s]+|[\r\n\s]+$)/mg, '');
                        var icon = $type.data('icon').replace(/^icon\.([\w\d\-_]+)$/, '$1');

                        $this.find('ul.js-type-icon li.selected').removeClass('selected');
                        if ($this.find('ul.js-type-icon li[data-icon="' + icon + '"]').length) {
                            $this.find('ul.js-type-icon li[data-icon="' + icon + '"]').addClass('selected');
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
         * @return {Boolean}
         */
        featuresTypeDelete: function () {
            var type = this.featuresHelper.type();
            $.shop.trace('$.settings.featuresTypeDelete', [type, this.path]);
            var self = this;
            if (type) {
                $.post('?module=settings&action=featuresTypeDelete', {
                    'id': type
                }, function (response) {
                    try {
                        if (response && (response.status == 'ok')) {
                            $.shop.trace('response', [response, response.data, type]);
                            self.$features_types.find('> li[data-type="' + type + '"]').remove();
                            self.$features_list.find('span[data-type="' + type + '"]').remove();
                            self.$features_list.find('> tbody > tr').filter(function () {
                                return self.featuresHelper.featureTypes($(this)).indexOf(type) >= 0;
                            }).each(function () {
                                var pattern = new RegExp('\b' + type + '\b\s+');
                                var $el = $(this);
                                $el.data('types', ('' + $el.data('types')).replace(pattern, ''));
                            });

                            if (!self.features_options.show_all_features) {
                                self.$features_list.find('li[data-type="' + type + '"]').remove();
                            }
                            var hash = '#/features/';
                            var $types = self.$features_types.find('> li.js-type-item:first, > li[data-type=""]:first').last();
                            if ($types.length) {
                                if ($types.data('type')) {
                                    hash += $types.data('type') + '/';
                                }
                            } else {
                                hash += 'empty/'
                            }
                            window.location.hash = hash;
                        } else {
                            if (response.errors) {
                                // display error;
                                var $error = $('#s-settings-features-type-error:first');
                                $error.text(response.errors.concat(' ')).slideDown();
                                setTimeout(function () {
                                    $error.slideUp(500);
                                }, 5000);
                            }
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
                var $feature = $.tmpl('edit-feature', {
                    'types': this.featuresHelper.types(),
                    'feature': feature
                });
                self.$features_list.find('> tbody:first').prepend($feature);

                self.featuresFeatureChange($feature, feature);
                if (!self.featuresHelper.type()) {
                    $feature.find(' > td > .sort').hide()
                }

                var $el = $feature.find(':input:checkbox[name$="\\]\\[types\\]\\[0\\]"][name^="feature\\["]');
                self.featuresFeatureTypesChange($el);
                $feature.find(':input[name$="\\[name\\]"]:first').focus();
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
                var $actions = $feature.find('a[href^="\\#/features/feature/delete/"]:first').parents('ul');
                $actions.after('<i class="icon16 loading"></i>');
                $actions.hide();
                this.featuresFeatureValuesShow(feature_id, function (feature_id) {
                    self.featuresFeatureEdit(feature_id);
                });
            } else {
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
                    var $this = $(this);
                    var id = parseInt($this.data('value-id'));
                    if (id) {
                        var value = $this.text().replace(/(^[\r\n\s]+|[\r\n\s]+$)/mg, '');
                        var code = 0;
                        var unit = null;
                        if (type.match(/^color/)) {
                            var rgbString = $this.find('> .icon16').css('background-color') || 'rgb(255,255,255)';
                            var parts = rgbString.match(/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/);

                            for (var i = 1; i <= 3; i++) {
                                code = (code << 8) + parseInt(parts[i]);
                            }
                        } else if (type.match(/^dimension\./)) {
                            unit = self.featuresHelper.value({value: value}, 'unit');
                            value = value.replace(/\s.*$/, '');
                        }
                        feature.values.push({
                            'id': id,
                            'value': value,
                            'code': code,
                            'unit': unit
                        });

                    }
                });
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
                        var $el = $edit_feature.find(':input:checkbox[name$="\\]\\[types\\]\\[0\\]"][name^="feature\\["]');
                        self.featuresFeatureTypesChange($el);
                        if (type.match(/^dimension\.\w+/)) {
                            $edit_feature.find(':input[name$="\[unit\]"]').change(function () {
                                self.featuresHelper.lastUnit(type, this.value);
                            });
                        }
                    });
                } catch (e) {
                    $.shop.error('exception', e);
                }
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
            $container.find(':input[name$="\\[code\\]"]').show().focus();
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
            $feature.find(':input[name$="\\[type\\]"]').val(feature.type);
            $feature.find(':input[name$="\\[selectable\\]"]').val(feature.selectable);
            $feature.find(':input[name$="\\[multiple\\]"]').val(feature.multiple);

            this.featuresFeatureChange($feature, feature);

            $feature.find(':input.js-feature-subtypes-control').each(function () {
                if (feature.type == $(this).data('subtype')) {
                    $(this).show().trigger('change').focus();
                } else {
                    $(this).hide();
                }
            });

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
                'type': $feature.find(':input[name$="\\[type\\]"]').val(),
                'selectable': $feature.find(':input[name$="\\[selectable\\]"]').val(),
                'multiple': $feature.find(':input[name$="\\[multiple\\]"]').val()
            };

            var $selected = $el.find('option:selected');
            if (!$selected.length) {
                $selected = $el.find('option:first');
            }
            feature.type = $selected.data('type');
            $selected.select();
            // update hidden input value
            $feature.find(':input[name$="\\[type\\]"]').val(feature.type);
            $.shop.trace('$.settings.featuresFeatureValueTypeChainChange', feature);
            this.featuresFeatureChange($feature, feature);
        },

        featuresFeatureSave: function (feature_id) {
            var self = this;
            var $feature = this.$features_list.find('> tbody:first > tr[data-feature="' + feature_id + '"].js-inline-edit');
            var $name = $feature.find(':input[name$="\\[name\\]"]');
            if ($name.val().replace(/\s+/, '') == '') {
                $name.addClass('error').focus();
                return false;
            }
            $name.removeClass('error');
            var feature_raw = $feature.find(':input').serialize();
            //$.shop.trace('$.settings.featuresFeatureSave', feature_raw);
            $.post('?module=settings&action=featuresFeatureSave', feature_raw, function (data) {
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

                    $.when(
                        $feature.replaceWith($.tmpl(error ? 'edit-feature' : 'feature', {
                            'types': self.featuresHelper.types(!error),
                            'feature': feature
                        }))
                    ).done(function () {
                            if (error) {
                                $feature = self.$features_list.find('> tbody:first > tr[data-feature="' + feature_id + '"]:first');
                                $feature.on('click focus', 'input', function () {
                                    var $this = $(this);
                                    var $parent = $(this).parents('ul');
                                    $parent.find('input.red').removeClass('red');
                                    if ($this.hasClass('error')) {
                                        var original = parseInt($(this).data('original-id')) || 0;
                                        if (original) {
                                            $parent.find('input[name*="\\[values\\]\\[' + original + '\\]"]:first').addClass('red');
                                        }
                                    }

                                });
                            } else {
                                $feature = self.$features_list.find('> tbody:first > tr[data-feature="' + feature.id + '"]:first');
                                $feature.hide();
                                self.featuresFilter(self.path.tail, true);
                            }
                        });
                }
            }, 'json').complete(function () {
                //self.featuresHelper.featureCountByType(self.featuresHelper.type());
            });
            return false;
        },

        featuresFeatureTypeIncompatible: function (feature_type, type_to) {
            var ban_rule = '';

            if (feature_type.type == type_to.type &&
                feature_type.selectable == type_to.selectable &&
                feature_type.multiple == type_to.multiple) {
                return true;
            }

            if (feature_type.multiple == '1' && type_to.multiple != '1') {
                return true;
            }

            feature_type = feature_type.type;
            type_to = type_to.type;

            var types = $.settings.features_incompatible_types || {};
            for (var type_from in types) {
                var list = types[type_from];
                if (type_from === feature_type) {
                    ban_rule = list;
                    break;
                } else if (type_from.slice(-1) === '*') {
                    type_from = type_from.slice(0, -2);
                    var f_type = feature_type.split('.');
                    if (type_from === f_type[0]) {
                        ban_rule = list;
                        break;
                    }
                }
            }
            if (ban_rule === '*') {
                return true;
            } else if (ban_rule.slice(0, 1) === '^') {
                var not_banned = ban_rule.slice(1);
                if (not_banned.slice(-1) === '*') {
                    not_banned = not_banned.slice(0, -2);
                    var type_to_ar = type_to.split('.');
                    if (not_banned !== type_to_ar[0]) {
                        return true;
                    }
                } else if (not_banned !== type_to) {
                    return true;
                }
            } else {
                var ban_list = ban_rule.split(',');
                for (var i = 0; i < ban_list.length; i += 1) {
                    var ban_type = ban_list[i];
                    if (ban_type === type_to) {
                        return true;
                    } else if (ban_type.slice(-1) === '*') {
                        ban_type = ban_type.slice(0, -2);
                        var type_to_ar = type_to.split('.');
                        if (ban_type === type_to_ar[0]) {
                            return true;
                        }
                    }
                }
            }
            return false;
        },

        featuresFeatureType: function (feature_id) {
            var self = this;
            var isConvertBanned = self.featuresFeatureTypeIncompatible;
            $('#s-settings-features-feature-type-dialog').waDialog({
                onLoad: function () {
                    var d = $(this);

                    d.find('.errormsg').hide();

                    // substitute old type text
                    var f = self.$features_list.find('tr[data-feature=' + feature_id + ']');
                    var old_type = $('.js-feature-type-name', f).text();
                    d.find('.feature-old-type').text(old_type);

                    var f_type = {
                        type: f.data('type'),
                        selectable: f.data('selectable'),
                        multiple: f.data('multiple')
                    };
                    var subtypes_selects = d.find('.js-feature-subtypes-control').hide();

                    var types_select = d.find('.js-feature-types-control');


                    // disable some needed options in select of types
                    types_select.find('option').each(function () {
                        var item = $(this);
                        var type = {
                            type: item.data('type'),
                            selectable: item.data('selectable'),
                            multiple: item.data('multiple')
                        };
                        if (isConvertBanned(f_type, type) || type === 'divider') {
                            item.attr('disabled', true);
                        } else {
                            item.attr('disabled', false);
                        }
                    }).not(':disabled').first().attr('selected', true);

                    types_select.unbind('change.value_type_edit').bind('change.value_type_edit', function () {

                        d.find('.errormsg').hide();

                        var el = $(this);
                        var selected = el.find('option:selected');
                        var type = {
                            type: selected.data('type'),
                            selectable: selected.data('selectable'),
                            multiple: selected.data('multiple')
                        };

                        // try choose proper subtype select
                        subtypes_selects.hide().each(function () {
                            var item = $(this);
                            if (type.type === item.data('subtype')) {
                                item.show().focus();

                                // disable some needed options in select of subtypes
                                item.find('option').each(function () {
                                    var option = $(this);
                                    var subtype = {
                                        type: option.data('type'),
                                        selectable: type.selectable,
                                        multiple: type.multiple
                                    };
                                    if (isConvertBanned(f_type, subtype)) {
                                        option.attr('disabled', true);
                                    } else {
                                        option.attr('disabled', false);
                                    }
                                }).not(':disabled').first().attr('selected', true);

                                return false;
                            }
                        });
                        return false;
                    }).trigger('change');

                    subtypes_selects.unbind('change.value_type_edit').bind('change.value_type_edit', function () {
                        d.find('.errormsg').hide();
                    });

                },
                onSubmit: function (dialog) {
                    var form = $(this);
                    var type_option = form.find('.js-feature-types-control').find('option:selected');
                    var subtype_option = form.find('.js-feature-subtypes-control:not(:hidden)').find('option:selected');
                    var data = {
                        feature_id: feature_id,
                        type: type_option.data('type'),
                        subtype: subtype_option.data('type'),
                        selectable: type_option.data('selectable'),
                        multiple: type_option.data('multiple')
                    };
                    $.post(form.attr('action'), data, function (r) {
                        if (r.status !== 'ok' && r.errors) {
                            dialog.find('.errormsg').show().text(r.errors[0]);
                        } else {
                            try {
                                var feature = r.data[feature_id];
                                var $feature = self.$features_list.find('> tbody:first > tr[data-feature="' + feature_id + '"]:first');
                                $.shop.trace('$.settings.featuresFeatureType ', [$feature.length, $feature, feature]);
                                feature.values = feature.values || {};
                                feature.values_template = feature.values_template || (self.features_options.value_templates[feature.type] || '');
                                $.shop.trace('$.settings.featuresFeatureType ', feature);

                                $.when($feature.replaceWith($.tmpl('feature', {
                                    'types': self.featuresHelper.types(false),
                                    'feature': feature
                                }))).done(function () {
                                    dialog.trigger('close');
                                    $feature = self.$features_list.find('> tbody:first > tr[data-feature="' + feature.id + '"]:first');
                                    $feature.hide();
                                    self.featuresFilter(self.path.tail, true);
                                });
                            } catch (e) {
                                $.shop.error(e.gmessage, e);
                            }
                        }
                    }, 'json');
                    return false;

                }
            });
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
            }, function (data, textStatus) {
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
            var $actions = $feature.find('a[href^="\\#/features/feature/delete/"]:first').parents('ul');
            if (feature_id > 0) {
                var ajax = null;
                $('#s-settings-features-feature-delete-dialog').waDialog({
                    disableButtonsOnSubmit: true,
                    onLoad: function () {
                        var $this = $(this);
                        var form = $this.find('form');
                        var $container = form.find('.js-features-feature-usage-counter');
                        $container.html('<i class="icon16 loading"></i>');
                        form.find(':submit').focus();
                        ajax = $.get('?module=settings&action=featuresFeatureUsage', {
                            feature_id: feature_id
                        }, function (data) {
                            if (data.status == 'ok') {
                                $container.html(data.data.notice || '');
                            } else {
                                $actions.show();
                                //$.shop.error(e);
                                d.trigger('close');
                            }
                        }, 'json');
                    },
                    onSubmit: function (d) {
                        d.trigger('close');
                        if (ajax) {
                            ajax.abort();
                        }
                        $actions.after('<i class="icon16 loading"></i>');
                        $actions.hide();
                        $.post('?module=settings&action=featuresFeatureDelete', {
                            feature_id: feature_id
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
                        return false;
                    },
                    onCancel: function (d) {
                        if (ajax) {
                            ajax.abort();
                        }
                    }
                });
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

            var $container = this.$features_list.find('> tbody:first');
            var $feature = $container.find('> tr[data-feature="' + feature_id + '"].js-inline-edit:first');
            if (!$feature.length) {
                this.featuresFeatureEdit(feature_id);
                $feature = $container.find(' > tr[data-feature="' + feature_id + '"].js-inline-edit:first');
            }
            var type = $feature.find(':input[name$="\\[type\\]"]').val();
            var template = (this.features_options.value_templates[type] || '');
            var self = this;
            var feature = {
                'id': feature_id,
                'value_template': template,
                'type': type || $feature.data('type') || 'text'
            };
            $.shop.trace('featuresFeatureValueAdd', feature);
            var value = '';
            var unit = null;
            $.shop.trace('type', type);
            if (type.match(/^dimension\..+/) && (unit = this.featuresHelper.lastUnit(type))) {
                value = {
                    'value': '',
                    'unit': unit
                };
            }
            var data = {
                'feature': feature,
                'id': --self.features_data.value_id,
                'feature_value': value
            };

            //$.when(function () {
            var $value = $.tmpl('edit-feature-value' + template, data).insertBefore($feature.find('ul.js-feature-values li:last'));
            if (type.match(/^dimension\..+/)) {
                $value.find(':input[name$="\[unit\]"]').change(function () {
                    self.featuresHelper.lastUnit(type, this.value);
                });
            }

            //}).done(function () {
            $.shop.trace('featuresFeatureValueAdd', 'done');
            self.featuresFeatureValueChange($feature, feature);
            $feature.find('ul.js-feature-values:first').sortable('refresh').find(':input:last').focus();
            //  });
        },

        featuresFeatureValuesShow: function (feature_id, callback) {
            var $container = this.$features_list.find('> tbody:first > tr[data-feature="' + feature_id + '"] ul.js-feature-values');
            if (!callback || (typeof(callback) != 'function')) {
                $container.find('li.js-more-link').html('<i class="icon16 loading"></i>');
            }
            $.get('?module=settings&action=featuresFeatureValues',
                {'id': feature_id}, function (data) {
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

        featuresFeatureValueColor: function (feature_id, value_id) {
            var $feature = this.$features_list.find('> tbody:first > tr.js-inline-edit[data-feature="' + feature_id + '"]:first');
            var $input = $feature.find(':input[name^="feature[' + feature_id + '\\]\\[values\\]\\[' + value_id + '\\]\\[code\\]"]');
            var $value = $input.parents('li');
            var $color = $value.find('a.js-action[href$="color/' + feature_id + '/' + value_id + '/"] > i.icon16');
            //add farbstatic placeholder if not exists
            var $colorpicker = $value.find('.js-colorpicker');
            if ($colorpicker.length == 0) {
                $value.append('<div class="js-colorpicker" style="display:none;"></div>');

                $colorpicker = $value.find('.js-colorpicker');
                var farbtastic = $.farbtastic($colorpicker, function (color) {
                    color = 0xFFFFFF & (parseInt(color.substr(1), 16));
                    $.settings.featuresHelper.nameByCode(color, $input.parent().find(':input[name$="\\[value\\]"]'));
                    color = (0xF000000 | color).toString(16).toUpperCase().replace(/^F/, '');
                    if (($input.val() + '000000').substr(0, 6) != color) {
                        $input.val(color);
                        $input.data('changed', true);
                    }

                    color = '#' + color;
                    $color.css('background', color);
                });

                farbtastic.setColor('#' + $input.val());
                $colorpicker.slideToggle(200);
                var timer_id;
                $input.unbind('keydown').bind('keydown.farbtastic', function () {
                    if (timer_id) {
                        clearTimeout(timer_id);
                    }
                    if ($input.val().match(/^[0-9A-F]{6}$/gi)) {
                        timer_id = setTimeout(function () {
                            var color = parseInt(($input.val() + '000000').replace(/[^0-9A-F]+/gi, '').substr(0, 6), 16) & 0xFFFFFF;
                            var $name = $input.parent().find(':input[name$="\\[value\\]"]:first');
                            if (!$name.data('changed')) {
                                $.settings.featuresHelper.nameByCode(color, $name);
                            }
                            color = (0xF000000 | color).toString(16).toUpperCase().replace(/^F/, '#');
                            farbtastic.setColor(color);
                        }, 250);
                    }
                });
            } else {
                $colorpicker.slideToggle(200);
            }
        },

        featuresFeatureValueDelete: function (feature_id, value_id) {
            var $feature = this.$features_list.find('> tbody:first > tr[data-feature="' + feature_id + '"].js-inline-edit:first');
            var $input = $feature.find(':input[name^="feature[' + feature_id + '\\]\\[values\\]\\[' + value_id + '\\]"]');
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
             * @param {string|Number=} type
             */
            featureCountByType: function (type) {
                var helper = this;
                var counter = {
                    '': 0, /* total count */
                    'empty': 0, /* without type*/
                    0: 0 /* applicable for all types */
                };
                var filter = '> tbody:first > tr';
                if (type === undefined) {
                    if (!this.parent.features_options.show_all_features) {
                        var t = this.parent.path.tail;
                        type = ((t !== '') && (t !== 'empty')) ? (parseInt(t) || 0) : t;
                    }
                }
                if (type !== undefined) {
                    filter += ':visible';
                }
                this.parent.$features_list.find(filter).each(function () {
                    if (helper.parent.features_options.show_all_features) {
                        ++counter[''];
                    }
                    var types = helper.featureTypes($(this));
                    if (!types.length) {
                        ++counter['empty'];
                    } else {
                        for (var i = 0; i < types.length; i++) {
                            if (typeof(counter[types[i]]) == 'undefined') {
                                counter[types[i]] = 0;
                            }
                            ++counter[types[i]];
                        }
                    }
                });

                var selector = '> li';
                if (type !== undefined) {
                    if (parseInt(type) > 0) {
                        selector += '.js-type-item';
                    }
                    selector += '[data-type="' + type + '"]';
                }
                $.shop.trace('featureCountByType', [type, filter, selector, counter]);
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
            /**
             *
             * @param value hex
             * @param $input
             */
            nameByCode: function (value, $input) {
                if (value && !$input.data('changed') && (($input.prop("defaultValue") == '') || ($input.val() == ''))) {
                    $.ajax({
                        url: '?module=settings&action=featuresHelper',
                        dataType: 'json',
                        data: {code: value},
                        success: function (response) {
                            if (response && (response.status == 'ok')) {
                                $input.val(response.data.name || '');
                            }
                        }
                    });
                }
            },
            codeByName: function (value, $input, callback) {
                if (value && !$input.data('changed') && (($input.prop("defaultValue") == '') || ($input.val() == ''))) {
                    $.ajax({
                        url: '?module=settings&action=featuresHelper',
                        dataType: 'json',
                        data: {name: value},
                        success: function (response) {
                            if (response && (response.status == 'ok')) {
                                var code = (0xF000000 | parseInt((response.data.code || 0))).toString(16).toUpperCase().replace(/^F/, '#');

                                $input.val(code.replace(/^#/, ''));
                                if (callback && (typeof(callback) == 'function')) {
                                    callback(code, value, $input);
                                }
                            }
                        }
                    });
                }
            },
            value: function (value, field) {
                try {
                    if (typeof(value) != 'object') {
                        if (field) {
                            value = {
                                'value': value.replace(/(^[\r\n\s]+|[\r\n\s]+$)/mg, ''),
                                'unit': value.replace(/^[^\s]\s+/, ''),
                                'hex': '',
                                'color': '#FFFFFF'
                            };
                        } else {
                            value = {
                                'value': value
                            }
                        }
                    } else {
                        if (typeof value['value'] == 'object') {
                            value = value['value'];
                        }
                        value['unit'] = value['unit'] || value.value.replace(/^[^\s]+\s+/, '');
                        value['value'] = value.value.replace(/(^[\r\n\s]+|[\r\n\s]+$)/mg, '');
                        value['code'] = parseInt(value['code'] || 0, 10);
                        value['hex'] = (0xF000000 | value['code']).toString(16).toUpperCase().replace(/^F/, '');
                        value['color'] = '#' + value['hex'];
                    }
                    return field ? (value[field] || '') : value.value;
                } catch (e) {
                    $.shop.error(e, value);
                }
            },
            lastUnit: function (type, unit) {
                if (unit) {
                    $.storage.set('shop/settings/features/unit/' + type, unit);
                } else {
                    unit = $.storage.get('shop/settings/features/unit/' + type);
                }
                $.shop.trace('lastUnit.' + type, unit);
                return unit;
            }
        }

    });
} else {
    //
}
/**
 * {/literal}
 */
