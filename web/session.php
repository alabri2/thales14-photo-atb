<?php
session_start();
require_once('config.php');

// Verifying if the user is already connected
if (!isset($_SESSION['user'])) { // testing if the session isn't configure
    header('Location: login.php'); // leads the user to the login page
    exit;
}

// Automatic logout after inactivity
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) { # testing if the user inactivity is greater than the session timeout that we configured
    session_unset();
    session_destroy(); // resetting the session
    header('Location: login.php'); // leads the user to the login page
    exit;
}

// Update the user activity
$_SESSION['last_activity'] = time();
?>
