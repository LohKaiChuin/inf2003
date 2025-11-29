/**
 * Predictive Analytics Page - Main Module
 * Handles data loading and visualization for forecasts.
 */

document.addEventListener('DOMContentLoaded', () => {
    // Add a specific API endpoint for our new forecast data
    API.getForecast = async () => await API.fetchData('ridership_forecast');
    console.log('Predictive Analytics page initializing...');
    initPredictivePage();
});

/**
 * Initialize all components on the predictive analytics page.
 */
async function initPredictivePage() {
    try {
        // Load data in parallel for performance
        await Promise.all([
            loadPredictiveMetrics(),
            initForecastChart(),
            initHotspotMap()
        ]);

        console.log('✓ Predictive Analytics page initialized successfully!');

    } catch (error) {
        console.error('Error initializing predictive page:', error);
        // You can show a global error on the page if needed
    }
}

/**
 * Load and display the top predictive metric cards.
 */
async function loadPredictiveMetrics() {
    console.log('Loading predictive metrics...');
    // TODO: Replace with your actual API call for predictive metrics
    // const metrics = await API.getPredictiveSummary();

    // Using mock data for now
    const mockMetrics = {
        nextMonthRidership: 85_500_000,
        predictedPeak: '6:00 PM',
        demandHotspot: 'Jurong East',
        modelAccuracy: '94.5%'
    };

    document.getElementById('metric-next-month-ridership').textContent = (mockMetrics.nextMonthRidership / 1_000_000).toFixed(1) + 'M';
    document.getElementById('metric-predicted-peak').textContent = mockMetrics.predictedPeak;
    document.getElementById('metric-demand-hotspot').textContent = mockMetrics.demandHotspot;
    document.getElementById('metric-model-accuracy').textContent = mockMetrics.modelAccuracy;
    console.log('✓ Predictive metrics loaded.');
}

/**
 * Initialize the ridership forecast chart.
 */
async function initForecastChart() {
    console.log('Initializing forecast chart...');
    try {
        const forecastData = await API.getForecast();

        // Group data by month and sum passengers
        const monthlyData = forecastData.reduce((acc, record) => {
            // Extract YYYY-MM from the date string
            const monthKey = record.date.substring(0, 7);
            if (!acc[monthKey]) {
                acc[monthKey] = 0;
            }
            acc[monthKey] += record.passengers;
            return acc;
        }, {});

        const labels = Object.keys(monthlyData);
        const dataPoints = Object.values(monthlyData);

        const container = document.getElementById('forecast-chart');
        container.innerHTML = '<canvas id="ridershipForecastCanvas"></canvas>'; // Add a canvas element
        const ctx = document.getElementById('ridershipForecastCanvas').getContext('2d');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Predicted Ridership',
                    data: dataPoints,
                    borderColor: '#1a73e8',
                    backgroundColor: 'rgba(26, 115, 232, 0.1)',
                    fill: true,
                    tension: 0.4, // Makes the line smooth
                    pointBackgroundColor: '#1a73e8',
                    pointRadius: 4,
                    pointHoverRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Total Passengers'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Month'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false // Hide legend as there's only one dataset
                    }
                }
            }
        });
    } catch (error) {
        console.error('Error rendering forecast chart:', error);
    }
    console.log('✓ Forecast chart initialized.');
}

/**
 * Initialize the demand hotspot map.
 */
async function initHotspotMap() {
    console.log('Initializing hotspot map...');
    const mapContainer = document.getElementById('hotspot-map');

    // TODO: Add your Leaflet map logic here.
    // You can display polygons or a heatmap of predicted high-demand areas.
    const map = L.map(mapContainer).setView([1.3521, 103.8198], 12);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>'
    }).addTo(map);

    // Example: Add a sample marker
    L.marker([1.3329, 103.7436]).addTo(map)
        .bindPopup('<b>Predicted Hotspot:</b><br>Jurong East Area');

    console.log('✓ Hotspot map initialized.');
}