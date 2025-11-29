<?php
/**
 * YourTrip Analytics Backend API
 * Provides data endpoints for the Python ML analytics system
 * 
 * Usage: analytics_api.php?action={action_name}&param=value
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Database configuration
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '33060');
define('DB_NAME', 'yourtrip_db');
define('DB_USER', 'inf2003-sqldev');
define('DB_PASS', 'Inf2003#DevSecure!2025');

/**
 * Get PDO database connection
 */
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
        exit;
    }
}

// Get action parameter
$action = $_GET['action'] ?? '';

try {
    $pdo = getDBConnection();

    switch ($action) {
        // ==================== HEALTH & INFO ====================
        case 'health':
            echo json_encode(getHealthStatus($pdo));
            break;

        case 'info':
            echo json_encode(getAPIInfo($pdo));
            break;

        // ==================== ROUTE ENDPOINTS ====================
        case 'routes':
            echo json_encode(getAllRoutes($pdo));
            break;

        case 'route_details':
            $serviceNo = $_GET['service_no'] ?? null;
            if (!$serviceNo) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing service_no parameter']);
                break;
            }
            echo json_encode(getRouteDetails($pdo, $serviceNo));
            break;

        case 'route_stops':
            $serviceNo = $_GET['service_no'] ?? null;
            $direction = $_GET['direction'] ?? 1;
            if (!$serviceNo) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing service_no parameter']);
                break;
            }
            echo json_encode(getRouteStops($pdo, $serviceNo, $direction));
            break;

        // ==================== BUS VOLUME ENDPOINTS ====================
        case 'volume_by_route':
            $serviceNo = $_GET['service_no'] ?? null;
            $month = $_GET['month'] ?? null;
            $direction = $_GET['direction'] ?? 1;
            
            if (!$serviceNo) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing service_no parameter']);
                break;
            }
            echo json_encode(getVolumeByRoute($pdo, $serviceNo, $month, $direction));
            break;

        case 'volume_by_stop':
            $stopId = $_GET['stop_id'] ?? null;
            $month = $_GET['month'] ?? null;
            
            if (!$stopId) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing stop_id parameter']);
                break;
            }
            echo json_encode(getVolumeByStop($pdo, $stopId, $month));
            break;

        case 'available_months':
            echo json_encode(getAvailableMonths($pdo));
            break;

        case 'data_date_range':
            echo json_encode(getDataDateRange($pdo));
            break;

        // ==================== PREDICTION STORAGE ====================
        case 'save_prediction':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed. Use POST.']);
                break;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            echo json_encode(savePrediction($pdo, $data));
            break;

        case 'get_predictions':
            $routeId = $_GET['route_id'] ?? null;
            $startDate = $_GET['start_date'] ?? null;
            $endDate = $_GET['end_date'] ?? null;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
            
            echo json_encode(getPredictions($pdo, $routeId, $startDate, $endDate, $limit));
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'error' => 'Invalid action parameter',
                'available_actions' => [
                    'health', 'info', 'routes', 'route_details', 'route_stops',
                    'volume_by_route', 'volume_by_stop', 'available_months', 
                    'data_date_range', 'save_prediction', 'get_predictions'
                ]
            ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

// ==================== FUNCTION IMPLEMENTATIONS ====================

/**
 * Health check endpoint
 */
