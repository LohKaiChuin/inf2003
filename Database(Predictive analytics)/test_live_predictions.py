"""
Test Live MySQL Data Integration with Predictions
This script tests the complete pipeline:
1. Connect to MySQL via PHP API
2. Fetch live bus volume data
3. Run predictions using the analytical model
4. Save predictions to the Predictions table
5. Verify stored predictions
"""
import sys
from datetime import datetime, timedelta
import pandas as pd
from tabulate import tabulate

from APIClient import get_api_client
from prediction_functions import load_model, predict_ridership, predict_multiple_hours
from config import AVAILABLE_ROUTES, API_BASE_URL

def print_section(title):
    """Print formatted section header"""
    print("\n" + "="*70)
    print(f"  {title}")
    print("="*70 + "\n")

def test_mysql_connection():
    """Test 1: Verify MySQL connection via PHP API"""
    print_section("TEST 1: MySQL Connection via PHP API")

    try:
        api = get_api_client(API_BASE_URL)

        # Test health check
        if api.test_connection():
            print("✓ MySQL connection successful")

            # Get data range info
            date_range = api.get_data_date_range()
            print(f"\nDatabase Info:")
            print(f"  Earliest data: {date_range['earliest_month']}")
            print(f"  Latest data:   {date_range['latest_month']}")
            print(f"  Total records: {date_range['total_records']:,}")

            return api, True
        else:
            print("✗ MySQL connection failed")
            return None, False

    except Exception as e:
        print(f"✗ Connection test failed: {e}")
        return None, False

def test_fetch_live_data(api):
    """Test 2: Fetch live data from BusVolume table"""
    print_section("TEST 2: Fetch Live Data from BusVolume")

    try:
        # Get available routes
        routes = api.get_all_routes()
        print(f"✓ Total routes available: {len(routes)}")

        # Get available months
        months = api.get_available_months()
        print(f"✓ Data available for {len(months)} months")
        print(f"  Month range: {months[0]} to {months[-1]}")

        # Fetch data for Route 118 (latest month)
        latest_month = months[-1]
        print(f"\n📊 Fetching live data for Route 118, Month {latest_month}...")

        volume_data = api.get_bus_volume_by_route('118', month=latest_month)

        if not volume_data.empty:
            print(f"✓ Retrieved {len(volume_data)} hourly records")
            print(f"\nData Summary:")
            print(f"  Total passengers: {volume_data['total_passengers'].sum():,}")
            print(f"  Average hourly:   {volume_data['total_passengers'].mean():.0f}")
            print(f"  Peak hour:        {volume_data['total_passengers'].max()}")

            # Display sample data
            print(f"\n📋 Sample Live Data (First 10 records):")
            print(tabulate(
                volume_data.head(10),
                headers='keys',
                tablefmt='grid',
                showindex=False
            ))

            return volume_data, True
        else:
            print("✗ No data retrieved")
            return None, False

    except Exception as e:
        print(f"✗ Data fetch failed: {e}")
        import traceback
        traceback.print_exc()
        return None, False

def test_model_predictions(api):
    """Test 3: Run predictions using the analytical model"""
    print_section("TEST 3: Run Predictions with Analytical Model")

    try:
        # Load the trained model
        print("Loading trained model...")
        model = load_model()
        print("✓ Model loaded successfully")

        # Test single prediction
        print(f"\n🔮 Generating prediction for Route 118 (now)...")
        now = datetime.now()

        single_pred = predict_ridership(
            route_id='118',
            target_datetime=now,
            model=model,
            db_client=api,
            save_to_db=False  # Don't save yet
        )

        print(f"\n📊 Single Prediction Result:")
        print(f"  Route:              {single_pred['route_id']}")
        print(f"  Time:               {single_pred['datetime']}")
        print(f"  Predicted Pax:      {single_pred['predicted_passengers']}")
        print(f"  Confidence:         {single_pred['confidence']*100:.1f}%")
        print(f"  Peak Hour:          {'Yes' if single_pred['is_peak'] else 'No'}")
        print(f"\n  Features Used:")
        for feat, val in single_pred['features'].items():
            print(f"    {feat:<25} {val}")

        # Test multi-hour predictions
        print(f"\n🔮 Generating 6-hour forecast for Route 118...")
        predictions = predict_multiple_hours(
            route_id='118',
            hours=6,
            model=model,
            db_client=api,
            save_to_db=False  # Don't save yet
        )

        print(f"\n📊 6-Hour Forecast:")
        forecast_table = []
        for pred in predictions:
            dt = datetime.fromisoformat(pred['datetime'])
            forecast_table.append([
                dt.strftime('%Y-%m-%d %H:%M'),
                pred['predicted_passengers'],
                f"{pred['confidence']*100:.1f}%",
                '🔴 Peak' if pred['is_peak'] else '⚪ Normal'
            ])

        print(tabulate(
            forecast_table,
            headers=['Time', 'Predicted Pax', 'Confidence', 'Status'],
            tablefmt='grid'
        ))

        return model, predictions, True

    except Exception as e:
        print(f"✗ Prediction failed: {e}")
        import traceback
        traceback.print_exc()
        return None, None, False

