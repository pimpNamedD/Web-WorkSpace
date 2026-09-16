<?php
/**
 * FFMS (Field Ledger) - Input Purchases & Mobile Money Ledger
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
$filter_method = isset($_GET['method']) ? trim($_GET['method']) : '';
$filter_cat = isset($_GET['category']) ? trim($_GET['category']) : '';

$inputs = [];
$total_spent = 0.0;
$momo_spent = 0.0;
$airtel_spent = 0.0;
$cash_spent = 0.0;

if (!empty($farm_ids)) {
    $where = ['f.user_id = ?'];
    $params = [$user_id];

    if ($filter_farm > 0) {
        $where[] = 'i.farm_id = ?';
        $params[] = $filter_farm;
    }
    if (!empty($filter_method) && in_array($filter_method, ['cash', 'mtn_momo', 'airtel_money'])) {
        $where[] = 'i.payment_method = ?';
        $params[] = $filter_method;
    }
    if (!empty($filter_cat)) {
        $where[] = 'i.category = ?';
        $params[] = $filter_cat;
    }

    $sql = '
        SELECT i.*, f.farm_name 
        FROM input_purchases i
        JOIN farms f ON i.farm_id = f.id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY i.purchase_date DESC, i.id DESC
    ';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $inputs = $stmt->fetchAll();

    foreach ($inputs as $inp) {
        $cost = (float)$inp['cost_zmw'];
        if ($inp['payment_status'] === 'completed') {
            $total_spent += $cost;
            if ($inp['payment_method'] === 'mtn_momo') {
                $momo_spent += $cost;
            } elseif ($inp['payment_method'] === 'airtel_money') {
                $airtel_spent += $cost;
            } elseif ($inp['payment_method'] === 'cash') {
                $cash_spent += $cost;
            }
        }
    }
}

$page_title = 'Input Purchases & Mobile Money';
include __DIR__ . '/includes/header.php';
?>

<div class="ledger-wrapper">

    <!-- Title Card -->
    <div class="ledger-card border-ochre">
        <div class="card-header-ruled">
            <div>
                <span class="folio-tag">EXPENSES FOLIO &bull; SECTION 05</span>
                <h1 style="margin-top: 6px;">Input Purchases &amp; Mobile Money Ledger</h1>
                <p style="color: var(--ink-muted); margin-bottom: 0; font-size: 14px;">
                    Track seed, fertilizer, agrochemical, and feed acquisitions with MTN MoMo and Airtel Money sandbox collection records.
                </p>
            </div>
            <div style="display: flex; gap: 8px; align-items: center;">
                <a href="input_add.php" class="ledger-btn ledger-btn-primary">+ Record Input Purchase</a>
            </div>
        </div>

        <!-- Metric Highlights -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin: 16px 0;">
            <div style="background: var(--paper-subtle); border-left: 3px solid var(--stamp-ochre); padding: 10px 14px; border-radius: 2px;">
                <div class="kpi-label">Total Input Folio</div>
                <div style="font-family: var(--font-heading); font-size: 1.45rem; font-weight: 700;"><?php echo format_zmw($total_spent); ?></div>
            </div>
            <div style="background: var(--paper-subtle); border-left: 3px solid var(--stamp-amber); padding: 10px 14px; border-radius: 2px;">
                <div class="kpi-label">MTN MoMo Volume</div>
                <div style="font-family: var(--font-heading); font-size: 1.45rem; font-weight: 700;"><?php echo format_zmw($momo_spent); ?></div>
            </div>
            <div style="background: var(--paper-subtle); border-left: 3px solid var(--stamp-red); padding: 10px 14px; border-radius: 2px;">
                <div class="kpi-label">Airtel Money Volume</div>
                <div style="font-family: var(--font-heading); font-size: 1.45rem; font-weight: 700;"><?php echo format_zmw($airtel_spent); ?></div>
            </div>
            <div style="background: var(--paper-subtle); border-left: 3px solid var(--stamp-green); padding: 10px 14px; border-radius: 2px;">
                <div class="kpi-label">Cash Receipts</div>
                <div style="font-family: var(--font-heading); font-size: 1.45rem; font-weight: 700;"><?php echo format_zmw($cash_spent); ?></div>
            </div>
        </div>

        <!-- Filter Bar -->
        <form method="GET" action="inputs.php" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; background: var(--paper-subtle); padding: 12px 14px; border-radius: 2px; border: 1px solid var(--border-rule);">
            <div style="display: flex; flex-direction: column; gap: 4px;">
                <label style="font-family: var(--font-mono); font-size: 11px; font-weight: 700; text-transform: uppercase;">Holding</label>
                <select name="farm_id" class="form-control" style="padding: 6px 10px; font-size: 13px;">
                    <option value="0">&mdash; All Holdings &mdash;</option>
                    <?php foreach ($user_farms as $uf): ?>
                    <option value="<?php echo (int)$uf['id']; ?>" <?php echo ($filter_farm === (int)$uf['id']) ? 'selected' : ''; ?>>
                        <?php echo sanitize($uf['farm_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; flex-direction: column; gap: 4px;">
                <label style="font-family: var(--font-mono); font-size: 11px; font-weight: 700; text-transform: uppercase;">Payment Method</label>
                <select name="method" class="form-control" style="padding: 6px 10px; font-size: 13px;">
                    <option value="">&mdash; All Payment Types &mdash;</option>
                    <option value="mtn_momo" <?php echo ($filter_method === 'mtn_momo') ? 'selected' : ''; ?>>MTN MoMo Sandbox</option>
                    <option value="airtel_money" <?php echo ($filter_method === 'airtel_money') ? 'selected' : ''; ?>>Airtel Money Sandbox</option>
                    <option value="cash" <?php echo ($filter_method === 'cash') ? 'selected' : ''; ?>>Cash / Direct Receipt</option>
                </select>
            </div>

            <div style="display: flex; flex-direction: column; gap: 4px;">
                <label style="font-family: var(--font-mono); font-size: 11px; font-weight: 700; text-transform: uppercase;">Category</label>
                <select name="category" class="form-control" style="padding: 6px 10px; font-size: 13px;">
                    <option value="">&mdash; All Categories &mdash;</option>
                    <option value="fertilizer" <?php echo ($filter_cat === 'fertilizer') ? 'selected' : ''; ?>>Fertilizer (D-Comp / Urea)</option>
                    <option value="seed" <?php echo ($filter_cat === 'seed') ? 'selected' : ''; ?>>Seed &amp; Cultivars</option>
                    <option value="chemicals" <?php echo ($filter_cat === 'chemicals') ? 'selected' : ''; ?>>Agro-Chemicals &amp; Dips</option>
                    <option value="feed" <?php echo ($filter_cat === 'feed') ? 'selected' : ''; ?>>Livestock Feed / Mash</option>
                    <option value="fuel" <?php echo ($filter_cat === 'fuel') ? 'selected' : ''; ?>>Diesel &amp; Petrol</option>
                    <option value="equipment" <?php echo ($filter_cat === 'equipment') ? 'selected' : ''; ?>>Machinery &amp; Spares</option>
                </select>
            </div>

            <div>
                <button type="submit" class="ledger-btn ledger-btn-sm">Filter Folio</button>
                <a href="inputs.php" class="ledger-btn ledger-btn-sm" style="margin-left: 4px;">Reset</a>
            </div>
        </form>
    </div>

    <!-- Purchases Table -->
    <div class="ledger-card border-ochre">
        <?php if (empty($inputs)): ?>
            <div style="text-align: center; padding: 40px 20px;">
                <span class="stamp-badge stamp-neutral" style="font-size: 13px;">[ NO INPUT PURCHASES LOGGED ]</span>
                <p style="color: var(--ink-muted); margin-top: 14px;">No input purchases found under this filter.</p>
                <a href="input_add.php" class="ledger-btn ledger-btn-primary">+ Record First Input Purchase</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="ledger-table">
                    <thead>
                        <tr>
                            <th>Item &amp; Category</th>
                            <th>Holding</th>
                            <th>Quantity</th>
                            <th class="col-right">Cost (ZMW)</th>
                            <th>Payment Method</th>
                            <th>Payer Phone</th>
                            <th>Provider Ref</th>
                            <th>Status Stamp</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inputs as $inp): ?>
                        <tr>
                            <td>
                                <strong><?php echo sanitize($inp['item_name']); ?></strong>
                                <div style="font-size: 11px; color: var(--ink-faint); font-family: var(--font-mono);">
                                    Category: <?php echo strtoupper(sanitize($inp['category'])); ?>
                                </div>
                                <?php if (!empty($inp['notes'])): ?>
                                <div style="font-size: 12px; color: var(--ink-muted); margin-top: 2px;">
                                    <?php echo sanitize($inp['notes']); ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="col-mono" style="font-size: 12px;"><?php echo sanitize($inp['farm_name']); ?></td>
                            <td class="col-mono"><?php echo format_qty($inp['quantity']); ?> <?php echo sanitize($inp['unit']); ?></td>
                            <td class="col-mono col-right" style="font-weight: 700; font-size: 14px;">
                                <?php echo format_zmw($inp['cost_zmw']); ?>
                            </td>
                            <td class="col-mono">
                                <?php if ($inp['payment_method'] === 'mtn_momo'): ?>
                                    <span style="color: #c98800; font-weight: 600;">MTN MoMo</span>
                                <?php elseif ($inp['payment_method'] === 'airtel_money'): ?>
                                    <span style="color: #bd2727; font-weight: 600;">Airtel Money</span>
                                <?php else: ?>
                                    <span style="color: var(--stamp-green); font-weight: 600;">Cash</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-mono" style="font-size: 12px;">
                                <?php echo sanitize($inp['phone_number'] ?: '—'); ?>
                            </td>
                            <td class="col-mono" style="font-size: 11.5px;">
                                <span title="<?php echo sanitize($inp['provider_response'] ?? ''); ?>">
                                    <?php echo sanitize($inp['provider_ref'] ?: '—'); ?>
                                </span>
                            </td>
                            <td><?php echo render_stamp_badge($inp['payment_status']); ?></td>
                            <td class="col-mono" style="font-size: 11.5px;"><?php echo format_date_mono($inp['purchase_date']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
