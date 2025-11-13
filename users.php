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

if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $role = $_POST['role'];
    $status = $_POST['status'];

    $stmt = $mysqli->prepare('INSERT INTO users (name,username,password,role,status) VALUES (?,?,?,?,?)');
    $stmt->bind_param('sssss', $name, $username, $password, $role, $status);
    $stmt->execute();
    $stmt->close();

    log_activity($user_id, 'Added user: ' . $username);
    header('Location: users.php');
    exit;
}

if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_GET['id'] ?? 0);
    $name = $_POST['name'];
    $username = $_POST['username'];
    $role = $_POST['role'];
    $status = $_POST['status'];

    $stmt = $mysqli->prepare('UPDATE users SET name=?,username=?,role=?,status=? WHERE user_id=?');
    $stmt->bind_param('ssssi', $name, $username, $role, $status, $id);
    $stmt->execute();
    $stmt->close();

    log_activity($user_id, 'Updated user ID ' . $id);
    header('Location: users.php');
    exit;
}

if ($action === 'deactivate') {
    $id = intval($_GET['id'] ?? 0);
    $stmt = $mysqli->prepare('UPDATE users SET status = "Inactive" WHERE user_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    log_activity($user_id, 'Deactivated user ID ' . $id);
    header('Location: users.php');
    exit;
}

if ($action === 'list') {
    $stmt = $mysqli->prepare('SELECT user_id,name,username,role,status,date_created FROM users');
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<h3>Users Management</h3>
<a class="btn btn-sm btn-primary mb-2" href="?action=add">Add User</a>

<?php if ($action === 'list'): ?>
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
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?php echo htmlspecialchars($r['name']) ?></td>
        <td><?php echo htmlspecialchars($r['username']) ?></td>
        <td><?php echo htmlspecialchars($r['role']) ?></td>
        <td><?php echo htmlspecialchars($r['status']) ?></td>
        <td><?php echo htmlspecialchars($r['date_created']) ?></td>
        <td>
          <a class="btn btn-sm btn-secondary" href="?action=edit&id=<?php echo $r['user_id'] ?>">Edit</a>
          <?php if ($r['status'] === 'Active'): ?>
            <a class="btn btn-sm btn-danger" href="?action=deactivate&id=<?php echo $r['user_id'] ?>" onclick="return confirm('Deactivate?')">Deactivate</a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<?php if ($action === 'add'): ?>
<form method="post">
  <div class="mb-2">
    <label>Name</label>
    <input name="name" class="form-control" required>
  </div>
  <div class="mb-2">
    <label>Username</label>
    <input name="username" class="form-control" required>
  </div>
  <div class="mb-2">
    <label>Password</label>
    <input name="password" class="form-control" required>
  </div>
  <div class="mb-2">
    <label>Role</label>
    <select name="role" class="form-control">
      <option value="Head BHW">Head BHW</option>
      <option value="BHW">BHW</option>
    </select>
  </div>
  <div class="mb-2">
    <label>Status</label>
    <select name="status" class="form-control">
      <option value="Active">Active</option>
      <option value="Inactive">Inactive</option>
    </select>
  </div>
  <button class="btn btn-primary">Create</button>
  <a href="users.php" class="btn btn-secondary">Cancel</a>
</form>
<?php endif; ?>

<?php if ($action === 'edit'): 
  $id = intval($_GET['id'] ?? 0);
  $stmt = $mysqli->prepare('SELECT * FROM users WHERE user_id = ? LIMIT 1');
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
?>
<form method="post">
  <div class="mb-2">
    <label>Name</label>
    <input name="name" class="form-control" value="<?php echo htmlspecialchars($row['name']) ?>" required>
  </div>
  <div class="mb-2">
    <label>Username</label>
    <input name="username" class="form-control" value="<?php echo htmlspecialchars($row['username']) ?>" required>
  </div>
  <div class="mb-2">
    <label>Role</label>
    <select name="role" class="form-control">
      <option value="Head BHW" <?php if ($row['role'] === 'Head BHW') echo 'selected'; ?>>Head BHW</option>
      <option value="BHW" <?php if ($row['role'] === 'BHW') echo 'selected'; ?>>BHW</option>
    </select>
  </div>
  <div class="mb-2">
    <label>Status</label>
    <select name="status" class="form-control">
      <option value="Active" <?php if ($row['status'] === 'Active') echo 'selected'; ?>>Active</option>
      <option value="Inactive" <?php if ($row['status'] === 'Inactive') echo 'selected'; ?>>Inactive</option>
    </select>
  </div>
  <button class="btn btn-primary">Update</button>
  <a href="users.php" class="btn btn-secondary">Cancel</a>
</form>
<?php endif; ?>

<?php require 'footer.php'; ?>
