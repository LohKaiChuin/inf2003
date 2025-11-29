<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POI Recommendations - YourTrip Analytics</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/poi.css">
</head>
<body>
    <!-- Header -->
    <header class="dashboard-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="dashboard-title">POI Recommendations</h1>
                    <p class="dashboard-subtitle">Points of Interest Near Transport Hubs</p>
                </div>
                <?php include "inc/navbar.inc.php" ?>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container dashboard-container">

        <!-- Top Metrics Cards -->
        <section id="poi-metrics" class="section">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="metric-card">
                        <div class="metric-label">TOTAL POIs</div>
                        <div class="metric-value" id="metric-total-pois">0</div>
                        <div class="metric-description">Points of Interest</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card">
                        <div class="metric-label">CATEGORIES</div>
                        <div class="metric-value" id="metric-categories">0</div>
                        <div class="metric-description">POI Categories</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card">
                        <div class="metric-label">AVG RATING</div>
                        <div class="metric-value" id="metric-avg-rating">-</div>
                        <div class="metric-description">Overall rating</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card">
                        <div class="metric-label">TRANSPORT HUBS</div>
                        <div class="metric-value" id="metric-nearby-hubs">0</div>
                        <div class="metric-description">With nearby POIs</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- POI Filters and Search -->
        <section id="poi-filters" class="section">
            <div class="card chart-card">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="category-filter" class="form-label">Category</label>
                            <select id="category-filter" class="form-select">
                                <option value="">All Categories</option>
                                <option value="Food & Dining">Food & Dining</option>
                                <option value="Shopping">Shopping</option>
                                <option value="Entertainment">Entertainment</option>
                                <option value="Parks & Recreation">Parks & Recreation</option>
                                <option value="Cultural">Cultural</option>
                                <option value="Services">Services</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="distance-filter" class="form-label">Max Distance from Transport Hub</label>
                            <select id="distance-filter" class="form-select">
                                <option value="">Any Distance</option>
                                <option value="200">Within 200m</option>
                                <option value="500">Within 500m</option>
                                <option value="1000">Within 1km</option>
                                <option value="2000">Within 2km</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="search-input" class="form-label">Search POI</label>
                            <input type="text" id="search-input" class="form-control" placeholder="Search by name or location...">
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- POI Map View -->
        <section id="poi-map-section" class="section">
            <div class="card chart-card">
                <div class="card-body">
                    <h2 class="chart-title mb-3">POI Map View</h2>
                    <div id="poi-map" style="height: 500px; border-radius: 8px;"></div>
                </div>
            </div>
        </section>

        <!-- POI List/Grid View -->
        <section id="poi-list" class="section">
            <div class="card chart-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="chart-title mb-0">Recommended POIs</h2>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-outline-primary active" id="view-grid">
                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M1 2.5A1.5 1.5 0 0 1 2.5 1h3A1.5 1.5 0 0 1 7 2.5v3A1.5 1.5 0 0 1 5.5 7h-3A1.5 1.5 0 0 1 1 5.5v-3zm8 0A1.5 1.5 0 0 1 10.5 1h3A1.5 1.5 0 0 1 15 2.5v3A1.5 1.5 0 0 1 13.5 7h-3A1.5 1.5 0 0 1 9 5.5v-3zm-8 8A1.5 1.5 0 0 1 2.5 9h3A1.5 1.5 0 0 1 7 10.5v3A1.5 1.5 0 0 1 5.5 15h-3A1.5 1.5 0 0 1 1 13.5v-3zm8 0A1.5 1.5 0 0 1 10.5 9h3a1.5 1.5 0 0 1 1.5 1.5v3a1.5 1.5 0 0 1-1.5 1.5h-3A1.5 1.5 0 0 1 9 13.5v-3z"/>
                                </svg>
                            </button>
                            <button type="button" class="btn btn-outline-primary" id="view-list">
                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div id="poi-results" class="row g-3">
                        <!-- POI cards will be inserted here -->
                        <div class="col-12 text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3 text-muted">Loading POI recommendations...</p>
                        </div>
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
    <script src="js/api.js"></script>
    <script src="js/poi.js"></script>
</body>
</html>
