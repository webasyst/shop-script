window.ProductPickup = class {

    constructor(params) {
        if (!params.$wrapper?.[0]) return;

        this.$wrapper = params.$wrapper;
        this.$body = $('.pickup-dialog-body', this.$wrapper);
        this.$form = $('.pickup-dialog-right-content', this.$wrapper);
        this.$view_toolbar = $('.js-view-toolbar', this.$form);

        this.wa_app_url = params.wa_app_url;
        this.sku_id = params.sku_id;
        this.templates = params.templates || {};
        this.map = params.map;
        this.variants = Object.values(params.pickup_list ?? {}).map(p => ({
            pos_id: p.id,
            name: p.name,
            lat: parseFloat(p.params.latitude),
            lng: parseFloat(p.params.longitude)
        }));

        this.pickup_id = null;
        this.map_deferred = $.Deferred();
        this.getMap = () => this.map_deferred.promise();

        this.init();
        this.initMap();
    }

    init() {
        setTimeout(() => this.$wrapper.css('opacity', '1'), 10);

        this.initEvents();
    }

    initEvents() {
        const that = this;
        const $step_2_block = $('#pickup-selected-store', this.$wrapper);

        const slideToStep = (step = 1) => {
            const translate_x = Math.min(this.$form.width() * (step - 1) * -1, 0);
            that.$form.css('transform', `translateX(${translate_x}px)`);
            that.$form.parent().scrollTop(0);
        }
        // go to step 2
        that.$form.on('click', '[data-pickup-id]', function() {
            const $selected_pickup = $(this).clone();
            const pos_id = $selected_pickup.data('pickup-id');
            const stock = $selected_pickup.data('stock');

            $step_2_block.find('.js-actions').toggle(parseFloat(stock) !== 0);

            $step_2_block.find('.js-heading').html($selected_pickup.find('.js-name').html());
            $step_2_block.find('.js-info').html($selected_pickup.find('.js-info').html());
            $step_2_block.find('.js-description').html($selected_pickup.find('.js-description').show().html());


            that.current_pickup_id = pos_id;
            that.getMap().then(map => map.moveTo(pos_id));
            slideToStep(2);
        });
        // go to step 3
        that.$form.on('click', '.js-preorder', () => {
            slideToStep(3);
        });
        // back to previous step
        that.$form.on('click', '.js-back', () => {
            slideToStep(-1);
            that.getMap().then(map_provider => map_provider.reset());
        });

        // close dialog
        that.$form.on('click', '.js-close-dialog', () => {
            that.closeDialog();
        });

        that.initMobileToggleViewMode();

        that.initSubmit();
    }

    // pay
    initSubmit() {
        const that = this;
        const $checkout_form = $('#js-pickup-checkout', that.$wrapper);
        const $place_for_error = $('#js-place-for-error', that.$wrapper);
        const $submit = $checkout_form.find('.js-pickup');

        const submit = () => {
            $place_for_error.empty();
            const payload = {
                sku_id: that.sku_id,
                pickup_id: that.current_pickup_id,
                customer: $checkout_form.find(':input').serializeArray()
            };

            return $.post(`${that.wa_app_url}pickup/create/`, payload, function (response) {
                if (response.status === 'ok' && response?.data?.code) {
                    that.$body.html(that.templates.order_confirmation);
                    // redirect to paymentlink
                    location.href = `${that.wa_app_url}paymentlink/${response.data.code}/`;
                } else if (response.errors) {
                    $place_for_error.html(response.errors.server_error ?? response.errors.error_description);
                }
            });
        };

        $submit.on('click', function (e) {
            e.preventDefault();
            if ($submit.prop('disabled')) return;
            $submit.prop('disabled', true);
            submit().always(() => {
                $submit.prop('disabled', false);
            });
        });
    }

    // toggle view mode
    initMobileToggleViewMode () {
        const that = this;
        const $toggler = that.$view_toolbar.find('[data-id]');

        $toggler.on('click', function() {
            const $view = $(this).addClass('selected');
            $view.siblings().removeClass('selected');

            const $map = that.$wrapper.find('.pickup-map-wrapper');
            $map.remove();

            const view = $view.data('id');
            const $list = that.$wrapper.find('[data-step="1"] .pickup-body').toggle(view !== 'map');
            if (view === 'map') {
                $list.after('<div class="pickup-map-wrapper" id="wa-pickup-map"></div>');
            }
            that.initMap();
        });
    }

    closeDialog() {
        this.$wrapper.remove();
    }

    initMap() {
        const that = this;
        const $map = $("#wa-pickup-map");
        if (!that.map.adapter || !that.map.api_uri || !$map.length) {
            return false;
        }

        if (that.map.adapter === "yandex") {
            $.loadSource([{
                id: "yandex-maps-api-js",
                type: "js",
                uri: that.map.api_uri
            }]).then(function () {
                if (window.ymaps) {
                    window.ymaps.ready(function () {
                        initYandexMap(window.ymaps);
                    });
                } else {
                    console.error("Yandex API required", "error");
                    return false;
                }
            });

        } else if (that.map.adapter === "google") {
            $.loadSource([{
                id: "google-maps-api-js",
                type: "js",
                uri: that.map.api_uri
            }, {
                id: "google-maps-clusterer-js",
                type: "js",
                uri: "//unpkg.com/@googlemaps/markerclusterer/dist/index.min.js"
            }]).then( function() {
                initGoogleMap();
            });
        }

        function initYandexMap(ymaps) {
            let placemarks = {},
                active_balloon = null;

            const center = getCenter(that.variants);
            const zoom = getZoomForBounds(that.variants);
            const map = new ymaps.Map("wa-pickup-map", { center, zoom, controls: ["fullscreenControl", "zoomControl", "geolocationControl"] });
            const cluster = new ymaps.Clusterer({
                clusterIconColor: '#333333'
            });

            var moveTo = function(variant_id) {
                const placemark = placemarks[variant_id];
                if (!placemark) return;
                map.setCenter(placemark.geometry.getCoordinates(), 17).then( function() {
                    active_balloon = placemark.balloon;

                    var state = cluster.getObjectState(placemark);

                    if (!state.isClustered && state.isShown) {
                        if (!active_balloon.isOpen()) {
                            active_balloon.open();
                        }
                    }
                });
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
                    map.setZoom(zoom);
                    if (active_balloon) {
                        if (active_balloon.isOpen()) {
                            active_balloon.close();
                        }
                        active_balloon = null;
                    }
                },
                moveTo: function(variant_id) {
                    return moveTo(variant_id);
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
                        is_cluster = !!target.getGeoObjects;

                    if (is_cluster) {
                        var placemarks_array = target.getGeoObjects(),
                            current_zoom = map.getZoom();

                        if (current_zoom >= 17) {
                            var variants_array = [];

                            $.each(placemarks_array, function(i, placemark) {
                                if (placemark.pos_id) {
                                    variants_array.push(placemark.pos_id);
                                }
                            });

                            if (variants_array.length) {
                                exitFullscreen();
                            }
                        }

                    } else if (target.pos_id) {
                        active_balloon = target.balloon;
                        exitFullscreen();
                        that.openPos(target.pos_id);
                    }
                });

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
                }, {
                    preset: 'islands#pinkCircleIcon',
                    iconColor: '#db65ab',
                });

                placemark.pos_id = variant.pos_id;

                placemarks[variant.pos_id] = placemark;

                return placemark;
            }
        }

        function initGoogleMap() {
            const placemarks = {},
                center = getCenter(that.variants),
                zoom = getZoomForBounds(that.variants);

            var map = new google.maps.Map(document.getElementById("wa-pickup-map"), {
                center: { lat: center[0], lng: center[1] },
                zoom,
                maxZoom: 18
            });

            var placemarks_array = renderPlacemarks();

            var balloon = new google.maps.InfoWindow(),
                cluster = initCluster(placemarks_array);

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
                    map.setCenter({ lat: center[0], lng: center[1] });
                    map.setZoom(zoom);
                    balloon.close();
                },
                moveTo: function(variant_id) {
                    return moveTo(variant_id);
                }
            });

            function moveTo(variant_id) {
                const placemark = placemarks[variant_id];
                if (!placemark) return;
                map.setZoom(17);
                map.setCenter(placemark.getPosition());

                balloon.close();
                balloon.setContent(placemark.title);
                balloon.open(map, placemark);
            }

            function initCluster(placemarks) {
                if (cluster) { cluster.clearMarkers(); }

                cluster = new markerClusterer.MarkerClusterer({
                    map,
                    markers: placemarks,
                    onClusterClick: function(clust) {
                        var zoom = map.getZoom();
                        if (zoom >= 17) {

                            var markets_array = clust.getMarkers(),
                                variants_array = [];

                            $.each(markets_array, function(i, marker) {
                                variants_array.push(marker.pos_id);
                            });

                            if (variants_array.length) {
                                exitFullscreen();
                            }
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

                    placemark.pos_id = variant.pos_id;

                    placemarks[variant.pos_id] = placemark;

                    placemark.addListener("click", function() {
                        map.setZoom(17);
                        map.setCenter(placemark.getPosition());

                        balloon.close();
                        balloon.setContent(variant.name);
                        balloon.open(map, placemark);

                        exitFullscreen();
                        that.openPos(variant.pos_id);
                    });

                    return placemark;
                }
            }

            function exitFullscreen() {
                if (!document.fullscreenElement) {
                    return;
                }
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

        function getZoomForBounds(variants) {
            let zoom = 10;
            const tile_size = 256;
            const lat_array = [];
            const lng_array = [];

            $.each(variants, function(id, variant) {
                if (variant.lat && variant.lng) {
                    lat_array.push(variant.lat);
                    lng_array.push(variant.lng);
                }
            });
            if (lat_array.length) {
                const lat_min = Math.min.apply(null, lat_array),
                    lat_max = Math.max.apply(null, lat_array),
                    lng_min = Math.min.apply(null, lng_array),
                    lng_max = Math.max.apply(null, lng_array);

                // преобразуем в меркаторские координаты (радианы)
                const x1 = (lng_min + 180) / 360; // нормализованный 0..1
                const x2 = (lng_max + 180) / 360;

                const sinLatToY = lat => 0.5 - (Math.log((1 + Math.sin(lat * Math.PI/180)) / (1 - Math.sin(lat * Math.PI/180))) / (4 * Math.PI));
                const y1 = sinLatToY(lat_min);
                const y2 = sinLatToY(lat_max);

                const dx = Math.abs(x1 - x2);
                const dy = Math.abs(y1 - y2);

                // учёт обёртки по долготе
                const dx_wrapped = Math.min(dx, 1 - dx);

                // сколько мировых нормализованных единиц помещается в ширину/высоту
                const scaleX = $map.width() / (dx_wrapped * tile_size);
                const scaleY = $map.height() / (dy * tile_size);

                const scale = Math.min(scaleX, scaleY);
                zoom = Math.floor(Math.log2(scale));
            }

            return zoom;
        }
    }

    openPos(pos_id) {
        if (!pos_id) return;
        this.$form.find(`[data-pickup-id="${pos_id}"]`).click();
    }
}
