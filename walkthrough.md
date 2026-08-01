# Campus Complaint & Maintenance Management System Walkthrough

The codebase has been refactored into a **Modular Frontend & Backend Architecture**. All duplicate and redundant files at the root level have been cleaned up.

---

## 📂 Project Architecture & Directory Structure

```
Campus Complaint Management System/
├── index.php                            # System Router / Redirector
├── walkthrough.md                       # Architecture & setup guide
│
├── frontend/                            # 🎨 FRONTEND LAYER
│   ├── index.php                        # Portal Router
│   ├── login.php                        # User Authentication Form
│   ├── register.php                     # Student Registration Form
│   ├── logout.php                       # Session Destruction & Logout
│   ├── includes/                        # Layout Partials
│   │   ├── header.php                   # Sidebar, Topbar & Meta Tags
│   │   └── footer.php                   # Page Footer & Script Injection
│   ├── assets/                          # Static Frontend Assets
│   │   ├── css/style.css                # Custom CSS Design System
│   │   └── js/main.js                   # UI Bindings, Modals, Toast & Fetch Wrapper
│   ├── student/                         # Student Portal Views
│   │   ├── dashboard.php
│   │   ├── submit_complaint.php
│   │   ├── my_complaints.php
│   │   ├── edit_complaint.php
│   │   ├── view_complaint.php
│   │   └── feedback.php
│   ├── staff/                           # Technician / Maintenance Staff Portal Views
│   │   ├── dashboard.php
│   │   ├── assigned_tasks.php
│   │   ├── update_progress.php
│   │   └── completed_tasks.php
│   └── admin/                           # Administrator Portal Views
│       ├── dashboard.php
│       ├── complaints.php
│       ├── manage_students.php
│       ├── manage_staff.php
│       ├── manage_categories.php
│       ├── manage_locations.php
│       └── reports.php
│
└── backend/                             # ⚙️ BACKEND LAYER
    ├── setup.php                        # System Setup & Database Initializer Script
    ├── campus_complaint_system.sql      # Database Schema Export
    ├── config/
    │   └── db.php                       # Database Connection (PDO) & URL Constants
    ├── includes/
    │   ├── auth.php                     # Session Guard & Role Authorization
    │   ├── functions.php                # Utility Functions, Badges, File Uploads & Pagination
    │   └── mark_notification.php        # AJAX Notification Reader
    ├── student/ajax/
    │   └── complaint_actions.php        # Delete/Cancel Student Complaints
    ├── staff/ajax/
    │   └── task_actions.php             # Update Job Status & Submit Resolution
    ├── admin/ajax/
    │   ├── assign_complaint.php         # Dispatch Technician to Ticket
    │   ├── category_crud.php            # Add/Edit Categories
    │   ├── complaint_actions.php        # Change Complaint Status/Priority
    │   ├── dashboard_stats.php          # Dynamic Chart Data
    │   ├── location_crud.php            # Add/Edit Buildings
    │   ├── staff_crud.php               # Technician Management API
    │   └── student_crud.php             # Student Management API
    └── uploads/                         # File Upload Storage
        ├── complaints/                  # Issue Photos uploaded by Students
        └── repairs/                     # Resolution Photos uploaded by Technicians
```

---

## ⚡ Hosting & Running Locally (XAMPP)

1. **Directory Placement**: Place project folder under `C:\xampp\htdocs\ccms\`.
2. **Database Setup**:
   - Open `http://localhost/phpmyadmin/`
   - Create database `campus_complaint_system`
   - Import `backend/campus_complaint_system.sql`
3. **Initialize Setup**:
   - Access `http://localhost/ccms/backend/setup.php` to set up initial admin credentials (`admin@campus.edu` / `admin123`) and verify upload directories.
4. **Access System**:
   - Open `http://localhost/ccms/` to automatically be routed to the `frontend/` portal.
