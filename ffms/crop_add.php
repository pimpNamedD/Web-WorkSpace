<?php
/**
 * FFMS (Field Ledger) - Record New Crop Planting
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

login_required();
$user = current_user();
$user_id = $user['id'];

// Fetch user farms
$stmt_f = $pdo->prepare('SELECT id, farm_name FROM farms WHERE user_id = ? ORDER BY farm_name ASC');
$stmt_f->execute([$user_id]);
$farms = $stmt_f->fetchAll();

if (empty($farms)) {
    set_flash('amber', 'Please register at least one farm holding before adding crops.');
    header('Location: farms.php');
    exit;
}

$preset_farm_id = isset($_GET['farm_id']) ? (int)$_GET['farm_id'] : ($farms[0]['id'] ?? 0);
$error = '';

$zambian_crops = [
    'White Maize (Seed Co SC647)',
    'White Maize (Pioneer P3812W)',
    'Soya Beans (MRI Dina)',
    'Soya Beans (Kafue Local)',
    'Winter Wheat (Irrigated)',
    'Sunflower (Pannar PAN 7033)',
    'Sugar Beans (Kabulangeti)',
    'Groundnuts (Chalimbana)',
    'Cassava (Manyok)',
    'Irish Potatoes',
    'Mixed Commercial Vegetables',
    'Sweet Corn'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Session security token expired. Please try again.';
    } else {
        $farm_id        = (int)($_POST['farm_id'] ?? 0);
        $crop_name      = trim($_POST['crop_name'] ?? '');
        $field_name     = trim($_POST['field_name'] ?? '');
        $area_hectares  = (float)($_POST['area_hectares'] ?? 0);
        $planting_date  = !empty($_POST['planting_date']) ? $_POST['planting_date'] : null;
        $exp_harvest    = !empty($_POST['expected_harvest_date']) ? $_POST['expected_harvest_date'] : null;
        $status         = $_POST['status'] ?? 'growing';
        $exp_yield_kg   = (float)($_POST['expected_yield_kg'] ?? 0);
        $notes          = trim($_POST['notes'] ?? '');

        // Verify farm ownership
        $stmt_check = $pdo->prepare('SELECT id FROM farms WHERE id = ? AND user_id = ?');
        $stmt_check->execute([$farm_id, $user_id]);
        if (!$stmt_check->fetch()) {
            $error = 'Invalid farm holding selected.';
        } elseif (empty($crop_name)) {
            $error = 'Crop name/variety is required.';
        } elseif ($area_hectares <= 0) {
            $error = 'Please enter a valid cultivation area in hectares.';
        } else {
            $ins = $pdo->prepare('
                INSERT INTO crops (farm_id, crop_name, field_name, area_hectares, planting_date, expected_harvest_date, status, expected_yield_kg, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $ins->execute([
                $farm_id,
                $crop_name,
                $field_name,
                $area_hectares,
                $planting_date,
                $exp_harvest,
                $status,
                $exp_yield_kg,
                $notes
            ]);

            // Automatically log an activity entry for this planting
            if ($planting_date) {
                $log_desc = sprintf('Planted %s on field "%s" (%s ha). Target yield: %s kg.', 
                    $crop_name, 
                    $field_name ?: 'Main Plot', 
                    format_qty($area_hectares), 
                    format_qty($exp_yield_kg)
                );
                $pdo->prepare('INSERT INTO activity_log (farm_id, activity_type, description, activity_date) VALUES (?, "planting", ?, ?)')
                    ->execute([$farm_id, $log_desc, $planting_date]);
            }

            set_flash('green', 'Crop record for "' . $crop_name . '" successfully entered into the field ledger.');
            header('Location: crops.php?farm_id=' . $farm_id);
            exit;
        }
    }
}

$page_title = 'Record Crop Planting';
include __DIR__ . '/includes/header.php';
?>

<div class="ledger-container" style="max-width: 740px; margin-top: 20px;">
    <div class="ledger-card border-green">
        <div class="card-header-ruled">
            <div>
                <span class="folio-tag">NEW CROP ENTRY &bull; FOLIO 02</span>
                <h2 style="margin-top: 6px;">Record Crop Cultivation</h2>
            </div>
            <a href="crops.php" class="ledger-btn ledger-btn-sm">&larr; Back to Crops</a>
        </div>

        <?php if ($error): ?>
        <div class="ledger-flash flash-red">
            <span class="flash-tag">ERROR:</span>
            <span class="flash-text"><?php echo sanitize($error); ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" action="crop_add.php" class="ledger-form">
            <?php echo csrf_field(); ?>

            <div class="form-grid">
                <div class="form-group">
                    <label for="farm_id">Farm Holding <span class="required">*</span></label>
                    <select id="farm_id" name="farm_id" class="form-control" required>
                        <?php foreach ($farms as $f): ?>
                        <option value="<?php echo (int)$f['id']; ?>" <?php echo ($preset_farm_id === (int)$f['id']) ? 'selected' : ''; ?>>
                            <?php echo sanitize($f['farm_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="crop_select">Select Common Zambian Crop Preset</label>
                    <select id="crop_select" class="form-control" onchange="applyCropPreset(this.value)">
                        <option value="">&mdash; Choose from Zambian Varieties &mdash;</option>
                        <?php foreach ($zambian_crops as $zc): ?>
                        <option value="<?php echo sanitize($zc); ?>"><?php echo sanitize($zc); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="crop_name">Crop Name &amp; Variety <span class="required">*</span></label>
                    <input type="text" id="crop_name" name="crop_name" class="form-control" required
                           placeholder="e.g. White Maize (Seed Co SC647)">
                </div>

                <div class="form-group">
                    <label for="field_name">Field Name / Plot Identifier</label>
                    <input type="text" id="field_name" name="field_name" class="form-control"
                           placeholder="e.g. Block A - North Pivot, Plot 4 Lower">
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="area_hectares">Area Under Crop (Hectares) <span class="required">*</span></label>
                    <input type="number" step="0.01" id="area_hectares" name="area_hectares" class="form-control input-mono" required
                           placeholder="e.g. 25.50">
                </div>

                <div class="form-group">
                    <label for="status">Initial Status <span class="required">*</span></label>
                    <select id="status" name="status" class="form-control" required>
                        <option value="growing" selected>Growing (Currently in field)</option>
                        <option value="planned">Planned (Upcoming planting)</option>
                    </select>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="planting_date">Date of Planting</label>
                    <input type="date" id="planting_date" name="planting_date" class="form-control input-mono"
                           value="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="form-group">
                    <label for="expected_harvest_date">Expected Maturity / Harvest Date</label>
                    <input type="date" id="expected_harvest_date" name="expected_harvest_date" class="form-control input-mono">
                </div>

                <div class="form-group">
                    <label for="expected_yield_kg">Target / Expected Yield (kg)</label>
                    <input type="number" step="0.01" id="expected_yield_kg" name="expected_yield_kg" class="form-control input-mono"
                           placeholder="e.g. 175000 (approx 3,500 x 50kg bags)">
                </div>
            </div>

            <div class="form-group">
                <label for="notes">Agronomic Notes &amp; Inputs Applied</label>
                <textarea id="notes" name="notes" class="form-control" placeholder="Basal D-Compound fertilizer application rate, seed pocket numbers, pesticide treatment schedule..."></textarea>
            </div>

            <div style="margin-top: 8px;">
                <button type="submit" class="ledger-btn ledger-btn-primary">
                    Enter Crop in Field Ledger &rarr;
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function applyCropPreset(val) {
    if (val) {
        document.getElementById('crop_name').value = val;
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
