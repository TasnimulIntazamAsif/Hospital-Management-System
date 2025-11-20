<?php
// api/medicines.php - Medicine and pathology test management endpoints
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
            handleListMedicines($conn);
            break;
        case 'get':
            handleGetMedicine($conn);
            break;
        case 'create':
            handleCreateMedicine($conn);
            break;
        case 'update':
            handleUpdateMedicine($conn);
            break;
        case 'delete':
            handleDeleteMedicine($conn);
            break;
        case 'categories':
            handleMedicineCategories($conn);
            break;
        case 'stock-low':
            handleLowStockMedicines($conn);
            break;
        case 'search':
            handleSearchMedicines($conn);
            break;
        default:
            Response::error('Invalid action', 404);
    }
} catch (Exception $e) {
    Logger::error('Medicine API Error: ' . $e->getMessage());
    Response::error('Internal server error', 500);
}

function handleListMedicines($conn) {
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
        $whereConditions[] = "(name LIKE ? OR generic_name LIKE ? OR manufacturer LIKE ? OR description LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM medicines $whereClause";
    $stmt = $conn->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    
    // Get medicines
    $sql = "
        SELECT 
            id,
            name,
            generic_name,
            manufacturer,
            dosage_form,
            strength,
            category,
            description,
            side_effects,
            contraindications,
            price,
            stock_quantity,
            is_active,
            created_at,
            updated_at
        FROM medicines 
        $whereClause
        ORDER BY name ASC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $medicines = $stmt->fetchAll();
    
    Response::success([
        'medicines' => $medicines,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

function handleGetMedicine($conn) {
    $user = Auth::requireAuth();
    
    $medicineId = (int)($_GET['id'] ?? 0);
    if (!$medicineId) {
        Response::error('Medicine ID required');
    }
    
    $stmt = $conn->prepare("
        SELECT 
            id,
            name,
            generic_name,
            manufacturer,
            dosage_form,
            strength,
            category,
            description,
            side_effects,
            contraindications,
            price,
            stock_quantity,
            is_active,
            created_at,
            updated_at
        FROM medicines 
        WHERE id = ?
    ");
    $stmt->execute([$medicineId]);
    $medicine = $stmt->fetch();
    
    if (!$medicine) {
        Response::notFound('Medicine not found');
    }
    
    Response::success($medicine);
}

function handleCreateMedicine($conn) {
    $user = Auth::requireRole(['admin', 'manager']);
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $required = ['name', 'generic_name', 'manufacturer', 'dosage_form', 'strength', 'category', 'price'];
    $missing = Validator::validateRequired($required, $input);
    
    if (!empty($missing)) {
        Response::error('Missing required fields: ' . implode(', ', $missing));
    }
    
    $name = Validator::sanitizeInput($input['name']);
    $genericName = Validator::sanitizeInput($input['generic_name']);
    $manufacturer = Validator::sanitizeInput($input['manufacturer']);
    $dosageForm = Validator::sanitizeInput($input['dosage_form']);
    $strength = Validator::sanitizeInput($input['strength']);
    $category = Validator::sanitizeInput($input['category']);
    $description = Validator::sanitizeInput($input['description'] ?? '');
    $sideEffects = Validator::sanitizeInput($input['side_effects'] ?? '');
    $contraindications = Validator::sanitizeInput($input['contraindications'] ?? '');
    $price = (float)$input['price'];
    $stockQuantity = (int)($input['stock_quantity'] ?? 0);
    
    // Check if medicine already exists
    $stmt = $conn->prepare("SELECT id FROM medicines WHERE name = ? AND manufacturer = ? AND strength = ?");
    $stmt->execute([$name, $manufacturer, $strength]);
    if ($stmt->fetch()) {
        Response::error('Medicine with same name, manufacturer, and strength already exists');
    }
    
    $stmt = $conn->prepare("
        INSERT INTO medicines (name, generic_name, manufacturer, dosage_form, strength, category, description, side_effects, contraindications, price, stock_quantity) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$name, $genericName, $manufacturer, $dosageForm, $strength, $category, $description, $sideEffects, $contraindications, $price, $stockQuantity]);
    
    Logger::info('Medicine created', [
        'medicine_id' => $conn->lastInsertId(),
        'name' => $name,
        'created_by' => $user['user_id']
    ]);
    
    Response::success(['medicine_id' => $conn->lastInsertId()], 'Medicine created successfully');
}

function handleUpdateMedicine($conn) {
    $user = Auth::requireRole(['admin', 'manager']);
    
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        Response::error('Method not allowed', 405);
    }
    
    $medicineId = (int)($_GET['id'] ?? 0);
    if (!$medicineId) {
        Response::error('Medicine ID required');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Verify medicine exists
    $stmt = $conn->prepare("SELECT id FROM medicines WHERE id = ?");
    $stmt->execute([$medicineId]);
    if (!$stmt->fetch()) {
        Response::notFound('Medicine not found');
    }
    
    $name = Validator::sanitizeInput($input['name']);
    $genericName = Validator::sanitizeInput($input['generic_name']);
    $manufacturer = Validator::sanitizeInput($input['manufacturer']);
    $dosageForm = Validator::sanitizeInput($input['dosage_form']);
    $strength = Validator::sanitizeInput($input['strength']);
    $category = Validator::sanitizeInput($input['category']);
    $description = Validator::sanitizeInput($input['description'] ?? '');
    $sideEffects = Validator::sanitizeInput($input['side_effects'] ?? '');
    $contraindications = Validator::sanitizeInput($input['contraindications'] ?? '');
    $price = (float)$input['price'];
    $stockQuantity = (int)($input['stock_quantity'] ?? 0);
    $isActive = (bool)($input['is_active'] ?? true);
    
    $stmt = $conn->prepare("
        UPDATE medicines 
        SET name = ?, generic_name = ?, manufacturer = ?, dosage_form = ?, strength = ?, category = ?, 
            description = ?, side_effects = ?, contraindications = ?, price = ?, stock_quantity = ?, 
            is_active = ?, updated_at = CURRENT_TIMESTAMP 
        WHERE id = ?
    ");
    $stmt->execute([$name, $genericName, $manufacturer, $dosageForm, $strength, $category, $description, $sideEffects, $contraindications, $price, $stockQuantity, $isActive, $medicineId]);
    
    Logger::info('Medicine updated', [
        'medicine_id' => $medicineId,
        'updated_by' => $user['user_id']
    ]);
    
    Response::success(null, 'Medicine updated successfully');
}

function handleDeleteMedicine($conn) {
    $user = Auth::requireRole(['admin']);
    
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        Response::error('Method not allowed', 405);
    }
    
    $medicineId = (int)($_GET['id'] ?? 0);
    if (!$medicineId) {
        Response::error('Medicine ID required');
    }
    
    // Check if medicine is used in prescriptions
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM prescription_medicines WHERE medicine_id = ?");
    $stmt->execute([$medicineId]);
    $usageCount = $stmt->fetch()['count'];
    
    if ($usageCount > 0) {
        Response::error('Cannot delete medicine that is used in prescriptions. Consider deactivating instead.');
    }
    
    $stmt = $conn->prepare("DELETE FROM medicines WHERE id = ?");
    $stmt->execute([$medicineId]);
    
    Logger::info('Medicine deleted', [
        'medicine_id' => $medicineId,
        'deleted_by' => $user['user_id']
    ]);
    
    Response::success(null, 'Medicine deleted successfully');
}

function handleMedicineCategories($conn) {
    $user = Auth::requireAuth();
    
    $stmt = $conn->prepare("
        SELECT DISTINCT category 
        FROM medicines 
        WHERE is_active = TRUE AND category IS NOT NULL AND category != '' 
        ORDER BY category
    ");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    Response::success($categories);
}

function handleLowStockMedicines($conn) {
    $user = Auth::requireRole(['admin', 'manager', 'doctor']);
    
    $threshold = (int)($_GET['threshold'] ?? 50);
    
    $stmt = $conn->prepare("
        SELECT 
            id,
            name,
            generic_name,
            category,
            stock_quantity,
            price
        FROM medicines 
        WHERE stock_quantity <= ? AND is_active = TRUE 
        ORDER BY stock_quantity ASC
    ");
    $stmt->execute([$threshold]);
    $medicines = $stmt->fetchAll();
    
    Response::success($medicines);
}

function handleSearchMedicines($conn) {
    $user = Auth::requireAuth();
    
    $query = $_GET['q'] ?? '';
    $limit = (int)($_GET['limit'] ?? 10);
    
    if (strlen($query) < 2) {
        Response::error('Search query must be at least 2 characters');
    }
    
    $stmt = $conn->prepare("
        SELECT 
            id,
            name,
            generic_name,
            manufacturer,
            dosage_form,
            strength,
            category,
            price,
            stock_quantity
        FROM medicines 
        WHERE is_active = TRUE AND (
            name LIKE ? OR 
            generic_name LIKE ? OR 
            manufacturer LIKE ? OR
            CONCAT(name, ' ', generic_name, ' ', manufacturer) LIKE ?
        )
        ORDER BY 
            CASE 
                WHEN name LIKE ? THEN 1
                WHEN generic_name LIKE ? THEN 2
                ELSE 3
            END,
            name
        LIMIT ?
    ");
    
    $searchTerm = "%$query%";
    $exactMatch = "$query%";
    
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $exactMatch, $exactMatch, $limit]);
    $medicines = $stmt->fetchAll();
    
    Response::success($medicines);
}
?>
