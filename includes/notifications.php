<?php
/**
 * Email Notification Functions
 */

require_once __DIR__ . '/database.php';

/**
 * Send email notification
 * 
 * @param string $to Email address
 * @param string $subject Email subject
 * @param string $body HTML body
 * @return array ['success' => bool, 'message' => string]
 */
function sendEmail($to, $subject, $body) {
    // Get email configuration
    $smtpHost = $_ENV['SMTP_HOST'] ?? getenv('SMTP_HOST') ?? '';
    $smtpPort = $_ENV['SMTP_PORT'] ?? getenv('SMTP_PORT') ?? '587';
    $smtpUser = $_ENV['SMTP_USER'] ?? getenv('SMTP_USER') ?? '';
    $smtpPass = $_ENV['SMTP_PASS'] ?? getenv('SMTP_PASS') ?? '';
    $fromEmail = $_ENV['SMTP_FROM'] ?? getenv('SMTP_FROM') ?? 'noreply@eldorado.gg';
    $fromName = $_ENV['SMTP_FROM_NAME'] ?? getenv('SMTP_FROM_NAME') ?? 'Eldorado Konferansehub';
    
    // If no SMTP config, use PHP mail
    if (empty($smtpHost)) {
        $headers = "From: {$fromName} <{$fromEmail}>\r\n";
        $headers .= "Reply-To: {$fromEmail}\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        $result = mail($to, $subject, $body, $headers);
        
        return [
            'success' => $result,
            'message' => $result ? 'Email sent' : 'Failed to send email'
        ];
    }
    
    // SMTP sending would require PHPMailer or similar
    // For now, return error indicating SMTP needs setup
    return [
        'success' => false,
        'message' => 'SMTP not configured. Please set SMTP_HOST in .env'
    ];
}

/**
 * Send notification based on user preference
 */
function sendUserNotification($userId, $subject, $message, $priority = 'normal') {
    $db = getDB();
    
    // Get user preferences
    $stmt = $db->prepare("
        SELECT email, phone, notification_method, email_notifications, sms_notifications
        FROM users WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return ['success' => false, 'message' => 'User not found'];
    }
    
    $method = $user['notification_method'] ?? 'email';
    $results = [];
    
    // Send based on preference
    if ($method === 'sms' && $user['phone'] && $user['sms_notifications']) {
        require_once __DIR__ . '/sms.php';
        $results[] = sendSMS($user['phone'], $message);
    } elseif ($method === 'email' && $user['email'] && $user['email_notifications']) {
        $htmlBody = "<html><body>"
            . "<h2>{$subject}</h2>"
            . "<p>{$message}</p>"
            . "<hr><p><small>Sent from Eldorado Konferansehub</small></p>"
            . "</body></html>";
        $results[] = sendEmail($user['email'], $subject, $htmlBody);
    } elseif ($method === 'both') {
        // Send to both if user wants both
        if ($user['email'] && $user['email_notifications']) {
            $htmlBody = "<html><body>"
                . "<h2>{$subject}</h2>"
                . "<p>{$message}</p>"
                . "<hr><p><small>Sent from Eldorado Konferansehub</small></p>"
                . "</body></html>";
            $results[] = sendEmail($user['email'], $subject, $htmlBody);
        }
        if ($user['phone'] && $user['sms_notifications']) {
            require_once __DIR__ . '/sms.php';
            $results[] = sendSMS($user['phone'], $message);
        }
    }
    
    return [
        'success' => count(array_filter($results, fn($r) => $r['success'])) > 0,
        'results' => $results
    ];
}

/**
 * Update user notification preferences
 */
function updateNotificationPreferences($userId, $data) {
    $db = getDB();
    
    $allowed = ['notification_method', 'email_notifications', 'sms_notifications'];
    $updates = [];
    $values = [];
    
    foreach ($data as $key => $value) {
        if (in_array($key, $allowed)) {
            $updates[] = "$key = ?";
            $values[] = $value;
        }
    }
    
    if (empty($updates)) {
        return ['success' => false, 'message' => 'No valid fields'];
    }
    
    $values[] = $userId;
    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    
    return ['success' => $stmt->execute($values)];
}

/**
 * Get notification preferences for user
 */
function getNotificationPreferences($userId) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT notification_method, email_notifications, sms_notifications, email, phone
        FROM users WHERE id = ?
    ");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

/**
 * Notify venue team about important change
 */
function notifyVenueTeam($conferenceId, $subject, $message, $priority = 'normal') {
    $db = getDB();
    
    // Get venue admins and conference owner
    $stmt = $db->prepare("
        SELECT DISTINCT u.id, u.email, u.phone, u.notification_method,
               u.email_notifications, u.sms_notifications
        FROM users u
        JOIN conferences c ON c.id = ?
        WHERE u.is_venue_admin = 1 OR u.id = c.user_id
    ");
    $stmt->execute([$conferenceId]);
    $recipients = $stmt->fetchAll();
    
    $results = [];
    foreach ($recipients as $recipient) {
        $results[] = sendUserNotification($recipient['id'], $subject, $message, $priority);
    }
    
    return [
        'success' => count(array_filter($results, fn($r) => $r['success'] ?? false)) > 0,
        'sent_count' => count($results),
        'results' => $results
    ];
}
