<?php
/**
 * Active Question API - Returns current active question with answers
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

// Get active question
$stmt = $db->prepare("SELECT * FROM questions WHERE conference_id = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$conferenceId]);
$question = $stmt->fetch();

if (!$question) {
    echo json_encode(['hasQuestion' => false]);
    exit;
}

// Get answers if showing results
$answers = [];
if ($question['show_results']) {
    $stmt = $db->prepare("SELECT answer_text, COUNT(*) as count FROM answers WHERE question_id = ? GROUP BY answer_text ORDER BY count DESC");
    $stmt->execute([$question['id']]);
    $answers = $stmt->fetchAll();
}

// Get total response count
$stmt = $db->prepare("SELECT COUNT(*) as count FROM answers WHERE question_id = ?");
$stmt->execute([$question['id']]);
$responseCount = $stmt->fetch()['count'] ?? 0;

echo json_encode([
    'hasQuestion' => true,
    'question' => $question,
    'answers' => $answers,
    'responseCount' => $responseCount
]);
