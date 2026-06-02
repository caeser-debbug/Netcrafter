<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$_d = fn($fr, $en) => (($_SESSION['nc_lang'] ?? 'fr') === 'en') ? $en : $fr;
$page_title = $_d('Base64 & URL Encodeur — Netcrafter', 'Base64 & URL Encoder — Netcrafter');
require_once __DIR__ . '/../includes/header.php';
?>

<section class="relative pt-32 pb-10 overflow-hidden">
    <div class="blob bg-nc-violet" style="width:400px;height:400px;top:-150px;left:-150px;opacity:0.12"></div>
    <div class="blob bg-nc-cyan"   style="width:350px;height:350px;bottom:-80px;right:-100px;animation-delay:2s;opacity:0.1"></div>
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center">
        <div class="badge mb-5 mx-auto" data-aos="fade-down"><i class="fas fa-code"></i> <?= $_d('Outils Développeur','Developer Tools') ?></div>
        <h1 class="font-heading font-black text-5xl md:text-6xl text-white mb-4" data-aos="fade-up">
            <span class="gradient-text-violet">Base64</span> &amp; <span class="gradient-text">URL</span><br>
            <span class="text-4xl"><?= $_d('Encodeur / Décodeur', 'Encoder / Decoder') ?></span>
        </h1>
        <p class="text-gray-400 text-lg max-w-xl mx-auto" data-aos="fade-up" data-aos-delay="80">
            <?= $_d('Encodez et décodez en Base64, URL, HTML entities et JSON en un clic.',
                     'Encode and decode Base64, URL, HTML entities and JSON in one click.') ?>
        </p>
    </div>
</section>

<section class="pb-20">
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

    <!-- Mode tabs -->
    <div class="flex flex-wrap gap-2 mb-6" data-aos="fade-up">
        <?php foreach ([
            ['base64',   'fa-lock', 'Base64'],
            ['url',      'fa-link', 'URL'],
            ['html',     'fa-code', 'HTML Entities'],
            ['json',     'fa-braces', 'JSON'],
            ['hex',      'fa-hashtag', 'HEX'],
        ] as $i => [$mode, $icon, $lbl]): ?>
        <button class="enc-tab flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold transition-all <?= $i===0?'tab-on':'' ?>"
                data-mode="<?= $mode ?>">
            <i class="fas <?= $icon ?> text-xs"></i><?= $lbl ?>
        </button>
        <?php endforeach; ?>
    </div>

    <!-- Editor panels -->
    <div class="grid md:grid-cols-2 gap-4" data-aos="fade-up" data-aos-delay="80">

        <!-- Input -->
        <div class="glass rounded-3xl p-6">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-heading font-semibold text-white text-base" id="input-label"><?= $_d('Texte original','Original text') ?></h3>
                <div class="flex gap-2">
                    <button onclick="clearAll()" class="btn-ghost text-xs py-1.5 px-3"><i class="fas fa-trash-alt mr-1"></i><?= $_d('Vider','Clear') ?></button>
                    <label class="btn-ghost text-xs py-1.5 px-3 cursor-pointer">
                        <i class="fas fa-upload mr-1"></i><?= $_d('Fichier','File') ?>
                        <input type="file" id="file-input" class="hidden" accept="*">
                    </label>
                </div>
            </div>
            <textarea id="enc-input" rows="12" placeholder="<?= $_d('Entrez votre texte ici…','Enter your text here…') ?>"
                      class="w-full resize-none rounded-xl p-4 text-white text-sm font-mono outline-none transition-all leading-relaxed"
                      style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);"></textarea>
            <div class="flex items-center justify-between mt-2 text-xs text-gray-600">
                <span id="input-chars">0 <?= $_d('car.','chars') ?></span>
                <span id="input-size">0 B</span>
            </div>
        </div>

        <!-- Output -->
        <div class="glass rounded-3xl p-6">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-heading font-semibold text-white text-base" id="output-label"><?= $_d('Résultat encodé','Encoded result') ?></h3>
                <div class="flex gap-2">
                    <button onclick="swapPanels()" class="btn-ghost text-xs py-1.5 px-3" title="<?= $_d('Inverser','Swap') ?>">
                        <i class="fas fa-exchange-alt"></i>
                    </button>
                    <button onclick="copyOutput()" class="btn-ghost text-xs py-1.5 px-3" id="copy-output-btn">
                        <i class="fas fa-copy mr-1"></i><?= $_d('Copier','Copy') ?>
                    </button>
                </div>
            </div>
            <textarea id="enc-output" rows="12" readonly placeholder="—"
                      class="w-full resize-none rounded-xl p-4 text-nc-cyan text-sm font-mono outline-none leading-relaxed cursor-text select-all"
                      style="background:rgba(0,200,255,0.04);border:1px solid rgba(0,200,255,0.12);"></textarea>
            <div class="flex items-center justify-between mt-2 text-xs text-gray-600">
                <span id="output-chars">0 <?= $_d('car.','chars') ?></span>
                <span id="output-ratio"></span>
            </div>
        </div>
    </div>

    <!-- Action buttons -->
    <div class="flex flex-wrap gap-3 mt-4 justify-center" data-aos="fade-up" data-aos-delay="120">
        <button onclick="doEncode()" class="btn-primary px-8">
            <i class="fas fa-lock"></i> <?= $_d('Encoder','Encode') ?>
        </button>
        <button onclick="doDecode()" class="btn-outline px-8">
            <i class="fas fa-unlock"></i> <?= $_d('Décoder','Decode') ?>
        </button>
    </div>

    <!-- Info card per mode -->
    <div id="mode-info" class="mt-6 glass rounded-2xl p-5 text-sm text-gray-400 leading-relaxed" data-aos="fade-up" data-aos-delay="150"></div>

