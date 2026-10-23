<?php
/**
 * StudentHub: Event Management Helpers (Practicals 12 & 13)
 * Shared validation, poster upload processing, and safe file cleanup.
 */

/**
 * Validates event fields from client input.
 *
 * @param array $data Input key-value pairs
 * @param bool $isUpdate True if validating an update (requires valid event_id)
 * @return array List of error strings (empty if valid)
 */
function validateEventData(array $data, bool $isUpdate = false): array {
    $errors = [];

    if ($isUpdate) {
        $id = (int)($data['event_id'] ?? $data['id'] ?? 0);
        if ($id <= 0) {
            $errors[] = 'A valid positive event ID is required for updating an event.';
        }
    }

    $eventName = trim(strip_tags($data['event_name'] ?? ''));
    if (empty($eventName) || mb_strlen($eventName) < 3 || mb_strlen($eventName) > 150) {
        $errors[] = 'Event title is required and must be between 3 and 150 characters.';
    }

    $category = trim(strip_tags($data['category'] ?? 'Technical'));
    $validCategories = ['Technical', 'Cultural', 'Sports', 'Literary', 'Academic', 'Workshop', 'Other'];
    if (!in_array($category, $validCategories, true)) {
        $errors[] = 'Please select a valid event category.';
    }

    $eventDate = trim($data['event_date'] ?? '');
    if (empty($eventDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
        $errors[] = 'A valid event date (YYYY-MM-DD) is required.';
    } else {
        // Verify calendar date validity
        [$year, $month, $day] = explode('-', $eventDate);
        if (!checkdate((int)$month, (int)$day, (int)$year)) {
            $errors[] = 'Specified event date is not a valid calendar date.';
        }
    }

    $eventTime = trim($data['event_time'] ?? '10:00');
    if (!empty($eventTime) && !preg_match('/^(?:2[0-3]|[01][0-9]):[0-5][0-9](?::[0-5][0-9])?$/', $eventTime)) {
        $errors[] = 'A valid event time (HH:MM or HH:MM:SS) is required.';
    }

    $venue = trim(strip_tags($data['venue'] ?? ''));
    if (empty($venue) || mb_strlen($venue) < 2 || mb_strlen($venue) > 100) {
        $errors[] = 'Venue is required and must be between 2 and 100 characters.';
    }

    $status = trim($data['status'] ?? 'Open');
    if (!in_array($status, ['Open', 'Closed'], true)) {
        $errors[] = 'Event status must be either Open or Closed.';
    }

    return $errors;
}

/**
 * Normalizes time string to standard MySQL TIME format (HH:MM:SS).
 *
 * @param string|null $time
 * @return string
 */
function normalizeEventTime(?string $time): string {
    $time = trim($time ?? '');
    if (empty($time)) {
        return '10:00:00';
    }
    if (strlen($time) === 5) {
        return $time . ':00';
    }
    return $time;
}

/**
 * Validates and securely stores an uploaded poster image.
 *
 * Enforces:
 *  1. Upload error code verification.
 *  2. Maximum 2 MB file size limit (2,097,152 bytes).
 *  3. Allowed extensions: jpg, jpeg, png, webp.
 *  4. Server-side MIME inspection via finfo (magic bytes).
 *  5. Image integrity inspection via getimagesize().
 *  6. Randomized unique server-side filename (prevents collision & directory traversal).
 *
 * @param array $file The $_FILES['poster'] entry
 * @param string $uploadDir Target filesystem directory
 * @param string|null &$storedFilename Output variable for unique stored filename
 * @param array &$errors Output array of error strings
 * @return bool True if upload succeeded or no file was uploaded; False on validation failure
 */
function handlePosterUpload(array $file, string $uploadDir, ?string &$storedFilename, array &$errors): bool {
    $storedFilename = null;

    // Check if user did not provide an optional file
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return true; // Optional file omitted
    }

    // 1. Verify upload error code
    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errors[] = 'Poster file exceeds server upload size limit (maximum 2 MB).';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errors[] = 'Poster upload was interrupted. Please try again.';
                break;
            default:
                $errors[] = 'File upload failed with system error code [' . (int)$file['error'] . '].';
        }
        return false;
    }

    // 2. Validate file size (Max 2 MB = 2,097,152 bytes)
    $maxBytes = 2 * 1024 * 1024;
    if ($file['size'] > $maxBytes || filesize($file['tmp_name']) > $maxBytes) {
        $errors[] = 'Poster file exceeds the maximum allowed size of 2 MB.';
        return false;
    }

    // 3. Validate client file extension
    $clientFilename = $file['name'] ?? '';
    $extension = strtolower(pathinfo($clientFilename, PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($extension, $allowedExtensions, true)) {
        $errors[] = 'Invalid file extension. Only JPG, JPEG, PNG, and WebP images are allowed.';
        return false;
    }

    // 4. Server-side MIME verification using finfo (magic bytes inspection)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $realMime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimes = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png'  => ['png'],
        'image/webp' => ['webp']
    ];

    if (!array_key_exists($realMime, $allowedMimes)) {
        $errors[] = 'Invalid file type (' . htmlspecialchars($realMime, ENT_QUOTES, 'UTF-8') . '). Uploaded file is not a valid JPEG, PNG, or WebP image.';
        return false;
    }

    // 5. Verify image validity and dimensions via getimagesize()
    $imgDetails = @getimagesize($file['tmp_name']);
    if ($imgDetails === false || empty($imgDetails[0]) || empty($imgDetails[1])) {
        $errors[] = 'Corrupt or unreadable image file. Please upload a valid image.';
        return false;
    }

    // 6. Generate secure, unique server-side filename
    $canonicalExt = ($realMime === 'image/jpeg') ? 'jpg' : ($realMime === 'image/png' ? 'png' : 'webp');
    $uniqueName   = 'poster_' . bin2hex(random_bytes(10)) . '_' . time() . '.' . $canonicalExt;

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $uniqueName;

    // 7. Move uploaded file to secure storage directory
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        $errors[] = 'Failed to move uploaded poster to storage directory. Please verify directory permissions.';
        return false;
    }

    // Set safe file permissions (read-only for others, no execute)
    chmod($targetPath, 0644);

    $storedFilename = $uniqueName;
    return true;
}

/**
 * Safely unlinks a poster physical file if no other event references it.
 *
 * @param mysqli $conn Active MySQLi database connection
 * @param string|null $posterFilename Filename stored in database
 * @param string $uploadDir Storage directory
 * @param int|null $excludeEventId Event ID to exclude from reference count
 */
function safelyDeletePosterFile(mysqli $conn, ?string $posterFilename, string $uploadDir, ?int $excludeEventId = null): void {
    if (empty($posterFilename)) {
        return;
    }

    $safeName = basename($posterFilename);

    if ($excludeEventId !== null) {
        $stmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM `events` WHERE `poster` = ? AND `event_id` != ?');
        $stmt->bind_param('si', $safeName, $excludeEventId);
    } else {
        $stmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM `events` WHERE `poster` = ?');
        $stmt->bind_param('s', $safeName);
    }

    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // If no other record references this poster, delete the physical file
    if ((int)($res['cnt'] ?? 0) === 0) {
        $realUploadDir = realpath($uploadDir);
        $targetFile    = $realUploadDir . DIRECTORY_SEPARATOR . $safeName;
        if ($realUploadDir && file_exists($targetFile) && strpos($targetFile, $realUploadDir) === 0) {
            @unlink($targetFile);
        }
    }
}
