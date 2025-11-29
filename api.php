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
            $mode = isset($_GET['mode']) ? $_GET['mode'] : null;
            echo json_encode(getRidershipTrends($pdo, $mode));
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

        case 'pois':
            echo json_encode(getPOIs($pdo));
            break;

        case 'poi_by_category':
            $category = $_GET['category'] ?? '';
            echo json_encode(getPOIsByCategory($pdo, $category));
            break;

        case 'poi_stats':
            echo json_encode(getPOIStats($pdo));
            break;

        case 'intermodal_analysis':
            $stationId = isset($_GET['station_id']) ? $_GET['station_id'] : null;
            $radius = isset($_GET['radius']) ? (int)$_GET['radius'] : 500;
            if ($stationId) {
                echo json_encode(getIntermodalAnalysis($pdo, $stationId, $radius));
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Missing station_id parameter']);
            }
            break;

        case 'mrt_stations_list':
            echo json_encode(getMRTStationsList($pdo));
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
function getRidershipTrends($pdo, $mode = null) {
    // Build query with optional mode filter
    $query = "
        SELECT
            date,
            year,
            month,
            mode as label,
            ridership as total_passengers
        FROM Ridership
    ";

    // Add WHERE clause if mode is specified
    if ($mode !== null && $mode !== '' && $mode !== 'all') {
        $query .= " WHERE mode = :mode";
    }

    $query .= " ORDER BY year ASC, month ASC, mode ASC";

    $stmt = $pdo->prepare($query);

    // Bind parameter if mode is specified
    if ($mode !== null && $mode !== '' && $mode !== 'all') {
        $stmt->bindParam(':mode', $mode, PDO::PARAM_STR);
    }

    $stmt->execute();

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

/**
 * Get all POIs with transport hub information
 */
function getPOIs($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT
                id,
                name,
                rating,
                lat,
                lng,
                formatted_address,
                category,
                distance_to_hub,
                nearest_hub_id,
                planning_area
            FROM points_of_interest
            ORDER BY rating DESC, name ASC
            LIMIT 2000
        ");

        $data = [];
        while ($row = $stmt->fetch()) {
            $data[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'category' => $row['category'],
                'rating' => $row['rating'] ? (float)$row['rating'] : null,
                'latitude' => $row['lat'] ? (float)$row['lat'] : null,
                'longitude' => $row['lng'] ? (float)$row['lng'] : null,
                'description' => null,
                'location' => $row['formatted_address'],
                'distance_to_hub' => $row['distance_to_hub'] ? (int)$row['distance_to_hub'] : null,
                'nearest_hub' => $row['planning_area']
            ];
        }

        return $data;

    } catch (PDOException $e) {
        // Return empty array if table doesn't exist yet
        error_log("POI query error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get POIs filtered by category
 */
function getPOIsByCategory($pdo, $category) {
    if (empty($category)) {
        return getPOIs($pdo);
    }

    try {
        $stmt = $pdo->prepare("
            SELECT
                id,
                name,
                rating,
                lat,
                lng,
                formatted_address,
                category,
                distance_to_hub,
                nearest_hub_id,
                planning_area
            FROM points_of_interest
            WHERE category = ?
            ORDER BY rating DESC, name ASC
            LIMIT 2000
        ");

        $stmt->execute([$category]);

        $data = [];
        while ($row = $stmt->fetch()) {
            $data[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'category' => $row['category'],
                'rating' => $row['rating'] ? (float)$row['rating'] : null,
                'latitude' => $row['lat'] ? (float)$row['lat'] : null,
                'longitude' => $row['lng'] ? (float)$row['lng'] : null,
                'description' => null,
                'location' => $row['formatted_address'],
                'distance_to_hub' => $row['distance_to_hub'] ? (int)$row['distance_to_hub'] : null,
                'nearest_hub' => $row['planning_area']
            ];
        }

        return $data;

    } catch (PDOException $e) {
        error_log("POI by category query error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get POI statistics
 */
function getPOIStats($pdo) {
    // Return basic stats
    return [
        'total_pois' => 0,
        'total_categories' => 0,
        'avg_rating' => null,
        'category_distribution' => []
    ];
}

/**
 * Get list of all MRT stations
 */
function getMRTStationsList($pdo) {
    $stmt = $pdo->query("
        SELECT DISTINCT
            stop_id,
            name,
            lat,
            lng
        FROM MRTStations
        GROUP BY name, stop_id, lat, lng
        ORDER BY name ASC
    ");

    $data = [];
    $seenNames = [];

    while ($row = $stmt->fetch()) {
        // Additional check to ensure unique names in the result
        if (!in_array($row['name'], $seenNames)) {
            $data[] = [
                'stop_id' => $row['stop_id'],
                'stop_name' => $row['name'],
                'lat' => (float)$row['lat'],
                'lng' => (float)$row['lng']
            ];
            $seenNames[] = $row['name'];
        }
    }

    return $data;
}

/**
 * Calculate distance between two coordinates using Haversine formula
 * Returns distance in meters
 */
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000; // Earth's radius in meters

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    $distance = $earthRadius * $c;

    return $distance;
}

/**
 * Get intermodal transfer analysis for a specific MRT station
 * OPTIMIZED: Uses SQL-based distance calculation and single query
 */
function getIntermodalAnalysis($pdo, $stationId, $radius = 500) {
    // Get MRT station details
    $stmt = $pdo->prepare("
        SELECT
            stop_id,
            name,
            lat,
            lng
        FROM MRTStations
        WHERE stop_id = ?
    ");
    $stmt->execute([$stationId]);
    $station = $stmt->fetch();

    if (!$station) {
        return ['error' => 'MRT station not found'];
    }

    $stationLat = (float)$station['lat'];
    $stationLng = (float)$station['lng'];
    $radiusKm = $radius / 1000; // Convert to km for calculation

    // Calculate bounding box (approximate, 1 degree ≈ 111km)
    $latDelta = $radiusKm / 111;
    $lngDelta = $radiusKm / (111 * cos(deg2rad($stationLat)));

    // OPTIMIZED: Get bus stops within radius using SQL-based Haversine formula
    // This replaces the PHP loop that was fetching ALL bus stops
    $stmt = $pdo->prepare("
        SELECT
            BUS_STOP,
            LOC_DESC,
            Latitude,
            Longitude,
            (6371000 * acos(
                cos(radians(:lat1)) * cos(radians(Latitude))
                * cos(radians(Longitude) - radians(:lng1))
                + sin(radians(:lat2)) * sin(radians(Latitude))
            )) AS distance
        FROM BusStops
        WHERE LOC_DESC IS NOT NULL
            AND Latitude IS NOT NULL
            AND Longitude IS NOT NULL
            AND Latitude BETWEEN :minLat AND :maxLat
            AND Longitude BETWEEN :minLng AND :maxLng
        HAVING distance <= :radius
        ORDER BY distance
    ");

    $stmt->execute([
        'lat1' => $stationLat,
        'lat2' => $stationLat,
        'lng1' => $stationLng,
        'minLat' => $stationLat - $latDelta,
        'maxLat' => $stationLat + $latDelta,
        'minLng' => $stationLng - $lngDelta,
        'maxLng' => $stationLng + $lngDelta,
        'radius' => $radius
    ]);

    $busStops = $stmt->fetchAll();

    if (empty($busStops)) {
        return [
            'station' => [
                'stop_id' => $station['stop_id'],
                'stop_name' => $station['name'],
                'lat' => $stationLat,
                'lng' => $stationLng
            ],
            'radius' => $radius,
            'bus_stops' => [],
            'total_bus_stops' => 0,
            'unique_bus_services' => 0
        ];
    }

    // Get all stop codes for batch query
    $stopCodes = array_column($busStops, 'BUS_STOP');
    $placeholders = str_repeat('?,', count($stopCodes) - 1) . '?';

    // OPTIMIZED: Get all services for all stops in ONE query instead of N queries
    // No JOINs needed - just get unique service numbers per stop
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            r.BusStopCode,
            r.ServiceNo
        FROM Routes r
        WHERE r.BusStopCode IN ($placeholders)
        ORDER BY r.BusStopCode, r.ServiceNo
    ");
    $stmt->execute($stopCodes);
    $allServices = $stmt->fetchAll();

    // Group services by bus stop
    $servicesByStop = [];
    foreach ($allServices as $service) {
        $stopCode = $service['BusStopCode'];
        if (!isset($servicesByStop[$stopCode])) {
            $servicesByStop[$stopCode] = [];
        }
        $servicesByStop[$stopCode][] = [
            'service_no' => $service['ServiceNo']
        ];
    }

    // Build final result with services
    $busStopsWithinRadius = [];
    $allBusServices = [];

    foreach ($busStops as $stop) {
        $stopCode = $stop['BUS_STOP'];
        $services = $servicesByStop[$stopCode] ?? [];

        // Track unique services
        foreach ($services as $service) {
            if (!in_array($service['service_no'], $allBusServices)) {
                $allBusServices[] = $service['service_no'];
            }
        }

        $busStopsWithinRadius[] = [
            'stop_code' => $stopCode,
            'stop_name' => $stop['LOC_DESC'],
            'lat' => (float)$stop['Latitude'],
            'lng' => (float)$stop['Longitude'],
            'distance' => round($stop['distance']),
            'services' => $services
        ];
    }

    return [
        'station' => [
            'stop_id' => $station['stop_id'],
            'stop_name' => $station['name'],
            'lat' => $stationLat,
            'lng' => $stationLng
        ],
        'radius' => $radius,
        'bus_stops' => $busStopsWithinRadius,
        'total_bus_stops' => count($busStopsWithinRadius),
        'unique_bus_services' => count($allBusServices)
    ];
}
?>
