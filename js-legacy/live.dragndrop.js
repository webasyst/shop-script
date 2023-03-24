//live draggable and live droppable


$.fn.liveDraggable = function (opts) {
    this.each(function(i,el) {
        var self = $(this);
        if (self.data('init_draggable')) {
            self.die("mouseover", self.data('init_draggable'));
        }
    });
    var h;
    this.die("mouseover").live("mouseover", h = function() {
        var self = $(this);
        if (!self.data("init_draggable")) {
            self.data("init_draggable", h).draggable(opts);
        }
    });
};
$.fn.liveDroppable = function (opts) {
    this.each(function(i,el) {
        var self = $(this);
        if (self.data('init_droppable')) {
            self.die("mouseover", self.data('init_droppable'));
        }
    });
    var init = function() {
        var self = $(this);
        if (!self.data("init_droppable")) {
            self.data("init_droppable", init).droppable(opts);
            self.mouseover();
        }
    };
    init.call(this);
    this.die("mouseover", init).live("mouseover", init);
};