<?php
// Titre de la page pour l'inclusion de l'en-tête
$page_title = "Modifier un produit";

// Inclusion de l'en-tête
require_once 'includes/header.php';

// Vérifier si un ID de produit est fourni
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($product_id <= 0) {
    header("Location: products.php?error=" . urlencode("ID de produit invalide."));
    exit;
}

// Récupération des informations du produit
$product_query = "SELECT * FROM products WHERE id = ?";
$stmt = $conn->prepare($product_query);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product_result = $stmt->get_result();

if (!$product_result || $product_result->num_rows === 0) {
    header("Location: products.php?error=" . urlencode("Produit introuvable."));
    exit;
}

$product = $product_result->fetch_assoc();

// Récupération des images du produit
$images_query = "SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, display_order ASC";
$stmt = $conn->prepare($images_query);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$images_result = $stmt->get_result();
$product_images = [];

if ($images_result && $images_result->num_rows > 0) {
    while ($row = $images_result->fetch_assoc()) {
        $product_images[] = $row;
    }
}

// Traitement du formulaire de modification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_product') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $short_description = trim($_POST['short_description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $sale_price = !empty($_POST['sale_price']) ? floatval($_POST['sale_price']) : null;
    $sku = trim($_POST['sku'] ?? '');
    $stock = intval($_POST['stock'] ?? 0);
    $category_id = intval($_POST['category_id'] ?? 0);
    $status = $_POST['status'] ?? 'active';
    $featured = isset($_POST['featured']) ? 1 : 0;
    $meta_title = trim($_POST['meta_title'] ?? '');
    $meta_description = trim($_POST['meta_description'] ?? '');
    $weight = !empty($_POST['weight']) ? floatval($_POST['weight']) : null;
    $dimensions = trim($_POST['dimensions'] ?? '');
    
    $errors = [];
    
    // Validation
    if (empty($name)) {
        $errors[] = "Le nom du produit est requis.";
    }
    
    if ($price <= 0) {
        $errors[] = "Le prix doit être supérieur à 0.";
    }
    
    if ($sale_price !== null && $sale_price >= $price) {
        $errors[] = "Le prix promotionnel doit être inférieur au prix normal.";
    }
    
    if ($stock < 0) {
        $errors[] = "Le stock ne peut pas être négatif.";
    }
    
    if (!in_array($status, ['active', 'inactive', 'out_of_stock'])) {
        $errors[] = "Statut invalide.";
    }
    
    // Vérifier l'unicité du SKU (sauf pour ce produit)
    if (!empty($sku)) {
        $sku_check_query = "SELECT id FROM products WHERE sku = ? AND id != ?";
        $stmt = $conn->prepare($sku_check_query);
        $stmt->bind_param("si", $sku, $product_id);
        $stmt->execute();
        $sku_result = $stmt->get_result();
        
        if ($sku_result && $sku_result->num_rows > 0) {
            $errors[] = "Ce SKU est déjà utilisé par un autre produit.";
        }
    }
    
    if (empty($errors)) {
        // Mise à jour du produit
        $update_query = "UPDATE products SET 
                        name = ?, 
                        description = ?, 
                        short_description = ?, 
                        price = ?, 
                        sale_price = ?, 
                        sku = ?, 
                        stock = ?, 
                        category_id = ?, 
                        status = ?, 
                        featured = ?, 
                        meta_title = ?, 
                        meta_description = ?, 
                        weight = ?, 
                        dimensions = ?, 
                        updated_at = NOW() 
                        WHERE id = ?";
        
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("sssdsssissssdi", 
            $name, $description, $short_description, $price, $sale_price, 
            $sku, $stock, $category_id, $status, $featured, 
            $meta_title, $meta_description, $weight, $dimensions, $product_id
        );
        
        if ($stmt->execute()) {
            header("Location: edit_product.php?id=" . $product_id . "&success=" . urlencode("Produit mis à jour avec succès."));
            exit;
        } else {
            $errors[] = "Une erreur est survenue lors de la mise à jour du produit.";
        }
    }
}

