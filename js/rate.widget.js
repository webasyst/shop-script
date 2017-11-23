(function($) {
    $.fn.rateWidget = function(options, ext, value) {

        var supportsTouch = !!('ontouchstart' in window || navigator.msMaxTouchPoints);

        var settings;
        var self = this;
        if (!this || !this.length) {
            return;
        }

        if (typeof options == 'string') {
            if (options == 'getOption') {
                if (ext == 'rate') {
                    return parseInt(this.attr('data-rate'), 10);
                }
            }
            if (options == 'setOption') {
                if (ext == 'rate') {
                    update.call(this, parseInt(value, 10));
                    ext = {
                        rate: value
                    };
                }
                if (typeof ext === 'object' && ext) {
                    settings = this.data('rateWidgetSettings') || {};
                    $.extend(settings, ext);
                    if (typeof ext.hold !== 'undefined' && typeof ext.hold !== 'function') {
                        settings.hold = _scalarToFunc(settings.hold);
                    }
                }
            }
            return this;        // means that widget is installed already
        }

        this.data('rateWidgetSettings', $.extend({
            onUpdate: function() {},
            rate: null,
            hold: false,
            withClearAction: true
        }, options || {}));

        if (this.data('inited')) {  // has inited already. Don't init again
            return;
        }

        settings = this.data('rateWidgetSettings');
        if (typeof settings.hold !== 'function') {
            settings.hold = _scalarToFunc(settings.hold);
        }
        init.call(this);

        function init() {
            if (!self.attr('id')) {
                self.attr('id', (''+Math.random()).substr(2));
            }
            if (settings.rate !== null) {
                self.attr('data-rate', settings.rate);
            }
            self.find('i:lt(' + self.attr('data-rate') + ')').removeClass('star-empty').addClass('star');

            if (!supportsTouch) {
                self.mouseover(function(e) {
                    if (settings.hold.call(self)) {
                        return;
                    }
                    var target = e.target;
                    if (target.tagName == 'I') {
                        target = $(target);
                        target.prevAll()
                            .removeClass('star star-empty').addClass('star-hover').end()
                            .removeClass('star star-empty').addClass('star-hover');
                        target.nextAll().removeClass('star star-hover').addClass('star-empty');
                    }
                }).mouseleave(function() {
                    if (settings.hold.call(self)) {
                        return;
                    }
                    update.call(self, self.attr('data-rate'));
                });
            }

            self.click(function(e) {
                if (settings.hold.call(self)) {
                    return;
                }
                var item = e.target;
                var root = this;
                while (item.tagName != 'I') {
                    if (item == root) {
                        return;
                    }
                }
                var prev_rate = self.attr('data-rate');
                var rate = 0;
                self.find('i')
                    .removeClass('star star-hover')
                    .addClass('star-empty')
                    .each(function() {
                        rate++;
                        $(this).removeClass('star-empty').addClass('star');
                        if (this == item) {
                            if (prev_rate != rate) {
                                self.attr('data-rate', rate);
                                settings.onUpdate(rate);
                            }
                            return false;
                        }
                });
                return false;
            });
            // if withClearAction is setted to true make available near the stars link-area for clear all stars (set rate to zero)
            if (settings.withClearAction) {
                var clear_link_id = 'clear-' + $(this).attr('id'),
                    clear_link = $('#' + clear_link_id);
                if (!clear_link.length) {
                    self.after('<a href="javascript:void(0);" class="inline-link rate-clear" id="'+clear_link_id+'" style="display:none;"><b><i>'+$_('clear')+'</b></i></a>');
                    clear_link = $('#' + clear_link_id);
                }
                clear_link.click(function() {
                    if (settings.hold.call(self)) {
                        return;
                    }
                    var prev_rate = self.attr('data-rate');
                    update.call(self, 0);
                    if (prev_rate !== 0) {
                        settings.onUpdate(0);
                    }
                });
                var timer_id;
                self.parent().mousemove(function() {
                    if (settings.hold.call(self)) {
                        return;
                    }
                    if (timer_id) {
                        clearTimeout(timer_id);
                    }
                    clear_link.show(0);
                }).mouseleave(function() {
                    timer_id = setTimeout(function() {
                        if (settings.hold.call(self)) {
                            return;
                        }
                        clear_link.hide(0);
                    }, 150);
                });
            }
            this.unbind('clear').bind('clear', function() {
                update.call(self, 0);
            });
            this.data('inited', true);

            self[0].addEventListener("touchend", function(event) {
                $(event.target).trigger("click");
            }, false);
        }

        function update(new_rate) {
            var rate = 0;
            this.find('i')
                .removeClass('star star-hover')
                .addClass('star-empty').each(function() {
                    if (rate == new_rate) {
                        return false;
                    }
                    rate++;
                    $(this).removeClass('star-empty').addClass('star');
                });
            this.attr('data-rate', new_rate);
        }

        function _scalarToFunc(scalar) {
            return function() {
                return scalar;
            };
        }

        return this;

    };
})(jQuery);