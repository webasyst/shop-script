/**
 * {literal}
 */
if (!lang_strings) {
    var lang_strings = {
        'edit_link': 'edit link',
        'field_title': 'DoubleClick to edit',
        'save_link': 'save'
    }
}

window.Printform = {
    edit: function (node) {
        if (node.edited != 'edited') {
            node.edited = 'edited';
            var value = node.innerHTML.replace(/&nbsp;/g, ' ').replace(/\s+/g, ' ');
            var clean_value = value.replace(/&lt;/g, '<').replace(/&gt;/g, '>');
            node.innerHTML = '';
            var inputTag = window.document.createElement('input');
            inputTag.type = "text";
            inputTag.value = clean_value;
            inputTag.className = 'text';
            inputTag.defaultValue = clean_value;
            inputTag.size = 2 + clean_value.length;
            node.appendChild(inputTag);

            saveTag = window.document.createElement('input');
            saveTag.type = "button";
            saveTag.value = lang_strings['save_link'];
            saveTag.onclick = function () {
                Printform.save(node);
                return false;
            };
            node.appendChild(saveTag);

            printTag = window.document.createElement('span');
            printTag.className = "printable";
            printTag.innerHTML = value;

            node.appendChild(printTag);

            inputTag.focus();
        }
    },
    editAll: function (class_name) {
        var els = document.getElementsByTagName('*');
        var elsLen = els.length;
        var pattern = new RegExp("(^|\\s)" + class_name + "(\\s|$)");
        for (var i = 0; els[i]; i++) {
            if (pattern.test(els[i].className)) {
                this.edit(els[i]);
            }
        }
    },
    save: function (node) {
        if (node.edited == 'edited') {
            node.edited = '';
            node.innerHTML = node.firstChild.value.replace(/\</g, '&lt;').replace(/\>/g, '&gt;').replace(/\s+/g, '&nbsp;');
            node.style.backgroundColor = '';

            if (jQuery) { jQuery(node).trigger("edited", node); }
        }

    },
    cancel: function (node) {
        if (node.edited == 'edited') {
            node.edited = '';
            node.innerHTML = node.firstChild.defaultValue.replace(/\</g, '&lt;').replace(/\>/g, '&gt;').replace(/\s+/g, '&nbsp;');
            node.style.backgroundColor = '';
        }

    },
    saveAll: function (class_name) {
        var els = document.getElementsByTagName('*');
        var elsLen = els.length;
        var pattern = new RegExp("(^|\\s)" + class_name + "(\\s|$)");
        for (var i = 0; i < elsLen; i++) {
            if (els[i] && els[i].className && pattern.test(els[i].className)) {
                this.save(els[i]);
            }
        }
    },
    init: function (class_name) {
        var els = document.getElementsByTagName('*');
        var elsLen = els.length;
        var pattern = new RegExp("(^|\\s)" + class_name + "(\\s|$)");
        var founded = 0;
        for (var i = 0; i < elsLen; i++) {
            if (pattern.test(els[i].className)) {
                founded++;
                els[i].ondblclick = function () {
                    this.style.backgroundColor = '';
                    Printform.edit(this)
                };
                els[i].onmouseover = function () {
                    this.style.backgroundColor = '#FFC';
                };
                els[i].onmouseout = function () {
                    this.style.backgroundColor = '';
                };
                els[i].title = lang_strings['field_title'];
                els[i].onkeydown = function (e) {
                    var code = null;
                    try {
                        if (window.event) {
                            code = window.event.keyCode;
                        } else if (e.which) {
                            code = e.which;
                        } else if (e.keyCode) {
                            code = e.keyCode;
                        }

                    } catch (e) {
                        alert(e.message);
                    }
                    if (code == 13) {
                        Printform.save(this);
                    } else if (code == 27) {
                        Printform.cancel(this);
                    }
                    try {
                        var input = this.firstChild;
                        var length = input.value.length;
                        if (length < 5) {
                            input.size = 7;
                        } else if (length < 80) {
                            input.size = 2 + length;
                        } else {
                            input.size = 82;
                        }
                    } catch (e) {
                    }

                }
            }
        }
        if (founded) {
            /*
             * saveTag = window.document.createElement('input'); saveTag.value = "Save"; saveTag.type = "button"; saveTag.id = 'save_button';
             * saveTag.style.display = 'inline'; saveTag.style.margin = '60px 3px'; saveTag.disabled = true; saveTag.onclick = function() {
             * Printform.saveAll(class_name); }; window.document.body.appendChild(saveTag);
             */

            var printButton = window.document.getElementById('print_button');
            if (printButton) {
                var old_function = printButton.onclick;
                printButton.onclick = function () {
                    Printform.saveAll(class_name);
                    old_function();
                };
                linkTag = window.document.createElement('a');
                linkTag.href = page_url + '#edit';
                linkTag.innerHTML = lang_strings['edit_link'];
                linkTag.style.display = 'inline';
                linkTag.style.margin = '60px 3px';
                linkTag.disabled = true;
                linkTag.onclick = function () {
                    Printform.editAll(class_name);
                    return false;
                };

                printButton.parentNode.appendChild(linkTag);
            }

            window.document.body.onbeforeprint = function () {
                Printform.saveAll(class_name);
            }
        }
    },

    /**
     * @description convert price string to number
     * @param {String} string
     * @return {Null|Number}
     * */
    parseString: function(string) {
        string = string.replace(/\s/g, "").replace(/,/g,".");

        var result = parseFloat(string);

        return ( Math.abs(result) >= 0 ? result : null );
    },

    /**
     * @description based on russian locale
     * @param {Number} price
     * @param {Boolean?} text
     * @return {Null|String}
     * */
    formatPrice: function (price, text) {
        var result = null,
            format = {
                "fraction_divider":",",
                "fraction_size": 2,
                "group_divider":" ",
                "group_size": 3,
                "pattern_html": "%s",
                "pattern_text": "%s"
            };

        try {
            price = parseFloat(price).toFixed(format.fraction_size);

            if ( (price >= 0) && format) {
                var price_floor = Math.floor(price),
                    price_string = getGroupedString("" + price_floor, format.group_size, format.group_divider),
                    fraction_string = getFractionString(price - price_floor);

                result = ( text ? format.pattern_text : format.pattern_html ).replace("%s", price_string + fraction_string );
            }

        } catch(e) {
            if (console && console.log) {
                console.log(e.message, price);
            }
        }

        return result;

        function getGroupedString(string, size, divider) {
            var result = "";

            if (!(size && string && divider)) {
                return string;
            }

            var string_array = string.split("").reverse();

            var groups = [];
            var group = [];

            for (var i = 0; i < string_array.length; i++) {
                var letter = string_array[i],
                    is_first = (i === 0),
                    is_last = (i === string_array.length - 1),
                    delta = (i % size);

                if (delta === 0 && !is_first) {
                    groups.unshift(group);
                    group = [];
                }

                group.unshift(letter);

                if (is_last) {
                    groups.unshift(group);
                }
            }

            for (i = 0; i < groups.length; i++) {
                var is_last_group = (i === groups.length - 1),
                    _group = groups[i].join("");

                result += _group + ( is_last_group ? "" : divider );
            }

            return result;
        }

        function getFractionString(number) {
            var result = "";

            if (number >= 0) {
                number = number.toFixed(format.fraction_size + 1);
                var power = Math.pow(10, format.fraction_size);
                number = Math.round(number * power) / power;
                var string = number.toFixed(format.fraction_size);
                result = string.replace("0.", format.fraction_divider);
            }

            return result;
        }
    }
};

/**
 * {/literal}
 */