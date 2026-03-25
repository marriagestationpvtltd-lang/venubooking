/**
 * booking-step3-menu.js
 * Handles custom menu item selection UI on booking step 3.
 * Professional card-based design with click-to-select items.
 */
(function () {
    'use strict';

    // menuStructures[menu_id] = { sections: [...] }
    const menuStructures = {};
    // currentSelections[menu_id][group_id] = Set of item_ids
    const currentSelections = {};
    // Currency symbol injected by PHP; fallback to 'Rs.' if not available
    const currencySymbol = (typeof CURRENCY !== 'undefined' ? CURRENCY : 'Rs.');

    // Initialize from session data if available
    if (typeof menuSelectionsSession !== 'undefined' && menuSelectionsSession) {
        Object.entries(menuSelectionsSession).forEach(([mid, groups]) => {
            currentSelections[parseInt(mid)] = {};
            Object.entries(groups).forEach(([gid, item_ids]) => {
                currentSelections[parseInt(mid)][parseInt(gid)] = new Set(item_ids.map(Number));
            });
        });
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(str != null ? String(str) : ''));
        return div.innerHTML;
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
        container.className = 'cmp-panel';
        container.dataset.menuId = menuId;

        // Menu header
        const menuHeader = document.createElement('div');
        menuHeader.className = 'cmp-menu-header';
        menuHeader.innerHTML =
            '<span class="cmp-menu-icon"><i class="fas fa-utensils"></i></span>' +
            '<div class="cmp-menu-title-block">' +
            '<span class="cmp-menu-title">' + escapeHtml(structure.menu_name) + '</span>' +
            '<span class="cmp-menu-sub">Select your preferred items from each category below</span>' +
            '</div>';
        container.appendChild(menuHeader);

        structure.sections.forEach(function (section) {
            if (!section.groups || section.groups.length === 0) return;

            const sectionWrap = document.createElement('div');
            sectionWrap.className = 'cmp-section';

            // Section header
            const sectionHead = document.createElement('div');
            sectionHead.className = 'cmp-section-head';

            const sectionTitle = document.createElement('span');
            sectionTitle.className = 'cmp-section-title';
            sectionTitle.textContent = section.section_name;
            sectionHead.appendChild(sectionTitle);

            if (section.choose_limit) {
                const limBadge = document.createElement('span');
                limBadge.className = 'cmp-limit-badge';
                const counter = document.createElement('span');
                counter.id = 'sec-counter-' + menuId + '-' + section.id;
                counter.textContent = '0';
                limBadge.appendChild(document.createTextNode('Select '));
                limBadge.appendChild(counter);
                limBadge.appendChild(document.createTextNode(' / ' + section.choose_limit));
                sectionHead.appendChild(limBadge);
            }

            sectionWrap.appendChild(sectionHead);

            // Groups
            const groupsWrap = document.createElement('div');
            groupsWrap.className = 'cmp-groups';

            section.groups.forEach(function (group) {
                if (!currentSelections[menuId][group.id]) {
                    currentSelections[menuId][group.id] = new Set();
                }

                const groupDiv = document.createElement('div');
                groupDiv.className = 'cmp-group';

                const groupHead = document.createElement('div');
                groupHead.className = 'cmp-group-head';

                const groupTitle = document.createElement('span');
                groupTitle.className = 'cmp-group-title';
                groupTitle.innerHTML = '<i class="fas fa-angle-right me-1 text-success"></i>' + escapeHtml(group.group_name);
                groupHead.appendChild(groupTitle);

                if (group.choose_limit) {
                    const gLim = document.createElement('span');
                    gLim.className = 'cmp-group-limit';
                    const gCounter = document.createElement('span');
                    gCounter.id = 'grp-counter-' + menuId + '-' + group.id;
                    gCounter.textContent = currentSelections[menuId][group.id].size;
                    gLim.appendChild(document.createTextNode('Max: '));
                    gLim.appendChild(gCounter);
                    gLim.appendChild(document.createTextNode('/' + group.choose_limit));
                    groupHead.appendChild(gLim);
                }

                groupDiv.appendChild(groupHead);

                const itemsGrid = document.createElement('div');
                itemsGrid.className = 'cmp-items-grid';

                group.items.forEach(function (item) {
                    const isSelected = currentSelections[menuId][group.id].has(item.id);

                    const itemCard = document.createElement('div');
                    itemCard.className = 'cmp-item' + (isSelected ? ' cmp-item--selected' : '');
                    itemCard.dataset.menuId = menuId;
                    itemCard.dataset.groupId = group.id;
                    itemCard.dataset.sectionId = section.id;
                    itemCard.dataset.itemId = item.id;
                    itemCard.dataset.groupLimit = group.choose_limit || '';
                    itemCard.dataset.sectionLimit = section.choose_limit || '';

                    // Check indicator
                    const checkIcon = document.createElement('div');
                    checkIcon.className = 'cmp-item-check';
                    checkIcon.innerHTML = '<i class="fas fa-check"></i>';
                    itemCard.appendChild(checkIcon);

                    // Item body
                    const body = document.createElement('div');
                    body.className = 'cmp-item-body';

                    const nameEl = document.createElement('div');
                    nameEl.className = 'cmp-item-name';
                    nameEl.textContent = item.item_name;
                    body.appendChild(nameEl);

                    if (item.sub_category) {
                        const subEl = document.createElement('div');
                        subEl.className = 'cmp-item-sub';
                        subEl.textContent = item.sub_category;
                        body.appendChild(subEl);
                    }

                    if (parseFloat(item.extra_charge) > 0) {
                        const extraEl = document.createElement('div');
                        extraEl.className = 'cmp-item-extra';
                        extraEl.textContent = '+' + currencySymbol + Math.round(parseFloat(item.extra_charge));
                        body.appendChild(extraEl);
                    }

                    itemCard.appendChild(body);

                    // Hidden checkbox for form-based compatibility
                    const hiddenCb = document.createElement('input');
                    hiddenCb.type = 'checkbox';
                    hiddenCb.className = 'menu-item-checkbox d-none';
                    hiddenCb.value = item.id;
                    hiddenCb.dataset.menuId = menuId;
                    hiddenCb.dataset.groupId = group.id;
                    hiddenCb.checked = isSelected;
                    itemCard.appendChild(hiddenCb);

                    itemCard.addEventListener('click', function () {
                        toggleItem(this, menuId, group.id, section.id, group.choose_limit, section.choose_limit);
                    });

                    itemsGrid.appendChild(itemCard);
                });

                groupDiv.appendChild(itemsGrid);
                groupsWrap.appendChild(groupDiv);
            });

            sectionWrap.appendChild(groupsWrap);
            container.appendChild(sectionWrap);
        });

        return container;
    }

    function toggleItem(card, menuId, groupId, sectionId, groupLimit, sectionLimit) {
        const itemId = parseInt(card.dataset.itemId);
        const isSelected = card.classList.contains('cmp-item--selected');

        if (!currentSelections[menuId]) currentSelections[menuId] = {};
        if (!currentSelections[menuId][groupId]) currentSelections[menuId][groupId] = new Set();

        // Calculate current section total
        const structure = menuStructures[menuId];
        let sectionTotal = 0;
        if (structure && sectionLimit) {
            structure.sections.forEach(function (s) {
                if (s.id == sectionId) {
                    s.groups.forEach(function (g) {
                        if (currentSelections[menuId][g.id]) {
                            sectionTotal += currentSelections[menuId][g.id].size;
                        }
                    });
                }
            });
        }

        if (!isSelected) {
            if (groupLimit && currentSelections[menuId][groupId].size >= parseInt(groupLimit)) {
                showLimitAlert('You can only choose up to ' + groupLimit + ' items from this group.');
                return;
            }
            if (sectionLimit && sectionTotal >= parseInt(sectionLimit)) {
                showLimitAlert('You can only choose up to ' + sectionLimit + ' items from this section.');
                return;
            }
            currentSelections[menuId][groupId].add(itemId);
            card.classList.add('cmp-item--selected');
            const cb = card.querySelector('.menu-item-checkbox');
            if (cb) cb.checked = true;
        } else {
            currentSelections[menuId][groupId].delete(itemId);
            card.classList.remove('cmp-item--selected');
            const cb = card.querySelector('.menu-item-checkbox');
            if (cb) cb.checked = false;
        }

        updateCounters(menuId);
        serializeSelections();
    }

    function updateCounters(menuId) {
        const structure = menuStructures[menuId];
        if (!structure) return;

        structure.sections.forEach(function (section) {
            let sectionTotal = 0;
            section.groups.forEach(function (group) {
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
        setTimeout(function () {
            if (alertDiv) {
                alertDiv.classList.remove('show');
                setTimeout(function () {
                    if (alertDiv && alertDiv.parentNode) alertDiv.parentNode.removeChild(alertDiv);
                }, 300);
            }
        }, 3000);
    }

    function serializeSelections() {
        const result = {};
        Object.entries(currentSelections).forEach(function ([mid, groups]) {
            const menuId = parseInt(mid, 10);
            if (isNaN(menuId) || menuId <= 0) return;
            const checkbox = document.querySelector('.menu-checkbox[value="' + menuId + '"]');
            if (!checkbox || !checkbox.checked) return;

            result[menuId] = {};
            Object.entries(groups).forEach(function ([gid, itemSet]) {
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
        const specialInstructions = document.getElementById('menuSpecialInstructions');

        panel.innerHTML = '<div class="cmp-loading"><i class="fas fa-spinner fa-spin me-2"></i>Loading menu options...</div>';

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
            allPanels.forEach(function (p) { panel.appendChild(p); });
            if (panelContainer) panelContainer.style.display = '';
            if (specialInstructions) specialInstructions.style.display = '';
        } else {
            if (panelContainer) panelContainer.style.display = 'none';
            if (specialInstructions) specialInstructions.style.display = 'none';
        }

        updateAllCounters();
        serializeSelections();
    }

    function updateAllCounters() {
        Object.keys(menuStructures).forEach(function (mid) { updateCounters(parseInt(mid)); });
    }

    // Listen to menu checkbox changes
    document.addEventListener('change', function (e) {
        if (e.target && e.target.classList.contains('menu-checkbox')) {
            const menuId = parseInt(e.target.value);
            if (!e.target.checked) {
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
