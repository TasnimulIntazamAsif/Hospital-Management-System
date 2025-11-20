<?php
// api/auth.php - Authentication endpoints
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
        case 'login':
            handleLogin($conn);
            break;
        case 'register':
            handleRegister($conn);
            break;
        case 'logout':
            handleLogout();
            break;
        case 'profile':
            handleProfile($conn);
            break;
        case 'update-profile':
            handleUpdateProfile($conn);
            break;
        case 'change-password':
            handleChangePassword($conn);
            break;
        default:
            Response::error('Invalid action', 404);
    }
} catch (Exception $e) {
    Logger::error('Auth API Error: ' . $e->getMessage());
    Response::error('Internal server error', 500);
}

function handleLogin($conn) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $required = ['email', 'password'];
    $missing = Validator::validateRequired($required, $input);
    
    if (!empty($missing)) {
        Response::error('Missing required fields: ' . implode(', ', $missing));
    }
    
    $email = Validator::sanitizeInput($input['email']);
    $password = $input['password'];
    
    if (!Validator::validateEmail($email)) {
        Response::error('Invalid email format');
    }
    
    $stmt = $conn->prepare("
        SELECT u.*, d.specialty, d.license_number, d.experience_years, d.consultation_fee, d.bio, d.status as doctor_status,
               p.date_of_birth, p.gender, p.address, p.emergency_contact, p.medical_history, p.nationality,
               p.passport_number, p.passport_expiry, p.passport_path, p.passport_filename,
               p.photo_path, p.photo_filename
        FROM users u
        LEFT JOIN doctors d ON u.id = d.user_id
        LEFT JOIN patients p ON u.id = p.user_id
        WHERE u.email = ? AND u.status = 'active'
    ");
    
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password_hash'])) {
        Logger::warning('Failed login attempt', ['email' => $email]);
        Response::error('Invalid credentials', 401);
    }
    
    // Generate token
    $token = Auth::generateToken($user['id'], $user['role']);
    
    // Remove sensitive data
    unset($user['password_hash']);
    
    // Log successful login
    Logger::info('User logged in', ['user_id' => $user['id'], 'role' => $user['role']]);
    
    Response::success([
        'user' => $user,
        'token' => $token
    ], 'Login successful');
}

function handleRegister($conn) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $required = ['name', 'email', 'password', 'phone', 'role'];
    $missing = Validator::validateRequired($required, $input);
    
    if (!empty($missing)) {
        Response::error('Missing required fields: ' . implode(', ', $missing));
    }
    
    $name = Validator::sanitizeInput($input['name']);
    $email = Validator::sanitizeInput($input['email']);
    $password = $input['password'];
    $phone = Validator::sanitizeInput($input['phone']);
    $role = Validator::sanitizeInput($input['role']);
    
    // Validation
    if (!Validator::validateEmail($email)) {
        Response::error('Invalid email format');
    }
    
    if (!Validator::validatePassword($password)) {
        Response::error('Password must be at least 6 characters long');
    }
    
    if (!Validator::validatePhone($phone)) {
        Response::error('Invalid phone number format');
    }
    
    if (!in_array($role, ['admin', 'doctor', 'patient', 'manager'])) {
        Response::error('Invalid role');
    }
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        Response::error('Email already registered');
    }
    
    $conn->beginTransaction();
    
    try {
        // Create user
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("
            INSERT INTO users (name, email, phone, password_hash, role, status) 
            VALUES (?, ?, ?, ?, ?, 'active')
        ");
        $stmt->execute([$name, $email, $phone, $passwordHash, $role]);
        $userId = $conn->lastInsertId();
        
        // Create role-specific records
        if ($role === 'doctor') {
            createDoctorRecord($conn, $userId, $input);
        } elseif ($role === 'patient') {
            createPatientRecord($conn, $userId, $input);
        }
        
        $conn->commit();
        
        // Generate token
        $token = Auth::generateToken($userId, $role);
        
        Logger::info('User registered', ['user_id' => $userId, 'role' => $role, 'email' => $email]);
        
        Response::success([
            'user_id' => $userId,
            'token' => $token
        ], 'Registration successful');
        
    } catch (Exception $e) {
        $conn->rollBack();
        Logger::error('Registration failed', ['error' => $e->getMessage()]);
        Response::error('Registration failed');
    }
}

