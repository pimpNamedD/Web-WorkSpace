<?php
/**
 * FFMS (Field Ledger) - Farmer Registration
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
$form_data = [
    'full_name'         => '',
    'email'             => '',
    'phone'             => '',
    'location_district' => 'Lusaka',
    'farm_name'         => ''
];

$zambian_districts = [
    'Lusaka', 'Chongwe', 'Chisamba', 'Chibombo', 'Mkushi', 'Kapiri Mposhi', 
    'Kabwe', 'Mazabuka', 'Monze', 'Choma', 'Kalomo', 'Livingstone', 
    'Ndola', 'Kitwe', 'Mpongwe', 'Chipata', 'Petauke', 'Kasama', 'Solwezi', 'Mans'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Session security expired. Please try again.';
    } else {
        $form_data['full_name']         = trim($_POST['full_name'] ?? '');
        $form_data['email']             = trim($_POST['email'] ?? '');
        $form_data['phone']             = trim($_POST['phone'] ?? '');
        $form_data['location_district'] = trim($_POST['location_district'] ?? 'Lusaka');
        $form_data['farm_name']         = trim($_POST['farm_name'] ?? '');
        $password                       = (string)($_POST['password'] ?? '');
        $confirm_password               = (string)($_POST['confirm_password'] ?? '');

        if (empty($form_data['full_name']) || empty($form_data['email']) || empty($password)) {
            $error = 'Full name, email address, and passcode are required.';
        } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Please provide a valid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password should be at least 6 characters long.';
        } elseif ($password !== $confirm_password) {
            $error = 'Password and confirmation do not match.';
        } else {
            // Check email uniqueness
            $check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $check->execute([$form_data['email']]);
            if ($check->fetch()) {
                $error = 'A farm folio with this email already exists. Please sign in instead.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ins = $pdo->prepare('
                    INSERT INTO users (full_name, email, phone, password_hash, location_district)
                    VALUES (?, ?, ?, ?, ?)
                ');
                $ins->execute([
                    $form_data['full_name'],
                    $form_data['email'],
                    $form_data['phone'],
                    $hash,
                    $form_data['location_district']
                ]);
                $new_user_id = (int)$pdo->lastInsertId();

                // Create initial starter farm for this farmer
                $farm_name = !empty($form_data['farm_name']) ? $form_data['farm_name'] : ($form_data['full_name'] . ' Holding');
                $farm_ins = $pdo->prepare('
                    INSERT INTO farms (user_id, farm_name, location, weather_city, size_hectares, farm_type, notes)
                    VALUES (?, ?, ?, ?, 25.00, "mixed", "Initial farm folio opened upon registration.")
                ');
                $farm_ins->execute([
                    $new_user_id,
                    $farm_name,
                    $form_data['location_district'],
                    $form_data['location_district']
                ]);

                // Authenticate and redirect
                $new_user = [
                    'id'                => $new_user_id,
                    'full_name'         => $form_data['full_name'],
                    'email'             => $form_data['email'],
                    'phone'             => $form_data['phone'],
                    'location_district' => $form_data['location_district']
                ];
                login_user($new_user);

                set_flash('green', 'Farm folio created successfully! Your initial farm "' . $farm_name . '" is ready.');
                header('Location: dashboard.php');
                exit;
            }
        }
    }
}

$page_title = 'Register New Farm Folio';
include __DIR__ . '/includes/header.php';
?>

<div class="ledger-container" style="max-width: 640px; margin-top: 20px;">
    <div class="ledger-card border-green">
        <div class="card-header-ruled">
            <div>
                <span class="folio-tag">FOLIO № NEW-REG</span>
                <h2 style="margin-top: 6px;">Register New Farm Ledger Folio</h2>
            </div>
            <span class="stamp-badge stamp-green">[ $0 FREE-TIER ]</span>
        </div>

        <p style="font-size: 13.5px; color: var(--ink-muted); margin-bottom: 20px;">
            Open an official, physical-style digital farm ledger. No credit card required, runs locally on XAMPP or free hosting.
        </p>

        <?php if ($error): ?>
        <div class="ledger-flash flash-red" style="margin-bottom: 18px;">
            <span class="flash-tag">ERROR:</span>
            <span class="flash-text"><?php echo sanitize($error); ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" action="register.php" class="ledger-form">
            <?php echo csrf_field(); ?>

            <div class="form-grid">
                <div class="form-group">
                    <label for="full_name">Farmer / Manager Name <span class="required">*</span></label>
                    <input type="text" id="full_name" name="full_name" class="form-control" required
                           value="<?php echo sanitize($form_data['full_name']); ?>" placeholder="e.g. Dalitso Mwansa">
                </div>

                <div class="form-group">
                    <label for="email">Email Address <span class="required">*</span></label>
                    <input type="email" id="email" name="email" class="form-control input-mono" required
                           value="<?php echo sanitize($form_data['email']); ?>" placeholder="e.g. farmer@fieldledger.zm">
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="phone">Phone Number (MTN / Airtel)</label>
                    <input type="tel" id="phone" name="phone" class="form-control input-mono"
                           value="<?php echo sanitize($form_data['phone']); ?>" placeholder="e.g. +260 977 123456">
                    <span class="form-hint">Used for mobile money input purchases.</span>
                </div>

                <div class="form-group">
                    <label for="location_district">Farming District / Province <span class="required">*</span></label>
                    <select id="location_district" name="location_district" class="form-control" required>
                        <?php foreach ($zambian_districts as $dst): ?>
                        <option value="<?php echo sanitize($dst); ?>" <?php echo ($form_data['location_district'] === $dst) ? 'selected' : ''; ?>>
                            <?php echo sanitize($dst); ?> District
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="farm_name">Primary Farm Name</label>
                <input type="text" id="farm_name" name="farm_name" class="form-control"
                       value="<?php echo sanitize($form_data['farm_name']); ?>" placeholder="e.g. Kafue River Agri Holding (Optional)">
                <span class="form-hint">A starter farm folio will be initialized for this holding.</span>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="password">Create Ledger Passcode <span class="required">*</span></label>
                    <input type="password" id="password" name="password" class="form-control input-mono" required
                           placeholder="At least 6 characters">
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Passcode <span class="required">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control input-mono" required
                           placeholder="Re-enter passcode">
                </div>
            </div>

            <div style="margin-top: 10px;">
                <button type="submit" class="ledger-btn ledger-btn-primary" style="width: 100%; padding: 12px;">
                    Establish Farm Ledger Record &rarr;
                </button>
            </div>
        </form>

        <div style="margin-top: 20px; text-align: center; font-size: 13.5px; border-top: 1px dashed var(--border-rule); padding-top: 14px;">
            Already have an active folio? 
            <a href="login.php" style="font-weight: 600;">Sign in to your records &rarr;</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
