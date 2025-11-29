/**
 * POI Recommendations Page - Main Module
 * Handles POI data loading, filtering, and visualization
 */

// Global state
let allPOIs = [];
let filteredPOIs = [];
let poiMap = null;
let markersLayer = null;

/**
 * Initialize the POI page
 */
async function initPOIPage() {
    try {
        // Load all POI data
        await loadPOIData();

        // Initialize components
        initMetrics();
        initMap();
        initFilters();
        renderPOIList();
        renderCategoryChart();
        renderTopRated();

    } catch (error) {
        console.error('Error initializing POI page:', error);
        showError('Failed to load POI data. The database may not be populated yet.');
    }
}

/**
 * Load POI data from API
 */
async function loadPOIData() {
    try {
        const data = await API.getPOIs();
        allPOIs = data;
        filteredPOIs = [...allPOIs];

        // Show placeholder if no data
        if (allPOIs.length === 0) {
            showNoDataMessage();
        }
    } catch (error) {
        console.error('Error loading POI data:', error);
        allPOIs = generateMockData(); // Use mock data if API fails
        filteredPOIs = [...allPOIs];
    }
}

/**
 * Initialize metrics cards
 */
function initMetrics() {
    const totalPOIs = allPOIs.length;
    const categories = new Set(allPOIs.map(poi => poi.category)).size;
    const avgRating = allPOIs.length > 0
        ? (allPOIs.reduce((sum, poi) => sum + (poi.rating || 0), 0) / allPOIs.length).toFixed(1)
        : '-';
    const hubsWithPOIs = new Set(allPOIs.map(poi => poi.nearest_hub)).size;

    document.getElementById('metric-total-pois').textContent = totalPOIs.toLocaleString();
    document.getElementById('metric-categories').textContent = categories;
    document.getElementById('metric-avg-rating').textContent = avgRating !== '-' ? `${avgRating} ★` : '-';
    document.getElementById('metric-nearby-hubs').textContent = hubsWithPOIs.toLocaleString();
}

/**
 * Initialize Leaflet map
 */
function initMap() {
    // Initialize map centered on Singapore
    poiMap = L.map('poi-map').setView([1.3521, 103.8198], 12);

    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 18
    }).addTo(poiMap);

    // Create markers layer
    markersLayer = L.layerGroup().addTo(poiMap);

    // Add markers for POIs
    updateMapMarkers();
}

/**
 * Update map markers based on filtered POIs
 */
function updateMapMarkers() {
    console.log('updateMapMarkers called with', filteredPOIs.length, 'POIs');

    if (!markersLayer) {
        console.error('markersLayer is not initialized');
        return;
    }

    // Clear existing markers
    markersLayer.clearLayers();
    console.log('Cleared existing markers');

    // Add markers for filtered POIs
    let markersAdded = 0;
    filteredPOIs.forEach((poi) => {
        if (poi.latitude && poi.longitude) {
            const marker = L.marker([poi.latitude, poi.longitude], {
                icon: getMarkerIcon(poi.category)
            });

            // Create popup content
            const displayHub = poi.translatedHub || poi.nearest_hub;
            const popupContent = `
                <div style="min-width: 200px;">
                    <h6 style="margin: 0 0 8px 0; color: #1a73e8;">${poi.name}</h6>
                    <div style="font-size: 12px; color: #5f6368;">
                        <div style="margin-bottom: 4px;">
                            <strong>Category:</strong> ${poi.category}
                        </div>
                        ${poi.rating ? `
                            <div style="margin-bottom: 4px;">
                                <strong>Rating:</strong> ${poi.rating} ★
                            </div>
                        ` : ''}
                        ${poi.distance_to_hub ? `
                            <div style="margin-bottom: 4px;">
                                <strong>Distance:</strong> ${poi.distance_to_hub}m to hub
                            </div>
                        ` : ''}
                        ${displayHub ? `
                            <div>
                                <strong>Nearest Hub:</strong> ${displayHub}
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;

            marker.bindPopup(popupContent);
            marker.addTo(markersLayer);
            markersAdded++;
        }
    });

    console.log('Added', markersAdded, 'markers to map');

    // Fit map to markers if there are any
    if (markersAdded > 0) {
        // Calculate bounds manually from filtered POIs
        const validPOIs = filteredPOIs.filter(poi => poi.latitude && poi.longitude);
        if (validPOIs.length > 0) {
            const lats = validPOIs.map(poi => poi.latitude);
            const lngs = validPOIs.map(poi => poi.longitude);

            const bounds = L.latLngBounds(
                [Math.min(...lats), Math.min(...lngs)],
                [Math.max(...lats), Math.max(...lngs)]
            );

            poiMap.fitBounds(bounds, { padding: [50, 50] });
            console.log('Map bounds fitted to', validPOIs.length, 'markers');
        }
    }
}

