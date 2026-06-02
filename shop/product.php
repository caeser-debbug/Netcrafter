<?php
session_start();

require_once __DIR__ . '/db.php';

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Échec de la connexion: " . $conn->connect_error); }
$conn->set_charset("utf8");

if (!isset($_SESSION['cart']))      $_SESSION['cart']      = [];
if (!isset($_SESSION['favorites'])) $_SESSION['favorites'] = [];

function getMacAddress() {
    if (function_exists('exec')) {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec('getmac', $output);
            if (isset($output[1])) return substr($output[1], 0, 17);
        } else {
            exec("ifconfig -a | grep -Po 'HWaddr \K.*$'", $output);
            if (!empty($output[0])) return trim($output[0]);
        }
    }
    return $_SERVER['REMOTE_ADDR'];
}

if (!isset($_SESSION['mac_address'])) {
    $_SESSION['mac_address'] = getMacAddress();
    $session_id   = session_id();
    $mac_address  = $_SESSION['mac_address'];
    $ip_address   = $_SERVER['REMOTE_ADDR'];
    $session_data = json_encode(['cart' => $_SESSION['cart'], 'favorites' => $_SESSION['favorites']]);

    $check = $conn->prepare("SELECT id FROM sessions WHERE id = ?");
    $check->bind_param("s", $session_id);
    $check->execute();
    if ($check->get_result()->num_rows == 0) {
        $ins = $conn->prepare("INSERT INTO sessions (id, ip_address, mac_address, data) VALUES (?, ?, ?, ?)");
        $ins->bind_param("ssss", $session_id, $ip_address, $mac_address, $session_data);
        $ins->execute();
    }
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) { header("Location: shop.php"); exit; }
$product_id = intval($_GET['id']);

// Traitement des actions
if (isset($_POST['action'])) {
    $post_product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $quantity = max(1, isset($_POST['quantity']) ? intval($_POST['quantity']) : 1);

    if ($_POST['action'] === 'add_to_cart') {
        if (array_key_exists($post_product_id, $_SESSION['cart'])) $_SESSION['cart'][$post_product_id] += $quantity;
        else $_SESSION['cart'][$post_product_id] = $quantity;
        $sd = json_encode(['cart' => $_SESSION['cart'], 'favorites' => $_SESSION['favorites']]);
        $st = $conn->prepare("UPDATE sessions SET data = ? WHERE id = ?"); $sid = session_id();
        $st->bind_param("ss", $sd, $sid); $st->execute();
        header("Location: product.php?id=$product_id&cart_added=1"); exit;
    }
    elseif ($_POST['action'] === 'add_to_favorites') {
        if (!in_array($post_product_id, $_SESSION['favorites'])) {
            $_SESSION['favorites'][] = $post_product_id;
            $sd = json_encode(['cart' => $_SESSION['cart'], 'favorites' => $_SESSION['favorites']]);
            $st = $conn->prepare("UPDATE sessions SET data = ? WHERE id = ?"); $sid = session_id();
            $st->bind_param("ss", $sd, $sid); $st->execute();
        }
        header("Location: product.php?id=$product_id&fav_added=1"); exit;
    }
    elseif ($_POST['action'] === 'remove_from_favorites') {
        if (($key = array_search($post_product_id, $_SESSION['favorites'])) !== false) {
            unset($_SESSION['favorites'][$key]);
            $_SESSION['favorites'] = array_values($_SESSION['favorites']);
            $sd = json_encode(['cart' => $_SESSION['cart'], 'favorites' => $_SESSION['favorites']]);
            $st = $conn->prepare("UPDATE sessions SET data = ? WHERE id = ?"); $sid = session_id();
            $st->bind_param("ss", $sd, $sid); $st->execute();
        }
        header("Location: product.php?id=$product_id&fav_removed=1"); exit;
    }
}

// Récupération du produit
$stmt = $conn->prepare("SELECT p.*, c.name as category_name, s.name as supplier_name, s.shipping_method, s.average_delivery_time FROM products p LEFT JOIN categories c ON p.category_id = c.id LEFT JOIN suppliers s ON p.supplier_id = s.id WHERE p.id = ?");
if (!$stmt) { header("Location: shop.php"); exit; }
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
if (!$result || $result->num_rows == 0) { header("Location: shop.php"); exit; }
$product = $result->fetch_assoc();
if (!$product) { header("Location: shop.php"); exit; }
if (empty($product['category_name'])) $product['category_name'] = 'Produit';

$uv = $conn->prepare("UPDATE products SET views = views + 1 WHERE id = ?");
if ($uv) { $uv->bind_param("i", $product_id); $uv->execute(); }

// Images
$images = [];
$img_stmt = $conn->prepare("SELECT image_url FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, display_order ASC");
$img_stmt->bind_param("i", $product_id); $img_stmt->execute();
$img_res = $img_stmt->get_result();
while ($r = $img_res->fetch_assoc()) $images[] = $r['image_url'];

