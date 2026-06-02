<?php
$page_title = 'Aperçu SEO & Réseaux Sociaux — Netcrafter Outils';
require_once __DIR__ . '/../includes/header.php';
$_d = fn($fr, $en) => ($GLOBALS['nc_lang'] ?? 'fr') === 'en' ? $en : $fr;
?>

<!-- ═══════════════════════════════════════
     HERO
═══════════════════════════════════════ -->
<section class="relative pt-32 pb-12 overflow-hidden">
    <div class="blob bg-nc-cyan"  style="width:500px;height:500px;top:-180px;left:-180px;"></div>
    <div class="blob bg-nc-blue"  style="width:400px;height:400px;bottom:-100px;right:-120px;animation-delay:2s;"></div>
    <div class="blob bg-nc-green" style="width:300px;height:300px;top:50%;right:20%;animation-delay:4s;opacity:0.05;"></div>

    <div class="absolute inset-0 pointer-events-none"
         style="background-image:linear-gradient(rgba(0,200,255,0.04) 1px,transparent 1px),linear-gradient(90deg,rgba(0,200,255,0.04) 1px,transparent 1px);background-size:60px 60px;"></div>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center">
        <a href="<?= BASE ?>/outils/" class="inline-flex items-center gap-2 text-gray-500 hover:text-nc-cyan transition-colors text-sm mb-6" data-aos="fade-down">
            <i class="fas fa-arrow-left text-xs"></i> <?= $_d('Retour aux Outils','Back to Tools') ?>
        </a>
        <div class="inline-flex items-center gap-2 badge mb-6" data-aos="fade-down" data-aos-delay="50">
            <i class="fas fa-search"></i> <?= $_d('Outils SEO','SEO Tools') ?>
        </div>
        <h1 class="font-heading font-black text-5xl md:text-6xl text-white mb-5" data-aos="fade-up">
            <?= $_d('Aperçu','Preview') ?> <span class="gradient-text"><?= $_d('SEO & Réseaux Sociaux','SEO & Social Media') ?></span>
        </h1>
        <p class="text-gray-400 text-xl max-w-3xl mx-auto leading-relaxed" data-aos="fade-up" data-aos-delay="100">
            <?= $_d('Visualisez l\'apparence de votre page dans les résultats Google et sur les réseaux sociaux en temps réel.','Visualize how your page appears in Google results and on social media in real time.') ?>
        </p>
    </div>
</section>

<!-- ═══════════════════════════════════════
     MAIN TOOL
