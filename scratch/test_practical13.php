<?php
/**
 * Automated Test Suite for Practical 13: PHP JSON API & Fetch-Based CRUD
 */

$baseUrl = 'http://localhost/StudentHub';
$cookieFileUser  = __DIR__ . '/cookie_user.txt';
$cookieFileAdmin = __DIR__ . '/cookie_admin.txt';

@unlink($cookieFileUser);
@unlink($cookieFileAdmin);

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
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
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
        echo "[PASS] {$name}\n";
    } else {
        $failCount++;
        echo "[FAIL] {$name} - {$details}\n";
    }
}

echo "======================================================\n";
echo "Starting Practical 13 API & CRUD Automated Test Suite\n";
echo "======================================================\n\n";

// TEST 1: Unauthenticated access
echo "--- TEST 1: Unauthenticated API Access ---\n";
$res = httpReq("{$baseUrl}/api/events.php");
assertTest("GET unauthenticated returns 401", $res['code'] === 401, "Expected 401, got {$res['code']}");
assertTest("Response is application/json", strpos($res['contentType'], 'application/json') !== false, "Got {$res['contentType']}");
assertTest("Response success is false", isset($res['json']['success']) && $res['json']['success'] === false);

// TEST 2: Student Login
echo "\n--- TEST 2: Student Login & Student Role Permissions ---\n";
// Get CSRF from login page
$loginPage = httpReq("{$baseUrl}/pages/login.php", 'GET', null, $cookieFileUser);
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $loginPage['body'], $csrfMatches);
$loginCsrf = $csrfMatches[1] ?? '';

$loginRes = httpReq("{$baseUrl}/pages/login.php", 'POST', [
    'email'      => 'rohan.verma@charusat.edu.in',
    'password'   => 'SuperSecretPassword@2026',
    'csrf_token' => $loginCsrf
], $cookieFileUser);
assertTest("Student login redirects to home.php", in_array($loginRes['code'], [302, 200]));

// GET events as student
$evtRes = httpReq("{$baseUrl}/api/events.php", 'GET', null, $cookieFileUser);
assertTest("Student can GET events list (200 OK)", $evtRes['code'] === 200);
assertTest("Events array returned in data", is_array($evtRes['json']['data']) && count($evtRes['json']['data']) > 0);

// Search events
$searchRes = httpReq("{$baseUrl}/api/events.php?search=Hackathon", 'GET', null, $cookieFileUser);
assertTest("Search filter works (?search=Hackathon)", $searchRes['code'] === 200 && count($searchRes['json']['data']) >= 1);

// Category filter
$catRes = httpReq("{$baseUrl}/api/events.php?category=Technical", 'GET', null, $cookieFileUser);
assertTest("Category filter works (?category=Technical)", $catRes['code'] === 200 && count($catRes['json']['data']) >= 1);

// Empty results
$emptyRes = httpReq("{$baseUrl}/api/events.php?search=NonExistentKeywordXYZ123", 'GET', null, $cookieFileUser);
assertTest("Empty search handled gracefully (200 with empty array)", $emptyRes['code'] === 200 && is_array($emptyRes['json']['data']) && count($emptyRes['json']['data']) === 0);

// Single event
$singleRes = httpReq("{$baseUrl}/api/events.php?id=1", 'GET', null, $cookieFileUser);
assertTest("Single event fetch by ID works", $singleRes['code'] === 200 && isset($singleRes['json']['data']['event_id']));

// Nonexistent event
$nonExistentRes = httpReq("{$baseUrl}/api/events.php?id=99999", 'GET', null, $cookieFileUser);
assertTest("Nonexistent event ID returns 404", $nonExistentRes['code'] === 404 && $nonExistentRes['json']['success'] === false);

// RBAC: Student attempting POST (Create)
$studentPost = httpReq("{$baseUrl}/api/events.php", 'POST', [
    'action'     => 'create',
    'event_name' => 'Unauthorized Event'
], $cookieFileUser, ['X-CSRF-Token: fake']);
assertTest("Student user blocked from creating events (403 Forbidden)", $studentPost['code'] === 403);

