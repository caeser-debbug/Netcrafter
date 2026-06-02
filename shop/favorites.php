<?php
// Initialisation de la session pour le panier et les favoris
session_start();

require_once __DIR__ . '/db.php';

// Connexion à la base de données
$conn = new mysqli($servername, $username, $password, $dbname);

// Vérification de la connexion
if ($conn->connect_error) {
    die("Échec de la connexion: " . $conn->connect_error);
}
$conn->set_charset("utf8");

// Initialisation du panier et des favoris s'ils n'existent pas
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
if (!isset($_SESSION['favorites'])) {
    $_SESSION['favorites'] = [];
}

// Traitement des actions (supprimer des favoris, ajouter au panier, etc.)
if (isset($_POST['action'])) {
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

    if ($_POST['action'] === 'remove_from_favorites') {
        if (($key = array_search($product_id, $_SESSION['favorites'])) !== false) {
            unset($_SESSION['favorites'][$key]);
            $_SESSION['favorites'] = array_values($_SESSION['favorites']);

            $session_id = session_id();
            $session_data = json_encode(['cart' => $_SESSION['cart'], 'favorites' => $_SESSION['favorites']]);
            $update_session = "UPDATE sessions SET data = ? WHERE id = ?";
            $stmt = $conn->prepare($update_session);
            $stmt->bind_param("ss", $session_data, $session_id);
            $stmt->execute();
        }
        header("Location: favorites.php?removed=1");
        exit;
    }
    elseif ($_POST['action'] === 'add_to_cart') {
        if (array_key_exists($product_id, $_SESSION['cart'])) {
            $_SESSION['cart'][$product_id]++;
        } else {
            $_SESSION['cart'][$product_id] = 1;
        }
        $session_id = session_id();
        $session_data = json_encode(['cart' => $_SESSION['cart'], 'favorites' => $_SESSION['favorites']]);
        $update_session = "UPDATE sessions SET data = ? WHERE id = ?";
        $stmt = $conn->prepare($update_session);
        $stmt->bind_param("ss", $session_data, $session_id);
        $stmt->execute();
        header("Location: favorites.php?added_to_cart=1");
        exit;
    }
    elseif ($_POST['action'] === 'clear_favorites') {
        $_SESSION['favorites'] = [];
        $session_id = session_id();
        $session_data = json_encode(['cart' => $_SESSION['cart'], 'favorites' => $_SESSION['favorites']]);
        $update_session = "UPDATE sessions SET data = ? WHERE id = ?";
        $stmt = $conn->prepare($update_session);
        $stmt->bind_param("ss", $session_data, $session_id);
        $stmt->execute();
        header("Location: favorites.php?cleared=1");
        exit;
    }
}

// Récupération des produits favoris
$favorite_items = [];

if (!empty($_SESSION['favorites'])) {
    $product_ids = $_SESSION['favorites'];
    $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';

    $sql = "SELECT p.*, c.name as category_name
            FROM products p
            JOIN categories c ON p.category_id = c.id
            WHERE p.id IN ($placeholders)
            ORDER BY FIELD(p.id, " . $placeholders . ")";

    $stmt = $conn->prepare($sql);
    $param_types = str_repeat('i', count($product_ids) * 2);
    $params = array_merge($product_ids, $product_ids);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        while ($product = $result->fetch_assoc()) {
            $product_id = $product['id'];

            $img_query = "SELECT image_url FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, display_order ASC LIMIT 1";
            $img_stmt = $conn->prepare($img_query);
            $img_stmt->bind_param("i", $product_id);
            $img_stmt->execute();
            $img_result = $img_stmt->get_result();

            if ($img_result && $img_result->num_rows > 0) {
                $img_row = $img_result->fetch_assoc();
                $product['image'] = $img_row['image_url'];
            } else {
                $product['image'] = '../image/oops.avif';
            }

            $product['in_cart'] = array_key_exists($product_id, $_SESSION['cart']);
            $product['cart_quantity'] = $product['in_cart'] ? $_SESSION['cart'][$product_id] : 0;
            $favorite_items[] = $product;
        }
    }
}

