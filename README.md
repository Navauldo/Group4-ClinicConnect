# Group4-ClinicConnect

# ClinicConnect - Medical Appointment System

## Setup Instructions

### Prerequisites
- XAMPP (Apache, MySQL, PHP)
- Modern web browser

### Installation
1. Clone this repository to your htdocs folder
2. Import `database/schema.sql` to phpMyAdmin
3. Copy `config.example.php` to `config.php` and update database credentials
4. Access via: http://localhost/Group4-ClinicConnect


## Database Setup

1. **Import Database:**
   - Start XAMPP and open phpMyAdmin (http://localhost/phpmyadmin)
   - Create new database: `clinic_connect`
   - Click the "Import" tab
   - Click "Choose File" and select: `clinic_connect.sql`
   - Click "Go" to import

2. **Verify Import:**
   - You should see tables like: appointments, clinic hours, clinics, etc.
   - Check that all tables were created successfully


Installation

    Clone this repository to your htdocs folder:
    bash

git clone https://github.com/Navauldo/ClinicConnect.git Group4-ClinicConnect

    Update database credentials if needed (default should work: root/no password)

Access Application:
http://localhost/Group4-ClinicConnect/index.php


### Current Features
- ✅ Book Appointments (FR-001) - Navauldo
- ✅ Reschedule Appointments (FR-002) - Navauldo
- ✅ Staff Dashboard (FR-003) - Navauldo
- ✅ Cancel Appointments (FR-005) Navauldo
- ✅ Clinic Schedule Management (FR-008) - Navauldo


### In Progress
- 🔴 Send Reminders (FR-006) - Navauldo 
- 🔴 Manage Patient Contact Info (FR-004) - [Team Member]
- 🔴 View Appointment History (FR-007) - [Team Member]
- 🔴 Export Data (FR-009) - Adrienne Jobs
