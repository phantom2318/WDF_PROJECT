<?php
/**
 * StudentHub: Event Registration
 * Server-side processing, validation, and JSON file storage
 */

$file    = __DIR__ . '/../data/registrations.json';
$errors  = [];
$success = false;
$newId   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name   = trim(strip_tags($_POST['fullname'] ?? ''));
    $email  = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $phone  = preg_replace('/\D/', '', $_POST['phone'] ?? '');
    $course = trim($_POST['course'] ?? '');
    $event  = trim($_POST['purpose'] ?? 'General Event');

    // Server-side validation
    if (strlen($name) < 3)                         $errors['fullname'] = 'Enter full name (at least 3 characters).';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email']    = 'Enter a valid email address.';
    if (!preg_match('/^[6-9]\d{9}$/', $phone))     $errors['phone']    = 'Enter a valid 10-digit mobile number.';
    if (empty($course))                             $errors['course']   = 'Please select your course.';
    if (empty($_POST['terms']))                     $errors['terms']    = 'You must accept the terms.';

    if (empty($errors)) {
        $records = file_exists($file) ? json_decode(file_get_contents($file), true) ?: [] : [];
        $newId   = 'REG-' . date('Ymd') . '-' . str_pad(count($records) + 1, 4, '0', STR_PAD_LEFT);

        $records[] = [
            'id'           => $newId,
            'fullname'     => $name,
            'email'        => $email,
            'phone'        => $phone,
            'course'       => $course,
            'purpose'      => $event,
            'submitted_at' => date('Y-m-d H:i')
        ];

        file_put_contents($file, json_encode($records, JSON_PRETTY_PRINT), LOCK_EX);
        $success = true;
        $_POST   = []; // clear inputs on success
    }
}

$allRecords = file_exists($file) ? json_decode(file_get_contents($file), true) ?: [] : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Event Registration — StudentHub</title>
    <script src="../scripts/theme.js"></script>
    <link rel="icon" type="image/png" href="../images/favicon.png">
    <link rel="stylesheet" href="portal.css">
    <link rel="stylesheet" href="darkmode.css">
    <script src="../scripts/portal.js"></script>
    <script src="../scripts/profile_loader.js" defer></script>
