<?php
session_start();

require_once __DIR__ . '/db.php';

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Échec de la connexion: " . $conn->connect_error); }
$conn->set_charset("utf8");

if (!isset($_SESSION['cart']))      $_SESSION['cart']      = [];
if (!isset($_SESSION['favorites'])) $_SESSION['favorites'] = [];

// Traitement des actions
if (isset($_POST['action'])) {
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

    if ($_POST['action'] === 'remove_from_cart') {
        if (array_key_exists($product_id, $_SESSION['cart'])) {
            unset($_SESSION['cart'][$product_id]);
            $session_id = session_id();
            $session_data = json_encode(['cart' => $_SESSION['cart'], 'favorites' => $_SESSION['favorites']]);
            $stmt = $conn->prepare("UPDATE sessions SET data = ? WHERE id = ?");
            $stmt->bind_param("ss", $session_data, $session_id);
            $stmt->execute();
        }
        header("Location: cart.php?removed=1"); exit;
    }
    elseif ($_POST['action'] === 'update_quantity') {
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
        if ($quantity > 0) {
            $_SESSION['cart'][$product_id] = $quantity;
            $session_id = session_id();
            $session_data = json_encode(['cart' => $_SESSION['cart'], 'favorites' => $_SESSION['favorites']]);
            $stmt = $conn->prepare("UPDATE sessions SET data = ? WHERE id = ?");
            $stmt->bind_param("ss", $session_data, $session_id);
            $stmt->execute();
        }
        header("Location: cart.php?updated=1"); exit;
    }
    elseif ($_POST['action'] === 'empty_cart') {
        $_SESSION['cart'] = [];
        $session_id = session_id();
        $session_data = json_encode(['cart' => [], 'favorites' => $_SESSION['favorites']]);
        $stmt = $conn->prepare("UPDATE sessions SET data = ? WHERE id = ?");
        $stmt->bind_param("ss", $session_data, $session_id);
        $stmt->execute();
        header("Location: cart.php?emptied=1"); exit;
    }
    elseif ($_POST['action'] === 'add_to_favorites') {
        if (!in_array($product_id, $_SESSION['favorites'])) {
            $_SESSION['favorites'][] = $product_id;
            $session_id = session_id();
            $session_data = json_encode(['cart' => $_SESSION['cart'], 'favorites' => $_SESSION['favorites']]);
            $stmt = $conn->prepare("UPDATE sessions SET data = ? WHERE id = ?");
            $stmt->bind_param("ss", $session_data, $session_id);
            $stmt->execute();
        }
        header("Location: cart.php?fav_added=1"); exit;
    }
}

// Récupération des produits du panier
$cart_items = [];
$total_price = 0;
$shipping_cost = 0;
$total_weight = 0;
$default_currency = 'FCFA';

if (!empty($_SESSION['cart'])) {
    $product_ids = array_keys($_SESSION['cart']);
    $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
    $sql = "SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.id IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    $param_types = str_repeat('i', count($product_ids));
    $stmt->bind_param($param_types, ...$product_ids);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        while ($product = $result->fetch_assoc()) {
            $product_id = $product['id'];
            $quantity = $_SESSION['cart'][$product_id];

            $img_stmt = $conn->prepare("SELECT image_url FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, display_order ASC LIMIT 1");
            $img_stmt->bind_param("i", $product_id);
            $img_stmt->execute();
            $img_result = $img_stmt->get_result();
            $product['image'] = ($img_result && $img_result->num_rows > 0) ? $img_result->fetch_assoc()['image_url'] : '../image/oops.avif';

            $product['quantity'] = $quantity;
            $product['subtotal'] = $product['price'] * $quantity;
            $total_price += $product['subtotal'];
            $total_weight += $product['weight'] * $quantity;
            $cart_items[] = $product;
        }
    }

    if ($total_price < 50) {
        $shipping_cost = 5.99 + ($total_weight > 5 ? ($total_weight - 5) * 0.5 : 0);
    }
}