def test_save_predictions(api, model):
    """Test 4: Save predictions to Predictions table"""
    print_section("TEST 4: Save Predictions to MySQL Predictions Table")

    try:
        print("Generating predictions and saving to database...")

        # Generate predictions for next 12 hours with save enabled
        predictions = predict_multiple_hours(
            route_id='118',
            hours=12,
            model=model,
            db_client=api,
            save_to_db=True  # SAVE TO DATABASE
        )

        print(f"✓ Generated and saved {len(predictions)} predictions to Predictions table")

        # Also test predictions for a few other routes
        other_routes = ['10', '100', '105']
        for route in other_routes:
            print(f"\nGenerating predictions for Route {route}...")
            route_preds = predict_multiple_hours(
                route_id=route,
                hours=6,
                model=model,
                db_client=api,
                save_to_db=True
            )
            print(f"✓ Saved {len(route_preds)} predictions for Route {route}")

        print(f"\n✅ All predictions saved successfully!")
        return True

    except Exception as e:
        print(f"✗ Failed to save predictions: {e}")
        import traceback
        traceback.print_exc()
        return False

def test_verify_predictions(api):
    """Test 5: Verify stored predictions in database"""
    print_section("TEST 5: Verify Stored Predictions in Database")

    try:
        # Retrieve recent predictions from database
        print("Retrieving predictions from Predictions table...")

        # Get predictions for Route 118
        predictions = api.get_predictions(
            route_id='118',
            start_date=datetime.now() - timedelta(hours=1),
            limit=20
        )

        if predictions:
            print(f"✓ Retrieved {len(predictions)} predictions from database")

            print(f"\n📋 Stored Predictions (Latest 10):")
            pred_table = []
            for pred in predictions[:10]:
                pred_table.append([
                    pred['prediction_id'],
                    pred['route_id'],
                    pred['prediction_datetime'],
                    pred['predicted_passengers'],
                    f"{pred['confidence']*100:.1f}%",
                    '🔴' if pred['is_peak'] else '⚪',
                    pred['model_version']
                ])

            print(tabulate(
                pred_table,
                headers=['ID', 'Route', 'DateTime', 'Predicted Pax', 'Confidence', 'Peak', 'Model'],
                tablefmt='grid'
            ))

            # Get summary statistics
            print(f"\n📊 Predictions Summary:")
            all_preds = api.get_predictions(limit=1000)
            if all_preds:
                routes = set(p['route_id'] for p in all_preds)
                avg_pax = sum(p['predicted_passengers'] for p in all_preds) / len(all_preds)
                avg_conf = sum(p['confidence'] for p in all_preds) / len(all_preds)
                peak_count = sum(1 for p in all_preds if p['is_peak'])

                print(f"  Total predictions stored: {len(all_preds)}")
                print(f"  Routes covered:           {len(routes)}")
                print(f"  Average passengers:       {avg_pax:.0f}")
                print(f"  Average confidence:       {avg_conf*100:.1f}%")
                print(f"  Peak hour predictions:    {peak_count} ({peak_count/len(all_preds)*100:.1f}%)")

            return True
        else:
            print("⚠️  No predictions found in database")
            return False

    except Exception as e:
        print(f"✗ Verification failed: {e}")
        import traceback
        traceback.print_exc()
        return False

