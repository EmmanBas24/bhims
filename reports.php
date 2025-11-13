<?php
require_once 'header.php';
require_once 'functions.php';


// SERVER-SIDE ROLE CHECK: only Head BHW can access reports
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Head BHW') {
    // show friendly error and stop executing the rest of the page
    echo '<div class="alert alert-danger">Access denied. Reports are available to Head BHW only.</div>';
    require 'footer.php';
    exit;
}
$which = $_GET['which'] ?? 'medicine';
if ($which === 'medicine') {
    $stmt = $mysqli->prepare('SELECT * FROM medicine');
} elseif ($which === 'supplies') {
    $stmt = $mysqli->prepare('SELECT * FROM supplies');
} else {
    $stmt = $mysqli->prepare('SELECT * FROM equipment');
}
$stmt->execute(); $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
?>
<h3>Reports - <?php echo htmlspecialchars(ucfirst($which)) ?></h3>
<div class="mb-2">
  <a class="btn btn-sm btn-outline-primary" href="?which=medicine">Medicine</a>
  <a class="btn btn-sm btn-outline-primary" href="?which=supplies">Supplies</a>
  <a class="btn btn-sm btn-outline-primary" href="?which=equipment">Equipment</a>
  <button onclick="window.print()" class="btn btn-sm btn-success">Print / Save as PDF</button>
</div>
<table class="table table-sm">
  <thead><tr>
    <?php if ($which === 'medicine'): ?><th>Code</th><th>Name</th><th>Expiry</th><th>Qty</th><?php else: ?><th>Code</th><th>Name</th><th>Qty</th><?php endif; ?>
    <th>Supplier</th><th>Date Received</th>
  </tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?php echo htmlspecialchars($r['item_code']) ?></td>
      <td><?php echo htmlspecialchars($r['item_name']) ?></td>
      <?php if ($which === 'medicine'): ?><td><?php echo htmlspecialchars($r['expiry_date']) ?></td><?php endif; ?>
      <td><?php echo htmlspecialchars($r['quantity'] ?? '') ?></td>
      <td><?php echo htmlspecialchars($r['supplier']) ?></td>
      <td><?php echo htmlspecialchars($r['date_received']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php require 'footer.php'; ?>