/**
 * Shop Sources
 */

var gulp = require('gulp'),
    //css
    stylus = require('gulp-stylus'),
    nib = require('nib'),
    //js
    uglify = require('gulp-uglify'),
    concat = require('gulp-concat'),
    sourcemaps = require('gulp-sourcemaps');

var app_id = "shop",
    task_name = app_id + "-sources";

var app_dir = './wa-apps/shop/';

var css_sources = {
    "front-checkout-cart": {
        "directory": "./wa-apps/shop/css/frontend/order/",
        "sources": "./wa-apps/shop/css/frontend/order/cart/*",
        "result": "./wa-apps/shop/css/frontend/order/cart/cart.styl"
    },
    "front-checkout-cross_selling": {
        "directory": "./wa-apps/shop/css/frontend/order/",
        "sources": "./wa-apps/shop/css/frontend/order/cross_selling/*",
        "result": "./wa-apps/shop/css/frontend/order/cross_selling/cross_selling.styl"
    },
    "front-checkout-form": {
        "directory": "./wa-apps/shop/css/frontend/order/",
        "sources": "./wa-apps/shop/css/frontend/order/form/*",
        "result": "./wa-apps/shop/css/frontend/order/form/form.styl"
    },
    "front-checkout-ui": {
        "directory": "./wa-apps/shop/css/frontend/order/",
        "sources": "./wa-apps/shop/css/frontend/order/ui/*",
        "result": "./wa-apps/shop/css/frontend/order/ui/ui.styl"
    },
    "front-checkout-layout": {
        "directory": "./wa-apps/shop/css/frontend/order/",
        "sources": "./wa-apps/shop/css/frontend/order/layout/*",
        "result": "./wa-apps/shop/css/frontend/order/layout/layout.styl"
    },
    "backend-orders-refund": {
        "directory": "./wa-apps/shop/css/backend/orders/",
        "sources": "./wa-apps/shop/css/backend/orders/refund/*",
        "result": "./wa-apps/shop/css/backend/orders/refund/refund.styl"
    },
    "backend-orders-capture": {
        "directory": "./wa-apps/shop/css/backend/orders/",
        "sources": "./wa-apps/shop/css/backend/orders/capture/*",
        "result": "./wa-apps/shop/css/backend/orders/capture/capture.styl"
    },
    "backend-orders-kanban": {
        "directory": "./wa-apps/shop/css/backend/orders/",
        "sources": "./wa-apps/shop/css/backend/orders/kanban/*",
        "result": "./wa-apps/shop/css/backend/orders/kanban/kanban.styl"
    },
    "backend-orders-order": {
        "directory": "./wa-apps/shop/css/backend/orders/",
        "sources": "./wa-apps/shop/css/backend/orders/order/*",
        "result": "./wa-apps/shop/css/backend/orders/order/order.styl"
    },
    "backend-orders-orders": {
        "directory": "./wa-apps/shop/css/backend/orders/",
        "sources": "./wa-apps/shop/css/backend/orders/*",
        "result": "./wa-apps/shop/css/backend/orders/orders.styl"
    },
    "backend-customers": {
        "directory": "./wa-apps/shop/css/backend/",
        "sources": "./wa-apps/shop/css/backend/customers/*",
        "result": "./wa-apps/shop/css/backend/customers/customers.styl"
    },
    "backend-tutorial": {
        "directory": "./wa-apps/shop/css/backend/",
        "sources": "./wa-apps/shop/css/backend/tutorial/*",
        "result": "./wa-apps/shop/css/backend/tutorial/tutorial.styl"
    },
    "backend-marketing": {
        "directory": "./wa-apps/shop/css/backend/",
        "sources": "./wa-apps/shop/css/backend/marketing/*",
        "result": "./wa-apps/shop/css/backend/marketing/marketing.styl"
    },
    "backend-settings-features": {
        "directory": "./wa-apps/shop/css/backend/settings/",
        "sources": "./wa-apps/shop/css/backend/settings/features/*",
        "result": "./wa-apps/shop/css/backend/settings/features/features.styl"
    },
    "backend-settings-units": {
        "directory": "./wa-apps/shop/css/backend/settings/",
        "sources": "./wa-apps/shop/css/backend/settings/units/*",
        "result": "./wa-apps/shop/css/backend/settings/units/units.styl"
    },
    "backend-settings-compatibility": {
        "directory": "./wa-apps/shop/css/backend/settings/",
        "sources": "./wa-apps/shop/css/backend/settings/compatibility/*",
        "result": "./wa-apps/shop/css/backend/settings/compatibility/compatibility.styl"
    },
    "backend-settings-sections": {
        "directory": "./wa-apps/shop/css/backend/settings/",
        "sources": "./wa-apps/shop/css/backend/settings/sections/*",
        "result": "./wa-apps/shop/css/backend/settings/sections/*.styl"
    },
    "backend-settings-marketplaces": {
        "directory": "./wa-apps/shop/css/backend/settings/",
        "sources": "./wa-apps/shop/css/backend/settings/marketplaces/*",
        "result": "./wa-apps/shop/css/backend/settings/marketplaces/marketplaces.styl"
    },
    "backend-settings-premium": {
        "directory": "./wa-apps/shop/css/backend/settings/",
        "sources": "./wa-apps/shop/css/backend/settings/premium/*",
        "result": "./wa-apps/shop/css/backend/settings/premium/premium.styl"
    },
    "backend-settings-features-dialogs": {
        "directory": "./wa-apps/shop/css/backend/settings/features/dialogs/",
        "sources": "./wa-apps/shop/css/backend/settings/features/dialogs/*.styl",
        "result": "./wa-apps/shop/css/backend/settings/features/dialogs/*.styl"
    },
    "backend-settings-plugins": {
        "directory": "./wa-apps/shop/css/backend/settings/",
        "sources": "./wa-apps/shop/css/backend/settings/plugins/*",
        "result": "./wa-apps/shop/css/backend/settings/plugins/plugins.styl"
    },
    "backend-products-reviews": {
        "directory": "./wa-apps/shop/css/backend/products/reviews/",
        "sources": "./wa-apps/shop/css/backend/products/reviews/*.styl",
        "result": "./wa-apps/shop/css/backend/products/reviews/reviews.styl"
    },
    "backend-products-wa2": {
        "directory": "./wa-apps/shop/css/backend/products/wa2/",
        "sources": "./wa-apps/shop/css/backend/products/wa2/*.styl",
        "result": "./wa-apps/shop/css/backend/products/wa2/wa2.styl"
    },
    "backend-products-ui": {
        "directory": "./wa-apps/shop/css/backend/products/ui/",
        "sources": "./wa-apps/shop/css/backend/products/ui/*.styl",
        "result": "./wa-apps/shop/css/backend/products/ui/ui.styl"
    },
    "backend-products-main": {
        "directory": "./wa-apps/shop/css/backend/products/main/",
        "sources": "./wa-apps/shop/css/backend/products/main/*.styl",
        "result": "./wa-apps/shop/css/backend/products/main/main.styl"
    },
    "backend-products-product": {
        "directory": "./wa-apps/shop/css/backend/products/product/",
        "sources": "./wa-apps/shop/css/backend/products/product/*.styl",
        "result": "./wa-apps/shop/css/backend/products/product/product.styl"
    },
    "backend-wa2-menu": {
        "directory": "./wa-apps/shop/css/backend/",
        "sources": "./wa-apps/shop/css/backend/wa2_main_menu/*.styl",
        "result": "./wa-apps/shop/css/backend/wa2_main_menu/wa2_main_menu.styl",
    },
    "sidebar-menu": {
        "directory": "./wa-apps/shop/css/backend/",
        "sources": "./wa-apps/shop/css/backend/sidebar_menu/*.styl",
        "result": "./wa-apps/shop/css/backend/sidebar_menu/sidebar_menu.styl",
    },
    "backend-plugins-list": {
        "directory": "./wa-apps/shop/css/backend/plugins/",
        "sources": "./wa-apps/shop/css/backend/plugins/*",
        "result": "./wa-apps/shop/css/backend/plugins/plugins.styl"
    },
    "backend-themes-list": {
        "directory": "./wa-apps/shop/css/backend/themes/",
        "sources": "./wa-apps/shop/css/backend/themes/*",
        "result": "./wa-apps/shop/css/backend/themes/themes.styl"
    },

    // PLUGINS
    "plugin-yandexmarket": {
        "directory": "./wa-apps/shop/plugins/yandexmarket/css/",
        "sources": "./wa-apps/shop/plugins/yandexmarket/css/backend/*",
        "result": "./wa-apps/shop/plugins/yandexmarket/css/backend/yandexmarket.styl"
    },

    // d3 styles
    "styl-charts": {
        "directory": "./wa-apps/shop/css/",
        "sources": "./wa-apps/shop/css/styl/*",
        "result": "./wa-apps/shop/css/styl/charts.styl"
    }
};

