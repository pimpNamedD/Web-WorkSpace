<?php
/**
 * FFMS (Field Ledger) - Farms Folio Management
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

login_required();
$user = current_user();
$user_id = $user['id'];
$error = '';

// Handle creating a new farm folio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_farm') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please try again.';
    } else {
        $farm_name     = trim($_POST['farm_name'] ?? '');
        $location      = trim($_POST['location'] ?? '');
        $weather_city  = trim($_POST['weather_city'] ?? '');
        $size_hectares = (float)($_POST['size_hectares'] ?? 0);
        $farm_type     = $_POST['farm_type'] ?? 'mixed';
        $notes         = trim($_POST['notes'] ?? '');

        if (empty($farm_name) || empty($location)) {
            $error = 'Farm name and location are required to register a new holding.';
        } elseif (!in_array($farm_type, ['crop', 'livestock', 'mixed'])) {
            $error = 'Invalid farm type selected.';
        } else {
            $ins = $pdo->prepare('
                INSERT INTO farms (user_id, farm_name, location, weather_city, size_hectares, farm_type, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ');
            $ins->execute([
                $user_id,
                $farm_name,
                $location,
                $weather_city ?: $location,
                $size_hectares,
                $farm_type,
                $notes
            ]);
            set_flash('green', 'New farm folio "' . $farm_name . '" opened successfully.');
            header('Location: farms.php');
            exit;
        }
    }
}

// Fetch all user's farms with count aggregates
$stmt = $pdo->prepare('
    SELECT f.*,
        (SELECT COUNT(*) FROM crops WHERE farm_id = f.id) AS total_crops,
        (SELECT COUNT(*) FROM crops WHERE farm_id = f.id AND status = "growing") AS active_crops,
        (SELECT COALESCE(SUM(quantity), 0) FROM livestock WHERE farm_id = f.id AND health_status != "deceased") AS total_livestock,
        (SELECT COUNT(*) FROM workers WHERE farm_id = f.id AND is_active = 1) AS active_workers
    FROM farms f
    WHERE f.user_id = ?
    ORDER BY f.id ASC
');
$stmt->execute([$user_id]);
$farms = $stmt->fetchAll();

$page_title = 'Farm Holdings Folio';
include __DIR__ . '/includes/header.php';
?>

<div class="ledger-wrapper">

    <!-- Title Card -->
    <div class="ledger-card border-green">
        <div class="card-header-ruled">
            <div>
                <span class="folio-tag">FOLIO REGISTER &bull; SECTION 01</span>
                <h1 style="margin-top: 6px;">Registered Farm Holdings</h1>
                <p style="color: var(--ink-muted); margin-bottom: 0; font-size: 14px;">
                    Agricultural properties, outgrower blocks, and livestock ranches registered under folio owner <?php echo sanitize($user['full_name']); ?>.
                </p>
            </div>
            <span class="stamp-badge stamp-green">[ <?php echo count($farms); ?> REGISTERED ]</span>
        </div>

        <?php if ($error): ?>
        <div class="ledger-flash flash-red" style="margin-top: 14px;">
            <span class="flash-tag">ERROR:</span>
            <span class="flash-text"><?php echo sanitize($error); ?></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Farms Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 20px;">
        <?php foreach ($farms as $farm): ?>
        <div class="ledger-card border-<?php echo ($farm['farm_type'] === 'crop') ? 'green' : (($farm['farm_type'] === 'livestock') ? 'ochre' : 'navy'); ?>">
            <div class="card-header-ruled">
                <div>
                    <span class="folio-tag">№ FL-FARM-<?php echo str_pad((string)$farm['id'], 3, '0', STR_PAD_LEFT); ?></span>
                    <h3 style="margin-top: 6px; font-size: 1.35rem;">
                        <a href="farm_view.php?id=<?php echo (int)$farm['id']; ?>"><?php echo sanitize($farm['farm_name']); ?></a>
                    </h3>
                </div>
                <?php echo render_stamp_badge($farm['farm_type']); ?>
            </div>

            <div style="font-family: var(--font-mono); font-size: 12.5px; margin-bottom: 12px; color: var(--ink-muted);">
                <div><strong>Location:</strong> <?php echo sanitize($farm['location']); ?></div>
                <div><strong>Weather Station:</strong> <?php echo sanitize($farm['weather_city'] ?: $farm['location']); ?></div>
                <div><strong>Acreage:</strong> <?php echo format_qty($farm['size_hectares']); ?> Hectares</div>
            </div>

            <?php if (!empty($farm['notes'])): ?>
            <p style="font-size: 13px; color: var(--ink-muted); border-top: 1px dashed var(--border-rule); padding-top: 8px;">
                <?php echo sanitize($farm['notes']); ?>
            </p>
            <?php endif; ?>

            <!-- Metric summary for this farm -->
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; background: var(--paper-subtle); padding: 10px; border-radius: 2px; text-align: center; margin: 12px 0;">
                <div>
                    <div style="font-family: var(--font-heading); font-size: 1.25rem; font-weight: 700;"><?php echo (int)$farm['active_crops']; ?></div>
                    <div style="font-family: var(--font-mono); font-size: 10px; color: var(--ink-faint); text-transform: uppercase;">Crops Growing</div>
                </div>
                <div>
                    <div style="font-family: var(--font-heading); font-size: 1.25rem; font-weight: 700;"><?php echo number_format((int)$farm['total_livestock']); ?></div>
                    <div style="font-family: var(--font-mono); font-size: 10px; color: var(--ink-faint); text-transform: uppercase;">Livestock Head</div>
                </div>
                <div>
                    <div style="font-family: var(--font-heading); font-size: 1.25rem; font-weight: 700;"><?php echo (int)$farm['active_workers']; ?></div>
                    <div style="font-family: var(--font-mono); font-size: 10px; color: var(--ink-faint); text-transform: uppercase;">Active Workers</div>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed var(--border-rule); padding-top: 12px;">
                <a href="farm_view.php?id=<?php echo (int)$farm['id']; ?>" class="ledger-btn ledger-btn-sm ledger-btn-primary">
                    Open Full Ledger &rarr;
                </a>
                <a href="farm_edit.php?id=<?php echo (int)$farm['id']; ?>" class="ledger-btn ledger-btn-sm">
                    Edit Folio
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Open New Farm Folio Card -->
    <div class="ledger-card border-ochre" style="margin-top: 20px;">
        <div class="card-header-ruled">
            <div>
                <span class="folio-tag">NEW RECORD</span>
                <h3 style="margin-top: 4px;">Register Another Farm Folio</h3>
            </div>
            <span class="stamp-badge stamp-ochre">[ ENTRY FORM ]</span>
        </div>

        <form method="POST" action="farms.php" class="ledger-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="add_farm">

            <div class="form-grid">
                <div class="form-group">
                    <label for="farm_name">Farm Holding Name <span class="required">*</span></label>
                    <input type="text" id="farm_name" name="farm_name" class="form-control" required
                           placeholder="e.g. Chisamba River Block">
                </div>

                <div class="form-group">
                    <label for="location">Location / District <span class="required">*</span></label>
                    <input type="text" id="location" name="location" class="form-control" required
                           placeholder="e.g. Chisamba, Central Province">
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="weather_city">Weather Station / City</label>
                    <input type="text" id="weather_city" name="weather_city" class="form-control"
                           placeholder="e.g. Chisamba or Kabwe (for OpenWeatherMap)">
                    <span class="form-hint">Defaults to location text if left blank.</span>
                </div>

                <div class="form-group">
                    <label for="size_hectares">Total Area (Hectares)</label>
                    <input type="number" step="0.01" id="size_hectares" name="size_hectares" class="form-control input-mono"
                           placeholder="e.g. 150.00">
                </div>

                <div class="form-group">
                    <label for="farm_type">Primary Farm Type <span class="required">*</span></label>
                    <select id="farm_type" name="farm_type" class="form-control" required>
                        <option value="mixed">Mixed Farming (Crops &amp; Livestock)</option>
                        <option value="crop">Crop Production Only</option>
                        <option value="livestock">Livestock &amp; Ranching Only</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="notes">Ledger Notes / Tenure Details</label>
                <textarea id="notes" name="notes" class="form-control" placeholder="Tenure type (Title deed, customary allocation), soil characteristics, irrigation availability..."></textarea>
            </div>

            <div>
                <button type="submit" class="ledger-btn ledger-btn-primary">
                    Record New Farm in Ledger &rarr;
                </button>
            </div>
        </form>
    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
