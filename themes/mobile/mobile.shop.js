// Shop :: Lazy Loading
var LazyLoading = ( function($) {

    var onScroll;

    LazyLoading = function(options) {
        var that = this;

        // VARS
        that.list_name = options["names"]["list"];
        that.items_name = options["names"]["items"];
        that.pagind_name = options["names"]["paging"];
        that.load_class = "is-loading";

        // DOM
        that.$wrapper = ( options["$wrapper"] || false );
        that.$list = that.$wrapper.find(that.list_name);
        that.$window = $(window);

        // DYNAMIC VARS
        that.$paging = that.$wrapper.find(that.pagind_name);

        // INIT
        that.initLazyLoading();
    };

    LazyLoading.prototype.initLazyLoading = function() {
        var that = this;

        that.addWatcher();
    };

    LazyLoading.prototype.addWatcher = function() {
        var that = this;

        onScroll = function() {
            that.onScroll();
        };

        that.$window.on("scroll", onScroll);
    };

    LazyLoading.prototype.stopWatcher = function() {
        var that = this;

        if (typeof onScroll == "function") {
            that.$window.off("scroll", onScroll);
        }
    };

    LazyLoading.prototype.onScroll = function() {
        var that = this,
            is_paging_exist = ( $.contains(document, that.$paging[0]) );

        if (is_paging_exist) {

            var $window = that.$window,
                scroll_top = $window.scrollTop(),
                display_height = $window.height(),
                paging_top = that.$paging.offset().top;

            // If we see paging, stop watcher and run load
            if (scroll_top + display_height >= paging_top) {
                that.stopWatcher();
                that.loadNextPage();
            }

        } else {
            that.stopWatcher();
        }

    };

    LazyLoading.prototype.loadNextPage = function() {
        var that = this,
            next_page_url = getNextUrl(),
            $paging = that.$paging;

        function getNextUrl() {
            var $nextPage = that.$paging.find(".selected").next(),
                result = false;

            if ($nextPage.length) {
                result = $nextPage.find("a").attr("href");
            }

            return result;
        }

        function showLoad() {
            var $loading = '<div class="s-loading-wrapper"><i class="icon16 loading"></i>&nbsp;' + $paging.data("loading-text") + '</div>';

            $paging
                .addClass(that.load_class)
                .append($loading);
        }

        if (next_page_url) {

            showLoad();

            $.get(next_page_url, function(response) {
                var $category = $(response),
                    $newItems = $category.find(that.list_name + " " + that.items_name),
                    $newPaging = $category.find(that.pagind_name);

                that.$list.append($newItems);

                $paging.after($newPaging);

                $paging.remove();

                that.$paging = $newPaging;

                that.addWatcher();
            });
        }
    };

    return LazyLoading;

})(jQuery);

