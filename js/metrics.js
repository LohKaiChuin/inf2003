/**
 * Metrics Module - Handles metrics cards display
 */

const Metrics = {
    /**
     * Format number to millions (e.g., 8500000 => "8.5M")
     */
    formatToMillions(num) {
        if (!num || num === 0) return '0';
        const millions = num / 1000000;
        return millions >= 1 ? `${millions.toFixed(1)}M` : `${(num / 1000).toFixed(0)}K`;
    },

    /**
     * Format peak month from "1 01 2019" to "January 2019"
     */
    formatPeakMonth(dateStr) {
        if (!dateStr || dateStr === 'N/A') return 'N/A';

        const parts = dateStr.trim().split(/\s+/);
        if (parts.length < 3) return dateStr;

        const monthNum = parseInt(parts[0]);
        const year = parts[2];

        const months = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];

        return `${months[monthNum - 1] || 'Unknown'} ${year}`;
    },

    /**
     * Format large numbers with commas
     */
    formatNumber(num) {
        return num.toLocaleString('en-US');
    },

    /**
     * Initialize top metrics cards
     */
    async initTopMetrics() {
        try {
            const data = await API.getSummary();

            // Most Popular Mode
            document.getElementById('metric-popular-mode').textContent = data.most_popular_mode || 'N/A';

            // Peak Month
            document.getElementById('metric-peak-month').textContent = this.formatPeakMonth(data.peak_month);

            // Transport Hubs
            document.getElementById('metric-hubs').textContent = this.formatNumber(data.total_hubs || 0);

            // Daily Ridership
            document.getElementById('metric-daily').textContent = this.formatToMillions(data.avg_daily_ridership);

            // Transport Modes
            document.getElementById('metric-modes').textContent = data.transport_modes || '0';

            // Add fade-in animation
            document.querySelectorAll('.metric-card').forEach(card => {
                card.classList.add('fade-in');
            });

        } catch (error) {
            console.error('Error loading top metrics:', error);
            // Set error state for metrics
            ['metric-popular-mode', 'metric-peak-month', 'metric-hubs', 'metric-daily', 'metric-modes'].forEach(id => {
                const element = document.getElementById(id);
                if (element) element.textContent = 'Error';
            });
        }
    },

    /**
     * Initialize bus metrics cards
     */
    async initBusMetrics() {
        try {
            const data = await API.getBusMetrics();

            // Total Bus Routes
            document.getElementById('bus-total-routes').textContent = this.formatNumber(data.total_routes || 0);

            // Single Operator Stops
            document.getElementById('bus-single-operator').textContent = this.formatNumber(data.single_operator_stops || 0);

            // Above Average Routes
            document.getElementById('bus-above-avg').textContent = this.formatNumber(data.high_ridership_routes || 0);

            // Peak Hour Usage
            document.getElementById('bus-peak-usage').textContent = `${data.peak_hour_usage || 0}%`;

            // Add fade-in animation
            document.querySelectorAll('#bus-metrics .metric-card').forEach(card => {
                card.classList.add('fade-in');
            });

        } catch (error) {
            console.error('Error loading bus metrics:', error);
            // Set error state for bus metrics
            ['bus-total-routes', 'bus-single-operator', 'bus-above-avg', 'bus-peak-usage'].forEach(id => {
                const element = document.getElementById(id);
                if (element) element.textContent = 'Error';
            });
        }
    },

    /**
     * Setup click handlers for metric cards
     */
    setupClickHandlers() {
        document.querySelectorAll('.metric-card[data-scroll-to]').forEach(card => {
            card.addEventListener('click', (e) => {
                const targetId = card.getAttribute('data-scroll-to');
                const targetElement = document.getElementById(targetId);

                if (targetElement) {
                    targetElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    }
};
