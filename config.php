<?php
// Database configuration for local XAMPP
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'bhis';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS);
if ($mysqli->connect_errno) {
    die('Database connect error: ' . $mysqli->connect_error);
}
$mysqli->select_db($DB_NAME);
$mysqli->set_charset('utf8mb4');

session_start();
function is_logged_in() {
    return isset($_SESSION['user_id']);
}
function require_login() {
    if (!is_logged_in()) {
        header('Location: index.php');
        exit;
    }
}
?>