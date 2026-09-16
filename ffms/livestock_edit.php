<?php
/**
 * FFMS (Field Ledger) - Edit or Update Livestock Entry
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

login_required();
$user = current_user();
$user_id = $user['id'];

$stock_id = (int)($_GET['id'] ?? 0);

// Verify ownership via farm
$stmt = $pdo->prepare('
    SELECT l.*, f.farm_name, f.user_id 
    FROM livestock l 
    JOIN farms f ON l.farm_id = f.id 
    WHERE l.id = ? AND f.user_id = ?
');
$stmt->execute([$stock_id, $user_id]);
$stock = $stmt->fetch();

if (!$stock) {
    set_flash('red', 'Livestock entry not found or access denied.');
    header('Location: livestock.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? 'update';

        // 1. Delete Livestock Record
        if ($action === 'delete') {
            $del = $pdo->prepare('DELETE FROM livestock WHERE id = ?');
            $del->execute([$stock_id]);
            set_flash('amber', 'Livestock entry "' . $stock['animal_type'] . '" struck from the ledger.');
            header('Location: livestock.php?farm_id=' . $stock['farm_id']);
            exit;
        }

        // 2. Update Record
        if ($action === 'update') {
            $animal_type      = trim($_POST['animal_type'] ?? '');
            $tag_id           = trim($_POST['tag_id'] ?? '');
            $quantity         = (int)($_POST['quantity'] ?? 1);
            $acquisition_date = !empty($_POST['acquisition_date']) ? $_POST['acquisition_date'] : null;
            $health_status    = $_POST['health_status'] ?? 'healthy';
            $notes            = trim($_POST['notes'] ?? '');

            if (empty($animal_type)) {
                $error = 'Animal type / breed cannot be blank.';
            } elseif ($quantity < 0) {
                $error = 'Quantity cannot be negative.';
            } else {
                $upd = $pdo->prepare('
                    UPDATE livestock 
                    SET animal_type = ?, tag_id = ?, quantity = ?, acquisition_date = ?, health_status = ?, notes = ?
                    WHERE id = ?
                ');
                $upd->execute([
                    $animal_type,
                    $tag_id,
                    $quantity,
                    $acquisition_date,
                    $health_status,
                    $notes,
                    $stock_id
                ]);

                // If health status changed, record a veterinary treatment log
                if ($health_status !== $stock['health_status']) {
                    $log_desc = sprintf('Updated condition for %s (Tag: %s): Changed from [%s] to [%s]. Observation: %s',
                        $animal_type,
                        $tag_id ?: 'Batch',
                        strtoupper(str_replace('_', ' ', $stock['health_status'])),
                        strtoupper(str_replace('_', ' ', $health_status)),
                        $notes ?: 'Routine inspection'
                    );
                    $pdo->prepare('INSERT INTO activity_log (farm_id, activity_type, description, activity_date) VALUES (?, "treatment", ?, ?)')
                        ->execute([$stock['farm_id'], $log_desc, date('Y-m-d')]);
                }

                set_flash('green', 'Livestock record for "' . $animal_type . '" updated successfully.');
                header('Location: livestock.php?farm_id=' . $stock['farm_id']);
                exit;
            }
        }
    }
}

$page_title = 'Update ' . $stock['animal_type'];
include __DIR__ . '/includes/header.php';
?>

<div class="ledger-container" style="max-width: 740px; margin-top: 20px;">
    <div class="ledger-card border-ochre">
        <div class="card-header-ruled">
            <div>
                <span class="folio-tag">MODIFY ENTRY № FL-STOCK-<?php echo str_pad((string)$stock['id'], 3, '0', STR_PAD_LEFT); ?></span>
                <h2 style="margin-top: 6px;">Update Livestock Condition</h2>
                <div style="font-family: var(--font-mono); font-size: 12px; color: var(--ink-muted);">
                    Farm: <?php echo sanitize($stock['farm_name']); ?>
                </div>
            </div>
            <div>
                <?php echo render_stamp_badge($stock['health_status']); ?>
            </div>
        </div>

        <?php if ($error): ?>
        <div class="ledger-flash flash-red">
            <span class="flash-tag">ERROR:</span>
            <span class="flash-text"><?php echo sanitize($error); ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" action="livestock_edit.php?id=<?php echo (int)$stock['id']; ?>" class="ledger-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="update">

            <div class="form-grid">
                <div class="form-group">
                    <label for="animal_type">Breed / Animal Classification <span class="required">*</span></label>
                    <input type="text" id="animal_type" name="animal_type" class="form-control" required
                           value="<?php echo sanitize($stock['animal_type']); ?>">
                </div>

                <div class="form-group">
                    <label for="tag_id">Ear Tag / Batch ID</label>
                    <input type="text" id="tag_id" name="tag_id" class="form-control input-mono"
                           value="<?php echo sanitize($stock['tag_id']); ?>">
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="quantity">Head Count / Quantity <span class="required">*</span></label>
                    <input type="number" id="quantity" name="quantity" class="form-control input-mono" min="0" required
                           value="<?php echo (int)$stock['quantity']; ?>">
                </div>

                <div class="form-group">
                    <label for="health_status">Health Condition <span class="required">*</span></label>
                    <select id="health_status" name="health_status" class="form-control" required>
                        <option value="healthy" <?php echo ($stock['health_status'] === 'healthy') ? 'selected' : ''; ?>>Healthy (Good)</option>
                        <option value="under_treatment" <?php echo ($stock['health_status'] === 'under_treatment') ? 'selected' : ''; ?>>Under Treatment (Antibiotic/Dip)</option>
                        <option value="sick" <?php echo ($stock['health_status'] === 'sick') ? 'selected' : ''; ?>>Sick / Quarantined</option>
                        <option value="deceased" <?php echo ($stock['health_status'] === 'deceased') ? 'selected' : ''; ?>>Deceased / Loss</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="acquisition_date">Acquisition Date</label>
                    <input type="date" id="acquisition_date" name="acquisition_date" class="form-control input-mono"
                           value="<?php echo sanitize($stock['acquisition_date']); ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="notes">Veterinary Observations, Treatments &amp; Feed</label>
                <textarea id="notes" name="notes" class="form-control"><?php echo sanitize($stock['notes']); ?></textarea>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed var(--border-rule); padding-top: 14px; margin-top: 8px;">
                <button type="submit" class="ledger-btn ledger-btn-primary">
                    Save Changes to Livestock Folio &rarr;
                </button>
                <a href="livestock.php" class="ledger-btn">Cancel</a>
            </div>
        </form>

        <!-- Danger Zone: Strike Record -->
        <div style="margin-top: 30px; padding: 14px; border: 1px solid var(--stamp-red); background: var(--stamp-red-bg); border-radius: 2px;">
            <form method="POST" action="livestock_edit.php?id=<?php echo (int)$stock['id']; ?>" onsubmit="return confirm('Confirm: Are you sure you want to remove this livestock record from the ledger?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete">
                <button type="submit" class="ledger-btn ledger-btn-danger ledger-btn-sm confirm-delete" data-item="<?php echo sanitize($stock['animal_type']); ?>">
                    Strike Livestock Record From Ledger
                </button>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
