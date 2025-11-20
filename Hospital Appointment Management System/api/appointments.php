<?php
// api/appointments.php - Appointment and payment management endpoints
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
        case 'list':
            handleListAppointments($conn);
            break;
        case 'get':
            handleGetAppointment($conn);
            break;
        case 'create':
            handleCreateAppointment($conn);
            break;
        case 'update':
            handleUpdateAppointment($conn);
            break;
        case 'approve':
            handleApproveAppointment($conn);
            break;
        case 'cancel':
            handleCancelAppointment($conn);
            break;
        case 'complete':
            handleCompleteAppointment($conn);
            break;
        case 'schedule':
            handleDoctorSchedule($conn);
            break;
        case 'available-slots':
            handleAvailableSlots($conn);
            break;
        case 'payments':
            handlePayments($conn);
            break;
        case 'create-payment':
            handleCreatePayment($conn);
            break;
        case 'verify-payment':
            handleVerifyPayment($conn);
            break;
        case 'reject-payment':
            handleRejectPayment($conn);
            break;
        case 'update-schedule':
            handleUpdateSchedule($conn);
            break;
        case 'get-schedule':
            handleGetSchedule($conn);
            break;
        case 'delete-schedule':
            handleDeleteSchedule($conn);
            break;
        default:
            Response::error('Invalid action', 404);
    }
} catch (Exception $e) {
    Logger::error('Appointment API Error: ' . $e->getMessage());
    Response::error('Internal server error', 500);
}

function handleUpdateSchedule($conn) {
    // Check session authentication
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'doctor') {
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $day = $input['day'] ?? '';
    $startTime = $input['start_time'] ?? '';
    $endTime = $input['end_time'] ?? '';
    
    if (empty($day) || empty($startTime) || empty($endTime)) {
        echo json_encode(['success' => false, 'message' => 'Day, start time, and end time required']);
        exit;
    }
    
    // Get doctor ID from doctors table
    $stmt = $conn->prepare("SELECT id FROM doctors WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $doctorRecord = $stmt->fetch();
    if (!$doctorRecord) {
        echo json_encode(['success' => false, 'message' => 'Doctor profile not found']);
        exit;
    }
    $doctorId = $doctorRecord['id'];
    
    try {
        // Check if schedule exists
        $stmt = $conn->prepare("SELECT id FROM doctor_schedules WHERE doctor_id = ? AND day_of_week = ?");
        $stmt->execute([$doctorId, strtolower($day)]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update existing schedule
            $stmt = $conn->prepare("UPDATE doctor_schedules SET start_time = ?, end_time = ? WHERE doctor_id = ? AND day_of_week = ?");
            $stmt->execute([$startTime, $endTime, $doctorId, strtolower($day)]);
        } else {
            // Insert new schedule
            $stmt = $conn->prepare("INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, slot_duration, break_time) VALUES (?, ?, ?, ?, 30, 15)");
            $stmt->execute([$doctorId, strtolower($day), $startTime, $endTime]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Schedule updated successfully']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to update schedule: ' . $e->getMessage()]);
    }
    exit;
}

function handleGetSchedule($conn) {
    // Check session authentication
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'doctor') {
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    
    // Get doctor ID
    $stmt = $conn->prepare("SELECT id FROM doctors WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $doctorRecord = $stmt->fetch();
    if (!$doctorRecord) {
        echo json_encode(['success' => false, 'data' => []]);
        exit;
    }
    $doctorId = $doctorRecord['id'];
    
    // Get schedule
    $stmt = $conn->prepare("SELECT * FROM doctor_schedules WHERE doctor_id = ? ORDER BY FIELD(day_of_week, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday')");
    $stmt->execute([$doctorId]);
    $schedule = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $schedule]);
    exit;
}