$conn->close();
?>
<?php
$page_title = 'Mes Favoris - Netcrafter';
include '../includes/header.php';
include 'shop-theme.php';
?>

<!-- Page Header -->
<section class="shop-hero">
    <div class="blob w-72 h-72 bg-nc-blue" style="top:-100px;left:-80px;"></div>
    <div class="blob w-56 h-56 bg-nc-cyan" style="bottom:-60px;right:8%;"></div>
    <div class="max-w-7xl mx-auto px-4 relative z-10 text-center">
        <span class="badge mb-4 inline-flex"><i class="fas fa-heart mr-1"></i> <?= t('shop.fav_title') ?></span>
        <h1 class="text-3xl md:text-4xl font-bold text-white mb-3" data-aos="fade-up"><?= t('shop.fav_title') ?></h1>
        <p class="text-slate-300 text-lg max-w-2xl mx-auto" data-aos="fade-up" data-aos-delay="100">
            Retrouvez tous les produits que vous avez ajoutés à vos favoris
        </p>
    </div>
</section>

<!-- Main Favorites Section -->
<section class="py-10 md:py-14">
    <div class="max-w-7xl mx-auto px-4">
        <!-- Breadcrumb -->
        <nav class="flex mb-6 text-sm text-slate-400">
            <a href="shop.php" class="hover:text-nc-cyan transition-colors">Boutique</a>
            <span class="mx-2 text-white/20">/</span>
            <span class="text-white">Favoris</span>
        </nav>

        <?php if (!empty($favorite_items)): ?>
        <div class="flex justify-between items-center mb-6" data-aos="fade-up">
            <h2 class="text-2xl font-bold text-white">
                Vos produits favoris (<?php echo count($favorite_items); ?>)
            </h2>
            <form method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer tous vos favoris ?');">
                <input type="hidden" name="action" value="clear_favorites">
                <button type="submit" class="text-red-400 hover:text-red-300 flex items-center gap-2 text-sm transition-colors">
                    <i class="fas fa-trash-alt"></i>
                    <span class="hidden sm:inline">Vider les favoris</span>
                </button>
            </form>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php foreach ($favorite_items as $item): ?>
            <div class="product-card" data-aos="fade-up">
                <div class="relative">
                    <a href="product.php?id=<?php echo $item['id']; ?>" class="block h-56 overflow-hidden">
                        <img src="../<?php echo htmlspecialchars($item['image']); ?>"
                             alt="<?php echo htmlspecialchars($item['name']); ?>"
                             class="w-full h-full object-cover hover:scale-105 transition-transform duration-500">
                    </a>
                    <div class="absolute top-3 left-3 cat-badge"><?php echo htmlspecialchars($item['category_name']); ?></div>
                    <form method="POST" class="absolute top-3 right-3">
                        <input type="hidden" name="action" value="remove_from_favorites">
                        <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                        <button type="submit" class="bg-red-500/80 backdrop-blur-sm text-white h-8 w-8 rounded-full flex items-center justify-center hover:bg-red-500 transition-colors">
                            <i class="fas fa-heart text-sm"></i>
                        </button>
                    </form>
                    <?php if ($item['stock'] > 0 && $item['stock'] <= 5): ?>
                    <div class="absolute bottom-3 left-3 bg-amber-500/90 backdrop-blur-sm text-white text-xs font-bold px-2 py-1 rounded-full">
                        Plus que <?php echo $item['stock']; ?> en stock
                    </div>
                    <?php elseif ($item['stock'] == 0): ?>
                    <div class="absolute bottom-3 left-3 bg-red-500/90 backdrop-blur-sm text-white text-xs font-bold px-2 py-1 rounded-full">
                        Rupture de stock
                    </div>
                    <?php endif; ?>
                </div>
                <div class="p-4">
                    <a href="product.php?id=<?php echo $item['id']; ?>">
                        <h3 class="font-bold text-white hover:text-nc-cyan transition-colors mb-2 line-clamp-1">
                            <?php echo htmlspecialchars($item['name']); ?>
                        </h3>
                    </a>
                    <div class="flex justify-between items-center mb-3">
                        <div class="price-tag text-xl"><?php echo number_format($item['price'], 2); ?> FCFA</div>
                        <div class="text-slate-400 text-sm"><?php echo $item['weight']; ?> kg</div>
                    </div>
                    <p class="text-slate-400 text-sm mb-4 line-clamp-2">
                        <?php
                        if (!empty($item['short_description'])) {
                            echo htmlspecialchars($item['short_description']);
                        } else {
                            echo htmlspecialchars(substr($item['description'], 0, 100)) . (strlen($item['description']) > 100 ? '...' : '');
                        }
                        ?>
                    </p>
                    <div class="flex gap-2">
                        <a href="product.php?id=<?php echo $item['id']; ?>" class="flex-1 btn-secondary py-2 text-sm">Détails</a>
                        <?php if ($item['stock'] > 0): ?>
                            <?php if ($item['in_cart']): ?>
                            <a href="cart.php" class="flex-1 bg-nc-green/20 border border-nc-green/40 text-nc-green text-center py-2 rounded-lg text-sm font-semibold hover:bg-nc-green/30 transition-colors">
                                <i class="fas fa-check mr-1"></i>Panier
                            </a>
                            <?php else: ?>
                            <form method="POST" class="flex-1">
                                <input type="hidden" name="action" value="add_to_cart">
                                <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                <button type="submit" class="w-full btn-primary text-sm py-2 px-3 justify-center rounded-lg">
                                    <i class="fas fa-cart-plus text-xs"></i> Acheter
                                </button>
                            </form>
                            <?php endif; ?>
                        <?php else: ?>
                        <button disabled class="flex-1 bg-white/5 text-slate-500 text-center py-2 rounded-lg text-sm cursor-not-allowed border border-white/10">
                            <i class="fas fa-ban mr-1"></i> Indispo
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="flex justify-center mt-10" data-aos="fade-up">
            <a href="cart.php" class="btn-outline">
                <i class="fas fa-shopping-cart"></i> Voir mon panier
            </a>
        </div>

        <?php else: ?>
        <div class="shop-card p-12 text-center max-w-lg mx-auto" data-aos="fade-up">
            <div class="text-6xl mb-6 text-slate-600"><i class="fas fa-heart-broken"></i></div>
            <h2 class="text-2xl font-bold text-white mb-3"><?= t('shop.fav_empty') ?></h2>
            <p class="text-slate-400 mb-6">
                Vous n'avez pas encore ajouté de produits à vos favoris. Parcourez notre boutique et cliquez sur le cœur pour sauvegarder vos coups de cœur.
            </p>
            <a href="shop.php" class="btn-primary"><i class="fas fa-shopping-basket text-sm"></i> Découvrir nos produits</a>
        </div>
         <?php endif; ?>
    </div>
