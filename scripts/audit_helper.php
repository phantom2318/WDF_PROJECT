<?php
/**
 * StudentHub: Central Audit Logging Helper (Practical 14)
 * Provides standardized recording and retrieval of administrative audit trails.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

/**
 * Records an administrator action in the audit log.
 *
 * @param string      $action      Action identifier (e.g., 'CREATE_STUDENT', 'UPDATE_EVENT')
 * @param string      $entityType  Entity type ('student', 'event', 'user', 'auth', etc.)
 * @param string|null $entityId    Identifier of the affected entity
 * @param string|null $details     Descriptive details (no secrets/passwords)
 * @param int|null    $userId      Explicit user ID, or null to auto-detect from active session
 * @return bool True if logged successfully, false on error
 */
function logAuditAction(string $action, string $entityType, ?string $entityId = null, ?string $details = null, ?int $userId = null): bool {
    global $pdo;

    try {
        if ($pdo === null) {
            require_once __DIR__ . '/db.php';
        }

        // Auto-detect user ID from authenticated session if not provided
        if ($userId === null && isset($_SESSION['user_id'])) {
            $userId = (int)$_SESSION['user_id'];
        }

        // Sanitize details: strip any accidental password or token strings
        if ($details !== null) {
            $details = preg_replace('/(password|token|hash|secret)\s*[:=]\s*[^\s,]+/i', '$1: [REDACTED]', $details);
        }

        // Resolve client IP address safely
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if (strlen($ip) > 45) {
            $ip = substr($ip, 0, 45);
        }

        // Insert audit log using PDO prepared statement
        $stmt = $pdo->prepare('
            INSERT INTO `audit_logs` (`user_id`, `action`, `entity_type`, `entity_id`, `details`, `ip_address`, `created_at`)
            VALUES (:uid, :act, :etype, :eid, :details, :ip, NOW())
        ');

        return $stmt->execute([
            'uid'     => $userId,
            'act'     => strtoupper(trim($action)),
            'etype'   => strtolower(trim($entityType)),
            'eid'     => $entityId !== null ? (string)$entityId : null,
            'details' => $details,
            'ip'      => $ip
        ]);
    } catch (Throwable $e) {
        // Safe logging failure handling: record to PHP error log without breaking the parent operation
        error_log('Audit logging failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Retrieves recent audit log records with user information.
 *
 * @param int $limit Maximum records to return
 * @param int $offset Offset for pagination
 * @return array List of audit log records
 */
function getRecentAuditLogs(int $limit = 15, int $offset = 0): array {
    global $pdo;

    try {
        if ($pdo === null) {
            require_once __DIR__ . '/db.php';
        }

        $stmt = $pdo->prepare('
            SELECT 
                a.`log_id`,
                a.`user_id`,
                a.`action`,
                a.`entity_type`,
                a.`entity_id`,
                a.`details`,
                a.`ip_address`,
                a.`created_at`,
                COALESCE(u.`full_name`, "System / Former User") AS `user_name`,
                COALESCE(u.`email`, "system@charusat.edu.in") AS `user_email`,
                COALESCE(u.`role`, "admin") AS `user_role`
            FROM `audit_logs` a
            LEFT JOIN `users` u ON a.`user_id` = u.`user_id`
            ORDER BY a.`created_at` DESC, a.`log_id` DESC
            LIMIT :limit OFFSET :offset
        ');

        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('Error retrieving audit logs: ' . $e->getMessage());
        return [];
    }
}

/**
 * Returns total count of recorded audit logs.
 *
 * @return int Total logs count
 */
function getAuditLogCount(): int {
    global $pdo;

    try {
        if ($pdo === null) {
            require_once __DIR__ . '/db.php';
        }
        return (int)$pdo->query('SELECT COUNT(*) FROM `audit_logs`')->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}
