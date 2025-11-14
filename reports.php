<?php
require_once 'config.php';
require_once 'header.php';
require_once 'functions.php';
require_login();

// ACCESS CONTROL: Only Head BHW
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Head BHW') {
    echo "<div class='alert alert-danger m-4'>Access denied. Reports are available only to Head BHW.</div>";
    require 'footer.php';
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;

/* ---------------------------
   Helper DB functions
   --------------------------- */

/**
 * Check whether a table exists (uses escaped literal)
 */
function table_exists($mysqli, $table) {
    $tbl_esc = $mysqli->real_escape_string($table);
    $sql = "SHOW TABLES LIKE '{$tbl_esc}'";
    $res = $mysqli->query($sql);
    if ($res === false) return false;
    $exists = ($res->num_rows > 0);
    $res->free();
    return $exists;
}

/**
 * Check whether a column exists in a table
 */
function column_exists($mysqli, $table, $column) {
    $tbl_esc = $mysqli->real_escape_string($table);
    $col_esc = $mysqli->real_escape_string($column);
    $sql = "SHOW COLUMNS FROM `{$tbl_esc}` LIKE '{$col_esc}'";
    $res = $mysqli->query($sql);
    if ($res === false) return false;
    $exists = ($res->num_rows > 0);
    $res->free();
    return $exists;
}

/* ---------------------------
   Inventory gathering (AGGREGATED by item_name ONLY)
   Replaces the previous per-row batch reading.
   --------------------------- */

$inventory_rows = [];

$inv_tables = [
    ['table' => 'medicine',  'category' => 'Medicine',  'id_col' => 'med_id'],
    ['table' => 'supplies',  'category' => 'Supply',    'id_col' => 'supply_id'],
    ['table' => 'equipment', 'category' => 'Equipment', 'id_col' => 'equipment_id'],
];

foreach ($inv_tables as $it) {
    $tbl = $it['table'];

    if (!table_exists($mysqli, $tbl)) continue;

    // ensure expected columns exist (defensive)
    if (!column_exists($mysqli, $tbl, 'item_name') || !column_exists($mysqli, $tbl, 'quantity')) {
        continue;
    }

    $tbl_esc = $mysqli->real_escape_string($tbl);

    // AGGREGATED QUERY – GROUP BY item_name only
    $sel = "
        SELECT 
            item_name,
            COALESCE(SUM(CAST(quantity AS DECIMAL(12,4))),0) AS total_qty
        FROM `{$tbl_esc}`
        GROUP BY item_name
    ";

    $res = $mysqli->query($sel);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $inventory_rows[] = [
                'category'  => $it['category'],
                'item_name' => $r['item_name'] ?? '',
                'item_code' => '',  // grouping by item_name only; codes omitted for summary
                'quantity'  => is_null($r['total_qty']) ? 0 : (float)$r['total_qty'],
            ];
        }
        $res->free();
    } else {
        // Optional: enable during debugging
        // error_log("Inventory aggregate query failed for {$tbl_esc}: " . $mysqli->error);
    }
}

/* ---------------------------
   Prepare stock in/out arrays
   (NO pagination — show all rows)
   --------------------------- */

$stock_in_all  = array_values(array_filter($inventory_rows, function($r){ return $r['quantity'] > 0; }));
$stock_out_all = array_values(array_filter($inventory_rows, function($r){ return $r['quantity'] == 0; }));

$stock_in  = $stock_in_all;   // show all rows
$stock_out = $stock_out_all;  // show all rows

$stock_in_total  = count($stock_in);
$stock_out_total = count($stock_out);

/* ---------------------------
   Header info (Asia/Manila)
   --------------------------- */
$tz = new DateTimeZone('Asia/Manila');
$now = new DateTime('now', $tz);
$reportDate = $now->format('F j, Y');

?>

