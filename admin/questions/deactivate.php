<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/dashboard.php');
}

$conferenceId = intval($_POST['conference_id'] ?? 0);
$questionId = intval($_POST['question_id'] ?? 0);

if (!$conferenceId || !$questionId) {
    setFlashMessage('error', 'Missing parameters');
    redirect('/admin/combined-dashboard.php?conference_id=' . $conferenceId);
}

$db = getDB();

$stmt = $db->prepare("UPDATE questions SET is_active = 0, show_results = 0 WHERE id = ? AND conference_id = ?");
$stmt->execute([$questionId, $conferenceId]);

setFlashMessage('success', 'Spørsmål stoppet');
redirect('/admin/combined-dashboard.php?conference_id=' . $conferenceId);
