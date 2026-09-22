<?php
/**
 * FFMS (Field Ledger) - Comprehensive Farm Folio View
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/weather_service.php';

login_required();
$user = current_user();
$user_id = $user['id'];

$farm_id = (int)($_GET['id'] ?? 0);
$farm = verify_farm_owner($pdo, $farm_id, $user_id);

// 1. Weather reading for this farm
$weather_data = get_farm_weather($pdo, $farm);

// 2. Fetch Crops on this farm
$stmt_crops = $pdo->prepare('SELECT * FROM crops WHERE farm_id = ? ORDER BY planting_date DESC, id DESC');
$stmt_crops->execute([$farm_id]);
$crops = $stmt_crops->fetchAll();

// 3. Fetch Livestock on this farm
$stmt_livestock = $pdo->prepare('SELECT * FROM livestock WHERE farm_id = ? ORDER BY id DESC');
$stmt_livestock->execute([$farm_id]);
$livestock = $stmt_livestock->fetchAll();

// 4. Fetch Activity Logs on this farm
$stmt_act = $pdo->prepare('SELECT * FROM activity_log WHERE farm_id = ? ORDER BY activity_date DESC, id DESC LIMIT 15');
$stmt_act->execute([$farm_id]);
$activities = $stmt_act->fetchAll();

// 5. Fetch Input Purchases on this farm
$stmt_inputs = $pdo->prepare('SELECT * FROM input_purchases WHERE farm_id = ? ORDER BY purchase_date DESC, id DESC LIMIT 15');
$stmt_inputs->execute([$farm_id]);
$inputs = $stmt_inputs->fetchAll();

// 6. Fetch Workers on this farm
$stmt_workers = $pdo->prepare('SELECT * FROM workers WHERE farm_id = ? ORDER BY points_balance DESC');
$stmt_workers->execute([$farm_id]);
$workers = $stmt_workers->fetchAll();

$page_title = $farm['farm_name'] . ' — Farm Folio';
include __DIR__ . '/includes/header.php';
?>

<div class="ledger-wrapper">

    <!-- Farm Master Folio Header -->
    <div class="ledger-card border-<?php echo ($farm['farm_type'] === 'crop') ? 'green' : (($farm['farm_type'] === 'livestock') ? 'ochre' : 'navy'); ?>">
        <div class="card-header-ruled">
            <div>
                <span class="folio-tag">FARM FOLIO № FL-FARM-<?php echo str_pad((string)$farm['id'], 3, '0', STR_PAD_LEFT); ?></span>
                <h1 style="margin-top: 6px;"><?php echo sanitize($farm['farm_name']); ?></h1>
                <div style="font-family: var(--font-mono); font-size: 13px; color: var(--ink-muted);">
                    <?php echo sanitize($farm['location']); ?> &bull; 
                    <strong><?php echo format_qty($farm['size_hectares']); ?> Hectares</strong> &bull;
                    Type: <strong><?php echo strtoupper($farm['farm_type']); ?></strong>
                </div>
            </div>
            <div style="text-align: right; display: flex; flex-direction: column; align-items: flex-end; gap: 8px;">
                <?php echo render_stamp_badge($farm['farm_type']); ?>
                <div style="display: flex; gap: 6px;">
                    <a href="farm_edit.php?id=<?php echo (int)$farm['id']; ?>" class="ledger-btn ledger-btn-sm">Edit Folio Details</a>
                    <a href="farms.php" class="ledger-btn ledger-btn-sm">&larr; Back to Farms</a>
                </div>
            </div>
        </div>

        <?php if (!empty($farm['notes'])): ?>
        <p style="font-size: 13.5px; color: var(--ink-muted); margin-bottom: 12px;">
            <strong>Folio Notes:</strong> <?php echo nl2br(sanitize($farm['notes'])); ?>
        </p>
        <?php endif; ?>

        <!-- Action Ribbon -->
        <div style="display: flex; gap: 8px; flex-wrap: wrap; border-top: 1px dashed var(--border-rule); padding-top: 12px;">
            <a href="crop_add.php?farm_id=<?php echo (int)$farm['id']; ?>" class="ledger-btn ledger-btn-sm ledger-btn-primary">+ Add Crop</a>
            <a href="livestock_add.php?farm_id=<?php echo (int)$farm['id']; ?>" class="ledger-btn ledger-btn-sm">+ Livestock</a>
            <a href="farm_fields.php?farm_id=<?php echo (int)$farm['id']; ?>" class="ledger-btn ledger-btn-sm">Field Parcels &amp; Map</a>
            <a href="carbon.php?farm_id=<?php echo (int)$farm['id']; ?>" class="ledger-btn ledger-btn-sm">Carbon Audit</a>
            <a href="tokens.php?farm_id=<?php echo (int)$farm['id']; ?>" class="ledger-btn ledger-btn-sm">Token Wallet</a>
            <a href="traceability.php?farm_id=<?php echo (int)$farm['id']; ?>" class="ledger-btn ledger-btn-sm">Traceability</a>
            <a href="inventory.php?farm_id=<?php echo (int)$farm['id']; ?>" class="ledger-btn ledger-btn-sm">Stores Inventory</a>
            <a href="finances.php?farm_id=<?php echo (int)$farm['id']; ?>" class="ledger-btn ledger-btn-sm">Financial Accounts</a>
            <a href="input_add.php?farm_id=<?php echo (int)$farm['id']; ?>" class="ledger-btn ledger-btn-sm">+ Record Input</a>
            <a href="workers.php?farm_id=<?php echo (int)$farm['id']; ?>" class="ledger-btn ledger-btn-sm">Crew &amp; Points</a>
        </div>
    </div>

    <!-- Weather Section for this Farm -->
    <div class="weather-widget">
        <div class="weather-header">
            <div>
                <span class="folio-tag">LOCAL WEATHER OBSERVATION</span>
                <h3 style="margin-top: 4px; font-size: 1.15rem;">
                    Station: <?php echo sanitize($weather_data['city']); ?>, ZM
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
                <a href="weather_ajax.php?farm_id=<?php echo (int)$farm['id']; ?>&redirect=farm_view.php?id=<?php echo (int)$farm['id']; ?>" class="ledger-btn ledger-btn-sm">
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
                    <span class="label">Humidity</span>
                    <span class="val"><?php echo (int)$weather_data['humidity']; ?>%</span>
                </div>
                <div class="weather-detail-item">
                    <span class="label">Wind Speed</span>
                    <span class="val"><?php echo number_format($weather_data['wind_speed'], 1); ?> m/s</span>
                </div>
                <div class="weather-detail-item">
                    <span class="label">Rain (Last Hour)</span>
                    <span class="val"><?php echo number_format($weather_data['rain_1h'], 1); ?> mm</span>
                </div>
                <div class="weather-detail-item">
                    <span class="label">Observation Age</span>
                    <span class="val"><?php echo time_ago($weather_data['fetched_at']); ?></span>
                </div>
            </div>
        </div>
        <?php if (!empty($weather_data['notice'])): ?>
        <div style="margin-top: 8px; font-family: var(--font-mono); font-size: 11px; color: var(--stamp-amber);">
            * <?php echo sanitize($weather_data['notice']); ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Section 1: Crops on this Farm -->
    <div class="ledger-card border-green">
        <div class="card-header-ruled">
            <div>
                <span class="folio-tag">CROPS FOLIO</span>
                <h3 style="margin-top: 4px;">Crop Rotations &amp; Plantings (<?php echo count($crops); ?>)</h3>
            </div>
            <a href="crop_add.php?farm_id=<?php echo (int)$farm['id']; ?>" class="ledger-btn ledger-btn-sm ledger-btn-primary">+ Add Crop</a>
        </div>

        <?php if (empty($crops)): ?>
            <p style="color: var(--ink-muted); font-style: italic;">No crops recorded for this farm yet. <a href="crop_add.php?farm_id=<?php echo (int)$farm['id']; ?>">Record planting &rarr;</a></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="ledger-table">
                    <thead>
                        <tr>
                            <th>Crop / Cultivar</th>
                            <th>Field / Plot</th>
                            <th>Area</th>
                            <th>Planted Date</th>
                            <th>Harvest Date</th>
                            <th>Expected Yield</th>
                            <th>Actual Yield</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($crops as $c): ?>
                        <tr>
                            <td><strong><?php echo sanitize($c['crop_name']); ?></strong></td>
                            <td class="col-mono"><?php echo sanitize($c['field_name'] ?: '—'); ?></td>
                            <td class="col-mono"><?php echo format_qty($c['area_hectares']); ?> ha</td>
                            <td class="col-mono"><?php echo format_date_mono($c['planting_date']); ?></td>
                            <td class="col-mono"><?php echo format_date_mono($c['actual_harvest_date'] ?: $c['expected_harvest_date']); ?></td>
                            <td class="col-mono"><?php echo format_qty($c['expected_yield_kg']); ?> kg</td>
                            <td class="col-mono">
                                <?php if ($c['status'] === 'harvested'): ?>
                                    <strong style="color: var(--stamp-green);"><?php echo format_qty($c['actual_yield_kg']); ?> kg</strong>
                                <?php else: ?>
                                    <span style="color: var(--ink-faint);">In Progress</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo render_stamp_badge($c['status']); ?></td>
                            <td>
                                <a href="crop_edit.php?id=<?php echo (int)$c['id']; ?>" class="ledger-btn ledger-btn-sm">Update</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Section 2: Livestock on this Farm -->
    <div class="ledger-card border-ochre">
        <div class="card-header-ruled">
            <div>
                <span class="folio-tag">LIVESTOCK FOLIO</span>
                <h3 style="margin-top: 4px;">Livestock Batches &amp; Registered Animals (<?php echo count($livestock); ?>)</h3>
            </div>
            <a href="livestock_add.php?farm_id=<?php echo (int)$farm['id']; ?>" class="ledger-btn ledger-btn-sm">+ Register Animal/Batch</a>
        </div>

        <?php if (empty($livestock)): ?>
            <p style="color: var(--ink-muted); font-style: italic;">No livestock herds or flocks recorded on this property.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="ledger-table">
                    <thead>
                        <tr>
                            <th>Animal Type / Breed</th>
                            <th>Tag / Batch ID</th>
                            <th>Head / Quantity</th>
                            <th>Acquisition Date</th>
                            <th>Health Condition</th>
                            <th>Notes</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($livestock as $l): ?>
                        <tr>
                            <td><strong><?php echo sanitize($l['animal_type']); ?></strong></td>
                            <td class="col-mono"><?php echo sanitize($l['tag_id'] ?: '—'); ?></td>
                            <td class="col-mono" style="font-weight: 700;"><?php echo number_format((int)$l['quantity']); ?></td>
                            <td class="col-mono"><?php echo format_date_mono($l['acquisition_date']); ?></td>
                            <td><?php echo render_stamp_badge($l['health_status']); ?></td>
                            <td style="font-size: 13px;"><?php echo sanitize($l['notes'] ?: '—'); ?></td>
                            <td>
                                <a href="livestock_edit.php?id=<?php echo (int)$l['id']; ?>" class="ledger-btn ledger-btn-sm">Update</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Section 3: Recent Activity Log & Input Purchases -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 20px;">
        
        <!-- Activity Log -->
        <div class="ledger-card border-navy">
            <div class="card-header-ruled">
                <div>
                    <span class="folio-tag">OPERATIONAL JOURNAL</span>
                    <h3 style="margin-top: 4px;">Farm Activities</h3>
                </div>
                <a href="activities.php?farm_id=<?php echo (int)$farm['id']; ?>" class="ledger-btn ledger-btn-sm">+ Log Activity</a>
            </div>

            <?php if (empty($activities)): ?>
                <p style="color: var(--ink-muted); font-style: italic;">No recent activities logged for this farm.</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <?php foreach ($activities as $act): ?>
                    <div style="background: var(--paper-card-alt); border-left: 3px solid var(--border-strong); padding: 8px 12px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px;">
                            <span class="stamp-badge stamp-neutral" style="transform:none; font-size:10px;">
                                <?php echo strtoupper(sanitize($act['activity_type'])); ?>
                            </span>
                            <span class="col-mono" style="font-size: 11px; color: var(--ink-muted);">
                                <?php echo format_date_mono($act['activity_date']); ?>
                            </span>
                        </div>
                        <div style="font-size: 13.5px;"><?php echo nl2br(sanitize($act['description'])); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Input Purchases -->
        <div class="ledger-card border-ochre">
            <div class="card-header-ruled">
                <div>
                    <span class="folio-tag">EXPENSES FOLIO</span>
                    <h3 style="margin-top: 4px;">Input Purchases &amp; Mobile Money</h3>
                </div>
                <a href="input_add.php?farm_id=<?php echo (int)$farm['id']; ?>" class="ledger-btn ledger-btn-sm ledger-btn-primary">+ Purchase Input</a>
            </div>

            <?php if (empty($inputs)): ?>
                <p style="color: var(--ink-muted); font-style: italic;">No input purchases recorded on this farm yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="ledger-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Qty</th>
                                <th>Cost (ZMW)</th>
                                <th>Method</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inputs as $inp): ?>
                            <tr>
                                <td>
                                    <strong><?php echo sanitize($inp['item_name']); ?></strong>
                                    <div style="font-size: 11px; color: var(--ink-faint);"><?php echo format_date_mono($inp['purchase_date']); ?></div>
                                </td>
                                <td class="col-mono"><?php echo format_qty($inp['quantity']); ?> <?php echo sanitize($inp['unit']); ?></td>
                                <td class="col-mono" style="font-weight: 700;"><?php echo format_zmw($inp['cost_zmw']); ?></td>
                                <td class="col-mono">
                                    <?php echo strtoupper(str_replace('_', ' ', $inp['payment_method'])); ?>
                                </td>
                                <td><?php echo render_stamp_badge($inp['payment_status']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
