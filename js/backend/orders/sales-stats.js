(function ($) {
    $.orders_sales = {
        init: function (options) {
            this.options = options || {};
            this.$wrapper = options.$wrapper;
            this.date = options.date;
            this.$wrapper.html(options.preloaded_html);
        },
        reload: function () {
            if ($('#js-orders-stats').is(':hidden')) {
                return;
            }
            $.post('?module=orders&action=salesStats', { date: this.date }).then((r) => {
                this.$wrapper.html(r);
            }, () => {
                console.log('Error loading orders stats', arguments);
                this.$wrapper.empty();
            });
        }
    }
})(jQuery);

window.PaidOrdersSalesGraph = (function($) { "use strict";
    function Graph(options) {
        this.node = options.node;
        this.color = options.color;
        this.date = options.date;

        if (!(this.node instanceof HTMLElement)) {
            return;
        }
        this.node.innerHTML = '';
        this.renderChart(this.getData(options.data));
    }

    Graph.prototype.linkFormationCallback = (chart) => {
        var filter_params_str = '&' + ($.order_list.options.filter_params_str||'').replace(/viewpos=[0-9-]*/, '');
        filter_params_str += '&viewpos='+chart.date;
        filter_params_str = filter_params_str.replace('&&', '&').replace(/\&$/, '');
        return '#/orders/view=split' + filter_params_str + '/';
    };

    Graph.prototype.renderChart = function(data) {
        let total = 0;
        data.forEach(item => {
            if (item.value > total) {
                total = item.value;
            }
        });
        total = Math.round(total);

        const col_min_height = 1;
        const $wrapper = $(this.node);
        const max_height = $wrapper.height() - 20;
        data.forEach(item => {
            let height = max_height * item.value / total;
            if (height > 0) {
                height = Math.max(height, col_min_height).toFixed(2);
            }

            const day = new Date(item.date).getDate();
            const html = `
                <a href="${this.linkFormationCallback(item)}" class="s-black-link flexbox vertical width-100 height-100" data-wa-tooltip-template="#template-col-${item.date}">
                    <div class="wa-tooltip-template" id="template-col-${item.date}">${item.tooltip}</div>
                    <div class="wide flexbox vertical justify-content-end" style="height:${max_height}px;">
                        <div style="height:${height}px;background:${this.color};"></div>
                    </div>
                    <div style="height:2px; background:var(${item.date === this.date ? '--accent-color' : '--light-gray'})"></div>
                    <div class="align-center smaller">${day}</div>
                </a>
            `;
            $wrapper.append(html.trim());
        });
        $wrapper.find('a[data-wa-tooltip-template]').waTooltip({
            placement:"top",
            offset: [0, 0],
            class:"hint bold"
        });
    };

    Graph.prototype.getData = function(data) {
        data = Object.values(data);

        const result = [];
        for (let chart of data) {
            result.push({
                value: parseFloat(chart.sales || 0),
                date: chart.date,
                tooltip: chart.total_html
            });
        }

        return result;
    };
    return Graph;
})(jQuery);

window.PaymentTypeSalesGraph = (function () { "use-strict";
    function Graph(options) {
        this.node = options.node;
        this.colors = options.colors;
        this.tooltipCallback = options.tooltipCallback;

        if (!(this.node instanceof HTMLElement)) {
            return;
        }
        this.node.innerHTML = '';
        this.renderChart(this.getData(options.data));
    }

    Graph.prototype.renderChart = function(data) {
        let total = 0;
        data.forEach(item => {
            total += item.value;
        });
        if (total <= 0) {
            return;
        }
        total = Math.round(total);

        const col_min_width = 2;
        const max_width = this.node.clientWidth;
        data.forEach((item, i) => {
            let width = item.value / total * max_width;
            if (width > 0) {
                width = Math.max(width, col_min_width).toFixed(2);
            }

            const el = document.createElement('div');
            el.style.width = width + 'px';
            el.style.background = this.colors[i];
            if (item.tooltip) {
                el.title = item.tooltip;
            }

            this.node.appendChild(el);
        });
    };

    Graph.prototype.getData = function(data) {
        data = Object.values(data);

        const result = [];
        for (let chart of data) {
            result.push({
                value: parseFloat(chart.sales || 0),
                tooltip: typeof this.tooltipCallback === 'function' ? this.tooltipCallback(chart) : null
            });
        }

        return result;
    };
    return Graph;
})();
