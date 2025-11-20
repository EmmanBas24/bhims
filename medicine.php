<?php
require_once 'header.php';
require_once 'functions.php';
$user_id = $_SESSION['user_id'] ?? 0;
$action = $_GET['action'] ?? 'list';

/*
  Medicine management (medicine-only) with:
  - Add medicine (master) + initial batch
  - Add batch for existing medicine
  - View batches (AJAX JSON)
  - Adjust batch remaining qty (POST)
  - Mark batch unusable (POST -> sets quantity_remaining = 0)
  - Delete medicine
  - Server-side validation: reject batch with expiry in the past
  - Edit action removed
*/

// categories (kept for compatibility)
$categories = ['Child','Adult','Newborns','Pediatric','Neonatal','Geriatric','General'];

/** Helper: generate item_code from name */
function generate_item_code_from_name(mysqli $mysqli, string $item_name): string {
    $raw = preg_replace('/[^A-Za-z0-9]/', '', strtoupper($item_name));
    $prefix = substr($raw, 0, 4);
    if ($prefix === '') $prefix = 'MED';
    $like = $prefix . '-%';
    $stmt = $mysqli->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(item_code,'-',-1) AS UNSIGNED)), 0) AS maxn FROM medicine WHERE item_code LIKE ?");
    if ($stmt === false) return $prefix . '-001';
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $maxn = (int)($row['maxn'] ?? 0);
    $stmt->close();
    $next = $maxn + 1;
    return sprintf('%s-%03d', $prefix, $next);
}

/** Helper: validate expiry (returns true if acceptable) */
function expiry_is_valid_or_empty(?string $expiry): bool {
    if ($expiry === null || $expiry === '') return true;
    try {
        $exp = new DateTimeImmutable($expiry);
        $today = new DateTimeImmutable('today');
        // expiry must be today or later
        return $exp >= $today;
    } catch (Exception $e) {
        return false;
    }
}

/* ---------------------------
   HANDLERS
   --------------------------- */

// 1) Add medicine (master + batch)
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_name    = trim($_POST['item_name'] ?? '');
    $generic_name = trim($_POST['generic_name'] ?? '');
    $dosage       = trim($_POST['dosage'] ?? '');
    $form         = trim($_POST['form'] ?? '');
    $unit         = trim($_POST['unit'] ?? 'pcs');
    $min_stock    = intval($_POST['min_stock'] ?? 0);

    // batch
    $batch_no     = trim($_POST['batch_no'] ?? '');
    $expiry_date  = $_POST['expiry_date'] ?: null;
    $date_received= $_POST['date_received'] ?: null;
    $quantity     = (int) ($_POST['quantity'] ?? 0);
    $supplier     = trim($_POST['supplier'] ?? '');

    if ($item_name === '' || $quantity <= 0) {
        $_SESSION['flash_error'] = 'Please provide name and a positive quantity for the batch.';
        header('Location: medicine.php?action=add'); exit;
    }

    // server-side expiry validation
    if (!expiry_is_valid_or_empty($expiry_date)) {
        $_SESSION['flash_error'] = 'Expiry date cannot be in the past.';
        header('Location: medicine.php?action=add'); exit;
    }

    $item_code = generate_item_code_from_name($mysqli, $item_name);

    $mysqli->begin_transaction();
    try {
        // create master if needed
        $chk = $mysqli->prepare('SELECT med_id FROM medicine WHERE item_code = ? LIMIT 1');
        if ($chk === false) throw new Exception('Prepare failed: '.$mysqli->error);
        $chk->bind_param('s', $item_code);
        $chk->execute();
        $cres = $chk->get_result();
        $med = $cres->fetch_assoc();
        $chk->close();

        if ($med) {
            $med_id = (int)$med['med_id'];
            $upd = $mysqli->prepare('UPDATE medicine SET item_name=?, generic_name=?, dosage=?, form=?, unit=?, min_stock=?, updated_at=NOW() WHERE med_id=?');
            if ($upd === false) throw new Exception('Prepare failed: '.$mysqli->error);
            $upd->bind_param('sssssii', $item_name, $generic_name, $dosage, $form, $unit, $min_stock, $med_id);
            $upd->execute();
            $upd->close();
        } else {
            $ins = $mysqli->prepare('INSERT INTO medicine (item_code, item_name, generic_name, dosage, form, unit, min_stock, created_at) VALUES (?,?,?,?,?,?,?,NOW())');
            if ($ins === false) throw new Exception('Prepare failed: '.$mysqli->error);
            $ins->bind_param('ssssssi', $item_code, $item_name, $generic_name, $dosage, $form, $unit, $min_stock);
            $ins->execute();
            $med_id = $ins->insert_id;
            $ins->close();
        }

        // insert batch (validate expiry already checked)
        $insBatch = $mysqli->prepare('INSERT INTO batches (medicine_id, batch_no, quantity_received, quantity_remaining, date_received, expiry_date, supplier, created_at) VALUES (?,?,?,?,?,?,?,NOW())');
        if ($insBatch === false) throw new Exception('Prepare failed: '.$mysqli->error);
        $insBatch->bind_param('isissss', $med_id, $batch_no, $quantity, $quantity, $date_received, $expiry_date, $supplier);
        $insBatch->execute();
        $batch_id = $insBatch->insert_id;
        $insBatch->close();

        // stock movement IN
        $insMove = $mysqli->prepare('INSERT INTO stock_movements (movement_type, medicine_id, batch_id, qty, unit, movement_date, note) VALUES (?,?,?,?,?,NOW(),?)');
        if ($insMove === false) throw new Exception('Prepare failed: '.$mysqli->error);
        $mvType = 'IN';
        $note = 'Received batch ' . ($batch_no ?: $batch_id);
        $insMove->bind_param('siiiss', $mvType, $med_id, $batch_id, $quantity, $unit, $note);
        $insMove->execute();
        $insMove->close();

        // update aggregated quantity if column exists
        $checkCol = $mysqli->query("SHOW COLUMNS FROM medicine LIKE 'quantity'");
        if ($checkCol && $checkCol->num_rows) {
            $updQty = $mysqli->prepare('UPDATE medicine SET quantity = IFNULL(quantity,0) + ? WHERE med_id = ?');
            if ($updQty === false) throw new Exception('Prepare failed: '.$mysqli->error);
            $updQty->bind_param('ii', $quantity, $med_id);
            $updQty->execute();
            $updQty->close();
        }

        $mysqli->commit();
        log_activity($user_id, 'Added medicine/batch: '.$item_name.' (code '.$item_code.' qty '.$quantity.')');
        $_SESSION['flash_success'] = 'Medicine and batch added successfully. Code: ' . htmlspecialchars($item_code);
        header('Location: medicine.php'); exit;
    } catch (Exception $e) {
        $mysqli->rollback();
        error_log('Medicine add error: ' . $e->getMessage());
        $_SESSION['flash_error'] = 'Failed to add medicine: ' . htmlspecialchars($e->getMessage());
        header('Location: medicine.php?action=add'); exit;
    }
}

