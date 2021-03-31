( function($) { "use strict";

    // STEPS

    var Auth = ( function($) {

        Auth = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$form = that.$wrapper.find("form:first");

            // VARS
            that.templates = options["templates"];
            that.errors = options["errors"];
            that.scope = options["scope"];
            that.contact_id = options["contact_id"];

            // DYNAMIC VARS
            that.reload = true;

            // INIT
            that.initClass();
        };

        Auth.prototype.initClass = function() {
            var that = this;

            if (typeof that.errors === "object" && Object.keys(that.errors).length ) {
                that.scope.DEBUG("Errors:", "error", that.errors);
                that.renderErrors(that.errors);
            }

            that.$form.on("submit", function(event) {
                event.preventDefault();
            });

            that.$wrapper.on("focus", "select, textarea, input", function(event) {
                var $field = $(this),
                    has_error = $field.hasClass("wa-error");

                if (has_error) {
                    $field.data("reload", true);
                }
            });

            var $tab_field = null;

            that.$wrapper.on("change", "select, textarea, input", function(event) {
                var $field = $(this),
                    reload = !!$field.data("affects-rate") || !!$field.data("reload");

                var set_focus = !!($tab_field && $tab_field[0] === $field[0]);
                $tab_field = null;

                var $field_wrapper = $field.closest(".wa-field-wrapper");
                if (!$field_wrapper.length) {
                    $field_wrapper = $field.parent();
                }

                var error = that.scope.validate($field_wrapper, true);
                if (!error.length) {
                    if (reload) {
                        var promise = that.update({
                            reload: true
                        });

                        if (set_focus) {
                            promise.done( function() {
                                focusField(that.scope.sections["auth"].$wrapper);
                            });
                        }
                    }
                }
            });

            that.$wrapper.on("keydown", "select, textarea, input", function(event) {
                if (event.keyCode === 9) {
                    $tab_field = $(this);
                }
            });

            that.initType();

            that.initAuth();

            that.initDatepicker();
        };

        Auth.prototype.initAuth = function() {
            var that = this,
                $document = $(document),
                dialog = null,
                dialog_xhr = null;

            $document.on("wa_auth_contact_logged", loginWatcher);
            function loginWatcher() {
                var is_exist = $.contains(document, that.$wrapper[0]);
                if (is_exist) {
                    if (dialog) { dialog.close(); }
                } else {
                    $document.off("wa_auth_contact_logged", loginWatcher);
                }
            }

            // LOGOUT
            if (that.contact_id) {
                // These code were used to update the block. Now used reload page
                that.$wrapper.on("click", ".js-logout-button", function(event) {
                    $(document).trigger("wa_order_reload_start");
                });

            // LOGIN
            } else {
                that.$wrapper.on("click", ".js-show-login-dialog", function(event) {
                    event.preventDefault();
                    openDialog("login");
                });
            }

            function onOpenDialog($wrapper, dialog) {

                $wrapper.on("click", "a", function(event) {
                    var $link = $(this),
                        type = $link.data("type"),
                        types = ["login", "signup", "forgotpassword", "setpassword"];

                    if (type) {
                        event.preventDefault();
                        if (types.indexOf(type) >= 0) {
                            openDialog(type);
                        }
                    }
                });

                $wrapper.on("wa_auth_set_password", function(event, hash) {
                    openDialog("setpassword", {
                        data: [{
                            name: "hash",
                            value: hash
                        }]
                    });
                });

                $wrapper.on("wa_auth_resent_password", function(event, hash) {
                    openDialog("login");
                });

                $wrapper.on("wa_auth_contact_signed", function(event, contact, params) {
                    if (params.password_sent) {
                        openDialog("login");
                    }
                });

                $wrapper.on("wa_auth_form_change_view", function() {
                    dialog.resize();
                });
            }

            function openDialog(type, options) {
                options = (options || {});

                var href = that.scope.urls["auth_dialog"],
                    data = [{
                        name: "type",
                        value: type
                    }];

                var $type = that.$wrapper.find(".js-type-field");
                if ($type.length) {
                    var type_id = $.trim($type.val());
                    if (type_id) {
                        data.push({
                            name: "contact_type",
                            value: type_id
                        });
                    }
                }

                if (options.data) {
                    data = data.concat(options.data);
                }

                if (dialog_xhr) { dialog_xhr.abort(); }

                if (dialog) { dialog.lock(true); }

                dialog_xhr = $.post(href, data, function(html) {
                    if (dialog) { dialog.close(); }

                    dialog = new window.waOrder.ui.Dialog({
                        $wrapper: $(html),
                        onOpen: onOpenDialog
                    });
                }).always( function() {
                    dialog_xhr = null;
                });

                return dialog_xhr;
            }
        };

        Auth.prototype.initType = function() {
            var that = this;

            var $type_toggle = that.$wrapper.find(".js-type-toggle").first(),
                $type_field = that.$wrapper.find(".js-type-field");

            var toggle = new window.waOrder.ui.Toggle({
                $wrapper: $type_toggle,
                change: function(event, target, toggle) {
                    var id = $(target).data("id");
                    if (id) {
                        that.reload = true;
                        $type_field.val(id);
                        that.scope.update();
                    }
                }
            });
        };

        Auth.prototype.initDatepicker = function() {
            var that = this;

            that.$wrapper.find(".js-datepicker").each( function() {
                var $input = $(this);

                var options = {};

                var alt_selector = $input.data("alt");
                if (alt_selector) {
                    var $alt_input = $input.closest(".wa-field-wrapper").find(alt_selector);
                    if ($alt_input.length) {
                        options["altField"] = $alt_input;
                        options["altFormat"] = "yy-mm-dd";
                    }
                }

                var type = $input.data("type");
                if (type && type === "birthday") {
                    options["maxDate"] = 0;

                    $input.on("change", function() {
                        var is_valid = checkDate( $(this).val() );
                        if (!is_valid) {
                            $input.datepicker("setDate", "today");

                        } else {
                            var date = $input.datepicker("getDate"),
                                today = new Date();

                            if (date && date > today) {
                                $input.datepicker("setDate", "today");
                            }
                        }
                    });
                }

                $input.datepicker(options);

                $input.on("keydown keypress keyup", function(event) {
                    if ( event.which === 13 ) {
                        event.preventDefault();
                    }
                });
            });

            //

            function checkDate(value) {
                var format = $.datepicker._defaults.dateFormat,
                    is_valid = false;

                try {
                    $.datepicker.parseDate(format, value);
                    is_valid = true;
                } catch(e) {
                    is_valid = false;
                }

                return is_valid;
            }
        };

        // REQUIRED

        /**
         * @return {Array}
         * */
        Auth.prototype.getData = function(options) {
            var that = this,
                result = [];

            if (options.clean) {
                result.push({
                    name: "auth[html]",
                    value: 1
                });

            } else {
                var render_errors = (options.render_errors !== false);

                if (that.$form.length) {
                    result = that.$form.serializeArray();
                }

                if (!options.only_api && that.reload) {
                    result.push({
                        name: "auth[html]",
                        value: 1
                    });
                }

                var errors = that.scope.validate(that.$form, render_errors);
                if (errors.length) {
                    result.errors = errors;
                }
            }

            return result;
        };

        Auth.prototype.render = function(api) {
            var that = this,
                is_changed = false;

            if (api.auth && api.auth["html"]) {
                that.$wrapper.replaceWith(api.auth["html"]);
                is_changed = true;
            }

            return is_changed;
        };

        /**
         * @param {Object} errors
         * @return {Array}
         * */
        Auth.prototype.renderErrors = function(errors) {
            var that = this,
                result = [];

            $.each(errors, function(i, error) {
                if (error.name && error.text) {
                    var $field = that.$wrapper.find("[name=\"" + error.name + "\"]");
                    if ($field.length) {
                        error.$field = $field;
                        renderError(error);
                    }
                }

                result.push(error);
            });

            return result;

            function renderError(error) {
                var $error = $("<div class=\"wa-error-text\" />").text(error.text);
                var error_class = "wa-error";

                if (error.$field) {
                    var $field = error.$field;

                    if (!$field.hasClass(error_class)) {
                        $field.addClass(error_class);

                        var $field_wrapper = $field.closest(".wa-field-wrapper");
                        if ($field_wrapper.length) {
                            $field_wrapper.append($error);
                        } else {
                            $error.insertAfter($field);
                        }

                        $field.on("change keyup", removeFieldError);
                    }
                }

                function removeFieldError() {
                    $field.removeClass(error_class);
                    $error.remove();

                    $field.off("change", removeFieldError);
                }
            }
        };

        // PROTECTED

        Auth.prototype.update = function(options) {
            var that = this;

            if (options.reload) {
                that.reload = true;
            }

            return that.scope.update().always( function() {
                that.reload = true;
            });
        };

        return Auth;

    })($);

    var Region = ( function($) {

        Region = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$form = that.$wrapper.find("form:first");

            // VARS
            that.disabled = options["disabled"];
            that.errors = options["errors"];
            that.scope = options["scope"];
            that.auto_use_timeout = options["auto_use_timeout"];

            // DYNAMIC VARS
            that.reload = true;

            // INIT
            that.initClass();
        };

        Region.prototype.initClass = function() {
            var that = this;

            var key_timer = 0;

            if (typeof that.errors === "object" && Object.keys(that.errors).length ) {
                that.scope.DEBUG("Errors:", "error", that.errors);
            }

            that.$form.on("submit", function(event) {
                event.preventDefault();
            });

            var $location_field = that.$form.find(".js-location-field"),
                $country_field = that.$form.find(".js-country-field"),
                $region_field = that.$form.find(".js-region-field"),
                $city_field = that.$form.find(".js-city-field"),
                $zip_field = that.$form.find(".js-zip-field");

            fieldWatcher($location_field, [$country_field, $region_field, $city_field, $zip_field]);
            fieldWatcher($country_field, [$region_field, $city_field, $zip_field]);
            fieldWatcher($region_field, [$city_field, $zip_field]);
            fieldWatcher($zip_field);

            checkPreviousFields([$zip_field, $city_field, $region_field, $country_field, $location_field]);

            if ($city_field.length && $city_field.hasClass("js-city-autocomplete")) {
                // hack for IE 11
                setTimeout( function() {
                    initAutocomplete($city_field);
                }, 100);

            } else {
                fieldWatcher($city_field, [$zip_field]);
            }

            //

            /**
             * @param {Object} $field
             * @param {Array?} $dependent_fields
             * */
            function fieldWatcher($field, $dependent_fields) {
                if (isRendered($field)) {

                    $field.on("keydown", function() {
                        onKeyDown($field);
                    });

                    $field.on("change", function() {
                        if ($dependent_fields && $dependent_fields.length) {
                            $.each($dependent_fields, function(i, $field) {
                                if ($field.length) {
                                    $field.attr("disabled", true);
                                }
                            });
                        }

                        onChange($field);
                    });

                    $field.on("focus blur", function() {
                        clearTimeout(key_timer);
                    });
                }
            }

            function initAutocomplete($city_field) {
                // var is_depends = isRendered($zip_field);
                var is_depends = false;

                var xhr = null,
                    change_timer = 0;

                $city_field.autocomplete({
                    source: function(field_data, resolve) {
                        getData().then( function(data) {
                            resolve(data);
                            if (data.length) {
                                onKeyDown($city_field);
                            }
                        });
                    },
                    minLength: 2,
                    focus: function() {
                        return false;
                    },
                    select: function(event, ui) {
                        clearTimeout(change_timer);
                        $city_field.val(ui.item.value);
                        validate($city_field, true);
                        return false;
                    }
                });

                $city_field.on("keydown", function(event) {
                    var code = event.keyCode,
                        is_enter = (code === 13);

                    if (is_enter) {
                        validate($city_field, true);
                    } else {
                        onKeyDown($city_field);
                    }
                });

                $city_field.on("timeout_change", function() {
                    var errors = that.scope.validate(that.$wrapper, false);
                    if (!errors.length) {
                        validate($city_field, true);
                    }
                });

                $city_field.on("focus blur", function() {
                    clearTimeout(key_timer);
                });

                $city_field.on("change", function() {
                    change_timer = setTimeout(function() {
                        $city_field.trigger("focus");
                        validate($city_field, true);
                    }, 100);
                });

                function getData() {
                    var deferred = $.Deferred();

                    var href = that.scope.urls["order"] + "addressAutocomplete/",
                        data = {};

                    if ($country_field.length) {
                        var country = $country_field.val();
                        if (country) {
                            data.country = country;
                        }
                    }

                    if ($region_field.length) {
                        var region = $region_field.val();
                        if (region) {
                            data.region = region;
                        }
                    }

                    var city = $city_field.val();
                    if (city) {
                        data.city = city;
                    }

                    if ($zip_field.length) {
                        var zip = $zip_field.val();
                        if (zip) {
                            data.zip = zip;
                        }
                    }

                    if (xhr) { xhr.abort(); }

                    xhr = $.post(href, data, "json")
                        .always( function() {
                            xhr = null;
                        })
                        .done( function(response) {
                            if (response.status === "ok") {

                                var result = response.data.result;
                                if (!result.length) {
                                    result = [];
                                } else {
                                    result = format(result);
                                }

                                deferred.resolve(result);
                            } else {
                                deferred.reject();
                            }
                        })
                        .fail( function() {
                            deferred.reject();
                        });

                    return deferred.promise();

                    function format(data) {
                        var result = [];

                        $.each(data, function(i, item) {
                            var city = item.city;
                            if (city) {
                                result.push({
                                    label: city,
                                    value: city
                                });
                            }
                        });

                        return result;
                    }
                }

                function validate($field, blur) {
                    var errors = that.scope.validate(that.$wrapper, true, true);
                    if (!errors.length) {
                        onChange($field);
                    }
                    if (blur) {
                        $field.trigger("blur");
                    }
                }
            }

            //

            /**
             * @param {Object} $field
             * */
            function onKeyDown($field) {
                clearTimeout(key_timer);

                if (that.auto_use_timeout > 0) {
                    key_timer = setTimeout( function() {
                        $field.trigger("change").trigger("timeout_change");
                    }, that.auto_use_timeout);
                }
            }

            /**
             * @param {Object} $field
             * */
            function onChange($field) {
                clearTimeout(key_timer);

                var value = $.trim( $field.val() );
                if (!value.length) { return false; }

                that.scope.trigger("region_change");
            }

            function checkPreviousFields(fields) {
                var required_fields = [],
                    is_ready = false;

                $.each(fields, function(i, $field) {
                    if ($field.length && $field.is(":visible")) {
                        if (is_ready) {
                            required_fields.push($field);
                        } else {
                            var value = $.trim($field.val());
                            if (value) {
                                is_ready = true;
                            }
                        }
                    }
                });

                if (required_fields.length) {
                    $.each(required_fields, function(i, $field) {
                        var $field_wrapper = $field.closest(".wa-field-wrapper");
                        that.scope.validate($field_wrapper, true);
                    });
                }
            }

            function isRendered($field) {
                return !!($field.length && $field.is(":visible"));
            }
        };

        // REQUIRED

        /**
         * @return {Array}
         * */
        Region.prototype.getData = function(options) {
            var that = this,
                result = [];

            if (that.disabled) {
                result.push({
                    name: "region[html]",
                    value: "only"
                });

            } else if (options.clean) {
                result.push({
                    name: "region[html]",
                    value: "only"
                });

            } else {
                var render_errors = (options.render_errors !== false);

                if (that.$form.length) {
                    result = that.$form.serializeArray();
                }

                if (!options.only_api && that.reload) {
                    result.push({
                        name: "region[html]",
                        value: "only"
                    });
                }

                var errors = that.scope.validate(that.$form, render_errors);
                if (errors.length) {
                    result.errors = errors;
                }
            }

            return result;
        };

        Region.prototype.render = function(api) {
            var that = this,
                is_changed = false;

            if (api.region && api.region["html"]) {
                that.$wrapper.replaceWith(api.region["html"]);
                is_changed = true;
            }

            return is_changed;
        };

        /**
         * @param {Object} errors
         * @return {Array}
         * */
        Region.prototype.renderErrors = function(errors) {
            var that = this,
                result = [];

            $.each(errors, function(i, error) {
                result.push(error);
            });

            return result;
        };

        // PROTECTED

        Region.prototype.update = function(data) {
            var that = this;

            that.reload = !!data.reload;

            that.scope.update()
                .always( function() {
                    that.reload = true;
                });
        };

        return Region;

    })($);

    var Shipping = ( function($) {

        var PickupDialog = ( function($) {

            PickupDialog = function(options) {
                var that = this;

                // DOM
                that.$wrapper = options["$wrapper"];
                that.$sidebar = that.$wrapper.find(".wa-sidebar-section");
                that.$map_section = that.$wrapper.find(".wa-map-section");
                that.$variants = that.$wrapper.find(".wa-variant-wrapper");

                //
                that.dialog = options["dialog"];
                that.scope = options["scope"];

                // VARS
                that.show_map = options["show_map"];
                that.templates = options["templates"];
                that.variants = formatVariants(options["variants"]);
                that.active_variant = options["active_variant"];

                // FUNCTIONS
                that.onSet = (typeof options["set"] === "function" ? options["set"] : function() {});

                // DYNAMIC VARS
                that.map_deferred = $.Deferred();
                that.getMap = function () {
                    return that.map_deferred.promise();
                };

                // INIT
                that.initClass();

                function formatVariants(variants) {
                    $.each(variants, function(id, variant) {
                        var lat = (variant.lat ? parseFloat(variant.lat) : null),
                            lng = (variant.lng ? parseFloat(variant.lng) : null);

                        lat = (isNaN(lat) ? null : lat);
                        lng = (isNaN(lng) ? null : lng);

                        variant.lat = lat;
                        variant.lng = lng;
                    });

                    return variants;
                }
            };

            PickupDialog.prototype.initClass = function() {
                var that = this;

                if (that.show_map) {
                    that.initMap();
                } else {
                    that.$map_section.hide();
                }

                that.initFilters();

                that.initDetails();

                that.initMobileToggle();
            };

            PickupDialog.prototype.initMap = function() {
                var that = this;

                if ( !(that.scope.map.adapter && that.scope.map.api_uri) ) {
                    return false;
                }

                if (!$("#wa-shipping-map").length) { return false; }

                if (that.scope.map.adapter === "yandex") {
                    window.waOrder.ui.load([{
                        id: "yandex-maps-api-js",
                        type: "js",
                        uri: that.scope.map.api_uri
                    }]).then(function () {
                        if (window.ymaps) {
                            window.ymaps.ready(function () {
                                initYandexMap(window.ymaps);
                            });
                        } else {
                            that.scope.scope.DEBUG("Yandex API required", "error");
                            return false;
                        }
                    });

                } else if (that.scope.map.adapter === "google") {
                    window.waOrder.ui.load([{
                        id: "google-maps-api-js",
                        type: "js",
                        uri: that.scope.map.api_uri
                    }, {
                        id: "google-maps-clusterer-js",
                        type: "js",
                        uri: "//developers.google.com/maps/documentation/javascript/examples/markerclusterer/markerclusterer.js"
                    }]).then( function() {
                        initGoogleMap();
                    });
                }

                function initYandexMap(ymaps) {
                    var placemarks = {},
                        active_balloon = null;

                    var center = getCenter(that.variants),
                        map = new ymaps.Map("wa-shipping-map", { center: center, zoom: 10, controls: ["fullscreenControl", "zoomControl", "geolocationControl"] }),
                        cluster = new ymaps.Clusterer();

                    var moveTo = function(placemark) {
                        var is_mobile = isMobile();

                        if (!is_mobile) {
                            map.setCenter(placemark.geometry.getCoordinates(), 17).then( function() {
                                active_balloon = placemark.balloon;

                                var state = cluster.getObjectState(placemark);

                                if (!state.isClustered && state.isShown) {
                                    if (!active_balloon.isOpen()) {
                                        active_balloon.open();
                                    }
                                }
                            });
                        }
                    };

                    initCluster();

                    initSearch();

                    that.map_deferred.resolve({
                        map: map,
                        placemarks: placemarks,
                        refresh: function(variant_ids) {
                            var placemarks_array = [];

                            $.each(variant_ids, function(i, variant_id) {
                                if (variant_id && placemarks[variant_id]) {
                                    placemarks_array.push(placemarks[variant_id]);
                                }
                            });

                            cluster.removeAll();
                            cluster.add(placemarks_array);
                        },
                        reset: function() {
                            map.setCenter(center, 10);
                            if (active_balloon) {
                                if (active_balloon.isOpen()) {
                                    active_balloon.close();
                                }
                                active_balloon = null;
                            }
                        },
                        moveTo: function(placemark) {
                            return moveTo(placemark);
                        }
                    });

                    function initCluster() {
                        var placemarks_array = [];

                        $.each(that.variants, function(id, variant) {
                            var placemark = addPlacemark(variant);
                            if (placemark) { placemarks_array.push(placemark); }
                        });

                        cluster.add(placemarks_array);
                        map.geoObjects.add(cluster);

                        cluster.events.add("click", function(event) {
                            var target = event.get("target"),
                                is_cluster = !!(target.getGeoObjects);

                            if (is_cluster) {
                                var placemarks_array = target.getGeoObjects(),
                                    current_zoom = map.getZoom();

                                if (current_zoom >= 17) {
                                    var variants_array = [];

                                    $.each(placemarks_array, function(i, placemark) {
                                        if (placemark.variant_id) {
                                            variants_array.push(placemark.variant_id);
                                        }
                                    });

                                    if (variants_array.length) {
                                        exitFullscreen();
                                        that.$wrapper.trigger("show_variant_details", [variants_array, null]);
                                    }
                                }

                            } else if (target.variant_id) {
                                exitFullscreen();
                                that.$wrapper.trigger("show_variant_details", [[target.variant_id], null]);
                            }
                        });

                        // set placemark if we have an active variant
                        if (that.active_variant && placemarks[that.active_variant]) {
                            var active_placemark = placemarks[that.active_variant];
                            moveTo(active_placemark);
                        }

                        return cluster;

                        function exitFullscreen() {
                            var fullscreen_control = map.controls.get("fullscreenControl");
                            if (fullscreen_control.state.get("fullscreen")) {
                                fullscreen_control.exitFullscreen();
                            }
                        }
                    }

                    function initSearch() {
                        var mySearchControl = new ymaps.control.SearchControl({
                                options: { noPlacemark: true }
                            }),
                            mySearchResults = new ymaps.Collection(null, {});

                        map.controls.add(mySearchControl);
                        map.geoObjects.add(mySearchResults);

                        mySearchControl.events.add('resultselect', function(e) {
                            var index = e.get("index");
                            mySearchControl.getResult(index).then( function(placemark) {
                                placemark.options.set("preset", "islands#redIcon");
                                mySearchResults.add(placemark);
                            });
                        }).add('submit', function () {
                            mySearchResults.removeAll();
                        })
                    }

                    function addPlacemark(variant) {
                        var lat = null,
                            lng = null;

                        if (variant.lat && variant.lng) {
                            lat = variant.lat;
                            lng = variant.lng;
                        } else {
                            return false;
                        }

                        var placemark = new ymaps.Placemark([lat, lng], {
                            balloonContentBody: variant.name
                        });

                        placemark.variant_id = variant.variant_id;

                        placemarks[variant.variant_id] = placemark;

                        return placemark;
                    }
                }

                function initGoogleMap() {
                    var placemarks = {},
                        center = getCenter(that.variants);

                    var map = new google.maps.Map(document.getElementById("wa-shipping-map"), {
                        center: {lat: center[0], lng: center[1]},
                        zoom: 10,
                        maxZoom: 18
                    });

                    var placemarks_array = renderPlacemarks();

                    var balloon = new google.maps.InfoWindow(),
                        cluster = initCluster(placemarks_array);

                    // set placemark if we have an active variant
                    if (that.active_variant && placemarks[that.active_variant]) {
                        var active_placemark = placemarks[that.active_variant];

                        setTimeout( function() {
                            moveTo(active_placemark);
                        }, 1000);
                    }

                    that.map_deferred.resolve({
                        map: map,
                        placemarks: placemarks,
                        refresh: function(variant_ids) {
                            var placemarks_array = [];

                            $.each(variant_ids, function(i, variant_id) {
                                if (variant_id && placemarks[variant_id]) {
                                    placemarks_array.push(placemarks[variant_id]);
                                }
                            });

                            cluster = initCluster(placemarks_array);
                        },
                        reset: function() {
                            map.setCenter(center);
                            map.setZoom(10);
                            balloon.close();
                        },
                        moveTo: function(placemark) {
                            return moveTo(placemark);
                        }
                    });

                    function moveTo(placemark) {
                        var is_mobile = isMobile();
                        if (!is_mobile) {
                            var variant = that.variants[placemark.variant_id];

                            map.setZoom(17);
                            map.setCenter(placemark.getPosition());

                            balloon.close();
                            balloon.setContent(variant.name);
                            balloon.open(map, placemark);
                        }
                    }

                    function initCluster(placemarks) {
                        if (cluster) { cluster.clearMarkers(); }

                        cluster = new MarkerClusterer(map, placemarks, {
                            maxZoom: 18,
                            imagePath: '//developers.google.com/maps/documentation/javascript/examples/markerclusterer/m'
                        });

                        cluster.addListener("clusterclick", function(clust) {
                            var zoom = map.getZoom();
                            if (zoom >= 17) {

                                var markets_array = clust.getMarkers(),
                                    variants_array = [];

                                $.each(markets_array, function(i, marker) {
                                    variants_array.push(marker.variant_id);
                                });

                                if (variants_array.length) {
                                    exitFullscreen();
                                    that.$wrapper.trigger("show_variant_details", [variants_array, null]);
                                }
                            }
                        });

                        return cluster;
                    }

                    function renderPlacemarks() {
                        var placemarks_array = [];

                        $.each(that.variants, function(id, variant) {
                            var placemark = addPlacemark(variant);
                            if (placemark) { placemarks_array.push(placemark); }
                        });

                        return placemarks_array;

                        function addPlacemark(variant) {
                            var lat = null,
                                lng = null;

                            if (variant.lat && variant.lng) {
                                lat = parseFloat(variant.lat);
                                lng = parseFloat(variant.lng);
                            } else {
                                return false;
                            }

                            var placemark = new google.maps.Marker({
                                position: {
                                    lat: lat,
                                    lng: lng
                                },
                                title: variant.name
                            });

                            placemark.variant_id = variant.variant_id;

                            placemarks[variant.variant_id] = placemark;

                            placemark.addListener("click", function() {
                                map.setZoom(17);
                                map.setCenter(placemark.getPosition());

                                balloon.close();
                                balloon.setContent(variant.name);
                                balloon.open(map, placemark);

                                exitFullscreen();
                                that.$wrapper.trigger("show_variant_details", [[variant.variant_id], null]);
                            });

                            return placemark;
                        }
                    }

                    function exitFullscreen() {
                        try {
                            if (document.exitFullscreen) {
                                document.exitFullscreen();
                            } else if (document.mozCancelFullScreen) {
                                document.mozCancelFullScreen();
                            } else if (document.webkitCancelFullScreen) {
                                document.webkitCancelFullScreen();
                            }
                        } catch(e) {}
                    }
                }

                function getCenter(variants) {
                    var result = [55,37],
                        lat_array = [],
                        lng_array = [];

                    $.each(variants, function(id, variant) {
                        if (variant.lat && variant.lng) {
                            lat_array.push(variant.lat);
                            lng_array.push(variant.lng);
                        }
                    });

                    if (lat_array.length && lng_array.length) {
                        var lat_min = Math.min.apply(null, lat_array),
                            lat_max = Math.max.apply(null, lat_array),
                            lng_min = Math.min.apply(null, lng_array),
                            lng_max = Math.max.apply(null, lng_array);

                        result[0] = lat_min + (lat_max-lat_min)/2;
                        result[1] = lng_min + (lng_max-lng_min)/2;
                    }

                    return result;
                }
            };

            PickupDialog.prototype.initFilters = function() {
                var that = this,
                    filters = [];

                var $list = that.$wrapper.find(".wa-filters-list"),
                    set_class = "is-set";

                that.$wrapper.on("click", ".js-set-filter", function(event) {
                    event.preventDefault();

                    setFilter($(this));
                    render();
                });

                function setFilter($button) {
                    var service_id = $button.data("id"),
                        filter_index = filters.indexOf(service_id);

                    var active_class = "is-active",
                        is_active = $button.hasClass(active_class);

                    if (is_active) {
                        $button.removeClass(active_class);
                        if (filter_index >= 0) {
                            filters.splice(filter_index, 1);
                        }

                    } else {
                        $button.addClass(active_class);
                        if ( !(filter_index >= 0) ) {
                            filters.push(service_id);
                        }
                    }

                    if (filters.length) {
                        $list.addClass(set_class);
                    } else {
                        $list.removeClass(set_class);
                    }
                }

                function render() {
                    var active_variant_ids = [];

                    that.$variants.each( function() {
                        var $variant = $(this),
                            variant_id = $variant.data("id"),
                            variant_service_id = $variant.data("service-id");

                        var active = (!filters.length || filters.indexOf(variant_service_id) >= 0);

                        if (active) {
                            $variant.show();
                            active_variant_ids.push(variant_id);
                        } else {
                            $variant.hide();
                        }
                    });

                    that.getMap().then( function(map) {
                        map.refresh(active_variant_ids);
                    });

                    // that.dialog.resize();
                }
            };

            PickupDialog.prototype.initDetails = function() {
                var that = this;

                var $variants_section = that.$sidebar.find(".wa-variants-section"),
                    $details_section = that.$sidebar.find(".wa-variant-details-section"),
                    $details_body = $details_section.find("> .wa-section-body");

                that.$wrapper.on("click", ".js-show-variant-details", function(event) {
                    event.preventDefault();
                    var variant_id = $(this).data("id");
                    var is_mobile = isMobile();
                    if (is_mobile) {
                        set(variant_id);
                    } else {
                        toggle([variant_id]);
                    }
                });

                that.$wrapper.on("show_variant_details", function(event, variants) {
                    toggle(variants);
                    // var is_mobile = isMobile();
                    // if (is_mobile) {
                    //     set(variants[0]);
                    // } else {
                    //     toggle(variants);
                    // }
                });

                that.$wrapper.on("click", ".js-show-variants-list", function(event) {
                    event.preventDefault();
                    toggle(false);
                });

                that.$wrapper.on("click", ".js-use-variant", function(event) {
                    event.preventDefault();
                    var variant_id = $(this).data("variant-id");
                    set(variant_id);
                });

                function set(variant_id) {
                    if (variant_id) {
                        that.onSet(variant_id);
                        that.dialog.close();

                    } else {
                        that.scope.scope.DEBUG("Variant id is missing.", "error");
                    }
                }

                function toggle(variants) {
                    var show_class = "is-shown";

                    if (variants) {
                        var details_html = "";

                        $.each(variants, function(i, variant_id) {
                            if (that.variants[variant_id]) {
                                details_html += getDetailsHTML(that.variants[variant_id]);
                            } else {
                                that.scope.scope.DEBUG("Variant data is missing.", "error");
                            }
                        });

                        $details_body.html(details_html);
                        $variants_section.hide();
                        $details_section.addClass(show_class);

                        if (variants.length === 1) {
                            that.getMap().then( function(map) {
                                if (map.placemarks && map.placemarks[variants[0]]) {
                                    var placemark = map.placemarks[variants[0]];
                                    map.moveTo(placemark);
                                }
                            });
                        }
                    } else {
                        $details_section.removeClass(show_class);
                        $details_body.html("");
                        $variants_section.show();

                        that.dialog.resize();
                    }
                }

                function getDetailsHTML(variant) {
                    var template = that.templates["map_details"];

                    template = template.replace("%title%", variant.name).replace("%variant_id%", variant.variant_id);

                    var geolink = "";
                    if (!that.show_map) {
                        if (that.scope.map.adapter === "yandex") {
                            geolink = "ymaps-geolink";
                        } else if (that.scope.map.adapter === "google") {
                            geolink = "google-geolink";
                        }
                    }
                    template = template.replace("%geolink%", geolink);

                    if (variant.formatted_price) {
                        template = template.replace("%shipping_cost_style%", "").replace("%shipping_cost%", variant.formatted_price);
                    } else {
                        template = template.replace("%shipping_cost_style%", "display: none;");
                    }

                    if (variant.formatted_date) {
                        template = template.replace("%shipping_time_style%", "").replace("%shipping_time%", variant.formatted_date);
                    } else {
                        template = template.replace("%shipping_time_style%", "display: none;");
                    }

                    if (variant.service) {
                        template = template.replace("%shipping_service_style%", "").replace("%shipping_service%", variant.service);
                    } else {
                        template = template.replace("%shipping_service_style%", "display: none;");
                    }

                    if (variant.storage_days) {
                        template = template.replace("%storage_time_style%", "").replace("%storage_time%", variant.storage_days);
                    } else {
                        template = template.replace("%storage_time_style%", "display: none;");
                    }

                    if (variant.description) {
                        var address = variant.description;
                        if (!that.show_map && that.scope.map.adapter === "google") {
                            var link = '<a class="google-geolink" target="_blank" href="//maps.google.com/maps?q=%encode_address%">' + variant.description + '</a>';
                            address = link.replace("%encode_address%", encodeURIComponent(variant.description));
                        }

                        template = template.replace("%address_style%", "").replace("%address%", address);
                    } else {
                        template = template.replace("%address_style%", "display: none;");
                    }

                    return template;
                }
            };

            PickupDialog.prototype.initMobileToggle = function() {
                var that = this;

                var $type_toggle = that.$wrapper.find(".js-mobile-view-toggle").first();
                if (!$type_toggle.length) { return false; }

                var map_class = "is-mobile-map-view",
                    list_class = "is-mobile-list-view";

                that.$wrapper.addClass(list_class);

                if (that.show_map) {
                    var toggle = new window.waOrder.ui.Toggle({
                        $wrapper: $type_toggle,
                        change: function(event, target, toggle) {
                            var id = $(target).data("id");
                            if (id) {
                                if (id === "map") {
                                    that.$wrapper.removeClass(list_class);
                                    that.$wrapper.addClass(map_class);

                                } else if (id === "list") {
                                    that.$wrapper.removeClass(map_class);
                                    that.$wrapper.addClass(list_class);
                                }
                            }
                        }
                    });

                    var $list_toggle = toggle.$wrapper.find("[data-id=\"list\"]");
                    if ($list_toggle.length) {
                        that.$wrapper.on("show_variant_details", function(event, variants) {
                            $list_toggle.trigger("click");
                        });
                    }

                } else {
                    $type_toggle.hide();
                }
            };

            return PickupDialog;

        })(jQuery);

        var Types = ( function($) {

            Types = function(options) {
                var that = this;

                // DOM
                that.$wrapper = options["$wrapper"];

                // VARS
                that.active_class = "is-active";
                that.onChange = (typeof options["onChange"] === "function" ? options["onChange"] : function() {});

                // DYNAMIC VARS
                that.$active = that.$wrapper.find(".wa-type-wrapper." + that.active_class);

                // INIT
                that.initClass();
            };

            Types.prototype.initClass = function() {
                var that = this;

                that.$wrapper.on("click", ".wa-type-wrapper", function(event) {
                    event.preventDefault();
                    that.changeType( $(this) );
                });
            };

            /**
             * @param {Object} $type
             * */
            Types.prototype.changeType = function($type) {
                var that = this;

                if ($type.hasClass(that.active_class)) { return false; }

                var id = $type.data("id");

                if (id) {
                    if (that.$active.length) { that.$active.removeClass(that.active_class); }
                    that.$active = $type.addClass(that.active_class);
                    that.onChange(id);
                }
            };

            return Types;

        })(jQuery);

        Shipping = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$form = that.$wrapper.find("form:first");

            // VARS
            that.possible_addresses = options["possible_addresses"];
            that.map = options["map"];
            that.templates = options["templates"];
            that.disabled = options["disabled"];
            that.variants_count = options["variants_count"];
            that.locales = options["locales"];
            that.errors = options["errors"];
            that.scope = options["scope"];
            that.urls = options["urls"];

            // DYNAMIC VARS
            that.reload = true;

            // INIT
            that.initClass();
        };

        Shipping.prototype.initClass = function() {
            var that = this;

            if (typeof that.errors === "object" && Object.keys(that.errors).length ) {
                that.scope.DEBUG("Errors:", "error", that.errors);
            }

            that.$form.on("submit", function(event) {
                event.preventDefault();
            });

            var $type_field = null,
                $variant_field = null;

            var $types_section = that.$wrapper.find("#js-delivery-types-section");
            if ($types_section.length) {
                $type_field = $types_section.find(".js-type-field");

                new Types({
                    $wrapper: $types_section,
                    onChange: function (id) {
                        $type_field.val(id);
                        that.scope.DEBUG("Delivery type changed — " + id, "info");

                        if ($variant_field) {
                            $variant_field.val("");
                        }

                        that.update({
                            reload: true
                        }).then( function() {
                            var updated_shipping = that.scope.sections["shipping"];
                            if (updated_shipping) {
                                if (updated_shipping.variants_count > 1) {
                                    var $section = updated_shipping.$wrapper.find("#js-delivery-variants-section");
                                    if ($section.length) {
                                        $section.find(".wa-dropdown-toggle").trigger("click");
                                    }
                                }
                            }
                        });
                    }
                });
            }

            var $variants_section = that.$wrapper.find("#js-delivery-variants-section");
            if ($variants_section.length) {
                $variant_field = $variants_section.find(".js-variant-field");

                var type = ($type_field ? $type_field.val() : "");

                var dropdown = new window.waOrder.ui.Dropdown({
                    $wrapper: $variants_section.find(".js-variants-select"),
                    hover: false,
                    change_title: false,
                    change_selector: ".wa-dropdown-item",
                    open: function(dropdown) {
                        var is_mobile = isMobile(),
                            variants_count = that.variants_count;

                        if (that.map.display === "always") {
                            showPickupDialog(dropdown, true);
                            return false;

                        } else if (that.map.display === "desktop") {
                            showPickupDialog(dropdown, !is_mobile);
                            return false;

                        } else if (variants_count > 5 && type === "pickup") {
                            showPickupDialog(dropdown, false);
                            return false;

                        } else {
                            scrollContent($variants_section);
                        }
                    },
                    change: function(event, target, dropdown) {
                        var $target = $(target),
                            variant_id = $target.data("id"),
                            name = $target.find(".wa-name").data("name");

                        $variant_field.val(variant_id);
                        dropdown.setTitle(name);

                        that.update({
                            reload: true
                        });
                    }
                });

                if (that.variants_count === 1) {
                    dropdown.lock(true);
                }

                $types_section.on("click", ".wa-type-wrapper.is-active", function() {
                    dropdown.$button.trigger("click");
                });
            }

            var $short_variants_section = that.$wrapper.find("#js-delivery-short-variants-section");
            if ($short_variants_section.length) {
                $type_field = $types_section.find(".js-type-field");
                $variant_field = $short_variants_section.find(".js-variant-field");

                new window.waOrder.ui.Dropdown({
                    $wrapper: $short_variants_section.find(".js-variants-select"),
                    hover: false,
                    change_title: false,
                    change_selector: ".wa-dropdown-item",
                    open: function(dropdown) {
                        scrollContent($short_variants_section);
                    },
                    change: function(event, target, dropdown) {
                        var $target = $(target),
                            type_id = $target.data("type-id"),
                            variant_id = $target.data("variant-id"),
                            name = $target.find(".wa-name").data("name");

                        $type_field.val(type_id);
                        $variant_field.val(variant_id);

                        dropdown.setTitle(name);

                        that.update({
                            reload: true
                        });
                    }
                });
            }

            var $possible_addresses_section = that.$wrapper.find("#js-possible-addresses-section");
            if ($possible_addresses_section.length) {
                var dropdown = new window.waOrder.ui.Dropdown({
                    $wrapper: $possible_addresses_section.find(".js-possible-address-select"),
                    hover: false,
                    change_title: false,
                    change_selector: ".wa-dropdown-item",
                    open: function(dropdown) {
                    },
                    change: function(event, target, dropdown) {
                        var $target = $(target),
                            name = $target.find(".wa-name").text(),
                            index = $target.attr("data-index");

                        var possible_address = that.possible_addresses[parseInt(index)];
                        if (possible_address) {
                            dropdown.setTitle(name);

                            var $region = waOrder.form.sections["region"].$wrapper,
                                address = possible_address.address;

                            if (address.region) {
                                var $region_field = $region.find("input.js-region-field-value");
                                if ($region_field.length) {
                                    $region_field.val(address.region);
                                }
                            }
                            if (address.city) {
                                var $city_field = $region.find("input.js-city-field, input[name=\"region[city]\"]");
                                if ($city_field.length) {
                                    $city_field.val(address.city);
                                }
                            }
                            if (address.zip) {
                                var $zip_field = $region.find("input.js-zip-field, input[name=\"region[zip]\"]");
                                if ($zip_field.length) {
                                    $zip_field.val(address.zip);
                                }
                            }

                            that.update({ reload: true });
                        }
                    }
                });

                console.log( dropdown );
            }

            function showPickupDialog(dropdown, show_map) {
                var href = that.urls["variants_dialog"],
                    data = that.scope.getFormData();

                loading(true);

                $.post(href, data)
                    .done( function(html) {
                        var $wrapper = $(html);
                        $wrapper.data("scope", that);

                        new that.scope.ui.Dialog({
                            $wrapper: $wrapper,
                            options: {
                                dropdown: dropdown,
                                show_map: show_map
                            }
                        });
                    })
                    .always( function() {
                        loading(false);
                    });

                function loading(show) {
                    if (show) {
                        dropdown.lock(true);
                    } else {
                        dropdown.lock(false);
                    }
                }
            }

            function scrollContent($wrapper) {
                var is_mobile = isMobile(),
                    lift = ( is_mobile ? 55 : 10 ),
                    top = $wrapper.offset().top;

                if (!is_mobile) {
                    try {
                        top = that.scope.sections["region"].$wrapper.offset().top;
                    } catch(error) {

                    }
                }

                $("html, body").scrollTop(top - lift);
            }
        };

        Shipping.prototype.initPickupDialog = function(options) {
            var that = this;

            var $wrapper = options["$wrapper"],
                variants_array = options["variants"],
                active_variant = options["active_variant"],
                variants = construct(variants_array, "variant_id"),
                templates = options["templates"];

            var dialog = $wrapper.data("dialog"),
                show_map = dialog.options.show_map,
                dropdown = dialog.options.dropdown;

            var pickup_dialog = new PickupDialog({
                $wrapper: $wrapper,
                dialog: dialog,

                variants: variants,
                active_variant: active_variant,
                scope: that,
                templates: templates,
                show_map: show_map,

                set: function(variant_id) {
                    if (variants[variant_id]) {
                        var variant = variants[variant_id];
                        var template = "<div class=\"wa-dropdown-item js-set-dropdown-item\" data-id=\"%variant_id%\"><div class=\"wa-delivery-variant\"><div class=\"wa-name\" data-name=\"%variant_name%\"></div></div></div>";
                        template = template.replace("%variant_id%", variant_id).replace("%variant_name%", variant.name);
                        dropdown.$menu.append(template);

                        var $target = dropdown.$menu.find(".js-set-dropdown-item[data-id=\"" + variant_id + "\"]");
                        $target.trigger("click");

                    } else {
                        that.scope.DEBUG("Variants is missing", "error", variant_id);
                    }
                }
            });

            that.scope.DEBUG("Pickup Dialog initialized", "info", pickup_dialog);
        };

        // REQUIRED

        /**
         * @return {Array}
         * */
        Shipping.prototype.getData = function(options) {
            var that = this,
                result = [];

            if (that.disabled) {
                result.push({
                    name: "shipping[html]",
                    value: "only"
                });

            } else if (options.clean) {
                result.push({
                    name: "shipping[html]",
                    value: "only"
                });

            } else {
                var render_errors = (options.render_errors !== false);

                if (that.$form.length) {
                    result = that.$form.serializeArray();
                }

                if (!options.only_api && that.reload) {
                    result.push({
                        name: "shipping[html]",
                        value: "only"
                    });
                }

                var errors = that.scope.validate(that.$form);
                var error_class = "wa-error";

                var $type_section = that.$wrapper.find("#js-delivery-types-section");
                if ($type_section.length) {
                    var $types_list =  $type_section.find(".wa-types-list"),
                        $type_field = $type_section.find(".js-type-field"),
                        type_id = $.trim( $type_field.val() );

                    if (!type_id.length) {
                        errors.push({
                            $wrapper: $types_list,
                            id: "type_required",
                            value: that.locales["type_required"]
                        });

                        if (render_errors) {
                            if (!$types_list.hasClass(error_class)) {
                                var $error = $("<div class=\"wa-error-text\" />").text(that.locales["type_required"]).insertAfter($types_list);

                                $types_list.addClass(error_class)
                                    .one("change", function() {
                                        $types_list.removeClass(error_class);
                                        $error.remove();
                                    });
                            }
                        }
                    }
                }

                var variant_id = null;

                var $variant_section = that.$wrapper.find("#js-delivery-variants-section");
                if ($variant_section.length) {
                    var $variants_list =  $variant_section.find(".js-variants-select"),
                        $variant_field = $variant_section.find(".js-variant-field");

                    variant_id = $.trim( $variant_field.val() );

                    if (!variant_id.length) {
                        errors.push({
                            $wrapper: $variants_list,
                            id: "variant_required",
                            value: that.locales["variant_required"]
                        });

                        if (render_errors) {
                            if (!$variants_list.hasClass(error_class)) {
                                var $variant_error = $("<div class=\"wa-error-text\" />").text(that.locales["variant_required"]).insertAfter($variants_list);

                                $variants_list.addClass(error_class)
                                    .one("change", function() {
                                        $variants_list.removeClass(error_class);
                                        $variant_error.remove();
                                    });
                            }
                        }
                    }
                }

                var $short_variants_section = that.$wrapper.find("#js-delivery-short-variants-section");
                if ($short_variants_section.length) {
                    var $short_variants_list =  $short_variants_section.find(".js-variants-select"),
                        $short_variant_field = $short_variants_section.find(".js-variant-field");

                    variant_id = $.trim( $short_variant_field.val() );

                    if (!variant_id.length) {
                        errors.push({
                            $wrapper: $short_variants_list,
                            id: "variant_required",
                            value: that.locales["variant_required"]
                        });

                        if (render_errors) {
                            if (!$short_variants_list.hasClass(error_class)) {
                                var $short_variant_error = $("<div class=\"wa-error-text\" />").text(that.locales["variant_required"]).insertAfter($short_variants_list);

                                $short_variants_list.addClass(error_class)
                                    .one("change", function() {
                                        $short_variants_list.removeClass(error_class);
                                        $short_variant_error.remove();
                                    });
                            }
                        }
                    }
                }

                if (errors.length) {
                    result.errors = errors;
                }
            }

            return result;
        };

        Shipping.prototype.render = function(api) {
            var that = this,
                is_changed = false;

            if (api.shipping && api.shipping["html"]) {
                that.$wrapper.replaceWith(api.shipping["html"]);
                is_changed = true;
            }

            return is_changed;
        };

        /**
         * @param {Object} errors
         * @return {Array}
         * */
        Shipping.prototype.renderErrors = function(errors) {
            var that = this,
                result = [];

            $.each(errors, function(i, error) {
                result.push(error);
            });

            return result;
        };

        // PROTECTED

        Shipping.prototype.update = function(data) {
            var that = this;

            that.reload = !!data.reload;

            return that.scope
                .update({
                    sections: ["auth", "region", "shipping", "details", "confirm"]
                })
                .always( function() {
                    that.reload = true;
                });
        };

        return Shipping;

        function construct(data, key) {
            var result = {};

            if (key) {
                $.each(data, function(i, item) {
                    if (item[key]) {
                        result[item[key]] = item;
                    }
                });
            }

            return result;
        }

    })($);

    var Details = ( function($) {

        Details = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$form = that.$wrapper.find("form:first");

            // VARS
            that.templates = options["templates"];
            that.disabled = options["disabled"];
            that.errors = options["errors"];
            that.scope = options["scope"];

            // DYNAMIC VARS
            that.reload = true;

            // INIT
            that.initClass();
        };

        Details.prototype.initClass = function() {
            var that = this;

            if (typeof that.errors === "object" && Object.keys(that.errors).length ) {
                that.scope.DEBUG("Errors:", "error", that.errors);
                that.renderErrors(that.errors);
            }

            that.$form.on("submit", function(event) {
                event.preventDefault();
            });

            that.$wrapper.on("change", "select, textarea, input", function(event) {
                var $field = $(this),
                    reload = !!$field.data("affects-rate") || !!$field.data("reload");

                var $field_wrapper = $field.closest(".wa-field-wrapper");
                if (!$field_wrapper.length) {
                    $field_wrapper = $field.parent();
                }

                var error = that.scope.validate($field_wrapper, true);
                if (!error.length) {
                    if (reload) {
                        that.update({
                            reload: true
                        });
                    }
                }
            });

            that.initPhotos();
        };

        Details.prototype.initPhotos = function() {
            var that = this,
                $photos_section = that.$wrapper.find(".wa-photos-section");

            if (!$photos_section.length) { return false; }

            // horizontal move photos
            initSlider();

            // click on photo
            initOpen();

            function initSlider() {

                var Slider = ( function($) {

                    Slider = function(options) {
                        var that = this;

                        // DOM
                        that.$wrapper = options["$wrapper"];
                        that.$slides = options["$slides"];
                        that.$left_arrow = options["$left_arrow"];
                        that.$right_arrow = options["$right_arrow"];

                        // VARS
                        that.disable_class = "is-disabled";

                        // DYNAMIC VARS
                        that.wrapper_w = null;
                        that.scroll_w = null;
                        that.slide_w = null;
                        that.left = 0;
                        that.is_locked = false;

                        // INIT
                        that.initClass();
                    };

                    Slider.prototype.initClass = function() {
                        var that = this,
                            $window = $(window);

                        // INIT

                        that.setData();

                        that.showArrows();

                        // EVENTS

                        that.$wrapper.on("scroll", function(event) {
                            that.left = that.$wrapper.scrollLeft();
                        });

                        that.$left_arrow.on("click", function(event) {
                            event.preventDefault();
                            var is_locked = that.$left_arrow.hasClass(that.disable_class);
                            if (!is_locked) {
                                that.move(false);
                            }
                        });

                        that.$right_arrow.on("click", function(event) {
                            event.preventDefault();
                            var is_locked = that.$right_arrow.hasClass(that.disable_class);
                            if (!is_locked) {
                                that.move(true);
                            }
                        });

                        $window.on("resize", onResize);

                        function onResize() {
                            var is_exist = $.contains(document, that.$wrapper[0]);
                            if (is_exist) {
                                var is_change = ( that.wrapper_w !== that.$wrapper.outerWidth() );
                                if (is_change) {
                                    that.reset();
                                }
                            } else {
                                $window.off("resize", onResize);
                            }
                        }
                    };

                    Slider.prototype.setData = function() {
                        var that = this;

                        that.wrapper_w = that.$wrapper.outerWidth();
                        that.scroll_w = that.$wrapper[0].scrollWidth;
                        that.slide_w = that.$slides.first().outerWidth(true);
                    };

                    Slider.prototype.showArrows = function() {
                        var that = this,
                            disable_class = that.disable_class;

                        if (that.left > 0) {
                            that.$left_arrow.removeClass(disable_class);

                            if (that.scroll_w - that.wrapper_w - that.left > 0) {
                                that.$right_arrow.removeClass(disable_class);
                            } else {
                                that.$right_arrow.addClass(disable_class);
                            }

                        } else {
                            that.$left_arrow.addClass(disable_class);
                            if (that.scroll_w - that.wrapper_w > 0) {
                                that.$right_arrow.removeClass(disable_class);
                            } else {
                                that.$right_arrow.addClass(disable_class);
                            }
                        }
                    };

                    Slider.prototype.set = function(value) {
                        var that = this;

                        if (!that.is_locked) {
                            that.is_locked = true;

                            value = (value ? parseFloat(value) : 0);
                            if (!(value >= 0)) { value = 0; }

                            that.$wrapper.animate({
                                scrollLeft: value
                            }, 200, function() {
                                that.is_locked = false;
                            });

                            that.left = value;
                        }
                    };

                    Slider.prototype.move = function(right) {
                        var that = this,
                            step = that.slide_w * 4,
                            delta = (that.scroll_w - that.wrapper_w),
                            new_left = 0;

                        if (delta > 0) {
                            if (right) {
                                new_left = that.left + step;

                                if (new_left % that.slide_w > 0) {
                                    new_left = parseInt(that.left/that.slide_w) * that.slide_w;
                                }

                                if (new_left > delta) { new_left = delta; }

                            } else {
                                new_left = that.left - step;

                                if (new_left % that.slide_w > 0) {
                                    new_left = parseInt(that.left/that.slide_w) * that.slide_w;
                                }

                                if (new_left < 0) { new_left = 0; }
                            }
                        }

                        that.set(new_left);
                        that.showArrows();
                    };

                    Slider.prototype.reset = function() {
                        var that = this;

                        that.set();
                        that.setData();
                        that.showArrows();
                    };

                    return Slider;

                })($);

                var $list = $photos_section.find(".wa-photos-list"),
                    $photos = $list.find(".wa-photo-wrapper");

                var slider = new Slider({
                    $wrapper: $list,
                    $slides: $photos,
                    $left_arrow: $photos_section.find(".js-scroll-prev"),
                    $right_arrow: $photos_section.find(".js-scroll-next")
                });
            }

            function initOpen() {

                var dialog_name = $photos_section.data("name");

                $photos_section.on("click", ".js-show-photo", function(event) {
                    event.preventDefault();
                    showDialog($(this).closest(".wa-photo-wrapper"));
                });

                function showDialog($photo_wrapper) {
                    var template = that.templates["photo"].replace("%title%", dialog_name);

                    var dialog = new that.scope.ui.Dialog({
                        $wrapper: $(template),
                        onOpen: function($dialog, dialog) {
                            var $section = $dialog.find(".js-photo-slider-section");
                            if ($section.length) {
                                initPhotoSection($section, {
                                    index: $photo_wrapper.index(),
                                    onChange: function () {
                                        dialog.resize();
                                    }
                                });
                            }
                        }
                    });

                    function initPhotoSection($section, options) {
                        // DOM
                        var $body = $section.find(".wa-photo-body"),
                            $image = $("<img />").appendTo($body);

                        // CONST
                        var photos = getPhotos();

                        // VARS
                        var onChange = (options.onChange || function() {});
                        var index = (options["index"] || 0);

                        // INIT

                        setPhoto(photos[index]);

                        // EVENTS

                        $(document).on("keyup", keyWatcher);

                        function keyWatcher(event) {
                            var is_exist = $.contains(document, $section[0]);
                            if (is_exist) {
                                var code = event.keyCode;
                                if (code === 37) {
                                    show(false);
                                } else if (code === 39) {
                                    show(true);
                                }

                            } else {
                                $(document).off("keyup", keyWatcher);
                            }
                        }

                        $section.on("click", ".js-show-prev", function(event) {
                            event.preventDefault();
                            show(false);
                        });

                        $section.on("click", ".js-show-next", function(event) {
                            event.preventDefault();
                            show(true);
                        });

                        // FUNCTIONS

                        function show(next) {
                            if (next) {
                                index = (index + 1 < photos.length ? index + 1 : 0);
                            } else {
                                index = ( index > 0 ? index - 1 : photos.length - 1 );
                            }

                            setPhoto(photos[index]);
                        }

                        function setPhoto(photo) {
                            $image.attr("src", photo.thumb_uri);

                            var $pseudo_image = $("<img />");

                            $pseudo_image.on("load", function() {
                                if ($image.attr("src") === photo.thumb_uri) {
                                    $image.attr("src", photo.image_uri);
                                    onChange();
                                }
                            });

                            $pseudo_image.attr("src", photo.image_uri);
                        }
                    }
                }

                function getPhotos() {
                    var result = [];

                    $photos_section.find(".wa-photo-wrapper").each( function() {
                        var $wrapper = $(this),
                            image_uri = $wrapper.data("image-uri"),
                            thumb_uri = $wrapper.data("thumb-uri");

                        result.push({
                            image_uri: image_uri,
                            thumb_uri: thumb_uri
                        });
                    });

                    return result;
                }
            }
        };

        // REQUIRED

        /**
         * @return {Array}
         * */
        Details.prototype.getData = function(options) {
            var that = this,
                result = [];

            if (that.disabled) {
                result.push({
                    name: "details[html]",
                    value: "only"
                });

            } else if (options.clean) {
                result.push({
                    name: "details[html]",
                    value: "only"
                });

            } else {
                var render_errors = (options.render_errors !== false);

                if (that.$form.length) {
                    result = that.$form.serializeArray();
                }

                if (!options.only_api && that.reload) {
                    result.push({
                        name: "details[html]",
                        value: "only"
                    });
                }

                var errors = that.scope.validate(that.$form, render_errors);
                if (errors.length) {
                    result.errors = errors;
                }
            }

            return result;
        };

        Details.prototype.render = function(api) {
            var that = this,
                is_changed = false;

            if (api.details && api.details["html"]) {
                that.$wrapper.replaceWith(api.details["html"]);
                is_changed = true;
            }

            return is_changed;
        };

        /**
         * @param {Object} errors
         * @return {Array}
         * */
        Details.prototype.renderErrors = function(errors) {
            var that = this,
                result = [];

            $.each(errors, function(i, error) {
                if (error.name && error.text) {
                    var $field = that.$wrapper.find("[name=\"" + error.name + "\"]");
                    if ($field.length) {
                        error.$field = $field;
                        renderError(error);
                    }
                }

                result.push(error);
            });

            return result;

            function renderError(error) {
                var $error = $("<div class=\"wa-error-text\" />").text(error.text);
                var error_class = "wa-error";

                if (error.$field) {
                    var $field = error.$field;

                    if (!$field.hasClass(error_class)) {
                        $field.addClass(error_class);

                        var $field_wrapper = $field.closest(".wa-field-wrapper");
                        if ($field_wrapper.length) {
                            $field_wrapper.append($error);
                        } else {
                            $error.insertAfter($field);
                        }

                        $field.on("change focus", removeFieldError);
                    }
                }

                function removeFieldError() {
                    $field.removeClass(error_class);
                    $error.remove();

                    $field.off("change focus", removeFieldError);
                }
            }
        };

        // PROTECTED

        Details.prototype.update = function(data) {
            var that = this;

            that.reload = !!data.reload;

            that.scope.update()
                .always( function() {
                    that.reload = true;
                });
        };

        return Details;

    })($);

    var Payment = ( function($) {

        Payment = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$form = that.$wrapper.find("form:first");

            // VARS
            that.disabled = options["disabled"];
            that.locales = options["locales"];
            that.errors = options["errors"];
            that.scope = options["scope"];

            // DYNAMIC VARS
            that.reload = true;

            // INIT
            that.initClass();
        };

        Payment.prototype.initClass = function() {
            var that = this;

            if (typeof that.errors === "object" && Object.keys(that.errors).length ) {
                that.scope.DEBUG("Errors:", "error", that.errors);
            }

            that.$form.on("submit", function(event) {
                event.preventDefault();
            });

            that.$wrapper.on("change", "select, textarea, input", function(event) {
                var $field = $(this),
                    reload = !!$field.data("affects-rate") || !!$field.data("reload");

                var $field_wrapper = $field.closest(".wa-field-wrapper");
                if (!$field_wrapper.length) {
                    $field_wrapper = $field.parent();
                }

                var error = that.scope.validate($field_wrapper, true);
                if (!error.length) {
                    if (reload) {
                        that.update({
                            reload: true
                        });
                    }
                }
            });

            that.initMethods();
        };

        Payment.prototype.initMethods = function() {
            var that = this;

            // DOM
            var $list = that.$wrapper.find(".js-methods-list");
            if (!$list.length) { return false; }

            // CONST
            var active_class = "is-active";

            // DYNAMIC VARS
            var $active_method = $list.find(".wa-method-wrapper." + active_class);

            $list.on("click", ".wa-method-wrapper", function(event) {
                event.preventDefault();

                var $method = $(this);

                if ($active_method.length) {
                    if ($active_method[0] !== $method[0]) {
                        setActive($method);
                    }
                } else {
                    setActive($method);
                }
            });

            function setActive($method) {
                var $field = $method.find(".js-method-field");

                if ($active_method.length) {
                    $active_method.removeClass(active_class);
                }

                $active_method = $method.addClass(active_class);

                $field.attr("checked", true).trigger("change");
            }
        };

        // REQUIRED

        /**
         * @return {Array}
         * */
        Payment.prototype.getData = function(options) {
            var that = this,
                result = [];

            if (that.disabled) {
                result.push({
                    name: "payment[html]",
                    value: "only"
                });

            } else if (options.clean) {
                result.push({
                    name: "payment[html]",
                    value: "only"
                });

            } else {
                var render_errors = (options.render_errors !== false);

                if (that.$form.length) {
                    result = that.$form.serializeArray();
                }

                if (!options.only_api && that.reload) {
                    result.push({
                        name: "payment[html]",
                        value: 1
                    });
                }

                var errors = that.scope.validate(that.$wrapper, render_errors);

                var $list = that.$wrapper.find(".js-methods-list");
                if ($list.length) {
                    var $method_field = $list.find(".js-method-field:checked");
                    if (!$method_field.length) {

                        errors.push({
                            $wrapper: $list,
                            id: "method_required",
                            value: that.locales["method_required"]
                        });

                        if (render_errors) {
                            var error_class = "wa-error";

                            if (!$list.hasClass(error_class)) {
                                var $error = $("<div class=\"wa-error-text\" />").text(that.locales["method_required"]).insertAfter($list);

                                $list.
                                    addClass(error_class)
                                    .one("change", function() {
                                        $list.removeClass(error_class);
                                        $error.remove();
                                    });
                            }
                        }
                    }
                }

                if (errors.length) {
                    result.errors = errors;
                }
            }

            return result;
        };

        Payment.prototype.render = function(api) {
            var that = this,
                is_changed = false;

            if (api.payment && api.payment["html"]) {
                that.$wrapper.replaceWith(api.payment["html"]);
                is_changed = true;
            }

            return is_changed;
        };

        /**
         * @param {Object} errors
         * @return {Array}
         * */
        Payment.prototype.renderErrors = function(errors) {
            var that = this,
                result = [];

            $.each(errors, function(i, error) {
                result.push(error);
            });

            return result;
        };

        // PROTECTED

        Payment.prototype.update = function(options) {
            var that = this;

            if (options.reload) {
                that.reload = true;
            }

            return that.scope.update();
        };

        return Payment;

    })($);

    var Confirm = ( function($) {

        Confirm = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$form = that.$wrapper.find("form:first");
            that.$submit_button = that.$wrapper.find(".js-submit-order-button");

            // VARS
            that.templates = options["templates"];
            that.errors = options["errors"];
            that.scope = options["scope"];
            that.urls = options["urls"];

            // DYNAMIC VARS
            that.reload = true;
            that.is_locked = false;
            that.is_channel_confirm_skiped = false;

            // INIT
            that.initClass();
        };

        Confirm.prototype.initClass = function() {
            var that = this;

            if (typeof that.errors === "object" && Object.keys(that.errors).length ) {
                that.scope.DEBUG("Errors:", "error", that.errors);
                that.renderErrors(that.errors);
            }

            that.$form.on("submit", function(event) {
                event.preventDefault();
            });

            that.$wrapper.on("change", "select, input, textarea", function(event) {
                var $field = $(this),
                    $field_wrapper = $field.closest(".wa-field-wrapper"),
                    reload = $field.data("reload");

                if (reload) {
                    that.update({
                        reload: true
                    });

                } else {
                    if ($field_wrapper.length) {
                        that.scope.validate($field_wrapper, true);
                    }
                }
            });

            var $comment_section = that.$wrapper.find(".wa-comment-section");
            if ($comment_section.length) {
                var open_class = "is-opened";
                $comment_section.on("click", " .js-open-section", function(event) {
                    event.preventDefault();
                    $comment_section.toggleClass(open_class);
                });
            }

            that.$wrapper.on("click", ".js-show-terms-dialog", function(event) {
                event.preventDefault();
                if (that.templates["terms_dialog"]) {
                    new that.scope.ui.Dialog({
                        $wrapper: $(that.templates["terms_dialog"])
                    });
                }
            });

            var start_form_data_json = "";

            that.scope.$wrapper.on("ready changed", changeWatcher);
            function changeWatcher() {
                var is_exist = $.contains(document, that.$wrapper[0]);
                if (is_exist) {
                    start_form_data_json = JSON.stringify(that.scope.getFormData());
                } else {
                    that.scope.$wrapper.off("ready changed", changeWatcher);
                }
            }

            that.$submit_button.on("click", function(event) {
                event.preventDefault();

                if (!that.is_locked) {
                    that.is_locked = true;

                    var promise = null;

                    var is_create = (that.$submit_button.data("action") === "create");
                    if (is_create) {
                        var finish_form_data_json = JSON.stringify(that.scope.getFormData()),
                            is_changed = ( start_form_data_json !== finish_form_data_json );

                        if (!is_changed) {
                            promise = that.create();
                        } else {
                            promise = that.scope.update({
                                render_errors: true
                            });
                        }
                    } else {
                        promise = that.scope.update({
                            render_errors: true
                        });
                    }

                    promise
                        .always( function() {
                            that.is_locked = false;
                        })
                        .done( function(api) {
                            if (api.order_id) {
                                that.is_locked = true;
                            } else {
                                if (!api.errors) {
                                    var confirm_section = that.scope.sections["confirm"];
                                    confirm_section.$submit_button.trigger("click");
                                }
                            }
                        }).fail( function(state, errors) {
                            if (errors && errors.length) {
                                focus(errors[0]);
                            }
                        });
                }
            });
        };

        // REQUIRED

        /**
         * @return {Array}
         * */
        Confirm.prototype.getData = function(options) {
            var that = this,
                result = [];

            if (options.clean) {
                result.push({
                    name: "confirm[html]",
                    value: "only"
                });

            } else {
                var render_errors = (options.render_errors !== false);

                if (that.$form.length) {
                    result = that.$form.serializeArray();
                }

                if (!options.only_api && that.reload) {
                    result.push({
                        name: "confirm[html]",
                        value: 1
                    });
                }

                var errors = that.scope.validate(that.$wrapper, render_errors);
                if (errors.length) {
                    result.errors = errors;
                }
            }

            return result;
        };

        Confirm.prototype.render = function(api) {
            var that = this,
                is_changed = false;

            if (api.confirm && api.confirm["html"]) {
                that.$wrapper.replaceWith(api.confirm["html"]);
                is_changed = true;
            }

            return is_changed;
        };

        /**
         * @param {Object} errors
         * @return {Array}
         * */
        Confirm.prototype.renderErrors = function(errors) {
            var that = this,
                result = [],
                focus = true;

            var fatal_errors = [];
            var simple_errors = [];

            $.each(errors, function(i, error) {
                if (error.id === "cart_invalid") {
                    fatal_errors.push(error);
                } else {
                    simple_errors.push(error);
                }
            });

            if (fatal_errors.length) {
                render(fatal_errors);
            } else if (simple_errors.length) {
                render(simple_errors);
                result = simple_errors;
            }

            result.focus = focus;

            return result;

            function render(errors) {
                $.each(errors, function(i, error) {
                    switch (error.id) {
                        case "cart_invalid":
                            $.each(that.scope.sections, function(section_id, section) {
                                if (section_id !== "confirm") {
                                    section.$wrapper.hide();
                                }
                                that.$wrapper.addClass("is-single");
                            });

                            that.$wrapper.trigger("wa_order_cart_invalid");
                            break;

                        case "confirm_channel":
                            var type = error.type,
                                required = !!error.auth_with_code;

                            that.showChannelConfirmDialog(type, required);
                            focus = false;
                            break;

                        default:
                            break;
                    }
                });
            }

            function showConfirmDialog(error) {

            }
        };

        // PROTECTED

        Confirm.prototype.update = function(options) {
            var that = this;

            if (options.reload) {
                that.reload = true;
            }

            return that.scope.update();
        };

        Confirm.prototype.create = function() {
            var that = this,
                deferred = $.Deferred();

            var errors = that.scope.validate(that.scope.$wrapper, true);
            if (errors.length) {
                that.scope.DEBUG("Errors:", "error", errors);
                focus(errors[0]);
                deferred.reject();

            } else {
                create(deferred);
            }

            return deferred.promise();

            function create(deferred) {
                return that.scope.update({
                    create: true,
                    render_errors: true
                }).then( function(api) {
                    if (api.order_id) {
                        that.scope.trigger("created", api);
                        that.scope.lock(true);
                        try {
                            location.href = that.scope.urls.success;
                        } catch (e) {
                            that.scope.DEBUG(e.message, "error");
                        }
                    } else {
                        var errors = that.scope.renderErrors(api);
                        if (errors.length && errors.focus) {
                            focus(errors[0]);
                        }
                    }
                    deferred.resolve(api);
                }, function(state, response) {
                    if (state === "front_errors") {
                        if (response.length) {
                            focus(response[0]);
                        }
                    }
                    deferred.reject();
                });
            }
        };

        Confirm.prototype.showChannelConfirmDialog = function(type) {
            var that = this;

            var href = that.urls["channel_dialog"],
                data = {
                    source: getSource()
                };

            that.scope.lock(true);

            $.post(href, data).done( function(response) {
                if (response.status === "ok") {
                    var html = response.data.confirmation_dialog;

                    var $wrapper = $(html);
                    $wrapper.data("scope", that);

                    var dialog = new that.scope.ui.Dialog({
                        $wrapper: $wrapper,
                        options: {
                            onSuccess: function(data) {
                                if (data.type === "phone") {
                                    var $phone_field = $("input[name='auth[data][phone]']");
                                    if ($phone_field.length) {
                                        $phone_field.val(data.value);
                                    }

                                } else if (data.type === "email") {
                                    var $email_field = $("input[name='auth[data][email]']");
                                    if ($email_field.length) {
                                        $email_field.val(data.value);
                                    }
                                }

                                create();
                            },
                            onSkip: function() {
                                that.is_channel_confirm_skiped = true;
                                create();
                            }
                        },
                        onClose: function () {
                            that.scope.lock(false);
                        }
                    });
                } else {
                    that.scope.DEBUG("Channel confirm dialog error", "error");
                }
            });

            function getSource() {
                var result = "";

                if (type === "phone") {
                    var $phone_field = $("input[name='auth[data][phone]']");
                    if ($phone_field.length) {
                        result = $phone_field.val();
                    }

                } else if (type === "email") {
                    var $email_field = $("input[name='auth[data][email]']");
                    if ($email_field.length) {
                        result = $email_field.val();
                    }
                }

                return result;
            }

            function create() {
                that.$submit_button.trigger("click");
            }
        };

        Confirm.prototype.initChannelConfirm = function(options) {

            var ChannelConfirmDialog = ( function($) {

                ChannelConfirmDialog = function(options) {
                    var that = this;

                    // DOM
                    that.$wrapper = options["$wrapper"];
                    that.$code_field = that.$wrapper.find(".js-code-field");
                    that.$value_field = that.$wrapper.find(".js-value-field");
                    that.$errors_w = that.$wrapper.find(".js-errors-wrapper");

                    // VARS
                    that.recode_timeout = options["recode_timeout"];
                    that.dialog = that.$wrapper.data("dialog");
                    that.scope = that.$wrapper.data("scope");
                    that.locales = options["locales"];
                    that.urls = options["urls"];
                    that.type = options["type"];

                    // DYNAMIC VARS

                    // INIT
                    that.init();
                };

                ChannelConfirmDialog.prototype.init = function() {
                    var that = this;

                    that.initSendCode();

                    that.initSubmit();
                };

                ChannelConfirmDialog.prototype.initSendCode = function() {
                    var that = this,
                        is_locked = false,
                        resend_locked = false,
                        interval = 0;

                    var $send_line = that.$wrapper.find(".js-send-line"),
                        $code_line = that.$wrapper.find(".js-code-line"),
                        // $value_content = that.$wrapper.find(".js-value-content"),
                        $submit_line = that.$wrapper.find(".js-submit-line"),
                        $resend = $code_line.find(".js-resend-code"),
                        $time_w = $code_line.find(".js-timer-wrapper"),
                        $time =  $time_w.find(".wa-time");

                    that.$wrapper.on("click", ".js-send-code", function(event) {
                        event.preventDefault();

                        var value = that.$value_field.val(),
                            value_validate = validate(value, that.type);

                        if (!value_validate) {
                            that.renderError({
                                $field: that.$value_field,
                                text: that.locales["invalid"]
                            });
                            return false;
                        }

                        sendCode().then( function() {
                            toggle(true);
                            setTimer();
                        }, function(errors) {
                            that.renderErrors(errors);
                        });
                    });

                    that.$wrapper.on("click", ".js-resend-code", function(event) {
                        event.preventDefault();

                        if (!resend_locked) {
                            resend_locked = true;

                            sendCode().then( function() {
                                setTimer();
                                resend_locked = false;
                            }, function(errors) {
                                that.renderErrors(errors);
                            });
                        }
                    });

                    that.$wrapper.on("click", ".js-edit-value", function(event) {
                        event.preventDefault();
                        toggle(false);
                        // $value_content.hide();
                    });

                    function toggle(show) {
                        if (show) {
                            that.$value_field.attr("readonly", true);
                            $send_line.hide();
                            $code_line.show();
                            $submit_line.show();

                        } else {
                            that.$value_field.attr("readonly", false);
                            $send_line.show();
                            $code_line.hide();
                            $submit_line.hide();
                        }
                    }

                    function timerToggle(show) {
                        if (show) {
                            $time_w.hide();
                            $resend.show();
                            // $value_content.show();
                        } else {
                            $resend.hide();
                            $time_w.show();
                            // $value_content.hide();
                        }
                    }

                    function sendCode() {
                        var deferred = $.Deferred(),
                            value = that.$value_field.val();

                        if (!value.length) {
                            that.$value_field.focus();
                            deferred.reject();

                        } else if (!is_locked) {
                            is_locked = true;

                            var href = that.urls["code"],
                                data = {
                                    source: value,
                                    type: that.type
                                };

                            $.post(href, data)
                                .done( function(response) {
                                    if (response.status === "ok") {
                                        deferred.resolve();
                                    } else {
                                        var errors = [];

                                        if (response.errors) {
                                            errors = response.errors;
                                        }

                                        deferred.reject(errors);
                                    }

                                })
                                .fail( function() {
                                    deferred.reject();
                                })
                                .always( function() {
                                    is_locked = false;
                                });
                        }

                        return deferred.promise();
                    }

                    function setTimer() {
                        var deferred = $.Deferred(),
                            interval_time = 0,
                            time = that.recode_timeout;

                        timerToggle(false);

                        $time.html( getTimeString(time) );

                        interval = setInterval( function() {
                            interval_time++;

                            if (!$.contains(document, $time[0])) {
                                clearInterval(interval);
                                deferred.reject();

                            } else {
                                if (interval_time >= time) {
                                    clearInterval(interval);
                                    timerToggle(true);
                                    deferred.resolve();

                                } else {
                                    $time.html( getTimeString(time - interval_time) );
                                }
                            }
                        }, 1000);

                        return deferred.promise();

                        function getTimeString(time) {
                            time = parseInt(time);
                            if ( !(time >= 0) ) { return ""; }

                            var minutes = parseInt(time/60),
                                seconds = time - (minutes * 60);

                            if (minutes < 10) {
                                minutes = "0" + minutes;
                            }

                            if (seconds < 10) {
                                seconds = "0" + seconds;
                            }

                            return minutes + ":" + seconds;
                        }
                    }
                };

                ChannelConfirmDialog.prototype.initSubmit = function() {
                    var that = this,
                        is_locked = false;

                    that.$wrapper.on("click", ".js-submit-confirm", function(event) {
                        event.preventDefault();
                        onSubmit();
                    });

                    that.$wrapper.on("click", ".js-skip-confirm", function(event) {
                        event.preventDefault();
                        that.dialog.close();
                        that.dialog.options.onSkip();
                    });

                    function onSubmit() {
                        if (!is_locked) {
                            is_locked = true;

                            var code = that.$code_field.val(),
                                value = that.$value_field.val(),
                                value_validate = validate(value, that.type);

                            if (!code.length) {
                                that.renderError({
                                    $field: that.$code_field,
                                    text: that.locales["code_empty"]
                                });
                                is_locked = false;
                                return false;
                            }

                            if (!value_validate) {
                                that.renderError({
                                    $field: that.$value_field,
                                    text: that.locales["invalid"]
                                });
                                is_locked = false;
                                return false;
                            }

                            var href = that.urls["submit"],
                                data = {
                                    code: code,
                                    value: value
                                };

                            $.post(href, data)
                                .done( function(response) {
                                    if (response.errors) {
                                        that.renderErrors(response.errors);
                                    } else {
                                        that.dialog.close();
                                        that.dialog.options.onSuccess({
                                            type: that.type,
                                            value: value
                                        });
                                    }
                                })
                                .always( function() {
                                    is_locked = false;
                                });
                        }
                    }
                };

                ChannelConfirmDialog.prototype.renderErrors = function(errors) {
                    var that = this;

                    that.$errors_w.html("");

                    if (errors.length) {
                        $.each(errors, function(i, error) {
                            if (error.text) {
                                var $error = $("<div class=\"wa-error-text\" />").text(error.text);
                                that.$errors_w.append($error);
                            }
                        });
                    }

                    that.scope.scope.DEBUG("ERRORS:", "error", errors);
                };

                ChannelConfirmDialog.prototype.renderError = function(error) {
                    var that = this;
                    var $error = $("<div class=\"wa-error-text\" />").text(error.text);
                    var error_class = "wa-error";

                    if (error.$field) {
                        var $field = error.$field;

                        if (!$field.hasClass(error_class)) {
                            $field.addClass(error_class);

                            var $field_wrapper = $field.closest(".wa-field-wrapper");
                            if ($field_wrapper.length) {
                                $field_wrapper.append($error);
                            } else {
                                $error.insertAfter($field);
                            }

                            $field.on("change keyup", removeFieldError);
                        }
                    }

                    function removeFieldError() {
                        $field.removeClass(error_class);
                        $error.remove();
                        $field.off("change", removeFieldError);
                    }
                };

                return ChannelConfirmDialog;

                /**
                 * @param {String} value
                 * @param {String} type
                 * */
                function validate(value, type) {
                    var result = false;

                    switch (type) {
                        case "phone":
                            result = window.waOrder.ui.validate.phone(value);
                            break;
                        case "email":
                            result = window.waOrder.ui.validate.email(value);
                            break;
                        default:
                            result = true;
                            break;
                    }

                    return result;
                }

            })($);

            new ChannelConfirmDialog(options);
        };

        return Confirm;

        function focus(error) {
            var scroll_top = 0,
                is_mobile = isMobile(),
                lift = ( is_mobile ? 70 : 40 );

            if (error["$field"] && error["$field"].length) {
                scroll_top = getTop(error["$field"]);
                error["$field"].focus();
            }

            if (error["$wrapper"] && error["$wrapper"].length) {
                scroll_top = error["$wrapper"].offset().top;
            }

            $("html, body").scrollTop(scroll_top - lift);

            function getTop($wrapper) {
                var result = 0,
                    is_visible = $wrapper.is(":visible");

                if (is_visible) {
                    result = $wrapper.offset().top;

                } else {
                    var $parent = $wrapper.parent();
                    if ($parent.length) {
                        result = getTop($parent);
                    }
                }

                return result;
            }
        }

    })($);

    // SCOPE

    var Form = ( function($) {

        /**
         * @description constructor
         * */
        Form = function(options) {
            var that = this;

            if ( !(options.outer_options && (typeof options.outer_options.wrapper === "string") && options.outer_options.wrapper.length > 0) ) {
                throw new Error('Checkout wrapper element not specified.');
            }

            var $outer_wrapper = $(options.outer_options["wrapper"]);
            if ($outer_wrapper.length !== 1) {
                throw new Error('Error: Checkout form wrapper element must be exactly one on page (found '+ $outer_wrapper.length + ')');
            }

            if (!options.urls["calculate"]) {
                throw new Error('Must specify url_calculate');
            }

            if (!options.urls["create"]) {
                throw new Error('Must specify url_create_order');
            }

            // DOM
            that.$outer_wrapper = $outer_wrapper;
            that.$wrapper = options["$wrapper"];

            // CONST
            that.use_storage = options["use_storage"];
            that.options = options["outer_options"];
            that.templates = options["templates"];
            that.locales = options["locales"];
            that.urls = options["urls"];
            that.ui = window.waOrder.ui;

            // VARS
            that.is_updating = false;
            that.sections = {};
            that.render_scheduled = false;
            that.calculate_promise = null;
            that.create_promise = null;

            // XHR
            that.calculate_xhr = null;
            that.reload_xhr = null;
            that.create_xhr = null;

            // that.blocks_order = []; // list of string keys in this.blocks
            // that.blocks = {}; // block_id: block object

            that.initClass();
        };

        Form.prototype.initClass = function() {
            var that = this,
                $document = $(document);

            var invisible_class = "js-invisible-content";
            that.$wrapper.find(".wa-form-body > .wa-form-loader").remove();
            that.$wrapper.removeClass("is-not-ready")
                .find("." + invisible_class).removeAttr("style").removeClass(invisible_class);

            that.$outer_wrapper.data("controller", that);

            // START

            var ready_promise = that.$wrapper.data("ready");
            ready_promise.resolve(that);
            that.trigger("ready", that);

            that.$wrapper.on("region_change", onRegionChange);
            function onRegionChange() {
                var data = that.getFormData({
                    sections: ["auth", "region", "details", "confirm"]
                });

                that.update({
                    data: data
                }).then();
            }

            $document.on("wa_order_cart_changed wa_order_product_added", cartWatcher);
            function cartWatcher() {
                var is_exist = $.contains(document, that.$wrapper[0]);
                if (is_exist) {
                    that.update().then();
                } else {
                    $document.off("wa_order_cart_changed wa_order_product_added", cartWatcher);
                }
            }

            $document.on("wa_auth_contact_logout", logoutWatcher);
            function logoutWatcher() {
                var is_exist = $.contains(document, that.$wrapper[0]);
                if (is_exist) {
                    that.reload();
                } else {
                    $document.off("wa_auth_contact_logout", logoutWatcher);
                }
            }

            // These code were used to update the cart block. Now used reload page
            $document.on("wa_auth_contact_logged", loginWatcher);
            function loginWatcher() {
                var is_exist = $.contains(document, that.$wrapper[0]);
                if (is_exist) {
                    $document.trigger("wa_order_reload_start");
                    location.reload();

                } else {
                    $(document).off("wa_auth_contact_logged", loginWatcher);
                }
            }

            $document.on("wa_order_reload_start", reloadWatcher);
            function reloadWatcher() {
                var is_exist = $.contains(document, that.$wrapper[0]);
                if (is_exist) {
                    that.lock(true);
                } else {
                    $document.off("wa_order_reload_start", reloadWatcher);
                }
            }

            var use_focus = true;

            var $cart = $("#wa-order-cart-wrapper");
            if ($cart.length) {
                var wrapper_top = that.$wrapper.offset().top,
                    cart_top = $cart.offset().top;
                use_focus = (wrapper_top - cart_top < 10);
            }

            if (use_focus) {
                focusField(that.$wrapper);
            }

            that.$wrapper.on("focus", "select, textarea, input", function(event) {
                if (that.is_updating) {
                    $(this).trigger("blur");
                }
            });

            if (that.use_storage) { that.storage("load"); }
        };

        Form.prototype.DEBUG = function() {
            var that = this,
                log_function = console.log;

            var styles = {
                "hint": "font-weight: bold; color: #666;",
                "info": "font-weight: bold; font-size: 1.25em; color: blue;",
                "warn": "font-weight: bold; font-size: 1.25em;",
                "error": "font-weight: bold; font-size: 1.25em;"
            };

            if (that.options && that.options.DEBUG) {
                if (styles[arguments[1]]) {
                    arguments[0] = (typeof arguments[0] === "string" ? "%c" + arguments[0] : arguments[0]);

                    switch (arguments[1]) {
                        case "info":
                            log_function = console.info;
                            break;
                        case "error":
                            log_function = console.error;
                            break;
                        case "warn":
                            log_function = console.warn;
                            break;
                    }

                    arguments[1] = styles[arguments[1]];
                }

                return log_function.apply(console, arguments);
            }
        };

        // CONSTRUCTORS

        Form.prototype.initAuth = function(options) {
            var that = this;

            options["scope"] = that;

            that.sections["auth"] = new Auth(options);

            that.DEBUG("Auth initialized", "info", that.sections["auth"]);
        };

        Form.prototype.initRegion = function(options) {
            var that = this;

            options["scope"] = that;

            that.sections["region"] = new Region(options);

            that.DEBUG("Region initialized", "info", that.sections["region"]);
        };

        Form.prototype.initShipping = function(options) {
            var that = this;

            options["scope"] = that;

            that.sections["shipping"] = new Shipping(options);

            that.DEBUG("Shipping initialized", "info", that.sections["shipping"]);
        };

        Form.prototype.initDetails = function(options) {
            var that = this;

            options["scope"] = that;

            that.sections["details"] = new Details(options);

            that.DEBUG("Details initialized", "info", that.sections["details"]);
        };

        Form.prototype.initPayment = function(options) {
            var that = this;

            options["scope"] = that;

            that.sections["payment"] = new Payment(options);

            that.DEBUG("Payment initialized", "info", that.sections["payment"]);
        };

        Form.prototype.initConfirm = function(options) {
            var that = this;

            options["scope"] = that;

            that.sections["confirm"] = new Confirm(options);

            that.DEBUG("Confirm initialized", "info", that.sections["confirm"]);
        };

        // PROTECTED

        /**
         * @param {string} event_name
         * @param {Object|Array?} data
         * */
        Form.prototype.trigger = function(event_name, data) {
            var that = this;

            var form_event_name = "wa_order_form_" + event_name;

            that.$wrapper.triggerHandler(event_name, (data || null));
            that.$outer_wrapper.trigger(form_event_name, (data || null));
        };

        /**
         * @param {Object} $wrapper
         * @param {Boolean?} render_errors
         * @param {Boolean?} focus
         * @return {Array} with errors
         * */
        Form.prototype.validate = function($wrapper, render_errors, focus) {
            var that = this,
                errors = [];

            $wrapper.find(".wa-input").each( function() {
                var $input = $(this),
                    name = $input.attr("name"),
                    value = $input.val().trim(),
                    is_disabled = $input.is(":disabled");

                if (is_disabled) { return true; }

                if (!value.length) {
                    var is_required = !!$input.attr("required");
                    if (is_required) {
                        errors.push({
                            $field: $input,
                            name: name,
                            value: that.locales["required"]
                        });
                    }

                } else if (!name) {
                    return true;

                } else if ($input.hasClass("wa-email")) {
                    var is_email = that.ui.validate.email(value);
                    if (!is_email) {
                        errors.push({
                            $field: $input,
                            name: name,
                            value: that.locales["invalid"]
                        });
                    }

                } else if ($input.hasClass("wa-phone")) {
                    var is_phone = that.ui.validate.phone(value);
                    if (!is_phone) {
                        errors.push({
                            $field: $input,
                            name: name,
                            value: that.locales["invalid"]
                        });
                    }

                } else if ($input.hasClass("wa-url")) {
                    var is_url = that.ui.validate.url(value);
                    if (!is_url) {
                        errors.push({
                            $field: $input,
                            name: name,
                            value: that.locales["invalid"]
                        });
                    }

                } else if ($input.hasClass("wa-number")) {
                    var is_number = that.ui.validate.number(value);
                    if (!is_number) {
                        errors.push({
                            $field: $input,
                            name: name,
                            value: that.locales["invalid"]
                        });
                    }
                }
            });

            $wrapper.find(".wa-field-date input.hasDatepicker").each(function () {
                var $input = $(this);
                var name = $input.attr("name");
                var value = $input.val().trim();

                if (value.length > 0) {
                    var start_date = new Date($input.data("start_date") + ' 00:00:00');
                    var delivery_date = $input.datepicker("getDate");
                    if (delivery_date - start_date < 0) {
                        errors.push({
                            $field: $input,
                            name: name,
                            value: that.locales["incorrect_date"]
                        });
                    }

                    var is_valid = checkDate(value);
                    if (!is_valid) {
                        errors.push({
                            $field: $input,
                            name: name,
                            value: that.locales["invalid_date"]
                        });
                    }
                }
            });

            $wrapper.find(".wa-checkbox").each( function() {
                var $input = $(this),
                    name = $input.attr("name"),
                    is_active = $input.is(":checked"),
                    is_disabled = $input.is(":disabled");

                if (is_disabled) { return true; }

                if (!is_active) {
                    var is_required = !!$input.attr("required");
                    if (is_required) {
                        errors.push({
                            $field: $input,
                            name: name,
                            value: that.locales["required"]
                        });
                    }

                } else if (!name) {
                    return true;
                }
            });

            $wrapper.find(".wa-select, wa-textarea").each( function() {
                var $input = $(this),
                    name = $input.attr("name"),
                    value = $.trim($input.val()),
                    is_disabled = $input.is(":disabled");

                if (is_disabled) { return true; }

                if (!value.length) {
                    var is_required = !!$input.attr("required");
                    if (is_required) {
                        errors.push({
                            $field: $input,
                            name: name,
                            value: that.locales["required"]
                        });
                    }

                } else if (!name) {
                    return true;
                }
            });

            if (render_errors) {
                if (errors.length) {
                    $.each(errors, function(i, error) {
                        renderError(error);
                    });

                    var first_error = errors[0];
                    if (focus && first_error.$field) {
                        focusField(first_error.$field.parent());
                    }
                }
            }

            return errors;

            function renderError(error) {
                var $error = $("<div class=\"wa-error-text\" />").text(error.value);
                var error_class = "wa-error";

                if (error.$field) {
                    var $field = error.$field;

                    if (!$field.hasClass(error_class)) {
                        $field.addClass(error_class);

                        var $field_wrapper = $field.closest(".wa-field-wrapper");
                        if ($field_wrapper.length) {
                            $field_wrapper.append($error);
                        } else {
                            $error.insertAfter($field);
                        }

                        $field.on("change keyup", removeFieldError);
                    }
                }

                function removeFieldError() {
                    $field.removeClass(error_class);
                    $error.remove();

                    $field.off("change", removeFieldError);
                }
            }
        };

        /**
         * @param {Object} api
         * @return {Array}
         * */
        Form.prototype.renderErrors = function(api) {
            var that = this,
                result = [];

            if (api.error_step_id && api.errors) {
                $.each(that.sections, function(section_id, section) {
                    if (api.error_step_id === section_id) {
                        if (typeof section.renderErrors === "function") {
                            var errors = section.renderErrors(api.errors);
                            if (errors.length) {
                                result = result.concat(errors);
                            }
                        }
                    }
                });
            }

            return result;
        };

        /**
         * @param {Object?} options
         * @return {Array}
         * */
        Form.prototype.getFormData = function(options) {
            options = ( options ? options : {});

            var that = this,
                result = [],
                errors = [],
                render_errors = !!options.render_errors;

            $.each(that.sections, function(section_id, section) {
                var clean = false;

                if (options.sections && Array.isArray(options.sections)) {
                    if ( !(options.sections.indexOf(section_id) >= 0) ) {
                        clean = true;
                    }
                }

                if (typeof section.getData === "function") {
                    var data = section.getData({
                            clean: clean,
                            only_api: !!(options.only_api),
                            render_errors: render_errors
                        });

                    if (data.length) {
                        result = result.concat(data);
                    }
                    if (data.errors) {
                        errors = errors.concat(data.errors);
                    }
                }
            });

            if (errors.length) {
                result.errors = errors;
                that.DEBUG("Errors:", "warn", errors);
            }

            return result;
        };

        /**
         * @param {Object?} options
         * @return {Promise}
         * */
        Form.prototype.update = function(options) {
            options = (options ? options : {});

            var that = this,
                deferred = $.Deferred(),
                promise = deferred.promise(),
                form_data = [];

            var render_errors = !!options.render_errors,
                is_changed = false;

            if (options.data) {
                form_data = options.data;

            } else {
                var form_options = {
                    render_errors: render_errors
                };

                if (options.sections) {
                    form_options.sections = options.sections
                }

                form_data = that.getFormData(form_options);
            }

            if (render_errors && form_data.errors) {
                deferred.reject("front_errors", form_data.errors);
                return promise;

            } else {

                that.is_updating = true;

                promise = (options.create ? that.create(form_data) : that.calculate(form_data));

                var locked_class = "is-locked";

                var sections_order = ["auth", "region", "shipping", "details", "payment", "confirm"];

                $.each(sections_order, function(i, section_id) {
                    var section = null;
                    if (that.sections[section_id]) {
                        section = that.sections[section_id];
                    } else {
                        return true;
                    }

                    section.$wrapper.addClass(locked_class);

                    promise
                        .always( function() {
                            section.$wrapper.removeClass(locked_class);
                        })
                        .then( function(api) {
                            if (typeof section.render === "function") {
                                var section_changed = section.render(api);
                                if (section_changed) {
                                    is_changed = true;
                                    that.trigger(section_id + "_changed", api);
                                }
                            }
                        });
                });

                // Crude way to show bad unexpected rare errors that happen upon order creation
                promise.fail(function(state, response) {
                    if (response) {
                        that.DEBUG("render errors:", "errors", response);
                        if (options.create && response.errors && response.errors.general) {
                            alert(response.errors.general);
                        }
                    }
                });

                var $loading = showAnimation();
                that.lock(true);

                promise
                    .always( function() {
                        that.is_updating = false;
                        $loading.remove();
                        that.lock(false);
                        that.storage("save", form_data);
                    })
                    .then( function(api) {
                        if (is_changed) {
                            that.trigger("changed", api);
                        }
                    });
            }

            return promise;

            function showAnimation() {
                var $loading = $(that.templates["loading"]).appendTo(that.$wrapper),
                    $window = $(window);

                var window_top = $window.scrollTop(),
                    display_h = $window.height(),
                    wrapper_h = that.$wrapper.outerHeight(),
                    offset = that.$wrapper.offset(),
                    loading_h = $loading.outerHeight();

                var visible_h = offset.top + wrapper_h - window_top;
                if (visible_h > display_h) { visible_h = display_h; }

                var top = window_top + (visible_h/2) - offset.top;

                if (top < loading_h/2) { top = loading_h/2; }
                if (top > wrapper_h - loading_h) { top = wrapper_h - loading_h; }

                $loading.css({ top: top });

                return $loading;
            }
        };

        /**
         * @param {Object} form_data
         * */
        Form.prototype.calculate = function(form_data) {
            var that = this;

            // there's no more that one form/calculate "thread"
            if (that.calculate_promise) {
                // previous existing "thread" will restart after abort
                if (that.calculate_xhr) {
                    that.calculate_xhr.abort();
                }

                // but the promise is still correct
                return that.calculate_promise;
            }

            // this deferred is resolved once form/calculate request is completed
            // (may take more than one request if some are cancelled)
            var result_deferred = $.Deferred();
            that.calculate_promise = result_deferred.promise();
            that.calculate_promise.then( function() {
                that.calculate_promise = null;
                that.calculate_xhr = null;
            }, function() {
                that.calculate_promise = null;
                that.calculate_xhr = null;
            });

            restart();

            return that.calculate_promise;

            // attempt to run form/calculate until it completes
            // restarts itself if aborted by addService()
            function restart() {
                if (!form_data) {
                    that.DEBUG("Form data is required.", "error");
                    result_deferred.reject("error");
                    return false;
                }

                form_data.push({
                    name: "response",
                    value: "json"
                });

                that.DEBUG("Form data:", "info");
                that.DEBUG(form_data);

                that.calculate_xhr = $.post(that.urls["calculate"], form_data, "json")
                    .done( function(response) {
                        // save succeeded
                        if (response.status === "ok") {
                            var api = formatAPI(response.data);
                            that.DEBUG("API received:", "info");
                            that.DEBUG(api);

                            result_deferred.resolve(api);

                            // validation error
                        } else {
                            that.DEBUG("API not received.", "error", ( response.errors ? response.errors : "No error description") );
                            result_deferred.reject("fail", response);
                        }
                    })
                    .fail( function(jqXHR, state) {
                        if (state === "abort") {
                            restart();

                            // server error
                        } else {
                            that.DEBUG("Getting API aborted.", "error", state);
                            result_deferred.reject(state);
                        }
                    });
            }
        };

        /**
         * @param {Object} form_data
         * */
        Form.prototype.create = function(form_data) {
            var that = this;

            // there's no more that one form/create "thread"
            if (that.create_promise) {
                // previous existing "thread" will restart after abort
                if (that.create_xhr) {
                    that.create_xhr.abort();
                }

                // but the promise is still correct
                return that.create_promise;
            }

            var result_deferred = $.Deferred();
            that.create_promise = result_deferred.promise();
            that.create_promise.then( function() {
                that.create_promise = null;
                that.create_xhr = null;
            }, function() {
                that.create_promise = null;
                that.create_xhr = null;
            });

            request();

            return that.create_promise;

            function request() {
                if (!form_data) {
                    that.DEBUG("Form data is required.", "error");
                    result_deferred.reject("error");
                    return false;
                }

                form_data.push({
                    name: "response",
                    value: "json"
                });

                that.DEBUG("Form data for create:", "info");
                that.DEBUG(form_data);

                that.create_xhr = $.post(that.urls["create"], form_data, "json")
                    .done( function(response) {
                        // save succeeded
                        if (response.status === "ok") {
                            var api = formatAPI(response.data);
                            that.DEBUG("API received:", "info");
                            that.DEBUG(api);

                            result_deferred.resolve(api);
                            that.storage("clear");

                        // validation error
                        } else {
                            that.DEBUG("API not received.", "error", ( response.errors ? response.errors : "No error description") );
                            result_deferred.reject("fail", response);
                        }
                    })
                    .fail( function(jqXHR, state) {
                        that.DEBUG("Getting API aborted.", "error", state);
                        result_deferred.reject(state);
                    });
            }

        };

        Form.prototype.reload = function() {
            var that = this,
                deferred = $.Deferred();

            if (!that.reload_xhr) {
                if (that.calculate_xhr) {
                    that.calculate_xhr.abort();
                    that.calculate_xhr = false;
                }

                that.lock(true);

                var form_data = that.getFormData({
                    only_api: true
                });

                if (that.options) {
                    form_data.push({
                        name: "opts[DEBUG]",
                        value: (that.options.DEBUG ? 1 : 0 )
                    });

                    form_data.push({
                        name: "opts[wrapper]",
                        value: that.options.wrapper
                    });

                    if (typeof that.options.adaptive !== "undefined" && (!that.options.adaptive || that.options.adaptive === "false")) {
                        form_data.push({
                            name: "opts[adaptive]",
                            value: "false"
                        });
                    }
                }

                form_data.push({
                    name: "response",
                    value: "html"
                });

                that.DEBUG("Form is reloading...", "info", form_data);
                that.trigger("before_reload", that);

                that.reload_xhr = $.post(that.urls["calculate"], form_data)
                    .done( function(html) {
                        that.$outer_wrapper.one("wa_order_form_ready", function() {
                            var new_controller = that.$outer_wrapper.data("controller");
                            deferred.resolve(new_controller);
                        });

                        that.$wrapper.replaceWith(html);

                        that.DEBUG("Form reloaded.", "info");
                        that.trigger("reloaded", that);
                    })
                    .fail( function(jqXHR, state) {
                        that.DEBUG("Reload is failed.", "error", state);
                        deferred.reject(state);
                    })
                    .always( function() {
                        that.lock(false);
                        that.reload_xhr = false;
                    });
            }

            return deferred.promise();
        };

        Form.prototype.lock = function(do_lock) {
            var that = this,
                locked_class = "is-locked";

            if (do_lock) {
                that.$wrapper.addClass(locked_class);

            } else {
                that.$wrapper.removeClass(locked_class);
            }
        };

        Form.prototype.storage = function(mode, form_data) {
            var that = this;

            var storage_name = "webasyst/shop/order/form";

            switch (mode) {
                case "save":
                    save(form_data);
                    break;
                case "load":
                    load();
                    break;
                case "clear":
                    clear();
                    break;
                default:
                    break;
            }

            function save(form_data) {
                try {
                    var form_data_json = JSON.stringify(form_data);
                    window.localStorage.setItem(storage_name, form_data_json);
                } catch(error) {
                    that.DEBUG("STORAGE ERROR:", "error", error.message);
                }
            }

            function load() {
                var form_data_json = window.localStorage.getItem(storage_name);
                if (form_data_json) {
                    var form_data = JSON.parse(form_data_json);
                    if (form_data) {
                        that.update({
                            data: form_data
                        }).then();
                    }
                }
            }

            function clear() {
                window.localStorage.removeItem(storage_name);
            }
        };

        // PUBLIC

        return Form;

        /**
         * @param {Object} api
         * @description correct data for you
         * */
        function formatAPI(api) {
            if (api.errors) {
                if (!Array.isArray(api.errors)) {
                    api.errors = destruct(api.errors);
                }
            }

            return api;

            function destruct(object) {
                var result = [];

                if (object) {
                    $.each(object, function(name, value) {
                        result.push({
                            name: name,
                            value: value
                        });
                    });
                }

                return result;
            }
        }

    })($);

    window.waOrder = (window.waOrder || {});

    window.waOrder.Form = Form;

    function isMobile() {
        return ( $(document).width() <= 760 );
    }

    function focusField($wrapper) {
        $wrapper.find("input:visible").each( function() {
            var $field = $(this),
                value = $.trim($field.val());

            if (!value.length) {
                $field.trigger("focus");
                return false;
            }
        });
    }

    function checkDate(string) {
        var format = $.datepicker._defaults.dateFormat,
            is_valid = false;

        try {
            $.datepicker.parseDate(format, string);
            is_valid = true;
        } catch(e) {

        }

        return is_valid;
    }

})(jQuery);