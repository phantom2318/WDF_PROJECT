<?php
/**
 * StudentHub: Practical 14 Automated Test Suite
 * Validates Admin Dashboard, Role-Based Access Control, Dynamic Role Navigation, and Audit Logging.
 */

$baseUrl = 'http://localhost/StudentHub';
$cookieFileAdmin = __DIR__ . '/cookie_admin_p14.txt';
$cookieFileUser  = __DIR__ . '/cookie_user_p14.txt';
$cookieFileAnon  = __DIR__ . '/cookie_anon_p14.txt';

@unlink($cookieFileAdmin);
@unlink($cookieFileUser);
@unlink($cookieFileAnon);

require_once __DIR__ . '/../scripts/db.php';

function httpReq(string $url, string $method = 'GET', $data = null, ?string $cookieFile = null, array $headers = []): array {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
    } elseif ($method === 'PUT' || $method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? json_encode($data) : $data);
        }
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $raw = curl_exec($ch);
    $headerSize  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    $headerStr = substr($raw, 0, $headerSize);
    $body      = substr($raw, $headerSize);

    return [
        'code'        => $httpCode,
        'contentType' => $contentType,
        'headers'     => $headerStr,
        'body'        => $body,
        'json'        => json_decode($body, true)
    ];
}

$passCount = 0;
$failCount = 0;

function assertTest(string $name, bool $condition, string $details = '') {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "  [PASS] {$name}\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$name} - {$details}\n";
    }
}

echo "====================================================================\n";
echo "Starting Practical 14 Automated Test Suite\n";
echo "Admin Dashboard &bull; RBAC &bull; Dynamic Menu &bull; Audit Log\n";
echo "====================================================================\n\n";

// ------------------------------------------------------------------
// CASE 1: Unauthenticated & Anonymous Protection (Req 6 & 7)
// ------------------------------------------------------------------
echo "--- CASE 1: Anonymous Access Protection (Unauthenticated) ---\n";
$anonDash = httpReq("{$baseUrl}/pages/admin_dashboard.php", 'GET', null, $cookieFileAnon);
assertTest("Direct GET admin_dashboard.php without session redirects (302)", in_array($anonDash['code'], [302, 303]));
assertTest("Redirect target is login.php?error=unauthorized", strpos($anonDash['headers'], 'Location: login.php?error=unauthorized') !== false);

$anonApi = httpReq("{$baseUrl}/api/dashboard.php", 'GET', null, $cookieFileAnon);
assertTest("Direct GET api/dashboard.php returns 401 Unauthorized", $anonApi['code'] === 401);
assertTest("Response is JSON with success=false", isset($anonApi['json']['success']) && $anonApi['json']['success'] === false);

// ------------------------------------------------------------------
// CASE 2: Student Login & RBAC Enforcement (Req 5, 6, 7 & 8)
// ------------------------------------------------------------------
echo "\n--- CASE 2: Student Role Restrictions (RBAC) ---\n";
// Fetch login CSRF
$loginGet = httpReq("{$baseUrl}/pages/login.php", 'GET', null, $cookieFileUser);
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $loginGet['body'], $csrfM);
$userCsrf = $csrfM[1] ?? '';

// Sign in as student
$studentLogin = httpReq("{$baseUrl}/pages/login.php", 'POST', [
    'email'      => 'rohan.verma@charusat.edu.in',
    'password'   => 'SuperSecretPassword@2026',
    'csrf_token' => $userCsrf
], $cookieFileUser);
assertTest("Student login successful and redirects (302)", in_array($studentLogin['code'], [302, 303]));

// Student accesses student dashboard (home.php)
$studentHome = httpReq("{$baseUrl}/pages/home.php", 'GET', null, $cookieFileUser);
assertTest("Student can access home.php (200 OK)", $studentHome['code'] === 200);

