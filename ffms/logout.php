<?php
/**
 * FFMS (Field Ledger) - Farmer Sign Out
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

logout_user();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
set_flash('navy', 'You have been safely signed out of your farm ledger.');

header('Location: login.php');
exit;
