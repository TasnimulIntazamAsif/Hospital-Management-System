<?php
// api/tests.php - Pathology tests management endpoints
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['action'] ?? '';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    switch ($path) {
        case 'list':
            handleListTests($conn);
            break;
        case 'get':
            handleGetTest($conn);
            break;
        case 'create':
            handleCreateTest($conn);
            break;
        case 'update':
            handleUpdateTest($conn);
            break;
        case 'delete':
            handleDeleteTest($conn);
            break;
        case 'categories':
            handleTestCategories($conn);
            break;
        case 'search':
            handleSearchTests($conn);
            break;
        default:
            Response::error('Invalid action', 404);
    }
} catch (Exception $e) {
    Logger::error('Test API Error: ' . $e->getMessage());
    Response::error('Internal server error', 500);
}

function handleListTests($conn) {
    $user = Auth::requireAuth();
    
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $category = $_GET['category'] ?? '';
    $search = $_GET['search'] ?? '';
    $activeOnly = $_GET['active_only'] ?? 'true';
    
    $offset = ($page - 1) * $limit;
    
    $whereConditions = [];
    $params = [];
    
    // Active only filter
    if ($activeOnly === 'true') {
        $whereConditions[] = "is_active = TRUE";
    }
    
    // Category filter
    if ($category) {
        $whereConditions[] = "category = ?";
        $params[] = $category;
    }
    
    // Search filter
    if ($search) {
        $whereConditions[] = "(test_name LIKE ? OR test_code LIKE ? OR description LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM pathology_tests $whereClause";
    $stmt = $conn->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    
    // Get tests
    $sql = "
        SELECT 
            id,
            test_name,
            test_code,
            category,
            description,
            preparation_instructions,
            normal_values,
            price,
            duration_hours,
            is_active,
            created_at,
            updated_at
        FROM pathology_tests 
        $whereClause
        ORDER BY test_name ASC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $tests = $stmt->fetchAll();
    
    Response::success([
        'tests' => $tests,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

function handleGetTest($conn) {
    $user = Auth::requireAuth();
    
    $testId = (int)($_GET['id'] ?? 0);
    if (!$testId) {
        Response::error('Test ID required');
    }
    
    $stmt = $conn->prepare("
        SELECT 
            id,
            test_name,
            test_code,
            category,
            description,
            preparation_instructions,
            normal_values,
            price,
            duration_hours,
            is_active,
            created_at,
            updated_at
        FROM pathology_tests 
        WHERE id = ?
    ");
    $stmt->execute([$testId]);
    $test = $stmt->fetch();
    
    if (!$test) {
        Response::notFound('Test not found');
    }
    
    Response::success($test);
}

function handleCreateTest($conn) {
    $user = Auth::requireRole(['admin', 'manager']);
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $required = ['test_name', 'test_code', 'category', 'price'];
    $missing = Validator::validateRequired($required, $input);
    
    if (!empty($missing)) {
        Response::error('Missing required fields: ' . implode(', ', $missing));
    }
    
    $testName = Validator::sanitizeInput($input['test_name']);
    $testCode = Validator::sanitizeInput($input['test_code']);
    $category = Validator::sanitizeInput($input['category']);
    $description = Validator::sanitizeInput($input['description'] ?? '');
    $preparationInstructions = Validator::sanitizeInput($input['preparation_instructions'] ?? '');
    $normalValues = Validator::sanitizeInput($input['normal_values'] ?? '');
    $price = (float)$input['price'];
    $durationHours = (float)($input['duration_hours'] ?? 1);
    
    // Check if test code already exists
    $stmt = $conn->prepare("SELECT id FROM pathology_tests WHERE test_code = ?");
    $stmt->execute([$testCode]);
    if ($stmt->fetch()) {
        Response::error('Test code already exists');
    }
    
    $stmt = $conn->prepare("
        INSERT INTO pathology_tests (test_name, test_code, category, description, preparation_instructions, normal_values, price, duration_hours) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$testName, $testCode, $category, $description, $preparationInstructions, $normalValues, $price, $durationHours]);
    
    Logger::info('Pathology test created', [
        'test_id' => $conn->lastInsertId(),
        'test_name' => $testName,
        'test_code' => $testCode,
        'created_by' => $user['user_id']
    ]);
    
    Response::success(['test_id' => $conn->lastInsertId()], 'Test created successfully');
}

function handleUpdateTest($conn) {
    $user = Auth::requireRole(['admin', 'manager']);
    
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        Response::error('Method not allowed', 405);
    }
    
    $testId = (int)($_GET['id'] ?? 0);
    if (!$testId) {
        Response::error('Test ID required');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Verify test exists
    $stmt = $conn->prepare("SELECT id FROM pathology_tests WHERE id = ?");
    $stmt->execute([$testId]);
    if (!$stmt->fetch()) {
        Response::notFound('Test not found');
    }
    
    $testName = Validator::sanitizeInput($input['test_name']);
    $testCode = Validator::sanitizeInput($input['test_code']);
    $category = Validator::sanitizeInput($input['category']);
    $description = Validator::sanitizeInput($input['description'] ?? '');
    $preparationInstructions = Validator::sanitizeInput($input['preparation_instructions'] ?? '');
    $normalValues = Validator::sanitizeInput($input['normal_values'] ?? '');
    $price = (float)$input['price'];
    $durationHours = (float)($input['duration_hours'] ?? 1);
    $isActive = (bool)($input['is_active'] ?? true);
    
    $stmt = $conn->prepare("
        UPDATE pathology_tests 
        SET test_name = ?, test_code = ?, category = ?, description = ?, preparation_instructions = ?, 
            normal_values = ?, price = ?, duration_hours = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP 
        WHERE id = ?
    ");
    $stmt->execute([$testName, $testCode, $category, $description, $preparationInstructions, $normalValues, $price, $durationHours, $isActive, $testId]);
    
    Logger::info('Pathology test updated', [
        'test_id' => $testId,
        'updated_by' => $user['user_id']
    ]);
    
    Response::success(null, 'Test updated successfully');
}

function handleDeleteTest($conn) {
    $user = Auth::requireRole(['admin']);
    
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        Response::error('Method not allowed', 405);
    }
    
    $testId = (int)($_GET['id'] ?? 0);
    if (!$testId) {
        Response::error('Test ID required');
    }
    
    // Check if test is used in prescriptions
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM prescription_tests WHERE test_id = ?");
    $stmt->execute([$testId]);
    $usageCount = $stmt->fetch()['count'];
    
    if ($usageCount > 0) {
        Response::error('Cannot delete test that is used in prescriptions. Consider deactivating instead.');
    }
    
    $stmt = $conn->prepare("DELETE FROM pathology_tests WHERE id = ?");
    $stmt->execute([$testId]);
    
    Logger::info('Pathology test deleted', [
        'test_id' => $testId,
        'deleted_by' => $user['user_id']
    ]);
    
    Response::success(null, 'Test deleted successfully');
}

function handleTestCategories($conn) {
    $user = Auth::requireAuth();
    
    $stmt = $conn->prepare("
        SELECT DISTINCT category 
        FROM pathology_tests 
        WHERE is_active = TRUE AND category IS NOT NULL AND category != '' 
        ORDER BY category
    ");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    Response::success($categories);
}

function handleSearchTests($conn) {
    $user = Auth::requireAuth();
    
    $query = $_GET['q'] ?? '';
    $limit = (int)($_GET['limit'] ?? 10);
    
    if (strlen($query) < 2) {
        Response::error('Search query must be at least 2 characters');
    }
    
    $stmt = $conn->prepare("
        SELECT 
            id,
            test_name,
            test_code,
            category,
            price,
            duration_hours
        FROM pathology_tests 
        WHERE is_active = TRUE AND (
            test_name LIKE ? OR 
            test_code LIKE ? OR
            CONCAT(test_name, ' ', test_code) LIKE ?
        )
        ORDER BY 
            CASE 
                WHEN test_name LIKE ? THEN 1
                WHEN test_code LIKE ? THEN 2
                ELSE 3
            END,
            test_name
        LIMIT ?
    ");
    
    $searchTerm = "%$query%";
    $exactMatch = "$query%";
    
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $exactMatch, $exactMatch, $limit]);
    $tests = $stmt->fetchAll();
    
    Response::success($tests);
}
?>
