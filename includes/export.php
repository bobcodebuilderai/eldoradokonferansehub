<?php
/**
 * Export Functions for Run of Show
 * PDF and Excel generation
 */

require_once __DIR__ . '/database.php';

/**
 * Export Run of Show to PDF
 * Requires TCPDF or similar library
 */
function exportROStoPDF($conferenceId, $dayNumber = null) {
    // For now, return data structure that can be used with any PDF library
    $db = getDB();
    
    // Get conference info
    $stmt = $db->prepare("SELECT * FROM conferences WHERE id = ?");
    $stmt->execute([$conferenceId]);
    $conference = $stmt->fetch();
    
    if (!$conference) {
        return ['success' => false, 'message' => 'Conference not found'];
    }
    
    require_once __DIR__ . '/runofshow.php';
    $blocks = getROSBlocks($conferenceId, $dayNumber);
    
    // Calculate statistics
    $totalDuration = calculateDayDuration($conferenceId, $dayNumber);
    
    $pdfData = [
        'conference_name' => $conference['name'],
        'conference_code' => $conference['unique_code'],
        'generated_at' => date('Y-m-d H:i:s'),
        'day' => $dayNumber,
        'total_duration' => $totalDuration['formatted'],
        'blocks' => []
    ];
    
    foreach ($blocks as $block) {
        $pdfData['blocks'][] = [
            'time' => substr($block['start_time'], 0, 5) . ' - ' . substr($block['end_time'], 0, 5),
            'duration' => $block['duration_minutes'] . ' min',
            'type' => getBlockTypeLabel($block['block_type']),
            'title' => $block['title'],
            'description' => $block['description'],
            'location' => $block['location'],
            'responsible' => $block['responsible_person'],
            'tech' => formatTechRequirements($block['tech_requirements']),
            'presenter_notes' => $block['presenter_notes'],
            'venue_notes' => $block['venue_notes']
        ];
    }
    
    return ['success' => true, 'data' => $pdfData];
}

/**
 * Export Run of Show to Excel/CSV
 */
