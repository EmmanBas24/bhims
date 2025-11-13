<?php
$no_require = false;
require_once 'header.php';
require_once 'functions.php';
$total_med = count_table('medicine');
$total_sup = count_table('supplies');
$total_eq = count_table('equipment');
$alerts = low_stock_alerts();

$stmt = $mysqli->prepare('SELECT issue_id, category, item_name, quantity_issued, issued_to, date_issued FROM issuance ORDER BY date_issued DESC LIMIT 8');
$stmt->execute();
$issued = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $mysqli->prepare('SELECT a.log_id, u.name, a.activity_description, a.timestamp FROM activity_logs a LEFT JOIN users u ON a.user_id = u.user_id ORDER BY a.timestamp DESC LIMIT 8');
$stmt->execute();
$acts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<h3>Dashboard</h3>
<div class="row">
  <div class="col-md-4">
    <div class="card p-3 mb-3">
      <h5>Total Medicine</h5>
      <h2><?php echo $total_med ?></h2>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card p-3 mb-3">
      <h5>Total Supplies</h5>
      <h2><?php echo $total_sup ?></h2>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card p-3 mb-3">
      <h5>Total Equipment</h5>
      <h2><?php echo $total_eq ?></h2>
    </div>
  </div>
</div>
<div class="row">
  <div class="col-md-6">
    <h5>Low Stock Alerts</h5>
    <ul>
      <?php foreach ($alerts as $a): ?>
        <li><?php echo htmlspecialchars($a['type'] . ' - ' . $a['item_name'] . ' (qty: ' . $a['qty'] . ')') ?></li>
      <?php endforeach; ?>
      <?php if (empty($alerts)): ?><li>No low stock items.</li><?php endif; ?>
    </ul>
  </div>
  <div class="col-md-6">
    <h5>Recently Issued</h5>
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

<h5>Recent Activities</h5>
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

<?php require 'footer.php'; ?>