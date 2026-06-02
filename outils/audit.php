<?php
// ─── Language setup ───────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
$_d = fn($fr, $en) => (($_SESSION['nc_lang'] ?? 'fr') === 'en') ? $en : $fr;

// ═══════════════════════════════════════════════════════════
//  AUDIT COMPLET — Netcrafter Outils v2
//  30+ checks: SEO, Technique, Sécurité, Social, Performance, Accessibilité
// ═══════════════════════════════════════════════════════════

$audit_result = null;
$audit_error  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['url'])) {

    $raw_url = trim($_POST['url']);
    if (!preg_match('#^https?://#i', $raw_url)) $raw_url = 'https://' . $raw_url;

    $parsed_url  = parse_url($raw_url);
    $base_domain = ($parsed_url['scheme'] ?? 'https') . '://' . ($parsed_url['host'] ?? '');

    $is_ajax = isset($_POST['ajax'])
        || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

    $ctx = stream_context_create([
        'http' => [
            'timeout'         => 12,
            'method'          => 'GET',
            'user_agent'      => 'Mozilla/5.0 (compatible; NetcrafterAudit/2.0)',
            'follow_location' => true,
            'max_redirects'   => 5,
            'ignore_errors'   => true,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);

    // ── Fetch main page ────────────────────────────────────────────────────────
    $t_start = microtime(true);
    $html = @file_get_contents($raw_url, false, $ctx);
    $t_end = microtime(true);
    $load_time_ms = round(($t_end - $t_start) * 1000);
    $resp_headers = $http_response_header ?? [];

    if ($html === false) {
        $audit_error = $_d(
            "Impossible d'accéder à l'URL. Vérifiez qu'elle est publiquement accessible.",
            "Cannot access the URL. Make sure it is publicly accessible."
        );
    } else {
        $page_size_kb = round(strlen($html) / 1024, 1);
        $is_https     = stripos($raw_url, 'https://') === 0;

        // ── Parse HTTP response headers ────────────────────────────────────────
        $h_map = [];
        $http_status = 200;
        foreach ($resp_headers as $line) {
            if (preg_match('/^([^:]+):\s*(.+)$/i', $line, $m))
                $h_map[strtolower(trim($m[1]))] = trim($m[2]);
            if (preg_match('#HTTP/[\d\.]+\s+(\d+)#i', $line, $m))
                $http_status = (int)$m[1];
        }
        $has_csp    = isset($h_map['content-security-policy']);
        $has_xframe = isset($h_map['x-frame-options']);
        $has_xctype = isset($h_map['x-content-type-options']);
        $has_hsts   = isset($h_map['strict-transport-security']);
        $has_refpol = isset($h_map['referrer-policy']);
        $has_xss    = isset($h_map['x-xss-protection']);

        // ── DOM parsing ────────────────────────────────────────────────────────
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8"?>' . $html);
        libxml_clear_errors();
        $xpath = new DOMXPath($doc);

        // Title
        $title_node = $xpath->query('//title')->item(0);
        $title      = $title_node ? trim($title_node->textContent) : '';
        $title_len  = mb_strlen($title);

        // Meta tags
        $meta_desc = $meta_keywords = $meta_robots = $meta_author = '';
        $has_viewport = false;
        foreach ($xpath->query('//meta[@name]') as $m) {
            $n = strtolower($m->getAttribute('name'));
            $c = trim($m->getAttribute('content'));
            match($n) {
                'description' => $meta_desc     = $c,
                'keywords'    => $meta_keywords  = $c,
                'viewport'    => ($has_viewport = true),
                'robots'      => $meta_robots    = strtolower($c),
                'author'      => $meta_author    = $c,
                default       => null,
            };
        }
        $meta_desc_len = mb_strlen($meta_desc);
        $is_noindex    = str_contains($meta_robots, 'noindex');

        // Canonical
        $has_canonical = false;
        $canonical_url = '';
        foreach ($xpath->query('//link[@rel]') as $l) {
            if (strtolower($l->getAttribute('rel')) === 'canonical') {
                $has_canonical = true;
                $canonical_url = $l->getAttribute('href');
                break;
            }
        }

        // HTML lang — documentElement is more reliable than XPath //html with XML declarations
        $html_el   = $doc->documentElement;
        $lang_attr = $html_el ? trim($html_el->getAttribute('lang')) : '';
        // Regex fallback in case DOM parsing misses it (e.g. malformed HTML, BOM, PHP output before DOCTYPE)
        if ($lang_attr === '' && preg_match('/<html[^>]+lang=["\']([^"\']+)["\']/', $html, $_lm)) {
            $lang_attr = trim($_lm[1]);
        }
        $has_lang  = $lang_attr !== '';

        // Charset
        $has_charset = stripos($html, 'charset') !== false;

        // Open Graph
        $og = ['title'=>'','description'=>'','image'=>'','url'=>'','type'=>'','site_name'=>''];
        foreach ($xpath->query('//meta[@property]') as $m) {
            $p = strtolower($m->getAttribute('property'));
            $c = trim($m->getAttribute('content'));
            if (str_starts_with($p, 'og:')) {
                $key = substr($p, 3);
                if (isset($og[$key])) $og[$key] = $c;
            }
        }

        // Twitter Card
        $tw = ['card'=>'','title'=>'','description'=>'','image'=>''];
        foreach ($xpath->query('//meta[@name]') as $m) {
            $n = strtolower($m->getAttribute('name'));
            if (str_starts_with($n, 'twitter:')) {
                $key = substr($n, 8);
                if (isset($tw[$key])) $tw[$key] = trim($m->getAttribute('content'));
            }
        }

        // Headings
        $h1_nodes = $xpath->query('//h1');
        $h1_count = $h1_nodes->length;
        $h2_count = $xpath->query('//h2')->length;
        $h3_count = $xpath->query('//h3')->length;
        $h4_count = $xpath->query('//h4')->length;
        $h1_texts = [];
        foreach ($h1_nodes as $h) $h1_texts[] = trim($h->textContent);

        // Images
        $img_nodes    = $xpath->query('//img');
        $total_imgs   = $img_nodes->length;
        $imgs_no_alt  = 0;
        $imgs_decorative = 0;
        foreach ($img_nodes as $img) {
            if (!$img->hasAttribute('alt'))          $imgs_no_alt++;
            elseif (trim($img->getAttribute('alt')) === '') $imgs_decorative++;
        }

        // Links
        $link_nodes    = $xpath->query('//a[@href]');
        $total_links   = $link_nodes->length;
        $ext_links = $int_links = $nofollow_links = 0;
        $host_check = $parsed_url['host'] ?? '';
        foreach ($link_nodes as $a) {
            $href = $a->getAttribute('href');
            $rel  = strtolower($a->getAttribute('rel'));
            if (str_contains($rel, 'nofollow')) $nofollow_links++;
            if (preg_match('#^https?://#i', $href)) {
                $h = parse_url($href, PHP_URL_HOST) ?? '';
                if ($h && $h !== $host_check) $ext_links++;
                else $int_links++;
            } else $int_links++;
        }

        // Scripts / styles
        $js_count  = $xpath->query('//script[@src]')->length;
        $css_count = $xpath->query('//link[@rel="stylesheet"]')->length;
        $inline_js = $xpath->query('//script[not(@src)]')->length;
        $inline_css = substr_count($html, 'style="');

        // Favicon
        $has_favicon = false;
        foreach ($xpath->query('//link[@rel]') as $l) {
            if (str_contains(strtolower($l->getAttribute('rel')), 'icon')) {
                $has_favicon = true; break;
            }
        }
        if (!$has_favicon) {
            $fav = @file_get_contents($base_domain . '/favicon.ico', false, $ctx);
            if ($fav !== false && strlen($fav) > 100) $has_favicon = true;
        }

        // Structured data (JSON-LD / microdata)
        $has_schema    = false;
        $schema_types  = [];
        foreach ($xpath->query('//script[@type="application/ld+json"]') as $s) {
            $json_data = @json_decode(trim($s->textContent), true);
            if ($json_data) {
                $has_schema = true;
                $type = $json_data['@type'] ?? ($json_data[0]['@type'] ?? '');
                if ($type) $schema_types[] = $type;
            }
        }
        if (!$has_schema && $xpath->query('//*[@itemtype]')->length > 0) {
            $has_schema = true;
            $schema_types[] = 'Microdata';
        }

        // hreflang
        $hreflang_count = $xpath->query('//link[@rel="alternate"][@hreflang]')->length;
        $has_hreflang   = $hreflang_count > 0;

        // Mixed content
        $mixed_content = false;
        if ($is_https) {
            foreach ($xpath->query('//*[@src]') as $node) {
                if (str_starts_with($node->getAttribute('src'), 'http://')) {
                    $mixed_content = true; break;
                }
            }
        }

        // Word count
        $body = $xpath->query('//body')->item(0);
        $body_text = $body ? preg_replace('/\s+/', ' ', trim(strip_tags($body->textContent))) : '';
        $word_count = str_word_count($body_text);

        // Forms & inputs
        $forms = $xpath->query('//form')->length;
        $inputs_no_label = 0;
        foreach ($xpath->query('//input[@type!="hidden"][@type!="submit"][@type!="button"]') as $inp) {
            $id  = $inp->getAttribute('id');
            $aria = trim($inp->getAttribute('aria-label') . $inp->getAttribute('aria-labelledby'));
            if ($id === '' && $aria === '') $inputs_no_label++;
        }

        // Robots.txt + Sitemap
        $robots_ok = false;
        $robots_txt = @file_get_contents($base_domain . '/robots.txt', false, $ctx);
        if ($robots_txt !== false && strlen($robots_txt) > 5) $robots_ok = true;

        $sitemap_ok = false;
        foreach (['/sitemap.xml', '/sitemap_index.xml', '/sitemap.xml.gz', '/sitemap/'] as $sp) {
            $sm = @file_get_contents($base_domain . $sp, false, $ctx);
            if ($sm !== false && (str_contains($sm, '<sitemap') || str_contains($sm, '<urlset') || str_contains($sm, 'xml'))) {
                $sitemap_ok = true; break;
            }
        }
        if (!$sitemap_ok && $robots_ok && preg_match('/Sitemap:\s*(https?:\/\/\S+)/i', $robots_txt ?? '', $sm_m)) {
            $sitemap_ok = true;
        }

        // ── SCORING ──────────────────────────────────────────────────────────
        $score  = 100;
        $issues = [];

        // — SEO —
        if ($title === '')            { $score -= 10; $issues[] = [$_d('Titre de page absent','Page title missing'),                                     'red',    'fa-times-circle',      'seo']; }
        elseif ($title_len < 30)      { $score -= 6;  $issues[] = [$_d('Titre trop court','Title too short')." ({$title_len} car.)",                     'orange', 'fa-exclamation-circle', 'seo']; }
        elseif ($title_len > 60)      { $score -= 3;  $issues[] = [$_d('Titre trop long','Title too long')." ({$title_len} car.)",                       'yellow', 'fa-exclamation-circle', 'seo']; }

        if ($meta_desc === '')        { $score -= 8;  $issues[] = [$_d('Meta description absente','Missing meta description'),                           'red',    'fa-times-circle',      'seo']; }
        elseif ($meta_desc_len < 100) { $score -= 4;  $issues[] = [$_d('Meta desc. trop courte','Meta desc. too short')." ({$meta_desc_len} car.)",     'orange', 'fa-exclamation-circle', 'seo']; }
        elseif ($meta_desc_len > 160) { $score -= 2;  $issues[] = [$_d('Meta desc. trop longue','Meta desc. too long')." ({$meta_desc_len} car.)",      'yellow', 'fa-exclamation-circle', 'seo']; }

        if ($h1_count === 0)          { $score -= 6;  $issues[] = [$_d('Aucune balise H1','No H1 tag found'),                                           'red',    'fa-times-circle',      'seo']; }
        elseif ($h1_count > 1)        { $score -= 3;  $issues[] = [$_d("Plusieurs H1 ({$h1_count})","Multiple H1 ({$h1_count})"),                       'orange', 'fa-exclamation-circle', 'seo']; }
        if ($h2_count === 0)          { $score -= 2;  $issues[] = [$_d('Aucun H2 (structure des titres)','No H2 headings (title structure)'),           'yellow', 'fa-exclamation-circle', 'seo']; }

        if (!$has_canonical)          { $score -= 4;  $issues[] = [$_d('Balise canonical absente','Canonical tag missing'),                             'orange', 'fa-exclamation-circle', 'seo']; }
        if ($is_noindex)              { $score -= 15; $issues[] = [$_d('Page non indexée (noindex)','Page blocked from indexing (noindex)'),            'red',    'fa-times-circle',      'seo']; }
        if ($word_count < 300)        { $score -= 3;  $issues[] = [$_d('Contenu insuffisant','Thin content')." ({$word_count} ".$_d('mots','words').")",'orange', 'fa-exclamation-circle', 'seo']; }
        if (!$robots_ok)              { $score -= 2;  $issues[] = [$_d('robots.txt introuvable','robots.txt not found'),                               'orange', 'fa-exclamation-circle', 'seo']; }
        if (!$sitemap_ok)             { $score -= 2;  $issues[] = [$_d('Sitemap XML introuvable','XML Sitemap not found'),                             'orange', 'fa-exclamation-circle', 'seo']; }
        if ($meta_keywords === '')    { $score -= 1;  $issues[] = [$_d('Balise keywords absente','Keywords meta tag missing'),                         'yellow', 'fa-info-circle',        'seo']; }

        // — Technique / Sécurité —
        if (!$is_https)               { $score -= 10; $issues[] = [$_d('Site non sécurisé (HTTP)','Unsecured site (HTTP)'),                            'red',    'fa-times-circle',      'tech']; }
        if (!$has_viewport)           { $score -= 6;  $issues[] = [$_d('Viewport meta tag absent','Viewport meta tag missing'),                        'red',    'fa-times-circle',      'tech']; }
        if (!$has_lang)               { $score -= 3;  $issues[] = [$_d('Attribut lang absent sur &lt;html&gt;','Missing lang on &lt;html&gt;'),            'orange', 'fa-exclamation-circle', 'tech']; }
        if (!$has_charset)            { $score -= 2;  $issues[] = [$_d('Charset non déclaré','Charset not declared'),                                  'yellow', 'fa-exclamation-circle', 'tech']; }
        if (!$has_favicon)            { $score -= 2;  $issues[] = [$_d('Favicon manquant','Favicon missing'),                                         'yellow', 'fa-exclamation-circle', 'tech']; }
        if ($mixed_content)           { $score -= 5;  $issues[] = [$_d('Contenu mixte HTTP/HTTPS','Mixed HTTP/HTTPS content'),                         'red',    'fa-times-circle',      'tech']; }
        if (!$has_schema)             { $score -= 3;  $issues[] = [$_d('Données structurées absentes (JSON-LD)','No structured data (JSON-LD)'),       'yellow', 'fa-exclamation-circle', 'tech']; }
        if (!$has_csp)                { $score -= 2;  $issues[] = [$_d('En-tête CSP absent','Content-Security-Policy header missing'),                'yellow', 'fa-exclamation-circle', 'tech']; }
        if (!$has_xframe)             { $score -= 1;  $issues[] = [$_d('X-Frame-Options absent','X-Frame-Options header missing'),                    'yellow', 'fa-info-circle',        'tech']; }
        if (!$has_xctype)             { $score -= 1;  $issues[] = [$_d('X-Content-Type-Options absent','X-Content-Type-Options header missing'),      'yellow', 'fa-info-circle',        'tech']; }
        if ($is_https && !$has_hsts)  { $score -= 2;  $issues[] = [$_d('HSTS absent','HSTS header missing'),                                         'orange', 'fa-exclamation-circle', 'tech']; }
        if ($http_status >= 400)      { $score -= 15; $issues[] = [$_d("Statut HTTP {$http_status}","HTTP status {$http_status}"),                    'red',    'fa-times-circle',      'tech']; }

        // — Social / OG —
        if ($og['title'] === '')      { $score -= 4;  $issues[] = [$_d('og:title manquant','og:title missing'),                                       'orange', 'fa-exclamation-circle', 'social']; }
        if ($og['description'] === '') { $score -= 3; $issues[] = [$_d('og:description manquant','og:description missing'),                           'orange', 'fa-exclamation-circle', 'social']; }
        if ($og['image'] === '')      { $score -= 3;  $issues[] = [$_d('og:image manquant','og:image missing'),                                       'orange', 'fa-exclamation-circle', 'social']; }
        if ($og['url'] === '')        { $score -= 2;  $issues[] = [$_d('og:url manquant','og:url missing'),                                           'yellow', 'fa-exclamation-circle', 'social']; }
        if ($og['type'] === '')       { $score -= 1;  $issues[] = [$_d('og:type manquant','og:type missing'),                                         'yellow', 'fa-info-circle',        'social']; }
        if ($tw['card'] === '')       { $score -= 3;  $issues[] = [$_d('Twitter Card absent','Twitter Card missing'),                                 'orange', 'fa-exclamation-circle', 'social']; }
        if ($tw['image'] === '' && $tw['card'] !== '') { $score -= 1; $issues[] = [$_d('twitter:image absent','twitter:image missing'), 'yellow', 'fa-info-circle', 'social']; }

        // — Performance —
        if      ($load_time_ms > 4000) { $score -= 8;  $issues[] = [$_d('Chargement très lent','Very slow load time')." ({$load_time_ms}ms)",          'red',    'fa-times-circle',      'perf']; }
        elseif  ($load_time_ms > 2000) { $score -= 5;  $issues[] = [$_d('Chargement lent','Slow load time')." ({$load_time_ms}ms)",                    'orange', 'fa-exclamation-circle', 'perf']; }
        elseif  ($load_time_ms > 1000) { $score -= 2;  $issues[] = [$_d('Chargement moyen','Average load time')." ({$load_time_ms}ms)",                'yellow', 'fa-exclamation-circle', 'perf']; }
        if      ($page_size_kb > 3000) { $score -= 5;  $issues[] = [$_d('Page très lourde','Very heavy page')." ({$page_size_kb}KB)",                  'orange', 'fa-exclamation-circle', 'perf']; }
        elseif  ($page_size_kb > 1500) { $score -= 2;  $issues[] = [$_d('Page lourde','Heavy page')." ({$page_size_kb}KB)",                            'yellow', 'fa-exclamation-circle', 'perf']; }
        if ($inline_js > 8)            { $score -= 2;  $issues[] = [$inline_js." ".$_d("scripts inline","inline scripts"),                            'yellow', 'fa-exclamation-circle', 'perf']; }
        if ($js_count > 15)            { $score -= 2;  $issues[] = [$js_count." ".$_d("fichiers JS externes","external JS files"),                    'yellow', 'fa-info-circle',        'perf']; }

        // — Accessibilité —
        $alt_issues = $imgs_no_alt;
        $alt_penalty = min(8, $alt_issues * 2);
        if ($alt_penalty > 0)          { $score -= $alt_penalty; $issues[] = [$alt_issues." ".$_d("image(s) sans alt","image(s) missing alt"),         'orange', 'fa-exclamation-circle', 'access']; }
        if ($inputs_no_label > 0)      { $score -= 2;  $issues[] = [$inputs_no_label." ".$_d("champ(s) sans label","form field(s) without label"),     'orange', 'fa-exclamation-circle', 'access']; }
        if (!$has_lang)                { /* already in tech */; }

        $score = max(0, min(100, $score));

        // Build category scores
        $cat_issues = ['seo'=>[], 'tech'=>[], 'social'=>[], 'perf'=>[], 'access'=>[]];
        foreach ($issues as $iss) $cat_issues[$iss[3]][] = $iss;
        $cat_score = function($cat, $max_deduction) use ($cat_issues, $issues) {
            $deducted = 0;
            foreach ($cat_issues[$cat] as $i) {
                $deducted += match($i[1]) { 'red' => 10, 'orange' => 5, 'yellow' => 2, default => 1 };
            }
            return max(0, round(100 - min($deducted / $max_deduction * 100, 100)));
        };

        $audit_result = compact(
            'raw_url','title','title_len','meta_desc','meta_desc_len','meta_keywords','meta_author',
            'has_viewport','has_canonical','canonical_url','has_lang','lang_attr','has_charset',
            'og','tw','h1_count','h1_texts','h2_count','h3_count','h4_count',
            'total_imgs','imgs_no_alt','imgs_decorative','total_links','int_links','ext_links','nofollow_links',
            'js_count','css_count','inline_js','inline_css','page_size_kb','load_time_ms','is_https','http_status',
            'has_favicon','has_schema','schema_types','has_hreflang','hreflang_count',
            'mixed_content','robots_ok','sitemap_ok','word_count','forms','inputs_no_label',
            'has_csp','has_xframe','has_xctype','has_hsts','has_refpol','has_xss','is_noindex',
            'score','issues','cat_issues'
        );

        if ($is_ajax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'data' => $audit_result]);
            exit;
        }
    }

    if ($is_ajax ?? false) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $audit_error]);
        exit;
    }
}

