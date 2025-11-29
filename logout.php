<?php
session_start();

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Clear the remember me cookie
if (isset($_COOKIE['ltaWannabeUser'])) {
    setcookie('ltaWannabeUser', '', time() - 3600, '/'); // Clear both potential cookies
}

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: login.php');
exit();
?>
