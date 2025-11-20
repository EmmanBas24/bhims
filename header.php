<?php
require_once 'config.php';
if (!isset($no_require)) require_login();
$current_user_name = $_SESSION['name'] ?? 'Guest';
$current_role = $_SESSION['role'] ?? '';
?>
<!doctype html>
<html>

<style>
   .logo-wrapper {
    width: 40px;
    height: 40px;
    background: #fff;
    display: flex;
    justify-content: center;
    align-items: center;
    border-radius: 8px;
    margin-right: 8px;
   }
   
    .logo-wrapper .logo {
      width: 24px;
      height: 24px;
    }
</style>

<head>
  <meta charset="utf-8">
  <title>BHIS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
 <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/3.0.0/uicons-solid-rounded/css/uicons-solid-rounded.css'>
</head>
<body>
<nav class="navbar navbar-expand-lg topnav">
  <div class="container-fluid d-flex justify-content-between align-items-center">

    <!-- LEFT: Logo -->
    <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
      <div class="logo-wrapper">
        <img src="img/logo/logo.png" alt="" class="logo">
      </div> BHIMS
    </a>

    <!-- RIGHT: Profile Dropdown -->
    <div class="dropdown">
      <a class="d-flex align-items-center text-white text-decoration-none dropdown-toggle"
         href="#"
         id="userDropdown"
         data-bs-toggle="dropdown"
         aria-expanded="false">

        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($current_user_name); ?>&background=0b4f6c&color=fff"
             alt="User"
             class="rounded-circle me-2"
             width="34" height="34">

        <span><?php echo htmlspecialchars($current_user_name); ?></span>
      </a>

      <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="userDropdown">
        <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person-circle me-2"></i> Profile</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
      </ul>
    </div>

  </div>
</nav>

<div class="d-flex">
  <div class="sidebar">
    <ul class="nav flex-column px-2">
      <li class="nav-item"><a class="nav-link <?php echo basename($_SERVER['PHP_SELF'])=='dashboard.php' ? 'active':'' ?>" href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>

     

      <li class="nav-item"><a class="nav-link <?php echo basename($_SERVER['PHP_SELF'])=='medicine.php' ? 'active':'' ?>" href="medicine.php"><i class="bi bi-capsule"></i> Medicine</a></li>
      <li class="nav-item"><a class="nav-link <?php echo basename($_SERVER['PHP_SELF'])=='issuance.php' ? 'active':'' ?>" href="issuance.php"><i class="bi bi-send-fill"></i> Issuance</a></li>

      <?php if ($current_role === 'Head BHW'): ?>
        <li class="nav-item"><a class="nav-link <?php echo basename($_SERVER['PHP_SELF'])=='reports.php' ? 'active':'' ?>" href="reports.php"><i class="bi bi-file-earmark-text"></i> Reports</a></li>
      <?php endif; ?>
  <?php if ($current_role === 'Head BHW'): ?>
        <li class="nav-item"><a class="nav-link <?php echo basename($_SERVER['PHP_SELF'])=='users.php' ? 'active':'' ?>" href="users.php"><i class="bi bi-people-fill"></i> Users</a></li>
      <?php endif; ?>

      <li class="nav-item"><a class="nav-link <?php echo basename($_SERVER['PHP_SELF'])=='logs.php' ? 'active':'' ?>" href="logs.php"><i class="bi bi-clock-history"></i> Activity Logs</a></li>
      <li class="nav-item"><a class="nav-link <?php echo basename($_SERVER['PHP_SELF'])=='profile.php' ? 'active':'' ?>" href="profile.php"><i class="bi bi-person-circle"></i> Profile</a></li>
      <li class="nav-item mt-2"><a class="nav-link logout" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
    </ul>
  </div>

  <div class="content-wrap" style="flex:1;">
