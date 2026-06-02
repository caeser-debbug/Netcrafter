<?php
/**
 * Netcrafter – Cahier des charges client
 * Accessible via lien partagé : /cahier/index.php?token=XXXX
 */
ini_set('display_errors', 0);

$host = $_SERVER['HTTP_HOST'] ?? '';
define('BASE', (strpos($host,'localhost')!==false || strpos($host,'127.0.0.1')!==false) ? '/netcrafter' : '');

// ── DB ──────────────────────────────────────────────────────────────────────
$_loc = PHP_OS_FAMILY === 'Windows'
    || strpos($host,'localhost')!==false
    || strpos($host,'127.0.0.1')!==false;
try {
    $pdo = new PDO(
        'mysql:host=localhost;charset=utf8mb4;dbname=' . ($_loc ? 'netcrafter' : 'u264396140_netcrafternige'),
        $_loc ? 'root'                       : 'u264396140_netcrefternige',
        $_loc ? ''                           : 'Hondaand@1',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $pdo->exec("CREATE TABLE IF NOT EXISTS `cahier_charges` (
        `id`            INT AUTO_INCREMENT PRIMARY KEY,
        `token`         VARCHAR(64)  NOT NULL UNIQUE,
        `label`         VARCHAR(255) DEFAULT NULL,
        `client_name`   VARCHAR(255) DEFAULT NULL,
        `client_email`  VARCHAR(255) DEFAULT NULL,
        `client_phone`  VARCHAR(100) DEFAULT NULL,
        `client_company`VARCHAR(255) DEFAULT NULL,
        `project_name`  VARCHAR(255) DEFAULT NULL,
        `project_type`  VARCHAR(100) DEFAULT NULL,
        `description`   TEXT,
        `objectives`    TEXT,
        `target_audience` TEXT,
        `features`      TEXT COMMENT 'JSON',
        `custom_features` TEXT,
        `design_style`  VARCHAR(100) DEFAULT NULL,
        `color_prefs`   TEXT,
        `has_brand`     TINYINT(1)   DEFAULT 0,
        `brand_details` TEXT,
        `ref_urls`      TEXT,
        `budget`        VARCHAR(100) DEFAULT NULL,
        `deadline`      VARCHAR(100) DEFAULT NULL,
        `cms_pref`      VARCHAR(100) DEFAULT NULL,
        `hosting_pref`  VARCHAR(100) DEFAULT NULL,
        `notes`         TEXT,
        `status`        ENUM('pending','in_review','validated','archived') DEFAULT 'pending',
        `admin_notes`   TEXT,
        `submitted_at`  DATETIME DEFAULT NULL,
        `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `cahier_files` (
        `id`            INT AUTO_INCREMENT PRIMARY KEY,
        `cahier_id`     INT NOT NULL,
        `original_name` VARCHAR(500) NOT NULL,
        `stored_name`   VARCHAR(500) NOT NULL,
        `file_type`     VARCHAR(100) DEFAULT NULL,
        `file_size`     INT          DEFAULT 0,
        `uploaded_at`   DATETIME     DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {
    die('<div style="font-family:sans-serif;padding:3rem;text-align:center;color:#ef4444">Erreur de connexion.</div>');
}

// ── Token ────────────────────────────────────────────────────────────────────
$token = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['token'] ?? '');
$brief = null;
if ($token) {
    $st = $pdo->prepare("SELECT * FROM cahier_charges WHERE token = ?");
    $st->execute([$token]);
    $brief = $st->fetch();
}
$invalid     = !$token || !$brief;
$already_sub = $brief && $brief['submitted_at'] !== null;
$submit_ok   = false;
$submit_err  = '';
$initial_step = 1;

// ── Process POST ─────────────────────────────────────────────────────────────
if (!$invalid && !$already_sub && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_brief'])) {
    $fields = ['client_name','client_email','client_phone','client_company',
               'project_name','project_type','description','objectives','target_audience',
               'custom_features','design_style','color_prefs','brand_details','ref_urls',
               'budget','deadline','cms_pref','hosting_pref','notes'];
    $data = [];
    foreach ($fields as $f) $data[$f] = htmlspecialchars(trim($_POST[$f] ?? ''), ENT_QUOTES, 'UTF-8');
    $data['has_brand'] = isset($_POST['has_brand']) ? 1 : 0;
    $data['features']  = json_encode(array_map('strip_tags', (array)($_POST['features'] ?? [])));

    if (empty($data['client_name']) || empty($data['client_email']) || empty($data['project_name'])) {
        $submit_err = 'Veuillez remplir les champs obligatoires : nom, e-mail et nom du projet.';
        $initial_step = 4;
    } else {
        $sets = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($data)));
        $vals = array_values($data);
        $vals[] = date('Y-m-d H:i:s');
        $vals[] = $brief['id'];
        $pdo->prepare("UPDATE cahier_charges SET $sets, submitted_at = ? WHERE id = ?")->execute($vals);

        // File uploads
        $updir = dirname(__DIR__) . "/uploads/cahier/{$brief['id']}/";
        if (!is_dir($updir)) @mkdir($updir, 0755, true);
        $allowed = ['pdf','doc','docx','png','jpg','jpeg','gif','svg','ai','psd','zip','xls','xlsx','ppt','pptx'];
        if (!empty($_FILES['docs']['name'][0])) {
            foreach ($_FILES['docs']['name'] as $i => $orig) {
                if ($_FILES['docs']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed) || $_FILES['docs']['size'][$i] > 20*1024*1024) continue;
                $stored = uniqid('f_', true) . '.' . $ext;
                if (move_uploaded_file($_FILES['docs']['tmp_name'][$i], $updir . $stored)) {
                    $pdo->prepare("INSERT INTO cahier_files (cahier_id,original_name,stored_name,file_type,file_size) VALUES (?,?,?,?,?)")
                        ->execute([$brief['id'], $orig, $stored, $_FILES['docs']['type'][$i], $_FILES['docs']['size'][$i]]);
                }
            }
        }
        $st->execute([$token]);
        $brief   = $st->fetch();
        $submit_ok = true;
    }
}
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $invalid ? 'Lien invalide' : 'Cahier des charges – ' . htmlspecialchars($brief['label'] ?: 'Netcrafter') ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script>tailwind.config={theme:{extend:{colors:{'nc-cyan':'#00c8ff','nc-blue':'#0066cc'}}}}</script>
<style>
*{box-sizing:border-box}
body{background:#060d1e;color:#e5e7eb;font-family:system-ui,sans-serif;min-height:100vh}
.glass{background:rgba(255,255,255,.04);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,.08)}
.step-panel{display:none}.step-panel.active{display:block;animation:fadeUp .3s ease}
@keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
.inp{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.12);border-radius:10px;
     color:#e5e7eb;padding:.65rem 1rem;width:100%;font-size:.9rem;transition:border-color .2s}
.inp:focus{outline:none;border-color:rgba(0,200,255,.5);background:rgba(0,200,255,.03)}
.inp::placeholder{color:#4b5563}
textarea.inp{resize:vertical;min-height:88px}
select.inp{appearance:auto;cursor:pointer}
.lbl{display:block;font-size:.78rem;font-weight:600;color:#9ca3af;margin-bottom:.4rem;
     letter-spacing:.04em;text-transform:uppercase}
.req{color:#ef4444;margin-left:2px}
.feat-btn{display:flex;align-items:center;gap:.55rem;background:rgba(255,255,255,.04);
          border:1px solid rgba(255,255,255,.1);border-radius:10px;padding:.6rem .85rem;
          cursor:pointer;transition:all .18s;user-select:none;font-size:.83rem;color:#9ca3af}
.feat-btn:hover{border-color:rgba(0,200,255,.35);color:#e5e7eb}
.feat-btn.on{background:rgba(0,200,255,.1);border-color:rgba(0,200,255,.4);color:#00c8ff}
.style-card{border:2px solid rgba(255,255,255,.1);border-radius:12px;padding:1rem .75rem;
            cursor:pointer;transition:all .18s;text-align:center}
.style-card:hover{border-color:rgba(0,200,255,.4)}
.style-card.on{border-color:#00c8ff;background:rgba(0,200,255,.08)}
.drop-zone{border:2px dashed rgba(0,200,255,.3);border-radius:14px;padding:2rem;text-align:center;
           cursor:pointer;transition:all .2s;background:rgba(0,200,255,.02)}
.drop-zone.over{border-color:#00c8ff;background:rgba(0,200,255,.06)}
.sdot{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;
      font-size:.75rem;font-weight:700;border:2px solid rgba(255,255,255,.15);color:#6b7280;
      background:rgba(255,255,255,.04);transition:all .3s;flex-shrink:0}
.sdot.active{border-color:#00c8ff;color:#00c8ff;background:rgba(0,200,255,.12)}
.sdot.done{border-color:#10b981;color:#fff;background:#10b981}
.sline{height:2px;flex:1;background:rgba(255,255,255,.08);transition:background .3s}
.sline.done{background:#10b981}
.gtext{background:linear-gradient(90deg,#00c8ff,#0066cc);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.btn-pri{background:linear-gradient(135deg,#00c8ff,#0066cc);color:#fff;font-weight:600;font-size:.875rem;
         border-radius:12px;padding:.7rem 1.5rem;border:none;cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;gap:.5rem}
.btn-pri:hover{transform:scale(1.03);opacity:.95}
.btn-sec{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.12);color:#9ca3af;
         font-weight:600;font-size:.875rem;border-radius:12px;padding:.7rem 1.4rem;cursor:pointer;
         transition:all .2s;display:inline-flex;align-items:center;gap:.5rem}
.btn-sec:hover{color:#e5e7eb;border-color:rgba(255,255,255,.25)}
</style>
</head>
<body>

<!-- Header -->
<header style="background:rgba(6,13,30,.95);backdrop-filter:blur(20px);border-bottom:1px solid rgba(0,200,255,.1)">
    <div class="max-w-5xl mx-auto px-4 py-4 flex items-center justify-between">
        <a href="<?= BASE ?>/index.php" class="flex items-center gap-2.5">
            <img src="<?= BASE ?>/image/logo-n.png" alt="Netcrafter" class="h-8" onerror="this.style.display='none'">
            <span class="font-bold text-lg text-white">NET<span class="gtext">CRAFTER</span></span>
        </a>
        <span class="text-xs text-gray-500 hidden sm:flex items-center gap-1.5">
            <i class="fas fa-lock" style="color:#00c8ff"></i>Formulaire sécurisé
        </span>
    </div>
</header>

<main class="max-w-5xl mx-auto px-4 py-10">

<?php if ($invalid): ?>
<!-- ── Lien invalide ── -->
<div class="text-center py-24">
    <div class="w-20 h-20 rounded-2xl flex items-center justify-center mx-auto mb-6"
         style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2)">
        <i class="fas fa-link-slash text-3xl text-red-400"></i>
    </div>
    <h1 class="text-2xl font-bold text-white mb-3">Lien invalide ou expiré</h1>
    <p class="text-gray-400 max-w-sm mx-auto">Ce lien n'est plus valide. Contactez Netcrafter pour obtenir un nouveau lien partagé.</p>
    <a href="mailto:contact@netcrafterniger.com"
       class="btn-pri mt-6 inline-flex"><i class="fas fa-envelope"></i>Contacter Netcrafter</a>
</div>

<?php elseif ($already_sub || $submit_ok): ?>
<!-- ── Succès ── -->
<div class="text-center py-20">
    <div class="w-24 h-24 rounded-full flex items-center justify-center mx-auto mb-6"
         style="background:rgba(16,185,129,.12);border:2px solid #10b981">
        <i class="fas fa-check-circle text-4xl" style="color:#10b981"></i>
    </div>
    <h1 class="text-3xl font-bold text-white mb-4">Cahier des charges reçu !</h1>
    <p class="text-gray-400 max-w-md mx-auto mb-2">
        Merci <strong class="text-white"><?= htmlspecialchars($brief['client_name'] ?: '') ?></strong>,
        votre cahier des charges pour le projet
        <strong class="text-white">« <?= htmlspecialchars($brief['project_name'] ?: '') ?> »</strong>
        a bien été transmis à l'équipe Netcrafter.
    </p>
    <p class="text-gray-500 text-sm mb-10">
        Nous vous contacterons rapidement à
        <span style="color:#00c8ff"><?= htmlspecialchars($brief['client_email'] ?: '') ?></span>.
    </p>
    <div class="glass rounded-2xl p-6 max-w-sm mx-auto">
        <p class="text-sm text-gray-400 mb-3">Des questions ? Contactez-nous directement :</p>
        <a href="https://wa.me/22788672115" target="_blank"
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-sm text-white"
           style="background:rgba(16,185,129,.15);border:1px solid rgba(16,185,129,.3)">
            <i class="fab fa-whatsapp" style="color:#10b981"></i>WhatsApp
        </a>
        <a href="tel:+22788672115"
           class="ml-3 inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-sm text-gray-300"
           style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.12)">
            <i class="fas fa-phone text-nc-cyan"></i>Appeler
        </a>
    </div>
</div>

<?php else: ?>
<!-- ── FORMULAIRE ── -->

<?php if ($submit_err): ?>
<div class="mb-6 p-4 rounded-xl text-red-300 text-sm flex items-start gap-2"
     style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2)">
    <i class="fas fa-exclamation-circle mt-0.5"></i><?= htmlspecialchars($submit_err) ?>
</div>
<?php endif; ?>

<div class="text-center mb-10">
    <h1 class="text-3xl md:text-4xl font-bold text-white mb-3">Cahier des charges</h1>
    <p class="text-gray-400 max-w-lg mx-auto">Décrivez votre projet pour que nous puissions vous proposer la meilleure solution.</p>
    <?php if ($brief['label']): ?>
    <span class="inline-block mt-3 px-4 py-1 rounded-full text-xs font-semibold"
          style="background:rgba(0,200,255,.1);border:1px solid rgba(0,200,255,.25);color:#00c8ff">
        <i class="fas fa-tag mr-1"></i><?= htmlspecialchars($brief['label']) ?>
    </span>
    <?php endif; ?>
</div>

<!-- Step indicator -->
<div class="flex items-center mb-3" id="step-indicator">
    <div class="sdot active" id="dot-1">1</div>
    <div class="sline" id="line-1"></div>
    <div class="sdot" id="dot-2">2</div>
    <div class="sline" id="line-2"></div>
    <div class="sdot" id="dot-3">3</div>
    <div class="sline" id="line-3"></div>
    <div class="sdot" id="dot-4">4</div>
</div>
<div class="grid grid-cols-4 text-center mb-8 text-xs" style="color:#4b5563">
    <span>Coordonnées</span><span>Projet</span><span>Design</span><span>Documents</span>
</div>

<form method="post" enctype="multipart/form-data" id="brief-form" novalidate>

<!-- ════ STEP 1 : Coordonnées ════ -->
<div class="step-panel active" id="step-1">
    <div class="glass rounded-2xl p-6 md:p-8">
        <h2 class="text-xl font-bold text-white mb-6 flex items-center gap-3">
            <span class="w-8 h-8 rounded-lg flex items-center justify-center text-sm font-bold"
                  style="background:rgba(0,200,255,.15);border:1px solid rgba(0,200,255,.3);color:#00c8ff">1</span>
            Vos coordonnées
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="lbl">Nom complet <span class="req">*</span></label>
                <input class="inp" type="text" name="client_name" id="f_name" placeholder="Jean Dupont" autocomplete="name">
            </div>
            <div>
                <label class="lbl">Adresse e-mail <span class="req">*</span></label>
                <input class="inp" type="email" name="client_email" id="f_email" placeholder="vous@exemple.com" autocomplete="email">
            </div>
            <div>
                <label class="lbl">Téléphone / WhatsApp</label>
                <input class="inp" type="tel" name="client_phone" placeholder="+227 XX XX XX XX" autocomplete="tel">
            </div>
            <div>
                <label class="lbl">Entreprise / Organisation</label>
                <input class="inp" type="text" name="client_company" placeholder="Nom de votre société" autocomplete="organization">
            </div>
        </div>
    </div>
    <div class="flex justify-end mt-5">
        <button type="button" class="btn-pri" onclick="goStep(2)">Suivant <i class="fas fa-arrow-right"></i></button>
    </div>
</div>

<!-- ════ STEP 2 : Projet ════ -->
<div class="step-panel" id="step-2">
    <div class="glass rounded-2xl p-6 md:p-8">
        <h2 class="text-xl font-bold text-white mb-6 flex items-center gap-3">
            <span class="w-8 h-8 rounded-lg flex items-center justify-center text-sm font-bold"
                  style="background:rgba(0,200,255,.15);border:1px solid rgba(0,200,255,.3);color:#00c8ff">2</span>
            Votre projet
        </h2>
        <div class="space-y-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="lbl">Nom du projet <span class="req">*</span></label>
                    <input class="inp" type="text" name="project_name" id="f_pname" placeholder="Ex : Site vitrine Mon Commerce">
                </div>
                <div>
                    <label class="lbl">Type de projet</label>
                    <select class="inp" name="project_type">
                        <option value="">— Choisir —</option>
                        <option value="site_vitrine">Site vitrine</option>
                        <option value="ecommerce">E-commerce / Boutique</option>
                        <option value="application_web">Application web</option>
                        <option value="application_mobile">Application mobile</option>
                        <option value="refonte">Refonte de site existant</option>
                        <option value="blog">Blog / Magazine en ligne</option>
                        <option value="portail">Portail client / Intranet</option>
                        <option value="autre">Autre</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="lbl">Description du projet</label>
                <textarea class="inp" name="description" placeholder="Décrivez votre activité et ce que vous souhaitez accomplir avec ce projet..."></textarea>
            </div>
            <div>
                <label class="lbl">Objectifs principaux</label>
                <textarea class="inp" name="objectives" placeholder="Ex : augmenter mes ventes, améliorer ma visibilité, automatiser un processus..."></textarea>
            </div>
            <div>
                <label class="lbl">Audience cible</label>
                <textarea class="inp" name="target_audience" placeholder="Qui sont vos clients / visiteurs ? Âge, profession, localisation, habitudes..."></textarea>
            </div>
        </div>
    </div>
    <div class="flex justify-between mt-5">
        <button type="button" class="btn-sec" onclick="goStep(1)"><i class="fas fa-arrow-left"></i>Précédent</button>
        <button type="button" class="btn-pri" onclick="goStep(3)">Suivant <i class="fas fa-arrow-right"></i></button>
    </div>
</div>

<!-- ════ STEP 3 : Design & Fonctionnalités ════ -->
<div class="step-panel" id="step-3">
    <div class="glass rounded-2xl p-6 md:p-8 space-y-8">
        <h2 class="text-xl font-bold text-white flex items-center gap-3">
            <span class="w-8 h-8 rounded-lg flex items-center justify-center text-sm font-bold"
                  style="background:rgba(0,200,255,.15);border:1px solid rgba(0,200,255,.3);color:#00c8ff">3</span>
            Design & Fonctionnalités
        </h2>

        <!-- Features -->
        <div>
            <label class="lbl mb-3">Fonctionnalités souhaitées <span style="color:#4b5563;text-transform:none;font-weight:400">(sélectionnez tout ce qui s'applique)</span></label>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                <?php
                $feats = [
                    ['vitrine',    'fas fa-globe',          'Site vitrine / Présentation'],
                    ['ecommerce',  'fas fa-shopping-cart',  'E-commerce / Vente en ligne'],
                    ['blog',       'fas fa-pen-nib',        'Blog / Actualités'],
                    ['contact',    'fas fa-envelope',       'Formulaire de contact'],
                    ['espace_mb',  'fas fa-user-circle',    'Espace membre / Client'],
                    ['dashboard',  'fas fa-chart-bar',      'Tableau de bord / Reporting'],
                    ['mobile',     'fas fa-mobile-alt',     'Application mobile'],
                    ['seo',        'fas fa-search',         'Optimisation SEO'],
                    ['social',     'fas fa-share-nodes',    'Intégration réseaux sociaux'],
                    ['newsletter', 'fas fa-paper-plane',    'Newsletter / E-mailing'],
                    ['paiement',   'fas fa-credit-card',    'Paiement en ligne'],
                    ['chat',       'fas fa-comments',       'Chat / Support en ligne'],
                    ['multilang',  'fas fa-language',       'Multi-langue'],
                    ['api',        'fas fa-plug',           'API / Intégrations tierces'],
                    ['galerie',    'fas fa-images',         'Galerie / Portfolio'],
                    ['maintenance','fas fa-wrench',         'Hébergement & Maintenance'],
                ];
                foreach ($feats as [$v,$ico,$lbl]) {
                    echo "<label class='feat-btn' data-v='$v'>"
                        . "<i class='$ico text-xs flex-shrink-0'></i>"
                        . "<span>$lbl</span>"
                        . "<input type='checkbox' name='features[]' value='$v' class='hidden'>"
                        . "</label>";
                }
                ?>
            </div>
            <div class="mt-3">
                <label class="lbl">Autres fonctionnalités</label>
                <input class="inp" type="text" name="custom_features" placeholder="Décrivez d'autres besoins spécifiques...">
            </div>
        </div>

        <!-- Style -->
        <div>
            <label class="lbl mb-3">Style de design souhaité</label>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <?php
                $styles = [
                    ['moderne',    'fas fa-bolt',         'Moderne & Épuré',        '#00c8ff'],
                    ['minimaliste','fas fa-minus-square', 'Minimaliste',             '#8b5cf6'],
                    ['corporate',  'fas fa-building',     'Corporate / Pro',         '#0066cc'],
                    ['creatif',    'fas fa-paint-brush',  'Créatif & Original',      '#f59e0b'],
                    ['tech',       'fas fa-microchip',    'Tech / Futuriste',        '#10b981'],
                    ['classique',  'fas fa-scroll',       'Classique / Traditionnel','#94a3b8'],
                ];
                foreach ($styles as [$v,$ico,$lbl,$clr]) {
                    echo "<label class='style-card' data-v='$v'>"
                        . "<i class='$ico text-xl mb-2' style='color:$clr'></i><br>"
                        . "<span class='text-sm font-medium' style='color:#d1d5db'>$lbl</span>"
                        . "<input type='radio' name='design_style' value='$v' class='hidden'>"
                        . "</label>";
                }
                ?>
            </div>
        </div>

        <!-- Colors & refs -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="lbl">Couleurs préférées</label>
                <input class="inp" type="text" name="color_prefs" placeholder="Ex : bleu marine, blanc, or">
            </div>
            <div>
                <label class="lbl">Sites de référence / inspiration</label>
                <input class="inp" type="text" name="ref_urls" placeholder="Ex : apple.com, airbnb.com">
            </div>
        </div>

        <!-- Brand toggle -->
        <div>
            <label class="feat-btn inline-flex w-auto" id="brand-toggle">
                <i class="fas fa-palette text-xs flex-shrink-0"></i>
                <span>J'ai déjà une charte graphique / identité visuelle</span>
                <input type="checkbox" name="has_brand" value="1" class="hidden" id="brand-cb">
            </label>
            <div id="brand-wrap" class="mt-3 hidden">
                <label class="lbl">Décrivez votre charte existante</label>
                <textarea class="inp" name="brand_details"
                    placeholder="Couleurs officielles, polices, logo existant, guide de style... Vous pourrez aussi uploader vos fichiers à l'étape suivante."></textarea>
            </div>
        </div>
    </div>
    <div class="flex justify-between mt-5">
        <button type="button" class="btn-sec" onclick="goStep(2)"><i class="fas fa-arrow-left"></i>Précédent</button>
        <button type="button" class="btn-pri" onclick="goStep(4)">Suivant <i class="fas fa-arrow-right"></i></button>
    </div>
</div>

<!-- ════ STEP 4 : Documents & Envoi ════ -->
<div class="step-panel" id="step-4">
    <div class="glass rounded-2xl p-6 md:p-8 space-y-6">
        <h2 class="text-xl font-bold text-white flex items-center gap-3">
            <span class="w-8 h-8 rounded-lg flex items-center justify-center text-sm font-bold"
                  style="background:rgba(0,200,255,.15);border:1px solid rgba(0,200,255,.3);color:#00c8ff">4</span>
            Documents, Budget & Envoi
        </h2>

        <!-- Upload -->
        <div>
            <label class="lbl mb-2">Vos documents <span style="color:#4b5563;text-transform:none;font-weight:400">(charte graphique, logo, contenu, maquette…)</span></label>
            <div class="drop-zone" id="drop-zone" onclick="document.getElementById('file-input').click()">
                <i class="fas fa-cloud-upload-alt text-3xl mb-3" style="color:#00c8ff"></i>
                <p class="text-white font-medium mb-1">Glissez vos fichiers ici ou cliquez pour parcourir</p>
                <p class="text-sm" style="color:#6b7280">PDF, Word, PNG, JPG, SVG, AI, PSD, ZIP — max 20 Mo par fichier</p>
                <input type="file" id="file-input" name="docs[]" multiple
                    accept=".pdf,.doc,.docx,.png,.jpg,.jpeg,.gif,.svg,.ai,.psd,.zip,.xls,.xlsx,.ppt,.pptx"
                    class="hidden" onchange="updateFiles(this)">
            </div>
            <div id="file-list" class="mt-3 space-y-2"></div>
        </div>

        <!-- Budget / Délai -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="lbl">Budget estimé</label>
                <select class="inp" name="budget">
                    <option value="">— Non défini —</option>
                    <option value="<200k">Moins de 200 000 FCFA</option>
                    <option value="200-500k">200 000 – 500 000 FCFA</option>
                    <option value="500k-1m">500 000 – 1 000 000 FCFA</option>
                    <option value="1m-3m">1 000 000 – 3 000 000 FCFA</option>
                    <option value=">3m">Plus de 3 000 000 FCFA</option>
                    <option value="a_definir">À définir ensemble</option>
                </select>
            </div>
            <div>
                <label class="lbl">Délai souhaité</label>
                <input class="inp" type="text" name="deadline" placeholder="Ex : avant septembre 2026, dans 2 mois…">
            </div>
        </div>

        <!-- Tech prefs -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="lbl">CMS / Technologie préférée</label>
                <select class="inp" name="cms_pref">
                    <option value="">— Pas de préférence —</option>
                    <option value="wordpress">WordPress</option>
                    <option value="custom">Sur mesure (PHP / Laravel)</option>
                    <option value="shopify">Shopify</option>
                    <option value="prestashop">PrestaShop</option>
                    <option value="autre">Autre</option>
                </select>
            </div>
            <div>
                <label class="lbl">Hébergement</label>
                <select class="inp" name="hosting_pref">
                    <option value="">— Pas de préférence —</option>
                    <option value="netcrafter">Géré par Netcrafter</option>
                    <option value="existant">J'ai déjà un hébergement</option>
                    <option value="local">Serveur local / entreprise</option>
                </select>
            </div>
        </div>

        <!-- Notes -->
        <div>
            <label class="lbl">Informations complémentaires</label>
            <textarea class="inp" name="notes"
                placeholder="Contraintes techniques, intégrations existantes, accès en cours, remarques particulières…"></textarea>
        </div>

        <!-- Summary -->
        <div id="summary-box" class="p-4 rounded-xl hidden"
             style="background:rgba(0,200,255,.04);border:1px solid rgba(0,200,255,.12)">
            <p class="text-xs font-semibold uppercase tracking-wider mb-3" style="color:#4b5563">Récapitulatif</p>
            <div id="summary-content" class="text-sm space-y-1.5" style="color:#d1d5db"></div>
        </div>

        <!-- Confirm + submit -->
        <div class="pt-1">
            <label class="flex items-start gap-3 cursor-pointer mb-5">
                <input type="checkbox" id="confirm-cb" class="mt-0.5" style="accent-color:#00c8ff">
                <span class="text-sm" style="color:#9ca3af">Je confirme que ces informations sont exactes et j'autorise Netcrafter à les utiliser pour établir une proposition.</span>
            </label>
            <button type="submit" name="submit_brief" id="submit-btn" disabled
                    class="w-full py-4 rounded-xl font-bold text-white text-base flex items-center justify-center gap-3 transition-all"
                    style="background:linear-gradient(135deg,#00c8ff,#0066cc);opacity:.45;cursor:not-allowed">
                <i class="fas fa-paper-plane"></i> Envoyer mon cahier des charges
            </button>
        </div>
    </div>
    <div class="flex justify-start mt-5">
        <button type="button" class="btn-sec" onclick="goStep(3)"><i class="fas fa-arrow-left"></i>Précédent</button>
    </div>
</div>

</form>
<?php endif; ?>
</main>

<footer class="mt-20 py-8 text-center text-xs border-t"
        style="color:#374151;border-color:rgba(255,255,255,.06)">
    &copy; <?= date('Y') ?> <span style="color:#00c8ff">Netcrafter</span> · Niamey, Niger ·
    <a href="mailto:contact@netcrafterniger.com" class="hover:text-gray-400 transition-colors">contact@netcrafterniger.com</a>
</footer>

<script>
const STEPS = 4;
let cur = <?= $initial_step ?>;
const TOKEN = '<?= addslashes($token) ?>';

function goStep(n) {
    if (n > cur) {
        if (cur === 1 && !validateStep1()) return;
        if (cur === 2 && !validateStep2()) return;
    }
    document.querySelector('.step-panel.active')?.classList.remove('active');
    document.getElementById('step-' + n).classList.add('active');
    for (let i = 1; i <= STEPS; i++) {
        const dot  = document.getElementById('dot-' + i);
        const line = document.getElementById('line-' + i);
        dot.classList.remove('active','done');
        if (line) line.classList.remove('done');
        if (i < n)      { dot.classList.add('done');   dot.innerHTML = '<i class="fas fa-check text-xs"></i>'; if (line) line.classList.add('done'); }
        else if (i===n) { dot.classList.add('active'); dot.textContent = i; }
        else            { dot.textContent = i; }
    }
    cur = n;
    window.scrollTo({top:0,behavior:'smooth'});
    save();
    if (n === 4) buildSummary();
}

function validateStep1() {
    const name  = document.getElementById('f_name').value.trim();
    const email = document.getElementById('f_email').value.trim();
    if (!name || !email) { toast('Veuillez saisir votre nom et votre e-mail.','error'); return false; }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { toast('Adresse e-mail invalide.','error'); return false; }
    return true;
}
function validateStep2() {
    if (!document.getElementById('f_pname').value.trim()) {
        toast('Veuillez saisir le nom du projet.','error'); return false;
    }
    return true;
}

// Feature toggles
document.querySelectorAll('.feat-btn[data-v]').forEach(btn => {
    btn.addEventListener('click', function(e) {
        if (e.target.tagName === 'INPUT') return;
        const cb = this.querySelector('input[type=checkbox]');
        cb.checked = !cb.checked;
        this.classList.toggle('on', cb.checked);
        if (this.id === 'brand-toggle') {
            document.getElementById('brand-wrap').classList.toggle('hidden', !cb.checked);
        }
    });
});

// Style cards
document.querySelectorAll('.style-card').forEach(card => {
    card.addEventListener('click', function(e) {
        if (e.target.tagName === 'INPUT') return;
        document.querySelectorAll('.style-card').forEach(c => c.classList.remove('on'));
        this.classList.add('on');
        this.querySelector('input[type=radio]').checked = true;
    });
});

// File upload
function updateFiles(input) {
    const list = document.getElementById('file-list');
    list.innerHTML = '';
    Array.from(input.files).forEach(f => {
        const mb = (f.size/1024/1024).toFixed(2);
        const el = document.createElement('div');
        el.className = 'flex items-center gap-3 p-3 rounded-lg text-sm';
        el.style.cssText = 'background:rgba(0,200,255,.05);border:1px solid rgba(0,200,255,.1)';
        el.innerHTML = `<i class="fas fa-file-alt flex-shrink-0" style="color:#00c8ff"></i>
                        <span class="flex-1 truncate" style="color:#d1d5db">${f.name}</span>
                        <span class="flex-shrink-0" style="color:#6b7280">${mb} Mo</span>`;
        list.appendChild(el);
    });
}
// Drag-drop
const dz = document.getElementById('drop-zone');
if (dz) {
    dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('over'); });
    dz.addEventListener('dragleave', ()  => dz.classList.remove('over'));
    dz.addEventListener('drop', e => {
        e.preventDefault(); dz.classList.remove('over');
        const fi = document.getElementById('file-input');
        const dt = new DataTransfer();
        [...e.dataTransfer.files].forEach(f => dt.items.add(f));
        fi.files = dt.files;
        updateFiles(fi);
    });
}

// Confirm checkbox
document.getElementById('confirm-cb')?.addEventListener('change', function() {
    const btn = document.getElementById('submit-btn');
    btn.disabled = !this.checked;
    btn.style.opacity = this.checked ? '1' : '.45';
    btn.style.cursor  = this.checked ? 'pointer' : 'not-allowed';
});

// Summary
function buildSummary() {
    const rows = [
        ['Client',  document.getElementById('f_name')?.value],
        ['E-mail',  document.getElementById('f_email')?.value],
        ['Projet',  document.getElementById('f_pname')?.value],
        ['Type',    document.querySelector('[name=project_type]')?.options[document.querySelector('[name=project_type]').selectedIndex]?.text],
        ['Style',   document.querySelector('.style-card.on span')?.textContent],
        ['Budget',  document.querySelector('[name=budget]')?.options[document.querySelector('[name=budget]').selectedIndex]?.text],
        ['Délai',   document.querySelector('[name=deadline]')?.value],
    ];
    const feats = [...document.querySelectorAll('.feat-btn.on:not(#brand-toggle) span')].map(s => s.textContent);
    let html = rows.filter(([,v]) => v && v !== '— Choisir —' && v !== '— Pas de préférence —' && v !== '— Non défini —')
                   .map(([k,v]) => `<div class="flex gap-2"><span style="color:#6b7280;min-width:90px;flex-shrink:0">${k}</span><span>${v}</span></div>`).join('');
    if (feats.length) html += `<div class="flex gap-2"><span style="color:#6b7280;min-width:90px;flex-shrink:0">Fonctions</span><span>${feats.join(', ')}</span></div>`;
    document.getElementById('summary-content').innerHTML = html;
    document.getElementById('summary-box').classList.remove('hidden');
}

// LocalStorage
function save() {
    const form = document.getElementById('brief-form');
    if (!form || !TOKEN) return;
    const d = {};
    form.querySelectorAll('input:not([type=file]):not([type=checkbox]):not([type=radio]),select,textarea')
        .forEach(el => { if (el.name) d[el.name] = el.value; });
    form.querySelectorAll('input[type=checkbox]:checked').forEach(el => {
        d[el.name] = d[el.name] ? [...(Array.isArray(d[el.name]) ? d[el.name] : [d[el.name]]), el.value] : el.value;
    });
    form.querySelectorAll('input[type=radio]:checked').forEach(el => { d[el.name] = el.value; });
    try { localStorage.setItem('nc_brief_' + TOKEN, JSON.stringify(d)); } catch(e){}
}
function restore() {
    if (!TOKEN) return;
    let saved;
    try { saved = JSON.parse(localStorage.getItem('nc_brief_' + TOKEN) || 'null'); } catch(e){ return; }
    if (!saved) return;
    const form = document.getElementById('brief-form');
    Object.entries(saved).forEach(([name, val]) => {
        form.querySelectorAll(`[name="${name}"],[name="${name}[]"]`).forEach(el => {
            if (el.type === 'checkbox') {
                el.checked = Array.isArray(val) ? val.includes(el.value) : el.value === val;
                el.closest('.feat-btn')?.classList.toggle('on', el.checked);
            } else if (el.type === 'radio') {
                if (el.value === val) { el.checked = true; el.closest('.style-card')?.classList.add('on'); }
            } else { el.value = val || ''; }
        });
    });
    if (saved['has_brand'] === '1') document.getElementById('brand-wrap')?.classList.remove('hidden');
}
document.getElementById('brief-form')?.addEventListener('input', save);
restore();
if (cur > 1) goStep(cur);

// Toast
function toast(msg, type='info') {
    const el = document.createElement('div');
    const bg = type === 'error' ? '#ef4444' : '#10b981';
    el.style.cssText = `position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;padding:.75rem 1.25rem;
        border-radius:10px;font-size:.85rem;font-weight:600;color:#fff;
        box-shadow:0 4px 20px rgba(0,0,0,.4);background:${bg};animation:fadeUp .3s ease`;
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3500);
}
</script>
</body>
</html>