// Produits similaires
$similar_products = [];
$sim_stmt = $conn->prepare("SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.category_id = ? AND p.id != ? AND p.status = 'active' ORDER BY RAND() LIMIT 4");
$sim_stmt->bind_param("ii", $product['category_id'], $product_id);
$sim_stmt->execute();
$sim_res = $sim_stmt->get_result();
while ($row = $sim_res->fetch_assoc()) {
    $si = $conn->prepare("SELECT image_url FROM product_images WHERE product_id = ? ORDER BY is_primary DESC LIMIT 1");
    $si->bind_param("i", $row['id']); $si->execute();
    $sir = $si->get_result();
    $row['images'] = ($sir && $sir->num_rows > 0) ? [$sir->fetch_assoc()['image_url']] : [];
    $similar_products[] = $row;
}

// Avis
$reviews = [];
$avg_rating = 0;
$total_reviews = 0;
$rev_stmt = $conn->prepare("SELECT pr.*, u.username, u.full_name FROM product_reviews pr LEFT JOIN users u ON pr.user_id = u.id WHERE pr.product_id = ? AND pr.status = 'approved' ORDER BY pr.created_at DESC LIMIT 5");
$rev_stmt->bind_param("i", $product_id); $rev_stmt->execute();
$rev_res = $rev_stmt->get_result();
if ($rev_res && $rev_res->num_rows > 0) {
    $rating_sum = 0;
    while ($review = $rev_res->fetch_assoc()) { $reviews[] = $review; $rating_sum += $review['rating']; $total_reviews++; }
    if ($total_reviews > 0) $avg_rating = round($rating_sum / $total_reviews, 1);
}

$conn->close();
?>
<?php
$page_title = htmlspecialchars($product['name']) . ' - Netcrafter';
include '../includes/header.php';
$_d = fn($fr, $en) => ($GLOBALS['nc_lang'] ?? 'fr') === 'en' ? $en : $fr;
include 'shop-theme.php';
?>
<!-- Swiper -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.css">
<script src="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.js"></script>

<!-- Breadcrumb -->
<div class="pt-24 pb-4 border-b border-white/5">
    <div class="max-w-7xl mx-auto px-4">
        <nav class="flex text-sm text-slate-400 flex-wrap gap-1">
            <a href="shop.php" class="hover:text-nc-cyan transition-colors"><?= $_d('Boutique','Shop') ?></a>
            <span class="text-white/20">/</span>
            <a href="shop.php?category=<?php echo $product['category_id']; ?>" class="hover:text-nc-cyan transition-colors"><?php echo htmlspecialchars($product['category_name']); ?></a>
            <span class="text-white/20">/</span>
            <span class="text-white line-clamp-1"><?php echo htmlspecialchars($product['name']); ?></span>
        </nav>
    </div>
</div>