var css_widgets = {
    "widget-sales": {
        "compress": false,
        "directory": app_dir+"./widgets/sales/css/",
        "sources": app_dir+"./widgets/sales/css/styl/*.styl",
        "result": app_dir+"./widgets/sales/css/styl/salesWidget.styl"
    },
    "widget-customers": {
        "compress": false,
        "directory": app_dir+"./widgets/customers/css/",
        "sources": app_dir+"./widgets/customers/css/styl/*.styl",
        "result": app_dir+"./widgets/customers/css/styl/oneline.styl"
    },
    "widget-orders": {
        "compress": false,
        "directory": app_dir+"./widgets/orders/css/",
        "sources": app_dir+"./widgets/orders/css/styl/*.styl",
        "result": app_dir+"./widgets/orders/css/styl/orders.styl"
    },
    "widget-reviews": {
        "compress": false,
        "directory": app_dir+"./widgets/reviews/css/",
        "sources": app_dir+"./widgets/reviews/css/styl/*.styl",
        "result": app_dir+"./widgets/reviews/css/styl/reviews.styl"
    },
};

var js_sources = {
    // "front-checkout-cart": {
    //     "directory": "./wa-apps/shop/js/frontend/order/",
    //     "sources": "./wa-apps/shop/js/frontend/order/cart.js",
    //     "result_name": "cart.min.js"
    // },
    // "front-checkout-product": {
    //     "directory": "./wa-apps/shop/js/frontend/order/",
    //     "sources": "./wa-apps/shop/js/frontend/order/product.js",
    //     "result_name": "product.min.js"
    // },
    // "front-checkout-cross_selling": {
    //     "directory": "./wa-apps/shop/js/frontend/order/",
    //     "sources": "./wa-apps/shop/js/frontend/order/cross_selling.js",
    //     "result_name": "cross_selling.min.js"
    // },
    // "front-checkout-form": {
    //     "directory": "./wa-apps/shop/js/frontend/order/",
    //     "sources": "./wa-apps/shop/js/frontend/order/form.js",
    //     "result_name": "form.min.js"
    // },
    // "front-checkout-ui": {
    //     "directory": "./wa-apps/shop/js/frontend/order/",
    //     "sources": "./wa-apps/shop/js/frontend/order/ui.js",
    //     "result_name": "ui.min.js"
    // },
    "front-product_all-ui": {
        "directory": "./wa-apps/shop/js/product/",
        "sources": "./wa-apps/shop/js/product/product_all.js",
        "result_name": "product.min.js"
    }
};

