<?php
// Titre de la page pour l'inclusion de l'en-tête
$page_title = "Gestion des clients";

// Inclusion de l'en-tête
require_once 'includes/header.php';

// Traitement de la suppression d'un client
if (isset($_POST['action']) && $_POST['action'] === 'delete_customer') {
    $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
    
    if ($customer_id > 0) {
        // Vérifier si le client existe
        $check_query = "SELECT id FROM users WHERE id = ? AND is_admin = 0";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $check_result = $stmt->get_result();
        
        if ($check_result && $check_result->num_rows > 0) {
            // Supprimer les adresses du client
            $delete_addresses_query = "DELETE FROM addresses WHERE user_id = ?";
            $stmt = $conn->prepare($delete_addresses_query);
            $stmt->bind_param("i", $customer_id);
            $stmt->execute();
            
            // Supprimer les sessions du client
            $delete_sessions_query = "DELETE FROM sessions WHERE user_id = ?";
            $stmt = $conn->prepare($delete_sessions_query);
            $stmt->bind_param("i", $customer_id);
            $stmt->execute();
            
            // Mettre à jour les commandes pour les marquer comme anonymes
            $update_orders_query = "UPDATE orders SET user_id = NULL WHERE user_id = ?";
            $stmt = $conn->prepare($update_orders_query);
            $stmt->bind_param("i", $customer_id);
            $stmt->execute();
            
            // Supprimer les avis du client
            $delete_reviews_query = "DELETE FROM product_reviews WHERE user_id = ?";
            $stmt = $conn->prepare($delete_reviews_query);
            $stmt->bind_param("i", $customer_id);
            $stmt->execute();
            
            // Supprimer le client
            $delete_query = "DELETE FROM users WHERE id = ?";
            $stmt = $conn->prepare($delete_query);
            $stmt->bind_param("i", $customer_id);
            $result = $stmt->execute();
            
            if ($result) {
                header("Location: customers.php?success=" . urlencode("Le client a été supprimé avec succès."));
                exit;
            } else {
                header("Location: customers.php?error=" . urlencode("Une erreur est survenue lors de la suppression du client."));
                exit;
            }
        } else {
            header("Location: customers.php?error=" . urlencode("Client introuvable."));
            exit;
        }
    }
}

// Traitement du changement de statut d'un client (activer/désactiver)
if (isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
    $new_status = isset($_POST['status']) ? intval($_POST['status']) : 0;
    
    if ($customer_id > 0) {
        // Pour cette démo, on utilise un champ fictif 'active' (vous pouvez ajouter ce champ à la table users)
        // Ou utiliser une autre logique selon vos besoins
        header("Location: customers.php?success=" . urlencode("Statut du client mis à jour."));
        exit;
    }
}

// Paramètres de filtrage et de tri
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'id';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'desc';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$customers_per_page = 15;

// Construction de la requête SQL de base
$sql_count = "SELECT COUNT(*) as total FROM users WHERE is_admin = 0";
$sql = "SELECT u.*, 
               COUNT(DISTINCT o.id) as total_orders,
               COALESCE(SUM(o.total_amount), 0) as total_spent,
               MAX(o.created_at) as last_order_date
        FROM users u 
        LEFT JOIN orders o ON u.id = o.user_id 
        WHERE u.is_admin = 0";

// Construction des conditions WHERE
$where_conditions = [];
$params = [];
$types = "";

