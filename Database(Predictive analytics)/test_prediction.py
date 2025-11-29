"""
ENHANCED TEST: Prediction Functions with Detailed Diagnostics
"""
from datetime import datetime, timedelta
from prediction_functions import predict_ridership, predict_multiple_hours

def print_test_header(test_num, test_name):
    """Print formatted test header"""
    print(f"\n{'='*60}")
    print(f"[Test {test_num}] {test_name}")
    print(f"{'='*60}")

def print_result(passed, expected, actual, message=""):
    """Print test result with details"""
    status = "✓ PASS" if passed else "❌ FAIL"
    print(f"\n{status}")
    if not passed:
        print(f"  Expected: {expected}")
        print(f"  Actual:   {actual}")
        if message:
            print(f"  Note:     {message}")

def test_single_prediction():
    """Test single prediction"""
    print_test_header(1, "Single Route Prediction")
    
    route_id = '118'
    target_time = datetime(2025, 10, 27, 8, 0)
    
    result = predict_ridership(route_id, target_time)
    
    print(f"Route:      {result['route_id']}")
    print(f"Time:       {result['datetime']}")
    print(f"Prediction: {result['predicted_passengers']} passengers")
    print(f"Confidence: {result['confidence']*100:.0f}%")
    print(f"Peak Hour:  {'Yes' if result['is_peak'] else 'No'}")
    
    passed = result['predicted_passengers'] > 0
    print_result(passed, ">0", result['predicted_passengers'])
    
    return passed

def test_peak_detection():
    """Test peak hour detection"""
    print_test_header(2, "Peak Hour Detection & High Ridership")
    
    route_id = '118'
    target_time = datetime(2025, 10, 27, 18, 0)  # Evening rush
    
    result = predict_ridership(route_id, target_time)
    
    print(f"Route:      {result['route_id']}")
    print(f"Time:       {result['datetime']} (Evening Rush Hour)")
    print(f"Prediction: {result['predicted_passengers']} passengers")
    print(f"Peak Hour:  {'Yes' if result['is_peak'] else 'No'}")
    print(f"Confidence: {result['confidence']*100:.0f}%")
    
    # Check both peak detection AND high ridership
    is_peak = result['is_peak']
    has_high_ridership = result['predicted_passengers'] > 150
    
    print(f"\nChecks:")
    print(f"  Peak detected:     {'✓' if is_peak else '❌'} {is_peak}")
    print(f"  High ridership:    {'✓' if has_high_ridership else '❌'} "
          f"({result['predicted_passengers']} > 150)")
    
    passed = is_peak and has_high_ridership
    
    if not passed:
        message = "Peak hours should predict 150+ passengers for realistic bus ridership"
    else:
        message = ""
    
    print_result(passed, ">150 passengers", result['predicted_passengers'], message)
    
    return passed

def test_off_peak():
    """Test off-peak prediction"""
    print_test_header(3, "Off-Peak Prediction")
    
    route_id = '118'
    target_time = datetime(2025, 10, 27, 14, 0)  # Mid-afternoon
    
    result = predict_ridership(route_id, target_time)
    
    print(f"Route:      {result['route_id']}")
    print(f"Time:       {result['datetime']} (Mid-afternoon)")
    print(f"Prediction: {result['predicted_passengers']} passengers")
    print(f"Peak Hour:  {'Yes' if result['is_peak'] else 'No'}")
    
    passed = not result['is_peak']
    print_result(passed, "Not peak hour", "Peak" if result['is_peak'] else "Not peak")
    
    return passed

def test_weekend():
    """Test weekend prediction"""
    print_test_header(4, "Weekend Prediction")
    
    route_id = '118'
    today = datetime.now()
    days_ahead = 5 - today.weekday()  # Saturday is 5
    if days_ahead <= 0:
        days_ahead += 7
    saturday = today + timedelta(days=days_ahead)
    target_time = saturday.replace(hour=10, minute=0, second=0, microsecond=0)
    
    result = predict_ridership(route_id, target_time)
    
    print(f"Route:      {result['route_id']}")
    print(f"Time:       {result['datetime']} (Saturday)")
    print(f"Prediction: {result['predicted_passengers']} passengers")
    print(f"Weekend:    {result['features']['is_weekend']}")
    
    passed = result['features']['is_weekend']
    print_result(passed, "Weekend", "Weekend" if passed else "Weekday")
    
    return passed

