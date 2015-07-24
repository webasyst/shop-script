// Product Class
var Product = ( function($) {

    function Product(form, options) {

        this.form = $(form);
        this.add2cart = this.form.find(".add2cart");
        this.button = this.add2cart.find("input[type=submit]");

        for (var k in options) {
            this[k] = options[k];
        }

        this.volume = 1;
        this.$price = this.add2cart.find(".price");
        this.price = parseFloat( this.$price.data("price") );

        var self = this;

        // add to cart block: services
        this.form.find(".services input[type=checkbox]").click(function () {
            var obj = $('select[name="service_variant[' + $(this).val() + ']"]');
            if (obj.length) {
                if ($(this).is(':checked')) {
                    obj.removeAttr('disabled');
                } else {
                    obj.attr('disabled', 'disabled');
                }
            }
            self.cartButtonVisibility(true);
            self.updatePrice();
        });

        this.form.find(".services .service-variants").on('change', function () {
            self.cartButtonVisibility(true);
            self.updatePrice();
        });

        this.form.find('.inline-select a').click(function () {
            var d = $(this).closest('.inline-select');
            d.find('a.selected').removeClass('selected');
            $(this).addClass('selected');
            d.find('.sku-feature').val( $(this).data('value') ).change();
            return false;
        });

        this.form.find(".skus input[type=radio]").click(function () {
            if ($(this).data('image-id')) {
                //$("#product-image-" + $(this).data('image-id')).click();
            }
            if ($(this).data('disabled')) {
                self.button.attr('disabled', 'disabled');
            } else {
                self.button.removeAttr('disabled');
            }
            var sku_id = $(this).val();
            self.updateSkuServices(sku_id);
            self.cartButtonVisibility(true);
            self.updatePrice();
        });

        $("#product-skus input[type=radio]:checked").click();

        this.form.find(".sku-feature").change(function () {
            var key = "";
            self.form.find(".sku-feature").each(function () {
                key += $(this).data('feature-id') + ':' + $(this).val() + ';';
            });
            var sku = self.features[key];
            if (sku) {
                if (sku.image_id) {
                    //$("#product-image-" + sku.image_id).click();
                }
                self.updateSkuServices(sku.id);
                if (sku.available) {
                    self.button.removeAttr('disabled');
                } else {
                    self.form.find("div.stocks div").hide();
                    self.form.find(".sku-no-stock").show();
                    self.button.attr('disabled', 'disabled');
                }
                self.add2cart.find(".price").data('price', sku.price);
                self.updatePrice(sku.price, sku.compare_price);
            } else {
                self.form.find("div.stocks div").hide();
                self.form.find(".sku-no-stock").show();
                self.button.attr('disabled', 'disabled');
                self.add2cart.find(".compare-at-price").hide();
                self.add2cart.find(".price").empty();
            }
            self.cartButtonVisibility(true);
        });
        this.form.find(".sku-feature:first").change();

        if (!this.form.find(".skus input:radio:checked").length) {
            this.form.find(".skus input:radio:enabled:first").attr('checked', 'checked');
        }

        this.form.submit( function () {
            var f = $(this),
                $button = self.add2cart.find(".submit-wrapper");

            // Show refreshing icon over button
            if (typeof toggleRefreshIcon === "function") {
                toggleRefreshIcon($button, "show");
            }

            $.post(f.attr('action') + '?html=1', f.serialize(), function (response) {
                if (response.status == 'ok') {
                    self.cartButtonVisibility(false);

                    if (f.data('cart')) {
                        $("#page-content").load(location.href, function () {
                            $("#dialog").hide().find('.cart').empty();
                        });
                    }

                    if (response.data.error) {
                        alert(response.data.error);
                    }

                    // Update Cart Counter at Header Place
                    if (typeof updateHeaderCartCount === "function") {
                        updateHeaderCartCount( response );
                    }

                    // restore button
                    if (typeof toggleRefreshIcon === "function") {
                        toggleRefreshIcon($button, "hide");
                    }

                } else if (response.status == 'fail') {
                    alert(response.errors);
                }
            }, "json");

            return false;
        });

        // Click on "+" button
        this.form.find(".quantity-wrapper .increase-volume").on("click", function() {
            self.changeVolume("positive");
            return false;
        });

        // Click on "-" button
        this.form.find(".quantity-wrapper .decrease-volume").on("click", function() {
            self.changeVolume("negative");
            return false;
        });

        // Change volume field
        this.form.find("#product-quantity-field").on("change", function() {
            var new_volume = parseFloat( $(this).val() );
            // AntiWord at Field
            if (new_volume) {
                $(this).val(new_volume);
                self.changeVolume(new_volume);
            } else {
                $(this).val( self.volume );
            }
            return false;
        });
    }

    // Change Volume
    Product.prototype.changeVolume = function( type ) {
        var $volume_input = $("#product-quantity-field"),
            current_val = parseInt( $volume_input.val() ),
            input_max_data = parseInt($volume_input.data("max-quantity")),
            max_val = ( isNaN(input_max_data) || input_max_data === 0 ) ? Infinity : input_max_data,
            new_val;

        // If click "+" button
        if (type === "positive") {
            if (this.volume < max_val) {
                new_val = this.volume + 1;
            }

            // If click "-" button
        } else if (type === "negative") {
            if (this.volume > 1) {
                new_val = this.volume - 1;
            }

            // If manual input at field
        } else if ( type > 0 && type !== this.volume ) {
            if (current_val <= 0) {
                if ( this.volume > 1 ) {
                    new_val = 1;
                }

            } else if (current_val > max_val) {
                if ( this.volume != max_val ) {
                    new_val = max_val;
                }

            } else {
                new_val = current_val;
            }
        }

        // Set product data
        if (new_val) {
            this.volume = new_val;

            // Set new value
            $volume_input.val(new_val);

            // Update Price
            this.updatePrice();
        }
    };

    // Replace price to site format
    Product.prototype.currencyFormat = function (number, no_html) {
        // Format a number with grouped thousands
        //
        // +   original by: Jonas Raoni Soares Silva (http://www.jsfromhell.com)
        // +   improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
        // +	 bugfix by: Michael White (http://crestidg.com)

        var i, j, kw, kd, km;
        var decimals = this.currency.frac_digits;
        var dec_point = this.currency.decimal_point;
        var thousands_sep = this.currency.thousands_sep;

        // input sanitation & defaults
        if( isNaN(decimals = Math.abs(decimals)) ){
            decimals = 2;
        }
        if( dec_point == undefined ){
            dec_point = ",";
        }
        if( thousands_sep == undefined ){
            thousands_sep = ".";
        }

        i = parseInt(number = (+number || 0).toFixed(decimals)) + "";

        if( (j = i.length) > 3 ){
            j = j % 3;
        } else{
            j = 0;
        }

        km = (j ? i.substr(0, j) + thousands_sep : "");
        kw = i.substr(j).replace(/(\d{3})(?=\d)/g, "$1" + thousands_sep);
        //kd = (decimals ? dec_point + Math.abs(number - i).toFixed(decimals).slice(2) : "");
        kd = (decimals && (number - i) ? dec_point + Math.abs(number - i).toFixed(decimals).replace(/-/, 0).slice(2) : "");


        var number = km + kw + kd;
        var s = no_html ? this.currency.sign : this.currency.sign_html;
        if (!this.currency.sign_position) {
            return s + this.currency.sign_delim + number;
        } else {
            return number + this.currency.sign_delim + s;
        }
    };

    Product.prototype.serviceVariantHtml= function (id, name, price) {
        return $('<option data-price="' + price + '" value="' + id + '"></option>').text(name + ' (+' + this.currencyFormat(price, 1) + ')');
    };

    Product.prototype.updateSkuServices = function (sku_id) {
        this.form.find("div.stocks div").hide();
        this.form.find(".sku-" + sku_id + "-stock").show();
        for (var service_id in this.services[sku_id]) {
            var v = this.services[sku_id][service_id];
            if (v === false) {
                this.form.find(".service-" + service_id).hide().find('input,select').attr('disabled', 'disabled').removeAttr('checked');
            } else {
                this.form.find(".service-" + service_id).show().find('input').removeAttr('disabled');
                if (typeof (v) == 'string') {
                    this.form.find(".service-" + service_id + ' .service-price').html(this.currencyFormat(v));
                    this.form.find(".service-" + service_id + ' input').data('price', v);
                } else {
                    var select = this.form.find(".service-" + service_id + ' .service-variants');
                    var selected_variant_id = select.val();
                    for (var variant_id in v) {
                        var obj = select.find('option[value=' + variant_id + ']');
                        if (v[variant_id] === false) {
                            obj.hide();
                            if (obj.attr('value') == selected_variant_id) {
                                selected_variant_id = false;
                            }
                        } else {
                            if (!selected_variant_id) {
                                selected_variant_id = variant_id;
                            }
                            obj.replaceWith(this.serviceVariantHtml(variant_id, v[variant_id][0], v[variant_id][1]));
                        }
                    }
                    this.form.find(".service-" + service_id + ' .service-variants').val(selected_variant_id);
                }
            }
        }
    };

    Product.prototype.updatePrice = function (price, compare_price) {
        var self = this,
            $compare = this.add2cart.find(".compare-at-price");

        // Price for One item
        if (price === undefined) {
            var input_checked = this.form.find(".skus input:radio:checked");

            if (input_checked.length) {
                price = parseFloat(input_checked.data('price'));
            } else {
                price = this.price;
            }
        } else {
            self.price = price;
        }

        // Increase price * volume
        price = price * this.volume;

        // Compare Price
        if ($compare.length) {
            compare_price = $compare.data("compare-price") * this.volume;
        }

        // Adding price for service
        this.form.find(".services input:checked").each(function () {
            var s = $(this).val(),
                service_price;

            if (self.form.find('.service-' + s + '  .service-variants').length) {
                service_price = parseFloat( self.form.find('.service-' + s + '  .service-variants :selected').data('price') );
            } else {
                service_price = parseFloat( $(this).data('price') );
            }

            price += service_price;
            if ($compare.length) {
                compare_price += service_price;
            }
        });

        // Render Price
        this.add2cart.find(".price").html( this.currencyFormat(price) );
        if ($compare.length) {
            $compare.html(this.currencyFormat(compare_price));
        }
    };

    Product.prototype.cartButtonVisibility = function(visible) {
        //toggles "Add to cart" / "%s is now in your shopping cart" visibility status
        if (visible) {
            this.add2cart.find(".add-form-wrapper").show();
            this.add2cart.find('.added2cart').hide();
        } else {
            this.add2cart.find(".add-form-wrapper").hide();
            this.add2cart.find('.added2cart').show();
        }
    };

    return Product
})(jQuery);

