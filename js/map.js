/**
 * Map Module - Leaflet map handling for bus stops
 * Enhanced with better table styling and color-coded markers
 */

const MapModule = {
    map: null,
    markers: [],
    selectedMarker: null,
    stopsData: [],
    selectedStopIndex: null,

    /**
     * Initialize busiest stops table and map
     */
    async initBusiestStops() {
        try {
            const data = await API.getBusiestStops(10);

            if (!data || data.length === 0) {
                displayError('busiest-stops-table', 'No bus stop data available');
                return;
            }

            this.stopsData = data;
            this.renderEnhancedTable(data);
            this.initMap(data);

        } catch (error) {
            displayError('busiest-stops-table', error.message);
            console.error('Error loading busiest stops:', error);
        }
    },

    /**
     * Render enhanced table with numbered badges and styling
     */
    renderEnhancedTable(data) {
        const tbody = document.querySelector('#busiest-stops-table tbody');

        tbody.innerHTML = data.map((stop, index) => {
            const rank = index + 1;
            const badgeClass = this.getRankBadgeClass(rank);

            return `
                <tr data-index="${index}" class="bus-stop-row">
                    <td>
                        <div class="rank-badge ${badgeClass}">${rank}</div>
                    </td>
                    <td>${stop.stop_id || stop.BusStopCode}</td>
                    <td class="stop-name">${stop.stop_name}</td>
                    <td class="total-boarding">${stop.total_boarding.toLocaleString()}</td>
                    <td style="font-size: 12px; color: #6b7280;">${stop.lat.toFixed(4)}, ${stop.lng.toFixed(4)}</td>
                </tr>
            `;
        }).join('');

        // Add click handlers
        tbody.querySelectorAll('tr').forEach((row, index) => {
            row.addEventListener('click', () => {
                this.selectStop(index);
            });
        });
    },

    /**
     * Get rank badge class based on position
     */
    getRankBadgeClass(rank) {
        if (rank === 1) return 'rank-1';
        if (rank === 2) return 'rank-2';
        if (rank === 3) return 'rank-3';
        return 'rank-other';
    },

    /**
     * Get marker color based on rank
     */
    getMarkerColor(rank) {
        if (rank === 1) return '#ef4444'; // Red
        if (rank === 2) return '#f97316'; // Orange
        if (rank === 3) return '#eab308'; // Yellow
        return '#22c55e'; // Green for 4-10
    },

    /**
     * Initialize Leaflet map
     */
    initMap(data) {
        const mapContainer = document.getElementById('stops-map');

        if (!mapContainer) {
            console.error('Map container not found');
            return;
        }

        // Clear existing map if any
        if (this.map) {
            this.map.remove();
        }

        // Create map centered on Singapore
        this.map = L.map('stops-map').setView([1.3521, 103.8198], 12);

        // Add CartoDB Light tile layer
        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
            subdomains: 'abcd',
            maxZoom: 19
        }).addTo(this.map);

        // Add markers for all stops
        this.markers = data.map((stop, index) => {
            return this.createMarker(stop, index);
        });

        // Fit bounds to show all markers
        if (this.markers.length > 0) {
            const group = L.featureGroup(this.markers);
            this.map.fitBounds(group.getBounds().pad(0.1));
        }
    },

    /**
     * Create marker for bus stop with rank-based styling
     */
    createMarker(stop, index) {
        const rank = index + 1;
        const maxBoardings = Math.max(...this.stopsData.map(s => s.total_boarding));
        const minBoardings = Math.min(...this.stopsData.map(s => s.total_boarding));

        // Calculate marker size based on ridership
        const normalized = (stop.total_boarding - minBoardings) / (maxBoardings - minBoardings);
        const radius = 10 + (normalized * 12); // 10-22px radius

        const markerColor = this.getMarkerColor(rank);

        const marker = L.circleMarker([stop.lat, stop.lng], {
            radius: radius,
            fillColor: markerColor,
            color: '#ffffff',
            weight: 2,
            opacity: 1,
            fillOpacity: 0.8
        });

        marker.addTo(this.map);

        // Enhanced popup
        marker.bindPopup(`
            <div style="text-align: center; min-width: 180px;">
                <div style="font-weight: 600; font-size: 14px; color: #1f2937; margin-bottom: 6px;">
                    ${stop.stop_name}
                </div>
                <div style="color: #6b7280; font-size: 12px; margin-bottom: 8px;">
                    Rank #${rank}
                </div>
                <div style="background: ${markerColor}; color: white; padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 13px;">
                    ${stop.total_boarding.toLocaleString()} boardings
                </div>
            </div>
        `, {
            className: 'custom-popup'
        });

        // Click handler
        marker.on('click', () => {
            this.selectStop(index);
        });

        // Store reference
        marker.stopIndex = index;
        marker.originalColor = markerColor;
        marker.originalRadius = radius;

        return marker;
    },

    /**
     * Select a stop (from table or map)
     */
    selectStop(index) {
        const stop = this.stopsData[index];

        if (!stop) return;

        this.selectedStopIndex = index;

        // Update table selection with enhanced styling
        document.querySelectorAll('#busiest-stops-table tbody tr').forEach((row, i) => {
            if (i === index) {
                row.classList.add('selected');
            } else {
                row.classList.remove('selected');
            }
        });

        // Reset all markers to original style
        this.markers.forEach((marker, i) => {
            const rank = i + 1;
            marker.setStyle({
                fillColor: this.getMarkerColor(rank),
                color: '#ffffff',
                weight: 2,
                fillOpacity: 0.8,
                radius: marker.originalRadius
            });
        });

        // Highlight selected marker with blue pin style
        const selectedMarker = this.markers[index];
        selectedMarker.setStyle({
            fillColor: '#2563eb', // Blue
            color: '#2563eb',
            weight: 3,
            fillOpacity: 1,
            radius: selectedMarker.originalRadius + 3
        });

        // Open popup with animation
        selectedMarker.openPopup();

        // Store selected marker
        this.selectedMarker = selectedMarker;

        // Pan and zoom to selected stop with smooth animation
        this.map.flyTo([stop.lat, stop.lng], 15, {
            duration: 1.2,
            easeLinearity: 0.25
        });
    },

    /**
     * Lazy load map when section is in view
     */
    setupLazyLoading() {
        const mapSection = document.getElementById('busiest-stops');

        if (!mapSection) return;

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !this.map) {
                    this.initBusiestStops();
                    observer.disconnect();
                }
            });
        }, {
            rootMargin: '100px'
        });

        observer.observe(mapSection);
    }
};
