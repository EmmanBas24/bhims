<?php
require_once 'config.php';
require_once 'functions.php';
if (isset($_SESSION['user_id'])) {
    log_activity($_SESSION['user_id'], 'Logged out');
}
session_unset();
session_destroy();
header('Location: index.php');
exit;
?>