// Traitement de l'upload d'images
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_images') {
    $upload_dir = '../uploads/products/';
    
    // Créer le dossier s'il n'existe pas
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $uploaded_files = [];
    $errors = [];
    
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        $file_count = count($_FILES['images']['name']);
        
        for ($i = 0; $i < $file_count; $i++) {
            $file_name = $_FILES['images']['name'][$i];
            $file_tmp = $_FILES['images']['tmp_name'][$i];
            $file_size = $_FILES['images']['size'][$i];
            $file_error = $_FILES['images']['error'][$i];
            
            if ($file_error === UPLOAD_ERR_OK) {
                // Vérifier le type de fichier
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                $file_type = mime_content_type($file_tmp);
                
                if (in_array($file_type, $allowed_types)) {
                    // Vérifier la taille (max 5MB)
                    if ($file_size <= 5 * 1024 * 1024) {
                        // Générer un nom unique
                        $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                        $unique_name = uniqid('product_' . $product_id . '_') . '.' . $file_extension;
                        $file_path = $upload_dir . $unique_name;
                        
                        if (move_uploaded_file($file_tmp, $file_path)) {
                            $uploaded_files[] = 'uploads/products/' . $unique_name;
                        } else {
                            $errors[] = "Erreur lors de l'upload de $file_name.";
                        }
                    } else {
                        $errors[] = "Le fichier $file_name est trop volumineux (max 5MB).";
                    }
                } else {
                    $errors[] = "Type de fichier non autorisé pour $file_name.";
                }
            }
        }
        
        // Insérer les images dans la base de données
        if (!empty($uploaded_files)) {
            foreach ($uploaded_files as $file_path) {
                $insert_image_query = "INSERT INTO product_images (product_id, image_url, is_primary, display_order) VALUES (?, ?, 0, 0)";
                $stmt = $conn->prepare($insert_image_query);
                $stmt->bind_param("is", $product_id, $file_path);
                $stmt->execute();
            }
            
            header("Location: edit_product.php?id=" . $product_id . "&success=" . urlencode(count($uploaded_files) . " image(s) ajoutée(s) avec succès."));
            exit;
        }
    }
}

// Traitement de la suppression d'image
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_image') {
    $image_id = intval($_POST['image_id'] ?? 0);
    
    if ($image_id > 0) {
        // Récupérer le chemin de l'image
        $image_query = "SELECT image_url FROM product_images WHERE id = ? AND product_id = ?";
        $stmt = $conn->prepare($image_query);
        $stmt->bind_param("ii", $image_id, $product_id);
        $stmt->execute();
        $image_result = $stmt->get_result();
        
        if ($image_result && $image_result->num_rows > 0) {
            $image_data = $image_result->fetch_assoc();
            $image_path = '../' . $image_data['image_url'];
            
            // Supprimer l'image de la base de données
            $delete_query = "DELETE FROM product_images WHERE id = ? AND product_id = ?";
            $stmt = $conn->prepare($delete_query);
            $stmt->bind_param("ii", $image_id, $product_id);
            
            if ($stmt->execute()) {
                // Supprimer le fichier physique
                if (file_exists($image_path)) {
                    unlink($image_path);
                }
                
                header("Location: edit_product.php?id=" . $product_id . "&success=" . urlencode("Image supprimée avec succès."));
                exit;
            }
        }
    }
}

