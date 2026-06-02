<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$_d = fn($fr, $en) => (($_SESSION['nc_lang'] ?? 'fr') === 'en') ? $en : $fr;
$page_title = $_d('Générateur de Mot de Passe — Netcrafter', 'Password Generator — Netcrafter');
require_once __DIR__ . '/../includes/header.php';
?>

<section class="relative pt-32 pb-10 overflow-hidden">
    <div class="blob bg-nc-green" style="width:400px;height:400px;top:-150px;left:-150px;opacity:0.12"></div>
    <div class="blob bg-nc-cyan"  style="width:350px;height:350px;bottom:-80px;right:-100px;animation-delay:2s;opacity:0.1"></div>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center">
        <div class="badge mb-5 mx-auto" data-aos="fade-down"><i class="fas fa-key"></i> <?= $_d('Outil Sécurité','Security Tool') ?></div>
        <h1 class="font-heading font-black text-5xl md:text-6xl text-white mb-4" data-aos="fade-up">
            <?= $_d('Générateur &', 'Password') ?> <span class="gradient-text-green"><?= $_d('Analyseur', 'Generator &') ?></span><br>
            <span class="text-4xl md:text-5xl"><?= $_d('de Mots de Passe', 'Strength Analyser') ?></span>
        </h1>
        <p class="text-gray-400 text-lg max-w-xl mx-auto" data-aos="fade-up" data-aos-delay="80">
            <?= $_d('Générez des mots de passe forts et analysez la robustesse de vos mots de passe existants.',
                     'Generate strong passwords and analyse the strength of your existing ones.') ?>
        </p>
    </div>
</section>

