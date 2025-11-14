<?php
require_once 'config.php';
require_once 'header.php';
require_once 'functions.php';
require_login();

// ACCESS CONTROL: Only Head BHW
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Head BHW') {
    echo "<div class='alert alert-danger m-4'>Access denied. Reports are available only to Head BHW.</div>";
    require 'footer.php';
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;

// categories available for filters
$categories = ['Medicine', 'Supply'];

// fetch distinct months present in issuance (format: YYYY-MM) for the month filter dropdown
$month_options = [];
$mstmt = $mysqli->prepare("SELECT DISTINCT DATE_FORMAT(date_issued, '%Y-%m') AS ym FROM issuance WHERE date_issued IS NOT NULL ORDER BY ym DESC");
if ($mstmt !== false) {
    $mstmt->execute();
    $mres = $mstmt->get_result();
    while ($r = $mres->fetch_assoc()) {
        if (!empty($r['ym'])) $month_options[] = $r['ym'];
    }
    $mstmt->close();
}

// Filters (category and month)
$category_filter = $_GET['category'] ?? '';
$month_filter = $_GET['month'] ?? ''; // expected YYYY-MM, if empty fall back to current month
if ($month_filter === '') {
    // default to current month in YYYY-MM so dropdown shows something
    $month_filter = date('Y-m');
}

// Build query with optional filters
$sql = "SELECT i.*, u.name as issuer
        FROM issuance i
        LEFT JOIN users u ON i.issued_by = u.user_id
        WHERE 1=1";
$params = [];
$types = '';

if ($category_filter !== '') {
    $sql .= " AND i.category = ?";
    $params[] = $category_filter;
    $types .= 's';
}
if ($month_filter !== '') {
    $sql .= " AND DATE_FORMAT(i.date_issued, '%Y-%m') = ?";
    $params[] = $month_filter;
    $types .= 's';
}

$sql .= " ORDER BY i.date_issued DESC, i.issue_id DESC";

$stmt = $mysqli->prepare($sql);
if ($stmt === false) { die('Prepare failed: ' . $mysqli->error); }
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// map month YYYY-MM to label for header if possible
$displayMonthLabel = '';
if (!empty($month_filter)) {
    $dt = DateTime::createFromFormat('!Y-m', $month_filter);
    if ($dt) $displayMonthLabel = $dt->format('F Y');
    else $displayMonthLabel = $month_filter;
}
?>

