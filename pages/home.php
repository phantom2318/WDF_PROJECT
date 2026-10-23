<?php
/**
 * StudentHub: Authenticated Student Dashboard
 * Protected page: Enforces session authentication, inactivity timeout, and RBAC
 */

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once __DIR__ . '/../scripts/auth.php';
requireLogin();
require_once __DIR__ . '/../scripts/nav_helper.php';

$userName = htmlspecialchars($_SESSION['name'] ?? 'Student');
$userRole = htmlspecialchars($_SESSION['role'] ?? 'user');
$userEmail = htmlspecialchars($_SESSION['email'] ?? '');
$errorMessage = '';
if (isset($_GET['error']) && $_GET['error'] === 'forbidden') {
    $errorMessage = 'Access denied. Administrator privileges are required to access that page.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Portal — Dashboard</title>
    <script src="../scripts/theme.js"></script>
    <link rel="icon" type="image/png" href="../images/favicon.png">
    <link rel="stylesheet" href="portal.css">
    <link rel="stylesheet" href="darkmode.css">
    <script src="../scripts/portal.js"></script>
</head>
<body>

    <!-- Top Bar -->
    <table border="1" width="100%" class="topbar">
        <tr>
            <td width="15%">
                <img src="../images/logo.jpg" alt="Logo" style="height:65px; width:65px; border-radius: var(--radius-md);">
            </td>
            <td width="60%">
                <h1 id="welcome-heading" style="margin:0;">WELCOME, <?= strtoupper($userName) ?></h1>
                <p style="margin:2px 0 0; color:var(--text-muted); font-size:13px;">
                    Role: <span class="badge <?= $userRole === 'admin' ? 'badge-danger' : 'badge-info' ?>"><?= ucfirst($userRole) ?></span>
                    &bull; <?= $userEmail ?>
                </p>
            </td>
            <td width="35%" align="right">
                <?php if ($userRole === 'admin'): ?>
                    <a href="admin_dashboard.php"><button class="btn-outline" type="button">Admin Dashboard</button></a>
                    &nbsp;
                <?php endif; ?>
                <a href="settings.html"><button class="btn-outline" type="button">Settings</button></a>
                &nbsp;
                <a href="logout.php"><button class="btn-danger" type="button">Logout</button></a>
            </td>
        </tr>
    </table>

    <br>

    <!-- Main Content & Sidebar -->
    <table border="1" width="100%">
        <tr>
            <td width="22%" valign="top" style="vertical-align: top; padding: 16px;">
                <?php renderSidebarNavigation('home'); ?>
            </td>

            <td width="78%" valign="top" style="vertical-align: top; padding: 20px;">
                <?php if (!empty($errorMessage)): ?>
                    <div class="alert alert-danger" style="margin-bottom:16px;">
                        <span>⚠️ <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <h2 style="margin:0;">Campus Events &amp; Announcements</h2>
                    <a href="events.php"><button class="btn-outline" type="button" style="padding:6px 14px; font-size:12.5px;">Browse All Events &rarr;</button></a>
                </div>
                <p style="color:var(--text-muted); font-size:13.5px;">Welcome to your unified academic portal. Use the navigation sidebar to register for events, check fee details, or manage your club participations.</p>

                <br>
                <table border="1" width="100%" cellpadding="10">
                    <tr>
                        <td width="50%">
                            <h4>CHARUSAT Annual Hackathon 2026</h4>
                            <p style="font-size:13px; color:var(--text-muted); margin:4px 0 8px;">24-hour inter-college software hackathon. Registrations open now.</p>
                            <a href="Event_registration.php"><button type="button" style="padding:6px 14px; font-size:12px;">Register Now</button></a>
                        </td>
                        <td width="50%">
                            <h4>Web Frameworks Workshop</h4>
                            <p style="font-size:13px; color:var(--text-muted); margin:4px 0 8px;">Hands-on session on PHP PDO, MySQL, and secure authentication.</p>
                            <a href="Event_registration.php"><button type="button" class="btn-outline" style="padding:6px 14px; font-size:12px;">Learn More</button></a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

</body>
</html>
