<?php
require_once 'header.php';
require_once 'functions.php';

// ensure logged in (use your app auth helper)
require_login();

$user_id = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? 'BHW';

/* -------------------------
   INPUT FILTERS
------------------------- */
$filter_month = isset($_GET['month']) ? trim($_GET['month']) : '';
$filter_type  = isset($_GET['type']) ? trim($_GET['type']) : '';
$filter_user  = isset($_GET['user']) ? trim($_GET['user']) : '';
$filter_search = isset($_GET['q']) ? trim($_GET['q']) : ''; // optional keyword search

/* -------------------------
   Build base query
------------------------- */
$sql = "
    SELECT a.*, u.user_id AS log_user_id, u.username AS log_username, u.role AS log_role
    FROM activity_logs a
    LEFT JOIN users u ON a.user_id = u.user_id
    WHERE 1=1
";
$where = [];
$params = [];
$types = '';

/* ROLE: non-Head BHWs only see their own logs */
if ($role !== 'Head BHW') {
    $where[] = "a.user_id = ?";
    $params[] = $user_id;
    $types .= 'i';
}

/* MONTH FILTER (numeric 1-12) */
if ($filter_month !== '' && ctype_digit($filter_month)) {
    $where[] = "MONTH(a.timestamp) = ?";
    $params[] = (int)$filter_month;
    $types .= 'i';
}

/* TYPE FILTER (match text inside activity_description)
   allow only expected tokens to avoid abuse */
if ($filter_type !== '') {
    $allowed_types = ['Added','Issued','Deleted','Updated','Logged in','Logged out'];
    if (in_array($filter_type, $allowed_types, true)) {
        $where[] = "a.activity_description LIKE ?";
        $params[] = "%{$filter_type}%";
        $types .= 's';
    }
}

/* USER FILTER (only for Head BHW) */
if ($role === 'Head BHW' && $filter_user !== '') {
    if (ctype_digit((string)$filter_user)) {
        $where[] = "a.user_id = ?";
        $params[] = (int)$filter_user;
        $types .= 'i';
    }
}

/* optional keyword search across activity_description */
if ($filter_search !== '') {
    $where[] = "a.activity_description LIKE ?";
    $params[] = "%{$filter_search}%";
    $types .= 's';
}

if (!empty($where)) {
    $sql .= " AND " . implode(" AND ", $where);
}

$sql .= " ORDER BY a.timestamp DESC";

/* prepare & bind */
$stmt = $mysqli->prepare($sql);
if ($stmt === false) {
    die('Prepare failed: ' . $mysqli->error);
}
if (!empty($params)) {
    $bind_names = [];
    $bind_names[] = $types;
    for ($i = 0; $i < count($params); $i++) {
        $bind_names[] = &$params[$i];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_names);
}
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* Load users list (for Head BHW dropdown) */
$users_list = [];
if ($role === 'Head BHW') {
    $ustmt = $mysqli->prepare("SELECT user_id, username FROM users ORDER BY username ASC");
    if ($ustmt) {
        $ustmt->execute();
        $users_list = $ustmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $ustmt->close();
    }
}

