/**
 * Transport Stops Page - Main Module
 * Displays MRT & Bus stops with multilingual support
 */

// Global state
let allStops = [];
let filteredStops = [];
let transportMap = null;
let markersLayer = null;
let currentStopType = 'mrt';

/**
 * Initialize the transport stops page
 */
async function initTransportStopsPage() {
    try {
        // Initialize map first
        initMap();

        // Initialize filters
        initFilters();

        // Load initial data (MRT stations)
        await loadTransportStops('mrt');

    } catch (error) {
        console.error('Error initializing transport stops page:', error);
        showError('Failed to load transport stops data.');
    }
}

/**
 * Initialize Leaflet map
 */
function initMap() {
    // Initialize map centered on Singapore
    transportMap = L.map('transport-map').setView([1.3521, 103.8198], 12);

    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 18
    }).addTo(transportMap);

    // Create markers layer
    markersLayer = L.layerGroup().addTo(transportMap);
}

/**
 * Load transport stops from multilingual API
 */
async function loadTransportStops(stopType) {
    try {
        const currentLanguage = getCurrentLanguage();
        console.log(`Loading ${stopType} stops in language: ${currentLanguage}`);

        const response = await MultilingualAPI.getStops(stopType, currentLanguage);

        if (!response || !response.stops) {
            throw new Error('No stops data received');
        }

        allStops = response.stops;
        filteredStops = [...allStops];
        currentStopType = stopType;

        console.log(`Loaded ${allStops.length} ${stopType} stops`);

        // Update metrics
        updateMetrics();

        // Update map and list
        updateMapMarkers();
        renderStopsList();

    } catch (error) {
        console.error('Error loading transport stops:', error);
        showError('Failed to load transport stops. Please try again.');
    }
}

/**
 * Update metrics cards
 */
function updateMetrics() {
    const mrtCount = currentStopType === 'mrt' ? allStops.length : 0;
    const busCount = currentStopType === 'bus' ? allStops.length : 0;

    document.getElementById('metric-mrt-count').textContent = mrtCount.toLocaleString();
    document.getElementById('metric-bus-count').textContent = busCount.toLocaleString();

    const langNames = {
        'en': 'English',
        'zh': '中文',
        'ta': 'தமிழ்',
        'ms': 'Bahasa'
    };
    const currentLang = getCurrentLanguage();
    document.getElementById('metric-current-lang').textContent = langNames[currentLang] || 'English';
}

/**
 * Update map markers
 */
function updateMapMarkers() {
    if (!markersLayer || !transportMap) {
        console.error('Map not initialized');
        return;
    }

    // Clear existing markers
    markersLayer.clearLayers();

    console.log(`Adding ${filteredStops.length} markers to map`);

    let markersAdded = 0;

    filteredStops.forEach((stop) => {
        if (stop.lat && stop.lng) {
            const marker = L.marker([stop.lat, stop.lng], {
                icon: getMarkerIcon(stop.stop_type)
            });

            // Create popup content
            const popupContent = `
                <div style="min-width: 200px;">
                    <h6 style="margin: 0 0 8px 0; color: #1a73e8;">${stop.name}</h6>
                    <div style="font-size: 12px; color: #5f6368;">
                        <div style="margin-bottom: 4px;">
                            <strong>Stop ID:</strong> ${stop.stop_id}
                        </div>
                        <div style="margin-bottom: 4px;">
                            <strong>Type:</strong> ${stop.stop_type.toUpperCase()}
                        </div>
                        <div>
                            <strong>Coordinates:</strong> ${stop.lat.toFixed(4)}, ${stop.lng.toFixed(4)}
                        </div>
                    </div>
                </div>
            `;

            marker.bindPopup(popupContent);
            marker.addTo(markersLayer);
            markersAdded++;
        }
    });

    console.log(`Added ${markersAdded} markers`);

    // Fit map to markers
    if (markersAdded > 0) {
        const validStops = filteredStops.filter(stop => stop.lat && stop.lng);
        if (validStops.length > 0) {
            const lats = validStops.map(stop => stop.lat);
            const lngs = validStops.map(stop => stop.lng);

            const bounds = L.latLngBounds(
                [Math.min(...lats), Math.min(...lngs)],
                [Math.max(...lats), Math.max(...lngs)]
            );

            transportMap.fitBounds(bounds, { padding: [50, 50] });
        }
    }
}

/**
 * Get custom marker icon based on stop type
 */
