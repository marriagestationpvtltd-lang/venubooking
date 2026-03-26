/**
 * Booking Step 5 – Additional Services Selection
 *
 * Supports two modes:
 *  1. Regular services  – checkbox-based selection (existing behaviour)
 *  2. Services with designs – photo-based design selection flow:
 *       Main services list → design photo grid → auto-back to services on selection
 *
 * Also handles vendor selection:
 *  When a service with a vendor_type_slug is checked a vendor selection modal
 *  is shown so the user can optionally assign a specific vendor.
 *
 * Navigation flow:
 *   View 1 (Services) → click service card
 *   View 2 (Design Selection) → tap a design photo → auto-back to View 1
 */

document.addEventListener('DOMContentLoaded', function () {

    // ── Guard ────────────────────────────────────────────────────────────────
    if (typeof baseTotal === 'undefined') {
        window.location.href = baseUrl + '/booking-step4.php';
        return;
    }

    // ── State ────────────────────────────────────────────────────────────────
    // selectedDesigns: { service_id: { design_id, price, name, service_id } }
    const selectedDesigns = {};
    let currentServiceId    = null;   // service being navigated

    // vendorForService: { service_id: vendor_id }  (0 = skipped, do not send)
    const vendorForService = {};
    // track which service the vendor modal is currently for
    let vendorModalServiceId = null;

    // ── Build lookup maps from PHP-injected JSON ──────────────────────────────
    const servicesById = {};  // id → service object (with designs)
    const designsById  = {};  // id → design object

    if (typeof servicesData !== 'undefined') {
        servicesData.forEach(function (svc) {
            servicesById[svc.id] = svc;
            if (svc.designs) {
                svc.designs.forEach(function (d) {
                    designsById[d.id] = d;
                    d.service_id = svc.id;
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

        const rate = (typeof taxRate !== 'undefined') ? taxRate : 0; // taxRate is always PHP-injected; 0 is a safe fallback to avoid breaking the UI
        const total = (baseTotal + regularTotal + designTotal) * (1 + rate / 100);
        const totalCostEl = document.getElementById('totalCost');
        if (totalCostEl) totalCostEl.textContent = formatCurrency(total);
    }

    // ── Update hidden inputs so form includes selected designs ────────────────
    function syncDesignInputs() {
        const container = document.getElementById('selected-designs-inputs');
        if (!container) return;
        container.innerHTML = '';
        Object.values(selectedDesigns).forEach(function (d) {
            const input = document.createElement('input');
            input.type  = 'hidden';
            input.name  = 'selected_designs[' + d.service_id + ']';
            input.value = d.design_id;
            container.appendChild(input);
        });
    }

    // ── Update service card summary text and photo (main view) ───────────────
    function updateServiceSummary(serviceId) {
        const sel = selectedDesigns[serviceId];
        const text = sel ? (sel.name + ' (' + formatPrice(sel.price) + ')') : '';

        const desktopEl = document.getElementById('service-summary-' + serviceId);
        if (desktopEl)   desktopEl.textContent = text;

        const mobileEl  = document.getElementById('service-summary-mob-' + serviceId);
        if (mobileEl)    mobileEl.textContent  = text;

        // Show the selected subcategory photo on the service card
        const photoContainer    = document.getElementById('service-photo-'         + serviceId);
        const photoImg          = document.getElementById('service-selected-img-'  + serviceId);
        const photoContainerMob = document.getElementById('service-photo-mob-'     + serviceId);
        const photoImgMob       = document.getElementById('service-selected-img-mob-' + serviceId);

        if (sel && sel.photo) {
            const photoSrc = uploadUrl + '/' + sel.photo;
            if (photoContainer && photoImg) {
                photoImg.src = photoSrc;
                photoImg.alt = sel.name;
                photoContainer.style.display = '';
            }
            if (photoContainerMob && photoImgMob) {
                photoImgMob.src = photoSrc;
                photoImgMob.alt = sel.name;
                photoContainerMob.style.display = '';
            }
        } else {
            if (photoContainer)    photoContainer.style.display    = 'none';
            if (photoContainerMob) photoContainerMob.style.display = 'none';
        }

        // Highlight drilldown card if a design is selected
        const card = document.querySelector('.service-drilldown-card[data-service-id="' + serviceId + '"]');
        if (card) {
            card.classList.toggle('border-success', !!sel);
        }
    }

    // ── Build the flat design photo grid for a service ────────────────────────
    function buildDesignGrid(svc) {
        const sel = selectedDesigns[svc.id];

        if (!svc.designs || svc.designs.length === 0) {
            return '<div class="col-12"><div class="alert alert-info small py-2 mb-0">'
                 + '<i class="fas fa-info-circle me-1"></i>No designs available.</div></div>';
        }

        let html = '';
        svc.designs.forEach(function (d) {
            const isChosen = sel && sel.design_id == d.id;
            const photoHtml = d.photo
                ? '<img src="' + escapeHtml(uploadUrl + '/' + d.photo) + '" '
                    + 'alt="' + escapeHtml(d.name) + '" '
                    + 'class="card-img-top" style="height:200px;object-fit:cover;">'
                : '<div class="d-flex align-items-center justify-content-center bg-light" style="height:200px;">'
                    + '<i class="fas fa-image fa-3x text-muted"></i></div>';

            html += '<div class="col-6 col-md-4">';
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
        return html;
    }

    // ── Navigate INTO a service's design selection view ───────────────────────
    window.openDesignsView = function (serviceId) {
        currentServiceId = serviceId;
        const svc = servicesById[serviceId];
        if (!svc) return;

        document.getElementById('sub-services-title').textContent    = svc.name;
        document.getElementById('sub-services-subtitle').textContent = svc.description || '';

        // Update breadcrumb
        updateBreadcrumb(svc.name);

        const list = document.getElementById('sub-services-list');
        list.innerHTML = buildDesignGrid(svc);

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

    // ── Select a design and auto-return to services list ─────────────────────
    window.selectDesign = function (designId) {
        const d = designsById[designId];
        if (!d) return;

        // Record selection keyed by service_id
        selectedDesigns[d.service_id] = {
            design_id  : d.id,
            price      : parseFloat(d.price) || 0,
            name       : d.name,
            service_id : d.service_id,
            photo      : d.photo || ''
        };

        syncDesignInputs();
        recalculateTotal();

        // Auto-back to services list
        backToServices();
    };

    // ── Regular checkbox handler ──────────────────────────────────────────────
    // When a service with a vendor_type_slug is checked, open the vendor selection
    // modal so the user can optionally pick a specific vendor for that service.
    document.querySelectorAll('.service-checkbox').forEach(function (cb) {
        cb.addEventListener('change', function () {
            recalculateTotal();
            if (!this.checked) {
                // Service unchecked – clear any stored vendor selection
                const sid = parseInt(this.value);
                if (sid > 0) {
                    delete vendorForService[sid];
                    syncVendorInputs();
                }
                return;
            }
            const vendorTypeSlug = this.dataset.vendorTypeSlug || '';
            const serviceName    = this.dataset.serviceName    || '';
            const serviceId      = parseInt(this.value);
            if (vendorTypeSlug && serviceId > 0) {
                openVendorModal(serviceId, serviceName, vendorTypeSlug);
            }
        });
    });

    // ── Design radio handlers (inline checkbox mode) ──────────────────────────
    // Update visual state for all design cards belonging to a service
    function updateDesignCardStates(serviceId) {
        const sel = selectedDesigns[serviceId];
        document.querySelectorAll('.design-radio[data-service-id="' + serviceId + '"]').forEach(function (radio) {
            const dId = parseInt(radio.dataset.designId);
            const isSelected = !!(sel && sel.design_id === dId);
            radio.checked = isSelected;
            // Update both desktop and mobile card variants
            ['design-card-' + dId, 'design-card-mob-' + dId].forEach(function (cardId) {
                const card = document.getElementById(cardId);
                if (!card) return;
                card.classList.toggle('selected-design', isSelected);
                const overlay = card.querySelector('.design-check-overlay');
                if (overlay) overlay.style.display = isSelected ? '' : 'none';
                // Hide wrapper column for non-selected designs when a design is selected;
                // show all wrappers when nothing is selected (deselected state)
                const wrapper = card.closest('.design-col-item');
                if (wrapper) {
                    wrapper.classList.toggle('design-col-hidden', !!(sel && !isSelected));
                }
            });
        });
    }

    // Handle label click: support deselecting an already-selected design
    document.querySelectorAll('.design-select-label').forEach(function (label) {
        label.addEventListener('click', function (e) {
            const radio = this.querySelector('.design-radio');
            if (!radio) return;
            const serviceId = parseInt(radio.dataset.serviceId);
            const designId  = parseInt(radio.dataset.designId);
            const sel = selectedDesigns[serviceId];
            if (sel && sel.design_id === designId) {
                // Already selected – deselect on second click
                e.preventDefault();
                radio.checked = false;
                delete selectedDesigns[serviceId];
                updateDesignCardStates(serviceId);
                syncDesignInputs();
                recalculateTotal();
            }
        });
    });

    // Handle radio change: record new selection
    document.querySelectorAll('.design-radio').forEach(function (radio) {
        radio.addEventListener('change', function () {
            if (!this.checked) return;
            const designId  = parseInt(this.dataset.designId);
            const serviceId = parseInt(this.dataset.serviceId);
            selectedDesigns[serviceId] = {
                design_id  : designId,
                price      : parseFloat(this.dataset.price) || 0,
                name       : this.dataset.name || '',
                service_id : serviceId,
                photo      : this.dataset.photo || ''
            };
            updateDesignCardStates(serviceId);
            syncDesignInputs();
            recalculateTotal();
        });
    });

    // ── Vendor selection helpers ──────────────────────────────────────────────

    // Update hidden inputs so the form includes selected vendor_for_service[id]
    function syncVendorInputs() {
        const container = document.getElementById('selected-vendors-inputs');
        if (!container) return;
        container.innerHTML = '';
        Object.keys(vendorForService).forEach(function (sid) {
            const vid = vendorForService[sid];
            if (!vid) return; // skipped
            const input = document.createElement('input');
            input.type  = 'hidden';
            input.name  = 'vendor_for_service[' + sid + ']';
            input.value = vid;
            container.appendChild(input);
        });
    }

    // Open the vendor selection modal for the given service / type
    function openVendorModal(serviceId, serviceName, vendorTypeSlug) {
        vendorModalServiceId = serviceId;
        const modalEl = document.getElementById('vendorSelectModal');
        if (!modalEl) return; // modal HTML not present; skip silently

        const nameEl = document.getElementById('vendorModalServiceName');
        if (nameEl) nameEl.textContent = serviceName;

        const listEl  = document.getElementById('vendorModalList');
        const emptyEl = document.getElementById('vendorModalEmpty');

        // Fetch vendors from PHP-injected JSON (vendorsByType)
        const vendors = (typeof vendorsByType !== 'undefined' && vendorsByType[vendorTypeSlug]) || [];

        if (listEl) listEl.innerHTML = '';
        if (vendors.length === 0) {
            if (emptyEl)  emptyEl.style.display  = 'block';
            if (listEl)   listEl.style.display    = 'none';
        } else {
            if (emptyEl)  emptyEl.style.display  = 'none';
            if (listEl)   listEl.style.display    = '';

            const currentVendorId = vendorForService[serviceId] || 0;
            vendors.forEach(function (v) {
                const isSelected = (currentVendorId === v.id);
                const photoHtml = v.photo
                    ? '<img src="' + escapeHtml(uploadUrl + '/' + v.photo) + '" alt="' + escapeHtml(v.name) + '" class="rounded-circle me-2" style="width:40px;height:40px;object-fit:cover;">'
                    : '<span class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center me-2" style="width:40px;height:40px;font-size:1.1rem;"><i class="fas fa-user-tie"></i></span>';

                const cityText = v.city_name ? '<small class="text-muted">' + escapeHtml(v.city_name) + '</small>' : '';
                const phoneText = v.phone ? '<small class="text-muted"><i class="fas fa-phone me-1"></i>' + escapeHtml(v.phone) + '</small>' : '';
                const infoLine = [cityText, phoneText].filter(Boolean).join(' &bull; ');

                const card = document.createElement('div');
                card.className = 'col-12 col-md-6';
                card.innerHTML = '<div class="card vendor-select-card h-100 ' + (isSelected ? 'border-success selected-vendor' : '') + '" style="cursor:pointer;" data-vendor-id="' + v.id + '">'
                    + '<div class="card-body d-flex align-items-center py-2 px-3">'
                    + photoHtml
                    + '<div class="flex-grow-1 min-w-0">'
                    + '<div class="fw-semibold">' + escapeHtml(v.name) + (isSelected ? ' <i class="fas fa-check-circle text-success ms-1"></i>' : '') + '</div>'
                    + (infoLine ? '<div>' + infoLine + '</div>' : '')
                    + '</div>'
                    + '</div></div>';

                card.querySelector('.vendor-select-card').addEventListener('click', function () {
                    selectVendorForService(serviceId, parseInt(this.dataset.vendorId));
                });
                listEl.appendChild(card);
            });
        }

        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }

    // Record the chosen vendor for a service and close the modal
    function selectVendorForService(serviceId, vendorId) {
        vendorForService[serviceId] = vendorId;
        syncVendorInputs();

        // Close the modal
        const modalEl = document.getElementById('vendorSelectModal');
        if (modalEl) {
            const instance = bootstrap.Modal.getInstance(modalEl);
            if (instance) instance.hide();
        }
    }

    // "Skip" button in vendor modal: clear vendor for this service
    const vendorSkipBtn = document.getElementById('vendorSkipBtn');
    if (vendorSkipBtn) {
        vendorSkipBtn.addEventListener('click', function () {
            if (vendorModalServiceId !== null) {
                delete vendorForService[vendorModalServiceId];
                syncVendorInputs();
                vendorModalServiceId = null;
            }
        });
    }

    // ── Service search filter ─────────────────────────────────────────────────
    const searchInput = document.getElementById('serviceSearchInput');
    const clearBtn    = document.getElementById('serviceSearchClear');
    const noResults   = document.getElementById('serviceSearchNoResults');

    if (searchInput) {
        let debounceTimer = null;

        function filterServices() {
            const term = searchInput.value.trim().toLowerCase();

            const allCards = Array.from(document.querySelectorAll('.service-category-section [data-service-name]'));

            allCards.forEach(function (card) {
                const name = (card.getAttribute('data-service-name') || '').toLowerCase();
                card.style.display = name.includes(term) ? '' : 'none';
            });

            const categorySections = Array.from(document.querySelectorAll('.service-category-section'));
            let totalVisible = 0;

            categorySections.forEach(function (section) {
                const anyVisible = Array.from(section.querySelectorAll('[data-service-name]'))
                    .some(function (c) { return c.style.display !== 'none'; });
                section.style.display = anyVisible ? '' : 'none';
                if (anyVisible) {
                    totalVisible++;
                    // Auto-expand category when search narrows down results
                    if (term) {
                        const collapseEl = section.querySelector('.collapse');
                        if (collapseEl && !collapseEl.classList.contains('show')) {
                            bootstrap.Collapse.getOrCreateInstance(collapseEl).show();
                        }
                    }
                }
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