</div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<style>
#enc-input:focus, #enc-output:focus { border-color:rgba(0,200,255,0.4)!important; outline:none; }
.enc-tab { background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);color:#64748b; }
.enc-tab:hover { color:#fff;background:rgba(124,58,237,0.1); }
.enc-tab.tab-on { background:rgba(124,58,237,0.15);border-color:rgba(124,58,237,0.4);color:#a78bfa; }
</style>

<script>
const i18n = <?= json_encode([
    'copied'      => $_d('Copié !','Copied!'),
    'encode_err'  => $_d('Erreur d\'encodage.','Encoding error.'),
    'decode_err'  => $_d('Erreur de décodage.','Decoding error.'),
    'copy'        => $_d('Copier','Copy'),
    'chars'       => $_d('car.','chars'),
    'info_base64' => $_d('Base64 encode les données binaires en texte ASCII. Utilisé pour les emails (MIME), les data URI, les jetons d\'authentification.','Base64 encodes binary data as ASCII text. Used for MIME emails, data URIs and authentication tokens.'),
    'info_url'    => $_d('L\'encodage URL remplace les caractères spéciaux par %XX. Indispensable pour les paramètres de requête HTTP.','URL encoding replaces special characters with %XX. Essential for HTTP query parameters.'),
    'info_html'   => $_d('Les entités HTML encodent les caractères spéciaux (&lt; &gt; &amp; etc.) pour éviter les injections XSS dans les pages web.','HTML entities encode special characters (&lt; &gt; &amp; etc.) to prevent XSS injections in web pages.'),
    'info_json'   => $_d('Formate et valide le JSON. Pratique pour debugger des API ou lisibiliser des réponses minifiées.','Formats and validates JSON. Handy for debugging APIs or making minified responses readable.'),
    'info_hex'    => $_d('Convertit du texte en hexadécimal (base 16). Utilisé en cryptographie, débogage réseau et encodage couleur.','Converts text to hexadecimal (base 16). Used in cryptography, network debugging and colour encoding.'),
], JSON_UNESCAPED_UNICODE) ?>;

let currentMode = 'base64';

document.querySelectorAll('.enc-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.enc-tab').forEach(t => t.classList.remove('tab-on'));
        this.classList.add('tab-on');
        currentMode = this.dataset.mode;
        updateInfo();
        doEncode();
    });
});