function getHealthStatus($pdo) {
    try {
        $stmt = $pdo->query("SELECT 1 as test");
        $result = $stmt->fetch();
        
        return [
            'status' => 'healthy',
            'database' => 'connected',
            'timestamp' => date('Y-m-d H:i:s')
        ];
    } catch (Exception $e) {
        return [
            'status' => 'unhealthy',
            'database' => 'disconnected',
            'error' => $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}

/**
 * Get API information
 */
function getAPIInfo($pdo) {
    // Get data statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total_records FROM BusVolume");
    $volumeCount = $stmt->fetch()['total_records'];
    
    $stmt = $pdo->query("SELECT COUNT(DISTINCT ServiceNo) as route_count FROM Routes");
    $routeCount = $stmt->fetch()['route_count'];
    
    $stmt = $pdo->query("SELECT MIN(month) as earliest, MAX(month) as latest FROM BusVolume");
    $dateRange = $stmt->fetch();
    
    return [
        'api_name' => 'YourTrip Analytics Backend API',
        'version' => '1.0',
        'database' => DB_NAME,
        'statistics' => [
            'total_volume_records' => (int)$volumeCount,
            'total_routes' => (int)$routeCount,
            'earliest_month' => $dateRange['earliest'],
            'latest_month' => $dateRange['latest']
        ],
        'endpoints' => [
            'health' => 'GET /analytics_api.php?action=health',
            'routes' => 'GET /analytics_api.php?action=routes',
            'volume_by_route' => 'GET /analytics_api.php?action=volume_by_route&service_no=118&month=202107',
            'save_prediction' => 'POST /analytics_api.php?action=save_prediction'
        ]
    ];
}

/**
 * Get all available bus routes
 */
function getAllRoutes($pdo) {
    $stmt = $pdo->query("
        SELECT DISTINCT 
            r.ServiceNo,
            bs.Operator,
            bs.Category,
            COUNT(DISTINCT r.BusStopCode) as total_stops
        FROM Routes r
        LEFT JOIN BusServices bs ON r.ServiceNo = bs.ServiceNo
        GROUP BY r.ServiceNo, bs.Operator, bs.Category
        ORDER BY r.ServiceNo
    ");
    
    $routes = [];
    while ($row = $stmt->fetch()) {
        $routes[] = [
            'ServiceNo' => $row['ServiceNo'],
            'Operator' => $row['Operator'],
            'Category' => $row['Category'],
            'total_stops' => (int)$row['total_stops']
        ];
    }
    
    return $routes;
}

/**
 * Get details for a specific route
 */
function getRouteDetails($pdo, $serviceNo) {
    $stmt = $pdo->prepare("
        SELECT 
            bs.ServiceNo, 
            bs.Operator, 
            bs.Direction, 
            bs.Category,
            bs.AM_Peak_Freq_Mins, 
            bs.PM_Peak_Freq_Mins,
            origin.LOC_DESC as Origin, 
            dest.LOC_DESC as Destination
        FROM BusServices bs
        LEFT JOIN BusStops origin ON bs.OriginCode = origin.BUS_STOP
        LEFT JOIN BusStops dest ON bs.DestinationCode = dest.BUS_STOP
        WHERE bs.ServiceNo = ?
    ");
    
    $stmt->execute([$serviceNo]);
    
    $details = [];
    while ($row = $stmt->fetch()) {
        $details[] = $row;
    }
    
    return $details;
}

/**
 * Get all stops for a route
 */
function getRouteStops($pdo, $serviceNo, $direction = 1) {
    $stmt = $pdo->prepare("
        SELECT 
            r.ServiceNo, 
            r.Direction, 
            r.StopSequence, 
            r.BusStopCode,
            bs.LOC_DESC, 
            bs.Latitude, 
            bs.Longitude, 
            r.Distance
        FROM Routes r
        JOIN BusStops bs ON r.BusStopCode = bs.BUS_STOP
        WHERE r.ServiceNo = ? AND r.Direction = ?
        ORDER BY r.StopSequence
    ");
    
    $stmt->execute([$serviceNo, $direction]);
    
    $stops = [];
    while ($row = $stmt->fetch()) {
        $stops[] = [
            'ServiceNo' => $row['ServiceNo'],
            'Direction' => (int)$row['Direction'],
            'StopSequence' => (int)$row['StopSequence'],
            'BusStopCode' => $row['BusStopCode'],
            'LOC_DESC' => $row['LOC_DESC'],
            'Latitude' => (float)$row['Latitude'],
            'Longitude' => (float)$row['Longitude'],
            'Distance' => (float)$row['Distance']
        ];
    }
    
    return $stops;
}

/**
 * Get aggregated bus volume for a route
 * This is the KEY endpoint for ML training data
 */
function getVolumeByRoute($pdo, $serviceNo, $month = null, $direction = 1) {
    if ($month) {
        $stmt = $pdo->prepare("
            SELECT 
                bv.day,
                bv.hour,
                bv.month,
                SUM(bv.vol_in) as total_passengers,
                COUNT(DISTINCT bv.stop_id) as num_stops
            FROM BusVolume bv
            INNER JOIN Routes r ON bv.stop_id = r.BusStopCode
            WHERE r.ServiceNo = ? 
                AND r.Direction = ?
                AND bv.month = ?
            GROUP BY bv.day, bv.hour, bv.month
            ORDER BY bv.month, bv.day, bv.hour
        ");
        $stmt->execute([$serviceNo, $direction, $month]);
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                bv.day,
                bv.hour,
                bv.month,
                SUM(bv.vol_in) as total_passengers,
                COUNT(DISTINCT bv.stop_id) as num_stops
            FROM BusVolume bv
            INNER JOIN Routes r ON bv.stop_id = r.BusStopCode
            WHERE r.ServiceNo = ? AND r.Direction = ?
            GROUP BY bv.day, bv.hour, bv.month
            ORDER BY bv.month, bv.day, bv.hour
        ");
        $stmt->execute([$serviceNo, $direction]);
    }
    
    $data = [];
    while ($row = $stmt->fetch()) {
        $data[] = [
            'day' => $row['day'],
            'hour' => (int)$row['hour'],
            'month' => (int)$row['month'],
            'total_passengers' => (int)$row['total_passengers'],
            'num_stops' => (int)$row['num_stops']
        ];
    }
    
    return $data;
}

/**
 * Get bus volume for a specific stop
 */
function getVolumeByStop($pdo, $stopId, $month = null) {
    if ($month) {
        $stmt = $pdo->prepare("
            SELECT day, hour, vol_in, vol_out, month
            FROM BusVolume
            WHERE stop_id = ? AND month = ?
            ORDER BY month, day, hour
        ");
        $stmt->execute([$stopId, $month]);
    } else {
        $stmt = $pdo->prepare("
            SELECT day, hour, vol_in, vol_out, month
            FROM BusVolume
            WHERE stop_id = ?
            ORDER BY month, day, hour
        ");
        $stmt->execute([$stopId]);
    }
    
    $data = [];
    while ($row = $stmt->fetch()) {
        $data[] = [
            'day' => $row['day'],
            'hour' => (int)$row['hour'],
            'vol_in' => (int)$row['vol_in'],
            'vol_out' => (int)$row['vol_out'],
            'month' => (int)$row['month']
        ];
    }
    
    return $data;
}

/**
 * Get all available months in BusVolume data
 */
function getAvailableMonths($pdo) {
    $stmt = $pdo->query("
        SELECT DISTINCT month
        FROM BusVolume
        ORDER BY month
    ");
    
    $months = [];
    while ($row = $stmt->fetch()) {
        $months[] = (int)$row['month'];
    }
    
    return $months;
}

/**
 * Get the date range of available data
 */
function getDataDateRange($pdo) {
    $stmt = $pdo->query("
        SELECT 
            MIN(month) as earliest_month,
            MAX(month) as latest_month,
            COUNT(*) as total_records
        FROM BusVolume
    ");
    
    $result = $stmt->fetch();
    
    return [
        'earliest_month' => (int)$result['earliest_month'],
        'latest_month' => (int)$result['latest_month'],
        'total_records' => (int)$result['total_records']
    ];
}

/**
 * Save a prediction to database
 */
function savePrediction($pdo, $data) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO Predictions 
            (route_id, prediction_datetime, predicted_passengers, confidence, is_peak, model_version, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $data['route_id'],
            $data['prediction_datetime'],
            $data['predicted_passengers'],
            $data['confidence'],
            $data['is_peak'] ? 1 : 0,
            $data['model_version'] ?? 'v1.0'
        ]);
        
        return [
            'success' => true,
            'prediction_id' => $pdo->lastInsertId(),
            'message' => 'Prediction saved successfully'
        ];
    } catch (Exception $e) {
        http_response_code(500);
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Get predictions from database
 */
function getPredictions($pdo, $routeId = null, $startDate = null, $endDate = null, $limit = 100) {
    $query = "SELECT id as prediction_id, route_id, prediction_datetime, predicted_passengers, confidence, is_peak, model_version, created_at FROM Predictions WHERE 1=1";
    $params = [];

    if ($routeId) {
        $query .= " AND route_id = ?";
        $params[] = $routeId;
    }

    if ($startDate) {
        $query .= " AND prediction_datetime >= ?";
        $params[] = $startDate;
    }

    if ($endDate) {
        $query .= " AND prediction_datetime <= ?";
        $params[] = $endDate;
    }

    $query .= " ORDER BY prediction_datetime DESC LIMIT ?";
    $params[] = $limit;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    $predictions = [];
    while ($row = $stmt->fetch()) {
        $predictions[] = [
            'prediction_id' => (int)$row['prediction_id'],
            'route_id' => $row['route_id'],
            'prediction_datetime' => $row['prediction_datetime'],
            'predicted_passengers' => (int)$row['predicted_passengers'],
            'confidence' => (float)$row['confidence'],
            'is_peak' => (bool)$row['is_peak'],
            'model_version' => $row['model_version'],
            'created_at' => $row['created_at']
        ];
    }

    return $predictions;
}
?>