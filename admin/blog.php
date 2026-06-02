<?php
mysqli_report(MYSQLI_REPORT_OFF); // PHP 8.1+ throws exceptions on query failure — disable
$page_title = 'Gestion du Blog';

// ── DB connection BEFORE header so errors don't get swallowed by ob_start ──
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
    $vconn->query("CREATE TABLE IF NOT EXISTS blog_categories (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(100) NOT NULL,
        slug       VARCHAR(110) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $vconn->query("CREATE TABLE IF NOT EXISTS blog_posts (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        category_id INT DEFAULT NULL,
        title       VARCHAR(255) NOT NULL,
        slug        VARCHAR(270) NOT NULL UNIQUE,
        excerpt     TEXT,
        content     LONGTEXT,
        image       VARCHAR(500) DEFAULT NULL,
        author      VARCHAR(100) DEFAULT 'Netcrafter',
        status      ENUM('draft','published') DEFAULT 'published',
        views       INT DEFAULT 0,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// ── Actions (POST → redirect to avoid re-submit) ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$_vconn_error) {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $pid    = intval($_POST['id'] ?? 0);
        $title  = trim($vconn->real_escape_string($_POST['title']      ?? ''));
        $cat_id = intval($_POST['category_id'] ?? 0) ?: null;
        $excerpt= trim($vconn->real_escape_string($_POST['excerpt']    ?? ''));
        $content= trim($vconn->real_escape_string($_POST['content']    ?? ''));
        $image  = trim($vconn->real_escape_string($_POST['image']      ?? ''));
        $author = trim($vconn->real_escape_string($_POST['author']     ?? 'Netcrafter')) ?: 'Netcrafter';
        $status = in_array($_POST['status'] ?? '', ['draft','published']) ? $_POST['status'] : 'published';
        if (!$title) {
            header("Location: blog.php?err=titre_requis" . ($pid ? "&edit=$pid" : "")); exit;
        }
        $base = generateSlug($title); $slug = $base; $n = 1;
        if ($pid) {
            $vconn->query("UPDATE blog_posts SET
                title='$title', category_id=".($cat_id?:'NULL').",
                excerpt='$excerpt', content='$content', image='$image',
                author='$author', status='$status'
                WHERE id=$pid");
            header("Location: blog.php?updated=1"); exit;
        } else {
            $r = $vconn->query("SELECT id FROM blog_posts WHERE slug='$slug'");
            while ($r && $r->num_rows > 0) { $slug = $base.'-'.$n++; $r = $vconn->query("SELECT id FROM blog_posts WHERE slug='$slug'"); }
            $vconn->query("INSERT INTO blog_posts (title,slug,category_id,excerpt,content,image,author,status)
                VALUES ('$title','$slug',".($cat_id?:'NULL').",'$excerpt','$content','$image','$author','$status')");
            header("Location: blog.php?created=1"); exit;
        }
    }

    if ($action === 'delete') {
        $pid = intval($_POST['id'] ?? 0);
        if ($pid) $vconn->query("DELETE FROM blog_posts WHERE id=$pid");
        header("Location: blog.php?deleted=1"); exit;
    }

    if ($action === 'toggle_status') {
        $pid = intval($_POST['id'] ?? 0);
        if ($pid) {
            $r = $vconn->query("SELECT status FROM blog_posts WHERE id=$pid");
            $cur = ($r && ($row = $r->fetch_assoc())) ? $row['status'] : 'draft';
            $new = $cur === 'published' ? 'draft' : 'published';
            $vconn->query("UPDATE blog_posts SET status='$new' WHERE id=$pid");
        }
        header("Location: blog.php?updated=1"); exit;
    }

    if ($action === 'save_category') {
        $cn = trim($vconn->real_escape_string($_POST['cat_name'] ?? ''));
        $cs = generateSlug($cn);
        if ($cn) $vconn->query("INSERT IGNORE INTO blog_categories (name,slug) VALUES ('$cn','$cs')");
        header("Location: blog.php?cat_saved=1"); exit;
    }

    if ($action === 'delete_category') {
        $cid = intval($_POST['cat_id'] ?? 0);
        if ($cid) $vconn->query("DELETE FROM blog_categories WHERE id=$cid");
        header("Location: blog.php?cat_deleted=1"); exit;
    }
}

// ── Data ────────────────────────────────────────────────────────────────────
$edit_post = null;
$posts = $categories = [];
$total = $published_c = $draft_c = $total_views = 0;

if (!$_vconn_error) {
    if (isset($_GET['edit'])) {
        $eid = intval($_GET['edit']);
        $r = $vconn->query("SELECT * FROM blog_posts WHERE id=$eid");
        if ($r) $edit_post = $r->fetch_assoc();
    }
    $_r = $vconn->query("SELECT COUNT(*) c FROM blog_posts");                            if ($_r) $total       = (int)$_r->fetch_assoc()['c'];
    $_r = $vconn->query("SELECT COUNT(*) c FROM blog_posts WHERE status='published'");   if ($_r) $published_c = (int)$_r->fetch_assoc()['c'];
    $_r = $vconn->query("SELECT COUNT(*) c FROM blog_posts WHERE status='draft'");       if ($_r) $draft_c     = (int)$_r->fetch_assoc()['c'];
    $_r = $vconn->query("SELECT COALESCE(SUM(views),0) v FROM blog_posts");              if ($_r) $total_views = (int)$_r->fetch_assoc()['v'];

    $_r = $vconn->query("SELECT p.*, c.name cat_name FROM blog_posts p
        LEFT JOIN blog_categories c ON c.id=p.category_id ORDER BY p.created_at DESC");
    if ($_r) $posts = $_r->fetch_all(MYSQLI_ASSOC);

    $_r = $vconn->query("SELECT * FROM blog_categories ORDER BY name");
    if ($_r) $categories = $_r->fetch_all(MYSQLI_ASSOC);
}

require_once 'includes/header.php';
?>

<!-- DB error banner -->
<?php if ($_vconn_error): ?>
<div class="mx-6 mt-4 p-4 bg-red-900/30 border border-red-700 text-red-300 rounded-xl text-sm">
    <i class="fas fa-exclamation-triangle mr-2"></i>
    Erreur de connexion base de données : <?= htmlspecialchars($_vconn_error) ?>
</div>
<?php endif; ?>

<div class="p-6">

    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-bold text-white">Blog</h1>
            <p class="text-gray-400 text-sm mt-1">Gérez vos articles et catégories</p>
        </div>
        <button onclick="openAddModal()"
                class="flex items-center gap-2 px-5 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-medium transition-colors text-sm">
            <i class="fas fa-plus"></i> Nouvel article
        </button>
    </div>

    <!-- Flash messages -->
    <?php if (isset($_GET['created'])): ?><div class="mb-6 p-3 rounded-xl bg-green-900/30 border border-green-700 text-green-300 text-sm"><i class="fas fa-check-circle mr-2"></i>Article créé avec succès.</div><?php endif; ?>
    <?php if (isset($_GET['updated'])): ?><div class="mb-6 p-3 rounded-xl bg-blue-900/30 border border-blue-700 text-blue-300 text-sm"><i class="fas fa-check-circle mr-2"></i>Modifications enregistrées.</div><?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?><div class="mb-6 p-3 rounded-xl bg-yellow-900/30 border border-yellow-700 text-yellow-300 text-sm"><i class="fas fa-check-circle mr-2"></i>Article supprimé.</div><?php endif; ?>
    <?php if (isset($_GET['cat_saved'])): ?><div class="mb-6 p-3 rounded-xl bg-green-900/30 border border-green-700 text-green-300 text-sm"><i class="fas fa-check-circle mr-2"></i>Catégorie créée.</div><?php endif; ?>
    <?php if (isset($_GET['err']) && $_GET['err'] === 'titre_requis'): ?><div class="mb-6 p-3 rounded-xl bg-red-900/30 border border-red-700 text-red-300 text-sm"><i class="fas fa-exclamation-circle mr-2"></i>Le titre est requis.</div><?php endif; ?>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <?php foreach ([
            ['Total articles',  $total,       'fa-newspaper',    'bg-blue-500/10',   'text-blue-400'],
            ['Publiés',         $published_c, 'fa-eye',          'bg-green-500/10',  'text-green-400'],
            ['Brouillons',      $draft_c,     'fa-edit',         'bg-gray-500/10',   'text-gray-400'],
            ['Vues totales',    number_format($total_views), 'fa-chart-line', 'bg-purple-500/10', 'text-purple-400'],
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

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

        <!-- Posts table -->
        <div class="lg:col-span-3">
            <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-700">
                    <h2 class="font-semibold text-white">Articles (<?= count($posts) ?>)</h2>
                </div>
                <?php if (empty($posts)): ?>
                <div class="text-center py-16 text-gray-500">
                    <i class="fas fa-newspaper text-4xl mb-3 block"></i>
                    Aucun article. Créez votre premier article.
                    <button onclick="openAddModal()" class="block mx-auto mt-4 px-4 py-2 rounded-lg bg-blue-600 text-white text-sm hover:bg-blue-700">
                        Créer un article
                    </button>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-700">
                                <th class="text-left px-4 py-3 text-gray-400 font-medium">Article</th>
                                <th class="text-left px-4 py-3 text-gray-400 font-medium hidden md:table-cell">Catégorie</th>
                                <th class="text-center px-4 py-3 text-gray-400 font-medium hidden sm:table-cell">Statut</th>
                                <th class="text-center px-4 py-3 text-gray-400 font-medium hidden lg:table-cell">Vues</th>
                                <th class="text-left px-4 py-3 text-gray-400 font-medium hidden lg:table-cell">Date</th>
                                <th class="text-right px-4 py-3 text-gray-400 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                        <?php foreach ($posts as $p): ?>
                        <tr class="hover:bg-gray-750 transition-colors group" id="row-<?= $p['id'] ?>">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <?php if ($p['image']): ?>
                                    <img src="../<?= htmlspecialchars($p['image']) ?>"
                                         class="w-10 h-10 rounded-lg object-cover flex-shrink-0"
                                         onerror="this.src='../image/oops.avif'">
                                    <?php else: ?>
                                    <div class="w-10 h-10 rounded-lg bg-gray-700 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-newspaper text-xs text-gray-400"></i>
                                    </div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="font-medium text-white"><?= htmlspecialchars(mb_substr($p['title'],0,55)) ?><?= mb_strlen($p['title'])>55?'…':'' ?></div>
                                        <div class="text-xs text-gray-500"><?= htmlspecialchars($p['author']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 hidden md:table-cell">
                                <span class="text-xs text-gray-400"><?= htmlspecialchars($p['cat_name'] ?? '—') ?></span>
                            </td>
                            <td class="px-4 py-3 text-center hidden sm:table-cell">
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <button type="submit"
                                            class="px-2.5 py-1 rounded-full text-xs font-medium transition-all <?= $p['status']==='published' ? 'bg-green-500/15 text-green-400 border border-green-500/30' : 'bg-gray-700 text-gray-400 border border-gray-600' ?>">
                                        <?= $p['status']==='published' ? 'Publié' : 'Brouillon' ?>
                                    </button>
                                </form>
                            </td>
                            <td class="px-4 py-3 text-center hidden lg:table-cell text-gray-400 text-xs">
                                <?= number_format($p['views']) ?>
                            </td>
                            <td class="px-4 py-3 hidden lg:table-cell text-xs text-gray-500">
                                <?= date('d/m/Y', strtotime($p['created_at'])) ?>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <button onclick="openEditModal(<?= $p['id'] ?>)"
                                            class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:text-blue-400 hover:bg-blue-400/10 transition-all" title="Modifier">
                                        <i class="fas fa-edit text-xs"></i>
                                    </button>
                                    <button onclick="deletePost(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['title'])) ?>')"
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

        <!-- Categories panel -->
        <div class="space-y-4">
            <div class="bg-gray-800 rounded-xl border border-gray-700 p-5">
                <h2 class="font-semibold text-white mb-4 flex items-center gap-2">
                    <i class="fas fa-tags text-blue-400 text-sm"></i> Catégories
                </h2>
                <form method="POST" class="flex gap-2 mb-4">
                    <input type="hidden" name="action" value="save_category">
                    <input type="text" name="cat_name" placeholder="Nom de catégorie"
                           class="flex-1 px-3 py-2 text-sm rounded-lg bg-gray-700 border border-gray-600 text-white placeholder-gray-500 focus:outline-none focus:border-blue-500">
                    <button type="submit" class="px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm transition-colors">
                        <i class="fas fa-plus"></i>
                    </button>
                </form>
                <?php if (empty($categories)): ?>
                <p class="text-xs text-gray-500">Aucune catégorie créée.</p>
                <?php else: ?>
                <ul class="space-y-2">
                    <?php foreach ($categories as $cat): ?>
                    <li class="flex items-center justify-between">
                        <span class="text-sm text-gray-300"><?= htmlspecialchars($cat['name']) ?></span>
                        <form method="POST" onsubmit="return confirm('Supprimer cette catégorie ?')">
                            <input type="hidden" name="action" value="delete_category">
                            <input type="hidden" name="cat_id" value="<?= $cat['id'] ?>">
                            <button type="submit" class="w-6 h-6 rounded flex items-center justify-center text-gray-500 hover:text-red-400 hover:bg-red-400/10 transition-all">
                                <i class="fas fa-times text-xs"></i>
                            </button>
                        </form>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>

            <!-- Quick tips -->
            <div class="bg-gray-800 rounded-xl border border-gray-700 p-5">
                <h3 class="font-semibold text-white mb-3 text-sm flex items-center gap-2">
                    <i class="fas fa-lightbulb text-yellow-400 text-xs"></i> Conseils
                </h3>
                <ul class="space-y-2 text-xs text-gray-500">
                    <li>• Cliquez sur <span class="text-green-400">Publié</span> / <span class="text-gray-400">Brouillon</span> pour basculer le statut.</li>
                    <li>• L'image peut être un chemin relatif ou une URL complète.</li>
                    <li>• Le contenu HTML est accepté.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- ══ Add / Edit Modal ══════════════════════════════════════════════════════ -->
<div id="post-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/70">
    <div class="bg-gray-900 border border-gray-700 rounded-xl w-full max-w-2xl max-h-[92vh] overflow-y-auto shadow-2xl">
        <div class="flex items-center justify-between p-6 border-b border-gray-700">
            <h2 id="modal-title" class="text-lg font-bold text-white">Nouvel article</h2>
            <button onclick="closeModal()" class="text-gray-400 hover:text-white w-8 h-8 rounded-lg bg-gray-700 hover:bg-gray-600 flex items-center justify-center">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>
        <form id="post-form" method="POST" class="p-6 space-y-4">
            <input type="hidden" id="pf-id"     name="id"     value="">
            <input type="hidden" id="pf-action" name="action" value="save">

            <!-- Title + Author -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-300 mb-1.5">Titre <span class="text-red-400">*</span></label>
                    <input type="text" id="pf-title" name="title" required
                           class="w-full px-3 py-2.5 rounded-lg bg-gray-800 border border-gray-700 text-white placeholder-gray-600 focus:outline-none focus:border-blue-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1.5">Auteur</label>
                    <input type="text" id="pf-author" name="author" value="Netcrafter"
                           class="w-full px-3 py-2.5 rounded-lg bg-gray-800 border border-gray-700 text-white placeholder-gray-600 focus:outline-none focus:border-blue-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1.5">Catégorie</label>
                    <select id="pf-category" name="category_id"
                            class="w-full px-3 py-2.5 rounded-lg bg-gray-800 border border-gray-700 text-white focus:outline-none focus:border-blue-500 text-sm">
                        <option value="">— Aucune —</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Status + Image -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1.5">Statut</label>
                    <select id="pf-status" name="status"
                            class="w-full px-3 py-2.5 rounded-lg bg-gray-800 border border-gray-700 text-white focus:outline-none focus:border-blue-500 text-sm">
                        <option value="published">Publié</option>
                        <option value="draft">Brouillon</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1.5">Image (chemin ou URL)</label>
                    <input type="text" id="pf-image" name="image" placeholder="image/blog/article.jpg"
                           class="w-full px-3 py-2.5 rounded-lg bg-gray-800 border border-gray-700 text-white placeholder-gray-600 focus:outline-none focus:border-blue-500 text-sm">
                </div>
            </div>

            <!-- Excerpt -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1.5">Résumé</label>
                <textarea id="pf-excerpt" name="excerpt" rows="2"
                          class="w-full px-3 py-2.5 rounded-lg bg-gray-800 border border-gray-700 text-white placeholder-gray-600 focus:outline-none focus:border-blue-500 text-sm resize-none"
                          placeholder="Court résumé affiché en aperçu…"></textarea>
            </div>

            <!-- Content -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1.5">Contenu <span class="text-xs text-gray-500">(HTML accepté)</span></label>
                <textarea id="pf-content" name="content" rows="10"
                          class="w-full px-3 py-2.5 rounded-lg bg-gray-800 border border-gray-700 text-white placeholder-gray-600 focus:outline-none focus:border-blue-500 text-sm font-mono"
                          placeholder="<h2>Titre</h2><p>Votre contenu…</p>"></textarea>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeModal()"
                        class="px-5 py-2.5 rounded-lg bg-gray-700 hover:bg-gray-600 text-white text-sm transition-colors">Annuler</button>
                <button type="submit" id="submit-btn"
                        class="px-5 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium transition-colors">
                    <i class="fas fa-save mr-1"></i> <span id="submit-label">Créer</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══ Delete confirm modal ══════════════════════════════════════════════════ -->
<div id="delete-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70">
    <div class="bg-gray-900 border border-gray-700 rounded-xl p-6 w-full max-w-sm text-center shadow-2xl">
        <div class="w-14 h-14 rounded-full bg-red-500/10 border border-red-500/30 flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-trash text-red-400 text-xl"></i>
        </div>
        <h3 class="text-white font-bold text-lg mb-2">Supprimer cet article ?</h3>
        <p class="text-gray-400 text-sm mb-6" id="delete-name"></p>
        <form id="delete-form" method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" id="delete-id" name="id" value="">
            <div class="flex gap-3">
                <button type="button" onclick="closeDeleteModal()"
                        class="flex-1 py-2.5 rounded-lg bg-gray-700 hover:bg-gray-600 text-white text-sm">Annuler</button>
                <button type="submit"
                        class="flex-1 py-2.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-medium">Supprimer</button>
            </div>
        </form>
    </div>
</div>

<!-- ══ posts data for JS ═════════════════════════════════════════════════════ -->
<script>
const POSTS_DATA = <?= json_encode(array_column($posts, null, 'id'), JSON_UNESCAPED_UNICODE) ?>;

function openAddModal() {
    document.getElementById('modal-title').textContent  = 'Nouvel article';
    document.getElementById('submit-label').textContent = 'Créer';
    document.getElementById('post-form').reset();
    document.getElementById('pf-id').value     = '';
    document.getElementById('pf-action').value = 'save';
    document.getElementById('post-modal').classList.replace('hidden','flex');
}

function openEditModal(id) {
    const p = POSTS_DATA[id];
    if (!p) return;
    document.getElementById('modal-title').textContent  = 'Modifier l\'article';
    document.getElementById('submit-label').textContent = 'Enregistrer';
    document.getElementById('pf-id').value       = p.id;
    document.getElementById('pf-action').value   = 'save';
    document.getElementById('pf-title').value    = p.title || '';
    document.getElementById('pf-author').value   = p.author || 'Netcrafter';
    document.getElementById('pf-category').value = p.category_id || '';
    document.getElementById('pf-status').value   = p.status || 'published';
    document.getElementById('pf-image').value    = p.image || '';
    document.getElementById('pf-excerpt').value  = p.excerpt || '';
    document.getElementById('pf-content').value  = p.content || '';
    document.getElementById('post-modal').classList.replace('hidden','flex');
}

function closeModal() {
    document.getElementById('post-modal').classList.replace('flex','hidden');
}

function deletePost(id, title) {
    document.getElementById('delete-name').textContent = title;
    document.getElementById('delete-id').value = id;
    document.getElementById('delete-modal').classList.replace('hidden','flex');
}

function closeDeleteModal() {
    document.getElementById('delete-modal').classList.replace('flex','hidden');
}

// Close on backdrop
document.getElementById('post-modal').addEventListener('click',   e => { if(e.target===e.currentTarget) closeModal(); });
document.getElementById('delete-modal').addEventListener('click',  e => { if(e.target===e.currentTarget) closeDeleteModal(); });

<?php if ($edit_post): ?>
openEditModal(<?= $edit_post['id'] ?>);
<?php endif; ?>
</script>

<?php require_once 'includes/footer.php'; ?>
