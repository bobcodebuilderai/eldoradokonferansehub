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

// First hide any currently displayed question
$stmt = $db->prepare("UPDATE guest_questions SET status = 'approved' WHERE conference_id = ? AND status = 'displayed'");
$stmt->execute([$conferenceId]);

// Then display the selected question
$stmt = $db->prepare("UPDATE guest_questions SET status = 'displayed' WHERE id = ? AND conference_id = ?");
$stmt->execute([$guestQuestionId, $conferenceId]);

setFlashMessage('success', 'Spørsmål vises på skjerm');
redirect('/admin/combined-dashboard.php?conference_id=' . $conferenceId);
