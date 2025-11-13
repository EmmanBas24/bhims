<?php
$no_require = false;
require_once 'header.php';
require_once 'functions.php';
$total_med = count_table('medicine');
$total_sup = count_table('supplies');
$total_eq = count_table('equipment');
$alerts = low_stock_alerts();

// recent issuance
$stmt = $mysqli->prepare('SELECT issue_id, category, item_name, quantity_issued, issued_to, date_issued FROM issuance ORDER BY date_issued DESC LIMIT 8');
$stmt->execute();
$issued = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// recent activities
$stmt = $mysqli->prepare('SELECT a.log_id, u.name, a.activity_description, a.timestamp FROM activity_logs a LEFT JOIN users u ON a.user_id = u.user_id ORDER BY a.timestamp DESC LIMIT 8');
$stmt->execute();
$acts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="container-fluid">
  <div class="d-flex align-items-center mb-3">
    <h2 class="mb-0">Dashboard</h2>
  </div>

  <div class="stat-grid mb-3">
    <div class="stat-card med">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <h5>Total Medicine</h5>
          <div class="big"><?php echo $total_med ?></div>
        </div>
        <div><i class="bi bi-capsule" style="font-size:28px;color:#f97316;"></i></div>
      </div>
      <small class="text-muted">As of <?php echo date('Y-m-d') ?></small>
    </div>

    <div class="stat-card sup">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <h5>Total Supplies</h5>
          <div class="big"><?php echo $total_sup ?></div>
        </div>
        <div><i class="bi bi-box-seam" style="font-size:28px;color:#06b6d4;"></i></div>
      </div>
      <small class="text-muted">Stock levels overview</small>
    </div>

    <div class="stat-card eq">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <h5>Total Equipment</h5>
          <div class="big"><?php echo $total_eq ?></div>
        </div>
        <div><i class="bi bi-tools" style="font-size:28px;color:#7c3aed;"></i></div>
      </div>
      <small class="text-muted">Checked status</small>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-6">
      <div class="alert-low mb-3">
        <strong>Low Stock Alerts</strong>
        <ul class="mt-2 mb-0">
          <?php foreach ($alerts as $a): ?>
            <li><?php echo htmlspecialchars($a['type'] . ' - ' . $a['item_name'] . ' (qty: ' . $a['qty'] . ')') ?></li>
          <?php endforeach; ?>
          <?php if (empty($alerts)): ?><li>No low stock items.</li><?php endif; ?>
        </ul>
      </div>

      <div class="recent-card">
        <h5 style="margin-bottom:12px;">Recently Issued</h5>
        <table class="table table-sm">
          <thead><tr><th>Item</th><th>Qty</th><th>To</th><th>Date</th></tr></thead>
          <tbody>
          <?php foreach ($issued as $it): ?>
            <tr>
              <td><?php echo htmlspecialchars($it['item_name']) ?></td>
              <td><?php echo (int)$it['quantity_issued'] ?></td>
              <td><?php echo htmlspecialchars($it['issued_to']) ?></td>
              <td><?php echo htmlspecialchars($it['date_issued']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($issued)): ?><tr><td colspan="4">No issuance yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="recent-card mb-3">
        <h5 style="margin-bottom:12px;">Recent Activities</h5>
        <table class="table table-sm">
          <thead><tr><th>User</th><th>Activity</th><th>Date</th></tr></thead>
          <tbody>
          <?php foreach ($acts as $a): ?>
            <tr>
              <td><?php echo htmlspecialchars($a['name'] ?? 'System') ?></td>
              <td><?php echo htmlspecialchars($a['activity_description']) ?></td>
              <td><?php echo htmlspecialchars($a['timestamp']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="recent-card">
        <h5 style="margin-bottom:12px;">Quick Actions</h5>
        <div class="d-grid gap-2">
          <a href="medicine.php?action=add" class="btn btn-outline-primary btn-sm"><i class="bi bi-plus-circle"></i> Add Medicine</a>
          <a href="supplies.php?action=add" class="btn btn-outline-info btn-sm"><i class="bi bi-plus-circle"></i> Add Supply</a>
          <a href="issuance.php?action=add" class="btn btn-outline-success btn-sm"><i class="bi bi-send-fill"></i> Issue Item</a>
        </div>
      </div>
    </div>
  </div>

</div>

<?php require 'footer.php'; ?>
