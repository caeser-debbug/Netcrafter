<?php
// Titre de la page pour l'inclusion de l'en-tête
$page_title = "Détails de la commande";

// Inclusion de l'en-tête
require_once 'includes/header.php';

// Récupérer l'ID de la commande à partir de l'URL
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Vérifier si l'ID est valide
if ($order_id <= 0) {
    header("Location: orders.php?error=" . urlencode("ID de commande invalide."));
    exit;
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Mise à jour du statut de la commande
    if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
        $order_status = isset($_POST['order_status']) ? $_POST['order_status'] : '';
        $payment_status = isset($_POST['payment_status']) ? $_POST['payment_status'] : '';
        $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
        
        // Validation des statuts
        $valid_order_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'returned'];
        $valid_payment_statuses = ['pending', 'paid', 'failed', 'refunded'];
        
        if (!in_array($order_status, $valid_order_statuses) || !in_array($payment_status, $valid_payment_statuses)) {
            header("Location: order_details.php?id={$order_id}&error=" . urlencode("Statut invalide."));
            exit;
        }
        
        // Récupérer le statut actuel pour détecter les changements
        $current_status_query = "SELECT order_status, payment_status FROM orders WHERE id = ?";
        $stmt = $conn->prepare($current_status_query);
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $current_status_result = $stmt->get_result();
        $current_status = $current_status_result->fetch_assoc();
        
        // Mise à jour de la commande
        $update_query = "UPDATE orders SET order_status = ?, payment_status = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ssi", $order_status, $payment_status, $order_id);
        $result = $stmt->execute();
        
        if ($result) {
            // Ajouter un suivi de commande si le statut a changé
            if ($current_status['order_status'] !== $order_status) {
                $tracking_query = "INSERT INTO order_tracking (order_id, status, notes, updated_by, created_at) 
                                  VALUES (?, ?, ?, ?, NOW())";
                $updated_by = $_SESSION['admin_username'];
                
                $stmt = $conn->prepare($tracking_query);
                $stmt->bind_param("isss", $order_id, $order_status, $notes, $updated_by);
                $stmt->execute();
            }
            
            header("Location: order_details.php?id={$order_id}&success=" . urlencode("Le statut de la commande a été mis à jour avec succès."));
            exit;
        } else {
            header("Location: order_details.php?id={$order_id}&error=" . urlencode("Une erreur est survenue lors de la mise à jour du statut de la commande."));
            exit;
        }
    }
    
    // Ajout d'un suivi de commande
    if (isset($_POST['action']) && $_POST['action'] === 'add_tracking') {
        $tracking_number = isset($_POST['tracking_number']) ? trim($_POST['tracking_number']) : '';
        $carrier = isset($_POST['carrier']) ? trim($_POST['carrier']) : '';
        $location = isset($_POST['location']) ? trim($_POST['location']) : '';
        $tracking_notes = isset($_POST['tracking_notes']) ? trim($_POST['tracking_notes']) : '';
        $tracking_status = isset($_POST['tracking_status']) ? $_POST['tracking_status'] : '';
        
        // Validation basique
        if (empty($tracking_number) || empty($carrier)) {
            header("Location: order_details.php?id={$order_id}&error=" . urlencode("Le numéro de suivi et le transporteur sont obligatoires."));
            exit;
        }
        
        // Validation du statut
        $valid_tracking_statuses = ['processing', 'shipped', 'in_transit', 'delivered', 'returned', 'cancelled'];
        if (!in_array($tracking_status, $valid_tracking_statuses)) {
            header("Location: order_details.php?id={$order_id}&error=" . urlencode("Statut de suivi invalide."));
            exit;
        }
        
        // Ajout du suivi
        $add_tracking_query = "INSERT INTO order_tracking (order_id, status, tracking_number, carrier, location, notes, updated_by, created_at) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        $updated_by = $_SESSION['admin_username'];
        
        $stmt = $conn->prepare($add_tracking_query);
        $stmt->bind_param("issssss", $order_id, $tracking_status, $tracking_number, $carrier, $location, $tracking_notes, $updated_by);
        $result = $stmt->execute();
        
        if ($result) {
            // Mettre à jour le statut de la commande si nécessaire
            if ($tracking_status === 'shipped' || $tracking_status === 'delivered') {
                $update_order_query = "UPDATE orders SET order_status = ?, updated_at = NOW() WHERE id = ?";
                $stmt = $conn->prepare($update_order_query);
                $stmt->bind_param("si", $tracking_status, $order_id);
                $stmt->execute();
            }
            
            header("Location: order_details.php?id={$order_id}&success=" . urlencode("Les informations de suivi ont été ajoutées avec succès."));
            exit;
        } else {
            header("Location: order_details.php?id={$order_id}&error=" . urlencode("Une erreur est survenue lors de l'ajout des informations de suivi."));
            exit;
        }
    }
}

