<?php
// Initialisation de la session pour le panier et les favoris
session_start();

require_once __DIR__ . '/db.php';

// Connexion à la base de données
$conn = new mysqli($servername, $username, $password, $dbname);

// Vérification de la connexion
if ($conn->connect_error) {
    die("Échec de la connexion: " . $conn->connect_error);
}
$conn->set_charset("utf8");

// Capture de l'adresse MAC pour le suivi (à utiliser avec précaution et conformément au RGPD)
function getMacAddress() {
    // Cette fonction est simplifiée et peut nécessiter des ajustements selon l'environnement
    if (function_exists('exec')) {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Pour Windows
            exec('getmac', $output);
            if (isset($output[1])) {
                $mac = substr($output[1], 0, 17);
                return $mac;
            }
        } else {
            // Pour Linux/Unix
            exec("ifconfig -a | grep -Po 'HWaddr \K.*$'", $output);
            if (!empty($output[0])) {
                return trim($output[0]);
            }
        }
    }
    return $_SERVER['REMOTE_ADDR']; // Fallback à l'adresse IP
}

// Initialisation du panier et des favoris s'ils n'existent pas
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
if (!isset($_SESSION['favorites'])) {
    $_SESSION['favorites'] = [];
}

// Enregistrement de la session avec l'adresse MAC si ce n'est pas déjà fait
if (!isset($_SESSION['mac_address'])) {
    $_SESSION['mac_address'] = getMacAddress();
    
    // On pourrait enregistrer cette session dans la base de données ici
    $session_id = session_id();
    $mac_address = $_SESSION['mac_address'];
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    // Vérifier si cette session existe déjà
    $check_session = "SELECT id FROM sessions WHERE id = ?";
    $stmt = $conn->prepare($check_session);
    $stmt->bind_param("s", $session_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        // Insérer la nouvelle session
        $insert_session = "INSERT INTO sessions (id, ip_address, mac_address, data) VALUES (?, ?, ?, ?)";
        $session_data = json_encode(['cart' => $_SESSION['cart'], 'favorites' => $_SESSION['favorites']]);
        
        $stmt = $conn->prepare($insert_session);
        $stmt->bind_param("ssss", $session_id, $ip_address, $mac_address, $session_data);
        $stmt->execute();
    }
}

// Traitement des actions (ajout au panier, ajout aux favoris, etc.)
if (isset($_POST['action'])) {
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    
    if ($_POST['action'] === 'add_to_cart') {
        // Ajouter au panier
        if (array_key_exists($product_id, $_SESSION['cart'])) {
            $_SESSION['cart'][$product_id]++;
        } else {
            $_SESSION['cart'][$product_id] = 1;
        }
        
        // Mettre à jour la session dans la base de données
        $session_id = session_id();
        $session_data = json_encode(['cart' => $_SESSION['cart'], 'favorites' => $_SESSION['favorites']]);
        
        $update_session = "UPDATE sessions SET data = ? WHERE id = ?";
        $stmt = $conn->prepare($update_session);
        $stmt->bind_param("ss", $session_data, $session_id);
        $stmt->execute();
        
        header("Location: shop.php?cart_added=1");
        exit;
    } 
    elseif ($_POST['action'] === 'add_to_favorites') {
        // Ajouter aux favoris
        if (!in_array($product_id, $_SESSION['favorites'])) {
            $_SESSION['favorites'][] = $product_id;
            
            // Mettre à jour la session dans la base de données
            $session_id = session_id();
            $session_data = json_encode(['cart' => $_SESSION['cart'], 'favorites' => $_SESSION['favorites']]);
            
            $update_session = "UPDATE sessions SET data = ? WHERE id = ?";
            $stmt = $conn->prepare($update_session);
            $stmt->bind_param("ss", $session_data, $session_id);
            $stmt->execute();
        }
        header("Location: shop.php?fav_added=1");
        exit;
    }
    elseif ($_POST['action'] === 'remove_from_favorites') {
        // Supprimer des favoris
        if (($key = array_search($product_id, $_SESSION['favorites'])) !== false) {
            unset($_SESSION['favorites'][$key]);
            $_SESSION['favorites'] = array_values($_SESSION['favorites']); // Réindexer le tableau
            
            // Mettre à jour la session dans la base de données
            $session_id = session_id();
            $session_data = json_encode(['cart' => $_SESSION['cart'], 'favorites' => $_SESSION['favorites']]);
            
            $update_session = "UPDATE sessions SET data = ? WHERE id = ?";
            $stmt = $conn->prepare($update_session);
            $stmt->bind_param("ss", $session_data, $session_id);
            $stmt->execute();
        }
        header("Location: shop.php?fav_removed=1");
        exit;
    }
    elseif ($_POST['action'] === 'remove_from_cart') {
        // Supprimer du panier
        if (array_key_exists($product_id, $_SESSION['cart'])) {
            unset($_SESSION['cart'][$product_id]);
            
            // Mettre à jour la session dans la base de données
            $session_id = session_id();
            $session_data = json_encode(['cart' => $_SESSION['cart'], 'favorites' => $_SESSION['favorites']]);
            
            $update_session = "UPDATE sessions SET data = ? WHERE id = ?";
            $stmt = $conn->prepare($update_session);
            $stmt->bind_param("ss", $session_data, $session_id);
            $stmt->execute();
        }
        header("Location: shop.php?cart_removed=1");
        exit;
    }
}

// Récupération des catégories
$categories_query = "SELECT * FROM categories ORDER BY name";
$categories_result = $conn->query($categories_query);
$categories = [];
if ($categories_result && $categories_result->num_rows > 0) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Paramètres de filtrage et de recherche
$category_id = isset($_GET['category']) ? intval($_GET['category']) : 0;
$search_term = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
$max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 10000;
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'newest';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$products_per_page = 12; // Nombre de produits par page

// Construction de la requête SQL de base
$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        JOIN categories c ON p.category_id = c.id 
        WHERE p.status = 'active' AND p.price >= ? AND p.price <= ?";
$params = [$min_price, $max_price];
$types = "dd"; // double, double

// Ajout des conditions de filtrage
if ($category_id > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $category_id;
    $types .= "i"; // integer
}

if (!empty($search_term)) {
    $sql .= " AND (p.name LIKE CONCAT('%', ?, '%') OR p.description LIKE CONCAT('%', ?, '%'))";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss"; // string, string
}

