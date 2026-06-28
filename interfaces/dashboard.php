<?php
require_once "../config/auth_check.php";
require_once "../config/db_config.php";
$conn = getDB();
$active = "dashboard";

if (isset($_GET['select'])) {
    $_SESSION['active_device'] = $_GET['select'];
    header("Location: dashboard.php");
    exit;
}

if (!isset($_SESSION['active_device'])) {
    header("Location: rooms.php");
    exit;
}

$device_id = getActiveDeviceId($conn, $_SESSION['user_id']);
$rooms = getUserDevices($conn, $_SESSION['user_id']);
$room_name = "";
$settings = null;
if ($device_id) {
    $stmt = $conn->prepare("SELECT * FROM device_settings WHERE device_id = ?");
    $stmt->bind_param("s", $device_id);
    $stmt->execute();
    $settings = $stmt->get_result()->fetch_assoc();
    $room_name = $settings['room_name'];
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $device_id) {
    $alert_mode = $_POST['output_mode'];
    $stmt = $conn->prepare("UPDATE device_settings SET output_mode=? WHERE device_id=?");
    $stmt->bind_param("ss", $alert_mode, $device_id);
    $stmt->execute();
    $stmt->close();
    header("Location: dashboard.php");
    exit;
}

$latest = null;
$activeWarnings = 0;
if ($device_id) {
    $stmt = $conn->prepare("SELECT * FROM sensor_data WHERE device_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("s", $device_id);
    $stmt->execute();
    $latest = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM sensor_data WHERE device_id = ? AND status != 'NORMAL' AND created_at >= NOW() - INTERVAL 1 DAY");
    $stmt->bind_param("s", $device_id);
    $stmt->execute();
    $activeWarnings = $stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT * FROM sensor_data WHERE device_id = ? AND status != 'NORMAL' AND created_at >= NOW() - INTERVAL 1 DAY ORDER BY id DESC LIMIT 5");
    $stmt->bind_param("s", $device_id);
    $stmt->execute();
    $recentAlerts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) AS c, AVG(sensor_1) AS avg_temp FROM sensor_data WHERE device_id = ? AND created_at >= NOW() - INTERVAL 1 DAY");
    $stmt->bind_param("s", $device_id);
    $stmt->execute();
    $todayStats = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM sensor_data WHERE device_id = ?");
    $stmt->bind_param("s", $device_id);
    $stmt->execute();
    $totalRecords = $stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
}

$hour = (int)date('H');
if ($hour < 12) $greeting = "Good morning";
elseif ($hour < 18) $greeting = "Good afternoon";
else $greeting = "Good evening";

