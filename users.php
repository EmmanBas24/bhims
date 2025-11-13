<?php
require_once 'header.php';
require_once 'functions.php';
$user_id = $_SESSION['user_id'];

if ($_SESSION['role'] !== 'Head BHW') {
    echo '<div class="alert alert-danger">Access denied.</div>';
    require 'footer.php';
    exit;
}

$action = $_GET['action'] ?? 'list';

// ADD
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $username = $_POST['username'] ?? '';
    $password = password_hash($_POST['password'] ?? '', PASSWORD_BCRYPT);
    $role = $_POST['role'] ?? 'BHW';
    $status = $_POST['status'] ?? 'Active';

    $stmt = $mysqli->prepare('INSERT INTO users (name,username,password,role,status,date_created) VALUES (?,?,?,?,?,NOW())');
    if ($stmt === false) { die('Prepare failed: ' . $mysqli->error); }
    $stmt->bind_param('sssss', $name, $username, $password, $role, $status);
    $stmt->execute();
    $stmt->close();

    log_activity($user_id, 'Added user: ' . $username);
    header('Location: users.php');
    exit;
}

// EDIT (no password change here)
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_GET['id'] ?? 0);
    $name = $_POST['name'] ?? '';
    $username = $_POST['username'] ?? '';
    $role = $_POST['role'] ?? 'BHW';
    $status = $_POST['status'] ?? 'Active';

    $stmt = $mysqli->prepare('UPDATE users SET name=?,username=?,role=?,status=?,updated_at=NOW() WHERE user_id=?');
    if ($stmt === false) { die('Prepare failed: ' . $mysqli->error); }
    $stmt->bind_param('ssssi', $name, $username, $role, $status, $id);
    $stmt->execute();
    $stmt->close();

    log_activity($user_id, 'Updated user ID ' . $id);
    header('Location: users.php');
    exit;
}

// DEACTIVATE
if ($action === 'deactivate') {
    $id = intval($_GET['id'] ?? 0);
    $stmt = $mysqli->prepare('UPDATE users SET status = "Inactive" WHERE user_id = ?');
    if ($stmt === false) { die('Prepare failed: ' . $mysqli->error); }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    log_activity($user_id, 'Deactivated user ID ' . $id);
    header('Location: users.php');
    exit;
}

