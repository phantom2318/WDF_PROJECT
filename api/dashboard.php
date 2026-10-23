<?php
/**
 * StudentHub: Admin Dashboard JSON API (Practical 14)
 * Provides authenticated administrator endpoints for live dashboard metrics,
 * latest event registrations, and recent audit trail entries.
 */

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

/**
 * Sends standardized JSON response and terminates script.
 */
function sendJsonResponse(bool $success, string $message, $data = null, int $statusCode = 200): void {
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($statusCode);
    echo json_encode([
        'success'   => $success,
        'message'   => $message,
        'data'      => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../scripts/auth.php';
require_once __DIR__ . '/../scripts/db.php';
require_once __DIR__ . '/../scripts/audit_helper.php';

// 1. Enforce Authentication & Admin Role
if (!isset($_SESSION['user_id'])) {
    sendJsonResponse(false, 'Unauthorized. Please sign in to access admin dashboard metrics.', null, 401);
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    sendJsonResponse(false, 'Forbidden. Administrator privileges are required to access this endpoint.', null, 403);
}

// 2. Process GET Request
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method !== 'GET') {
    sendJsonResponse(false, "Method {$method} not allowed.", null, 405);
}

try {
    // A. User Counts
    $userCountStmt = $pdo->query('
        SELECT 
            COUNT(*) AS total_users,
            SUM(CASE WHEN `role` = "admin" THEN 1 ELSE 0 END) AS total_admins,
            SUM(CASE WHEN `role` != "admin" THEN 1 ELSE 0 END) AS total_normal_users
        FROM `users`
    ');
    $userStats = $userCountStmt->fetch(PDO::FETCH_ASSOC) ?: [
        'total_users'        => 0,
        'total_admins'       => 0,
        'total_normal_users' => 0
    ];

    // B. Student Count
    $studentCount = (int)$pdo->query('SELECT COUNT(*) FROM `students`')->fetchColumn();

    // C. Event Counts
    $eventCountStmt = $pdo->query('
        SELECT 
            COUNT(*) AS total_events,
            SUM(CASE WHEN `status` = "Open" THEN 1 ELSE 0 END) AS open_events,
            SUM(CASE WHEN `status` = "Closed" THEN 1 ELSE 0 END) AS closed_events
        FROM `events`
    ');
    $eventStats = $eventCountStmt->fetch(PDO::FETCH_ASSOC) ?: [
        'total_events'  => 0,
        'open_events'   => 0,
        'closed_events' => 0
    ];

    // D. Registration Counts
    $regCountStmt = $pdo->query('
        SELECT 
            COUNT(*) AS total_registrations,
            SUM(CASE WHEN `status` = "Confirmed" THEN 1 ELSE 0 END) AS confirmed_registrations,
            SUM(CASE WHEN `status` = "Pending" THEN 1 ELSE 0 END) AS pending_registrations
        FROM `registrations`
    ');
    $regStats = $regCountStmt->fetch(PDO::FETCH_ASSOC) ?: [
        'total_registrations'     => 0,
        'confirmed_registrations' => 0,
        'pending_registrations'   => 0
    ];

    // E. Latest Registrations with Student and Event Details (Limit 10)
    $regLimit = isset($_GET['reg_limit']) ? max(1, min(50, (int)$_GET['reg_limit'])) : 10;
    $regStmt = $pdo->prepare('
        SELECT 
            r.`reg_id`,
            r.`student_id`,
            COALESCE(s.`full_name`, "Unknown Student") AS `student_name`,
            COALESCE(s.`course`, "—") AS `student_course`,
            COALESCE(s.`department`, "—") AS `student_department`,
            r.`event_id`,
            COALESCE(e.`event_name`, "Unknown Event") AS `event_name`,
            COALESCE(e.`category`, "General") AS `event_category`,
            r.`status`,
            r.`registered_at`
        FROM `registrations` r
        LEFT JOIN `students` s ON r.`student_id` = s.`student_id`
        LEFT JOIN `events` e ON r.`event_id` = e.`event_id`
        ORDER BY r.`registered_at` DESC, r.`reg_id` DESC
        LIMIT :limit
    ');
    $regStmt->bindValue(':limit', $regLimit, PDO::PARAM_INT);
    $regStmt->execute();
    $latestRegistrations = $regStmt->fetchAll(PDO::FETCH_ASSOC);

    // Format registration dates for UI convenience
    foreach ($latestRegistrations as &$reg) {
        $reg['formatted_date'] = !empty($reg['registered_at']) ? date('M j, Y h:i A', strtotime($reg['registered_at'])) : '—';
    }
    unset($reg);

    // F. Recent Audit Logs (Limit 15)
    $auditLimit = isset($_GET['audit_limit']) ? max(1, min(50, (int)$_GET['audit_limit'])) : 15;
    $recentAuditLogs = getRecentAuditLogs($auditLimit);

    foreach ($recentAuditLogs as &$log) {
        $log['formatted_date'] = !empty($log['created_at']) ? date('M j, Y h:i:s A', strtotime($log['created_at'])) : '—';
    }
    unset($log);

    // G. Total Audit Logs Count
    $totalAuditLogs = getAuditLogCount();

    // H. Compile payload
    $payload = [
        'stats' => [
            'total_users'             => (int)$userStats['total_users'],
            'total_admins'            => (int)$userStats['total_admins'],
            'total_normal_users'      => (int)$userStats['total_normal_users'],
            'total_students'          => $studentCount,
            'total_events'            => (int)$eventStats['total_events'],
            'open_events'             => (int)$eventStats['open_events'],
            'closed_events'           => (int)$eventStats['closed_events'],
            'total_registrations'     => (int)$regStats['total_registrations'],
            'confirmed_registrations' => (int)$regStats['confirmed_registrations'],
            'pending_registrations'   => (int)$regStats['pending_registrations'],
            'total_audit_logs'        => $totalAuditLogs
        ],
        'latest_registrations' => $latestRegistrations,
        'recent_audit_logs'    => $recentAuditLogs,
        'server_info' => [
            'php_version' => PHP_VERSION,
            'mysql_version' => $pdo->query('SELECT VERSION()')->fetchColumn()
        ]
    ];

    sendJsonResponse(true, 'Admin dashboard metrics retrieved successfully.', $payload, 200);

} catch (Throwable $e) {
    error_log('Dashboard API Error: ' . $e->getMessage());
    sendJsonResponse(false, 'Database error: Unable to load dashboard statistics at this time.', null, 500);
}
