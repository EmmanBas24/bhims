<?php
require_once 'header.php';
require_once 'functions.php';
$user_id = $_SESSION['user_id'];
if ($_SESSION['role'] === 'Head BHW') {
    $stmt = $mysqli->prepare('SELECT a.*, u.name FROM activity_logs a LEFT JOIN users u ON a.user_id = u.user_id ORDER BY timestamp DESC');
    $stmt->execute(); $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
} else {
    $stmt = $mysqli->prepare('SELECT a.*, u.name FROM activity_logs a LEFT JOIN users u ON a.user_id = u.user_id WHERE a.user_id = ? ORDER BY timestamp DESC');
    $stmt->bind_param('i',$user_id); $stmt->execute(); $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
}
?>
<h3>Activity Logs</h3>
<table class="table table-sm">
  <thead><tr><th>User</th><th>Activity</th><th>Timestamp</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?php echo htmlspecialchars($r['name'] ?? 'System') ?></td>
      <td><?php echo htmlspecialchars($r['activity_description']) ?></td>
      <td><?php echo htmlspecialchars($r['timestamp']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php require 'footer.php'; ?>