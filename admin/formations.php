<?php
mysqli_report(MYSQLI_REPORT_OFF);
$page_title = "Gestion des formations";
require_once 'includes/header.php';

// Connexion à la base de données formations
$f_cfg = ['localhost', 'root', '', 'netcrafter_formation'];
$_fh = $_SERVER['HTTP_HOST'] ?? '';
if (PHP_OS_FAMILY !== 'Windows' && strpos($_fh,'localhost')===false && strpos($_fh,'127.0.0.1')===false && strpos($_fh,'::1')===false) {
    $f_cfg = ['localhost', 'u264396140_formation', 'Hondaand@1', 'u264396140_formation'];
}
$fconn = new mysqli($f_cfg[0], $f_cfg[1], $f_cfg[2], $f_cfg[3]);
if ($fconn->connect_error) { die("Erreur DB formation: " . $fconn->connect_error); }
$fconn->set_charset("utf8");

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $fid = intval($_POST['formation_id'] ?? 0);

    if ($action === 'delete' && $fid > 0) {
        $fconn->query("DELETE FROM formation_videos WHERE module_id IN (SELECT id FROM formation_modules WHERE formation_id = $fid)");
        $fconn->query("DELETE FROM formation_modules WHERE formation_id = $fid");
        $fconn->query("DELETE FROM formation_subscriptions WHERE formation_id = $fid");
        $fconn->query("DELETE FROM formation_favorites WHERE formation_id = $fid");
        $fconn->query("DELETE FROM formations WHERE id = $fid");
        header("Location: formations.php?deleted=1"); exit;
    }
    if ($action === 'toggle_status' && $fid > 0) {
        $cur = $fconn->query("SELECT status FROM formations WHERE id = $fid")->fetch_assoc()['status'];
        $new = ($cur === 'active') ? 'inactive' : 'active';
        $fconn->query("UPDATE formations SET status = '$new' WHERE id = $fid");
        header("Location: formations.php?updated=1"); exit;
    }
    if ($action === 'approve_subscription') {
        $sid = intval($_POST['sub_id'] ?? 0);
        if ($sid > 0) {
            $months = intval($_POST['months'] ?? 1);
            $end = date('Y-m-d', strtotime("+$months months"));
            $fconn->query("UPDATE formation_subscriptions SET status='active', start_date=NOW(), end_date='$end' WHERE id=$sid");
            header("Location: formations.php?sub_approved=1"); exit;
        }
    }
    if ($action === 'reject_subscription') {
        $sid = intval($_POST['sub_id'] ?? 0);
        if ($sid > 0) {
            $fconn->query("UPDATE formation_subscriptions SET status='cancelled' WHERE id=$sid");
            header("Location: formations.php?sub_rejected=1"); exit;
        }
    }
}

// Stats
$_r = $fconn->query("SELECT COUNT(*) c FROM formations");                                    $total_f    = $_r ? (int)$_r->fetch_assoc()['c'] : 0;
$_r = $fconn->query("SELECT COUNT(*) c FROM formations WHERE status='active'");             $active_f   = $_r ? (int)$_r->fetch_assoc()['c'] : 0;
$_r = $fconn->query("SELECT COUNT(*) c FROM formation_subscriptions WHERE status='active'"); $total_subs = $_r ? (int)$_r->fetch_assoc()['c'] : 0;
$_r = $fconn->query("SELECT COUNT(*) c FROM formation_subscriptions WHERE status='pending'"); $pending_s  = $_r ? (int)$_r->fetch_assoc()['c'] : 0;

// Tab
$tab = $_GET['tab'] ?? 'formations';