$page_title = $_d('Audit Complet de Site — Netcrafter', 'Full Site Audit — Netcrafter');
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ═══ HERO ══════════════════════════════════════════════════════════════════ -->
<section class="relative pt-32 pb-14 overflow-hidden">
    <div class="blob bg-nc-cyan"  style="width:500px;height:500px;top:-180px;left:-180px;"></div>
    <div class="blob bg-nc-violet" style="width:400px;height:400px;bottom:-100px;right:-120px;animation-delay:2s;opacity:0.15"></div>
    <div class="absolute inset-0 pointer-events-none"
         style="background-image:linear-gradient(rgba(0,200,255,0.04) 1px,transparent 1px),linear-gradient(90deg,rgba(0,200,255,0.04) 1px,transparent 1px);background-size:60px 60px;"></div>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center">
        <div class="inline-flex items-center gap-2 badge mb-5" data-aos="fade-down">
            <i class="fas fa-microscope"></i> <?= $_d('Audit Professionnel','Professional Audit') ?>
        </div>
        <h1 class="font-heading font-black text-5xl md:text-6xl text-white mb-4" data-aos="fade-up">
            <?= $_d('Audit Complet','Full Audit') ?> <span class="gradient-text"><?= $_d('de Site Web','Website') ?></span>
        </h1>
        <p class="text-gray-400 text-lg max-w-2xl mx-auto leading-relaxed mb-8" data-aos="fade-up" data-aos-delay="80">
            <?= $_d('30+ vérifications : SEO, sécurité, réseaux sociaux, performance, accessibilité et bien plus.','30+ checks: SEO, security, social media, performance, accessibility and much more.') ?>
        </p>
        <!-- Stats chips -->
        <div class="flex flex-wrap justify-center gap-3 mb-2" data-aos="fade-up" data-aos-delay="150">
            <?php foreach ([
                ['fa-search', $_d('30+ Vérifications','30+ Checks')],
                ['fa-shield-alt', $_d('Sécurité','Security')],
                ['fa-share-alt', $_d('Open Graph','Open Graph')],
                ['fa-tachometer-alt', $_d('Performance','Performance')],
                ['fa-universal-access', $_d('Accessibilité','Accessibility')],
            ] as [$icon, $lbl]): ?>
            <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full"
                  style="background:rgba(0,200,255,0.07);border:1px solid rgba(0,200,255,0.2);color:#94a3b8;">
                <i class="fas <?= $icon ?> text-nc-cyan text-xs"></i> <?= $lbl ?>
            </span>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══ FORM ═══════════════════════════════════════════════════════════════════ -->
