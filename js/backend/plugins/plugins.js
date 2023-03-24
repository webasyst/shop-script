/**
 * @used at wa-apps/shop/templates/actions/plugins/PluginsList.html
 * Controller for Plugins List Page.
 **/
 export class ShopPluginsListPage {
    constructor(options) {
        // DOM
        this.$wrapper = options["$wrapper"];
        // CONST
        this.mainContent = $('.js-main-content');

        this.plugin = null;
        // INIT
        this.init();
    }

    init() {
        const that = this;
        window.onhashchange = locationHashChanged;

        $(document).on('wa_loaded', () => {
            const $menu = $('#wa-plugin-list');

            const plugin_id = $menu.find('li.selected').attr('id');
            if (plugin_id) {
                this.plugin = plugin_id.replace('plugin-','')
            }
        });

        locationHashChanged();

        function locationHashChanged() {
            const path = that.parsePath(location.hash);

            if (location.hash !== '' && path.plugin !== that.plugin) {
                that.installedAction();
            }else {
                that.pageReady();
            }
        }
    }

    pageReady() {
        this.$wrapper
            .css("visibility", "")
            .data("controller", this);
    }

    installedAction() {
        this.mainContent.load('?module=plugins&action=installed');
    }

    parsePath(path) {
        path = path.replace(new RegExp('^.*#/'), '');

        const splited_array = path.split("/"),
            tail = (splited_array.length > 1) ? splited_array[1] : null;

        return {
            plugin: path.replace(/\/.*$/, '') || null,
            tail: tail,
            raw: path
        };
    }
}
