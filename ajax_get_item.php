<?php
// ajax_get_item.php
// Returns JSON { success: true/false, item_name: string, quantity: int, error: 'not_found'|'empty_code'|... }

require_once 'config.php';
header('Content-Type: application/json; charset=utf-8');

$category = $_GET['category'] ?? '';
$item_code = $_GET['item_code'] ?? '';

$category = trim($category);
$item_code = trim($item_code);

if ($item_code === '') {
    echo json_encode(['success' => false, 'error' => 'empty_code']);
    exit;
}

// choose table based on category (case-insensitive)
$table = (strtolower($category) === 'medicine') ? 'medicine' : 'supplies';

// Prepare query safely
$sql = "SELECT item_name, quantity FROM `$table` WHERE item_code = ? LIMIT 1";
$stmt = $mysqli->prepare($sql);
if ($stmt === false) {
    echo json_encode(['success' => false, 'error' => 'prepare_failed', 'msg' => $mysqli->error]);
    exit;
}
$stmt->bind_param('s', $item_code);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    echo json_encode([
        'success' => true,
        'item_name' => $row['item_name'],
        'quantity' => (int)$row['quantity']
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'not_found']);
}
$stmt->close();