// 2) Add batch for existing medicine
if ($action === 'add_batch' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $medicine_id   = intval($_POST['medicine_id'] ?? 0);
    $batch_no      = trim($_POST['batch_no'] ?? '');
    $quantity      = (int) ($_POST['quantity'] ?? 0);
    $date_received = $_POST['date_received'] ?: null;
    $expiry_date   = $_POST['expiry_date'] ?: null;
    $supplier      = trim($_POST['supplier'] ?? '');
    $unit          = trim($_POST['unit'] ?? 'pcs');

    if ($medicine_id <= 0 || $quantity <= 0) {
        $_SESSION['flash_error'] = 'Invalid medicine or quantity.';
        header('Location: medicine.php'); exit;
    }

    // expiry validation
    if (!expiry_is_valid_or_empty($expiry_date)) {
        $_SESSION['flash_error'] = 'Expiry date cannot be in the past.';
        header('Location: medicine.php'); exit;
    }

    $mysqli->begin_transaction();
    try {
        // confirm medicine exists
        $chk = $mysqli->prepare('SELECT item_name FROM medicine WHERE med_id = ? LIMIT 1');
        if ($chk === false) throw new Exception('Prepare failed: '.$mysqli->error);
        $chk->bind_param('i', $medicine_id);
        $chk->execute();
        $cres = $chk->get_result();
        $mrow = $cres->fetch_assoc();
        $chk->close();
        if (!$mrow) throw new Exception('Medicine not found.');

        $insBatch = $mysqli->prepare('INSERT INTO batches (medicine_id, batch_no, quantity_received, quantity_remaining, date_received, expiry_date, supplier, created_at) VALUES (?,?,?,?,?,?,?,NOW())');
        if ($insBatch === false) throw new Exception('Prepare failed: '.$mysqli->error);
        $insBatch->bind_param('isissss', $medicine_id, $batch_no, $quantity, $quantity, $date_received, $expiry_date, $supplier);
        $insBatch->execute();
        $batch_id = $insBatch->insert_id;
        $insBatch->close();

        $insMove = $mysqli->prepare('INSERT INTO stock_movements (movement_type, medicine_id, batch_id, qty, unit, movement_date, note) VALUES (?,?,?,?,?,NOW(),?)');
        if ($insMove === false) throw new Exception('Prepare failed: '.$mysqli->error);
        $mvType = 'IN';
        $note = 'Batch added via Add Batch (batch ' . ($batch_no ?: $batch_id) . ')';
        $insMove->bind_param('siiiss', $mvType, $medicine_id, $batch_id, $quantity, $unit, $note);
        $insMove->execute();
        $insMove->close();

        // update master aggregated quantity if exists
        $checkCol = $mysqli->query("SHOW COLUMNS FROM medicine LIKE 'quantity'");
        if ($checkCol && $checkCol->num_rows) {
            $updQty = $mysqli->prepare('UPDATE medicine SET quantity = IFNULL(quantity,0) + ? WHERE med_id = ?');
            if ($updQty === false) throw new Exception('Prepare failed: '.$mysqli->error);
            $updQty->bind_param('ii', $quantity, $medicine_id);
            $updQty->execute();
            $updQty->close();
        }

        $mysqli->commit();
        log_activity($user_id, 'Added batch for medicine_id '.$medicine_id.' qty '.$quantity);
        $_SESSION['flash_success'] = 'Batch added successfully.';
        header('Location: medicine.php'); exit;
    } catch (Exception $e) {
        $mysqli->rollback();
        error_log('Add batch error: ' . $e->getMessage());
        $_SESSION['flash_error'] = 'Failed to add batch: ' . htmlspecialchars($e->getMessage());
        header('Location: medicine.php'); exit;
    }
}

