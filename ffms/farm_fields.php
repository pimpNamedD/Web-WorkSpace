<?php
/**
 * FFMS (Field Ledger) - Farm Fields & GPS Parcels Ledger
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
    set_flash('amber', 'Please register at least one farm holding before configuring fields.');
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

$error = '';

// Handle creating a new field parcel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_field' && verify_csrf()) {
    $farm_id   = (int)($_POST['farm_id'] ?? 0);
    $name      = trim($_POST['name'] ?? '');
    $size      = (float)($_POST['size_hectares'] ?? 0);
    $soil_type = trim($_POST['soil_type'] ?? 'Sandy Clay Loam');
    $gps_json  = trim($_POST['gps_boundary'] ?? '');
    $notes     = trim($_POST['notes'] ?? '');

    // Verify farm ownership
    $stmt_chk = $pdo->prepare('SELECT id FROM farms WHERE id = ? AND user_id = ?');
    $stmt_chk->execute([$farm_id, $user_id]);
    if (!$stmt_chk->fetch()) {
        $error = 'Invalid farm holding selected.';
    } elseif (empty($name)) {
        $error = 'Field parcel name is required.';
    } elseif ($size <= 0) {
        $error = 'Field size must be greater than zero hectares.';
    } else {
        $stmt_ins = $pdo->prepare('
            INSERT INTO fields (farm_id, name, size_hectares, soil_type, gps_boundary, notes)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt_ins->execute([$farm_id, $name, $size, $soil_type, $gps_json, $notes]);

        set_flash('green', "Field parcel '{$name}' registered under {$active_farm['farm_name']}!");
        header('Location: farm_fields.php?farm_id=' . $farm_id);
        exit;
    }
}

// Fetch fields for active farm
$stmt_fields = $pdo->prepare('
    SELECT fld.*, 
           (SELECT COUNT(*) FROM crops c WHERE c.field_id = fld.id OR c.field_name COLLATE utf8mb4_unicode_ci = fld.name) as crop_count
    FROM fields fld
    WHERE fld.farm_id = ?
    ORDER BY fld.size_hectares DESC, fld.id ASC
');
$stmt_fields->execute([$active_farm_id]);
$fields = $stmt_fields->fetchAll();

$total_parceled_ha = array_sum(array_column($fields, 'size_hectares'));

$page_title = 'Farm Fields & GPS Parcels — ' . $active_farm['farm_name'];
include __DIR__ . '/includes/header.php';
?>

<div class="ledger-wrapper">

    <!-- Header Card -->
    <div class="ledger-card border-green">
        <div class="card-header-ruled">
            <div>
                <span class="folio-tag">CADASTRAL FOLIO &bull; FIELD PARCELS № <?php echo str_pad((string)$active_farm_id, 3, '0', STR_PAD_LEFT); ?></span>
                <h1 style="margin-top: 6px;">Farm Fields &amp; Parcel Boundaries</h1>
                <p style="color: var(--ink-muted); margin-bottom: 0; font-size: 14px;">
                    Subdivide <strong><?php echo sanitize($active_farm['farm_name']); ?></strong> (<?php echo format_qty($active_farm['size_hectares']); ?> ha) into arable parcels with soil profiles and GPS spatial coordinates.
                </p>
            </div>
            <div style="display: flex; gap: 8px; align-items: center;">
                <a href="farms.php" class="ledger-btn ledger-btn-sm">&larr; Back to Farms</a>
            </div>
        </div>

        <!-- Holding Switcher -->
        <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 14px; padding-top: 10px; border-top: 1px dashed var(--border-rule);">
            <?php foreach ($user_farms as $uf): ?>
            <a href="farm_fields.php?farm_id=<?php echo (int)$uf['id']; ?>" class="ledger-btn ledger-btn-sm <?php echo ($uf['id'] == $active_farm_id) ? 'ledger-btn-primary' : ''; ?>">
                <?php echo sanitize($uf['farm_name']); ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Interactive Field Parcels & Spatial Map Layout -->
    <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 20px; margin-top: 16px;">
        
        <!-- Left: Field Parcel Roster -->
        <div>
            <div class="ledger-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                    <div>
                        <h2 style="font-size: 1.25rem; margin-bottom: 2px;">Field Parcels Roster</h2>
                        <span style="font-size: 12px; color: var(--ink-muted);">
                            Allocated: <strong><?php echo format_qty($total_parceled_ha); ?> ha</strong> of <strong><?php echo format_qty($active_farm['size_hectares']); ?> ha</strong>
                        </span>
                    </div>
                    <span class="stamp-badge stamp-green" style="font-size: 10px;">[ CADASTRAL SURVEY ]</span>
                </div>

                <?php if (empty($fields)): ?>
                <div style="text-align: center; padding: 30px; background: var(--paper-subtle); border: 1px dashed var(--border-rule);">
                    <p style="color: var(--ink-muted);">No subdivided fields logged for this holding yet.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Parcel Name</th>
                                <th style="text-align: right;">Area (ha)</th>
                                <th>Soil Classification</th>
                                <th>Crops Linked</th>
                                <th>Notes / Irrigation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fields as $fld): ?>
                            <tr>
                                <td>
                                    <strong><?php echo sanitize($fld['name']); ?></strong>
                                </td>
                                <td style="text-align: right;" class="mono">
                                    <strong style="color: var(--stamp-green);"><?php echo format_qty($fld['size_hectares']); ?></strong> ha
                                </td>
                                <td>
                                    <span class="mono" style="font-size: 12px;"><?php echo sanitize($fld['soil_type']); ?></span>
                                </td>
                                <td class="mono" style="font-size: 12px;">
                                    <?php echo (int)$fld['crop_count']; ?> cycles
                                </td>
                                <td style="font-size: 12.5px; color: var(--ink-muted);">
                                    <?php echo sanitize($fld['notes'] ?: '—'); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- Visual SVG Farm Parcel Map Canvas -->
            <div class="ledger-card border-navy" style="margin-top: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <h3 style="font-size: 1.1rem; margin-bottom: 0;">Spatial Cadastral Map (Digital Parcel Plan)</h3>
                    <span class="mono" style="font-size: 11px; color: var(--ink-muted);">SCALE: 1:10,000</span>
                </div>
                <p style="font-size: 12.5px; color: var(--ink-muted); margin-bottom: 12px;">
                    Visual field plot layout of <?php echo sanitize($active_farm['farm_name']); ?> showing center pivot arcs, riverfront alluvial parcels, and rotational pasture blocks.
                </p>

                <!-- SVG Parcel Map Canvas -->
                <div style="background: #eef2e6; border: 2px solid var(--border-rule); border-radius: 4px; padding: 12px; text-align: center;">
                    <svg viewBox="0 0 500 280" style="width: 100%; height: auto; max-height: 280px; display: block;">
                        <!-- Outer Farm Boundary -->
                        <polygon points="20,20 480,20 470,260 30,250" fill="#fdfbf7" stroke="#968974" stroke-width="2" stroke-dasharray="4,2" />
                        
                        <!-- River stream for riverfront plots -->
                        <path d="M 15 80 Q 80 120 180 90 T 350 140 T 485 110" fill="none" stroke="#7aa3c7" stroke-width="8" opacity="0.6" />
                        <text x="50" y="110" font-family="'IBM Plex Mono', monospace" font-size="9" fill="#1f3b58" font-style="italic">Kafue / Chongwe Watercourse</text>

                        <!-- Center Pivot A -->
                        <circle cx="140" cy="180" r="55" fill="rgba(35, 89, 44, 0.15)" stroke="#23592c" stroke-width="2" />
                        <circle cx="140" cy="180" r="3" fill="#23592c" />
                        <line x1="140" y1="180" x2="190" y2="160" stroke="#23592c" stroke-width="1.5" />
                        <text x="100" y="183" font-family="'Work Sans', sans-serif" font-size="10" font-weight="600" fill="#23592c">Pivot A (50 ha)</text>

                        <!-- Rainfed Block B -->
                        <polygon points="230,130 360,110 370,210 240,225" fill="rgba(148, 93, 0, 0.12)" stroke="#945d00" stroke-width="2" />
                        <text x="260" y="170" font-family="'Work Sans', sans-serif" font-size="10" font-weight="600" fill="#945d00">Block B (35 ha)</text>

                        <!-- East Pasture C -->
                        <polygon points="380,30 460,30 450,220 385,200" fill="rgba(31, 59, 88, 0.12)" stroke="#1f3b58" stroke-width="2" />
                        <text x="390" y="120" font-family="'Work Sans', sans-serif" font-size="9" font-weight="600" fill="#1f3b58">Pasture C (65 ha)</text>

                        <!-- Compass Rose -->
                        <g transform="translate(440, 60)">
                            <circle cx="0" cy="0" r="14" fill="#fff" stroke="#968974" stroke-width="1" />
                            <line x1="0" y1="-12" x2="0" y2="12" stroke="#a32d2d" stroke-width="1.5" />
                            <text x="-3" y="-5" font-family="'IBM Plex Mono', monospace" font-size="9" font-weight="700" fill="#a32d2d">N</text>
                        </g>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Right: Register New Field Form -->
        <div>
            <div class="ledger-card border-ochre" style="position: sticky; top: 20px;">
                <h3 style="font-size: 1.15rem; margin-bottom: 8px;">Subdivide / Add Field Parcel</h3>
                <p style="font-size: 12.5px; color: var(--ink-muted); margin-bottom: 14px;">
                    Add an arable section, pivot circle, or fenced grazing paddock under <?php echo sanitize($active_farm['farm_name']); ?>.
                </p>

                <?php if (!empty($error)): ?>
                <div class="flash-message flash-red" style="margin-bottom: 12px; font-size: 12px;">
                    <?php echo sanitize($error); ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="farm_fields.php?farm_id=<?php echo $active_farm_id; ?>">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="add_field">
                    <input type="hidden" name="farm_id" value="<?php echo $active_farm_id; ?>">

                    <div style="margin-bottom: 10px;">
                        <label style="font-size: 11px; font-weight: 600;">Parcel Identifier / Name <span style="color: var(--stamp-red);">*</span></label>
                        <input type="text" name="name" class="form-control-sm" style="width: 100%;" required placeholder="e.g. Center Pivot Block C, River Meadow 2">
                    </div>

                    <div style="margin-bottom: 10px;">
                        <label style="font-size: 11px; font-weight: 600;">Size in Hectares <span style="color: var(--stamp-red);">*</span></label>
                        <input type="number" name="size_hectares" step="0.01" min="0.1" class="form-control-sm" style="width: 100%;" required placeholder="25.00">
                    </div>

                    <div style="margin-bottom: 10px;">
                        <label style="font-size: 11px; font-weight: 600;">Soil Classification</label>
                        <select name="soil_type" class="form-control-sm" style="width: 100%;">
                            <option value="Red Sandy Clay Loam">Red Sandy Clay Loam (Ferric Luvisol)</option>
                            <option value="Clay Loam">Heavy Clay Loam (Vertisol)</option>
                            <option value="Alluvial Silt Loam">Alluvial Silt Loam (Riverine)</option>
                            <option value="Sandy Loam">Light Sandy Loam</option>
                            <option value="Black Cotton Soil">Black Cotton Soil</option>
                        </select>
                    </div>

                    <div style="margin-bottom: 10px;">
                        <label style="font-size: 11px; font-weight: 600;">GPS Coordinates / Boundary JSON</label>
                        <input type="text" name="gps_boundary" class="form-control-sm" style="width: 100%;" placeholder='[{"lat":-15.385,"lng":28.450}]'>
                    </div>

                    <div style="margin-bottom: 14px;">
                        <label style="font-size: 11px; font-weight: 600;">Parcel Notes &amp; Irrigation Setup</label>
                        <textarea name="notes" class="form-control-sm" rows="3" style="width: 100%;" placeholder="e.g. Equipped with 3-tower Valley pivot. Hydrant fed from Borehole 2."></textarea>
                    </div>

                    <button type="submit" class="ledger-btn ledger-btn-primary ledger-btn-sm" style="width: 100%;">
                        [ RECORD FIELD PARCEL ]
                    </button>
                </form>
            </div>
        </div>

    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
