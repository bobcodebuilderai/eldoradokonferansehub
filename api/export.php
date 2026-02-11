<?php
/**
 * Export API for Run of Show
 * PDF and CSV generation endpoints
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/export.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$conferenceId = intval($_GET['conference_id'] ?? 0);
$format = $_GET['format'] ?? 'csv';
$dayNumber = isset($_GET['day']) ? intval($_GET['day']) : null;

if (!$conferenceId) {
    http_response_code(400);
    echo json_encode(['error' => 'Conference ID required']);
    exit;
}

if (!canAccessConference($conferenceId)) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

if ($format === 'csv') {
    $result = exportROStoExcel($conferenceId, $dayNumber, 'csv');
    
    if ($result['success']) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
        echo $result['content'];
        exit;
    }
    
    echo json_encode(['error' => $result['message']]);
    exit;
}

if ($format === 'pdf' || $format === 'print') {
    // Generate printable HTML that can be saved as PDF
    $html = generatePrintableROS($conferenceId, $dayNumber);
    
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}

if ($format === 'json') {
    $result = exportROStoPDF($conferenceId, $dayNumber);
    echo json_encode($result);
    exit;
}

echo json_encode(['error' => 'Invalid format. Use: csv, pdf, print, or json']);
