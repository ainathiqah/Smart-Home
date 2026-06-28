<?php
require_once "../config/auth_check.php";
require_once "../config/db_config.php";
$conn = getDB();
$active = "rooms";

if (isset($_GET['select'])) {
    $_SESSION['active_device'] = $_GET['select'];
    header("Location: dashboard.php");
    exit;
}

$rooms = getUserDevices($conn, $_SESSION['user_id']);
$current = getActiveDeviceId($conn, $_SESSION['user_id']);

$colors = ["#f7a8c4", "#7fd3e8", "#ffd97a", "#9fb8f0", "#9be3c4", "#c9a8f7"];
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rooms - Smart Home</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . "/style.css") ?>">
<style>
.room-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
.room-card {
    border-radius: 18px;
    padding: 26px 20px;
    color: #fff;
    box-shadow: 0 6px 20px rgba(45, 110, 230, 0.12);
    position: relative;
}
.room-card .card-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 18px;
}
.room-card .card-top-right { display: flex; align-items: center; gap: 8px; }
.room-card .icon-link { display: block; }
.room-card .icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: rgba(255,255,255,0.28);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}
.room-card .delete-btn {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    background: rgba(255,255,255,0.85);
    color: #c23b30;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 13px;
    transition: background 0.15s ease;
}
.room-card .delete-btn:hover { background: #fff; }
.room-card .name-pill {
    display: flex;
    align-items: center;
    gap: 8px;
    background: rgba(255,255,255,0.93);
    border-radius: 12px;
    padding: 10px 14px;
    text-decoration: none;
}
.room-card .name-pill .name { font-size: 15px; font-weight: 700; color: #1f2d50; }
.room-card .name-pill .sub { font-size: 11px; font-weight: 700; color: #5c6b85; }
.room-card .name-pill .check-icon { margin-left: auto; color: #2bb673; font-size: 14px; }
.room-card .conn-dot-row {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    font-weight: 700;
    padding: 5px 12px;
    border-radius: 20px;
    background: rgba(255,255,255,0.95);
}
.room-card .conn-dot-row .dot { width: 7px; height: 7px; border-radius: 50%; }
.room-card .conn-dot-row.is-connected { color: #1c8a56; }
.room-card .conn-dot-row.is-connected .dot { background: #1c8a56; }
.room-card .conn-dot-row.is-disconnected { color: #c23b30; }
.room-card .conn-dot-row.is-disconnected .dot { background: #c23b30; }
.add-card {
    border-radius: 18px;
    border: 2px dashed #c6d6f5;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6b94f0;
    font-size: 30px;
    text-decoration: none;
    min-height: 110px;
}
</style>
</head>
<body>
<div class="app">
  <?php include "sidebar.php"; ?>
  <div class="main">
    <div class="topbar">
      <h1>My Rooms</h1>
      <div class="profile-chip"><i class="fa-solid fa-circle-user"></i> <?= htmlspecialchars($_SESSION['username']) ?></div>
    </div>

    <?php if (count($rooms) === 0): ?>
    <div class="panel" style="background:#fdf3e0;border:1px solid #f3dca7;">
      <h2 style="color:#b3791f;"><i class="fa-solid fa-triangle-exclamation"></i> You don't have any rooms yet</h2>
      <p style="font-size:13px;color:#8a6420;">
        Dashboard, Settings, and Stats all require an active room - that's why those pages keep sending you back here.
        Click <strong>"Add Room"</strong> below to create one and unlock the rest of the app.
      </p>
    </div>
    <?php endif; ?>
    <div class="panel">
      <h2>Select a room to monitor &amp; control</h2>
      <p class="panel-sub" style="margin-bottom:18px;">
        <?= count($rooms) ?> room<?= count($rooms) === 1 ? '' : 's' ?> connected to your account
      </p>
      <div class="room-grid">
        <?php foreach ($rooms as $i => $room): ?>
          <div class="room-card" style="background: <?= $colors[$i % count($colors)] ?>">
            <div class="card-top">
              <a class="icon-link" href="rooms.php?select=<?= urlencode($room['device_id']) ?>">
                <span class="icon"><i class="fa-solid fa-house"></i></span>
              </a>
              <div class="card-top-right">
                <?php $connStatus = getDeviceConnectionStatus($conn, $room['device_id']); ?>
                <span class="conn-dot-row is-<?= strtolower($connStatus) ?>">
                  <span class="dot"></span><?= $connStatus ?>
                </span>
                <form method="POST" action="delete_room.php" onsubmit="return confirm('Delete &quot;<?= htmlspecialchars($room['room_name'], ENT_QUOTES) ?>&quot; and all of its recorded data? This cannot be undone.');">
                  <input type="hidden" name="device_id" value="<?= htmlspecialchars($room['device_id']) ?>">
                  <button type="submit" class="delete-btn" title="Delete room"><i class="fa-solid fa-trash"></i></button>
                </form>
              </div>
            </div>
            <a class="name-pill" href="rooms.php?select=<?= urlencode($room['device_id']) ?>">
              <span class="name"><?= htmlspecialchars($room['room_name']) ?></span>
              <span class="sub"><?= htmlspecialchars($room['device_id']) ?></span>
              <?php if ($room['device_id'] === $current): ?><i class="fa-solid fa-circle-check check-icon" title="Currently active"></i><?php endif; ?>
            </a>
          </div>
        <?php endforeach; ?>
        <a class="add-card" href="add_room.php">+</a>
      </div>
    </div>

  </div>
</div>
</body>
</html>
