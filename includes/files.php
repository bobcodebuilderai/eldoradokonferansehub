<?php
/**
 * File Management Functions for Conference File Sharing
 */

require_once __DIR__ . '/database.php';

/**
 * Upload file for a conference
 * 
 * @param int $conferenceId Conference ID
 * @param int $userId User ID uploading the file
 * @param array $file $_FILES array element
 * @param string $description Optional description
 * @param bool $isVenueVisible Whether venue staff can see this file
 * @return array ['success' => bool, 'message' => string, 'file_id' => int|null]
 */
function uploadConferenceFile($conferenceId, $userId, $file, $description = '', $isVenueVisible = true) {
    // Validate file
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['success' => false, 'message' => 'No file uploaded.', 'file_id' => null];
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File too large (server limit).',
            UPLOAD_ERR_FORM_SIZE => 'File too large (form limit).',
            UPLOAD_ERR_PARTIAL => 'File partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file.',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension.'
        ];
        return ['success' => false, 'message' => $errorMessages[$file['error']] ?? 'Unknown upload error.', 'file_id' => null];
    }
    
    // Max file size: 50MB
    $maxSize = 50 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'File too large. Maximum size is 50MB.', 'file_id' => null];
    }
    
    // Determine file type
    $fileType = determineFileType($file['type'], $file['name']);
    
    // Create upload directory if not exists
    $uploadDir = __DIR__ . '/../../uploads/conferences/' . $conferenceId . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
    $filePath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        return ['success' => false, 'message' => 'Failed to save file.', 'file_id' => null];
    }
    
    // Save to database
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO conference_files 
        (conference_id, uploaded_by, filename, original_name, file_path, file_type, file_size, mime_type, description, is_venue_visible)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $relativePath = 'uploads/conferences/' . $conferenceId . '/' . $filename;
    
    $result = $stmt->execute([
        $conferenceId,
        $userId,
        $filename,
        $file['name'],
        $relativePath,
        $fileType,
        $file['size'],
        $file['type'],
        $description,
        $isVenueVisible ? 1 : 0
    ]);
    
    if ($result) {
        $fileId = $db->lastInsertId();
        return [
            'success' => true, 
            'message' => 'File uploaded successfully.', 
            'file_id' => $fileId,
            'file_path' => $relativePath
        ];
    }
    
    // Clean up file if database insert failed
    unlink($filePath);
    return ['success' => false, 'message' => 'Failed to save file information.', 'file_id' => null];
}

/**
 * Determine file type category
 */
function determineFileType($mimeType, $filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    // PDF
    if ($mimeType === 'application/pdf' || $extension === 'pdf') {
        return 'pdf';
    }
    
    // Images
    if (strpos($mimeType, 'image/') === 0 || in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
        return 'image';
    }
    
    // Video
    if (strpos($mimeType, 'video/') === 0 || in_array($extension, ['mp4', 'mov', 'avi', 'webm', 'mkv'])) {
        return 'video';
    }
    
    // Audio
    if (strpos($mimeType, 'audio/') === 0 || in_array($extension, ['mp3', 'wav', 'ogg', 'm4a', 'aac'])) {
        return 'audio';
    }
    
    return 'other';
}

/**
 * Get files for a conference
 * 
 * @param int $conferenceId Conference ID
 * @param bool $includeVenueFiles Whether to include files marked for venue
 * @param string|null $fileType Filter by file type (pdf, image, video, audio, other)
 * @return array Array of file records
 */
function getConferenceFiles($conferenceId, $includeVenueFiles = true, $fileType = null) {
    $db = getDB();
    
    $sql = "
        SELECT cf.*, u.username as uploaded_by_name
        FROM conference_files cf
        JOIN users u ON cf.uploaded_by = u.id
        WHERE cf.conference_id = ?
    ";
    $params = [$conferenceId];
    
    if (!$includeVenueFiles) {
        $sql .= " AND cf.is_venue_visible = 0";
    }
    
    if ($fileType) {
        $sql .= " AND cf.file_type = ?";
        $params[] = $fileType;
    }
    
    $sql .= " ORDER BY cf.uploaded_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Get a single file by ID
 * 
 * @param int $fileId File ID
 * @return array|null File record or null if not found
 */
function getFileById($fileId) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT cf.*, u.username as uploaded_by_name
        FROM conference_files cf
        JOIN users u ON cf.uploaded_by = u.id
        WHERE cf.id = ?
    ");
    $stmt->execute([$fileId]);
    return $stmt->fetch();
}

/**
 * Delete a file
 * 
 * @param int $fileId File ID
 * @param int $userId User ID attempting deletion (for permission check)
 * @return array ['success' => bool, 'message' => string]
 */
function deleteConferenceFile($fileId, $userId) {
    $db = getDB();
    
    // Get file info
    $file = getFileById($fileId);
    if (!$file) {
        return ['success' => false, 'message' => 'File not found.'];
    }
    
    // Check permissions
    $user = getCurrentUser();
    if (!$user || (!isVenueAdmin() && $file['uploaded_by'] != $userId)) {
        return ['success' => false, 'message' => 'Permission denied.'];
    }
    
    // Delete physical file
    $filePath = __DIR__ . '/../../' . $file['file_path'];
    if (file_exists($filePath)) {
        unlink($filePath);
    }
    
    // Delete from database
    $stmt = $db->prepare("DELETE FROM conference_files WHERE id = ?");
    $result = $stmt->execute([$fileId]);
    
    if ($result) {
        return ['success' => true, 'message' => 'File deleted successfully.'];
    }
    
    return ['success' => false, 'message' => 'Failed to delete file.'];
}

/**
 * Format file size for display
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $unitIndex = 0;
    
    while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
        $bytes /= 1024;
        $unitIndex++;
    }
    
    return round($bytes, 2) . ' ' . $units[$unitIndex];
}

/**
 * Get file icon based on type
 */
function getFileIcon($fileType) {
    $icons = [
        'pdf' => '📄',
        'image' => '🖼️',
        'video' => '🎬',
        'audio' => '🎵',
        'other' => '📎'
    ];
    
    return $icons[$fileType] ?? '📎';
}

/**
 * Check if file is an image
 */
function isImageFile($fileType) {
    return $fileType === 'image';
}

/**
 * Check if file can be previewed in browser
 */
function canPreviewInBrowser($fileType, $mimeType) {
    $previewable = ['pdf', 'image'];
    return in_array($fileType, $previewable);
}

// Include auth functions for permission checks
require_once __DIR__ . '/auth.php';
