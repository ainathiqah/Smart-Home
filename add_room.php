<?php
require_once "config/auth_check.php";
require_once "config/db_config.php";
$conn = getDB();
$active = "rooms";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_name = trim($_POST['room_name']);
    $device_id = trim($_POST['device_id']);

    if ($room_name === '' || $device_id === '') {
        $error = "Please fill in both fields.";
    } else {
        $stmt = $conn->prepare("INSERT INTO device_settings (user_id, device_id, room_name, threshold_1, threshold_2, humidity_threshold, air_threshold, upload_interval, output_mode) VALUES (?, ?, ?, 34, 3000, 70, 0, 10, 'AUTO')");
        $stmt->bind_param("iss", $_SESSION['user_id'], $device_id, $room_name);
        if ($stmt->execute()) {
            header("Location: rooms.php");
            exit;
        } else {
            $error = "Device ID already exists. Choose a unique device ID.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Add Room - Smart Home</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . "/style.css") ?>">
</head>
<body>
<div class="app">
  <?php include "sidebar.php"; ?>
  <div class="main">
    <div class="topbar">
      <h1>Add a Room / Device</h1>
      <div class="profile-chip"><i class="fa-solid fa-circle-user"></i> <?= htmlspecialchars($_SESSION['username']) ?></div>
    </div>

    <div class="panel">
      <?php if ($error): ?><p class="login-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
      <form method="POST">
        <div class="form-row">
          <label>Room Name</label>
          <input type="text" name="room_name" placeholder="e.g. Bedroom" required>
        </div>
        <div class="form-row">
          <label>Device ID (must match ESP32 DEVICE_ID)</label>
          <input type="text" name="device_id" placeholder="e.g. INDOOR_002" required>
        </div>
        <button type="submit" class="btn-save">Add Room</button>
      </form>
    </div>

  </div>
</div>
</body>
</html>
