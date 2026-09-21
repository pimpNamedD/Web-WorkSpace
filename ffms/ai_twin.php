<?php
/**
 * FFMS (Field Ledger) - AI Crop Digital Twin & Yield Simulation Engine
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

login_required();
$user = current_user();
$user_id = $user['id'];

// Agronomic Simulation Function for Crop Digital Twin
function run_twin_simulation(array $crop, float $irrigation_mm, float $fertilizer_kg_ha, float $pesticide_l_ha): array {
    $crop_name = strtolower($crop['crop_name']);
    $area_ha   = max(0.1, (float)$crop['area_hectares']);

    // Baseline yield expectations per hectare in Zambian agro-ecological zone
    $base_yield_kg_ha = 6000.0; // Default maize baseline
    if (str_contains($crop_name, 'soya')) {
        $base_yield_kg_ha = 2500.0;
    } elseif (str_contains($crop_name, 'wheat')) {
        $base_yield_kg_ha = 7000.0;
    } elseif (str_contains($crop_name, 'sunflower')) {
        $base_yield_kg_ha = 1600.0;
    } elseif (str_contains($crop_name, 'bean')) {
        $base_yield_kg_ha = 1400.0;
    }

    // 1. Water response curve (Diminishing returns & stress curve)
    // Optimal water: 120-180mm supplement for irrigated, or 80-120mm
    $water_ratio = $irrigation_mm / 140.0;
    if ($water_ratio < 0.3) {
        $water_factor = 0.65;
        $water_stress = 'High Deficit / Moisture Stress';
    } elseif ($water_ratio <= 1.2) {
        $water_factor = 0.85 + (0.35 * ($water_ratio / 1.2));
        $water_stress = 'Optimal Moisture Available';
    } else {
        // Over-irrigation saturation penalty
        $water_factor = 1.20 - (0.15 * ($water_ratio - 1.2));
        $water_stress = 'Excessive Irrigation / Waterlogging Risk';
    }

    // 2. Fertilizer response curve (Nitrogen & Basal curve)
    // Optimal: 120-160 kg/ha
    $fert_ratio = $fertilizer_kg_ha / 130.0;
    if ($fert_ratio < 0.4) {
        $fert_factor = 0.70;
        $nutrient_status = 'Severe Nitrogen & Phosphate Deficiency';
    } elseif ($fert_ratio <= 1.3) {
        $fert_factor = 0.80 + (0.35 * ($fert_ratio / 1.3));
        $nutrient_status = 'Balanced Soil Fertility Nutrition';
    } else {
        $fert_factor = 1.15; // Plateau
        $nutrient_status = 'Luxury Consumption / Leaching Risk';
    }

    // 3. Plant defense factor
    $pest_factor = 0.88 + min(0.18, ($pesticide_l_ha / 20.0) * 0.18);

    // Yield computation
    $simulated_yield_kg_ha = round($base_yield_kg_ha * $water_factor * $fert_factor * $pest_factor, 1);
    $total_predicted_yield_kg = round($simulated_yield_kg_ha * $area_ha, 0);

    // Dynamic AI Recommendations
    $recommendations = [];
    if ($irrigation_mm < 60 && !str_contains($crop_name, 'rainfed')) {
        $recommendations[] = "Increase center pivot cycle to deliver at least 25mm per week before flowering.";
    } elseif ($irrigation_mm > 220) {
        $recommendations[] = "Irrigation application exceeds evapotranspiration rate; reduce cycle to conserve electricity and avoid root rot.";
    }

    if ($fertilizer_kg_ha < 70 && !str_contains($crop_name, 'soya') && !str_contains($crop_name, 'bean')) {
        $recommendations[] = "Nitrogen level is sub-optimal for high-yield potential. Apply 50kg/ha Urea split top-dressing.";
    } elseif ($fertilizer_kg_ha > 180) {
        $recommendations[] = "High nitrogen level detected; monitor for vegetative lodging during windy thunderstorms.";
    }

    if ($pesticide_l_ha < 10) {
        $recommendations[] = "Scouting frequency should be increased to 2x weekly for Fall Armyworm and cutworm presence.";
    } else {
        $recommendations[] = "Pest suppression index is optimal. Adhere strictly to the pre-harvest chemical withdrawal period.";
    }

    $days_to_harvest = max(14, 45 - (int)round(($irrigation_mm + $fertilizer_kg_ha) / 10));
    $harvest_date = date('Y-m-d', strtotime("+{$days_to_harvest} days"));

    return [
        'yield_kg_ha'               => $simulated_yield_kg_ha,
        'total_yield_kg'            => $total_predicted_yield_kg,
        'water_stress'              => $water_stress,
        'nutrient_status'           => $nutrient_status,
        'optimal_harvest_date'      => $harvest_date,
        'recommendation'            => implode(" ", $recommendations),
        'irrigation_mm'             => $irrigation_mm,
        'fertilizer_kg_ha'          => $fertilizer_kg_ha,
        'pesticide_l_ha'            => $pesticide_l_ha,
        'simulated_at'              => date('Y-m-d H:i:s')
    ];
}

// Check for async AJAX simulation request
if (isset($_GET['ajax_simulate']) && $_GET['ajax_simulate'] === '1') {
    header('Content-Type: application/json');
    $crop_id    = (int)($_GET['crop_id'] ?? 0);
    $irrigation = (float)($_GET['irrigation'] ?? 100);
    $fertilizer = (float)($_GET['fertilizer'] ?? 100);
    $pesticide  = (float)($_GET['pesticide'] ?? 15);

    $stmt_c = $pdo->prepare('SELECT c.*, f.farm_name FROM crops c JOIN farms f ON c.farm_id = f.id WHERE c.id = ? AND f.user_id = ?');
    $stmt_c->execute([$crop_id, $user_id]);
    $crop = $stmt_c->fetch();

    if (!$crop) {
        echo json_encode(['error' => 'Crop not found']);
        exit;
    }

    $result = run_twin_simulation($crop, $irrigation, $fertilizer, $pesticide);
    echo json_encode($result);
    exit;
}

// Fetch all active crops for the user
$stmt_crops = $pdo->prepare('
    SELECT c.*, f.farm_name 
    FROM crops c 
    JOIN farms f ON c.farm_id = f.id 
    WHERE f.user_id = ? 
    ORDER BY c.status = "growing" DESC, c.planting_date DESC
');
$stmt_crops->execute([$user_id]);
$crops = $stmt_crops->fetchAll();

if (empty($crops)) {
    set_flash('amber', 'Please plant at least one crop block before launching the AI Digital Twin.');
    header('Location: crops.php');
    exit;
}

// Active crop selection
$active_crop_id = isset($_GET['crop_id']) ? (int)$_GET['crop_id'] : ($crops[0]['id'] ?? 0);
$active_crop = null;
foreach ($crops as $c) {
    if ($c['id'] === $active_crop_id) {
        $active_crop = $c;
        break;
    }
}
if (!$active_crop) {
    $active_crop = $crops[0];
    $active_crop_id = (int)$active_crop['id'];
}

// Decode existing twin data if stored
$existing_twin = [];
if (!empty($active_crop['digital_twin_data'])) {
    $existing_twin = json_decode($active_crop['digital_twin_data'], true) ?: [];
}

$current_irrigation = (float)($existing_twin['irrigation_mm'] ?? 120.0);
$current_fertilizer = (float)($existing_twin['fertilizer_kg_ha'] ?? 140.0);
$current_pesticide  = (float)($existing_twin['pesticide_l_ha'] ?? 15.0);

// Handle saving the simulation to crop records
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_twin' && verify_csrf()) {
    $post_irrigation = (float)($_POST['irrigation_mm'] ?? $current_irrigation);
    $post_fertilizer = (float)($_POST['fertilizer_kg_ha'] ?? $current_fertilizer);
    $post_pesticide  = (float)($_POST['pesticide_l_ha'] ?? $current_pesticide);

    $sim = run_twin_simulation($active_crop, $post_irrigation, $post_fertilizer, $post_pesticide);

    $twin_json = json_encode([
        'irrigation_mm'    => $post_irrigation,
        'fertilizer_kg_ha' => $post_fertilizer,
        'pesticide_l_ha'   => $post_pesticide,
        'simulated_at'     => $sim['simulated_at']
    ]);

    $insights_json = json_encode([
        'water_stress'         => $sim['water_stress'],
        'nutrient_status'      => $sim['nutrient_status'],
        'optimal_harvest_date' => $sim['optimal_harvest_date'],
        'recommendation'       => $sim['recommendation']
    ]);

    $stmt_up = $pdo->prepare('
        UPDATE crops 
        SET yield_predicted = ?, digital_twin_data = ?, ai_insights = ?
        WHERE id = ?
    ');
    $stmt_up->execute([$sim['total_yield_kg'], $twin_json, $insights_json, $active_crop_id]);

    // Log to activity
    $act_text = "AI Crop Twin updated for {$active_crop['crop_name']}: Predicted Yield: " . number_format($sim['total_yield_kg']) . " kg (" . number_format($sim['yield_kg_ha']) . " kg/ha).";
    $stmt_act = $pdo->prepare('INSERT INTO activity_log (farm_id, activity_type, description, activity_date) VALUES (?, "scouting", ?, CURDATE())');
    $stmt_act->execute([$active_crop['farm_id'], $act_text]);

    set_flash('green', "Digital Twin scenario saved! Predicted Harvest: " . number_format($sim['total_yield_kg']) . " kg.");
    header('Location: ai_twin.php?crop_id=' . $active_crop_id);
    exit;
}

// Initial simulation calculation for rendering
$simulation = run_twin_simulation($active_crop, $current_irrigation, $current_fertilizer, $current_pesticide);

$page_title = 'AI Crop Twin Simulator — ' . $active_crop['crop_name'];
include __DIR__ . '/includes/header.php';
?>

<div class="ledger-wrapper">

    <!-- Title Card -->
    <div class="ledger-card border-green">
        <div class="card-header-ruled">
            <div>
                <span class="folio-tag">AGRONOMIC AI ENGINE &bull; CROP TWIN SIMULATION</span>
                <h1 style="margin-top: 6px;">AI Crop Digital Twin &amp; Yield Simulation</h1>
                <p style="color: var(--ink-muted); margin-bottom: 0; font-size: 14px;">
                    Simulate real-time "What-If" agronomic scenarios for irrigation depth, fertilizer dosage, and plant protection.
                </p>
            </div>
            <div style="text-align: right;">
                <span class="stamp-badge stamp-green">[ DIGITAL TWIN SYNCED ]</span>
            </div>
        </div>

        <!-- Crop Selector Tabs -->
        <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 14px; padding-top: 10px; border-top: 1px dashed var(--border-rule);">
            <?php foreach ($crops as $c): ?>
            <a href="ai_twin.php?crop_id=<?php echo (int)$c['id']; ?>" class="ledger-btn ledger-btn-sm <?php echo ($c['id'] == $active_crop_id) ? 'ledger-btn-primary' : ''; ?>">
                <?php echo sanitize($c['crop_name']); ?> (<?php echo sanitize($c['farm_name']); ?>)
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Main Digital Twin Interactive Dashboard -->
    <div style="display: grid; grid-template-columns: 1.1fr 1fr; gap: 20px; margin-top: 16px;">

        <!-- Left: Interactive Parameter Sliders & Twin Visualization -->
        <div class="ledger-card border-navy">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <h2 style="font-size: 1.3rem; margin-bottom: 0;">Twin Parameter Sliders</h2>
                <span class="mono" style="font-size: 11px; color: var(--ink-muted);">BLOCK: <?php echo sanitize($active_crop['field_name'] ?: 'Block A'); ?> (<?php echo format_qty($active_crop['area_hectares']); ?> ha)</span>
            </div>
            <p style="font-size: 13px; color: var(--ink-muted); margin-bottom: 18px;">
                Adjust inputs below to simulate crop physiological response curves in real-time.
            </p>

            <form method="POST" action="ai_twin.php?crop_id=<?php echo $active_crop_id; ?>" id="twinForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="save_twin">

                <!-- 1. Irrigation Slider -->
                <div style="background: var(--paper-subtle); padding: 14px; border-radius: 4px; border-left: 4px solid var(--stamp-navy); margin-bottom: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <label for="irrigationRange" style="font-weight: 600; font-size: 14px; margin-bottom: 0;">
                            Irrigation Depth (mm)
                        </label>
                        <span class="mono" id="irrigationVal" style="font-weight: 700; font-size: 16px; color: var(--stamp-navy);">
                            <?php echo (int)$current_irrigation; ?> mm
                        </span>
                    </div>
                    <input type="range" name="irrigation_mm" id="irrigationRange" min="0" max="300" step="5" 
                           value="<?php echo (int)$current_irrigation; ?>" style="width: 100%; accent-color: var(--stamp-navy); cursor: pointer;">
                    <div style="display: flex; justify-content: space-between; font-size: 10px; color: var(--ink-faint); margin-top: 4px;" class="mono">
                        <span>0 mm (Pure Rainfed)</span>
                        <span>140 mm (Optimal)</span>
                        <span>300 mm (Flood/Max)</span>
                    </div>
                </div>

                <!-- 2. Fertilizer Slider -->
                <div style="background: var(--paper-subtle); padding: 14px; border-radius: 4px; border-left: 4px solid var(--stamp-ochre); margin-bottom: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <label for="fertilizerRange" style="font-weight: 600; font-size: 14px; margin-bottom: 0;">
                            Fertilizer Dosage (kg / ha)
                        </label>
                        <span class="mono" id="fertilizerVal" style="font-weight: 700; font-size: 16px; color: var(--stamp-ochre);">
                            <?php echo (int)$current_fertilizer; ?> kg/ha
                        </span>
                    </div>
                    <input type="range" name="fertilizer_kg_ha" id="fertilizerRange" min="0" max="250" step="5" 
                           value="<?php echo (int)$current_fertilizer; ?>" style="width: 100%; accent-color: var(--stamp-ochre); cursor: pointer;">
                    <div style="display: flex; justify-content: space-between; font-size: 10px; color: var(--ink-faint); margin-top: 4px;" class="mono">
                        <span>0 kg/ha (Unfertilized)</span>
                        <span>130 kg/ha (Target Balanced)</span>
                        <span>250 kg/ha (High Density)</span>
                    </div>
                </div>

                <!-- 3. Pesticide Defense Slider -->
                <div style="background: var(--paper-subtle); padding: 14px; border-radius: 4px; border-left: 4px solid var(--stamp-green); margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <label for="pesticideRange" style="font-weight: 600; font-size: 14px; margin-bottom: 0;">
                            Agrochemical Crop Defense (L / ha)
                        </label>
                        <span class="mono" id="pesticideVal" style="font-weight: 700; font-size: 16px; color: var(--stamp-green);">
                            <?php echo (int)$current_pesticide; ?> L/ha
                        </span>
                    </div>
                    <input type="range" name="pesticide_l_ha" id="pesticideRange" min="0" max="40" step="1" 
                           value="<?php echo (int)$current_pesticide; ?>" style="width: 100%; accent-color: var(--stamp-green); cursor: pointer;">
                    <div style="display: flex; justify-content: space-between; font-size: 10px; color: var(--ink-faint); margin-top: 4px;" class="mono">
                        <span>0 L/ha (Organic / Unprotected)</span>
                        <span>15 L/ha (Recommended IPM)</span>
                        <span>40 L/ha (Heavy Spray)</span>
                    </div>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="ledger-btn ledger-btn-primary" style="flex: 1;">
                        [ COMMIT SCENARIO TO CROP RECORD ]
                    </button>
                    <button type="button" id="resetBtn" class="ledger-btn ledger-btn-sm">
                        Reset Defaults
                    </button>
                </div>
            </form>
        </div>

        <!-- Right: Real-Time Prediction & AI Agronomic Insights -->
        <div>
            <!-- Yield Prediction Banner -->
            <div class="ledger-card border-green" style="background: var(--paper-card); text-align: center; padding: 22px;">
                <span class="folio-tag">PREDICTIVE HARVEST OUTPUT</span>
                <div style="margin-top: 8px;">
                    <div class="mono" id="predYieldTotal" style="font-size: 2.3rem; font-weight: 700; color: var(--stamp-green); line-height: 1.1;">
                        <?php echo number_format($simulation['total_yield_kg']); ?> kg
                    </div>
                    <div style="font-size: 14px; color: var(--ink-muted); margin-top: 4px;">
                        Predicted Yield: <strong class="mono" id="predYieldHa"><?php echo number_format($simulation['yield_kg_ha']); ?></strong> kg/ha across <?php echo format_qty($active_crop['area_hectares']); ?> ha
                    </div>
                </div>

                <!-- Comparison to baseline target -->
                <?php 
                    $target_yield = (float)$active_crop['expected_yield_kg'];
                    $diff_yield = $simulation['total_yield_kg'] - $target_yield;
                    $diff_pct = ($target_yield > 0) ? (($diff_yield / $target_yield) * 100) : 0;
                ?>
                <div style="display: flex; justify-content: center; gap: 16px; margin-top: 14px; padding-top: 12px; border-top: 1px dashed var(--border-rule);">
                    <div>
                        <div style="font-size: 11px; color: var(--ink-faint);">Target Baseline:</div>
                        <div class="mono" style="font-weight: 600;"><?php echo number_format($target_yield); ?> kg</div>
                    </div>
                    <div>
                        <div style="font-size: 11px; color: var(--ink-faint);">Scenario Variance:</div>
                        <div class="mono" id="predVariance" style="font-weight: 700; color: <?php echo ($diff_yield >= 0) ? 'var(--stamp-green)' : 'var(--stamp-red)'; ?>;">
                            <?php echo ($diff_yield >= 0 ? '+' : '') . number_format($diff_pct, 1); ?>%
                        </div>
                    </div>
                    <div>
                        <div style="font-size: 11px; color: var(--ink-faint);">Optimal Harvest Window:</div>
                        <div class="mono" id="predHarvestDate" style="font-weight: 600;"><?php echo format_date_mono($simulation['optimal_harvest_date']); ?></div>
                    </div>
                </div>
            </div>

            <!-- Agronomic Stress & Balance Diagnostics -->
            <div class="ledger-card" style="margin-top: 16px;">
                <h3 style="font-size: 1.15rem; margin-bottom: 12px;">Physiological Stress &amp; Nutrient Diagnostics</h3>

                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <!-- Moisture Status -->
                    <div style="background: var(--paper-subtle); padding: 10px 14px; border-radius: 2px; border-left: 3px solid var(--stamp-navy);">
                        <div style="font-size: 11px; text-transform: uppercase; color: var(--ink-faint);">Water Stress Evaluation</div>
                        <div id="diagWaterStress" style="font-weight: 600; color: var(--stamp-navy);">
                            <?php echo sanitize($simulation['water_stress']); ?>
                        </div>
                    </div>

                    <!-- Nutrient Balance -->
                    <div style="background: var(--paper-subtle); padding: 10px 14px; border-radius: 2px; border-left: 3px solid var(--stamp-ochre);">
                        <div style="font-size: 11px; text-transform: uppercase; color: var(--ink-faint);">Nutrient / NPK Balance</div>
                        <div id="diagNutrient" style="font-weight: 600; color: var(--stamp-ochre);">
                            <?php echo sanitize($simulation['nutrient_status']); ?>
                        </div>
                    </div>

                    <!-- AI Recommendation Box -->
                    <div style="background: rgba(35, 89, 44, 0.06); padding: 12px 14px; border: 1px solid rgba(35, 89, 44, 0.2); border-radius: 2px;">
                        <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--stamp-green); margin-bottom: 4px;">
                            [ AI AGRONOMIC ADVISORY ]
                        </div>
                        <p id="diagAdvisory" style="font-size: 13px; color: var(--ink-primary); line-height: 1.5; margin-bottom: 0;">
                            <?php echo sanitize($simulation['recommendation']); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>

<!-- Client-Side Reactive Simulation Script -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const cropId = <?php echo $active_crop_id; ?>;
    const irrInput = document.getElementById('irrigationRange');
    const fertInput = document.getElementById('fertilizerRange');
    const pestInput = document.getElementById('pesticideRange');

    const irrVal = document.getElementById('irrigationVal');
    const fertVal = document.getElementById('fertilizerVal');
    const pestVal = document.getElementById('pesticideVal');

    const totalEl = document.getElementById('predYieldTotal');
    const haEl = document.getElementById('predYieldHa');
    const harvestEl = document.getElementById('predHarvestDate');
    const waterEl = document.getElementById('diagWaterStress');
    const nutEl = document.getElementById('diagNutrient');
    const advEl = document.getElementById('diagAdvisory');

    let debounceTimer = null;

    function triggerSimulation() {
        irrVal.textContent = irrInput.value + ' mm';
        fertVal.textContent = fertInput.value + ' kg/ha';
        pestVal.textContent = pestInput.value + ' L/ha';

        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            const url = `ai_twin.php?ajax_simulate=1&crop_id=${cropId}&irrigation=${irrInput.value}&fertilizer=${fertInput.value}&pesticide=${pestInput.value}`;
            fetch(url)
                .then(r => r.json())
                .then(data => {
                    if (data && !data.error) {
                        totalEl.textContent = Number(data.total_yield_kg).toLocaleString() + ' kg';
                        haEl.textContent = Number(data.yield_kg_ha).toLocaleString();
                        waterEl.textContent = data.water_stress;
                        nutEl.textContent = data.nutrient_status;
                        advEl.textContent = data.recommendation;
                        harvestEl.textContent = data.optimal_harvest_date;
                    }
                })
                .catch(err => console.error(err));
        }, 80);
    }

    irrInput.addEventListener('input', triggerSimulation);
    fertInput.addEventListener('input', triggerSimulation);
    pestInput.addEventListener('input', triggerSimulation);

    document.getElementById('resetBtn').addEventListener('click', () => {
        irrInput.value = 120;
        fertInput.value = 140;
        pestInput.value = 15;
        triggerSimulation();
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
