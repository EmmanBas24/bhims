<?php
require_once 'header.php';
require_once 'functions.php';
$user_id = $_SESSION['user_id'];

$action = $_GET['action'] ?? 'list';

// --- Server-side CRUD / Issue logic ---
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = $_POST['category'] ?? '';
    $item_code = $_POST['item_code'] ?? '';
    $item_name = $_POST['item_name'] ?? '';
    $quantity_issued = intval($_POST['quantity_issued'] ?? 0);
    $issued_to = $_POST['issued_to'] ?? '';
    $purpose = $_POST['purpose'] ?? '';

    // Insert issuance with date_issued = NOW()
    $stmt = $mysqli->prepare('INSERT INTO issuance (category,item_code,item_name,quantity_issued,issued_to,purpose,issued_by,date_issued) VALUES (?,?,?,?,?,?,?,NOW())');
    if ($stmt === false) { die('Prepare failed: ' . $mysqli->error); }
    // types: s, s, s, i, s, s, i
    $stmt->bind_param('sssissi', $category, $item_code, $item_name, $quantity_issued, $issued_to, $purpose, $user_id);
    $stmt->execute();
    $stmt->close();

    // Deduct from stock depending on category
    if (strtolower($category) === 'medicine') {
        $stmt = $mysqli->prepare('UPDATE medicine SET quantity = quantity - ? WHERE item_code = ? LIMIT 1');
        if ($stmt !== false) {
            $stmt->bind_param('is', $quantity_issued, $item_code);
            $stmt->execute();
            $stmt->close();
        }
    } else {
        // assume supplies for anything else
        $stmt = $mysqli->prepare('UPDATE supplies SET quantity = quantity - ? WHERE item_code = ? LIMIT 1');
        if ($stmt !== false) {
            $stmt->bind_param('is', $quantity_issued, $item_code);
            $stmt->execute();
            $stmt->close();
        }
    }

    log_activity($user_id, 'Issued ' . $quantity_issued . ' x ' . $item_name . ' to ' . $issued_to);
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
    header('Location: issuance.php'); exit;
}

// Listing (default)
$stmt = $mysqli->prepare('SELECT i.*, u.name as issuer FROM issuance i LEFT JOIN users u ON i.issued_by = u.user_id ORDER BY date_issued DESC');
if ($stmt === false) { die('Prepare failed: ' . $mysqli->error); }
$stmt->execute(); $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
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

  .table{width:100%;border-collapse:collapse;font-size:.95rem}
  .table thead th{padding:.6rem .75rem;border-bottom:1px solid #e9ecef;color:#495057;font-weight:600;text-align:left}
  .table tbody td{padding:.6rem .75rem;border-bottom:1px solid #f1f3f5;vertical-align:middle}
  .table tr:hover td{background:#fbfdff}

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
</style>

<h3 style="margin-bottom:.5rem;">Medicine and Supply Issuance</h3>

<div class="table-card">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <div class="table-actions">
      <!-- placeholder for filters/search -->
    </div>

    <div>
      <a id="openUnifiedBtn" href="issuance.php?action=add" class="btn btn-sm btn-success"><span>＋</span> Issue Item</a>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-sm">
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
            <td>
              <?php
                $cat = $r['category'] ?? '';
                if (strtolower($cat) === 'medicine') echo '<span class="badge-med">Medicine</span>';
                else echo '<span class="badge-sup">Supply</span>';
              ?>
            </td>
            <td><?php echo htmlspecialchars($r['item_name']) ?> <div style="color:#6c757d;font-size:.85rem;"><?php echo htmlspecialchars($r['item_code']) ?></div></td>
            <td><?php echo (int)$r['quantity_issued'] ?></td>
            <td><?php echo htmlspecialchars($r['issued_to']) ?></td>
            <td><?php echo htmlspecialchars($r['issuer'] ?? '') ?></td>
            <td><?php echo htmlspecialchars($r['date_issued']) ?></td>
            <td>
              <?php
                // data-* for potential future edit modal
                $data_attrs = [
                  'id' => $r['issue_id'],
                  'category' => $r['category'],
                  'item_code' => $r['item_code'],
                  'item_name' => $r['item_name'],
                  'quantity_issued' => $r['quantity_issued'],
                  'issued_to' => $r['issued_to'],
                  'purpose' => $r['purpose']
                ];
                $data_str = '';
                foreach ($data_attrs as $k => $v) {
                    $data_str .= ' data-'.$k.'="'.htmlspecialchars($v, ENT_QUOTES).'"';
                }
              ?>
              <a class="btn btn-sm btn-danger" href="issuance.php?action=delete&id=<?php echo $r['issue_id']?>" onclick="return confirm('Delete?')">Delete</a>
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
          <input id="u_item_code" name="item_code" class="form-control" />
        </div>
      </div>

      <div class="form-row">
        <div class="form-group mb-2">
          <label for="u_item_name">Item Name</label>
          <input id="u_item_name" name="item_name" class="form-control" required />
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

<!-- Modal JS (open/close, set action & title) -->
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
})();
</script>

<?php require 'footer.php'; ?>
