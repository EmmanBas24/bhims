<?php
require_once 'header.php';
require_once 'functions.php';
$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'list';
if ($action === 'add' && $_SERVER['REQUEST_METHOD']==='POST') {
    $item_code = $_POST['item_code']; $item_name = $_POST['item_name']; $quantity = intval($_POST['quantity']);
    $supplier = $_POST['supplier']; $condition = $_POST['condition']; $status = $_POST['status']; $date_received = $_POST['date_received'];
    $stmt = $mysqli->prepare('INSERT INTO equipment (item_code,item_name,quantity,supplier,`condition`,status,date_received,added_by) VALUES (?,?,?,?,?,?,?,?)');
    $stmt->bind_param('ssisssii', $item_code,$item_name,$quantity,$supplier,$condition,$status,$date_received,$user_id);
    $stmt->execute(); $stmt->close();
    log_activity($user_id,'Added equipment: '.$item_name);
    header('Location: equipment.php'); exit;
}
if ($action === 'edit' && $_SERVER['REQUEST_METHOD']==='POST') {
    $id = intval($_GET['id'] ?? 0);
    $item_code = $_POST['item_code']; $item_name = $_POST['item_name']; $quantity = intval($_POST['quantity']);
    $supplier = $_POST['supplier']; $condition = $_POST['condition']; $status = $_POST['status']; $date_received = $_POST['date_received'];
    $stmt = $mysqli->prepare('UPDATE equipment SET item_code=?,item_name=?,quantity=?,supplier=?,`condition`=?,status=?,date_received=?,added_by=? WHERE equipment_id=?');
    $stmt->bind_param('ssisssii', $item_code,$item_name,$quantity,$supplier,$condition,$status,$date_received,$user_id,$id);
    $stmt->execute(); $stmt->close();
    log_activity($user_id,'Updated equipment ID '.$id);
    header('Location: equipment.php'); exit;
}
if ($action === 'delete') {
    $id = intval($_GET['id'] ?? 0);
    $stmt = $mysqli->prepare('DELETE FROM equipment WHERE equipment_id = ?');
    $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();
    log_activity($user_id,'Deleted equipment ID '.$id);
    header('Location: equipment.php'); exit;
}
if ($action === 'list') {
    $stmt = $mysqli->prepare('SELECT * FROM equipment ORDER BY date_received DESC');
    $stmt->execute(); $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
}
?>
<h3>Equipment</h3>
<a class="btn btn-sm btn-primary mb-2" href="?action=add">Add Equipment</a>
<table class="table table-sm">
  <thead><tr><th>Item Code</th><th>Name</th><th>Qty</th><th>Supplier</th><th>Condition</th><th>Status</th><th>Date Received</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?php echo htmlspecialchars($r['item_code']) ?></td>
      <td><?php echo htmlspecialchars($r['item_name']) ?></td>
      <td><?php echo (int)$r['quantity'] ?></td>
      <td><?php echo htmlspecialchars($r['supplier']) ?></td>
      <td><?php echo htmlspecialchars($r['condition']) ?></td>
      <td><?php echo htmlspecialchars($r['status']) ?></td>
      <td><?php echo htmlspecialchars($r['date_received']) ?></td>
      <td>
        <a class="btn btn-sm btn-secondary" href="?action=edit&id=<?php echo $r['equipment_id']?>">Edit</a>
        <a class="btn class="btn btn-sm btn-danger" href="?action=delete&id=<?php echo $r['equipment_id']?>" onclick="return confirm('Delete?')">Delete</a>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php if ($action === 'add'): ?>
<h3>Add Equipment</h3>
<form method="post">
  <div class="mb-2"><label>Item Code</label><input name="item_code" class="form-control"></div>
  <div class="mb-2"><label>Item Name</label><input name="item_name" class="form-control" required></div>
  <div class="mb-2"><label>Quantity</label><input type="number" name="quantity" class="form-control" value="0"></div>
  <div class="mb-2"><label>Supplier</label><input name="supplier" class="form-control"></div>
  <div class="mb-2"><label>Condition</label><select name="condition" class="form-control"><option>Good</option><option>Broken</option><option>Damaged</option></select></div>
  <div class="mb-2"><label>Status</label><select name="status" class="form-control"><option>Available</option><option>Unavailable</option></select></div>
  <div class="mb-2"><label>Date Received</label><input type="date" name="date_received" class="form-control"></div>
  <button class="btn btn-primary">Save</button>
  <a href="equipment.php" class="btn btn-secondary">Cancel</a>
</form>
<?php endif; ?>
<?php if ($action === 'edit'):
$id = intval($_GET['id'] ?? 0);
$stmt = $mysqli->prepare('SELECT * FROM equipment WHERE equipment_id = ? LIMIT 1'); $stmt->bind_param('i',$id); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
?>
<h3>Edit Equipment</h3>
<form method="post">
  <div class="mb-2"><label>Item Code</label><input name="item_code" class="form-control" value="<?php echo htmlspecialchars($row['item_code']) ?>"></div>
  <div class="mb-2"><label>Item Name</label><input name="item_name" class="form-control" value="<?php echo htmlspecialchars($row['item_name']) ?>" required></div>
  <div class="mb-2"><label>Quantity</label><input type="number" name="quantity" class="form-control" value="<?php echo htmlspecialchars($row['quantity']) ?>"></div>
  <div class="mb-2"><label>Supplier</label><input name="supplier" class="form-control" value="<?php echo htmlspecialchars($row['supplier']) ?>"></div>
  <div class="mb-2"><label>Condition</label><select name="condition" class="form-control"><option>Good</option><option>Broken</option><option>Damaged</option></select></div>
  <div class="mb-2"><label>Status</label><select name="status" class="form-control"><option>Available</option><option>Unavailable</option></select></div>
  <div class="mb-2"><label>Date Received</label><input type="date" name="date_received" class="form-control" value="<?php echo htmlspecialchars($row['date_received']) ?>"></div>
  <button class="btn btn-primary">Update</button>
  <a href="equipment.php" class="btn btn-secondary">Cancel</a>
</form>
<?php endif; ?>
<?php require 'footer.php'; ?>