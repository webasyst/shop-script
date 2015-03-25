$(document).ready(function () {

    $('.bxslider').bxSlider( { auto : false, pause : 5000, autoHover : true });

    $('.dialog').on('click', 'a.dialog-close', function () {
        $(this).closest('.dialog').hide().find('.cart').empty();
        return false;
    });

    $(document).keyup(function(e) {
        if (e.keyCode == 27) {
            $(".dialog:visible").hide().find('.cart').empty();
        }
    });

    $(".content").on('submit', '.product-list form.addtocart', function () {
        var f = $(this);
        f.find('input[type="submit"]').after('<i class="icon16 loading adding2cart"></i>');
        if (f.data('url')) {
            var d = $('#dialog');
            var c = d.find('.cart');
            f.find('.adding2cart').hide();
            c.load(f.data('url'), function () {
                c.prepend('<a href="#" class="dialog-close">&times;</a>');
                d.show();
                if ((c.height() > c.find('form').height())) {
                    c.css('bottom', 'auto');
                } else {
                    c.css('bottom', '15%');
                }
            });
            return false;
        }
        $.post(f.attr('action') + '?html=1', f.serialize(), function (response) {
            if (response.status == 'ok') {
                var cart_total = $(".cart-total");
                cart_total.closest('#cart').removeClass('empty');
                cart_total.html(response.data.total);

                f.find('input[type="submit"]').hide();
                f.find('.price').hide();
                f.find('.adding2cart').hide();
                f.find('span.added2cart').show();
            } else if (response.status == 'fail') {
                f.find('.adding2cart').hide();
                alert(response.errors);
            }
        }, "json");
        return false;
    });
    $('.filters.ajax form input').change(function () {
        var f = $(this).closest('form');
        var url = '?' + f.serialize();
        $(window).lazyLoad && $(window).lazyLoad('sleep');
        $.get(url+'&_=_', function(html) {
            var tmp = $('<div></div>').html(html);
            $('#product-list').html(tmp.find('#product-list').html());
            if (!!(history.pushState && history.state !== undefined)) {
                window.history.pushState({}, '', url);
            }
            $(window).lazyLoad && $(window).lazyLoad('reload');
        });
    });
});
