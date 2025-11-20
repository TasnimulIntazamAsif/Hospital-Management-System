<?php
// config/database.php - Complete configuration file with all utility classes
// Database Connection Class
class Database {
    private $host = 'localhost';
    private $db_name = 'hospital_management';
    private $username = 'root';
    private $password = '';
    private $conn;
    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            throw new Exception("Database connection error: " . $exception->getMessage());
        }
        
        return $this->conn;
    }
}
// Application Configuration
class Config {
    const JWT_SECRET = 'hospital_management_secret_key_2024';
    const UPLOAD_PATH = 'uploads/';
    const PRESCRIPTION_PATH = 'prescriptions/';
    const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
    const ALLOWED_IMAGE_TYPES = ['jpg', 'jpeg', 'png', 'gif'];
    const ALLOWED_DOCUMENT_TYPES = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
    
    public static function getUploadPath($type = '') {
        $path = self::UPLOAD_PATH . $type;
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }
        return $path;
    }
    
    public static function getPrescriptionPath() {
        $path = self::PRESCRIPTION_PATH;
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }
        return $path;
    }
}
// Standardized API Responses
class Response {
    public static function success($data = null, $message = 'Success', $code = 200) {
        http_response_code($code);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT);
        exit;
    }
    
    public static function error($message = 'Error', $code = 400, $data = null) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT);
        exit;
    }
    
    public static function unauthorized($message = 'Unauthorized') {
        self::error($message, 401);
    }
    
    public static function forbidden($message = 'Forbidden') {
        self::error($message, 403);
    }
    
    public static function notFound($message = 'Not Found') {
        self::error($message, 404);
    }
}
// Input Validation
class Validator {
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    
    public static function validatePassword($password) {
        return strlen($password) >= 6;
    }
    
    public static function validatePhone($phone) {
        return preg_match('/^[\+]?[0-9\s\-\(\)]{10,}$/', $phone);
    }
    
    public static function validateRequired($fields, $data) {
        $missing = [];
        foreach ($fields as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                $missing[] = $field;
            }
        }
        return $missing;
    }
    
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
}
// Authentication Utilities
class Auth {
    public static function generateToken($user_id, $role) {
        $payload = [
            'user_id' => $user_id,
            'role' => $role,
            'iat' => time(),
            'exp' => time() + (24 * 60 * 60) // 24 hours
        ];
        
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode($payload);
        
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, Config::JWT_SECRET, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }
    
    public static function verifyToken($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }
        
        $header = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[0]));
        $payload = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1]));
        $signature = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[2]));
        
        $expectedSignature = hash_hmac('sha256', $parts[0] . "." . $parts[1], Config::JWT_SECRET, true);
        
        if (!hash_equals($signature, $expectedSignature)) {
            return false;
        }
        
        $payloadData = json_decode($payload, true);
        
        if ($payloadData['exp'] < time()) {
            return false;
        }
        
        return $payloadData;
    }
    
    public static function getCurrentUser() {
        $headers = getallheaders();
        $token = null;
        
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                $token = $matches[1];
            }
        }
        
        if (!$token && isset($_GET['token'])) {
            $token = $_GET['token'];
        }
        
        if (!$token) {
            return null;
        }
        
        return self::verifyToken($token);
    }
    
    public static function requireAuth() {
        $user = self::getCurrentUser();
        if (!$user) {
            Response::unauthorized('Authentication required');
        }
        return $user;
    }
    
    public static function requireRole($allowedRoles) {
        $user = self::requireAuth();
        if (!in_array($user['role'], $allowedRoles)) {
            Response::forbidden('Insufficient permissions');
        }
        return $user;
    }
}
// File Upload Handling
class FileUpload {
    public static function uploadFile($file, $type = 'general', $allowedTypes = null) {
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload failed');
        }
        
        $fileSize = $file['size'];
        if ($fileSize > Config::MAX_FILE_SIZE) {
            throw new Exception('File size too large');
        }
        
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($allowedTypes === null) {
            $allowedTypes = array_merge(Config::ALLOWED_IMAGE_TYPES, Config::ALLOWED_DOCUMENT_TYPES);
        }
        
        if (!in_array($fileExtension, $allowedTypes)) {
            throw new Exception('File type not allowed');
        }
        
        $uploadPath = Config::getUploadPath($type);
        $fileName = uniqid() . '_' . time() . '.' . $fileExtension;
        $filePath = $uploadPath . $fileName;
        
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception('Failed to move uploaded file');
        }
        
        return [
            'filename' => $fileName,
            'filepath' => $filePath,
            'filesize' => $fileSize,
            'filetype' => $file['type'],
            'original_name' => $file['name']
        ];
    }
    
    public static function deleteFile($filePath) {
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        return false;
    }
}
// PDF Generation for Prescriptions
class PDFGenerator {
    public static function generatePrescriptionPDF($prescriptionData) {
        $html = self::generatePrescriptionHTML($prescriptionData);
        
        $filename = 'prescription_' . $prescriptionData['prescription_number'] . '.html';
        $filepath = Config::getPrescriptionPath() . $filename;
        
        file_put_contents($filepath, $html);
        
        return [
            'filename' => $filename,
            'filepath' => $filepath
        ];
    }
    
