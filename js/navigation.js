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
            updateAuthStatus();

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

        let pageIdentifier = currentPage.split('.')[0];
        if (pageIdentifier === 'index' || pageIdentifier === '') {
            pageIdentifier = 'index';
        }

        navLinks.forEach(link => {
            const linkPage = link.getAttribute('data-page');

            // Remove active class from all links
            link.classList.remove('active');

            // Add active class to current page link
            if (linkPage === pageIdentifier) {
                link.classList.add('active');
            }
        });
    }

    /**
     * Checks login status and updates the Login/Logout button.
     * This is a client-side check based on a session cookie.
     */
    function updateAuthStatus() {
        const authLink = document.getElementById('auth-link');
        const authLinkText = document.getElementById('auth-link-text');

        if (!authLink || !authLinkText) return;

        // Check for a session cookie (e.g., 'ltaWannabeUser')
        const isLoggedIn = document.cookie.split(';').some((item) => item.trim().startsWith('ltaWannabeUser='));

        if (isLoggedIn) {
            // User is logged in
            authLink.href = 'logout.php';
            authLinkText.textContent = 'Logout';
            authLink.classList.remove('nav-link-login');
            authLink.classList.add('nav-link-logout');
            authLink.onclick = function(e) {
                if (!confirm('Are you sure you want to logout?')) {
                    e.preventDefault();
                }
            };
        } else {
            // User is not logged in
            authLink.href = 'login.php';
            authLinkText.textContent = 'Login';
            authLink.classList.remove('nav-link-logout');
            authLink.classList.add('nav-link-login');
            authLink.onclick = null; // Remove any previous click handler
        }
    }

    // Load navigation when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadNavigation);
    } else {
        loadNavigation();
    }
})();
