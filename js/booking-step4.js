/**
 * Booking Step 4 – Service Packages Selection
 *
 * Handles package checkbox selection, category filter tabs,
 * and running-total recalculation.
 */

document.addEventListener('DOMContentLoaded', function () {

    // ── Guard ────────────────────────────────────────────────────────────────
    if (typeof baseTotal === 'undefined') {
        window.location.href = baseUrl + '/booking-step3.php';
        return;
    }

    // ── Currency formatter matching server-side formatCurrency() ─────────────
    function formatPrice(amount) {
        const num = parseFloat(amount) || 0;
        const cur = (typeof currency !== 'undefined') ? currency : 'NPR';
        return cur + ' ' + num.toLocaleString('en-NP', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // ── Calculate running total and update display ────────────────────────────
    function recalculateTotal() {
        let packageTotal = 0;
        document.querySelectorAll('.package-checkbox:checked').forEach(function (cb) {
            packageTotal += parseFloat(cb.dataset.price) || 0;
        });

        const rate = (typeof taxRate !== 'undefined') ? taxRate : 0;
        const total = (baseTotal + packageTotal) * (1 + rate / 100);
        const totalCostEl = document.getElementById('totalCost');
        if (totalCostEl) totalCostEl.textContent = formatCurrency(total);
    }

    // ── Package checkbox handler ──────────────────────────────────────────────
    document.querySelectorAll('.package-checkbox').forEach(function (cb) {
        cb.addEventListener('change', recalculateTotal);
    });

    // ── Package category filter buttons ──────────────────────────────────────
    document.querySelectorAll('.pkg-category-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const targetId = btn.dataset.pkgCat;

            // Update button active styles
            document.querySelectorAll('.pkg-category-btn').forEach(function (b) {
                b.classList.remove('btn-success');
                b.classList.add('btn-outline-secondary');
            });
            btn.classList.remove('btn-outline-secondary');
            btn.classList.add('btn-success');

            // Show the selected panel, hide others
            document.querySelectorAll('.pkg-category-panel').forEach(function (panel) {
                if (panel.id === targetId) {
                    panel.classList.remove('d-none');
                } else {
                    panel.classList.add('d-none');
                }
            });
        });
    });

});
