(function($) {
    $.product_dragndrop = {
        handlers: {},
        options: {},
        init: function(options) {
            this.options = options;
            if (options.collections) {
                this.initDragCollections();
                this.initDropCollections();
            }
            if (options.products) {
                this.initDragProducts();
                this.initDropProducts();
            }
            return this;
        },

        bind: function(event, handler) {
            this.handlers[event] = handler;
            return this;
        },

        trigger: function(event) {
            if (typeof this.handlers[event] === 'function') {
                return this.handlers[event].apply(this, Array.prototype.slice.call(arguments, 1));
            }
        },

        initDragProducts: function() {
            var product_list = $('#product-list');
            var context = product_list.find('.product:not(.s-alien)');
            context.find('.drag-handle').live('selectstart', function() {
                document.onselectstart = function() {
                    return false;
                };
                return false;
            });
            context.liveDraggable({
                opacity: 0.75,
                zIndex: 9999,
                distance: 5,
                appendTo: 'body',
                cursor: 'move',
                refreshPositions: true,
                containment: [
                      0,
                      0,
                      $(window).width(),
                      {
                          toString: function() {
                              return $(document).height();  // we have lengthened document, so make flexible calculating (use typecast method toString)
                          }
                      }
                ],
                start: function(event, ui) {
                    // prevent default-browser drag-and-drop action
                    document.ondragstart = function() {
                        return false;
                    };
                    // scroll fix. See helperScroll
                    ui.helper.data('scrollTop', $(document).scrollTop());
                    $(document).bind('scroll', $.product_dragndrop._scrolHelper);

                    $('.block.drop-target').addClass('drag-active');
                },
                handle: '.drag-handle',
                stop: function(event, ui) {
                    document.ondragstart   = null;
                    document.onselectstart = null;

                    var self = $(this);
                    if (!self.find('input:checked').length) {
                        self.removeClass('selected');
                    }
                    hideSortHint();
                    $(document).unbind('scroll', $.product_dragndrop._scrolHelper);
                    $('.block.drop-target').removeClass('drag-active');
                    
                    hideSortHint();
                },
                helper: function(event, ui) {
                    var count = 1;
                    var select_all_input = product_list.find('.s-select-all');
                    if (select_all_input.is(':checked')) {
                        count = select_all_input.attr('data-count');
                    } else {
                        var selected = product_list.find('.product.selected');
                        if (selected.length) {
                            if (selected.index(this) !== -1) {
                                count = selected.length;
                            } else {
                                $(this).trigger('select', true);
                                count = selected.length + 1;
                            }
                        } else {
                            $(this).addClass('selected');
                        }
                    }
                    return '<div id="products-helper"><span class="indicator red">'+count+'</span><i class="icon10 no-bw" style="display:none;"></i></div>';
                },
                drag: function(event, ui) {
                    var e = event.originalEvent;
                    ui.position.left = e.pageX - 20;
                    ui.position.top = e.pageY;
                }
            });
        },

        initDropProducts: function() {
            // dropping process in photo-list itself. Dropping process is trying sorting
            $('#product-list .product').liveDroppable({
                disabled: false,
                greedy: true,
                tolerance: 'pointer',
                over: function(event, ui) {
                    
                    // activate item
                    var self = $(this);
                    if (self.hasClass('s-alien')) {
                        return false;
                    }
                    if (!ui.draggable.hasClass('product')) {
                        return false;
                    }
                    
                    if (!$.product_dragndrop.trigger('is_product_sortable')) {
                        showSortHint();
                    } else {
                        hideSortHint();
                    }
                    
                    $.product_dragndrop._activatePhotoListItem.call(this);
                },
                out: function(event, ui) {
                    $.product_dragndrop._unactivatePhotoListItem.call(this);
                },
                drop: function(event, ui) {
                    if (!ui.draggable.hasClass('product')) {
                        return false;
                    }
                    
                    var self = $(this);
                    // drop into itself is illegal
                    if (self.hasClass('selected')) {
                        $.product_dragndrop._unactivatePhotoListItem.call(this);
                        return false;
                    }

                    var selected = $('#product-list').find('.product.selected');
                    
                    if (!$.product_dragndrop.trigger('is_product_sortable')) {
                        $.product_dragndrop._unactivatePhotoListItem.call(this);
                        $.product_dragndrop._unactivatePhotoListItem.call(ui.draggable);
                        return false;
                    }
                    
                    var before_id = null;
                    // Special case: self is alien (from child categories)
                    if (self.hasClass('s-alien')) {
                        var closest = self.nextAll(':not(.s-alien):first');
                        if (!closest.length) {
                            closest = self.prevAll(':not(.s-alien):first');
                            if (!closest.length || closest.hasClass('selected')) {
                                return false;
                            }
                            before_id = null;
                            closest.after(selected);
                            if (closest.hasClass('last')) {
                                closest.removeClass('last');
                                selected.filter(':last').addClass('last');    
                            }
                        } else {
                            before_id = closest.attr('data-product-id');
                            closest.before(selected);
                            if (closest.hasClass('last')) {
                                closest.removeClass('last');
                                selected.filter(':last').addClass('last');
                            }
                        }
                        
                    } else {
                        if (!self.hasClass('drag-active-last')) {
                            before_id = self.attr('data-product-id');
                        }
                        
                        if (self.hasClass('last') && self.hasClass('drag-active-last')) {
                            self.after(selected).removeClass('drag-active drag-active-last last');
                            selected.filter(':last').addClass('last');
                        } else {
                            self.before(selected).removeClass('drag-active');
                        }
                        self.removeClass('drag-active drag-active-last');
                    }
                    
                    $.product_dragndrop._unactivatePhotoListItem.call(this);
                    $.product_dragndrop._unactivatePhotoListItem.call(ui.draggable);
                    
                    selected.trigger('select', false);

                    var product_ids = selected.map(function() {
                        return $(this).attr('data-product-id');
                    }).toArray();
                    $.product_dragndrop.trigger('move_product', {
                        product_ids: product_ids, before_id: before_id,
                        error: function(r) {
                            if (r && console) {
                                console.log(typeof r.errors !== 'undefined' ? r.errors : r);
                            }
                            // TODO: restore
                        }
                    });
                }
            });
        },

        initDragCollections: function() {
            var containment = $('#wa-app .sidebar:first');
            var containment_pos = containment.position();
            var containment_metrics = { width: containment.width(), height: containment.height() };

            $(".s-collection-list li.dr").liveDraggable({
                containment: [
                      containment_pos.left,
                      containment_pos.top,
                      containment_pos.left + containment_metrics.width * 1.25,
                      containment_pos.top + containment_metrics.height * 1.25
                ],
                refreshPositions: true,
                revert: 'invalid',
                helper: function() {
                    var self = $(this);
                    var parent = self.parents('.s-collection-list:first').find('ul:first');
                    var clone = self.clone().addClass('ui-draggable dr-helper').css({
                        position: 'absolute'
                    }).prependTo(parent);
                    clone.find('a:first').append('<i class="icon10 no-bw" style="margin-left: 0; margin-right: 0; display:none;"></i>');
                    return clone;
                },
                cursor: 'move',
                cursorAt: { left: 5 },
                opacity: 0.75,
                zIndex: 9999,
                distance: 5,
                start: function(event, ui) {
                    document.ondragstart = function() {
                        return false;
                    };
                },
                stop: function() {
                    document.ondragstart = null;
                }
            });
        },

        initDropCollections: function() {
            this.initDropBetweenCollections();
            this.initDropInsideCollections();
        },

        initDropBetweenCollections: function() {
            $('.s-collection-list li.drag-newposition').liveDroppable({
                greedy: true,
                tolerance: 'pointer',
                over: function(event, ui) {
                    var self = $(this);
                    if (ui.draggable.attr('data-type') != self.attr('data-type')) {
                        return false;
                    }
                    self.addClass('active').parent().parent().addClass('drag-active');
                },
                out: function(event, ui) {
                    var self = $(this);
                    if (ui.draggable.attr('data-type') != self.attr('data-type')) {
                        return false;
                    }
                    self.removeClass('active').parent().parent().removeClass('drag-active');
                },
                deactivate: function(event, ui) {
                    var self = $(this);
                    if (ui.draggable.attr('data-type') != self.attr('data-type')) {
                        return false;
                    }
                    if (self.is(':animated') || self.hasClass('dragging')) {
                        self.stop().animate({ height: '2px' }, 300, null,
                                function() {
                                    self.removeClass('dragging');
                                }
                        );
                    }
                    self.removeClass('active').parent().parent().removeClass('drag-active');
                },
                drop: function(event, ui) {
                    var self = $(this);
                    var dr = ui.draggable;
                    var type = dr.attr('data-type');
                    if (type != self.attr('data-type')) {
                        return false;
                    }

                    var getRawId = function(id) {
                        var parts = id.split('-');
                        parts.shift();
                        return parts.join('-');
                    };


                    var id = getRawId(dr.attr('id'));
                    var prev = self.prev('li');
                    var sep  = dr.next();
                    var home = dr.prev();

                    if (prev.length && prev.attr('id') == 'category-'+id && !prev.hasClass('dr-helper')) {
                        return false;
                    }
                    if (this == dr.next().get(0)) {
                        return false;
                    }

                    var parent_list = dr.parent('ul');
                    var li_count = parent_list.children('li.dr[id!=category-'+id+']').length;
                    var old_parent_id = parseInt(getRawId(parent_list.parent().attr('id')), 10) || 0;

                    self.after(sep).after(dr);

                    var parent = dr.parent().parent();
                    if (parent.is('li.dr') || parent.is('.s-collection-list')) {
                        var parent_id = 0;
                        if (!parent.is('.s-collection-list')) {
                            parent_id = parseInt(getRawId(parent.attr('id')), 10) || 0;
                        }
                        var next = dr.nextAll('li.dr:first');
                        var before_id = null;
                        if (next.length) {
                            before_id = getRawId(next.attr('id')) || null;
                        }

                        if (!li_count && old_parent_id !== parent_id) {
                            parent_list.parent('li').children('i').hide();
                            parent_list.hide();
                        }

                        $.product_dragndrop.trigger('move_list', {
                            id: id, type: type, before_id: before_id, parent_id: parent_id,
                            success: function(r) {
                                if (!li_count && old_parent_id !== parent_id) {
                                    parent_list.parent('li').children('i').remove();
                                    parent_list.remove();
                                    $.categories_tree.setCollapsed(old_parent_id);
                                }
                            },
                            error: function(r) {
                                if (r && console) {
                                    console.log(typeof r.errors !== 'undefined' ? r.errors : r);
                                }
                                // restore
                                home.after(dr.next()).after(dr);
                                if (!li_count) {
                                    parent_list.parent('li').children('i').show();
                                    parent_list.show();
                                }
                            }
                        });
                    }
                }
            });
        },

        initDropInsideCollections: function() {
            $('.s-collection-list li.dr a').liveDroppable({
                tolerance: 'pointer',
                greedy: true,
                out: function(event, ui) {
                    var dr = ui.draggable;
                    var self = $(this).parent();
                    if (!dr.hasClass('product') && self.attr('data-type') != dr.attr('data-type')) {
                        return false;
                    }
                    if (dr.hasClass('product')) {
                        ui.helper.find('span').show().end().find('i').hide();       // show 'circle'-icon
                    }
                    self.removeClass('drag-newparent');
                },
                over: function(event, ui) {
                    var dr = ui.draggable;
                    var self = $(this).parent();
                    var type = dr.attr('data-type');
                    if (!dr.hasClass('product') && type != self.attr('data-type')) {
                        return false;
                    }
                    if (type != 'set') {
                        self.addClass('drag-newparent');
                    }
                    if (dr.hasClass('product')) {
                        if (self.hasClass('dynamic')) {
                            ui.helper.find('span').hide().end().find('i').show();   // show 'cross'-icon
                        } else {
                            ui.helper.find('span').show().end().find('i').hide();   // show 'circle'-icon
                        }
                        return false;
                    }

                    if (!dr.hasClass('dynamic') && self.hasClass('dynamic')) {
                        ui.helper.find('i.no-bw').show();
                        return false;
                    } else {
                        ui.helper.find('i.no-bw').hide();
                    }

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
                        return arguments.callee(nearby, current.parent().closest('li'));
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

                    old.stop().animate({height: '2px'}, 300, null, function() {
                        old.removeClass('dragging');
                    });
                    nearby.stop().animate({height: '10px'}, 300, null, function() {
                        nearby.addClass('dragging');
                    });
                },
                drop: function(event, ui) {
                    var dr = ui.draggable;
                    var self = $(this).parent();
                    var type = dr.attr('data-type');
                    if (!dr.hasClass('product')) {
                        if (type == 'set') {
                            return false;
                        }
                        if (self.attr('id') == dr.attr('id')) {
                            return false;
                        }
                        if (type != self.attr('data-type')) {
                            return false;
                        }
                    }
                    self.removeClass('drag-newparent');

                    // coping product to category section
                    if (dr.hasClass('product')) {
                        if (self.hasClass('dynamic')) {
                            return false;
                        }
                        var product_list = $('#product-list');

                        var data = {};
                        if (product_list.find('.s-select-all').is(':checked')) {
                            data.whole_list = true;
                        } else {
                            var products = product_list.find('.product.selected');
                            data.product_ids = products.map(function() {
                                return $(this).attr('data-product-id');
                            }).toArray();
                        }

                        $.product_dragndrop.trigger('add_to_list', $.extend(data, {
                            collection_param: self.attr('id').replace('-', '_id='),
                            success: function(r) {
                                if (data.whole_list) {
                                    product_list.find('.s-select-all').trigger('select', false);
                                } else if (data.product_ids && data.product_ids.length) {
                                    products.trigger('select', false);
                                }
                                if (r.data) {
                                    self.find('>.counters .count:not(.subtree)').text(r.data.count);
                                    self.find('>.counters .subtree').text(r.data.total_count);
                                }
                            }
                        }));
                        return false;
                    } else {
                        if (!dr.hasClass('dynamic') && self.hasClass('dynamic')) {
                            return false;
                        }
                    }

                    // sorting categories
                    var id = dr.attr('id').split('-')[1];
                    if (self.attr('id') == 'category-'+id) {
                        return false;
                    }

                    if (dr.hasClass('product')) {
                        var selected = $('#product-list').find('.product.selected');
                        selected.trigger('select', false);
                        return false;
                    }

                    var parent_id = parseInt(self.attr('id').split('-')[1], 10) || 0;

                    var list = null;
                    var sep  = dr.next();
                    var home = dr.prev();

                    // for tracking situation when list acceptor has no children
                    // and inserted item will be first
                    var first_child = false;

                    if (self.hasClass('drag-newposition')) {
                        list = self.parent('ul');
                    } else {
                        // has loaded (via ajax) children items
                        if (self.children('ul').length) {
                            list =  self.children('ul');
                        } else if (!self.find('>i.collapse-handler-ajax').length) { // no children

                            list = $(
                                '<ul class="menu-v with-icons dr unapproved">' +
                                    '<li class="drag-newposition unapproved" data-type="'+type+'"></li>' +
                                '</ul>'
                            ).appendTo(self);
                            list.find('.drag-newposition').mouseover(); // init droppable
                            $('<i class="icon16 darr overhanging collapse-handler-ajax unapproved" id="' +
                                    type + '-' + parent_id + '-handler' +
                                '"></i>').insertBefore(self.children('a'));

                            first_child = true;
                        }
                    }

                    var parent_list = dr.parent('ul');
                    var li_count = parent_list.children('li.dr[id!=category-'+id+']').length;
                    var old_parent_id = parseInt(parent_list.parent().attr('id').split('-')[1], 10) || 0;

                    if (list) {
                        list.append(dr).append(sep);
                    }

                    if (!li_count && old_parent_id !== parent_id) {
                        parent_list.parent('li').children('i').hide();
                        parent_list.hide();
                    }

                    var parent = self;
                    if (parent.is('li.dr')) {
                        $.product_dragndrop.trigger('move_list', {
                            id: id, type: type, parent_id: parent_id,
                            success: function(r) {
                                if (!li_count && old_parent_id !== parent_id) {
                                    parent_list.parent('li').children('i').remove();
                                    parent_list.remove();
                                    $.categories_tree.setCollapsed(old_parent_id);
                                }
                                // has children, but not loaded yet
                                if (!list) {
                                    dr.remove();
                                    sep.remove();
                                    // Because children is not loaded yet, calling of count_subtree (see below)
                                    // will not have effect.
                                    // So counters is returned by server
                                    self.trigger('update_counters',
                                        r.data.count || {}
                                    );
                                } else {
                                    $('.s-collection-list .unapproved').removeClass('unapproved');
                                    if (first_child) {
                                        $.categories_tree.setExpanded(parent_id);
                                    } else {
                                        self.trigger('count_subtree');
                                    }
                                }
                            },
                            error: function(r) {
                                if (r && console) {
                                    console.log(typeof r.errors !== 'undefined' ? r.errors : r);
                                }
                                // restore
                                home.after(dr).after(sep);
                                if (!li_count) {
                                    parent_list.parent('li').children('i').show();
                                    parent_list.show();
                                }
                                $('.s-collection-list .unapproved').remove();
                            }
                        });
                    }
                }
            });
        },

        // when scrolling page drag-n-drop helper must moving too with cursor
        _scrolHelper: function(e) {
            var helper = $('#products-helper'),
                prev_scroll_top = helper.data('scrollTop'),
                scroll_top = $(document).scrollTop(),
                shift = prev_scroll_top ? scroll_top - prev_scroll_top : 50;

            helper.css('top', helper.position().top + shift + 'px');
            helper.data('scrollTop', scroll_top);
        },

        // active/inactive drop-item both left and right
        _extDragActivate: function(e, self, className) {
            var classNameOfLast = className + '-last';
            if (!self.hasClass('last')) {
                self.addClass('drag-active');
                return;
            }
            var pageX = e.pageX,
                pageY = e.pageY,
                self_width = self.width(),
                self_height = self.height(),
                self_offset = self.offset();

            if ($.product_dragndrop.options.view == 'thumbs') {
                if (pageX > self_offset.left + self_width*0.5 && pageX <= self_offset.left + self_width) {
                    self.removeClass(className).addClass(classNameOfLast);
                    $.product_dragndrop._shiftToLeft(self);
                } else if (pageX > self_offset.left && pageX <= self_offset.left + self_width*0.5) {
                    self.removeClass(classNameOfLast).addClass(className);
                    $.product_dragndrop._shiftToRight(self);
                } else {
                    $.product_dragndrop._shiftAtPlace(self);
                }
            } else if ($.product_dragndrop.options.view == 'table') {
                if (pageY > self_offset.top + self_height*0.5) {
                    self.removeClass(className).addClass(classNameOfLast);
                } else if (pageY > self_offset.top) {
                    self.removeClass(classNameOfLast).addClass(className);
                }
            }
            if (pageY < self_offset.top || pageY > self_offset.top + self_height ||
                    pageX < self_offset.left || pageX > self_offset.left + self_width)
            {
                self.removeClass(className).removeClass(classNameOfLast);
            }
        },

        _bindExtDragActivate: function(item, className) {
            $(document).bind('mousemove.ext_drag_activate', function (e) {
                $.product_dragndrop._extDragActivate(
                    e,  item, className
                );
            });
        },
        
        _unbindExtDragActivate: function() {
            $(document).unbind('mousemove.ext_drag_activate');
        },
                
        _activatePhotoListItem: function() {
                var self = $(this);
                var sort_enable = $.product_dragndrop.trigger('is_product_sortable');
                var className = sort_enable ? 'drag-active' : 'drag-active-disabled';
                if (sort_enable && $.product_dragndrop.options.view == 'thumbs') {
                    $.product_dragndrop._shiftToRight(self);
                }
                if (self.hasClass('last')) {
                    $.product_dragndrop._bindExtDragActivate(self, className);
                } else {
                    self.addClass(className);
                }  
        },

        // clear (unactive) action
        _unactivatePhotoListItem: function() {
            var self = $(this);
            var sort_enable = $.product_dragndrop.trigger('is_product_sortable');
            var className =  sort_enable ? 'drag-active' : 'drag-active-disabled';
            var classNameOfLast = className + '-last';
            if (self.hasClass('last')) {
                $.product_dragndrop._unbindExtDragActivate();
            }
            self.removeClass(className + ' ' + classNameOfLast);
            if (sort_enable && $.product_dragndrop.options.view == 'thumbs') {
                $.product_dragndrop._shiftAtPlace(self);
            }
        },

        _shiftToLeft: function(item) {
            if (item.data('shifted') !== 'left') {
                var wrapper = item.find('.p-wrapper');
                if (!wrapper.length) {
                    var children = item.children();
                    var wrapper = $("<div class='p-wrapper' style='position:relative;'></div>").appendTo(item);
                    wrapper.append(children);
                }
                wrapper.stop().animate({
                    left: -15
                }, 200);
                item.data('shifted', 'left');
            }
        },
        _shiftToRight: function(item) {
                if (item.data('shifted') !== 'right') {
                    var wrapper = item.find('.p-wrapper');
                    if (!wrapper.length) {
                        var children = item.children();
                        var wrapper = $("<div class='p-wrapper' style='position:relative;'></div>").appendTo(item);
                        wrapper.append(children);
                    }
                    wrapper.stop().animate({
                        left: 15
                    }, 200);
                    item.data('shifted', 'right');
                }
        },
        _shiftAtPlace: function(item) {
            if (item.data('shifted')) {
                var wrapper = item.find('.p-wrapper');
                if (wrapper.length) {
                    var children = wrapper.children();
                    wrapper.stop().css({
                        left: 0
                    });
                    item.append(children);
                    wrapper.remove();
                }
                item.data('shifted', '');
            }
        }

    };
    
    function showSortHint() {
        var sort_method = $.product_dragndrop.options.sort;
        if (sort_method) {
            var block = $('#hint-menu-block').show();
            block.children().hide();
            block.find('.' + sort_method).show();
        }
    }
    
    function hideSortHint() {
            var block = $('#hint-menu-block').hide();
            block.children().hide();
    }
    
})(jQuery);
