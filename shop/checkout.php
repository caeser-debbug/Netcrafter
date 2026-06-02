<?php
session_start();

require_once __DIR__ . '/db.php';

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Échec de la connexion: " . $conn->connect_error); }
$conn->set_charset("utf8");

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

$order_submitted = false;
$order_number    = '';

$villes_niger = [
    "Niamey","Zinder","Maradi","Tahoua","Agadez","Diffa","Dosso","Tillabéri",
    "Arlit","Birni N'Konni","Dogondoutchi","Gaya","Madaoua","Mirriah","N'Guigmi",
    "Téra","Tessaoua","Tibiri","Tanout","Magaria","Illéla","Matameye"
];

$default_currency = 'FCFA';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_order'])) {
    if (empty($_SESSION['cart'])) {
        $error_message = "Votre panier est vide.";
    } else {
        $full_name      = trim($_POST['full_name']      ?? '');
        $phone          = trim($_POST['phone']          ?? '');
        $email          = trim($_POST['email']          ?? '');
        $city           = trim($_POST['city']           ?? '');
        $address        = trim($_POST['address']        ?? '');
        $payment_method = trim($_POST['payment_method'] ?? '');
        $notes          = trim($_POST['notes']          ?? '');

        $errors = [];
        if (empty($full_name))      $errors[] = "Le nom complet est requis.";
        if (empty($phone))          $errors[] = "Le téléphone est requis.";
        if (empty($city))           $errors[] = "La ville est requise.";
        if (empty($address))        $errors[] = "L'adresse est requise.";
        if (empty($payment_method)) $errors[] = "La méthode de paiement est requise.";
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email invalide.";

        $receipt_path = '';
        $id_card_path = '';
        $allowed_ext  = ["jpg","jpeg","png","pdf"];

        if (isset($_FILES['payment_receipt']) && $_FILES['payment_receipt']['size'] > 0) {
            $ext = strtolower(pathinfo($_FILES['payment_receipt']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_ext)) $errors[] = "Format de reçu invalide (JPG, PNG, PDF).";
            if ($_FILES['payment_receipt']['size'] > 5000000) $errors[] = "Reçu trop volumineux (max 5MB).";
            if (empty($errors)) {
                $dir = "uploads/receipts/";
                if (!file_exists($dir)) mkdir($dir, 0777, true);
                $fn = $dir . uniqid() . '_receipt.' . $ext;
                if (move_uploaded_file($_FILES['payment_receipt']['tmp_name'], $fn)) $receipt_path = $fn;
                else $errors[] = "Erreur upload reçu.";
            }
        }

        if (isset($_FILES['id_card']) && $_FILES['id_card']['size'] > 0) {
            $ext = strtolower(pathinfo($_FILES['id_card']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_ext)) $errors[] = "Format carte d'identité invalide.";
            if ($_FILES['id_card']['size'] > 5000000) $errors[] = "Carte d'identité trop volumineuse (max 5MB).";
            if (empty($errors)) {
                $dir = "uploads/id_cards/";
                if (!file_exists($dir)) mkdir($dir, 0777, true);
                $fn = $dir . uniqid() . '_id_card.' . $ext;
                if (move_uploaded_file($_FILES['id_card']['tmp_name'], $fn)) $id_card_path = $fn;
                else $errors[] = "Erreur upload carte d'identité.";
            }
        } else {
            $errors[] = "La copie de la carte d'identité est requise.";
        }

        if (empty($errors)) {
            $conn->begin_transaction();
            try {
                $order_number = 'NC-' . date('Ymd') . '-' . rand(1000, 9999);
                $mac_address  = $_SERVER['HTTP_USER_AGENT'] ?? $_SERVER['REMOTE_ADDR'];
                $total_amount = 0;
                $shipping_cost = 0;
                $cart_items = [];

                if (!empty($_SESSION['cart'])) {
                    $pids = array_keys($_SESSION['cart']);
                    $ph   = str_repeat('?,', count($pids)-1).'?';
                    $st   = $conn->prepare("SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id=c.id WHERE p.id IN ($ph)");
                    $pt   = str_repeat('i', count($pids));
                    $st->bind_param($pt, ...$pids);
                    $st->execute();
                    $res = $st->get_result();
                    while ($p = $res->fetch_assoc()) {
                        $p['quantity'] = $_SESSION['cart'][$p['id']];
                        $p['subtotal'] = $p['price'] * $p['quantity'];
                        $total_amount += $p['subtotal'];
                        $cart_items[] = $p;
                    }
                    if ($total_amount < 50) $shipping_cost = 5.99;
                }

                $sa = $conn->prepare("INSERT INTO addresses (street_address, city, country, postal_code, is_default) VALUES (?, ?, 'Niger', '', 1)");
                $sa->bind_param("ss", $address, $city); $sa->execute();
                $shipping_address_id = $conn->insert_id;

                $so = $conn->prepare("INSERT INTO orders (order_number, total_amount, shipping_cost, shipping_address_id, payment_method, payment_status, order_status, notes, mac_address) VALUES (?, ?, ?, ?, ?, 'pending', 'pending', ?, ?)");
                $so->bind_param("sddisss", $order_number, $total_amount, $shipping_cost, $shipping_address_id, $payment_method, $notes, $mac_address);
                $so->execute();
                $order_id = $conn->insert_id;

                foreach ($cart_items as $ci) {
                    $si = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                    $si->bind_param("iiid", $order_id, $ci['id'], $ci['quantity'], $ci['price']); $si->execute();
                    $ss = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
                    $ss->bind_param("ii", $ci['quantity'], $ci['id']); $ss->execute();
                }

                $tr = $conn->prepare("INSERT INTO order_tracking (order_id, status, notes) VALUES (?, 'processing', 'Commande reçue')");
                $tr->bind_param("i", $order_id); $tr->execute();

                $conn->commit();
                $_SESSION['cart'] = [];
                $sid = session_id();
                $sd  = json_encode(['cart'=>[], 'favorites'=> $_SESSION['favorites'] ?? []]);
                $su  = $conn->prepare("UPDATE sessions SET data = ? WHERE id = ?");
                $su->bind_param("ss", $sd, $sid); $su->execute();
                $order_submitted = true;

            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = "Erreur lors de la création de votre commande : " . $e->getMessage();
            }
        }
    }
}

