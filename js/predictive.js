/**
 * Predictive Analytics Module
 * Handles fetching and displaying ridership forecast data.
 */

document.addEventListener('DOMContentLoaded', () => {
    initPredictivePage();
});

let forecastChart = null;
let allForecastData = [];

/**
 * Initializes the predictive analytics page.
 */
async function initPredictivePage() {
    try {
        showLoading('forecast-chart');
        allForecastData = await API.getForecast();

        if (!allForecastData || allForecastData.length === 0) {
            displayError('forecast-chart', 'No forecast data is available. Please run the prediction model.');
            return;
        }

        populateFilters(allForecastData);
        setupEventListeners();

        // Initial render
        const initialRoute = document.getElementById('route-selector').value;
        updateDashboard(initialRoute);

    } catch (error) {
        console.error('Error initializing predictive page:', error);
        displayError('forecast-chart', `Failed to load forecast data: ${error.message}`);
    }
}

/**
 * Populates the filter dropdowns.
 * @param {Array} data The forecast data.
 */
function populateFilters(data) {
    const routeSelector = document.getElementById('route-selector');
    const uniqueRoutes = [...new Set(data.map(item => item.route_id))].sort();

    routeSelector.innerHTML = uniqueRoutes.map(route => `<option value="${route}">${route}</option>`).join('');

    // Set default date if needed
    const datePicker = document.getElementById('date-picker');
    if (!datePicker.value) {
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        datePicker.value = tomorrow.toISOString().split('T')[0];
    }
}

/**
 * Sets up event listeners for filters.
 */
function setupEventListeners() {
    document.getElementById('apply-filters-btn').addEventListener('click', () => {
        const selectedRoute = document.getElementById('route-selector').value;
        updateDashboard(selectedRoute);
    });
}

/**
 * Updates the chart and table based on the selected route.
 * @param {string} routeId The selected bus route ID.
 */
function updateDashboard(routeId) {
    const filteredData = allForecastData.filter(item => item.route_id === routeId);
    renderForecastChart(filteredData);
    renderForecastTable(filteredData);
}

/**
 * Renders the forecast chart using Chart.js.
 * @param {Array} data The data for the selected route.
 */
function renderForecastChart(data) {
    const chartContainer = document.getElementById('forecast-chart');
    chartContainer.innerHTML = '<canvas id="forecast-canvas"></canvas>'; // Clear previous chart
    const ctx = document.getElementById('forecast-canvas').getContext('2d');

    const labels = data.map(item => `${item.date} ${String(item.hour).padStart(2, '0')}:00`);
    const passengerData = data.map(item => item.passengers);

    if (forecastChart) {
        forecastChart.destroy();
    }

    forecastChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Predicted Passengers',
                data: passengerData,
                borderColor: 'rgba(26, 115, 232, 1)',
                backgroundColor: 'rgba(26, 115, 232, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: 'rgba(26, 115, 232, 1)',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Date & Hour'
                    },
                    ticks: {
                        maxRotation: 45,
                        minRotation: 45,
                        autoSkip: true,
                        maxTicksLimit: 24 // Show a reasonable number of ticks
                    }
                },
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Number of Passengers'
                    }
                }
            },
            plugins: {
                tooltip: {
                    mode: 'index',
                    intersect: false,
                },
                legend: {
                    display: false
                }
            },
            hover: {
                mode: 'nearest',
                intersect: true
            }
        }
    });
}

/**
 * Renders the forecast data in a table.
 * @param {Array} data The data for the selected route.
 */
function renderForecastTable(data) {
    const tableBody = document.getElementById('forecast-table-body');
    const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    tableBody.innerHTML = data.map(item => `
        <tr>
            <td>${item.date}</td>
            <td>${String(item.hour).padStart(2, '0')}:00</td>
            <td>${days[item.day_of_week]}</td>
            <td>${Math.round(item.passengers)}</td>
        </tr>
    `).join('');
}