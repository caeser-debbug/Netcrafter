<?php
session_start();
require_once __DIR__ . '/db.php';

$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset('utf8');

// Parse and sanitise product IDs
$raw_ids = isset($_GET['ids']) ? (array)$_GET['ids'] : [];
$ids = array_values(array_unique(array_slice(array_filter(array_map('intval', $raw_ids)), 0, 4)));

if (count($ids) < 2) {
    header('Location: shop.php');
    exit;
}

// Fetch products
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $conn->prepare(
    "SELECT p.*, c.name as category_name
     FROM products p
     LEFT JOIN categories c ON p.category_id = c.id
     WHERE p.id IN ($placeholders)"
);
$types = str_repeat('i', count($ids));
$stmt->bind_param($types, ...$ids);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch image + rating for each
foreach ($products as &$p) {
    $is = $conn->prepare(
        "SELECT image_url FROM product_images
         WHERE product_id=? ORDER BY is_primary DESC LIMIT 1"
    );
    $is->bind_param('i', $p['id']); $is->execute();
    $ir = $is->get_result();
    $p['image'] = ($ir->num_rows > 0) ? $ir->fetch_assoc()['image_url'] : 'image/oops.avif';

    // Try product_reviews table (may not exist yet)
    $p['avg_rating']   = 0;
    $p['review_count'] = 0;
    $check = $conn->query("SHOW TABLES LIKE 'product_reviews'");
    if ($check && $check->num_rows > 0) {
        $rs = $conn->prepare(
            "SELECT AVG(rating) as avg, COUNT(*) as cnt
             FROM product_reviews WHERE product_id=? AND status='approved'"
        );
        $rs->bind_param('i', $p['id']); $rs->execute();
        $rr = $rs->get_result()->fetch_assoc();
        $p['avg_rating']   = round($rr['avg'] ?? 0, 1);
        $p['review_count'] = (int)($rr['cnt'] ?? 0);
    }
}
unset($p);
$conn->close();

$page_title = 'Comparaison produits — Netcrafter';
include '../includes/header.php';
include 'shop-theme.php';
?>

<!-- Breadcrumb -->
<div class="pt-24 pb-4 border-b border-white/5">
    <div class="max-w-7xl mx-auto px-4">
        <nav class="flex text-sm text-slate-400 gap-2 items-center">
            <a href="shop.php" class="hover:text-nc-cyan transition-colors">Boutique</a>
            <i class="fas fa-chevron-right text-xs text-white/20"></i>
            <span class="text-white">Comparaison</span>
        </nav>
    </div>
</div>

