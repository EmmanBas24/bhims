<?php
require_once 'header.php';
require_once 'functions.php';

// must be logged in
require_login();

$current_user_id = $_SESSION['user_id'];
$current_role = $_SESSION['role'] ?? 'BHW';

// messages
$success = '';
$error = '';
$show_modal = false; // if true, JS will open the modal automatically

// Fetch current user details (fresh from DB)
$stmt = $mysqli->prepare('SELECT user_id, name, username, role, status, date_created, password FROM users WHERE user_id = ? LIMIT 1');
if ($stmt === false) { die('Prepare failed: ' . $mysqli->error); }
$stmt->bind_param('i', $current_user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    echo '<div class="alert alert-danger">User not found.</div>';
    require 'footer.php';
    exit;
}

/*
  POST handling:
  - If Head BHW and submitted profile update (update_profile) => allow updating name, username, role, status
  - If any user submitted change password (change_pass) => require current password verification, update to new password
*/

// HEAD updates profile fields (name, username, role, status)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile']) && $current_role === 'Head BHW') {
    $new_name = trim($_POST['name'] ?? '');
    $new_username = trim($_POST['username'] ?? '');
    $new_role = $_POST['role'] ?? 'BHW';
    $new_status = $_POST['status'] ?? 'Active';

    // keep submitted values in $user for repopulating modal if error
    $user['name'] = $new_name;
    $user['username'] = $new_username;
    $user['role'] = $new_role;
    $user['status'] = $new_status;

    if ($new_name === '' || $new_username === '') {
        $error = 'Name and username cannot be empty.';
        $show_modal = true;
    } else {
        // check if username is taken by another user
        $stmt = $mysqli->prepare('SELECT user_id FROM users WHERE username = ? AND user_id != ? LIMIT 1');
        if ($stmt === false) { die('Prepare failed: ' . $mysqli->error); }
        $stmt->bind_param('si', $new_username, $current_user_id);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($exists) {
            $error = 'Username already taken. Choose a different username.';
            $show_modal = true;
        } else {
            // update
            $stmt = $mysqli->prepare('UPDATE users SET name = ?, username = ?, role = ?, status = ? WHERE user_id = ?');
            if ($stmt === false) { die('Prepare failed: ' . $mysqli->error); }
            $stmt->bind_param('ssssi', $new_name, $new_username, $new_role, $new_status, $current_user_id);
            if ($stmt->execute()) {
                $success = 'Profile updated successfully.';
                // update session name and role
                $_SESSION['name'] = $new_name;
                $_SESSION['role'] = $new_role;
                // refresh $user data
                $user['name'] = $new_name;
                $user['username'] = $new_username;
                $user['role'] = $new_role;
                $user['status'] = $new_status;
                log_activity($current_user_id, 'Updated profile details');
            } else {
                $error = 'Failed to update profile. Try again.';
                $show_modal = true;
            }
            $stmt->close();
        }
    }
}

// Change password (available to all)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_pass'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($new_password === '') {
        $error = 'New password cannot be empty.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New password and confirmation do not match.';
    } else {
        // verify current password
        if (!password_verify($current_password, $user['password'])) {
            $error = 'Current password is incorrect.';
        } else {
            $hash = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt = $mysqli->prepare('UPDATE users SET password = ? WHERE user_id = ?');
            if ($stmt === false) { die('Prepare failed: ' . $mysqli->error); }
            $stmt->bind_param('si', $hash, $current_user_id);
            if ($stmt->execute()) {
                $success = 'Password changed successfully.';
                log_activity($current_user_id, 'Changed password');
                // update loaded user password hash for any further checks in this request
                $user['password'] = $hash;
            } else {
                $error = 'Failed to update password.';
            }
            $stmt->close();
        }
    }
}
?>

