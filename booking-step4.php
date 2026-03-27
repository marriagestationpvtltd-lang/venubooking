<?php
$page_title = 'Service Packages';
// Require PHP utilities before any HTML output so session-guard redirects work correctly
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Check if we have all required booking data
if (!isset($_SESSION['booking_data']) || !isset($_SESSION['selected_hall'])) {
    $_SESSION['booking_error_flash'] = 'Your booking session has expired or is incomplete. Please start again.';
    header('Location: index.php');
    exit;
}

// Save selected menus when arriving from step 3 (booking-step3.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['menus'])) {
    $_SESSION['selected_menus'] = $_POST['menus'];
}

// Include HTML header only after all redirects have been handled
require_once __DIR__ . '/includes/header.php';

$booking_data   = $_SESSION['booking_data'];
$selected_hall  = $_SESSION['selected_hall'];
$selected_menus = $_SESSION['selected_menus'] ?? [];

// Get service packages grouped by category
$packages_by_category = getServicePackagesByCategory();
// Keep only categories that have active packages
$packages_by_category = array_filter($packages_by_category, function ($cat) {
    return !empty($cat['packages']);
});

// Build enriched package data for the "View Full Information" modal
// (includes gallery photos, all features, and menu items)
$all_packages_modal_data = [];
try {
    $db_modal = getDB();
    foreach ($packages_by_category as $cat) {
        foreach ($cat['packages'] as $pkg) {
            $pkg_menus = [];
            try {
                $menu_ids = getPackageMenuIds((int)$pkg['id']);
                foreach ($menu_ids as $mid) {
                    $mstmt = $db_modal->prepare(
                        "SELECT id, name, description FROM menus WHERE id = ? AND status='active'"
                    );
                    $mstmt->execute([(int)$mid]);
                    $menu_row = $mstmt->fetch(PDO::FETCH_ASSOC);
                    if ($menu_row) {
                        $menu_row['structure'] = getMenuStructure((int)$mid);
                        if (empty($menu_row['structure'])) {
                            // Fallback to flat item list for legacy installs
                            $fi = $db_modal->prepare(
                                "SELECT item_name, category FROM menu_items
                                 WHERE menu_id = ? ORDER BY display_order, category"
                            );
                            $fi->execute([(int)$mid]);
                            $menu_row['flat_items'] = $fi->fetchAll(PDO::FETCH_ASSOC);
                        } else {
                            $menu_row['flat_items'] = [];
                        }
                        $pkg_menus[] = $menu_row;
                    }
                }
            } catch (\Exception $e) {
                error_log('booking-step4: failed to load menus for package ' . $pkg['id'] . ': ' . $e->getMessage());
            }

            $raw_photos = $pkg['photos'] ?? [];
            $all_packages_modal_data[(int)$pkg['id']] = [
                'id'            => (int)$pkg['id'],
                'name'          => $pkg['name'],
                'price'         => (float)$pkg['price'],
                'description'   => $pkg['description'] ?? '',
                'category_name' => $cat['name'],
                'photos'        => array_values(array_filter($raw_photos, fn($p) => is_string($p) && $p !== '')),
                'features'      => array_map(fn($f) => [
                    'feature_text'  => $f['feature_text'],
                    'service_photo' => $f['service_photo'] ?? null,
                ], $pkg['features'] ?? []),
                'menus'         => $pkg_menus,
            ];
        }
    }
} catch (\Exception $e) {
    error_log('booking-step4: failed to build modal package data: ' . $e->getMessage());
}

// Calculate current totals (no packages selected yet on initial load)
$totals        = calculateBookingTotal($selected_hall['id'], $selected_menus, $booking_data['guests'], [], [], [], $selected_hall['base_price'], $_SESSION['menu_selections'] ?? []);
$tax_rate      = floatval(getSetting('tax_rate', '13'));
$current_total = $totals['grand_total'];
?>