// Comptage du nombre total de produits pour la pagination
$count_sql = preg_replace('/SELECT p\.\*, c\.name as category_name/i', 'SELECT COUNT(*) as total', $sql);
$stmt = $conn->prepare($count_sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$count_result = $stmt->get_result();
$total_products = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_products / $products_per_page);

// Tri des résultats
switch ($sort_by) {
    case 'price_low':
        $sql .= " ORDER BY p.price ASC";
        break;
    case 'price_high':
        $sql .= " ORDER BY p.price DESC";
        break;
    case 'popular':
        $sql .= " ORDER BY p.views DESC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY p.created_at DESC";
        break;
}

// Ajout de la pagination
$offset = ($page - 1) * $products_per_page;
$sql .= " LIMIT ?, ?";
$params[] = $offset;
$params[] = $products_per_page;
$types .= "ii"; // integer, integer

// Exécution de la requête
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$products = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Récupération des images pour chaque produit
        $product_id = $row['id'];
        $images_query = "SELECT image_url FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, display_order ASC";
        $img_stmt = $conn->prepare($images_query);
        $img_stmt->bind_param("i", $product_id);
        $img_stmt->execute();
        $images_result = $img_stmt->get_result();
        
        $images = [];
        if ($images_result && $images_result->num_rows > 0) {
            while ($img_row = $images_result->fetch_assoc()) {
                $images[] = $img_row['image_url'];
            }
        }
        
        $row['images'] = $images;
        $products[] = $row;
    }
}

// Obtenir les prix min et max pour les filtres
$price_range_query = "SELECT MIN(price) as min_price, MAX(price) as max_price FROM products WHERE status = 'active'";
$price_range_result = $conn->query($price_range_query);
$price_range = $price_range_result->fetch_assoc();

// Suggestion de produits populaires
$popular_products_query = "SELECT p.*, c.name as category_name 
                          FROM products p 
                          JOIN categories c ON p.category_id = c.id 
                          WHERE p.status = 'active' 
                          ORDER BY p.views DESC 
                          LIMIT 6";
$popular_result = $conn->query($popular_products_query);
$popular_products = [];

