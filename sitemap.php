<?php
/**
 * SITEMAP.PHP — Génération dynamique du sitemap XML
 * Accessible sans authentification.
 */
require_once __DIR__ . '/functions/utils.php';

header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex');

$base = 'https://adnmovie.fr';

$publicContent = db_fetch_all(
    "SELECT slug, updated_at FROM user_content WHERE is_public = 1 ORDER BY updated_at DESC",
    []
) ?: [];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// Pages statiques publiques
$staticPages = [
    ['loc' => $base . '/archives.php', 'changefreq' => 'daily',  'priority' => '0.8'],
    ['loc' => $base . '/',             'changefreq' => 'monthly', 'priority' => '0.5'],
];
foreach ($staticPages as $p) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($p['loc']) . "</loc>\n";
    echo "    <changefreq>{$p['changefreq']}</changefreq>\n";
    echo "    <priority>{$p['priority']}</priority>\n";
    echo "  </url>\n";
}

// Pages de contenu utilisateur publiques
foreach ($publicContent as $row) {
    $loc     = $base . '/content.php?slug=' . rawurlencode($row['slug']);
    $lastmod = date('Y-m-d', strtotime($row['updated_at']));
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($loc) . "</loc>\n";
    echo "    <lastmod>{$lastmod}</lastmod>\n";
    echo "    <changefreq>monthly</changefreq>\n";
    echo "    <priority>0.6</priority>\n";
    echo "  </url>\n";
}

echo '</urlset>';