<section class="pb-20">
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

    <!-- Strength analyser -->
    <div class="glass rounded-3xl p-7" data-aos="fade-up">
        <h2 class="font-heading font-bold text-xl text-white mb-5 flex items-center gap-2">
            <i class="fas fa-shield-alt text-nc-cyan"></i> <?= $_d('Analyser un mot de passe','Analyse a Password') ?>
        </h2>
        <div class="relative mb-4">
            <input type="text" id="pwd-input" placeholder="<?= $_d('Entrez votre mot de passe…','Enter your password…') ?>"
                   autocomplete="off" spellcheck="false"
                   class="w-full px-5 py-4 rounded-2xl text-white placeholder-gray-500 text-base outline-none transition-all font-mono"
                   style="background:rgba(255,255,255,0.05);border:1px solid rgba(0,200,255,0.2);">
            <button id="pwd-toggle" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 hover:text-white transition-colors" title="<?= $_d('Afficher/Masquer','Show/Hide') ?>">
                <i class="fas fa-eye" id="pwd-eye"></i>
            </button>
        </div>
        <!-- Strength bar -->
        <div class="mb-4">
            <div class="flex justify-between items-center mb-2">
                <span class="text-sm text-gray-400"><?= $_d('Force','Strength') ?></span>
                <span id="strength-label" class="text-sm font-bold">—</span>
            </div>
            <div class="h-3 rounded-full overflow-hidden" style="background:rgba(255,255,255,0.05)">
                <div id="strength-bar" class="h-full rounded-full transition-all duration-500" style="width:0%;background:#ef4444"></div>
            </div>
        </div>
        <!-- Criteria grid -->
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2" id="criteria-grid"></div>
        <!-- Crack time -->
        <div id="crack-time" class="mt-4 text-center p-3 rounded-2xl hidden" style="background:rgba(0,200,255,0.05);border:1px solid rgba(0,200,255,0.1)">
            <span class="text-gray-500 text-xs"><?= $_d('Temps estimé pour craquer :','Estimated crack time:') ?></span>
            <span id="crack-val" class="text-nc-cyan font-bold text-sm ml-2"></span>
        </div>
    </div>

    <!-- Generator -->
    <div class="glass rounded-3xl p-7" data-aos="fade-up" data-aos-delay="80">
        <h2 class="font-heading font-bold text-xl text-white mb-5 flex items-center gap-2">
            <i class="fas fa-magic text-nc-violet"></i> <?= $_d('Générer un mot de passe','Generate a Password') ?>
        </h2>

        <!-- Length slider -->
        <div class="mb-5">
            <div class="flex justify-between mb-2">
                <span class="text-sm text-gray-400"><?= $_d('Longueur','Length') ?></span>
                <span id="len-val" class="text-nc-cyan font-bold text-sm">16</span>
            </div>
            <input type="range" id="pwd-len" min="8" max="64" value="16"
                   class="w-full accent-cyan-400" style="accent-color:#00c8ff">
        </div>

        <!-- Options -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
            <?php foreach ([
                ['opt-upper',  $_d('Majuscules','Uppercase'), 'A-Z', true],
                ['opt-lower',  $_d('Minuscules','Lowercase'), 'a-z', true],
                ['opt-digits', $_d('Chiffres','Digits'),      '0-9', true],
                ['opt-syms',   $_d('Symboles','Symbols'),     '!@#…',true],
            ] as [$id, $lbl, $sample, $checked]): ?>
            <label class="flex items-center gap-2 p-3 rounded-xl cursor-pointer transition-all"
                   style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08)">
                <input type="checkbox" id="<?= $id ?>" <?= $checked?'checked':'' ?>
                       class="w-4 h-4 rounded accent-cyan" style="accent-color:#00c8ff">
                <div>
                    <div class="text-xs font-semibold text-white"><?= $lbl ?></div>
                    <div class="text-xs font-mono text-gray-600"><?= $sample ?></div>
                </div>
            </label>
            <?php endforeach; ?>
        </div>

        <!-- Generated password -->
        <div class="relative mb-4">
            <div id="generated-pwd" class="w-full px-5 py-4 rounded-2xl text-nc-cyan font-mono text-base break-all select-all cursor-text leading-relaxed"
                 style="background:rgba(0,200,255,0.05);border:1px solid rgba(0,200,255,0.2);min-height:58px">
                —
            </div>
            <button id="copy-gen-btn" onclick="copyGenerated()"
                    class="absolute right-3 top-1/2 -translate-y-1/2 btn-ghost text-xs py-1.5 px-3">
                <i class="fas fa-copy mr-1"></i><?= $_d('Copier','Copy') ?>
            </button>
        </div>

        <div class="flex gap-3">
            <button onclick="generatePassword()" class="btn-primary flex-1 justify-center">
                <i class="fas fa-sync-alt"></i> <?= $_d('Générer','Generate') ?>
            </button>
            <button onclick="generateMultiple()" class="btn-outline text-sm px-4">
                <i class="fas fa-list"></i> ×10
            </button>
        </div>

        <!-- Batch output -->
        <div id="batch-output" class="hidden mt-4 space-y-2"></div>
    </div>

</div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<style>
#pwd-input:focus { border-color:rgba(0,200,255,0.5)!important; box-shadow:0 0 0 3px rgba(0,200,255,0.08); }
.criterion { display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:10px;font-size:0.8rem;
             background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.05);transition:all .3s; }
