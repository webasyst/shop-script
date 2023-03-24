$.product_images.loupe = {

    options: {
        animate: true
    },

    /**
     * {Boolean}
     */
    drag: false,

    /**
     * {Object}
     */
    original : {
        width: '', height: '', src: '', dom: ''
    },

    /**
     * {Object}
     */
    image : {
        width: '', height: '', src: '', dom: ''
    },

    link : null,
    offset : {},
    status : 'minimazed',

    init: function(options) {
        this.options = $.extend(this.options, options || {});

        this.original = $.extend(this.original, options.original);
        this.image = $.extend(this.image, options.image);

        this.reset();
    },

    reset : function() {
        this.link = $('#s-product-image-loupe');

        this.status = 'minimazed';
        this.link.find('.minimize').hide();
        this.link.find('.maximize').show();

        var self = this;
        this.link.unbind('click.loupe').bind('click.loupe', function(e) {
            return self.clickHandler();
        });

        if (this.image.dom.parent().hasClass('image-loupe-wrapper')) {
            this.image.dom.unwrap();
        }
        this.image.dom.css('max-width', '');
    },

    resize: function(sizes) {
        this.image = $.extend(this.image, sizes);
        if (this.status == 'maximized') {
            this.image.dom.parent().css(sizes);
        }
    },

    clickHandler: function() {
        switch (this.status) {
            case 'maximized' : {
                this.status = 'unloading';
                this.link.find('.minimize').hide();
                this.link.find('.maximize').show();
                this.minimize();
                break;
            }
            case 'minimazed' : {
                this.status = 'loading';
                this.link.find('.minimize').show();
                this.link.find('.maximize').hide();
                this.maximize();
                break;
            }
        }
        return false;
    },

    interaction: function(element, e, node) {
        var body = $(document.body);
        switch (e.type) {
            case 'mouseup' :
                if (this.drag) {
                    this.drag = false;
                    this.image.dom.parent('.image-loupe-wrapper').css('cursor', 'auto');
                    $('body').css('cursor', '');
                    body.unbind(".loupe-move");

                    e.preventDefault();
                }
                break;
            case 'mousedown':
                if (!this.drag) {
                    this.drag = true;
                    this.image.dom.parent('.image-loupe-wrapper').css('cursor', 'move');
                    this.offset.mouseX = e.pageX;
                    this.offset.mouseY = e.pageY;

                    var self = this;
                    body.unbind("mouseover.loupe-move mousemove.loupe-move").
                        bind("mouseover.loupe-move mousemove.loupe-move", function(e) {
                            return self.watch(this, e);
                        }
                    );

                    e.preventDefault();
                }
                break;
            case 'mouseleave':
                if (this.drag) {
                    this.image.dom.parent('.image-loupe-wrapper').css('cursor', 'auto');
                    this.drag = false;
                    body.unbind(".loupe-move");
                    e.preventDefault();
                }
                break;
        }
    },

    maximize: function() {
        this.drag = false;

        var img = this.image.dom;

        this.offset = img.offset();
        this.offset.x = Math.round((this.image.width  - this.original.width)  / 2);
        this.offset.y = Math.round((this.image.height - this.original.height) / 2);

        if (!img.parent('.image-loupe-wrapper').length) {
            img.wrap(
                '<div class="image-loupe-wrapper" ' +
                    'style="' +
                        'height:' + this.image.height + 'px; ' +
                        'width: ' + this.image.width  + 'px; ' +
                        'position: relative; ' +
                        'overflow: hidden" ' +
                '/>'
                );
        }

        var self = this;

        img.css('max-width', this.original.width+'px');
        if (this.options.animate) {
            img.animate(this.computeStyle(this.original), function() {
                return self.maximizeComplete();
            });
        } else {
            img.css(this.computeStyle(this.photo_data));
            this.maximizeComplete();
        }
        this.link.find('.minimize').show();
        this.bind(img);
    },

    computeStyle: function(data) {
        return {
            'width':  data.width  + 'px',
            'height': data.height + 'px',
            'margin-left': this.offset.x + 'px',
            'margin-top':  this.offset.y + 'px'
        };
    },

    maximizeComplete: function() {
        var self = this;

        var complete = function() {
            var original_dom = self.original.dom;
            original_dom.css(self.computeStyle(self.original)).show();
            original_dom.unbind('click.loupe').bind('click.loupe', function() {
                return false;
            });
            self.bind(original_dom);

            self.image.dom.css($.extend(self.computeStyle(self.image), {
                'margin-left': '', 'margin-top': '', 'max-width': ''
            })).unbind(".loupe .loupe-move").hide();

            self.link.find('.minimize').show();
            self.status = 'maximized';
        };

        if (!self.original.dom) {
            var dom = $('#s-product-image-maximazed');
            if (!dom.length) {
                $('<img id="s-product-image-maximazed" />').
                    attr('src', self.original.src).
                    load(function() {
                        self.original.dom = $(this).appendTo(self.image.dom.parents('.image-loupe-wrapper'));
                        complete();
                    }
                );
            } else {
                dom.appendTo(self.image.dom.parents('.image-loupe-wrapper'));
                self.original.dom = dom;
                complete();
            }
        } else {
            self.original.dom.appendTo(self.image.dom.parents('.image-loupe-wrapper'));
            complete();
        }
    },

    bind: function(node) {
        var self = this;
        node.unbind("mousedown.loupe").bind("mousedown.loupe", function(e) {
            return self.interaction(this, e);
        });
        $(document).unbind("mouseup.loupe").bind("mouseup.loupe", function(e) {
            return self.interaction(this, e);
        });
    },

    minimize: function() {
        var style = {
            'width':  this.image.dom.width()  + 'px',
            'height': this.image.dom.height() + 'px',
            'margin-left': 0,
            'margin-top':  0
        };
        this.original.dom.unbind('click.loupe');

        var self = this;
        if (this.options.animate) {
            this.original.dom.animate(style, function() {
                self.minimizeComplete();
            });
        } else {
            this.original.dom.css(style);
            self.minimizeComplete();
        }
        return false;
    },

    minimizeComplete: function() {
        this.image.dom.show();
        this.original.dom.hide();
        self.status = 'minimized';
        this.reset();
    },

    watch: function(element, e) {
        if (this.drag) {
            this.offset.x = Math.min(
                0,
                Math.max(
                    this.image.width - this.original.width,
                    Math.round(this.offset.x - this.offset.mouseX + e.pageX)
                )
            );
            this.offset.y = Math.min(
                0,
                Math.max(
                    this.image.height - this.original.height,
                    Math.round(this.offset.y - this.offset.mouseY + e.pageY)
                )
            );
            this.offset.mouseX = e.pageX;
            this.offset.mouseY = e.pageY;

            var dom = this.status == 'loading' ? this.image.dom : this.original.dom;
            dom.css({
                'margin-left': this.offset.x + 'px',
                'margin-top':  this.offset.y + 'px'
            });
        }
    }
};
