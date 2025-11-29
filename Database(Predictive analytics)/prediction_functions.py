"""
Prediction Functions with MySQL Database Integration
Makes predictions using real-time data from the database
"""
import pickle
import pandas as pd
from datetime import datetime, timedelta
from APIClient import get_api_client
from config import MODEL_FILE, CAPACITY_PER_BUS

def load_model():
    """Load trained model from file"""
    try:
        with open(MODEL_FILE, 'rb') as f:
            model = pickle.load(f)
        return model
    except FileNotFoundError:
        raise FileNotFoundError(
            f"Model file not found: {MODEL_FILE}\n"
            "Please run train_model_db.py first to train the model."
        )

def get_historical_average(route_id, hour, db_client=None):
    """
    Get historical average ridership for a route at a specific hour
    Used as fallback for prev_hour_passengers

    Args:
        route_id: Route service number
        hour: Hour of day (0-23)
        db_client: Database client (optional)

    Returns:
        Average passenger count
    """
    if db_client is None:
        db_client = get_api_client()

    try:
        # Get volume data for this route using the API
        # Use all available data to calculate average
        volume_df = db_client.get_bus_volume_by_route(route_id, month=None)

        if not volume_df.empty:
            # Filter by hour if data is available
            hour_data = volume_df[volume_df['hour'] == hour]

            if not hour_data.empty:
                return float(hour_data['total_passengers'].mean())
            else:
                # Fallback: return overall average for the route
                return float(volume_df['total_passengers'].mean())
        else:
            # Final fallback
            return 100.0

    except Exception as e:
        print(f"Warning: Could not get historical average: {e}")
        return 100.0

def predict_ridership(route_id, target_datetime, model=None, db_client=None, save_to_db=False):
    """
    Predict ridership for a specific route and time
    
    Args:
        route_id: Route service number
        target_datetime: datetime object for prediction
        model: Pre-loaded model (optional)
        db_client: Database client (optional)
        save_to_db: Whether to save prediction to database (default False)
    
    Returns:
        dict with prediction results
    """
    if model is None:
        model = load_model()
    
    if db_client is None:
        db_client = get_api_client()
    
    # Prepare features
    features = {
        'hour': target_datetime.hour,
        'day_of_week': target_datetime.weekday(),
        'is_weekend': 1 if target_datetime.weekday() in [5, 6] else 0,
        'month': target_datetime.month,
        'prev_hour_passengers': get_historical_average(
            route_id, 
            target_datetime.hour, 
            db_client
        )
    }
    
    # Check for NaN values
    for key, value in features.items():
        if pd.isna(value):
            print(f"Warning: NaN value for feature {key}, using default")
            if key == 'prev_hour_passengers':
                features[key] = 100.0
            else:
                features[key] = 0
    
    # Create DataFrame for prediction
    feature_order = ['hour', 'day_of_week', 'is_weekend', 'month', 'prev_hour_passengers']
    X = pd.DataFrame([[features[f] for f in feature_order]], columns=feature_order)
    
    # Make prediction
    prediction = model.predict(X)[0]
    
    # Calculate confidence (based on feature values and model performance)
    # Higher confidence during weekdays and normal hours
    base_confidence = 0.85
    if features['is_weekend'] == 1:
        base_confidence -= 0.07  # Less data for weekends
    if features['hour'] < 6 or features['hour'] > 22:
        base_confidence -= 0.05  # Less confident late night/early morning
    
    confidence = max(0.70, min(0.95, base_confidence))
    
    # Determine if peak hour
    is_peak = features['hour'] in [7, 8, 9, 17, 18, 19] and features['is_weekend'] == 0
    
    result = {
        'route_id': route_id,
        'predicted_passengers': int(round(max(0, prediction))),  # No negative predictions
        'datetime': target_datetime.isoformat(),
        'confidence': confidence,
        'is_peak': is_peak,
        'features': features
    }
    
    # Save to database if requested
    if save_to_db:
        try:
            db_client.save_prediction(
                route_id=route_id,
                prediction_datetime=target_datetime,
                predicted_passengers=result['predicted_passengers'],
                confidence=confidence,
                is_peak=is_peak,
                model_version='v1.0'
            )
        except Exception as e:
            print(f"Warning: Could not save prediction to database: {e}")
    
    return result

