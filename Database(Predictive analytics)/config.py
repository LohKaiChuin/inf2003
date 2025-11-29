"""
Configuration - API-based access (no direct MySQL connection)
"""
import os

# ==================== API CONFIGURATION ====================

# Backend API URL - UPDATE THIS to match your setup
# Option 1: PHP built-in server on same machine
API_BASE_URL = os.getenv('API_BASE_URL', 'http://localhost:8000/Analytics_api.php')

# Option 2: Remote server (if deployed)
# API_BASE_URL = 'http://your-server.com/analytics_api.php'

# Option 3: Different port
# API_BASE_URL = 'http://localhost:8080/analytics_api.php'

print(f"✓ API Configuration loaded")
print(f"  API URL: {API_BASE_URL}")

# ==================== ALERT THRESHOLDS ====================

CAPACITY_PER_BUS = 180
HIGH_DEMAND_THRESHOLD = 200
CRITICAL_THRESHOLD = 270  # 1.5x capacity

# ==================== MODEL CONFIGURATION ====================

MODEL_FILE = 'models/ridership_model.pkl'
RANDOM_STATE = 42
TEST_SIZE = 0.2
N_ESTIMATORS = 100

# ==================== AVAILABLE ROUTES ====================

# These will be fetched from API, but define defaults as fallback
AVAILABLE_ROUTES = ['10', '100', '100A', '101', '102', '105', '105B', '106', '106A', '118']

print(f"✓ Default routes configured: {len(AVAILABLE_ROUTES)} routes")

# ==================== NOTES ====================

# NOTE: We no longer connect directly to MySQL!
# Instead, we use the PHP backend API which handles all database queries.
# 
# Setup checklist:
# 1. Ensure SSH tunnel is active:
#    ssh -L 33060:127.0.0.1:3306 inf2003-dev@35.212.180.159
# 
# 2. Start PHP server with analytics_api.php:
#    php -S localhost:8000
# 
# 3. Test API connection:
#    python API_Client.py
# 
# 4. Train model:
#    python train_model.py
#
# 5. Test predictions:
#    python test_prediction.py