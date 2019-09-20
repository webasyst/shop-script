( function($) {

    var AffiliatePage = ( function($) {

        AffiliatePage = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];

            // CONST
            that.locales = options["locales"];
            that.urls = options["urls"];

            // DYNAMIC VARS

            // INIT
            that.init();
        };

        AffiliatePage.prototype.init = function() {
            var that = this;

            var $sidebar_list = that.$wrapper.find("#affiliate-plugins"),
                $content_1 = that.$wrapper.find("#affiliate-settings"),
                $content_2 = that.$wrapper.find("#affiliate-settings-plugin"),
                $form = that.$wrapper.find('#s-settings-affiliate-form'),
                $submit_button = $form.find(".js-submit-button");

            var active_class = "selected";

            var is_locked = false;

            $sidebar_list.on("click", "a", function(event) {
                event.preventDefault();

                var $this = $(this);
                var $parent = $this.closest('li');

                if ($parent.hasClass(active_class)) {
                    return false;
                }

                $sidebar_list.find("li." + active_class).removeClass(active_class);
                $parent.addClass(active_class);

                $.shop.trace('url',[$content_2.length, $this.data('url')]);

                var url = $this.data("url");
                if (url) {
                    $content_1.hide();
                    $content_2.html('<i class="icon16 loading"></i>').show().load(url);
                } else {
                    $content_1.show();
                    $content_2.hide();
                }
            });

            $sidebar_list.find("li.active_class a").trigger("click");

            // Global on/off toggle for the whole form
            var ibutton = $('#s-toggle-status').iButton( { labelOn : "", labelOff : "", className: 'mini' } ).change(function() {
                var self = $(this);
                var enabled = self.is(':checked');
                if (enabled) {
                    self.closest('.field-group').siblings().show(200);
                } else {
                    self.closest('.field-group').siblings().hide(200);
                }
                $.post(
                    '?module=marketingAffiliateEnable',
                    { enable: enabled ? '1' : '0' },
                    function (r) {
                        if (r.status === 'ok') {
                            if (enabled) {
                                $('.s-exclamation').hide();
                            } else {
                                $('.s-exclamation').show();
                            }
                        }
                    });
            });

            // Submit via XHR
            $form.on("submit", function(event) {
                event.preventDefault();

                if (is_locked) { return false; }
                is_locked= true;

                var $loading = $("<span class=\"s-msg-after-button\"><i class=\"icon16 loading\"></i></span>");

                $submit_button.attr("disabled", false).after($loading);

                $.post($form.attr('action'), $form.serialize(), "json")
                    .fail( function() {
                        $loading.remove();
                        $submit_button.attr("disabled", false);
                    })
                    .done( function() {
                        $.shop.marketing.content.reload();
                    });
            });

            // Controller for program applicability
            ( function() {
                var radios = $(':input:radio[name="conf[affiliate_product_types]"]');
                var radio_all = radios.first();
                var radio_selected = radios.last();
                var product_types_ul = radio_selected.closest(".value").find('.js-hidden-list');

                // Show/hide list of product types when radios change
                radios.change(function() {
                    if (radio_selected.is(':checked')) {
                        product_types_ul.show();
                    } else {
                        product_types_ul.hide();
                    }
                }).change();

                // Change value of 'Selected only' radio depending on list of checked checkboxes
                var h;
                product_types_ul.on('change', ':checkbox', h = function() {
                    var val = [];
                    product_types_ul.find(':checkbox').each(function() {
                        var self = $(this);
                        if (self.is(':checked')) {
                            val.push($(this).val());
                        }
                    });
                    radio_selected.attr('value', val.join(','));
                });
                h();
            })();
        };

        return AffiliatePage;

    })($);

    $.shop.marketing.init.affiliatePage = function(options) {
        return new AffiliatePage(options);
    };

})(jQuery);