<?php

/**
 * PDF Merge Handler
 * Merges multiple PDFs using FPDI in user-defined order
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/vendor/autoload.php';

use setasign\Fpdi\Fpdi;

// Configuration
define('MERGED_DIR', __DIR__ . '/merged/');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('FILE_EXPIRY', 3600); // 1 hour

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'downloadId' => null,
    'filename' => null
];

try {
    // Clean up old files first
    cleanupOldFiles();

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['fileIds']) || !is_array($input['fileIds']) || empty($input['fileIds'])) {
        throw new Exception('No files specified for merging');
    }

    $fileIds = $input['fileIds'];

    // Validate session
    if (!isset($_SESSION['files']) || !isset($_SESSION['upload_id'])) {
        throw new Exception('Session expired. Please upload your files again.');
    }

    // Validate all file IDs exist
    $filesToMerge = [];
    foreach ($fileIds as $fileId) {
        if (!isset($_SESSION['files'][$fileId])) {
            throw new Exception('One or more files not found. Please re-upload.');
        }

        $fileInfo = $_SESSION['files'][$fileId];

        if (!file_exists($fileInfo['path'])) {
            throw new Exception('File "' . $fileInfo['originalName'] . '" not found on server.');
        }

        $filesToMerge[] = $fileInfo;
    }

    // Create merged directory if needed
    if (!is_dir(MERGED_DIR)) {
        if (!mkdir(MERGED_DIR, 0755, true)) {
            throw new Exception('Failed to create output directory');
        }
    }

    // Create session-based merged directory
    $sessionMergedDir = MERGED_DIR . $_SESSION['upload_id'] . '/';
    if (!is_dir($sessionMergedDir)) {
        if (!mkdir($sessionMergedDir, 0755, true)) {
            throw new Exception('Failed to create session output directory');
        }
    }

    // Increase memory limit for large merges
    ini_set('memory_limit', '512M');
    set_time_limit(300);

    // Create new PDF using FPDI
    $pdf = new Fpdi();
    $pdf->SetAutoPageBreak(false);

    // Merge all PDFs
    foreach ($filesToMerge as $fileInfo) {
        try {
            $pageCount = $pdf->setSourceFile($fileInfo['path']);

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);

                // Add page with same orientation and size as source
                $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
                $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId, 0, 0, $size['width'], $size['height']);
            }
        } catch (Exception $e) {
            throw new Exception('Error processing "' . $fileInfo['originalName'] . '": ' . $e->getMessage());
        }
    }

    // Generate output filename
    $timestamp = date('Ymd_His');
    $downloadId = bin2hex(random_bytes(16));
    $outputFilename = 'merged_' . $timestamp . '.pdf';
    $outputPath = $sessionMergedDir . $downloadId . '_' . $outputFilename;

    // Save merged PDF
    $pdf->Output('F', $outputPath);

    // Store download info in session
    if (!isset($_SESSION['merged'])) {
        $_SESSION['merged'] = [];
    }

    $_SESSION['merged'][$downloadId] = [
        'id' => $downloadId,
        'filename' => $outputFilename,
        'path' => $outputPath,
        'createdAt' => time(),
        'fileCount' => count($filesToMerge)
    ];

    // Clean up source files
    foreach ($filesToMerge as $fileInfo) {
        if (file_exists($fileInfo['path'])) {
            unlink($fileInfo['path']);
        }
        unset($_SESSION['files'][$fileInfo['id']]);
    }

    // Success response
    $response['success'] = true;
    $response['message'] = 'PDFs merged successfully';
    $response['downloadId'] = $downloadId;
    $response['filename'] = $outputFilename;
    $response['pageCount'] = $pdf->setSourceFile($outputPath);
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);

/**
 * Clean up files older than FILE_EXPIRY
 */
function cleanupOldFiles(): void
{
    $directories = [UPLOAD_DIR, MERGED_DIR];

    foreach ($directories as $baseDir) {
        if (!is_dir($baseDir)) {
            continue;
        }

        $sessionDirs = glob($baseDir . '*', GLOB_ONLYDIR);

        foreach ($sessionDirs as $sessionDir) {
            $files = glob($sessionDir . '/*');
            $allOld = true;

            foreach ($files as $file) {
                if (is_file($file)) {
                    if (time() - filemtime($file) > FILE_EXPIRY) {
                        unlink($file);
                    } else {
                        $allOld = false;
                    }
                }
            }

            // Remove empty directories
            if ($allOld && is_dir($sessionDir)) {
                $remaining = glob($sessionDir . '/*');
                if (empty($remaining)) {
                    rmdir($sessionDir);
                }
            }
        }
    }
}
