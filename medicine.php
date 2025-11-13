<?php
require_once 'header.php';
require_once 'functions.php';
$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'list';

// categories (you can adjust / extend as needed)
$categories = [
    'Child', 'Adult', 'Newborns', 'Pediatric', 'Neonatal', 'Geriatric', 'General'
];

// CRUD operations (unchanged)...
if ($action === 'add' && $_SERVER['REQUEST_METHOD']==='POST') {
    $item_code = $_POST['item_code'] ?? '';
    $item_name = $_POST['item_name'] ?? '';
    $expiry_date = $_POST['expiry_date'] ?? null;
    $quantity = intval($_POST['quantity'] ?? 0);
    $supplier = $_POST['supplier'] ?? '';
    $status = $_POST['status'] ?? 'Available';
    $date_received = $_POST['date_received'] ?? null;
    $dosage = $_POST['dosage'] ?? '';
    $category = $_POST['category'] ?? '';

    $stmt = $mysqli->prepare('INSERT INTO medicine (item_code,item_name,expiry_date,quantity,supplier,status,date_received,added_by,dosage,category,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())');
    if ($stmt === false) { die('Prepare failed: ' . $mysqli->error); }
    $stmt->bind_param('sssississs', $item_code, $item_name, $expiry_date, $quantity, $supplier, $status, $date_received, $user_id, $dosage, $category);
    $stmt->execute(); $stmt->close();
    log_activity($user_id,'Added medicine: '.$item_name);
    header('Location: medicine.php'); exit;
}

if ($action === 'edit' && $_SERVER['REQUEST_METHOD']==='POST') {
    $id = intval($_GET['id'] ?? 0);
    $item_code = $_POST['item_code'] ?? '';
    $item_name = $_POST['item_name'] ?? '';
    $expiry_date = $_POST['expiry_date'] ?? null;
    $quantity = intval($_POST['quantity'] ?? 0);
    $supplier = $_POST['supplier'] ?? '';
    $status = $_POST['status'] ?? 'Available';
    $date_received = $_POST['date_received'] ?? null;
    $dosage = $_POST['dosage'] ?? '';
    $category = $_POST['category'] ?? '';

    $stmt = $mysqli->prepare('UPDATE medicine SET item_code=?,item_name=?,expiry_date=?,quantity=?,supplier=?,status=?,date_received=?,added_by=?,dosage=?,category=?,updated_at=NOW() WHERE med_id=?');
    if ($stmt === false) { die('Prepare failed: ' . $mysqli->error); }
    $stmt->bind_param('sssississsi', $item_code, $item_name, $expiry_date, $quantity, $supplier, $status, $date_received, $user_id, $dosage, $category, $id);
    $stmt->execute(); $stmt->close();
    log_activity($user_id,'Updated medicine ID '.$id);
    header('Location: medicine.php'); exit;
}

if ($action === 'delete') {
    $id = intval($_GET['id'] ?? 0);
    $stmt = $mysqli->prepare('DELETE FROM medicine WHERE med_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    log_activity($user_id,'Deleted medicine ID '.$id);
    header('Location: medicine.php'); exit;
}

// Listing (with filters)
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';

$sql = "SELECT * FROM medicine WHERE 1=1";
$params = [];
$types = '';
if ($search !== '') { $sql .= " AND item_name LIKE ?"; $params[] = "%$search%"; $types .= 's'; }
if ($status_filter !== '') { $sql .= " AND status = ?"; $params[] = $status_filter; $types .= 's'; }
$sql .= " ORDER BY updated_at DESC";

$stmt = $mysqli->prepare($sql);
if ($stmt === false) { die('Prepare failed: ' . $mysqli->error); }
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!-- Styles: no blur, just dim backdrop -->
<style>
  /* backdrop only (no blur on page content) */
  .modal-backdrops {
    position: fixed;
    inset: 0;
    opacity: 1  ;
    background: rgba(0,0,0,0.45);
    display: none;
    z-index: 1050;
    align-items: center;
    justify-content: center;
    padding: 1rem;
  }
  .modal-backdrops.show { display: flex; }

  .modal-panel {
    background: #fff;
    border-radius: 10px;
    width: 100%;
    max-width: 720px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.25);
    z-index: 1060;
    padding: 1.25rem;
    max-height: 90vh;
    overflow: auto;
  }

  .modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom: .75rem; }
  .modal-title { font-weight:600; font-size:1.1rem; }
  .modal-close { background:none; border:0; font-size:1.2rem; cursor:pointer; }

  .form-row { display:flex; gap:12px; flex-wrap:wrap; }
  .form-row .form-group { flex:1; min-width:180px; }
  .btn-modal { margin-right:8px; }

  body.modal-open { overflow: hidden; } /* prevent background scroll */
  body.modal-open .modal-backdrop,
  body.modal-open .modal-backdrop * { pointer-events: auto; user-select: auto; }

  @media (max-width:600px) { .modal-panel { padding: .75rem; border-radius:8px; } }
