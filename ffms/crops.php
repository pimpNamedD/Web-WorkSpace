<?php
/**
 * FFMS (Field Ledger) - Crops Master Ledger
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
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';

$crops = [];
$total_area = 0.0;
$growing_count = 0;
$harvested_yield = 0.0;

if (!empty($farm_ids)) {
    $where = ['f.user_id = ?'];
    $params = [$user_id];

    if ($filter_farm > 0) {
        $where[] = 'c.farm_id = ?';
        $params[] = $filter_farm;
    }
    if (!empty($filter_status) && in_array($filter_status, ['planned', 'growing', 'harvested', 'failed'])) {
        $where[] = 'c.status = ?';
        $params[] = $filter_status;
    }

    $sql = '
        SELECT c.*, f.farm_name 
        FROM crops c
        JOIN farms f ON c.farm_id = f.id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY c.status = "growing" DESC, c.planting_date DESC, c.id DESC
    ';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $crops = $stmt->fetchAll();

    foreach ($crops as $c) {
        $total_area += (float)$c['area_hectares'];
        if ($c['status'] === 'growing') {
            $growing_count++;
        }
        if ($c['status'] === 'harvested') {
            $harvested_yield += (float)$c['actual_yield_kg'];
        }
    }
}

$page_title = 'Crops Master Ledger';
include __DIR__ . '/includes/header.php';
?>

<div class="ledger-wrapper">

    <!-- Title Card -->
    <div class="ledger-card border-green">
        <div class="card-header-ruled">
            <div>
                <span class="folio-tag">CROPS FOLIO &bull; SECTION 02</span>
                <h1 style="margin-top: 6px;">Crop Plantings &amp; Harvest Ledger</h1>
                <p style="color: var(--ink-muted); margin-bottom: 0; font-size: 14px;">
                    Field cultivation records, expected maturity dates, and harvest yields in kilograms.
                </p>
            </div>
            <div style="display: flex; gap: 8px; align-items: center;">
                <a href="crop_add.php" class="ledger-btn ledger-btn-primary">+ Record New Planting</a>
            </div>
        </div>

        <!-- Filter Bar -->
        <form method="GET" action="crops.php" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; background: var(--paper-subtle); padding: 12px 14px; border-radius: 2px; border: 1px solid var(--border-rule); margin-top: 14px;">
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
                <label style="font-family: var(--font-mono); font-size: 11px; font-weight: 700; text-transform: uppercase;">Crop Status</label>
                <select name="status" class="form-control" style="padding: 6px 10px; font-size: 13px;">
                    <option value="">&mdash; All Statuses &mdash;</option>
                    <option value="growing" <?php echo ($filter_status === 'growing') ? 'selected' : ''; ?>>Growing</option>
                    <option value="planned" <?php echo ($filter_status === 'planned') ? 'selected' : ''; ?>>Planned</option>
                    <option value="harvested" <?php echo ($filter_status === 'harvested') ? 'selected' : ''; ?>>Harvested</option>
                    <option value="failed" <?php echo ($filter_status === 'failed') ? 'selected' : ''; ?>>Failed</option>
                </select>
            </div>

            <div>
                <button type="submit" class="ledger-btn ledger-btn-sm">Filter Folio</button>
                <a href="crops.php" class="ledger-btn ledger-btn-sm" style="margin-left: 4px;">Reset</a>
            </div>

            <div style="margin-left: auto; font-family: var(--font-mono); font-size: 12px; color: var(--ink-muted); align-self: center;">
                Total Land: <strong><?php echo format_qty($total_area); ?> ha</strong> &bull; 
                Active Fields: <strong><?php echo $growing_count; ?></strong>
            </div>
        </form>
    </div>

    <!-- Crops Ledger Table -->
    <div class="ledger-card border-green">
        <?php if (empty($crops)): ?>
            <div style="text-align: center; padding: 40px 20px;">
                <span class="stamp-badge stamp-neutral" style="font-size: 13px;">[ NO ENTRIES MATCHING FILTER ]</span>
                <p style="color: var(--ink-muted); margin-top: 14px;">No crop records found. Record a planting to initiate your field logbook.</p>
                <a href="crop_add.php" class="ledger-btn ledger-btn-primary">+ Add New Crop Record</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="ledger-table">
                    <thead>
                        <tr>
                            <th>Crop &amp; Holding</th>
                            <th>Field Plot</th>
                            <th>Area</th>
                            <th>Planted</th>
                            <th>Target / Harvest</th>
                            <th class="col-right">Expected Yield</th>
                            <th class="col-right">Actual Yield</th>
                            <th>Status Stamp</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($crops as $c): ?>
                        <tr>
                            <td>
                                <strong><?php echo sanitize($c['crop_name']); ?></strong>
                                <div style="font-size: 11px; color: var(--ink-faint); font-family: var(--font-mono);">
                                    <?php echo sanitize($c['farm_name']); ?>
                                </div>
                            </td>
                            <td class="col-mono"><?php echo sanitize($c['field_name'] ?: 'General Field'); ?></td>
                            <td class="col-mono"><?php echo format_qty($c['area_hectares']); ?> ha</td>
                            <td class="col-mono"><?php echo format_date_mono($c['planting_date']); ?></td>
                            <td class="col-mono">
                                <?php if ($c['status'] === 'harvested'): ?>
                                    <span style="color: var(--stamp-green);"><?php echo format_date_mono($c['actual_harvest_date']); ?></span>
                                <?php else: ?>
                                    <?php echo format_date_mono($c['expected_harvest_date']); ?>
                                <?php endif; ?>
                            </td>
                            <td class="col-mono col-right"><?php echo format_qty($c['expected_yield_kg']); ?> kg</td>
                            <td class="col-mono col-right">
                                <?php if ($c['status'] === 'harvested'): ?>
                                    <strong style="color: var(--stamp-green);"><?php echo format_qty($c['actual_yield_kg']); ?> kg</strong>
                                <?php elseif ($c['status'] === 'failed'): ?>
                                    <span style="color: var(--stamp-red);">0 kg</span>
                                <?php else: ?>
                                    <span style="color: var(--ink-faint);">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo render_stamp_badge($c['status']); ?></td>
                            <td>
                                <a href="crop_edit.php?id=<?php echo (int)$c['id']; ?>" class="ledger-btn ledger-btn-sm">
                                    <?php echo ($c['status'] === 'growing') ? 'Harvest / Edit' : 'Edit'; ?>
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
