$(document).ready(function () {

    $("form.addtocart").submit(function () {
        var f = $(this);
        $.post(f.attr('action'), f.serialize(), function (response) {
            if (response.status == 'ok') {
                var cart_total = $(".cart-total");
                cart_total.closest('#cart').removeClass('empty');
                cart_total.html(response.data.total);

                f.find('input[type="submit"]').hide();
                f.find('.price').hide();
                f.find('span.added2cart').show();
            } else if (response.status == 'fail') {
                alert(response.errors);
            }
        }, "json");
        return false;
    });

});
