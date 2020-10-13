( function($) {

    var Dialog = ( function($) {

        Dialog = function(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];

            // CONST
            that.dialog = that.$wrapper.data("dialog");
            that.sets = options["sets"];

            // DYNAMIC VARS

            // INIT
            that.init();
        };

        Dialog.prototype.init = function() {
            var that = this;

            var $list = that.$wrapper.find(".js-sets-list");

            that.$wrapper.on("keyup change", ".js-filter-field", function(event) {
                var value = $(this).val().toLowerCase();

                $list.find(".s-set .s-name").each( function() {
                    var $text = $(this),
                        $set = $text.closest(".s-set"),
                        is_good = ($text.text().toLowerCase().indexOf(value) >= 0);

                    if (value.length) {
                        if (is_good) {
                            $set.show();
                        } else {
                            $set.hide();
                        }
                    } else {
                        $set.show();
                    }
                });
            });

            that.$wrapper.on("click", ".js-add-sets", function(event) {
                event.preventDefault();
                that.dialog.options.onSuccess(getActiveSets());
                that.dialog.close();
            });

            function getActiveSets() {
                var result = [];

                $list.find(".js-set-field:checked").each( function() {
                    var $set = $(this).closest(".s-set");

                    var set_id = $set.data("id"),
                        set = that.sets[set_id];

                    result.push(set);
                });

                return result;
            }
        };

        return Dialog;

    })($);

    $.wa_shop_products.init.initProductGeneralAddSetsDialog = function(options) {
        return new Dialog(options);
    };

})(jQuery);