<?php
// dashboard.php
// Redesigned professional dashboard (medicine-only) with a Quick Link to Reports
$no_require = false;
require_once 'header.php';
require_once 'functions.php';

// counts (medicine only)
$total_med = count_table('medicine');
$total_items = $total_med;

// alerts
$alerts = low_stock_alerts();

// recent issuance (medicine only)
$stmt = $mysqli->prepare(
  'SELECT issue_id, item_name, quantity_issued, issued_to, date_issued
   FROM issuance
   ORDER BY date_issued DESC
   LIMIT 8'
);
if ($stmt === false) {
    $issued = [];
} else {
    $stmt->execute();
    $issued = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// path to uploaded image (developer requested local path be used)
$logo_path = '/mnt/data/c145234f-9f69-4b5a-9062-e3a06f323f00.png';
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Dashboard — Barangay Health Inventory</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    /* Layout & utility (kept compact, professional) */
    :root{--bg:#f6fbfb;--card:#ffffff;--muted:#6b7280;--accent:#0b5f74;--accent-2:#0f9aa8;--danger:#d23b3b;--glass:rgba(255,255,255,0.7);--border:#e6eef0;}
    html,body{height:100%}
    body{background:linear-gradient(180deg,#f7fcfc 0%,var(--bg) 100%);font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial;color:#173738;margin:0;padding:0}
    .container-fluid{max-width:1200px}
    .topbar{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:18px 6px}
    .brand{display:flex;align-items:center;gap:12px}
    .brand img{width:52px;height:52px;object-fit:cover;border-radius:8px;border:1px solid rgba(0,0,0,0.06)}
    .brand h1{font-size:18px;margin:0;color:var(--accent);font-weight:700}
    .brand p{margin:0;font-size:12px;color:var(--muted)}
    /* CARD GRID */
    .grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin:14px 0}
    @media (max-width:992px){.grid{grid-template-columns:repeat(2,1fr)}}
    @media (max-width:576px){.grid{grid-template-columns:1fr}}
    .metric-card{background:var(--card);border-radius:12px;padding:18px;border:1px solid var(--border);box-shadow:0 8px 30px rgba(10,30,30,0.04);display:flex;flex-direction:column;justify-content:space-between;min-height:110px}
    .metric-top{display:flex;align-items:flex-start;justify-content:space-between;gap:10px}
    .metric-title{font-size:13px;color:var(--muted);margin-bottom:6px}
    .metric-value{font-size:28px;font-weight:800;color:#0b4f58}
    .metric-sub{font-size:12px;color:#2b7f74;margin-top:8px}
    .metric-icon{font-size:26px;opacity:.9}
    .metric-dot{width:12px;height:12px;border-radius:50%}
    /* Quick actions full width card */
    .quick-card{display:flex;gap:12px;align-items:center;justify-content:space-between;padding:14px;border-radius:12px;background:linear-gradient(90deg,#ffffff,#fbffff);border:1px solid var(--border);box-shadow:0 8px 22px rgba(10,30,30,0.03)}
    .quick-actions{display:flex;gap:10px;flex-wrap:wrap}
    .quick-actions .btn{min-width:160px;text-align:left;border-radius:10px;padding:.6rem .85rem}
    .report-quick{background:linear-gradient(135deg,var(--accent),var(--accent-2));color:#fff;border:0;box-shadow:0 8px 20px rgba(11,95,116,0.18)}
    .icon-small{width:36px;height:36px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;background:var(--accent-2);color:#fff;margin-right:10px}
    /* Recent table */
    .recent-card{margin-top:12px;padding:14px;border-radius:12px;background:var(--card);border:1px solid var(--border);box-shadow:0 10px 30px rgba(12,18,26,0.04)}
    .recent-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
    .recent-head h3{margin:0;font-size:16px;color:#153e3e}
    .recent-actions{display:flex;gap:8px;align-items:center}
    .clean-table{width:100%;border-collapse:collapse;background:var(--card);border-radius:8px;overflow:hidden;border:1.6px solid #7a8a8c}
    .clean-table thead th{background:#eaf6f6;color:#08373a;font-weight:700;padding:12px 14px;text-align:left;border-right:1px solid rgba(0,0,0,0.02)}
    .clean-table thead th:last-child{border-right:0}
    .clean-table tbody td{padding:12px 14px;border-top:1px solid #eef6f6}
    .clean-table tbody tr:nth-child(even){background:#fbffff}
    .status-pill{display:inline-block;padding:6px 10px;border-radius:999px;font-weight:700;font-size:12px}
    .status-available{background:#e7f9ef;color:#0b6b3d;border:1px solid #cdebd2}
    .status-low{background:#fff6e6;color:#9a6200;border:1px solid #ffe8bf}
    .status-out{background:#ffecec;color:#a80000;border:1px solid #ffbdbd}
    /* small helpers */
    .muted{color:var(--muted)}
    .small{font-size:13px}
    .text-muted{color:var(--muted)}
    /* footer quicklinks */
    .quick-links{display:flex;gap:8px;flex-wrap:wrap}
    .quick-link{padding:10px 12px;border-radius:10px;background:#fff;border:1px solid #eef6f6;color:#0b5560;text-decoration:none;display:inline-flex;align-items:center;gap:8px;box-shadow:0 6px 18px rgba(12,18,26,0.03)}
    /* Modest responsive */
    @media (max-width:720px){.topbar{padding:12px 6px}.metric-value{font-size:22px}}
  </style>
</head>
<body>


    <!-- Metrics -->
    <div class="grid" role="region" aria-label="Top metrics">
      <div class="metric-card">
        <div class="metric-top">
          <div>
            <div class="metric-title">Total Inventory Items</div>
            <div class="metric-value"><?php echo (int)$total_items; ?></div>
            <div class="metric-sub muted">Only medicines counted</div>
          </div>
          <div style="text-align:right">
            <div class="metric-icon"><i class="bi bi-box-seam" style="font-size:28px;color:#0b7f6f"></i></div>
          </div>
        </div>
        <div style="margin-top:10px" class="muted small">Updated just now</div>
      </div>

      <div class="metric-card">
        <div class="metric-top">
          <div>
            <div class="metric-title">Total Medicine</div>
            <div class="metric-value"><?php echo (int)$total_med; ?></div>
            <div class="metric-sub muted">Active master records</div>
          </div>
          <div>
            <i class="bi bi-capsule metric-icon" style="color:#0b5f74"></i>
          </div>
        </div>
      </div>

      <div class="metric-card">
        <div class="metric-top">
          <div>
            <div class="metric-title">Low Stock</div>
            <div class="metric-value"><?php echo (int)count($alerts); ?></div>
            <div class="metric-sub muted">Critical items</div>
          </div>
          <div>
            <i class="bi bi-exclamation-triangle-fill metric-icon" style="color:var(--danger)"></i>
          </div>
        </div>
      </div>

      <div class="metric-card">
        <div class="metric-top">
          <div>
            <div class="metric-title">Recent Issues</div>
            <div class="metric-value"><?php echo (int)count($issued); ?></div>
            <div class="metric-sub muted">Latest entries</div>
          </div>
          <div>
            <i class="bi bi-send-fill metric-icon" style="color:#0b7f74"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick actions -->
    <div class="quick-card">
      <div style="display:flex;align-items:center;gap:12px">
        <div class="icon-small"><i class="bi bi-lightning-charge-fill"></i></div>
        <div>
          <div style="font-weight:700">Quick Actions</div>
          <div class="muted small">Common tasks for Barangay Health Workers</div>
        </div>
      </div>

      <div class="quick-actions">
        <a href="medicine.php" class="btn btn-outline-primary"><i class="bi bi-capsule me-2"></i> Manage Medicines</a>
        <a href="issuance.php" class="btn btn-outline-success"><i class="bi bi-send-fill me-2"></i> Issue Item</a>
        <a href="reports.php" class="btn report-quick"><i class="bi bi-file-earmark-text me-2"></i> Reports</a>
       
      </div>
    </div>

    <!-- Recent Issuance -->
    <div class="recent-card">
      <div class="recent-head">
        <h3>Recent Issuance</h3>
        <div class="recent-actions">
          <a href="issuance.php" class="btn btn-sm btn-light">View All</a>
          <a href="reports.php#issued" class="btn btn-sm btn-outline-primary">Export</a>
        </div>
      </div>

      <div class="table-responsive">
        <table class="clean-table" role="table" aria-label="Recent issuance">
          <thead>
            <tr>
              <th>Issue ID</th>
              <th>Item</th>
              <th>Qty</th>
              <th>Issued To</th>
              <th>Date Issued</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($issued)): ?>
              <?php foreach ($issued as $it): ?>
                <tr>
                  <td class="fw-medium">#<?php echo htmlspecialchars($it['issue_id']); ?></td>
                  <td><?php echo htmlspecialchars($it['item_name']); ?></td>
                  <td><?php echo (int)$it['quantity_issued']; ?></td>
                  <td><?php echo htmlspecialchars($it['issued_to']); ?></td>
                  <td class="text-muted small"><?php echo date("M d, Y • g:i A", strtotime($it['date_issued'])); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="5" class="text-center muted py-4">No recent issuance.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>



  <!-- Optional small script for a11y focus and keyboard shortcuts -->
  <script>
    // quick keyboard shortcut: press "r" to open reports
    document.addEventListener('keydown', function(e){
      if (e.key === 'r' && !e.ctrlKey && !e.metaKey && !e.altKey) {
        window.location.href = 'reports.php';
      }
    });
  </script>
</body>
</html>
