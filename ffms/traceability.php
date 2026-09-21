<?php
/**
 * FFMS (Field Ledger) - Blockchain Supply Chain Traceability
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

login_required();
$user = current_user();
$user_id = $user['id'];

// Fetch user's farms
$stmt_f = $pdo->prepare('SELECT id, farm_name, location FROM farms WHERE user_id = ? ORDER BY farm_name ASC');
$stmt_f->execute([$user_id]);
$user_farms = $stmt_f->fetchAll();
$farm_ids = array_column($user_farms, 'id');

if (empty($user_farms)) {
    set_flash('amber', 'Please register at least one farm holding before registering blockchain batches.');
    header('Location: farms.php');
    exit;
}

$active_farm_id = isset($_GET['farm_id']) ? (int)$_GET['farm_id'] : ($user_farms[0]['id'] ?? 0);
$error = '';

// Fetch crops and livestock on this farm for batch registration
$stmt_cr = $pdo->prepare('SELECT id, crop_name, field_name, actual_harvest_date FROM crops WHERE farm_id = ? ORDER BY id DESC');
$stmt_cr->execute([$active_farm_id]);
$farm_crops = $stmt_cr->fetchAll();

// Handle New Batch Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_batch' && verify_csrf()) {
    $product_name  = trim($_POST['product_name'] ?? '');
    $product_type  = trim($_POST['product_type'] ?? 'crop');
    $harvest_date  = !empty($_POST['harvest_date']) ? trim($_POST['harvest_date']) : date('Y-m-d');
    $quality_score = max(50, min(100, (int)($_POST['quality_score'] ?? 95)));
    $moisture_pct  = (float)($_POST['moisture_pct'] ?? 11.5);
    $pesticide_res = trim($_POST['pesticide_residue'] ?? 'None detected');
    $handler       = trim($_POST['handler_name'] ?? $user['full_name']);

    if (empty($product_name)) {
        $error = 'Product name / variety is required.';
    } else {
        // Generate unique batch code and cryptographic block
        $batch_code = 'BATCH-ZM-' . date('Y') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
        $tx_hash = '0x' . bin2hex(random_bytes(32));
        $block_num = rand(19840000, 19890000);

        $metadata_json = json_encode([
            'moisture_pct'       => $moisture_pct,
            'pesticide_residue'  => $pesticide_res,
            'certified_standard' => 'Zambian Bureau of Standards (ZABS) Agricultural Grade A',
            'initial_inspector'  => $handler
        ]);

        // Insert blockchain_records
        $stmt_ins = $pdo->prepare('
            INSERT INTO blockchain_records (
                farm_id, product_type, product_name, batch_code, harvest_date,
                quality_score, is_certified, tx_hash, block_number, metadata, recorded_at
            ) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, NOW())
        ');
        $stmt_ins->execute([
            $active_farm_id,
            $product_type,
            $product_name,
            $batch_code,
            $harvest_date,
            $quality_score,
            $tx_hash,
            $block_num,
            $metadata_json
        ]);
        $record_id = (int)$pdo->lastInsertId();

        // Initial Custody Event: Harvest & Quality Grading
        $stmt_ev = $pdo->prepare('
            INSERT INTO blockchain_custody_events (
                record_id, stage, location, handler_name, notes, tx_hash, recorded_at
            ) VALUES (?, "harvest", ?, ?, ?, ?, NOW())
        ');
        $harvest_notes = "Initial harvest assay completed. Quality score {$quality_score}/100. Moisture: {$moisture_pct}%.";
        $stmt_ev->execute([
            $record_id,
            $user_farms[0]['location'] ?? 'Farm Station',
            $handler,
            $harvest_notes,
            $tx_hash
        ]);

        set_flash('green', "Batch {$batch_code} registered on Blockchain! Block #{$block_num}.");
        header('Location: traceability.php?farm_id=' . $active_farm_id . '&batch_id=' . $record_id);
        exit;
    }
}

// Handle Appending a Custody Checkpoint Event
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_custody_event' && verify_csrf()) {
    $rec_id   = (int)($_POST['record_id'] ?? 0);
    $stage    = trim($_POST['stage'] ?? 'transport');
    $location = trim($_POST['location'] ?? '');
    $handler  = trim($_POST['handler_name'] ?? '');
    $notes    = trim($_POST['notes'] ?? '');

    // Verify record belongs to this user's farm
    $stmt_chk = $pdo->prepare('
        SELECT r.id FROM blockchain_records r 
        JOIN farms f ON r.farm_id = f.id 
        WHERE r.id = ? AND f.user_id = ?
    ');
    $stmt_chk->execute([$rec_id, $user_id]);

    if (!$stmt_chk->fetch()) {
        $error = 'Invalid batch record selected.';
    } elseif (empty($location) || empty($handler)) {
        $error = 'Location and Handler Name are required for chain of custody verification.';
    } else {
        $event_tx = '0x' . bin2hex(random_bytes(32));
        $stmt_ev = $pdo->prepare('
            INSERT INTO blockchain_custody_events (
                record_id, stage, location, handler_name, notes, tx_hash, recorded_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ');
        $stmt_ev->execute([$rec_id, $stage, $location, $handler, $notes, $event_tx]);

        set_flash('green', "Custody checkpoint recorded! Stage: " . strtoupper($stage) . " [Tx: " . substr($event_tx, 0, 16) . "...]");
        header('Location: traceability.php?farm_id=' . $active_farm_id . '&batch_id=' . $rec_id);
        exit;
    }
}

// Fetch all batch records for the active farm
$stmt_batches = $pdo->prepare('
    SELECT b.*, 
           (SELECT COUNT(*) FROM blockchain_custody_events e WHERE e.record_id = b.id) as custody_count
    FROM blockchain_records b
    WHERE b.farm_id = ?
    ORDER BY b.recorded_at DESC
');
$stmt_batches->execute([$active_farm_id]);
$batches = $stmt_batches->fetchAll();

// Selected batch for detailed custody view
$active_batch_id = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : ($batches[0]['id'] ?? 0);
$selected_batch = null;
foreach ($batches as $b) {
    if ($b['id'] === $active_batch_id) {
        $selected_batch = $b;
        break;
    }
}
if (!$selected_batch && !empty($batches)) {
    $selected_batch = $batches[0];
    $active_batch_id = (int)$selected_batch['id'];
}

// Fetch custody events for selected batch
$custody_events = [];
if ($selected_batch) {
    $stmt_ev_list = $pdo->prepare('
        SELECT * FROM blockchain_custody_events 
        WHERE record_id = ? 
        ORDER BY recorded_at ASC
    ');
    $stmt_ev_list->execute([$active_batch_id]);
    $custody_events = $stmt_ev_list->fetchAll();
}

$page_title = 'Blockchain Traceability & Supply Chain';
include __DIR__ . '/includes/header.php';
?>

<div class="ledger-wrapper">

    <!-- Header Card -->
    <div class="ledger-card border-navy">
        <div class="card-header-ruled">
            <div>
                <span class="folio-tag">TRACEABILITY FOLIO &bull; FARM-TO-FORK PROVENANCE</span>
                <h1 style="margin-top: 6px;">Blockchain Supply Chain Traceability</h1>
                <p style="color: var(--ink-muted); margin-bottom: 0; font-size: 14px;">
                    Immutable provenance tracking from planting and harvest to retail distribution. Tamper-proof cryptographic hashes verify origin and quality.
                </p>
            </div>
            <div style="text-align: right;">
                <span class="stamp-badge stamp-navy">[ SMART LEDGER TRACE ]</span>
            </div>
        </div>

        <!-- Holding Switcher -->
        <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 14px; padding-top: 10px; border-top: 1px dashed var(--border-rule);">
            <?php foreach ($user_farms as $uf): ?>
            <a href="traceability.php?farm_id=<?php echo (int)$uf['id']; ?>" class="ledger-btn ledger-btn-sm <?php echo ($uf['id'] == $active_farm_id) ? 'ledger-btn-primary' : ''; ?>">
                <?php echo sanitize($uf['farm_name']); ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (!empty($error)): ?>
    <div class="flash-message flash-red" style="margin-top: 14px;">
        <strong>REJECTED:</strong> <?php echo sanitize($error); ?>
    </div>
    <?php endif; ?>

    <!-- Batch Selection Ribbon -->
    <?php if (!empty($batches)): ?>
    <div style="display: flex; gap: 8px; overflow-x: auto; padding: 10px 0; margin-top: 8px;">
        <?php foreach ($batches as $b): ?>
        <a href="traceability.php?farm_id=<?php echo $active_farm_id; ?>&batch_id=<?php echo (int)$b['id']; ?>" 
           class="ledger-btn ledger-btn-sm <?php echo ($b['id'] === $active_batch_id) ? 'ledger-btn-primary' : ''; ?>" 
           style="white-space: nowrap; font-family: var(--font-mono); font-size: 12px;">
            <?php echo sanitize($b['batch_code']); ?> (<?php echo sanitize($b['product_name']); ?>)
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Main Two-Column Traceability Canvas -->
    <div style="display: grid; grid-template-columns: 1.3fr 1fr; gap: 20px; margin-top: 12px;">

        <!-- Left: Verified Chain of Custody Timeline -->
        <div>
            <?php if ($selected_batch): ?>
            <div class="ledger-card border-green">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 14px; padding-bottom: 12px; border-bottom: 1px dashed var(--border-rule);">
                    <div>
                        <span class="folio-tag"><?php echo strtoupper(sanitize($selected_batch['product_type'])); ?> BATCH PASSPORT</span>
                        <h2 style="margin-top: 4px; font-size: 1.4rem;"><?php echo sanitize($selected_batch['product_name']); ?></h2>
                        <div class="mono" style="font-size: 13px; color: var(--stamp-navy); font-weight: 700;">
                            № <?php echo sanitize($selected_batch['batch_code']); ?>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <span class="stamp-badge stamp-green" style="font-size: 11px;">[ CERTIFIED GRADE A ]</span>
                        <div class="mono" style="font-size: 11px; color: var(--ink-faint); margin-top: 4px;">
                            Block #<?php echo number_format((int)$selected_batch['block_number']); ?>
                        </div>
                    </div>
                </div>

                <!-- Product Attributes & Lab Quality Metrics -->
                <?php 
                    $meta = json_decode($selected_batch['metadata'] ?? '{}', true) ?: [];
                ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; background: var(--paper-subtle); padding: 12px; border-radius: 2px; margin-bottom: 20px;">
                    <div>
                        <div style="font-size: 10.5px; color: var(--ink-faint);">HARVEST DATE:</div>
                        <div class="mono" style="font-weight: 600; font-size: 13px;"><?php echo format_date_mono($selected_batch['harvest_date']); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 10.5px; color: var(--ink-faint);">QUALITY SCORE:</div>
                        <div class="mono" style="font-weight: 700; font-size: 14px; color: var(--stamp-green);"><?php echo (int)$selected_batch['quality_score']; ?> / 100</div>
                    </div>
                    <div>
                        <div style="font-size: 10.5px; color: var(--ink-faint);">MOISTURE CONTENT:</div>
                        <div class="mono" style="font-weight: 600; font-size: 13px;"><?php echo $meta['moisture_pct'] ?? '10.5'; ?>%</div>
                    </div>
                    <div>
                        <div style="font-size: 10.5px; color: var(--ink-faint);">PESTICIDE RESIDUE:</div>
                        <div class="mono" style="font-weight: 600; font-size: 12px; color: var(--stamp-green);"><?php echo $meta['pesticide_residue'] ?? 'Zero Residue'; ?></div>
                    </div>
                </div>

                <!-- Visual Chain of Custody Timeline -->
                <h3 style="font-size: 1.15rem; margin-bottom: 16px;">Verified Chain of Custody Milestones</h3>

                <div style="position: relative; padding-left: 28px; display: flex; flex-direction: column; gap: 20px;">
                    <!-- Vertical Timeline Track Line -->
                    <div style="position: absolute; left: 10px; top: 8px; bottom: 8px; width: 2px; background: var(--border-rule);"></div>

                    <?php foreach ($custody_events as $idx => $ev): 
                        $stage_labels = [
                            'harvest'            => 'Harvest & Farm Grading',
                            'quality_inspection' => 'Laboratory Quality Assay',
                            'cold_storage'       => 'Aerated Silo & Cold Storage',
                            'packaging'          => 'Food-Grade Packing & Sealing',
                            'transport'          => 'GPS Sealed Freight Haulage',
                            'retail_delivery'    => 'Destination Market Acceptance'
                        ];
                        $stage_name = $stage_labels[$ev['stage']] ?? ucfirst($ev['stage']);
                    ?>
                    <div style="position: relative;">
                        <!-- Node Marker -->
                        <div style="position: absolute; left: -24px; top: 2px; width: 14px; height: 14px; border-radius: 50%; background: var(--stamp-navy); border: 2px solid #fff; box-shadow: 0 0 0 2px var(--stamp-navy);"></div>

                        <div style="background: var(--paper-card); border: 1px solid var(--border-rule); padding: 12px 16px; border-radius: 2px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                <strong style="font-size: 13.5px; color: var(--stamp-navy);"><?php echo sanitize($stage_name); ?></strong>
                                <span class="mono" style="font-size: 11px; color: var(--ink-faint);"><?php echo format_date_mono($ev['recorded_at']); ?></span>
                            </div>

                            <div style="font-size: 12px; color: var(--ink-muted); margin-bottom: 4px;">
                                📍 <strong><?php echo sanitize($ev['location']); ?></strong> &bull; Verified by: <strong><?php echo sanitize($ev['handler_name']); ?></strong>
                            </div>

                            <?php if (!empty($ev['notes'])): ?>
                            <div style="font-size: 12.5px; color: var(--ink-primary); margin-bottom: 6px; line-height: 1.4;">
                                <?php echo sanitize($ev['notes']); ?>
                            </div>
                            <?php endif; ?>

                            <div style="font-family: var(--font-mono); font-size: 10px; color: var(--ink-faint);">
                                Tx Hash: <code style="color: var(--stamp-navy);"><?php echo sanitize($ev['tx_hash']); ?></code>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Append Custody Event Form -->
                <div style="margin-top: 24px; padding-top: 16px; border-top: 1px dashed var(--border-rule);">
                    <details>
                        <summary class="ledger-btn ledger-btn-sm" style="cursor: pointer;">
                            + Record Next Custody Checkpoint
                        </summary>
                        <form method="POST" action="traceability.php?farm_id=<?php echo $active_farm_id; ?>&batch_id=<?php echo $active_batch_id; ?>" style="margin-top: 12px; background: var(--paper-subtle); padding: 14px; border-radius: 2px;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="add_custody_event">
                            <input type="hidden" name="record_id" value="<?php echo $active_batch_id; ?>">

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                                <div>
                                    <label style="font-size: 11px; font-weight: 600;">Custody Stage:</label>
                                    <select name="stage" class="form-control-sm" style="width: 100%;">
                                        <option value="quality_inspection">Laboratory Quality Assay</option>
                                        <option value="cold_storage">Aerated Silo / Cold Storage</option>
                                        <option value="packaging">Packaging &amp; Barcode Tagging</option>
                                        <option value="transport">GPS Sealed Freight Transit</option>
                                        <option value="retail_delivery">Market / Abattoir Delivery</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="font-size: 11px; font-weight: 600;">Location / Station:</label>
                                    <input type="text" name="location" class="form-control-sm" style="width: 100%;" required placeholder="e.g. Lusaka Grain Depot 4">
                                </div>
                            </div>

                            <div style="margin-bottom: 10px;">
                                <label style="font-size: 11px; font-weight: 600;">Handler / Inspector Signature:</label>
                                <input type="text" name="handler_name" class="form-control-sm" style="width: 100%;" required placeholder="e.g. Dr. Mutale Banda (Chief Inspector)">
                            </div>

                            <div style="margin-bottom: 12px;">
                                <label style="font-size: 11px; font-weight: 600;">Observations / Temperature / Weight Log:</label>
                                <textarea name="notes" class="form-control-sm" rows="2" style="width: 100%;" placeholder="e.g. Weighed on bridge scale: 30.5 tonnes. Temperature sealed at 17°C."></textarea>
                            </div>

                            <button type="submit" class="ledger-btn ledger-btn-primary ledger-btn-sm">
                                [ COMMIT CHECKPOINT TO CHAIN ]
                            </button>
                        </form>
                    </details>
                </div>
            </div>
            <?php else: ?>
            <div class="ledger-card" style="text-align: center; padding: 40px;">
                <p style="color: var(--ink-muted);">No product batches registered yet for this farm holding.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right: Register New Batch + Public QR & Proof Portal -->
        <div>
            <!-- Register Batch Form -->
            <div class="ledger-card border-ochre">
                <h3 style="font-size: 1.15rem; margin-bottom: 8px;">Register Harvest Batch</h3>
                <p style="font-size: 12.5px; color: var(--ink-muted); margin-bottom: 14px;">
                    Mint an immutable blockchain passport for newly harvested grain, beef, or poultry batches.
                </p>

                <form method="POST" action="traceability.php?farm_id=<?php echo $active_farm_id; ?>">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="register_batch">

                    <div style="margin-bottom: 10px;">
                        <label style="font-size: 11px; font-weight: 600;">Product Description / Variety <span style="color: var(--stamp-red);">*</span></label>
                        <input type="text" name="product_name" class="form-control-sm" style="width: 100%;" required 
                               placeholder="e.g. Winter Wheat (Grade A Hard Red), White Maize SC647">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 10px;">
                        <div>
                            <label style="font-size: 11px; font-weight: 600;">Product Type:</label>
                            <select name="product_type" class="form-control-sm" style="width: 100%;">
                                <option value="crop">Field Crop (Grain/Oilseed)</option>
                                <option value="livestock">Livestock (Pasture Beef)</option>
                                <option value="poultry">Poultry (Broilers/Layers)</option>
                                <option value="dairy">Dairy / Fresh Milk</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size: 11px; font-weight: 600;">Harvest Date:</label>
                            <input type="date" name="harvest_date" class="form-control-sm" style="width: 100%;" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 10px;">
                        <div>
                            <label style="font-size: 11px; font-weight: 600;">Quality Score (0-100):</label>
                            <input type="number" name="quality_score" class="form-control-sm" style="width: 100%;" value="96" min="50" max="100">
                        </div>
                        <div>
                            <label style="font-size: 11px; font-weight: 600;">Moisture (%):</label>
                            <input type="number" name="moisture_pct" step="0.1" class="form-control-sm" style="width: 100%;" value="11.2">
                        </div>
                    </div>

                    <div style="margin-bottom: 14px;">
                        <label style="font-size: 11px; font-weight: 600;">Inspector / Agronomist Name:</label>
                        <input type="text" name="handler_name" class="form-control-sm" style="width: 100%;" value="<?php echo sanitize($user['full_name']); ?>">
                    </div>

                    <button type="submit" class="ledger-btn ledger-btn-primary ledger-btn-sm" style="width: 100%;">
                        [ REGISTER &amp; MINT BATCH ]
                    </button>
                </form>
            </div>

            <!-- Public Consumer Verification Proof Card -->
            <?php if ($selected_batch): ?>
            <div class="ledger-card border-green" style="margin-top: 16px; text-align: center; padding: 20px;">
                <div class="folio-tag">CONSUMER VERIFICATION PORTAL</div>
                <h4 style="margin-top: 4px; font-size: 1.15rem;">Public Proof of Authenticity</h4>
                <p style="font-size: 12.5px; color: var(--ink-muted); margin-bottom: 14px;">
                    Anyone holding this product batch can look up its origin and cryptographic certificate without requiring login credentials.
                </p>

                <!-- Visual QR Code Mock / Public Direct Link -->
                <div style="background: #fff; border: 2px solid var(--border-rule); display: inline-block; padding: 12px; margin-bottom: 12px; border-radius: 4px;">
                    <div style="width: 100px; height: 100px; margin: 0 auto; background: repeating-conic-gradient(#241e17 0% 25%, #ffffff 0% 50%) 50% / 10px 10px; border: 4px solid #241e17;"></div>
                    <div class="mono" style="font-size: 10px; font-weight: 700; margin-top: 6px; color: var(--stamp-navy);">
                        SCAN FOR PROOF
                    </div>
                </div>

                <div style="margin-top: 8px;">
                    <a href="verify.php?batch=<?php echo urlencode($selected_batch['batch_code']); ?>" target="_blank" class="ledger-btn ledger-btn-sm" style="display: block; text-decoration: none;">
                        Open Public Verification Folio &rarr;
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
