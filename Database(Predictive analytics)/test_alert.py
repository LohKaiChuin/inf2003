"""
Step 7: Test alert generation
"""
from datetime import datetime
from alert_generator import generate_all_alerts, get_alert_summary

def test_alert_generation():
    """Test alert generation for all routes"""
    print("="*60)
    print("ALERT GENERATION SYSTEM")
    print("="*60)
    print()
    
    print("[Analyzing next 24 hours for all routes...]")
    print()
    
    alerts = generate_all_alerts(hours=24)
    
    print(f"Generated Alerts: {len(alerts)}")
    print()
    
    # Display first 3 alerts
    for i, alert in enumerate(alerts[:3], 1):
        dt = datetime.fromisoformat(alert['datetime'])
        print(f"┌{'─'*59}┐")
        print(f"│ ALERT #{i:<52}│")
        print(f"├{'─'*59}┤")
        print(f"│ Type:     {alert['type']:<47}│")
        print(f"│ Severity: {alert['severity']:<47}│")
        print(f"│ Route:    {alert['route_id']:<47}│")
        print(f"│ Time:     {dt.strftime('%Y-%m-%d %H:%M'):<47}│")
        print(f"│ Predicted: {alert['predicted_passengers']} passengers{' '*(34)}│")
        print(f"│ Message:  {alert['message'][:46]:<46}│")
        if alert['recommendation']:
            rec = alert['recommendation'][:46]
            print(f"│ Action:   {rec:<46}│")
        print(f"└{'─'*59}┘")
        print()
    
    if len(alerts) > 3:
        print(f"[... {len(alerts) - 3} more alerts ...]")
        print()
    
    # Summary
    summary = get_alert_summary(alerts)
    print("="*60)
    print("Summary:")
    print(f"- Critical: {summary['critical']}")
    print(f"- Warning: {summary['warning']}")
    print(f"- Info: {summary['info']}")
    print(f"- Routes affected: {summary['routes_affected']}")
    print("="*60)

if __name__ == '__main__':
    test_alert_generation()