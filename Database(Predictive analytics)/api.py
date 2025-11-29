"""
Flask API for Predictive Alerts with MySQL Integration
Serves predictions and real-time alerts from database data
"""
from flask import Flask, Blueprint, request, jsonify
from flask_cors import CORS
from datetime import datetime, timedelta
import traceback

from prediction_functions import (
    predict_ridership,
    predict_multiple_hours,
    predict_daily_forecast,
    get_recent_predictions,
    load_model
)
from alert_generator import (
    generate_alerts_for_route,
    generate_all_alerts,
    get_alert_summary
)
from APIClient import get_api_client
from config import AVAILABLE_ROUTES

# Create Blueprint
analytics_bp = Blueprint('analytics', __name__, url_prefix='/analytics')

# Load model and database client at startup
print("Initializing Analytics API...")
print("Loading prediction model...")
MODEL = load_model()
print("✓ Model loaded successfully")

print("Connecting to database...")
DB_CLIENT = get_api_client()
print("✓ Database connected")

# ==================== PREDICTION ENDPOINTS ====================

@analytics_bp.route('/predictions', methods=['GET'])
def get_predictions():
    """
    Get ridership predictions for a route
    
    Query params:
        route (required): Route ID
        hours (optional): Number of hours to predict (default: 6, max: 48)
        save (optional): Save predictions to database (default: false)
    
    Example: GET /analytics/predictions?route=118&hours=6&save=true
    """
    try:
        route_id = request.args.get('route')
        hours = int(request.args.get('hours', 6))
        save_to_db = request.args.get('save', 'false').lower() == 'true'
        
        # Validation
        if not route_id:
            return jsonify({'error': 'Missing required parameter: route'}), 400
        
        if route_id not in AVAILABLE_ROUTES:
            return jsonify({
                'error': f'Route not found: {route_id}',
                'available_routes': AVAILABLE_ROUTES
            }), 404
        
        if hours < 1 or hours > 48:
            return jsonify({'error': 'Hours must be between 1 and 48'}), 400
        
        # Generate predictions
        predictions = predict_multiple_hours(
            route_id, 
            hours=hours, 
            model=MODEL, 
            db_client=DB_CLIENT,
            save_to_db=save_to_db
        )
        
        return jsonify({
            'route_id': route_id,
            'predictions': predictions,
            'saved_to_database': save_to_db,
            'generated_at': datetime.now().isoformat()
        })
    
    except Exception as e:
        print(f"Error in /predictions: {e}")
        traceback.print_exc()
        return jsonify({'error': str(e)}), 500

@analytics_bp.route('/predictions/history', methods=['GET'])
def get_prediction_history():
    """
    Get historical predictions from database
    
    Query params:
        route (optional): Filter by route
        hours (optional): Hours back to retrieve (default: 24)
        limit (optional): Maximum results (default: 100)
    
    Example: GET /analytics/predictions/history?route=118&hours=24
    """
    try:
        route_id = request.args.get('route', None)
        hours = int(request.args.get('hours', 24))
        limit = int(request.args.get('limit', 100))
        
        # Validation
        if route_id and route_id not in AVAILABLE_ROUTES:
            return jsonify({
                'error': f'Route not found: {route_id}',
                'available_routes': AVAILABLE_ROUTES
            }), 404
        
        # Get predictions from database
        predictions = get_recent_predictions(
            route_id=route_id,
            hours=hours,
            db_client=DB_CLIENT
        )
        
        return jsonify({
            'route_id': route_id,
            'predictions': predictions,
            'count': len(predictions),
            'retrieved_at': datetime.now().isoformat()
        })
    
    except Exception as e:
        print(f"Error in /predictions/history: {e}")
        traceback.print_exc()
        return jsonify({'error': str(e)}), 500

# ==================== ALERT ENDPOINTS ====================

