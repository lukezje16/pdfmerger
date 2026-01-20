<?php
/**
 * PDF Upload Handler
 * Handles AJAX file uploads with validation
 */

session_start();
header('Content-Type: application/json');

// Configuration
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB per file
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('ALLOWED_MIME', 'application/pdf');

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'file' => null
];

try {
    // Check if file was uploaded
    if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
        ];
        
        $errorCode = $_FILES['pdf']['error'] ?? UPLOAD_ERR_NO_FILE;
        throw new Exception($errorMessages[$errorCode] ?? 'Unknown upload error');
    }

    $file = $_FILES['pdf'];

    // Validate file size
    if ($file['size'] > MAX_FILE_SIZE) {
        throw new Exception('File size exceeds 50MB limit');
    }

    // Validate MIME type using finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    
    if ($mimeType !== ALLOWED_MIME) {
        throw new Exception('Invalid file type. Only PDF files are allowed.');
    }

    // Additional PDF header check
    $handle = fopen($file['tmp_name'], 'rb');
    $header = fread($handle, 4);
    fclose($handle);
    
    if ($header !== '%PDF') {
        throw new Exception('Invalid PDF file format');
    }

    // Create session-based upload directory
    if (!isset($_SESSION['upload_id'])) {
        $_SESSION['upload_id'] = bin2hex(random_bytes(16));
    }
    
    $sessionDir = UPLOAD_DIR . $_SESSION['upload_id'] . '/';
    
    if (!is_dir($sessionDir)) {
        if (!mkdir($sessionDir, 0755, true)) {
            throw new Exception('Failed to create upload directory');
        }
    }

    // Generate unique filename
    $fileId = bin2hex(random_bytes(8));
    $safeOriginalName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
    $safeOriginalName = substr($safeOriginalName, 0, 100); // Limit filename length
    $storedName = $fileId . '_' . $safeOriginalName;
    $targetPath = $sessionDir . $storedName;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('Failed to save uploaded file');
    }

    // Store file info in session
    if (!isset($_SESSION['files'])) {
        $_SESSION['files'] = [];
    }
    
    $_SESSION['files'][$fileId] = [
        'id' => $fileId,
        'originalName' => $file['name'],
        'storedName' => $storedName,
        'path' => $targetPath,
        'size' => $file['size'],
        'uploadedAt' => time()
    ];

    // Success response
    $response['success'] = true;
    $response['message'] = 'File uploaded successfully';
    $response['file'] = [
        'id' => $fileId,
        'name' => $file['name'],
        'size' => $file['size'],
        'sizeFormatted' => formatFileSize($file['size'])
    ];

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);

/**
 * Format file size to human readable format
 */
function formatFileSize(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    
    return round($bytes, 2) . ' ' . $units[$i];
}