═══════════════════════════════════════ -->
<section class="pb-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-5 gap-6 items-start">

            <!-- LEFT — Form (sticky) -->
            <div class="lg:col-span-2 lg:sticky lg:top-24 space-y-4" data-aos="fade-right">
                <div class="glass rounded-3xl p-6">
                    <h2 class="font-heading font-bold text-lg text-white mb-6 flex items-center gap-2">
                        <i class="fas fa-edit text-nc-cyan"></i> <?= $_d('Informations de la page','Page information') ?>
                    </h2>

                    <!-- Page title -->
                    <div class="mb-5">
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-sm font-semibold text-gray-400 uppercase tracking-wider"><?= $_d('Titre de la page','Page title') ?></label>
                            <span id="title-count" class="text-xs font-bold px-2 py-0.5 rounded-full" style="background:rgba(16,185,129,0.15);color:#10b981;">0/60</span>
                        </div>
                        <input type="text" id="f-title" maxlength="70"
                               placeholder="<?= $_d('Mon super titre de page…','My awesome page title…') ?>"
                               value="Netcrafter – Développement Web &amp; Solutions Numériques"
                               class="nc-input w-full">
                        <div class="mt-2 h-1.5 rounded-full overflow-hidden" style="background:rgba(255,255,255,0.06);">
                            <div id="title-bar" class="h-full rounded-full transition-all duration-300" style="width:0%;background:#10b981;"></div>
                        </div>
                    </div>

                    <!-- URL -->
                    <div class="mb-5">
                        <label class="block text-sm font-semibold text-gray-400 mb-2 uppercase tracking-wider"><?= $_d('URL de la page','Page URL') ?></label>
                        <input type="text" id="f-url"
                               placeholder="https://votre-site.com/page"
                               value="https://netcrafter.net/solutions"
                               class="nc-input w-full">
                    </div>

                    <!-- Meta description -->
                    <div class="mb-5">
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-sm font-semibold text-gray-400 uppercase tracking-wider">Meta description</label>
                            <span id="desc-count" class="text-xs font-bold px-2 py-0.5 rounded-full" style="background:rgba(16,185,129,0.15);color:#10b981;">0/160</span>
                        </div>
                        <textarea id="f-desc" maxlength="180" rows="3"
                                  placeholder="<?= $_d('Description de votre page pour les moteurs de recherche…','Describe your page for search engines…') ?>"
                                  class="nc-input w-full resize-none">Expert en développement web, cybersécurité et marketing digital au Niger. Des solutions numériques sur mesure pour votre entreprise.</textarea>
                        <div class="mt-2 h-1.5 rounded-full overflow-hidden" style="background:rgba(255,255,255,0.06);">
                            <div id="desc-bar" class="h-full rounded-full transition-all duration-300" style="width:0%;background:#10b981;"></div>
                        </div>
                    </div>

                    <!-- Image URL -->
                    <div class="mb-5">
                        <label class="block text-sm font-semibold text-gray-400 mb-2 uppercase tracking-wider"><?= $_d('Image de partage (URL)','Share image (URL)') ?></label>
                        <input type="text" id="f-image"
                               placeholder="https://votre-site.com/image.jpg (1200×630)"
                               value=""
                               class="nc-input w-full">
                        <p class="text-gray-600 text-xs mt-1"><?= $_d('Dimensions recommandées : 1200 × 630 px','Recommended dimensions: 1200 × 630 px') ?></p>
                    </div>

                    <!-- Site name -->
                    <div class="mb-5">
                        <label class="block text-sm font-semibold text-gray-400 mb-2 uppercase tracking-wider"><?= $_d('Nom du site','Site name') ?></label>
                        <input type="text" id="f-sitename"
                               placeholder="<?= $_d('Nom de votre site','Your site name') ?>"
                               value="Netcrafter"
                               class="nc-input w-full">
                    </div>

                    <!-- Type + Author row -->
                    <div class="grid grid-cols-2 gap-3 mb-2">
                        <div>
                            <label class="block text-sm font-semibold text-gray-400 mb-2 uppercase tracking-wider text-xs">Type</label>
                            <div class="relative">
                                <select id="f-type" class="nc-input w-full appearance-none">
                                    <option value="website"><?= $_d('Site web','Website') ?></option>
                                    <option value="article"><?= $_d('Article','Article') ?></option>
                                    <option value="product"><?= $_d('Produit','Product') ?></option>
                                </select>
                                <i class="fas fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 text-xs pointer-events-none"></i>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-400 mb-2 uppercase tracking-wider text-xs"><?= $_d('Auteur','Author') ?></label>
                            <input type="text" id="f-author"
                                   placeholder="@username"
                                   value="@netcrafter"
                                   class="nc-input w-full">
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT — Live Previews -->
            <div class="lg:col-span-3 space-y-5" data-aos="fade-left">

                <!-- Tabs -->
                <div class="flex gap-2 flex-wrap">
                    <?php
                    $tabs = [
                        ['google',   'fa-google',       'Google',    '#4285f4'],
                        ['facebook', 'fab fa-facebook', 'Facebook',  '#1877f2'],
                        ['twitter',  'fab fa-twitter',  'Twitter/X', '#1da1f2'],
                        ['whatsapp', 'fab fa-whatsapp', 'WhatsApp',  '#25d366'],
                    ];
                    foreach ($tabs as $i => [$id, $icon, $label, $color]): ?>
                    <button class="preview-tab flex items-center gap-2 px-4 py-2 rounded-full text-sm font-semibold transition-all"
                            data-tab="<?= $id ?>"
                            style="<?= $i === 0 ? "background:{$color}22;color:{$color};border:1px solid {$color}44;" : 'background:rgba(255,255,255,0.04);color:#94a3b8;border:1px solid rgba(255,255,255,0.08);' ?>">
                        <i class="<?= $icon ?> text-xs"></i> <?= $label ?>
                    </button>
                    <?php endforeach; ?>
                </div>

                <!-- GOOGLE preview -->
                <div id="preview-google" class="preview-panel">
                    <div class="glass rounded-3xl p-6">
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-7 h-7 rounded-full flex items-center justify-center" style="background:#4285f433;">
                                <i class="fab fa-google text-xs" style="color:#4285f4;"></i>
                            </div>
                            <h3 class="font-heading font-semibold text-white text-sm"><?= $_d('Résultat Google Search','Google Search result') ?></h3>
                        </div>

                        <!-- Google card -->
                        <div class="rounded-2xl p-5" style="background:#fff;">
                            <div id="g-url" class="text-xs mb-1.5 flex items-center gap-1" style="color:#3c4043;font-family:arial,sans-serif;">
                                <span style="color:#202124;">&#x1F512;</span>
                                <span id="g-url-text" style="color:#0d652d;font-size:14px;">netcrafter.net › solutions</span>
                            </div>
                            <div id="g-title" class="text-xl mb-1 hover:underline cursor-pointer" style="color:#1a0dab;font-family:arial,sans-serif;font-size:20px;line-height:1.3;">Netcrafter – Développement Web &amp; Solutions Numériques</div>
                            <div id="g-desc" class="text-sm leading-snug" style="color:#3c4043;font-family:arial,sans-serif;font-size:14px;">Expert en développement web, cybersécurité et marketing digital au Niger. Des solutions numériques sur mesure pour votre entreprise.</div>
                        </div>

                        <!-- Warnings -->
                        <div id="g-warnings" class="mt-3 space-y-1.5"></div>
                    </div>
                </div>

                <!-- FACEBOOK preview -->
                <div id="preview-facebook" class="preview-panel hidden">
                    <div class="glass rounded-3xl p-6">
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-7 h-7 rounded-full flex items-center justify-center" style="background:#1877f222;">
                                <i class="fab fa-facebook text-xs" style="color:#1877f2;"></i>
                            </div>
                            <h3 class="font-heading font-semibold text-white text-sm"><?= $_d('Aperçu Facebook / Open Graph','Facebook / Open Graph preview') ?></h3>
                        </div>

                        <div class="rounded-2xl overflow-hidden" style="border:1px solid #dddfe2;background:#fff;max-width:500px;">
                            <div id="fb-image-wrap" class="w-full bg-gray-100 flex items-center justify-center" style="min-height:180px;background:#e9ebee;">
                                <img id="fb-image" src="" alt="" class="w-full object-cover hidden" style="max-height:261px;">
                                <div id="fb-image-placeholder" class="text-center py-8">
                                    <i class="fas fa-image text-4xl" style="color:#bec3c9;"></i>
                                    <p class="text-xs mt-2" style="color:#bec3c9;">Image OG (1200×630)</p>
                                </div>
                            </div>
                            <div class="p-3" style="background:#f2f3f5;border-top:1px solid #dddfe2;">
                                <div id="fb-sitename" class="text-xs uppercase mb-1" style="color:#606770;font-family:Helvetica,sans-serif;">NETCRAFTER.NET</div>
                                <div id="fb-title" class="font-bold text-sm leading-snug mb-0.5" style="color:#1d2129;font-family:Helvetica,sans-serif;">Netcrafter – Développement Web & Solutions Numériques</div>
                                <div id="fb-desc" class="text-xs leading-snug" style="color:#606770;font-family:Helvetica,sans-serif;">Expert en développement web, cybersécurité et marketing digital au Niger.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TWITTER preview -->
                <div id="preview-twitter" class="preview-panel hidden">
                    <div class="glass rounded-3xl p-6">
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-7 h-7 rounded-full flex items-center justify-center" style="background:#1da1f222;">
                                <i class="fab fa-twitter text-xs" style="color:#1da1f2;"></i>
                            </div>
                            <h3 class="font-heading font-semibold text-white text-sm">Twitter / X Card</h3>
                        </div>

                        <div class="rounded-2xl overflow-hidden" style="border:1px solid #2f3336;background:#000;max-width:500px;">
                            <div id="tw-image-wrap" class="w-full flex items-center justify-center" style="min-height:180px;background:#1a1a1a;">
                                <img id="tw-image" src="" alt="" class="w-full object-cover hidden" style="max-height:261px;">
                                <div id="tw-image-placeholder" class="text-center py-8">
                                    <i class="fas fa-image text-3xl" style="color:#555;"></i>
                                </div>
                            </div>
                            <div class="p-3">
                                <div id="tw-title" class="font-bold text-sm text-white mb-0.5" style="font-family:-apple-system,sans-serif;">Netcrafter – Développement Web & Solutions Numériques</div>
                                <div id="tw-desc" class="text-xs leading-snug mb-1" style="color:#8b98a5;font-family:-apple-system,sans-serif;">Expert en développement web, cybersécurité et marketing digital au Niger.</div>
                                <div class="flex items-center gap-1 text-xs" style="color:#8b98a5;">
                                    <i class="fas fa-link text-xs"></i>
                                    <span id="tw-url">netcrafter.net</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- WHATSAPP preview -->
                <div id="preview-whatsapp" class="preview-panel hidden">
                    <div class="glass rounded-3xl p-6">
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-7 h-7 rounded-full flex items-center justify-center" style="background:#25d36622;">
                                <i class="fab fa-whatsapp text-xs" style="color:#25d366;"></i>
                            </div>
                            <h3 class="font-heading font-semibold text-white text-sm"><?= $_d('Aperçu WhatsApp','WhatsApp preview') ?></h3>
                        </div>

                        <!-- Chat bubble -->
                        <div style="background:#0d1117;border-radius:16px;padding:20px;max-width:420px;">
                            <div style="background:#1e2b2e;border-radius:12px;overflow:hidden;border-left:4px solid #25d366;">
                                <div id="wa-image-wrap" class="hidden">
                                    <img id="wa-image" src="" alt="" class="w-full object-cover" style="max-height:180px;">
                                </div>
                                <div class="p-3">
                                    <div id="wa-sitename" class="text-xs font-semibold mb-1" style="color:#25d366;font-family:sans-serif;">netcrafter.net</div>
                                    <div id="wa-title" class="text-sm font-bold text-white mb-1" style="font-family:sans-serif;">Netcrafter – Développement Web & Solutions Numériques</div>
                                    <div id="wa-desc" class="text-xs leading-snug" style="color:#8696a0;font-family:sans-serif;">Expert en développement web, cybersécurité et marketing digital au Niger.</div>
                                </div>
                            </div>
                            <div class="mt-2 text-right">
                                <span class="text-xs" style="color:#8696a0;">14:32 ✓✓</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tips section -->
                <div class="glass rounded-3xl p-6" data-aos="fade-up">
                    <h3 class="font-heading font-bold text-lg text-white mb-5 flex items-center gap-2">
                        <i class="fas fa-lightbulb text-yellow-400"></i> <?= $_d('Bonnes pratiques SEO','SEO best practices') ?>
                    </h3>
                    <ul class="space-y-3">
                        <?php
                        $tips = [
                            ['#10b981', 'fa-check-circle',
                             $_d('Titre entre 50-60 caractères','Title between 50-60 characters'),
                             $_d('Un titre trop court manque d\'informations, trop long sera tronqué dans les SERP.','A title that is too short lacks information; too long and it will be truncated in SERPs.')],
                            ['#00c8ff', 'fa-check-circle',
                             $_d('Description entre 120-160 caractères','Description between 120-160 characters'),
                             $_d('Google peut réécrire votre description, mais une bonne méta-description améliore le CTR.','Google may rewrite your description, but a well-crafted meta description improves CTR.')],
                            ['#7c3aed', 'fa-check-circle',
                             $_d('Image OG recommandée : 1200×630px','Recommended OG image: 1200×630px'),
                             $_d('Ratio 1.91:1. Évitez les textes importants dans les marges de l\'image (peuvent être rognées).','Ratio 1.91:1. Avoid important text near image edges — they may be cropped on some platforms.')],
                            ['#f59e0b', 'fa-check-circle',
                             $_d('Un seul H1 par page','One H1 per page'),
                             $_d('Le H1 doit correspondre au titre principal et contenir le mot-clé principal.','The H1 should match the main heading and include the primary keyword.')],
                            ['#0066cc', 'fa-check-circle',
                             $_d('URL courte et descriptive','Short, descriptive URL'),
                             $_d('Privilégiez les mots-clés, évitez les paramètres inutiles. Séparez les mots avec des tirets.','Use keywords, avoid unnecessary parameters. Separate words with hyphens.')],
                        ];
                        foreach ($tips as $tip): ?>
                        <li class="flex items-start gap-3">
                            <i class="fas <?= $tip[1] ?> text-sm mt-0.5 flex-shrink-0" style="color:<?= $tip[0] ?>"></i>
                            <div>
                                <div class="text-sm font-semibold text-white"><?= $tip[2] ?></div>
                                <div class="text-xs text-gray-500 leading-relaxed"><?= $tip[3] ?></div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

            </div><!-- /right col -->
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<style>
.nc-input {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(0,200,255,0.18);
    border-radius: 12px;
    padding: 10px 14px;
    color: #fff;
    font-size: 0.875rem;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.nc-input:focus {
    border-color: rgba(0,200,255,0.55);
    box-shadow: 0 0 0 3px rgba(0,200,255,0.09);
}
.nc-input::placeholder { color: #4b5563; }
select.nc-input option { background: #0a1835; color: #fff; }
.preview-tab.active-tab {
    font-weight: 700;
}
</style>

<script>
const i18n = <?= json_encode([
    'title_ph'    => $_d('Titre de la page','Page title'),
    'desc_ph'     => $_d('Description de la page…','Page description…'),
    'site_ph'     => $_d('Votre Site','Your Site'),
    'title_long'  => $_d('Titre trop long','Title too long'),
    'title_short' => $_d('Titre trop court','Title too short'),
    'desc_long'   => $_d('Description trop longue','Description too long'),
    'desc_short'  => $_d('Description trop courte','Description too short'),
], JSON_UNESCAPED_UNICODE) ?>;

// ── Field refs ───────────────────────────────────────────────
const fTitle    = document.getElementById('f-title');
const fUrl      = document.getElementById('f-url');
const fDesc     = document.getElementById('f-desc');
const fImage    = document.getElementById('f-image');
const fSitename = document.getElementById('f-sitename');
const fType     = document.getElementById('f-type');
const fAuthor   = document.getElementById('f-author');

// ── Tab switching ────────────────────────────────────────────
const tabColors = {
    google:   '#4285f4',
    facebook: '#1877f2',
    twitter:  '#1da1f2',
    whatsapp: '#25d366',
};

document.querySelectorAll('.preview-tab').forEach(btn => {
    btn.addEventListener('click', () => {
        const tab = btn.dataset.tab;
        document.querySelectorAll('.preview-panel').forEach(p => p.classList.add('hidden'));
        document.getElementById('preview-' + tab).classList.remove('hidden');
        document.querySelectorAll('.preview-tab').forEach(b => {
            b.style.cssText = 'background:rgba(255,255,255,0.04);color:#94a3b8;border:1px solid rgba(255,255,255,0.08);';
            b.classList.remove('active-tab');
        });
        const c = tabColors[tab] || '#00c8ff';
        btn.style.cssText = `background:${c}22;color:${c};border:1px solid ${c}44;`;
        btn.classList.add('active-tab');
    });
});

// ── Character counters ───────────────────────────────────────
function updateCounter(input, countEl, barEl, min, max) {
    const len = input.value.length;
    const pct = Math.min(100, (len / max) * 100);
    countEl.textContent = len + '/' + max;

    let color;
    if (input === fTitle) {
        if      (len < 50)  color = '#10b981';
        else if (len <= 60) color = '#f59e0b';
        else                color = '#ef4444';
    } else {
        if      (len < 120)  color = '#10b981';
        else if (len <= 160) color = '#f59e0b';
        else                 color = '#ef4444';
    }

    countEl.style.background = color + '22';
    countEl.style.color      = color;
    barEl.style.width        = pct + '%';
    barEl.style.background   = color;
}

function getDisplayUrl(url) {
    try {
        const u = new URL(url.startsWith('http') ? url : 'https://' + url);
        return u.hostname.replace('www.', '') + u.pathname;
    } catch(e) { return url.replace(/^https?:\/\/(www\.)?/, ''); }
}

function getHostname(url) {
    try {
        return new URL(url.startsWith('http') ? url : 'https://' + url).hostname.replace('www.', '');
    } catch(e) { return url.replace(/^https?:\/\/(www\.)?/, '').split('/')[0]; }
}

// ── Live update all previews ─────────────────────────────────
function updatePreviews() {
    const title    = fTitle.value    || i18n.title_ph;
    const url      = fUrl.value      || 'https://votre-site.com';
    const desc     = fDesc.value     || i18n.desc_ph;
    const image    = fImage.value.trim();
    const sitename = fSitename.value || i18n.site_ph;

    // Counters
    updateCounter(fTitle, document.getElementById('title-count'), document.getElementById('title-bar'), 50, 60);
    updateCounter(fDesc,  document.getElementById('desc-count'),  document.getElementById('desc-bar'),  120, 160);

    const displayUrl = getDisplayUrl(url);
    const hostname   = getHostname(url);

    // ── Google ──
    document.getElementById('g-url-text').textContent = displayUrl;
    const gTitle = document.getElementById('g-title');
    gTitle.textContent = title.length > 60 ? title.substring(0,57) + '…' : title;
    gTitle.style.color = title.length > 60 ? '#b00000' : '#1a0dab';
    document.getElementById('g-desc').textContent = desc.length > 160 ? desc.substring(0,157) + '…' : desc;

    // Warnings
    const warnings = [];
    if (title.length > 60) warnings.push({ msg: `${i18n.title_long} (${title.length}/60)`, col: '#f97316' });
    if (title.length < 10) warnings.push({ msg: i18n.title_short, col: '#ef4444' });
    if (desc.length > 160) warnings.push({ msg: `${i18n.desc_long} (${desc.length}/160)`, col: '#f97316' });
    if (desc.length < 50)  warnings.push({ msg: i18n.desc_short, col: '#ef4444' });
    const wEl = document.getElementById('g-warnings');
    wEl.innerHTML = warnings.map(w =>
        `<div class="flex items-center gap-2 text-xs px-3 py-1.5 rounded-lg" style="background:${w.col}18;color:${w.col};border:1px solid ${w.col}33;">
            <i class="fas fa-exclamation-triangle text-xs"></i> ${w.msg}
        </div>`
    ).join('');

    // ── Facebook ──
    document.getElementById('fb-title').textContent    = title;
    document.getElementById('fb-desc').textContent     = desc.length > 100 ? desc.substring(0,97) + '…' : desc;
    document.getElementById('fb-sitename').textContent = hostname.toUpperCase();
    setImagePreview('fb-image', 'fb-image-placeholder', image);

    // ── Twitter ──
    document.getElementById('tw-title').textContent = title;
    document.getElementById('tw-desc').textContent  = desc.length > 100 ? desc.substring(0,97) + '…' : desc;
    document.getElementById('tw-url').textContent   = hostname;
    setImagePreview('tw-image', 'tw-image-placeholder', image);

    // ── WhatsApp ──
    document.getElementById('wa-title').textContent    = title;
    document.getElementById('wa-desc').textContent     = desc.length > 80 ? desc.substring(0,77) + '…' : desc;
    document.getElementById('wa-sitename').textContent = hostname;
    const waImageWrap = document.getElementById('wa-image-wrap');
    const waImg = document.getElementById('wa-image');
    if (image) {
        waImg.src = image;
        waImageWrap.classList.remove('hidden');
    } else {
        waImageWrap.classList.add('hidden');
    }
}

function setImagePreview(imgId, placeholderId, src) {
    const img = document.getElementById(imgId);
    const ph  = document.getElementById(placeholderId);
    if (src) {
        img.src = src;
        img.classList.remove('hidden');
        if (ph) ph.classList.add('hidden');
    } else {
        img.classList.add('hidden');
        if (ph) ph.classList.remove('hidden');
    }
}

// Listen to all inputs
[fTitle, fUrl, fDesc, fImage, fSitename, fType, fAuthor].forEach(el => {
    el.addEventListener('input', updatePreviews);
});

// Initial render
updatePreviews();
</script>
