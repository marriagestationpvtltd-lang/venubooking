<?php
require_once __DIR__ . '/includes/db.php';

const PACKAGE_DETAIL_PATH = '/package-detail.php';

header('Content-Type: application/xml; charset=UTF-8');

function getSitemapBaseUrl(): string {
    if (!empty($_ENV['APP_URL'])) {
        return rtrim($_ENV['APP_URL'], '/');
    }

    $scheme = 'http';
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $scheme = 'https';
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $forwarded = explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO']);
        $scheme = trim($forwarded[0]);
    }

    $host = $_SERVER['SERVER_NAME'] ?? '';
    if ($host === '') {
        $hostCandidate = $_SERVER['HTTP_HOST'] ?? '';
        if ($hostCandidate !== '' && preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*(?::\\d+)?$/i', $hostCandidate)) {
            $host = $hostCandidate;
        }
    }
    $basePath = BASE_URL;
    if ($host === '') {
        return rtrim($basePath, '/');
    }

    $basePath = '/' . ltrim($basePath, '/');
    $basePath = rtrim($basePath, '/');

    return $scheme . '://' . $host . $basePath;
}

function buildSitemapUrl(string $baseUrl, string $path): string {
    return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
}

function getFileLastModified(string $path): string {
    $fullPath = __DIR__ . $path;
    if (file_exists($fullPath)) {
        return gmdate('Y-m-d', filemtime($fullPath));
    }

    return gmdate('Y-m-d');
}

function buildPackageDetailPath(int $packageId): string {
    return PACKAGE_DETAIL_PATH . '?' . http_build_query(['id' => $packageId]);
}

$baseUrl = getSitemapBaseUrl();

$staticPages = [
    ['path' => '/index.php', 'priority' => '1.0', 'changefreq' => 'daily'],
    ['path' => '/venues.php', 'priority' => '0.9', 'changefreq' => 'weekly'],
    ['path' => '/packages.php', 'priority' => '0.9', 'changefreq' => 'weekly'],
    ['path' => '/gallery.php', 'priority' => '0.8', 'changefreq' => 'monthly'],
    ['path' => '/portfolio.php', 'priority' => '0.8', 'changefreq' => 'monthly'],
    ['path' => '/vendors.php', 'priority' => '0.8', 'changefreq' => 'monthly'],
    ['path' => '/testimonials.php', 'priority' => '0.7', 'changefreq' => 'monthly'],
    ['path' => '/about.php', 'priority' => '0.7', 'changefreq' => 'monthly'],
];

$packagePages = [];
$pdo = getDB();
$stmt = $pdo->prepare('SELECT id, updated_at, created_at FROM service_packages WHERE status = ? ORDER BY id LIMIT 50000');
$stmt->execute(['active']);
$packagePages = $stmt->fetchAll();

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($staticPages as $page): ?>
    <url>
        <loc><?php echo htmlspecialchars(buildSitemapUrl($baseUrl, $page['path']), ENT_XML1); ?></loc>
        <lastmod><?php echo htmlspecialchars(getFileLastModified($page['path']), ENT_XML1); ?></lastmod>
        <changefreq><?php echo htmlspecialchars($page['changefreq'], ENT_XML1); ?></changefreq>
        <priority><?php echo htmlspecialchars($page['priority'], ENT_XML1); ?></priority>
    </url>
<?php endforeach; ?>
<?php foreach ($packagePages as $package):
    $lastmodSource = $package['updated_at'] ?? $package['created_at'] ?? null;
    $lastmodDate = $lastmodSource ? gmdate('Y-m-d', strtotime($lastmodSource)) : gmdate('Y-m-d');
    $packagePath = buildPackageDetailPath((int)$package['id']);
?>
    <url>
        <loc><?php echo htmlspecialchars(buildSitemapUrl($baseUrl, $packagePath), ENT_XML1); ?></loc>
        <lastmod><?php echo htmlspecialchars($lastmodDate, ENT_XML1); ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.6</priority>
    </url>
<?php endforeach; ?>
</urlset>
