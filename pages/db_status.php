<?php
/**
 * StudentHub: Database & System Status
 * Verifies live MySQL PDO connectivity and schema health
 */
require_once __DIR__ . '/../scripts/db.php';
require_once __DIR__ . '/../scripts/auth.php';

$isAdmin = isAdmin();

try {
    $dbVersion    = $pdo->query('SELECT VERSION()')->fetchColumn();
    $userCount    = $pdo->query('SELECT COUNT(*) FROM `users`')->fetchColumn();
    $studentCount = $pdo->query('SELECT COUNT(*) FROM `students`')->fetchColumn();
    $eventCount   = $pdo->query('SELECT COUNT(*) FROM `events`')->fetchColumn();
    $regCount     = $pdo->query('SELECT COUNT(*) FROM `registrations`')->fetchColumn();
    $auditCount   = $pdo->query('SELECT COUNT(*) FROM `audit_logs`')->fetchColumn();

    $stmt = $pdo->prepare('SELECT `student_id`, `full_name`, `email`, `department`, `course`, `semester` FROM `students` LIMIT 5');
    $stmt->execute();
    $students = $stmt->fetchAll();

    $stmtEvt = $pdo->query('SELECT `event_id`, `event_name`, `category`, `event_date`, `venue`, `status`, `poster` FROM `events` ORDER BY `event_date` ASC LIMIT 5');
    $previewEvents = $stmtEvt->fetchAll();
} catch (PDOException $e) {
    die('Database Error: ' . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Database Status — StudentHub</title>
    <script src="../scripts/theme.js"></script>
    <link rel="icon" type="image/png" href="../images/favicon.png">
    <link rel="stylesheet" href="portal.css">
    <link rel="stylesheet" href="darkmode.css">
</head>
<body>

    <!-- Top Bar -->
    <table border="1" width="100%" class="topbar">
        <tr>
            <td width="15%"><img src="../images/logo.jpg" alt="Logo" style="height:60px;width:60px;"></td>
            <td width="55%">
                <h1 style="margin:0;">DATABASE STATUS</h1>
                <p style="margin:0;color:var(--text-muted);font-size:13px;">MySQL PDO Connectivity &amp; Schema Health</p>
            </td>
            <td width="35%" align="right">
                <a href="home.php"><button class="btn-outline" type="button">Dashboard</button></a>
                &nbsp;
                <a href="events.php"><button class="btn-outline" type="button">Events</button></a>
                &nbsp;
                <a href="students.php"><button class="btn-outline" type="button">Students</button></a>
                <?php if ($isAdmin): ?>
                    &nbsp;
                    <a href="admin_dashboard.php"><button class="btn-outline" type="button">Admin Panel</button></a>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <br>

    <!-- Connection Summary -->
    <table border="1" width="100%" cellpadding="12">
        <tr>
            <td>
                <b>Database Status:</b> <span class="badge badge-success">&#10004; Connected</span>
                &nbsp;&bull;&nbsp; <b>Host:</b> localhost
                &nbsp;&bull;&nbsp; <b>Database:</b> studenthub
                &nbsp;&bull;&nbsp; <b>Server:</b> MySQL (<?= htmlspecialchars($dbVersion) ?>)
            </td>
        </tr>
    </table>

    <br>

    <!-- Schema Counts -->
    <table border="1" width="100%" cellpadding="12" style="text-align:center;">
        <tr>
            <td width="20%">
                <div style="font-size:12px;color:var(--text-muted);font-weight:600;">USER ACCOUNTS</div>
                <div style="font-size:1.8rem;font-weight:700;color:var(--primary);"><?= (int)$userCount ?></div>
            </td>
            <td width="20%">
                <div style="font-size:12px;color:var(--text-muted);font-weight:600;">STUDENTS ENROLLED</div>
                <div style="font-size:1.8rem;font-weight:700;color:var(--primary);"><?= (int)$studentCount ?></div>
            </td>
            <td width="20%">
                <div style="font-size:12px;color:var(--text-muted);font-weight:600;">CAMPUS EVENTS</div>
                <div style="font-size:1.8rem;font-weight:700;color:var(--primary);"><?= (int)$eventCount ?></div>
            </td>
            <td width="20%">
                <div style="font-size:12px;color:var(--text-muted);font-weight:600;">REGISTRATIONS</div>
                <div style="font-size:1.8rem;font-weight:700;color:var(--primary);"><?= (int)$regCount ?></div>
            </td>
            <td width="20%">
                <div style="font-size:12px;color:var(--text-muted);font-weight:600;">AUDIT LOGS</div>
                <div style="font-size:1.8rem;font-weight:700;color:var(--danger);"><?= (int)$auditCount ?></div>
            </td>
        </tr>
    </table>

    <br>

    <!-- Students Table Preview -->
    <h3>Enrolled Students (<code>students</code>)</h3>
    <table border="1" width="100%" cellpadding="8">
        <thead>
            <tr>
                <th>Student ID</th>
                <th>Full Name</th>
                <th>Email Address</th>
                <th>Department</th>
                <th>Course</th>
                <th>Semester</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($students as $s): ?>
                <tr>
                    <td><code><?= htmlspecialchars($s['student_id']) ?></code></td>
                    <td><b><?= htmlspecialchars($s['full_name']) ?></b></td>
                    <td><?= htmlspecialchars($s['email']) ?></td>
                    <td><?= htmlspecialchars($s['department']) ?></td>
                    <td><?= htmlspecialchars($s['course']) ?></td>
                    <td><span class="badge badge-info">Sem <?= (int)$s['semester'] ?></span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <br>

    <!-- Campus Events Table Preview (Practical 12) -->
    <h3>Campus Events (<code>events</code>)</h3>
    <table border="1" width="100%" cellpadding="8">
        <thead>
            <tr>
                <th>Event ID</th>
                <th>Event Title</th>
                <th>Category</th>
                <th>Event Date</th>
                <th>Venue</th>
                <th>Status</th>
                <th>Poster</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($previewEvents as $e): ?>
                <tr>
                    <td><code>#<?= (int)$e['event_id'] ?></code></td>
                    <td><b><?= htmlspecialchars($e['event_name']) ?></b></td>
                    <td><span class="badge badge-info"><?= htmlspecialchars($e['category']) ?></span></td>
                    <td><?= htmlspecialchars($e['event_date']) ?></td>
                    <td><?= htmlspecialchars($e['venue']) ?></td>
                    <td>
                        <span class="badge <?= strtolower($e['status']) === 'open' ? 'badge-success' : 'badge-danger' ?>">
                            <?= htmlspecialchars($e['status']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if (!empty($e['poster'])): ?>
                            <span class="badge badge-success">&#10004; Uploaded</span>
                        <?php else: ?>
                            <span style="color:var(--text-muted); font-size:12px;">None</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

</body>
</html>
