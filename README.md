# Student Companion System

A Final Year Project — multi-role academic management system built with **PHP + MySQL**, featuring attendance tracking, assignment management, notifications, leave requests, analytics, and reports.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.x (plain PHP, no framework) |
| Database | MySQL (PDO, prepared statements) |
| Frontend | Bootstrap 5.3 (CDN), Bootstrap Icons, Chart.js |
| Libraries | PHPMailer, DomPDF, PhpSpreadsheet (via Composer) |

---

## System Roles

```
HOD (Admin)
    ├── Mentor
    └── Learner (Student)
```

- **HOD** — full control: manage students & mentors, analytics, notifications, reports, audit log
- **Mentor** — mark attendance, create/evaluate assignments, upload study materials, approve student leave
- **Learner** — submit assignments, view attendance, download materials, request leave

---

## Project Structure

```
student-companion/
├── admin/                  # HOD pages
│   ├── dashboard.php
│   ├── students.php
│   ├── mentors.php
│   ├── notifications.php
│   ├── leave.php
│   ├── analytics.php
│   ├── reports.php
│   └── audit_log.php
│
├── mentor/                 # Mentor pages
│   ├── dashboard.php
│   ├── attendance.php
│   ├── assignments.php
│   ├── materials.php
│   └── leave.php
│
├── learner/                # Student pages
│   ├── dashboard.php
│   ├── assignments.php
│   ├── attendance.php
│   ├── materials.php
│   ├── notifications.php
│   └── leave.php
│
├── assets/
│   ├── css/style.css
│   └── js/
│       ├── theme.js          # Dark/light mode (runs before body renders)
│       ├── app.js            # Tooltips, CSRF helper, confirm dialogs
│       └── notifications.js  # AJAX polling every 15s
│
├── config/
│   ├── config.php            # App constants (DB, SMTP, upload limits)
│   └── database.php          # PDO singleton
│
├── includes/
│   ├── auth_check.php        # Session guard + role check (included on every protected page)
│   ├── header.php
│   ├── navbar.php
│   ├── sidebar.php           # Role-aware navigation links
│   ├── footer.php
│   ├── helpers.php           # CSRF, flash, file upload, pagination, audit log
│   └── notification_poll.php # AJAX endpoint — returns unread notification count
│
├── uploads/
│   ├── notes/                # Study material files
│   ├── assignments/          # Assignment files + student submissions
│   └── profiles/             # Profile pictures
│
├── exports/                  # Generated Excel/PDF reports
├── vendor/                   # Composer dependencies
│
├── index.php                 # Redirect dispatcher (role-based)
├── login.php
├── logout.php
├── forgot_password.php
├── reset_password.php
├── change_password.php
├── download.php              # Access-controlled file download proxy
├── schema.sql                # Full database schema + seed data
└── composer.json
```

---

## Database Schema

14 tables:

| Table | Purpose |
|---|---|
| `users` | All roles (admin / mentor / learner) |
| `departments` | Department list |
| `subjects` | Subjects linked to departments/year/semester |
| `mentor_subjects` | Which subjects a mentor teaches |
| `mentor_students` | Mentor → student class-advisor mapping |
| `attendance` | Per student/subject/date attendance records |
| `assignments` | Assignments created by mentors |
| `assignment_submissions` | Student file submissions |
| `study_materials` | Notes/videos uploaded by mentors |
| `notifications` | System-wide or targeted announcements |
| `notification_reads` | Per-user read tracking |
| `leave_requests` | Student → mentor or mentor → HOD routing |
| `password_resets` | Time-limited tokens for forgot-password flow |
| `audit_logs` | Append-only action log |

---

## Local Setup (XAMPP / WAMP)

### 1. Copy the project
```
C:\xampp\htdocs\student-companion\
```

### 2. Create the database
Open **phpMyAdmin** → SQL tab:
```sql
CREATE DATABASE student_companion CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```
Then import `schema.sql` via the **Import** tab.

### 3. Install Composer dependencies
```bash
cd C:\xampp\htdocs\student-companion
composer install
```

