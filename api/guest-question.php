<?php
/**
 * Guest Question API - Returns currently displayed guest question
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$conferenceId = intval($_GET['conference_id'] ?? 0);
if (!$conferenceId) {
    http_response_code(400);
    echo json_encode(['error' => 'No conference ID']);
    exit;
}

$db = getDB();

$stmt = $db->prepare("SELECT * FROM guest_questions WHERE conference_id = ? AND status = 'displayed' LIMIT 1");
$stmt->execute([$conferenceId]);
$question = $stmt->fetch();

if ($question) {
    // Get participant name
    $stmt = $db->prepare("SELECT name FROM participants WHERE id = ?");
    $stmt->execute([$question['participant_id']]);
    $participant = $stmt->fetch();
    $question['participant_name'] = $participant ? $participant['name'] : 'Anonymous';
}

echo json_encode([
    'hasQuestion' => !!$question,
    'question' => $question
]);