// Récupérer les détails de la commande
$order_query = "SELECT o.*, 
                u.username, u.email, u.full_name, u.phone,
                sa.street_address as shipping_street, sa.city as shipping_city, sa.state as shipping_state, sa.postal_code as shipping_postal_code, sa.country as shipping_country,
                ba.street_address as billing_street, ba.city as billing_city, ba.state as billing_state, ba.postal_code as billing_postal_code, ba.country as billing_country
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                LEFT JOIN addresses sa ON o.shipping_address_id = sa.id
                LEFT JOIN addresses ba ON o.billing_address_id = ba.id
                WHERE o.id = ?";

$stmt = $conn->prepare($order_query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order_result = $stmt->get_result();

if ($order_result->num_rows === 0) {
    header("Location: orders.php?error=" . urlencode("Commande introuvable."));
    exit;
}

$order = $order_result->fetch_assoc();

// Récupérer les articles de la commande
$items_query = "SELECT oi.*, p.name as product_name, p.sku, pi.image_url
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                LEFT JOIN (
                    SELECT product_id, image_url 
                    FROM product_images 
                    WHERE is_primary = 1 
                    UNION 
                    SELECT product_id, image_url 
                    FROM product_images 
                    GROUP BY product_id
                ) pi ON p.id = pi.product_id
                WHERE oi.order_id = ?";

$stmt = $conn->prepare($items_query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items_result = $stmt->get_result();
$order_items = [];

while ($item = $items_result->fetch_assoc()) {
    $order_items[] = $item;
}

// Récupérer l'historique de suivi de la commande
$tracking_query = "SELECT * FROM order_tracking WHERE order_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($tracking_query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$tracking_result = $stmt->get_result();
$tracking_history = [];

while ($tracking = $tracking_result->fetch_assoc()) {
    $tracking_history[] = $tracking;
}

// Calculer le résumé financier
$subtotal = 0;
foreach ($order_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

$discount = $order['discount_amount'] ?? 0;
$shipping = $order['shipping_cost'] ?? 0;
$tax = $order['tax_amount'] ?? 0;
$total = $order['total_amount'];
?>

<div class="space-y-6">
    <!-- Order Header with Actions -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        <div class="flex flex-col md:flex-row md:justify-between md:items-center">
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-xl font-bold text-gray-800 dark:text-white">Commande #<?php echo htmlspecialchars($order['order_number']); ?></h2>
                    <?php echo getOrderStatusBadge($order['order_status']); ?>
                    <?php echo getPaymentStatusBadge($order['payment_status']); ?>
                </div>
                <p class="text-gray-500 dark:text-gray-400 mt-1">
                    <i class="far fa-calendar-alt mr-1"></i> <?php echo formatDate($order['created_at'], 'd/m/Y à H:i'); ?>
                </p>
            </div>
            
            <div class="flex flex-wrap gap-2 mt-4 md:mt-0">
                <button type="button" id="update-status-btn" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded-md transition-colors">
                    <i class="fas fa-edit mr-2"></i>Modifier le statut
                </button>
                <button type="button" id="add-tracking-btn" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-md transition-colors">
                    <i class="fas fa-truck mr-2"></i>Ajouter un suivi
                </button>
                <a href="invoice.php?id=<?php echo $order_id; ?>" target="_blank" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md transition-colors">
                    <i class="fas fa-file-invoice mr-2"></i>Facture
                </a>
                <a href="orders.php" class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 px-4 py-2 rounded-md transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>Retour
                </a>
            </div>
        </div>
    </div>
    
    <!-- Order Details Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Customer Information -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">
                <i class="fas fa-user-circle mr-2 text-netblue-500"></i>Client
            </h3>
            
            <?php if (!empty($order['user_id'])): ?>
                <div class="space-y-3">
                    <p class="text-gray-800 dark:text-white font-medium">
                        <?php echo !empty($order['full_name']) ? htmlspecialchars($order['full_name']) : htmlspecialchars($order['username']); ?>
                    </p>
                    
                    <?php if (!empty($order['email'])): ?>
                    <p class="text-gray-600 dark:text-gray-300">
                        <i class="fas fa-envelope text-gray-400 mr-2"></i>
                        <a href="mailto:<?php echo htmlspecialchars($order['email']); ?>" class="hover:text-netblue-600 dark:hover:text-netblue-400">
                            <?php echo htmlspecialchars($order['email']); ?>
                        </a>
                    </p>
                    <?php endif; ?>
                    
                    <?php if (!empty($order['phone'])): ?>
                    <p class="text-gray-600 dark:text-gray-300">
                        <i class="fas fa-phone text-gray-400 mr-2"></i>
                        <a href="tel:<?php echo htmlspecialchars($order['phone']); ?>" class="hover:text-netblue-600 dark:hover:text-netblue-400">
                            <?php echo htmlspecialchars($order['phone']); ?>
                        </a>
                    </p>
                    <?php endif; ?>
                    
                    <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
                        <a href="customers.php?id=<?php echo $order['user_id']; ?>" class="text-netblue-600 dark:text-netblue-400 hover:text-netblue-800 dark:hover:text-netblue-300 text-sm">
                            <i class="fas fa-external-link-alt mr-1"></i>Voir le profil client
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-gray-600 dark:text-gray-400">
                    <p>Commande passée en tant qu'invité</p>
                    <?php if (!empty($order['mac_address'])): ?>
                    <p class="text-xs mt-1">
                        <i class="fas fa-fingerprint mr-1"></i>
                        ID appareil: <?php echo htmlspecialchars($order['mac_address']); ?>
                    </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Shipping Address -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">
                <i class="fas fa-shipping-fast mr-2 text-netblue-500"></i>Adresse de livraison
            </h3>
            
            <?php if (!empty($order['shipping_street'])): ?>
            <address class="not-italic text-gray-600 dark:text-gray-300 space-y-1">
                <p class="text-gray-800 dark:text-white font-medium">
                    <?php echo !empty($order['full_name']) ? htmlspecialchars($order['full_name']) : ''; ?>
                </p>
                <p><?php echo htmlspecialchars($order['shipping_street']); ?></p>
                <p>
                    <?php echo htmlspecialchars($order['shipping_postal_code']); ?> 
                    <?php echo htmlspecialchars($order['shipping_city']); ?>
                    <?php if (!empty($order['shipping_state'])): ?>
                        , <?php echo htmlspecialchars($order['shipping_state']); ?>
                    <?php endif; ?>
                </p>
                <p><?php echo htmlspecialchars($order['shipping_country']); ?></p>
            </address>
            <?php else: ?>
            <div class="text-gray-600 dark:text-gray-400">
                <p>Aucune adresse de livraison enregistrée</p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Billing Address -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">
                <i class="fas fa-file-invoice mr-2 text-netblue-500"></i>Adresse de facturation
            </h3>
            
            <?php if (!empty($order['billing_street'])): ?>
            <address class="not-italic text-gray-600 dark:text-gray-300 space-y-1">
                <p class="text-gray-800 dark:text-white font-medium">
                    <?php echo !empty($order['full_name']) ? htmlspecialchars($order['full_name']) : ''; ?>
                </p>
                <p><?php echo htmlspecialchars($order['billing_street']); ?></p>
                <p>
                    <?php echo htmlspecialchars($order['billing_postal_code']); ?> 
                    <?php echo htmlspecialchars($order['billing_city']); ?>
                    <?php if (!empty($order['billing_state'])): ?>
                        , <?php echo htmlspecialchars($order['billing_state']); ?>
                    <?php endif; ?>
                </p>
                <p><?php echo htmlspecialchars($order['billing_country']); ?></p>
            </address>
            <?php else: ?>
            <div class="text-gray-600 dark:text-gray-400">
                <p>Identique à l'adresse de livraison</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Order Items and Summary -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Order Items -->
        <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">
                <i class="fas fa-box mr-2 text-netblue-500"></i>Articles commandés
            </h3>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Produit
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Prix unitaire
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Quantité
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Total
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($order_items as $item): ?>
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="h-10 w-10 flex-shrink-0">
                                        <?php if (!empty($item['image_url'])): ?>
                                        <img class="h-10 w-10 rounded-md object-cover" src="../<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                        <?php else: ?>
                                        <div class="h-10 w-10 rounded-md bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                            <i class="fas fa-box text-gray-400"></i>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            <a href="edit_product.php?id=<?php echo $item['product_id']; ?>" class="hover:text-netblue-600 dark:hover:text-netblue-400">
                                                <?php echo htmlspecialchars($item['product_name']); ?>
                                            </a>
                                        </div>
                                        <?php if (!empty($item['sku'])): ?>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            SKU: <?php echo htmlspecialchars($item['sku']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="text-sm text-gray-900 dark:text-white">
                                    <?php echo number_format($item['price'], 2); ?> FCFA
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="text-sm text-gray-900 dark:text-white">
                                    <?php echo $item['quantity']; ?>
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                    <?php echo number_format($item['price'] * $item['quantity'], 2); ?> FCFA
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Order Summary -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">
                <i class="fas fa-receipt mr-2 text-netblue-500"></i>Résumé de la commande
            </h3>
            
            <div class="space-y-3 mb-6">
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">Sous-total</span>
                    <span class="text-gray-800 dark:text-white font-medium"><?php echo number_format($subtotal, 2); ?> FCFA</span>
                </div>
                
                <?php if ($discount > 0): ?>
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">Remise</span>
                    <span class="text-green-600 dark:text-green-400">-<?php echo number_format($discount, 2); ?> FCFA</span>
                </div>
                <?php endif; ?>
                
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">Frais de livraison</span>
                    <span class="text-gray-800 dark:text-white"><?php echo number_format($shipping, 2); ?> FCFA</span>
                </div>
                
                <?php if ($tax > 0): ?>
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">TVA</span>
                    <span class="text-gray-800 dark:text-white"><?php echo number_format($tax, 2); ?> FCFA</span>
                </div>
                <?php endif; ?>
                
                <div class="pt-3 border-t border-gray-200 dark:border-gray-700 flex justify-between font-bold">
                    <span class="text-gray-800 dark:text-white">Total</span>
                    <span class="text-netblue-600 dark:text-netblue-400 text-xl"><?php echo number_format($total, 2); ?> FCFA</span>
                </div>
            </div>
            
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-gray-600 dark:text-gray-400">Méthode de paiement</span>
                    <span class="text-gray-800 dark:text-white">
                        <?php if (!empty($order['payment_method'])): ?>
                            <?php 
                            $payment_icon = '';
                            switch(strtolower($order['payment_method'])) {
                                case 'credit_card':
                                case 'card':
                                    $payment_icon = '<i class="far fa-credit-card mr-1"></i>';
                                    $payment_text = 'Carte bancaire';
                                    break;
                                case 'paypal':
                                    $payment_icon = '<i class="fab fa-paypal mr-1"></i>';
                                    $payment_text = 'PayPal';
                                    break;
                                case 'bank_transfer':
                                    $payment_icon = '<i class="fas fa-university mr-1"></i>';
                                    $payment_text = 'Virement bancaire';
                                    break;
                                default:
                                    $payment_icon = '<i class="fas fa-money-bill-wave mr-1"></i>';
                                    $payment_text = htmlspecialchars($order['payment_method']);
                            }
                            echo $payment_icon . $payment_text;
                            ?>
                        <?php else: ?>
                            Non spécifié
                        <?php endif; ?>
                    </span>
                </div>
                
                <?php if (!empty($order['notes'])): ?>
                <div class="mt-4 p-3 bg-gray-50 dark:bg-gray-700 rounded-md">
                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Notes de commande</h4>
                    <p class="text-gray-600 dark:text-gray-400 text-sm">
                        <?php echo nl2br(htmlspecialchars($order['notes'])); ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Order Tracking History -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-6">
            <i class="fas fa-history mr-2 text-netblue-500"></i>Historique de suivi
        </h3>
        
        <?php if (!empty($tracking_history)): ?>
        <div class="relative">
            <!-- Timeline Line -->
            <div class="absolute top-0 left-8 bottom-0 w-0.5 bg-gray-200 dark:bg-gray-700"></div>
            
            <!-- Timeline Items -->
            <div class="space-y-6">
                <?php foreach ($tracking_history as $index => $tracking): ?>
                <div class="relative pl-12">
                    <!-- Timeline Dot -->
                    <div class="absolute left-0 top-0 flex items-center justify-center w-16 h-16">
                        <div class="w-8 h-8 rounded-full ring-4 ring-white dark:ring-gray-800 <?php echo getTrackingStatusColor($tracking['status']); ?>">
                            <i class="<?php echo getTrackingStatusIcon($tracking['status']); ?> text-white text-center flex items-center justify-center h-full"></i>
                        </div>
                    </div>
                    
                    <!-- Timeline Content -->
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 ml-4">
                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-2">
                            <h4 class="text-base font-medium text-gray-800 dark:text-white mb-1 sm:mb-0">
                                <?php echo getTrackingStatusText($tracking['status']); ?>
                            </h4>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                <i class="far fa-clock mr-1"></i> <?php echo formatDate($tracking['created_at'], 'd/m/Y à H:i'); ?>
                            </div>
                        </div>
                        
                        <div class="space-y-2">
                            <?php if (!empty($tracking['tracking_number']) || !empty($tracking['carrier'])): ?>
                            <div class="flex flex-wrap gap-4">
                                <?php if (!empty($tracking['tracking_number'])): ?>
                                <div class="flex items-center">
                                    <span class="text-gray-500 dark:text-gray-400 text-sm">Numéro de suivi:</span>
                                    <span class="ml-2 text-gray-800 dark:text-white font-medium"><?php echo htmlspecialchars($tracking['tracking_number']); ?></span>
                                </div>
                                <?php endif; ?>
                             <?php if (!empty($tracking['carrier'])): ?>
                                <div class="flex items-center">
                                    <span class="text-gray-500 dark:text-gray-400 text-sm">Transporteur:</span>
                                    <span class="ml-2 text-gray-800 dark:text-white font-medium"><?php echo htmlspecialchars($tracking['carrier']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($tracking['location'])): ?>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400 text-sm">Localisation:</span>
                                <span class="ml-2 text-gray-800 dark:text-white"><?php echo htmlspecialchars($tracking['location']); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($tracking['notes'])): ?>
                            <div class="mt-2 text-gray-600 dark:text-gray-400 text-sm">
                                <?php echo nl2br(htmlspecialchars($tracking['notes'])); ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                Mis à jour par <?php echo htmlspecialchars($tracking['updated_by']); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="text-center py-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-400 dark:text-gray-500 mb-4">
                <i class="fas fa-shipping-fast text-2xl"></i>
            </div>
            <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Aucun suivi de commande</h4>
            <p class="text-gray-500 dark:text-gray-400 max-w-md mx-auto mb-6">
                Cette commande n'a pas encore d'historique de suivi. Ajoutez un suivi pour tenir le client informé de l'état de sa commande.
            </p>
            <button type="button" id="add-tracking-btn-empty" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-md transition-colors">
                <i class="fas fa-truck mr-2"></i>Ajouter un suivi
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Update Status Modal -->
<div id="update-status-modal" class="fixed inset-0 z-50 overflow-auto bg-black bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6 transform transition-all">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Modifier le statut de la commande</h3>
            <button type="button" class="close-modal text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form action="order_details.php?id=<?php echo $order_id; ?>" method="POST">
            <input type="hidden" name="action" value="update_status">
            
            <div class="space-y-4">
                <div>
                    <label for="order_status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Statut de la commande</label>
                    <select id="order_status" name="order_status" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:ring-netblue-500 focus:border-netblue-500 dark:bg-gray-700 dark:text-white">
                        <option value="pending" <?php echo $order['order_status'] === 'pending' ? 'selected' : ''; ?>>En attente</option>
                        <option value="processing" <?php echo $order['order_status'] === 'processing' ? 'selected' : ''; ?>>En traitement</option>
                        <option value="shipped" <?php echo $order['order_status'] === 'shipped' ? 'selected' : ''; ?>>Expédiée</option>
                        <option value="delivered" <?php echo $order['order_status'] === 'delivered' ? 'selected' : ''; ?>>Livrée</option>
                        <option value="cancelled" <?php echo $order['order_status'] === 'cancelled' ? 'selected' : ''; ?>>Annulée</option>
                        <option value="returned" <?php echo $order['order_status'] === 'returned' ? 'selected' : ''; ?>>Retournée</option>
                    </select>
                </div>
                
                <div>
                    <label for="payment_status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Statut du paiement</label>
                    <select id="payment_status" name="payment_status" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:ring-netblue-500 focus:border-netblue-500 dark:bg-gray-700 dark:text-white">
                        <option value="pending" <?php echo $order['payment_status'] === 'pending' ? 'selected' : ''; ?>>En attente</option>
                        <option value="paid" <?php echo $order['payment_status'] === 'paid' ? 'selected' : ''; ?>>Payé</option>
                        <option value="failed" <?php echo $order['payment_status'] === 'failed' ? 'selected' : ''; ?>>Échoué</option>
                        <option value="refunded" <?php echo $order['payment_status'] === 'refunded' ? 'selected' : ''; ?>>Remboursé</option>
                    </select>
                </div>
                
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Notes (facultatif)</label>
                    <textarea id="notes" name="notes" rows="3" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:ring-netblue-500 focus:border-netblue-500 dark:bg-gray-700 dark:text-white"></textarea>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Ces notes seront affichées dans l'historique de suivi.</p>
                </div>
            </div>
            
            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" class="close-modal px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                    Annuler
                </button>
                <button type="submit" class="px-4 py-2 bg-netblue-600 text-white rounded-md hover:bg-netblue-700 transition-colors">
                    Mettre à jour
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Add Tracking Modal -->
<div id="add-tracking-modal" class="fixed inset-0 z-50 overflow-auto bg-black bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6 transform transition-all">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Ajouter un suivi de commande</h3>
            <button type="button" class="close-modal text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form action="order_details.php?id=<?php echo $order_id; ?>" method="POST">
            <input type="hidden" name="action" value="add_tracking">
            
            <div class="space-y-4">
                <div>
                    <label for="tracking_status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Statut</label>
                    <select id="tracking_status" name="tracking_status" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:ring-netblue-500 focus:border-netblue-500 dark:bg-gray-700 dark:text-white">
                        <option value="processing">En traitement</option>
                        <option value="shipped">Expédiée</option>
                        <option value="in_transit">En transit</option>
                        <option value="delivered">Livrée</option>
                        <option value="returned">Retournée</option>
                        <option value="cancelled">Annulée</option>
                    </select>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="tracking_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Numéro de suivi</label>
                        <input type="text" id="tracking_number" name="tracking_number" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:ring-netblue-500 focus:border-netblue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label for="carrier" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Transporteur</label>
                        <input type="text" id="carrier" name="carrier" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:ring-netblue-500 focus:border-netblue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                </div>
                
                <div>
                    <label for="location" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Localisation actuelle (facultatif)</label>
                    <input type="text" id="location" name="location" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:ring-netblue-500 focus:border-netblue-500 dark:bg-gray-700 dark:text-white">
                </div>
                
                <div>
                    <label for="tracking_notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Notes (facultatif)</label>
                    <textarea id="tracking_notes" name="tracking_notes" rows="3" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:ring-netblue-500 focus:border-netblue-500 dark:bg-gray-700 dark:text-white"></textarea>
                </div>
            </div>
            
            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" class="close-modal px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                    Annuler
                </button>
                <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 transition-colors">
                    Ajouter
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Fonctions pour formater l'affichage du statut de suivi
function getTrackingStatusText($status) {
    switch ($status) {
        case 'processing':
            return 'Commande en traitement';
        case 'shipped':
            return 'Commande expédiée';
        case 'in_transit':
            return 'En transit';
        case 'delivered':
            return 'Commande livrée';
        case 'returned':
            return 'Commande retournée';
        case 'cancelled':
            return 'Commande annulée';
        default:
            return 'Mise à jour de statut';
    }
}

function getTrackingStatusColor($status) {
    switch ($status) {
        case 'processing':
            return 'bg-blue-500';
        case 'shipped':
            return 'bg-purple-500';
        case 'in_transit':
            return 'bg-amber-500';
        case 'delivered':
            return 'bg-green-500';
        case 'returned':
            return 'bg-gray-500';
        case 'cancelled':
            return 'bg-red-500';
        default:
            return 'bg-gray-500';
    }
}

function getTrackingStatusIcon($status) {
    switch ($status) {
        case 'processing':
            return 'fas fa-cogs';
        case 'shipped':
            return 'fas fa-shipping-fast';
        case 'in_transit':
            return 'fas fa-truck';
        case 'delivered':
            return 'fas fa-check';
        case 'returned':
            return 'fas fa-undo';
        case 'cancelled':
            return 'fas fa-times';
        default:
            return 'fas fa-info-circle';
    }
}
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Modal handling
    const updateStatusModal = document.getElementById('update-status-modal');
    const updateStatusBtn = document.getElementById('update-status-btn');
    const addTrackingModal = document.getElementById('add-tracking-modal');
    const addTrackingBtn = document.getElementById('add-tracking-btn');
    const addTrackingBtnEmpty = document.getElementById('add-tracking-btn-empty');
    const closeModalButtons = document.querySelectorAll('.close-modal');
    
    // Show Update Status Modal
    if (updateStatusBtn) {
        updateStatusBtn.addEventListener('click', function() {
            updateStatusModal.classList.remove('hidden');
        });
    }
    
    // Show Add Tracking Modal
    if (addTrackingBtn) {
        addTrackingBtn.addEventListener('click', function() {
            addTrackingModal.classList.remove('hidden');
        });
    }
    
    // Alternative button in empty state
    if (addTrackingBtnEmpty) {
        addTrackingBtnEmpty.addEventListener('click', function() {
            addTrackingModal.classList.remove('hidden');
        });
    }
    
    // Close Modals
    closeModalButtons.forEach(button => {
        button.addEventListener('click', function() {
            updateStatusModal.classList.add('hidden');
            addTrackingModal.classList.add('hidden');
        });
    });
    
    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === updateStatusModal) {
            updateStatusModal.classList.add('hidden');
        }
        if (event.target === addTrackingModal) {
            addTrackingModal.classList.add('hidden');
        }
    });
    
    // Escape key to close modals
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            updateStatusModal.classList.add('hidden');
            addTrackingModal.classList.add('hidden');
        }
    });
});
</script>

<?php
// Inclusion du pied de page
require_once 'includes/footer.php';
?>