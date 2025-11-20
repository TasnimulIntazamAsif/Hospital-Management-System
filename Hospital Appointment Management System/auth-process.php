<?php
// auth-process.php - Handle login and registration
session_start();
require_once 'config/database.php';

$action = $_GET['action'] ?? 'login';

if ($action === 'login') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: index.php');
        exit;
    }
    
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
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
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Check role match
            if ($user['role'] !== $role) {
                $_SESSION['error'] = 'Invalid role selection';
                header('Location: index.php');
                exit;
            }
            
            // Set session data
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_phone'] = $user['phone'];
            
            // Role-specific data
            if ($user['role'] === 'doctor') {
                $_SESSION['doctor_specialty'] = $user['specialty'] ?? '';
                $_SESSION['doctor_status'] = $user['doctor_status'] ?? '';
                $_SESSION['photo_path'] = $user['photo_path'] ?? '';
            }
            
            if ($user['role'] === 'patient') {
                $_SESSION['photo_path'] = $user['photo_path'] ?? '';
                $_SESSION['nationality'] = $user['nationality'] ?? 'local';
            }
            
            // Redirect to appropriate dashboard
            header('Location: dashboard-' . $user['role'] . '.php');
            exit;
        } else {
            $_SESSION['error'] = 'Invalid credentials';
            header('Location: index.php');
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Login failed: ' . $e->getMessage();
        header('Location: index.php');
        exit;
    }
}

if ($action === 'register') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: index.php');
        exit;
    }
    
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $role = $_POST['role'] ?? '';
        
        // Validation
        if (empty($name) || empty($email) || empty($password) || empty($phone) || empty($role)) {
            $_SESSION['error'] = 'Please fill all required fields';
            header('Location: index.php');
            exit;
        }
        
        // Check if email exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = 'Email already registered';
            header('Location: index.php');
            exit;
        }
        
        $conn->beginTransaction();
        
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
            $specialty = $_POST['specialty'] ?? '';
            $licenseNumber = $_POST['license'] ?? '';
            $experienceYears = $_POST['experience'] ?? 0;
            $consultationFee = $_POST['consultation_fee'] ?? 0;
            $bio = $_POST['bio'] ?? '';
            
            $stmt = $conn->prepare("
                INSERT INTO doctors (user_id, specialty, license_number, experience_years, consultation_fee, bio, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$userId, $specialty, $licenseNumber, $experienceYears, $consultationFee, $bio]);
            
            // Handle photo upload
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/doctors/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $photoExtension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                $photoFilename = uniqid() . '_' . time() . '.' . $photoExtension;
                $photoPath = $uploadDir . $photoFilename;
                
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $photoPath)) {
                    $stmt = $conn->prepare("
                        UPDATE doctors 
                        SET photo_path = ?, photo_filename = ?, photo_size = ?, photo_type = ? 
                        WHERE user_id = ?
                    ");
                    $stmt->execute([
                        $photoPath,
                        $photoFilename,
                        $_FILES['photo']['size'],
                        $_FILES['photo']['type'],
                        $userId
                    ]);
                }
            }
        } elseif ($role === 'patient') {
            $dateOfBirth = $_POST['date_of_birth'] ?? null;
            $gender = $_POST['gender'] ?? '';
            $address = $_POST['address'] ?? '';
            $emergencyContact = $_POST['emergency_contact'] ?? '';
            $nationality = $_POST['nationality'] ?? 'local';
            
            $stmt = $conn->prepare("
                INSERT INTO patients (user_id, date_of_birth, gender, address, emergency_contact, nationality) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $dateOfBirth, $gender, $address, $emergencyContact, $nationality]);
            
            // Handle photo upload if provided
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/patients/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $photoExtension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                $photoFilename = uniqid() . '_' . time() . '.' . $photoExtension;
                $photoPath = $uploadDir . $photoFilename;
                
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $photoPath)) {
                    $stmt = $conn->prepare("
                        UPDATE patients 
                        SET photo_path = ?, photo_filename = ? 
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$photoPath, $photoFilename, $userId]);
                }
            }
        }
        
        $conn->commit();
        $_SESSION['success'] = 'Registration successful! Please login.';
        header('Location: index.php');
        exit;
        
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = 'Registration failed: ' . $e->getMessage();
        header('Location: index.php');
        exit;
    }
}

if ($action === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}
?>