<section class="py-10">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex items-center justify-between mb-8" data-aos="fade-up">
            <h1 class="text-2xl font-bold text-white">Comparer les produits</h1>
            <a href="shop.php" class="btn-secondary text-sm py-2 px-4">
                <i class="fas fa-plus mr-1"></i> Ajouter un produit
            </a>
        </div>

        <div class="overflow-x-auto" data-aos="fade-up">
            <table class="w-full border-separate border-spacing-0" style="min-width:<?= count($products) * 230 + 160 ?>px">

                <!-- Product cards row -->
                <thead>
                    <tr>
                        <th class="w-36 pr-4"></th>
                        <?php foreach ($products as $p): ?>
                        <th class="px-2 pb-4 align-top text-center font-normal">
                            <div class="shop-card p-4 mx-1 h-full flex flex-col">
                                <a href="product.php?id=<?= $p['id'] ?>">
                                    <img src="../<?= htmlspecialchars($p['image']) ?>"
                                         alt="<?= htmlspecialchars($p['name']) ?>"
                                         class="w-full h-36 object-contain rounded-xl mb-3"
                                         onerror="this.src='../image/oops.avif'">
                                    <p class="text-white font-semibold text-sm leading-snug mb-1 text-center">
                                        <?= htmlspecialchars($p['name']) ?>
                                    </p>
                                </a>
                                <p class="price-tag text-center font-bold mb-3">
                                    <?= number_format($p['price'], 0) ?> FCFA
                                </p>
                                <?php if (!empty($p['stock']) && $p['stock'] > 0): ?>
                                <form method="POST" action="product.php?id=<?= $p['id'] ?>" class="mb-2">
                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                    <input type="hidden" name="action"     value="add_to_cart">
                                    <button type="submit" class="btn-primary w-full justify-center text-xs py-2">
                                        <i class="fas fa-cart-plus"></i> Panier
                                    </button>
                                </form>
                                <?php else: ?>
                                <div class="text-red-400 text-xs text-center py-2 mb-2">Rupture de stock</div>
                                <?php endif; ?>
                                <?php
                                $remove_ids = array_values(array_filter($ids, function($i) use ($p) { return $i !== $p['id']; }));
                                $remove_qs  = implode('&', array_map(function($i) { return 'ids[]='.$i; }, $remove_ids));
                                ?>
                                <a href="<?= count($remove_ids) >= 2 ? 'compare.php?'.$remove_qs : 'shop.php' ?>"
                                   class="text-xs text-slate-500 hover:text-red-400 text-center transition-colors mt-auto">
                                    <i class="fas fa-times mr-1"></i>Retirer
                                </a>
                            </div>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>

                <!-- Comparison rows -->
                <tbody>
                <?php
                $rows = [
                    ['label' => 'Catégorie',    'fn' => function($p) { return '<span class="cat-badge">'.htmlspecialchars($p['category_name'] ?? '—').'</span>'; }],
                    ['label' => 'Stock',         'fn' => function($p) {
                        if (!isset($p['stock'])) return '—';
                        if ($p['stock'] == 0)  return '<span class="text-red-400 text-sm">Rupture</span>';
                        if ($p['stock'] <= 5)  return '<span class="text-amber-400 text-sm">'.$p['stock'].' restants</span>';
                        return '<span class="text-nc-green text-sm">'.$p['stock'].' en stock</span>';
                    }],
                    ['label' => 'Poids',         'fn' => function($p) { return !empty($p['weight']) ? htmlspecialchars($p['weight']).' kg' : '—'; }],
                    ['label' => 'Dimensions',    'fn' => function($p) { return !empty($p['dimensions']) ? htmlspecialchars($p['dimensions']) : '—'; }],
                    ['label' => 'Note',          'fn' => function($p) {
                        if ($p['avg_rating'] <= 0) return '<span class="text-slate-500 text-xs">Pas encore noté</span>';
                        $stars = str_repeat('<i class="fas fa-star text-amber-400 text-xs"></i>', (int)round($p['avg_rating']))
                               . str_repeat('<i class="far fa-star text-slate-600 text-xs"></i>', 5 - (int)round($p['avg_rating']));
                        return $stars.'<br><span class="text-xs text-slate-400">'.$p['avg_rating'].'/5 ('.$p['review_count'].' avis)</span>';
                    }],
                    ['label' => 'Description',   'fn' => function($p) {
                        $desc = !empty($p['short_description']) ? $p['short_description'] : ($p['description'] ?? '');
                        $desc = mb_substr(strip_tags($desc), 0, 120);
                        return '<span class="text-slate-400 text-xs leading-relaxed">'.htmlspecialchars($desc).(mb_strlen($p['description'] ?? '') > 120 ? '…' : '').'</span>';
                    }],
                ];
                foreach ($rows as $row):
                ?>
                <tr class="border-t border-white/5">
                    <td class="pr-4 py-4 align-middle">
                        <span class="text-slate-400 text-sm font-medium whitespace-nowrap"><?= $row['label'] ?></span>
                    </td>
                    <?php foreach ($products as $p): ?>
                    <td class="px-4 py-4 text-center align-middle text-sm text-slate-200">
                        <?= ($row['fn'])($p) ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-10 text-center" data-aos="fade-up">
            <a href="shop.php" class="btn-secondary">
                <i class="fas fa-arrow-left mr-2"></i>Retour à la boutique
            </a>
        </div>
    </div>
</section>

<?php include '../includes/footer.php'; ?>
