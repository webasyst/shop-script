/* d3.js 3.5.7 version */
window.CurrencyChartD3 = (function () {
    function CurrencyChartD3(element, startDate, endDate, width = null, height = null) {
        this.element = element;
        this.width = width === null ? this.element.getAttribute('width') : width;
        this.height = height === null ? this.element.getAttribute('height') : height;
        this.margin = { top: 3, right: 0, bottom: 3, left: 0 };

        this._uid = Math.random().toString(36).substring(2, 9);

        const daysInInterval = Math.round((endDate.getTime() - startDate.getTime()) / (1000 * 3600 * 24));
        this.timeline = new Array(daysInInterval).fill(null).map((e, i) => ({
                period: new Date(startDate.getTime()).setDate(startDate.getDate() + i),
                amount: null
            }));
    }

    CurrencyChartD3.prototype.renderChart = function (chartData) {
        const data = this.timeline.map((item) => Object.assign({}, item));

        // Merge empty period with days with data
        chartData.forEach((element) => {
            const i = data.findIndex((e) => {
                return e.period === new Date(element.period).getTime();
            });
            if (i > -1) {
                data.splice(i, 1, Object.assign({}, element));
            }
        });

        const svg = d3.select(this.element);
        svg.selectAll('*').remove();
        svg.attr('width', this.width);
        svg.attr('height', this.height);
        svg.attr('viewBox', [0, 0, this.width, this.height]);

        const x = d3.time.scale()
            .domain(d3.extent(data, (d) => new Date(d.period)))
            .range([this.margin.left, this.width - this.margin.right]);

        const y = d3.scale.linear()
            .domain([
                d3.min(data, (d) => d.amount),
                d3.max(data, (d) => d.amount)
            ])
            .nice()
            .range([this.height - this.margin.bottom, this.margin.top])
            .clamp(true);

        // Draw Balance Line Past
        const nowMs = new Date().getTime();
        const pastDates = data.filter((e) => {
            return nowMs - new Date(e.period).getTime() >= 0;
        });

        const linePast = d3.svg.line()
            .x((d) => x(new Date(d.period)))
            .y((d) => y(d.amount));

        svg.append('g')
            .append('path')
            .attr('style', 'stroke: url(#line-gradient-' + this._uid + ');fill: none;stroke-width: 2px;')
            .attr('d', linePast(pastDates));

        // Draw Negative Area
        const areaNeg = d3.svg.area()
            .x((d) => x(new Date(d.period)))
            .y0(y(0))
            .y1(d => y(Math.min(0, d.amount)));

        svg.append('path')
            .datum(data)
            .attr('d', areaNeg)
            .attr('style', 'fill: url(#area-gradient-negative-' + this._uid + ');stroke-width: 0px;');

        // Draw Positive Area
        const areaPos = d3.svg.area()
            .x((d) => x(new Date(d.period)))
            .y0(y(0))
            .y1((d) => y(Math.max(0, d.amount)));

        svg.append('path')
            .datum(data)
            .attr('d', areaPos)
            .attr('style', 'fill: url(#area-gradient-positive-' + this._uid + ');stroke-width: 0px;');

        // Gradient positive area
        if (d3.max(data, (d) => d.amount) >= 0) {
            svg.append('linearGradient')
                .attr('id', 'area-gradient-positive-' + this._uid)
                .attr('gradientUnits', 'userSpaceOnUse')
                .attr('x1', 0)
                .attr('y1', y(0))
                .attr('x2', 0)
                .attr('y2', y(d3.max(data, (d) => d.amount)))
                .selectAll('stop')
                .data([
                    { offset: '0%', color: 'rgba(62, 197, 94, 0)' },
                    { offset: '100%', color: 'rgba(62, 197, 94, 0.3)' }
                ])
                .enter()
                .append('stop')
                .attr('offset', (d) => d.offset)
                .attr('stop-color', (d) => d.color);
        }

        // Gradient negative area
        if (d3.min(data, (d) => d.amount) < 0) {
            svg.append('linearGradient')
                .attr('id', 'area-gradient-negative-' + this._uid)
                .attr('gradientUnits', 'userSpaceOnUse')
                .attr('x1', 0)
                .attr('y1', y(0))
                .attr('x2', 0)
                .attr('y2', y(d3.min(data, (d) => d.amount)))
                .selectAll('stop')
                .data([
                    { offset: '0%', color: 'rgba(252, 61, 56, 0)' },
                    { offset: '100%', color: 'rgba(252, 61, 56, 0.6)' }
                ])
                .enter()
                .append('stop')
                .attr('offset', (d) => d.offset)
                .attr('stop-color', (d) => d.color);
        }

        // Gradient for the line
        const amountRange = Math.abs(d3.max(data, (d) => d.amount)) +
            Math.abs(d3.min(data, (d) => d.amount));

        const amountMax = d3.max(data, (d) => d.amount);
        const amountMin = d3.min(data, (d) => d.amount);

        let offset;
        if (amountMax < 0) {
            offset = 0;
        } else if (amountMin >= 0) {
            offset = this.height;
        } else {
            offset = Math.ceil((Math.abs(d3.max(data, (d) => d.amount)) / amountRange) * 100) + '%';
        }

        svg.append('linearGradient')
            .attr('id', 'line-gradient-' + this._uid)
            .attr('gradientUnits', 'userSpaceOnUse')
            .attr('x1', 0)
            .attr('y1', 0)
            .attr('x2', 0)
            .attr('y2', this.height)
            .selectAll('stop')
            .data([
                { offset: offset, color: '#3ec55e' },
                { offset: offset, color: '#fc3d38' }
            ])
            .enter()
            .append('stop')
            .attr('offset', (d) => d.offset)
            .attr('stop-color', (d) => d.color);

        // current day pointer
        const pointData = data[data.length - 1];
        svg.append('circle')
            .attr('cx', x(new Date(pointData.period)))
            .attr('cy', y(pointData.amount))
            .attr('r', 3)
            .attr('fill', pointData.amount < 0 ? '#fc3d38' : '#3ec55e');

        return this;
    }

    return CurrencyChartD3;
})();
