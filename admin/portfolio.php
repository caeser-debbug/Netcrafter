<?php
mysqli_report(MYSQLI_REPORT_OFF); // PHP 8.1+ throws exceptions on failed queries — disable
$page_title = 'Gestion Portfolio';

// Connexion vitrine AVANT le header pour capturer les erreurs proprement
$_vh = $_SERVER['HTTP_HOST'] ?? '';
$_vl = PHP_OS_FAMILY === 'Windows'
    || strpos($_vh,'localhost') !== false
    || strpos($_vh,'127.0.0.1') !== false
    || strpos($_vh,'::1')       !== false;
$vconn = new mysqli('localhost',
    $_vl ? 'root'                   : 'u264396140_netcrefternige',
    $_vl ? ''                       : 'Hondaand@1',
    $_vl ? 'netcrafter'             : 'u264396140_netcrafternige'
);
$_vconn_error = $vconn->connect_error ?? null;
if (!$_vconn_error) {
    $vconn->set_charset('utf8mb4');
    $vconn->query("CREATE TABLE IF NOT EXISTS portfolio_projects (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        title       VARCHAR(255) NOT NULL,
        slug        VARCHAR(280) NOT NULL UNIQUE,
        category    VARCHAR(50)  DEFAULT 'dev-web',
        short_desc  TEXT,
        full_desc   LONGTEXT,
        image       VARCHAR(500) DEFAULT NULL,
        tags        VARCHAR(500) DEFAULT '',
        client      VARCHAR(150) DEFAULT '',
        year        SMALLINT     DEFAULT NULL,
        live_url    VARCHAR(500) DEFAULT '',
        featured    TINYINT(1)   DEFAULT 0,
        status      ENUM('published','draft') DEFAULT 'published',
        order_num   INT          DEFAULT 0,
        created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

$categories = [
    'dev-web'    => ['label'=>'Dev Web',          'icon'=>'fa-laptop-code',   'color'=>'text-cyan-400'],
    'webview'    => ['label'=>'WebView',           'icon'=>'fa-mobile-alt',    'color'=>'text-blue-400'],
    'ia-chatbot' => ['label'=>'IA & Chatbot',      'icon'=>'fa-robot',         'color'=>'text-purple-400'],
    'whatsapp'   => ['label'=>'WhatsApp',          'icon'=>'fa-comment-dots',  'color'=>'text-green-400'],
    'gestion'    => ['label'=>'Gestion',           'icon'=>'fa-chart-bar',     'color'=>'text-yellow-400'],
    'suivi'      => ['label'=>'Suivi & Tracking',  'icon'=>'fa-map-marker-alt','color'=>'text-orange-400'],
    'design'     => ['label'=>'Design',            'icon'=>'fa-palette',       'color'=>'text-pink-400'],
    'securite'   => ['label'=>'Sécurité',          'icon'=>'fa-shield-alt',    'color'=>'text-red-400'],
];

$filter   = 'all';
$search   = '';
$projects = [];
$total = $featured = $published = 0;

if (!$_vconn_error) {
    $filter = isset($_GET['cat']) ? $vconn->real_escape_string($_GET['cat']) : 'all';
    $search = isset($_GET['q'])   ? $vconn->real_escape_string($_GET['q'])   : '';
    $where  = '1=1';
    if ($filter !== 'all' && array_key_exists($filter, $categories)) $where .= " AND category='$filter'";
    if ($search) $where .= " AND (title LIKE '%$search%' OR client LIKE '%$search%' OR tags LIKE '%$search%')";
    $rows = $vconn->query("SELECT * FROM portfolio_projects WHERE $where ORDER BY order_num ASC, created_at DESC");
    $projects = $rows ? $rows->fetch_all(MYSQLI_ASSOC) : [];

    $rTotal    = $vconn->query("SELECT COUNT(*) c FROM portfolio_projects");
    $rFeatured = $vconn->query("SELECT COUNT(*) c FROM portfolio_projects WHERE featured=1");
    $rPublished= $vconn->query("SELECT COUNT(*) c FROM portfolio_projects WHERE status='published'");
    $total     = $rTotal     ? (int)$rTotal->fetch_assoc()['c']     : 0;
    $featured  = $rFeatured  ? (int)$rFeatured->fetch_assoc()['c']  : 0;
    $published = $rPublished ? (int)$rPublished->fetch_assoc()['c'] : 0;
}

require_once 'includes/header.php';
?>

<!-- Main content -->
<div>

<?php if ($_vconn_error): ?>
<div class="bg-red-100 border border-red-400 text-red-800 px-4 py-3 rounded mb-6">
    <strong>Erreur connexion base vitrine :</strong> <?= htmlspecialchars($_vconn_error) ?>
</div>
<?php endif; ?>

    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-bold text-white">Portfolio</h1>
            <p class="text-gray-400 text-sm mt-1">Gérez vos projets et réalisations affichés sur le site</p>
        </div>
        <button onclick="openAddModal()"
                class="flex items-center gap-2 px-5 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-medium transition-colors">
            <i class="fas fa-plus"></i> Nouveau projet
        </button>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <?php foreach ([
            ['Total projets',    $total,     'fa-layer-group',   'bg-blue-500/10',   'text-blue-400'],
            ['Publiés',          $published, 'fa-eye',           'bg-green-500/10',  'text-green-400'],
            ['Mis en avant',     $featured,  'fa-star',          'bg-yellow-500/10', 'text-yellow-400'],
            ['Brouillons',       $total-$published,'fa-edit',    'bg-gray-500/10',   'text-gray-400'],
        ] as $s): ?>
        <div class="rounded-xl p-4 <?= $s[3] ?> border border-gray-700">
            <div class="flex items-center gap-3">
                <i class="fas <?= $s[2] ?> <?= $s[4] ?>"></i>
                <div>
                    <div class="text-xl font-bold text-white"><?= $s[1] ?></div>
                    <div class="text-xs text-gray-400"><?= $s[0] ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Filters + search -->
    <div class="flex flex-col md:flex-row gap-3 mb-6">
        <form method="GET" class="flex flex-wrap gap-2 flex-1">
            <select name="cat" onchange="this.form.submit()" class="px-3 py-2 rounded-lg text-sm bg-gray-800 border border-gray-700 text-gray-300">
                <option value="all" <?= $filter==='all'?'selected':'' ?>>Toutes catégories</option>
                <?php foreach ($categories as $key => $cat): ?>
                <option value="<?= $key ?>" <?= $filter===$key?'selected':'' ?>><?= $cat['label'] ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Rechercher..."
                   class="flex-1 min-w-48 px-3 py-2 rounded-lg text-sm bg-gray-800 border border-gray-700 text-gray-300 placeholder-gray-600">
            <button type="submit" class="px-4 py-2 rounded-lg bg-gray-700 hover:bg-gray-600 text-white text-sm transition-colors">
                <i class="fas fa-search"></i>
            </button>
            <?php if ($search || $filter !== 'all'): ?>
            <a href="portfolio.php" class="px-4 py-2 rounded-lg bg-gray-700 hover:bg-gray-600 text-white text-sm transition-colors">
                <i class="fas fa-times"></i>
            </a>
            <?php endif; ?>
        </form>
        <a href="../portfolio.php" target="_blank" class="flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-700 text-gray-400 hover:text-white hover:border-gray-500 text-sm transition-colors">
            <i class="fas fa-external-link-alt"></i> Voir le portfolio
        </a>
    </div>

    <!-- Table -->
    <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
        <?php if (empty($projects)): ?>
        <div class="text-center py-16 text-gray-500">
            <i class="fas fa-folder-open text-4xl mb-3 block"></i>
            Aucun projet trouvé.
            <button onclick="openAddModal()" class="block mx-auto mt-4 px-4 py-2 rounded-lg bg-blue-600 text-white text-sm hover:bg-blue-700">
                Créer le premier projet
            </button>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-700">
                        <th class="text-left px-4 py-3 text-gray-400 font-medium">Projet</th>
                        <th class="text-left px-4 py-3 text-gray-400 font-medium hidden md:table-cell">Catégorie</th>
                        <th class="text-left px-4 py-3 text-gray-400 font-medium hidden lg:table-cell">Client</th>
                        <th class="text-center px-4 py-3 text-gray-400 font-medium">Featured</th>
                        <th class="text-center px-4 py-3 text-gray-400 font-medium">Statut</th>
                        <th class="text-right px-4 py-3 text-gray-400 font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700" id="projects-table">
                <?php foreach ($projects as $p):
                    $cat = $categories[$p['category']] ?? ['label'=>$p['category'],'icon'=>'fa-folder','color'=>'text-gray-400'];
                ?>
                <tr class="hover:bg-gray-750 transition-colors group" id="row-<?= $p['id'] ?>">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <?php if ($p['image']): ?>
                            <img src="../<?= htmlspecialchars($p['image']) ?>" class="w-10 h-10 rounded-lg object-cover flex-shrink-0">
                            <?php else: ?>
                            <div class="w-10 h-10 rounded-lg bg-gray-700 flex items-center justify-center flex-shrink-0">
                                <i class="fas <?= $cat['icon'] ?> text-xs <?= $cat['color'] ?>"></i>
                            </div>
                            <?php endif; ?>
                            <div>
                                <div class="font-medium text-white"><?= htmlspecialchars($p['title']) ?></div>
                                <div class="text-xs text-gray-500 truncate max-w-xs"><?= htmlspecialchars(substr($p['short_desc'], 0, 60)) ?>...</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 hidden md:table-cell">
                        <span class="flex items-center gap-1.5 text-xs font-medium <?= $cat['color'] ?>">
                            <i class="fas <?= $cat['icon'] ?> text-xs"></i> <?= $cat['label'] ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-gray-400 text-xs hidden lg:table-cell">
                        <?= $p['client'] ? htmlspecialchars($p['client']) : '<span class="text-gray-600">—</span>' ?>
                        <?= $p['year']   ? " <span class='text-gray-600'>({$p['year']})</span>" : '' ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <button onclick="toggleFeatured(<?= $p['id'] ?>, <?= $p['featured'] ? 0 : 1 ?>)"
                                class="w-8 h-8 rounded-full transition-all <?= $p['featured'] ? 'text-yellow-400 bg-yellow-400/10' : 'text-gray-600 hover:text-yellow-400' ?>"
                                title="<?= $p['featured'] ? 'Retirer des favoris' : 'Mettre en avant' ?>">
                            <i class="fas fa-star text-sm"></i>
                        </button>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <button onclick="toggleStatus(<?= $p['id'] ?>, '<?= $p['status'] === 'published' ? 'draft' : 'published' ?>')"
                                class="px-2.5 py-1 rounded-full text-xs font-medium transition-all <?= $p['status'] === 'published' ? 'bg-green-500/15 text-green-400 border border-green-500/30' : 'bg-gray-700 text-gray-400 border border-gray-600' ?>">
                            <?= $p['status'] === 'published' ? 'Publié' : 'Brouillon' ?>
                        </button>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1">
                            <?php if ($p['live_url']): ?>
                            <a href="<?= htmlspecialchars($p['live_url']) ?>" target="_blank"
                               class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:text-white hover:bg-gray-700 transition-all" title="Voir en ligne">
                                <i class="fas fa-external-link-alt text-xs"></i>
                            </a>
                            <?php endif; ?>
                            <button onclick="openEditModal(<?= $p['id'] ?>)"
                                    class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:text-blue-400 hover:bg-blue-400/10 transition-all" title="Modifier">
                                <i class="fas fa-edit text-xs"></i>
                            </button>
                            <button onclick="deleteProject(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['title'])) ?>')"
                                    class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:text-red-400 hover:bg-red-400/10 transition-all" title="Supprimer">
                                <i class="fas fa-trash text-xs"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add / Edit Modal -->
<div id="project-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/70">
    <div class="bg-gray-900 border border-gray-700 rounded-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-2xl">
        <div class="flex items-center justify-between p-6 border-b border-gray-700">
            <h2 id="modal-title" class="text-lg font-bold text-white">Nouveau projet</h2>
            <button onclick="closeModal()" class="text-gray-400 hover:text-white w-8 h-8 rounded-lg bg-gray-700 hover:bg-gray-600 flex items-center justify-center">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>
        <form id="project-form" enctype="multipart/form-data" class="p-6 space-y-5">
            <input type="hidden" id="pf-id" name="id" value="">
            <input type="hidden" id="pf-action" name="action" value="create">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <!-- Titre -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-300 mb-1.5">Titre du projet <span class="text-red-400">*</span></label>
                    <input type="text" id="pf-title" name="title" required
                           class="w-full px-3 py-2.5 rounded-lg bg-gray-800 border border-gray-700 text-white placeholder-gray-600 focus:outline-none focus:border-blue-500 text-sm">
                </div>
                <!-- Catégorie -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1.5">Catégorie <span class="text-red-400">*</span></label>
                    <select id="pf-category" name="category" class="w-full px-3 py-2.5 rounded-lg bg-gray-800 border border-gray-700 text-white focus:outline-none focus:border-blue-500 text-sm">
                        <?php foreach ($categories as $key => $cat): ?>
                        <option value="<?= $key ?>"><?= $cat['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Année -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1.5">Année</label>
                    <input type="number" id="pf-year" name="year" min="2015" max="2030" placeholder="2025"
                           class="w-full px-3 py-2.5 rounded-lg bg-gray-800 border border-gray-700 text-white placeholder-gray-600 focus:outline-none focus:border-blue-500 text-sm">
                </div>
                <!-- Client -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1.5">Client</label>
                    <input type="text" id="pf-client" name="client" placeholder="Nom du client ou secteur"
                           class="w-full px-3 py-2.5 rounded-lg bg-gray-800 border border-gray-700 text-white placeholder-gray-600 focus:outline-none focus:border-blue-500 text-sm">
                </div>
                <!-- Lien live -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1.5">Lien live</label>
                    <input type="url" id="pf-live-url" name="live_url" placeholder="https://..."
                           class="w-full px-3 py-2.5 rounded-lg bg-gray-800 border border-gray-700 text-white placeholder-gray-600 focus:outline-none focus:border-blue-500 text-sm">
                </div>
            </div>

            <!-- Description courte -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1.5">Description courte <span class="text-red-400">*</span></label>
                <textarea id="pf-short-desc" name="short_desc" rows="2" required
                          class="w-full px-3 py-2.5 rounded-lg bg-gray-800 border border-gray-700 text-white placeholder-gray-600 focus:outline-none focus:border-blue-500 text-sm resize-none"
                          placeholder="Résumé accrocheur (1-2 phrases, affiché dans la carte)"></textarea>
            </div>

            <!-- Description complète -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1.5">Description complète</label>
                <textarea id="pf-full-desc" name="full_desc" rows="4"
                          class="w-full px-3 py-2.5 rounded-lg bg-gray-800 border border-gray-700 text-white placeholder-gray-600 focus:outline-none focus:border-blue-500 text-sm resize-none"
                          placeholder="Description détaillée affichée dans la modale (contexte, défis, solutions, résultats...)"></textarea>
            </div>

            <!-- Tags -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1.5">Technologies / Tags</label>
                <input type="text" id="pf-tags" name="tags" placeholder="PHP, MySQL, React, WhatsApp API (séparés par des virgules)"
                       class="w-full px-3 py-2.5 rounded-lg bg-gray-800 border border-gray-700 text-white placeholder-gray-600 focus:outline-none focus:border-blue-500 text-sm">
            </div>

            <!-- Image + options -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1.5">Image principale</label>
                    <input type="file" id="pf-image" name="image" accept="image/*"
                           class="w-full text-sm text-gray-400 bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 file:mr-3 file:py-1 file:px-3 file:rounded file:border-0 file:bg-blue-600 file:text-white file:text-xs hover:file:bg-blue-700">
                    <p class="text-xs text-gray-600 mt-1">JPG, PNG, WEBP — max 3MB</p>
                    <div id="current-image-preview" class="hidden mt-2">
                        <img id="img-preview" src="" alt="" class="w-20 h-14 object-cover rounded border border-gray-600">
                    </div>
                </div>
                <div class="flex flex-col gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1.5">Ordre d'affichage</label>
                        <input type="number" id="pf-order" name="order_num" min="0" value="0"
                               class="w-full px-3 py-2.5 rounded-lg bg-gray-800 border border-gray-700 text-white focus:outline-none focus:border-blue-500 text-sm">
                    </div>
                    <div class="flex flex-col gap-3">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" id="pf-featured" name="featured" value="1"
                                   class="w-4 h-4 rounded accent-yellow-400">
                            <span class="text-sm text-gray-300"><i class="fas fa-star text-yellow-400 mr-1"></i>Mettre en avant</span>
                        </label>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="radio" name="status" value="published" id="status-pub" checked class="accent-green-400">
                            <span class="text-sm text-gray-300 text-green-400">Publié</span>
                        </label>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="radio" name="status" value="draft" id="status-draft" class="accent-gray-400">
                            <span class="text-sm text-gray-400">Brouillon</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeModal()" class="px-5 py-2.5 rounded-lg bg-gray-700 hover:bg-gray-600 text-white text-sm transition-colors">Annuler</button>
                <button type="submit" id="submit-btn" class="px-5 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium transition-colors">
                    <i class="fas fa-save mr-1"></i> <span id="submit-label">Créer</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete confirm -->
<div id="delete-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70">
    <div class="bg-gray-900 border border-gray-700 rounded-xl p-6 w-full max-w-sm text-center shadow-2xl">
        <div class="w-14 h-14 rounded-full bg-red-500/10 border border-red-500/30 flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-trash text-red-400 text-xl"></i>
        </div>
        <h3 class="text-white font-bold text-lg mb-2">Supprimer ce projet ?</h3>
        <p class="text-gray-400 text-sm mb-6" id="delete-name"></p>
        <div class="flex gap-3">
            <button onclick="closeDeleteModal()" class="flex-1 py-2.5 rounded-lg bg-gray-700 hover:bg-gray-600 text-white text-sm">Annuler</button>
            <button onclick="confirmDelete()" class="flex-1 py-2.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-medium">Supprimer</button>
        </div>
    </div>
</div>

<!-- Toast -->
<div id="admin-toast" class="fixed top-5 right-5 z-[100] hidden">
    <div id="toast-msg" class="px-5 py-3 rounded-xl text-white text-sm font-medium shadow-xl border"></div>
</div>

<script>
let deleteId = null;

// ── Toast ──────────────────────────────────────────────────────────
function toast(msg, ok=true) {
    const t = document.getElementById('admin-toast');
    const m = document.getElementById('toast-msg');
    m.textContent = msg;
    m.className = 'px-5 py-3 rounded-xl text-white text-sm font-medium shadow-xl border ' +
        (ok ? 'bg-green-600 border-green-500' : 'bg-red-600 border-red-500');
    t.classList.remove('hidden');
    setTimeout(() => t.classList.add('hidden'), 3500);
}

// ── Modal helpers ──────────────────────────────────────────────────
function closeModal() {
    document.getElementById('project-modal').classList.replace('flex','hidden');
    document.getElementById('project-form').reset();
    document.getElementById('current-image-preview').classList.add('hidden');
}

function openAddModal() {
    document.getElementById('modal-title').textContent  = 'Nouveau projet';
    document.getElementById('submit-label').textContent = 'Créer';
    document.getElementById('pf-id').value     = '';
    document.getElementById('pf-action').value = 'create';
    document.getElementById('project-form').reset();
    document.getElementById('current-image-preview').classList.add('hidden');
    document.getElementById('project-modal').classList.replace('hidden','flex');
}

function openEditModal(id) {
    fetch('portfolio_handler.php?action=get&id=' + id)
        .then(r => r.json())
        .then(res => {
            if (!res.ok) { toast(res.msg, false); return; }
            const p = res.data;
            document.getElementById('modal-title').textContent  = 'Modifier le projet';
            document.getElementById('submit-label').textContent = 'Enregistrer';
            document.getElementById('pf-id').value       = p.id;
            document.getElementById('pf-action').value   = 'update';
            document.getElementById('pf-title').value    = p.title;
            document.getElementById('pf-category').value = p.category;
            document.getElementById('pf-year').value     = p.year || '';
            document.getElementById('pf-client').value   = p.client || '';
            document.getElementById('pf-live-url').value = p.live_url || '';
            document.getElementById('pf-short-desc').value = p.short_desc;
            document.getElementById('pf-full-desc').value  = p.full_desc || '';
            document.getElementById('pf-tags').value     = p.tags || '';
            document.getElementById('pf-order').value    = p.order_num;
            document.getElementById('pf-featured').checked = p.featured == 1;
            document.getElementById(p.status === 'published' ? 'status-pub' : 'status-draft').checked = true;
            if (p.image) {
                document.getElementById('img-preview').src = '../' + p.image;
                document.getElementById('current-image-preview').classList.remove('hidden');
            } else {
                document.getElementById('current-image-preview').classList.add('hidden');
            }
            document.getElementById('project-modal').classList.replace('hidden','flex');
        });
}

// ── Form submit ────────────────────────────────────────────────────
document.getElementById('project-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn  = document.getElementById('submit-btn');
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Enregistrement...';
    btn.disabled = true;

    const fd = new FormData(this);
    fetch('portfolio_handler.php', { method:'POST', body:fd })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                toast(res.msg);
                closeModal();
                setTimeout(() => location.reload(), 700);
            } else {
                toast(res.msg, false);
                btn.innerHTML = orig;
                btn.disabled  = false;
            }
        })
        .catch(() => { toast('Erreur réseau', false); btn.innerHTML = orig; btn.disabled = false; });
});

