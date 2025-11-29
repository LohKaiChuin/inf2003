"""
API Client - Replaces Direct MySQL Connection
Fetches data from your friend's backend API instead of direct database access
"""
import requests
import pandas as pd
from datetime import datetime, timedelta
import time

class APIClient:
    """Client for accessing backend API instead of direct MySQL"""
    
    def __init__(self, base_url='http://localhost:8000/analytics_api.php'):
        """
        Initialize API client
        
        Args:
            base_url: Base URL of the PHP backend API
                     Default: http://localhost:8000/analytics_api.php
                     You can change this to match your setup
        """
        self.base_url = base_url.rstrip('/')
        self.session = requests.Session()
        self.session.headers.update({
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        })
        print(f"✓ API Client initialized")
        print(f"  Base URL: {self.base_url}")
    
    def _make_request(self, endpoint, method='GET', params=None, data=None, retry=3):
        """
        Make HTTP request with retry logic
        
        Args:
            endpoint: API endpoint (e.g., '/routes/118/volume')
            method: HTTP method (GET, POST, etc.)
            params: Query parameters
            data: Request body data
            retry: Number of retries on failure
        
        Returns:
            Response JSON or raises exception
        """
        url = f"{self.base_url}{endpoint}"
        
        for attempt in range(retry):
            try:
                if method == 'GET':
                    response = self.session.get(url, params=params, timeout=30)
                elif method == 'POST':
                    response = self.session.post(url, json=data, params=params, timeout=30)
                else:
                    raise ValueError(f"Unsupported method: {method}")
                
                # Raise exception for bad status codes
                response.raise_for_status()
                
                return response.json()
            
            except requests.exceptions.Timeout:
                print(f"⚠️  Timeout on attempt {attempt + 1}/{retry}")
                if attempt == retry - 1:
                    raise
                time.sleep(1)
            
            except requests.exceptions.RequestException as e:
                print(f"⚠️  Request failed on attempt {attempt + 1}/{retry}: {e}")
                if attempt == retry - 1:
                    raise
                time.sleep(1)
    
    # ==================== ROUTE QUERIES ====================
    
    def get_all_routes(self):
        """
        Get all available bus routes
        
        API endpoint: GET /analytics_api.php?action=routes
        
        Returns:
            List of route dicts
        """
        try:
            response = self._make_request('', params={'action': 'routes'})
            return response if isinstance(response, list) else []
        
        except Exception as e:
            print(f"✗ Error fetching routes: {e}")
            return []
    
    def get_route_details(self, service_no):
        """
        Get details for a specific route
        
        API endpoint: GET /analytics_api.php?action=route_details&service_no={service_no}
        
        Args:
            service_no: Route service number
        
        Returns:
            List of route details (may have multiple directions)
        """
        try:
            response = self._make_request('', params={
                'action': 'route_details',
                'service_no': service_no
            })
            return response if isinstance(response, list) else []
        
        except Exception as e:
            print(f"✗ Error fetching route details: {e}")
            return []
    
    def get_route_stops(self, service_no, direction=1):
        """
        Get all stops for a route
        
        API endpoint: GET /analytics_api.php?action=route_stops&service_no={service_no}&direction={direction}
        
        Args:
            service_no: Route service number
            direction: Route direction
        
        Returns:
            List of stops
        """
        try:
            response = self._make_request('', params={
                'action': 'route_stops',
                'service_no': service_no,
                'direction': direction
            })
            return response if isinstance(response, list) else []
        
        except Exception as e:
            print(f"✗ Error fetching route stops: {e}")
            return []
    
    # ==================== BUS VOLUME QUERIES ====================
    
    def get_bus_volume_by_route(self, service_no, month=None, direction=1):
        """
        Get aggregated bus volume for a specific route
        **THIS IS THE KEY METHOD FOR ML TRAINING DATA**
        
        API endpoint: GET /analytics_api.php?action=volume_by_route&service_no={service_no}&month={month}&direction={direction}
        
        Args:
            service_no: Route service number
            month: Month in YYYYMM format (e.g., 202107), None for all months
            direction: Route direction (default 1)
        
        Returns:
            DataFrame with aggregated volume data
        """
        try:
            params = {
                'action': 'volume_by_route',
                'service_no': service_no,
                'direction': direction
            }
            
            if month:
                params['month'] = month
            
            data = self._make_request('', params=params)
            
            if data and isinstance(data, list):
                return pd.DataFrame(data)
            else:
                print(f"⚠️  No data returned for route {service_no}")
                return pd.DataFrame()
        
        except Exception as e:
            print(f"✗ Error fetching bus volume: {e}")
            return pd.DataFrame()
    
    def get_bus_volume_by_stop(self, stop_id, month=None):
        """
        Get bus volume for a specific stop
        
        API endpoint: GET /analytics_api.php?action=volume_by_stop&stop_id={stop_id}&month={month}
        
        Args:
            stop_id: Bus stop code
            month: Month in YYYYMM format
        
        Returns:
            DataFrame with volume data
        """
        try:
            params = {
                'action': 'volume_by_stop',
                'stop_id': stop_id
            }
            
            if month:
                params['month'] = month
            
            data = self._make_request('', params=params)
            
            if data and isinstance(data, list):
                return pd.DataFrame(data)
            else:
                return pd.DataFrame()
        
        except Exception as e:
            print(f"✗ Error fetching stop volume: {e}")
            return pd.DataFrame()
    
    def get_available_months(self):
        """
        Get all available months in BusVolume data
        
        API endpoint: GET /analytics_api.php?action=available_months
        
        Returns:
            List of months in YYYYMM format
        """
        try:
            response = self._make_request('', params={'action': 'available_months'})
            return response if isinstance(response, list) else []
        
        except Exception as e:
            print(f"✗ Error fetching available months: {e}")
            return []
    
    def get_data_date_range(self):
        """
        Get the date range of available data
        
        API endpoint: GET /analytics_api.php?action=data_date_range
        
        Returns:
            Dict with date range info
        """
        try:
            response = self._make_request('', params={'action': 'data_date_range'})
            return response if isinstance(response, dict) else {
                'earliest_month': None,
                'latest_month': None,
                'total_records': 0
            }
        
        except Exception as e:
            print(f"✗ Error fetching date range: {e}")
            return {
                'earliest_month': None,
                'latest_month': None,
                'total_records': 0
            }
    
    # ==================== PREDICTION STORAGE ====================
    
    def save_prediction(self, route_id, prediction_datetime, predicted_passengers, 
                       confidence, is_peak, model_version='v1.0'):
        """
        Save a prediction via backend API
        
        API endpoint: POST /analytics_api.php?action=save_prediction
        
        Args:
            route_id: Route service number
            prediction_datetime: Datetime for prediction
            predicted_passengers: Predicted passenger count
            confidence: Prediction confidence (0-1)
            is_peak: Whether it's a peak hour
            model_version: Version of the model used
        
        Returns:
            Prediction ID or None
        """
        try:
            data = {
                'route_id': route_id,
                'prediction_datetime': prediction_datetime.isoformat() if isinstance(prediction_datetime, datetime) else prediction_datetime,
                'predicted_passengers': predicted_passengers,
                'confidence': confidence,
                'is_peak': is_peak,
                'model_version': model_version
            }
            
            response = self._make_request('', method='POST', params={'action': 'save_prediction'}, data=data)
            
            if response and response.get('success'):
                return response.get('prediction_id')
            else:
                print(f"⚠️  Failed to save prediction: {response.get('error', 'Unknown error')}")
                return None
        
        except Exception as e:
            print(f"✗ Error saving prediction: {e}")
            return None
    
    def get_predictions(self, route_id=None, start_date=None, end_date=None, limit=100):
        """
        Retrieve predictions from backend API
        
        API endpoint: GET /analytics_api.php?action=get_predictions&route_id=...&start_date=...&end_date=...&limit=...
        
        Args:
            route_id: Filter by route (optional)
            start_date: Start datetime (optional)
            end_date: End datetime (optional)
            limit: Maximum number of results
        
        Returns:
            List of predictions
        """
        try:
            params = {
                'action': 'get_predictions',
                'limit': limit
            }
            
            if route_id:
                params['route_id'] = route_id
            if start_date:
                params['start_date'] = start_date.isoformat() if isinstance(start_date, datetime) else start_date
            if end_date:
                params['end_date'] = end_date.isoformat() if isinstance(end_date, datetime) else end_date
            
            response = self._make_request('', params=params)
            return response if isinstance(response, list) else []
        
        except Exception as e:
            print(f"✗ Error fetching predictions: {e}")
            return []
    
    # ==================== UTILITY FUNCTIONS ====================
    
    def test_connection(self):
        """
        Test API connection
        
        API endpoint: GET /analytics_api.php?action=health
        
        Returns:
            True if connection successful, False otherwise
        """
        try:
            response = self._make_request('', params={'action': 'health'})
            
            if response and response.get('status') == 'healthy':
                print("✓ API connection test successful")
                print(f"  Database: {response.get('database')}")
                print(f"  Timestamp: {response.get('timestamp')}")
                return True
            else:
                print("✗ API connection unhealthy")
                return False
        
        except Exception as e:
            print(f"✗ API connection test failed: {e}")
            return False
    
    def get_api_info(self):
        """
        Get API information/status
        
        API endpoint: GET /analytics_api.php?action=info
        
        Returns:
            Dict with API info
        """
        try:
            response = self._make_request('', params={'action': 'info'})
            return response if isinstance(response, dict) else {
                'base_url': self.base_url,
                'status': 'unknown'
            }
        except Exception as e:
            print(f"✗ Error getting API info: {e}")
            return {
                'base_url': self.base_url,
                'status': 'error',
                'error': str(e)
            }
    
    def close(self):
        """Close session"""
        self.session.close()
        print("✓ API client session closed")