// TEST 3: Admin Login
echo "\n--- TEST 3: Admin Login & Full Asynchronous CRUD Operations ---\n";
$adminLoginPage = httpReq("{$baseUrl}/pages/login.php", 'GET', null, $cookieFileAdmin);
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $adminLoginPage['body'], $adminCsrfMatches);
$adminLoginCsrf = $adminCsrfMatches[1] ?? '';

$adminLoginRes = httpReq("{$baseUrl}/pages/login.php", 'POST', [
    'email'      => 'admin@charusat.edu.in',
    'password'   => 'Admin@2026',
    'csrf_token' => $adminLoginCsrf
], $cookieFileAdmin);
assertTest("Admin login redirects successfully", in_array($adminLoginRes['code'], [302, 200]));

// Fetch events page as admin to extract session CSRF token
$adminEventsPage = httpReq("{$baseUrl}/pages/events.php", 'GET', null, $cookieFileAdmin);
preg_match('/const CSRF_TOKEN = "([^"]+)";/', $adminEventsPage['body'], $tokenMatches);
$adminCsrfToken = $tokenMatches[1] ?? '';
assertTest("Admin session CSRF token retrieved from page", !empty($adminCsrfToken));

// CSRF check: Invalid CSRF token on POST
$invalidCsrfRes = httpReq("{$baseUrl}/api/events.php", 'POST', [
    'action'     => 'create',
    'event_name' => 'Valid Title',
    'csrf_token' => 'invalid_csrf_token_value'
], $cookieFileAdmin);
assertTest("Invalid CSRF token returns 403 Forbidden", $invalidCsrfRes['code'] === 403);

// Validation check: Invalid input (empty title)
$invalidInputRes = httpReq("{$baseUrl}/api/events.php", 'POST', [
    'action'     => 'create',
    'event_name' => '',
    'category'   => 'Technical',
    'event_date' => '2026-11-20',
    'event_time' => '10:00',
    'venue'      => 'Hall A',
    'status'     => 'Open',
    'csrf_token' => $adminCsrfToken
], $cookieFileAdmin, ["X-CSRF-Token: {$adminCsrfToken}"]);
assertTest("Invalid inputs return 400 Bad Request with validation message", $invalidInputRes['code'] === 400 && $invalidInputRes['json']['success'] === false);

// CREATE EVENT (without poster)
$newEventTitle = 'Fetch API Test Symposium ' . time();
$createRes = httpReq("{$baseUrl}/api/events.php", 'POST', [
    'action'      => 'create',
    'event_name'  => $newEventTitle,
    'category'    => 'Technical',
    'event_date'  => '2026-12-25',
    'event_time'  => '14:30',
    'venue'       => 'Auditorium B',
    'status'      => 'Open',
    'description' => 'Asynchronous CRUD demonstration event created via Fetch API.',
    'csrf_token'  => $adminCsrfToken
], $cookieFileAdmin, ["X-CSRF-Token: {$adminCsrfToken}"]);
assertTest("Admin can create event via API (201 Created)", $createRes['code'] === 201 && $createRes['json']['success'] === true);
$createdId = $createRes['json']['data']['event_id'] ?? 0;
assertTest("Created event returns new ID (#{$createdId})", $createdId > 0);

// UPDATE EVENT (PUT)
$updatedTitle = 'Updated Fetch API Symposium ' . time();
$updateRes = httpReq("{$baseUrl}/api/events.php", 'POST', [
    '_method'     => 'PUT',
    'action'      => 'update',
    'event_id'    => $createdId,
    'event_name'  => $updatedTitle,
    'category'    => 'Workshop',
    'event_date'  => '2026-12-26',
    'event_time'  => '15:00',
    'venue'       => 'Advanced Computing Lab',
    'status'      => 'Closed',
    'description' => 'Updated description via Fetch API.',
    'csrf_token'  => $adminCsrfToken
], $cookieFileAdmin, ["X-CSRF-Token: {$adminCsrfToken}"]);
assertTest("Admin can update event via API (200 OK)", $updateRes['code'] === 200 && $updateRes['json']['success'] === true);

