<?php
// ── Bootstrap ─────────────────────────────────────────────────────────────────
$page_title = $page_title ?? 'Netcrafter - Solutions Numériques Professionnelles';
if (!defined('BASE')) {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    define('BASE', (strpos($host,'localhost')!==false || strpos($host,'127.0.0.1')!==false) ? '/netcrafter' : '');
}

// Language engine
if (!function_exists('t')) {
    require_once __DIR__ . '/lang.php';
}
$lang = $GLOBALS['nc_lang'];

// SEO / Open Graph variables (pages can override before including header)
$page_description = $page_description ?? ($lang === 'en'
    ? 'Netcrafter — Expert in web development, cybersecurity, digital marketing and professional training in Niamey, Niger.'
    : 'Netcrafter — Expert en développement web, sécurité informatique, marketing digital et formations professionnelles à Niamey, Niger.');

$_kw_default = $lang === 'en'
    ? 'web development Niger, cybersecurity Niamey, digital marketing Niger, IT training Niamey, web agency Niger, Netcrafter'
    : 'développement web Niger, sécurité informatique Niamey, marketing digital Niger, formation informatique Niamey, agence web Niger, agence digitale Niger, Netcrafter';
$page_keywords = $page_keywords ?? $_kw_default;
$_scheme  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$_host_og = $_SERVER['HTTP_HOST'] ?? 'localhost';
// Use og.jpg if it exists, otherwise fall back to logo-n.png
$_og_img_path = __DIR__ . '/../image/og.jpg';
$og_image = $og_image ?? "$_scheme://$_host_og" . BASE . (file_exists($_og_img_path) ? '/image/og.jpg' : '/image/logo-n.png');
$og_url   = $og_url   ?? "$_scheme://$_host_og" . ($_SERVER['REQUEST_URI'] ?? '/');
$og_type  = $og_type  ?? 'website';

// Build language-switch URL (keeps all current GET params except lang)
$curParams = $_GET;
$curParams['lang'] = ($lang === 'fr') ? 'en' : 'fr';
$switchUrl  = strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($curParams);

