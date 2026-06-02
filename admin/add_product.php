<?php
// Activer la mise en tampon de sortie
ob_start();

// Titre de la page pour l'inclusion de l'en-tête
$page_title = "Ajouter un produit";

// Inclusion de l'en-tête
require_once 'includes/header.php';

// Initialisation des variables pour le formulaire
$product = [
    'name' => '',
    'sku' => '',
    'category_id' => 0,
    'description' => '',
    'short_description' => '',
    'price' => '',
    'sale_price' => '',
    'cost_price' => '',
    'weight' => '',
    'dimensions' => '',
    'stock' => '',
    'status' => 'active',
    'supplier_id' => '',
    'supplier_url' => '',
    'shipping_time' => '5-10'
];

$errors = [];
$success = false;

// Récupération des catégories
$categories_query = "SELECT id, name FROM categories ORDER BY name";
$categories_result = $conn->query($categories_query);
$categories = [];
if ($categories_result && $categories_result->num_rows > 0) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Récupération des fournisseurs
$suppliers_query = "SELECT id, name FROM suppliers ORDER BY name";
$suppliers_result = $conn->query($suppliers_query);
$suppliers = [];
if ($suppliers_result && $suppliers_result->num_rows > 0) {
    while ($row = $suppliers_result->fetch_assoc()) {
        $suppliers[] = $row;
    }
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération des données du formulaire
    $product['name'] = trim($_POST['name'] ?? '');
    $product['sku'] = trim($_POST['sku'] ?? '');
    $product['category_id'] = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
    $product['description'] = trim($_POST['description'] ?? '');
    $product['short_description'] = trim($_POST['short_description'] ?? '');
    $product['price'] = isset($_POST['price']) ? str_replace(',', '.', $_POST['price']) : '';
    $product['sale_price'] = isset($_POST['sale_price']) && $_POST['sale_price'] !== '' ? str_replace(',', '.', $_POST['sale_price']) : null;
    $product['cost_price'] = isset($_POST['cost_price']) && $_POST['cost_price'] !== '' ? str_replace(',', '.', $_POST['cost_price']) : null;
    $product['weight'] = isset($_POST['weight']) ? str_replace(',', '.', $_POST['weight']) : '';
    $product['dimensions'] = trim($_POST['dimensions'] ?? '');
    $product['stock'] = isset($_POST['stock']) ? intval($_POST['stock']) : 0;
    $product['status'] = $_POST['status'] ?? 'active';
    $product['supplier_id'] = isset($_POST['supplier_id']) ? $_POST['supplier_id'] : null;
    $product['supplier_url'] = trim($_POST['supplier_url'] ?? '');
    $product['shipping_time'] = trim($_POST['shipping_time'] ?? '5-10');
    
    // Spécifications sous forme de JSON
    $specifications = [];
    if (isset($_POST['spec_keys']) && isset($_POST['spec_values'])) {
        $spec_keys = $_POST['spec_keys'];
        $spec_values = $_POST['spec_values'];
        
        foreach ($spec_keys as $index => $key) {
            if (!empty($key) && isset($spec_values[$index])) {
                $specifications[$key] = $spec_values[$index];
            }
        }
    }
    
    // Contenu du package sous forme de JSON
    $package_contents = [];
    if (isset($_POST['package_items'])) {
        foreach ($_POST['package_items'] as $item) {
            if (!empty($item)) {
                $package_contents[] = $item;
            }
        }
    }
    
    // Validation
    if (empty($product['name'])) {
        $errors[] = "Le nom du produit est obligatoire.";
    }
    
    if (empty($product['price']) || !is_numeric($product['price']) || $product['price'] <= 0) {
        $errors[] = "Le prix doit être un nombre positif.";
    }
    
    if ($product['sale_price'] !== null && (!is_numeric($product['sale_price']) || $product['sale_price'] < 0)) {
        $errors[] = "Le prix de vente doit être un nombre positif ou nul.";
    }
    
    if ($product['cost_price'] !== null && (!is_numeric($product['cost_price']) || $product['cost_price'] < 0)) {
        $errors[] = "Le prix de revient doit être un nombre positif ou nul.";
    }
    
    // Poids optionnel - validation seulement si renseigné
    if (!empty($product['weight']) && (!is_numeric($product['weight']) || $product['weight'] < 0)) {
        $errors[] = "Le poids doit être un nombre positif.";
    }

    if (!empty($product['sku'])) {
        // Vérifier si le SKU existe déjà
        $check_sku_query = "SELECT id FROM products WHERE sku = ?";
        $stmt = $conn->prepare($check_sku_query);
        $stmt->bind_param("s", $product['sku']);
        $stmt->execute();
        $check_result = $stmt->get_result();
        
        if ($check_result && $check_result->num_rows > 0) {
            $errors[] = "Ce SKU est déjà utilisé par un autre produit.";
        }
    }
    
    // Si aucune erreur, insérer le produit dans la base de données
    if (empty($errors)) {
        // Générer un slug à partir du nom
        $slug = generateSlug($product['name']);
        
        // Préparer les spécifications et le contenu du package au format JSON
        $specs_json = !empty($specifications) ? json_encode($specifications) : null;
        $package_json = !empty($package_contents) ? json_encode($package_contents) : null;
        
        // Échapper correctement les données pour éviter les injections SQL
        $name = $conn->real_escape_string($product['name']);
        $sku = $conn->real_escape_string($product['sku']);
        $category_id = intval($product['category_id']);
        $description = $conn->real_escape_string($product['description']);
        $short_description = $conn->real_escape_string($product['short_description']);
        $price = floatval($product['price']);
        $sale_price = $product['sale_price'] !== null ? floatval($product['sale_price']) : "NULL";
        $cost_price = $product['cost_price'] !== null ? floatval($product['cost_price']) : "NULL";
        $weight = !empty($product['weight']) ? floatval($product['weight']) : "NULL";
        $dimensions = $conn->real_escape_string($product['dimensions']);
        $stock = intval($product['stock']);
        $status = $conn->real_escape_string($product['status']);
        $specs_json_escaped = $specs_json ? "'" . $conn->real_escape_string($specs_json) . "'" : "NULL";
        $package_json_escaped = $package_json ? "'" . $conn->real_escape_string($package_json) . "'" : "NULL";
        $supplier_id = $product['supplier_id'] ? "'" . $conn->real_escape_string($product['supplier_id']) . "'" : "NULL";
        $supplier_url = $conn->real_escape_string($product['supplier_url']);
        $shipping_time = $conn->real_escape_string($product['shipping_time']);

        $slug_escaped = $conn->real_escape_string($slug);
        // Construire la requête SQL avec les valeurs échappées
        $insert_query = "INSERT INTO products (
                            name, slug, sku, category_id, description, short_description,
                            price, sale_price, cost_price, weight, dimensions,
                            stock, status, specifications, package_contents,
                            supplier_id, supplier_url, shipping_time
                        ) VALUES (
                            '$name', '$slug_escaped', '$sku', $category_id, '$description', '$short_description',
                            $price, " . ($sale_price === "NULL" ? "NULL" : "$sale_price") . ",
                            " . ($cost_price === "NULL" ? "NULL" : "$cost_price") . ",
                            " . ($weight === "NULL" ? "NULL" : "$weight") . ", '$dimensions', $stock, '$status',
                            $specs_json_escaped, $package_json_escaped,
                            $supplier_id, '$supplier_url', '$shipping_time'
                        )";
        
        if ($conn->query($insert_query)) {
            $product_id = $conn->insert_id;
            
            // Téléchargement et enregistrement des images
            if (!empty($_FILES['product_images']['name'][0])) {
                $upload_dir = '../uploads/products/';
                
                // S'assurer que le répertoire existe
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $images_count = count($_FILES['product_images']['name']);
                $primary_image_index = isset($_POST['primary_image']) ? intval($_POST['primary_image']) : 0;
                
                for ($i = 0; $i < $images_count; $i++) {
                    if ($_FILES['product_images']['error'][$i] === 0) {
                        $tmp_name = $_FILES['product_images']['tmp_name'][$i];
                        $name = $_FILES['product_images']['name'][$i];
                        $ext = pathinfo($name, PATHINFO_EXTENSION);
                        $new_name = uniqid() . '_' . $slug . '.' . $ext;
                        
                        if (move_uploaded_file($tmp_name, $upload_dir . $new_name)) {
                            $is_primary = ($i === $primary_image_index) ? 1 : 0;
                            
                            // Insertion de l'image dans la base de données
                            $image_query = "INSERT INTO product_images (product_id, image_url, is_primary, display_order) 
                                           VALUES (?, ?, ?, ?)";
                            $stmt = $conn->prepare($image_query);
                            $image_path = 'uploads/products/' . $new_name;
                            $stmt->bind_param("isii", $product_id, $image_path, $is_primary, $i);
                            $stmt->execute();
                        }
                    }
                }
            }
            
            // Redirection vers la page de liste des produits avec un message de succès
            header("Location: products.php?success=" . urlencode("Le produit a été ajouté avec succès."));
            exit;
        } else {
            $errors[] = "Une erreur est survenue lors de l'ajout du produit : " . $conn->error;
        }
    }
}
?>

