<?php
$no_require = false;
require_once 'header.php';
require_once 'functions.php';

// counts
$total_med = count_table('medicine');
$total_sup = count_table('supplies');
$total_eq = count_table('equipment');

// alerts
$alerts = low_stock_alerts();

// recent issuance
$stmt = $mysqli->prepare(
  'SELECT issue_id, category, item_name, quantity_issued, issued_to, date_issued
   FROM issuance ORDER BY date_issued DESC LIMIT 8'
);
$stmt->execute();
$issued = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="container-fluid dashboard-v2 py-4">

  <!-- TOP METRICS -->
  <div class="row g-3 mb-3 align-items-stretch">
    <div class="col-md-3">
      <div class="metric-card h-100 p-3">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <small class="muted">Total Inventory Items</small>
            <div class="metric-big"><?php echo ($total_med + $total_sup + $total_eq) ?></div>
          </div>
          <div class="metric-dot bg-success"></div>
        </div>
        <div class="muted mt-2">+2.4% Since last week</div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="metric-card h-100 p-3">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <small class="muted">Total Medicine</small>
            <div class="metric-big"><?php echo $total_med ?></div>
          </div>
          <i class="bi bi-capsule metric-icon"></i>
        </div>
        <div class="muted mt-2">+1.3% Since last week</div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="metric-card h-100 p-3">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <small class="muted">Low Stock</small>
            <div class="metric-big"><?php echo count($alerts) ?></div>
          </div>
          <i class="bi bi-exclamation-triangle-fill metric-icon text-danger"></i>
        </div>
        <div class="muted mt-2">Critical items</div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="metric-card h-100 p-3">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <small class="muted">Recent Issues</small>
            <div class="metric-big"><?php echo count($issued) ?></div>
          </div>
          <i class="bi bi-send-fill metric-icon"></i>
        </div>
        <div class="muted mt-2">Last 7 records</div>
      </div>
    </div>
  </div>


  <!-- QUICK ACTIONS ROW -->
  <div class="row g-3 mb-3">
    <div class="col-12">
      <div class="card p-3 small-widget-card h-100">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="mb-0">Quick Actions</h6>
          <small class="muted">Shortcuts</small>
        </div>

        <div class="d-flex flex-column flex-md-row gap-2">
          <a href="medicine.php" class="btn btn-outline-primary btn-lg flex-fill text-start">
            <i class="bi bi-capsule me-2"></i> Medicines
          </a>

          <a href="supplies.php" class="btn btn-outline-info btn-lg flex-fill text-start">
            <i class="bi bi-box-seam me-2"></i> Supplies
          </a>

          <a href="issuance.php" class="btn btn-outline-success btn-lg flex-fill text-start">
            <i class="bi bi-send-fill me-2"></i> Issue Item
          </a>
        </div>
      </div>
    </div>
  </div>


  <!-- RECENT ISSUANCE (CLEAN VERSION) -->
  <div class="card p-3 recent-orders shadow-sm border-0 rounded-3">

    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0">Recent Issuance</h5>

      <a href="issuance.php" class="btn btn-sm btn-light px-3 py-1 rounded-pill view-all-btn">
        View All
      </a>
    </div>

    <div class="table-responsive">
      <table class="table table-hover clean-table align-middle mb-0">
        <thead>
          <tr>
            <th class="text-muted small fw-semibold">Order ID</th>
            <th class="text-muted small fw-semibold">Item</th>
            <th class="text-muted small fw-semibold">Qty</th>
            <th class="text-muted small fw-semibold">Issued To</th>
            <th class="text-muted small fw-semibold">Date Issued</th>
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
                <td class="text-muted">
                  <?php echo date("M d, Y • g:i A", strtotime($it['date_issued'])); ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="5" class="text-center text-muted py-4">
                No recent issuance.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>

      </table>
    </div>
  </div>

</div>


<!-- STYLES -->
<style>
  .muted { color:#6b7280; }
  .fw-medium { font-weight:600; }

  .metric-card {
    background:#fff;
    border-radius:12px;
    box-shadow:0 6px 18px rgba(12,18,26,0.06);
    border:1px solid rgba(15,23,42,0.03);
  }
  .metric-big { font-size:1.45rem; font-weight:700; }
  .metric-dot { width:12px; height:12px; border-radius:50%; }
  .metric-icon { font-size:22px; opacity:.75; }


  /* Quick Actions */
  .small-widget-card {
    border-radius:12px;
    border:1px solid #e5e7eb;
    box-shadow:0 6px 18px rgba(12,18,26,0.06);
    background:#fff;
  }
  .small-widget-card .btn {
    min-height:44px;
    display:flex;
    align-items:center;
  }
  .small-widget-card .btn-lg { 
    padding:.55rem 1rem;
    border-radius:8px;
  }


  /* Recent Issuance Table */
  .clean-table thead th {
    border-bottom:1px solid #e5e7eb;
    padding-bottom:8px;
  }
  .clean-table tbody tr:hover {
    background:#f9fafb;
  }
  .clean-table td {
    padding-top:10px;
    padding-bottom:10px;
  }

  /* View All Button */
  .view-all-btn {
    border:1px solid #d1d5db;
    background:#f8fafc;
    font-size:.75rem;
    transition:.2s;
  }
  .view-all-btn:hover {
    background:#e2e8f0;
    border-color:#cbd5e1;
  }

  .recent-orders {
    border-radius:12px !important;
    border:1px solid #e5e7eb !important;
  }
</style>
