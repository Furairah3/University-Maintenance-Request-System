# Smart Hostel Maintenance Request System

**CS415 Software Engineering | Group 11 | Ashesi University**

A web-based system connecting students, administrators, and maintenance staff for efficient hostel maintenance management.

## Tech Stack

- **Frontend:** HTML5, CSS3, JavaScript
- **Backend:** PHP 8.0+
- **Database:** MySQL 8.0+
- **Server:** Apache with mod_rewrite

## Setup Instructions

### Prerequisites
- PHP 8.0 or higher
- MySQL 8.0 or higher
- Apache web server (XAMPP, WAMP, or MAMP recommended)

### Installation

1. **Clone the repository:**
   ```bash
   git clone https://github.com/Furairah3/University-Maintenance-Request-System..git hostel-system
   cd hostel-system
   ```

2. **Create the database:**
   ```bash
   mysql -u root -p < database/schema.sql
   ```

3. **Configure the application:**
   - Open `backend/config/config.php`
   - Update database credentials (`DB_USER`, `DB_PASS`)
   - Update `APP_URL` to match your local setup

4. **Set up Apache:**
   - Copy the project to your Apache htdocs folder
   - Ensure `mod_rewrite` is enabled
   - Access at `http://localhost/hostel-system/`

5. **Create the uploads directory:**
   ```bash
   mkdir -p backend/uploads
   chmod 755 backend/uploads
   ```

### Default Test Accounts

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@ashesi.edu.gh | Admin@2026 |
| Staff | j.mensah@ashesi.edu.gh | Staff@2026 |
| Staff | a.darko@ashesi.edu.gh | Staff@2026 |
| Student | student@ashesi.edu.gh | Student@2026 |

> **Note:** The seed passwords in the SQL use a placeholder hash. After setup, register fresh accounts or update the hashes using PHP's `password_hash()`.

## Project Structure

```
hostel-system/
├── backend/
│   ├── config/config.php        # App configuration
│   ├── includes/
│   │   ├── Auth.php             # Authentication & RBAC
│   │   ├── Database.php         # PDO singleton
│   │   ├── Logger.php           # Structured logging
│   │   └── helpers.php          # Utility functions
│   ├── api/                     # API endpoints
│   └── uploads/                 # User-uploaded images
├── frontend/
│   ├── css/style.css            # Main stylesheet
│   ├── js/                      # JavaScript files
│   ├── images/                  # Static images
│   ├── errors/403.php           # Forbidden page
│   └── includes/                # Layout templates
│       ├── student-header.php
│       ├── admin-header.php
│       ├── staff-header.php
│       └── footer.php
├── student/
│   ├── dashboard.php            # Student dashboard
│   ├── new-request.php          # Submit request form
│   ├── my-requests.php          # Request listing
│   └── view-request.php         # Request detail + re-open
├── admin/
│   └── dashboard.php            # Admin dashboard with filters, assign, metrics
├── staff/
│   └── dashboard.php            # Staff task list with status updates
├── database/
│   └── schema.sql               # Complete database schema
├── docs/                        # UML diagrams, mockups
├── logs/                        # Application logs (auto-created)
├── login.php                    # Shared login page
├── register.php                 # Student registration
├── logout.php                   # Logout handler
├── index.php                    # Redirect to login
└── README.md
```

## Features

### Student Module
- Register with university email (@ashesi.edu.gh)
- Submit maintenance requests with image uploads
- Track request status (Pending → In Progress → Completed)
- Re-open completed requests within 48 hours
- In-app notifications

### Admin Module
- Centralized dashboard with all requests
- Filter by status, category, priority, date
- Search by student name or request title
- Set priority and assign to staff
- Staff account management (create/edit/deactivate)
- Performance metrics (avg response time, avg completion time)

### Maintenance Staff Module
- View only assigned tasks
- Update status with strict flow enforcement
- View task details and uploaded images
- Active task counter

### Security
- Bcrypt password hashing
- Role-based access control (RBAC)
- CSRF protection on all forms
- Session timeout (15 minutes)
- Server-side file validation
- Prepared statements (SQL injection prevention)

## Team

- **Group 11** — Class of 2027, Cohort A
- **Instructor:** Dr. Umut Tosun
- **Faculty Interns:** Elikem Bansah, Daniel Byiringiro

## License

This project is developed for academic purposes at Ashesi University.
