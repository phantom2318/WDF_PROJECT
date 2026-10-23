<?php
/**
 * StudentHub: Secure User Authentication (Sign In)
 * Authenticates users using PDO prepared statements, password_verify(), and session security
 */

require_once __DIR__ . '/../scripts/db.php';
require_once __DIR__ . '/../scripts/auth.php';
require_once __DIR__ . '/../scripts/audit_helper.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit;
}

$error   = '';
$message = '';

// Check query notifications
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'timeout') {
        $error = 'Your session has expired due to 15 minutes of inactivity. Please sign in again.';
    } elseif ($_GET['error'] === 'unauthorized') {
        $error = 'Please sign in to access that page.';
    } elseif ($_GET['error'] === 'forbidden') {
        $error = 'Access denied. You do not have administrator permissions.';
    }
}
if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $message = 'You have been successfully logged out.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);

    if (empty($email) || empty($password)) {
        $error = 'Please enter both your email address and password.';
    } else {
        try {
            // Retrieve user using prepared statement
            $stmt = $pdo->prepare('SELECT `user_id`, `full_name`, `email`, `password_hash`, `role` FROM `users` WHERE `email` = :email LIMIT 1');
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            // Verify password using native bcrypt verification
            if ($user && password_verify($password, $user['password_hash'])) {
                // Regenerate session ID to prevent session fixation attacks
                session_regenerate_id(true);

                $_SESSION['user_id']       = $user['user_id'];
                $_SESSION['name']          = $user['full_name'];
                $_SESSION['email']         = $user['email'];
                $_SESSION['role']          = $user['role'];
                $_SESSION['last_activity'] = time();

                // Handle Remember-Me token
                if ($remember) {
                    $rawToken  = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $rawToken);
                    $expiresAt = date('Y-m-d H:i:s', time() + 86400 * 30); // 30 days

                    $tokStmt = $pdo->prepare('INSERT INTO `remember_tokens` (`user_id`, `token_hash`, `expires_at`) VALUES (:uid, :hash, :exp)');
                    $tokStmt->execute([
                        'uid'  => $user['user_id'],
                        'hash' => $tokenHash,
                        'exp'  => $expiresAt
                    ]);

                    setcookie('remember_token', $user['user_id'] . ':' . $rawToken, [
                        'expires'  => time() + 86400 * 30,
                        'path'     => '/',
                        'httponly' => true,
                        'samesite' => 'Lax',
                        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    ]);
                }

                // Redirect based on role or to home.php
                if ($user['role'] === 'admin') {
                    logAuditAction('ADMIN_LOGIN', 'auth', (string)$user['user_id'], "Administrator '{$user['full_name']}' logged in successfully", (int)$user['user_id']);
                    header('Location: admin_dashboard.php');
                } else {
                    header('Location: home.php');
                }
                exit;
            } else {
                // Generic error message: do not reveal whether email exists
                $error = 'Invalid email address or password. Please try again.';
            }
        } catch (PDOException $e) {
            $error = 'Authentication failed due to a system error. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — StudentHub</title>
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
        .alert-danger {
            background: var(--danger-bg);
            color: var(--danger);
            border: 1px solid #fca5a5;
            border-radius: var(--radius-sm);
            padding: 12px;
            margin-bottom: 16px;
            font-size: 13px;
        }
        .alert-success {
            background: var(--success-bg);
            color: var(--success);
            border: 1px solid #86efac;
            border-radius: var(--radius-sm);
            padding: 12px;
            margin-bottom: 16px;
            font-size: 13px;
        }
    </style>
</head>
<body>

    <div class="portal-header">
        <h1 style="margin:0; font-size: 1.8rem;">Student Portal</h1>
        <p style="margin:4px 0 0; color:var(--text-muted); font-size:13.5px;">Sign in to your academic dashboard</p>
    </div>

    <div class="card">
        <div class="tab-bar">
            <a href="login.php" class="tab-btn active">Sign In</a>
            <a href="register.php" class="tab-btn">Register</a>
        </div>

        <div class="tab-panel">
            <?php if (!empty($message)): ?>
                <div class="alert-success">&#10004; <?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert-danger">&#9888; <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form action="login.php" method="POST" novalidate>
                <!-- Email Address -->
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email"
                           id="email"
                           name="email"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           placeholder="student@charusat.edu.in"
                           required autocomplete="email">
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password"
                           id="password"
                           name="password"
                           placeholder="Enter your password"
                           required autocomplete="current-password">
                </div>

                <!-- Remember Me Checkbox -->
                <div class="form-group" style="display:flex; align-items:center; gap:8px;">
                    <label style="cursor:pointer; display:flex; align-items:center; gap:6px; font-weight:normal; text-transform:none; font-size:13px; color:var(--text); margin-bottom:0;">
                        <input type="checkbox" name="remember" value="1" <?= !empty($_POST['remember']) ? 'checked' : '' ?> style="width:auto;">
                        Remember Me (30 days)
                    </label>
                </div>

                <button type="submit" class="btn-full" style="width: 100%; margin-top: 10px; padding: 11px;">
                    Sign In
                </button>
            </form>

            <p style="text-align: center; margin-top: 18px; font-size: 13px; color: var(--text-muted);">
                Don't have an account? <a href="register.php" style="color: var(--primary); font-weight: 600; text-decoration: underline;">Create an account</a>
            </p>
        </div>
    </div>

    <p style="margin-top: 24px; color: var(--text-muted); font-size: 12px; text-align: center;">
        <button type="button" class="btn-outline" style="padding: 4px 12px; font-size: 12px;" onclick="THEME.toggleTheme()">🌓 Toggle Theme</button>
    </p>

</body>
</html>
