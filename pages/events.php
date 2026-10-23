<?php
/**
 * StudentHub: Event Management Module (Practical 12)
 * Full CRUD operations with Poster Upload, MIME & Size File Validation,
 * Category & Status Filters, Role-Based Access Control, and MySQLi Prepared Statements.
 */

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once __DIR__ . '/../scripts/auth.php';
requireLogin(); // Enforce authentication

require_once __DIR__ . '/../scripts/db.php';
require_once __DIR__ . '/../scripts/events_helper.php';
require_once __DIR__ . '/../scripts/audit_helper.php';
require_once __DIR__ . '/../scripts/nav_helper.php';

$isAdmin   = isAdmin();
$userName  = htmlspecialchars($_SESSION['name'] ?? 'User', ENT_QUOTES, 'UTF-8');
$userRole  = htmlspecialchars($_SESSION['role'] ?? 'user', ENT_QUOTES, 'UTF-8');
$userEmail = htmlspecialchars($_SESSION['email'] ?? '', ENT_QUOTES, 'UTF-8');

// Directory for storing uploaded event posters
$uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'posters';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Flash messages from session (PRG pattern fallback)
$successMessage = $_SESSION['flash_success'] ?? '';
$errorMessage   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// =========================================================================
// Handle State-Changing Operations (CREATE, UPDATE, DELETE) via POST
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    $csrf   = $_POST['csrf_token'] ?? '';

    // 1. Strict Server-Side RBAC Enforcement: Only Admin can modify events
    if (!$isAdmin) {
        http_response_code(403);
        $_SESSION['flash_error'] = 'Access denied. Administrator privileges are required to modify events.';
        header('Location: events.php');
        exit;
    }

    // 2. Strict CSRF Token Validation
    if (!validateCsrfToken($csrf)) {
        http_response_code(400);
        $_SESSION['flash_error'] = 'Security validation failed (invalid or expired CSRF token). Please try again.';
        header('Location: events.php');
        exit;
    }

    // --- CREATE EVENT OPERATION ---
    if ($action === 'create') {
        $eventName   = trim(strip_tags($_POST['event_name'] ?? ''));
        $category    = trim(strip_tags($_POST['category'] ?? 'Technical'));
        $eventDate   = trim($_POST['event_date'] ?? '');
        $eventTime   = trim($_POST['event_time'] ?? '10:00');
        $venue       = trim(strip_tags($_POST['venue'] ?? ''));
        $status      = trim($_POST['status'] ?? 'Open');
        $description = trim(strip_tags($_POST['description'] ?? ''));

        $errors = [];

        // Field validations
        if (empty($eventName) || strlen($eventName) < 3 || strlen($eventName) > 150) {
            $errors[] = 'Event title is required and must be between 3 and 150 characters.';
        }
        $validCategories = ['Technical', 'Cultural', 'Sports', 'Literary', 'Academic', 'Workshop', 'Other'];
        if (!in_array($category, $validCategories, true)) {
            $errors[] = 'Please select a valid event category.';
        }
        if (empty($eventDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
            $errors[] = 'A valid event date (YYYY-MM-DD) is required.';
        }
        if (!empty($eventTime) && !preg_match('/^(?:2[0-3]|[01][0-9]):[0-5][0-9](?::[0-5][0-9])?$/', $eventTime)) {
            $errors[] = 'A valid event time (HH:MM) is required.';
        }
        if (empty($venue) || strlen($venue) < 2 || strlen($venue) > 100) {
            $errors[] = 'Venue is required (2–100 characters).';
        }
        if (!in_array($status, ['Open', 'Closed'], true)) {
            $errors[] = 'Event status must be either Open or Closed.';
        }

        // Format event_time for MySQL TIME column (HH:MM:SS)
        if (strlen($eventTime) === 5) {
            $eventTime .= ':00';
        } elseif (empty($eventTime)) {
            $eventTime = '10:00:00';
        }

        // Handle poster upload
        $newPoster = null;
        if (!empty($_FILES['poster']) && $_FILES['poster']['error'] !== UPLOAD_ERR_NO_FILE) {
            if (!handlePosterUpload($_FILES['poster'], $uploadDir, $newPoster, $errors)) {
                // Upload failed; error already appended to $errors
            }
        }

        if (empty($errors)) {
            try {
                // Insert using MySQLi Prepared Statement
                $stmt = $conn->prepare('
                    INSERT INTO `events` (`event_name`, `category`, `event_date`, `event_time`, `venue`, `description`, `poster`, `status`)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ');
                if (!$stmt) {
                    throw new Exception($conn->error);
                }

                $stmt->bind_param('ssssssss', $eventName, $category, $eventDate, $eventTime, $venue, $description, $newPoster, $status);
                $execOk = $stmt->execute();
                $newId  = $stmt->insert_id;
                $stmt->close();

                if ($execOk) {
                    // Audit Logging (Practical 14)
                    logAuditAction('CREATE_EVENT', 'event', (string)$newId, "Created event '{$eventName}' (#{$newId}), Category: {$category}, Date: {$eventDate}");

                    $_SESSION['flash_success'] = "Event '{$eventName}' (ID #{$newId}) created successfully!";
                    header('Location: events.php');
                    exit;
                } else {
                    $errors[] = 'Failed to insert event into database. Please try again.';
                }
            } catch (Exception $e) {
                // If database insert failed and a poster file was saved, remove orphan file
                if (!empty($newPoster)) {
                    $orphan = $uploadDir . DIRECTORY_SEPARATOR . $newPoster;
                    if (file_exists($orphan)) {
                        @unlink($orphan);
                    }
                }
                $errors[] = 'Database error: Unable to create event. Please try again.';
            }
        }

        if (!empty($errors)) {
            $_SESSION['flash_error'] = implode(' ', $errors);
            header('Location: events.php');
            exit;
        }
    }

    // --- UPDATE EVENT OPERATION ---
    if ($action === 'update') {
        $eventId     = (int)($_POST['event_id'] ?? 0);
        $eventName   = trim(strip_tags($_POST['event_name'] ?? ''));
        $category    = trim(strip_tags($_POST['category'] ?? 'Technical'));
        $eventDate   = trim($_POST['event_date'] ?? '');
        $eventTime   = trim($_POST['event_time'] ?? '10:00');
        $venue       = trim(strip_tags($_POST['venue'] ?? ''));
        $status      = trim($_POST['status'] ?? 'Open');
        $description = trim(strip_tags($_POST['description'] ?? ''));

        $errors = [];

        if ($eventId <= 0) {
            $errors[] = 'Invalid event ID provided for update.';
        }
        if (empty($eventName) || strlen($eventName) < 3 || strlen($eventName) > 150) {
            $errors[] = 'Event title is required and must be between 3 and 150 characters.';
        }
        $validCategories = ['Technical', 'Cultural', 'Sports', 'Literary', 'Academic', 'Workshop', 'Other'];
        if (!in_array($category, $validCategories, true)) {
            $errors[] = 'Please select a valid event category.';
        }
        if (empty($eventDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
            $errors[] = 'A valid event date (YYYY-MM-DD) is required.';
        }
        if (!empty($eventTime) && !preg_match('/^(?:2[0-3]|[01][0-9]):[0-5][0-9](?::[0-5][0-9])?$/', $eventTime)) {
            $errors[] = 'A valid event time (HH:MM) is required.';
        }
        if (empty($venue) || strlen($venue) < 2 || strlen($venue) > 100) {
            $errors[] = 'Venue is required (2–100 characters).';
        }
        if (!in_array($status, ['Open', 'Closed'], true)) {
            $errors[] = 'Event status must be either Open or Closed.';
        }

        if (strlen($eventTime) === 5) {
            $eventTime .= ':00';
        } elseif (empty($eventTime)) {
            $eventTime = '10:00:00';
        }

        if (empty($errors)) {
            try {
                // Fetch existing event to inspect current poster and verify existence
                $checkStmt = $conn->prepare('SELECT `event_id`, `event_name`, `poster` FROM `events` WHERE `event_id` = ?');
                $checkStmt->bind_param('i', $eventId);
                $checkStmt->execute();
                $existing = $checkStmt->get_result()->fetch_assoc();
                $checkStmt->close();

                if (!$existing) {
                    $errors[] = "Event #{$eventId} does not exist.";
                } else {
                    $currentPoster  = $existing['poster'];
                    $posterToSave   = $currentPoster; // Preserves existing poster by default
                    $newPosterUploaded = false;

                    // Handle replacement poster upload if a new file was selected
                    if (!empty($_FILES['poster']) && $_FILES['poster']['error'] !== UPLOAD_ERR_NO_FILE) {
                        $uploadedName = null;
                        if (handlePosterUpload($_FILES['poster'], $uploadDir, $uploadedName, $errors)) {
                            if (!empty($uploadedName)) {
                                $posterToSave      = $uploadedName;
                                $newPosterUploaded = true;
                            }
                        }
                    }

                    if (empty($errors)) {
                        // Update using MySQLi Prepared Statement
                        $updateStmt = $conn->prepare('
                            UPDATE `events`
                            SET `event_name` = ?,
                                `category`   = ?,
                                `event_date` = ?,
                                `event_time` = ?,
                                `venue`      = ?,
                                `description`= ?,
                                `poster`     = ?,
                                `status`     = ?
                            WHERE `event_id` = ?
                        ');
                        if (!$updateStmt) {
                            throw new Exception($conn->error);
                        }

                        $updateStmt->bind_param('ssssssssi', $eventName, $category, $eventDate, $eventTime, $venue, $description, $posterToSave, $status, $eventId);
                        $execOk = $updateStmt->execute();
                        $updateStmt->close();

                        if ($execOk) {
                            // If a new poster was successfully assigned, clean up the old file safely
                            if ($newPosterUploaded && !empty($currentPoster) && $currentPoster !== $posterToSave) {
                                safelyDeletePosterFile($conn, $currentPoster, $uploadDir, $eventId);
                            }

                            // Audit Logging (Practical 14)
                            logAuditAction('UPDATE_EVENT', 'event', (string)$eventId, "Updated event '{$eventName}' (#{$eventId}), Category: {$category}, Status: {$status}");

                            $_SESSION['flash_success'] = "Event '{$eventName}' (#{$eventId}) updated successfully!";
                            header('Location: events.php');
                            exit;
                        } else {
                            $errors[] = 'Failed to execute event update in database.';
                        }
                    }
                }
            } catch (Exception $e) {
                // If update failed and a new poster was uploaded, remove newly saved file
                if (isset($newPosterUploaded) && $newPosterUploaded && !empty($posterToSave)) {
                    $orphan = $uploadDir . DIRECTORY_SEPARATOR . $posterToSave;
                    if (file_exists($orphan)) {
                        @unlink($orphan);
                    }
                }
                $errors[] = 'Database update error: Please check your entries and try again.';
            }
        }

        if (!empty($errors)) {
            $_SESSION['flash_error'] = implode(' ', $errors);
            header('Location: events.php');
            exit;
        }
    }

    // --- DELETE EVENT OPERATION ---
    if ($action === 'delete') {
        $eventId = (int)($_POST['event_id'] ?? 0);

        if ($eventId <= 0) {
            $_SESSION['flash_error'] = 'Invalid event ID provided for deletion.';
            header('Location: events.php');
            exit;
        }

        try {
            // Retrieve event information and poster prior to deletion
            $fetchStmt = $conn->prepare('SELECT `event_id`, `event_name`, `poster` FROM `events` WHERE `event_id` = ?');
            $fetchStmt->bind_param('i', $eventId);
            $fetchStmt->execute();
            $eventRecord = $fetchStmt->get_result()->fetch_assoc();
            $fetchStmt->close();

            if (!$eventRecord) {
                $_SESSION['flash_error'] = "Event #{$eventId} does not exist or has already been deleted.";
            } else {
                $posterToDelete = $eventRecord['poster'];
                $eventNameDel   = $eventRecord['event_name'];

                // Execute DELETE using MySQLi Prepared Statement
                $delStmt = $conn->prepare('DELETE FROM `events` WHERE `event_id` = ?');
                $delStmt->bind_param('i', $eventId);
                $delOk = $delStmt->execute();
                $delStmt->close();

                if ($delOk) {
                    // Remove physical poster file only after successful database deletion
                    if (!empty($posterToDelete)) {
                        safelyDeletePosterFile($conn, $posterToDelete, $uploadDir);
                    }

                    // Audit Logging (Practical 14)
                    logAuditAction('DELETE_EVENT', 'event', (string)$eventId, "Deleted event '{$eventNameDel}' (#{$eventId})");

                    $_SESSION['flash_success'] = "Event '{$eventNameDel}' (#{$eventId}) has been permanently deleted.";
                } else {
                    $_SESSION['flash_error'] = 'Unable to delete event record due to database constraints.';
                }
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Database error occurred during event deletion.';
        }

        header('Location: events.php');
        exit;
    }
}

// =========================================================================
// READ / SEARCH / FILTER OPERATIONS (GET via MySQLi Prepared Statements)
// =========================================================================
$search         = trim($_GET['search'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');
$statusFilter   = trim($_GET['status'] ?? '');

$whereConditions = [];
$paramTypes      = '';
$bindParams      = [];

// 1. Keyword Search (matches event_name, venue, description)
if ($search !== '') {
    $whereConditions[] = '(`event_name` LIKE ? OR `venue` LIKE ? OR `description` LIKE ?)';
    $wild = '%' . $search . '%';
    $paramTypes   .= 'sss';
    $bindParams[] = $wild;
    $bindParams[] = $wild;
    $bindParams[] = $wild;
}

// 2. Category Filter
if ($categoryFilter !== '') {
    $whereConditions[] = '`category` = ?';
    $paramTypes   .= 's';
    $bindParams[] = $categoryFilter;
}

// 3. Status Filter (Open / Closed)
if ($statusFilter !== '') {
    $whereConditions[] = '`status` = ?';
    $paramTypes   .= 's';
    $bindParams[] = $statusFilter;
}

// Assemble query using MySQLi Prepared Statement
$query = 'SELECT `event_id`, `event_name`, `category`, `event_date`, `event_time`, `venue`, `description`, `poster`, `status`, `created_at`, `updated_at` FROM `events`';
if (!empty($whereConditions)) {
    $query .= ' WHERE ' . implode(' AND ', $whereConditions);
}
$query .= ' ORDER BY `event_date` ASC, `event_time` ASC';

$stmt = $conn->prepare($query);
if (!$stmt) {
    die('Database query preparation failed: ' . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8'));
}

if (!empty($bindParams)) {
    $stmt->bind_param($paramTypes, ...$bindParams);
}

$stmt->execute();
$eventsResult = $stmt->get_result();
$eventsList   = $eventsResult->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch distinct categories for filter dropdown using MySQLi
$catQuery = $conn->query('SELECT DISTINCT `category` FROM `events` WHERE `category` IS NOT NULL AND `category` != "" ORDER BY `category` ASC');
$allCategories = [];
if ($catQuery) {
    while ($row = $catQuery->fetch_assoc()) {
        $allCategories[] = $row['category'];
    }
    $catQuery->free();
}

$csrfToken = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Management — StudentHub</title>
    <script src="../scripts/theme.js"></script>
    <link rel="icon" type="image/png" href="../images/favicon.png">
    <link rel="stylesheet" href="portal.css">
    <link rel="stylesheet" href="darkmode.css">
    <style>
        /* Modal Overlay & Styling */
        .modal-overlay {
            display: none !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            background: rgba(15, 23, 42, 0.72) !important;
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
            max-width: 620px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 24px 28px;
            box-sizing: border-box;
            animation: modalSlide 0.22s var(--ease);
        }
        @keyframes modalSlide {
            from { transform: translateY(16px); opacity: 0; }
            to   { transform: translateY(0); opacity: 1; }
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
            font-size: 1.25rem;
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
        .form-field select,
        .form-field textarea {
            width: 100%;
            box-sizing: border-box;
        }

        /* Filter Toolbar */
        .filter-toolbar {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 14px 18px;
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
            flex: 2;
            min-width: 200px;
        }
        .filter-form select {
            flex: 1;
            min-width: 130px;
        }

        /* Table & Action styling */
        .action-btns {
            display: inline-flex;
            gap: 6px;
        }
        .action-btns button {
            padding: 5px 12px;
            font-size: 12px;
            font-weight: 500;
        }
        .poster-thumb {
            width: 58px;
            height: 58px;
            border-radius: var(--radius-sm);
            object-fit: cover;
            border: 1px solid var(--border);
            background: var(--surface-muted);
            cursor: pointer;
            transition: var(--transition);
            display: block;
        }
        .poster-thumb:hover {
            transform: scale(1.06);
            border-color: var(--primary);
        }
        .poster-placeholder {
            width: 58px;
            height: 58px;
            border-radius: var(--radius-sm);
            border: 1px dashed var(--border-strong);
            background: var(--surface-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            font-size: 11px;
            font-weight: 600;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            line-height: 1.1;
            padding: 2px;
            box-sizing: border-box;
        }
        .preview-box {
            margin-top: 8px;
            border: 1px dashed var(--border-strong);
            border-radius: var(--radius-sm);
            padding: 8px;
            text-align: center;
            background: var(--surface-muted);
            display: none;
        }
        .preview-box img {
            max-height: 140px;
            max-width: 100%;
            border-radius: var(--radius-sm);
            object-fit: contain;
        }
        .status-badge-open {
            background: var(--success-bg);
            color: var(--success);
            border: 1px solid #86efac;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 99px;
            display: inline-block;
        }
        .status-badge-closed {
            background: var(--danger-bg);
            color: var(--danger);
            border: 1px solid #fca5a5;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 99px;
            display: inline-block;
        }
        .cat-badge {
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 4px;
            background: var(--primary-light);
            color: var(--primary-dark);
            border: 1px solid #bfdbfe;
        }
        .file-help {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 4px;
        }
        .modal-detail-img {
            max-width: 100%;
            max-height: 280px;
            border-radius: var(--radius-md);
            object-fit: contain;
            border: 1px solid var(--border);
            background: #000;
            display: block;
            margin: 0 auto 16px;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>

    <!-- Top Bar -->
    <table border="1" width="100%" class="topbar">
        <tr>
            <td width="12%">
                <img src="../images/logo.jpg" alt="StudentHub Logo" style="height:55px; width:55px; border-radius: var(--radius-md);">
            </td>
            <td width="63%">
                <h1 style="margin:0; font-size: 1.55rem;">EVENT MANAGEMENT</h1>
                <p style="margin:2px 0 0; color:var(--text-muted); font-size:13px;">
                    Practical 12 &amp; 13 &bull; PHP JSON API &amp; Asynchronous Fetch API CRUD &bull; Logged in as: <b><?= $userName ?></b>
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

    <!-- Main Layout: Sidebar & Content Area -->
    <table border="1" width="100%">
        <tr>
            <!-- Left Sidebar Navigation -->
            <td width="22%" valign="top" style="vertical-align: top; padding: 16px;">
                <?php renderSidebarNavigation('events'); ?>
            </td>

            <!-- Right Main Area -->
            <td width="78%" valign="top" style="vertical-align: top; padding: 20px;">

                <!-- UI Feedback Notifications Anchor (Practical 13) -->
                <div id="ajax-feedback">
                    <?php if (!empty($successMessage)): ?>
                        <div style="background:var(--success-bg); color:var(--success); padding:12px 16px; border:1px solid #86efac; border-radius:var(--radius-sm); margin-bottom:14px; font-weight:600;">
                            &#10004; <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errorMessage)): ?>
                        <div style="background:var(--danger-bg); color:var(--danger); padding:12px 16px; border:1px solid #fca5a5; border-radius:var(--radius-sm); margin-bottom:14px; font-size:13.5px;">
                            &#9888; <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Toolbar: Search, Filters & Add Event Action -->
                <div class="filter-toolbar">
                    <form id="filterForm" method="GET" action="events.php" class="filter-form">
                        <input
                            type="text"
                            id="search-input"
                            name="search"
                            value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="Search event name, venue, or keyword..."
                            aria-label="Search events"
                            autocomplete="off"
                        >

                        <select id="category-filter" name="category" aria-label="Filter by category">
                            <option value="">All Categories</option>
                            <?php foreach ($allCategories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>" <?= $categoryFilter === $cat ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select id="status-filter" name="status" aria-label="Filter by status">
                            <option value="">All Statuses</option>
                            <option value="Open" <?= $statusFilter === 'Open' ? 'selected' : '' ?>>Open Only</option>
                            <option value="Closed" <?= $statusFilter === 'Closed' ? 'selected' : '' ?>>Closed Only</option>
                        </select>

                        <button type="submit" style="padding: 8px 14px; font-size:13px;">Filter</button>
                        <button type="button" class="btn-secondary" onclick="resetFilters()" style="padding: 8px 14px; font-size:13px;">Reset</button>

                        <?php if ($isAdmin): ?>
                            <div style="margin-left: auto;">
                                <button type="button" class="btn-success" onclick="openCreateModal()" style="padding: 8px 16px; font-size:13px;">
                                    + Add New Event
                                </button>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Event Directory Table -->
                <table border="1" width="100%" cellpadding="10" style="margin-top: 10px;">
                    <thead>
                        <tr>
                            <th width="8%" style="text-align:center;">Poster</th>
                            <th width="28%">Event Title &amp; Category</th>
                            <th width="18%">Schedule</th>
                            <th width="18%">Venue</th>
                            <th width="10%" style="text-align:center;">Status</th>
                            <th width="18%" style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="events-table-body">
                        <?php if (empty($eventsList)): ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding: 36px 16px; color:var(--text-muted);">
                                    <div style="font-size: 2.2rem; margin-bottom: 8px;">&#128197;</div>
                                    <b style="font-size: 1.05rem; color:var(--text);">No events found matching your criteria.</b>
                                    <p style="margin: 6px 0 12px; font-size: 13px;">Try clearing filters or search keywords.</p>
                                    <a href="events.php"><button type="button" class="btn-outline" style="font-size: 12.5px;">View All Events</button></a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($eventsList as $evt): ?>
                                <?php
                                $evtId    = (int)$evt['event_id'];
                                $evtName  = htmlspecialchars($evt['event_name'], ENT_QUOTES, 'UTF-8');
                                $evtCat   = htmlspecialchars($evt['category'], ENT_QUOTES, 'UTF-8');
                                $evtDate  = htmlspecialchars($evt['event_date'], ENT_QUOTES, 'UTF-8');
                                $evtTime  = !empty($evt['event_time']) ? date('h:i A', strtotime($evt['event_time'])) : 'TBA';
                                $evtTimeRaw = !empty($evt['event_time']) ? substr($evt['event_time'], 0, 5) : '10:00';
                                $evtVenue = htmlspecialchars($evt['venue'], ENT_QUOTES, 'UTF-8');
                                $evtDesc  = htmlspecialchars($evt['description'] ?? '', ENT_QUOTES, 'UTF-8');
                                $evtStatus= htmlspecialchars($evt['status'] ?? 'Open', ENT_QUOTES, 'UTF-8');
                                $evtPoster= $evt['poster'] ?? '';
                                $posterUrl= !empty($evtPoster) ? '../uploads/posters/' . htmlspecialchars($evtPoster, ENT_QUOTES, 'UTF-8') : '';
                                ?>
                                <tr id="event-row-<?= $evtId ?>">
                                    <!-- Poster Thumbnail -->
                                    <td align="center" style="vertical-align: middle;">
                                        <?php if (!empty($posterUrl) && file_exists(dirname(__DIR__) . '/uploads/posters/' . $evtPoster)): ?>
                                            <img
                                                src="<?= $posterUrl ?>"
                                                alt="<?= $evtName ?> Poster"
                                                class="poster-thumb"
                                                onclick="openDetailModal(<?= $evtId ?>)"
                                                title="Click to view details"
                                            >
                                        <?php else: ?>
                                            <div class="poster-placeholder" title="No poster uploaded">
                                                No<br>Poster
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Event Title & Category -->
                                    <td>
                                        <b style="font-size: 14.5px; color:var(--text);"><?= $evtName ?></b>
                                        <div style="margin-top: 4px;">
                                             <span class="cat-badge"><?= $evtCat ?></span>
                                            <span style="font-size: 11px; color:var(--text-muted); margin-left:6px;">ID #<?= $evtId ?></span>
                                        </div>
                                    </td>

                                    <!-- Schedule: Date & Time -->
                                    <td>
                                        <div style="font-weight: 600;"><?= date('D, M j, Y', strtotime($evt['event_date'])) ?></div>
                                        <div style="font-size: 12px; color:var(--text-muted); margin-top: 2px;">
                                            &#128338; <?= $evtTime ?>
                                        </div>
                                    </td>

                                    <!-- Venue -->
                                    <td>
                                        <div style="font-size: 13.5px;">&#128205; <?= $evtVenue ?></div>
                                    </td>

                                    <!-- Status -->
                                    <td align="center">
                                        <?php if (strtolower($evtStatus) === 'open'): ?>
                                            <span class="status-badge-open">&#9679; Open</span>
                                        <?php else: ?>
                                            <span class="status-badge-closed">&#9679; Closed</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Actions -->
                                    <td align="center">
                                        <div class="action-btns">
                                            <button
                                                type="button"
                                                class="btn-outline"
                                                onclick="openDetailModal(<?= $evtId ?>)"
                                                title="View event details"
                                            >
                                                View
                                            </button>

                                            <?php if ($isAdmin): ?>
                                                <button
                                                    type="button"
                                                    onclick="openEditModal(<?= $evtId ?>)"
                                                    title="Edit event"
                                                >
                                                    Edit
                                                </button>
                                                <button
                                                    type="button"
                                                    class="btn-danger"
                                                    onclick="openDeleteModal(<?= $evtId ?>, '<?= addslashes($evt['event_name']) ?>')"
                                                    title="Delete event"
                                                >
                                                    Delete
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div style="margin-top: 14px; font-size: 12.5px; color:var(--text-muted); display:flex; justify-content:space-between; align-items:center;">
                    <div id="event-count-label">Showing <b><?= count($eventsList) ?></b> event(s) in system.</div>
                    <div>Practical 12 &amp; 13 &bull; PHP JSON API &amp; Asynchronous Fetch API CRUD</div>
                </div>

            </td>
        </tr>
    </table>

    <!-- ===================================================================== -->
    <!-- MODAL 1: CREATE NEW EVENT (Admin Only) -->
    <!-- ===================================================================== -->
    <?php if ($isAdmin): ?>
    <div id="createModal" class="modal-overlay" onclick="handleOverlayClick(event, 'createModal')">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Create New Campus Event</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('createModal')" aria-label="Close dialog">&times;</button>
            </div>
            <form id="createEventForm" method="POST" action="events.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                <div id="create-modal-error" style="display:none; background:var(--danger-bg); color:var(--danger); padding:8px 12px; border:1px solid #fca5a5; border-radius:var(--radius-sm); margin-bottom:12px; font-size:12.5px;"></div>

                <div class="form-field">
                    <label for="create_title">Event Title *</label>
                    <input type="text" id="create_title" name="event_name" required maxlength="150" placeholder="e.g., CHARUSAT AI Hackathon 2026">
                </div>

                <div class="form-grid-2">
                    <div class="form-field">
                        <label for="create_category">Category *</label>
                        <select id="create_category" name="category" required>
                            <option value="Technical">Technical</option>
                            <option value="Cultural">Cultural</option>
                            <option value="Sports">Sports</option>
                            <option value="Literary">Literary</option>
                            <option value="Academic">Academic</option>
                            <option value="Workshop">Workshop</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="form-field">
                        <label for="create_status">Status *</label>
                        <select id="create_status" name="status" required>
                            <option value="Open">Open (Registrations Active)</option>
                            <option value="Closed">Closed</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-field">
                        <label for="create_date">Event Date *</label>
                        <input type="date" id="create_date" name="event_date" required>
                    </div>

                    <div class="form-field">
                        <label for="create_time">Event Time *</label>
                        <input type="time" id="create_time" name="event_time" value="10:00" required>
                    </div>
                </div>

                <div class="form-field">
                    <label for="create_venue">Venue *</label>
                    <input type="text" id="create_venue" name="venue" required maxlength="100" placeholder="e.g., Central Auditorium / Lab 301">
                </div>

                <div class="form-field">
                    <label for="create_poster">Event Poster (Optional)</label>
                    <input
                        type="file"
                        id="create_poster"
                        name="poster"
                        accept="image/jpeg,image/png,image/webp"
                        onchange="previewImage(this, 'create_preview', 'create_preview_img')"
                    >
                    <div class="file-help">Accepted formats: JPG, PNG, WebP &bull; Maximum upload size: 2 MB</div>
                    <div id="create_preview" class="preview-box">
                        <p style="font-size:11.5px; font-weight:600; margin-bottom:4px; color:var(--text-muted);">Poster Preview:</p>
                        <img id="create_preview_img" src="" alt="Selected Poster Preview">
                    </div>
                </div>

                <div class="form-field">
                    <label for="create_description">Description (Optional)</label>
                    <textarea id="create_description" name="description" rows="3" placeholder="Provide event schedule, rules, eligibility, or guidelines..."></textarea>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:18px; border-top:1px solid var(--border); padding-top:14px;">
                    <button type="button" class="btn-secondary" onclick="closeModal('createModal')">Cancel</button>
                    <button type="submit" id="create_submit_btn" class="btn-success">Create Event</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- MODAL 2: EDIT EVENT (Admin Only) -->
    <!-- ===================================================================== -->
    <div id="editModal" class="modal-overlay" onclick="handleOverlayClick(event, 'editModal')">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Edit Campus Event</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('editModal')" aria-label="Close dialog">&times;</button>
            </div>
            <form id="editEventForm" method="POST" action="events.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" id="edit_event_id" name="event_id" value="">

                <div id="edit-modal-error" style="display:none; background:var(--danger-bg); color:var(--danger); padding:8px 12px; border:1px solid #fca5a5; border-radius:var(--radius-sm); margin-bottom:12px; font-size:12.5px;"></div>

                <div class="form-field">
                    <label for="edit_title">Event Title *</label>
                    <input type="text" id="edit_title" name="event_name" required maxlength="150">
                </div>

                <div class="form-grid-2">
                    <div class="form-field">
                        <label for="edit_category">Category *</label>
                        <select id="edit_category" name="category" required>
                            <option value="Technical">Technical</option>
                            <option value="Cultural">Cultural</option>
                            <option value="Sports">Sports</option>
                            <option value="Literary">Literary</option>
                            <option value="Academic">Academic</option>
                            <option value="Workshop">Workshop</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="form-field">
                        <label for="edit_status">Status *</label>
                        <select id="edit_status" name="status" required>
                            <option value="Open">Open</option>
                            <option value="Closed">Closed</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-field">
                        <label for="edit_date">Event Date *</label>
                        <input type="date" id="edit_date" name="event_date" required>
                    </div>

                    <div class="form-field">
                        <label for="edit_time">Event Time *</label>
                        <input type="time" id="edit_time" name="event_time" required>
                    </div>
                </div>

                <div class="form-field">
                    <label for="edit_venue">Venue *</label>
                    <input type="text" id="edit_venue" name="venue" required maxlength="100">
                </div>

                <div class="form-field">
                    <label for="edit_poster">Replace Event Poster (Optional)</label>
                    <div id="current_poster_container" style="margin-bottom:8px; font-size:12px; color:var(--text-muted);">
                        Current Poster: <span id="current_poster_label" style="font-weight:600; color:var(--text);">None</span>
                    </div>
                    <input
                        type="file"
                        id="edit_poster"
                        name="poster"
                        accept="image/jpeg,image/png,image/webp"
                        onchange="previewImage(this, 'edit_preview', 'edit_preview_img')"
                    >
                    <div class="file-help">Leave empty to keep current poster &bull; Max 2 MB (JPG, PNG, WebP)</div>
                    <div id="edit_preview" class="preview-box">
                        <p style="font-size:11.5px; font-weight:600; margin-bottom:4px; color:var(--text-muted);">New Poster Preview:</p>
                        <img id="edit_preview_img" src="" alt="Replacement Poster Preview">
                    </div>
                </div>

                <div class="form-field">
                    <label for="edit_description">Description (Optional)</label>
                    <textarea id="edit_description" name="description" rows="3"></textarea>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:18px; border-top:1px solid var(--border); padding-top:14px;">
                    <button type="button" class="btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" id="edit_submit_btn">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- MODAL 3: DELETE CONFIRMATION (Admin Only) -->
    <!-- ===================================================================== -->
    <div id="deleteModal" class="modal-overlay" onclick="handleOverlayClick(event, 'deleteModal')">
        <div class="modal-box" style="max-width: 440px;">
            <div class="modal-header">
                <h3 style="color:var(--danger);">&#9888; Confirm Event Deletion</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('deleteModal')" aria-label="Close dialog">&times;</button>
            </div>
            <p style="font-size:14px; margin-bottom:12px;">Are you sure you want to permanently delete this event?</p>
            <div style="background:var(--danger-bg); border:1px solid #fca5a5; padding:12px 14px; border-radius:var(--radius-sm); margin-bottom:16px;">
                <b id="delete_event_title" style="color:var(--danger); font-size:14px;"></b>
                <div id="delete_event_id_label" style="font-size:12px; color:var(--text-muted); margin-top:3px;"></div>
            </div>
            <p style="font-size:12.5px; color:var(--text-muted); margin-bottom:18px;">
                This action is permanent and cannot be undone. Any associated poster image will also be safely removed if no other event uses it.
            </p>
            <form id="deleteEventForm" method="POST" action="events.php">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" id="delete_event_id" name="event_id" value="">

                <div id="delete-modal-error" style="display:none; background:var(--danger-bg); color:var(--danger); padding:8px 12px; border:1px solid #fca5a5; border-radius:var(--radius-sm); margin-bottom:12px; font-size:12.5px;"></div>

                <div style="display:flex; justify-content:flex-end; gap:10px;">
                    <button type="button" class="btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                    <button type="submit" id="delete_submit_btn" class="btn-danger">Permanently Delete</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===================================================================== -->
    <!-- MODAL 4: EVENT DETAILS (Visible to all authenticated users) -->
    <!-- ===================================================================== -->
    <div id="detailModal" class="modal-overlay" onclick="handleOverlayClick(event, 'detailModal')">
        <div class="modal-box" style="max-width: 580px;">
            <div class="modal-header">
                <h3 id="detail_title">Event Details</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('detailModal')" aria-label="Close dialog">&times;</button>
            </div>
            <div id="detail_content">
                <div id="detail_poster_wrap" style="text-align:center; display:none;">
                    <img id="detail_poster" src="" alt="Event Poster" class="modal-detail-img">
                </div>

                <table border="1" width="100%" cellpadding="8" style="margin-bottom:14px;">
                    <tr>
                        <td width="30%"><b>Category:</b></td>
                        <td width="70%"><span id="detail_category" class="cat-badge"></span></td>
                    </tr>
                    <tr>
                        <td><b>Status:</b></td>
                        <td><span id="detail_status"></span></td>
                    </tr>
                    <tr>
                        <td><b>Date &amp; Time:</b></td>
                        <td id="detail_schedule"></td>
                    </tr>
                    <tr>
                        <td><b>Venue:</b></td>
                        <td id="detail_venue"></td>
                    </tr>
                    <tr>
                        <td><b>Description:</b></td>
                        <td id="detail_description" style="line-height:1.5;"></td>
                    </tr>
                    <tr>
                        <td><b>Created / Updated:</b></td>
                        <td id="detail_timestamps" style="font-size:12px; color:var(--text-muted);"></td>
                    </tr>
                </table>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:14px; border-top:1px solid var(--border); padding-top:12px;">
                    <a href="Event_registration.php"><button type="button" style="padding:6px 14px; font-size:12.5px;">Register For This Event</button></a>
                    <button type="button" class="btn-secondary" onclick="closeModal('detailModal')" style="padding:6px 14px; font-size:12.5px;">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Practical 13 Asynchronous Fetch API CRUD Integration -->
    <script>
        const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;
        const IS_ADMIN   = <?= $isAdmin ? 'true' : 'false' ?>;
    </script>
    <script src="../scripts/events.js"></script>
</body>
</html>