function updateInfo() {
    document.getElementById('mode-info').textContent = i18n['info_' + currentMode] || '';
}

const inputEl  = document.getElementById('enc-input');
const outputEl = document.getElementById('enc-output');

inputEl.addEventListener('input', function() {
    updateCounters();
    doEncode();
});

function updateCounters() {
    const v = inputEl.value;
    document.getElementById('input-chars').textContent = v.length + ' ' + i18n.chars;
    document.getElementById('input-size').textContent  = formatBytes(new Blob([v]).size);
}

function formatBytes(b) {
    if (b < 1024) return b + ' B';
    if (b < 1048576) return (b/1024).toFixed(1) + ' KB';
    return (b/1048576).toFixed(1) + ' MB';
}

function doEncode() {
    const v = inputEl.value;
    if (!v) { outputEl.value = ''; updateOutputCounters(); return; }
    try {
        let result;
        switch(currentMode) {
            case 'base64': result = btoa(unescape(encodeURIComponent(v))); break;
            case 'url':    result = encodeURIComponent(v); break;
            case 'html':   result = v.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); break;
            case 'json':
                try { result = JSON.stringify(JSON.parse(v), null, 2); } catch { result = JSON.stringify(v); }
                break;
            case 'hex':    result = Array.from(new TextEncoder().encode(v)).map(b => b.toString(16).padStart(2,'0')).join(' '); break;
            default:       result = v;
        }
        outputEl.value = result;
    } catch(e) { outputEl.value = i18n.encode_err + ' ' + e.message; }
    updateOutputCounters();
}

function doDecode() {
    const v = inputEl.value;
    if (!v) return;
    try {
        let result;
        switch(currentMode) {
            case 'base64': result = decodeURIComponent(escape(atob(v))); break;
            case 'url':    result = decodeURIComponent(v); break;
            case 'html':   result = v.replace(/&amp;/g,'&').replace(/&lt;/g,'<').replace(/&gt;/g,'>').replace(/&quot;/g,'"').replace(/&#39;/g,"'"); break;
            case 'json':
                try { result = JSON.stringify(JSON.parse(v), null, 2); } catch { result = v; }
                break;
            case 'hex':    result = new TextDecoder().decode(new Uint8Array(v.trim().split(/\s+/).map(h => parseInt(h,16)))); break;
            default:       result = v;
        }
        outputEl.value = result;
    } catch(e) { outputEl.value = i18n.decode_err + ' ' + e.message; }
    updateOutputCounters();
}

function updateOutputCounters() {
    const v = outputEl.value;
    document.getElementById('output-chars').textContent = v.length + ' ' + i18n.chars;
    const inLen  = inputEl.value.length;
    const outLen = v.length;
    if (inLen > 0 && outLen > 0) {
        const ratio = ((outLen / inLen) * 100).toFixed(0);
        document.getElementById('output-ratio').textContent = ratio + '%';
    } else {
        document.getElementById('output-ratio').textContent = '';
    }
}

function copyOutput() {
    navigator.clipboard.writeText(outputEl.value).then(() => {
        const btn = document.getElementById('copy-output-btn');
        btn.innerHTML = `<i class="fas fa-check mr-1 text-nc-green"></i>${i18n.copied}`;
        setTimeout(() => { btn.innerHTML = `<i class="fas fa-copy mr-1"></i>${i18n.copy}`; }, 2000);
    });
}

function swapPanels() {
    const tmp = inputEl.value;
    inputEl.value = outputEl.value;
    outputEl.value = tmp;
    updateCounters();
    updateOutputCounters();
}

function clearAll() {
    inputEl.value = '';
    outputEl.value = '';
    updateCounters();
    updateOutputCounters();
}

// File input
document.getElementById('file-input').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    if (file.size > 1048576) { alert('Max 1MB'); return; }
    const reader = new FileReader();
    reader.onload = ev => { inputEl.value = ev.target.result; updateCounters(); doEncode(); };
    reader.readAsText(file);
});

updateInfo();
</script>
