<?php
// Initialisation de la session
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
        header("Location: formation-details.php?id=$formation_id&fav_added=1");
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
        header("Location: formation-details.php?id=$formation_id&fav_removed=1");
        exit;
    }
}

// Récupérer l'ID de la formation depuis l'URL
$formation_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Si aucun ID n'est fourni, rediriger vers la liste des formations
if ($formation_id === 0) {
    header("Location: formations.php");
    exit;
}

// Récupérer les détails de la formation
$formation_query = "SELECT f.*, c.name as category_name, c.icon as category_icon 
                   FROM formations f 
                   JOIN formation_categories c ON f.category_id = c.id 
                   WHERE f.id = ? AND f.status = 'active'";
$stmt = $conn->prepare($formation_query);
$stmt->bind_param("i", $formation_id);
$stmt->execute();
$result = $stmt->get_result();

// Si la formation n'existe pas ou n'est pas active, rediriger vers la liste des formations
if ($result->num_rows === 0) {
    header("Location: formations.php?error=formation_not_found");
    exit;
}

$formation = $result->fetch_assoc();

// Si l'image de couverture n'est pas définie, utiliser une image par défaut
if (empty($formation['cover_image'])) {
    $formation['cover_image'] = "image/formations/default-" . rand(1, 3) . ".jpg";
}

// Récupérer les modules de la formation
$modules_query = "SELECT * FROM formation_modules WHERE formation_id = ? ORDER BY order_number";
$stmt = $conn->prepare($modules_query);
$stmt->bind_param("i", $formation_id);
$stmt->execute();
$modules_result = $stmt->get_result();
$modules = [];

while ($module = $modules_result->fetch_assoc()) {
    // Récupérer les vidéos de chaque module
    $videos_query = "SELECT * FROM formation_videos WHERE module_id = ? ORDER BY order_number";
    $video_stmt = $conn->prepare($videos_query);
    $video_stmt->bind_param("i", $module['id']);
    $video_stmt->execute();
    $videos_result = $video_stmt->get_result();
    
    $videos = [];
    while ($video = $videos_result->fetch_assoc()) {
        $videos[] = $video;
    }
    
    $module['videos'] = $videos;
    $modules[] = $module;
}

// Vérifier si l'utilisateur est abonné à cette formation
$is_subscribed = false;
$subscription = null;

if ($is_logged_in) {
    $subscription_query = "SELECT * FROM formation_subscriptions 
                          WHERE user_id = ? AND formation_id = ? 
                          AND status = 'active' AND end_date >= CURDATE()";
    $stmt = $conn->prepare($subscription_query);
    $stmt->bind_param("ii", $user_id, $formation_id);
    $stmt->execute();
    $subscription_result = $stmt->get_result();
    
    if ($subscription_result->num_rows > 0) {
        $is_subscribed = true;
        $subscription = $subscription_result->fetch_assoc();
    }
}

// Récupérer la progression de l'utilisateur si abonné
$user_progress = [];
if ($is_subscribed) {
    // Récupérer toutes les vidéos de cette formation
    $all_videos_query = "SELECT fv.id 
                        FROM formation_videos fv 
                        JOIN formation_modules fm ON fv.module_id = fm.id 
                        WHERE fm.formation_id = ?";
    $all_videos_stmt = $conn->prepare($all_videos_query);
    $all_videos_stmt->bind_param("i", $formation_id);
    $all_videos_stmt->execute();
    $all_videos_result = $all_videos_stmt->get_result();
    $total_videos = $all_videos_result->num_rows;
    
    // Récupérer les vidéos complétées par l'utilisateur
    $completed_videos_query = "SELECT vp.video_id 
                              FROM video_progress vp 
                              JOIN formation_videos fv ON vp.video_id = fv.id 
                              JOIN formation_modules fm ON fv.module_id = fm.id 
                              WHERE vp.user_id = ? AND fm.formation_id = ? AND vp.is_completed = 1";
    $completed_videos_stmt = $conn->prepare($completed_videos_query);
    $completed_videos_stmt->bind_param("ii", $user_id, $formation_id);
    $completed_videos_stmt->execute();
    $completed_videos_result = $completed_videos_stmt->get_result();
    $completed_videos = $completed_videos_result->num_rows;
    
    // Calculer le pourcentage de progression
    $progress_percentage = $total_videos > 0 ? round(($completed_videos / $total_videos) * 100) : 0;
    
    $user_progress = [
        'total_videos' => $total_videos,
        'completed_videos' => $completed_videos,
        'progress_percentage' => $progress_percentage
    ];
    
    // Récupérer l'avancement détaillé pour chaque vidéo
    $video_progress_query = "SELECT video_id, watched_seconds, is_completed 
                            FROM video_progress 
                            WHERE user_id = ?";
    $video_progress_stmt = $conn->prepare($video_progress_query);
    $video_progress_stmt->bind_param("i", $user_id);
    $video_progress_stmt->execute();
    $video_progress_result = $video_progress_stmt->get_result();
    
    while ($progress = $video_progress_result->fetch_assoc()) {
        $user_progress['videos'][$progress['video_id']] = [
            'watched_seconds' => $progress['watched_seconds'],
            'is_completed' => $progress['is_completed']
        ];
    }
}

