# 企業內部員工差勤管理系統
**Employee Attendance & Leave Management System**

A full-stack internal HR system built during an internship at 大愛電視台 數位發展中心.
Integrates a Laravel web application, a Line Bot, and a Python desktop monitor through MQTT messaging.

---

## System Overview

The system consists of three interconnected sub-systems:

| Sub-system | Tech Stack | Description |
|---|---|---|
| **Web System** | PHP 8.3 + Laravel 13 + SQLite + Blade | Core application for all HR operations |
| **Line Bot Server** | Node.js + Express + @line/bot-sdk | Mobile access via Line for employees and managers |
| **Desktop Monitor** | Python 3.12 + tkinter + paho-mqtt | Real-time attendance dashboard for HR/admin |

All three sub-systems communicate through a **Mosquitto MQTT Broker (localhost:1883)**.

---

## Features

### For All Employees
- Clock in / clock out with automatic late and early-leave detection
- Apply for leave (全天 / 小時制) with real-time balance display
- Record overtime and view compensatory hours balance
- Personal attendance calendar (3-month color-coded view)
- Monthly attendance statistics
- All features also accessible via **Line Bot**

### For Department Managers
- Review and approve/reject leave requests from their department
- Confirm or reject overtime records
- Set approval delegates during absences
- Receive Line push notifications for new requests; approve directly from Line

### For HR (人資部)
- Final approval for leave requests over 3 days
- Employee management (create, edit, deactivate accounts)
- Attendance management with manual clock-time correction
- Export daily attendance to Excel (5 worksheets)
- Holiday management (add/delete, with 2026 Taiwan national holidays pre-loaded)
- Department management and shift settings

### For System Admin (系統管理者)
- All HR features
- Password reset for any employee
- Role assignment (with restrictions per department)

---

## Role Hierarchy

| Role | Code | Key Permissions |
|---|---|---|
| 一般員工 | `employee` | Clock in/out, apply leave, record overtime |
| 部門主管 | `manager` | Above + approve/reject own department's requests |
| 人資部 | `hr` | Above + employee management, attendance management, holiday management |
| 系統管理者 | `admin` | Full system access + password reset + role assignment |

---

## Approval Flow
- Leave ≤ 3 days: Employee → Manager → ✅ Approved
- Leave > 3 days: Employee → Manager → HR → ✅ Approved
- Overtime: Employee → Manager → ✅ Confirmed (compensatory hours auto-added)

---

## Tech Stack

| Category | Technology |
|---|---|
| Backend Framework | PHP 8.3 + Laravel 13 |
| Template Engine | Blade (Laravel built-in) |
| Database | SQLite |
| Frontend | Bootstrap 5.3.3 + Bootstrap Icons 1.11.3 (CDN) |
| Dev Environment | WSL2 (Ubuntu) + VS Code + `php artisan serve` |
| Version Control | Git + GitHub |
| Excel Export | `maatwebsite/excel` |
| MQTT Broker | Mosquitto `localhost:1883` |
| Line Bot Server | Node.js + Express + `@line/bot-sdk` + `axios` + `mqtt` |
| Desktop Monitor | Python 3.12 + `tkinter` + `paho-mqtt` + `pystray` + `plyer` |
| Line Integration | Line Messaging API + ngrok HTTPS webhook |

---

## Database

SQLite — `leave-management.sqlite` — 7 tables:

| Table | Description |
|---|---|
| `employees` | All users, roles, line_user_id binding |
| `leave_requests` | Leave applications with approval routing |
| `overtime_records` | Overtime submissions and confirmations |
| `attendance_records` | Daily clock-in/out records with UNIQUE(employee_id, date) |
| `delegations` | Approval delegation settings |
| `holidays` | Company holidays (public / typhoon / other) |
| `shift_settings` | Per-department shift times and late tolerance |

---

## MQTT Event Topics