.criterion.pass { background:rgba(16,185,129,0.08);border-color:rgba(16,185,129,0.2);color:#10b981; }
.criterion.fail { color:#4b5563; }
</style>

<script>
const i18n = <?= json_encode([
    'too_short'  => $_d('Trop court','Too short'),
    'weak'       => $_d('Faible','Weak'),
    'fair'       => $_d('Moyen','Fair'),
    'good'       => $_d('Bien','Good'),
    'strong'     => $_d('Fort','Strong'),
    'very_strong'=> $_d('Très fort','Very Strong'),
    'length8'    => $_d('8+ caractères','8+ characters'),
    'length12'   => $_d('12+ caractères','12+ characters'),
    'uppercase'  => $_d('Majuscules','Uppercase'),
    'lowercase'  => $_d('Minuscules','Lowercase'),
    'digits'     => $_d('Chiffres','Digits'),
    'symbols'    => $_d('Symboles','Symbols'),
    'no_repeat'  => $_d('Pas de répétition','No repeats'),
    'copied'     => $_d('Copié !','Copied!'),
    'crack_inst' => $_d('Instantané','Instant'),
    'crack_sec'  => $_d('secondes','seconds'),
    'crack_min'  => $_d('minutes','minutes'),
    'crack_hr'   => $_d('heures','hours'),
    'crack_day'  => $_d('jours','days'),
    'crack_yr'   => $_d('années','years'),
    'crack_cent' => $_d('siècles','centuries'),
], JSON_UNESCAPED_UNICODE) ?>;

const criteria = [
    {id:'c-len8',  label:i18n.length8,   test: p => p.length >= 8},
    {id:'c-len12', label:i18n.length12,  test: p => p.length >= 12},
    {id:'c-upper', label:i18n.uppercase, test: p => /[A-Z]/.test(p)},
    {id:'c-lower', label:i18n.lowercase, test: p => /[a-z]/.test(p)},
    {id:'c-digit', label:i18n.digits,    test: p => /[0-9]/.test(p)},
    {id:'c-sym',   label:i18n.symbols,   test: p => /[^A-Za-z0-9]/.test(p)},
];

// Build criteria grid
const grid = document.getElementById('criteria-grid');
criteria.forEach(c => {
    const el = document.createElement('div');
    el.className = 'criterion fail';
    el.id = c.id;
    el.innerHTML = `<i class="fas fa-times text-xs w-3"></i><span>${c.label}</span>`;
    grid.appendChild(el);
});

const input = document.getElementById('pwd-input');
input.addEventListener('input', analysePassword);

function analysePassword() {
    const p = input.value;
    if (!p) {
        document.getElementById('strength-bar').style.width = '0%';
        document.getElementById('strength-label').textContent = '—';
        document.getElementById('crack-time').classList.add('hidden');
        criteria.forEach(c => {
            const el = document.getElementById(c.id);
            el.className = 'criterion fail';
            el.querySelector('i').className = 'fas fa-times text-xs w-3';
        });
        return;
    }
    let score = 0;
    let passing = 0;
    criteria.forEach(c => {
        const ok = c.test(p);
        if (ok) { score += c.id === 'c-sym' ? 25 : c.id === 'c-len12' ? 20 : 15; passing++; }
        const el = document.getElementById(c.id);
        el.className = 'criterion ' + (ok ? 'pass' : 'fail');
        el.querySelector('i').className = 'fas fa-' + (ok ? 'check' : 'times') + ' text-xs w-3';
    });
    score = Math.min(100, score + Math.min(20, p.length - 8) * 2);
    const bar = document.getElementById('strength-bar');
    const lbl = document.getElementById('strength-label');
    let color, label;
    if      (score < 25) { color='#ef4444'; label=i18n.weak; }
    else if (score < 45) { color='#f97316'; label=i18n.fair; }
    else if (score < 65) { color='#f59e0b'; label=i18n.good; }
    else if (score < 85) { color='#10b981'; label=i18n.strong; }
    else                 { color='#00c8ff'; label=i18n.very_strong; }
    bar.style.width    = score + '%';
    bar.style.background = color;
    lbl.textContent    = label;
    lbl.style.color    = color;

    // Crack time estimation
    const charSet = (/[a-z]/.test(p)?26:0)+(/[A-Z]/.test(p)?26:0)+(/[0-9]/.test(p)?10:0)+(/[^A-Za-z0-9]/.test(p)?32:0);
    const combinations = Math.pow(Math.max(charSet,1), p.length);
    const guessPerSec = 1e10;
    const seconds = combinations / guessPerSec / 2;
    let crackText;
    if      (seconds < 1)        crackText = i18n.crack_inst;
    else if (seconds < 60)       crackText = Math.round(seconds)+' '+i18n.crack_sec;
    else if (seconds < 3600)     crackText = Math.round(seconds/60)+' '+i18n.crack_min;
    else if (seconds < 86400)    crackText = Math.round(seconds/3600)+' '+i18n.crack_hr;
    else if (seconds < 31536000) crackText = Math.round(seconds/86400)+' '+i18n.crack_day;
    else if (seconds < 3153600000) crackText = Math.round(seconds/31536000)+' '+i18n.crack_yr;
    else                         crackText = Math.round(seconds/315360000)+' '+i18n.crack_cent;
    document.getElementById('crack-val').textContent = crackText;
    document.getElementById('crack-time').classList.remove('hidden');
}

// Toggle visibility
document.getElementById('pwd-toggle').addEventListener('click', function() {
    const t = input.type === 'password' ? 'text' : 'password';
    input.type = t;
    document.getElementById('pwd-eye').className = 'fas fa-' + (t === 'password' ? 'eye' : 'eye-slash');
});
input.type = 'text';

// Generator
const chars = {
    upper:  'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
    lower:  'abcdefghijklmnopqrstuvwxyz',
    digits: '0123456789',
    syms:   '!@#$%^&*()_+-=[]{}|;:,.<>?'
};

document.getElementById('pwd-len').addEventListener('input', function() {
    document.getElementById('len-val').textContent = this.value;
});

function buildCharset() {
    let cs = '';
    if (document.getElementById('opt-upper').checked)  cs += chars.upper;
    if (document.getElementById('opt-lower').checked)  cs += chars.lower;
    if (document.getElementById('opt-digits').checked) cs += chars.digits;
    if (document.getElementById('opt-syms').checked)   cs += chars.syms;
    return cs || chars.lower;
}

function generatePassword() {
    const len = parseInt(document.getElementById('pwd-len').value);
    const cs  = buildCharset();
    let pwd   = '';
    const arr = new Uint32Array(len);
    crypto.getRandomValues(arr);
    for (let i = 0; i < len; i++) pwd += cs[arr[i] % cs.length];
    document.getElementById('generated-pwd').textContent = pwd;
    document.getElementById('batch-output').classList.add('hidden');
}

function generateMultiple() {
    const len = parseInt(document.getElementById('pwd-len').value);
    const cs  = buildCharset();
    const box = document.getElementById('batch-output');
    box.innerHTML = '';
    for (let j = 0; j < 10; j++) {
        let pwd = '';
        const arr = new Uint32Array(len);
        crypto.getRandomValues(arr);
        for (let i = 0; i < len; i++) pwd += cs[arr[i] % cs.length];
        box.innerHTML += `<div class="flex items-center gap-3 p-3 rounded-xl font-mono text-sm" style="background:rgba(0,200,255,0.04);border:1px solid rgba(0,200,255,0.1)">
            <span class="flex-1 text-nc-cyan break-all">${pwd}</span>
            <button onclick="navigator.clipboard.writeText('${pwd}')" class="btn-ghost text-xs py-1 px-2 flex-shrink-0">
                <i class="fas fa-copy"></i>
            </button>
        </div>`;
    }
    box.classList.remove('hidden');
}

function copyGenerated() {
    const txt = document.getElementById('generated-pwd').textContent;
    if (txt === '—') return;
    navigator.clipboard.writeText(txt).then(() => {
        const btn = document.getElementById('copy-gen-btn');
        btn.innerHTML = `<i class="fas fa-check mr-1 text-nc-green"></i>${i18n.copied}`;
        setTimeout(() => { btn.innerHTML = `<i class="fas fa-copy mr-1"></i><?= $_d('Copier','Copy') ?>`; }, 2000);
    });
}

generatePassword();
</script>
