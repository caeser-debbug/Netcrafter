<?php
// Initialisation de la session
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    // Sauvegarder l'URL actuelle pour y revenir après la connexion
    $_SESSION['redirect_url'] = "dashboard.php";
    
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

// Récupérer les formations actives de l'utilisateur
$active_formations_query = "SELECT f.*, c.name as category_name, c.icon as category_icon, 
                          fs.start_date, fs.end_date, fs.status as subscription_status,
                          fs.subscription_months
                          FROM formations f 
                          JOIN formation_categories c ON f.category_id = c.id 
                          JOIN formation_subscriptions fs ON f.id = fs.formation_id 
                          WHERE fs.user_id = ? 
                          AND fs.status = 'active' 
                          AND fs.end_date >= CURDATE() 
                          ORDER BY fs.end_date ASC";
$stmt = $conn->prepare($active_formations_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$active_formations_result = $stmt->get_result();
$active_formations = [];

while ($formation = $active_formations_result->fetch_assoc()) {
    // Si l'image de couverture n'est pas définie, utiliser une image par défaut
    if (empty($formation['cover_image'])) {
        $formation['cover_image'] = "image/formations/default-" . rand(1, 3) . ".jpg";
    }
    
    // Calculer les jours restants avant expiration
    $end_date = new DateTime($formation['end_date']);
    $today = new DateTime();
    $days_remaining = $today->diff($end_date)->days;
    $formation['days_remaining'] = $days_remaining;
    
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
    $last_video_query = "SELECT fv.id, fv.title, fm.id as module_id, fm.title as module_title
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
        }
    }
    
    $active_formations[] = $formation;
}

// Récupérer les formations en attente de validation
$pending_formations_query = "SELECT f.*, c.name as category_name, c.icon as category_icon, 
                           fs.created_at as subscription_date, fs.payment_method,
                           fs.amount_paid, fs.subscription_months
                           FROM formations f 
                           JOIN formation_categories c ON f.category_id = c.id 
                           JOIN formation_subscriptions fs ON f.id = fs.formation_id 
                           WHERE fs.user_id = ? 
                           AND fs.status = 'pending' 
                           ORDER BY fs.created_at DESC";
