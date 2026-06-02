<?php
// Initialisation de la session pour les favoris et l'authentification
session_start();

require_once __DIR__ . '/../db.php';

// Connexion à la base de données
$conn = new mysqli($servername, $username, $password, $dbname);

// Vérification de la connexion
if ($conn->connect_error) {
    die("Échec de la connexion: " . $conn->connect_error);
}
$conn->set_charset("utf8");

// Vérifier si l'utilisateur est connecté
$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$user_id = $is_logged_in ? $_SESSION['user_id'] : 0;

// Initialisation des favoris s'ils n'existent pas
if (!isset($_SESSION['formation_favorites'])) {
    $_SESSION['formation_favorites'] = [];
    
    // Si l'utilisateur est connecté, charger ses favoris depuis la base de données
    if ($is_logged_in) {
        $favorites_query = "SELECT formation_id FROM formation_favorites WHERE user_id = ?";
        $stmt = $conn->prepare($favorites_query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $_SESSION['formation_favorites'][] = $row['formation_id'];
        }
    }
}

// Traitement des actions (ajout/suppression des favoris)
if (isset($_POST['action']) && isset($_POST['formation_id'])) {
    $formation_id = intval($_POST['formation_id']);
    
    if ($_POST['action'] === 'add_to_favorites') {
        // Ajouter aux favoris
        if (!in_array($formation_id, $_SESSION['formation_favorites'])) {
            $_SESSION['formation_favorites'][] = $formation_id;
            
            // Si l'utilisateur est connecté, enregistrer dans la base de données
            if ($is_logged_in) {
                $add_favorite_query = "INSERT IGNORE INTO formation_favorites (user_id, formation_id) VALUES (?, ?)";
                $stmt = $conn->prepare($add_favorite_query);
                $stmt->bind_param("ii", $user_id, $formation_id);
                $stmt->execute();
            }
        }
        header("Location: formations.php?fav_added=1");
        exit;
    } 
    elseif ($_POST['action'] === 'remove_from_favorites') {
        // Supprimer des favoris
        if (($key = array_search($formation_id, $_SESSION['formation_favorites'])) !== false) {
            unset($_SESSION['formation_favorites'][$key]);
            $_SESSION['formation_favorites'] = array_values($_SESSION['formation_favorites']); // Réindexer le tableau
            
            // Si l'utilisateur est connecté, supprimer de la base de données
            if ($is_logged_in) {
                $remove_favorite_query = "DELETE FROM formation_favorites WHERE user_id = ? AND formation_id = ?";
                $stmt = $conn->prepare($remove_favorite_query);
                $stmt->bind_param("ii", $user_id, $formation_id);
                $stmt->execute();
            }
        }
        header("Location: formations.php?fav_removed=1");
        exit;
    }
}

// Récupération des catégories de formation
$categories_query = "SELECT * FROM formation_categories WHERE status = 'active' ORDER BY name";
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
$level = isset($_GET['level']) ? $_GET['level'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'newest';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$formations_per_page = 9; // Nombre de formations par page

// Construction de la requête SQL de base
$sql = "SELECT f.*, c.name as category_name, c.icon as category_icon 
        FROM formations f 
        JOIN formation_categories c ON f.category_id = c.id 
        WHERE f.status = 'active'";
$params = [];
$types = "";

// Ajout des conditions de filtrage
if ($category_id > 0) {
    $sql .= " AND f.category_id = ?";
    $params[] = $category_id;
    $types .= "i"; // integer
}

if (!empty($search_term)) {
    $sql .= " AND (f.title LIKE CONCAT('%', ?, '%') OR f.description LIKE CONCAT('%', ?, '%'))";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss"; // string, string
}

if (!empty($level)) {
    $sql .= " AND f.level = ?";
    $params[] = $level;
    $types .= "s"; // string
}

// Comptage du nombre total de formations pour la pagination
$count_sql = preg_replace('/SELECT f\.\*, c\.name as category_name, c\.icon as category_icon/i', 'SELECT COUNT(*) as total', $sql);
$stmt = $conn->prepare($count_sql);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$count_result = $stmt->get_result();
$total_formations = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_formations / $formations_per_page);

// Tri des résultats
switch ($sort_by) {
    case 'price_low':
        $sql .= " ORDER BY f.price_per_month ASC";
        break;
    case 'price_high':
        $sql .= " ORDER BY f.price_per_month DESC";
        break;
    case 'alphabetical':
        $sql .= " ORDER BY f.title ASC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY f.created_at DESC";
        break;
}

// Ajout de la pagination
$offset = ($page - 1) * $formations_per_page;
$sql .= " LIMIT ?, ?";
$params[] = $offset;
$params[] = $formations_per_page;
$types .= "ii"; // integer, integer

// Exécution de la requête
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$formations = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Si l'image de couverture n'est pas définie, utiliser une image par défaut
        if (empty($row['cover_image'])) {
            $row['cover_image'] = "image/formations/default-" . rand(1, 3) . ".jpg";
        }
        
        // Compter le nombre de modules pour cette formation
        $modules_query = "SELECT COUNT(*) AS module_count FROM formation_modules WHERE formation_id = ?";
        $mod_stmt = $conn->prepare($modules_query);
        $mod_stmt->bind_param("i", $row['id']);
        $mod_stmt->execute();
        $mod_result = $mod_stmt->get_result();
        $row['module_count'] = $mod_result->fetch_assoc()['module_count'];
        
        $formations[] = $row;
    }
}

