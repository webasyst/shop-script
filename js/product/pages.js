(function($) {
    $.product_pages = {

        /**
         * {Jquery object}
         */
        container: null,

        /**
         * {Jquery object}
         */
        sidebar: null,

        /**
         * {Number}
         */
        product_id: 0,

        page_id: 0,

        button_color: null,

        init: function(options) {
            if (options.container) {
                if (typeof options.container === 'object') {
                    this.container = options.container;
                } else {
                    this.container = $(options.container);
                }
                this.sidebar = this.container.find('.sidebar');
            }
            this.tab_counter = $('#s-product-edit-menu').find('li.pages .hint:first');
            this.product_id = options.product_id;
            this.page_id = options.page_id || 0;
            if (options.count) {
                this.tab_counter.text(options.count);
            }

            $.product.editTabPagesAction = function(path) {
                var button = $('#s-product-save-button');
                if (!$.product_pages.button_color) {
                    $.product_pages.button_color = button.hasClass('yellow') ? 'yellow' : 'green';
                }
                button.removeClass('yellow').addClass('green');
                var page_id = parseInt(path.tail, 10);
                if (!page_id) {
                    page_id = $.product_pages.container.find('.s-pages').find('.selected').attr('id');
                    if (!page_id) {
                        $.product_pages.loadPage();
                    } else {
                        $.product_pages.loadPage(parseInt(page_id.replace('page-', '')), 10);
                    }
                } else {
                    $.product_pages.loadPage(page_id);
                }
            };

            $.product.editTabPagesBlur = function() {
                var button = $('#s-product-save-button');
                if (button.hasClass('yellow')) {
                    var form = $('#s-page-form');
                    $.shop.jsonPost(form.attr('action'), form.serialize(), function(r) {
                        if (!$.product_pages.page_id) {
                            $.product_pages.tab_counter.text(
                                    parseInt($.product_pages.tab_counter.text(), 10) + 1 || 0
                            );
                        }
                    });
                    $.product_pages.container.remove();
                }
                button.removeClass('yellow green').addClass($.product_pages.button_color);
                $.product_pages.button_color = null;
                $.product_pages.container.find("#s-page-container").html('');
            };

            $.product.editTabPagesSave = function() {
                var form = $("#s-page-form");
                if (!form.length) {
                    return;
                }
                var li = $.product_pages.sidebar.find("li.selected");
                $.product.refresh('submit');
                $.shop.jsonPost(form.attr('action'), form.serialize(),
                    function(r) {
                        $.product.refresh('succes', $_('Save'));
                        var page = r.data;
                        if (!page.status) {
                            page.name += ' <span class="s-page-draft">' + $_('draft') + '</span>';
                        }
                        var html = $('<li id="page-' + page.id + '" class="dr selected">' +
                                '<a class="wa-page-link" href="' + $.product_pages.getUrl(page.id) + '">' +
                                '<i class="icon16 notebook"></i>' + page.name + ' <span class="hint">/' + page.url_escaped +'</span>' + '</a></li>');
                        if (!li.hasClass('s-add-page')) {
                            li.replaceWith(html);
                            li.remove();
                        } else {
                            li.removeClass('selected');
                            li.before(html);
                        }
                        if (!$.product_pages.page_id) {
                            $.product_pages.tab_counter.text(parseInt($.product_pages.tab_counter.text(), 10) + 1 || 0);
                            location.hash = '#/product/' + $.product_pages.product_id + '/edit/pages/' + page.id + '/';
                            $('#page-'+page.id).after('<li class="drag-newposition ui-droppable"></li>');
                        } else {
                            var container = $.product_pages.container;
                            container.find(".s-page-editor h2").html(page.name);
                            container.find(".s-page-urls a").each(function(index) {
                                var self = $(this);
                                var url = r.data.frontend_url+'/'+r.data.url+'/';
                                self.attr('href', url + '?preview='+r.data.preview_hash);
                                if (index == 0) {
                                    self.text(url);
                                }
                            });
                        }
                        $('#s-product-save-button').removeClass('yellow green').addClass('green');
                        $('#s-product-edit-menu li.pages .s-product-edit-tab-status').html('');
                    }
                );

                return false;
            };

            this.initSidebar();
            this.initPageContainer();
        },

        initSidebar: function() {
            var sidebar = this.sidebar;

            sidebar.find(".block-pages").off('click', 'i.s-page-add').on('click', 'i.s-page-add', function () {
                var li = $('<li class="selected"><a class="s-page-link" href="'+$.product_pages.getUrl()+'"><i class="icon16 notebook"></i>'+$_('New page')+'</a></li>');
                sidebar.find('li.selected').removeClass('selected');
                $(this).closest('h4').next('ul').append(li);
                $.product_pages.loadPage();
                li.children('a').click();
                return false;
            });
            sidebar.find(".block-pages ul").off('click', 'li a.s-page-link').on('click', 'li a.s-page-link', function (e) {
                if ($(e.target).hasClass('s-page-add')) return true;
                var p = $(this).parent();
                if (p.attr('id')) {
                    $.product_pages.loadPage(p.attr('id').replace(/page-/, ''));
                } else {
                    $.product_pages.loadPage();
                    sidebar.find('.selected').removeClass('selected');
                    $(this).parent().addClass('selected');
                    return false;
                }
            });
            this.initDragndrop();
        },

        initPageContainer: function() {
            var container = this.container;
            container.off('click', '.s-page-delete').on('click', '.s-page-delete', function () {
                if (confirm($_('This will delete entire page. Are you sure?'))) {
                    var page_id = $.product_pages.page_id;
                    $.shop.jsonPost($(this).attr('href'), { id: page_id }, function () {
                        var li = container.find("#page-"+page_id);
                        var prev = li.prev().prev('.dr');
                        var url;
                        if (prev.length) {
                            url = prev.addClass('selected').find('a').attr('href');
                        } else {
                            var next = li.next().next('.dr');
                            if (next.length) {
                                url = next.addClass('selected').find('a').attr('href');
                            } else {
                                url = $.product_pages.getUrl();
                            }
                        }
                        location.href = url;
                        li.remove();
                        $.product_pages.tab_counter.text(parseInt($.product_pages.tab_counter.text(), 10) - 1 || 0);
                    });
                }
                return false;
            });

        },

        loadPage: function(id) {
            this.page_id = id || 0;
            var self  = this;
            var onLoad = function(html) {
                self.container.find("#s-page-container").html(html);
                self.sidebar.find('li.selected').removeClass('selected');
                if (self.page_id) {
                    var li = self.sidebar.find('#page-' + self.page_id);
                    if (li.length) {
                        li.addClass('selected');
                        return;
                    }
                }
                self.page_id = 0;
                self.sidebar.find('li:last').addClass('selected');
            };

            $.get("?module=product&action=pageEdit&id="+this.page_id+'&product_id='+this.product_id, function(html) {
                onLoad(html);
            });
        },

        initDragndrop: function() {
            var sidebar = this.sidebar;
            var ul = sidebar.find('ul');
            ul.find('li.dr').liveDraggable({
                opacity: 0.75,
                zIndex: 9999,
                distance: 5,
                appendTo: ul,
                cursor: 'move',
                refreshPositions: true,
                helper: 'clone'
            });

            ul.find('li.drag-newposition').liveDroppable({
                greedy: true,
                tolerance: 'pointer',
                over: function() {
                    $(this).addClass('active').parent().parent().addClass('drag-active');
                },
                out: function() {
                    $(this).removeClass('active').parent().parent().removeClass('drag-active');
                },
                deactivate: function() {
                    var self = $(this);
                    if (self.is(':animated') || self.hasClass('dragging')) {
                        self.stop().animate({height: '2px'}, 300, null, function(){self.removeClass('dragging');});
                    }
                    self.removeClass('active').parent().parent().removeClass('drag-active');
                },
                drop: function(event, ui) {
                    var self = $(this);
                    var dr = ui.draggable;
                    var id = dr.attr('id').replace('page-', '');
                    var sep  = dr.next();
                    var home = dr.prev();

                    var next = self.nextAll('li.dr:not(#page-'+id+'):first');
                    var before_id = null;
                    if (next.length) {
                        before_id = next.attr('id').replace('page-', '') || null;
                    }
                    $.shop.jsonPost('?module=product&action=pageMove', { id: id, before_id: before_id }, null,
                        function(r) {
                            // restore
                            home.after(dr.next()).after(dr);
                        }
                    );

                    self.after(sep).after(dr);

                }
            });

            ul.find('li.dr a').liveDroppable({
                tolerance: 'pointer',
                greedy: true,
                over: function(event, ui) {
                    var dr = ui.draggable;
                    var self = $(this).parent();

                    var drSelector = '.dr[id!="'+dr.attr('id')+'"]';
                    var nearby = $();

                    // helper to widen all spaces below the current li and above next li (which may be on another tree level, but not inside current)
                    var addBelow = function(nearby, current) {
                        if (!current.length) {
                            return nearby;
                        }
                        nearby = nearby.add(current.nextUntil(drSelector).filter('li.drag-newposition'));
                        if (current.nextAll(drSelector).length > 0) {
                            return nearby;
                        }
                        return arguments.callee(nearby, current.parent().closest('li:not(.s-add-page)'));
                    };

                    // widen all spaces above the current li and below the prev li (which may be on another tree level)
                    var above = self.prevAll(drSelector+':first');
                    if(above.length > 0) {
                        var d = above.find(drSelector);
                        if (d.length > 0) {
                            nearby = addBelow(nearby, d.last());
                        } else {
                            nearby = addBelow(nearby, above);
                        }
                    } else {
                        nearby = nearby.add(self.prevUntil(drSelector).filter('li.drag-newposition'));
                    }

                    // widen all spaces below the current li and above the next li (which may be on another tree level)
                    if (self.children('ul').children(drSelector).length > 0) {
                        nearby = nearby.add(self.children('ul').children('li.drag-newposition:first'));
                    } else {
                        nearby = addBelow(nearby, self);
                    }

                    var old = $('.drag-newposition:animated, .drag-newposition.dragging').not(nearby);
                    old.stop().animate({height: '2px'}, 300, null, function(){old.removeClass('dragging');});
                    nearby.stop().animate({height: '10px'}, 300, null, function(){nearby.addClass('dragging');});
                }
            });
        },

        getUrl: function(id) {
            return '#/product/'+this.product_id+'/edit/pages/' + (id || '');
        }
    };
})(jQuery);
