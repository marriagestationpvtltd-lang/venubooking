/**
 * Booking Step 4 – Service Packages Selection
 *
 * Handles package checkbox selection, group accordion behaviour,
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

    // ── Update the collapsed summary for a group item ─────────────────────────
    function updateGroupSummary(groupItem) {
        const checked = groupItem.querySelectorAll('.package-checkbox:checked');
        const summaryInline = groupItem.querySelector('.pkg-group-summary-inline');
        const summaryText   = groupItem.querySelector('.pkg-group-summary-text');
        const summaryCost   = groupItem.querySelector('.pkg-group-summary-cost');
        const divider       = groupItem.querySelector('.pkg-group-divider');

        if (!summaryInline || !summaryText) return;

        if (checked.length === 0) {
            summaryInline.classList.remove('visible');
            if (divider) divider.classList.add('d-none');
        } else {
            // Build label: one item → item name; multiple → "N selected"
            if (checked.length === 1) {
                summaryText.textContent = checked[0].dataset.pkgName || '';
            } else {
                summaryText.textContent = checked.length + ' selected';
            }

            // Extra cost badge
            let extraCost = 0;
            checked.forEach(function (cb) {
                extraCost += parseFloat(cb.dataset.price) || 0;
            });

            if (extraCost > 0) {
                summaryCost.textContent = '+' + formatCurrency(extraCost);
                summaryCost.classList.remove('d-none');
            } else {
                summaryCost.classList.add('d-none');
            }

            summaryInline.classList.add('visible');
            if (divider) divider.classList.remove('d-none');
        }
    }

    // ── Toggle a group open; collapse all others ──────────────────────────────
    function openGroup(targetItem) {
        document.querySelectorAll('.pkg-group-item').forEach(function (item) {
            if (item === targetItem) return;

            if (item.classList.contains('pkg-group-active')) {
                item.classList.remove('pkg-group-active');
                const hdr = item.querySelector('.pkg-group-header');
                if (hdr) hdr.setAttribute('aria-expanded', 'false');
                updateGroupSummary(item);
            }
        });

        const isActive = targetItem.classList.contains('pkg-group-active');

        if (isActive) {
            // Click on already-open group → collapse it
            targetItem.classList.remove('pkg-group-active');
            const hdr = targetItem.querySelector('.pkg-group-header');
            if (hdr) hdr.setAttribute('aria-expanded', 'false');
            updateGroupSummary(targetItem);
        } else {
            targetItem.classList.add('pkg-group-active');
            const hdr = targetItem.querySelector('.pkg-group-header');
            if (hdr) hdr.setAttribute('aria-expanded', 'true');
            // Hide summary while expanded (full list is visible)
            const summaryInline = targetItem.querySelector('.pkg-group-summary-inline');
            const divider       = targetItem.querySelector('.pkg-group-divider');
            if (summaryInline) summaryInline.classList.remove('visible');
            if (divider) divider.classList.add('d-none');
        }
    }

    // ── Group header click handler ────────────────────────────────────────────
    document.querySelectorAll('.pkg-group-header').forEach(function (header) {
        header.addEventListener('click', function () {
            const groupItem = header.closest('.pkg-group-item');
            if (groupItem) openGroup(groupItem);
        });
    });

    // ── Package checkbox handler ──────────────────────────────────────────────
    document.querySelectorAll('.package-checkbox').forEach(function (cb) {
        cb.addEventListener('change', function () {
            recalculateTotal();

            // Highlight card based on checked state
            const card = cb.closest('.package-select-card');
            if (card) {
                if (cb.checked) {
                    card.classList.add('pkg-card-selected');
                } else {
                    card.classList.remove('pkg-card-selected');
                }
            }
        });
    });

});
