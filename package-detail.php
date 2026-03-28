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
$package_halls = [];

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

            // Load menus assigned to this package with full structure
            $package_menus = [];
            $pkg_menu_count = 0;
            try {
                $menu_stmt = $db->prepare(
                    "SELECT m.id, m.name, m.description, m.price_per_person, m.image
                     FROM package_menus pm
                     INNER JOIN menus m ON m.id = pm.menu_id
                     WHERE pm.package_id = ? AND m.status = 'active'
                     ORDER BY m.name"
                );
                $menu_stmt->execute([$package_id]);
                $package_menus = $menu_stmt->fetchAll(PDO::FETCH_ASSOC);
                $pkg_menu_count = count($package_menus);
                foreach ($package_menus as &$pmenu) {
                    try {
                        $pmenu['structure'] = getMenuStructure($pmenu['id']);
                    } catch (Exception $me) {
                        error_log('package-detail.php getMenuStructure error (menu ' . $pmenu['id'] . '): ' . $me->getMessage());
                        $pmenu['structure'] = [];
                    }
                    // Also load simple menu items (menu_items table, grouped by category)
                    try {
                        $mi_stmt = $db->prepare(
                            "SELECT item_name, category, display_order FROM menu_items
                             WHERE menu_id = ? ORDER BY display_order, category, item_name"
                        );
                        $mi_stmt->execute([$pmenu['id']]);
                        $mi_rows = $mi_stmt->fetchAll(PDO::FETCH_ASSOC);
                        $items_by_cat = [];
                        foreach ($mi_rows as $mi_row) {
                            // Use empty string as key for uncategorized items; HTML skips the heading for it
                            $cat = !empty($mi_row['category']) ? $mi_row['category'] : '';
                            $items_by_cat[$cat][] = $mi_row['item_name'];
                        }
                        $pmenu['menu_items_by_category'] = $items_by_cat;
                    } catch (Exception $me) {
                        $pmenu['menu_items_by_category'] = [];
                    }
                }
                unset($pmenu);
            } catch (Exception $e) {
                error_log('package-detail.php menu load error: ' . $e->getMessage());
                $package_menus = [];
            }

            // Load halls/venues assigned to this package
            $package_halls = getPackageHalls($package_id);
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
                             class="d-block w-100 pkg-detail-img pkg-lb-trigger"
                             data-index="<?php echo $pi; ?>"
                             style="cursor:zoom-in"
                             loading="lazy"
                             title="Click to view full screen"
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
                <div class="pkg-detail-thumbs d-flex gap-2 p-2 bg-white">
                    <?php foreach ($photos as $ti => $tpath): ?>
                    <button type="button"
                            class="pkg-detail-thumb-btn <?php echo $ti === 0 ? 'active' : ''; ?>"
                            data-bs-target="#pkgDetailCarousel"
                            data-bs-slide-to="<?php echo $ti; ?>"
                            data-index="<?php echo $ti; ?>"
                            aria-label="Photo <?php echo $ti + 1; ?>">
                        <img src="<?php echo UPLOAD_URL . htmlspecialchars($tpath, ENT_QUOTES, 'UTF-8'); ?>"
                             class="pkg-detail-thumb-img"
                             loading="lazy"
                             alt="Thumbnail <?php echo $ti + 1; ?>">
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <img src="<?php echo UPLOAD_URL . htmlspecialchars($photos[0], ENT_QUOTES, 'UTF-8'); ?>"
                 class="img-fluid rounded shadow-sm pkg-detail-img pkg-lb-trigger"
                 data-index="0"
                 style="cursor:zoom-in"
                 loading="lazy"
                 title="Click to view full screen"
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

                    <?php if (!empty($package_halls)): ?>
                    <div class="pkg-detail-venues mb-3">
                        <?php
                        // Collect unique venues (a package can have halls in the same venue)
                        $seen_venues = [];
                        foreach ($package_halls as $ph) {
                            $vid = $ph['venue_id'];
                            if (!isset($seen_venues[$vid])) {
                                $seen_venues[$vid] = [
                                    'name'     => $ph['venue_name'],
                                    'location' => $ph['venue_location'] ?? '',
                                    'address'  => $ph['venue_address']  ?? '',
                                ];
                            }
                        }
                        ?>
                        <?php foreach ($seen_venues as $venue): ?>
                        <div class="d-flex align-items-start gap-2 mb-1">
                            <i class="fas fa-map-marker-alt text-success mt-1" aria-hidden="true"></i>
                            <div>
                                <span class="fw-semibold"><?php echo htmlspecialchars($venue['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php if (!empty($venue['location'])): ?>
                                <span class="text-muted"> &mdash; <?php echo htmlspecialchars($venue['location'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($venue['address'])): ?>
                                <div class="text-muted small"><?php echo htmlspecialchars($venue['address'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($package['description'])): ?>
                    <p class="text-muted mb-4"><?php echo nl2br(htmlspecialchars($package['description'], ENT_QUOTES, 'UTF-8')); ?></p>
                    <?php endif; ?>

                    <?php if (isset($package['guest_limit']) && $package['guest_limit'] > 0 && !empty($package_menus)): ?>
                    <div class="pkg-detail-guest-limit mb-3">
                        <span class="badge bg-success-subtle text-success border border-success-subtle fs-6 px-3 py-2">
                            <i class="fas fa-utensils me-2"></i>खाना = <?php echo (int)$package['guest_limit']; ?> जना
                        </span>
                    </div>
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

    <?php
    // Extract YouTube video ID from an embed code (<iframe>) or a plain URL.
    // The admin now pastes the full embed code; plain URLs are still supported
    // for backward compatibility with existing entries.
    $youtube_embed_url = '';
    if (!empty($package['youtube_url'])) {
        $raw = trim($package['youtube_url']);
        $yt_id = '';
        $src_to_parse = $raw;
        // If the value looks like an iframe embed code, use DOMDocument to
        // safely extract the src attribute regardless of quote style.
        if (stripos($raw, '<iframe') !== false) {
            $dom = new DOMDocument();
            @$dom->loadHTML($raw, LIBXML_NOERROR | LIBXML_NOWARNING);
            $iframes = $dom->getElementsByTagName('iframe');
            if ($iframes->length > 0) {
                $iframe_src = $iframes->item(0)->getAttribute('src');
                if ($iframe_src !== '') {
                    $src_to_parse = html_entity_decode($iframe_src, ENT_QUOTES, 'UTF-8');
                }
            }
        }
        // Extract the 11-character video ID from any recognised YouTube URL format.
        if (preg_match('/(?:youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/)|youtu\.be\/)([A-Za-z0-9_\-]{11})/', $src_to_parse, $m)) {
            $yt_id = $m[1];
        }
        if ($yt_id !== '' && preg_match('/^[A-Za-z0-9_\-]{11}$/', $yt_id)) {
            // autoplay=1&mute=1: mute is required for autoplay in modern browsers.
            $youtube_embed_url = 'https://www.youtube.com/embed/' . htmlspecialchars($yt_id, ENT_QUOTES, 'UTF-8') . '?autoplay=1&mute=1&rel=0&modestbranding=1';
        }
    }
    ?>

    <?php if (!empty($youtube_embed_url)): ?>
    <div class="row justify-content-center mt-4">
        <div class="col-12 col-lg-10">
            <div class="card shadow-sm border-0">
                <div class="card-body p-3 p-md-4">
                    <h5 class="fw-semibold mb-3"><i class="fab fa-youtube text-danger me-2"></i>Package Video</h5>
                    <div class="pkg-yt-wrap ratio ratio-16x9">
                        <!-- loading="lazy" intentionally omitted: the iframe must load immediately
                             so that the autoplay=1 parameter can trigger on page open. -->
                        <iframe src="<?php echo $youtube_embed_url; ?>"
                                title="<?php echo htmlspecialchars($package['name'], ENT_QUOTES, 'UTF-8'); ?> video"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen
                                class="rounded"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($package_menus)): ?>
    <div class="row justify-content-center mt-4">
        <div class="col-12 col-lg-10">
            <div class="card shadow-sm border-0">
                <div class="card-body p-3 p-md-4">
                    <h5 class="fw-semibold mb-3"><i class="fas fa-utensils text-success me-2"></i>Included Menus</h5>
                    <?php foreach ($package_menus as $pmenu_idx => $pmenu): ?>
                    <div class="pkg-menu-block mb-4">
                        <div class="d-flex align-items-start gap-3 mb-3">
                            <?php if (!empty($pmenu['image'])): ?>
                            <img src="<?php echo UPLOAD_URL . htmlspecialchars($pmenu['image'], ENT_QUOTES, 'UTF-8'); ?>"
                                 class="pkg-menu-thumb rounded"
                                 loading="lazy"
                                 alt="<?php echo htmlspecialchars($pmenu['name'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php endif; ?>
                            <div>
                                <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($pmenu['name'], ENT_QUOTES, 'UTF-8'); ?></h6>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                    <?php echo formatCurrency($pmenu['price_per_person']); ?>/person
                                </span>
                                <?php if (!empty($pmenu['description'])): ?>
                                <p class="text-muted small mb-0 mt-1"><?php echo nl2br(htmlspecialchars($pmenu['description'], ENT_QUOTES, 'UTF-8')); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!empty($pmenu['structure'])): ?>
                        <div class="pkg-menu-sections">
                            <?php foreach ($pmenu['structure'] as $section): ?>
                            <div class="pkg-menu-section mb-3">
                                <div class="pkg-menu-section-title fw-semibold text-success mb-2">
                                    <i class="fas fa-layer-group me-1" aria-hidden="true"></i>
                                    <?php echo htmlspecialchars($section['section_name'], ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if (!empty($section['choose_limit'])): ?>
                                    <span class="text-muted small fw-normal ms-1">(Choose <?php echo (int)$section['choose_limit']; ?>)</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($section['groups'])): ?>
                                <div class="row g-2">
                                    <?php foreach ($section['groups'] as $group): ?>
                                    <div class="col-12 col-sm-6 col-md-4">
                                        <div class="pkg-menu-group p-2 rounded border">
                                            <div class="pkg-menu-group-name text-muted small fw-semibold mb-1">
                                                <?php echo htmlspecialchars($group['group_name'], ENT_QUOTES, 'UTF-8'); ?>
                                                <?php if (!empty($group['choose_limit'])): ?>
                                                <span class="text-muted fw-normal">(Choose <?php echo (int)$group['choose_limit']; ?>)</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($group['items'])): ?>
                                            <ul class="list-unstyled mb-0">
                                                <?php foreach ($group['items'] as $item): ?>
                                                <li class="pkg-menu-item d-flex align-items-center gap-2 py-1">
                                                    <?php if (!empty($item['photo'])): ?>
                                                    <img src="<?php echo UPLOAD_URL . htmlspecialchars($item['photo'], ENT_QUOTES, 'UTF-8'); ?>"
                                                         class="pkg-menu-item-photo rounded-circle"
                                                         loading="lazy"
                                                         alt="<?php echo htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php else: ?>
                                                    <span class="pkg-menu-item-dot" aria-hidden="true"></span>
                                                    <?php endif; ?>
                                                    <span class="small">
                                                        <?php echo htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8'); ?>
                                                        <?php if (!empty($item['sub_category'])): ?>
                                                        <em class="text-muted ms-1"><?php echo htmlspecialchars($item['sub_category'], ENT_QUOTES, 'UTF-8'); ?></em>
                                                        <?php endif; ?>
                                                        <?php if (!empty($item['extra_charge']) && $item['extra_charge'] > 0): ?>
                                                        <span class="text-warning small ms-1">(+<?php echo formatCurrency($item['extra_charge']); ?>)</span>
                                                        <?php endif; ?>
                                                    </span>
                                                </li>
                                                <?php endforeach; ?>
                                            </ul>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($pmenu['menu_items_by_category'])): ?>
                        <div class="pkg-menu-flat-items<?php echo !empty($pmenu['structure']) ? ' mt-3' : ''; ?>">
                            <?php foreach ($pmenu['menu_items_by_category'] as $mi_cat => $mi_names): ?>
                            <div class="pkg-menu-section mb-2">
                                <?php if ($mi_cat !== ''): ?>
                                <div class="pkg-menu-section-title fw-semibold text-success mb-2">
                                    <i class="fas fa-utensils me-1" aria-hidden="true"></i>
                                    <?php echo htmlspecialchars($mi_cat, ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <?php endif; ?>
                                <div class="row g-1">
                                    <?php foreach ($mi_names as $mi_name): ?>
                                    <div class="col-12 col-sm-6 col-md-4">
                                        <div class="pkg-menu-item d-flex align-items-center gap-2 py-1">
                                            <span class="pkg-menu-item-dot" aria-hidden="true"></span>
                                            <span class="small"><?php echo htmlspecialchars($mi_name, ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($pmenu_idx < $pkg_menu_count - 1): ?>
                    <hr class="my-3">
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
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

<!-- Package Photo Lightbox -->
<?php if (!empty($photos)): ?>
<div id="pkgLightbox" class="pkg-lb-overlay" role="dialog" aria-modal="true" aria-label="Photo gallery" style="display:none">
    <div class="pkg-lb-backdrop"></div>
    <div class="pkg-lb-content">
        <button class="pkg-lb-close" id="pkgLbClose" aria-label="Close gallery">&times;</button>
        <div class="pkg-lb-img-wrap">
            <button class="pkg-lb-nav pkg-lb-prev" id="pkgLbPrev" aria-label="Previous photo"><i class="fas fa-chevron-left"></i></button>
            <img id="pkgLbImg" src="" alt="" class="pkg-lb-img" draggable="false">
            <button class="pkg-lb-nav pkg-lb-next" id="pkgLbNext" aria-label="Next photo"><i class="fas fa-chevron-right"></i></button>
        </div>
        <div class="pkg-lb-footer">
            <span id="pkgLbCounter" class="pkg-lb-counter"></span>
        </div>
        <div class="pkg-lb-thumbs" id="pkgLbThumbs"></div>
    </div>
</div>
<?php endif; ?>

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
/* YouTube embed */
.pkg-yt-wrap { border-radius: 0.5rem; overflow: hidden; }
/* Package menus */
.pkg-menu-thumb {
    width: 64px;
    height: 64px;
    object-fit: cover;
    flex-shrink: 0;
}
.pkg-menu-section-title {
    font-size: .9rem;
    border-bottom: 1px solid #e9ecef;
    padding-bottom: .4rem;
}
.pkg-menu-group {
    background: #f8f9fa;
    font-size: .85rem;
}
.pkg-menu-group-name {
    font-size: .8rem;
    text-transform: uppercase;
    letter-spacing: .03em;
}
.pkg-menu-item-photo {
    width: 28px;
    height: 28px;
    object-fit: cover;
    border: 1px solid #dee2e6;
    flex-shrink: 0;
}
.pkg-menu-item-dot {
    display: inline-block;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #198754;
    flex-shrink: 0;
}
/* Package photo lightbox */
.pkg-lb-overlay {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
}
.pkg-lb-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.92);
    cursor: pointer;
}
.pkg-lb-content {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    max-width: 95vw;
    max-height: 95vh;
    width: 100%;
}
.pkg-lb-close {
    position: absolute;
    top: -2.5rem;
    right: 0;
    background: rgba(255,255,255,0.15);
    border: none;
    color: #fff;
    font-size: 2rem;
    line-height: 1;
    width: 2.4rem;
    height: 2.4rem;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background .2s;
}
.pkg-lb-close:hover { background: rgba(25,135,84,0.7); }
.pkg-lb-img-wrap {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
}
.pkg-lb-img {
    max-width: 90vw;
    max-height: 72vh;
    object-fit: contain;
    border-radius: 6px;
    box-shadow: 0 8px 40px rgba(0,0,0,0.6);
    display: block;
    user-select: none;
}
.pkg-lb-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(255,255,255,0.15);
    border: none;
    color: #fff;
    font-size: 1.4rem;
    width: 3rem;
    height: 3rem;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background .2s;
    z-index: 2;
}
.pkg-lb-nav:hover { background: rgba(25,135,84,0.7); }
.pkg-lb-prev { left: 0.25rem; }
.pkg-lb-next { right: 0.25rem; }
.pkg-lb-footer {
    margin-top: 0.6rem;
    color: rgba(255,255,255,0.7);
    font-size: 0.85rem;
    text-align: center;
}
.pkg-lb-thumbs {
    display: flex;
    gap: 0.4rem;
    margin-top: 0.6rem;
    overflow-x: auto;
    max-width: 90vw;
    padding: 0.25rem 0;
    scrollbar-width: thin;
}
.pkg-lb-thumb {
    width: 56px;
    height: 44px;
    object-fit: cover;
    border-radius: 4px;
    cursor: pointer;
    border: 2px solid transparent;
    flex-shrink: 0;
    opacity: 0.65;
    transition: border-color .15s, opacity .15s;
}
.pkg-lb-thumb:hover,
.pkg-lb-thumb.active { border-color: #198754; opacity: 1; }
@media (max-width: 575px) {
    .pkg-lb-nav { width: 2.2rem; height: 2.2rem; font-size: 1rem; }
    .pkg-lb-prev { left: 0; }
    .pkg-lb-next { right: 0; }
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

// Lightbox JS — only output when there are photos to show
if (!empty($photos)) {
    $photos_json = json_encode(
        array_map(function($p) { return UPLOAD_URL . $p; }, $photos),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
    $extra_js .= '
<script>
(function() {
    var photos  = ' . $photos_json . ';
    var overlay = document.getElementById("pkgLightbox");
    if (!overlay || !photos.length) return;
    var img     = document.getElementById("pkgLbImg");
    var counter = document.getElementById("pkgLbCounter");
    var thumbsEl= document.getElementById("pkgLbThumbs");
    var current = 0;

    function buildThumbs() {
        thumbsEl.innerHTML = "";
        photos.forEach(function(src, i) {
            var t = document.createElement("img");
            t.src = src; t.alt = "Photo " + (i + 1) + " of " + photos.length; t.loading = "lazy";
            t.className = "pkg-lb-thumb" + (i === current ? " active" : "");
            t.addEventListener("click", function() { showSlide(i); });
            thumbsEl.appendChild(t);
        });
    }

    function showSlide(idx) {
        current = ((idx % photos.length) + photos.length) % photos.length;
        img.src = photos[current];
        counter.textContent = (current + 1) + " / " + photos.length;
        var thumbs = thumbsEl.querySelectorAll(".pkg-lb-thumb");
        thumbs.forEach(function(t, i) { t.classList.toggle("active", i === current); });
        if (thumbs[current]) { thumbs[current].scrollIntoView({ behavior: "smooth", block: "nearest", inline: "center" }); }
        // Sync Bootstrap carousel if present
        var carousel = document.getElementById("pkgDetailCarousel");
        if (carousel && window.bootstrap) {
            var bsC = bootstrap.Carousel.getInstance(carousel);
            if (bsC) { bsC.to(current); }
        }
    }

    function openLightbox(idx) {
        buildThumbs();
        overlay.style.display = "flex";
        document.body.classList.add("modal-open");
        showSlide(idx);
    }

    function closeLightbox() {
        overlay.style.display = "none";
        document.body.classList.remove("modal-open");
        img.src = "";
    }

    // Trigger from carousel main images
    document.querySelectorAll(".pkg-lb-trigger").forEach(function(el) {
        el.addEventListener("click", function() {
            openLightbox(parseInt(el.dataset.index, 10) || 0);
        });
    });

    // Trigger from thumbnail strip buttons (double-click to open)
    document.querySelectorAll(".pkg-detail-thumb-btn").forEach(function(btn) {
        btn.addEventListener("dblclick", function(e) {
            e.preventDefault();
            openLightbox(parseInt(btn.dataset.index, 10) || 0);
        });
    });

    // Navigation
    document.getElementById("pkgLbClose").addEventListener("click", closeLightbox);
    document.getElementById("pkgLbPrev").addEventListener("click",  function(e) { e.stopPropagation(); showSlide(current - 1); });
    document.getElementById("pkgLbNext").addEventListener("click",  function(e) { e.stopPropagation(); showSlide(current + 1); });
    overlay.querySelector(".pkg-lb-backdrop").addEventListener("click", closeLightbox);

    // Keyboard
    document.addEventListener("keydown", function(e) {
        if (overlay.style.display !== "flex") return;
        if (e.key === "Escape")     { closeLightbox(); }
        if (e.key === "ArrowLeft")  { showSlide(current - 1); }
        if (e.key === "ArrowRight") { showSlide(current + 1); }
    });

    // Touch swipe
    var swipeX = 0;
    overlay.addEventListener("touchstart", function(e) { if (e.touches && e.touches[0]) swipeX = e.touches[0].pageX; }, { passive: true });
    overlay.addEventListener("touchend",   function(e) {
        if (!e.changedTouches || !e.changedTouches[0]) return;
        var dx = e.changedTouches[0].pageX - swipeX;
        if (Math.abs(dx) > 50) { dx < 0 ? showSlide(current + 1) : showSlide(current - 1); }
    }, { passive: true });

    // Keep lightbox in sync when Bootstrap carousel slides
    var carousel = document.getElementById("pkgDetailCarousel");
    if (carousel) {
        carousel.addEventListener("slid.bs.carousel", function(e) {
            if (overlay.style.display === "flex") { showSlide(e.to); }
        });
    }
}());
</script>
';
}

require_once __DIR__ . '/includes/footer.php';
?>
