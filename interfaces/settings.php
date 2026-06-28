<?php
require_once "../config/auth_check.php";
require_once "../config/db_config.php";
$conn = getDB();
$active = "settings";

if (!isset($_SESSION['active_device'])) {
    header("Location: rooms.php");
    exit;
}

$device_id = getActiveDeviceId($conn, $_SESSION['user_id']);

if (!$device_id) {
    $pageTitle = "Settings";
    include "no_room.php";
    exit;
}

$settingsError = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $t1 = $_POST['threshold_1'] ?? '';
    $t2 = $_POST['threshold_2'] ?? '';
    $hum = $_POST['humidity_threshold'] ?? '';
    $air = $_POST['air_threshold'] ?? '';
    $interval = $_POST['upload_interval'] ?? '';

    if ($t1 === '' || $t2 === '' || $hum === '' || $air === '' || $interval === '') {
        $settingsError = "All fields are required.";
    } elseif (!is_numeric($t1) || !is_numeric($t2) || !is_numeric($hum) || !is_numeric($air) || !is_numeric($interval)) {
        $settingsError = "All fields must be numbers.";
    } elseif ($t1 < 0 || $t1 > 60) {
        $settingsError = "Temperature threshold must be between 0 and 60°C.";
    } elseif ($t2 < 0 || $t2 > 4095) {
        $settingsError = "Light threshold must be between 0 and 4095 (raw ADC range).";
    } elseif ($hum < 0 || $hum > 100) {
        $settingsError = "Humidity threshold must be between 0 and 100%.";
    } elseif ($air < 0 || $air > 1) {
        $settingsError = "Air quality threshold must be 0 or 1 (digital sensor output).";
    } elseif ($interval < 5) {
        $settingsError = "Upload interval must be at least 5 seconds.";
    } else {
        $stmt = $conn->prepare("UPDATE device_settings SET threshold_1=?, threshold_2=?, humidity_threshold=?, air_threshold=?, upload_interval=? WHERE device_id=?");
        $stmt->bind_param("didiis", $t1, $t2, $hum, $air, $interval, $device_id);
        $stmt->execute();
        $stmt->close();

        header("Location: settings.php");
        exit;
    }
}

$stmt = $conn->prepare("SELECT * FROM device_settings WHERE device_id = ?");
$stmt->bind_param("s", $device_id);
$stmt->execute();
$settings = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Settings - Smart Home</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . "/style.css") ?>">
</head>
<body>
<div class="app">
  <?php include "sidebar.php"; ?>
  <div class="main">
    <div class="topbar">
      <h1><?= htmlspecialchars($settings['room_name']) ?> Settings</h1>
      <div class="profile-chip"><i class="fa-solid fa-circle-user"></i> <?= htmlspecialchars($_SESSION['username']) ?></div>
    </div>

    <div class="panel">
      <h2>Thresholds &amp; Upload Interval</h2>
      <?php if ($settingsError): ?><p class="login-error"><?= htmlspecialchars($settingsError) ?></p><?php endif; ?>
      <form method="POST">
        <div class="form-row">
          <label>Temperature Threshold</label>
          <div class="threshold-input threshold-temp">
            <i class="fa-solid fa-temperature-half"></i>
            <button type="button" class="step-btn" onclick="stepValue(this, -1)">&minus;</button>
            <input type="number" step="0.1" name="threshold_1" value="<?= htmlspecialchars($settings['threshold_1']) ?>" data-step="0.1" data-min="0" data-max="60">
            <button type="button" class="step-btn" onclick="stepValue(this, 1)">+</button>
            <span class="threshold-unit">&deg;C</span>
          </div>
        </div>
        <div class="form-row">
          <label>Light Threshold</label>
          <div class="threshold-input threshold-light">
            <i class="fa-solid fa-sun"></i>
            <button type="button" class="step-btn" onclick="stepValue(this, -1)">&minus;</button>
            <input type="number" name="threshold_2" value="<?= htmlspecialchars($settings['threshold_2']) ?>" data-step="50" data-min="0" data-max="4095">
            <button type="button" class="step-btn" onclick="stepValue(this, 1)">+</button>
            <span class="threshold-unit">raw</span>
          </div>
        </div>
        <div class="form-row">
          <label>Humidity Threshold</label>
          <div class="threshold-input threshold-hum">
            <i class="fa-solid fa-droplet"></i>
            <button type="button" class="step-btn" onclick="stepValue(this, -1)">&minus;</button>
            <input type="number" step="0.1" name="humidity_threshold" value="<?= htmlspecialchars($settings['humidity_threshold']) ?>" data-step="0.1" data-min="0" data-max="100">
            <button type="button" class="step-btn" onclick="stepValue(this, 1)">+</button>
            <span class="threshold-unit">% RH</span>
          </div>
          <p class="sub" style="margin-top:6px;color:#697791;font-size:11px;">Indoor comfort range is 40-70% RH (ASHRAE 55 / DOSH ICOP IAQ 2010). Default 70% triggers a warning above the comfort ceiling.</p>
        </div>
        <div class="form-row">
          <label>Air Quality Threshold</label>
          <div class="threshold-input threshold-air">
            <i class="fa-solid fa-wind"></i>
            <button type="button" class="step-btn" onclick="stepValue(this, -1)">&minus;</button>
            <input type="number" name="air_threshold" value="<?= htmlspecialchars($settings['air_threshold']) ?>" data-step="1" data-min="0" data-max="1">
            <button type="button" class="step-btn" onclick="stepValue(this, 1)">+</button>
            <span class="threshold-unit">DO</span>
          </div>
        </div>
        <div class="form-row">
          <label>Upload Interval</label>
          <div class="threshold-input threshold-upload">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <button type="button" class="step-btn" onclick="stepValue(this, -1)">&minus;</button>
            <input type="number" name="upload_interval" value="<?= htmlspecialchars($settings['upload_interval']) ?>" data-step="5" data-min="5" data-max="3600">
            <button type="button" class="step-btn" onclick="stepValue(this, 1)">+</button>
            <span class="threshold-unit">sec</span>
          </div>
        </div>
        <button type="submit" class="btn-save">Save Settings</button>
      </form>
    </div>

  </div>
</div>
<script>
function stepValue(btn, dir) {
  const input = dir < 0 ? btn.nextElementSibling : btn.previousElementSibling;
  const step = parseFloat(input.dataset.step || "1");
  const min = parseFloat(input.dataset.min ?? "-Infinity");
  const max = parseFloat(input.dataset.max ?? "Infinity");
  let val = parseFloat(input.value) || 0;
  val = Math.round((val + dir * step) * 10) / 10;
  if (val < min) val = min;
  if (val > max) val = max;
  input.value = val;
}
</script>
</body>
</html>
