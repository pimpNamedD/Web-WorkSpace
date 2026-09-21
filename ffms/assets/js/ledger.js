/**
 * FFMS: Field Ledger - Minimal Vanilla JavaScript
 * Zero external dependencies, pure native browser APIs.
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Mobile Navigation Drawer Toggle
    const mobileBtn = document.getElementById('mobileMenuBtn');
    const headerNavBar = document.getElementById('headerNavBar');
    const ledgerNav = document.getElementById('ledgerNav');

    if (mobileBtn && (headerNavBar || ledgerNav)) {
        const toggleMenu = (forceState) => {
            const willOpen = typeof forceState === 'boolean' 
                ? forceState 
                : !(headerNavBar?.classList.contains('is-open') || ledgerNav?.classList.contains('open'));

            if (headerNavBar) headerNavBar.classList.toggle('is-open', willOpen);
            if (ledgerNav) ledgerNav.classList.toggle('open', willOpen);

            mobileBtn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            mobileBtn.innerHTML = willOpen 
                ? '<span class="menu-icon">&times;</span> [ CLOSE ]' 
                : '<span class="menu-icon">&equiv;</span> [ MENU ]';
        };

        mobileBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleMenu();
        });

        // Close when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 1024) {
                const isOpen = headerNavBar?.classList.contains('is-open');
                if (isOpen && !headerNavBar.contains(e.target) && !mobileBtn.contains(e.target)) {
                    toggleMenu(false);
                }
            }
        });

        // Close on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && headerNavBar?.classList.contains('is-open')) {
                toggleMenu(false);
            }
        });
    }

    // 2. Financial Vouchers - Dynamic Category Group Filter (Income vs Expense)
    const typeSelect = document.getElementById('typeSelect');
    const categorySelect = document.getElementById('categorySelect');
    const incomeOptGroup = document.getElementById('incomeOptGroup');
    const expenseOptGroup = document.getElementById('expenseOptGroup');

    if (typeSelect && categorySelect && incomeOptGroup && expenseOptGroup) {
        const filterCategories = () => {
            const flowType = typeSelect.value;
            const isIncome = flowType === 'income';

            // Toggle optgroup display and option disabled states
            incomeOptGroup.style.display = isIncome ? '' : 'none';
            Array.from(incomeOptGroup.options).forEach(opt => opt.disabled = !isIncome);

            expenseOptGroup.style.display = isIncome ? 'none' : '';
            Array.from(expenseOptGroup.options).forEach(opt => opt.disabled = isIncome);

            // If current selected option is disabled, select first visible option
            if (categorySelect.selectedOptions.length === 0 || categorySelect.selectedOptions[0].disabled) {
                const targetGroup = isIncome ? incomeOptGroup : expenseOptGroup;
                if (targetGroup.options.length > 0) {
                    categorySelect.value = targetGroup.options[0].value;
                }
            }
        };

        typeSelect.addEventListener('change', filterCategories);
        filterCategories(); // Initial sync on load
    }

    // 3. Input Purchases - Dynamic Payment Method Phone Field Toggle
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

    // 4. Confirm Ledger Deletions
    const deleteButtons = document.querySelectorAll('.confirm-delete');
    deleteButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            const item = btn.getAttribute('data-item') || 'this ledger record';
            if (!confirm(`Confirm removal: Are you sure you want to permanently strike ${item} from the field ledger?`)) {
                e.preventDefault();
            }
        });
    });

    // 5. Print Ledger Helper
    const printBtns = document.querySelectorAll('.trigger-print');
    printBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            window.print();
        });
    });
});
