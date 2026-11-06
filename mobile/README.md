# DoodhDesk Mobile App (Flutter for iOS & Android)

Cross-platform mobile application for **DoodhDesk** with **Offline-First Sync Engine**, designed for early morning milk deliveries and dairy operations.

---

## 📱 Features

1. **Offline-First Delivery Run-Sheet**:
   - Zero-latency field logging (0ms tap response).
   - High sunlight contrast with large 48px thumb tap targets.
   - 1-Tap **Delivered (✓)**, **Skipped (✗)** (strictly enforces 0.00L Zero-Charge anti-dispute rule), and **Volume Adjustment (✏️)**.
   - Works 100% offline in basement parking and cellular dead zones.
   - Automatically synchronizes queued changes to cloud via FIFO Outbox Queue once internet reconnects.
2. **Customer Roster & Milk Subscriptions**:
   - Manage multi-product milk allocations (Cow Milk, Buffalo Milk).
   - Schedule customer vacation pauses (preventing disputed billing).
   - Real-time SaaS Quota capacity monitoring.
3. **Billing, WhatsApp & Instant Payments**:
   - Monthly invoice generation.
   - 1-Tap WhatsApp bill share with pre-filled payment message and public bill URL.
   - On-screen **Dynamic UPI QR Code** (customers scan driver's screen with Google Pay / PhonePe / Paytm).
   - Record cash collections on the spot.
4. **Hardware Integrations**:
   - Native ESC/POS Bluetooth thermal printer support for 58mm/80mm receipt printing.
   - Phone dialer intent (`tel:...`).

---

## 🏗️ Architecture

```
mobile/
├── lib/
│   ├── main.dart                      # Flutter app bootstrap
│   ├── core/
│   │   ├── api/
│   │   │   ├── api_client.dart        # Dio HTTP client with Bearer Token interceptor
│   │   │   └── auth_service.dart      # Secure storage credentials manager
│   │   ├── constants/
│   │   │   └── api_constants.dart     # Endpoint URLs
│   │   ├── database/
│   │   │   └── database_helper.dart   # Offline SQLite local database
│   │   ├── sync/
│   │   │   └── sync_engine.dart       # Outbox queue worker & connectivity observer
│   │   └── theme/
│   │       └── app_theme.dart         # Sunlight high-contrast styling
│   ├── models/
│   │   ├── customer.dart              # Customer & subscription models
│   │   ├── delivery_item.dart         # Daily run-sheet item
│   │   └── invoice.dart               # Monthly invoice & breakdown
│   └── screens/
│       ├── splash_screen.dart         # Auto-login & pre-fetch
│       ├── login_screen.dart          # Dairy sign-in with server URL config
│       ├── home_screen.dart           # 4-Tab navigation shell
│       ├── delivery_screen.dart       # Core Run-Sheet screen
│       ├── customer_list_screen.dart  # Customer roster & quota bar
│       ├── customer_form_screen.dart  # Customer creator & subscription pricing
│       ├── customer_leave_screen.dart # Vacation pause scheduler
│       ├── invoice_list_screen.dart   # Invoices list & batch billing
│       ├── invoice_detail_screen.dart # WhatsApp share & Dynamic UPI QR
│       └── dashboard_screen.dart      # Operational KPIs & settings
├── android/                           # Android native configuration & manifests
├── ios/                               # iOS native configuration & Info.plist
└── pubspec.yaml                       # Dependencies
```

---

## 🚀 How to Run

### 1. Prerequisites
- Install [Flutter SDK (v3.24+)](https://docs.flutter.dev/get-started/install)
- Android Studio (for Android Emulator / Device)
- Xcode (on macOS for iOS Simulator / iPhone)

### 2. Configure Backend Endpoint
By default:
- **Android Emulator**: connects to `http://10.0.2.2/doodhdesk/index.php/api`
- **iOS Simulator**: connects to `http://localhost/doodhdesk/index.php/api`
- **Physical Phone (over Wi-Fi)**: enter your PC's local LAN IP (e.g. `http://192.168.1.5/doodhdesk/index.php/api`) on the login screen.

### 3. Install Packages & Run
```bash
cd mobile

# Fetch dependencies
flutter pub get

# Run on connected device or simulator
flutter run
```

### 4. Build Release Artifacts
```bash
# Build Android APK
flutter build apk --release

# Build Android App Bundle (for Google Play Store)
flutter build appbundle --release

# Build iOS Archive (for Apple App Store / TestFlight)
flutter build ipa --release
```

---

## ⚡ How Offline Mode Works in the Field

1. **At the Dairy (4:30 AM)**: The app downloads today's run-sheet to local SQLite.
2. **On the Road (5:00 AM - 7:00 AM)**:
   - When cell service is lost, an amber badge appears: *"⚡ Offline Mode Active"*.
   - Delivery boy taps **"✓ Delivered"** or adjusts milk to **2.5L**.
   - Changes are saved immediately to local SQLite (0ms lag).
   - An entry with a UUID idempotency key is queued in `sync_outbox`.
3. **Connectivity Restored (7:30 AM)**:
   - The app detects internet and automatically flushes the outbox to `POST /api/deliveries/toggle`.
   - Cloud MySQL database is updated; customer invoices reflect accurate field deliveries with zero billing disputes.
