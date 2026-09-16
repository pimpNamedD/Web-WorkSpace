<?php
/**
 * FFMS (Field Ledger) - Authentication & Security Guards
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    // Standard session configuration
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

/**
 * Returns the currently authenticated user session or null
 */
function current_user(): ?array {
    if (!empty($_SESSION['user_id']) && !empty($_SESSION['user_email'])) {
        return [
            'id'                => (int)$_SESSION['user_id'],
            'full_name'         => $_SESSION['user_name'] ?? 'Farmer',
            'email'             => $_SESSION['user_email'],
            'phone'             => $_SESSION['user_phone'] ?? '',
            'location_district' => $_SESSION['user_district'] ?? 'Lusaka'
        ];
    }
    return null;
}

/**
 * Ensures user is authenticated. Redirects to login with a return notice if not.
 */
function login_required(string $redirect_to = 'login.php'): void {
    if (!current_user()) {
        $_SESSION['flash'] = [
            'type'    => 'amber',
            'message' => 'Please sign in to access the Field Ledger records.'
        ];
        header('Location: ' . $redirect_to);
        exit;
    }
}

/**
 * Authenticates a user into the session
 */
function login_user(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id']       = (int)$user['id'];
    $_SESSION['user_name']     = $user['full_name'];
    $_SESSION['user_email']    = $user['email'];
    $_SESSION['user_phone']    = $user['phone'] ?? '';
    $_SESSION['user_district'] = $user['location_district'] ?? 'Lusaka';
}

/**
 * Logs out the current user and clears session data
 */
function logout_user(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Generates or retrieves a CSRF token for the current session
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Outputs a hidden CSRF token input field
 */
function csrf_field(): string {
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Verifies that a submitted CSRF token matches the session token
 */
function verify_csrf(): bool {
    $submitted = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (empty($submitted) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $submitted);
}

/**
 * Strictly verifies that a given farm belongs to the authenticated user.
 * Returns the farm row if valid, or terminates with 403 Forbidden.
 */
function verify_farm_owner(PDO $pdo, int $farm_id, int $user_id): array {
    $stmt = $pdo->prepare('SELECT * FROM farms WHERE id = ? AND user_id = ?');
    $stmt->execute([$farm_id, $user_id]);
    $farm = $stmt->fetch();

    if (!$farm) {
        http_response_code(403);
        include_once __DIR__ . '/header.php';
        ?>
        <div class="ledger-container">
            <div class="ledger-card border-red" style="margin-top: 40px;">
                <div class="ledger-stamp stamp-red">[ ACCESS RESTRICTED ]</div>
                <h2 style="margin-top:0;">Ledger Entry Not Found or Access Denied</h2>
                <p>This farm record does not exist or you do not have permission to view or modify this folio.</p>
                <div style="margin-top: 20px;">
                    <a href="farms.php" class="ledger-btn">Return to My Farms</a>
                </div>
            </div>
        </div>
        <?php
        include_once __DIR__ . '/footer.php';
        exit;
    }

    return $farm;
}
