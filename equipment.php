<?php
require_once 'header.php';
require_once 'functions.php';
$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'list';

// --- Server-side CRUD (preserves logic; added basic prepare checks) ---
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_code = $_POST['item_code'] ?? '';
    $item_name = $_POST['item_name'] ?? '';
    $quantity = intval($_POST['quantity'] ?? 0);
    $supplier = $_POST['supplier'] ?? '';
    $condition = $_POST['condition'] ?? '';
    $status = $_POST['status'] ?? 'Available';
    $date_received = $_POST['date_received'] ?? null;

    $stmt = $mysqli->prepare('INSERT INTO equipment (item_code,item_name,quantity,supplier,`condition`,status,date_received,added_by,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())');
    if ($stmt === false) { die('Prepare failed: ' . $mysqli->error); }
    $stmt->bind_param('ssissssi', $item_code, $item_name, $quantity, $supplier, $condition, $status, $date_received, $user_id);
    $stmt->execute(); $stmt->close();
    log_activity($user_id, 'Added equipment: ' . $item_name);
    header('Location: equipment.php'); exit;
}

if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_GET['id'] ?? 0);
    $item_code = $_POST['item_code'] ?? '';
    $item_name = $_POST['item_name'] ?? '';
    $quantity = intval($_POST['quantity'] ?? 0);
    $supplier = $_POST['supplier'] ?? '';
    $condition = $_POST['condition'] ?? '';
    $status = $_POST['status'] ?? 'Available';
    $date_received = $_POST['date_received'] ?? null;

    $stmt = $mysqli->prepare('UPDATE equipment SET item_code=?, item_name=?, quantity=?, supplier=?, `condition`=?, status=?, date_received=?, added_by=?, updated_at=NOW() WHERE equipment_id=?');
    if ($stmt === false) { die('Prepare failed: ' . $mysqli->error); }
    $stmt->bind_param('ssissssii', $item_code, $item_name, $quantity, $supplier, $condition, $status, $date_received, $user_id, $id);
    $stmt->execute(); $stmt->close();
    log_activity($user_id, 'Updated equipment ID ' . $id);
    header('Location: equipment.php'); exit;
}

if ($action === 'delete') {
    $id = intval($_GET['id'] ?? 0);
    $stmt = $mysqli->prepare('DELETE FROM equipment WHERE equipment_id = ?');
    if ($stmt === false) { die('Prepare failed: ' . $mysqli->error); }
    $stmt->bind_param('i', $id);
    $stmt->execute(); $stmt->close();
    log_activity($user_id, 'Deleted equipment ID ' . $id);
    header('Location: equipment.php'); exit;
}

// Listing (with search + status filter to match medicine UI)
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';

$sql = "SELECT * FROM equipment WHERE 1=1";
$params = [];
$types = '';
if ($search !== '') { $sql .= " AND item_name LIKE ?"; $params[] = "%$search%"; $types .= 's'; }
if ($status_filter !== '') { $sql .= " AND status = ?"; $params[] = $status_filter; $types .= 's'; }
$sql .= " ORDER BY updated_at DESC, date_received DESC, equipment_id DESC";

