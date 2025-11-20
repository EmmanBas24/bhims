<?php
// ajax_mark_unusable.php
// POST: batch_id, reason
// Sets quantity_remaining = 0 and writes stock_movements entry
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'method_not_allowed']);
    exit;
}

$batch_id = intval($_POST['batch_id'] ?? 0);
$reason = trim($_POST['reason'] ?? 'Marked unusable');

if ($batch_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'invalid_parameters']);
    exit;
}

$mysqli->begin_transaction();
try {
    $stmt = $mysqli->prepare('SELECT medicine_id, quantity_remaining FROM batches WHERE id = ? LIMIT 1');
    if ($stmt === false) throw new Exception('Prepare failed: '.$mysqli->error);
    $stmt->bind_param('i', $batch_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $batch = $res->fetch_assoc();
    $stmt->close();

    if (!$batch) throw new Exception('Batch not found.');

    $med_id = (int)$batch['medicine_id'];
    $old_remaining = (int)$batch['quantity_remaining'];
    if ($old_remaining <= 0) {
        // nothing to deduct, but still mark
        $delta = 0;
    } else {
        $delta = -$old_remaining; // negative delta to reduce master aggregated qty
    }

    // set remaining to 0
    $ust = $mysqli->prepare('UPDATE batches SET quantity_remaining = 0 WHERE id = ?');
    if ($ust === false) throw new Exception('Prepare failed: '.$mysqli->error);
    $ust->bind_param('i', $batch_id);
    $ust->execute();
    $ust->close();

    // write stock_movements (ADJ)
    $ins = $mysqli->prepare('INSERT INTO stock_movements (movement_type, medicine_id, batch_id, qty, unit, movement_date, note) VALUES (?,?,?,?,?,NOW(),?)');
    if ($ins === false) throw new Exception('Prepare failed: '.$mysqli->error);
    $mvType = 'ADJ';
    $unit = 'pcs';
    $note = 'Marked unusable: ' . $reason . ' (old_remaining:' . $old_remaining . ')';
    $ins->bind_param('siiiss', $mvType, $med_id, $batch_id, $delta, $unit, $note);
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

    if (function_exists('log_activity')) {
        log_activity($user_id, 'Marked batch '.$batch_id.' unusable for med '.$med_id.' (removed '.$old_remaining.')');
    }

    echo json_encode(['success' => true, 'message' => 'Batch marked unusable.', 'batch_id' => $batch_id]);
    exit;
} catch (Exception $e) {
    $mysqli->rollback();
    http_response_code(500);
    error_log('Mark unusable error: '.$e->getMessage());
    echo json_encode(['success' => false, 'error' => 'mark_unusable_failed', 'detail' => $e->getMessage()]);
    exit;
}