<!-- Product Detail -->
<section class="py-10 md:py-14">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex flex-col lg:flex-row gap-8 lg:gap-12">

            <!-- Gallery -->
            <div class="lg:w-1/2" data-aos="fade-right">
                <div class="shop-card p-4 md:p-6">
                    <div class="swiper product-swiper swiper-product mb-4">
                        <div class="swiper-wrapper">
                            <?php if (empty($images)): ?>
                            <div class="swiper-slide"><img src="../image/oops.avif" alt="<?php echo htmlspecialchars($product['name']); ?>" class="rounded-xl"></div>
                            <?php else: foreach ($images as $image): ?>
                            <div class="swiper-slide"><img src="../<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="rounded-xl"></div>
                            <?php endforeach; endif; ?>
                        </div>
                        <div class="swiper-pagination"></div>
                        <div class="swiper-button-next"></div>
                        <div class="swiper-button-prev"></div>
                    </div>
                    <?php if (count($images) > 1): ?>
                    <div class="swiper thumbs-swiper swiper-thumbs">
                        <div class="swiper-wrapper">
                            <?php foreach ($images as $i => $image): ?>
                            <div class="swiper-slide thumb-slide <?php echo $i === 0 ? 'thumb-slide-active' : ''; ?>">
                                <img src="../<?php echo htmlspecialchars($image); ?>" alt="Miniature" class="rounded-lg h-full w-full object-cover">
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Product Info -->
            <div class="lg:w-1/2" data-aos="fade-left">
                <div class="shop-card p-6">
                    <div class="cat-badge inline-block mb-3"><?php echo htmlspecialchars($product['category_name']); ?></div>
                    <h1 class="text-2xl md:text-3xl font-bold text-white mb-4"><?php echo htmlspecialchars($product['name']); ?></h1>

                    <?php if ($total_reviews > 0): ?>
                    <div class="flex items-center mb-4">
                        <div class="star-rating mr-2">
                            <?php
                            $full = floor($avg_rating); $half = $avg_rating - $full >= 0.5; $empty = 5 - $full - ($half ? 1 : 0);
                            for ($i=0;$i<$full;$i++) echo '<i class="fas fa-star star filled"></i>';
                            if ($half) echo '<i class="fas fa-star-half-alt star filled"></i>';
                            for ($i=0;$i<$empty;$i++) echo '<i class="far fa-star star"></i>';
                            ?>
                        </div>
                        <span class="text-slate-400 text-sm"><?php echo number_format($avg_rating,1); ?> (<?php echo $total_reviews; ?> <?= $_d('avis','reviews') ?>)</span>
                    </div>
                    <?php endif; ?>

                    <div class="flex flex-wrap items-center gap-4 mb-6">
                        <div class="price-tag text-3xl"><?php echo number_format($product['price'], 2); ?> FCFA</div>
                        <?php if (!empty($product['sale_price']) && $product['sale_price'] < $product['price']): ?>
                        <span class="line-through text-slate-500 text-lg"><?php echo number_format($product['sale_price'], 2); ?> FCFA</span>
                        <span class="bg-red-500/20 text-red-400 border border-red-500/30 text-xs font-bold px-2.5 py-1 rounded-full">
                            -<?php echo round(($product['price'] - $product['sale_price']) / $product['price'] * 100); ?>%
                        </span>
                        <?php endif; ?>
                        <div class="bg-white/5 border border-white/10 px-3 py-1 rounded-full text-slate-300 text-sm flex items-center gap-2">
                            <i class="fas fa-weight-hanging text-nc-cyan text-xs"></i><?php echo $product['weight']; ?> kg
                        </div>
                    </div>

                    <?php if ($product['stock'] > 0): ?>
                    <?php if ($product['stock'] <= 5): ?>
                    <div class="flex items-center text-amber-400 mb-4 text-sm">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?= $_d('Stock limité','Limited stock') ?> <span class="text-slate-400 ml-1">(<?php echo $product['stock']; ?> <?= $_d('dispo.','avail.') ?>)</span>
                    </div>
                    <?php else: ?>
                    <div class="flex items-center text-nc-green mb-4 text-sm">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?= $_d('En stock','In stock') ?> <span class="text-slate-400 ml-1">(<?php echo $product['stock']; ?> <?= $_d('dispo.','avail.') ?>)</span>
                    </div>
                    <?php endif; ?>
                    <?php else: ?>
                    <div class="flex items-center text-red-400 mb-4 text-sm">
                        <i class="fas fa-times-circle mr-2"></i><?= $_d('Rupture de stock','Out of stock') ?>
                    </div>
                    <!-- Stock alert subscription -->
                    <div class="mb-4 rounded-xl p-4" style="background:rgba(0,200,255,0.06);border:1px solid rgba(0,200,255,0.15)">
                        <p class="text-sm text-slate-300 mb-3"><i class="fas fa-bell text-nc-cyan mr-2"></i><?= $_d('Soyez alerté dès le retour en stock','Get notified when back in stock') ?></p>
                        <div class="flex gap-2">
                            <input type="email" id="alert-email"
                                   placeholder="votre@email.com"
                                   class="shop-input flex-1 text-sm py-2">
                            <button onclick="subscribeStockAlert()"
                                    class="btn-secondary py-2 px-4 text-sm whitespace-nowrap">
                                <?= $_d('M\'alerter','Notify me') ?>
                            </button>
                        </div>
                        <p id="alert-msg" class="text-xs mt-2 hidden"></p>
                    </div>
                    <?php endif; ?>

                    <div class="mb-5 text-slate-300 text-sm leading-relaxed">
                        <?php echo nl2br(htmlspecialchars(!empty($product['short_description']) ? $product['short_description'] : substr($product['description'],0,200).'...')); ?>
                    </div>

                    <form method="POST" class="mb-5">
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                        <input type="hidden" name="action" value="add_to_cart">
                        <div class="flex flex-col md:flex-row items-stretch gap-3 mb-3">
                            <div class="flex items-center border border-white/15 rounded-xl overflow-hidden">
                                <button type="button" id="decrement-btn" class="bg-white/5 text-white w-10 h-10 flex items-center justify-center hover:bg-white/15 transition-colors">
                                    <i class="fas fa-minus text-xs"></i>
                                </button>
                                <input type="number" id="quantity" name="quantity" min="1" max="<?php echo $product['stock']; ?>" value="1"
                                       class="w-14 h-10 bg-transparent text-white text-center focus:outline-none text-sm">
                                <button type="button" id="increment-btn" class="bg-white/5 text-white w-10 h-10 flex items-center justify-center hover:bg-white/15 transition-colors">
                                    <i class="fas fa-plus text-xs"></i>
                                </button>
                            </div>
                            <button type="submit" <?php echo $product['stock'] > 0 ? '' : 'disabled'; ?>
                                    class="flex-1 btn-primary justify-center <?php echo $product['stock'] <= 0 ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                                <i class="fas fa-shopping-cart text-sm"></i> <?= $_d('Ajouter au panier','Add to cart') ?>
                            </button>
                            <button type="button" id="toggle-favorite"
                                    class="w-12 h-12 bg-white/5 border border-white/15 rounded-xl flex items-center justify-center hover:bg-white/10 transition-colors flex-shrink-0">
                                <?php if (in_array($product['id'], $_SESSION['favorites'])): ?>
                                <i class="fas fa-heart text-red-500"></i>
                                <?php else: ?>
                                <i class="far fa-heart text-slate-300"></i>
                                <?php endif; ?>
                            </button>
                        </div>
                    </form>
                    <form id="favorite-form" method="POST" class="hidden">
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                        <input type="hidden" name="action" id="favorite-action" value="<?php echo in_array($product['id'], $_SESSION['favorites']) ? 'remove_from_favorites' : 'add_to_favorites'; ?>">
                    </form>
                    <!-- Compare button -->
                    <button onclick="toggleCompareProduct(<?php echo $product['id']; ?>, <?php echo json_encode($product['name']); ?>)"
                            id="compare-btn"
                            class="w-full flex items-center justify-center gap-2 text-sm py-2.5 rounded-xl border transition-all mb-5"
                            style="background:rgba(0,200,255,0.05);border-color:rgba(0,200,255,0.2);color:#94a3b8">
                        <i class="fas fa-columns text-xs"></i>
                        <span id="compare-btn-label"><?= $_d('Ajouter à la comparaison','Add to compare') ?></span>
                    </button>

                    <!-- Delivery -->
                    <div class="bg-nc-blue/10 border border-nc-blue/20 rounded-xl p-4 mb-5">
                        <div class="flex items-center gap-2 mb-2">
                            <img src="../image/joegol.jpg" alt="Joegol" class="h-6 rounded">
                            <h3 class="font-semibold text-white text-sm">Livraison par <?php echo htmlspecialchars($product['supplier_name'] ?? 'Joegol Logistics'); ?></h3>
                        </div>
                        <ul class="space-y-1.5 text-sm text-slate-300">
                            <li class="flex items-center gap-2"><i class="fas fa-shipping-fast text-nc-cyan w-4"></i> <?= $_d('Expédition en 24-48h','Ships within 24-48h') ?></li>
                            <li class="flex items-center gap-2"><i class="fas fa-box text-nc-cyan w-4"></i> <?= $_d('Envoi direct depuis notre fournisseur','Shipped directly from our supplier') ?></li>
                            <li class="flex items-center gap-2"><i class="fas fa-truck text-nc-cyan w-4"></i> <?= $_d('Livraison estimée :','Estimated delivery:') ?> <?php echo $product['average_delivery_time'] ?? '5-10'; ?> <?= $_d('jours','days') ?></li>
                        </ul>
                    </div>

                    <!-- Trust badges -->
                    <div class="grid grid-cols-4 gap-2 pt-4 border-t border-white/10">
                        <?php
                        $trust = [['fas fa-sync-alt',$_d('Retours 30j','30-day returns')],['fas fa-shield-alt',$_d('Garantie 2 ans','2-year warranty')],['fas fa-credit-card',$_d('Paiement sécurisé','Secure payment')],['fas fa-headset','Support 24/7']];
                        foreach ($trust as $t):?>
                        <div class="flex flex-col items-center text-center">
                            <i class="<?php echo $t[0]; ?> text-lg text-nc-cyan mb-1"></i>
                            <span class="text-xs text-slate-400"><?php echo $t[1]; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="mt-12" data-aos="fade-up">
            <div class="shop-card overflow-hidden">
                <div class="flex flex-wrap border-b border-white/10">
                    <button class="tab-button py-4 px-5 text-sm font-semibold border-b-2 tab-active text-nc-cyan" data-tab="description"><?= $_d('Description','Description') ?></button>
                    <button class="tab-button py-4 px-5 text-sm font-semibold border-b-2 border-transparent text-slate-400 hover:text-white" data-tab="specifications"><?= $_d('Spécifications','Specifications') ?></button>
                    <button class="tab-button py-4 px-5 text-sm font-semibold border-b-2 border-transparent text-slate-400 hover:text-white" data-tab="shipping"><?= $_d('Livraison','Shipping') ?></button>
                    <button class="tab-button py-4 px-5 text-sm font-semibold border-b-2 border-transparent text-slate-400 hover:text-white" data-tab="reviews"><?= $_d('Avis','Reviews') ?> (<?php echo $total_reviews; ?>)</button>
                </div>
                <div class="p-6">
                    <!-- Description -->
                    <div id="description-tab" class="tab-content text-slate-300 leading-relaxed">
                        <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                    </div>

                    <!-- Specifications -->
                    <div id="specifications-tab" class="tab-content hidden">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h3 class="text-white font-semibold mb-4"><?= $_d('Caractéristiques techniques','Technical specifications') ?></h3>
                                <ul class="space-y-2 text-sm">
                                    <?php
                                    $specs = !empty($product['specifications']) ? (json_decode($product['specifications'], true) ?: []) : [];
                                    $defaults = [$_d('Dimensions','Dimensions') => $product['dimensions'] ?? 'N/A', $_d('Poids','Weight') => $product['weight'].' kg', $_d('Garantie','Warranty') => $_d('2 ans','2 years'), $_d('Origine','Origin') => $_d('Chine','China')];
                                    foreach (array_merge($defaults, $specs) as $k => $v): ?>
                                    <li class="flex justify-between py-1.5 border-b border-white/5">
                                        <span class="text-slate-400"><?php echo htmlspecialchars($k); ?></span>
                                        <span class="text-white font-medium"><?php echo htmlspecialchars($v); ?></span>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <div>
                                <h3 class="text-white font-semibold mb-4"><?= $_d('Dans la boîte','In the box') ?></h3>
                                <ul class="space-y-2 text-sm text-slate-300">
                                    <?php
                                    $contents = !empty($product['package_contents']) ? json_decode($product['package_contents'], true) : null;
                                    $items = $contents ?: [$_d('Produit principal','Main product').' '.$product['name'], $_d("Manuel d'utilisation",'User manual'), $_d('Carte de garantie','Warranty card')];
                                    foreach ($items as $item): ?>
                                    <li class="flex items-center gap-2"><i class="fas fa-check text-nc-green text-xs"></i><?php echo htmlspecialchars($item); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Shipping -->
                    <div id="shipping-tab" class="tab-content hidden text-slate-300">
                        <p class="mb-4"><?= $_d('Tous nos produits sont expédiés via','All our products are shipped via') ?> <strong class="text-white"><?php echo htmlspecialchars($product['supplier_name'] ?? 'Joegol Logistics'); ?></strong>, <?= $_d('spécialiste du dropshipping.','dropshipping specialist.') ?></p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="bg-white/5 rounded-xl p-4">
                                <h4 class="text-white font-semibold mb-2 text-sm"><?= $_d('Délais d\'expédition','Shipping times') ?></h4>
                                <ul class="space-y-1.5 text-sm">
                                    <li class="flex gap-2"><i class="fas fa-circle text-nc-cyan text-xs mt-1"></i> <?= $_d('Préparation : 1-2 jours ouvrés','Processing: 1-2 business days') ?></li>
                                    <li class="flex gap-2"><i class="fas fa-circle text-nc-cyan text-xs mt-1"></i> <?= $_d('Standard :','Standard:') ?> <?php echo $product['average_delivery_time'] ?? '5-10'; ?> <?= $_d('jours ouvrés','business days') ?></li>
                                    <li class="flex gap-2"><i class="fas fa-circle text-nc-cyan text-xs mt-1"></i> <?= $_d('Express : 3-5 jours ouvrés','Express: 3-5 business days') ?></li>
                                </ul>
                            </div>
                            <div class="bg-white/5 rounded-xl p-4">
                                <h4 class="text-white font-semibold mb-2 text-sm"><?= $_d('Frais de livraison','Shipping fees') ?></h4>
                                <ul class="space-y-1.5 text-sm">
                                    <li class="flex gap-2"><i class="fas fa-circle text-nc-cyan text-xs mt-1"></i> <?= $_d('Standard : 5,99 FCFA (gratuit dès 50 FCFA)','Standard: 5.99 FCFA (free from 50 FCFA)') ?></li>
                                    <li class="flex gap-2"><i class="fas fa-circle text-nc-cyan text-xs mt-1"></i> <?= $_d('Express : 12,99 FCFA','Express: 12.99 FCFA') ?></li>
                                    <li class="flex gap-2"><i class="fas fa-circle text-nc-cyan text-xs mt-1"></i> <?= $_d('Suivi en temps réel inclus','Real-time tracking included') ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Reviews -->
                    <div id="reviews-tab" class="tab-content hidden">
                        <div class="flex flex-col md:flex-row gap-6 border-b border-white/10 pb-6 mb-6">
                            <div class="md:w-1/4 text-center">
                                <div class="text-5xl font-bold text-white mb-2"><?php echo number_format($avg_rating,1); ?></div>
                                <div class="star-rating mb-2 flex justify-center">
                                    <?php
                                    $full = floor($avg_rating); $half = $avg_rating - $full >= 0.5; $empty = 5 - $full - ($half?1:0);
                                    for($i=0;$i<$full;$i++) echo '<i class="fas fa-star star filled"></i>';
                                    if($half) echo '<i class="fas fa-star-half-alt star filled"></i>';
                                    for($i=0;$i<$empty;$i++) echo '<i class="far fa-star star"></i>';
                                    ?>
                                </div>
                                <p class="text-slate-400 text-sm"><?= $_d('Basé sur','Based on') ?> <?php echo $total_reviews; ?> <?= $_d('avis','reviews') ?></p>
                            </div>
                            <div class="md:w-3/4">
                                <?php if (empty($reviews)): ?>
                                <p class="text-slate-400"><?= $_d('Aucun avis pour le moment. Soyez le premier !','No reviews yet. Be the first!') ?></p>
                                <?php else: ?>
                                <div class="space-y-3">
                                    <?php foreach ($reviews as $review): ?>
                                    <div class="bg-white/5 rounded-xl p-4">
                                        <div class="flex justify-between items-start mb-2">
                                            <div>
                                                <h4 class="font-semibold text-white text-sm"><?php echo htmlspecialchars($review['title'] ?? $_d('Avis','Review')); ?></h4>
                                                <div class="star-rating">
                                                    <?php for($i=1;$i<=5;$i++): ?><i class="<?php echo $i<=$review['rating']?'fas fa-star star filled':'far fa-star star'; ?>"></i><?php endfor; ?>
                                                    <span class="text-slate-400 text-xs ml-2"><?php echo htmlspecialchars($review['full_name'] ?? $review['username'] ?? $_d('Client','Customer')); ?></span>
                                                </div>
                                            </div>
                                            <span class="text-xs text-slate-500"><?php echo date('d/m/Y', strtotime($review['created_at'])); ?></span>
                                        </div>
                                        <p class="text-slate-300 text-sm"><?php echo nl2br(htmlspecialchars($review['review'])); ?></p>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Write review -->
                        <h3 class="text-white font-semibold mb-4"><?= $_d('Donnez votre avis','Write a review') ?></h3>
                        <div id="review-success" class="hidden rounded-xl p-4 mb-4 text-nc-green text-sm"
                             style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3)">
                            <i class="fas fa-check-circle mr-2"></i><span id="review-success-msg"></span>
                        </div>
                        <form id="review-form" class="space-y-4">
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm text-slate-300 mb-1"><?= $_d('Nom','Name') ?></label>
                                    <input type="text" name="name" class="shop-input" required>
                                </div>
                                <div>
                                    <label class="block text-sm text-slate-300 mb-1"><?= $_d('Titre de votre avis','Review title') ?></label>
                                    <input type="text" name="title" class="shop-input">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm text-slate-300 mb-1"><?= $_d('Note','Rating') ?></label>
                                <div class="flex gap-1 text-2xl star-rating-input" id="star-picker">
                                    <?php for($i=1;$i<=5;$i++): ?>
                                    <i class="far fa-star cursor-pointer text-slate-600 hover:text-amber-400 transition-colors"
                                       data-star="<?php echo $i; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <input type="hidden" name="rating" id="rating-val" value="5">
                            </div>
                            <div>
                                <label class="block text-sm text-slate-300 mb-1"><?= $_d('Votre avis','Your review') ?></label>
                                <textarea name="review" rows="4" class="shop-input resize-none" required></textarea>
                            </div>
                            <button type="submit" class="btn-primary text-sm py-2.5 px-5">
                                <i class="fas fa-paper-plane mr-2"></i><?= $_d('Soumettre mon avis','Submit review') ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Similar Products -->
        <?php if (!empty($similar_products)): ?>
        <div class="mt-12" data-aos="fade-up">
            <h2 class="text-2xl font-bold text-white mb-6"><?= $_d('Produits similaires','Similar products') ?></h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-5">
                <?php foreach ($similar_products as $sp): ?>
                <div class="product-card">
                    <div class="relative h-48">
                        <?php $spImg = empty($sp['images']) ? '../image/oops.avif' : '../'.$sp['images'][0]; ?>
                        <img src="<?php echo htmlspecialchars($spImg); ?>" alt="<?php echo htmlspecialchars($sp['name']); ?>" class="w-full h-full object-cover">
                        <div class="absolute top-2 left-2 cat-badge"><?php echo htmlspecialchars($sp['category_name']); ?></div>
                        <form method="POST" class="absolute top-2 right-2">
                            <input type="hidden" name="product_id" value="<?php echo $sp['id']; ?>">
                            <?php if (in_array($sp['id'], $_SESSION['favorites'])): ?>
                            <input type="hidden" name="action" value="remove_from_favorites">
                            <button type="submit" class="bg-red-500/80 backdrop-blur-sm text-white h-7 w-7 rounded-full flex items-center justify-center hover:bg-red-500 transition-colors"><i class="fas fa-heart text-xs"></i></button>
                            <?php else: ?>
                            <input type="hidden" name="action" value="add_to_favorites">
                            <button type="submit" class="bg-white/10 backdrop-blur-sm text-white h-7 w-7 rounded-full flex items-center justify-center hover:bg-white/20 transition-colors"><i class="far fa-heart text-xs"></i></button>
                            <?php endif; ?>
                        </form>
                        <?php if ($sp['stock'] == 0): ?>
                        <div class="absolute bottom-2 left-2 bg-red-500/90 text-white text-xs font-bold px-2 py-0.5 rounded-full"><?= $_d('Rupture','Out of stock') ?></div>
                        <?php elseif ($sp['stock'] <= 5): ?>
                        <div class="absolute bottom-2 left-2 bg-amber-500/90 text-white text-xs font-bold px-2 py-0.5 rounded-full"><?= $_d('+que','Only') ?> <?php echo $sp['stock']; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="p-4">
                        <h3 class="font-bold text-white text-sm line-clamp-1 mb-2"><?php echo htmlspecialchars($sp['name']); ?></h3>
                        <div class="flex justify-between items-center mb-3">
                            <span class="price-tag"><?php echo number_format($sp['price'], 2); ?> FCFA</span>
                            <span class="text-slate-400 text-xs"><?php echo $sp['weight']; ?> kg</span>
                        </div>
                        <a href="product.php?id=<?php echo $sp['id']; ?>" class="btn-secondary w-full text-xs py-2 justify-center"><?= $_d('Voir le produit','View product') ?></a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Toasts -->