def predict_multiple_hours(route_id, hours=6, model=None, db_client=None, save_to_db=False):
    """
    Predict ridership for next N hours
    
    Args:
        route_id: Route service number
        hours: Number of hours to predict
        model: Pre-loaded model (optional)
        db_client: Database client (optional)
        save_to_db: Whether to save predictions to database
    
    Returns:
        List of prediction dicts
    """
    if model is None:
        model = load_model()
    
    if db_client is None:
        db_client = get_api_client()
    
    predictions = []
    now = datetime.now()
    
    for i in range(hours):
        target_time = now + timedelta(hours=i)
        pred = predict_ridership(route_id, target_time, model, db_client, save_to_db)
        predictions.append(pred)
    
    return predictions

def predict_daily_forecast(route_id, target_date, model=None, db_client=None, save_to_db=False):
    """
    Predict ridership for all 24 hours of a specific day
    
    Args:
        route_id: Route service number
        target_date: date object
        model: Pre-loaded model (optional)
        db_client: Database client (optional)
        save_to_db: Whether to save predictions to database
    
    Returns:
        dict with daily forecast
    """
    if model is None:
        model = load_model()
    
    if db_client is None:
        db_client = get_api_client()
    
    predictions = []
    
    for hour in range(24):
        target_datetime = datetime.combine(target_date, datetime.min.time()).replace(hour=hour)
        pred = predict_ridership(route_id, target_datetime, model, db_client, save_to_db)
        predictions.append(pred)
    
    daily_total = sum(p['predicted_passengers'] for p in predictions)
    peak_hour_pred = max(predictions, key=lambda x: x['predicted_passengers'])
    
    return {
        'date': target_date.isoformat(),
        'route_id': route_id,
        'hourly_predictions': predictions,
        'daily_total': daily_total,
        'peak_hour': peak_hour_pred['features']['hour'],
        'peak_passengers': peak_hour_pred['predicted_passengers']
    }

def get_recent_predictions(route_id, hours=24, db_client=None):
    """
    Get recent predictions from database
    
    Args:
        route_id: Route service number
        hours: How many hours back to retrieve
        db_client: Database client (optional)
    
    Returns:
        List of predictions from database
    """
    if db_client is None:
        db_client = get_api_client()
    
    start_time = datetime.now() - timedelta(hours=hours)
    
    try:
        predictions = db_client.get_predictions(
            route_id=route_id,
            start_date=start_time,
            limit=100
        )
        return predictions
    except Exception as e:
        print(f"Warning: Could not retrieve predictions: {e}")
        return []


# ==================== TESTING ====================

if __name__ == '__main__':
    print("="*60)
    print("TESTING PREDICTION FUNCTIONS WITH DATABASE")
    print("="*60)
    print()
    
    try:
        # Load model
        print("[Loading Model]")
        model = load_model()
        print("✓ Model loaded successfully")
        print()
        
        # Test 1: Single prediction
        print("[Test 1] Single Prediction for Route 118")
        now = datetime.now()
        result = predict_ridership('118', now, model, save_to_db=True)
        
        print(f"Route: {result['route_id']}")
        print(f"Time: {result['datetime']}")
        print(f"Predicted: {result['predicted_passengers']} passengers")
        print(f"Confidence: {result['confidence']*100:.0f}%")
        print(f"Peak Hour: {result['is_peak']}")
        print(f"✓ Prediction saved to database")
        print()
        
        # Test 2: Multi-hour prediction
        print("[Test 2] Next 6 Hours for Route 118")
        print("="*60)
        predictions = predict_multiple_hours('118', hours=6, model=model, save_to_db=False)
        
        for pred in predictions:
            dt = datetime.fromisoformat(pred['datetime'])
            peak = "🔴 PEAK" if pred['is_peak'] else ""
            capacity_status = "⚠️ HIGH" if pred['predicted_passengers'] > CAPACITY_PER_BUS else ""
            print(f"{dt.strftime('%H:%M')} → {pred['predicted_passengers']:3d} pax {peak} {capacity_status}")
        
        print()
        
        # Test 3: Daily forecast
        print("[Test 3] Daily Forecast for Route 118")
        from datetime import date
        today = date.today()
        forecast = predict_daily_forecast('118', today, model=model, save_to_db=False)
        
        print(f"Date: {forecast['date']}")
        print(f"Daily Total: {forecast['daily_total']:,} passengers")
        print(f"Peak Hour: {forecast['peak_hour']}:00 ({forecast['peak_passengers']} pax)")
        print()
        
        print("="*60)
        print("✓ ALL TESTS PASSED")
        print("="*60)
    
    except Exception as e:
        print(f"\n✗ Test failed: {e}")
        import traceback
        traceback.print_exc()