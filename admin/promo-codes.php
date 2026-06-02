<?php
$page_title = 'Codes Promo';
require_once 'includes/header.php';

// Auto-create table
$conn->query("CREATE TABLE IF NOT EXISTS promo_codes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  discount_percent DECIMAL(5,2) DEFAULT 0,
  discount_fixed   DECIMAL(10,2) DEFAULT 0,
  min_order        DECIMAL(10,2) DEFAULT 0,
  max_uses         INT DEFAULT 0,
  uses_count       INT DEFAULT 0,
  expires_at       DATETIME DEFAULT NULL,
  active           TINYINT(1) DEFAULT 1,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8");

$msg = '';
$edit_promo = null;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $pid      = intval($_POST['id'] ?? 0);
        $code     = strtoupper(trim($conn->real_escape_string($_POST['code'] ?? '')));
        $percent  = max(0, min(100, floatval($_POST['discount_percent'] ?? 0)));
        $fixed    = max(0, floatval($_POST['discount_fixed'] ?? 0));
        $min_ord  = max(0, floatval($_POST['min_order'] ?? 0));
        $max_uses = max(0, intval($_POST['max_uses'] ?? 0));
        $expires  = trim($_POST['expires_at'] ?? '');
        $expires_val = $expires ? "'$expires'" : 'NULL';
        $active   = intval($_POST['active'] ?? 1);

        if (!$code) {
            $msg = '<div class="bg-red-100 text-red-700 px-4 py-3 rounded mb-4">Le code est requis.</div>';
        } else {
            if ($pid) {
                $conn->query("UPDATE promo_codes SET
                    code='$code', discount_percent=$percent, discount_fixed=$fixed,
                    min_order=$min_ord, max_uses=$max_uses,
                    expires_at=$expires_val, active=$active
                    WHERE id=$pid");
                $msg = '<div class="bg-green-100 text-green-700 px-4 py-3 rounded mb-4">Code mis à jour.</div>';
            } else {
                $result = $conn->query("INSERT INTO promo_codes
                    (code, discount_percent, discount_fixed, min_order, max_uses, expires_at, active)
                    VALUES ('$code', $percent, $fixed, $min_ord, $max_uses, $expires_val, $active)");
                $msg = $result
                    ? '<div class="bg-green-100 text-green-700 px-4 py-3 rounded mb-4">Code créé.</div>'
                    : '<div class="bg-red-100 text-red-700 px-4 py-3 rounded mb-4">Ce code existe déjà.</div>';
            }
        }
    } elseif ($action === 'delete') {
        $pid = intval($_POST['id'] ?? 0);
        if ($pid) $conn->query("DELETE FROM promo_codes WHERE id=$pid");
        $msg = '<div class="bg-yellow-100 text-yellow-700 px-4 py-3 rounded mb-4">Code supprimé.</div>';
    } elseif ($action === 'toggle') {
        $pid = intval($_POST['id'] ?? 0);
        if ($pid) $conn->query("UPDATE promo_codes SET active = 1-active WHERE id=$pid");
    }
}

if (isset($_GET['edit'])) {
    $eid = intval($_GET['edit']);
    $edit_promo = $conn->query("SELECT * FROM promo_codes WHERE id=$eid")->fetch_assoc();
}

$promos = $conn->query("SELECT * FROM promo_codes ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
?>

<div class="p-6">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold dark:text-white">Codes Promo</h1>
        <button onclick="document.getElementById('form-section').classList.toggle('hidden')"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm">
            <i class="fas fa-plus mr-2"></i>Nouveau code
        </button>
    </div>

    <?= $msg ?>

    <!-- Form -->
    <div id="form-section" class="<?= $edit_promo ? '' : 'hidden' ?> bg-white dark:bg-gray-800 rounded-xl shadow p-6 mb-8 border dark:border-gray-700">
        <h2 class="text-lg font-semibold dark:text-white mb-4">
            <?= $edit_promo ? 'Modifier le code' : 'Nouveau code promo' ?>
        </h2>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $edit_promo['id'] ?? 0 ?>">

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium dark:text-gray-300 mb-1">Code *</label>
                    <input type="text" name="code"
                           value="<?= htmlspecialchars($edit_promo['code'] ?? '') ?>"
                           placeholder="EX: SUMMER20"
                           class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white uppercase"
                           style="letter-spacing:0.1em" required>
                </div>
                <div>
                    <label class="block text-sm font-medium dark:text-gray-300 mb-1">Réduction % (0 = aucune)</label>
                    <input type="number" name="discount_percent" min="0" max="100" step="0.01"
                           value="<?= $edit_promo['discount_percent'] ?? 0 ?>"
                           class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                </div>
                <div>
                    <label class="block text-sm font-medium dark:text-gray-300 mb-1">Réduction fixe FCFA</label>
                    <input type="number" name="discount_fixed" min="0" step="100"
                           value="<?= $edit_promo['discount_fixed'] ?? 0 ?>"
                           class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                </div>
                <div>
                    <label class="block text-sm font-medium dark:text-gray-300 mb-1">Commande min. FCFA</label>
                    <input type="number" name="min_order" min="0" step="100"
                           value="<?= $edit_promo['min_order'] ?? 0 ?>"
                           class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium dark:text-gray-300 mb-1">Utilisations max (0 = illimité)</label>
                    <input type="number" name="max_uses" min="0"
                           value="<?= $edit_promo['max_uses'] ?? 0 ?>"
                           class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                </div>
                <div>
                    <label class="block text-sm font-medium dark:text-gray-300 mb-1">Expiration (vide = jamais)</label>
                    <input type="datetime-local" name="expires_at"
                           value="<?= $edit_promo && $edit_promo['expires_at'] ? date('Y-m-d\TH:i', strtotime($edit_promo['expires_at'])) : '' ?>"
                           class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                </div>
                <div>
                    <label class="block text-sm font-medium dark:text-gray-300 mb-1">Statut</label>
                    <select name="active" class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        <option value="1" <?= ($edit_promo['active'] ?? 1) ? 'selected' : '' ?>>Actif</option>
                        <option value="0" <?= !($edit_promo['active'] ?? 1) ? 'selected' : '' ?>>Inactif</option>
                    </select>
                </div>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg text-sm">
                    <i class="fas fa-save mr-2"></i>Enregistrer
                </button>
                <a href="promo-codes.php" class="bg-gray-200 dark:bg-gray-700 dark:text-white text-gray-800 px-5 py-2 rounded-lg text-sm">Annuler</a>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border dark:border-gray-700 overflow-hidden">
        <div class="p-4 border-b dark:border-gray-700">
            <h2 class="font-semibold dark:text-white">Codes actifs/inactifs (<?= count($promos) ?>)</h2>
        </div>
        <?php if (empty($promos)): ?>
        <div class="p-8 text-center text-gray-400">
            <i class="fas fa-tag text-4xl mb-3"></i>
            <p>Aucun code promo. Créez-en un ci-dessus.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left dark:text-gray-300">Code</th>
                        <th class="px-4 py-3 text-left dark:text-gray-300">Réduction</th>
                        <th class="px-4 py-3 text-left dark:text-gray-300 hidden md:table-cell">Min. commande</th>
                        <th class="px-4 py-3 text-left dark:text-gray-300 hidden md:table-cell">Utilisations</th>
                        <th class="px-4 py-3 text-left dark:text-gray-300 hidden lg:table-cell">Expiration</th>
                        <th class="px-4 py-3 text-left dark:text-gray-300">Statut</th>
                        <th class="px-4 py-3 text-right dark:text-gray-300">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y dark:divide-gray-700">
                    <?php foreach ($promos as $promo): ?>
                    <tr class="dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td class="px-4 py-3 font-mono font-bold tracking-widest"><?= htmlspecialchars($promo['code']) ?></td>
                        <td class="px-4 py-3">
                            <?php if ($promo['discount_percent'] > 0): ?>
                            <span class="text-green-600"><?= $promo['discount_percent'] ?>%</span>
                            <?php endif; ?>
                            <?php if ($promo['discount_fixed'] > 0): ?>
                            <span class="text-green-600 ml-1"><?= number_format($promo['discount_fixed'],0) ?> FCFA</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 hidden md:table-cell text-gray-500">
                            <?= $promo['min_order'] > 0 ? number_format($promo['min_order'],0).' FCFA' : '—' ?>
                        </td>
                        <td class="px-4 py-3 hidden md:table-cell">
                            <?= $promo['uses_count'] ?>
                            <?= $promo['max_uses'] > 0 ? '/ '.$promo['max_uses'] : '' ?>
                        </td>
                        <td class="px-4 py-3 hidden lg:table-cell text-xs text-gray-400">
                            <?= $promo['expires_at'] ? date('d/m/Y H:i', strtotime($promo['expires_at'])) : '∞' ?>
                        </td>
                        <td class="px-4 py-3">
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= $promo['id'] ?>">
                                <button type="submit"
                                        class="px-2 py-0.5 rounded-full text-xs font-medium <?= $promo['active'] ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-500' ?>">
                                    <?= $promo['active'] ? 'Actif' : 'Inactif' ?>
                                </button>
                            </form>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="promo-codes.php?edit=<?= $promo['id'] ?>"
                               class="inline-flex items-center text-blue-600 hover:text-blue-800 mr-3 text-xs">
                                <i class="fas fa-edit mr-1"></i>
                            </a>
                            <form method="POST" class="inline" onsubmit="return confirm('Supprimer ce code ?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $promo['id'] ?>">
                                <button type="submit" class="text-red-500 hover:text-red-700 text-xs">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
