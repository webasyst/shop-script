$(document).ready(function () {

    // scroll-dependent animations
    function updateCartSummaryFixedStatus() {
      	if ( $(window).scrollTop()>=45 ) {
            if (!$("#cart-summary").hasClass('empty')) {
              	$("#cart-summary").addClass( "fixed" );
            }
    	}
    	else if ( $(window).scrollTop()<40 ) {
    		$("#cart-summary").removeClass( "fixed" );
    	}    
    }

    $(window).scroll(function() {    
      	updateCartSummaryFixedStatus();   
    });

    $('.dialog').on('click', 'a.dialog-close', function () {
        $(this).closest('.dialog').hide().find('.cart').empty();
        return false;
    });

    $(document).keyup(function(e) {
        if (e.keyCode == 27) {
            $(".dialog:visible").hide().find('.cart').empty();
        }
    });

    $("#main").on('submit', '.product-list form.addtocart', function () {
        var f = $(this);
        if (f.data('url')) {
            var d = $('#dialog');
            var c = d.find('.cart');
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
                cart_total.closest('#cart-summary').removeClass('empty');
                updateCartSummaryFixedStatus();
                if ($("table.cart").length) {
                    $(".content").parent().load(location.href, function () {
                        cart_total.html(response.data.total);
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

    $('.filters.ajax form input').change(function () {
        var f = $(this).closest('form');
        var url = '?' + f.serialize();
        $(window).lazyLoad && $(window).lazyLoad('sleep');
        $.get(url, function(html) {
            var tmp = $('<div></div>').html(html);
            $('#product-list').html(tmp.find('#product-list').html());
            if (!!(history.pushState && history.state !== undefined)) {
                window.history.pushState({}, '', url);
            }
            $(window).lazyLoad && $(window).lazyLoad('reload');
        });
    });
});
