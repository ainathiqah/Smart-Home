<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle ?? 'No Room') ?> - Smart Home</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . "/style.css") ?>">
</head>
<body>
<div class="app">
  <?php include "sidebar.php"; ?>
  <div class="main">
    <div class="topbar">
      <h1><?= htmlspecialchars($pageTitle ?? 'No Room Yet') ?></h1>
      <div class="profile-chip"><i class="fa-solid fa-circle-user"></i> <?= htmlspecialchars($_SESSION['username']) ?></div>
    </div>

    <div class="empty-state">
      <div class="empty-state-icon"><i class="fa-solid fa-house"></i></div>
      <h2>You haven't added a room yet</h2>
      <p>Add your first room/device to start monitoring temperature, humidity, air quality, and light level.</p>
      <a href="add_room.php" class="btn-save" style="text-decoration:none; display:inline-flex; align-items:center; gap:8px;">
        <i class="fa-solid fa-plus"></i> Add Your First Room
      </a>
    </div>

  </div>
</div>
</body>
</html>