<div id="cart-toast"       class="fixed bottom-6 right-6 glass text-white px-5 py-3 rounded-xl shadow-2xl transform translate-y-24 opacity-0 transition-all duration-300 flex items-center gap-3 z-50"><i class="fas fa-check-circle text-nc-green text-lg"></i><span><?= $_d('Produit ajouté au panier','Product added to cart') ?></span></div>
<div id="fav-toast"        class="fixed bottom-6 right-6 glass text-white px-5 py-3 rounded-xl shadow-2xl transform translate-y-24 opacity-0 transition-all duration-300 flex items-center gap-3 z-50"><i class="fas fa-heart text-nc-cyan text-lg"></i><span><?= $_d('Produit ajouté aux favoris','Product added to favourites') ?></span></div>
<div id="fav-removed-toast" class="fixed bottom-6 right-6 glass text-white px-5 py-3 rounded-xl shadow-2xl transform translate-y-24 opacity-0 transition-all duration-300 flex items-center gap-3 z-50"><i class="fas fa-heart-broken text-red-400 text-lg"></i><span><?= $_d('Produit retiré des favoris','Product removed from favourites') ?></span></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Swiper
    const productSwiper = new Swiper('.product-swiper', {
        loop: <?php echo count($images) > 1 ? 'true' : 'false'; ?>,
        pagination: { el: '.swiper-pagination', clickable: true },
        navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
        autoplay: { delay: 5000, disableOnInteraction: false },
    });
    <?php if (count($images) > 1): ?>
    const thumbsSwiper = new Swiper('.thumbs-swiper', { slidesPerView: 4, spaceBetween: 8, freeMode: true, watchSlidesProgress: true });
    productSwiper.controller.control = thumbsSwiper;
    thumbsSwiper.controller.control = productSwiper;
    document.querySelectorAll('.thumb-slide').forEach((s, i) => {
        s.addEventListener('click', () => {
            productSwiper.slideTo(i);
            document.querySelectorAll('.thumb-slide').forEach(x => x.classList.remove('thumb-slide-active'));
            s.classList.add('thumb-slide-active');
        });
    });
    productSwiper.on('slideChange', function() {
        document.querySelectorAll('.thumb-slide').forEach(x => x.classList.remove('thumb-slide-active'));
        const el = document.querySelectorAll('.thumb-slide')[productSwiper.realIndex];
        if (el) el.classList.add('thumb-slide-active');
    });
    <?php endif; ?>

    // Tabs
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tab-button').forEach(b => { b.classList.remove('tab-active','text-nc-cyan'); b.classList.add('text-slate-400','border-transparent'); });
            document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
            btn.classList.add('tab-active','text-nc-cyan'); btn.classList.remove('text-slate-400','border-transparent');
            document.getElementById(btn.getAttribute('data-tab') + '-tab').classList.remove('hidden');
        });
    });

    // Quantity
    const qInput = document.getElementById('quantity');
    document.getElementById('increment-btn')?.addEventListener('click', () => { const max = parseInt(qInput.getAttribute('max')); if (parseInt(qInput.value) < max) qInput.value = parseInt(qInput.value) + 1; });
    document.getElementById('decrement-btn')?.addEventListener('click', () => { if (parseInt(qInput.value) > 1) qInput.value = parseInt(qInput.value) - 1; });

    // Favorite toggle
    document.getElementById('toggle-favorite')?.addEventListener('click', () => document.getElementById('favorite-form').submit());

    // Toasts
    <?php if (isset($_GET['cart_added'])): ?>showToast('cart-toast');<?php endif; ?>
    <?php if (isset($_GET['fav_added'])): ?>showToast('fav-toast');<?php endif; ?>
    <?php if (isset($_GET['fav_removed'])): ?>showToast('fav-removed-toast');<?php endif; ?>
});

