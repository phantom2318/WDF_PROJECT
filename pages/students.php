<?php
/**
 * StudentHub: Student Management Module (Practical 11)
 * Full CRUD operations with Search, Course Filter, Role-Based Access Control, and CSRF Protection
 */

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once __DIR__ . '/../scripts/auth.php';
requireLogin(); // Enforce authentication

require_once __DIR__ . '/../scripts/db.php';
require_once __DIR__ . '/../scripts/audit_helper.php';
require_once __DIR__ . '/../scripts/nav_helper.php';

$isAdmin   = isAdmin();
$userName  = htmlspecialchars($_SESSION['name'] ?? 'User', ENT_QUOTES, 'UTF-8');
$userRole  = htmlspecialchars($_SESSION['role'] ?? 'user', ENT_QUOTES, 'UTF-8');
$userEmail = htmlspecialchars($_SESSION['email'] ?? '', ENT_QUOTES, 'UTF-8');

// Optional CSV Export feature (Intermediate curriculum extension)
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $search = trim($_GET['search'] ?? '');
    $course = trim($_GET['course'] ?? '');

    $where   = [];
    $params  = [];

    if ($search !== '') {
        $where[] = '(`full_name` LIKE :search_name OR `email` LIKE :search_email OR `student_id` LIKE :search_id)';
        $params['search_name']  = "%{$search}%";
        $params['search_email'] = "%{$search}%";
        $params['search_id']    = "%{$search}%";
    }

    if ($course !== '') {
        $where[] = '`course` = :course';
        $params['course'] = $course;
    }

    $sql = 'SELECT `student_id`, `full_name`, `email`, `mobile`, `department`, `course`, `semester`, `created_at` FROM `students`';
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY `student_id` ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="studenthub_students_' . date('Ymd_His') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Student ID', 'Full Name', 'Email', 'Mobile', 'Department', 'Course', 'Semester', 'Created At']);
    foreach ($records as $r) {
        fputcsv($output, [
            $r['student_id'],
            $r['full_name'],
            $r['email'],
            $r['mobile'],
            $r['department'],
            $r['course'],
            $r['semester'],
            $r['created_at']
        ]);
    }
    fclose($output);
    exit;
}