if ($popular_result && $popular_result->num_rows > 0) {
    while ($row = $popular_result->fetch_assoc()) {
        $product_id = $row['id'];
        $images_query = "SELECT image_url FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, display_order ASC LIMIT 1";
        $img_stmt = $conn->prepare($images_query);
        $img_stmt->bind_param("i", $product_id);
        $img_stmt->execute();
        $images_result = $img_stmt->get_result();
        
        $images = [];
        if ($images_result && $images_result->num_rows > 0) {
            while ($img_row = $images_result->fetch_assoc()) {
                $images[] = $img_row['image_url'];
            }
        }
        
        $row['images'] = $images;
        $popular_products[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr" class="light scroll">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Netcrafter Shop - Boutique en ligne</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Animation library -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- AOS Library for scroll animations -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
    <!-- Swiper Slider -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.js"></script>
    <!-- Custom styles -->
    <style>
        html {
            scroll-behavior: smooth;
            overflow-x: hidden;
        }
        
        body {
            width: 100%;
            max-width: 100vw;
            overflow-x: hidden;
        }
        
        .hero-gradient-light {
            background: linear-gradient(135deg, #0288d1 0%, #01579b 100%);
        }
        
        .hero-gradient-dark {
            background: linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%);
        }
        
        .product-card {
            transition: all 0.3s ease;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .text-shadow {
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }
        
        .blob {
            position: absolute;
            width: 300px;
            height: 300px;
            filter: blur(80px);
            opacity: 0.2;
            z-index: -1;
            border-radius: 50%;
            animation: blobMove 20s infinite alternate ease-in-out;
        }
        
        @keyframes blobMove {
            0% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(100px, 50px) scale(1.2); }
            66% { transform: translate(-50px, 100px) scale(0.8); }
            100% { transform: translate(70px, -70px) scale(1.1); }
        }
        
        /* Swiper customizations */
        .swiper {
            width: 100%;
            height: 100%;
        }
        
        .swiper-slide {
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .swiper-slide img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .swiper-pagination-bullet-active {
            background-color: #3B82F6 !important;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        /* Truncate lines */
        .line-clamp-1 {
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .line-clamp-3 {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Slider pour le prix */
        .price-slider {
            width: 100%;
            height: 5px;
            border-radius: 5px;
            background: #ddd;
            outline: none;
            -webkit-appearance: none;
        }
        
        .price-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #3B82F6;
            cursor: pointer;
        }
        
        .price-slider::-moz-range-thumb {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #3B82F6;
            cursor: pointer;
        }
        
        .dark .price-slider {
            background: #4B5563;
        }
        
        /* Animation du loader */
        .loader {
            border-top-color: #3B82F6;
            -webkit-animation: spinner 1.5s linear infinite;
            animation: spinner 1.5s linear infinite;
        }
        
        @-webkit-keyframes spinner {
            0% { -webkit-transform: rotate(0deg); }
            100% { -webkit-transform: rotate(360deg); }
        }
        
        @keyframes spinner {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        netblue: {
                            100: '#E6F2FF',
                            200: '#B8D4FF',
                            300: '#8AB6FF',
                            400: '#5C98FF',
                            500: '#3B82F6',
                            600: '#1A6BE2',
                            700: '#0055CC',
                            800: '#003F99',
                            900: '#002966'
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-white transition-colors duration-300">
    <!-- Navigation -->
    <nav class="fixed w-full bg-white dark:bg-gray-800 shadow-md z-50 transition-all duration-300" id="navbar">
        <div class="container mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center">
                <img src="../image/logo-n.png" alt="Netcrafter Logo" class="h-10 mr-2">
                <span class="text-xl md:text-2xl font-bold text-netblue-600 dark:text-netblue-400 navbar-brand">NETCRAFTER</span>
            </div>
            
            <!-- Desktop Menu -->
            <div class="hidden md:flex items-center">
                <div class="space-x-4 md:space-x-6 text-gray-700 dark:text-gray-300 mr-6">
                    <a href="../index.html" class="hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors">Accueil</a>
                    <a href="../service.html" class="hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors">Services</a>
                    <a href="../devis.html" class="hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors">Devis</a>
                    <a href="shop.php" class="text-netblue-600 dark:text-netblue-400 font-medium">Boutique</a>
                    <a href="../formation/formations.php" class="hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors">Formation</a>
                </div>
                
                <!-- Cart and Favorites -->
                <div class="flex items-center space-x-4 mr-4">
                    <a href="favorites.php" class="relative text-gray-700 dark:text-gray-300 hover:text-netblue-600 dark:hover:text-netblue-400" title="Mes favoris">
                        <i class="fas fa-heart text-xl"></i>
                        <?php if (count($_SESSION['favorites']) > 0): ?>
                        <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                            <?php echo count($_SESSION['favorites']); ?>
                        </span>
                        <?php endif; ?>
                    </a>
                    <a href="cart.php" class="relative text-gray-700 dark:text-gray-300 hover:text-netblue-600 dark:hover:text-netblue-400" title="Mon panier">
                        <i class="fas fa-shopping-cart text-xl"></i>
                        <?php if (count($_SESSION['cart']) > 0): ?>
                        <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                            <?php echo array_sum($_SESSION['cart']); ?>
                        </span>
                        <?php endif; ?>
                    </a>
                </div>
                
                <!-- Dark Mode Toggle -->
                <div class="flex items-center">
                    <label class="theme-switch relative inline-block w-14 h-7">
                        <input type="checkbox" id="darkModeToggle" class="opacity-0 w-0 h-0">
                        <span class="slider absolute cursor-pointer inset-0 bg-gray-300 rounded-full transition-all duration-300 before:absolute before:h-5 before:w-5 before:left-1 before:bottom-1 before:bg-white before:rounded-full before:transition-all before:duration-300"></span>
                    </label>
                </div>
            </div>
            
            <!-- Mobile Menu Button, Cart, Favorites and Dark Mode Toggle -->
            <div class="md:hidden flex items-center space-x-4">
                <a href="favorites.php" class="relative text-gray-700 dark:text-gray-300">
                    <i class="fas fa-heart text-xl"></i>
                    <?php if (count($_SESSION['favorites']) > 0): ?>
                    <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                        <?php echo count($_SESSION['favorites']); ?>
                    </span>
                    <?php endif; ?>
                </a>
                <a href="cart.php" class="relative text-gray-700 dark:text-gray-300">
                    <i class="fas fa-shopping-cart text-xl"></i>
                    <?php if (count($_SESSION['cart']) > 0): ?>
                    <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                        <?php echo array_sum($_SESSION['cart']); ?>
                    </span>
                    <?php endif; ?>
                </a>
                
                <!-- Mobile Dark Mode Toggle -->
                <div class="flex items-center">
                    <label class="theme-switch relative inline-block w-12 h-6">
                        <input type="checkbox" id="darkModeToggleMobile" class="opacity-0 w-0 h-0">
                        <span class="slider absolute cursor-pointer inset-0 bg-gray-300 rounded-full transition-all duration-300 before:absolute before:h-4 before:w-4 before:left-1 before:bottom-1 before:bg-white before:rounded-full before:transition-all before:duration-300"></span>
                    </label>
                </div>
                
                <button id="menu-toggle" class="text-gray-700 dark:text-white focus:outline-none">
                    <i class="fas fa-bars text-2xl"></i>
                </button>
            </div>
        </div>
        
        <!-- Mobile Menu -->
        <div id="mobile-menu" class="hidden md:hidden bg-white dark:bg-gray-800 shadow-md">
            <div class="px-4 py-2 space-y-3">
                <a href="../index.html" class="block py-2 hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors dark:text-gray-300">Accueil</a>
                <a href="../service.html" class="block py-2 hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors dark:text-gray-300">Services</a>
                <a href="../devis.html" class="block py-2 hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors dark:text-gray-300">Devis</a>
                <a href="shop.php" class="block py-2 text-netblue-600 dark:text-netblue-400 font-medium">Boutique</a>
                <a href="../formation/formation.html" class="block py-2 hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors dark:text-gray-300">Formation</a>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <section class="hero-gradient-light dark:hero-gradient-dark text-white pt-32 pb-16 relative overflow-hidden">
        <!-- Background Blobs -->
        <div class="blob bg-blue-500 dark:bg-blue-700" style="top: -200px; left: -200px;"></div>
        <div class="blob bg-purple-500 dark:bg-purple-700" style="bottom: -200px; right: -200px;"></div>
        
        <div class="container mx-auto px-4 relative">
            <div class="text-center mb-8">
                <h1 class="text-3xl md:text-4xl lg:text-5xl font-bold mb-4 animate__animated animate__fadeInUp text-shadow">
                    Boutique Netcrafter
                </h1>
                <p class="text-base md:text-lg max-w-3xl mx-auto animate__animated animate__fadeInUp animate__delay-1s">
                    Découvrez nos produits de qualité, expédiés directement depuis nos fournisseurs partenaires
                </p>
            </div>
            
            <!-- Search Bar -->
            <div class="max-w-3xl mx-auto bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 animate__animated animate__fadeInUp animate__delay-2s">
                <form action="shop.php" method="GET" class="flex flex-col md:flex-row gap-3">
                    <div class="flex-1 relative">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Rechercher un produit..." class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    </div>
                    <button type="submit" class="bg-netblue-600 hover:bg-netblue-700 text-white font-medium py-2 px-6 rounded-lg transition-colors">
                        <i class="fas fa-search mr-2"></i>Rechercher
                    </button>
                    <button type="button" id="filter-toggle" class="md:hidden bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-700 dark:text-white font-medium py-2 px-6 rounded-lg transition-colors">
                        <i class="fas fa-filter mr-2"></i>Filtres
                    </button>
                </form>
            </div>
        </div>
    </section>

    <!-- Main Shop Section -->
    <section class="py-8 md:py-12 bg-gray-50 dark:bg-gray-900">
        <div class="container mx-auto px-4">
            <div class="flex flex-col lg:flex-row gap-8">
                <!-- Filter Sidebar - Desktop -->
                <div class="lg:w-1/4 hidden lg:block">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 sticky top-24" data-aos="fade-right">
                        <h3 class="text-xl font-bold mb-6 dark:text-white">Filtres</h3>
                        
                        <form action="shop.php" method="GET" id="filter-form">
                            <!-- Search term hidden field (to preserve it when filtering) -->
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_term); ?>">
                            
                            <!-- Categories -->
                            <div class="mb-6">
                                <h4 class="font-semibold mb-3 dark:text-white">Catégories</h4>
                                <div class="space-y-2">
                                    <div class="flex items-center">
                                        <input type="radio" id="cat_all" name="category" value="0" <?php echo $category_id == 0 ? 'checked' : ''; ?> class="h-4 w-4 text-netblue-600 focus:ring-netblue-500 border-gray-300 rounded">
                                        <label for="cat_all" class="ml-2 text-gray-700 dark:text-gray-300">Toutes les catégories</label>
                                    </div>
                                    
                                    <?php foreach ($categories as $category): ?>
                                    <div class="flex items-center">
                                        <input type="radio" id="cat_<?php echo $category['id']; ?>" name="category" value="<?php echo $category['id']; ?>" <?php echo $category_id == $category['id'] ? 'checked' : ''; ?> class="h-4 w-4 text-netblue-600 focus:ring-netblue-500 border-gray-300 rounded">
                                        <label for="cat_<?php echo $category['id']; ?>" class="ml-2 text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($category['name']); ?></label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Price Range -->
                            <div class="mb-6">
                                <h4 class="font-semibold mb-3 dark:text-white">Fourchette de prix</h4>
                                <div class="space-y-4">
                                    <div class="flex flex-col">
                                        <div class="flex justify-between mb-1">
                                            <label for="min_price" class="text-sm text-gray-600 dark:text-gray-400">Min: <span id="min-value"><?php echo $min_price; ?></span> FCFA</label>
                                            <label for="max_price" class="text-sm text-gray-600 dark:text-gray-400">Max: <span id="max-value"><?php echo $max_price; ?></span> FCFA</label>
                                        </div>
                                        <input type="range" id="min_price_slider" min="<?php echo $price_range['min_price']; ?>" max="<?php echo $price_range['max_price']; ?>" value="<?php echo $min_price; ?>" class="price-slider mb-2">
                                        <input type="range" id="max_price_slider" min="<?php echo $price_range['min_price']; ?>" max="<?php echo $price_range['max_price']; ?>" value="<?php echo $max_price; ?>" class="price-slider">
                                        
                                        <input type="hidden" id="min_price" name="min_price" value="<?php echo $min_price; ?>">
                                        <input type="hidden" id="max_price" name="max_price" value="<?php echo $max_price; ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Sort By -->
                            <div class="mb-6">
                                <h4 class="font-semibold mb-3 dark:text-white">Trier par</h4>
                                <select name="sort_by" class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 bg-white dark:bg-gray-700 text-gray-800 dark:text-white">
                                    <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Plus récents</option>
                                    <option value="price_low" <?php echo $sort_by == 'price_low' ? 'selected' : ''; ?>>Prix croissant</option>
                                    <option value="price_high" <?php echo $sort_by == 'price_high' ? 'selected' : ''; ?>>Prix décroissant</option>
                                    <option value="popular" <?php echo $sort_by == 'popular' ? 'selected' : ''; ?>>Popularité</option>
                                </select>
                            </div>
                            
                            <!-- Apply Filters -->
                            <button type="submit" class="w-full bg-netblue-600 hover:bg-netblue-700 text-white font-medium py-2 px-4 rounded-md transition-colors">
                                Appliquer les filtres
                            </button>
                            
                            <!-- Reset Filters -->
                            <a href="shop.php" class="w-full mt-3 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-700 dark:text-white font-medium py-2 px-4 rounded-md transition-colors text-center block">
                                Réinitialiser les filtres
                            </a>
                        </form>
                    </div>
                </div>
                
                <!-- Mobile Filter Menu -->
                <div id="mobile-filter-menu" class="fixed inset-0 bg-gray-900 bg-opacity-95 z-50 transform translate-x-full transition-transform duration-300 lg:hidden overflow-y-auto">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-2xl font-bold text-white">Filtres</h3>
                            <button id="close-filter-menu" class="text-white text-xl focus:outline-none">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        
                        <form action="shop.php" method="GET">
                            <!-- Search term hidden field -->
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_term); ?>">
                            
                            <!-- Categories -->
                            <div class="mb-8">
                                <h4 class="text-lg font-semibold mb-4 text-white">Catégories</h4>
                                <div class="space-y-3">
                                    <div class="flex items-center">
                                        <input type="radio" id="mobile_cat_all" name="category" value="0" <?php echo $category_id == 0 ? 'checked' : ''; ?> class="h-5 w-5 text-netblue-600 focus:ring-netblue-500 border-gray-300 rounded">
                                        <label for="mobile_cat_all" class="ml-3 text-white">Toutes les catégories</label>
                                    </div>
                                    
                                    <?php foreach ($categories as $category): ?>
                                    <div class="flex items-center">
                                        <input type="radio" id="mobile_cat_<?php echo $category['id']; ?>" name="category" value="<?php echo $category['id']; ?>" <?php echo $category_id == $category['id'] ? 'checked' : ''; ?> class="h-5 w-5 text-netblue-600 focus:ring-netblue-500 border-gray-300 rounded">
                                        <label for="mobile_cat_<?php echo $category['id']; ?>" class="ml-3 text-white"><?php echo htmlspecialchars($category['name']); ?></label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Price Range -->
                            <div class="mb-8">
                                <h4 class="text-lg font-semibold mb-4 text-white">Fourchette de prix</h4>
                                <div class="space-y-4">
                                    <div class="flex flex-col">
                                        <div class="flex justify-between mb-2">
                                            <label class="text-white">Min: <span id="mobile-min-value"><?php echo $min_price; ?></span> FCFA</label>
                                            <label class="text-white">Max: <span id="mobile-max-value"><?php echo $max_price; ?></span> FCFA</label>
                                        </div>
                                        <input type="range" id="mobile_min_price_slider" min="<?php echo $price_range['min_price']; ?>" max="<?php echo $price_range['max_price']; ?>" value="<?php echo $min_price; ?>" class="price-slider mb-3">
                                        <input type="range" id="mobile_max_price_slider" min="<?php echo $price_range['min_price']; ?>" max="<?php echo $price_range['max_price']; ?>" value="<?php echo $max_price; ?>" class="price-slider">
                                        
                                        <input type="hidden" id="mobile_min_price" name="min_price" value="<?php echo $min_price; ?>">
                                        <input type="hidden" id="mobile_max_price" name="max_price" value="<?php echo $max_price; ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Sort By -->
                            <div class="mb-8">
                                <h4 class="text-lg font-semibold mb-4 text-white">Trier par</h4>
                                <select name="sort_by" class="w-full border border-gray-600 rounded-md px-4 py-3 bg-gray-800 text-white">
                                    <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Plus récents</option>
                                    <option value="price_low" <?php echo $sort_by == 'price_low' ? 'selected' : ''; ?>>Prix croissant</option>
                                    <option value="price_high" <?php echo $sort_by == 'price_high' ? 'selected' : ''; ?>>Prix décroissant</option>
                                    <option value="popular" <?php echo $sort_by == 'popular' ? 'selected' : ''; ?>>Popularité</option>
                                </select>
                            </div>
                            
                            <!-- Apply Filters -->
                            <button type="submit" class="w-full bg-netblue-600 hover:bg-netblue-700 text-white font-bold py-3 px-4 rounded-lg transition-colors text-lg mb-4">
                                Appliquer les filtres
                            </button>
                            
                            <!-- Reset Filters -->
                            <a href="shop.php" class="w-full bg-gray-700 hover:bg-gray-600 text-white font-bold py-3 px-4 rounded-lg transition-colors text-lg text-center block">
                                Réinitialiser les filtres
                            </a>
                        </form>
                    </div>
                </div>
                
                <!-- Products Grid -->
                <div class="lg:w-3/4">
                    <!-- Results Summary -->
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 bg-white dark:bg-gray-800 rounded-lg shadow-md p-4" data-aos="fade-up">
                        <div>
                            <h2 class="text-xl font-bold dark:text-white">
                                <?php if (!empty($search_term)): ?>
                                    Résultats pour "<?php echo htmlspecialchars($search_term); ?>"
                                <?php elseif ($category_id > 0): 
                                    $current_category = '';
                                    foreach ($categories as $cat) {
                                        if ($cat['id'] == $category_id) {
                                            $current_category = $cat['name'];
                                            break;
                                        }
                                    }
                                ?>
                                    Catégorie : <?php echo htmlspecialchars($current_category); ?>
                                <?php else: ?>
                                    Tous nos produits
                                <?php endif; ?>
                            </h2>
                            <p class="text-gray-600 dark:text-gray-400 mt-1">
                                <?php echo $total_products; ?> produits trouvés
                            </p>
                        </div>
                        
                        <!-- Sort By - Mobile & Tablet -->
                        <div class="mt-4 sm:mt-0 self-stretch sm:self-auto w-full sm:w-auto lg:hidden">
                            <form action="shop.php" method="GET" class="flex">
                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_term); ?>">
                                <input type="hidden" name="category" value="<?php echo $category_id; ?>">
                                <input type="hidden" name="min_price" value="<?php echo $min_price; ?>">
                                <input type="hidden" name="max_price" value="<?php echo $max_price; ?>">
                                
                                <select name="sort_by" onchange="this.form.submit()" class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 bg-white dark:bg-gray-700 text-gray-800 dark:text-white">
                                    <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Trier par : Plus récents</option>
                                    <option value="price_low" <?php echo $sort_by == 'price_low' ? 'selected' : ''; ?>>Trier par : Prix croissant</option>
                                    <option value="price_high" <?php echo $sort_by == 'price_high' ? 'selected' : ''; ?>>Trier par : Prix décroissant</option>
                                    <option value="popular" <?php echo $sort_by == 'popular' ? 'selected' : ''; ?>>Trier par : Popularité</option>
                                </select>
                            </form>
                        </div>
                    </div>
                    
                    <?php if (count($products) > 0): ?>
                    <!-- Products Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($products as $product): ?>
                        <div class="product-card bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden" data-aos="fade-up">
                            <!-- Product Swiper Gallery -->
                            <div class="relative">
                                <div class="swiper product-swiper-<?php echo $product['id']; ?> h-56 sm:h-64">
                                    <div class="swiper-wrapper">
                                        <?php if (empty($product['images'])): ?>
                                            <div class="swiper-slide">
                                                <img src="../image/oops.avif" alt="<?php echo htmlspecialchars($product['name']); ?>" class="w-full h-full object-cover">
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($product['images'] as $image): ?>
                                            <div class="swiper-slide">
                                                <img src="../<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="w-full h-full object-cover">
                                            </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="swiper-pagination"></div>
                                </div>
                                
                                <!-- Category Badge -->
                                <div class="absolute top-3 left-3 bg-netblue-600 text-white text-xs font-bold px-2 py-1 rounded">
                                    <?php echo htmlspecialchars($product['category_name']); ?>
                                </div>
                                
                                <!-- Favorite Button -->
                                <form method="POST" class="absolute top-3 right-3">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                    <?php if (in_array($product['id'], $_SESSION['favorites'])): ?>
                                        <input type="hidden" name="action" value="remove_from_favorites">
                                        <button type="submit" class="bg-white dark:bg-gray-700 text-red-500 h-8 w-8 rounded-full flex items-center justify-center shadow-md hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                            <i class="fas fa-heart"></i>
                                        </button>
                                    <?php else: ?>
                                        <input type="hidden" name="action" value="add_to_favorites">
                                        <button type="submit" class="bg-white dark:bg-gray-700 text-gray-400 h-8 w-8 rounded-full flex items-center justify-center shadow-md hover:bg-gray-100 dark:hover:bg-gray-600 hover:text-red-500 transition-colors">
                                            <i class="far fa-heart"></i>
                                        </button>
                                    <?php endif; ?>
                                </form>
                                
                                <!-- Stock Badge if low stock -->
                                <?php if ($product['stock'] > 0 && $product['stock'] <= 5): ?>
                                <div class="absolute bottom-3 left-3 bg-amber-500 text-white text-xs font-bold px-2 py-1 rounded">
                                    Plus que <?php echo $product['stock']; ?> en stock
                                </div>
                                <?php elseif ($product['stock'] == 0): ?>
                                <div class="absolute bottom-3 left-3 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded">
                                    Rupture de stock
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Product Details -->
                            <div class="p-4">
                                <h3 class="text-lg font-bold mb-2 text-gray-800 dark:text-white line-clamp-1">
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </h3>
                                
                                <div class="flex justify-between items-center mb-3">
                                    <div class="text-netblue-600 dark:text-netblue-400 font-bold text-xl">
                                        <?php echo number_format($product['price'], 2); ?> FCFA
                                    </div>
                                    <div class="text-gray-500 dark:text-gray-400 text-sm">
                                        <?php echo $product['weight']; ?> kg
                                    </div>
                                </div>
                                
                                <p class="text-gray-600 dark:text-gray-300 text-sm mb-4 line-clamp-2">
                                    <?php 
                                    if (!empty($product['short_description'])) {
                                        echo htmlspecialchars($product['short_description']);
                                    } else {
                                        echo htmlspecialchars(substr($product['description'], 0, 100)) . (strlen($product['description']) > 100 ? '...' : '');
                                    }
                                    ?>
                                </p>
                                
                                <div class="flex space-x-2">
                                    <a href="product.php?id=<?php echo $product['id']; ?>" class="flex-1 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-white text-center py-2 rounded-md transition-colors text-sm font-medium">
                                        Détails
                                    </a>
                                    
                                    <?php if ($product['stock'] > 0): ?>
                                    <form method="POST" class="flex-1">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                        <input type="hidden" name="action" value="add_to_cart">
                                        <button type="submit" class="w-full bg-netblue-600 hover:bg-netblue-700 text-white text-center py-2 rounded-md transition-colors text-sm font-medium">
                                            <i class="fas fa-shopping-cart mr-1"></i> Acheter
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <button disabled class="flex-1 bg-gray-400 dark:bg-gray-600 text-white text-center py-2 rounded-md transition-colors text-sm font-medium cursor-not-allowed">
                                        <i class="fas fa-times-circle mr-1"></i> Indisponible
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="mt-8 flex justify-center" data-aos="fade-up">
                        <nav class="inline-flex rounded-md shadow-sm -space-x-px">
                            <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search_term); ?>&category=<?php echo $category_id; ?>&min_price=<?php echo $min_price; ?>&max_price=<?php echo $max_price; ?>&sort_by=<?php echo $sort_by; ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                                <span class="sr-only">Précédent</span>
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <?php else: ?>
                            <span class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-800 text-sm font-medium text-gray-400 dark:text-gray-500 cursor-not-allowed">
                                <span class="sr-only">Précédent</span>
                                <i class="fas fa-chevron-left"></i>
                            </span>
                            <?php endif; ?>
                            
                            <?php
                            // Afficher un nombre limité de pages autour de la page actuelle
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            if ($start_page > 1) {
                                echo '<a href="?page=1&search=' . urlencode($search_term) . '&category=' . $category_id . '&min_price=' . $min_price . '&max_price=' . $max_price . '&sort_by=' . $sort_by . '" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">1</a>';
                                if ($start_page > 2) {
                                    echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300">...</span>';
                                }
                            }
                            
                            for ($i = $start_page; $i <= $end_page; $i++) {
                                if ($i == $page) {
                                    echo '<span aria-current="page" class="relative inline-flex items-center px-4 py-2 border border-netblue-500 bg-netblue-50 dark:bg-netblue-900 text-sm font-medium text-netblue-600 dark:text-netblue-300">' . $i . '</span>';
                                } else {
                                    echo '<a href="?page=' . $i . '&search=' . urlencode($search_term) . '&category=' . $category_id . '&min_price=' . $min_price . '&max_price=' . $max_price . '&sort_by=' . $sort_by . '" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">' . $i . '</a>';
                                }
                            }
                            
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300">...</span>';
                                }
                                echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search_term) . '&category=' . $category_id . '&min_price=' . $min_price . '&max_price=' . $max_price . '&sort_by=' . $sort_by . '" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">' . $total_pages . '</a>';
                            }
                            ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search_term); ?>&category=<?php echo $category_id; ?>&min_price=<?php echo $min_price; ?>&max_price=<?php echo $max_price; ?>&sort_by=<?php echo $sort_by; ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                                <span class="sr-only">Suivant</span>
                                <i class="fas fa-chevron-right"></i>
                            </a>
                            <?php else: ?>
                            <span class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-800 text-sm font-medium text-gray-400 dark:text-gray-500 cursor-not-allowed">
                                <span class="sr-only">Suivant</span>
                                <i class="fas fa-chevron-right"></i>
                            </span>
                            <?php endif; ?>
                        </nav>
                    </div>
                    <?php endif; ?>
                    
                    <?php else: ?>
                    <!-- No Products Found -->
                    <div class="flex flex-col items-center justify-center py-12 bg-white dark:bg-gray-800 rounded-lg shadow-md" data-aos="fade-up">
                        <div class="text-gray-400 text-6xl mb-6">
                            <i class="fas fa-search"></i>
                        </div>
                        <h3 class="text-2xl font-bold mb-2 dark:text-white">Aucun produit trouvé</h3>
                        <p class="text-gray-600 dark:text-gray-400 text-center max-w-md">
                            Nous n'avons pas trouvé de produits correspondant à vos critères. Essayez de modifier vos filtres ou votre recherche.
                        </p>
                        <a href="shop.php" class="mt-6 bg-netblue-600 hover:bg-netblue-700 text-white font-medium py-2 px-6 rounded-lg transition-colors">
                            Voir tous les produits
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Joegol Logistics Banner -->
                    <div class="mt-12 bg-gradient-to-r from-netblue-600 to-netblue-800 rounded-lg shadow-lg overflow-hidden" data-aos="fade-up">
                        <div class="flex flex-col md:flex-row items-center">
                            <div class="md:w-1/3 p-6 md:p-8 flex justify-center">
                                <img src="../image/joegolm.png" alt="Joegol Logistics" class="h-50">
                            </div>
                            <div class="md:w-2/3 p-6 md:p-8 text-white">
                                <h3 class="text-xl md:text-2xl font-bold mb-2" style="color: #db5201;">Livraison rapide avec Joegol</h3>
                                <p class="mb-4">
                                    Nos produits sont expédiés directement de nos fournisseurs vers vous, avec un suivi complet et une logistique optimisée par Joegol.
                                </p>
                                <div class="flex flex-wrap gap-4">
                                    <div class="flex items-center">
                                        <i class="fas fa-shipping-fast mr-2" style="color: #db5201;"></i>
                                        <span>Livraison rapide</span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-globe mr-2" style="color: #db5201;"></i>
                                        <span>Mondial</span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-shield-alt mr-2" style="color: #db5201;"></i>
                                        <span>Sécurisé</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Popular Products Section -->
    <?php if (!empty($popular_products)): ?>
    <section class="py-8 bg-white dark:bg-gray-800">
        <div class="container mx-auto px-4">
            <h2 class="text-2xl font-bold mb-6 dark:text-white">Produits populaires</h2>
            
            <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
                <?php foreach ($popular_products as $product): ?>
                <a href="product.php?id=<?php echo $product['id']; ?>" class="bg-white dark:bg-gray-700 rounded-lg shadow-sm overflow-hidden hover:shadow-md transition-shadow">
                    <div class="h-32 overflow-hidden">
                        <img src="../<?php echo !empty($product['images']) ? htmlspecialchars($product['images'][0]) : '../image/oops.avif'; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="w-full h-full object-cover">
                    </div>
                    <div class="p-3">
                        <h3 class="text-sm font-medium text-gray-800 dark:text-white line-clamp-1"><?php echo htmlspecialchars($product['name']); ?></h3>
                        <p class="text-netblue-600 dark:text-netblue-400 font-bold text-sm mt-1"><?php echo number_format($product['price'], 2); ?> FCFA</p>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Advantages Section -->
    <section class="py-12 bg-gray-100 dark:bg-gray-900">
        <div class="container mx-auto px-4">
            <h2 class="text-2xl font-bold mb-8 text-center dark:text-white">Pourquoi choisir Netcrafter Shop ?</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Advantage 1 -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 text-center" data-aos="fade-up" data-aos-delay="100">
                    <div class="text-netblue-600 dark:text-netblue-400 text-4xl mb-4 flex justify-center">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-3 dark:text-white">Qualité garantie</h3>
                    <p class="text-gray-600 dark:text-gray-300">
                        Tous nos produits sont soigneusement sélectionnés et testés pour garantir une qualité optimale.
                    </p>
                </div>
                
                <!-- Advantage 2 -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 text-center" data-aos="fade-up" data-aos-delay="200">
                    <div class="text-netblue-600 dark:text-netblue-400 text-4xl mb-4 flex justify-center">
                        <i class="fas fa-shipping-fast"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-3 dark:text-white">Livraison rapide</h3>
                    <p class="text-gray-600 dark:text-gray-300">
                        Grâce à notre partenariat avec Joegol, vos commandes sont expédiées rapidement et avec suivi.
                    </p>
                </div>
                
                <!-- Advantage 3 -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 text-center" data-aos="fade-up" data-aos-delay="300">
                    <div class="text-netblue-600 dark:text-netblue-400 text-4xl mb-4 flex justify-center">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-3 dark:text-white">Support client 24/7</h3>
                    <p class="text-gray-600 dark:text-gray-300">
                        Notre équipe de support est disponible à tout moment pour répondre à vos questions et résoudre vos problèmes.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-10 md:py-12">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <!-- About -->
                <div>
                    <div class="flex items-center mb-6">
                        <img src="../image/logo-n.png" alt="Netcrafter Logo" class="h-10 mr-4">
                        <span class="text-xl md:text-2xl font-bold text-netblue-400">NETCRAFTER</span>
                    </div>
                    <p class="text-gray-400 mb-6">
                        Solutions informatiques globales pour les entreprises : développement, équipement et formation professionnelle.
                    </p>
                    <p class="text-gray-400">
                        © 2023 Netcrafter. Tous droits réservés.
                    </p>
                </div>
                
                <!-- Quick Links -->
                <div>
                    <h4 class="text-xl font-bold mb-6">Liens rapides</h4>
                    <ul class="space-y-3">
                        <li><a href="index.html" class="text-gray-400 hover:text-white transition-colors">Accueil</a></li>
                        <li><a href="service.html" class="text-gray-400 hover:text-white transition-colors">Services</a></li>
                        <li><a href="devis.html" class="text-gray-400 hover:text-white transition-colors">Devis</a></li>
                        <li><a href="shop.php" class="text-gray-400 hover:text-white transition-colors">Boutique</a></li>
                        <li><a href="formation.html" class="text-gray-400 hover:text-white transition-colors">Formation</a></li>
                    </ul>
                </div>
                
                <!-- Legal -->
                <div>
                    <h4 class="text-xl font-bold mb-6">Informations légales</h4>
                    <ul class="space-y-3">
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Mentions légales</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Politique de confidentialité</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Conditions générales</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Cookies</a></li>
                    </ul>
                </div>
                
                <!-- Newsletter -->
                <div>
                    <h4 class="text-xl font-bold mb-6">Newsletter</h4>
                    <p class="text-gray-400 mb-4">
                        Restez informé des dernières actualités et offres
                    </p>
                    <form class="mb-4">
                        <div class="flex">
                            <input type="email" placeholder="Votre email" class="px-4 py-2 w-full rounded-l-lg focus:outline-none text-gray-800 dark:bg-gray-700 dark:text-white">
                            <button type="submit" class="bg-netblue-600 px-4 py-2 rounded-r-lg hover:bg-netblue-700 transition-colors">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </form>
                    
                    <!-- Social Media -->
                    <div class="flex space-x-4">
                        <a href="https://www.facebook.com/share/1Y7kHRs16L/" class="text-gray-400 hover:text-white transition-colors">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        
                        <a href="https://www.instagram.com/netcrafter.niger?igsh=NzJ2bzM2aWRnMzho" class="text-gray-400 hover:text-white transition-colors">
                            <i class="fab fa-instagram"></i>
                        </a>
                        
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Back to top button -->
    <button id="back-to-top" class="fixed bottom-6 right-6 bg-netblue-600 dark:bg-netblue-700 text-white w-12 h-12 rounded-full flex items-center justify-center shadow-lg opacity-0 invisible transition-all">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- Cart Added Toast Notification -->
    <div id="cart-toast" class="fixed bottom-4 right-4 bg-green-600 text-white px-6 py-3 rounded-lg shadow-lg transform translate-y-24 opacity-0 transition-all duration-300 flex items-center z-50">
        <i class="fas fa-check-circle mr-3 text-xl"></i>
        <span>Produit ajouté au panier</span>
    </div>

    <!-- Favorites Added Toast Notification -->
    <div id="fav-toast" class="fixed bottom-4 right-4 bg-purple-600 text-white px-6 py-3 rounded-lg shadow-lg transform translate-y-24 opacity-0 transition-all duration-300 flex items-center z-50">
        <i class="fas fa-heart mr-3 text-xl"></i>
        <span>Produit ajouté aux favoris</span>
    </div>

    <!-- Favorites Removed Toast Notification -->
    <div id="fav-removed-toast" class="fixed bottom-4 right-4 bg-gray-700 text-white px-6 py-3 rounded-lg shadow-lg transform translate-y-24 opacity-0 transition-all duration-300 flex items-center z-50">
        <i class="fas fa-heart-broken mr-3 text-xl"></i>
        <span>Produit retiré des favoris</span>
    </div>

    <!-- AOS Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>

    <!-- Main JavaScript -->
    <script>
        // Initialize AOS animation library
        AOS.init({
            duration: 800,
            once: true,
            disable: window.innerWidth < 768 ? true : false
        });

        // Initialize product image sliders
        document.addEventListener('DOMContentLoaded', function() {
            <?php foreach ($products as $product): ?>
            new Swiper('.product-swiper-<?php echo $product['id']; ?>', {
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true,
                },
                loop: <?php echo count($product['images']) > 1 ? 'true' : 'false'; ?>,
                autoplay: {
                    delay: 5000,
                    disableOnInteraction: false,
                },
            });
            <?php endforeach; ?>
            
            // Mobile filter menu toggle
            const filterToggle = document.getElementById('filter-toggle');
            const closeFilterMenu = document.getElementById('close-filter-menu');
            const mobileFilterMenu = document.getElementById('mobile-filter-menu');
            
            if (filterToggle) {
                filterToggle.addEventListener('click', function() {
                    mobileFilterMenu.classList.remove('translate-x-full');
                    document.body.style.overflow = 'hidden'; // Prevent scrolling when menu is open
                });
            }
            
            if (closeFilterMenu) {
                closeFilterMenu.addEventListener('click', function() {
                    mobileFilterMenu.classList.add('translate-x-full');
                    document.body.style.overflow = ''; // Re-enable scrolling
                });
            }
            
            // Price range sliders for desktop
            const minSlider = document.getElementById('min_price_slider');
            const maxSlider = document.getElementById('max_price_slider');
            const minValueDisplay = document.getElementById('min-value');
            const maxValueDisplay = document.getElementById('max-value');
            const minPriceInput = document.getElementById('min_price');
            const maxPriceInput = document.getElementById('max_price');
            
            if (minSlider && maxSlider) {
                // Update the min slider value
                minSlider.addEventListener('input', function() {
                    let minValue = parseFloat(minSlider.value);
                    let maxValue = parseFloat(maxSlider.value);
                    
                    if (minValue > maxValue) {
                        minValue = maxValue;
                        minSlider.value = minValue;
                    }
                    
                    minValueDisplay.textContent = minValue;
                    minPriceInput.value = minValue;
                });
                
                // Update the max slider value
                maxSlider.addEventListener('input', function() {
                    let maxValue = parseFloat(maxSlider.value);
                    let minValue = parseFloat(minSlider.value);
                    
                    if (maxValue < minValue) {
                        maxValue = minValue;
                        maxSlider.value = maxValue;
                    }
                    
                    maxValueDisplay.textContent = maxValue;
                    maxPriceInput.value = maxValue;
                });
            }
            
            // Price range sliders for mobile
            const mobileMinSlider = document.getElementById('mobile_min_price_slider');
            const mobileMaxSlider = document.getElementById('mobile_max_price_slider');
            const mobileMinValueDisplay = document.getElementById('mobile-min-value');
            const mobileMaxValueDisplay = document.getElementById('mobile-max-value');
            const mobileMinPriceInput = document.getElementById('mobile_min_price');
            const mobileMaxPriceInput = document.getElementById('mobile_max_price');
            
            if (mobileMinSlider && mobileMaxSlider) {
                // Update the min slider value
                mobileMinSlider.addEventListener('input', function() {
                    let minValue = parseFloat(mobileMinSlider.value);
                    let maxValue = parseFloat(mobileMaxSlider.value);
                    
                    if (minValue > maxValue) {
                        minValue = maxValue;
                        mobileMinSlider.value = minValue;
                    }
                    
                    mobileMinValueDisplay.textContent = minValue;
                    mobileMinPriceInput.value = minValue;
                });
                
                // Update the max slider value
                mobileMaxSlider.addEventListener('input', function() {
                    let maxValue = parseFloat(mobileMaxSlider.value);
                    let minValue = parseFloat(mobileMinSlider.value);
                    
                    if (maxValue < minValue) {
                        maxValue = minValue;
                        mobileMaxSlider.value = maxValue;
                    }
                    
                    mobileMaxValueDisplay.textContent = maxValue;
                    mobileMaxPriceInput.value = maxValue;
                });
            }
            
            // Show toast notifications
            <?php if (isset($_GET['cart_added'])): ?>
            showToast('cart-toast');
            <?php endif; ?>
            
            <?php if (isset($_GET['fav_added'])): ?>
            showToast('fav-toast');
            <?php endif; ?>
            
            <?php if (isset($_GET['fav_removed'])): ?>
            showToast('fav-removed-toast');
            <?php endif; ?>
            
            // Dark mode toggle
            const darkModeToggle = document.getElementById('darkModeToggle');
            const darkModeToggleMobile = document.getElementById('darkModeToggleMobile');
            const htmlElement = document.documentElement;
            
            // Check for saved theme preference
            if (localStorage.getItem('darkMode') === 'enabled') {
                htmlElement.classList.add('dark');
                darkModeToggle.checked = true;
                darkModeToggleMobile.checked = true;
            }
            
            // Function to toggle dark mode
            function toggleDarkMode() {
                if (htmlElement.classList.contains('dark')) {
                    htmlElement.classList.remove('dark');
                    localStorage.setItem('darkMode', 'disabled');
                    darkModeToggle.checked = false;
                    darkModeToggleMobile.checked = false;
                } else {
                    htmlElement.classList.add('dark');
                    localStorage.setItem('darkMode', 'enabled');
                    darkModeToggle.checked = true;
                    darkModeToggleMobile.checked = true;
                }
            }
            
            // Event listeners for toggle switches
            darkModeToggle.addEventListener('change', toggleDarkMode);
            darkModeToggleMobile.addEventListener('change', toggleDarkMode);
            
            // Mobile menu toggle
            document.getElementById('menu-toggle').addEventListener('click', function() {
                const mobileMenu = document.getElementById('mobile-menu');
                mobileMenu.classList.toggle('hidden');
            });
            
            // Back to top button
            const backToTopButton = document.getElementById('back-to-top');
            
            window.addEventListener('scroll', function() {
                if (window.scrollY > 300) {
                    backToTopButton.classList.remove('opacity-0', 'invisible');
                    backToTopButton.classList.add('opacity-100', 'visible');
                } else {
                    backToTopButton.classList.remove('opacity-100', 'visible');
                    backToTopButton.classList.add('opacity-0', 'invisible');
                }
            });
            
            backToTopButton.addEventListener('click', function() {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
        });
        
        // Toast notification function
        function showToast(id) {
            const toast = document.getElementById(id);
            if (toast) {
                toast.classList.remove('translate-y-24', 'opacity-0');
                toast.classList.add('translate-y-0', 'opacity-100');
                
                setTimeout(() => {
                    toast.classList.remove('translate-y-0', 'opacity-100');
                    toast.classList.add('translate-y-24', 'opacity-0');
                }, 3000);
            }
        }
    </script>
</body>
</html>