    private static function generatePrescriptionHTML($data) {
        $patientName = isset($data['patient_name']) ? $data['patient_name'] : 'N/A';
        $doctorName = isset($data['doctor_name']) ? $data['doctor_name'] : 'N/A';
        $doctorSpecialty = isset($data['doctor_specialty']) ? $data['doctor_specialty'] : '';
        $diagnosis = isset($data['diagnosis']) ? htmlspecialchars($data['diagnosis']) : '';
        $symptoms = isset($data['symptoms']) ? htmlspecialchars($data['symptoms']) : '';
        $notes = isset($data['notes']) ? htmlspecialchars($data['notes']) : '';
        
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <title>Prescription - ' . $data['prescription_number'] . '</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
                .prescription-info { margin-bottom: 20px; }
                .patient-info, .doctor-info { display: inline-block; width: 48%; vertical-align: top; }
                .medicines, .tests { margin: 20px 0; }
                table { width: 100%; border-collapse: collapse; margin: 10px 0; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>HOSPITAL PRESCRIPTION</h1>
                <h2>Prescription #' . $data['prescription_number'] . '</h2>
            </div>
            
            <div class="prescription-info">
                <div class="patient-info">
                    <h3>Patient Information</h3>
                    <p><strong>Name:</strong> ' . htmlspecialchars($patientName) . '</p>
                    <p><strong>Date:</strong> ' . (isset($data['prescription_date']) ? date('Y-m-d', strtotime($data['prescription_date'])) : date('Y-m-d')) . '</p>
                </div>
                <div class="doctor-info">
                    <h3>Doctor Information</h3>
                    <p><strong>Name:</strong> ' . htmlspecialchars($doctorName) . '</p>
                    <p><strong>Specialty:</strong> ' . htmlspecialchars($doctorSpecialty) . '</p>
                </div>
            </div>
            
            <div class="diagnosis">
                <h3>Diagnosis</h3>
                <p>' . $diagnosis . '</p>
            </div>
            
            <div class="symptoms">
                <h3>Symptoms</h3>
                <p>' . $symptoms . '</p>
            </div>';
        
        if (!empty($data['medicines'])) {
            $html .= '
            <div class="medicines">
                <h3>Medicines</h3>
                <table>
                    <tr>
                        <th>Medicine</th>
                        <th>Dosage</th>
                        <th>Frequency</th>
                        <th>Duration</th>
                        <th>Instructions</th>
                    </tr>';
            
            foreach ($data['medicines'] as $medicine) {
                $html .= '
                    <tr>
                        <td>' . htmlspecialchars($medicine['medicine_name'] ?? '') . '</td>
                        <td>' . htmlspecialchars($medicine['dosage'] ?? '') . '</td>
                        <td>' . htmlspecialchars($medicine['frequency'] ?? '') . '</td>
                        <td>' . htmlspecialchars($medicine['duration'] ?? '') . '</td>
                        <td>' . htmlspecialchars($medicine['instructions'] ?? '') . '</td>
                    </tr>';
            }
            
            $html .= '</table></div>';
        }
        
        if (!empty($data['tests'])) {
            $html .= '
            <div class="tests">
                <h3>Pathology Tests</h3>
                <table>
                    <tr>
                        <th>Test Name</th>
                        <th>Urgency</th>
                        <th>Instructions</th>
                    </tr>';
            
            foreach ($data['tests'] as $test) {
                $html .= '
                    <tr>
                        <td>' . htmlspecialchars($test['test_name'] ?? '') . '</td>
                        <td>' . htmlspecialchars($test['urgency'] ?? '') . '</td>
                        <td>' . htmlspecialchars($test['instructions'] ?? '') . '</td>
                    </tr>';
            }
            
            $html .= '</table></div>';
        }
        
        if (!empty($notes)) {
            $html .= '
            <div class="notes">
                <h3>Additional Notes</h3>
                <p>' . $notes . '</p>
            </div>';
        }
        
        if (!empty($data['follow_up_date'])) {
            $html .= '
            <div class="follow-up">
                <h3>Follow-up</h3>
                <p><strong>Follow-up Date:</strong> ' . date('Y-m-d', strtotime($data['follow_up_date'])) . '</p>
            </div>';
        }
        
        $html .= '
            <div class="footer">
                <p>This prescription is valid for 30 days from the date of issue.</p>
                <p>Generated on ' . date('Y-m-d H:i:s') . '</p>
            </div>
        </body>
        </html>';
        
        return $html;
    }
}
// Logging Utility
class Logger {
    public static function log($level, $message, $context = []) {
        $logFile = 'logs/app.log';
        $logDir = dirname($logFile);
        
        if (!file_exists($logDir)) {
            mkdir($logDir, 0777, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logEntry = "[$timestamp] [$level] $message$contextStr" . PHP_EOL;
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    public static function info($message, $context = []) {
        self::log('INFO', $message, $context);
    }
    
    public static function error($message, $context = []) {
        self::log('ERROR', $message, $context);
    }
    
    public static function warning($message, $context = []) {
        self::log('WARNING', $message, $context);
    }
}
?>
