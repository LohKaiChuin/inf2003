/**
 * Multilingual Support Module
 * Handles language switching for transport stop names
 * Supports: English, Chinese (中文), Tamil (தமிழ்), Malay (Bahasa)
 */

// Global state
let currentLanguage = localStorage.getItem('preferred_language') || 'en';
let stopNameCache = {};

// API Configuration
const MULTILINGUAL_API = {
    baseUrl: 'multilingual_api.php',  // PHP API endpoint (same server)
    timeout: 5000, // 5 second timeout

    /**
     * Fetch with timeout
     */
    async fetchWithTimeout(url, options = {}) {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), this.timeout);

        try {
            const response = await fetch(url, {
                ...options,
                signal: controller.signal
            });
            clearTimeout(timeoutId);
            return response;
        } catch (error) {
            clearTimeout(timeoutId);
            if (error.name === 'AbortError') {
                throw new Error('Request timeout - API not responding');
            }
            throw error;
        }
    },

    /**
     * Get all stops with selected language
     */
    async getStops(stopType = null, lang = currentLanguage) {
        const params = new URLSearchParams({ action: 'get_stops', lang });
        if (stopType) params.append('stop_type', stopType);

        const response = await this.fetchWithTimeout(`${this.baseUrl}?${params}`);
        if (!response.ok) throw new Error('Failed to fetch stops');
        return await response.json();
    },

    /**
     * Get single stop by ID
     */
    async getStop(stopId, lang = currentLanguage) {
        const params = new URLSearchParams({ action: 'get_stop', stop_id: stopId, lang });
        const response = await this.fetchWithTimeout(`${this.baseUrl}?${params}`);
        if (!response.ok) throw new Error('Stop not found');
        return await response.json();
    },

    /**
     * Search stops across all languages
     */
    async searchStops(query, lang = currentLanguage) {
        const params = new URLSearchParams({ action: 'search', q: query, lang });
        const response = await this.fetchWithTimeout(`${this.baseUrl}?${params}`);
        if (!response.ok) throw new Error('Search failed');
        return await response.json();
    },

    /**
     * Get supported languages
     */
    async getLanguages() {
        const params = new URLSearchParams({ action: 'languages' });
        const response = await this.fetchWithTimeout(`${this.baseUrl}?${params}`);
        if (!response.ok) throw new Error('Failed to fetch languages');
        return await response.json();
    }
};

/**
 * Initialize language switcher UI
 */
function initLanguageSwitcher() {
    // Check if switcher already exists
    if (document.getElementById('language-switcher')) return;

    // Create language switcher HTML
    const switcherHTML = `
        <div class="btn-group" role="group" id="language-switcher">
            <button type="button" class="btn btn-sm btn-outline-secondary language-btn ${currentLanguage === 'en' ? 'active' : ''}" data-lang="en">
                EN
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary language-btn ${currentLanguage === 'zh' ? 'active' : ''}" data-lang="zh">
                中文
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary language-btn ${currentLanguage === 'ta' ? 'active' : ''}" data-lang="ta">
                தமிழ்
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary language-btn ${currentLanguage === 'ms' ? 'active' : ''}" data-lang="ms">
                Bahasa
            </button>
        </div>
    `;

    // Find header or suitable location
    const header = document.querySelector('.dashboard-header') || document.querySelector('header');
    if (header) {
        const container = document.createElement('div');
        container.style.cssText = 'position: absolute; top: 15px; right: 20px;';
        container.innerHTML = switcherHTML;
        header.style.position = 'relative';
        header.appendChild(container);

        // Add event listeners
        document.querySelectorAll('.language-btn').forEach(btn => {
            btn.addEventListener('click', () => switchLanguage(btn.dataset.lang));
        });
    }
}

/**
 * Switch to a different language
 */
async function switchLanguage(lang) {
    if (lang === currentLanguage) return;

    console.log(`Switching language from ${currentLanguage} to ${lang}`);
    currentLanguage = lang;
    localStorage.setItem('preferred_language', lang);

    // Update button states
    document.querySelectorAll('.language-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.lang === lang);
    });

    // Clear cache
    stopNameCache = {};

    // Trigger language change event
    window.dispatchEvent(new CustomEvent('languageChanged', { detail: { language: lang } }));

    // Reload page data
    await reloadPageData();
}

/**
 * Get stop name in current language
 */
async function getStopName(stopId, stopType = 'mrt') {
    // Check cache first
    const cacheKey = `${stopType}_${stopId}_${currentLanguage}`;
    if (stopNameCache[cacheKey]) {
        return stopNameCache[cacheKey];
    }

    try {
        const response = await MULTILINGUAL_API.getStop(stopId, currentLanguage);
        const name = response.stop.name;
        stopNameCache[cacheKey] = name;
        return name;
    } catch (error) {
        console.error(`Error fetching stop name for ${stopId}:`, error);
        return stopId; // Fallback to stop ID
    }
}

/**
 * Update all stop names on the page
 */
async function updateStopNamesOnPage() {
    // Find all elements with data-stop-id attribute
    const elements = document.querySelectorAll('[data-stop-id]');

    for (const element of elements) {
        const stopId = element.dataset.stopId;
        const stopType = element.dataset.stopType || 'mrt';

        try {
            const name = await getStopName(stopId, stopType);
            element.textContent = name;
        } catch (error) {
            console.error(`Failed to update stop name for ${stopId}`, error);
        }
    }
}

/**
 * Reload page data with new language
 */
async function reloadPageData() {
    console.log('Reloading page data for language:', currentLanguage);

    // Update stop names on the page
    await updateStopNamesOnPage();

    // For POI page: Update MRT/Bus station names in map markers
    if (typeof updateTransportStopNames === 'function') {
        await updateTransportStopNames(currentLanguage);
    }

    // Trigger custom reload if defined
    if (typeof window.reloadMultilingualData === 'function') {
        await window.reloadMultilingualData(currentLanguage);
    }

    console.log('Page data reloaded with language:', currentLanguage);
}

/**
 * Get current language
 */
function getCurrentLanguage() {
    return currentLanguage;
}

/**
 * Format stop data with multilingual names
 */
function formatStopWithLanguage(stop) {
    return {
        ...stop,
        displayName: stop.names ? stop.names[currentLanguage] || stop.names.en || stop.name : stop.name,
        allNames: stop.names || { en: stop.name }
    };
}

/**
 * Create multilingual dropdown for stop selection
 */
function createMultilingualStopDropdown(stops, elementId) {
    const select = document.getElementById(elementId);
    if (!select) return;

    select.innerHTML = '<option value="">Select a station...</option>';

    stops.forEach(stop => {
        const option = document.createElement('option');
        option.value = stop.stop_id;
        option.textContent = stop.names ? stop.names[currentLanguage] || stop.names.en : stop.name;
        option.dataset.allNames = JSON.stringify(stop.names || {});
        select.appendChild(option);
    });
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    initLanguageSwitcher();
    console.log('Multilingual support initialized with language:', currentLanguage);
});

// Export for use in other modules
window.MultilingualAPI = MULTILINGUAL_API;
window.getCurrentLanguage = getCurrentLanguage;
window.getStopName = getStopName;
window.switchLanguage = switchLanguage;
window.formatStopWithLanguage = formatStopWithLanguage;
window.createMultilingualStopDropdown = createMultilingualStopDropdown;
