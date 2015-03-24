(function ($) {
    $.storage = new $.store();
    $.storefronts = {
        init: function () {
            if (typeof($.History) != "undefined") {
                $.History.bind(function () {
                    $.storefronts.dispatch();
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

        defaultAction: function () {
            if ($('#s-storefronts-content').data('design')) {
                this.designAction();
            } else {
                this.pagesAction();
            }
        },

        setActive: function (id) {
            $(".s-links li.selected").removeClass('selected');
            $("#" + id).addClass('selected');
        },

        pagesAction: function (id) {
            if ($("#s-storefronts-content").data('design')) {
                if ($('#wa-design-container').length) {
                    waDesignLoad('pages');
                } else {
                    $("#s-storefronts-content").load('?module=design', function () {
                        waDesignLoad('pages');
                    });
                }
            } else {
                if ($('#wa-page-container').length) {
                    waLoadPage(id);
                } else {
                    $("#s-storefronts-content").load('?module=pages');
                }
            }
        },

        designAction: function(params) {
            if (params) {
                if ($('#wa-design-container').length) {
                    waDesignLoad();
                } else {
                    $("#s-storefronts-content").load('?module=design', function () {
                        waDesignLoad(params);
                    });
                }
            } else {
                $("#s-storefronts-content").load('?module=design', function () {
                    waDesignLoad('');
                });
            }
        },

        designPagesAction: function () {
            this.designAction('pages');
        },

        designThemesAction: function (params) {
            if ($('#wa-design-container').length) {
                waDesignLoad();
            } else {
                $("#s-storefronts-content").load('?module=design', function () {
                    waDesignLoad();
                });
            }
        },

        settingsAction: function () {
            this.setActive('s-link-settings');
            $("#s-storefronts-content").load('?module=storefronts&action=settings');
        }
    }
})(jQuery);