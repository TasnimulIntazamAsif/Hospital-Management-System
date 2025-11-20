<?php
// api/upload.php - File upload handling
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

try {
    $user = Auth::requireAuth();
    $uploadType = $_GET['type'] ?? 'general';
    
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        Response::error('No file uploaded or upload error');
    }
    
    $file = $_FILES['file'];
    $allowedTypes = [];
    
    // Set allowed file types based on upload type
    switch ($uploadType) {
        case 'photo':
            $allowedTypes = Config::ALLOWED_IMAGE_TYPES;
            break;
        case 'document':
        case 'certificate':
        case 'passport':
            $allowedTypes = Config::ALLOWED_DOCUMENT_TYPES;
            break;
        case 'prescription':
            $allowedTypes = ['pdf', 'html'];
            break;
        default:
            $allowedTypes = array_merge(Config::ALLOWED_IMAGE_TYPES, Config::ALLOWED_DOCUMENT_TYPES);
    }
    
    $uploadData = FileUpload::uploadFile($file, $uploadType, $allowedTypes);
    
    // Log upload
    Logger::info('File uploaded', [
        'user_id' => $user['user_id'],
        'upload_type' => $uploadType,
        'filename' => $uploadData['filename'],
        'file_size' => $uploadData['filesize']
    ]);
    
    Response::success([
        'filename' => $uploadData['filename'],
        'filepath' => $uploadData['filepath'],
        'filesize' => $uploadData['filesize'],
        'filetype' => $uploadData['filetype'],
        'original_name' => $uploadData['original_name']
    ], 'File uploaded successfully');
    
} catch (Exception $e) {
    Logger::error('File upload failed', ['error' => $e->getMessage()]);
    Response::error('File upload failed: ' . $e->getMessage());
}
?>
