<?php
// reports.php
// Inventory Stock Report — Medicine only
// Redesigned UI: cleaner, professional cards (clickable) and subtle color/shadow accents.
// Tables unchanged (only styling adjusted). Print still supported.

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
function table_exists($mysqli, $table) {
    $tbl_esc = $mysqli->real_escape_string($table);
    $sql = "SHOW TABLES LIKE '{$tbl_esc}'";
    $res = $mysqli->query($sql);
    if ($res === false) return false;
    $exists = ($res->num_rows > 0);
    if ($res) $res->free();
    return $exists;
}
function column_exists($mysqli, $table, $column) {
    $tbl_esc = $mysqli->real_escape_string($table);
    $col_esc = $mysqli->real_escape_string($column);
    $sql = "SHOW COLUMNS FROM `{$tbl_esc}` LIKE '{$col_esc}'";
    $res = $mysqli->query($sql);
    if ($res === false) return false;
    $exists = ($res->num_rows > 0);
    if ($res) $res->free();
    return $exists;
}

/* ---------------------------
   INVENTORY aggregation (MEDICINE only)
   - Group by item_name + unit for clarity
   --------------------------- */
$inventory_rows = [];
if (table_exists($mysqli, 'medicine')) {
    if (column_exists($mysqli, 'medicine', 'item_name') && column_exists($mysqli, 'medicine', 'quantity')) {
        $unit_col = column_exists($mysqli, 'medicine', 'unit') ? 'unit' : null;

        $sql = "
            SELECT
              item_name,
              " . ($unit_col ? "COALESCE(unit,'pcs') AS unit," : "'pcs' AS unit,") . "
              COALESCE(SUM(CAST(quantity AS DECIMAL(12,4))),0) AS total_qty
            FROM medicine
            GROUP BY item_name" . ($unit_col ? ", unit" : "") . "
            ORDER BY item_name ASC
        ";
        if ($res = $mysqli->query($sql)) {
            while ($r = $res->fetch_assoc()) {
                $inventory_rows[] = [
                    'item_name' => $r['item_name'] ?? '',
                    'unit'      => $r['unit'] ?? 'pcs',
                    'quantity'  => is_null($r['total_qty']) ? 0 : (float)$r['total_qty'],
                ];
            }
            $res->free();
        }
    }
}

/* ---------------------------
   Stock IN / OUT arrays
   --------------------------- */
$stock_in_all  = array_values(array_filter($inventory_rows, function($r){ return $r['quantity'] > 0; }));
$stock_out_all = array_values(array_filter($inventory_rows, function($r){ return $r['quantity'] == 0; }));
$stock_in  = $stock_in_all;
$stock_out = $stock_out_all;
$stock_in_total  = count($stock_in);
$stock_out_total = count($stock_out);

/* ---------------------------
   Stock movements (IN / OUT)
   --------------------------- */
$movements_available = table_exists($mysqli, 'stock_movements') && table_exists($mysqli, 'batches') && table_exists($mysqli, 'medicine');
$movements_in = [];
$movements_out = [];

if ($movements_available) {
    $required_cols = [
        ['stock_movements','movement_type'],
        ['stock_movements','medicine_id'],
        ['stock_movements','qty'],
        ['stock_movements','movement_date'],
        ['stock_movements','unit'],
    ];
    $ok = true;
    foreach ($required_cols as $c) {
        if (!column_exists($mysqli, $c[0], $c[1])) { $ok = false; break; }
    }
    if ($ok) {
        $sql_in = "
            SELECT sm.id AS movement_id, sm.movement_type, COALESCE(m.item_name, '') AS item_name,
                   COALESCE(b.batch_no,'') AS batch_no, sm.qty, COALESCE(sm.unit,'pcs') AS unit, sm.movement_date, COALESCE(sm.note,'') AS note
            FROM stock_movements sm
            LEFT JOIN medicine m ON sm.medicine_id = m.med_id
            LEFT JOIN batches b ON sm.batch_id = b.id
            WHERE sm.movement_type = 'IN'
            ORDER BY sm.movement_date DESC, sm.id DESC
            LIMIT 500
        ";
        if ($res_in = $mysqli->query($sql_in)) {
            while ($r = $res_in->fetch_assoc()) $movements_in[] = $r;
            $res_in->free();
        }

        $sql_out = "
            SELECT sm.id AS movement_id, sm.movement_type, COALESCE(m.item_name, '') AS item_name,
                   COALESCE(b.batch_no,'') AS batch_no, sm.qty, COALESCE(sm.unit,'pcs') AS unit, sm.movement_date, COALESCE(sm.note,'') AS note
            FROM stock_movements sm
            LEFT JOIN medicine m ON sm.medicine_id = m.med_id
            LEFT JOIN batches b ON sm.batch_id = b.id
            WHERE sm.movement_type = 'OUT'
            ORDER BY sm.movement_date DESC, sm.id DESC
            LIMIT 500
        ";
        if ($res_out = $mysqli->query($sql_out)) {
            while ($r = $res_out->fetch_assoc()) $movements_out[] = $r;
            $res_out->free();
        }
    } else {
        $movements_available = false;
    }
}

