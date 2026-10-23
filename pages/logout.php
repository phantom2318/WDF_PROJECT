<?php
/**
 * StudentHub: Logout Action
 * Destroys session, revokes persistent remember-me tokens, and clears cookies
 */

require_once __DIR__ . '/../scripts/auth.php';

logoutUser();

// Redirect to login page with clear logout notification
header('Location: login.php?logout=success');
exit;
