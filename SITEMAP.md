## Site Map
Student Portal (Site Structure)
|
├── landingPage.html        (Landing Page - Public)
|   ├── Login button
|   └── Register button
|
├── login.php               (Secure Sign In - Public)
|   ├── Email & Password authentication via PDO & password_verify()
|   ├── Remember Me persistent login (SHA-256 hashed token)
|   └── Session regeneration on success
|
├── register.php            (User Registration - Public)
|   ├── Full name, Email, Password, Confirm password
|   ├── Server-side validation & duplicate email checks
|   └── Secure password hashing (bcrypt) & database insert
|
├── logout.php              (Session Destruction & Token Revocation)
|
├── admin_dashboard.php     (Administrator Dashboard - Practical 14)
|   ├── Strict server-side RBAC verification (admin only)
|   ├── Dynamic MySQL statistics (Users, Students, Events, Registrations)
|   ├── Recent registrations table (student and event relationship join)
|   ├── Comprehensive Administrator Audit Log table (state-changing actions)
|   ├── User accounts management overview
|   ├── Shared dynamic role-based sidebar navigation
|   └── Asynchronous Fetch API live data refresh via api/dashboard.php
|
├── api/dashboard.php       (Dashboard JSON API Endpoint - Practical 14)
|   ├── Strict admin-only session authorization
|   └── Standardized JSON metrics, recent registrations, and audit logs
|
├── students.php            (Student Management CRUD - Authenticated, Admin write)
|   ├── Server-side RBAC: admin can Create/Update/Delete; standard users read-only
|   ├── Live search by student name, email, or ID (PDO prepared statements)
|   ├── Course filter (All Courses, B.Tech, BCA, MCA, B.Sc, M.Tech)
|   ├── Combined multi-parameter search & filtering
|   ├── CSRF protection on state-changing forms (token hashing & timing-safe validation)
|   ├── Server-side validation, duplicate check, and HTML escaping (XSS prevention)
|   ├── Confirmation modal before record deletion
|   └── CSV student directory export
|
├── events.php              (Event Management CRUD with Poster Upload & Fetch API - Practical 12 & 13)
|   ├── Asynchronous Fetch API CRUD: List, Add, Edit, Delete without full page reload
|   ├── Real-time debounced live search and dynamic category/status filtering
|   ├── Server-side RBAC: admin can Create/Update/Delete; standard users read-only
|   ├── MySQLi prepared statements for all CRUD operations
|   ├── Secure poster file upload (JPG, PNG, WebP &bull; Max 2 MB limit) via FormData
|   ├── Multi-layer validation (upload error code, size, finfo MIME, getimagesize integrity)
|   ├── Safe physical poster cleanup on event update/deletion when unreferenced
|   ├── CSRF token protection on state-changing actions (X-CSRF-Token header & token validation)
|   └── Modals for Create, Edit, View Details, and Delete Confirmation with dynamic feedback
|
├── api/events.php          (RESTful PHP JSON API Endpoints - Practical 13)
|   ├── GET: Retrieve all events (with search & filters) or single event (?id=)
|   ├── POST: Create new event (JSON or multipart/form-data for posters)
|   ├── PUT / POST: Update existing event with optional poster replacement
|   ├── DELETE / POST: Delete event and safe poster file unlinking
|   ├── Standardized JSON responses { success, message, data } with HTTP status codes
|   └── Server-side session authentication, RBAC admin enforcement, and CSRF protection
|
└── home.php                (Student Dashboard - Authenticated User)
    ├── profile.html        (Profile - Student)
    |   ├── Personal Info (Name, DOB, Gender, Blood Group)
    |   ├── Contact Info (Email, Phone, Address)
    |   ├── Academic Summary (Course, Year, CGPA)
    |   ├── Edit Profile button
    |   └── Change Password button
    |
    ├── academics.html      (Academics - Student)
    |   ├── Results (semester + exam type selector)
    |   ├── Timetable
    |   └── Attendance
    |
    ├── fees.html           (Fee Details - Student)
    |   ├── Fee Overview (Total, Paid, Pending, Due Date)
    |   ├── Per-semester fee breakdown table
    |   ├── Pay Current Due button
    |   └── Payment History & Receipts (Download)
    |
    ├── clubs.html          (Student Clubs - Student)
    |   ├── Category filters (Technical / Cultural / Sports / Literary)
    |   ├── My Joined Clubs (Role, Leave Club)
    |   ├── All Available Clubs (Join / View Events)
    |   ├── Upcoming Club Events (Register)
    |   └── Apply to Start a New Club form
    |
    ├── extracurricular.html (Extracurriculars - Student)
    |   ├── Category filters (Hackathons / Competitions / Workshops / Conclave)
    |   └── Event listing cards (image + details)
    |
    ├── Event_registration.php  (Event Registration & Inquiries - PHP/JSON)
    |   ├── Personal & academic registration form
    |   ├── Server-side sanitization & validation (Email, Phone, Name, Course, Year)
    |   ├── Safe JSON file storage (data/registrations.json)
    |   └── Recent registrations summary table
    |
    ├── db_status.php           (Database & System Status)
    |   ├── Live PDO connection verification (scripts/db.php)
    |   ├── Schema metrics (students, events, registrations)
    |   └── Verified records preview using PDO prepared statements
    |
    ├── settings.html       (Settings - Student)
    |   ├── Change Password
    |   ├── Notifications (Email / Exam / SMS)
    |   ├── Privacy & 2FA
    |   ├── Language & Region
    |   └── Dark Mode toggle
    |
    └── Logout
