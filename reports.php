<?php
require_once 'header.php';
require_once 'functions.php';
require_login();

// ACCESS CONTROL: Only Head BHW
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Head BHW') {
    echo "<div class='alert alert-danger m-4'>Access denied. Reports are available only to Head BHW.</div>";
    require 'footer.php';
    exit;
}

// CURRENT MONTH default
$currentMonth = date('m');
$selectedMonth = $_GET['month'] ?? $currentMonth;

// Fetch issuance data for selected month
$stmt = $mysqli->prepare("
    SELECT issue_id, item_name, quantity_issued, issued_to, purpose, date_issued 
    FROM issuance 
    WHERE MONTH(date_issued) = ?
    ORDER BY date_issued DESC
");

$stmt->bind_param('i', $selectedMonth);
$stmt->execute();
$result = $stmt->get_result();
$rows = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Month names for dropdown
$months = [
    1=>"January",2=>"February",3=>"March",4=>"April",5=>"May",6=>"June",
    7=>"July",8=>"August",9=>"September",10=>"October",11=>"November",12=>"December"
];
?>

<style>
/* Hide buttons and sidebar during print */
@media print {
    .no-print, nav, .bg-light, .sidebar, .btn, .form-select {
        display: none !important;
    }
    body {
        background: white;
    }
}
</style>

<div class="container-fluid mt-4">
    <h4 class="text-center fw-bold">Barangay Health Inventory System</h4>
    <h5 class="text-center">Issued Items Report</h5>
    <h6 class="text-center mb-4">Month: <?php echo $months[intval($selectedMonth)]; ?></h6>

    <!-- Filter + Print Button -->
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <form method="GET" class="d-flex">
            <select name="month" class="form-select me-2" required>
                <?php foreach ($months as $num => $name): ?>
                    <option value="<?php echo $num; ?>" <?php if ($num == $selectedMonth) echo 'selected'; ?>>
                        <?php echo $name; ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button class="btn btn-primary">Filter</button>
        </form>

        <button class="btn btn-success no-print" onclick="window.print()">Print / Save as PDF</button>
    </div>

    <!-- Table -->
    <div class="table-responsive">
        <table class="table table-sm table-striped table-bordered">
            <thead class="table-dark">
                <tr>
                    <th>Issue ID</th>
                    <th>Resident Name</th>
                    <th>Item Name</th>
                    <th>Quantity</th>
                    <th>Purpose</th>
                    <th>Date Issued</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted">
                            No records found for this month.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?php echo $r['issue_id']; ?></td>
                        <td><?php echo htmlspecialchars($r['issued_to']); ?></td>
                        <td><?php echo htmlspecialchars($r['item_name']); ?></td>
                        <td><?php echo $r['quantity_issued']; ?></td>
                        <td><?php echo htmlspecialchars($r['purpose']); ?></td>
                        <td><?php echo date("F d, Y", strtotime($r['date_issued'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<?php require 'footer.php'; ?>
