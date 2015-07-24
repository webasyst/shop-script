    // LAZY LOAD
( function($) {

    var initialize = function() {
        var $lazyPadding = $(".lazyloading-paging");

        if ($.fn.lazyLoad) {

            if (!$lazyPadding.length) {
                return;
            }

            var times = parseInt( $lazyPadding.data('times'), 10),
                link_text = $lazyPadding.data('linkText') || 'Load more',
                current = $lazyPadding.find('li.selected');

            if (current.children('a').text() != '1') {
                return;
            }

            $lazyPadding.hide();

            var win = $(window);

            // prevent previous launched lazy-loading
            win.lazyLoad('stop');

            // check need to initialize lazy-loading
            var next = current.next();

            if (next.length) {
                win.lazyLoad({
                    container: ".shop-list-wrapper",
                    load: function () {
                        win.lazyLoad('sleep');

                        $lazyPadding.hide();

                        // determine actual current and next item for getting actual url
                        var current = $lazyPadding.find('li.selected');
                        var next = current.next();
                        var url = next.find('a').attr('href');
                        if (!url) {
                            win.lazyLoad('stop');
                            return;
                        }

                        var product_list = $(".shop-list-wrapper");

                        var loading = $lazyPadding.parent().find('.loading').parent();

                        if (!loading.length) {
                            var loading_str = $lazyPadding.data('loading-str') || 'Loading...';
                            loading = $('<div><i class="icon16 loading"></i>'+loading_str+'</div>').insertBefore($lazyPadding);
                        }

                        loading.show();

                        $.get(url, function (html) {
                            var tmp = $('<div></div>').html(html);
                            if ($.Retina) {
                                tmp.find('.shop-list-wrapper img').retina();
                            }
                            product_list.append(tmp.find(".shop-list-wrapper").children());
                            var tmp_paging = tmp.find('.lazyloading-paging').hide();
                            $lazyPadding.replaceWith(tmp_paging);
                            $lazyPadding = tmp_paging;

                            times -= 1;

                            // check need to stop lazy-loading
                            var current = $lazyPadding.find('li.selected');
                            var next = current.next();
                            if (next.length) {
                                if (!isNaN(times) && times <= 0) {
                                    win.lazyLoad('sleep');
                                    if (!$('.lazyloading-load-more').length) {
                                        $('<a href="#" class="lazyloading-load-more">' + link_text + '</a>').insertAfter($lazyPadding)
                                            .click(function () {
                                                loading.show();
                                                times = 1;      // one more time
                                                win.lazyLoad('wake');
                                                win.lazyLoad('force');
                                                return false;
                                            });
                                    }
                                } else {
                                    win.lazyLoad('wake');
                                }
                            } else {
                                win.lazyLoad('stop');
                            }

                            loading.hide();
                            tmp.remove();
                        });
                    }
                });
            }
        }
    };

    $(document).ready(function () {
        initialize();
    });

})(jQuery);

// SLIDER
( function($) {
    //SLIDERS

    $(document).ready( function() {

    var $slider = $(".homepage-bxslider"),
        slide_count = $slider.find("li").length;

        $slider.bxSlider( {
            auto : slide_count > 1,
            touchEnabled: true,
            pause : 5000,
            autoHover : true,
            pager: slide_count > 1
        });
    });

})(jQuery);