function showToast(id) {
    const t = document.getElementById(id);
    if (!t) return;
    t.classList.remove('translate-y-24','opacity-0'); t.classList.add('translate-y-0','opacity-100');
    setTimeout(() => { t.classList.remove('translate-y-0','opacity-100'); t.classList.add('translate-y-24','opacity-0'); }, 3000);
}
</script>

<script>
/* ── Stock alert ─────────────────────────────────── */
function subscribeStockAlert() {
    const email = document.getElementById('alert-email').value.trim();
    const msg   = document.getElementById('alert-msg');
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        msg.textContent = 'Email invalide.';
        msg.className = 'text-xs mt-2 text-red-400';
        return;
    }
    const fd = new FormData();
    fd.append('product_id', '<?php echo $product_id; ?>');
    fd.append('email', email);
    fetch('stock-alert-ajax.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            msg.textContent = d.ok ? 'Vous serez alerté dès le retour en stock !' : (d.msg || 'Erreur.');
            msg.className = 'text-xs mt-2 ' + (d.ok ? 'text-nc-green' : 'text-red-400');
            msg.classList.remove('hidden');
            if (d.ok) document.getElementById('alert-email').value = '';
        })
        .catch(function() {
            msg.textContent = 'Erreur réseau.';
            msg.className = 'text-xs mt-2 text-red-400';
            msg.classList.remove('hidden');
        });
}