| Topic | Trigger |
|---|---|
| `attendance/clock-in` | Employee clocks in |
| `attendance/clock-out` | Employee clocks out |
| `leave/submitted` | Leave request submitted |
| `leave/approved` | Leave request approved |
| `leave/rejected` | Leave request rejected |
| `overtime/submitted` | Overtime record submitted |
| `overtime/confirmed` | Overtime record confirmed |
| `overtime/rejected` | Overtime record rejected |
| `delegation/set` | Approval delegate set |
| `delegation/revoked` | Approval delegate revoked |

---

## Line Bot Commands

| Command | Function |
|---|---|
| `上班` / `下班` | Clock in / clock out |
| `請假` | Start guided leave application flow |
| `加班` | Start guided overtime recording flow |
| `我的假期` | View leave balance |
| `我的請假` | View recent leave records (Flex Carousel) |
| `我的加班` | View recent overtime records |
| `待審` | View pending approvals (managers/HR) |
| `設定代理` | Set approval delegate |
| `我的代理` | View active delegations |
| `我的LineID` | Get your Line User ID for HR binding |
| `說明` | Show command list |

---

## Repository Structure
```
Leave_Management_System/
├── app/
│   ├── Enums/
│   ├── Http/Controllers/
│   ├── Models/
│   └── Services/MqttService.php
├── routes/
│   ├── web.php
│   └── api.php
├── resources/views/
├── database/
│   └── seeders/HolidaySeeder.php
└── docs/
```

> **Line Bot** is maintained in a separate branch: `linebot`
> **Desktop Monitor** is maintained in a separate branch: `attendance`

---

## Getting Started

### Prerequisites

- PHP 8.3 + Composer
- Node.js 18+
- Python 3.12 with venv
- Mosquitto MQTT broker
- WSL2 (Ubuntu) for the Laravel server

### 1. Laravel Web System

```bash
git clone https://github.com/melvian/Leave_Management_System.git
cd Leave_Management_System

composer install
cp .env.example .env
php artisan key:generate

# Database is SQLite — path is set in .env
php artisan migrate
php artisan db:seed --class=HolidaySeeder

php artisan serve
```

Access at: `http://localhost:8000`

### 2. Mosquitto MQTT Broker

```bash
# Install (Ubuntu/WSL2)
sudo apt install mosquitto mosquitto-clients

# Start broker
mosquitto -v
```

### 3. Line Bot Server

```bash
git checkout linebot
cd ~/linebot

npm install
cp .env.example .env
# Fill in LINE_CHANNEL_ACCESS_TOKEN, LINE_CHANNEL_SECRET
# MQTT_BROKER=mqtt://localhost:1883
# LARAVEL_API=http://127.0.0.1:8000/api

node index.js
```

In Windows CMD, run ngrok and set the HTTPS URL as your Line webhook:
```cmd
ngrok http 3000
```

### 4. Python Desktop Monitor

```bash
git checkout attendance
cd C:\Users\...\attendance-monitor

# Windows CMD (not WSL2)
venv\Scripts\activate
python app.py
```

---

## Documentation

All technical documents are in the `/docs` folder:

| File | Description |
|---|---|
| `01_系統技術規格書_Tech_Spec.pdf` | Full technical specification (PRD) |
| `flowcharts/登入主選單流程圖.png` | Login & main menu flow |
| `flowcharts/審核管理流程圖.png` | Approval management flow |
| `flowcharts/組織管理流程圖.png` | HR organization management flow |
| `flowcharts/打卡記錄流程圖.png` | Attendance clock-in/out flow |
| `flowcharts/全系統整合流程圖.png` | Full system integration flow (MQTT + Line + Monitor) |
| `flowcharts/系統架構圖.png` | System architecture diagram |
| `flowcharts/MVC架構圖.png` | Laravel MVC architecture diagram |

---

## Known Limitations

- System runs on local development environment only (WSL2 + Windows)
- Line Bot webhook URL changes every time ngrok restarts
- Line Bot conversation state (`userState`) is in-memory and resets on restart
- API routes have no session authentication (identity resolved via `line_user_id`)
- Python desktop monitor requires Windows (pystray does not support WSL2)

---

## Developed By

Internship project at **大愛電視台 數位發展中心 — 內部系統維運組**
Intern: Melvian 黃美媛 | Duration: 8 weeks (Summer 2026)
