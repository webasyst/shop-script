/**
 *
 * @names stock*
 * @method stocksInit
 * @method stocksAction
 * @method stocksBlur
 */
$.extend($.settings = $.settings || {}, {

  stocksInit : function(options) {
      this.stock_options = options;

      var form = $('#s-settings-stocks-form');
      var content = $('#s-settings-stocks');
      $('#s-settings-add-stock').unbind('click').bind('click', function () {
          // render new item
          var new_tr = $('<tr class="new-stock"></tr>').html($.settings.stock_options.new_stock);

          // .s-inventory-stock checkbox enable or disable
          if (content.find('tr.new-stock').length < 1) {
              new_tr.find('.s-inventory-stock').show().find('input').attr('disabled', false);
          } else {
              new_tr.find('.s-inventory-stock').hide().find('input').attr('disabled', true);
          }

          content.prepend(new_tr);
          new_tr.find('input[data-name="name"]').select();
          form.find('input[type=submit]').show();
          return false;
      });

      if ($.storage.get('shop/settings/stock/just-saved')) {
          $.storage.del('shop/settings/stock/just-saved');
          form.find(':submit').siblings('.s-msg-after-button').show().animate({ opacity: 0 }, 2000, function() {
              $(this).hide();
          });
      }

      content.off('click', '.s-delete-stock').on('click', '.s-delete-stock', function() {
          var tr = $(this).parents('tr:first');
          var stock_id = parseInt(tr.attr('data-id'), 10);
          if (tr.length) {
               if (stock_id) {

                   var d = null;
                   if (content.find('.s-stock').length > 1) {
                       d = $("#s-settings-delete-stock");
                   } else {
                       d = $("#s-settings-delete-last-stock");
                   }

                   if (d.parent().get(0) != document.body) {
                       $(document.body).append(d);
                   }
                   d.waDialog({
                       disableButtonsOnSubmit: true,
                       onLoad: function() {
                           var form = d.find('form:first');
                           var dst_stock = form.find('select[name=dst_stock]');
                           dst_stock.find('option').attr('disabled', false).show();
                           dst_stock.find('option[value='+stock_id+']').attr('disabled', true).hide();
                           var first = dst_stock.find('option:not(:disabled):first');
                           first.attr('selected', true);
                           if (!d.data('inited')) {
                               form.find('input[name=delete_stock]').change(function() {
                                   if ($(this).val() == '1') {
                                       dst_stock.attr('disabled', false);
                                   } else {
                                       dst_stock.attr('disabled', true);
                                   }
                               });
                               d.data('inited', true);
                           }
                       },
                       onSubmit: function() {
                           tr.hide();
                           var form = d.find('form:first');
                           var dst_stock = form.find('select[name=dst_stock]');
                           var option = dst_stock.find('option[value='+stock_id+']');
                           $.post(form.attr('action')+'&id='+stock_id, form.serializeArray(),
                               function(r) {
                                   if (r.status == 'ok') {
                                       if (dst_stock.find('option').length <= 1) {
                                           // need different dialog content, so reloading
                                           $.settings.dispatch('#/stock/', true);
                                       } else {
                                           tr.remove();
                                           option.remove();
                                       }
                                   } else {
                                       tr.show();
                                       if (console) {
                                           if (r && r.errors) {
                                               console.error(r.errors);
                                           }
                                           if (r && r.responseText) {
                                               console.error(r.responseText);
                                           }
                                       }
                                   }
                                   d.trigger('close');
                               }, 'json'
                           ).error(function(r) {
                               tr.show();
                               option.show();
                               if (console) {
                                   console.error(r && r.responseText ? 'Error:' + r.responseText : r);
                               }
                               d.trigger('close');
                           });
                           return false;
                       }
                   });
               } else {
                   tr.remove();
               }
           }
           return false;
       });

       content.off('click', '.s-edit-stock').on('click', '.s-edit-stock', function() {
           var tr = $(this).parents('tr:first');
           tr.find('h3').hide();
           tr.find('input').attr('disabled', false).show();
           tr.find('span.s-count span').hide();
           form.find('input[type=submit]').show();
           return false;
       });

       var validateBoundary = function(input, name) {
           var val = parseInt(input.val(), 10);
           var tr = input.parents('tr:first');
           var other = name == 'low_count' ? tr.find('input[data-name=critical_count]') : tr.find('input[data-name=low_count]');
           var error = '';
           var validate_errors = $.settings.stock_options.validate_errors;
           if (
               (input.val() && isNaN(val)) ||
               (!input.val() && parseInt(other.val(), 10)) ||
               val < 0)
           {
               error = validate_errors.number;
           } else if (name == 'low_count' && val < parseInt(other.val(), 10)) {
               error = validate_errors.no_less;
           } else if (name == 'critical_count' && val > parseInt(other.val(), 10)) {
               error = validate_errors.no_greater;
           }
           if (error) {
               tr.addClass('has-errors');
               input.addClass('error').nextAll('.errormsg:first').text(error).show();
           } else {
               input.removeClass('error').nextAll('.errormsg:first').hide();
           }
           if (!tr.find('.error:first').length) {
               tr.removeClass('has-errors');
           }
       };

       content.off('keydown', 'input[type=text]').on('keydown', 'input[type=text]', function() {
           var item = $(this);
           var timer_id = item.data('timer_id');
           if (timer_id) {
               clearTimeout(timer_id);
           }
           item.data('timer_id', setTimeout(function() {
               var name = item.attr('data-name');
               
               if (name === 'name') {
                   if (!item.val()) {
                       item.addClass('error').nextAll('.errormsg:first').text(
                           $.settings.stock_options.validate_errors.empty
                       ).show();
                   } else {
                       item.removeClass('error').nextAll('.errormsg:first').text('').hide();
                   }
                   return;
               }
               if (name === 'low_count' || name === 'critical_count') {
                   var other = name == 'low_count' ?
                        item.parents('tr:first').find('input[data-name=critical_count]') :
                        item.parents('tr:first').find('input[data-name=low_count]');
                    validateBoundary(item, name);
                    validateBoundary(other, other.attr('data-name'));

                    if (form.find('.error:first').length) {
                        form.find('input[type=submit]').attr('disabled', true);
                    } else {
                        form.find('input[type=submit]').attr('disabled', false);
                    }
                    return;
               }
               
           }, 450));
       });

       form.submit(function() {
           var form = $(this);
           content.find('tr.new-stock').each(function() {
               var tr = $(this);
               if (tr.hasClass('new-stock')) {
                   var before_id = 0;
                   var next = tr.nextAll('tr:not(.new-stock):first');
                   if (next.length) {
                       before_id = next.attr('data-id');
                   }
                   tr.find('input[name^=add\\[before_id]').remove();
                   tr.append('<input type="hidden" name="add[before_id][]" value="'+before_id+'">');
               }
           });
           $.post(form.attr('action'), form.serialize(), function(r) {
               if (r.status == 'ok') {
                   $.storage.set('shop/settings/stock/just-saved', true);
                   $.settings.dispatch('#/stock', true);
               } else {
                   if (console) {
                       console.error(r && r.errors ? r.errors : r);
                   }
               }
           }, 'json').
           error(function(r) {
               if (console) {
                   console.error(r && r.responseText ? r.responseText : r);
               }
           });
           return false;
       });

       content.find('tbody:first').sortable({
           distance: 5,
           helper: 'clone',
           items: 'tr',
           handle: 'i.sort',
           opacity: 0.75,
           tolerance: 'pointer',
           update: function (event, ui) {
               var tr = ui.item;
               var id = parseInt(tr.attr('data-id'), 10);
               var next, before_id = 0;
               if (id) {
                   next = tr.nextAll('tr:not(.new-stock):first');
                   if (next.length) {
                       before_id = next.attr('data-id');
                   }
                   $.post('?module=settings&action=moveStock', { id: id, before_id: before_id },
                       function(r) {
                           if (r.status != 'ok') {
                               content.sortable('cancel');
                               if (console) {
                                   console.error(r && r.errors ? r.errors : r);
                               }
                           }
                       }, 'json'
                   ).error(function(r) {
                       content.sortable('cancel');
                       if (console) {
                           console.error(r);
                       }
                   });
               }
           }
       });
   },

   stocksAction : function() {

   },

   stocksBlur : function() {

   }
});
