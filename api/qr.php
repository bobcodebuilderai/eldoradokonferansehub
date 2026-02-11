<?php
/**
 * QR Code Generator for Download
 * Generates QR codes using PHP QR Code library or simple output
 */
require_once __DIR__ . '/../includes/functions.php';

// Release session lock to prevent blocking other requests
session_write_close();

$code = trim($_GET['code'] ?? '');

if (empty($code)) {
    http_response_code(400);
    die('No conference code provided');
}

$db = getDB();
$stmt = $db->prepare("SELECT id, name FROM conferences WHERE unique_code = ? AND is_active = 1");
$stmt->execute([$code]);
$conference = $stmt->fetch();

if (!$conference) {
    http_response_code(404);
    die('Conference not found');
}

$guestUrl = getBaseUrl() . '/guest/?code=' . urlencode($code);

// Try to use PHP QR Code library if available, otherwise redirect to a QR API
$qrFile = __DIR__ . '/../assets/qr/conference_' . $conference['id'] . '.png';

// If we have phpqrcode library installed
if (file_exists(__DIR__ . '/../vendor/phpqrcode/qrlib.php')) {
    require_once __DIR__ . '/../vendor/phpqrcode/qrlib.php';
    
    // Generate QR code
    QRcode::png($guestUrl, $qrFile, 'M', 10, 2);
    
    // Serve the file
    header('Content-Type: image/png');
    header('Content-Disposition: attachment; filename="conference-' . $code . '-qr.png"');
    readfile($qrFile);
    exit;
}

// Fallback: Use QRServer API (free, no key required for basic usage)
// Or use Google's Chart API (deprecated but still works)
$qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=' . urlencode($guestUrl);

// Download and save the QR code
$qrImage = file_get_contents($qrApiUrl);
if ($qrImage !== false) {
    file_put_contents($qrFile, $qrImage);
    
    header('Content-Type: image/png');
    header('Content-Disposition: attachment; filename="conference-' . $code . '-qr.png"');
    echo $qrImage;
    exit;
}

// Ultimate fallback: Redirect to QR API
header('Location: ' . $qrApiUrl);
exit;
