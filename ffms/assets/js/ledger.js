/**
 * FFMS: Field Ledger - Minimal Vanilla JavaScript
 * Zero dependencies, pure native browser APIs.
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Mobile Menu Toggle
    const mobileBtn = document.getElementById('mobileMenuBtn');
    const ledgerNav = document.getElementById('ledgerNav');
    if (mobileBtn && ledgerNav) {
        mobileBtn.addEventListener('click', () => {
            ledgerNav.classList.toggle('open');
            const isOpen = ledgerNav.classList.contains('open');
            mobileBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            mobileBtn.textContent = isOpen ? '[ CLOSE MENU ]' : '[ MENU ]';
        });
    }

    // 2. Input Purchases - Dynamic Payment Method Phone Field Toggle
    const paymentSelect = document.getElementById('paymentMethodSelect');
    const phoneGroup = document.getElementById('phoneInputGroup');
    const phoneInput = document.getElementById('paymentPhoneInput');
    const phoneHint = document.getElementById('phoneHint');

    if (paymentSelect && phoneGroup) {
        const updatePhoneVisibility = () => {
            const method = paymentSelect.value;
            if (method === 'mtn_momo') {
                phoneGroup.style.display = 'flex';
                if (phoneInput) {
                    phoneInput.required = true;
                    phoneInput.placeholder = 'e.g. 0966 123456 or 0766 123456';
                }
                if (phoneHint) {
                    phoneHint.textContent = 'Zambian MTN MoMo sandbox test number (096/076). Test number ending in 99 simulates failure.';
                }
            } else if (method === 'airtel_money') {
                phoneGroup.style.display = 'flex';
                if (phoneInput) {
                    phoneInput.required = true;
                    phoneInput.placeholder = 'e.g. 0977 123456 or 0777 123456';
                }
                if (phoneHint) {
                    phoneHint.textContent = 'Zambian Airtel Money sandbox test number (097/077). Test number ending in 00 simulates failure.';
                }
            } else {
                phoneGroup.style.display = 'none';
                if (phoneInput) {
                    phoneInput.required = false;
                    phoneInput.value = '';
                }
            }
        };

        paymentSelect.addEventListener('change', updatePhoneVisibility);
        updatePhoneVisibility(); // Run on initial page load
    }

    // 3. Confirm Ledger Deletions
    const deleteButtons = document.querySelectorAll('.confirm-delete');
    deleteButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            const item = btn.getAttribute('data-item') || 'this ledger record';
            if (!confirm(`Confirm removal: Are you sure you want to permanently strike ${item} from the field ledger?`)) {
                e.preventDefault();
            }
        });
    });

    // 4. Print Ledger Helper
    const printBtns = document.querySelectorAll('.trigger-print');
    printBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            window.print();
        });
    });
});
