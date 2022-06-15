var ReviewImagesSection = ( function($) {

    ReviewImagesSection = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$file_field = that.$wrapper.find(".js-file-field");
        that.$files_wrapper = that.$wrapper.find(".js-attached-files-section");
        that.$errors_wrapper = that.$wrapper.find(".js-errors-section");

        // CONST
        that.max_post_size = options["max_post_size"];
        that.max_file_size = options["max_file_size"];
        that.max_files = options["max_files"];
        that.templates = options["templates"];
        that.patterns = options["patterns"];
        that.locales = options["locales"];

        // DYNAMIC VARS
        that.post_size = 0;
        that.id_counter = 0;
        that.files_data = {};
        that.images_count = 0;

        // INIT
        that.init();
    };

    ReviewImagesSection.prototype.init = function() {
        var that = this,
            $document = $(document);

        that.$wrapper.data("controller", that);

        that.$file_field.on("change", function() {
            addFiles(this.files);
            that.$file_field.val("");
        });

        that.$wrapper.on("click", ".js-show-textarea", function(event) {
            event.preventDefault();
            $(this).closest(".s-description-wrapper").addClass("is-extended");
        });

        that.$wrapper.on("click", ".js-delete-file", function(event) {
            event.preventDefault();
            var $file = $(this).closest(".s-file-wrapper"),
                file_id = "" + $file.data("file-id");

            if (file_id && that.files_data[file_id]) {
                var file_data = that.files_data[file_id];
                that.post_size -= file_data.file.size;
                delete that.files_data[file_id];
                that.images_count -= 1;
            }

            $file.remove();

            that.renderErrors();
        });

        that.$wrapper.on("keyup change", ".js-textarea", function(event) {
            var $textarea = $(this),
                $file = $textarea.closest(".s-file-wrapper"),
                file_id = "" + $file.data("file-id");

            if (file_id && that.files_data[file_id]) {
                var file = that.files_data[file_id];
                file.desc = $textarea.val();
            }
        });

        var timeout = null,
            is_entered = false;

        $document.on("dragover", dragWatcher);
        function dragWatcher(event) {
            var is_exist = $.contains(document, that.$wrapper[0]);
            if (is_exist) {
                onDrag(event);
            } else {
                $document.off("dragover", dragWatcher);
            }
        }

        $document.on("drop", dropWatcher);
        function dropWatcher(event) {
            var is_exist = $.contains(document, that.$wrapper[0]);
            if (is_exist) {
                onDrop(event)
            } else {
                $document.off("drop", dropWatcher);
            }
        }

        $document.on("reset clear", resetWatcher);
        function resetWatcher(event) {
            var is_exist = $.contains(document, that.$wrapper[0]);
            if (is_exist) {
                that.reset();
            } else {
                $document.off("reset clear", resetWatcher);
            }
        }

        function onDrop(event) {
            event.preventDefault();

            var files = event.originalEvent.dataTransfer.files;

            addFiles(files);
            dropToggle(false);
        }

        function onDrag(event) {
            event.preventDefault();

            if (!timeout)  {
                if (!is_entered) {
                    is_entered = true;
                    dropToggle(true);
                }
            } else {
                clearTimeout(timeout);
            }

            timeout = setTimeout(function () {
                timeout = null;
                is_entered = false;
                dropToggle(false);
            }, 100);
        }

        function dropToggle(show) {
            var active_class = "is-highlighted";

            if (show) {
                that.$wrapper.addClass(active_class);
            } else {
                that.$wrapper.removeClass(active_class);
            }
        }

        function addFiles(files) {
            var errors_types = [],
                errors = [];

            $.each(files, function(i, file) {
                var response = that.addFile(file);
                if (response.error) {
                    var error = response.error;

                    if (errors_types.indexOf(error.type) < 0) {
                        errors_types.push(error.type);
                        errors.push(error);
                    }
                }
            });

            that.renderErrors(errors);
        }
    };

    ReviewImagesSection.prototype.addFile = function(file) {
        var that = this,
            file_size = file.size;

        var image_type = /^image\/(png|jpe?g|gif|webp)$/,
            is_image = (file.type.match(image_type));

        if (!is_image) {
            return {
                error: {
                    text: that.locales["file_type"],
                    type: "file_type"
                }
            };

        } else if (that.images_count >= that.max_files) {
            return {
                error: {
                    text: that.locales["files_limit"],
                    type: "files_limit"
                }
            };

        } else if (file_size >= that.max_file_size) {
            return {
                error: {
                    text: that.locales["file_size"],
                    type: "file_size"
                }
            };

        } else if (that.post_size + file_size >= that.max_file_size) {
            return {
                error: {
                    text: that.locales["post_size"],
                    type: "post_size"
                }
            };

        } else {
            that.post_size += file_size;

            var file_id = that.id_counter,
                file_data = {
                    id: file_id,
                    file: file,
                    desc: ""
                };

            that.files_data[file_id] = file_data;

            that.id_counter++;
            that.images_count += 1;

            render();

            return file_data;
        }

        function render() {
            var $template = $(that.templates["file"]),
                $image = $template.find(".s-image-wrapper");

            $template.attr("data-file-id", file_id);

            getImageUri().then( function(image_uri) {
                $image.css("background-image", "url(" + image_uri + ")");
            });

            that.$files_wrapper.append($template);

            function getImageUri() {
                var deferred = $.Deferred(),
                    reader = new FileReader();

                reader.onload = function(event) {
                    deferred.resolve(event.target.result);
                };

                reader.readAsDataURL(file);

                return deferred.promise();
            }
        }
    };

    ReviewImagesSection.prototype.reset = function() {
        var that = this;

        that.post_size = 0;
        that.id_counter = 0;
        that.files_data = {};

        that.$files_wrapper.html("");
        that.$errors_wrapper.html("");
    };

    ReviewImagesSection.prototype.getSerializedArray = function() {
        var that = this,
            result = [];

        var index = 0;

        $.each(that.files_data, function(file_id, file_data) {
            var file_name = that.patterns["file"].replace("%index%", index),
                desc_name = that.patterns["desc"].replace("%index%", index);

            result.push({
                name: file_name,
                value: file_data.file
            });

            result.push({
                name: desc_name,
                value: file_data.desc
            });

            index++;
        });

        return result;
    };

    ReviewImagesSection.prototype.renderErrors = function(errors) {
        var that = this,
            result = [];

        that.$errors_wrapper.html("");

        if (errors && errors.length) {
            $.each(errors, function(i, error) {
                if (error.text) {
                    var $error = $(that.templates["error"].replace("%text%", error.text));
                    $error.appendTo(that.$errors_wrapper);
                    result.push($error);
                }
            });
        }

        return result;
    };

    return ReviewImagesSection;

})(jQuery);

