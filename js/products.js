(function($) {

    // This should probably be somewhere else...
    if (!Array.prototype.filter) {
      Array.prototype.filter = function(fun /*, thisp*/) {
        var len = this.length;
        if (typeof fun != "function")
          throw new TypeError();

        var res = new Array();
        var thisp = arguments[1];
        for (var i = 0; i < len; i++) {
          if (i in this) {
            var val = this[i]; // in case fun mutates this
            if (fun.call(thisp, val, i, this))
              res.push(val);
          }
        }

        return res;
      };
    }

    $.storage = new $.store();
    $.products = {
        hash : '',
        list_hash : '', // hash of last list
        list_params : {}, // params of last list
        options : {
            view : 'thumbs' // default view
        },
        random : '',
        init : function(options) {
            $.extend(this.options, options);
            this.initRouting();
            this.initSearch();
            this.initSidebar();
            this.initCollapsible();
        },

        data : {
            'prev_action' : null
        },

        initRouting : function() {
            if (typeof($.History) != "undefined") {
                $.History.bind(function() {
                    $.products.dispatch();
                });
            }
            $.wa.errorHandler = function(xhr) {
                if ((xhr.status === 403) || (xhr.status === 404)) {
                    var text = $(xhr.responseText);
                    console.log(text);
                    if (text.find('.dialog-content').length) {
                        text = $('<div class="block double-padded"></div>').append(text.find('.dialog-content *'));

                    } else {
                        text = $('<div class="block double-padded"></div>').append(text.find(':not(style)'));
                    }
                    $("#s-content").empty().append(text);
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

        dispatch : function(hash) {
            if (hash === undefined) {
                hash = window.location.hash;
            }
            hash = hash.replace(/(^[^#]*#\/*|\/$)/g, ''); /* fix syntax highlight */
            this.hash = hash;
            try {
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
                                } else if (actionName == 'product' || actionName == 'tag' || actionName == 'search' || actionName == 'plugins'
                                || actionName == 'pages' || actionName == 'stocks') {
                                    attrMarker = i;
                                    break;
                                } else if (parseInt(h, 10) != h && h.indexOf('=') == -1) {
                                    actionName += h.substr(0, 1).toUpperCase() + h.substr(1);
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
                            $.shop.trace('$.products.dispatch', [actionName + 'Action', attr]);
                            this[actionName + 'Action'].apply(this, attr);
                        } else {
                            $.shop.error('Invalid action name:', actionName + 'Action');
                        }
                    } else {
                        this.preExecute();
                        this.defaultAction();
                    }
                } else {
                    this.preExecute();
                    this.defaultAction();
                }
            } catch (e) {
                $.shop.error(e.message, e);
            }
        },

        load : function(url, callback) {
            var r = Math.random();
            this.random = r;
            var self = this;
            $.get(url, function(result) {
                if (self.random != r) {
                    // too late: user clicked something else.
                    return;
                }
                $("#s-content").removeClass('bordered-left').html(result);
                $('html, body').animate({
                    scrollTop : 0
                }, 200);
                if (callback) {
                    try {
                        callback.call(this);
                    } catch (e) {
                        $.shop.error('$.products.load callback error: ' + e.message, e);
                    }
                }
            });
        },

        addOptions : function(options) {
            this.options = $.extend(this.options, options || {});
        },

        preExecute : function(action, args) {
            try {
                if (this.data.prev_action && (this.data.prev_action != action)) {
                    var actionName = this.data.prev_action + 'Termination';
                    $.shop.trace('$.products.preExecute', [actionName, action]);
                    if (typeof(this[actionName]) == 'function') {
                        this[actionName].apply(this, []);
                    }
                }
                this.data.prev_action = action;
            } catch (e) {
                $.shop.error('preExecute error: ' + e.message, e);
            }
        },

        defaultAction : function() {
            this.productsAction();
        },

        buildProductsUrlComponent : function(params) {
            return 'view=' + (params.view || this.options.view) + (params.category_id ? '&category_id=' + params.category_id : '')
            + (params.set_id ? '&set_id=' + params.set_id : '') + (params.tag ? '&tag=' + params.tag : '') + (params.sort ? '&sort=' + params.sort : '')
            + (params.order ? '&order=' + params.order : '') + (params.text ? '&text=' + params.text : '') + (params.edit ? '&edit=' + params.edit : '')
            + (params.hash ? '&hash=' + params.hash : '') + (params.type_id ? '&type_id=' + params.type_id : '');
        },

        productsAction : function() {
            var params = Array.prototype.join.call(arguments, '/');
            params = $.shop.helper.parseParams(params || '');
            if (!params.view) {
                params.view = $.storage.get('shop/products/view') || this.options.view;
            }
            $.storage.set('shop/products/view', params.view);
            this.list_hash = this.hash;
            this.list_params = params;
            this.load('?module=products&' + this.buildProductsUrlComponent(params));
        },

        productAction : function(id, action, tab) {
            var path = Array.prototype.slice.call(arguments).filter(function(chunk) {
                return chunk.length;
            }).join('/');
            $.shop.trace('$.products.productAction', [path, arguments]);
            var url = '?module=product';
            if (id) {
                url += '&id=' + id;
            }
            if (typeof($.product) != 'undefined') {
                $.product.dispatch(path);
            } else {
                this.load(url, function(response) {
                    $.product.dispatch(path);
                });
            }
        },

        productTermination : function() {
            if (typeof($.product) != 'undefined') {
                $.product.termination();
            }
        },

        reviewsAction : function() {
            this.load('?module=reviews');
        },

        stocksAction : function(order) {
            this.load('?module=stocks' + (order ? '&order=' + order : ''));
        },

        servicesAction : function(id) {
            this.load('?module=services' + (id ? '&id=' + id : ''), function() {
                $("#s-content").addClass('bordered-left');
                if (typeof $.products.afterServicesAction === 'function') {
                    $.products.afterServicesAction();
                }
            });
        },

        initCollapsible : function() {
            var key_prefix = 'shop/products/';
            var collapse = function(el, not_save) {
                $(el).removeClass('darr').addClass('rarr');
                var item = target(el).hide();
                if (!not_save) {
                    $.storage.set(key_prefix + el.id + '/collapse', 1);
                }
                var parent = item.closest('li');
                if (parent.attr('data-type') == 'category') {
                    parent.trigger('count_subtree', true);
                }
            };
            var expand = function(el) {
                $(el).removeClass('rarr').addClass('darr');
                var item = target(el).show();
                $.storage.del(key_prefix + el.id + '/collapse');
                var parent = item.closest('li');
                if (parent.attr('data-type') == 'category') {
                    parent.trigger('count_subtree', false);
                }
            };
            var target = function(el) {
                var parent = $(el).parent();
                return parent.is('li') ? parent.find('ul:first') : parent.next();
            };

            $(".collapse-handler").die('click').live('click', function() {
                var self = $(this);
                if (self.hasClass('darr')) {
                    collapse(this);
                } else {
                    expand(this);
                }
            }).each(function() {
                var key = key_prefix + this.id + '/collapse';
                if ($.storage.get(key)) {
                    collapse(this, true);
                }
            });
        },

        initSearch : function() {
            var search = function() {
                var query = this.value, match = $.products.hash.match(/[&\/](text=.*?&|text=.*)/);
                var hash = $.products.hash;
                if (match) {
                    var text = match[1];
                    if (query) {
                        var new_text = text.substr(-1) == '&' ? 'text=' + encodeURIComponent(query) + '&' : 'text=' + encodeURIComponent(query);
                        hash = hash.replace(text, new_text);
                    } else {
                        if (text.substr(-1) != '&') {
                            text = '[&\/]' + text;
                        }
                        hash = hash.replace(new RegExp(text), '');
                    }
                } else if (query) {
                    hash = 'products/' + $.products.buildProductsUrlComponent($.products.list_params) + '&text=' + encodeURIComponent(query);
                } else {
                    hash = 'products/' + $.products.buildProductsUrlComponent($.products.list_params);
                }
                location.hash = '#/' + hash;
            };
            // HTML5 search input search-event isn't suppotred
            $('#s-products-search').unbind('keydown').bind('keydown', function(event) {
                if (event.keyCode == 13) { // 'Enter'
                    search.call(this);
                    $(this).autocomplete("close");
                    return false;
                }
            });

            $('#s-products-search').unbind('search').bind('search', function() {
                search.call(this);
                return false;
            });

            $("#s-products-search").autocomplete({
                source : '?action=autocomplete',
                minLength : 3,
                delay : 300,
                select : function(event, ui) {
                    $.wa.setHash('#/product/' + ui.item.id);
                    return false;
                }
            });
        },

        initSidebar : function() {
            var sidebar = $('#s-sidebar');
            $.product_dragndrop.init({
                collections : true
            }).bind('move_list', function(options) {
                if (!options.type) {
                    if (typeof options.error === 'function') {
                        options.error('Unknown list type');
                    }
                    return;
                }
                var data = {
                    id : options.id,
                    type : options.type,
                    parent_id : options.parent_id || 0
                };
                if (options.before_id) {
                    data.before_id = options.before_id;
                }
                $.products.jsonPost('?module=products&action=moveList', data, options.success, options.error);
            });
            sidebar.off('add', '.s-collection-list ul').on('add', '.s-collection-list ul', function(e, new_item, type) {
                var self = $(this), parent = self.parents('.s-collection-list:first');
                self.prepend(tmpl('template-sidebar-list-item', {
                    type : type,
                    item : new_item
                })).show();
                parent.find('.s-empty-list').hide();
                return false;
            });

            sidebar.unbind('update').bind('update', function(e, lists) {
                for (var type in lists) {
                    if (type == 'all') {
                        $('#s-all-products').find('.count:first').text(lists[type].count);
                        continue;
                    }
                    var prefix = '#' + type + '-';
                    for (var id in lists[type]) {
                        $(prefix + id).find('.count:first').text(lists[type][id].count);
                    }
                }
                return false;
            });

            $('#s-tag-cloud').unbind('update').bind('update', function(e, tag_cloud) {
                // redraw tag cloud
                var html = '<ul class="tags">' +
                    '<li class="block align-center">';
                for (var tag_id in tag_cloud) {
                    var tag = tag_cloud[tag_id];
                    html +=
                        '<a href="#/products/tag='  + tag.uri_name +
                            '/" style="font-size: ' + tag.size +
                            '%; opacity: ' + tag.opacity +
                            '"  data-id="' + tag.id +
                            '"  class="s-product-list">' + tag.name +
                        '</a>';
                }
                html += '</li></ul>';
                $('#s-tag-cloud').html(html).parents('.block:first').show();
                return false;
            });

            sidebar.off('count_subtree', '.s-collection-list li').on('count_subtree', '.s-collection-list li', function(e, collapsed) {
                var item = $(this);
                if (typeof collapsed === 'undefined') {
                    collapsed = item.find('i.collapse-handler').hasClass('rarr');
                }

                var counter = item.find('>.count:not(.subtree)');
                var subtree_counter = item.find('>.subtree');
                if (!subtree_counter.length) {
                    subtree_counter = counter.clone().addClass('subtree').hide();
                    counter.after(subtree_counter);
                }
                if (collapsed) {
                    var total_count = parseInt(counter.text(), 10) || 0;
                    item.find('li.dr:not(.dynamic)>.count:not(.subtree)').each(function() {
                        total_count += parseInt($(this).text(), 10) || 0;
                    });
                    subtree_counter.text(total_count).show();
                    counter.hide();
                } else {
                    subtree_counter.hide();
                    counter.show();
                }
                return false;
            });
        },

        jsonPost : function(url, data, success, error) {
            $.shop.jsonPost(url, data, success, error);
        }
    };
})(jQuery);