// Verify update in DB
$verifyUpdate = httpReq("{$baseUrl}/api/events.php?id={$createdId}", 'GET', null, $cookieFileAdmin);
assertTest("Updated values persisted in database",
    $verifyUpdate['json']['data']['event_name'] === $updatedTitle &&
    $verifyUpdate['json']['data']['category'] === 'Workshop' &&
    $verifyUpdate['json']['data']['status'] === 'Closed'
);

// DELETE EVENT
$deleteRes = httpReq("{$baseUrl}/api/events.php", 'POST', [
    '_method'    => 'DELETE',
    'action'     => 'delete',
    'event_id'   => $createdId,
    'csrf_token' => $adminCsrfToken
], $cookieFileAdmin, ["X-CSRF-Token: {$adminCsrfToken}"]);
assertTest("Admin can delete event via API (200 OK)", $deleteRes['code'] === 200 && $deleteRes['json']['success'] === true);

// Verify event no longer exists
$verifyDelete = httpReq("{$baseUrl}/api/events.php?id={$createdId}", 'GET', null, $cookieFileAdmin);
assertTest("Deleted event is no longer accessible (404 Not Found)", $verifyDelete['code'] === 404);

// TEST 4: Poster Upload Compatibility
echo "\n--- TEST 4: Poster Upload Compatibility (FormData / Multipart) ---\n";
// Copy existing valid poster image for test upload
$testImgPath = __DIR__ . '/test_poster.jpg';
$sampleSrc = dirname(__DIR__) . '/uploads/posters/poster_dbd9edb4a8e438c35cf0_1792741178.jpg';
copy($sampleSrc, $testImgPath);

$cFile = new CURLFile($testImgPath, 'image/jpeg', 'test_poster.jpg');
$posterEventTitle = 'Poster Test Event ' . time();

$createWithPosterRes = httpReq("{$baseUrl}/api/events.php", 'POST', [
    'action'      => 'create',
    'event_name'  => $posterEventTitle,
    'category'    => 'Technical',
    'event_date'  => '2026-11-30',
    'event_time'  => '10:00',
    'venue'       => 'Seminar Hall 1',
    'status'      => 'Open',
    'description' => 'Testing poster file upload with Fetch API.',
    'poster'      => $cFile,
    'csrf_token'  => $adminCsrfToken
], $cookieFileAdmin, ["X-CSRF-Token: {$adminCsrfToken}"]);

assertTest("Event created with poster image (201 Created)", $createWithPosterRes['code'] === 201 && !empty($createWithPosterRes['json']['data']['poster']));
$posterEvtId = $createWithPosterRes['json']['data']['event_id'] ?? 0;
$uploadedPosterName = $createWithPosterRes['json']['data']['poster'] ?? '';

$savedPosterPath = dirname(__DIR__) . '/uploads/posters/' . $uploadedPosterName;
assertTest("Poster file physically stored in uploads/posters/", file_exists($savedPosterPath));

// Test poster preservation when updating without selecting a new poster
$updatePreservePosterRes = httpReq("{$baseUrl}/api/events.php", 'POST', [
    '_method'     => 'PUT',
    'action'      => 'update',
    'event_id'    => $posterEvtId,
    'event_name'  => $posterEventTitle . ' (Preserved Poster)',
    'category'    => 'Technical',
    'event_date'  => '2026-11-30',
    'event_time'  => '11:00',
    'venue'       => 'Seminar Hall 1',
    'status'      => 'Open',
    'csrf_token'  => $adminCsrfToken
], $cookieFileAdmin, ["X-CSRF-Token: {$adminCsrfToken}"]);

