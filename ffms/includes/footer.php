<?php
/**
 * FFMS (Field Ledger) - Main Footer Template
 */

declare(strict_types=1);
?>
    </main>

    <!-- Physical Ledger Folio Footer -->
    <footer class="ledger-footer no-print">
        <div class="footer-inner">
            <div>
                <strong>FFMS (Field Ledger)</strong> &mdash; Zambian Smallholder &amp; Commercial Farm Management Folio.
                <br>
                <span style="opacity: 0.7;">Crafted with pure procedural PHP 8 + PDO (MariaDB/MySQL), Vanilla CSS, and zero paid dependencies.</span>
            </div>
            <div style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
                <a href="#" class="trigger-print" title="Print this folio page for physical binder storage">[ Print Ledger Sheet ]</a>
                <span>&bull;</span>
                <a href="https://openweathermap.org/api" target="_blank" rel="noopener">OpenWeather Free Tier</a>
                <span>&bull;</span>
                <a href="https://momodeveloper.mtn.com" target="_blank" rel="noopener">MTN MoMo Sandbox</a>
                <span>&bull;</span>
                <a href="https://developers.airtel.africa" target="_blank" rel="noopener">Airtel Money Sandbox</a>
            </div>
        </div>
    </footer>

    <!-- Minimal Vanilla JS -->
    <script src="<?php echo base_url('assets/js/ledger.js'); ?>"></script>
</body>
</html>
