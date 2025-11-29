/**
 * API Module - Handles all API requests
 */

const API = {
    baseURL: 'api.php',

    /**
     * Generic fetch function with error handling
     */
    async fetchData(action, params = {}) {
        try {
            const url = new URL(this.baseURL, window.location.href);
            url.searchParams.append('action', action);

            // Add additional parameters
            Object.keys(params).forEach(key => {
                url.searchParams.append(key, params[key]);
            });

            const response = await fetch(url.toString());

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data.error) {
                throw new Error(data.error);
            }

            return data;

        } catch (error) {
            console.error(`API Error (${action}):`, error);
            throw error;
        }
    },

    /**
     * Get summary data for top metrics cards
     */
    async getSummary() {
        return await this.fetchData('summary');
    },

    /**
     * Get ridership trends for stacked area chart
     */
    async getRidershipTrends() {
        return await this.fetchData('ridership_trends');
    },

    /**
     * Get bus-specific metrics
     */
    async getBusMetrics() {
        return await this.fetchData('bus_metrics');
    },

    /**
     * Get peak vs off-peak data
     */
    async getPeakOffPeak() {
        return await this.fetchData('peak_offpeak');
    },

    /**
     * Get hourly ridership for heatmap
     */
    async getHourlyRidership() {
        return await this.fetchData('hourly_ridership');
    },

    /**
     * Get busiest bus stops
     */
    async getBusiestStops(limit = 10) {
        return await this.fetchData('busiest_stops', { limit });
    },

    /**
     * Get all POIs
     */
    async getPOIs() {
        return await this.fetchData('pois');
    },

    /**
     * Get POIs by category
     */
    async getPOIsByCategory(category) {
        return await this.fetchData('poi_by_category', { category });
    },

    /**
     * Get POI statistics
     */
    async getPOIStats() {
        return await this.fetchData('poi_stats');
    }
};

/**
 * Display error message to user
 */
function displayError(containerId, message) {
    const container = document.getElementById(containerId);
    if (container) {
        container.innerHTML = `
            <div class="error-message">
                <strong>Error:</strong> ${message}
            </div>
        `;
    }
}

/**
 * Show loading state
 */
function showLoading(containerId, message = 'Loading...') {
    const container = document.getElementById(containerId);
    if (container) {
        container.innerHTML = `
            <div class="loading-spinner">${message}</div>
        `;
    }
}
