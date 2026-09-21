<?php
/**
 * FFMS (Field Ledger) - Inventory & Stock Management Ledger
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

login_required();
$user = current_user();
$user_id = $user['id'];

// Fetch user's farms
$stmt_f = $pdo->prepare('SELECT id, farm_name FROM farms WHERE user_id = ? ORDER BY farm_name ASC');
$stmt_f->execute([$user_id]);
$user_farms = $stmt_f->fetchAll();
$farm_ids = array_column($user_farms, 'id');

// Filters
$filter_farm = isset($_GET['farm_id']) && is_numeric($_GET['farm_id']) ? (int)$_GET['farm_id'] : 0;
$filter_cat = isset($_GET['category']) ? trim($_GET['category']) : '';
$filter_alert = isset($_GET['low_stock']) && $_GET['low_stock'] === '1';

// Handle quick stock adjustments (Restock / Consume)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && verify_csrf()) {
    $item_id = (int)($_POST['item_id'] ?? 0);
    $action_type = $_POST['action']; // 'restock' or 'consume'
    $adjust_qty = (float)($_POST['adjust_quantity'] ?? 0);
    $adjust_notes = trim($_POST['adjust_notes'] ?? '');

    // Verify ownership of the item's farm
    $stmt_it = $pdo->prepare('
        SELECT i.*, f.user_id, f.farm_name 
        FROM inventory_items i
        JOIN farms f ON i.farm_id = f.id
        WHERE i.id = ? AND f.user_id = ?
    ');
    $stmt_it->execute([$item_id, $user_id]);
    $item = $stmt_it->fetch();

    if ($item && $adjust_qty > 0) {
        $old_qty = (float)$item['quantity'];
        $new_qty = ($action_type === 'restock') ? ($old_qty + $adjust_qty) : max(0.0, $old_qty - $adjust_qty);

        $stmt_up = $pdo->prepare('UPDATE inventory_items SET quantity = ?, updated_at = NOW() WHERE id = ?');
        $stmt_up->execute([$new_qty, $item_id]);

        // Log into farm activity log
        $act_verb = ($action_type === 'restock') ? 'Restocked' : 'Consumed/Applied';
        $log_desc = "Inventory update: {$act_verb} {$adjust_qty} {$item['unit']} of {$item['name']}. New balance: {$new_qty} {$item['unit']}. Note: {$adjust_notes}";
        $stmt_act = $pdo->prepare('INSERT INTO activity_log (farm_id, activity_type, description, activity_date) VALUES (?, "note", ?, CURDATE())');
        $stmt_act->execute([$item['farm_id'], $log_desc]);

        set_flash('green', "Stock balance updated for {$item['name']}! Current: {$new_qty} {$item['unit']}");
        header('Location: inventory.php' . ($filter_farm ? "?farm_id={$filter_farm}" : ''));
        exit;
    }
}

// Fetch inventory records
$items = [];
$low_stock_count = 0;
$total_inventory_value = 0.0;

if (!empty($farm_ids)) {
    $where = ['f.user_id = ?'];
    $params = [$user_id];

    if ($filter_farm > 0) {
        $where[] = 'i.farm_id = ?';
        $params[] = $filter_farm;
    }
    if (!empty($filter_cat)) {
        $where[] = 'i.category = ?';
        $params[] = $filter_cat;
    }
    if ($filter_alert) {
        $where[] = 'i.quantity <= i.low_stock_threshold';
    }

    $sql = '
        SELECT i.*, f.farm_name 
        FROM inventory_items i
        JOIN farms f ON i.farm_id = f.id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY (i.quantity <= i.low_stock_threshold) DESC, i.category ASC, i.name ASC
    ';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    // Summary metrics across all holdings
    $stmt_all = $pdo->prepare('
        SELECT i.quantity, i.unit_cost_zmw, i.low_stock_threshold
        FROM inventory_items i
        JOIN farms f ON i.farm_id = f.id
        WHERE f.user_id = ?
    ');
    $stmt_all->execute([$user_id]);
    $all_items = $stmt_all->fetchAll();

    foreach ($all_items as $ai) {
        $total_inventory_value += ((float)$ai['quantity'] * (float)$ai['unit_cost_zmw']);
        if ((float)$ai['quantity'] <= (float)$ai['low_stock_threshold']) {
            $low_stock_count++;
        }
    }
}

$page_title = 'Inventory & Stock Management';
include __DIR__ . '/includes/header.php';
?>

<div class="ledger-wrapper">

    <!-- Header Card -->
    <div class="ledger-card border-green">
        <div class="card-header-ruled">
            <div>
                <span class="folio-tag">STORES &amp; SUPPLIES FOLIO &bull; SECTION 06</span>
                <h1 style="margin-top: 6px;">Farm Inventory &amp; Stock Ledger</h1>
                <p style="color: var(--ink-muted); margin-bottom: 0; font-size: 14px;">
                    Track on-farm stores of seed, fertilizer, agrochemicals, veterinary medicines, and fuel with automated low-stock warnings.
                </p>
            </div>
            <div style="display: flex; gap: 8px; align-items: center;">
                <a href="inventory_add.php" class="ledger-btn ledger-btn-primary">+ Register New Stock Item</a>
                <a href="input_add.php" class="ledger-btn ledger-btn-sm">+ Record Input Purchase</a>
            </div>
        </div>

        <!-- Metric Highlights -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin: 16px 0;">
            <div style="background: var(--paper-subtle); border-left: 3px solid var(--stamp-green); padding: 10px 14px; border-radius: 2px;">
                <div class="kpi-label">Total Tracked Items</div>
                <div class="kpi-value mono" style="font-size: 1.45rem; color: var(--stamp-green);">
                    <?php echo count($items); ?> SKUs
                </div>
            </div>

            <div style="background: var(--paper-subtle); border-left: 3px solid <?php echo ($low_stock_count > 0) ? 'var(--stamp-red)' : 'var(--stamp-green)'; ?>; padding: 10px 14px; border-radius: 2px;">
                <div class="kpi-label">Low Stock Alerts</div>
                <div class="kpi-value mono" style="font-size: 1.45rem; color: <?php echo ($low_stock_count > 0) ? 'var(--stamp-red)' : 'var(--stamp-green)'; ?>;">
                    <?php echo $low_stock_count; ?> <?php echo ($low_stock_count > 0) ? 'CRITICAL' : 'OK'; ?>
                </div>
            </div>

            <div style="background: var(--paper-subtle); border-left: 3px solid var(--stamp-amber); padding: 10px 14px; border-radius: 2px;">
                <div class="kpi-label">Estimated Stores Valuation</div>
                <div class="kpi-value mono" style="font-size: 1.45rem; color: var(--stamp-amber);">
                    <?php echo format_zmw($total_inventory_value); ?>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <form method="GET" action="inventory.php" class="filter-strip" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center; background: var(--paper-card-alt); padding: 10px; border: 1px dashed var(--border-rule);">
            <div>
                <label style="font-size: 12px; font-weight: 600; color: var(--ink-muted);">Farm Holding:</label>
                <select name="farm_id" class="form-control-sm" onchange="this.form.submit()">
                    <option value="0">All Farm Holdings</option>
                    <?php foreach ($user_farms as $uf): ?>
                    <option value="<?php echo (int)$uf['id']; ?>" <?php echo ($uf['id'] == $filter_farm) ? 'selected' : ''; ?>>
                        <?php echo sanitize($uf['farm_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label style="font-size: 12px; font-weight: 600; color: var(--ink-muted);">Category:</label>
                <select name="category" class="form-control-sm" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <option value="seed" <?php echo ($filter_cat === 'seed') ? 'selected' : ''; ?>>Seeds &amp; Pockets</option>
                    <option value="fertilizer" <?php echo ($filter_cat === 'fertilizer') ? 'selected' : ''; ?>>Fertilizers</option>
                    <option value="pesticide" <?php echo ($filter_cat === 'pesticide') ? 'selected' : ''; ?>>Agrochemicals</option>
                    <option value="feed" <?php echo ($filter_cat === 'feed') ? 'selected' : ''; ?>>Animal Feed</option>
                    <option value="veterinary" <?php echo ($filter_cat === 'veterinary') ? 'selected' : ''; ?>>Veterinary Medicine</option>
                    <option value="fuel" <?php echo ($filter_cat === 'fuel') ? 'selected' : ''; ?>>Diesel &amp; Petrol</option>
                    <option value="equipment" <?php echo ($filter_cat === 'equipment') ? 'selected' : ''; ?>>Equipment &amp; Spares</option>
                    <option value="tools" <?php echo ($filter_cat === 'tools') ? 'selected' : ''; ?>>Hand Tools</option>
                </select>
            </div>

            <div style="display: flex; align-items: center; gap: 6px; margin-top: 14px;">
                <input type="checkbox" name="low_stock" value="1" id="lowStockChk" <?php echo $filter_alert ? 'checked' : ''; ?> onchange="this.form.submit()">
                <label for="lowStockChk" style="font-size: 12px; font-weight: 600; color: var(--stamp-red); cursor: pointer; margin-bottom: 0;">
                    Show Low Stock Alerts Only
                </label>
            </div>

            <?php if ($filter_farm || $filter_cat || $filter_alert): ?>
            <div style="margin-left: auto;">
                <a href="inventory.php" class="ledger-btn ledger-btn-sm" style="font-size: 11px;">Clear Filters</a>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Inventory Table -->
    <div class="ledger-card" style="margin-top: 16px;">
        <h2 style="font-size: 1.25rem; margin-bottom: 12px;">Stores Stock Inventory Roster</h2>

        <?php if (empty($items)): ?>
        <div style="text-align: center; padding: 40px 20px; background: var(--paper-subtle); border: 1px dashed var(--border-rule);">
            <div class="ledger-seal" style="margin: 0 auto 12px; width: 44px; height: 44px; font-size: 16px;">FL</div>
            <h3>No inventory records found</h3>
            <p style="color: var(--ink-muted); font-size: 14px;">Register your seed bags, fertilizer stocks, veterinary medicines, or fuel stores.</p>
            <a href="inventory_add.php" class="ledger-btn ledger-btn-primary">+ Register First Stock Item</a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Farm Holding</th>
                        <th>Item Description</th>
                        <th>Category</th>
                        <th style="text-align: right;">Current Stock</th>
                        <th style="text-align: right;">Threshold</th>
                        <th style="text-align: right;">Unit Value</th>
                        <th style="text-align: right;">Total Value</th>
                        <th>Storage Location</th>
                        <th>Stock Health</th>
                        <th style="text-align: center;">Quick Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it): 
                        $is_low = ((float)$it['quantity'] <= (float)$it['low_stock_threshold']);
                        $item_val = (float)$it['quantity'] * (float)$it['unit_cost_zmw'];
                    ?>
                    <tr style="<?php echo $is_low ? 'background: rgba(163, 45, 45, 0.04);' : ''; ?>">
                        <td><strong><?php echo sanitize($it['farm_name']); ?></strong></td>
                        <td>
                            <strong><?php echo sanitize($it['name']); ?></strong>
                            <?php if (!empty($it['notes'])): ?>
                            <div style="font-size: 11.5px; color: var(--ink-muted);"><?php echo sanitize($it['notes']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="mono" style="font-size: 12px; text-transform: uppercase;">
                                <?php echo sanitize($it['category']); ?>
                            </span>
                        </td>
                        <td style="text-align: right;" class="mono">
                            <strong style="<?php echo $is_low ? 'color: var(--stamp-red); font-size: 16px;' : 'color: var(--stamp-green);'; ?>">
                                <?php echo format_qty($it['quantity']); ?>
                            </strong> 
                            <span style="font-size: 11px; color: var(--ink-muted);"><?php echo sanitize($it['unit']); ?></span>
                        </td>
                        <td style="text-align: right;" class="mono" style="font-size: 12px; color: var(--ink-muted);">
                            <?php echo format_qty($it['low_stock_threshold']); ?>
                        </td>
                        <td style="text-align: right;" class="mono">
                            <?php echo format_zmw($it['unit_cost_zmw']); ?>
                        </td>
                        <td style="text-align: right;" class="mono">
                            <strong><?php echo format_zmw($item_val); ?></strong>
                        </td>
                        <td>
                            <small class="mono"><?php echo sanitize($it['storage_location'] ?: 'Main Store'); ?></small>
                        </td>
                        <td>
                            <?php if ($is_low): ?>
                            <span class="stamp-badge stamp-red">[ LOW STOCK ]</span>
                            <?php else: ?>
                            <span class="stamp-badge stamp-green">[ SUFFICIENT ]</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                            <!-- Inline Quick Adjust Button -->
                            <details style="display: inline-block; position: relative;">
                                <summary class="ledger-btn ledger-btn-sm" style="font-size: 11px; padding: 2px 8px; cursor: pointer;">
                                    [ Adjust ]
                                </summary>
                                <div style="position: absolute; right: 0; top: 100%; z-index: 50; background: var(--paper-card); border: 2px solid var(--border-strong); box-shadow: var(--shadow-binder); padding: 14px; width: 260px; text-align: left; border-radius: 2px;">
                                    <form method="POST" action="inventory.php">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="item_id" value="<?php echo (int)$it['id']; ?>">
                                        <div style="font-weight: 600; font-size: 13px; margin-bottom: 8px;">
                                            Quick Stock Adjustment
                                        </div>
                                        <div style="margin-bottom: 8px;">
                                            <label style="font-size: 11px;">Action:</label>
                                            <select name="action" class="form-control-sm" style="width: 100%;">
                                                <option value="consume">Consume / Apply in Field</option>
                                                <option value="restock">Restock / Add Quantity</option>
                                            </select>
                                        </div>
                                        <div style="margin-bottom: 8px;">
                                            <label style="font-size: 11px;">Quantity (<?php echo sanitize($it['unit']); ?>):</label>
                                            <input type="number" name="adjust_quantity" step="0.1" min="0.1" value="1.0" class="form-control-sm" style="width: 100%;" required>
                                        </div>
                                        <div style="margin-bottom: 10px;">
                                            <label style="font-size: 11px;">Reason / Notes:</label>
                                            <input type="text" name="adjust_notes" class="form-control-sm" placeholder="e.g. Applied to maize Block A" style="width: 100%;">
                                        </div>
                                        <button type="submit" class="ledger-btn ledger-btn-primary ledger-btn-sm" style="width: 100%;">
                                            Save Adjustment
                                        </button>
                                    </form>
                                </div>
                            </details>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
