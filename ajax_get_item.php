<?php
// ajax_get_item.php
// Returns JSON:
// { success: true, item_name: "...", quantity: INT }
// OR { success:false, error:"not_found" | "empty_code" | ... }

require_once 'config.php';
header('Content-Type: application/json; charset=utf-8');

$item_code = trim($_GET['item_code'] ?? '');

if ($item_code === '') {
    echo json_encode(['success' => false, 'error' => 'empty_code']);
    exit;
}

// 1) Find medicine row by item_code
$stmt = $mysqli->prepare("
    SELECT med_id, item_name 
    FROM medicine 
    WHERE item_code = ? 
    LIMIT 1
");
if ($stmt === false) {
    echo json_encode(['success' => false, 'error' => 'prepare_failed']);
    exit;
}
$stmt->bind_param('s', $item_code);
$stmt->execute();
$res = $stmt->get_result();
$med = $res->fetch_assoc();
$stmt->close();

if (!$med) {
    echo json_encode(['success' => false, 'error' => 'not_found']);
    exit;
}

$med_id = (int)$med['med_id'];
$item_name = $med['item_name'];

// 2) Compute total stock from NON-EXPIRED batches only
$stmt2 = $mysqli->prepare("
    SELECT COALESCE(SUM(quantity_remaining), 0) AS total_stock
    FROM batches
    WHERE medicine_id = ?
      AND quantity_remaining > 0
      AND (expiry_date IS NULL OR expiry_date >= CURDATE())
");
if ($stmt2 === false) {
    echo json_encode(['success' => false, 'error' => 'prepare_failed']);
    exit;
}
$stmt2->bind_param('i', $med_id);
$stmt2->execute();
$res2 = $stmt2->get_result();
$row2 = $res2->fetch_assoc();
$stmt2->close();

$total_stock = (int)($row2['total_stock'] ?? 0);

// 3) Return data
echo json_encode([
    'success' => true,
    'item_name' => $item_name,
    'quantity' => $total_stock
]);
exit;
?>
