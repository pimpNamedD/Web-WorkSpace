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

// Active selected farm for widgets (or first farm)
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

$fin_summary = ['income' => 0.0, 'expense' => 0.0, 'net' => 0.0];
$low_stock_items = [];
$primary_twin_crop = null;
$carbon_summary = null;
$token_balance = 0;

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

    // Harvested yield (kg)
    $stmt_hy = $pdo->prepare("SELECT COALESCE(SUM(actual_yield_kg), 0) FROM crops WHERE farm_id IN ($in_placeholders) AND status = 'harvested'");
    $stmt_hy->execute($farm_ids);
    $kpis['harvested_kg'] = (float)$stmt_hy->fetchColumn();

    // Total input purchases (ZMW)
    $stmt_inp = $pdo->prepare("SELECT COALESCE(SUM(cost_zmw), 0) FROM input_purchases WHERE farm_id IN ($in_placeholders) AND payment_status = 'completed'");
    $stmt_inp->execute($farm_ids);
    $kpis['total_inputs_zmw'] = (float)$stmt_inp->fetchColumn();

    // Financial accounts P&L
    $stmt_fin = $pdo->prepare("SELECT type, SUM(amount) as total FROM financial_transactions WHERE farm_id IN ($in_placeholders) GROUP BY type");
    $stmt_fin->execute($farm_ids);
    while ($row = $stmt_fin->fetch()) {
        if ($row['type'] === 'income') $fin_summary['income'] = (float)$row['total'];
        if ($row['type'] === 'expense') $fin_summary['expense'] = (float)$row['total'];
    }
    $fin_summary['net'] = $fin_summary['income'] - $fin_summary['expense'];

    // Low stock alerts
    $stmt_low = $pdo->prepare("SELECT name, quantity, unit, low_stock_threshold FROM inventory_items WHERE farm_id IN ($in_placeholders) AND quantity <= low_stock_threshold ORDER BY quantity ASC LIMIT 3");
    $stmt_low->execute($farm_ids);
    $low_stock_items = $stmt_low->fetchAll();

    // Primary AI Digital Twin crop
    $stmt_ptc = $pdo->prepare("SELECT c.*, f.farm_name FROM crops c JOIN farms f ON c.farm_id = f.id WHERE c.farm_id IN ($in_placeholders) AND c.status = 'growing' ORDER BY c.id ASC LIMIT 1");
    $stmt_ptc->execute($farm_ids);
    $primary_twin_crop = $stmt_ptc->fetch();
}

// 3. Carbon footprint and Token balance for active farm
if ($active_farm) {
    $stmt_carb = $pdo->prepare('SELECT * FROM carbon_footprints WHERE farm_id = ? ORDER BY calculated_at DESC LIMIT 1');
    $stmt_carb->execute([$active_farm_id]);
    $carbon_summary = $stmt_carb->fetch();

    $stmt_tok = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM token_transactions WHERE farm_id = ?');
    $stmt_tok->execute([$active_farm_id]);
    $token_balance = (int)$stmt_tok->fetchColumn();
}

// 4. Weather for the active farm
$weather_data = null;
if ($active_farm) {
    $weather_data = get_farm_weather($pdo, $active_farm);
}

// 5. Recent activity feed
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

