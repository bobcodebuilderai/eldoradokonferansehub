<?php
/**
 * Run of Show (Kjøreplan) Functions
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';

/**
 * Create a new run of show block
 */
function createROSBlock($data) {
    $db = getDB();
    
    $stmt = $db->prepare("
        INSERT INTO run_of_show_blocks 
        (conference_id, title, description, block_type, start_time, duration_minutes, 
         day_number, location, responsible_person, tech_requirements, color_code, 
         display_order, presenter_notes, attachment_file_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $result = $stmt->execute([
        $data['conference_id'],
        $data['title'],
        $data['description'] ?? '',
        $data['block_type'] ?? 'presentation',
        $data['start_time'],
        $data['duration_minutes'],
        $data['day_number'] ?? 1,
        $data['location'] ?? '',
        $data['responsible_person'] ?? '',
        json_encode($data['tech_requirements'] ?? []),
        $data['color_code'] ?? '#3b82f6',
        $data['display_order'] ?? 0,
        $data['presenter_notes'] ?? '',
        $data['attachment_file_id'] ?? null
    ]);
    
    if ($result) {
        return ['success' => true, 'id' => $db->lastInsertId()];
    }
    
    return ['success' => false, 'message' => 'Failed to create block.'];
}

/**
 * Update a run of show block
 */
function updateROSBlock($blockId, $data) {
    $db = getDB();
    
    $allowedFields = [
        'title', 'description', 'block_type', 'start_time', 'duration_minutes',
        'day_number', 'location', 'responsible_person', 'tech_requirements',
        'color_code', 'display_order', 'status', 'presenter_notes', 
        'venue_notes', 'attachment_file_id', 'actual_start_time', 'actual_end_time'
    ];
    
    $updates = [];
    $values = [];
    
    foreach ($data as $key => $value) {
        if (in_array($key, $allowedFields)) {
            $updates[] = "$key = ?";
            if ($key === 'tech_requirements') {
                $values[] = json_encode($value);
            } else {
                $values[] = $value;
            }
        }
    }
    
    if (empty($updates)) {
        return ['success' => false, 'message' => 'No valid fields to update.'];
    }
    
    $values[] = $blockId;
    
    $sql = "UPDATE run_of_show_blocks SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    
    return ['success' => $stmt->execute($values)];
}

/**
 * Delete a run of show block
 */
function deleteROSBlock($blockId) {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM run_of_show_blocks WHERE id = ?");
    return ['success' => $stmt->execute([$blockId])];
}

/**
 * Get all blocks for a conference
 */
function getROSBlocks($conferenceId, $dayNumber = null) {
    $db = getDB();
    
    $sql = "
        SELECT ros.*, cf.original_name as attachment_name
        FROM run_of_show_blocks ros
        LEFT JOIN conference_files cf ON ros.attachment_file_id = cf.id
        WHERE ros.conference_id = ?
    ";
    $params = [$conferenceId];
    
    if ($dayNumber) {
        $sql .= " AND ros.day_number = ?";
        $params[] = $dayNumber;
    }
    
    $sql .= " ORDER BY ros.day_number ASC, ros.display_order ASC, ros.start_time ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $blocks = $stmt->fetchAll();
    
    // Decode JSON fields
    foreach ($blocks as &$block) {
        $block['tech_requirements'] = json_decode($block['tech_requirements'] ?? '{}', true);
    }
    
    return $blocks;
}

/**
 * Get a single block by ID
 */
function getROSBlock($blockId) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT ros.*, cf.original_name as attachment_name
        FROM run_of_show_blocks ros
        LEFT JOIN conference_files cf ON ros.attachment_file_id = cf.id
        WHERE ros.id = ?
    ");
    $stmt->execute([$blockId]);
    $block = $stmt->fetch();
    
    if ($block) {
        $block['tech_requirements'] = json_decode($block['tech_requirements'] ?? '{}', true);
    }
    
    return $block;
}

/**
 * Reorder blocks (drag and drop)
 */
function reorderROSBlocks($conferenceId, $dayNumber, $blockOrder) {
    $db = getDB();
    
    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare("
            UPDATE run_of_show_blocks 
            SET display_order = ? 
            WHERE id = ? AND conference_id = ? AND day_number = ?
        ");
        
        foreach ($blockOrder as $index => $blockId) {
            $stmt->execute([$index, $blockId, $conferenceId, $dayNumber]);
        }
        
        $db->commit();
        return ['success' => true];
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Check for time conflicts
 */
function checkTimeConflicts($conferenceId, $dayNumber, $startTime, $durationMinutes, $excludeBlockId = null) {
    $blocks = getROSBlocks($conferenceId, $dayNumber);
    
    $newStart = strtotime($startTime);
    $newEnd = $newStart + ($durationMinutes * 60);
    
    $conflicts = [];
    
    foreach ($blocks as $block) {
        if ($excludeBlockId && $block['id'] == $excludeBlockId) {
            continue;
        }
        
        $blockStart = strtotime($block['start_time']);
        $blockEnd = strtotime($block['end_time']);
        
        // Check for overlap
        if ($newStart < $blockEnd && $newEnd > $blockStart) {
            $conflicts[] = $block;
        }
    }
    
    return $conflicts;
}

/**
 * Calculate total duration for a day
 */
function calculateDayDuration($conferenceId, $dayNumber) {
    $blocks = getROSBlocks($conferenceId, $dayNumber);
    
    $totalMinutes = 0;
    foreach ($blocks as $block) {
        $totalMinutes += $block['duration_minutes'];
    }
    
    $hours = floor($totalMinutes / 60);
    $minutes = $totalMinutes % 60;
    
    return [
        'total_minutes' => $totalMinutes,
        'formatted' => sprintf('%d:%02d', $hours, $minutes)
    ];
}

/**
 * Set block status (for live management)
 */
function setBlockStatus($blockId, $status) {
    $data = ['status' => $status];
    
    if ($status === 'active') {
        $data['actual_start_time'] = date('Y-m-d H:i:s');
    } elseif (in_array($status, ['completed', 'skipped'])) {
        $data['actual_end_time'] = date('Y-m-d H:i:s');
    }
    
    return updateROSBlock($blockId, $data);
}

/**
 * Get active block for a conference
 */
function getActiveBlock($conferenceId) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT ros.*, cf.original_name as attachment_name
        FROM run_of_show_blocks ros
        LEFT JOIN conference_files cf ON ros.attachment_file_id = cf.id
        WHERE ros.conference_id = ? AND ros.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$conferenceId]);
    $block = $stmt->fetch();
    
    if ($block) {
        $block['tech_requirements'] = json_decode($block['tech_requirements'] ?? '{}', true);
    }
    
    return $block;
}

