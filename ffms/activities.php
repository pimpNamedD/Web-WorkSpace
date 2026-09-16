<?php
/**
 * FFMS (Field Ledger) - Farm Operations & Activity Log
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

login_required();
$user = current_user();
$user_id = $user['id'];

// Fetch user's farms
$stmt_f = $pdo->prepare('SELECT id, farm_name FROM farms WHERE user_id = ? ORDER BY farm_name ASC');
$stmt_f->execute([$user_id]);
$farms = $stmt_f->fetchAll();
$farm_ids = array_column($farms, 'id');

$filter_farm = isset($_GET['farm_id']) && is_numeric($_GET['farm_id']) ? (int)$_GET['farm_id'] : 0;
$filter_type = isset($_GET['type']) ? trim($_GET['type']) : '';
$error = '';

// Handle quick log submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please refresh and try again.';
    } elseif ($_POST['action'] === 'add_activity') {
        $farm_id       = (int)($_POST['farm_id'] ?? 0);
        $activity_type = $_POST['activity_type'] ?? 'note';
        $description   = trim($_POST['description'] ?? '');
        $activity_date = !empty($_POST['activity_date']) ? $_POST['activity_date'] : date('Y-m-d');

        // Verify farm ownership
        $stmt_chk = $pdo->prepare('SELECT id FROM farms WHERE id = ? AND user_id = ?');
        $stmt_chk->execute([$farm_id, $user_id]);
        if (!$stmt_chk->fetch()) {
            $error = 'Invalid farm holding selected.';
        } elseif (empty($description)) {
            $error = 'Activity description cannot be empty.';
        } else {
            $ins = $pdo->prepare('
                INSERT INTO activity_log (farm_id, activity_type, description, activity_date)
                VALUES (?, ?, ?, ?)
            ');
            $ins->execute([$farm_id, $activity_type, $description, $activity_date]);
            set_flash('green', 'Farm activity logged into the daily journal.');
            header('Location: activities.php' . ($filter_farm ? '?farm_id=' . $filter_farm : ''));
            exit;
        }
    } elseif ($_POST['action'] === 'delete_activity') {
        $act_id = (int)($_POST['activity_id'] ?? 0);
        // Verify ownership via farm
        $del = $pdo->prepare('
            DELETE a FROM activity_log a 
            JOIN farms f ON a.farm_id = f.id 
            WHERE a.id = ? AND f.user_id = ?
        ');
        $del->execute([$act_id, $user_id]);
        set_flash('amber', 'Activity journal entry removed.');
        header('Location: activities.php' . ($filter_farm ? '?farm_id=' . $filter_farm : ''));
        exit;
    }
}

// Fetch activities matching filters
$activities = [];
if (!empty($farm_ids)) {
    $where = ['f.user_id = ?'];
    $params = [$user_id];

    if ($filter_farm > 0) {
        $where[] = 'a.farm_id = ?';
        $params[] = $filter_farm;
    }
    if (!empty($filter_type) && in_array($filter_type, ['planting', 'harvest', 'feeding', 'treatment', 'irrigation', 'scouting', 'note'])) {
        $where[] = 'a.activity_type = ?';
        $params[] = $filter_type;
    }

    $sql = '
        SELECT a.*, f.farm_name 
        FROM activity_log a
        JOIN farms f ON a.farm_id = f.id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY a.activity_date DESC, a.id DESC
    ';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $activities = $stmt->fetchAll();
}

$page_title = 'Farm Activity Journal';
include __DIR__ . '/includes/header.php';
?>

<div class="ledger-wrapper">

    <!-- Title Card -->
    <div class="ledger-card border-navy">
        <div class="card-header-ruled">
            <div>
                <span class="folio-tag">OPERATIONS FOLIO &bull; SECTION 04</span>
                <h1 style="margin-top: 6px;">Daily Farm Operations Journal</h1>
                <p style="color: var(--ink-muted); margin-bottom: 0; font-size: 14px;">
                    Chronological audit log of field preparation, planting, irrigation, veterinary treatments, livestock feeding, and scouting.
                </p>
            </div>
            <span class="stamp-badge stamp-navy">[ <?php echo count($activities); ?> ENTRIES ]</span>
        </div>

        <?php if ($error): ?>
        <div class="ledger-flash flash-red" style="margin-top: 14px;">
            <span class="flash-tag">ERROR:</span>
            <span class="flash-text"><?php echo sanitize($error); ?></span>
        </div>
        <?php endif; ?>

        <!-- Filter Bar -->
        <form method="GET" action="activities.php" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; background: var(--paper-subtle); padding: 12px 14px; border-radius: 2px; border: 1px solid var(--border-rule); margin-top: 14px;">
            <div style="display: flex; flex-direction: column; gap: 4px;">
                <label style="font-family: var(--font-mono); font-size: 11px; font-weight: 700; text-transform: uppercase;">Farm</label>
                <select name="farm_id" class="form-control" style="padding: 6px 10px; font-size: 13px;">
                    <option value="0">&mdash; All Holdings &mdash;</option>
                    <?php foreach ($farms as $uf): ?>
                    <option value="<?php echo (int)$uf['id']; ?>" <?php echo ($filter_farm === (int)$uf['id']) ? 'selected' : ''; ?>>
                        <?php echo sanitize($uf['farm_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; flex-direction: column; gap: 4px;">
                <label style="font-family: var(--font-mono); font-size: 11px; font-weight: 700; text-transform: uppercase;">Activity Category</label>
                <select name="type" class="form-control" style="padding: 6px 10px; font-size: 13px;">
                    <option value="">&mdash; All Categories &mdash;</option>
                    <option value="planting" <?php echo ($filter_type === 'planting') ? 'selected' : ''; ?>>Planting &amp; Seeding</option>
                    <option value="harvest" <?php echo ($filter_type === 'harvest') ? 'selected' : ''; ?>>Harvest &amp; Threshing</option>
                    <option value="irrigation" <?php echo ($filter_type === 'irrigation') ? 'selected' : ''; ?>>Irrigation &amp; Pivot Cycles</option>
                    <option value="treatment" <?php echo ($filter_type === 'treatment') ? 'selected' : ''; ?>>Veterinary Dip &amp; Sprays</option>
                    <option value="feeding" <?php echo ($filter_type === 'feeding') ? 'selected' : ''; ?>>Livestock / Poultry Feeding</option>
                    <option value="scouting" <?php echo ($filter_type === 'scouting') ? 'selected' : ''; ?>>Pest &amp; Agronomic Scouting</option>
                    <option value="note" <?php echo ($filter_type === 'note') ? 'selected' : ''; ?>>General Field Observation</option>
                </select>
            </div>

            <div>
                <button type="submit" class="ledger-btn ledger-btn-sm">Apply Filter</button>
                <a href="activities.php" class="ledger-btn ledger-btn-sm" style="margin-left: 4px;">Reset</a>
            </div>
        </form>
    </div>

    <!-- Quick Log Form -->
    <div class="ledger-card border-green">
        <div class="card-header-ruled">
            <div>
                <span class="folio-tag">NEW JOURNAL ENTRY</span>
                <h3 style="margin-top: 4px;">Record Farm Event</h3>
            </div>
            <span class="stamp-badge stamp-green">[ FAST ENTRY ]</span>
        </div>

        <form method="POST" action="activities.php" class="ledger-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="add_activity">

            <div class="form-grid">
                <div class="form-group">
                    <label for="act_farm">Farm Holding <span class="required">*</span></label>
                    <select id="act_farm" name="farm_id" class="form-control" required>
                        <?php foreach ($farms as $f): ?>
                        <option value="<?php echo (int)$f['id']; ?>" <?php echo ($filter_farm === (int)$f['id']) ? 'selected' : ''; ?>>
                            <?php echo sanitize($f['farm_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="act_type">Operation Category <span class="required">*</span></label>
                    <select id="act_type" name="activity_type" class="form-control" required>
                        <option value="scouting">Pest / Field Scouting</option>
                        <option value="treatment">Veterinary Dip / Spraying</option>
                        <option value="irrigation">Irrigation / Pumping</option>
                        <option value="feeding">Livestock / Poultry Feeding</option>
                        <option value="planting">Planting / Seeding</option>
                        <option value="harvest">Harvesting / Bagging</option>
                        <option value="note" selected>General Note / Inspection</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="activity_date">Date of Operation <span class="required">*</span></label>
                    <input type="date" id="activity_date" name="activity_date" class="form-control input-mono" required
                           value="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="description">Operation Description &amp; Findings <span class="required">*</span></label>
                <textarea id="description" name="description" class="form-control" required
                          placeholder="Describe the field, chemicals/quantities used, rainfall millimeters measured, machinery hours run, or animal condition observed..."></textarea>
            </div>

            <div>
                <button type="submit" class="ledger-btn ledger-btn-primary">
                    Commit to Activity Log &rarr;
                </button>
            </div>
        </form>
    </div>

    <!-- Activities Chronological Ledger -->
    <div class="ledger-card border-navy">
        <div class="card-header-ruled">
            <span class="folio-tag">JOURNAL AUDIT TRAIL</span>
            <span class="stamp-badge stamp-navy">[ CHRONOLOGICAL ]</span>
        </div>

        <?php if (empty($activities)): ?>
            <p style="color: var(--ink-muted); text-align: center; padding: 24px;">No journal entries found. Log your daily farm work using the form above.</p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 14px;">
                <?php foreach ($activities as $act): ?>
                <div style="background: var(--paper-card-alt); border: 1px solid var(--border-rule); border-left: 5px solid var(--stamp-navy); padding: 14px 18px; border-radius: 2px;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
                        <div>
                            <span class="stamp-badge stamp-navy" style="font-size: 10.5px; transform: none; margin-right: 8px;">
                                <?php echo strtoupper(sanitize($act['activity_type'])); ?>
                            </span>
                            <span class="col-mono" style="font-weight: 700; color: var(--ink-primary);">
                                <?php echo sanitize($act['farm_name']); ?>
                            </span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span class="col-mono" style="font-size: 11.5px; color: var(--ink-muted);">
                                <?php echo format_date_mono($act['activity_date']); ?>
                            </span>
                            <form method="POST" action="activities.php" onsubmit="return confirm('Delete this log entry?');" style="display: inline;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete_activity">
                                <input type="hidden" name="activity_id" value="<?php echo (int)$act['id']; ?>">
                                <button type="submit" class="ledger-btn ledger-btn-sm" style="color: var(--stamp-red); padding: 2px 6px; font-size: 10px;">
                                    &times;
                                </button>
                            </form>
                        </div>
                    </div>
                    <div style="font-size: 14px; color: var(--ink-primary); line-height: 1.5;">
                        <?php echo nl2br(sanitize($act['description'])); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
