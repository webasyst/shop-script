ShopSettingsGeneral = (function ($) {
  return class {
    constructor($wrapper, options) {
      // DOM
      this.$wrapper = $wrapper;
      this.$form = $wrapper;

      this.$button = this.$wrapper.find('.js-form-submit');
      this.$loading = this.$wrapper.find('.bottombar .js-loading');

      // VARS
      this.options = options;
      this.is_locked = false;
      // this.oldData = null;
      // this.changeData = null;

      this.initClass();
    };

    initClass() {
      // this.oldData = this.$form.serialize();
      this.initComponents();
      this.initServices();
      this.initOn();
      this.initSubmit();
      this.formChanged(false);
    }

    initOn() {
      const that = this;

      $(':input').on('input', function () {
        that.formChanged();
      });
      this.$form.on('change', function () {
        // that.changeData = $(this).serialize();
        // if (that.changeData == that.oldData) {
        //   that.formChanged(false);
        // } else {
        //   that.formChanged();
        // }
        that.formChanged();
      });

      $('.s-captcha input:radio').change(function () {
        if ($(this).is(":checked")) {
          $('.s-captcha label > div').hide().find('input').attr('disabled');
          $(this).parent().children('div').show().find('input').removeAttr('disabled');
          $(this).parent().find('div input:first').focus();
        }
      });
      $('.s-captcha input:radio:checked').change();

      $(':input[name="map"]').change(function () {
        var scope = $(this).parents('div.field');
        scope.find('div.js-map-adapter-settings').hide();
        if (this.checked) {
          scope.find('div.js-map-adapter-settings[data-adapter-id="' + this.value + '"]').show();
        }
      });

      $(':input[name="map"]:checked').change();


      $('.js-setting-require-auth').on('change', function () {
        if ($(this).is(':checked')) {
          $('#setting-require-captcha').slideUp(200);
        } else {
          $('#setting-require-captcha').slideDown(200);
        }
      }).change();

      $('input[name="workhours_type"]').change(function () {
        if ($(this).val() == '1') {
          $('#workhours-div').show();
        } else {
          $('#workhours-div').hide();
        }
      });
    }

    initComponents() {
      $('.js-toggle-backend-validation-status').waSwitch({
        change: function (active, wa_switch) {
          wa_switch.$wrapper.siblings('.description').toggle();
        }
      });

      $('.js-toggle-checkout-antispam-status').waSwitch({
        change: function (active, wa_switch) {
          wa_switch.$wrapper.siblings('.description').toggle();
        }
      });
    }

    initServices() {
      //
      // Service agreement settings wrapper
      //
      (function () {
        const $wrapper = $('#service-agreement-settings-wrapper');
        const $textarea = $wrapper.find('textarea');
        const $textEditor = $wrapper.find('.js-text-editor');
        const $textareaCheckbox = $wrapper.find('.js-textarea-checkbox');
        let previous_default_text = null;

        $wrapper.on('change', ':radio', function () {
          if (!$textarea.val() || previous_default_text === $textarea.val()) {
            setDefaultText();
          }

          switch (this.value) {
            case 'notice':
              $textareaCheckbox.hide();
              $textEditor.show();
              break;
            case 'checkbox':
              $textareaCheckbox.show()
              $textEditor.show();
              break;
            default:
              $textEditor.hide();
              break;
          }
        }).find(':radio:checked').change();

        $wrapper.on('click', '.generalte-example-link', function (e) {
          e.preventDefault();
          setDefaultText();
          $textarea.focus();
        });

        function setDefaultText() {
          previous_default_text = $wrapper.find(':radio:checked').closest('label').data('default-text') || '';
          $textarea.val(previous_default_text);
        }
      }());
    }

    btnSubmit() {
      const btn = $('.js-form-submit');

      return {
        send: () => {
          btn.attr('disabled', true);
          btn.after('<div class="spinner">');
        },
        done: () => {
          btn.attr('disabled', false);
          btn.siblings('.spinner').remove();
        },
      }
    }

    initSubmit() {

      const that = this;

      that.$form.on('submit', function () {
        that.btnSubmit().send();

        const self = $(this);

        that.$form.find(':submit').after('<span class="s-msg-after-button"><i class="icon16 loading"></i></span>');

        $.post(self.attr('action'), self.serialize(), (r) => {
          that.btnSubmit().done();
          that.$wrapper.replaceWith(r);
        });

        return false;
      });
    }

    formChanged(isChange = true) {
      const default_class = "green";
      const active_class = "yellow";

      if (isChange) {
        this.$button.removeClass(default_class).addClass(active_class);
      } else {
        this.$button.removeClass(active_class).addClass(default_class);
      }
    }
  }
})(jQuery);
