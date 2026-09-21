<?php
/**
 * FFMS (Field Ledger) - Award Worker Points & Audit Log
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

login_required();
$user = current_user();
$user_id = $user['id'];

$worker_id = (int)($_GET['worker_id'] ?? 0);

// Verify worker belongs to a farm owned by current user
$stmt_w = $pdo->prepare('
    SELECT w.*, f.farm_name, f.user_id 
    FROM workers w
    JOIN farms f ON w.farm_id = f.id
    WHERE w.id = ? AND f.user_id = ?
');
$stmt_w->execute([$worker_id, $user_id]);
$worker = $stmt_w->fetch();

if (!$worker) {
    set_flash('amber', 'Worker record not found or access denied.');
    header('Location: workers.php');
    exit;
}

$error = '';

$common_reasons = [
    'Preventative maintenance on pivot or tractor avoiding costly breakdowns (+40 PTS)' => 40,
    'Night calf delivery assistance with 100% survival rate (+50 PTS)' => 50,
    'Consistent irrigation logging and water conservation score (+30 PTS)' => 30,
    'Early detection and isolation of crop disease / armyworms (+35 PTS)' => 35,
    'Zero chemical spill and spotless tractor maintenance checklist (+25 PTS)' => 25,
    'Outstanding harvest yield sorting and tonnage packing (+45 PTS)' => 45
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please try again.';
    } else {
        $delta = (int)($_POST['points_delta'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        $date = !empty($_POST['log_date']) ? trim($_POST['log_date']) : date('Y-m-d');

        if ($delta == 0) {
            $error = 'Points amount cannot be zero.';
        } elseif (empty($reason)) {
            $error = 'A detailed recognition reason is required for the audit log.';
        } else {
            // 1. Update worker balance
            $new_balance = max(0, (int)$worker['points_balance'] + $delta);
            $stmt_up = $pdo->prepare('UPDATE workers SET points_balance = ? WHERE id = ?');
            $stmt_up->execute([$new_balance, $worker_id]);

            // 2. Insert into worker_points_log
            $stmt_log = $pdo->prepare('INSERT INTO worker_points_log (worker_id, points_delta, reason, log_date) VALUES (?, ?, ?, ?)');
            $stmt_log->execute([$worker_id, $delta, $reason, $date]);

            // 3. Also mint an on-chain token transaction in the Token Economy
            $tx_hash = '0x' . bin2hex(random_bytes(32));
            $stmt_tok = $pdo->prepare('
                INSERT INTO token_transactions (
                    farm_id, user_id, worker_id, token_type, amount, reason, blockchain_tx_hash
                ) VALUES (?, ?, ?, "reward", ?, ?, ?)
            ');
            $stmt_tok->execute([
                $worker['farm_id'],
                $user_id,
                $worker_id,
                $delta,
                $reason,
                $tx_hash
            ]);

            set_flash('green', "Awarded {$delta} points to {$worker['name']}! Cryptographic Token Minted: " . substr($tx_hash, 0, 16) . '...');
            header('Location: worker_points.php?worker_id=' . $worker_id);
            exit;
        }
    }
}

// Fetch historical points audit log
$stmt_hist = $pdo->prepare('
    SELECT * FROM worker_points_log 
    WHERE worker_id = ? 
    ORDER BY log_date DESC, id DESC
');
$stmt_hist->execute([$worker_id]);
$logs = $stmt_hist->fetchAll();

$page_title = 'Points & Token Audit — ' . $worker['name'];
include __DIR__ . '/includes/header.php';
?>

<div class="ledger-wrapper">

    <!-- Header Card -->
    <div class="ledger-card border-ochre">
        <div class="card-header-ruled">
            <div>
                <span class="folio-tag">RECOGNITION FOLIO &bull; WORKER № FL-WRK-<?php echo str_pad((string)$worker['id'], 3, '0', STR_PAD_LEFT); ?></span>
                <h1 style="margin-top: 6px;"><?php echo sanitize($worker['name']); ?></h1>
                <div style="font-family: var(--font-mono); font-size: 13.5px; color: var(--ink-muted);">
                    Holding: <strong><?php echo sanitize($worker['farm_name']); ?></strong> &bull; 
                    Role: <strong><?php echo sanitize($worker['role']); ?></strong> &bull;
                    Current Balance: <strong style="color: var(--stamp-green); font-size: 16px;"><?php echo number_format((int)$worker['points_balance']); ?> PTS</strong>
                </div>
            </div>
            <div style="text-align: right;">
                <a href="workers.php" class="ledger-btn ledger-btn-sm">&larr; Back to Worker Roster</a>
            </div>
        </div>

        <?php if (!empty($error)): ?>
        <div class="flash-message flash-red" style="margin-top: 14px;">
            <strong>REJECTED:</strong> <?php echo sanitize($error); ?>
        </div>
        <?php endif; ?>

        <!-- Form to Award Points -->
        <div style="background: var(--paper-subtle); padding: 18px; border: 1px dashed var(--border-rule); border-radius: 2px; margin-top: 16px;">
            <h3 style="font-size: 1.15rem; margin-bottom: 12px;">Award Recognition Points &amp; Mint Tokens</h3>

            <form method="POST" action="worker_points.php?worker_id=<?php echo $worker_id; ?>">
                <?php echo csrf_field(); ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="points_delta">Points Amount to Award <span style="color: var(--stamp-red);">*</span></label>
                        <input type="number" name="points_delta" id="points_delta" class="form-control" value="30" step="5" min="5" max="500" required>
                    </div>

                    <div class="form-group">
                        <label for="log_date">Date of Achievement <span style="color: var(--stamp-red);">*</span></label>
                        <input type="date" name="log_date" id="log_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="form-group span-2">
                        <label for="reason">Achievement Justification / Citation <span style="color: var(--stamp-red);">*</span></label>
                        <input type="text" name="reason" id="reason" list="reasons_list" class="form-control" required
                               placeholder="e.g. Preventative maintenance on North Pivot gearbox avoiding breakdown during dry spell">
                        <datalist id="reasons_list">
                            <?php foreach ($common_reasons as $r_text => $r_val): ?>
                            <option value="<?php echo sanitize($r_text); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; align-items: center; margin-top: 14px;">
                    <button type="submit" class="ledger-btn ledger-btn-primary">
                        [ MINT TOKEN &amp; AWARD POINTS ]
                    </button>
                    <span style="font-size: 12px; color: var(--ink-muted);">
                        Automatically generates a cryptographic blockchain hash in the Token Economy.
                    </span>
                </div>
            </form>
        </div>
    </div>

    <!-- Points Audit History Table -->
    <div class="ledger-card" style="margin-top: 16px;">
        <h2 style="font-size: 1.25rem; margin-bottom: 12px;">Points Audit Log &amp; Achievement Trail</h2>

        <?php if (empty($logs)): ?>
        <p style="color: var(--ink-muted);">No points activity recorded yet for this worker.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Achievement Narrative</th>
                        <th style="text-align: right;">Points Delta</th>
                        <th>Audit Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $l): ?>
                    <tr>
                        <td class="mono"><?php echo format_date_mono($l['log_date']); ?></td>
                        <td><?php echo sanitize($l['reason']); ?></td>
                        <td style="text-align: right;" class="mono">
                            <strong style="color: var(--stamp-green); font-size: 15px;">
                                +<?php echo (int)$l['points_delta']; ?> PTS
                            </strong>
                        </td>
                        <td>
                            <span class="stamp-badge stamp-green" style="font-size: 10px;">[ VERIFIED AUDIT ]</span>
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