<section class="pb-8">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="glass rounded-3xl p-8" data-aos="fade-up">
            <form id="audit-form" class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1 relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none">
                        <i class="fas fa-globe"></i>
                    </span>
                    <input type="url" id="audit-url" name="url"
                           placeholder="https://votre-site.com"
                           required
                           class="w-full pl-10 pr-4 py-4 rounded-2xl text-white placeholder-gray-500 text-base outline-none transition-all"
                           style="background:rgba(255,255,255,0.05);border:1px solid rgba(0,200,255,0.2);">
                </div>
                <button type="submit" id="audit-btn" class="btn-primary whitespace-nowrap text-base px-7">
                    <i class="fas fa-search" id="audit-btn-icon"></i>
                    <span id="audit-btn-text"><?= $_d('Analyser','Analyse') ?></span>
                </button>
            </form>
            <div class="mt-5">
                <p class="text-gray-500 text-xs mb-3 uppercase tracking-wider font-semibold"><?= $_d('Sites exemples :','Example sites:') ?></p>
                <div class="flex flex-wrap gap-2">
                    <?php foreach (['https://example.com','https://wikipedia.org','https://github.com'] as $ex): ?>
                    <button class="example-btn text-xs px-3 py-1.5 rounded-full transition-all font-medium"
                            data-url="<?= $ex ?>"
                            style="background:rgba(0,200,255,0.07);border:1px solid rgba(0,200,255,0.18);color:#94a3b8;">
                        <?= $ex ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══ LOADING ════════════════════════════════════════════════════════════════ -->