// Données panier pour affichage
$cart_items  = [];
$total_price = 0;
$shipping_cost = 0;
$total_weight  = 0;

if (!empty($_SESSION['cart'])) {
    $pids = array_keys($_SESSION['cart']);
    $ph   = str_repeat('?,', count($pids)-1).'?';
    $st   = $conn->prepare("SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id=c.id WHERE p.id IN ($ph)");
    $pt   = str_repeat('i', count($pids));
    $st->bind_param($pt, ...$pids);
    $st->execute();
    $res = $st->get_result();

    while ($product = $res->fetch_assoc()) {
        $pid = $product['id']; $qty = $_SESSION['cart'][$pid];
        $is  = $conn->prepare("SELECT image_url FROM product_images WHERE product_id=? ORDER BY is_primary DESC LIMIT 1");
        $is->bind_param("i", $pid); $is->execute(); $ir = $is->get_result();
        $product['image']    = ($ir && $ir->num_rows > 0) ? $ir->fetch_assoc()['image_url'] : '../image/oops.avif';
        $product['quantity'] = $qty;
        $product['subtotal'] = $product['price'] * $qty;
        $total_price  += $product['subtotal'];
        $total_weight += $product['weight'] * $qty;
        $cart_items[] = $product;
    }
    if ($total_price < 50) $shipping_cost = 5.99 + ($total_weight > 5 ? ($total_weight-5)*0.5 : 0);
}

$conn->close();
?>
<?php
$page_title = 'Finaliser la commande - Netcrafter';
include '../includes/header.php';
include 'shop-theme.php';
?>

