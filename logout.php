<?php
// logout.php
require_once 'config.php';

// Log the activity if user was logged in
if (is_logged_in()) {
    log_activity($_SESSION['user_id'], 'LOGOUT', 'User logged out');
}

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page
redirect('login.php?msg=logged_out');
?>