/**
 * Get custom marker icon based on category
 */
function getMarkerIcon(category) {
    const colors = {
        'Food & Dining': '#ea4335',
        'Shopping': '#fbbc04',
        'Entertainment': '#9c27b0',
        'Education': '#1a73e8',
        'Healthcare': '#34a853',
        'Parks & Recreation': '#0f9d58',
        'Cultural': '#ff6d00',
        'Services': '#5f6368'
    };

    const color = colors[category] || '#1a73e8';

    return L.divIcon({
        className: 'custom-marker',
        html: `<div style="background-color: ${color}; width: 24px; height: 24px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>`,
        iconSize: [24, 24],
        iconAnchor: [12, 12]
    });
}

/**
 * Initialize filter controls
 */
function initFilters() {
    const categoryFilter = document.getElementById('category-filter');
    const distanceFilter = document.getElementById('distance-filter');
    const searchInput = document.getElementById('search-input');

    // Category filter
    categoryFilter.addEventListener('change', applyFilters);

    // Distance filter
    distanceFilter.addEventListener('change', applyFilters);

    // Search input with debounce
    let searchTimeout;
    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(applyFilters, 300);
    });

    // View toggle buttons
    document.getElementById('view-grid').addEventListener('click', () => {
        setViewMode('grid');
    });
    document.getElementById('view-list').addEventListener('click', () => {
        setViewMode('list');
    });
}

/**
 * Apply all filters to POI list
 */
async function applyFilters() {
    const category = document.getElementById('category-filter').value;
    const maxDistance = document.getElementById('distance-filter').value;
    const searchTerm = document.getElementById('search-input').value.toLowerCase();

    console.log('Applying filters:', { category, maxDistance, searchTerm });

    // If category filter is used, fetch from API
    if (category) {
        try {
            console.log('Fetching POIs for category:', category);
            const categoryData = await API.getPOIsByCategory(category);
            console.log('Received POIs:', categoryData.length);
            filteredPOIs = categoryData;
        } catch (error) {
            console.error('Error fetching POIs by category:', error);
            // Fallback to client-side filtering
            filteredPOIs = allPOIs.filter(poi => poi.category === category);
        }
    } else {
        // No category filter, use all POIs
        filteredPOIs = [...allPOIs];
    }

    // Apply distance filter (client-side)
    if (maxDistance) {
        const beforeDistance = filteredPOIs.length;
        filteredPOIs = filteredPOIs.filter(poi =>
            poi.distance_to_hub && poi.distance_to_hub <= parseInt(maxDistance)
        );
        console.log(`Distance filter: ${beforeDistance} -> ${filteredPOIs.length} POIs`);
    }

    // Apply search filter (client-side)
    if (searchTerm) {
        const beforeSearch = filteredPOIs.length;
        filteredPOIs = filteredPOIs.filter(poi => {
            const searchable = `${poi.name} ${poi.category} ${poi.location || ''} ${poi.nearest_hub || ''}`.toLowerCase();
            return searchable.includes(searchTerm);
        });
        console.log(`Search filter: ${beforeSearch} -> ${filteredPOIs.length} POIs`);
    }

    console.log('Final filtered POIs:', filteredPOIs.length);

    // Update UI
    renderPOIList();
    updateMapMarkers();
}

/**
 * Render POI list/grid
 */
function renderPOIList() {
    const container = document.getElementById('poi-results');

    if (filteredPOIs.length === 0) {
        container.innerHTML = `
            <div class="col-12 text-center py-5">
                <svg width="64" height="64" fill="currentColor" class="text-muted mb-3" viewBox="0 0 16 16">
                    <path d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10zm0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6z"/>
                </svg>
                <p class="text-muted">No POIs found matching your criteria.</p>
                <button class="btn btn-primary btn-sm" onclick="clearFilters()">Clear Filters</button>
            </div>
        `;
        return;
    }

    const viewMode = document.getElementById('view-grid').classList.contains('active') ? 'grid' : 'list';

    if (viewMode === 'grid') {
        container.innerHTML = filteredPOIs.map(poi => createPOICard(poi)).join('');
    } else {
        container.innerHTML = `
            <div class="col-12">
                <div class="list-group">
                    ${filteredPOIs.map(poi => createPOIListItem(poi)).join('')}
                </div>
            </div>
        `;
    }
}

/**
 * Create POI card (grid view)
 */
function createPOICard(poi) {
    const displayHub = poi.translatedHub || poi.nearest_hub || 'transport hub';
    return `
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 poi-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h5 class="card-title mb-0">${poi.name}</h5>
                        ${poi.rating ? `<span class="badge bg-warning text-dark">${poi.rating} ★</span>` : ''}
                    </div>
                    <p class="card-text text-muted mb-2">
                        <small>${poi.category}</small>
                    </p>
                    ${poi.description ? `<p class="card-text">${poi.description}</p>` : ''}
                    <div class="mt-auto">
                        ${poi.distance_to_hub ? `
                            <p class="mb-1"><small class="text-muted">
                                <svg width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8z"/>
                                </svg>
                                ${poi.distance_to_hub}m from ${displayHub}
                            </small></p>
                        ` : ''}
                    </div>
                </div>
            </div>
        </div>
    `;
}

