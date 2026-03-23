<?php
require_once __DIR__ . '/includes/db.php';

const PACKAGE_DETAIL_PATH = '/package-detail.php';
const DEFAULT_SITEMAP_PACKAGE_LIMIT = 10000;

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
        if ($hostCandidate !== '') {
            $parsedHost = parse_url('http://' . $hostCandidate);
            $hostName = $parsedHost['host'] ?? '';
            $port = $parsedHost['port'] ?? null;
            $isIpHost = $hostName !== '' && filter_var($hostName, FILTER_VALIDATE_IP);
            $isDomainHost = $hostName !== '' && preg_match('/^[a-z]/i', $hostName) && filter_var($hostName, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
            if ($isIpHost || $isDomainHost) {
                $host = $hostName . ($port ? ':' . $port : '');
            }
        }
    }
    $basePath = defined('BASE_URL') ? BASE_URL : '';
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
$packageLimit = isset($_ENV['SITEMAP_PACKAGE_LIMIT']) ? (int) $_ENV['SITEMAP_PACKAGE_LIMIT'] : DEFAULT_SITEMAP_PACKAGE_LIMIT;
$packageLimit = max(1, min(50000, $packageLimit));
$stmt = $pdo->prepare('SELECT id, updated_at, created_at FROM service_packages WHERE status = ? ORDER BY id LIMIT ' . $packageLimit);
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
