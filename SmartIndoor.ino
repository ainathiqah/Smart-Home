#include <WiFi.h>
#include <WiFiManager.h>
#include <Preferences.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <Wire.h>
#include <Adafruit_GFX.h>
#include <Adafruit_SSD1306.h>
#include <DHT.h>

// ================= WIFI + BACKEND =================
// WiFi credentials are no longer hardcoded - WiFiManager handles them.
// On first boot (or after a reset), connect a phone/laptop to the
// "SmartHome-Setup" WiFi network it creates, open the captive portal,
// and enter your real WiFi SSID/password there. It is saved to flash
// and reused automatically on every future boot.
//
// Device ID is also set through this same portal (no IDE editing needed).
// To re-open the portal later and change it, hold the push button down
// while powering on / resetting the board.
const char* SERVER_BASE = "http://10.200.94.91/FinalSensor_297545";
String deviceId = "INDOOR_003";  // fallback default if nothing saved yet
Preferences prefs;

// ================= PIN CONFIGURATION =================
#define SDA_PIN 21       // OLED SDA
#define SCL_PIN 22       // OLED SCL
#define DHT_PIN 4        // DHT11 data pin
#define DHT_TYPE DHT11
#define MQ_PIN 32        // MQ-2 digital output DO
#define LDR_PIN 34       // LDR module analog output AO
#define BTN_PIN 26       // Push button
#define BUZZER_PIN 27    // 2-legged buzzer positive pin

// ================= OBJECTS =================
Adafruit_SSD1306 display(128, 64, &Wire, -1);
DHT dht(DHT_PIN, DHT_TYPE);

// ================= SYSTEM VARIABLES =================
bool oledOK = false;
int page = 1;

float temp = 0, hum = 0;
float tempLimit = 32;
float humLimit = 70;

int mq = 1;
int lightVal = 0;       // raw ADC reading (0-4095), kept internally only
int lightPercent = 0;   // 0% = totally dark, 100% = max brightness - this is what gets sent/displayed
int lightLimit = 30;    // % threshold: below this = DARK, below 2x this = DIM, above = BRIGHT
int airLimit = 0;
int uploadSec = 10;

String lightStatus = "UNKNOWN";
String mqStatus = "NORMAL";
String status = "NORMAL";
String buzzerStatus = "OFF";
String outputMode = "AUTO";

// ================= TIMER VARIABLES =================
unsigned long tRead = 0;
unsigned long tOLED = 0;
unsigned long tUpload = 0;
unsigned long tSettings = 0;
unsigned long tBuzz = 0;

// ================= SENSOR WARM-UP =================
// The MQ-2 gas sensor's heating element needs time to stabilize after
// power-on before its readings are reliable. We hold uploads/buzzer back
// for a short warm-up window right after WiFi connects, showing a
// countdown on the OLED - same idea as the warm-up window described in
// commercial gas-sensor datasheets.
const unsigned long WARMUP_MS = 30000;  // 30 seconds
bool warmingUp = false;
unsigned long warmupStart = 0;

// ================= WIFI CONNECTION =================
// Runs once in setup(): tries saved WiFi credentials first. If none are
// saved (or they fail), it opens an access point named "SmartHome-Setup"
// with a captive portal page for entering new WiFi details - no reflashing
// needed to change networks.
void setupWiFi() {
  prefs.begin("smartiot", false);
  deviceId = prefs.getString("device_id", deviceId);

  WiFiManager wm;
  wm.setConfigPortalTimeout(180);  // give up after 3 minutes if no one configures it

  WiFiManagerParameter custom_device_id("device_id", "Device ID (must match a room you created on the dashboard)", deviceId.c_str(), 32);
  wm.addParameter(&custom_device_id);

  // Holding the push button down while the board boots forces the config
  // portal to reopen even if WiFi is already saved - lets you change the
  // Device ID later without touching the Arduino IDE.
  bool forcePortal = digitalRead(BTN_PIN) == LOW;

  bool connected = forcePortal
    ? wm.startConfigPortal("SmartHome-Setup")
    : wm.autoConnect("SmartHome-Setup");

  if (!connected) {
    Serial.println("WiFi setup failed or timed out. Restarting...");
    delay(2000);
    ESP.restart();
  }

  String enteredId = custom_device_id.getValue();
  enteredId.trim();
  if (enteredId != "") {
    deviceId = enteredId;
    prefs.putString("device_id", deviceId);
  }

  Serial.println("WiFi connected: " + WiFi.SSID());
  Serial.println("ESP32 IP: " + WiFi.localIP().toString());
  Serial.println("Device ID: " + deviceId);

  warmingUp = true;
  warmupStart = millis();
}

// Called repeatedly in loop() to recover from a dropped connection
// without re-opening the config portal (uses the already-saved credentials).
void connectWiFi() {
  if (WiFi.status() == WL_CONNECTED) return;
  WiFi.reconnect();
}