function exportROStoExcel($conferenceId, $dayNumber = null, $format = 'csv') {
    require_once __DIR__ . '/runofshow.php';
    $export = exportROS($conferenceId);
    
    if ($format === 'csv') {
        $output = fopen('php://temp', 'r+');
        
        // Headers
        fputcsv($output, array_keys($export[0] ?? []));
        
        // Data
        foreach ($export as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return [
            'success' => true,
            'content' => $csv,
            'filename' => 'run_of_show_' . date('Y-m-d') . '.csv'
        ];
    }
    
    // For Excel format, we'd need a library like PhpSpreadsheet
    return ['success' => false, 'message' => 'Excel format requires PhpSpreadsheet library'];
}

/**
 * Format tech requirements for display
 */
function formatTechRequirements($tech) {
    if (!$tech) return '';
    
    $items = [];
    if ($tech['microphone'] ?? false) $items[] = 'Mic';
    if ($tech['presentation'] ?? false) $items[] = 'Pres';
    if ($tech['video'] ?? false) $items[] = 'Video';
    if ($tech['lighting'] ?? false) $items[] = 'Light';
    if ($tech['audience_interaction'] ?? false) $items[] = 'Q&A';
    
    return implode(', ', $items);
}

/**
 * Generate HTML table for print/PDF
 */
function generateROSHtmlTable($conferenceId, $dayNumber = null) {
    require_once __DIR__ . '/runofshow.php';
    $blocks = getROSBlocks($conferenceId, $dayNumber);
    
    $html = '<table border="1" cellpadding="10" cellspacing="0" style="width:100%; border-collapse: collapse;">';
    $html .= '<thead style="background-color: #f3f4f6;">';
    $html .= '<tr>';
    $html .= '<th>Time</th>';
    $html .= '<th>Duration</th>';
    $html .= '<th>Type</th>';
    $html .= '<th>Title</th>';
    $html .= '<th>Location</th>';
    $html .= '<th>Responsible</th>';
    $html .= '<th>Tech Needs</th>';
    $html .= '</tr>';
    $html .= '</thead>';
    $html .= '<tbody>';
    
    foreach ($blocks as $block) {
        $color = getBlockTypeColors()[$block['block_type']] ?? '#6b7280';
        
        $html .= '<tr>';
        $html .= '<td>' . substr($block['start_time'], 0, 5) . ' - ' . substr($block['end_time'], 0, 5) . '</td>';
        $html .= '<td>' . $block['duration_minutes'] . ' min</td>';
        $html .= '<td style="background-color: ' . $color . '; color: white;">' . getBlockTypeLabel($block['block_type']) . '</td>';
        $html .= '<td><strong>' . htmlspecialchars($block['title']) . '</strong>';
        if ($block['description']) {
            $html .= '<br><small>' . htmlspecialchars($block['description']) . '</small>';
        }
        $html .= '</td>';
        $html .= '<td>' . htmlspecialchars($block['location']) . '</td>';
        $html .= '<td>' . htmlspecialchars($block['responsible_person']) . '</td>';
        $html .= '<td>' . formatTechRequirements($block['tech_requirements']) . '</td>';
        $html .= '</tr>';
        
        // Add notes row if present
        if ($block['presenter_notes'] || $block['venue_notes']) {
            $html .= '<tr style="background-color: #f9fafb;">';
            $html .= '<td colspan="7" style="font-size: 0.9em;">';
            if ($block['presenter_notes']) {
                $html .= '<strong>Presenter Notes:</strong> ' . htmlspecialchars($block['presenter_notes']) . '<br>';
            }
            if ($block['venue_notes']) {
                $html .= '<strong>Venue Notes:</strong> ' . htmlspecialchars($block['venue_notes']);
            }
            $html .= '</td>';
            $html .= '</tr>';
        }
    }
    
    $html .= '</tbody>';
    $html .= '</table>';
    
    return $html;
}

/**
 * Simple PDF generation using output buffering and print styles
 * This creates a printable HTML page that can be saved as PDF
 */
function generatePrintableROS($conferenceId, $dayNumber = null) {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT * FROM conferences WHERE id = ?");
    $stmt->execute([$conferenceId]);
    $conference = $stmt->fetch();
    
    require_once __DIR__ . '/runofshow.php';
    $blocks = getROSBlocks($conferenceId, $dayNumber);
    $duration = calculateDayDuration($conferenceId, $dayNumber);
    
    $html = '<!DOCTYPE html>';
    $html .= '<html><head>';
    $html .= '<meta charset="UTF-8">';
    $html .= '<title>Run of Show - ' . htmlspecialchars($conference['name']) . '</title>';
    $html .= '<style>';
    $html .= 'body { font-family: Arial, sans-serif; margin: 20px; }';
    $html .= 'h1 { color: #1e40af; }';
    $html .= 'table { width: 100%; border-collapse: collapse; margin-top: 20px; }';
    $html .= 'th, td { border: 1px solid #d1d5db; padding: 12px; text-align: left; }';
    $html .= 'th { background-color: #f3f4f6; font-weight: bold; }';
    $html .= '.type-presentation { background-color: #dbeafe; }';
    $html .= '.type-break { background-color: #d1fae5; }';
    $html .= '.type-video { background-color: #e9d5ff; }';
    $html .= '.type-audio { background-color: #fed7aa; }';
    $html .= '.notes { background-color: #f9fafb; font-size: 0.9em; }';
    $html .= '.summary { margin: 20px 0; padding: 15px; background-color: #f3f4f6; border-radius: 8px; }';
    $html .= '@media print { .no-print { display: none; } }';
    $html .= '</style>';
    $html .= '</head><body>';
    
    $html .= '<div class="no-print" style="margin-bottom: 20px;">';
    $html .= '<button onclick="window.print()" style="padding: 10px 20px; background: #3b82f6; color: white; border: none; border-radius: 5px; cursor: pointer;">🖨️ Print / Save as PDF</button>';
    $html .= '</div>';
    
    $html .= '<h1>📋 Run of Show</h1>';
    $html .= '<h2>' . htmlspecialchars($conference['name']) . '</h2>';
    
    $html .= '<div class="summary">';
    $html .= '<strong>Conference Code:</strong> ' . htmlspecialchars($conference['unique_code']) . '<br>';
    $html .= '<strong>Day:</strong> ' . ($dayNumber ?? 'All') . '<br>';
    $html .= '<strong>Total Duration:</strong> ' . $duration['formatted'] . '<br>';
    $html .= '<strong>Generated:</strong> ' . date('Y-m-d H:i:s') . '<br>';
    $html .= '<strong>Items:</strong> ' . count($blocks);
    $html .= '</div>';
    
    $html .= generateROSHtmlTable($conferenceId, $dayNumber);
    
    $html .= '<div style="margin-top: 40px; padding-top: 20px; border-top: 2px solid #e5e7eb; color: #6b7280; font-size: 0.9em;">';
    $html .= '<p>Generated by Eldorado Konferansehub</p>';
    $html .= '</div>';
    
    $html .= '</body></html>';
    
    return $html;
}