// Recherche par terme
if (!empty($search_term)) {
    $where_conditions[] = "(u.username LIKE ? OR u.email LIKE ? OR u.full_name LIKE ? OR u.phone LIKE ?)";
    $search_param = "%" . $search_term . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

// Ajout des conditions WHERE à la requête
if (!empty($where_conditions)) {
    $sql_count .= " AND " . implode(" AND ", $where_conditions);
    $sql .= " AND " . implode(" AND ", $where_conditions);
}

// Ajout du GROUP BY pour la requête principale
$sql .= " GROUP BY u.id";

// Ajout du tri
$valid_sort_columns = ['id', 'username', 'email', 'full_name', 'created_at', 'last_login', 'total_orders', 'total_spent'];
$valid_sort_orders = ['asc', 'desc'];

if (!in_array($sort_by, $valid_sort_columns)) {
    $sort_by = 'id';
}
if (!in_array($sort_order, $valid_sort_orders)) {
    $sort_order = 'desc';
}

// Ajustement du tri pour les colonnes calculées
if ($sort_by === 'total_orders' || $sort_by === 'total_spent') {
    $sql .= " ORDER BY $sort_by $sort_order";
} else {
    $sql .= " ORDER BY u.$sort_by $sort_order";
}

// Récupération du nombre total de clients pour la pagination
$stmt_count = $conn->prepare($sql_count);
if (!empty($types)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$count_result = $stmt_count->get_result();
$row_count = $count_result->fetch_assoc();
$total_customers = $row_count['total'];
$total_pages = ceil($total_customers / $customers_per_page);

// Ajustement de la page courante
if ($page < 1) $page = 1;
if ($page > $total_pages && $total_pages > 0) $page = $total_pages;

// Ajout de la pagination à la requête
$offset = ($page - 1) * $customers_per_page;
$sql .= " LIMIT ?, ?";
$params[] = $offset;
$params[] = $customers_per_page;
$types .= "ii";

// Exécution de la requête paginée
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$customers = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
}

// Statistiques générales
$stats_query = "SELECT 
                   COUNT(*) as total_customers,
                   COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_this_month,
                   COUNT(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as active_this_month
                FROM users 
                WHERE is_admin = 0";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();
?>

<!-- Customers Management Content -->
<div class="space-y-6">
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Total Clients -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-gray-500 dark:text-gray-400 text-sm font-medium">Total des clients</h3>
                    <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo number_format($stats['total_customers']); ?></p>
                </div>
                <div class="p-3 rounded-full bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-400">
                    <i class="fas fa-users text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Nouveaux ce mois -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-gray-500 dark:text-gray-400 text-sm font-medium">Nouveaux ce mois</h3>
                    <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo number_format($stats['new_this_month']); ?></p>
                </div>
                <div class="p-3 rounded-full bg-green-100 dark:bg-green-900 text-green-600 dark:text-green-400">
                    <i class="fas fa-user-plus text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Actifs ce mois -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-gray-500 dark:text-gray-400 text-sm font-medium">Actifs ce mois</h3>
                    <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo number_format($stats['active_this_month']); ?></p>
                </div>
                <div class="p-3 rounded-full bg-purple-100 dark:bg-purple-900 text-purple-600 dark:text-purple-400">
                    <i class="fas fa-user-clock text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        <!-- Top Controls -->
        <div class="flex flex-col md:flex-row md:justify-between md:items-center mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 mb-4 md:mb-0">
                <h2 class="text-xl font-bold text-gray-800 dark:text-white">Liste des clients</h2>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Total: <?php echo $total_customers; ?> client(s)
                </div>
            </div>
            
            <div class="flex flex-col sm:flex-row gap-2 sm:gap-4">
                <button onclick="exportCustomers()" class="bg-green-600 hover:bg-green-700 text-white text-center px-4 py-2 rounded-md transition-colors">
                    <i class="fas fa-download mr-2"></i>Exporter CSV
                </button>
            </div>
        </div>
        
        <!-- Search and Filter -->
        <div class="mb-6 bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
            <form action="customers.php" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Search Field -->
                <div class="sm:col-span-2">
                    <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Recherche</label>
                    <div class="relative">
                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Nom, email, téléphone..." class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Sorting -->
                <div>
                    <label for="sort_by" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Trier par</label>
                    <select id="sort_by" name="sort_by" class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                        <option value="id" <?php echo $sort_by === 'id' ? 'selected' : ''; ?>>ID</option>
                        <option value="username" <?php echo $sort_by === 'username' ? 'selected' : ''; ?>>Nom d'utilisateur</option>
                        <option value="full_name" <?php echo $sort_by === 'full_name' ? 'selected' : ''; ?>>Nom complet</option>
                        <option value="email" <?php echo $sort_by === 'email' ? 'selected' : ''; ?>>Email</option>
                        <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Date d'inscription</option>
                        <option value="last_login" <?php echo $sort_by === 'last_login' ? 'selected' : ''; ?>>Dernière connexion</option>
                        <option value="total_orders" <?php echo $sort_by === 'total_orders' ? 'selected' : ''; ?>>Nombre de commandes</option>
                        <option value="total_spent" <?php echo $sort_by === 'total_spent' ? 'selected' : ''; ?>>Total dépensé</option>
                    </select>
                </div>
                
                <div>
                    <label for="sort_order" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ordre</label>
                    <select id="sort_order" name="sort_order" class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                        <option value="asc" <?php echo $sort_order === 'asc' ? 'selected' : ''; ?>>Croissant</option>
                        <option value="desc" <?php echo $sort_order === 'desc' ? 'selected' : ''; ?>>Décroissant</option>
                    </select>
                </div>
                
                <!-- Form Buttons -->
                <div class="sm:col-span-2 lg:col-span-4 flex flex-col sm:flex-row gap-2 sm:gap-4 sm:justify-end">
                    <button type="submit" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded-md transition-colors">
                        <i class="fas fa-filter mr-2"></i>Appliquer les filtres
                    </button>
                    <a href="customers.php" class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 text-center px-4 py-2 rounded-md transition-colors">
                        <i class="fas fa-undo mr-2"></i>Réinitialiser
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Customers Table -->
        <?php if (!empty($customers)): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Client
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Contact
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Inscription
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Dernière connexion
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Commandes
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Total dépensé
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($customers as $customer): ?>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10">
                                    <div class="h-10 w-10 rounded-full bg-netblue-100 dark:bg-netblue-900 flex items-center justify-center">
                                        <span class="text-sm font-medium text-netblue-800 dark:text-netblue-200">
                                            <?php echo strtoupper(substr($customer['username'] ?? $customer['email'], 0, 2)); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($customer['full_name'] ?? $customer['username']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        @<?php echo htmlspecialchars($customer['username']); ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm text-gray-900 dark:text-white">
                                <?php echo htmlspecialchars($customer['email']); ?>
                            </div>
                            <?php if (!empty($customer['phone'])): ?>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                <?php echo htmlspecialchars($customer['phone']); ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            <?php echo date('d/m/Y', strtotime($customer['created_at'])); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php if ($customer['last_login']): ?>
                            <span class="text-sm text-gray-900 dark:text-white">
                                <?php echo date('d/m/Y H:i', strtotime($customer['last_login'])); ?>
                            </span>
                            <?php else: ?>
                            <span class="text-sm text-gray-500 dark:text-gray-400">Jamais connecté</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <span class="text-sm font-medium text-gray-900 dark:text-white mr-2">
                                    <?php echo $customer['total_orders']; ?>
                                </span>
                                <?php if ($customer['total_orders'] > 0): ?>
                                <a href="orders.php?customer_id=<?php echo $customer['id']; ?>" class="text-netblue-600 hover:text-netblue-900 dark:text-netblue-400 dark:hover:text-netblue-200 text-xs">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                            <?php if ($customer['last_order_date']): ?>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                Dernière: <?php echo date('d/m/Y', strtotime($customer['last_order_date'])); ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm font-medium text-gray-900 dark:text-white">
                                <?php echo number_format($customer['total_spent'], 2); ?> FCFA
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-2">
                                <a href="customer_details.php?id=<?php echo $customer['id']; ?>" class="text-netblue-600 hover:text-netblue-900 dark:text-netblue-400 dark:hover:text-netblue-200" title="Voir les détails">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="orders.php?customer_id=<?php echo $customer['id']; ?>" class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-200" title="Voir les commandes">
                                    <i class="fas fa-shopping-cart"></i>
                                </a>
                                <form method="POST" action="customers.php" class="inline-block delete-form">
                                    <input type="hidden" name="action" value="delete_customer">
                                    <input type="hidden" name="customer_id" value="<?php echo $customer['id']; ?>">
                                    <button type="button" class="delete-button text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-200" title="Supprimer">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
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
                    Affichage de <span class="font-medium"><?php echo $offset + 1; ?></span> à <span class="font-medium"><?php echo min($offset + $customers_per_page, $total_customers); ?></span> sur <span class="font-medium"><?php echo $total_customers; ?></span> clients
                </span>
            </div>
            
            <nav class="inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search_term); ?>&sort_by=<?php echo $sort_by; ?>&sort_order=<?php echo $sort_order; ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
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
                    echo '<a href="?page=1&search=' . urlencode($search_term) . '&sort_by=' . $sort_by . '&sort_order=' . $sort_order . '" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">1</a>';
                    if ($start_page > 2) {
                        echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300">...</span>';
                    }
                }
                
                for ($i = $start_page; $i <= $end_page; $i++) {
                    if ($i == $page) {
                        echo '<span aria-current="page" class="relative inline-flex items-center px-4 py-2 border border-netblue-500 bg-netblue-50 dark:bg-netblue-900 text-sm font-medium text-netblue-600 dark:text-netblue-300">' . $i . '</span>';
                    } else {
                        echo '<a href="?page=' . $i . '&search=' . urlencode($search_term) . '&sort_by=' . $sort_by . '&sort_order=' . $sort_order . '" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">' . $i . '</a>';
                    }
                }
                
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                        echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300">...</span>';
                    }
                    echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search_term) . '&sort_by=' . $sort_by . '&sort_order=' . $sort_order . '" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">' . $total_pages . '</a>';
                }
                ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search_term); ?>&sort_by=<?php echo $sort_by; ?>&sort_order=<?php echo $sort_order; ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
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
                <i class="fas fa-users text-2xl"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Aucun client trouvé</h3>
            <p class="text-gray-500 dark:text-gray-400 max-w-md mx-auto mb-6">
                <?php if (!empty($search_term)): ?>
                    Aucun client ne correspond à vos critères de recherche. Essayez de modifier votre recherche.
                <?php else: ?>
                    Aucun client n'est encore inscrit sur votre site.
                <?php endif; ?>
            </p>
            <?php if (!empty($search_term)): ?>
            <a href="customers.php" class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 px-4 py-2 rounded-md transition-colors">
                <i class="fas fa-undo mr-2"></i>Réinitialiser la recherche
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Confirmation Modal for Delete -->
<div id="delete-confirmation-modal" class="fixed inset-0 z-50 overflow-auto bg-black bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6 transform transition-all">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Confirmer la suppression</h3>
            <button id="close-delete-modal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="mb-6">
            <p class="text-gray-700 dark:text-gray-300">Êtes-vous sûr de vouloir supprimer ce client ? Cette action supprimera également :</p>
            <ul class="mt-2 text-sm text-gray-600 dark:text-gray-400 list-disc list-inside">
                <li>Toutes ses adresses</li>
                <li>Ses sessions actives</li>
                <li>Ses avis produits</li>
                <li>Les commandes seront conservées mais marquées comme anonymes</li>
            </ul>
            <p class="mt-2 text-red-600 dark:text-red-400 font-medium">Cette action est irréversible !</p>
        </div>
        <div class="flex justify-end space-x-3">
            <button id="cancel-delete" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                Annuler
            </button>
            <button id="confirm-delete" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                Supprimer définitivement
            </button>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div id="export-modal" class="fixed inset-0 z-50 overflow-auto bg-black bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6 transform transition-all">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Exporter les clients</h3>
            <button id="close-export-modal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="mb-6">
            <p class="text-gray-700 dark:text-gray-300 mb-4">Choisissez les informations à inclure dans l'export :</p>
            <form id="export-form">
                <div class="space-y-2">
                    <label class="flex items-center">
                        <input type="checkbox" name="export_fields[]" value="basic_info" checked class="rounded border-gray-300 text-netblue-600 focus:ring-netblue-500">
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Informations de base (nom, email, téléphone)</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="export_fields[]" value="dates" checked class="rounded border-gray-300 text-netblue-600 focus:ring-netblue-500">
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Dates (inscription, dernière connexion)</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="export_fields[]" value="orders" checked class="rounded border-gray-300 text-netblue-600 focus:ring-netblue-500">
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Statistiques commandes (nombre, total dépensé)</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="export_fields[]" value="addresses" class="rounded border-gray-300 text-netblue-600 focus:ring-netblue-500">
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Adresses</span>
                    </label>
                </div>
            </form>
        </div>
        <div class="flex justify-end space-x-3">
            <button id="cancel-export" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                Annuler
            </button>
            <button id="confirm-export" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                <i class="fas fa-download mr-2"></i>Exporter
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Delete confirmation
    const deleteButtons = document.querySelectorAll('.delete-button');
    const deleteModal = document.getElementById('delete-confirmation-modal');
    const closeDeleteModalButton = document.getElementById('close-delete-modal');
    const cancelDeleteButton = document.getElementById('cancel-delete');
    const confirmDeleteButton = document.getElementById('confirm-delete');
    let currentForm = null;
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            currentForm = this.closest('.delete-form');
            deleteModal.classList.remove('hidden');
        });
    });
    
    const closeDeleteModal = function() {
        deleteModal.classList.add('hidden');
        currentForm = null;
    };
    
    closeDeleteModalButton.addEventListener('click', closeDeleteModal);
    cancelDeleteButton.addEventListener('click', closeDeleteModal);
    
    confirmDeleteButton.addEventListener('click', function() {
        if (currentForm) {
            currentForm.submit();
        }
    });
    
    // Export modal
    const exportModal = document.getElementById('export-modal');
    const closeExportModalButton = document.getElementById('close-export-modal');
    const cancelExportButton = document.getElementById('cancel-export');
    const confirmExportButton = document.getElementById('confirm-export');
    
    window.exportCustomers = function() {
        exportModal.classList.remove('hidden');
    };
    
    const closeExportModal = function() {
        exportModal.classList.add('hidden');
    };
    
    closeExportModalButton.addEventListener('click', closeExportModal);
    cancelExportButton.addEventListener('click', closeExportModal);
    
    confirmExportButton.addEventListener('click', function() {
        const form = document.getElementById('export-form');
        const formData = new FormData(form);
        const selectedFields = formData.getAll('export_fields[]');
        
        if (selectedFields.length === 0) {
            alert('Veuillez sélectionner au moins un champ à exporter.');
            return;
        }
        
        // Créer l'URL d'export avec les paramètres
        const exportUrl = new URL('export_customers.php', window.location.origin + '/admin/');
        selectedFields.forEach(field => {
            exportUrl.searchParams.append('fields[]', field);
        });
        
        // Ajouter les paramètres de filtrage actuels
        const currentUrl = new URL(window.location);
        const searchTerm = currentUrl.searchParams.get('search');
        if (searchTerm) {
            exportUrl.searchParams.set('search', searchTerm);
        }
        
        // Ouvrir le lien d'export
        window.open(exportUrl.toString(), '_blank');
        
        closeExportModal();
    });
    
    // Fermer les modals en cliquant en dehors
    deleteModal.addEventListener('click', function(e) {
        if (e.target === this) {
            closeDeleteModal();
        }
    });
    
    exportModal.addEventListener('click', function(e) {
        if (e.target === this) {
            closeExportModal();
        }
    });
    
    // Fermer les modals avec la touche Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (!deleteModal.classList.contains('hidden')) {
                closeDeleteModal();
            }
            if (!exportModal.classList.contains('hidden')) {
                closeExportModal();
            }
        }
    });
});

// Fonction pour formater les numéros avec des espaces
function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

// Mettre à jour les statistiques en temps réel si nécessaire
function updateStats() {
    // Cette fonction peut être appelée périodiquement pour mettre à jour les statistiques
    // Implementation dépendant de vos besoins
}

// Auto-submit du formulaire de recherche après un délai
let searchTimeout;
const searchInput = document.getElementById('search');

if (searchInput) {
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            // Optionnel : auto-submit après 2 secondes de pause dans la saisie
            // this.form.submit();
        }, 2000);
    });
}
</script>

<?php
// Inclusion du pied de page
require_once 'includes/footer.php';
?>
