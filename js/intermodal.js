/**
 * Intermodal Transfer Analysis Module
 * Analyzes bus coverage around MRT stations
 */

const IntermodalAnalysis = {
    map: null,
    stationMarker: null,
    radiusCircle: null,
    busStopMarkers: [],
    currentStation: null,
    currentRadius: 500,
    mrtIcon: null,
    busIcon: null,

    /**
     * Initialize the intermodal analysis module
     */
    async init() {
        try {
            // Initialize custom icons
            this.initIcons();

            // Initialize map
            this.initMap();

            // Load MRT stations list
            await this.loadMRTStations();

            // Setup event listeners
            this.setupEventListeners();

        } catch (error) {
            console.error('Intermodal Analysis initialization error:', error);
            this.showError('Failed to initialize intermodal analysis');
        }
    },

    /**
     * Initialize custom icons
     */
    initIcons() {
        this.mrtIcon = L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
        });

        this.busIcon = L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
            iconSize: [20, 33],
            iconAnchor: [10, 33],
            popupAnchor: [1, -28],
            shadowSize: [33, 33]
        });
    },

    /**
     * Initialize Leaflet map
     */
    initMap() {
        // Center on Singapore
        this.map = L.map('intermodal-map').setView([1.3521, 103.8198], 12);

        // Add tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(this.map);
    },

    /**
     * Load MRT stations list into dropdown
     */
    async loadMRTStations() {
        try {
            const response = await fetch('api.php?action=mrt_stations_list');
            const data = await response.json();

            console.log('MRT Stations API Response:', data);

            // Check if there's an error in the response
            if (data.error) {
                throw new Error(data.error);
            }

            // Check if data is an array
            if (!Array.isArray(data)) {
                throw new Error('Invalid response format: expected array');
            }

            if (data.length === 0) {
                throw new Error('No MRT stations found in database');
            }

            const selector = document.getElementById('station-selector');
            selector.innerHTML = '<option value="">Select an MRT station...</option>' +
                data.map(station =>
                    `<option value="${station.stop_id}" data-lat="${station.lat}" data-lng="${station.lng}">
                        ${station.stop_name}
                    </option>`
                ).join('');

        } catch (error) {
            console.error('Error loading MRT stations:', error);
            const selector = document.getElementById('station-selector');
            selector.innerHTML = '<option value="">Error loading stations</option>';

            // Show detailed error in map area
            this.showError('Failed to load MRT stations: ' + error.message);
        }
    },

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Station selector
        document.getElementById('station-selector').addEventListener('change', (e) => {
            if (e.target.value) {
                this.performAnalysis(e.target.value);
            }
        });

        // Radius slider
        const slider = document.getElementById('radius-slider');
        const radiusValue = document.getElementById('radius-value');

        slider.addEventListener('input', (e) => {
            this.currentRadius = parseInt(e.target.value);
            radiusValue.textContent = this.currentRadius + 'm';
        });

        slider.addEventListener('change', (e) => {
            if (this.currentStation) {
                this.performAnalysis(this.currentStation);
            }
        });
    },

    /**
     * Perform intermodal analysis
     */
    async performAnalysis(stationId) {
        try {
            this.currentStation = stationId;

            // Show loading state
            this.showLoading();

            // Fetch analysis data
            const response = await fetch(
                `api.php?action=intermodal_analysis&station_id=${stationId}&radius=${this.currentRadius}`
            );
            const data = await response.json();

            if (data.error) {
                throw new Error(data.error);
            }

            // Update UI
            this.updateMetrics(data);
            this.updateMap(data);
            this.updateTable(data);

        } catch (error) {
            console.error('Analysis error:', error);
            this.showError('Failed to perform analysis: ' + error.message);
        }
    },

    /**
     * Update metrics display
     */
    updateMetrics(data) {
        document.getElementById('intermodal-metrics').style.display = 'block';
        document.getElementById('metric-station-name').textContent = data.station.stop_name;
        document.getElementById('metric-bus-stops').textContent = data.total_bus_stops;
        document.getElementById('metric-bus-services').textContent = data.unique_bus_services;
        document.getElementById('metric-radius').textContent = data.radius + 'm';
    },

    /**
     * Update map with analysis results
     */
    updateMap(data) {
        // Clear existing markers and circles
        this.clearMap();

        const station = data.station;

        // Add MRT station marker
        this.stationMarker = L.marker([station.lat, station.lng], { icon: this.mrtIcon })
            .addTo(this.map)
            .bindPopup(`<b>${station.stop_name}</b><br>MRT Station`);

        // Add radius circle
        this.radiusCircle = L.circle([station.lat, station.lng], {
            radius: data.radius,
            color: '#ef4444',
            fillColor: '#fecaca',
            fillOpacity: 0.2,
            weight: 2
        }).addTo(this.map);

        // Add bus stop markers
        data.bus_stops.forEach((stop, index) => {
            const marker = L.marker([stop.lat, stop.lng], { icon: this.busIcon })
                .addTo(this.map)
                .bindPopup(this.createBusStopPopup(stop, index + 1));

            this.busStopMarkers.push(marker);
        });

        // Fit map to show all markers with more horizontal padding for better aesthetics
        if (data.bus_stops.length > 0) {
            const bounds = L.latLngBounds([
                [station.lat, station.lng],
                ...data.bus_stops.map(stop => [stop.lat, stop.lng])
            ]);
            // Padding: [vertical, horizontal] - more horizontal space for better look
            this.map.fitBounds(bounds, { padding: [60, 150] });
        } else {
            this.map.setView([station.lat, station.lng], 14);
        }

        // Hide loading overlay
        this.hideLoading();
    },

    /**
     * Create popup content for bus stop
     */
    createBusStopPopup(stop, rank) {
        const services = stop.services
            .map(s => s.service_no)
            .join(', ');

        return `
            <div style="min-width: 200px;">
                <b>#${rank} - ${stop.stop_name}</b><br>
                <small>Stop Code: ${stop.stop_code}</small><br>
                <small>Distance: ${stop.distance}m</small><br>
                <small>Services: ${services}</small>
            </div>
        `;
    },

    /**
     * Update results table
     */
    updateTable(data) {
        const container = document.getElementById('intermodal-results');
        const tbody = document.getElementById('intermodal-table-body');

        if (data.bus_stops.length === 0) {
            container.style.display = 'none';
            return;
        }

        container.style.display = 'block';

        tbody.innerHTML = data.bus_stops.map((stop, index) => `
            <tr>
                <td>${index + 1}</td>
                <td>${stop.stop_code}</td>
                <td>${stop.stop_name}</td>
                <td>${stop.distance}m</td>
                <td>${this.formatServices(stop.services)}</td>
            </tr>
        `).join('');
    },

    /**
     * Format services with directional info
     */
    formatServices(services) {
        if (services.length === 0) {
            return '<span class="text-muted">No services</span>';
        }

        return services.map(service => {
            return `
                <span style="display: inline-block; background: #e3f2fd; color: #1976d2; padding: 4px 10px; border-radius: 4px; font-size: 13px; margin: 2px; font-weight: 500;">
                    ${service.service_no}
                </span>
            `;
        }).join('');
    },

    /**
     * Clear map markers and circles
     */
    clearMap() {
        if (this.stationMarker) {
            this.map.removeLayer(this.stationMarker);
            this.stationMarker = null;
        }

        if (this.radiusCircle) {
            this.map.removeLayer(this.radiusCircle);
            this.radiusCircle = null;
        }

        this.busStopMarkers.forEach(marker => {
            this.map.removeLayer(marker);
        });
        this.busStopMarkers = [];
    },

    /**
     * Show loading state
     */
    showLoading() {
        const container = document.getElementById('intermodal-map');

        // Remove existing loading overlay if present
        const existingOverlay = container.querySelector('.loading-overlay');
        if (existingOverlay) {
            existingOverlay.remove();
        }

        // Add loading overlay on top of map (don't destroy the map!)
        const overlay = document.createElement('div');
        overlay.className = 'loading-overlay';
        overlay.style.cssText = 'position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.9); display: flex; align-items: center; justify-content: center; z-index: 1000;';
        overlay.innerHTML = '<div style="text-align: center; color: #6b7280;">Loading analysis...</div>';
        container.style.position = 'relative';
        container.appendChild(overlay);
    },

    hideLoading() {
        const container = document.getElementById('intermodal-map');
        const overlay = container.querySelector('.loading-overlay');
        if (overlay) {
            overlay.remove();
        }
    },

    /**
     * Show error message
     */
    showError(message) {
        // Hide loading overlay first
        this.hideLoading();

        // Show error in console
        console.error('Intermodal Analysis Error:', message);

        // Optionally show alert to user
        alert('Error: ' + message);
    }
};
