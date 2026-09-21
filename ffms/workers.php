<?php
/**
 * FFMS (Field Ledger) - Worker Roster & Recognition Ledger
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
$user_farms = $stmt_f->fetchAll();
$farm_ids = array_column($user_farms, 'id');

$filter_farm = isset($_GET['farm_id']) && is_numeric($_GET['farm_id']) ? (int)$_GET['farm_id'] : 0;
$error = '';

// Handle adding a new worker
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_worker' && verify_csrf()) {
    $farm_id = (int)($_POST['farm_id'] ?? 0);
    $name    = trim($_POST['name'] ?? '');
    $role    = trim($_POST['role'] ?? 'General Hand');
    $phone   = trim($_POST['phone'] ?? '');

    $stmt_chk = $pdo->prepare('SELECT id FROM farms WHERE id = ? AND user_id = ?');
    $stmt_chk->execute([$farm_id, $user_id]);
    if (!$stmt_chk->fetch()) {
        $error = 'Invalid farm holding selected.';
    } elseif (empty($name)) {
        $error = 'Worker name is required.';
    } else {
        $stmt_ins = $pdo->prepare('INSERT INTO workers (farm_id, name, role, phone, points_balance, is_active) VALUES (?, ?, ?, ?, 50, 1)');
        $stmt_ins->execute([$farm_id, $name, $role, $phone]);
        $worker_id = (int)$pdo->lastInsertId();

        // Welcome bonus points log
        $stmt_log = $pdo->prepare('INSERT INTO worker_points_log (worker_id, points_delta, reason, log_date) VALUES (?, 50, "Induction and onboarding welcome points token", CURDATE())');
        $stmt_log->execute([$worker_id]);

        set_flash('green', "Worker '{$name}' enrolled with 50 onboarding points!");
        header('Location: workers.php' . ($farm_id ? "?farm_id={$farm_id}" : ''));
        exit;
    }
}

$workers = [];
$total_workers = 0;
$total_points = 0;

if (!empty($farm_ids)) {
    $where = ['f.user_id = ?'];
    $params = [$user_id];

    if ($filter_farm > 0) {
        $where[] = 'w.farm_id = ?';
        $params[] = $filter_farm;
    }

    $sql = '
        SELECT w.*, f.farm_name,
               (SELECT COUNT(*) FROM worker_points_log l WHERE l.worker_id = w.id) as log_count
        FROM workers w
        JOIN farms f ON w.farm_id = f.id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY w.points_balance DESC, w.name ASC
    ';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $workers = $stmt->fetchAll();

    $total_workers = count($workers);
    foreach ($workers as $w) {
        $total_points += (int)$w['points_balance'];
    }
}

$page_title = 'Farm Crew & Worker Recognition';
include __DIR__ . '/includes/header.php';
?>

<div class="ledger-wrapper">

    <!-- Header Card -->
    <div class="ledger-card border-ochre">
        <div class="card-header-ruled">
            <div>
                <span class="folio-tag">LABOR &amp; PERSONNEL FOLIO &bull; SECTION 08</span>
                <h1 style="margin-top: 6px;">Worker Roster &amp; Recognition Points</h1>
                <p style="color: var(--ink-muted); margin-bottom: 0; font-size: 14px;">
                    Track farm crew personnel, operators, and herdsmen. Incentivize performance, water conservation, and equipment care with recognition points and tokens.
                </p>
            </div>
            <div style="display: flex; gap: 8px; align-items: center;">
                <a href="tokens.php" class="ledger-btn ledger-btn-sm">View Token Economy Wallet &rarr;</a>
            </div>
        </div>

        <!-- Metric Highlights -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin: 16px 0;">
            <div style="background: var(--paper-subtle); border-left: 3px solid var(--stamp-ochre); padding: 10px 14px; border-radius: 2px;">
                <div class="kpi-label">Active Farm Crew</div>
                <div class="kpi-value mono" style="font-size: 1.45rem; color: var(--stamp-ochre);">
                    <?php echo $total_workers; ?> Staff
                </div>
            </div>

            <div style="background: var(--paper-subtle); border-left: 3px solid var(--stamp-green); padding: 10px 14px; border-radius: 2px;">
                <div class="kpi-label">Total Points Circulating</div>
                <div class="kpi-value mono" style="font-size: 1.45rem; color: var(--stamp-green);">
                    <?php echo number_format($total_points); ?> PTS
                </div>
            </div>

            <div style="background: var(--paper-subtle); border-left: 3px solid var(--stamp-navy); padding: 10px 14px; border-radius: 2px;">
                <div class="kpi-label">Token Redemption Value</div>
                <div class="kpi-value mono" style="font-size: 1.45rem; color: var(--stamp-navy);">
                    <?php echo format_zmw($total_points * 2.50); ?>
                </div>
                <small style="color: var(--ink-muted); font-size: 11px;">1 PTS = K2.50 store purchasing power</small>
            </div>
        </div>

        <!-- Filter & Enroll Strip -->
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; background: var(--paper-card-alt); padding: 10px; border: 1px dashed var(--border-rule);">
            <form method="GET" action="workers.php" style="display: flex; align-items: center; gap: 8px;">
                <label style="font-size: 12px; font-weight: 600;">Farm Holding:</label>
                <select name="farm_id" class="form-control-sm" onchange="this.form.submit()">
                    <option value="0">All Farm Holdings</option>
                    <?php foreach ($user_farms as $uf): ?>
                    <option value="<?php echo (int)$uf['id']; ?>" <?php echo ($uf['id'] == $filter_farm) ? 'selected' : ''; ?>>
                        <?php echo sanitize($uf['farm_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <details style="position: relative;">
                <summary class="ledger-btn ledger-btn-primary ledger-btn-sm" style="cursor: pointer;">
                    + Enroll New Worker
                </summary>
                <div style="position: absolute; right: 0; top: 100%; z-index: 50; background: var(--paper-card); border: 2px solid var(--border-strong); box-shadow: var(--shadow-binder); padding: 16px; width: 320px; text-align: left; border-radius: 2px;">
                    <form method="POST" action="workers.php">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="add_worker">
                        <div style="font-weight: 600; margin-bottom: 10px;">Enroll New Crew Member</div>

                        <div style="margin-bottom: 8px;">
                            <label style="font-size: 11px;">Farm Holding:</label>
                            <select name="farm_id" class="form-control-sm" style="width: 100%;" required>
                                <?php foreach ($user_farms as $uf): ?>
                                <option value="<?php echo (int)$uf['id']; ?>"><?php echo sanitize($uf['farm_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="margin-bottom: 8px;">
                            <label style="font-size: 11px;">Full Name:</label>
                            <input type="text" name="name" class="form-control-sm" style="width: 100%;" required placeholder="e.g. Musonda Kapembwa">
                        </div>

                        <div style="margin-bottom: 8px;">
                            <label style="font-size: 11px;">Role / Designation:</label>
                            <input type="text" name="role" class="form-control-sm" style="width: 100%;" value="General Hand" placeholder="e.g. Tractor Driver, Herdsman">
                        </div>

                        <div style="margin-bottom: 12px;">
                            <label style="font-size: 11px;">Mobile Contact:</label>
                            <input type="tel" name="phone" class="form-control-sm" style="width: 100%;" placeholder="+260 97...">
                        </div>

                        <button type="submit" class="ledger-btn ledger-btn-primary ledger-btn-sm" style="width: 100%;">
                            Confirm Enrollment
                        </button>
                    </form>
                </div>
            </details>
        </div>
    </div>

    <!-- Workers Roster Table -->
    <div class="ledger-card" style="margin-top: 16px;">
        <h2 style="font-size: 1.25rem; margin-bottom: 12px;">Farm Crew Roster &amp; Recognition Standings</h2>

        <?php if (empty($workers)): ?>
        <div style="text-align: center; padding: 30px; background: var(--paper-subtle); border: 1px dashed var(--border-rule);">
            <p style="color: var(--ink-muted);">No workers currently registered for this farm holding.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Worker Name</th>
                        <th>Farm Holding</th>
                        <th>Role / Specialization</th>
                        <th>Contact Phone</th>
                        <th style="text-align: right;">Points Balance</th>
                        <th>Status</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($workers as $w): ?>
                    <tr>
                        <td>
                            <strong><?php echo sanitize($w['name']); ?></strong>
                        </td>
                        <td><?php echo sanitize($w['farm_name']); ?></td>
                        <td>
                            <span class="mono" style="font-size: 12px;"><?php echo sanitize($w['role']); ?></span>
                        </td>
                        <td class="mono" style="font-size: 12.5px;">
                            <?php echo sanitize($w['phone'] ?: '—'); ?>
                        </td>
                        <td style="text-align: right;" class="mono">
                            <strong style="color: var(--stamp-green); font-size: 16px;">
                                <?php echo number_format((int)$w['points_balance']); ?>
                            </strong>
                            <span style="font-size: 11px; color: var(--ink-muted);">PTS</span>
                        </td>
                        <td>
                            <?php if ($w['is_active']): ?>
                            <span class="stamp-badge stamp-green" style="font-size: 10px;">[ ACTIVE CREW ]</span>
                            <?php else: ?>
                            <span class="stamp-badge stamp-neutral" style="font-size: 10px;">[ INACTIVE ]</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                            <a href="worker_points.php?worker_id=<?php echo (int)$w['id']; ?>" class="ledger-btn ledger-btn-sm" style="font-size: 11px; padding: 2px 8px;">
                                [ Award Points &amp; Log ]
                            </a>
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
