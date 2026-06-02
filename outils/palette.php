<?php
$page_title = 'Générateur de Palette — Netcrafter Outils';
require_once __DIR__ . '/../includes/header.php';
$_d = fn($fr, $en) => ($GLOBALS['nc_lang'] ?? 'fr') === 'en' ? $en : $fr;
?>

<!-- ═══════════════════════════════════════
     HERO
═══════════════════════════════════════ -->
<section class="relative pt-32 pb-12 overflow-hidden">
    <div class="blob bg-nc-cyan"  style="width:500px;height:500px;top:-180px;left:-180px;"></div>
    <div class="blob bg-nc-violet" style="width:400px;height:400px;bottom:-100px;right:-120px;animation-delay:2s;"></div>

    <div class="absolute inset-0 pointer-events-none"
         style="background-image:linear-gradient(rgba(0,200,255,0.04) 1px,transparent 1px),linear-gradient(90deg,rgba(0,200,255,0.04) 1px,transparent 1px);background-size:60px 60px;"></div>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center">
        <a href="<?= BASE ?>/outils/" class="inline-flex items-center gap-2 text-gray-500 hover:text-nc-cyan transition-colors text-sm mb-6" data-aos="fade-down">
            <i class="fas fa-arrow-left text-xs"></i> <?= $_d('Retour aux Outils','Back to Tools') ?>
        </a>
        <div class="inline-flex items-center gap-2 badge mb-6" data-aos="fade-down" data-aos-delay="50">
            <i class="fas fa-palette"></i> <?= $_d('Outils Créatifs','Creative Tools') ?>
        </div>
        <h1 class="font-heading font-black text-5xl md:text-6xl text-white mb-5" data-aos="fade-up">
            <?= $_d('Générateur de','Color') ?> <span class="gradient-text"><?= $_d('Palette de Couleurs','Palette Generator') ?></span>
        </h1>
        <p class="text-gray-400 text-xl max-w-2xl mx-auto leading-relaxed" data-aos="fade-up" data-aos-delay="100">
            <?= $_d('Créez des palettes harmonieuses en un clic grâce à la théorie des couleurs HSL.','Create harmonious palettes in one click using HSL color theory.') ?>
        </p>
    </div>
</section>

<!-- ═══════════════════════════════════════
     MAIN TOOL
