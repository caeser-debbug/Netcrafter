<?php
// Titre de la page pour l'inclusion de l'en-tête
$page_title = "Paramètres de la boutique";

// Inclusion de l'en-tête
require_once 'includes/header.php';

// Vérifier si l'utilisateur a les droits d'administrateur
if (!isset($_SESSION['admin_is_admin']) || $_SESSION['admin_is_admin'] != 1) {
    header("Location: index.php?error=" . urlencode("Vous n'avez pas les droits nécessaires pour accéder à cette page."));
    exit;
}

// Vérifier si la table settings existe, sinon la créer
$check_table = $conn->query("SHOW TABLES LIKE 'settings'");
if ($check_table->num_rows == 0) {
    // Créer la table settings
    $create_table = "CREATE TABLE IF NOT EXISTS `settings` (
        `id` int NOT NULL AUTO_INCREMENT,
        `site_name` varchar(100) NOT NULL DEFAULT 'Netcrafter',
        `site_description` text,
        `contact_email` varchar(100) DEFAULT NULL,
        `contact_phone` varchar(20) DEFAULT NULL,
        `address` text,
        `facebook_url` varchar(255) DEFAULT NULL,
        `twitter_url` varchar(255) DEFAULT NULL,
        `instagram_url` varchar(255) DEFAULT NULL,
        `linkedin_url` varchar(255) DEFAULT NULL,
        `analytics_code` varchar(50) DEFAULT NULL,
        `currency` varchar(10) DEFAULT 'FCFA',
        `tax_rate` decimal(5,2) DEFAULT '18.00',
        `shipping_fee` decimal(10,2) DEFAULT '2000.00',
        `free_shipping_min` decimal(10,2) DEFAULT '30000.00',
        `maintenance_mode` tinyint(1) DEFAULT '0',
        `logo_path` varchar(255) DEFAULT 'image/logo-n.png',
        `favicon_path` varchar(255) DEFAULT 'image/favicon.ico',
        `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;";
    
    $conn->query($create_table);
    
    // Insérer des valeurs par défaut
    $insert_default = "INSERT INTO `settings` (id, site_name, site_description, contact_email, contact_phone, currency) 
                       VALUES (1, 'Netcrafter', 'Services informatiques et développement web', 'contact@netcrafterniger.com', '+227 88 37 18 17', 'FCFA')";
    $conn->query($insert_default);
}

// Récupérer les paramètres actuels
$query = "SELECT * FROM settings WHERE id = 1";
$result = $conn->query($query);
$settings = $result->fetch_assoc();

if (!$settings) {
    // Créer une entrée par défaut si elle n'existe pas
    $default_settings = [
        'site_name' => 'Netcrafter',
        'site_description' => 'Services informatiques et développement web',
        'contact_email' => 'contact@netcrafterniger.com',
        'contact_phone' => '+227 88 37 18 17',
        'address' => 'Niamey, Niger',
        'facebook_url' => '',
        'twitter_url' => '',
        'instagram_url' => '',
        'linkedin_url' => '',
        'analytics_code' => '',
        'currency' => 'FCFA',
        'tax_rate' => '18',
        'shipping_fee' => '2000',
        'free_shipping_min' => '30000',
        'maintenance_mode' => '0',
        'logo_path' => 'image/logo-n.png',
        'favicon_path' => 'image/favicon.ico'
    ];
    
    $columns = implode(", ", array_keys($default_settings));
    $placeholders = implode(", ", array_fill(0, count($default_settings), "?"));
    
    $insert_query = "INSERT INTO settings (id, $columns) VALUES (1, $placeholders)";
    $stmt = $conn->prepare($insert_query);
    $types = str_repeat("s", count($default_settings));
    $stmt->bind_param($types, ...array_values($default_settings));
    $stmt->execute();
    
    $settings = $default_settings;
    $settings['id'] = 1;
}

// Traitement du formulaire de mise à jour
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_settings') {
        // Récupérer les valeurs du formulaire
        $site_name = isset($_POST['site_name']) ? trim($_POST['site_name']) : '';
        $site_description = isset($_POST['site_description']) ? trim($_POST['site_description']) : '';
        $contact_email = isset($_POST['contact_email']) ? trim($_POST['contact_email']) : '';
        $contact_phone = isset($_POST['contact_phone']) ? trim($_POST['contact_phone']) : '';
        $address = isset($_POST['address']) ? trim($_POST['address']) : '';
        $facebook_url = isset($_POST['facebook_url']) ? trim($_POST['facebook_url']) : '';
        $twitter_url = isset($_POST['twitter_url']) ? trim($_POST['twitter_url']) : '';
        $instagram_url = isset($_POST['instagram_url']) ? trim($_POST['instagram_url']) : '';
        $linkedin_url = isset($_POST['linkedin_url']) ? trim($_POST['linkedin_url']) : '';
        $analytics_code = isset($_POST['analytics_code']) ? trim($_POST['analytics_code']) : '';
        $currency = isset($_POST['currency']) ? trim($_POST['currency']) : 'FCFA';
        $tax_rate = isset($_POST['tax_rate']) ? floatval($_POST['tax_rate']) : 0;
        $shipping_fee = isset($_POST['shipping_fee']) ? floatval($_POST['shipping_fee']) : 0;
        $free_shipping_min = isset($_POST['free_shipping_min']) ? floatval($_POST['free_shipping_min']) : 0;
        $maintenance_mode = isset($_POST['maintenance_mode']) ? '1' : '0';
        
        // Validation
        if (empty($site_name)) {
            $error_message = "Le nom du site est requis.";
        } elseif (!filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "L'adresse e-mail n'est pas valide.";
        } else {
            // Gérer les uploads de logo et favicon si présents
            $logo_path = $settings['logo_path'];
            $favicon_path = $settings['favicon_path'];
            
            // Traitement du logo
            if (isset($_FILES['logo']) && $_FILES['logo']['size'] > 0) {
                $target_dir = "../image/";
                $file_extension = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                $new_filename = 'logo_' . time() . '.' . $file_extension;
                $target_file = $target_dir . $new_filename;
                
                // Vérifier le type de fichier
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
                if (!in_array($file_extension, $allowed_extensions)) {
                    $error_message = "Seuls les fichiers JPG, JPEG, PNG, GIF et SVG sont autorisés pour le logo.";
                } elseif ($_FILES['logo']['size'] > 1000000) { // 1MB max
                    $error_message = "Le fichier logo est trop volumineux. Max: 1MB.";
                } elseif (move_uploaded_file($_FILES['logo']['tmp_name'], $target_file)) {
                    $logo_path = "image/" . $new_filename;
                } else {
                    $error_message = "Erreur lors de l'upload du logo.";
                }
            }
            
            // Traitement du favicon
            if (empty($error_message) && isset($_FILES['favicon']) && $_FILES['favicon']['size'] > 0) {
                $target_dir = "../image/";
                $file_extension = strtolower(pathinfo($_FILES['favicon']['name'], PATHINFO_EXTENSION));
                $new_filename = 'favicon_' . time() . '.' . $file_extension;
                $target_file = $target_dir . $new_filename;
                
                // Vérifier le type de fichier
                $allowed_extensions = ['ico', 'png'];
                if (!in_array($file_extension, $allowed_extensions)) {
                    $error_message = "Seuls les fichiers ICO et PNG sont autorisés pour le favicon.";
                } elseif ($_FILES['favicon']['size'] > 500000) { // 500KB max
                    $error_message = "Le fichier favicon est trop volumineux. Max: 500KB.";
                } elseif (move_uploaded_file($_FILES['favicon']['tmp_name'], $target_file)) {
                    $favicon_path = "image/" . $new_filename;
                } else {
                    $error_message = "Erreur lors de l'upload du favicon.";
                }
            }
            
            // Mise à jour des paramètres
            if (empty($error_message)) {
                $update_query = "UPDATE settings SET 
                    site_name = ?, 
                    site_description = ?, 
                    contact_email = ?, 
                    contact_phone = ?, 
                    address = ?, 
                    facebook_url = ?, 
                    twitter_url = ?, 
                    instagram_url = ?, 
                    linkedin_url = ?, 
                    analytics_code = ?, 
                    currency = ?, 
                    tax_rate = ?, 
                    shipping_fee = ?, 
                    free_shipping_min = ?, 
                    maintenance_mode = ?, 
                    logo_path = ?, 
                    favicon_path = ?, 
                    updated_at = NOW() 
                    WHERE id = 1";
                
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param("sssssssssssdddsss", 
                    $site_name, 
                    $site_description, 
                    $contact_email, 
                    $contact_phone, 
                    $address, 
                    $facebook_url, 
                    $twitter_url, 
                    $instagram_url, 
                    $linkedin_url, 
                    $analytics_code, 
                    $currency, 
                    $tax_rate, 
                    $shipping_fee, 
                    $free_shipping_min, 
                    $maintenance_mode, 
                    $logo_path, 
                    $favicon_path
                );
                
                if ($stmt->execute()) {
                    $success_message = "Les paramètres ont été mis à jour avec succès.";
                    
                    // Mettre à jour les valeurs affichées
                    $settings['site_name'] = $site_name;
                    $settings['site_description'] = $site_description;
                    $settings['contact_email'] = $contact_email;
                    $settings['contact_phone'] = $contact_phone;
                    $settings['address'] = $address;
                    $settings['facebook_url'] = $facebook_url;
                    $settings['twitter_url'] = $twitter_url;
                    $settings['instagram_url'] = $instagram_url;
                    $settings['linkedin_url'] = $linkedin_url;
                    $settings['analytics_code'] = $analytics_code;
                    $settings['currency'] = $currency;
                    $settings['tax_rate'] = $tax_rate;
                    $settings['shipping_fee'] = $shipping_fee;
                    $settings['free_shipping_min'] = $free_shipping_min;
                    $settings['maintenance_mode'] = $maintenance_mode;
                    $settings['logo_path'] = $logo_path;
                    $settings['favicon_path'] = $favicon_path;
                } else {
                    $error_message = "Erreur lors de la mise à jour des paramètres : " . $conn->error;
                }
            }
        }
    }
}
?>

<div class="space-y-6">
    <!-- Alert Messages -->
    <?php if (!empty($success_message)): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded" role="alert">
        <p><?php echo htmlspecialchars($success_message); ?></p>
    </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded" role="alert">
        <p><?php echo htmlspecialchars($error_message); ?></p>
    </div>
    <?php endif; ?>

    <!-- Settings Form -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <div class="border-b border-gray-200 dark:border-gray-700 p-4 md:p-6">
            <h2 class="text-xl font-bold text-gray-800 dark:text-white">Paramètres du site</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Configuration générale du site et de la boutique</p>
        </div>

        <form method="POST" action="settings.php" enctype="multipart/form-data" class="p-4 md:p-6">
            <input type="hidden" name="action" value="update_settings">
            
            <!-- Tabs -->
            <div class="mb-6 border-b border-gray-200 dark:border-gray-700">
                <ul class="flex flex-wrap -mb-px text-sm font-medium text-center" id="settings-tabs" role="tablist">
                    <li class="mr-2" role="presentation">
                        <button type="button" class="inline-block p-4 border-b-2 border-netblue-600 rounded-t-lg active" id="general-tab" data-tab="general">
                            <i class="fas fa-cog mr-2"></i>Général
                        </button>
                    </li>
                    <li class="mr-2" role="presentation">
                        <button type="button" class="inline-block p-4 border-b-2 border-transparent rounded-t-lg hover:border-gray-300 dark:hover:border-gray-600" id="contact-tab" data-tab="contact">
                            <i class="fas fa-address-book mr-2"></i>Contact
                        </button>
                    </li>
                    <li class="mr-2" role="presentation">
                        <button type="button" class="inline-block p-4 border-b-2 border-transparent rounded-t-lg hover:border-gray-300 dark:hover:border-gray-600" id="ecommerce-tab" data-tab="ecommerce">
                            <i class="fas fa-shopping-cart mr-2"></i>E-commerce
                        </button>
                    </li>
                    <li role="presentation">
                        <button type="button" class="inline-block p-4 border-b-2 border-transparent rounded-t-lg hover:border-gray-300 dark:hover:border-gray-600" id="appearance-tab" data-tab="appearance">
                            <i class="fas fa-palette mr-2"></i>Apparence
                        </button>
                    </li>
                </ul>
            </div>
            
            <!-- Tab contents -->
            <div id="tab-content">
                <!-- General Tab -->
                <div id="general-content" class="tab-pane active">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="site_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Nom du site <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="site_name" name="site_name" value="<?php echo htmlspecialchars($settings['site_name']); ?>" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                        </div>
                        
                        <div>
                            <label for="maintenance_mode" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Mode maintenance
                            </label>
                            <div class="flex items-center mt-2">
                                <input type="checkbox" id="maintenance_mode" name="maintenance_mode" <?php echo $settings['maintenance_mode'] === '1' ? 'checked' : ''; ?> class="h-4 w-4 text-netblue-600 focus:ring-netblue-500 border-gray-300 dark:border-gray-600 rounded">
                                <label for="maintenance_mode" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                    Activer le mode maintenance
                                </label>
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Lorsqu'activé, seuls les administrateurs peuvent accéder au site.
                            </p>
                        </div>
                        
                        <div class="md:col-span-2">
                            <label for="site_description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Description du site
                            </label>
                            <textarea id="site_description" name="site_description" rows="3" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white"><?php echo htmlspecialchars($settings['site_description']); ?></textarea>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Brève description utilisée pour le référencement (SEO).
                            </p>
                        </div>
                        
                        <div>
                            <label for="analytics_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Code Google Analytics
                            </label>
                            <input type="text" id="analytics_code" name="analytics_code" value="<?php echo htmlspecialchars($settings['analytics_code']); ?>" placeholder="G-XXXXXXXXXX" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                ID de mesure Google Analytics (ex: G-XXXXXXXXXX).
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Contact Tab -->
                <div id="contact-content" class="tab-pane hidden">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="contact_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Email de contact <span class="text-red-500">*</span>
                            </label>
                            <input type="email" id="contact_email" name="contact_email" value="<?php echo htmlspecialchars($settings['contact_email']); ?>" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                        </div>
                        
                        <div>
                            <label for="contact_phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Téléphone
                            </label>
                            <input type="text" id="contact_phone" name="contact_phone" value="<?php echo htmlspecialchars($settings['contact_phone']); ?>" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label for="address" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Adresse
                            </label>
                            <textarea id="address" name="address" rows="3" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white"><?php echo htmlspecialchars($settings['address']); ?></textarea>
                        </div>
                        
                        <div>
                            <label for="facebook_url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Facebook
                            </label>
                            <div class="flex">
                                <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                                    <i class="fab fa-facebook"></i>
                                </span>
                                <input type="url" id="facebook_url" name="facebook_url" value="<?php echo htmlspecialchars($settings['facebook_url']); ?>" placeholder="https://facebook.com/votrepage" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-r-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                            </div>
                        </div>
                        
                        <div>
                            <label for="twitter_url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Twitter
                            </label>
                            <div class="flex">
                                <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                                    <i class="fab fa-twitter"></i>
                                </span>
                                <input type="url" id="twitter_url" name="twitter_url" value="<?php echo htmlspecialchars($settings['twitter_url']); ?>" placeholder="https://twitter.com/votrecompte" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-r-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                            </div>
                        </div>
                        
                        <div>
                            <label for="instagram_url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Instagram
                            </label>
                            <div class="flex">
                                <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                                    <i class="fab fa-instagram"></i>
                                </span>
                                <input type="url" id="instagram_url" name="instagram_url" value="<?php echo htmlspecialchars($settings['instagram_url']); ?>" placeholder="https://instagram.com/votrecompte" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-r-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                            </div>
                        </div>
                        
                        <div>
                            <label for="linkedin_url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                LinkedIn
                            </label>
                            <div class="flex">
                                <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                                    <i class="fab fa-linkedin"></i>
                                </span>
                                <input type="url" id="linkedin_url" name="linkedin_url" value="<?php echo htmlspecialchars($settings['linkedin_url']); ?>" placeholder="https://linkedin.com/company/votreentreprise" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-r-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- E-commerce Tab -->
                <div id="ecommerce-content" class="tab-pane hidden">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="currency" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Devise
                            </label>
                            <select id="currency" name="currency" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                                <option value="FCFA" <?php echo $settings['currency'] === 'FCFA' ? 'selected' : ''; ?>>FCFA</option>
                                <option value="€" <?php echo $settings['currency'] === '€' ? 'selected' : ''; ?>>Euro (€)</option>
                                <option value="$" <?php echo $settings['currency'] === '$' ? 'selected' : ''; ?>>Dollar ($)</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="tax_rate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Taux de TVA (%)
                            </label>
                            <input type="number" id="tax_rate" name="tax_rate" value="<?php echo htmlspecialchars($settings['tax_rate']); ?>" min="0" max="100" step="0.01" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                        </div>
                        
                        <div>
                            <label for="shipping_fee" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Frais de livraison standard
                            </label>
                            <div class="flex">
                                <input type="number" id="shipping_fee" name="shipping_fee" value="<?php echo htmlspecialchars($settings['shipping_fee']); ?>" min="0" step="1" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-l-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                                <span class="inline-flex items-center px-3 rounded-r-md border border-l-0 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                                    <?php echo htmlspecialchars($settings['currency']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div>
                            <label for="free_shipping_min" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Montant minimum pour livraison gratuite
                            </label>
                            <div class="flex">
                                <input type="number" id="free_shipping_min" name="free_shipping_min" value="<?php echo htmlspecialchars($settings['free_shipping_min']); ?>" min="0" step="1" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-l-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                                <span class="inline-flex items-center px-3 rounded-r-md border border-l-0 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                                    <?php echo htmlspecialchars($settings['currency']); ?>
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Mettez 0 pour désactiver la livraison gratuite.
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Appearance Tab -->
                <div id="appearance-content" class="tab-pane hidden">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Logo actuel
                            </label>
                            <div class="mt-1 flex items-center">
                                <div class="w-48 h-16 overflow-hidden bg-gray-100 dark:bg-gray-700 rounded-md flex items-center justify-center">
                                    <?php if (!empty($settings['logo_path']) && file_exists('../' . $settings['logo_path'])): ?>
                                        <img src="../<?php echo htmlspecialchars($settings['logo_path']); ?>" alt="Logo" class="max-h-16 max-w-full">
                                    <?php else: ?>
                                        <span class="text-gray-400">Aucun logo</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <label for="logo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mt-4 mb-1">
                                Changer le logo
                            </label>
                            <input type="file" id="logo" name="logo" accept=".jpg,.jpeg,.png,.gif,.svg" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Formats acceptés : JPG, PNG, GIF, SVG. Taille max : 1MB.
                            </p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Favicon actuel
                            </label>
                            <div class="mt-1 flex items-center">
                                <div class="w-16 h-16 overflow-hidden bg-gray-100 dark:bg-gray-700 rounded-md flex items-center justify-center">
                                    <?php if (!empty($settings['favicon_path']) && file_exists('../' . $settings['favicon_path'])): ?>
                                        <img src="../<?php echo htmlspecialchars($settings['favicon_path']); ?>" alt="Favicon" class="max-h-16 max-w-full">
                                    <?php else: ?>
                                        <span class="text-gray-400">Aucun favicon</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <label for="favicon" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mt-4 mb-1">
                                Changer le favicon
                            </label>
                            <input type="file" id="favicon" name="favicon" accept=".ico,.png" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Formats acceptés : ICO, PNG. Taille max : 500KB. Taille recommandée : 32x32px ou 16x16px.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Submit Button -->
            <div class="mt-8 flex justify-end">
                <button type="submit" class="px-4 py-2 bg-netblue-600 text-white rounded-md hover:bg-netblue-700 transition-colors">
                    <i class="fas fa-save mr-2"></i>Enregistrer les modifications
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab navigation
    const tabs = document.querySelectorAll('[data-tab]');
    const tabContents = document.querySelectorAll('.tab-pane');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            // Remove active class from all tabs
            tabs.forEach(t => {
                t.classList.remove('active', 'border-netblue-600');
                t.classList.add('border-transparent', 'hover:border-gray-300', 'dark:hover:border-gray-600');
            });
            
            // Add active class to clicked tab
            this.classList.add('active', 'border-netblue-600');
            this.classList.add('active', 'border-netblue-600');
            this.classList.remove('border-transparent', 'hover:border-gray-300', 'dark:hover:border-gray-600');
            
            // Hide all tab contents
            tabContents.forEach(content => {
                content.classList.add('hidden');
            });
            
            // Show selected tab content
            const tabId = this.getAttribute('data-tab');
            document.getElementById(tabId + '-content').classList.remove('hidden');
        });
    });
    
    // Form validation
    const settingsForm = document.querySelector('form');
    
    settingsForm.addEventListener('submit', function(e) {
        const siteName = document.getElementById('site_name').value.trim();
        const contactEmail = document.getElementById('contact_email').value.trim();
        
        if (siteName === '') {
            e.preventDefault();
            alert('Le nom du site est requis.');
            document.getElementById('general-tab').click();
            document.getElementById('site_name').focus();
        } else if (contactEmail === '') {
            e.preventDefault();
            alert('L\'email de contact est requis.');
            document.getElementById('contact-tab').click();
            document.getElementById('contact_email').focus();
        } else if (contactEmail !== '' && !isValidEmail(contactEmail)) {
            e.preventDefault();
            alert('L\'adresse e-mail n\'est pas valide.');
            document.getElementById('contact-tab').click();
            document.getElementById('contact_email').focus();
        }
    });
    
    // Email validation function
    function isValidEmail(email) {
        const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return re.test(email);
    }
});
</script>

<?php
// Inclusion du pied de page
require_once 'includes/footer.php';
?>