<div id="audit-loading" class="hidden py-10 text-center">
    <div class="max-w-3xl mx-auto px-4">
        <div class="glass rounded-3xl p-12">
            <div class="relative w-20 h-20 mx-auto mb-6">
                <div class="absolute inset-0 rounded-full border-4 border-t-transparent animate-spin"
                     style="border-color:rgba(0,200,255,0.15);border-top-color:#00c8ff;"></div>
                <div class="absolute inset-3 rounded-full border-4 border-t-transparent animate-spin"
                     style="border-color:rgba(0,102,204,0.15);border-top-color:#0066cc;animation-direction:reverse;animation-duration:0.8s;"></div>
                <i class="fas fa-search absolute inset-0 flex items-center justify-center text-nc-cyan text-lg"
                   style="display:flex!important;"></i>
            </div>
            <p class="text-white font-semibold text-xl mb-2"><?= $_d('Analyse en cours…','Analysis in progress…') ?></p>
            <p class="text-gray-500 text-sm mb-8"><?= $_d('30+ vérifications automatiques…','Running 30+ automatic checks…') ?></p>
            <div class="space-y-3 max-w-sm mx-auto text-left">
                <?php foreach ([
                    [$_d('Chargement de la page','Loading page'),           'fa-download'],
                    [$_d('Analyse SEO & méta-tags','SEO & meta-tag scan'), 'fa-search'],
                    [$_d('Vérification sécurité','Security check'),        'fa-shield-alt'],
                    [$_d('Analyse Open Graph / Twitter Card','OG / Twitter Card analysis'), 'fa-share-alt'],
                    [$_d('Performance & taille','Performance & size'),     'fa-tachometer-alt'],
                    [$_d('Accessibilité & structure','Accessibility & structure'), 'fa-universal-access'],
                    [$_d('Calcul du score final','Final score calculation'), 'fa-chart-pie'],
                ] as $i => [$step, $icon]): ?>
                <div class="loading-step flex items-center gap-3 text-sm text-gray-600 opacity-<?= $i === 0 ? '100' : '30' ?>"
                     style="transition:opacity .4s">
                    <span class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0"
                          style="background:rgba(0,200,255,0.07);border:1px solid rgba(0,200,255,0.15);">
                        <i class="fas <?= $icon ?> text-xs" style="color:rgba(0,200,255,0.5)"></i>
                    </span>
                    <span class="loading-step-text"><?= $step ?></span>
                    <i class="fas fa-check text-xs text-nc-green ml-auto opacity-0 step-done"></i>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- ═══ RESULTS ════════════════════════════════════════════════════════════════ -->
<div id="audit-results" class="hidden pb-20">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 mb-4 flex justify-end">
        <button onclick="downloadPDF('audit', window._auditData)"
                class="flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-sm text-white transition-all hover:scale-105"
                style="background:linear-gradient(135deg,#ef4444,#dc2626)">
            <i class="fas fa-file-pdf"></i> Télécharger le rapport PDF
        </button>
    </div>
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        <!-- ── Score header ── -->
        <div class="glass rounded-3xl p-6 md:p-8" data-aos="fade-up">
            <div class="flex flex-col md:flex-row gap-8 items-center">

                <!-- Score ring -->
                <div class="flex-shrink-0 text-center">
                    <div class="relative w-40 h-40 mx-auto">
                        <svg class="w-40 h-40 -rotate-90" viewBox="0 0 36 36">
                            <circle cx="18" cy="18" r="15.9" fill="none" stroke="rgba(255,255,255,0.04)" stroke-width="2"/>
                            <circle id="score-track" cx="18" cy="18" r="15.9" fill="none"
                                    stroke="rgba(0,200,255,0.07)" stroke-width="2" stroke-dasharray="100 0"/>
                            <circle id="score-circle" cx="18" cy="18" r="15.9" fill="none"
                                    stroke="#10b981" stroke-width="2.5" stroke-linecap="round"
                                    stroke-dasharray="0 100" stroke-dashoffset="25"
                                    style="transition:stroke-dasharray 1.4s cubic-bezier(.16,1,.3,1);"/>
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span id="score-number" class="font-heading font-black text-5xl text-white leading-none">0</span>
                            <span class="text-gray-500 text-sm font-medium">/100</span>
                        </div>
                    </div>
                    <div id="score-label" class="mt-3 text-base font-bold text-nc-green"></div>
                    <div class="text-gray-600 text-xs mt-1"><?= $_d('Score global','Global score') ?></div>
                </div>

                <!-- Site info -->
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-2">
                        <span id="https-icon"></span>
                        <span id="http-status-badge" class="text-xs px-2 py-0.5 rounded-full font-bold"></span>
                        <span id="result-url" class="text-gray-400 text-sm truncate font-mono"></span>
                    </div>
                    <h2 id="result-title" class="font-heading font-bold text-xl md:text-2xl text-white mb-2 leading-snug"></h2>
                    <p id="result-desc" class="text-gray-400 text-sm leading-relaxed mb-4 line-clamp-2"></p>

                    <!-- Category mini-scores -->
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-3" id="cat-scores-row"></div>
                </div>
            </div>
        </div>

        <!-- ── Metrics grid ── -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3" id="metrics-grid"></div>

        <!-- ── Tab nav ── -->
        <div class="flex flex-wrap gap-2 border-b pb-1" style="border-color:rgba(0,200,255,0.1)">
            <?php foreach ([
                ['all',    'fa-th-list',         $_d('Tout','All')],
                ['seo',    'fa-search',           'SEO'],
                ['tech',   'fa-shield-alt',       $_d('Technique','Technical')],
                ['social', 'fa-share-alt',        'Social'],
                ['perf',   'fa-tachometer-alt',   $_d('Performance','Performance')],
                ['access', 'fa-universal-access', $_d('Accessibilité','Accessibility')],
            ] as $i => [$tab, $icon, $lbl]): ?>
            <button class="audit-tab <?= $i === 0 ? 'tab-active' : '' ?> flex items-center gap-1.5 px-4 py-2 rounded-t-xl text-sm font-medium transition-all"
                    data-tab="<?= $tab ?>">
                <i class="fas <?= $icon ?> text-xs"></i><?= $lbl ?>
                <span class="tab-count-<?= $tab ?> text-xs px-1.5 py-0.5 rounded-full hidden"
                      style="background:rgba(239,68,68,0.15);color:#ef4444;"></span>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- ── Checklist + Recommendations ── -->
        <div class="grid md:grid-cols-5 gap-6">

            <!-- Checklist (3/5) -->
            <div class="md:col-span-3 glass rounded-3xl p-6">
                <h3 class="font-heading font-bold text-lg text-white mb-5 flex items-center gap-2">
                    <i class="fas fa-clipboard-check text-nc-cyan"></i>
                    <?= $_d('Vérifications détaillées','Detailed Checks') ?>
                    <span id="issues-count" class="ml-auto text-xs px-2 py-0.5 rounded-full font-normal text-gray-500"
                          style="background:rgba(255,255,255,0.05)"></span>
                </h3>
                <ul id="checklist" class="space-y-2"></ul>
            </div>

            <!-- Right column (2/5) -->
            <div class="md:col-span-2 space-y-6">

                <!-- OG preview -->
                <div class="glass rounded-3xl p-6" id="og-card">
                    <h3 class="font-heading font-bold text-base text-white mb-4 flex items-center gap-2">
                        <i class="fas fa-share-alt text-nc-violet"></i> Open Graph
                    </h3>
                    <div id="og-preview-box"></div>
                </div>

                <!-- Security headers -->
                <div class="glass rounded-3xl p-6">
                    <h3 class="font-heading font-bold text-base text-white mb-4 flex items-center gap-2">
                        <i class="fas fa-lock text-nc-green"></i>
                        <?= $_d('En-têtes de sécurité','Security Headers') ?>
                    </h3>
                    <ul id="security-headers" class="space-y-2"></ul>
                </div>

            </div>
        </div>

        <!-- ── Recommendations ── -->
        <div class="glass rounded-3xl p-6">
            <h3 class="font-heading font-bold text-lg text-white mb-5 flex items-center gap-2">
                <i class="fas fa-lightbulb text-yellow-400"></i>
                <?= $_d('Recommandations prioritaires','Priority Recommendations') ?>
            </h3>
            <div id="recommendations" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3"></div>
        </div>

        <!-- ── Content analysis ── -->
        <div class="glass rounded-3xl p-6">
            <h3 class="font-heading font-bold text-lg text-white mb-5 flex items-center gap-2">
                <i class="fas fa-chart-bar text-nc-cyan"></i>
                <?= $_d('Analyse du Contenu','Content Analysis') ?>
            </h3>
            <div id="content-analysis" class="grid sm:grid-cols-2 md:grid-cols-4 gap-4"></div>
        </div>

    </div>