// Student attempts to access admin dashboard
$studentToAdmin = httpReq("{$baseUrl}/pages/admin_dashboard.php", 'GET', null, $cookieFileUser);
assertTest("Student accessing admin_dashboard.php is blocked & redirected (302)", in_array($studentToAdmin['code'], [302, 303]));
assertTest("Student redirect target is home.php?error=forbidden", strpos($studentToAdmin['headers'], 'Location: home.php?error=forbidden') !== false);

// Student attempts to access admin dashboard API
$studentApi = httpReq("{$baseUrl}/api/dashboard.php", 'GET', null, $cookieFileUser);
assertTest("Student accessing api/dashboard.php returns 403 Forbidden", $studentApi['code'] === 403);
assertTest("Response JSON explains forbidden admin access", isset($studentApi['json']['success']) && $studentApi['json']['success'] === false);

// Verify Student Navigation Menu (Req 8)
assertTest("Student menu does NOT contain Admin Dashboard button", strpos($studentHome['body'], 'Admin Dashboard') === false);
assertTest("Student menu contains Campus Events link", strpos($studentHome['body'], 'Campus Events') !== false);
assertTest("Student menu contains Student Directory link", strpos($studentHome['body'], 'Student Directory') !== false);
assertTest("Student menu header identifies as Student Navigation", strpos($studentHome['body'], 'Student Navigation') !== false);

// ------------------------------------------------------------------
// CASE 3: Administrator Login & Dashboard Access (Req 1, 2, 3, 8)
// ------------------------------------------------------------------
echo "\n--- CASE 3: Administrator Login & Dashboard Metrics ---\n";
$loginAdminGet = httpReq("{$baseUrl}/pages/login.php", 'GET', null, $cookieFileAdmin);
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $loginAdminGet['body'], $csrfAdm);
$adminCsrf = $csrfAdm[1] ?? '';

$adminLogin = httpReq("{$baseUrl}/pages/login.php", 'POST', [
    'email'      => 'admin@charusat.edu.in',
    'password'   => 'Admin@2026',
    'csrf_token' => $adminCsrf
], $cookieFileAdmin);
assertTest("Admin login redirects to admin_dashboard.php", in_array($adminLogin['code'], [302, 303]) && strpos($adminLogin['headers'], 'admin_dashboard.php') !== false);

$adminDash = httpReq("{$baseUrl}/pages/admin_dashboard.php", 'GET', null, $cookieFileAdmin);
assertTest("Admin can access admin_dashboard.php (200 OK)", $adminDash['code'] === 200);

// Verify Admin Navigation Menu (Req 8)
assertTest("Admin menu header identifies as Administrator Navigation", strpos($adminDash['body'], 'Administrator Navigation') !== false);
assertTest("Admin menu contains Admin Dashboard link", strpos($adminDash['body'], 'Admin Dashboard') !== false);
assertTest("Admin menu contains Student Management link", strpos($adminDash['body'], 'Student Management') !== false);
assertTest("Admin menu contains Event Management link", strpos($adminDash['body'], 'Event Management') !== false);
assertTest("Admin menu contains Event Registrations link", strpos($adminDash['body'], 'Event Registrations') !== false);

// ------------------------------------------------------------------
// CASE 4: Verification of Database Counts vs Dashboard API (Req 2)
// ------------------------------------------------------------------
echo "\n--- CASE 4: Dynamic Statistics Verification ---\n";
$apiDash = httpReq("{$baseUrl}/api/dashboard.php", 'GET', null, $cookieFileAdmin);
assertTest("Admin can access api/dashboard.php (200 OK)", $apiDash['code'] === 200);
assertTest("api/dashboard.php returns success=true", isset($apiDash['json']['success']) && $apiDash['json']['success'] === true);

$data = $apiDash['json']['data'] ?? [];
$dbUserCount   = (int)$pdo->query('SELECT COUNT(*) FROM `users`')->fetchColumn();
$dbStudentCount= (int)$pdo->query('SELECT COUNT(*) FROM `students`')->fetchColumn();
$dbEventCount  = (int)$pdo->query('SELECT COUNT(*) FROM `events`')->fetchColumn();
$dbRegCount    = (int)$pdo->query('SELECT COUNT(*) FROM `registrations`')->fetchColumn();

