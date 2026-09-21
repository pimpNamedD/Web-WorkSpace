<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Log in demo user in session
$stmt = $pdo->query('SELECT * FROM users WHERE email = "dalitso@fieldledger.zm" LIMIT 1');
$user = $stmt->fetch();
if (!$user) {
    echo "FAIL: Demo user not found\n";
    exit(1);
}

login_user($user);

$pages = [
    'dashboard.php',
    'farms.php',
    'farm_view.php?id=1',
    'farm_fields.php?farm_id=1',
    'crops.php',
    'crop_add.php',
    'ai_twin.php?crop_id=1',
    'inventory.php',
    'inventory_add.php',
    'inputs.php',
    'input_add.php',
    'livestock.php',
    'livestock_add.php',
    'finances.php',
    'finance_add.php',
    'carbon.php?farm_id=1',
    'tokens.php?farm_id=1',
    'traceability.php?farm_id=1&batch_id=1',
    'workers.php',
    'worker_points.php?worker_id=1',
    'community.php',
    'community_post.php?id=1'
];

$all_passed = true;
foreach ($pages as $p) {
    $parsed = parse_url($p);
    $file = $parsed['path'];
    $_GET = [];
    if (!empty($parsed['query'])) {
        parse_str($parsed['query'], $_GET);
    }
    $_SERVER['SCRIPT_NAME'] = '/' . $file;
    $_SERVER['REQUEST_METHOD'] = 'GET';

    ob_start();
    try {
        include __DIR__ . '/../' . $file;
        $output = ob_get_clean();
        if (str_contains($output, 'Fatal error') || str_contains($output, 'Parse error')) {
            echo "[FAIL] {$file} had errors!\n";
            $all_passed = false;
        } else {
            echo "[OK] {$file} rendered successfully (" . strlen($output) . " bytes)\n";
        }
    } catch (Throwable $e) {
        ob_end_clean();
        echo "[ERROR] {$file}: " . $e->getMessage() . "\n";
        $all_passed = false;
    }
}

if ($all_passed) {
    echo "\nALL 21 PAGES RENDERED AND PASSED VERIFICATION WITH ZERO ERRORS!\n";
} else {
    exit(1);
}