</div>

<!-- ═══ ERROR ══════════════════════════════════════════════════════════════════ -->
<div id="audit-error" class="hidden py-10">
    <div class="max-w-3xl mx-auto px-4">
        <div class="glass rounded-3xl p-8 text-center" style="border:1px solid rgba(239,68,68,0.3);">
            <div class="w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4"
                 style="background:rgba(239,68,68,0.1);">
                <i class="fas fa-exclamation-triangle text-red-400 text-xl"></i>
            </div>
            <h3 class="font-heading font-bold text-xl text-white mb-2"><?= $_d("Erreur d'analyse",'Analysis Error') ?></h3>
            <p id="error-message" class="text-gray-400 text-sm"></p>
            <button onclick="document.getElementById('audit-error').classList.add('hidden')"
                    class="btn-ghost mt-4 text-sm"><?= $_d('Réessayer','Try again') ?></button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<style>
#audit-url:focus {
    border-color: rgba(0,200,255,0.6) !important;
    box-shadow: 0 0 0 3px rgba(0,200,255,0.1);
}
.example-btn:hover { background:rgba(0,200,255,0.14)!important; border-color:rgba(0,200,255,0.4)!important; color:#00c8ff!important; }
.check-item {
    display:flex; align-items:flex-start; gap:10px; padding:9px 12px; border-radius:12px;
    background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.04);
    transition:background .2s;
}
.check-item:hover { background:rgba(255,255,255,0.04); }
.check-item.hidden-check { display:none; }
.audit-tab {
    background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.06);
    color:#64748b;
}
.audit-tab:hover { color:#fff; background:rgba(0,200,255,0.06); }
.audit-tab.tab-active { background:rgba(0,200,255,0.1); border-color:rgba(0,200,255,0.3); color:#00c8ff; }
.cat-score-bar { transition: width 1.2s cubic-bezier(.16,1,.3,1); }
.line-clamp-2 { display:-webkit-box;-webkit-line-clamp:2;line-clamp:2;-webkit-box-orient:vertical;overflow:hidden; }
@keyframes checkIn { from{opacity:0;transform:scale(0.7)} to{opacity:1;transform:scale(1)} }
.check-anim { animation: checkIn .3s ease forwards; }
</style>

<script>
const i18n = <?= json_encode([
    'analyse'     => $_d('Analyser','Analyse'),
    'analysing'   => $_d('Analyse…','Analysing…'),
    'excellent'   => $_d('Excellent','Excellent'),
    'good'        => $_d('Bien','Good'),
    'average'     => $_d('Moyen','Average'),
    'poor'        => $_d('Faible','Poor'),
    'critical'    => $_d('Critique','Critical'),
    'no_title'    => $_d('(Aucun titre)','(No title)'),
    'no_desc'     => $_d('(Aucune description)','(No description)'),
    'no_issues'   => $_d('Aucun problème détecté !','No issues detected!'),
    'well_opt'    => $_d('Votre site est parfaitement optimisé.','Your site is perfectly optimised.'),
    'unknown_err' => $_d('Erreur inconnue.','Unknown error.'),
    'net_err'     => $_d('Erreur réseau : ','Network error: '),
    'present'     => $_d('Présent','Present'),
    'absent'      => $_d('Absent','Absent'),
    'words'       => $_d('mots','words'),
    'issues'      => $_d('problème(s)','issue(s)'),
    'ms'          => 'ms',
    'kb'          => 'KB',
    'cat_seo'     => 'SEO',
    'cat_tech'    => $_d('Technique','Technical'),
    'cat_social'  => 'Social',
    'cat_perf'    => $_d('Perf.','Perf.'),
    'cat_access'  => $_d('Access.','Access.'),
    'lbl_loadtime'=> $_d('Temps de chargement','Load Time'),
    'lbl_size'    => $_d('Taille page','Page Size'),
    'lbl_links'   => $_d('Liens','Links'),
    'lbl_images'  => $_d('Images','Images'),
    'lbl_words'   => $_d('Mots','Words'),
    'lbl_scripts' => $_d('Scripts JS','JS Scripts'),
    'lbl_h1'      => 'H1',
    'lbl_h2'      => 'H2',
], JSON_UNESCAPED_UNICODE) ?>;

const form     = document.getElementById('audit-form');
const urlInput = document.getElementById('audit-url');
const loading  = document.getElementById('audit-loading');
const results  = document.getElementById('audit-results');
const errorDiv = document.getElementById('audit-error');
const btnIcon  = document.getElementById('audit-btn-icon');
const btnText  = document.getElementById('audit-btn-text');

document.querySelectorAll('.example-btn').forEach(b =>
    b.addEventListener('click', () => { urlInput.value = b.dataset.url; urlInput.focus(); })
);

form.addEventListener('submit', async function(e) {
    e.preventDefault();
    const url = urlInput.value.trim();
    if (!url) return;

    loading.classList.remove('hidden');
    results.classList.add('hidden');
    errorDiv.classList.add('hidden');
    btnIcon.className = 'fas fa-circle-notch fa-spin';
    btnText.textContent = i18n.analysing;

    // Animate loading steps sequentially
    const steps = loading.querySelectorAll('.loading-step');
    let si = 0;
    const stepInt = setInterval(() => {
        if (si < steps.length) {
            steps[si].style.opacity = '1';
            if (si > 0) steps[si - 1].querySelector('.step-done').style.opacity = '1';
            si++;
        }
    }, 800);

    try {
        const fd = new FormData();
        fd.append('url', url);
        fd.append('ajax', '1');
        const res  = await fetch(window.location.href, {method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'}});
        const json = await res.json();

        clearInterval(stepInt);
        loading.classList.add('hidden');
        btnIcon.className = 'fas fa-search';
        btnText.textContent = i18n.analyse;

        if (!json.ok) {
            document.getElementById('error-message').textContent = json.error || i18n.unknown_err;
            errorDiv.classList.remove('hidden');
            return;
        }
        window._auditData = json.data;
        renderResults(json.data);
        results.classList.remove('hidden');
        setTimeout(() => results.scrollIntoView({behavior:'smooth', block:'start'}), 100);

    } catch(err) {
        clearInterval(stepInt);
        loading.classList.add('hidden');
        btnIcon.className = 'fas fa-search';
        btnText.textContent = i18n.analyse;
        document.getElementById('error-message').textContent = i18n.net_err + err.message;
        errorDiv.classList.remove('hidden');
    }
});

function downloadPDF(type, data) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'pdf_report.php?type=' + type;
    form.target = '_blank';
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'data';
    input.value = JSON.stringify(data);
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
    setTimeout(() => document.body.removeChild(form), 1000);
}

function renderResults(d) {
    // ── Score ring ──────────────────────────────────────────────────────────
    const score = d.score;
    const circle  = document.getElementById('score-circle');
    const numEl   = document.getElementById('score-number');
    const labelEl = document.getElementById('score-label');

    let color, label;
    if      (score >= 90) { color = '#10b981'; label = i18n.excellent; }
    else if (score >= 75) { color = '#f59e0b'; label = i18n.good; }
    else if (score >= 55) { color = '#f97316'; label = i18n.average; }
    else if (score >= 35) { color = '#ef4444'; label = i18n.poor; }
    else                  { color = '#dc2626'; label = i18n.critical; }

    circle.style.stroke = color;
    labelEl.style.color = color;
    labelEl.textContent = label;

    let n = 0;
    const step = Math.max(1, Math.ceil(score / 50));
    const timer = setInterval(() => {
        n = Math.min(n + step, score);
        numEl.textContent = n;
        circle.setAttribute('stroke-dasharray', n + (n >= 100 ? ' 0' : ' 100'));
        if (n >= score) clearInterval(timer);
    }, 25);

    // ── URL + title ─────────────────────────────────────────────────────────
    const httpsEl = document.getElementById('https-icon');
    httpsEl.innerHTML = d.is_https
        ? '<i class="fas fa-lock text-nc-green text-sm"></i>'
        : '<i class="fas fa-lock-open text-red-400 text-sm"></i>';

    const statusBadge = document.getElementById('http-status-badge');
    statusBadge.textContent = 'HTTP ' + d.http_status;
    statusBadge.style.background = d.http_status < 300 ? 'rgba(16,185,129,0.15)' : 'rgba(239,68,68,0.15)';
    statusBadge.style.color = d.http_status < 300 ? '#10b981' : '#ef4444';

    document.getElementById('result-url').textContent   = d.raw_url;
    document.getElementById('result-title').textContent = d.title   || i18n.no_title;
    document.getElementById('result-desc').textContent  = d.meta_desc || i18n.no_desc;

    // ── Category scores ──────────────────────────────────────────────────────
    const catDefs = [
        {key:'seo',    label:i18n.cat_seo,    color:'#00c8ff', icon:'fa-search'},
        {key:'tech',   label:i18n.cat_tech,   color:'#7c3aed', icon:'fa-shield-alt'},
        {key:'social', label:i18n.cat_social, color:'#10b981', icon:'fa-share-alt'},
        {key:'perf',   label:i18n.cat_perf,   color:'#f59e0b', icon:'fa-tachometer-alt'},
        {key:'access', label:i18n.cat_access, color:'#4db8ff', icon:'fa-universal-access'},
    ];
    const catIssues = d.cat_issues || {};
    const catRow = document.getElementById('cat-scores-row');
    catRow.innerHTML = catDefs.map(c => {
        const issues = catIssues[c.key] || [];
        const ded = issues.reduce((s, i) => s + (i[1]==='red'?10:i[1]==='orange'?5:2), 0);
        const cs  = Math.max(0, Math.min(100, 100 - ded));
        const col = cs >= 80 ? '#10b981' : cs >= 60 ? '#f59e0b' : '#ef4444';
        return `<div class="text-center p-3 rounded-xl" style="background:rgba(${hexToRgb(c.color)},0.07);border:1px solid rgba(${hexToRgb(c.color)},0.18)">
            <i class="fas ${c.icon} text-sm mb-1 block" style="color:${c.color}"></i>
            <div class="text-lg font-black font-heading" style="color:${col}">${cs}</div>
            <div class="text-xs text-gray-500 mt-0.5">${c.label}</div>
            <div class="w-full h-1 rounded-full mt-2 overflow-hidden" style="background:rgba(255,255,255,0.06)">
                <div class="h-full rounded-full cat-score-bar" style="width:0%;background:${col}" data-target="${cs}"></div>
            </div>
        </div>`;
    }).join('');
    // Animate bars
    setTimeout(() => {
        catRow.querySelectorAll('.cat-score-bar').forEach(b => {
            b.style.width = b.dataset.target + '%';
        });
    }, 200);

    // ── Metrics grid ────────────────────────────────────────────────────────
    const metrics = [
        {label:i18n.lbl_loadtime, value: d.load_time_ms+'ms',  icon:'fa-clock',           color:'#00c8ff', good: d.load_time_ms<1000, warn: d.load_time_ms<2500},
        {label:i18n.lbl_size,     value: d.page_size_kb+'KB',  icon:'fa-weight-hanging',  color:'#7c3aed', good: d.page_size_kb<500, warn: d.page_size_kb<1500},
        {label:i18n.lbl_words,    value: d.word_count,          icon:'fa-align-left',      color:'#10b981', good: d.word_count>=500, warn: d.word_count>=300},
        {label:i18n.lbl_links,    value: d.total_links,         icon:'fa-link',            color:'#f59e0b', good: d.total_links>0, warn: true},
        {label:i18n.lbl_images,   value: d.total_imgs,          icon:'fa-image',           color:'#4db8ff', good: d.imgs_no_alt===0, warn: d.imgs_no_alt<3},
        {label:i18n.lbl_scripts,  value: d.js_count,            icon:'fa-code',            color:'#f97316', good: d.js_count<=8, warn: d.js_count<=15},
        {label:i18n.lbl_h1,       value: d.h1_count+' H1',      icon:'fa-heading',         color:'#00c8ff', good: d.h1_count===1, warn: d.h1_count>0},
        {label:i18n.lbl_h2,       value: d.h2_count+' H2',      icon:'fa-list',            color:'#94a3b8', good: d.h2_count>=2, warn: d.h2_count>0},
    ];
    const mGrid = document.getElementById('metrics-grid');
    mGrid.innerHTML = metrics.map(m => {
        const c = m.good ? '#10b981' : m.warn ? '#f59e0b' : '#ef4444';
        return `<div class="glass rounded-2xl p-4 text-center">
            <div class="w-9 h-9 rounded-xl mx-auto mb-2 flex items-center justify-center" style="background:${m.color}14;border:1px solid ${m.color}28">
                <i class="fas ${m.icon} text-xs" style="color:${m.color}"></i>
            </div>
            <div class="font-heading font-black text-xl leading-none mb-1" style="color:${c}">${m.value}</div>
            <div class="text-gray-500 text-xs">${m.label}</div>
        </div>`;
    }).join('');

    // ── Tab filtering ────────────────────────────────────────────────────────
    function renderChecklist(filter) {
        const allIssuesCombined = d.issues || [];
        const allChecks = buildChecklist(d);

        const filtered = filter === 'all' ? allChecks : allChecks.filter(c => c.cat === filter);
        const cl = document.getElementById('checklist');
        cl.innerHTML = filtered.map((c, i) => `
            <li class="check-item check-anim" data-cat="${c.cat}" style="animation-delay:${i*25}ms">
                <span class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5 text-xs"
                      style="background:${c.ok ? 'rgba(16,185,129,0.12)' : (c.sev==='red'?'rgba(239,68,68,0.12)':c.sev==='orange'?'rgba(249,115,22,0.12)':'rgba(245,158,11,0.12)')}">
                    <i class="fas ${c.ok ? 'fa-check' : (c.sev==='red'?'fa-times':'fa-exclamation')} text-xs"
                       style="color:${c.ok?'#10b981':c.sev==='red'?'#ef4444':c.sev==='orange'?'#f97316':'#f59e0b'}"></i>
                </span>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium text-white leading-snug">${c.label}</div>
                    ${c.detail ? `<div class="text-xs text-gray-500 mt-0.5 truncate">${c.detail}</div>` : ''}
                </div>
                <span class="text-xs px-1.5 py-0.5 rounded-md flex-shrink-0"
                      style="background:rgba(${c.cat==='seo'?'0,200,255':c.cat==='tech'?'124,58,237':c.cat==='social'?'16,185,129':c.cat==='perf'?'245,158,11':'77,184,255'},0.1);
                             color:rgba(${c.cat==='seo'?'0,200,255':c.cat==='tech'?'124,58,237':c.cat==='social'?'16,185,129':c.cat==='perf'?'245,158,11':'77,184,255'},0.8);
                             font-size:0.6rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em">
                    ${c.cat.toUpperCase()}
                </span>
            </li>`).join('');

        const cnt = document.getElementById('issues-count');
        const fails = filtered.filter(c => !c.ok).length;
        cnt.textContent = fails + ' ' + i18n.issues;

        // Update tab counts
        document.querySelectorAll('.audit-tab').forEach(tab => {
            const t = tab.dataset.tab;
            const badge = tab.querySelector('.tab-count-' + t);
            if (!badge) return;
            const count = t === 'all'
                ? allChecks.filter(c => !c.ok).length
                : allChecks.filter(c => !c.ok && c.cat === t).length;
            if (count > 0) {
                badge.textContent = count;
                badge.classList.remove('hidden');
            }
        });
    }

    document.querySelectorAll('.audit-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.audit-tab').forEach(t => t.classList.remove('tab-active'));
            this.classList.add('tab-active');
            renderChecklist(this.dataset.tab);
        });
    });

    renderChecklist('all');

    // ── OG Preview ──────────────────────────────────────────────────────────
    const ogBox = document.getElementById('og-preview-box');
    if (d.og.title || d.og.image) {
        ogBox.innerHTML = `
            ${d.og.image ? `<img src="${d.og.image}" alt="OG" class="w-full h-28 object-cover rounded-xl mb-3" style="border:1px solid rgba(0,200,255,0.15);" onerror="this.style.display='none'">` : ''}
            <div class="space-y-1.5">
                ${mkOGRow('og:title',       d.og.title)}
                ${mkOGRow('og:description', d.og.description)}
                ${mkOGRow('og:image',       d.og.image ? '✓ défini' : '')}
                ${mkOGRow('og:url',         d.og.url)}
                ${mkOGRow('og:type',        d.og.type)}
                ${mkOGRow('twitter:card',   d.tw.card)}
            </div>`;
    } else {
        ogBox.innerHTML = `<p class="text-gray-500 text-sm text-center py-4"><i class="fas fa-times-circle text-red-400 mr-2"></i>${i18n.absent}</p>`;
    }

    // ── Security headers ─────────────────────────────────────────────────────
    const secHeaders = [
        {label:'HTTPS',                    ok:d.is_https},
        {label:'Content-Security-Policy',  ok:d.has_csp},
        {label:'X-Frame-Options',          ok:d.has_xframe},
        {label:'X-Content-Type-Options',   ok:d.has_xctype},
        {label:'Strict-Transport-Security',ok:d.has_hsts},
        {label:'Referrer-Policy',          ok:d.has_refpol},
    ];
    document.getElementById('security-headers').innerHTML = secHeaders.map(h => `
        <li class="flex items-center justify-between gap-2 text-xs py-1.5 border-b" style="border-color:rgba(255,255,255,0.05)">
            <span class="font-mono text-gray-400">${h.label}</span>
            <span class="font-bold" style="color:${h.ok?'#10b981':'#ef4444'}">${h.ok ? i18n.present : i18n.absent}</span>
        </li>`).join('');

    // ── Recommendations ───────────────────────────────────────────────────────
    const rec = document.getElementById('recommendations');
    const issues = (d.issues || []).filter(i => !['seo'].includes('')) ; // all issues
    const sorted = [...(d.issues || [])].sort((a,b) => {
        const w = {red:3, orange:2, yellow:1};
        return (w[b[1]]||0) - (w[a[1]]||0);
    });
    if (sorted.length === 0) {
        rec.innerHTML = `<div class="col-span-3 text-center py-8">
            <i class="fas fa-check-circle text-nc-green text-4xl mb-3 block"></i>
            <p class="text-nc-green font-semibold">${i18n.no_issues}</p>
            <p class="text-gray-500 text-sm mt-1">${i18n.well_opt}</p>
        </div>`;
    } else {
        const colors = {red:'#ef4444', orange:'#f97316', yellow:'#f59e0b'};
        rec.innerHTML = sorted.map(iss => `
            <div class="p-4 rounded-2xl flex items-start gap-3" style="background:rgba(${iss[1]==='red'?'239,68,68':iss[1]==='orange'?'249,115,22':'245,158,11'},0.07);border:1px solid rgba(${iss[1]==='red'?'239,68,68':iss[1]==='orange'?'249,115,22':'245,158,11'},0.18);">
                <i class="fas ${iss[2]} mt-0.5 flex-shrink-0" style="color:${colors[iss[1]]}"></i>
                <div>
                    <p class="text-sm font-medium text-white leading-snug">${iss[0]}</p>
                    <span class="text-xs uppercase font-bold tracking-wider" style="color:${colors[iss[1]]};opacity:0.7">${iss[1]==='red'?'Critique':iss[1]==='orange'?'Important':'Conseil'}</span>
                </div>
            </div>`).join('');
    }

    // ── Content analysis ─────────────────────────────────────────────────────
    document.getElementById('content-analysis').innerHTML = [
        {label:i18n.words,             value:d.word_count,      ok:d.word_count>=500,     icon:'fa-align-left',  color:'#10b981'},
        {label:'H1 / H2 / H3',        value:`${d.h1_count} / ${d.h2_count} / ${d.h3_count}`, ok:d.h1_count===1&&d.h2_count>0, icon:'fa-heading', color:'#00c8ff'},
        {label:'Images sans alt',      value:`${d.imgs_no_alt}/${d.total_imgs}`, ok:d.imgs_no_alt===0, icon:'fa-image', color:'#4db8ff'},
        {label:'Liens int. / ext.',    value:`${d.int_links} / ${d.ext_links}`, ok:d.int_links>0,    icon:'fa-link',  color:'#f59e0b'},
        {label:'robots.txt',           value:d.robots_ok?'✓':'✗',              ok:d.robots_ok,      icon:'fa-robot', color:'#7c3aed'},
        {label:'Sitemap XML',          value:d.sitemap_ok?'✓':'✗',             ok:d.sitemap_ok,     icon:'fa-sitemap',color:'#7c3aed'},
        {label:'Données structurées',  value:d.has_schema?(d.schema_types&&d.schema_types.length?d.schema_types[0]:'✓'):'✗', ok:d.has_schema, icon:'fa-code', color:'#f97316'},
        {label:'hreflang',             value:d.has_hreflang?d.hreflang_count+' lang(s)':'✗', ok:d.has_hreflang, icon:'fa-globe', color:'#4db8ff'},
    ].map(c => `
        <div class="flex items-center gap-3 p-3 rounded-xl" style="background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.05)">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" style="background:${c.color}12;border:1px solid ${c.color}22">
                <i class="fas ${c.icon} text-xs" style="color:${c.color}"></i>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-xs text-gray-500">${c.label}</div>
                <div class="text-sm font-semibold truncate" style="color:${c.ok?'#10b981':'#ef4444'}">${c.value}</div>
            </div>
        </div>`).join('');
}

