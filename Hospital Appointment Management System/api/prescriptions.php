<?php
// api/prescriptions.php - Prescription management endpoints
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
        case 'create':
            handleCreatePrescription($conn);
            break;
        case 'list':
            handleListPrescriptions($conn);
            break;
        case 'get':
            handleGetPrescription($conn);
            break;
        case 'update':
            handleUpdatePrescription($conn);
            break;
        case 'delete':
            handleDeletePrescription($conn);
            break;
        case 'print':
            handlePrintPrescription($conn);
            break;
        case 'templates':
            handlePrescriptionTemplates($conn);
            break;
        case 'save-template':
            handleSaveTemplate($conn);
            break;
        case 'use-template':
            handleUseTemplate($conn);
            break;
        default:
            Response::error('Invalid action', 404);
    }
} catch (Exception $e) {
    Logger::error('Prescription API Error: ' . $e->getMessage());
    Response::error('Internal server error', 500);
}

function handleCreatePrescription($conn) {
    $user = Auth::requireRole(['doctor']);
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $required = ['patient_id', 'diagnosis'];
    $missing = Validator::validateRequired($required, $input);
    
    if (!empty($missing)) {
        Response::error('Missing required fields: ' . implode(', ', $missing));
    }
    
    $patientId = (int)$input['patient_id'];
    $diagnosis = Validator::sanitizeInput($input['diagnosis']);
    $symptoms = Validator::sanitizeInput($input['symptoms'] ?? '');
    $notes = Validator::sanitizeInput($input['notes'] ?? '');
    $followUpDate = $input['follow_up_date'] ?? null;
    $appointmentId = $input['appointment_id'] ?? null;
    
    // Verify patient exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'patient' AND status = 'active'");
    $stmt->execute([$patientId]);
    if (!$stmt->fetch()) {
        Response::error('Patient not found');
    }
    
    $conn->beginTransaction();
    
    try {
        // Generate prescription number
        $prescriptionNumber = 'RX' . date('Ymd') . sprintf('%04d', rand(1000, 9999));
        
        // Check if prescription number already exists
        $stmt = $conn->prepare("SELECT id FROM prescriptions WHERE prescription_number = ?");
        $stmt->execute([$prescriptionNumber]);
        if ($stmt->fetch()) {
            $prescriptionNumber = 'RX' . date('Ymd') . sprintf('%04d', rand(1000, 9999));
        }
        
        // Create prescription
        $stmt = $conn->prepare("
            INSERT INTO prescriptions (doctor_id, patient_id, appointment_id, prescription_number, diagnosis, symptoms, notes, follow_up_date, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmt->execute([$user['user_id'], $patientId, $appointmentId, $prescriptionNumber, $diagnosis, $symptoms, $notes, $followUpDate]);
        $prescriptionId = $conn->lastInsertId();
        
        // Add medicines
        if (!empty($input['medicines'])) {
            foreach ($input['medicines'] as $medicine) {
                $stmt = $conn->prepare("
                    INSERT INTO prescription_medicines (prescription_id, medicine_id, medicine_name, dosage, frequency, duration, instructions, quantity) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $prescriptionId,
                    $medicine['medicine_id'],
                    Validator::sanitizeInput($medicine['medicine_name']),
                    Validator::sanitizeInput($medicine['dosage']),
                    Validator::sanitizeInput($medicine['frequency']),
                    Validator::sanitizeInput($medicine['duration']),
                    Validator::sanitizeInput($medicine['instructions']),
                    (int)($medicine['quantity'] ?? 1)
                ]);
            }
        }
        
        // Add tests
        if (!empty($input['tests'])) {
            foreach ($input['tests'] as $test) {
                $stmt = $conn->prepare("
                    INSERT INTO prescription_tests (prescription_id, test_id, test_name, instructions, urgency) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $prescriptionId,
                    $test['test_id'],
                    Validator::sanitizeInput($test['test_name']),
                    Validator::sanitizeInput($test['instructions']),
                    Validator::sanitizeInput($test['urgency'] ?? 'routine')
                ]);
            }
        }
        
        // Generate PDF
        $prescriptionData = getPrescriptionData($conn, $prescriptionId);
        $pdfData = PDFGenerator::generatePrescriptionPDF($prescriptionData);
        
        // Update prescription with PDF info
        $stmt = $conn->prepare("UPDATE prescriptions SET pdf_path = ?, pdf_filename = ? WHERE id = ?");
        $stmt->execute([$pdfData['filepath'], $pdfData['filename'], $prescriptionId]);
        
        $conn->commit();
        
        Logger::info('Prescription created', [
            'prescription_id' => $prescriptionId,
            'prescription_number' => $prescriptionNumber,
            'doctor_id' => $user['user_id'],
            'patient_id' => $patientId
        ]);
        
        Response::success([
            'prescription_id' => $prescriptionId,
            'prescription_number' => $prescriptionNumber,
            'pdf_path' => $pdfData['filepath']
        ], 'Prescription created successfully');
        
    } catch (Exception $e) {
        $conn->rollBack();
        Logger::error('Prescription creation failed', ['error' => $e->getMessage()]);
        Response::error('Prescription creation failed');
    }
}