</style>

<h3>Medicine Management</h3>

<div class="table-card">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <div class="table-actions">
      <form class="d-flex" method="get" style="gap:8px;align-items:center;">
        <input name="search" value="<?php echo htmlspecialchars($search) ?>" placeholder="Search medicine..." class="form-control form-control-sm">
        <select name="status" class="form-control form-control-sm">
          <option value="">All status</option>
          <option value="Available" <?php if($status_filter==='Available') echo 'selected' ?>>Available</option>
          <option value="Low Stock" <?php if($status_filter==='Low Stock') echo 'selected' ?>>Low Stock</option>
          <option value="Expired" <?php if($status_filter==='Expired') echo 'selected' ?>>Expired</option>
        </select>
        <button class="btn btn-sm btn-primary">Filter</button>
        <a href="medicine.php" class="btn btn-sm btn-outline-secondary">Reset</a>
      </form>
    </div>

    <div>
      <!-- JS will intercept and open modal. fallback link kept for no-JS -->
      <a id="openUnifiedBtn" href="medicine.php?action=add" class="btn btn-sm btn-success"><i class="bi bi-plus"></i> Add Medicine</a>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-modern w-100">
      <thead>
        <tr>
          <th>Code</th>
          <th>Name</th>
          <th>Dosage</th>
          <th>Category</th>
          <th>Unit Qty</th>
          <th>Status</th>
          <th>Last Updated</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="8">No records found.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <td><?php echo htmlspecialchars($r['item_code']) ?></td>
            <td><?php echo htmlspecialchars($r['item_name']) ?></td>
            <td><?php echo htmlspecialchars($r['dosage'] ?? '') ?></td>
            <td><?php echo htmlspecialchars($r['category'] ?? '') ?></td>
            <td><?php echo (int)$r['quantity'] ?></td>
            <td>
              <?php
                if ($r['status'] === 'Available') echo '<span class="badge-available">'.$r['status'].'</span>';
                elseif ($r['status'] === 'Low Stock') echo '<span class="badge-low">'.$r['status'].'</span>';
                else echo '<span class="badge-expired">'.$r['status'].'</span>';
              ?>
            </td>
            <td><?php echo htmlspecialchars($r['updated_at']) ?></td>
            <td>
              <?php
                $data_attrs = [
                  'id' => $r['med_id'],
                  'item_code' => $r['item_code'],
                  'item_name' => $r['item_name'],
                  'dosage' => $r['dosage'] ?? '',
                  'category' => $r['category'] ?? '',
                  'expiry_date' => $r['expiry_date'] ?? '',
                  'quantity' => $r['quantity'],
                  'supplier' => $r['supplier'] ?? '',
                  'status' => $r['status'] ?? '',
                  'date_received' => $r['date_received'] ?? ''
                ];
                $data_str = '';
                foreach ($data_attrs as $k=>$v) {
                    $data_str .= ' data-'.$k.'="'.htmlspecialchars($v,ENT_QUOTES).'"';
                }
              ?>
              <a class="btn btn-sm btn-outline-secondary action-btn openUnifiedEdit" href="medicine.php?action=edit&id=<?php echo $r['med_id'] ?>" <?php echo $data_str; ?> title="Edit"><i class="bi bi-pencil"></i></a>
              <a class="btn btn-sm btn-outline-danger action-btn" href="medicine.php?action=delete&id=<?php echo $r['med_id'] ?>" onclick="return confirm('Delete?')"><i class="bi bi-trash"></i></a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Unified Modal (used for both Add & Edit) -->