// Flash messages from session (PRG pattern)
$successMessage = $_SESSION['flash_success'] ?? '';
$errorMessage   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Handle State-Changing Operations (CREATE, UPDATE, DELETE) via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    $csrf   = $_POST['csrf_token'] ?? '';

    // 1. Strict Server-side RBAC Enforcement
    if (!$isAdmin) {
        http_response_code(403);
        $_SESSION['flash_error'] = 'Access denied. You do not have permission to modify student records.';
        header('Location: students.php');
        exit;
    }

    // 2. CSRF Token Validation
    if (!validateCsrfToken($csrf)) {
        http_response_code(400);
        $_SESSION['flash_error'] = 'Security validation failed (invalid or expired CSRF token). Please try again.';
        header('Location: students.php');
        exit;
    }

    // --- CREATE OPERATION ---
    if ($action === 'create') {
        $studentId  = strtoupper(trim($_POST['student_id'] ?? ''));
        $fullName   = trim(strip_tags($_POST['full_name'] ?? ''));
        $email      = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $mobile     = preg_replace('/\D/', '', $_POST['mobile'] ?? '');
        $department = trim(strip_tags($_POST['department'] ?? ''));
        $course     = trim(strip_tags($_POST['course'] ?? ''));
        $semester   = (int)($_POST['semester'] ?? 1);

        $errors = [];

        if (empty($studentId) || !preg_match('/^[A-Z0-9]{4,20}$/', $studentId)) {
            $errors[] = 'Student ID must be 4–20 alphanumeric characters (e.g., 25DCS110).';
        }
        if (empty($fullName) || strlen($fullName) < 3 || !preg_match('/^[A-Za-z0-9\s.\'()-]+$/', $fullName)) {
            $errors[] = 'Full name must contain at least 3 alphabetical characters.';
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }
        if (empty($mobile) || !preg_match('/^[6-9]\d{9}$/', $mobile)) {
            $errors[] = 'Mobile number must be a valid 10-digit number.';
        }
        if (empty($department)) {
            $errors[] = 'Department is required.';
        }
        if (empty($course)) {
            $errors[] = 'Course selection is required.';
        }
        if ($semester < 1 || $semester > 8) {
            $errors[] = 'Semester must be between 1 and 8.';
        }

        if (empty($errors)) {
            try {
                // Check duplicate Student ID
                $checkId = $pdo->prepare('SELECT COUNT(*) FROM `students` WHERE `student_id` = :sid');
                $checkId->execute(['sid' => $studentId]);
                if ($checkId->fetchColumn() > 0) {
                    $errors[] = "Student ID '{$studentId}' is already registered in the system.";
                }

                // Check duplicate Email
                $checkEmail = $pdo->prepare('SELECT COUNT(*) FROM `students` WHERE `email` = :email');
                $checkEmail->execute(['email' => $email]);
                if ($checkEmail->fetchColumn() > 0) {
                    $errors[] = "Email address '{$email}' is already associated with another student.";
                }

                if (empty($errors)) {
                    $insertStmt = $pdo->prepare('
                        INSERT INTO `students` (`student_id`, `full_name`, `email`, `mobile`, `department`, `course`, `semester`)
                        VALUES (:student_id, :full_name, :email, :mobile, :department, :course, :semester)
                    ');
                    $insertStmt->execute([
                        'student_id'  => $studentId,
                        'full_name'   => $fullName,
                        'email'       => $email,
                        'mobile'      => $mobile,
                        'department'  => $department,
                        'course'      => $course,
                        'semester'    => $semester
                    ]);

                    // Audit Logging (Practical 14)
                    logAuditAction('CREATE_STUDENT', 'student', $studentId, "Created student '{$fullName}' ({$studentId}), Course: {$course}, Dept: {$department}");

                    $_SESSION['flash_success'] = "Student '{$fullName}' ({$studentId}) was created successfully.";
                    header('Location: students.php');
                    exit;
                }
            } catch (PDOException $e) {
                $errors[] = 'Database operation failed. Please check inputs and try again.';
            }
        }

        if (!empty($errors)) {
            $_SESSION['flash_error'] = implode(' ', $errors);
            header('Location: students.php');
            exit;
        }
    }

    // --- UPDATE OPERATION ---
    if ($action === 'update') {
        $studentId  = strtoupper(trim($_POST['student_id'] ?? ''));
        $fullName   = trim(strip_tags($_POST['full_name'] ?? ''));
        $email      = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $mobile     = preg_replace('/\D/', '', $_POST['mobile'] ?? '');
        $department = trim(strip_tags($_POST['department'] ?? ''));
        $course     = trim(strip_tags($_POST['course'] ?? ''));
        $semester   = (int)($_POST['semester'] ?? 1);

        $errors = [];

        if (empty($studentId)) {
            $errors[] = 'Student ID is missing.';
        }
        if (empty($fullName) || strlen($fullName) < 3 || !preg_match('/^[A-Za-z0-9\s.\'()-]+$/', $fullName)) {
            $errors[] = 'Full name must contain at least 3 alphabetical characters.';
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }
        if (empty($mobile) || !preg_match('/^[6-9]\d{9}$/', $mobile)) {
            $errors[] = 'Mobile number must be a valid 10-digit number.';
        }
        if (empty($department)) {
            $errors[] = 'Department is required.';
        }
        if (empty($course)) {
            $errors[] = 'Course selection is required.';
        }
        if ($semester < 1 || $semester > 8) {
            $errors[] = 'Semester must be between 1 and 8.';
        }

        if (empty($errors)) {
            try {
                // Ensure student exists
                $existStmt = $pdo->prepare('SELECT COUNT(*) FROM `students` WHERE `student_id` = :sid');
                $existStmt->execute(['sid' => $studentId]);
                if ($existStmt->fetchColumn() === 0) {
                    $errors[] = "Student '{$studentId}' does not exist.";
                }

                // Check duplicate email (excluding this student's own record)
                $dupEmail = $pdo->prepare('SELECT COUNT(*) FROM `students` WHERE `email` = :email AND `student_id` != :sid');
                $dupEmail->execute(['email' => $email, 'sid' => $studentId]);
                if ($dupEmail->fetchColumn() > 0) {
                    $errors[] = "Email '{$email}' is already in use by another student.";
                }

                if (empty($errors)) {
                    $updateStmt = $pdo->prepare('
                        UPDATE `students`
                        SET `full_name` = :full_name,
                            `email` = :email,
                            `mobile` = :mobile,
                            `department` = :department,
                            `course` = :course,
                            `semester` = :semester
                        WHERE `student_id` = :student_id
                    ');
                    $updateStmt->execute([
                        'full_name'   => $fullName,
                        'email'       => $email,
                        'mobile'      => $mobile,
                        'department'  => $department,
                        'course'      => $course,
                        'semester'    => $semester,
                        'student_id'  => $studentId
                    ]);

                    // Audit Logging (Practical 14)
                    logAuditAction('UPDATE_STUDENT', 'student', $studentId, "Updated student '{$fullName}' ({$studentId}), Course: {$course}, Dept: {$department}");

                    $_SESSION['flash_success'] = "Student '{$studentId}' updated successfully.";
                    header('Location: students.php');
                    exit;
                }
            } catch (PDOException $e) {
                $errors[] = 'Database update failed. Please try again.';
            }
        }

        if (!empty($errors)) {
            $_SESSION['flash_error'] = implode(' ', $errors);
            header('Location: students.php');
            exit;
        }
    }

    // --- DELETE OPERATION ---
    if ($action === 'delete') {
        $studentId = strtoupper(trim($_POST['student_id'] ?? ''));

        if (empty($studentId)) {
            $_SESSION['flash_error'] = 'Invalid student ID provided for deletion.';
            header('Location: students.php');
            exit;
        }

        try {
            // Check student existence
            $checkStmt = $pdo->prepare('SELECT `full_name` FROM `students` WHERE `student_id` = :sid');
            $checkStmt->execute(['sid' => $studentId]);
            $student = $checkStmt->fetch();

            if (!$student) {
                $_SESSION['flash_error'] = "Student '{$studentId}' does not exist or has already been removed.";
            } else {
                // Delete student record
                $delStmt = $pdo->prepare('DELETE FROM `students` WHERE `student_id` = :sid');
                $delStmt->execute(['sid' => $studentId]);

                // Audit Logging (Practical 14)
                logAuditAction('DELETE_STUDENT', 'student', $studentId, "Deleted student '{$student['full_name']}' ({$studentId})");

                $_SESSION['flash_success'] = "Student '{$student['full_name']}' ({$studentId}) has been successfully deleted.";
            }
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = 'Unable to delete student record due to database constraint.';
        }

        header('Location: students.php');
        exit;
    }
}

// --- READ / SEARCH / FILTER OPERATIONS (GET) ---
$search = trim($_GET['search'] ?? '');
$course = trim($_GET['course'] ?? '');

$whereConditions = [];
$queryParams     = [];

// Search by name or email or student ID using PDO prepared statement
if ($search !== '') {
    $whereConditions[] = '(`full_name` LIKE :search_name OR `email` LIKE :search_email OR `student_id` LIKE :search_id)';
    $queryParams['search_name']  = "%{$search}%";
    $queryParams['search_email'] = "%{$search}%";
    $queryParams['search_id']    = "%{$search}%";
}

// Filter by course
if ($course !== '') {
    $whereConditions[] = '`course` = :course_filter';
    $queryParams['course_filter'] = $course;
}

// Assemble query with safe parameter binding
$sql = 'SELECT `student_id`, `full_name`, `email`, `mobile`, `department`, `course`, `semester`, `created_at` FROM `students`';
if (!empty($whereConditions)) {
    $sql .= ' WHERE ' . implode(' AND ', $whereConditions);
}
$sql .= ' ORDER BY `student_id` ASC';

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParams);
    $students = $stmt->fetchAll();

    // Fetch distinct course list for filter dropdown
    $courseStmt = $pdo->query('SELECT DISTINCT `course` FROM `students` WHERE `course` IS NOT NULL AND `course` != "" ORDER BY `course` ASC');
    $allCourses = $courseStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    die('Database query error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

$csrfToken = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management — StudentHub</title>
    <script src="../scripts/theme.js"></script>
    <link rel="icon" type="image/png" href="../images/favicon.png">
    <link rel="stylesheet" href="portal.css">
    <link rel="stylesheet" href="darkmode.css">
    <style>
        /* Strict Modal Overlay Hiding & Centering */
        .modal-overlay {
            display: none !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            background: rgba(15, 23, 42, 0.70) !important;
            backdrop-filter: blur(4px) !important;
            align-items: center !important;
            justify-content: center !important;
            z-index: 99999 !important;
        }
        .modal-overlay.active {
            display: flex !important;
        }
        .modal-box {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.35);
            width: 95%;
            max-width: 580px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 24px 28px;
            box-sizing: border-box;
            animation: modalSlide 0.22s var(--ease);
        }
        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border);
            padding-bottom: 12px;
            margin-bottom: 18px;
        }
        .modal-header h3 {
            margin: 0;
            background: none;
            border-left: none;
            padding: 0;
            font-size: 1.2rem;
            color: var(--text);
        }
        .modal-close-btn {
            background: transparent !important;
            color: var(--text-muted) !important;
            border: none !important;
            font-size: 26px !important;
            line-height: 1 !important;
            cursor: pointer !important;
            padding: 2px 8px !important;
            border-radius: var(--radius-sm) !important;
            box-shadow: none !important;
        }
        .modal-close-btn:hover {
            background: var(--surface-muted) !important;
            color: var(--danger) !important;
            transform: none !important;
        }
        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-bottom: 12px;
        }
        .form-field {
            margin-bottom: 12px;
        }
        .form-field label {
            display: block;
            font-size: 11.5px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .form-field input,
        .form-field select {
            width: 100%;
            box-sizing: border-box;
        }

        /* Filter Toolbar */
        .filter-toolbar {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 12px 16px;
            margin-bottom: 16px;
            box-shadow: var(--shadow-xs);
        }
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            width: 100%;
        }
        .filter-form input[type="text"] {
            flex: 1;
            min-width: 220px;
        }
        .filter-form select {
            min-width: 150px;
        }
        .action-btns {
            display: inline-flex;
            gap: 6px;
        }
        .action-btns button {
            padding: 5px 12px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-pill {
            font-size: 11px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 99px;
            background: var(--surface-muted);
            border: 1px solid var(--border);
            color: var(--text-muted);
        }
    </style>
</head>
<body>

    <!-- Top Bar -->
    <table border="1" width="100%" class="topbar">
        <tr>
            <td width="12%">
                <img src="../images/logo.jpg" alt="Logo" style="height:55px; width:55px; border-radius: var(--radius-md);">
            </td>
            <td width="63%">
                <h1 style="margin:0; font-size: 1.5rem;">STUDENT MANAGEMENT</h1>
                <p style="margin:2px 0 0; color:var(--text-muted); font-size:13px;">
                    Practical 11 &bull; Logged in as: <b><?= $userName ?></b>
                    (<span class="badge <?= $isAdmin ? 'badge-danger' : 'badge-info' ?>"><?= ucfirst($userRole) ?></span>)
                </p>
            </td>
            <td width="25%" align="right">
                <a href="home.php"><button class="btn-outline" type="button">Dashboard</button></a>
                <?php if ($isAdmin): ?>
                    &nbsp;
                    <a href="admin_dashboard.php"><button class="btn-outline" type="button">Admin Panel</button></a>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <br>

    <!-- Main Content & Sidebar -->
    <table border="1" width="100%">
        <tr>
            <!-- Left Sidebar Navigation -->
            <td width="22%" valign="top" style="vertical-align: top; padding: 16px;">
                <?php renderSidebarNavigation('students'); ?>
            </td>

            <!-- Right Main Area -->
            <td width="78%" valign="top" style="vertical-align: top; padding: 20px;">

                <!-- UI Feedback Messages -->
                <?php if (!empty($successMessage)): ?>
                    <div class="alert alert-success">
                        <div><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errorMessage)): ?>
                    <div class="alert alert-danger">
                        <div><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                <?php endif; ?>

                <!-- Toolbar: Search, Course Filter, and Action Buttons -->
                <div class="filter-toolbar">
                    <form method="GET" action="students.php" class="filter-form">
                        <input
                            type="text"
                            name="search"
                            id="search-input"
                            placeholder="Search by student name, email, or ID..."
                            value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                            autocomplete="off"
                        >

                        <select name="course" id="course-filter">
                            <option value="">All Courses</option>
                            <?php foreach ($allCourses as $c): ?>
                                <option value="<?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>" <?= $course === $c ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <button type="submit" class="btn-sm">Filter</button>

                        <?php if ($search !== '' || $course !== ''): ?>
                            <a href="students.php">
                                <button type="button" class="btn-outline btn-sm">Reset</button>
                            </a>
                        <?php endif; ?>

                        <div style="margin-left: auto; display:flex; gap:8px; align-items:center;">
                            <a href="students.php?export=csv<?= $search !== '' ? '&search=' . urlencode($search) : '' ?><?= $course !== '' ? '&course=' . urlencode($course) : '' ?>">
                                <button type="button" class="btn-outline btn-sm" title="Export current list to CSV file">
                                    Export CSV
                                </button>
                            </a>

                            <?php if ($isAdmin): ?>
                                <button type="button" class="btn-success btn-sm" onclick="openCreateModal()">
                                    Add Student
                                </button>
                            <?php else: ?>
                                <span class="status-pill" title="Read-only view for standard users">View Only</span>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Results Header Metrics -->
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <div>
                        <h3 style="margin:0; padding:4px 10px; display:inline-block;">
                            Student Records Directory
                        </h3>
                        <span style="font-size:13px; color:var(--text-muted); margin-left:8px;">
                            Showing <b><?= count($students) ?></b> student<?= count($students) === 1 ? '' : 's' ?>
                            <?php if ($search !== '' || $course !== ''): ?>
                                (filtered)
                            <?php endif; ?>
                        </span>
                    </div>

                    <?php if (!$isAdmin): ?>
                        <div style="font-size:12px; color:var(--text-muted);">
                            <i>Notice: Only administrator accounts can create, edit, or delete records.</i>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Students Table -->
                <table border="1" width="100%" cellpadding="8">
                    <thead>
                        <tr>
                            <th width="14%">Student ID</th>
                            <th width="24%">Student Name</th>
                            <th width="26%">Email</th>
                            <th width="12%">Course</th>
                            <th width="14%">Department</th>
                            <th width="10%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding:36px 16px;">
                                    <div style="font-size:15px; font-weight:600; color:var(--text);">
                                        No students found matching your criteria.
                                    </div>
                                    <p style="font-size:13px; color:var(--text-muted); margin-top:4px;">
                                        Try adjusting your search terms or clearing the course filter.
                                    </p>
                                    <a href="students.php">
                                        <button type="button" class="btn-outline btn-sm" style="margin-top:10px;">
                                            View All Students
                                        </button>
                                    </a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($students as $s): ?>
                                <tr>
                                    <td>
                                        <code><?= htmlspecialchars($s['student_id'], ENT_QUOTES, 'UTF-8') ?></code>
                                    </td>
                                    <td>
                                        <b><?= htmlspecialchars($s['full_name'], ENT_QUOTES, 'UTF-8') ?></b>
                                        <div style="font-size:11.5px; color:var(--text-muted);">
                                            Sem <?= (int)$s['semester'] ?> &bull; <?= htmlspecialchars($s['mobile'], ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="mailto:<?= htmlspecialchars($s['email'], ENT_QUOTES, 'UTF-8') ?>" style="color:var(--primary); text-decoration:none;">
                                            <?= htmlspecialchars($s['email'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge badge-info">
                                            <?= htmlspecialchars($s['course'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td style="font-size:13px; color:var(--text-muted);">
                                        <?= htmlspecialchars($s['department'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td>
                                        <?php if ($isAdmin): ?>
                                            <div class="action-btns">
                                                <button
                                                    type="button"
                                                    class="btn-outline btn-sm"
                                                    title="Edit student"
                                                    onclick='openEditModal(<?= json_encode($s, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                                                >
                                                    Edit
                                                </button>
                                                <button
                                                    type="button"
                                                    class="btn-danger btn-sm"
                                                    title="Delete student"
                                                    onclick="openDeleteModal('<?= htmlspecialchars($s['student_id'], ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars(addslashes($s['full_name']), ENT_QUOTES, 'UTF-8') ?>')"
                                                >
                                                    Delete
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <span style="font-size:12px; color:var(--text-light);">Restricted</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

            </td>
        </tr>
    </table>

    <?php if ($isAdmin): ?>
        <!-- ========================================================== -->
        <!-- CREATE STUDENT MODAL (ADMIN ONLY)                          -->
        <!-- ========================================================== -->
        <div id="create-modal" class="modal-overlay" onclick="handleBackdropClick(event, 'create-modal')">
            <div class="modal-box">
                <div class="modal-header">
                    <h3>Add New Student Record</h3>
                    <button type="button" class="modal-close-btn" onclick="closeModal('create-modal')">&times;</button>
                </div>

                <form method="POST" action="students.php" onsubmit="return validateCreateForm(this)">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                    <div class="form-grid-2">
                        <div class="form-field">
                            <label for="create_student_id">Student ID *</label>
                            <input
                                type="text"
                                id="create_student_id"
                                name="student_id"
                                placeholder="e.g. 25DCS115"
                                required
                                pattern="^[A-Za-z0-9]{4,20}$"
                                title="Alphanumeric identifier, 4 to 20 characters"
                            >
                        </div>

                        <div class="form-field">
                            <label for="create_full_name">Full Name *</label>
                            <input
                                type="text"
                                id="create_full_name"
                                name="full_name"
                                placeholder="e.g. Aryan Trivedi"
                                required
                                minlength="3"
                            >
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-field">
                            <label for="create_email">Email Address *</label>
                            <input
                                type="email"
                                id="create_email"
                                name="email"
                                placeholder="e.g. student@charusat.edu.in"
                                required
                            >
                        </div>

                        <div class="form-field">
                            <label for="create_mobile">Mobile Number *</label>
                            <input
                                type="tel"
                                id="create_mobile"
                                name="mobile"
                                placeholder="10-digit mobile"
                                required
                                pattern="^[6-9]\d{9}$"
                                title="Valid 10-digit mobile number starting with 6, 7, 8, or 9"
                            >
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-field">
                            <label for="create_course">Course *</label>
                            <select id="create_course" name="course" required>
                                <option value="">Select Course</option>
                                <option value="B.Tech">B.Tech</option>
                                <option value="BCA">BCA</option>
                                <option value="MCA">MCA</option>
                                <option value="B.Sc">B.Sc</option>
                                <option value="M.Tech">M.Tech</option>
                            </select>
                        </div>

                        <div class="form-field">
                            <label for="create_semester">Semester *</label>
                            <select id="create_semester" name="semester" required>
                                <?php for ($i = 1; $i <= 8; $i++): ?>
                                    <option value="<?= $i ?>" <?= $i === 3 ? 'selected' : '' ?>>Semester <?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-field">
                        <label for="create_department">Department *</label>
                        <select id="create_department" name="department" required>
                            <option value="Information Technology">Information Technology</option>
                            <option value="Computer Science">Computer Science</option>
                            <option value="Computer Engineering">Computer Engineering</option>
                            <option value="Computer Applications">Computer Applications</option>
                            <option value="Artificial Intelligence">Artificial Intelligence</option>
                            <option value="Mathematical Sciences">Mathematical Sciences</option>
                            <option value="Physical Sciences">Physical Sciences</option>
                        </select>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:16px;">
                        <button type="button" class="btn-outline" onclick="closeModal('create-modal')">Cancel</button>
                        <button type="submit" class="btn-success">Save Student</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================================== -->
        <!-- EDIT STUDENT MODAL (ADMIN ONLY)                            -->
        <!-- ========================================================== -->
        <div id="edit-modal" class="modal-overlay" onclick="handleBackdropClick(event, 'edit-modal')">
            <div class="modal-box">
                <div class="modal-header">
                    <h3>Edit Student Record</h3>
                    <button type="button" class="modal-close-btn" onclick="closeModal('edit-modal')">&times;</button>
                </div>

                <form method="POST" action="students.php" onsubmit="return validateEditForm(this)">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" id="edit_student_id_hidden" name="student_id" value="">

                    <div class="form-grid-2">
                        <div class="form-field">
                            <label>Student ID</label>
                            <input
                                type="text"
                                id="edit_student_id_display"
                                disabled
                                style="cursor:not-allowed; opacity:0.75;"
                            >
                        </div>

                        <div class="form-field">
                            <label for="edit_full_name">Full Name *</label>
                            <input
                                type="text"
                                id="edit_full_name"
                                name="full_name"
                                required
                                minlength="3"
                            >
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-field">
                            <label for="edit_email">Email Address *</label>
                            <input
                                type="email"
                                id="edit_email"
                                name="email"
                                required
                            >
                        </div>

                        <div class="form-field">
                            <label for="edit_mobile">Mobile Number *</label>
                            <input
                                type="tel"
                                id="edit_mobile"
                                name="mobile"
                                required
                                pattern="^[6-9]\d{9}$"
                                title="Valid 10-digit mobile number starting with 6, 7, 8, or 9"
                            >
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-field">
                            <label for="edit_course">Course *</label>
                            <select id="edit_course" name="course" required>
                                <option value="B.Tech">B.Tech</option>
                                <option value="BCA">BCA</option>
                                <option value="MCA">MCA</option>
                                <option value="B.Sc">B.Sc</option>
                                <option value="M.Tech">M.Tech</option>
                            </select>
                        </div>

                        <div class="form-field">
                            <label for="edit_semester">Semester *</label>
                            <select id="edit_semester" name="semester" required>
                                <?php for ($i = 1; $i <= 8; $i++): ?>
                                    <option value="<?= $i ?>">Semester <?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-field">
                        <label for="edit_department">Department *</label>
                        <select id="edit_department" name="department" required>
                            <option value="Information Technology">Information Technology</option>
                            <option value="Computer Science">Computer Science</option>
                            <option value="Computer Engineering">Computer Engineering</option>
                            <option value="Computer Applications">Computer Applications</option>
                            <option value="Artificial Intelligence">Artificial Intelligence</option>
                            <option value="Mathematical Sciences">Mathematical Sciences</option>
                            <option value="Physical Sciences">Physical Sciences</option>
                        </select>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:16px;">
                        <button type="button" class="btn-outline" onclick="closeModal('edit-modal')">Cancel</button>
                        <button type="submit">Update Details</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================================== -->
        <!-- DELETE CONFIRMATION MODAL (ADMIN ONLY)                     -->
        <!-- ========================================================== -->
        <div id="delete-modal" class="modal-overlay" onclick="handleBackdropClick(event, 'delete-modal')">
            <div class="modal-box" style="max-width: 440px;">
                <div class="modal-header">
                    <h3 style="color:var(--danger);">Confirm Student Deletion</h3>
                    <button type="button" class="modal-close-btn" onclick="closeModal('delete-modal')">&times;</button>
                </div>

                <p style="font-size:14px; margin-bottom:12px;">
                    Are you sure you want to permanently delete the student record for:
                </p>
                <div style="background:var(--surface-muted); padding:12px; border-radius:var(--radius-sm); border:1px solid var(--border); margin-bottom:16px;">
                    <b id="delete-student-name" style="font-size:15px; color:var(--text);"></b>
                    <div style="font-size:12.5px; color:var(--text-muted); margin-top:2px;">
                        Student ID: <code id="delete-student-id-display"></code>
                    </div>
                </div>

                <p style="font-size:12.5px; color:var(--danger); margin-bottom:20px;">
                    This action will permanently remove this student and cannot be undone.
                </p>

                <form method="POST" action="students.php">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" id="delete_student_id_input" name="student_id" value="">

                    <div style="display:flex; justify-content:flex-end; gap:8px;">
                        <button type="button" class="btn-outline" onclick="closeModal('delete-modal')">Cancel</button>
                        <button type="submit" class="btn-danger">Confirm Delete</button>
                    </div>
                </form>
            </div>
        </div>
    <!-- Modal Management Scripts (Admin Only) -->
    <script>
        function openCreateModal() {
            const modal = document.getElementById('create-modal');
            if (modal) {
                modal.classList.add('active');
                const idInput = document.getElementById('create_student_id');
                if (idInput) idInput.focus();
            }
        }

        function openEditModal(student) {
            const modal = document.getElementById('edit-modal');
            if (!modal) return;

            document.getElementById('edit_student_id_hidden').value  = student.student_id;
            document.getElementById('edit_student_id_display').value = student.student_id;
            document.getElementById('edit_full_name').value           = student.full_name;
            document.getElementById('edit_email').value               = student.email;
            document.getElementById('edit_mobile').value              = student.mobile;
            document.getElementById('edit_course').value              = student.course;
            document.getElementById('edit_semester').value            = student.semester;
            document.getElementById('edit_department').value          = student.department;

            modal.classList.add('active');
        }

        function openDeleteModal(studentId, fullName) {
            const modal = document.getElementById('delete-modal');
            if (!modal) return;

            document.getElementById('delete-student-name').textContent           = fullName;
            document.getElementById('delete-student-id-display').textContent    = studentId;
            document.getElementById('delete_student_id_input').value            = studentId;

            modal.classList.add('active');
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('active');
            }
        }

        function handleBackdropClick(event, modalId) {
            if (event.target.id === modalId) {
                closeModal(modalId);
            }
        }

        // Close on Escape key press
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                ['create-modal', 'edit-modal', 'delete-modal'].forEach(closeModal);
            }
        });

        // Client-side quick validations
        function validateCreateForm(form) {
            const id = form.student_id.value.trim();
            const name = form.full_name.value.trim();
            const email = form.email.value.trim();
            const mobile = form.mobile.value.trim();

            if (id.length < 4) {
                alert('Student ID must be at least 4 characters long.');
                return false;
            }
            if (name.length < 3) {
                alert('Full Name must be at least 3 characters.');
                return false;
            }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                alert('Please enter a valid email address.');
                return false;
            }
            if (!/^[6-9]\d{9}$/.test(mobile)) {
                alert('Mobile number must be a valid 10-digit number starting with 6, 7, 8, or 9.');
                return false;
            }
            return true;
        }

        function validateEditForm(form) {
            const name = form.full_name.value.trim();
            const email = form.email.value.trim();
            const mobile = form.mobile.value.trim();

            if (name.length < 3) {
                alert('Full Name must be at least 3 characters.');
                return false;
            }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                alert('Please enter a valid email address.');
                return false;
            }
            if (!/^[6-9]\d{9}$/.test(mobile)) {
                alert('Mobile number must be a valid 10-digit number.');
                return false;
            }
            return true;
        }
    </script>
    <?php endif; ?>
</body>
</html>
