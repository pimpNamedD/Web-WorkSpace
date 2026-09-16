<?php
/**
 * FFMS (Field Ledger) - Edit or Harvest Crop Entry
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

login_required();
$user = current_user();
$user_id = $user['id'];

$crop_id = (int)($_GET['id'] ?? 0);

// Verify crop ownership via farm
$stmt = $pdo->prepare('
    SELECT c.*, f.farm_name, f.user_id 
    FROM crops c 
    JOIN farms f ON c.farm_id = f.id 
    WHERE c.id = ? AND f.user_id = ?
');
$stmt->execute([$crop_id, $user_id]);
$crop = $stmt->fetch();

if (!$crop) {
    set_flash('red', 'Crop entry not found or access denied.');
    header('Location: crops.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? 'update';

        // 1. Delete Crop
        if ($action === 'delete') {
            $del = $pdo->prepare('DELETE FROM crops WHERE id = ?');
            $del->execute([$crop_id]);
            set_flash('amber', 'Crop record "' . $crop['crop_name'] . '" was removed from the ledger.');
            header('Location: crops.php?farm_id=' . $crop['farm_id']);
            exit;
        }

        // 2. Update / Harvest Crop
        if ($action === 'update') {
            $crop_name      = trim($_POST['crop_name'] ?? '');
            $field_name     = trim($_POST['field_name'] ?? '');
            $area_hectares  = (float)($_POST['area_hectares'] ?? 0);
            $planting_date  = !empty($_POST['planting_date']) ? $_POST['planting_date'] : null;
            $exp_harvest    = !empty($_POST['expected_harvest_date']) ? $_POST['expected_harvest_date'] : null;
            $act_harvest    = !empty($_POST['actual_harvest_date']) ? $_POST['actual_harvest_date'] : null;
            $status         = $_POST['status'] ?? 'growing';
            $exp_yield_kg   = (float)($_POST['expected_yield_kg'] ?? 0);
            $act_yield_kg   = (float)($_POST['actual_yield_kg'] ?? 0);
            $notes          = trim($_POST['notes'] ?? '');

            if (empty($crop_name)) {
                $error = 'Crop name cannot be empty.';
            } else {
                // If marking as harvested and no actual harvest date provided, default to today
                if ($status === 'harvested' && empty($act_harvest)) {
                    $act_harvest = date('Y-m-d');
                }

                $upd = $pdo->prepare('
                    UPDATE crops 
                    SET crop_name = ?, field_name = ?, area_hectares = ?, planting_date = ?, 
                        expected_harvest_date = ?, actual_harvest_date = ?, status = ?, 
                        expected_yield_kg = ?, actual_yield_kg = ?, notes = ?
                    WHERE id = ?
                ');
                $upd->execute([
                    $crop_name,
                    $field_name,
                    $area_hectares,
                    $planting_date,
                    $exp_harvest,
                    $act_harvest,
                    $status,
                    $exp_yield_kg,
                    $act_yield_kg,
                    $notes,
                    $crop_id
                ]);

                // If newly marked as harvested, log harvest activity
                if ($status === 'harvested' && $crop['status'] !== 'harvested') {
                    $harvest_log = sprintf('Completed harvest of %s on "%s" (%s ha). Actual yield: %s kg.',
                        $crop_name,
                        $field_name ?: 'Main Plot',
                        format_qty($area_hectares),
                        format_qty($act_yield_kg)
                    );
                    $pdo->prepare('INSERT INTO activity_log (farm_id, activity_type, description, activity_date) VALUES (?, "harvest", ?, ?)')
                        ->execute([$crop['farm_id'], $harvest_log, $act_harvest ?: date('Y-m-d')]);
                }

                set_flash('green', 'Crop folio for "' . $crop_name . '" updated successfully.');
                header('Location: crops.php?farm_id=' . $crop['farm_id']);
                exit;
            }
        }
    }
}

$page_title = 'Update ' . $crop['crop_name'];
include __DIR__ . '/includes/header.php';
?>

<div class="ledger-container" style="max-width: 740px; margin-top: 20px;">
    <div class="ledger-card border-green">
        <div class="card-header-ruled">
            <div>
                <span class="folio-tag">FOLIO UPDATE № FL-CROP-<?php echo str_pad((string)$crop['id'], 3, '0', STR_PAD_LEFT); ?></span>
                <h2 style="margin-top: 6px;">Update Crop / Record Harvest</h2>
                <div style="font-family: var(--font-mono); font-size: 12px; color: var(--ink-muted);">
                    Farm: <?php echo sanitize($crop['farm_name']); ?>
                </div>
            </div>
            <div>
                <?php echo render_stamp_badge($crop['status']); ?>
            </div>
        </div>

        <?php if ($error): ?>
        <div class="ledger-flash flash-red">
            <span class="flash-tag">ERROR:</span>
            <span class="flash-text"><?php echo sanitize($error); ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" action="crop_edit.php?id=<?php echo (int)$crop['id']; ?>" class="ledger-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="update">

            <div class="form-grid">
                <div class="form-group">
                    <label for="crop_name">Crop Name &amp; Cultivar <span class="required">*</span></label>
                    <input type="text" id="crop_name" name="crop_name" class="form-control" required
                           value="<?php echo sanitize($crop['crop_name']); ?>">
                </div>

                <div class="form-group">
                    <label for="field_name">Field Name / Plot Identifier</label>
                    <input type="text" id="field_name" name="field_name" class="form-control"
                           value="<?php echo sanitize($crop['field_name']); ?>">
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="area_hectares">Area Under Crop (Hectares) <span class="required">*</span></label>
                    <input type="number" step="0.01" id="area_hectares" name="area_hectares" class="form-control input-mono" required
                           value="<?php echo (float)$crop['area_hectares']; ?>">
                </div>

                <div class="form-group">
                    <label for="status">Crop Status <span class="required">*</span></label>
                    <select id="status" name="status" class="form-control" required onchange="toggleHarvestFields(this.value)">
                        <option value="growing" <?php echo ($crop['status'] === 'growing') ? 'selected' : ''; ?>>Growing (In Field)</option>
                        <option value="harvested" <?php echo ($crop['status'] === 'harvested') ? 'selected' : ''; ?>>Harvested (Completed)</option>
                        <option value="planned" <?php echo ($crop['status'] === 'planned') ? 'selected' : ''; ?>>Planned (Pending)</option>
                        <option value="failed" <?php echo ($crop['status'] === 'failed') ? 'selected' : ''; ?>>Failed (Crop Loss)</option>
                    </select>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="planting_date">Planting Date</label>
                    <input type="date" id="planting_date" name="planting_date" class="form-control input-mono"
                           value="<?php echo sanitize($crop['planting_date']); ?>">
                </div>

                <div class="form-group">
                    <label for="expected_harvest_date">Expected Maturity Date</label>
                    <input type="date" id="expected_harvest_date" name="expected_harvest_date" class="form-control input-mono"
                           value="<?php echo sanitize($crop['expected_harvest_date']); ?>">
                </div>

                <div class="form-group">
                    <label for="expected_yield_kg">Expected Yield Target (kg)</label>
                    <input type="number" step="0.01" id="expected_yield_kg" name="expected_yield_kg" class="form-control input-mono"
                           value="<?php echo (float)$crop['expected_yield_kg']; ?>">
                </div>
            </div>

            <!-- Harvest Specific Section (Highlighted if status is harvested) -->
            <div id="harvestSection" style="background: var(--paper-subtle); border-left: 4px solid var(--stamp-green); padding: 14px; border-radius: 2px;">
                <h4 style="color: var(--stamp-green); margin-bottom: 8px; font-size: 1.1rem;">Harvest Completion Details</h4>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="actual_harvest_date">Actual Harvest Date</label>
                        <input type="date" id="actual_harvest_date" name="actual_harvest_date" class="form-control input-mono"
                               value="<?php echo sanitize($crop['actual_harvest_date'] ?: date('Y-m-d')); ?>">
                    </div>

                    <div class="form-group">
                        <label for="actual_yield_kg">Actual Harvest Yield (kg)</label>
                        <input type="number" step="0.01" id="actual_yield_kg" name="actual_yield_kg" class="form-control input-mono"
                               value="<?php echo (float)$crop['actual_yield_kg']; ?>" placeholder="e.g. 182500">
                        <span class="form-hint">Total weighed yield delivered to storage or off-taker.</span>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="notes">Ledger Notes / Crop Observations</label>
                <textarea id="notes" name="notes" class="form-control"><?php echo sanitize($crop['notes']); ?></textarea>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed var(--border-rule); padding-top: 14px; margin-top: 8px;">
                <button type="submit" class="ledger-btn ledger-btn-primary">
                    Update Crop Record &rarr;
                </button>
                <a href="crops.php" class="ledger-btn">Cancel</a>
            </div>
        </form>

        <!-- Danger Zone: Delete Crop -->
        <div style="margin-top: 30px; padding: 14px; border: 1px solid var(--stamp-red); background: var(--stamp-red-bg); border-radius: 2px;">
            <form method="POST" action="crop_edit.php?id=<?php echo (int)$crop['id']; ?>" onsubmit="return confirm('Confirm: Are you sure you want to permanently delete this crop entry?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete">
                <button type="submit" class="ledger-btn ledger-btn-danger ledger-btn-sm confirm-delete" data-item="<?php echo sanitize($crop['crop_name']); ?>">
                    Strike Crop Entry From Ledger
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function toggleHarvestFields(status) {
    const sec = document.getElementById('harvestSection');
    if (status === 'harvested') {
        sec.style.opacity = '1';
    } else {
        sec.style.opacity = '0.7';
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
