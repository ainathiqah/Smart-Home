<div class="sidebar">
  <div class="sidebar-logo"><i class="fa-solid fa-house-signal"></i></div>
  <div class="nav-group">
    <a href="dashboard.php" class="<?= $active === 'dashboard' ? 'active' : '' ?>" title="Dashboard">
      <i class="fa-solid fa-gauge-high"></i><span>Home</span>
    </a>
    <a href="rooms.php" class="<?= $active === 'rooms' ? 'active' : '' ?>" title="Rooms">
      <i class="fa-solid fa-building"></i><span>Rooms</span>
    </a>
    <a href="settings.php" class="<?= $active === 'settings' ? 'active' : '' ?>" title="Settings">
      <i class="fa-solid fa-gear"></i><span>Settings</span>
    </a>
    <a href="analytics.php" class="<?= $active === 'analytics' ? 'active' : '' ?>" title="Analytics">
      <i class="fa-solid fa-chart-line"></i><span>Statistics</span>
    </a>
  </div>
  <a href="logout.php" class="logout-link" title="Logout">
    <i class="fa-solid fa-right-from-bracket"></i><span>Logout</span>
  </a>
</div>