// Récupérer des formations similaires (même catégorie)
$similar_formations_query = "SELECT f.*, c.name as category_name, c.icon as category_icon 
                           FROM formations f 
                           JOIN formation_categories c ON f.category_id = c.id 
                           WHERE f.category_id = ? AND f.id != ? AND f.status = 'active' 
                           ORDER BY RAND() 
                           LIMIT 3";
$stmt = $conn->prepare($similar_formations_query);
$stmt->bind_param("ii", $formation['category_id'], $formation_id);
$stmt->execute();
$similar_result = $stmt->get_result();
$similar_formations = [];

while ($similar = $similar_result->fetch_assoc()) {
    // Si l'image de couverture n'est pas définie, utiliser une image par défaut
    if (empty($similar['cover_image'])) {
        $similar['cover_image'] = "image/formations/default-" . rand(1, 3) . ".jpg";
    }
    $similar_formations[] = $similar;
}

// Récupérer les avis sur la formation (à implémenter selon la structure de votre base de données)

// Fermer la connexion à la base de données
$conn->close();
?>

<!DOCTYPE html>
<html lang="fr" class="light scroll">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($formation['title']); ?> - Netcrafter Formations</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Animation library -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- AOS Library for scroll animations -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
    <!-- Video.js for video player -->
    <link href="https://vjs.zencdn.net/7.20.3/video-js.css" rel="stylesheet" />
    <script src="https://vjs.zencdn.net/7.20.3/video.min.js"></script>
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
        
        .text-shadow {
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
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
        
        /* Module accordion styles */
        .module-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        
        .module-content.active {
            max-height: 1000px;
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
        
        /* Style du lecteur vidéo */
        .video-js {
            width: 100%;
            height: 100%;
            aspect-ratio: 16/9;
        }
        
        .vjs-netblue-theme {
            --vjs-primary-color: #3B82F6;
            --vjs-secondary-color: #1E40AF;
        }
        
        .vjs-netblue-theme .vjs-big-play-button {
            background-color: rgba(59, 130, 246, 0.7);
            border-color: white;
        }
        
        .vjs-netblue-theme .vjs-slider {
            background-color: rgba(255, 255, 255, 0.3);
        }
        
        .vjs-netblue-theme .vjs-volume-level, 
        .vjs-netblue-theme .vjs-play-progress {
            background-color: var(--vjs-primary-color);
        }
        
        .vjs-netblue-theme .vjs-control-bar {
            background-color: rgba(17, 24, 39, 0.8);
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
                <a href="index.html">
                    <img src="../image/logo-n.png" alt="Netcrafter Logo" class="h-10 mr-2">
                    <span class="text-xl md:text-2xl font-bold text-netblue-600 dark:text-netblue-400 navbar-brand">NETCRAFTER</span>
                </a>
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
            
            <!-- Mobile Menu Button, Favorites and Dark Mode Toggle -->
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

    <!-- Formation Hero Section -->
    <section class="pt-24 pb-16 relative">
        <div class="absolute inset-0 bg-gradient-to-b from-gray-100 to-white dark:from-gray-800 dark:to-gray-900 z-0"></div>
        
        <div class="container mx-auto px-4 relative z-10">
            <div class="flex flex-col md:flex-row gap-8">
                <!-- Formation Image -->
                <div class="md:w-1/3" data-aos="fade-right">
                    <div class="relative rounded-lg overflow-hidden shadow-xl">
                        <img src="../<?php echo htmlspecialchars($formation['cover_image']); ?>" alt="<?php echo htmlspecialchars($formation['title']); ?>" class="w-full h-auto">
                        
                        <!-- Category Badge -->
                        <div class="absolute top-4 left-4 bg-netblue-600 text-white px-3 py-1 rounded-full text-sm font-medium">
                            <i class="fas <?php echo htmlspecialchars($formation['category_icon']); ?> mr-1"></i>
                            <?php echo htmlspecialchars($formation['category_name']); ?>
                        </div>
                        
                        <!-- Favorite Button -->
                        <form method="POST" class="absolute top-4 right-4">
                            <input type="hidden" name="formation_id" value="<?php echo $formation['id']; ?>">
                            <?php if (in_array($formation['id'], $_SESSION['formation_favorites'])): ?>
                                <input type="hidden" name="action" value="remove_from_favorites">
                                <button type="submit" class="bg-white dark:bg-gray-800 text-red-500 h-10 w-10 rounded-full flex items-center justify-center shadow-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                    <i class="fas fa-heart text-lg"></i>
                                </button>
                            <?php else: ?>
                                <input type="hidden" name="action" value="add_to_favorites">
                                <button type="submit" class="bg-white dark:bg-gray-800 text-gray-400 h-10 w-10 rounded-full flex items-center justify-center shadow-md hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-red-500 transition-colors">
                                    <i class="far fa-heart text-lg"></i>
                                </button>
                            <?php endif; ?>
                        </form>
                        
                        <!-- Level Badge -->
                        <div class="absolute bottom-4 left-4 text-white px-3 py-1 rounded-full text-sm font-medium
                            <?php 
                            switch($formation['level']) {
                                case 'debutant':
                                    echo 'bg-green-600';
                                    break;
                                case 'intermediaire':echo 'bg-yellow-600';
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
                    
                    <!-- Formation Price Card -->
                    <div class="mt-6 bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6" data-aos="fade-up">
                        <div class="flex justify-between items-center mb-4">
                            <div>
                                <h3 class="text-2xl font-bold text-netblue-600 dark:text-netblue-400">
                                    <?php echo number_format($formation['price_per_month'], 0, ',', ' '); ?> FCFA
                                </h3>
                                <p class="text-gray-500 dark:text-gray-400">par mois</p>
                            </div>
                            <div class="flex items-center space-x-1 text-yellow-400">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star-half-alt"></i>
                                <span class="ml-1 text-gray-600 dark:text-gray-300 text-sm">4.5</span>
                            </div>
                        </div>
                        
                        <?php if ($is_subscribed): ?>
                        <!-- Already subscribed -->
                        <div class="bg-green-50 dark:bg-green-900 border border-green-200 dark:border-green-700 rounded-lg p-3 mb-4">
                            <div class="flex items-start">
                                <div class="flex-shrink-0 pt-0.5">
                                    <i class="fas fa-check-circle text-green-600 dark:text-green-400"></i>
                                </div>
                                <div class="ml-3">
                                    <h4 class="text-sm font-medium text-green-800 dark:text-green-300">Vous êtes abonné</h4>
                                    <p class="mt-1 text-sm text-green-700 dark:text-green-400">
                                        Votre abonnement est valide jusqu'au <?php echo date('d/m/Y', strtotime($subscription['end_date'])); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Progress -->
                        <div class="mb-4">
                            <div class="flex justify-between mb-1">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Progression</span>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300"><?php echo $user_progress['progress_percentage']; ?>%</span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                                <div class="bg-netblue-600 h-2.5 rounded-full" style="width: <?php echo $user_progress['progress_percentage']; ?>%"></div>
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                <?php echo $user_progress['completed_videos']; ?> sur <?php echo $user_progress['total_videos']; ?> vidéos terminées
                            </p>
                        </div>
                        
                        <a href="#module-content" class="w-full bg-netblue-600 hover:bg-netblue-700 text-white font-bold py-3 px-4 rounded-lg transition-colors flex items-center justify-center mb-3">
                            <i class="fas fa-play-circle mr-2"></i>Continuer la formation
                        </a>
                        
                        <a href="forum.php?formation_id=<?php echo $formation['id']; ?>" class="w-full bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-white font-medium py-3 px-4 rounded-lg transition-colors flex items-center justify-center">
                            <i class="fas fa-comments mr-2"></i>Forum de discussion
                        </a>
                        <?php else: ?>
                        <div class="space-y-3 mb-4">
                            <div class="flex items-center">
                                <i class="fas fa-check text-netblue-600 dark:text-netblue-400 mr-3"></i>
                                <span class="text-gray-700 dark:text-gray-300">Accès à toutes les vidéos</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-check text-netblue-600 dark:text-netblue-400 mr-3"></i>
                                <span class="text-gray-700 dark:text-gray-300">Forum d'entraide</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-check text-netblue-600 dark:text-netblue-400 mr-3"></i>
                                <span class="text-gray-700 dark:text-gray-300">Certificat de fin de formation</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-check text-netblue-600 dark:text-netblue-400 mr-3"></i>
                                <span class="text-gray-700 dark:text-gray-300">Mises à jour du contenu</span>
                            </div>
                        </div>
                        
                        <?php if ($is_logged_in): ?>
                        <a href="subscribe.php?formation_id=<?php echo $formation['id']; ?>" class="w-full bg-netblue-600 hover:bg-netblue-700 text-white font-bold py-3 px-4 rounded-lg transition-colors flex items-center justify-center mb-3">
                            <i class="fas fa-graduation-cap mr-2"></i>S'inscrire à cette formation
                        </a>
                        <?php else: ?>
                        <a href="login.php?redirect=formation-details.php?id=<?php echo $formation['id']; ?>" class="w-full bg-netblue-600 hover:bg-netblue-700 text-white font-bold py-3 px-4 rounded-lg transition-colors flex items-center justify-center mb-3">
                            <i class="fas fa-sign-in-alt mr-2"></i>Se connecter pour s'inscrire
                        </a>
                        <?php endif; ?>
                        
                        <div class="text-center text-sm text-gray-500 dark:text-gray-400 mt-3">
                            <p>Paiement sécurisé via</p>
                            <div class="flex justify-center space-x-3 mt-2">
                                <span class="font-medium">Nita</span>
                                <span class="font-medium">Amana</span>
                                <span class="font-medium">Zeyna</span>
                                <span class="font-medium">Niya</span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Formation Details Card -->
                    <div class="mt-6 bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6" data-aos="fade-up">
                        <h3 class="text-lg font-bold mb-4 dark:text-white">Détails de la formation</h3>
                        
                        <div class="space-y-4">
                            <div class="flex items-start">
                                <div class="text-netblue-600 dark:text-netblue-400 mr-3 mt-1">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-800 dark:text-white">Durée</h4>
                                    <p class="text-gray-600 dark:text-gray-300"><?php echo !empty($formation['duration']) ? htmlspecialchars($formation['duration']) : 'Variable'; ?></p>
                                </div>
                            </div>
                            
                            <div class="flex items-start">
                                <div class="text-netblue-600 dark:text-netblue-400 mr-3 mt-1">
                                    <i class="fas fa-layer-group"></i>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-800 dark:text-white">Modules</h4>
                                    <p class="text-gray-600 dark:text-gray-300"><?php echo count($modules); ?> modules, 
                                    <?php 
                                    $total_videos = 0;
                                    foreach ($modules as $module) {
                                        $total_videos += count($module['videos']);
                                    }
                                    echo $total_videos;
                                    ?> vidéos</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start">
                                <div class="text-netblue-600 dark:text-netblue-400 mr-3 mt-1">
                                    <i class="fas fa-signal"></i>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-800 dark:text-white">Niveau</h4>
                                    <p class="text-gray-600 dark:text-gray-300">
                                        <?php 
                                        switch($formation['level']) {
                                            case 'debutant':
                                                echo 'Débutant - aucun prérequis';
                                                break;
                                            case 'intermediaire':
                                                echo 'Intermédiaire - connaissances de base requises';
                                                break;
                                            case 'avance':
                                                echo 'Avancé - bonnes connaissances requises';
                                                break;
                                        }
                                        ?>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="flex items-start">
                                <div class="text-netblue-600 dark:text-netblue-400 mr-3 mt-1">
                                    <i class="fas fa-language"></i>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-800 dark:text-white">Langue</h4>
                                    <p class="text-gray-600 dark:text-gray-300">Français</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start">
                                <div class="text-netblue-600 dark:text-netblue-400 mr-3 mt-1">
                                    <i class="fas fa-certificate"></i>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-800 dark:text-white">Certification</h4>
                                    <p class="text-gray-600 dark:text-gray-300">Certificat délivré en fin de formation</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Formation Content -->
                <div class="md:w-2/3" data-aos="fade-left">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 md:p-8">
                        <h1 class="text-3xl font-bold mb-4 dark:text-white"><?php echo htmlspecialchars($formation['title']); ?></h1>
                        
                        <div class="flex flex-wrap gap-4 mb-6">
                            <div class="flex items-center text-gray-600 dark:text-gray-400">
                                <i class="fas fa-calendar-alt mr-2"></i>
                                <span>Mis à jour le <?php echo date('d/m/Y', strtotime($formation['updated_at'])); ?></span>
                            </div>
                            <div class="flex items-center text-gray-600 dark:text-gray-400">
                                <i class="fas fa-users mr-2"></i>
                                <span>152 apprenants</span>
                            </div>
                        </div>
                        
                        <!-- Formation Description -->
                        <div class="prose prose-netblue max-w-none dark:prose-invert mb-8">
                            <?php echo nl2br(htmlspecialchars($formation['description'])); ?>
                        </div>
                        
                        <!-- Video Preview (if a preview video exists) -->
                        <?php if (!empty($modules) && !empty($modules[0]['videos'])): ?>
                        <div class="mb-8">
                            <h2 class="text-xl font-bold mb-4 dark:text-white">Aperçu de la formation</h2>
                            
                            <div class="relative bg-black rounded-lg overflow-hidden" style="aspect-ratio: 16/9;">
                                <?php if ($is_subscribed): ?>
                                <!-- Full video for subscribers -->
                                <video 
                                    id="preview-video"
                                    class="video-js vjs-netblue-theme"
                                    controls
                                    preload="auto"
                                    poster="../<?php echo htmlspecialchars($formation['cover_image']); ?>"
                                    data-setup='{}'>
                                    <source src="../<?php echo htmlspecialchars($modules[0]['videos'][0]['video_url']); ?>" type="video/mp4">
                                    <p class="vjs-no-js">
                                        Pour voir cette vidéo, veuillez activer JavaScript et considérer de mettre à jour votre navigateur.
                                    </p>
                                </video>
                                <?php else: ?>
                                <!-- Preview video with time limit -->
                                <video 
                                    id="preview-video"
                                    class="video-js vjs-netblue-theme"
                                    controls
                                    preload="auto"
                                    poster="../<?php echo htmlspecialchars($formation['cover_image']); ?>"
                                    data-setup='{"userActions": {"hotkeys": true}}'>
                                    <source src="../<?php echo htmlspecialchars($modules[0]['videos'][0]['video_url']); ?>#t=0,<?php echo intval($modules[0]['videos'][0]['preview_duration']); ?>" type="video/mp4">
                                    <p class="vjs-no-js">
                                        Pour voir cette vidéo, veuillez activer JavaScript et considérer de mettre à jour votre navigateur.
                                    </p>
                                </video>
                                
                                <!-- Preview Overlay (shown after preview time ends) -->
                                <div id="preview-overlay" class="hidden absolute inset-0 bg-black bg-opacity-80 flex flex-col items-center justify-center text-white p-6 text-center">
                                    <i class="fas fa-lock text-4xl mb-4"></i>
                                    <h3 class="text-xl font-bold mb-2">Aperçu terminé</h3>
                                    <p class="mb-4">Abonnez-vous à cette formation pour accéder à l'intégralité du contenu.</p>
                                    <a href="subscribe.php?formation_id=<?php echo $formation['id']; ?>" class="bg-netblue-600 hover:bg-netblue-700 text-white font-bold py-2 px-6 rounded-lg transition-colors">
                                        S'abonner maintenant
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Module Content -->
                        <div id="module-content">
                            <h2 class="text-xl font-bold mb-4 dark:text-white">Contenu de la formation</h2>
                            
                            <!-- Module Accordion -->
                            <div class="space-y-4">
                                <?php foreach ($modules as $index => $module): ?>
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg overflow-hidden">
                                    <!-- Module Header -->
                                    <button class="module-toggle w-full flex items-center justify-between p-4 text-left font-medium" data-index="<?php echo $index; ?>">
                                        <div class="flex items-center">
                                            <div class="mr-3 text-netblue-600 dark:text-netblue-400">
                                                <i class="fas fa-folder"></i>
                                            </div>
                                            <div>
                                                <h3 class="font-bold text-gray-800 dark:text-white"><?php echo htmlspecialchars($module['title']); ?></h3>
                                                <p class="text-sm text-gray-600 dark:text-gray-400"><?php echo count($module['videos']); ?> vidéos</p>
                                            </div>
                                        </div>
                                        <i class="fas fa-chevron-down text-gray-500 dark:text-gray-400 transform transition-transform"></i>
                                    </button>
                                    
                                    <!-- Module Content (videos) -->
                                    <div class="module-content px-4 pb-4 <?php echo $index === 0 ? 'active' : ''; ?>">
                                        <div class="space-y-3">
                                            <?php foreach ($module['videos'] as $video): ?>
                                            <div class="flex items-start bg-white dark:bg-gray-800 p-3 rounded-lg">
                                                <div class="text-netblue-600 dark:text-netblue-400 mr-3 mt-1">
                                                    <i class="fas fa-play-circle"></i>
                                                </div>
                                                <div class="flex-grow">
                                                    <h4 class="font-medium text-gray-800 dark:text-white"><?php echo htmlspecialchars($video['title']); ?></h4>
                                                    <p class="text-sm text-gray-600 dark:text-gray-400"><?php echo htmlspecialchars($video['duration']); ?></p>
                                                </div>
                                                
                                                <?php if ($is_subscribed): ?>
                                                <a href="watch.php?video_id=<?php echo $video['id']; ?>" class="bg-netblue-100 dark:bg-netblue-900 text-netblue-600 dark:text-netblue-400 px-3 py-1 rounded-full text-sm hover:bg-netblue-200 dark:hover:bg-netblue-800 transition-colors">
                                                    <?php 
                                                    if (isset($user_progress['videos'][$video['id']]['is_completed']) && $user_progress['videos'][$video['id']]['is_completed']) {
                                                        echo 'Revoir';
                                                    } else {
                                                        echo 'Visionner';
                                                    }
                                                    ?>
                                                </a>
                                                <?php elseif ($video === $modules[0]['videos'][0]): ?>
                                                <span class="bg-gray-100 dark:bg-gray-600 text-gray-600 dark:text-gray-400 px-3 py-1 rounded-full text-sm">
                                                    Aperçu
                                                </span>
                                                <?php else: ?>
                                                <span class="bg-gray-100 dark:bg-gray-600 text-gray-600 dark:text-gray-400 px-3 py-1 rounded-full text-sm">
                                                    <i class="fas fa-lock mr-1"></i>Verrouillé
                                                </span>
                                                <?php endif; ?>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- What You'll Learn -->
                    <div class="mt-8 bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 md:p-8" data-aos="fade-up">
                        <h2 class="text-xl font-bold mb-6 dark:text-white">Ce que vous allez apprendre</h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="flex items-start">
                                <div class="flex-shrink-0 mt-0.5 text-netblue-600 dark:text-netblue-400">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <p class="ml-3 text-gray-700 dark:text-gray-300">Maîtriser les concepts fondamentaux de <?php echo htmlspecialchars($formation['category_name']); ?></p>
                            </div>
                            <div class="flex items-start">
                                <div class="flex-shrink-0 mt-0.5 text-netblue-600 dark:text-netblue-400">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <p class="ml-3 text-gray-700 dark:text-gray-300">Appliquer des techniques professionnelles</p>
                            </div>
                            <div class="flex items-start">
                                <div class="flex-shrink-0 mt-0.5 text-netblue-600 dark:text-netblue-400">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <p class="ml-3 text-gray-700 dark:text-gray-300">Résoudre des problèmes complexes</p>
                            </div>
                            <div class="flex items-start">
                                <div class="flex-shrink-0 mt-0.5 text-netblue-600 dark:text-netblue-400">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <p class="ml-3 text-gray-700 dark:text-gray-300">Réaliser des projets concrets et pratiques</p>
                            </div>
                            <div class="flex items-start">
                                <div class="flex-shrink-0 mt-0.5 text-netblue-600 dark:text-netblue-400">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <p class="ml-3 text-gray-700 dark:text-gray-300">Acquérir des compétences recherchées sur le marché</p>
                            </div>
                            <div class="flex items-start">
                                <div class="flex-shrink-0 mt-0.5 text-netblue-600 dark:text-netblue-400">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <p class="ml-3 text-gray-700 dark:text-gray-300">Obtenir un certificat validant vos connaissances</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Requirements -->
                    <div class="mt-8 bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 md:p-8" data-aos="fade-up">
                        <h2 class="text-xl font-bold mb-6 dark:text-white">Prérequis</h2>
                        
                        <div class="space-y-3">
                            <?php if ($formation['level'] === 'debutant'): ?>
                            <div class="flex items-start">
                                <div class="flex-shrink-0 mt-0.5 text-netblue-600 dark:text-netblue-400">
                                    <i class="fas fa-info-circle"></i>
                                </div>
                                <p class="ml-3 text-gray-700 dark:text-gray-300">Aucun prérequis particulier - cette formation est accessible aux débutants</p>
                            </div>
                            <div class="flex items-start">
                                <div class="flex-shrink-0 mt-0.5 text-netblue-600 dark:text-netblue-400">
                                    <i class="fas fa-info-circle"></i>
                                </div>
                                <p class="ml-3 text-gray-700 dark:text-gray-300">Disposer d'un ordinateur avec une connexion internet</p>
                            </div>
                            <?php elseif ($formation['level'] === 'intermediaire'): ?>
                            <div class="flex items-start">
                                <div class="flex-shrink-0 mt-0.5 text-netblue-600 dark:text-netblue-400">
                                    <i class="fas fa-info-circle"></i>
                                </div>
                                <p class="ml-3 text-gray-700 dark:text-gray-300">Connaissances de base en <?php echo htmlspecialchars($formation['category_name']); ?></p>
                            </div>
                            <div class="flex items-start">
                                <div class="flex-shrink-0 mt-0.5 text-netblue-600 dark:text-netblue-400">
                                    <i class="fas fa-info-circle"></i>
                                </div>
                                <p class="ml-3 text-gray-700 dark:text-gray-300">Avoir déjà pratiqué ou suivi une formation d'introduction</p>
                            </div>
                            <div class="flex items-start">
                                <div class="flex-shrink-0 mt-0.5 text-netblue-600 dark:text-netblue-400">
                                    <i class="fas fa-info-circle"></i>
                                </div>
                                <p class="ml-3 text-gray-700 dark:text-gray-300">Disposer d'un ordinateur avec une connexion internet</p>
                            </div>
                            <?php else: ?>
                            <div class="flex items-start">
                                <div class="flex-shrink-0 mt-0.5 text-netblue-600 dark:text-netblue-400">
                                    <i class="fas fa-info-circle"></i>
                                </div>
                                <p class="ml-3 text-gray-700 dark:text-gray-300">Bonnes connaissances en <?php echo htmlspecialchars($formation['category_name']); ?></p>
                            </div>
                            <div class="flex items-start">
                                <div class="flex-shrink-0 mt-0.5 text-netblue-600 dark:text-netblue-400">
                                    <i class="fas fa-info-circle"></i>
                                </div>
                                <p class="ml-3 text-gray-700 dark:text-gray-300">Expérience pratique dans le domaine</p>
                            </div>
                            <div class="flex items-start">
                                <div class="flex-shrink-0 mt-0.5 text-netblue-600 dark:text-netblue-400">
                                    <i class="fas fa-info-circle"></i>
                                </div>
                                <p class="ml-3 text-gray-700 dark:text-gray-300">Avoir déjà réalisé des projets personnels ou professionnels</p>
                            </div>
                            <div class="flex items-start">
                                <div class="flex-shrink-0 mt-0.5 text-netblue-600 dark:text-netblue-400">
                                    <i class="fas fa-info-circle"></i>
                                </div>
                                <p class="ml-3 text-gray-700 dark:text-gray-300">Disposer d'un ordinateur avec une connexion internet</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Similar Formations Section -->
    <?php if (!empty($similar_formations)): ?>
    <section class="py-12 bg-gray-50 dark:bg-gray-900">
        <div class="container mx-auto px-4">
            <h2 class="text-2xl font-bold mb-8 dark:text-white">Formations similaires</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php foreach ($similar_formations as $formation): ?>
                <div class="formation-card bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden" data-aos="fade-up">
                    <!-- Formation Image -->
                    <div class="relative h-48">
                        <img src="../<?php echo htmlspecialchars($formation['cover_image']); ?>" alt="<?php echo htmlspecialchars($formation['title']); ?>" class="w-full h-full object-cover">
                        
                        <!-- Category Badge -->
                        <div class="absolute top-3 left-3 bg-netblue-600 text-white text-xs font-bold px-2 py-1 rounded">
                            <i class="fas <?php echo htmlspecialchars($formation['category_icon']); ?> mr-1"></i>
                            <?php echo htmlspecialchars($formation['category_name']); ?>
                        </div>
                        
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
        </div>
    </section>
    <?php endif; ?>

    <!-- Call to Action Section -->
    <section class="py-12 bg-netblue-600 dark:bg-netblue-800 text-white">
        <div class="container mx-auto px-4 text-center">
            <h2 class="text-3xl font-bold mb-4">Prêt à développer vos compétences ?</h2>
            <p class="text-xl mb-8 max-w-3xl mx-auto">
                Rejoignez notre communauté d'apprenants et accédez à des formations de qualité pour booster votre carrière.
            </p>
            <div class="flex flex-col sm:flex-row items-center justify-center space-y-4 sm:space-y-0 sm:space-x-4">
                <?php if (!$is_logged_in): ?>
                <a href="register.php" class="bg-white text-netblue-600 hover:bg-netblue-100 font-bold py-3 px-8 rounded-lg transition-colors inline-block">
                    <i class="fas fa-user-plus mr-2"></i>Créer un compte
                </a>
                <?php elseif (!$is_subscribed): ?>
                <a href="subscribe.php?formation_id=<?php echo $formation_id; ?>" class="bg-white text-netblue-600 hover:bg-netblue-100 font-bold py-3 px-8 rounded-lg transition-colors inline-block">
                    <i class="fas fa-graduation-cap mr-2"></i>S'abonner à cette formation
                </a>
                <?php else: ?>
                <a href="#module-content" class="bg-white text-netblue-600 hover:bg-netblue-100 font-bold py-3 px-8 rounded-lg transition-colors inline-block">
                    <i class="fas fa-play-circle mr-2"></i>Continuer la formation
                </a>
                <?php endif; ?>
                <a href="formations.php" class="bg-transparent border-2 border-white hover:bg-white hover:text-netblue-600 text-white font-bold py-3 px-8 rounded-lg transition-colors inline-block">
                    <i class="fas fa-th-list mr-2"></i>Voir toutes les formations
                </a>
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
                
                <!-- Newsletter -->
                <div>
                    <h4 class="text-xl font-bold mb-6">Newsletter</h4>
                    <p class="text-gray-400 mb-4">
                        Restez informé des dernières actualités et nouvelles formations
                    </p>
                    <form class="mb-4">
                        <div class="flex">
                            <input type="email" placeholder="Votre email" class="px-4 py-2 w-full rounded-l-lg focus:outline-none text-gray-800 dark:bg-gray-700 dark:text-white">
                            <button type="submit" class="bg-netblue-600 px-4 py-2 rounded-r-lg hover:bg-netblue-700 transition-colors">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </form>
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
            // Module toggles
            const moduleToggles = document.querySelectorAll('.module-toggle');
            moduleToggles.forEach(toggle => {
                toggle.addEventListener('click', function() {
                    const index = this.getAttribute('data-index');
                    const content = this.nextElementSibling;
                    const icon = this.querySelector('i.fas');
                    
                    content.classList.toggle('active');
                    icon.classList.toggle('rotate-180');
                });
            });
            
            // Video player preview time limit (if not subscribed)
            <?php if (!$is_subscribed && !empty($modules) && !empty($modules[0]['videos'])): ?>
            const player = videojs('preview-video');
            const previewOverlay = document.getElementById('preview-overlay');
            const previewDuration = <?php echo intval($modules[0]['videos'][0]['preview_duration']); ?>;
            
            player.on('timeupdate', function() {
                if (this.currentTime() >= previewDuration) {
                    this.pause();
                    previewOverlay.classList.remove('hidden');
                }
            });
            <?php endif; ?>
            
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