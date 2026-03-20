/**
 * Booking Step 4 – Additional Services Selection
 *
 * Supports two modes:
 *  1. Regular services  – checkbox-based selection (existing behaviour)
 *  2. Services with sub-services – photo-based design selection flow:
 *       Main services list → combined sub-services & designs view → back to services
 *
 * Navigation flow:
 *   View 1 (Services) → click service card
 *   View 2 (Design Selection) → tap a design photo → selection shown inline → "Done" button
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
    const breadcrumb      = document.getElementById('booking-breadcrumb');
    const bcServiceName   = document.getElementById('bc-service-name');

    function showView(view) {
        [viewServices, viewSubServices].forEach(function (v) {
            if (v) v.style.display = 'none';
        });
        if (view) {
            view.style.display = '';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        // Show breadcrumb only when drilling down
        if (breadcrumb) {
            breadcrumb.style.display = (view === viewServices) ? 'none' : '';
        }
    }

    // ── Update breadcrumb ────────────────────────────────────────────────────
    function updateBreadcrumb(serviceName) {
        if (bcServiceName) {
            if (serviceName) {
                bcServiceName.textContent = serviceName;
                bcServiceName.style.display = '';
            } else {
                bcServiceName.style.display = 'none';
            }
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

    // ── Update the sub-services view progress counter and Done button ─────────
    function updateSubServiceProgress(serviceId) {
        const svc = servicesById[serviceId];
        if (!svc || !svc.sub_services) return;

        const total    = svc.sub_services.length;
        const selected = svc.sub_services.filter(function (ss) { return !!selectedDesigns[ss.id]; }).length;

        const progressEl = document.getElementById('sub-service-progress');
        if (progressEl) {
            progressEl.textContent = selected + ' of ' + total + ' selected';
            progressEl.style.display = '';
            progressEl.className = 'badge ms-3 fs-6 ' + (selected === total ? 'bg-success' : 'bg-secondary');
        }

        const doneBtn = document.getElementById('sub-services-done-btn');
        if (doneBtn) {
            doneBtn.style.display = selected > 0 ? '' : 'none';
            if (selected === total) {
                doneBtn.innerHTML = '<i class="fas fa-check-circle me-1"></i> Done – All Selected';
            } else {
                doneBtn.innerHTML = '<i class="fas fa-check me-1"></i> Done';
            }
        }
    }

    // ── Build a single sub-service section with its design grid ───────────────
    function buildSubServiceSection(ss) {
        const sel = selectedDesigns[ss.id];

        let html = '<div class="mb-4" id="ss-section-' + ss.id + '">';
        html += '<div class="d-flex align-items-center flex-wrap mb-2">';
        html += '<h5 class="mb-0 me-2">' + escapeHtml(ss.name) + '</h5>';
        if (sel) {
            html += '<span class="badge bg-success small">'
                  + '<i class="fas fa-check-circle me-1"></i>'
                  + escapeHtml(sel.name) + ' – ' + escapeHtml(formatPrice(sel.price))
                  + '</span>';
        } else {
            html += '<span class="badge bg-light text-muted border small">Choose a design</span>';
        }
        html += '</div>';

        if (ss.description) {
            html += '<p class="text-muted small mb-2">' + escapeHtml(ss.description) + '</p>';
        }

        if (!ss.designs || ss.designs.length === 0) {
            html += '<div class="alert alert-info small py-2 mb-0">'
                  + '<i class="fas fa-info-circle me-1"></i>No designs available.</div>';
        } else {
            html += '<div class="row g-2">';
            ss.designs.forEach(function (d) {
                const isChosen = sel && sel.design_id == d.id;
                const photoHtml = d.photo
                    ? '<img src="' + escapeHtml(uploadUrl + '/' + d.photo) + '" '
                        + 'alt="' + escapeHtml(d.name) + '" '
                        + 'class="card-img-top" style="height:120px;object-fit:cover;">'
                    : '<div class="d-flex align-items-center justify-content-center bg-light" style="height:120px;">'
                        + '<i class="fas fa-image fa-2x text-muted"></i></div>';

                html += '<div class="col-6 col-md-3">';
                html += '<div class="card h-100 design-card '
                      + (isChosen ? 'border-success border-3 selected-design' : '')
                      + '" style="cursor:pointer;" onclick="selectDesign(' + d.id + ')">';
                html += photoHtml;
                html += '<div class="card-body p-2 text-center">';
                if (isChosen) html += '<i class="fas fa-check-circle text-success me-1"></i>';
                html += '<div class="fw-semibold small">' + escapeHtml(d.name) + '</div>';
                html += '<div class="text-success small fw-bold">' + escapeHtml(formatPrice(d.price)) + '</div>';
                if (d.description) {
                    html += '<div class="text-muted small mt-1">' + escapeHtml(d.description) + '</div>';
                }
                html += '</div></div></div>';
            });
            html += '</div>';
        }
        html += '</div>';
        return html;
    }

    // ── Navigate INTO a service's design selection view ───────────────────────
    window.openSubServicesView = function (serviceId) {
        currentServiceId = serviceId;
        const svc = servicesById[serviceId];
        if (!svc) return;

        document.getElementById('sub-services-title').textContent    = svc.name;
        document.getElementById('sub-services-subtitle').textContent = svc.description || '';

        // Update breadcrumb
        updateBreadcrumb(svc.name);

        const list = document.getElementById('sub-services-list');
        list.innerHTML = '';

        if (!svc.sub_services || svc.sub_services.length === 0) {
            list.innerHTML = '<div class="col-12"><div class="alert alert-info"><i class="fas fa-info-circle"></i> No sub-services configured.</div></div>';
        } else {
            svc.sub_services.forEach(function (ss) {
                const col = document.createElement('div');
                col.className = 'col-12';
                col.innerHTML = buildSubServiceSection(ss);
                list.appendChild(col);
            });
        }

        updateSubServiceProgress(serviceId);
        showView(viewSubServices);
    };

    // ── Navigate BACK to services list ────────────────────────────────────────
    window.backToServices = function () {
        if (currentServiceId !== null) {
            updateServiceSummary(currentServiceId);
        }
        updateBreadcrumb(null);
        showView(viewServices);
    };

    // ── Select a design (in-place update, no navigation needed) ──────────────
    window.selectDesign = function (designId) {
        const d = designsById[designId];
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

        // Re-render just this sub-service section in-place to reflect the new selection
        const ss = subServicesById[d.sub_service_id];
        if (ss) {
            const listEl = document.getElementById('sub-services-list');
            if (listEl) {
                const sectionEl = listEl.querySelector('#ss-section-' + d.sub_service_id);
                if (sectionEl) {
                    const parentCol = sectionEl.parentElement;
                    if (parentCol) {
                        parentCol.innerHTML = buildSubServiceSection(ss);
                    }
                }
            }
        }

        updateSubServiceProgress(d.service_id);
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
