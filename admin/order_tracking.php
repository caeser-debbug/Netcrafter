<?php
// Titre de la page pour l'inclusion de l'en-tête
$page_title = "Suivi des expéditions";

// Inclusion de l'en-tête
require_once 'includes/header.php';

// Inclure les fonctions spécifiques aux commandes
require_once 'order_functions.php';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ajout d'un nouveau suivi d'expédition
    if (isset($_POST['action']) && $_POST['action'] === 'add_tracking') {
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $tracking_number = isset($_POST['tracking_number']) ? trim($_POST['tracking_number']) : '';
        $carrier = isset($_POST['carrier']) ? trim($_POST['carrier']) : '';
        $location = isset($_POST['location']) ? trim($_POST['location']) : '';
        $tracking_notes = isset($_POST['tracking_notes']) ? trim($_POST['tracking_notes']) : '';
        $tracking_status = isset($_POST['tracking_status']) ? $_POST['tracking_status'] : '';
        
        // Validation basique
        if ($order_id === 0) {
            header("Location: order_tracking.php?error=" . urlencode("ID de commande invalide."));
            exit;
        }
        
        if (empty($tracking_number) || empty($carrier)) {
            header("Location: order_tracking.php?error=" . urlencode("Le numéro de suivi et le transporteur sont obligatoires."));
            exit;
        }
        
        // Validation du statut
        $valid_tracking_statuses = ['processing', 'shipped', 'in_transit', 'delivered', 'returned', 'cancelled'];
        if (!in_array($tracking_status, $valid_tracking_statuses)) {
            header("Location: order_tracking.php?error=" . urlencode("Statut de suivi invalide."));
            exit;
        }
        
        // Vérifier que la commande existe
        $check_order_query = "SELECT id, order_status FROM orders WHERE id = ?";
        $stmt = $conn->prepare($check_order_query);
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $check_result = $stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            header("Location: order_tracking.php?error=" . urlencode("Commande introuvable."));
            exit;
        }
        
        $order = $check_result->fetch_assoc();
        
        // Ajout du suivi
        $add_tracking_query = "INSERT INTO order_tracking (order_id, status, tracking_number, carrier, location, notes, updated_by, created_at) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        $updated_by = $_SESSION['admin_username'];
        
        $stmt = $conn->prepare($add_tracking_query);
        $stmt->bind_param("issssss", $order_id, $tracking_status, $tracking_number, $carrier, $location, $tracking_notes, $updated_by);
        $result = $stmt->execute();
        
        if ($result) {
            // Mettre à jour le statut de la commande si nécessaire
            if (($tracking_status === 'shipped' || $tracking_status === 'delivered' || $tracking_status === 'returned' || $tracking_status === 'cancelled') 
                && $order['order_status'] !== $tracking_status) {
                
                $update_order_query = "UPDATE orders SET order_status = ?, updated_at = NOW() WHERE id = ?";
                $stmt = $conn->prepare($update_order_query);
                $stmt->bind_param("si", $tracking_status, $order_id);
                $stmt->execute();
            }
            
            header("Location: order_tracking.php?success=" . urlencode("Les informations de suivi ont été ajoutées avec succès."));
            exit;
        } else {
            header("Location: order_tracking.php?error=" . urlencode("Une erreur est survenue lors de l'ajout des informations de suivi."));
            exit;
        }
    }
    
    // Mise à jour d'un suivi existant
    if (isset($_POST['action']) && $_POST['action'] === 'update_tracking') {
        $tracking_id = isset($_POST['tracking_id']) ? intval($_POST['tracking_id']) : 0;
        $tracking_status = isset($_POST['tracking_status']) ? $_POST['tracking_status'] : '';
        $location = isset($_POST['location']) ? trim($_POST['location']) : '';
        $tracking_notes = isset($_POST['tracking_notes']) ? trim($_POST['tracking_notes']) : '';
        
        // Validation basique
        if ($tracking_id === 0) {
            header("Location: order_tracking.php?error=" . urlencode("ID de suivi invalide."));
            exit;
        }
        
        // Validation du statut
        $valid_tracking_statuses = ['processing', 'shipped', 'in_transit', 'delivered', 'returned', 'cancelled'];
        if (!in_array($tracking_status, $valid_tracking_statuses)) {
            header("Location: order_tracking.php?error=" . urlencode("Statut de suivi invalide."));
            exit;
        }
        
        // Récupérer les informations actuelles du suivi
        $current_tracking_query = "SELECT ot.order_id, ot.status, o.order_status 
                                   FROM order_tracking ot
                                   JOIN orders o ON ot.order_id = o.id
                                   WHERE ot.id = ?";
        $stmt = $conn->prepare($current_tracking_query);
        $stmt->bind_param("i", $tracking_id);
        $stmt->execute();
        $current_tracking_result = $stmt->get_result();
        
        if ($current_tracking_result->num_rows === 0) {
            header("Location: order_tracking.php?error=" . urlencode("Suivi introuvable."));
            exit;
        }
        
        $current_tracking = $current_tracking_result->fetch_assoc();
        $order_id = $current_tracking['order_id'];
        
        // Mise à jour du suivi
        $update_tracking_query = "UPDATE order_tracking SET status = ?, location = ?, notes = ? WHERE id = ?";
        
        $stmt = $conn->prepare($update_tracking_query);
        $stmt->bind_param("sssi", $tracking_status, $location, $tracking_notes, $tracking_id);
        $result = $stmt->execute();
        
        if ($result) {
            // Mettre à jour le statut de la commande si nécessaire
            if (($tracking_status === 'shipped' || $tracking_status === 'delivered' || $tracking_status === 'returned' || $tracking_status === 'cancelled') 
                && $current_tracking['order_status'] !== $tracking_status) {
                
                $update_order_query = "UPDATE orders SET order_status = ?, updated_at = NOW() WHERE id = ?";
                $stmt = $conn->prepare($update_order_query);
                $stmt->bind_param("si", $tracking_status, $order_id);
                $stmt->execute();
            }
            
            header("Location: order_tracking.php?success=" . urlencode("Les informations de suivi ont été mises à jour avec succès."));
            exit;
        } else {
            header("Location: order_tracking.php?error=" . urlencode("Une erreur est survenue lors de la mise à jour des informations de suivi."));
            exit;
        }
    }
}