<!-- Styles: reuse the card/modal style used across the system -->
<style>
  .table-card {
    background: #fff;
    border-radius: 10px;
    padding: 1rem;
    box-shadow: 0 8px 24px rgba(0,0,0,0.06);
  }
  .mb-2 { margin-bottom:.5rem; }
  .btn { display:inline-flex; align-items:center; gap:.5rem; padding:.375rem .6rem; border-radius:6px; border:1px solid transparent; text-decoration:none; cursor:pointer; }
  .btn-primary { background:#0d6efd; color:#fff; border-color:#0d6efd; }
  .btn-outline-secondary { background:transparent; color:#495057; border-color:#ced4da; }
  label { display:block; font-size:.9rem; margin-bottom:.25rem; color:#333; }
  input.form-control, select.form-control, textarea.form-control { width:100%; padding:.45rem .5rem; border:1px solid #dfe3e6; border-radius:6px; }
  textarea.form-control { min-height:100px; resize:vertical; }

  /* modal backdrop & panel (no blur) */
  .modal-backdrops {
    position: fixed;
    inset: 0;
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
  .modal-header{ display:flex; justify-content:space-between; align-items:center; margin-bottom:.75rem; }
  .modal-title{ font-weight:600; font-size:1.1rem; }
  .modal-close{ background:none; border:0; font-size:1.4rem; cursor:pointer; }
  .form-row{ display:flex; gap:12px; flex-wrap:wrap; }
  .form-row .form-group{ flex:1; min-width:180px; }
  body.modal-open { overflow: hidden; }

  /* small device tweaks */
  @media (max-width:600px) { .modal-panel { padding:.75rem; border-radius:8px; } }
</style>

<div class="container-fluid">
  <h3>Profile</h3>

  <?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="row" style="display:flex; gap:16px; flex-wrap:wrap;">
    <!-- Left: Profile details -->
    <div style="flex:1 1 420px; min-width:280px;">
      <div class="table-card">
        <h5 class="mb-3">Your Details</h5>
        <table class="table table-borderless" style="width:100%; border-collapse:collapse; font-size:.95rem;">
          <tr><th style="width:160px; text-align:left; padding:.35rem 0;">User ID</th><td style="padding:.35rem 0;"><?php echo (int)$user['user_id'] ?></td></tr>
          <tr><th style="text-align:left; padding:.35rem 0;">Name</th><td style="padding:.35rem 0;"><?php echo htmlspecialchars($user['name']) ?></td></tr>
          <tr><th style="text-align:left; padding:.35rem 0;">Username</th><td style="padding:.35rem 0;"><?php echo htmlspecialchars($user['username']) ?></td></tr>
          <tr><th style="text-align:left; padding:.35rem 0;">Role</th><td style="padding:.35rem 0;"><?php echo htmlspecialchars($user['role']) ?></td></tr>
          <tr><th style="text-align:left; padding:.35rem 0;">Status</th><td style="padding:.35rem 0;"><?php echo htmlspecialchars($user['status']) ?></td></tr>
          <tr><th style="text-align:left; padding:.35rem 0;">Date Created</th><td style="padding:.35rem 0;"><?php echo htmlspecialchars($user['date_created']) ?></td></tr>
        </table>

        <?php if ($current_role === 'Head BHW'): ?>
          <div class="mt-3">
            <!-- JS will intercept and open modal. fallback link for no-JS -->
            <a id="openEditBtn" href="?action=edit" class="btn btn-primary">Edit Details</a>
          </div>
        <?php else: ?>
          <div class="mt-3">
            <small class="text-muted">To update your name or username, contact the Head BHW.</small>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Right: Password change (available to all) -->
    <div style="flex:1 1 360px; min-width:280px;">
      <div class="table-card">
        <h5 class="mb-3">Change Password</h5>
        <form method="post">
          <div class="mb-2">
            <label>Current Password</label>
            <input type="password" name="current_password" class="form-control" required>
          </div>
          <div class="mb-2">
            <label>New Password</label>
            <input type="password" name="new_password" class="form-control" required>
          </div>
          <div class="mb-2">
            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" required>
          </div>
          <button name="change_pass" class="btn" style="background:#ffc107;color:#000;border-color:#ffc107;padding:.4rem .7rem;">Change Password</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Unified Edit Modal (Head BHW only) -->
<?php if ($current_role === 'Head BHW'): ?>
<div id="editProfileModal" class="modal-backdrops" aria-hidden="true" role="dialog" aria-modal="true">
  <div class="modal-panel" role="document">
    <div class="modal-header">
      <div class="modal-title">Edit Profile Details</div>
      <button class="modal-close" data-close aria-label="Close">&times;</button>
    </div>

    <form id="editProfileForm" method="post" action="">
      <input type="hidden" name="update_profile" value="1">
      <div class="form-row">
        <div class="form-group mb-2">
          <label for="u_name">Name</label>
          <input id="u_name" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']) ?>" required>
        </div>
        <div class="form-group mb-2">
          <label for="u_username">Username</label>
          <input id="u_username" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username']) ?>" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group mb-2">
          <label for="u_role">Role</label>
          <select id="u_role" name="role" class="form-control">
            <option value="Head BHW" <?php if($user['role']==='Head BHW') echo 'selected' ?>>Head BHW</option>
            <option value="BHW" <?php if($user['role']==='BHW') echo 'selected' ?>>BHW</option>
          </select>
        </div>
        <div class="form-group mb-2">
          <label for="u_status">Status</label>
          <select id="u_status" name="status" class="form-control">
            <option value="Active" <?php if($user['status']==='Active') echo 'selected' ?>>Active</option>
            <option value="Inactive" <?php if($user['status']==='Inactive') echo 'selected' ?>>Inactive</option>
          </select>
        </div>
      </div>

      <div class="d-flex justify-content-end mt-2" style="margin-top:.5rem;">
        <button type="submit" class="btn btn-primary btn-modal">Save Changes</button>
        <button type="button" class="btn btn-outline-secondary" data-close>Cancel</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- JS for modal open/close and auto-open on validation error -->
<script>
(function(){
  const body = document.body;
  const modal = document.getElementById('editProfileModal');
  if (!modal) return;
  const form = document.getElementById('editProfileForm');

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
  }

  // wire close buttons
  document.querySelectorAll('[data-close]').forEach(btn=> btn.addEventListener('click', ()=> closeModal()));

  // clicking backdrop outside panel closes modal
  modal.addEventListener('click', function(e){
    if (e.target === this) closeModal();
  });

  // intercept the Edit Details link to open modal (fallback link left for no-JS)
  const openBtn = document.getElementById('openEditBtn');
  if (openBtn) openBtn.addEventListener('click', function(e){
    e.preventDefault();
    // populate form fields from displayed values (in case values changed)
    const nameCell = '<?php echo addslashes($user['name']); ?>';
    const usernameCell = '<?php echo addslashes($user['username']); ?>';
    document.getElementById('u_name').value = nameCell;
    document.getElementById('u_username').value = usernameCell;
    // set selects (they already render server-side with correct selected options)
    openModal();
  });

  // Optionally auto-open when server-side validation triggered
  var showModal = <?php echo ($show_modal && $current_role === 'Head BHW') ? 'true' : 'false'; ?>;
  if (showModal) {
    // open on DOM ready
    document.addEventListener('DOMContentLoaded', function(){
      openModal();
    });
  }

  // allow Escape to close
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape' && modal.classList.contains('show')) closeModal();
  });
})();
</script>