css_sources = Object.assign(css_sources, css_widgets);

// build task 'shop-jquery.min.js'
var plugins_dir = './wa-content/js/jquery-plugins/';
gulp.task(app_id + '-jquery.js', function() {
  gulp.src([
        plugins_dir + 'jquery.history.js',
        plugins_dir + 'jquery.store.js',
        './wa-apps/shop/js/jquery-ui/js/jquery-ui-1.9.2.custom.min.js',
        plugins_dir + 'jquery.tmpl.min.js',
        plugins_dir + 'jquery.retina.js',
        plugins_dir + 'jquery.swap.js',
        plugins_dir + 'jquery-plot/plugins/jqplot.highlighter.min.js',
        plugins_dir + 'jquery-plot/plugins/jqplot.cursor.min.js',
        plugins_dir + 'jquery-plot/plugins/jqplot.dateAxisRenderer.min.js',
        plugins_dir + 'jquery-plot/plugins/jqplot.pieRenderer.min.js'
    ])
    .pipe(sourcemaps.init())
    .pipe(concat('shop-jquery.min.js', {newLine: ';'}))
    .pipe(uglify())
    .pipe(sourcemaps.write("./", {
        includeContent: false
    }))
    .pipe(gulp.dest('./wa-apps/shop/js/'))
});

gulp.task(task_name, function () {
    // CSS
    for (var css_source_id in css_sources) {
        if (css_sources.hasOwnProperty(css_source_id)) {
            var css_source = css_sources[css_source_id];

            setCSSWatcher({
                name: app_id + "-" + css_source_id + "-css",
                target: css_source.result,
                sources: css_source.sources,
                compress: (typeof css_source.compress === "boolean" ? css_source.compress : true),
                directory: css_source.directory
            });
        }
    }

    function setCSSWatcher(options) {
        gulp.watch(options.sources, [options.name]);
        gulp.task(options.name, function() {
            //process.stdout.write(source_file);
            gulp.src(options.target)
                .pipe(stylus({
                    use: nib(),
                    compress: options.compress
                }))
                .pipe(gulp.dest(options.directory));
        });
    }

    // JS
    for (var js_source_id in js_sources) {
        if (js_sources.hasOwnProperty(js_source_id)) {
            var js_source = js_sources[js_source_id];
            setJSWatcher(js_source.directory, js_source.sources, js_source.result_name, app_id + "-" + js_source_id + "-js");
        }
    }

    function setJSWatcher(directory, sources, result_name, task_name) {
        gulp.watch(sources, [task_name]);
        gulp.task(task_name, function() {
            gulp.src(sources)
                .pipe(sourcemaps.init())
                .pipe(concat(directory + result_name))
                .pipe(uglify())
                .pipe(sourcemaps.write("./", {
                    includeContent: false,
                    sourceRoot: directory
                }))
                .pipe(gulp.dest("./"));
        });
    }
});

module.exports = {
    "task_name": task_name
};