/* ---------------------------
   Header info (Asia/Manila)
   --------------------------- */
$tz = new DateTimeZone('Asia/Manila');
$now = new DateTime('now', $tz);
$reportDate = $now->format('F j, Y');

?><!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Inventory Stock Report — Medicine</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root{
      --bg:#f4fbfb;
      --card:#ffffff;
      --muted:#6b7280;
      --accent-1:#0b7285;
      --accent-2:#00b4d8;
      --soft: rgba(11,114,133,0.08);
      --border-strong: #707070;
    }
    * { box-sizing:border-box; }
    body { font-family: Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial; margin:0; background:var(--bg); color:#1f2937;  }

    /* Header */
    .report-head { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:20px; flex-wrap:wrap; }
    .title { display:flex; gap:14px; align-items:center; }
    .logo-badge { width:56px; height:56px; background:linear-gradient(135deg,var(--accent-1),var(--accent-2)); border-radius:12px; display:flex; align-items:center; justify-content:center; color:white; font-weight:700; box-shadow:0 6px 18px rgba(11,114,133,0.18); }
    .report-title { font-size:20px; font-weight:700; color:#073642; margin:0; }
    .report-sub { color:var(--muted); font-size:13px; margin-top:4px; }

    /* Controls */
    .controls { display:flex; gap:10px; align-items:center; }
    .btn-ghost { background:transparent; border:1px solid rgba(15,23,42,0.06); padding:.5rem .75rem; border-radius:10px; cursor:pointer; color:#0b7285; display:inline-flex; gap:.5rem; align-items:center; }
    .btn-primary { background:var(--accent-1); color:white; border:0; padding:.55rem .85rem; border-radius:10px; box-shadow:0 8px 24px rgba(11,114,133,0.12); cursor:pointer; display:inline-flex; gap:.6rem; align-items:center; }

    /* Cards */
    .cards { display:flex; gap:16px; margin-bottom:18px; flex-wrap:wrap; }
    .card {
      background:var(--card);
      border-radius:12px;
      padding:16px;
      flex:1 1 300px;
      min-width:240px;
      border:1px solid rgba(13,16,19,0.06);
      box-shadow: 0 6px 18px rgba(19,54,64,0.05);
      cursor:pointer;
      transition: transform .12s ease, box-shadow .12s ease, border-color .12s ease;
      display:flex;
      gap:12px;
      align-items:center;
    }
    .card:hover { transform:translateY(-6px); box-shadow: 0 18px 40px rgba(11,114,133,0.09); border-color: rgba(11,114,133,0.12); }
    .card .icon {
      width:56px; height:56px; border-radius:10px; display:flex; align-items:center; justify-content:center; color:white; font-size:22px;
      box-shadow: inset 0 -6px 12px rgba(0,0,0,0.06);
    }
    .card .meta { display:flex; flex-direction:column; }
    .card h4 { margin:0; font-size:15px; font-weight:700; color:#073642; }
    .card p { margin:6px 0 0 0; color:var(--muted); font-size:13px; }

    /* specific card colors */
    .c-summary .icon { background: linear-gradient(180deg,#26c6da,#0288d1); }
    .c-movements .icon { background: linear-gradient(180deg,#7dd3fc,#0ea5a1); }

    .kpi { font-size:20px; font-weight:800; color:#073642; }
    .kpi-sub { font-size:12px; color:var(--muted); margin-top:4px; }

    /* Section wrapper */
    .section { display:none; margin-top:10px; }
    .section.show { display:block; animation: fadeIn .18s ease; }

    @keyframes fadeIn { from {opacity:.0; transform: translateY(6px)} to {opacity:1; transform:none;} }

    /* ★★★★★ SUPER VISIBLE BORDERS ★★★★★ */
table.clean {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
  border: 1px solid #000000 !important;
  background: #fff;
}

table.clean thead th {
  background: #e0f3f7;
  padding: 12px 14px;
  font-weight: 600;
  text-align: left;

  /* thick inner borders */
  border: 1px solid #000000 !important;
}

table.clean tbody td {
  padding: 8px 10px;
  vertical-align: middle;

  /* thick visible borders */
  border: 1px solid #000000 !important;
}

/* Row striping still allowed */
table.clean tbody tr:nth-child(even) {
  background: #f8fcff;
}


    .section-title { font-weight:700; margin:8px 0 12px 0; color:#073642; }

    /* small helpers */
    .muted { color:var(--muted); font-size:13px; }
    .no-print { }
    @media print {
      .no-print, nav, header, footer, .sidebar { display:none !important; }
      thead { display:table-header-group; }
      body { background:#fff; padding:8mm; }
    }

    @media (max-width:880px) {
      .cards { flex-direction:column; }
      .card { min-width:unset; }
    }
  </style>
</head>
<body>

<div class="report-head">
  <div class="title">
    <div class="logo-badge" aria-hidden="true">B</div>
    <div>
      <div class="report-title">Inventory Stock Report</div>
      <div class="report-sub">Generated: <strong><?php echo htmlspecialchars($reportDate, ENT_QUOTES, 'UTF-8') ?></strong></div>
    </div>
  </div>

  <div class="controls no-print">
    <button class="btn-ghost" onclick="location.reload()" title="Refresh"><i class="bi bi-arrow-clockwise"></i></button>
    <button class="btn-primary" onclick="window.print()"><i class="bi bi-printer" style="font-size:1rem"></i> Print</button>
  </div>
</div>

<!-- Cards (click to reveal sections) -->
<div class="cards no-print" role="list" aria-label="Report cards">
  <div class="card c-summary" role="listitem" tabindex="0" data-target="sec-inventory" aria-pressed="false">
    <div class="icon"><i class="bi bi-box-seam" style="font-size:20px"></i></div>
    <div class="meta">
      <h4>Stock Summary</h4>
      <p class="muted">In stock: <span class="kpi"><?php echo (int)$stock_in_total ?></span> &nbsp; Out: <span class="kpi"><?php echo (int)$stock_out_total ?></span></p>
    </div>
  </div>

  <div class="card c-movements" role="listitem" tabindex="0" data-target="sec-movements" aria-pressed="false">
    <div class="icon"><i class="bi bi-arrow-repeat" style="font-size:20px"></i></div>
    <div class="meta">
      <h4>Stock Movements</h4>
      <p class="muted"><?php echo $movements_available ? ('IN: '.count($movements_in).' · OUT: '.count($movements_out)) : 'Movements not available' ?></p>
    </div>
  </div>
</div>

<!-- Inventory section (Stock summary) -->
<section id="sec-inventory" class="section" aria-labelledby="h-inventory">
  <div id="h-inventory" class="section-title">Stock Summary</div>

  <!-- IN STOCK -->
  <div style="margin-bottom:14px;">
    <div style="font-weight:700;margin-bottom:8px;">In Stock (<?php echo (int)$stock_in_total ?>)</div>
    <table class="clean" role="table" aria-label="In stock">
      <thead>
        <tr>
          <th>Item</th>
          <th style="width:12%;">Unit</th>
          <th style="width:18%; text-align:right;">Qty</th>
          <th style="width:18%;">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($stock_in)): ?>
          <tr><td colspan="4" class="muted" style="text-align:center;padding:18px;">No items in stock.</td></tr>
        <?php else: foreach ($stock_in as $it): ?>
          <tr>
            <td style="font-weight:600;"><?php echo htmlspecialchars($it['item_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td class="muted"><?php echo htmlspecialchars($it['unit'] ?? 'pcs', ENT_QUOTES, 'UTF-8') ?></td>
            <td style="text-align:right;"><?php echo (int)$it['quantity'] ?></td>
            <td class="muted">In Stock</td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <!-- OUT OF STOCK -->
  <div>
    <div style="font-weight:700;margin-bottom:8px;">Out of Stock (<?php echo (int)$stock_out_total ?>)</div>
    <table class="clean" role="table" aria-label="Out of stock">
      <thead>
        <tr>
          <th>Item</th>
          <th style="width:12%;">Unit</th>
          <th style="width:18%; text-align:right;">Qty</th>
          <th style="width:18%;">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($stock_out)): ?>
          <tr><td colspan="4" class="muted" style="text-align:center;padding:18px;">No out-of-stock items.</td></tr>
        <?php else: foreach ($stock_out as $it): ?>
          <tr>
            <td style="font-weight:600;"><?php echo htmlspecialchars($it['item_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td class="muted"><?php echo htmlspecialchars($it['unit'] ?? 'pcs', ENT_QUOTES, 'UTF-8') ?></td>
            <td style="text-align:right;"><?php echo (int)$it['quantity'] ?></td>
            <td class="muted">Out of Stock</td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</section>

<!-- Stock movements section -->
<section id="sec-movements" class="section" aria-labelledby="h-movements">
  <div id="h-movements" class="section-title">Stock Movements</div>

  <?php if (!$movements_available): ?>
    <div class="muted">Stock movements not available. Enable <code>stock_movements</code> to view logs.</div>
  <?php else: ?>

    <div style="margin-bottom:14px;">
      <div style="font-weight:700;margin-bottom:8px;">IN Movements (<?php echo count($movements_in) ?>)</div>
      <table class="clean" role="table" aria-label="In movements">
        <thead>
          <tr>
            <th style="width:8%;">ID</th>
            <th>Item</th>
            <th style="width:14%;">Batch</th>
            <th style="width:12%; text-align:right;">Qty</th>
            <th style="width:12%;">Unit</th>
            <th style="width:22%;">Date</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($movements_in)): ?>
            <tr><td colspan="6" class="muted" style="text-align:center;padding:18px;">No IN movements found.</td></tr>
          <?php else: foreach ($movements_in as $m): ?>
            <tr>
              <td><?php echo (int)$m['movement_id'] ?></td>
              <td style="font-weight:600;"><?php echo htmlspecialchars($m['item_name'], ENT_QUOTES, 'UTF-8') ?></td>
              <td class="muted"><?php echo htmlspecialchars($m['batch_no'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
              <td style="text-align:right;"><?php echo (int)$m['qty'] ?></td>
              <td class="muted"><?php echo htmlspecialchars($m['unit'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
              <td class="muted"><?php echo htmlspecialchars($m['movement_date'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <div>
      <div style="font-weight:700;margin-bottom:8px;">OUT Movements (<?php echo count($movements_out) ?>)</div>
      <table class="clean" role="table" aria-label="Out movements">
        <thead>
          <tr>
            <th style="width:8%;">ID</th>
            <th>Item</th>
            <th style="width:14%;">Batch</th>
            <th style="width:12%; text-align:right;">Qty</th>
            <th style="width:12%;">Unit</th>
            <th style="width:22%;">Date</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($movements_out)): ?>
            <tr><td colspan="6" class="muted" style="text-align:center;padding:18px;">No OUT movements found.</td></tr>
          <?php else: foreach ($movements_out as $m): ?>
            <tr>
              <td><?php echo (int)$m['movement_id'] ?></td>
              <td style="font-weight:600;"><?php echo htmlspecialchars($m['item_name'], ENT_QUOTES, 'UTF-8') ?></td>
              <td class="muted"><?php echo htmlspecialchars($m['batch_no'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
              <td style="text-align:right;"><?php echo (int)$m['qty'] ?></td>
              <td class="muted"><?php echo htmlspecialchars($m['unit'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
              <td class="muted"><?php echo htmlspecialchars($m['movement_date'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

  <?php endif; ?>
</section>

<script>
(function(){
  // card toggles
  const cards = document.querySelectorAll('.card[data-target]');
  cards.forEach(card => {
    card.addEventListener('click', () => {
      const id = card.getAttribute('data-target');
      if (!id) return;
      document.querySelectorAll('.section').forEach(s => s.classList.remove('show'));
      const targ = document.getElementById(id);
      if (targ) {
        targ.classList.add('show');
        window.scrollTo({ top: targ.getBoundingClientRect().top + window.scrollY - 20, behavior: 'smooth' });
      }
    });
    card.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); card.click(); }
    });
  });

  // keyboard: press 1 = summary, 2 = movements for quick access
  document.addEventListener('keydown', (e) => {
    if (e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA')) return;
    if (e.key === '1') document.querySelector('.card.c-summary')?.click();
    if (e.key === '2') document.querySelector('.card.c-movements')?.click();
  });
})();
</script>

</body>
</html>
