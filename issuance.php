<?php
require_once 'header.php';
require_once 'functions.php';
$user_id = $_SESSION['user_id'];

$action = $_GET['action'] ?? 'list';
if ($action === 'add' && $_SERVER['REQUEST_METHOD']==='POST') {
    $category = $_POST['category']; $item_code = $_POST['item_code']; $item_name = $_POST['item_name'];
    $quantity_issued = intval($_POST['quantity_issued']); $issued_to = $_POST['issued_to']; $purpose = $_POST['purpose'];
    $stmt = $mysqli->prepare('INSERT INTO issuance (category,item_code,item_name,quantity_issued,issued_to,purpose,issued_by) VALUES (?,?,?,?,?,?,?)');
    $stmt->bind_param('sssissi', $category,$item_code,$item_name,$quantity_issued,$issued_to,$purpose,$user_id);
    $stmt->execute(); $stmt->close();
    if ($category === 'Medicine') {
        $stmt = $mysqli->prepare('UPDATE medicine SET quantity = quantity - ? WHERE item_code = ? LIMIT 1');
        $stmt->bind_param('is', $quantity_issued, $item_code); $stmt->execute(); $stmt->close();
    } else {
        $stmt = $mysqli->prepare('UPDATE supplies SET quantity = quantity - ? WHERE item_code = ? LIMIT 1');
        $stmt->bind_param('is', $quantity_issued, $item_code); $stmt->execute(); $stmt->close();
    }
    log_activity($user_id,'Issued ' . $quantity_issued . ' x ' . $item_name . ' to ' . $issued_to);
    header('Location: issuance.php'); exit;
}
if ($action === 'delete') {
    $id = intval($_GET['id'] ?? 0);
    $stmt = $mysqli->prepare('DELETE FROM issuance WHERE issue_id = ?');
    $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();
    log_activity($user_id,'Deleted issuance ID ' . $id);
    header('Location: issuance.php'); exit;
}
if ($action === 'list') {
    $stmt = $mysqli->prepare('SELECT i.*, u.name as issuer FROM issuance i LEFT JOIN users u ON i.issued_by = u.user_id ORDER BY date_issued DESC');
    $stmt->execute(); $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
}
?>
<h3>Issuance</h3>
<a class="btn btn-sm btn-primary mb-2" href="?action=add">Issue Item</a>
<?php if ($action === 'add'): ?>
<form method="post">
  <div class="mb-2"><label>Category</label>
    <select name="category" class="form-control" required>
      <option>Medicine</option><option>Supply</option>
    </select>
  </div>
  <div class="mb-2"><label>Item Code</label><input name="item_code" class="form-control"></div>
  <div class="mb-2"><label>Item Name</label><input name="item_name" class="form-control" required></div>
  <div class="mb-2"><label>Quantity</label><input type="number" name="quantity_issued" class="form-control" value="1"></div>
  <div class="mb-2"><label>Issued To</label><input name="issued_to" class="form-control"></div>
  <div class="mb-2"><label>Purpose</label><textarea name="purpose" class="form-control"></textarea></div>
  <button class="btn btn-primary">Issue</button>
  <a href="issuance.php" class="btn btn-secondary">Cancel</a>
</form>
<?php else: ?>
<table class="table table-sm">
  <thead><tr><th>Category</th><th>Item</th><th>Qty</th><th>To</th><th>By</th><th>Date</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?php echo htmlspecialchars($r['category']) ?></td>
      <td><?php echo htmlspecialchars($r['item_name']) ?></td>
      <td><?php echo (int)$r['quantity_issued'] ?></td>
      <td><?php echo htmlspecialchars($r['issued_to']) ?></td>
      <td><?php echo htmlspecialchars($r['issuer']) ?></td>
      <td><?php echo htmlspecialchars($r['date_issued']) ?></td>
      <td><a class="btn btn-sm btn-danger" href="?action=delete&id=<?php echo $r['issue_id']?>" onclick="return confirm('Delete?')">Delete</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php require 'footer.php'; ?>