// Shop :: AJAX Products Filtering
var ProductsFilter = ( function($) {

    ProductsFilter = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form");

        // VARS

        // DYNAMIC VARS
        that.is_locked = false;

        // INIT
        that.initClass();
    };

    ProductsFilter.prototype.initClass = function() {
        var that = this;
        //
        that.bindEvents();

        window.addEventListener('popstate', function(event) {
            location.reload();
        });
    };

    ProductsFilter.prototype.bindEvents = function() {
        var that = this;
        //
        that.$wrapper.on("click", ".show-filter-content-link", function() {
            that.toggleFilter( $(this) );
            return false;
        });
        // On submit form
        that.$form.on("submit", function(event) {
            event.preventDefault();
            if (!that.is_locked) {
                that.onSubmit( $(this) );
            }
            return false;
        });
    };

    ProductsFilter.prototype.toggleFilter = function( $link ) {
        var that = this,
            $wrapper = that.$wrapper,
            activeClass = "is-shown";

        // Change Link Text
        if ($wrapper.hasClass(activeClass)) {
            $link.text( $link.data("hide-text") )
        } else {
            $link.text( $link.data("show-text") )
        }

        // Toggle Content
        $wrapper.toggleClass(activeClass);
    };

    ProductsFilter.prototype.onSubmit = function( $form ) {
        var that = this,
            href = $form.attr("action"),
            data = $form.serialize(),
            $category = $("#s-category-wrapper");

        // Lock
        that.is_locked = true;

        // Animation
        $category.addClass("is-loading");

        $.get(href, data, function(html) {
            that.changeHistory( href + "?" + data );

            // Insert new html
            if ($category.length) {
                $category.replaceWith(html);
            }
            // Scroll to Top
            $("html, body").animate({
                scrollTop: 0
            }, 200);
            // Unclock
            that.is_locked = false;
        });
    };

    ProductsFilter.prototype.changeHistory = function(href) {
        var that = this,
            api_is_enabled = ( history && history.pushState && history.replaceState && history.state !== undefined );

        if (api_is_enabled) {
            history.pushState({}, '', href);
        }
    };

    return ProductsFilter;

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

// CountDown
var CountDown = ( function($) {

    CountDown = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];

        // VARS
        that.start = options["start"];
        that.end = options["end"];
        that.format = "%days%:%hours%:%minutes%:%seconds%";

        // DYNAMIC VARS
        that.period = that.getPeriod();
        that.time_period = null;
        that.timer = 0;

        // INIT
        that.run();
    };

    CountDown.prototype.getPeriod = function() {
        var that = this,
            start_date = new Date( that.start ),
            end_date = new Date( that.end );

        return (end_date > start_date) ? (end_date - start_date) : 0;
    };

    CountDown.prototype.getData = function() {
        var that = this,
            period = that.period;

        var second = 1000,
            minute = second * 60,
            hour = minute * 60,
            day = hour * 24,
            residue;

        var days = Math.floor(period/day);
        residue = ( period - days * day );

        var hours = Math.floor(residue/hour);
        residue = ( residue - hours * hour );

        var minutes = Math.floor(residue/minute);
        residue = ( residue - minutes * minute );

        var seconds = Math.floor(residue/second);

        return {
            days: days,
            hours: hours,
            minutes: minutes,
            seconds: seconds
        }
    };

    CountDown.prototype.getTime = function() {
        var that = this,
            data = that.getData(),
            result = that.format;

        return result
            .replace("%days%", (data.days < 10) ? "0" + data.days : data.days)
            .replace("%hours%", (data.hours < 10) ? "0" + data.hours : data.hours)
            .replace("%minutes%", (data.minutes < 10) ? "0" + data.minutes : data.minutes)
            .replace("%seconds%", (data.seconds < 10) ? "0" + data.seconds : data.seconds);
    };

    CountDown.prototype.run = function() {
        var that = this,
            timer = 1000;

        if (that.period > 0) {
            var time = that.getTime();

            that.$wrapper.html(time);

            that.period -= timer;

            if (that.period > 0) {
                that.timer = setTimeout( function () {
                    that.run();
                }, timer);
            }

        } else {
            that.destroy();
        }
    };

    CountDown.prototype.destroy = function() {
        var that = this;

        that.$wrapper.remove();
    };

    return CountDown;

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

var CategorySorting = ( function($) {

    CategorySorting = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$sortList = that.$wrapper.find(".sort-list");
        that.$sortSelect = that.$wrapper.find(".sort-select");
        that.$filtersW = $("#js-category-filters");

        // VARS

        // DYNAMIC VARS
        that.xhr = false;

        // INIT
        that.initClass();
    };

    CategorySorting.prototype.initClass = function() {
        var that = this,
            dataArray = getDataArray();

        if (dataArray.length) {
            var $select = that.render(dataArray);

            $select.on("change", function() {
                var href = $(this).val();
                if (href) {
                    that.onChange(href);
                }
            });
        }

        // Adding sort-fields to filter form
        function onChange(href) {

        }

        function getDataArray() {
            var $list = that.$sortList,
                is_selected = false,
                dataArray = [],
                $link,
                href,
                name;

            $list.find("li").each( function() {
                is_selected = ( $(this).hasClass("selected") );
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
        }
    };

    CategorySorting.prototype.render = function(dataArray) {
        var that = this;

        var $select = $("<select class=\"select\" />"),
            $wrapper = that.$sortSelect,
            $list = that.$sortList,
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
            .addClass("is-shown");

        // Remove old list
        $list.remove();

        return $select;
    };

    CategorySorting.prototype.onChange = function(href) {
        var that = this,
            $filtersW = that.$filtersW;

        if ($filtersW.length) {
            var array = getArray(href),
                $form = $filtersW.find("form");

            if (!array.length) {
                array = [
                    { name: "sort", value: "" },
                    { name: "order", value: "" }
                ];
            }

            $.each(array, function(index, item) {
                var $hidden_input = $form.find("input[name=\"" + item.name + "\"] ");

                if ($hidden_input.length) {
                    $hidden_input.val(item.value);

                } else {
                    $hidden_input = $("<input type=\"hidden\" />");
                    $hidden_input.attr("name",item.name);
                    $hidden_input.val(item.value);
                    $form.append($hidden_input);
                }
            });

            $form.trigger("submit");

        } else {
            var $category = $("#s-category-wrapper").addClass("is-loading");

            if (that.xhr) { that.xhr.abort(); }

            that.xhr = $.get(href, function(html) {
                changeHistory(href);

                // Insert new html
                if ($category.length) {
                    $category.replaceWith(html);
                }
            }).always( function() {
                that.xhr = false;
            });
        }

        // Parse href to Obj name->value
        function getArray(href) {
            var result = [],
                array = href.replace("?","").split("&");

            // Sort Params
            for (var i = 0; i < array.length; i++) {
                var array_item = array[i],
                    sub_array = array_item.split("="),
                    name = sub_array[0],
                    value = sub_array[1];

                if (name === "sort" || name === "order" ) {
                    result.push({
                        name: name,
                        value: value
                    });
                }
            }

            return result;
        }

        function changeHistory(href) {
            var api_is_enabled = (history && history.pushState);
            if (api_is_enabled) {
                history.pushState({}, '', href);
            }
        }
    };

    return CategorySorting;

})(jQuery);

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