$stmt = $conn->prepare($pending_formations_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pending_formations_result = $stmt->get_result();
$pending_formations = [];

while ($formation = $pending_formations_result->fetch_assoc()) {
    // Si l'image de couverture n'est pas définie, utiliser une image par défaut
    if (empty($formation['cover_image'])) {
        $formation['cover_image'] = "image/formations/default-" . rand(1, 3) . ".jpg";
    }
    
    $pending_formations[] = $formation;
}

// Récupérer les certificats obtenus
$certificates_query = "SELECT c.*, f.title as formation_title, f.id as formation_id,
                      qa.score, c.created_at as certificate_date
                      FROM certificates c
                      JOIN formations f ON c.formation_id = f.id
                      JOIN quiz_attempts qa ON c.quiz_attempt_id = qa.id
                      WHERE c.user_id = ?
                      ORDER BY c.created_at DESC";
$stmt = $conn->prepare($certificates_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$certificates_result = $stmt->get_result();
$certificates = [];

while ($certificate = $certificates_result->fetch_assoc()) {
    $certificates[] = $certificate;
}

// Récupérer les statistiques générales
$stats = [
    'active_formations' => count($active_formations),
    'pending_formations' => count($pending_formations),
    'certificates' => count($certificates),
    'total_videos_watched' => 0
];

// Calculer le nombre total de vidéos visionnées
$videos_query = "SELECT COUNT(*) as total_videos_watched
                FROM video_progress
                WHERE user_id = ? AND is_completed = 1";
$stmt = $conn->prepare($videos_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$videos_result = $stmt->get_result();
$videos_data = $videos_result->fetch_assoc();

$stats['total_videos_watched'] = $videos_data['total_videos_watched'];

// Fermeture de la connexion à la base de données
$conn->close();
?>

<!DOCTYPE html>
<html lang="fr" class="light scroll">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('form.dashboard') ?> - Netcrafter</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
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
        
        /* Mobile optimizations */
        @media (max-width: 640px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
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
    <?php
        $curLang   = $GLOBALS['nc_lang'] ?? 'fr';
        $switchLang = $curLang === 'fr' ? 'en' : 'fr';
        $switchLabel = $curLang === 'fr' ? 'EN' : 'FR';
        $switchUrl  = strtok($_SERVER['REQUEST_URI'],'?').'?'.http_build_query(array_merge($_GET,['lang'=>$switchLang]));
    ?>
    <?php include __DIR__ . '/nc-theme.php'; ?>
</head>
<body style="background:linear-gradient(180deg,#060d1e 0%,#030810 100%)">
    <!-- Overlay for mobile menu -->
    <div id="overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 overlay" onclick="toggleMobileMenu()"></div>
    
    <!-- Side Navigation -->
    <aside id="sidenav" class="sidenav fixed h-full shadow-lg z-50" style="background:rgba(6,13,30,0.97);border-right:1px solid rgba(0,200,255,0.1)">
        <!-- Logo and collapse button -->
        <div class="flex items-center justify-between px-4 py-3" style="border-bottom:1px solid rgba(0,200,255,0.1)">
            <div class="flex items-center sidenav-title">
                <img src="../image/logo-n.png" alt="Netcrafter Logo" class="h-8 mr-2">
                <span class="text-lg font-bold transition-opacity duration-300 whitespace-nowrap" style="color:#00c8ff">NETCRAFTER</span>
            </div>
            <button id="sidenav-toggle" class="sidenav-toggle text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 focus:outline-none md:block hidden">
                <i class="fas fa-chevron-left"></i>
            </button>
        </div>
        
        <!-- User info -->
        <div class="px-4 py-4" style="border-bottom:1px solid rgba(0,200,255,0.1)">
            <div class="flex items-center">
                <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-lg flex-shrink-0" style="background:linear-gradient(135deg,#00c8ff,#0066cc)">
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
                    <a href="dashboard.php" class="flex items-center px-3 py-2 text-base rounded-lg" style="background:rgba(0,200,255,0.1);color:#00c8ff">
                        <i class="fas fa-tachometer-alt w-6 text-center"></i>
                        <span class="ml-2 nav-text transition-opacity duration-300 truncate"><?= t('form.dashboard') ?></span>
                    </a>
                </li>
                <li>
                    <a href="my-formations.php" class="flex items-center px-3 py-2 text-base rounded-lg hover:text-nc-cyan transition-colors duration-200" style="color:#94a3b8">
                        <i class="fas fa-graduation-cap w-6 text-center"></i>
                        <span class="ml-2 nav-text transition-opacity duration-300 truncate"><?= t('form.my_formations') ?></span>
                    </a>
                </li>
                <li>
                    <a href="formation-favorites.php" class="flex items-center px-3 py-2 text-base rounded-lg hover:text-nc-cyan transition-colors duration-200" style="color:#94a3b8">
                        <i class="fas fa-heart w-6 text-center"></i>
                        <span class="ml-2 nav-text transition-opacity duration-300 truncate"><?= t('form.favorites') ?></span>
                        <?php if (!empty($_SESSION['formation_favorites'])): ?>
                        <span class="ml-auto bg-red-500 text-white text-xs rounded-full h-5 min-w-[1.25rem] flex items-center justify-center nav-text transition-opacity duration-300">
                            <?php echo count($_SESSION['formation_favorites']); ?>
                        </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="certificates.php" class="flex items-center px-3 py-2 text-base rounded-lg hover:text-nc-cyan transition-colors duration-200" style="color:#94a3b8">
                        <i class="fas fa-certificate w-6 text-center"></i>
                        <span class="ml-2 nav-text transition-opacity duration-300 truncate"><?= t('form.certificates') ?></span>
                        <?php if (!empty($certificates)): ?>
                        <span class="ml-auto bg-netblue-500 text-white text-xs rounded-full h-5 min-w-[1.25rem] flex items-center justify-center nav-text transition-opacity duration-300">
                            <?php echo count($certificates); ?>
                        </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="forum.php" class="flex items-center px-3 py-2 text-base rounded-lg hover:text-nc-cyan transition-colors duration-200" style="color:#94a3b8">
                        <i class="fas fa-comments w-6 text-center"></i>
                        <span class="ml-2 nav-text transition-opacity duration-300 truncate">Forum</span>
                    </a>
                </li>
                <li>
                    <a href="quiz.php" class="flex items-center px-3 py-2 text-base rounded-lg hover:text-nc-cyan transition-colors duration-200" style="color:#94a3b8">
                        <i class="fas fa-question-circle w-6 text-center"></i>
                        <span class="ml-2 nav-text transition-opacity duration-300 truncate">Quiz</span>
                    </a>
                </li>
                <li class="pt-2 mt-2" style="border-top:1px solid rgba(0,200,255,0.1)">
                    <a href="profile.php" class="flex items-center px-3 py-2 text-base rounded-lg hover:text-nc-cyan transition-colors duration-200" style="color:#94a3b8">
                        <i class="fas fa-user-edit w-6 text-center"></i>
                        <span class="ml-2 nav-text transition-opacity duration-300 truncate"><?= t('nav.edit_profile') ?></span>
                    </a>
                </li>
                <li>
                    <a href="settings.php" class="flex items-center px-3 py-2 text-base rounded-lg hover:text-nc-cyan transition-colors duration-200" style="color:#94a3b8">
                        <i class="fas fa-cog w-6 text-center"></i>
                        <span class="ml-2 nav-text transition-opacity duration-300 truncate">Paramètres</span>
                    </a>
                </li>
            </ul>
        </nav>
        
        <!-- Logout and Lang -->
        <div class="absolute bottom-0 left-0 right-0 p-4" style="border-top:1px solid rgba(0,200,255,0.1);background:rgba(6,13,30,0.97)">
            <div class="flex items-center mb-3">
                <a href="<?= htmlspecialchars($switchUrl) ?>" class="nc-lang-btn nav-text transition-opacity duration-300 truncate w-full justify-center">
                    <i class="fas fa-globe text-xs"></i><?= $switchLabel ?>
                </a>
            </div>
            <a href="logout.php" class="flex items-center justify-center px-3 py-2 rounded-lg text-red-400 hover:text-red-300 transition-colors duration-200">
                <i class="fas fa-sign-out-alt w-6 text-center"></i>
                <span class="ml-2 nav-text transition-opacity duration-300 truncate"><?= t('nav.logout') ?></span>
            </a>
        </div>
    </aside>
    
    <!-- Main Content -->
    <div id="content" class="content-area transition-all duration-300">
        <!-- Top Bar -->
        <header class="sticky top-0 z-20" style="background:rgba(6,13,30,0.96);border-bottom:1px solid rgba(0,200,255,0.1);backdrop-filter:blur(20px)">
            <div class="flex items-center justify-between px-4 py-3">
                <button id="mobile-menu-toggle" class="md:hidden focus:outline-none" style="color:#94a3b8">
                    <i class="fas fa-bars text-2xl"></i>
                </button>

                <h1 class="text-xl font-bold text-white"><?= t('form.dashboard') ?></h1>

                <div class="flex items-center gap-3">
                    <a href="formations.php" class="btn-primary text-sm py-2 px-4 hidden md:inline-flex">
                        <i class="fas fa-search mr-2"></i><?= t('form.all_formations') ?>
                    </a>
                    <a href="formations.php" class="md:hidden" style="color:#94a3b8">
                        <i class="fas fa-search text-2xl"></i>
                    </a>
                </div>
            </div>
        </header>
        
        <!-- Main Content Area -->
        <main class="p-4">
            <!-- Welcome Message -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6 relative overflow-hidden">
                <!-- Background Blobs -->
                <div class="blob bg-blue-500 dark:bg-blue-700" style="top: -150px; right: -150px;"></div>
                <div class="blob bg-purple-500 dark:bg-purple-700" style="bottom: -150px; left: -150px;"></div>
                
                <div class="relative">
                    <h2 class="text-2xl font-bold mb-2 dark:text-white">Bienvenue, <?php echo htmlspecialchars($user['firstname']); ?>!</h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        <?php if (!empty($active_formations)): ?>
                            Vous avez <?php echo count($active_formations); ?> formation<?php echo count($active_formations) > 1 ? 's' : ''; ?> active<?php echo count($active_formations) > 1 ? 's' : ''; ?>.
                            Continuez à apprendre et à progresser!
                        <?php else: ?>
                            Vous n'avez pas encore de formations actives. Explorez notre catalogue pour trouver des formations qui vous intéressent.
                        <?php endif; ?>
                    </p>
                    <a href="formations.php" class="inline-block bg-netblue-600 hover:bg-netblue-700 text-white font-medium px-4 py-2 rounded transition-colors">
                        <i class="fas fa-search mr-2"></i>Explorer les formations
                    </a>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-6 mb-6 stats-grid">
                <!-- Active Formations -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 sm:p-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-blue-100 dark:bg-blue-900 p-3 rounded-lg">
                            <i class="fas fa-graduation-cap text-xl text-blue-600 dark:text-blue-400"></i>
                        </div>
                        <div class="ml-3 sm:ml-4 overflow-hidden">
                            <h3 class="text-xs sm:text-sm font-medium text-gray-600 dark:text-gray-400 truncate">Formations actives</h3>
                            <p class="text-xl sm:text-2xl font-bold text-gray-800 dark:text-white"><?php echo $stats['active_formations']; ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Pending Formations -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 sm:p-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-yellow-100 dark:bg-yellow-900 p-3 rounded-lg">
                            <i class="fas fa-clock text-xl text-yellow-600 dark:text-yellow-400"></i>
                        </div>
                        <div class="ml-3 sm:ml-4 overflow-hidden">
                            <h3 class="text-xs sm:text-sm font-medium text-gray-600 dark:text-gray-400 truncate">En attente</h3>
                            <p class="text-xl sm:text-2xl font-bold text-gray-800 dark:text-white"><?php echo $stats['pending_formations']; ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Certificates -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 sm:p-6" data-aos="fade-up" data-aos-delay="300">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-green-100 dark:bg-green-900 p-3 rounded-lg">
                            <i class="fas fa-certificate text-xl text-green-600 dark:text-green-400"></i>
                        </div>
                        <div class="ml-3 sm:ml-4 overflow-hidden">
                            <h3 class="text-xs sm:text-sm font-medium text-gray-600 dark:text-gray-400 truncate">Certificats obtenus</h3>
                            <p class="text-xl sm:text-2xl font-bold text-gray-800 dark:text-white"><?php echo $stats['certificates']; ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Videos Watched -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 sm:p-6" data-aos="fade-up" data-aos-delay="400">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-purple-100 dark:bg-purple-900 p-3 rounded-lg">
                            <i class="fas fa-video text-xl text-purple-600 dark:text-purple-400"></i>
                        </div>
                        <div class="ml-3 sm:ml-4 overflow-hidden">
                            <h3 class="text-xs sm:text-sm font-medium text-gray-600 dark:text-gray-400 truncate">Vidéos visionnées</h3>
                            <p class="text-xl sm:text-2xl font-bold text-gray-800 dark:text-white"><?php echo $stats['total_videos_watched']; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Active Formations Section -->
            <div class="mb-8">
                <h2 class="text-xl font-bold mb-4 dark:text-white flex items-center justify-between">
                    <span>Mes formations actives</span>
                    <a href="my-formations.php" class="text-sm text-netblue-600 dark:text-netblue-400 hover:underline">
                        Voir tout <i class="fas fa-chevron-right ml-1 text-xs"></i>
                    </a>
                </h2>
                
                <?php if (empty($active_formations)): ?>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 text-center" data-aos="fade-up">
                    <div class="mb-4 text-gray-400 dark:text-gray-500">
                        <i class="fas fa-book-open text-6xl"></i>
                    </div>
                    <h3 class="text-lg font-medium mb-2 dark:text-white">Aucune formation active</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        Vous n'êtes actuellement inscrit à aucune formation. Explorez notre catalogue pour trouver des formations qui vous intéressent.
                    </p>
                    <a href="formations.php" class="inline-block bg-netblue-600 hover:bg-netblue-700 text-white font-medium px-4 py-2 rounded transition-colors">
                        <i class="fas fa-search mr-2"></i>Explorer les formations
                    </a>
                </div>
                <?php else: ?>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <?php foreach ($active_formations as $formation): ?>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden" data-aos="fade-up">
                        <div class="flex flex-col sm:flex-row">
                            <!-- Formation Image -->
                            <div class="sm:w-1/3 h-40 sm:h-auto relative">
                                <img src="../<?php echo htmlspecialchars($formation['cover_image']); ?>" alt="<?php echo htmlspecialchars($formation['title']); ?>" class="w-full h-full object-cover">
                                <div class="absolute top-2 left-2 bg-netblue-600 text-white text-xs font-bold px-2 py-1 rounded">
                                    <i class="fas <?php echo htmlspecialchars($formation['category_icon']); ?> mr-1"></i>
                                    <?php echo htmlspecialchars($formation['category_name']); ?>
                                </div>
                            </div>
                            
                            <!-- Formation Details -->
                            <div class="sm:w-2/3 p-4 flex flex-col">
                                <h3 class="text-lg font-bold mb-2 dark:text-white line-clamp-1">
                                    <?php echo htmlspecialchars($formation['title']); ?>
                                </h3>
                                
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
                                
                                <!-- Subscription Info -->
                                <div class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                    <div class="flex flex-wrap items-center">
                                        <i class="fas fa-calendar-alt mr-2"></i>
                                        <span class="mr-2">Expire le <?php echo date('d/m/Y', strtotime($formation['end_date'])); ?></span>
                                        <?php if ($formation['days_remaining'] <= 7): ?>
                                        <span class="mt-1 sm:mt-0 bg-red-100 text-red-800 text-xs font-medium px-2 py-0.5 rounded dark:bg-red-900 dark:text-red-300">
                                            Plus que <?php echo $formation['days_remaining']; ?> jour<?php echo $formation['days_remaining'] > 1 ? 's' : ''; ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Last Watched Video -->
                                <?php if (!empty($formation['last_video'])): ?>
                                <div class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                                    <p>Dernière activité :</p>
                                    <div class="mt-1 bg-gray-100 dark:bg-gray-700 p-2 rounded">
                                        <div class="line-clamp-1 font-medium text-gray-800 dark:text-white">
                                            <?php echo htmlspecialchars($formation['last_video']['title']); ?>
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            Module: <?php echo htmlspecialchars($formation['last_video']['module_title']); ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Action Buttons -->
                                <div class="mt-auto flex flex-wrap gap-2">
                                    <a href="watch.php?video_id=<?php echo !empty($formation['last_video']) ? $formation['last_video']['id'] : ''; ?>&formation_id=<?php echo $formation['id']; ?>" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded text-sm font-medium transition-colors flex-grow text-center">
                                        <i class="fas fa-play mr-1"></i>Continuer
                                    </a>
                                    <a href="formation-details.php?id=<?php echo $formation['id']; ?>" class="bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-white px-4 py-2 rounded text-sm font-medium transition-colors text-center">
                                        <i class="fas fa-info-circle mr-1"></i>Détails
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Pending Formations Section -->
            <?php if (!empty($pending_formations)): ?>
            <div class="mb-8">
                <h2 class="text-xl font-bold mb-4 dark:text-white">Demandes d'abonnement en cours</h2>
                
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden" data-aos="fade-up">
                    <div class="overflow-x-auto table-container">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th scope="col" class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Formation</th>
                                    <th scope="col" class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                                    <th scope="col" class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Paiement</th>
                                    <th scope="col" class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach ($pending_formations as $formation): ?>
                                <tr>
                                    <td class="px-4 sm:px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 hidden sm:block">
                                                <img class="h-10 w-10 rounded-md object-cover" src="../<?php echo htmlspecialchars($formation['cover_image']); ?>" alt="<?php echo htmlspecialchars($formation['title']); ?>">
                                            </div>
                                            <div class="ml-0 sm:ml-4">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($formation['title']); ?></div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($formation['category_name']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 sm:px-6 py-4">
                                        <div class="text-sm text-gray-900 dark:text-white"><?php echo date('d/m/Y', strtotime($formation['subscription_date'])); ?></div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo $formation['subscription_months']; ?> mois</div>
                                    </td>
                                    <td class="px-4 sm:px-6 py-4">
                                        <div class="text-sm text-gray-900 dark:text-white"><?php echo number_format($formation['amount_paid'], 0, ',', ' '); ?> FCFA</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            Via <?php echo ucfirst($formation['payment_method']); ?>
                                        </div>
                                    </td>
                                    <td class="px-4 sm:px-6 py-4 text-sm font-medium">
                                        <a href="subscribe.php?formation_id=<?php echo $formation['id']; ?>" class="text-netblue-600 hover:text-netblue-900 dark:text-netblue-400 dark:hover:text-netblue-300">Modifier</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Certificates Section -->
            <?php if (!empty($certificates)): ?>
            <div class="mb-8">
                <h2 class="text-xl font-bold mb-4 dark:text-white flex items-center justify-between">
                    <span>Mes certificats récents</span>
                    <a href="certificates.php" class="text-sm text-netblue-600 dark:text-netblue-400 hover:underline">
                        Voir tout <i class="fas fa-chevron-right ml-1 text-xs"></i>
                    </a>
                </h2>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach (array_slice($certificates, 0, 3) as $certificate): ?>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 border border-gray-200 dark:border-gray-700" data-aos="fade-up">
                        <div class="text-center mb-4">
                            <div class="inline-block p-3 rounded-full bg-green-100 dark:bg-green-900 text-green-600 dark:text-green-400">
                                <i class="fas fa-certificate text-3xl"></i>
                            </div>
                        </div>
                        <h3 class="text-lg font-bold text-center mb-3 dark:text-white">
                            <?php echo htmlspecialchars($certificate['formation_title']); ?>
                        </h3>
                        <div class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            <div class="flex justify-between mb-1">
                                <span>Score:</span>
                                <span class="font-medium text-gray-800 dark:text-white"><?php echo $certificate['score']; ?>%</span>
                            </div>
                            <div class="flex justify-between mb-1">
                                <span>Date:</span>
                                <span class="font-medium text-gray-800 dark:text-white"><?php echo date('d/m/Y', strtotime($certificate['certificate_date'])); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Numéro:</span>
                                <span class="font-medium text-gray-800 dark:text-white line-clamp-1"><?php echo $certificate['certificate_number']; ?></span>
                            </div>
                        </div>
                        <div class="flex justify-center">
                            <a href="<?php echo $certificate['certificate_url']; ?>" target="_blank" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded text-sm font-medium transition-colors">
                                <i class="fas fa-download mr-1"></i>Télécharger
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Learning Progress Chart -->
            <div class="mb-8">
                <h2 class="text-xl font-bold mb-4 dark:text-white">Ma progression d'apprentissage</h2>
                
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 sm:p-6" data-aos="fade-up">
                    <div class="h-64">
                        <canvas id="progressChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Recommended Formations -->
            <div class="mb-8">
                <h2 class="text-xl font-bold mb-4 dark:text-white flex items-center justify-between">
                    <span>Formations recommandées pour vous</span>
                    <a href="formations.php" class="text-sm text-netblue-600 dark:text-netblue-400 hover:underline">
                        Voir plus <i class="fas fa-chevron-right ml-1 text-xs"></i>
                    </a>
                </h2>
                
                <div id="recommended-loader" class="flex justify-center py-8">
                    <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-netblue-600"></div>
                </div>
                
                <div id="recommended-formations" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 hidden">
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
                
                // Update chart colors if they exist
                if (window.progressChart) {
                    updateChartColors();
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
            
            // Progress Chart
            const ctx = document.getElementById('progressChart').getContext('2d');
            
            // Mock data for the chart - replace with actual data from your database
            const labels = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil'];
            const data = {
                labels: labels,
                datasets: [{
                    label: 'Vidéos visionnées',
                    data: [5, 12, 20, 15, 30, 25, 40],
                    borderColor: '#3B82F6',
                    backgroundColor: 'rgba(59, 130, 246, 0.2)',
                    tension: 0.3,
                    fill: true
                }]
            };
            
            // Chart configuration with responsive options
            const config = {
                type: 'line',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            labels: {
                                color: htmlElement.classList.contains('dark') ? '#E5E7EB' : '#1F2937',
                                font: {
                                    size: window.innerWidth < 768 ? 10 : 12
                                }
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            bodyFont: {
                                size: window.innerWidth < 768 ? 12 : 14
                            },
                            titleFont: {
                                size: window.innerWidth < 768 ? 12 : 14
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: {
                                color: htmlElement.classList.contains('dark') ? '#9CA3AF' : '#4B5563',
                                font: {
                                    size: window.innerWidth < 768 ? 10 : 12
                                },
                                maxRotation: 45,
                                minRotation: 45
                            },
                            grid: {
                                color: htmlElement.classList.contains('dark') ? 'rgba(75, 85, 99, 0.2)' : 'rgba(209, 213, 219, 0.5)'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0,
                                color: htmlElement.classList.contains('dark') ? '#9CA3AF' : '#4B5563',
                                font: {
                                    size: window.innerWidth < 768 ? 10 : 12
                                }
                            },
                            grid: {
                                color: htmlElement.classList.contains('dark') ? 'rgba(75, 85, 99, 0.2)' : 'rgba(209, 213, 219, 0.5)'
                            }
                        }
                    }
                }
            };
            
            // Create chart
            window.progressChart = new Chart(ctx, config);
            
            // Function to update chart colors based on theme
            function updateChartColors() {
                const isDark = htmlElement.classList.contains('dark');
                
                window.progressChart.options.plugins.legend.labels.color = isDark ? '#E5E7EB' : '#1F2937';
                window.progressChart.options.scales.x.ticks.color = isDark ? '#9CA3AF' : '#4B5563';
                window.progressChart.options.scales.y.ticks.color = isDark ? '#9CA3AF' : '#4B5563';
                window.progressChart.options.scales.x.grid.color = isDark ? 'rgba(75, 85, 99, 0.2)' : 'rgba(209, 213, 219, 0.5)';
                window.progressChart.options.scales.y.grid.color = isDark ? 'rgba(75, 85, 99, 0.2)' : 'rgba(209, 213, 219, 0.5)';
                
                window.progressChart.update();
            }
            
            // Function to update chart responsiveness
            function updateChartResponsiveness() {
                const isMobile = window.innerWidth < 768;
                
                window.progressChart.options.plugins.legend.labels.font.size = isMobile ? 10 : 12;
                window.progressChart.options.plugins.tooltip.bodyFont.size = isMobile ? 12 : 14;
                window.progressChart.options.plugins.tooltip.titleFont.size = isMobile ? 12 : 14;
                window.progressChart.options.scales.x.ticks.font.size = isMobile ? 10 : 12;
                window.progressChart.options.scales.y.ticks.font.size = isMobile ? 10 : 12;
                
                window.progressChart.update();
            }
            
            // Update chart on window resize
            window.addEventListener('resize', updateChartResponsiveness);
            
            // Load recommended formations
            loadRecommendedFormations();
        });
        
        // Function to load recommended formations via AJAX
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
                        title: "Administration réseau avancée",
                        category_name: "Réseau Informatique",
                        category_icon: "fa-network-wired",
                        cover_image: "image/formations/default-1.jpg",
                        level: "avance",
                        price_per_month: 25000,
                        short_description: "Configurez et gérez des réseaux d'entreprise complexes"
                    },
                    {
                        id: 2,
                        title: "Excel Avancé",
                        category_name: "Informatique Bureautique",
                        category_icon: "fa-desktop",
                        cover_image: "image/formations/default-2.jpg",
                        level: "avance",
                        price_per_month: 15000,
                        short_description: "Devenez un expert des tableaux croisés dynamiques et des macros"
                    },
                    {
                        id: 3,
                        title: "Introduction à la cybersécurité",
                        category_name: "Hacking Éthique",
                        category_icon: "fa-shield-alt",
                        cover_image: "image/formations/default-3.jpg",
                        level: "debutant",
                        price_per_month: 20000,
                        short_description: "Comprendre les bases de la sécurité informatique"
                    }
                ];
                
                // Generate HTML for recommendations
                let html = '';
                recommendations.forEach(formation => {
                    html += `
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden" data-aos="fade-up">
                        <div class="relative h-40">
                            <img src="../${formation.cover_image}" alt="${formation.title}" class="w-full h-full object-cover">
                            <div class="absolute top-2 left-2 bg-netblue-600 text-white text-xs font-bold px-2 py-1 rounded">
                                <i class="fas ${formation.category_icon} mr-1"></i>
                                ${formation.category_name}
                            </div>
                            <div class="absolute bottom-2 left-2 text-white text-xs font-bold px-2 py-1 rounded
                                ${formation.level === 'debutant' ? 'bg-green-600' : 
                                  formation.level === 'intermediaire' ? 'bg-yellow-600' : 
                                  'bg-red-600'}">
                                ${formation.level === 'debutant' ? 'Débutant' : 
                                  formation.level === 'intermediaire' ? 'Intermédiaire' : 
                                  'Avancé'}
                            </div>
                        </div>
                        <div class="p-4">
                            <h3 class="text-lg font-bold mb-2 dark:text-white line-clamp-1">${formation.title}</h3>
                            <p class="text-gray-600 dark:text-gray-400 text-sm mb-4 line-clamp-2">
                                ${formation.short_description}
                            </p>
                            <div class="flex justify-between items-center">
                                <div class="font-bold text-netblue-600 dark:text-netblue-400">
                                    ${formation.price_per_month.toLocaleString()} <span class="text-sm font-normal">FCFA</span>
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
            }, 1000); // Simulate loading time
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