( function($) {

    $(document).ready(init);

    function init() {
        var $form_wrapper = $("#product-review-form"),
            $form = $form_wrapper.find("form"),
            $captcha = $(".wa-captcha"),
            $provider = $("#user-auth-provider"),
            current_provider = $provider.find(".selected").attr('data-provider');

        initialize();

        function initialize() {
            //
            checkAddedReview();

            // Show Captcha
            showCaptcha();

            //
            checkHash();

            // Define binds
            bindEvents();
        }

        function bindEvents() {

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

            var $submit_button = $form.find(".js-submit-button"),
                is_locked = false;

            $form.on("submit", function(event) {
                event.preventDefault();

                if (!is_locked) {
                    is_locked = true;

                    var $loading = $('<i class="icon16 loading" />');
                    $loading.insertAfter($submit_button);

                    $submit_button
                        .attr("disabled", true)
                        .val( $submit_button.data("active") );

                    addReview($form)
                        .always( function() {
                            is_locked = false;
                        })
                        .done( function(response) {
                            if (response.status === "fail") {
                                $loading.remove();

                                $submit_button
                                    .removeAttr("disabled")
                                    .val( $submit_button.data("inactive") );
                            }
                        });
                }
            });

        }

        function checkAddedReview() {
            if (sessionStorage) {
                var new_review_id = sessionStorage.getItem("review-id"),
                    activeClass = "active-review-item";

                if (new_review_id) {
                    // Marking
                    $(".review-item").each( function() {
                        var $review = $(this);
                        if ( $review.data("id") === new_review_id ) {
                            $review.addClass(activeClass);
                        }
                    });

                    // Restore Storage
                    sessionStorage.setItem("review-id", null);
                }
            }
        }

        function checkHash() {
            var hash_name = "#publish",
                $link = $(".show-write-form"),
                is_publish = (location.hash === hash_name),
                empty_reviews = ( !$(".reviews-list-wrapper .review-item").length ),
                showForm = ( ( is_publish || empty_reviews ) && $link.length );

            if (showForm) {
                showWriteForm($link);
            }
        }

        function unsetReplyID( $link ) {
            var $review = $link.closest(".review-item"),
                parent_id = $review.data("id"),
                $form = $(".reviews-form-wrapper"),
                $input =  $form.find("input[name=\"parent_id\"]"),
                $notification = $link.closest(".reply-notification");

            $notification.hide();
            $input.val("");
        }

        function setReplyID( $link ) {
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
            var scrollTopValue = parseInt( $form_wrapper.offset().top - $(".header-wrapper").outerHeight() - 10 );
            $("html, body").scrollTop(scrollTopValue);
        }

        function onProviderClick( $link ) {
            var li = $link.closest("li");

            if (!li.hasClass('selected')) {
                li.siblings(".selected")
                    .removeClass("selected");

                li.addClass('selected');

                var provider_name = li.attr('data-provider');

                if (provider_name === 'guest') {
                    $('.provider-fields').hide();
                    $('.provider-fields[data-provider=guest]').show();
                }

                if (provider_name === current_provider) {
                    $(".provider-fields").hide();
                    $(".provider-fields[data-provider='+provider_name+']").show();
                }

                showCaptcha();

                // Set input
                $form.find('input[name=auth_provider]').val(provider_name);

                window.open( $link.attr('href'), "oauth");
            }
        }

        function addReview($form) {
            var href = location.pathname + 'add/',
                form_data = getData($form);

            return $.ajax({
                url: href,
                data: form_data,
                cache: false,
                contentType: false,
                processData: false,
                type: 'POST',
                success: onSuccess,
                error: function(jqXHR, errorText) {
                    if (console) {
                        console.error("Error", errorText);
                    }
                }
            });

            function getData($form) {
                var fields_data = $form.serializeArray(),
                    form_data = new FormData();

                $.each(fields_data, function () {
                    var field = $(this)[0];
                    form_data.append(field.name, field.value);
                });

                var $image_section = $form.find("#js-review-images-section");
                if ($image_section.length) {
                    var controller = $image_section.data("controller"),
                        data = controller.getSerializedArray();

                    $.each(data, function(i, file_data) {
                        form_data.append(file_data.name, file_data.value);
                    });
                }

                return form_data;
            }

            function onSuccess(r) {
                if (r.status === "fail") {
                    showErrors($form, r.errors);
                    refreshCaptcha();
                } else {
                    // Save new review ID to sStorage
                    if (sessionStorage) { sessionStorage.setItem("review-id", r.data.id); }

                    // Refresh without hash
                    location.href = location.pathname;
                }
            }
        }

        function showErrors($form, errors) {
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
        }

        // Show Captcha
        function showCaptcha() {
            if (current_provider === 'guest' || !current_provider) {
                $captcha.show();
            } else {
                $captcha.hide();
            }
        }

        function setRating( $link ) {
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
        }

        function showWriteForm( $link ) {
            var $wrapper = $form_wrapper,
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
        }

        function hideWriteForm() {
            var $wrapper = $form_wrapper,
                $link = $(".reviews-form-wrapper .show-write-form"),
                wrapper_active_class = "is-shown";

            // Hide link
            $link.removeClass("is-active");

            // Show Form
            $wrapper.removeClass(wrapper_active_class);
        }

        function refreshCaptcha() {
            $(".wa-captcha-img").trigger("click");
        }

    }

})(jQuery);

