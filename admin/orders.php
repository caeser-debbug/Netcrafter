<?php
// Titre de la page pour l'inclusion de l'en-tête
$page_title = "Gestion des commandes";

// Inclusion de l'en-tête
require_once 'includes/header.php';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Mise à jour du statut d'une commande
    if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $order_status = isset($_POST['order_status']) ? $_POST['order_status'] : '';
        $payment_status = isset($_POST['payment_status']) ? $_POST['payment_status'] : '';
        
        if ($order_id === 0) {
            header("Location: orders.php?error=" . urlencode("ID de commande invalide."));
            exit;
        }
        
        // Validation des statuts
        $valid_order_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'returned'];
        $valid_payment_statuses = ['pending', 'paid', 'failed', 'refunded'];
        
        if (!in_array($order_status, $valid_order_statuses) || !in_array($payment_status, $valid_payment_statuses)) {
            header("Location: orders.php?error=" . urlencode("Statut invalide."));
            exit;
        }
        
        // Mise à jour de la commande
        $update_query = "UPDATE orders SET order_status = ?, payment_status = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ssi", $order_status, $payment_status, $order_id);
        $result = $stmt->execute();
        
        if ($result) {
            // Ajouter un suivi de commande
            $tracking_query = "INSERT INTO order_tracking (order_id, status, notes, updated_by, created_at) 
                              VALUES (?, ?, ?, ?, NOW())";
            $notes = "Statut mis à jour par l'administrateur.";
            $updated_by = $_SESSION['admin_username'];
            
            $stmt = $conn->prepare($tracking_query);
            $stmt->bind_param("isss", $order_id, $order_status, $notes, $updated_by);
            $stmt->execute();
            
            header("Location: orders.php?success=" . urlencode("Le statut de la commande a été mis à jour avec succès."));
            exit;
        } else {
            header("Location: orders.php?error=" . urlencode("Une erreur est survenue lors de la mise à jour du statut de la commande."));
            exit;
        }
    }
}

// Paramètres de filtrage et de tri
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'created_at';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'desc';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$orders_per_page = 10;

// Construction de la requête SQL de base
$sql_count = "SELECT COUNT(*) as total FROM orders o LEFT JOIN users u ON o.user_id = u.id";
$sql = "SELECT o.*, u.username, u.email 
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id";

// Construction des conditions WHERE
$where_conditions = [];
$params = [];
$types = "";