// Traitement de la définition d'image principale
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_primary') {
    $image_id = intval($_POST['image_id'] ?? 0);
    
    if ($image_id > 0) {
        // Réinitialiser toutes les images comme non principales
        $reset_query = "UPDATE product_images SET is_primary = 0 WHERE product_id = ?";
        $stmt = $conn->prepare($reset_query);
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        
        // Définir l'image sélectionnée comme principale
        $set_primary_query = "UPDATE product_images SET is_primary = 1 WHERE id = ? AND product_id = ?";
        $stmt = $conn->prepare($set_primary_query);
        $stmt->bind_param("ii", $image_id, $product_id);
        
        if ($stmt->execute()) {
            header("Location: edit_product.php?id=" . $product_id . "&success=" . urlencode("Image principale définie avec succès."));
            exit;
        }
    }
}

// Récupération des catégories
$categories_query = "SELECT id, name FROM categories ORDER BY name";
$categories_result = $conn->query($categories_query);
$categories = [];
if ($categories_result && $categories_result->num_rows > 0) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Recharger les images après les opérations
$images_query = "SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, display_order ASC";
$stmt = $conn->prepare($images_query);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$images_result = $stmt->get_result();
$product_images = [];

if ($images_result && $images_result->num_rows > 0) {
    while ($row = $images_result->fetch_assoc()) {
        $product_images[] = $row;
    }
}
?>

