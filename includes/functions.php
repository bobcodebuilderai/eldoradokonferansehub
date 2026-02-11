<?php
/**
 * Common Functions
 */

require_once __DIR__ . '/../config/database.php';

session_start();

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize output
 */
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate unique code for conference
 */
function generateUniqueCode($length = 8) {
    $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    $max = strlen($characters) - 1;
    
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[random_int(0, $max)];
    }
    
    return $code;
}

/**
 * Flash message helpers
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function showFlashMessage() {
    $flash = getFlashMessage();
    if ($flash) {
        $colors = [
            'success' => 'bg-green-100 border-green-400 text-green-700',
            'error' => 'bg-red-100 border-red-400 text-red-700',
            'warning' => 'bg-yellow-100 border-yellow-400 text-yellow-700',
            'info' => 'bg-blue-100 border-blue-400 text-blue-700'
        ];
        $colorClass = $colors[$flash['type']] ?? $colors['info'];
        
        echo '<div class="' . $colorClass . ' px-4 py-3 rounded mb-4 border" role="alert">';
        echo e($flash['message']);
        echo '</div>';
    }
}

/**
 * Redirect helper
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Get current URL
 */
function getCurrentUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

/**
 * Get base URL
 */
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
}

/**
 * Language functions
 */
function getLang($key, $lang = null) {
    if ($lang === null) {
        $lang = $_SESSION['lang'] ?? 'no';
    }
    
    $langFile = __DIR__ . '/lang/' . $lang . '.php';
    if (!file_exists($langFile)) {
        $langFile = __DIR__ . '/lang/no.php';
    }
    
    $translations = require $langFile;
    return $translations[$key] ?? $key;
}

function setLanguage($lang) {
    $_SESSION['lang'] = $lang;
}

function getCurrentLanguage() {
    return $_SESSION['lang'] ?? 'no';
}

// Short helper function
function __($key) {
    return getLang($key);
}

/**
 * Get conference by ID
 */
function getConferenceById($conferenceId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM conferences WHERE id = ?");
    $stmt->execute([$conferenceId]);
    return $stmt->fetch();
}