$conn->close();

// Apply promo discount
$discount   = 0;
$promo      = $_SESSION['promo'] ?? null;
if ($promo && $total_price > 0) {
    if (!empty($promo['percent'])) $discount += $total_price * $promo['percent'] / 100;
    if (!empty($promo['fixed']))   $discount += $promo['fixed'];
    $discount = min(round($discount, 2), $total_price);
}
$final_total = max(0, $total_price - $discount) + $shipping_cost;
?>
<?php
$page_title = 'Mon Panier - Netcrafter';
include '../includes/header.php';
include 'shop-theme.php';
?>

<!-- Page Header -->
<section class="shop-hero">
    <div class="blob w-72 h-72 bg-nc-blue" style="top:-100px;left:-80px;"></div>
    <div class="blob w-56 h-56 bg-nc-cyan" style="bottom:-60px;right:8%;"></div>
    <div class="max-w-7xl mx-auto px-4 relative z-10 text-center">
        <span class="badge mb-4 inline-flex"><i class="fas fa-shopping-cart mr-1"></i> <?= t('shop.cart_title') ?></span>
        <h1 class="text-3xl md:text-4xl font-bold text-white mb-3" data-aos="fade-up"><?= t('shop.cart_title') ?></h1>
        <p class="text-slate-300 text-lg max-w-2xl mx-auto" data-aos="fade-up" data-aos-delay="100">
            Gérez vos articles et finalisez votre commande
        </p>
    </div>
</section>

