<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'] ?? 'User';
$userInitial = strtoupper(substr($username, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YourTrip - User Dashboard</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        /* Navigation Bar */
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .navbar-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar-brand {
            font-size: 24px;
            font-weight: 700;
        }

        .navbar-user {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            color: #667eea;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .logout-btn {
            padding: 8px 20px;
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid white;
            color: white;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: white;
            color: #667eea;
        }

        /* Main Container */
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .welcome-section {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .welcome-section h1 {
            color: #333;
            margin-bottom: 0.5rem;
        }

        .welcome-section p {
            color: #666;
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
        }

        .stat-card h3 {
            color: #64748b;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .stat-card .change {
            font-size: 14px;
            color: #10b981;
        }

        /* Main Content Area */
        .main-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        .section-card {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .section-card h2 {
            color: #333;
            margin-bottom: 1.5rem;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Route Planner */
        .search-box {
            position: relative;
            margin-bottom: 1rem;
        }

        .search-box input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            outline: none;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            cursor: pointer;
            margin-top: 1rem;
            transition: all 0.3s ease;
        }

        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        /* Quick Links */
        .quick-links {
            display: grid;
            gap: 1rem;
        }

        .quick-link-item {
            padding: 1rem;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .quick-link-item:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateX(5px);
        }

        .quick-link-icon {
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .quick-link-item:hover .quick-link-icon {
            background: rgba(255, 255, 255, 0.2);
        }

        /* Recent Trips */
        .trip-list {
            display: grid;
            gap: 1rem;
        }

        .trip-item {
            padding: 1rem;
            background: #f8fafc;
            border-radius: 12px;
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }

        .trip-item:hover {
            background: #e2e8f0;
            transform: translateX(5px);
        }

        .trip-route {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .trip-time {
            font-size: 14px;
            color: #64748b;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .main-content {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .navbar-content {
                flex-direction: column;
                gap: 1rem;
            }

            .container {
                padding: 0 1rem;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="navbar-content">
            <div class="navbar-brand">🚌 YourTrip</div>
            <div class="navbar-user">
                <div class="user-info">
                    <div class="user-avatar"><?php echo htmlspecialchars($userInitial); ?></div>
                    <div>
                        <div style="font-weight: 600;"><?php echo htmlspecialchars($username); ?></div>
                        <div style="font-size: 12px; opacity: 0.8;">Passenger</div>
                    </div>
                </div>
                <button class="logout-btn" onclick="logout()">Logout</button>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="container">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h1>Welcome back, <?php echo htmlspecialchars($username); ?>!</h1>
            <p>Plan your journey and explore Singapore's transport network</p>
        </div>

        <!-- Statistics Cards -->
        <div class="dashboard-grid">
            <div class="stat-card">
                <h3>TOTAL TRIPS</h3>
                <div class="value">24</div>
                <div class="change">↑ 12% from last month</div>
            </div>
            <div class="stat-card">
                <h3>FAVORITE ROUTES</h3>
                <div class="value">5</div>
                <div class="change">Active routes saved</div>
            </div>
            <div class="stat-card">
                <h3>TRAVEL TIME SAVED</h3>
                <div class="value">3.5h</div>
                <div class="change">This month</div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Route Planner -->
            <div class="section-card">
                <h2>🗺️ Plan Your Journey</h2>
                <div class="search-box">
                    <input type="text" placeholder="From: Enter starting location" id="fromLocation">
                </div>
                <div class="search-box">
                    <input type="text" placeholder="To: Enter destination" id="toLocation">
                </div>
                <button class="search-btn" onclick="searchRoute()">🔍 Find Best Route</button>

                <div style="margin-top: 2rem;">
                    <h3 style="color: #333; margin-bottom: 1rem; font-size: 16px;">Recent Trips</h3>
                    <div class="trip-list">
                        <div class="trip-item">
                            <div class="trip-route">🏠 Home → 🏢 Office</div>
                            <div class="trip-time">Bus 174 • 25 mins • Today 8:30 AM</div>
                        </div>
                        <div class="trip-item">
                            <div class="trip-route">🏢 Office → 🍽️ Marina Bay</div>
                            <div class="trip-time">MRT Downtown Line • 15 mins • Yesterday 6:45 PM</div>
                        </div>
                        <div class="trip-item">
                            <div class="trip-route">🏠 Home → 🛍️ Orchard Road</div>
                            <div class="trip-time">Bus 190 → MRT • 35 mins • Dec 28</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Links Sidebar -->
            <div>
                <div class="section-card">
                    <h2>⚡ Quick Access</h2>
                    <div class="quick-links">
                        <div class="quick-link-item" onclick="viewAllRoutes()">
                            <div class="quick-link-icon">🚌</div>
                            <div>
                                <div style="font-weight: 600;">All Routes</div>
                                <div style="font-size: 12px; opacity: 0.8;">Browse all bus routes</div>
                            </div>
                        </div>
                        <div class="quick-link-item" onclick="viewBusStops()">
                            <div class="quick-link-icon">📍</div>
                            <div>
                                <div style="font-weight: 600;">Bus Stops</div>
                                <div style="font-size: 12px; opacity: 0.8;">Find nearby stops</div>
                            </div>
                        </div>
                        <div class="quick-link-item" onclick="viewInteractiveMap()">
                            <div class="quick-link-icon">🗺️</div>
                            <div>
                                <div style="font-weight: 600;">Interactive Map</div>
                                <div style="font-size: 12px; opacity: 0.8;">Explore with live tracking</div>
                            </div>
                        </div>
                        <div class="quick-link-item" onclick="viewAnalytics()">
                            <div class="quick-link-icon">📊</div>
                            <div>
                                <div style="font-weight: 600;">Analytics</div>
                                <div style="font-size: 12px; opacity: 0.8;">View ridership trends</div>
                            </div>
                        </div>
                        <div class="quick-link-item" onclick="submitFeedback()">
                            <div class="quick-link-icon">💬</div>
                            <div>
                                <div style="font-weight: 600;">Feedback</div>
                                <div style="font-size: 12px; opacity: 0.8;">Share your experience</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Logout function
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        }

        // Search route function
        function searchRoute() {
            const from = document.getElementById('fromLocation').value;
            const to = document.getElementById('toLocation').value;

            if (!from || !to) {
                alert('Please enter both starting location and destination');
                return;
            }

            alert(`Searching for best route from "${from}" to "${to}"...\n\nThis will be integrated with the route planning algorithm.`);
        }

        // Quick link functions
        function viewAllRoutes() {
            alert('Redirecting to All Routes page...');
        }

        function viewBusStops() {
            alert('Redirecting to Bus Stops page...');
        }

        function viewInteractiveMap() {
            alert('Opening Interactive Map...');
        }

        function viewAnalytics() {
            alert('Redirecting to Analytics Dashboard...');
        }

        function submitFeedback() {
            alert('Opening Feedback Form...');
        }
    </script>
</body>
</html>
