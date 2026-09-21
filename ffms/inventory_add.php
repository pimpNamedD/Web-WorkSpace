<?php
/**
 * FFMS (Field Ledger) - Register New Inventory Item
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

login_required();
$user = current_user();
$user_id = $user['id'];

$stmt_f = $pdo->prepare('SELECT id, farm_name FROM farms WHERE user_id = ? ORDER BY farm_name ASC');
$stmt_f->execute([$user_id]);
$farms = $stmt_f->fetchAll();

if (empty($farms)) {
    set_flash('amber', 'Please register at least one farm holding first.');
    header('Location: farms.php');
    exit;
}

$preset_farm_id = isset($_GET['farm_id']) ? (int)$_GET['farm_id'] : ($farms[0]['id'] ?? 0);
$error = '';

$categories = [
    'fertilizer' => 'Fertilizer & Soil Amendments',
    'seed'       => 'Seeds & Seedlings',
    'pesticide'  => 'Pesticides & Herbicides',
    'feed'       => 'Animal Feed & Minerals',
    'veterinary' => 'Veterinary Medicine & Vaccines',
    'fuel'       => 'Diesel & Petrol',
    'equipment'  => 'Equipment & Machinery Spares',
    'tools'      => 'Hand Tools & Implements',
    'other'      => 'General Farm Supplies'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please retry.';
    } else {
        $farm_id        = (int)($_POST['farm_id'] ?? 0);
        $name           = trim($_POST['name'] ?? '');
        $category       = trim($_POST['category'] ?? 'other');
        $quantity       = (float)($_POST['quantity'] ?? 0);
        $unit           = trim($_POST['unit'] ?? 'units');
        $threshold      = (float)($_POST['low_stock_threshold'] ?? 5.0);
        $unit_cost_zmw  = (float)($_POST['unit_cost_zmw'] ?? 0.0);
        $expiry_date    = !empty($_POST['expiry_date']) ? trim($_POST['expiry_date']) : null;
        $storage_loc    = trim($_POST['storage_location'] ?? 'Main Store');
        $notes          = trim($_POST['notes'] ?? '');

        // Verify farm ownership
        $stmt_check = $pdo->prepare('SELECT id FROM farms WHERE id = ? AND user_id = ?');
        $stmt_check->execute([$farm_id, $user_id]);
        if (!$stmt_check->fetch()) {
            $error = 'Invalid farm holding selected.';
        } elseif (empty($name)) {
            $error = 'Item description or commercial name is required.';
        } elseif ($quantity < 0) {
            $error = 'Quantity cannot be negative.';
        } else {
            $stmt_ins = $pdo->prepare('
                INSERT INTO inventory_items (
                    farm_id, name, category, quantity, unit, low_stock_threshold, 
                    unit_cost_zmw, expiry_date, storage_location, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt_ins->execute([
                $farm_id, $name, $category, $quantity, $unit, $threshold,
                $unit_cost_zmw, $expiry_date, $storage_loc, $notes
            ]);

            set_flash('green', "Stock item '{$name}' successfully registered in farm stores!");
            header('Location: inventory.php?farm_id=' . $farm_id);
            exit;
        }
    }
}

$page_title = 'Register New Inventory Item';
include __DIR__ . '/includes/header.php';
?>

<div class="ledger-wrapper">

    <div class="ledger-card border-green">
        <div class="card-header-ruled">
            <div>
                <span class="folio-tag">STORES FOLIO &bull; NEW STOCK ENTRY</span>
                <h1 style="margin-top: 6px;">Register Inventory Item</h1>
                <p style="color: var(--ink-muted); margin-bottom: 0; font-size: 14px;">
                    Catalog fertilizer, seed pockets, chemicals, livestock feeds, or fuel in the farm stores folio.
                </p>
            </div>
            <div style="text-align: right;">
                <a href="inventory.php" class="ledger-btn ledger-btn-sm">&larr; Back to Inventory</a>
            </div>
        </div>

        <?php if (!empty($error)): ?>
        <div class="flash-message flash-red" style="margin-top: 14px;">
            <strong>REJECTED:</strong> <?php echo sanitize($error); ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="inventory_add.php" style="margin-top: 20px;">
            <?php echo csrf_field(); ?>

            <div class="form-grid">
                <div class="form-group">
                    <label for="farm_id">Farm Holding <span style="color: var(--stamp-red);">*</span></label>
                    <select name="farm_id" id="farm_id" class="form-control" required>
                        <?php foreach ($farms as $f): ?>
                        <option value="<?php echo (int)$f['id']; ?>" <?php echo ($f['id'] == $preset_farm_id) ? 'selected' : ''; ?>>
                            <?php echo sanitize($f['farm_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="category">Category <span style="color: var(--stamp-red);">*</span></label>
                    <select name="category" id="category" class="form-control" required>
                        <?php foreach ($categories as $cat_key => $cat_lbl): ?>
                        <option value="<?php echo sanitize($cat_key); ?>">
                            <?php echo sanitize($cat_lbl); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group span-2">
                    <label for="name">Item Name / Brand <span style="color: var(--stamp-red);">*</span></label>
                    <input type="text" name="name" id="name" class="form-control" required
                           placeholder="e.g. Urea 46% N Top Dressing, Seed Co SC719, Albendazole 10% Oral, Diesel">
                </div>

                <div class="form-group">
                    <label for="quantity">Current Physical Stock <span style="color: var(--stamp-red);">*</span></label>
                    <input type="number" name="quantity" id="quantity" step="0.01" min="0" value="10.00" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="unit">Stock Unit <span style="color: var(--stamp-red);">*</span></label>
                    <input type="text" name="unit" id="unit" class="form-control" value="50kg bags" required 
                           placeholder="e.g. 50kg bags, Liters, 25kg pockets, vials">
                </div>

                <div class="form-group">
                    <label for="low_stock_threshold">Low Stock Warning Threshold <span style="color: var(--stamp-red);">*</span></label>
                    <input type="number" name="low_stock_threshold" id="low_stock_threshold" step="0.01" min="0" value="5.00" class="form-control" required>
                    <small style="font-size: 11px; color: var(--ink-muted);">Flag as critical when quantity drops below this level.</small>
                </div>

                <div class="form-group">
                    <label for="unit_cost_zmw">Estimated Unit Replacement Cost (ZMW)</label>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span class="mono" style="font-weight: 600; color: var(--stamp-ochre);">K</span>
                        <input type="number" name="unit_cost_zmw" id="unit_cost_zmw" step="0.01" min="0" value="0.00" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label for="storage_location">Storage Location</label>
                    <input type="text" name="storage_location" id="storage_location" class="form-control" 
                           placeholder="e.g. Fertilizer Shed A, Cold Seed Store, Chemical Cabinet">
                </div>

                <div class="form-group">
                    <label for="expiry_date">Batch Expiration Date (if applicable)</label>
                    <input type="date" name="expiry_date" id="expiry_date" class="form-control">
                </div>

                <div class="form-group span-2">
                    <label for="notes">Notes / Handling Instructions</label>
                    <textarea name="notes" id="notes" class="form-control" rows="2" placeholder="e.g. Keep dry on wooden pallets; toxic to aquatic organisms; store away from feed."></textarea>
                </div>
            </div>

            <div style="display: flex; gap: 12px; margin-top: 24px; padding-top: 14px; border-top: 1px dashed var(--border-rule);">
                <button type="submit" class="ledger-btn ledger-btn-primary">
                    [ ENTER INTO INVENTORY FOLIO ]
                </button>
                <a href="inventory.php" class="ledger-btn ledger-btn-sm">Cancel</a>
            </div>
        </form>
    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
