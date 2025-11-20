<?php
// issuance.php
// NOTE: header.php already includes config.php (which should start session if not active)
require_once 'header.php';
require_once 'functions.php';

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) { header('Location: index.php'); exit; }

$action = $_GET['action'] ?? 'list';

/* --- month options for filters (YYYY-MM) --- */
$month_options = [];
$mstmt = $mysqli->prepare("SELECT DISTINCT DATE_FORMAT(date_issued, '%Y-%m') AS ym FROM issuance WHERE date_issued IS NOT NULL ORDER BY ym DESC");
if ($mstmt !== false) {
    $mstmt->execute();
    $res = $mstmt->get_result();
    while ($r = $res->fetch_assoc()) {
        if (!empty($r['ym'])) $month_options[] = $r['ym'];
    }
    $mstmt->close();
}

/* -------------------------
   ACTION: add (issue medicine)
   ------------------------- */
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_code = trim($_POST['item_code'] ?? '');
    $item_name = trim($_POST['item_name'] ?? '');
    $quantity_issued = intval($_POST['quantity_issued'] ?? 0);
    $issued_to = trim($_POST['issued_to'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');

    if ($item_code === '' || $item_name === '' || $quantity_issued <= 0) {
        $_SESSION['flash_error'] = 'Provide item code, item name and positive quantity.';
        header('Location: issuance.php?action=add'); exit;
    }

    $mysqli->begin_transaction();
    try {
        // find medicine master by item_code
        $mstmt = $mysqli->prepare('SELECT med_id, item_name FROM medicine WHERE item_code = ? LIMIT 1');
        if ($mstmt === false) throw new Exception('DB prepare failed: ' . $mysqli->error);
        $mstmt->bind_param('s', $item_code);
        $mstmt->execute();
        $mres = $mstmt->get_result();
        $medrow = $mres->fetch_assoc();
        $mstmt->close();
        if (!$medrow) throw new Exception('Medicine not found for given item code.');

        $med_id = (int)$medrow['med_id'];

        // compute total available across batches (non-expired)
        $bst = $mysqli->prepare("SELECT IFNULL(SUM(quantity_remaining),0) AS total FROM batches WHERE medicine_id = ? AND quantity_remaining > 0 AND (expiry_date IS NULL OR expiry_date >= CURDATE())");
        if ($bst === false) throw new Exception('DB prepare failed: ' . $mysqli->error);
        $bst->bind_param('i', $med_id);
        $bst->execute();
        $bres = $bst->get_result();
        $btotal = (int)($bres->fetch_assoc()['total'] ?? 0);
        $bst->close();

        if ($quantity_issued > $btotal) throw new Exception('Requested quantity exceeds available stock (' . $btotal . ').');

        // create issuance record (status default = Complete)
        $ist = $mysqli->prepare('INSERT INTO issuance (item_code,item_name,quantity_issued,issued_to,purpose,issued_by,date_issued,status) VALUES (?,?,?,?,?,?,NOW(),?)');
        if ($ist === false) throw new Exception('DB prepare failed: ' . $mysqli->error);
        $status_default = 'Complete';
        $ist->bind_param('ssissis', $item_code, $item_name, $quantity_issued, $issued_to, $purpose, $user_id, $status_default);
        $ist->execute();
        $issue_id = $ist->insert_id;
        $ist->close();

        // Deduct using FIFO from batches
        $remaining_to_deduct = $quantity_issued;
        $bst2 = $mysqli->prepare("SELECT id, quantity_remaining, date_received, created_at FROM batches WHERE medicine_id = ? AND quantity_remaining > 0 AND (expiry_date IS NULL OR expiry_date >= CURDATE()) ORDER BY date_received ASC, created_at ASC");
        if ($bst2 === false) throw new Exception('DB prepare failed: ' . $mysqli->error);
        $bst2->bind_param('i', $med_id);
        $bst2->execute();
        $res2 = $bst2->get_result();
        while ($row = $res2->fetch_assoc()) {
            if ($remaining_to_deduct <= 0) break;
            $batch_id = (int)$row['id'];
            $avail = (int)$row['quantity_remaining'];
            if ($avail <= 0) continue;
            $deduct = min($avail, $remaining_to_deduct);

            // update batch
            $ust = $mysqli->prepare('UPDATE batches SET quantity_remaining = quantity_remaining - ? WHERE id = ? LIMIT 1');
            if ($ust === false) throw new Exception('DB prepare failed: ' . $mysqli->error);
            $ust->bind_param('ii', $deduct, $batch_id);
            $ust->execute();
            $ust->close();

            // insert stock_movements OUT
            $insMove = $mysqli->prepare('INSERT INTO stock_movements (movement_type, medicine_id, batch_id, qty, unit, movement_date, note) VALUES (?,?,?,?,?,NOW(),?)');
            if ($insMove === false) throw new Exception('DB prepare failed: ' . $mysqli->error);
            $mvType = 'OUT';
            $unit = 'pcs';
            $ustu = $mysqli->prepare('SELECT unit FROM medicine WHERE med_id = ? LIMIT 1');
            if ($ustu !== false) { $ustu->bind_param('i',$med_id); $ustu->execute(); $ru=$ustu->get_result()->fetch_assoc(); if (!empty($ru['unit'])) $unit = $ru['unit']; $ustu->close(); }
            $note = 'Issued (issue_id=' . $issue_id . ')';
            $insMove->bind_param('siiiss', $mvType, $med_id, $batch_id, $deduct, $unit, $note);
            $insMove->execute();
            $insMove->close();

            $remaining_to_deduct -= $deduct;
        }
        $bst2->close();

        if ($remaining_to_deduct > 0) {
            throw new Exception('Failed to deduct full quantity from batches; remaining: ' . $remaining_to_deduct);
        }

        // update aggregated medicine.quantity if present
        $checkCol = $mysqli->query("SHOW COLUMNS FROM medicine LIKE 'quantity'");
        if ($checkCol && $checkCol->num_rows) {
            $updQty = $mysqli->prepare('UPDATE medicine SET quantity = IFNULL(quantity,0) - ? WHERE med_id = ?');
            if ($updQty === false) throw new Exception('DB prepare failed: ' . $mysqli->error);
            $updQty->bind_param('ii', $quantity_issued, $med_id);
            $updQty->execute();
            $updQty->close();
        }

        $mysqli->commit();
        log_activity($user_id, 'Issued ' . $quantity_issued . ' x ' . $item_name . ' to ' . $issued_to . ' (issue_id=' . $issue_id . ')');
        $_SESSION['flash_success'] = 'Issued successfully.';
        header('Location: issuance.php'); exit;
    } catch (Exception $e) {
        $mysqli->rollback();
        error_log('Issuance error: ' . $e->getMessage());
        $_SESSION['flash_error'] = 'Failed to issue: ' . htmlspecialchars($e->getMessage());
        header('Location: issuance.php?action=add'); exit;
    }
}

/* -------------------------
   ACTION: archive issuance (via POST)
   ------------------------- */
if ($action === 'archive' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $issue_id = intval($_POST['issue_id'] ?? 0);
    if ($issue_id <= 0) {
        $_SESSION['flash_error'] = 'Invalid issuance id.';
        header('Location: issuance.php'); exit;
    }
    $ust = $mysqli->prepare('UPDATE issuance SET status = ? WHERE issue_id = ?');
    if ($ust === false) { $_SESSION['flash_error'] = 'DB error.'; header('Location: issuance.php'); exit; }
    $st = 'Archived';
    $ust->bind_param('si', $st, $issue_id);
    $ust->execute();
    $ust->close();
    log_activity($user_id, 'Archived issuance ID ' . $issue_id);
    $_SESSION['flash_success'] = 'Issuance archived.';
    header('Location: issuance.php'); exit;
}

/* -------------------------
   ACTION: view single issuance (modal) - AJAX GET returns JSON
   ------------------------- */
if ($action === 'view' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // clear any accidental output, then send JSON
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    $issue_id = intval($_GET['id'] ?? 0);
    if ($issue_id <= 0) {
        echo json_encode(['success'=>false,'error'=>'invalid_id']); exit;
    }
    $stmt = $mysqli->prepare("SELECT i.*, u.name as issuer FROM issuance i LEFT JOIN users u ON i.issued_by = u.user_id WHERE i.issue_id = ? LIMIT 1");
    if ($stmt === false) { echo json_encode(['success'=>false,'error'=>'prepare_failed']); exit; }
    $stmt->bind_param('i', $issue_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $r = $res->fetch_assoc();
    $stmt->close();
    if (!$r) { echo json_encode(['success'=>false,'error'=>'not_found']); exit; }
    echo json_encode(['success'=>true,'record'=>$r]); exit;
}

/* -------------------------
   LISTING & filters (default: show Complete only)
   ------------------------- */
$month_filter = $_GET['month'] ?? ''; // YYYY-MM
$status_filter = $_GET['status'] ?? ''; // '', 'Complete', 'Archived', 'All'
$show_all = ($_GET['view_all'] ?? '') === '1';

// Build SQL and params
$sql = "SELECT i.*, u.name as issuer FROM issuance i LEFT JOIN users u ON i.issued_by = u.user_id WHERE 1=1";
$params = []; $types = '';

// status filter behavior:
// - if status_filter == 'Archived' -> show archived only
// - if status_filter == 'All' -> show everything
// - if empty -> default: show only 'Complete'
if ($status_filter === 'Archived') {
    $sql .= " AND i.status = ?";
    $params[] = 'Archived'; $types .= 's';
} elseif (strtoupper($status_filter) === 'ALL') {
    // no additional condition
} else {
    // default: only Complete rows
    $sql .= " AND COALESCE(i.status, 'Complete') = ?";
    $params[] = 'Complete'; $types .= 's';
}

if ($month_filter !== '') {
    $sql .= " AND DATE_FORMAT(i.date_issued, '%Y-%m') = ?";
    $params[] = $month_filter; $types .= 's';
}

$sql .= " ORDER BY i.date_issued DESC, i.issue_id DESC";

$stmt = $mysqli->prepare($sql);
if ($stmt === false) { die('Prepare failed: ' . $mysqli->error); }
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Issuance</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
  .table-card { background:#fff; border-radius:10px; padding:1rem; box-shadow:0 8px 24px rgba(0,0,0,0.06); }
  .modal-backdrops{position:fixed;inset:0;background:rgba(0,0,0,0.45);display:none;z-index:1050;align-items:center;justify-content:center;padding:1rem}
  .modal-backdrops.show{display:flex}
  .modal-panel{background:#fff;border-radius:10px;width:100%;max-width:920px;box-shadow:0 10px 30px rgba(0,0,0,0.25);z-index:1060;padding:1.25rem;max-height:90vh;overflow:auto}
  .btn-xs { padding:.25rem .45rem; font-size:.82rem; border-radius:6px; }
  .icon-btn { background:transparent;border:0;padding:.25rem;cursor:pointer;font-size:1rem; }
  .muted { color:#6b7280; }
  .badge-status { display:inline-block;padding:.25rem .5rem;border-radius:6px;font-weight:600;font-size:.82rem; }
  .badge-complete { background:#e7f7e7;color:#2b8a2b; }
  .badge-archived { background:#ffecec;color:#a80000; }
  .table-modern th, .table-modern td { padding:.6rem .5rem; border-bottom:1px solid #f1f3f5; vertical-align:middle; }
</style>
</head>
<body>
<div class="container-fluid py-3">
  <h3 style="margin-bottom:.5rem;">Issuance (Medicine)</h3>

  <?php if (!empty($_SESSION['flash_success'])): ?>
    <div style="padding:.6rem;border-radius:6px;background:#e6ffed;color:#065f2d;margin-bottom:.75rem;"><?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div style="padding:.6rem;border-radius:6px;background:#ffecec;color:#811b1b;margin-bottom:.75rem;"><?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
  <?php endif; ?>

  <div class="table-card">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div>
        <form method="get" class="d-flex" style="gap:8px; align-items:center;">
          <select name="status" class="form-control form-control-sm">
            <option value=""><?php echo htmlspecialchars('Default: Complete') ?></option>
            <option value="All" <?php if($status_filter==='All') echo 'selected' ?>>All</option>
            <option value="Complete" <?php if($status_filter==='Complete') echo 'selected' ?>>Complete</option>
            <option value="Archived" <?php if($status_filter==='Archived') echo 'selected' ?>>Archived</option>
          </select>

          <select name="month" class="form-control form-control-sm">
            <option value="">All months</option>
            <?php foreach ($month_options as $ym):
                $label = DateTime::createFromFormat('!Y-m', $ym) ? DateTime::createFromFormat('!Y-m', $ym)->format('F Y') : $ym;
            ?>
              <option value="<?php echo htmlspecialchars($ym) ?>" <?php if($month_filter===$ym) echo 'selected' ?>><?php echo htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
          </select>

          <button class="btn btn-sm btn-primary">Filter</button>
          <a href="issuance.php" class="btn btn-sm btn-outline-secondary">Reset</a>
        </form>
      </div>

      <div style="display:flex;gap:8px;align-items:center;">
        <a id="openIssueBtn" href="#" class="btn btn-sm btn-success"><i class="bi bi-plus-lg"></i> Issue Item</a>
        <button id="viewAllReportBtn" class="btn btn-sm btn-outline-dark"><i class="bi bi-file-earmark-text"></i> View All Issued Reports</button>
      </div>
    </div>

    <div class="table-responsive" style="margin-top:.75rem;">
      <table class="table table-modern w-100">
        <thead>
          <tr>
            <th>Item</th>
            <th>Qty</th>
            <th>To</th>
            <th>By</th>
            <th>Date</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="7">No records found.</td></tr>
          <?php else: foreach ($rows as $r): 
             $status = $r['status'] ?? 'Complete';
          ?>
            <tr>
              <td>
                <div style="font-weight:600;"><?php echo htmlspecialchars($r['item_name']) ?></div>
                <div style="color:#6c757d;font-size:.85rem;"><?php echo htmlspecialchars($r['item_code']) ?></div>
              </td>
              <td style="width:70px;"><?php echo (int)$r['quantity_issued'] ?></td>
              <td style="width:160px;"><?php echo htmlspecialchars($r['issued_to']) ?></td>
              <td style="width:140px;"><?php echo htmlspecialchars($r['issuer'] ?? '') ?></td>
              <td style="width:160px;"><?php echo htmlspecialchars($r['date_issued']) ?></td>
              <td style="width:110px;">
                <?php if (strtolower($status) === 'archived'): ?>
                  <span class="badge-status badge-archived">Archived</span>
                <?php else: ?>
                  <span class="badge-status badge-complete">Complete</span>
                <?php endif; ?>
              </td>
              <td style="width:120px;text-align:right;">
                <button class="icon-btn view-issue" data-id="<?php echo (int)$r['issue_id'] ?>" title="View"><i class="bi bi-eye"></i></button>
                <?php if (strtolower($status) !== 'archived'): ?>
                  <button class="icon-btn archive-issue" data-id="<?php echo (int)$r['issue_id'] ?>" title="Archive"><i class="bi bi-archive"></i></button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Issue modal (add) -->
<div id="issueModal" class="modal-backdrops" aria-hidden="true" role="dialog" aria-modal="true">
  <div class="modal-panel" role="document" style="max-width:720px;">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div style="font-weight:600">Issue Medicine</div>
      <button class="btn btn-sm btn-light" data-close-issue>&times;</button>
    </div>

    <form id="issueForm" method="post" action="issuance.php?action=add">
      <div class="row g-2">
        <div class="col-6"><label class="form-label">Item Code</label><input id="u_item_code" name="item_code" class="form-control" required></div>
        <div class="col-6"><label class="form-label">Item Name</label><input id="u_item_name" name="item_name" class="form-control" required></div>
        <div class="col-4"><label class="form-label">Quantity</label><input id="u_quantity_issued" name="quantity_issued" type="number" min="1" value="1" class="form-control" required></div>
        <div class="col-4"><label class="form-label">Issued To</label><input id="u_issued_to" name="issued_to" class="form-control"></div>
        <div class="col-4"><label class="form-label">Purpose</label><input id="u_purpose" name="purpose" class="form-control"></div>
        <div class="col-12"><div id="u_stock_row" style="margin-top:.35rem; font-size:.9rem; color:#495057; display:none;">Current stock: <strong id="u_stock_display">0</strong></div></div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-3">
        <button type="submit" class="btn btn-primary btn-sm">Issue</button>
        <button type="button" class="btn btn-secondary btn-sm" data-close-issue>Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- View Issuance modal (ajax filled) -->
<div id="viewIssueModal" class="modal-backdrops" aria-hidden="true" role="dialog" aria-modal="true">
  <div class="modal-panel" role="document" style="max-width:720px;">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div style="font-weight:600">Issuance Details</div>
      <button class="btn btn-sm btn-light" data-close-view-issue>&times;</button>
    </div>
    <div id="viewIssueBody">
      <div>Loading…</div>
    </div>
  </div>
</div>

<!-- View All Issued Reports modal (printable, landscape) -->
<div id="reportModal" class="modal-backdrops" aria-hidden="true" role="dialog" aria-modal="true">
  <div class="modal-panel" role="document" style="max-width:1200px; width:96%;">
    <div class="d-flex justify-content-between align-items-center mb-2" style="gap:12px;flex-wrap:wrap;">
      <div style="font-weight:600">All Issued Report</div>

      <div style="display:flex;align-items:center;gap:8px;">
        <select id="report_status" class="form-control form-control-sm">
          <option value="Complete">Complete</option>
          <option value="All">All</option>
          <option value="Archived">Archived</option>
        </select>

        <select id="report_month" class="form-control form-control-sm">
          <option value="">All months</option>
          <?php foreach ($month_options as $ym):
              $label = DateTime::createFromFormat('!Y-m', $ym) ? DateTime::createFromFormat('!Y-m', $ym)->format('F Y') : $ym;
          ?>
            <option value="<?php echo htmlspecialchars($ym) ?>"><?php echo htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>

        <?php
        $years = [];
        foreach ($month_options as $ym) {
          if (preg_match('/^(\d{4})-/', $ym, $m)) $years[] = $m[1];
        }
        $years = array_values(array_unique($years));
        if (empty($years)) $years[] = date('Y');
        sort($years);
        ?>
        <select id="report_year" class="form-control form-control-sm">
          <option value="">All years</option>
          <?php foreach ($years as $y): ?>
            <option value="<?php echo htmlspecialchars($y) ?>"><?php echo htmlspecialchars($y) ?></option>
          <?php endforeach; ?>
        </select>

        <button id="printReportBtn" class="btn btn-sm btn-primary">Print (Landscape)</button>
        <button class="btn btn-sm btn-outline-secondary" data-close-report>Close</button>
      </div>
    </div>

    <div id="reportBody" style="overflow:auto; max-height:70vh;">
      <table id="reportTable" class="print-table" style="width:100%; border-collapse:collapse;">
        <thead>
          <tr>
            <th style="padding:6px;border:1px solid #ddd;">Issue ID</th>
            <th style="padding:6px;border:1px solid #ddd;">Item</th>
            <th style="padding:6px;border:1px solid #ddd;">Qty</th>
            <th style="padding:6px;border:1px solid #ddd;">Issued To</th>
            <th style="padding:6px;border:1px solid #ddd;">By</th>
            <th style="padding:6px;border:1px solid #ddd;">Date</th>
            <th style="padding:6px;border:1px solid #ddd;">Purpose</th>
            <th style="padding:6px;border:1px solid #ddd;">Status</th>
          </tr>
        </thead>
        <tbody id="report_tbody">
          <?php if (!empty($rows)): foreach ($rows as $r):
              $d = $r['date_issued'] ?? '';
              $d_short = $d ? substr($d,0,10) : '';
              $d_month = $d_short ? substr($d_short,0,7) : '';
              $d_year = $d_short ? substr($d_short,0,4) : '';
              $status = $r['status'] ?? 'Complete';
          ?>
            <tr data-date="<?php echo htmlspecialchars($d_short)?>" data-month="<?php echo htmlspecialchars($d_month)?>" data-year="<?php echo htmlspecialchars($d_year)?>" data-status="<?php echo htmlspecialchars($status)?>">
              <td style="padding:6px;border:1px solid #ddd;"><?php echo (int)$r['issue_id'] ?></td>
              <td style="padding:6px;border:1px solid #ddd;"><?php echo htmlspecialchars($r['item_name']) ?></td>
              <td style="padding:6px;border:1px solid #ddd;"><?php echo (int)$r['quantity_issued'] ?></td>
              <td style="padding:6px;border:1px solid #ddd;"><?php echo htmlspecialchars($r['issued_to']) ?></td>
              <td style="padding:6px;border:1px solid #ddd;"><?php echo htmlspecialchars($r['issuer'] ?? '') ?></td>
              <td style="padding:6px;border:1px solid #ddd;"><?php echo htmlspecialchars($r['date_issued']) ?></td>
              <td style="padding:6px;border:1px solid #ddd;"><?php echo htmlspecialchars($r['purpose'] ?? '') ?></td>
              <td style="padding:6px;border:1px solid #ddd;"><?php echo htmlspecialchars($status) ?></td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="8" style="padding:12px;text-align:center;">No records to show.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
(function(){
  const body = document.body;
  function openModal(el){ el.classList.add('show'); body.classList.add('modal-open'); el.setAttribute('aria-hidden','false'); }
  function closeModal(el){ el.classList.remove('show'); body.classList.remove('modal-open'); el.setAttribute('aria-hidden','true'); }

  // issue modal
  const issueModal = document.getElementById('issueModal');
  document.getElementById('openIssueBtn').addEventListener('click', function(e){
    e.preventDefault();
    document.getElementById('issueForm').reset();
    document.getElementById('u_stock_row').style.display='none';
    openModal(issueModal);
  });
  document.querySelectorAll('[data-close-issue]').forEach(btn=> btn.addEventListener('click', ()=> closeModal(issueModal)));
  issueModal.addEventListener('click', (e)=> { if (e.target === issueModal) closeModal(issueModal); });

  // lookup stock when typing code (uses ajax_get_item.php)
  const codeEl = document.getElementById('u_item_code');
  const nameEl = document.getElementById('u_item_name');
  const stockRow = document.getElementById('u_stock_row');
  const stockDisplay = document.getElementById('u_stock_display');

  let lookupTimer;
  function debounce(fn, delay){ clearTimeout(lookupTimer); lookupTimer = setTimeout(fn, delay); }
  async function fetchItem(item_code) {
    if (!item_code) return null;
    try {
      const p = new URLSearchParams({ category: 'Medicine', item_code: item_code });
      const resp = await fetch('ajax_get_item.php?' + p.toString(), { credentials:'same-origin' });
      if (!resp.ok) return null;
      const j = await resp.json();
      return j;
    } catch(e) { return null; }
  }

  if (codeEl) codeEl.addEventListener('input', ()=> {
    debounce(async ()=> {
      const code = (codeEl.value||'').trim();
      if (!code) { nameEl.value=''; stockRow.style.display='none'; return; }
      nameEl.value = 'Searching...';
      const result = await fetchItem(code);
      if (result && result.success) {
        nameEl.value = result.item_name || '';
        stockDisplay.textContent = String(result.quantity ?? 0);
        stockRow.style.display = 'block';
      } else {
        nameEl.value = '';
        stockRow.style.display='none';
      }
    }, 250);
  });

  // view issuance (ajax)
  const viewIssueModal = document.getElementById('viewIssueModal');
  const viewIssueBody = document.getElementById('viewIssueBody');
  document.querySelectorAll('.view-issue').forEach(btn=>{
    btn.addEventListener('click', async function(e){
      e.preventDefault();
      const id = this.dataset.id;
      openModal(viewIssueModal);
      viewIssueBody.innerHTML = 'Loading…';
      try {
        const resp = await fetch('issuance.php?action=view&id=' + encodeURIComponent(id), { credentials:'same-origin' });
        if (!resp.ok) throw new Error('network');
        const j = await resp.json();
        if (!j.success) throw new Error(j.error || 'failed');
        const r = j.record;
        let html = '<dl style="display:grid;grid-template-columns:120px 1fr;gap:8px;">';
        html += '<dt class="muted">Issue ID</dt><dd>#' + (r.issue_id||'') + '</dd>';
        html += '<dt class="muted">Item</dt><dd>' + escapeHtml(r.item_name||'') + '</dd>';
        html += '<dt class="muted">Qty</dt><dd>' + (r.quantity_issued||'') + '</dd>';
        html += '<dt class="muted">Issued To</dt><dd>' + escapeHtml(r.issued_to||'') + '</dd>';
        html += '<dt class="muted">Issued By</dt><dd>' + escapeHtml(r.issuer||'') + '</dd>';
        html += '<dt class="muted">Date</dt><dd>' + escapeHtml(r.date_issued||'') + '</dd>';
        html += '<dt class="muted">Purpose</dt><dd>' + escapeHtml(r.purpose||'') + '</dd>';
        html += '<dt class="muted">Status</dt><dd>' + escapeHtml(r.status||'') + '</dd>';
        html += '</dl>';
        viewIssueBody.innerHTML = html;
      } catch (err) {
        viewIssueBody.innerHTML = '<div style="color:#a94442;">Failed to load record: ' + String(err) + '</div>';
      }
    });
  });
  document.querySelectorAll('[data-close-view-issue]').forEach(btn=> btn.addEventListener('click', ()=> closeModal(viewIssueModal)));
  viewIssueModal.addEventListener('click', (e)=> { if (e.target === viewIssueModal) closeModal(viewIssueModal); });

  // archive issuance (post)
  document.querySelectorAll('.archive-issue').forEach(btn=>{
    btn.addEventListener('click', function(e){
      e.preventDefault();
      const id = this.dataset.id;
      if (!confirm('Archive this issuance?')) return;
      const form = new FormData();
      form.append('issue_id', id);
      fetch('issuance.php?action=archive', { method:'POST', body: form, credentials:'same-origin' })
        .then(resp => {
          if (!resp.ok) throw new Error('network');
          location.reload();
        }).catch(err => alert('Failed to archive: ' + err));
    });
  });

  // Report modal & printing
  const reportModal = document.getElementById('reportModal');
  const reportMonth = document.getElementById('report_month');
  const reportYear = document.getElementById('report_year');
  const reportStatus = document.getElementById('report_status');
  const viewAllReportBtn = document.getElementById('viewAllReportBtn');
  const printBtn = document.getElementById('printReportBtn');
  const reportTbody = document.getElementById('report_tbody');

  viewAllReportBtn.addEventListener('click', function(e){
    e.preventDefault();
    openModal(reportModal);
    reportMonth.value = '';
    reportYear.value = '';
    reportStatus.value = 'Complete';
    filterReportRows();
  });
  document.querySelectorAll('[data-close-report]').forEach(btn=> btn.addEventListener('click', ()=> closeModal(reportModal)));
  reportModal.addEventListener('click', (e)=> { if (e.target === reportModal) closeModal(reportModal); });

  function filterReportRows(){
    const selMonth = (reportMonth.value || '').trim();
    const selYear = (reportYear.value || '').trim();
    const selStatus = (reportStatus.value || '').trim();
    const rows = Array.from(reportTbody.querySelectorAll('tr'));
    let visibleCount = 0;
    for (const tr of rows) {
      const rowMonth = tr.getAttribute('data-month') || '';
      const rowYear = tr.getAttribute('data-year') || '';
      const rowStatus = tr.getAttribute('data-status') || '';
      let show = true;
      if (selMonth && rowMonth !== selMonth) show = false;
      if (selYear && rowYear !== selYear) show = false;
      if (selStatus && selStatus !== 'All' && rowStatus !== selStatus) show = false;
      tr.style.display = show ? '' : 'none';
      if (show) visibleCount++;
    }
    if (visibleCount === 0) {
      if (!reportTbody.querySelector('.no-match')) {
        const r = document.createElement('tr'); r.className='no-match';
        r.innerHTML = '<td colspan="8" style="padding:12px;text-align:center;">No records for selected filters.</td>';
        reportTbody.appendChild(r);
      }
    } else {
      const nm = reportTbody.querySelector('.no-match'); if (nm) nm.remove();
    }
  }
  reportMonth.addEventListener('change', filterReportRows);
  reportYear.addEventListener('change', filterReportRows);
  reportStatus.addEventListener('change', filterReportRows);

  // print function (landscape)
  printBtn.addEventListener('click', function(){
    const table = document.createElement('table'); table.style.borderCollapse='collapse'; table.style.width='100%';
    table.innerHTML = document.querySelector('#reportTable thead').outerHTML;
    const rows = Array.from(reportTbody.querySelectorAll('tr')).filter(r=> r.style.display !== 'none');
    const tbody = document.createElement('tbody');
    if (rows.length === 0) {
      const tr = document.createElement('tr'); tr.innerHTML = '<td colspan="8" style="padding:12px;text-align:center;">No records to print.</td>'; tbody.appendChild(tr);
    } else {
      for (const r of rows) tbody.appendChild(r.cloneNode(true));
    }
    table.appendChild(tbody);
    const w = window.open('', '_blank', 'width=1200,height=800,scrollbars=yes');
    const doc = w.document; doc.open();
    doc.write('<!doctype html><html><head><meta charset="utf-8"><title>Issued Report</title>');
    doc.write('<style>@page { size: A4 landscape; margin: 12mm; } body{font-family:Arial,Helvetica,sans-serif;font-size:12px;margin:0;padding:8px;} table{border-collapse:collapse;width:100%;} table th, table td{border:1px solid #999;padding:6px;text-align:left;}</style>');
    doc.write('</head><body>');
    doc.write('<h3>Issued Report</h3>');
    doc.write(table.outerHTML);
    doc.write('</body></html>');
    doc.close(); w.focus();
    setTimeout(()=> { w.print(); }, 400);
  });

  // helper escape
  function escapeHtml(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
})();
</script>
</body>
</html>
