/**
 * Navigation Component Loader
 * Loads the navigation bar and handles active state and mobile toggle
 */

(function() {
    /**
     * Load navigation HTML into the page
     */
    async function loadNavigation() {
        try {
            const response = await fetch('components/navigation.html');
            if (!response.ok) {
                throw new Error('Failed to load navigation');
            }

            const html = await response.text();

            // Insert navigation at the beginning of body
            document.body.insertAdjacentHTML('afterbegin', html);

            // Initialize navigation functionality
            initNavigation();
            setActivePage();

        } catch (error) {
            console.error('Error loading navigation:', error);
        }
    }

    /**
     * Initialize navigation functionality (mobile toggle, etc.)
     */
    function initNavigation() {
        const navToggle = document.getElementById('navToggle');
        const navLinks = document.getElementById('navLinks');

        if (navToggle && navLinks) {
            // Toggle mobile menu
            navToggle.addEventListener('click', () => {
                navLinks.classList.toggle('open');
            });

            // Close mobile menu when clicking a link
            navLinks.querySelectorAll('.nav-link').forEach(link => {
                link.addEventListener('click', () => {
                    navLinks.classList.remove('open');
                });
            });

            // Close mobile menu when clicking outside
            document.addEventListener('click', (e) => {
                if (!navToggle.contains(e.target) && !navLinks.contains(e.target)) {
                    navLinks.classList.remove('open');
                }
            });
        }
    }

    /**
     * Set active navigation link based on current page
     */
    function setActivePage() {
        const currentPage = window.location.pathname.split('/').pop() || 'index.html';
        const navLinks = document.querySelectorAll('.nav-link');

        navLinks.forEach(link => {
            const href = link.getAttribute('href');

            // Remove active class from all links
            link.classList.remove('active');

            // Add active class to current page link
            if (href === currentPage ||
                (currentPage === '' && href === 'index.html') ||
                (currentPage === '/' && href === 'index.html')) {
                link.classList.add('active');
            }
        });
    }

    // Load navigation when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadNavigation);
    } else {
        loadNavigation();
    }
})();