// ── Featured ───────────────────────────────────────────────────────
function toggleFeatured(id, val) {
    fetch('portfolio_handler.php', {
        method:'POST',
        body: new URLSearchParams({action:'toggle_featured', id, val})
    }).then(r => r.json()).then(res => {
        if (res.ok) { toast(val ? 'Mis en avant' : 'Retiré des favoris'); setTimeout(() => location.reload(), 600); }
    });
}

// ── Status ─────────────────────────────────────────────────────────
function toggleStatus(id, val) {
    fetch('portfolio_handler.php', {
        method:'POST',
        body: new URLSearchParams({action:'toggle_status', id, val})
    }).then(r => r.json()).then(res => {
        if (res.ok) { toast(val === 'published' ? 'Publié' : 'Mis en brouillon'); setTimeout(() => location.reload(), 600); }
    });
}

// ── Delete ─────────────────────────────────────────────────────────
function deleteProject(id, title) {
    deleteId = id;
    document.getElementById('delete-name').textContent = title;
    document.getElementById('delete-modal').classList.replace('hidden','flex');
}
function closeDeleteModal() {
    document.getElementById('delete-modal').classList.replace('flex','hidden');
    deleteId = null;
}
function confirmDelete() {
    if (!deleteId) return;
    fetch('portfolio_handler.php', {
        method:'POST',
        body: new URLSearchParams({action:'delete', id:deleteId})
    }).then(r => r.json()).then(res => {
        if (res.ok) {
            toast('Projet supprimé');
            document.getElementById('row-' + deleteId)?.remove();
        } else {
            toast(res.msg, false);
        }
        closeDeleteModal();
    });
}

// Close on backdrop
document.getElementById('project-modal').addEventListener('click', e => { if(e.target === e.currentTarget) closeModal(); });
document.getElementById('delete-modal').addEventListener('click',  e => { if(e.target === e.currentTarget) closeDeleteModal(); });
</script>

<?php require_once 'includes/footer.php'; ?>
