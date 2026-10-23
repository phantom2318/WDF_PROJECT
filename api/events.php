<?php
/**
 * StudentHub: Event Management RESTful JSON API (Practical 13)
 * Provides asynchronous endpoints for listing, adding, updating, and deleting events.
 *
 * Supported Operations:
 *  - GET    /api/events.php             : List events with live search & filters (or ?id= for single event)
 *  - POST   /api/events.php             : Create event (multipart/form-data with poster or application/json)
 *  - PUT    /api/events.php             : Update event (or POST with _method=PUT / action=update)
 *  - DELETE /api/events.php             : Delete event (or POST with _method=DELETE / action=delete)
 */

// Buffer all outputs to guarantee clean JSON response without PHP warnings leaking
ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

/**
 * Standardized JSON response emitter.
 *
 * @param bool $success Operation status
 * @param string $message User-friendly message
 * @param mixed $data Response payload or null
 * @param int $statusCode HTTP response status code
 */
function sendJsonResponse(bool $success, string $message, $data = null, int $statusCode = 200): void {
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// Global exception and error fallback
set_exception_handler(function(Throwable $e) {
    sendJsonResponse(false, 'Server error: An unexpected error occurred. Please try again.', null, 500);
});

require_once __DIR__ . '/../scripts/auth.php';
require_once __DIR__ . '/../scripts/db.php';
require_once __DIR__ . '/../scripts/events_helper.php';
require_once __DIR__ . '/../scripts/audit_helper.php';

// Enforce authentication for all API access
if (!isset($_SESSION['user_id'])) {
    sendJsonResponse(false, 'Unauthorized. Please sign in to access events.', null, 401);
}

// Upload directory for posters
$uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'posters';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Determine effective HTTP request method (supporting method override & action parameters)
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Parse request payload
$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true);
$payload   = is_array($jsonInput) ? $jsonInput : $_POST;