function handleListPrescriptions($conn) {
    $user = Auth::requireAuth();
    
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $status = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    
    $offset = ($page - 1) * $limit;
    
    $whereConditions = [];
    $params = [];
    
    // Role-based filtering
    if ($user['role'] === 'doctor') {
        $whereConditions[] = "p.doctor_id = ?";
        $params[] = $user['user_id'];
    } elseif ($user['role'] === 'patient') {
        $whereConditions[] = "p.patient_id = ?";
        $params[] = $user['user_id'];
    }
    
    // Status filtering
    if ($status) {
        $whereConditions[] = "p.status = ?";
        $params[] = $status;
    }
    
    // Search filtering
    if ($search) {
        $whereConditions[] = "(p.prescription_number LIKE ? OR p.diagnosis LIKE ? OR u1.name LIKE ? OR u2.name LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get total count
    $countSql = "
        SELECT COUNT(*) as total
        FROM prescriptions p
        JOIN users u1 ON p.doctor_id = u1.id
        JOIN users u2 ON p.patient_id = u2.id
        $whereClause
    ";
    $stmt = $conn->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    
    // Get prescriptions
    $sql = "
        SELECT 
            p.id,
            p.prescription_number,
            p.diagnosis,
            p.symptoms,
            p.notes,
            p.follow_up_date,
            p.prescription_date,
            p.status,
            p.pdf_path,
            p.pdf_filename,
            u1.name as doctor_name,
            u1.email as doctor_email,
            u2.name as patient_name,
            u2.email as patient_email,
            d.specialty as doctor_specialty,
            COUNT(DISTINCT pm.id) as medicine_count,
            COUNT(DISTINCT pt.id) as test_count
        FROM prescriptions p
        JOIN users u1 ON p.doctor_id = u1.id
        JOIN users u2 ON p.patient_id = u2.id
        LEFT JOIN doctors d ON p.doctor_id = d.user_id
        LEFT JOIN prescription_medicines pm ON p.id = pm.prescription_id
        LEFT JOIN prescription_tests pt ON p.id = pt.prescription_id
        $whereClause
        GROUP BY p.id
        ORDER BY p.prescription_date DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $prescriptions = $stmt->fetchAll();
    
    Response::success([
        'prescriptions' => $prescriptions,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

function handleGetPrescription($conn) {
    $user = Auth::requireAuth();
    
    $prescriptionId = (int)($_GET['id'] ?? 0);
    if (!$prescriptionId) {
        Response::error('Prescription ID required');
    }
    
    // Get prescription with access control
    $sql = "
        SELECT 
            p.*,
            u1.name as doctor_name,
            u1.email as doctor_email,
            u2.name as patient_name,
            u2.email as patient_email,
            d.specialty as doctor_specialty,
            d.license_number
        FROM prescriptions p
        JOIN users u1 ON p.doctor_id = u1.id
        JOIN users u2 ON p.patient_id = u2.id
        LEFT JOIN doctors d ON p.doctor_id = d.user_id
        WHERE p.id = ?
    ";
    
    $params = [$prescriptionId];
    
    // Add access control
    if ($user['role'] === 'doctor') {
        $sql .= " AND p.doctor_id = ?";
        $params[] = $user['user_id'];
    } elseif ($user['role'] === 'patient') {
        $sql .= " AND p.patient_id = ?";
        $params[] = $user['user_id'];
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $prescription = $stmt->fetch();
    
    if (!$prescription) {
        Response::notFound('Prescription not found');
    }
    
    // Get medicines
    $stmt = $conn->prepare("
        SELECT pm.*, m.generic_name, m.manufacturer, m.category, m.price
        FROM prescription_medicines pm
        LEFT JOIN medicines m ON pm.medicine_id = m.id
        WHERE pm.prescription_id = ?
        ORDER BY pm.id
    ");
    $stmt->execute([$prescriptionId]);
    $prescription['medicines'] = $stmt->fetchAll();
    
    // Get tests
    $stmt = $conn->prepare("
        SELECT pt.*, t.test_code, t.category, t.price, t.duration_hours
        FROM prescription_tests pt
        LEFT JOIN pathology_tests t ON pt.test_id = t.id
        WHERE pt.prescription_id = ?
        ORDER BY pt.id
    ");
    $stmt->execute([$prescriptionId]);
    $prescription['tests'] = $stmt->fetchAll();
    
    Response::success($prescription);
}

function handleUpdatePrescription($conn) {
    $user = Auth::requireRole(['doctor']);
    
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        Response::error('Method not allowed', 405);
    }
    
    $prescriptionId = (int)($_GET['id'] ?? 0);
    if (!$prescriptionId) {
        Response::error('Prescription ID required');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Verify prescription exists and belongs to doctor
    $stmt = $conn->prepare("SELECT id FROM prescriptions WHERE id = ? AND doctor_id = ?");
    $stmt->execute([$prescriptionId, $user['user_id']]);
    if (!$stmt->fetch()) {
        Response::notFound('Prescription not found');
    }
    
    $conn->beginTransaction();
    
    try {
        // Update prescription
        $stmt = $conn->prepare("
            UPDATE prescriptions 
            SET diagnosis = ?, symptoms = ?, notes = ?, follow_up_date = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmt->execute([
            Validator::sanitizeInput($input['diagnosis']),
            Validator::sanitizeInput($input['symptoms']),
            Validator::sanitizeInput($input['notes']),
            $input['follow_up_date'],
            $prescriptionId
        ]);
        
        // Update medicines
        if (isset($input['medicines'])) {
            // Delete existing medicines
            $stmt = $conn->prepare("DELETE FROM prescription_medicines WHERE prescription_id = ?");
            $stmt->execute([$prescriptionId]);
            
            // Add new medicines
            foreach ($input['medicines'] as $medicine) {
                $stmt = $conn->prepare("
                    INSERT INTO prescription_medicines (prescription_id, medicine_id, medicine_name, dosage, frequency, duration, instructions, quantity) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $prescriptionId,
                    $medicine['medicine_id'],
                    Validator::sanitizeInput($medicine['medicine_name']),
                    Validator::sanitizeInput($medicine['dosage']),
                    Validator::sanitizeInput($medicine['frequency']),
                    Validator::sanitizeInput($medicine['duration']),
                    Validator::sanitizeInput($medicine['instructions']),
                    (int)($medicine['quantity'] ?? 1)
                ]);
            }
        }
        
        // Update tests
        if (isset($input['tests'])) {
            // Delete existing tests
            $stmt = $conn->prepare("DELETE FROM prescription_tests WHERE prescription_id = ?");
            $stmt->execute([$prescriptionId]);
            
            // Add new tests
            foreach ($input['tests'] as $test) {
                $stmt = $conn->prepare("
                    INSERT INTO prescription_tests (prescription_id, test_id, test_name, instructions, urgency) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $prescriptionId,
                    $test['test_id'],
                    Validator::sanitizeInput($test['test_name']),
                    Validator::sanitizeInput($test['instructions']),
                    Validator::sanitizeInput($test['urgency'] ?? 'routine')
                ]);
            }
        }
        
        $conn->commit();
        
        Logger::info('Prescription updated', [
            'prescription_id' => $prescriptionId,
            'doctor_id' => $user['user_id']
        ]);
        
        Response::success(null, 'Prescription updated successfully');
        
    } catch (Exception $e) {
        $conn->rollBack();
        Logger::error('Prescription update failed', ['error' => $e->getMessage()]);
        Response::error('Prescription update failed');
    }
}

function handleDeletePrescription($conn) {
    $user = Auth::requireRole(['doctor', 'admin']);
    
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        Response::error('Method not allowed', 405);
    }
    
    $prescriptionId = (int)($_GET['id'] ?? 0);
    if (!$prescriptionId) {
        Response::error('Prescription ID required');
    }
    
    // Verify prescription exists and access
    $sql = "SELECT id, pdf_path FROM prescriptions WHERE id = ?";
    $params = [$prescriptionId];
    
    if ($user['role'] === 'doctor') {
        $sql .= " AND doctor_id = ?";
        $params[] = $user['user_id'];
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $prescription = $stmt->fetch();
    
    if (!$prescription) {
        Response::notFound('Prescription not found');
    }
    
    $conn->beginTransaction();
    
    try {
        // Delete prescription medicines
        $stmt = $conn->prepare("DELETE FROM prescription_medicines WHERE prescription_id = ?");
        $stmt->execute([$prescriptionId]);
        
        // Delete prescription tests
        $stmt = $conn->prepare("DELETE FROM prescription_tests WHERE prescription_id = ?");
        $stmt->execute([$prescriptionId]);
        
        // Delete prescription
        $stmt = $conn->prepare("DELETE FROM prescriptions WHERE id = ?");
        $stmt->execute([$prescriptionId]);
        
        // Delete PDF file
        if ($prescription['pdf_path'] && file_exists($prescription['pdf_path'])) {
            FileUpload::deleteFile($prescription['pdf_path']);
        }
        
        $conn->commit();
        
        Logger::info('Prescription deleted', [
            'prescription_id' => $prescriptionId,
            'deleted_by' => $user['user_id']
        ]);
        
        Response::success(null, 'Prescription deleted successfully');
        
    } catch (Exception $e) {
        $conn->rollBack();
        Logger::error('Prescription deletion failed', ['error' => $e->getMessage()]);
        Response::error('Prescription deletion failed');
    }
}

function handlePrintPrescription($conn) {
    $user = Auth::requireAuth();
    
    $prescriptionId = (int)($_GET['id'] ?? 0);
    if (!$prescriptionId) {
        Response::error('Prescription ID required');
    }
    
    // Get prescription with access control
    $sql = "SELECT pdf_path FROM prescriptions WHERE id = ?";
    $params = [$prescriptionId];
    
    if ($user['role'] === 'doctor') {
        $sql .= " AND doctor_id = ?";
        $params[] = $user['user_id'];
    } elseif ($user['role'] === 'patient') {
        $sql .= " AND patient_id = ?";
        $params[] = $user['user_id'];
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $prescription = $stmt->fetch();
    
    if (!$prescription) {
        Response::notFound('Prescription not found');
    }
    
    if (!$prescription['pdf_path'] || !file_exists($prescription['pdf_path'])) {
        Response::error('Prescription PDF not found');
    }
    
    // Return PDF file info for download/print
    Response::success([
        'pdf_path' => $prescription['pdf_path'],
        'download_url' => '/api/download.php?file=' . urlencode($prescription['pdf_path'])
    ]);
}

function handlePrescriptionTemplates($conn) {
    $user = Auth::requireRole(['doctor']);
    
    $stmt = $conn->prepare("
        SELECT * FROM prescription_templates 
        WHERE doctor_id = ? AND is_active = TRUE 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user['user_id']]);
    $templates = $stmt->fetchAll();
    
    Response::success($templates);
}

function handleSaveTemplate($conn) {
    $user = Auth::requireRole(['doctor']);
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $required = ['template_name', 'diagnosis'];
    $missing = Validator::validateRequired($required, $input);
    
    if (!empty($missing)) {
        Response::error('Missing required fields: ' . implode(', ', $missing));
    }
    
    $templateName = Validator::sanitizeInput($input['template_name']);
    $diagnosis = Validator::sanitizeInput($input['diagnosis']);
    $symptoms = Validator::sanitizeInput($input['symptoms'] ?? '');
    $notes = Validator::sanitizeInput($input['notes'] ?? '');
    $medicines = json_encode($input['medicines'] ?? []);
    $tests = json_encode($input['tests'] ?? []);
    
    $stmt = $conn->prepare("
        INSERT INTO prescription_templates (doctor_id, template_name, diagnosis, symptoms, notes, medicines, tests) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$user['user_id'], $templateName, $diagnosis, $symptoms, $notes, $medicines, $tests]);
    
    Logger::info('Prescription template saved', [
        'template_id' => $conn->lastInsertId(),
        'doctor_id' => $user['user_id']
    ]);
    
    Response::success(['template_id' => $conn->lastInsertId()], 'Template saved successfully');
}

function handleUseTemplate($conn) {
    $user = Auth::requireRole(['doctor']);
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::error('Method not allowed', 405);
    }
    
    $templateId = (int)($_GET['template_id'] ?? 0);
    if (!$templateId) {
        Response::error('Template ID required');
    }
    
    $stmt = $conn->prepare("
        SELECT * FROM prescription_templates 
        WHERE id = ? AND doctor_id = ? AND is_active = TRUE
    ");
    $stmt->execute([$templateId, $user['user_id']]);
    $template = $stmt->fetch();
    
    if (!$template) {
        Response::notFound('Template not found');
    }
    
    Response::success([
        'diagnosis' => $template['diagnosis'],
        'symptoms' => $template['symptoms'],
        'notes' => $template['notes'],
        'medicines' => json_decode($template['medicines'], true),
        'tests' => json_decode($template['tests'], true)
    ]);
}

function getPrescriptionData($conn, $prescriptionId) {
    $stmt = $conn->prepare("
        SELECT 
            p.*,
            u1.name as doctor_name,
            u2.name as patient_name,
            d.specialty as doctor_specialty
        FROM prescriptions p
        JOIN users u1 ON p.doctor_id = u1.id
        JOIN users u2 ON p.patient_id = u2.id
        LEFT JOIN doctors d ON p.doctor_id = d.user_id
        WHERE p.id = ?
    ");
    $stmt->execute([$prescriptionId]);
    $prescription = $stmt->fetch();
    
    // Get medicines
    $stmt = $conn->prepare("
        SELECT pm.*, m.generic_name, m.manufacturer, m.category, m.price
        FROM prescription_medicines pm
        LEFT JOIN medicines m ON pm.medicine_id = m.id
        WHERE pm.prescription_id = ?
    ");
    $stmt->execute([$prescriptionId]);
    $prescription['medicines'] = $stmt->fetchAll();
    
    // Get tests
    $stmt = $conn->prepare("
        SELECT pt.*, t.test_code, t.category, t.price, t.duration_hours
        FROM prescription_tests pt
        LEFT JOIN pathology_tests t ON pt.test_id = t.id
        WHERE pt.prescription_id = ?
    ");
    $stmt->execute([$prescriptionId]);
    $prescription['tests'] = $stmt->fetchAll();
    
    return $prescription;
}
?>
