<?php
/**
 * SMS Notifications via GatewayAPI.com
 */

require_once __DIR__ . '/database.php';

/**
 * Send SMS via GatewayAPI
 * 
 * @param string $to Phone number (international format, e.g., +4799999999)
 * @param string $message SMS message (max 160 chars for single SMS)
 * @param string $from Sender name (max 11 chars)
 * @return array ['success' => bool, 'message' => string, 'id' => string|null]
 */
function sendSMS($to, $message, $from = 'Eldorado') {
    // Get API credentials from environment or config
    $apiKey = $_ENV['GATEWAYAPI_KEY'] ?? getenv('GATEWAYAPI_KEY') ?? '';
    $apiSecret = $_ENV['GATEWAYAPI_SECRET'] ?? getenv('GATEWAYAPI_SECRET') ?? '';
    
    if (empty($apiKey) || empty($apiSecret)) {
        // Log error but don't expose in production
        error_log('GatewayAPI credentials not configured');
        return ['success' => false, 'message' => 'SMS not configured', 'id' => null];
    }
    
    // Clean phone number
    $to = preg_replace('/[^0-9+]/', '', $to);
    if (!str_starts_with($to, '+')) {
        // Assume Norwegian number if no country code
        if (str_starts_with($to, '00')) {
            $to = '+' . substr($to, 2);
        } elseif (str_starts_with($to, '0')) {
            $to = '+47' . substr($to, 1);
        } else {
            $to = '+' . $to;
        }
    }
    
    // Prepare payload
    $payload = [
        'sender' => substr($from, 0, 11),
        'message' => $message,
        'recipients' => [['msisdn' => $to]]
    ];
    
    // Send request
    $ch = curl_init('https://gatewayapi.com/rest/mtsms');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($apiKey . ':' . $apiSecret)
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        return [
            'success' => true,
            'message' => 'SMS sent successfully',
            'id' => $data['ids'][0] ?? null
        ];
    }
    
    $error = json_decode($response, true);
    return [
        'success' => false,
        'message' => $error['message'] ?? 'Failed to send SMS',
        'id' => null
    ];
}

/**
 * Send notification to venue team about new file upload
 */
function notifyVenueNewFile($conferenceId, $fileInfo) {
    // Get venue admin phone numbers
    $db = getDB();
    $stmt = $db->prepare("
        SELECT u.phone, c.name as conference_name
        FROM users u
        JOIN conferences c ON c.id = ?
        WHERE u.is_venue_admin = 1 AND u.phone IS NOT NULL
    ");
    $stmt->execute([$conferenceId]);
    $admins = $stmt->fetchAll();
    
    if (empty($admins)) {
        return ['success' => false, 'message' => 'No venue admins with phone numbers found'];
    }
    
    $results = [];
    $conferenceName = $admins[0]['conference_name'];
    
    foreach ($admins as $admin) {
        $message = sprintf(
            'Ny fil lastet opp: %s (%s) - %s',
            substr($fileInfo['original_name'], 0, 30),
            strtoupper($fileInfo['file_type']),
            $conferenceName
        );
        
        $results[] = sendSMS($admin['phone'], $message);
    }
    
    return ['success' => true, 'sent' => count($results), 'results' => $results];
}

/**
 * Send notification about Run of Show changes
 */
function notifyROSChanges($conferenceId, $changeType, $blockTitle) {
    $db = getDB();
    
    // Get conference owner and venue admins
    $stmt = $db->prepare("
        SELECT DISTINCT u.phone, c.name as conference_name
        FROM users u
        JOIN conferences c ON c.id = ?
        LEFT JOIN users va ON va.is_venue_admin = 1 AND va.phone IS NOT NULL
        WHERE (u.id = c.user_id OR u.is_venue_admin = 1) AND u.phone IS NOT NULL
    ");
    $stmt->execute([$conferenceId]);
    $recipients = $stmt->fetchAll();
    
    if (empty($recipients)) {
        return ['success' => false, 'message' => 'No recipients with phone numbers found'];
    }
    
    $messages = [
        'added' => 'Nytt innslag: %s - %s',
        'updated' => 'Endret: %s - %s',
        'deleted' => 'Slettet: %s - %s',
        'started' => 'STARTET: %s - %s',
        'completed' => 'Fullfort: %s - %s'
    ];
    
    $messageTemplate = $messages[$changeType] ?? 'Oppdatert: %s - %s';
    $conferenceName = $recipients[0]['conference_name'];
    
    $results = [];
    foreach ($recipients as $recipient) {
        $message = sprintf(
            $messageTemplate,
            substr($blockTitle, 0, 40),
            $conferenceName
        );
        
        $results[] = sendSMS($recipient['phone'], $message);
    }
    
    return ['success' => true, 'sent' => count($results), 'results' => $results];
}

/**
 * Send reminder before block starts
 */
function sendBlockReminder($phone, $blockTitle, $minutesUntil, $location = '') {
    $message = sprintf(
        '%s min: %s%s',
        $minutesUntil,
        substr($blockTitle, 0, 40),
        $location ? ' @ ' . $location : ''
    );
    
    return sendSMS($phone, $message);
}

/**
 * Test SMS configuration
 */
function testSMSConfig() {
    $apiKey = $_ENV['GATEWAYAPI_KEY'] ?? getenv('GATEWAYAPI_KEY') ?? '';
    $apiSecret = $_ENV['GATEWAYAPI_SECRET'] ?? getenv('GATEWAYAPI_SECRET') ?? '';
    
    return [
        'configured' => !empty($apiKey) && !empty($apiSecret),
        'has_key' => !empty($apiKey),
        'has_secret' => !empty($apiSecret)
    ];
}

/**
 * Store user phone number
 */
function updateUserPhone($userId, $phone) {
    $db = getDB();
    
    // Clean phone number
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    if (!str_starts_with($phone, '+') && !empty($phone)) {
        if (str_starts_with($phone, '00')) {
            $phone = '+' . substr($phone, 2);
        } elseif (str_starts_with($phone, '0')) {
            $phone = '+47' . substr($phone, 1);
        }
    }
    
    $stmt = $db->prepare("UPDATE users SET phone = ? WHERE id = ?");
    return $stmt->execute([$phone, $userId]);
}

/**
 * Get user phone number
 */
function getUserPhone($userId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT phone FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    return $result['phone'] ?? null;
}
