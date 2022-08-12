( function($) {

    var Menu = ( function($) {

        Menu = function(options) {
            var that = this;

            // DOM
            that.$wrapper = $(options["html"]);

            // CONST
            that.animation_time = (typeof options["animation_time"] === "number" ? options["animation_time"] : 200);

            // VARS
            that.classes = {
                hide_class: "is-hidden"
            };
            that.states = {
                is_locked: false,
                is_visible: false
            };
            that.functions = {
                keyupWatcher: function(event) {
                    var escape_code = 27;
                    if (event.keyCode === escape_code) { that.hide(); }
                },
                clickWatcher: function(event) {
                    var is_target = that.$wrapper[0].contains(event.target);
                    if (!is_target) { that.hide(); }
                }
            }

            // INIT
            that.init();
        };

        Menu.prototype.init = function() {
            var that = this;

            that.$wrapper.addClass(that.classes.hide_class);

            // that.$wrapper.on("click", ".js-toggle-menu", function(event) {
            //     event.preventDefault();
            //     that.hide();
            // });

            that.$wrapper.on("mouseleave", function() {
                if (!that.states.is_locked) { that.hide(); }
            });

            that.$wrapper.on("click", ".js-group-toggle", function(event) {
                event.preventDefault();

                let $group = $(this).closest("li"),
                    active_class = "is-restricted";

                var show = $group.hasClass(active_class);
                if (show) {
                    $group.removeClass(active_class);
                } else {
                    $group.addClass(active_class);
                }
            });
        };

        Menu.prototype.events = function(use_events) {
            var that = this,
                $document = $(document);

            if (use_events) {
                setTimeout( function() {
                    $document
                        .on("keyup", that.functions.keyupWatcher)
                        .on("click", that.functions.clickWatcher);
                }, 0);
            } else {
                $document
                    .off("keyup", that.functions.keyupWatcher)
                    .off("click", that.functions.clickWatcher);
            }
        };

        Menu.prototype.show = function() {
            var that = this;

            var is_exist = $.contains(document, that.$wrapper[0]);
            if (!is_exist) {
                $("body").append(that.$wrapper);
                that.events(true);
            }

            if (!that.states.is_locked) {
                that.states.is_locked = true;
                setTimeout( function() {
                    that.$wrapper.removeClass(that.classes.hide_class);
                    setTimeout( function() {
                        that.states.is_locked = false;
                    }, that.animation_time);
                }, 10);
            }

            that.states.is_visible = true;
        };

        Menu.prototype.hide = function() {
            var that = this;

            if (!that.states.is_locked) {
                that.states.is_locked = true;

                that.$wrapper.addClass(that.classes.hide_class);
                setTimeout( function() {
                    $("<div />").append(that.$wrapper);
                    that.events(false);
                    that.states.is_visible = false;
                    that.states.is_locked = false;
                }, that.animation_time);
            }
        };

        return Menu;
    })($);

    $.wa2_main_menu = {
        menu: null,
        get: function() {
            return $.wa2_main_menu.menu;
        },
        init: function(options) {
            $.wa2_main_menu.menu = new Menu(options);
            return this.get();
        }
    };

})(jQuery);