CREATE DATABASE IF NOT EXISTS smart_indoor;
USE smart_indoor;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS device_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    device_id VARCHAR(50) NOT NULL UNIQUE,
    room_name VARCHAR(50) DEFAULT 'Room',
    wifi_status VARCHAR(20) DEFAULT 'disconnected',
    threshold_1 FLOAT DEFAULT 32,      -- temp_threshold
    threshold_2 INT DEFAULT 3000,      -- light_threshold
    humidity_threshold FLOAT DEFAULT 70,  -- indoor comfort ceiling per ASHRAE 55 / DOSH ICOP IAQ 2010 (40-70% RH)
    air_threshold INT DEFAULT 0,
    upload_interval INT DEFAULT 10,
    alert_enabled TINYINT DEFAULT 1,
    output_mode VARCHAR(10) DEFAULT 'AUTO',
    actuator_status VARCHAR(10) DEFAULT 'OFF',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS sensor_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(50) NOT NULL,
    sensor_1 FLOAT,        -- temperature
    sensor_2 FLOAT,        -- humidity
    sensor_3 INT,          -- air quality (MQ digital: 0/1)
    light_level INT,
    status VARCHAR(20),
    output_status VARCHAR(10),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (device_id) REFERENCES device_settings(device_id) ON DELETE CASCADE
);

-- default login: username "admin", password "admin123"
INSERT INTO users (username, password_hash)
VALUES ('admin', '$2y$10$VGz5ZHY5aRquMabcljBIEO8jP5o6nEYDoGXLwZFjDlb08eUIf8h92')
ON DUPLICATE KEY UPDATE username = username;

INSERT INTO device_settings (user_id, device_id, room_name, threshold_1, threshold_2, air_threshold, upload_interval, alert_enabled, output_mode)
VALUES (
    (SELECT id FROM users WHERE username = 'admin'),
    'INDOOR_001', 'Living Room', 32, 3000, 0, 10, 1, 'AUTO'
)
ON DUPLICATE KEY UPDATE device_id = device_id;

-- If device_settings already existed before this column was added, run this separately:
-- ALTER TABLE device_settings ADD COLUMN humidity_threshold FLOAT DEFAULT 70;
