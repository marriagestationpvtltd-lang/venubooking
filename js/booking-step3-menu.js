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
                // Calculate current section total from already-restored session data
                let sectionCurrent = 0;
                if (currentSelections[menuId]) {
                    section.groups.forEach(function (g) {
                        if (currentSelections[menuId][g.id]) {
                            sectionCurrent += currentSelections[menuId][g.id].size;
                        }
                    });
                }
                const limBadge = document.createElement('span');
                limBadge.className = 'cmp-limit-badge';
                const counter = document.createElement('span');
                counter.id = 'sec-counter-' + menuId + '-' + section.id;
                counter.textContent = String(sectionCurrent);
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
                groupDiv.dataset.groupId = group.id;
                groupDiv.dataset.menuId = menuId;

                const groupHead = document.createElement('div');
                groupHead.className = 'cmp-group-head';

                const groupTitle = document.createElement('span');
                groupTitle.className = 'cmp-group-title';

                const toggleIcon = document.createElement('i');
                toggleIcon.className = 'fas fa-angle-down me-1 text-success cmp-group-toggle';
                groupTitle.appendChild(toggleIcon);

                if (group.photo) {
                    const photoWrap = document.createElement('span');
                    photoWrap.className = 'cmp-group-photo';
                    const photoImg = document.createElement('img');
                    photoImg.src = BASE_URL + '/uploads/' + encodeURIComponent(group.photo);
                    photoImg.alt = escapeHtml(group.group_name);
                    photoWrap.appendChild(photoImg);
                    groupTitle.appendChild(photoWrap);
                }

                groupTitle.appendChild(document.createTextNode(group.group_name));
                groupHead.appendChild(groupTitle);

                // Inline selected-items preview — always visible in the header when items are chosen
                const previewEl = document.createElement('span');
                previewEl.className = 'cmp-group-selected-preview';
                previewEl.id = 'grp-preview-' + menuId + '-' + group.id;
                groupHead.appendChild(previewEl);

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

                groupHead.addEventListener('click', function () {
                    toggleGroupCollapse(groupDiv);
                });

                groupDiv.appendChild(groupHead);

                const itemsGrid = document.createElement('div');
                itemsGrid.className = 'cmp-items-grid';

                group.items.forEach(function (item) {
                    const isSelected = currentSelections[menuId][group.id].has(parseInt(item.id));

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
        updateGroupSummary(menuId, groupId);
        updateSelectedSummary();

        // Auto-collapse this group when its limit is reached, then expand the next one
        if (!isSelected && groupLimit &&
                currentSelections[menuId][groupId].size >= parseInt(groupLimit)) {
            const groupEl = findGroupEl(menuId, groupId);
            if (groupEl && !groupEl.classList.contains('cmp-group--collapsed')) {
                collapseGroup(groupEl);
                expandNextGroup(groupEl);
            }
        }
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

    function collapseGroup(groupEl) {
        groupEl.classList.add('cmp-group--collapsed');
    }

    function expandGroup(groupEl) {
        groupEl.classList.remove('cmp-group--collapsed');
    }

    function toggleGroupCollapse(groupEl) {
        if (groupEl.classList.contains('cmp-group--collapsed')) {
            expandGroup(groupEl);
        } else {
            collapseGroup(groupEl);
        }
    }

    // Returns the .cmp-group element for a given menu/group ID pair without
    // constructing a CSS selector from untrusted values.
    function findGroupEl(menuId, groupId) {
        const mId = String(menuId);
        const gId = String(groupId);
        return Array.from(document.querySelectorAll('.cmp-group')).find(function (el) {
            return el.dataset.menuId === mId && el.dataset.groupId === gId;
        }) || null;
    }

    function expandNextGroup(currentGroupEl) {
        const groupsWrap = currentGroupEl.closest('.cmp-groups');
        if (!groupsWrap) return;
        const allGroups = Array.from(groupsWrap.querySelectorAll('.cmp-group'));
        const idx = allGroups.indexOf(currentGroupEl);
        if (idx >= 0 && idx + 1 < allGroups.length) {
            expandGroup(allGroups[idx + 1]);
            // Small delay lets the CSS transition start before the browser calculates scroll position
            setTimeout(function () {
                allGroups[idx + 1].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }, 50);
        }
    }

    function updateGroupSummary(menuId, groupId) {
        const previewEl = document.getElementById('grp-preview-' + menuId + '-' + groupId);
        if (!previewEl) return;

        const selections = currentSelections[menuId] && currentSelections[menuId][groupId]
            ? currentSelections[menuId][groupId] : new Set();

        if (selections.size === 0) {
            previewEl.textContent = '';
            return;
        }

        const structure = menuStructures[menuId];
        if (!structure) return;

        const selectedNames = [];
        structure.sections.forEach(function (section) {
            section.groups.forEach(function (g) {
                if (parseInt(g.id) === parseInt(groupId)) {
                    g.items.forEach(function (item) {
                        if (selections.has(parseInt(item.id))) {
                            selectedNames.push(item.item_name);
                        }
                    });
                }
            });
        });

        previewEl.textContent = '\u2713 ' + selectedNames.join(', ');
    }

    function updateAllGroupSummaries(menuId) {
        const structure = menuStructures[menuId];
        if (!structure) return;
        structure.sections.forEach(function (section) {
            section.groups.forEach(function (group) {
                updateGroupSummary(menuId, group.id);
            });
        });
    }

    function updateSelectedSummary() {
        const summaryDiv = document.getElementById('selectedMenusSummary');
        if (!summaryDiv) return;

        const checkedIds = getCheckedMenuIds();
        if (checkedIds.length === 0) {
            summaryDiv.style.display = 'none';
            return;
        }

        const body = document.getElementById('selectedMenusSummaryBody');
        if (!body) return;

        summaryDiv.style.display = '';
        body.innerHTML = '';

        // Horizontal grid: menus side by side, auto-fill columns
        const grid = document.createElement('div');
        grid.style.cssText = 'display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:8px;';

        checkedIds.forEach(function (menuId) {
            const cb = document.querySelector('.menu-checkbox[value="' + menuId + '"]');
            const card = cb ? cb.closest('.menu-card') : null;
            const menuNameEl = card ? card.querySelector('.card-title') : null;
            const menuName = menuNameEl ? menuNameEl.textContent.trim() : ('Menu #' + menuId);

            // Get pre-defined items from data attribute
            const menuCol = cb ? cb.closest('[data-menu-items]') : null;
            let menuItemsData = [];
            try {
                menuItemsData = menuCol && menuCol.dataset.menuItems
                    ? JSON.parse(menuCol.dataset.menuItems) : [];
            } catch (e) { menuItemsData = []; }

            // Menu cell
            const cell = document.createElement('div');
            cell.style.cssText = 'background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 10px;min-width:0;';

            // Compact menu title
            cell.innerHTML =
                '<div style="display:flex;align-items:center;gap:5px;margin-bottom:5px;">' +
                '<i class="fas fa-utensils" style="color:#15803d;font-size:0.7rem;flex-shrink:0;"></i>' +
                '<span style="font-size:0.82rem;font-weight:600;color:#14532d;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' +
                escapeHtml(menuName) + '</span></div>';

            // Determine structure
            const structure = menuStructures[menuId];
            const hasSections = structure && structure.sections && structure.sections.length > 0;

            if (hasSections && currentSelections[menuId]) {
                // Custom-selection menu: compact rows per group
                let hasAnySelection = false;
                const linesWrap = document.createElement('div');

                structure.sections.forEach(function (section) {
                    section.groups.forEach(function (group) {
                        const sel = currentSelections[menuId][group.id];
                        if (!sel || sel.size === 0) return;
                        const itemNames = [];
                        group.items.forEach(function (item) {
                            if (sel.has(parseInt(item.id))) itemNames.push(item.item_name);
                        });
                        if (itemNames.length === 0) return;
                        hasAnySelection = true;

                        const line = document.createElement('div');
                        line.style.cssText = 'margin-bottom:4px;';
                        line.innerHTML =
                            '<span style="font-size:0.72rem;font-weight:600;color:#64748b;margin-right:3px;">' +
                            escapeHtml(group.group_name) + ':</span>' +
                            itemNames.map(function (n) { return compactChip(n); }).join(' ');
                        linesWrap.appendChild(line);
                    });
                });

                if (!hasAnySelection) {
                    linesWrap.setAttribute('role', 'status');
                    linesWrap.innerHTML =
                        '<span style="font-size:0.72rem;color:#d97706;">' +
                        '<i class="fas fa-exclamation-circle" aria-hidden="true"></i> No items selected yet</span>';
                }
                cell.appendChild(linesWrap);

            } else if (menuItemsData.length > 0) {
                // Simple menu: compact rows per category
                const grouped = {};
                const categoryOrder = [];
                menuItemsData.forEach(function (item) {
                    const cat = item.category || '';
                    if (!Object.prototype.hasOwnProperty.call(grouped, cat)) {
                        grouped[cat] = [];
                        categoryOrder.push(cat);
                    }
                    grouped[cat].push(item.item_name);
                });

                const linesWrap = document.createElement('div');
                categoryOrder.forEach(function (cat) {
                    const line = document.createElement('div');
                    line.style.cssText = 'margin-bottom:4px;';
                    line.innerHTML =
                        (cat ? '<span style="font-size:0.72rem;font-weight:600;color:#64748b;margin-right:3px;">' +
                            escapeHtml(cat) + ':</span>' : '') +
                        grouped[cat].map(function (n) { return compactChip(n); }).join(' ');
                    linesWrap.appendChild(line);
                });
                cell.appendChild(linesWrap);
            }

            grid.appendChild(cell);
        });

        body.appendChild(grid);
    }

    function compactChip(name) {
        return '<span style="display:inline-flex;align-items:center;gap:3px;padding:1px 7px;' +
            'border-radius:20px;background:#dcfce7;border:1px solid #86efac;' +
            'font-size:0.72rem;font-weight:500;color:#14532d;">' +
            '<svg style="width:8px;height:8px;flex-shrink:0;" viewBox="0 0 12 12" fill="none">' +
            '<circle cx="6" cy="6" r="5.5" fill="#15803d"/>' +
            '<path d="M3.5 6l2 2 3-3" stroke="#fff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>' +
            '</svg>' + escapeHtml(name) + '</span>';
    }

    function autoCollapseFilledGroups(menuId) {
        const structure = menuStructures[menuId];
        if (!structure) return;
        structure.sections.forEach(function (section) {
            section.groups.forEach(function (group) {
                const limit = parseInt(group.choose_limit);
                if (!limit) return;
                const count = currentSelections[menuId] && currentSelections[menuId][group.id]
                    ? currentSelections[menuId][group.id].size : 0;
                if (count >= limit) {
                    const groupEl = findGroupEl(menuId, group.id);
                    if (groupEl) collapseGroup(groupEl);
                }
            });
        });
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
        const menuSearchWrapper = document.getElementById('menuSearchWrapper');
        const menuSearchNoResults = document.getElementById('menuSearchNoResults');
        const allMenuCols = Array.from(document.querySelectorAll('#menusContainer > [data-menu-name]'));

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
        if (checkedIds.length > 0) {
            // Always hide non-selected menu cards and search bar when a menu is selected
            allMenuCols.forEach(function (col) {
                const cb = col.querySelector('.menu-checkbox');
                col.style.display = (cb && cb.checked) ? '' : 'none';
            });
            if (menuSearchWrapper) menuSearchWrapper.style.display = 'none';
            if (menuSearchNoResults) menuSearchNoResults.style.display = 'none';

            if (hasAnyStructure) {
                allPanels.forEach(function (p) { panel.appendChild(p); });
                // Restore summaries and auto-collapse groups that are already full (e.g. from session)
                checkedIds.forEach(function (menuId) {
                    if (menuStructures[menuId]) {
                        updateAllGroupSummaries(menuId);
                        autoCollapseFilledGroups(menuId);
                    }
                });
                if (panelContainer) panelContainer.style.display = '';
            } else {
                if (panelContainer) panelContainer.style.display = 'none';
            }
            if (specialInstructions) specialInstructions.style.display = '';
        } else {
            // No menu selected: restore all menu cards and search bar
            if (panelContainer) panelContainer.style.display = 'none';
            if (specialInstructions) specialInstructions.style.display = 'none';
            allMenuCols.forEach(function (col) { col.style.display = ''; });
            if (menuSearchWrapper) menuSearchWrapper.style.display = '';
            if (menuSearchNoResults) menuSearchNoResults.style.display = 'none';
        }

        updateAllCounters();
        serializeSelections();
        updateSelectedSummary();
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
            updateSelectedSummary();
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
            updateSelectedSummary();
            refreshCustomPanel();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