// Filtre par statut
if (!empty($status_filter) && in_array($status_filter, ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'returned'])) {
    $where_conditions[] = "o.order_status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Recherche par terme (numéro de commande, email ou username)
if (!empty($search_term)) {
    $where_conditions[] = "(o.order_number LIKE ? OR u.email LIKE ? OR u.username LIKE ?)";
    $search_param = "%" . $search_term . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

// Filtre par date
if (!empty($date_from)) {
    $date_from_formatted = date('Y-m-d 00:00:00', strtotime($date_from));
    $where_conditions[] = "o.created_at >= ?";
    $params[] = $date_from_formatted;
    $types .= "s";
}

if (!empty($date_to)) {
    $date_to_formatted = date('Y-m-d 23:59:59', strtotime($date_to));
    $where_conditions[] = "o.created_at <= ?";
    $params[] = $date_to_formatted;
    $types .= "s";
}

// Ajout des conditions WHERE à la requête
if (!empty($where_conditions)) {
    $sql_count .= " WHERE " . implode(" AND ", $where_conditions);
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

// Ajout du tri
$valid_sort_columns = ['id', 'order_number', 'total_amount', 'created_at', 'order_status', 'payment_status'];
$valid_sort_orders = ['asc', 'desc'];

if (!in_array($sort_by, $valid_sort_columns)) {
    $sort_by = 'created_at';
}
if (!in_array($sort_order, $valid_sort_orders)) {
    $sort_order = 'desc';
}

$sql .= " ORDER BY o.$sort_by $sort_order";

// Récupération du nombre total de commandes pour la pagination
$stmt_count = $conn->prepare($sql_count);
if (!empty($types)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$count_result = $stmt_count->get_result();
$row_count = $count_result->fetch_assoc();
$total_orders = $row_count['total'];
$total_pages = ceil($total_orders / $orders_per_page);

// Ajustement de la page courante
if ($page < 1) $page = 1;
if ($page > $total_pages && $total_pages > 0) $page = $total_pages;

// Ajout de la pagination à la requête
$offset = ($page - 1) * $orders_per_page;
$sql .= " LIMIT ?, ?";
$params[] = $offset;
$params[] = $orders_per_page;
$types .= "ii";

// Exécution de la requête paginée
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$orders = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}

// Statistiques des commandes (pour les compteurs en haut)
$orders_stats_query = "SELECT 
                      SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                      SUM(CASE WHEN order_status = 'processing' THEN 1 ELSE 0 END) as processing_count,
                      SUM(CASE WHEN order_status = 'shipped' THEN 1 ELSE 0 END) as shipped_count,
                      SUM(CASE WHEN order_status = 'delivered' THEN 1 ELSE 0 END) as delivered_count,
                      SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END) as total_revenue
                      FROM orders";
$stats_result = $conn->query($orders_stats_query);
$stats = $stats_result->fetch_assoc();
?>

<!-- Orders Management Content -->
<div class="space-y-6">
    <!-- Order Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
        <!-- Pending Orders -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 border-l-4 border-yellow-500">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-yellow-100 dark:bg-yellow-900 text-yellow-600 dark:text-yellow-400 mr-4">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">En attente</p>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white"><?php echo $stats['pending_count'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
        
        <!-- Processing Orders -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 border-l-4 border-blue-500">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-400 mr-4">
                    <i class="fas fa-cogs"></i>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">En traitement</p>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white"><?php echo $stats['processing_count'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
        
        <!-- Shipped Orders -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 border-l-4 border-purple-500">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100 dark:bg-purple-900 text-purple-600 dark:text-purple-400 mr-4">
                    <i class="fas fa-shipping-fast"></i>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">Expédiées</p>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white"><?php echo $stats['shipped_count'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
        
        <!-- Delivered Orders -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 border-l-4 border-green-500">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 dark:bg-green-900 text-green-600 dark:text-green-400 mr-4">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">Livrées</p>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white"><?php echo $stats['delivered_count'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
        
        <!-- Total Revenue -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 border-l-4 border-emerald-500">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-emerald-100 dark:bg-emerald-900 text-emerald-600 dark:text-emerald-400 mr-4">
                    <i class="fas fa-euro-sign"></i>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">Revenus totaux</p>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white"><?php echo number_format($stats['total_revenue'] ?? 0, 2); ?> FCFA</h3>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Orders List Card -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        <!-- Filters and Search -->
        <div class="mb-6 bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
            <form action="orders.php" method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <!-- Search Field -->
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Recherche</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="N° commande, email..." class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:ring-netblue-500 focus:border-netblue-500 dark:bg-gray-800 dark:text-white">
                </div>
                
                <!-- Status Filter -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Statut</label>
                    <select id="status" name="status" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:ring-netblue-500 focus:border-netblue-500 dark:bg-gray-800 dark:text-white">
                        <option value="">Tous les statuts</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>En attente</option>
                        <option value="processing" <?php echo $status_filter === 'processing' ? 'selected' : ''; ?>>En traitement</option>
                        <option value="shipped" <?php echo $status_filter === 'shipped' ? 'selected' : ''; ?>>Expédiée</option>
                        <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>Livrée</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Annulée</option>
                        <option value="returned" <?php echo $status_filter === 'returned' ? 'selected' : ''; ?>>Retournée</option>
                    </select>
                </div>
                
                <!-- Date Range -->
                <div>
                    <label for="date_from" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date de début</label>
                    <input type="text" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" placeholder="JJ/MM/AAAA" class="datepicker block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:ring-netblue-500 focus:border-netblue-500 dark:bg-gray-800 dark:text-white">
                </div>
                
                <div>
                    <label for="date_to" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date de fin</label>
                    <input type="text" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" placeholder="JJ/MM/AAAA" class="datepicker block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:ring-netblue-500 focus:border-netblue-500 dark:bg-gray-800 dark:text-white">
                </div>
                
                <!-- Sort By -->
                <div>
                    <label for="sort_by" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Trier par</label>
                    <div class="flex space-x-2">
                        <select id="sort_by" name="sort_by" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:ring-netblue-500 focus:border-netblue-500 dark:bg-gray-800 dark:text-white">
                            <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Date</option>
                            <option value="order_number" <?php echo $sort_by === 'order_number' ? 'selected' : ''; ?>>N° commande</option>
                            <option value="total_amount" <?php echo $sort_by === 'total_amount' ? 'selected' : ''; ?>>Montant</option>
                            <option value="order_status" <?php echo $sort_by === 'order_status' ? 'selected' : ''; ?>>Statut commande</option>
                            <option value="payment_status" <?php echo $sort_by === 'payment_status' ? 'selected' : ''; ?>>Statut paiement</option>
                        </select>
                        
                        <select id="sort_order" name="sort_order" class="w-20 rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:ring-netblue-500 focus:border-netblue-500 dark:bg-gray-800 dark:text-white">
                            <option value="asc" <?php echo $sort_order === 'asc' ? 'selected' : ''; ?>>↑</option>
                            <option value="desc" <?php echo $sort_order === 'desc' ? 'selected' : ''; ?>>↓</option>
                        </select>
                    </div>
                </div>
                
                <!-- Form Buttons -->
                <div class="lg:col-span-5 flex flex-col sm:flex-row gap-2 sm:gap-4 sm:justify-end">
                    <button type="submit" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded-md transition-colors">
                        <i class="fas fa-filter mr-2"></i>Appliquer les filtres
                    </button>
                    <a href="orders.php" class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 text-center px-4 py-2 rounded-md transition-colors">
                        <i class="fas fa-undo mr-2"></i>Réinitialiser
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Orders Table -->
        <?php if (!empty($orders)): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            N° Commande
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Client
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Date
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Montant
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Statut Commande
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Statut Paiement
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                <a href="order_details.php?id=<?php echo $order['id']; ?>" class="hover:text-netblue-600 dark:hover:text-netblue-400">
                                    #<?php echo htmlspecialchars($order['order_number']); ?>
                                </a>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php if (!empty($order['username']) || !empty($order['email'])): ?>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                    <?php echo htmlspecialchars($order['username'] ?? 'Client'); ?>
                                </div>
                                <?php if (!empty($order['email'])): ?>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    <?php echo htmlspecialchars($order['email']); ?>
                                </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    Client anonyme
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900 dark:text-white">
                                <?php echo formatDate($order['created_at']); ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                <?php echo number_format($order['total_amount'], 2); ?> FCFA
                            </div>
                            <?php if (!empty($order['discount_amount']) && $order['discount_amount'] > 0): ?>
                            <div class="text-xs text-green-600 dark:text-green-400">
                                -<?php echo number_format($order['discount_amount'], 2); ?> FCFA
                            </div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php echo getOrderStatusBadge($order['order_status']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php echo getPaymentStatusBadge($order['payment_status']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-2">
                                <a href="order_details.php?id=<?php echo $order['id']; ?>" class="text-netblue-600 hover:text-netblue-900 dark:text-netblue-400 dark:hover:text-netblue-200" title="Voir les détails">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button type="button" class="edit-status-btn text-amber-600 hover:text-amber-900 dark:text-amber-400 dark:hover:text-amber-200" title="Modifier le statut" data-id="<?php echo $order['id']; ?>" data-order-status="<?php echo $order['order_status']; ?>" data-payment-status="<?php echo $order['payment_status']; ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="mt-6 flex justify-between items-center">
            <div>
                <span class="text-sm text-gray-700 dark:text-gray-300">
                    Affichage de <span class="font-medium"><?php echo $offset + 1; ?></span> à <span class="font-medium"><?php echo min($offset + $orders_per_page, $total_orders); ?></span> sur <span class="font-medium"><?php echo $total_orders; ?></span> commandes
                </span>
            </div>
            
            <nav class="inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search_term); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&sort_by=<?php echo $sort_by; ?>&sort_order=<?php echo $sort_order; ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <span class="sr-only">Précédent</span>
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php else: ?>
                <span class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 text-sm font-medium text-gray-400 dark:text-gray-500 cursor-not-allowed">
                    <span class="sr-only">Précédent</span>
                    <i class="fas fa-chevron-left"></i>
                </span>
                <?php endif; ?>
                
                <?php
                // Afficher un nombre limité de pages autour de la page actuelle
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1) {
                    echo '<a href="?page=1&status=' . urlencode($status_filter) . '&search=' . urlencode($search_term) . '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) . '&sort_by=' . $sort_by . '&sort_order=' . $sort_order . '" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">1</a>';
                    if ($start_page > 2) {
                        echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300">...</span>';
                    }
                }
                
                for ($i = $start_page; $i <= $end_page; $i++) {
                    if ($i == $page) {
                        echo '<span aria-current="page" class="relative inline-flex items-center px-4 py-2 border border-netblue-500 bg-netblue-50 dark:bg-netblue-900 text-sm font-medium text-netblue-600 dark:text-netblue-300">' . $i . '</span>';
                    } else {
                        echo '<a href="?page=' . $i . '&status=' . urlencode($status_filter) . '&search=' . urlencode($search_term) . '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) . '&sort_by=' . $sort_by . '&sort_order=' . $sort_order . '" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">' . $i . '</a>';
                    }
                }
                
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                        echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300">...</span>';
                    }
                    echo '<a href="?page=' . $total_pages . '&status=' . urlencode($status_filter) . '&search=' . urlencode($search_term) . '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) . '&sort_by=' . $sort_by . '&sort_order=' . $sort_order . '" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">' . $total_pages . '</a>';
                }
                ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search_term); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&sort_by=<?php echo $sort_by; ?>&sort_order=<?php echo $sort_order; ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <span class="sr-only">Suivant</span>
                    <i class="fas fa-chevron-right"></i>
                </a>
                <?php else: ?>
                <span class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 text-sm font-medium text-gray-400 dark:text-gray-500 cursor-not-allowed">
                    <span class="sr-only">Suivant</span>
                    <i class="fas fa-chevron-right"></i>
                </span>
                <?php endif; ?>
            </nav>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <!-- Empty State -->
        <div class="text-center py-12">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-400 dark:text-gray-500 mb-4">
                <i class="fas fa-shopping-cart text-2xl"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Aucune commande trouvée</h3>
            <p class="text-gray-500 dark:text-gray-400 max-w-md mx-auto mb-6">
                <?php if (!empty($search_term) || !empty($status_filter) || !empty($date_from) || !empty($date_to)): ?>
                    Aucune commande ne correspond à vos critères de recherche. Essayez de modifier vos filtres.
                <?php else: ?>
                    Aucune commande n'a encore été passée.
                <?php endif; ?>
            </p>
            <?php if (!empty($search_term) || !empty($status_filter) || !empty($date_from) || !empty($date_to)): ?>
            <a href="orders.php" class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 px-4 py-2 rounded-md transition-colors inline-flex items-center">
                <i class="fas fa-undo mr-2"></i>Réinitialiser les filtres
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Order Status Modal -->
<div id="status-modal" class="fixed inset-0 z-50 overflow-auto bg-black bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6 transform transition-all">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Modifier le statut</h3>
            <button id="close-status-modal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form action="orders.php" method="POST" id="status-form">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="order_id" id="status-order-id" value="">
            
            <div class="space-y-4">
                <div>
                    <label for="order_status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Statut de la commande</label>
                    <select id="order_status" name="order_status" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:ring-netblue-500 focus:border-netblue-500 dark:bg-gray-700 dark:text-white">
                        <option value="pending">En attente</option>
                        <option value="processing">En traitement</option>
                        <option value="shipped">Expédiée</option>
                        <option value="delivered">Livrée</option>
                        <option value="cancelled">Annulée</option>
                        <option value="returned">Retournée</option>
                    </select>
                </div>
                
                <div>
                    <label for="payment_status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Statut du paiement</label>
                    <select id="payment_status" name="payment_status" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:ring-netblue-500 focus:border-netblue-500 dark:bg-gray-700 dark:text-white">
                        <option value="pending">En attente</option>
                        <option value="paid">Payé</option>
                        <option value="failed">Échoué</option>
                        <option value="refunded">Remboursé</option>
                    </select>
                </div>
            </div>
            
            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" class="cancel-status px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                    Annuler
                </button>
                <button type="submit" class="px-4 py-2 bg-netblue-600 text-white rounded-md hover:bg-netblue-700 transition-colors">
                    Mettre à jour
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize datepickers
    if (flatpickr) {
        flatpickr(".datepicker", {
            dateFormat: "d/m/Y",
            locale: "fr"
        });
    }
    
    // Order status modal
    const editStatusButtons = document.querySelectorAll('.edit-status-btn');
    const statusModal = document.getElementById('status-modal');
    const closeStatusModalButton = document.getElementById('close-status-modal');
    const cancelStatusButtons = document.querySelectorAll('.cancel-status');
    const orderStatusSelect = document.getElementById('order_status');
    const paymentStatusSelect = document.getElementById('payment_status');
    const orderIdInput = document.getElementById('status-order-id');
    
    editStatusButtons.forEach(button => {
        button.addEventListener('click', function() {
            const orderId = this.getAttribute('data-id');
            const orderStatus = this.getAttribute('data-order-status');
            const paymentStatus = this.getAttribute('data-payment-status');
            
            orderIdInput.value = orderId;
            orderStatusSelect.value = orderStatus;
            paymentStatusSelect.value = paymentStatus;
            
            statusModal.classList.remove('hidden');
        });
    });
    
    const closeStatusModal = function() {
        statusModal.classList.add('hidden');
    };
    
    closeStatusModalButton.addEventListener('click', closeStatusModal);
    cancelStatusButtons.forEach(button => {
        button.addEventListener('click', closeStatusModal);
    });
});
</script>

<?php
// Inclusion du pied de page
require_once 'includes/footer.php';
?>