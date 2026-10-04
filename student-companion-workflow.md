# Student Companion System — Complete Workflow & Technical Spec
### Stack: PHP + MySQL | Final Year Project Brief (v2 — security & schema hardened)

This is the refined version of the original workflow with all security fixes, schema corrections, and feature additions merged in. Use this as the single source of truth when prompting Claude Code — feed it section by section rather than all at once.

---

## 1. System Roles

```text
                HOD (Admin)
                     │
      ┌──────────────┴──────────────┐
      │                             │
   Mentor                      Learner
```

---

## 2. Authentication & Security (mandatory, not optional)

```text
Login Page (single page for all 3 roles)

↓

Enter Email + Password

↓

password_verify() against password_hash() in DB
(NEVER store or compare plain text passwords)

↓

Set $_SESSION['user_id'], $_SESSION['role']

↓

Redirect to Admin / Mentor / Learner Dashboard
```

Rules to enforce everywhere, not just at login:

- **Prepared statements only** (PDO or mysqli with `?` placeholders) — no raw string-concatenated SQL, anywhere.
- **Per-page role guard.** Every file inside `admin/`, `mentor/`, `learner/` must check `$_SESSION['role']` at the top of the file itself — not just rely on the login redirect. Otherwise a guessed URL like `admin/add_student.php` is directly reachable.
- **CSRF tokens** on every state-changing form: add student, mark attendance, publish notification, approve leave, evaluate assignment.
- **File upload validation** on all uploads (assignments, notes, profile pictures): whitelist extensions (`pdf`, `ppt`, `pptx`, `jpg`, `png`, `mp4`), check MIME type server-side, cap file size, rename files to a random string before saving — never trust the original filename or extension alone.
- **Session timeout** — auto-logout after a period of inactivity (e.g. 30 min), checked on every protected page.
- **Forgot password** flow — email-based reset link with a time-limited token table (`password_resets`: `id`, `user_id`, `token`, `expires_at`).

---

## 3. Database Schema (refined)

```text
users
-----------------------------------------
id, name, email (unique), password_hash,
role (enum: admin, mentor, learner),
department_id (FK), year (nullable, learners only),
status (active/inactive), created_at

departments
-----------------------------------------
id, name

subjects
-----------------------------------------
id, name, department_id (FK), year, semester

mentor_subjects
-----------------------------------------
id, mentor_id (FK users), subject_id (FK), department_id, year
-- which mentor teaches which subject, to which class

mentor_students
-----------------------------------------
id, mentor_id (FK users), student_id (FK users)
-- class-advisor mapping: which learners a mentor manages

attendance
-----------------------------------------
id, student_id (FK), subject_id (FK), date,
status (present/absent), marked_by (FK users, the mentor)

assignments
-----------------------------------------
id, mentor_id (FK), subject_id (FK), title, description,
file_path, deadline, created_at

assignment_submissions
-----------------------------------------
id, assignment_id (FK), student_id (FK), file_path,
submitted_at, marks, feedback,
status (submitted / late / evaluated)

study_materials
-----------------------------------------
id, mentor_id (FK), subject_id (FK), title, file_path,
type (pdf/ppt/image/video), uploaded_at

notifications
-----------------------------------------
id, created_by (FK users), title, message,
target_type (all / department / mentor_students),
target_id (nullable), created_at

notification_reads
-----------------------------------------
id, notification_id (FK), user_id (FK), read_at
-- one notification → many users, each with their own read state
-- (a single is_read flag on `notifications` breaks once >1 user receives it)

leave_requests
-----------------------------------------
id, requested_by (FK users), requested_to_role (mentor/admin),
reason, from_date, to_date,
status (pending/approved/rejected),
approved_by (FK users, nullable), created_at

password_resets
-----------------------------------------
id, user_id (FK), token, expires_at

audit_logs
-----------------------------------------
id, user_id (who acted), action, target_table, target_id, created_at
```

