<?php
/**
 * Server-Sent Events endpoint for live display updates
 * Pushes updates immediately when state changes
 */

require_once __DIR__ . '/../config/database.php';

// Disable output buffering
while (ob_get_level() > 0) {
    ob_end_clean();
}

// Set SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable Nginx buffering if using Nginx

$conferenceUuid = trim($_GET['conference'] ?? '');

if (!$conferenceUuid) {
    // Legacy support for conference_id
    $conferenceId = intval($_GET['conference_id'] ?? 0);
    if (!$conferenceId) {
        echo "event: error\n";
        echo "data: " . json_encode(['error' => 'Conference UUID required']) . "\n\n";
        exit;
    }
} else {
    // Lookup by UUID
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM conferences WHERE uuid = ? AND is_active = 1");
    $stmt->execute([$conferenceUuid]);
    $result = $stmt->fetch();
    if (!$result) {
        echo "event: error\n";
        echo "data: " . json_encode(['error' => 'Conference not found']) . "\n\n";
        exit;
    }
    $conferenceId = $result['id'];
}

$db = getDB();

// Get last known state
$lastState = null;
$lastCheck = time();

// Send initial connection confirmation
echo "event: connected\n";
echo "data: " . json_encode(['message' => 'SSE connected', 'conference_id' => $conferenceId]) . "\n\n";
flush();

// Main loop - check for state changes every 100ms
while (true) {
    // Check if client disconnected
    if (connection_aborted()) {
        break;
    }
    
    // Get current state
    $currentState = getCurrentState($db, $conferenceId);
    
    // If state changed, send update
    if ($lastState === null || stateChanged($lastState, $currentState)) {
        echo "event: update\n";
        echo "data: " . json_encode($currentState) . "\n\n";
        flush();
        $lastState = $currentState;
    }
    
    // Send keepalive every 15 seconds to prevent timeout
    if (time() - $lastCheck > 15) {
        echo "event: ping\n";
        echo "data: " . json_encode(['time' => time()]) . "\n\n";
        flush();
        $lastCheck = time();
    }
    
    // Small sleep to prevent CPU spinning (100ms = 10 checks/second)
    usleep(100000);
}

function getCurrentState($db, $conferenceId) {
    // Check conference exists
    $stmt = $db->prepare("SELECT id, name, language FROM conferences WHERE id = ? AND is_active = 1");
    $stmt->execute([$conferenceId]);
    $conference = $stmt->fetch();
    
    if (!$conference) {
        return ['error' => 'Conference not found'];
    }
    
    // Get active question
    $stmt = $db->prepare("SELECT id, question_text, question_type, show_results, chart_type FROM questions WHERE conference_id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$conferenceId]);
    $activeQuestion = $stmt->fetch();
    
    $activeQuestionId = $activeQuestion ? $activeQuestion['id'] : null;
    $showResults = $activeQuestion ? (bool)$activeQuestion['show_results'] : false;
    
    // Get response count
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
    
    // Get displayed guest question
    $stmt = $db->prepare("SELECT id, question_text, is_anonymous, 
        (SELECT name FROM participants WHERE id = guest_questions.participant_id) as participant_name 
        FROM guest_questions 
        WHERE conference_id = ? AND status = 'displayed' 
        LIMIT 1");
    $stmt->execute([$conferenceId]);
    $displayedGuestQuestion = $stmt->fetch();
    
    // Get response data for chart (if showing results)
    $responseData = [];
    if ($activeQuestionId && $showResults) {
        if ($activeQuestion['question_type'] === 'wordcloud') {
            // Get all answer texts for wordcloud
            $stmt = $db->prepare("SELECT answer_text FROM answers WHERE question_id = ?");
            $stmt->execute([$activeQuestionId]);
            $responseData = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            // Get aggregated data for bar/pie charts
            $stmt = $db->prepare("
                SELECT answer_text, COUNT(*) as count 
                FROM answers 
                WHERE question_id = ? 
                GROUP BY answer_text 
                ORDER BY count DESC
            ");
            $stmt->execute([$activeQuestionId]);
            $responseData = $stmt->fetchAll();
        }
    }
    
    return [
        'conference_id' => $conferenceId,
        'participants' => $participantCount,
        'responses' => $responseCount,
        'hasActiveQuestion' => $activeQuestionId !== null,
        'activeQuestionId' => $activeQuestionId,
        'showResults' => $showResults,
        'question' => $activeQuestion,
        'displayedGuestQuestion' => $displayedGuestQuestion,
        'responseData' => $responseData,
        'responseCount' => count($responseData),
        'timestamp' => time()
    ];
}

function stateChanged($old, $new) {
    // Compare key fields that should trigger updates
    $keys = ['participants', 'responses', 'activeQuestionId', 'showResults'];
    foreach ($keys as $key) {
        if (($old[$key] ?? null) !== ($new[$key] ?? null)) {
            return true;
        }
    }
    
    // Check guest question change
    $oldGuestId = $old['displayedGuestQuestion']['id'] ?? null;
    $newGuestId = $new['displayedGuestQuestion']['id'] ?? null;
    if ($oldGuestId !== $newGuestId) {
        return true;
    }
    
    // Check if response data changed (for live chart updates)
    if (($old['responseCount'] ?? null) !== ($new['responseCount'] ?? null)) {
        return true;
    }
    
    return false;
}