/**
 * Create POI list item (list view)
 */
function createPOIListItem(poi) {
    const displayHub = poi.translatedHub || poi.nearest_hub || 'transport hub';
    return `
        <div class="list-group-item">
            <div class="d-flex w-100 justify-content-between align-items-center">
                <div>
                    <h6 class="mb-1">${poi.name}</h6>
                    <p class="mb-1 text-muted"><small>${poi.category}</small></p>
                    ${poi.distance_to_hub ? `
                        <small class="text-muted">${poi.distance_to_hub}m from ${displayHub}</small>
                    ` : ''}
                </div>
                ${poi.rating ? `<span class="badge bg-warning text-dark">${poi.rating} ★</span>` : ''}
            </div>
        </div>
    `;
}

/**
 * Set view mode (grid or list)
 */
function setViewMode(mode) {
    const gridBtn = document.getElementById('view-grid');
    const listBtn = document.getElementById('view-list');

    if (mode === 'grid') {
        gridBtn.classList.add('active');
        listBtn.classList.remove('active');
    } else {
        listBtn.classList.add('active');
        gridBtn.classList.remove('active');
    }

    renderPOIList();
}

/**
 * Clear all filters
 */
function clearFilters() {
    document.getElementById('category-filter').value = '';
    document.getElementById('distance-filter').value = '';
    document.getElementById('search-input').value = '';
    applyFilters();
}

/**
 * Render category distribution chart
 */
function renderCategoryChart() {
    const chartContainer = document.getElementById('category-chart');
    if (!chartContainer) return; // Element doesn't exist on this page

    const categoryCounts = {};
    allPOIs.forEach(poi => {
        categoryCounts[poi.category] = (categoryCounts[poi.category] || 0) + 1;
    });

    const categories = Object.keys(categoryCounts).sort((a, b) => categoryCounts[b] - categoryCounts[a]);
    const maxCount = Math.max(...Object.values(categoryCounts));

    const chartHTML = categories.map(category => {
        const count = categoryCounts[category];
        const percentage = ((count / allPOIs.length) * 100).toFixed(1);
        const width = (count / maxCount * 100).toFixed(1);

        return `
            <div style="margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                    <span style="font-size: 14px; color: #202124;">${category}</span>
                    <span style="font-size: 14px; color: #5f6368;">${count} (${percentage}%)</span>
                </div>
                <div style="background-color: #e0e0e0; height: 24px; border-radius: 4px; overflow: hidden;">
                    <div style="background-color: #1a73e8; height: 100%; width: ${width}%; transition: width 0.3s;"></div>
                </div>
            </div>
        `;
    }).join('');

    chartContainer.innerHTML = chartHTML || '<p class="text-muted text-center">No data available</p>';
}

/**
 * Render top rated POIs table
 */
