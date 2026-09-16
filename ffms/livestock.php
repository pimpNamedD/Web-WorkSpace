<?php
/**
 * FFMS (Field Ledger) - Livestock Master Ledger
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

login_required();
$user = current_user();
$user_id = $user['id'];

// Fetch user's farms for filter dropdown
$stmt_f = $pdo->prepare('SELECT id, farm_name FROM farms WHERE user_id = ? ORDER BY farm_name ASC');
$stmt_f->execute([$user_id]);
$user_farms = $stmt_f->fetchAll();
$farm_ids = array_column($user_farms, 'id');

// Filters
$filter_farm = isset($_GET['farm_id']) && is_numeric($_GET['farm_id']) ? (int)$_GET['farm_id'] : 0;
$filter_status = isset($_GET['health_status']) ? trim($_GET['health_status']) : '';

$livestock = [];
$total_head = 0;
$healthy_head = 0;
$treatment_head = 0;

if (!empty($farm_ids)) {
    $where = ['f.user_id = ?'];
    $params = [$user_id];

    if ($filter_farm > 0) {
        $where[] = 'l.farm_id = ?';
        $params[] = $filter_farm;
    }
    if (!empty($filter_status) && in_array($filter_status, ['healthy', 'sick', 'under_treatment', 'deceased'])) {
        $where[] = 'l.health_status = ?';
        $params[] = $filter_status;
    }

    $sql = '
        SELECT l.*, f.farm_name 
        FROM livestock l
        JOIN farms f ON l.farm_id = f.id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY l.health_status = "sick" DESC, l.health_status = "under_treatment" DESC, l.id DESC
    ';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $livestock = $stmt->fetchAll();

    foreach ($livestock as $l) {
        $qty = (int)$l['quantity'];
        if ($l['health_status'] !== 'deceased') {
            $total_head += $qty;
            if ($l['health_status'] === 'healthy') {
                $healthy_head += $qty;
            } elseif (in_array($l['health_status'], ['sick', 'under_treatment'])) {
                $treatment_head += $qty;
            }
        }
    }
}

$page_title = 'Livestock Master Ledger';
include __DIR__ . '/includes/header.php';
?>

<div class="ledger-wrapper">

    <!-- Title Card -->
    <div class="ledger-card border-ochre">
        <div class="card-header-ruled">
            <div>
                <span class="folio-tag">LIVESTOCK FOLIO &bull; SECTION 03</span>
                <h1 style="margin-top: 6px;">Livestock Herd &amp; Flock Register</h1>
                <p style="color: var(--ink-muted); margin-bottom: 0; font-size: 14px;">
                    Animal inventory, individual/batch tags, veterinary health condition, and mortality logs.
                </p>
            </div>
            <div style="display: flex; gap: 8px; align-items: center;">
                <a href="livestock_add.php" class="ledger-btn ledger-btn-primary">+ Register Animal / Batch</a>
            </div>
        </div>

        <!-- Filter Bar -->
        <form method="GET" action="livestock.php" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; background: var(--paper-subtle); padding: 12px 14px; border-radius: 2px; border: 1px solid var(--border-rule); margin-top: 14px;">
            <div style="display: flex; flex-direction: column; gap: 4px;">
                <label style="font-family: var(--font-mono); font-size: 11px; font-weight: 700; text-transform: uppercase;">Filter by Farm</label>
                <select name="farm_id" class="form-control" style="padding: 6px 10px; font-size: 13px;">
                    <option value="0">&mdash; All Registered Farms &mdash;</option>
                    <?php foreach ($user_farms as $uf): ?>
                    <option value="<?php echo (int)$uf['id']; ?>" <?php echo ($filter_farm === (int)$uf['id']) ? 'selected' : ''; ?>>
                        <?php echo sanitize($uf['farm_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; flex-direction: column; gap: 4px;">
                <label style="font-family: var(--font-mono); font-size: 11px; font-weight: 700; text-transform: uppercase;">Health Status</label>
                <select name="health_status" class="form-control" style="padding: 6px 10px; font-size: 13px;">
                    <option value="">&mdash; All Conditions &mdash;</option>
                    <option value="healthy" <?php echo ($filter_status === 'healthy') ? 'selected' : ''; ?>>Healthy</option>
                    <option value="under_treatment" <?php echo ($filter_status === 'under_treatment') ? 'selected' : ''; ?>>Under Treatment</option>
                    <option value="sick" <?php echo ($filter_status === 'sick') ? 'selected' : ''; ?>>Sick / Quarantined</option>
                    <option value="deceased" <?php echo ($filter_status === 'deceased') ? 'selected' : ''; ?>>Deceased</option>
                </select>
            </div>

            <div>
                <button type="submit" class="ledger-btn ledger-btn-sm">Filter Folio</button>
                <a href="livestock.php" class="ledger-btn ledger-btn-sm" style="margin-left: 4px;">Reset</a>
            </div>

            <div style="margin-left: auto; font-family: var(--font-mono); font-size: 12px; color: var(--ink-muted); align-self: center;">
                Total Live Head: <strong><?php echo number_format($total_head); ?></strong> &bull; 
                In Treatment: <strong style="color: var(--stamp-amber);"><?php echo number_format($treatment_head); ?></strong>
            </div>
        </form>
    </div>

    <!-- Livestock Table -->
    <div class="ledger-card border-ochre">
        <?php if (empty($livestock)): ?>
            <div style="text-align: center; padding: 40px 20px;">
                <span class="stamp-badge stamp-neutral" style="font-size: 13px;">[ NO LIVESTOCK ENTRIES FOUND ]</span>
                <p style="color: var(--ink-muted); margin-top: 14px;">No livestock batches recorded under this filter.</p>
                <a href="livestock_add.php" class="ledger-btn ledger-btn-primary">+ Register Livestock Head</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="ledger-table">
                    <thead>
                        <tr>
                            <th>Animal Breed / Type</th>
                            <th>Tag / Batch ID</th>
                            <th>Farm Holding</th>
                            <th class="col-right">Quantity / Head</th>
                            <th>Acquired</th>
                            <th>Health Condition</th>
                            <th>Veterinary &amp; Feed Notes</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($livestock as $l): ?>
                        <tr>
                            <td>
                                <strong><?php echo sanitize($l['animal_type']); ?></strong>
                            </td>
                            <td class="col-mono"><?php echo sanitize($l['tag_id'] ?: '—'); ?></td>
                            <td class="col-mono" style="font-size: 12px;"><?php echo sanitize($l['farm_name']); ?></td>
                            <td class="col-mono col-right" style="font-weight: 700; font-size: 14px;">
                                <?php echo number_format((int)$l['quantity']); ?>
                            </td>
                            <td class="col-mono"><?php echo format_date_mono($l['acquisition_date']); ?></td>
                            <td><?php echo render_stamp_badge($l['health_status']); ?></td>
                            <td style="font-size: 13px; max-width: 260px;">
                                <?php echo sanitize($l['notes'] ?: '—'); ?>
                            </td>
                            <td>
                                <a href="livestock_edit.php?id=<?php echo (int)$l['id']; ?>" class="ledger-btn ledger-btn-sm">
                                    Update
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