</head>
<body>

    <!-- Top Bar -->
    <table border="1" width="100%" class="topbar">
        <tr>
            <td width="15%"><img src="../images/logo.jpg" alt="Logo" style="height:60px;width:60px;"></td>
            <td width="60%">
                <h1 style="margin:0;">EVENT REGISTRATION</h1>
                <p style="margin:0;color:var(--text-muted);font-size:13px;">Register for campus hackathons, workshops, and club activities</p>
            </td>
            <td width="25%" align="right">
                <a href="home.html"><button class="btn-outline" type="button">Dashboard</button></a>
                <button type="button" onclick="THEME.toggleTheme()">🌓 Theme</button>
            </td>
        </tr>
    </table>

    <br>

    <!-- Student Summary Strip -->
    <table border="1" width="100%" cellpadding="8" class="student-info">
        <tr>
            <td><b>Student Name:</b> <span data-profile="fullName">Om Patel</span></td>
            <td><b>Student ID:</b> <span data-profile="studentId">25DCS095</span></td>
            <td><b>Department:</b> <span data-profile="department">Information Technology</span></td>
            <td><b>Semester:</b> <span data-profile="semester">3</span></td>
        </tr>
    </table>

    <br>

    <!-- Feedback Alerts -->
    <?php if ($success): ?>
        <div style="background:var(--success-bg);color:var(--success);padding:12px 16px;border:1px solid #86efac;border-radius:var(--radius-sm);margin-bottom:14px;font-weight:600;">
            ✓ Registration confirmed successfully! Reference ID: <code><?= htmlspecialchars($newId) ?></code>
        </div>
    <?php elseif (!empty($errors)): ?>
        <div style="background:var(--danger-bg);color:var(--danger);padding:12px 16px;border:1px solid #fca5a5;border-radius:var(--radius-sm);margin-bottom:14px;font-size:13px;">
            <b>Please correct the following errors:</b>
            <ul style="margin:4px 0 0 18px;padding:0;">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Registration Form -->
    <h3>Registration Details</h3>
    <table border="1" width="100%" cellpadding="0">
        <tr>
            <td style="padding:18px;">
                <form action="Event_registration.php" method="POST" novalidate>
                    <table cellpadding="6" cellspacing="0" style="border:none!important;box-shadow:none;">
                        <tr>
                            <td width="160"><b>Full Name *</b></td>
                            <td>
                                <input type="text" name="fullname" style="width:300px;" class="<?= isset($errors['fullname']) ? 'invalid' : '' ?>"
                                       value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>" placeholder="Enter your full name">
                            </td>
                        </tr>
                        <tr>
                            <td><b>Email Address *</b></td>
                            <td>
                                <input type="email" name="email" style="width:300px;" class="<?= isset($errors['email']) ? 'invalid' : '' ?>"
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="student@charusat.edu.in">
                            </td>
                        </tr>
                        <tr>
                            <td><b>Mobile Number *</b></td>
                            <td>
                                <input type="tel" name="phone" maxlength="10" style="width:300px;" class="<?= isset($errors['phone']) ? 'invalid' : '' ?>"
                                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" placeholder="10-digit mobile number">
                            </td>
                        </tr>
                        <tr>
                            <td><b>Course *</b></td>
                            <td>
                                <select name="course" style="width:300px;" class="<?= isset($errors['course']) ? 'invalid' : '' ?>">
                                    <option value="">-- Select Course --</option>
                                    <?php foreach (['B.Tech', 'M.Tech', 'BCA', 'MCA', 'B.Sc'] as $c): ?>
                                        <option value="<?= $c ?>" <?= ($_POST['course'] ?? '') === $c ? 'selected' : '' ?>><?= $c ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td><b>Category / Event *</b></td>
                            <td>
                                <select name="purpose" style="width:300px;">
                                    <option value="Campus Hackathon 2026">Campus Hackathon 2026</option>
                                    <option value="Web Frameworks Workshop">Web Frameworks Workshop</option>
                                    <option value="Coding Club Membership">Coding Club Membership</option>
                                    <option value="General Inquiry">General Inquiry</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td>&nbsp;</td>
                            <td>
                                <label style="cursor:pointer;font-size:13px;">
                                    <input type="checkbox" name="terms" value="1" <?= !empty($_POST['terms']) ? 'checked' : '' ?>>
                                    I accept the event registration terms &amp; conditions. *
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <td>&nbsp;</td>
                            <td style="padding-top:10px;">
                                <button type="submit" style="padding:8px 20px;font-weight:bold;">Submit Registration</button>
                                &nbsp;
                                <a href="Event_registration.php"><button type="button" class="btn-secondary">Reset</button></a>
                            </td>
                        </tr>
                    </table>
                </form>
            </td>
        </tr>
    </table>

    <br>

    <!-- Recent Registrations Table -->
    <h3>Recent Registrations (<?= count($allRecords) ?>)</h3>
    <table border="1" width="100%" cellpadding="8">
        <thead>
            <tr>
                <th>ID</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Mobile</th>
                <th>Course</th>
                <th>Event / Category</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($allRecords)): ?>
                <tr><td colspan="7" align="center" style="color:var(--text-muted);">No registrations found yet.</td></tr>
            <?php else: ?>
                <?php foreach (array_slice(array_reverse($allRecords), 0, 5) as $r): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($r['id'] ?? '') ?></code></td>
                        <td><b><?= htmlspecialchars($r['fullname'] ?? '') ?></b></td>
                        <td><?= htmlspecialchars($r['email'] ?? '') ?></td>
                        <td><?= htmlspecialchars($r['phone'] ?? '') ?></td>
                        <td><span class="badge badge-info"><?= htmlspecialchars($r['course'] ?? '') ?></span></td>
                        <td><span class="badge badge-warning"><?= htmlspecialchars($r['purpose'] ?? '') ?></span></td>
                        <td style="color:var(--text-muted);font-size:12px;"><?= htmlspecialchars($r['submitted_at'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>