<!-- Main Cart Section -->
<section class="py-10 md:py-14">
    <div class="max-w-7xl mx-auto px-4">
        <!-- Breadcrumb -->
        <nav class="flex mb-6 text-sm text-slate-400">
            <a href="shop.php" class="hover:text-nc-cyan transition-colors">Boutique</a>
            <span class="mx-2 text-white/20">/</span>
            <span class="text-white">Panier</span>
        </nav>

        <?php if (!empty($cart_items)): ?>
        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Cart Items -->
            <div class="lg:w-2/3">
                <div class="shop-card overflow-hidden" data-aos="fade-up">
                    <div class="p-4 border-b border-white/10 flex justify-between items-center">
                        <h2 class="text-xl font-bold text-white">Articles (<?php echo array_sum($_SESSION['cart']); ?>)</h2>
                        <form method="POST" onsubmit="return confirm('Vider le panier ?');">
                            <input type="hidden" name="action" value="empty_cart">
                            <button type="submit" class="text-red-400 hover:text-red-300 text-sm flex items-center gap-2 transition-colors">
                                <i class="fas fa-trash-alt"></i><span class="hidden sm:inline">Vider le panier</span>
                            </button>
                        </form>
                    </div>

                    <ul class="divide-y divide-white/5">
                        <?php foreach ($cart_items as $item): ?>
                        <li class="cart-item p-4 flex flex-col sm:flex-row gap-4">
                            <div class="sm:w-24 flex-shrink-0">
                                <a href="product.php?id=<?php echo $item['id']; ?>">
                                    <img src="../<?php echo htmlspecialchars($item['image']); ?>"
                                         alt="<?php echo htmlspecialchars($item['name']); ?>"
                                         class="w-24 h-24 object-cover rounded-xl">
                                </a>
                            </div>
                            <div class="flex-1 flex flex-col justify-between">
                                <div>
                                    <div class="flex justify-between items-start">
                                        <a href="product.php?id=<?php echo $item['id']; ?>"
                                           class="font-semibold text-white hover:text-nc-cyan transition-colors">
                                            <?php echo htmlspecialchars($item['name']); ?>
                                        </a>
                                        <span class="price-tag font-bold ml-2 whitespace-nowrap">
                                            <?php echo number_format($item['price'], 2) . ' ' . $default_currency; ?>
                                        </span>
                                    </div>
                                    <p class="text-sm text-slate-400 mt-1">Catégorie : <?php echo htmlspecialchars($item['category_name']); ?></p>
                                    <?php if ($item['stock'] <= 5): ?>
                                    <p class="text-xs text-amber-400 mt-1"><i class="fas fa-exclamation-circle mr-1"></i>Plus que <?php echo $item['stock']; ?> disponible(s)</p>
                                    <?php endif; ?>
                                </div>
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between mt-3 gap-3">
                                    <div class="flex items-center">
                                        <form method="POST" class="flex items-center gap-1">
                                            <input type="hidden" name="action" value="update_quantity">
                                            <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                            <button type="button" class="quantity-decrease bg-white/10 text-white w-8 h-8 rounded-lg flex items-center justify-center hover:bg-white/20 transition-colors">
                                                <i class="fas fa-minus text-xs"></i>
                                            </button>
                                            <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>"
                                                   min="1" max="<?php echo $item['stock']; ?>"
                                                   class="quantity-input no-arrows w-14 h-8 bg-white/5 border border-white/10 rounded-lg text-center text-white text-sm">
                                            <button type="button" class="quantity-increase bg-white/10 text-white w-8 h-8 rounded-lg flex items-center justify-center hover:bg-white/20 transition-colors">
                                                <i class="fas fa-plus text-xs"></i>
                                            </button>
                                            <button type="submit" class="update-quantity ml-1 text-nc-cyan hover:text-nc-light transition-colors text-sm">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                        </form>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <span class="text-slate-400 text-sm">
                                            Sous-total : <span class="font-bold text-white"><?php echo number_format($item['subtotal'], 2) . ' ' . $default_currency; ?></span>
                                        </span>
                                        <div class="flex gap-3">
                                            <form method="POST">
                                                <input type="hidden" name="action" value="add_to_favorites">
                                                <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" class="text-slate-400 hover:text-red-400 transition-colors" title="Ajouter aux favoris">
                                                    <i class="far fa-heart"></i>
                                                </button>
                                            </form>
                                            <form method="POST" onsubmit="return confirm('Supprimer cet article ?');">
                                                <input type="hidden" name="action" value="remove_from_cart">
                                                <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" class="text-slate-400 hover:text-red-400 transition-colors" title="Supprimer">
                                                    <i class="far fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="p-4 border-t border-white/10">
                        <a href="shop.php" class="text-nc-cyan hover:text-nc-light transition-colors text-sm flex items-center gap-2">
                            <i class="fas fa-arrow-left"></i> Continuer vos achats
                        </a>
                    </div>
                </div>

                <!-- Delivery Info -->
                <div class="shop-card p-6 mt-6" data-aos="fade-up">
                    <h3 class="text-lg font-bold text-white mb-4">Informations de livraison</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <?php
                        $delivery_items = [
                            ['icon' => 'fas fa-shipping-fast', 'title' => 'Livraison rapide',   'desc' => 'Expédition sous 24-48h'],
                            ['icon' => 'fas fa-globe',         'title' => 'Livraison mondiale', 'desc' => 'Via Joegol Logistics'],
                            ['icon' => 'fas fa-undo-alt',      'title' => 'Retours faciles',    'desc' => '30 jours pour retourner'],
                        ];
                        foreach ($delivery_items as $d): ?>
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-xl bg-nc-cyan/10 flex items-center justify-center flex-shrink-0">
                                <i class="<?php echo $d['icon']; ?> text-nc-cyan"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-white text-sm"><?php echo $d['title']; ?></h4>
                                <p class="text-slate-400 text-xs mt-0.5"><?php echo $d['desc']; ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="lg:w-1/3">
                <div class="shop-card p-6 sticky top-24" data-aos="fade-left">
                    <h3 class="text-xl font-bold text-white mb-6">Récapitulatif</h3>

                    <div class="space-y-3 mb-6">
                        <div class="flex justify-between text-slate-300">
                            <span><?= t('shop.subtotal') ?></span>
                            <span class="font-medium text-white"><?php echo number_format($total_price, 2) . ' ' . $default_currency; ?></span>
                        </div>
                        <div class="flex justify-between text-slate-300">
                            <span>Frais de livraison</span>
                            <?php if ($shipping_cost > 0): ?>
                            <span class="font-medium text-white"><?php echo number_format($shipping_cost, 2) . ' ' . $default_currency; ?></span>
                            <?php else: ?>
                            <span class="font-medium text-nc-green">Gratuit</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($shipping_cost > 0): ?>
                        <div class="text-xs text-slate-400 italic">
                            Livraison gratuite à partir de 50 FCFA d'achat
                            <div class="mt-1 bg-white/10 rounded-full h-1.5">
                                <div class="bg-nc-cyan h-1.5 rounded-full" style="width:<?php echo min(100, ($total_price/50)*100); ?>%"></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if ($discount > 0): ?>
                        <div class="flex justify-between text-nc-green">
                            <span class="flex items-center gap-2">
                                <i class="fas fa-tag text-xs"></i>
                                Code <strong><?= htmlspecialchars($promo['code']) ?></strong>
                                <?php if (!empty($promo['percent'])): ?>(<?= $promo['percent'] ?>%)<?php endif; ?>
                            </span>
                            <span class="font-medium">-<?php echo number_format($discount, 2) . ' ' . $default_currency; ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="pt-4 border-t border-white/10 flex justify-between">
                            <span class="font-bold text-white"><?= t('shop.total') ?></span>
                            <span class="font-bold text-2xl price-tag"><?php echo number_format($final_total, 2) . ' ' . $default_currency; ?></span>
                        </div>
                    </div>

                    <!-- Coupon -->
                    <div class="mb-5">
                        <label class="block text-sm text-slate-300 mb-2">Code promo</label>
                        <?php if ($promo && $discount > 0): ?>
                        <div class="flex items-center justify-between rounded-xl px-4 py-3 text-sm"
                             style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3)">
                            <span class="text-nc-green"><i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($promo['code']) ?> appliqué</span>
                            <button onclick="removePromo()" class="text-slate-500 hover:text-red-400 text-xs transition-colors">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <?php else: ?>
                        <div class="flex gap-2">
                            <input type="text" id="promo-input" placeholder="Votre code promo"
                                   class="shop-input flex-1 uppercase" style="letter-spacing:0.05em">
                            <button onclick="applyPromo()"
                                    id="promo-btn"
                                    class="btn-secondary py-2 px-4 text-sm whitespace-nowrap">
                                Appliquer
                            </button>
                        </div>
                        <p id="promo-msg" class="text-xs mt-1.5 hidden"></p>
                        <?php endif; ?>
                    </div>

                    <a href="checkout.php" class="btn-primary w-full justify-center mb-4">
                        <i class="fas fa-lock text-sm"></i> <?= t('shop.checkout') ?>
                    </a>

                    <div class="flex justify-center gap-3 text-slate-500 text-2xl">
                        <i class="fab fa-cc-visa"></i>
                        <i class="fab fa-cc-mastercard"></i>
                        <i class="fab fa-cc-paypal"></i>
                    </div>
                    <p class="text-center text-xs text-slate-500 mt-3"><i class="fas fa-lock mr-1"></i>Paiement 100% sécurisé</p>
                </div>
            </div>
        </div>

        <?php else: ?>
        <div class="shop-card p-12 text-center max-w-lg mx-auto" data-aos="fade-up">
            <div class="text-6xl mb-6 text-slate-600"><i class="fas fa-shopping-cart"></i></div>
            <h2 class="text-2xl font-bold text-white mb-3"><?= t('shop.cart_empty') ?></h2>
            <p class="text-slate-400 mb-6">Vous n'avez pas encore ajouté d'articles. Découvrez notre sélection de produits.</p>
            <a href="shop.php" class="btn-primary"><i class="fas fa-shopping-basket text-sm"></i> Découvrir nos produits</a>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Toast Notifications -->
