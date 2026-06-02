<?php
// Titre de la page pour l'inclusion de l'en-tête
$page_title = "Gestion des produits";

// Inclusion de l'en-tête
require_once 'includes/header.php';

// Traitement de la suppression d'un produit
if (isset($_POST['action']) && $_POST['action'] === 'delete_product') {
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    
    if ($product_id > 0) {
        // Vérifier si le produit existe
        $check_query = "SELECT id FROM products WHERE id = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $check_result = $stmt->get_result();
        
        if ($check_result && $check_result->num_rows > 0) {
            // Supprimer d'abord les images du produit
            $delete_images_query = "DELETE FROM product_images WHERE product_id = ?";
            $stmt = $conn->prepare($delete_images_query);
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            
            // Supprimer le produit
            $delete_query = "DELETE FROM products WHERE id = ?";
            $stmt = $conn->prepare($delete_query);
            $stmt->bind_param("i", $product_id);
            $result = $stmt->execute();
            
            if ($result) {
                header("Location: products.php?success=" . urlencode("Le produit a été supprimé avec succès."));
                exit;
            } else {
                header("Location: products.php?error=" . urlencode("Une erreur est survenue lors de la suppression du produit."));
                exit;
            }
        } else {
            header("Location: products.php?error=" . urlencode("Produit introuvable."));
            exit;
        }
    }
}

// Traitement du changement de statut d'un produit
if (isset($_POST['action']) && $_POST['action'] === 'change_status') {
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $new_status = isset($_POST['status']) ? $_POST['status'] : '';
    
    if ($product_id > 0 && in_array($new_status, ['active', 'inactive', 'out_of_stock'])) {
        $update_query = "UPDATE products SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("si", $new_status, $product_id);
        $result = $stmt->execute();
        
        if ($result) {
            header("Location: products.php?success=" . urlencode("Le statut du produit a été mis à jour avec succès."));
            exit;
        } else {
            header("Location: products.php?error=" . urlencode("Une erreur est survenue lors de la mise à jour du statut."));
            exit;
        }
    }
}

// Paramètres de filtrage et de tri
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'id';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'desc';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$products_per_page = 10;

// Construction de la requête SQL de base
$sql_count = "SELECT COUNT(*) as total FROM products p";
$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id";

// Construction des conditions WHERE
$where_conditions = [];
$params = [];
$types = "";

// Filtre par catégorie
if ($category_filter > 0) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category_filter;
    $types .= "i";
}

// Filtre par statut
if (!empty($status_filter) && in_array($status_filter, ['active', 'inactive', 'out_of_stock'])) {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Recherche par terme
if (!empty($search_term)) {
    $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ? OR p.sku LIKE ?)";
    $search_param = "%" . $search_term . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

// Ajout des conditions WHERE à la requête
if (!empty($where_conditions)) {
    $sql_count .= " WHERE " . implode(" AND ", $where_conditions);
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

// Ajout du tri
$valid_sort_columns = ['id', 'name', 'price', 'stock', 'created_at', 'views'];
$valid_sort_orders = ['asc', 'desc'];

if (!in_array($sort_by, $valid_sort_columns)) {
    $sort_by = 'id';
}
if (!in_array($sort_order, $valid_sort_orders)) {
    $sort_order = 'desc';
}

$sql .= " ORDER BY p.$sort_by $sort_order";

// Récupération du nombre total de produits pour la pagination
$stmt_count = $conn->prepare($sql_count);
if (!empty($types)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$count_result = $stmt_count->get_result();
$row_count = $count_result->fetch_assoc();
$total_products = $row_count['total'];
$total_pages = ceil($total_products / $products_per_page);

// Ajustement de la page courante
if ($page < 1) $page = 1;
if ($page > $total_pages && $total_pages > 0) $page = $total_pages;

// Ajout de la pagination à la requête
$offset = ($page - 1) * $products_per_page;
$sql .= " LIMIT ?, ?";
$params[] = $offset;
$params[] = $products_per_page;
$types .= "ii";

// Exécution de la requête paginée
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$products = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Récupération de l'image principale de chaque produit
        $img_query = "SELECT image_url FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, display_order ASC LIMIT 1";
        $img_stmt = $conn->prepare($img_query);
        $img_stmt->bind_param("i", $row['id']);
        $img_stmt->execute();
        $img_result = $img_stmt->get_result();
        
        $row['image'] = '';
        if ($img_result && $img_result->num_rows > 0) {
            $img_row = $img_result->fetch_assoc();
            $row['image'] = $img_row['image_url'];
        }
        
        $products[] = $row;
    }
}

// Récupération des catégories pour le filtre
$categories_query = "SELECT id, name FROM categories ORDER BY name";
$categories_result = $conn->query($categories_query);
$categories = [];
if ($categories_result && $categories_result->num_rows > 0) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
}
?>

