$(document).ready(function () {

    // scroll-dependent animations
    $(window).scroll(function() {    
      	if ( $(this).scrollTop()>=253 ) {
            $("#cart-flyer").addClass( "fixed" );
            $(".aux").hide();
    	}
    	else if ( $(this).scrollTop()<252 ) {
    		$("#cart-flyer").removeClass( "fixed" );
            $(".aux").show();
    	}    
    });

    $('.bxslider').bxSlider( { auto : true, pause : 5000, autoHover : true });

    $("form.addtocart").submit(function () {
        var f = $(this);
        $.post(f.attr('action'), f.serialize(), function (response) {
            if (response.status == 'ok') {
                var cart_total = $(".cart-total");
                if ($("table.cart").length) {
                    $(".content").parent().load(location.href, function () {
                        cart_total.html(response.data.total);
                        cart_total.closest('#cart').removeClass('empty');
                    });
                } else {
                    if (f.closest(".product-list").get(0).tagName.toLowerCase() == 'table') {
                        var origin = f.closest('tr');
                        var block = $('<div></div>').append($('<table></table>').append(origin.clone()));
                    } else {
                        var origin = f.closest('li');
                        var block = $('<div></div>').append(origin.html());
                    }
                    block.css({
                        'z-index': 10,
                        top: origin.offset().top,
                        left: origin.offset().left,
                        width: origin.width()+'px',
                        height: origin.height()+'px',
                        position: 'absolute',
                        overflow: 'hidden'
                    }).insertAfter(origin).animate({
                            top: cart_total.offset().top,
                            left: cart_total.offset().left,
                            width: 0,
                            height: 0,
                            opacity: 0.5
                        }, 500, function() {
                            $(this).remove();
                            cart_total.html(response.data.total);
                            cart_total.closest('#cart').removeClass('empty');
                        });
                }
            } else if (response.status == 'fail') {
                alert(response.errors);
            }
        }, "json");
        return false;
    });

});
