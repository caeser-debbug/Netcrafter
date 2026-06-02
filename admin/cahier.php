<?php
// ── DB (must be before any include that may output) ─────────────────────────
$_ch = $_SERVER['HTTP_HOST'] ?? '';
$_vl = PHP_OS_FAMILY === 'Windows'
    || strpos($_ch,'localhost')!==false
    || strpos($_ch,'127.0.0.1')!==false;
try {
    $pdo = new PDO(
        'mysql:host=localhost;charset=utf8mb4;dbname=' . ($_vl ? 'netcrafter' : 'u264396140_netcrafternige'),
        $_vl ? 'root'                       : 'u264396140_netcrefternige',
        $_vl ? ''                           : 'Hondaand@1',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $pdo->exec("CREATE TABLE IF NOT EXISTS `cahier_charges` (
        `id`             INT AUTO_INCREMENT PRIMARY KEY,
        `token`          VARCHAR(64)  NOT NULL UNIQUE,
        `label`          VARCHAR(255) DEFAULT NULL,
        `client_name`    VARCHAR(255) DEFAULT NULL,
        `client_email`   VARCHAR(255) DEFAULT NULL,
        `client_phone`   VARCHAR(100) DEFAULT NULL,
        `client_company` VARCHAR(255) DEFAULT NULL,
        `project_name`   VARCHAR(255) DEFAULT NULL,
        `project_type`   VARCHAR(100) DEFAULT NULL,
        `description`    TEXT,
        `objectives`     TEXT,
        `target_audience`TEXT,
        `features`       TEXT,
        `custom_features`TEXT,
        `design_style`   VARCHAR(100) DEFAULT NULL,
        `color_prefs`    TEXT,
        `has_brand`      TINYINT(1)  DEFAULT 0,
        `brand_details`  TEXT,
        `ref_urls`       TEXT,
        `budget`         VARCHAR(100) DEFAULT NULL,
        `deadline`       VARCHAR(100) DEFAULT NULL,
        `cms_pref`       VARCHAR(100) DEFAULT NULL,
        `hosting_pref`   VARCHAR(100) DEFAULT NULL,
        `notes`          TEXT,
        `status`         ENUM('pending','in_review','validated','archived') DEFAULT 'pending',
        `admin_notes`    TEXT,
        `submitted_at`   DATETIME DEFAULT NULL,
        `created_at`     DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `cahier_files` (
        `id`           INT AUTO_INCREMENT PRIMARY KEY,
        `cahier_id`    INT  NOT NULL,
        `original_name`VARCHAR(500) NOT NULL,
        `stored_name`  VARCHAR(500) NOT NULL,
        `file_type`    VARCHAR(100) DEFAULT NULL,
        `file_size`    INT  DEFAULT 0,
        `uploaded_at`  DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {
    die('DB error: ' . $e->getMessage());
}

$action = $_GET['action'] ?? 'list';

// ── AJAX: update status ──────────────────────────────────────────────────────
if ($action === 'update_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $st = in_array($_POST['status']??'',['pending','in_review','validated','archived']) ? $_POST['status'] : 'pending';
    $pdo->prepare("UPDATE cahier_charges SET status=? WHERE id=?")->execute([$st,$id]);
    echo json_encode(['ok'=>true]); exit;
}

// ── AJAX: save admin notes ───────────────────────────────────────────────────
if ($action === 'save_notes' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id    = (int)($_POST['id'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $pdo->prepare("UPDATE cahier_charges SET admin_notes=? WHERE id=?")->execute([$notes,$id]);
    echo json_encode(['ok'=>true]); exit;
}

// ── File download ────────────────────────────────────────────────────────────
if ($action === 'download' && isset($_GET['fid'])) {
    require_once __DIR__ . '/includes/auth.php';
    $fid  = (int)($_GET['fid'] ?? 0);
    $stmt = $pdo->prepare("SELECT cf.*,cc.id AS cid FROM cahier_files cf
                           JOIN cahier_charges cc ON cf.cahier_id=cc.id WHERE cf.id=?");
    $stmt->execute([$fid]);
    $file = $stmt->fetch();
    if ($file) {
        $path = dirname(__DIR__) . "/uploads/cahier/{$file['cid']}/{$file['stored_name']}";
        if (file_exists($path)) {
            header('Content-Type: ' . ($file['file_type'] ?: 'application/octet-stream'));
            header('Content-Disposition: attachment; filename="' . rawurlencode($file['original_name']) . '"');
            header('Content-Length: ' . filesize($path));
            readfile($path);
            exit;
        }
    }
    die('Fichier introuvable.');
}

// ── Create new link ──────────────────────────────────────────────────────────
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $label = htmlspecialchars(trim($_POST['label'] ?? 'Nouveau projet'), ENT_QUOTES);
    $token = bin2hex(random_bytes(16));
    $pdo->prepare("INSERT INTO cahier_charges (token,label) VALUES (?,?)")->execute([$token,$label]);
    header('Location: cahier.php?created=1'); exit;
}

// ── Delete ───────────────────────────────────────────────────────────────────
if ($action === 'delete' && isset($_GET['id'])) {
    $id    = (int)$_GET['id'];
    $files = $pdo->prepare("SELECT stored_name FROM cahier_files WHERE cahier_id=?");
    $files->execute([$id]);
    $upbase = dirname(__DIR__) . "/uploads/cahier/$id/";
    foreach ($files->fetchAll() as $f) @unlink($upbase . $f['stored_name']);
    @rmdir($upbase);
    $pdo->prepare("DELETE FROM cahier_files  WHERE cahier_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM cahier_charges WHERE id=?")->execute([$id]);
    header('Location: cahier.php?deleted=1'); exit;
}

// ── Load view data ───────────────────────────────────────────────────────────
$brief      = null;
$brief_files = [];
if ($action === 'view' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM cahier_charges WHERE id=?");
    $stmt->execute([(int)$_GET['id']]);
    $brief = $stmt->fetch();
    if (!$brief) { header('Location: cahier.php'); exit; }
    $fstmt = $pdo->prepare("SELECT * FROM cahier_files WHERE cahier_id=? ORDER BY uploaded_at");
    $fstmt->execute([(int)$_GET['id']]);
    $brief_files = $fstmt->fetchAll();
}
$briefs = [];
if ($action === 'list') {
    $briefs = $pdo->query("SELECT * FROM cahier_charges ORDER BY created_at DESC")->fetchAll();
}

// ── Helpers ──────────────────────────────────────────────────────────────────
$proto    = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on') ? 'https' : 'http';
$base_url = $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$base_path = strpos($_SERVER['HTTP_HOST']??'','localhost')!==false ? '/netcrafter' : '';
$share_base = $base_url . $base_path . '/cahier/index.php?token=';

$st_labels = ['pending'=>'En attente','in_review'=>'En cours','validated'=>'Validé','archived'=>'Archivé'];
$st_colors = ['pending'=>'yellow','in_review'=>'blue','validated'=>'green','archived'=>'gray'];

$type_labels = [
    'site_vitrine'=>'Site vitrine','ecommerce'=>'E-commerce','application_web'=>'App web',
    'application_mobile'=>'App mobile','refonte'=>'Refonte','blog'=>'Blog','portail'=>'Portail','autre'=>'Autre'
];
$feat_labels = [
    'vitrine'=>'Site vitrine','ecommerce'=>'E-commerce','blog'=>'Blog','contact'=>'Formulaire contact',
    'espace_mb'=>'Espace membre','dashboard'=>'Dashboard','mobile'=>'App mobile','seo'=>'SEO',
    'social'=>'Réseaux sociaux','newsletter'=>'Newsletter','paiement'=>'Paiement','chat'=>'Chat',
    'multilang'=>'Multi-langue','api'=>'API','galerie'=>'Galerie','maintenance'=>'Maintenance',
];
$style_labels  = ['moderne'=>'Moderne & Épuré','minimaliste'=>'Minimaliste','corporate'=>'Corporate',
                  'creatif'=>'Créatif','tech'=>'Tech / Futuriste','classique'=>'Classique'];
$budget_labels = ['<200k'=>'< 200K FCFA','200-500k'=>'200–500K','500k-1m'=>'500K–1M',
                  '1m-3m'=>'1M–3M','>3m'=>'> 3M','a_definir'=>'À définir'];
$cms_labels    = ['wordpress'=>'WordPress','custom'=>'Sur mesure','shopify'=>'Shopify',
                  'prestashop'=>'PrestaShop','autre'=>'Autre'];
$host_labels   = ['netcrafter'=>'Géré par Netcrafter','existant'=>'Hébergement existant','local'=>'Serveur local'];

// ── Admin header ─────────────────────────────────────────────────────────────
$page_title = 'Cahiers des charges';
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($action === 'list'): ?>
<!-- ══════════════════════ LIST VIEW ══════════════════════ -->

<?php if (isset($_GET['created'])): ?>
<div class="mb-4 p-4 rounded-xl bg-green-900 text-green-300 text-sm flex items-center gap-2">
    <i class="fas fa-check-circle"></i>Lien créé avec succès. Copiez-le et partagez-le avec votre client.
</div>
<?php endif; ?>
<?php if (isset($_GET['deleted'])): ?>
<div class="mb-4 p-4 rounded-xl bg-red-900 text-red-300 text-sm flex items-center gap-2">
    <i class="fas fa-trash"></i>Cahier supprimé.
</div>
<?php endif; ?>

<!-- Toolbar -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
    <div>
        <p class="text-gray-400 text-sm"><?= count($briefs) ?> lien<?= count($briefs)!==1?'s':'' ?> au total</p>
    </div>
    <button onclick="document.getElementById('create-modal').classList.remove('hidden')"
            class="flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-white transition-all hover:scale-105"
            style="background:linear-gradient(135deg,#3b82f6,#1d4ed8)">
        <i class="fas fa-plus"></i>Nouveau lien client
    </button>
</div>

<!-- Table -->
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow overflow-hidden">
    <div class="table-responsive">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Label / Projet</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider hidden md:table-cell">Client</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider hidden lg:table-cell">Créé le</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Statut</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <?php if (empty($briefs)): ?>
                <tr>
                    <td colspan="5" class="px-4 py-10 text-center text-gray-400">
                        <i class="fas fa-folder-open text-3xl mb-3 block opacity-30"></i>
                        Aucun cahier des charges. Créez un premier lien pour le partager avec un client.
                    </td>
                </tr>
                <?php else: foreach ($briefs as $b):
                    $sc = $st_colors[$b['status']] ?? 'gray';
                    $sl = $st_labels[$b['status']] ?? '—';
                    $share_url = $share_base . $b['token'];
                    $submitted = $b['submitted_at'] !== null;
                ?>
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors">
                    <td class="px-4 py-3">
                        <div class="font-medium text-gray-800 dark:text-white text-sm">
                            <?= htmlspecialchars($b['label'] ?: '(sans titre)') ?>
                        </div>
                        <?php if ($b['project_name']): ?>
                        <div class="text-xs text-gray-400 mt-0.5">
                            <i class="fas fa-folder-open mr-1"></i><?= htmlspecialchars($b['project_name']) ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!$submitted): ?>
                        <span class="text-xs text-yellow-500"><i class="fas fa-clock mr-1"></i>En attente de réponse</span>
                        <?php else: ?>
                        <span class="text-xs text-green-500"><i class="fas fa-check mr-1"></i>Soumis le <?= date('d/m/Y', strtotime($b['submitted_at'])) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 hidden md:table-cell">
                        <?php if ($b['client_name']): ?>
                        <div class="text-sm text-gray-700 dark:text-gray-200"><?= htmlspecialchars($b['client_name']) ?></div>
                        <div class="text-xs text-gray-400"><?= htmlspecialchars($b['client_email'] ?: '') ?></div>
                        <?php else: ?>
                        <span class="text-xs text-gray-500 italic">Formulaire non rempli</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-400 hidden lg:table-cell">
                        <?= date('d/m/Y H:i', strtotime($b['created_at'])) ?>
                    </td>
                    <td class="px-4 py-3">
                        <select onchange="updateStatus(<?= $b['id'] ?>, this.value)"
                                class="text-xs px-2 py-1 rounded-lg border-0 font-semibold cursor-pointer
                                       <?= $sc==='yellow'?'bg-yellow-100 text-yellow-800':($sc==='blue'?'bg-blue-100 text-blue-800':($sc==='green'?'bg-green-100 text-green-800':'bg-gray-100 text-gray-600')) ?>">
                            <?php foreach ($st_labels as $sv => $sl2): ?>
                            <option value="<?= $sv ?>" <?= $b['status']===$sv?'selected':'' ?>><?= $sl2 ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <!-- Copy link -->
                            <button onclick="copyLink('<?= htmlspecialchars($share_url, ENT_QUOTES) ?>')"
                                    title="Copier le lien client"
                                    class="w-8 h-8 rounded-lg flex items-center justify-center text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-colors">
                                <i class="fas fa-link text-xs"></i>
                            </button>
                            <?php if ($submitted): ?>
                            <!-- View -->
                            <a href="cahier.php?action=view&id=<?= $b['id'] ?>"
                               title="Voir le cahier"
                               class="w-8 h-8 rounded-lg flex items-center justify-center text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-colors">
                                <i class="fas fa-eye text-xs"></i>
                            </a>
                            <?php endif; ?>
                            <!-- Delete -->
                            <a href="cahier.php?action=delete&id=<?= $b['id'] ?>"
                               onclick="return confirm('Supprimer ce cahier et tous ses fichiers ?')"
                               title="Supprimer"
                               class="w-8 h-8 rounded-lg flex items-center justify-center text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors">
                                <i class="fas fa-trash text-xs"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create modal -->
<div id="create-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,.6);backdrop-filter:blur(4px)">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md p-6">
        <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-1">Créer un lien client</h3>
        <p class="text-sm text-gray-500 mb-5">Un lien unique sera généré. Partagez-le avec votre client pour qu'il remplisse son cahier des charges.</p>
        <form method="post" action="cahier.php?action=create">
            <label class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-2">
                Étiquette interne <span class="text-gray-400 font-normal">(nom du projet ou du client)</span>
            </label>
            <input type="text" name="label" required placeholder="Ex : Site vitrine M. Issoufou"
                   class="w-full px-4 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 mb-5">
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('create-modal').classList.add('hidden')"
                        class="px-4 py-2 rounded-xl text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 border border-gray-200 dark:border-gray-600 transition-colors">
                    Annuler
                </button>
                <button type="submit"
                        class="px-5 py-2 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 transition-colors">
                    <i class="fas fa-link mr-1"></i>Créer le lien
                </button>
            </div>
        </form>
    </div>
</div>


<?php elseif ($action === 'view' && $brief): ?>
<!-- ══════════════════════ DETAIL VIEW ══════════════════════ -->

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
    <a href="cahier.php" class="flex items-center gap-2 text-sm text-gray-400 hover:text-white transition-colors">
        <i class="fas fa-arrow-left"></i>Retour à la liste
    </a>
    <div class="flex items-center gap-3">
        <?php
        $sc = $st_colors[$brief['status']] ?? 'gray';
        $colorClasses = ['yellow'=>'bg-yellow-100 text-yellow-800','blue'=>'bg-blue-100 text-blue-800','green'=>'bg-green-100 text-green-800','gray'=>'bg-gray-100 text-gray-600'];
        ?>
        <select id="status-sel" onchange="updateStatus(<?= $brief['id'] ?>, this.value)"
                class="text-sm px-3 py-1.5 rounded-xl border-0 font-semibold cursor-pointer <?= $colorClasses[$sc] ?? 'bg-gray-100 text-gray-600' ?>">
            <?php foreach ($st_labels as $sv => $sl2): ?>
            <option value="<?= $sv ?>" <?= $brief['status']===$sv?'selected':'' ?>><?= $sl2 ?></option>
            <?php endforeach; ?>
        </select>
        <button onclick="copyLink('<?= htmlspecialchars($share_base . $brief['token'], ENT_QUOTES) ?>')"
                class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm text-blue-400 border border-blue-400/30 hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-colors">
            <i class="fas fa-link"></i>Copier le lien
        </button>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Left column -->
    <div class="lg:col-span-2 space-y-5">

        <!-- Client info -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 shadow">
            <h3 class="font-semibold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                <i class="fas fa-user text-blue-400 text-sm"></i>Informations client
            </h3>
            <div class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                <?php
                $ci = [
                    ['Nom',         $brief['client_name']],
                    ['E-mail',      $brief['client_email']],
                    ['Téléphone',   $brief['client_phone']],
                    ['Entreprise',  $brief['client_company']],
                ];
                foreach ($ci as [$k,$v]): if (!$v) continue; ?>
                <div><span class="text-gray-400 block text-xs uppercase font-medium tracking-wide"><?= $k ?></span>
                     <span class="text-gray-700 dark:text-gray-200"><?= htmlspecialchars($v) ?></span></div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Project info -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 shadow">
            <h3 class="font-semibold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                <i class="fas fa-folder-open text-indigo-400 text-sm"></i>Projet
            </h3>
            <div class="space-y-3 text-sm">
                <?php
                $pi = [
                    ['Nom du projet',  $brief['project_name']],
                    ['Type',           $type_labels[$brief['project_type']] ?? $brief['project_type']],
                    ['Budget',         $budget_labels[$brief['budget']] ?? $brief['budget']],
                    ['Délai',          $brief['deadline']],
                    ['CMS / Tech',     $cms_labels[$brief['cms_pref']] ?? $brief['cms_pref']],
                    ['Hébergement',    $host_labels[$brief['hosting_pref']] ?? $brief['hosting_pref']],
                ];
                foreach ($pi as [$k,$v]): if (!$v) continue; ?>
                <div class="flex gap-3"><span class="text-gray-400 text-xs uppercase font-medium tracking-wide w-28 flex-shrink-0 mt-0.5"><?= $k ?></span>
                     <span class="text-gray-700 dark:text-gray-200"><?= htmlspecialchars($v) ?></span></div>
                <?php endforeach; ?>
                <?php foreach (['description'=>'Description','objectives'=>'Objectifs','target_audience'=>'Audience cible'] as $col => $lbl): if (!$brief[$col]) continue; ?>
                <div><span class="text-gray-400 text-xs uppercase font-medium tracking-wide block mb-1"><?= $lbl ?></span>
                     <p class="text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-line"><?= htmlspecialchars($brief[$col]) ?></p></div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Features & Design -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 shadow">
            <h3 class="font-semibold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                <i class="fas fa-paint-brush text-pink-400 text-sm"></i>Design & Fonctionnalités
            </h3>
            <?php
            $feats = json_decode($brief['features'] ?? '[]', true) ?: [];
            if ($feats): ?>
            <div class="mb-4">
                <span class="text-xs text-gray-400 uppercase font-medium tracking-wide block mb-2">Fonctionnalités</span>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($feats as $fv): ?>
                    <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-300">
                        <?= htmlspecialchars($feat_labels[$fv] ?? $fv) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($brief['custom_features']): ?>
            <div class="mb-4"><span class="text-xs text-gray-400 uppercase font-medium tracking-wide block mb-1">Autres fonctionnalités</span>
                 <p class="text-sm text-gray-700 dark:text-gray-300"><?= htmlspecialchars($brief['custom_features']) ?></p></div>
            <?php endif; ?>
            <?php if ($brief['design_style']): ?>
            <div class="mb-4"><span class="text-xs text-gray-400 uppercase font-medium tracking-wide block mb-1">Style</span>
                 <span class="text-sm text-gray-700 dark:text-gray-200"><?= htmlspecialchars($style_labels[$brief['design_style']] ?? $brief['design_style']) ?></span></div>
            <?php endif; ?>
            <?php if ($brief['color_prefs']): ?>
            <div class="mb-4"><span class="text-xs text-gray-400 uppercase font-medium tracking-wide block mb-1">Couleurs</span>
                 <span class="text-sm text-gray-700 dark:text-gray-200"><?= htmlspecialchars($brief['color_prefs']) ?></span></div>
            <?php endif; ?>
            <?php if ($brief['ref_urls']): ?>
            <div class="mb-4"><span class="text-xs text-gray-400 uppercase font-medium tracking-wide block mb-1">Références</span>
                 <span class="text-sm text-gray-700 dark:text-gray-200"><?= htmlspecialchars($brief['ref_urls']) ?></span></div>
            <?php endif; ?>
            <?php if ($brief['has_brand'] && $brief['brand_details']): ?>
            <div><span class="text-xs text-gray-400 uppercase font-medium tracking-wide block mb-1">Charte existante</span>
                 <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line"><?= htmlspecialchars($brief['brand_details']) ?></p></div>
            <?php endif; ?>
        </div>

        <?php if ($brief['notes']): ?>
        <!-- Notes -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 shadow">
            <h3 class="font-semibold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                <i class="fas fa-sticky-note text-yellow-400 text-sm"></i>Notes complémentaires
            </h3>
            <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line leading-relaxed"><?= htmlspecialchars($brief['notes']) ?></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Right column -->
    <div class="space-y-5">

        <!-- Submitted at -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 shadow">
            <div class="text-center">
                <i class="fas fa-calendar-check text-2xl text-green-500 mb-2"></i>
                <p class="text-xs text-gray-400 uppercase font-medium tracking-wide">Soumis le</p>
                <p class="text-gray-700 dark:text-white font-semibold">
                    <?= $brief['submitted_at'] ? date('d/m/Y à H:i', strtotime($brief['submitted_at'])) : '—' ?>
                </p>
            </div>
        </div>

        <!-- Files -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 shadow">
            <h3 class="font-semibold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                <i class="fas fa-paperclip text-cyan-400 text-sm"></i>
                Documents <span class="text-gray-400 text-xs font-normal ml-1">(<?= count($brief_files) ?>)</span>
            </h3>
            <?php if (empty($brief_files)): ?>
            <p class="text-sm text-gray-400 italic">Aucun document fourni.</p>
            <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($brief_files as $f):
                    $mb   = number_format($f['file_size']/1024/1024, 2);
                    $ext  = strtolower(pathinfo($f['original_name'], PATHINFO_EXTENSION));
                    $ico  = match(true) {
                        in_array($ext,['jpg','jpeg','png','gif','svg']) => 'fa-file-image text-pink-400',
                        in_array($ext,['pdf'])                          => 'fa-file-pdf  text-red-400',
                        in_array($ext,['doc','docx'])                   => 'fa-file-word text-blue-400',
                        in_array($ext,['xls','xlsx'])                   => 'fa-file-excel text-green-500',
                        in_array($ext,['zip'])                          => 'fa-file-archive text-yellow-400',
                        default                                          => 'fa-file text-gray-400',
                    };
                ?>
                <div class="flex items-center gap-2.5 p-2.5 rounded-lg bg-gray-50 dark:bg-gray-700">
                    <i class="fas <?= $ico ?> text-sm flex-shrink-0"></i>
                    <span class="flex-1 text-xs text-gray-700 dark:text-gray-200 truncate" title="<?= htmlspecialchars($f['original_name']) ?>">
                        <?= htmlspecialchars($f['original_name']) ?>
                    </span>
                    <span class="text-xs text-gray-400 flex-shrink-0"><?= $mb ?> Mo</span>
                    <a href="cahier.php?action=download&fid=<?= $f['id'] ?>"
                       class="flex-shrink-0 w-7 h-7 rounded-lg flex items-center justify-center text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-colors"
                       title="Télécharger">
                        <i class="fas fa-download text-xs"></i>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Admin notes -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-5 shadow">
            <h3 class="font-semibold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                <i class="fas fa-pen text-purple-400 text-sm"></i>Notes internes
            </h3>
            <textarea id="admin-notes-ta" rows="5"
                      class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 resize-none"
                      placeholder="Commentaires internes, devis estimé, prochaine étape…"><?= htmlspecialchars($brief['admin_notes'] ?? '') ?></textarea>
            <button onclick="saveNotes(<?= $brief['id'] ?>)"
                    class="mt-2 w-full py-2 rounded-xl text-sm font-semibold text-white bg-purple-600 hover:bg-purple-700 transition-colors">
                <i class="fas fa-save mr-1"></i>Enregistrer
            </button>
        </div>
    </div>
</div>

<?php endif; ?>

<script>
function updateStatus(id, status) {
    fetch('cahier.php?action=update_status', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({id, status})
    }).then(r => r.json()).then(d => {
        if (d.ok) showAdminToast('Statut mis à jour', 'success');
    });
}
function saveNotes(id) {
    const notes = document.getElementById('admin-notes-ta')?.value || '';
    fetch('cahier.php?action=save_notes', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({id, notes})
    }).then(r => r.json()).then(d => {
        if (d.ok) showAdminToast('Notes enregistrées', 'success');
    });
}
function copyLink(url) {
    navigator.clipboard.writeText(url).then(() => {
        showAdminToast('Lien copié ! Partagez-le avec votre client.', 'success');
    }).catch(() => {
        prompt('Copiez ce lien :', url);
    });
}
function showAdminToast(msg, type) {
    const el = document.createElement('div');
    el.className = 'toast show';
    el.style.cssText = `background:${type==='success'?'#10b981':'#ef4444'};color:#fff;font-size:.85rem;font-weight:600`;
    el.innerHTML = `<i class="fas fa-${type==='success'?'check':'exclamation'}-circle mr-2"></i>${msg}`;
    document.getElementById('toast-container')?.appendChild(el);
    setTimeout(() => { el.style.opacity='0'; setTimeout(() => el.remove(), 400); }, 3000);
}
// Close modal on backdrop click
document.getElementById('create-modal')?.addEventListener('click', function(e) {
    if (e.target === this) this.classList.add('hidden');
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