assertTest("Total Users statistic matches MySQL ({$data['stats']['total_users']} == {$dbUserCount})", $data['stats']['total_users'] === $dbUserCount);
assertTest("Total Students statistic matches MySQL ({$data['stats']['total_students']} == {$dbStudentCount})", $data['stats']['total_students'] === $dbStudentCount);
assertTest("Total Events statistic matches MySQL ({$data['stats']['total_events']} == {$dbEventCount})", $data['stats']['total_events'] === $dbEventCount);
assertTest("Total Registrations statistic matches MySQL ({$data['stats']['total_registrations']} == {$dbRegCount})", $data['stats']['total_registrations'] === $dbRegCount);

// ------------------------------------------------------------------
// CASE 5: Recent Registrations Order & Relations (Req 3 & 6)
// ------------------------------------------------------------------
echo "\n--- CASE 5: Recent Registrations Order & Relations ---\n";
$latestRegs = $data['latest_registrations'] ?? [];
assertTest("Latest registrations array is present", is_array($latestRegs));
if (!empty($latestRegs)) {
    assertTest("First registration has student_name", !empty($latestRegs[0]['student_name']));
    assertTest("First registration has event_name", !empty($latestRegs[0]['event_name']));
    assertTest("First registration has status", !empty($latestRegs[0]['status']));
    assertTest("First registration has registered_at", !empty($latestRegs[0]['registered_at']));

    // Verify descending order
    $isSorted = true;
    for ($i = 0; $i < count($latestRegs) - 1; $i++) {
        if (strtotime($latestRegs[$i]['registered_at']) < strtotime($latestRegs[$i + 1]['registered_at'])) {
            $isSorted = false;
            break;
        }
    }
    assertTest("Registrations sorted in descending timestamp order", $isSorted);
}

// ------------------------------------------------------------------
// CASE 6: Audit Logging for Student CRUD (Req 9, 11, 12, 13)
// ------------------------------------------------------------------
echo "\n--- CASE 6: Student CRUD & Audit Logging ---\n";
// Fetch students page to get session CSRF token
$studPage = httpReq("{$baseUrl}/pages/students.php", 'GET', null, $cookieFileAdmin);
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $studPage['body'], $studCsrfM);
$studCsrf = $studCsrfM[1] ?? '';

$testSid = 'TEST' . rand(1000, 9999);
$testName = 'Audit Test Student ' . rand(100, 999);
$testEmail = 'audit.' . strtolower($testSid) . '@charusat.edu.in';

// 6a. Create Student
$createStud = httpReq("{$baseUrl}/pages/students.php", 'POST', [
    'action'     => 'create',
    'csrf_token' => $studCsrf,
    'student_id' => $testSid,
    'full_name'  => $testName,
    'email'      => $testEmail,
    'mobile'     => '9876543210',
    'department' => 'Information Technology',
    'course'     => 'B.Tech',
    'semester'   => 3
], $cookieFileAdmin);
assertTest("Create student redirects (302)", in_array($createStud['code'], [302, 303]));

// Verify Audit Entry for CREATE_STUDENT
$auditStmt = $pdo->prepare('SELECT * FROM `audit_logs` WHERE `action` = "CREATE_STUDENT" AND `entity_id` = :sid ORDER BY `log_id` DESC LIMIT 1');
$auditStmt->execute(['sid' => $testSid]);
$createLog = $auditStmt->fetch(PDO::FETCH_ASSOC);

assertTest("Audit log recorded CREATE_STUDENT for {$testSid}", !empty($createLog));
assertTest("Audit log user_id matches logged-in admin", (int)($createLog['user_id'] ?? 0) === 3);
assertTest("Audit log timestamp is recent", !empty($createLog['created_at']) && (time() - strtotime($createLog['created_at']) < 60));

