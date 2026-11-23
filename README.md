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
6. **Dual Verification & Submission**: Saves the complete report, individual answers, and GPS evidence in MySQL using atomic database transactions.
7. **College Admin Dashboard**: Real-time overview of equipment health, agent activities, interactive QR code generation/printing, and report audit logs.

---

## 2. Technology Stack

- **Backend**: Node.js & Express.js (REST API, Connection Pooling, JSON communication)
  - Packages: `express`, `mysql2`, `bcryptjs`, `cors`, `dotenv`
- **Database**: MySQL 8.x / MariaDB (`college_maintenance_db`)
- **Mobile Application**: Flutter 3.x, Dart 3.x (Android, Material 3)
  - Packages: `mobile_scanner`, `geolocator`, `http`, `shared_preferences`, `intl`
- **Admin Dashboard**: Modern Responsive Web Dashboard (HTML5, CSS3, JavaScript ES6) served directly by Express.js.

---

## 3. Directory Structure

```
qr_manage_system/
├── college_maintenance_backend/            # Node.js & Express.js Backend
│   ├── package.json                        # Express, mysql2, bcryptjs, cors, dotenv
│   ├── .env                                # Database & server port configuration
│   ├── server.js                           # Express entry point & static server
│   ├── config/
│   │   ├── constants.js                    # Business rules, HMAC secrets, constants
│   │   └── db.js                           # mysql2/promise connection pool
│   ├── utils/
│   │   ├── cryptoHelper.js                 # HMAC-SHA256 signature generator & timing-safe verifier
│   │   └── haversine.js                    # Exact spherical trigonometric distance formula
│   ├── middleware/
│   │   └── auth.js                         # Bearer token validation for Agents & Admin
│   ├── routes/
│   │   ├── authRoutes.js                   # Agent/Admin login and session management
│   │   ├── systemRoutes.js                 # Equipment CRUD & QR versioning
│   │   ├── qrRoutes.js                     # HMAC-SHA256 QR generation & validation
│   │   ├── agentRoutes.js                  # Service agent accounts administration
│   │   └── maintenanceRoutes.js            # Geo-verification & transactional reports
│   ├── public/                             # Admin Web Dashboard (Served by Express)
│   │   ├── login.html                      # Admin portal login
│   │   ├── dashboard.html                  # Real-time statistics & overview
│   │   ├── systems.html                    # Equipment inventory & QR manager
│   │   ├── agents.html                     # Technician account administration
│   │   ├── reports.html                    # Filterable maintenance reports audit logs
│   │   ├── qr_print.html                   # High-resolution printable QR stickers
│   │   └── js/admin_common.js              # Token management & responsive sidebar
│   ├── assets/
│   │   ├── css/style.css                   # Modern college equipment theme
│   │   └── js/app.js                       # Browser GPS capture & QR rendering
│   ├── database/
│   │   └── schema.sql                      # Complete MySQL schema & seed data
│   └── test_api_logic.js                   # Automated Node.js verification test suite
│
└── college_maintenance_app/                # Flutter Mobile Application
    ├── pubspec.yaml                        # Flutter package configuration
    ├── android/app/src/main/
    │   └── AndroidManifest.xml             # Camera, Fine/Coarse GPS permissions
    ├── lib/
    │   ├── main.dart                       # App entry point & session router
    │   ├── models/                         # Data models (agent, system, qr, checklist, report)
    │   ├── services/                       # API client, GPS service, SharedPreferences
    │   ├── utils/                          # Haversine calculator, App theme, Constants
    │   └── screens/                        # Login, Dashboard, Scanner, Geo-Verification, Checklist, Reports
    └── test/
        └── unit_test.dart                  # Automated unit test suite
```

---

## 4. Setup & Running Instructions

### 4.1 Database Setup (MySQL / XAMPP)
1. Open XAMPP Control Panel and start **MySQL** (port 3306).
2. Open terminal and import the schema:
   ```bash
   cd C:\Users\acer\OneDrive\Documents\qr_manage_system
   & "C:\xampp\mysql\bin\mysql.exe" -u root < college_maintenance_backend/database/schema.sql
   ```
   *(Or open phpMyAdmin at `http://localhost:8080/phpmyadmin/` -> create database `college_maintenance_db` -> Import `schema.sql`).*

### 4.2 Start the Express.js Backend Server
In a terminal, run:
```bash
cd C:\Users\acer\OneDrive\Documents\qr_manage_system\college_maintenance_backend
npm start
```
*(Runs on `http://localhost:8000`)*.

- **Admin Web Dashboard**: Open your browser at:
  👉 **http://localhost:8000/admin**
  - **Email:** `admin@college.edu`
  - **Password:** `admin123`

### 4.3 Run the Flutter Mobile App
In another terminal:
```bash
cd C:\Users\acer\OneDrive\Documents\qr_manage_system\college_maintenance_app
flutter run
```
- **Default Agent Credentials:**
  - **Agent ID:** `AGT-001`
  - **Password:** `agent123`
- **Server Address:**
  - On Android Emulator: `http://10.0.2.2:8000`
  - On Physical Device: `http://<YOUR_PC_IP>:8000` *(configured via Settings icon on login screen)*.
