<?php
require_once 'config.php';
require_once 'functions.php';

// Hero image file path used in the left panel.
// Update this if your environment needs a different path/URL.
$hero = '/mnt/data/754d5768-180c-4eb6-8045-869dcb50ef10.png';

$error = '';
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
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>BHIS - Login</title>

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Custom styles to mimic the provided UI -->
  <style>
    :root{
      --primary-900:#0f4fa0;
      --primary-800:#1e63b8;
      --panel-blue:#185aa9;
      --panel-blue-2:#1f66c0;
      --card-bg: rgba(255,255,255,0.98);
    }

    html,body{height:100%;}
    body{
      background:#f3f6f9;
      font-family: system-ui,-apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
    }

    /* Main split container */
    .login-wrap {
      min-height:100vh;
      display:flex;
      align-items:center;
      justify-content:center;
      padding:2rem;
    }

    .login-card {
      width:100%;
      max-width:1100px;
      min-height:520px;
      border-radius:12px;
      overflow:hidden;
      box-shadow: 0 18px 40px rgba(12, 35, 63, 0.12);
      display:flex;
      background: #fff;
    }

    /* Left hero panel */
    .hero {
      width:58%;
      min-width:360px;
      background: linear-gradient(180deg, #ffffff 0%, #ffffff 100%);
      display:flex;
      align-items:center;
      justify-content:center;
      position:relative;
      padding:2.5rem;
    }
    .hero .inner {
      width:100%;
      max-width:420px;
      text-align:center;
    }
    .hero img.logo {
      max-width:320px;
      width:100%;
      height:auto;
      display:block;
      margin:0 auto 1.25rem;
    }
    .hero h1 {
      font-size:1.25rem;
      color:#0b3a5a;
      margin:0;
      font-weight:700;
      letter-spacing:0.2px;
    }
    .hero p.lead {
      margin-top:.5rem;
      color:#6b7b88;
      font-size:.9rem;
    }

    /* Right form panel with diagonal clipping */
    .form-panel {
      width:42%;
      min-width:320px;
      position:relative;
      background: linear-gradient(180deg,var(--panel-blue), var(--panel-blue-2));
      color:#fff;
      padding:3.25rem 2.25rem;
      display:flex;
      align-items:center;
      justify-content:center;
    }

    /* diagonal effect using pseudo-element */
    .form-panel::before{
      content:'';
      position:absolute;
      left:-40px;
      top:0;
      bottom:0;
      width:120px;
      background: inherit;
      transform: skewX(-18deg);
      transform-origin:left;
      box-shadow: inset -30px 0 50px rgba(0,0,0,0.08);
    }

    .login-form {
      width:100%;
      max-width:320px;
      position:relative;
      z-index:2;
    }

    .login-form .form-control {
      border-radius:6px;
      padding:.6rem .65rem;
      border:0;
      outline:0;
    }

    .login-form .form-label {
      color: rgba(255,255,255,0.88);
      font-size:.85rem;
    }

    .login-form .form-text {
      color: rgba(255,255,255,0.75);
    }

    .btn-signin {
      background: linear-gradient(180deg,#4f79e4,#3f66d6);
      border:0;
      color:#fff;
      padding:.55rem .9rem;
      border-radius:8px;
      box-shadow: 0 6px 18px rgba(31,78,151,0.18);
      font-weight:600;
      letter-spacing:.4px;
    }

    .card-left {
      background: var(--card-bg);
      border-radius:10px;
      padding:1.25rem;
      box-shadow: 0 6px 20px rgba(12, 35, 63, 0.05);
    }

    /* small note and link */
    .help-text {
      color: rgba(255,255,255,0.85);
      font-size:.85rem;
    }

    /* responsive */
    @media (max-width:991px){
      .login-card { flex-direction:column; max-width: 900px; }
      .hero, .form-panel { width:100%; min-width:unset; }
      .form-panel::before { display:none; }
      .form-panel { padding:2rem; }
    }

    @media (max-width:420px){
      .login-form .form-control { padding:.5rem; }
      .login-card { min-height:unset; border-radius:8px; }
    }
  </style>
</head>
<body>
  <div class="login-wrap">
    <div class="login-card">

      <!-- LEFT: hero / logo -->
      <div class="hero">
        <div class="inner">
          <?php if (file_exists($hero)): ?>
            <img class="logo" src="<?php echo htmlspecialchars($hero) ?>" alt="BHIS Logo">
          <?php else: ?>
            <div class="card-left">
              <h1>BHIS Dashboard</h1>
              <p class="lead">Barangay Health Inventory System — Manage medicines, supplies and equipment easily.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- RIGHT: form panel -->
      <div class="form-panel">
        <div class="login-form">
          <h5 class="mb-3" style="color: #fff; font-weight:700;">Sign in to BHIS</h5>

          <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error) ?></div>
          <?php endif; ?>

          <form method="post" novalidate>
            <div class="mb-3">
              <label class="form-label">Username</label>
              <input class="form-control" name="username" required placeholder="Enter your username" autofocus>
            </div>

            <div class="mb-3">
              <label class="form-label">Password</label>
              <input class="form-control" name="password" type="password" required placeholder="Enter your password">
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" value="" id="rememberMe">
                <label class="form-check-label help-text" for="rememberMe">Remember me</label>
              </div>
              <a href="#" class="help-text">Recover password</a>
            </div>

            <div class="d-grid">
              <button class="btn btn-signin" type="submit">SIGN IN</button>
            </div>

            <div class="text-center mt-3" style="color: rgba(255,255,255,0.75); font-size:.85rem;">
              <small>Default Head BHW: <strong style="color:#fff">admin</strong> / admin123</small>
            </div>
          </form>
        </div>
      </div>

    </div>
  </div>

  <!-- Bootstrap JS (optional) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
