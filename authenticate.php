<?php
session_start();
require 'includes/db.php';

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    die("CSRF token validation failed.");
}

// Hardcoded credentials (CHANGE THESE!)
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', '7!cWULntwwo9*8'); // Change this in production!

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

// Simple validation
if (empty($username) || empty($password)) {
    header('Location: login.php?error=1');
    exit;
}

// Verify credentials
if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);

    // Set session variables
    $_SESSION['loggedin'] = true;
    $_SESSION['user'] = $username;
    $_SESSION['login_time'] = time();
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];

    // Redirect to dashboard
    header('Location: index.php');
    exit;
} else {
    // Log failed attempt (optional)
    error_log("Failed login attempt for username: {$username}");

    // Redirect back to login
    header('Location: login.php?error=1');
    exit;
}
?>