// ── Helpers ──────────────────────────────────────────────────────────────────
function mkOGRow(name, val) {
    const ok = val && val !== '';
    return `<div class="flex items-center justify-between gap-2 text-xs py-1" style="border-bottom:1px solid rgba(255,255,255,0.04)">
        <span class="font-mono text-gray-500 flex-shrink-0">${name}</span>
        <span class="truncate font-medium max-w-[150px]" style="color:${ok?'#94a3b8':'#ef4444'}">${ok ? (val.length>30?val.substring(0,30)+'…':val) : i18n.absent}</span>
    </div>`;
}

function hexToRgb(hex) {
    const r = parseInt(hex.slice(1,3),16);
    const g = parseInt(hex.slice(3,5),16);
    const b = parseInt(hex.slice(5,7),16);
    return `${r},${g},${b}`;
}

function buildChecklist(d) {
    const ok = (l,det,cat) => ({ok:true, label:l, detail:det, cat, sev:'ok'});
    const fail = (sev,l,det,cat) => ({ok:false, label:l, detail:det, cat, sev});
    return [
        // SEO
        d.title      ? ok  ('Titre présent',`${d.title_len} car.`,'seo') : fail('red','Titre absent','','seo'),
        d.title_len>=30&&d.title_len<=60 ? ok('Longueur titre optimale',`${d.title_len}/60`,'seo') : fail('orange',`Longueur titre (${d.title_len} car.)`,d.title_len<30?'< 30 min':'> 60 max','seo'),
        d.meta_desc  ? ok  ('Meta description présente',`${d.meta_desc_len} car.`,'seo') : fail('red','Meta description absente','','seo'),
        d.meta_desc_len>=100&&d.meta_desc_len<=160 ? ok('Longueur meta desc. optimale',`${d.meta_desc_len}/160`,'seo') : (d.meta_desc_len>0?fail('orange',`Longueur meta (${d.meta_desc_len} car.)`,d.meta_desc_len<100?'< 100 min':'> 160 max','seo'):null),
        d.h1_count===1 ? ok('H1 unique','1 balise H1','seo') : fail(d.h1_count===0?'red':'orange',`H1 — ${d.h1_count} balise(s)`,d.h1_count===0?'Requis':'Doit être unique','seo'),
        d.h2_count>0  ? ok(`H2 présents`,`${d.h2_count} balises H2`,'seo') : fail('yellow','Pas de H2 (structure faible)','','seo'),
        d.has_canonical ? ok('Balise canonical présente',d.canonical_url||'','seo') : fail('orange','Canonical manquant','','seo'),
        d.robots_ok   ? ok('robots.txt présent','','seo') : fail('orange','robots.txt introuvable','','seo'),
        d.sitemap_ok  ? ok('Sitemap XML trouvé','','seo') : fail('orange','Sitemap XML introuvable','','seo'),
        !d.is_noindex ? ok('Page indexable','','seo') : fail('red','Page bloquée (noindex)','','seo'),
        d.word_count>=300 ? ok(`Contenu suffisant`,`${d.word_count} mots`,'seo') : fail('orange',`Contenu insuffisant`,`${d.word_count} mots`,'seo'),
        // Tech
        d.is_https    ? ok('HTTPS actif','','tech') : fail('red','HTTP non sécurisé','','tech'),
        d.has_viewport ? ok('Viewport présent','','tech') : fail('red','Viewport manquant','','tech'),
        d.has_lang    ? ok(`Langue déclarée (${d.lang_attr})`,'','tech') : fail('orange','Attribut lang absent','','tech'),
        d.has_charset ? ok('Charset déclaré','','tech') : fail('yellow','Charset non déclaré','','tech'),
        d.has_favicon ? ok('Favicon présent','','tech') : fail('yellow','Favicon manquant','','tech'),
        !d.mixed_content ? ok('Pas de contenu mixte','','tech') : fail('red','Contenu mixte HTTP/HTTPS','','tech'),
        d.has_schema  ? ok(`Données structurées`,d.schema_types&&d.schema_types.length?d.schema_types.join(', '):'','tech') : fail('yellow','JSON-LD / Schema absent','','tech'),
        d.has_csp     ? ok('CSP header présent','','tech') : fail('yellow','CSP manquant','','tech'),
        d.has_xframe  ? ok('X-Frame-Options présent','','tech') : fail('yellow','X-Frame-Options absent','','tech'),
        d.has_xctype  ? ok('X-Content-Type-Options présent','','tech') : fail('yellow','X-Content-Type-Options absent','','tech'),
        (!d.is_https||d.has_hsts) ? ok('HSTS configuré','','tech') : fail('orange','HSTS absent (HTTPS sans HSTS)','','tech'),
        // Social
        d.og.title    ? ok('og:title présent','','social') : fail('orange','og:title manquant','','social'),
        d.og.description ? ok('og:description présent','','social') : fail('orange','og:description manquant','','social'),
        d.og.image    ? ok('og:image présent','','social') : fail('orange','og:image manquant','','social'),
        d.og.url      ? ok('og:url présent','','social') : fail('yellow','og:url manquant','','social'),
        d.og.type     ? ok(`og:type (${d.og.type})`, '','social') : fail('yellow','og:type manquant','','social'),
        d.tw.card     ? ok(`Twitter Card (${d.tw.card})`,'','social') : fail('orange','Twitter Card absent','','social'),
        // Performance
        d.load_time_ms<1000 ? ok(`Chargement rapide`,`${d.load_time_ms}ms`,'perf') :
        d.load_time_ms<2500 ? fail('yellow',`Chargement moyen`,`${d.load_time_ms}ms`,'perf') :
                              fail('red',`Chargement lent`,`${d.load_time_ms}ms`,'perf'),
        d.page_size_kb<1000 ? ok(`Taille page acceptable`,`${d.page_size_kb}KB`,'perf') : fail('orange',`Page lourde`,`${d.page_size_kb}KB`,'perf'),
        d.inline_js<=5 ? ok(`Scripts inline OK`,`${d.inline_js} scripts`,'perf') : fail('yellow',`Trop de scripts inline`,`${d.inline_js}`,'perf'),
        // Accessibility
        d.imgs_no_alt===0 ? ok('Toutes les images ont un alt',`${d.total_imgs} images`,'access') : fail('orange',`Images sans alt`,`${d.imgs_no_alt}/${d.total_imgs}`,'access'),
        d.has_lang ? ok(`Langue page (${d.lang_attr})`,'','access') : fail('orange','Lang attr. absent','','access'),
        d.inputs_no_label===0 ? ok('Champs formulaire avec labels','','access') : fail('orange',`${d.inputs_no_label} champ(s) sans label`,'','access'),
    ].filter(Boolean);
}
</script>
