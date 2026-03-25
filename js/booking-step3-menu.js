/**
 * booking-step3-menu.js
 * Handles custom menu item selection UI on booking step 3.
 */
(function () {
    'use strict';

    // menuStructures[menu_id] = { sections: [...] }
    const menuStructures = {};
    // currentSelections[menu_id][group_id] = Set of item_ids
    const currentSelections = {};

    // Initialize from session data if available
    if (typeof menuSelectionsSession !== 'undefined' && menuSelectionsSession) {
        Object.entries(menuSelectionsSession).forEach(([mid, groups]) => {
            currentSelections[parseInt(mid)] = {};
            Object.entries(groups).forEach(([gid, item_ids]) => {
                currentSelections[parseInt(mid)][parseInt(gid)] = new Set(item_ids.map(Number));
            });
        });
    }

    function getCheckedMenuIds() {
        return Array.from(document.querySelectorAll('.menu-checkbox:checked')).map(cb => parseInt(cb.value));
    }

    async function loadMenuStructure(menuId) {
        if (menuStructures[menuId]) return menuStructures[menuId];
        try {
            const resp = await fetch(BASE_URL + '/api/get-menu-structure.php?menu_id=' + menuId);
            const data = await resp.json();
            if (data.success) {
                menuStructures[menuId] = data;
                return data;
            }
        } catch (e) {
            console.error('Failed to load menu structure for menu', menuId, e);
        }
        return null;
    }

    function buildMenuPanel(menuId, structure) {
        if (!structure || !structure.sections || structure.sections.length === 0) return null;

        if (!currentSelections[menuId]) currentSelections[menuId] = {};

        const container = document.createElement('div');
        container.className = 'menu-structure-panel mb-4';
        container.dataset.menuId = menuId;

        const header = document.createElement('h6');
        header.className = 'text-success fw-bold border-bottom pb-2 mb-3';
        header.innerHTML = '<i class="fas fa-utensils me-2"></i>' + structure.menu_name;
        container.appendChild(header);

        structure.sections.forEach(section => {
            const sectionDiv = document.createElement('div');
            sectionDiv.className = 'mb-4';

            const sectionHeader = document.createElement('div');
            sectionHeader.className = 'd-flex justify-content-between align-items-center mb-2 bg-light rounded p-2';

            const sectionTitle = document.createElement('span');
            sectionTitle.className = 'fw-semibold text-uppercase';
            sectionTitle.textContent = section.section_name;
            sectionHeader.appendChild(sectionTitle);

            if (section.choose_limit) {
                const limitBadge = document.createElement('span');
                limitBadge.className = 'badge bg-info';
                const sectionCounter = document.createElement('span');
                sectionCounter.id = 'sec-counter-' + menuId + '-' + section.id;
                sectionCounter.textContent = '0';
                limitBadge.textContent = 'Choose up to: ';
                limitBadge.appendChild(sectionCounter);
                limitBadge.appendChild(document.createTextNode('/' + section.choose_limit));
                sectionHeader.appendChild(limitBadge);
            }

            sectionDiv.appendChild(sectionHeader);

            section.groups.forEach(group => {
                if (!currentSelections[menuId][group.id]) {
                    currentSelections[menuId][group.id] = new Set();
                }

                const groupDiv = document.createElement('div');
                groupDiv.className = 'ms-2 mb-3';

                const groupHeader = document.createElement('div');
                groupHeader.className = 'd-flex justify-content-between align-items-center mb-2';

                const groupTitle = document.createElement('span');
                groupTitle.className = 'fw-semibold text-dark';
                groupTitle.textContent = group.group_name;
                groupHeader.appendChild(groupTitle);

                if (group.choose_limit) {
                    const glimitSpan = document.createElement('span');
                    glimitSpan.className = 'badge bg-warning text-dark';
                    const groupCounter = document.createElement('span');
                    groupCounter.id = 'grp-counter-' + menuId + '-' + group.id;
                    groupCounter.textContent = currentSelections[menuId][group.id].size;
                    glimitSpan.textContent = 'Choose up to: ';
                    glimitSpan.appendChild(groupCounter);
                    glimitSpan.appendChild(document.createTextNode('/' + group.choose_limit));
                    groupHeader.appendChild(glimitSpan);
                }

                groupDiv.appendChild(groupHeader);

                const itemsGrid = document.createElement('div');
                itemsGrid.className = 'row g-2';

                group.items.forEach(item => {
                    const col = document.createElement('div');
                    col.className = 'col-md-4 col-6';

                    const label = document.createElement('label');
                    label.className = 'form-check d-flex align-items-start gap-2 border rounded p-2 item-label';
                    label.style.cursor = 'pointer';

                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.className = 'form-check-input mt-1 flex-shrink-0 menu-item-checkbox';
                    checkbox.value = item.id;
                    checkbox.dataset.menuId = menuId;
                    checkbox.dataset.groupId = group.id;
                    checkbox.dataset.sectionId = section.id;
                    checkbox.dataset.groupLimit = group.choose_limit || '';
                    checkbox.dataset.sectionLimit = section.choose_limit || '';
                    checkbox.dataset.extraCharge = item.extra_charge || 0;

                    // Restore selection from session
                    if (currentSelections[menuId][group.id] && currentSelections[menuId][group.id].has(item.id)) {
                        checkbox.checked = true;
                        label.classList.add('border-success', 'bg-light');
                    }

                    const itemContent = document.createElement('div');
                    itemContent.className = 'flex-grow-1';

                    const itemName = document.createElement('div');
                    itemName.className = 'small fw-medium';
                    itemName.textContent = item.item_name;
                    itemContent.appendChild(itemName);

                    if (item.sub_category) {
                        const subCat = document.createElement('div');
                        subCat.className = 'text-muted small';
                        subCat.style.fontSize = '0.75em';
                        subCat.textContent = item.sub_category;
                        itemContent.appendChild(subCat);
                    }

                    if (parseFloat(item.extra_charge) > 0) {
                        const extraBadge = document.createElement('span');
                        extraBadge.className = 'badge bg-warning text-dark mt-1';
                        extraBadge.style.fontSize = '0.7em';
                        extraBadge.textContent = '+Rs.' + parseFloat(item.extra_charge).toFixed(2);
                        itemContent.appendChild(extraBadge);
                    }

                    label.appendChild(checkbox);
                    label.appendChild(itemContent);
                    col.appendChild(label);
                    itemsGrid.appendChild(col);

                    checkbox.addEventListener('change', function () {
                        handleItemCheck(this, menuId, group.id, section.id, group.choose_limit, section.choose_limit, label);
                    });
                });

                groupDiv.appendChild(itemsGrid);
                sectionDiv.appendChild(groupDiv);
            });

            container.appendChild(sectionDiv);
        });

        return container;
    }

    function handleItemCheck(checkbox, menuId, groupId, sectionId, groupLimit, sectionLimit, label) {
        const isChecked = checkbox.checked;
        const itemId = parseInt(checkbox.value);

        if (!currentSelections[menuId]) currentSelections[menuId] = {};
        if (!currentSelections[menuId][groupId]) currentSelections[menuId][groupId] = new Set();

        // Get current section total
        const structure = menuStructures[menuId];
        let sectionTotal = 0;
        if (structure && sectionLimit) {
            structure.sections.forEach(s => {
                if (s.id == sectionId) {
                    s.groups.forEach(g => {
                        if (currentSelections[menuId][g.id]) {
                            sectionTotal += currentSelections[menuId][g.id].size;
                        }
                    });
                }
            });
        }

        if (isChecked) {
            // Check group limit
            if (groupLimit && currentSelections[menuId][groupId].size >= parseInt(groupLimit)) {
                checkbox.checked = false;
                showLimitAlert('You can only choose up to ' + groupLimit + ' items from this group.');
                return;
            }
            // Check section limit
            if (sectionLimit && sectionTotal >= parseInt(sectionLimit)) {
                checkbox.checked = false;
                showLimitAlert('You can only choose up to ' + sectionLimit + ' items from this section.');
                return;
            }
            currentSelections[menuId][groupId].add(itemId);
            label.classList.add('border-success', 'bg-light');
        } else {
            currentSelections[menuId][groupId].delete(itemId);
            label.classList.remove('border-success', 'bg-light');
        }

        updateCounters(menuId);
        serializeSelections();
    }

    function updateCounters(menuId) {
        const structure = menuStructures[menuId];
        if (!structure) return;

        structure.sections.forEach(section => {
            let sectionTotal = 0;
            section.groups.forEach(group => {
                const count = currentSelections[menuId] && currentSelections[menuId][group.id]
                    ? currentSelections[menuId][group.id].size : 0;
                sectionTotal += count;

                const grpCounter = document.getElementById('grp-counter-' + menuId + '-' + group.id);
                if (grpCounter) grpCounter.textContent = count;
            });

            const secCounter = document.getElementById('sec-counter-' + menuId + '-' + section.id);
            if (secCounter) secCounter.textContent = sectionTotal;
        });
    }

    function showLimitAlert(message) {
        let alertDiv = document.getElementById('menuLimitAlert');
        if (!alertDiv) {
            alertDiv = document.createElement('div');
            alertDiv.id = 'menuLimitAlert';
            alertDiv.className = 'alert alert-warning alert-dismissible fade show position-fixed';
            alertDiv.style.cssText = 'top:80px;right:20px;z-index:9999;max-width:350px;';
            document.body.appendChild(alertDiv);
        }
        // Build alert content safely to avoid XSS
        alertDiv.innerHTML = '';
        const icon = document.createElement('i');
        icon.className = 'fas fa-exclamation-triangle me-2';
        const msgSpan = document.createElement('span');
        msgSpan.textContent = message;
        const closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'btn-close';
        closeBtn.setAttribute('data-bs-dismiss', 'alert');
        alertDiv.appendChild(icon);
        alertDiv.appendChild(msgSpan);
        alertDiv.appendChild(closeBtn);
        alertDiv.classList.add('show');
        setTimeout(() => {
            if (alertDiv) {
                alertDiv.classList.remove('show');
                // Remove from DOM after transition to avoid accumulation
                setTimeout(() => { if (alertDiv && alertDiv.parentNode) alertDiv.parentNode.removeChild(alertDiv); }, 300);
            }
        }, 3000);
    }

    function serializeSelections() {
        const result = {};
        Object.entries(currentSelections).forEach(([mid, groups]) => {
            const menuId = parseInt(mid, 10);
            // Guard: skip non-numeric menu IDs to prevent CSS selector injection
            if (isNaN(menuId) || menuId <= 0) return;
            // Only serialize for checked menus
            const checkbox = document.querySelector('.menu-checkbox[value="' + menuId + '"]');
            if (!checkbox || !checkbox.checked) return;

            result[menuId] = {};
            Object.entries(groups).forEach(([gid, itemSet]) => {
                if (itemSet.size > 0) {
                    result[menuId][parseInt(gid)] = Array.from(itemSet);
                }
            });
        });

        const jsonField = document.getElementById('menuSelectionsJson');
        if (jsonField) jsonField.value = JSON.stringify(result);
    }

    async function refreshCustomPanel() {
        const panel = document.getElementById('customMenuPanelBody');
        if (!panel) return;

        const checkedIds = getCheckedMenuIds();
        const panelContainer = document.getElementById('customMenuPanel');

        panel.innerHTML = '<div class="text-center py-3"><i class="fas fa-spinner fa-spin"></i> Loading menu customization...</div>';

        const allPanels = [];
        let hasAnyStructure = false;

        for (const menuId of checkedIds) {
            const structure = await loadMenuStructure(menuId);
            if (structure && structure.sections && structure.sections.length > 0) {
                hasAnyStructure = true;
                const panelEl = buildMenuPanel(menuId, structure);
                if (panelEl) allPanels.push(panelEl);
            }
        }

        panel.innerHTML = '';
        if (hasAnyStructure) {
            allPanels.forEach(p => panel.appendChild(p));
            if (panelContainer) panelContainer.style.display = '';
        } else {
            if (panelContainer) panelContainer.style.display = 'none';
        }

        updateAllCounters();
        serializeSelections();
    }

    function updateAllCounters() {
        Object.keys(menuStructures).forEach(mid => updateCounters(parseInt(mid)));
    }

    // Listen to menu checkbox changes
    document.addEventListener('change', function (e) {
        if (e.target && e.target.classList.contains('menu-checkbox')) {
            const menuId = parseInt(e.target.value);
            if (!e.target.checked) {
                // Clear selections for unchecked menu
                delete currentSelections[menuId];
            }
            refreshCustomPanel();
        }
    });

    // On form submit, serialize selections
    const menuForm = document.getElementById('menuForm');
    if (menuForm) {
        menuForm.addEventListener('submit', function () {
            serializeSelections();
        });
    }

    // Initialize on page load if any menus are pre-checked
    function init() {
        const checkedMenus = getCheckedMenuIds();
        if (checkedMenus.length > 0) {
            refreshCustomPanel();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
