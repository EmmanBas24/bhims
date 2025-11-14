<?php
require_once 'header.php';
require_once 'functions.php';
$user_id = $_SESSION['user_id'] ?? 0;
$action = $_GET['action'] ?? 'list';

// categories
$categories = ['Child','Adult','Newborns','Pediatric','Neonatal','Geriatric','General'];

// CRUD: add
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_code = $_POST['item_code'] ?? '';
    $item_name = $_POST['item_name'] ?? '';
    $expiry_date = $_POST['expiry_date'] ?: null;
    $quantity = intval($_POST['quantity'] ?? 0);
    $supplier = $_POST['supplier'] ?? '';
    $status = $_POST['status'] ?? 'Available';
    $date_received = $_POST['date_received'] ?: null;
    $dosage = $_POST['dosage'] ?? '';
    $category = $_POST['category'] ?? '';
    $stmt = $mysqli->prepare('INSERT INTO medicine (item_code,item_name,expiry_date,quantity,supplier,status,date_received,added_by,dosage,category,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())');
    if ($stmt===false) { die('Prepare failed: '.$mysqli->error); }
    $stmt->bind_param('sssississs',$item_code,$item_name,$expiry_date,$quantity,$supplier,$status,$date_received,$user_id,$dosage,$category);
    $stmt->execute(); $stmt->close();
    log_activity($user_id,'Added medicine: '.$item_name);
    header('Location: medicine.php'); exit;
}

// CRUD: edit
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_GET['id'] ?? 0);
    $item_code = $_POST['item_code'] ?? '';
    $item_name = $_POST['item_name'] ?? '';
    $expiry_date = $_POST['expiry_date'] ?: null;
    $quantity = intval($_POST['quantity'] ?? 0);
    $supplier = $_POST['supplier'] ?? '';
    $status = $_POST['status'] ?? 'Available';
    $date_received = $_POST['date_received'] ?: null;
    $dosage = $_POST['dosage'] ?? '';
    $category = $_POST['category'] ?? '';
    $stmt = $mysqli->prepare('UPDATE medicine SET item_code=?,item_name=?,expiry_date=?,quantity=?,supplier=?,status=?,date_received=?,added_by=?,dosage=?,category=?,updated_at=NOW() WHERE med_id=?');
    if ($stmt===false) { die('Prepare failed: '.$mysqli->error); }
    $stmt->bind_param('sssississsi',$item_code,$item_name,$expiry_date,$quantity,$supplier,$status,$date_received,$user_id,$dosage,$category,$id);
    $stmt->execute(); $stmt->close();
    log_activity($user_id,'Updated medicine ID '.$id);
    header('Location: medicine.php'); exit;
}

// CRUD: delete
if ($action === 'delete') {
    $id = intval($_GET['id'] ?? 0);
    $stmt = $mysqli->prepare('DELETE FROM medicine WHERE med_id=?');
    $stmt->bind_param('i',$id);
    $stmt->execute(); $stmt->close();
    log_activity($user_id,'Deleted medicine ID '.$id);
    header('Location: medicine.php'); exit;
}

// Listing filters
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$category_filter = $_GET['category'] ?? '';

