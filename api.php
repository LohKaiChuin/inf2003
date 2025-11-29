<?php
/**
 * YourTrip Analytics API
 * Handles all data requests via query parameters
 *
 * Usage: /api.php?action={action_name}
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'config.php';

// Get action parameter
$action = $_GET['action'] ?? '';

try {
    $pdo = getDBConnection();

    switch ($action) {
        case 'summary':
            echo json_encode(getSummaryData($pdo));
            break;

        case 'ridership_trends':
            echo json_encode(getRidershipTrends($pdo));
            break;

        case 'bus_metrics':
            echo json_encode(getBusMetrics($pdo));
            break;

        case 'peak_offpeak':
            echo json_encode(getPeakOffPeakData($pdo));
            break;

        case 'hourly_ridership':
            echo json_encode(getHourlyRidership($pdo));
            break;

        case 'busiest_stops':
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            echo json_encode(getBusiestStops($pdo, $limit));
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action parameter']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

/**
 * Get summary data for top metrics cards
 */
function getSummaryData($pdo) {
    $result = [];

    // Transport hubs - bus stops
    $stmt = $pdo->query("SELECT COUNT(DISTINCT BUS_STOP) as total_stops FROM BusStops");
    $busStops = $stmt->fetch()['total_stops'];

    // Transport hubs - MRT stations
    $stmt = $pdo->query("SELECT COUNT(DISTINCT stop_id) as total_mrt FROM MRTStations");
    $mrtStations = $stmt->fetch()['total_mrt'];

    $result['total_hubs'] = (int)$busStops + (int)$mrtStations;

    // Daily ridership (latest month average)
    $stmt = $pdo->query("
        SELECT SUM(ridership) / 30 as avg_daily_ridership
        FROM Ridership
        WHERE CONCAT(year, '-', LPAD(month, 2, '0')) = (
            SELECT MAX(CONCAT(year, '-', LPAD(month, 2, '0')))
            FROM Ridership
        )
    ");
    $daily = $stmt->fetch();
    $result['avg_daily_ridership'] = $daily ? (int)$daily['avg_daily_ridership'] : 0;

    // Most popular mode
    $stmt = $pdo->query("
        SELECT mode, SUM(ridership) as total_ridership
        FROM Ridership
        GROUP BY mode
        ORDER BY total_ridership DESC
        LIMIT 1
    ");
    $popular = $stmt->fetch();
    $result['most_popular_mode'] = $popular ? $popular['mode'] : 'N/A';

    // Peak month
    $stmt = $pdo->query("
        SELECT
            month,
            year,
            date as peak_month,
            SUM(ridership) as total_ridership
        FROM Ridership
        GROUP BY year, month, date
        ORDER BY total_ridership DESC
        LIMIT 1
    ");
    $peakMonth = $stmt->fetch();
    $result['peak_month'] = $peakMonth ? $peakMonth['peak_month'] : 'N/A';

    // Transport modes count
    $stmt = $pdo->query("SELECT COUNT(DISTINCT mode) as mode_count FROM Ridership");
    $modes = $stmt->fetch();
    $result['transport_modes'] = (int)$modes['mode_count'];

    return $result;
}

/**
 * Get ridership trends data for stacked area chart
 */
function getRidershipTrends($pdo) {
    $stmt = $pdo->query("
        SELECT
            date,
            year,
            month,
            mode as label,
            ridership as total_passengers
        FROM Ridership
        ORDER BY year ASC, month ASC, mode ASC
    ");

    $data = [];
    while ($row = $stmt->fetch()) {
        $data[] = [
            'date' => $row['date'],
            'year' => (int)$row['year'],
            'month' => (int)$row['month'],
            'label' => $row['label'],
            'total_passengers' => (int)$row['total_passengers']
        ];
    }

    return $data;
}

/**
 * Get bus-specific metrics with nested queries
 */
function getBusMetrics($pdo) {
    $result = [];

    // Total routes
    $stmt = $pdo->query("SELECT COUNT(DISTINCT ServiceNo) as total_routes FROM Routes");
    $routes = $stmt->fetch();
    $result['total_routes'] = (int)$routes['total_routes'];

    // Single operator stops (NESTED QUERY)
    $stmt = $pdo->query("
        SELECT COUNT(*) as single_operator_stops
        FROM (
            SELECT BusStopCode
            FROM Routes
            GROUP BY BusStopCode
            HAVING COUNT(DISTINCT Operator) = 1
        ) subquery
    ");
    $singleOp = $stmt->fetch();
    $result['single_operator_stops'] = (int)$singleOp['single_operator_stops'];

    // Above-average ridership routes (NESTED QUERY)
    $stmt = $pdo->query("
        SELECT COUNT(*) as high_ridership_routes
        FROM (
            SELECT ServiceNo, SUM(vol_in) as total
            FROM BusVolume bv
            JOIN Routes r ON bv.stop_id = r.BusStopCode
            GROUP BY ServiceNo
            HAVING total > (
                SELECT AVG(route_total) FROM (
                    SELECT ServiceNo, SUM(vol_in) as route_total
                    FROM BusVolume bv
                    JOIN Routes r ON bv.stop_id = r.BusStopCode
                    GROUP BY ServiceNo
                ) avg_subquery
            )
        ) main_query
    ");
    $highRidership = $stmt->fetch();
    $result['high_ridership_routes'] = (int)$highRidership['high_ridership_routes'];

    // Peak hour percentage
    $stmt = $pdo->query("
        SELECT
            SUM(CASE WHEN hour IN (7,8,9,17,18,19) THEN vol_in ELSE 0 END) as peak_ridership,
            SUM(vol_in) as total_ridership
        FROM BusVolume
    ");
    $peakData = $stmt->fetch();
    if ($peakData && $peakData['total_ridership'] > 0) {
        $result['peak_hour_usage'] = round(
            ($peakData['peak_ridership'] / $peakData['total_ridership']) * 100,
            1
        );
    } else {
        $result['peak_hour_usage'] = 0;
    }

    return $result;
}

/**
 * Get peak vs off-peak data for bar chart (using averages for fair comparison)
 */
function getPeakOffPeakData($pdo) {
    // First sum by hour, then average those hour totals
    $stmt = $pdo->query("
        SELECT
            period,
            AVG(hourly_total) as boarding
        FROM (
            SELECT
                CASE
                    WHEN hour IN (7,8,9,17,18,19) THEN 'Peak Hours'
                    ELSE 'Off-Peak Hours'
                END as period,
                hour,
                SUM(vol_in) as hourly_total
            FROM BusVolume
            GROUP BY
                CASE
                    WHEN hour IN (7,8,9,17,18,19) THEN 'Peak Hours'
                    ELSE 'Off-Peak Hours'
                END,
                hour
        ) hourly_data
        GROUP BY period
    ");

    $data = [];
    while ($row = $stmt->fetch()) {
        $data[] = [
            'period' => $row['period'],
            'boarding' => (int)$row['boarding']
        ];
    }

    // Calculate percentages based on average intensity
    $total = array_sum(array_column($data, 'boarding'));
    foreach ($data as &$item) {
        $item['percentage'] = $total > 0 ? round(($item['boarding'] / $total) * 100, 1) : 0;
    }

    return $data;
}

/**
 * Get hourly ridership data for heatmap
 */
function getHourlyRidership($pdo) {
    $stmt = $pdo->query("
        SELECT
            hour,
            SUM(CASE WHEN day = 'WD' THEN vol_in ELSE 0 END) as weekday,
            SUM(CASE WHEN day = 'H' THEN vol_in ELSE 0 END) as weekend
        FROM BusVolume
        GROUP BY hour
        ORDER BY hour
    ");

    $data = [];
    while ($row = $stmt->fetch()) {
        $data[] = [
            'hour' => (int)$row['hour'],
            'weekday' => (int)$row['weekday'],
            'weekend' => (int)$row['weekend']
        ];
    }

    return $data;
}

/**
 * Get busiest bus stops for table and map
 */
function getBusiestStops($pdo, $limit = 10) {
    $stmt = $pdo->prepare("
        SELECT
            bv.stop_id,
            bs.BUS_STOP as BusStopCode,
            bs.LOC_DESC as stop_name,
            SUM(bv.vol_in) as total_boarding,
            bs.Latitude as lat,
            bs.Longitude as lng
        FROM BusVolume bv
        LEFT JOIN BusStops bs ON bv.stop_id = bs.BUS_STOP
        WHERE bs.LOC_DESC IS NOT NULL
        GROUP BY bv.stop_id, bs.BUS_STOP, bs.LOC_DESC, bs.Latitude, bs.Longitude
        ORDER BY total_boarding DESC
        LIMIT ?
    ");

    $stmt->execute([$limit]);

    $data = [];
    while ($row = $stmt->fetch()) {
        $data[] = [
            'stop_id' => $row['stop_id'],
            'BusStopCode' => $row['BusStopCode'],
            'stop_name' => $row['stop_name'],
            'total_boarding' => (int)$row['total_boarding'],
            'lat' => (float)$row['lat'],
            'lng' => (float)$row['lng']
        ];
    }

    return $data;
}
?>