═══════════════════════════════════════ -->
<section class="pb-12">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-5 gap-6">

            <!-- LEFT PANEL — Controls -->
            <div class="lg:col-span-2 space-y-5" data-aos="fade-right">
                <div class="glass rounded-3xl p-6">
                    <h2 class="font-heading font-bold text-lg text-white mb-6 flex items-center gap-2">
                        <i class="fas fa-sliders-h text-nc-cyan"></i> <?= $_d('Paramètres','Settings') ?>
                    </h2>

                    <!-- Color Picker -->
                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-400 mb-3 uppercase tracking-wider"><?= $_d('Couleur de base','Base color') ?></label>
                        <div class="flex gap-3 items-center">
                            <div class="relative">
                                <input type="color" id="color-picker" value="#00c8ff"
                                       class="w-16 h-16 rounded-xl cursor-pointer border-0 p-0 bg-transparent"
                                       style="outline:none;">
                                <div class="absolute inset-0 rounded-xl pointer-events-none"
                                     style="box-shadow:0 0 0 2px rgba(0,200,255,0.3);"></div>
                            </div>
                            <div class="flex-1">
                                <input type="text" id="hex-input" value="#00c8ff" maxlength="7"
                                       placeholder="#000000"
                                       class="w-full px-4 py-3 rounded-xl text-white font-mono text-lg uppercase placeholder-gray-600 outline-none transition-all"
                                       style="background:rgba(255,255,255,0.06);border:1px solid rgba(0,200,255,0.2);">
                                <div id="hex-error" class="text-red-400 text-xs mt-1 hidden"><?= $_d('Hex invalide','Invalid hex') ?></div>
                            </div>
                        </div>
                        <!-- Color preview bar -->
                        <div id="color-preview-bar" class="w-full h-3 rounded-full mt-3 transition-all duration-300"
                             style="background:#00c8ff;box-shadow:0 0 20px rgba(0,200,255,0.5);"></div>
                    </div>

                    <!-- Mode dropdown -->
                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-400 mb-3 uppercase tracking-wider"><?= $_d('Mode de palette','Palette mode') ?></label>
                        <div class="relative">
                            <select id="palette-mode"
                                    class="w-full px-4 py-3 rounded-xl text-white appearance-none outline-none transition-all cursor-pointer"
                                    style="background:rgba(255,255,255,0.06);border:1px solid rgba(0,200,255,0.2);">
                                <option value="complementary"><?= $_d('Complémentaire','Complementary') ?></option>
                                <option value="mono"><?= $_d('Monochromatique','Monochromatic') ?></option>
                                <option value="analogous"><?= $_d('Analogue','Analogous') ?></option>
                                <option value="triadic"><?= $_d('Triadique','Triadic') ?></option>
                                <option value="tetradic"><?= $_d('Tétradique','Tetradic') ?></option>
                            </select>
                            <i class="fas fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-xs"></i>
                        </div>
                    </div>

                    <!-- Generate button -->
                    <button id="generate-btn" class="btn-primary w-full justify-center text-base">
                        <i class="fas fa-magic"></i> <?= $_d('Générer la palette','Generate palette') ?>
                    </button>

                    <!-- Export buttons -->
                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <button id="export-css" class="btn-ghost w-full justify-center text-xs py-2.5">
                            <i class="fas fa-code"></i> CSS Variables
                        </button>
                        <button id="export-tailwind" class="btn-ghost w-full justify-center text-xs py-2.5">
                            <i class="fab fa-css3-alt"></i> Tailwind Config
                        </button>
                    </div>

                    <!-- Copy feedback -->
                    <div id="copy-feedback" class="hidden mt-3 text-center text-sm font-semibold text-nc-green py-2 rounded-xl"
                         style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.2);">
                        <i class="fas fa-check mr-2"></i><span id="copy-msg"><?= $_d('Copié !','Copied!') ?></span>
                    </div>
                </div>

                <!-- Info panel -->
                <div class="glass rounded-3xl p-6">
                    <h3 class="font-heading font-semibold text-white mb-4 flex items-center gap-2">
                        <i class="fas fa-info-circle text-nc-cyan text-sm"></i> <?= $_d('Couleur sélectionnée','Selected color') ?>
                    </h3>
                    <div id="color-info" class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500">HEX</span>
                            <span id="info-hex" class="text-white font-mono">#00c8ff</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">RGB</span>
                            <span id="info-rgb" class="text-white font-mono">rgb(0, 200, 255)</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">HSL</span>
                            <span id="info-hsl" class="text-white font-mono">hsl(192, 100%, 50%)</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT PANEL — Generated Palette -->
            <div class="lg:col-span-3" data-aos="fade-left">
                <div class="glass rounded-3xl p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="font-heading font-bold text-lg text-white flex items-center gap-2">
                            <i class="fas fa-swatchbook text-nc-violet"></i> <?= $_d('Palette générée','Generated palette') ?>
                        </h2>
                        <span id="mode-label" class="badge text-xs"><?= $_d('Complémentaire','Complementary') ?></span>
                    </div>

                    <!-- Swatches -->
                    <div id="palette-swatches" class="space-y-3"></div>

                    <!-- Wide preview bar -->
                    <div id="palette-bar" class="mt-5 h-14 rounded-2xl overflow-hidden flex"
                         style="box-shadow:0 0 30px rgba(0,0,0,0.5);"></div>
                </div>

                <!-- Color wheel info -->
                <div class="mt-5 grid grid-cols-3 gap-3">
                    <?php
                    $modes = [
                        ['icon'=>'fa-circle',   'title'=>$_d('Monochromatique','Monochromatic'), 'desc'=>$_d('Teintes d\'une même couleur à différentes luminosités.','Shades of one color at varying lightness levels.')],
                        ['icon'=>'fa-yin-yang', 'title'=>$_d('Complémentaire','Complementary'),  'desc'=>$_d('Couleurs opposées sur le cercle chromatique.','Opposite colors on the color wheel.')],
                        ['icon'=>'fa-shapes',   'title'=>$_d('Triadique','Triadic'),             'desc'=>$_d('Trois couleurs équidistantes à 120° sur le cercle.','Three colors equidistant at 120° on the wheel.')],
                    ];
                    foreach ($modes as $m): ?>
                    <div class="glass rounded-2xl p-4 text-center" style="border-color:rgba(124,58,237,0.2);">
                        <i class="fas <?= $m['icon'] ?> text-nc-violet mb-2 block text-sm"></i>
                        <div class="font-semibold text-white text-xs mb-1"><?= $m['title'] ?></div>
                        <p class="text-gray-600 text-xs leading-snug"><?= $m['desc'] ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════
     WHY COLORS MATTER
