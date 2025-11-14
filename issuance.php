<?php

require_once 'config.php';
require_once 'header.php';
require_once 'functions.php';

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: login.php'); exit;
}

$action = $_GET['action'] ?? 'list';

// categories available for issuance filter
$categories = ['Medicine', 'Supply'];

// fetch distinct months present in issuance (format: YYYY-MM) for the month filter dropdown
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

// --- Server-side CRUD / Issue logic ---
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = $_POST['category'] ?? '';
    $item_code = trim($_POST['item_code'] ?? '');
    $item_name = $_POST['item_name'] ?? '';
    $quantity_issued = intval($_POST['quantity_issued'] ?? 0);
    $issued_to = $_POST['issued_to'] ?? '';
    $purpose = $_POST['purpose'] ?? '';

    // normalize category
    $is_medicine = (strtolower(trim($category)) === 'medicine');
    $table = $is_medicine ? 'medicine' : 'supplies';

    // server-side: fetch current stock (authoritative)
    $stock_stmt = $mysqli->prepare("SELECT quantity FROM `$table` WHERE item_code = ? LIMIT 1");
    if ($stock_stmt === false) { die('Prepare failed: ' . $mysqli->error); }
    $stock_stmt->bind_param('s', $item_code);
    $stock_stmt->execute();
    $stock_res = $stock_stmt->get_result();
    $current_stock = null;
    if ($row = $stock_res->fetch_assoc()) {
        $current_stock = (int)$row['quantity'];
    }
    $stock_stmt->close();

    if ($current_stock === null) {
        // item not found
        $_SESSION['flash_error'] = 'Item code not found for selected category.';
        header('Location: issuance.php?action=add'); exit;
    }

    if ($quantity_issued <= 0) {
        $_SESSION['flash_error'] = 'Invalid quantity.';
        header('Location: issuance.php?action=add'); exit;
    }

    if ($quantity_issued > $current_stock) {
        $_SESSION['flash_error'] = 'Requested quantity (' . $quantity_issued . ') exceeds current stock (' . $current_stock . ').';
        header('Location: issuance.php?action=add'); exit;
    }

    // proceed with insertion
    $stmt = $mysqli->prepare('INSERT INTO issuance (category,item_code,item_name,quantity_issued,issued_to,purpose,issued_by,date_issued) VALUES (?,?,?,?,?,?,?,NOW())');
    if ($stmt === false) { die('Prepare failed: ' . $mysqli->error); }
    $stmt->bind_param('sssissi', $category, $item_code, $item_name, $quantity_issued, $issued_to, $purpose, $user_id);
    $stmt->execute();
    $stmt->close();

    // Deduct from stock (server-side)
    if ($is_medicine) {
        $ust = $mysqli->prepare('UPDATE medicine SET quantity = quantity - ? WHERE item_code = ? LIMIT 1');
        if ($ust !== false) { $ust->bind_param('is', $quantity_issued, $item_code); $ust->execute(); $ust->close(); }
    } else {
        $ust = $mysqli->prepare('UPDATE supplies SET quantity = quantity - ? WHERE item_code = ? LIMIT 1');
        if ($ust !== false) { $ust->bind_param('is', $quantity_issued, $item_code); $ust->execute(); $ust->close(); }
    }

    log_activity($user_id, 'Issued ' . $quantity_issued . ' x ' . $item_name . ' to ' . $issued_to);
    $_SESSION['flash_success'] = 'Issued successfully.';
    header('Location: issuance.php'); exit;
}

if ($action === 'delete') {
    $id = intval($_GET['id'] ?? 0);
    $stmt = $mysqli->prepare('DELETE FROM issuance WHERE issue_id = ?');
    if ($stmt === false) { die('Prepare failed: ' . $mysqli->error); }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    log_activity($user_id, 'Deleted issuance ID ' . $id);
    $_SESSION['flash_success'] = 'Issuance record deleted.';
    header('Location: issuance.php'); exit;
}