<!-- Page Header -->
<section class="shop-hero">
    <div class="blob w-72 h-72 bg-nc-blue" style="top:-100px;left:-80px;"></div>
    <div class="blob w-56 h-56 bg-nc-cyan" style="bottom:-60px;right:8%;"></div>
    <div class="max-w-7xl mx-auto px-4 relative z-10 text-center">
        <span class="badge mb-4 inline-flex"><i class="fas fa-lock mr-1"></i> Commande</span>
        <h1 class="text-3xl md:text-4xl font-bold text-white mb-3" data-aos="fade-up">Finaliser votre commande</h1>
        <p class="text-slate-300 text-lg max-w-2xl mx-auto" data-aos="fade-up" data-aos-delay="100">
            Remplissez les informations nécessaires pour compléter votre achat
        </p>
    </div>
</section>

<!-- Checkout Section -->
<section class="py-10 md:py-14">
    <div class="max-w-7xl mx-auto px-4">
        <!-- Breadcrumb -->
        <nav class="flex mb-6 text-sm text-slate-400 flex-wrap gap-1">
            <a href="shop.php" class="hover:text-nc-cyan transition-colors">Boutique</a>
            <span class="text-white/20">/</span>
            <a href="cart.php" class="hover:text-nc-cyan transition-colors">Panier</a>
            <span class="text-white/20">/</span>
            <span class="text-white">Finaliser la commande</span>
        </nav>

        <?php if ($order_submitted): ?>
        <!-- Success -->
        <div class="shop-card p-10 text-center max-w-2xl mx-auto" data-aos="fade-up">
            <div class="w-20 h-20 rounded-full bg-nc-green/20 border border-nc-green/40 flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-check text-nc-green text-3xl"></i>
            </div>
            <h2 class="text-2xl font-bold text-white mb-3">Commande reçue avec succès !</h2>
            <p class="text-slate-300 mb-6">
                Votre commande a été enregistrée sous le numéro <strong class="text-nc-cyan"><?php echo $order_number; ?></strong>.<br>
                Nous vous contacterons prochainement pour confirmer les détails.
            </p>
            <div class="bg-nc-green/10 border border-nc-green/20 rounded-xl p-4 mb-6 text-left text-sm text-slate-300">
                <strong class="text-white">Note :</strong> Pour les paiements mobile money (Nita, Amana, Zeyna, Niya), envoyez une capture d'écran sur WhatsApp au
                <a href="tel:+22788371817" class="text-nc-cyan font-bold">+227 88 37 18 17</a> en mentionnant votre numéro de commande.
            </div>
            <div class="flex flex-col sm:flex-row justify-center gap-3">
                <a href="shop.php" class="btn-primary"><i class="fas fa-shopping-basket text-sm"></i> Continuer vos achats</a>
                <a href="../index.php" class="btn-outline"><i class="fas fa-home text-sm"></i> Accueil</a>
            </div>
        </div>

        <?php elseif (empty($_SESSION['cart'])): ?>
        <div class="shop-card p-12 text-center max-w-lg mx-auto" data-aos="fade-up">
            <div class="text-6xl text-slate-600 mb-6"><i class="fas fa-shopping-cart"></i></div>
            <h2 class="text-2xl font-bold text-white mb-3">Votre panier est vide</h2>
            <a href="shop.php" class="btn-primary mt-2"><i class="fas fa-shopping-basket text-sm"></i> Découvrir nos produits</a>
        </div>

        <?php else: ?>
        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Form -->
            <div class="lg:w-2/3 space-y-4">
                <?php if (!empty($errors)): ?>
                <div class="bg-red-500/10 border border-red-500/30 rounded-xl p-4" data-aos="fade-up">
                    <div class="flex gap-3">
                        <i class="fas fa-exclamation-circle text-red-400 text-lg mt-0.5"></i>
                        <div>
                            <h3 class="text-white font-semibold text-sm mb-2">Des erreurs ont été détectées</h3>
                            <ul class="list-disc list-inside space-y-1 text-sm text-red-300">
                                <?php foreach ($errors as $err): ?><li><?php echo htmlspecialchars($err); ?></li><?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Warning -->
                <div class="bg-amber-500/10 border border-amber-500/30 rounded-xl p-4" data-aos="fade-up">
                    <div class="flex gap-3">
                        <i class="fas fa-exclamation-triangle text-amber-400 text-lg mt-0.5"></i>
                        <div class="text-sm text-slate-300">
                            <strong class="text-white">Avertissement :</strong> Toute fausse commande fera l'objet de poursuites pénales conformément aux lois en vigueur au Niger. Une copie de votre carte d'identité est requise.
                        </div>
                    </div>
                </div>

                <form method="POST" enctype="multipart/form-data" class="space-y-4" data-aos="fade-up">
                    <!-- Personal info -->
                    <div class="shop-card p-6">
                        <h2 class="text-lg font-bold text-white mb-5 flex items-center gap-2">
                            <i class="fas fa-user-circle text-nc-cyan"></i> Informations personnelles
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-slate-300 mb-1.5">Nom complet <span class="text-red-400">*</span></label>
                                <input type="text" name="full_name" required placeholder="Votre nom et prénom" class="shop-input">
                            </div>
                            <div>
                                <label class="block text-sm text-slate-300 mb-1.5">Téléphone <span class="text-red-400">*</span></label>
                                <input type="tel" name="phone" required placeholder="+227 XX XX XX XX" class="shop-input">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm text-slate-300 mb-1.5">Email (facultatif)</label>
                                <input type="email" name="email" placeholder="votre@email.com" class="shop-input">
                            </div>
                        </div>
                    </div>

                    <!-- Delivery address -->
                    <div class="shop-card p-6">
                        <h2 class="text-lg font-bold text-white mb-5 flex items-center gap-2">
                            <i class="fas fa-map-marker-alt text-nc-cyan"></i> Adresse de livraison
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-slate-300 mb-1.5">Ville <span class="text-red-400">*</span></label>
                                <select name="city" required class="shop-select">
                                    <option value="">Sélectionnez une ville</option>
                                    <?php foreach ($villes_niger as $v): ?>
                                    <option value="<?php echo htmlspecialchars($v); ?>"><?php echo htmlspecialchars($v); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm text-slate-300 mb-1.5">Adresse détaillée <span class="text-red-400">*</span></label>
                                <input type="text" name="address" required placeholder="Quartier, rue, points de repère" class="shop-input">
                            </div>
                        </div>
                    </div>

                    <!-- Payment -->
                    <div class="shop-card p-6">
                        <h2 class="text-lg font-bold text-white mb-5 flex items-center gap-2">
                            <i class="fas fa-credit-card text-nc-cyan"></i> Méthode de paiement
                        </h2>
                        <p class="text-sm text-slate-400 mb-4">Sélectionnez votre méthode de paiement préférée.</p>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
                            <?php
                            $methods = [
                                ['Nita',  'nita.png',  'Nita'],
                                ['Amana', 'amana.png', 'Amana'],
                                ['Zeyna', 'zeyna.png', 'Zeyna'],
                                ['Niya',  'niya.png',  'Niya'],
                            ];
                            foreach ($methods as [$val, $img, $label]): ?>
                            <div class="payment-method-card p-3 text-center" data-method="<?php echo $val; ?>">
                                <input type="radio" name="payment_method" id="payment_<?php echo strtolower($val); ?>" value="<?php echo $val; ?>" class="hidden radio-nc">
                                <label for="payment_<?php echo strtolower($val); ?>" class="flex flex-col items-center gap-2 cursor-pointer">
                                    <img src="../image/payments/<?php echo $img; ?>" alt="<?php echo $label; ?>" class="h-10 object-contain">
                                    <span class="text-sm font-semibold text-white"><?php echo $label; ?></span>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div id="payment-info" class="hidden bg-white/5 border border-white/10 rounded-xl p-4 mb-5 text-sm text-slate-300">
                            <div id="nita-info"  class="payment-info hidden">Nita : <strong class="text-white">+227 88 37 18 17</strong> (Ali Abdoulaye Ousmane)</div>
                            <div id="amana-info" class="payment-info hidden">Amana : <strong class="text-white">+227 88 67 21 15</strong> (Ali Abdoulaye Ousmane)</div>
                            <div id="zeyna-info" class="payment-info hidden">Zeyna : <strong class="text-white">+227 XX XX XX XX</strong></div>
                            <div id="niya-info"  class="payment-info hidden">Niya : <strong class="text-white">+227 XX XX XX XX</strong></div>
                        </div>

                        <div>
                            <label class="block text-sm text-slate-300 mb-1.5">Reçu de paiement (facultatif)</label>
                            <input type="file" name="payment_receipt" accept=".jpg,.jpeg,.png,.pdf" class="custom-file-input text-sm text-slate-400">
                            <p class="text-xs text-slate-500 mt-1">JPG, PNG, PDF — max 5MB</p>
                        </div>
                    </div>

                    <!-- ID card -->
                    <div class="shop-card p-6">
                        <h2 class="text-lg font-bold text-white mb-5 flex items-center gap-2">
                            <i class="fas fa-id-card text-nc-cyan"></i> Pièce d'identité
                        </h2>
                        <div>
                            <label class="block text-sm text-slate-300 mb-1.5">Copie de la carte d'identité <span class="text-red-400">*</span></label>
                            <input type="file" name="id_card" accept=".jpg,.jpeg,.png,.pdf" required class="custom-file-input text-sm text-slate-400">
                            <p class="text-xs text-slate-500 mt-1">JPG, PNG, PDF — max 5MB</p>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="shop-card p-6">
                        <h2 class="text-lg font-bold text-white mb-5 flex items-center gap-2">
                            <i class="fas fa-sticky-note text-nc-cyan"></i> Notes supplémentaires
                        </h2>
                        <textarea name="notes" rows="3" placeholder="Instructions spéciales, points de repère..." class="shop-input resize-none"></textarea>
                    </div>

                    <!-- Submit -->
                    <div class="shop-card p-6">
                        <input type="hidden" name="submit_order" value="1">
                        <button type="submit" class="btn-primary w-full justify-center text-base py-3.5">
                            <i class="fas fa-check-circle"></i> Passer la commande
                        </button>
                        <p class="text-xs text-center text-slate-500 mt-3">
                            En cliquant, vous acceptez nos <a href="#" class="text-nc-cyan hover:underline">conditions générales</a> et notre <a href="#" class="text-nc-cyan hover:underline">politique de confidentialité</a>.
                        </p>
                    </div>
                </form>
            </div>

            <!-- Order Summary -->
            <div class="lg:w-1/3">
                <div class="shop-card p-6 sticky top-24" data-aos="fade-left">
                    <h3 class="text-xl font-bold text-white mb-5">Récapitulatif</h3>

                    <div class="space-y-3 mb-5 max-h-64 overflow-y-auto pr-1">
                        <?php foreach ($cart_items as $item): ?>
                        <div class="flex items-start gap-3">
                            <img src="../<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="w-14 h-14 object-cover rounded-lg flex-shrink-0">
                            <div class="flex-1 min-w-0">
                                <h4 class="text-sm font-medium text-white truncate"><?php echo htmlspecialchars($item['name']); ?></h4>
                                <div class="flex justify-between text-xs text-slate-400 mt-1">
                                    <span><?php echo $item['quantity']; ?> × <?php echo number_format($item['price'],2); ?> <?php echo $default_currency; ?></span>
                                    <span class="text-white font-medium"><?php echo number_format($item['subtotal'],2); ?> <?php echo $default_currency; ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="border-t border-white/10 pt-4 space-y-2 mb-5">
                        <div class="flex justify-between text-slate-300 text-sm">
                            <span>Sous-total</span>
                            <span class="text-white"><?php echo number_format($total_price,2).' '.$default_currency; ?></span>
                        </div>
                        <div class="flex justify-between text-slate-300 text-sm">
                            <span>Livraison</span>
                            <?php if ($shipping_cost > 0): ?>
                            <span class="text-white"><?php echo number_format($shipping_cost,2).' '.$default_currency; ?></span>
                            <?php else: ?><span class="text-nc-green">Gratuit</span><?php endif; ?>
                        </div>
                        <div class="flex justify-between pt-3 border-t border-white/10">
                            <span class="font-bold text-white">Total</span>
                            <span class="font-bold text-2xl price-tag"><?php echo number_format($total_price+$shipping_cost,2).' '.$default_currency; ?></span>
                        </div>
                    </div>

                    <div class="bg-white/5 rounded-xl p-4 flex items-center gap-3 mb-4">
                        <i class="fas fa-shield-alt text-nc-cyan text-xl"></i>
                        <div>
                            <p class="text-sm font-semibold text-white">Commande sécurisée</p>
                            <p class="text-xs text-slate-400">Vos données sont protégées</p>
                        </div>
                    </div>

                    <div class="text-center text-sm text-slate-400">
                        Besoin d'aide ? <span class="text-nc-cyan font-semibold"><i class="fas fa-phone-alt mr-1"></i>+227 88 37 18 17</span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Trust Badges -->
        <div class="mt-12 grid grid-cols-2 md:grid-cols-4 gap-4" data-aos="fade-up">
            <?php
            $trust = [
                ['fas fa-truck',     'Livraison rapide',  'Dans tout le Niger'],
                ['fas fa-shield-alt','Paiement sécurisé', 'Méthodes locales fiables'],
                ['fas fa-headset',   'Support client',    '7j/7 de 8h à 20h'],
                ['fas fa-undo-alt',  'Retours faciles',   '30 jours pour changer'],
            ];
            foreach ($trust as $t): ?>
            <div class="shop-card p-4 flex flex-col items-center text-center">
                <div class="w-12 h-12 rounded-xl bg-nc-cyan/10 flex items-center justify-center mb-3">
                    <i class="<?php echo $t[0]; ?> text-nc-cyan text-xl"></i>
                </div>
                <h4 class="font-semibold text-white text-sm mb-1"><?php echo $t[1]; ?></h4>
                <p class="text-xs text-slate-400"><?php echo $t[2]; ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Payment method selection
    document.querySelectorAll('.payment-method-card').forEach(card => {
        card.addEventListener('click', function() {
            const method = this.getAttribute('data-method');
            const radio  = document.getElementById('payment_' + method.toLowerCase());
            document.querySelectorAll('.payment-method-card').forEach(c => c.classList.remove('selected'));
            document.querySelectorAll('input[name="payment_method"]').forEach(m => m.checked = false);
            this.classList.add('selected');
            if (radio) radio.checked = true;
            document.getElementById('payment-info').classList.remove('hidden');
            document.querySelectorAll('.payment-info').forEach(d => d.classList.add('hidden'));
            const div = document.getElementById(method.toLowerCase() + '-info');
            if (div) div.classList.remove('hidden');
        });
    });

    // Form validation
    const form = document.querySelector('form[enctype]');
    if (form) {
        form.addEventListener('submit', function(e) {
            const pm = document.querySelector('input[name="payment_method"]:checked');
            if (!pm) { e.preventDefault(); alert('Veuillez sélectionner une méthode de paiement.'); return; }
            const idCard = document.getElementById('id_card');
            if (idCard && idCard.files.length === 0) { e.preventDefault(); alert("Veuillez télécharger votre carte d'identité."); return; }
            const btn = this.querySelector('button[type="submit"]');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Traitement...';
            btn.disabled = true;
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>
