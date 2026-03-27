<?php
$page_title       = 'Package Details';
$page_description = 'View detailed information about our service package including features, pricing and photos.';
$page_keywords    = 'service package details, venue package, event package, Nepal';
require_once __DIR__ . '/includes/header.php';

$package_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$package_id = $package_id ?: 0;
$package    = null;
$features   = [];
$photos     = [];
$pkg_menus  = [];   // menus associated with this package
$pkg_halls  = [];   // halls/venues associated with this package

if ($package_id > 0) {
    $db = getDB();
    try {
        $stmt = $db->prepare(
            "SELECT sp.*, sc.name AS category_name
             FROM service_packages sp
             LEFT JOIN service_categories sc ON sc.id = sp.category_id
             WHERE sp.id = ? AND sp.status = 'active'"
        );
        $stmt->execute([$package_id]);
        $package = $stmt->fetch();

        if ($package) {
            $page_title = htmlspecialchars($package['name']) . ' - Package Details';

            $feat_stmt = $db->prepare(
                "SELECT spf.feature_text, spf.service_id, s.photo AS service_photo
                 FROM service_package_features spf
                 LEFT JOIN additional_services s ON s.id = spf.service_id
                 WHERE spf.package_id = ? ORDER BY spf.display_order, spf.id"
            );
            $feat_stmt->execute([$package_id]);
            $features = $feat_stmt->fetchAll(PDO::FETCH_ASSOC);

            try {
                $photo_stmt = $db->prepare(
                    "SELECT image_path FROM service_package_photos
                     WHERE package_id = ? ORDER BY display_order, id"
                );
                $photo_stmt->execute([$package_id]);
                $photos = $photo_stmt->fetchAll(PDO::FETCH_COLUMN);
            } catch (Exception $e) {
                $photos = [];
            }

            // Also load gallery photos linked via package_gallery_photos
            try {
                $gp_stmt = $db->prepare(
                    "SELECT si.image_path
                     FROM package_gallery_photos pgp
                     INNER JOIN site_images si ON si.id = pgp.site_image_id AND si.status = 'active'
                     WHERE pgp.package_id = ?
                     ORDER BY pgp.display_order, pgp.id"
                );
                $gp_stmt->execute([$package_id]);
                foreach ($gp_stmt->fetchAll(PDO::FETCH_COLUMN) as $gpath) {
                    $safe = !empty($gpath) ? basename($gpath) : '';
                    if (!empty($safe) && preg_match(SAFE_FILENAME_PATTERN, $safe)) {
                        $photos[] = $safe;
                    }
                }
            } catch (Exception $e) {
                // table may not exist yet; silently skip
            }

            // Load menus associated with this package
            try {
                $pm_stmt = $db->prepare(
                    "SELECT m.id, m.name, m.description, m.price_per_person, m.image
                     FROM package_menus pm
                     INNER JOIN menus m ON m.id = pm.menu_id AND m.status = 'active'
                     WHERE pm.package_id = ?
                     ORDER BY m.name"
                );
                $pm_stmt->execute([$package_id]);
                $raw_menus = $pm_stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($raw_menus as $idx => $pmenu) {
                    // Load legacy flat items
                    $mi_stmt = $db->prepare(
                        "SELECT item_name, category FROM menu_items
                         WHERE menu_id = ? ORDER BY display_order, category, id"
                    );
                    $mi_stmt->execute([$pmenu['id']]);
                    $raw_menus[$idx]['items'] = $mi_stmt->fetchAll(PDO::FETCH_ASSOC);

                    // Load structured sections → groups → items (getMenuStructure is defined in includes/functions.php)
                    $raw_menus[$idx]['sections'] = getMenuStructure($pmenu['id']);
                }
                $pkg_menus = $raw_menus;
            } catch (Exception $e) {
                error_log('package-detail.php menu load error (package_id=' . $package_id . '): ' . $e->getMessage());
                $pkg_menus = [];
            }

            // Load halls/venues associated with this package
            try {
                $pv_stmt = $db->prepare(
                    "SELECT h.id AS hall_id, h.name AS hall_name, h.capacity,
                            h.indoor_outdoor, h.base_price AS hall_price,
                            v.id AS venue_id, v.name AS venue_name, v.location
                     FROM package_venues pv
                     INNER JOIN halls h ON h.id = pv.hall_id AND h.status = 'active'
                     INNER JOIN venues v ON v.id = h.venue_id AND v.status = 'active'
                     WHERE pv.package_id = ?
                     ORDER BY v.name, h.name"
                );
                $pv_stmt->execute([$package_id]);
                $pkg_halls = $pv_stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                error_log('package-detail.php venue load error (package_id=' . $package_id . '): ' . $e->getMessage());
                $pkg_halls = [];
            }
        }
    } catch (Exception $e) {
        error_log('package-detail.php error: ' . $e->getMessage());
    }
}