/* ── Compare ─────────────────────────────────────── */
var ncCompare = JSON.parse(localStorage.getItem('nc_compare') || '[]');

function toggleCompareProduct(id, name) {
    var idx = ncCompare.findIndex(function(x) { return x.id === id; });
    var btn = document.getElementById('compare-btn');
    var lbl = document.getElementById('compare-btn-label');
    if (idx >= 0) {
        ncCompare.splice(idx, 1);
        if (lbl) lbl.textContent = 'Ajouter à la comparaison';
        if (btn) { btn.style.color = '#94a3b8'; btn.style.borderColor = 'rgba(0,200,255,0.2)'; }
    } else {
        if (ncCompare.length >= 4) { alert('Maximum 4 produits comparables.'); return; }
        ncCompare.push({ id: id, name: name });
        if (lbl) lbl.textContent = 'Retiré de la comparaison';
        if (btn) { btn.style.color = '#00c8ff'; btn.style.borderColor = 'rgba(0,200,255,0.5)'; }
        if (ncCompare.length >= 2) {
            var link = 'compare.php?' + ncCompare.map(function(x) { return 'ids[]=' + x.id; }).join('&');
            setTimeout(function() {
                if (confirm('Comparer les ' + ncCompare.length + ' produits sélectionnés ?')) {
                    window.location.href = link;
                }
            }, 300);
        }
    }
    localStorage.setItem('nc_compare', JSON.stringify(ncCompare));
}

