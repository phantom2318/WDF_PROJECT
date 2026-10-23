<?php
/**
 * StudentHub: User Registration
 * Secure account registration with PDO prepared statements and bcrypt password hashing
 */

require_once __DIR__ . '/../scripts/db.php';

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname        = trim(strip_tags($_POST['fullname'] ?? ''));
    $email           = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password        = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // 1. Server-side validation
    if ($fullname === '') {
        $errors['fullname'] = 'Full Name is required.';
    } elseif (strlen($fullname) < 3 || !preg_match('/^[A-Za-z\s]+$/', $fullname)) {
        $errors['fullname'] = 'Full Name must be at least 3 letters (alphabets only).';
    }

    if ($email === '') {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if ($password === '') {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters long.';
    }

    if ($confirmPassword === '') {
        $errors['confirm_password'] = 'Please confirm your password.';
    } elseif ($password !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    // 2. Database check and insert
    if (empty($errors)) {
        try {
            // Check for existing email using prepared statement
            $checkStmt = $pdo->prepare('SELECT `user_id` FROM `users` WHERE `email` = :email LIMIT 1');
            $checkStmt->execute(['email' => $email]);

            if ($checkStmt->fetch()) {
                $errors['email'] = 'An account with this email address already exists.';
            } else {
                // Secure password hashing
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                // Insert into users table
                $insertStmt = $pdo->prepare('INSERT INTO `users` (`full_name`, `email`, `password_hash`) VALUES (:name, :email, :hash)');
                $insertStmt->execute([
                    'name'  => $fullname,
                    'email' => $email,
                    'hash'  => $passwordHash
                ]);

                $success = true;
                $_POST   = []; // Reset form data
            }
        } catch (PDOException $e) {
            // Handle race conditions where two requests submit same email simultaneously
            if ($e->getCode() === '23000' || ($e->errorInfo[1] ?? 0) === 1062) {
                $errors['email'] = 'An account with this email address already exists.';
            } else {
                $errors['general'] = 'Registration could not be completed at this time. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — StudentHub</title>
    <script src="../scripts/theme.js"></script>
    <link rel="icon" type="image/png" href="../images/favicon.png">
    <link rel="stylesheet" href="portal.css">
    <link rel="stylesheet" href="darkmode.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: 40px 24px;
        }
        .portal-header { text-align: center; margin-bottom: 24px; }
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            width: 100%;
            max-width: 440px;
            overflow: hidden;
        }
        .tab-bar { display: flex; border-bottom: 1px solid var(--border); }
        .tab-btn {
            flex: 1;
            padding: 14px;
            font-size: 14px;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            background: var(--surface-muted);
            color: var(--text-muted);
            border: none;
            cursor: pointer;
        }
        .tab-btn.active {
            background: var(--surface);
            color: var(--primary);
            border-bottom: 2px solid var(--primary);
        }
        .tab-panel { padding: 28px 32px; }
        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block;
            font-size: 11.5px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .form-group input { width: 100%; }
        .err-msg {
            color: var(--danger);
            font-size: 12px;
            margin-top: 4px;
            font-weight: 500;
        }
        .alert-success {
            background: var(--success-bg);
            color: var(--success);
            border: 1px solid #86efac;
            border-radius: var(--radius-sm);
            padding: 14px;
            margin-bottom: 18px;
            font-size: 13.5px;
            text-align: center;
        }
        .alert-danger {
            background: var(--danger-bg);
            color: var(--danger);
            border: 1px solid #fca5a5;
            border-radius: var(--radius-sm);
            padding: 12px;
            margin-bottom: 16px;
            font-size: 13px;
        }
    </style>
</head>
<body>

    <div class="portal-header">
        <h1 style="margin: 0; font-size: 1.8rem;">Student Portal</h1>
        <p style="margin: 4px 0 0; color: var(--text-muted); font-size: 13.5px;">Create your student account to get started</p>
    </div>

    <div class="card">
        <div class="tab-bar">
            <a href="login.php" class="tab-btn">Sign In</a>
            <a href="register.php" class="tab-btn active">Register</a>
        </div>

        <div class="tab-panel">
            <?php if ($success): ?>
                <div class="alert-success">
                    <b>&#10004; Account Created Successfully!</b>
                    <p style="margin: 6px 0 14px; font-size: 13px;">Your account has been registered with StudentHub.</p>
                    <a href="login.php"><button type="button" style="width:100%;">Proceed to Sign In &rarr;</button></a>
                </div>
            <?php else: ?>
                <?php if (isset($errors['general'])): ?>
                    <div class="alert-danger"><?= htmlspecialchars($errors['general']) ?></div>
                <?php endif; ?>

                <form action="register.php" method="POST" novalidate>
                    <!-- Full Name -->
                    <div class="form-group">
                        <label for="fullname">Full Name *</label>
                        <input type="text"
                               id="fullname"
                               name="fullname"
                               class="<?= isset($errors['fullname']) ? 'invalid' : '' ?>"
                               value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>"
                               placeholder="e.g. Om Patel"
                               required autocomplete="name">
                        <?php if (isset($errors['fullname'])): ?>
                            <div class="err-msg">&cross; <?= htmlspecialchars($errors['fullname']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Email Address -->
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email"
                               id="email"
                               name="email"
                               class="<?= isset($errors['email']) ? 'invalid' : '' ?>"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               placeholder="student@charusat.edu.in"
                               required autocomplete="email">
                        <?php if (isset($errors['email'])): ?>
                            <div class="err-msg">&cross; <?= htmlspecialchars($errors['email']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Password -->
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password"
                               id="password"
                               name="password"
                               class="<?= isset($errors['password']) ? 'invalid' : '' ?>"
                               placeholder="Minimum 8 characters"
                               required autocomplete="new-password">
                        <?php if (isset($errors['password'])): ?>
                            <div class="err-msg">&cross; <?= htmlspecialchars($errors['password']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Confirm Password -->
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password *</label>
                        <input type="password"
                               id="confirm_password"
                               name="confirm_password"
                               class="<?= isset($errors['confirm_password']) ? 'invalid' : '' ?>"
                               placeholder="Repeat your password"
                               required autocomplete="new-password">
                        <?php if (isset($errors['confirm_password'])): ?>
                            <div class="err-msg">&cross; <?= htmlspecialchars($errors['confirm_password']) ?></div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn-full" style="width: 100%; margin-top: 10px; padding: 11px;">
                        Create Account
                    </button>
                </form>

                <p style="text-align: center; margin-top: 18px; font-size: 13px; color: var(--text-muted);">
                    Already have an account? <a href="login.php" style="color: var(--primary); font-weight: 600; text-decoration: underline;">Sign In</a>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <p style="margin-top: 24px; color: var(--text-muted); font-size: 12px; text-align: center;">
        <button type="button" class="btn-outline" style="padding: 4px 12px; font-size: 12px;" onclick="THEME.toggleTheme()">🌓 Toggle Theme</button>
    </p>

</body>
</html>
