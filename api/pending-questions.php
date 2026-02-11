<?php
/**
 * API endpoint for getting pending guest questions
 * Used by combined dashboard for AJAX polling
 */

require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');
header('Cache-Control: no-cache');

$conferenceId = intval($_GET['conference_id'] ?? 0);
if (!$conferenceId) {
    echo json_encode(['error' => 'Conference ID required']);
    exit;
}

$db = getDB();

// Get pending guest questions
$stmt = $db->prepare("
    SELECT gq.*, p.name as participant_name 
    FROM guest_questions gq 
    JOIN participants p ON gq.participant_id = p.id 
    WHERE gq.conference_id = ? AND gq.status = 'pending'
    ORDER BY gq.created_at ASC
");
$stmt->execute([$conferenceId]);
$pendingQuestions = $stmt->fetchAll();

// Get currently displayed question
$stmt = $db->prepare("
    SELECT gq.*, p.name as participant_name 
    FROM guest_questions gq 
    JOIN participants p ON gq.participant_id = p.id 
    WHERE gq.conference_id = ? AND gq.status = 'displayed'
    LIMIT 1
");
$stmt->execute([$conferenceId]);
$displayedQuestion = $stmt->fetch();

// Get approved questions count
$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM guest_questions 
    WHERE conference_id = ? AND status = 'approved'
");
$stmt->execute([$conferenceId]);
$approvedCount = $stmt->fetch()['count'] ?? 0;

echo json_encode([
    'pending' => $pendingQuestions,
    'pendingCount' => count($pendingQuestions),
    'displayed' => $displayedQuestion,
    'approvedCount' => $approvedCount,
    'timestamp' => time()
]);