$sql = "SELECT * FROM medicine WHERE 1=1";
$params = []; $types = '';
if ($search !== '') { $sql.=" AND item_name LIKE ?"; $params[]="%$search%"; $types.='s'; }
if ($status_filter !== '') { $sql.=" AND status = ?"; $params[]=$status_filter; $types.='s'; }
if ($category_filter !== '') { $sql.=" AND category = ?"; $params[]=$category_filter; $types.='s'; }
$sql .= " ORDER BY updated_at DESC";
$stmt = $mysqli->prepare($sql);
if ($stmt === false) { die('Prepare failed: '.$mysqli->error); }
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// derive statuses and soon count
$derived = []; $soon_count = 0;
$today = new DateTimeImmutable('today');
foreach ($rows as $r) {
    $row = $r;
    $row['quantity'] = (int)$r['quantity'];
    $row['derived_status'] = $r['status'] ?? 'Available';
    $row['is_expired'] = false; $row['is_soon'] = false; $row['days_to_expiry'] = null;
    if (!empty($r['expiry_date']) && $r['expiry_date'] !== '0000-00-00') {
        try {
            $exp = new DateTimeImmutable($r['expiry_date']);
            $days = (int) floor(($exp->getTimestamp() - $today->getTimestamp())/86400);
            $row['days_to_expiry'] = $days;
            if ($days < 0) $row['is_expired'] = true;
            elseif ($days <= 30) $row['is_soon'] = true;
        } catch (Exception $e) { $row['days_to_expiry'] = null; }
    }
    if ($row['is_expired']) $row['derived_status']='Expired';
    elseif ($row['quantity'] <= 0) $row['derived_status']='Out of Stock';
    elseif ($row['quantity'] < 50) $row['derived_status']='Low Stock';
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
/* filters aligned on one row with add button (no horizontal scroll) */
.filters-wrap {
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:12px;
  flex-wrap:wrap; /* allow wrapping on very small screens but avoid horizontal scrollbar */
  margin-bottom:8px;
}

/* left side controls */
.filters-left {
  align-items:center;
  gap:10px;
  flex:1 1 auto;
  min-width:0; /* allow children to shrink properly */
  flex-wrap:wrap;
}

/* individual control styling */
.filters-left .form-control,
.filters-left .form-select {
  height:36px;
  padding:6px 10px;
  font-size:0.95rem;
}

/* flexible widths but prevent forcing a horizontal scroll */
.input-search { flex: 1 1 220px; min-width:140px; max-width:420px; }
.input-cat    { flex: 0 1 160px; min-width:120px; max-width:240px; }
.input-status { flex: 0 1 150px; min-width:120px; max-width:200px; }

/* right side (add button) */
.filters-right { flex: 0 0 auto; display:flex; align-items:center; gap:8px; }

/* other UI */
.table-modern th,.table-modern td{padding:12px 10px;vertical-align:middle}
.modal-backdrops{position:fixed;inset:0;background:rgba(0,0,0,0.45);display:none;z-index:1050;align-items:center;justify-content:center;padding:1rem}
.modal-backdrops.show{display:flex}
.modal-panel{background:#fff;border-radius:10px;width:100%;max-width:720px;box-shadow:0 10px 30px rgba(0,0,0,0.25);z-index:1060;padding:1.25rem;max-height:90vh;overflow:auto}
.row-soon-expire{background-color:#fff6f6}
.row-expired{background-color:#f3f3f3;color:#6b6b6b}
.badge-available{background:#e7f7e7;padding:4px 8px;border-radius:6px;color:#2b8a2b;font-weight:600}
.badge-low{background:#fff4e5;padding:4px 8px;border-radius:6px;color:#b36a00;font-weight:600}
.badge-expired{background:#ffecec;padding:4px 8px;border-radius:6px;color:#a80000;font-weight:700}
.badge-out{background:#ffdede;padding:4px 8px;border-radius:6px;color:#a80000;font-weight:700}

/* small screens: stack controls neatly */
@media (max-width:560px) {
  .filters-left { gap:8px; }
  .input-search { flex-basis: 100%; max-width: 100%; }
  .input-cat, .input-status { flex-basis: 45%; max-width: 48%; }
  .filters-right { width:100%; justify-content:flex-end; margin-top:6px; }
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

  <!-- FILTERS + ADD aligned together -->
  <div class="filters-wrap">
    <div class="filters-left">
      <form id="filterForm" method="get" class="d-flex align-items-center" style="gap:10px;flex-wrap:wrap;">
        <input name="search" value="<?php echo htmlspecialchars($search)?>" placeholder="Search..." class="form-control input-search" />
        <select name="category" class="form-select input-cat">
          <option value="">All categories</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?php echo htmlspecialchars($c)?>" <?php if($category_filter===$c) echo 'selected'?>><?php echo htmlspecialchars($c)?></option>
          <?php endforeach;?>
        </select>
        <select name="status" class="form-select input-status">
          <option value="">All status</option>
          <option value="Available" <?php if($status_filter==='Available') echo 'selected'?>>Available</option>
          <option value="Low Stock" <?php if($status_filter==='Low Stock') echo 'selected'?>>Low Stock</option>
          <option value="Expired" <?php if($status_filter==='Expired') echo 'selected'?>>Expired</option>
          <option value="Out of Stock" <?php if($status_filter==='Out of Stock') echo 'selected'?>>Out of Stock</option>
        </select>
        <div class="d-flex" style="gap:8px;">
          <button class="btn btn-sm btn-primary" type="submit">Filter</button>
          <a href="medicine.php" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
      </form>
    </div>

    <div class="filters-right">
      <a id="openUnifiedBtn" href="medicine.php?action=add" class="btn btn-sm btn-success"><i class="bi bi-plus"></i> Add Medicine</a>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-modern table-hover w-100">
      <thead class="bg-dark text-white">
        <tr>
          <th>Code</th><th>Name</th><th>Dosage</th><th>Category</th><th>Unit Qty</th><th>Status</th><th>Expiry</th><th>Last Updated</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($derived)): ?>
          <tr><td colspan="9">No records found.</td></tr>
        <?php else: foreach ($derived as $r):
            $tr_class = $r['is_expired'] ? 'row-expired' : ($r['is_soon'] ? 'row-soon-expire' : '');
        ?>
          <tr class="<?php echo $tr_class ?>">
            <td><?php echo htmlspecialchars($r['item_code'])?></td>
            <td><?php echo htmlspecialchars($r['item_name'])?></td>
            <td><?php echo htmlspecialchars($r['dosage'] ?? '')?></td>
            <td><?php echo htmlspecialchars($r['category'] ?? '')?></td>
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
            <td><?php echo htmlspecialchars($r['expiry_date'] ?? '')?></td>
            <td><?php echo htmlspecialchars($r['updated_at'])?></td>
            <td>
              <?php
                $data_attrs = [
                  'id'=>$r['med_id'],'item_code'=>$r['item_code'],'item_name'=>$r['item_name'],
                  'dosage'=>$r['dosage'] ?? '','category'=>$r['category'] ?? '','expiry_date'=>$r['expiry_date'] ?? '',
                  'quantity'=>$r['quantity'],'supplier'=>$r['supplier'] ?? '','status'=>$r['status'] ?? '','date_received'=>$r['date_received'] ?? ''
                ];
                $data_str = '';
                foreach ($data_attrs as $k=>$v) $data_str .= ' data-'.$k.'="'.htmlspecialchars($v,ENT_QUOTES).'"';
              ?>
              <a class="btn btn-sm btn-outline-secondary openUnifiedEdit" href="medicine.php?action=edit&id=<?php echo $r['med_id']?>" <?php echo $data_str?> title="Edit"><i class="bi bi-pencil"></i></a>
              <a class="btn btn-sm btn-outline-danger" href="medicine.php?action=delete&id=<?php echo $r['med_id']?>" onclick="return confirm('Delete?')"><i class="bi bi-trash"></i></a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- unified modal -->
<div id="unifiedModal" class="modal-backdrops" aria-hidden="true" role="dialog" aria-modal="true">
  <div class="modal-panel" role="document">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div id="unifiedTitle" style="font-weight:600">Add Medicine</div>
      <button class="btn btn-sm btn-light" data-close>&times;</button>
    </div>
    <form id="unifiedForm" method="post" action="medicine.php?action=add">
      <input type="hidden" id="mode_field" name="mode" value="add">
      <div class="row g-2">
        <div class="col-md-6"><label class="form-label">Item Code</label><input id="u_item_code" name="item_code" class="form-control"></div>
        <div class="col-md-6"><label class="form-label">Item Name</label><input id="u_item_name" name="item_name" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">Dosage</label><input id="u_dosage" name="dosage" class="form-control" placeholder="e.g. 500 mg"></div>
        <div class="col-md-6"><label class="form-label">Category</label><select id="u_category" name="category" class="form-control"><?php foreach($categories as $c) echo "<option>".htmlspecialchars($c)."</option>"; ?></select></div>
        <div class="col-md-6"><label class="form-label">Expiry Date</label><input id="u_expiry_date" type="date" name="expiry_date" class="form-control"></div>
        <div class="col-md-6"><label class="form-label">Quantity</label><input id="u_quantity" type="number" name="quantity" class="form-control" value="0"></div>
        <div class="col-md-6"><label class="form-label">Supplier</label><input id="u_supplier" name="supplier" class="form-control"></div>
        <div class="col-md-6"><label class="form-label">Status</label><select id="u_status" name="status" class="form-control"><option>Available</option><option>Low Stock</option><option>Expired</option><option>Out of Stock</option></select></div>
        <div class="col-12"><label class="form-label">Date Received</label><input id="u_date_received" type="date" name="date_received" class="form-control"></div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-3">
        <button id="u_submit" type="submit" class="btn btn-primary btn-sm">Save</button>
        <button type="button" class="btn btn-secondary btn-sm" data-close>Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  const body = document.body;
  const modal = document.getElementById('unifiedModal');
  const form = document.getElementById('unifiedForm');
  const title = document.getElementById('unifiedTitle');
  const submitBtn = document.getElementById('u_submit');
  function openModal(){ modal.classList.add('show'); body.classList.add('modal-open'); modal.setAttribute('aria-hidden','false'); const first = document.getElementById('u_item_name'); if(first) setTimeout(()=>first.focus(),50); }
  function closeModal(){ modal.classList.remove('show'); body.classList.remove('modal-open'); modal.setAttribute('aria-hidden','true'); document.getElementById('mode_field').value='add'; form.action='medicine.php?action=add'; title.textContent='Add Medicine'; submitBtn.textContent='Save'; form.reset(); }
  document.querySelectorAll('[data-close]').forEach(btn=>btn.addEventListener('click',closeModal));
  modal.addEventListener('click',e=>{ if(e.target===modal) closeModal(); });
  const openBtn = document.getElementById('openUnifiedBtn');
  if(openBtn) openBtn.addEventListener('click',e=>{ e.preventDefault(); form.reset(); title.textContent='Add Medicine'; form.action='medicine.php?action=add'; document.getElementById('mode_field').value='add'; submitBtn.textContent='Save'; openModal(); });
  document.querySelectorAll('.openUnifiedEdit').forEach(btn=>{
    btn.addEventListener('click',function(e){
      e.preventDefault();
      const id = this.dataset.id;
      document.getElementById('u_item_code').value = this.dataset.item_code || '';
      document.getElementById('u_item_name').value = this.dataset.item_name || '';
      document.getElementById('u_dosage').value = this.dataset.dosage || '';
      document.getElementById('u_category').value = this.dataset.category || '';
      document.getElementById('u_expiry_date').value = this.dataset.expiry_date || '';
      document.getElementById('u_quantity').value = this.dataset.quantity || 0;
      document.getElementById('u_supplier').value = this.dataset.supplier || '';
      document.getElementById('u_status').value = this.dataset.status || 'Available';
      document.getElementById('u_date_received').value = this.dataset.date_received || '';
      form.action = 'medicine.php?action=edit&id=' + encodeURIComponent(id);
      document.getElementById('mode_field').value='edit';
      title.textContent='Edit Medicine';
      submitBtn.textContent='Update';
      openModal();
    });
  });
  const urlParams = new URLSearchParams(window.location.search);
  const act = urlParams.get('action');
  if(act==='add'){ form.reset(); form.action='medicine.php?action=add'; title.textContent='Add Medicine'; submitBtn.textContent='Save'; openModal(); }
  else if(act==='edit'){ const id = urlParams.get('id'); if(id){ const editBtn = document.querySelector('.openUnifiedEdit[data-id="'+id+'"]'); if(editBtn) editBtn.click(); else { form.reset(); form.action='medicine.php?action=edit&id='+encodeURIComponent(id); title.textContent='Edit Medicine'; submitBtn.textContent='Update'; openModal(); } } }
  document.addEventListener('keydown',function(e){ if(e.key==='Escape' && modal.classList.contains('show')) closeModal(); });
})();
</script>
</body>
</html>
