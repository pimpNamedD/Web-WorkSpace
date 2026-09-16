<?php
/**
 * FFMS (Field Ledger) - Database Connection
 * Free-Tier XAMPP / MariaDB / MySQL Configuration
 */

declare(strict_types=1);

if (defined('DB_CONNECTED')) {
    return;
}

$db_host = getenv('DB_HOST') ?: '127.0.0.1';
$db_port = getenv('DB_PORT') ?: '3306';
$db_name = getenv('DB_NAME') ?: 'ffms_db';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

$pdo = null;

// Attempt standard port first, then fallback to alternate XAMPP port 3307 if 3306 fails
$ports_to_try = array_unique([$db_port, '3306', '3307']);

$last_error = null;
foreach ($ports_to_try as $port) {
    try {
        $dsn = "mysql:host={$db_host};port={$port};dbname={$db_name};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];
        $pdo = new PDO($dsn, $db_user, $db_pass, $options);
        break; // Connected successfully!
    } catch (PDOException $e) {
        $last_error = $e;
    }
}

if (!$pdo) {
    // Graceful error display adhering to Field Ledger aesthetics
    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Database Offline - FFMS Field Ledger</title>
        <style>
            body { font-family: 'Courier New', monospace; background: #fbf8f1; color: #241e17; padding: 40px; }
            .box { max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-left: 6px solid #a64b2a; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
            h2 { font-family: Georgia, serif; color: #a64b2a; margin-top: 0; }
            code { background: #f4ede0; padding: 2px 6px; }
        </style>
    </head>
    <body>
        <div class="box">
            <h2>[ FIELD LEDGER : DATABASE UNREACHABLE ]</h2>
            <p>Could not connect to the local MariaDB/MySQL database <code><?php echo htmlspecialchars($db_name); ?></code>.</p>
            <p><strong>Troubleshooting:</strong></p>
            <ul>
                <li>Ensure the MySQL service is started in your <strong>XAMPP Control Panel</strong>.</li>
                <li>Import <code>sql/schema.sql</code> into phpMyAdmin or run the bootstrap script.</li>
                <li>Verify your database credentials in <code>config/db.php</code>.</li>
            </ul>
            <p><small>Diagnostic: <?php echo htmlspecialchars($last_error ? $last_error->getMessage() : 'Unknown error'); ?></small></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

define('DB_CONNECTED', true);
