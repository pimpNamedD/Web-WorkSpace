<?php
/**
 * FFMS (Field Ledger) - Record Financial Transaction Voucher
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

$income_categories = [
    'Crop Sales'          => 'Grain & Harvest Crop Sales',
    'Livestock Sales'     => 'Live Cattle, Smallstock & Broiler Sales',
    'Dairy & Eggs'        => 'Milk, Eggs & Daily Farm Produce',
    'Carbon Credits'      => 'Voluntary Carbon Credit Token Monetization',
    'Equipment Rental'    => 'Tractor & Implement Hire-Out Income',
    'Government Subsidy'  => 'FISP / Agricultural Support Grants',
    'Other Income'        => 'Miscellaneous Receipts'
];

$expense_categories = [
    'Input Purchases'        => 'Seeds, Fertilizers & Agrochemicals',
    'Labor & Wages'          => 'Farm Crew Wages & Operator Allowances',
    'Fuel & Electricity'     => 'Diesel, Generator Fuel & Pumping Power',
    'Equipment Maintenance'  => 'Tractor Repairs, Pivot Spares & Implements',
    'Veterinary & Dips'      => 'Livestock Vaccines, Plunge Dips & Care',
    'Transport & Logistics'  => 'Haulage to Depots & Abattoirs',
    'Feed & Nutrition'       => 'Broiler Mash, Dairy Meal & Mineral Licks',
    'Other Operating Cost'   => 'General Overhead Expenses'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please try again.';
    } else {
        $farm_id        = (int)($_POST['farm_id'] ?? 0);
        $type           = trim($_POST['type'] ?? 'income');
        $category       = trim($_POST['category'] ?? '');
        $amount         = (float)($_POST['amount'] ?? 0);
        $description    = trim($_POST['description'] ?? '');
        $date           = !empty($_POST['transaction_date']) ? trim($_POST['transaction_date']) : date('Y-m-d');
        $method         = trim($_POST['payment_method'] ?? 'cash');
        $reference_no   = trim($_POST['reference_no'] ?? '');

        // Verify farm ownership
        $stmt_check = $pdo->prepare('SELECT id FROM farms WHERE id = ? AND user_id = ?');
        $stmt_check->execute([$farm_id, $user_id]);
        if (!$stmt_check->fetch()) {
            $error = 'Invalid farm holding selected.';
        } elseif ($amount <= 0) {
            $error = 'Transaction amount must be greater than zero.';
        } elseif (empty($description)) {
            $error = 'Description/narrative is required for accounting verification.';
        } else {
            if (empty($reference_no)) {
                $prefix = ($type === 'income') ? 'REC-' : 'VOUCH-';
                $reference_no = $prefix . strtoupper(substr(md5(uniqid()), 0, 8));
            }

            $stmt_ins = $pdo->prepare('
                INSERT INTO financial_transactions (
                    farm_id, type, category, amount, description, 
                    transaction_date, payment_method, reference_no
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt_ins->execute([
                $farm_id, $type, $category, $amount, $description,
                $date, $method, $reference_no
            ]);

            // Also record in activity log
            $type_label = ($type === 'income') ? 'Income voucher recorded' : 'Expense voucher recorded';
            $act_desc = "{$type_label}: {$category} - " . format_zmw($amount) . " [Ref: {$reference_no}]. Narrative: {$description}";
            $stmt_act = $pdo->prepare('INSERT INTO activity_log (farm_id, activity_type, description, activity_date) VALUES (?, "note", ?, ?)');
            $stmt_act->execute([$farm_id, $act_desc, $date]);

            set_flash('green', "Voucher {$reference_no} recorded into farm accounts!");
            header('Location: finances.php?farm_id=' . $farm_id);
            exit;
        }
    }
}

$page_title = 'Record Financial Voucher';
include __DIR__ . '/includes/header.php';
?>

<div class="ledger-wrapper">

    <div class="ledger-card border-navy">
        <div class="card-header-ruled">
            <div>
                <span class="folio-tag">ACCOUNTS FOLIO &bull; TRANSACTION VOUCHER</span>
                <h1 style="margin-top: 6px;">Record Financial Voucher</h1>
                <p style="color: var(--ink-muted); margin-bottom: 0; font-size: 14px;">
                    Log farm revenue inflows (grain contracts, livestock sales) or operating cost disbursements.
                </p>
            </div>
            <div style="text-align: right;">
                <a href="finances.php" class="ledger-btn ledger-btn-sm">&larr; Back to Financial Accounts</a>
            </div>
        </div>

        <?php if (!empty($error)): ?>
        <div class="flash-message flash-red" style="margin-top: 14px;">
            <strong>ERROR:</strong> <?php echo sanitize($error); ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="finance_add.php" style="margin-top: 20px;">
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
                    <label for="typeSelect">Transaction Flow Type <span style="color: var(--stamp-red);">*</span></label>
                    <select name="type" id="typeSelect" class="form-control" required>
                        <option value="income">Revenue Inflow (+ Income)</option>
                        <option value="expense">Operating Disbursement (- Expense)</option>
                    </select>
                </div>

                <div class="form-group span-2">
                    <label for="categorySelect">Account Category <span style="color: var(--stamp-red);">*</span></label>
                    <select name="category" id="categorySelect" class="form-control" required>
                        <optgroup label="Revenue Categories" id="incomeOptGroup">
                            <?php foreach ($income_categories as $c_val => $c_lbl): ?>
                            <option value="<?php echo sanitize($c_val); ?>"><?php echo sanitize($c_lbl); ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Operating Expense Categories" id="expenseOptGroup">
                            <?php foreach ($expense_categories as $c_val => $c_lbl): ?>
                            <option value="<?php echo sanitize($c_val); ?>"><?php echo sanitize($c_lbl); ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>

                <div class="form-group">
                    <label for="amount">Voucher Amount (ZMW) <span style="color: var(--stamp-red);">*</span></label>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span class="mono" style="font-weight: 600; font-size: 16px; color: var(--stamp-ochre);">K</span>
                        <input type="number" name="amount" id="amount" step="0.01" min="0.01" class="form-control" placeholder="0.00" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="transaction_date">Date of Transaction <span style="color: var(--stamp-red);">*</span></label>
                    <input type="date" name="transaction_date" id="transaction_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group">
                    <label for="payment_method">Payment / Settlement Instrument <span style="color: var(--stamp-red);">*</span></label>
                    <select name="payment_method" id="payment_method" class="form-control" required>
                        <option value="bank_transfer">Bank Wire / EFT / Direct Deposit</option>
                        <option value="mtn_momo">MTN Mobile Money</option>
                        <option value="airtel_money">Airtel Money</option>
                        <option value="cash">Cash on Hand</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="reference_no">External Reference / Invoice / Receipt №</label>
                    <input type="text" name="reference_no" id="reference_no" class="form-control" placeholder="e.g. NML-INV-8891, TX-MOMO-902, CASH-45">
                    <small style="font-size: 11px; color: var(--ink-muted);">Auto-generated if left empty.</small>
                </div>

                <div class="form-group span-2">
                    <label for="description">Voucher Narrative / Counterparty Details <span style="color: var(--stamp-red);">*</span></label>
                    <textarea name="description" id="description" class="form-control" rows="2" required
                              placeholder="e.g. Sold 50 metric tonnes white maize to National Milling depot; or Paid tractor driver monthly allowances."></textarea>
                </div>
            </div>

            <div style="display: flex; gap: 12px; margin-top: 24px; padding-top: 14px; border-top: 1px dashed var(--border-rule);">
                <button type="submit" class="ledger-btn ledger-btn-primary">
                    [ RECORD IN FINANCIAL FOLIO ]
                </button>
                <a href="finances.php" class="ledger-btn ledger-btn-sm">Cancel</a>
            </div>
        </form>
    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
