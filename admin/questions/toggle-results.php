<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/dashboard.php');
}

$conferenceId = intval($_POST['conference_id'] ?? 0);
$questionId = intval($_POST['question_id'] ?? 0);
$showResults = intval($_POST['show_results'] ?? 0);

if (!$conferenceId || !$questionId) {
    setFlashMessage('error', 'Missing parameters');
    redirect('/admin/combined-dashboard.php?conference_id=' . $conferenceId);
}

$db = getDB();

$stmt = $db->prepare("UPDATE questions SET show_results = ? WHERE id = ? AND conference_id = ?");
$stmt->execute([$showResults, $questionId, $conferenceId]);

$message = $showResults ? 'Resultater vises' : 'Resultater skjult';
setFlashMessage('success', $message);
redirect('/admin/combined-dashboard.php?conference_id=' . $conferenceId);
