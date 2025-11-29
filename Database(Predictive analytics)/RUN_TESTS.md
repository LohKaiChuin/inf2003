# Live MySQL Data & Predictions Test Guide

This guide will help you test the complete pipeline: MySQL connection → Live data → ML predictions → Database storage

## Prerequisites

1. SSH access to the remote MySQL database
2. PHP installed (check with `php --version`)
3. Python 3 with required packages

## Step-by-Step Instructions

### Step 1: Start the SSH Tunnel

Open a **NEW TERMINAL WINDOW** and run:

```bash
cd "/Users/justin/Desktop/MODS/Y2T1/INF2003 Database System/Project/ethan help/Database(Predictive analytics)"
./start_ssh_tunnel.sh
```

Or manually:

```bash
ssh -L 33060:127.0.0.1:3306 inf2003-dev@104.198.169.207
```

**Important:**
- Keep this terminal window open during testing
- You may need to enter your SSH password
- You should see "Connection established" or similar message
- The tunnel will stay open until you close it with Ctrl+C

### Step 2: Verify the SSH Tunnel

In a **DIFFERENT TERMINAL**, check if the tunnel is working:

```bash
lsof -i :33060
```

You should see a line with `ssh` and port `33060`

### Step 3: Start the PHP API Server

The PHP server should already be running. Verify with:

```bash
curl "http://localhost:8000/Analytics_api.php?action=health"
```

You should see:
```json
{
    "status": "healthy",
    "database": "connected",
    "timestamp": "..."
}
```

If the server is not running:

```bash
cd "/Users/justin/Desktop/MODS/Y2T1/INF2003 Database System/Project/ethan help/Database(Predictive analytics)"
php -S localhost:8000
```

### Step 4: Run the Comprehensive Test

Now run the main test script:

```bash
cd "/Users/justin/Desktop/MODS/Y2T1/INF2003 Database System/Project/ethan help/Database(Predictive analytics)"
python test_live_predictions.py
```

## What the Test Does

The test script will:

1. ✅ **Test MySQL Connection** - Verify connection to database via PHP API
2. ✅ **Fetch Live Data** - Retrieve actual bus volume data from BusVolume table
3. ✅ **Run Predictions** - Use the trained ML model to predict ridership
4. ✅ **Save to Database** - Store predictions in the Predictions table
5. ✅ **Verify Storage** - Confirm predictions were saved correctly
6. ✅ **Performance Analysis** - Test model accuracy on real data

## Expected Output

You should see detailed output like:

```
╔════════════════════════════════════════════════════════════════════╗
║          LIVE MySQL DATA & PREDICTIONS INTEGRATION TEST            ║
╚════════════════════════════════════════════════════════════════════╝

======================================================================
  TEST 1: MySQL Connection via PHP API
======================================================================

✓ MySQL connection successful
Database Info:
  Earliest data: 202107
  Latest data:   202109
  Total records: 1,234,567

======================================================================
  TEST 2: Fetch Live Data from BusVolume
======================================================================

✓ Retrieved 672 hourly records
...
```

## Troubleshooting

### Error: "Connection refused"
- **Cause:** SSH tunnel is not running
- **Solution:** Complete Step 1 above

### Error: "404 Not Found"
- **Cause:** PHP server not running or wrong directory
- **Solution:** Restart PHP server from correct directory (Step 3)

### Error: "Model file not found"
- **Cause:** ML model hasn't been trained yet
- **Solution:** Run `python train_model.py` first

### Error: "No module named 'tabulate'"
- **Cause:** Missing Python package
- **Solution:** Run `pip install tabulate`

## After Successful Tests

Once all tests pass, you can:

1. Start the Flask API server:
   ```bash
   python api.py
   ```

2. Test all API routes:
   ```bash
   python Test_All_Routes.py
   ```

3. Make predictions via API:
   ```bash
   curl "http://localhost:5001/analytics/predictions?route=118&hours=6&save=true"
   ```

## Database Schema

The **Predictions** table stores:
- `prediction_id` - Unique ID
- `route_id` - Bus route (e.g., '118')
- `prediction_datetime` - When prediction is for
- `predicted_passengers` - Number of passengers predicted
- `confidence` - Model confidence (0.0 - 1.0)
- `is_peak` - Whether it's peak hour (0 or 1)
- `model_version` - Version of ML model used
- `created_at` - When prediction was created

## Quick Test Command

For a quick test without details:

```bash
# 1. Start SSH tunnel (in separate terminal)
ssh -L 33060:127.0.0.1:3306 inf2003-dev@104.198.169.207

# 2. Run test (in another terminal)
cd "/Users/justin/Desktop/MODS/Y2T1/INF2003 Database System/Project/ethan help/Database(Predictive analytics)"
python test_live_predictions.py
```

## Notes

- The trained model is stored in: `models/ridership_model.pkl`
- PHP API endpoint: `http://localhost:8000/Analytics_api.php`
- Flask API will run on: `http://localhost:5001`
- All predictions are stored in the MySQL `Predictions` table
