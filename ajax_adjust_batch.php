<?php
// ajax_adjust_batch.php
// POST: batch_id, new_remaining, note
// Adjusts batch.quantity_remaining and writes a stock_movements ADJ entry
// Returns JSON

require_once 'config.php';
session_start();

header('Content-Type: application/json; charset=utf-8');

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'unauthorized']);
    exit;
}

// only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'method_not_allowed']);
    exit;
}

$batch_id = intval($_POST['batch_id'] ?? 0);
$new_remaining = isset($_POST['new_remaining']) ? intval($_POST['new_remaining']) : null;
$note = trim($_POST['note'] ?? 'Manual adjustment');

if ($batch_id <= 0 || $new_remaining === null || $new_remaining < 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'invalid_parameters']);
    exit;
}

$mysqli->begin_transaction();
try {
    // fetch batch
    $stmt = $mysqli->prepare('SELECT medicine_id, quantity_remaining, quantity_received FROM batches WHERE id = ? LIMIT 1');
    if ($stmt === false) throw new Exception('Prepare failed: '.$mysqli->error);
    $stmt->bind_param('i', $batch_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $batch = $res->fetch_assoc();
    $stmt->close();

    if (!$batch) throw new Exception('Batch not found.');

    $med_id = (int)$batch['medicine_id'];
    $old_remaining = (int)$batch['quantity_remaining'];
    $delta = $new_remaining - $old_remaining; // positive => increase stock, negative => decrease

    // update batch
    $ust = $mysqli->prepare('UPDATE batches SET quantity_remaining = ? WHERE id = ?');
    if ($ust === false) throw new Exception('Prepare failed: '.$mysqli->error);
    $ust->bind_param('ii', $new_remaining, $batch_id);
    $ust->execute();
    $ust->close();

    // write stock_movements (ADJ)
    $ins = $mysqli->prepare('INSERT INTO stock_movements (movement_type, medicine_id, batch_id, qty, unit, movement_date, note) VALUES (?,?,?,?,?,NOW(),?)');
    if ($ins === false) throw new Exception('Prepare failed: '.$mysqli->error);
    $mvType = 'ADJ';
    // attempt to infer unit from batch/medicine - fallback 'pcs'
    $unit = 'pcs';
    $ins->bind_param('siiiss', $mvType, $med_id, $batch_id, $delta, $unit, $note . ' (old:' . $old_remaining . ', new:' . $new_remaining . ')');
    $ins->execute();
    $ins->close();

    // update aggregated medicine.quantity if column exists
    $checkCol = $mysqli->query("SHOW COLUMNS FROM medicine LIKE 'quantity'");
    if ($checkCol && $checkCol->num_rows) {
        $updQty = $mysqli->prepare('UPDATE medicine SET quantity = IFNULL(quantity,0) + ? WHERE med_id = ?');
        if ($updQty === false) throw new Exception('Prepare failed: '.$mysqli->error);
        $updQty->bind_param('ii', $delta, $med_id);
        $updQty->execute();
        $updQty->close();
    }

    $mysqli->commit();

    // optional activity log function if available
    if (function_exists('log_activity')) {
        log_activity($user_id, 'Adjusted batch '.$batch_id.' for med '.$med_id.' from '.$old_remaining.' to '.$new_remaining);
    }

    echo json_encode(['success' => true, 'message' => 'Batch adjusted.', 'batch_id' => $batch_id, 'new_remaining' => $new_remaining]);
    exit;
} catch (Exception $e) {
    $mysqli->rollback();
    http_response_code(500);
    error_log('Adjust batch error: '.$e->getMessage());
    echo json_encode(['success' => false, 'error' => 'adjust_failed', 'detail' => $e->getMessage()]);
    exit;
}
