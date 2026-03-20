/**
 * Booking Step 4 – Additional Services Selection
 *
 * Supports two modes:
 *  1. Regular services  – checkbox-based selection (existing behaviour)
 *  2. Services with sub-services – photo-based drill-down selection flow:
 *       Main services list → sub-service list → design photo grid → auto-back
 */

document.addEventListener('DOMContentLoaded', function () {

    // ── Guard ────────────────────────────────────────────────────────────────
    if (typeof baseTotal === 'undefined') {
        window.location.href = baseUrl + '/booking-step3.php';
        return;
    }

    // ── State ────────────────────────────────────────────────────────────────
    // selectedDesigns: { sub_service_id: { design_id, price, name, sub_service_id, service_id } }
    const selectedDesigns = {};
    let currentServiceId    = null;   // service being navigated
    let currentSubServiceId = null;   // sub-service whose designs are shown

    // ── Build lookup maps from PHP-injected JSON ──────────────────────────────
    const servicesById     = {};  // id → service object (with sub_services)
    const subServicesById  = {};  // id → sub_service object (with designs)
    const designsById      = {};  // id → design object

    if (typeof servicesData !== 'undefined') {
        servicesData.forEach(function (svc) {
            servicesById[svc.id] = svc;
            if (svc.sub_services) {
                svc.sub_services.forEach(function (ss) {
                    subServicesById[ss.id] = ss;
                    ss.service_id = svc.id;
                    if (ss.designs) {
                        ss.designs.forEach(function (d) {
                            designsById[d.id] = d;
                            d.sub_service_id = ss.id;
                            d.service_id     = svc.id;
                        });
                    }
                });
            }
        });
    }

    // ── View switching helpers ────────────────────────────────────────────────
    const viewServices    = document.getElementById('view-services');
    const viewSubServices = document.getElementById('view-sub-services');
    const viewDesigns     = document.getElementById('view-designs');

    function showView(view) {
        [viewServices, viewSubServices, viewDesigns].forEach(function (v) {
            if (v) v.style.display = 'none';
        });
        if (view) {
            view.style.display = '';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    // ── Currency formatter matching server-side formatCurrency() ─────────────
    function formatPrice(amount) {
        const num = parseFloat(amount) || 0;
        const cur = (typeof currency !== 'undefined') ? currency : 'NPR';
        return cur + ' ' + num.toLocaleString('en-NP', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // ── Calculate running total and update display ────────────────────────────
    function recalculateTotal() {
        // Regular service checkboxes
        let regularTotal = 0;
        document.querySelectorAll('.service-checkbox:checked').forEach(function (cb) {
            regularTotal += parseFloat(cb.dataset.price) || 0;
        });

        // Design selections
        let designTotal = 0;
        Object.values(selectedDesigns).forEach(function (d) {
            designTotal += parseFloat(d.price) || 0;
        });

        updateTotalCost(baseTotal + regularTotal + designTotal);
    }

    // ── Update hidden inputs so form includes selected designs ────────────────
    function syncDesignInputs() {
        const container = document.getElementById('selected-designs-inputs');
        if (!container) return;
        container.innerHTML = '';
        Object.values(selectedDesigns).forEach(function (d) {
            const input = document.createElement('input');
            input.type  = 'hidden';
            input.name  = 'selected_designs[' + d.sub_service_id + ']';
            input.value = d.design_id;
            container.appendChild(input);
        });
    }

    // ── Update service card summary text (main view) ──────────────────────────
    function updateServiceSummary(serviceId) {
        const svc = servicesById[serviceId];
        if (!svc || !svc.sub_services) return;

        const parts = [];
        svc.sub_services.forEach(function (ss) {
            const sel = selectedDesigns[ss.id];
            if (sel) {
                parts.push(ss.name + ': ' + sel.name + ' (' + formatPrice(sel.price) + ')');
            }
        });

        const desktopEl = document.getElementById('service-summary-' + serviceId);
        if (desktopEl)   desktopEl.textContent   = parts.join(' • ');

        const mobileEl  = document.getElementById('service-summary-mob-' + serviceId);
        if (mobileEl)    mobileEl.textContent    = parts.join(' • ');

        // Highlight drilldown card if any selections made
        const card = document.querySelector('.service-drilldown-card[data-service-id="' + serviceId + '"]');
        if (card) {
            card.classList.toggle('border-success', parts.length > 0);
        }
    }

    // ── Navigate INTO a service's sub-services ────────────────────────────────
    window.openSubServicesView = function (serviceId) {
        currentServiceId = serviceId;
        const svc = servicesById[serviceId];
        if (!svc) return;

        document.getElementById('sub-services-title').textContent    = svc.name;
        document.getElementById('sub-services-subtitle').textContent = svc.description || '';

        const list = document.getElementById('sub-services-list');
        list.innerHTML = '';

        if (!svc.sub_services || svc.sub_services.length === 0) {
            list.innerHTML = '<div class="col-12"><div class="alert alert-info"><i class="fas fa-info-circle"></i> No sub-services configured.</div></div>';
        } else {
            svc.sub_services.forEach(function (ss) {
                const sel    = selectedDesigns[ss.id];
                const isSelected = !!sel;

                const col  = document.createElement('div');
                col.className = 'col-md-6';

                col.innerHTML =
                    '<div class="card h-100 sub-service-card ' + (isSelected ? 'border-success' : '') + '" ' +
                         'style="cursor:pointer;" onclick="openDesignsView(' + ss.id + ')">' +
                        '<div class="card-body d-flex justify-content-between align-items-center">' +
                            '<div>' +
                                '<h5 class="mb-1">' + escapeHtml(ss.name) + '</h5>' +
                                (ss.description ? '<p class="text-muted small mb-1">' + escapeHtml(ss.description) + '</p>' : '') +
                                (isSelected
                                    ? '<div class="text-success small"><i class="fas fa-check-circle"></i> ' +
                                        escapeHtml(sel.name) + ' \u2013 ' + formatPrice(sel.price) + '</div>'
                                    : '<div class="text-muted small">Tap to choose a design</div>') +
                            '</div>' +
                            '<i class="fas fa-chevron-right text-muted ms-3"></i>' +
                        '</div>' +
                    '</div>';

                list.appendChild(col);
            });
        }

        showView(viewSubServices);
    };

    // ── Navigate BACK to services list ────────────────────────────────────────
    window.backToServices = function () {
        if (currentServiceId !== null) {
            updateServiceSummary(currentServiceId);
        }
        showView(viewServices);
    };

    // ── Navigate INTO a sub-service's designs ─────────────────────────────────
    window.openDesignsView = function (subServiceId) {
        currentSubServiceId = subServiceId;
        const ss = subServicesById[subServiceId];
        if (!ss) return;

        document.getElementById('designs-title').textContent    = ss.name;
        document.getElementById('designs-subtitle').textContent = 'Tap a photo to select it';

        const grid = document.getElementById('designs-grid');
        grid.innerHTML = '';

        if (!ss.designs || ss.designs.length === 0) {
            grid.innerHTML = '<div class="col-12"><div class="alert alert-info"><i class="fas fa-info-circle"></i> No designs available.</div></div>';
        } else {
            const currentSelection = selectedDesigns[subServiceId];

            ss.designs.forEach(function (d) {
                const isChosen = currentSelection && currentSelection.design_id == d.id;

                const col = document.createElement('div');
                col.className = 'col-6 col-md-3';

                const photoHtml = d.photo
                    ? '<img src="' + escapeHtml(uploadUrl + '/' + d.photo) + '" ' +
                        'alt="' + escapeHtml(d.name) + '" ' +
                        'class="card-img-top design-photo" style="height:160px;object-fit:cover;">'
                    : '<div class="d-flex align-items-center justify-content-center bg-light" style="height:160px;">' +
                        '<i class="fas fa-image fa-3x text-muted"></i></div>';

                col.innerHTML =
                    '<div class="card design-card h-100 ' + (isChosen ? 'border-success border-3 selected-design' : '') + '" ' +
                         'style="cursor:pointer;" onclick="selectDesign(' + d.id + ')">' +
                        photoHtml +
                        '<div class="card-body p-2 text-center">' +
                            (isChosen ? '<i class="fas fa-check-circle text-success"></i> ' : '') +
                            '<div class="fw-semibold small">' + escapeHtml(d.name) + '</div>' +
                            '<div class="text-success small fw-bold">' + formatPrice(d.price) + '</div>' +
                            (d.description ? '<div class="text-muted small mt-1">' + escapeHtml(d.description) + '</div>' : '') +
                        '</div>' +
                    '</div>';

                grid.appendChild(col);
            });
        }

        showView(viewDesigns);
    };

    // ── Select a design and auto-navigate ────────────────────────────────────
    window.selectDesign = function (designId) {
        const d  = designsById[designId];
        if (!d) return;

        // Record selection
        selectedDesigns[d.sub_service_id] = {
            design_id      : d.id,
            price          : parseFloat(d.price) || 0,
            name           : d.name,
            sub_service_id : d.sub_service_id,
            service_id     : d.service_id
        };

        syncDesignInputs();
        recalculateTotal();

        // Determine next sub-service (of same parent service) without a selection yet
        const svc = servicesById[d.service_id];
        let nextSS = null;
        if (svc && svc.sub_services) {
            for (let i = 0; i < svc.sub_services.length; i++) {
                const ss = svc.sub_services[i];
                if (!selectedDesigns[ss.id]) {
                    nextSS = ss;
                    break;
                }
            }
        }

        if (nextSS) {
            // Auto-navigate to next sub-service
            openDesignsView(nextSS.id);
        } else {
            // All sub-services selected → back to main services list
            updateServiceSummary(d.service_id);
            showView(viewServices);
        }
    };

    // ── Navigate BACK from designs to sub-services ────────────────────────────
    window.backToSubServices = function () {
        if (currentServiceId !== null) {
            openSubServicesView(currentServiceId);
        } else {
            showView(viewServices);
        }
    };

    // ── Regular checkbox handler ──────────────────────────────────────────────
    document.querySelectorAll('.service-checkbox').forEach(function (cb) {
        cb.addEventListener('change', recalculateTotal);
    });

    // ── Service search filter ─────────────────────────────────────────────────
    const searchInput = document.getElementById('serviceSearchInput');
    const clearBtn    = document.getElementById('serviceSearchClear');
    const noResults   = document.getElementById('serviceSearchNoResults');

    if (searchInput) {
        let debounceTimer = null;

        function filterServices() {
            const term = searchInput.value.trim().toLowerCase();

            const desktopCards = Array.from(document.querySelectorAll('.d-none.d-md-block [data-service-name]'));
            const mobileCards  = Array.from(document.querySelectorAll('.d-md-none [data-service-name]'));

            desktopCards.forEach(function (card) {
                const name = (card.getAttribute('data-service-name') || '').toLowerCase();
                card.style.display = name.includes(term) ? '' : 'none';
            });

            mobileCards.forEach(function (card) {
                const name = (card.getAttribute('data-service-name') || '').toLowerCase();
                card.style.display = name.includes(term) ? '' : 'none';
            });

            const categorySections = Array.from(document.querySelectorAll('.service-category-section'));
            let totalVisible = 0;

            categorySections.forEach(function (section) {
                const visibleDesktop = section.querySelectorAll('.d-none.d-md-block [data-service-name]');
                const anyDesktop = Array.from(visibleDesktop).some(function (c) { return c.style.display !== 'none'; });

                const visibleMobile = section.querySelectorAll('.d-md-none [data-service-name]');
                const anyMobile = Array.from(visibleMobile).some(function (c) { return c.style.display !== 'none'; });

                const anyVisible = anyDesktop || anyMobile;
                section.style.display = anyVisible ? '' : 'none';
                if (anyVisible) totalVisible++;
            });

            if (noResults) noResults.style.display = (totalVisible === 0) ? 'block' : 'none';
            if (clearBtn)  clearBtn.style.display  = (term.length > 0) ? 'inline-block' : 'none';
        }

        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(filterServices, 200);
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                searchInput.value = '';
                filterServices();
                searchInput.focus();
            });
        }
    }

    // ── Utility: escape HTML for JS-generated markup ─────────────────────────
    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g,  '&amp;')
            .replace(/</g,  '&lt;')
            .replace(/>/g,  '&gt;')
            .replace(/"/g,  '&quot;')
            .replace(/'/g,  '&#39;');
    }

});