<!-- Styles: reuse issuance card/table/modal system -->
<style>
  /* Print tweaks */
  @media print {
    .no-print, nav, .bg-light, .sidebar, .btn, .form-control, .form-select { display: none !important; }
    body { background: white; }
  }

  .table-card { background:#fff; border-radius:10px; padding:1rem; box-shadow:0 8px 24px rgba(0,0,0,0.06); }
  .d-flex{display:flex}.justify-content-between{justify-content:space-between}.align-items-center{align-items:center}
  .mb-2{margin-bottom:.5rem}.btn{display:inline-flex;align-items:center;gap:.5rem;padding:.375rem .6rem;border-radius:6px;border:1px solid transparent;text-decoration:none;cursor:pointer}
  .btn-sm{font-size:.85rem;padding:.275rem .5rem;border-radius:6px}
  .btn-primary{background:#0d6efd;color:#fff;border-color:#0d6efd}
  .btn-success{background:#198754;color:#fff;border-color:#198754}
  .btn-secondary{background:#6c757d;color:#fff;border-color:#6c757d}
  .btn-danger{background:#dc3545;color:#fff;border-color:#dc3545}
  .btn-outline-secondary{background:transparent;color:#495057;border-color:#ced4da}

  .badge-med{display:inline-block;padding:.25rem .5rem;border-radius:999px;background:#e6f4ff;color:#0b5bd7;font-weight:600;font-size:.82rem}
  .badge-sup{display:inline-block;padding:.25rem .5rem;border-radius:999px;background:#eaf6ea;color:#0b7a3a;font-weight:600;font-size:.82rem}

  label{display:block;font-size:.9rem;margin-bottom:.25rem;color:#333}
  input.form-control,select.form-control,textarea.form-control{width:100%;padding:.45rem .5rem;border:1px solid #dfe3e6;border-radius:6px}
  .table-modern{width:100%;border-collapse:collapse}
  .table-modern th, .table-modern td {padding:.6rem .5rem;border-bottom:1px solid #f1f3f5;text-align:left;vertical-align:middle}
  .table-responsive{overflow:auto}
</style>

<h3 style="margin-bottom:.5rem;">Issued Items Report</h3>
<div class="table-card">
  <div class="d-flex justify-content-between align-items-center no-print" 
     style="gap:14px; margin-bottom:12px; flex-wrap:wrap;">

    <!-- FILTER FORM ROW -->
    <form method="get" class="d-flex align-items-center" 
          style="gap:10px; flex-wrap:nowrap;">

        <!-- Category -->
        <div>
            <label style="font-size:.85rem; margin-bottom:2px;">Category</label>
            <select name="category" class="form-control form-control-sm" 
                    style="min-width:150px;">
                <option value="">All categories</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?php echo htmlspecialchars($c) ?>" 
                        <?php if($category_filter===$c) echo 'selected' ?>>
                        <?php echo htmlspecialchars($c) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Month -->
        <div>
            <label style="font-size:.85rem; margin-bottom:2px;">Month</label>
            <select name="month" class="form-control form-control-sm" 
                    style="min-width:150px;">
                <option value="">All months</option>
                <?php foreach ($month_options as $ym):
                    $label = DateTime::createFromFormat('!Y-m', $ym)
                        ? DateTime::createFromFormat('!Y-m', $ym)->format('F Y')
                        : $ym;
                ?>
                    <option value="<?php echo htmlspecialchars($ym) ?>" 
                        <?php if($month_filter===$ym) echo 'selected' ?>>
                        <?php echo htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Buttons -->
        <div class="d-flex align-items-end" style="gap:8px; margin-top:22px;">
            <button class="btn btn-sm btn-primary" style="padding: .45rem .8rem;">Filter</button>
            <a href="reports.php" class="btn btn-sm btn-outline-secondary" style="padding: .45rem .8rem;">Reset</a>
        </div>
    </form>

    <!-- PRINT BUTTON -->
    <button class="btn btn-sm btn-success no-print" onclick="window.print()">
        Print / Save as PDF
    </button>

</div>


  <div class="table-responsive" style="margin-top:.75rem;">
    <table class="table-modern w-100">
      <thead>
        <tr>
          <th style="width:110px;">Category</th>
          <th>Item</th>
          <th style="width:70px;">Qty</th>
          <th style="width:160px;">To</th>
          <th style="width:140px;">By</th>
          <th style="width:160px;">Date</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="6">No records found.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <td>
              <?php
                $cat = $r['category'] ?? '';
                if (strtolower($cat) === 'medicine') echo '<span class="badge-med">Medicine</span>';
                else echo '<span class="badge-sup">Supply</span>';
              ?>
            </td>
            <td>
              <div style="font-weight:600;"><?php echo htmlspecialchars($r['item_name']) ?></div>
              <div style="color:#6c757d;font-size:.85rem;"><?php echo htmlspecialchars($r['item_code'] ?? '') ?></div>
            </td>
            <td><?php echo (int)$r['quantity_issued'] ?></td>
            <td><?php echo htmlspecialchars($r['issued_to']) ?></td>
            <td><?php echo htmlspecialchars($r['issuer'] ?? '') ?></td>
            <td><?php echo htmlspecialchars($r['date_issued']) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Optional summary header for print view -->
<?php if (!empty($displayMonthLabel)): ?>
  <div style="margin-top:12px; font-size:.95rem; color:#555;">
    Showing records for: <strong><?php echo htmlspecialchars($displayMonthLabel); ?></strong>
    <?php if (!empty($category_filter)): ?> — Category: <strong><?php echo htmlspecialchars($category_filter); ?></strong><?php endif; ?>
  </div>
<?php endif; ?>
