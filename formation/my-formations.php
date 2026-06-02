<?php
// Initialisation de la session
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    // Sauvegarder l'URL actuelle pour y revenir après la connexion
    $_SESSION['redirect_url'] = "my-formations.php";
    
    // Rediriger vers la page de connexion
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/db.php';

// Connexion à la base de données
$conn = new mysqli($servername, $username, $password, $dbname);

// Vérification de la connexion
if ($conn->connect_error) {
    die("Échec de la connexion: " . $conn->connect_error);
}
$conn->set_charset("utf8");

$user_id = $_SESSION['user_id'];

// Récupérer les informations de l'utilisateur
$user_query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();

// Variables pour le filtrage et la recherche
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_term = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'expiry';

// Requête de base pour toutes les formations de l'utilisateur
$base_query = "SELECT f.*, c.name as category_name, c.icon as category_icon, 
              fs.start_date, fs.end_date, fs.status as subscription_status,
              fs.subscription_months, fs.created_at as subscription_date
              FROM formations f 
              JOIN formation_categories c ON f.category_id = c.id 
              JOIN formation_subscriptions fs ON f.id = fs.formation_id 
              WHERE fs.user_id = ?";

// Ajouter les conditions de filtrage
if ($status_filter == 'active') {
    $base_query .= " AND fs.status = 'active' AND fs.end_date >= CURDATE()";
} elseif ($status_filter == 'expired') {
    $base_query .= " AND (fs.status = 'active' AND fs.end_date < CURDATE() OR fs.status = 'expired')";
} elseif ($status_filter == 'pending') {
    $base_query .= " AND fs.status = 'pending'";
}

// Ajouter la recherche
if (!empty($search_term)) {
    $base_query .= " AND (f.title LIKE CONCAT('%', ?, '%') OR f.description LIKE CONCAT('%', ?, '%') OR c.name LIKE CONCAT('%', ?, '%'))";
}

// Ajouter le tri
if ($sort_by == 'title') {
    $base_query .= " ORDER BY f.title ASC";
} elseif ($sort_by == 'category') {
    $base_query .= " ORDER BY c.name ASC, f.title ASC";
} elseif ($sort_by == 'progress') {
    // On triera par progression après avoir récupéré les données
    $base_query .= " ORDER BY fs.status ASC, fs.end_date DESC";
} elseif ($sort_by == 'recent') {
    $base_query .= " ORDER BY fs.created_at DESC";
} else { // expiry par défaut
    $base_query .= " ORDER BY fs.status ASC, fs.end_date DESC";
}

// Préparer et exécuter la requête
$stmt = $conn->prepare($base_query);

