$(function() {
    var paging = $('.lazyloading-paging').hide();
    if (!paging.length) {
        return;
    }
    var loading = $('<div><i class="icon16 loading"></i>Loading...</div>').hide().insertBefore(paging);

    var win = $(window);
    var product_list = $('#product-list .product-list');
    var current = paging.find('li.selected');
    var next = current.next();

    win.lazyLoad('stop');
    if (next.length) {
        win.lazyLoad({
            container: $('#main'),
            load: function() {
                win.lazyLoad('sleep');
                var url = next.find('a').attr('href');
                if (!url) {
                    win.lazyLoad('stop');
                }

                loading.show();
                $.get(url, function(html) {
                    var tmp = $('<div></div>').html(html);
                    product_list.append(tmp.find('#product-list .product-list').children());
                    var tmp_paging = tmp.find('.lazyloading-paging').hide();
                    paging.replaceWith(tmp_paging);
                    paging = tmp_paging;
                    current = paging.find('li.selected');
                    next = current.next();
                    if (next.length) {
                        win.lazyLoad('wake');
                    } else {
                        win.lazyLoad('stop');
                    }
                    loading.hide();
                    tmp.remove();
                });
            }
        });
    }
});