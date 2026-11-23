# College Equipment Maintenance Management System
## QR-Based & Geo-Verified Physical Presence Verification Application

A production-structured, end-to-end college equipment maintenance management platform that enforces **physical presence** of service agents before granting access to maintenance inspection workflows.

---

## 1. Project Overview

In educational institutions with extensive IT infrastructure (such as computer labs with multiple desktop workstations), it is essential to ensure technicians physically inspect systems on-site rather than logging reports remotely.

Each piece of equipment is physically fitted with a tamper-proof QR code containing:
- **System ID** (e.g., `SYS-001`)
- **Registered Latitude & Longitude**
- **Permitted Radius** (Default: `10 metres`)
- **QR Version Number**
- **Server-Generated HMAC-SHA256 Digital Signature**

### Inspection Workflow:
1. **Agent Authentication**: Service agent logs in to the Flutter mobile application (`AGT-001`).
2. **Dynamic Dashboard**: Displays agent name, active inspection counts, and dynamic local device date.
3. **QR Scanning**: Agent scans the physical QR tag attached to the desktop with `mobile_scanner`.
4. **GPS Geo-Fencing (Haversine Formula)**: App retrieves real-time satellite GPS coordinates and verifies distance against registered equipment coordinates.
   - **If distance $\le 10\text{m}$**: Location verified. Access to checklist granted.
   - **If distance $> 10\text{m}$**: Access is blocked and the screen displays **ONLY**: `"Not in correct location"`. No coordinates or distance are leaked.
   - **If accuracy is poor ($> 25\text{m}$)**: Agent is prompted to retry for a reliable satellite lock without false location failure.
5. **Interactive Checklist**: 7 baseline desktop inspection questions (Switching ON, Monitor, Keyboard, Mouse, Internet, OS, Applications). Selecting `NO` enforces a mandatory issue description.
6. **Dual Verification & Submission**: Saves the complete report, individual answers, and GPS evidence in MySQL using atomic PDO transactions.
7. **College Admin Dashboard**: Real-time overview of equipment health, agent activities, interactive QR code generation/printing, and report audit logs.

---

## 2. Technology Stack

- **Mobile Application**: Flutter 3.x, Dart 3.x (Android, Material 3)
  - Packages: `mobile_scanner`, `geolocator`, `http`, `shared_preferences`, `intl`
- **Backend**: Native PHP 8.x (REST API, PDO prepared statements, JSON communication)
- **Database**: MySQL 8.x / MariaDB (`college_maintenance_db`)
- **Admin Dashboard**: HTML5, CSS3, JavaScript (Vanilla ES6), PHP (Responsive design, SVG QR code rendering)

---

## 3. Directory Structure

```
qr_manage_system/
├── college_maintenance_backend/            # PHP Backend & Admin Web Interface
│   ├── api/
│   │   ├── auth/
│   │   │   ├── agent-login.php             # Agent authentication & token issuance
│   │   │   ├── admin-login.php             # Admin authentication & token issuance
│   │   │   └── logout.php                  # Invalidate API tokens
│   │   ├── systems/
│   │   │   ├── list.php                    # List equipment with active QR versions
│   │   │   ├── create.php                  # Register system & generate initial QR
│   │   │   ├── update.php                  # Update details & invalidate/renew QR
│   │   │   └── details.php                 # Details & equipment history
│   │   ├── qr/
│   │   │   ├── generate.php                # Generate HMAC-SHA256 QR payload
│   │   │   └── verify.php                  # Server-side validation of scanned QR
│   │   ├── agents/
│   │   │   ├── list.php                    # List service agents (Admin only)
│   │   │   ├── create.php                  # Register service agent
│   │   │   └── update.php                  # Activate/deactivate agent accounts
│   │   └── maintenance/
│   │       ├── checklist-questions.php     # Fetch equipment inspection questions
│   │       ├── verify-location.php         # Server pre-check of GPS proximity
│   │       ├── submit-report.php           # Submit report & dual-verify presence
│   │       ├── agent-reports.php           # Agent's personal report history
│   │       ├── report-details.php          # Detailed report view with answers
│   │       └── all-reports.php             # Filterable admin report logs
│   ├── admin/
│   │   ├── login.php                       # Admin login page
│   │   ├── dashboard.php                   # Real-time statistics & overview
│   │   ├── systems.php                     # Equipment inventory & QR manager
│   │   ├── agents.php                      # Agent account management
│   │   ├── reports.php                     # Filterable report inspection viewer
│   │   ├── qr_print.php                    # Printable equipment QR stickers
│   │   ├── auth_check.php                  # Session guard
│   │   └── layout_nav.php                  # Admin sidebar component
│   ├── config/
│   │   ├── constants.php                   # Business rules, HMAC secrets, constants
│   │   ├── database.php                    # PDO Singleton connection manager
│   │   └── helpers.php                     # Cryptographic & Haversine utilities
│   ├── assets/
│   │   ├── css/style.css                   # Modern dashboard theme stylesheet
│   │   └── js/app.js                       # Modals, Geolocation capture, QR engine
│   ├── database/
│   │   └── schema.sql                      # Complete MySQL schema & seed data
│   └── test_api_logic.php                  # Verification test suite
│
└── college_maintenance_app/                # Flutter Mobile Application
    ├── pubspec.yaml                        # Flutter package configuration
    ├── android/app/src/main/
    │   └── AndroidManifest.xml             # Camera, Fine/Coarse GPS permissions
    ├── lib/
    │   ├── main.dart                       # App entry point & session router
    │   ├── models/
    │   │   ├── agent_model.dart            # Agent profile data model
    │   │   ├── system_model.dart           # Equipment model
    │   │   ├── qr_payload_model.dart       # Scanned QR JSON parser & validator
    │   │   ├── checklist_question_model.dart# Questions, YES/NO answers & issues
    │   │   └── maintenance_report_model.dart# Report history model
    │   ├── services/
    │   │   ├── api_service.dart            # REST client with auth headers
    │   │   ├── location_service.dart       # High-accuracy GPS with accuracy guards
    │   │   └── storage_service.dart        # SharedPreferences session manager
    │   ├── utils/
    │   │   ├── constants.dart              # App constants & server config
    │   │   ├── haversine.dart              # Haversine distance calculator
    │   │   └── app_theme.dart              # Material 3 theme definitions
    │   └── screens/
    │       ├── login_screen.dart           # Agent login & server URL config
    │       ├── agent_dashboard_screen.dart # Dynamic date, counters & scan launcher
    │       ├── qr_scanner_screen.dart      # Camera scanning & debounce logic
    │       ├── location_verification_screen.dart # Strict 10m geo-fence check
    │       ├── checklist_screen.dart       # 7 Yes/No questions & issue prompts
    │       ├── report_summary_screen.dart  # Submission confirmation screen
    │       ├── my_reports_screen.dart      # Personal inspection history
    │       └── report_detail_screen.dart   # Complete audit inspection viewer
    └── test/
        └── unit_test.dart                  # Automated unit test suite
```