def test_multi_hour():
    """Test multi-hour forecast"""
    print_test_header(5, "Multi-Hour Forecast (Next 6 Hours)")
    
    route_id = '118'
    predictions = predict_multiple_hours(route_id, hours=6)
    
    print(f"Route: {route_id}")
    print(f"\n{'Time':<8} {'Passengers':<12} {'Confidence':<12} {'Peak':<8}")
    print("-" * 60)
    
    for pred in predictions:
        dt = datetime.fromisoformat(pred['datetime'])
        confidence = pred['confidence'] * 100
        peak_indicator = "🔴 PEAK" if pred['is_peak'] else ""
        
        print(f"{dt.strftime('%H:%M'):<8} {pred['predicted_passengers']:<12} "
              f"{confidence:>5.0f}%{' '*7} {peak_indicator:<8}")
    
    peak_pred = max(predictions, key=lambda x: x['predicted_passengers'])
    peak_time = datetime.fromisoformat(peak_pred['datetime'])
    
    print(f"\nPeak expected at: {peak_time.strftime('%H:%M')} "
          f"({peak_pred['predicted_passengers']} passengers)")
    
    # Check all predictions are valid
    all_positive = all(p['predicted_passengers'] >= 0 for p in predictions)
    correct_count = len(predictions) == 6
    has_variation = len(set(p['predicted_passengers'] for p in predictions)) > 1
    
    print(f"\nChecks:")
    print(f"  Correct count:     {'✓' if correct_count else '❌'} {len(predictions)} == 6")
    print(f"  All positive:      {'✓' if all_positive else '❌'}")
    print(f"  Has variation:     {'✓' if has_variation else '❌'} (not all same value)")
    
    passed = correct_count and all_positive
    print_result(passed, "6 predictions, all >= 0", f"{len(predictions)} predictions")
    
    return passed

def test_realistic_ranges():
    """Test if predictions fall within realistic ranges"""
    print_test_header(6, "BONUS: Realistic Value Ranges")
    
    route_id = '123'
    
    # Test various times
    test_cases = [
        (8, "Morning Peak", 150, 400),
        (18, "Evening Peak", 150, 400),
        (12, "Lunch Time", 50, 150),
        (23, "Late Night", 10, 80),
        (3, "Early Morning", 5, 50),
    ]
    
    all_passed = True
    
    print(f"\n{'Time':<18} {'Expected Range':<20} {'Actual':<12} {'Status':<8}")
    print("-" * 60)
    
    for hour, description, min_exp, max_exp in test_cases:
        target_time = datetime(2025, 10, 27, hour, 0)
        result = predict_ridership(route_id, target_time)
        actual = result['predicted_passengers']
        
        in_range = min_exp <= actual <= max_exp
        status = "✓" if in_range else "❌"
        
        print(f"{description:<18} {min_exp:>3}-{max_exp:<3} pax{' '*8} {actual:>4} pax{' '*4} {status:<8}")
        
        if not in_range:
            all_passed = False
    
    print(f"\n{'='*60}")
    if all_passed:
        print("✓ All predictions within realistic ranges!")
        print("  Your model is producing REALISTIC ridership patterns.")
    else:
        print("❌ Some predictions outside realistic ranges")
        print("  Possible causes:")
        print("  1. Training data has unrealistic passenger counts")
        print("  2. Model needs retraining with better data")
        print("\n  Solution: Run python fix_training_data.py then retrain")
    
    return all_passed

if __name__ == '__main__':
    print("╔" + "═"*58 + "╗")
    print("║" + " "*58 + "║")
    print("║" + " "*12 + "ENHANCED PREDICTION FUNCTION TESTS" + " "*12 + "║")
    print("║" + " "*58 + "║")
    print("╚" + "═"*58 + "╝")
    
    results = []
    
    try:
        results.append(("Single Prediction", test_single_prediction()))
        results.append(("Peak Detection", test_peak_detection()))
        results.append(("Off-Peak", test_off_peak()))
        results.append(("Weekend", test_weekend()))
        results.append(("Multi-Hour", test_multi_hour()))
        results.append(("Realistic Ranges", test_realistic_ranges()))
        
        # Summary
        print(f"\n{'='*60}")
        print("TEST SUMMARY")
        print(f"{'='*60}")
        
        passed_count = sum(1 for _, passed in results if passed)
        total_count = len(results)
        
        for test_name, passed in results:
            status = "✓ PASS" if passed else "❌ FAIL"
            print(f"{status}  {test_name}")
        
        print(f"{'='*60}")
        print(f"Results: {passed_count}/{total_count} tests passed")
        
        if passed_count == total_count:
            print("\n🎉 ALL TESTS PASSED! Your model is working correctly!")
        else:
            print(f"\n⚠️  {total_count - passed_count} test(s) failed")
            print("\nTo fix:")
            print("  1. Run: python fix_training_data.py")
            print("  2. Run: python train_model.py")
            print("  3. Run this test again")
        
        print(f"{'='*60}\n")
    
    except Exception as e:
        print(f"\n❌ Error during testing: {e}")
        import traceback
        traceback.print_exc()