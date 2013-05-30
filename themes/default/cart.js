$(function () {

    function updateCart(data)
    {
        $(".cart-total").html(data.total);
        if (data.discount_numeric) {
            $(".cart-discount").closest('tr').show();
        }
        $(".cart-discount").html('&minus; ' + data.discount);
        $(".affiliate").hide();
    }

    // add to cart block: services
    $(".services input:checkbox").click(function () {
        var obj = $('select[name="service_variant[' + $(this).closest('tr').data('id') + '][' + $(this).val() + ']"]');
        if (obj.length) {
            if ($(this).is(':checked')) {
                obj.removeAttr('disabled');
            } else {
                obj.attr('disabled', 'disabled');
            }
        }
    });


    $(".cart a.delete").click(function () {
        var tr = $(this).closest('tr');
        $.post('delete/', {id: tr.data('id')}, function (response) {
            tr.remove();
            updateCart(response.data);
        }, "json");
    });

    $(".cart input.qty").change(function () {
        var that = $(this);
        if (that.val() > 0) {
            var tr = that.closest('tr');
            if (that.val()) {
                $.post('save/', {id: tr.data('id'), quantity: that.val()}, function (response) {
                    tr.find('.item-total').html(response.data.item_total);
                    if (response.data.q) {
                        that.val(response.data.q);
                    }
                    if (response.data.error) {
                        alert(response.data.error);
                    } else {
                        that.removeClass('error');
                    }
                    updateCart(response.data);
                }, "json");
            }
        } else {
            that.val(1);
        }
    });

    $(".cart .services input:checkbox").change(function () {
        var div = $(this).closest('div');
        var tr = $(this).closest('tr');
        if ($(this).is(':checked')) {
           var parent_id = $(this).closest('tr').data('id')
           var data = {parent_id: parent_id, service_id: $(this).val()};
           var variants = $('select[name="service_variant[' + parent_id + '][' + $(this).val() + ']"]');
           if (variants.length) {
               data['service_variant_id'] = variants.val();
           }
           $.post('add/', data, function(response) {
               div.data('id', response.data.id);
               tr.find('.item-total').html(response.data.item_total);
               updateCart(response.data);
           }, "json");
        } else {
           $.post('delete/', {id: div.data('id')}, function (response) {
               div.data('id', null);
               tr.find('.item-total').html(response.data.item_total);
               updateCart(response.data);
           }, "json");
        }
    });

    $(".cart .services select").change(function () {
        var tr = $(this).closest('tr');
        $.post('save/', {id: $(this).closest('div').data('id'), 'service_variant_id': $(this).val()}, function (response) {
            tr.find('.item-total').html(response.data.item_total);
            updateCart(response.data);
        }, "json");
    });

    $("#cancel-affiliate").click(function () {
        $(this).closest('form').append('<input type="hidden" name="use_affiliate" value="0">').submit();
        return false;
    });
});