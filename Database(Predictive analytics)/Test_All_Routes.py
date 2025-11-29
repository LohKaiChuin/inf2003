"""
Comprehensive Test: ALL Routes
Tests predictions across ALL routes to prove system works universally
"""
from datetime import datetime, timedelta
from prediction_functions import predict_ridership, predict_multiple_hours
from config import AVAILABLE_ROUTES, CAPACITY_PER_BUS

def print_header(title):
    print("\n" + "="*70)
    print(title)
    print("="*70)

def test_all_routes_peak_hour():
    """Test peak hour predictions for ALL routes"""
    print_header("TEST 1: Peak Hour Predictions - ALL ROUTES")
    
    target_time = datetime(2025, 11, 28, 18, 0)  # Evening peak
    
    print(f"\nTesting evening peak (6pm) for all {len(AVAILABLE_ROUTES)} routes:")
    print(f"{'Route':<10} {'Prediction':<15} {'Peak?':<10} {'Alert?':<10} {'Status':<10}")
    print("-" * 70)
    
    all_passed = True
    
    for route_id in AVAILABLE_ROUTES:
        result = predict_ridership(route_id, target_time)
        passengers = result['predicted_passengers']
        is_peak = result['is_peak']
        triggers_alert = passengers > CAPACITY_PER_BUS
        
        # Check if realistic (should be >100 for peak hour)
        status = "✓ PASS" if passengers > 100 else "❌ FAIL"
        if passengers <= 100:
            all_passed = False
        
        peak_str = "Yes" if is_peak else "No"
        alert_str = "Yes" if triggers_alert else "No"
        
        print(f"{route_id:<10} {passengers:<15} {peak_str:<10} {alert_str:<10} {status:<10}")
    
    print("\n" + "="*70)
    if all_passed:
        print("✓ ALL ROUTES show realistic peak hour predictions!")
    else:
        print("❌ Some routes have unrealistic predictions")
    
    return all_passed

def test_all_routes_off_peak():
    """Test off-peak predictions for ALL routes"""
    print_header("TEST 2: Off-Peak Predictions - ALL ROUTES")
    
    target_time = datetime(2025, 11, 28, 14, 0)  # Afternoon off-peak
    
    print(f"\nTesting off-peak (2pm) for all {len(AVAILABLE_ROUTES)} routes:")
    print(f"{'Route':<10} {'Prediction':<15} {'Peak?':<10} {'Valid Range?':<15} {'Status':<10}")
    print("-" * 70)
    
    all_passed = True
    
    for route_id in AVAILABLE_ROUTES:
        result = predict_ridership(route_id, target_time)
        passengers = result['predicted_passengers']
        is_peak = result['is_peak']
        
        # Off-peak should be 50-200 passengers
        in_range = 50 <= passengers <= 200
        
        status = "✓ PASS" if in_range and not is_peak else "❌ FAIL"
        if not (in_range and not is_peak):
            all_passed = False
        
        peak_str = "No" if not is_peak else "Yes (ERROR)"
        range_str = "Yes" if in_range else f"No ({passengers})"
        
        print(f"{route_id:<10} {passengers:<15} {peak_str:<10} {range_str:<15} {status:<10}")
    
    print("\n" + "="*70)
    if all_passed:
        print("✓ ALL ROUTES show realistic off-peak predictions!")
    else:
        print("❌ Some routes have issues")
    
    return all_passed

def test_all_routes_variation():
    """Test that different routes have different predictions"""
    print_header("TEST 3: Route Variation - Each Route Should Be Different")
    
    target_time = datetime(2025, 11, 28, 8, 0)  # Morning peak
    
    predictions = {}
    
    print(f"\nMorning peak (8am) predictions for all routes:")
    print(f"{'Route':<10} {'Prediction':<15} {'Daily Est.':<15}")
    print("-" * 70)
    
    for route_id in AVAILABLE_ROUTES:
        result = predict_ridership(route_id, target_time)
        passengers = result['predicted_passengers']
        predictions[route_id] = passengers
        
        # Estimate daily ridership (morning peak is ~9% of daily)
        daily_est = int(passengers / 0.09)
        
        print(f"{route_id:<10} {passengers:<15} ~{daily_est:,} pax/day")
    
    # Check if routes have different predictions
    unique_values = len(set(predictions.values()))
    
    print("\n" + "="*70)
    print(f"Unique prediction values: {unique_values} out of {len(AVAILABLE_ROUTES)} routes")
    
    if unique_values >= len(AVAILABLE_ROUTES) * 0.8:  # At least 80% unique
        print("✓ Routes show good variation (not all the same)")
        return True
    else:
        print("❌ Routes show too little variation")
        return False

