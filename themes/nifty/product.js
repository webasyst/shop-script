function Product(form, options) {
    this.form = $(form);
    this.add2cart = this.form.find(".add2cart");
    this.button = this.add2cart.find("input[type=submit]");
    for (var k in options) {
        this[k] = options[k];
    }
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

    this.form.find(".skus input[type=radio]").click(function () {
        if ($(this).data('image-id')) {
            $("#product-image-" + $(this).data('image-id')).click();
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
    var $initial_cb = this.form.find(".skus input[type=radio]:checked:not(:disabled)");
    if (!$initial_cb.length) {
        $initial_cb = this.form.find(".skus input[type=radio]:not(:disabled):first").prop('checked', true).click();
    }
    $initial_cb.click();

    this.form.find("select.sku-feature").change(function () {
        var key = "";
        self.form.find("select.sku-feature").each(function () {
            key += $(this).data('feature-id') + ':' + $(this).val() + ';';
        });
        var sku = self.features[key];
        if (sku) {
            if (sku.image_id) {
                $("#product-image-" + sku.image_id).click();
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
            self.add2cart(".compare-at-price").hide();
            self.add2cart(".price").empty();
        }
        self.cartButtonVisibility(true);
    });
    this.form.find("select.sku-feature:first").change();

    if (!this.form.find(".skus input:radio:checked").length) {
        this.form.find(".skus input:radio:enabled:first").attr('checked', 'checked');
    }

    this.form.submit(function () {
        var f = $(this);
        $.post(f.attr('action') + '?html=1', f.serialize(), function (response) {
            if (response.status == 'ok') {
                var cart_total = $(".cart-total");
                var cart_div = f.closest('.cart');

                cart_total.closest('#cart').removeClass('empty');
                cart_total.html(response.data.total);

                self.cartButtonVisibility(false);

                if (f.data('cart')) {
                    $("#cart-content").parent().load(location.href, function () {
                        $("#dialog").hide().find('.cart').empty();
                    });
                }
                if (response.data.error) {
                    alert(response.data.error);
                }
            } else if (response.status == 'fail') {
                alert(response.errors);
            }
        }, "json");
        return false;
    });

    this.compare_price = options["compare_price"];
}

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
            if (typeof (v) === 'string' || typeof (v) === 'number') {
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
    var self = this;

    if (price === undefined) {
        var input_checked = this.form.find(".skus input:radio:checked");
        if (input_checked.length) {
            price = parseFloat(input_checked.data('price'));
            compare_price = parseFloat(input_checked.data('compare-price'));
        } else {
            price = parseFloat(this.add2cart.find(".price").data('price'));
            compare_price = this.compare_price;
        }
    }

    var service_price = 0;

    this.form.find(".services input:checked").each(function () {
        var s = $(this).val();
        if (self.form.find('.service-' + s + '  .service-variants').length) {
            service_price += parseFloat(self.form.find('.service-' + s + '  .service-variants :selected').data('price'));
        } else {
            service_price += parseFloat($(this).data('price'));
        }
    });

    this.add2cart.find(".price").html(this.currencyFormat(price + service_price));

    if (compare_price) {
        if (!this.add2cart.find(".compare-at-price").length) {
            this.add2cart.prepend('<span class="compare-at-price nowrap"></span>');
        }
        this.add2cart.find(".compare-at-price").html(this.currencyFormat(compare_price + service_price)).show();
    } else {
        this.add2cart.find(".compare-at-price").hide().html("");
    }

    this.compare_price = (compare_price ? compare_price : 0);
};

Product.prototype.cartButtonVisibility = function (visible) {
    //toggles "Add to cart" / "%s is now in your shopping cart" visibility status
    if (visible) {
        if (this.compare_price > 0) {
            this.add2cart.find('.compare-at-price').show();
        }
        this.add2cart.find('input[type="submit"]').show();
        this.add2cart.find('.price').show();
        this.add2cart.find('.qty').show();
        this.add2cart.find('span.added2cart').hide();
    } else {
        this.add2cart.find('.compare-at-price').hide();
        this.add2cart.find('input[type="submit"]').hide();
        this.add2cart.find('.price').hide();
        this.add2cart.find('.qty').hide();
        this.add2cart.find('span.added2cart').show();
    }
}

$(function () {

    if ($("a.easyzoom").length) {
        $("a.easyzoom").easyZoom({parent: '#product-core-image'});
    }

    // product images
    $("#product-gallery a").not("#product-image-video").click(function () {
        $('#product-core-image').show();
        $('#video-container').hide();

        $('.gallery .image').removeClass('selected');
        $(this).parent().addClass('selected');

        $("#product-core-image div.loading").remove();
        $("#product-core-image").append('<div class="loading" style="position: absolute; left: 192px; top: ' + (($("#product-image").height() - 16)/2) + 'px"><i class="icon16 loading"></i></div>');

        var img = $(this).find('img');
        var size = $("#product-image").attr('src').replace(/^.*\/[^\/]+\.(.*)\.[^\.]*$/, '$1');
        var src = img.attr('src').replace(/^(.*\/[^\/]+\.)(.*)(\.[^\.]*)$/, '$1' + size + '$3');
        $('<img>').attr('src', src).load(function () {
            $("#product-image").attr('src', src);
            $("#product-core-image div.loading").remove();
        }).each(function() {
                //ensure image load is fired. Fixes opera loading bug
                if (this.complete) { $(this).trigger("load"); }
            });
        var size = $("#product-image").parent().attr('href').replace(/^.*\/[^\/]+\.(.*)\.[^\.]*$/, '$1');
        var href = img.attr('src').replace(/^(.*\/[^\/]+\.)(.*)(\.[^\.]*)$/, '$1' + size + '$3');
        $("#product-image").parent().attr('href', href);
        $("a.easyzoom").easyZoom({parent: '#product-core-image'});
        return false;
    });

    // product image video
    $('#product-image-video').click(function () {
        $('#product-core-image').hide();
        $('#video-container').show();
        $('#product-gallery .image').removeClass('selected');
        $(this).parent().addClass('selected');
        return false;
    });

    // compare block
    $("a.compare-add").click(function () {
        var compare = $.cookie('shop_compare');
        if (compare) {
            compare += ',' + $(this).data('product');
        } else {
            compare = '' + $(this).data('product');
        }
        if (compare.split(',').length > 1) {
            var url = $("#compare-link").attr('href').replace(/compare\/.*$/, 'compare/' + compare + '/');
            $("#compare-link").attr('href', url).show().find('span.count').html(compare.split(',').length);
        }
        $.cookie('shop_compare', compare, { expires: 30, path: '/'});
        $(this).hide();
        $("a.compare-remove").show();
        return false;
    });
    $("a.compare-remove").click(function () {
        var compare = $.cookie('shop_compare');
        if (compare) {
            compare = compare.split(',');
        } else {
            compare = [];
        }
        var i = $.inArray($(this).data('product') + '', compare);
        if (i != -1) {
            compare.splice(i, 1)
        }
        $("#compare-link").hide();
        if (compare) {
            $.cookie('shop_compare', compare.join(','), { expires: 30, path: '/'});
        } else {
            $.cookie('shop_compare', null);
        }
        $(this).hide();
        $("a.compare-add").show();
        return false;
    });
});