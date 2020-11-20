// Product Class
var Product = ( function($) {

    Product = function(options) {
        var that = this;

        // DOM
        that.$form = options["$form"];
        that.add2cart = that.$form.find(".add2cart");
        that.$button = that.add2cart.find("input[type=submit]");
        that.$price = that.add2cart.find(".price");
        that.$comparePrice = that.add2cart.find(".compare-at-price");

        // VARS
        that.currency = ( options["currency"] || false );
        that.services = ( options["services"] || false );
        that.features = ( options["features"] || false );

        // DYNAMIC VARS
        that.volume = 1;
        that.price = parseFloat( this.$price.data("price") );
        that.compare_price = parseFloat( that.$comparePrice.data("compare-price") );

        // add to cart block: services
        this.$form.find(".services input[type=checkbox]").click(function () {
            var obj = $('select[name="service_variant[' + $(this).val() + ']"]');
            if (obj.length) {
                if ($(this).is(':checked')) {
                    obj.removeAttr('disabled');
                } else {
                    obj.attr('disabled', 'disabled');
                }
            }
            that.cartButtonVisibility(true);
            that.updatePrice();
        });

        this.$form.find(".services .service-variants").on('change', function () {
            that.cartButtonVisibility(true);
            that.updatePrice();
        });

        this.$form.find('.inline-select a').click(function () {
            var d = $(this).closest('.inline-select');
            d.find('a.selected').removeClass('selected');
            $(this).addClass('selected');
            d.find('.sku-feature').val( $(this).data('value') ).change();
            return false;
        });

        this.$form.find(".skus input[type=radio]").click(function () {
            that.onSkusClick( $(this) );
        });

        $("#product-skus input[type=radio]:checked").click();

        that.$form.find(".sku-feature").on("change", function () {
            that.onSkusChange( $(this) );
        });

        that.$form.find(".sku-feature:first").change();

        if (!this.$form.find(".skus input:radio:checked").length) {
            this.$form.find(".skus input:radio:enabled:first").attr('checked', 'checked');
        }

        this.$form.submit( function () {
            var f = $(this),
                $button = that.add2cart.find(".submit-wrapper");

            // Show refreshing icon over button
            if (typeof toggleRefreshIcon === "function") {
                toggleRefreshIcon($button, "show");
            }

            $.post(f.attr('action') + '?html=1', f.serialize(), function (response) {
                if (response.status == 'ok') {
                    that.cartButtonVisibility(false);

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
        this.$form.find(".quantity-wrapper .increase-volume").on("click", function() {
            that.changeVolume("positive");
            return false;
        });

        // Click on "-" button
        this.$form.find(".quantity-wrapper .decrease-volume").on("click", function() {
            that.changeVolume("negative");
            return false;
        });

        // Change volume field
        this.$form.find("#product-quantity-field").on("change", function() {
            var new_volume = parseFloat( $(this).val() );
            // AntiWord at Field
            if (new_volume) {
                $(this).val(new_volume);
                that.changeVolume(new_volume);
            } else {
                $(this).val( that.volume );
            }
            return false;
        });
    };

    Product.prototype.onSkusChange = function() {
        var that = this;

        // DOM
        var $form = that.$form,
            $button = that.$button;

        var key = getKey(),
            sku = that.features[key];

        if (sku) {
            //
            that.updateSkuServices(sku.id);
            //
            if (sku.image_id) {
                that.changeImage(sku.image_id);
            }
            //
            if (sku.available) {
                $button.removeAttr('disabled');
            } else {
                $form.find("div.s-stocks-wrapper div").hide();
                $form.find(".sku-no-stock").show();
                $button.attr('disabled', 'disabled');
            }
            //
            that.updatePrice(sku["price"], ( sku["compare_price"] || 0) );

        } else {
            //
            $form.find("div.s-stocks-wrapper div").hide();
            //
            $form.find(".sku-no-stock").show();
            //
            $button.attr('disabled', 'disabled');
            //
            that.add2cart.find(".compare-at-price").hide();
            //
            that.$price.empty();
        }

        //
        that.cartButtonVisibility(true);

        function getKey() {
            var result = "";

            $form.find(".sku-feature").each( function () {
                var $input = $(this);

                result += $input.data("feature-id") + ':' + $input.val() + ';';
            });

            return result;
        }
    };

    Product.prototype.onSkusClick = function( $link ) {
        var that = this,
            sku_id = $link.val(),
            price = $link.data("price"),
            compare_price = $link.data("compare-price");

        // DOM
        var $button = that.$button;

        var image_id = $link.data('image-id');
        if (image_id) {
            that.changeImage(image_id);
        }

        if ($link.data('disabled')) {
            $button.attr('disabled', 'disabled');
        } else {
            $button.removeAttr('disabled');
        }

        //
        that.updateSkuServices(sku_id);
        //
        that.cartButtonVisibility(true);
        //
        that.updatePrice(price, compare_price);
    };

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
        this.$form.find("div.stocks div").hide();
        this.$form.find(".sku-" + sku_id + "-stock").show();
        for (var service_id in this.services[sku_id]) {
            var v = this.services[sku_id][service_id];
            if (v === false) {
                this.$form.find(".service-" + service_id).hide().find('input,select').attr('disabled', 'disabled').removeAttr('checked');
            } else {
                this.$form.find(".service-" + service_id).show().find('input').removeAttr('disabled');
                if (typeof (v) === 'string' || typeof (v) === 'number') {
                    this.$form.find(".service-" + service_id + ' .service-price').html(this.currencyFormat(v));
                    this.$form.find(".service-" + service_id + ' input').data('price', v);
                } else {
                    var select = this.$form.find(".service-" + service_id + ' .service-variants');
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
                    this.$form.find(".service-" + service_id + ' .service-variants').val(selected_variant_id);
                }
            }
        }
    };

    Product.prototype.updatePrice = function(price, compare_price) {
        var that = this;

        // DOM
        var $form = that.$form,
            $price = that.$price,
            $compare = that.$comparePrice;

        // VARS
        var services_price = getServicePrice(),
            volume = that.volume,
            price_sum,
            compare_sum;

        //
        if (price || price === 0) {
            that.price = price;
            $price.data("price", price);
        } else {
            price = that.price;
        }

        //
        if (compare_price >= 0) {
            that.compare_price = compare_price;
            $compare.data("price", compare_price);
        } else {
            compare_price = that.compare_price;
        }

        //
        price_sum = (price + services_price) * volume;
        compare_sum = (compare_price + services_price) * volume;

        // Render Price
        $price.html( that.currencyFormat(price_sum) );
        $compare.html( that.currencyFormat(compare_sum) );

        // Render Compare
        if (compare_price > 0) {
            $compare.show();
        } else {
            $compare.hide();
        }

        //
        function getServicePrice() {
            // DOM
            var $checkedServices = $form.find(".services input:checked");

            // DYNAMIC VARS
            var services_price = 0;

            $checkedServices.each( function () {
                var $service = $(this),
                    service_value = $service.val(),
                    service_price = 0;

                var $serviceVariants = $form.find(".service-" + service_value + " .service-variants");

                if ($serviceVariants.length) {
                    service_price = parseFloat( $serviceVariants.find(":selected").data("price") );
                } else {
                    service_price = parseFloat( $service.data("price") );
                }

                services_price += service_price;
            });

            return services_price;
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

    Product.prototype.changeImage = function(image_id) {
        var that = this;

        if (image_id) {
            var $imageLink = $("#product-image-" + image_id);
            if ($imageLink.length) {
                var $link = $("#product-core-image a"),
                    $image = $link.find("img"),
                    image_uri = $imageLink[0].href;

                $link.attr("href", image_uri);
                $image.attr("src", image_uri);
            }
        }
    };

    return Product;

})(jQuery);

// Change Photo on Click at Product Page
( function($) {

    var bindEvents = function() {

        $(".swipebox").swipebox({
            useSVG: false,
            hideBarsOnMobile: false,
            hideBarsDelay: 0,
            afterOpen: function() {
                // console.log(this);
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
                // console.log(this);
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

