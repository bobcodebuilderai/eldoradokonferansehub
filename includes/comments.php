<?php
/**
 * Run of Show Comments Functions
 * Venue team and presenters can add comments to blocks
 */

require_once __DIR__ . '/database.php';

/**
 * Add a comment to a block
 */
function addBlockComment($blockId, $userId, $comment, $commentType = 'general') {
    $db = getDB();
    
    $stmt = $db->prepare("
        INSERT INTO ros_comments (block_id, user_id, comment, comment_type, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    
    $result = $stmt->execute([$blockId, $userId, $comment, $commentType]);
    
    if ($result) {
        return [
            'success' => true,
            'id' => $db->lastInsertId(),
            'message' => 'Comment added'
        ];
    }
    
    return ['success' => false, 'message' => 'Failed to add comment'];
}

/**
 * Get comments for a block
 */
function getBlockComments($blockId, $type = null) {
    $db = getDB();
    
    $sql = "
        SELECT rc.*, u.username as author_name, u.is_venue_admin as is_venue_team
        FROM ros_comments rc
        JOIN users u ON rc.user_id = u.id
        WHERE rc.block_id = ?
    ";
    $params = [$blockId];
    
    if ($type) {
        $sql .= " AND rc.comment_type = ?";
        $params[] = $type;
    }
    
    $sql .= " ORDER BY rc.created_at ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Delete a comment
 */
function deleteBlockComment($commentId, $userId) {
    $db = getDB();
    
    // Check if user is comment author or admin
    $stmt = $db->prepare("
        SELECT rc.user_id, u.is_venue_admin 
        FROM ros_comments rc
        JOIN users u ON u.id = ?
        WHERE rc.id = ?
    ");
    $stmt->execute([$userId, $commentId]);
    $result = $stmt->fetch();
    
    if (!$result) {
        return ['success' => false, 'message' => 'Comment not found'];
    }
    
    if ($result['user_id'] != $userId && !$result['is_venue_admin']) {
        return ['success' => false, 'message' => 'Permission denied'];
    }
    
    $stmt = $db->prepare("DELETE FROM ros_comments WHERE id = ?");
    return ['success' => $stmt->execute([$commentId])];
}

/**
 * Get unread comments count for a conference
 */
function getUnreadCommentsCount($conferenceId, $userId) {
    $db = getDB();
    
    $stmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM ros_comments rc
        JOIN run_of_show_blocks ros ON rc.block_id = ros.id
        WHERE ros.conference_id = ?
        AND rc.user_id != ?
        AND rc.id NOT IN (
            SELECT comment_id FROM ros_comment_reads WHERE user_id = ?
        )
    ");
    $stmt->execute([$conferenceId, $userId, $userId]);
    $result = $stmt->fetch();
    
    return $result['count'] ?? 0;
}

/**
 * Mark comments as read
 */
function markCommentsAsRead($blockId, $userId) {
    $db = getDB();
    
    // Get all comments for this block
    $stmt = $db->prepare("SELECT id FROM ros_comments WHERE block_id = ?");
    $stmt->execute([$blockId]);
    $comments = $stmt->fetchAll();
    
    // Insert read records
    $stmt = $db->prepare("
        INSERT IGNORE INTO ros_comment_reads (comment_id, user_id, read_at)
        VALUES (?, ?, NOW())
    ");
    
    foreach ($comments as $comment) {
        $stmt->execute([$comment['id'], $userId]);
    }
    
    return ['success' => true];
}

/**
 * Get recent comments across all blocks in a conference
 */
function getRecentComments($conferenceId, $limit = 10) {
    $db = getDB();
    
    $stmt = $db->prepare("
        SELECT rc.*, u.username as author_name, ros.title as block_title, ros.id as block_id
        FROM ros_comments rc
        JOIN users u ON rc.user_id = u.id
        JOIN run_of_show_blocks ros ON rc.block_id = ros.id
        WHERE ros.conference_id = ?
        ORDER BY rc.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$conferenceId, $limit]);
    return $stmt->fetchAll();
}

/**
 * Format comment for display
 */
function formatComment($comment) {
    $badge = '';
    if ($comment['is_venue_team']) {
        $badge = '<span class="bg-blue-100 text-blue-700 text-xs px-2 py-1 rounded ml-2">Venue Team</span>';
    }
    
    $typeLabel = [
        'general' => '',
        'technical' => '<span class="bg-purple-100 text-purple-700 text-xs px-2 py-1 rounded ml-2">Technical</span>',
        'urgent' => '<span class="bg-red-100 text-red-700 text-xs px-2 py-1 rounded ml-2">Urgent</span>',
        'presenter' => '<span class="bg-green-100 text-green-700 text-xs px-2 py-1 rounded ml-2">Presenter</span>'
    ][$comment['comment_type']] ?? '';
    
    return [
        'id' => $comment['id'],
        'text' => nl2br(htmlspecialchars($comment['comment'])),
        'author' => htmlspecialchars($comment['author_name']) . $badge,
        'type_badge' => $typeLabel,
        'time' => date('M j, H:i', strtotime($comment['created_at'])),
        'is_venue_team' => $comment['is_venue_team']
    ];
}