function createDoctorRecord($conn, $userId, $input) {
    $required = ['specialty', 'license_number', 'experience_years', 'consultation_fee'];
    $missing = Validator::validateRequired($required, $input);
    
    if (!empty($missing)) {
        throw new Exception('Missing doctor fields: ' . implode(', ', $missing));
    }
    
    $specialty = Validator::sanitizeInput($input['specialty']);
    $licenseNumber = Validator::sanitizeInput($input['license_number']);
    $experienceYears = (int)$input['experience_years'];
    $consultationFee = (float)$input['consultation_fee'];
    $bio = Validator::sanitizeInput($input['bio'] ?? '');
    
    // Check if license number already exists
    $stmt = $conn->prepare("SELECT id FROM doctors WHERE license_number = ?");
    $stmt->execute([$licenseNumber]);
    if ($stmt->fetch()) {
        throw new Exception('License number already exists');
    }
    
    $stmt = $conn->prepare("
        INSERT INTO doctors (user_id, specialty, license_number, experience_years, consultation_fee, bio, status) 
        VALUES (?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([$userId, $specialty, $licenseNumber, $experienceYears, $consultationFee, $bio]);
    
    // Handle photo upload if provided
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $photoData = FileUpload::uploadFile($_FILES['photo'], 'doctors', Config::ALLOWED_IMAGE_TYPES);
        
        $stmt = $conn->prepare("
            UPDATE doctors 
            SET photo_path = ?, photo_filename = ?, photo_size = ?, photo_type = ? 
            WHERE user_id = ?
        ");
        $stmt->execute([
            $photoData['filepath'],
            $photoData['filename'],
            $photoData['filesize'],
            $photoData['filetype'],
            $userId
        ]);
    }
    
    // Handle certificate uploads if provided
    if (isset($_FILES['certificates']) && is_array($_FILES['certificates']['name'])) {
        $certificateCount = count($_FILES['certificates']['name']);
        
        for ($i = 0; $i < $certificateCount; $i++) {
            if ($_FILES['certificates']['error'][$i] === UPLOAD_ERR_OK) {
                $certFile = [
                    'name' => $_FILES['certificates']['name'][$i],
                    'type' => $_FILES['certificates']['type'][$i],
                    'tmp_name' => $_FILES['certificates']['tmp_name'][$i],
                    'error' => $_FILES['certificates']['error'][$i],
                    'size' => $_FILES['certificates']['size'][$i]
                ];
                
                $certData = FileUpload::uploadFile($certFile, 'certificates', ['pdf', 'jpg', 'jpeg', 'png']);
                
                $stmt = $conn->prepare("
                    INSERT INTO certificates (doctor_id, certificate_name, issuing_authority, issue_date, expiry_date, file_path, file_name, file_size, file_type, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                ");
                $stmt->execute([
                    $conn->lastInsertId(),
                    Validator::sanitizeInput($input['certificate_names'][$i] ?? 'Certificate'),
                    Validator::sanitizeInput($input['certificate_authorities'][$i] ?? 'Unknown'),
                    $input['certificate_issue_dates'][$i] ?? date('Y-m-d'),
                    $input['certificate_expiry_dates'][$i] ?? null,
                    $certData['filepath'],
                    $certData['filename'],
                    $certData['filesize'],
                    $certData['filetype']
                ]);
            }
        }
    }
}

function createPatientRecord($conn, $userId, $input) {
    $dateOfBirth = $input['date_of_birth'] ?? null;
    $gender = Validator::sanitizeInput($input['gender'] ?? '');
    $address = Validator::sanitizeInput($input['address'] ?? '');
    $emergencyContact = Validator::sanitizeInput($input['emergency_contact'] ?? '');
    $nationality = Validator::sanitizeInput($input['nationality'] ?? 'local');
    $passportNumber = Validator::sanitizeInput($input['passport_number'] ?? '');
    $passportExpiry = $input['passport_expiry'] ?? null;
    
    $stmt = $conn->prepare("
        INSERT INTO patients (user_id, date_of_birth, gender, address, emergency_contact, nationality, passport_number, passport_expiry) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $dateOfBirth, $gender, $address, $emergencyContact, $nationality, $passportNumber, $passportExpiry]);
    
    // Handle photo upload if provided
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $photoData = FileUpload::uploadFile($_FILES['photo'], 'patients', Config::ALLOWED_IMAGE_TYPES);
        
        $stmt = $conn->prepare("
            UPDATE patients 
            SET photo_path = ?, photo_filename = ? 
            WHERE user_id = ?
        ");
        $stmt->execute([$photoData['filepath'], $photoData['filename'], $userId]);
    }
    
    // Handle passport upload for international patients
    if ($nationality === 'international' && isset($_FILES['passport']) && $_FILES['passport']['error'] === UPLOAD_ERR_OK) {
        $passportData = FileUpload::uploadFile($_FILES['passport'], 'passports', ['pdf', 'jpg', 'jpeg', 'png']);
        
        $stmt = $conn->prepare("
            UPDATE patients 
            SET passport_path = ?, passport_filename = ? 
            WHERE user_id = ?
        ");
        $stmt->execute([$passportData['filepath'], $passportData['filename'], $userId]);
    }
}

function handleLogout() {
    // In a stateless JWT system, logout is handled client-side
    // We could implement a token blacklist here if needed
    Response::success(null, 'Logout successful');
}

function handleProfile($conn) {
    $user = Auth::requireAuth();
    
    $stmt = $conn->prepare("
        SELECT u.*, d.specialty, d.license_number, d.experience_years, d.consultation_fee, d.bio, d.status as doctor_status,
               p.date_of_birth, p.gender, p.address, p.emergency_contact, p.medical_history, p.nationality,
               p.passport_number, p.passport_expiry, p.passport_path, p.passport_filename,
               p.photo_path, p.photo_filename
        FROM users u
        LEFT JOIN doctors d ON u.id = d.user_id
        LEFT JOIN patients p ON u.id = p.user_id
        WHERE u.id = ?
    ");
    
    $stmt->execute([$user['user_id']]);
    $profile = $stmt->fetch();
    
    if (!$profile) {
        Response::notFound('Profile not found');
    }
    
    unset($profile['password_hash']);
    Response::success($profile);
}

function handleUpdateProfile($conn) {
    $user = Auth::requireAuth();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Method not allowed', 405);
    }
    
    // Handle JSON input for PUT or form data for POST
    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
    } else {
        $input = $_POST;
        // Handle multipart form data
        if (isset($input['data'])) {
            $input = json_decode($input['data'], true);
        }
    }
    
    $conn->beginTransaction();
    
    try {
        // Update user basic info
        if (isset($input['name']) || isset($input['phone'])) {
            $stmt = $conn->prepare("
                UPDATE users 
                SET name = ?, phone = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmt->execute([
                Validator::sanitizeInput($input['name'] ?? ''),
                Validator::sanitizeInput($input['phone'] ?? ''),
                $user['user_id']
            ]);
        }
        
        // Handle photo upload
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            if ($user['role'] === 'doctor') {
                $photoData = FileUpload::uploadFile($_FILES['photo'], 'doctors', Config::ALLOWED_IMAGE_TYPES);
                
                $stmt = $conn->prepare("
                    UPDATE doctors 
                    SET photo_path = ?, photo_filename = ?, photo_size = ?, photo_type = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE user_id = ?
                ");
                $stmt->execute([
                    $photoData['filepath'],
                    $photoData['filename'],
                    $photoData['filesize'],
                    $photoData['filetype'],
                    $user['user_id']
                ]);
            } elseif ($user['role'] === 'patient') {
                $photoData = FileUpload::uploadFile($_FILES['photo'], 'patients', Config::ALLOWED_IMAGE_TYPES);
                
                $stmt = $conn->prepare("
                    UPDATE patients 
                    SET photo_path = ?, photo_filename = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE user_id = ?
                ");
                $stmt->execute([
                    $photoData['filepath'],
                    $photoData['filename'],
                    $user['user_id']
                ]);
            }
        }
        
        // Update role-specific info
        if ($user['role'] === 'doctor' && isset($input['specialty'])) {
            updateDoctorProfile($conn, $user['user_id'], $input);
        } elseif ($user['role'] === 'patient' && (isset($input['address']) || isset($input['emergency_contact']))) {
            updatePatientProfile($conn, $user['user_id'], $input);
        }
        
        $conn->commit();
        Logger::info('Profile updated', ['user_id' => $user['user_id']]);
        
        Response::success(null, 'Profile updated successfully');
        
    } catch (Exception $e) {
        $conn->rollBack();
        Logger::error('Profile update failed', ['error' => $e->getMessage()]);
        Response::error('Profile update failed: ' . $e->getMessage());
    }
}

function updateDoctorProfile($conn, $userId, $input) {
    // Build dynamic update query based on provided fields
    $fields = [];
    $values = [];
    
    if (isset($input['specialty'])) {
        $fields[] = "specialty = ?";
        $values[] = Validator::sanitizeInput($input['specialty']);
    }
    if (isset($input['bio'])) {
        $fields[] = "bio = ?";
        $values[] = Validator::sanitizeInput($input['bio']);
    }
    if (isset($input['consultation_fee'])) {
        $fields[] = "consultation_fee = ?";
        $values[] = (float)$input['consultation_fee'];
    }
    
    if (!empty($fields)) {
        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $values[] = $userId;
        
        $sql = "UPDATE doctors SET " . implode(", ", $fields) . " WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute($values);
    }
}

function updatePatientProfile($conn, $userId, $input) {
    // Build dynamic update query based on provided fields
    $fields = [];
    $values = [];
    
    if (isset($input['address'])) {
        $fields[] = "address = ?";
        $values[] = Validator::sanitizeInput($input['address']);
    }
    if (isset($input['emergency_contact'])) {
        $fields[] = "emergency_contact = ?";
        $values[] = Validator::sanitizeInput($input['emergency_contact']);
    }
    if (isset($input['medical_history'])) {
        $fields[] = "medical_history = ?";
        $values[] = Validator::sanitizeInput($input['medical_history']);
    }
    
    if (!empty($fields)) {
        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $values[] = $userId;
        
        $sql = "UPDATE patients SET " . implode(", ", $fields) . " WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute($values);
    }
}

function handleChangePassword($conn) {
    $user = Auth::requireAuth();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $required = ['current_password', 'new_password'];
    $missing = Validator::validateRequired($required, $input);
    
    if (!empty($missing)) {
        Response::error('Missing required fields: ' . implode(', ', $missing));
    }
    
    $currentPassword = $input['current_password'];
    $newPassword = $input['new_password'];
    
    if (!Validator::validatePassword($newPassword)) {
        Response::error('New password must be at least 6 characters long');
    }
    
    // Verify current password
    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$user['user_id']]);
    $userData = $stmt->fetch();
    
    if (!password_verify($currentPassword, $userData['password_hash'])) {
        Response::error('Current password is incorrect', 401);
    }
    
    // Update password
    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$newPasswordHash, $user['user_id']]);
    
    Logger::info('Password changed', ['user_id' => $user['user_id']]);
    Response::success(null, 'Password changed successfully');
}
?>
