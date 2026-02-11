<?php
/**
 * Delete Conference
 */
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$db = getDB();

$conferenceId = intval($_GET['id'] ?? 0);

// Verify ownership and delete
$stmt = $db->prepare("DELETE FROM conferences WHERE id = ? AND user_id = ?");
if ($stmt->execute([$conferenceId, $_SESSION['user_id']])) {
    setFlashMessage('success', __('conference_deleted'));
} else {
    setFlashMessage('error', __('error'));
}

redirect('../dashboard.php');
