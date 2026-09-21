<?php
/**
 * FFMS (Field Ledger) - Record Input Purchase (MoMo / Airtel Money / Cash)
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/payment_service.php';

login_required();
$user = current_user();
$user_id = $user['id'];

// Fetch user farms
$stmt_f = $pdo->prepare('SELECT id, farm_name FROM farms WHERE user_id = ? ORDER BY farm_name ASC');
$stmt_f->execute([$user_id]);
$farms = $stmt_f->fetchAll();

if (empty($farms)) {
    set_flash('amber', 'Please register at least one farm holding before recording inputs.');
    header('Location: farms.php');
    exit;
}

$preset_farm_id = isset($_GET['farm_id']) ? (int)$_GET['farm_id'] : ($farms[0]['id'] ?? 0);
$error = '';
$result_info = null;

$categories = [
    'fertilizer' => 'Fertilizer & Soil Amendments',
    'seed'       => 'Seeds & Seedlings',
    'chemicals'  => 'Pesticides, Herbicides & Chemicals',
    'feed'       => 'Animal Feed & Supplements',
    'veterinary' => 'Veterinary Medicine & Vaccines',
    'equipment'  => 'Equipment, Tools & Machinery',
    'fuel'       => 'Fuel & Generator Diesel',
    'labor'      => 'Field Labor & Casual Wages',
    'other'      => 'Other Farm Supplies'
];

$common_units = [
    '50kg bags',
    '25kg pockets',
    '10kg pockets',
    '5kg bags',
    'Liters',
    '5L canisters',
    '20L drums',
    'kg',
    'tonnes',
    'doses / vials',
    'units / pieces'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Session security token expired. Please try again.';
    } else {
        $farm_id        = (int)($_POST['farm_id'] ?? 0);
        $item_name      = trim($_POST['item_name'] ?? '');
        $category       = trim($_POST['category'] ?? 'other');
        $quantity       = (float)($_POST['quantity'] ?? 1);
        $unit           = trim($_POST['unit'] ?? 'units');
        $cost_zmw       = (float)($_POST['cost_zmw'] ?? 0);
        $purchase_date  = !empty($_POST['purchase_date']) ? trim($_POST['purchase_date']) : date('Y-m-d');
        $payment_method = trim($_POST['payment_method'] ?? 'cash');
        $phone_number   = trim($_POST['phone_number'] ?? '');
        $sync_inventory = !empty($_POST['sync_inventory']);
        $notes          = trim($_POST['notes'] ?? '');

        // Verify farm ownership
        $stmt_check = $pdo->prepare('SELECT id, farm_name FROM farms WHERE id = ? AND user_id = ?');
        $stmt_check->execute([$farm_id, $user_id]);
        $farm_row = $stmt_check->fetch();

        if (!$farm_row) {
            $error = 'Invalid farm holding selected.';
        } elseif (empty($item_name)) {
            $error = 'Item description/name is required.';
        } elseif ($quantity <= 0) {
            $error = 'Quantity must be greater than zero.';
        } elseif ($cost_zmw < 0) {
            $error = 'Cost cannot be negative.';
        } else {
            // Process payment via MTN MoMo / Airtel / Cash engine
            $pay_res = process_input_payment($payment_method, $phone_number, $cost_zmw, $item_name, $farm_id);
            $payment_status = $pay_res['status'] ?? 'completed';
            $provider_ref   = $pay_res['provider_ref'] ?? null;
            $provider_resp  = $pay_res['response_msg'] ?? null;

            // 1. Insert into input_purchases table
            $stmt_ins = $pdo->prepare('
                INSERT INTO input_purchases (
                    farm_id, item_name, category, quantity, unit, cost_zmw, 
                    purchase_date, payment_method, phone_number, payment_status, 
                    provider_ref, provider_response, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt_ins->execute([
                $farm_id, $item_name, $category, $quantity, $unit, $cost_zmw,
                $purchase_date, $payment_method, $phone_number, $payment_status,
                $provider_ref, $provider_resp, $notes
            ]);
            $purchase_id = (int)$pdo->lastInsertId();

            // 2. Also record in financial_transactions as an operating expense
            if ($cost_zmw > 0 && $payment_status === 'completed') {
                $category_label = $categories[$category] ?? ucfirst($category);
                $stmt_fin = $pdo->prepare('
                    INSERT INTO financial_transactions (
                        farm_id, type, category, amount, description, 
                        transaction_date, payment_method, reference_no
                    ) VALUES (?, "expense", ?, ?, ?, ?, ?, ?)
                ');
                $desc = "Input: {$item_name} ({$quantity} {$unit})";
                $stmt_fin->execute([
                    $farm_id,
                    'Input Purchases',
                    $cost_zmw,
                    $desc,
                    $purchase_date,
                    $payment_method,
                    $provider_ref ?: "PUR-{$purchase_id}"
                ]);
            }

            // 3. Sync with inventory_items if requested
            if ($sync_inventory && $payment_status === 'completed') {
                // Check if existing item exists
                $stmt_inv_chk = $pdo->prepare('SELECT id, quantity FROM inventory_items WHERE farm_id = ? AND LOWER(name) = LOWER(?) LIMIT 1');
                $stmt_inv_chk->execute([$farm_id, $item_name]);
                $existing_inv = $stmt_inv_chk->fetch();

                if ($existing_inv) {
                    $new_qty = (float)$existing_inv['quantity'] + $quantity;
                    $stmt_up = $pdo->prepare('UPDATE inventory_items SET quantity = ?, updated_at = NOW() WHERE id = ?');
                    $stmt_up->execute([$new_qty, $existing_inv['id']]);
                } else {
                    $unit_cost = ($quantity > 0) ? ($cost_zmw / $quantity) : 0.0;
                    $inv_cat = in_array($category, ['seed', 'fertilizer', 'pesticide', 'feed', 'equipment', 'veterinary', 'fuel', 'tools']) ? $category : 'other';
                    $stmt_inv_new = $pdo->prepare('
                        INSERT INTO inventory_items (farm_id, name, category, quantity, unit, low_stock_threshold, unit_cost_zmw, storage_location, notes)
                        VALUES (?, ?, ?, ?, ?, 5.00, ?, "Main Farm Store", ?)
                    ');
                    $stmt_inv_new->execute([$farm_id, $item_name, $inv_cat, $quantity, $unit, $unit_cost, $notes]);
                }
            }

            // Log farm activity
            $stmt_act = $pdo->prepare('
                INSERT INTO activity_log (farm_id, activity_type, description, activity_date)
                VALUES (?, "note", ?, ?)
            ');
            $act_desc = "Input acquired: {$item_name} ({$quantity} {$unit}) for " . format_zmw($cost_zmw) . " via " . strtoupper($payment_method) . " [Ref: {$provider_ref}]";
            $stmt_act->execute([$farm_id, $act_desc, $purchase_date]);

            set_flash('green', "Purchase recorded successfully! Ref: {$provider_ref}");
            header('Location: inputs.php?farm_id=' . $farm_id);
            exit;
        }
    }
}

$page_title = 'Record Farm Input Purchase';
include __DIR__ . '/includes/header.php';
?>

<div class="ledger-wrapper">

    <!-- Header Card -->
    <div class="ledger-card border-ochre">
        <div class="card-header-ruled">
            <div>
                <span class="folio-tag">EXPENDITURE ENTRY &bull; VOUCHER № INP-<?php echo strtoupper(substr(md5(uniqid()), 0, 6)); ?></span>
                <h1 style="margin-top: 6px;">Record Input Purchase</h1>
                <p style="color: var(--ink-muted); margin-bottom: 0; font-size: 14px;">
                    Log seed, fertilizer, agrochemical, or equipment acquisitions with Cash, MTN MoMo, or Airtel Money sandbox validation.
                </p>
            </div>
            <div style="text-align: right;">
                <a href="inputs.php" class="ledger-btn ledger-btn-sm">&larr; Back to Inputs Ledger</a>
            </div>
        </div>

        <?php if (!empty($error)): ?>
        <div class="flash-message flash-red" style="margin-top: 14px;">
            <strong>ENTRY REJECTED:</strong> <?php echo sanitize($error); ?>
        </div>
        <?php endif; ?>

        <!-- Form Entry -->
        <form method="POST" action="input_add.php" style="margin-top: 20px;">
            <?php echo csrf_field(); ?>

            <div class="form-grid">
                <!-- Farm Selection -->
                <div class="form-group">
                    <label for="farm_id">Target Farm Holding <span style="color: var(--stamp-red);">*</span></label>
                    <select name="farm_id" id="farm_id" class="form-control" required>
                        <?php foreach ($farms as $f): ?>
                        <option value="<?php echo (int)$f['id']; ?>" <?php echo ($f['id'] == $preset_farm_id) ? 'selected' : ''; ?>>
                            <?php echo sanitize($f['farm_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Category -->
                <div class="form-group">
                    <label for="category">Input Category <span style="color: var(--stamp-red);">*</span></label>
                    <select name="category" id="category" class="form-control" required>
                        <?php foreach ($categories as $cat_key => $cat_label): ?>
                        <option value="<?php echo sanitize($cat_key); ?>">
                            <?php echo sanitize($cat_label); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Item Name / Description -->
                <div class="form-group span-2">
                    <label for="item_name">Item Name &amp; Commercial Brand <span style="color: var(--stamp-red);">*</span></label>
                    <input type="text" name="item_name" id="item_name" class="form-control" required 
                           placeholder="e.g. D-Compound Basal Fertilizer (50kg), Seed Co SC647, or Triatix Dip">
                </div>

                <!-- Quantity -->
                <div class="form-group">
                    <label for="quantity">Quantity Purchased <span style="color: var(--stamp-red);">*</span></label>
                    <input type="number" name="quantity" id="quantity" class="form-control" step="0.01" min="0.01" value="1.00" required>
                </div>

                <!-- Unit -->
                <div class="form-group">
                    <label for="unit">Packaging / Measurement Unit <span style="color: var(--stamp-red);">*</span></label>
                    <input type="text" name="unit" id="unit" list="units_list" class="form-control" value="50kg bags" required>
                    <datalist id="units_list">
                        <?php foreach ($common_units as $u): ?>
                        <option value="<?php echo sanitize($u); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <!-- Total Cost ZMW -->
                <div class="form-group">
                    <label for="cost_zmw">Total Cost (ZMW / Kwacha) <span style="color: var(--stamp-red);">*</span></label>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span class="mono" style="font-size: 16px; font-weight: 600; color: var(--stamp-ochre);">K</span>
                        <input type="number" name="cost_zmw" id="cost_zmw" class="form-control" step="0.01" min="0.00" placeholder="0.00" required>
                    </div>
                </div>

                <!-- Purchase Date -->
                <div class="form-group">
                    <label for="purchase_date">Transaction Date <span style="color: var(--stamp-red);">*</span></label>
                    <input type="date" name="purchase_date" id="purchase_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <!-- Payment Method -->
                <div class="form-group">
                    <label for="paymentMethodSelect">Disbursement / Settlement Method <span style="color: var(--stamp-red);">*</span></label>
                    <select name="payment_method" id="paymentMethodSelect" class="form-control" required>
                        <option value="cash">Cash on Hand / In-Person Receipt</option>
                        <option value="mtn_momo">MTN Mobile Money (Sandbox / Push)</option>
                        <option value="airtel_money">Airtel Money (Sandbox / Push)</option>
                    </select>
                </div>

                <!-- Phone number for MoMo (Dynamic toggle via ledger.js) -->
                <div class="form-group" id="phoneInputGroup" style="display: none;">
                    <label for="paymentPhoneInput">Mobile Money Payer Number <span style="color: var(--stamp-red);">*</span></label>
                    <input type="tel" name="phone_number" id="paymentPhoneInput" class="form-control" placeholder="0966 000000">
                    <small id="phoneHint" style="font-size: 12px; color: var(--ink-muted); margin-top: 4px; display: block;"></small>
                </div>

                <!-- Auto-Inventory Sync Checkbox -->
                <div class="form-group span-2" style="background: var(--paper-subtle); padding: 12px 14px; border-radius: 2px; border-left: 3px solid var(--stamp-green);">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin-bottom: 0;">
                        <input type="checkbox" name="sync_inventory" value="1" checked style="width: 18px; height: 18px; accent-color: var(--stamp-green);">
                        <div>
                            <strong>Automatically Sync with Farm Inventory Ledger</strong>
                            <div style="font-size: 13px; color: var(--ink-muted);">
                                Automatically increments stock quantity in your farm inventory and updates unit replacement costs.
                            </div>
                        </div>
                    </label>
                </div>

                <!-- Notes -->
                <div class="form-group span-2">
                    <label for="notes">Voucher Memo / Supplier Details</label>
                    <textarea name="notes" id="notes" class="form-control" rows="2" placeholder="e.g. Purchased at Omnia Agro depot Lusaka. Batch number #OMN-984. Basal application for Block A."></textarea>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div style="display: flex; gap: 12px; align-items: center; margin-top: 24px; padding-top: 14px; border-top: 1px dashed var(--border-rule);">
                <button type="submit" class="ledger-btn ledger-btn-primary">
                    [ COMMIT TO FIELD LEDGER ]
                </button>
                <a href="inputs.php" class="ledger-btn ledger-btn-sm">
                    Cancel &amp; Return
                </a>
            </div>
        </form>
    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