<!-- Products Management Content -->
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
    <!-- Top Controls -->
    <div class="flex flex-col md:flex-row md:justify-between md:items-center mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 mb-4 md:mb-0">
            <h2 class="text-xl font-bold text-gray-800 dark:text-white">Liste des produits</h2>
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Total: <?php echo $total_products; ?> produit(s)
            </div>
        </div>
        
        <div class="flex flex-col sm:flex-row gap-2 sm:gap-4">
            <a href="add_product.php" class="bg-netblue-600 hover:bg-netblue-700 text-white text-center px-4 py-2 rounded-md transition-colors">
                <i class="fas fa-plus mr-2"></i>Ajouter un produit
            </a>
            <a href="categories.php" class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 text-center px-4 py-2 rounded-md transition-colors">
                <i class="fas fa-tags mr-2"></i>Gérer les catégories
            </a>
        </div>
    </div>
    
    <!-- Search and Filter -->
    <div class="mb-6 bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
        <form action="products.php" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <!-- Search Field -->
            <div class="sm:col-span-2">
                <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Recherche</label>
                <div class="relative">
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Nom, description, SKU..." class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400"></i>
                    </div>
                </div>
            </div>
            
            <!-- Category Filter -->
            <div>
                <label for="category" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Catégorie</label>
                <select id="category" name="category" class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                    <option value="0">Toutes les catégories</option>
                    <?php foreach ($categories as $category): ?>
                    <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($category['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Status Filter -->
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Statut</label>
                <select id="status" name="status" class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                    <option value="">Tous les statuts</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Actif</option>
                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactif</option>
                    <option value="out_of_stock" <?php echo $status_filter === 'out_of_stock' ? 'selected' : ''; ?>>Rupture de stock</option>
                </select>
            </div>
            
            <!-- Sorting -->
            <div>
                <label for="sort_by" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Trier par</label>
                <div class="flex space-x-2">
                    <select id="sort_by" name="sort_by" class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                        <option value="id" <?php echo $sort_by === 'id' ? 'selected' : ''; ?>>ID</option>
                        <option value="name" <?php echo $sort_by === 'name' ? 'selected' : ''; ?>>Nom</option>
                        <option value="price" <?php echo $sort_by === 'price' ? 'selected' : ''; ?>>Prix</option>
                        <option value="stock" <?php echo $sort_by === 'stock' ? 'selected' : ''; ?>>Stock</option>
                        <option value="views" <?php echo $sort_by === 'views' ? 'selected' : ''; ?>>Vues</option>
                        <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Date de création</option>
                    </select>
                    
                    <select id="sort_order" name="sort_order" class="w-20 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                        <option value="asc" <?php echo $sort_order === 'asc' ? 'selected' : ''; ?>>↑</option>
                        <option value="desc" <?php echo $sort_order === 'desc' ? 'selected' : ''; ?>>↓</option>
                    </select>
                </div>
            </div>
            
            <!-- Form Buttons -->
            <div class="sm:col-span-2 lg:col-span-5 flex flex-col sm:flex-row gap-2 sm:gap-4 sm:justify-end">
                <button type="submit" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded-md transition-colors">
                    <i class="fas fa-filter mr-2"></i>Appliquer les filtres
                </button>
                <a href="products.php" class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 text-center px-4 py-2 rounded-md transition-colors">
                    <i class="fas fa-undo mr-2"></i>Réinitialiser
                </a>
            </div>
        </form>
    </div>
    
    <!-- Products Table -->
    <?php if (!empty($products)): ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        Image
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        Nom
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        Prix
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        Catégorie
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        Stock
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
                <?php foreach ($products as $product): ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="h-12 w-12 overflow-hidden rounded-md bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                            <?php if (!empty($product['image'])): ?>
                            <img src="../<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="h-full w-full object-cover">
                            <?php else: ?>
                            <i class="fas fa-box text-gray-400 text-xl"></i>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                            <?php echo htmlspecialchars($product['name']); ?>
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            SKU: <?php echo htmlspecialchars($product['sku'] ?? 'N/A'); ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900 dark:text-white">
                            <?php echo number_format($product['price'], 2); ?> FCFA
                        </div>
                        <?php if ($product['sale_price'] && $product['sale_price'] < $product['price']): ?>
                        <div class="text-xs text-red-600 dark:text-red-400">
                            Promo: <?php echo number_format($product['sale_price'], 2); ?> FCFA
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="text-sm text-gray-900 dark:text-white">
                            <?php echo htmlspecialchars($product['category_name'] ?? 'Non catégorisé'); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                            <?php if ($product['stock'] > 10): ?>
                                bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                            <?php elseif ($product['stock'] > 0): ?>
                                bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                            <?php else: ?>
                                bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                            <?php endif; ?>
                        ">
                            <?php echo $product['stock']; ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="inline-block">
                            <form method="POST" action="products.php" class="status-form">
                                <input type="hidden" name="action" value="change_status">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                
                                <select name="status" class="status-select border border-gray-300 dark:border-gray-600 rounded-md text-xs shadow-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white py-1 pl-2 pr-7">
                                    <option value="active" <?php echo $product['status'] === 'active' ? 'selected' : ''; ?>>Actif</option>
                                    <option value="inactive" <?php echo $product['status'] === 'inactive' ? 'selected' : ''; ?>>Inactif</option>
                                    <option value="out_of_stock" <?php echo $product['status'] === 'out_of_stock' ? 'selected' : ''; ?>>Rupture</option>
                                </select>
                                <button type="submit" class="status-save hidden ml-2 bg-green-500 hover:bg-green-600 text-white text-xs py-1 px-2 rounded transition-colors">
                                    <i class="fas fa-check"></i> OK
                                </button>
                            </form>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <div class="flex space-x-2">
                            <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="text-netblue-600 hover:text-netblue-900 dark:text-netblue-400 dark:hover:text-netblue-200" title="Modifier">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="../product.php?id=<?php echo $product['id']; ?>" target="_blank" class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-200" title="Voir sur le site">
                                <i class="fas fa-eye"></i>
                            </a>
                            <form method="POST" action="products.php" class="inline-block delete-form">
                                <input type="hidden" name="action" value="delete_product">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <button type="button" class="delete-button text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-200 ml-3" title="Supprimer">
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
                Affichage de <span class="font-medium"><?php echo $offset + 1; ?></span> à <span class="font-medium"><?php echo min($offset + $products_per_page, $total_products); ?></span> sur <span class="font-medium"><?php echo $total_products; ?></span> produits
            </span>
        </div>
        
        <nav class="inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
            <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>&category=<?php echo $category_filter; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search_term); ?>&sort_by=<?php echo $sort_by; ?>&sort_order=<?php echo $sort_order; ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
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
                echo '<a href="?page=1&category=' . $category_filter . '&status=' . urlencode($status_filter) . '&search=' . urlencode($search_term) . '&sort_by=' . $sort_by . '&sort_order=' . $sort_order . '" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">1</a>';
                if ($start_page > 2) {
                    echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300">...</span>';
                }
            }
            
            for ($i = $start_page; $i <= $end_page; $i++) {
                if ($i == $page) {
                    echo '<span aria-current="page" class="relative inline-flex items-center px-4 py-2 border border-netblue-500 bg-netblue-50 dark:bg-netblue-900 text-sm font-medium text-netblue-600 dark:text-netblue-300">' . $i . '</span>';
                } else {
                    echo '<a href="?page=' . $i . '&category=' . $category_filter . '&status=' . urlencode($status_filter) . '&search=' . urlencode($search_term) . '&sort_by=' . $sort_by . '&sort_order=' . $sort_order . '" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">' . $i . '</a>';
                }
            }
            
            if ($end_page < $total_pages) {
                if ($end_page < $total_pages - 1) {
                    echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300">...</span>';
                }
                echo '<a href="?page=' . $total_pages . '&category=' . $category_filter . '&status=' . urlencode($status_filter) . '&search=' . urlencode($search_term) . '&sort_by=' . $sort_by . '&sort_order=' . $sort_order . '" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">' . $total_pages . '</a>';
            }
            ?>
            
            <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page + 1; ?>&category=<?php echo $category_filter; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search_term); ?>&sort_by=<?php echo $sort_by; ?>&sort_order=<?php echo $sort_order; ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
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
            <i class="fas fa-box-open text-2xl"></i>
        </div>
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Aucun produit trouvé</h3>
        <p class="text-gray-500 dark:text-gray-400 max-w-md mx-auto mb-6">
            <?php if (!empty($search_term) || $category_filter > 0 || !empty($status_filter)): ?>
                Aucun produit ne correspond à vos critères de recherche. Essayez de modifier vos filtres.
            <?php else: ?>
                Vous n'avez pas encore ajouté de produits. Commencez par ajouter votre premier produit.
            <?php endif; ?>
        </p>
        <div class="flex justify-center space-x-4">
            <a href="add_product.php" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded-md transition-colors">
                <i class="fas fa-plus mr-2"></i>Ajouter un produit
            </a>
            <?php if (!empty($search_term) || $category_filter > 0 || !empty($status_filter)): ?>
            <a href="products.php" class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 px-4 py-2 rounded-md transition-colors">
                <i class="fas fa-undo mr-2"></i>Réinitialiser les filtres
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
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
            <p class="text-gray-700 dark:text-gray-300">Êtes-vous sûr de vouloir supprimer ce produit ? Cette action est irréversible.</p>
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
});
</script>

<?php
// Inclusion du pied de page
require_once 'includes/footer.php';
?>