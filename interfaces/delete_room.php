<?php
require_once "../config/auth_check.php";
require_once "../config/db_config.php";
$conn = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $device_id = $_POST['device_id'] ?? '';

    $stmt = $conn->prepare("DELETE FROM device_settings WHERE device_id = ? AND user_id = ?");
    $stmt->bind_param("si", $device_id, $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();

    if ($_SESSION['active_device'] === $device_id) {
        unset($_SESSION['active_device']);
    }
}

header("Location: rooms.php");
exit;