═══════════════════════════════════════ -->
<section class="py-16">
    <div class="section-divider mb-16"></div>
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12" data-aos="fade-up">
            <div class="inline-flex items-center gap-2 badge mb-4">
                <i class="fas fa-star"></i> <?= $_d('Conseil design','Design tip') ?>
            </div>
            <h2 class="font-heading font-bold text-3xl md:text-4xl text-white">
                <?= $_d('Pourquoi les bonnes couleurs','Why the right colors') ?> <span class="gradient-text"><?= $_d('comptent','matter') ?></span>
            </h2>
        </div>
        <div class="grid md:grid-cols-3 gap-6">
            <?php
            $why = [
                ['fa-brain',    '#00c8ff',
                 $_d('Psychologie des couleurs','Color psychology'),
                 $_d('Les couleurs influencent les émotions et les décisions. Le bleu inspire confiance, le vert évoque la croissance, le rouge crée l\'urgence.','Colors influence emotions and decisions. Blue inspires trust, green evokes growth, red creates urgency.')],
                ['fa-eye',      '#7c3aed',
                 $_d('Lisibilité et contraste','Readability & contrast'),
                 $_d('Un bon ratio de contraste (4.5:1 minimum) garantit l\'accessibilité WCAG et une lecture confortable sur tous les écrans.','A good contrast ratio (4.5:1 minimum) ensures WCAG accessibility and comfortable reading on all screens.')],
                ['fa-bullseye', '#10b981',
                 $_d('Cohérence de marque','Brand consistency'),
                 $_d('Une palette harmonieuse crée une identité visuelle forte et mémorable, renforçant la reconnaissance et la confiance de votre marque.','A harmonious palette creates a strong, memorable visual identity that reinforces brand recognition and trust.')],
            ];
            foreach ($why as $w): ?>
            <div class="glass rounded-2xl p-6 hover-glow" data-aos="fade-up">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-4"
                     style="background:<?= $w[1] ?>18;border:1px solid <?= $w[1] ?>33;">
                    <i class="fas <?= $w[0] ?> text-lg" style="color:<?= $w[1] ?>"></i>
                </div>
                <h3 class="font-heading font-bold text-white mb-2"><?= $w[2] ?></h3>
                <p class="text-gray-500 text-sm leading-relaxed"><?= $w[3] ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<style>
