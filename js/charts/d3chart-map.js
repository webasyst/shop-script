var showMapGraph = function(data, country_names) {
    var $map = $(".map-wrapper"),
        width = $map.width();

    // Return min/max values of data array
    var getScale = function(data) {
        var array = [],
            min, max;

        for (var data_item in data) {
            if (data.hasOwnProperty(data_item)) {
                array.push(data[data_item].value);
            }
        }

        min = parseFloat(Math.min.apply(Math, array));
        max = parseFloat(Math.max.apply(Math, array));

        return {
            min: min,
            max: max
        }
    };

    // Return array with percent data
    var getDataPercentArray = function(data) {
        var scale = getScale(data),
            data_percent_array = [],
            scale_value,
            percent;

        if (scale.max === scale.min) { scale.min = 0; }
        scale_value = scale.max - scale.min;

        for (var data_item in data) {
            if (data.hasOwnProperty(data_item)) {
                percent = (parseFloat(data[data_item].value) - scale.min)/scale_value;
                data_percent_array.push(percent);
            }
        }

        return data_percent_array;
    };

    // Return Obj with Name -> Percent (0 .. 10)
    var getFillsData = function(data) {
        var fill_array = {};
        if (data.length) {
            var percent_array = getDataPercentArray(data),
                i = 0;

            for (var data_item in data) {
                if (data.hasOwnProperty(data_item)) {
                    var name = data[data_item].name;
                    fill_array[name] = {};
                    fill_array[name].fillKey = parseInt(percent_array[i] * 10);
                }
                i++;
            }
        }
        return fill_array;
    };

    var getFills = function(r,g,b) {
        var fills = {};

        fills.defaultFill = "rgba(" + r + "," + g + "," + b + ", 0.5)";

        for (var i = 0; i <= 10; i++) {
            fills[i] = "rgba(" + r + "," + g + "," + b + ", "+ (0.5 + i/20) + ")";
        }

        return fills;
    };

    // Render Map
    $map.datamaps({
        height: width * 0.6,
        fills: getFills(59,164,237),
        data: getFillsData(data),
        geographyConfig: {
            hideAntarctica: true,
            borderWidth: 1,
            borderColor: '#ffffff',
            popupOnHover: true,
            highlightOnHover: true,
            highlightFillColor: "rgba(59,164,237,1)",
            highlightBorderColor: false,
            highlightBorderWidth: 1,
            popupTemplate: function(geography) {
                var countText = "",
                    country_name = country_names[(geography.id||'').toLowerCase()] || geography.properties.name,
                    count,
                    hint;

                for (var index in data) {
                    if (data.hasOwnProperty(index)) {
                        var name = data[index].name;
                        if (name === geography.id) {
                            count = data[index].value;
                            hint = data[index].hint;
                            country_name = data[index].country_name;
                            break;
                        }
                    }
                }

                if (hint) {
                    countText = ': <span class="nowrap">'+ hint + '</span>';
                } else if (count) {
                    countText = ': <span class="nowrap">' + count + ' customers</span>';
                }

                return "<div class=\"hoverinfo\"><strong>" + country_name + "" + countText + "</strong></div>";
            }
        }
    });
};