document.addEventListener('DOMContentLoaded', function() {
    // Init compare button state
    var inList = ncCompare.some(function(x) { return x.id === <?php echo $product_id; ?>; });
    var btn = document.getElementById('compare-btn');
    var lbl = document.getElementById('compare-btn-label');
    if (inList && btn && lbl) {
        lbl.textContent = 'Retiré de la comparaison';
        btn.style.color = '#00c8ff';
        btn.style.borderColor = 'rgba(0,200,255,0.5)';
    }

    /* ── Star picker ─────────────────────────────── */
    const stars = document.querySelectorAll('#star-picker i');
    const ratingInput = document.getElementById('rating-val');
    stars.forEach(function(star, i) {
        star.addEventListener('click', function() {
            ratingInput.value = i + 1;
            stars.forEach(function(s, j) {
                s.className = j <= i
                    ? 'fas fa-star cursor-pointer text-amber-400 transition-colors'
                    : 'far fa-star cursor-pointer text-slate-600 hover:text-amber-400 transition-colors';
            });
        });
        star.addEventListener('mouseenter', function() {
            stars.forEach(function(s, j) {
                s.classList.toggle('text-amber-400', j <= i);
                s.classList.toggle('text-slate-600', j > i);
            });
        });
    });
    document.getElementById('star-picker')?.addEventListener('mouseleave', function() {
        const cur = parseInt(ratingInput.value) - 1;
        stars.forEach(function(s, j) {
            s.className = j <= cur
                ? 'fas fa-star cursor-pointer text-amber-400 transition-colors'
                : 'far fa-star cursor-pointer text-slate-600 hover:text-amber-400 transition-colors';
        });
    });
    // Default 5 stars
    stars.forEach(function(s) { s.className = 'fas fa-star cursor-pointer text-amber-400 transition-colors'; });

    /* ── AJAX Review Form ────────────────────────── */
    document.getElementById('review-form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Envoi…';
        fetch('reviews-ajax.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.ok) {
                    document.getElementById('review-success-msg').textContent = d.msg;
                    document.getElementById('review-success').classList.remove('hidden');
                    document.getElementById('review-form').reset();
                    // Reset stars
                    ratingInput.value = 5;
                    stars.forEach(function(s) { s.className = 'fas fa-star cursor-pointer text-amber-400 transition-colors'; });
                }
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i>Soumettre mon avis';
            })
            .catch(function() {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i>Soumettre mon avis';
            });
    });
});
</script>

<?php include '../includes/footer.php'; ?>
