<?php
// api/admin.php - Admin management endpoints
session_start();
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
        case 'pending-doctors':
            handlePendingDoctors($conn);
            break;
        case 'approve-doctor':
            handleApproveDoctor($conn);
            break;
        case 'reject-doctor':
            handleRejectDoctor($conn);
            break;
        case 'users':
            handleUsers($conn);
            break;
        case 'create-user':
            handleCreateUser($conn);
            break;
        case 'update-user':
            handleUpdateUser($conn);
            break;
        case 'delete-user':
            handleDeleteUser($conn);
            break;
        case 'update-user-status':
            handleUpdateUserStatus($conn);
            break;
        case 'certificates':
            handleCertificates($conn);
            break;
        case 'verify-certificate':
            handleVerifyCertificate($conn);
            break;
        case 'reject-certificate':
            handleRejectCertificate($conn);
            break;
        case 'dashboard-stats':
            handleDashboardStats($conn);
            break;
        case 'audit-logs':
            handleAuditLogs($conn);
            break;
        case 'activity-logs':
            handleActivityLogs($conn);
            break;
        case 'create-manager':
            handleCreateManager($conn);
            break;
        case 'update-manager':
            handleUpdateManager($conn);
            break;
        default:
            Response::error('Invalid action', 404);
    }
} catch (Exception $e) {
    Logger::error('Admin API Error: ' . $e->getMessage());
    Response::error('Internal server error', 500);
}

function handlePendingDoctors($conn) {
    $user = Auth::requireRole(['admin']);
    
    $stmt = $conn->prepare("
        SELECT 
            d.*,
            u.name,
            u.email,
            u.phone,
            u.created_at as user_created_at,
            COUNT(c.id) as certificate_count
        FROM doctors d
        JOIN users u ON d.user_id = u.id
        LEFT JOIN certificates c ON d.id = c.doctor_id
        WHERE d.status = 'pending'
        GROUP BY d.id
        ORDER BY d.created_at ASC
    ");
    $stmt->execute();
    $doctors = $stmt->fetchAll();
    
    Response::success($doctors);
}

function handleApproveDoctor($conn) {
    // Check session authentication
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
        header('Location: ../dashboard-admin.php?error=Authentication required');
        exit;
    }
    
    $adminId = $_SESSION['user_id'];
    
    $doctorId = (int)($_GET['id'] ?? 0);
    if (!$doctorId) {
        header('Location: ../dashboard-admin.php?error=Doctor ID required');
        exit;
    }
    
    $conn->beginTransaction();
    
    try {
        // Update doctor status
        $stmt = $conn->prepare("
            UPDATE doctors 
            SET status = 'approved', approved_by = ?, approved_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmt->execute([$adminId, $doctorId]);
        
        // Update certificates status if they exist
        $stmt = $conn->prepare("
            UPDATE certificates 
            SET status = 'verified', verified_by = ?, verified_at = CURRENT_TIMESTAMP 
            WHERE doctor_id = ? AND status = 'pending'
        ");
        $stmt->execute([$adminId, $doctorId]);
        
        $conn->commit();
        
        Logger::info('Doctor approved', [
            'doctor_id' => $doctorId,
            'approved_by' => $adminId
        ]);
        
        header('Location: ../dashboard-admin.php?success=Doctor approved successfully');
        exit;
        
    } catch (Exception $e) {
        $conn->rollBack();
        Logger::error('Doctor approval failed', ['error' => $e->getMessage()]);
        header('Location: ../dashboard-admin.php?error=' . urlencode('Doctor approval failed: ' . $e->getMessage()));
        exit;
    }
}

function handleRejectDoctor($conn) {
    // Check session authentication
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
        header('Location: ../dashboard-admin.php?error=Authentication required');
        exit;
    }
    
    $adminId = $_SESSION['user_id'];
    
    $doctorId = (int)($_GET['id'] ?? 0);
    $rejectionReason = 'Rejected by admin';
    
    if (!$doctorId) {
        header('Location: ../dashboard-admin.php?error=Doctor ID required');
        exit;
    }
    
    $conn->beginTransaction();
    
    try {
        // Update doctor status
        $stmt = $conn->prepare("
            UPDATE doctors 
            SET status = 'rejected', rejected_by = ?, rejected_at = CURRENT_TIMESTAMP, rejection_reason = ? 
            WHERE id = ?
        ");
        $stmt->execute([$adminId, $rejectionReason, $doctorId]);
        
        // Update user status
        $stmt = $conn->prepare("
            UPDATE users 
            SET status = 'inactive' 
            WHERE id = (SELECT user_id FROM doctors WHERE id = ?)
        ");
        $stmt->execute([$doctorId]);
        
        $conn->commit();
        
        Logger::info('Doctor rejected', [
            'doctor_id' => $doctorId,
            'rejected_by' => $adminId,
            'reason' => $rejectionReason
        ]);
        
        header('Location: ../dashboard-admin.php?success=Doctor rejected successfully');
        exit;
        
    } catch (Exception $e) {
        $conn->rollBack();
        Logger::error('Doctor rejection failed', ['error' => $e->getMessage()]);
        header('Location: ../dashboard-admin.php?error=' . urlencode('Doctor rejection failed: ' . $e->getMessage()));
        exit;
    }
}

function handleCreateUser($conn) {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $name = Validator::sanitizeInput($input['name'] ?? '');
    $email = Validator::sanitizeInput($input['email'] ?? '');
    $phone = Validator::sanitizeInput($input['phone'] ?? '');
    $password = $input['password'] ?? '';
    $role = Validator::sanitizeInput($input['role'] ?? '');
    $status = Validator::sanitizeInput($input['status'] ?? 'active');
    
    if (empty($name) || empty($email) || empty($password) || empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'All fields required']);
        exit;
    }
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        exit;
    }
    
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password_hash, role, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $email, $phone, $passwordHash, $role, $status]);
    
    echo json_encode(['success' => true, 'message' => 'User created successfully']);
    exit;
}

