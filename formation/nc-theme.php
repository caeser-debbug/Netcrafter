<?php
/**
 * Netcrafter Formation — Theme bridge
 * Include this file ONCE per formation page:
 *   1. In the PHP block (before HTML output) — loads translations
 *   2. Inside <head> — emits harmonized CSS
 * Usage: <?php require_once __DIR__.'/nc-theme.php'; ?>  (in PHP block)
 *         <?php include __DIR__.'/nc-theme.php'; ?>        (in <head> for CSS)
 */

// Load translation engine (guards against double-load)
if (!function_exists('t')) {
    require_once __DIR__ . '/../includes/lang.php';
}

// If we're inside an HTML <head> context, emit the CSS
if (!defined('NC_THEME_CSS_EMITTED')) {
    define('NC_THEME_CSS_EMITTED', true);
?>
<style>
/* ═══════════════════════════════════════════════════════════
   Netcrafter Formation – Harmonized Dark Navy / Cyan Theme
   Overrides the default light-blue palette across all pages
═══════════════════════════════════════════════════════════ */

/* ── CSS variables ──────────────────────────────────────── */
:root {
    --nc-cyan:    #00c8ff;
    --nc-blue:    #0066cc;
    --nc-navy:    #060d1e;
    --nc-navy2:   #0a1835;
    --nc-card-bg: rgba(10,24,58,0.72);
    --nc-border:  rgba(0,200,255,0.14);
    --nc-green:   #10b981;
}

/* ── Base ───────────────────────────────────────────────── */
html, body {
    background: linear-gradient(180deg,#060d1e 0%,#030810 100%) !important;
    color: #e2e8f0 !important;
}

/* ── Blob decorations ───────────────────────────────────── */
.blob { animation: blobMove 20s infinite alternate ease-in-out; }
@keyframes blobMove {
    0%   { transform: translate(0,0) scale(1); }
    33%  { transform: translate(100px,50px) scale(1.2); }
    66%  { transform: translate(-50px,100px) scale(0.8); }
    100% { transform: translate(70px,-70px) scale(1.1); }
}

/* ── Hero gradient override ─────────────────────────────── */
.hero-gradient-light, .hero-gradient-dark {
    background: linear-gradient(135deg,rgba(0,200,255,0.12) 0%,rgba(6,13,30,0.98) 100%) !important;
}

/* ── Navbar ─────────────────────────────────────────────── */
nav, .navbar, header {
    background: rgba(6,13,30,0.96) !important;
    border-bottom: 1px solid rgba(0,200,255,0.1) !important;
    backdrop-filter: blur(20px) !important;
}
nav a, header a { color: #cbd5e1; }
nav a:hover, header a:hover { color: #00c8ff; }

/* ── Cards ──────────────────────────────────────────────── */
.formation-card,
.bg-white, [class*="bg-white\/"],
.card, [class*="rounded-xl"], [class*="rounded-2xl"] {
    background: var(--nc-card-bg) !important;
    border-color: var(--nc-border) !important;
    color: #e2e8f0 !important;
    backdrop-filter: blur(16px) !important;
}
.formation-card:hover {
    border-color: rgba(0,200,255,0.38) !important;
    box-shadow: 0 20px 40px rgba(0,0,0,0.45), 0 0 18px rgba(0,200,255,0.08) !important;
    transform: translateY(-5px) !important;
}

/* ── Blue palette → Cyan/Blue gradient ─────────────────── */
.bg-blue-600, .bg-blue-700, .bg-blue-800, .bg-blue-500,
[class*="bg-netblue"], [class*="bg-lightblue"] {
    background: linear-gradient(135deg,#00c8ff,#0066cc) !important;
    border-color: transparent !important;
}
.hover\:bg-blue-700:hover, .hover\:bg-blue-600:hover {
    background: linear-gradient(135deg,#00aee0,#0055aa) !important;
}
.text-blue-600, .text-blue-500, .text-blue-700, .text-blue-400,
[class*="text-netblue"], [class*="text-lightblue"] {
    color: #00c8ff !important;
}
.border-blue-600, .border-blue-500, .border-blue-400 {
    border-color: rgba(0,200,255,0.4) !important;
}
.ring-blue-500, .focus\:ring-blue-500:focus {
    --tw-ring-color: rgba(0,200,255,0.4) !important;
}

/* ── Green palette ──────────────────────────────────────── */
.bg-green-600, .bg-green-500 { background: #10b981 !important; }
.text-green-600, .text-green-500 { color: #10b981 !important; }

/* ── Text tone overrides ────────────────────────────────── */
.text-gray-900, .text-gray-800 { color: #f1f5f9 !important; }
.text-gray-700, .text-gray-600 { color: #cbd5e1 !important; }
.text-gray-500, .text-gray-400 { color: #94a3b8 !important; }
.text-gray-300                 { color: #e2e8f0 !important; }

/* ── Background overrides ───────────────────────────────── */
.bg-gray-50, .bg-gray-100 { background: rgba(10,24,58,0.5) !important; }
.bg-gray-200              { background: rgba(10,24,58,0.7) !important; }
.bg-gray-700, .bg-gray-800, .bg-gray-900 {
    background: rgba(6,13,30,0.9) !important;
}

/* ── Inputs & Selects ───────────────────────────────────── */
input:not([type=radio]):not([type=checkbox]),
select, textarea {
    background: rgba(10,24,58,0.82) !important;
    border: 1px solid rgba(0,200,255,0.22) !important;
    color: #fff !important;
    border-radius: 10px !important;
}
input:not([type=radio]):not([type=checkbox]):focus,
select:focus, textarea:focus {
    border-color: rgba(0,200,255,0.55) !important;
    box-shadow: 0 0 0 2px rgba(0,200,255,0.12) !important;
    outline: none !important;
}
input::placeholder, textarea::placeholder { color: rgba(255,255,255,0.35) !important; }
select option { background: #0a1835 !important; color: #e2e8f0 !important; }

/* ── Dividers / borders ─────────────────────────────────── */
.border-gray-200, .border-gray-100, .border-gray-300,
.divide-gray-200 > * + *, .divide-gray-100 > * + * {
    border-color: rgba(0,200,255,0.1) !important;
}

/* ── Buttons ────────────────────────────────────────────── */
.btn-primary, button[type="submit"] {
    background: linear-gradient(135deg,#00c8ff,#0066cc) !important;
    color: #fff !important;
    border: none !important;
    border-radius: 10px !important;
    padding: 10px 20px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
}
.btn-primary:hover, button[type="submit"]:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 20px rgba(0,200,255,0.3);
}

/* ── Badges ─────────────────────────────────────────────── */
.badge {
    background: rgba(0,200,255,0.1);
    border: 1px solid rgba(0,200,255,0.25);
    color: #00c8ff;
    padding: 4px 14px;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

/* ── Progress bars ──────────────────────────────────────── */
.bg-blue-500[style*="width"], .progress-bar {
    background: linear-gradient(90deg,#00c8ff,#0066cc) !important;
}

/* ── Gradient text ──────────────────────────────────────── */
.gradient-text {
    background: linear-gradient(135deg,#00c8ff,#0066cc);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* ── Scrollbar ──────────────────────────────────────────── */
::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: #060d1e; }
::-webkit-scrollbar-thumb { background: rgba(0,200,255,0.3); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: rgba(0,200,255,0.5); }

/* ── lang switcher ──────────────────────────────────────── */
.nc-lang-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 5px 12px; border-radius: 8px; font-size: 0.75rem; font-weight: 700;
    border: 1px solid rgba(0,200,255,0.3); color: #00c8ff;
    background: rgba(0,200,255,0.07); text-decoration: none;
    transition: background 0.2s;
}
.nc-lang-btn:hover { background: rgba(0,200,255,0.15); }
</style>
<?php
} // end NC_THEME_CSS_EMITTED guard
?>
