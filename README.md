# Smart Home Environment Monitoring and Control System

A web-based dashboard for monitoring and managing the indoor environment of a smart home, built on an ESP32 with a DHT11, MQ-2, and LDR sensor, paired with a PHP and MySQL backend.

<p align="center">
  <img src="https://github.com/user-attachments/assets/64bbb634-f0b9-4251-b652-6eec3fe168f4" alt="Login Page" width="650">
</p>

The login page is the entry point to the system. Users log in with a username and password, or register a new account if they don't have one yet. Once logged in, each user can manage one or more rooms, each monitored by its own ESP32 device.

## What the System Does

- Reads temperature, humidity, air quality, and light level from a room in real time
- Sends readings from the ESP32 to the web dashboard over WiFi
- Shows live readings, history, and simple insights for each room
- Lets the user set safety thresholds and control the buzzer alert from the dashboard, without reprogramming the hardware
- Supports more than one room per account, each with its own device

## Folder Structure

```
FinalSensor_297545/
├── api/            PHP endpoints the ESP32 calls directly
│   ├── api_add_data.php        receives and stores new sensor readings
│   └── api_get_command.php     sends back the current thresholds and buzzer mode
│
├── config/         Shared setup used by every page
│   ├── db_config.php           database connection
│   └── auth_check.php          checks the user is logged in
│
├── db/             Database structure
│   └── schema.sql               table definitions (users, device_settings, sensor_data)
│
├── interfaces/     All web pages the user sees in the browser
│   ├── login.php / register.php
│   ├── dashboard.php           live readings and quick controls
│   ├── rooms.php                choose, add, or delete a room
│   ├── settings.php            edit thresholds and buzzer mode
│   ├── analytics.php           history, trends, and insights (Stats page)
│   ├── sidebar.php             shared navigation menu
│   └── style.css               shared styling
│
├── index.php       entry point - redirects to the login or dashboard page
└── SmartIndoor.ino  ESP32 firmware
```

The folders are split by role: `api/` is only ever called by the ESP32, `config/` and `db/` hold the shared backend setup, and `interfaces/` holds everything a human user sees and clicks through in the browser.