/**
 * Get upcoming blocks
 */
function getUpcomingBlocks($conferenceId, $limit = 3) {
    $db = getDB();
    
    $stmt = $db->prepare("
        SELECT ros.*, cf.original_name as attachment_name
        FROM run_of_show_blocks ros
        LEFT JOIN conference_files cf ON ros.attachment_file_id = cf.id
        WHERE ros.conference_id = ? 
        AND ros.status IN ('pending', 'active')
        ORDER BY 
            CASE ros.status
                WHEN 'active' THEN 0
                WHEN 'pending' THEN 1
            END,
            ros.day_number ASC,
            ros.display_order ASC,
            ros.start_time ASC
        LIMIT ?
    ");
    $stmt->execute([$conferenceId, $limit]);
    $blocks = $stmt->fetchAll();
    
    foreach ($blocks as &$block) {
        $block['tech_requirements'] = json_decode($block['tech_requirements'] ?? '{}', true);
    }
    
    return $blocks;
}

/**
 * Get countdown for a block
 */
function getBlockCountdown($block) {
    if (!$block || $block['status'] !== 'active') {
        return null;
    }
    
    $now = time();
    $start = strtotime($block['actual_start_time'] ?? $block['start_time']);
    $end = $start + ($block['duration_minutes'] * 60);
    
    $remaining = $end - $now;
    
    if ($remaining <= 0) {
        return ['finished' => true, 'remaining_seconds' => 0];
    }
    
    $minutes = floor($remaining / 60);
    $seconds = $remaining % 60;
    
    return [
        'finished' => false,
        'remaining_seconds' => $remaining,
        'remaining_formatted' => sprintf('%02d:%02d', $minutes, $seconds),
        'progress_percent' => min(100, (($now - $start) / ($end - $start)) * 100)
    ];
}

/**
 * Color codes for block types
 */
function getBlockTypeColors() {
    return [
        'presentation' => '#3b82f6', // Blue
        'break' => '#10b981',        // Green
        'video' => '#8b5cf6',        // Purple
        'audio' => '#f59e0b',        // Orange
        'other' => '#6b7280'         // Gray
    ];
}

/**
 * Get block type label
 */
function getBlockTypeLabel($type) {
    $labels = [
        'presentation' => 'Innlegg',
        'break' => 'Pause',
        'video' => 'Video',
        'audio' => 'Lyd',
        'other' => 'Annet'
    ];
    
    return $labels[$type] ?? 'Annet';
}

/**
 * Default tech requirements structure
 */
function getDefaultTechRequirements() {
    return [
        'microphone' => false,
        'presentation' => false,
        'video' => false,
        'lighting' => false,
        'audience_interaction' => false
    ];
}

/**
 * Export run of show to array (for PDF/Excel export)
 */
function exportROS($conferenceId) {
    $blocks = getROSBlocks($conferenceId);
    
    $export = [];
    foreach ($blocks as $block) {
        $export[] = [
            'Day' => $block['day_number'],
            'Start Time' => $block['start_time'],
            'End Time' => $block['end_time'],
            'Duration (min)' => $block['duration_minutes'],
            'Type' => getBlockTypeLabel($block['block_type']),
            'Title' => $block['title'],
            'Description' => $block['description'],
            'Location' => $block['location'],
            'Responsible' => $block['responsible_person'],
            'Presenter Notes' => $block['presenter_notes'],
            'Venue Notes' => $block['venue_notes'],
            'Microphone' => $block['tech_requirements']['microphone'] ? 'Yes' : 'No',
            'Presentation' => $block['tech_requirements']['presentation'] ? 'Yes' : 'No',
            'Video' => $block['tech_requirements']['video'] ? 'Yes' : 'No',
            'Lighting' => $block['tech_requirements']['lighting'] ? 'Yes' : 'No',
            'Audience Interaction' => $block['tech_requirements']['audience_interaction'] ? 'Yes' : 'No',
        ];
    }
    
    return $export;
}

/**
 * Duplicate blocks from one day to another
 */
function duplicateDayBlocks($conferenceId, $fromDay, $toDay) {
    $blocks = getROSBlocks($conferenceId, $fromDay);
    
    $results = [];
    foreach ($blocks as $block) {
        $data = [
            'conference_id' => $conferenceId,
            'title' => $block['title'],
            'description' => $block['description'],
            'block_type' => $block['block_type'],
            'start_time' => $block['start_time'],
            'duration_minutes' => $block['duration_minutes'],
            'day_number' => $toDay,
            'location' => $block['location'],
            'responsible_person' => $block['responsible_person'],
            'tech_requirements' => $block['tech_requirements'],
            'color_code' => $block['color_code'],
            'display_order' => $block['display_order'],
            'presenter_notes' => $block['presenter_notes']
        ];
        
        $results[] = createROSBlock($data);
    }
    
    return $results;
}