// 6. Active crops sample table
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
                    Agricultural Ledger for <?php echo sanitize($user['location_district']); ?> District holdings. All accounts and environmental folios balanced.
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
        <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 14px;">
            <a href="input_add.php" class="ledger-btn ledger-btn-primary ledger-btn-sm">
                + Record Input (MoMo/Cash)
            </a>
            <a href="ai_twin.php" class="ledger-btn ledger-btn-sm">
                AI Crop Twin
            </a>
            <a href="carbon.php" class="ledger-btn ledger-btn-sm">
                Carbon Accounting
            </a>
            <a href="tokens.php" class="ledger-btn ledger-btn-sm">
                Token Wallet
            </a>
            <a href="traceability.php" class="ledger-btn ledger-btn-sm">
                Traceability
            </a>
            <a href="finances.php" class="ledger-btn ledger-btn-sm">
                Finances &amp; P&amp;L
            </a>
            <a href="inventory.php" class="ledger-btn ledger-btn-sm">
                Stores Inventory
            </a>
            <a href="crop_add.php" class="ledger-btn ledger-btn-sm">
                + Add Crop Block
            </a>
            <a href="livestock_add.php" class="ledger-btn ledger-btn-sm">
                + Register Livestock
            </a>
        </div>
    </div>

    <!-- Low Stock Alert Banner (if any) -->
    <?php if (!empty($low_stock_items)): ?>
    <div style="background: #fff8f8; border: 1px solid #e0b4b4; border-left: 5px solid var(--stamp-red); padding: 12px 16px; border-radius: 2px; margin-top: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <span class="stamp-badge stamp-red" style="font-size: 10px;">[ CRITICAL STOCK ]</span>
            <div style="font-size: 13.5px; color: var(--stamp-red);">
                <strong>Depleted stores detected:</strong> 
                <?php 
                    $alert_texts = [];
                    foreach ($low_stock_items as $lsi) {
                        $alert_texts[] = sanitize($lsi['name']) . " (" . format_qty($lsi['quantity']) . " " . sanitize($lsi['unit']) . " remaining)";
                    }
                    echo implode(' &bull; ', $alert_texts);
                ?>
            </div>
        </div>
        <div>
            <a href="inventory.php?low_stock=1" class="ledger-btn ledger-btn-sm" style="color: var(--stamp-red); border-color: var(--stamp-red);">
                Manage Stores &rarr;
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- KPI Metric Cards Grid -->
    <div class="kpi-grid" style="margin-top: 16px;">
        <div class="kpi-card top-green">
            <span class="kpi-label">Active Farm Holdings</span>
            <span class="kpi-value"><?php echo $kpis['farms_count']; ?></span>
            <span class="kpi-sub">Registered properties</span>
        </div>
        <div class="kpi-card top-green">
            <span class="kpi-label">Crops Growing</span>
            <span class="kpi-value"><?php echo $kpis['crops_growing']; ?></span>
            <span class="kpi-sub">Field blocks in season</span>
        </div>
        <div class="kpi-card top-amber">
            <span class="kpi-label">Livestock Head</span>
            <span class="kpi-value"><?php echo number_format($kpis['livestock_head']); ?></span>
            <span class="kpi-sub">Active cattle &amp; smallstock</span>
        </div>
        <div class="kpi-card top-navy">
            <span class="kpi-label">Net Operating Margin</span>
            <span class="kpi-value mono" style="font-size: 1.45rem; color: <?php echo ($fin_summary['net'] >= 0) ? 'var(--stamp-green)' : 'var(--stamp-red)'; ?>;">
                <?php echo ($fin_summary['net'] >= 0 ? '+' : '') . format_zmw($fin_summary['net']); ?>
            </span>
            <span class="kpi-sub">Revenue K<?php echo number_format($fin_summary['income'], 0); ?> / Costs K<?php echo number_format($fin_summary['expense'], 0); ?></span>
        </div>
        <div class="kpi-card top-ochre">
            <span class="kpi-label">Input Expenditure</span>
            <span class="kpi-value" style="font-size: 1.5rem;"><?php echo format_zmw($kpis['total_inputs_zmw']); ?></span>
            <span class="kpi-sub">Completed purchases</span>
        </div>
    </div>

    <!-- UNIQUE BLUEPRINT WIDGETS SECTION -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 18px; margin-top: 20px;">
        
        <!-- 1. Carbon Accounting Widget (Blueprint CarbonWidget) -->
        <div class="ledger-card border-green" style="display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                    <div>
                        <span class="folio-tag">ENVIRONMENTAL SINK</span>
                        <h3 style="font-size: 1.2rem; margin-top: 2px;">Carbon Footprint Widget</h3>
                    </div>
                    <span class="stamp-badge stamp-green" style="font-size: 10px;">[ VCS PROTOCOL ]</span>
                </div>
                
                <?php if ($carbon_summary): ?>
                <div style="margin: 12px 0;">
                    <div style="font-size: 12px; color: var(--ink-muted);">Net Farm Carbon Footprint:</div>
                    <div class="mono" style="font-size: 1.7rem; font-weight: 700; color: <?php echo ((float)$carbon_summary['net_footprint'] <= 0) ? 'var(--stamp-green)' : 'var(--stamp-amber)'; ?>;">
                        <?php echo number_format((float)$carbon_summary['net_footprint']); ?> <span style="font-size: 13px;">kg CO₂e</span>
                    </div>
                    <div style="font-size: 12.5px; color: var(--ink-primary); margin-top: 4px;">
                        Credits Earned: <strong class="mono" style="color: var(--stamp-green);"><?php echo (int)$carbon_summary['credits_earned']; ?> Credits</strong> 
                        (Valued at ~<?php echo format_zmw((int)$carbon_summary['credits_earned'] * 1300); ?>)
                    </div>
                </div>
                <?php else: ?>
                <p style="color: var(--ink-muted); font-size: 13px; margin: 12px 0;">
                    No certified carbon audit executed yet for <?php echo sanitize($active_farm['farm_name'] ?? 'your farm'); ?>.
                </p>
                <?php endif; ?>
            </div>

            <div style="border-top: 1px dashed var(--border-rule); padding-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                <span class="mono" style="font-size: 11px; color: var(--ink-faint);">Holding: <?php echo sanitize($active_farm['farm_name'] ?? ''); ?></span>
                <a href="carbon.php?farm_id=<?php echo $active_farm_id; ?>" class="ledger-btn ledger-btn-sm">
                    Recalculate &amp; Audit &rarr;
                </a>
            </div>
        </div>

        <!-- 2. Token Wallet Widget (Blueprint TokenWallet) -->
        <div class="ledger-card border-ochre" style="display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                    <div>
                        <span class="folio-tag">WEB3 TREASURY</span>
                        <h3 style="font-size: 1.2rem; margin-top: 2px;">Token Wallet</h3>
                    </div>
                    <span class="stamp-badge stamp-amber" style="font-size: 10px;">[ SMART WALLET ]</span>
                </div>

                <div style="margin: 12px 0;">
                    <div style="font-size: 12px; color: var(--ink-muted);">Circulating Token Balance:</div>
                    <div class="mono" style="font-size: 1.7rem; font-weight: 700; color: var(--stamp-ochre);">
                        <?php echo number_format($token_balance); ?> <span style="font-size: 13px;">AGRI-TOKENS</span>
                    </div>
                    <div style="font-size: 12.5px; color: var(--ink-primary); margin-top: 4px;">
                        Redeemable for certified seed pockets, PPE boots, fertilizer, and airtime.
                    </div>
                </div>
            </div>

            <div style="border-top: 1px dashed var(--border-rule); padding-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                <a href="tokens.php?farm_id=<?php echo $active_farm_id; ?>" class="ledger-btn ledger-btn-primary ledger-btn-sm">
                    Open Redemption Store &rarr;
                </a>
                <a href="workers.php" class="ledger-btn ledger-btn-sm">
                    Award Crew
                </a>
            </div>
        </div>

        <!-- 3. AI Crop Twin Simulator Widget (Blueprint AITwinSimulator) -->
        <div class="ledger-card border-navy" style="display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                    <div>
                        <span class="folio-tag">DIGITAL TWIN ENGINE</span>
                        <h3 style="font-size: 1.2rem; margin-top: 2px;">AI Crop Twin</h3>
                    </div>
                    <span class="stamp-badge stamp-navy" style="font-size: 10px;">[ SIMULATOR ACTIVE ]</span>
                </div>

                <?php if ($primary_twin_crop): ?>
                <div style="margin: 12px 0;">
                    <div style="font-weight: 700; font-size: 14px; color: var(--ink-primary);">
                        <?php echo sanitize($primary_twin_crop['crop_name']); ?>
                    </div>
                    <div style="font-size: 12.5px; color: var(--ink-muted);">
                        Field: <?php echo sanitize($primary_twin_crop['field_name'] ?: 'Main Block'); ?> (<?php echo format_qty($primary_twin_crop['area_hectares']); ?> ha)
                    </div>
                    <div style="margin-top: 6px;">
                        Predicted Harvest: <strong class="mono" style="color: var(--stamp-green); font-size: 15px;">
                            <?php echo number_format((float)($primary_twin_crop['yield_predicted'] ?: $primary_twin_crop['expected_yield_kg'])); ?> kg
                        </strong>
                    </div>
                </div>
                <?php else: ?>
                <p style="color: var(--ink-muted); font-size: 13px; margin: 12px 0;">
                    Plant a crop block to activate the real-time AI Crop Twin simulator.
                </p>
                <?php endif; ?>
            </div>

            <div style="border-top: 1px dashed var(--border-rule); padding-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                <span class="mono" style="font-size: 11px; color: var(--ink-faint);">Yield Forecasting</span>
                <a href="ai_twin.php<?php echo $primary_twin_crop ? '?crop_id=' . $primary_twin_crop['id'] : ''; ?>" class="ledger-btn ledger-btn-sm">
                    Launch Simulator Sliders &rarr;
                </a>
            </div>
        </div>

    </div>

    <!-- Weather Widget Section -->
    <?php if ($active_farm && $weather_data): ?>
    <div class="weather-widget" style="margin-top: 20px;">
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
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <?php if (!empty($weather_data['source']) && $weather_data['source'] === 'live_api'): ?>
                    <span class="stamp-badge stamp-green">[ LIVE SATELLITE ]</span>
                <?php elseif (!empty($weather_data['source']) && $weather_data['source'] === 'cache'): ?>
                    <span class="stamp-badge stamp-navy">[ 30-MIN CACHED ]</span>
                <?php elseif (!empty($weather_data['source']) && $weather_data['source'] === 'stale_cache'): ?>
                    <span class="stamp-badge stamp-amber">[ STALE OBSERVATION ]</span>
                <?php else: ?>
                    <span class="stamp-badge stamp-ochre">[ REGIONAL BASELINE ]</span>
                <?php endif; ?>
                
                <?php if (count($farms) > 1): ?>
                <form method="GET" action="dashboard.php" style="display: inline-block;">
                    <select name="farm_id" onchange="this.form.submit()" class="form-control-sm">
                        <?php foreach ($farms as $fm): ?>
                        <option value="<?php echo (int)$fm['id']; ?>" <?php echo ($fm['id'] === $active_farm['id']) ? 'selected' : ''; ?>>
                            <?php echo sanitize($fm['farm_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php endif; ?>

                <a href="weather_ajax.php?farm_id=<?php echo (int)$active_farm['id']; ?>&redirect=dashboard.php" class="ledger-btn ledger-btn-sm" title="Refresh reading from OpenWeatherMap">
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
        <div style="font-size: 11.5px; color: var(--stamp-amber); margin-top: 12px; font-family: var(--font-mono); border-top: 1px dashed var(--border-rule); padding-top: 8px;">
            &bull; <?php echo sanitize($weather_data['notice']); ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Two-Column Section: Active Crops and Recent Activity Log -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 24px; margin-top: 20px;">

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
