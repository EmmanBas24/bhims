<?php
require_once 'config.php';
require_once 'functions.php';

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

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
:root{
  --theme-start:#073b4c;
  --theme-end:#0b5f74;
}

/* FULL BACKGROUND IMAGE + GRADIENT OVERLAY */
body {
  margin:0;
  padding:0;
  height:100vh;
  background: 
    linear-gradient(180deg, rgba(7,59,76,0.75), rgba(11,95,116,0.75)),
    url('img/backgrounds/bg.png');
  background-size: cover;
  background-position: center;
  background-attachment: fixed;
  display:flex;
  justify-content:center;
  align-items:center;
  font-family: system-ui, -apple-system, "Segoe UI", Roboto;
}

/* Center wrapper */
.login-wrap {
  width: 100%;
  display: flex;
  justify-content: center;
  align-items: center;
  padding:2rem;
}

/* Login Card */
.login-card {
  background:#ffffffdd;
  backdrop-filter: blur(4px);
  display:flex;
  justify-content: center;
  border-radius:12px;
  width: 60%;
  overflow:hidden;
  box-shadow:0 18px 40px rgba(0,0,0,0.25);
}

/* Left Panel */
.hero {
  width:55%;
  background:#fff;
  padding: 1.2rem 4.5rem 2rem 2rem;
  display:flex;
  justify-content:center;
  align-items:center;
}

.hero img.logo {
  width:100px;
  margin-bottom:1rem;
}

.hero h1 {
  font-size:1.2rem;
  font-weight:600;
  color:#08546dff;
  text-align:center;
}

.inner {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

/* Right Panel */
.form-panel {
  width:45%;
  padding:3rem 2rem;
  background: linear-gradient(180deg, var(--theme-start), var(--theme-end));
  color:#fff;
  position:relative;
}

.form-panel::before {
  content:"";
  position:absolute;
  left:-30px;
  top:0; bottom:-1px;
  width:120px;
  background: inherit;
  transform: skewX(-18deg);
  filter: brightness(0.9);
}

.login-form {
  position:relative;
  z-index:5;
}

.login-form .form-label { color:#e7fafc; }

.login-form .form-control {
  border:0;
  border-radius:6px;
  padding:.6rem .7rem;
}

.btn-signin {
  width:100%;
  background:#45bbe2;
  background: linear-gradient(180deg,#45bbe2,#0e85a5);
  border:0;
  color:white;
  padding:.55rem;
  font-weight:600;
  border-radius:8px;
  margin-top:.75rem;
  box-shadow:0 6px 18px rgba(0,0,0,0.2);
}

.btn-signin:hover {
  background:#0e85a5;
}

/* Responsive */
@media(max-width:990px){
  .login-card { flex-direction:column; }
  .hero, .form-panel { width:100%; }
  .form-panel::before { display:none; }
}
</style>

</head>
<body>

<div class="login-wrap">
  <div class="login-card">

    <!-- LEFT SIDE -->
    <div class="hero">
      <div class="inner">
        <img src="img/logo/logo.png" class="logo">
        <h1>Better inventory. Better health.</h1>
      </div>
    </div>

    <!-- RIGHT SIDE -->
    <div class="form-panel">
      <div class="login-form">
        <h2 class="fw-bold mb-3">Welcome</h2>
        <p class="fw-light mb-4">Provide your credentials to sign in.</p>

        <?php if (!empty($error)): ?>
          <div class="alert alert-danger"><?php echo htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
          <label class="form-label">Username</label>
          <input class="form-control mb-3" name="username" required>

          <label class="form-label">Password</label>
          <input class="form-control mb-3" type="password" name="password" required>

          <button class="btn-signin">SIGN IN</button>
        </form>

      </div>
    </div>

  </div>
</div>

</body>
</html>
