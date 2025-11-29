/**
 * Main Module - Initialize YourTrip Analytics Dashboard
 */

document.addEventListener('DOMContentLoaded', () => {
    console.log('YourTrip Analytics Dashboard Initializing...');

    // Initialize all dashboard components
    initDashboard();
});

/**
 * Initialize all dashboard components
 * OPTIMIZED: Load all data in parallel for faster page load
 */
async function initDashboard() {
    try {
        // Setup metric card click handlers (synchronous)
        Metrics.setupClickHandlers();

        console.log('Loading dashboard data in parallel...');

        // Load all data in parallel using Promise.all for 5-10x faster loading!
        await Promise.all([
            Metrics.initTopMetrics().then(() => console.log('✓ Top metrics loaded')),
            Charts.initRidershipTrends().then(() => console.log('✓ Ridership trends loaded')),
            Metrics.initBusMetrics().then(() => console.log('✓ Bus metrics loaded')),
            Charts.initPeakOffPeak().then(() => console.log('✓ Peak/Off-peak chart loaded')),
            Charts.initHourlyHeatmap().then(() => console.log('✓ Hourly heatmap loaded')),
            IntermodalAnalysis.init().then(() => console.log('✓ Intermodal analysis loaded'))
        ]);

        // Setup lazy loading for map
        MapModule.setupLazyLoading();
        console.log('✓ Map lazy loading setup');

        // Setup window resize handler for responsive charts
        setupResizeHandler();

        console.log('✓ Dashboard initialization complete!');

    } catch (error) {
        console.error('Dashboard initialization error:', error);
        showGlobalError(error.message);
    }
}

/**
 * Setup window resize handler with debouncing
 */
function setupResizeHandler() {
    let resizeTimer;

    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);

        resizeTimer = setTimeout(async () => {
            console.log('Window resized, redrawing charts...');

            // Redraw all charts
            try {
                // Ridership trends - trigger year selector change
                const yearSelector = document.getElementById('year-selector');
                if (yearSelector && yearSelector.value) {
                    const allData = await API.getRidershipTrends();
                    Charts.renderStackedAreaChart(allData, parseInt(yearSelector.value));
                }

                // Peak/Off-peak chart
                const peakData = await API.getPeakOffPeak();
                Charts.renderHorizontalBarChart(peakData);

                // Hourly heatmap
                const hourlyData = await API.getHourlyRidership();
                Charts.renderHeatmap(hourlyData);

                console.log('✓ Charts redrawn');

            } catch (error) {
                console.error('Error redrawing charts:', error);
            }

        }, 250); // Debounce delay
    });
}

/**
 * Show global error message
 */
function showGlobalError(message) {
    const container = document.querySelector('.dashboard-container');

    if (container) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.innerHTML = `
            <strong>Dashboard Error:</strong> ${message}<br>
            <small>Please check your database connection and try refreshing the page.</small>
        `;

        container.insertBefore(errorDiv, container.firstChild);
    }
}

/**
 * Smooth scroll utility
 */
function smoothScrollTo(elementId) {
    const element = document.getElementById(elementId);

    if (element) {
        element.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }
}

// Export utilities for global access
window.YourTripDashboard = {
    smoothScrollTo,
    API,
    Metrics,
    Charts,
    MapModule,
    IntermodalAnalysis
};

console.log('YourTrip Analytics Dashboard v1.0 - Ready');