// Check method overrides
if ($method === 'POST') {
    if (isset($payload['_method'])) {
        $override = strtoupper(trim($payload['_method']));
        if (in_array($override, ['PUT', 'DELETE', 'PATCH'], true)) {
            $method = $override;
        }
    } elseif (!empty($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
        $override = strtoupper(trim($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']));
        if (in_array($override, ['PUT', 'DELETE', 'PATCH'], true)) {
            $method = $override;
        }
    } elseif (isset($payload['action'])) {
        $action = strtolower(trim($payload['action']));
        if ($action === 'update') {
            $method = 'PUT';
        } elseif ($action === 'delete') {
            $method = 'DELETE';
        }
    }
}

// =========================================================================
// 1. GET: LIST EVENTS OR FETCH SINGLE EVENT
// =========================================================================
if ($method === 'GET') {
    $singleId = (int)($_GET['id'] ?? $_GET['event_id'] ?? 0);

    // Single event lookup
    if ($singleId > 0) {
        $stmt = $conn->prepare('
            SELECT `event_id`, `event_name`, `category`, `event_date`, `event_time`, `venue`, `description`, `poster`, `status`, `created_at`, `updated_at`
            FROM `events`
            WHERE `event_id` = ?
        ');
        if (!$stmt) {
            sendJsonResponse(false, 'Database query preparation failed.', null, 500);
        }
        $stmt->bind_param('i', $singleId);
        $stmt->execute();
        $event = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$event) {
            sendJsonResponse(false, "Event #{$singleId} does not exist.", null, 404);
        }

        // Add formatted date and time for client display convenience
        $event['formatted_date'] = date('D, M j, Y', strtotime($event['event_date']));
        $event['formatted_time'] = !empty($event['event_time']) ? date('h:i A', strtotime($event['event_time'])) : 'TBA';
        $event['poster_url']     = !empty($event['poster']) ? '../uploads/posters/' . $event['poster'] : null;

        sendJsonResponse(true, 'Event details retrieved successfully.', $event, 200);
    }

    // List events with search & filters
    $search         = trim($_GET['search'] ?? '');
    $categoryFilter = trim($_GET['category'] ?? '');
    $statusFilter   = trim($_GET['status'] ?? '');

    $whereConditions = [];
    $paramTypes      = '';
    $bindParams      = [];

    // Search by title, venue, or description
    if ($search !== '') {
        $whereConditions[] = '(`event_name` LIKE ? OR `venue` LIKE ? OR `description` LIKE ?)';
        $wild = '%' . $search . '%';
        $paramTypes   .= 'sss';
        $bindParams[] = $wild;
        $bindParams[] = $wild;
        $bindParams[] = $wild;
    }

    // Category filter
    if ($categoryFilter !== '') {
        $whereConditions[] = '`category` = ?';
        $paramTypes   .= 's';
        $bindParams[] = $categoryFilter;
    }

    // Status filter
    if ($statusFilter !== '') {
        $whereConditions[] = '`status` = ?';
        $paramTypes   .= 's';
        $bindParams[] = $statusFilter;
    }

    $query = 'SELECT `event_id`, `event_name`, `category`, `event_date`, `event_time`, `venue`, `description`, `poster`, `status`, `created_at`, `updated_at` FROM `events`';
    if (!empty($whereConditions)) {
        $query .= ' WHERE ' . implode(' AND ', $whereConditions);
    }
    $query .= ' ORDER BY `event_date` ASC, `event_time` ASC';

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        sendJsonResponse(false, 'Database query preparation failed.', null, 500);
    }

    if (!empty($bindParams)) {
        $stmt->bind_param($paramTypes, ...$bindParams);
    }

    $stmt->execute();
    $result     = $stmt->get_result();
    $eventsList = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Augment records with presentation helpers
    foreach ($eventsList as &$evt) {
        $evt['formatted_date'] = date('D, M j, Y', strtotime($evt['event_date']));
        $evt['formatted_time'] = !empty($evt['event_time']) ? date('h:i A', strtotime($evt['event_time'])) : 'TBA';
        $evt['poster_url']     = !empty($evt['poster']) ? '../uploads/posters/' . $evt['poster'] : null;
    }
    unset($evt);

    $msg = count($eventsList) > 0 ? 'Events loaded successfully.' : 'No events found matching your criteria.';
    sendJsonResponse(true, $msg, $eventsList, 200);
}

// =========================================================================
// SECURITY CHECKS FOR STATE-CHANGING OPERATIONS (POST, PUT, DELETE)
// =========================================================================

// 1. Role-Based Access Control: Only administrator can create, update, or delete
if (!isAdmin()) {
    sendJsonResponse(false, 'Access denied. Administrator privileges are required to perform this action.', null, 403);
}

// 2. CSRF Token Verification
$submittedCsrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $payload['csrf_token'] ?? $_GET['csrf_token'] ?? '';
if (!validateCsrfToken($submittedCsrf)) {
    sendJsonResponse(false, 'Security validation failed: Invalid or expired CSRF token.', null, 403);
}

// =========================================================================
// 2. POST: CREATE A NEW EVENT
// =========================================================================
if ($method === 'POST') {
    $eventName   = trim(strip_tags($payload['event_name'] ?? ''));
    $category    = trim(strip_tags($payload['category'] ?? 'Technical'));
    $eventDate   = trim($payload['event_date'] ?? '');
    $eventTime   = normalizeEventTime($payload['event_time'] ?? '10:00');
    $venue       = trim(strip_tags($payload['venue'] ?? ''));
    $status      = trim($payload['status'] ?? 'Open');
    $description = trim(strip_tags($payload['description'] ?? ''));

    // Validate inputs
    $errors = validateEventData([
        'event_name' => $eventName,
        'category'   => $category,
        'event_date' => $eventDate,
        'event_time' => $eventTime,
        'venue'      => $venue,
        'status'     => $status
    ], false);

    if (!empty($errors)) {
        sendJsonResponse(false, implode(' ', $errors), null, 400);
    }

    // Handle poster upload
    $newPoster = null;
    if (!empty($_FILES['poster']) && $_FILES['poster']['error'] !== UPLOAD_ERR_NO_FILE) {
        if (!handlePosterUpload($_FILES['poster'], $uploadDir, $newPoster, $errors)) {
            sendJsonResponse(false, implode(' ', $errors), null, 400);
        }
    }

    try {
        $stmt = $conn->prepare('
            INSERT INTO `events` (`event_name`, `category`, `event_date`, `event_time`, `venue`, `description`, `poster`, `status`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        if (!$stmt) {
            throw new Exception($conn->error);
        }

        $stmt->bind_param('ssssssss', $eventName, $category, $eventDate, $eventTime, $venue, $description, $newPoster, $status);
        $execOk = $stmt->execute();
        $newId  = $stmt->insert_id;
        $stmt->close();

        if ($execOk && $newId > 0) {
            // Audit Logging (Practical 14)
            logAuditAction('CREATE_EVENT', 'event', (string)$newId, "Created event '{$eventName}' (#{$newId}) via JSON API, Category: {$category}, Date: {$eventDate}");

            $createdEvent = [
                'event_id'       => $newId,
                'event_name'     => $eventName,
                'category'       => $category,
                'event_date'     => $eventDate,
                'event_time'     => $eventTime,
                'venue'          => $venue,
                'description'    => $description,
                'poster'         => $newPoster,
                'status'         => $status,
                'formatted_date' => date('D, M j, Y', strtotime($eventDate)),
                'formatted_time' => date('h:i A', strtotime($eventTime)),
                'poster_url'     => !empty($newPoster) ? '../uploads/posters/' . $newPoster : null
            ];
            sendJsonResponse(true, "Event '{$eventName}' created successfully!", $createdEvent, 201);
        } else {
            if (!empty($newPoster)) {
                @unlink($uploadDir . DIRECTORY_SEPARATOR . $newPoster);
            }
            sendJsonResponse(false, 'Failed to insert event into database.', null, 500);
        }
    } catch (Exception $e) {
        if (!empty($newPoster)) {
            @unlink($uploadDir . DIRECTORY_SEPARATOR . $newPoster);
        }
        sendJsonResponse(false, 'Database error while creating event. Please try again.', null, 500);
    }
}

// =========================================================================
// 3. PUT / POST: UPDATE AN EXISTING EVENT
// =========================================================================
if ($method === 'PUT') {
    $eventId     = (int)($payload['event_id'] ?? $payload['id'] ?? $_GET['id'] ?? 0);
    $eventName   = trim(strip_tags($payload['event_name'] ?? ''));
    $category    = trim(strip_tags($payload['category'] ?? 'Technical'));
    $eventDate   = trim($payload['event_date'] ?? '');
    $eventTime   = normalizeEventTime($payload['event_time'] ?? '10:00');
    $venue       = trim(strip_tags($payload['venue'] ?? ''));
    $status      = trim($payload['status'] ?? 'Open');
    $description = trim(strip_tags($payload['description'] ?? ''));

    $errors = validateEventData([
        'event_id'   => $eventId,
        'event_name' => $eventName,
        'category'   => $category,
        'event_date' => $eventDate,
        'event_time' => $eventTime,
        'venue'      => $venue,
        'status'     => $status
    ], true);

    if (!empty($errors)) {
        sendJsonResponse(false, implode(' ', $errors), null, 400);
    }

    try {
        // Inspect current event to verify existence and existing poster
        $checkStmt = $conn->prepare('SELECT `event_id`, `event_name`, `poster` FROM `events` WHERE `event_id` = ?');
        $checkStmt->bind_param('i', $eventId);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if (!$existing) {
            sendJsonResponse(false, "Event #{$eventId} does not exist.", null, 404);
        }

        $currentPoster     = $existing['poster'];
        $posterToSave      = $currentPoster; // Preserves existing poster by default
        $newPosterUploaded = false;

        // Handle replacement poster if provided
        if (!empty($_FILES['poster']) && $_FILES['poster']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadedName = null;
            if (handlePosterUpload($_FILES['poster'], $uploadDir, $uploadedName, $errors)) {
                if (!empty($uploadedName)) {
                    $posterToSave      = $uploadedName;
                    $newPosterUploaded = true;
                }
            } else {
                sendJsonResponse(false, implode(' ', $errors), null, 400);
            }
        }

        // Update database record
        $updateStmt = $conn->prepare('
            UPDATE `events`
            SET `event_name`  = ?,
                `category`    = ?,
                `event_date`  = ?,
                `event_time`  = ?,
                `venue`       = ?,
                `description` = ?,
                `poster`      = ?,
                `status`      = ?
            WHERE `event_id` = ?
        ');
        if (!$updateStmt) {
            throw new Exception($conn->error);
        }

        $updateStmt->bind_param('ssssssssi', $eventName, $category, $eventDate, $eventTime, $venue, $description, $posterToSave, $status, $eventId);
        $execOk = $updateStmt->execute();
        $updateStmt->close();

        if ($execOk) {
            // Clean up old poster safely if replaced
            if ($newPosterUploaded && !empty($currentPoster) && $currentPoster !== $posterToSave) {
                safelyDeletePosterFile($conn, $currentPoster, $uploadDir, $eventId);
            }

            // Audit Logging (Practical 14)
            logAuditAction('UPDATE_EVENT', 'event', (string)$eventId, "Updated event '{$eventName}' (#{$eventId}) via JSON API, Category: {$category}, Status: {$status}");

            $updatedEvent = [
                'event_id'       => $eventId,
                'event_name'     => $eventName,
                'category'       => $category,
                'event_date'     => $eventDate,
                'event_time'     => $eventTime,
                'venue'          => $venue,
                'description'    => $description,
                'poster'         => $posterToSave,
                'status'         => $status,
                'formatted_date' => date('D, M j, Y', strtotime($eventDate)),
                'formatted_time' => date('h:i A', strtotime($eventTime)),
                'poster_url'     => !empty($posterToSave) ? '../uploads/posters/' . $posterToSave : null
            ];

            sendJsonResponse(true, "Event '{$eventName}' (#{$eventId}) updated successfully!", $updatedEvent, 200);
        } else {
            if ($newPosterUploaded && !empty($posterToSave)) {
                @unlink($uploadDir . DIRECTORY_SEPARATOR . $posterToSave);
            }
            sendJsonResponse(false, 'Failed to update event in database.', null, 500);
        }
    } catch (Exception $e) {
        if (isset($newPosterUploaded) && $newPosterUploaded && !empty($posterToSave)) {
            @unlink($uploadDir . DIRECTORY_SEPARATOR . $posterToSave);
        }
        sendJsonResponse(false, 'Database error while updating event.', null, 500);
    }
}

// =========================================================================
// 4. DELETE / POST: DELETE AN EVENT
// =========================================================================
if ($method === 'DELETE') {
    $eventId = (int)($payload['event_id'] ?? $payload['id'] ?? $_GET['id'] ?? 0);

    if ($eventId <= 0) {
        sendJsonResponse(false, 'Invalid event ID provided for deletion.', null, 400);
    }

    try {
        $checkStmt = $conn->prepare('SELECT `event_id`, `event_name`, `poster` FROM `events` WHERE `event_id` = ?');
        $checkStmt->bind_param('i', $eventId);
        $checkStmt->execute();
        $eventRecord = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if (!$eventRecord) {
            sendJsonResponse(false, "Event #{$eventId} does not exist or has already been deleted.", null, 404);
        }

        $posterToDelete = $eventRecord['poster'];
        $eventNameDel   = $eventRecord['event_name'];

        // Execute DELETE
        $delStmt = $conn->prepare('DELETE FROM `events` WHERE `event_id` = ?');
        $delStmt->bind_param('i', $eventId);
        $delOk = $delStmt->execute();
        $delStmt->close();

        if ($delOk) {
            // Delete associated poster file only after successful database deletion
            if (!empty($posterToDelete)) {
                safelyDeletePosterFile($conn, $posterToDelete, $uploadDir);
            }

            // Audit Logging (Practical 14)
            logAuditAction('DELETE_EVENT', 'event', (string)$eventId, "Deleted event '{$eventNameDel}' (#{$eventId}) via JSON API");

            sendJsonResponse(true, "Event '{$eventNameDel}' (#{$eventId}) deleted successfully.", [
                'event_id' => $eventId,
                'event_name' => $eventNameDel
            ], 200);
        } else {
            sendJsonResponse(false, 'Unable to delete event record due to database constraints.', null, 500);
        }
    } catch (Exception $e) {
        sendJsonResponse(false, 'Database error occurred during event deletion.', null, 500);
    }
}

// Any other method
sendJsonResponse(false, "Method {$method} not allowed.", null, 405);
