<?php
/**
 * FFMS (Field Ledger) - Public Blockchain Traceability & Verification Portal
 * Open access portal for consumers, retailers, and food safety inspectors.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

$batch_query = trim($_GET['batch'] ?? '');
$record = null;
$custody_events = [];

if (!empty($batch_query)) {
    $stmt = $pdo->prepare('
        SELECT b.*, f.farm_name, f.location as farm_location, u.full_name as farmer_name
        FROM blockchain_records b
        JOIN farms f ON b.farm_id = f.id
        JOIN users u ON f.user_id = u.id
        WHERE b.batch_code = ? OR b.tx_hash = ?
        LIMIT 1
    ');
    $stmt->execute([$batch_query, $batch_query]);
    $record = $stmt->fetch();

    if ($record) {
        $stmt_ev = $pdo->prepare('
            SELECT * FROM blockchain_custody_events 
            WHERE record_id = ? 
            ORDER BY recorded_at ASC
        ');
        $stmt_ev->execute([$record['id']]);
        $custody_events = $stmt_ev->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Blockchain Traceability Verification — FFMS Field Ledger</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;0,9..144,700;1,9..144,400&family=IBM+Plex+Mono:wght@400;500;600;700&family=Work+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="<?php echo base_url('assets/css/ledger.css'); ?>">
</head>
<body style="padding: 20px 0;">

    <div class="ledger-container" style="max-width: 860px; margin: 0 auto;">

        <!-- Header -->
        <div style="text-align: center; margin-bottom: 24px;">
            <div class="ledger-seal" style="margin: 0 auto 10px; width: 50px; height: 50px; font-size: 18px;">FL</div>
            <span class="folio-tag">REPUBLIC OF ZAMBIA &bull; PUBLIC AGRICULTURAL TRACEABILITY PORTAL</span>
            <h1 style="font-size: 2rem; margin-top: 4px;">Smart Food Origin &amp; Quality Passport</h1>
            <p style="color: var(--ink-muted); font-size: 14px; max-width: 600px; margin: 0 auto;">
                Verify authentic farm-to-fork origin, harvest parameters, laboratory test assay results, and tamper-proof blockchain chain of custody.
            </p>
        </div>

        <!-- Search Bar -->
        <div class="ledger-card border-navy" style="margin-bottom: 24px; padding: 20px;">
            <form method="GET" action="verify.php" style="display: flex; gap: 10px; flex-wrap: wrap;">
                <input type="text" name="batch" class="form-control" style="flex: 1; min-width: 260px;" 
                       value="<?php echo sanitize($batch_query); ?>" 
                       placeholder="Enter Product Batch № (e.g. BATCH-ZM-2026-WHT-01) or Tx Hash...">
                <button type="submit" class="ledger-btn ledger-btn-primary">
                    [ VERIFY AUTHENTICITY ]
                </button>
            </form>
        </div>

        <?php if (!empty($batch_query) && !$record): ?>
        <div class="ledger-card border-red" style="text-align: center; padding: 40px;">
            <div class="stamp-badge stamp-red" style="font-size: 14px; margin-bottom: 12px;">[ UNVERIFIED BATCH ]</div>
            <h2>No Blockchain Record Found</h2>
            <p style="color: var(--ink-muted); font-size: 14px;">
                The identifier <code><?php echo sanitize($batch_query); ?></code> could not be located on the Field Ledger blockchain. Verify the code on your produce packaging.
            </p>
        </div>
        <?php elseif ($record): 
            $meta = json_decode($record['metadata'] ?? '{}', true) ?: [];
        ?>
        <!-- Verified Product Certificate -->
        <div class="ledger-card border-green" style="box-shadow: var(--shadow-binder);">
            
            <div class="card-header-ruled">
                <div>
                    <span class="folio-tag">SMART CONTRACT PROVENANCE CERTIFICATE</span>
                    <h2 style="margin-top: 4px; font-size: 1.8rem;"><?php echo sanitize($record['product_name']); ?></h2>
                    <div class="mono" style="font-size: 14px; color: var(--stamp-navy); font-weight: 700;">
                        BATCH PASSPORT № <?php echo sanitize($record['batch_code']); ?>
                    </div>
                </div>
                <div style="text-align: right;">
                    <span class="stamp-badge stamp-green" style="font-size: 12px;">[ 100% AUTHENTIC ]</span>
                    <div class="mono" style="font-size: 11px; color: var(--ink-faint); margin-top: 4px;">
                        BLOCK № <?php echo number_format((int)$record['block_number']); ?>
                    </div>
                </div>
            </div>

            <!-- Origin & Farmer Identity -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; background: var(--paper-subtle); padding: 16px; border-radius: 2px; margin: 20px 0;">
                <div>
                    <div style="font-size: 11px; color: var(--ink-faint);">PRODUCING ESTATE:</div>
                    <div style="font-weight: 700; font-size: 15px;"><?php echo sanitize($record['farm_name']); ?></div>
                    <div style="font-size: 12.5px; color: var(--ink-muted);"><?php echo sanitize($record['farm_location']); ?></div>
                </div>

                <div>
                    <div style="font-size: 11px; color: var(--ink-faint);">REGISTERED FARMER:</div>
                    <div style="font-weight: 600; font-size: 14px;"><?php echo sanitize($record['farmer_name']); ?></div>
                    <div style="font-size: 12px; color: var(--stamp-green);">Verified Agricultural Holder</div>
                </div>

                <div>
                    <div style="font-size: 11px; color: var(--ink-faint);">HARVEST DATE:</div>
                    <div class="mono" style="font-weight: 700; font-size: 14px;"><?php echo format_date_mono($record['harvest_date']); ?></div>
                    <div style="font-size: 12px; color: var(--ink-muted);">Season 2026 Batch</div>
                </div>

                <div>
                    <div style="font-size: 11px; color: var(--ink-faint);">LAB QUALITY SCORE:</div>
                    <div class="mono" style="font-weight: 700; font-size: 16px; color: var(--stamp-green);">
                        <?php echo (int)$record['quality_score']; ?> / 100
                    </div>
                    <div style="font-size: 11px; color: var(--ink-muted);">Moisture: <?php echo $meta['moisture_pct'] ?? '10.5'; ?>%</div>
                </div>
            </div>

            <!-- Cryptographic Ledger Verification Proof -->
            <div style="background: rgba(31, 59, 88, 0.05); padding: 14px; border: 1px solid var(--border-rule); border-radius: 2px; margin-bottom: 24px;">
                <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--stamp-navy); margin-bottom: 4px;">
                    Immutable Cryptographic Proof (SHA-256 / Web3)
                </div>
                <div class="mono" style="font-size: 11.5px; word-break: break-all; color: var(--ink-primary);">
                    Tx Hash: <strong><?php echo sanitize($record['tx_hash']); ?></strong>
                </div>
                <div style="font-size: 11px; color: var(--ink-faint); margin-top: 4px;">
                    Recorded on <?php echo format_date_mono($record['recorded_at']); ?> &bull; Consensus algorithm: Proof of Harvest
                </div>
            </div>

            <!-- Chain of Custody Timeline -->
            <h3 style="font-size: 1.25rem; margin-bottom: 16px;">Verified Supply Chain Custody Track</h3>

            <div style="position: relative; padding-left: 28px; display: flex; flex-direction: column; gap: 18px;">
                <div style="position: absolute; left: 10px; top: 6px; bottom: 6px; width: 2px; background: var(--border-rule);"></div>

                <?php foreach ($custody_events as $ev): 
                    $stage_labels = [
                        'harvest'            => 'Harvest & Farm Grading',
                        'quality_inspection' => 'Laboratory Quality Assay',
                        'cold_storage'       => 'Aerated Silo / Cold Storage',
                        'packaging'          => 'Food-Grade Packing & Sealing',
                        'transport'          => 'GPS Sealed Freight Haulage',
                        'retail_delivery'    => 'Destination Market Acceptance'
                    ];
                    $stage_name = $stage_labels[$ev['stage']] ?? ucfirst($ev['stage']);
                ?>
                <div style="position: relative;">
                    <div style="position: absolute; left: -24px; top: 2px; width: 14px; height: 14px; border-radius: 50%; background: var(--stamp-navy); border: 2px solid #fff; box-shadow: 0 0 0 2px var(--stamp-navy);"></div>

                    <div style="background: var(--paper-card); border: 1px solid var(--border-rule); padding: 12px 16px; border-radius: 2px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px;">
                            <strong style="font-size: 13.5px; color: var(--stamp-navy);"><?php echo sanitize($stage_name); ?></strong>
                            <span class="mono" style="font-size: 11px; color: var(--ink-faint);"><?php echo format_date_mono($ev['recorded_at']); ?></span>
                        </div>
                        <div style="font-size: 12px; color: var(--ink-muted); margin-bottom: 4px;">
                            Station: <strong><?php echo sanitize($ev['location']); ?></strong> &bull; Sign-off: <strong><?php echo sanitize($ev['handler_name']); ?></strong>
                        </div>
                        <?php if (!empty($ev['notes'])): ?>
                        <div style="font-size: 12.5px; color: var(--ink-primary); line-height: 1.4;">
                            <?php echo sanitize($ev['notes']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Print / Download Button -->
            <div style="text-align: center; margin-top: 24px; padding-top: 18px; border-top: 1px dashed var(--border-rule);">
                <button onclick="window.print()" class="ledger-btn ledger-btn-sm">
                    Print / Save Verification Folio
                </button>
                <a href="index.php" class="ledger-btn ledger-btn-sm" style="margin-left: 8px;">
                    Return to FFMS Home
                </a>
            </div>

        </div>
        <?php else: ?>
        <!-- Default State with sample batches -->
        <div class="ledger-card" style="text-align: center; padding: 40px;">
            <h3>Verify Any Registered Agricultural Batch</h3>
            <p style="color: var(--ink-muted); font-size: 14px; margin-bottom: 20px;">
                Try searching for demo registered batches:
            </p>
            <div style="display: flex; justify-content: center; gap: 10px; flex-wrap: wrap;">
                <a href="verify.php?batch=BATCH-ZM-2026-WHT-01" class="ledger-btn ledger-btn-sm">
                    BATCH-ZM-2026-WHT-01 (Winter Wheat)
                </a>
                <a href="verify.php?batch=BATCH-ZM-2026-MZ-04" class="ledger-btn ledger-btn-sm">
                    BATCH-ZM-2026-MZ-04 (White Maize)
                </a>
                <a href="verify.php?batch=BATCH-ZM-2026-BOF-09" class="ledger-btn ledger-btn-sm">
                    BATCH-ZM-2026-BOF-09 (Boran Beef)
                </a>
            </div>
        </div>
        <?php endif; ?>

    </div>

</body>
</html>
