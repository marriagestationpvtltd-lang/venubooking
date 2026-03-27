<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

// Get site settings
$site_name = getSetting('site_name', 'Venue Booking System');
$site_logo = getSetting('site_logo', '');
$site_favicon = getSetting('site_favicon', '');
$meta_title = getSetting('meta_title', '');
$meta_description = getSetting('meta_description', '');
$meta_keywords = getSetting('meta_keywords', '');

// Per-page overrides (set $page_description / $page_keywords before including this header)
if (!empty($page_description)) $meta_description = $page_description;
if (!empty($page_keywords))    $meta_keywords    = $page_keywords;

// Build page title
$full_title = isset($page_title) ? $page_title . ' - ' . $site_name : $site_name;
if (!empty($meta_title)) {
    $full_title = $meta_title;
}
// Per-page title override takes precedence over site-wide meta_title
if (!empty($page_title)) {
    $full_title = $page_title . ' - ' . $site_name;
}

// ── SEO helpers ───────────────────────────────────────────────────────
// Canonical URL: prefer $page_canonical (set before include), else auto-detect
if (empty($page_canonical)) {
    $req_path = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    $page_canonical = rtrim(BASE_URL, '/') . $req_path;
}

// Open Graph image: prefer $page_og_image, then site logo, then empty
$og_image = '';
if (!empty($page_og_image)) {
    $og_image = $page_og_image;
} elseif (!empty($site_logo)) {
    $og_image = rtrim(UPLOAD_URL, '/') . '/' . ltrim($site_logo, '/');
}

// Effective description for OG (fallback to a site-level default)
$og_description = !empty($meta_description) ? $meta_description : $site_name;

// Robots meta: allow per-page override via $page_robots
$robots_meta = !empty($page_robots) ? $page_robots : 'index, follow';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#4CAF50">
    <link rel="manifest" href="<?php echo BASE_URL; ?>/manifest.php">
    <title><?php echo htmlspecialchars($full_title); ?></title>

    <!-- Canonical URL -->
    <link rel="canonical" href="<?php echo htmlspecialchars($page_canonical, ENT_QUOTES, 'UTF-8'); ?>">

    <!-- Robots -->
    <meta name="robots" content="<?php echo htmlspecialchars($robots_meta, ENT_QUOTES, 'UTF-8'); ?>">

    <?php if (!empty($meta_description)): ?>
    <meta name="description" content="<?php echo htmlspecialchars($meta_description); ?>">
    <?php endif; ?>

    <?php if (!empty($meta_keywords)): ?>
    <meta name="keywords" content="<?php echo htmlspecialchars($meta_keywords); ?>">
    <?php endif; ?>

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="<?php echo !empty($page_og_type) ? htmlspecialchars($page_og_type, ENT_QUOTES, 'UTF-8') : 'website'; ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($page_canonical, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($full_title); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($og_description); ?>">
    <meta property="og:site_name" content="<?php echo htmlspecialchars($site_name); ?>">
    <?php if (!empty($og_image)): ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($og_image, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <?php endif; ?>

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($full_title); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($og_description); ?>">
    <?php if (!empty($og_image)): ?>
    <meta name="twitter:image" content="<?php echo htmlspecialchars($og_image, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>

    <?php if (!empty($site_favicon)): ?>
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars(UPLOAD_URL . $site_favicon); ?>">
    <?php endif; ?>

    <!-- Resource hints for faster CDN loading -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://code.jquery.com">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
    <link rel="dns-prefetch" href="https://code.jquery.com">
    <link rel="dns-prefetch" href="https://fonts.googleapis.com">

    <!-- Google Fonts: Poppins + Inter (body/UI) + Playfair Display (legacy headings) + Noto Sans Devanagari (Nepali) -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Noto+Sans+Devanagari:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;0,800;1,600&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/booking.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/responsive.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/nepali-date-picker.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/share.css">

    <!-- JSON-LD Structured Data (WebSite + Organization) -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@graph": [
        {
          "@type": "WebSite",
          "@id": "<?php echo rtrim(BASE_URL, '/'); ?>/#website",
          "url": "<?php echo rtrim(BASE_URL, '/'); ?>/",
          "name": <?php echo json_encode($site_name, JSON_UNESCAPED_UNICODE); ?>,
          "description": <?php echo json_encode($og_description, JSON_UNESCAPED_UNICODE); ?>,
          "potentialAction": {
            "@type": "SearchAction",
            "target": {
              "@type": "EntryPoint",
              "urlTemplate": "<?php echo rtrim(BASE_URL, '/'); ?>/venues.php?search={search_term_string}"
            },
            "query-input": "required name=search_term_string"
          }
        },
        {
          "@type": "Organization",
          "@id": "<?php echo rtrim(BASE_URL, '/'); ?>/#organization",
          "name": <?php echo json_encode($site_name, JSON_UNESCAPED_UNICODE); ?>,
          "url": "<?php echo rtrim(BASE_URL, '/'); ?>/",
          "logo": <?php echo json_encode(!empty($og_image) ? $og_image : rtrim(BASE_URL, '/') . '/', JSON_UNESCAPED_UNICODE); ?>,
          "sameAs": []
        }
      ]
    }
    </script>
    <?php if (!empty($page_schema)): ?>
    <!-- Per-page structured data -->
    <script type="application/ld+json">
    <?php echo $page_schema; ?>
    </script>
    <?php endif; ?>

    <?php if (isset($extra_css)) echo $extra_css; ?>

    <?php if (isset($extra_head)) echo $extra_head; ?>

    <?php
    $ga_id = getSetting('google_analytics_id', '');
    if (!empty($ga_id)):
    ?>
    <!-- Google Analytics (GA4) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo htmlspecialchars($ga_id); ?>"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '<?php echo htmlspecialchars($ga_id); ?>');
    </script>
    <?php endif; ?>