$office_whatsapp       = getSetting('whatsapp_number', '');
$clean_office_whatsapp = preg_replace('/[^0-9]/', '', $office_whatsapp);
$package_share_url     = $package_id ? BASE_URL . '/package-detail.php?' . http_build_query(['id' => $package_id]) : '';
$package_share_id      = $package_id ? 'package-detail-' . $package_id : '';
?>

<!-- Page Hero -->
<div class="page-hero-bar bg-success text-white py-4">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/index.php" class="text-white-50">Home</a></li>
                <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/packages.php" class="text-white-50">Packages</a></li>
                <li class="breadcrumb-item active text-white" aria-current="page">
                    <?php echo $package ? htmlspecialchars($package['name'], ENT_QUOTES, 'UTF-8') : 'Package Details'; ?>
                </li>
            </ol>
        </nav>
        <h1 class="h3 mb-0 fw-bold">
            <i class="fas fa-box-open me-2"></i>
            <?php echo $package ? htmlspecialchars($package['name'], ENT_QUOTES, 'UTF-8') : 'Package Details'; ?>
        </h1>
        <?php if ($package && !empty($package['category_name'])): ?>
        <p class="mb-0 mt-1 text-white-75 small"><?php echo htmlspecialchars($package['category_name'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
    </div>
</div>

<div class="container py-5">
<?php if ($package): ?>
    <div class="row justify-content-center g-4">

        <!-- Photo Column -->
        <?php if (!empty($photos)): ?>
        <div class="col-12 col-md-6 col-lg-5">
            <?php if (count($photos) > 1): ?>
            <div id="pkgDetailCarousel" class="carousel slide rounded shadow-sm overflow-hidden" data-bs-ride="carousel">
                <div class="carousel-inner">
                    <?php foreach ($photos as $pi => $photo_path): ?>
                    <div class="carousel-item <?php echo $pi === 0 ? 'active' : ''; ?>">
                        <img src="<?php echo UPLOAD_URL . htmlspecialchars($photo_path, ENT_QUOTES, 'UTF-8'); ?>"
                             class="d-block w-100 pkg-detail-img"
                             loading="lazy"
                             alt="<?php echo htmlspecialchars($package['name'], ENT_QUOTES, 'UTF-8'); ?> photo <?php echo $pi + 1; ?>">
                    </div>
                    <?php endforeach; ?>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#pkgDetailCarousel" data-bs-slide="prev" aria-label="Previous photo">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#pkgDetailCarousel" data-bs-slide="next" aria-label="Next photo">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Next</span>
                </button>
                <!-- Thumbnail strip -->
                <?php if (count($photos) > 1): ?>
                <div class="pkg-detail-thumbs d-flex gap-2 p-2 bg-white">
                    <?php foreach ($photos as $ti => $tpath): ?>
                    <button type="button"
                            class="pkg-detail-thumb-btn <?php echo $ti === 0 ? 'active' : ''; ?>"
                            data-bs-target="#pkgDetailCarousel"
                            data-bs-slide-to="<?php echo $ti; ?>"
                            aria-label="Photo <?php echo $ti + 1; ?>">
                        <img src="<?php echo UPLOAD_URL . htmlspecialchars($tpath, ENT_QUOTES, 'UTF-8'); ?>"
                             class="pkg-detail-thumb-img"
                             loading="lazy"
                             alt="Thumbnail <?php echo $ti + 1; ?>">
                    </button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <img src="<?php echo UPLOAD_URL . htmlspecialchars($photos[0], ENT_QUOTES, 'UTF-8'); ?>"
                 class="img-fluid rounded shadow-sm pkg-detail-img"
                 loading="lazy"
                 alt="<?php echo htmlspecialchars($package['name'], ENT_QUOTES, 'UTF-8'); ?> photo">
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Details Column -->
        <div class="col-12 <?php echo !empty($photos) ? 'col-md-6 col-lg-7' : ''; ?>">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body p-4">

                    <?php if (!empty($package['category_name'])): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle mb-3 px-3 py-2">
                        <?php echo htmlspecialchars($package['category_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                    <?php endif; ?>

                    <h2 class="h4 fw-bold mb-2"><?php echo htmlspecialchars($package['name'], ENT_QUOTES, 'UTF-8'); ?></h2>

                    <div class="pkg-detail-price mb-3">
                        <span class="h3 fw-bold text-success"><?php echo formatCurrency($package['price']); ?></span>
                    </div>

                    <?php if (!empty($package['description'])): ?>
                    <p class="text-muted mb-4"><?php echo nl2br(htmlspecialchars($package['description'], ENT_QUOTES, 'UTF-8')); ?></p>
                    <?php endif; ?>

                    <?php if (!empty($features)): ?>
                    <h5 class="fw-semibold mb-3"><i class="fas fa-list-check me-2 text-success"></i>Package Features</h5>
                    <div class="pkg-service-icons d-flex flex-wrap gap-3 mb-4">
                        <?php foreach ($features as $feat): ?>
                        <div class="pkg-service-icon-item text-center">
                            <?php if (!empty($feat['service_photo'])): ?>
                            <img src="<?php echo UPLOAD_URL . htmlspecialchars($feat['service_photo'], ENT_QUOTES, 'UTF-8'); ?>"
                                 class="pkg-service-icon-img"
                                 loading="lazy"
                                 alt="<?php echo htmlspecialchars($feat['feature_text'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php else: ?>
                            <div class="pkg-service-icon-fallback">
                                <i class="fas fa-check" aria-hidden="true"></i>
                            </div>
                            <?php endif; ?>
                            <p class="pkg-service-icon-label"><?php echo htmlspecialchars($feat['feature_text'], ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($pkg_menus)): ?>
                    <h5 class="fw-semibold mb-3 mt-2"><i class="fas fa-utensils me-2 text-success"></i>Included Menus</h5>
                    <div class="d-flex flex-wrap gap-2 mb-4">
                        <?php foreach ($pkg_menus as $pm): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fs-6">
                            <i class="fas fa-concierge-bell me-1"></i><?php echo htmlspecialchars($pm['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($pkg_halls)): ?>
                    <h5 class="fw-semibold mb-3 mt-2"><i class="fas fa-map-marker-alt me-2 text-success"></i>Available Venues / Halls</h5>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <thead class="table-success">
                                <tr>
                                    <th>Venue</th>
                                    <th>Hall</th>
                                    <th>Capacity</th>
                                    <th>Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pkg_halls as $ph): ?>
                                <tr>
                                    <td>
                                        <span class="fw-semibold"><?php echo htmlspecialchars($ph['venue_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php if (!empty($ph['location'])): ?>
                                        <br><small class="text-muted"><i class="fas fa-map-pin me-1"></i><?php echo htmlspecialchars($ph['location'], ENT_QUOTES, 'UTF-8'); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($ph['hall_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo number_format((int)$ph['capacity']); ?> guests</td>
                                    <td><?php echo ucfirst(htmlspecialchars($ph['indoor_outdoor'], ENT_QUOTES, 'UTF-8')); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>

                    <div class="d-flex flex-column flex-sm-row gap-2 mt-auto">
                        <a href="<?php echo BASE_URL; ?>/package-booking.php?id=<?php echo (int)$package_id; ?>" class="btn btn-success flex-fill fw-semibold">
                            <i class="fas fa-calendar-check me-2"></i>Book Now
                        </a>
                        <?php
                        $wa_msg = "Hello, I would like to book this package:\n\nPackage: " . strip_tags($package['name']) . "\nPrice: " . strip_tags(formatCurrency($package['price']));
                        if (!empty($features)) {
                            $wa_msg .= "\n\nFeatures:";
                            foreach ($features as $feat) { $wa_msg .= "\n- " . strip_tags($feat['feature_text']); }
                        }
                        if (!empty($package['description'])) {
                            $wa_msg .= "\n\nDescription:\n" . strip_tags($package['description']);
                        }
                        $wa_msg .= "\n\nPlease provide me with more details.";
                        ?>
                        <?php if (!empty($clean_office_whatsapp)): ?>
                        <a href="https://wa.me/<?php echo htmlspecialchars($clean_office_whatsapp, ENT_QUOTES, 'UTF-8'); ?>?text=<?php echo rawurlencode($wa_msg); ?>"
                           target="_blank" rel="noopener noreferrer"
                           class="btn btn-success flex-fill">
                            <i class="fab fa-whatsapp me-2"></i>Contact Us
                        </a>
                        <?php else: ?>
                        <button class="btn btn-success flex-fill" disabled>
                            <i class="fab fa-whatsapp me-2"></i>Contact Us
                        </button>
                        <?php endif; ?>
                        <a href="<?php echo BASE_URL; ?>/packages.php" class="btn btn-outline-secondary flex-fill">
                            <i class="fas fa-arrow-left me-2"></i>Back to Packages
                        </a>
                        <?php if (!empty($package_share_url)): ?>
                        <div class="dropdown flex-fill"
                             data-share-wrap="<?php echo htmlspecialchars($package_share_id, ENT_QUOTES, 'UTF-8'); ?>"
                             data-page-url="<?php echo htmlspecialchars($package_share_url, ENT_QUOTES, 'UTF-8'); ?>">
                            <button class="btn btn-outline-success w-100 pkg-share-toggle"
                                    type="button"
                                    id="pkgShareDropdown"
                                    data-bs-toggle="dropdown"
                                    data-bs-auto-close="true"
                                    aria-expanded="false"
                                    title="Share this package">
                                <i class="fas fa-share-alt me-2" aria-hidden="true"></i>Share
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end w-100 py-1"
                                aria-labelledby="pkgShareDropdown">
                                <li>
                                    <button class="dropdown-item share-copy d-flex align-items-center gap-2"
                                            type="button"
                                            data-section="<?php echo htmlspecialchars($package_share_id, ENT_QUOTES, 'UTF-8'); ?>">
                                        <i class="fas fa-link text-muted" aria-hidden="true"></i>
                                        <span>Copy link</span>
                                    </button>
                                </li>
                                <li>
                                    <a class="dropdown-item share-whatsapp d-flex align-items-center gap-2"
                                       href="#"
                                       data-section="<?php echo htmlspecialchars($package_share_id, ENT_QUOTES, 'UTF-8'); ?>"
                                       target="_blank" rel="noopener noreferrer">
                                        <i class="fab fa-whatsapp text-whatsapp" aria-hidden="true"></i>
                                        <span>Share on WhatsApp</span>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item share-facebook d-flex align-items-center gap-2"
                                       href="#"
                                       data-section="<?php echo htmlspecialchars($package_share_id, ENT_QUOTES, 'UTF-8'); ?>"
                                       target="_blank" rel="noopener noreferrer">
                                        <i class="fab fa-facebook-f text-facebook" aria-hidden="true"></i>
                                        <span>Share on Facebook</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /.row -->

    <?php if (!empty($pkg_menus)): ?>
    <!-- Full-width Menu Detail Section -->
    <div class="row mt-5">
        <div class="col-12">
            <h4 class="fw-bold mb-4"><i class="fas fa-utensils me-2 text-success"></i>Menu Details</h4>
            <?php foreach ($pkg_menus as $pm_idx => $pm): ?>
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-success text-white d-flex align-items-center gap-3 py-3">
                    <?php if (!empty($pm['image'])): ?>
                    <img src="<?php echo UPLOAD_URL . htmlspecialchars($pm['image'], ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars($pm['name'], ENT_QUOTES, 'UTF-8'); ?>"
                         class="pkg-menu-img rounded-circle"
                         loading="lazy">
                    <?php else: ?>
                    <div class="pkg-menu-icon-fallback"><i class="fas fa-concierge-bell"></i></div>
                    <?php endif; ?>
                    <div>
                        <h5 class="mb-0 fw-semibold"><?php echo htmlspecialchars($pm['name'], ENT_QUOTES, 'UTF-8'); ?></h5>
                        <?php if (!empty($pm['price_per_person']) && $pm['price_per_person'] > 0): ?>
                        <small><?php echo formatCurrency($pm['price_per_person']); ?> / person</small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($pm['description'])): ?>
                    <p class="text-muted mb-4"><?php echo nl2br(htmlspecialchars($pm['description'], ENT_QUOTES, 'UTF-8')); ?></p>
                    <?php endif; ?>

                    <?php if (!empty($pm['sections'])): ?>
                    <!-- Structured menu: sections → groups → items -->
                    <div class="accordion pkg-menu-accordion" id="menuAccordion<?php echo $pm_idx; ?>">
                        <?php foreach ($pm['sections'] as $sec_idx => $section): ?>
                        <?php $sec_id = 'menuSec' . $pm_idx . '_' . $sec_idx; ?>
                        <div class="accordion-item border mb-2 rounded">
                            <h2 class="accordion-header" id="hd<?php echo $sec_id; ?>">
                                <button class="accordion-button fw-semibold <?php echo $sec_idx > 0 ? 'collapsed' : ''; ?>"
                                        type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#cl<?php echo $sec_id; ?>"
                                        aria-expanded="<?php echo $sec_idx === 0 ? 'true' : 'false'; ?>"
                                        aria-controls="cl<?php echo $sec_id; ?>">
                                    <i class="fas fa-layer-group me-2 text-success"></i>
                                    <?php echo htmlspecialchars($section['section_name'] ?? $section['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if (!empty($section['choose_limit'])): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle ms-2">Choose <?php echo (int)$section['choose_limit']; ?></span>
                                    <?php endif; ?>
                                </button>
                            </h2>
                            <div id="cl<?php echo $sec_id; ?>"
                                 class="accordion-collapse collapse <?php echo $sec_idx === 0 ? 'show' : ''; ?>"
                                 aria-labelledby="hd<?php echo $sec_id; ?>"
                                 data-bs-parent="#menuAccordion<?php echo $pm_idx; ?>">
                                <div class="accordion-body pt-2 pb-3">
                                    <?php if (!empty($section['groups'])): ?>
                                    <?php foreach ($section['groups'] as $grp): ?>
                                    <div class="pkg-menu-group mb-3">
                                        <h6 class="fw-semibold text-success mb-2">
                                            <i class="fas fa-chevron-right me-1 small"></i>
                                            <?php echo htmlspecialchars($grp['group_name'] ?? $grp['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                        </h6>
                                        <?php if (!empty($grp['items'])): ?>
                                        <div class="d-flex flex-wrap gap-2 ps-3">
                                            <?php foreach ($grp['items'] as $gitem): ?>
                                            <div class="pkg-menu-item-chip d-flex align-items-center gap-2">
                                                <?php if (!empty($gitem['photo'])): ?>
                                                <img src="<?php echo UPLOAD_URL . htmlspecialchars($gitem['photo'], ENT_QUOTES, 'UTF-8'); ?>"
                                                     alt="<?php echo htmlspecialchars($gitem['item_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                     class="pkg-item-photo"
                                                     loading="lazy">
                                                <?php else: ?>
                                                <span class="pkg-item-dot" aria-hidden="true"></span>
                                                <?php endif; ?>
                                                <span><?php echo htmlspecialchars($gitem['item_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                <?php if (!empty($gitem['extra_charge']) && $gitem['extra_charge'] > 0): ?>
                                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle small">+<?php echo formatCurrency($gitem['extra_charge']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php else: ?>
                                        <p class="text-muted small ps-3 mb-0">No items in this group.</p>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php else: ?>
                                    <p class="text-muted small mb-0">No groups in this section.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <?php elseif (!empty($pm['items'])): ?>
                    <!-- Flat menu items grouped by category -->
                    <?php
                    $by_cat = [];
                    foreach ($pm['items'] as $mi) {
                        $cat = !empty($mi['category']) ? $mi['category'] : 'Items';
                        $by_cat[$cat][] = $mi['item_name'];
                    }
                    ?>
                    <?php foreach ($by_cat as $cat_name => $cat_items): ?>
                    <div class="mb-3">
                        <h6 class="fw-semibold text-success mb-2">
                            <i class="fas fa-chevron-right me-1 small"></i>
                            <?php echo htmlspecialchars($cat_name, ENT_QUOTES, 'UTF-8'); ?>
                        </h6>
                        <div class="d-flex flex-wrap gap-2 ps-3">
                            <?php foreach ($cat_items as $iname): ?>
                            <div class="pkg-menu-item-chip d-flex align-items-center gap-2">
                                <span class="pkg-item-dot" aria-hidden="true"></span>
                                <span><?php echo htmlspecialchars($iname, ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php else: ?>
                    <p class="text-muted mb-0"><em>No menu items available.</em></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

<?php else: ?>
    <!-- Package not found -->
    <div class="text-center py-5">
        <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
        <h3 class="text-muted">Package not found.</h3>
        <p class="text-muted">The package you are looking for does not exist or is no longer available.</p>
        <a href="<?php echo BASE_URL; ?>/packages.php" class="btn btn-success mt-3">
            <i class="fas fa-arrow-left me-1"></i> Browse All Packages
        </a>
    </div>
<?php endif; ?>
</div>

<!-- Floating WhatsApp Button -->
<?php if (!empty($clean_office_whatsapp)): ?>
<a href="https://wa.me/<?php echo htmlspecialchars($clean_office_whatsapp, ENT_QUOTES, 'UTF-8'); ?>?text=<?php echo rawurlencode('Hello! I would like to know more about your service packages. Please help me.'); ?>"
   class="floating-wa-btn"
   target="_blank" rel="noopener noreferrer"
   aria-label="Contact us on WhatsApp"
   title="Chat on WhatsApp">
    <span class="floating-wa-pulse" aria-hidden="true"></span>
    <i class="fab fa-whatsapp wa-fab-icon"></i>
    <span class="wa-fab-text">Chat with Us</span>
</a>
<?php endif; ?>

<button class="scroll-top-fab" id="scrollTopFab" aria-label="Scroll to top" title="Back to top">
    <i class="fas fa-chevron-up"></i>
</button>

<style>
.pkg-detail-img {
    width: 100%;
    max-height: 560px;
    object-fit: cover;
}
.pkg-detail-thumbs {
    overflow-x: auto;
    scrollbar-width: thin;
}
.pkg-detail-thumb-btn {
    border: 2px solid transparent;
    border-radius: 6px;
    padding: 0;
    background: none;
    flex-shrink: 0;
    cursor: pointer;
    transition: border-color .2s;
}
.pkg-detail-thumb-btn.active,
.pkg-detail-thumb-btn:hover {
    border-color: #198754;
}
.pkg-detail-thumb-img {
    width: 60px;
    height: 48px;
    object-fit: cover;
    border-radius: 4px;
    display: block;
}
.pkg-detail-price .h3 {
    font-size: 2rem;
}
.pkg-detail-features li {
    font-size: .95rem;
}
/* Included-service rounded icons */
.pkg-service-icons {
    flex-wrap: wrap;
}
.pkg-service-icon-item {
    width: 80px;
}
.pkg-service-icon-img {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #198754;
    display: block;
    margin: 0 auto;
}
.pkg-service-icon-fallback {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: #d1e7dd;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    color: #198754;
    font-size: 1.5rem;
}
.pkg-service-icon-label {
    font-size: 0.72rem;
    margin-top: 0.35rem;
    line-height: 1.25;
    color: #333;
    word-break: break-word;
}
/* Share dropdown icon sizing */
.pkg-share-toggle .fa-share-alt { font-size: .9em; }
.dropdown-item .fab,
.dropdown-item .fas { width: 1.1em; text-align: center; flex-shrink: 0; }
.text-whatsapp  { color: #25D366 !important; }
.text-facebook  { color: #1877F2 !important; }
/* Menu detail section */
.pkg-menu-img {
    width: 48px;
    height: 48px;
    object-fit: cover;
    border: 2px solid rgba(255,255,255,.5);
    flex-shrink: 0;
}
.pkg-menu-icon-fallback {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: rgba(255,255,255,.25);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    flex-shrink: 0;
}
.pkg-menu-group {
    border-left: 3px solid #d1e7dd;
    padding-left: .75rem;
}
.pkg-menu-item-chip {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 20px;
    padding: .25rem .75rem;
    font-size: .875rem;
}
.pkg-item-photo {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
}
.pkg-item-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #198754;
    display: inline-block;
    flex-shrink: 0;
}
.pkg-menu-accordion .accordion-button:not(.collapsed) {
    background-color: #d1e7dd;
    color: #0a3622;
}
.pkg-menu-accordion .accordion-button::after {
    filter: none;
}
</style>

<?php
$extra_js = '
<script>
(function() {
    var btn = document.getElementById("scrollTopFab");
    if (!btn) return;
    var ticking = false;
    window.addEventListener("scroll", function() {
        if (!ticking) { requestAnimationFrame(function() { btn.classList.toggle("visible", window.scrollY > 400); ticking = false; }); ticking = true; }
    }, { passive: true });
    btn.addEventListener("click", function() { window.scrollTo({ top: 0, behavior: "smooth" }); });
}());
// Close Bootstrap share dropdown after a share action fires
(function() {
    document.addEventListener("click", function(e) {
        if (!e.target.closest(".share-copy, .share-whatsapp, .share-facebook")) return;
        var toggleEl = document.getElementById("pkgShareDropdown");
        if (toggleEl && window.bootstrap) {
            var dd = bootstrap.Dropdown.getInstance(toggleEl);
            if (dd) dd.hide();
        }
    }, true); // capture phase runs before share.js stopPropagation
}());
</script>
';
require_once __DIR__ . '/includes/footer.php';
?>