assertTest("Updating event without file preserves existing poster",
    $updatePreservePosterRes['code'] === 200 &&
    $updatePreservePosterRes['json']['data']['poster'] === $uploadedPosterName &&
    file_exists($savedPosterPath)
);

// Test invalid file rejection (e.g., text file)
$fakeFilePath = __DIR__ . '/fake_image.txt';
file_put_contents($fakeFilePath, 'This is not an image file.');
$fakeCFile = new CURLFile($fakeFilePath, 'text/plain', 'fake_image.jpg');

$rejectInvalidFileRes = httpReq("{$baseUrl}/api/events.php", 'POST', [
    'action'      => 'create',
    'event_name'  => 'Invalid File Event',
    'category'    => 'Technical',
    'event_date'  => '2026-11-30',
    'event_time'  => '10:00',
    'venue'       => 'Seminar Hall 1',
    'status'      => 'Open',
    'poster'      => $fakeCFile,
    'csrf_token'  => $adminCsrfToken
], $cookieFileAdmin, ["X-CSRF-Token: {$adminCsrfToken}"]);

assertTest("Invalid MIME / non-image file is rejected (400 Bad Request)",
    $rejectInvalidFileRes['code'] === 400 && $rejectInvalidFileRes['json']['success'] === false
);
@unlink($fakeFilePath);

// Test oversized file rejection (> 2 MB)
$largeFilePath = __DIR__ . '/large_file.jpg';
$largeFp = fopen($largeFilePath, 'w');
fseek($largeFp, 2.5 * 1024 * 1024); // 2.5 MB
fwrite($largeFp, '0');
fclose($largeFp);
$largeCFile = new CURLFile($largeFilePath, 'image/jpeg', 'large_file.jpg');

$rejectOversizedRes = httpReq("{$baseUrl}/api/events.php", 'POST', [
    'action'      => 'create',
    'event_name'  => 'Oversized File Event',
    'category'    => 'Technical',
    'event_date'  => '2026-11-30',
    'event_time'  => '10:00',
    'venue'       => 'Seminar Hall 1',
    'status'      => 'Open',
    'poster'      => $largeCFile,
    'csrf_token'  => $adminCsrfToken
], $cookieFileAdmin, ["X-CSRF-Token: {$adminCsrfToken}"]);

assertTest("Oversized file (>2MB) is rejected (400 Bad Request)",
    $rejectOversizedRes['code'] === 400 && $rejectOversizedRes['json']['success'] === false
);
@unlink($largeFilePath);

// Clean up poster test event
$delPosterEvt = httpReq("{$baseUrl}/api/events.php", 'POST', [
    '_method'    => 'DELETE',
    'action'     => 'delete',
    'event_id'   => $posterEvtId,
    'csrf_token' => $adminCsrfToken
], $cookieFileAdmin, ["X-CSRF-Token: {$adminCsrfToken}"]);
assertTest("Poster event deleted successfully", $delPosterEvt['code'] === 200);
assertTest("Poster physical file safely unlinked after deletion", !file_exists($savedPosterPath));

@unlink($testImgPath);

// TEST 5: Non-Regression on Practicals 10 & 11
echo "\n--- TEST 5: Regression Check on Earlier Practicals ---\n";
$studentsPage = httpReq("{$baseUrl}/pages/students.php", 'GET', null, $cookieFileAdmin);
assertTest("Practical 11 Student CRUD page accessible (200 OK)", $studentsPage['code'] === 200 && strpos($studentsPage['body'], 'STUDENT MANAGEMENT') !== false);

$adminDashPage = httpReq("{$baseUrl}/pages/admin_dashboard.php", 'GET', null, $cookieFileAdmin);
assertTest("Practical 10 Admin Dashboard accessible (200 OK)", $adminDashPage['code'] === 200 && strpos($adminDashPage['body'], 'ADMIN DASHBOARD') !== false);

@unlink($cookieFileUser);
@unlink($cookieFileAdmin);

echo "\n======================================================\n";
echo "Test Results: {$passCount} PASSED, {$failCount} FAILED\n";
echo "======================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
