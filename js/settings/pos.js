/**
 * {literal}
 *
 * @names Point Of Sales*
 * @method posAction
 */
if (typeof ($) != 'undefined') {

    $.extend($.settings = $.settings || {}, {
        posInit: function () {
            this.$pos_plugins_container = $('#s-settings-pos-plugins');
        },

        /**
         *
         * @param {String} tail
         */
        posAction: function (tail) {
            const method = $.shop.getMethod(tail.split('/'), this, 'pos');

            $.shop.trace('$.settings.posAction', [method, this.path, tail]);

            if (method.name) {
                this[method.name].apply(this, method.params);
            } else {
                if (this.$pos_plugins_container.is(':empty')) {
                    this.loadInstallerPlugins();
                }
            }
        },

        loadInstallerPlugins: function () {
            if ($.settings.options.hasOwnProperty('installer_access') && !$.settings.options.installer_access) {
                return;
            }
            const url = this.options.backend_url + 'installer/?module=store&action=inApp&filter[tag]=fz54&filter[type]=plugin';
            $.get(url, (html) => {
                this.$pos_plugins_container.show().html(html);
                const $iframe = $('iframe.js-store-frame');
                const changeIframeTheme = () => {
                    const message = JSON.stringify({ theme: (document.documentElement.dataset.theme || 'light') });
                    $iframe[0].contentWindow.postMessage(message, '*');
                }

                const observer = new MutationObserver((mutationList) => {
                    for (const mutation of mutationList) {
                        if (mutation.type === "attributes" && mutation.attributeName === 'data-theme') {
                            changeIframeTheme();
                            break;
                        }
                    }
                });

                const prev_title = document.title;
                $iframe.on('load', () => {
                    document.title = prev_title;
                    changeIframeTheme();
                    observer.observe(document.documentElement, { attributes: true });
                })

                $.settings.$container.find('> :first').one('remove', function () {
                    observer.disconnect();
                })
            });
        }

    });
}