// 6b. Update Student
$updateStud = httpReq("{$baseUrl}/pages/students.php", 'POST', [
    'action'     => 'update',
    'csrf_token' => $studCsrf,
    'student_id' => $testSid,
    'full_name'  => $testName . ' Updated',
    'email'      => $testEmail,
    'mobile'     => '9876543210',
    'department' => 'Computer Science',
    'course'     => 'B.Tech',
    'semester'   => 4
], $cookieFileAdmin);
assertTest("Update student redirects (302)", in_array($updateStud['code'], [302, 303]));

// Verify Audit Entry for UPDATE_STUDENT
$auditStmt = $pdo->prepare('SELECT * FROM `audit_logs` WHERE `action` = "UPDATE_STUDENT" AND `entity_id` = :sid ORDER BY `log_id` DESC LIMIT 1');
$auditStmt->execute(['sid' => $testSid]);
$updateLog = $auditStmt->fetch(PDO::FETCH_ASSOC);
assertTest("Audit log recorded UPDATE_STUDENT for {$testSid}", !empty($updateLog));

// 6c. Delete Student
$deleteStud = httpReq("{$baseUrl}/pages/students.php", 'POST', [
    'action'     => 'delete',
    'csrf_token' => $studCsrf,
    'student_id' => $testSid
], $cookieFileAdmin);
assertTest("Delete student redirects (302)", in_array($deleteStud['code'], [302, 303]));

// Verify Audit Entry for DELETE_STUDENT
$auditStmt = $pdo->prepare('SELECT * FROM `audit_logs` WHERE `action` = "DELETE_STUDENT" AND `entity_id` = :sid ORDER BY `log_id` DESC LIMIT 1');
$auditStmt->execute(['sid' => $testSid]);
$deleteLog = $auditStmt->fetch(PDO::FETCH_ASSOC);
assertTest("Audit log recorded DELETE_STUDENT for {$testSid}", !empty($deleteLog));

// 6d. Failed CRUD (Validation failure) must NOT create audit log (Req 11)
$beforeAuditCount = (int)$pdo->query('SELECT COUNT(*) FROM `audit_logs`')->fetchColumn();
$failStud = httpReq("{$baseUrl}/pages/students.php", 'POST', [
    'action'     => 'create',
    'csrf_token' => $studCsrf,
    'student_id' => 'INVALID!', // Invalid characters
    'full_name'  => 'X',        // Too short
    'email'      => 'notanemail'
], $cookieFileAdmin);
$afterAuditCount = (int)$pdo->query('SELECT COUNT(*) FROM `audit_logs`')->fetchColumn();
assertTest("Failed validation does NOT create false success audit log", $beforeAuditCount === $afterAuditCount);

// ------------------------------------------------------------------
// CASE 7: Audit Logging for Event CRUD via API & Forms (Req 10, 14, 15)
// ------------------------------------------------------------------
echo "\n--- CASE 7: Event CRUD & Audit Logging (Practical 12 & 13) ---\n";
// Fetch event CSRF
$evtPage = httpReq("{$baseUrl}/pages/events.php", 'GET', null, $cookieFileAdmin);
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $evtPage['body'], $evtCsrfM);
$evtCsrf = $evtCsrfM[1] ?? $studCsrf;

$eventTitle = 'P14 Audit Tech Workshop ' . rand(100, 999);
$createEvt = httpReq("{$baseUrl}/api/events.php", 'POST', [
    'event_name' => $eventTitle,
    'category'   => 'Technical',
    'event_date' => '2026-11-20',
    'event_time' => '14:30',
    'venue'      => 'Seminar Hall 3',
    'status'     => 'Open',
    'description'=> 'Testing audit logging for event creation via JSON API.',
    'csrf_token' => $evtCsrf
], $cookieFileAdmin, ['X-CSRF-Token: ' . $evtCsrf]);

