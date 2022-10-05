( function($) {

    var ContentRouter = ( function($) {

        ContentRouter = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];

            // VARS
            that.templates = options["templates"];
            that.main_url = options["main_url"];
            that.sections = options["sections"];
            that.routes = options["routes"];
            that.on = {
                click: (typeof options["onClick"] === "function" ? options["onClick"] : null)
            }

            // DYNAMIC VARS
            that.xhr = false;
            that.active_section = that.getSection(location.origin + location.pathname);
            that.is_changed = false;

            // INIT
            that.init();
        };

        ContentRouter.prototype.init = function() {
            var that = this;

            that.$wrapper.on("change input", function(event) {
                if (!that.is_changed) {
                    that.is_changed = true;
                    that.$wrapper.find(".js-router-submit-button").addClass("yellow");
                }
            });

            that.$wrapper.on("reset_router_change_watcher", function() {
                that.is_changed = false;
                that.$wrapper.find(".js-router-submit-button").removeClass("yellow");
            });

            that.$wrapper.on("click", "a", function(event) {
                var is_blank = ($(this).attr("target") === "_blank");
                if (!is_blank) {
                    var content_url = that.getContentURL(this.href);
                    if (content_url) {
                        var is_disabled = !onClick(this, content_url);
                        if (!is_disabled && !event.ctrlKey && !event.shiftKey && !event.metaKey) {
                            event.preventDefault();

                            if (that.is_changed) {
                                confirmLoad(content_url)
                                    .done( function() {
                                        that.is_changed = false;
                                        that.load(content_url)
                                            .fail( function(state) {
                                                if (state === "incorrect_url") { location.href = content_url; }
                                            });
                                    });
                            } else {
                                that.load(content_url)
                                    .done( function() {
                                        $(window).scrollTop(0);
                                    })
                                    .fail( function(state) {
                                        if (state === "incorrect_url") { location.href = content_url; }
                                    });
                            }
                        }
                    }
                }
            });

            function onClick(node, content_url) {
                var result = true;

                if (typeof that.on.click === "function") {
                    var _result = that.on.click(node, content_url);
                    if (typeof _result === "boolean") {
                        result = _result;
                    }
                }

                return result;
            }

            // При первой загрузке страницы state пустой, заполняем его данными текущей страницы
            if (!history.state) {
                var content_url = location.href;
                history.replaceState({
                    reload: true,
                    content_url: content_url
                }, "", content_url);
            }

            window.onpopstate = function(event) {
                event.stopPropagation();
                that.onPopState(event);
            };

            that.initAnimation();

            $(function() { "use strict";
                that.trigger("wa_loaded", [{
                    content_url: window.location.origin + window.location.pathname,
                    data: {},
                    section: that.active_section
                }]);
            });

            function confirmLoad(content_url) {
                var deferred = $.Deferred(),
                    is_success = false;

                // Если в секции контента подключен футер с кнопками сохранения, то показываем диалог с действиями
                var $footer_save_button = $(".js-router-submit-button:first");
                if ($footer_save_button.length) {
                    $.waDialog({
                        html: that.templates["confirm_dialog"],
                        onOpen: function($wrapper, dialog) {
                            $wrapper.on("click", ".js-leave-button", function(event) {
                                event.preventDefault();
                                is_success = true;
                                dialog.close();
                            });

                            $wrapper.on("click", ".js-save-button", function(event) {
                                event.preventDefault();

                                $footer_save_button.trigger("click", {
                                    redirect_url: content_url
                                });

                                dialog.close();
                            });
                        },
                        onClose: function() {
                            if (is_success) {
                                deferred.resolve();
                            } else {
                                deferred.reject();
                            }
                        }
                    });
                // Если нет, то молчаливо уходим в новый раздел
                } else {
                    deferred.resolve();
                }

                return deferred.promise();
            }
        };

        ContentRouter.prototype.load = function(content_url, unset_state) {
            var that = this;

            var deferred = $.Deferred(),
                section = null;

            content_url = that.getContentURL(content_url);
            if (!content_url) {
                deferred.reject("incorrect_url");
                return deferred.promise();
            } else {
                section = that.getSection(content_url);
                if (!section) {
                    deferred.reject("incorrect_url");
                    return deferred.promise();
                }
            }

            if (that.xhr) { that.xhr.abort(); }

            var data = {};
            var is_new_section = (that.active_section === null || section.id !== that.active_section.id);
            if (is_new_section) {
                data["section"] = "1"
            }

            that.trigger("wa_before_load", [{
                content_url: content_url,
                data: $.extend({}, data),
                section: section
            }]);

            that.xhr = $.ajax({
                    method: 'GET',
                    url: content_url,
                    data: data,
                    dataType: 'html',
                    global: false,
                    cache: false,
                    xhr: function() {
                        var xhr = new window.XMLHttpRequest();

                        xhr.addEventListener("progress", function(event) {
                            that.trigger("wa_loading", event);
                        }, false);

                        xhr.addEventListener("abort", function(event) {
                            that.trigger("wa_abort");
                        }, false);

                        return xhr;
                    }
                })
                .always( function() {
                    that.xhr = false;
                })
                .done(function(html) {
                    if (!unset_state) {
                        history.pushState({
                            reload: true,               // force reload history state
                            content_url: content_url    // url, string
                        }, "", content_url);
                    }

                    that.setContent(html, section);
                    that.trigger("wa_loaded", [{
                        content_url: content_url,
                        data: $.extend({}, data),
                        section: section,
                    }]);
                    that.is_changed = false;
                    deferred.resolve();
                })
                .fail( function(data, state) {
                    if (data.responseText) {
                        console.log(data.responseText);
                    }
                    that.trigger("wa_load_fail");
                    deferred.reject(state);
                });

            return deferred.promise();
        };

        ContentRouter.prototype.reload = function() {
            var that = this,
                content_url = (history.state && history.state.content_url) ? history.state.content_url : location.href;

            return that.load(content_url, true);
        };

        ContentRouter.prototype.setContent = function(html, section) {
            var that = this;

            var is_new_section = (that.active_section === null || section.id !== that.active_section.id);
            if (is_new_section) {
                that.$wrapper.html(html);
                that.trigger("wa_updated");
            } else {
                var $content = that.$wrapper.find(section.content_selector);
                if ($content.length) {
                    $content.html(html);
                    that.trigger("wa_updated");
                } else {
                    console.error("ERROR: Content place is not found.");
                }
            }

            that.active_section = section;
        };

        ContentRouter.prototype.onPopState = function(event) {
            var that = this,
                state = (event.state || null);

            if (state && state.content_url) {
                that.reload(state.content_url);
            } else {
                location.reload();
            }
        };

        ContentRouter.prototype.trigger = function(name, options) {
            var that = this;
            that.$wrapper.trigger(name, options);
        };

        ContentRouter.prototype.getContentURL = function(url) {
            var that = this,
                result = null;

            var absolute_url = getAbsoluteURL(url);
            if (!absolute_url) { return result; }

            var absolute_main_url = window.location.origin + that.main_url;
            var is_main_url = (absolute_url.substr(0, absolute_main_url.length) === absolute_main_url);
            if (is_main_url) {
                result = absolute_url;
            }

            return result;

            function getAbsoluteURL(url) {
                var result = null;

                if (url) {
                    result = $("<a />", { href: url })[0].href;
                }

                return result;
            }
        };

        ContentRouter.prototype.getSection = function(absolute_url) {
            var that = this,
                result = null;

            var url_data = new URL(absolute_url),
                pathname = url_data.pathname,
                relative_url = pathname.replace(that.main_url, "/");

            $.each(that.routes, function(route, section) {
                route = route.replace(/\//g, "\\/");
                route = "^" + route + "$";
                var is_that = relative_url.match( new RegExp(route) );
                if (is_that) {
                    result = section;
                    return false;
                }
            });

            return result;
        };

        ContentRouter.prototype.initAnimation = function() {
            var that = this;

            var waLoading = $.waLoading();

            var $wrapper = $("#wa"),
                locked_class = "is-locked";

            that.$wrapper
                .on("wa_before_load", function() {
                    waLoading.show();
                    waLoading.animate(10000, 95, false);
                    $wrapper.addClass(locked_class);
                })
                .on("wa_loading", function(event, xhr_event) {
                    var percent = (xhr_event.loaded / xhr_event.total) * 100;
                    waLoading.set(percent);
                })
                .on("wa_abort", function() {
                    waLoading.abort();
                    $wrapper.removeClass(locked_class);
                })
                .on("wa_loaded", function() {
                    waLoading.done();
                    $wrapper.removeClass(locked_class);
                });
        };

        return ContentRouter;

    })($);

    $.wa_shop_products = {
        lang: "en",
        date_format: "d.mm.yy",
        app_url: null,
        section_url: null,
        router: null,
        init: {
            initContentRouter: function(options) {
                $.wa_shop_products.router = new ContentRouter(options);
                return $.wa_shop_products.router;
            }
        }
    }

})(jQuery);
