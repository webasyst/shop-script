(function ($) {
    $.storage = new $.store();
    $.reports = {
        init: function () {
            var that = this;
            if (typeof($.History) != "undefined") {
                $.History.bind(function () {
                    that.dispatch();
                });
            }
            $.wa.errorHandler = function (xhr) {
                if ((xhr.status === 403) || (xhr.status === 404) ) {
                    $("#s-content").html('<div class="content left200px"><div class="block double-padded">' + xhr.responseText + '</div></div>');
                    return false;
                }
                return true;
            };
            var hash = window.location.hash;
            if (hash === '#/' || !hash) {
                this.dispatch();
            } else {
                $.wa.setHash(hash);
            }
        },

        dispatch: function (hash) {
            if (hash === undefined) {
                hash = window.location.hash;
            }
            hash = hash.replace(/(^[^#]*#\/*|\/$)/g, ''); /* fix syntax highlight*/
            var original_hash = this.hash
            this.hash = hash;
            if (hash) {
                hash = hash.split('/');
                if (hash[0]) {
                    var actionName = "";
                    var attrMarker = hash.length;
                    for (var i = 0; i < hash.length; i++) {
                        var h = hash[i];
                        if (i < 2) {
                            if (i === 0) {
                                actionName = h;
                            } else if (parseInt(h, 10) != h && h.indexOf('=') == -1) {
                                actionName += h.substr(0,1).toUpperCase() + h.substr(1);
                            } else {
                                attrMarker = i;
                                break;
                            }
                        } else {
                            attrMarker = i;
                            break;
                        }
                    }
                    var attr = hash.slice(attrMarker);
                    this.preExecute(actionName, attr);
                    if (typeof(this[actionName + 'Action']) == 'function') {
                        $.shop.trace('$.products.dispatch',[actionName + 'Action',attr]);
                        this.setActiveTop(actionName);
                        this[actionName + 'Action'].apply(this, attr);
                    } else {
                        $.shop.error('Invalid action name:', actionName+'Action');
                    }
                } else {
                    this.preExecute();
                    this.defaultAction();
                }
            } else {
                this.preExecute();
                this.defaultAction();
            }
        },

        preExecute: function () {

        },

        setActiveTop: function (action) {
            if (!action) {
                action = 'sales';
            }
            var hash = '#/' + action + '/';
            $("ul.s-reports li.selected").removeClass('selected');
            $('ul.s-reports a[href="' + hash + '"]').parent('li').addClass('selected');
        },

        defaultAction: function () {
            this.setActiveTop('sales');
            this.salesAction();
        },

        salesAction: function () {
            $("#reportscontent").load('?module=reports&action=sales');
        },

        profitAction: function () {
            $("#reportscontent").load('?module=reports&action=profit');
        },

        topAction: function () {
            $("#reportscontent").load('?module=reports&action=top');
        },
        topSalesAction: function () {
            this.setActiveTop('top');
            $("#reportscontent").load('?module=reports&action=top&mode=sales');
        },
        topProfitsAction: function () {
            this.setActiveTop('top');
            $("#reportscontent").load('?module=reports&action=top&mode=profits');
        },

        pie: function(id, data) {
            if (!data || !data.length) {
                $('#'+id).remove();
                return;
            }

            $.jqplot(id, data, {
                seriesColors : ["#0077CC", "#33BB11", "#EE5500", "#EEBB11", "#44DDDD", "#6b6b6b", "#686190", "#b2b000", "#00b1ab", "#76b300"],
                grid : {
                    borderWidth : 0,
                    background : '#ffffff',
                    shadow : false
                },
                legend : {
                    show : true,
                    location : 's'
                },
                seriesDefaults : {
                    shadow : false,
                    renderer : $.jqplot.PieRenderer,
                    rendererOptions : {
                        padding : 0,
                        sliceMargin : 1,
                        showDataLabels : false
                    }
                }
            });
        },

        graph: function (id, data) {
            $.jqplot(id, [data], {
                seriesColors : ["#3b7dc0", "#129d0e", "#a38717", "#ac3562", "#1ba17a", "#87469f", "#6b6b6b", "#686190", "#b2b000", "#00b1ab", "#76b300"],
                series : [{
                    color : '#129d0e',
                    yaxis : 'y2axis',
                    shadow : false,
                    lineWidth : 3,
                    fill : true,
                    fillAlpha : 0.1,
                    fillAndStroke : true,
                    rendererOptions : {
                        highlightMouseOver : false,
                    }
                }],
                grid : {
                    borderWidth : 0,
                    shadow : false,
                    background : '#ffffff',
                    gridLineColor : '#eeeeee'
                },
                axes:{
                    xaxis:{
                        renderer: $.jqplot.DateAxisRenderer,
                        showTickMarks: false,
                        tickOptions:{
                            formatString:'%b %d'
                        },
                    },
                    y2axis:{
                        min: 0,
                        shopTicks: false,
                        showTickMarks: false,
                        tickOptions:{
                            formatString:'%.2f'
                        }
                    }
                },
                highlighter: {
                    show: true,
                    sizeAdjust: 7.5
                },
                cursor: {
                    show: false
                }
            });

        }
    }
})(jQuery);