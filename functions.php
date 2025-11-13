<?php
require_once 'config.php';

function log_activity($user_id, $desc) {
    global $mysqli;
    $stmt = $mysqli->prepare('INSERT INTO activity_logs (user_id, activity_description) VALUES (?, ?)');
    $stmt->bind_param('is', $user_id, $desc);
    $stmt->execute();
    $stmt->close();
}

function get_user_by_username($username) {
    global $mysqli;
    $stmt = $mysqli->prepare('SELECT user_id, name, username, password, role, status FROM users WHERE username = ? LIMIT 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $res;
}

function count_table($table) {
    global $mysqli;
    $res = $mysqli->query("SELECT COUNT(*) as cnt FROM $table");
    $r = $res->fetch_assoc();
    return intval($r['cnt']);
}

function low_stock_alerts() {
    global $mysqli;
    $alerts = array();
    $stmt = $mysqli->prepare('SELECT med_id, item_name, quantity FROM medicine WHERE quantity <= 5');
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $alerts[] = array('type'=>'Medicine','item_name'=>$row['item_name'],'qty'=>$row['quantity']);
    $stmt->close();
    $stmt = $mysqli->prepare('SELECT supply_id, item_name, quantity FROM supplies WHERE quantity <= 5');
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $alerts[] = array('type'=>'Supply','item_name'=>$row['item_name'],'qty'=>$row['quantity']);
    $stmt->close();
    return $alerts;
}
?>