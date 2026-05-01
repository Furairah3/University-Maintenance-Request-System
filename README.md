# HostelIQ

A maintenance request tracking system for Ashesi University hostels.  
Students submit issues. Admins assign the right staff. Staff resolve and update. Everyone is notified automatically.

**Live:** https://smarthostel.rf.gd/smart-hostel/

---

## The three roles

| Role | What they do |
|------|-------------|
| **Student** | Submit requests, track status, receive email updates, rate resolutions |
| **Admin** | View all requests, set priority, assign staff by profession, view analytics |
| **Staff** | See assigned tasks only, mark In Progress, mark Completed |

---

## Built with

- **PHP 8** — backend, authentication, business logic
- **MySQL 8** — database with full audit trail
- **HTML / CSS / Vanilla JS** — no Bootstrap, no React, no frameworks
- **Custom SMTP client** — email notifications without PHPMailer
- **InfinityFree** — free hosting

---

## Run locally (XAMPP)

```bash
git clone https://github.com/Furairah3/University-Maintenance-Request-System.git
```

1. Copy the folder into `htdocs/smart-hostel/`
2. Create a database called `smart_hostel` in phpMyAdmin
3. Import `database.sql`
4. Edit `config/db.php`:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'smart_hostel');
define('SITE_URL', 'http://localhost/smart-hostel');
define('UPLOAD_DIR', $_SERVER['DOCUMENT_ROOT'] . '/smart-hostel/uploads/');
define('UPLOAD_URL', 'http://localhost/smart-hostel/uploads/');
```

5. Visit `http://localhost/smart-hostel`

---

## Demo accounts

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@ashesi.edu.gh | password |
| Student | k.asante@ashesi.edu.gh | password |
| Staff | j.mensah@ashesi.edu.gh | password |

---

## Email setup (Gmail)

```php
// In config/db.php
define('SMTP_USER', 'yourgmail@gmail.com');
define('SMTP_PASS', 'your-16-char-app-password');
```

Get the app password: Google Account → Security → 2-Step Verification → App Passwords.

Test it at `/admin/test_email.php` — shows the full SMTP transcript.

---

## Deploy to InfinityFree

1. Sign up at infinityfree.com
2. Create a MySQL database — note the hostname, username, password, DB name
3. Import `database.sql` via phpMyAdmin (select your database first, then Import)
4. Update `config/db.php` with your InfinityFree credentials and live URL
5. Upload all files into `htdocs/` via File Manager or FTP
6. Set `uploads/` folder permissions to 755

---

## Diagnostic tools

| URL | What it checks |
|-----|---------------|
| `/healthcheck.php` | PHP version, DB connection, all tables, upload permissions, SMTP port |
| `/admin/test_email.php` | Sends a test email, prints the full SMTP conversation |
| Any URL `+ ?debug=1` | Shows the real PHP error instead of a blank page |

---

## Project

**CS415 Software Engineering — Group 11 — Ashesi University, Class of 2027 Cohort A**

| Name | Role |
|------|------|
| Fourairatou Idi | Product Owner |
| Ramatou Salah Hassane | Scrum Master |
| Chidima P. J. Ugwu | Developer |
| Papa Obosu | Developer |

Instructor: Dr. Umut Tosun  
Faculty Interns: Elikem Bansah, Daniel Byiringiro  
Jira: [ramaah.atlassian.net](https://ramaah.atlassian.net)
