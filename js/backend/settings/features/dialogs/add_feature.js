( function($) {

    var Dialog = ( function($) {

        function Dialog(options) {
            var that = this;

            // DOM
            that.$wrapper = options["$wrapper"];
            that.$form = that.$wrapper.find("form:first");

            // CONST
            that.scope = options["scope"];
            that.urls = options["urls"];
            that.dialog = options["dialog"];
            that.feature_id = options["feature_id"];
            that.kinds = options["kinds"];
            that.feature = options["feature"];
            that.formats = options["formats"];
            that.templates = options["templates"];
            that.show_skus_warning = options["show_skus_warning"];

            // DYNAMIC VARS
            that.active_kind = that.kinds[options["active_kind_id"]];
            that.active_format = that.formats[options["active_format_id"]];

            // INIT
            that.init();
        }

        Dialog.prototype.init = function() {
            var that = this;

            var active_class = "is-active";

            // visibility field
            var $visibility_field = that.$wrapper.find(".js-visibility-field");
            $visibility_field.on("change", function() {
                var $icon = $visibility_field.closest(".s-checkbox-wrapper").find(".js-icon"),
                    is_enabled = $(this).is(":checked");

                $icon
                    .removeClass(!is_enabled ? "fa-eye" : "fa-eye-slash text-light-gray")
                    .addClass(is_enabled ? "fa-eye" : "fa-eye-slash text-light-gray");
            });

            // available field
            var $available_field = that.$wrapper.find(".js-available-field");
            var skus_message_is_displayed = false;
            $available_field.on("change", function() {
                var $icon = $available_field.closest(".s-checkbox-wrapper").find(".js-icon"),
                    is_enabled = $(this).is(":checked");

                $icon
                    .removeClass(!is_enabled ? "text-gray" : "text-light-gray")
                    .addClass(is_enabled ? "text-gray" : "text-light-gray");

                if (that.show_skus_warning && !skus_message_is_displayed) {
                    const $field_section = $(this).closest('.s-field-section');
                    $field_section.append(that.templates["skus_warning_message"]);
                    $field_section.find('[data-wa-tooltip-template]').waTooltip();
                    skus_message_is_displayed = true;
                }
            });

            // type group field
            var $active_type_group = null;
            var $active_type_group_field = that.$wrapper.find(".js-type-group-field:checked");
            if ($active_type_group_field.length) {
                $active_type_group = $active_type_group_field.closest(".s-radio-wrapper");
            }
            that.$wrapper.on("change", ".js-type-group-field", function() {
                var $group = $(this).closest(".s-radio-wrapper");
                if ($active_type_group) {
                    $active_type_group.removeClass(active_class);
                }
                $active_type_group = $group.toggleClass(active_class);
                that.dialog.resize();
            });

            initCheckAllTypes();

            that.initTransliterate();
            that.initViewToggle();
            that.initSubmit();

            setTimeout( function() {
                that.$wrapper.find(".js-name-field").trigger("focus");
            }, 100);

            function initCheckAllTypes() {
                var $all_types_field = that.$wrapper.find(".js-all-types-field"),
                    $type_fields = that.$wrapper.find(".js-type-field");

                var checked_types = getCheckedTypesCount(),
                    types_count = $type_fields.length;

                $all_types_field.on("change", function() {
                    var $input = $(this),
                        is_active = $input.is(":checked");

                    checked_types = (is_active ? types_count : 0);
                    $type_fields.prop("checked", is_active);
                });

                that.$wrapper.on("change", ".js-type-field", function() {
                    var $input = $(this),
                        is_active = $input.is(":checked");

                    checked_types += (is_active ? 1 : -1);
                    $all_types_field.prop("checked", (checked_types >= types_count));
                });

                function getCheckedTypesCount() {
                    var result = 0;

                    $type_fields.each( function() {
                        var $input = $(this),
                            is_active = $input.is(":checked");
                        if (is_active) { result += 1; }
                    });

                    return result;
                }
            }

            that.$wrapper.find('[data-wa-tooltip-content]').waTooltip();
        };

        Dialog.prototype.initTransliterate = function() {
            var that = this;

            var $name_field = that.$wrapper.find(".js-name-field"),
                $code_field = that.$wrapper.find(".js-code-field"),
                $loading = null;

            var use_transliterate = !$.trim($code_field.val()).length,
                keyup_timer = 0,
                time = 500,
                xhr = null;

            $code_field.on("keyup", function() {
                var value = !!($(this).val().length);
                use_transliterate = !value;
            });

            $name_field.on("keyup", function() {
                if (use_transliterate) { onKeyUp(); }
            });

            function onKeyUp() {
                var name = $.trim($name_field.val());

                if (!$loading) {
                    $loading = $("<i class=\"icon16 loading\" />").insertAfter($code_field);
                }

                getCodeName(name)
                    .always( function() {
                        if ($loading) {
                            $loading.remove();
                            $loading = null;
                        }
                    })
                    .done( function(code_name) {
                        $code_field.val(code_name).trigger("change");
                    });

                function getCodeName(name) {
                    var deferred = $.Deferred();

                    clearTimeout(keyup_timer);

                    if (!name) {
                        deferred.resolve("");

                    } else {
                        keyup_timer = setTimeout( function() {
                            if (xhr) { xhr.abort(); }

                            $.post(that.urls["transliterate"], { str: name }, "json")
                                .always( function() {
                                    xhr = null;
                                })
                                .done( function(response) {
                                    var text = ( response.data ? response.data : "");
                                    deferred.resolve(text);
                                });
                        }, time);
                    }

                    return deferred.promise();
                }
            }
        };

        Dialog.prototype.initViewToggle = function() {
            var that = this;

            var $kind_section = that.$wrapper.find(".js-field-kind-section"),
                $kind_section_select = $kind_section.find('.js-select'),
                $format_section = that.$wrapper.find(".js-field-format-section"),
                $format_select = $format_section.find(".js-select"),
                $default_unit_section = that.$wrapper.find(".js-field-default-unit-section"),
                $default_unit_select = $default_unit_section.find(".js-select"),
                $value_section = that.$wrapper.find(".js-field-value-section"),
                $value_block = $value_section.find(".s-feature-values-section"),
                $value_list = $value_block.find(".s-feature-values-list");

            // Warning displayed below kind selector in place of values editor.
            var $kind_message = null;

            $kind_section.on("change", ".js-select", function() {
                var $select = $(this),
                    kind_id = $select.val(),
                    kind = that.kinds[kind_id];

                that.active_kind = kind;

                onViewChange(kind);

                if (that.feature_id) {
                    showMessage(that.templates["kind_warning_message"]);
                }
            });

            $format_select.on("change", function() {
                var format_id = $(this).val();
                that.active_format = that.formats[format_id];

                if (that.feature_id) {
                    showMessage(that.templates["kind_warning_message"]);
                    $value_section.find(".s-feature-value-wrapper").remove();
                    defaultValueSectionToggle(false);
                } else {
                    defaultValueSectionToggle(true);
                }

                if (that.active_format.values) {
                    valueSectionToggle(true);
                    var $feature_value = addFeatureValue();
                    $feature_value.find(".js-field-value").trigger("focus");
                } else {
                    valueSectionToggle(false);
                }
            });

            $default_unit_select.on("change", function() {
                that.feature.default_unit = $(this).val()
            });

            $value_block.on("click", ".js-feature-value-remove", function(event) {
                event.preventDefault();
                $(this).closest(".s-feature-value-wrapper").remove();
                that.dialog.resize();
            });

            $value_block.on("click", ".js-feature-value-add", function(event) {
                event.preventDefault();
                var $feature_value = addFeatureValue();
                $feature_value.find(".js-field-value").trigger("focus");
            });

            that.feature.selectable = +that.feature.selectable; // converts '0' to 0
            valueSectionToggle(!!that.feature.selectable);

            if (that.feature.selectable && that.feature.values && that.feature.values.length) {
                // reverse list of values because addFeatureValue() add them on top instead of on bottom
                $.each(that.feature.values.reverse(), function(i, value) {
                    var $feature_value = addFeatureValue(value);
                    // ID
                    if (value.id) { $feature_value.find(".js-field-id").val(value.id).trigger("change"); }
                    // VALUE
                    if (value.value) { $feature_value.find(".js-field-value").val(value.value).trigger("change"); }
                    // COLOR
                    if (value.code) { $feature_value.find(".js-field-code").val(value.code.toLowerCase()).trigger("change"); }
                    // UNIT
                    if (value.unit) { $feature_value.find(".js-field-unit").val(value.unit).trigger("change"); }
                });
            }

            initSortable();

            function defaultValueSectionToggle(show) {
                show = (typeof show === "boolean" ? show : !!that.active_kind.dimensions);

                var $section = $default_unit_section,
                    $select = $default_unit_select;

                $select.html("");

                if (show && that.active_kind.dimensions) {
                    var kind_dimensions = that.active_kind.dimensions;
                    if (($kind_section_select.val() == 'volume' && $format_select.val() == '3d') ||
                        ($kind_section_select.val() == 'area' && $format_select.val() == '2d')) {
                        kind_dimensions = that.kinds['length'].dimensions;
                    }
                    setSelectOptions($select, kind_dimensions, that.feature.default_unit);
                    $section.show();

                } else {
                    $section.hide();
                }

                /**
                 * @param {Object} $select
                 * @param {Object} dimensions
                 * @param {String?} active_dimension_id
                 * */
                function setSelectOptions($select, dimensions, active_dimension_id) {
                    active_dimension_id = ( typeof active_dimension_id === "string" ? active_dimension_id : null);

                    $.each(dimensions, function(i, dimension) {
                        var $option = $("<option />", {
                            value: dimension.id,
                            selected: !!(that.feature.default_unit && that.feature.default_unit === dimension.id)
                        }).text(dimension.title);

                        $select.append($option);
                    });
                }
            }

            function onViewChange(kind) {
                $format_select.html("");

                var formats = (kind.formats && kind.formats.length ? kind.formats : null );
                if (formats) {
                    $.each(kind.formats, function(i, format_id) {
                        var format = that.formats[format_id];
                        if (format) {
                            $("<option />").val(format.id).text(format.title).appendTo($format_select);
                        }
                    });

                    $format_section.show();
                    $format_select.trigger("change");

                } else {
                    $format_section.hide();
                    valueSectionToggle(false);
                }

                defaultValueSectionToggle(!!formats);
            }

            /**
             * @param {Boolean?} is_exist
             * */
            function addFeatureValue(is_exist) {
                if ($kind_message) {
                    // When warning message is shown instead of values editor,
                    // we don't want to add values to the list no matter what
                    // because empty `required` fields trigger validation error in chrome
                    // and make form unable to submit with error in console:
                    // An invalid form control with name='values[value][]' is not focusable.
                    return $([]);
                }

                var $new_item = $(that.templates["feature_value_item"]).prependTo($value_list),
                    $fields = $new_item.find(".s-fields");

                // COLOR
                if (that.active_kind.id === "color") {
                    var $color_widget = $(that.templates["color_widget"]);
                    $color_widget.appendTo($fields);
                    setTimeout(() => initColorPicker($color_widget))

                    // OR UNIT SELECT
                } else if (that.active_kind.dimensions) {
                    var $unit_select = getUnitSelect(that.active_kind.dimensions);
                    $fields.append($unit_select);

                } else {
                    $new_item.find(".js-field-value").addClass("wide");
                }

                that.dialog.resize();

                return $new_item;

                function getUnitSelect(dimensions) {
                    var $select = $(that.templates["unit_select"]);

                    $.each(dimensions, function(i, dimension) {
                        var $option = $("<option />", {
                            value: dimension.id,
                            selected: !!(that.feature.default_unit && that.feature.default_unit === dimension.id)
                        }).text(dimension.title);

                        $select.append($option)
                    });

                    return $('<div class="wa-select smaller">').append($select);
                }
            }
            function initColorPicker($wrapper) {
              const el = $wrapper.find('.js-color-picker')[0];
              const $colorPicker = $(el);
              const $codeColor = $colorPicker.next('.js-field-code');

              let initColor = $codeColor.val() || '#ffffff';
              if (initColor) {
                $colorPicker.css('color', initColor);
                $colorPicker.attr('data-color', initColor);
              }

              const getColorPicker = (pickr) => {
                if (pickr.hasOwnProperty('toHEXA')) {
                  return pickr.toHEXA().toString(0);
                }

                return pickr.target.value;
              }

              function setColor(color, notSave = false) {
                $colorPicker.css('color', color);
                $colorPicker.attr('data-color', color);
                if (!notSave) {
                  $codeColor.val(color);
                }
              }

              $colorPicker.on('click', (event) => {
                event.preventDefault();

                const defaultColor = $colorPicker.attr('data-color');

                const colorPicker = Pickr.create({
                  el,
                  theme: 'classic',
                  swatches: [
                    '#ed2509',
                    '#22d13d',
                    '#1a9afe',
                    '#f3c200',
                    '#ff6c00',
                    '#7256ee',
                    '#996e4d',
                    '#ff7a99',
                    '#fff',
                    '#000',
                    '#89a',
                    'rgba(0, 20, 65, 0.2)',
                    '#568',

                  ],
                  appClass: 'wa-pcr-app small',
                  lockOpacity: false,
                  position: 'bottom-middle',
                  useAsButton: true,
                  default: defaultColor,
                  components: {
                    palette: true,
                    hue: true,
                    interaction: {
                      input: true,
                      save: true
                    }
                  },
                  i18n: {
                    'btn:save': 'OK',
                  }
                }).on('change', (pickr) => {
                  const color_hex = getColorPicker(pickr);
                  setColor(color_hex, true)

                }).on('save', (color, pickr) => {
                  $codeColor.val(getColorPicker(color).toLowerCase());
                  pickr.hide();

                }).on('hide', (pickr) => {
                  let color_hex = $codeColor.val();
                  if (!color_hex) {
                    color_hex = initColor;
                  }
                  setColor(color_hex)
                  pickr.setColor(color_hex);
                  $codeColor.trigger('change');

                  pickr.destroyAndRemove();
                });

                colorPicker.show();
              });

              initTransliterate($wrapper.closest(".s-feature-value-wrapper"));

              /**
               * @param {Object} $wrapper
               * */
              function initTransliterate($wrapper) {
                  var that = this;

                  var $name_field = $wrapper.find('.js-field-value'),
                      $code_field = $wrapper.find('.js-field-code');

                  var keyup_timer = 0,
                      time = 500,
                      xhr = null,
                      timer = 0;

                  var active_class = "transliterate-enabled";

                  $name_field.on("keydown", function() {
                      clearTimeout(timer);
                      timer = setTimeout( function () {
                          onNameChange();
                      }, 500);

                      $name_field.removeClass(active_class);
                  });

                  $code_field.on("change", function() {
                      clearTimeout(timer);
                      timer = setTimeout( function () {
                          onColorChange();
                      }, 500);

                      $code_field.removeClass(active_class);
                  });

                  function onNameChange() {
                      var color_is_empty = !$code_field.val().length;
                      if (color_is_empty || $code_field.hasClass(active_class)) {
                          var name = $name_field.val();
                          if (name) {
                              getColorData(name, null).then( function(data) {
                                  color_is_empty = !$code_field.val().length;
                                  if (color_is_empty || $code_field.hasClass(active_class)) {
                                      if (data.color) {
                                          setColor(data.color);
                                          $code_field.addClass(active_class);
                                      }
                                  }
                              });
                          }
                      }
                  }

                  function onColorChange() {
                      var name_is_empty = !$name_field.val().length;
                      if (name_is_empty || $name_field.hasClass(active_class)) {
                          var color = $code_field.val();
                          if (color) {
                              getColorData(null, color).then( function(data) {
                                  var name_is_empty = !$name_field.val().length;
                                  if (name_is_empty || $name_field.hasClass(active_class)) {
                                      if (data.name) {
                                          $name_field.val(data.name).addClass(active_class);
                                      }
                                  }
                              });
                          }
                      }
                  }

                  /**
                   * @param {String?} name
                   * @param {String?} color
                   * */
                  function getColorData(name, color) {
                      var href = "?module=settingsTypefeat&action=featuresHelper",
                          data = {};

                      if (color) { data.code = color2magic(color); }
                      if (name) { data.name = name; }

                      var deferred = $.Deferred();

                      if (xhr) { xhr.abort(); }

                      xhr = $.get(href, data, "json")
                          .always( function() {
                              xhr = null;
                          })
                          .done( function(response) {
                              if (response.status === "ok") {
                                  deferred.resolve({
                                      name: response.data.name,
                                      color: magic2color(response.data.code)
                                  });
                              } else {
                                  deferred.reject();
                              }
                          })
                          .fail( function() {
                              deferred.reject();
                          });

                      return deferred.promise();

                      function color2magic(color) {
                          return 0xFFFFFF & parseInt(('' + color + '000000').replace(/[^0-9A-F]+/gi, '').substr(0, 6), 16);
                      }

                      function magic2color(magic) {
                          return (0xF000000 | magic).toString(16).toLowerCase().replace(/^f/, "#");
                      }

                  }
              }
            }

            function initSortable() {
                $value_list.sortable({
                    distance: 5,
                    opacity: 0.75,
                    containment: "parent",
                    items: "> .s-feature-value-wrapper",
                    handle: ".js-feature-value-sort",
                    cursor: "move",
                    tolerance: "pointer"
                });
            }

            function showMessage(html, $append_wrapper) {
                $append_wrapper = ($append_wrapper ? $append_wrapper : $format_section);

                if ($kind_message) {
                    $kind_message.remove();
                    $kind_message = null;
                }

                if (html) {
                    $kind_message = $(html).appendTo($append_wrapper);
                    $kind_message.find('[data-wa-tooltip-content]').waTooltip();
                    valueSectionToggle(false);
                }
            }

            /**
             * @param {Boolean} show
             * */
            function valueSectionToggle(show) {
                $value_list.html("");
                if (show && !$kind_message) {
                    $value_section.show();
                } else {
                    $value_section.hide();
                }
                that.dialog.resize();
            }
        };

        Dialog.prototype.initSubmit = function() {
            var that = this,
                $form = that.$form,
                $errorsPlace = that.$wrapper.find(".js-errors-place"),
                is_locked = false;

            var $submit_button = that.$wrapper.find(".js-submit-button");

            that.$wrapper.one("change", function() {
                $submit_button.removeClass("green").addClass("yellow");
            });

            $form.on("submit", onSubmit);
            function onSubmit(event) {
                event.preventDefault();

                var formData = getData();

                if (formData.errors.length) {
                    renderErrors(formData.errors);
                } else {
                    request(formData.data);
                }
            }

            function getData() {
                var result = {
                        data: [],
                        errors: []
                    },
                    data = $form.serializeArray();

                $form.find('input[data-unchecked]:not(:checked)').each(function() {
                    data.push({ name: $(this).attr('name'), value: "" });
                });

                $.each(data, function(index, item) {
                    result.data.push(item);
                });

                return result;
            }

            function renderErrors(errors) {
                var result = [];

                $errorsPlace.html("");

                if (!errors || !errors[0]) { errors = []; }

                $.each(errors, function(i, error) {
                    if (!error.text) { alert("error"); return true; }

                    if (error.id) {
                        switch (error.id) {
                            case "kind_value_error":
                                var $value_fields = that.$wrapper.find(".js-field-value-section .js-field-value"),
                                    field = $value_fields[error.data.index];

                                if (field) {
                                    error.$field = $(field);
                                    renderError(error, error.$field.closest(".s-fields").parent());
                                } else {
                                    renderError(error);
                                }
                                break;

                            case "some_error":
                                renderError(error);
                                break;

                            default:
                                renderError(error);
                                break;
                        }

                    } else if (error.name) {
                        error.$field = that.$wrapper.find("[name=\"" + error.name + "\"]").first();
                        renderError(error);
                    }

                    result.push(error);
                });

                that.dialog.resize();

                return result;

                function renderError(error, $error_wrapper) {
                    var $error = $("<div class=\"s-error errormsg\" />").text(error.text);
                    var error_class = "error";

                    if (error.$field) {
                        var $field = error.$field;

                        if (!$field.hasClass(error_class)) {
                            $field.addClass(error_class);

                            if ($error_wrapper) {
                                $error_wrapper.append($error);
                            } else {
                                $error.insertAfter($field);
                            }

                            $field
                                .trigger("error")
                                .on("change keyup", removeFieldError);
                        }
                    } else {
                        $errorsPlace.append($error);
                    }

                    function removeFieldError() {
                        $error.remove();
                        $field
                            .removeClass(error_class)
                            .off("change keyup", removeFieldError);
                        that.dialog.resize();
                    }
                }

            }

            function request(data) {
                if (!is_locked) {
                    is_locked = true;
                    var animation = animateUI();
                    animation.lock();

                    $.post(that.urls["feature_save"], data, "json")
                        .always( function() {
                            animation.unlock();
                            is_locked = false;
                        })
                        .done( function(response) {
                            if (response.status === "ok") {
                                if (that.dialog.options && (typeof that.dialog.options.onSuccess === "function")) {
                                    that.dialog.options.onSuccess(response.data, {
                                        dialog: that.dialog,
                                        animation: animation
                                    });
                                } else {
                                    dialog.close();
                                }
                            } else {
                                renderErrors(response.errors);
                            }
                        });
                }

                function animateUI() {
                    var $loading = $('<span class="icon custom-ml-4 loading"><i class="fas fa-spinner wa-animation-spin"></span>'),
                        locked_class = "is-locked",
                        is_displayed = false;

                    return {
                        lock: lock,
                        unlock: unlock
                    };

                    function lock() {
                        that.$wrapper.addClass(locked_class);
                        $submit_button.attr("disabled", true);
                        if (!is_displayed) {
                            $loading.appendTo($submit_button);
                            is_displayed = true;
                        }
                    }

                    function unlock() {
                        that.$wrapper.removeClass(locked_class);
                        $submit_button.attr("disabled", false);
                        if (is_displayed) {
                            $loading.detach();
                            is_displayed = false;
                        }
                    }
                }
            }
        };

        return Dialog;

    })($);

    if (!$.wa) { $.wa = {}; }
    if (!$.wa.new) { $.wa.new = {}; }

    $.wa.new.FeatureDialog = function(options) {
        return new Dialog(options);
    };

})(jQuery);
