<?php
require_once 'config.php';

/**
 * Log an activity.
 */
function log_activity($user_id, $desc) {
    global $mysqli;
    $stmt = $mysqli->prepare('INSERT INTO activity_logs (user_id, activity_description) VALUES (?, ?)');
    $stmt->bind_param('is', $user_id, $desc);
    $stmt->execute();
    $stmt->close();
}

/**
 * Get a user record by username.
 */
function get_user_by_username($username) {
    global $mysqli;
    $stmt = $mysqli->prepare('SELECT user_id, name, username, password, role, status FROM users WHERE username = ? LIMIT 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $res;
}

/**
 * Safe table row count helper.
 * Accepts only simple table names (letters, numbers, underscore).
 * Returns integer count or 0 on invalid table.
 */
function count_table($table) {
    global $mysqli;
    // allow only simple table names to avoid injection
    if (!preg_match('/^[a-z0-9_]+$/i', $table)) {
        return 0;
    }
    $table = $mysqli->real_escape_string($table);
    $res = $mysqli->query("SELECT COUNT(*) as cnt FROM `$table`");
    if (!$res) return 0;
    $r = $res->fetch_assoc();
    return intval($r['cnt']);
}

/**
 * Low stock alerts (medicine only).
 * Returns array of ['type'=>'Medicine','item_name'=>...,'qty'=>...]
 * Uses either min_stock if set (>0) otherwise falls back to threshold 5.
 */
function low_stock_alerts() {
    global $mysqli;
    $alerts = [];

    // We'll use a query that returns medicines where quantity is less than min_stock (when min_stock > 0)
    // or quantity <= 5 when min_stock = 0 (legacy fallback).
    $sql = '
      SELECT med_id, item_name,
             COALESCE(quantity,0) AS quantity,
             COALESCE(min_stock,0) AS min_stock
      FROM medicine
      WHERE (min_stock > 0 AND quantity < min_stock)
         OR (COALESCE(min_stock,0) = 0 AND quantity <= 5)
      ORDER BY (min_stock > 0) DESC, (quantity - min_stock) ASC, quantity ASC
    ';
    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $alerts[] = [
                'type' => 'Medicine',
                'item_name' => $row['item_name'],
                'qty' => (int)$row['quantity'],
                'min_stock' => (int)$row['min_stock'],
                'med_id' => (int)$row['med_id']
            ];
        }
        $stmt->close();
    } else {
        // Fallback: simple query to avoid breaking if prepare fails
        $res = $mysqli->query("SELECT med_id, item_name, COALESCE(quantity,0) AS quantity FROM medicine WHERE quantity <= 5");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $alerts[] = [
                    'type' => 'Medicine',
                    'item_name' => $row['item_name'],
                    'qty' => (int)$row['quantity'],
                    'min_stock' => 0,
                    'med_id' => (int)$row['med_id']
                ];
            }
        }
    }

    return $alerts;
}
?>