</head>
<body<?php if (!empty($body_class)) echo ' class="' . htmlspecialchars($body_class, ENT_QUOTES, 'UTF-8') . '"'; ?>>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-success" id="mainNavbar">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?php echo BASE_URL; ?>/index.php">
                <?php if (!empty($site_logo)): ?>
                    <img src="<?php echo htmlspecialchars(UPLOAD_URL . $site_logo); ?>" 
                         alt="<?php echo htmlspecialchars($site_name); ?>" 
                         style="max-height: 42px; max-width: 200px;">
                <?php else: ?>
                    <i class="fas fa-building me-1"></i><?php echo htmlspecialchars($site_name); ?>
                <?php endif; ?>
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                    <li class="nav-item">
                        <a class="nav-link px-3" href="<?php echo BASE_URL; ?>/index.php">
                            <i class="fas fa-home me-1 d-lg-none"></i>Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link px-3" href="<?php echo BASE_URL; ?>/venues.php">
                            <i class="fas fa-building me-1 d-lg-none"></i>Venues
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link px-3" href="<?php echo BASE_URL; ?>/packages.php">
                            <i class="fas fa-box-open me-1 d-lg-none"></i>Packages
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle px-3" href="#" id="moreNavDropdown" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false">
                            More
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="moreNavDropdown">
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/gallery.php">
                                <i class="fas fa-images me-2 text-success"></i>Gallery
                            </a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/portfolio.php">
                                <i class="fas fa-folder-open me-2 text-warning"></i>Portfolio
                            </a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/vendors.php">
                                <i class="fas fa-user-tie me-2 text-primary"></i>Our Team
                            </a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/testimonials.php">
                                <i class="fas fa-quote-left me-2 text-info"></i>Testimonials
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/about.php">
                                <i class="fas fa-info-circle me-2 text-secondary"></i>About Us
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <script>
    // Glassmorphism effect: add .navbar-glass class when user scrolls past hero
    (function() {
        var nav = document.getElementById('mainNavbar');
        if (!nav) return;
        var threshold = 80;
        var ticking   = false;
        function update() {
            if (window.scrollY > threshold) {
                nav.classList.add('navbar-glass');
            } else {
                nav.classList.remove('navbar-glass');
            }
            ticking = false;
        }
        window.addEventListener('scroll', function() {
            if (!ticking) {
                requestAnimationFrame(update);
                ticking = true;
            }
        }, { passive: true });
        update();
    }());
    </script>