// Récupération des formations populaires (basées sur le nombre d'abonnements)
$popular_formations_query = "SELECT f.*, c.name as category_name, c.icon as category_icon,
                            COUNT(fs.id) as subscription_count
                            FROM formations f
                            JOIN formation_categories c ON f.category_id = c.id
                            LEFT JOIN formation_subscriptions fs ON f.id = fs.formation_id
                            WHERE f.status = 'active'
                            GROUP BY f.id
                            ORDER BY subscription_count DESC, f.created_at DESC
                            LIMIT 6";
$popular_result = $conn->query($popular_formations_query);
$popular_formations = [];

if ($popular_result && $popular_result->num_rows > 0) {
    while ($row = $popular_result->fetch_assoc()) {
        // Si l'image de couverture n'est pas définie, utiliser une image par défaut
        if (empty($row['cover_image'])) {
            $row['cover_image'] = "image/formations/default-" . rand(1, 3) . ".jpg";
        }
        
        $popular_formations[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr" class="light scroll">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Netcrafter Formations - Plateforme d'Apprentissage</title>
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
        
        .formation-card {
            transition: all 0.3s ease;
        }
        
        .formation-card:hover {
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
                    <a href="index.html" class="hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors">Accueil</a>
                    <a href="service.html" class="hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors">Services</a>
                    <a href="devis.html" class="hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors">Devis</a>
                    <a href="shop.php" class="hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors">Boutique</a>
                    <a href="formations.php" class="text-netblue-600 dark:text-netblue-400 font-medium">Formation</a>
                </div>
                
                <!-- Favorites and Account -->
                <div class="flex items-center space-x-4 mr-4">
                    <a href="formation-favorites.php" class="relative text-gray-700 dark:text-gray-300 hover:text-netblue-600 dark:hover:text-netblue-400" title="Mes formations favorites">
                        <i class="fas fa-heart text-xl"></i>
                        <?php if (count($_SESSION['formation_favorites']) > 0): ?>
                        <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                            <?php echo count($_SESSION['formation_favorites']); ?>
                        </span>
                        <?php endif; ?>
                    </a>
                    <?php if ($is_logged_in): ?>
                    <div class="relative group">
                        <button class="flex items-center text-gray-700 dark:text-gray-300 hover:text-netblue-600 dark:hover:text-netblue-400">
                            <i class="fas fa-user-circle text-xl mr-2"></i>
                            <span>Mon Compte</span>
                            <i class="fas fa-chevron-down ml-1 text-xs"></i>
                        </button>
                        <div class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg py-2 z-10 hidden group-hover:block">
                            <a href="dashboard.php" class="block px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-netblue-100 dark:hover:bg-gray-700">
                                <i class="fas fa-tachometer-alt mr-2"></i>Tableau de bord
                            </a>
                            <a href="my-formations.php" class="block px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-netblue-100 dark:hover:bg-gray-700">
                                <i class="fas fa-graduation-cap mr-2"></i>Mes formations
                            </a>
                            <a href="certificates.php" class="block px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-netblue-100 dark:hover:bg-gray-700">
                                <i class="fas fa-certificate mr-2"></i>Mes certificats
                            </a>
                            <a href="profile.php" class="block px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-netblue-100 dark:hover:bg-gray-700">
                                <i class="fas fa-user-edit mr-2"></i>Modifier profil
                            </a>
                            <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>
                            <a href="logout.php" class="block px-4 py-2 text-red-600 hover:bg-red-100 dark:hover:bg-red-900">
                                <i class="fas fa-sign-out-alt mr-2"></i>Déconnexion
                            </a>
                        </div>
                    </div>
                    <?php else: ?>
                    <a href="login.php" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-sign-in-alt mr-1"></i>Connexion
                    </a>
                    <?php endif; ?>
                </div>
                
                <!-- Dark Mode Toggle -->
                <div class="flex items-center">
                    <label class="theme-switch relative inline-block w-14 h-7">
                        <input type="checkbox" id="darkModeToggle" class="opacity-0 w-0 h-0">
                        <span class="slider absolute cursor-pointer inset-0 bg-gray-300 rounded-full transition-all duration-300 before:absolute before:h-5 before:w-5 before:left-1 before:bottom-1 before:bg-white before:rounded-full before:transition-all before:duration-300"></span>
                    </label>
                </div>
            </div>
            
            <!-- Mobile Menu Button and Dark Mode Toggle -->
            <div class="md:hidden flex items-center space-x-4">
                <a href="formation-favorites.php" class="relative text-gray-700 dark:text-gray-300">
                    <i class="fas fa-heart text-xl"></i>
                    <?php if (count($_SESSION['formation_favorites']) > 0): ?>
                    <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                        <?php echo count($_SESSION['formation_favorites']); ?>
                    </span>
                    <?php endif; ?>
                </a>
                
                <?php if ($is_logged_in): ?>
                <a href="dashboard.php" class="text-gray-700 dark:text-gray-300">
                    <i class="fas fa-user-circle text-xl"></i>
                </a>
                <?php else: ?>
                <a href="login.php" class="text-gray-700 dark:text-gray-300">
                    <i class="fas fa-sign-in-alt text-xl"></i>
                </a>
                <?php endif; ?>
                
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
                <a href="index.html" class="block py-2 hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors dark:text-gray-300">Accueil</a>
                <a href="service.html" class="block py-2 hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors dark:text-gray-300">Services</a>
                <a href="devis.html" class="block py-2 hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors dark:text-gray-300">Devis</a>
                <a href="shop.php" class="block py-2 hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors dark:text-gray-300">Boutique</a>
                <a href="formations.php" class="block py-2 text-netblue-600 dark:text-netblue-400 font-medium">Formation</a>
                
                <?php if ($is_logged_in): ?>
                <div class="border-t border-gray-200 dark:border-gray-700 pt-2 mt-2">
                    <a href="dashboard.php" class="block py-2 hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors dark:text-gray-300">
                        <i class="fas fa-tachometer-alt mr-2"></i>Tableau de bord
                    </a>
                    <a href="my-formations.php" class="block py-2 hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors dark:text-gray-300">
                        <i class="fas fa-graduation-cap mr-2"></i>Mes formations
                    </a>
                    <a href="certificates.php" class="block py-2 hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors dark:text-gray-300">
                        <i class="fas fa-certificate mr-2"></i>Mes certificats
                    </a>
                    <a href="profile.php" class="block py-2 hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors dark:text-gray-300">
                        <i class="fas fa-user-edit mr-2"></i>Modifier profil
                    </a>
                    <a href="logout.php" class="block py-2 text-red-600">
                        <i class="fas fa-sign-out-alt mr-2"></i>Déconnexion
                    </a>
                </div>
                <?php endif; ?>
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
                    Centre de Formation Netcrafter
                </h1>
                <p class="text-base md:text-lg max-w-3xl mx-auto animate__animated animate__fadeInUp animate__delay-1s">
                    Développez vos compétences avec nos formations professionnelles en informatique, réseaux, design et plus encore
                </p>
            </div>
            
            <!-- Search Bar -->
            <div class="max-w-3xl mx-auto bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 animate__animated animate__fadeInUp animate__delay-2s">
                <form action="formations.php" method="GET" class="flex flex-col md:flex-row gap-3">
                    <div class="flex-1 relative">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Rechercher une formation..." class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
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

    <!-- Avantages de la Formation -->
    <section class="py-8 bg-white dark:bg-gray-800">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <!-- Avantage 1 -->
                <div class="text-center" data-aos="fade-up" data-aos-delay="100">
                    <div class="bg-netblue-100 dark:bg-netblue-900 text-netblue-600 dark:text-netblue-300 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-laptop-code text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-bold mb-2 dark:text-white">Formations Professionnelles</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Contenu de qualité conçu par des experts du domaine</p>
                </div>
                
                <!-- Avantage 2 -->
                <div class="text-center" data-aos="fade-up" data-aos-delay="200">
                    <div class="bg-netblue-100 dark:bg-netblue-900 text-netblue-600 dark:text-netblue-300 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-clock text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-bold mb-2 dark:text-white">À Votre Rythme</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Apprenez quand vous voulez, où vous voulez</p>
                </div>
                
                <!-- Avantage 3 -->
                <div class="text-center" data-aos="fade-up" data-aos-delay="300">
                    <div class="bg-netblue-100 dark:bg-netblue-900 text-netblue-600 dark:text-netblue-300 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-certificate text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-bold mb-2 dark:text-white">Certificats Validés</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Obtenez une attestation à la fin de votre parcours</p>
                </div>
                
                <!-- Avantage 4 -->
                <div class="text-center" data-aos="fade-up" data-aos-delay="400">
                    <div class="bg-netblue-100 dark:bg-netblue-900 text-netblue-600 dark:text-netblue-300 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-comments text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-bold mb-2 dark:text-white">Forum Communautaire</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Échangez avec des formateurs et d'autres apprenants</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Formations Section -->
    <section class="py-8 md:py-12 bg-gray-50 dark:bg-gray-900">
        <div class="container mx-auto px-4">
            <div class="flex flex-col lg:flex-row gap-8">
                <!-- Filter Sidebar - Desktop -->
                <div class="lg:w-1/4 hidden lg:block">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 sticky top-24" data-aos="fade-right">
                        <h3 class="text-xl font-bold mb-6 dark:text-white">Filtres</h3>
                        
                        <form action="formations.php" method="GET" id="filter-form">
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
                                        <label for="cat_<?php echo $category['id']; ?>" class="ml-2 text-gray-700 dark:text-gray-300">
                                            <i class="fas <?php echo htmlspecialchars($category['icon']); ?> mr-1"></i>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Niveau -->
                            <div class="mb-6">
                                <h4 class="font-semibold mb-3 dark:text-white">Niveau</h4>
                                <div class="space-y-2">
                                    <div class="flex items-center">
                                        <input type="radio" id="level_all" name="level" value="" <?php echo empty($level) ? 'checked' : ''; ?> class="h-4 w-4 text-netblue-600 focus:ring-netblue-500 border-gray-300 rounded">
                                        <label for="level_all" class="ml-2 text-gray-700 dark:text-gray-300">Tous les niveaux</label>
                                    </div>
                                    <div class="flex items-center">
                                        <input type="radio" id="level_debutant" name="level" value="debutant" <?php echo $level == 'debutant' ? 'checked' : ''; ?> class="h-4 w-4 text-netblue-600 focus:ring-netblue-500 border-gray-300 rounded">
                                        <label for="level_debutant" class="ml-2 text-gray-700 dark:text-gray-300">Débutant</label>
                                    </div>
                                    <div class="flex items-center">
                                        <input type="radio" id="level_intermediaire" name="level" value="intermediaire" <?php echo $level == 'intermediaire' ? 'checked' : ''; ?> class="h-4 w-4 text-netblue-600 focus:ring-netblue-500 border-gray-300 rounded">
                                        <label for="level_intermediaire" class="ml-2 text-gray-700 dark:text-gray-300">Intermédiaire</label>
                                    </div>
                                    <div class="flex items-center">
                                        <input type="radio" id="level_avance" name="level" value="avance" <?php echo $level == 'avance' ? 'checked' : ''; ?> class="h-4 w-4 text-netblue-600 focus:ring-netblue-500 border-gray-300 rounded">
                                        <label for="level_avance" class="ml-2 text-gray-700 dark:text-gray-300">Avancé</label>
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
                                    <option value="alphabetical" <?php echo $sort_by == 'alphabetical' ? 'selected' : ''; ?>>Alphabétique</option>
                                </select>
                            </div>
                            
                            <!-- Apply Filters -->
                            <button type="submit" class="w-full bg-netblue-600 hover:bg-netblue-700 text-white font-medium py-2 px-4 rounded-md transition-colors">
                                Appliquer les filtres
                            </button>
                            
                            <!-- Reset Filters -->
                            <a href="formations.php" class="w-full mt-3 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-700 dark:text-white font-medium py-2 px-4 rounded-md transition-colors text-center block">
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
                        
                        <form action="formations.php" method="GET">
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
                                        <label for="mobile_cat_<?php echo $category['id']; ?>" class="ml-3 text-white">
                                            <i class="fas <?php echo htmlspecialchars($category['icon']); ?> mr-1"></i>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Niveau -->
                            <div class="mb-8">
                                <h4 class="text-lg font-semibold mb-4 text-white">Niveau</h4>
                                <div class="space-y-3">
                                    <div class="flex items-center">
                                        <input type="radio" id="mobile_level_all" name="level" value="" <?php echo empty($level) ? 'checked' : ''; ?> class="h-5 w-5 text-netblue-600 focus:ring-netblue-500 border-gray-300 rounded">
                                        <label for="mobile_level_all" class="ml-3 text-white">Tous les niveaux</label>
                                    </div>
                                    <div class="flex items-center">
                                        <input type="radio" id="mobile_level_debutant" name="level" value="debutant" <?php echo $level == 'debutant' ? 'checked' : ''; ?> class="h-5 w-5 text-netblue-600 focus:ring-netblue-500 border-gray-300 rounded">
                                        <label for="mobile_level_debutant" class="ml-3 text-white">Débutant</label>
                                    </div>
                                    <div class="flex items-center">
                                        <input type="radio" id="mobile_level_intermediaire" name="level" value="intermediaire" <?php echo $level == 'intermediaire' ? 'checked' : ''; ?> class="h-5 w-5 text-netblue-600 focus:ring-netblue-500 border-gray-300 rounded">
                                        <label for="mobile_level_intermediaire" class="ml-3 text-white">Intermédiaire</label>
                                    </div>
                                    <div class="flex items-center">
                                        <input type="radio" id="mobile_level_avance" name="level" value="avance" <?php echo $level == 'avance' ? 'checked' : ''; ?> class="h-5 w-5 text-netblue-600 focus:ring-netblue-500 border-gray-300 rounded">
                                        <label for="mobile_level_avance" class="ml-3 text-white">Avancé</label>
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
                                    <option value="alphabetical" <?php echo $sort_by == 'alphabetical' ? 'selected' : ''; ?>>Alphabétique</option>
                                </select>
                            </div>
                            
                            <!-- Apply Filters -->
                            <button type="submit" class="w-full bg-netblue-600 hover:bg-netblue-700 text-white font-bold py-3 px-4 rounded-lg transition-colors text-lg mb-4">
                                Appliquer les filtres
                            </button>
                            
                            <!-- Reset Filters -->
                            <a href="formations.php" class="w-full bg-gray-700 hover:bg-gray-600 text-white font-bold py-3 px-4 rounded-lg transition-colors text-lg text-center block">
                                Réinitialiser les filtres
                            </a>
                        </form>
                    </div>
                </div>
                
                <!-- Formations Grid -->
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
                                    Toutes nos formations
                                <?php endif; ?>
                            </h2>
                            <p class="text-gray-600 dark:text-gray-400 mt-1">
                                <?php echo $total_formations; ?> formations trouvées
                            </p>
                        </div>
                        
                        <!-- Sort By - Mobile & Tablet -->
                        <div class="mt-4 sm:mt-0 self-stretch sm:self-auto w-full sm:w-auto lg:hidden">
                            <form action="formations.php" method="GET" class="flex">
                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_term); ?>">
                                <input type="hidden" name="category" value="<?php echo $category_id; ?>">
                                <input type="hidden" name="level" value="<?php echo $level; ?>">
                                
                                <select name="sort_by" onchange="this.form.submit()" class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 bg-white dark:bg-gray-700 text-gray-800 dark:text-white">
                                    <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Trier par : Plus récents</option>
                                    <option value="price_low" <?php echo $sort_by == 'price_low' ? 'selected' : ''; ?>>Trier par : Prix croissant</option>
                                    <option value="price_high" <?php echo $sort_by == 'price_high' ? 'selected' : ''; ?>>Trier par : Prix décroissant</option>
                                    <option value="alphabetical" <?php echo $sort_by == 'alphabetical' ? 'selected' : ''; ?>>Trier par : Alphabétique</option>
                                </select>
                            </form>
                        </div>
                    </div>
                    
                    <?php if (count($formations) > 0): ?>
                    <!-- Formations Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($formations as $formation): ?>
                        <div class="formation-card bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden" data-aos="fade-up">
                            <!-- Formation Image -->
                            <div class="relative h-48">
                                <img src="../<?php echo htmlspecialchars($formation['cover_image']); ?>" alt="<?php echo htmlspecialchars($formation['title']); ?>" class="w-full h-full object-cover">
                                
                                <!-- Category Badge -->
                                <div class="absolute top-3 left-3 bg-netblue-600 text-white text-xs font-bold px-2 py-1 rounded">
                                    <i class="fas <?php echo htmlspecialchars($formation['category_icon']); ?> mr-1"></i>
                                    <?php echo htmlspecialchars($formation['category_name']); ?>
                                </div>
                                
                                <!-- Favorite Button -->
                                <form method="POST" class="absolute top-3 right-3">
                                    <input type="hidden" name="formation_id" value="<?php echo $formation['id']; ?>">
                                    <?php if (in_array($formation['id'], $_SESSION['formation_favorites'])): ?>
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
                                
                                <!-- Level Badge -->
                                <div class="absolute bottom-3 left-3 text-white text-xs font-bold px-2 py-1 rounded
                                    <?php 
                                    switch($formation['level']) {
                                        case 'debutant':
                                            echo 'bg-green-600';
                                            break;
                                        case 'intermediaire':
                                            echo 'bg-yellow-600';
                                            break;
                                        case 'avance':
                                            echo 'bg-red-600';
                                            break;
                                    }
                                    ?>">
                                    <?php 
                                    switch($formation['level']) {
                                        case 'debutant':
                                            echo 'Débutant';
                                            break;
                                        case 'intermediaire':
                                            echo 'Intermédiaire';
                                            break;
                                        case 'avance':
                                            echo 'Avancé';
                                            break;
                                    }
                                    ?>
                                </div>
                            </div>
                            
                            <!-- Formation Details -->
                            <div class="p-4">
                                <h3 class="text-lg font-bold mb-2 text-gray-800 dark:text-white line-clamp-2">
                                    <?php echo htmlspecialchars($formation['title']); ?>
                                </h3>
                                
                                <div class="flex items-center mb-3 text-sm text-gray-500 dark:text-gray-400">
                                    <div class="flex items-center mr-4">
                                        <i class="fas fa-clock mr-1"></i>
                                        <span><?php echo !empty($formation['duration']) ? htmlspecialchars($formation['duration']) : 'Durée variable'; ?></span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-layer-group mr-1"></i>
                                        <span><?php echo $formation['module_count']; ?> modules</span>
                                    </div>
                                </div>
                                
                                <p class="text-gray-600 dark:text-gray-300 text-sm mb-4 line-clamp-2">
                                    <?php 
                                    if (!empty($formation['short_description'])) {
                                        echo htmlspecialchars($formation['short_description']);
                                    } else {
                                        echo htmlspecialchars(substr($formation['description'], 0, 100)) . (strlen($formation['description']) > 100 ? '...' : '');
                                    }
                                    ?>
                                </p>
                                
                                <div class="flex justify-between items-center">
                                    <div class="text-netblue-600 dark:text-netblue-400 font-bold">
                                        <?php echo number_format($formation['price_per_month'], 0, ',', ' '); ?> FCFA<span class="text-sm font-normal">/mois</span>
                                    </div>
                                    <a href="formation-details.php?id=<?php echo $formation['id']; ?>" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded text-sm font-medium transition-colors">
                                        Découvrir
                                    </a>
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
                            <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search_term); ?>&category=<?php echo $category_id; ?>&level=<?php echo $level; ?>&sort_by=<?php echo $sort_by; ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
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
                                echo '<a href="?page=1&search=' . urlencode($search_term) . '&category=' . $category_id . '&level=' . $level . '&sort_by=' . $sort_by . '" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">1</a>';
                                if ($start_page > 2) {
                                    echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300">...</span>';
                                }
                            }
                            
                            for ($i = $start_page; $i <= $end_page; $i++) {
                                if ($i == $page) {
                                    echo '<span aria-current="page" class="relative inline-flex items-center px-4 py-2 border border-netblue-500 bg-netblue-50 dark:bg-netblue-900 text-sm font-medium text-netblue-600 dark:text-netblue-300">' . $i . '</span>';
                                } else {
                                    echo '<a href="?page=' . $i . '&search=' . urlencode($search_term) . '&category=' . $category_id . '&level=' . $level . '&sort_by=' . $sort_by . '" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">' . $i . '</a>';
                                }
                            }
                            
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300">...</span>';
                                }
                                echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search_term) . '&category=' . $category_id . '&level=' . $level . '&sort_by=' . $sort_by . '" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">' . $total_pages . '</a>';
                            }
                            ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search_term); ?>&category=<?php echo $category_id; ?>&level=<?php echo $level; ?>&sort_by=<?php echo $sort_by; ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
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
                    <!-- No Formations Found -->
                    <div class="flex flex-col items-center justify-center py-12 bg-white dark:bg-gray-800 rounded-lg shadow-md" data-aos="fade-up">
                        <div class="text-gray-400 text-6xl mb-6">
                            <i class="fas fa-search"></i>
                        </div>
                        <h3 class="text-2xl font-bold mb-2 dark:text-white">Aucune formation trouvée</h3>
                        <p class="text-gray-600 dark:text-gray-400 text-center max-w-md">
                            Nous n'avons pas trouvé de formations correspondant à vos critères. Essayez de modifier vos filtres ou votre recherche.
                        </p>
                        <a href="formations.php" class="mt-6 bg-netblue-600 hover:bg-netblue-700 text-white font-medium py-2 px-6 rounded-lg transition-colors">
                            Voir toutes les formations
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <!-- CTA Banner -->
                    <div class="mt-12 bg-gradient-to-r from-netblue-600 to-netblue-800 rounded-lg shadow-lg overflow-hidden" data-aos="fade-up">
                        <div class="flex flex-col md:flex-row">
                            <div class="md:w-2/3 p-6 md:p-8 text-white">
                                <h3 class="text-xl md:text-2xl font-bold mb-3">Vous ne trouvez pas votre formation idéale ?</h3>
                                <p class="mb-6">
                                    Contactez-nous pour discuter de vos besoins spécifiques. Nous pouvons créer des parcours de formation personnalisés pour les individus et les entreprises.
                                </p>
                                <a href="contact.php" class="inline-block bg-white text-netblue-600 font-bold py-2 px-6 rounded-lg hover:bg-netblue-50 transition-colors">
                                    Nous contacter
                                </a>
                            </div>
                            <div class="md:w-1/3 flex items-center justify-center p-6 md:p-8">
                                <div class="text-white text-7xl opacity-80">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Popular Formations Section -->
    <?php if (!empty($popular_formations)): ?>
    <section class="py-8 bg-white dark:bg-gray-800">
        <div class="container mx-auto px-4">
            <h2 class="text-2xl font-bold mb-6 dark:text-white">Formations populaires</h2>
            
            <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
                <?php foreach ($popular_formations as $formation): ?>
                <a href="formation-details.php?id=<?php echo $formation['id']; ?>" class="bg-white dark:bg-gray-700 rounded-lg shadow-sm overflow-hidden hover:shadow-md transition-shadow">
                    <div class="h-32 overflow-hidden">
                        <img src="../<?php echo htmlspecialchars($formation['cover_image']); ?>" alt="<?php echo htmlspecialchars($formation['title']); ?>" class="w-full h-full object-cover">
                    </div>
                    <div class="p-3">
                        <h3 class="text-sm font-medium text-gray-800 dark:text-white line-clamp-2"><?php echo htmlspecialchars($formation['title']); ?></h3>
                        <div class="flex justify-between items-center mt-2">
                            <span class="text-netblue-600 dark:text-netblue-400 font-bold text-sm"><?php echo number_format($formation['price_per_month'], 0, ',', ' '); ?> FCFA</span>
                            <span class="text-xs px-2 py-1 rounded
                                <?php 
                                switch($formation['level']) {
                                    case 'debutant':
                                        echo 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
                                        break;
                                    case 'intermediaire':
                                        echo 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300';
                                        break;
                                    case 'avance':
                                        echo 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300';
                                        break;
                                }
                                ?>">
                                <?php 
                                switch($formation['level']) {
                                    case 'debutant':
                                        echo 'Débutant';
                                        break;
                                    case 'intermediaire':
                                        echo 'Intermédiaire';
                                        break;
                                    case 'avance':
                                        echo 'Avancé';
                                        break;
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Testimonials Section -->
    <section class="py-12 bg-gray-100 dark:bg-gray-900">
        <div class="container mx-auto px-4">
            <h2 class="text-2xl font-bold mb-8 text-center dark:text-white">Ce que disent nos apprenants</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Testimonial 1 -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="flex items-center mb-4">
                        <div class="text-yellow-400 mr-2">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <span class="text-gray-600 dark:text-gray-400 text-sm">5/5</span>
                    </div>
                    <p class="text-gray-600 dark:text-gray-300 mb-6 italic">
                        "La formation en réseau informatique m'a permis d'acquérir des compétences très recherchées sur le marché. Les vidéos sont claires et le forum m'a aidé à résoudre mes problèmes. Je recommande vivement !"
                    </p>
                    <div class="flex items-center">
                        <div class="bg-gray-300 dark:bg-gray-600 w-10 h-10 rounded-full flex items-center justify-center text-gray-600 dark:text-gray-300 mr-3">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <h4 class="font-bold text-gray-800 dark:text-white">Amadou D.</h4>
                            <p class="text-gray-500 dark:text-gray-400 text-sm">Formation Réseaux Avancés</p>
                        </div>
                    </div>
                </div>
                
                <!-- Testimonial 2 -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="flex items-center mb-4">
                        <div class="text-yellow-400 mr-2">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star-half-alt"></i>
                        </div>
                        <span class="text-gray-600 dark:text-gray-400 text-sm">4.5/5</span>
                    </div>
                    <p class="text-gray-600 dark:text-gray-300 mb-6 italic">
                        "J'ai pu créer ma boutique en ligne grâce à la formation e-commerce. Tout est expliqué pas à pas et le certificat m'a aidé à gagner la confiance de mes clients. Excellente plateforme d'apprentissage !"
                    </p>
                    <div class="flex items-center">
                        <div class="bg-gray-300 dark:bg-gray-600 w-10 h-10 rounded-full flex items-center justify-center text-gray-600 dark:text-gray-300 mr-3">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <h4 class="font-bold text-gray-800 dark:text-white">Fatima M.</h4>
                            <p class="text-gray-500 dark:text-gray-400 text-sm">Formation E-Commerce</p>
                        </div>
                    </div>
                </div>
                
                <!-- Testimonial 3 -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6" data-aos="fade-up" data-aos-delay="300">
                    <div class="flex items-center mb-4">
                        <div class="text-yellow-400 mr-2">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="far fa-star"></i>
                        </div>
                        <span class="text-gray-600 dark:text-gray-400 text-sm">4/5</span>
                    </div>
                    <p class="text-gray-600 dark:text-gray-300 mb-6 italic">
                        "La formation Excel avancé m'a fait gagner en productivité dans mon travail quotidien. Les tutoriels sont bien structurés et le formateur est très pédagogue. Le prix est très abordable pour la qualité proposée."
                    </p>
                    <div class="flex items-center">
                        <div class="bg-gray-300 dark:bg-gray-600 w-10 h-10 rounded-full flex items-center justify-center text-gray-600 dark:text-gray-300 mr-3">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <h4 class="font-bold text-gray-800 dark:text-white">Ibrahim S.</h4>
                            <p class="text-gray-500 dark:text-gray-400 text-sm">Formation Excel Avancé</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Why Choose Us Section -->
    <section class="py-12 bg-white dark:bg-gray-800">
        <div class="container mx-auto px-4">
            <h2 class="text-2xl font-bold mb-8 text-center dark:text-white">Pourquoi choisir notre plateforme de formation</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <!-- Feature 1 -->
                <div class="text-center" data-aos="fade-up" data-aos-delay="100">
                    <div class="text-netblue-600 dark:text-netblue-400 text-4xl mb-4 flex justify-center">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-3 dark:text-white">Formateurs experts</h3>
                    <p class="text-gray-600 dark:text-gray-300">
                        Nos formateurs sont des professionnels reconnus dans leur domaine avec une solide expérience terrain.
                    </p>
                </div>
                
                <!-- Feature 2 -->
                <div class="text-center" data-aos="fade-up" data-aos-delay="200">
                    <div class="text-netblue-600 dark:text-netblue-400 text-4xl mb-4 flex justify-center">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-3 dark:text-white">Accessible partout</h3>
                    <p class="text-gray-600 dark:text-gray-300">
                        Accédez à vos formations depuis n'importe quel appareil : ordinateur, tablette ou smartphone.
                    </p>
                </div>
                
                <!-- Feature 3 -->
                <div class="text-center" data-aos="fade-up" data-aos-delay="300">
                    <div class="text-netblue-600 dark:text-netblue-400 text-4xl mb-4 flex justify-center">
                        <i class="fas fa-sync-alt"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-3 dark:text-white">Contenu à jour</h3>
                    <p class="text-gray-600 dark:text-gray-300">
                        Nos formations sont régulièrement mises à jour pour refléter les dernières avancées technologiques.
                    </p>
                </div>
                
                <!-- Feature 4 -->
                <div class="text-center" data-aos="fade-up" data-aos-delay="400">
                    <div class="text-netblue-600 dark:text-netblue-400 text-4xl mb-4 flex justify-center">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-3 dark:text-white">Support dédié</h3>
                    <p class="text-gray-600 dark:text-gray-300">
                        Notre équipe de support est disponible pour vous aider à chaque étape de votre apprentissage.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action Section -->
    <section class="py-12 bg-netblue-600 dark:bg-netblue-800 text-white">
        <div class="container mx-auto px-4 text-center">
            <h2 class="text-3xl font-bold mb-4">Prêt à développer vos compétences ?</h2>
            <p class="text-xl mb-8 max-w-3xl mx-auto">
                Rejoignez notre communauté d'apprenants et accédez à des formations de qualité pour booster votre carrière et rester compétitif sur le marché du travail.
            </p>
            <div class="flex flex-col sm:flex-row items-center justify-center space-y-4 sm:space-y-0 sm:space-x-4">
                <?php if (!$is_logged_in): ?>
                <a href="register.php" class="bg-white text-netblue-600 hover:bg-netblue-100 font-bold py-3 px-8 rounded-lg transition-colors inline-block">
                    <i class="fas fa-user-plus mr-2"></i>Créer un compte
                </a>
                <a href="login.php" class="bg-transparent border-2 border-white hover:bg-white hover:text-netblue-600 text-white font-bold py-3 px-8 rounded-lg transition-colors inline-block">
                    <i class="fas fa-sign-in-alt mr-2"></i>Se connecter
                </a>
                <?php else: ?>
                <a href="formations.php" class="bg-white text-netblue-600 hover:bg-netblue-100 font-bold py-3 px-8 rounded-lg transition-colors inline-block">
                    <i class="fas fa-graduation-cap mr-2"></i>Explorer toutes les formations
                </a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="py-12 bg-gray-50 dark:bg-gray-900">
        <div class="container mx-auto px-4">
            <h2 class="text-2xl font-bold mb-8 text-center dark:text-white">Questions fréquentes</h2>
            
            <div class="max-w-3xl mx-auto space-y-4" data-aos="fade-up">
                <!-- Question 1 -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
                    <button class="faq-toggle w-full flex justify-between items-center p-4 text-left">
                        <span class="font-bold text-gray-800 dark:text-white">Comment fonctionne l'abonnement ?</span>
                        <i class="fas fa-chevron-down text-netblue-600 dark:text-netblue-400 transition-transform"></i>
                    </button>
                    <div class="faq-content hidden p-4 pt-0 text-gray-600 dark:text-gray-300 border-t border-gray-200 dark:border-gray-700">
                        <p>
                            L'abonnement est mensuel et vous donne un accès complet à la formation choisie. Vous pouvez vous abonner pour un ou plusieurs mois. Après votre inscription, vous effectuez un paiement via l'un de nos partenaires (Nita, Amana, Zeyna, Niya), et envoyez le reçu. Après validation par notre équipe (généralement sous 24h), vous aurez accès à l'intégralité du contenu de la formation.
                        </p>
                    </div>
                </div>
                
                <!-- Question 2 -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
                    <button class="faq-toggle w-full flex justify-between items-center p-4 text-left">
                        <span class="font-bold text-gray-800 dark:text-white">Comment obtenir mon certificat ?</span>
                        <i class="fas fa-chevron-down text-netblue-600 dark:text-netblue-400 transition-transform"></i>
                    </button>
                    <div class="faq-content hidden p-4 pt-0 text-gray-600 dark:text-gray-300 border-t border-gray-200 dark:border-gray-700">
                        <p>
                            À la fin de chaque formation, vous aurez accès à un quiz final qui évaluera vos connaissances. Une fois que vous aurez réussi ce quiz (score minimum de 70%), vous pourrez générer votre certificat en remplissant vos informations personnelles. Le certificat sera disponible en format PDF, que vous pourrez télécharger ou imprimer.
                        </p>
                    </div>
                </div>
                
                <!-- Question 3 -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
                    <button class="faq-toggle w-full flex justify-between items-center p-4 text-left">
                        <span class="font-bold text-gray-800 dark:text-white">Puis-je suivre les formations sur mon téléphone ?</span>
                        <i class="fas fa-chevron-down text-netblue-600 dark:text-netblue-400 transition-transform"></i>
                    </button>
                    <div class="faq-content hidden p-4 pt-0 text-gray-600 dark:text-gray-300 border-t border-gray-200 dark:border-gray-700">
                        <p>
                            Oui, notre plateforme est entièrement responsive et optimisée pour les appareils mobiles. Vous pouvez suivre vos formations depuis un ordinateur, une tablette ou un smartphone. La progression est synchronisée entre tous vos appareils, ce qui vous permet de commencer sur un appareil et de continuer sur un autre.
                        </p>
                    </div>
                </div>
                
                <!-- Question 4 -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
                    <button class="faq-toggle w-full flex justify-between items-center p-4 text-left">
                        <span class="font-bold text-gray-800 dark:text-white">Comment puis-je poser des questions si je suis bloqué ?</span>
                        <i class="fas fa-chevron-down text-netblue-600 dark:text-netblue-400 transition-transform"></i>
                    </button>
                    <div class="faq-content hidden p-4 pt-0 text-gray-600 dark:text-gray-300 border-t border-gray-200 dark:border-gray-700">
                        <p>
                            Chaque formation dispose d'un forum dédié où vous pouvez poser vos questions. Les formateurs et les autres apprenants peuvent vous répondre. Vous pouvez également contacter notre support technique via le formulaire de contact si vous rencontrez des problèmes techniques avec la plateforme.
                        </p>
                    </div>
                </div>
                
                <!-- Question 5 -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
                    <button class="faq-toggle w-full flex justify-between items-center p-4 text-left">
                        <span class="font-bold text-gray-800 dark:text-white">Les formations sont-elles reconnues officiellement ?</span>
                        <i class="fas fa-chevron-down text-netblue-600 dark:text-netblue-400 transition-transform"></i>
                    </button>
                    <div class="faq-content hidden p-4 pt-0 text-gray-600 dark:text-gray-300 border-t border-gray-200 dark:border-gray-700">
                        <p>
                            Nos formations sont conçues par des professionnels du secteur et visent à vous donner des compétences pratiques. Les certificats que nous délivrons attestent de votre suivi de la formation et de la validation de vos connaissances. Bien qu'ils ne soient pas des diplômes d'État, ils sont reconnus par de nombreuses entreprises comme preuve de vos compétences dans le domaine concerné.
                        </p>
                    </div>
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
                        <li><a href="formations.php" class="text-gray-400 hover:text-white transition-colors">Formation</a></li>
                    </ul>
                </div>
                
                <!-- Formations -->
                <div>
                    <h4 class="text-xl font-bold mb-6">Nos formations</h4>
                    <ul class="space-y-3">
                        <?php foreach (array_slice($categories, 0, 5) as $category): ?>
                        <li><a href="formations.php?category=<?php echo $category['id']; ?>" class="text-gray-400 hover:text-white transition-colors">
                            <i class="fas <?php echo htmlspecialchars($category['icon']); ?> mr-2"></i><?php echo htmlspecialchars($category['name']); ?>
                        </a></li>
                        <?php endforeach; ?>
                        <li><a href="formations.php" class="text-netblue-400 hover:text-netblue-300 transition-colors">
                            <i class="fas fa-graduation-cap mr-2"></i>Toutes les formations
                        </a></li>
                    </ul>
                </div>
                
                <!-- Contact -->
                <div>
                    <h4 class="text-xl font-bold mb-6">Contactez-nous</h4>
                    <ul class="space-y-3 text-gray-400">
                        <li class="flex items-start">
                            <i class="fas fa-map-marker-alt mt-1 mr-3"></i>
                            <span>Niamey, Niger</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-phone-alt mr-3"></i>
                            <span>+227 88 67 21 15</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-envelope mr-3"></i>
                            <span>contact@netcrafterniger.com</span>
                        </li>
                    </ul>
                    
                    <!-- Social Media -->
                    <div class="flex space-x-4 mt-6">
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

    <!-- Favorites Added Toast Notification -->
    <div id="fav-toast" class="fixed bottom-4 right-4 bg-purple-600 text-white px-6 py-3 rounded-lg shadow-lg transform translate-y-24 opacity-0 transition-all duration-300 flex items-center z-50">
        <i class="fas fa-heart mr-3 text-xl"></i>
        <span>Formation ajoutée aux favoris</span>
    </div>

    <!-- Favorites Removed Toast Notification -->
    <div id="fav-removed-toast" class="fixed bottom-4 right-4 bg-gray-700 text-white px-6 py-3 rounded-lg shadow-lg transform translate-y-24 opacity-0 transition-all duration-300 flex items-center z-50">
        <i class="fas fa-heart-broken mr-3 text-xl"></i>
        <span>Formation retirée des favoris</span>
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

        document.addEventListener('DOMContentLoaded', function() {
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
            
            // FAQ toggles
            const faqToggles = document.querySelectorAll('.faq-toggle');
            faqToggles.forEach(toggle => {
                toggle.addEventListener('click', function() {
                    const content = this.nextElementSibling;
                    const icon = this.querySelector('i');
                    
                    if (content.style.maxHeight) {
                        content.style.maxHeight = null;
                        content.classList.add('hidden');
                        icon.classList.remove('transform', 'rotate-180');
                    } else {
                        content.classList.remove('hidden');
                        content.style.maxHeight = content.scrollHeight + 'px';
                        icon.classList.add('transform', 'rotate-180');
                    }
                });
            });
            
            // Show toast notifications
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