// ================= HTTP GET REQUEST =================
bool httpGET(String url, String &res) {
  if (WiFi.status() != WL_CONNECTED) return false;

  HTTPClient http;
  http.begin(url);
  http.setFollowRedirects(HTTPC_FORCE_FOLLOW_REDIRECTS);
  http.setRedirectLimit(10);

  int code = http.GET();

  res = code > 0 ? http.getString() : "";

  Serial.println("HTTP Code: " + String(code));

  http.end();

  return code == 200;
}

// ================= OLED SETUP =================
void setupOLED() {
  Wire.begin(SDA_PIN, SCL_PIN);

  // Try common OLED address 0x3C first
  Wire.beginTransmission(0x3C);
  if (Wire.endTransmission() == 0) {
    oledOK = display.begin(SSD1306_SWITCHCAPVCC, 0x3C);
  }
  else {
    // Try alternative OLED address 0x3D
    Wire.beginTransmission(0x3D);
    if (Wire.endTransmission() == 0) {
      oledOK = display.begin(SSD1306_SWITCHCAPVCC, 0x3D);
    }
  }

  if (oledOK) {
    display.clearDisplay();
    display.setTextColor(SSD1306_WHITE);
    display.setTextSize(1);
    display.setCursor(0, 0);
    display.println("Smart Home");
    display.println("System Ready");
    display.display();
  }
}

// ================= LIGHT CLASSIFICATION =================
// lightPercent is already in human-readable terms: 0% = dark, 100% = bright.
String getLightStatus(int percent) {
  if (percent < lightLimit) return "DARK";
  if (percent < lightLimit * 2) return "DIM";
  return "BRIGHT";
}

// ================= READ SENSOR VALUES =================
void readSensors() {
  temp = dht.readTemperature();
  hum = dht.readHumidity();

  mq = digitalRead(MQ_PIN);
  lightVal = analogRead(LDR_PIN);

  // LDR module's raw reading is reversed (low value = bright, high value = dark),
  // so flip it into an intuitive 0-100% brightness scale before using it anywhere else.
  lightPercent = round((1.0 - (float)lightVal / 4095.0) * 100.0);

  mqStatus = mq == LOW ? "GAS" : "NORMAL";
  lightStatus = getLightStatus(lightPercent);

  // Rule-based status classification
  status = "NORMAL";

  if (!isnan(temp) && temp >= tempLimit) {
    status = "WARNING";
  }

  if (!isnan(hum) && hum >= humLimit) {
    status = "WARNING";
  }

  // Light level is informational only (Bright/Dim/Dark) - it does not affect
  // system status or the buzzer, since a dark room (e.g. lights off at night)
  // is normal household behavior, not an unsafe condition.

  if (mq <= airLimit) {
    status = "CRITICAL";
  }
}

// ================= PUSH BUTTON PAGE CONTROL =================
void handleButton() {
  static bool oldPress = false;
  bool press = digitalRead(BTN_PIN) == LOW;

  if (press && !oldPress) {
    page++;

    if (page > 4) page = 1;

    delay(250);  // Debounce delay
  }

  oldPress = press;
}

// ================= BUZZER CONTROL =================
void buzzOn(int freq) {
  tone(BUZZER_PIN, freq);
  buzzerStatus = "ON";
}

void buzzOff() {
  noTone(BUZZER_PIN);
  buzzerStatus = "OFF";
}

void updateBuzzer() {
  if (outputMode == "OFF") {
    buzzOff();
    return;
  }

  if (outputMode == "ON") {
    buzzOn(1000);
    return;
  }

  // AUTO mode: warning = slow beep, critical = fast beep
  if (status == "WARNING" || status == "CRITICAL") {
    unsigned long now = millis();
    static bool state = false;

    int interval = status == "CRITICAL" ? 150 : 500;
    int freq = status == "CRITICAL" ? 2000 : 1200;

    if (now - tBuzz >= interval) {
      tBuzz = now;
      state = !state;

      if (state) buzzOn(freq);
      else buzzOff();
    }
  }
  else {
    buzzOff();
  }
}

// ================= GET SETTINGS FROM DASHBOARD =================
void fetchSettings() {
  String res;
  String url = String(SERVER_BASE) + "/api/api_get_command.php?device_id=" + deviceId;

  if (!httpGET(url, res)) return;

  StaticJsonDocument<512> doc;
  if (deserializeJson(doc, res)) return;

  tempLimit = doc["temp_threshold"] | 32.0;
  humLimit = doc["humidity_threshold"] | 70.0;
  airLimit = doc["air_threshold"] | 0;
  lightLimit = doc["light_threshold"] | 30;
  uploadSec = doc["upload_interval"] | 10;

  outputMode = doc["output_mode"].as<String>();
  if (outputMode == "") outputMode = "AUTO";
  outputMode.toUpperCase();

  if (uploadSec < 5) uploadSec = 5;
}

