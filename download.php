<?php
/**
 * Secure Download Handler
 * Serves merged PDF files securely
 */

session_start();

// Get download ID from query string
$downloadId = $_GET['id'] ?? null;

if (!$downloadId) {
    http_response_code(400);
    die('Missing download ID');
}

// Sanitize download ID (should be hex string)
if (!preg_match('/^[a-f0-9]{32}$/', $downloadId)) {
    http_response_code(400);
    die('Invalid download ID');
}

// Check session for download info
if (!isset($_SESSION['merged'][$downloadId])) {
    http_response_code(404);
    die('Download not found or expired');
}

$fileInfo = $_SESSION['merged'][$downloadId];

// Verify file exists
if (!file_exists($fileInfo['path'])) {
    http_response_code(404);
    unset($_SESSION['merged'][$downloadId]);
    die('File not found');
}

// Check if file has expired (1 hour)
if (time() - $fileInfo['createdAt'] > 3600) {
    if (file_exists($fileInfo['path'])) {
        unlink($fileInfo['path']);
    }
    unset($_SESSION['merged'][$downloadId]);
    http_response_code(410);
    die('Download has expired');
}

// Get file size
$fileSize = filesize($fileInfo['path']);

// Set headers for download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $fileInfo['filename'] . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('Expires: 0');

// Clear output buffer
if (ob_get_level()) {
    ob_end_clean();
}

// Stream the file
readfile($fileInfo['path']);
exit;
