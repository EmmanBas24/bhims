<?php
require_once 'header.php';
require_once 'functions.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'BHW';

if ($role === 'Head BHW') {
    // Head sees all logs
    $stmt = $mysqli->prepare('
        SELECT a.*, u.user_id AS log_user_id, u.role AS log_role
        FROM activity_logs a
        LEFT JOIN users u ON a.user_id = u.user_id
        ORDER BY timestamp DESC
    ');
} else {
    // BHW sees only their logs
    $stmt = $mysqli->prepare('
        SELECT a.*, u.user_id AS log_user_id, u.role AS log_role
        FROM activity_logs a
        LEFT JOIN users u ON a.user_id = u.user_id
        WHERE a.user_id = ?
        ORDER BY timestamp DESC
    ');
    $stmt->bind_param('i', $user_id);
}

$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<h3>Activity Logs</h3>

<div class="table-card">
    <table class="table table-modern w-100">
        <thead>
            <tr>
                <th>User ID</th>
                <th>Role</th>
                <th>Activity</th>
                <th>Timestamp</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="4">No activity logs found.</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td><?php echo htmlspecialchars($r['log_user_id'] ?? 'System'); ?></td>
                    <td><?php echo htmlspecialchars($r['log_role'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($r['activity_description']); ?></td>
                    <td><?php echo htmlspecialchars($r['timestamp']); ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