// ================= UPLOAD SENSOR DATA TO BACKEND =================
void uploadData() {
  String res;
  String url = String(SERVER_BASE) + "/api/api_add_data.php";

  url += "?device_id=" + deviceId;
  url += "&temperature=" + String(isnan(temp) ? 0 : temp, 2);
  url += "&humidity=" + String(isnan(hum) ? 0 : hum, 2);
  url += "&air_quality=" + String(mq);
  url += "&light_level=" + String(lightPercent);
  url += "&system_status=" + status;
  url += "&output_status=" + buzzerStatus;

  if (httpGET(url, res)) {
    Serial.println("Upload OK");
  } else {
    Serial.println("Upload failed");
  }
}

// ================= OLED DISPLAY PAGES =================
void showOLED() {
  if (!oledOK) return;

  display.clearDisplay();
  display.setTextColor(SSD1306_WHITE);
  display.setCursor(0, 0);
  display.setTextSize(1);

  if (warmingUp) {
    int secondsLeft = (WARMUP_MS - (millis() - warmupStart)) / 1000 + 1;
    if (secondsLeft < 0) secondsLeft = 0;

    display.println("Smart Home");
    display.println("----------------");
    display.println("Warming up...");
    display.println("MQ-2 sensor needs");
    display.println("time to stabilize.");
    display.setTextSize(2);
    display.println(String(secondsLeft) + "s");
    display.display();
    return;
  }

  if (page == 1) {
    display.println("Page 1: Overview");
    display.println("----------------");
    display.println(WiFi.status() == WL_CONNECTED ? "WiFi: OK" : "WiFi: NO");
    display.println("T:" + String(temp, 1) + "C H:" + String(hum, 0) + "%");
    display.println("Light: " + lightStatus);
    display.println("MQ: " + mqStatus);
    display.println("Status: " + status);
    display.println("Buzz: " + buzzerStatus + " (" + outputMode + ")");
  }

  else if (page == 2) {
  display.println("Page 2: Temp/Hum");
  display.println("----------------");

  display.setTextSize(2);
  display.println("T:" + String(temp, 1) + "C");
  display.println("H:" + String(hum, 0) + "%");

  display.setTextSize(1);
  display.println("----------------");
  display.println("Temp Limit: " + String(tempLimit, 1) + "C");

  if (!isnan(temp) && temp >= tempLimit) {
    display.println("Status: HIGH TEMP");
  } else {
    display.println("Status: NORMAL");
  }
}

  else if (page == 3) {
    display.println("Page 3: Light");
    display.println("----------------");
    display.println("Light: " + String(lightPercent) + "%");
    display.println("Cond: " + lightStatus);
    display.println(lightStatus == "DARK" ? "Turn on light." : "Lighting OK.");
  }

  else if (page == 4) {
    display.println("Page 4: MQ Sensor");
    display.println("----------------");
    display.println("MQ: " + String(mq));
    display.println("Status: " + mqStatus);
    display.println(mqStatus == "GAS" ? "Gas/smoke!" : "Air normal");
    display.println("Buzz: " + buzzerStatus + " (" + outputMode + ")");
  }

  display.display();
}

// ================= SETUP =================
void setup() {
  Serial.begin(115200);

  pinMode(MQ_PIN, INPUT);
  pinMode(LDR_PIN, INPUT);
  pinMode(BTN_PIN, INPUT_PULLUP);
  pinMode(BUZZER_PIN, OUTPUT);

  dht.begin();
  setupOLED();
  setupWiFi();
  fetchSettings();

  readSensors();
  showOLED();
}

// ================= MAIN LOOP =================
void loop() {
  unsigned long now = millis();

  handleButton();

  if (WiFi.status() != WL_CONNECTED) {
    connectWiFi();
  }

  if (warmingUp && now - warmupStart >= WARMUP_MS) {
    warmingUp = false;
    tUpload = now;  // start the upload cycle fresh once warm-up ends
  }

  // Read sensors every 1 second
  if (now - tRead >= 1000) {
    tRead = now;
    readSensors();
  }

  // Fetch dashboard settings every 5 seconds
  if (now - tSettings >= 5000) {
    tSettings = now;
    fetchSettings();
  }

  // Update OLED every 0.3 seconds
  if (now - tOLED >= 300) {
    tOLED = now;
    showOLED();
  }

  if (warmingUp) return;  // hold uploads/buzzer back until the sensor stabilizes

  updateBuzzer();

  // Upload data based on dashboard upload interval
  if (now - tUpload >= (unsigned long)uploadSec * 1000UL) {
    tUpload = now;
    uploadData();
  }
}
