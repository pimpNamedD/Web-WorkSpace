<?php
/**
 * FFMS (Field Ledger) - Register New Livestock Batch / Animal
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
    set_flash('amber', 'Please register a farm holding before adding livestock.');
    header('Location: farms.php');
    exit;
}

$preset_farm_id = isset($_GET['farm_id']) ? (int)$_GET['farm_id'] : ($farms[0]['id'] ?? 0);
$error = '';

$zambian_livestock = [
    'Boran Beef Cattle (Breeding Cows)',
    'Boran Beef Bulls',
    'Brahman Beef Steers',
    'Holstein-Friesian Dairy Cows',
    'Boer Goats (Breeding Flock)',
    'Dorper Sheep',
    'Broiler Chickens (Cobb 500 / Ross 308)',
    'Layer Chickens (Lohmann Brown)',
    'Free-Range Village Chickens',
    'Commercial Pigs (Large White / Landrace)'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please try again.';
    } else {
        $farm_id          = (int)($_POST['farm_id'] ?? 0);
        $animal_type      = trim($_POST['animal_type'] ?? '');
        $tag_id           = trim($_POST['tag_id'] ?? '');
        $quantity         = (int)($_POST['quantity'] ?? 1);
        $acquisition_date = !empty($_POST['acquisition_date']) ? $_POST['acquisition_date'] : null;
        $health_status    = $_POST['health_status'] ?? 'healthy';
        $notes            = trim($_POST['notes'] ?? '');

        // Verify farm ownership
        $stmt_chk = $pdo->prepare('SELECT id FROM farms WHERE id = ? AND user_id = ?');
        $stmt_chk->execute([$farm_id, $user_id]);
        if (!$stmt_chk->fetch()) {
            $error = 'Invalid farm holding selected.';
        } elseif (empty($animal_type)) {
            $error = 'Animal type / breed is required.';
        } elseif ($quantity <= 0) {
            $error = 'Quantity must be at least 1 head.';
        } else {
            $ins = $pdo->prepare('
                INSERT INTO livestock (farm_id, animal_type, tag_id, quantity, acquisition_date, health_status, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ');
            $ins->execute([
                $farm_id,
                $animal_type,
                $tag_id,
                $quantity,
                $acquisition_date,
                $health_status,
                $notes
            ]);

            // Auto-log activity
            $log_desc = sprintf('Registered %d head of %s (Tag: %s). Initial condition: %s.',
                $quantity,
                $animal_type,
                $tag_id ?: 'General Batch',
                strtoupper(str_replace('_', ' ', $health_status))
            );
            $pdo->prepare('INSERT INTO activity_log (farm_id, activity_type, description, activity_date) VALUES (?, "note", ?, ?)')
                ->execute([$farm_id, $log_desc, $acquisition_date ?: date('Y-m-d')]);

            set_flash('green', 'Livestock entry for "' . $animal_type . '" recorded into the field ledger.');
            header('Location: livestock.php?farm_id=' . $farm_id);
            exit;
        }
    }
}

$page_title = 'Register Livestock';
include __DIR__ . '/includes/header.php';
?>

<div class="ledger-container" style="max-width: 740px; margin-top: 20px;">
    <div class="ledger-card border-ochre">
        <div class="card-header-ruled">
            <div>
                <span class="folio-tag">NEW LIVESTOCK ENTRY &bull; FOLIO 03</span>
                <h2 style="margin-top: 6px;">Register Herd or Batch</h2>
            </div>
            <a href="livestock.php" class="ledger-btn ledger-btn-sm">&larr; Back to Livestock</a>
        </div>

        <?php if ($error): ?>
        <div class="ledger-flash flash-red">
            <span class="flash-tag">ERROR:</span>
            <span class="flash-text"><?php echo sanitize($error); ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" action="livestock_add.php" class="ledger-form">
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
                    <label for="livestock_select">Select Common Zambian Livestock Preset</label>
                    <select id="livestock_select" class="form-control" onchange="applyStockPreset(this.value)">
                        <option value="">&mdash; Choose Breed / Species &mdash;</option>
                        <?php foreach ($zambian_livestock as $zl): ?>
                        <option value="<?php echo sanitize($zl); ?>"><?php echo sanitize($zl); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="animal_type">Animal Type / Breed <span class="required">*</span></label>
                    <input type="text" id="animal_type" name="animal_type" class="form-control" required
                           placeholder="e.g. Boran Beef Cattle (Breeding Cows)">
                </div>

                <div class="form-group">
                    <label for="tag_id">Ear Tag / Batch Reference</label>
                    <input type="text" id="tag_id" name="tag_id" class="form-control input-mono"
                           placeholder="e.g. TAG-BOR-2026/01 or BATCH-BROILER-42">
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="quantity">Head Count / Quantity <span class="required">*</span></label>
                    <input type="number" id="quantity" name="quantity" class="form-control input-mono" min="1" required
                           value="1" placeholder="Number of head or birds">
                </div>

                <div class="form-group">
                    <label for="health_status">Current Health Condition <span class="required">*</span></label>
                    <select id="health_status" name="health_status" class="form-control" required>
                        <option value="healthy" selected>Healthy (Routine condition)</option>
                        <option value="under_treatment">Under Treatment (Veterinary care)</option>
                        <option value="sick">Sick (Quarantined)</option>
                        <option value="deceased">Deceased / Loss</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="acquisition_date">Acquisition / Hatch Date</label>
                    <input type="date" id="acquisition_date" name="acquisition_date" class="form-control input-mono"
                           value="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="notes">Veterinary Protocols &amp; Feeding Notes</label>
                <textarea id="notes" name="notes" class="form-control" placeholder="Plunge dip schedule, Anthrax / Blackleg vaccine batch, broiler feed phase, dewormer administration..."></textarea>
            </div>

            <div style="margin-top: 8px;">
                <button type="submit" class="ledger-btn ledger-btn-primary">
                    Record Livestock in Ledger &rarr;
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function applyStockPreset(val) {
    if (val) {
        document.getElementById('animal_type').value = val;
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