// Change Photo on Click at Product Page
( function($) {

    var bindEvents = function() {

        $(".swipebox").swipebox({
            useSVG: false,
            hideBarsOnMobile: false,
            afterOpen: function() {
                console.log(this);
            }
        });

        $(document).on( "click", "#product-core-image a", function(e) {
            e.preventDefault();
            showSwipe();
            return false;
        });
    };

    // Show Swipe
    var showSwipe = function() {
        var $gallery = $("#product-gallery");
        var images = [];
        if ( $gallery.find("a").length) {
            var k = $gallery.find(".selected").prevAll('.image').length;
            $gallery.find(".image").each(function () {
                images.push({href: $(this).find('a').attr('href')});
            });
            if (k) {
                images = images.slice(k).concat(images.slice(0, k));
            }
        } else {
            images.push({href: $('#product-core-image a:first').attr('href')||''});
        }
        $.swipebox(images, {
            useSVG: false,
            hideBarsOnMobile: false,
            afterOpen: function() {
                console.log(this);
            }
        });
    };

    var getImageHref = function($preview_image, $main_image) {
        var size = $main_image.attr('src').replace(/^.*\/[^\/]+\.(.*)\.[^\.]*$/, '$1'),
            src = $preview_image.attr('src').replace(/^(.*\/[^\/]+\.)(.*)(\.[^\.]*)$/, '$1' + size + '$3');

        return src;
    };

    $(document).ready( function() {
        bindEvents();
    });

})(jQuery);