@analytics_bp.route('/alerts', methods=['GET'])
def get_alerts():
    """
    Get real-time predictive alerts
    
    Query params:
        route (optional): Specific route ID, or all routes if not specified
        hours (optional): Hours ahead to check (default: 24, max: 168)
    
    Example: GET /analytics/alerts?route=118&hours=24
    """
    try:
        route_id = request.args.get('route', None)
        hours = int(request.args.get('hours', 24))
        
        # Validation
        if hours < 1 or hours > 168:  # Max 1 week
            return jsonify({'error': 'Hours must be between 1 and 168'}), 400
        
        # Generate alerts (real-time, not from database)
        if route_id:
            if route_id not in AVAILABLE_ROUTES:
                return jsonify({
                    'error': f'Route not found: {route_id}',
                    'available_routes': AVAILABLE_ROUTES
                }), 404
            alerts = generate_alerts_for_route(route_id, hours=hours, model=MODEL)
        else:
            alerts = generate_all_alerts(hours=hours)
        
        summary = get_alert_summary(alerts)
        
        return jsonify({
            'alerts': alerts,
            'summary': summary,
            'generated_at': datetime.now().isoformat(),
            'note': 'Alerts are generated in real-time and not stored in database'
        })
    
    except Exception as e:
        print(f"Error in /alerts: {e}")
        traceback.print_exc()
        return jsonify({'error': str(e)}), 500

# ==================== FORECAST ENDPOINTS ====================

@analytics_bp.route('/forecast', methods=['GET'])
def get_forecast():
    """
    Get multi-day forecast for a route
    
    Query params:
        route (required): Route ID
        days (optional): Number of days to forecast (default: 7, max: 30)
        save (optional): Save forecasts to database (default: false)
    
    Example: GET /analytics/forecast?route=118&days=7
    """
    try:
        route_id = request.args.get('route')
        days = int(request.args.get('days', 7))
        save_to_db = request.args.get('save', 'false').lower() == 'true'
        
        # Validation
        if not route_id:
            return jsonify({'error': 'Missing required parameter: route'}), 400
        
        if route_id not in AVAILABLE_ROUTES:
            return jsonify({
                'error': f'Route not found: {route_id}',
                'available_routes': AVAILABLE_ROUTES
            }), 404
        
        if days < 1 or days > 30:
            return jsonify({'error': 'Days must be between 1 and 30'}), 400
        
        # Generate forecast
        forecast = []
        today = datetime.now().date()
        
        for day_offset in range(days):
            target_date = today + timedelta(days=day_offset)
            daily_forecast = predict_daily_forecast(
                route_id, 
                target_date, 
                model=MODEL, 
                db_client=DB_CLIENT,
                save_to_db=save_to_db
            )
            forecast.append(daily_forecast)
        
        # Calculate summary statistics
        weekly_total = sum(day['daily_total'] for day in forecast)
        average_daily = weekly_total / len(forecast)
        busiest_day = max(forecast, key=lambda x: x['daily_total'])
        quietest_day = min(forecast, key=lambda x: x['daily_total'])
        
        return jsonify({
            'route_id': route_id,
            'forecast': forecast,
            'summary': {
                'weekly_total': weekly_total,
                'average_daily': int(average_daily),
                'busiest_day': {
                    'date': busiest_day['date'],
                    'total': busiest_day['daily_total'],
                    'peak_hour': busiest_day['peak_hour']
                },
                'quietest_day': {
                    'date': quietest_day['date'],
                    'total': quietest_day['daily_total']
                }
            },
            'saved_to_database': save_to_db,
            'generated_at': datetime.now().isoformat()
        })
    
    except Exception as e:
        print(f"Error in /forecast: {e}")
        traceback.print_exc()
        return jsonify({'error': str(e)}), 500

# ==================== ROUTE INFORMATION ENDPOINTS ====================

@analytics_bp.route('/routes', methods=['GET'])
def get_routes():
    """
    Get all available routes
    
    Example: GET /analytics/routes
    """
    try:
        routes = DB_CLIENT.get_all_routes()
        
        return jsonify({
            'routes': routes,
            'count': len(routes),
            'retrieved_at': datetime.now().isoformat()
        })
    
    except Exception as e:
        print(f"Error in /routes: {e}")
        traceback.print_exc()
        return jsonify({'error': str(e)}), 500

