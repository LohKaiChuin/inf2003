<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Predictive Analytics - YourTrip</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/navigation.css">
    <link rel="stylesheet" href="css/predictive.css">
</head>
<body>
    <!-- Navigation will be loaded here by navigation.js -->

    <!-- Header -->
    <header class="dashboard-header">
        <div class="container">
            <h1 class="dashboard-title">Predictive Analytics</h1>
            <p class="dashboard-subtitle">Forecasting Future Bus Ridership</p>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container dashboard-container">

        <!-- Filter Controls -->
        <section id="predictive-filters" class="section">
            <div class="card chart-card">
                <div class="card-body">
                    <h2 class="chart-title">Forecast Filters</h2>
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="route-selector" class="form-label">Bus Route</label>
                            <select id="route-selector" class="form-select">
                                <option value="">Loading routes...</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="date-picker" class="form-label">Forecast Date</label>
                            <input type="date" id="date-picker" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <button id="apply-filters-btn" class="btn btn-primary w-100">Apply Filters</button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Ridership Forecast Chart -->
        <section id="ridership-forecast" class="section">
            <div class="card chart-card">
                <div class="card-body">
                    <h2 class="chart-title">Hourly Ridership Forecast</h2>
                    <div id="forecast-chart" class="chart-container">
                        <div class="loading-spinner">Loading forecast...</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Data Table -->
        <section id="forecast-table-section" class="section">
            <div class="card chart-card">
                <div class="card-body">
                    <h2 class="chart-title">Forecast Data</h2>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Hour</th>
                                    <th>Day of Week</th>
                                    <th>Predicted Passengers</th>
                                </tr>
                            </thead>
                            <tbody id="forecast-table-body">
                                <!-- Data will be populated by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

    </main>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>

    <!-- Custom JS -->
    <script src="js/api.js"></script>
    <script src="js/navigation.js"></script>
    <script src="js/predictive.js"></script>
</body>
</html>
