<?php
/**
 * StudentHub: Central Authentication & Session Management
 * Handles sessions, inactivity timeout (15 mins), RBAC, and remember-me tokens
 */

if (session_status() === PHP_SESSION_NONE) {
    // Secure cookie parameters for local and production environments
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Inactivity timeout: 15 minutes (900 seconds)
define('INACTIVITY_TIMEOUT', 900);

/**
 * Validates session state, checks inactivity timeout, and checks remember-me cookie.
 */
function checkAuthSession(): void {
    global $pdo;

    // 1. Check inactivity timeout for authenticated session
    if (isset($_SESSION['user_id'])) {
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > INACTIVITY_TIMEOUT)) {
            logoutUser();
            header('Location: login.php?error=timeout');
            exit;
        }
        $_SESSION['last_activity'] = time();
        return;
    }

    // 2. Check Remember-Me cookie if session is absent
    if (!empty($_COOKIE['remember_token'])) {
        $parts = explode(':', $_COOKIE['remember_token'], 2);
        if (count($parts) === 2) {
            $userId   = (int)$parts[0];
            $rawToken = $parts[1];
            $tokenHash = hash('sha256', $rawToken);

            require_once __DIR__ . '/db.php';
            try {
                $stmt = $pdo->prepare('
                    SELECT t.token_id, u.user_id, u.full_name, u.email, u.role
                    FROM `remember_tokens` t
                    JOIN `users` u ON t.user_id = u.user_id
                    WHERE t.user_id = :uid AND t.token_hash = :hash AND t.expires_at > NOW()
                    LIMIT 1
                ');
                $stmt->execute(['uid' => $userId, 'hash' => $tokenHash]);
                $user = $stmt->fetch();

                if ($user) {
                    // Populate session
                    $_SESSION['user_id']       = $user['user_id'];
                    $_SESSION['name']          = $user['full_name'];
                    $_SESSION['email']         = $user['email'];
                    $_SESSION['role']          = $user['role'];
                    $_SESSION['last_activity'] = time();
                    session_regenerate_id(true);

                    // Rotate remember-me token
                    $newRawToken = bin2hex(random_bytes(32));
                    $newHash     = hash('sha256', $newRawToken);
                    $newExpiry   = date('Y-m-d H:i:s', time() + 86400 * 30);

                    $updateStmt = $pdo->prepare('UPDATE `remember_tokens` SET `token_hash` = :new_hash, `expires_at` = :new_exp WHERE `token_id` = :tid');
                    $updateStmt->execute(['new_hash' => $newHash, 'new_exp' => $newExpiry, 'tid' => $user['token_id']]);

                    setcookie('remember_token', $user['user_id'] . ':' . $newRawToken, [
                        'expires'  => time() + 86400 * 30,
                        'path'     => '/',
                        'httponly' => true,
                        'samesite' => 'Lax',
                        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    ]);
                } else {
                    // Invalid/expired token: clear cookie
                    setcookie('remember_token', '', time() - 3600, '/');
                }
            } catch (PDOException $e) {
                // Silently ignore DB errors on cookie validation
            }
        }
    }
}

// Run check on script inclusion
checkAuthSession();

/**
 * Requires an active logged-in session; redirects to login.php if not authenticated.
 */
function requireLogin(): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php?error=unauthorized');
        exit;
    }
}

/**
 * Requires admin role; redirects to home.php with forbidden message if unauthorized.
 */
function requireAdmin(): void {
    requireLogin();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        header('Location: home.php?error=forbidden');
        exit;
    }
}

/**
 * Destroys session, revokes remember token, and clears cookies.
 */
function logoutUser(): void {
    global $pdo;

    // Revoke remember-me token in DB
    if (!empty($_COOKIE['remember_token'])) {
        $parts = explode(':', $_COOKIE['remember_token'], 2);
        if (count($parts) === 2) {
            $tokenHash = hash('sha256', $parts[1]);
            try {
                if (empty($pdo)) {
                    require_once __DIR__ . '/db.php';
                }
                $delStmt = $pdo->prepare('DELETE FROM `remember_tokens` WHERE `token_hash` = :hash');
                $delStmt->execute(['hash' => $tokenHash]);
            } catch (Exception $e) {}
        }
        setcookie('remember_token', '', time() - 3600, '/');
        unset($_COOKIE['remember_token']);
    }

    // Destroy PHP session
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

/**
 * Checks whether the current user is an administrator.
 */
function isAdmin(): bool {
    return (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
}

/**
 * Generates or retrieves the existing CSRF token for the active session.
 */
function getCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validates the submitted CSRF token using timing-safe comparison.
 */
function validateCsrfToken(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

