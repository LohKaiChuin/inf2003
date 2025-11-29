"""
Step 8: Test API endpoints with requests
"""
import requests
import json
from time import sleep

BASE_URL = 'http://localhost:5001/analytics'

def test_health():
    """Test health check endpoint"""
    print("[Test 1] Health Check")
    response = requests.get(f'{BASE_URL}/health')
    print(f"Status Code: {response.status_code}")
    print(f"Response: {json.dumps(response.json(), indent=2)}")
    assert response.status_code == 200
    print("✓ Test passed\n")

def test_predictions():
    """Test predictions endpoint"""
    print("[Test 2] Get Predictions")
    response = requests.get(f'{BASE_URL}/predictions?route=123&hours=6')
    print(f"Status Code: {response.status_code}")
    data = response.json()
    print(f"Route: {data['route_id']}")
    print(f"Predictions: {len(data['predictions'])}")
    for pred in data['predictions'][:3]:
        print(f"  - {pred['datetime']}: {pred['predicted_passengers']} passengers")
    assert response.status_code == 200
    assert len(data['predictions']) == 6
    print("✓ Test passed\n")

def test_alerts():
    """Test alerts endpoint"""
    print("[Test 3] Get Alerts")
    response = requests.get(f'{BASE_URL}/alerts?route=123&hours=24')
    print(f"Status Code: {response.status_code}")
    data = response.json()
    print(f"Total Alerts: {data['summary']['total']}")
    print(f"Critical: {data['summary']['critical']}")
    print(f"Warning: {data['summary']['warning']}")
    print(f"Info: {data['summary']['info']}")
    if data['alerts']:
        print(f"\nFirst alert:")
        alert = data['alerts'][0]
        print(f"  Type: {alert['type']}")
        print(f"  Severity: {alert['severity']}")
        print(f"  Message: {alert['message']}")
    assert response.status_code == 200
    print("✓ Test passed\n")

def test_forecast():
    """Test forecast endpoint"""
    print("[Test 4] Get Forecast")
    response = requests.get(f'{BASE_URL}/forecast?route=123&days=7')
    print(f"Status Code: {response.status_code}")
    data = response.json()
    print(f"Route: {data['route_id']}")
    print(f"Days forecasted: {len(data['forecast'])}")
    print(f"Weekly total: {data['weekly_total']} passengers")
    print(f"Average daily: {data['average_daily']} passengers")
    print(f"Busiest day: {data['busiest_day']}")
    assert response.status_code == 200
    assert len(data['forecast']) == 7
    print("✓ Test passed\n")

def test_invalid_route():
    """Test error handling for invalid route"""
    print("[Test 5] Invalid Route Error Handling")
    response = requests.get(f'{BASE_URL}/predictions?route=INVALID&hours=6')
    print(f"Status Code: {response.status_code}")
    print(f"Error: {response.json()['error']}")
    assert response.status_code == 404
    print("✓ Test passed\n")

def test_missing_parameter():
    """Test error handling for missing parameters"""
    print("[Test 6] Missing Parameter Error Handling")
    response = requests.get(f'{BASE_URL}/predictions')
    print(f"Status Code: {response.status_code}")
    print(f"Error: {response.json()['error']}")
    assert response.status_code == 400
    print("✓ Test passed\n")

if __name__ == '__main__':
    print("="*60)
    print("API ENDPOINT TESTS")
    print("="*60)
    print("\n⚠️  Make sure the API server is running first!")
    print("    Run: python 5_feature1_api.py")
    print()
    input("Press Enter when server is ready...")
    print()
    
    try:
        test_health()
        test_predictions()
        test_alerts()
        test_forecast()
        test_invalid_route()
        test_missing_parameter()
        
        print("="*60)
        print("ALL API TESTS PASSED ✓ (6/6)")
        print("="*60)
    
    except requests.exceptions.ConnectionError:
        print("\n❌ Error: Could not connect to API server")
        print("   Make sure the server is running on http://localhost:5001")
    except AssertionError as e:
        print(f"\n❌ Test failed: {e}")
    except Exception as e:
        print(f"\n❌ Error: {e}")