<?php
$page_title = "Catégories de formations";
require_once 'includes/header.php';

$f_cfg = ['localhost', 'root', '', 'netcrafter_formation'];
$_ch = $_SERVER['HTTP_HOST'] ?? '';
if (PHP_OS_FAMILY !== 'Windows' && strpos($_ch,'localhost')===false && strpos($_ch,'127.0.0.1')===false && strpos($_ch,'::1')===false) {
    $f_cfg = ['localhost', 'u264396140_formation', 'Hondaand@1', 'u264396140_formation'];
}
$fconn = new mysqli($f_cfg[0], $f_cfg[1], $f_cfg[2], $f_cfg[3]);
if ($fconn->connect_error) { die("Erreur DB formation: " . $fconn->connect_error); }
$fconn->set_charset("utf8");

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name   = trim($_POST['name'] ?? '');
        $icon   = trim($_POST['icon'] ?? 'fas fa-book');
        $status = $_POST['status'] ?? 'active';
        if (empty($name)) {
            $errors[] = "Le nom est requis.";
        } else {
            $st = $fconn->prepare("INSERT INTO formation_categories (name, icon, status, created_at) VALUES (?, ?, ?, NOW())");
            $st->bind_param("sss", $name, $icon, $status);
            if ($st->execute()) $success = "Catégorie ajoutée.";
            else $errors[] = "Erreur: " . $fconn->error;
        }
    }
    elseif ($action === 'delete') {
        $cid = intval($_POST['cat_id'] ?? 0);
        if ($cid > 0) {
            $count = $fconn->query("SELECT COUNT(*) c FROM formations WHERE category_id=$cid")->fetch_assoc()['c'];
            if ($count > 0) {
                $errors[] = "Impossible de supprimer : $count formation(s) utilisent cette catégorie.";
            } else {
                $fconn->query("DELETE FROM formation_categories WHERE id=$cid");
                $success = "Catégorie supprimée.";
            }
        }
    }
    elseif ($action === 'toggle') {
        $cid = intval($_POST['cat_id'] ?? 0);
        $cur = $fconn->query("SELECT status FROM formation_categories WHERE id=$cid")->fetch_assoc()['status'];
        $new = $cur === 'active' ? 'inactive' : 'active';
        $fconn->query("UPDATE formation_categories SET status='$new' WHERE id=$cid");
        $success = "Statut mis à jour.";
    }
}

$cats = [];
$r = $fconn->query("SELECT fc.*, (SELECT COUNT(*) FROM formations WHERE category_id=fc.id) f_count FROM formation_categories fc ORDER BY fc.name");
while ($row = $r->fetch_assoc()) $cats[] = $row;
?>

<div class="p-6">
    <div class="flex items-center gap-3 mb-6">
        <a href="formations.php" class="text-gray-400 hover:text-gray-600"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Catégories de formations</h1>
            <p class="text-sm text-gray-500"><?= count($cats) ?> catégorie(s)</p>
        </div>
    </div>

    <?php if ($success): ?><div class="mb-4 p-3 bg-green-100 text-green-800 rounded-lg text-sm"><?= $success ?></div><?php endif; ?>
    <?php if (!empty($errors)): ?><div class="mb-4 p-3 bg-red-100 text-red-800 rounded-lg text-sm"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div><?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Add Form -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Ajouter une catégorie</h2>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nom <span class="text-red-500">*</span></label>
                    <input type="text" name="name" required
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Ex: Développement Web">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Icône FontAwesome</label>
                    <input type="text" name="icon" value="fas fa-book"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Ex: fas fa-code">
                    <p class="text-xs text-gray-400 mt-1">Classe FontAwesome (fas fa-code, fas fa-shield-alt...)</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Statut</label>
                    <select name="status" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="active">Actif</option>
                        <option value="inactive">Inactif</option>
                    </select>
                </div>
                <button type="submit" class="w-full py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">
                    <i class="fas fa-plus mr-1"></i> Ajouter
                </button>
            </form>
        </div>

        <!-- Categories List -->
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden border border-gray-100 dark:border-gray-700">
                <?php if (empty($cats)): ?>
                <div class="p-12 text-center text-gray-400">
                    <i class="fas fa-tags text-3xl mb-3"></i>
                    <p>Aucune catégorie</p>
                </div>
                <?php else: ?>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300 text-xs uppercase">
                        <tr>
                            <th class="px-4 py-3 text-left">Catégorie</th>
                            <th class="px-4 py-3 text-center">Icône</th>
                            <th class="px-4 py-3 text-center">Formations</th>
                            <th class="px-4 py-3 text-center">Statut</th>
                            <th class="px-4 py-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    <?php foreach ($cats as $c): ?>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($c['name']) ?></td>
                        <td class="px-4 py-3 text-center text-gray-500"><i class="<?= htmlspecialchars($c['icon'] ?? 'fas fa-book') ?>"></i></td>
                        <td class="px-4 py-3 text-center text-gray-600 dark:text-gray-300"><?= $c['f_count'] ?></td>
                        <td class="px-4 py-3 text-center">
                            <?php if ($c['status'] === 'active'): ?>
                            <span class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-800">Actif</span>
                            <?php else: ?>
                            <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600">Inactif</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-2">
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="cat_id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="text-yellow-500 hover:text-yellow-700" title="Changer statut"><i class="fas fa-toggle-on"></i></button>
                                </form>
                                <form method="POST" class="inline" onsubmit="return confirm('Supprimer cette catégorie ?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="cat_id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="text-red-500 hover:text-red-700" title="Supprimer"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php $fconn->close(); require_once 'includes/footer.php'; ?>
