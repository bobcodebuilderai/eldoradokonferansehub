<?php
/**
 * Conference State API - Session Independent
 * Returns current conference state as JSON
 * Uses conference_id from URL instead of session
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');

$conferenceId = intval($_GET['conference_id'] ?? 0);

if (!$conferenceId) {
    http_response_code(400);
    echo json_encode(['error' => 'Conference ID required']);
    exit;
}

$db = getDB();

// Get conference
$stmt = $db->prepare("SELECT id, name, language, screen_width, screen_height FROM conferences WHERE id = ? AND is_active = 1");
$stmt->execute([$conferenceId]);
$conference = $stmt->fetch();

if (!$conference) {
    http_response_code(404);
    echo json_encode(['error' => 'Conference not found']);
    exit;
}

$conferenceId = $conference['id'];

// Get current active question
$stmt = $db->prepare("SELECT id, show_results FROM questions WHERE conference_id = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$conferenceId]);
$activeQuestion = $stmt->fetch();

$activeQuestionId = $activeQuestion ? $activeQuestion['id'] : null;
$showResults = $activeQuestion ? (bool)$activeQuestion['show_results'] : false;

// Get response count for active question
$responseCount = 0;
if ($activeQuestionId) {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM answers WHERE question_id = ?");
    $stmt->execute([$activeQuestionId]);
    $result = $stmt->fetch();
    $responseCount = $result ? (int)$result['count'] : 0;
}

// Get participant count
$stmt = $db->prepare("SELECT COUNT(*) as count FROM participants WHERE conference_id = ?");
$stmt->execute([$conferenceId]);
$result = $stmt->fetch();
$participantCount = $result ? (int)$result['count'] : 0;

// Get displayed guest question (if any)
$stmt = $db->prepare("SELECT id, question_text, is_anonymous, 
    (SELECT name FROM participants WHERE id = guest_questions.participant_id) as participant_name 
    FROM guest_questions 
    WHERE conference_id = ? AND status = 'displayed' 
    LIMIT 1");
$stmt->execute([$conferenceId]);
$displayedGuestQuestion = $stmt->fetch();

// Get pending guest questions count
$stmt = $db->prepare("SELECT COUNT(*) as count FROM guest_questions 
    WHERE conference_id = ? AND status = 'pending'");
$stmt->execute([$conferenceId]);
$pendingQuestionsCount = $stmt->fetch()['count'] ?? 0;

// Build response
$state = [
    'conference_id' => $conferenceId,
    'conference_name' => $conference['name'],
    'language' => $conference['language'],
    'participants' => $participantCount,
    'responses' => $responseCount,
    'hasActiveQuestion' => $activeQuestionId !== null,
    'activeQuestionId' => $activeQuestionId,
    'showResults' => $showResults,
    'displayedGuestQuestion' => $displayedGuestQuestion,
    'pendingGuestQuestions' => $pendingQuestionsCount,
    'timestamp' => time()
];

echo json_encode($state);
