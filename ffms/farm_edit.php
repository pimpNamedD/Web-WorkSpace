<?php
/**
 * FFMS (Field Ledger) - Edit or Remove Farm Folio
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

login_required();
$user = current_user();
$user_id = $user['id'];

$farm_id = (int)($_GET['id'] ?? 0);
$farm = verify_farm_owner($pdo, $farm_id, $user_id);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? 'update';

        // 1. Delete Farm
        if ($action === 'delete') {
            $del = $pdo->prepare('DELETE FROM farms WHERE id = ? AND user_id = ?');
            $del->execute([$farm_id, $user_id]);
            set_flash('amber', 'Farm folio "' . $farm['farm_name'] . '" was removed from the ledger.');
            header('Location: farms.php');
            exit;
        }

        // 2. Update Farm
        if ($action === 'update') {
            $farm_name     = trim($_POST['farm_name'] ?? '');
            $location      = trim($_POST['location'] ?? '');
            $weather_city  = trim($_POST['weather_city'] ?? '');
            $size_hectares = (float)($_POST['size_hectares'] ?? 0);
            $farm_type     = $_POST['farm_type'] ?? 'mixed';
            $notes         = trim($_POST['notes'] ?? '');

            if (empty($farm_name) || empty($location)) {
                $error = 'Farm name and location cannot be left empty.';
            } else {
                $upd = $pdo->prepare('
                    UPDATE farms 
                    SET farm_name = ?, location = ?, weather_city = ?, size_hectares = ?, farm_type = ?, notes = ?
                    WHERE id = ? AND user_id = ?
                ');
                $upd->execute([
                    $farm_name,
                    $location,
                    $weather_city ?: $location,
                    $size_hectares,
                    $farm_type,
                    $notes,
                    $farm_id,
                    $user_id
                ]);

                set_flash('green', 'Farm folio "' . $farm_name . '" updated successfully.');
                header('Location: farm_view.php?id=' . $farm_id);
                exit;
            }
        }
    }
}

$page_title = 'Edit ' . $farm['farm_name'];
include __DIR__ . '/includes/header.php';
?>

<div class="ledger-container" style="max-width: 720px; margin-top: 20px;">
    <div class="ledger-card border-green">
        <div class="card-header-ruled">
            <div>
                <span class="folio-tag">MODIFY FOLIO № FL-FARM-<?php echo str_pad((string)$farm['id'], 3, '0', STR_PAD_LEFT); ?></span>
                <h2 style="margin-top: 6px;">Update Farm Holding Details</h2>
            </div>
            <a href="farm_view.php?id=<?php echo (int)$farm['id']; ?>" class="ledger-btn ledger-btn-sm">&larr; Return to Folio</a>
        </div>

        <?php if ($error): ?>
        <div class="ledger-flash flash-red">
            <span class="flash-tag">ERROR:</span>
            <span class="flash-text"><?php echo sanitize($error); ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" action="farm_edit.php?id=<?php echo (int)$farm['id']; ?>" class="ledger-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="update">

            <div class="form-grid">
                <div class="form-group">
                    <label for="farm_name">Farm Name <span class="required">*</span></label>
                    <input type="text" id="farm_name" name="farm_name" class="form-control" required
                           value="<?php echo sanitize($farm['farm_name']); ?>">
                </div>

                <div class="form-group">
                    <label for="location">Location / District <span class="required">*</span></label>
                    <input type="text" id="location" name="location" class="form-control" required
                           value="<?php echo sanitize($farm['location']); ?>">
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="weather_city">Weather Station City</label>
                    <input type="text" id="weather_city" name="weather_city" class="form-control"
                           value="<?php echo sanitize($farm['weather_city']); ?>" placeholder="e.g. Lusaka, Mkushi">
                    <span class="form-hint">Used for OpenWeatherMap free API queries.</span>
                </div>

                <div class="form-group">
                    <label for="size_hectares">Size (Hectares)</label>
                    <input type="number" step="0.01" id="size_hectares" name="size_hectares" class="form-control input-mono"
                           value="<?php echo (float)$farm['size_hectares']; ?>">
                </div>

                <div class="form-group">
                    <label for="farm_type">Farm Category <span class="required">*</span></label>
                    <select id="farm_type" name="farm_type" class="form-control" required>
                        <option value="mixed" <?php echo ($farm['farm_type'] === 'mixed') ? 'selected' : ''; ?>>Mixed Farming</option>
                        <option value="crop" <?php echo ($farm['farm_type'] === 'crop') ? 'selected' : ''; ?>>Crops Only</option>
                        <option value="livestock" <?php echo ($farm['farm_type'] === 'livestock') ? 'selected' : ''; ?>>Livestock Only</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="notes">Ledger Notes</label>
                <textarea id="notes" name="notes" class="form-control"><?php echo sanitize($farm['notes']); ?></textarea>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed var(--border-rule); padding-top: 18px; margin-top: 10px;">
                <button type="submit" class="ledger-btn ledger-btn-primary">
                    Save Changes to Folio &rarr;
                </button>
            </div>
        </form>

        <!-- Danger Zone: Delete Farm -->
        <div style="margin-top: 32px; padding: 18px; border: 1px solid var(--stamp-red); background: var(--stamp-red-bg); border-radius: 2px;">
            <h4 style="color: var(--stamp-red); margin-bottom: 6px;">Close &amp; Strike Farm Folio</h4>
            <p style="font-size: 13px; color: var(--ink-muted); margin-bottom: 12px;">
                Warning: Removing this farm will permanently delete all associated crops, livestock, activities, and worker records from the database.
            </p>
            <form method="POST" action="farm_edit.php?id=<?php echo (int)$farm['id']; ?>" onsubmit="return confirm('Are you sure you want to permanently delete this farm folio and all linked records?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete">
                <button type="submit" class="ledger-btn ledger-btn-danger ledger-btn-sm confirm-delete" data-item="<?php echo sanitize($farm['farm_name']); ?>">
                    Strike Farm Folio From Record
                </button>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