<div id="cart-updated-toast" class="fixed bottom-6 right-6 glass text-white px-5 py-3 rounded-xl shadow-2xl transform translate-y-24 opacity-0 transition-all duration-300 flex items-center gap-3 z-50">
    <i class="fas fa-check-circle text-nc-green text-lg"></i><span>Panier mis à jour</span>
</div>
<div id="item-removed-toast" class="fixed bottom-6 right-6 glass text-white px-5 py-3 rounded-xl shadow-2xl transform translate-y-24 opacity-0 transition-all duration-300 flex items-center gap-3 z-50">
    <i class="fas fa-trash-alt text-red-400 text-lg"></i><span>Article supprimé du panier</span>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Quantity controls
    const qInputs  = document.querySelectorAll('.quantity-input');
    const decBtns  = document.querySelectorAll('.quantity-decrease');
    const incBtns  = document.querySelectorAll('.quantity-increase');
    const updBtns  = document.querySelectorAll('.update-quantity');

    for (let i = 0; i < qInputs.length; i++) {
        const input  = qInputs[i];
        const decBtn = decBtns[i];
        const incBtn = incBtns[i];
        const updBtn = updBtns[i];

        decBtn.addEventListener('click', () => { if (parseInt(input.value) > 1) input.value = parseInt(input.value) - 1; });
        incBtn.addEventListener('click', () => { const max = parseInt(input.getAttribute('max')); if (parseInt(input.value) < max) input.value = parseInt(input.value) + 1; });
        input.addEventListener('change', () => { updBtn.classList.add('text-nc-cyan'); });
    }

    <?php if (isset($_GET['updated'])): ?>showToast('cart-updated-toast');<?php endif; ?>
    <?php if (isset($_GET['removed'])): ?>showToast('item-removed-toast');<?php endif; ?>
});
function showToast(id) {
    const t = document.getElementById(id);
    if (!t) return;
    t.classList.remove('translate-y-24','opacity-0'); t.classList.add('translate-y-0','opacity-100');
    setTimeout(() => { t.classList.remove('translate-y-0','opacity-100'); t.classList.add('translate-y-24','opacity-0'); }, 3000);
}
</script>

