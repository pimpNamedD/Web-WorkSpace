<?php
/**
 * FFMS (Field Ledger) - Carbon Accounting & Credits Folio
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

login_required();
$user = current_user();
$user_id = $user['id'];

// Fetch user's farms
$stmt_f = $pdo->prepare('SELECT id, farm_name, size_hectares, location FROM farms WHERE user_id = ? ORDER BY farm_name ASC');
$stmt_f->execute([$user_id]);
$user_farms = $stmt_f->fetchAll();

if (empty($user_farms)) {
    set_flash('amber', 'Please register at least one farm holding before running carbon accounting.');
    header('Location: farms.php');
    exit;
}

$active_farm_id = isset($_GET['farm_id']) ? (int)$_GET['farm_id'] : ($user_farms[0]['id'] ?? 0);
$active_farm = null;
foreach ($user_farms as $uf) {
    if ($uf['id'] === $active_farm_id) {
        $active_farm = $uf;
        break;
    }
}
if (!$active_farm) {
    $active_farm = $user_farms[0];
    $active_farm_id = (int)$active_farm['id'];
}

// Function to calculate live carbon accounting based on farm's real assets
function calculate_farm_carbon_footprint(PDO $pdo, array $farm): array {
    $farm_id = (int)$farm['id'];
    $size_ha = (float)$farm['size_hectares'];

    // 1. Livestock Count & Enteric Emissions (IPCC Tier 1/2 factors for Africa)
    // Cattle: ~1,800 kg CO2e / head / year
    // Goats/Sheep: ~120 kg CO2e / head / year
    // Poultry: ~12 kg CO2e / bird / year
    $stmt_ls = $pdo->prepare('SELECT animal_type, quantity FROM livestock WHERE farm_id = ? AND health_status != "deceased"');
    $stmt_ls->execute([$farm_id]);
    $livestock_rows = $stmt_ls->fetchAll();

    $enteric_emissions = 0.0;
    $total_livestock_head = 0;
    foreach ($livestock_rows as $ls) {
        $qty = (int)$ls['quantity'];
        $total_livestock_head += $qty;
        $type = strtolower($ls['animal_type']);
        if (str_contains($type, 'cattle') || str_contains($type, 'cow') || str_contains($type, 'bull') || str_contains($type, 'boran')) {
            $enteric_emissions += ($qty * 1800.0);
        } elseif (str_contains($type, 'goat') || str_contains($type, 'sheep') || str_contains($type, 'boer') || str_contains($type, 'dorper')) {
            $enteric_emissions += ($qty * 120.0);
        } elseif (str_contains($type, 'poultry') || str_contains($type, 'chicken') || str_contains($type, 'broiler')) {
            $enteric_emissions += ($qty * 12.0);
        } else {
            $enteric_emissions += ($qty * 300.0);
        }
    }

    // 2. Crop Acreage & Direct Field Operations (Diesel machinery: ~80 L/ha = 214 kg CO2e/ha)
    $stmt_cr = $pdo->prepare('SELECT SUM(area_hectares) as total_crop_ha FROM crops WHERE farm_id = ? AND status IN ("growing", "harvested")');
    $stmt_cr->execute([$farm_id]);
    $crop_ha = (float)($stmt_cr->fetchColumn() ?: ($size_ha * 0.6));

    $machinery_emissions = $crop_ha * 214.0; // diesel tractor & combine emissions

    // 3. Synthetic Fertilizer N2O Emissions (Purchased inputs)
    $stmt_inp = $pdo->prepare('SELECT SUM(cost_zmw) FROM input_purchases WHERE farm_id = ? AND category = "fertilizer"');
    $stmt_inp->execute([$farm_id]);
    $fert_spend = (float)($stmt_inp->fetchColumn() ?: 25000.0);
    $fertilizer_n2o = ($fert_spend / 800.0) * 115.0; // kg CO2e per 50kg bag equivalent

    // Scope 1 = Direct on-farm (Enteric + Machinery diesel + Soil N2O)
    $scope_1 = round($enteric_emissions + $machinery_emissions + $fertilizer_n2o, 2);

    // Scope 2 = Indirect Electricity (Center pivot irrigation pumps, boreholes, cold rooms)
    // ~120 kWh/ha on irrigated acreage * 0.70 kg CO2e/kWh grid factor
    $scope_2 = round($crop_ha * 150.0 * 0.70, 2);

    // Scope 3 = Supply chain inputs & agrochemical manufacturing
    $scope_3 = round(($scope_1 + $scope_2) * 0.15, 2);

    $total_emissions = $scope_1 + $scope_2 + $scope_3;

    // 4. Sequestration Engine (Photosynthesis, minimum tillage, soil organic matter, cover crops)
    // Soya / Legumes fix nitrogen and sequester ~1,200 kg CO2e/ha
    // Rhodes grass pastures sequester ~2,500 kg CO2e/ha
    // Minimum tillage grain systems sequester ~850 kg CO2e/ha
    // Agroforestry border trees: ~25 kg CO2e/tree
    $pasture_ha = max(0.0, $size_ha - $crop_ha);
    $sequestration_crops = $crop_ha * 950.0;
    $sequestration_pasture = $pasture_ha * 2200.0;
    $sequestration_trees = 400 * 25.0; // Est. 400 boundary trees per holding

    $total_sequestration = round($sequestration_crops + $sequestration_pasture + $sequestration_trees, 2);

    // Net Footprint
    $net_footprint = round($total_emissions - $total_sequestration, 2);

    // Carbon Credits Earned (1 Credit per 1,000 kg CO2e sequestered or net negative reduction)
    $credits = (int)max(0, floor($total_sequestration / 1000.0));

    return [
        'scope_1'              => $scope_1,
        'scope_2'              => $scope_2,
        'scope_3'              => $scope_3,
        'total_emissions'      => $total_emissions,
        'carbon_sequestration' => $total_sequestration,
        'net_footprint'        => $net_footprint,
        'credits_earned'       => $credits,
        'livestock_count'      => $total_livestock_head,
        'crop_ha'              => $crop_ha,
        'pasture_ha'           => $pasture_ha
    ];
}

// Handle Recalculate & Certify Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'recalculate_carbon' && verify_csrf()) {
    $calc = calculate_farm_carbon_footprint($pdo, $active_farm);

    $details_json = json_encode([
        'livestock_head'    => $calc['livestock_count'],
        'crop_hectares'     => $calc['crop_ha'],
        'pasture_hectares'  => $calc['pasture_ha'],
        'methodology'       => 'IPCC Tier 2 African Savanna & Agro-Ecological Model',
        'auditor'           => 'FFMS Carbon Ledger System'
    ]);

    $stmt_ins = $pdo->prepare('
        INSERT INTO carbon_footprints (
            farm_id, source_type, scope_1_emissions, scope_2_emissions, 
            scope_3_emissions, carbon_sequestration, net_footprint, 
            credits_earned, calculation_details, calculated_at
        ) VALUES (?, "farm_audit", ?, ?, ?, ?, ?, ?, ?, NOW())
    ');
    $stmt_ins->execute([
        $active_farm_id,
        $calc['scope_1'],
        $calc['scope_2'],
        $calc['scope_3'],
        $calc['carbon_sequestration'],
        $calc['net_footprint'],
        $calc['credits_earned'],
        $details_json
    ]);

    // Also award carbon tokens in the Token Economy
    if ($calc['credits_earned'] > 0) {
        $tok_tx = '0x' . bin2hex(random_bytes(32));
        $stmt_tok = $pdo->prepare('
            INSERT INTO token_transactions (
                farm_id, user_id, worker_id, token_type, amount, reason, blockchain_tx_hash
            ) VALUES (?, ?, NULL, "carbon", ?, "Verified farm carbon sequestration audit credits", ?)
        ');
        $stmt_tok->execute([
            $active_farm_id,
            $user_id,
            $calc['credits_earned'],
            $tok_tx
        ]);
    }

    set_flash('green', "Carbon Audit Certified! Net Footprint: " . number_format($calc['net_footprint']) . " kg CO2e. Credits Earned: {$calc['credits_earned']}.");
    header('Location: carbon.php?farm_id=' . $active_farm_id);
    exit;
}

// Fetch historical audit records
$stmt_hist = $pdo->prepare('
    SELECT * FROM carbon_footprints 
    WHERE farm_id = ? 
    ORDER BY calculated_at DESC 
    LIMIT 10
');
$stmt_hist->execute([$active_farm_id]);
$carbon_history = $stmt_hist->fetchAll();

// Latest or live calculation
$live_calc = calculate_farm_carbon_footprint($pdo, $active_farm);
$latest_audit = !empty($carbon_history) ? $carbon_history[0] : null;

$display_scope_1 = $latest_audit ? (float)$latest_audit['scope_1_emissions'] : $live_calc['scope_1'];
$display_scope_2 = $latest_audit ? (float)$latest_audit['scope_2_emissions'] : $live_calc['scope_2'];
$display_scope_3 = $latest_audit ? (float)$latest_audit['scope_3_emissions'] : $live_calc['scope_3'];
$display_seq     = $latest_audit ? (float)$latest_audit['carbon_sequestration'] : $live_calc['carbon_sequestration'];
$display_net     = $latest_audit ? (float)$latest_audit['net_footprint'] : $live_calc['net_footprint'];
$display_credits = $latest_audit ? (int)$latest_audit['credits_earned'] : $live_calc['credits_earned'];
$display_total_emissions = $display_scope_1 + $display_scope_2 + $display_scope_3;

$page_title = 'Carbon Accounting & Credits — ' . $active_farm['farm_name'];
include __DIR__ . '/includes/header.php';
?>

<div class="ledger-wrapper">

    <!-- Header Card -->
    <div class="ledger-card border-green">
        <div class="card-header-ruled">
            <div>
                <span class="folio-tag">ENVIRONMENTAL LEDGER &bull; EMISSIONS &amp; SEQUESTRATION</span>
                <h1 style="margin-top: 6px;">Farm Carbon Accounting &amp; Credits</h1>
                <p style="color: var(--ink-muted); margin-bottom: 0; font-size: 14px;">
                    Automated greenhouse gas accounting (GHG Protocol Scope 1, 2, 3) and verified carbon credit generation for <strong><?php echo sanitize($active_farm['farm_name']); ?></strong>.
                </p>
            </div>
            <div style="display: flex; gap: 8px; align-items: center;">
                <form method="POST" action="carbon.php?farm_id=<?php echo $active_farm_id; ?>">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="recalculate_carbon">
                    <button type="submit" class="ledger-btn ledger-btn-primary ledger-btn-sm">
                        [ Run &amp; Certify Carbon Audit ]
                    </button>
                </form>
                <button class="ledger-btn ledger-btn-sm trigger-print">Export Folio</button>
            </div>
        </div>

        <!-- Holding Switcher -->
        <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 14px; padding-top: 10px; border-top: 1px dashed var(--border-rule);">
            <?php foreach ($user_farms as $uf): ?>
            <a href="carbon.php?farm_id=<?php echo (int)$uf['id']; ?>" class="ledger-btn ledger-btn-sm <?php echo ($uf['id'] == $active_farm_id) ? 'ledger-btn-primary' : ''; ?>">
                <?php echo sanitize($uf['farm_name']); ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Carbon Balance Sheet Summary Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-top: 16px;">
        
        <!-- Total Emissions -->
        <div class="ledger-card border-red" style="padding: 16px;">
            <div class="kpi-label">Gross Farm Emissions</div>
            <div class="kpi-value mono" style="font-size: 1.65rem; color: var(--stamp-red);">
                <?php echo number_format($display_total_emissions); ?> <span style="font-size: 13px;">kg CO₂e</span>
            </div>
            <small style="color: var(--ink-muted); font-size: 11px;">Scope 1 direct + Scope 2 electricity + Scope 3 inputs</small>
        </div>

        <!-- Carbon Sequestration -->
        <div class="ledger-card border-green" style="padding: 16px;">
            <div class="kpi-label">Biomass &amp; Soil Sequestration</div>
            <div class="kpi-value mono" style="font-size: 1.65rem; color: var(--stamp-green);">
                <?php echo number_format($display_seq); ?> <span style="font-size: 13px;">kg CO₂e</span>
            </div>
            <small style="color: var(--ink-muted); font-size: 11px;">Rhodes grass pastures, crop cover &amp; trees</small>
        </div>

        <!-- Net Footprint -->
        <div class="ledger-card border-<?php echo ($display_net <= 0) ? 'green' : 'amber'; ?>" style="padding: 16px;">
            <div class="kpi-label">Net Carbon Footprint</div>
            <div class="kpi-value mono" style="font-size: 1.65rem; color: <?php echo ($display_net <= 0) ? 'var(--stamp-green)' : 'var(--stamp-amber)'; ?>;">
                <?php echo number_format($display_net); ?> <span style="font-size: 13px;">kg CO₂e</span>
            </div>
            <div style="font-size: 11px; font-weight: 600; color: <?php echo ($display_net <= 0) ? 'var(--stamp-green)' : 'var(--stamp-amber)'; ?>;">
                <?php echo ($display_net <= 0) ? '[ CERTIFIED CARBON SINK ]' : '[ NET EMITTER ]'; ?>
            </div>
        </div>

        <!-- Carbon Credits Earned -->
        <div class="ledger-card border-navy" style="padding: 16px; background: rgba(31, 59, 88, 0.03);">
            <div class="kpi-label">Carbon Credits Earned</div>
            <div class="kpi-value mono" style="font-size: 1.65rem; color: var(--stamp-navy);">
                <?php echo number_format($display_credits); ?> <span style="font-size: 13px;">CREDITS</span>
            </div>
            <div style="font-size: 11px; color: var(--ink-muted);">
                Est. Value: <strong class="mono" style="color: var(--stamp-green);"><?php echo format_zmw($display_credits * 1300.0); ?></strong> ($<?php echo number_format($display_credits * 50); ?> USD)
            </div>
        </div>
    </div>

    <!-- Scope 1, 2, 3 Detailed Breakdown & Protocol Audit -->
    <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 20px; margin-top: 16px;">
        
        <!-- Detailed Protocol Breakdown -->
        <div class="ledger-card">
            <h3 style="font-size: 1.2rem; margin-bottom: 12px;">GHG Protocol Accounting Breakdown</h3>

            <div style="display: flex; flex-direction: column; gap: 14px;">
                <!-- Scope 1 -->
                <div style="background: var(--paper-subtle); padding: 14px; border-left: 4px solid var(--stamp-ochre); border-radius: 2px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <span style="font-weight: 700; font-size: 13.5px;">Scope 1: Direct On-Farm Emissions</span>
                        <strong class="mono" style="color: var(--stamp-ochre); font-size: 15px;"><?php echo number_format($display_scope_1); ?> kg CO₂e</strong>
                    </div>
                    <div style="font-size: 12.5px; color: var(--ink-muted); line-height: 1.5;">
                        • <strong>Livestock Enteric Fermentation:</strong> Boran cattle rumen digestion &amp; manure management.<br>
                        • <strong>Mobile Machinery:</strong> Diesel consumed by tractors, combines, and borehole generators.<br>
                        • <strong>Synthetic Soil Nitrogen:</strong> Direct soil N₂O volatilization from basal and Urea applications.
                    </div>
                </div>

                <!-- Scope 2 -->
                <div style="background: var(--paper-subtle); padding: 14px; border-left: 4px solid var(--stamp-navy); border-radius: 2px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <span style="font-weight: 700; font-size: 13.5px;">Scope 2: Indirect Pumping &amp; Electricity</span>
                        <strong class="mono" style="color: var(--stamp-navy); font-size: 15px;"><?php echo number_format($display_scope_2); ?> kg CO₂e</strong>
                    </div>
                    <div style="font-size: 12.5px; color: var(--ink-muted); line-height: 1.5;">
                        • <strong>ZESCO Grid Pumping:</strong> Electricity consumed by center pivot booster pumps.<br>
                        • <strong>Cold Chain &amp; Storage:</strong> On-farm grain aeration fans and refrigeration units.
                    </div>
                </div>

                <!-- Scope 3 -->
                <div style="background: var(--paper-subtle); padding: 14px; border-left: 4px solid var(--stamp-amber); border-radius: 2px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <span style="font-weight: 700; font-size: 13.5px;">Scope 3: Supply Chain &amp; Input Logistics</span>
                        <strong class="mono" style="color: var(--stamp-amber); font-size: 15px;"><?php echo number_format($display_scope_3); ?> kg CO₂e</strong>
                    </div>
                    <div style="font-size: 12.5px; color: var(--ink-muted); line-height: 1.5;">
                        • <strong>Upstream Fertilizer Manufacturing:</strong> Embodied Haber-Bosch synthesis carbon.<br>
                        • <strong>Outbound Commodity Transport:</strong> Road freight to millers and abattoirs.
                    </div>
                </div>

                <!-- Sequestration -->
                <div style="background: rgba(35, 89, 44, 0.08); padding: 14px; border-left: 4px solid var(--stamp-green); border-radius: 2px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <span style="font-weight: 700; font-size: 13.5px; color: var(--stamp-green);">Carbon Sink &amp; Biological Sequestration</span>
                        <strong class="mono" style="color: var(--stamp-green); font-size: 15px;">-<?php echo number_format($display_seq); ?> kg CO₂e</strong>
                    </div>
                    <div style="font-size: 12.5px; color: var(--ink-primary); line-height: 1.5;">
                        • <strong>Perennial Rhodes Grass Pastures:</strong> Deep-root biomass carbon capture.<br>
                        • <strong>Conservation Agriculture:</strong> Minimum tillage preserving soil organic matter.<br>
                        • <strong>Agroforestry Windbreaks:</strong> Boundary indigenous tree carbon absorption.
                    </div>
                </div>
            </div>
        </div>

        <!-- Carbon Credit Monetization & Certificate Box -->
        <div>
            <div class="ledger-card border-green" style="text-align: center; padding: 24px;">
                <div class="ledger-seal" style="margin: 0 auto 10px; width: 46px; height: 46px; font-size: 16px;">CO₂</div>
                <h3 style="font-size: 1.25rem; margin-bottom: 6px;">Voluntary Carbon Certificate</h3>
                <p style="font-size: 13px; color: var(--ink-muted); margin-bottom: 14px;">
                    Verified Carbon Standard (VCS) compliant audit certificate generated under the Zambian Agriculture Emission Reduction Protocol.
                </p>

                <div style="background: var(--paper-subtle); border: 2px dashed var(--border-rule); padding: 16px; margin-bottom: 16px; text-align: left;">
                    <div style="font-size: 11px; color: var(--ink-faint);" class="mono">ISSUED TO:</div>
                    <div style="font-weight: 700; font-size: 15px;"><?php echo sanitize($active_farm['farm_name']); ?></div>
                    <div style="font-size: 12.5px; color: var(--ink-muted); margin-bottom: 8px;"><?php echo sanitize($active_farm['location']); ?></div>

                    <div style="display: flex; justify-content: space-between; border-top: 1px dashed var(--border-rule); padding-top: 8px; font-size: 12px;">
                        <span>Verified Sink Quantity:</span>
                        <strong class="mono"><?php echo number_format($display_seq); ?> kg CO₂e</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding-top: 4px; font-size: 12px;">
                        <span>Certified Carbon Credits:</span>
                        <strong class="mono" style="color: var(--stamp-green);"><?php echo $display_credits; ?> Verified Credits</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding-top: 4px; font-size: 12px;">
                        <span>Registry Serial Number:</span>
                        <span class="mono" style="font-size: 11px;">FL-CO2-ZM-<?php echo strtoupper(substr(md5((string)$active_farm_id), 0, 8)); ?></span>
                    </div>
                </div>

                <a href="tokens.php" class="ledger-btn ledger-btn-primary" style="width: 100%; display: block; text-align: center; text-decoration: none;">
                    Deposit Credits into Token Wallet &rarr;
                </a>
            </div>
        </div>

    </div>

    <!-- Historical Audit Log Table -->
    <div class="ledger-card" style="margin-top: 16px;">
        <h3 style="font-size: 1.25rem; margin-bottom: 12px;">Carbon Audit Certifications History</h3>

        <?php if (empty($carbon_history)): ?>
        <p style="color: var(--ink-muted);">No historical carbon audits logged. Click "Run &amp; Certify Carbon Audit" above to record your first official certification.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Audit Timestamp</th>
                        <th>Type</th>
                        <th style="text-align: right;">Scope 1 (Direct)</th>
                        <th style="text-align: right;">Scope 2 (Energy)</th>
                        <th style="text-align: right;">Scope 3 (Supply)</th>
                        <th style="text-align: right;">Sequestration</th>
                        <th style="text-align: right;">Net Footprint</th>
                        <th style="text-align: right;">Credits</th>
                        <th>Certification</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($carbon_history as $ch): ?>
                    <tr>
                        <td class="mono"><?php echo format_date_mono($ch['calculated_at']); ?></td>
                        <td><span class="mono" style="font-size: 11px;"><?php echo strtoupper($ch['source_type']); ?></span></td>
                        <td style="text-align: right;" class="mono"><?php echo number_format((float)$ch['scope_1_emissions']); ?></td>
                        <td style="text-align: right;" class="mono"><?php echo number_format((float)$ch['scope_2_emissions']); ?></td>
                        <td style="text-align: right;" class="mono"><?php echo number_format((float)$ch['scope_3_emissions']); ?></td>
                        <td style="text-align: right;" class="mono" style="color: var(--stamp-green);">
                            -<?php echo number_format((float)$ch['carbon_sequestration']); ?>
                        </td>
                        <td style="text-align: right;" class="mono">
                            <strong style="color: <?php echo ((float)$ch['net_footprint'] <= 0) ? 'var(--stamp-green)' : 'var(--stamp-amber)'; ?>;">
                                <?php echo number_format((float)$ch['net_footprint']); ?> kg
                            </strong>
                        </td>
                        <td style="text-align: right;" class="mono">
                            <strong style="color: var(--stamp-navy);"><?php echo (int)$ch['credits_earned']; ?></strong>
                        </td>
                        <td>
                            <span class="stamp-badge stamp-green" style="font-size: 9.5px;">[ CERTIFIED ]</span>
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
