//live draggable and live droppable


$.fn.liveDraggable = function (opts) {
    this.each(function(i,el) {
        var self = $(this);
        if (self.data('init_draggable')) {
            self.die("mouseover", self.data('init_draggable'));
        }
    });
    this.die("mouseover").live("mouseover", function() {
        var self = $(this);
        if (!self.data("init_draggable")) {
            self.data("init_draggable", arguments.callee).draggable(opts);
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
            self.data("init_droppable", arguments.callee).droppable(opts);
            self.mouseover();
        }
    };
    init.call(this);
    this.die("mouseover", init).live("mouseover", init);
};