function renderTopRated() {
    const tableBody = document.getElementById('top-rated-body');
    if (!tableBody) return; // Element doesn't exist on this page

    const topRated = [...allPOIs]
        .filter(poi => poi.rating)
        .sort((a, b) => b.rating - a.rating)
        .slice(0, 10);

    if (topRated.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center py-4 text-muted">
                    No rated POIs available yet.
                </td>
            </tr>
        `;
        return;
    }

    tableBody.innerHTML = topRated.map((poi, index) => {
        const displayHub = poi.translatedHub || poi.nearest_hub || '-';
        return `
            <tr>
                <td>
                    <span class="badge ${index < 3 ? 'bg-warning text-dark' : 'bg-secondary'}">${index + 1}</span>
                </td>
                <td><strong>${poi.name}</strong></td>
                <td>${poi.category}</td>
                <td>${poi.rating} ★</td>
                <td>${poi.distance_to_hub ? poi.distance_to_hub + 'm' : '-'}</td>
                <td>${displayHub}</td>
            </tr>
        `;
    }).join('');
}

/**
 * Show error message
 */
function showError(message) {
    const container = document.getElementById('poi-results');
    container.innerHTML = `
        <div class="col-12">
            <div class="alert alert-warning" role="alert">
                <h5 class="alert-heading">No Data Available</h5>
                <p>${message}</p>
                <hr>
                <p class="mb-0">Sample data is shown below for demonstration purposes.</p>
            </div>
        </div>
    `;
}

/**
 * Show no data message
 */
function showNoDataMessage() {
    document.getElementById('poi-results').innerHTML = `
        <div class="col-12 text-center py-5">
            <svg width="64" height="64" fill="currentColor" class="text-muted mb-3" viewBox="0 0 16 16">
                <path d="M8 1a2.5 2.5 0 0 1 2.5 2.5V4h-5v-.5A2.5 2.5 0 0 1 8 1zm3.5 3v-.5a3.5 3.5 0 1 0-7 0V4H1v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V4h-3.5z"/>
            </svg>
            <h5 class="text-muted">POI Data Not Yet Available</h5>
            <p class="text-muted">The POI database is currently being populated. Please check back later.</p>
        </div>
    `;
}

/**
 * Update transport stop names (MRT/Bus) with translations
 * Keep business/POI names in English
 */
async function updateTransportStopNames(language) {
    console.log('Updating transport stop names to language:', language);

    try {
        // Fetch translated MRT stations from multilingual API
        const response = await MultilingualAPI.getStops('mrt', language);

        if (!response || !response.stops) {
            console.warn('No MRT station translations available');
            return;
        }

        // Create translation map: English name (uppercase) -> Translated name
        const translationMap = {};
        response.stops.forEach(stop => {
            if (stop.names && stop.names.en) {
                // Map both with and without "MRT Station" suffix
                const englishName = stop.names.en.toUpperCase();
                translationMap[englishName] = stop.name;

                // Also map without "MRT Station" for matching
                const baseName = englishName.replace(' MRT STATION', '').replace(' MRT', '');
                translationMap[baseName] = stop.name;
            }
        });

        console.log('Translation map created with', Object.keys(translationMap).length, 'entries');

        // Update both allPOIs and filteredPOIs
        [allPOIs, filteredPOIs].forEach(poiList => {
            poiList.forEach(poi => {
                // Check if nearest_hub is a transport stop (contains MRT, LRT, or Bus)
                if (poi.nearest_hub &&
                    (poi.nearest_hub.includes('MRT') ||
                     poi.nearest_hub.includes('LRT') ||
                     poi.nearest_hub.includes('Bus'))) {

                    const upperHub = poi.nearest_hub.toUpperCase();
                    const baseHub = upperHub.replace(' MRT STATION', '').replace(' MRT', '');

                    // Try to find translation
                    const translated = translationMap[upperHub] || translationMap[baseHub];

                    if (translated && translated !== poi.nearest_hub) {
                        console.log(`Translating: ${poi.nearest_hub} -> ${translated}`);
                        poi.translatedHub = translated;
                    } else {
                        poi.translatedHub = poi.nearest_hub;
                    }
                } else {
                    // Keep non-transport locations in English
                    poi.translatedHub = poi.nearest_hub;
                }
            });
        });

        // Re-render the UI with updated translations
        updateMapMarkers();
        renderPOIList();

        console.log('Transport stop names updated successfully');

    } catch (error) {
        console.error('Error updating transport stop names:', error);
        // On error, keep original names
        [allPOIs, filteredPOIs].forEach(poiList => {
            poiList.forEach(poi => {
                poi.translatedHub = poi.nearest_hub;
            });
        });
    }
}

/**
 * Generate mock data for demonstration
 */
function generateMockData() {
    const categories = ['Food & Dining', 'Shopping', 'Entertainment', 'Education', 'Healthcare', 'Parks & Recreation', 'Cultural', 'Services'];
    const nearestHubs = ['Orchard MRT', 'Raffles Place MRT', 'Marina Bay Sands', 'Bugis MRT', 'Jurong East MRT'];
    const mockPOIs = [];

    const baseCoords = [
        { lat: 1.3048, lng: 103.8318 }, // Orchard
        { lat: 1.2839, lng: 103.8510 }, // Raffles Place
        { lat: 1.2834, lng: 103.8607 }, // Marina Bay
        { lat: 1.3001, lng: 103.8463 }, // Bugis
        { lat: 1.3329, lng: 103.7436 }  // Jurong East
    ];

    for (let i = 0; i < 50; i++) {
        const baseIndex = i % baseCoords.length;
        const base = baseCoords[baseIndex];

        mockPOIs.push({
            id: i + 1,
            name: `Sample POI ${i + 1}`,
            category: categories[i % categories.length],
            rating: (Math.random() * 2 + 3).toFixed(1),
            distance_to_hub: Math.floor(Math.random() * 1500) + 100,
            nearest_hub: nearestHubs[baseIndex],
            latitude: base.lat + (Math.random() - 0.5) * 0.01,
            longitude: base.lng + (Math.random() - 0.5) * 0.01,
            description: 'Sample POI for demonstration purposes. Actual data will be loaded from the database.',
            location: `Sample Location ${i + 1}`
        });
    }

    return mockPOIs;
}

// Export updateTransportStopNames for use by multilingual.js
window.updateTransportStopNames = updateTransportStopNames;

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', initPOIPage);
