<?php
/**
 * Multilingual Transport Stops API
 * PHP endpoint for serving multilingual stop data from MongoDB
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// MongoDB connection using PHP MongoDB extension
// Note: MongoDB extension must be enabled in php.ini
// require_once __DIR__ . '/vendor/autoload.php'; // Not needed - using native PHP MongoDB extension

// Check if MongoDB extension is loaded
if (!extension_loaded('mongodb')) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'MongoDB extension is not installed. Please install php-mongodb extension.',
        'hint' => 'Run: sudo apt-get install php-mongodb (Ubuntu) or pecl install mongodb (macOS/others)'
    ]);
    exit();
}

// Check if MongoDB\Driver\Manager class exists
if (!class_exists('MongoDB\Driver\Manager')) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'MongoDB\Driver\Manager class not found. Please check MongoDB extension installation.'
    ]);
    exit();
}

// MongoDB Atlas connection
$mongoUsername = getenv('MONGO_USERNAME') ?: 'inf2003-mongoDB';
$mongoPassword = getenv('MONGO_PASSWORD') ?: 'Password123456';
$mongoCluster = getenv('MONGO_CLUSTER') ?: 'inf2003-nosql.pblgwjp';
$mongoUri = "mongodb+srv://{$mongoUsername}:{$mongoPassword}@{$mongoCluster}.mongodb.net/?retryWrites=true&w=majority";

try {
    $client = new MongoDB\Driver\Manager($mongoUri);
    $database = 'yourtrip_db';
    $collection = 'multilingual_stops';

    // Parse request
    $action = $_GET['action'] ?? 'get_stops';

    switch ($action) {
        case 'get_stops':
            getStops($client, $database, $collection);
            break;

        case 'get_stop':
            getStop($client, $database, $collection);
            break;

        case 'search':
            searchStops($client, $database, $collection);
            break;

        case 'languages':
            getLanguages();
            break;

        case 'health':
            healthCheck();
            break;

        default:
            sendError('Invalid action', 400);
    }

} catch (Exception $e) {
    sendError('Database connection failed: ' . $e->getMessage(), 500);
}

/**
 * Get all stops with optional filtering
 */
function getStops($client, $database, $collection) {
    $stopType = $_GET['stop_type'] ?? null;
    $lang = $_GET['lang'] ?? 'en';

    // Build query filter
    $filter = [];
    if ($stopType) {
        $filter['stop_type'] = $stopType;
    }

    // Query MongoDB
    $query = new MongoDB\Driver\Query($filter);
    $namespace = "{$database}.{$collection}";

    try {
        $cursor = $client->executeQuery($namespace, $query);
        $stops = [];

        foreach ($cursor as $document) {
            $doc = (array)$document;
            $names = (array)($doc['names'] ?? []);

            $stops[] = [
                'stop_id' => $doc['stop_id'] ?? '',
                'stop_type' => $doc['stop_type'] ?? 'bus',
                'name' => $names[$lang] ?? $names['en'] ?? '',
                'names' => $names,
                'lat' => (float)($doc['lat'] ?? 0),
                'lng' => (float)($doc['lng'] ?? 0)
            ];
        }

        sendSuccess([
            'language' => $lang,
            'count' => count($stops),
            'stops' => $stops
        ]);

    } catch (Exception $e) {
        sendError('Query failed: ' . $e->getMessage(), 500);
    }
}

/**
 * Get a single stop by ID
 */
function getStop($client, $database, $collection) {
    $stopId = $_GET['stop_id'] ?? null;
    $lang = $_GET['lang'] ?? 'en';

    if (!$stopId) {
        sendError('stop_id parameter is required', 400);
        return;
    }

    $filter = ['stop_id' => $stopId];
    $query = new MongoDB\Driver\Query($filter);
    $namespace = "{$database}.{$collection}";

    try {
        $cursor = $client->executeQuery($namespace, $query);
        $documents = iterator_to_array($cursor);

        if (empty($documents)) {
            sendError('Stop not found', 404);
            return;
        }

        $doc = (array)$documents[0];
        $names = (array)($doc['names'] ?? []);

        $stop = [
            'stop_id' => $doc['stop_id'] ?? '',
            'stop_type' => $doc['stop_type'] ?? 'bus',
            'name' => $names[$lang] ?? $names['en'] ?? '',
            'names' => $names,
            'lat' => (float)($doc['lat'] ?? 0),
            'lng' => (float)($doc['lng'] ?? 0)
        ];

        sendSuccess(['stop' => $stop]);

    } catch (Exception $e) {
        sendError('Query failed: ' . $e->getMessage(), 500);
    }
}

/**
 * Search stops by name in any language
 */
function searchStops($client, $database, $collection) {
    $query = $_GET['q'] ?? '';
    $lang = $_GET['lang'] ?? 'en';

    if (empty($query)) {
        sendError('Search query (q) is required', 400);
        return;
    }

    // MongoDB regex search across all language fields
    $filter = [
        '$or' => [
            ['names.en' => new MongoDB\BSON\Regex($query, 'i')],
            ['names.zh' => new MongoDB\BSON\Regex($query, 'i')],
            ['names.ta' => new MongoDB\BSON\Regex($query, 'i')],
            ['names.ms' => new MongoDB\BSON\Regex($query, 'i')]
        ]
    ];

    $mongoQuery = new MongoDB\Driver\Query($filter);
    $namespace = "{$database}.{$collection}";

    try {
        $cursor = $client->executeQuery($namespace, $mongoQuery);
        $results = [];

        foreach ($cursor as $document) {
            $doc = (array)$document;
            $names = (array)($doc['names'] ?? []);

            $results[] = [
                'stop_id' => $doc['stop_id'] ?? '',
                'stop_type' => $doc['stop_type'] ?? 'bus',
                'name' => $names[$lang] ?? $names['en'] ?? '',
                'names' => $names,
                'lat' => (float)($doc['lat'] ?? 0),
                'lng' => (float)($doc['lng'] ?? 0)
            ];
        }

        sendSuccess([
            'query' => $query,
            'language' => $lang,
            'count' => count($results),
            'results' => $results
        ]);

    } catch (Exception $e) {
        sendError('Search failed: ' . $e->getMessage(), 500);
    }
}

/**
 * Get supported languages
 */
function getLanguages() {
    sendSuccess([
        'languages' => [
            ['code' => 'en', 'name' => 'English', 'native_name' => 'English'],
            ['code' => 'zh', 'name' => 'Chinese', 'native_name' => '中文'],
            ['code' => 'ta', 'name' => 'Tamil', 'native_name' => 'தமிழ்'],
            ['code' => 'ms', 'name' => 'Malay', 'native_name' => 'Bahasa Melayu']
        ]
    ]);
}

/**
 * Health check endpoint
 */
function healthCheck() {
    sendSuccess([
        'status' => 'healthy',
        'service' => 'multilingual-api-php',
        'timestamp' => date('c')
    ]);
}

/**
 * Send success response
 */
function sendSuccess($data) {
    http_response_code(200);
    echo json_encode(array_merge(['success' => true], $data));
    exit();
}

/**
 * Send error response
 */
function sendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $message
    ]);
    exit();
}
