<?php
require_once 'config/database.php';

// Redirect to dashboard if logged in, otherwise to login
if (isLoggedIn()) {
    header('Location: /dashboard.php');
} else {
    header('Location: /auth/login.php');
}
exit();
?>
