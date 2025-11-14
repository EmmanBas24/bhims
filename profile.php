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
// Support first_name + last_name incoming from form by concatenating into name
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile']) && $current_role === 'Head BHW') {
    // if form sent first_name + last_name, combine
    if (isset($_POST['first_name']) || isset($_POST['last_name'])) {
        $first = trim($_POST['first_name'] ?? '');
        $last  = trim($_POST['last_name'] ?? '');
        $new_name = trim($first . ' ' . $last);
    } else {
        $new_name = trim($_POST['name'] ?? '');
    }

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

/* Helper: split name for display into first / last */
$first_name = '';
$last_name = '';
if (!empty($user['name'])) {
    $parts = preg_split('/\s+/', trim($user['name']));
    if (count($parts) === 1) {
        $first_name = $parts[0];
    } else {
        $last_name = array_pop($parts);
        $first_name = implode(' ', $parts);
    }
}
?>

<!-- load your site CSS (assumes header.php doesn't already include it) -->
<link rel="stylesheet" href="/assets/css/style.css">

<!-- small extra CSS to mimic screenshot layout -->
<style>
  .profile-wrap { max-width:1100px; margin:18px auto; padding:0 12px; }
  .card { background:#fff; border-radius:10px; padding:18px; border:1px solid #eef3f6; box-shadow:0 6px 18px rgba(12,17,25,0.03); }
  .card + .card { margin-top:16px; }
  .card-title { font-weight:600; font-size:15px; color:#0b4f6c; margin-bottom:12px; }
  .form-row { display:flex; gap:12px; align-items:stretch; }
  .label-col { width:180px; padding-top:6px; color:#374151; font-size:14px; }
  .input-col { flex:1; }
  .form-control { width:100%; padding:10px 12px; border:1px solid #e6eef3; border-radius:8px; background:#fff; box-sizing:border-box; }
  .note { color:#6b7280; font-size:13px; margin-top:6px; }
  .pw-req { list-style: disc; padding-left:18px; margin:8px 0 0 0; color:#475569; font-size:13px; }
  .pw-panel { background:#fbfdff; border:1px solid #eef6f8; padding:12px; border-radius:8px; }
  .btn-primary { background:#2b9adf; color:#fff; border:0; padding:10px 16px; border-radius:8px; cursor:pointer; }
  .btn-ghost { background:transparent; border:1px solid #dbe7eb; padding:10px 14px; border-radius:8px; cursor:pointer; color:#374151; }
  .two-cols { display:grid; grid-template-columns: 1fr 360px; gap:18px; align-items:start; }
  @media (max-width: 980px) {
    .two-cols { grid-template-columns: 1fr; }
    .label-col { width:120px; }
  }
  .split-name { display:flex; gap:12px; }
  .small-muted { color:#94a3b8; font-size:13px; }
</style>

<div class="profile-wrap">
  <?php if ($success): ?>
    <div style="margin-bottom:12px;" class="card"><div style="color: #0f5132; font-weight:600;"><?php echo htmlspecialchars($success) ?></div></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div style="margin-bottom:12px;" class="card"><div style="color:#842029; font-weight:600;"><?php echo htmlspecialchars($error) ?></div></div>
  <?php endif; ?>

  
  <!-- Basic information card (matches screenshot layout) -->
  <div class="card" style="margin-top:16px;">
    <div class="card-title">Basic information</div>

    <form method="post" style="margin-top:6px;">
      <input type="hidden" name="update_profile" value="1">

      <!-- Full name split row -->
      <div class="form-row" style="margin-bottom:12px;">
        <div class="label-col">Full name <span class="small-muted">(required)</span></div>
        <div class="input-col">
          <div class="split-name">
            <input name="first_name" class="form-control" placeholder="First name" value="<?php echo htmlspecialchars($first_name) ?>">
            <input name="last_name" class="form-control" placeholder="Last name" value="<?php echo htmlspecialchars($last_name) ?>">
          </div>
        </div>
      </div>

      <!-- Username (disabled display but still editable if Head BHW) -->
      <div class="form-row" style="margin-bottom:12px;">
        <div class="label-col">Username</div>
        <div class="input-col">
          <?php if ($current_role === 'Head BHW'): ?>
            <input name="username" class="form-control" value="<?php echo htmlspecialchars($user['username']) ?>">
          <?php else: ?>
            <input class="form-control" value="<?php echo htmlspecialchars($user['username']) ?>" disabled>
          <?php endif; ?>
        </div>
      </div>

      <!-- Email (not in provided data - show empty placeholder) 
      <div class="form-row" style="margin-bottom:12px;">
        <div class="label-col">Email</div>
        <div class="input-col"><input class="form-control" name="email" value="<?php echo htmlspecialchars($user['email'] ?? '') ?>" placeholder="you@example.com"></div>
      </div>

      Phone 
      <div class="form-row" style="margin-bottom:12px;">
        <div class="label-col">Phone <span class="small-muted">(Optional)</span></div>
        <div class="input-col"><input class="form-control" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? '') ?>" placeholder="+63 (9xx) xxx-xxxx"></div>
      </div>

    -->
  
      <!-- Role and Status (only editable by Head BHW) -->
      <div class="form-row" style="margin-bottom:12px;">
        <div class="label-col">Role</div>
        <div class="input-col">
          <?php if ($current_role === 'Head BHW'): ?>
            <select name="role" class="form-control">
              <option value="Head BHW" <?php if ($user['role']==='Head BHW') echo 'selected'; ?>>Head BHW</option>
              <option value="BHW" <?php if ($user['role']==='BHW') echo 'selected'; ?>>BHW</option>
            </select>
          <?php else: ?>
            <input class="form-control" value="<?php echo htmlspecialchars($user['role']) ?>" disabled>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-row" style="margin-bottom:6px;">
        <div class="label-col">Status</div>
        <div class="input-col">
          <?php if ($current_role === 'Head BHW'): ?>
            <select name="status" class="form-control">
              <option value="Active" <?php if ($user['status']==='Active') echo 'selected'; ?>>Active</option>
              <option value="Inactive" <?php if ($user['status']==='Inactive') echo 'selected'; ?>>Inactive</option>
            </select>
          <?php else: ?>
            <input class="form-control" value="<?php echo htmlspecialchars($user['status']) ?>" disabled>
          <?php endif; ?>
        </div>
      </div>

      <!-- Form actions -->
      <div style="display:flex; justify-content:flex-end; margin-top:12px; gap:10px;">
        <button type="button" class="btn-ghost" onclick="location.reload();">Cancel</button>
        <?php if ($current_role === 'Head BHW'): ?>
          <button type="submit" name="update_profile" class="btn-primary">Save changes</button>
        <?php else: ?>
          <button type="button" class="btn-primary" disabled>Save changes</button>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- small meta card -->
  <div style="margin-top:12px;">
    <div class="card">
      <div style="display:flex; justify-content:space-between; align-items:center;">
        <div>
          <div style="font-weight:700;">Account details</div>
          <div class="small-muted" style="margin-top:6px;">
            User ID: <?php echo (int)$user['user_id'] ?> &nbsp; • &nbsp; Created: <?php echo htmlspecialchars($user['date_created']) ?>
          </div>
        </div>
        <div style="text-align:right;">
          <div><strong><?php echo htmlspecialchars($user['username']) ?></strong></div>
          <div class="small-muted"><?php echo htmlspecialchars($user['role']) ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Change password: full-width card like screenshot -->
  <div class="card" style="margin-top:14px;">
    <div class="card-title">Change your password</div>

    <div style="display:flex; gap:18px; align-items:flex-start; flex-wrap:wrap;">
      <form method="post" style="flex:1 1 60%; min-width:320px;">
        <input type="hidden" name="change_pass" value="1">
        <div class="form-row" style="margin-bottom:12px;">
          <div class="label-col">Current password</div>
          <div class="input-col"><input class="form-control" type="password" name="current_password" placeholder="Enter current password" required></div>
        </div>

        <div class="form-row" style="margin-bottom:12px;">
          <div class="label-col">New password</div>
          <div class="input-col"><input id="new_password" class="form-control" type="password" name="new_password" placeholder="Enter new password" required></div>
        </div>

        <div class="form-row" style="margin-bottom:6px;">
          <div class="label-col">Confirm new password</div>
          <div class="input-col"><input id="confirm_password" class="form-control" type="password" name="confirm_password" placeholder="Confirm your new password" required></div>
        </div>

        <div style="display:flex; justify-content:flex-end; margin-top:10px;">
          <button type="submit" name="change_pass" class="btn-primary">Save Changes</button>
        </div>
      </form>

    
    </div>
  </div>

</div>



<!-- JS: password live checks & small helpers -->
<script>
(function(){
  var newPw = document.getElementById('new_password');
  var confirmPw = document.getElementById('confirm_password');
  var reqLength = document.getElementById('req-length');
  var reqLower = document.getElementById('req-lower');
  var reqUpper = document.getElementById('req-upper');
  var reqDigit = document.getElementById('req-digit');

  function testPw(){
    if (!newPw) return;
    var v = newPw.value || '';
    var okLen = v.length >= 8;
    var okLower = /[a-z]/.test(v);
    var okUpper = /[A-Z]/.test(v);
    var okDigit = /[0-9\W_]/.test(v);

    if (reqLength) reqLength.style.opacity = okLen ? 0.9 : 0.35;
    if (reqLower) reqLower.style.opacity = okLower ? 0.9 : 0.35;
    if (reqUpper) reqUpper.style.opacity = okUpper ? 0.9 : 0.35;
    if (reqDigit) reqDigit.style.opacity = okDigit ? 0.9 : 0.35;
  }
  if (newPw) newPw.addEventListener('input', testPw);
  if (confirmPw) confirmPw.addEventListener('input', testPw);

  // optional: when Head BHW clicks "Save changes" ensure first+last are combined if needed
  // (server already handles concatenation)
})();
</script>


