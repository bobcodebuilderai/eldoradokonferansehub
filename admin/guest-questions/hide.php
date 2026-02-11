<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/dashboard.php');
}

$conferenceId = intval($_POST['conference_id'] ?? 0);
$guestQuestionId = intval($_POST['guest_question_id'] ?? 0);

if (!$conferenceId || !$guestQuestionId) {
    setFlashMessage('error', 'Missing parameters');
    redirect('/admin/combined-dashboard.php?conference_id=' . $conferenceId);
}

$db = getDB();

$stmt = $db->prepare("UPDATE guest_questions SET status = 'approved' WHERE id = ? AND conference_id = ?");
$stmt->execute([$guestQuestionId, $conferenceId]);

setFlashMessage('success', 'Spørsmål fjernet fra skjerm');
redirect('/admin/combined-dashboard.php?conference_id=' . $conferenceId);
