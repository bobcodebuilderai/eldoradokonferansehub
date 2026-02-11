<?php
/**
 * API endpoint for stats
 */
require_once __DIR__ . '/../includes/functions.php';

// Release session lock to prevent blocking other requests
session_write_close();

header('Content-Type: application/json');

$code = trim($_GET['code'] ?? '');

if (empty($code)) {
    echo json_encode(['error' => 'No code provided']);
    exit;
}

$db = getDB();

// Get conference
$stmt = $db->prepare("SELECT id FROM conferences WHERE unique_code = ? AND is_active = 1");
$stmt->execute([$code]);
$conference = $stmt->fetch();

if (!$conference) {
    echo json_encode(['error' => 'Conference not found']);
    exit;
}

// Get participant count
$stmt = $db->prepare("SELECT COUNT(*) as count FROM participants WHERE conference_id = ?");
$stmt->execute([$conference['id']]);
$participantCount = $stmt->fetch()['count'];

echo json_encode([
    'participants' => $participantCount
]);