input[type="color"] {
    -webkit-appearance: none;
    appearance: none;
    border: none;
    padding: 0;
    width: 64px; height: 64px;
    border-radius: 12px;
    cursor: pointer;
    overflow: hidden;
}
input[type="color"]::-webkit-color-swatch-wrapper { padding: 0; }
input[type="color"]::-webkit-color-swatch { border: none; border-radius: 12px; }
select option { background: #0a1835; color: #fff; }
#hex-input:focus { border-color: rgba(0,200,255,0.6) !important; box-shadow: 0 0 0 3px rgba(0,200,255,0.1); }
.swatch-card { transition: all 0.3s ease; }
.swatch-card:hover { transform: translateX(4px); }
</style>

<script>
const i18n = <?= json_encode([
    'copied'        => $_d('Copié !','Copied!'),
    'copied_prefix' => $_d('Copié : ','Copied: '),
    'css_copied'    => $_d('CSS Variables copiées !','CSS Variables copied!'),
    'tw_copied'     => $_d('Config Tailwind copiée !','Tailwind Config copied!'),
    'copy_btn'      => $_d('Copier','Copy'),
], JSON_UNESCAPED_UNICODE) ?>;

const modeNames = <?= json_encode([
    'mono'          => $_d('Monochromatique','Monochromatic'),
    'complementary' => $_d('Complémentaire','Complementary'),
    'analogous'     => $_d('Analogue','Analogous'),
    'triadic'       => $_d('Triadique','Triadic'),
    'tetradic'      => $_d('Tétradique','Tetradic'),
], JSON_UNESCAPED_UNICODE) ?>;

// ── Color math ───────────────────────────────────────────────
function hexToRgb(hex) {
    const r = parseInt(hex.slice(1,3),16);
    const g = parseInt(hex.slice(3,5),16);
    const b = parseInt(hex.slice(5,7),16);
    return [r, g, b];
}
function rgbToHsl(r, g, b) {
    r /= 255; g /= 255; b /= 255;
    const max = Math.max(r,g,b), min = Math.min(r,g,b);
    let h, s, l = (max+min)/2;
    if (max === min) { h = s = 0; }
    else {
        const d = max - min;
        s = l > 0.5 ? d/(2-max-min) : d/(max+min);
        switch(max) {
            case r: h = ((g-b)/d + (g<b?6:0))/6; break;
            case g: h = ((b-r)/d + 2)/6; break;
            case b: h = ((r-g)/d + 4)/6; break;
        }
    }
    return [Math.round(h*360), Math.round(s*100), Math.round(l*100)];
}
function hslToHex(h, s, l) {
    h = ((h % 360) + 360) % 360;
    s = Math.min(100, Math.max(0, s));
    l = Math.min(95,  Math.max(5,  l));
    const hn = h/360, sn = s/100, ln = l/100;
    const q = ln < 0.5 ? ln*(1+sn) : ln+sn-ln*sn;
    const p = 2*ln-q;
    const hue2rgb = (p,q,t) => {
        if(t<0)t+=1; if(t>1)t-=1;
        if(t<1/6) return p+(q-p)*6*t;
        if(t<1/2) return q;
        if(t<2/3) return p+(q-p)*(2/3-t)*6;
        return p;
    };
    const r=Math.round(hue2rgb(p,q,hn+1/3)*255);
    const g=Math.round(hue2rgb(p,q,hn)*255);
    const b=Math.round(hue2rgb(p,q,hn-1/3)*255);
    return '#' + [r,g,b].map(v=>v.toString(16).padStart(2,'0')).join('');
}
function hexToHsl(hex) {
    const [r,g,b] = hexToRgb(hex);
    return rgbToHsl(r,g,b);
}
function hexToRgbStr(hex) {
    const [r,g,b] = hexToRgb(hex);
    return `rgb(${r}, ${g}, ${b})`;
}
function isValidHex(h) { return /^#[0-9a-fA-F]{6}$/.test(h); }

function generatePalette(hex, mode) {
    const [h, s, l] = hexToHsl(hex);
    if (mode === 'mono') return [
        hslToHex(h, s, Math.min(95, l+35)),
        hslToHex(h, s, Math.min(85, l+20)),
        hex,
        hslToHex(h, s, Math.max(5, l-20)),
        hslToHex(h, s, Math.max(5, l-35)),
    ];
    if (mode === 'complementary') return [
        hslToHex(h, s, Math.min(90,l+15)), hex,
        hslToHex((h+180)%360, s, Math.min(90,l+10)),
        hslToHex((h+180)%360, s, l),
        hslToHex((h+180)%360, s, Math.max(5,l-15)),
    ];
    if (mode === 'analogous') return [
        hslToHex((h-30+360)%360, s, l),
        hslToHex((h-15+360)%360, s, l),
        hex,
        hslToHex((h+15)%360, s, l),
        hslToHex((h+30)%360, s, l),
    ];
    if (mode === 'triadic') return [
        hex,
        hslToHex((h+120)%360, s, l),
        hslToHex((h+240)%360, s, l),
        hslToHex(h, s, Math.max(5,l-15)),
        hslToHex((h+120)%360, s, Math.max(5,l-15)),
    ];
    // tetradic
    return [
        hex,
        hslToHex((h+90)%360, s, l),
        hslToHex((h+180)%360, s, l),
        hslToHex((h+270)%360, s, l),
        hslToHex(h, s, Math.max(5,l-20)),
    ];
}

// ── DOM refs ─────────────────────────────────────────────────
const picker      = document.getElementById('color-picker');
const hexInput    = document.getElementById('hex-input');
const modeSelect  = document.getElementById('palette-mode');
const genBtn      = document.getElementById('generate-btn');
const swatchWrap  = document.getElementById('palette-swatches');
const barWrap     = document.getElementById('palette-bar');
const modeLabel   = document.getElementById('mode-label');
const copyFeedback = document.getElementById('copy-feedback');
const copyMsg     = document.getElementById('copy-msg');
const previewBar  = document.getElementById('color-preview-bar');
const infoHex     = document.getElementById('info-hex');
const infoRgb     = document.getElementById('info-rgb');
const infoHsl     = document.getElementById('info-hsl');

let currentPalette = [];

function updateColorInfo(hex) {
    if (!isValidHex(hex)) return;
    const [r,g,b] = hexToRgb(hex);
    const [h,s,l] = rgbToHsl(r,g,b);
    infoHex.textContent = hex.toUpperCase();
    infoRgb.textContent = `rgb(${r}, ${g}, ${b})`;
    infoHsl.textContent = `hsl(${h}, ${s}%, ${l}%)`;
    previewBar.style.background = hex;
    previewBar.style.boxShadow  = `0 0 20px ${hex}66`;
}

function renderPalette(hex) {
    if (!isValidHex(hex)) return;
    const mode   = modeSelect.value;
    const colors = generatePalette(hex, mode);
    currentPalette = colors;
    modeLabel.textContent = modeNames[mode] || mode;

    swatchWrap.innerHTML = '';
    colors.forEach((c, i) => {
        const [r,g,b] = hexToRgb(c);
        const [ch,cs,cl] = rgbToHsl(r,g,b);
        const isBase = c.toLowerCase() === hex.toLowerCase();
        swatchWrap.innerHTML += `
            <div class="swatch-card flex items-center gap-4 p-3 rounded-2xl group cursor-pointer"
                 style="background:rgba(255,255,255,0.03);border:1px solid ${isBase ? 'rgba(0,200,255,0.3)' : 'rgba(255,255,255,0.06)'};"
                 onclick="copySingle('${c}')">
                <div class="w-14 h-14 rounded-xl flex-shrink-0 shadow-lg"
                     style="background:${c};box-shadow:0 4px 20px ${c}55;"></div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="font-mono font-bold text-white text-sm">${c.toUpperCase()}</span>
                        ${isBase ? '<span class="text-xs px-2 py-0.5 rounded-full" style="background:rgba(0,200,255,0.15);color:#00c8ff;border:1px solid rgba(0,200,255,0.3);">Base</span>' : ''}
                    </div>
                    <div class="text-gray-500 text-xs font-mono">rgb(${r}, ${g}, ${b})</div>
                    <div class="text-gray-600 text-xs font-mono">hsl(${ch}, ${cs}%, ${cl}%)</div>
                </div>
                <button class="copy-btn opacity-0 group-hover:opacity-100 transition-opacity px-3 py-1.5 rounded-lg text-xs font-semibold"
                        style="background:rgba(0,200,255,0.12);color:#00c8ff;border:1px solid rgba(0,200,255,0.2);">
                    <i class="fas fa-copy"></i> ${i18n.copy_btn}
                </button>
            </div>
        `;
    });

    // Wide bar
    barWrap.innerHTML = '';
    colors.forEach(c => {
        const div = document.createElement('div');
        div.style.cssText = `flex:1;background:${c};transition:flex 0.3s ease;cursor:pointer;`;
        div.title = c;
        div.addEventListener('mouseenter', () => div.style.flex = '2');
        div.addEventListener('mouseleave', () => div.style.flex = '1');
        div.addEventListener('click', () => copySingle(c));
        barWrap.appendChild(div);
    });
}

function showCopyFeedback(msg) {
    copyMsg.textContent = msg || i18n.copied;
    copyFeedback.classList.remove('hidden');
    setTimeout(() => copyFeedback.classList.add('hidden'), 2500);
}

function copySingle(hex) {
    navigator.clipboard.writeText(hex.toUpperCase()).then(() => showCopyFeedback(i18n.copied_prefix + hex.toUpperCase()));
}

// Export CSS variables
document.getElementById('export-css').addEventListener('click', () => {
    const text = currentPalette.map((c,i) => `  --color-${i+1}: ${c.toUpperCase()};`).join('\n');
    navigator.clipboard.writeText(`:root {\n${text}\n}`).then(() => showCopyFeedback(i18n.css_copied));
});

// Export Tailwind config
document.getElementById('export-tailwind').addEventListener('click', () => {
    const colors = currentPalette.map((c,i) => `    '${i+1}': '${c.toUpperCase()}'`).join(',\n');
    navigator.clipboard.writeText(`colors: {\n  palette: {\n${colors}\n  }\n}`).then(() => showCopyFeedback(i18n.tw_copied));
});

// Sync picker <-> hex input
picker.addEventListener('input', () => {
    const h = picker.value;
    hexInput.value = h.toUpperCase();
    document.getElementById('hex-error').classList.add('hidden');
    updateColorInfo(h);
    renderPalette(h);
});

hexInput.addEventListener('input', () => {
    let h = hexInput.value.trim();
    if (!h.startsWith('#')) h = '#' + h;
    if (isValidHex(h)) {
        document.getElementById('hex-error').classList.add('hidden');
        picker.value = h;
        updateColorInfo(h);
        renderPalette(h);
    } else {
        document.getElementById('hex-error').classList.remove('hidden');
    }
});

modeSelect.addEventListener('change', () => renderPalette(picker.value));
genBtn.addEventListener('click', () => renderPalette(picker.value));

// Init
updateColorInfo('#00c8ff');
renderPalette('#00c8ff');
</script>
