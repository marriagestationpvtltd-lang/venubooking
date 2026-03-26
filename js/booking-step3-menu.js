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
    // overLimitSelections[menu_id][group_id] = Set of item_ids added beyond the group choose_limit
    const overLimitSelections = {};
    // Currency symbol injected by PHP; fallback to 'Rs.' if not available

    // ── Shared photo hover popup ──────────────────────────────────────────────
    let _photoPopup = null;
    const POPUP_SIZE = 130;
    const POPUP_VIEWPORT_PADDING = 8;

    function getPhotoPopup() {
        if (!_photoPopup) {
            _photoPopup = document.createElement('div');
            _photoPopup.id = 'cmp-photo-popup';
            const img = document.createElement('img');
            img.alt = '';
            _photoPopup.appendChild(img);
            document.body.appendChild(_photoPopup);
        }
        return _photoPopup;
    }

    function showPhotoPopup(triggerEl, imgSrc) {
        const popup = getPhotoPopup();
        popup.querySelector('img').src = imgSrc;
        const rect = triggerEl.getBoundingClientRect();
        let top = rect.top - POPUP_SIZE - 10;
        let left = rect.left + rect.width / 2 - POPUP_SIZE / 2;
        if (top < POPUP_VIEWPORT_PADDING) { top = rect.bottom + 10; }
        if (left < POPUP_VIEWPORT_PADDING) { left = POPUP_VIEWPORT_PADDING; }
        if (left + POPUP_SIZE > window.innerWidth - POPUP_VIEWPORT_PADDING) {
            left = window.innerWidth - POPUP_SIZE - POPUP_VIEWPORT_PADDING;
        }
        popup.style.top = top + 'px';
        popup.style.left = left + 'px';
        popup.classList.add('cmp-photo-popup--visible');
    }

    function hidePhotoPopup() {
        if (_photoPopup) {
            _photoPopup.classList.remove('cmp-photo-popup--visible');
        }
    }

    function attachPhotoHover(el, imgSrc, label) {
        el.setAttribute('tabindex', '0');
        el.setAttribute('role', 'img');
        el.setAttribute('aria-label', label || '');
        el.addEventListener('mouseenter', function () { showPhotoPopup(el, imgSrc); });
        el.addEventListener('mouseleave', hidePhotoPopup);
        el.addEventListener('focus', function () { showPhotoPopup(el, imgSrc); });
        el.addEventListener('blur', hidePhotoPopup);
    }
    // ─────────────────────────────────────────────────────────────────────────
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
                    const imgSrc = BASE_URL + '/uploads/' + encodeURIComponent(group.photo);
                    photoImg.src = imgSrc;
                    photoImg.alt = escapeHtml(group.group_name);
                    photoWrap.appendChild(photoImg);
                    // Stop click from toggling group collapse
                    photoWrap.addEventListener('click', function (e) { e.stopPropagation(); });
                    attachPhotoHover(photoWrap, imgSrc, group.group_name);
                    groupTitle.appendChild(photoWrap);
                }

                groupTitle.appendChild(document.createTextNode(group.group_name));

                // Show selection limit inline, right after the group name
                if (group.choose_limit) {
                    const gLim = document.createElement('span');
                    gLim.className = 'cmp-group-limit';
                    const gCounter = document.createElement('span');
                    gCounter.id = 'grp-counter-' + menuId + '-' + group.id;
                    gCounter.textContent = currentSelections[menuId][group.id].size;
                    gLim.appendChild(document.createTextNode('Select '));
                    gLim.appendChild(gCounter);
                    gLim.appendChild(document.createTextNode(' / ' + group.choose_limit));
                    groupTitle.appendChild(gLim);
                }

                groupHead.appendChild(groupTitle);

                // Inline selected-items preview — always visible in the header when items are chosen
                const previewEl = document.createElement('span');
                previewEl.className = 'cmp-group-selected-preview';
                previewEl.id = 'grp-preview-' + menuId + '-' + group.id;
                groupHead.appendChild(previewEl);

                // Selected count + extra price badge — shown only when group is collapsed
                const totalEl = document.createElement('span');
                totalEl.className = 'cmp-group-selected-total';
                totalEl.id = 'grp-total-' + menuId + '-' + group.id;
                groupHead.appendChild(totalEl);

                groupHead.addEventListener('click', function () {
                    toggleGroupCollapse(groupDiv);
                });

                groupDiv.appendChild(groupHead);

                const itemsGrid = document.createElement('div');
                itemsGrid.className = 'cmp-items-grid';

                group.items.forEach(function (item) {
                    const isSelected = currentSelections[menuId][group.id].has(parseInt(item.id));

                    const itemCard = document.createElement('div');
                    itemCard.className = 'cmp-item' + (isSelected ? ' cmp-item--selected' : '') + (item.photo ? ' cmp-item--has-photo' : '');
                    itemCard.dataset.menuId = menuId;
                    itemCard.dataset.groupId = group.id;
                    itemCard.dataset.sectionId = section.id;
                    itemCard.dataset.itemId = item.id;
                    itemCard.dataset.groupLimit = group.choose_limit || '';
                    itemCard.dataset.sectionLimit = section.choose_limit || '';
                    itemCard.dataset.extraCharge = item.extra_charge || '0';
                    itemCard.dataset.groupExtraChargePerItem = group.extra_charge_per_item || '0';
                    itemCard.dataset.itemName = item.item_name;

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

                    // Item photo circular icon with hover popup
                    if (item.photo) {
                        const itemPhotoWrap = document.createElement('span');
                        itemPhotoWrap.className = 'cmp-item-photo';
                        itemPhotoWrap.title = item.item_name;
                        const itemPhotoImg = document.createElement('img');
                        const itemImgSrc = BASE_URL + '/uploads/' + encodeURIComponent(item.photo);
                        itemPhotoImg.src = itemImgSrc;
                        itemPhotoImg.alt = escapeHtml(item.item_name);
                        itemPhotoWrap.appendChild(itemPhotoImg);
                        // Stop click from selecting/deselecting the item
                        itemPhotoWrap.addEventListener('click', function (e) { e.stopPropagation(); });
                        attachPhotoHover(itemPhotoWrap, itemImgSrc, item.item_name);
                        itemCard.appendChild(itemPhotoWrap);
                    }

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
                        toggleItem(this, menuId, group.id, section.id, group.choose_limit, section.choose_limit, group.extra_charge_per_item);
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

    // Adds an item within the group/section limit, updates all UI state.
    function addItemNormally(card, menuId, groupId, itemId, groupLimit) {
        currentSelections[menuId][groupId].add(itemId);
        card.classList.add('cmp-item--selected');
        card.classList.add('cmp-item--selecting');
        setTimeout(function () { card.classList.remove('cmp-item--selecting'); }, 250);
        var itemCheckbox = card.querySelector('.menu-item-checkbox');
        if (itemCheckbox) itemCheckbox.checked = true;
        updateCounters(menuId);
        serializeSelections();
        updateGroupSummary(menuId, groupId);
        updateSelectedSummary();
        computeExtraChargesTotal();
        if (groupLimit && currentSelections[menuId][groupId].size >= parseInt(groupLimit)) {
            var groupElement = findGroupEl(menuId, groupId);
            if (groupElement && !groupElement.classList.contains('cmp-group--collapsed')) {
                collapseGroup(groupElement);
                expandNextGroup(groupElement);
            }
        }
    }

    function toggleItem(card, menuId, groupId, sectionId, groupLimit, sectionLimit, groupExtraChargePerItem) {
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
            const isGroupOver = groupLimit && currentSelections[menuId][groupId].size >= parseInt(groupLimit);
            const isSectionOver = !isGroupOver && sectionLimit && sectionTotal >= parseInt(sectionLimit);
            if (isGroupOver || isSectionOver) {
                // Determine the over-limit charge: group-level extra_charge_per_item takes priority;
                // fall back to the item's own extra_charge if no group-level charge is configured.
                const itemExtraCharge = parseFloat(card.dataset.extraCharge || '0');
                const perItemCharge = parseFloat(groupExtraChargePerItem || card.dataset.groupExtraChargePerItem || '0');
                const overLimitCharge = perItemCharge > 0 ? perItemCharge : itemExtraCharge;
                const itemName = card.dataset.itemName || 'this item';
                showExtraChargeConfirmation(itemName, overLimitCharge, itemExtraCharge, function () {
                    addItemOverLimit(card, menuId, groupId, itemId);
                }, true);
                return;
            }
            // Within limit but item has its own extra charge → confirm before adding
            const itemOwnCharge = parseFloat(card.dataset.extraCharge || '0');
            if (itemOwnCharge > 0) {
                const itemName = card.dataset.itemName || 'this item';
                showExtraChargeConfirmation(itemName, itemOwnCharge, itemOwnCharge, function () {
                    addItemNormally(card, menuId, groupId, itemId, groupLimit);
                }, false);
                return;
            }
            addItemNormally(card, menuId, groupId, itemId, groupLimit);
        } else {
            currentSelections[menuId][groupId].delete(itemId);
            card.classList.remove('cmp-item--selected');
            card.classList.remove('cmp-item--extra-included');
            card.removeAttribute('data-extra-label');
            if (overLimitSelections[menuId] && overLimitSelections[menuId][groupId]) {
                overLimitSelections[menuId][groupId].delete(itemId);
            }
            const cb = card.querySelector('.menu-item-checkbox');
            if (cb) cb.checked = false;
            updateCounters(menuId);
            serializeSelections();
            updateGroupSummary(menuId, groupId);
            updateSelectedSummary();
            computeExtraChargesTotal();
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

    function showExtraChargeConfirmation(itemName, overLimitCharge, itemExtraCharge, onConfirm, isOverLimit) {
        var modalEl = document.getElementById('menuExtraChargeConfirmModal');
        if (!modalEl) {
            modalEl = document.createElement('div');
            modalEl.id = 'menuExtraChargeConfirmModal';
            modalEl.className = 'modal fade';
            modalEl.tabIndex = -1;
            modalEl.setAttribute('aria-modal', 'true');
            modalEl.setAttribute('role', 'dialog');
            modalEl.innerHTML =
                '<div class="modal-dialog modal-dialog-centered modal-sm">' +
                '<div class="modal-content">' +
                '<div class="modal-header py-2">' +
                '<h6 class="modal-title"><i class="fas fa-plus-circle text-warning me-2"></i>Extra Item</h6>' +
                '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>' +
                '</div>' +
                '<div class="modal-body py-3">' +
                '<p id="menuExtraConfirmMsg" class="mb-0 text-center"></p>' +
                '</div>' +
                '<div class="modal-footer py-2 justify-content-center gap-2">' +
                '<button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>' +
                '<button type="button" class="btn btn-sm btn-success" id="menuExtraConfirmOkBtn">Add Item</button>' +
                '</div>' +
                '</div></div>';
            document.body.appendChild(modalEl);
        }

        var msgEl = modalEl.querySelector('#menuExtraConfirmMsg');
        if (msgEl) {
            var escapedCurrency = escapeHtml(currencySymbol);
            var displayCharge = overLimitCharge > 0 ? overLimitCharge : 0;
            if (isOverLimit === false && displayCharge > 0) {
                msgEl.innerHTML = '<strong>' + escapeHtml(itemName) + '</strong> has an extra charge of <strong>' + escapedCurrency + Math.round(displayCharge) + '</strong>. Do you want to add it?';
            } else if (displayCharge > 0) {
                msgEl.innerHTML = 'Adding <strong>' + escapeHtml(itemName) + '</strong> is beyond the included selection. An extra charge of <strong>' + escapedCurrency + Math.round(displayCharge) + '</strong> per item applies. Do you want to add it?';
            } else {
                msgEl.innerHTML = 'Adding <strong>' + escapeHtml(itemName) + '</strong> as an extra item (beyond your included selection). Do you want to add it?';
            }
        }

        var oldOkBtn = modalEl.querySelector('#menuExtraConfirmOkBtn');
        if (oldOkBtn) {
            var newOkBtn = oldOkBtn.cloneNode(true);
            oldOkBtn.parentNode.replaceChild(newOkBtn, oldOkBtn);
            newOkBtn.addEventListener('click', function () {
                var bsModal = typeof bootstrap !== 'undefined' && bootstrap.Modal.getInstance(modalEl);
                if (bsModal) bsModal.hide();
                if (typeof onConfirm === 'function') onConfirm();
            });
        }

        var bsModal = (typeof bootstrap !== 'undefined')
            ? (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl))
            : null;
        if (bsModal) {
            bsModal.show();
        } else if (typeof onConfirm === 'function' && window.confirm(
            isOverLimit === false && overLimitCharge > 0
                ? '"' + itemName + '" has an extra charge of ' + currencySymbol + Math.round(overLimitCharge) + '. Add it?'
                : overLimitCharge > 0
                    ? 'Adding "' + itemName + '" will cost ' + currencySymbol + Math.round(overLimitCharge) + ' extra per item. Add it?'
                    : 'Add "' + itemName + '" as an extra item?'
        )) {
            onConfirm();
        }
    }

    function addItemOverLimit(card, menuId, groupId, itemId) {
        currentSelections[menuId][groupId].add(itemId);
        card.classList.add('cmp-item--selected');
        card.classList.add('cmp-item--extra-included');
        card.classList.add('cmp-item--selecting');
        setTimeout(function () { card.classList.remove('cmp-item--selecting'); }, 250);
        var itemExtraCharge = parseFloat(card.dataset.extraCharge || '0');
        var perItemCharge = parseFloat(card.dataset.groupExtraChargePerItem || '0');
        var overLimitCharge = perItemCharge > 0 ? perItemCharge : itemExtraCharge;
        card.dataset.extraLabel = overLimitCharge > 0
            ? 'Extra +' + currencySymbol + Math.round(overLimitCharge)
            : 'Extra';
        var cb = card.querySelector('.menu-item-checkbox');
        if (cb) cb.checked = true;

        if (!overLimitSelections[menuId]) overLimitSelections[menuId] = {};
        if (!overLimitSelections[menuId][groupId]) overLimitSelections[menuId][groupId] = new Set();
        overLimitSelections[menuId][groupId].add(itemId);

        updateCounters(menuId);
        serializeSelections();
        updateGroupSummary(menuId, groupId);
        updateSelectedSummary();
        computeExtraChargesTotal();
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
        const totalEl = document.getElementById('grp-total-' + menuId + '-' + groupId);
        if (!previewEl || !totalEl) return;

        const selections = currentSelections[menuId] && currentSelections[menuId][groupId]
            ? currentSelections[menuId][groupId] : new Set();

        if (selections.size === 0) {
            previewEl.textContent = '';
            totalEl.textContent = '';
            return;
        }

        const structure = menuStructures[menuId];
        if (!structure) return;

        const selectedNames = [];
        let extraTotal = 0;
        structure.sections.forEach(function (section) {
            section.groups.forEach(function (g) {
                if (parseInt(g.id) === parseInt(groupId)) {
                    g.items.forEach(function (item) {
                        if (selections.has(parseInt(item.id))) {
                            selectedNames.push(item.item_name);
                            extraTotal += parseFloat(item.extra_charge || 0);
                        }
                    });
                }
            });
        });

        previewEl.textContent = '\u2713 ' + selectedNames.join(', ');

        let totalText = selectedNames.length + (selectedNames.length === 1 ? ' item' : ' items');
        if (extraTotal > 0) {
            totalText += ' \u00b7 +' + currencySymbol + Math.round(extraTotal);
        }
        totalEl.textContent = totalText;
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
                // Custom-selection menu: section-wise price breakdown
                const pricePerPerson = cb ? parseFloat(cb.dataset.price || '0') : 0;
                const guests = (typeof guestsCount !== 'undefined') ? parseInt(guestsCount) : 0;
                let hasAnySelection = false;
                let runningExtra = 0;
                const sectionsWrap = document.createElement('div');
                sectionsWrap.className = 'cmp-sections-grid';
                structure.sections.forEach(function (section) {
                    let sectionExtra = 0;
                    const sectionGroups = [];

                    section.groups.forEach(function (group) {
                        const sel = currentSelections[menuId][group.id];
                        if (!sel || sel.size === 0) return;
                        const groupLimit = parseInt(group.choose_limit) || 0;
                        const perItemCharge = parseFloat(group.extra_charge_per_item || 0);
                        const itemData = [];
                        group.items.forEach(function (item) {
                            if (sel.has(parseInt(item.id))) {
                                const isOver = overLimitSelections[menuId] &&
                                    overLimitSelections[menuId][group.id] &&
                                    overLimitSelections[menuId][group.id].has(parseInt(item.id));
                                // For over-limit items, show group's per-item charge (if set); else item's own charge
                                const charge = isOver && perItemCharge > 0
                                    ? perItemCharge
                                    : (parseFloat(item.extra_charge) || 0);
                                sectionExtra += charge;
                                itemData.push({
                                    name: item.item_name,
                                    extraCharge: charge,
                                    isOver: isOver
                                });
                            }
                        });
                        if (itemData.length > 0) {
                            sectionGroups.push({ groupName: group.group_name, items: itemData });
                        }
                    });

                    if (sectionGroups.length === 0) return;
                    hasAnySelection = true;
                    runningExtra += sectionExtra;

                    const sectionDiv = document.createElement('div');
                    sectionDiv.style.cssText = 'margin-bottom:5px;border:1px solid #e2e8f0;border-radius:5px;overflow:hidden;';

                    // Section header: name + section extra badge
                    const sectionHead = document.createElement('div');
                    sectionHead.style.cssText = 'display:flex;justify-content:space-between;align-items:center;background:#f1f5f9;padding:2px 7px;';
                    const sectionTitle = document.createElement('span');
                    sectionTitle.style.cssText = 'font-size:0.7rem;font-weight:700;color:#334155;';
                    sectionTitle.textContent = section.section_name;
                    sectionHead.appendChild(sectionTitle);
                    if (sectionExtra > 0) {
                        const secExtraBadge = document.createElement('span');
                        secExtraBadge.style.cssText = 'font-size:0.63rem;font-weight:600;color:#b45309;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:0 5px;';
                        secExtraBadge.textContent = '+' + currencySymbol + Math.round(sectionExtra);
                        sectionHead.appendChild(secExtraBadge);
                    }
                    sectionDiv.appendChild(sectionHead);

                    // Groups & items
                    const itemsDiv = document.createElement('div');
                    itemsDiv.style.cssText = 'padding:3px 7px;';
                    sectionGroups.forEach(function (grp) {
                        const line = document.createElement('div');
                        line.style.cssText = 'margin-bottom:2px;';
                        line.innerHTML =
                            '<span style="font-size:0.68rem;font-weight:600;color:#64748b;margin-right:3px;">' +
                            escapeHtml(grp.groupName) + ':</span>' +
                            grp.items.map(function (d) { return compactChip(d.name, d.extraCharge, d.isOver); }).join(' ');
                        itemsDiv.appendChild(line);
                    });
                    sectionDiv.appendChild(itemsDiv);

                    // Running cumulative extra total
                    const cumDiv = document.createElement('div');
                    cumDiv.style.cssText = 'display:flex;justify-content:space-between;padding:1px 7px 3px;border-top:1px dashed #e2e8f0;background:#fafafa;';
                    const cumLabel = document.createElement('span');
                    cumLabel.style.cssText = 'font-size:0.62rem;color:#94a3b8;';
                    cumLabel.textContent = 'Cumulative extras:';
                    cumDiv.appendChild(cumLabel);
                    const cumVal = document.createElement('span');
                    cumVal.style.cssText = 'font-size:0.65rem;font-weight:700;color:#92400e;';
                    cumVal.textContent = currencySymbol + Math.round(runningExtra);
                    cumDiv.appendChild(cumVal);
                    sectionDiv.appendChild(cumDiv);

                    sectionsWrap.appendChild(sectionDiv);
                });

                if (!hasAnySelection) {
                    sectionsWrap.setAttribute('role', 'status');
                    sectionsWrap.innerHTML =
                        '<span style="font-size:0.72rem;color:#d97706;">' +
                        '<i class="fas fa-exclamation-circle" aria-hidden="true"></i> No items selected yet</span>';
                }
                cell.appendChild(sectionsWrap);

                // Menu total summary row (base price × guests + extras)
                if (hasAnySelection) {
                    const totalRow = document.createElement('div');
                    totalRow.style.cssText = 'margin-top:4px;display:flex;justify-content:space-between;align-items:center;padding:3px 7px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:4px;';
                    const totalLabel = document.createElement('span');
                    totalLabel.style.cssText = 'font-size:0.65rem;color:#166534;';
                    if (guests > 0 && pricePerPerson > 0) {
                        totalLabel.textContent = currencySymbol + Math.round(pricePerPerson) + '/pax' + (runningExtra > 0 ? ' + extras' : '') + ' \u00d7 ' + guests;
                    } else {
                        totalLabel.textContent = 'Menu total:';
                    }
                    totalRow.appendChild(totalLabel);
                    const totalVal = document.createElement('span');
                    totalVal.style.cssText = 'font-size:0.7rem;font-weight:700;color:#166534;';
                    totalVal.textContent = currencySymbol + Math.round((pricePerPerson + runningExtra) * guests);
                    totalRow.appendChild(totalVal);
                    cell.appendChild(totalRow);
                }

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
                linesWrap.className = 'cmp-sections-grid';

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

    function compactChip(name, extraCharge, isExtraIncluded) {
        var bg = isExtraIncluded ? '#fff7ed' : '#dcfce7';
        var border = isExtraIncluded ? '#fed7aa' : '#86efac';
        var textColor = isExtraIncluded ? '#9a3412' : '#14532d';
        var svgFill = isExtraIncluded ? '#ea580c' : '#15803d';
        var chargeTag = (extraCharge > 0)
            ? '<span style="font-size:0.63rem;font-weight:600;color:#b45309;margin-left:2px;">+' + escapeHtml(currencySymbol) + Math.round(extraCharge) + '</span>'
            : '';
        var extraTag = isExtraIncluded
            ? '<span style="font-size:0.6rem;font-weight:700;color:#ea580c;background:#ffedd5;border-radius:3px;padding:0 3px;margin-left:2px;">Extra</span>'
            : '';
        return '<span style="display:inline-flex;align-items:center;gap:3px;padding:1px 7px;' +
            'border-radius:20px;background:' + bg + ';border:1px solid ' + border + ';' +
            'font-size:0.72rem;font-weight:500;color:' + textColor + ';">' +
            '<svg style="width:8px;height:8px;flex-shrink:0;" viewBox="0 0 12 12" fill="none">' +
            '<circle cx="6" cy="6" r="5.5" fill="' + svgFill + '"/>' +
            '<path d="M3.5 6l2 2 3-3" stroke="#fff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>' +
            '</svg>' + escapeHtml(name) + chargeTag + extraTag + '</span>';
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
        computeExtraChargesTotal();
    }

    function updateAllCounters() {
        Object.keys(menuStructures).forEach(function (mid) { updateCounters(parseInt(mid)); });
    }

    // Compute the sum of extra_charge values for all currently selected menu items
    // plus group-level over-limit charges (extra_charge_per_item × items beyond choose_limit),
    // and update the price total in the booking summary bar via calculateMenuTotal().
    function computeExtraChargesTotal() {
        let extra = 0;
        const checkedIds = getCheckedMenuIds();
        checkedIds.forEach(function (menuId) {
            const structure = menuStructures[menuId];
            const selections = currentSelections[menuId];
            if (!structure || !selections) return;
            structure.sections.forEach(function (section) {
                section.groups.forEach(function (group) {
                    const sel = selections[group.id];
                    if (!sel || sel.size === 0) return;

                    // Per-item extra charges (individual premium items)
                    group.items.forEach(function (item) {
                        const charge = parseFloat(item.extra_charge);
                        if (sel.has(parseInt(item.id)) && charge > 0) {
                            extra += charge;
                        }
                    });

                    // Group-level over-limit charge
                    const groupLimit = parseInt(group.choose_limit);
                    const perItemCharge = parseFloat(group.extra_charge_per_item || 0);
                    if (groupLimit > 0 && perItemCharge > 0 && sel.size > groupLimit) {
                        extra += (sel.size - groupLimit) * perItemCharge;
                    }
                });
            });
        });
        window.menuExtraChargesTotal = extra;
        if (typeof calculateMenuTotal === 'function') {
            calculateMenuTotal();
        }
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
