<?php
// api/index.php - Main API router
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

// Remove 'api' from path parts
if ($pathParts[0] === 'api') {
    array_shift($pathParts);
}

$endpoint = $pathParts[0] ?? '';

try {
    switch ($endpoint) {
        case 'auth':
            require_once 'auth.php';
            break;
        case 'prescriptions':
            require_once 'prescriptions.php';
            break;
        case 'medicines':
            require_once 'medicines.php';
            break;
        case 'tests':
            require_once 'tests.php';
            break;
        case 'appointments':
            require_once 'appointments.php';
            break;
        case 'admin':
            require_once 'admin.php';
            break;
        case 'upload':
            require_once 'upload.php';
            break;
        case 'download':
            require_once 'download.php';
            break;
        case 'health':
            handleHealthCheck();
            break;
        default:
            Response::error('API endpoint not found', 404);
    }
} catch (Exception $e) {
    Logger::error('API Router Error: ' . $e->getMessage());
    Response::error('Internal server error', 500);
}

function handleHealthCheck() {
    Response::success([
        'status' => 'healthy',
        'timestamp' => date('Y-m-d H:i:s'),
        'version' => '1.0.0'
    ], 'API is running');
}
?>