// Tabs
( function($) {

    var storage = {
        getTabWrapper: function() {
            return $(".tab-list-wrapper");
        },
        getTabList: function() {
            return this.getTabWrapper().find(".tab-list");
        }
    };

    var bindEvents = function() {
        $(document).on( "click", ".tab-list-wrapper .tab-item", function() {
            var contentID = $(this).data("content-id");
            if (contentID) {
                showTabContent( $(this), contentID );
                return false;
            }
        });

        if (UserTouch) {
            var $tab_list = document.querySelector(".tab-list-wrapper .tab-list");
            // Move on Tab list
            $tab_list.addEventListener("touchmove", onTouchMove, false);
            $tab_list.addEventListener("touchend", onTouchEnd, false);
        }
    };

    var onTouchMove = function() {
        var $wrapper = storage.getTabWrapper(),
            $list = storage.getTabList(),
            touch_horizontal_offset = UserTouch.offsetDelta.x,
            current_left_offset = $list.data("horizontal-offset") || 0,
            horizontal_offset,
            delta;

        // if tab list width > window width
        delta = parseInt( $list.width() - $wrapper.width() );
        if ( touch_horizontal_offset && delta > 0 ) {

            horizontal_offset = touch_horizontal_offset + current_left_offset;

            if (horizontal_offset >= 0) {
                horizontal_offset = 0;

            } else if ( Math.abs(horizontal_offset) >= delta ) {
                horizontal_offset = -delta;
            }

            $list
                .data("moving-offset", horizontal_offset)
                .css({
                    "-webkit-transform": "translateX(" + horizontal_offset + "px)"
                })
            ;
        }
    };

    var onTouchEnd = function() {
        var $list = storage.getTabList(),
            horizontal_offset = $list.data("moving-offset") || 0;

        $list.data("horizontal-offset", horizontal_offset);
    };

    var showTabContent = function($tab_link, content_id ) {
        var $content = $("#tab-content-"+content_id),
            $tabs_wrapper = $(".tab-list-wrapper"),
            $tabs = $tabs_wrapper.find(".tab-item"),
            $content_wrapper = $(".tab-content-wrapper"),
            $content_item = $content_wrapper.find(".tab-content-item"),
            activeTabClass = "active-tab-item",
            activeContentClass = "is-shown";

        // hide old content
        $content_item.removeClass(activeContentClass);

        // show current content
        $content.addClass(activeContentClass);

        // unmark old tab
        $tabs.removeClass(activeTabClass);

        // mark current tab
        $tab_link.addClass(activeTabClass);
    };

    $(document).ready( function() {
        bindEvents();
    });

})(jQuery);

