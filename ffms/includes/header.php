<?php
/**
 * FFMS (Field Ledger) - Main Header Template
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

$user = current_user();
$page_title = $page_title ?? 'Field Ledger Folio';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($page_title); ?> — FFMS Field Ledger</title>
    
    <!-- Authentic Typography: Fraunces (Serif), Work Sans (Body), IBM Plex Mono (Ledger/Code) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;0,9..144,700;1,9..144,400&family=IBM+Plex+Mono:wght@400;500;600;700&family=Work+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    
    <!-- Ledger Stylesheet -->
    <link rel="stylesheet" href="<?php echo base_url('assets/css/ledger.css'); ?>">
</head>
<body>

    <!-- Physical Binder Folio Top Bar -->
    <div class="binder-topbar no-print">
        <div>
            <span>№ FL-ZM-2026</span>
            <span style="margin: 0 8px; opacity: 0.5;">|</span>
            <span>REPUBLIC OF ZAMBIA AGRICULTURAL LEDGER</span>
        </div>
        <div>
            <span class="mono"><?php echo strtoupper(date('l, d M Y')); ?></span>
            <span style="margin: 0 8px; opacity: 0.5;">|</span>
            <span>$0 FREE-TIER ARCHITECTURE</span>
        </div>
    </div>

    <!-- Main Navigation Header -->
    <header class="binder-header no-print">
        <div class="header-inner">
            <a href="<?php echo $user ? base_url('dashboard.php') : base_url('index.php'); ?>" class="brand-area">
                <div class="ledger-seal">FL</div>
                <div>
                    <div class="brand-title">
                        FFMS
                        <span style="font-size: 0.95rem; font-weight: 400; opacity: 0.7;">Field Ledger</span>
                    </div>
                    <div class="brand-tagline">Farm Management &amp; Crop Logbook</div>
                </div>
            </a>

            <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-expanded="false" aria-label="Toggle navigation">
                [ MENU ]
            </button>

            <?php if ($user): ?>
            <nav class="ledger-nav" id="ledgerNav">
                <a href="<?php echo base_url('dashboard.php'); ?>" class="<?php echo active_nav('dashboard.php'); ?>">Dashboard</a>
                <a href="<?php echo base_url('farms.php'); ?>" class="<?php echo active_nav('farms.php'); ?> <?php echo active_nav('farm_view.php'); ?> <?php echo active_nav('farm_edit.php'); ?> <?php echo active_nav('farm_fields.php'); ?>">Farms &amp; Fields</a>
                <a href="<?php echo base_url('crops.php'); ?>" class="<?php echo active_nav('crops.php'); ?> <?php echo active_nav('crop_add.php'); ?> <?php echo active_nav('crop_edit.php'); ?> <?php echo active_nav('ai_twin.php'); ?>">Crops &amp; AI Twin</a>
                <a href="<?php echo base_url('inventory.php'); ?>" class="<?php echo active_nav('inventory.php'); ?> <?php echo active_nav('inventory_add.php'); ?> <?php echo active_nav('inputs.php'); ?> <?php echo active_nav('input_add.php'); ?>">Inventory</a>
                <a href="<?php echo base_url('livestock.php'); ?>" class="<?php echo active_nav('livestock.php'); ?> <?php echo active_nav('livestock_add.php'); ?> <?php echo active_nav('livestock_edit.php'); ?>">Livestock</a>
                <a href="<?php echo base_url('finances.php'); ?>" class="<?php echo active_nav('finances.php'); ?> <?php echo active_nav('finance_add.php'); ?>">Finances</a>
                <a href="<?php echo base_url('carbon.php'); ?>" class="<?php echo active_nav('carbon.php'); ?>">Carbon</a>
                <a href="<?php echo base_url('tokens.php'); ?>" class="<?php echo active_nav('tokens.php'); ?>">Tokens</a>
                <a href="<?php echo base_url('traceability.php'); ?>" class="<?php echo active_nav('traceability.php'); ?>">Traceability</a>
                <a href="<?php echo base_url('workers.php'); ?>" class="<?php echo active_nav('workers.php'); ?> <?php echo active_nav('worker_points.php'); ?>">Crew</a>
                <a href="<?php echo base_url('community.php'); ?>" class="<?php echo active_nav('community.php'); ?> <?php echo active_nav('community_post.php'); ?>">Community</a>
            </nav>

            <div class="user-folio-pill">
                <span class="stamp-badge stamp-green" style="transform:none; font-size:10px;"><?php echo sanitize($user['location_district']); ?></span>
                <span class="user-name"><?php echo sanitize($user['full_name']); ?></span>
                <a href="<?php echo base_url('logout.php'); ?>" style="color: var(--stamp-red); font-size: 11px; margin-left: 6px;">[ Sign Out ]</a>
            </div>
            <?php else: ?>
            <div style="display: flex; gap: 10px; align-items: center;">
                <a href="<?php echo base_url('login.php'); ?>" class="ledger-btn ledger-btn-sm">Sign In</a>
                <a href="<?php echo base_url('register.php'); ?>" class="ledger-btn ledger-btn-sm ledger-btn-primary">Open New Ledger</a>
            </div>
            <?php endif; ?>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="ledger-container">
        <?php echo display_flash(); ?>
