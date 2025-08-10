<?php
require_once '../config/database.php';

session_destroy();
header('Location: /auth/login.php');
exit();
?>