<!-- Booking Progress -->
<div class="booking-progress py-3 bg-light">
    <div class="container">
        <div class="row">
            <div class="col">
                <div class="progress-steps">
                    <div class="step completed">
                        <span class="step-number">1</span>
                        <span class="step-label">Details</span>
                    </div>
                    <div class="step completed">
                        <span class="step-number">2</span>
                        <span class="step-label">Venue &amp; Hall</span>
                    </div>
                    <div class="step completed">
                        <span class="step-number">3</span>
                        <span class="step-label">Menu</span>
                    </div>
                    <div class="step active">
                        <span class="step-number">4</span>
                        <span class="step-label">Packages</span>
                    </div>
                    <div class="step">
                        <span class="step-number">5</span>
                        <span class="step-label">Services</span>
                    </div>
                    <div class="step">
                        <span class="step-number">6</span>
                        <span class="step-label">Confirm</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Booking Summary Bar -->
<div class="booking-summary-bar py-2 bg-success text-white">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8 col-12">
                <strong><?php echo sanitize($selected_hall['venue_name']); ?> - <?php echo sanitize($selected_hall['name']); ?></strong>
                <span class="mx-2 d-none d-md-inline">|</span>
                <span class="d-block d-md-inline">
                    <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($booking_data['event_date'])); ?> <small class="opacity-75">(<?php echo convertToNepaliDate($booking_data['event_date']); ?>)</small>
                    <span class="mx-1">&bull;</span>
                    <i class="fas fa-clock"></i> <?php echo ucfirst($booking_data['shift']); ?>
                    <?php if (!empty($booking_data['start_time']) && !empty($booking_data['end_time'])): ?>
                        (<?php echo formatBookingTime($booking_data['start_time']); ?> &ndash; <?php echo formatBookingTime($booking_data['end_time']); ?>)
                    <?php endif; ?>
                </span>
            </div>
            <div class="col-md-4 col-12 text-md-end mt-2 mt-md-0">
                <strong>Total: <span id="totalCost"><?php echo formatCurrency($current_total); ?></span></strong>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<section class="py-5">
    <div class="container">
        <h2 class="mb-2">Service Packages</h2>
        <p class="lead text-muted mb-4">Choose a pre-configured service package (Optional)</p>

        <form id="packagesForm" method="POST" action="booking-step5.php">

            <?php if (empty($packages_by_category)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No service packages available at this time.
                </div>
                <div class="row mt-4">
                    <div class="col-md-6">
                        <a href="booking-step3.php" class="btn btn-outline-secondary btn-lg w-100">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
                    <div class="col-md-6">
                        <button type="submit" class="btn btn-success btn-lg w-100">
                            Continue <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <!-- Group Accordion -->
                <div class="pkg-groups-accordion mb-4" id="pkgGroupsAccordion">
                    <?php $pkg_cat_index = 0; foreach ($packages_by_category as $cat): ?>
                        <?php if (empty($cat['packages'])) continue; ?>
                        <div class="pkg-group-item <?php echo $pkg_cat_index === 0 ? 'pkg-group-active' : ''; ?>"
                             data-cat-id="<?php echo (int)$cat['id']; ?>">

                            <!-- Group Header (always visible horizontal row) -->
                            <div class="pkg-group-header" role="button" aria-expanded="<?php echo $pkg_cat_index === 0 ? 'true' : 'false'; ?>">
                                <div class="pkg-group-header-left">
                                    <span class="pkg-group-icon">
                                        <i class="fas fa-tag" aria-hidden="true"></i>
                                    </span>
                                    <span class="pkg-group-name-text"><?php echo sanitize($cat['name']); ?></span>

                                    <!-- Inline summary: shown when group is collapsed and has selections -->
                                    <span class="pkg-group-divider d-none">|</span>
                                    <span class="pkg-group-summary-inline" aria-live="polite">
                                        <i class="fas fa-check-circle pkg-group-summary-check" aria-hidden="true"></i>
                                        <span class="pkg-group-summary-text"></span>
                                        <span class="pkg-group-summary-cost d-none"></span>
                                    </span>
                                </div>
                                <i class="fas fa-chevron-down pkg-group-chevron" aria-hidden="true"></i>
                            </div>

                            <!-- Group Body (package grid, visible only when active) -->
                            <div class="pkg-group-body">
                                <div class="row g-3">
                                    <?php foreach ($cat['packages'] as $pkg): ?>
                                        <div class="col-sm-6 col-lg-4">
                                            <div class="card package-select-card h-100">
                                                <?php if (!empty($pkg['photos'])): ?>
                                                    <?php if (count($pkg['photos']) > 1): ?>
                                                        <?php $pid = 'pkgCarousel' . $pkg['id']; ?>
                                                        <div id="<?php echo $pid; ?>" class="carousel slide" data-bs-ride="false">
                                                            <div class="carousel-indicators">
                                                                <?php foreach ($pkg['photos'] as $pi => $ph): ?>
                                                                    <button type="button"
                                                                            data-bs-target="#<?php echo $pid; ?>"
                                                                            data-bs-slide-to="<?php echo $pi; ?>"
                                                                            <?php if ($pi === 0) echo 'class="active" aria-current="true"'; ?>
                                                                            aria-label="Photo <?php echo $pi + 1; ?>">
                                                                    </button>
                                                                <?php endforeach; ?>
                                                            </div>
                                                            <div class="carousel-inner">
                                                                <?php foreach ($pkg['photos'] as $pi => $photo): ?>
                                                                    <div class="carousel-item <?php echo ($pi === 0) ? 'active' : ''; ?>">
                                                                        <img src="<?php echo UPLOAD_URL . htmlspecialchars($photo); ?>"
                                                                             alt="<?php echo htmlspecialchars($pkg['name']); ?>"
                                                                             class="d-block w-100"
                                                                             style="height:200px;object-fit:cover;">
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                            <button class="carousel-control-prev" type="button" data-bs-target="#<?php echo $pid; ?>" data-bs-slide="prev">
                                                                <span class="carousel-control-prev-icon"></span>
                                                            </button>
                                                            <button class="carousel-control-next" type="button" data-bs-target="#<?php echo $pid; ?>" data-bs-slide="next">
                                                                <span class="carousel-control-next-icon"></span>
                                                            </button>
                                                        </div>
                                                    <?php else: ?>
                                                        <img src="<?php echo UPLOAD_URL . htmlspecialchars($pkg['photos'][0]); ?>"
                                                             alt="<?php echo htmlspecialchars($pkg['name']); ?>"
                                                             class="card-img-top"
                                                             style="height:200px;object-fit:cover;">
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <div class="d-flex align-items-center justify-content-center bg-light"
                                                         style="height:200px;">
                                                        <i class="fas fa-box fa-3x text-muted"></i>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="card-body d-flex flex-column">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <div class="form-check flex-grow-1 me-2">
                                                            <input class="form-check-input package-checkbox"
                                                                   type="checkbox"
                                                                   name="packages[]"
                                                                   value="<?php echo $pkg['id']; ?>"
                                                                   id="pkg<?php echo $pkg['id']; ?>"
                                                                   data-price="<?php echo htmlspecialchars($pkg['price'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                   data-pkg-name="<?php echo htmlspecialchars($pkg['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                                            <label class="form-check-label fw-semibold" for="pkg<?php echo $pkg['id']; ?>">
                                                                <?php echo sanitize($pkg['name']); ?>
                                                            </label>
                                                        </div>
                                                        <span class="text-success fw-bold text-nowrap">
                                                            <?php echo formatCurrency($pkg['price']); ?>
                                                        </span>
                                                    </div>

                                                    <?php if (!empty($pkg['description'])): ?>
                                                        <p class="text-muted small mb-2"><?php echo sanitize($pkg['description']); ?></p>
                                                    <?php endif; ?>

                                                    <?php if (!empty($pkg['features'])): ?>
                                                        <div class="pkg-feat-icons mt-auto mb-0">
                                                            <?php foreach (array_slice($pkg['features'], 0, 6) as $feat): ?>
                                                            <div class="pkg-feat-icon-item" title="<?php echo htmlspecialchars($feat['feature_text'], ENT_QUOTES, 'UTF-8'); ?>">
                                                                <?php if (!empty($feat['service_photo'])): ?>
                                                                <img src="<?php echo UPLOAD_URL . htmlspecialchars($feat['service_photo'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                     class="pkg-feat-icon-img"
                                                                     loading="lazy"
                                                                     alt="<?php echo htmlspecialchars($feat['feature_text'], ENT_QUOTES, 'UTF-8'); ?>">
                                                                <?php else: ?>
                                                                <div class="pkg-feat-icon-fallback">
                                                                    <i class="fas fa-check" aria-hidden="true"></i>
                                                                </div>
                                                                <?php endif; ?>
                                                                <p class="pkg-feat-icon-label"><?php echo htmlspecialchars($feat['feature_text'], ENT_QUOTES, 'UTF-8'); ?></p>
                                                            </div>
                                                            <?php endforeach; ?>
                                                            <?php if (count($pkg['features']) > 6): ?>
                                                            <div class="pkg-feat-icon-item" title="+<?php echo count($pkg['features']) - 6; ?> more features">
                                                                <div class="pkg-feat-more-chip">+<?php echo count($pkg['features']) - 6; ?></div>
                                                                <p class="pkg-feat-icon-label">थप</p>
                                                            </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <!-- View Full Information button -->
                                                    <div class="mt-3 pt-2 border-top">
                                                        <button type="button"
                                                                class="btn btn-outline-info btn-sm w-100 btn-pkg-details"
                                                                data-pkg-id="<?php echo (int)$pkg['id']; ?>">
                                                            <i class="fas fa-info-circle me-1"></i> View Full Information
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php $pkg_cat_index++; endforeach; ?>
                </div>

                <div class="row mt-4">
                    <div class="col-12 mb-2 text-center">
                        <button type="submit" name="skip_packages" value="1"
                                id="skipPackagesBtn"
                                class="btn btn-link text-muted">
                            <i class="fas fa-forward me-1"></i> Skip Packages &rarr;
                        </button>
                    </div>
                    <div class="col-md-6">
                        <a href="booking-step3.php" class="btn btn-outline-secondary btn-lg w-100">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
                    <div class="col-md-6">
                        <button type="submit" class="btn btn-success btn-lg w-100">
                            Continue <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
            <?php endif; ?>

        </form>
    </div>
</section>

<!-- Package Full-Information Modal -->
<div class="modal fade" id="pkgDetailModal" tabindex="-1"
     aria-labelledby="pkgDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-light align-items-start">
                <div class="flex-grow-1 me-3">
                    <h5 class="modal-title fw-bold mb-0" id="pkgDetailModalLabel"></h5>
                    <small class="text-muted" id="pkgDetailCategory"></small>
                </div>
                <span class="badge bg-success fs-6 align-self-center me-2" id="pkgDetailPrice"></span>
                <button type="button" class="btn-close align-self-start mt-1"
                        data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Gallery photos -->
                <div id="pkgDetailPhotos" class="mb-4"></div>
                <!-- Description -->
                <div id="pkgDetailDesc" class="mb-3" style="display:none"></div>
                <!-- Features -->
                <div id="pkgDetailFeatures" class="mb-3" style="display:none"></div>
                <!-- Menu items -->
                <div id="pkgDetailMenus" class="mb-3" style="display:none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Close
                </button>
                <button type="button" class="btn btn-success" id="pkgDetailSelectBtn" data-pkg-id="">
                    <i class="fas fa-check me-1"></i> Select this Package
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JSON data for JS -->
<script>
const baseTotal = <?php echo json_encode($totals['subtotal']); ?>;
const taxRate   = <?php echo json_encode($tax_rate); ?>;
const currency  = <?php echo json_encode(getSetting('currency', 'NPR')); ?>;
const pkgDetailData = <?php echo json_encode($all_packages_modal_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const pkgUploadUrl  = <?php echo json_encode(rtrim(UPLOAD_URL, '/') . '/'); ?>;

document.addEventListener('DOMContentLoaded', function () {

    // ── Skip button: uncheck all packages ───────────────────────────────────
    var skipBtn = document.getElementById('skipPackagesBtn');
    if (skipBtn) {
        skipBtn.addEventListener('click', function () {
            document.querySelectorAll('.package-checkbox').forEach(function (c) {
                c.checked = false;
            });
        });
    }

    // ── Package detail modal helpers ────────────────────────────────────────
    function pkgEscape(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function pkgFmtPrice(amount) {
        var num = parseFloat(amount) || 0;
        var cur = (typeof currency !== 'undefined') ? currency : 'NPR';
        return cur + '\u00a0' + num.toLocaleString('en-NP', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function syncSelectBtn(btn, pkgId) {
        var cb = document.getElementById('pkg' + pkgId);
        if (cb && cb.checked) {
            btn.innerHTML = '<i class="fas fa-times me-1"></i> Deselect this Package';
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-secondary');
        } else {
            btn.innerHTML = '<i class="fas fa-check me-1"></i> Select this Package';
            btn.classList.remove('btn-outline-secondary');
            btn.classList.add('btn-success');
        }
        btn.dataset.pkgId = pkgId;
    }

    function buildMenuHtml(menus) {
        if (!menus || menus.length === 0) return '';
        var html = '<h6 class="fw-bold border-bottom pb-2 mt-3">'
                 + '<i class="fas fa-utensils me-2 text-muted"></i>Included Menus</h6>';
        menus.forEach(function (menu) {
            html += '<div class="card mb-3 border-0 shadow-sm">'
                  + '<div class="card-header py-2 fw-semibold bg-light">'
                  + pkgEscape(menu.name)
                  + '</div><div class="card-body py-2">';
            if (menu.description) {
                html += '<p class="text-muted small mb-2">' + pkgEscape(menu.description) + '</p>';
            }
            if (menu.structure && menu.structure.length > 0) {
                menu.structure.forEach(function (section) {
                    html += '<div class="mb-2"><p class="small text-uppercase text-muted fw-semibold mb-1">'
                          + pkgEscape(section.name) + '</p>';
                    if (section.groups && section.groups.length > 0) {
                        section.groups.forEach(function (group) {
                            html += '<div class="ms-2 mb-1"><span class="small fw-semibold text-secondary">'
                                  + pkgEscape(group.name) + '</span>';
                            if (group.items && group.items.length > 0) {
                                html += '<ul class="list-unstyled ms-3 mb-0 mt-1">';
                                group.items.forEach(function (item) {
                                    html += '<li class="small mb-1"><i class="fas fa-circle-dot fa-xs text-success me-1"></i>'
                                          + pkgEscape(item.item_name);
                                    if (parseFloat(item.extra_charge) > 0) {
                                        html += ' <span class="text-muted">(+' + pkgEscape(String(item.extra_charge)) + ')</span>';
                                    }
                                    html += '</li>';
                                });
                                html += '</ul>';
                            }
                            html += '</div>';
                        });
                    }
                    html += '</div>';
                });
            } else if (menu.flat_items && menu.flat_items.length > 0) {
                // Group flat items by category
                var catMap = {};
                menu.flat_items.forEach(function (item) {
                    var cat = item.category || 'Other';
                    if (!catMap[cat]) catMap[cat] = [];
                    catMap[cat].push(item.item_name);
                });
                Object.keys(catMap).forEach(function (cat) {
                    html += '<div class="mb-1"><span class="small fw-semibold text-muted">'
                          + pkgEscape(cat) + ': </span>'
                          + catMap[cat].map(pkgEscape).join(', ') + '</div>';
                });
            } else {
                html += '<p class="text-muted small mb-0">No menu items available.</p>';
            }
            html += '</div></div>';
        });
        return html;
    }

    function showPkgDetailModal(pkgId) {
        var pkg = pkgDetailData[pkgId];
        if (!pkg) return;

        // Header
        document.getElementById('pkgDetailModalLabel').textContent = pkg.name;
        document.getElementById('pkgDetailCategory').textContent   = pkg.category_name || '';
        document.getElementById('pkgDetailPrice').textContent      = pkgFmtPrice(pkg.price);

        // Photos
        var photosEl = document.getElementById('pkgDetailPhotos');
        if (pkg.photos && pkg.photos.length > 0) {
            if (pkg.photos.length === 1) {
                photosEl.innerHTML = '<img src="' + pkgEscape(pkgUploadUrl + pkg.photos[0])
                    + '" class="d-block w-100 rounded" style="max-height:420px;object-fit:cover;" alt="'
                    + pkgEscape(pkg.name) + '">';
            } else {
                var cid = 'pkgModalCarousel' + pkgId;
                var indicators = '';
                var slides = '';
                pkg.photos.forEach(function (photo, i) {
                    indicators += '<button type="button" data-bs-target="#' + cid
                        + '" data-bs-slide-to="' + i + '"'
                        + (i === 0 ? ' class="active" aria-current="true"' : '')
                        + ' aria-label="Photo ' + (i + 1) + '"></button>';
                    slides += '<div class="carousel-item' + (i === 0 ? ' active' : '') + '">'
                        + '<img src="' + pkgEscape(pkgUploadUrl + photo)
                        + '" class="d-block w-100" style="height:420px;object-fit:cover;" alt="'
                        + pkgEscape(pkg.name) + ' photo ' + (i + 1) + '"></div>';
                });
                photosEl.innerHTML = '<div id="' + cid
                    + '" class="carousel slide rounded overflow-hidden" data-bs-ride="false">'
                    + '<div class="carousel-indicators">' + indicators + '</div>'
                    + '<div class="carousel-inner">' + slides + '</div>'
                    + '<button class="carousel-control-prev" type="button" data-bs-target="#' + cid + '" data-bs-slide="prev">'
                    + '<span class="carousel-control-prev-icon"></span></button>'
                    + '<button class="carousel-control-next" type="button" data-bs-target="#' + cid + '" data-bs-slide="next">'
                    + '<span class="carousel-control-next-icon"></span></button>'
                    + '</div>';
            }
        } else {
            photosEl.innerHTML = '<div class="text-center py-5 bg-light rounded mb-3">'
                + '<i class="fas fa-box fa-3x text-muted"></i>'
                + '<p class="text-muted mt-2 mb-0">No photos available</p></div>';
        }

        // Description
        var descEl = document.getElementById('pkgDetailDesc');
        if (pkg.description) {
            descEl.innerHTML = '<h6 class="fw-bold border-bottom pb-2">'
                + '<i class="fas fa-align-left me-2 text-muted"></i>Description</h6>'
                + '<p class="text-muted">'
                + pkgEscape(pkg.description).replace(/\n/g, '<br>') + '</p>';
            descEl.style.display = '';
        } else {
            descEl.style.display = 'none';
        }

        // Features
        var featEl = document.getElementById('pkgDetailFeatures');
        if (pkg.features && pkg.features.length > 0) {
            var fHtml = '<h6 class="fw-bold border-bottom pb-2">'
                + '<i class="fas fa-list-check me-2 text-muted"></i>Included Features</h6>'
                + '<div class="row g-2">';
            pkg.features.forEach(function (f) {
                fHtml += '<div class="col-6 col-md-4 col-lg-3 d-flex align-items-center gap-2">';
                if (f.service_photo) {
                    fHtml += '<img src="' + pkgEscape(pkgUploadUrl + f.service_photo)
                        + '" class="rounded-circle flex-shrink-0"'
                        + ' style="width:32px;height:32px;object-fit:cover;" alt="">';
                } else {
                    fHtml += '<span class="d-inline-flex align-items-center justify-content-center'
                        + ' bg-success text-white rounded-circle flex-shrink-0"'
                        + ' style="width:32px;height:32px;">'
                        + '<i class="fas fa-check fa-xs"></i></span>';
                }
                fHtml += '<span class="small">' + pkgEscape(f.feature_text) + '</span></div>';
            });
            fHtml += '</div>';
            featEl.innerHTML = fHtml;
            featEl.style.display = '';
        } else {
            featEl.style.display = 'none';
        }

        // Menus
        var menuEl = document.getElementById('pkgDetailMenus');
        var menuHtml = buildMenuHtml(pkg.menus);
        if (menuHtml) {
            menuEl.innerHTML = menuHtml;
            menuEl.style.display = '';
        } else {
            menuEl.style.display = 'none';
        }

        // Sync select button state
        var selectBtn = document.getElementById('pkgDetailSelectBtn');
        syncSelectBtn(selectBtn, pkgId);

        // Show modal
        var modalEl = document.getElementById('pkgDetailModal');
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    // Attach click handler to all "View Full Information" buttons
    document.querySelectorAll('.btn-pkg-details').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            showPkgDetailModal(parseInt(this.dataset.pkgId, 10));
        });
    });

    // Select / deselect from inside the modal
    var selectBtn = document.getElementById('pkgDetailSelectBtn');
    if (selectBtn) {
        selectBtn.addEventListener('click', function () {
            var pkgId = parseInt(this.dataset.pkgId, 10);
            var cb = document.getElementById('pkg' + pkgId);
            if (cb) {
                cb.checked = !cb.checked;
                cb.dispatchEvent(new Event('change', { bubbles: true }));
            }
            syncSelectBtn(this, pkgId);
        });
    }

});
</script>
<?php
$extra_js = '<script src="' . BASE_URL . '/js/booking-step4.js"></script>'
          . '<script src="' . BASE_URL . '/js/design-zoom.js"></script>';
require_once __DIR__ . '/includes/footer.php';
?>
