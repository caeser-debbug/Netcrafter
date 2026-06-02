<?php
header('Content-Type: application/xml; charset=UTF-8');
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$base   = "$scheme://$host";
$today  = date('Y-m-d');
$month  = date('Y-m-d', strtotime('-30 days'));

$pages = [
    // [url, lastmod, changefreq, priority]
    ['/',                         $today, 'daily',   '1.0'],
    ['/service.php',              $month, 'weekly',  '0.9'],
    ['/shop/shop.php',            $month, 'weekly',  '0.8'],
    ['/formation/formations.php', $month, 'weekly',  '0.9'],
    ['/volet.php',                $month, 'monthly', '0.7'],
    ['/portfolio.php',            $month, 'monthly', '0.8'],
    ['/blog/index.php',           $today, 'daily',   '0.9'],
    ['/processus.php',            $month, 'monthly', '0.7'],
    ['/stack.php',                $month, 'monthly', '0.6'],
    ['/configurateur.php',        $month, 'monthly', '0.7'],
    ['/devis.php',                $month, 'weekly',  '0.8'],
    ['/outils/audit.php',         $month, 'monthly', '0.6'],
    ['/outils/palette.php',       $month, 'monthly', '0.5'],
    ['/outils/seo-preview.php',   $month, 'monthly', '0.5'],
    ['/outils/password.php',      $month, 'monthly', '0.5'],
    ['/outils/base64.php',        $month, 'monthly', '0.5'],
];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
echo '        xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

foreach ($pages as [$url, $lastmod, $changefreq, $priority]) {
    $loc = htmlspecialchars($base . $url);
    echo "  <url>\n";
    echo "    <loc>$loc</loc>\n";
    echo "    <lastmod>$lastmod</lastmod>\n";
    echo "    <changefreq>$changefreq</changefreq>\n";
    echo "    <priority>$priority</priority>\n";
    // Alternate lang versions
    echo "    <xhtml:link rel=\"alternate\" hreflang=\"fr\" href=\"$loc?lang=fr\"/>\n";
    echo "    <xhtml:link rel=\"alternate\" hreflang=\"en\" href=\"$loc?lang=en\"/>\n";
    echo "  </url>\n";
}

echo '</urlset>';
