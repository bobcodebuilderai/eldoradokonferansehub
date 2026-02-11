<?php
/**
 * Root redirect - goes to login page
 */

// If already logged in, go to admin dashboard
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    redirect('/admin/dashboard.php');
} else {
    redirect('/login.php');
}