<div id="unifiedModal" class="modal-backdrops" aria-hidden="true" role="dialog" aria-modal="true">
  <div class="modal-panel" role="document">
    <div class="modal-header">
      <div id="unifiedTitle" class="modal-title">Add Medicine</div>
      <button class="modal-close" data-close>&times;</button>
    </div>

    <!-- form - action set dynamically by JS. keep method=post -->
    <form id="unifiedForm" method="post" action="medicine.php?action=add">
      <input type="hidden" id="mode_field" name="mode" value="add"> <!-- optional -->
      <div class="form-row">
        <div class="form-group mb-2">
          <label>Item Code</label>
          <input id="u_item_code" name="item_code" class="form-control">
        </div>
        <div class="form-group mb-2">
          <label>Item Name</label>
          <input id="u_item_name" name="item_name" class="form-control" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group mb-2">
          <label>Dosage (e.g. 500 ml)</label>
          <input id="u_dosage" name="dosage" class="form-control" placeholder="500 ml">
        </div>
        <div class="form-group mb-2">
          <label>Category</label>
          <select id="u_category" name="category" class="form-control">
            <?php foreach ($categories as $c): ?>
              <option value="<?php echo htmlspecialchars($c) ?>"><?php echo htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group mb-2">
          <label>Expiry Date</label>
          <input id="u_expiry_date" type="date" name="expiry_date" class="form-control">
        </div>
        <div class="form-group mb-2">
          <label>Quantity</label>
          <input id="u_quantity" type="number" name="quantity" class="form-control" value="0">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group mb-2">
          <label>Supplier</label>
          <input id="u_supplier" name="supplier" class="form-control">
        </div>
        <div class="form-group mb-2">
          <label>Status</label>
          <select id="u_status" name="status" class="form-control">
            <option>Available</option><option>Low Stock</option><option>Expired</option>
          </select>
        </div>
      </div>

      <div class="mb-2">
        <label>Date Received</label>
        <input id="u_date_received" type="date" name="date_received" class="form-control">
      </div>

      <div class="d-flex justify-content-end mt-2">
        <button id="u_submit" type="submit" class="btn btn-primary btn-modal">Save</button>
        <button type="button" class="btn btn-secondary" data-close>Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- JS for unified modal (open/close, populate, set action & title) -->
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
    // focus first input for accessibility
    const first = document.getElementById('u_item_name');
    if(first) { setTimeout(()=> first.focus(),50); }
  }
  function closeModal() {
    modal.classList.remove('show');
    body.classList.remove('modal-open');
    modal.setAttribute('aria-hidden','true');
    // clear mode hidden field if present
    const modeField = document.getElementById('mode_field');
    if(modeField) modeField.value = 'add';
    // restore form action default
    form.action = 'medicine.php?action=add';
    title.textContent = 'Add Medicine';
    submitBtn.textContent = 'Save';
  }

  // wire close buttons
  document.querySelectorAll('[data-close]').forEach(btn=> btn.addEventListener('click', ()=> closeModal()));

  // clicking backdrop outside panel closes modal
  modal.addEventListener('click', function(e){
    if(e.target === this) closeModal();
  });

  // Open Add (intercept link if JS)
  const openBtn = document.getElementById('openUnifiedBtn');
  if(openBtn) openBtn.addEventListener('click', function(e){
    e.preventDefault();
    // clear fields for add
    form.reset();
    title.textContent = 'Add Medicine';
    form.action = 'medicine.php?action=add';
    document.getElementById('mode_field').value = 'add';
    submitBtn.textContent = 'Save';
    openModal();
  });

  // attach edit buttons (data-* attributes provided by PHP)
  document.querySelectorAll('.openUnifiedEdit').forEach(btn=>{
    btn.addEventListener('click', function(e){
      e.preventDefault();
      const id = this.dataset.id;
      // populate fields
      document.getElementById('u_item_code').value = this.dataset.item_code || '';
      document.getElementById('u_item_name').value = this.dataset.item_name || '';
      document.getElementById('u_dosage').value = this.dataset.dosage || '';
      document.getElementById('u_category').value = this.dataset.category || '';
      document.getElementById('u_expiry_date').value = this.dataset.expiry_date || '';
      document.getElementById('u_quantity').value = this.dataset.quantity || 0;
      document.getElementById('u_supplier').value = this.dataset.supplier || '';
      document.getElementById('u_status').value = this.dataset.status || 'Available';
      document.getElementById('u_date_received').value = this.dataset.date_received || '';

      // set form action to include id
      form.action = 'medicine.php?action=edit&id=' + encodeURIComponent(id);
      document.getElementById('mode_field').value = 'edit';
      title.textContent = 'Edit Medicine';
      submitBtn.textContent = 'Update';
      openModal();
    });
  });

  // Open modal on page load when ?action=add or ?action=edit&id=... is present (fallback for no-JS navigation)
  const urlParams = new URLSearchParams(window.location.search);
  const action = urlParams.get('action');
  if(action === 'add') {
    // open add modal
    // but only if modal exists (it does)
    // ensure form empty
    form.reset();
    form.action = 'medicine.php?action=add';
    title.textContent = 'Add Medicine';
    submitBtn.textContent = 'Save';
    openModal();
  } else if(action === 'edit') {
    const id = urlParams.get('id');
    if(id) {
      // prefer to find row in DOM to populate via data-attrs
      const editBtn = document.querySelector('.openUnifiedEdit[data-id="'+id+'"]');
      if(editBtn) {
        editBtn.click(); // will populate & open modal
      } else {
        // row not found (maybe server rendered separate edit page). still open modal and set action to edit id.
        form.reset();
        form.action = 'medicine.php?action=edit&id=' + encodeURIComponent(id);
        title.textContent = 'Edit Medicine';
        submitBtn.textContent = 'Update';
        openModal();
      }
    }
  }

  // Allow Escape key to close modal
  document.addEventListener('keydown', function(e){
    if(e.key === 'Escape' && modal.classList.contains('show')) closeModal();
  });

})();
</script>
<style>