function handleDeleteSchedule($conn) {
    // Check session authentication
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'doctor') {
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    
    $day = $_GET['day'] ?? '';
    if (empty($day)) {
        echo json_encode(['success' => false, 'message' => 'Day required']);
        exit;
    }
    
    // Get doctor ID
    $stmt = $conn->prepare("SELECT id FROM doctors WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $doctorRecord = $stmt->fetch();
    if (!$doctorRecord) {
        echo json_encode(['success' => false, 'message' => 'Doctor profile not found']);
        exit;
    }
    $doctorId = $doctorRecord['id'];
    
    // Delete schedule
    $stmt = $conn->prepare("DELETE FROM doctor_schedules WHERE doctor_id = ? AND day_of_week = ?");
    $stmt->execute([$doctorId, strtolower($day)]);
    
    echo json_encode(['success' => true, 'message' => 'Schedule deleted successfully']);
    exit;
}

function handleListAppointments($conn) {
    $user = Auth::requireAuth();
    
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $status = $_GET['status'] ?? '';
    $date = $_GET['date'] ?? '';
    $search = $_GET['search'] ?? '';
    
    $offset = ($page - 1) * $limit;
    
    $whereConditions = [];
    $params = [];
    
    // Role-based filtering
    if ($user['role'] === 'doctor') {
        $whereConditions[] = "a.doctor_id = ?";
        $params[] = $user['user_id'];
    } elseif ($user['role'] === 'patient') {
        $whereConditions[] = "a.patient_id = ?";
        $params[] = $user['user_id'];
    }
    
    // Status filtering
    if ($status) {
        $whereConditions[] = "a.status = ?";
        $params[] = $status;
    }
    
    // Date filtering
    if ($date) {
        $whereConditions[] = "a.appointment_date = ?";
        $params[] = $date;
    }
    
    // Search filtering
    if ($search) {
        $whereConditions[] = "(u1.name LIKE ? OR u2.name LIKE ? OR a.reason LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get total count
    $countSql = "
        SELECT COUNT(*) as total
        FROM appointments a
        JOIN users u1 ON a.doctor_id = u1.id
        JOIN users u2 ON a.patient_id = u2.id
        $whereClause
    ";
    $stmt = $conn->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    
    // Get appointments
    $sql = "
        SELECT 
            a.id,
            a.appointment_date,
            a.appointment_time,
            a.status,
            a.reason,
            a.notes,
            a.created_at,
            u1.name as doctor_name,
            u1.email as doctor_email,
            u2.name as patient_name,
            u2.email as patient_email,
            d.specialty as doctor_specialty,
            d.consultation_fee,
            p.amount as payment_amount,
            p.status as payment_status
        FROM appointments a
        JOIN users u1 ON a.doctor_id = u1.id
        JOIN users u2 ON a.patient_id = u2.id
        LEFT JOIN doctors d ON a.doctor_id = d.user_id
        LEFT JOIN payments p ON a.id = p.appointment_id
        $whereClause
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();
    
    Response::success([
        'appointments' => $appointments,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

function handleGetAppointment($conn) {
    $user = Auth::requireAuth();
    
    $appointmentId = (int)($_GET['id'] ?? 0);
    if (!$appointmentId) {
        Response::error('Appointment ID required');
    }
    
    // Get appointment with access control
    $sql = "
        SELECT 
            a.*,
            u1.name as doctor_name,
            u1.email as doctor_email,
            u2.name as patient_name,
            u2.email as patient_email,
            d.specialty as doctor_specialty,
            d.consultation_fee,
            p.amount as payment_amount,
            p.status as payment_status,
            p.transaction_id,
            p.payment_method
        FROM appointments a
        JOIN users u1 ON a.doctor_id = u1.id
        JOIN users u2 ON a.patient_id = u2.id
        LEFT JOIN doctors d ON a.doctor_id = d.user_id
        LEFT JOIN payments p ON a.id = p.appointment_id
        WHERE a.id = ?
    ";
    
    $params = [$appointmentId];
    
    // Add access control
    if ($user['role'] === 'doctor') {
        $sql .= " AND a.doctor_id = ?";
        $params[] = $user['user_id'];
    } elseif ($user['role'] === 'patient') {
        $sql .= " AND a.patient_id = ?";
        $params[] = $user['user_id'];
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $appointment = $stmt->fetch();
    
    if (!$appointment) {
        Response::notFound('Appointment not found');
    }
    
    Response::success($appointment);
}

function handleCreateAppointment($conn) {
    // Check session authentication
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['patient', 'admin'])) {
        echo json_encode(['success' => false, 'message' => 'Authentication required', 'data' => null]);
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Method not allowed', 405);
    }
    
    $user = [
        'user_id' => $_SESSION['user_id'],
        'role' => $_SESSION['user_role']
    ];
    
    $input = json_decode(file_get_contents('php://input'), true);
    $required = ['doctor_id', 'appointment_date', 'appointment_time', 'reason'];
    $missing = Validator::validateRequired($required, $input);
    
    if (!empty($missing)) {
        Response::error('Missing required fields: ' . implode(', ', $missing));
    }
    
    $doctorId = (int)$input['doctor_id'];
    $appointmentDate = $input['appointment_date'];
    $appointmentTime = $input['appointment_time'];
    $reason = Validator::sanitizeInput($input['reason']);
    $notes = Validator::sanitizeInput($input['notes'] ?? '');
    
    // Validate appointment date (must be future)
    if (strtotime($appointmentDate) < strtotime(date('Y-m-d'))) {
        Response::error('Appointment date must be in the future');
    }
    
    // Get patient ID from patients table using user_id from session
    $stmt = $conn->prepare("SELECT id FROM patients WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $patientRecord = $stmt->fetch();
    if (!$patientRecord) {
        Response::error('Patient profile not found');
    }
    $patientId = $patientRecord['id'];
    
    // Verify doctor exists and is available
    $stmt = $conn->prepare("
        SELECT d.*, u.name as doctor_name 
        FROM doctors d 
        JOIN users u ON d.user_id = u.id 
        WHERE d.id = ? AND d.status = 'approved' AND u.status = 'active'
    ");
    $stmt->execute([$doctorId]);
    $doctor = $stmt->fetch();
    
    if (!$doctor) {
        Response::error('Doctor not found or not available');
    }
    
    // Check if doctor is available at the requested time
    if (!isDoctorAvailable($conn, $doctorId, $appointmentDate, $appointmentTime)) {
        Response::error('Doctor is not available at the requested time');
    }
    
    // Check for existing appointment conflict
    $stmt = $conn->prepare("
        SELECT id FROM appointments 
        WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status IN ('pending', 'confirmed')
    ");
    $stmt->execute([$doctorId, $appointmentDate, $appointmentTime]);
    if ($stmt->fetch()) {
        Response::error('Time slot is already booked');
    }
    
    $conn->beginTransaction();
    
    try {
        // Create appointment
        $stmt = $conn->prepare("
            INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, status, reason, notes) 
            VALUES (?, ?, ?, ?, 'pending', ?, ?)
        ");
        $stmt->execute([$patientId, $doctorId, $appointmentDate, $appointmentTime, $reason, $notes]);
        $appointmentId = $conn->lastInsertId();
        
        // Create payment record
        $transactionId = 'TXN' . date('Ymd') . sprintf('%06d', rand(100000, 999999));
        $stmt = $conn->prepare("
            INSERT INTO payments (appointment_id, patient_id, amount, payment_method, status, transaction_id) 
            VALUES (?, ?, ?, 'online', 'pending', ?)
        ");
        $stmt->execute([$appointmentId, $patientId, $doctor['consultation_fee'], $transactionId]);
        
        $conn->commit();
        
        Logger::info('Appointment created', [
            'appointment_id' => $appointmentId,
            'doctor_id' => $doctorId,
            'patient_id' => $patientId,
            'appointment_date' => $appointmentDate,
            'appointment_time' => $appointmentTime
        ]);
        
        Response::success([
            'appointment_id' => $appointmentId,
            'payment_required' => $doctor['consultation_fee']
        ], 'Appointment created successfully');
        
    } catch (Exception $e) {
        $conn->rollBack();
        Logger::error('Appointment creation failed', ['error' => $e->getMessage()]);
        Response::error('Appointment creation failed');
    }
}

function handleUpdateAppointment($conn) {
    $user = Auth::requireAuth();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        Response::error('Method not allowed', 405);
    }
    
    $appointmentId = (int)($_GET['id'] ?? 0);
    if (!$appointmentId) {
        Response::error('Appointment ID required');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Verify appointment exists and access
    $sql = "SELECT * FROM appointments WHERE id = ?";
    $params = [$appointmentId];
    
    if ($user['role'] === 'doctor') {
        $sql .= " AND doctor_id = ?";
        $params[] = $user['user_id'];
    } elseif ($user['role'] === 'patient') {
        $sql .= " AND patient_id = ?";
        $params[] = $user['user_id'];
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $appointment = $stmt->fetch();
    
    if (!$appointment) {
        Response::notFound('Appointment not found');
    }
    
    // Only allow updates for pending appointments
    if ($appointment['status'] !== 'pending') {
        Response::error('Only pending appointments can be updated');
    }
    
    $reason = Validator::sanitizeInput($input['reason'] ?? $appointment['reason']);
    $notes = Validator::sanitizeInput($input['notes'] ?? $appointment['notes']);
    
    $stmt = $conn->prepare("
        UPDATE appointments 
        SET reason = ?, notes = ?, updated_at = CURRENT_TIMESTAMP 
        WHERE id = ?
    ");
    $stmt->execute([$reason, $notes, $appointmentId]);
    
    Logger::info('Appointment updated', [
        'appointment_id' => $appointmentId,
        'updated_by' => $user['user_id']
    ]);
    
    Response::success(null, 'Appointment updated successfully');
}

function handleApproveAppointment($conn) {
    // Check session authentication
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'doctor') {
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    
    $appointmentId = (int)($_GET['id'] ?? 0);
    if (!$appointmentId) {
        echo json_encode(['success' => false, 'message' => 'Appointment ID required']);
        exit;
    }
    
    try {
        // Update appointment status to confirmed
        $stmt = $conn->prepare("UPDATE appointments SET status = 'confirmed' WHERE id = ?");
        $stmt->execute([$appointmentId]);
        
        echo json_encode(['success' => true, 'message' => 'Appointment approved successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

function handleCancelAppointment($conn) {
    // Check session authentication
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    
    $appointmentId = (int)($_GET['id'] ?? 0);
    if (!$appointmentId) {
        echo json_encode(['success' => false, 'message' => 'Appointment ID required']);
        exit;
    }
    
    try {
        // Update appointment status to cancelled
        $stmt = $conn->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ?");
        $stmt->execute([$appointmentId]);
        
        echo json_encode(['success' => true, 'message' => 'Appointment cancelled successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

function handleCompleteAppointment($conn) {
    $user = Auth::requireRole(['doctor']);
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Method not allowed', 405);
    }
    
    $appointmentId = (int)($_GET['id'] ?? 0);
    if (!$appointmentId) {
        Response::error('Appointment ID required');
    }
    
    // Verify appointment exists and belongs to doctor
    $stmt = $conn->prepare("SELECT * FROM appointments WHERE id = ? AND doctor_id = ?");
    $stmt->execute([$appointmentId, $user['user_id']]);
    $appointment = $stmt->fetch();
    
    if (!$appointment) {
        Response::notFound('Appointment not found');
    }
    
    if ($appointment['status'] !== 'confirmed') {
        Response::error('Only confirmed appointments can be completed');
    }
    
    $stmt = $conn->prepare("
        UPDATE appointments 
        SET status = 'completed', updated_at = CURRENT_TIMESTAMP 
        WHERE id = ?
    ");
    $stmt->execute([$appointmentId]);
    
    Logger::info('Appointment completed', [
        'appointment_id' => $appointmentId,
        'doctor_id' => $user['user_id']
    ]);
    
    Response::success(null, 'Appointment completed successfully');
}

function handleDoctorSchedule($conn) {
    $user = Auth::requireRole(['doctor']);
    
    $doctorId = $user['user_id'];
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get doctor schedule
        $stmt = $conn->prepare("
            SELECT * FROM doctor_schedules 
            WHERE doctor_id = ? AND is_available = TRUE 
            ORDER BY day_of_week, start_time
        ");
        $stmt->execute([$doctorId]);
        $schedule = $stmt->fetchAll();
        
        Response::success($schedule);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Update doctor schedule
        $input = json_decode(file_get_contents('php://input'), true);
        
        $conn->beginTransaction();
        
        try {
            // Delete existing schedule
            $stmt = $conn->prepare("DELETE FROM doctor_schedules WHERE doctor_id = ?");
            $stmt->execute([$doctorId]);
            
            // Insert new schedule
            foreach ($input['schedule'] as $daySchedule) {
                $stmt = $conn->prepare("
                    INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, slot_duration, break_time, is_available) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $doctorId,
                    $daySchedule['day_of_week'],
                    $daySchedule['start_time'],
                    $daySchedule['end_time'],
                    $daySchedule['slot_duration'] ?? 30,
                    $daySchedule['break_time'] ?? 15,
                    $daySchedule['is_available'] ?? true
                ]);
            }
            
            $conn->commit();
            
            Logger::info('Doctor schedule updated', ['doctor_id' => $doctorId]);
            
            Response::success(null, 'Schedule updated successfully');
            
        } catch (Exception $e) {
            $conn->rollBack();
            Logger::error('Schedule update failed', ['error' => $e->getMessage()]);
            Response::error('Schedule update failed');
        }
    }
}

function handleAvailableSlots($conn) {
    $user = Auth::requireAuth();
    
    $doctorId = (int)($_GET['doctor_id'] ?? 0);
    $date = $_GET['date'] ?? '';
    
    if (!$doctorId || !$date) {
        Response::error('Doctor ID and date are required');
    }
    
    // Get doctor schedule for the day of week
    $dayOfWeek = strtolower(date('l', strtotime($date)));
    
    $stmt = $conn->prepare("
        SELECT * FROM doctor_schedules 
        WHERE doctor_id = ? AND day_of_week = ? AND is_available = TRUE
    ");
    $stmt->execute([$doctorId, $dayOfWeek]);
    $schedule = $stmt->fetch();
    
    if (!$schedule) {
        Response::success([]);
    }
    
    // Get booked slots
    $stmt = $conn->prepare("
        SELECT appointment_time FROM appointments 
        WHERE doctor_id = ? AND appointment_date = ? AND status IN ('pending', 'confirmed')
    ");
    $stmt->execute([$doctorId, $date]);
    $bookedSlots = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Generate available slots
    $availableSlots = [];
    $startTime = strtotime($schedule['start_time']);
    $endTime = strtotime($schedule['end_time']);
    $slotDuration = $schedule['slot_duration'] * 60; // Convert to seconds
    $breakTime = $schedule['break_time'] * 60;
    
    $currentTime = $startTime;
    while ($currentTime < $endTime) {
        $timeSlot = date('H:i:s', $currentTime);
        
        if (!in_array($timeSlot, $bookedSlots)) {
            $availableSlots[] = $timeSlot;
        }
        
        $currentTime += $slotDuration + $breakTime;
    }
    
    Response::success($availableSlots);
}

function handlePayments($conn) {
    $user = Auth::requireAuth();
    
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $status = $_GET['status'] ?? '';
    
    $offset = ($page - 1) * $limit;
    
    $whereConditions = [];
    $params = [];
    
    // Role-based filtering
    if ($user['role'] === 'patient') {
        $whereConditions[] = "p.patient_id = ?";
        $params[] = $user['user_id'];
    } elseif ($user['role'] === 'manager') {
        // Managers can see all payments
    } else {
        Response::forbidden('Access denied');
    }
    
    // Status filtering
    if ($status) {
        $whereConditions[] = "p.status = ?";
        $params[] = $status;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get payments
    $sql = "
        SELECT 
            p.*,
            u.name as patient_name,
            u.email as patient_email,
            a.appointment_date,
            a.appointment_time,
            d.name as doctor_name,
            d.specialty as doctor_specialty
        FROM payments p
        JOIN users u ON p.patient_id = u.id
        JOIN appointments a ON p.appointment_id = a.id
        JOIN users d ON a.doctor_id = d.id
        $whereClause
        ORDER BY p.payment_date DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $payments = $stmt->fetchAll();
    
    Response::success($payments);
}

function handleCreatePayment($conn) {
    $user = Auth::requireRole(['patient']);
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $required = ['appointment_id', 'payment_method'];
    $missing = Validator::validateRequired($required, $input);
    
    if (!empty($missing)) {
        Response::error('Missing required fields: ' . implode(', ', $missing));
    }
    
    $appointmentId = (int)$input['appointment_id'];
    $paymentMethod = Validator::sanitizeInput($input['payment_method']);
    
    // Verify appointment belongs to patient
    $stmt = $conn->prepare("
        SELECT a.*, d.consultation_fee 
        FROM appointments a 
        JOIN doctors d ON a.doctor_id = d.user_id 
        WHERE a.id = ? AND a.patient_id = ?
    ");
    $stmt->execute([$appointmentId, $user['user_id']]);
    $appointment = $stmt->fetch();
    
    if (!$appointment) {
        Response::notFound('Appointment not found');
    }
    
    // Check if payment already exists
    $stmt = $conn->prepare("SELECT id FROM payments WHERE appointment_id = ?");
    $stmt->execute([$appointmentId]);
    if ($stmt->fetch()) {
        Response::error('Payment already exists for this appointment');
    }
    
    // Generate transaction ID
    $transactionId = 'TXN' . date('Ymd') . sprintf('%06d', rand(100000, 999999));
    
    $stmt = $conn->prepare("
        INSERT INTO payments (appointment_id, patient_id, amount, payment_method, status, transaction_id) 
        VALUES (?, ?, ?, ?, 'pending', ?)
    ");
    $stmt->execute([$appointmentId, $user['user_id'], $appointment['consultation_fee'], $paymentMethod, $transactionId]);
    
    Logger::info('Payment created', [
        'payment_id' => $conn->lastInsertId(),
        'appointment_id' => $appointmentId,
        'patient_id' => $user['user_id'],
        'amount' => $appointment['consultation_fee']
    ]);
    
    Response::success([
        'payment_id' => $conn->lastInsertId(),
        'transaction_id' => $transactionId,
        'amount' => $appointment['consultation_fee']
    ], 'Payment created successfully');
}

function handleVerifyPayment($conn) {
    // Check session authentication
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    
    $paymentId = (int)($_GET['id'] ?? 0);
    if (!$paymentId) {
        echo json_encode(['success' => false, 'message' => 'Payment ID required']);
        exit;
    }
    
    try {
        // Get payment info
        $stmt = $conn->prepare("SELECT * FROM payments WHERE id = ?");
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch();
        
        if (!$payment) {
            echo json_encode(['success' => false, 'message' => 'Payment not found']);
            exit;
        }
        
        // Update payment status to completed and set verified info
        $stmt = $conn->prepare("
            UPDATE payments 
            SET status = 'completed', 
                verified_by = ?, 
                verified_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $paymentId]);
        
        echo json_encode(['success' => true, 'message' => 'Payment verified successfully']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

function handleRejectPayment($conn) {
    // Check session authentication
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    
    $paymentId = (int)($_GET['id'] ?? 0);
    if (!$paymentId) {
        echo json_encode(['success' => false, 'message' => 'Payment ID required']);
        exit;
    }
    
    try {
        // Update payment status to failed
        $stmt = $conn->prepare("
            UPDATE payments 
            SET status = 'failed' 
            WHERE id = ?
        ");
        $stmt->execute([$paymentId]);
        
        echo json_encode(['success' => true, 'message' => 'Payment rejected successfully']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

// Removed duplicate function definition

function isDoctorAvailable($conn, $doctorId, $date, $time) {
    $dayOfWeek = strtolower(date('l', strtotime($date)));
    
    $stmt = $conn->prepare("
        SELECT * FROM doctor_schedules 
        WHERE doctor_id = ? AND day_of_week = ? AND is_available = TRUE
    ");
    $stmt->execute([$doctorId, $dayOfWeek]);
    $schedule = $stmt->fetch();
    
    if (!$schedule) {
        return false;
    }
    
    $appointmentTime = strtotime($time);
    $startTime = strtotime($schedule['start_time']);
    $endTime = strtotime($schedule['end_time']);
    
    return $appointmentTime >= $startTime && $appointmentTime < $endTime;
}
?>
