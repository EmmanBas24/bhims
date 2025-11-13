<?php
require_once 'config.php';
if (!isset($no_require)) require_login();
$current_user_name = $_SESSION['name'] ?? 'Guest';
$current_role = $_SESSION['role'] ?? '';
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>BHIS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons (optional, small visual improvement) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
  <div class="container-fluid">
    <a class="navbar-brand" href="dashboard.php">BHIS</a>
    <div class="d-flex">
      <span class="me-3">Hello, <?php echo htmlspecialchars($current_user_name) ?></span>
      <a class="btn btn-outline-secondary btn-sm" href="profile.php">Profile</a>
      <a class="btn btn-danger btn-sm ms-2" href="logout.php">Logout</a>
    </div>
  </div>
</nav>
<div class="d-flex">
  <div class="bg-light border-end" style="width:220px; min-height:calc(100vh - 56px);">
    <ul class="nav flex-column p-2">
      <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a></li>

      <!-- User Management only visible to Head BHW -->
      <?php if ($current_role === 'Head BHW'): ?>
        <li class="nav-item"><a class="nav-link" href="users.php"><i class="bi bi-people-fill me-1"></i> User Management</a></li>
      <?php endif; ?>

      <li class="nav-item"><a class="nav-link" href="medicine.php"><i class="bi bi-capsule me-1"></i> Medicine</a></li>
      <li class="nav-item"><a class="nav-link" href="supplies.php"><i class="bi bi-box-seam me-1"></i> Supplies</a></li>
      <li class="nav-item"><a class="nav-link" href="equipment.php"><i class="bi bi-tools me-1"></i> Equipment</a></li>
      <li class="nav-item"><a class="nav-link" href="issuance.php"><i class="bi bi-send-fill me-1"></i> Issuance</a></li>
      <li class="nav-item"><a class="nav-link" href="reports.php"><i class="bi bi-file-earmark-text me-1"></i> Reports</a></li>
      <li class="nav-item"><a class="nav-link" href="logs.php"><i class="bi bi-clock-history me-1"></i> Activity Logs</a></li>
      <li class="nav-item"><a class="nav-link" href="profile.php"><i class="bi bi-person-circle me-1"></i> Profile</a></li>
      <li class="nav-item"><a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-1"></i> Logout</a></li>
    </ul>
  </div>
  <div class="p-4" style="flex:1;">
