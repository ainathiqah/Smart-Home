<?php
require_once "config/auth_check.php";
require_once "config/db_config.php";
$conn = getDB();
$active = "analytics";

if (!isset($_SESSION['active_device'])) {
    header("Location: rooms.php");
    exit;
}

$device_id = getActiveDeviceId($conn, $_SESSION['user_id']);

if (!$device_id) {
    $pageTitle = "Stats";
    include "no_room.php";
    exit;
}

// ---- CSV export ----
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmt = $conn->prepare("SELECT * FROM sensor_data WHERE device_id = ? ORDER BY id ASC");
    $stmt->bind_param("s", $device_id);
    $stmt->execute();
    $rows = $stmt->get_result();

    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=\"{$device_id}_history.csv\"");
    $out = fopen("php://output", "w");
    fputcsv($out, ["id", "device_id", "temperature", "humidity", "air_quality", "light_level", "status", "output_status", "created_at"]);
    while ($row = $rows->fetch_assoc()) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

// ---- Trend data (last 30 readings) for chart ----
$stmt = $conn->prepare("SELECT * FROM sensor_data WHERE device_id = ? ORDER BY id DESC LIMIT 30");
$stmt->bind_param("s", $device_id);
$stmt->execute();
$trendRows = array_reverse($stmt->get_result()->fetch_all(MYSQLI_ASSOC));

$labels = array_map(fn($r) => substr($r['created_at'], 11, 5), $trendRows);
$temps = array_map(fn($r) => (float)$r['sensor_1'], $trendRows);
$hums = array_map(fn($r) => (float)$r['sensor_2'], $trendRows);

// ---- History table (filterable: all / warning / critical, paginated 10/page) ----
$filter = $_GET['filter'] ?? 'all';
if (!in_array($filter, ['all', 'warning', 'critical'])) $filter = 'all';