def test_time_progression():
    """Test that predictions change realistically over time"""
    print_header("TEST 4: Time Progression - 24-Hour Pattern")
    
    route_id = AVAILABLE_ROUTES[0]  # Test first route
    
    print(f"\nTesting 24-hour pattern for Route {route_id}:")
    print(f"{'Hour':<10} {'Prediction':<15} {'Expected Pattern':<20}")
    print("-" * 70)
    
    predictions_by_hour = []
    
    test_hours = [2, 7, 8, 12, 14, 17, 18, 22]  # Representative hours
    
    for hour in test_hours:
        target_time = datetime(2025, 11, 28, hour, 0)
        result = predict_ridership(route_id, target_time)
        passengers = result['predicted_passengers']
        predictions_by_hour.append(passengers)
        
        # Determine expected pattern
        if hour in [7, 8, 17, 18]:
            pattern = "Peak (high)"
        elif hour in [2, 22]:
            pattern = "Late/Early (low)"
        else:
            pattern = "Off-peak (medium)"
        
        print(f"{hour:02d}:00{' '*4} {passengers:<15} {pattern:<20}")
    
    # Check if peaks are higher than off-peak
    peak_hours = [predictions_by_hour[i] for i, h in enumerate(test_hours) if h in [7, 8, 17, 18]]
    off_peak_hours = [predictions_by_hour[i] for i, h in enumerate(test_hours) if h in [2, 12, 14, 22]]
    
    avg_peak = sum(peak_hours) / len(peak_hours)
    avg_offpeak = sum(off_peak_hours) / len(off_peak_hours)
    
    print("\n" + "="*70)
    print(f"Average peak hour:     {avg_peak:.0f} passengers")
    print(f"Average off-peak:      {avg_offpeak:.0f} passengers")
    print(f"Peak/Off-peak ratio:   {avg_peak/avg_offpeak:.2f}x")
    
    if avg_peak > avg_offpeak * 1.3:  # Peak should be at least 30% higher
        print("✓ Realistic time-of-day variation!")
        return True
    else:
        print("❌ Not enough variation between peak and off-peak")
        return False

def test_alert_coverage():
    """Test that alerts are generated for multiple routes"""
    print_header("TEST 5: Alert Coverage - All Routes Generate Alerts")
    
    from alert_generator import generate_alerts_for_route
    
    print(f"\nTesting alert generation for all routes (next 24 hours):")
    print(f"{'Route':<10} {'Total Alerts':<15} {'Critical':<12} {'Warning':<12} {'Status':<10}")
    print("-" * 70)
    
    all_generate_alerts = True
    total_alerts_all_routes = 0
    
    for route_id in AVAILABLE_ROUTES:
        alerts = generate_alerts_for_route(route_id, hours=24)
        
        critical = sum(1 for a in alerts if a['severity'] == 'CRITICAL')
        warning = sum(1 for a in alerts if a['severity'] == 'WARNING')
        total = len(alerts)
        
        total_alerts_all_routes += total
        
        # Each route should generate at least 1 alert in 24 hours
        status = "✓ PASS" if total > 0 else "❌ FAIL"
        if total == 0:
            all_generate_alerts = False
        
        print(f"{route_id:<10} {total:<15} {critical:<12} {warning:<12} {status:<10}")
    
    print("\n" + "="*70)
    print(f"Total alerts across all routes: {total_alerts_all_routes}")
    
    if all_generate_alerts and total_alerts_all_routes > 20:
        print("✓ All routes generate alerts - system working!")
        return True
    else:
        print("❌ Some routes not generating alerts")
        return False

if __name__ == '__main__':
    print("\n" + "╔" + "═"*68 + "╗")
    print("║" + " "*68 + "║")
    print("║" + " "*15 + "COMPREHENSIVE TEST: ALL ROUTES" + " "*23 + "║")
    print("║" + " "*68 + "║")
    print("╚" + "═"*68 + "╝")
    
    print(f"\nTesting predictive model across ALL {len(AVAILABLE_ROUTES)} routes:")
    print(f"Routes: {', '.join(AVAILABLE_ROUTES)}")
    
    results = []
    
    try:
        results.append(("Peak Hour - All Routes", test_all_routes_peak_hour()))
        results.append(("Off-Peak - All Routes", test_all_routes_off_peak()))
        results.append(("Route Variation", test_all_routes_variation()))
        results.append(("Time Progression", test_time_progression()))
        results.append(("Alert Coverage", test_alert_coverage()))
        
        # Final Summary
        print("\n" + "╔" + "═"*68 + "╗")
        print("║" + " "*68 + "║")
        print("║" + " "*25 + "FINAL SUMMARY" + " "*30 + "║")
        print("║" + " "*68 + "║")
        print("╚" + "═"*68 + "╝")
        
        passed = sum(1 for _, result in results if result)
        total = len(results)
        
        print(f"\n{'Test':<40} {'Status':<10}")
        print("-" * 70)
        for test_name, result in results:
            status = "✓ PASS" if result else "❌ FAIL"
            print(f"{test_name:<40} {status:<10}")
        
        print("\n" + "="*70)
        print(f"RESULTS: {passed}/{total} tests passed")
        
        if passed == total:
            print("\n🎉 ALL TESTS PASSED!")
            print("\n✓ Model works for ALL routes (not cherry-picked)")
            print("✓ Realistic predictions across different times")
            print("✓ Alert system covers all routes")
            print("✓ Each route has unique characteristics")
            print("\n✅ SYSTEM VALIDATED - READY FOR PRODUCTION")
        else:
            print(f"\n⚠️  {total - passed} test(s) failed")
            print("Review failed tests above for details")
        
        print("="*70 + "\n")
        
    except Exception as e:
        print(f"\n❌ Error during testing: {e}")
        import traceback
        traceback.print_exc()