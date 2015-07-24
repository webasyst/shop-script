( function($) {

    var $form_wrapper = $("#product-review-form"),
        $form = $form_wrapper.find("form"),
        $captcha = $(".wa-captcha"),
        $provider = $("#user-auth-provider"),
        current_provider = $provider.find(".selected").attr('data-provider');

    var initialize = function() {
        //
        checkAddedReview();

        // Show Captcha
        showCaptcha();

        //
        checkHash();

        // Define binds
        bindEvents();
    };

    var bindEvents = function() {

        $(document).on("click", ".show-write-form", function() {
            showWriteForm( $(this) );
            return false;
        });

        $(document).on("click", ".rate-wrapper .rate-item", function() {
            setRating( $(this) );
            return false;
        });

        $(document).on("click", "#user-auth-provider li a", function () {
            onProviderClick( $(this) );
            return false;
        });

        $(document).on("click", ".show-reply-comment-form", function() {
            setReplyID( $(this) );
            return false;
        });

        $(document).on("click", ".unset-reply-parent", function() {
            unsetReplyID( $(this) );
            return false;
        });

        $form.submit( function() {
            submitForm();
            return false;
        });

    };

    var checkAddedReview = function() {
        if (sessionStorage) {
            var new_review_id = sessionStorage.getItem("review-id"),
                activeClass = "active-review-item";

            if (new_review_id) {
                // Marking
                $(".review-item").each( function() {
                    var $review = $(this);
                    if ( $review.data("id") == new_review_id ) {
                        $review.addClass(activeClass);
                    }
                });

                // Restore Storage
                sessionStorage.setItem("review-id", false);
            }
        }
    };

    var checkHash = function() {
        var hash_name = "#publish",
            $link = $(".show-write-form"),
            is_publish = (location.hash === hash_name),
            empty_reviews = ( !$(".reviews-list-wrapper .review-item").length ),
            showForm = ( ( is_publish || empty_reviews ) && $link.length );

        if (showForm) {
            showWriteForm($link);
        }
    };

    var submitForm = function() {
        addReview();
    };

    var unsetReplyID = function( $link ) {
        var $review = $link.closest(".review-item"),
            parent_id = $review.data("id"),
            $form = $(".reviews-form-wrapper"),
            $input =  $form.find("input[name=\"parent_id\"]"),
            $notification = $link.closest(".reply-notification");

        $notification.hide();
        $input.val("");
    };

    var setReplyID = function( $link ) {
        var $review = $link.closest(".review-item"),
            parent_id = $review.data("id"),
            $form = $(".reviews-form-wrapper"),
            $input =  $form.find("input[name=\"parent_id\"]");

        // Set Form data
        $input.val(parent_id);

        // Show notification
        $form
            .find(".reply-notification") //.show()
                .find(".reply-number").text("#" + parent_id);

        // Show reply Form
        showWriteForm();

        // Animate
        var scrollTopValue = parseInt( $("#product-review-form").offset().top - $(".header-wrapper").outerHeight() - 10 );
        $("html, body").scrollTop(scrollTopValue);
    };

    var onProviderClick = function( $link ) {
        var li = $link.closest("li");

        if (!li.hasClass('selected')) {
            li.siblings(".selected")
                .removeClass("selected");

            li.addClass('selected');

            var provider_name = li.attr('data-provider');

            if (provider_name == 'guest') {
                $('.provider-fields').hide();
                $('.provider-fields[data-provider=guest]').show();
            }

            if (provider_name == current_provider) {
                $(".provider-fields").hide();
                $(".provider-fields[data-provider='+provider_name+']").show();

            }

            showCaptcha();

            // Set input
            $form.find('input[name=auth_provider]').val(provider);

            window.open( $(this).attr('href'), "oauth");
        }
    };

    var addReview = function() {
        $.post( location.origin + "" + location.pathname.replace(/\/#\/[^#]*|\/#|\/$/g, '') + '/add/', $form.serialize(), function (r) {
            if (r.status === "fail") {
                showErrors($form, r.errors);
                refreshCaptcha();
            } else {
                // Save new review ID to sStorage
                if (sessionStorage) { sessionStorage.setItem("review-id", r.data.id); }

                // Refresh without hash
                location.href = location.pathname;
            }
        },
        'json');
    };

    var showErrors = function($form, errors) {
        var wrapper = $form.find(".errors-wrapper");

        // Clear old errors
        wrapper.html("");

        for (var name in errors) {
            var $error = $("<div class=\"error\" />");
            //
            $error.text(errors[name]);
            //
            wrapper.append($error);
        }
    };

    // Show Captcha
    var showCaptcha = function() {
        if (current_provider == 'guest' || !current_provider) {
            $captcha.show();
        } else {
            $captcha.hide();
        }
    };

    var setRating = function( $link ) {
        var $wrapper = $link.closest(".rate-wrapper"),
            $input = $wrapper.find("input[name=\"rate\"]"),
            rate_count = $link.data("rate-count"),
            $links = $wrapper.find(".rate-item"),
            empty_rate_class = "icon-star-empty",
            full_rate_class = "icon-star";

        if (rate_count && rate_count > 0) {
            // SET RATING
                // Clear old styles
                $links
                    .removeClass(full_rate_class)
                    .addClass(empty_rate_class);

                for ( var i = 0; i < rate_count; i++ ) {
                    $($links[i])
                        .removeClass(empty_rate_class)
                        .addClass(full_rate_class);
                }

            // SET FIELD VALUE
            $input.val(rate_count);
        }
    };

    var showWriteForm = function( $link ) {
        var $wrapper = $("#product-review-form"),
            active_class = "is-active",
            wrapper_active_class = "is-shown";

        if ($link && $link.hasClass(active_class)) {
            hideWriteForm();
        } else {
            $link = $link ? $link : $(".reviews-form-wrapper .show-write-form");

            // Hide link
            $link.addClass(active_class);

            // Reset Form
            $wrapper.find("form").trigger("reset");

            // Show $form
            $wrapper.addClass(wrapper_active_class);
        }
    };

    var hideWriteForm = function() {
        var $wrapper = $("#product-review-form"),
            $link = $(".reviews-form-wrapper .show-write-form"),
            wrapper_active_class = "is-shown";

        // Hide link
        $link.removeClass("is-active");

        // Show Form
        $wrapper.removeClass(wrapper_active_class);
    };

    var refreshCaptcha = function() {
        $(".wa-captcha-img").trigger("click");
    };

    $(document).ready( function() {
        initialize();
    });

})(jQuery);

