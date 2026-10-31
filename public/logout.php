<?php
/**
 * Mini Enterprise Inventory & Financial Ledger Engine
 * Logout Endpoint
 */

require_once __DIR__ . '/../includes/auth.php';

// Terminate session and remove cookies
logout();

// Redirect to login screen with notification
header('Location: /login.php?logged_out=1');
exit;

