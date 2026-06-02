<?php
// Titre de la page pour l'inclusion de l'en-tête
$page_title = "Gestion des fournisseurs";

// Inclusion de l'en-tête
require_once 'includes/header.php';

// Traitement de l'ajout d'un fournisseur
if (isset($_POST['action']) && $_POST['action'] === 'add_supplier') {
    $name = trim($_POST['name'] ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $country = trim($_POST['country'] ?? 'China');
    $shipping_method = trim($_POST['shipping_method'] ?? '');
    $average_delivery_time = intval($_POST['average_delivery_time'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    $errors = [];
    
    // Validation
    if (empty($name)) {
        $errors[] = "Le nom du fournisseur est obligatoire.";
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'adresse email n'est pas valide.";
    }
    
    if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
        $errors[] = "L'URL du site web n'est pas valide.";
    }
    
    if (empty($errors)) {
        $insert_query = "INSERT INTO suppliers (name, contact_person, email, phone, website, country, shipping_method, average_delivery_time, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("sssssssis", $name, $contact_person, $email, $phone, $website, $country, $shipping_method, $average_delivery_time, $notes, $status);
        
        if ($stmt->execute()) {
            header("Location: suppliers.php?success=" . urlencode("Le fournisseur a été ajouté avec succès."));
            exit;
        } else {
            $errors[] = "Une erreur est survenue lors de l'ajout du fournisseur.";
        }
    }
}

// Traitement de la modification d'un fournisseur
if (isset($_POST['action']) && $_POST['action'] === 'edit_supplier') {
    $supplier_id = intval($_POST['supplier_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $country = trim($_POST['country'] ?? 'China');
    $shipping_method = trim($_POST['shipping_method'] ?? '');
    $average_delivery_time = intval($_POST['average_delivery_time'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    $errors = [];
    
    // Validation
    if (empty($name)) {
        $errors[] = "Le nom du fournisseur est obligatoire.";
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'adresse email n'est pas valide.";
    }
    
    if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
        $errors[] = "L'URL du site web n'est pas valide.";
    }
    
    if (empty($errors) && $supplier_id > 0) {
        $update_query = "UPDATE suppliers SET name = ?, contact_person = ?, email = ?, phone = ?, website = ?, country = ?, shipping_method = ?, average_delivery_time = ?, notes = ?, status = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("sssssssisi", $name, $contact_person, $email, $phone, $website, $country, $shipping_method, $average_delivery_time, $notes, $status, $supplier_id);
        
        if ($stmt->execute()) {
            header("Location: suppliers.php?success=" . urlencode("Le fournisseur a été modifié avec succès."));
            exit;
        } else {
            $errors[] = "Une erreur est survenue lors de la modification du fournisseur.";
        }
    }
}

// Traitement de la suppression d'un fournisseur
if (isset($_POST['action']) && $_POST['action'] === 'delete_supplier') {
    $supplier_id = intval($_POST['supplier_id'] ?? 0);
    
    if ($supplier_id > 0) {
        // Vérifier si le fournisseur existe
        $check_query = "SELECT id FROM suppliers WHERE id = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("i", $supplier_id);
        $stmt->execute();
        $check_result = $stmt->get_result();
        
        if ($check_result && $check_result->num_rows > 0) {
            // Vérifier s'il y a des produits liés à ce fournisseur
            $products_check_query = "SELECT COUNT(*) as count FROM products WHERE supplier_id = ?";
            $stmt = $conn->prepare($products_check_query);
            $stmt->bind_param("s", $supplier_id);
            $stmt->execute();
            $products_result = $stmt->get_result();
            $products_count = $products_result->fetch_assoc()['count'];
            
            if ($products_count > 0) {
                header("Location: suppliers.php?error=" . urlencode("Impossible de supprimer ce fournisseur car $products_count produit(s) y sont liés."));
                exit;
            }
            
            // Supprimer le fournisseur
            $delete_query = "DELETE FROM suppliers WHERE id = ?";
            $stmt = $conn->prepare($delete_query);
            $stmt->bind_param("i", $supplier_id);
            $result = $stmt->execute();
            
            if ($result) {
                header("Location: suppliers.php?success=" . urlencode("Le fournisseur a été supprimé avec succès."));
                exit;
            } else {
                header("Location: suppliers.php?error=" . urlencode("Une erreur est survenue lors de la suppression du fournisseur."));
                exit;
            }
        } else {
            header("Location: suppliers.php?error=" . urlencode("Fournisseur introuvable."));
            exit;
        }
    }
}

// Traitement du changement de statut d'un fournisseur
if (isset($_POST['action']) && $_POST['action'] === 'change_status') {
    $supplier_id = intval($_POST['supplier_id'] ?? 0);
    $new_status = $_POST['status'] ?? '';
    
    if ($supplier_id > 0 && in_array($new_status, ['active', 'inactive'])) {
        $update_query = "UPDATE suppliers SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("si", $new_status, $supplier_id);
        $result = $stmt->execute();
        
        if ($result) {
            header("Location: suppliers.php?success=" . urlencode("Le statut du fournisseur a été mis à jour avec succès."));
            exit;
        } else {
            header("Location: suppliers.php?error=" . urlencode("Une erreur est survenue lors de la mise à jour du statut."));
            exit;
        }
    }
}

// Paramètres de filtrage et de tri
$country_filter = isset($_GET['country']) ? trim($_GET['country']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'id';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'desc';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$suppliers_per_page = 10;

// Construction de la requête SQL de base
$sql_count = "SELECT COUNT(*) as total FROM suppliers";
$sql = "SELECT s.*, 
               COUNT(DISTINCT p.id) as products_count,
               AVG(CASE WHEN p.status = 'active' THEN p.stock ELSE NULL END) as avg_stock
        FROM suppliers s 
        LEFT JOIN products p ON s.id = p.supplier_id";

// Construction des conditions WHERE
$where_conditions = [];
$params = [];
$types = "";

// Filtre par pays
if (!empty($country_filter)) {
    $where_conditions[] = "s.country LIKE ?";
    $params[] = "%" . $country_filter . "%";
    $types .= "s";
}

// Filtre par statut
if (!empty($status_filter) && in_array($status_filter, ['active', 'inactive'])) {
    $where_conditions[] = "s.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Recherche par terme
if (!empty($search_term)) {
    $where_conditions[] = "(s.name LIKE ? OR s.contact_person LIKE ? OR s.email LIKE ? OR s.phone LIKE ?)";
    $search_param = "%" . $search_term . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

// Ajout des conditions WHERE à la requête
if (!empty($where_conditions)) {
    $sql_count .= " WHERE " . implode(" AND ", $where_conditions);
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

// Ajout du GROUP BY pour la requête principale
$sql .= " GROUP BY s.id";

// Ajout du tri
$valid_sort_columns = ['id', 'name', 'country', 'created_at', 'products_count', 'average_delivery_time'];
$valid_sort_orders = ['asc', 'desc'];

if (!in_array($sort_by, $valid_sort_columns)) {
    $sort_by = 'id';
}
if (!in_array($sort_order, $valid_sort_orders)) {
    $sort_order = 'desc';
}

// Ajustement du tri pour les colonnes calculées
if ($sort_by === 'products_count') {
    $sql .= " ORDER BY products_count $sort_order";
} else {
    $sql .= " ORDER BY s.$sort_by $sort_order";
}

// Récupération du nombre total de fournisseurs pour la pagination
$stmt_count = $conn->prepare($sql_count);
if (!empty($types)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$count_result = $stmt_count->get_result();
$row_count = $count_result->fetch_assoc();
$total_suppliers = $row_count['total'];
$total_pages = ceil($total_suppliers / $suppliers_per_page);

// Ajustement de la page courante
if ($page < 1) $page = 1;
if ($page > $total_pages && $total_pages > 0) $page = $total_pages;

// Ajout de la pagination à la requête
$offset = ($page - 1) * $suppliers_per_page;
$sql .= " LIMIT ?, ?";
$params[] = $offset;
$params[] = $suppliers_per_page;
$types .= "ii";

// Exécution de la requête paginée
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$suppliers = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $suppliers[] = $row;
    }
}

// Récupération des pays pour le filtre
$countries_query = "SELECT DISTINCT country FROM suppliers WHERE country IS NOT NULL AND country != '' ORDER BY country";
$countries_result = $conn->query($countries_query);
$countries = [];
if ($countries_result && $countries_result->num_rows > 0) {
    while ($row = $countries_result->fetch_assoc()) {
        $countries[] = $row['country'];
    }
}

// Statistiques générales
$stats_query = "SELECT 
                   COUNT(*) as total_suppliers,
                   COUNT(CASE WHEN status = 'active' THEN 1 END) as active_suppliers,
                   COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_suppliers,
                   AVG(average_delivery_time) as avg_delivery_time
                FROM suppliers";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Récupération du fournisseur à modifier (si edit_id est présent)
$edit_supplier = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $edit_query = "SELECT * FROM suppliers WHERE id = ?";
    $stmt = $conn->prepare($edit_query);
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_result = $stmt->get_result();
    if ($edit_result && $edit_result->num_rows > 0) {
        $edit_supplier = $edit_result->fetch_assoc();
    }
}
?>

<!-- Suppliers Management Content -->
<div class="space-y-6">
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <!-- Total Fournisseurs -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-gray-500 dark:text-gray-400 text-sm font-medium">Total fournisseurs</h3>
                    <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo number_format($stats['total_suppliers']); ?></p>
                </div>
                <div class="p-3 rounded-full bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-400">
                    <i class="fas fa-truck text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Actifs -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-gray-500 dark:text-gray-400 text-sm font-medium">Actifs</h3>
                    <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo number_format($stats['active_suppliers']); ?></p>
                </div>
                <div class="p-3 rounded-full bg-green-100 dark:bg-green-900 text-green-600 dark:text-green-400">
                    <i class="fas fa-check-circle text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Inactifs -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-gray-500 dark:text-gray-400 text-sm font-medium">Inactifs</h3>
                    <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo number_format($stats['inactive_suppliers']); ?></p>
                </div>
                <div class="p-3 rounded-full bg-red-100 dark:bg-red-900 text-red-600 dark:text-red-400">
                    <i class="fas fa-times-circle text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Délai moyen -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-gray-500 dark:text-gray-400 text-sm font-medium">Délai moyen</h3>
                    <p class="text-2xl font-bold text-gray-800 dark:text-white">
                        <?php echo round($stats['avg_delivery_time'] ?? 0); ?> j
                    </p>
                </div>
                <div class="p-3 rounded-full bg-purple-100 dark:bg-purple-900 text-purple-600 dark:text-purple-400">
                    <i class="fas fa-clock text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Formulaire d'ajout/modification -->
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-6">
                    <?php echo $edit_supplier ? 'Modifier le fournisseur' : 'Ajouter un fournisseur'; ?>
                </h3>
                
                <form method="POST" action="suppliers.php">
                    <input type="hidden" name="action" value="<?php echo $edit_supplier ? 'edit_supplier' : 'add_supplier'; ?>">
                    <?php if ($edit_supplier): ?>
                    <input type="hidden" name="supplier_id" value="<?php echo $edit_supplier['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="space-y-4">
                        <!-- Nom -->
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Nom du fournisseur <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="name" name="name" required 
                                   value="<?php echo htmlspecialchars($edit_supplier['name'] ?? ''); ?>"
                                   class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white px-3 py-2">
                        </div>
                        
                        <!-- Personne de contact -->
                        <div>
                            <label for="contact_person" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Personne de contact
                            </label>
                            <input type="text" id="contact_person" name="contact_person" 
                                   value="<?php echo htmlspecialchars($edit_supplier['contact_person'] ?? ''); ?>"
                                   class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white px-3 py-2">
                        </div>
                        
                        <!-- Email -->
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Email
                            </label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($edit_supplier['email'] ?? ''); ?>"
                                   class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white px-3 py-2">
                        </div>
                        
                        <!-- Téléphone -->
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Téléphone
                            </label>
                            <input type="text" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($edit_supplier['phone'] ?? ''); ?>"
                                   class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white px-3 py-2">
                        </div>
                        
                        <!-- Site web -->
                        <div>
                            <label for="website" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Site web
                            </label>
                            <input type="url" id="website" name="website" 
                                   value="<?php echo htmlspecialchars($edit_supplier['website'] ?? ''); ?>"
                                   placeholder="https://example.com"
                                   class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white px-3 py-2">
                        </div>
                        
                        <!-- Pays -->
                        <div>
                            <label for="country" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Pays
                            </label>
                            <input type="text" id="country" name="country" 
                                   value="<?php echo htmlspecialchars($edit_supplier['country'] ?? 'China'); ?>"
                                   class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white px-3 py-2">
                        </div>
                        
                        <!-- Méthode d'expédition -->
                        <div>
                            <label for="shipping_method" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Méthode d'expédition
                            </label>
                            <select id="shipping_method" name="shipping_method" 
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white px-3 py-2">
                                <option value="">Sélectionner...</option>
                                <option value="Standard" <?php echo ($edit_supplier['shipping_method'] ?? '') === 'Standard' ? 'selected' : ''; ?>>Standard</option>
                                <option value="Express" <?php echo ($edit_supplier['shipping_method'] ?? '') === 'Express' ? 'selected' : ''; ?>>Express</option>
                                <option value="Economy" <?php echo ($edit_supplier['shipping_method'] ?? '') === 'Economy' ? 'selected' : ''; ?>>Economy</option>
                                <option value="Air Freight" <?php echo ($edit_supplier['shipping_method'] ?? '') === 'Air Freight' ? 'selected' : ''; ?>>Fret aérien</option>
                                <option value="Sea Freight" <?php echo ($edit_supplier['shipping_method'] ?? '') === 'Sea Freight' ? 'selected' : ''; ?>>Fret maritime</option>
                            </select>
                        </div>
                        
                        <!-- Délai de livraison moyen -->
                        <div>
                            <label for="average_delivery_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Délai de livraison moyen (jours)
                            </label>
                            <input type="number" id="average_delivery_time" name="average_delivery_time" min="0" 
                                   value="<?php echo $edit_supplier['average_delivery_time'] ?? ''; ?>"
                                   class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white px-3 py-2">
                        </div>
                        
                        <!-- Statut -->
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Statut
                            </label>
                            <select id="status" name="status" 
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white px-3 py-2">
                                <option value="active" <?php echo ($edit_supplier['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Actif</option>
                                <option value="inactive" <?php echo ($edit_supplier['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactif</option>
                            </select>
                        </div>
                        
                        <!-- Notes -->
                        <div>
                            <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Notes
                            </label>
                            <textarea id="notes" name="notes" rows="3" 
                                      class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white px-3 py-2"><?php echo htmlspecialchars($edit_supplier['notes'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end space-x-3">
                        <?php if ($edit_supplier): ?>
                        <a href="suppliers.php" class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 px-4 py-2 rounded-md transition-colors">
                            Annuler
                        </a>
                        <?php endif; ?>
                        <button type="submit" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded-md transition-colors">
                            <i class="fas fa-save mr-2"></i>
                            <?php echo $edit_supplier ? 'Modifier' : 'Ajouter'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Liste des fournisseurs -->
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                <!-- Top Controls -->
                <div class="flex flex-col md:flex-row md:justify-between md:items-center mb-6">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 mb-4 md:mb-0">
                        <h2 class="text-xl font-bold text-gray-800 dark:text-white">Liste des fournisseurs</h2>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            Total: <?php echo $total_suppliers; ?> fournisseur(s)
                        </div>
                    </div>
                </div>
                
                <!-- Search and Filter -->
                <div class="mb-6 bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                    <form action="suppliers.php" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- Search Field -->
                        <div class="sm:col-span-2">
                            <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Recherche</label>
                            <div class="relative">
                                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Nom, contact, email..." class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-search text-gray-400"></i>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Country Filter -->
                        <div>
                            <label for="country" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Pays</label>
                            <select id="country" name="country" class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                                <option value="">Tous les pays</option>
                                <?php foreach ($countries as $country): ?>
                                <option value="<?php echo htmlspecialchars($country); ?>" <?php echo $country_filter === $country ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($country); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Status Filter -->
                        <div>
                            <label for="status_filter" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Statut</label>
                            <select id="status_filter" name="status" class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                                <option value="">Tous les statuts</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Actif</option>
                                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactif</option>
                            </select>
                        </div>
                        
                        <!-- Form Buttons -->
                        <div class="sm:col-span-2 lg:col-span-4 flex flex-col sm:flex-row gap-2 sm:gap-4 sm:justify-end">
                            <button type="submit" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded-md transition-colors">
                                <i class="fas fa-filter mr-2"></i>Appliquer les filtres
                            </button>
                            <a href="suppliers.php" class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 text-center px-4 py-2 rounded-md transition-colors">
                                <i class="fas fa-undo mr-2"></i>Réinitialiser
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- Suppliers Table -->
                <?php if (!empty($suppliers)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Fournisseur
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Contact
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Pays
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Livraison
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Produits
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Statut
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach ($suppliers as $supplier): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-netblue-100 dark:bg-netblue-900 flex items-center justify-center">
                                                <i class="fas fa-truck text-netblue-600 dark:text-netblue-400"></i>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($supplier['name']); ?>
                                            </div>
                                            <?php if (!empty($supplier['website'])): ?>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                <a href="<?php echo htmlspecialchars($supplier['website']); ?>" target="_blank" class="hover:text-netblue-600 dark:hover:text-netblue-400">
                                                    <i class="fas fa-external-link-alt mr-1"></i>Site web
                                                </a>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if (!empty($supplier['contact_person'])): ?>
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($supplier['contact_person']); ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($supplier['email'])): ?>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        <a href="mailto:<?php echo htmlspecialchars($supplier['email']); ?>" class="hover:text-netblue-600 dark:hover:text-netblue-400">
                                            <?php echo htmlspecialchars($supplier['email']); ?>
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($supplier['phone'])): ?>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        <a href="tel:<?php echo htmlspecialchars($supplier['phone']); ?>" class="hover:text-netblue-600 dark:hover:text-netblue-400">
                                            <?php echo htmlspecialchars($supplier['phone']); ?>
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($supplier['country'] ?? 'Non spécifié'); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if (!empty($supplier['shipping_method'])): ?>
                                    <div class="text-sm text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($supplier['shipping_method']); ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($supplier['average_delivery_time'] > 0): ?>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo $supplier['average_delivery_time']; ?> jour(s)
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <span class="text-sm font-medium text-gray-900 dark:text-white mr-2">
                                            <?php echo $supplier['products_count']; ?>
                                        </span>
                                        <?php if ($supplier['products_count'] > 0): ?>
                                        <a href="products.php?supplier_id=<?php echo $supplier['id']; ?>" class="text-netblue-600 hover:text-netblue-900 dark:text-netblue-400 dark:hover:text-netblue-200 text-xs">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="inline-block">
                                        <form method="POST" action="suppliers.php" class="status-form">
                                            <input type="hidden" name="action" value="change_status">
                                            <input type="hidden" name="supplier_id" value="<?php echo $supplier['id']; ?>">
                                            
                                            <select name="status" class="status-select border border-gray-300 dark:border-gray-600 rounded-md text-xs shadow-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white py-1 pl-2 pr-7">
                                                <option value="active" <?php echo $supplier['status'] === 'active' ? 'selected' : ''; ?>>Actif</option>
                                                <option value="inactive" <?php echo $supplier['status'] === 'inactive' ? 'selected' : ''; ?>>Inactif</option>
                                            </select>
                                            <button type="submit" class="status-save hidden ml-2 bg-green-500 hover:bg-green-600 text-white text-xs py-1 px-2 rounded transition-colors">
                                                <i class="fas fa-check"></i> OK
                                            </button>
                                        </form>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <a href="suppliers.php?edit_id=<?php echo $supplier['id']; ?>" class="text-netblue-600 hover:text-netblue-900 dark:text-netblue-400 dark:hover:text-netblue-200" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button onclick="viewSupplierDetails(<?php echo $supplier['id']; ?>)" class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-200" title="Voir les détails">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <form method="POST" action="suppliers.php" class="inline-block delete-form">
                                            <input type="hidden" name="action" value="delete_supplier">
                                            <input type="hidden" name="supplier_id" value="<?php echo $supplier['id']; ?>">
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
                            Affichage de <span class="font-medium"><?php echo $offset + 1; ?></span> à <span class="font-medium"><?php echo min($offset + $suppliers_per_page, $total_suppliers); ?></span> sur <span class="font-medium"><?php echo $total_suppliers; ?></span> fournisseurs
                        </span>
                    </div>
                    
                    <nav class="inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                        <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&country=<?php echo urlencode($country_filter); ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search_term); ?>&sort_by=<?php echo $sort_by; ?>&sort_order=<?php echo $sort_order; ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
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
                            echo '<a href="?page=1&country=' . urlencode($country_filter) . '&status=' . urlencode($status_filter) . '&search=' . urlencode($search_term) . '&sort_by=' . $sort_by . '&sort_order=' . $sort_order . '" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">1</a>';
                            if ($start_page > 2) {
                                echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300">...</span>';
                            }
                        }
                        
                        for ($i = $start_page; $i <= $end_page; $i++) {
                            if ($i == $page) {
                                echo '<span aria-current="page" class="relative inline-flex items-center px-4 py-2 border border-netblue-500 bg-netblue-50 dark:bg-netblue-900 text-sm font-medium text-netblue-600 dark:text-netblue-300">' . $i . '</span>';
                            } else {
                                echo '<a href="?page=' . $i . '&country=' . urlencode($country_filter) . '&status=' . urlencode($status_filter) . '&search=' . urlencode($search_term) . '&sort_by=' . $sort_by . '&sort_order=' . $sort_order . '" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">' . $i . '</a>';
                            }
                        }
                        
                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300">...</span>';
                            }
                            echo '<a href="?page=' . $total_pages . '&country=' . urlencode($country_filter) . '&status=' . urlencode($status_filter) . '&search=' . urlencode($search_term) . '&sort_by=' . $sort_by . '&sort_order=' . $sort_order . '" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">' . $total_pages . '</a>';
                        }
                        ?>
                        
                        <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&country=<?php echo urlencode($country_filter); ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search_term); ?>&sort_by=<?php echo $sort_by; ?>&sort_order=<?php echo $sort_order; ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
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
                        <i class="fas fa-truck text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Aucun fournisseur trouvé</h3>
                    <p class="text-gray-500 dark:text-gray-400 max-w-md mx-auto mb-6">
                        <?php if (!empty($search_term) || !empty($country_filter) || !empty($status_filter)): ?>
                            Aucun fournisseur ne correspond à vos critères de recherche. Essayez de modifier vos filtres.
                        <?php else: ?>
                            Vous n'avez pas encore ajouté de fournisseurs. Commencez par ajouter votre premier fournisseur.
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($search_term) || !empty($country_filter) || !empty($status_filter)): ?>
                    <a href="suppliers.php" class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 px-4 py-2 rounded-md transition-colors">
                        <i class="fas fa-undo mr-2"></i>Réinitialiser les filtres
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
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
            <p class="text-gray-700 dark:text-gray-300">Êtes-vous sûr de vouloir supprimer ce fournisseur ? Cette action est irréversible.</p>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                <strong>Note :</strong> Si des produits sont liés à ce fournisseur, la suppression sera bloquée.
            </p>
        </div>
        <div class="flex justify-end space-x-3">
            <button id="cancel-delete" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                Annuler
            </button>
            <button id="confirm-delete" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                Supprimer
            </button>
        </div>
    </div>
</div>

<!-- Supplier Details Modal -->
<div id="supplier-details-modal" class="fixed inset-0 z-50 overflow-auto bg-black bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full p-6 transform transition-all">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Détails du fournisseur</h3>
            <button id="close-details-modal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="supplier-details-content">
            <!-- Content will be loaded here -->
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
    
    // Status change handling
    const statusSelects = document.querySelectorAll('.status-select');
    
    statusSelects.forEach(select => {
        const initialValue = select.value;
        const saveButton = select.nextElementSibling;
        
        select.addEventListener('change', function() {
            if (this.value !== initialValue) {
                saveButton.classList.remove('hidden');
            } else {
                saveButton.classList.add('hidden');
            }
        });
    });
    
    // Details modal
    const detailsModal = document.getElementById('supplier-details-modal');
    const closeDetailsModalButton = document.getElementById('close-details-modal');
    
    window.viewSupplierDetails = function(supplierId) {
        // Here you would fetch supplier details via AJAX
        // For now, we'll just show a placeholder
        const content = document.getElementById('supplier-details-content');
        content.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin text-2xl text-gray-400"></i><p class="mt-2 text-gray-600 dark:text-gray-400">Chargement des détails...</p></div>';
        detailsModal.classList.remove('hidden');
        
        // Simulate loading (replace with actual AJAX call)
        setTimeout(() => {
            content.innerHTML = `
                <div class="space-y-4">
                    <p class="text-gray-600 dark:text-gray-400">Les détails complets du fournisseur seraient affichés ici.</p>
                    <p class="text-sm text-gray-500 dark:text-gray-500">Cette fonctionnalité nécessite une implémentation AJAX supplémentaire.</p>
                </div>
            `;
        }, 1000);
    };
    
    const closeDetailsModal = function() {
        detailsModal.classList.add('hidden');
    };
    
    closeDetailsModalButton.addEventListener('click', closeDetailsModal);
    
    // Close modals when clicking outside
    deleteModal.addEventListener('click', function(e) {
        if (e.target === this) {
            closeDeleteModal();
        }
    });
    
    detailsModal.addEventListener('click', function(e) {
        if (e.target === this) {
            closeDetailsModal();
        }
    });
    
    // Close modals with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (!deleteModal.classList.contains('hidden')) {
                closeDeleteModal();
            }
            if (!detailsModal.classList.contains('hidden')) {
                closeDetailsModal();
            }
        }
    });
});
</script>

<?php
// Inclusion du pied de page
require_once 'includes/footer.php';
?>