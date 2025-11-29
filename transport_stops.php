<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transport Stops - Multilingual - YourTrip Analytics</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/transport_stops.css">
</head>
<body>
    <!-- Header -->
    <header class="dashboard-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="dashboard-title">Transport Stops</h1>
                    <p class="dashboard-subtitle">MRT & Bus Stops with Multilingual Support</p>
                </div>
                <nav>
                    <a href="index.php" class="btn btn-outline-primary">
                        <svg width="16" height="16" fill="currentColor" class="me-2" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z"/>
                        </svg>
                        Back to Dashboard
                    </a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container dashboard-container">

        <!-- Top Metrics Cards -->
        <section id="transport-metrics" class="section">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="metric-card">
                        <div class="metric-label">MRT STATIONS</div>
                        <div class="metric-value" id="metric-mrt-count">0</div>
                        <div class="metric-description">Total MRT Stations</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="metric-card">
                        <div class="metric-label">BUS STOPS</div>
                        <div class="metric-value" id="metric-bus-count">0</div>
                        <div class="metric-description">Total Bus Stops</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="metric-card">
                        <div class="metric-label">LANGUAGE</div>
                        <div class="metric-value" id="metric-current-lang">English</div>
                        <div class="metric-description">Current Display Language</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Filters -->
        <section id="transport-filters" class="section">
            <div class="card chart-card">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label for="stop-type-filter" class="form-label">Transport Type</label>
                            <select id="stop-type-filter" class="form-select">
                                <option value="all">All Transport Types</option>
                                <option value="mrt" selected>MRT Stations</option>
                                <option value="bus">Bus Stops</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="search-input" class="form-label">Search Stop</label>
                            <input type="text" id="search-input" class="form-control" placeholder="Search by name...">
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Map View -->
        <section id="transport-map-section" class="section">
            <div class="card chart-card">
                <div class="card-body">
                    <h2 class="chart-title mb-3">Transport Stops Map</h2>
                    <div id="transport-map" style="height: 600px; border-radius: 8px;"></div>
                </div>
            </div>
        </section>

        <!-- Stops List -->
        <section id="transport-list" class="section">
            <div class="card chart-card">
                <div class="card-body">
                    <h2 class="chart-title mb-3">Stops List</h2>
                    <div id="transport-results" class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Stop ID</th>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Location</th>
                                </tr>
                            </thead>
                            <tbody id="transport-results-body">
                                <tr>
                                    <td colspan="4" class="text-center py-5">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p class="mt-3 text-muted">Loading transport stops...</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

    </main>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <!-- Custom JS -->
    <script src="js/multilingual.js"></script>
    <script src="js/transport_stops.js"></script>
</body>
</html>
