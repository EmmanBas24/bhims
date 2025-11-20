<?php
// ajax_get_batches.php
// Minimal JSON-only endpoint. DO NOT include header.php here.

require_once 'config.php'; // must define $mysqli and call session_start()
header('Content-Type: application/json; charset=utf-8');

// simple auth check
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'unauthorized']);
    exit;
}

$med_id = intval($_GET['med_id'] ?? 0);
if ($med_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'invalid_med_id']);
    exit;
}

// Ensure $mysqli exists
if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    http_response_code(500);
    error_log('ajax_get_batches: $mysqli not available.');
    echo json_encode(['success' => false, 'error' => 'db_not_available']);
    exit;
}

$sql = 'SELECT id AS batch_id, batch_no, quantity_received, quantity_remaining, date_received, expiry_date, supplier, created_at
        FROM batches
        WHERE medicine_id = ?
        ORDER BY expiry_date IS NULL, expiry_date ASC, created_at ASC';

$stmt = $mysqli->prepare($sql);
if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'db_prepare_failed', 'detail' => $mysqli->error]);
    exit;
}

$stmt->bind_param('i', $med_id);
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'db_execute_failed']);
    $stmt->close();
    exit;
}

$res = $stmt->get_result();
$batches = [];
while ($r = $res->fetch_assoc()) {
    $r['batch_no'] = $r['batch_no'] ?? '';
    $r['date_received'] = $r['date_received'] ?? '';
    $r['expiry_date'] = $r['expiry_date'] ?? '';
    $r['supplier'] = $r['supplier'] ?? '';
    $batches[] = $r;
}
$stmt->close();

echo json_encode(['success' => true, 'batches' => $batches]);
exit;