@analytics_bp.route('/routes/<route_id>', methods=['GET'])
def get_route_details(route_id):
    """
    Get details for a specific route
    
    Example: GET /analytics/routes/118
    """
    try:
        details = DB_CLIENT.get_route_details(route_id)
        stops = DB_CLIENT.get_route_stops(route_id)
        
        if not details:
            return jsonify({'error': f'Route not found: {route_id}'}), 404
        
        return jsonify({
            'route_id': route_id,
            'details': details,
            'stops': stops,
            'num_stops': len(stops),
            'retrieved_at': datetime.now().isoformat()
        })
    
    except Exception as e:
        print(f"Error in /routes/{route_id}: {e}")
        traceback.print_exc()
        return jsonify({'error': str(e)}), 500

# ==================== HEALTH & STATUS ENDPOINTS ====================

@analytics_bp.route('/health', methods=['GET'])
def health_check():
    """Health check endpoint"""
    try:
        # Test database connection
        db_status = DB_CLIENT.test_connection()
        
        # Get data info
        data_range = DB_CLIENT.get_data_date_range()
        
        return jsonify({
            'status': 'healthy',
            'model_loaded': MODEL is not None,
            'database_connected': db_status,
            'available_routes': AVAILABLE_ROUTES,
            'data_info': {
                'earliest_month': data_range['earliest_month'],
                'latest_month': data_range['latest_month'],
                'total_records': data_range['total_records']
            },
            'timestamp': datetime.now().isoformat()
        })
    except Exception as e:
        return jsonify({
            'status': 'unhealthy',
            'error': str(e),
            'timestamp': datetime.now().isoformat()
        }), 500

@analytics_bp.route('/stats', methods=['GET'])
def get_stats():
    """Get API statistics"""
    try:
        # Count predictions in database
        query = "SELECT COUNT(*) as count FROM Predictions"
        result = DB_CLIENT.execute_query(query)
        prediction_count = result[0]['count'] if result else 0
        
        return jsonify({
            'predictions_stored': prediction_count,
            'routes_available': len(AVAILABLE_ROUTES),
            'model_version': 'v1.0',
            'timestamp': datetime.now().isoformat()
        })
    except Exception as e:
        return jsonify({'error': str(e)}), 500

# ==================== FLASK APP ====================

def create_app():
    """Create and configure Flask app"""
    app = Flask(__name__)
    CORS(app)  # Enable CORS for frontend
    
    # Register blueprint
    app.register_blueprint(analytics_bp)
    
    # Error handlers
    @app.errorhandler(404)
    def not_found(error):
        return jsonify({'error': 'Endpoint not found'}), 404
    
    @app.errorhandler(500)
    def internal_error(error):
        return jsonify({'error': 'Internal server error'}), 500
    
    return app

if __name__ == '__main__':
    print("="*70)
    print("PREDICTIVE ANALYTICS API SERVER (MySQL Integration)")
    print("="*70)
    print()
    print("✓ Model loaded and ready")
    print("✓ Database connected")
    print()
    print("Available endpoints:")
    print("  Predictions:")
    print("    - GET  /analytics/predictions")
    print("    - GET  /analytics/predictions/history")
    print("  Alerts:")
    print("    - GET  /analytics/alerts")
    print("  Forecasts:")
    print("    - GET  /analytics/forecast")
    print("  Routes:")
    print("    - GET  /analytics/routes")
    print("    - GET  /analytics/routes/<route_id>")
    print("  System:")
    print("    - GET  /analytics/health")
    print("    - GET  /analytics/stats")
    print()
    print("Starting server on http://localhost:5001")
    print("="*70)
    print()
    
    app = create_app()
    app.run(host='127.0.0.1', port=5001, debug=True)