assertTest("Create event via API returns 201 Created", $createEvt['code'] === 201);
$createdEvtId = $createEvt['json']['data']['event_id'] ?? 0;
assertTest("Event ID was generated ({$createdEvtId})", $createdEvtId > 0);

// Verify CREATE_EVENT audit entry
$auditStmt = $pdo->prepare('SELECT * FROM `audit_logs` WHERE `action` = "CREATE_EVENT" AND `entity_id` = :eid ORDER BY `log_id` DESC LIMIT 1');
$auditStmt->execute(['eid' => (string)$createdEvtId]);
$createEvtLog = $auditStmt->fetch(PDO::FETCH_ASSOC);
assertTest("Audit log recorded CREATE_EVENT for Event #{$createdEvtId}", !empty($createEvtLog));

// Update Event via API
$updateEvt = httpReq("{$baseUrl}/api/events.php", 'PUT', [
    'event_id'   => $createdEvtId,
    'event_name' => $eventTitle . ' Updated',
    'category'   => 'Technical',
    'event_date' => '2026-11-20',
    'event_time' => '15:00',
    'venue'      => 'Auditorium Hall',
    'status'     => 'Open',
    'csrf_token' => $evtCsrf
], $cookieFileAdmin, ['X-CSRF-Token: ' . $evtCsrf]);
assertTest("Update event via API returns 200 OK", $updateEvt['code'] === 200);

// Verify UPDATE_EVENT audit entry
$auditStmt = $pdo->prepare('SELECT * FROM `audit_logs` WHERE `action` = "UPDATE_EVENT" AND `entity_id` = :eid ORDER BY `log_id` DESC LIMIT 1');
$auditStmt->execute(['eid' => (string)$createdEvtId]);
$updateEvtLog = $auditStmt->fetch(PDO::FETCH_ASSOC);
assertTest("Audit log recorded UPDATE_EVENT for Event #{$createdEvtId}", !empty($updateEvtLog));

// Delete Event via API
$delEvt = httpReq("{$baseUrl}/api/events.php", 'DELETE', [
    'event_id'   => $createdEvtId,
    'csrf_token' => $evtCsrf
], $cookieFileAdmin, ['X-CSRF-Token: ' . $evtCsrf]);
assertTest("Delete event via API returns 200 OK", $delEvt['code'] === 200);

// Verify DELETE_EVENT audit entry
$auditStmt = $pdo->prepare('SELECT * FROM `audit_logs` WHERE `action` = "DELETE_EVENT" AND `entity_id` = :eid ORDER BY `log_id` DESC LIMIT 1');
$auditStmt->execute(['eid' => (string)$createdEvtId]);
$deleteEvtLog = $auditStmt->fetch(PDO::FETCH_ASSOC);
assertTest("Audit log recorded DELETE_EVENT for Event #{$createdEvtId}", !empty($deleteEvtLog));

// ------------------------------------------------------------------
// CASE 8: Security & Secrets Redaction (Req 9 & 12)
// ------------------------------------------------------------------
echo "\n--- CASE 8: Security & Secrets Check in Audit Logs ---\n";
$secretCheck = $pdo->query('
    SELECT COUNT(*) FROM `audit_logs`
    WHERE `details` LIKE "%password%"
       OR `details` LIKE "%$2y$%"
       OR `details` LIKE "%token%"
')->fetchColumn();
assertTest("No passwords, hashes, or session tokens logged in audit_logs", (int)$secretCheck === 0);

// ------------------------------------------------------------------
// SUMMARY
// ------------------------------------------------------------------
echo "\n====================================================================\n";
echo "TEST RESULTS: {$passCount} Passed, {$failCount} Failed\n";
echo "====================================================================\n";

if ($failCount === 0) {
    echo ">>> ALL PRACTICAL 14 AUTOMATED TESTS PASSED SUCCESSFULLY! <<<\n";
    exit(0);
} else {
    echo ">>> SOME TESTS FAILED! CHECK DETAILS ABOVE. <<<\n";
    exit(1);
}
