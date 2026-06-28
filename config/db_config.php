<?php
$DB_HOST = "localhost";
$DB_NAME = "smart_indoor";
$DB_USER = "root";
$DB_PASS = "";

function getDB() {
    global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS;
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($conn->connect_error) {
        die(json_encode(["error" => "DB connection failed: " . $conn->connect_error]));
    }
    return $conn;
}

// Returns the device_id owned by the given user, or null if they have none.
function getUserDeviceId($conn, $user_id) {
    $stmt = $conn->prepare("SELECT device_id FROM device_settings WHERE user_id = ? ORDER BY id ASC LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? $row['device_id'] : null;
}

// Returns all rooms/devices owned by the given user.
function getUserDevices($conn, $user_id) {
    $stmt = $conn->prepare("SELECT device_id, room_name FROM device_settings WHERE user_id = ? ORDER BY id ASC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// Resolves which device/room is "active" for this session: the one the user
// last clicked in the Rooms menu, falling back to their first device.
function getActiveDeviceId($conn, $user_id) {
    if (!empty($_SESSION['active_device'])) {
        $stmt = $conn->prepare("SELECT device_id FROM device_settings WHERE user_id = ? AND device_id = ?");
        $stmt->bind_param("is", $user_id, $_SESSION['active_device']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) return $row['device_id'];
    }
    return getUserDeviceId($conn, $user_id);
}

// Returns "Connected" if the device has uploaded data within the last
// 2x its configured upload interval, otherwise "Disconnected". A stale
// wifi_status flag alone isn't trustworthy if the ESP32 silently went offline.
function getDeviceConnectionStatus($conn, $device_id) {
    $stmt = $conn->prepare("
        SELECT TIMESTAMPDIFF(SECOND, MAX(sd.created_at), NOW()) AS seconds_since_last,
               ds.upload_interval
        FROM device_settings ds
        LEFT JOIN sensor_data sd ON sd.device_id = ds.device_id
        WHERE ds.device_id = ?
        GROUP BY ds.upload_interval
    ");
    $stmt->bind_param("s", $device_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || $row['seconds_since_last'] === null) return "Disconnected";

    $maxGap = max($row['upload_interval'] * 2, 20);
    return $row['seconds_since_last'] <= $maxGap ? "Connected" : "Disconnected";
}
