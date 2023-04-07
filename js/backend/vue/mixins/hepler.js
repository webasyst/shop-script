// simple: mixins: [HeplerVue]
const HeplerVue = {
    methods: {
        htmlToText(html) {
            if (typeof html !== "string") {
                return '';
            }

            return html.replace(/</g, '&lt;').replace(/>/g, '&gt;');
        },
        clone(obj) {
            return JSON.parse(JSON.stringify(obj));
        },
    }
}
