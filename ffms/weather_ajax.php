<?php
/**
 * FFMS (Field Ledger) - Weather Refresh Handler (GET & AJAX)
 * 
 * Handles manual weather observation refreshes for farm folios.
 * Supports both standard browser redirect navigation and asynchronous JSON polling.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/weather_service.php';

login_required();
$user = current_user();
$user_id = $user['id'];

$farm_id = (int) ($_GET['farm_id'] ?? 0);
$redirect = trim($_GET['redirect'] ?? '');

// Verify farm holding ownership
$stmt = $pdo->prepare('SELECT * FROM farms WHERE id = ? AND user_id = ?');
$stmt->execute([$farm_id, $user_id]);
$farm = $stmt->fetch();

// Check if client expects JSON response
$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_GET['format']) && $_GET['format'] === 'json');

if (!$farm) {
    if ($is_ajax) {
        header('Content-Type: application/json', true, 404);
        echo json_encode(['error' => 'Farm holding not found or unauthorized.']);
        exit;
    }
    set_flash('red', 'Farm holding not found or unauthorized.');
    header('Location: ' . base_url('dashboard.php'));
    exit;
}

// Force-refresh weather observation from OpenWeatherMap or update cache
$weather_data = get_farm_weather($pdo, $farm, true);

if ($is_ajax) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'weather' => $weather_data
    ]);
    exit;
}

// Set descriptive ledger bookmark flash alert
if ($weather_data['source'] === 'live_api') {
    set_flash('green', 'Weather observation for ' . $farm['farm_name'] . ' refreshed live from OpenWeatherMap (' . $weather_data['city'] . ')!');
} elseif (!empty($weather_data['notice'])) {
    set_flash('amber', $weather_data['notice']);
} else {
    set_flash('navy', 'Weather observation for ' . $farm['farm_name'] . ' updated (' . $weather_data['city'] . ').');
}

// Safe redirect back to caller page
if (!empty($redirect) && !str_starts_with($redirect, 'http://') && !str_starts_with($redirect, 'https://')) {
    header('Location: ' . $redirect);
} else {
    header('Location: ' . base_url('farm_view.php?id=' . $farm_id));
}
exit;