// Formations list
$formations = [];
$res = $fconn->query("SELECT f.*, c.name cat_name,
    (SELECT COUNT(*) FROM formation_modules WHERE formation_id=f.id) mods,
    (SELECT COUNT(*) FROM formation_subscriptions WHERE formation_id=f.id AND status='active') subs
    FROM formations f
    LEFT JOIN formation_categories c ON c.id=f.category_id
    ORDER BY f.created_at DESC");
if ($res) while ($r = $res->fetch_assoc()) $formations[] = $r;

// Pending subscriptions
$pending_subs = [];
if ($tab === 'subscriptions') {
    $res2 = $fconn->query("SELECT fs.*, fs.user_id, fs.formation_id,
        COALESCE(u.first_name, 'N/A') first_name, COALESCE(u.last_name, '') last_name,
        COALESCE(u.email, '—') email, f.title f_title
        FROM formation_subscriptions fs
        LEFT JOIN users u ON u.id=fs.user_id
        LEFT JOIN formations f ON f.id=fs.formation_id
        WHERE fs.status='pending'
        ORDER BY fs.created_at DESC");
    if ($res2) while ($r = $res2->fetch_assoc()) $pending_subs[] = $r;
}
?>

<div class="p-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Formations</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Gérez les formations et abonnements</p>
        </div>
        <a href="add_formation.php" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium text-sm">
            <i class="fas fa-plus"></i> Ajouter une formation
        </a>
    </div>

    <?php if (isset($_GET['deleted'])): ?><div class="mb-4 p-3 bg-green-100 text-green-800 rounded-lg text-sm">Formation supprimée avec succès.</div><?php endif; ?>
    <?php if (isset($_GET['updated'])): ?><div class="mb-4 p-3 bg-blue-100 text-blue-800 rounded-lg text-sm">Statut mis à jour.</div><?php endif; ?>
    <?php if (isset($_GET['sub_approved'])): ?><div class="mb-4 p-3 bg-green-100 text-green-800 rounded-lg text-sm">Abonnement approuvé.</div><?php endif; ?>
    <?php if (isset($_GET['sub_rejected'])): ?><div class="mb-4 p-3 bg-red-100 text-red-800 rounded-lg text-sm">Abonnement rejeté.</div><?php endif; ?>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="text-2xl font-bold text-blue-600"><?= $total_f ?></div>
            <div class="text-sm text-gray-500 dark:text-gray-400">Total formations</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="text-2xl font-bold text-green-600"><?= $active_f ?></div>
            <div class="text-sm text-gray-500 dark:text-gray-400">Actives</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="text-2xl font-bold text-purple-600"><?= $total_subs ?></div>
            <div class="text-sm text-gray-500 dark:text-gray-400">Abonnés actifs</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="text-2xl font-bold text-orange-500"><?= $pending_s ?></div>
            <div class="text-sm text-gray-500 dark:text-gray-400">Abonnements en attente</div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 mb-4 border-b border-gray-200 dark:border-gray-700">
        <a href="?tab=formations" class="px-4 py-2 text-sm font-medium <?= $tab==='formations' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-gray-700' ?>">
            Formations (<?= count($formations) ?>)
        </a>
        <a href="?tab=subscriptions" class="px-4 py-2 text-sm font-medium <?= $tab==='subscriptions' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-gray-700' ?>">
            Abonnements en attente <?php if ($pending_s > 0): ?><span class="ml-1 px-2 py-0.5 text-xs bg-red-500 text-white rounded-full"><?= $pending_s ?></span><?php endif; ?>
        </a>
    </div>

    <?php if ($tab === 'formations'): ?>
    <!-- Formations Table -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden border border-gray-100 dark:border-gray-700">
        <?php if (empty($formations)): ?>
        <div class="p-12 text-center text-gray-400">
            <i class="fas fa-graduation-cap text-4xl mb-3"></i>
            <p class="font-medium">Aucune formation</p>
            <a href="add_formation.php" class="mt-3 inline-block text-blue-600 text-sm">Créer votre première formation</a>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300 text-xs uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left">Formation</th>
                        <th class="px-4 py-3 text-left">Catégorie</th>
                        <th class="px-4 py-3 text-center">Niveau</th>
                        <th class="px-4 py-3 text-center">Modules</th>
                        <th class="px-4 py-3 text-right">Prix/mois</th>
                        <th class="px-4 py-3 text-center">Abonnés</th>
                        <th class="px-4 py-3 text-center">Statut</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                <?php foreach ($formations as $f): ?>
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <?php if (!empty($f['cover_image'])): ?>
                            <img src="../formation/<?= htmlspecialchars($f['cover_image']) ?>" class="w-10 h-10 rounded-lg object-cover" onerror="this.src='../image/oops.avif'">
                            <?php else: ?>
                            <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900 flex items-center justify-center">
                                <i class="fas fa-graduation-cap text-blue-600"></i>
                            </div>
                            <?php endif; ?>
                            <div>
                                <div class="font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($f['title']) ?></div>
                                <div class="text-xs text-gray-400"><?= htmlspecialchars($f['duration'] ?? '') ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300"><?= htmlspecialchars($f['cat_name'] ?? '-') ?></td>
                    <td class="px-4 py-3 text-center">
                        <?php
                        $lvl_colors = ['débutant'=>'green','intermédiaire'=>'blue','avancé'=>'purple'];
                        $lvl = strtolower($f['level'] ?? '');
                        $col = $lvl_colors[$lvl] ?? 'gray';
                        ?>
                        <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-<?= $col ?>-100 text-<?= $col ?>-800 dark:bg-<?= $col ?>-900 dark:text-<?= $col ?>-200">
                            <?= htmlspecialchars($f['level'] ?? '-') ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center text-gray-600 dark:text-gray-300"><?= $f['mods'] ?></td>
                    <td class="px-4 py-3 text-right font-medium text-gray-900 dark:text-white"><?= number_format($f['price_per_month'] ?? 0) ?> FCFA</td>
                    <td class="px-4 py-3 text-center text-gray-600 dark:text-gray-300"><?= $f['subs'] ?></td>
                    <td class="px-4 py-3 text-center">
                        <?php if ($f['status'] === 'active'): ?>
                        <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-800">Actif</span>
                        <?php else: ?>
                        <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-600">Inactif</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-center gap-2">
                            <a href="edit_formation.php?id=<?= $f['id'] ?>" class="text-blue-500 hover:text-blue-700" title="Modifier"><i class="fas fa-edit"></i></a>
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="formation_id" value="<?= $f['id'] ?>">
                                <button type="submit" class="text-yellow-500 hover:text-yellow-700" title="<?= $f['status']==='active' ? 'Désactiver' : 'Activer' ?>">
                                    <i class="fas fa-<?= $f['status']==='active' ? 'eye-slash' : 'eye' ?>"></i>
                                </button>
                            </form>
                            <form method="POST" class="inline" onsubmit="return confirm('Supprimer cette formation et tout son contenu ?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="formation_id" value="<?= $f['id'] ?>">
                                <button type="submit" class="text-red-500 hover:text-red-700" title="Supprimer"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <?php elseif ($tab === 'subscriptions'): ?>
    <!-- Pending Subscriptions -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden border border-gray-100 dark:border-gray-700">
        <?php if (empty($pending_subs)): ?>
        <div class="p-12 text-center text-gray-400">
            <i class="fas fa-check-circle text-4xl mb-3 text-green-400"></i>
            <p class="font-medium">Aucun abonnement en attente</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300 text-xs uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left">Étudiant</th>
                        <th class="px-4 py-3 text-left">Formation</th>
                        <th class="px-4 py-3 text-center">Durée</th>
                        <th class="px-4 py-3 text-center">Paiement</th>
                        <th class="px-4 py-3 text-center">Reçu</th>
                        <th class="px-4 py-3 text-center">Date</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                <?php foreach ($pending_subs as $s): ?>
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                    <td class="px-4 py-3">
                        <div class="font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?></div>
                        <div class="text-xs text-gray-400"><?= htmlspecialchars($s['email']) ?></div>
                    </td>
                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300"><?= htmlspecialchars($s['f_title']) ?></td>
                    <td class="px-4 py-3 text-center"><?= $s['subscription_months'] ?> mois</td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-blue-100 text-blue-800"><?= htmlspecialchars($s['payment_method']) ?></span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <?php if (!empty($s['receipt_image'])): ?>
                        <a href="../formation/<?= htmlspecialchars($s['receipt_image']) ?>" target="_blank" class="text-blue-500 hover:underline text-xs">Voir reçu</a>
                        <?php else: ?><span class="text-gray-400 text-xs">-</span><?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-center text-gray-500 text-xs"><?= date('d/m/Y', strtotime($s['created_at'])) ?></td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-center gap-2">
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="approve_subscription">
                                <input type="hidden" name="sub_id" value="<?= $s['id'] ?>">
                                <input type="hidden" name="months" value="<?= $s['subscription_months'] ?>">
                                <button type="submit" class="px-3 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-700">Approuver</button>
                            </form>
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="reject_subscription">
                                <input type="hidden" name="sub_id" value="<?= $s['id'] ?>">
                                <button type="submit" class="px-3 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700">Rejeter</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php $fconn->close(); ?>
<?php require_once 'includes/footer.php'; ?>
