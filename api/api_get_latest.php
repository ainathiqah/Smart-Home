<?php
require_once "../config/auth_check.php";
require_once "../config/db_config.php";
$conn = getDB();

header("Content-Type: application/json");

$device_id = getActiveDeviceId($conn, $_SESSION['user_id']);

if (!$device_id) {
    echo json_encode(["error" => "no_active_device"]);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM sensor_data WHERE device_id = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("s", $device_id);
$stmt->execute();
$latest = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$latest) {
    echo json_encode(["error" => "no_data"]);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM device_settings WHERE device_id = ?");
$stmt->bind_param("s", $device_id);
$stmt->execute();
$settings = $stmt->get_result()->fetch_assoc();
$stmt->close();

$reasons = buildStatusReasons($latest, $settings);

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM sensor_data WHERE device_id = ? AND status != 'NORMAL' AND created_at >= NOW() - INTERVAL 1 DAY");
$stmt->bind_param("s", $device_id);
$stmt->execute();
$activeWarnings = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS c, AVG(temperature) AS avg_temp FROM sensor_data WHERE device_id = ? AND created_at >= NOW() - INTERVAL 1 DAY");
$stmt->bind_param("s", $device_id);
$stmt->execute();
$todayStats = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM sensor_data WHERE device_id = ?");
$stmt->bind_param("s", $device_id);
$stmt->execute();
$totalRecords = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

$connectionStatus = getDeviceConnectionStatus($conn, $device_id);

echo json_encode([
    "device_id"      => $device_id,
    "status"         => $latest['status'],
    "temp"           => $latest['temperature'],
    "humidity"       => $latest['humidity'],
    "air"            => $latest['air_quality'] == 0 ? "GAS" : "NORMAL",
    "light"          => $latest['light_level'],
    "created_at"     => $latest['created_at'],
    "reasons"        => $reasons,
    "connected"      => $connectionStatus === 'Connected',
    "active_warnings" => $activeWarnings,
    "readings_today" => (int)($todayStats['c'] ?? 0),
    "avg_temp_today" => $todayStats['avg_temp'] !== null ? round($todayStats['avg_temp'], 1) : null,
    "total_records"  => $totalRecords,
]);