def test_model_performance(api, model):
    """Bonus Test: Evaluate model performance on recent data"""
    print_section("BONUS: Model Performance Analysis")

    try:
        # Get latest month data
        months = api.get_available_months()
        latest_month = months[-1]

        print(f"Testing model on data from month {latest_month}...")

        # Get actual data
        actual_data = api.get_bus_volume_by_route('118', month=latest_month)

        if not actual_data.empty:
            # Sample 100 random records for testing
            sample_size = min(100, len(actual_data))
            sample = actual_data.sample(n=sample_size, random_state=42)

            errors = []
            for _, row in sample.iterrows():
                # Create datetime for this record
                # Note: Using day 15 as mid-month estimate
                target_dt = datetime(2021, 7, 15, int(row['hour']))

                # Make prediction
                pred = predict_ridership(
                    route_id='118',
                    target_datetime=target_dt,
                    model=model,
                    db_client=api,
                    save_to_db=False
                )

                # Calculate error
                actual = row['total_passengers']
                predicted = pred['predicted_passengers']
                error = abs(actual - predicted)
                errors.append(error)

            # Calculate metrics
            mae = sum(errors) / len(errors)
            within_10 = sum(1 for e in errors if e <= 10) / len(errors) * 100
            within_20 = sum(1 for e in errors if e <= 20) / len(errors) * 100
            within_30 = sum(1 for e in errors if e <= 30) / len(errors) * 100

            print(f"\n📊 Model Performance (on {sample_size} samples):")
            print(f"  Mean Absolute Error:  {mae:.2f} passengers")
            print(f"  Accuracy ±10 pax:     {within_10:.1f}%")
            print(f"  Accuracy ±20 pax:     {within_20:.1f}%")
            print(f"  Accuracy ±30 pax:     {within_30:.1f}%")

            return True
        else:
            print("⚠️  No data available for performance testing")
            return False

    except Exception as e:
        print(f"✗ Performance analysis failed: {e}")
        import traceback
        traceback.print_exc()
        return False

def main():
    """Main test orchestrator"""
    print("\n" + "╔" + "═"*68 + "╗")
    print("║" + " "*68 + "║")
    print("║" + " "*10 + "LIVE MySQL DATA & PREDICTIONS INTEGRATION TEST" + " "*12 + "║")
    print("║" + " "*68 + "║")
    print("╚" + "═"*68 + "╝")

    print(f"\n📅 Test Started: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print(f"🔗 API URL: {API_BASE_URL}")

    results = {
        'connection': False,
        'fetch_data': False,
        'predictions': False,
        'save': False,
        'verify': False,
        'performance': False
    }

    # Test 1: MySQL Connection
    api, results['connection'] = test_mysql_connection()
    if not results['connection']:
        print("\n❌ MySQL connection failed. Please check:")
        print("  1. SSH tunnel is running: ssh -L 33060:127.0.0.1:3306 inf2003-dev@35.212.180.159")
        print("  2. PHP server is running: php -S localhost:8000")
        print("  3. analytics_api.php is accessible")
        sys.exit(1)

    # Test 2: Fetch Live Data
    volume_data, results['fetch_data'] = test_fetch_live_data(api)

    # Test 3: Model Predictions
    model, predictions, results['predictions'] = test_model_predictions(api)

    # Test 4: Save Predictions
    if model:
        results['save'] = test_save_predictions(api, model)

    # Test 5: Verify Predictions
    results['verify'] = test_verify_predictions(api)

    # Bonus: Model Performance
    if model:
        results['performance'] = test_model_performance(api, model)

    # Final Summary
    print_section("TEST SUMMARY")

    test_results = [
        ['MySQL Connection', '✅ PASS' if results['connection'] else '❌ FAIL'],
        ['Fetch Live Data', '✅ PASS' if results['fetch_data'] else '❌ FAIL'],
        ['Model Predictions', '✅ PASS' if results['predictions'] else '❌ FAIL'],
        ['Save to Database', '✅ PASS' if results['save'] else '❌ FAIL'],
        ['Verify Storage', '✅ PASS' if results['verify'] else '❌ FAIL'],
        ['Performance Test', '✅ PASS' if results['performance'] else '❌ FAIL']
    ]

    print(tabulate(test_results, headers=['Test', 'Result'], tablefmt='grid'))

    passed = sum(results.values())
    total = len(results)

    print(f"\n📊 Overall: {passed}/{total} tests passed ({passed/total*100:.0f}%)")

    if all(results.values()):
        print("\n" + "="*70)
        print("🎉 ALL TESTS PASSED! 🎉")
        print("="*70)
        print("\n✅ Your system is working correctly:")
        print("   • MySQL connection via PHP API: ✓")
        print("   • Live data retrieval: ✓")
        print("   • ML model predictions: ✓")
        print("   • Database storage: ✓")
        print("\n📌 Next steps:")
        print("   1. Start the Flask API: python api.py")
        print("   2. Test API endpoints: python Test_All_Routes.py")
        print("   3. Integrate with frontend application")
        print("="*70)
    else:
        print("\n⚠️  Some tests failed. Please review the errors above.")

    print(f"\n📅 Test Completed: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}\n")

if __name__ == '__main__':
    try:
        main()
    except KeyboardInterrupt:
        print("\n\n⚠️  Test interrupted by user")
        sys.exit(1)
    except Exception as e:
        print(f"\n\n❌ Fatal error: {e}")
        import traceback
        traceback.print_exc()
        sys.exit(1)