<!-- Add Product Form -->
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
    <div class="mb-6">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white">Ajouter un nouveau produit</h2>
        <p class="text-gray-600 dark:text-gray-400 mt-1">Remplissez le formulaire ci-dessous pour ajouter un nouveau produit.</p>
    </div>
    
    <?php if (!empty($errors)): ?>
    <div class="mb-6 bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500 p-4 rounded">
        <div class="flex items-center mb-2">
            <i class="fas fa-exclamation-circle text-red-500 mr-2"></i>
            <h3 class="text-red-800 dark:text-red-400 font-medium">Des erreurs ont été détectées</h3>
        </div>
        <ul class="text-red-700 dark:text-red-300 text-sm ml-6 list-disc">
            <?php foreach ($errors as $error): ?>
            <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <form action="add_product.php" method="POST" enctype="multipart/form-data" class="space-y-6">
        <!-- Reste du formulaire reste inchangé -->
        <!-- Form Tabs -->
        <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
            <ul class="flex flex-wrap -mb-px text-sm font-medium text-center">
                <li class="mr-2">
                    <a href="#general" class="tab-button inline-block p-4 border-b-2 border-netblue-600 text-netblue-600 dark:text-netblue-400 dark:border-netblue-400 active" aria-current="page">
                        <i class="fas fa-info-circle mr-2"></i>Général
                    </a>
                </li>
                <li class="mr-2">
                    <a href="#media" class="tab-button inline-block p-4 border-b-2 border-transparent hover:text-gray-600 hover:border-gray-300 dark:hover:text-gray-300">
                        <i class="fas fa-images mr-2"></i>Images
                    </a>
                </li>
                <li class="mr-2">
                    <a href="#specs" class="tab-button inline-block p-4 border-b-2 border-transparent hover:text-gray-600 hover:border-gray-300 dark:hover:text-gray-300">
                        <i class="fas fa-clipboard-list mr-2"></i>Spécifications
                    </a>
                </li>
                <li>
                    <a href="#shipping" class="tab-button inline-block p-4 border-b-2 border-transparent hover:text-gray-600 hover:border-gray-300 dark:hover:text-gray-300">
                        <i class="fas fa-shipping-fast mr-2"></i>Livraison
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- General Tab Content -->
        <div id="general-tab" class="tab-content">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Product Name -->
                <div class="col-span-1 md:col-span-2">
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Nom du produit <span class="text-red-500">*</span></label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-netblue-500 focus:ring-netblue-500 dark:bg-gray-700 dark:text-white" required>
                </div>
                
                <!-- SKU & Category -->
                <div>
                    <label for="sku" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">SKU (Référence)</label>
                    <input type="text" id="sku" name="sku" value="<?php echo htmlspecialchars($product['sku']); ?>" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-netblue-500 focus:ring-netblue-500 dark:bg-gray-700 dark:text-white">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Laissez vide pour générer automatiquement</p>
                </div>
                
                <div>
                    <label for="category_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Catégorie</label>
                    <select id="category_id" name="category_id" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-netblue-500 focus:ring-netblue-500 dark:bg-gray-700 dark:text-white">
                        <option value="0">Sélectionner une catégorie</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo $product['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Short Description -->
                <div class="col-span-1 md:col-span-2">
                    <label for="short_description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Description courte</label>
                    <textarea id="short_description" name="short_description" rows="2" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-netblue-500 focus:ring-netblue-500 dark:bg-gray-700 dark:text-white"><?php echo htmlspecialchars($product['short_description']); ?></textarea>
                </div>
                
                <!-- Description -->
                <div class="col-span-1 md:col-span-2">
                    <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Description complète <span class="text-red-500">*</span></label>
                    <textarea id="description" name="description" rows="6" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-netblue-500 focus:ring-netblue-500 dark:bg-gray-700 dark:text-white" required><?php echo htmlspecialchars($product['description']); ?></textarea>
                </div>
                
                <!-- Pricing -->
                <div>
                    <label for="price" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Prix (FCFA) <span class="text-red-500">*</span></label>
                    <div class="relative rounded-md shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 dark:text-gray-400 sm:text-sm">FCFA</span>
                        </div>
                        <input type="number" step="0.01" min="0" id="price" name="price" value="<?php echo htmlspecialchars($product['price']); ?>" class="block w-full pl-7 pr-12 rounded-md border-gray-300 dark:border-gray-600 focus:border-netblue-500 focus:ring-netblue-500 dark:bg-gray-700 dark:text-white" placeholder="0.00" required>
                    </div>
                </div>
                
                <div>
                    <label for="sale_price" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Prix promotionnel (FCFA)</label>
                    <div class="relative rounded-md shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 dark:text-gray-400 sm:text-sm">FCFA</span>
                        </div>
                        <input type="number" step="0.01" min="0" id="sale_price" name="sale_price" value="<?php echo htmlspecialchars($product['sale_price']); ?>" class="block w-full pl-7 pr-12 rounded-md border-gray-300 dark:border-gray-600 focus:border-netblue-500 focus:ring-netblue-500 dark:bg-gray-700 dark:text-white" placeholder="0.00">
                    </div>
                </div>
                
                <div>
                    <label for="cost_price" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Prix de revient (FCFA)</label>
                    <div class="relative rounded-md shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 dark:text-gray-400 sm:text-sm">FCFA</span>
                        </div>
                        <input type="number" step="0.01" min="0" id="cost_price" name="cost_price" value="<?php echo htmlspecialchars($product['cost_price']); ?>" class="block w-full pl-7 pr-12 rounded-md border-gray-300 dark:border-gray-600 focus:border-netblue-500 focus:ring-netblue-500 dark:bg-gray-700 dark:text-white" placeholder="0.00">
                    </div>
                </div>
                
                <!-- Physical Properties -->
                <div>
                    <label for="weight" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Poids (kg) <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" min="0" id="weight" name="weight" value="<?php echo htmlspecialchars($product['weight']); ?>" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-netblue-500 focus:ring-netblue-500 dark:bg-gray-700 dark:text-white" required>
                </div>
                
                <div>
                    <label for="dimensions" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Dimensions (LxlxH cm)</label>
                    <input type="text" id="dimensions" name="dimensions" value="<?php echo htmlspecialchars($product['dimensions']); ?>" placeholder="Ex: 10x20x5" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-netblue-500 focus:ring-netblue-500 dark:bg-gray-700 dark:text-white">
                </div>
                
                <!-- Inventory -->
                <div>
                    <label for="stock" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Stock <span class="text-red-500">*</span></label>
                    <input type="number" min="0" id="stock" name="stock" value="<?php echo htmlspecialchars($product['stock']); ?>" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-netblue-500 focus:ring-netblue-500 dark:bg-gray-700 dark:text-white" required>
                </div>
                
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Statut</label>
                    <select id="status" name="status" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-netblue-500 focus:ring-netblue-500 dark:bg-gray-700 dark:text-white">
                        <option value="active" <?php echo $product['status'] === 'active' ? 'selected' : ''; ?>>Actif</option>
                        <option value="inactive" <?php echo $product['status'] === 'inactive' ? 'selected' : ''; ?>>Inactif</option>
                        <option value="out_of_stock" <?php echo $product['status'] === 'out_of_stock' ? 'selected' : ''; ?>>Rupture de stock</option>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Media Tab Content -->
        <div id="media-tab" class="tab-content hidden">
            <div class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Images du produit</label>
                    <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 dark:border-gray-600 border-dashed rounded-md">
                        <div class="space-y-1 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4h-12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <div class="flex text-sm text-gray-600 dark:text-gray-400">
                                <label for="product_images" class="relative cursor-pointer bg-white dark:bg-gray-700 rounded-md font-medium text-netblue-600 dark:text-netblue-400 hover:text-netblue-500 focus-within:outline-none">
                                    <span>Télécharger des fichiers</span>
                                    <input id="product_images" name="product_images[]" type="file" class="sr-only" accept="image/*" multiple>
                                </label>
                                <p class="pl-1">ou glisser-déposer</p>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                PNG, JPG, GIF jusqu'à 2MB
                            </p>
                        </div>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Images sélectionnées</label>
                    <div id="selected-images" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                        <div class="text-center text-gray-500 dark:text-gray-400 text-sm py-4">
                            Aucune image sélectionnée
                        </div>
                    </div>
                </div>
                
                <div>
                    <input type="hidden" name="primary_image" id="primary_image" value="0">
                </div>
            </div>
        </div>
        
        <!-- Specifications Tab Content -->
        <div id="specs-tab" class="tab-content hidden">
            <div class="space-y-6">
                <!-- Specifications -->
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Caractéristiques techniques</label>
                        <button type="button" id="add-specification" class="text-sm text-netblue-600 dark:text-netblue-400 hover:text-netblue-700 dark:hover:text-netblue-300">
                            <i class="fas fa-plus mr-1"></i>Ajouter
                        </button>
                    </div>
                    
                    <div id="specifications-container" class="space-y-3">
                        <div class="spec-row grid grid-cols-2 gap-4">
                            <div>
                                <input type="text" name="spec_keys[]" placeholder="Nom (ex: Couleur)" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-netblue-500 focus:ring-netblue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div class="flex items-center gap-2">
                                <input type="text" name="spec_values[]" placeholder="Valeur (ex: Noir)" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-netblue-500 focus:ring-netblue-500 dark:bg-gray-700 dark:text-white">
                                <button type="button" class="remove-spec text-red-500 hover:text-red-700">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Package Contents -->
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Contenu de la boîte</label>
                        <button type="button" id="add-package-item" class="text-sm text-netblue-600 dark:text-netblue-400 hover:text-netblue-700 dark:hover:text-netblue-300">
                            <i class="fas fa-plus mr-1"></i>Ajouter
                        </button>
                    </div>
                    
                    <div id="package-items-container" class="space-y-3">
                        <div class="package-item-row flex items-center gap-2">
                            <input type="text" name="package_items[]" placeholder="Élément (ex: Manuel d'utilisation)" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-netblue-500 focus:ring-netblue-500 dark:bg-gray-700 dark:text-white">
                            <button type="button" class="remove-package-item text-red-500 hover:text-red-700">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Shipping Tab Content -->
         <!-- Shipping Tab Content -->
        <div id="shipping-tab" class="tab-content hidden">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Supplier -->
                <div>
                    <label for="supplier_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Fournisseur</label>
                    <select id="supplier_id" name="supplier_id" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-netblue-500 focus:ring-netblue-500 dark:bg-gray-700 dark:text-white">
                        <option value="">Sélectionner un fournisseur</option>
                        <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?php echo $supplier['id']; ?>" <?php echo $product['supplier_id'] == $supplier['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($supplier['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="supplier_url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">URL du produit chez le fournisseur</label>
                    <input type="url" id="supplier_url" name="supplier_url" value="<?php echo htmlspecialchars($product['supplier_url']); ?>" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-netblue-500 focus:ring-netblue-500 dark:bg-gray-700 dark:text-white">
                </div>
                
                <!-- Shipping Info -->
                <div>
                    <label for="shipping_time" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Délai de livraison estimé (jours)</label>
                    <input type="text" id="shipping_time" name="shipping_time" value="<?php echo htmlspecialchars($product['shipping_time']); ?>" placeholder="Ex: 5-10" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-netblue-500 focus:ring-netblue-500 dark:bg-gray-700 dark:text-white">
                </div>
            </div>
        </div>
        
        <!-- Form Actions -->
        <div class="pt-6 border-t border-gray-200 dark:border-gray-700 flex justify-end space-x-3">
            <a href="products.php" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                Annuler
            </a>
            <button type="submit" class="px-4 py-2 bg-netblue-600 text-white rounded-md hover:bg-netblue-700 transition-colors">
                Ajouter le produit
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab switching functionality
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Get the tab ID from the href attribute
            const tabId = this.getAttribute('href').replace('#', '');
            
            // Remove active class from all buttons and contents
            tabButtons.forEach(btn => {
                btn.classList.remove('border-netblue-600', 'text-netblue-600', 'dark:text-netblue-400', 'dark:border-netblue-400');
                btn.classList.add('border-transparent', 'hover:text-gray-600', 'hover:border-gray-300', 'dark:hover:text-gray-300');
                btn.removeAttribute('aria-current');
            });
            
            tabContents.forEach(content => {
                content.classList.add('hidden');
            });
            
            // Add active class to clicked button
            this.classList.add('border-netblue-600', 'text-netblue-600', 'dark:text-netblue-400', 'dark:border-netblue-400');
            this.classList.remove('border-transparent', 'hover:text-gray-600', 'hover:border-gray-300', 'dark:hover:text-gray-300');
            this.setAttribute('aria-current', 'page');
            
            // Show corresponding content
            document.getElementById(`${tabId}-tab`).classList.remove('hidden');
        });
    });
    
    // Handle image selection and preview
    const fileInput = document.getElementById('product_images');
    const selectedImagesContainer = document.getElementById('selected-images');
    const primaryImageInput = document.getElementById('primary_image');
    let selectedFiles = [];
    
    fileInput.addEventListener('change', function() {
        const files = this.files;
        
        if (files.length > 0) {
            selectedImagesContainer.innerHTML = '';
            selectedFiles = Array.from(files);
            
            selectedFiles.forEach((file, index) => {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const imageContainer = document.createElement('div');
                    imageContainer.className = 'relative group';
                    
                    const isPrimary = index === parseInt(primaryImageInput.value);
                    
                    imageContainer.innerHTML = `
                        <div class="aspect-w-1 aspect-h-1 w-full overflow-hidden rounded-md bg-gray-200 dark:bg-gray-700">
                            <img src="${e.target.result}" alt="Preview" class="h-full w-full object-cover object-center">
                        </div>
                        <div class="mt-2 flex items-center justify-between">
                            <button type="button" class="set-primary-btn text-xs ${isPrimary ? 'text-netblue-600 dark:text-netblue-400 font-medium' : 'text-gray-600 dark:text-gray-400'}" data-index="${index}">
                                <i class="fas ${isPrimary ? 'fa-check-circle' : 'fa-circle'}"></i> 
                                ${isPrimary ? 'Image principale' : 'Définir comme principale'}
                            </button>
                            <button type="button" class="remove-image-btn text-xs text-red-600 dark:text-red-400" data-index="${index}">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    `;
                    
                    selectedImagesContainer.appendChild(imageContainer);
                    
                    // Add event listeners for the buttons
                    const setPrimaryBtn = imageContainer.querySelector('.set-primary-btn');
                    const removeImageBtn = imageContainer.querySelector('.remove-image-btn');
                    
                    setPrimaryBtn.addEventListener('click', function() {
                        const imageIndex = parseInt(this.getAttribute('data-index'));
                        setPrimaryImage(imageIndex);
                    });
                    
                    removeImageBtn.addEventListener('click', function() {
                        const imageIndex = parseInt(this.getAttribute('data-index'));
                        removeImage(imageIndex);
                    });
                };
                
                reader.readAsDataURL(file);
            });
        } else {
            selectedImagesContainer.innerHTML = '<div class="text-center text-gray-500 dark:text-gray-400 text-sm py-4">Aucune image sélectionnée</div>';
        }
    });
    
    function setPrimaryImage(index) {
        primaryImageInput.value = index;
        
        // Update UI for all image buttons
        const primaryBtns = document.querySelectorAll('.set-primary-btn');
        primaryBtns.forEach(btn => {
            const btnIndex = parseInt(btn.getAttribute('data-index'));
            
            if (btnIndex === index) {
                btn.innerHTML = '<i class="fas fa-check-circle"></i> Image principale';
                btn.classList.add('text-netblue-600', 'dark:text-netblue-400', 'font-medium');
                btn.classList.remove('text-gray-600', 'dark:text-gray-400');
            } else {
                btn.innerHTML = '<i class="fas fa-circle"></i> Définir comme principale';
                btn.classList.remove('text-netblue-600', 'dark:text-netblue-400', 'font-medium');
                btn.classList.add('text-gray-600', 'dark:text-gray-400');
            }
        });
    }
    
    function removeImage(index) {
        // Create a new FileList without the removed file
        const dt = new DataTransfer();
        
        selectedFiles.forEach((file, i) => {
            if (i !== index) {
                dt.items.add(file);
            }
        });
        
        fileInput.files = dt.files;
        selectedFiles = Array.from(dt.files);
        
        // Trigger the change event to update the preview
        const event = new Event('change');
        fileInput.dispatchEvent(event);
        
        // Reset primary image if it was the one removed
        if (parseInt(primaryImageInput.value) === index) {
            primaryImageInput.value = 0;
        } else if (parseInt(primaryImageInput.value) > index) {
            // Adjust index if primary was after the removed one
            primaryImageInput.value = parseInt(primaryImageInput.value) - 1;
        }
    }
    
    // Add specification row
    const addSpecBtn = document.getElementById('add-specification');
    const specificationsContainer = document.getElementById('specifications-container');
    
    addSpecBtn.addEventListener('click', function() {
        const newRow = document.createElement('div');
        newRow.className = 'spec-row grid grid-cols-2 gap-4';
        newRow.innerHTML = `
            <div>
                <input type="text" name="spec_keys[]" placeholder="Nom (ex: Couleur)" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-netblue-500 focus:ring-netblue-500 dark:bg-gray-700 dark:text-white">
            </div>
            <div class="flex items-center gap-2">
                <input type="text" name="spec_values[]" placeholder="Valeur (ex: Noir)" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-netblue-500 focus:ring-netblue-500 dark:bg-gray-700 dark:text-white">
                <button type="button" class="remove-spec text-red-500 hover:text-red-700">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;
        
        specificationsContainer.appendChild(newRow);
        
        // Add event listener to the remove button
        const removeButton = newRow.querySelector('.remove-spec');
        removeButton.addEventListener('click', function() {
            newRow.remove();
        });
    });
    
    // Remove specification row (for initial row)
    document.querySelectorAll('.remove-spec').forEach(button => {
        button.addEventListener('click', function() {
            this.closest('.spec-row').remove();
        });
    });
    
    // Add package item
    const addPackageItemBtn = document.getElementById('add-package-item');
    const packageItemsContainer = document.getElementById('package-items-container');
    
    addPackageItemBtn.addEventListener('click', function() {
        const newRow = document.createElement('div');
        newRow.className = 'package-item-row flex items-center gap-2';
        newRow.innerHTML = `
            <input type="text" name="package_items[]" placeholder="Élément (ex: Manuel d'utilisation)" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-netblue-500 focus:ring-netblue-500 dark:bg-gray-700 dark:text-white">
            <button type="button" class="remove-package-item text-red-500 hover:text-red-700">
                <i class="fas fa-trash"></i>
            </button>
        `;
        
        packageItemsContainer.appendChild(newRow);
        
        // Add event listener to the remove button
        const removeButton = newRow.querySelector('.remove-package-item');
        removeButton.addEventListener('click', function() {
            newRow.remove();
        });
    });
    
    // Remove package item (for initial row)
    document.querySelectorAll('.remove-package-item').forEach(button => {
        button.addEventListener('click', function() {
            this.closest('.package-item-row').remove();
        });
    });
});
</script>

<?php
// Inclusion du pied de page
require_once 'includes/footer.php';
?>