Notes on what changed from the original draft:
- `analytics_logs` removed — attendance %, submission %, department performance etc. are computed live with `GROUP BY` queries; storing them separately adds upkeep for no real benefit at this scale.
- `notification_reads` added as its own table — required for per-user read status.
- `mentor_subjects` added — the original schema let a mentor "choose subject" during attendance but never defined which subjects a mentor is actually allowed to teach.
- `audit_logs` and `password_resets` added as new tables.

---

## 4. HOD (Admin) Workflow

```text
HOD Login
↓
Dashboard
  - Total Students
  - Total Mentors
  - Today's Attendance
  - Pending Leave Requests
  - Assignments Overview
  - Department Performance
  - Recent Notifications
```

**Student Management**
```text
Add Student → Choose Department → Choose Year → Choose Mentor → Save
→ Student account created (password auto-generated, forced reset on first login)
```

**Mentor Management**
```text
Add Mentor → Assign Department → Assign Subjects → Assign Students → Save
```

**Leave Approval (mentor leave only)**
```text
Mentor submits leave request
↓
HOD reviews → Approve / Reject
↓
mentor.status / leave_requests.status updated
↓
Mentor notified
```

**Analytics Dashboard** (all computed live via SQL, not stored)
```text
Attendance %     → per department, per subject
Assignment %     → submitted vs pending
Top Mentor       → by avg attendance/evaluation turnaround
Top Department   → by combined attendance + submission rate
```

**Notifications**
```text
Create Notification → Select Target (All / Department / Specific Mentor's Students)
→ Publish → Row inserted in `notifications`
→ Each target user sees it until they read it (tracked in `notification_reads`)
```

**Reports**
```text
Export Attendance / Assignment report → Excel (PhpSpreadsheet) or PDF (TCPDF/DomPDF)
```

---

## 5. Mentor Workflow

```text
Mentor Login
↓
Dashboard
  - Today's Classes
  - Assigned Students
  - Pending Assignments to Evaluate
  - Attendance Shortcuts
  - Notifications
```

**Attendance**
```text
Choose Subject (from mentor_subjects) → Choose Class → Mark Present/Absent → Save
→ Insert/update rows in `attendance`, marked_by = mentor_id
```

**Assignment Creation**
```text
Create Assignment → Upload PDF → Set Deadline → Publish
→ Notification auto-created for the relevant students
```

**Study Materials**
```text
Upload (PDF / PPT / Images / Video) → tied to subject
→ Visible to students enrolled in that subject
```

**Assignment Evaluation**
```text
Student submission appears in queue
↓
Mentor opens submission → enters Marks + Feedback
↓
assignment_submissions.status = 'evaluated'
↓
Student dashboard updates, notification sent
```

**Leave**
```text
Mentor: submit own leave request → goes to HOD for approval
Student leave request → routed to Mentor for approval (see Section 7)
```

---

## 6. Learner Workflow

```text
Student Login
↓
Dashboard
  - Attendance %
  - Pending Assignments
  - Study Materials
  - Notifications
  - Profile
```

**Assignment Submission**
```text
View Assignment → Upload PDF (validated: type, size, renamed) → Submit
→ status = 'submitted' (or 'late' if past deadline)
```

**Attendance**
```text
Attendance Page → Overall % → Subject-wise % breakdown
→ Flag if any subject < 75% (visually highlighted)
```

**Notifications**
```text
Unread Notifications shown first
↓
On open → insert/update row in notification_reads
```

**Leave Request**
```text
Student submits leave (reason, from_date, to_date)
↓
Routed to assigned Mentor for approval
```

---

## 7. Leave Request Workflow (gap filled)

The original draft mentioned "Pending Leave" on the HOD dashboard but never defined who approves what. Fixed routing:

```text
Student leave request   → approved/rejected by their assigned Mentor
Mentor leave request    → approved/rejected by HOD
```

```text
Requester submits leave_requests row (status = 'pending')
↓
Approver (mentor or HOD, based on requested_to_role) sees it in their dashboard
↓
Approve / Reject → status updated, approved_by set
↓
Requester notified
```

