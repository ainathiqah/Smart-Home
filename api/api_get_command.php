<?php
require_once "../config/db_config.php";
$conn = getDB();

header('Content-Type: application/json');

$device_id = $_GET['device_id'] ?? '';

if ($device_id === '') {
    echo json_encode(["error" => "device_id is required"]);
    exit;
}

$stmt = $conn->prepare("SELECT threshold_1, threshold_2, humidity_threshold, air_threshold, upload_interval, alert_enabled, output_mode FROM device_settings WHERE device_id = ?");
$stmt->bind_param("s", $device_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
    echo json_encode([
        "temp_threshold" => 32,
        "light_threshold" => 3000,
        "humidity_threshold" => 70,
        "air_threshold" => 0,
        "upload_interval" => 10,
        "alert_enabled" => 1,
        "output_mode" => "AUTO"
    ]);
    exit;
}

echo json_encode([
    "temp_threshold"  => (float)$row['threshold_1'],
    "light_threshold" => (int)$row['threshold_2'],
    "humidity_threshold" => (float)$row['humidity_threshold'],
    "air_threshold"   => (int)$row['air_threshold'],
    "upload_interval" => (int)$row['upload_interval'],
    "alert_enabled"   => (int)$row['alert_enabled'],
    "output_mode"     => $row['output_mode']
]);

$stmt->close();
$conn->close();