// Paramètres de filtrage et de tri
$order_filter = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$carrier_filter = isset($_GET['carrier']) ? trim($_GET['carrier']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'created_at';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'desc';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$items_per_page = 10;

// Construction de la requête SQL de base
$sql_count = "SELECT COUNT(*) as total FROM order_tracking ot JOIN orders o ON ot.order_id = o.id";
$sql = "SELECT ot.*, o.order_number, u.username, u.email 
        FROM order_tracking ot 
        JOIN orders o ON ot.order_id = o.id
        LEFT JOIN users u ON o.user_id = u.id";

// Construction des conditions WHERE
$where_conditions = [];
$params = [];
$types = "";

// Filtre par commande
if ($order_filter > 0) {
    $where_conditions[] = "ot.order_id = ?";
    $params[] = $order_filter;
    $types .= "i";
}

// Filtre par statut
if (!empty($status_filter) && in_array($status_filter, ['processing', 'shipped', 'in_transit', 'delivered', 'returned', 'cancelled'])) {
    $where_conditions[] = "ot.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Filtre par transporteur
if (!empty($carrier_filter)) {
    $where_conditions[] = "ot.carrier LIKE ?";
    $params[] = "%" . $carrier_filter . "%";
    $types .= "s";
}

// Filtre par date
if (!empty($date_from)) {
    $date_from_formatted = date('Y-m-d 00:00:00', strtotime($date_from));
    $where_conditions[] = "ot.created_at >= ?";
    $params[] = $date_from_formatted;
    $types .= "s";
}

if (!empty($date_to)) {
    $date_to_formatted = date('Y-m-d 23:59:59', strtotime($date_to));
    $where_conditions[] = "ot.created_at <= ?";
    $params[] = $date_to_formatted;
    $types .= "s";
}

// Ajout des conditions WHERE à la requête
if (!empty($where_conditions)) {
    $sql_count .= " WHERE " . implode(" AND ", $where_conditions);
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

// Ajout du tri
$valid_sort_columns = ['created_at', 'status', 'carrier', 'order_id'];
$valid_sort_orders = ['asc', 'desc'];

if (!in_array($sort_by, $valid_sort_columns)) {
    $sort_by = 'created_at';
}
if (!in_array($sort_order, $valid_sort_orders)) {
    $sort_order = 'desc';
}

$sql .= " ORDER BY ot.$sort_by $sort_order";

// Récupération du nombre total de suivis pour la pagination
$stmt_count = $conn->prepare($sql_count);
if (!empty($types)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$count_result = $stmt_count->get_result();
$row_count = $count_result->fetch_assoc();
$total_items = $row_count['total'];
$total_pages = ceil($total_items / $items_per_page);

// Ajustement de la page courante
if ($page < 1) $page = 1;
if ($page > $total_pages && $total_pages > 0) $page = $total_pages;

// Ajout de la pagination à la requête
$offset = ($page - 1) * $items_per_page;
$sql .= " LIMIT ?, ?";
$params[] = $offset;
$params[] = $items_per_page;
$types .= "ii";

// Exécution de la requête paginée
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$tracking_items = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $tracking_items[] = $row;
    }
}

// Récupérer la liste des commandes pour le filtre
$orders_query = "SELECT id, order_number FROM orders ORDER BY created_at DESC LIMIT 100";
$orders_result = $conn->query($orders_query);
$orders_list = [];
if ($orders_result && $orders_result->num_rows > 0) {
    while ($row = $orders_result->fetch_assoc()) {
        $orders_list[] = $row;
    }
}

// Récupérer la liste des transporteurs uniques pour le filtre
$carriers_query = "SELECT DISTINCT carrier FROM order_tracking WHERE carrier != '' ORDER BY carrier";
$carriers_result = $conn->query($carriers_query);
$carriers_list = [];
if ($carriers_result && $carriers_result->num_rows > 0) {
    while ($row = $carriers_result->fetch_assoc()) {
        $carriers_list[] = $row['carrier'];
    }
}
?>

<!-- Order Tracking Management Content -->
<div class="space-y-6">
    <!-- Tracking Summary Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- In Processing -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 border-l-4 border-blue-500">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-400 mr-4">
                    <i class="fas fa-cogs"></i>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">En traitement</p>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white">
                        <?php 
                        $processing_count_query = "SELECT COUNT(*) as count FROM order_tracking WHERE status = 'processing'";
                        $processing_count_result = $conn->query($processing_count_query);
                        $processing_count = $processing_count_result->fetch_assoc()['count'] ?? 0;
                        echo $processing_count;
                        ?>
                    </h3>
                </div>
            </div>
        </div>
        
        <!-- Shipped -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 border-l-4 border-purple-500">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100 dark:bg-purple-900 text-purple-600 dark:text-purple-400 mr-4">
                    <i class="fas fa-shipping-fast"></i>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">Expédiées</p>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white">
                        <?php 
                        $shipped_count_query = "SELECT COUNT(*) as count FROM order_tracking WHERE status = 'shipped'";
                        $shipped_count_result = $conn->query($shipped_count_query);
                        $shipped_count = $shipped_count_result->fetch_assoc()['count'] ?? 0;
                        echo $shipped_count;
                        ?>
                    </h3>
                </div>
            </div>
        </div>
        
        <!-- In Transit -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 border-l-4 border-amber-500">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-amber-100 dark:bg-amber-900 text-amber-600 dark:text-amber-400 mr-4">
                    <i class="fas fa-truck"></i>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">En transit</p>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white">
                        <?php 
                        $transit_count_query = "SELECT COUNT(*) as count FROM order_tracking WHERE status = 'in_transit'";
                        $transit_count_result = $conn->query($transit_count_query);
                        $transit_count = $transit_count_result->fetch_assoc()['count'] ?? 0;
                        echo $transit_count;
                        ?>
                    </h3>
                </div>
            </div>
        </div>
        
        <!-- Delivered -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 border-l-4 border-green-500">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 dark:bg-green-900 text-green-600 dark:text-green-400 mr-4">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">Livrées</p>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white">
                        <?php 
                        $delivered_count_query = "SELECT COUNT(*) as count FROM order_tracking WHERE status = 'delivered'";
                        $delivered_count_result = $conn->query($delivered_count_query);
                        $delivered_count = $delivered_count_result->fetch_assoc()['count'] ?? 0;
                        echo $delivered_count;
                        ?>
                    </h3>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tracking List Card -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        <!-- Header with Add Button -->
        <div class="flex flex-col md:flex-row md:justify-between md:items-center mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 mb-4 md:mb-0">
                <h2 class="text-xl font-bold text-gray-800 dark:text-white">Suivi des expéditions</h2>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Total: <?php echo $total_items; ?> suivi(s)
                </div>
            </div>
            
            <button type="button" id="add-tracking-btn" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded-md transition-colors">
                <i class="fas fa-plus mr-2"></i>Ajouter un suivi
            </button>
        </div>
        
        <!-- Filters and Search -->
        <div class="mb-6 bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
            <form action="order_tracking.php" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <!-- Order Filter -->
                <div>
                    <label for="order_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Commande</label>
                    <select id="order_id" name="order_id" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:ring-netblue-500 focus:border-netblue-500 dark:bg-gray-800 dark:text-white">
                        <option value="0">Toutes les commandes</option>
                        <?php foreach ($orders_list as $order): ?>
                        <option value="<?php echo $order['id']; ?>" <?php echo $order_filter == $order['id'] ? 'selected' : ''; ?>>
                            #<?php echo htmlspecialchars($order['order_number']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Status Filter -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Statut</label>
                    <select id="status" name="status" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:ring-netblue-500 focus:border-netblue-500 dark:bg-gray-800 dark:text-white">
                        <option value="">Tous les statuts</option>
                        <option value="processing" <?php echo $status_filter === 'processing' ? 'selected' : ''; ?>>En traitement</option>
                        <option value="shipped" <?php echo $status_filter === 'shipped' ? 'selected' : ''; ?>>Expédiée</option>
                        <option value="in_transit" <?php echo $status_filter === 'in_transit' ? 'selected' : ''; ?>>En transit</option>
                        <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>Livrée</option>
                        <option value="returned" <?php echo $status_filter === 'returned' ? 'selected' : ''; ?>>Retournée</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Annulée</option>
                    </select>
                </div>
                
                <!-- Carrier Filter -->
                <div>
                    <label for="carrier" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Transporteur</label>
                    <select id="carrier" name="carrier" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:ring-netblue-500 focus:border-netblue-500 dark:bg-gray-800 dark:text-white">
                        <option value="">Tous les transporteurs</option>
                        <?php foreach ($carriers_list as $carrier): ?>
                        <option value="<?php echo htmlspecialchars($carrier); ?>" <?php echo $carrier_filter === $carrier ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($carrier); ?>
                        </option>
                        <?php endforeach; ?>
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
                
                <!-- Form Buttons -->
                <div class="lg:col-span-5 flex flex-col sm:flex-row gap-2 sm:gap-4 sm:justify-end">
                    <button type="submit" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded-md transition-colors">
                        <i class="fas fa-filter mr-2"></i>Appliquer les filtres
                    </button>
                    <a href="order_tracking.php" class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 text-center px-4 py-2 rounded-md transition-colors">
                        <i class="fas fa-undo mr-2"></i>Réinitialiser
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Tracking Table -->
        <?php if (!empty($tracking_items)): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Commande
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Date
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Statut
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Numéro de suivi
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Transporteur
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Localisation
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($tracking_items as $item): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <a href="order_details.php?id=<?php echo $item['order_id']; ?>" class="text-netblue-600 dark:text-netblue-400 hover:underline">
                                #<?php echo htmlspecialchars($item['order_number']); ?>
                            </a>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            <?php echo formatDate($item['created_at'], 'd/m/Y H:i'); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php 
                            switch ($item['status']) {
                                case 'processing':
                                    echo '<span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">En traitement</span>';
                                    break;
                                case 'shipped':
                                    echo '<span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">Expédiée</span>';
                                    break;
                                case 'in_transit':
                                    echo '<span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200">En transit</span>';
                                    break;
                                case 'delivered':
                                    echo '<span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Livrée</span>';
                                    break;
                                case 'returned':
                                    echo '<span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Retournée</span>';
                                    break;
                                case 'cancelled':
                                    echo '<span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Annulée</span>';
                                    break;
                                default:
                                    echo '<span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Inconnu</span>';
                            }
                            ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            <?php echo !empty($item['tracking_number']) ? htmlspecialchars($item['tracking_number']) : '-'; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            <?php echo !empty($item['carrier']) ? htmlspecialchars($item['carrier']) : '-'; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            <?php echo !empty($item['location']) ? htmlspecialchars($item['location']) : '-'; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex items-center space-x-2">
                                <button type="button" class="edit-tracking-btn text-netblue-600 hover:text-netblue-900 dark:text-netblue-400 dark:hover:text-netblue-200" 
                                        data-id="<?php echo $item['id']; ?>" 
                                        data-status="<?php echo $item['status']; ?>" 
                                        data-location="<?php echo htmlspecialchars($item['location']); ?>" 
                                        data-notes="<?php echo htmlspecialchars($item['notes']); ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="order_details.php?id=<?php echo $item['order_id']; ?>" class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200">
                                    <i class="fas fa-eye"></i>
                                </a>
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
                    Affichage de <span class="font-medium"><?php echo $offset + 1; ?></span> à <span class="font-medium"><?php echo min($offset + $items_per_page, $total_items); ?></span> sur <span class="font-medium"><?php echo $total_items; ?></span> suivis
                </span>
            </div>
            
            <nav class="inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&order_id=<?php echo $order_filter; ?>&status=<?php echo urlencode($status_filter); ?>&carrier=<?php echo urlencode($carrier_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
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
                    echo '<a href="?page=1&order_id=' . $order_filter . '&status=' . urlencode($status_filter) . '&carrier=' . urlencode($carrier_filter) . '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) . '" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">1</a>';
                    if ($start_page > 2) {
                        echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300">...</span>';
                    }
                }
                
                for ($i = $start_page; $i <= $end_page; $i++) {
                    if ($i == $page) {
                        echo '<span aria-current="page" class="relative inline-flex items-center px-4 py-2 border border-netblue-500 bg-netblue-50 dark:bg-netblue-900 text-sm font-medium text-netblue-600 dark:text-netblue-300">' . $i . '</span>';
                    } else {
                        echo '<a href="?page=' . $i . '&order_id=' . $order_filter . '&status=' . urlencode($status_filter) . '&carrier=' . urlencode($carrier_filter) . '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) . '" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">' . $i . '</a>';
                    }
                }
                
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                        echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300">...</span>';
                    }
                    echo '<a href="?page=' . $total_pages . '&order_id=' . $order_filter . '&status=' . urlencode($status_filter) . '&carrier=' . urlencode($carrier_filter) . '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) . '" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">' . $total_pages . '</a>';
                }
                ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&order_id=<?php echo $order_filter; ?>&status=<?php echo urlencode($status_filter); ?>&carrier=<?php echo urlencode($carrier_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
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
        <div class="text-center py-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-400 dark:text-gray-500 mb-4">
                <i class="fas fa-shipping-fast text-2xl"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Aucun suivi trouvé</h3>
            <p class="text-gray-500 dark:text-gray-400 max-w-md mx-auto mb-6">
                <?php if (!empty($order_filter) || !empty($status_filter) || !empty($carrier_filter) || !empty($date_from) || !empty($date_to)): ?>
                    Aucun suivi ne correspond à vos critères de recherche. Essayez de modifier vos filtres.
                <?php else: ?>
                    Aucun suivi d'expédition n'a encore été enregistré. Commencez par ajouter un suivi pour une commande.
                <?php endif; ?>
            </p>
            <div class="flex justify-center space-x-4">
                <button type="button" id="add-tracking-btn-empty" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded-md transition-colors">
                    <i class="fas fa-plus mr-2"></i>Ajouter un suivi
                </button>
                <?php if (!empty($order_filter) || !empty($status_filter) || !empty($carrier_filter) || !empty($date_from) || !empty($date_to)): ?>
                <a href="order_tracking.php" class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 px-4 py-2 rounded-md transition-colors">
                    <i class="fas fa-undo mr-2"></i>Réinitialiser les filtres
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Tracking Modal -->
<div id="add-tracking-modal" class="fixed inset-0 z-50 overflow-auto bg-black bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6 transform transition-all">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Ajouter un suivi d'expédition</h3>
            <button type="button" class="close-modal text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form action="order_tracking.php" method="POST">
            <input type="hidden" name="action" value="add_tracking">
            
            <div class="space-y-4">
                <div>
                    <label for="order_id_modal" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Commande</label>
                    <select id="order_id_modal" name="order_id" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:ring-netblue-500 focus:border-netblue-500 dark:bg-gray-700 dark:text-white" required>
                        <option value="">Sélectionnez une commande</option>
                        <?php foreach ($orders_list as $order): ?>
                        <option value="<?php echo $order['id']; ?>" <?php echo $order_filter == $order['id'] ? 'selected' : ''; ?>>
                            #<?php echo htmlspecialchars($order['order_number']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="tracking_status_modal" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Statut</label>
                    <select id="tracking_status_modal" name="tracking_status" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:ring-netblue-500 focus:border-netblue-500 dark:bg-gray-700 dark:text-white" required>
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
                        <label for="tracking_number_modal" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Numéro de suivi</label>
                        <input type="text" id="tracking_number_modal" name="tracking_number" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:ring-netblue-500 focus:border-netblue-500 dark:bg-gray-700 dark:text-white" required>
                    </div>
                    
                    <div>
                        <label for="carrier_modal" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Transporteur</label>
                        <input type="text" id="carrier_modal" name="carrier" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:ring-netblue-500 focus:border-netblue-500 dark:bg-gray-700 dark:text-white" required>
                    </div>
                </div>
                
                <div>
                    <label for="location_modal" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Localisation actuelle (facultatif)</label>
                    <input type="text" id="location_modal" name="location" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:ring-netblue-500 focus:border-netblue-500 dark:bg-gray-700 dark:text-white">
                </div>
                
                <div>
                    <label for="tracking_notes_modal" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Notes (facultatif)</label>
                    <textarea id="tracking_notes_modal" name="tracking_notes" rows="3" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:ring-netblue-500 focus:border-netblue-500 dark:bg-gray-700 dark:text-white"></textarea>
                </div>
            </div>
            
            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" class="close-modal px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                    Annuler
                </button>
                <button type="submit" class="px-4 py-2 bg-netblue-600 text-white rounded-md hover:bg-netblue-700 transition-colors">
                    Ajouter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Tracking Modal -->
<div id="edit-tracking-modal" class="fixed inset-0 z-50 overflow-auto bg-black bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6 transform transition-all">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Modifier le suivi</h3>
            <button type="button" class="close-modal text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form action="order_tracking.php" method="POST">
            <input type="hidden" name="action" value="update_tracking">
            <input type="hidden" name="tracking_id" id="edit_tracking_id" value="">
            
            <div class="space-y-4">
                <div>
                    <label for="edit_tracking_status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Statut</label>
                    <select id="edit_tracking_status" name="tracking_status" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:ring-netblue-500 focus:border-netblue-500 dark:bg-gray-700 dark:text-white" required>
                        <option value="processing">En traitement</option>
                        <option value="shipped">Expédiée</option>
                        <option value="in_transit">En transit</option>
                        <option value="delivered">Livrée</option>
                        <option value="returned">Retournée</option>
                        <option value="cancelled">Annulée</option>
                    </select>
                </div>
                
                <div>
                    <label for="edit_location" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Localisation actuelle</label>
                    <input type="text" id="edit_location" name="location" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:ring-netblue-500 focus:border-netblue-500 dark:bg-gray-700 dark:text-white">
                </div>
                
                <div>
                    <label for="edit_tracking_notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Notes</label>
                    <textarea id="edit_tracking_notes" name="tracking_notes" rows="3" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:ring-netblue-500 focus:border-netblue-500 dark:bg-gray-700 dark:text-white"></textarea>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Modal handling
    const addTrackingModal = document.getElementById('add-tracking-modal');
    const addTrackingBtn = document.getElementById('add-tracking-btn');
    const addTrackingBtnEmpty = document.getElementById('add-tracking-btn-empty');
    const editTrackingModal = document.getElementById('edit-tracking-modal');
    const editTrackingBtns = document.querySelectorAll('.edit-tracking-btn');
    const closeModalButtons = document.querySelectorAll('.close-modal');
    
    // Initialize datepickers
    if (flatpickr) {
        flatpickr(".datepicker", {
            dateFormat: "d/m/Y",
            locale: "fr"
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
    
    // Show Edit Tracking Modal
    editTrackingBtns.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const status = this.getAttribute('data-status');
            const location = this.getAttribute('data-location');
            const notes = this.getAttribute('data-notes');
            
            document.getElementById('edit_tracking_id').value = id;
            document.getElementById('edit_tracking_status').value = status;
            document.getElementById('edit_location').value = location;
            document.getElementById('edit_tracking_notes').value = notes;
            
            editTrackingModal.classList.remove('hidden');
        });
    });
    
    // Close Modals
    closeModalButtons.forEach(button => {
        button.addEventListener('click', function() {
            addTrackingModal.classList.add('hidden');
            editTrackingModal.classList.add('hidden');
        });
    });
    
    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === addTrackingModal) {
            addTrackingModal.classList.add('hidden');
        }
        if (event.target === editTrackingModal) {
            editTrackingModal.classList.add('hidden');
        }
    });
    
    // Escape key to close modals
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            addTrackingModal.classList.add('hidden');
            editTrackingModal.classList.add('hidden');
        }
    });
});
</script>

<?php
// Inclusion du pied de page
require_once 'includes/footer.php';
?>