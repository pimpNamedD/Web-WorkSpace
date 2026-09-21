<?php
/**
 * FFMS (Field Ledger) - Token Economy & Gamification Wallet
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

if (empty($user_farms)) {
    set_flash('amber', 'Please register at least one farm holding before accessing the Token Economy.');
    header('Location: farms.php');
    exit;
}

$active_farm_id = isset($_GET['farm_id']) ? (int)$_GET['farm_id'] : ($user_farms[0]['id'] ?? 0);
$error = '';

// Fetch workers under this farm for reward selection
$stmt_w = $pdo->prepare('SELECT id, name, role, points_balance FROM workers WHERE farm_id = ? AND is_active = 1 ORDER BY name ASC');
$stmt_w->execute([$active_farm_id]);
$farm_workers = $stmt_w->fetchAll();

// Redemption Store Catalog
$redemption_catalog = [
    'airtime_k50' => [
        'title'       => 'K50 Mobile Airtime (MTN / Airtel)',
        'cost_tokens' => 15,
        'category'    => 'Communication',
        'desc'        => 'Instant electronic airtime voucher pushed to worker phone number.'
    ],
    'safety_boots' => [
        'title'       => 'Heavy Duty Gum Boots & Gloves',
        'cost_tokens' => 35,
        'category'    => 'Safety & PPE',
        'desc'        => 'Certified acid and plunge-dip resistant PVC farm boots.'
    ],
    'seed_pocket' => [
        'title'       => '5kg Certified Vegetable Seed Pocket',
        'cost_tokens' => 40,
        'category'    => 'Agro-Inputs',
        'desc'        => 'High-germination sugar beans or sweet corn pocket for kitchen garden.'
    ],
    'fertilizer_bag' => [
        'title'       => '50kg D-Compound Fertilizer Bag',
        'cost_tokens' => 60,
        'category'    => 'Agro-Inputs',
        'desc'        => 'Basal dressing fertilizer bag redeemable at farm store depot.'
    ],
    'cash_bonus' => [
        'title'       => 'K250 Farm Cash Incentive Bonus',
        'cost_tokens' => 100,
        'category'    => 'Cash Bonus',
        'desc'        => 'Disbursed directly via MTN MoMo or cash payout voucher.'
    ]
];

// Handle Actions: Reward Worker or Redeem Item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = $_POST['action'] ?? '';

    // 1. Reward Worker Action
    if ($action === 'reward_worker') {
        $worker_id = (int)($_POST['worker_id'] ?? 0);
        $amount    = (int)($_POST['amount'] ?? 0);
        $reason    = trim($_POST['reason'] ?? '');

        if ($worker_id <= 0) {
            $error = 'Please select a worker to reward.';
        } elseif ($amount <= 0) {
            $error = 'Token amount must be greater than zero.';
        } elseif (empty($reason)) {
            $error = 'Reward reason citation is required.';
        } else {
            $tx_hash = '0x' . bin2hex(random_bytes(32));

            // Insert into token_transactions
            $stmt_ins = $pdo->prepare('
                INSERT INTO token_transactions (
                    farm_id, user_id, worker_id, token_type, amount, reason, blockchain_tx_hash
                ) VALUES (?, ?, ?, "reward", ?, ?, ?)
            ');
            $stmt_ins->execute([$active_farm_id, $user_id, $worker_id, $amount, $reason, $tx_hash]);

            // Update worker points balance
            $stmt_up = $pdo->prepare('UPDATE workers SET points_balance = points_balance + ? WHERE id = ?');
            $stmt_up->execute([$amount, $worker_id]);

            // Log points
            $stmt_log = $pdo->prepare('INSERT INTO worker_points_log (worker_id, points_delta, reason, log_date) VALUES (?, ?, ?, CURDATE())');
            $stmt_log->execute([$worker_id, $amount, $reason]);

            set_flash('green', "Minted {$amount} TOKENS to worker! Blockchain Tx: " . substr($tx_hash, 0, 18) . '...');
            header('Location: tokens.php?farm_id=' . $active_farm_id);
            exit;
        }
    }

    // 2. Redeem Item Action
    if ($action === 'redeem_item') {
        $worker_id  = (int)($_POST['worker_id'] ?? 0);
        $item_key   = trim($_POST['item_key'] ?? '');

        if (!array_key_exists($item_key, $redemption_catalog)) {
            $error = 'Invalid catalog item selected for redemption.';
        } elseif ($worker_id <= 0) {
            $error = 'Select the redeeming worker account.';
        } else {
            $item = $redemption_catalog[$item_key];
            $cost = (int)$item['cost_tokens'];

            // Check worker balance
            $stmt_chk_b = $pdo->prepare('SELECT name, points_balance FROM workers WHERE id = ? AND farm_id = ?');
            $stmt_chk_b->execute([$worker_id, $active_farm_id]);
            $w_row = $stmt_chk_b->fetch();

            if (!$w_row || (int)$w_row['points_balance'] < $cost) {
                $error = "Insufficient token balance! Worker has " . ((int)($w_row['points_balance'] ?? 0)) . " tokens, but item requires {$cost}.";
            } else {
                $tx_hash = '0x' . bin2hex(random_bytes(32));
                $neg_amount = -$cost;
                $red_reason = "Redeemed {$cost} tokens for {$item['title']}";

                $stmt_red = $pdo->prepare('
                    INSERT INTO token_transactions (
                        farm_id, user_id, worker_id, token_type, amount, reason, blockchain_tx_hash
                    ) VALUES (?, ?, ?, "redemption", ?, ?, ?)
                ');
                $stmt_red->execute([$active_farm_id, $user_id, $worker_id, $neg_amount, $red_reason, $tx_hash]);

                // Deduct from worker balance
                $stmt_ded = $pdo->prepare('UPDATE workers SET points_balance = points_balance - ? WHERE id = ?');
                $stmt_ded->execute([$cost, $worker_id]);

                // Log points deduction
                $stmt_log = $pdo->prepare('INSERT INTO worker_points_log (worker_id, points_delta, reason, log_date) VALUES (?, ?, ?, CURDATE())');
                $stmt_log->execute([$worker_id, $neg_amount, $red_reason]);

                set_flash('green', "Redemption successful! {$item['title']} claimed by {$w_row['name']}. Tx: " . substr($tx_hash, 0, 18) . '...');
                header('Location: tokens.php?farm_id=' . $active_farm_id);
                exit;
            }
        }
    }
}

// Compute balances for this user / active farm
$stmt_tot = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM token_transactions WHERE farm_id = ?');
$stmt_tot->execute([$active_farm_id]);
$net_token_supply = (int)$stmt_tot->fetchColumn();

$stmt_rew = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM token_transactions WHERE farm_id = ? AND token_type = "reward"');
$stmt_rew->execute([$active_farm_id]);
$total_reward_tokens = (int)$stmt_rew->fetchColumn();

$stmt_carb = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM token_transactions WHERE farm_id = ? AND token_type = "carbon"');
$stmt_carb->execute([$active_farm_id]);
$total_carbon_tokens = (int)$stmt_carb->fetchColumn();

$stmt_red_tot = $pdo->prepare('SELECT COALESCE(SUM(ABS(amount)), 0) FROM token_transactions WHERE farm_id = ? AND token_type = "redemption"');
$stmt_red_tot->execute([$active_farm_id]);
$total_redeemed_tokens = (int)$stmt_red_tot->fetchColumn();

// Fetch transaction history
$stmt_tx = $pdo->prepare('
    SELECT t.*, w.name as worker_name 
    FROM token_transactions t
    LEFT JOIN workers w ON t.worker_id = w.id
    WHERE t.farm_id = ?
    ORDER BY t.created_at DESC, t.id DESC
    LIMIT 20
');
$stmt_tx->execute([$active_farm_id]);
$token_history = $stmt_tx->fetchAll();

$page_title = 'Token Economy & Gamification Wallet';
include __DIR__ . '/includes/header.php';
?>

<div class="ledger-wrapper">

    <!-- Header Card -->
    <div class="ledger-card border-ochre">
        <div class="card-header-ruled">
            <div>
                <span class="folio-tag">WEB3 TOKEN ECONOMY &bull; SMART REWARDS WALLET</span>
                <h1 style="margin-top: 6px;">Farm Token Economy &amp; Gamification</h1>
                <p style="color: var(--ink-muted); margin-bottom: 0; font-size: 14px;">
                    Gamify agricultural sustainability and crew performance. Award verifiable cryptographic tokens for eco-friendly practices and water conservation.
                </p>
            </div>
            <div style="text-align: right;">
                <span class="stamp-badge stamp-amber">[ ETH CONTRACT SIMULATOR ]</span>
            </div>
        </div>

        <!-- Holding Switcher -->
        <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 14px; padding-top: 10px; border-top: 1px dashed var(--border-rule);">
            <?php foreach ($user_farms as $uf): ?>
            <a href="tokens.php?farm_id=<?php echo (int)$uf['id']; ?>" class="ledger-btn ledger-btn-sm <?php echo ($uf['id'] == $active_farm_id) ? 'ledger-btn-primary' : ''; ?>">
                <?php echo sanitize($uf['farm_name']); ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (!empty($error)): ?>
    <div class="flash-message flash-red" style="margin-top: 14px;">
        <strong>TRANSACTION FAILED:</strong> <?php echo sanitize($error); ?>
    </div>
    <?php endif; ?>

    <!-- Token Balance KPI Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 14px; margin-top: 16px;">
        
        <!-- Net Circulating Supply -->
        <div class="ledger-card border-green" style="padding: 16px;">
            <div class="kpi-label">Active Circulating Supply</div>
            <div class="kpi-value mono" style="font-size: 1.65rem; color: var(--stamp-green);">
                <?php echo number_format($net_token_supply); ?> <span style="font-size: 13px;">AGRI-TOKENS</span>
            </div>
            <small style="color: var(--ink-muted); font-size: 11px;">Current spendable balance held by crew &amp; vault</small>
        </div>

        <!-- Reward Tokens Minted -->
        <div class="ledger-card border-ochre" style="padding: 16px;">
            <div class="kpi-label">Performance Rewards Issued</div>
            <div class="kpi-value mono" style="font-size: 1.65rem; color: var(--stamp-ochre);">
                +<?php echo number_format($total_reward_tokens); ?> <span style="font-size: 13px;">PTS</span>
            </div>
            <small style="color: var(--ink-muted); font-size: 11px;">Awarded for equipment care &amp; zero loss</small>
        </div>

        <!-- Carbon Tokens Minted -->
        <div class="ledger-card border-navy" style="padding: 16px;">
            <div class="kpi-label">Carbon Credit Tokens</div>
            <div class="kpi-value mono" style="font-size: 1.65rem; color: var(--stamp-navy);">
                +<?php echo number_format($total_carbon_tokens); ?> <span style="font-size: 13px;">CO₂ CREDITS</span>
            </div>
            <small style="color: var(--ink-muted); font-size: 11px;">Minted from verified carbon sequestration</small>
        </div>

        <!-- Tokens Redeemed -->
        <div class="ledger-card border-red" style="padding: 16px;">
            <div class="kpi-label">Total Tokens Redeemed</div>
            <div class="kpi-value mono" style="font-size: 1.65rem; color: var(--stamp-red);">
                -<?php echo number_format($total_redeemed_tokens); ?> <span style="font-size: 13px;">BURNED</span>
            </div>
            <small style="color: var(--ink-muted); font-size: 11px;">Exchanged for seeds, boots, airtime, and cash</small>
        </div>
    </div>

    <!-- Actions Grid: Mint Reward Form + Redemption Store -->
    <div style="display: grid; grid-template-columns: 1fr 1.1fr; gap: 20px; margin-top: 16px;">
        
        <!-- Left: Mint Reward Tokens -->
        <div class="ledger-card border-ochre">
            <h3 style="font-size: 1.2rem; margin-bottom: 8px;">Mint Performance Reward</h3>
            <p style="font-size: 13px; color: var(--ink-muted); margin-bottom: 16px;">
                Issue reward tokens to farm workers for achieving sustainability milestones, nighttime livestock vigilance, or zero-spill equipment handling.
            </p>

            <form method="POST" action="tokens.php?farm_id=<?php echo $active_farm_id; ?>">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="reward_worker">

                <div style="margin-bottom: 12px;">
                    <label style="font-size: 11px; font-weight: 600;">Recipient Worker <span style="color: var(--stamp-red);">*</span></label>
                    <select name="worker_id" class="form-control-sm" style="width: 100%;" required>
                        <option value="">-- Choose Crew Member --</option>
                        <?php foreach ($farm_workers as $fw): ?>
                        <option value="<?php echo (int)$fw['id']; ?>">
                            <?php echo sanitize($fw['name']); ?> (<?php echo sanitize($fw['role']); ?>) — Current: <?php echo (int)$fw['points_balance']; ?> PTS
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-bottom: 12px;">
                    <label style="font-size: 11px; font-weight: 600;">Token Reward Amount <span style="color: var(--stamp-red);">*</span></label>
                    <input type="number" name="amount" class="form-control-sm" style="width: 100%;" value="25" min="5" max="250" step="5" required>
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="font-size: 11px; font-weight: 600;">Citation / Performance Reason <span style="color: var(--stamp-red);">*</span></label>
                    <input type="text" name="reason" class="form-control-sm" style="width: 100%;" required 
                           placeholder="e.g. Clean tractor maintenance checklist & zero fuel spillage">
                </div>

                <button type="submit" class="ledger-btn ledger-btn-primary ledger-btn-sm" style="width: 100%;">
                    [ MINT ON-CHAIN TOKENS ]
                </button>
            </form>
        </div>

        <!-- Right: Token Redemption Store -->
        <div class="ledger-card border-green">
            <h3 style="font-size: 1.2rem; margin-bottom: 8px;">Token Redemption Store</h3>
            <p style="font-size: 13px; color: var(--ink-muted); margin-bottom: 14px;">
                Workers can spend accumulated tokens on farm inputs, mobile airtime, safety gear, or cash bonuses.
            </p>

            <div style="display: flex; flex-direction: column; gap: 10px;">
                <?php foreach ($redemption_catalog as $cat_k => $cat_v): ?>
                <div style="display: flex; justify-content: space-between; align-items: center; background: var(--paper-subtle); padding: 10px 14px; border: 1px solid var(--border-rule); border-radius: 2px;">
                    <div>
                        <div style="font-weight: 700; font-size: 13.5px;"><?php echo sanitize($cat_v['title']); ?></div>
                        <div style="font-size: 11.5px; color: var(--ink-muted);"><?php echo sanitize($cat_v['desc']); ?></div>
                    </div>
                    <div style="text-align: right; min-width: 110px;">
                        <div class="mono" style="font-weight: 700; color: var(--stamp-ochre); font-size: 14px; margin-bottom: 4px;">
                            <?php echo $cat_v['cost_tokens']; ?> TOKENS
                        </div>
                        
                        <!-- Quick Redeem Drawer -->
                        <details style="position: relative;">
                            <summary class="ledger-btn ledger-btn-sm" style="font-size: 10.5px; padding: 2px 6px; cursor: pointer;">
                                [ Redeem ]
                            </summary>
                            <div style="position: absolute; right: 0; top: 100%; z-index: 50; background: var(--paper-card); border: 2px solid var(--border-strong); box-shadow: var(--shadow-binder); padding: 12px; width: 240px; text-align: left; border-radius: 2px;">
                                <form method="POST" action="tokens.php?farm_id=<?php echo $active_farm_id; ?>">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="redeem_item">
                                    <input type="hidden" name="item_key" value="<?php echo $cat_k; ?>">
                                    
                                    <div style="font-size: 11px; margin-bottom: 6px; font-weight: 600;">Redeeming Worker:</div>
                                    <select name="worker_id" class="form-control-sm" style="width: 100%; margin-bottom: 8px;" required>
                                        <?php foreach ($farm_workers as $fw): ?>
                                        <option value="<?php echo (int)$fw['id']; ?>" <?php echo ((int)$fw['points_balance'] < $cat_v['cost_tokens']) ? 'disabled' : ''; ?>>
                                            <?php echo sanitize($fw['name']); ?> (<?php echo (int)$fw['points_balance']; ?> pts)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="ledger-btn ledger-btn-primary ledger-btn-sm" style="width: 100%;">
                                        Confirm Burn &amp; Claim
                                    </button>
                                </form>
                            </div>
                        </details>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <!-- On-Chain Token Audit Ledger -->
    <div class="ledger-card" style="margin-top: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
            <h3 style="font-size: 1.2rem; margin-bottom: 0;">Blockchain Token Transaction Ledger</h3>
            <span class="mono" style="font-size: 11px; color: var(--ink-faint);">SMART CONTRACT: 0x47B9...A921</span>
        </div>

        <?php if (empty($token_history)): ?>
        <p style="color: var(--ink-muted);">No token transactions recorded yet on this farm.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Type</th>
                        <th>Worker / Recipient</th>
                        <th>Reason / Transaction Narrative</th>
                        <th style="text-align: right;">Amount</th>
                        <th>Cryptographic Tx Hash (Ethereum format)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($token_history as $tx): 
                        $is_positive = ((int)$tx['amount'] > 0);
                    ?>
                    <tr>
                        <td class="mono"><?php echo format_date_mono($tx['created_at']); ?></td>
                        <td>
                            <?php if ($tx['token_type'] === 'reward'): ?>
                            <span class="stamp-badge stamp-ochre" style="font-size: 9.5px;">[ REWARD ]</span>
                            <?php elseif ($tx['token_type'] === 'carbon'): ?>
                            <span class="stamp-badge stamp-green" style="font-size: 9.5px;">[ CARBON ]</span>
                            <?php else: ?>
                            <span class="stamp-badge stamp-red" style="font-size: 9.5px;">[ REDEEM ]</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo sanitize($tx['worker_name'] ?: 'Farm Treasury'); ?></strong>
                        </td>
                        <td><?php echo sanitize($tx['reason']); ?></td>
                        <td style="text-align: right;" class="mono">
                            <strong style="color: <?php echo $is_positive ? 'var(--stamp-green)' : 'var(--stamp-red)'; ?>; font-size: 14px;">
                                <?php echo ($is_positive ? '+' : '') . (int)$tx['amount']; ?>
                            </strong>
                        </td>
                        <td>
                            <code class="mono" style="font-size: 11px; color: var(--stamp-navy); background: var(--paper-subtle); padding: 2px 6px; border: 1px solid var(--border-rule);">
                                <?php echo substr($tx['blockchain_tx_hash'], 0, 14) . '...' . substr($tx['blockchain_tx_hash'], -8); ?>
                            </code>
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
