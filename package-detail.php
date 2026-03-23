<?php
$page_title       = 'Package Details';
$page_description = 'View detailed information about our service package including features, pricing and photos.';
$page_keywords    = 'service package details, venue package, event package, Nepal';
require_once __DIR__ . '/includes/header.php';

$package_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$package_id = $package_id ? (int) $package_id : 0;
$package    = null;
$features   = [];
$photos     = [];

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
                "SELECT feature_text FROM service_package_features
                 WHERE package_id = ? ORDER BY display_order, id"
            );
            $feat_stmt->execute([$package_id]);
            $features = $feat_stmt->fetchAll(PDO::FETCH_COLUMN);

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
        }
    } catch (Exception $e) {
        error_log('package-detail.php error: ' . $e->getMessage());
    }
}

$office_whatsapp       = getSetting('whatsapp_number', '');
$clean_office_whatsapp = preg_replace('/[^0-9]/', '', $office_whatsapp);
$package_share_url     = $package_id > 0 ? BASE_URL . '/package-detail.php?id=' . (string) $package_id : '';
$package_share_id      = $package_id > 0 ? 'package-detail-' . $package_id : '';
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
                    <ul class="pkg-detail-features list-unstyled mb-4">
                        <?php foreach ($features as $feat): ?>
                        <li class="d-flex align-items-start gap-2 mb-2">
                            <span class="text-success mt-1"><i class="fas fa-check-circle"></i></span>
                            <span><?php echo htmlspecialchars($feat, ENT_QUOTES, 'UTF-8'); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>

                    <div class="d-flex flex-column flex-sm-row gap-2 mt-auto">
                        <?php
                        $wa_msg = "Hello, I would like to book this package:\n\nPackage: " . strip_tags($package['name']) . "\nPrice: " . strip_tags(formatCurrency($package['price']));
                        if (!empty($features)) {
                            $wa_msg .= "\n\nFeatures:";
                            foreach ($features as $feat) { $wa_msg .= "\n- " . strip_tags($feat); }
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
                        <div class="section-share-wrap share-inline flex-fill"
                             data-share-wrap="<?php echo htmlspecialchars($package_share_id, ENT_QUOTES, 'UTF-8'); ?>"
                             data-page-url="<?php echo htmlspecialchars($package_share_url, ENT_QUOTES, 'UTF-8'); ?>">
                            <button class="section-share-btn" type="button"
                                    data-section="<?php echo htmlspecialchars($package_share_id, ENT_QUOTES, 'UTF-8'); ?>"
                                    title="Share this package"
                                    aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-share-alt" aria-hidden="true"></i>
                                <span>Share</span>
                            </button>
                            <div class="section-share-dropdown" role="menu" aria-label="Share options">
                                <button class="share-opt share-copy" type="button"
                                        data-section="<?php echo htmlspecialchars($package_share_id, ENT_QUOTES, 'UTF-8'); ?>"
                                        role="menuitem">
                                    <i class="fas fa-link" aria-hidden="true"></i> Copy link
                                </button>
                                <a class="share-opt share-whatsapp"
                                   data-section="<?php echo htmlspecialchars($package_share_id, ENT_QUOTES, 'UTF-8'); ?>"
                                   href="#" role="menuitem" target="_blank" rel="noopener noreferrer">
                                    <i class="fab fa-whatsapp" aria-hidden="true"></i> Share on WhatsApp
                                </a>
                                <a class="share-opt share-facebook"
                                   data-section="<?php echo htmlspecialchars($package_share_id, ENT_QUOTES, 'UTF-8'); ?>"
                                   href="#" role="menuitem" target="_blank" rel="noopener noreferrer">
                                    <i class="fab fa-facebook-f" aria-hidden="true"></i> Share on Facebook
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /.row -->

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
    max-height: 420px;
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
.section-share-wrap {
    position: relative;
    display: inline-block;
}
.section-share-btn {
    background: #f8f9fa;
    border: 1px solid #d9d9d9;
    border-radius: .375rem;
    width: 100%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #555;
    font-size: .95rem;
    transition: background 0.18s, color 0.18s, box-shadow 0.18s;
    box-shadow: 0 1px 4px rgba(0,0,0,0.08);
    padding: .55rem 1rem;
    gap: 6px;
}
.section-share-btn:hover,
.section-share-btn.active {
    background: #f0f8f0;
    color: #2e7d32;
    border-color: #2e7d32;
    box-shadow: 0 2px 8px rgba(46,125,50,0.15);
}
.section-share-dropdown {
    display: none;
    position: absolute;
    top: calc(100% + 6px);
    right: 0;
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.12);
    padding: 6px;
    min-width: 185px;
    z-index: 200;
    text-align: left;
}
.section-share-dropdown.open {
    display: block;
}
.share-opt {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 8px 12px;
    border-radius: 7px;
    text-decoration: none;
    color: #333;
    font-size: 13px;
    border: none;
    background: none;
    width: 100%;
    cursor: pointer;
    white-space: nowrap;
    transition: background 0.15s;
}
.share-opt:hover {
    background: #f5f5f5;
    color: #111;
    text-decoration: none;
}
.share-opt .fab.fa-whatsapp { color: #25D366; }
.share-opt .fab.fa-facebook-f { color: #1877F2; }
.share-opt .fas.fa-link { color: #555; }
.share-copied-toast {
    position: fixed;
    bottom: 28px;
    left: 50%;
    transform: translateX(-50%) translateY(10px);
    background: #2e7d32;
    color: #fff;
    padding: 9px 20px;
    border-radius: 24px;
    font-size: 13px;
    box-shadow: 0 4px 14px rgba(0,0,0,0.18);
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s, transform 0.3s;
    z-index: 9999;
}
.share-copied-toast.show {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
}
.section-share-wrap.share-inline {
    position: relative;
    top: auto;
    right: auto;
    display: block;
    width: 100%;
}
.section-share-wrap.share-inline .section-share-dropdown {
    left: 0;
    right: 0;
    min-width: 100%;
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
</script>
<script>
// ── Package Share Button ──
(function() {
    if (!document.querySelector(\'.section-share-wrap\')) return;
    var toast = document.createElement(\'div\');
    toast.className = \'share-copied-toast\';
    document.body.appendChild(toast);
    var toastTimer = null;
    function showToast(msg) {
        toast.textContent = msg;
        toast.classList.add(\'show\');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function() { toast.classList.remove(\'show\'); }, 2500);
    }
    function getShareUrl(sectionId) {
        var safeId = (window.CSS && CSS.escape) ? CSS.escape(sectionId) : sectionId;
        var wrap = document.querySelector(\'[data-share-wrap="\' + safeId + \'"]\');
        if (wrap) {
            var pageUrl = wrap.getAttribute(\'data-page-url\');
            if (pageUrl) return pageUrl;
        }
        return window.location.href;
    }
    function closeDropdowns() {
        document.querySelectorAll(\'.section-share-dropdown.open\').forEach(function(d) {
            d.classList.remove(\'open\');
        });
        document.querySelectorAll(\'.section-share-btn.active\').forEach(function(b) {
            b.classList.remove(\'active\');
            b.setAttribute(\'aria-expanded\', \'false\');
        });
    }
    function fallbackCopy(text) {
        var el = document.createElement(\'textarea\');
        el.value = text;
        el.style.cssText = \'position:fixed;top:-9999px;left:-9999px;opacity:0;\';
        el.setAttribute(\'aria-hidden\', \'true\');
        document.body.appendChild(el);
        el.select();
        try {
            document.execCommand(\'copy\');
            showToast(\'✓ Link copied!\');
        } catch (err) {
            showToast(\'Unable to copy link\');
        }
        document.body.removeChild(el);
    }
    document.addEventListener(\'click\', function(e) {
        var shareBtn = e.target.closest(\'.section-share-btn\');
        if (shareBtn) {
            e.stopPropagation();
            var wrap = shareBtn.closest(\'.section-share-wrap\');
            var dropdown = wrap ? wrap.querySelector(\'.section-share-dropdown\') : null;
            if (!dropdown) return;
            var isOpen = dropdown.classList.contains(\'open\');
            closeDropdowns();
            if (!isOpen) {
                dropdown.classList.add(\'open\');
                shareBtn.classList.add(\'active\');
                shareBtn.setAttribute(\'aria-expanded\', \'true\');
            }
            return;
        }
        var copyBtn = e.target.closest(\'.share-copy\');
        if (copyBtn) {
            e.preventDefault();
            e.stopPropagation();
            var sectionId = copyBtn.getAttribute(\'data-section\');
            var url = getShareUrl(sectionId);
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function() {
                    showToast(\'✓ Link copied!\');
                }).catch(function() { fallbackCopy(url); });
            } else {
                fallbackCopy(url);
            }
            closeDropdowns();
            return;
        }
        var waBtn = e.target.closest(\'.share-whatsapp\');
        if (waBtn) {
            e.preventDefault();
            e.stopPropagation();
            var url = getShareUrl(waBtn.getAttribute(\'data-section\'));
            var waShareUrl = \'https://wa.me/?text=\' + encodeURIComponent(url);
            waBtn.href = waShareUrl;
            window.open(waShareUrl, \'_blank\', \'noopener\');
            closeDropdowns();
            return;
        }
        var fbBtn = e.target.closest(\'.share-facebook\');
        if (fbBtn) {
            e.preventDefault();
            e.stopPropagation();
            var url = getShareUrl(fbBtn.getAttribute(\'data-section\'));
            var fbShareUrl = \'https://www.facebook.com/sharer/sharer.php?u=\' + encodeURIComponent(url);
            fbBtn.href = fbShareUrl;
            window.open(fbShareUrl, \'_blank\', \'noopener\');
            closeDropdowns();
            return;
        }
        if (!e.target.closest(\'.section-share-wrap\')) {
            closeDropdowns();
        }
    });
}());
</script>
';
require_once __DIR__ . '/includes/footer.php';
?>
