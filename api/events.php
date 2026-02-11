<?php
/**
 * Server-Sent Events API for instant overlay updates
 */
require_once __DIR__ . '/../includes/functions.php';

// Release session lock to prevent blocking other requests
session_write_close();

// Disable output buffering
while (ob_get_level() > 0) {
    ob_end_flush();
}

// Set headers for SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable nginx buffering

// Get conference code
$code = trim($_GET['code'] ?? '');

if (empty($code)) {
    echo "event: error\n";
    echo "data: " . json_encode(['error' => 'No code provided']) . "\n\n";
    flush();
    exit;
}

$db = getDB();

// Get conference
$stmt = $db->prepare("SELECT id FROM conferences WHERE unique_code = ? AND is_active = 1");
$stmt->execute([$code]);
$conference = $stmt->fetch();

if (!$conference) {
    echo "event: error\n";
    echo "data: " . json_encode(['error' => 'Conference not found']) . "\n\n";
    flush();
    exit;
}

$conferenceId = $conference['id'];

// Keep track of last state
$lastActiveQuestionId = null;
$lastShowResults = false;
$lastResponseCount = 0;
$lastParticipantCount = 0;

// Send initial connection message
echo "event: connected\n";
echo "data: " . json_encode(['message' => 'Connected to event stream']) . "\n\n";
flush();

// Main loop
$maxIterations = 600; // Run for about 10 minutes (600 * 1 second)
$iteration = 0;

while ($iteration < $maxIterations) {
    $iteration++;
    
    try {
        // Reconnect to database if needed
        $db = getDB();
        
        // Get current active question
        $stmt = $db->prepare("SELECT id, show_results FROM questions WHERE conference_id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$conferenceId]);
        $activeQuestion = $stmt->fetch();
        
        $currentActiveQuestionId = $activeQuestion ? $activeQuestion['id'] : null;
        $currentShowResults = $activeQuestion ? (bool)$activeQuestion['show_results'] : false;
        
        // Get response count for active question
        $currentResponseCount = 0;
        if ($currentActiveQuestionId) {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM answers WHERE question_id = ?");
            $stmt->execute([$currentActiveQuestionId]);
            $result = $stmt->fetch();
            $currentResponseCount = $result ? (int)$result['count'] : 0;
        }
        
        // Get participant count
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM participants WHERE conference_id = ?");
        $stmt->execute([$conferenceId]);
        $result = $stmt->fetch();
        $currentParticipantCount = $result ? (int)$result['count'] : 0;
        
        // Check if anything changed
        $shouldRefresh = false;
        
        if ($lastActiveQuestionId !== $currentActiveQuestionId) {
            $shouldRefresh = true;
        }
        
        if ($lastShowResults !== $currentShowResults) {
            $shouldRefresh = true;
        }
        
        if ($lastResponseCount !== $currentResponseCount) {
            // Only refresh if responses increased significantly (every 5 responses)
            if ($currentResponseCount - $lastResponseCount >= 5) {
                $shouldRefresh = true;
            }
        }
        
        // Update last state
        $lastActiveQuestionId = $currentActiveQuestionId;
        $lastShowResults = $currentShowResults;
        $lastResponseCount = $currentResponseCount;
        $lastParticipantCount = $currentParticipantCount;
        
        // Send data
        $data = [
            'participants' => $currentParticipantCount,
            'responses' => $currentResponseCount,
            'shouldRefresh' => $shouldRefresh,
            'hasActiveQuestion' => $currentActiveQuestionId !== null,
            'showResults' => $currentShowResults
        ];
        
        echo "data: " . json_encode($data) . "\n\n";
        
        // Ensure data is sent immediately
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
        
    } catch (Exception $e) {
        echo "event: error\n";
        echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
        flush();
    }
    
    // Sleep for 1 second
    sleep(1);
}

// Send completion event
echo "event: complete\n";
echo "data: " . json_encode(['message' => 'Stream completed, please reconnect']) . "\n\n";
flush();
