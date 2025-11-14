<?php
$no_require = false;
require_once 'header.php';
require_once 'functions.php';

// counts (existing helpers)
$total_med = count_table('medicine');
$total_sup = count_table('supplies');
$total_eq = count_table('equipment');

// alerts
$alerts = low_stock_alerts();

// recent issuance and activities (keeps your queries)
$stmt = $mysqli->prepare('SELECT issue_id, category, item_name, quantity_issued, issued_to, date_issued FROM issuance ORDER BY date_issued DESC LIMIT 8');
$stmt->execute();
$issued = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $mysqli->prepare('SELECT a.log_id, u.name, a.activity_description, a.timestamp 
                          FROM activity_logs a 
                          LEFT JOIN users u ON a.user_id = u.user_id 
                          ORDER BY a.timestamp DESC LIMIT 8');
$stmt->execute();
$acts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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

  <!-- MAIN GRID: chart area left, right sidebar small widgets -->
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card p-3 mb-3 chart-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="mb-0">Overview</h5>
          <div class="d-flex gap-2 align-items-center">
            <select class="form-select form-select-sm" style="width:140px;">
              <option>This Month</option>
              <option>This Week</option>
            </select>
            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-three-dots-vertical"></i></button>
          </div>
        </div>

        <!-- BIG CHART PLACEHOLDER -->
        <div class="chart-placeholder mb-3">
          <!-- Decorative bars to mimic screenshot style (static) -->
          <div class="chart-bars">
            <?php for ($i=1;$i<=13;$i++): ?>
              <div class="bar">
                <div class="bar-fill" style="height:<?php echo rand(40,220) ?>px"></div>
              </div>
            <?php endfor; ?>
          </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-2">
          <small class="muted">Total overview (visual placeholder)</small>
          <div>
            <button class="btn btn-sm btn-outline-primary">Export</button>
          </div>
        </div>
      </div>

      <!-- RECENT ORDERS (table) -->
      <div class="card p-3 recent-orders">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="mb-0">Recent Issuance</h5>
          <a href="issuance.php" class="small">View All</a>
        </div>

        <div class="table-responsive">
          <table class="table table-borderless align-middle mb-0">
            <thead>
              <tr class="small text-muted">
                <th>Order ID</th>
                <th>Item</th>
                <th>Qty</th>
                <th>Issued To</th>
                <th>Status</th>
                <th class="text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($issued)): ?>
                <?php foreach ($issued as $it): ?>
                  <tr>
                    <td class="fw-medium">#<?php echo htmlspecialchars($it['issue_id']) ?></td>
                    <td><?php echo htmlspecialchars($it['item_name']) ?></td>
                    <td><?php echo (int)$it['quantity_issued'] ?></td>
                    <td><?php echo htmlspecialchars($it['issued_to']) ?></td>
                    <td><span class="badge bg-light text-dark">Completed</span></td>
                    <td class="text-end">
                      <a href="issuance.php?action=view&id=<?php echo (int)$it['issue_id'] ?>" class="text-muted"><i class="bi bi-three-dots-vertical"></i></a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="6" class="text-muted">No recent issuance.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <!-- RIGHT COLUMN: small widgets -->
    <div class="col-lg-4">
      <div class="card p-3 mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="mb-0">Orders By Time</h6>
          <small class="muted">Heatmap</small>
        </div>

      <div class="card p-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="mb-0">Quick Links</h6>
          <small class="muted">Shortcuts</small>
        </div>

        <div class="d-grid gap-2">
          <a href="medicine.php" class="btn btn-sm btn-outline-primary text-start"><i class="bi bi-capsule me-2"></i> Medicines</a>
          <a href="supplies.php" class="btn btn-sm btn-outline-info text-start"><i class="bi bi-box-seam me-2"></i> Supplies</a>
          <a href="issuance.php" class="btn btn-sm btn-outline-success text-start"><i class="bi bi-send-fill me-2"></i> Issue Item</a>
        </div>
      </div>

      <div class="card p-3 mt-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="mb-0">Top Products</h6>
          <small class="muted">This Month</small>
        </div>

        <div class="top-products-list">
          <?php
            // Example placeholder list
            $top = [
              ['name'=>'Paracetamol','sold'=>120],
              ['name'=>'Ibuprofen','sold'=>98],
              ['name'=>'Bandage','sold'=>76],
              ['name'=>'Syringe','sold'=>55],
            ];
          ?>
          <?php foreach ($top as $p): ?>
            <div class="d-flex align-items-center justify-content-between py-2 border-bottom">
              <div>
                <div class="fw-medium"><?php echo htmlspecialchars($p['name']) ?></div>
                <small class="muted">Sold: <?php echo (int)$p['sold'] ?></small>
              </div>
              <div class="text-muted">$<?php echo rand(5,200) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>


    </div>
  </div>
</div>

<!-- Styles (component scoped) -->
<style>
  .muted { color: #6b7280; }
  .fw-medium { font-weight:600; }
  .metric-card {
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 6px 18px rgba(12, 18, 26, 0.06);
    border: 1px solid rgba(15,23,42,0.03);
  }
  .metric-big { font-size:1.45rem; font-weight:700; margin-top:6px; }
  .metric-dot { width:12px; height:12px; border-radius:50%; }

  .metric-icon { font-size:22px; color:#0f172a; opacity:.7; }

  .chart-card { border-radius:12px; background:#fff; border:1px solid rgba(12,18,26,0.03); }
  .chart-placeholder { height:200px; display:flex; align-items:end; }
  .chart-bars { width:100%; display:flex; gap:10px; align-items:end;  background:linear-gradient(180deg,#fbfdff,#ffffff); border-radius:8px; }
  .chart-bars .bar { flex:1; display:flex; align-items:end; justify-content:center; }
  .chart-bars .bar .bar-fill { width:70%; background: linear-gradient(180deg,#164e63,#0ea5a9); border-radius:8px 8px 0 0; opacity:.95; }

  .recent-orders .table td, .recent-orders .table th { vertical-align: middle; }

  .heatmap-placeholder .heat-grid { display:flex; flex-direction:column; gap:6px; }
  .heat-row { display:flex; gap:6px; }
  .heat-cell { width:26px; height:26px; border-radius:4px; background:#f1f5f9; }
  .heat-cell.level-0 { background:#f1f5f9; opacity:0.6; }
  .heat-cell.level-1 { background:#c7f9cc; }
  .heat-cell.level-2 { background:#66d3a9; }
  .heat-cell.level-3 { background:#0ea5a9; color:#fff; }

  .top-products-list > div:last-child { border-bottom: 0; }

  /* responsive */
  @media (max-width: 991px) {
    .chart-placeholder { height:160px; }
  }
  @media (max-width: 575px) {
    .metric-big { font-size:1.2rem; }
  }
</style>