$connectionStatus = $device_id ? getDeviceConnectionStatus($conn, $device_id) : null;
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard - Smart Home</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . "/style.css") ?>">
</head>
<body>
<div class="app">
  <?php include "sidebar.php"; ?>
  <div class="main">
    <div class="topbar">
      <div class="topbar-left">
        <span class="greeting-text"><?= $greeting ?>, <?= htmlspecialchars($_SESSION['username']) ?> <i class="fa-solid fa-sun greeting-icon"></i></span>
        <h1><?= $room_name ? htmlspecialchars($room_name) : "Welcome, Smart Home!" ?></h1>
      </div>
      <div class="topbar-right">
        <details class="alert-dropdown">
          <summary class="alert-bell" title="Active warnings (last 24h)">
            <i class="fa-solid fa-bell"></i>
            <?php if ($activeWarnings > 0): ?><span class="alert-count"><?= $activeWarnings ?></span><?php endif; ?>
          </summary>
          <div class="alert-panel">
            <div class="alert-panel-head">Warnings &amp; Critical Alerts (24h)</div>
            <?php if (empty($recentAlerts)): ?>
              <div class="alert-panel-empty">No active warnings right now.</div>
            <?php else: ?>
              <?php foreach ($recentAlerts as $a): ?>
                <div class="alert-item">
                  <span class="badge badge-<?= htmlspecialchars($a['status']) ?>"><?= htmlspecialchars($a['status']) ?></span>
                  <span class="alert-item-text">
                    <?= htmlspecialchars($a['sensor_1']) ?>&deg;C &middot; <?= htmlspecialchars($a['sensor_2']) ?>% &middot; Light <?= htmlspecialchars($a['light_level']) ?>
                  </span>
                  <span class="alert-item-time"><?= htmlspecialchars(substr($a['created_at'], 5, 11)) ?></span>
                </div>
              <?php endforeach; ?>
              <a href="analytics.php" class="alert-panel-link">View full history in Stats &rarr;</a>
            <?php endif; ?>
          </div>
        </details>
        <div class="profile-chip"><i class="fa-solid fa-circle-user"></i> <?= htmlspecialchars($_SESSION['username']) ?></div>
      </div>
    </div>

    <?php if (count($rooms) > 0): ?>
    <div class="room-strip">
      <?php foreach ($rooms as $r): ?>
        <a href="dashboard.php?select=<?= urlencode($r['device_id']) ?>" class="room-chip <?= $r['device_id'] === $device_id ? 'active' : '' ?>">
          <i class="fa-solid fa-house"></i> <?= htmlspecialchars($r['room_name']) ?>
        </a>
      <?php endforeach; ?>
      <a href="add_room.php" class="room-chip room-chip-add">
        <i class="fa-solid fa-plus"></i> Add Room
      </a>
    </div>
    <?php endif; ?>

    <?php if ($latest):
      $statusMeta = [
        'NORMAL'   => ['label' => 'Safe',     'icon' => 'fa-circle-check',      'msg' => 'All conditions are within safe limits.'],
        'WARNING'  => ['label' => 'Warning',  'icon' => 'fa-triangle-exclamation', 'msg' => 'Some readings are outside the safe range. Monitor closely.'],
        'CRITICAL' => ['label' => 'Critical', 'icon' => 'fa-circle-exclamation', 'msg' => 'Unsafe conditions detected! Immediate action recommended.'],
      ];
      $sm = $statusMeta[$latest['status']] ?? $statusMeta['NORMAL'];
    ?>
    <div class="device-line">
      <span class="conn-dot-inline conn-dot-<?= $connectionStatus === 'Connected' ? 'on' : 'off' ?>"></span>
      Device <?= $connectionStatus === 'Connected' ? 'Online' : 'Offline' ?> &middot; ID: <?= htmlspecialchars($device_id) ?>
    </div>

    <div class="status-banner status-banner-<?= htmlspecialchars($latest['status']) ?>">
      <i class="fa-solid <?= $sm['icon'] ?>"></i>
      <div>
        <div class="status-banner-title"><?= $sm['label'] ?></div>
        <div class="status-banner-msg"><?= $sm['msg'] ?></div>
      </div>
    </div>

    <div class="grid">
      <div class="card card-temp">
        <div class="card-icon"><i class="fa-solid fa-temperature-half"></i></div>
        <h3>Temperature</h3>
        <div class="value"><?= htmlspecialchars($latest['sensor_1']) ?>&deg;C</div>
        <div class="sub">Threshold: <?= htmlspecialchars($settings['threshold_1']) ?>&deg;C</div>
      </div>
      <div class="card card-hum">
        <div class="card-icon"><i class="fa-solid fa-droplet"></i></div>
        <h3>Humidity</h3>
        <div class="value"><?= htmlspecialchars($latest['sensor_2']) ?>%</div>
        <div class="sub">Relative humidity</div>
      </div>
      <div class="card card-air">
        <div class="card-icon"><i class="fa-solid fa-wind"></i></div>
        <h3>Air Quality</h3>
        <div class="value"><?= $latest['sensor_3'] == 0 ? 'GAS' : 'NORMAL' ?></div>
        <div class="sub">MQ-2 sensor</div>
      </div>
      <div class="card card-light">
        <div class="card-icon"><i class="fa-solid fa-sun"></i></div>
        <h3>Light Level</h3>
        <div class="value"><?= htmlspecialchars($latest['light_level']) ?></div>
        <div class="sub">Raw LDR reading</div>
      </div>
    </div>

    <div class="dash-2x2">
      <div class="panel">
        <h2><span class="panel-icon panel-icon-control"><i class="fa-solid fa-sliders"></i></span> Quick Control</h2>
        <form method="POST">
          <div class="quick-control-row">
            <div class="quick-control-label"><i class="fa-solid fa-bell"></i> Alert / Buzzer</div>
            <div class="seg-switch seg-switch-compact">
              <?php foreach (["AUTO", "ON", "OFF"] as $m): ?>
                <input type="radio" id="qalert-<?= $m ?>" class="opt-<?= $m ?>" name="output_mode" value="<?= $m ?>" <?= $settings['output_mode'] == $m ? 'checked' : '' ?>>
                <label for="qalert-<?= $m ?>" class="opt-<?= $m ?>"><?= $m ?></label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="quick-control-apply">
            <button type="submit" class="btn-save">Apply</button>
          </div>
        </form>
      </div>

      <div class="panel">
        <h2><span class="panel-icon panel-icon-snapshot"><i class="fa-solid fa-house-signal"></i></span> Room Snapshot</h2>
        <div class="snapshot-row"><span>Device ID</span><strong><?= htmlspecialchars($device_id) ?></strong></div>
        <div class="snapshot-row"><span>Connection</span><strong class="status-<?= $connectionStatus === 'Connected' ? 'NORMAL' : 'CRITICAL' ?>"><?= htmlspecialchars($connectionStatus) ?></strong></div>
        <div class="snapshot-row"><span>Last Updated</span><strong><?= htmlspecialchars($latest['created_at']) ?></strong></div>
        <div class="snapshot-row"><span>Total Records</span><strong><?= $totalRecords ?></strong></div>
      </div>

      <div class="panel">
        <h2><span class="panel-icon panel-icon-insight"><i class="fa-solid fa-chart-simple"></i></span> Today's Insight</h2>
        <div class="snapshot-row"><span>Readings Today</span><strong><?= $todayStats['c'] ?? 0 ?></strong></div>
        <div class="snapshot-row"><span>Average Temperature</span><strong><?= $todayStats['avg_temp'] !== null ? round($todayStats['avg_temp'], 1) . "&deg;C" : "-" ?></strong></div>
        <div class="snapshot-row"><span>Warning/Critical (24h)</span><strong><?= $activeWarnings ?></strong></div>
      </div>

      <div class="panel">
        <h2><span class="panel-icon panel-icon-tip"><i class="fa-solid fa-lightbulb"></i></span> Tip</h2>
        <p class="sub" style="font-size:13px;line-height:1.5;">
          <?php if ($activeWarnings > 5): ?>
            This room had <?= $activeWarnings ?> warning/critical readings in the last 24 hours. Check ventilation or cooling.
          <?php elseif ($latest['status'] !== 'NORMAL'): ?>
            Current reading is outside the safe range - check the status banner above for details.
          <?php else: ?>
            Conditions have been stable. No action needed right now.
          <?php endif; ?>
        </p>
        <a href="analytics.php" class="alert-panel-link" style="text-align:left;margin-top:10px;">See full trends in Stats &rarr;</a>
      </div>
    </div>
    <?php elseif ($device_id): ?>
    <div class="panel"><h2>No sensor data yet for this room</h2></div>
    <?php else: ?>
    <div class="empty-state">
      <div class="empty-state-icon"><i class="fa-solid fa-house"></i></div>
      <h2>You haven't added a room yet</h2>
      <p>Add your first room/device to start monitoring temperature, humidity, air quality, and light level.</p>
      <a href="add_room.php" class="btn-save" style="text-decoration:none; display:inline-flex; align-items:center; gap:8px;">
        <i class="fa-solid fa-plus"></i> Add Your First Room
      </a>
    </div>
    <?php endif; ?>

  </div>
</div>
</body>
</html>