if (!empty($search_term)) {
    $stmt->bind_param("issss", $user_id, $search_term, $search_term, $search_term);
} else {
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$result = $stmt->get_result();
$formations = [];

while ($formation = $result->fetch_assoc()) {
    // Si l'image de couverture n'est pas définie, utiliser une image par défaut
    if (empty($formation['cover_image'])) {
        $formation['cover_image'] = "image/formations/default-" . rand(1, 3) . ".jpg";
    }
    
    // Calculer les jours restants avant expiration
    $end_date = new DateTime($formation['end_date']);
    $today = new DateTime();
    $days_remaining = $today->diff($end_date)->days;
    $formation['days_remaining'] = $days_remaining;
    
    // Vérifier si l'abonnement est expiré
    $formation['is_expired'] = ($formation['subscription_status'] === 'active' && $formation['end_date'] < date('Y-m-d'));
    
    // Récupérer la progression de l'utilisateur pour cette formation
    $progress_query = "SELECT 
                      (SELECT COUNT(*) FROM formation_videos fv 
                       JOIN formation_modules fm ON fv.module_id = fm.id 
                       WHERE fm.formation_id = ?) as total_videos,
                      (SELECT COUNT(*) FROM video_progress vp 
                       JOIN formation_videos fv ON vp.video_id = fv.id 
                       JOIN formation_modules fm ON fv.module_id = fm.id 
                       WHERE vp.user_id = ? AND fm.formation_id = ? AND vp.is_completed = 1) as completed_videos";
    
    $prog_stmt = $conn->prepare($progress_query);
    $prog_stmt->bind_param("iii", $formation['id'], $user_id, $formation['id']);
    $prog_stmt->execute();
    $progress_result = $prog_stmt->get_result();
    $progress = $progress_result->fetch_assoc();
    
    $formation['total_videos'] = $progress['total_videos'];
    $formation['completed_videos'] = $progress['completed_videos'];
    
    // Calculer le pourcentage de progression
    if ($progress['total_videos'] > 0) {
        $formation['progress_percentage'] = round(($progress['completed_videos'] / $progress['total_videos']) * 100);
    } else {
        $formation['progress_percentage'] = 0;
    }
    
    // Trouver la dernière vidéo visionnée
    $last_video_query = "SELECT fv.id, fv.title, fm.id as module_id, fm.title as module_title, vp.last_watched
                        FROM video_progress vp
                        JOIN formation_videos fv ON vp.video_id = fv.id
                        JOIN formation_modules fm ON fv.module_id = fm.id
                        WHERE vp.user_id = ? AND fm.formation_id = ?
                        ORDER BY vp.last_watched DESC
                        LIMIT 1";
    
    $video_stmt = $conn->prepare($last_video_query);
    $video_stmt->bind_param("ii", $user_id, $formation['id']);
    $video_stmt->execute();
    $video_result = $video_stmt->get_result();
    
    if ($video_result->num_rows > 0) {
        $formation['last_video'] = $video_result->fetch_assoc();
    } else {
        // Si aucune vidéo n'a été visionnée, récupérer la première vidéo de la formation
        $first_video_query = "SELECT fv.id, fv.title, fm.id as module_id, fm.title as module_title
                             FROM formation_videos fv
                             JOIN formation_modules fm ON fv.module_id = fm.id
                             WHERE fm.formation_id = ?
                             ORDER BY fm.order_number ASC, fv.order_number ASC
                             LIMIT 1";
        
        $first_stmt = $conn->prepare($first_video_query);
        $first_stmt->bind_param("i", $formation['id']);
        $first_stmt->execute();
        $first_result = $first_stmt->get_result();
        
        if ($first_result->num_rows > 0) {
            $formation['last_video'] = $first_result->fetch_assoc();
            $formation['last_video']['last_watched'] = null;
        }
    }
    
    $formations[] = $formation;
}

// Trier par progression si demandé
if ($sort_by == 'progress') {
    usort($formations, function($a, $b) {
        if ($a['subscription_status'] != $b['subscription_status']) {
            // Les formations actives d'abord
            if ($a['subscription_status'] === 'active' && !$a['is_expired']) return -1;
            if ($b['subscription_status'] === 'active' && !$b['is_expired']) return 1;
        }
        
        // Ensuite trier par pourcentage de progression (décroissant)
        return $b['progress_percentage'] - $a['progress_percentage'];
    });
}

// Statistiques pour le résumé
$summary = [
    'all' => 0,
    'active' => 0,
    'expired' => 0,
    'pending' => 0,
    'completed' => 0
];

foreach ($formations as $formation) {
    $summary['all']++;
    
    if ($formation['subscription_status'] === 'active' && !$formation['is_expired']) {
        $summary['active']++;
        
        // Si toutes les vidéos sont complétées, compter comme terminée
        if ($formation['progress_percentage'] === 100) {
            $summary['completed']++;
        }
    } elseif ($formation['subscription_status'] === 'pending') {
        $summary['pending']++;
    } else {
        $summary['expired']++;
    }
}

// Fermeture de la connexion à la base de données
$conn->close();
?>

<!DOCTYPE html>
<html lang="fr" class="light scroll">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Formations - Netcrafter</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- AOS Library for scroll animations -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
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
        
        /* Side Navigation */
        .sidenav {
            transition: all 0.3s ease;
            z-index: 50;
        }
        
        @media (max-width: 768px) {
            .sidenav {
                width: 280px;
                transform: translateX(-100%);
            }
            
            .sidenav.open {
                transform: translateX(0);
            }
        }
        
        @media (min-width: 768px) {
            .sidenav {
                width: 280px;
            }
            
            .sidenav.collapsed {
                width: 70px;
            }
            
            .sidenav.collapsed .nav-text,
            .sidenav.collapsed .sidenav-title span {
                opacity: 0;
                white-space: nowrap;
            }
            
            .content-area {
                transition: all 0.3s ease;
                margin-left: 280px;
            }
            
            .content-area.nav-collapsed {
                margin-left: 70px;
            }
        }

        .overlay {
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        
        .overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        /* Formation Card Hover Effect */
        .formation-card {
            transition: all 0.3s ease;
        }
        
        .formation-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
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
    <!-- Overlay for mobile menu -->
    <div id="overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 overlay" onclick="toggleMobileMenu()"></div>
    
    <!-- Side Navigation -->
    <aside id="sidenav" class="sidenav fixed h-full bg-white dark:bg-gray-800 shadow-lg z-50">
        <!-- Logo and collapse button -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center sidenav-title">
                <img src="../image/logo-n.png" alt="Netcrafter Logo" class="h-8 mr-2">
                <span class="text-lg font-bold text-netblue-600 dark:text-netblue-400 transition-opacity duration-300 whitespace-nowrap">NETCRAFTER</span>
            </div>
            <button id="sidenav-toggle" class="sidenav-toggle text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 focus:outline-none md:block hidden">
                <i class="fas fa-chevron-left"></i>
            </button>
        </div>
        
        <!-- User info -->
        <div class="px-4 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center">
                <div class="w-10 h-10 rounded-full bg-netblue-600 dark:bg-netblue-700 flex items-center justify-center text-white font-bold text-lg flex-shrink-0">
                    <?php echo strtoupper(substr($user['firstname'], 0, 1) . substr($user['lastname'], 0, 1)); ?>
                </div>
                <div class="ml-3 overflow-hidden">
                    <p class="font-medium text-gray-800 dark:text-white nav-text transition-opacity duration-300 truncate"><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 nav-text transition-opacity duration-300 truncate"><?php echo htmlspecialchars($user['phone']); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Navigation Menu -->
        <nav class="mt-4 px-2 overflow-y-auto" style="max-height: calc(100vh - 200px);">
            <ul class="space-y-1">
                <li>
                    <a href="dashboard.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-tachometer-alt w-6 text-center"></i>
                        <span class="ml-2 nav-text transition-opacity duration-300 truncate">Tableau de bord</span>
                    </a>
                </li>
                <li>
                    <a href="my-formations.php" class="flex items-center px-3 py-2 text-base rounded-lg bg-netblue-100 dark:bg-netblue-900/30 text-netblue-800 dark:text-netblue-300">
                        <i class="fas fa-graduation-cap w-6 text-center"></i>
                        <span class="ml-2 nav-text transition-opacity duration-300 truncate">Mes formations</span>
                    </a>
                </li>
                <li>
                    <a href="formation-favorites.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-heart w-6 text-center"></i>
                        <span class="ml-2 nav-text transition-opacity duration-300 truncate">Favoris</span>
                        <?php if (!empty($_SESSION['formation_favorites'])): ?>
                        <span class="ml-auto bg-red-500 text-white text-xs rounded-full h-5 min-w-[1.25rem] flex items-center justify-center nav-text transition-opacity duration-300">
                            <?php echo count($_SESSION['formation_favorites']); ?>
                        </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="certificates.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-certificate w-6 text-center"></i>
                        <span class="ml-2 nav-text transition-opacity duration-300 truncate">Certificats</span>
                    </a>
                </li>
                <li>
                    <a href="forum.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-comments w-6 text-center"></i>
                        <span class="ml-2 nav-text transition-opacity duration-300 truncate">Forum</span>
                    </a>
                </li>
                <li>
                    <a href="quiz.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-question-circle w-6 text-center"></i>
                        <span class="ml-2 nav-text transition-opacity duration-300 truncate">Quiz</span>
                    </a>
                </li>
                <li class="pt-2 mt-2 border-t border-gray-200 dark:border-gray-700">
                    <a href="profile.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-user-edit w-6 text-center"></i>
                        <span class="ml-2 nav-text transition-opacity duration-300 truncate">Modifier profil</span>
                    </a>
                </li>
                <li>
                    <a href="settings.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-cog w-6 text-center"></i>
                        <span class="ml-2 nav-text transition-opacity duration-300 truncate">Paramètres</span>
                    </a>
                </li>
            </ul>
        </nav>
        
        <!-- Logout and Theme Toggle -->
        <div class="absolute bottom-0 left-0 right-0 border-t border-gray-200 dark:border-gray-700 p-4 bg-white dark:bg-gray-800">
            <div class="flex items-center mb-4">
                <span class="text-gray-700 dark:text-gray-300 mr-2 nav-text transition-opacity duration-300 truncate">Mode sombre</span>
                <label class="theme-switch relative inline-block w-12 h-6 ml-auto">
                    <input type="checkbox" id="darkModeToggle" class="opacity-0 w-0 h-0">
                    <span class="slider absolute cursor-pointer inset-0 bg-gray-300 rounded-full transition-all duration-300 before:absolute before:h-4 before:w-4 before:left-1 before:bottom-1 before:bg-white before:rounded-full before:transition-all before:duration-300"></span>
                </label>
            </div>
            <a href="logout.php" class="flex items-center justify-center px-3 py-2 rounded-lg text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors duration-200">
                <i class="fas fa-sign-out-alt w-6 text-center"></i>
                <span class="ml-2 nav-text transition-opacity duration-300 truncate">Déconnexion</span>
            </a>
        </div>
    </aside>
    
    <!-- Main Content -->
    <div id="content" class="content-area transition-all duration-300">
        <!-- Top Bar -->
        <header class="bg-white dark:bg-gray-800 shadow-sm sticky top-0 z-20">
            <div class="flex items-center justify-between px-4 py-3">
                <!-- Mobile Menu Toggle -->
                <button id="mobile-menu-toggle" class="md:hidden text-gray-700 dark:text-white focus:outline-none">
                    <i class="fas fa-bars text-2xl"></i>
                </button>
                
                <!-- Page Title -->
                <h1 class="text-xl font-bold text-gray-800 dark:text-white">Mes Formations</h1>
                
                <!-- Right Menu -->
                <div class="flex items-center">
                    <a href="formations.php" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded-lg transition-colors hidden md:block">
                        <i class="fas fa-search mr-2"></i>Explorer les formations
                    </a>
                    <!-- Mobile-Only Explore Button -->
                    <a href="formations.php" class="text-gray-700 dark:text-white md:hidden">
                        <i class="fas fa-search text-2xl"></i>
                    </a>
                </div>
            </div>
        </header>
        
        <!-- Main Content Area -->
        <main class="p-4">
            <!-- Summary Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-5 gap-3 sm:gap-6 mb-6">
                <!-- All Formations -->
                <a href="?status=all" class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 sm:p-6 flex flex-col items-center justify-center text-center <?php echo $status_filter == 'all' ? 'ring-2 ring-netblue-500 dark:ring-netblue-400' : ''; ?>" data-aos="fade-up" data-aos-delay="100">
                    <div class="text-4xl font-bold text-gray-800 dark:text-white mb-2"><?php echo $summary['all']; ?></div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Toutes</div>
                </a>
                
                <!-- Active Formations -->
                <a href="?status=active" class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 sm:p-6 flex flex-col items-center justify-center text-center <?php echo $status_filter == 'active' ? 'ring-2 ring-netblue-500 dark:ring-netblue-400' : ''; ?>" data-aos="fade-up" data-aos-delay="200">
                    <div class="text-4xl font-bold text-green-600 dark:text-green-400 mb-2"><?php echo $summary['active']; ?></div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Actives</div>
                </a>
                
                <!-- Completed Formations -->
                <a href="?status=active&sort=progress" class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 sm:p-6 flex flex-col items-center justify-center text-center <?php echo $status_filter == 'active' && $sort_by == 'progress' ? 'ring-2 ring-netblue-500 dark:ring-netblue-400' : ''; ?>" data-aos="fade-up" data-aos-delay="300">
                    <div class="text-4xl font-bold text-netblue-600 dark:text-netblue-400 mb-2"><?php echo $summary['completed']; ?></div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Terminées</div>
                </a>
                
                <!-- Pending Formations -->
                <a href="?status=pending" class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 sm:p-6 flex flex-col items-center justify-center text-center <?php echo $status_filter == 'pending' ? 'ring-2 ring-netblue-500 dark:ring-netblue-400' : ''; ?>" data-aos="fade-up" data-aos-delay="400">
                    <div class="text-4xl font-bold text-yellow-600 dark:text-yellow-400 mb-2"><?php echo $summary['pending']; ?></div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">En attente</div>
                </a>
                
                <!-- Expired Formations -->
                <a href="?status=expired" class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 sm:p-6 flex flex-col items-center justify-center text-center <?php echo $status_filter == 'expired' ? 'ring-2 ring-netblue-500 dark:ring-netblue-400' : ''; ?>" data-aos="fade-up" data-aos-delay="500">
                    <div class="text-4xl font-bold text-gray-500 dark:text-gray-400 mb-2"><?php echo $summary['expired']; ?></div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Expirées</div>
                </a>
            </div>
            
            <!-- Filters and Search -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 mb-6" data-aos="fade-up">
                <div class="flex flex-col sm:flex-row gap-4">
                    <!-- Search -->
                    <div class="flex-grow">
                        <form action="my-formations.php" method="GET" class="relative">
                            <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                            <input type="hidden" name="sort" value="<?php echo $sort_by; ?>">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Rechercher une formation..." class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                            <button type="submit" class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 dark:text-gray-500">
                                <i class="fas fa-search"></i>
                            </button>
                            <?php if (!empty($search_term)): ?>
                            <a href="?status=<?php echo $status_filter; ?>&sort=<?php echo $sort_by; ?>" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300">
                                <i class="fas fa-times"></i>
                            </a>
                            <?php endif; ?>
                        </form>
                    </div>
                    
                    <!-- Sort -->
                    <div class="sm:w-52">
                        <form action="my-formations.php" method="GET" id="sort-form">
                            <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_term); ?>">
                            <select name="sort" onchange="this.form.submit()" class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 bg-white dark:bg-gray-700 text-gray-800 dark:text-white">
                                <option value="expiry" <?php echo $sort_by == 'expiry' ? 'selected' : ''; ?>>Trier par : Date d'expiration</option>
                                <option value="title" <?php echo $sort_by == 'title' ? 'selected' : ''; ?>>Trier par : Titre</option>
                                <option value="category" <?php echo $sort_by == 'category' ? 'selected' : ''; ?>>Trier par : Catégorie</option>
                                <option value="progress" <?php echo $sort_by == 'progress' ? 'selected' : ''; ?>>Trier par : Progression</option>
                                <option value="recent" <?php echo $sort_by == 'recent' ? 'selected' : ''; ?>>Trier par : Plus récents</option>
                            </select>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Formations List -->
            <?php if (empty($formations)): ?>
            <!-- Empty state -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8 text-center" data-aos="fade-up">
                <div class="mb-6 text-gray-400 dark:text-gray-500">
                    <i class="fas fa-graduation-cap text-6xl"></i>
                </div>
                <h2 class="text-2xl font-bold mb-4 dark:text-white">Aucune formation trouvée</h2>
                <p class="text-gray-600 dark:text-gray-400 mb-6 max-w-md mx-auto">
                    <?php if (!empty($search_term)): ?>
                        Aucune formation ne correspond à votre recherche "<?php echo htmlspecialchars($search_term); ?>". Veuillez essayer avec d'autres termes ou explorer notre catalogue.
                    <?php elseif ($status_filter === 'active'): ?>
                        Vous n'avez pas encore de formations actives. Explorez notre catalogue pour trouver des formations qui vous intéressent.
                    <?php elseif ($status_filter === 'pending'): ?>
                        Vous n'avez pas de demandes d'abonnement en attente.
                    <?php elseif ($status_filter === 'expired'): ?>
                        Vous n'avez pas de formations expirées.
                    <?php else: ?>
                        Vous n'êtes pas encore inscrit à des formations. Explorez notre catalogue pour commencer votre parcours d'apprentissage.
                    <?php endif; ?>
                </p>
                <a href="formations.php" class="inline-block bg-netblue-600 hover:bg-netblue-700 text-white font-bold py-3 px-6 rounded-lg transition-colors">
                    <i class="fas fa-search mr-2"></i>Explorer les formations
                </a>
            </div>
            <?php else: ?>
            <!-- Formations Grid -->
            <div class="space-y-6">
                <?php foreach ($formations as $formation): ?>
                <div class="formation-card bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden" data-aos="fade-up">
                    <div class="flex flex-col sm:flex-row">
                        <!-- Formation Image -->
                        <div class="sm:w-1/4 h-48 sm:h-auto relative">
                            <img src="../<?php echo htmlspecialchars($formation['cover_image']); ?>" alt="<?php echo htmlspecialchars($formation['title']); ?>" class="w-full h-full object-cover">
                            
                            <!-- Category Badge -->
                            <div class="absolute top-2 left-2 bg-netblue-600 text-white text-xs font-bold px-2 py-1 rounded">
                                <i class="fas <?php echo htmlspecialchars($formation['category_icon']); ?> mr-1"></i>
                                <?php echo htmlspecialchars($formation['category_name']); ?>
                            </div>
                            
                            <!-- Status Badge -->
                            <?php if ($formation['subscription_status'] === 'active' && !$formation['is_expired']): ?>
                                <div class="absolute bottom-2 left-2 bg-green-600 text-white text-xs font-bold px-2 py-1 rounded">
                                    Active
                                </div>
                            <?php elseif ($formation['subscription_status'] === 'pending'): ?>
                                <div class="absolute bottom-2 left-2 bg-yellow-600 text-white text-xs font-bold px-2 py-1 rounded">
                                    En attente
                                </div>
                            <?php else: ?>
                                <div class="absolute bottom-2 left-2 bg-gray-600 text-white text-xs font-bold px-2 py-1 rounded">
                                    Expirée
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Formation Details -->
                        <div class="sm:w-3/4 p-4 flex flex-col">
                            <div class="flex justify-between items-start mb-2">
                                <h3 class="text-xl font-bold dark:text-white"><?php echo htmlspecialchars($formation['title']); ?></h3>
                                
                                <!-- Level Badge -->
                                <span class="text-xs px-2 py-1 rounded-full
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
                            
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                                <?php 
                                if (!empty($formation['short_description'])) {
                                    echo htmlspecialchars($formation['short_description']);
                                } else {
                                    echo htmlspecialchars(substr($formation['description'], 0, 150)) . (strlen($formation['description']) > 150 ? '...' : '');
                                }
                                ?>
                            </p>
                            
                            <?php if ($formation['subscription_status'] === 'active' && !$formation['is_expired']): ?>
                            <!-- Progress Bar -->
                            <div class="mb-3">
                                <div class="flex items-center justify-between text-sm mb-1">
                                    <span class="text-gray-600 dark:text-gray-400">Progression</span>
                                    <span class="font-medium text-gray-800 dark:text-white"><?php echo $formation['progress_percentage']; ?>%</span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                    <div class="bg-netblue-600 h-2 rounded-full" style="width: <?php echo $formation['progress_percentage']; ?>%"></div>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    <?php echo $formation['completed_videos']; ?> sur <?php echo $formation['total_videos']; ?> vidéos terminées
                                </div>
                            </div>
                            
                            <!-- Last Watched Video -->
                            <?php if (!empty($formation['last_video'])): ?>
                            <div class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                <p class="font-medium">Dernière activité :</p>
                                <div class="mt-1 bg-gray-100 dark:bg-gray-700 p-2 rounded">
                                    <div class="line-clamp-1 font-medium text-gray-800 dark:text-white">
                                        <?php echo htmlspecialchars($formation['last_video']['title']); ?>
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        <?php if (!empty($formation['last_video']['last_watched'])): ?>
                                            Module: <?php echo htmlspecialchars($formation['last_video']['module_title']); ?> · 
                                            <?php 
                                            $last_watched = new DateTime($formation['last_video']['last_watched']);
                                            $now = new DateTime();
                                            $diff = $now->diff($last_watched);
                                            
                                            if ($diff->days > 0) {
                                                echo 'Il y a ' . $diff->days . ' jour' . ($diff->days > 1 ? 's' : '');
                                            } elseif ($diff->h > 0) {
                                                echo 'Il y a ' . $diff->h . ' heure' . ($diff->h > 1 ? 's' : '');
                                            } else {
                                                echo 'Il y a ' . $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
                                            }
                                            ?>
                                        <?php else: ?>
                                            Module: <?php echo htmlspecialchars($formation['last_video']['module_title']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Subscription Info -->
                            <div class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                <p>Abonnement expirant le <?php echo date('d/m/Y', strtotime($formation['end_date'])); ?> 
                                    <span class="<?php echo $formation['days_remaining'] <= 7 ? 'text-red-600 dark:text-red-400 font-medium' : ''; ?>">
                                        (<?php echo $formation['days_remaining']; ?> jour<?php echo $formation['days_remaining'] > 1 ? 's' : ''; ?> restant<?php echo $formation['days_remaining'] > 1 ? 's' : ''; ?>)
                                    </span>
                                </p>
                            </div>
                            <?php elseif ($formation['subscription_status'] === 'pending'): ?>
                            <!-- Pending Subscription Info -->
                            <div class="mb-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-3">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 pt-0.5">
                                        <i class="fas fa-clock text-yellow-600 dark:text-yellow-400"></i>
                                    </div>
                                    <div class="ml-3">
                                        <h4 class="text-sm font-medium text-yellow-800 dark:text-yellow-300">Abonnement en attente de validation</h4>
                                        <p class="mt-1 text-xs text-yellow-700 dark:text-yellow-400">
                                            Durée : <?php echo $formation['subscription_months']; ?> mois · 
                                            Demande soumise le <?php echo date('d/m/Y', strtotime($formation['subscription_date'])); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <!-- Expired Subscription Info -->
                            <div class="mb-3 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg p-3">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 pt-0.5">
                                        <i class="fas fa-calendar-times text-gray-600 dark:text-gray-400"></i>
                                    </div>
                                    <div class="ml-3">
                                        <h4 class="text-sm font-medium text-gray-800 dark:text-white">Abonnement expiré</h4>
                                        <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                                            Abonnement terminé le <?php echo date('d/m/Y', strtotime($formation['end_date'])); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Action Buttons -->
                            <div class="mt-auto flex flex-wrap gap-2">
                                <?php if ($formation['subscription_status'] === 'active' && !$formation['is_expired']): ?>
                                    <a href="watch.php?video_id=<?php echo !empty($formation['last_video']) ? $formation['last_video']['id'] : ''; ?>&formation_id=<?php echo $formation['id']; ?>" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded text-sm font-medium transition-colors flex-grow text-center">
                                        <i class="fas fa-play mr-1"></i>Continuer
                                    </a>
                                    <a href="formation-details.php?id=<?php echo $formation['id']; ?>" class="bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-white px-4 py-2 rounded text-sm font-medium transition-colors text-center">
                                        <i class="fas fa-info-circle mr-1"></i>Détails
                                    </a>
                                    <a href="forum.php?formation_id=<?php echo $formation['id']; ?>" class="bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-white px-4 py-2 rounded text-sm font-medium transition-colors text-center">
                                        <i class="fas fa-comments mr-1"></i>Forum
                                    </a>
                                <?php elseif ($formation['subscription_status'] === 'pending'): ?>
                                    <a href="subscribe.php?formation_id=<?php echo $formation['id']; ?>" class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded text-sm font-medium transition-colors flex-grow text-center">
                                        <i class="fas fa-edit mr-1"></i>Modifier la demande
                                    </a>
                                    <a href="formation-details.php?id=<?php echo $formation['id']; ?>" class="bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-white px-4 py-2 rounded text-sm font-medium transition-colors text-center">
                                        <i class="fas fa-info-circle mr-1"></i>Détails
                                    </a>
                                <?php else: ?>
                                    <a href="subscribe.php?formation_id=<?php echo $formation['id']; ?>" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded text-sm font-medium transition-colors flex-grow text-center">
                                        <i class="fas fa-sync mr-1"></i>Renouveler
                                    </a>
                                    <a href="formation-details.php?id=<?php echo $formation['id']; ?>" class="bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-white px-4 py-2 rounded text-sm font-medium transition-colors text-center">
                                        <i class="fas fa-info-circle mr-1"></i>Détails
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Recommended Formations -->
            <div class="mt-12">
                <h2 class="text-xl font-bold mb-6 dark:text-white">Formations recommandées pour vous</h2>
                
                <div id="recommended-loader" class="flex justify-center py-8">
                    <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-netblue-600"></div>
                </div>
                
                <div id="recommended-formations" class="grid grid-cols-1 md:grid-cols-3 gap-6 hidden">
                    <!-- Recommendations will be loaded here via AJAX -->
                </div>
            </div>
        </main>
        
        <!-- Footer -->
        <footer class="bg-white dark:bg-gray-800 shadow-lg mt-auto py-4">
            <div class="px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col sm:flex-row justify-between items-center">
                    <div class="text-center sm:text-left mb-4 sm:mb-0">
                        <p class="text-gray-600 dark:text-gray-400">
                            © 2023 Netcrafter. Tous droits réservés.
                        </p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <a href="https://www.facebook.com/share/1Y7kHRs16L/" class="text-gray-600 dark:text-gray-400 hover:text-netblue-600 dark:hover:text-netblue-400">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        
                        <a href="https://www.instagram.com/netcrafter.niger?igsh=NzJ2bzM2aWRnMzho" class="text-gray-600 dark:text-gray-400 hover:text-netblue-600 dark:hover:text-netblue-400">
                            <i class="fab fa-instagram"></i>
                        </a>
                    </div>
                </div>
            </div>
        </footer>
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
            // Sidenav toggle
            const sidenav = document.getElementById('sidenav');
            const sidenavToggle = document.getElementById('sidenav-toggle');
            const content = document.getElementById('content');
            const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
            const overlay = document.getElementById('overlay');
            
            // Function to toggle sidenav on desktop
            function toggleSidenav() {
                if (window.innerWidth >= 768) {
                    sidenav.classList.toggle('collapsed');
                    content.classList.toggle('nav-collapsed');
                    
                    // Save preference
                    const isCollapsed = sidenav.classList.contains('collapsed');
                    localStorage.setItem('sidenavCollapsed', isCollapsed.toString());
                }
            }
            
            // Function to toggle sidenav on mobile
            function toggleMobileMenu() {
                sidenav.classList.toggle('open');
                overlay.classList.toggle('active');
                document.body.classList.toggle('overflow-hidden');
            }
            
            sidenavToggle.addEventListener('click', toggleSidenav);
            mobileMenuToggle.addEventListener('click', toggleMobileMenu);
            
            // Check for saved sidenav state
            const savedSidenavState = localStorage.getItem('sidenavCollapsed');
            if (savedSidenavState === 'true' && window.innerWidth >= 768) {
                sidenav.classList.add('collapsed');
                content.classList.add('nav-collapsed');
            }
            
            // Dark mode toggle
            const darkModeToggle = document.getElementById('darkModeToggle');
            const htmlElement = document.documentElement;
            
            // Check for saved theme preference
            if (localStorage.getItem('darkMode') === 'enabled') {
                htmlElement.classList.add('dark');
                darkModeToggle.checked = true;
            }
            
            // Function to toggle dark mode
            function toggleDarkMode() {
                if (htmlElement.classList.contains('dark')) {
                    htmlElement.classList.remove('dark');
                    localStorage.setItem('darkMode', 'disabled');
                    darkModeToggle.checked = false;
                } else {
                    htmlElement.classList.add('dark');
                    localStorage.setItem('darkMode', 'enabled');
                    darkModeToggle.checked = true;
                }
            }
            
            // Event listener for toggle switch
            darkModeToggle.addEventListener('change', toggleDarkMode);
            
            // Handle window resizing
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 768) {
                    // Reset mobile menu state when switching to desktop
                    sidenav.classList.remove('open');
                    overlay.classList.remove('active');
                    document.body.classList.remove('overflow-hidden');
                    
                    // Apply saved collapsed state
                    const savedSidenavState = localStorage.getItem('sidenavCollapsed');
                    if (savedSidenavState === 'true') {
                        sidenav.classList.add('collapsed');
                        content.classList.add('nav-collapsed');
                    } else {
                        sidenav.classList.remove('collapsed');
                        content.classList.remove('nav-collapsed');
                    }
                }
            });
            
            // Load recommended formations
            loadRecommendedFormations();
        });
        
        // Load recommended formations via AJAX
        function loadRecommendedFormations() {
            // Simulating AJAX request
            setTimeout(() => {
                const loader = document.getElementById('recommended-loader');
                const recommendedFormations = document.getElementById('recommended-formations');
                
                // Hide loader and show formations
                loader.classList.add('hidden');
                recommendedFormations.classList.remove('hidden');
                
                // Demo data for recommendations - in a real application, this would come from the server
                const recommendations = [
                    {
                        id: 1,
                        title: "Introduction à la cybersécurité",
                        category_name: "Hacking Éthique",
                        category_icon: "fa-shield-alt",
                        cover_image: "image/formations/default-1.jpg",
                        level: "debutant",
                        price_per_month: 20000,
                        short_description: "Cette formation vous introduit aux principes fondamentaux de la cybersécurité, y compris l'identification des vulnérabilités, les types d'attaques courants et les pratiques de sécurité de base."
                    },
                    {
                        id: 2,
                        title: "Excel Avancé",
                        category_name: "Informatique Bureautique",
                        category_icon: "fa-desktop",
                        cover_image: "image/formations/default-2.jpg",
                        level: "avance",
                        price_per_month: 15000,
                        short_description: "Formation avancée sur Excel, concentrée sur les fonctions complexes, les tableaux croisés dynamiques, l'analyse de données et l'automatisation avec les macros VBA."
                    },
                    {
                        id: 3,
                        title: "Création de site e-commerce avec WooCommerce",
                        category_name: "E-Commerce",
                        category_icon: "fa-shopping-cart",
                        cover_image: "image/formations/default-3.jpg",
                        level: "intermediaire",
                        price_per_month: 18000,
                        short_description: "Apprenez à créer et gérer une boutique en ligne complète avec WordPress et WooCommerce, incluant la configuration des paiements, la gestion des produits et l'optimisation SEO."
                    }
                ];
                
                // Generate HTML for recommendations
                let html = '';
                recommendations.forEach(formation => {
                    html += `
                    <div class="formation-card bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden" data-aos="fade-up">
                        <!-- Formation Image -->
                        <div class="relative h-48">
                            <img src="../${formation.cover_image}" alt="${formation.title}" class="w-full h-full object-cover">
                            
                            <!-- Category Badge -->
                            <div class="absolute top-3 left-3 bg-netblue-600 text-white text-xs font-bold px-2 py-1 rounded">
                                <i class="fas ${formation.category_icon} mr-1"></i>
                                ${formation.category_name}
                            </div>
                            
                            <!-- Add to Favorites Button -->
                            <form method="POST" action="formations.php" class="absolute top-3 right-3">
                                <input type="hidden" name="formation_id" value="${formation.id}">
                                <input type="hidden" name="action" value="add_to_favorites">
                                <button type="submit" class="bg-white dark:bg-gray-700 text-gray-400 h-8 w-8 rounded-full flex items-center justify-center shadow-md hover:bg-gray-100 dark:hover:bg-gray-600 hover:text-red-500 transition-colors">
                                    <i class="far fa-heart"></i>
                                </button>
                            </form>
                            
                            <!-- Level Badge -->
                            <div class="absolute bottom-3 left-3 text-white text-xs font-bold px-2 py-1 rounded
                                ${formation.level === 'debutant' ? 'bg-green-600' : 
                                  formation.level === 'intermediaire' ? 'bg-yellow-600' : 
                                  'bg-red-600'}">
                                ${formation.level === 'debutant' ? 'Débutant' : 
                                  formation.level === 'intermediaire' ? 'Intermédiaire' : 
                                  'Avancé'}
                            </div>
                        </div>
                        
                        <!-- Formation Details -->
                        <div class="p-4">
                            <h3 class="text-lg font-bold mb-2 text-gray-800 dark:text-white line-clamp-2">
                                ${formation.title}
                            </h3>
                            
                            <p class="text-gray-600 dark:text-gray-300 text-sm mb-4 line-clamp-2">
                                ${formation.short_description}
                            </p>
                            
                            <div class="flex justify-between items-center">
                                <div class="text-netblue-600 dark:text-netblue-400 font-bold">
                                    ${formation.price_per_month.toLocaleString()} FCFA<span class="text-sm font-normal">/mois</span>
                                </div>
                                
                                <a href="formation-details.php?id=${formation.id}" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded text-sm font-medium transition-colors">
                                    <i class="fas fa-info-circle mr-1"></i>Détails
                                </a>
                            </div>
                        </div>
                    </div>
                    `;
                });
                
                recommendedFormations.innerHTML = html;
                
                // Re-initialize AOS for dynamically loaded content
                AOS.refresh();
            }, 1500); // Simulate loading time
        }
        
        // Make toggleMobileMenu function globally accessible
        function toggleMobileMenu() {
            const sidenav = document.getElementById('sidenav');
            const overlay = document.getElementById('overlay');
            
            sidenav.classList.toggle('open');
            overlay.classList.toggle('active');
            document.body.classList.toggle('overflow-hidden');
        }
    </script>
</body>
</html>