<!-- Minimal / print-first styles -->
<style>
  /* Page and print rules */
  @page {
    size: auto;
    margin: 12mm;
  }

  /* Hide site chrome when printing */
  @media print {
    .no-print, nav, header, footer, .sidebar, .btn, .form-control, .form-select, .no-print-on-print { display: none !important; }
    body { background: #fff; color: #000; }
    /* Ensure table headers repeat on each page */
    thead { display: table-header-group; }
    tfoot { display: table-row-group; }
  }

  /* Page / screen layout */
  body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; color:#222; background:transparent; margin: 12px; }
  .report-header { margin-bottom: 8px; }
  .report-title { font-size: 18px; margin:0 0 4px 0; font-weight:700; }
  .report-meta { font-size: 13px; color:#444; margin-bottom: 12px; }

  /* Clean, plain table - visible borders */
  table.plain-table {
    width:100%;
    border-collapse: collapse;
    border-spacing:0;
    margin-bottom: 18px;
    font-size: 13px;
    color: #222;

    /* visible outer border */
    border: 1px solid #bdbdbd;
    /* rounded look on screen only */
    border-radius: 6px;
    overflow: hidden;
  }

  /* table header cells - stronger separators */
  table.plain-table thead th {
    text-align: left;
    padding: 8px 6px;
    font-weight:700;
    background: #f7f7f7;
    vertical-align: bottom;

    /* header cell borders */
    border-bottom: 1px solid #bdbdbd;
    border-right: 1px solid #e0e0e0;
  }

  /* last header cell shouldn't have right border */
  table.plain-table thead th:last-child { border-right: none; }

  /* body cell borders for clear rows */
  table.plain-table tbody td {
    padding: 8px 6px;
    vertical-align: middle;
    border-top: 1px solid #e9e9e9;
    border-right: 1px solid #f1f1f1;
  }
  table.plain-table tbody td:last-child { border-right: none; }

  /* Make sure the table rows remain visually separated on print */
  table.plain-table tbody tr { background: #fff; }

  /* Prevent row splitting across printed pages, but allow page breaks between rows */
  tr, td, th {
    page-break-inside: avoid;
    break-inside: avoid;
    -webkit-column-break-inside: avoid;
    -webkit-region-break-inside: avoid;
  }

  /* Small helpers */
  .muted { color:#555; font-size:13px; }
  .section-title { font-size:14px; font-weight:700; margin:8px 0; }
  .screen-actions { margin-bottom:12px; }
  .print-note { font-size:12px; color:#666; margin-top:6px; }

  /* Make sure code column doesn't wrap awkwardly */
  .col-code { white-space:nowrap; max-width: 180px; overflow:hidden; text-overflow:ellipsis; }

  /* When printing, avoid any shadows/borders from outer layout — neutralize common classes if present */
  .card, .table-card, .shadow { box-shadow: none !important; background: transparent !important; border: none !important; }

  /* Responsive: keep layout readable on small screens */
  @media (max-width:700px) {
    table.plain-table thead th, table.plain-table tbody td { padding: 6px 4px; font-size: 12px; }
    .report-title { font-size: 16px; }
  }
</style>

<!-- Simple page content (no cards / no shadows) -->
<div class="report-header" role="banner">
  <h1 class="report-title">Inventory Stock Report</h1>
  <div class="report-meta">Report generated: <strong><?php echo htmlspecialchars($reportDate, ENT_QUOTES, 'UTF-8') ?></strong>
    <span class="muted"> — Stock In: <?php echo (int)$stock_in_total ?> | Stock Out: <?php echo (int)$stock_out_total ?></span>
  </div>

  <!-- Screen-only print action -->
  <div class="screen-actions no-print">
    <button class="btn btn-sm" onclick="window.print()" style="padding:.4rem .6rem;border:1px solid #ccc;border-radius:6px;background:#f6f6f6;cursor:pointer">Print / Save as PDF</button>
    <span class="print-note">This view is optimized for printing — controls and site chrome will be hidden on print.</span>
  </div>
</div>

<!-- STOCK IN TABLE -->
<section aria-labelledby="stock-in-heading">
  <div id="stock-in-heading" class="section-title">In Stock (<?php echo (int)$stock_in_total ?>)</div>

  <table class="plain-table" role="table" aria-label="Stock In table">
    <thead>
      <tr>
        <th style="width:18%;">Category</th>
        <th style="width:44%;">Item</th>
        <th style="width:18%;">Code</th>
        <th style="width:10%; text-align:right;">Qty</th>
        <th style="width:10%;">Status</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($stock_in)): ?>
        <tr><td colspan="5">No items currently in stock.</td></tr>
      <?php else: foreach ($stock_in as $it): ?>
        <tr>
          <td><?php echo htmlspecialchars($it['category'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td style="font-weight:600;"><?php echo htmlspecialchars($it['item_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td class="col-code"><?php echo htmlspecialchars($it['item_code'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td style="text-align:right;"><?php echo (int)$it['quantity'] ?></td>
          <td>In Stock</td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</section>

<!-- STOCK OUT TABLE -->
<section aria-labelledby="stock-out-heading">
  <div id="stock-out-heading" class="section-title">Out of Stock (<?php echo (int)$stock_out_total ?>)</div>

  <table class="plain-table" role="table" aria-label="Stock Out table">
    <thead>
      <tr>
        <th style="width:18%;">Category</th>
        <th style="width:44%;">Item</th>
        <th style="width:18%;">Code</th>
        <th style="width:10%; text-align:right;">Qty</th>
        <th style="width:10%;">Status</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($stock_out)): ?>
        <tr><td colspan="5">No stock-out items.</td></tr>
      <?php else: foreach ($stock_out as $it): ?>
        <tr>
          <td><?php echo htmlspecialchars($it['category'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td style="font-weight:600;"><?php echo htmlspecialchars($it['item_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td class="col-code"><?php echo htmlspecialchars($it['item_code'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td style="text-align:right;"><?php echo (int)$it['quantity'] ?></td>
          <td>Out of Stock</td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</section>

<?php