// 3) View batches (AJAX JSON) - GET
if ($action === 'view_batches' && ($_SERVER['REQUEST_METHOD'] === 'GET')) {
    $med_id = intval($_GET['med_id'] ?? 0);
    header('Content-Type: application/json; charset=utf-8');
    if ($med_id <= 0) {
        echo json_encode(['success'=>false,'error'=>'invalid_med_id']); exit;
    }
    $stmt = $mysqli->prepare('SELECT id AS batch_id, batch_no, quantity_received, quantity_remaining, date_received, expiry_date, supplier, created_at FROM batches WHERE medicine_id = ? ORDER BY expiry_date IS NULL, expiry_date ASC, created_at ASC');
    if ($stmt === false) {
        echo json_encode(['success'=>false,'error'=>'db_prepare_failed']); exit;
    }
    $stmt->bind_param('i', $med_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $batches = [];
    while ($r = $res->fetch_assoc()) {
        $batches[] = $r;
    }
    $stmt->close();
    echo json_encode(['success'=>true,'batches'=>$batches]);
    exit;
}

// 4) Adjust batch remaining qty (POST)
if ($action === 'adjust_batch' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $batch_id = intval($_POST['batch_id'] ?? 0);
    $new_remaining = (int) ($_POST['new_remaining'] ?? -1);
    $note = trim($_POST['note'] ?? 'Manual adjustment');

    if ($batch_id <= 0 || $new_remaining < 0) {
        $_SESSION['flash_error'] = 'Invalid parameters for adjustment.';
        header('Location: medicine.php'); exit;
    }

    $mysqli->begin_transaction();
    try {
        // fetch current qty and medicine
        $stmt = $mysqli->prepare('SELECT medicine_id, quantity_remaining FROM batches WHERE id = ? LIMIT 1');
        if ($stmt === false) throw new Exception('Prepare failed: '.$mysqli->error);
        $stmt->bind_param('i', $batch_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $b = $res->fetch_assoc();
        $stmt->close();
        if (!$b) throw new Exception('Batch not found.');

        $med_id = (int)$b['medicine_id'];
        $old_remaining = (int)$b['quantity_remaining'];
        $delta = $new_remaining - $old_remaining; // positive means increase stock

        // update batch remaining
        $ust = $mysqli->prepare('UPDATE batches SET quantity_remaining = ? WHERE id = ?');
        if ($ust === false) throw new Exception('Prepare failed: '.$mysqli->error);
        $ust->bind_param('ii', $new_remaining, $batch_id);
        $ust->execute();
        $ust->close();

        // record stock movement as ADJ (adjust), note includes provided note
        $insMove = $mysqli->prepare('INSERT INTO stock_movements (movement_type, medicine_id, batch_id, qty, unit, movement_date, note) VALUES (?,?,?,?,?,NOW(),?)');
        if ($insMove === false) throw new Exception('Prepare failed: '.$mysqli->error);
        $mvType = 'ADJ';
        // we store absolute delta as qty; move sign inferred from ADJ type/note
        $insMove->bind_param('siiiss', $mvType, $med_id, $batch_id, $delta, $b['quantity_received'] ? 'pcs' : 'pcs', $note . ' (old:' . $old_remaining . ', new:' . $new_remaining . ')');
        $insMove->execute();
        $insMove->close();

        // update master aggregated quantity if exists
        $checkCol = $mysqli->query("SHOW COLUMNS FROM medicine LIKE 'quantity'");
        if ($checkCol && $checkCol->num_rows) {
            $updQty = $mysqli->prepare('UPDATE medicine SET quantity = IFNULL(quantity,0) + ? WHERE med_id = ?');
            if ($updQty === false) throw new Exception('Prepare failed: '.$mysqli->error);
            $updQty->bind_param('ii', $delta, $med_id);
            $updQty->execute();
            $updQty->close();
        }

        $mysqli->commit();
        log_activity($user_id, 'Adjusted batch '.$batch_id.' from '.$old_remaining.' to '.$new_remaining);
        $_SESSION['flash_success'] = 'Batch adjusted.';
        header('Location: medicine.php'); exit;
    } catch (Exception $e) {
        $mysqli->rollback();
        error_log('Adjust batch error: ' . $e->getMessage());
        $_SESSION['flash_error'] = 'Failed to adjust batch: ' . htmlspecialchars($e->getMessage());
        header('Location: medicine.php'); exit;
    }
}

// 5) Mark batch unusable (sets remaining = 0) — POST
if ($action === 'mark_unusable' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $batch_id = intval($_POST['batch_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? 'Marked unusable');

    if ($batch_id <= 0) {
        $_SESSION['flash_error'] = 'Invalid batch id.';
        header('Location: medicine.php'); exit;
    }

    $mysqli->begin_transaction();
    try {
        $stmt = $mysqli->prepare('SELECT medicine_id, quantity_remaining FROM batches WHERE id = ? LIMIT 1');
        if ($stmt === false) throw new Exception('Prepare failed: '.$mysqli->error);
        $stmt->bind_param('i', $batch_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $b = $res->fetch_assoc();
        $stmt->close();
        if (!$b) throw new Exception('Batch not found.');

        $med_id = (int)$b['medicine_id'];
        $old_remaining = (int)$b['quantity_remaining'];
        if ($old_remaining <= 0) {
            // nothing to do, but still mark
            $new_remaining = 0;
        } else {
            $new_remaining = 0;
        }

        $ust = $mysqli->prepare('UPDATE batches SET quantity_remaining = 0 WHERE id = ?');
        if ($ust === false) throw new Exception('Prepare failed: '.$mysqli->error);
        $ust->bind_param('i', $batch_id);
        $ust->execute();
        $ust->close();

        // log as ADJ or OUT with negative delta
        $delta = $new_remaining - $old_remaining; // negative or zero
        $insMove = $mysqli->prepare('INSERT INTO stock_movements (movement_type, medicine_id, batch_id, qty, unit, movement_date, note) VALUES (?,?,?,?,?,NOW(),?)');
        if ($insMove === false) throw new Exception('Prepare failed: '.$mysqli->error);
        $mvType = 'ADJ';
        $unit = 'pcs';
        $note = 'Marked unusable: ' . $reason . ' (old:' . $old_remaining . ')';
        $insMove->bind_param('siiiss', $mvType, $med_id, $batch_id, $delta, $unit, $note);
        $insMove->execute();
        $insMove->close();

        // update master aggregated quantity if exists
        $checkCol = $mysqli->query("SHOW COLUMNS FROM medicine LIKE 'quantity'");
        if ($checkCol && $checkCol->num_rows) {
            $updQty = $mysqli->prepare('UPDATE medicine SET quantity = IFNULL(quantity,0) + ? WHERE med_id = ?');
            if ($updQty === false) throw new Exception('Prepare failed: '.$mysqli->error);
            $updQty->bind_param('ii', $delta, $med_id);
            $updQty->execute();
            $updQty->close();
        }

        $mysqli->commit();
        log_activity($user_id, 'Marked batch '.$batch_id.' unusable (old remaining '.$old_remaining.')');
        $_SESSION['flash_success'] = 'Batch marked unusable.';
        header('Location: medicine.php'); exit;
    } catch (Exception $e) {
        $mysqli->rollback();
        error_log('Mark unusable error: ' . $e->getMessage());
        $_SESSION['flash_error'] = 'Failed to mark batch unusable: ' . htmlspecialchars($e->getMessage());
        header('Location: medicine.php'); exit;
    }
}

// 6) Delete medicine (unchanged)
if ($action === 'delete') {
    $id = intval($_GET['id'] ?? 0);
    $stmt = $mysqli->prepare('DELETE FROM medicine WHERE med_id=?');
    if ($stmt === false) { die('Prepare failed: '.$mysqli->error); }
    $stmt->bind_param('i', $id);
    $stmt->execute(); $stmt->close();
    log_activity($user_id,'Deleted medicine ID '.$id);
    $_SESSION['flash_success'] = 'Medicine deleted.';
    header('Location: medicine.php'); exit;
}

/* ---------------------------
   LISTING (with search + status filters)
   --------------------------- */
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

// Base SQL: aggregate non-expired batches for stock
$sql = "SELECT m.med_id, m.item_code, m.item_name, m.dosage, m.unit, m.min_stock, m.status,
               COALESCE(SUM(b.quantity_remaining),0) AS total_stock
        FROM medicine m
        LEFT JOIN batches b
          ON b.medicine_id = m.med_id
          AND (b.expiry_date IS NULL OR b.expiry_date >= CURDATE())
        WHERE 1=1";

$params = [];
$types = '';

// search by name (safe prepared param)
if ($search !== '') {
    $sql .= " AND m.item_name LIKE ?";
    $params[] = "%$search%";
    $types .= 's';
}

$sql .= " GROUP BY m.med_id";

// Apply status filter by using HAVING for stock-related statuses, or an EXISTS check for Expired.
if ($status !== '') {
    if ($status === 'Available') {
        // total_stock > 0 and not expired (we already excluded expired batches in join)
        $sql .= " HAVING total_stock > 0";
    } elseif ($status === 'Low Stock') {
        // total_stock > 0 and less than min_stock (if min_stock=0, treat as no threshold)
        $sql .= " HAVING total_stock > 0 AND (m.min_stock > 0 AND total_stock < m.min_stock)";
    } elseif ($status === 'Out of Stock') {
        $sql .= " HAVING total_stock = 0";
    } elseif ($status === 'Expired') {
        // items that have at least one batch that is already expired (we check batches table directly)
        $sql .= " AND EXISTS (
                    SELECT 1 FROM batches bx
                    WHERE bx.medicine_id = m.med_id
                      AND bx.quantity_remaining > 0
                      AND bx.expiry_date IS NOT NULL
                      AND bx.expiry_date < CURDATE()
                  )";
    }
}

// default ordering
$sql .= " ORDER BY m.item_name ASC";

$stmt = $mysqli->prepare($sql);
if ($stmt === false) { die('Prepare failed: ' . $mysqli->error); }

// bind params dynamically if any
if (!empty($params)) {
    // mysqli requires types string separate then values
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// derive statuses and nearest expiry (same logic you had)
$derived = []; $soon_count = 0;
$today = new DateTimeImmutable('today');
foreach ($rows as $r) {
    $row = $r;
    $row['quantity'] = (int)$r['total_stock'];
    $row['derived_status'] = $r['status'] ?? 'Available';
    $row['is_expired'] = false; $row['is_soon'] = false; $row['days_to_expiry'] = null;

    // find nearest expiry for this med with quantity_remaining > 0
    $nearest_expiry = null;
    $stmt2 = $mysqli->prepare("SELECT expiry_date FROM batches WHERE medicine_id = ? AND quantity_remaining > 0 AND expiry_date IS NOT NULL ORDER BY expiry_date ASC LIMIT 1");
    if ($stmt2) {
        $stmt2->bind_param('i', $r['med_id']);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        $nx = $res2->fetch_assoc();
        $nearest_expiry = $nx['expiry_date'] ?? null;
        $stmt2->close();
        if (!empty($nearest_expiry) && $nearest_expiry !== '0000-00-00') {
            try {
                $exp = new DateTimeImmutable($nearest_expiry);
                $days = (int) floor(($exp->getTimestamp() - $today->getTimestamp())/86400);
                $row['days_to_expiry'] = $days;
                if ($days < 0) $row['is_expired'] = true;
                elseif ($days <= 30) $row['is_soon'] = true;
            } catch (Exception $e) { $row['days_to_expiry'] = null; }
        }
    }

    if ($row['is_expired']) $row['derived_status']='Expired';
    elseif ($row['quantity'] <= 0) $row['derived_status']='Out of Stock';
    elseif ($row['quantity'] < max(1, (int)$r['min_stock'])) $row['derived_status']='Low Stock';
    else $row['derived_status']='Available';
    if ($row['is_soon'] && !$row['is_expired']) $soon_count++;
    $derived[] = $row;
}



?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Medicine Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<style>
.table-modern th,.table-modern td{padding:12px 10px;vertical-align:middle}
.modal-backdrops{position:fixed;inset:0;background:rgba(0,0,0,0.45);display:none;z-index:1050;align-items:center;justify-content:center;padding:1rem}
.modal-backdrops.show{display:flex}
.modal-panel{background:#fff;border-radius:10px;width:100%;max-width:920px;box-shadow:0 10px 30px rgba(0,0,0,0.25);z-index:1060;padding:1.25rem;max-height:90vh;overflow:auto}
.row-soon-expire{background-color:#fff6f6}
.row-expired{background-color:#f3f3f3;color:#6b6b6b}
.badge-available{background:#e7f7e7;padding:4px 8px;border-radius:6px;color:#2b8a2b;font-weight:600}
.badge-low{background:#fff4e5;padding:4px 8px;border-radius:6px;color:#b36a00;font-weight:600}
.badge-expired{background:#ffecec;padding:4px 8px;border-radius:6px;color:#a80000;font-weight:700}
.badge-out{background:#ffdede;padding:4px 8px;border-radius:6px;color:#a80000;font-weight:700}
.form-code { background:#f6f7f9;padding:.5rem .75rem;border-radius:6px;border:1px solid #e9ecef; }
.btn-xs { padding:.25rem .45rem; font-size:.82rem; border-radius:6px; }
.table-batches th, .table-batches td { padding:.45rem .5rem; border-bottom:1px solid #eee; vertical-align:middle; }

/* Replace your existing row-soon-expire / row-expired rules with these */
.row-soon-expire {
  background: #fff2f2;        /* pale red */
  color: #3b1f1f;             /* dark red-ish text for contrast */
  transition: background .12s ease;
}
.row-soon-expire td, .row-soon-expire th {
  border-color: rgba(255,0,0,0.04);
}

/* expired rows: muted / greyed out */
.row-expired {
  background: #f3f3f3;
  color: #6b6b6b;
}

/* Keep hover from overriding the soft red too strongly */
.table-modern tbody tr.row-soon-expire:hover {
  background: #ffecec; /* slightly stronger on hover */
}

/* ensure expired hover is subtle */
.table-modern tbody tr.row-expired:hover {
  background: #ebecec;
}

</style>
</head>
<body>
<div class="container-fluid py-3">
   <div class="d-flex justify-content-between align-items-center mb-2">
    <h3 class="mb-0">Medicine Management</h3>
    <div class="d-flex align-items-center gap-2">
      <div style="font-weight:600">Soon to expired = <?php echo (int)$soon_count ?></div>
    </div>
  </div>

  <!-- Filters: Search by name + Status -->
  <form method="get" class="d-flex gap-2 align-items-center mb-2">
    <input type="text"
           name="search"
           value="<?php echo htmlspecialchars($_GET['search'] ?? '') ?>"
           placeholder="Search medicine..."
           class="form-control"
           style="max-width:320px;">
    <select name="status" class="form-select" style="max-width:200px;">
        <option value="">All Status</option>
        <option value="Available"   <?php if(($_GET['status'] ?? '')==='Available') echo 'selected'; ?>>Available</option>
        <option value="Low Stock"   <?php if(($_GET['status'] ?? '')==='Low Stock') echo 'selected'; ?>>Low Stock</option>
        <option value="Out of Stock"<?php if(($_GET['status'] ?? '')==='Out of Stock') echo 'selected'; ?>>Out of Stock</option>
        <option value="Expired"     <?php if(($_GET['status'] ?? '')==='Expired') echo 'selected'; ?>>Expired</option>
    </select>

    <button class="btn btn-primary btn-sm" type="submit">Filter</button>
    <a href="medicine.php" class="btn btn-outline-secondary btn-sm">Reset</a>

    <div style="margin-left:auto;">
      <a id="openUnifiedBtn" href="medicine.php?action=add" class="btn btn-sm btn-success"><i class="bi bi-plus"></i> Add Medicine</a>
    </div>
  </form>


  <?php if (!empty($_SESSION['flash_success'])): ?>
    <div style="padding:.6rem;border-radius:6px;background:#e6ffed;color:#065f2d;margin-bottom:.75rem;"><?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div style="padding:.6rem;border-radius:6px;background:#ffecec;color:#811b1b;margin-bottom:.75rem;"><?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
  <?php endif; ?>

  <div class="table-responsive">
    <table class="table table-modern table-hover w-100">
      <thead class="bg-dark text-white">
        <tr>
          <th>Code</th><th>Medicine</th><th>Dosage</th><th>Unit Qty</th><th>Status</th><th>Nearest Expiry</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($derived)): ?>
          <tr><td colspan="8">No records found.</td></tr>
        <?php else: foreach ($derived as $r):
            $tr_class = $r['is_expired'] ? 'row-expired' : ($r['is_soon'] ? 'row-soon-expire' : '');
        ?>
          <tr class="<?php echo $tr_class ?>">
            <td><?php echo htmlspecialchars($r['item_code'] ?? '')?></td>
            <td><?php echo htmlspecialchars($r['item_name'])?></td>
            <td><?php echo htmlspecialchars($r['dosage'] ?? '')?></td>
            <td><?php echo (int)$r['quantity']?></td>
            <td>
              <?php
                $ds = $r['derived_status'];
                if ($ds === 'Available') echo '<span class="badge-available">Available</span>';
                elseif ($ds === 'Low Stock') echo '<span class="badge-low">Low Stock</span>';
                elseif ($ds === 'Out of Stock') echo '<span class="badge-out">Out of Stock</span>';
                else echo '<span class="badge-expired">Expired</span>';
              ?>
            </td>
            <td><?php echo htmlspecialchars($r['days_to_expiry'] !== null ? ($r['days_to_expiry'] < 0 ? 'Expired' : $r['days_to_expiry'].' days') : '—')?></td>
           
            <td>
              <?php
                // data attributes for add-batch & view-batches
                $data_attrs = [
                  'id'=>$r['med_id'],'item_code'=>$r['item_code'] ?? '','item_name'=>$r['item_name'],
                  'unit'=>$r['unit'] ?? 'pcs','min_stock'=>$r['min_stock'] ?? 0,'quantity'=>$r['quantity']
                ];
                $data_str = '';
                foreach ($data_attrs as $k=>$v) $data_str .= ' data-'.$k.'="'.htmlspecialchars($v,ENT_QUOTES).'"';
              ?>
              <button class="btn btn-xs btn-outline-success openAddBatch" <?php echo $data_str?> title="Add Batch"><i class="bi bi-box-arrow-in-down"></i> Add Batch</button>
              <button class="btn btn-xs btn-outline-primary openViewBatches" <?php echo $data_str?> title="View Batches"><i class="bi bi-card-list"></i> View Batches</button>
              <a class="btn btn-xs btn-outline-danger" href="medicine.php?action=delete&id=<?php echo $r['med_id']?>" onclick="return confirm('Delete?')"><i class="bi bi-trash"></i></a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Medicine modal (master + batch) -->
<div id="unifiedModal" class="modal-backdrops" aria-hidden="true" role="dialog" aria-modal="true">
  <div class="modal-panel" role="document">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div id="unifiedTitle" style="font-weight:600">Add Medicine</div>
      <button class="btn btn-sm btn-light" data-close>&times;</button>
    </div>

    <form id="unifiedForm" method="post" action="medicine.php?action=add">
      <input type="hidden" id="mode_field" name="mode" value="add">
      <div class="row g-2">
        <div class="col-md-4"><label class="form-label">Item Name</label><input id="u_item_name" name="item_name" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label">Generic Name</label><input id="u_generic" name="generic_name" class="form-control" /></div>

        <div class="col-md-4"><label class="form-label">Dosage</label><input id="u_dosage" name="dosage" class="form-control" placeholder="e.g. 500 mg" /></div>
        <div class="col-md-4"><label class="form-label">Form</label><input id="u_form" name="form" class="form-control" placeholder="tablet, syrup, vial" /></div>
        <div class="col-md-4"><label class="form-label">Unit</label><input id="u_unit" name="unit" class="form-control" value="pcs" /></div>

        <div class="col-md-4"><label class="form-label">Min Stock</label><input id="u_min_stock" name="min_stock" type="number" class="form-control" value="0" min="0" /></div>

        <hr style="width:100%;border-top:1px dashed #e9ecef;margin:8px 0;">

        <!-- Batch fields -->
        <div class="col-md-4"><label class="form-label">Batch No</label><input id="u_batch_no" name="batch_no" class="form-control" /></div>
        <div class="col-md-4"><label class="form-label">Quantity (batch)</label><input id="u_quantity" type="number" name="quantity" class="form-control" value="0" min="0" required /></div>
        <div class="col-md-4"><label class="form-label">Supplier</label><input id="u_supplier" name="supplier" class="form-control" /></div>

        <div class="col-md-6"><label class="form-label">Date Received</label><input id="u_date_received" type="date" name="date_received" class="form-control" /></div>
        <div class="col-md-6"><label class="form-label">Expiry Date</label><input id="u_expiry_date" type="date" name="expiry_date" class="form-control" /></div>

      </div>
      <div class="d-flex justify-content-end gap-2 mt-3">
        <button id="u_submit" type="submit" class="btn btn-primary btn-sm">Save</button>
        <button type="button" class="btn btn-secondary btn-sm" data-close>Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Add Batch modal -->
<div id="addBatchModal" class="modal-backdrops" aria-hidden="true" role="dialog" aria-modal="true">
  <div class="modal-panel" role="document" style="max-width:560px;">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div id="addBatchTitle" style="font-weight:600">Add Batch</div>
      <button class="btn btn-sm btn-light" data-close-batch>&times;</button>
    </div>

    <form id="addBatchForm" method="post" action="medicine.php?action=add_batch">
      <input type="hidden" id="batch_medicine_id" name="medicine_id" value="">
      <div class="row g-2">
        <div class="col-12">
          <label class="form-label">Medicine</label>
          <div id="batch_med_display" class="form-code">—</div>
        </div>

        <div class="col-md-6"><label class="form-label">Batch No</label><input id="batch_no" name="batch_no" class="form-control" /></div>
        <div class="col-md-6"><label class="form-label">Quantity</label><input id="batch_quantity" name="quantity" type="number" min="1" class="form-control" value="0" required/></div>

        <div class="col-md-6"><label class="form-label">Date Received</label><input id="batch_date_received" name="date_received" type="date" class="form-control" /></div>
        <div class="col-md-6"><label class="form-label">Expiry Date</label><input id="batch_expiry_date" name="expiry_date" type="date" class="form-control" /></div>

        <div class="col-12"><label class="form-label">Supplier</label><input id="batch_supplier" name="supplier" class="form-control" /></div>

        <div class="col-12" style="display:none;">
          <input id="batch_unit" name="unit" value="pcs" />
        </div>
      </div>

      <div class="d-flex justify-content-end gap-2 mt-3">
        <button type="submit" class="btn btn-primary btn-sm">Add Batch</button>
        <button type="button" class="btn btn-secondary btn-sm" data-close-batch>Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- View Batches modal -->
<div id="viewBatchesModal" class="modal-backdrops" aria-hidden="true" role="dialog" aria-modal="true">
  <div class="modal-panel" role="document" style="max-width:820px;">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div id="viewBatchesTitle" style="font-weight:600">Batches for — <span id="vb_med_name">—</span></div>
      <button class="btn btn-sm btn-light" data-close-view>&times;</button>
    </div>

    <div id="vb_alerts" style="margin-bottom:.5rem;"></div>

    <div style="overflow:auto; max-height:60vh;">
      <table class="table table-batches w-100" id="vb_table">
        <thead>
          <tr><th>Batch ID</th><th>Batch No</th><th>Received</th><th>Remaining</th><th>Expiry</th><th>Supplier</th><th>Actions</th></tr>
        </thead>
        <tbody id="vb_tbody">
          <tr><td colspan="7">Loading…</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
// UI behaviors for Add Medicine / Add Batch / View Batches + adjustments
(function(){
  const body = document.body;

  // Add Medicine modal
  const uModal = document.getElementById('unifiedModal');
  const uForm = document.getElementById('unifiedForm');
  function openUModal(){ uModal.classList.add('show'); body.classList.add('modal-open'); uModal.setAttribute('aria-hidden','false'); const f = document.getElementById('u_item_name'); if(f) setTimeout(()=>f.focus(),50); }
  function closeUModal(){ uModal.classList.remove('show'); body.classList.remove('modal-open'); uModal.setAttribute('aria-hidden','true'); uForm.reset(); }
  document.querySelectorAll('[data-close]').forEach(btn=> btn.addEventListener('click', closeUModal));
  uModal.addEventListener('click', e=> { if(e.target === uModal) closeUModal(); });
  document.getElementById('openUnifiedBtn').addEventListener('click', e=> { e.preventDefault(); uForm.reset(); openUModal(); });

  // Add Batch modal
  const batchModal = document.getElementById('addBatchModal');
  const batchForm = document.getElementById('addBatchForm');
  const batchMedDisplay = document.getElementById('batch_med_display');
  const batchMedId = document.getElementById('batch_medicine_id');
  function openBatchModal(){ batchModal.classList.add('show'); body.classList.add('modal-open'); batchModal.setAttribute('aria-hidden','false'); }
  function closeBatchModal(){ batchModal.classList.remove('show'); body.classList.remove('modal-open'); batchModal.setAttribute('aria-hidden','true'); batchForm.reset(); batchMedDisplay.textContent='—'; batchMedId.value=''; }
  document.querySelectorAll('[data-close-batch]').forEach(btn=> btn.addEventListener('click', closeBatchModal));
  batchModal.addEventListener('click', e=> { if(e.target === batchModal) closeBatchModal(); });

  document.querySelectorAll('.openAddBatch').forEach(btn=>{
    btn.addEventListener('click', function(e){
      e.preventDefault();
      const id = this.dataset.id;
      const code = this.dataset.item_code || '';
      const name = this.dataset.item_name || '';
      batchMedDisplay.textContent = (code ? code + ' — ' : '') + name;
      batchMedId.value = id || '';
      document.getElementById('batch_unit').value = this.dataset.unit || 'pcs';
      document.getElementById('batch_no').value = '';
      document.getElementById('batch_quantity').value = 0;
      document.getElementById('batch_supplier').value = '';
      document.getElementById('batch_date_received').value = '';
      document.getElementById('batch_expiry_date').value = '';
      openBatchModal();
    });
  });

  // View Batches modal & actions
  const vbModal = document.getElementById('viewBatchesModal');
  const vbTitleName = document.getElementById('vb_med_name');
  const vbBody = document.getElementById('vb_tbody');
  const vb_alerts = document.getElementById('vb_alerts');

  function openVBModal(){ vbModal.classList.add('show'); body.classList.add('modal-open'); vbModal.setAttribute('aria-hidden','false'); }
  function closeVBModal(){ vbModal.classList.remove('show'); body.classList.remove('modal-open'); vbModal.setAttribute('aria-hidden','true'); vbBody.innerHTML = '<tr><td colspan="7">Loading…</td></tr>'; vb_alerts.innerHTML=''; vbTitleName.textContent='—'; }
  document.querySelectorAll('[data-close-view]').forEach(btn=> btn.addEventListener('click', closeVBModal));
  vbModal.addEventListener('click', e=> { if(e.target === vbModal) closeVBModal(); });

  async function fetchBatches(med_id) {
    vbBody.innerHTML = '<tr><td colspan="7">Loading…</td></tr>';
    try {
const resp = await fetch('ajax_get_batches.php?med_id=' + encodeURIComponent(med_id), {
    credentials: 'same-origin'
});

      if (!resp.ok) throw new Error('network');
      const data = await resp.json();
      if (!data.success) throw new Error(data.error || 'unknown');
      renderBatchesTable(data.batches);
    } catch (err) {
      vbBody.innerHTML = '<tr><td colspan="7">Failed to load batches.</td></tr>';
      vb_alerts.innerHTML = '<div style="padding:.5rem;border-radius:6px;background:#ffecec;color:#811b1b">Error loading batches: ' + String(err) + '</div>';
    }
  }

  function renderBatchesTable(batches) {
    if (!batches || batches.length === 0) {
      vbBody.innerHTML = '<tr><td colspan="7">No batches found.</td></tr>';
      return;
    }
    let html = '';
    for (const b of batches) {
      const expiry = b.expiry_date ? b.expiry_date : '—';
      html += '<tr>';
      html += '<td>' + escapeHtml(b.batch_id) + '</td>';
      html += '<td>' + escapeHtml(b.batch_no || '—') + '</td>';
      html += '<td>' + escapeHtml(b.quantity_received) + '</td>';
      html += '<td>' + escapeHtml(b.quantity_remaining) + '</td>';
      html += '<td>' + escapeHtml(expiry) + '</td>';
      html += '<td>' + escapeHtml(b.supplier || '—') + '</td>';
      html += '<td style="white-space:nowrap;">' +
               '<button class="btn btn-xs btn-outline-secondary vb-adjust" data-batch-id="' + b.batch_id + '">Adjust</button> ' +
               '<button class="btn btn-xs btn-outline-danger vb-unusable" data-batch-id="' + b.batch_id + '">Mark Unusable</button>' +
               '</td>';
      html += '</tr>';
    }
    vbBody.innerHTML = html;

    // wire adjust buttons
    document.querySelectorAll('.vb-adjust').forEach(btn=>{
      btn.addEventListener('click', function(){
        const batchId = this.dataset.batchId;
        const current = this.closest('tr').children[3].textContent;
        let newQty = prompt('Enter new remaining quantity (integer):', current);
        if (newQty === null) return;
        newQty = parseInt(newQty,10);
        if (isNaN(newQty) || newQty < 0) { alert('Invalid quantity'); return; }
        const note = prompt('Optional note for adjustment:', 'Manual adjustment');
        postAdjustBatch(batchId, newQty, note || '');
      });
    });

    // wire mark unusable
    document.querySelectorAll('.vb-unusable').forEach(btn=>{
      btn.addEventListener('click', function(){
        const batchId = this.dataset.batchId;
        if (!confirm('Mark this batch as unusable? This will set remaining quantity to 0.')) return;
        const reason = prompt('Reason (optional):', 'Expired / contaminated / damaged');
        postMarkUnusable(batchId, reason || '');
      });
    });
  }

  // helper: escape
  function escapeHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  // post adjust
  async function postAdjustBatch(batchId, newRemaining, note) {
    try {
      const form = new FormData();
      form.append('batch_id', batchId);
      form.append('new_remaining', newRemaining);
      form.append('note', note);
      const resp = await fetch('medicine.php?action=adjust_batch', { method:'POST', body: form, credentials:'same-origin' });
      // response is a redirect (since server sets session flash and redirects). To pick up result, we reload page to see messages.
      if (!resp.ok) throw new Error('network');
      // simply reload to reflect updates and show flash messages
      location.reload();
    } catch (err) {
      alert('Failed to adjust batch: ' + err);
    }
  }

  // post mark unusable
  async function postMarkUnusable(batchId, reason) {
    try {
      const form = new FormData();
      form.append('batch_id', batchId);
      form.append('reason', reason);
      const resp = await fetch('medicine.php?action=mark_unusable', { method:'POST', body: form, credentials:'same-origin' });
      if (!resp.ok) throw new Error('network');
      location.reload();
    } catch (err) {
      alert('Failed to mark unusable: ' + err);
    }
  }

  // wire View Batches buttons
  document.querySelectorAll('.openViewBatches').forEach(btn=>{
    btn.addEventListener('click', function(e){
      e.preventDefault();
      const id = this.dataset.id;
      const code = this.dataset.item_code || '';
      const name = this.dataset.item_name || '';
      vbTitleName.textContent = (code ? code + ' — ' : '') + name;
      openVBModal();
      fetchBatches(id);
    });
  });

  // wire Add Batch openers (already set above) - re-add for dynamic load
  document.querySelectorAll('.openAddBatch').forEach(btn=>{
    btn.addEventListener('click', function(e){
      e.preventDefault();
      const id = this.dataset.id;
      const code = this.dataset.item_code || '';
      const name = this.dataset.item_name || '';
      document.getElementById('batch_med_display').textContent = (code ? code + ' — ' : '') + name;
      document.getElementById('batch_medicine_id').value = id || '';
      document.getElementById('batch_unit').value = this.dataset.unit || 'pcs';
      document.getElementById('batch_no').value = '';
      document.getElementById('batch_quantity').value = 0;
      document.getElementById('batch_supplier').value = '';
      document.getElementById('batch_date_received').value = '';
      document.getElementById('batch_expiry_date').value = '';
      openBatchModal();
    });
  });

  // close on Escape
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') {
      if (uModal.classList.contains('show')) closeUModal();
      if (batchModal.classList.contains('show')) closeBatchModal();
      if (vbModal.classList.contains('show')) closeVBModal();
    }
  });

})();
</script>
</body>
</html>