// Listing (default) with filters: category and month (YYYY-MM)
$category_filter = $_GET['category'] ?? '';
$month_filter = $_GET['month'] ?? ''; // expected format: YYYY-MM

$sql = "SELECT i.*, u.name as issuer FROM issuance i LEFT JOIN users u ON i.issued_by = u.user_id WHERE 1=1";
$params = [];
$types = '';

if ($category_filter !== '') {
    $sql .= " AND i.category = ?";
    $params[] = $category_filter;
    $types .= 's';
}
if ($month_filter !== '') {
    $sql .= " AND DATE_FORMAT(i.date_issued, '%Y-%m') = ?";
    $params[] = $month_filter;
    $types .= 's';
}

$sql .= " ORDER BY i.date_issued DESC, i.issue_id DESC";

$stmt = $mysqli->prepare($sql);
if ($stmt === false) { die('Prepare failed: ' . $mysqli->error); }
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!-- Styles: consistent card/table/modal system -->
<style>
  .table-card { background:#fff; border-radius:10px; padding:1rem; box-shadow:0 8px 24px rgba(0,0,0,0.06); }
  .d-flex{display:flex}.justify-content-between{justify-content:space-between}.align-items-center{align-items:center}
  .mb-2{margin-bottom:.5rem}.btn{display:inline-flex;align-items:center;gap:.5rem;padding:.375rem .6rem;border-radius:6px;border:1px solid transparent;text-decoration:none;cursor:pointer}
  .btn-sm{font-size:.85rem;padding:.275rem .5rem;border-radius:6px}
  .btn-primary{background:#0d6efd;color:#fff;border-color:#0d6efd}
  .btn-success{background:#198754;color:#fff;border-color:#198754}
  .btn-secondary{background:#6c757d;color:#fff;border-color:#6c757d}
  .btn-danger{background:#dc3545;color:#fff;border-color:#dc3545}
  .btn-outline-secondary{background:transparent;color:#495057;border-color:#ced4da}

  .badge-med{display:inline-block;padding:.25rem .5rem;border-radius:999px;background:#e6f4ff;color:#0b5bd7;font-weight:600;font-size:.82rem}
  .badge-sup{display:inline-block;padding:.25rem .5rem;border-radius:999px;background:#eaf6ea;color:#0b7a3a;font-weight:600;font-size:.82rem}

  .modal-backdrops{position:fixed;inset:0;background:rgba(0,0,0,0.45);display:none;z-index:1050;align-items:center;justify-content:center;padding:1rem}
  .modal-backdrops.show{display:flex}
  .modal-panel{background:#fff;border-radius:10px;width:100%;max-width:720px;box-shadow:0 10px 30px rgba(0,0,0,0.25);z-index:1060;padding:1.25rem;max-height:90vh;overflow:auto}
  .modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem}
  .modal-title{font-weight:600;font-size:1.1rem}
  .modal-close{background:none;border:0;font-size:1.4rem;cursor:pointer}
  .form-row{display:flex;gap:12px;flex-wrap:wrap}
  .form-row .form-group{flex:1;min-width:180px}
  .btn-modal{margin-right:8px}
  body.modal-open{overflow:hidden}
  @media (max-width:600px){.modal-panel{padding:.75rem;border-radius:8px}}
  label{display:block;font-size:.9rem;margin-bottom:.25rem;color:#333}
  input.form-control,select.form-control,textarea.form-control{width:100%;padding:.45rem .5rem;border:1px solid #dfe3e6;border-radius:6px}
  textarea.form-control{min-height:84px;resize:vertical}
  .table-modern{width:100%;border-collapse:collapse}
  .table-modern th, .table-modern td {padding:.6rem .5rem;border-bottom:1px solid #f1f3f5;text-align:left;vertical-align:middle}
</style>

<h3 style="margin-bottom:.5rem;">Medicine and Supply Issuance</h3>

<?php if (!empty($_SESSION['flash_success'])): ?>
  <div style="padding:.6rem;border-radius:6px;background:#e6ffed;color:#065f2d;margin-bottom:.75rem;"><?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_error'])): ?>
  <div style="padding:.6rem;border-radius:6px;background:#ffecec;color:#811b1b;margin-bottom:.75rem;"><?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
<?php endif; ?>

<div class="table-card">
  <div class="d-flex justify-content-between align-items-center">
    <div class="table-actions">
      <form method="get" class="d-flex" style="gap:8px; align-items:center;">
        <!-- Category filter -->
        <select name="category" class="form-control form-control-sm">
          <option value="">All categories</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?php echo htmlspecialchars($c) ?>" <?php if($category_filter===$c) echo 'selected' ?>><?php echo htmlspecialchars($c) ?></option>
          <?php endforeach; ?>
        </select>

        <!-- Month filter (YYYY-MM) -->
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

    <div>
      <a id="openUnifiedBtn" href="issuance.php?action=add" class="btn btn-sm btn-success"><span>＋</span> Issue Item</a>
    </div>
  </div>

   <div class="table-responsive" style="margin-top:.75rem;">
    <table class="table table-modern w-100">
      <thead>
        <tr>
          <th>Category</th>
          <th>Item</th>
          <th>Qty</th>
          <th>To</th>
          <th>By</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="7">No records found.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <td style="width:110px;">
              <?php
                $cat = $r['category'] ?? '';
                if (strtolower($cat) === 'medicine') echo '<span class="badge-med">Medicine</span>';
                else echo '<span class="badge-sup">Supply</span>';
              ?>
            </td>
            <td>
              <div style="font-weight:600;"><?php echo htmlspecialchars($r['item_name']) ?></div>
              <div style="color:#6c757d;font-size:.85rem;"><?php echo htmlspecialchars($r['item_code']) ?></div>
            </td>
            <td style="width:70px;"><?php echo (int)$r['quantity_issued'] ?></td>
            <td style="width:160px;"><?php echo htmlspecialchars($r['issued_to']) ?></td>
            <td style="width:140px;"><?php echo htmlspecialchars($r['issuer'] ?? '') ?></td>
            <td style="width:160px;"><?php echo htmlspecialchars($r['date_issued']) ?></td>
            <td style="width:120px;">
              <div style="display:flex;gap:8px;align-items:center;justify-content:flex-end;">
                <a class="btn btn-sm btn-danger" href="issuance.php?action=delete&id=<?php echo $r['issue_id']?>" onclick="return confirm('Delete?')">Delete</a>
              </div>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

</div>

<!-- Unified Modal for Issue -->
<div id="unifiedModal" class="modal-backdrops" aria-hidden="true" role="dialog" aria-modal="true">
  <div class="modal-panel" role="document">
    <div class="modal-header">
      <div id="unifiedTitle" class="modal-title">Issue Item</div>
      <button class="modal-close" data-close aria-label="Close">&times;</button>
    </div>

    <form id="unifiedForm" method="post" action="issuance.php?action=add">
      <input type="hidden" id="mode_field" name="mode" value="add">

      <div class="form-row">
        <div class="form-group mb-2">
          <label for="u_category">Category</label>
          <select id="u_category" name="category" class="form-control" required>
            <option>Medicine</option>
            <option>Supply</option>
          </select>
        </div>
        <div class="form-group mb-2">
          <label for="u_item_code">Item Code</label>
          <input id="u_item_code" name="item_code" class="form-control" autocomplete="off" />
        </div>
      </div>

      <div class="form-row">
        <div class="form-group mb-2" style="position:relative;">
          <label for="u_item_name">Item Name</label>
          <input id="u_item_name" name="item_name" class="form-control" required />
          <div id="u_stock_row" style="margin-top:.35rem; font-size:.9rem; color:#495057; display:none;">
            Current stock: <strong id="u_stock_display">0</strong>
          </div>
          <input type="hidden" id="u_current_stock" name="current_stock" value="0" />
        </div>
        <div class="form-group mb-2">
          <label for="u_quantity_issued">Quantity</label>
          <input id="u_quantity_issued" type="number" name="quantity_issued" class="form-control" value="1" min="1" />
        </div>
      </div>

      <div class="form-row">
        <div class="form-group mb-2" style="flex:1 1 60%;">
          <label for="u_issued_to">Issued To</label>
          <input id="u_issued_to" name="issued_to" class="form-control" />
        </div>
        <div class="form-group mb-2" style="flex:1 1 40%;">
          <label for="u_purpose">Purpose</label>
          <input id="u_purpose" name="purpose" class="form-control" />
        </div>
      </div>

      <div style="margin-top:.5rem;" class="d-flex justify-content-end">
        <button id="u_submit" type="submit" class="btn btn-primary btn-modal">Issue</button>
        <button type="button" class="btn btn-secondary" data-close>Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Non-JS fallback (page form) -->
<?php if ($action === 'add' && empty($_POST)): ?>
  <h3 style="margin-top:1rem;">Issue Item (No JS)</h3>
  <form method="post">
    <div class="mb-2">
      <label>Category</label>
      <select name="category" class="form-control" required>
        <option>Medicine</option><option>Supply</option>
      </select>
    </div>
    <div class="mb-2"><label>Item Code</label><input name="item_code" class="form-control"></div>
    <div class="mb-2"><label>Item Name</label><input name="item_name" class="form-control" required></div>
    <div class="mb-2"><label>Quantity</label><input type="number" name="quantity_issued" class="form-control" value="1" min="1"></div>
    <div class="mb-2"><label>Issued To</label><input name="issued_to" class="form-control"></div>
    <div class="mb-2"><label>Purpose</label><textarea name="purpose" class="form-control"></textarea></div>
    <button class="btn btn-primary">Issue</button>
    <a href="issuance.php" class="btn btn-secondary">Cancel</a>
  </form>
<?php endif; ?>

<!-- Modal JS (open/close, set action & title, and lookup logic) -->
<script>
(function(){
  const body = document.body;
  const modal = document.getElementById('unifiedModal');
  const form = document.getElementById('unifiedForm');
  const title = document.getElementById('unifiedTitle');
  const submitBtn = document.getElementById('u_submit');

  function openModal() {
    modal.classList.add('show');
    body.classList.add('modal-open');
    modal.setAttribute('aria-hidden','false');
    const first = document.getElementById('u_item_name');
    if(first) { setTimeout(()=> first.focus(),50); }
  }
  function closeModal() {
    modal.classList.remove('show');
    body.classList.remove('modal-open');
    modal.setAttribute('aria-hidden','true');
    const modeField = document.getElementById('mode_field');
    if(modeField) modeField.value = 'add';
    form.action = 'issuance.php?action=add';
    title.textContent = 'Issue Item';
    submitBtn && (submitBtn.textContent = 'Issue');
    form.reset();
    // hide stock row
    const stockRow = document.getElementById('u_stock_row');
    if (stockRow) stockRow.style.display = 'none';
    const existingWarn = document.getElementById('u_qty_warn');
    if (existingWarn) existingWarn.remove();
    submitBtn.disabled = false;
  }

  // wire close buttons
  document.querySelectorAll('[data-close]').forEach(btn=> btn.addEventListener('click', ()=> closeModal()));

  // clicking backdrop outside panel closes modal
  modal.addEventListener('click', function(e){ if(e.target === this) closeModal(); });

  // Open Add modal
  const openBtn = document.getElementById('openUnifiedBtn');
  if(openBtn) openBtn.addEventListener('click', function(e){
    e.preventDefault();
    form.reset();
    title.textContent = 'Issue Item';
    form.action = 'issuance.php?action=add';
    document.getElementById('mode_field').value = 'add';
    submitBtn && (submitBtn.textContent = 'Issue');
    openModal();
  });

  // On page load, open modal if ?action=add (fallback)
  const urlParams = new URLSearchParams(window.location.search);
  const action = urlParams.get('action');
  if(action === 'add') {
    form.reset();
    form.action = 'issuance.php?action=add';
    title.textContent = 'Issue Item';
    submitBtn && (submitBtn.textContent = 'Issue');
    openModal();
  }

  // Allow Escape key to close modal
  document.addEventListener('keydown', function(e){
    if(e.key === 'Escape' && modal.classList.contains('show')) closeModal();
  });

  // --- Lookup and validation logic ---

  // Helper - query DOM elements
  const itemCodeEl = document.getElementById('u_item_code');
  const categoryEl = document.getElementById('u_category');
  const itemNameEl = document.getElementById('u_item_name');
  const stockDisplayEl = document.getElementById('u_stock_display');
  const stockRowEl = document.getElementById('u_stock_row');
  const currentStockInput = document.getElementById('u_current_stock');
  const qtyEl = document.getElementById('u_quantity_issued');

  // debounce helper
  function debounce(fn, delay) {
    let t;
    return (...args) => {
      clearTimeout(t);
      t = setTimeout(()=> fn(...args), delay);
    };
  }

  async function fetchItemInfo(category, itemCode) {
    if (!itemCode) {
      return { success: false, error: 'empty' };
    }

    try {
      const params = new URLSearchParams({ category: category || '', item_code: itemCode });
      const resp = await fetch('ajax_get_item.php?' + params.toString(), { credentials: 'same-origin' });
      if (!resp.ok) {
        return { success: false, error: 'http' };
      }
      const data = await resp.json();
      return data;
    } catch (err) {
      return { success: false, error: 'network', detail: err.toString() };
    }
  }

  function showStock(quantity) {
    stockRowEl.style.display = 'block';
    stockDisplayEl.textContent = String(quantity);
    currentStockInput.value = String(quantity);
  }
  function hideStock() {
    stockRowEl.style.display = 'none';
    stockDisplayEl.textContent = '0';
    currentStockInput.value = '0';
  }

  const populateFromCode = debounce(async function() {
    const code = (itemCodeEl && itemCodeEl.value || '').trim();
    const category = (categoryEl && categoryEl.value || '').trim();

    if (!code) { itemNameEl.value = ''; hideStock(); validateQuantity(); return; }

    itemNameEl.value = 'Searching...';
    const result = await fetchItemInfo(category, code);

    if (result && result.success) {
      itemNameEl.value = result.item_name || '';
      showStock(result.quantity ?? 0);
    } else {
      itemNameEl.value = '';
      hideStock();
      if (result && result.error === 'not_found') {
        itemNameEl.placeholder = 'Item code not found';
      }
    }
    validateQuantity();
  }, 250);

  function validateQuantity() {
    const q = parseInt(qtyEl.value || '0', 10);
    const stock = parseInt(currentStockInput.value || '0', 10);

    const existingWarn = document.getElementById('u_qty_warn');
    if (existingWarn) existingWarn.remove();

    if (!isFinite(q) || q <= 0) {
      submitBtn.disabled = false;
      return;
    }

    if (stock === 0) {
      const warn = document.createElement('div');
      warn.id = 'u_qty_warn';
      warn.style.color = '#a94442';
      warn.style.marginTop = '.35rem';
      warn.style.fontSize = '.9rem';
      warn.textContent = 'Cannot issue: no stock available.';
      qtyEl.parentNode.appendChild(warn);
      submitBtn.disabled = true;
      return;
    }

    if (q > stock) {
      const warn = document.createElement('div');
      warn.id = 'u_qty_warn';
      warn.style.color = '#a94442';
      warn.style.marginTop = '.35rem';
      warn.style.fontSize = '.9rem';
      warn.textContent = 'Quantity exceeds current stock (' + stock + ').';
      qtyEl.parentNode.appendChild(warn);
      submitBtn.disabled = true;
      return;
    }

    submitBtn.disabled = false;
  }

  if (itemCodeEl) {
    itemCodeEl.addEventListener('input', populateFromCode);
    itemCodeEl.addEventListener('blur', populateFromCode);
  }
  if (categoryEl) {
    categoryEl.addEventListener('change', function(){ populateFromCode(); });
  }
  if (qtyEl) {
    qtyEl.addEventListener('input', validateQuantity);
    qtyEl.addEventListener('change', validateQuantity);
  }

})();
</script>