</section>

<!-- Toast Notifications -->
<div id="fav-removed-toast" class="fixed bottom-6 right-6 glass text-white px-5 py-3 rounded-xl shadow-2xl transform translate-y-24 opacity-0 transition-all duration-300 flex items-center gap-3 z-50">
    <i class="fas fa-heart-broken text-red-400 text-lg"></i>
    <span>Produit retiré des favoris</span>
</div>
<div id="added-to-cart-toast" class="fixed bottom-6 right-6 glass text-white px-5 py-3 rounded-xl shadow-2xl transform translate-y-24 opacity-0 transition-all duration-300 flex items-center gap-3 z-50">
    <i class="fas fa-check-circle text-nc-green text-lg"></i>
    <span>Produit ajouté au panier</span>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($_GET['removed'])): ?>showToast('fav-removed-toast');<?php endif; ?>
    <?php if (isset($_GET['added_to_cart'])): ?>showToast('added-to-cart-toast');<?php endif; ?>
});
function showToast(id) {
    const t = document.getElementById(id);
    if (!t) return;
    t.classList.remove('translate-y-24','opacity-0');
    t.classList.add('translate-y-0','opacity-100');
    setTimeout(() => { t.classList.remove('translate-y-0','opacity-100'); t.classList.add('translate-y-24','opacity-0'); }, 3000);
}
</script>

<?php include '../includes/footer.php'; ?>
 