/**
 * Charts Module - Custom SVG chart rendering
 * Enhanced version with improved aesthetics
 */

const Charts = {
    colors: {
        MRT: '#ea4335',
        LRT: '#fbbc04',
        'Public Bus': '#34a853',
        peak: '#dc2626',      // Red for peak hours
        offpeak: '#16a34a'    // Green for off-peak
    },

    monthNames: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],

    /**
     * Initialize ridership trends chart with year and mode selectors
     */
    async initRidershipTrends() {
        try {
            // Get all data initially to populate selectors
            const allData = await API.getRidershipTrends();

            if (!allData || allData.length === 0) {
                displayError('ridership-chart', 'No ridership data available');
                return;
            }

            // Get unique years and modes
            const years = [...new Set(allData.map(d => d.year))].sort();
            const modes = [...new Set(allData.map(d => d.label))].sort();

            // Populate mode selector
            const modeSelector = document.getElementById('mode-selector');
            modeSelector.innerHTML = '<option value="all">All Modes</option>' +
                modes.map(mode =>
                    `<option value="${mode}">${mode}</option>`
                ).join('');

            // Populate year selector
            const yearSelector = document.getElementById('year-selector');
            yearSelector.innerHTML = years.map(year =>
                `<option value="${year}">${year}</option>`
            ).join('');

            // Set defaults
            modeSelector.value = 'all';
            yearSelector.value = years[years.length - 1];

            // Create legend
            this.createRidershipLegend();

            // Render chart for selected year and mode
            const renderChart = async () => {
                const selectedYear = parseInt(yearSelector.value);
                const selectedMode = modeSelector.value;

                // Fetch data based on selected mode
                const data = await API.getRidershipTrends(selectedMode);
                this.renderStackedAreaChart(data, selectedYear, selectedMode);

                // Update legend visibility based on mode selection
                this.updateLegendVisibility(selectedMode);
            };

            modeSelector.addEventListener('change', renderChart);
            yearSelector.addEventListener('change', renderChart);
            renderChart();

        } catch (error) {
            displayError('ridership-chart', error.message);
        }
    },

    /**
     * Create legend for ridership chart
     */
    createRidershipLegend() {
        const legendContainer = document.getElementById('ridership-legend');
        const modes = ['LRT', 'MRT', 'Public Bus'];

        legendContainer.innerHTML = modes.map(mode => `
            <div class="legend-item" data-mode="${mode}">
                <div class="legend-color" style="background-color: ${this.colors[mode]}"></div>
                <span>${mode}</span>
            </div>
        `).join('');
    },

    /**
     * Update legend visibility based on selected mode
     */
    updateLegendVisibility(selectedMode) {
        const legendContainer = document.getElementById('ridership-legend');
        const legendItems = legendContainer.querySelectorAll('.legend-item');

        legendItems.forEach(item => {
            const mode = item.getAttribute('data-mode');
            if (selectedMode === 'all') {
                item.style.display = '';
            } else {
                item.style.display = (mode === selectedMode) ? '' : 'none';
            }
        });
    },

    /**
     * Render stacked area chart (or single area chart if one mode selected)
     */
    renderStackedAreaChart(allData, year, selectedMode = 'all') {
        const container = document.getElementById('ridership-chart');
        container.innerHTML = '';

        // Filter data for selected year
        const yearData = allData.filter(d => d.year === year);

        if (yearData.length === 0) {
            container.innerHTML = '<div class="loading-spinner">No data for selected year</div>';
            return;
        }

        // Determine which modes to display
        const availableModes = selectedMode === 'all'
            ? ['LRT', 'MRT', 'Public Bus']
            : [selectedMode];

        // Group by month
        const monthlyData = {};
        for (let month = 1; month <= 12; month++) {
            monthlyData[month] = {
                LRT: 0,
                MRT: 0,
                'Public Bus': 0
            };
        }

        yearData.forEach(d => {
            if (monthlyData[d.month]) {
                monthlyData[d.month][d.label] = d.total_passengers;
            }
        });

        // Setup SVG
        const margin = { top: 20, right: 20, bottom: 60, left: 80 };
        const width = container.offsetWidth;
        const height = 400;
        const chartWidth = width - margin.left - margin.right;
        const chartHeight = height - margin.top - margin.bottom;

        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('class', 'chart-svg');
        svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
        svg.setAttribute('preserveAspectRatio', 'xMidYMid meet');

        // Calculate stacked values
        const stackedData = [];
        for (let month = 1; month <= 12; month++) {
            const data = monthlyData[month];
            stackedData.push({
                month,
                LRT_start: 0,
                LRT_end: data.LRT,
                MRT_start: data.LRT,
                MRT_end: data.LRT + data.MRT,
                Bus_start: data.LRT + data.MRT,
                Bus_end: data.LRT + data.MRT + data['Public Bus']
            });
        }

        // Get max value for scale
        let maxValue;
        if (selectedMode === 'all') {
            maxValue = Math.max(...stackedData.map(d => d.Bus_end));
        } else {
            // For single mode, just get the max value of that mode
            maxValue = Math.max(...stackedData.map(d => monthlyData[d.month][selectedMode]));
        }

        const yScale = (value) => chartHeight - (value / maxValue * chartHeight);
        const xScale = (month) => ((month - 1) / 11) * chartWidth;

        // Create main group
        const g = document.createElementNS('http://www.w3.org/2000/svg', 'g');
        g.setAttribute('transform', `translate(${margin.left}, ${margin.top})`);

        // Draw grid lines
        for (let i = 0; i <= 4; i++) {
            const y = (chartHeight / 4) * i;
            const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            line.setAttribute('class', 'chart-grid-line');
            line.setAttribute('x1', 0);
            line.setAttribute('y1', y);
            line.setAttribute('x2', chartWidth);
            line.setAttribute('y2', y);
            g.appendChild(line);

            // Y-axis label
            const value = maxValue * (1 - i / 4);
            const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            text.setAttribute('class', 'chart-axis-text');
            text.setAttribute('x', -10);
            text.setAttribute('y', y + 4);
            text.setAttribute('text-anchor', 'end');
            text.textContent = (value / 1000000).toFixed(1) + 'M';
            g.appendChild(text);
        }

        // Draw areas based on selected mode
        if (selectedMode === 'all') {
            // Draw stacked areas for all modes
            const modes = [
                { key: 'LRT', start: 'LRT_start', end: 'LRT_end', color: this.colors.LRT },
                { key: 'MRT', start: 'MRT_start', end: 'MRT_end', color: this.colors.MRT },
                { key: 'Bus', start: 'Bus_start', end: 'Bus_end', color: this.colors['Public Bus'] }
            ];

            modes.forEach(mode => {
                const pathData = this.createAreaPath(stackedData, xScale, yScale, mode.start, mode.end, chartHeight);
                const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                path.setAttribute('class', 'area-path');
                path.setAttribute('d', pathData);
                path.setAttribute('fill', mode.color);
                g.appendChild(path);
            });
        } else {
            // Draw single area for selected mode
            const singleModeData = stackedData.map(d => ({
                month: d.month,
                value: monthlyData[d.month][selectedMode]
            }));

            const pathData = this.createSingleAreaPath(singleModeData, xScale, yScale, chartHeight);
            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('class', 'area-path');
            path.setAttribute('d', pathData);
            path.setAttribute('fill', this.colors[selectedMode]);
            g.appendChild(path);
        }

        // X-axis labels
        for (let month = 1; month <= 12; month++) {
            const x = xScale(month);
            const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            text.setAttribute('class', 'chart-axis-text');
            text.setAttribute('x', x);
            text.setAttribute('y', chartHeight + 25);
            text.setAttribute('text-anchor', 'middle');
            text.textContent = this.monthNames[month - 1];
            g.appendChild(text);
        }

        svg.appendChild(g);
        container.appendChild(svg);
    },

    /**
     * Create SVG path for area chart
     */
    createAreaPath(data, xScale, yScale, startKey, endKey, chartHeight) {
        let path = `M 0 ${yScale(data[0][startKey])}`;

        // Top line
        data.forEach((d, i) => {
            const x = xScale(d.month);
            const y = yScale(d[endKey]);
            path += ` L ${x} ${y}`;
        });

        // Bottom line (reversed)
        for (let i = data.length - 1; i >= 0; i--) {
            const x = xScale(data[i].month);
            const y = yScale(data[i][startKey]);
            path += ` L ${x} ${y}`;
        }

        path += ' Z';
        return path;
    },

    /**
     * Create SVG path for single mode area chart
     */
    createSingleAreaPath(data, xScale, yScale, chartHeight) {
        let path = `M 0 ${chartHeight}`;

        // Top line
        data.forEach((d, i) => {
            const x = xScale(d.month);
            const y = yScale(d.value);
            path += ` L ${x} ${y}`;
        });

        // Bottom line (reversed) - go back along the x-axis at chartHeight
        for (let i = data.length - 1; i >= 0; i--) {
            const x = xScale(data[i].month);
            path += ` L ${x} ${chartHeight}`;
        }

        path += ' Z';
        return path;
    },

    /**
     * Initialize peak vs off-peak VERTICAL bar chart
     */
    async initPeakOffPeak() {
        try {
            const data = await API.getPeakOffPeak();

            if (!data || data.length === 0) {
                displayError('peak-offpeak-chart', 'No peak/off-peak data available');
                return;
            }

            this.renderVerticalBarChart(data);

        } catch (error) {
            displayError('peak-offpeak-chart', error.message);
        }
    },

    /**
     * Render VERTICAL bar chart (improved version)
     */
    renderVerticalBarChart(data) {
        const container = document.getElementById('peak-offpeak-chart');
        container.innerHTML = '';

        const margin = { top: 60, right: 40, bottom: 80, left: 80 };
        const width = container.offsetWidth;
        const height = 400;
        const chartWidth = width - margin.left - margin.right;
        const chartHeight = height - margin.top - margin.bottom;

        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('class', 'chart-svg');
        svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
        svg.setAttribute('preserveAspectRatio', 'xMidYMid meet');

        const g = document.createElementNS('http://www.w3.org/2000/svg', 'g');
        g.setAttribute('transform', `translate(${margin.left}, ${margin.top})`);

        const maxValue = Math.max(...data.map(d => d.boarding));
        const barWidth = (chartWidth / data.length) * 0.6;
        const barSpacing = chartWidth / data.length;

        data.forEach((d, i) => {
            const x = i * barSpacing + (barSpacing - barWidth) / 2;
            const barHeight = (d.boarding / maxValue) * chartHeight;
            const y = chartHeight - barHeight;
            const color = d.period === 'Peak Hours' ? this.colors.peak : this.colors.offpeak;

            // Bar
            const rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
            rect.setAttribute('class', 'bar-rect');
            rect.setAttribute('x', x);
            rect.setAttribute('y', y);
            rect.setAttribute('width', barWidth);
            rect.setAttribute('height', barHeight);
            rect.setAttribute('fill', color);
            rect.setAttribute('rx', 6);
            g.appendChild(rect);

            // Value on top of bar
            const valueText = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            valueText.setAttribute('class', 'bar-top-label');
            valueText.setAttribute('x', x + barWidth / 2);
            valueText.setAttribute('y', y - 10);
            valueText.setAttribute('text-anchor', 'middle');
            valueText.setAttribute('fill', '#202124');
            valueText.setAttribute('font-weight', '600');
            valueText.setAttribute('font-size', '16');
            valueText.textContent = (d.boarding / 1000).toFixed(0) + 'K';
            g.appendChild(valueText);

            // Label below bar
            const labelGroup = document.createElementNS('http://www.w3.org/2000/svg', 'g');

            // Period name
            const periodLabel = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            periodLabel.setAttribute('x', x + barWidth / 2);
            periodLabel.setAttribute('y', chartHeight + 25);
            periodLabel.setAttribute('text-anchor', 'middle');
            periodLabel.setAttribute('fill', '#202124');
            periodLabel.setAttribute('font-weight', '500');
            periodLabel.setAttribute('font-size', '13');
            periodLabel.textContent = d.period;
            labelGroup.appendChild(periodLabel);

            // Percentage
            const percentLabel = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            percentLabel.setAttribute('x', x + barWidth / 2);
            percentLabel.setAttribute('y', chartHeight + 43);
            percentLabel.setAttribute('text-anchor', 'middle');
            percentLabel.setAttribute('fill', color);
            percentLabel.setAttribute('font-weight', '600');
            percentLabel.setAttribute('font-size', '12');
            percentLabel.textContent = `(${d.percentage}%)`;
            labelGroup.appendChild(percentLabel);

            g.appendChild(labelGroup);
        });

        // Add Y-axis gridlines and labels
        for (let i = 0; i <= 4; i++) {
            const y = chartHeight - (i / 4) * chartHeight;
            const value = (maxValue / 4) * i;

            // Grid line
            const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            line.setAttribute('class', 'chart-grid-line');
            line.setAttribute('x1', 0);
            line.setAttribute('y1', y);
            line.setAttribute('x2', chartWidth);
            line.setAttribute('y2', y);
            line.setAttribute('stroke', '#e5e7eb');
            line.setAttribute('stroke-dasharray', '4 4');
            g.appendChild(line);

            // Y-axis label
            const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            text.setAttribute('class', 'chart-axis-text');
            text.setAttribute('x', -10);
            text.setAttribute('y', y + 4);
            text.setAttribute('text-anchor', 'end');
            text.setAttribute('font-size', '11');
            text.textContent = (value / 1000).toFixed(0) + 'K';
            g.appendChild(text);
        }

        // Add Y-axis title
        const yAxisTitle = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        yAxisTitle.setAttribute('transform', `translate(-60, ${chartHeight / 2}) rotate(-90)`);
        yAxisTitle.setAttribute('text-anchor', 'middle');
        yAxisTitle.setAttribute('fill', '#6b7280');
        yAxisTitle.setAttribute('font-size', '12');
        yAxisTitle.setAttribute('font-weight', '500');
        yAxisTitle.textContent = 'Passenger Boarding Count';
        g.appendChild(yAxisTitle);

        svg.appendChild(g);
        container.appendChild(svg);
    },

    /**
     * Initialize hourly ridership heatmap (HORIZONTAL LAYOUT)
     */
    async initHourlyHeatmap() {
        try {
            const data = await API.getHourlyRidership();

            if (!data || data.length === 0) {
                displayError('hourly-heatmap-chart', 'No hourly ridership data available');
                return;
            }

            this.renderHorizontalHeatmap(data);

        } catch (error) {
            displayError('hourly-heatmap-chart', error.message);
        }
    },

    /**
     * Render horizontal heatmap (hours across top, 2 rows)
     */
    renderHorizontalHeatmap(data) {
        const container = document.getElementById('hourly-heatmap-chart');
        container.innerHTML = '';

        const margin = { top: 70, right: 40, bottom: 80, left: 100 };
        const width = container.offsetWidth;
        const height = 300;
        const chartWidth = width - margin.left - margin.right;
        const chartHeight = height - margin.top - margin.bottom;

        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('class', 'chart-svg');
        svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
        svg.setAttribute('preserveAspectRatio', 'xMidYMid meet');

        const g = document.createElementNS('http://www.w3.org/2000/svg', 'g');
        g.setAttribute('transform', `translate(${margin.left}, ${margin.top})`);

        // Get max/min values for color scale
        const allValues = data.flatMap(d => [d.weekday, d.weekend]).filter(v => v > 0);
        const maxValue = Math.max(...allValues);
        const minValue = Math.min(...allValues);

        const cellWidth = chartWidth / 24;
        const cellHeight = chartHeight / 2;

        // Row labels with better styling
        ['Weekday', 'Weekend'].forEach((label, row) => {
            const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            text.setAttribute('class', 'heatmap-label');
            text.setAttribute('x', -15);
            text.setAttribute('y', row * cellHeight + cellHeight / 2 + 6);
            text.setAttribute('text-anchor', 'end');
            text.setAttribute('font-weight', '600');
            text.setAttribute('font-size', '14');
            text.setAttribute('fill', '#374151');
            text.textContent = label;
            g.appendChild(text);
        });

        // Create tooltip
        const tooltip = document.createElement('div');
        tooltip.className = 'chart-tooltip';
        container.appendChild(tooltip);

        // Draw cells
        data.forEach((d, hour) => {
            // Hour label at top - show every 2 hours for clarity
            if (hour % 2 === 0) {
                const hourText = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                hourText.setAttribute('class', 'heatmap-hour-label');
                hourText.setAttribute('x', hour * cellWidth + cellWidth / 2);
                hourText.setAttribute('y', -15);
                hourText.setAttribute('text-anchor', 'middle');
                hourText.setAttribute('font-size', '12');
                hourText.setAttribute('font-weight', '500');
                hourText.setAttribute('fill', '#6b7280');
                hourText.textContent = this.formatHour12(d.hour);
                g.appendChild(hourText);
            }

            // Weekday cell
            this.createHorizontalHeatmapCell(g, d.weekday, hour, 0, cellWidth, cellHeight, minValue, maxValue, tooltip, `Weekday ${this.formatHour12(d.hour)}`, d.weekday);

            // Weekend cell
            this.createHorizontalHeatmapCell(g, d.weekend, hour, 1, cellWidth, cellHeight, minValue, maxValue, tooltip, `Weekend ${this.formatHour12(d.hour)}`, d.weekend);
        });

        // Add color scale legend at bottom
        this.addHeatmapLegend(g, chartWidth, chartHeight + 30, minValue, maxValue);

        svg.appendChild(g);
        container.appendChild(svg);
    },

    /**
     * Create horizontal heatmap cell with value inside
     */
    createHorizontalHeatmapCell(parent, value, col, row, cellWidth, cellHeight, minValue, maxValue, tooltip, label, count) {
        const x = col * cellWidth + 2;
        const y = row * cellHeight + 2;

        const rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
        rect.setAttribute('class', 'heatmap-cell');
        rect.setAttribute('x', x);
        rect.setAttribute('y', y);
        rect.setAttribute('width', cellWidth - 4);
        rect.setAttribute('height', cellHeight - 4);
        rect.setAttribute('rx', 4);
        rect.setAttribute('stroke', 'rgba(255, 255, 255, 0.3)');
        rect.setAttribute('stroke-width', '0.5');
        rect.style.cursor = 'pointer';
        rect.style.transition = 'all 0.2s ease';

        // Enhanced color scale: green → yellow → orange → red
        const intensity = value > 0 ? (value - minValue) / (maxValue - minValue) : 0;
        const color = this.getHeatmapColor(intensity);
        rect.setAttribute('fill', color);

        // Add value text inside cell if cell is large enough
        if (cellWidth > 25) {
            const valueText = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            valueText.setAttribute('x', x + cellWidth / 2);
            valueText.setAttribute('y', y + cellHeight / 2 + 4);
            valueText.setAttribute('text-anchor', 'middle');
            valueText.setAttribute('font-size', '10');
            valueText.setAttribute('font-weight', '600');
            valueText.setAttribute('fill', intensity > 0.6 ? '#ffffff' : '#1f2937');
            valueText.textContent = (count / 1000).toFixed(0) + 'K';
            parent.appendChild(valueText);
        }

        // Tooltip events with improved hover effect
        rect.addEventListener('mouseenter', (e) => {
            tooltip.innerHTML = `<strong>${label}</strong><br>Boardings: ${count.toLocaleString()}`;
            tooltip.classList.add('show');
            rect.style.filter = 'brightness(1.1)';
            rect.style.transform = 'scale(1.05)';
        });

        rect.addEventListener('mousemove', (e) => {
            tooltip.style.left = (e.pageX + 10) + 'px';
            tooltip.style.top = (e.pageY - 30) + 'px';
        });

        rect.addEventListener('mouseleave', () => {
            tooltip.classList.remove('show');
            rect.style.filter = 'none';
            rect.style.transform = 'scale(1)';
        });

        parent.appendChild(rect);
    },

    /**
     * Get heatmap color with better gradient
     */
    getHeatmapColor(intensity) {
        // Green → Yellow → Orange → Red gradient
        if (intensity < 0.25) {
            return this.interpolateColor('#86efac', '#fef08a', intensity * 4);
        } else if (intensity < 0.5) {
            return this.interpolateColor('#fef08a', '#fb923c', (intensity - 0.25) * 4);
        } else if (intensity < 0.75) {
            return this.interpolateColor('#fb923c', '#ef4444', (intensity - 0.5) * 4);
        } else {
            return this.interpolateColor('#ef4444', '#991b1b', (intensity - 0.75) * 4);
        }
    },

    /**
     * Add color scale legend with improved styling
     */
    addHeatmapLegend(parent, width, y, minValue, maxValue) {
        const legendWidth = 350;
        const legendHeight = 24;
        const legendX = (width - legendWidth) / 2;

        // Draw gradient rectangle
        const defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
        const gradient = document.createElementNS('http://www.w3.org/2000/svg', 'linearGradient');
        gradient.setAttribute('id', 'heatmap-gradient');
        gradient.setAttribute('x1', '0%');
        gradient.setAttribute('x2', '100%');

        const stops = [
            { offset: '0%', color: '#86efac' },
            { offset: '25%', color: '#fef08a' },
            { offset: '50%', color: '#fb923c' },
            { offset: '75%', color: '#ef4444' },
            { offset: '100%', color: '#991b1b' }
        ];

        stops.forEach(stop => {
            const stopEl = document.createElementNS('http://www.w3.org/2000/svg', 'stop');
            stopEl.setAttribute('offset', stop.offset);
            stopEl.setAttribute('stop-color', stop.color);
            gradient.appendChild(stopEl);
        });

        defs.appendChild(gradient);
        parent.appendChild(defs);

        // Add subtle border around legend
        const legendBorder = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
        legendBorder.setAttribute('x', legendX);
        legendBorder.setAttribute('y', y);
        legendBorder.setAttribute('width', legendWidth);
        legendBorder.setAttribute('height', legendHeight);
        legendBorder.setAttribute('fill', 'url(#heatmap-gradient)');
        legendBorder.setAttribute('stroke', '#d1d5db');
        legendBorder.setAttribute('stroke-width', '1');
        legendBorder.setAttribute('rx', 4);
        parent.appendChild(legendBorder);

        // "Low" label with better styling
        const lowText = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        lowText.setAttribute('x', legendX - 8);
        lowText.setAttribute('y', y + legendHeight / 2 + 5);
        lowText.setAttribute('text-anchor', 'end');
        lowText.setAttribute('font-size', '12');
        lowText.setAttribute('font-weight', '500');
        lowText.setAttribute('fill', '#6b7280');
        lowText.textContent = 'Low';
        parent.appendChild(lowText);

        // "High" label with better styling
        const highText = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        highText.setAttribute('x', legendX + legendWidth + 8);
        highText.setAttribute('y', y + legendHeight / 2 + 5);
        highText.setAttribute('text-anchor', 'start');
        highText.setAttribute('font-size', '12');
        highText.setAttribute('font-weight', '500');
        highText.setAttribute('fill', '#6b7280');
        highText.textContent = 'High';
        parent.appendChild(highText);
    },

    /**
     * Interpolate between two colors
     */
    interpolateColor(color1, color2, factor) {
        const c1 = this.hexToRgb(color1);
        const c2 = this.hexToRgb(color2);

        const r = Math.round(c1.r + factor * (c2.r - c1.r));
        const g = Math.round(c1.g + factor * (c2.g - c1.g));
        const b = Math.round(c1.b + factor * (c2.b - c1.b));

        return `rgb(${r}, ${g}, ${b})`;
    },

    /**
     * Convert hex to RGB
     */
    hexToRgb(hex) {
        const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? {
            r: parseInt(result[1], 16),
            g: parseInt(result[2], 16),
            b: parseInt(result[3], 16)
        } : { r: 0, g: 0, b: 0 };
    },

    /**
     * Format hour to 12-hour format (12am style)
     */
    formatHour12(hour) {
        if (hour === 0) return '12am';
        if (hour === 12) return '12pm';
        if (hour < 12) return `${hour}am`;
        return `${hour - 12}pm`;
    }
};