// LIST
$stmt = $mysqli->prepare('SELECT user_id,name,username,role,status,date_created FROM users ORDER BY date_created DESC');
if ($stmt === false) { die('Prepare failed: ' . $mysqli->error); }
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!-- Styles: consistent table-card + modal system -->
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

  .badge-active{display:inline-block;padding:.25rem .5rem;border-radius:999px;background:#e6f4ea;color:#0b7a3a;font-weight:600;font-size:.82rem}
  .badge-inactive{display:inline-block;padding:.25rem .5rem;border-radius:999px;background:#ffecec;color:#c21c1c;font-weight:600;font-size:.82rem}

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
  input.form-control,select.form-control{width:100%;padding:.45rem .5rem;border:1px solid #dfe3e6;border-radius:6px}
</style>

<h3 style="margin-bottom:.5rem;">Users Management</h3>

<div class="table-card">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <div class="table-actions">
      <!-- placeholder for filters/search -->
    </div>

    <div>
      <a id="openUnifiedBtn" href="users.php?action=add" class="btn btn-sm btn-success"><span>＋</span> Add User</a>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-sm">
      <thead>
        <tr>
          <th>Name</th>
          <th>Username</th>
          <th>Role</th>
          <th>Status</th>
          <th>Created</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="6">No users found.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <td><?php echo htmlspecialchars($r['name']) ?></td>
            <td><?php echo htmlspecialchars($r['username']) ?></td>
            <td><?php echo htmlspecialchars($r['role']) ?></td>
            <td>
              <?php if (($r['status'] ?? '') === 'Active') echo '<span class="badge-active">Active</span>'; else echo '<span class="badge-inactive">Inactive</span>'; ?>
            </td>
            <td><?php echo htmlspecialchars($r['date_created']) ?></td>
            <td>
              <?php
                $data_attrs = [
                  'id' => $r['user_id'],
                  'name' => $r['name'],
                  'username' => $r['username'],
                  'role' => $r['role'],
                  'status' => $r['status']
                ];
                $data_str = '';
                foreach ($data_attrs as $k => $v) {
                    $data_str .= ' data-'.$k.'="'.htmlspecialchars($v, ENT_QUOTES).'"';
                }
              ?>
              <a class="btn btn-sm btn-outline-secondary openUnifiedEdit" href="users.php?action=edit&id=<?php echo $r['user_id'] ?>" <?php echo $data_str; ?> title="Edit">Edit</a>
              <?php if (($r['status'] ?? '') === 'Active'): ?>
                <a class="btn btn-sm btn-danger" href="users.php?action=deactivate&id=<?php echo $r['user_id'] ?>" onclick="return confirm('Deactivate?')">Deactivate</a>
              <?php endif; ?>
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
      <div id="unifiedTitle" class="modal-title">Add User</div>
      <button class="modal-close" data-close aria-label="Close">&times;</button>
    </div>

    <form id="unifiedForm" method="post" action="users.php?action=add">
      <input type="hidden" id="mode_field" name="mode" value="add">

      <div class="form-row">
        <div class="form-group mb-2">
          <label for="u_name">Name</label>
          <input id="u_name" name="name" class="form-control" required />
        </div>
        <div class="form-group mb-2">
          <label for="u_username">Username</label>
          <input id="u_username" name="username" class="form-control" required />
        </div>
      </div>

      <!-- Password only shown on Add mode -->
      <div class="form-row" id="passwordRow">
        <div class="form-group mb-2">
          <label for="u_password">Password</label>
          <input id="u_password" name="password" type="password" class="form-control" required />
        </div>
      </div>

      <div class="form-row">
        <div class="form-group mb-2">
          <label for="u_role">Role</label>
          <select id="u_role" name="role" class="form-control">
            <option value="Head BHW">Head BHW</option>
            <option value="BHW">BHW</option>
          </select>
        </div>
        <div class="form-group mb-2">
          <label for="u_status">Status</label>
          <select id="u_status" name="status" class="form-control">
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
          </select>
        </div>
      </div>

      <div class="d-flex justify-content-end mt-2">
        <button id="u_submit" type="submit" class="btn btn-primary btn-modal">Create</button>
        <button type="button" class="btn btn-secondary" data-close>Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Non-JS fallback Add form -->
<?php if ($action === 'add' && empty($_POST)): ?>
  <h3 style="margin-top:1rem;">Add User (No JS)</h3>
  <form method="post">
    <div class="mb-2"><label>Name</label><input name="name" class="form-control" required></div>
    <div class="mb-2"><label>Username</label><input name="username" class="form-control" required></div>
    <div class="mb-2"><label>Password</label><input name="password" type="password" class="form-control" required></div>
    <div class="mb-2"><label>Role</label><select name="role" class="form-control"><option value="Head BHW">Head BHW</option><option value="BHW">BHW</option></select></div>
    <div class="mb-2"><label>Status</label><select name="status" class="form-control"><option value="Active">Active</option><option value="Inactive">Inactive</option></select></div>
    <button class="btn btn-primary">Create</button> <a href="users.php" class="btn btn-secondary">Cancel</a>
  </form>
<?php endif; ?>

<!-- Non-JS fallback Edit form -->
<?php if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] !== 'POST'):
  $id = intval($_GET['id'] ?? 0);
  $stmt = $mysqli->prepare('SELECT * FROM users WHERE user_id = ? LIMIT 1');
  if ($stmt === false) { die('Prepare failed: ' . $mysqli->error); }
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if ($row):
?>
  <h3 style="margin-top:1rem;">Edit User (No JS)</h3>
  <form method="post" action="users.php?action=edit&id=<?php echo $id ?>">
    <div class="mb-2"><label>Name</label><input name="name" class="form-control" value="<?php echo htmlspecialchars($row['name']) ?>" required></div>
    <div class="mb-2"><label>Username</label><input name="username" class="form-control" value="<?php echo htmlspecialchars($row['username']) ?>" required></div>
    <div class="mb-2"><label>Role</label><select name="role" class="form-control"><option value="Head BHW" <?php if ($row['role']==='Head BHW') echo 'selected'; ?>>Head BHW</option><option value="BHW" <?php if ($row['role']==='BHW') echo 'selected'; ?>>BHW</option></select></div>
    <div class="mb-2"><label>Status</label><select name="status" class="form-control"><option value="Active" <?php if ($row['status']==='Active') echo 'selected'; ?>>Active</option><option value="Inactive" <?php if ($row['status']==='Inactive') echo 'selected'; ?>>Inactive</option></select></div>
    <button class="btn btn-primary">Update</button> <a href="users.php" class="btn btn-secondary">Cancel</a>
  </form>
<?php
  else:
    echo '<div class="mb-2">User not found.</div>';
  endif;
endif;
?>

<!-- Modal JS (open/close, populate, set action & title) -->
<script>
(function(){
  const body = document.body;
  const modal = document.getElementById('unifiedModal');
  const form = document.getElementById('unifiedForm');
  const title = document.getElementById('unifiedTitle');
  const submitBtn = document.getElementById('u_submit');
  const passwordRow = document.getElementById('passwordRow');

  function openModal() {
    modal.classList.add('show');
    body.classList.add('modal-open');
    modal.setAttribute('aria-hidden','false');
    const first = document.getElementById('u_name');
    if(first) { setTimeout(()=> first.focus(),50); }
  }
  function closeModal() {
    modal.classList.remove('show');
    body.classList.remove('modal-open');
    modal.setAttribute('aria-hidden','true');
    const modeField = document.getElementById('mode_field');
    if(modeField) modeField.value = 'add';
    form.action = 'users.php?action=add';
    title.textContent = 'Add User';
    submitBtn.textContent = 'Create';
    // show password row in case it was hidden previously
    if(passwordRow) passwordRow.style.display = '';
    // clear password requirement
    const pwd = document.getElementById('u_password');
    if(pwd) { pwd.required = true; pwd.value = ''; }
    form.reset();
  }

  // wire close buttons
  document.querySelectorAll('[data-close]').forEach(btn=> btn.addEventListener('click', ()=> closeModal()));

  // clicking backdrop outside panel closes modal
  modal.addEventListener('click', function(e){ if(e.target === this) closeModal(); });

  // Open Add (intercept link if JS)
  const openBtn = document.getElementById('openUnifiedBtn');
  if(openBtn) openBtn.addEventListener('click', function(e){
    e.preventDefault();
    form.reset();
    title.textContent = 'Add User';
    form.action = 'users.php?action=add';
    document.getElementById('mode_field').value = 'add';
    submitBtn.textContent = 'Create';
    // show password field and make required
    if(passwordRow) passwordRow.style.display = '';
    const pwd = document.getElementById('u_password');
    if(pwd) pwd.required = true;
    openModal();
  });

  // attach edit buttons (data-* attributes provided by PHP)
  document.querySelectorAll('.openUnifiedEdit').forEach(btn=>{
    btn.addEventListener('click', function(e){
      e.preventDefault();
      const id = this.dataset.id;
      document.getElementById('u_name').value = this.dataset.name || '';
      document.getElementById('u_username').value = this.dataset.username || '';
      document.getElementById('u_role').value = this.dataset.role || 'BHW';
      document.getElementById('u_status').value = this.dataset.status || 'Active';

      // set form action to include id
      form.action = 'users.php?action=edit&id=' + encodeURIComponent(id);
      document.getElementById('mode_field').value = 'edit';
      title.textContent = 'Edit User';
      submitBtn.textContent = 'Update';

      // hide password row when editing (password change not supported here)
      if(passwordRow) passwordRow.style.display = 'none';
      const pwd = document.getElementById('u_password');
      if(pwd) pwd.required = false;

      openModal();
    });
  });

  // Open modal on page load when ?action=add or ?action=edit&id=... is present (fallback)
  const urlParams = new URLSearchParams(window.location.search);
  const action = urlParams.get('action');
  if(action === 'add') {
    form.reset();
    form.action = 'users.php?action=add';
    title.textContent = 'Add User';
    submitBtn.textContent = 'Create';
    if(passwordRow) passwordRow.style.display = '';
    const pwd = document.getElementById('u_password');
    if(pwd) pwd.required = true;
    openModal();
  } else if(action === 'edit') {
    const id = urlParams.get('id');
    if(id) {
      const editBtn = document.querySelector('.openUnifiedEdit[data-id="'+id+'"]');
      if(editBtn) {
        editBtn.click();
      } else {
        // row not found; open edit modal with action (server will handle if non-JS)
        form.reset();
        form.action = 'users.php?action=edit&id=' + encodeURIComponent(id);
        title.textContent = 'Edit User';
        submitBtn.textContent = 'Update';
        if(passwordRow) passwordRow.style.display = 'none';
        const pwd = document.getElementById('u_password');
        if(pwd) pwd.required = false;
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
