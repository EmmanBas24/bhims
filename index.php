<?php

require_once 'config.php';
require_once 'functions.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $user = get_user_by_username($username);
    if ($user && password_verify($password, $user['password']) && $user['status'] === 'Active') {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        log_activity($user['user_id'], 'Logged in');
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid credentials or inactive account.';
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>BHIS - Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-md-5">
      <div class="card shadow-sm">
        <div class="card-body">
          <h4 class="card-title mb-3">Barangay Health Inventory System</h4>
          <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error) ?></div>
          <?php endif; ?>
          <form method="post">
            <div class="mb-2">
              <label class="form-label">Username</label>
              <input class="form-control" name="username" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Password</label>
              <input class="form-control" name="password" type="password" required>
            </div>
            <button class="btn btn-primary w-100">Login</button>
          </form>
          <hr>
          <p class="small text-muted">Default Head BHW user: <strong>admin</strong> / admin123</p>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