// Checkout Marking Active Options
( function($) {

    var storage = {
        activeStepClass: "is-selected",
        getCheckoutOptions: function() {
            return $(".checkout-options li");
        }
    };

    var initialize = function() {
        var $checkoutOptions = storage.getCheckoutOptions();

        $checkoutOptions.find("input[type=\"radio\"]").each( function() {
            var $input = $(this),
                is_active = ( $(this).attr("checked") === "checked" );

            if (is_active) {
                markOption( $input );
            }
        });
    };

    var bindEvents = function() {
        var $checkoutOptions = storage.getCheckoutOptions();

        $checkoutOptions.find("input[type=\"radio\"]").on("click", function() {
            markOption( $(this) );
        });

    };

    var markOption = function( $input ) {
        var $wrapper = $input.closest("li"),
            $checkoutOptions = storage.getCheckoutOptions();

        // Clear class for all
        $checkoutOptions.removeClass(storage.activeStepClass);

        // Mark this
        $wrapper.addClass(storage.activeStepClass);
    };

    $(document).ready( function() {
        //
        initialize();
        //
        bindEvents();
    });

})(jQuery);

// Catalog AJAX Filtering
( function($) {

    var storage = {
        href: ""
    };

    var bindEvents = function() {

        $(".filter-content-wrapper form").submit( function() {
            onSubmit( $(this) );
            return false;
        });
    };

    var onSubmit = function( $form ) {
        var request = getRequest( $form),
            $list = $(".shop-list-wrapper"),
            $no_list = $(".no-product-wrapper"),
            $button = $form.find(".button-wrapper"),
            $block;

        if ( $list.length ) {
            $block = $list;
        } else if ($no_list.length) {
            $block = $no_list;
        }

        // Show refreshing icon over button
        if (typeof toggleRefreshIcon === "function") {
            toggleRefreshIcon($button, "show");
        }

        request.done( function(content) {
            var $content = $("<div />").html(content),
                list = $content.find(".shop-list-wrapper"),
                no_list = $content.find(".no-product-wrapper"),
                block;

            if ( list.length ) {
                block = list;
            } else if (no_list.length) {
                block = no_list;
            }

            if ($block && block) {
                $block.html(block);

                if (!!(history.pushState && history.state !== undefined)) {
                    window.history.pushState({}, '', storage.href);
                }

                $(window).lazyLoad && $(window).lazyLoad('reload');

            }

            // Show refreshing icon over button
            if (typeof toggleRefreshIcon === "function") {
                toggleRefreshIcon($button, "hide");
            }

            closeFilter();

            scrollList();
        });
    };

    var getRequest = function($form) {
        var deferred = $.Deferred(),
            fields = $form.serializeArray(),
            params = [],
            href;

        // Sort Params
        for (var i = 0; i < fields.length; i++) {
            if (fields[i].value !== '') {
                params.push(fields[i].name + '=' + fields[i].value);
            }
        }
        href = '?' + params.join('&');

        // Set href for PushState
        storage.href = href;

        // Request
        $.get( href + '&_=_', function(request) {
            deferred.resolve(request);
        });

        return deferred;
    };

    var closeFilter = function() {
        var $wrapper = $(".catalog-filter-wrapper"),
            activeClass = "is-shown";

        // If active close
        if ( $wrapper.hasClass(activeClass) ) {
            $wrapper.find(".show-filter-content-link").trigger("click");
        }
    };

    var scrollList = function() {
        var $filter = $(".catalog-filter-wrapper"),
            $header = $(".header-wrapper"),
            filter_top = parseInt( $filter.css("margin-top") ),
            header_height = parseInt( $header.outerHeight() ),
            scrollTop = 0;

        if ( $filter.length && $header.length ) {
            scrollTop = $filter.offset().top - header_height - filter_top;
            //
            $(window).scrollTop(scrollTop);
        }
    };

    $(document).ready( function() {
        bindEvents();
    });

})(jQuery);

// Adding refresh icon on AJAX-loader buttons
var toggleRefreshIcon = function($button, option ) {
    var activeClass = "rotate-icon-wrapper";

    if ($button && $button.length && option) {
        if (option === "show") {
            $button.addClass(activeClass);
        } else if (option === "hide") {
            $button.removeClass(activeClass);
        }
    }
};

