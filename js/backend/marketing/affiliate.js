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

            var $sidebar_list = that.$wrapper.find("#js-affiliate-plugins"),
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
                    $content_2.html('<i class="fas fa-spinner wa-animation-spin loading"></i>').show().load(url);
                } else {
                    $content_1.show();
                    $content_2.hide();
                }
            });

            $sidebar_list.find("li.active_class a").trigger("click");

            // Global on/off toggle for the whole form
                $(".js-switch-affiliate").waSwitch({
                    ready: function (wa_switch) {
                        let $label = wa_switch.$wrapper.siblings('label');
                        wa_switch.$label = $label;
                        wa_switch.active_text = $label.data('active-text');
                        wa_switch.inactive_text = $label.data('inactive-text');
                    },
                    change: function(active, wa_switch) {
                        wa_switch.$label.text(active ? wa_switch.active_text : wa_switch.inactive_text);

                        if (active) {
                            wa_switch.$wrapper.closest('.fields-group').siblings().show(200);
                        } else {
                            wa_switch.$wrapper.closest('.fields-group').siblings().hide(200);
                        }
                        $.post('?module=marketingAffiliateEnable', { enable: active ? '1' : '0' }, function (r) {
                            if (r.status === 'ok') {
                                if (active) {
                                    $('.s-exclamation').hide();
                                } else {
                                    $('.s-exclamation').show();
                                }
                            }
                        });
                    }
                });

            // Submit via XHR
            $form.on("submit", function(event) {
                event.preventDefault();

                if (is_locked) { return false; }
                is_locked= true;

                var $loading = $('<span class="s-msg-after-button custom-mr-4 text-green"><i class="fas fa-check-circle"></i></span><span>' + $_("Saved") + '</span>').animate({ opacity: 0 }, 1000);

                $submit_button.attr("disabled", false).after($loading);

                $.post($form.attr('action'), $form.serialize(), "json")
                    .always( function() {
                        is_locked = false;
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

            formChanged($form);

            function formChanged($form) {
                const $submit = $form.find('[type="submit"]');
                const submitChanged = () => {
                    $submit.removeClass('green').addClass('yellow');
                };

                let is_changed = false;
                $(':input:not(:submit)', $form).on('input', function() {
                    if (is_changed) return true;

                    submitChanged();

                    is_changed = true;
                });

                $submit.on('click', function() {
                    $submit.removeClass('yellow').addClass('green');
                    is_changed = false;
                });
            }
        };

        return AffiliatePage;

    })($);

    $.shop.marketing.init.affiliatePage = function(options) {
        return new AffiliatePage(options);
    };

})(jQuery);