$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$statusClause = $filter === 'warning' ? "AND status = 'WARNING'" : ($filter === 'critical' ? "AND status = 'CRITICAL'" : "");

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM sensor_data WHERE device_id = ? $statusClause");
$stmt->bind_param("s", $device_id);
$stmt->execute();
$totalRows = $stmt->get_result()->fetch_assoc()['c'];
$totalPages = max(1, ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $conn->prepare("SELECT * FROM sensor_data WHERE device_id = ? $statusClause ORDER BY id DESC LIMIT ? OFFSET ?");
$stmt->bind_param("sii", $device_id, $perPage, $offset);
$stmt->execute();
$warnings = $stmt->get_result();

// ---- Insight stats ----
$stmt = $conn->prepare("SELECT AVG(sensor_1) AS avg_temp FROM sensor_data WHERE device_id = ?");
$stmt->bind_param("s", $device_id);
$stmt->execute();
$avgTemp = $stmt->get_result()->fetch_assoc()['avg_temp'];

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM sensor_data WHERE device_id = ? AND sensor_3 = 0");
$stmt->bind_param("s", $device_id);
$stmt->execute();
$poorAirCount = $stmt->get_result()->fetch_assoc()['c'];

$stmt = $conn->prepare("SELECT
    SUM(status = 'WARNING') AS warning_c,
    SUM(status = 'CRITICAL') AS critical_c
    FROM sensor_data WHERE device_id = ?");
$stmt->bind_param("s", $device_id);
$stmt->execute();
$eventCounts = $stmt->get_result()->fetch_assoc();
$warningCount = (int)($eventCounts['warning_c'] ?? 0);
$criticalCount = (int)($eventCounts['critical_c'] ?? 0);

$stmt = $conn->prepare("SELECT light_level FROM sensor_data WHERE device_id = ?");
$stmt->bind_param("s", $device_id);
$stmt->execute();
$lightRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$dark = $dim = $bright = 0;
foreach ($lightRows as $r) {
    $v = (int)$r['light_level'];
    if ($v > 3000) $dark++;
    elseif ($v > 1500) $dim++;
    else $bright++;
}
$totalLight = max(count($lightRows), 1);

// ---- Rule-based recommendation (placeholder for future Ollama integration) ----
$recommendation = "Conditions look normal. No action needed.";
if ($avgTemp !== null && $avgTemp >= 32) {
    $recommendation = "Average temperature is high (" . round($avgTemp, 1) . "&deg;C). Consider improving ventilation or running the fan more frequently.";
} elseif ($poorAirCount > 5) {
    $recommendation = "Poor air quality was detected $poorAirCount times. Check for gas sources and improve room ventilation.";
} elseif ($dark > ($totalLight * 0.5)) {
    $recommendation = "The room is dark most of the time. Consider adding more lighting or adjusting the light threshold.";
}
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Stats - Smart Home</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . "/style.css") ?>">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="app">
  <?php include "sidebar.php"; ?>
  <div class="main">
    <div class="topbar">
      <h1>Stats</h1>
      <div class="profile-chip"><i class="fa-solid fa-circle-user"></i> <?= htmlspecialchars($_SESSION['username']) ?></div>
    </div>

    <div class="grid">
      <div class="card card-temp">
        <div class="card-icon"><i class="fa-solid fa-temperature-half"></i></div>
        <h3>Average Temperature</h3>
        <div class="value"><?= $avgTemp !== null ? round($avgTemp, 1) : "-" ?>&deg;C</div>
        <div class="sub">All-time average</div>
      </div>
      <div class="card card-air">
        <div class="card-icon"><i class="fa-solid fa-wind"></i></div>
        <h3>Poor Air Quality Events</h3>
        <div class="value"><?= $poorAirCount ?></div>
        <div class="sub">Total gas-detected readings</div>
      </div>
      <div class="card card-status">
        <div class="card-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <h3>Warning / Critical Events</h3>
        <div class="value"><?= $warningCount ?> <span style="font-size:16px;color:#5c6b85;">/</span> <?= $criticalCount ?></div>
        <div class="sub">Total warning &middot; critical readings</div>
      </div>
      <div class="card card-light">
        <div class="card-icon"><i class="fa-solid fa-sun"></i></div>
        <h3>Light Usage</h3>
        <div class="value" style="font-size:16px;">
          Bright <?= round($bright / $totalLight * 100) ?>% &middot;
          Dim <?= round($dim / $totalLight * 100) ?>% &middot;
          Dark <?= round($dark / $totalLight * 100) ?>%
        </div>
        <div class="sub">Based on all-time readings</div>
      </div>
      <div class="card card-insight">
        <div class="card-icon"><i class="fa-solid fa-lightbulb"></i></div>
        <h3>Recommendation</h3>
        <div class="value" style="font-size:13px; font-weight: 600; line-height:1.4;"><?= $recommendation ?></div>
      </div>
    </div>

    <div class="panel">
      <div class="panel-head">
        <h2><i class="fa-solid fa-chart-line"></i> Temperature &amp; Humidity Trend</h2>
        <span class="panel-sub">Last 30 readings</span>
      </div>
      <canvas id="trendChart" height="90"></canvas>
    </div>

    <div class="panel" id="history">
      <details class="collapsible" open>
        <summary>
          <h2><i class="fa-solid fa-clock-rotate-left"></i> Reading History</h2>
          <i class="fa-solid fa-chevron-down collapse-arrow"></i>
        </summary>

        <div class="collapsible-body">
          <div class="panel-head">
            <div class="filter-tabs">
              <a href="analytics.php?filter=all&page=1#history" class="<?= $filter === 'all' ? 'active' : '' ?>">All</a>
              <a href="analytics.php?filter=warning&page=1#history" class="<?= $filter === 'warning' ? 'active' : '' ?>">Warning</a>
              <a href="analytics.php?filter=critical&page=1#history" class="<?= $filter === 'critical' ? 'active' : '' ?>">Critical</a>
            </div>
            <a href="analytics.php?export=csv" class="btn-export">
              <i class="fa-solid fa-file-csv"></i> Export CSV
            </a>
          </div>

          <table>
            <tr><th>Time</th><th>Temp</th><th>Humidity</th><th>Air</th><th>Light</th><th>Status</th></tr>
            <?php if ($warnings->num_rows === 0): ?>
            <tr><td colspan="6" style="text-align:center;color:#697791;padding:24px;">No <?= $filter !== 'all' ? htmlspecialchars($filter) : '' ?> records found.</td></tr>
            <?php endif; ?>
            <?php while ($row = $warnings->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($row['created_at']) ?></td>
              <td><?= htmlspecialchars($row['sensor_1']) ?>&deg;C</td>
              <td><?= htmlspecialchars($row['sensor_2']) ?>%</td>
              <td><?= $row['sensor_3'] == 0 ? 'GAS' : 'NORMAL' ?></td>
              <td><?= htmlspecialchars($row['light_level']) ?></td>
              <td><span class="badge badge-<?= htmlspecialchars($row['status']) ?>"><?= htmlspecialchars($row['status']) ?></span></td>
            </tr>
            <?php endwhile; ?>
          </table>

          <?php if ($totalPages > 1): ?>
          <div class="pagination">
            <a href="analytics.php?filter=<?= $filter ?>&page=<?= max(1, $page - 1) ?>#history" class="<?= $page <= 1 ? 'disabled' : '' ?>">
              <i class="fa-solid fa-chevron-left"></i> Prev
            </a>
            <span class="pagination-info">Page <?= $page ?> of <?= $totalPages ?> &middot; <?= $totalRows ?> records</span>
            <a href="analytics.php?filter=<?= $filter ?>&page=<?= min($totalPages, $page + 1) ?>#history" class="<?= $page >= $totalPages ? 'disabled' : '' ?>">
              Next <i class="fa-solid fa-chevron-right"></i>
            </a>
          </div>
          <?php endif; ?>
        </div>
      </details>
    </div>

  </div>
</div>

<script>
new Chart(document.getElementById('trendChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode($labels) ?>,
    datasets: [
      { label: 'Temperature (°C)', data: <?= json_encode($temps) ?>, borderColor: '#2d6ee6', tension: 0.3 },
      { label: 'Humidity (%)', data: <?= json_encode($hums) ?>, borderColor: '#2bb673', tension: 0.3 }
    ]
  },
  options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});
</script>
</body>
</html>
