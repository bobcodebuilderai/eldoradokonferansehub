<?php
/**
 * Logout
 */
require_once __DIR__ . '/../includes/auth.php';

logoutUser();
setFlashMessage('success', 'You have been logged out successfully.');
redirect('index.php');
