<?php
/**
 * Delete Question
 */
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$db = getDB();

$questionId = intval($_GET['id'] ?? 0);
$conferenceId = intval($_GET['conference_id'] ?? 0);

// Verify ownership through conference
$stmt = $db->prepare("
    SELECT q.id FROM questions q 
    JOIN conferences c ON q.conference_id = c.id 
    WHERE q.id = ? AND c.user_id = ?
");
$stmt->execute([$questionId, $_SESSION['user_id']]);

if ($stmt->fetch()) {
    $stmt = $db->prepare("DELETE FROM questions WHERE id = ?");
    $stmt->execute([$questionId]);
    setFlashMessage('success', __('question_deleted'));
} else {
    setFlashMessage('error', __('error'));
}

redirect('list.php?conference_id=' . $conferenceId);