---

## 8. Real-Time Notification Workflow (AJAX Polling)

PHP has no native real-time push like Node + Socket.IO, so polling is the practical approach:

```text
HOD/Mentor creates notification
↓
Saved in `notifications`
↓
Every 10–30 seconds: JS fetch()/AJAX checks for new rows
  (query: notifications targeted at this user, not yet in notification_reads)
↓
If found → popup + badge count
↓
On click → mark as read (insert into notification_reads)
```

---

## 9. Dark / Light Mode

```text
Click Theme Toggle
↓
Store preference in localStorage (not MySQL — avoids a DB write on every toggle)
↓
On page load → JS reads localStorage → applies theme class to <body>
```

---

## 10. Responsive UI

Use **Bootstrap 5** (via CDN) rather than Tailwind — no build step needed for a plain-PHP project, and Bootstrap's grid + components (sidebar, cards, navbar, offcanvas menu) cover this brief directly.

```text
Desktop  → Sidebar + Content + Analytics panel
Tablet   → Collapsible sidebar
Mobile   → ☰ Offcanvas menu + stacked cards + bottom nav
```

---

## 11. Additional Features (viva-readiness additions)

- **Reports export** — Excel (PhpSpreadsheet) and PDF (DomPDF/TCPDF) for attendance and assignment reports.
- **Pagination** — on student lists, assignment lists, notification history (don't load everything in one query).
- **Email notifications** — PHPMailer, triggered on assignment deadline approaching or leave status change (optional but a strong demo point).
- **Audit log** — every add/edit/delete by HOD or Mentor logged to `audit_logs`, with a simple viewer page for HOD. Cheap to add, makes the system look production-grade.

---

## 12. Project Folder Structure

```text
student-companion/
│
├── admin/
├── mentor/
├── learner/
│
├── assets/
│      ├── css/
│      ├── js/
│      └── images/
│
├── config/
│      └── database.php
│
├── includes/
│      ├── header.php
│      ├── sidebar.php
│      ├── navbar.php
│      ├── footer.php
│      └── auth_check.php      (role guard, included at top of every protected page)
│
├── uploads/
│      ├── notes/
│      ├── assignments/
│      └── profiles/
│
├── exports/                   (generated Excel/PDF reports)
├── vendor/                    (Composer: PHPMailer, DomPDF/PhpSpreadsheet)
│
├── login.php
├── forgot_password.php
├── logout.php
├── dashboard.php
└── index.php
```

---

## 13. Final Real-World Flow

```text
HOD Login
      │
      ▼
Create Mentors & Students (with role-checked, hashed-password accounts)
      │
      ▼
Assign Students to Mentors, Mentors to Subjects
      │
      ▼
Mentor Uploads Notes & Assignments
      │
      ▼
Students View, Download & Submit Work
      │
      ▼
Mentor Evaluates & Marks Attendance
      │
      ▼
Leave Requests Routed (Student → Mentor, Mentor → HOD)
      │
      ▼
Analytics Dashboard Updates Automatically (live SQL, not stored)
      │
      ▼
HOD Monitors Department Performance, Reviews Audit Log
      │
      ▼
Notifications Sent & Tracked Per-User (AJAX polling + notification_reads)
```

---

## 14. Suggested Build Order for Claude Code

Feed these as separate prompts, in this order, rather than the whole spec at once — keeps each module consistent before moving to the next:

1. DB schema + `config/database.php` (PDO, prepared statements)
2. `includes/auth_check.php` + login/logout/forgot-password
3. HOD: student + mentor management
4. Mentor: attendance + assignment creation/evaluation
5. Learner: assignment submission + attendance view
6. Notifications (creation, AJAX polling, read tracking)
7. Leave request flow (both directions)
8. Analytics dashboard (live SQL queries)
9. Reports export (Excel/PDF) + audit log
10. Theme toggle + responsive pass (Bootstrap 5)
