function currency_format(number) {
    // Format a number with grouped thousands
    //
    // +   original by: Jonas Raoni Soares Silva (http://www.jsfromhell.com)
    // +   improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +	 bugfix by: Michael White (http://crestidg.com)

    var i, j, kw, kd, km;
    var decimals = currency.frac_digits;
    var dec_point = currency.decimal_point;
    var thousands_sep = currency.thousands_sep;

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
    if (!currency.sign_position) {
        return currency.sign + currency.sign_delim + number;
    } else {
        return number + currency.sign_delim + currency.sign;
    }
}

$(function () {

    var service_variant_html = function (id, name, price) {
        return '<option data-price="' + price + '" id="service-variant-' + id + '" value="' + id + '">' + name + ' (+' + currency_format(price) + ')</option>';
    }

    var update_sku_services = function (sku_id) {
        $("div.stocks div").hide();
        $("#sku-" + sku_id + "-stock").show();
        for (var service_id in sku_services[sku_id]) {
            var v = sku_services[sku_id][service_id];
            if (v === false) {
                $("#service-" + service_id).hide().find('input,select').attr('disabled', 'disabled').removeAttr('checked');
            } else {
                $("#service-" + service_id).show().find('input').removeAttr('disabled');
                if (typeof (v) == 'string') {
                    $("#service-" + service_id + ' .service-price').html(currency_format(v));
                    $("#service-" + service_id + ' input').data('price', v);
                } else {
                    var selected_variant_id = $("#service-" + service_id + ' .service-variants').data('variant-id');
                    for (var variant_id in v) {
                        var obj = $("#service-variant-" + variant_id);
                        if (v[variant_id] === false) {
                            obj.hide();
                        } else {
                            if (!selected_variant_id) {
                                selected_variant_id = variant_id;
                            }
                            obj.replaceWith(service_variant_html(variant_id, v[variant_id][0], v[variant_id][1]));
                        }
                    }
                    $("#service-" + service_id + ' .service-variants').val(selected_variant_id);
                }
            }
        }
    }

    function cart_button_visibility(visible)
    {
        //toggles "Add to cart" / "%s is now in your shopping cart" visibility status
        if (visible)
        {
            $('.add2cart .compare-at-price').show();
            $('.add2cart input[type="submit"]').show();
            $('.add2cart .price').show();
            $('.add2cart .qty').show();
            $('.add2cart span.added2cart').hide();
        }
        else
        {
            $('.add2cart .compare-at-price').hide();
            $('.add2cart input[type="submit"]').hide();
            $('.add2cart .price').hide();
            $('.add2cart .qty').hide();
            $('.add2cart span.added2cart').show();
        }
    }

    $("#product-skus input[type=radio]").click(function () {
        if ($(this).data('image-id')) {
            $("#product-image-" + $(this).data('image-id')).click();
        }
        if ($(this).data('disabled')) {
            $(".add2cart input[type=submit]").attr('disabled', 'disabled');
        } else {
            $(".add2cart input[type=submit]").removeAttr('disabled');
        }
        var sku_id = $(this).val();
        update_sku_services(sku_id);
        cart_button_visibility(true);
        update_price();
    });
    $("#product-skus input[type=radio]:checked").click();

    $("select.sku-feature").change(function () {
        var key = "";
        $("select.sku-feature").each(function () {
            key += $(this).data('feature-id') + ':' + $(this).val() + ';';
        });
        var sku = sku_features[key];
        if (sku) {
            if (sku.image_id) {
                $("#product-image-" + sku.image_id).click();
            }
            update_sku_services(sku.id);
            if (sku.available) {
                $(".add2cart input[type=submit]").removeAttr('disabled');
            } else {
                $(".add2cart input[type=submit]").attr('disabled', 'disabled');
            }
            $(".add2cart .price").data('price', sku.price);
        } else {
            $("div.stocks div").hide();
            $("#sku-no-stock").show();
            $(".add2cart input[type=submit]").attr('disabled', 'disabled');
        }
        cart_button_visibility(true);
        update_price(sku.price);
    });
    $("select.sku-feature:first").change();

    function update_price(price)
    {
        if (price === undefined) {
            if ($("#product-skus input:radio:checked").length) {
                var price = parseFloat($("#product-skus input:radio:checked").data('price'));
                var compare_price = parseFloat($("#product-skus input:radio:checked").data('compare-price'));
                if (compare_price) {
                    $(".add2cart .compare-at-price").html(currency_format(compare_price)).show();
                } else {
                    $(".add2cart .compare-at-price").hide();
                }
            } else {
                var price = parseFloat($(".add2cart .price").data('price'));
            }
        }
        $(".cart .services input:checked").each(function () {
            var s = $(this).val();
            if ($('#service-' + s + '  .service-variants').length) {
                price += parseFloat($('#service-' + s + '  .service-variants :selected').data('price'));
            } else {
                price += parseFloat($(this).data('price'));
            }
        });
        $(".add2cart .price").html(currency_format(price));
    }

    if (!$("#product-skus input:radio:checked").length) {
        $("#product-skus input:radio:enabled:first").attr('checked', 'checked');
    }

    // product images
    $("#product-gallery a").click(function () {
        $('.gallery .image').removeClass('selected');
        $(this).parent().addClass('selected');

        $("#product-core-image div.loading").remove();
        $("#product-core-image").append('<div class="loading" style="position: absolute; left: 192px; top: ' + (($("#product-image").height() - 16)/2) + 'px"><i class="icon16 loading"></i></div>');
        
        var img = $(this).find('img');
        var size = $("#product-image").attr('src').replace(/^.*\/[0-9]+\.(.*)\..*$/, '$1');
        var src = img.attr('src').replace(/^(.*\/[0-9]+\.)(.*)(\..*)$/, '$1' + size + '$3');
        $('<img>').attr('src', src).load(function () {
            $("#product-image").attr('src', src);
            $("#product-core-image div.loading").remove();
        }).each(function() {
            //ensure image load is fired. Fixes opera loading bug
            if (this.complete) { $(this).trigger("load"); }
        });
        var size = $("#product-image").parent().attr('href').replace(/^.*\/[0-9]+\.(.*)\..*$/, '$1');
        var href = img.attr('src').replace(/^(.*\/[0-9]+\.)(.*)(\..*)$/, '$1' + size + '$3');
        $("#product-image").parent().attr('href', href);
        if ($(".facebook-photo-zoom").is(':visible')) {
            $("#product-image").parent().click();
        }
        return false;
    });

    $("#product-core-image a").click(function () {
        $('<img>').attr('src', $(this).attr('href')).load(function () {
            var a = $("#facebook-photo-zoom");
            if (a.find('img').length) {
                a.find('img').attr('src', $(this).attr('src'));
            } else {
                a.append($(this));
            }
            FB.Canvas.getPageInfo(function (info) {
                $(".facebook-photo-zoom").css('top', (info.scrollTop - 20) + 'px');
                $(".facebook-photo-zoom").show();
            })
        }).each(function() {
                //ensure image load is fired. Fixes opera loading bug
                if (this.complete) { $(this).trigger("load"); }
        });
        return false;
    });

    $(".facebook-photo-zoom,#facebook-photo-zoom").click(function () {
        $(".facebook-photo-zoom").hide();
        $(this).children('img').remove();
        return false;
    });

    // add to cart block: services
    $(".cart .services input[type=checkbox]").click(function () {
    
        var obj = $('select[name="service_variant[' + $(this).val() + ']"]');
        if (obj.length) {
            if ($(this).is(':checked')) {
                obj.removeAttr('disabled');
            } else {
                obj.attr('disabled', 'disabled');
            }
        }
        cart_button_visibility(true);
        update_price();
    });

    $(".cart .services .service-variants").on('change', function () {
        cart_button_visibility(true);
        update_price();
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

    $("#cart-form").submit(function () {
        var f = $(this);
        $.post(f.attr('action'), f.serialize(), function (response) {
            if (response.status == 'ok') {
                var cart_total = $(".cart-total");
                var cart_div = f.closest('.cart');
                
                cart_total.closest('#cart').removeClass('empty');
                cart_total.html(response.data.total);

                cart_button_visibility(false);
                                
            } else if (response.status == 'fail') {
                alert(response.errors);
            }
        }, "json");
        return false;
    });
});