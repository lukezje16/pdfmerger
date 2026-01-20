<?php

/**
 * PDF Merge Handler
 * Merges multiple PDFs using FPDI in user-defined order
 * Includes Ghostscript fallback for compressed PDFs
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/vendor/autoload.php';

use setasign\Fpdi\Fpdi;

// Configuration
define('MERGED_DIR', __DIR__ . '/merged/');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('FILE_EXPIRY', 3600); // 1 hour
define('TEMP_DIR', __DIR__ . '/uploads/temp/');

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

    // Create directories if needed
    if (!is_dir(MERGED_DIR)) {
        mkdir(MERGED_DIR, 0755, true);
    }
    if (!is_dir(TEMP_DIR)) {
        mkdir(TEMP_DIR, 0755, true);
    }

    // Create session-based merged directory
    $sessionMergedDir = MERGED_DIR . $_SESSION['upload_id'] . '/';
    if (!is_dir($sessionMergedDir)) {
        mkdir($sessionMergedDir, 0755, true);
    }

    // Increase memory limit for large merges
    ini_set('memory_limit', '512M');
    set_time_limit(300);

    // Create new PDF using FPDI
    $pdf = new Fpdi();
    $pdf->SetAutoPageBreak(false);

    // Track temp files for cleanup
    $tempFiles = [];

    // Merge all PDFs
    foreach ($filesToMerge as $fileInfo) {
        $pdfPath = $fileInfo['path'];
        
        try {
            // Try to process the PDF directly first
            $pageCount = @$pdf->setSourceFile($pdfPath);
        } catch (Exception $e) {
            // Check if it's a compression error
            if (strpos($e->getMessage(), 'compression') !== false || 
                strpos($e->getMessage(), 'not supported') !== false ||
                strpos($e->getMessage(), 'pdf-parser') !== false) {
                
                // Try to convert with Ghostscript
                $convertedPath = convertPdfWithGhostscript($pdfPath, $fileInfo['originalName']);
                
                if ($convertedPath) {
                    $pdfPath = $convertedPath;
                    $tempFiles[] = $convertedPath;
                    $pageCount = $pdf->setSourceFile($pdfPath);
                } else {
                    throw new Exception(
                        'The file "' . $fileInfo['originalName'] . '" uses advanced PDF compression that cannot be processed. ' .
                        'Please try re-saving it as PDF 1.4 compatible or using "Print to PDF" to create a simpler version.'
                    );
                }
            } else {
                throw $e;
            }
        }

        // Import all pages from this PDF
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);

            // Add page with same orientation and size as source
            $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId, 0, 0, $size['width'], $size['height']);
        }
    }

    // Generate output filename
    $timestamp = date('Ymd_His');
    $downloadId = bin2hex(random_bytes(16));
    $outputFilename = 'merged_' . $timestamp . '.pdf';
    $outputPath = $sessionMergedDir . $downloadId . '_' . $outputFilename;

    // Save merged PDF
    $pdf->Output('F', $outputPath);

    // Clean up temp files
    foreach ($tempFiles as $tempFile) {
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    }

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

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);

/**
 * Try to convert PDF using Ghostscript to remove unsupported compression
 * Returns the path to converted file, or false if Ghostscript is not available
 */
function convertPdfWithGhostscript(string $inputPath, string $originalName): ?string
{
    // Check if Ghostscript is available
    $gsPath = findGhostscript();
    
    if (!$gsPath) {
        return null;
    }

    // Create temp output path
    $tempOutput = TEMP_DIR . uniqid('gs_') . '.pdf';

    // Build Ghostscript command to convert PDF to 1.4 compatible
    $command = sprintf(
        '%s -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dNOPAUSE -dQUIET -dBATCH ' .
        '-dPDFSETTINGS=/prepress -sOutputFile=%s %s 2>&1',
        escapeshellcmd($gsPath),
        escapeshellarg($tempOutput),
        escapeshellarg($inputPath)
    );

    // Execute Ghostscript
    exec($command, $output, $returnCode);

    // Check if conversion was successful
    if ($returnCode === 0 && file_exists($tempOutput) && filesize($tempOutput) > 0) {
        return $tempOutput;
    }

    // Clean up failed attempt
    if (file_exists($tempOutput)) {
        unlink($tempOutput);
    }

    return null;
}

/**
 * Find Ghostscript executable
 */
function findGhostscript(): ?string
{
    // Common Ghostscript paths
    $possiblePaths = [
        'gs',                           // Linux/Mac (in PATH)
        '/usr/bin/gs',                  // Linux
        '/usr/local/bin/gs',            // Linux/Mac homebrew
        '/opt/local/bin/gs',            // MacPorts
        'C:\\Program Files\\gs\\gs9.56.1\\bin\\gswin64c.exe',  // Windows
        'C:\\Program Files (x86)\\gs\\gs9.56.1\\bin\\gswin32c.exe',
    ];

    foreach ($possiblePaths as $path) {
        // Check if command exists
        if ($path === 'gs') {
            exec('which gs 2>/dev/null', $output, $returnCode);
            if ($returnCode === 0) {
                return 'gs';
            }
            // Try Windows
            exec('where gs 2>nul', $output, $returnCode);
            if ($returnCode === 0) {
                return 'gs';
            }
        } elseif (file_exists($path)) {
            return $path;
        }
    }

    return null;
}

/**
 * Clean up files older than FILE_EXPIRY
 */
function cleanupOldFiles(): void
{
    $directories = [UPLOAD_DIR, MERGED_DIR, TEMP_DIR];

    foreach ($directories as $baseDir) {
        if (!is_dir($baseDir)) {
            continue;
        }

        // Clean files directly in temp dir
        if ($baseDir === TEMP_DIR) {
            $files = glob($baseDir . '*');
            foreach ($files as $file) {
                if (is_file($file) && time() - filemtime($file) > 300) { // 5 min for temp
                    unlink($file);
                }
            }
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
