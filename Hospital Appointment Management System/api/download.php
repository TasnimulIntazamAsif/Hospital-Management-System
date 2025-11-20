<?php
// api/download.php - File download handling
require_once '../config/database.php';

try {
    $user = Auth::requireAuth();
    $filePath = $_GET['file'] ?? '';
    
    if (empty($filePath)) {
        Response::error('File path required');
    }
    
    // Security check - ensure file is within allowed directories
    $allowedPaths = [
        Config::UPLOAD_PATH,
        Config::PRESCRIPTION_PATH
    ];
    
    $isAllowed = false;
    foreach ($allowedPaths as $allowedPath) {
        if (strpos($filePath, $allowedPath) === 0) {
            $isAllowed = true;
            break;
        }
    }
    
    if (!$isAllowed) {
        Response::forbidden('Access denied');
    }
    
    if (!file_exists($filePath)) {
        Response::notFound('File not found');
    }
    
    // Get file info
    $fileInfo = pathinfo($filePath);
    $fileName = $fileInfo['basename'];
    $fileSize = filesize($filePath);
    $mimeType = mime_content_type($filePath);
    
    // Set headers for download
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . $fileSize);
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    // Log download
    Logger::info('File downloaded', [
        'user_id' => $user['user_id'],
        'file_path' => $filePath,
        'file_size' => $fileSize
    ]);
    
    // Output file
    readfile($filePath);
    exit;
    
} catch (Exception $e) {
    Logger::error('File download failed', ['error' => $e->getMessage()]);
    Response::error('File download failed');
}
?>