<!-- Edit Product Content -->
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:justify-between md:items-center mb-6">
        <div>
            <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-2">Modifier le produit</h2>
            <nav class="text-sm text-gray-500 dark:text-gray-400">
                <a href="products.php" class="hover:text-netblue-600 dark:hover:text-netblue-400">Produits</a>
                <span class="mx-2">/</span>
                <span>Modifier</span>
            </nav>
        </div>
        
        <div class="flex flex-col sm:flex-row gap-2 sm:gap-4 mt-4 md:mt-0">
            <a href="products.php" class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 text-center px-4 py-2 rounded-md transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>Retour à la liste
            </a>
            <a href="../product.php?id=<?php echo $product_id; ?>" target="_blank" class="bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 hover:bg-green-200 dark:hover:bg-green-800 text-center px-4 py-2 rounded-md transition-colors">
                <i class="fas fa-eye mr-2"></i>Voir sur le site
            </a>
        </div>
    </div>

    <!-- Messages de succès/erreur -->
    <?php if (isset($_GET['success'])): ?>
    <div class="mb-6 bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-300 px-4 py-3 rounded">
        <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($_GET['success']); ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <div class="mb-6 bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-300 px-4 py-3 rounded">
        <ul class="list-disc list-inside">
            <?php foreach ($errors as $error): ?>
            <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Formulaire principal -->
        <div class="lg:col-span-2">
            <form method="POST" action="edit_product.php?id=<?php echo $product_id; ?>" class="space-y-6">
                <input type="hidden" name="action" value="update_product">
                
                <!-- Informations de base -->
                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Informations de base</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Nom du produit <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2">
                        </div>
                        
                        <div>
                            <label for="price" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Prix (FCFA) <span class="text-red-500">*</span>
                            </label>
                            <input type="number" id="price" name="price" value="<?php echo $product['price']; ?>" step="0.01" min="0" required class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2">
                        </div>
                        
                        <div>
                            <label for="sale_price" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Prix promotionnel (FCFA)
                            </label>
                            <input type="number" id="sale_price" name="sale_price" value="<?php echo $product['sale_price']; ?>" step="0.01" min="0" class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2">
                        </div>
                        
                        <div>
                            <label for="sku" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                SKU
                            </label>
                            <input type="text" id="sku" name="sku" value="<?php echo htmlspecialchars($product['sku']); ?>" class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2">
                        </div>
                        
                        <div>
                            <label for="stock" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Stock
                            </label>
                            <input type="number" id="stock" name="stock" value="<?php echo $product['stock']; ?>" min="0" class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2">
                        </div>
                    </div>
                </div>
                
                <!-- Description -->
                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Description</h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label for="short_description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Description courte
                            </label>
                            <textarea id="short_description" name="short_description" rows="3" class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2"><?php echo htmlspecialchars($product['short_description']); ?></textarea>
                        </div>
                        
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Description détaillée
                            </label>
                            <textarea id="description" name="description" rows="6" class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2"><?php echo htmlspecialchars($product['description']); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Paramètres avancés -->
                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Paramètres avancés</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="category_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Catégorie
                            </label>
                            <select id="category_id" name="category_id" class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2">
                                <option value="0">Aucune catégorie</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo $product['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Statut
                            </label>
                            <select id="status" name="status" class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2">
                                <option value="active" <?php echo $product['status'] === 'active' ? 'selected' : ''; ?>>Actif</option>
                                <option value="inactive" <?php echo $product['status'] === 'inactive' ? 'selected' : ''; ?>>Inactif</option>
                                <option value="out_of_stock" <?php echo $product['status'] === 'out_of_stock' ? 'selected' : ''; ?>>Rupture de stock</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="weight" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Poids (kg)
                            </label>
                            <input type="number" id="weight" name="weight" value="<?php echo $product['weight']; ?>" step="0.01" min="0" class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2">
                        </div>
                        
                        <div>
                            <label for="dimensions" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Dimensions
                            </label>
                            <input type="text" id="dimensions" name="dimensions" value="<?php echo htmlspecialchars($product['dimensions']); ?>" placeholder="L x l x h (cm)" class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2">
                        </div>
                        
                        <div class="md:col-span-2">
                            <div class="flex items-center">
                                <input type="checkbox" id="featured" name="featured" value="1" <?php echo $product['featured'] ? 'checked' : ''; ?> class="h-4 w-4 text-netblue-600 focus:ring-netblue-500 border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-800">
                                <label for="featured" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                    Produit en vedette
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- SEO -->
                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Référencement (SEO)</h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label for="meta_title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Titre Meta
                            </label>
                            <input type="text" id="meta_title" name="meta_title" value="<?php echo htmlspecialchars($product['meta_title']); ?>" class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2">
                        </div>
                        
                        <div>
                            <label for="meta_description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Description Meta
                            </label>
                            <textarea id="meta_description" name="meta_description" rows="3" class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2"><?php echo htmlspecialchars($product['meta_description']); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Boutons de soumission -->
                <div class="flex flex-col sm:flex-row gap-4 pt-6 border-t border-gray-200 dark:border-gray-600">
                    <button type="submit" class="bg-netblue-600 hover:bg-netblue-700 text-white px-6 py-2 rounded-md transition-colors">
                        <i class="fas fa-save mr-2"></i>Mettre à jour le produit
                    </button>
                    <a href="products.php" class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 text-center px-6 py-2 rounded-md transition-colors">
                        <i class="fas fa-times mr-2"></i>Annuler
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Gestion des images -->
        <div class="lg:col-span-1">
            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Images du produit</h3>
                
                <!-- Upload d'images -->
                <form method="POST" action="edit_product.php?id=<?php echo $product_id; ?>" enctype="multipart/form-data" class="mb-6">
                    <input type="hidden" name="action" value="upload_images">
                    
                    <div class="mb-4">
                        <label for="images" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Ajouter des images
                        </label>
                        <input type="file" id="images" name="images[]" multiple accept="image/*" class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Formats acceptés: JPG, PNG, GIF, WebP. Taille max: 5MB par image.
                        </p>
                    </div>
                    
                    <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md transition-colors">
                        <i class="fas fa-upload mr-2"></i>Ajouter les images
                    </button>
                </form>
                
                <!-- Liste des images existantes -->
                <?php if (!empty($product_images)): ?>
                <div class="space-y-4">
                    <h4 class="font-medium text-gray-800 dark:text-white">Images actuelles</h4>
                    
                    <?php foreach ($product_images as $image): ?>
                    <div class="bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm border border-gray-200 dark:border-gray-600">
                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0">
                                <img src="../<?php echo htmlspecialchars($image['image_url']); ?>" alt="Image produit" class="w-16 h-16 object-cover rounded-md">
                            </div>
                            
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center space-x-2 mb-2">
                                    <?php if ($image['is_primary']): ?>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                        <i class="fas fa-star mr-1"></i>Principale
                                    </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex flex-col space-y-2">
                                    <?php if (!$image['is_primary']): ?>
                                    <form method="POST" action="edit_product.php?id=<?php echo $product_id; ?>" class="inline-block">
                                        <input type="hidden" name="action" value="set_primary">
                                        <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                                        <button type="submit" class="text-xs bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 hover:bg-blue-200 dark:hover:bg-blue-800 px-2 py-1 rounded transition-colors">
                                            <i class="fas fa-star mr-1"></i>Définir comme principale
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <form method="POST" action="edit_product.php?id=<?php echo $product_id; ?>" class="inline-block delete-image-form">
                                        <input type="hidden" name="action" value="delete_image">
                                        <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                                        <button type="button" class="delete-image-button text-xs bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 hover:bg-red-200 dark:hover:bg-red-800 px-2 py-1 rounded transition-colors">
                                            <i class="fas fa-trash mr-1"></i>Supprimer
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-6">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-600 text-gray-400 dark:text-gray-300 mb-3">
                        <i class="fas fa-images text-xl"></i>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Aucune image ajoutée</p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Informations sur le produit -->
            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg mt-6">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Informations</h3>
                
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600 dark:text-gray-400">ID:</span>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">#<?php echo $product['id']; ?></span>
                    </div>
                    
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Vues:</span>
                        <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo number_format($product['views']); ?></span>
                    </div>
                    
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Créé le:</span>
                        <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo date('d/m/Y', strtotime($product['created_at'])); ?></span>
                    </div>
                    
                    <?php if ($product['updated_at']): ?>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Modifié le:</span>
                        <span class="text-sm font-medium text-gray-900 dark:text-white"><?php echo date('d/m/Y H:i', strtotime($product['updated_at'])); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="pt-3 border-t border-gray-200 dark:border-gray-600">
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Statut actuel:</span>
                            <span class="text-sm font-medium">
                                <?php if ($product['status'] === 'active'): ?>
                                    <span class="text-green-600 dark:text-green-400">Actif</span>
                                <?php elseif ($product['status'] === 'inactive'): ?>
                                    <span class="text-red-600 dark:text-red-400">Inactif</span>
                                <?php else: ?>
                                    <span class="text-yellow-600 dark:text-yellow-400">Rupture de stock</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Actions rapides -->
            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg mt-6">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Actions rapides</h3>
                
                <div class="space-y-3">
                    <a href="../product.php?id=<?php echo $product_id; ?>" target="_blank" class="w-full bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 hover:bg-green-200 dark:hover:bg-green-800 text-center px-4 py-2 rounded-md transition-colors block">
                        <i class="fas fa-eye mr-2"></i>Voir sur le site
                    </a>
                    
                    <a href="add_product.php" class="w-full bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 hover:bg-blue-200 dark:hover:bg-blue-800 text-center px-4 py-2 rounded-md transition-colors block">
                        <i class="fas fa-plus mr-2"></i>Ajouter un nouveau produit
                    </a>
                    
                    <button type="button" id="duplicate-product" class="w-full bg-yellow-100 dark:bg-yellow-900 text-yellow-700 dark:text-yellow-300 hover:bg-yellow-200 dark:hover:bg-yellow-800 px-4 py-2 rounded-md transition-colors">
                        <i class="fas fa-copy mr-2"></i>Dupliquer ce produit
                    </button>
                    
                    <form method="POST" action="products.php" class="delete-product-form">
                        <input type="hidden" name="action" value="delete_product">
                        <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                        <button type="button" class="delete-product-button w-full bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 hover:bg-red-200 dark:hover:bg-red-800 px-4 py-2 rounded-md transition-colors">
                            <i class="fas fa-trash mr-2"></i>Supprimer ce produit
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmation pour suppression d'image -->
<div id="delete-image-modal" class="fixed inset-0 z-50 overflow-auto bg-black bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6 transform transition-all">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Supprimer l'image</h3>
            <button id="close-image-modal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="mb-6">
            <p class="text-gray-700 dark:text-gray-300">Êtes-vous sûr de vouloir supprimer cette image ? Cette action est irréversible.</p>
        </div>
        <div class="flex justify-end space-x-3">
            <button id="cancel-image-delete" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                Annuler
            </button>
            <button id="confirm-image-delete" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                Supprimer
            </button>
        </div>
    </div>
</div>

<!-- Modal de confirmation pour suppression du produit -->
<div id="delete-product-modal" class="fixed inset-0 z-50 overflow-auto bg-black bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6 transform transition-all">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Supprimer le produit</h3>
            <button id="close-product-modal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="mb-6">
            <p class="text-gray-700 dark:text-gray-300">Êtes-vous sûr de vouloir supprimer ce produit ? Cette action supprimera définitivement le produit et toutes ses images.</p>
            <div class="mt-3 p-3 bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-700 rounded-md">
                <p class="text-sm text-red-800 dark:text-red-200">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <strong>Attention :</strong> Cette action est irréversible !
                </p>
            </div>
        </div>
        <div class="flex justify-end space-x-3">
            <button id="cancel-product-delete" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                Annuler
            </button>
            <button id="confirm-product-delete" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                Supprimer définitivement
            </button>
        </div>
    </div>
</div>

<!-- Modal de duplication de produit -->
<div id="duplicate-product-modal" class="fixed inset-0 z-50 overflow-auto bg-black bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6 transform transition-all">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Dupliquer le produit</h3>
            <button id="close-duplicate-modal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="mb-6">
            <p class="text-gray-700 dark:text-gray-300 mb-4">Cette action créera une copie exacte de ce produit avec un nouveau nom.</p>
            
            <form id="duplicate-form" method="POST" action="duplicate_product.php">
                <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                
                <div class="mb-4">
                    <label for="new_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Nom du nouveau produit
                    </label>
                    <input type="text" id="new_name" name="new_name" value="<?php echo htmlspecialchars($product['name']); ?> (Copie)" required class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white p-2">
                </div>
                
                <div class="flex items-center mb-4">
                    <input type="checkbox" id="copy_images" name="copy_images" value="1" checked class="h-4 w-4 text-netblue-600 focus:ring-netblue-500 border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-800">
                    <label for="copy_images" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                        Copier aussi les images
                    </label>
                </div>
            </form>
        </div>
        <div class="flex justify-end space-x-3">
            <button id="cancel-duplicate" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                Annuler
            </button>
            <button id="confirm-duplicate" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                <i class="fas fa-copy mr-2"></i>Dupliquer
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion de la suppression d'image
    const deleteImageButtons = document.querySelectorAll('.delete-image-button');
    const deleteImageModal = document.getElementById('delete-image-modal');
    const closeImageModalButton = document.getElementById('close-image-modal');
    const cancelImageDeleteButton = document.getElementById('cancel-image-delete');
    const confirmImageDeleteButton = document.getElementById('confirm-image-delete');
    let currentImageForm = null;
    
    deleteImageButtons.forEach(button => {
        button.addEventListener('click', function() {
            currentImageForm = this.closest('.delete-image-form');
            deleteImageModal.classList.remove('hidden');
        });
    });
    
    const closeImageModal = function() {
        deleteImageModal.classList.add('hidden');
        currentImageForm = null;
    };
    
    closeImageModalButton.addEventListener('click', closeImageModal);
    cancelImageDeleteButton.addEventListener('click', closeImageModal);
    
    confirmImageDeleteButton.addEventListener('click', function() {
        if (currentImageForm) {
            currentImageForm.submit();
        }
    });
    
    // Gestion de la suppression du produit
    const deleteProductButton = document.querySelector('.delete-product-button');
    const deleteProductModal = document.getElementById('delete-product-modal');
    const closeProductModalButton = document.getElementById('close-product-modal');
    const cancelProductDeleteButton = document.getElementById('cancel-product-delete');
    const confirmProductDeleteButton = document.getElementById('confirm-product-delete');
    const deleteProductForm = document.querySelector('.delete-product-form');
    
    if (deleteProductButton) {
        deleteProductButton.addEventListener('click', function() {
            deleteProductModal.classList.remove('hidden');
        });
    }
    
    const closeProductModal = function() {
        deleteProductModal.classList.add('hidden');
    };
    
    closeProductModalButton.addEventListener('click', closeProductModal);
    cancelProductDeleteButton.addEventListener('click', closeProductModal);
    
    confirmProductDeleteButton.addEventListener('click', function() {
        if (deleteProductForm) {
            deleteProductForm.submit();
        }
    });
    
    // Gestion de la duplication de produit
    const duplicateButton = document.getElementById('duplicate-product');
    const duplicateModal = document.getElementById('duplicate-product-modal');
    const closeDuplicateModalButton = document.getElementById('close-duplicate-modal');
    const cancelDuplicateButton = document.getElementById('cancel-duplicate');
    const confirmDuplicateButton = document.getElementById('confirm-duplicate');
    const duplicateForm = document.getElementById('duplicate-form');
    
    if (duplicateButton) {
        duplicateButton.addEventListener('click', function() {
            duplicateModal.classList.remove('hidden');
        });
    }
    
    const closeDuplicateModal = function() {
        duplicateModal.classList.add('hidden');
    };
    
    closeDuplicateModalButton.addEventListener('click', closeDuplicateModal);
    cancelDuplicateButton.addEventListener('click', closeDuplicateModal);
    
    confirmDuplicateButton.addEventListener('click', function() {
        if (duplicateForm) {
            duplicateForm.submit();
        }
    });
    
    // Fermeture des modals en cliquant à l'extérieur
    [deleteImageModal, deleteProductModal, duplicateModal].forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.classList.add('hidden');
            }
        });
    });
    
    // Auto-remplissage du titre SEO basé sur le nom du produit
    const nameField = document.getElementById('name');
    const metaTitleField = document.getElementById('meta_title');
    
    nameField.addEventListener('input', function() {
        if (!metaTitleField.value.trim() || metaTitleField.value === nameField.dataset.originalValue) {
            metaTitleField.value = this.value;
        }
    });
    
    // Validation du formulaire
    const form = document.querySelector('form[action*="edit_product.php"]');
    if (form) {
        form.addEventListener('submit', function(e) {
            const price = parseFloat(document.getElementById('price').value);
            const salePrice = parseFloat(document.getElementById('sale_price').value);
            
            if (salePrice && salePrice >= price) {
                e.preventDefault();
                alert('Le prix promotionnel doit être inférieur au prix normal.');
                return false;
            }
        });
    }
    
    // Prévisualisation des images avant upload
    const imageInput = document.getElementById('images');
    if (imageInput) {
        imageInput.addEventListener('change', function() {
            const files = this.files;
            let totalSize = 0;
            
            for (let i = 0; i < files.length; i++) {
                totalSize += files[i].size;
                
                // Vérifier la taille de chaque fichier (5MB max)
                if (files[i].size > 5 * 1024 * 1024) {
                    alert(`Le fichier "${files[i].name}" est trop volumineux (max 5MB).`);
                    this.value = '';
                    return;
                }
            }
            
            // Vérifier la taille totale (20MB max)
            if (totalSize > 20 * 1024 * 1024) {
                alert('La taille totale des fichiers ne peut pas dépasser 20MB.');
                this.value = '';
                return;
            }
        });
    }
});
</script>

<?php
// Inclusion du pied de page
require_once 'includes/footer.php';
?>