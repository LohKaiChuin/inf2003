"""
Alert Logic - Real-Time Alert Generation
Generates alerts based on prediction thresholds (NOT stored in database)
"""
from datetime import datetime
from prediction_functions import predict_multiple_hours, load_model
from config import CAPACITY_PER_BUS, HIGH_DEMAND_THRESHOLD, CRITICAL_THRESHOLD, AVAILABLE_ROUTES

def generate_alert(prediction, alert_type, severity, message, recommendation=None):
    """
    Create an alert object
    
    Args:
        prediction: Prediction dict
        alert_type: Type of alert (e.g., 'HIGH_DEMAND', 'UNUSUAL_PATTERN')
        severity: 'INFO', 'WARNING', or 'CRITICAL'
        message: Alert message
        recommendation: Recommended action (optional)
    
    Returns:
        Alert dict
    """
    return {
        'type': alert_type,
        'route_id': prediction['route_id'],
        'datetime': prediction['datetime'],
        'predicted_passengers': prediction['predicted_passengers'],
        'severity': severity,
        'message': message,
        'recommendation': recommendation,
        'generated_at': datetime.now().isoformat(),
        'confidence': prediction.get('confidence', 0.85)
    }

def check_high_demand(prediction):
    """
    Check if prediction exceeds capacity thresholds
    
    Args:
        prediction: Prediction dict
    
    Returns:
        Alert dict or None
    """
    passengers = prediction['predicted_passengers']
    
    # CRITICAL: Severe overcrowding (>270 passengers = 1.5x capacity)
    if passengers > CRITICAL_THRESHOLD:
        return generate_alert(
            prediction,
            'HIGH_DEMAND',
            'CRITICAL',
            f"Severe overcrowding predicted: {passengers} passengers (capacity: {CAPACITY_PER_BUS})",
            "Deploy additional buses immediately and consider express service"
        )
    
    # WARNING: Over capacity (>180 passengers)
    elif passengers > CAPACITY_PER_BUS:
        return generate_alert(
            prediction,
            'HIGH_DEMAND',
            'WARNING',
            f"High demand predicted: {passengers} passengers (capacity: {CAPACITY_PER_BUS})",
            "Consider deploying additional bus"
        )
    
    # INFO: Approaching capacity (>200 passengers but haven't exceeded capacity yet)
    # This catches cases where capacity might be tight
    elif passengers > HIGH_DEMAND_THRESHOLD:
        return generate_alert(
            prediction,
            'HIGH_DEMAND',
            'INFO',
            f"Elevated demand predicted: {passengers} passengers",
            "Monitor situation closely"
        )
    
    return None

def check_unusual_pattern(prediction):
    """
    Check for unusual ridership patterns
    
    Args:
        prediction: Prediction dict
    
    Returns:
        Alert dict or None
    """
    passengers = prediction['predicted_passengers']
    hour = prediction['features']['hour']
    is_weekend = prediction['features']['is_weekend']
    
    # Unusual late-night/early morning high demand
    if (hour >= 22 or hour <= 5):
        if passengers > 100:  # Unusual for late night
            return generate_alert(
                prediction,
                'UNUSUAL_PATTERN',
                'INFO',
                f"Unusually high late-night demand: {passengers} passengers (typical: ~30-50)",
                "Possible special event in area. Consider monitoring situation."
            )
    
    # Unusual weekend peak
    if is_weekend and hour in [7, 8, 9]:
        if passengers > 150:  # High for weekend morning
            return generate_alert(
                prediction,
                'UNUSUAL_PATTERN',
                'INFO',
                f"Unusually high weekend morning demand: {passengers} passengers",
                "Possible event or unusual activity pattern"
            )
    
    return None

def check_peak_hour_capacity(prediction):
    """
    Special check for peak hours - stricter thresholds
    
    Args:
        prediction: Prediction dict
    
    Returns:
        Alert dict or None
    """
    if not prediction['is_peak']:
        return None
    
    passengers = prediction['predicted_passengers']
    
    # During peak hours, even 150+ passengers deserves attention
    if passengers >= 150 and passengers <= CAPACITY_PER_BUS:
        return generate_alert(
            prediction,
            'PEAK_CAPACITY',
            'INFO',
            f"Peak hour approaching capacity: {passengers} passengers",
            "Prepare for possible additional deployment"
        )
    
    return None

def generate_alerts_for_route(route_id, hours=24, model=None):
    """
    Generate all alerts for a specific route
    
    Args:
        route_id: Route service number
        hours: Hours ahead to check (default 24)
        model: Pre-loaded model (optional)
    
    Returns:
        List of alert dicts
    """
    # Get predictions for the next N hours
    predictions = predict_multiple_hours(route_id, hours=hours, model=model)
    
    alerts = []
    
    for pred in predictions:
        # Check for high demand (CRITICAL/WARNING/INFO based on threshold)
        alert = check_high_demand(pred)
        if alert:
            alerts.append(alert)
            continue  # Don't check other conditions if high demand alert already triggered
        
        # Check for unusual patterns
        alert = check_unusual_pattern(pred)
        if alert:
            alerts.append(alert)
        
        # Check peak hour capacity
        alert = check_peak_hour_capacity(pred)
        if alert:
            alerts.append(alert)
    
    return alerts