$stmt = $mysqli->prepare($sql);
if ($stmt === false) { die('Prepare failed: ' . $mysqli->error); }
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!-- Styles: modal + table card + badges (consistent with medicine.php) -->
<style>
  /* card + buttons + modal (keep your existing rules) */
  .table-card { background:#fff; border-radius:10px; padding:1rem; box-shadow:0 8px 24px rgba(0,0,0,0.06); }
  .d-flex{display:flex}.justify-content-between{justify-content:space-between}.align-items-center{align-items:center}
  .mb-2{margin-bottom:.5rem}.btn{display:inline-flex;align-items:center;gap:.5rem;padding:.375rem .6rem;border-radius:6px;border:1px solid transparent;text-decoration:none;cursor:pointer}
  .btn-sm{font-size:.85rem;padding:.275rem .5rem;border-radius:6px}
  .btn-primary{background:#0d6efd;color:#fff;border-color:#0d6efd}
  .btn-success{background:#198754;color:#fff;border-color:#198754}
  .btn-secondary{background:#6c757d;color:#fff;border-color:#6c757d}
  .btn-outline-secondary{background:transparent;color:#495057;border-color:#ced4da}
  .btn-outline-danger{background:transparent;color:#dc3545;border:1px solid rgba(220,53,69,0.12)}

  /* Modal backdrop & panel */
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
  label{display:block;font-size:.9rem;margin-bottom:.25rem;color:#333}
  input.form-control,select.form-control{width:100%;padding:.45rem .5rem;border:1px solid #dfe3e6;border-radius:6px}
  @media (max-width:600px){.modal-panel{padding:.75rem;border-radius:8px}}

  /* Table styles: modern card-like rows */
  .table.table-modern {
    border-collapse: separate;
    border-spacing: 0 10px;
    width: 100%;
  }
  .table-modern thead th {
    background: transparent;
    color: #0b4f6c;
    font-weight: 700;
    border: 0;
    padding: 10px 12px;
    text-align: left;
  }
  .table-modern tbody tr {
    background: #fbfdff;
    border-radius: 8px;
    box-shadow: 0 3px 8px rgba(10, 20, 30, 0.03);
  }
  /* ensure cells look like a single rounded row */
  .table-modern tbody tr td {
    vertical-align: middle;
    padding: 12px;
    border: 0;
    background: transparent;
  }
  /* keep small-device readability */
  @media (max-width:600px) {
    .table-modern thead th { padding: 8px; font-size: .9rem; }
    .table-modern tbody tr td { padding: 10px; font-size: .95rem; }
  }
</style>


<h3>Health Equipments</h3>

<div class="table-card">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <div class="table-actions">
      <form class="d-flex" method="get" style="gap:8px; align-items:center;">
        <input name="search" value="<?php echo htmlspecialchars($search) ?>" placeholder="Search equipment..." class="form-control form-control-sm" style="padding:.35rem .5rem;">
        <select name="status" class="form-control form-control-sm" style="padding:.35rem .5rem;">
          <option value="">All status</option>
          <option value="Available" <?php if($status_filter==='Available') echo 'selected' ?>>Available</option>
          <option value="Unavailable" <?php if($status_filter==='Unavailable') echo 'selected' ?>>Unavailable</option>
        </select>
        <button class="btn btn-sm btn-primary">Filter</button>
        <a href="equipment.php" class="btn btn-sm btn-outline-secondary">Reset</a>
      </form>
    </div>

    <div>
      <a id="openUnifiedBtn" href="equipment.php?action=add" class="btn btn-sm btn-success"><span style="font-weight:700;">＋</span> Add Equipment</a>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-modern w-100">
      <thead>
        <tr>
          <th>Code</th>
          <th>Name</th>
          <th>Qty</th>
          <th>Supplier</th>
          <th>Condition</th>
          <th>Status</th>
          <th>Date Received</th>
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
            <td><?php echo (int)$r['quantity'] ?></td>
            <td><?php echo htmlspecialchars($r['supplier']) ?></td>
            <td>
              <?php
                $cond = $r['condition'] ?? '';
                if ($cond === 'Good') echo '<span class="badge-good">Good</span>';
                elseif ($cond === 'Broken') echo '<span class="badge-broken">Broken</span>';
                else echo '<span class="badge-damaged">'.htmlspecialchars($cond).'</span>';
              ?>
            </td>
            <td><?php echo htmlspecialchars($r['status']) ?></td>
            <td><?php echo htmlspecialchars($r['date_received']) ?></td>
            <td>
              <?php
                $data_attrs = [
                  'id' => $r['equipment_id'],
                  'item_code' => $r['item_code'],
                  'item_name' => $r['item_name'],
                  'quantity' => $r['quantity'],
                  'supplier' => $r['supplier'],
                  'condition' => $r['condition'],
                  'status' => $r['status'],
                  'date_received' => $r['date_received']
                ];
                $data_str = '';
                foreach ($data_attrs as $k => $v) {
                    $data_str .= ' data-'.$k.'="'.htmlspecialchars($v, ENT_QUOTES).'"';
                }
              ?>
              <a class="btn btn-sm btn-outline-secondary openUnifiedEdit" href="equipment.php?action=edit&id=<?php echo $r['equipment_id'] ?>" <?php echo $data_str; ?> title="Edit"><i class="bi bi-pencil"></i></a>
              <a class="btn btn-sm btn-outline-danger" href="equipment.php?action=delete&id=<?php echo $r['equipment_id'] ?>" onclick="return confirm('Delete?')"><i class="bi bi-trash"></i></a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Unified Modal (Add & Edit) -->
<div id="unifiedModal" class="modal-backdrops" aria-hidden="true" role="dialog" aria-modal="true">
  <div class="modal-panel" role="document">
    <div class="modal-header">
      <div id="unifiedTitle" class="modal-title">Add Equipment</div>
      <button class="modal-close" data-close aria-label="Close">&times;</button>
    </div>

    <form id="unifiedForm" method="post" action="equipment.php?action=add">
      <input type="hidden" id="mode_field" name="mode" value="add">

      <div class="form-row">
        <div class="form-group mb-2">
          <label for="u_item_code">Item Code</label>
          <input id="u_item_code" name="item_code" class="form-control" />
        </div>
        <div class="form-group mb-2">
          <label for="u_item_name">Item Name</label>
          <input id="u_item_name" name="item_name" class="form-control" required />
        </div>
      </div>

      <div class="form-row">
        <div class="form-group mb-2">
          <label for="u_quantity">Quantity</label>
          <input id="u_quantity" type="number" name="quantity" class="form-control" value="0" />
        </div>
        <div class="form-group mb-2">
          <label for="u_supplier">Supplier</label>
          <input id="u_supplier" name="supplier" class="form-control" />
        </div>
      </div>

      <div class="form-row">
        <div class="form-group mb-2">
          <label for="u_condition">Condition</label>
          <select id="u_condition" name="condition" class="form-control">
            <option>Good</option>
            <option>Broken</option>
            <option>Damaged</option>
          </select>
        </div>
        <div class="form-group mb-2">
          <label for="u_status">Status</label>
          <select id="u_status" name="status" class="form-control">
            <option>Available</option>
            <option>Unavailable</option>
          </select>
        </div>
      </div>

      <div class="mb-2">
        <label for="u_date_received">Date Received</label>
        <input id="u_date_received" type="date" name="date_received" class="form-control" />
      </div>

      <div class="d-flex justify-content-end mt-2">
        <button id="u_submit" type="submit" class="btn btn-primary btn-modal">Save</button>
        <button type="button" class="btn btn-secondary" data-close>Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Non-JS fallback Add form -->
<?php if ($action === 'add' && empty($_POST)): ?>
  <h3 style="margin-top:1rem;">Add Equipment (No JS)</h3>
  <form method="post">
    <div class="mb-2"><label>Item Code</label><input name="item_code" class="form-control"></div>
    <div class="mb-2"><label>Item Name</label><input name="item_name" class="form-control" required></div>
    <div class="mb-2"><label>Quantity</label><input type="number" name="quantity" class="form-control" value="0"></div>
    <div class="mb-2"><label>Supplier</label><input name="supplier" class="form-control"></div>
    <div class="mb-2"><label>Condition</label><select name="condition" class="form-control"><option>Good</option><option>Broken</option><option>Damaged</option></select></div>
    <div class="mb-2"><label>Status</label><select name="status" class="form-control"><option>Available</option><option>Unavailable</option></select></div>
    <div class="mb-2"><label>Date Received</label><input type="date" name="date_received" class="form-control"></div>
    <button class="btn btn-primary">Save</button> <a href="equipment.php" class="btn btn-secondary">Cancel</a>
  </form>
<?php endif; ?>

<!-- Non-JS fallback Edit form -->
<?php if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] !== 'POST'):
    $id = intval($_GET['id'] ?? 0);
    $stmt = $mysqli->prepare('SELECT * FROM equipment WHERE equipment_id = ? LIMIT 1');
    if ($stmt === false) { die('Prepare failed: ' . $mysqli->error); }
    $stmt->bind_param('i', $id); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if ($row):
?>
  <h3 style="margin-top:1rem;">Edit Equipment (No JS)</h3>
  <form method="post" action="equipment.php?action=edit&id=<?php echo $id ?>">
    <div class="mb-2"><label>Item Code</label><input name="item_code" class="form-control" value="<?php echo htmlspecialchars($row['item_code']) ?>"></div>
    <div class="mb-2"><label>Item Name</label><input name="item_name" class="form-control" value="<?php echo htmlspecialchars($row['item_name']) ?>" required></div>
    <div class="mb-2"><label>Quantity</label><input type="number" name="quantity" class="form-control" value="<?php echo htmlspecialchars($row['quantity']) ?>"></div>
    <div class="mb-2"><label>Supplier</label><input name="supplier" class="form-control" value="<?php echo htmlspecialchars($row['supplier']) ?>"></div>
    <div class="mb-2"><label>Condition</label>
      <select name="condition" class="form-control">
        <option <?php if($row['condition']==='Good') echo 'selected' ?>>Good</option>
        <option <?php if($row['condition']==='Broken') echo 'selected' ?>>Broken</option>
        <option <?php if($row['condition']==='Damaged') echo 'selected' ?>>Damaged</option>
      </select>
    </div>
    <div class="mb-2"><label>Status</label>
      <select name="status" class="form-control">
        <option <?php if($row['status']==='Available') echo 'selected' ?>>Available</option>
        <option <?php if($row['status']==='Unavailable') echo 'selected' ?>>Unavailable</option>
      </select>
    </div>
    <div class="mb-2"><label>Date Received</label><input type="date" name="date_received" class="form-control" value="<?php echo htmlspecialchars($row['date_received']) ?>"></div>
    <button class="btn btn-primary">Update</button> <a href="equipment.php" class="btn btn-secondary">Cancel</a>
  </form>
<?php
    else:
      echo '<div class="mb-2">Record not found.</div>';
    endif;
endif;
?>

<!-- Unified modal JS (open/close, populate, set action & title) -->
<script>
(function(){
  const body = document.body;
  const modal = document.getElementById('unifiedModal');
  const form = document.getElementById('unifiedForm');
  const title = document.getElementById('unifiedTitle');
  const submitBtn = document.getElementById('u_submit');

  function openModal() {
    modal.classList.add('show'); body.classList.add('modal-open'); modal.setAttribute('aria-hidden','false');
    const first = document.getElementById('u_item_name'); if(first) { setTimeout(()=> first.focus(),50); }
  }
  function closeModal() {
    modal.classList.remove('show'); body.classList.remove('modal-open'); modal.setAttribute('aria-hidden','true');
    const modeField = document.getElementById('mode_field'); if(modeField) modeField.value = 'add';
    form.action = 'equipment.php?action=add'; title.textContent = 'Add Equipment'; submitBtn && (submitBtn.textContent = 'Save'); form.reset();
  }

  document.querySelectorAll('[data-close]').forEach(btn=> btn.addEventListener('click', ()=> closeModal()));
  modal.addEventListener('click', function(e){ if(e.target === this) closeModal(); });

  const openBtn = document.getElementById('openUnifiedBtn');
  if(openBtn) openBtn.addEventListener('click', function(e){
    e.preventDefault(); form.reset(); title.textContent = 'Add Equipment'; form.action = 'equipment.php?action=add';
    document.getElementById('mode_field').value = 'add'; submitBtn && (submitBtn.textContent = 'Save'); openModal();
  });

  document.querySelectorAll('.openUnifiedEdit').forEach(btn=>{
    btn.addEventListener('click', function(e){
      e.preventDefault();
      const id = this.dataset.id;
      document.getElementById('u_item_code').value = this.dataset.item_code || '';
      document.getElementById('u_item_name').value = this.dataset.item_name || '';
      document.getElementById('u_quantity').value = this.dataset.quantity || 0;
      document.getElementById('u_supplier').value = this.dataset.supplier || '';
      document.getElementById('u_condition').value = this.dataset.condition || 'Good';
      document.getElementById('u_status').value = this.dataset.status || 'Available';
      document.getElementById('u_date_received').value = this.dataset.date_received || '';

      form.action = 'equipment.php?action=edit&id=' + encodeURIComponent(id);
      document.getElementById('mode_field').value = 'edit';
      title.textContent = 'Edit Equipment';
      submitBtn && (submitBtn.textContent = 'Update');
      openModal();
    });
  });

  const urlParams = new URLSearchParams(window.location.search);
  const action = urlParams.get('action');
  if(action === 'add') {
    form.reset(); form.action = 'equipment.php?action=add'; title.textContent = 'Add Equipment'; submitBtn && (submitBtn.textContent = 'Save'); openModal();
  } else if(action === 'edit') {
    const id = urlParams.get('id');
    if(id) {
      const editBtn = document.querySelector('.openUnifiedEdit[data-id="'+id+'"]');
      if(editBtn) editBtn.click();
      else { form.reset(); form.action = 'equipment.php?action=edit&id=' + encodeURIComponent(id); title.textContent = 'Edit Equipment'; submitBtn && (submitBtn.textContent = 'Update'); openModal(); }
    }
  }

  document.addEventListener('keydown', function(e){ if(e.key === 'Escape' && modal.classList.contains('show')) closeModal(); });
})();
</script>