function getMarkerIcon(stopType) {
    const colors = {
        'mrt': '#ea4335',  // Red for MRT
        'bus': '#1a73e8'   // Blue for Bus
    };

    const color = colors[stopType] || '#1a73e8';

    return L.divIcon({
        className: 'custom-marker',
        html: `<div style="background-color: ${color}; width: 24px; height: 24px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>`,
        iconSize: [24, 24],
        iconAnchor: [12, 12]
    });
}

/**
 * Render stops list table
 */
function renderStopsList() {
    const tbody = document.getElementById('transport-results-body');

    if (filteredStops.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="4" class="text-center py-5 text-muted">
                    No transport stops found.
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = filteredStops.map(stop => `
        <tr>
            <td><strong>${stop.stop_id}</strong></td>
            <td>${stop.name}</td>
            <td><span class="badge bg-${stop.stop_type === 'mrt' ? 'danger' : 'primary'}">${stop.stop_type.toUpperCase()}</span></td>
            <td>${stop.lat.toFixed(4)}, ${stop.lng.toFixed(4)}</td>
        </tr>
    `).join('');
}

/**
 * Initialize filter controls
 */
function initFilters() {
    const stopTypeFilter = document.getElementById('stop-type-filter');
    const searchInput = document.getElementById('search-input');

    // Stop type filter
    stopTypeFilter.addEventListener('change', async () => {
        const selectedType = stopTypeFilter.value;

        if (selectedType === 'all') {
            // Load both MRT and bus stops
            await loadBothTypes();
        } else {
            await loadTransportStops(selectedType);
        }
    });

    // Search input with debounce
    let searchTimeout;
    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(applySearchFilter, 300);
    });
}

/**
 * Load both MRT and bus stops
 */
async function loadBothTypes() {
    try {
        const currentLanguage = getCurrentLanguage();
        console.log(`Loading both MRT and bus stops in language: ${currentLanguage}`);

        const [mrtResponse, busResponse] = await Promise.all([
            MultilingualAPI.getStops('mrt', currentLanguage),
            MultilingualAPI.getStops('bus', currentLanguage)
        ]);

        const mrtStops = mrtResponse?.stops || [];
        const busStops = busResponse?.stops || [];

        allStops = [...mrtStops, ...busStops];
        filteredStops = [...allStops];
        currentStopType = 'all';

        console.log(`Loaded ${mrtStops.length} MRT + ${busStops.length} bus stops`);

        // Update metrics
        document.getElementById('metric-mrt-count').textContent = mrtStops.length.toLocaleString();
        document.getElementById('metric-bus-count').textContent = busStops.length.toLocaleString();

        // Update map and list
        updateMapMarkers();
        renderStopsList();

    } catch (error) {
        console.error('Error loading both types:', error);
        showError('Failed to load transport stops. Please try again.');
    }
}

/**
 * Apply search filter
 */
function applySearchFilter() {
    const searchTerm = document.getElementById('search-input').value.toLowerCase();

    if (!searchTerm) {
        filteredStops = [...allStops];
    } else {
        filteredStops = allStops.filter(stop => {
            const searchable = `${stop.name} ${stop.stop_id} ${stop.stop_type}`.toLowerCase();
            return searchable.includes(searchTerm);
        });
    }

    console.log(`Search filter: ${allStops.length} -> ${filteredStops.length} stops`);

    // Update UI
    updateMapMarkers();
    renderStopsList();
}

/**
 * Show error message
 */
function showError(message) {
    const tbody = document.getElementById('transport-results-body');
    tbody.innerHTML = `
        <tr>
            <td colspan="4" class="text-center py-5">
                <div class="alert alert-warning mb-0">
                    <h5 class="alert-heading">Error</h5>
                    <p>${message}</p>
                </div>
            </td>
        </tr>
    `;
}

/**
 * Reload data when language changes
 */
async function reloadTransportData(language) {
    console.log('Reloading transport data for language:', language);

    const stopTypeFilter = document.getElementById('stop-type-filter');
    const selectedType = stopTypeFilter.value;

    if (selectedType === 'all') {
        await loadBothTypes();
    } else {
        await loadTransportStops(selectedType);
    }

    // Update language metric
    const langNames = {
        'en': 'English',
        'zh': '中文',
        'ta': 'தமிழ்',
        'ms': 'Bahasa'
    };
    document.getElementById('metric-current-lang').textContent = langNames[language] || 'English';
}

// Export for use by multilingual.js
window.reloadMultilingualData = reloadTransportData;

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', initTransportStopsPage);