function handleDeleteUser($conn) {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    
    $userId = (int)($_GET['id'] ?? 0);
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        exit;
    }
    
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    
    echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
    exit;
}

function handleUsers($conn) {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $role = $_GET['role'] ?? '';
    $status = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    
    $offset = ($page - 1) * $limit;
    
    $whereConditions = [];
    $params = [];
    
    // Role filtering
    if ($role) {
        $whereConditions[] = "u.role = ?";
        $params[] = $role;
    }
    
    // Status filtering
    if ($status) {
        $whereConditions[] = "u.status = ?";
        $params[] = $status;
    }
    
    // Search filtering
    if ($search) {
        $whereConditions[] = "(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM users u $whereClause";
    $stmt = $conn->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    
    // Get users
    $sql = "
        SELECT 
            u.id,
            u.name,
            u.email,
            u.phone,
            u.role,
            u.status,
            u.created_at,
            u.updated_at,
            d.specialty,
            d.license_number,
            d.status as doctor_status,
            p.date_of_birth,
            p.gender,
            p.nationality,
            m.department,
            m.position,
            m.employee_id
        FROM users u
        LEFT JOIN doctors d ON u.id = d.user_id
        LEFT JOIN patients p ON u.id = p.user_id
        LEFT JOIN managers m ON u.id = m.user_id
        $whereClause
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    Response::success([
        'users' => $users,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

function handleUpdateUserStatus($conn) {
    $user = Auth::requireRole(['admin']);
    
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        Response::error('Method not allowed', 405);
    }
    
    $userId = (int)($_GET['id'] ?? 0);
    $input = json_decode(file_get_contents('php://input'), true);
    $newStatus = Validator::sanitizeInput($input['status'] ?? '');
    
    if (!$userId || !in_array($newStatus, ['active', 'inactive', 'suspended'])) {
        Response::error('Invalid user ID or status');
    }
    
    $stmt = $conn->prepare("UPDATE users SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$newStatus, $userId]);
    
    Logger::info('User status updated', [
        'user_id' => $userId,
        'new_status' => $newStatus,
        'updated_by' => $user['user_id']
    ]);
    
    Response::success(null, 'User status updated successfully');
}

function handleCertificates($conn) {
    $user = Auth::requireRole(['admin']);
    
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $status = $_GET['status'] ?? '';
    
    $offset = ($page - 1) * $limit;
    
    $whereConditions = [];
    $params = [];
    
    // Status filtering
    if ($status) {
        $whereConditions[] = "c.status = ?";
        $params[] = $status;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get certificates
    $sql = "
        SELECT 
            c.*,
            u.name as doctor_name,
            u.email as doctor_email,
            d.specialty
        FROM certificates c
        JOIN doctors d ON c.doctor_id = d.id
        JOIN users u ON d.user_id = u.id
        $whereClause
        ORDER BY c.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $certificates = $stmt->fetchAll();
    
    Response::success($certificates);
}

function handleVerifyCertificate($conn) {
    $user = Auth::requireRole(['admin']);
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Method not allowed', 405);
    }
    
    $certificateId = (int)($_GET['id'] ?? 0);
    if (!$certificateId) {
        Response::error('Certificate ID required');
    }
    
    $stmt = $conn->prepare("
        UPDATE certificates 
        SET status = 'verified', verified_by = ?, verified_at = CURRENT_TIMESTAMP 
        WHERE id = ?
    ");
    $stmt->execute([$user['user_id'], $certificateId]);
    
    Logger::info('Certificate verified', [
        'certificate_id' => $certificateId,
        'verified_by' => $user['user_id']
    ]);
    
    Response::success(null, 'Certificate verified successfully');
}

function handleRejectCertificate($conn) {
    $user = Auth::requireRole(['admin']);
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Method not allowed', 405);
    }
    
    $certificateId = (int)($_GET['id'] ?? 0);
    $input = json_decode(file_get_contents('php://input'), true);
    $rejectionReason = Validator::sanitizeInput($input['reason'] ?? '');
    
    if (!$certificateId) {
        Response::error('Certificate ID required');
    }
    
    $stmt = $conn->prepare("
        UPDATE certificates 
        SET status = 'rejected', rejected_by = ?, rejected_at = CURRENT_TIMESTAMP, rejection_reason = ? 
        WHERE id = ?
    ");
    $stmt->execute([$user['user_id'], $rejectionReason, $certificateId]);
    
    Logger::info('Certificate rejected', [
        'certificate_id' => $certificateId,
        'rejected_by' => $user['user_id'],
        'reason' => $rejectionReason
    ]);
    
    Response::success(null, 'Certificate rejected successfully');
}

function handleDashboardStats($conn) {
    $user = Auth::requireRole(['admin']);
    
    // Get various statistics
    $stats = [];
    
    // Total users by role
    $stmt = $conn->prepare("
        SELECT role, COUNT(*) as count 
        FROM users 
        WHERE status = 'active' 
        GROUP BY role
    ");
    $stmt->execute();
    $stats['users_by_role'] = $stmt->fetchAll();
    
    // Pending doctors
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM doctors WHERE status = 'pending'");
    $stmt->execute();
    $stats['pending_doctors'] = $stmt->fetch()['count'];
    
    // Pending certificates
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM certificates WHERE status = 'pending'");
    $stmt->execute();
    $stats['pending_certificates'] = $stmt->fetch()['count'];
    
    // Today's appointments
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = CURDATE()");
    $stmt->execute();
    $stats['today_appointments'] = $stmt->fetch()['count'];
    
    // Pending payments
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM payments WHERE status = 'pending'");
    $stmt->execute();
    $stats['pending_payments'] = $stmt->fetch()['count'];
    
    // Recent activity
    $stmt = $conn->prepare("
        SELECT activity, COUNT(*) as count 
        FROM activity_logs 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
        GROUP BY activity 
        ORDER BY count DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $stats['recent_activity'] = $stmt->fetchAll();
    
    Response::success($stats);
}

function handleAuditLogs($conn) {
    $user = Auth::requireRole(['admin']);
    
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $action = $_GET['action'] ?? '';
    $table = $_GET['table'] ?? '';
    
    $offset = ($page - 1) * $limit;
    
    $whereConditions = [];
    $params = [];
    
    // Action filtering
    if ($action) {
        $whereConditions[] = "action = ?";
        $params[] = $action;
    }
    
    // Table filtering
    if ($table) {
        $whereConditions[] = "table_name = ?";
        $params[] = $table;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get audit logs
    $sql = "
        SELECT 
            al.*,
            u.name as user_name
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.id
        $whereClause
        ORDER BY al.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
    Response::success($logs);
}

function handleActivityLogs($conn) {
    $user = Auth::requireRole(['admin']);
    
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $activity = $_GET['activity'] ?? '';
    
    $offset = ($page - 1) * $limit;
    
    $whereConditions = [];
    $params = [];
    
    // Activity filtering
    if ($activity) {
        $whereConditions[] = "activity LIKE ?";
        $params[] = "%$activity%";
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get activity logs
    $sql = "
        SELECT 
            al.*,
            u.name as user_name
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        $whereClause
        ORDER BY al.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
    Response::success($logs);
}

function handleCreateManager($conn) {
    $user = Auth::requireRole(['admin']);
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $required = ['name', 'email', 'phone', 'password', 'department', 'position'];
    $missing = Validator::validateRequired($required, $input);
    
    if (!empty($missing)) {
        Response::error('Missing required fields: ' . implode(', ', $missing));
    }
    
    $name = Validator::sanitizeInput($input['name']);
    $email = Validator::sanitizeInput($input['email']);
    $phone = Validator::sanitizeInput($input['phone']);
    $password = $input['password'];
    $department = Validator::sanitizeInput($input['department']);
    $position = Validator::sanitizeInput($input['position']);
    $employeeId = Validator::sanitizeInput($input['employee_id'] ?? '');
    $hireDate = $input['hire_date'] ?? date('Y-m-d');
    
    // Validation
    if (!Validator::validateEmail($email)) {
        Response::error('Invalid email format');
    }
    
    if (!Validator::validatePassword($password)) {
        Response::error('Password must be at least 6 characters long');
    }
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        Response::error('Email already registered');
    }
    
    $conn->beginTransaction();
    
    try {
        // Create user account
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("
            INSERT INTO users (name, email, phone, password_hash, role, status) 
            VALUES (?, ?, ?, ?, 'manager', 'active')
        ");
        $stmt->execute([$name, $email, $phone, $passwordHash]);
        $userId = $conn->lastInsertId();
        
        // Create manager profile
        $stmt = $conn->prepare("
            INSERT INTO managers (user_id, department, employee_id, position, hire_date) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $department, $employeeId, $position, $hireDate]);
        
        $conn->commit();
        
        Logger::info('Manager created by admin', [
            'manager_id' => $userId,
            'created_by' => $user['user_id'],
            'email' => $email
        ]);
        
        Response::success(['manager_id' => $userId], 'Manager profile created successfully');
        
    } catch (Exception $e) {
        $conn->rollBack();
        Logger::error('Manager creation failed', ['error' => $e->getMessage()]);
        Response::error('Manager creation failed');
    }
}

function handleUpdateManager($conn) {
    $user = Auth::requireAuth();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        Response::error('Method not allowed', 405);
    }
    
    $userId = $_GET['user_id'] ?? $user['user_id'];
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Only admins can update other managers, managers can only update themselves
    if ($user['role'] !== 'admin' && $userId != $user['user_id']) {
        Response::forbidden('You can only update your own profile');
    }
    
    $conn->beginTransaction();
    
    try {
        // Update user basic info
        if (isset($input['name']) || isset($input['phone'])) {
            $updateFields = [];
            $params = [];
            
            if (isset($input['name'])) {
                $updateFields[] = "name = ?";
                $params[] = Validator::sanitizeInput($input['name']);
            }
            
            if (isset($input['phone'])) {
                $updateFields[] = "phone = ?";
                $params[] = Validator::sanitizeInput($input['phone']);
            }
            
            if (!empty($updateFields)) {
                $updateFields[] = "updated_at = CURRENT_TIMESTAMP";
                $params[] = $userId;
                
                $stmt = $conn->prepare("UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?");
                $stmt->execute($params);
            }
        }
        
        // Update manager profile
        if (isset($input['department']) || isset($input['position']) || isset($input['employee_id'])) {
            $updateFields = [];
            $params = [];
            
            if (isset($input['department'])) {
                $updateFields[] = "department = ?";
                $params[] = Validator::sanitizeInput($input['department']);
            }
            
            if (isset($input['position'])) {
                $updateFields[] = "position = ?";
                $params[] = Validator::sanitizeInput($input['position']);
            }
            
            if (isset($input['employee_id'])) {
                $updateFields[] = "employee_id = ?";
                $params[] = Validator::sanitizeInput($input['employee_id']);
            }
            
            if (!empty($updateFields)) {
                $updateFields[] = "updated_at = CURRENT_TIMESTAMP";
                $params[] = $userId;
                
                $stmt = $conn->prepare("UPDATE managers SET " . implode(', ', $updateFields) . " WHERE user_id = ?");
                $stmt->execute($params);
            }
        }
        
        $conn->commit();
        
        Logger::info('Manager profile updated', ['user_id' => $userId, 'updated_by' => $user['user_id']]);
        Response::success(null, 'Manager profile updated successfully');
        
    } catch (Exception $e) {
        $conn->rollBack();
        Logger::error('Manager update failed', ['error' => $e->getMessage()]);
        Response::error('Manager update failed');
    }
}
?>