/* helper month name */
function month_name($m) {
    if (!ctype_digit((string)$m)) return '';
    $mi = (int)$m;
    if ($mi < 1 || $mi > 12) return '';
    return date("F", mktime(0,0,0,$mi,1,2000));
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Activity Logs</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    /* Filters aligned: left filters, right action buttons (no horizontal scrollbar) */
    .filters-wrap {
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:12px;
      flex-wrap:wrap;
      margin-bottom:12px;
    }
    .filters-left {
      display:flex;
      align-items:center;
      gap:10px;
      flex:1 1 auto;
      min-width:0;
      flex-wrap:wrap;
    }
    .filters-left .form-control,
    .filters-left .form-select {
      height:36px;
      padding:6px 10px;
      font-size:0.95rem;
    }
    .input-month { flex: 0 0 160px; min-width:120px; max-width:220px; }
    .input-type  { flex: 0 0 180px; min-width:140px; max-width:260px; }
    .input-user  { flex: 0 0 180px; min-width:140px; max-width:260px; }
    .input-search { flex: 1 1 240px; min-width:140px; max-width:420px; }

    .filters-right { flex:0 0 auto; display:flex; gap:8px; align-items:center; }

    /* table tweaks */
    .table-modern th, .table-modern td { vertical-align: middle; padding: 10px; }
    .muted { color:#666; font-size:0.9rem; }
    @media (max-width:560px) {
      .input-month, .input-type, .input-user { flex-basis:48%; max-width:48%; }
      .input-search { flex-basis:100%; max-width:100%; order:1; }
      .filters-right { width:100%; justify-content:flex-end; order:2; margin-top:6px; }
    }
  </style>
</head>
<body>
<div class="container-fluid py-3">
  <h3 class="mb-3">Activity Logs</h3>

  <div class="table-card mb-3 p-3" style="background:#fff;border-radius:8px;box-shadow:0 6px 18px rgba(0,0,0,0.04)">

    <!-- FILTERS + ACTIONS (single row) -->
    <form method="get" class="filters-wrap" aria-label="Activity filters">
      <div class="filters-left">
        <!-- Month -->
        <select name="month" class="form-select input-month" aria-label="Filter by month">
            <option value="">All Months</option>
            <?php for ($m=1;$m<=12;$m++): ?>
                <option value="<?php echo $m?>" <?php if((string)$filter_month === (string)$m) echo 'selected'?>>
                    <?php echo date("F", mktime(0,0,0,$m,1)); ?>
                </option>
            <?php endfor; ?>
        </select>

        <!-- Type (includes Logged in / Logged out) -->
        <select name="type" class="form-select input-type" aria-label="Filter by activity type">
            <option value="">All Activity Types</option>
            <option value="Added"   <?php if($filter_type==="Added") echo 'selected'?>>Added Item</option>
            <option value="Issued"  <?php if($filter_type==="Issued") echo 'selected'?>>Issued Item</option>
            <option value="Deleted" <?php if($filter_type==="Deleted") echo 'selected'?>>Deleted Item</option>
            <option value="Updated" <?php if($filter_type==="Updated") echo 'selected'?>>Updated Item</option>
            <option value="Logged in" <?php if($filter_type==="Logged in") echo 'selected'?>>Logged in</option>
            <option value="Logged out" <?php if($filter_type==="Logged out") echo 'selected'?>>Logged out</option>
        </select>

        <!-- User (Head only) -->
        <?php if ($role === 'Head BHW'): ?>
        <select name="user" class="form-select input-user" aria-label="Filter by user">
            <option value="">All Users</option>
            <?php foreach ($users_list as $u): ?>
                <option value="<?php echo (int)$u['user_id'] ?>" <?php if((string)$filter_user === (string)$u['user_id']) echo 'selected'?>>
                    <?php echo htmlspecialchars($u['username'], ENT_QUOTES) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>

        <!-- optional search input -->
        <input name="q" class="form-control input-search" placeholder="Search activity description..." value="<?php echo htmlspecialchars($filter_search, ENT_QUOTES) ?>" aria-label="Search logs">
      </div>

      <div class="filters-right">
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <a href="activity_logs.php" class="btn btn-secondary btn-sm">Reset</a>
      </div>
    </form>

    <div class="mb-2 muted">
        Showing <?php echo count($rows) ?> log<?php echo count($rows) !== 1 ? 's' : '' ?>
        <?php if ($filter_month) echo " for " . htmlspecialchars(month_name($filter_month)); ?>
        <?php if ($filter_type) echo " — filtered by ".htmlspecialchars($filter_type); ?>
    </div>

    <div class="table-responsive">
      <table class="table table-modern table-hover w-100" role="table" aria-label="Activity logs table">
        <thead class="table-light">
          <tr>
            <th style="width:10%">User ID</th>
            <th style="width:15%">User</th>
            <th style="width:12%">Role</th>
            <th style="width:48%">Activity</th>
            <th style="width:15%">Timestamp</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="5">No logs found.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td><?php echo htmlspecialchars($r['log_user_id'] ?? 'System', ENT_QUOTES) ?></td>
              <td><?php echo htmlspecialchars($r['log_username'] ?? 'System', ENT_QUOTES) ?></td>
              <td><?php echo htmlspecialchars($r['log_role'] ?? '—', ENT_QUOTES) ?></td>
              <td><?php echo htmlspecialchars($r['activity_description'] ?? '', ENT_QUOTES) ?></td>
              <td><?php echo htmlspecialchars($r['timestamp'] ?? '', ENT_QUOTES) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>
</body>
</html>
