<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<?php include "inc/head.inc.php"; ?>
<body>
    <header class="dashboard-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="dashboard-title">YourTrip Analytics Admin Dashboard</h1>
                    <p class="dashboard-subtitle">Singapore Transport Data</p>
                </div>
            <?php include "inc/navbar.inc.php"; ?>
            </div>
        </div>
    </header>
    <!-- Main Content -->
    <main class="container dashboard-container">

        <!-- Top Metrics Cards (All Transport Modes) -->
        <section id="top-metrics" class="section">
            <div class="row g-3">
                <div class="col-md">
                    <div class="metric-card" data-scroll-to="ridership-trends">
                        <div class="metric-label">MOST POPULAR MODE</div>
                        <div class="metric-value" id="metric-popular-mode">Loading...</div>
                        <div class="metric-description">Highest total ridership</div>
                    </div>
                </div>
                <div class="col-md">
                    <div class="metric-card" data-scroll-to="ridership-trends">
                        <div class="metric-label">PEAK MONTH</div>
                        <div class="metric-value" id="metric-peak-month">Loading...</div>
                        <div class="metric-description">Highest combined ridership</div>
                    </div>
                </div>
                <div class="col-md">
                    <div class="metric-card" data-scroll-to="busiest-stops">
                        <div class="metric-label">TRANSPORT HUBS</div>
                        <div class="metric-value" id="metric-hubs">Loading...</div>
                        <div class="metric-description">Bus stops + MRT stations</div>
                    </div>
                </div>
                <div class="col-md">
                    <div class="metric-card" data-scroll-to="ridership-trends">
                        <div class="metric-label">DAILY RIDERSHIP</div>
                        <div class="metric-value" id="metric-daily">Loading...</div>
                        <div class="metric-description">Average daily passengers</div>
                    </div>
                </div>
                <div class="col-md">
                    <div class="metric-card">
                        <div class="metric-label">TRANSPORT MODES</div>
                        <div class="metric-value" id="metric-modes">Loading...</div>
                        <div class="metric-description">MRT, LRT, Public Bus</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Transport Mode Ridership Trends -->
        <section id="ridership-trends" class="section">
            <div class="card chart-card">
                <div class="card-body">
                    <div class="chart-header">
                        <h2 class="chart-title">Transport Mode Ridership Trends</h2>
                        <div class="chart-controls">
                            <label for="mode-selector" class="year-label">Mode:</label>
                            <select id="mode-selector" class="year-selector">
                                <option value="">Loading...</option>
                            </select>
                            <label for="year-selector" class="year-label" style="margin-left: 15px;">Year:</label>
                            <select id="year-selector" class="year-selector">
                                <option value="">Loading...</option>
                            </select>
                        </div>
                    </div>
                    <div id="ridership-chart" class="chart-container">
                        <div class="loading-spinner">Loading chart...</div>
                    </div>
                    <div class="chart-legend" id="ridership-legend"></div>
                </div>
            </div>
        </section>

        <!-- Intermodal Transfer Analysis -->
        <section id="intermodal-analysis" class="section">
            <div class="card chart-card">
                <div class="card-body">
                    <h2 class="chart-title">Intermodal Transfer Analysis</h2>
                    <p style="color: #6b7280; margin-bottom: 20px;">Assess bus coverage around MRT stations</p>

                    <!-- Controls -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="station-selector" class="year-label">Select MRT Station:</label>
                            <select id="station-selector" class="year-selector" style="width: 100%;">
                                <option value="">Loading stations...</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="radius-slider" class="year-label">
                                Search Radius: <span id="radius-value" style="color: #4285f4; font-weight: 600;">500m</span>
                            </label>
                            <input type="range" class="form-range" id="radius-slider"
                                   min="200" max="1000" step="50" value="500" style="width: 100%;">
                            <div class="d-flex justify-content-between" style="font-size: 11px; color: #9ca3af;">
                                <span>200m</span>
                                <span>1000m</span>
                            </div>
                        </div>
                    </div>

                    <!-- Metrics Display -->
                    <div id="intermodal-metrics" style="display: none; margin-bottom: 20px;">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 4px solid #ef4444;">
                                    <div style="font-size: 12px; color: #6b7280; text-transform: uppercase; font-weight: 600;">MRT Station</div>
                                    <div style="font-size: 18px; font-weight: 700; color: #202124; margin-top: 5px;" id="metric-station-name">-</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 4px solid #4285f4;">
                                    <div style="font-size: 12px; color: #6b7280; text-transform: uppercase; font-weight: 600;">Bus Stops Found</div>
                                    <div style="font-size: 24px; font-weight: 700; color: #202124; margin-top: 5px;" id="metric-bus-stops">0</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 4px solid #34a853;">
                                    <div style="font-size: 12px; color: #6b7280; text-transform: uppercase; font-weight: 600;">Unique Bus Services</div>
                                    <div style="font-size: 24px; font-weight: 700; color: #202124; margin-top: 5px;" id="metric-bus-services">0</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 4px solid #fbbc04;">
                                    <div style="font-size: 12px; color: #6b7280; text-transform: uppercase; font-weight: 600;">Search Radius</div>
                                    <div style="font-size: 24px; font-weight: 700; color: #202124; margin-top: 5px;" id="metric-radius">500m</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Map -->
                    <div id="intermodal-map" class="chart-container" style="height: 450px; border-radius: 8px;">
                        <div class="loading-spinner">Select an MRT station to begin analysis</div>
                    </div>

                    <!-- Results Table -->
                    <div id="intermodal-results" style="display: none; margin-top: 20px; max-height: 400px; overflow-y: auto;">
                        <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 15px;">Bus Stops Within Radius</h3>
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Bus Stop Code</th>
                                    <th>Bus Stop Name</th>
                                    <th>Distance</th>
                                    <th>Bus Services</th>
                                </tr>
                            </thead>
                            <tbody id="intermodal-table-body">
                                <tr>
                                    <td colspan="5" class="text-center">No data available</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- Bus Network Analysis Section Header -->
        <section class="section-header">
            <h2 class="section-title">Bus Network Analysis</h2>
        </section>

        <!-- Bus Metrics Cards -->
        <section id="bus-metrics" class="section">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="metric-card metric-card-secondary">
                        <div class="metric-label">TOTAL BUS ROUTES</div>
                        <div class="metric-value" id="bus-total-routes">Loading...</div>
                        <div class="metric-description">Distinct bus services</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card metric-card-secondary">
                        <div class="metric-label">SINGLE-OPERATOR STOPS</div>
                        <div class="metric-value" id="bus-single-operator">Loading...</div>
                        <div class="metric-description">Served by one operator</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card metric-card-secondary">
                        <div class="metric-label">ABOVE-AVG ROUTES</div>
                        <div class="metric-value" id="bus-above-avg">Loading...</div>
                        <div class="metric-description">Higher than average ridership</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card metric-card-secondary">
                        <div class="metric-label">PEAK HOUR USAGE</div>
                        <div class="metric-value" id="bus-peak-usage">Loading...</div>
                        <div class="metric-description">7-9 AM, 5-7 PM ridership</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Peak vs Off-Peak Bar Chart -->
        <section id="peak-offpeak" class="section">
            <div class="card chart-card">
                <div class="card-body">
                    <h2 class="chart-title">Peak vs Off-Peak Ridership</h2>
                    <div id="peak-offpeak-chart" class="chart-container">
                        <div class="loading-spinner">Loading chart...</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Hourly Ridership Heatmap -->
        <section id="hourly-heatmap" class="section">
            <div class="card chart-card">
                <div class="card-body">
                    <h2 class="chart-title">Hourly Ridership Pattern</h2>
                    <div id="hourly-heatmap-chart" class="chart-container">
                        <div class="loading-spinner">Loading heatmap...</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Top 10 Busiest Bus Stops -->
        <section id="busiest-stops" class="section">
            <div class="card chart-card">
                <div class="card-body">
                    <h2 class="chart-title">Top 10 Busiest Bus Stops</h2>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="table-container">
                                <table class="table table-hover" id="busiest-stops-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Stop ID</th>
                                            <th>Stop Name</th>
                                            <th>Total Boarding</th>
                                            <th>Location</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="5" class="text-center">Loading...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div id="stops-map" class="map-container"></div>
                            <div class="map-legend">
                                <span class="legend-item"><span class="legend-dot" style="background: #ef4444;"></span> Rank 1 (Busiest)</span>
                                <span class="legend-item"><span class="legend-dot" style="background: #f97316;"></span> Rank 2</span>
                                <span class="legend-item"><span class="legend-dot" style="background: #eab308;"></span> Rank 3</span>
                                <span class="legend-item"><span class="legend-dot" style="background: #22c55e;"></span> Rank 4-10</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    </main>

    <?php include "inc/footer.inc.php"?>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <!-- Custom JavaScript Modules -->
    <script src="js/api.js"></script>
    <script src="js/metrics.js"></script>
    <script src="js/charts.js"></script>
    <script src="js/map.js"></script>
    <script src="js/intermodal.js"></script>
    <script src="js/main.js"></script>
</body>
</html>
