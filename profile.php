<?php
require_once 'header.php';
require_once 'functions.php';
$user_id = $_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $name = $_POST['name']; $stmt = $mysqli->prepare('UPDATE users SET name = ? WHERE user_id = ?'); $stmt->bind_param('si',$name,$user_id); $stmt->execute(); $stmt->close();
        log_activity($user_id,'Updated profile');
        $_SESSION['name'] = $name;
    } elseif (isset($_POST['change_pass'])) {
        $cur = $_POST['current_password']; $new = $_POST['new_password'];
        $stmt = $mysqli->prepare('SELECT password FROM users WHERE user_id = ? LIMIT 1'); $stmt->bind_param('i',$user_id); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (password_verify($cur,$row['password'])) {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            $stmt = $mysqli->prepare('UPDATE users SET password = ? WHERE user_id = ?'); $stmt->bind_param('si',$hash,$user_id); $stmt->execute(); $stmt->close();
            log_activity($user_id,'Changed password');
            $msg = 'Password changed';
        } else { $err = 'Current password incorrect'; }
    }
}
$stmt = $mysqli->prepare('SELECT * FROM users WHERE user_id = ? LIMIT 1'); $stmt->bind_param('i',$user_id); $stmt->execute(); $user = $stmt->get_result()->fetch_assoc(); $stmt->close();
?>
<h3>Profile</h3>
<?php if (!empty($msg)): ?><div class="alert alert-success"><?php echo $msg ?></div><?php endif; ?>
<?php if (!empty($err)): ?><div class="alert alert-danger"><?php echo $err ?></div><?php endif; ?>
<form method="post">
  <div class="mb-2"><label>Name</label><input name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']) ?>"></div>
  <button name="update_profile" class="btn btn-primary">Update Profile</button>
</form>
<hr>
<h5>Change Password</h5>
<form method="post">
  <div class="mb-2"><label>Current Password</label><input type="password" name="current_password" class="form-control"></div>
  <div class="mb-2"><label>New Password</label><input type="password" name="new_password" class="form-control"></div>
  <button name="change_pass" class="btn btn-warning">Change Password</button>
</form>
<?php require 'footer.php'; ?>