def generate_all_alerts(hours=24, routes=None):
    """
    Generate alerts for all routes
    
    Args:
        hours: Hours ahead to check
        routes: List of route IDs (default: all available routes)
    
    Returns:
        List of all alerts
    """
    if routes is None:
        routes = AVAILABLE_ROUTES
    
    model = load_model()
    all_alerts = []
    
    for route_id in routes:
        try:
            alerts = generate_alerts_for_route(route_id, hours=hours, model=model)
            all_alerts.extend(alerts)
        except Exception as e:
            print(f"Warning: Could not generate alerts for route {route_id}: {e}")
    
    # Sort by severity and time
    severity_order = {'CRITICAL': 0, 'WARNING': 1, 'INFO': 2}
    all_alerts.sort(key=lambda x: (severity_order[x['severity']], x['datetime']))
    
    return all_alerts

def get_alert_summary(alerts):
    """
    Summarize alerts
    
    Args:
        alerts: List of alert dicts
    
    Returns:
        Summary dict
    """
    summary = {
        'total': len(alerts),
        'critical': sum(1 for a in alerts if a['severity'] == 'CRITICAL'),
        'warning': sum(1 for a in alerts if a['severity'] == 'WARNING'),
        'info': sum(1 for a in alerts if a['severity'] == 'INFO'),
        'routes_affected': len(set(a['route_id'] for a in alerts)),
        'alert_types': {}
    }
    
    # Count by alert type
    for alert in alerts:
        alert_type = alert['type']
        summary['alert_types'][alert_type] = summary['alert_types'].get(alert_type, 0) + 1
    
    return summary

def format_alert_display(alert):
    """
    Format alert for display
    
    Args:
        alert: Alert dict
    
    Returns:
        Formatted string
    """
    dt = datetime.fromisoformat(alert['datetime'])
    
    # Severity icon
    icons = {'CRITICAL': '🔴', 'WARNING': '⚠️', 'INFO': 'ℹ️'}
    icon = icons.get(alert['severity'], '•')
    
    output = f"{icon} {alert['severity']} - Route {alert['route_id']} @ {dt.strftime('%H:%M')}\n"
    output += f"   Predicted: {alert['predicted_passengers']} passengers\n"
    output += f"   {alert['message']}\n"
    if alert['recommendation']:
        output += f"   → {alert['recommendation']}\n"
    
    return output


# ==================== TESTING ====================

if __name__ == '__main__':
    print("="*60)
    print("REAL-TIME ALERT GENERATION TEST")
    print("="*60)
    print()
    
    # Test 1: Generate alerts for single route
    print("[Test 1] Generate Alerts for Route 118 (Next 24 Hours)")
    print()
    
    alerts_118 = generate_alerts_for_route('118', hours=24)
    
    print(f"Total Alerts: {len(alerts_118)}")
    print()
    
    # Display first 3 alerts
    for i, alert in enumerate(alerts_118[:3], 1):
        print(f"Alert #{i}:")
        print(format_alert_display(alert))
    
    if len(alerts_118) > 3:
        print(f"... and {len(alerts_118) - 3} more alerts\n")
    
    # Test 2: Generate alerts for all routes
    print("="*60)
    print("[Test 2] Generate Alerts for All Routes (Next 24 Hours)")
    print()
    
    all_alerts = generate_all_alerts(hours=24)
    summary = get_alert_summary(all_alerts)
    
    print(f"Total Alerts: {summary['total']}")
    print(f"  - Critical: {summary['critical']}")
    print(f"  - Warning:  {summary['warning']}")
    print(f"  - Info:     {summary['info']}")
    print(f"Routes Affected: {summary['routes_affected']}")
    print()
    
    print("Alert Types:")
    for alert_type, count in summary['alert_types'].items():
        print(f"  - {alert_type}: {count}")
    print()
    
    # Show top 5 critical/warning alerts
    critical_alerts = [a for a in all_alerts if a['severity'] in ['CRITICAL', 'WARNING']]
    if critical_alerts:
        print("Top Critical/Warning Alerts:")
        print("-" * 60)
        for i, alert in enumerate(critical_alerts[:5], 1):
            print(f"\n{i}. {format_alert_display(alert)}")
    
    print()
    print("="*60)
    print("✓ ALERT GENERATION TEST COMPLETE")
    print("="*60)
    print()
    print("Note: Alerts are generated in REAL-TIME from predictions")
    print("      They are NOT stored in the database")
    print("      Frontend queries the API to get current alerts")