(function ($) {
    $.product_images = {

        /**
         * {Number}
         */
        product_id: 0,

        /**
         * {Object}
         */
        image: null,

        /**
         * {Jquery Object}
         */
        product_image: null,

        /**
         * {Jquery Object}
         */
        image_list: null,

        /**
         * {Jquery Object}
         */
        photo_stream: null,

        /**
         * Dispatching tail
         * {String}
         */
        tail: null,

        /**
         * {Object}
         */
        options: {},

        init: function (options) {
            this.options = options;
            this.product_id = options.product_id || 0;
            this.image = options.image || null;
            this.options.placeholder = this.options.placeholder || null;

            var tab = $('#s-product-edit-menu .images');

            if (!this.image) {
                this.initImageList(options);
            } else {
                setTimeout(function () {
                    $.product_images.initImage();
                }, 200);
            }

            tab.find('.hint').text(options.count || (options.images && options.images.length) || 0);
            $('#s-product-edit-forms .s-product-form.images').addClass('ajax');

            $.product.editTabImagesAction = function (path) {
                if ($.product_images.tail !== null) {
                    var url = '?module=product&action=images&id=' + path.id;
                    if (path.tail) {
                        url += '&param[]=' + path.tail;
                        if ($.product_images.photo_stream && $.product_images.photo_stream.length) {
                            var ul = $.product_images.photo_stream.find('.s-stream-wrapper>ul');
                            url += '&ps[left]=' + parseInt(ul.css('left'), 10);
                            url += '&ps[width]=' + parseInt(ul.css('width'), 10);
                        }
                    }

                    $.get(url, function (html) {
                        $('#s-product-edit-forms .s-product-form.images').html(html);
                    });
                }
                $.product_images.tail = path.tail;
            };

            $.product.editTabImagesBlur = function (path) {
                $('#fileupload').fileupload('destroy');
            };

            var custom_badge = $('#s-product-set-custom-badge');

            $('.s-product-images-actions').off('click', 'li').on('click', 'li', function () {
                var self = $(this);
                var action = self.attr('data-action');
                switch (action) {
                    case 'set-badge':
                        $.product_images.setBadge(self.attr('data-type'));
                        break;
                    case 'set-custom-badge':
                        custom_badge.data('type', self.attr('data-type'));
                        custom_badge.show();
                        break;
                    case 'delete-badge':
                        $.product_images.deleteBadge();
                        break;
                }
                if (action != 'set-custom-badge') {
                    custom_badge.hide();
                }
                return false;
            });

            custom_badge.find('input[type=button]').unbind('click').bind('click', function () {
                var self = $(this).parent();
                custom_badge.show();
                $.product_images.setBadge(custom_badge.data('type'), self.find('textarea[name=code]').val());
                return false;
            });

        },

        initImageList: function (options) {
            this.image_list = $(options.image_list || '#s-product-image-list');
            this.image_list.html(tmpl('template-product-image-list', {
                images: options.images,
                placeholder: options.placeholder,
                type: options.type,
                product_id: this.product_id
            }));
            this.initListSortable();
            if (!options.type || options.type == 'thumbs') {
                this.initListEditable();
            }

            if (options.enable_2x) {
                $.fn.retina && this.image_list.find('img').retina();
            }
        },

        initImage: function () {
            var image_div = $('#s-product-one-image');
            var width = image_div.parent().width();
            this.photo_stream = $('#s-product-image-toolbar ul.s-photostream:first');
            var sizes = this.setImageDimensions();
            $(window).unbind('resize.product_images').bind('resize.product_images', function (e) {
                if (!$('#s-product-image').length) {
                    $(this).unbind('resize.product_images');
                    return;
                }
                if (!$.product_images.image) {
                    $(this).unbind('resize.product_images');
                    return;
                }
                var sizes = $.product_images.setImageDimensions();
                $.product_images.loupe.resize(sizes);
            });

            $('#s-product-image-description').inlineEditable({
                inputType: 'textarea',
                makeReadableBy: ['esc'],
                updateBy: ['ctrl+enter'],
                placeholderClass: 'gray',
                placeholder: $.product_images.options.placeholder,
                minSize: {
                    height: 50,
                    widht: 600
                },
                size: {
                    width: sizes.width
                },
                editLink: '#s-product-image-description-edit-link',
                allowEmpty: true,
                beforeMakeEditable: function (input) {
                    var self = $(this);
                    var button_id = this.id + '-button';
                    var button = $('#' + button_id);
                    if (!button.length) {
                        input.after('<br><input type="button" id="' + button_id + '" value="' + $_('Save') + '"> <em class="hint" id="' + this.id + '-hint">Ctrl+Enter</em>');
                        $('#' + button_id).click(function () {
                            self.trigger('readable');
                        });
                    }
                    $('#' + this.id + '-hint').show();
                    button.show().prev('br').hide();
                },
                afterBackReadable: function (input, data) {
                    var value = $(input).val();
                    var prefix = '#' + this.id + '-';

                    $(prefix + 'button').hide().prev('br').hide();
                    $(prefix + 'hint').hide();
                    if (data.changed) {
                        $.products.jsonPost('?module=product&action=imageSave', {
                            id: $.product_images.image.id,
                            data: {
                                description: value
                            }
                        });
                    }
                }
            });

            var photo_stream = this.photo_stream;
            var selected = photo_stream.find('li.selected');

            photo_stream.photoStreamSlider({
                backwardLink: '#s-product-image-toolbar .rewind',
                forwardLink: '#s-product-image-toolbar .ff',
                photoStream: 'ul',
                duration: 400
            });

            photo_stream.off('click', 'li.visible a').on('click', 'li.visible a', function () {
                var self = $(this);
                var href = self.attr('href');
                var li = $(this).parent();
                selected.removeClass('selected');
                selected = li.addClass('selected');
                photo_stream.trigger('home', [function () {
                    location.href = href;
                }]);
                return false;
            });

            this.photo_stream = photo_stream;
            this.product_image = $('#s-product-image');
            var custom_badge = $('#s-product-set-custom-badge');

            $('.s-product-image-actions').off('click', 'li').on('click', 'li', function () {
                var self = $(this);
                var action = self.attr('data-action');
                switch (action) {
                    case 'delete':
                        $.product_images.deleteImage();
                        break;
                    case 'rotate-right':
                        $.product_images.rotateImage('right');
                        break;
                    case 'rotate-left':
                        $.product_images.rotateImage('left');
                        break;
                    case 'set-badge':
                        $.product_images.setBadge(self.attr('data-type'));
                        break;
                    case 'set-custom-badge':
                        custom_badge.data('type', self.attr('data-type'));
                        custom_badge.show();
                        break;
                    case 'delete-badge':
                        $.product_images.deleteBadge();
                        break;
                }
                if (action != 'set-custom-badge') {
                    custom_badge.hide();
                }
                return false;
            });

            custom_badge.find('input[type=button]').unbind('click').bind('click', function () {
                var self = $(this).parent();
                custom_badge.show();
                $.product_images.setBadge(custom_badge.data('type'), self.find('textarea[name=code]').val());
                return false;
            });

            var image = this.image;
            if ($('#s-product-image-loupe').length) {
                $.product_images.loupe.init({
                    original: {
                        width: image.width,
                        height: image.height,
                        src: '?module=product&action=ImageDownload&id=' + image.id
                    },
                    image: {
                        width: this.product_image.width(),
                        height: this.product_image.height(),
                        src: image.url_big,
                        dom: this.product_image
                    },
                    animate: !!(image.dimensions.width && image.dimensions.height)
                });
            }

            var self = this;
            $('#s-restore-image-original').unbind('click').bind('click', function () {
                if (confirm($_('This will reset all changes you applied to the image after upload, and will restore the image to its original. Are you sure?'))) {
                    self.coverToggle();
                    $.products.jsonPost('?module=product&action=imageRestore', {id: self.image.id},
                        function (r) {
                            $('#s-product-view').find('li[data-image-id=' + self.image.id + '] img').attr('src', r.data.url_crop);
                            $('<img>').attr('src', r.data.url_big).load(
                                function () {
                                    $(this).remove();
                                    $.products.dispatch('#/product/' + self.product_id + '/edit/images/' + self.image.id + '/');
                                    self.coverToggle();
                                }
                            );
                        },
                        'json');
                }
            });

            if (this.options.enable_2x) {
                $.fn.retina && $('#s-product-image').retina();
            }

            var hold = false;
            $(document).unbind('keydown.product_images').
                bind('keydown.product_images', function (e) {
                    var target_type = $(e.target).prop('nodeName').toLowerCase();
                    var code = e.keyCode;
                    if (hold || target_type == 'text' || target_type == 'textarea' || (code != 37 && code != 39)) {
                        return;
                    }
                    // right
                    if (code == 39) {
                        var next = photo_stream.find('li.selected').next();
                        if (next.length && !next.hasClass('dummy')) {
                            hold = true;
                            location.href = next.find('a').attr('href');
                        }
                        return false;
                    }
                    // left
                    if (code == 37) {
                        var prev = photo_stream.find('li.selected').prev();
                        if (prev.length && !prev.hasClass('dummy')) {
                            hold = true;
                            location.href = prev.find('a').attr('href');
                        }
                        return false;
                    }
                });
        },

        setImageDimensions: function () {
            var image = $('#s-product-image');
            var image_div = $('#s-product-one-image');
            var width = image_div.parent().width();
            var dimensions = this.image.dimensions;
            dimensions.width = parseInt(dimensions.width, 10);
            dimensions.height = parseInt(dimensions.height, 10);

            var sizes = {width: '', height: ''};
            if (!isNaN(dimensions.width) && dimensions.width < width) {
                sizes.width = dimensions.width;
                if (!isNaN(dimensions.height)) {
                    sizes.height = dimensions.height;
                }
            } else {
                sizes.width = width;
            }
            image.css(sizes);
            return sizes;
        },

        deleteImage: function () {
            var self = this;
            var dialog = $('#s-product-image-delete-dialog');
            dialog.waDialog({
                onSubmit: function () {
                    dialog.trigger('close');
                    self.coverToggle();
                    var form = dialog.find('form');
                    $.shop.jsonPost(form.attr('action'), form.serialize(),
                        function (r) {
                            var href = '#/product/' + self.product_id + '/edit/images/';
                            var current = self.photo_stream.find('li.selected');
                            var near = current.next('li:not(.dummy)');
                            if (near.length) {
                                href = near.find('a').attr('href');
                            } else {
                                near = current.prev('li:not(.dummy)');
                                if (near.length) {
                                    href = near.find('a').attr('href');
                                }
                            }
                            $('.s-product-image-crops').find('li[data-image-id=' + r.data.id + ']').remove();
                            self.coverToggle();
                            location.href = href;
                        }
                    );
                    return false;
                }
            });
        },

        rotateImage: function (direction) {
            var self = this;
            self.coverToggle();
            $.products.jsonPost('?module=product&action=imageRotate&id=' + self.image.id, {direction: direction},
                function (r) {
                    $('#s-product-view').find('li[data-image-id=' + self.image.id + '] img').attr('src', r.data.url_crop);
                    $('<img>').attr('src', r.data.url_big).load(
                        function () {
                            $(this).remove();
                            $.products.dispatch('#/product/' + self.product_id + '/edit/images/' + self.image.id + '/');
                            self.coverToggle();
                        }
                    );
                }
            );
        },

        setBadge: function (type, code, fn) {
            var self = this;

            code = code || type;
            var organize_menu = $('#photo-organize-menu');
            var li = organize_menu.find('li[data-type=' + type + ']');
            li.find('a').append('<span class="count"><i class="icon16 loading"></i></span>');
            $.shop.jsonPost(
                '?module=product&action=badgeSet&id=' + self.product_id,
                {code: code},
                function (r) {
                    var image = $('#s-product-one-image');
                    image.find('.top.right').html(r.data);
                    var selected_li = organize_menu.find('li.selected');
                    selected_li.removeClass('selected').find('.small').text('');
                    li.addClass('selected');
                    li.find('span.count').remove();
                    if (!image.length) {
                        li.find('a').append('<span class="count"><i class="icon10 yes"></i></span>');
                        setTimeout(function () {
                            li.find('span.count').remove();
                        }, 1000);
                    }

                    if (type != 'custom') {
                        $('#s-product-set-custom-badge').hide();
                        li.find('.small').text(r.data);
                    } else {
                        $('#s-product-set-custom-badge').show();
                    }
                    if (typeof fn === 'function') {
                        fn(r);
                    }
                }
            );
        },

        deleteBadge: function () {
            var self = this;
            $.getJSON('?module=product&action=badgeDelete&id=' + self.product_id,
                function (r) {
                    $('#s-product-one-image').find('.top.right').html('');
                    $('#photo-organize-menu').find('li.selected').removeClass('selected').find('.small').text('');
                }
            );
        },

        initListSortable: function () {
            this.image_list.sortable({
                distance: 5,
                helper: 'clone',
                items: 'li',
                opacity: 0.75,
                tolerance: 'pointer',
                start: function () {
                    document.ondragstart = function () {
                        return false;
                    };
                },
                update: function (event, ui) {
                    document.ondragstart = null;
                    var self = $(this);
                    var li = $(ui.item);
                    var id = parseInt(li.attr('data-image-id'), 10);
                    var next = li.next(), before_id = null;
                    if (next.length) {
                        before_id = parseInt(next.attr('data-image-id'), 10);
                    }
                    $.products.jsonPost('?module=product&action=imageMove', {id: id, before_id: before_id},
                        function (r) {
                            if (typeof $.product_images.options.onSort === 'function') {
                                $.product_images.options.onSort(id, before_id);
                            }
                        },
                        function () {
                            self.sortable('cancel');
                        }
                    );
                }
            });
        },

        coverToggle: function () {
            var cover = $('#s-product-image-cover');
            if (cover.is(':hidden')) {
                var icon = cover.find('.loading');
                icon.css({
                    position: 'absolute',
                    left: parseInt((cover.width() - icon.width()) / 2, 10) + 'px',
                    top: parseInt((cover.height() - icon.height()) / 2, 10) + 'px'
                });
                cover.show();
            } else {
                cover.hide();
            }
        },

        initListEditable: function () {
            this.image_list.off('click', '.editable').on('click', '.editable', function () {
                $(this).inlineEditable({
                    inputType: 'textarea',
                    makeReadableBy: ['esc'],
                    updateBy: ['ctrl+enter'],
                    placeholderClass: 'gray',
                    placeholder: $.product_images.options.placeholder,
                    minSize: {
                        height: 40
                    },
                    allowEmpty: true,
                    beforeMakeEditable: function (input) {
                        var self = $(this);

                        input.css({
                            'font-size': self.css('font-size'),
                            'line-height': self.css('line-height')
                        }).width(
                            self.parents('li:first').find('img').width()
                        );

                        var button_id = this.id + '-button';
                        var button = $('#' + button_id);
                        if (!button.length) {
                            input.after('<br><input type="button" id="' + button_id + '" value="' + $_('Save') + '"> <em class="hint" id="' + this.id + '-hint">Ctrl+Enter</em>');
                            $('#' + button_id).click(function () {
                                self.trigger('readable');
                            });
                        }
                        $('#' + this.id + '-hint').show();
                        button.show();
                    },
                    afterBackReadable: function (input, data) {
                        var self = $(this);
                        var image_id = parseInt(self.parents('li:first').attr('data-image-id'), 10);
                        var value = $(input).val();
                        var prefix = '#' + this.id + '-';

                        $(prefix + 'button').hide();
                        $(prefix + 'hint').hide();
                        if (data.changed) {
                            $.products.jsonPost('?module=product&action=imageSave', {
                                id: image_id,
                                data: {
                                    description: value
                                }
                            });
                        }
                    }
                }).trigger('editable');
            });
        }
    };
})(jQuery);
