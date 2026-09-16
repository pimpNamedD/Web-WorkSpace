<?php
/**
 * FFMS (Field Ledger) - Farmer Sign In
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (current_user()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$email_val = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please refresh the page and try again.';
    } else {
        $email_val = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if (empty($email_val) || empty($password)) {
            $error = 'Please enter both your email address and password.';
        } else {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email_val]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                login_user($user);
                set_flash('green', 'Welcome back to your field ledger, ' . $user['full_name'] . '.');
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Invalid credentials. Please verify your email and password, or use the demo login.';
            }
        }
    }
}

$page_title = 'Farmer Sign In';
include __DIR__ . '/includes/header.php';
?>

<div class="ledger-container" style="max-width: 540px; margin-top: 20px;">
    <div class="ledger-card border-green">
        <div class="card-header-ruled">
            <div>
                <span class="folio-tag">FOLIO AUTH &bull; IDENTIFICATION</span>
                <h2 style="margin-top: 6px;">Sign In to Field Ledger</h2>
            </div>
            <span class="stamp-badge stamp-green">[ VERIFIED ]</span>
        </div>

        <?php if ($error): ?>
        <div class="ledger-flash flash-red" style="margin-bottom: 18px;">
            <span class="flash-tag">NOTICE:</span>
            <span class="flash-text"><?php echo sanitize($error); ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="ledger-form">
            <?php echo csrf_field(); ?>

            <div class="form-group">
                <label for="email">Farmer Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email" class="form-control input-mono" required 
                       value="<?php echo sanitize($email_val); ?>" placeholder="e.g. dalitso@fieldledger.zm">
            </div>

            <div class="form-group">
                <label for="password">Ledger Passcode <span class="required">*</span></label>
                <input type="password" id="password" name="password" class="form-control input-mono" required
                       placeholder="Enter your password">
            </div>

            <div style="margin-top: 6px;">
                <button type="submit" class="ledger-btn ledger-btn-primary" style="width: 100%; padding: 11px;">
                    Open Farm Folio &rarr;
                </button>
            </div>
        </form>

        <div style="margin-top: 22px; padding-top: 18px; border-top: 1px dashed var(--border-rule); background: var(--paper-subtle); padding: 14px; border-radius: 2px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="font-family: var(--font-mono); font-size: 11px; font-weight: 700; color: var(--stamp-green);">
                    [ DEMO CREDENTIALS ]
                </span>
                <button type="button" class="ledger-btn ledger-btn-sm" onclick="fillDemo()">Use Demo Account</button>
            </div>
            <p style="font-size: 12.5px; color: var(--ink-muted); margin-bottom: 0;">
                Email: <code>dalitso@fieldledger.zm</code><br>
                Password: <code>password123</code>
            </p>
        </div>

        <div style="margin-top: 16px; text-align: center; font-size: 13.5px;">
            Don't have a farm folio registered? 
            <a href="register.php" style="font-weight: 600;">Register a new farm here &rarr;</a>
        </div>
    </div>
</div>

<script>
function fillDemo() {
    document.getElementById('email').value = 'dalitso@fieldledger.zm';
    document.getElementById('password').value = 'password123';
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