<script>
function applyPromo() {
    const input = document.getElementById('promo-input');
    const btn   = document.getElementById('promo-btn');
    const msg   = document.getElementById('promo-msg');
    const code  = input?.value.trim().toUpperCase();
    if (!code) return;

    btn.disabled = true;
    btn.textContent = '…';
    const fd = new FormData();
    fd.append('code', code);

    fetch('apply-promo.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            btn.disabled = false;
            btn.textContent = 'Appliquer';
            msg.classList.remove('hidden');
            if (d.ok) {
                msg.textContent = 'Code appliqué avec succès !';
                msg.className = 'text-xs mt-1.5 text-nc-green';
                setTimeout(function() { location.reload(); }, 800);
            } else {
                msg.textContent = d.msg || 'Code invalide ou expiré.';
                msg.className = 'text-xs mt-1.5 text-red-400';
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.textContent = 'Appliquer';
            if (msg) { msg.textContent = 'Erreur réseau.'; msg.className = 'text-xs mt-1.5 text-red-400'; msg.classList.remove('hidden'); }
        });
}

function removePromo() {
    fetch('apply-promo.php', { method: 'POST', body: new URLSearchParams({ code: '__REMOVE__' }) })
        .catch(function() {})
        .finally(function() {
            fetch('remove-promo.php').catch(function(){}).finally(function() { location.reload(); });
        });
}

document.getElementById('promo-input')?.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); applyPromo(); }
});
</script>

<?php include '../includes/footer.php'; ?>
