<?php
/**
 * Authentication Functions
 */

require_once __DIR__ . '/functions.php';

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require login
 */
function requireLogin() {
    if (!isLoggedIn()) {
        setFlashMessage('error', 'Please log in to access this page.');
        redirect('index.php');
    }
}

/**
 * Get current user with role information
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $db = getDB();
    $stmt = $db->prepare("
        SELECT u.*, r.name as role_name, r.description as role_description
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Check if user is admin
 */
function isAdmin() {
    $user = getCurrentUser();
    return $user && ($user['role_name'] === 'admin' || $user['is_venue_admin']);
}

/**
 * Check if user is venue admin
 */
function isVenueAdmin() {
    $user = getCurrentUser();
    return $user && ($user['role_name'] === 'venue_admin' || $user['is_venue_admin'] || $user['role_name'] === 'admin');
}

/**
 * Check if user can access conference
 */
function canAccessConference($conferenceId) {
    if (!isLoggedIn()) {
        return false;
    }
    
    // Venue admins and admins can access all conferences
    if (isVenueAdmin()) {
        return true;
    }
    
    // Check if user owns the conference
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM conferences WHERE id = ? AND user_id = ?");
    $stmt->execute([$conferenceId, $_SESSION['user_id']]);
    return $stmt->fetch() !== false;
}

/**
 * Require venue admin access
 */
function requireVenueAdmin() {
    if (!isVenueAdmin()) {
        setFlashMessage('error', 'You do not have permission to access this page.');
        redirect('/admin/dashboard.php');
    }
}

/**
 * Login user
 */
function loginUser($username, $password) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT u.id, u.username, u.password_hash, u.is_venue_admin, r.name as role_name
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE u.username = ? OR u.email = ?
    ");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role_name'] = $user['role_name'];
        $_SESSION['is_venue_admin'] = $user['is_venue_admin'];
        return true;
    }
    
    return false;
}

/**
 * Register user
 */
function registerUser($username, $email, $password) {
    $db = getDB();
    
    // Check if username exists
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Username already exists.'];
    }
    
    // Check if email exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Email already exists.'];
    }
    
    // Hash password
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    
    // Insert user
    $stmt = $db->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
    if ($stmt->execute([$username, $email, $passwordHash])) {
        return ['success' => true, 'message' => 'Registration successful!'];
    }
    
    return ['success' => false, 'message' => 'Registration failed. Please try again.'];
}

/**
 * Logout user
 */
function logoutUser() {
    $_SESSION = [];
    session_destroy();
}

/**
 * Generate password reset token
 */
function generateResetToken($email) {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return false;
    }
    
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    $stmt = $db->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
    $stmt->execute([$token, $expires, $user['id']]);
    
    return $token;
}

/**
 * Verify reset token
 */
function verifyResetToken($token) {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->execute([$token]);
    return $stmt->fetch();
}

/**
 * Reset password
 */
function resetPassword($token, $newPassword) {
    $user = verifyResetToken($token);
    if (!$user) {
        return false;
    }
    
    $db = getDB();
    $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
    
    $stmt = $db->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
    return $stmt->execute([$passwordHash, $user['id']]);
}
