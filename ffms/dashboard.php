<?php
/**
 * FFMS (Field Ledger) - Farm Executive Dashboard
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/weather_service.php';

login_required();
$user = current_user();
$user_id = $user['id'];

// 1. Fetch all farms owned by this user
$stmt_farms = $pdo->prepare('SELECT * FROM farms WHERE user_id = ? ORDER BY id ASC');
$stmt_farms->execute([$user_id]);
$farms = $stmt_farms->fetchAll();
$farm_ids = array_column($farms, 'id');

// Active selected farm for weather widget (or first farm)
$active_farm_id = isset($_GET['farm_id']) ? (int)$_GET['farm_id'] : ($farms[0]['id'] ?? 0);
$active_farm = null;
foreach ($farms as $f) {
    if ($f['id'] === $active_farm_id) {
        $active_farm = $f;
        break;
    }
}
if (!$active_farm && !empty($farms)) {
    $active_farm = $farms[0];
    $active_farm_id = (int)$active_farm['id'];
}

// 2. Metrics calculation
$kpis = [
    'farms_count'      => count($farms),
    'crops_growing'    => 0,
    'livestock_head'   => 0,
    'harvested_kg'     => 0.0,
    'total_inputs_zmw' => 0.0
];

if (!empty($farm_ids)) {
    $in_placeholders = implode(',', array_fill(0, count($farm_ids), '?'));

    // Crops growing
    $stmt_cg = $pdo->prepare("SELECT COUNT(*) FROM crops WHERE farm_id IN ($in_placeholders) AND status = 'growing'");
    $stmt_cg->execute($farm_ids);
    $kpis['crops_growing'] = (int)$stmt_cg->fetchColumn();

    // Livestock head count
    $stmt_ls = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM livestock WHERE farm_id IN ($in_placeholders) AND health_status != 'deceased'");
    $stmt_ls->execute($farm_ids);
    $kpis['livestock_head'] = (int)$stmt_ls->fetchColumn();

    // Harvested yield (kg) this year / season
    $stmt_hy = $pdo->prepare("SELECT COALESCE(SUM(actual_yield_kg), 0) FROM crops WHERE farm_id IN ($in_placeholders) AND status = 'harvested'");
    $stmt_hy->execute($farm_ids);
    $kpis['harvested_kg'] = (float)$stmt_hy->fetchColumn();

    // Total input purchases (ZMW)
    $stmt_inp = $pdo->prepare("SELECT COALESCE(SUM(cost_zmw), 0) FROM input_purchases WHERE farm_id IN ($in_placeholders) AND payment_status = 'completed'");
    $stmt_inp->execute($farm_ids);
    $kpis['total_inputs_zmw'] = (float)$stmt_inp->fetchColumn();
}

// 3. Weather for the active farm
$weather_data = null;
if ($active_farm) {
    $weather_data = get_farm_weather($pdo, $active_farm);
}

// 4. Recent activity feed across all user's farms
$recent_activities = [];
if (!empty($farm_ids)) {
    $stmt_act = $pdo->prepare("
        SELECT a.*, f.farm_name 
        FROM activity_log a
        JOIN farms f ON a.farm_id = f.id
        WHERE a.farm_id IN ($in_placeholders)
        ORDER BY a.activity_date DESC, a.id DESC
        LIMIT 6
    ");
    $stmt_act->execute($farm_ids);
    $recent_activities = $stmt_act->fetchAll();
}

// 5. Active crops sample table
$active_crops = [];
if (!empty($farm_ids)) {
    $stmt_ac = $pdo->prepare("
        SELECT c.*, f.farm_name 
        FROM crops c
        JOIN farms f ON c.farm_id = f.id
        WHERE c.farm_id IN ($in_placeholders) AND c.status IN ('growing', 'planned')
        ORDER BY c.planting_date DESC
        LIMIT 5
    ");
    $stmt_ac->execute($farm_ids);
    $active_crops = $stmt_ac->fetchAll();
}

$page_title = 'Executive Farm Folio';
include __DIR__ . '/includes/header.php';
?>

<div class="ledger-wrapper">

    <!-- Top Executive Header Card -->
    <div class="ledger-card border-green">
        <div class="card-header-ruled">
            <div>
                <span class="folio-tag">FOLIO SUMMARY &bull; <?php echo strtoupper(date('F Y')); ?></span>
                <h1 style="margin-top: 6px;"><?php echo sanitize($user['full_name']); ?>'s Farm Records</h1>
                <p style="color: var(--ink-muted); margin-bottom: 0; font-size: 14px;">
                    Agricultural Ledger for <?php echo sanitize($user['location_district']); ?> District holdings. All folios verified and balanced.
                </p>
            </div>
            <div style="text-align: right; display: flex; flex-direction: column; align-items: flex-end; gap: 6px;">
                <span class="stamp-badge stamp-green">[ AUDIT BALANCED ]</span>
                <div style="font-family: var(--font-mono); font-size: 11px; color: var(--ink-faint);">
                    FOLIO REF: #FL-<?php echo str_pad((string)$user_id, 4, '0', STR_PAD_LEFT); ?>
                </div>
            </div>
        </div>

        <!-- Quick Ledger Action Buttons -->
        <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 14px;">
            <a href="input_add.php" class="ledger-btn ledger-btn-primary ledger-btn-sm">
                + Record Input (MoMo/Cash)
            </a>
            <a href="activities.php" class="ledger-btn ledger-btn-sm">
                + Log Farm Activity
            </a>
            <a href="crop_add.php" class="ledger-btn ledger-btn-sm">
                + Add Crop Block
            </a>
            <a href="livestock_add.php" class="ledger-btn ledger-btn-sm">
                + Register Livestock
            </a>
            <a href="farms.php" class="ledger-btn ledger-btn-sm">
                Manage Farm Holdings
            </a>
        </div>
    </div>

    <!-- KPI Metric Cards Grid -->
    <div class="kpi-grid">
        <div class="kpi-card top-green">
            <span class="kpi-label">Active Farm Holdings</span>
            <span class="kpi-value"><?php echo $kpis['farms_count']; ?></span>
            <span class="kpi-sub">Registered properties</span>
        </div>
        <div class="kpi-card top-green">
            <span class="kpi-label">Crops Currently Growing</span>
            <span class="kpi-value"><?php echo $kpis['crops_growing']; ?></span>
            <span class="kpi-sub">Fields in season</span>
        </div>
        <div class="kpi-card top-amber">
            <span class="kpi-label">Livestock Head</span>
            <span class="kpi-value"><?php echo number_format($kpis['livestock_head']); ?></span>
            <span class="kpi-sub">Active animals &amp; poultry</span>
        </div>
        <div class="kpi-card top-navy">
            <span class="kpi-label">Harvested This Season</span>
            <span class="kpi-value"><?php echo format_qty($kpis['harvested_kg']); ?></span>
            <span class="kpi-sub">Kilograms grain/produce</span>
        </div>
        <div class="kpi-card top-ochre">
            <span class="kpi-label">Input Expenditure</span>
            <span class="kpi-value" style="font-size: 1.6rem;"><?php echo format_zmw($kpis['total_inputs_zmw']); ?></span>
            <span class="kpi-sub">Completed purchases</span>
        </div>
    </div>

    <!-- Weather Widget Section (Module 3) -->
    <?php if ($active_farm && $weather_data): ?>
    <div class="weather-widget">
        <div class="weather-header">
            <div>
                <span class="folio-tag">METEOROLOGICAL OBSERVATION &bull; OPENWEATHERMAP FREE TIER</span>
                <h3 style="margin-top: 4px; font-size: 1.3rem;">
                    Weather for <?php echo sanitize($active_farm['farm_name']); ?>
                    <span style="font-size: 0.85rem; font-family: var(--font-mono); font-weight: 400; color: var(--ink-muted);">
                        (Station: <?php echo sanitize($weather_data['city']); ?>, ZM)
                    </span>
                </h3>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <?php if (!empty($weather_data['is_stale'])): ?>
                    <span class="stamp-badge stamp-amber">[ STALE OBSERVATION ]</span>
                <?php else: ?>
                    <span class="stamp-badge stamp-navy">[ 30-MIN CACHED ]</span>
                <?php endif; ?>
                
                <!-- Farm selector dropdown for weather -->
                <?php if (count($farms) > 1): ?>
                <form method="GET" action="dashboard.php" style="display: inline-block;">
                    <select name="farm_id" onchange="this.form.submit()" class="form-control" style="padding: 4px 8px; font-size: 12px; font-family: var(--font-mono);">
                        <?php foreach ($farms as $fm): ?>
                        <option value="<?php echo (int)$fm['id']; ?>" <?php echo ($fm['id'] === $active_farm['id']) ? 'selected' : ''; ?>>
                            <?php echo sanitize($fm['farm_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php endif; ?>

                <a href="weather_ajax.php?farm_id=<?php echo (int)$active_farm['id']; ?>" class="ledger-btn ledger-btn-sm" title="Refresh reading from OpenWeatherMap">
                    Refresh
                </a>
            </div>
        </div>

        <div class="weather-grid">
            <div class="weather-temp-block">
                <span class="weather-temp"><?php echo number_format($weather_data['temp_c'], 1); ?>&deg;</span>
                <span class="weather-unit">C</span>
                <div style="margin-left: 10px;">
                    <div style="font-weight: 600; font-size: 14px;"><?php echo sanitize($weather_data['description']); ?></div>
                    <div style="font-family: var(--font-mono); font-size: 11px; color: var(--ink-muted);">
                        Feels like: <?php echo number_format($weather_data['feels_like_c'], 1); ?>&deg;C
                    </div>
                </div>
            </div>

            <div class="weather-details">
                <div class="weather-detail-item">
                    <span class="label">Relative Humidity</span>
                    <span class="val"><?php echo (int)$weather_data['humidity']; ?>%</span>
                </div>
                <div class="weather-detail-item">
                    <span class="label">Wind Velocity</span>
                    <span class="val"><?php echo number_format($weather_data['wind_speed'], 1); ?> m/s</span>
                </div>
                <div class="weather-detail-item">
                    <span class="label">Precipitation (1h)</span>
                    <span class="val"><?php echo number_format($weather_data['rain_1h'], 1); ?> mm</span>
                </div>
                <div class="weather-detail-item">
                    <span class="label">Recorded</span>
                    <span class="val"><?php echo time_ago($weather_data['fetched_at']); ?></span>
                </div>
            </div>
        </div>

        <?php if (!empty($weather_data['notice'])): ?>
        <div style="margin-top: 10px; font-family: var(--font-mono); font-size: 11px; color: var(--stamp-amber); background: var(--stamp-amber-bg); padding: 5px 10px; border-radius: 2px;">
            <strong>Advisory:</strong> <?php echo sanitize($weather_data['notice']); ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Two-Column Section: Active Crops and Recent Activity Log -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 24px;">

        <!-- Active Crops Table -->
        <div class="ledger-card border-green">
            <div class="card-header-ruled">
                <div>
                    <span class="folio-tag">CROPS IN GROUND</span>
                    <h3 style="margin-top: 4px;">Active Plantings</h3>
                </div>
                <a href="crops.php" class="ledger-btn ledger-btn-sm">View All Crops &rarr;</a>
            </div>

            <?php if (empty($active_crops)): ?>
                <p style="color: var(--ink-muted); font-style: italic;">No active crops currently recorded. <a href="crop_add.php">Add a planting &rarr;</a></p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="ledger-table">
                        <thead>
                            <tr>
                                <th>Crop &amp; Variety</th>
                                <th>Field Plot</th>
                                <th>Area</th>
                                <th>Planted</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($active_crops as $crop): ?>
                            <tr>
                                <td>
                                    <strong><?php echo sanitize($crop['crop_name']); ?></strong>
                                    <div style="font-size: 11px; color: var(--ink-faint);"><?php echo sanitize($crop['farm_name']); ?></div>
                                </td>
                                <td class="col-mono"><?php echo sanitize($crop['field_name'] ?: 'Main Plot'); ?></td>
                                <td class="col-mono"><?php echo format_qty($crop['area_hectares']); ?> ha</td>
                                <td class="col-mono"><?php echo format_date_mono($crop['planting_date']); ?></td>
                                <td><?php echo render_stamp_badge($crop['status']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Activity Feed -->
        <div class="ledger-card border-navy">
            <div class="card-header-ruled">
                <div>
                    <span class="folio-tag">CHRONOLOGICAL JOURNAL</span>
                    <h3 style="margin-top: 4px;">Recent Farm Activity</h3>
                </div>
                <a href="activities.php" class="ledger-btn ledger-btn-sm">+ Log New Activity</a>
            </div>

            <?php if (empty($recent_activities)): ?>
                <p style="color: var(--ink-muted); font-style: italic;">No activities recorded yet. <a href="activities.php">Log an operation &rarr;</a></p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach ($recent_activities as $act): ?>
                    <div style="border-left: 3px solid var(--border-strong); padding: 8px 12px; background: var(--paper-card-alt); border-radius: 2px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                            <span class="stamp-badge stamp-neutral" style="transform:none; font-size:10px;">
                                <?php echo strtoupper(sanitize($act['activity_type'])); ?>
                            </span>
                            <span class="col-mono" style="font-size: 11px; color: var(--ink-muted);">
                                <?php echo format_date_mono($act['activity_date']); ?> &bull; <?php echo sanitize($act['farm_name']); ?>
                            </span>
                        </div>
                        <div style="font-size: 13.5px; color: var(--ink-primary);">
                            <?php echo nl2br(sanitize($act['description'])); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
