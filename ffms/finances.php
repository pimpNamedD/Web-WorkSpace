<?php
/**
 * FFMS (Field Ledger) - Farm Financial Folio & Cashflow Ledger
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
$filter_type = isset($_GET['type']) ? trim($_GET['type']) : '';
$filter_cat  = isset($_GET['category']) ? trim($_GET['category']) : '';

$transactions = [];
$total_income = 0.0;
$total_expense = 0.0;
$net_profit = 0.0;
$categories_income = [];
$categories_expense = [];

if (!empty($farm_ids)) {
    $where = ['f.user_id = ?'];
    $params = [$user_id];

    if ($filter_farm > 0) {
        $where[] = 't.farm_id = ?';
        $params[] = $filter_farm;
    }
    if (!empty($filter_type) && in_array($filter_type, ['income', 'expense'])) {
        $where[] = 't.type = ?';
        $params[] = $filter_type;
    }
    if (!empty($filter_cat)) {
        $where[] = 't.category = ?';
        $params[] = $filter_cat;
    }

    $sql = '
        SELECT t.*, f.farm_name 
        FROM financial_transactions t
        JOIN farms f ON t.farm_id = f.id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY t.transaction_date DESC, t.id DESC
    ';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();

    // Calculate totals and category breakdowns
    foreach ($transactions as $tx) {
        $amt = (float)$tx['amount'];
        if ($tx['type'] === 'income') {
            $total_income += $amt;
            $cat = $tx['category'];
            $categories_income[$cat] = ($categories_income[$cat] ?? 0.0) + $amt;
        } else {
            $total_expense += $amt;
            $cat = $tx['category'];
            $categories_expense[$cat] = ($categories_expense[$cat] ?? 0.0) + $amt;
        }
    }
    $net_profit = $total_income - $total_expense;
}

$page_title = 'Farm Financial Folio & Cashflow';
include __DIR__ . '/includes/header.php';
?>

<div class="ledger-wrapper">

    <!-- Header Card -->
    <div class="ledger-card border-navy">
        <div class="card-header-ruled">
            <div>
                <span class="folio-tag">FINANCIAL FOLIO &bull; SECTION 07</span>
                <h1 style="margin-top: 6px;">Farm Financial Ledger &amp; Cashflow</h1>
                <p style="color: var(--ink-muted); margin-bottom: 0; font-size: 14px;">
                    Consolidated double-entry financial accounts: grain and cattle sales, input costs, labor wages, and carbon credit inflows.
                </p>
            </div>
            <div style="display: flex; gap: 8px; align-items: center;">
                <a href="finance_add.php" class="ledger-btn ledger-btn-primary">+ Record Financial Voucher</a>
                <button class="ledger-btn ledger-btn-sm trigger-print">Print Statement</button>
            </div>
        </div>

        <!-- KPI Balance Sheet Bar -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin: 16px 0;">
            <div style="background: var(--paper-subtle); border-left: 3px solid var(--stamp-green); padding: 12px 16px; border-radius: 2px;">
                <div class="kpi-label">Total Revenue / Inflows</div>
                <div class="kpi-value mono" style="font-size: 1.55rem; color: var(--stamp-green);">
                    + <?php echo format_zmw($total_income); ?>
                </div>
                <small style="color: var(--ink-muted); font-size: 11px;">Crop sales, livestock auctions, carbon</small>
            </div>

            <div style="background: var(--paper-subtle); border-left: 3px solid var(--stamp-red); padding: 12px 16px; border-radius: 2px;">
                <div class="kpi-label">Operating Expenses / Outflows</div>
                <div class="kpi-value mono" style="font-size: 1.55rem; color: var(--stamp-red);">
                    - <?php echo format_zmw($total_expense); ?>
                </div>
                <small style="color: var(--ink-muted); font-size: 11px;">Inputs, seed, wages, maintenance, fuel</small>
            </div>

            <div style="background: var(--paper-subtle); border-left: 3px solid <?php echo ($net_profit >= 0) ? 'var(--stamp-green)' : 'var(--stamp-red)'; ?>; padding: 12px 16px; border-radius: 2px;">
                <div class="kpi-label">Net Operating Margin (P&amp;L)</div>
                <div class="kpi-value mono" style="font-size: 1.55rem; color: <?php echo ($net_profit >= 0) ? 'var(--stamp-green)' : 'var(--stamp-red)'; ?>;">
                    <?php echo ($net_profit >= 0 ? '+ ' : '') . format_zmw($net_profit); ?>
                </div>
                <small style="color: var(--ink-muted); font-size: 11px;">
                    <?php echo ($net_profit >= 0) ? 'Operating in surplus / profit' : 'Operating in deficit'; ?>
                </small>
            </div>
        </div>

        <!-- Filter Bar -->
        <form method="GET" action="finances.php" class="filter-strip no-print" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center; background: var(--paper-card-alt); padding: 10px; border: 1px dashed var(--border-rule);">
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
                <label style="font-size: 12px; font-weight: 600; color: var(--ink-muted);">Transaction Flow:</label>
                <select name="type" class="form-control-sm" onchange="this.form.submit()">
                    <option value="">All (Income &amp; Expenses)</option>
                    <option value="income" <?php echo ($filter_type === 'income') ? 'selected' : ''; ?>>Income Only</option>
                    <option value="expense" <?php echo ($filter_type === 'expense') ? 'selected' : ''; ?>>Expenses Only</option>
                </select>
            </div>

            <?php if ($filter_farm || $filter_type || $filter_cat): ?>
            <div style="margin-left: auto;">
                <a href="finances.php" class="ledger-btn ledger-btn-sm" style="font-size: 11px;">Reset Filters</a>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Category Inflow / Outflow Summary Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px; margin-top: 16px;">
        <div class="ledger-card border-green">
            <h3 style="font-size: 1.15rem; color: var(--stamp-green); margin-bottom: 8px;">
                Revenue Inflows Breakdown
            </h3>
            <?php if (empty($categories_income)): ?>
            <p style="color: var(--ink-muted); font-size: 13px;">No income recorded for this period.</p>
            <?php else: ?>
            <ul style="list-style: none; padding: 0; margin: 0; font-size: 13.5px;">
                <?php foreach ($categories_income as $cat_name => $cat_amt): ?>
                <li style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px dashed var(--border-rule);">
                    <span><?php echo sanitize($cat_name); ?></span>
                    <strong class="mono" style="color: var(--stamp-green);"><?php echo format_zmw($cat_amt); ?></strong>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

        <div class="ledger-card border-red">
            <h3 style="font-size: 1.15rem; color: var(--stamp-red); margin-bottom: 8px;">
                Operating Expenses Breakdown
            </h3>
            <?php if (empty($categories_expense)): ?>
            <p style="color: var(--ink-muted); font-size: 13px;">No expenses recorded for this period.</p>
            <?php else: ?>
            <ul style="list-style: none; padding: 0; margin: 0; font-size: 13.5px;">
                <?php foreach ($categories_expense as $cat_name => $cat_amt): ?>
                <li style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px dashed var(--border-rule);">
                    <span><?php echo sanitize($cat_name); ?></span>
                    <strong class="mono" style="color: var(--stamp-red);"><?php echo format_zmw($cat_amt); ?></strong>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- Financial Journal Table -->
    <div class="ledger-card" style="margin-top: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
            <h2 style="font-size: 1.25rem; margin-bottom: 0;">General Accounts Journal</h2>
            <span class="mono" style="font-size: 12px; color: var(--ink-muted);"><?php echo count($transactions); ?> Records</span>
        </div>

        <?php if (empty($transactions)): ?>
        <div style="text-align: center; padding: 30px; background: var(--paper-subtle); border: 1px dashed var(--border-rule);">
            <p style="color: var(--ink-muted);">No financial transactions logged yet.</p>
            <a href="finance_add.php" class="ledger-btn ledger-btn-primary">+ Record First Voucher</a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Voucher Ref</th>
                        <th>Farm Holding</th>
                        <th>Flow</th>
                        <th>Account Category</th>
                        <th>Description / Narrative</th>
                        <th>Method</th>
                        <th style="text-align: right;">Amount (ZMW)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $t): 
                        $is_inc = ($t['type'] === 'income');
                    ?>
                    <tr>
                        <td class="mono"><?php echo format_date_mono($t['transaction_date']); ?></td>
                        <td>
                            <span class="mono" style="font-size: 11.5px; background: var(--paper-subtle); padding: 2px 6px; border: 1px solid var(--border-rule);">
                                <?php echo sanitize($t['reference_no'] ?: '#' . $t['id']); ?>
                            </span>
                        </td>
                        <td><strong><?php echo sanitize($t['farm_name']); ?></strong></td>
                        <td>
                            <?php if ($is_inc): ?>
                            <span class="stamp-badge stamp-green" style="font-size: 10px;">[ INFLOW ]</span>
                            <?php else: ?>
                            <span class="stamp-badge stamp-red" style="font-size: 10px;">[ OUTFLOW ]</span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?php echo sanitize($t['category']); ?></strong></td>
                        <td><?php echo sanitize($t['description']); ?></td>
                        <td>
                            <small class="mono" style="text-transform: uppercase;">
                                <?php echo str_replace('_', ' ', sanitize($t['payment_method'])); ?>
                            </small>
                        </td>
                        <td style="text-align: right;" class="mono">
                            <strong style="<?php echo $is_inc ? 'color: var(--stamp-green); font-size: 15px;' : 'color: var(--stamp-red);'; ?>">
                                <?php echo ($is_inc ? '+' : '-') . ' ' . format_zmw($t['amount']); ?>
                            </strong>
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
