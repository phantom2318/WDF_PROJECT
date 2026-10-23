<?php
/**
 * StudentHub: Administrator Dashboard (Practical 14)
 * Role-Based Access Control, Dynamic Statistics, Recent Registrations, Audit Log, and Live Fetch Refresh.
 */

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once __DIR__ . '/../scripts/auth.php';
requireAdmin(); // Enforce strict server-side RBAC: role === 'admin'

require_once __DIR__ . '/../scripts/db.php';
require_once __DIR__ . '/../scripts/audit_helper.php';
require_once __DIR__ . '/../scripts/nav_helper.php';

$adminName  = htmlspecialchars($_SESSION['name'] ?? 'Administrator', ENT_QUOTES, 'UTF-8');
$adminEmail = htmlspecialchars($_SESSION['email'] ?? '', ENT_QUOTES, 'UTF-8');
$adminRole  = htmlspecialchars($_SESSION['role'] ?? 'admin', ENT_QUOTES, 'UTF-8');

// Flash messages from session
$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Query Database for Statistics
$dataError = null;
try {
    // 1. User Statistics
    $userCountStmt = $pdo->query('
        SELECT 
            COUNT(*) AS total_users,
            SUM(CASE WHEN `role` = "admin" THEN 1 ELSE 0 END) AS total_admins,
            SUM(CASE WHEN `role` != "admin" THEN 1 ELSE 0 END) AS total_normal_users
        FROM `users`
    ');
    $userStats = $userCountStmt->fetch() ?: ['total_users' => 0, 'total_admins' => 0, 'total_normal_users' => 0];

    // 2. Student Statistics
    $totalStudents = (int)$pdo->query('SELECT COUNT(*) FROM `students`')->fetchColumn();

    // 3. Event Statistics
    $eventCountStmt = $pdo->query('
        SELECT 
            COUNT(*) AS total_events,
            SUM(CASE WHEN `status` = "Open" THEN 1 ELSE 0 END) AS open_events,
            SUM(CASE WHEN `status` = "Closed" THEN 1 ELSE 0 END) AS closed_events
        FROM `events`
    ');
    $eventStats = $eventCountStmt->fetch() ?: ['total_events' => 0, 'open_events' => 0, 'closed_events' => 0];

    // 4. Registration Statistics
    $regCountStmt = $pdo->query('
        SELECT 
            COUNT(*) AS total_registrations,
            SUM(CASE WHEN `status` = "Confirmed" THEN 1 ELSE 0 END) AS confirmed_registrations,
            SUM(CASE WHEN `status` = "Pending" THEN 1 ELSE 0 END) AS pending_registrations
        FROM `registrations`
    ');
    $regStats = $regCountStmt->fetch() ?: ['total_registrations' => 0, 'confirmed_registrations' => 0, 'pending_registrations' => 0];

    // 5. Latest Event Registrations (Limit 10)
    $latestRegStmt = $pdo->query('
        SELECT 
            r.`reg_id`,
            r.`student_id`,
            COALESCE(s.`full_name`, "Unknown Student") AS `student_name`,
            COALESCE(s.`course`, "—") AS `student_course`,
            r.`event_id`,
            COALESCE(e.`event_name`, "Unknown Event") AS `event_name`,
            COALESCE(e.`category`, "General") AS `event_category`,
            r.`status`,
            r.`registered_at`
        FROM `registrations` r
        LEFT JOIN `students` s ON r.`student_id` = s.`student_id`
        LEFT JOIN `events` e ON r.`event_id` = e.`event_id`
        ORDER BY r.`registered_at` DESC, r.`reg_id` DESC
        LIMIT 10
    ');
    $latestRegistrations = $latestRegStmt->fetchAll();

    // 6. Recent Audit Trail Entries (Limit 15)
    $recentAuditLogs = getRecentAuditLogs(15);
    $totalAuditLogs  = getAuditLogCount();

    // 7. Registered User Accounts Overview
    $usersStmt = $pdo->query('SELECT `user_id`, `full_name`, `email`, `role`, `created_at` FROM `users` ORDER BY `user_id` ASC');
    $allUsers  = $usersStmt->fetchAll();

} catch (PDOException $e) {
    $dataError = 'Database query failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    $userStats = ['total_users' => 0, 'total_admins' => 0, 'total_normal_users' => 0];
    $totalStudents = 0;
    $eventStats = ['total_events' => 0, 'open_events' => 0, 'closed_events' => 0];
    $regStats = ['total_registrations' => 0, 'confirmed_registrations' => 0, 'pending_registrations' => 0];
    $latestRegistrations = [];
    $recentAuditLogs = [];
    $totalAuditLogs = 0;
    $allUsers = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — StudentHub</title>
    <script src="../scripts/theme.js"></script>
    <link rel="icon" type="image/png" href="../images/favicon.png">
    <link rel="stylesheet" href="portal.css">
    <link rel="stylesheet" href="darkmode.css">
    <style>
        /* Responsive Stat Cards Grid */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 16px 18px;
            box-shadow: var(--shadow-xs);
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary);
        }
        .stat-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 6px;
            letter-spacing: -0.5px;
        }
        .stat-subtext {
            font-size: 12px;
            color: var(--text-muted);
        }
        .action-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-bottom: 20px;
            border-radius: var(--radius-lg);
        }
        .audit-action-badge {
            font-size: 10.5px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 4px;
            font-family: monospace;
            display: inline-block;
        }
        .act-create { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
        .act-update { background: #e0f2fe; color: #0369a1; border: 1px solid #7dd3fc; }
        .act-delete { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
        .act-login  { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        .act-system { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
        .live-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #22c55e;
            margin-right: 4px;
            box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.2);
            animation: pulse 1.8s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(1.15); }
        }
        @media (max-width: 768px) {
            .stat-grid { grid-template-columns: 1fr; }
            .action-toolbar { justify-content: flex-start; }
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
            <td width="53%">
                <h1 style="margin:0; font-size: 1.55rem;">ADMIN DASHBOARD</h1>
                <p style="margin:2px 0 0; color:var(--text-muted); font-size:13px;">
                    Practical 14 &bull; Role-Based Access Control &bull; Logged in as: <b><?= $adminName ?></b>
                    (<span class="badge badge-danger"><?= ucfirst($adminRole) ?></span>)
                </p>
            </td>
            <td width="35%" align="right">
                <div class="action-toolbar" style="justify-content: flex-end;">
                    <button class="btn-outline" type="button" id="btn-refresh-data" onclick="refreshDashboardData()">
                        <span id="refresh-spinner" style="display:none;">⏳</span>
                        <span>🔄 Refresh Data</span>
                    </button>
                    <a href="home.php"><button class="btn-outline" type="button">Student View</button></a>
                    <a href="logout.php"><button class="btn-danger" type="button">Logout</button></a>
                </div>
            </td>
        </tr>
    </table>

    <br>

    <!-- Main Content & Sidebar Layout -->
    <table border="1" width="100%">
        <tr>
            <!-- Left Sidebar: Dynamic Role-Based Menu -->
            <td width="22%" valign="top" style="vertical-align: top; padding: 16px;">
                <?php renderSidebarNavigation('admin_dashboard'); ?>
            </td>

            <!-- Right Main Workspace -->
            <td width="78%" valign="top" style="vertical-align: top; padding: 20px;">

                <!-- Status Feedback Alerts -->
                <div id="live-alert" style="display:none;"></div>

                <?php if (!empty($flashSuccess)): ?>
                    <div class="alert alert-success">
                        <span>✓ <?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($flashError) || !empty($dataError)): ?>
                    <div class="alert alert-danger">
                        <span>⚠️ <?= htmlspecialchars($flashError ?: $dataError, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>

                <!-- Section 1: Dashboard Overview Header -->
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:8px;">
                    <div>
                        <h2 style="margin:0; font-size:1.35rem;">System Overview &amp; Key Metrics</h2>
                        <p style="color:var(--text-muted); font-size:13px; margin:2px 0 0;">
                            <span class="live-indicator"></span> Live MySQL metrics aggregated in real-time
                        </p>
                    </div>
                    <div style="font-size:12px; color:var(--text-muted);" id="last-updated-text">
                        Last synced: <b><?= date('h:i:s A') ?></b>
                    </div>
                </div>

                <!-- Section 2: 4 Primary Metric Cards -->
                <div class="stat-grid">
                    <!-- Total Users Card -->
                    <div class="stat-card">
                        <div class="stat-label">
                            <span>Total Users</span>
                            <span class="badge badge-info">Accounts</span>
                        </div>
                        <div class="stat-value" id="card-users" style="color:var(--primary);">
                            <?= (int)$userStats['total_users'] ?>
                        </div>
                        <div class="stat-subtext" id="subtext-users">
                            <b><?= (int)$userStats['total_admins'] ?></b> Admin &bull; <b><?= (int)$userStats['total_normal_users'] ?></b> Students
                        </div>
                    </div>

                    <!-- Total Students Card -->
                    <div class="stat-card">
                        <div class="stat-label">
                            <span>Total Students</span>
                            <a href="students.php" style="color:var(--primary); font-size:11px; text-decoration:none;">Manage &rarr;</a>
                        </div>
                        <div class="stat-value" id="card-students" style="color:var(--primary-dark);">
                            <?= $totalStudents ?>
                        </div>
                        <div class="stat-subtext">
                            Enrolled student directory records
                        </div>
                    </div>

                    <!-- Total Events Card -->
                    <div class="stat-card">
                        <div class="stat-label">
                            <span>Total Events</span>
                            <a href="events.php" style="color:var(--primary); font-size:11px; text-decoration:none;">Manage &rarr;</a>
                        </div>
                        <div class="stat-value" id="card-events" style="color:var(--primary);">
                            <?= (int)$eventStats['total_events'] ?>
                        </div>
                        <div class="stat-subtext" id="subtext-events">
                            <b><?= (int)$eventStats['open_events'] ?></b> Active &bull; <b><?= (int)$eventStats['closed_events'] ?></b> Concluded
                        </div>
                    </div>

                    <!-- Total Registrations Card -->
                    <div class="stat-card">
                        <div class="stat-label">
                            <span>Registrations</span>
                            <span class="badge badge-success">M:N Relation</span>
                        </div>
                        <div class="stat-value" id="card-registrations" style="color:var(--success);">
                            <?= (int)$regStats['total_registrations'] ?>
                        </div>
                        <div class="stat-subtext" id="subtext-registrations">
                            <b><?= (int)$regStats['confirmed_registrations'] ?></b> Confirmed &bull; <b><?= (int)$regStats['pending_registrations'] ?></b> Pending
                        </div>
                    </div>
                </div>

                <!-- Section 3: Recent Registrations Table -->
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:24px; margin-bottom:8px;">
                    <h3 style="margin:0; border-left:4px solid var(--primary); padding:6px 12px; background:var(--primary-light);">
                        Latest Event Registrations (MySQL <code>registrations</code> Table)
                    </h3>
                    <span style="font-size:12px; color:var(--text-muted);">
                        Showing latest <span id="reg-count-badge"><?= count($latestRegistrations) ?></span> records
                    </span>
                </div>

                <div class="table-responsive">
                    <table border="1" width="100%" cellpadding="10" id="registrations-table">
                        <thead>
                            <tr>
                                <th width="10%">Reg ID</th>
                                <th width="24%">Student Name</th>
                                <th width="14%">Student ID</th>
                                <th width="26%">Event Title</th>
                                <th width="12%">Status</th>
                                <th width="14%">Registered At</th>
                            </tr>
                        </thead>
                        <tbody id="registrations-tbody">
                            <?php if (empty($latestRegistrations)): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; padding:24px; color:var(--text-muted);">
                                        <i>No event registrations recorded yet in the database.</i>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($latestRegistrations as $r): ?>
                                    <tr>
                                        <td><code>#<?= (int)$r['reg_id'] ?></code></td>
                                        <td>
                                            <b><?= htmlspecialchars($r['student_name'], ENT_QUOTES, 'UTF-8') ?></b>
                                            <div style="font-size:11.5px; color:var(--text-muted);"><?= htmlspecialchars($r['student_course'], ENT_QUOTES, 'UTF-8') ?></div>
                                        </td>
                                        <td><code><?= htmlspecialchars($r['student_id'], ENT_QUOTES, 'UTF-8') ?></code></td>
                                        <td>
                                            <b><?= htmlspecialchars($r['event_name'], ENT_QUOTES, 'UTF-8') ?></b>
                                            <div style="font-size:11px; color:var(--text-muted);"><?= htmlspecialchars($r['event_category'], ENT_QUOTES, 'UTF-8') ?></div>
                                        </td>
                                        <td>
                                            <?php
                                                $status = strtolower($r['status']);
                                                $badgeClass = ($status === 'confirmed') ? 'badge-success' : (($status === 'pending') ? 'badge-warning' : 'badge-info');
                                            ?>
                                            <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($r['status']), ENT_QUOTES, 'UTF-8') ?></span>
                                        </td>
                                        <td style="font-size:12px; color:var(--text-muted);">
                                            <?= htmlspecialchars(date('M j, Y h:i A', strtotime($r['registered_at'])), ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Section 4: Audit Log Table -->
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:28px; margin-bottom:8px;">
                    <h3 style="margin:0; border-left:4px solid var(--danger); padding:6px 12px; background:var(--danger-bg); color:var(--danger);">
                        Administrator Action Audit Log (<code>audit_logs</code> Table)
                    </h3>
                    <span style="font-size:12px; color:var(--text-muted);">
                        Total Logged Operations: <b id="audit-count-badge"><?= $totalAuditLogs ?></b>
                    </span>
                </div>

                <p style="font-size:13px; color:var(--text-muted); margin-bottom:12px;">
                    Tracks all successful state-changing operations across Student Management (Practical 11), Event Management (Practical 12 &amp; 13), and Administrator access.
                </p>

                <div class="table-responsive">
                    <table border="1" width="100%" cellpadding="9" id="audit-table">
                        <thead>
                            <tr>
                                <th width="8%">Log ID</th>
                                <th width="18%">Administrator</th>
                                <th width="16%">Action</th>
                                <th width="12%">Entity</th>
                                <th width="32%">Operation Details</th>
                                <th width="14%">Timestamp</th>
                            </tr>
                        </thead>
                        <tbody id="audit-tbody">
                            <?php if (empty($recentAuditLogs)): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; padding:24px; color:var(--text-muted);">
                                        <i>No audit logs recorded yet.</i>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentAuditLogs as $log): ?>
                                    <?php
                                        $act = strtoupper($log['action']);
                                        $badgeClass = 'act-system';
                                        if (strpos($act, 'CREATE') !== false) {
                                            $badgeClass = 'act-create';
                                        } elseif (strpos($act, 'UPDATE') !== false) {
                                            $badgeClass = 'act-update';
                                        } elseif (strpos($act, 'DELETE') !== false) {
                                            $badgeClass = 'act-delete';
                                        } elseif (strpos($act, 'LOGIN') !== false) {
                                            $badgeClass = 'act-login';
                                        }
                                    ?>
                                    <tr>
                                        <td><code>#<?= (int)$log['log_id'] ?></code></td>
                                        <td>
                                            <b><?= htmlspecialchars($log['user_name'], ENT_QUOTES, 'UTF-8') ?></b>
                                            <div style="font-size:11px; color:var(--text-muted);"><?= htmlspecialchars($log['user_email'], ENT_QUOTES, 'UTF-8') ?></div>
                                        </td>
                                        <td>
                                            <span class="audit-action-badge <?= $badgeClass ?>"><?= htmlspecialchars($log['action'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </td>
                                        <td>
                                            <span class="badge badge-info"><?= htmlspecialchars(strtoupper($log['entity_type']), ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php if (!empty($log['entity_id'])): ?>
                                                <div style="font-size:11px; font-family:monospace; color:var(--text-muted); margin-top:2px;">ID: <?= htmlspecialchars($log['entity_id'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size:13px;">
                                            <?= htmlspecialchars($log['details'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                                            <?php if (!empty($log['ip_address'])): ?>
                                                <div style="font-size:10.5px; color:var(--text-muted); margin-top:2px;">IP: <?= htmlspecialchars($log['ip_address'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size:12px; color:var(--text-muted); white-space:nowrap;">
                                            <?= htmlspecialchars(date('M j, Y h:i A', strtotime($log['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Section 5: Registered User Accounts Management -->
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:28px; margin-bottom:8px;">
                    <h3 style="margin:0; border-left:4px solid #6366f1; padding:6px 12px; background:#eef2ff;">
                        Registered User Accounts (<code>users</code> Table)
                    </h3>
                    <span style="font-size:12px; color:var(--text-muted);">
                        Total Users: <b><?= count($allUsers) ?></b>
                    </span>
                </div>

                <div class="table-responsive">
                    <table border="1" width="100%" cellpadding="8">
                        <thead>
                            <tr>
                                <th width="10%">User ID</th>
                                <th width="28%">Full Name</th>
                                <th width="32%">Email Address</th>
                                <th width="12%">Role</th>
                                <th width="18%">Registered Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allUsers as $u): ?>
                                <tr>
                                    <td><code>#<?= (int)$u['user_id'] ?></code></td>
                                    <td><b><?= htmlspecialchars($u['full_name'], ENT_QUOTES, 'UTF-8') ?></b></td>
                                    <td><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <span class="badge <?= $u['role'] === 'admin' ? 'badge-danger' : 'badge-info' ?>">
                                            <?= ucfirst(htmlspecialchars($u['role'], ENT_QUOTES, 'UTF-8')) ?>
                                        </span>
                                    </td>
                                    <td style="font-size:12px; color:var(--text-muted);">
                                        <?= htmlspecialchars($u['created_at'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            </td>
        </tr>
    </table>

    <!-- Client-side Asynchronous Fetch API Integration (Practical 13 & 14) -->
    <script>
        async function refreshDashboardData() {
            const btn = document.getElementById('btn-refresh-data');
            const spinner = document.getElementById('refresh-spinner');
            const alertBox = document.getElementById('live-alert');

            btn.disabled = true;
            spinner.style.display = 'inline';

            try {
                const response = await fetch('../api/dashboard.php', {
                    method: 'GET',
                    headers: { 'Accept': 'application/json' },
                    cache: 'no-store'
                });

                const result = await response.json();

                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Failed to refresh dashboard metrics.');
                }

                const data = result.data;

                // 1. Update Stat Cards
                document.getElementById('card-users').textContent = data.stats.total_users;
                document.getElementById('subtext-users').innerHTML = `<b>${data.stats.total_admins}</b> Admin &bull; <b>${data.stats.total_normal_users}</b> Students`;

                document.getElementById('card-students').textContent = data.stats.total_students;

                document.getElementById('card-events').textContent = data.stats.total_events;
                document.getElementById('subtext-events').innerHTML = `<b>${data.stats.open_events}</b> Active &bull; <b>${data.stats.closed_events}</b> Concluded`;

                document.getElementById('card-registrations').textContent = data.stats.total_registrations;
                document.getElementById('subtext-registrations').innerHTML = `<b>${data.stats.confirmed_registrations}</b> Confirmed &bull; <b>${data.stats.pending_registrations}</b> Pending`;

                // 2. Update Registrations Table
                const regTbody = document.getElementById('registrations-tbody');
                if (data.latest_registrations.length === 0) {
                    regTbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:24px; color:var(--text-muted);"><i>No event registrations recorded yet in the database.</i></td></tr>';
                } else {
                    regTbody.innerHTML = data.latest_registrations.map(r => {
                        const statusClass = (r.status.toLowerCase() === 'confirmed') ? 'badge-success' : (r.status.toLowerCase() === 'pending' ? 'badge-warning' : 'badge-info');
                        return `
                            <tr>
                                <td><code>#${r.reg_id}</code></td>
                                <td>
                                    <b>${escapeHtml(r.student_name)}</b>
                                    <div style="font-size:11.5px; color:var(--text-muted);">${escapeHtml(r.student_course)}</div>
                                </td>
                                <td><code>${escapeHtml(r.student_id)}</code></td>
                                <td>
                                    <b>${escapeHtml(r.event_name)}</b>
                                    <div style="font-size:11px; color:var(--text-muted);">${escapeHtml(r.event_category)}</div>
                                </td>
                                <td><span class="badge ${statusClass}">${escapeHtml(capitalize(r.status))}</span></td>
                                <td style="font-size:12px; color:var(--text-muted);">${escapeHtml(r.formatted_date)}</td>
                            </tr>
                        `;
                    }).join('');
                }
                document.getElementById('reg-count-badge').textContent = data.latest_registrations.length;

                // 3. Update Audit Log Table
                const auditTbody = document.getElementById('audit-tbody');
                if (data.recent_audit_logs.length === 0) {
                    auditTbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:24px; color:var(--text-muted);"><i>No audit logs recorded yet.</i></td></tr>';
                } else {
                    auditTbody.innerHTML = data.recent_audit_logs.map(log => {
                        const act = log.action.toUpperCase();
                        let badgeClass = 'act-system';
                        if (act.includes('CREATE')) badgeClass = 'act-create';
                        else if (act.includes('UPDATE')) badgeClass = 'act-update';
                        else if (act.includes('DELETE')) badgeClass = 'act-delete';
                        else if (act.includes('LOGIN')) badgeClass = 'act-login';

                        return `
                            <tr>
                                <td><code>#${log.log_id}</code></td>
                                <td>
                                    <b>${escapeHtml(log.user_name)}</b>
                                    <div style="font-size:11px; color:var(--text-muted);">${escapeHtml(log.user_email)}</div>
                                </td>
                                <td><span class="audit-action-badge ${badgeClass}">${escapeHtml(log.action)}</span></td>
                                <td>
                                    <span class="badge badge-info">${escapeHtml(log.entity_type.toUpperCase())}</span>
                                    ${log.entity_id ? `<div style="font-size:11px; font-family:monospace; color:var(--text-muted); margin-top:2px;">ID: ${escapeHtml(log.entity_id)}</div>` : ''}
                                </td>
                                <td style="font-size:13px;">
                                    ${escapeHtml(log.details || '—')}
                                    ${log.ip_address ? `<div style="font-size:10.5px; color:var(--text-muted); margin-top:2px;">IP: ${escapeHtml(log.ip_address)}</div>` : ''}
                                </td>
                                <td style="font-size:12px; color:var(--text-muted); white-space:nowrap;">${escapeHtml(log.formatted_date)}</td>
                            </tr>
                        `;
                    }).join('');
                }
                document.getElementById('audit-count-badge').textContent = data.stats.total_audit_logs;

                // 4. Update Timestamp
                const now = new Date();
                document.getElementById('last-updated-text').innerHTML = `Last synced: <b>${now.toLocaleTimeString()}</b>`;

                // 5. Display brief success toast
                alertBox.className = 'alert alert-success';
                alertBox.style.display = 'block';
                alertBox.innerHTML = '✓ Dashboard metrics refreshed asynchronously via PHP JSON API.';
                setTimeout(() => { alertBox.style.display = 'none'; }, 3000);

            } catch (err) {
                alertBox.className = 'alert alert-danger';
                alertBox.style.display = 'block';
                alertBox.innerHTML = `⚠️ ${escapeHtml(err.message)}`;
            } finally {
                btn.disabled = false;
                spinner.style.display = 'none';
            }
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function capitalize(str) {
            if (!str) return '';
            return str.charAt(0).toUpperCase() + str.slice(1);
        }
    </script>

</body>
</html>
