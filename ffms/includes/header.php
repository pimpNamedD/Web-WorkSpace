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

    <!-- Main Navigation Header: Two-Tier Physical Binder Folio -->
    <header class="binder-header no-print">
        <!-- Top Tier: Identity, Farmer Folio Pill & Controls -->
        <div class="header-top-tier">
            <div class="header-top-inner">
                <a href="<?php echo $user ? base_url('dashboard.php') : base_url('index.php'); ?>" class="brand-area">
                    <div class="ledger-seal">FL</div>
                    <div>
                        <div class="brand-title">
                            FFMS
                            <span class="brand-title-sub">Field Ledger</span>
                        </div>
                        <div class="brand-tagline">Farm Management &amp; Crop Logbook</div>
                    </div>
                </a>

                <div class="header-top-actions">
                    <?php if ($user): ?>
                    <div class="user-folio-pill">
                        <span class="stamp-badge stamp-green user-district-badge"><?php echo sanitize($user['location_district']); ?></span>
                        <span class="user-name"><?php echo sanitize($user['full_name']); ?></span>
                        <a href="<?php echo base_url('logout.php'); ?>" class="signout-link">[ Sign Out ]</a>
                    </div>

                    <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-expanded="false" aria-label="Toggle navigation">
                        <span class="menu-icon">&equiv;</span> [ MENU ]
                    </button>
                    <?php else: ?>
                    <div class="guest-actions">
                        <a href="<?php echo base_url('login.php'); ?>" class="ledger-btn ledger-btn-sm">Sign In</a>
                        <a href="<?php echo base_url('register.php'); ?>" class="ledger-btn ledger-btn-sm ledger-btn-primary">Open New Ledger</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Bottom Tier: Labeled Binder Divider Tabs -->
        <?php if ($user): ?>
        <nav class="header-nav-bar" id="headerNavBar" aria-label="Farm Ledger Navigation">
            <div class="header-nav-inner">
                <div class="ledger-nav" id="ledgerNav">
                    <a href="<?php echo base_url('dashboard.php'); ?>" class="<?php echo active_nav('dashboard.php'); ?>">
                        <span class="nav-tab-idx">01</span> Dashboard
                    </a>
                    <a href="<?php echo base_url('farms.php'); ?>" class="<?php echo active_nav('farms.php', 'farm_view.php', 'farm_edit.php', 'farm_fields.php'); ?>">
                        <span class="nav-tab-idx">02</span> Farms &amp; Fields
                    </a>
                    <a href="<?php echo base_url('crops.php'); ?>" class="<?php echo active_nav('crops.php', 'crop_add.php', 'crop_edit.php', 'ai_twin.php'); ?>">
                        <span class="nav-tab-idx">03</span> Crops &amp; AI Twin
                    </a>
                    <a href="<?php echo base_url('inventory.php'); ?>" class="<?php echo active_nav('inventory.php', 'inventory_add.php', 'inputs.php', 'input_add.php'); ?>">
                        <span class="nav-tab-idx">04</span> Inventory
                    </a>
                    <a href="<?php echo base_url('livestock.php'); ?>" class="<?php echo active_nav('livestock.php', 'livestock_add.php', 'livestock_edit.php'); ?>">
                        <span class="nav-tab-idx">05</span> Livestock
                    </a>
                    <a href="<?php echo base_url('activities.php'); ?>" class="<?php echo active_nav('activities.php'); ?>">
                        <span class="nav-tab-idx">06</span> Activities
                    </a>
                    <a href="<?php echo base_url('finances.php'); ?>" class="<?php echo active_nav('finances.php', 'finance_add.php'); ?>">
                        <span class="nav-tab-idx">07</span> Finances
                    </a>
                    <a href="<?php echo base_url('carbon.php'); ?>" class="<?php echo active_nav('carbon.php'); ?>">
                        <span class="nav-tab-idx">08</span> Carbon
                    </a>
                    <a href="<?php echo base_url('tokens.php'); ?>" class="<?php echo active_nav('tokens.php'); ?>">
                        <span class="nav-tab-idx">09</span> Tokens
                    </a>
                    <a href="<?php echo base_url('traceability.php'); ?>" class="<?php echo active_nav('traceability.php'); ?>">
                        <span class="nav-tab-idx">10</span> Traceability
                    </a>
                    <a href="<?php echo base_url('workers.php'); ?>" class="<?php echo active_nav('workers.php', 'worker_points.php'); ?>">
                        <span class="nav-tab-idx">11</span> Crew
                    </a>
                    <a href="<?php echo base_url('community.php'); ?>" class="<?php echo active_nav('community.php', 'community_post.php'); ?>">
                        <span class="nav-tab-idx">12</span> Community
                    </a>
                </div>
            </div>
        </nav>
        <?php endif; ?>
    </header>

    <!-- Main Content Container -->
    <main class="ledger-container">
        <?php echo display_flash(); ?>