### 4. Configure the app
Edit `config/config.php`:
```php
define('BASE_URL', 'http://localhost/student-companion/');
define('DB_USER',  'root');
define('DB_PASS',  '');          // XAMPP default is empty

// SMTP — use Mailtrap for local testing
define('SMTP_HOST', 'smtp.mailtrap.io');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your_mailtrap_user');
define('SMTP_PASS', 'your_mailtrap_pass');
```

### 5. Open in browser
```
http://localhost/student-companion/
```

---

## Default Login

| Role | Email | Password |
|---|---|---|
| HOD (Admin) | `admin@school.edu` | `Admin@123` |

> Mentor and student accounts are created by the HOD after first login. Auto-generated passwords are shown on creation and must be changed on first login (`force_password_reset = 1`).

---

## Features

### HOD (Admin)
- Add/edit/deactivate students and mentors
- Assign mentors to subjects and students
- Create targeted notifications (all users / department / mentor's students)
- Approve or reject mentor leave requests
- Live analytics dashboard (attendance %, submission rate, low-attendance alerts)
- Export attendance and assignment reports as **Excel (.xlsx)** or **PDF**
- View full audit log of all system actions

### Mentor
- Mark daily attendance per subject (present / absent, with duplicate prevention)
- Create assignments with file attachments and auto-notify students
- Upload study materials (PDF, PPT, images, video)
- Evaluate student submissions with marks and feedback
- Approve or reject student leave requests
- Submit own leave request to HOD

### Learner
- Submit assignments (auto-detected as late if past deadline)
- View subject-wise attendance with colour-coded % (green ≥75%, yellow ≥50%, red <50%)
- Download study materials
- View and read notifications (unread badge, AJAX polling)
- Apply for leave (routed to assigned mentor)

### System-wide
- Dark / light mode toggle (localStorage, no flash on load)
- Responsive layout — offcanvas sidebar on mobile (Bootstrap 5)
- Real-time notification badge via AJAX polling (every 15 seconds)
- Forgot password flow with email reset link (1-hour token)
- Session timeout after 30 minutes of inactivity

---

## Security

| Measure | Implementation |
|---|---|
| Password hashing | `password_hash()` / `password_verify()` — never plaintext |
| SQL injection prevention | PDO prepared statements everywhere, `ATTR_EMULATE_PREPARES = false` |
| Per-page role guard | `auth_check.php` required at top of every protected file |
| CSRF protection | `$_SESSION['csrf_token']` validated with `hash_equals()` on every POST |
| File upload validation | Extension whitelist + server-side MIME check via `finfo` + 10 MB cap + random filename |
| Path traversal prevention | `download.php` rejects `..`, `/`, `\` in file parameters |
| Direct file access blocked | `.htaccess` in all upload dirs: `php_flag engine off`, `deny from all` |
| Session fixation prevention | `session_regenerate_id(true)` on every login |
| Session timeout | 30-minute inactivity check on every protected page |
| User enumeration prevention | Forgot password and login show generic error messages |

---

## SMTP / Email Setup

For local development, use **Mailtrap** (free fake inbox):
1. Sign up at [mailtrap.io](https://mailtrap.io)
2. Copy SMTP credentials into `config/config.php`

For production, use Gmail with an **App Password**:
```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your@gmail.com');
define('SMTP_PASS', 'your_app_password');
```

---

## Build Order (for reference)

The system was built in this order so each module depends only on completed previous modules:

1. Database schema + PDO config
2. Auth layer (login, logout, forgot/reset password, session guard)
3. Shared includes (header, navbar, sidebar, footer, helpers)
4. Assets (CSS, JS — theme, notifications, app)
5. HOD module (8 pages)
6. Mentor module (5 pages)
7. Learner module (6 pages)
8. Root utilities (index dispatcher, download proxy)
9. Security guards (.htaccess on all upload dirs)

---

## Composer Dependencies

```json
{
  "phpmailer/phpmailer": "^6.9",
  "dompdf/dompdf": "^2.0",
  "phpoffice/phpspreadsheet": "^2.1"
}
```

These are only loaded in the files that actually use them — not on every page.