var showSortSelect = function() {

    var storage = {
        activeClass: "selected",
        isShownClass: "is-shown",
        getWrapper: function() {
            return $(".sort-list-wrapper");
        },
        getSortList: function() {
            return this.getWrapper().find(".sort-list")
        },
        getSortSelect: function() {
            return this.getWrapper().find(".sort-select")
        }
    };

    var initialize = function() {
        var dataArray = getDataArray();

        if (dataArray.length) {
            renderSortSelect(dataArray);
        }
    };

    var getDataArray = function() {
        var $list =  storage.getSortList(),
            is_selected = false,
            dataArray = [],
            $link,
            href,
            name;

        $list.find("li").each( function() {
            is_selected = ( $(this).hasClass(storage.activeClass) );
            $link = $(this).find("a");
            href = $link.attr("href");
            name = $link.text();

            dataArray.push({
                name: name,
                href: href,
                is_selected: is_selected
            });
        });

        return dataArray;
    };

    var renderSortSelect = function( dataArray ) {
        var $select = $("<select class=\"select\" />"),
            $wrapper = storage.getSortSelect(),
            $list = storage.getSortList(),
            option = "";

        for (var item in dataArray) {
            if (dataArray.hasOwnProperty(item)) {
                var data = dataArray[item],
                    selected = (data.is_selected) ? "selected" : "";

                option = "<option value=\"" + data.href + "\" " + selected + " >" + data.name + "</option>";
                $select.append(option);
            }
        }

        // Render
        $wrapper
            .append($select)
            .addClass(storage.isShownClass);

        // Remove old list
        $list.remove();
    };

    var bindEvents = function() {
        var $select = storage.getSortSelect().find("select");

        $select.on("change", function() {
            var href = $(this).val();
            if (href) { onChange(href); }
        });
    };

    // Adding sort-fields to filter form
    var onChange = function( href ) {
        var array = getArray( href),
            $form = $(".filter-content-wrapper form");

        if (!(array && array.length)) {
            array = [
                { name: "sort", value: "" },
                { name: "order", value: "" }
            ];
        }

        for (var i = 0; i < array.length; i++) {
            var $hidden_input = $form.find("input[name=\"" + array[i].name + "\"] ");

            if ($hidden_input.length) {
                $hidden_input.val(array[i].value);

            } else {
                $hidden_input = $("<input type=\"hidden\" />");
                $hidden_input.attr("name",array[i].name);
                $hidden_input.val(array[i].value);
                $form.append($hidden_input);
            }
        }

        $form.trigger("submit");
    };

    // Parse href to Obj name->value
    var getArray = function( href ) {
        if (href.indexOf("&") + 1) {
            var array = href.replace("?","").split("&"),
                params = [];

            // Sort Params
            for (var i = 0; i < array.length; i++) {
                var array_item = array[i],
                    sub_array = array_item.split("="),
                    name = sub_array[0],
                    value = sub_array[1];

                if (name == "sort" || name == "order" ) {
                    params.push({
                        name: name,
                        value: value
                    });
                }
            }
            return params;
        }
    };

    $(document).ready( function() {
        //
        initialize();
        //
        bindEvents();
    });

};

// Update Cart Counter at Site Header
var updateHeaderCartCount = function( request ) {
    var setCartCount = function( request ) {
        var data = request;
        if (data.status === "ok") {
            var cart_count = data.data.count;
            if (cart_count >= 0) {
                renderCartCount( cart_count );
            }
        }
    };

    var renderCartCount = function( count ) {
        var $basket = $(".basket-wrapper a"),
            activeClass = "is-active",
            $counter = $basket.find(".basket-count"),
            is_rendered = $counter.length;

        if (count) {
            if (!is_rendered) {
                $basket.addClass(activeClass);
                $counter = $("<span class=\"basket-count\" />").appendTo( $basket );
            }
            $counter.text(count);

        } else {
            if (is_rendered) {
                $counter.remove();
                $basket.removeClass(activeClass)
            }
        }
    };

    return setCartCount( request );
};
