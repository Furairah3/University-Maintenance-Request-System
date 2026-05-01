# HostelIQ — Smart Hostel Management System
## Ashesi University CS415 · Group 11 · Class of 2027 Cohort A

---

## 📦 Project Structure

```
smart-hostel/
├── index.php                  ← Landing page
├── login.php                  ← Login page
├── register.php               ← Student registration
├── logout.php                 ← Session destroy
├── database.sql               ← Full database schema + seed data
├── config/
│   └── db.php                 ← DB connection + site config
├── includes/
│   ├── auth.php               ← Session, auth helpers
│   ├── email.php              ← Email notifications
│   └── sidebar.php            ← Shared navigation sidebar
├── student/
│   ├── dashboard.php          ← Student home + stats
│   ├── new_request.php        ← Submit new request
│   ├── my_requests.php        ← All requests with filters
│   ├── request_details.php    ← Request timeline + history
│   └── settings.php           ← Profile + password
├── admin/
│   ├── dashboard.php          ← Admin overview + charts
│   ├── requests.php           ← All requests with filters
│   ├── request_details.php    ← Assign staff, set priority
│   ├── staff.php              ← Staff list + workload
│   ├── add_staff.php          ← Add / edit staff accounts
│   ├── reports.php            ← Analytics + charts
│   └── settings.php           ← Admin profile
├── staff/
│   ├── dashboard.php          ← Task cards with priority
│   ├── task_details.php       ← Start Work / Mark Complete
│   └── settings.php           ← Password change
├── assets/
│   ├── css/style.css          ← Full Ashesi-themed stylesheet
│   └── js/main.js             ← Client-side interactions
└── uploads/                   ← Image upload directory (must be writable)
```

---

## 🚀 Setup Instructions

### Option A — Local (XAMPP / WAMP)

1. **Copy project** into your web root:
   - XAMPP: `C:/xampp/htdocs/smart-hostel/`
   - WAMP:  `C:/wamp64/www/smart-hostel/`

2. **Create the database:**
   - Open phpMyAdmin → create database `smart_hostel`
   - Click **Import** → select `database.sql` → click Go

3. **Configure connection** in `config/db.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');     // your DB username
   define('DB_PASS', '');         // your DB password
   define('DB_NAME', 'smart_hostel');
   define('SITE_URL', 'http://localhost/smart-hostel');
   ```

4. **Make uploads folder writable:**
   ```bash
   chmod 755 uploads/
   ```
   On Windows XAMPP this is automatic.

5. **Visit:** `http://localhost/smart-hostel`

---

### Option B — Free Hosting (InfinityFree)

1. Sign up at [infinityfree.net](https://infinityfree.net)
2. Create a hosting account and note your:
   - MySQL hostname, username, password, database name
3. Upload all files via FTP (FileZilla) or the control panel file manager
4. Create database in **phpMyAdmin** and import `database.sql`
5. Edit `config/db.php` with your InfinityFree DB credentials
6. Update `SITE_URL` to your InfinityFree subdomain

---

### Option C — Railway (MySQL + PHP)

1. Push to GitHub
2. Create a new Railway project → Deploy from GitHub
3. Add a MySQL plugin → copy connection variables
4. Set environment variables matching `config/db.php`
5. Import `database.sql` via Railway's database console

---

## 👤 Demo Accounts

| Role    | Email                       | Password |
|---------|-----------------------------|----------|
| Admin   | admin@ashesi.edu.gh         | password |
| Student | k.asante@ashesi.edu.gh      | password |
| Staff   | j.mensah@ashesi.edu.gh      | password |

> ⚠️ Change these passwords immediately after first login in production.

---

## 🔐 Security Features

- **Passwords:** Bcrypt hashed via PHP `password_hash()`
- **SQL Injection:** Prevented via PDO prepared statements
- **Access Control:** Role check on every page via `requireLogin()`
- **File Uploads:** Type + size validation before storage
- **XSS:** All output sanitized via `htmlspecialchars()`

---

## 📧 Email Notifications

By default, the system uses PHP's built-in `mail()` function.

To use **Gmail SMTP** (recommended), install PHPMailer:
```bash
composer require phpmailer/phpmailer
```
Then update `includes/email.php` to use PHPMailer with SMTP settings from `config/db.php`.

For testing without real emails, use [Mailtrap](https://mailtrap.io) — update SMTP credentials in `config/db.php`.

---

## 📱 Responsive Breakpoints

| Breakpoint | Behavior |
|------------|----------|
| > 1024px   | Full sidebar + 4-column stats |
| 768–1024px | 2-column stats |
| < 768px    | Sidebar collapses (hamburger), single column |

---

## 🗄️ Database Tables

| Table           | Purpose |
|-----------------|---------|
| `users`         | All users — students, admins, staff |
| `requests`      | Maintenance requests with status/priority |
| `categories`    | Electrical, Plumbing, Furniture, HVAC, Other |
| `status_history`| Full audit trail of every status change |
| `notifications` | In-app notification log |

---

## 🧰 Tech Stack

| Layer    | Technology |
|----------|-----------|
| Frontend | HTML5, CSS3 (custom), Vanilla JS |
| Backend  | PHP 8.x |
| Database | MySQL 8.x |
| Fonts    | Poppins (Google Fonts) |
| Auth     | PHP Sessions + bcrypt |
| Uploads  | PHP `move_uploaded_file()` |

---

## 📋 Sprint 1 Deliverables Checklist

- [x] US-03: Class Diagram (see `/docs/class_diagram.svg`)
- [x] US-05: UI Mockups (Figma file + HTML prototype)
- [x] US-07: Database Schema (`database.sql`)
- [x] US-08: GitHub repository structure

---

*CS415 Software Engineering · Ashesi University 2025*
*Instructor: Dr. Umut Tosun | Interns: Elikem Bansah & Daniel Byiringiro*