---

## 4. Setup & Installation Instructions

### 4.1 Database Setup (MySQL / XAMPP)
1. Open XAMPP Control Panel and start **Apache** and **MySQL**.
2. Open terminal or phpMyAdmin (`http://localhost/phpmyadmin`).
3. Import the database schema:
   ```bash
   mysql -u root -p < college_maintenance_backend/database/schema.sql
   ```
   *(If root has no password in default XAMPP, omit `-p`)*.
4. Verify the database `college_maintenance_db` was created with 10 seed systems (`SYS-001` through `SYS-010`), default admin, default agent, and 7 checklist questions.

### 4.2 Backend & Admin Dashboard Execution
You can run the PHP backend with Apache or the built-in PHP development server:
```bash
cd college_maintenance_backend
php -S 0.0.0.0:8000
```
- **Admin Dashboard**: `http://localhost:8000/admin/login.php`
- **Default Admin Credentials**:
  - Email: `admin@college.edu`
  - Password: `admin123`
- **Default Agent Credentials**:
  - Agent ID / Email: `AGT-001` or `agent@college.edu`
  - Password: `agent123`

### 4.3 Flutter Mobile Application Setup
1. Navigate to the mobile app directory:
   ```bash
   cd college_maintenance_app
   ```
2. Fetch dependencies:
   ```bash
   flutter pub get
   ```
3. Run automated tests to verify Haversine mathematics and QR payload parsing:
   ```bash
   dart test/unit_test.dart
   ```
4. Run the app on an Android emulator or connected device:
   ```bash
   flutter run
   ```

### 4.4 Connecting Mobile App to Backend
- **Android Emulator**: In the app login screen, tap the **Settings** icon in the top right and set URL to `http://10.0.2.2:8000`.
- **Physical Android Phone**: Ensure phone and development PC are on the same Wi-Fi network. Find your PC's IP (`ipconfig`) and set URL to `http://192.168.1.X:8000`.

---

## 5. Security & Verification Rules Reference

| Rule | Implementation Details |
| :--- | :--- |
| **HMAC-SHA256 QR Signatures** | Signatures are generated using `hash_hmac('sha256', "sys_id\|lat\|lon\|radius\|version", SECRET)` on the PHP server. The signing secret is never stored on the mobile app. |
| **10-Metre Strict Radius** | Haversine formula compares device GPS position with registered coordinates. Access to checklist is blocked if distance $> 10\text{m}$. |
| **Privacy Rule on Failure** | When distance $> 10\text{m}$, the mobile app displays **ONLY** `"Not in correct location"`. Coordinates and distances are not exposed. |
| **Accuracy Protection** | GPS readings with accuracy $> 25\text{m}$ prompt for retry instead of generating false location rejections. |
| **Server-Side Dual Check** | The backend independently verifies coordinates, calculates distance, checks accuracy, validates signature, and enforces mandatory issue descriptions before saving reports. |
| **Password Security** | Passwords hashed using standard `password_hash()` (Bcrypt) and checked with `password_verify()`. |
| **Report Immutability** | Reports, individual answers, and GPS evidence are committed within a single atomic SQL transaction. |
