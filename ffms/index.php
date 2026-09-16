<?php
/**
 * FFMS (Field Ledger) - Public Entry & Landing Page
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

// If already signed in, go straight to the dashboard
if (current_user()) {
    header('Location: dashboard.php');
    exit;
}

$page_title = 'Agricultural Record Book for Zambian Farming';
include __DIR__ . '/includes/header.php';
?>

<div class="ledger-wrapper">
    <!-- Hero / Title Folio Card -->
    <div class="ledger-card border-green" style="padding: 36px 32px;">
        <div class="card-header-ruled">
            <div>
                <span class="folio-tag">FOLIO № INTRO-01</span>
                <h1 style="margin-top: 8px;">The Tactile Field Ledger for Zambian Farms</h1>
                <p style="font-size: 1.15rem; color: var(--ink-muted); margin-bottom: 0;">
                    A zero-cost, physical-record-book inspired management system engineered for smallholder and commercial holdings in Zambia.
                </p>
            </div>
            <div style="text-align: right;">
                <span class="stamp-badge stamp-green">[ $0 FREE-TIER ]</span>
                <div style="font-family: var(--font-mono); font-size: 11px; margin-top: 6px; color: var(--ink-faint);">
                    NO PAID APIS &bull; NO CLOUD BILLS
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; margin: 24px 0;">
            <div style="border-left: 3px solid var(--stamp-green); padding-left: 14px;">
                <h3 style="font-size: 1.1rem; color: var(--stamp-green);">Tactile "Record Book" Aesthetic</h3>
                <p style="font-size: 13.5px; color: var(--ink-muted);">
                    Crafted to resemble a physical, heavy-bound agricultural ledger. Cream paper ruling, Fraunces serif headings, and ink-stamp status badges replace generic corporate dashboards.
                </p>
            </div>
            <div style="border-left: 3px solid var(--stamp-amber); padding-left: 14px;">
                <h3 style="font-size: 1.1rem; color: var(--stamp-amber);">Zambian Contextualized</h3>
                <p style="font-size: 13.5px; color: var(--ink-muted);">
                    Tailored for Maize, Soya, Sunflower, and Boran cattle across Mkushi, Mazabuka, Chongwe, and Chisamba. Native Kwacha (ZMW) currency and local metric units.
                </p>
            </div>
            <div style="border-left: 3px solid var(--stamp-navy); padding-left: 14px;">
                <h3 style="font-size: 1.1rem; color: var(--stamp-navy);">Mobile Money Sandbox Integration</h3>
                <p style="font-size: 13.5px; color: var(--ink-muted);">
                    Built-in support for MTN MoMo and Airtel Money sandbox "Request to Pay" collections. Real-world simulation and audit trails for input purchases without costly SMS gateways.
                </p>
            </div>
        </div>

        <div style="display: flex; gap: 14px; align-items: center; flex-wrap: wrap; margin-top: 10px; padding-top: 18px; border-top: 1px dashed var(--border-rule);">
            <a href="register.php" class="ledger-btn ledger-btn-primary" style="padding: 10px 24px; font-size: 14px;">
                Open Your Farm Ledger Folio &rarr;
            </a>
            <a href="login.php" class="ledger-btn" style="padding: 10px 20px;">
                Sign In With Demo Account
            </a>
            <span style="font-family: var(--font-mono); font-size: 12px; color: var(--ink-muted);">
                Demo credentials: <code>dalitso@fieldledger.zm</code> / <code>password123</code>
            </span>
        </div>
    </div>

    <!-- Ledger Modules Overview Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px;">
        <!-- Module 1 & 2 -->
        <div class="ledger-card border-green">
            <div class="card-header-ruled">
                <span class="folio-tag">FOLIO SEC. 01 &bull; CROPS &amp; LIVESTOCK</span>
                <span class="stamp-badge stamp-green">[ ACTIVE RECORDS ]</span>
            </div>
            <h3>Core Farm Management</h3>
            <p style="font-size: 13.5px; color: var(--ink-muted);">
                Record crop rotations from planting to harvest, track expected versus actual yield in kilograms, monitor beef and dairy herd health, and keep dated chronological farm activity logs.
            </p>
            <div class="mono" style="font-size: 12px; color: var(--stamp-green); font-weight: 600;">
                Maize &bull; Soya Beans &bull; Boran Cattle &bull; Boer Goats &bull; Broilers
            </div>
        </div>

        <!-- Module 3 -->
        <div class="ledger-card border-navy">
            <div class="card-header-ruled">
                <span class="folio-tag">FOLIO SEC. 02 &bull; METEOROLOGY</span>
                <span class="stamp-badge stamp-navy">[ 30-MIN CACHED ]</span>
            </div>
            <h3>Weather Intelligence (OpenWeatherMap)</h3>
            <p style="font-size: 13.5px; color: var(--ink-muted);">
                Localized temperature, humidity, wind, and precipitation readings for each farm location. Cached in MariaDB for 30 minutes to preserve free API limits with graceful offline degradation.
            </p>
            <div class="mono" style="font-size: 12px; color: var(--stamp-navy); font-weight: 600;">
                Free tier 1,000 calls/day &bull; Zero credit card needed
            </div>
        </div>

        <!-- Module 4 -->
        <div class="ledger-card border-ochre">
            <div class="card-header-ruled">
                <span class="folio-tag">FOLIO SEC. 03 &bull; INPUT TRACKING</span>
                <span class="stamp-badge stamp-ochre">[ MTN &amp; AIRTEL ]</span>
            </div>
            <h3>Mobile Money Purchases</h3>
            <p style="font-size: 13.5px; color: var(--ink-muted);">
                Track seed, fertilizer, chemical, and feed purchases in Zambian Kwacha (ZMW). Trigger sandbox payment prompts to simulate instant checkout via MTN MoMo and Airtel Money.
            </p>
            <div class="mono" style="font-size: 12px; color: var(--stamp-ochre); font-weight: 600;">
                Recorded provider reference numbers &bull; Full expense ledger
            </div>
        </div>

        <!-- Module 5 -->
        <div class="ledger-card border-amber">
            <div class="card-header-ruled">
                <span class="folio-tag">FOLIO SEC. 04 &bull; WORKER MERIT</span>
                <span class="stamp-badge stamp-amber">[ RECOGNITION ]</span>
            </div>
            <h3>Worker Points Ledger</h3>
            <p style="font-size: 13.5px; color: var(--ink-muted);">
                Non-monetary merit and recognition system for farm foremen, herdsmen, and tractor operators. Award or deduct points with reasons and maintain a transparent audit log.
            </p>
            <div class="mono" style="font-size: 12px; color: var(--stamp-amber); font-weight: 600;">
                100% DB-backed counter &bull; No speculative tokens or crypto
            </div>
        </div>

        <!-- Module 6 -->
        <div class="ledger-card border-red">
            <div class="card-header-ruled">
                <span class="folio-tag">FOLIO SEC. 05 &bull; PEER NETWORK</span>
                <span class="stamp-badge stamp-dark">[ COMMUNITY ]</span>
            </div>
            <h3>Farmer Peer Exchange</h3>
            <p style="font-size: 13.5px; color: var(--ink-muted);">
                A dedicated community board for Zambian growers to discuss market prices, fertilizer availability, pest outbreaks (fall armyworm, tick-borne diseases), and equipment maintenance.
            </p>
            <div class="mono" style="font-size: 12px; color: var(--stamp-red); font-weight: 600;">
                Regional district tags &bull; Threaded discussions
            </div>
        </div>

        <!-- Architecture Callout -->
        <div class="ledger-card" style="background: var(--paper-subtle); border-left-color: var(--binder-spine);">
            <div class="card-header-ruled">
                <span class="folio-tag">ARCHITECTURE PHILOSOPHY</span>
                <span class="stamp-badge stamp-neutral">[ SUSTAINABLE ]</span>
            </div>
            <h3>Built for Real African Farms</h3>
            <p style="font-size: 13.5px; color: var(--ink-muted);">
                Intentionally designed without high-cost SaaS dependencies, blockchain overhead, or enterprise subscriptions that fail smallholders. Works reliably on any local XAMPP setup or free web host.
            </p>
            <div style="margin-top: 10px;">
                <a href="register.php" class="ledger-btn ledger-btn-sm ledger-btn-primary">Get Started Now</a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
