$(function () {

    function updateCart(data)
    {
        $(".cart-total").html(data.total);
        if (data.discount_numeric) {
            $(".cart-discount").closest('div.row').show();
        }
        $(".cart-discount").html('&minus; ' + data.discount);
    }

    $(".cart a.delete").click(function () {
        var row = $(this).closest('div.row');
        $.post('delete/', {id: row.data('id')}, function (response) {
            if (response.data.count == 0) {
                location.reload();
            }
            row.remove();
            updateCart(response.data);
        }, "json");
        return false;
    });

    $(".cart input.qty").change(function () {
        var that = $(this);
        if (that.val() > 0) {
            var row = that.closest('div.row');
            if (that.val()) {
                $.post('save/', {id: row.data('id'), quantity: that.val()}, function (response) {
                    row.find('.item-total').html(response.data.item_total);
                    if (response.data.q) {
                        that.val(response.data.q);
                    }
                    if (response.data.error) {
                        alert(response.data.error);
                    }
                    updateCart(response.data);
                }, "json");
            }
        } else {
            that.val(1);
        }
    });

    $(".cart .services input:checkbox").change(function () {
        var obj = $('select[name="service_variant[' + $(this).closest('div.row').data('id') + '][' + $(this).val() + ']"]');
        if (obj.length) {
            if ($(this).is(':checked')) {
                obj.removeAttr('disabled');
            } else {
                obj.attr('disabled', 'disabled');
            }
        }

        var div = $(this).closest('div');
        var row = $(this).closest('div.row');
        if ($(this).is(':checked')) {
           var parent_id = row.data('id')
           var data = {parent_id: parent_id, service_id: $(this).val()};
           var variants = $('select[name="service_variant[' + parent_id + '][' + $(this).val() + ']"]');
           if (variants.length) {
               data['service_variant_id'] = variants.val();
           }
           $.post('add/', data, function(response) {
               div.data('id', response.data.id);
               row.find('.item-total').html(response.data.item_total);
               updateCart(response.data);
           }, "json");
        } else {
           $.post('delete/', {id: div.data('id')}, function (response) {
               div.data('id', null);
               row.find('.item-total').html(response.data.item_total);
               updateCart(response.data);
           }, "json");
        }
    });

    $(".cart .services select").change(function () {
        var row = $(this).closest('div.row');
        $.post('save/', {id: $(this).closest('div').data('id'), 'service_variant_id': $(this).val()}, function (response) {
            row.find('.item-total').html(response.data.item_total);
            updateCart(response.data);
        }, "json");
    });

    $("#cancel-affiliate").click(function () {
        $(this).closest('form').append('<input type="hidden" name="use_affiliate" value="0">').submit();
        return false;
    })

    $("div.addtocart input:button").click(function () {
        $.post($(this).data('url'), {product_id: $(this).data('product_id')}, function (response) {
            if (response.status == 'ok') {
                var cart_total = $(".cart-total");
                $("#cart-content").parent().load(location.href, function () {
                    cart_total.closest('#cart').removeClass('empty');
                    cart_total.html(response.data.total);
                });
            }
        }, 'json');
        return false;
    });
});