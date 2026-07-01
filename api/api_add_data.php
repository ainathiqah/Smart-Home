<?php
require_once "../config/db_config.php";
$conn = getDB();

$device_id      = $_GET['device_id'] ?? '';
$temperature    = $_GET['temperature'] ?? 0;
$humidity       = $_GET['humidity'] ?? 0;
$air_quality    = $_GET['air_quality'] ?? 0;
$light_level    = $_GET['light_level'] ?? 0;
$system_status  = $_GET['system_status'] ?? 'NORMAL';
$output_status  = $_GET['output_status'] ?? 'OFF';

if ($device_id === '') {
    echo json_encode(["error" => "device_id is required"]);
    exit;
}

$stmt = $conn->prepare("INSERT INTO sensor_data (device_id, temperature, humidity, air_quality, light_level, status, output_status) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("sddiiss", $device_id, $temperature, $humidity, $air_quality, $light_level, $system_status, $output_status);

if ($stmt->execute()) {
    $stmt->close();

    $stmt = $conn->prepare("UPDATE device_settings SET wifi_status = 'connected' WHERE device_id = ?");
    $stmt->bind_param("s", $device_id);
    $stmt->execute();

    echo json_encode(["result" => "ok"]);
} else {
    echo json_encode(["error" => $stmt->error]);
}

$stmt->close();
$conn->close();