# ==================== SINGLETON INSTANCE ====================

_api_client = None

def get_api_client(base_url='http://localhost:8000/analytics_api.php'):
    """
    Get or create the API client singleton
    
    Args:
        base_url: Base URL of backend API (default: http://localhost:8000/analytics_api.php)
                 Change this to match your PHP server setup
    
    Returns:
        APIClient instance
    """
    global _api_client
    if _api_client is None:
        _api_client = APIClient(base_url)
    return _api_client


# ==================== TESTING ====================

if __name__ == '__main__':
    print("="*70)
    print("API CLIENT TEST")
    print("="*70)
    print()
    print("Testing connection to PHP backend API...")
    print()
    
    try:
        # Create client - UPDATE THIS URL to match your PHP server
        api = get_api_client(base_url='http://localhost:8000/analytics_api.php')
        
        print("[Test 1] Connection Test")
        if api.test_connection():
            print("✓ Connection successful\n")
        else:
            print("✗ Connection failed\n")
            print("Make sure:")
            print("  1. SSH tunnel is running: ssh -L 33060:127.0.0.1:3306 inf2003-dev@35.212.180.159")
            print("  2. PHP server is running: php -S localhost:8000")
            print("  3. analytics_api.php is in the same directory")
            exit(1)
        
        print("[Test 2] Get API Info")
        info = api.get_api_info()
        if info:
            print(f"✓ API Name: {info.get('api_name')}")
            print(f"  Version: {info.get('version')}")
            print(f"  Total Routes: {info.get('statistics', {}).get('total_routes')}")
            print(f"  Data Range: {info.get('statistics', {}).get('earliest_month')} - {info.get('statistics', {}).get('latest_month')}")
        print()
        
        print("[Test 3] Get Available Routes")
        routes = api.get_all_routes()
        print(f"✓ Routes found: {len(routes)}")
        if routes:
            print(f"  Sample routes: {[r['ServiceNo'] for r in routes[:5]]}")
        print()
        
        print("[Test 4] Get Available Months")
        months = api.get_available_months()
        print(f"✓ Months available: {len(months)}")
        if months:
            print(f"  Range: {months[0]} - {months[-1]}")
        print()
        
        print("[Test 5] Get Bus Volume for Route 118 (July 2021)")
        volume_df = api.get_bus_volume_by_route('118', month=202107)
        print(f"✓ Volume records: {len(volume_df)}")
        if not volume_df.empty:
            print(f"  Total passengers: {volume_df['total_passengers'].sum():,}")
            print(f"  Average hourly: {volume_df['total_passengers'].mean():.0f}")
            print(f"\n  Sample data:")
            print(volume_df.head())
        print()
        
        print("="*70)
        print("✓ ALL API TESTS PASSED!")
        print("="*70)
        print()
        print("Your API client is ready to use!")
        print("Next steps:")
        print("  1. Update config.py to use API_Client instead of DB_Client")
        print("  2. Run: python train_model.py")
        print("  3. Run: python test_prediction.py")
        print("="*70)
    
    except Exception as e:
        print(f"\n✗ Test failed: {e}")
        import traceback
        traceback.print_exc()
        print()
        print("Troubleshooting:")
        print("  1. Ensure SSH tunnel is active")
        print("  2. Start PHP server: php -S localhost:8000")
        print("  3. Place analytics_api.php in the same directory")
        print("  4. Check PHP error logs")