// hreflang URLs
$_cur_path      = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
$_params_fr     = array_merge($_GET, ['lang' => 'fr']);
$_params_en     = array_merge($_GET, ['lang' => 'en']);
$_hreflang_base = "$_scheme://$_host_og";
$_hreflang_fr   = $_hreflang_base . $_cur_path . '?' . http_build_query($_params_fr);
$_hreflang_en   = $_hreflang_base . $_cur_path . '?' . http_build_query($_params_en);
$_hreflang_def  = $_hreflang_base . $_cur_path;
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <meta name="description" content="<?= htmlspecialchars($page_description) ?>">
    <meta name="keywords" content="<?= htmlspecialchars($page_keywords) ?>">
    <link rel="canonical" href="<?= htmlspecialchars($og_url) ?>">
    <link rel="alternate" hreflang="fr"        href="<?= htmlspecialchars($_hreflang_fr) ?>">
    <link rel="alternate" hreflang="en"        href="<?= htmlspecialchars($_hreflang_en) ?>">
    <link rel="alternate" hreflang="x-default" href="<?= htmlspecialchars($_hreflang_def) ?>">
    <link rel="icon" type="image/png" href="<?= BASE ?>/image/logo-n.png">
    <link rel="shortcut icon" type="image/png" href="<?= BASE ?>/image/logo-n.png">

    <!-- Open Graph -->
    <meta property="og:type"        content="<?= htmlspecialchars($og_type) ?>">
    <meta property="og:site_name"   content="Netcrafter">
    <meta property="og:title"       content="<?= htmlspecialchars($page_title) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($page_description) ?>">
    <meta property="og:url"         content="<?= htmlspecialchars($og_url) ?>">
    <meta property="og:image"       content="<?= htmlspecialchars($og_image) ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height"content="630">
    <meta property="og:locale"      content="<?= $lang === 'en' ? 'en_US' : 'fr_FR' ?>">

    <!-- Twitter Card -->
    <meta name="twitter:card"        content="summary_large_image">
    <meta name="twitter:site"        content="@netcrafter">
    <meta name="twitter:title"       content="<?= htmlspecialchars($page_title) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($page_description) ?>">
    <meta name="twitter:image"       content="<?= htmlspecialchars($og_image) ?>">

    <!-- PWA -->
    <link rel="manifest" href="<?= BASE ?>/manifest.json">
    <meta name="theme-color" content="#00c8ff">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="apple-touch-icon" href="<?= BASE ?>/image/logo-n.png">

    <!-- Geo & robots -->
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <meta name="author" content="Netcrafter">
    <meta name="geo.region" content="NE-8">
    <meta name="geo.placename" content="Niamey, Niger">
    <meta name="geo.position" content="13.5127;2.1128">
    <meta name="ICBM" content="13.5127, 2.1128">

    <!-- Google Fonts (free) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Tailwind CSS (free CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome 6 Free -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- AOS – scroll animations (free) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">

    <script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    nc: {
                        cyan:  '#00c8ff',
                        blue:  '#0066cc',
                        navy:  '#0a1835',
                        dark:  '#060d1e',
                        card:  'rgba(10,24,58,0.8)',
                        light: '#4db8ff',
                        green: '#10b981',
                        violet:'#7c3aed',
                    }
                },
                fontFamily: {
                    sans:    ['Inter','sans-serif'],
                    heading: ['Space Grotesk','sans-serif'],
                }
            }
        }
    }
    </script>

    <style>
    /* ╔══════════════════════════════════════════════════════════╗
       ║           NETCRAFTER – DESIGN SYSTEM v2026              ║
       ╚══════════════════════════════════════════════════════════╝ */
    :root {
        --bg-primary:    #060d1e;
        --bg-secondary:  #0a1835;
        --bg-card:       #0d1f48;
        --accent-cyan:   #00c8ff;
        --accent-blue:   #0066cc;
        --accent-light:  #4db8ff;
        --accent-green:  #10b981;
        --accent-violet: #7c3aed;
        --text-primary:  #ffffff;
        --text-secondary:#94a3b8;
        --border:        rgba(0,200,255,0.15);
        --border-hover:  rgba(0,200,255,0.4);
        --shadow-glow:   0 0 30px rgba(0,200,255,0.25);
        --radius-card:   16px;
        --radius-btn:    50px;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; overflow-x: hidden; }
    body {
        background-color: var(--bg-primary);
        color: var(--text-primary);
        font-family: 'Inter', sans-serif;
        overflow-x: hidden;
        line-height: 1.6;
    }

    /* Ambient radial glow background */
    body::before {
        content: '';
        position: fixed; inset: 0; pointer-events: none; z-index: 0;
        background:
            radial-gradient(ellipse 80% 60% at 20% -10%, rgba(0,102,204,0.12) 0%, transparent 60%),
            radial-gradient(ellipse 60% 50% at 80% 110%, rgba(0,200,255,0.08) 0%, transparent 60%);
    }

    /* Scrollbar */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: #060d1e; }
    ::-webkit-scrollbar-thumb { background: rgba(0,200,255,0.35); border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: rgba(0,200,255,0.6); }

    /* ── Glass card ─────────────────────────────────────────── */
    .glass {
        background: rgba(10,24,58,0.7);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid var(--border);
    }
    .glass-strong {
        background: rgba(6,13,30,0.85);
        backdrop-filter: blur(24px);
        -webkit-backdrop-filter: blur(24px);
        border: 1px solid var(--border);
    }

    /* ── Typography ─────────────────────────────────────────── */
    .gradient-text {
        background: linear-gradient(135deg, #00c8ff, #0066cc);
        -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
    }
    .gradient-text-green {
        background: linear-gradient(135deg, #10b981, #00c8ff);
        -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
    }
    .gradient-text-violet {
        background: linear-gradient(135deg, #7c3aed, #00c8ff);
        -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
    }

    /* ── Buttons ────────────────────────────────────────────── */
    .btn-primary {
        background: linear-gradient(135deg, #00c8ff, #0066cc);
        color: #fff; border-radius: var(--radius-btn);
        padding: 13px 30px; font-weight: 700; cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 0 24px rgba(0,200,255,0.3);
        display: inline-flex; align-items: center; gap: 8px;
        text-decoration: none; position: relative; overflow: visible;
    }
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 0 45px rgba(0,200,255,0.55);
        background: linear-gradient(135deg, #33d4ff, #0080ff);
    }
    .btn-primary::after {
        content: ''; position: absolute; inset: 0; border-radius: var(--radius-btn);
        background: rgba(0,200,255,0.35);
        animation: pulse-ring 2.4s ease-out infinite; pointer-events: none;
    }
    .btn-outline {
        background: transparent; color: #fff;
        border: 2px solid rgba(0,200,255,0.35); border-radius: var(--radius-btn);
        padding: 11px 28px; font-weight: 600; cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex; align-items: center; gap: 8px; text-decoration: none;
    }
    .btn-outline:hover { border-color: var(--accent-cyan); color: var(--accent-cyan); box-shadow: var(--shadow-glow); }
    .btn-ghost {
        background: rgba(0,200,255,0.08); color: var(--accent-cyan);
        border: 1px solid rgba(0,200,255,0.2); border-radius: 10px;
        padding: 8px 18px; font-weight: 600; cursor: pointer;
        transition: all 0.25s ease; display: inline-flex; align-items: center; gap: 6px; text-decoration: none;
    }
    .btn-ghost:hover { background: rgba(0,200,255,0.16); border-color: var(--border-hover); }

    /* ── Service & product cards ────────────────────────────── */
    .service-card, .card-dark {
        background: rgba(10,24,58,0.65); border: 1px solid rgba(0,200,255,0.1);
        border-radius: var(--radius-card); transition: all 0.3s ease;
    }
    .service-card:hover, .card-dark:hover {
        background: rgba(10,24,58,0.9); border-color: var(--border-hover);
        transform: translateY(-4px);
        box-shadow: 0 20px 40px rgba(0,0,0,0.5), 0 0 20px rgba(0,200,255,0.1);
    }
    .hover-glow { transition: box-shadow .35s, transform .35s, border-color .35s; }
    .hover-glow:hover { transform: translateY(-6px); box-shadow: 0 30px 60px rgba(0,0,0,0.4), 0 0 30px rgba(0,200,255,0.12); border-color: var(--border-hover) !important; }

    /* ── Badges & chips ─────────────────────────────────────── */
    .badge {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 6px 14px; border-radius: 50px; font-size: 0.75rem; font-weight: 600;
        background-size: 200% auto;
        background-image: linear-gradient(90deg, rgba(0,200,255,0.1) 0%, rgba(0,200,255,0.22) 40%, rgba(0,102,204,0.15) 60%, rgba(0,200,255,0.1) 100%);
        border: 1px solid rgba(0,200,255,0.25); color: var(--accent-cyan);
        letter-spacing: 0.05em; text-transform: uppercase;
        animation: shimmer 4s linear infinite;
    }

    /* ── Decorative ─────────────────────────────────────────── */
    .section-divider { height: 1px; background: linear-gradient(90deg, transparent, rgba(0,200,255,0.4), rgba(0,102,204,0.4), transparent); }
    .section-title-bar { width: 48px; height: 3px; border-radius: 3px; background: linear-gradient(90deg,#00c8ff,#0066cc); margin: 0 auto 1rem; }
    .highlight-left { border-left: 3px solid var(--accent-cyan); padding-left: 1rem; }
    .glow-cyan  { box-shadow: 0 0 30px rgba(0,200,255,0.35); }
    .glow-blue  { box-shadow: 0 0 30px rgba(0,102,204,0.35); }
    .counter-value { color: var(--accent-cyan); }
    .stat-number { font-variant-numeric: tabular-nums; letter-spacing: -0.02em; }
    .reveal-underline { position: relative; display: inline-block; }
    .reveal-underline::after { content: ''; position: absolute; bottom: -4px; left: 0; width: 0; height: 2px; background: linear-gradient(90deg,#00c8ff,#0066cc); transition: width 0.6s ease; border-radius: 2px; }
    .reveal-underline.aos-animate::after { width: 100%; }
    .blob { position: absolute; border-radius: 50%; filter: blur(80px); opacity: 0.12; animation: pulse-glow 4s ease-in-out infinite; pointer-events: none; }

    /* ── Navbar ─────────────────────────────────────────────── */
    #navbar { transition: all 0.3s ease; }
    #navbar.scrolled { background: rgba(6,13,30,0.96) !important; backdrop-filter: blur(20px); box-shadow: 0 1px 0 rgba(0,200,255,0.1); }

    /* ── Sidebar (mobile) ───────────────────────────────────── */
    #sidebar-overlay {
        position: fixed; inset: 0; z-index: 200;
        background: rgba(0,0,0,0.72); backdrop-filter: blur(4px);
        opacity: 0; visibility: hidden;
        transition: opacity 0.35s ease, visibility 0.35s ease;
    }
    #sidebar-overlay.open { opacity: 1; visibility: visible; }
    #sidebar {
        position: fixed; top: 0; left: 0; bottom: 0; z-index: 201;
        width: min(320px, 85vw);
        background: linear-gradient(180deg, #0a1835 0%, #060d1e 100%);
        border-right: 1px solid rgba(0,200,255,0.15);
        transform: translateX(-100%);
        transition: transform 0.42s cubic-bezier(.16,1,.3,1);
        overflow-y: auto; padding-bottom: 2rem;
        box-shadow: 12px 0 50px rgba(0,0,0,0.6);
        scrollbar-width: thin; scrollbar-color: rgba(0,200,255,0.2) transparent;
    }
    #sidebar::-webkit-scrollbar { width: 4px; }
    #sidebar::-webkit-scrollbar-thumb { background: rgba(0,200,255,0.2); border-radius: 2px; }
    #sidebar.open { transform: translateX(0); }
    .sidebar-link {
        display: flex; align-items: center; gap: 12px;
        padding: 11px 20px; color: #94a3b8; font-size: 0.875rem; font-weight: 500;
        text-decoration: none; transition: all 0.2s ease; border-radius: 10px; margin: 2px 12px;
    }
    .sidebar-link:hover { background: rgba(0,200,255,0.09); color: #00c8ff; transform: translateX(4px); }
    .sidebar-link i { width: 18px; text-align: center; opacity: 0.65; font-size: 0.82rem; flex-shrink: 0; }
    .sidebar-link:hover i { opacity: 1; }
    .sidebar-section {
        font-size: 0.65rem; font-weight: 800; text-transform: uppercase;
        letter-spacing: 0.12em; color: #334155; padding: 18px 32px 6px;
    }
    .sidebar-divider { height: 1px; background: rgba(0,200,255,0.07); margin: 8px 20px; }

    /* ── Lang switcher ──────────────────────────────────────── */
    .lang-btn {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 5px 12px; border-radius: 20px; font-size: 0.72rem; font-weight: 700;
        border: 1px solid rgba(0,200,255,0.3); color: var(--accent-cyan);
        background: rgba(0,200,255,0.07); cursor: pointer; text-decoration: none;
        transition: all 0.25s; letter-spacing: 0.08em; white-space: nowrap;
    }
    .lang-btn:hover { background: rgba(0,200,255,0.16); border-color: var(--accent-cyan); }

    /* ── Scroll progress ───────────────────────────────────── */
    #scroll-progress {
        position: fixed; top: 0; left: 0; height: 3px; z-index: 9999;
        background: linear-gradient(90deg, #00c8ff, #0066cc, #7c3aed);
        width: 0%; transition: width 0.08s linear;
        box-shadow: 0 0 12px rgba(0,200,255,0.6);
    }

    /* ── Page loader ────────────────────────────────────────── */
    #page-loader {
        position: fixed; inset: 0; z-index: 10000;
        background: #060d1e; display: flex; align-items: center;
        justify-content: center; flex-direction: column; gap: 24px;
        transition: opacity 0.6s ease, visibility 0.6s ease;
    }
    #page-loader.hidden-loader { opacity: 0; visibility: hidden; pointer-events: none; }
    .loader-ring {
        width: 72px; height: 72px; border-radius: 50%; position: relative;
        border: 3px solid rgba(0,200,255,0.08);
    }
    .loader-ring::before {
        content: ''; position: absolute; inset: -3px; border-radius: 50%;
        border: 3px solid transparent;
        border-top-color: #00c8ff; border-right-color: #0066cc;
        animation: loader-spin 0.9s linear infinite;
    }
    .loader-ring::after {
        content: ''; position: absolute; inset: 6px; border-radius: 50%;
        border: 2px solid transparent;
        border-top-color: rgba(124,58,237,0.7);
        animation: loader-spin 1.4s linear infinite reverse;
    }
    @keyframes loader-spin { to { transform: rotate(360deg); } }
    @keyframes loader-bar   { from { width:0 } to { width:100% } }
    .loader-bar-fill { animation: loader-bar 1.4s cubic-bezier(.16,1,.3,1) forwards; }
    @keyframes loader-fade-up { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
    .loader-text { animation: loader-fade-up 0.5s ease 0.2s forwards; opacity:0; }

    /* ── Global cursor glow ─────────────────────────────────── */
    #global-cursor {
        position: fixed; width: 420px; height: 420px; border-radius: 50%;
        background: radial-gradient(circle, rgba(0,200,255,0.06) 0%, transparent 65%);
        pointer-events: none; z-index: 1; top: 0; left: 0;
        will-change: transform;
    }

    /* ── Animations ─────────────────────────────────────────── */
    @keyframes shimmer      { 0%{background-position:-200% center}100%{background-position:200% center} }
    @keyframes float        { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-20px)} }
    @keyframes spin         { to{transform:rotate(360deg)} }
    @keyframes pulse-glow   { 0%,100%{opacity:0.3} 50%{opacity:0.7} }
    @keyframes pulse-ring   { 0%{transform:scale(1);opacity:0.6} 100%{transform:scale(1.6);opacity:0} }
    @keyframes blink        { 0%,100%{opacity:1} 50%{opacity:0} }
    @keyframes slideInLeft  { from{opacity:0;transform:translateX(-40px)} to{opacity:1;transform:translateX(0)} }
    @keyframes slideInRight { from{opacity:0;transform:translateX(40px)} to{opacity:1;transform:translateX(0)} }
    @keyframes slideInUp    { from{opacity:0;transform:translateY(40px)} to{opacity:1;transform:translateY(0)} }
    @keyframes zoomIn       { from{opacity:0;transform:scale(0.85)} to{opacity:1;transform:scale(1)} }
    @keyframes bounceIn     { 0%{opacity:0;transform:scale(0.7)} 50%{transform:scale(1.05)} 75%{transform:scale(0.97)} 100%{opacity:1;transform:scale(1)} }

    .float { animation: float 6s ease-in-out infinite; }

    /* ── Nav dropdown ───────────────────────────────────────── */
    .nav-dropdown { position:relative; }
    .nav-dropdown-menu {
        position:absolute; top:calc(100% + 12px); left:50%; transform:translateX(-50%);
        min-width:200px; background:rgba(6,13,30,0.97); backdrop-filter:blur(20px);
        border:1px solid rgba(0,200,255,0.18); border-radius:14px; padding:8px;
        opacity:0; visibility:hidden; transition:all 0.2s ease;
        box-shadow:0 20px 50px rgba(0,0,0,0.5), 0 0 20px rgba(0,200,255,0.06);
        z-index:100;
    }
    .nav-dropdown:hover .nav-dropdown-menu,
    .nav-dropdown-menu:hover { opacity:1; visibility:visible; }
    .nav-dropdown-item {
        display:flex; align-items:center; gap:10px; padding:9px 14px;
        border-radius:10px; color:#94a3b8; font-size:0.82rem; font-weight:500;
        text-decoration:none; transition:all 0.2s; white-space:nowrap;
    }
    .nav-dropdown-item:hover { background:rgba(0,200,255,0.1); color:#00c8ff; }
    .nav-dropdown-item i { width:16px; text-align:center; opacity:0.7; }
    .nav-dropdown-arrow { font-size:10px; opacity:0.5; transition:transform 0.2s; }
    .nav-dropdown:hover .nav-dropdown-arrow { transform:rotate(180deg); }
    .dropdown-divider { height:1px; background:rgba(0,200,255,0.1); margin:4px 6px; }
    .cursor-blink { animation: blink 1s step-end infinite; }
    .anim-left   { animation: slideInLeft  0.7s ease forwards; opacity:0; }
    .anim-right  { animation: slideInRight 0.7s ease forwards; opacity:0; }
    .anim-up     { animation: slideInUp    0.7s ease forwards; opacity:0; }
    .anim-zoom   { animation: zoomIn       0.65s ease forwards; opacity:0; }
    .anim-bounce { animation: bounceIn     0.7s ease forwards; opacity:0; }
    .delay-1{animation-delay:.1s} .delay-2{animation-delay:.2s} .delay-3{animation-delay:.3s}
    .delay-4{animation-delay:.4s} .delay-5{animation-delay:.5s} .delay-6{animation-delay:.6s}
    </style>

<?php
/* ── Schema.org JSON-LD ─────────────────────────────────────────────────────── */
$_base_url = "$_scheme://$_host_og" . BASE;
$_ld = [
    "@context" => "https://schema.org",
    "@graph"   => [
        [
            "@type"       => ["Organization", "LocalBusiness"],
            "@id"         => "$_base_url/#org",
            "name"        => "Netcrafter",
            "alternateName" => "Netcrafter Niger",
            "url"         => "$_base_url/",
            "logo"        => [
                "@type"      => "ImageObject",
                "@id"        => "$_base_url/#logo",
                "url"        => "$_base_url/image/logo-n.png",
                "contentUrl" => "$_base_url/image/logo-n.png",
                "width"      => 512,
                "height"     => 512,
                "caption"    => "Netcrafter"
            ],
            "image"       => ["@id" => "$_base_url/#logo"],
            "description" => $lang === 'en'
                ? "Expert in web development, cybersecurity, digital marketing and professional training in Niamey, Niger."
                : "Expert en développement web, sécurité informatique, marketing digital et formations professionnelles à Niamey, Niger.",
            "address"     => [
                "@type"           => "PostalAddress",
                "streetAddress"   => "Niamey",
                "addressLocality" => "Niamey",
                "addressRegion"   => "Niamey",
                "addressCountry"  => "NE"
            ],
            "geo"         => [
                "@type"     => "GeoCoordinates",
                "latitude"  => "13.5127",
                "longitude" => "2.1128"
            ],
            "areaServed"  => [
                ["@type" => "Country", "name" => "Niger"],
                ["@type" => "Country", "name" => "Burkina Faso"],
                ["@type" => "Country", "name" => "Mali"],
                ["@type" => "Country", "name" => "Sénégal"]
            ],
            "knowsLanguage" => ["fr", "en"],
            "priceRange"    => "$$",
            "openingHoursSpecification" => [[
                "@type"     => "OpeningHoursSpecification",
                "dayOfWeek" => ["Monday","Tuesday","Wednesday","Thursday","Friday"],
                "opens"     => "08:00",
                "closes"    => "18:00"
            ]],
            "hasOfferCatalog" => [
                "@type" => "OfferCatalog",
                "name"  => "Services Netcrafter",
                "itemListElement" => [
                    ["@type"=>"Offer","itemOffered"=>["@type"=>"Service","name"=>"Développement Web & Mobile"]],
                    ["@type"=>"Offer","itemOffered"=>["@type"=>"Service","name"=>"Sécurité Informatique & Cybersécurité"]],
                    ["@type"=>"Offer","itemOffered"=>["@type"=>"Service","name"=>"Marketing Digital & SEO"]],
                    ["@type"=>"Offer","itemOffered"=>["@type"=>"Service","name"=>"Formations Professionnelles en IT"]]
                ]
            ]
        ],
        [
            "@type"       => "WebSite",
            "@id"         => "$_base_url/#website",
            "url"         => "$_base_url/",
            "name"        => "Netcrafter",
            "description" => "Solutions Numériques Professionnelles au Niger",
            "publisher"   => ["@id" => "$_base_url/#org"],
            "inLanguage"  => ["fr-FR", "en-US"],
            "potentialAction" => [
                "@type"       => "SearchAction",
                "target"      => ["@type"=>"EntryPoint","urlTemplate"=>"$_base_url/blog/index.php?q={search_term_string}"],
                "query-input" => "required name=search_term_string"
            ]
        ],
        [
            "@type"       => "WebPage",
            "@id"         => $og_url . "#webpage",
            "url"         => $og_url,
            "name"        => $page_title,
            "description" => $page_description,
            "isPartOf"    => ["@id" => "$_base_url/#website"],
            "publisher"   => ["@id" => "$_base_url/#org"],
            "inLanguage"  => $lang === 'en' ? "en-US" : "fr-FR"
        ]
    ]
];
?>
<script type="application/ld+json">
<?= json_encode($_ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
</script>
</head>
<body data-lang="<?= $lang ?>" data-base="<?= BASE ?>">

<!-- Scroll progress -->
<div id="scroll-progress" aria-hidden="true"></div>

<!-- Global cursor glow (desktop only) -->
<div id="global-cursor" aria-hidden="true"></div>

<!-- Page loader (once per session) -->
<div id="page-loader" role="status" aria-label="Chargement">
    <div class="loader-ring"></div>
    <div class="loader-text text-center">
        <div class="font-heading font-black text-2xl text-white mb-3">
            NET<span class="gradient-text">CRAFTER</span>
        </div>
        <div class="w-48 h-0.5 rounded-full overflow-hidden" style="background:rgba(255,255,255,0.06)">
            <div class="h-full rounded-full loader-bar-fill" style="background:linear-gradient(90deg,#00c8ff,#0066cc);width:0%"></div>
        </div>
    </div>
</div>

<!-- ═══ SIDEBAR (MOBILE) ═════════════════════════════════════════════════════ -->
<div id="sidebar-overlay" onclick="closeSidebar()" aria-hidden="true"></div>
<aside id="sidebar" role="dialog" aria-label="Menu de navigation" aria-modal="true">
    <!-- Sidebar header -->
    <div class="flex items-center justify-between px-5 py-5" style="border-bottom:1px solid rgba(0,200,255,0.1)">
        <a href="<?= BASE ?>/index.php" class="flex items-center gap-2" onclick="closeSidebar()">
            <img src="<?= BASE ?>/image/logo-n.png" alt="Netcrafter" class="h-8">
            <span class="font-heading font-bold text-white text-base">NET<span class="gradient-text">CRAFTER</span></span>
        </a>
        <button onclick="closeSidebar()" aria-label="Fermer le menu"
                class="w-9 h-9 rounded-xl flex items-center justify-center text-gray-400 hover:text-white transition-all"
                style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08)">
            <i class="fas fa-times text-sm"></i>
        </button>
    </div>

    <!-- Nav links -->
    <nav class="mt-3">
        <p class="sidebar-section">Navigation</p>
        <a href="<?= BASE ?>/service.php"              class="sidebar-link" onclick="closeSidebar()"><i class="fas fa-cogs"></i><?= t('nav.services') ?></a>
        <a href="<?= BASE ?>/shop/shop.php"            class="sidebar-link" onclick="closeSidebar()"><i class="fas fa-shopping-bag"></i><?= t('nav.shop') ?></a>
        <a href="<?= BASE ?>/formation/formations.php" class="sidebar-link" onclick="closeSidebar()"><i class="fas fa-graduation-cap"></i><?= t('nav.training') ?></a>
        <a href="<?= BASE ?>/volet.php"                class="sidebar-link" onclick="closeSidebar()"><i class="fas fa-window-restore"></i><?= t('nav.panels') ?></a>
        <a href="<?= BASE ?>/portfolio.php"            class="sidebar-link" onclick="closeSidebar()"><i class="fas fa-layer-group"></i><?= t('nav.portfolio') ?></a>

        <div class="sidebar-divider"></div>
        <p class="sidebar-section"><?= $lang==='en' ? 'Resources' : 'Ressources' ?></p>
        <a href="<?= BASE ?>/blog/index.php"           class="sidebar-link" onclick="closeSidebar()"><i class="fas fa-newspaper"></i><?= t('nav.blog') ?></a>
        <a href="<?= BASE ?>/processus.php"            class="sidebar-link" onclick="closeSidebar()"><i class="fas fa-sitemap"></i><?= t('nav.process') ?></a>
        <a href="<?= BASE ?>/stack.php"                class="sidebar-link" onclick="closeSidebar()"><i class="fas fa-layer-group"></i><?= t('nav.stack') ?></a>

        <div class="sidebar-divider"></div>
        <p class="sidebar-section"><?= t('nav.tools') ?></p>
        <a href="<?= BASE ?>/configurateur.php"        class="sidebar-link" onclick="closeSidebar()"><i class="fas fa-sliders-h"></i><?= t('nav.configurator') ?></a>
        <a href="<?= BASE ?>/outils/audit.php"         class="sidebar-link" onclick="closeSidebar()"><i class="fas fa-microscope"></i><?= t('nav.audit') ?></a>
        <a href="<?= BASE ?>/outils/pentest.php"       class="sidebar-link" onclick="closeSidebar()"><i class="fas fa-user-secret"></i><?= $lang==='en' ? 'Security Audit' : 'Audit Sécurité' ?></a>
        <a href="<?= BASE ?>/outils/palette.php"       class="sidebar-link" onclick="closeSidebar()"><i class="fas fa-palette"></i><?= t('nav.palette') ?></a>
        <a href="<?= BASE ?>/outils/seo-preview.php"   class="sidebar-link" onclick="closeSidebar()"><i class="fas fa-eye"></i><?= t('nav.seo_preview') ?></a>
        <a href="<?= BASE ?>/outils/password.php"      class="sidebar-link" onclick="closeSidebar()"><i class="fas fa-key"></i><?= $lang==='en' ? 'Password Generator' : 'Générateur MDP' ?></a>
        <a href="<?= BASE ?>/outils/base64.php"        class="sidebar-link" onclick="closeSidebar()"><i class="fas fa-code"></i>Base64 / URL Encoder</a>
    </nav>

    <!-- CTA block -->
    <div class="px-5 mt-6 space-y-3">
        <a href="<?= BASE ?>/devis.php" class="btn-primary w-full justify-center text-sm" onclick="closeSidebar()">
            <i class="fas fa-paper-plane text-xs"></i> <?= t('nav.quote') ?>
        </a>
        <a href="<?= htmlspecialchars($switchUrl) ?>" class="lang-btn w-full justify-center">
            <i class="fas fa-globe text-xs"></i><?= t('nav.lang_switch') ?>
        </a>
    </div>
</aside>

<!-- ═══ NAVBAR ═══════════════════════════════════════════════════════════════ -->
<nav id="navbar" class="fixed w-full top-0 z-50 py-4 bg-transparent">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between">

            <!-- Logo -->
            <a href="<?= BASE ?>/index.php" class="flex items-center gap-3">
                <img src="<?= BASE ?>/image/logo-n.png" alt="Netcrafter" class="h-9">
                <span class="font-heading font-bold text-xl text-white tracking-wide">
                    NET<span class="gradient-text">CRAFTER</span>
                </span>
            </a>

            <!-- Actions & sidebar toggle (all screen sizes) -->
            <div class="flex items-center gap-3">
                <a href="<?= htmlspecialchars($switchUrl) ?>" class="lang-btn">
                    <i class="fas fa-globe text-xs"></i><?= t('nav.lang_switch') ?>
                </a>

                <button onclick="openSidebar()" aria-label="Ouvrir le menu"
                        class="w-10 h-10 rounded-xl flex items-center justify-center text-white transition-all"
                        style="background:rgba(0,200,255,0.08);border:1px solid rgba(0,200,255,0.2)">
                    <i class="fas fa-bars text-base"></i>
                </button>
            </div>
        </div>
    </div>
</nav>

