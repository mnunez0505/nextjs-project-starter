<?php
session_start();

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'cheque_system');

// Timezone
date_default_timezone_set('America/New_York');

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to check user role
function hasRole($required_role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $required_role;
}

// Function to redirect with message
function redirectWith($url, $message, $type = 'info') {
    $_SESSION['flash'] = [
        'message' => $message,
        'type' => $type
    ];
    header("Location: $url");
    exit();
}
