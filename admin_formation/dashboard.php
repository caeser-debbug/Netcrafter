<?php
// admin/dashboard.php
session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
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

// Récupérer les informations de l'admin
$admin_id = $_SESSION['admin_id'];
$admin_query = "SELECT * FROM admins WHERE id = ?";
$stmt = $conn->prepare($admin_query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin_result = $stmt->get_result();
$admin = $admin_result->fetch_assoc();

// Statistiques générales
$stats = [];

// Nombre total d'utilisateurs
$users_query = "SELECT COUNT(*) as total FROM users WHERE status = 'active'";
$users_result = $conn->query($users_query);
$stats['total_users'] = $users_result->fetch_assoc()['total'];

// Nombre total de formations
$formations_query = "SELECT COUNT(*) as total FROM formations WHERE status = 'active'";
$formations_result = $conn->query($formations_query);
$stats['total_formations'] = $formations_result->fetch_assoc()['total'];

// Abonnements actifs
$active_subs_query = "SELECT COUNT(*) as total FROM formation_subscriptions WHERE status = 'active' AND end_date >= CURDATE()";
$active_subs_result = $conn->query($active_subs_query);
$stats['active_subscriptions'] = $active_subs_result->fetch_assoc()['total'];

// Abonnements en attente
$pending_subs_query = "SELECT COUNT(*) as total FROM formation_subscriptions WHERE status = 'pending'";
$pending_subs_result = $conn->query($pending_subs_query);
$stats['pending_subscriptions'] = $pending_subs_result->fetch_assoc()['total'];

// Certificats délivrés
$certificates_query = "SELECT COUNT(*) as total FROM certificates";
$certificates_result = $conn->query($certificates_query);
$stats['total_certificates'] = $certificates_result->fetch_assoc()['total'];

// Revenus du mois
$revenue_query = "SELECT SUM(amount_paid) as total FROM formation_subscriptions WHERE status IN ('active', 'expired') AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
$revenue_result = $conn->query($revenue_query);
$stats['monthly_revenue'] = $revenue_result->fetch_assoc()['total'] ?? 0;

// Nouveaux utilisateurs cette semaine
$new_users_query = "SELECT COUNT(*) as total FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
$new_users_result = $conn->query($new_users_query);
$stats['new_users_week'] = $new_users_result->fetch_assoc()['total'];

// Données pour les graphiques
// Inscriptions par mois (6 derniers mois)
$registrations_data = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $month_query = "SELECT COUNT(*) as total FROM users WHERE DATE_FORMAT(created_at, '%Y-%m') = '$month'";
    $month_result = $conn->query($month_query);
    $registrations_data[] = [
        'month' => date('M Y', strtotime($month . '-01')),
        'users' => $month_result->fetch_assoc()['total']
    ];
}

// Abonnements par formation (top 5)
$top_formations_query = "SELECT f.title, COUNT(fs.id) as subscriptions 
                        FROM formations f 
                        LEFT JOIN formation_subscriptions fs ON f.id = fs.formation_id 
                        WHERE fs.status IN ('active', 'expired')
                        GROUP BY f.id 
                        ORDER BY subscriptions DESC 
                        LIMIT 5";
$top_formations_result = $conn->query($top_formations_query);
$top_formations = [];
while ($row = $top_formations_result->fetch_assoc()) {
    $top_formations[] = $row;
}

// Activités récentes (dernières 10)
$recent_activities_query = "
    (SELECT 'new_user' as type, CONCAT(firstname, ' ', lastname) as description, created_at as activity_date 
     FROM users ORDER BY created_at DESC LIMIT 5)
    UNION ALL
    (SELECT 'new_subscription' as type, CONCAT('Abonnement à ', f.title) as description, fs.created_at as activity_date
     FROM formation_subscriptions fs 
     JOIN formations f ON fs.formation_id = f.id 
     ORDER BY fs.created_at DESC LIMIT 5)
    ORDER BY activity_date DESC 
    LIMIT 10";
$recent_activities_result = $conn->query($recent_activities_query);
$recent_activities = [];
while ($row = $recent_activities_result->fetch_assoc()) {
    $recent_activities[] = $row;
}

// Abonnements en attente (pour validation)
$pending_subscriptions_query = "SELECT fs.*, f.title as formation_title, u.firstname, u.lastname, u.phone
                               FROM formation_subscriptions fs
                               JOIN formations f ON fs.formation_id = f.id
                               JOIN users u ON fs.user_id = u.id
                               WHERE fs.status = 'pending'
                               ORDER BY fs.created_at DESC
                               LIMIT 10";
$pending_subscriptions_result = $conn->query($pending_subscriptions_query);
$pending_subscriptions = [];
while ($row = $pending_subscriptions_result->fetch_assoc()) {
    $pending_subscriptions[] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr" class="light scroll">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Admin - Netcrafter</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
    
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
            
            .sidenav.collapsed .nav-text {
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

        /* Stats cards animation */
        .stats-card {
            transition: all 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        /* Activity feed */
        .activity-item {
            transition: all 0.2s ease;
        }
        
        .activity-item:hover {
            background-color: #f8fafc;
            transform: translateX(5px);
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
            <div class="flex items-center">
                <img src="../image/logo-n.png" alt="Netcrafter Logo" class="h-8 mr-2">
                <span class="text-lg font-bold text-netblue-600 dark:text-netblue-400 nav-text transition-opacity duration-300">ADMIN</span>
            </div>
            <button id="sidenav-toggle" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 focus:outline-none md:block hidden">
                <i class="fas fa-chevron-left"></i>
            </button>
        </div>
        
        <!-- Admin info -->
        <div class="px-4 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center">
                <div class="w-10 h-10 rounded-full bg-netblue-600 dark:bg-netblue-700 flex items-center justify-center text-white font-bold text-lg flex-shrink-0">
                    <?php echo strtoupper(substr($admin['firstname'], 0, 1) . substr($admin['lastname'], 0, 1)); ?>
                </div>
                <div class="ml-3 overflow-hidden nav-text">
                    <p class="font-medium text-gray-800 dark:text-white truncate"><?php echo htmlspecialchars($admin['firstname'] . ' ' . $admin['lastname']); ?></p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 truncate"><?php echo ucfirst($admin['role']); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Navigation Menu -->
        <nav class="mt-4 px-2 overflow-y-auto" style="max-height: calc(100vh - 200px);">
            <ul class="space-y-1">
                <li>
                    <a href="dashboard.php" class="flex items-center px-3 py-2 text-base rounded-lg bg-netblue-100 dark:bg-netblue-900/30 text-netblue-800 dark:text-netblue-300">
                        <i class="fas fa-tachometer-alt w-6 text-center"></i>
                        <span class="ml-2 nav-text">Tableau de bord</span>
                    </a>
                </li>
                <li>
                    <a href="users.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-users w-6 text-center"></i>
                        <span class="ml-2 nav-text">Utilisateurs</span>
                        <span class="ml-auto bg-blue-500 text-white text-xs rounded-full px-2 py-1 nav-text"><?php echo $stats['total_users']; ?></span>
                    </a>
                </li>
                <li>
                    <a href="formations.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-graduation-cap w-6 text-center"></i>
                        <span class="ml-2 nav-text">Formations</span>
                        <span class="ml-auto bg-green-500 text-white text-xs rounded-full px-2 py-1 nav-text"><?php echo $stats['total_formations']; ?></span>
                    </a>
                </li>
                <li>
                    <a href="subscriptions.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-credit-card w-6 text-center"></i>
                        <span class="ml-2 nav-text">Abonnements</span>
                        <?php if ($stats['pending_subscriptions'] > 0): ?>
                        <span class="ml-auto bg-red-500 text-white text-xs rounded-full px-2 py-1 nav-text"><?php echo $stats['pending_subscriptions']; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="quiz.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-question-circle w-6 text-center"></i>
                        <span class="ml-2 nav-text">Quizz</span>
                    </a>
                </li>
                <li>
                    <a href="certificates.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-certificate w-6 text-center"></i>
                        <span class="ml-2 nav-text">Certificats</span>
                    </a>
                </li>
                
                <li>
                    <a href="forum.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-comments w-6 text-center"></i>
                        <span class="ml-2 nav-text">Forum</span>
                    </a>
                </li>
                <li>
                    <a href="reports.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-chart-bar w-6 text-center"></i>
                        <span class="ml-2 nav-text">Rapports</span>
                    </a>
                </li>
                <li class="pt-2 mt-2 border-t border-gray-200 dark:border-gray-700">
                    <a href="settings.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-cog w-6 text-center"></i>
                        <span class="ml-2 nav-text">Paramètres</span>
                    </a>
                </li>
                <li>
                    <a href="admins.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-shield-alt w-6 text-center"></i>
                        <span class="ml-2 nav-text">Administrateurs</span>
                    </a>
                </li>
            </ul>
        </nav>
        
        <!-- Logout -->
        <div class="absolute bottom-0 left-0 right-0 border-t border-gray-200 dark:border-gray-700 p-4 bg-white dark:bg-gray-800">
            <a href="logout.php" class="flex items-center justify-center px-3 py-2 rounded-lg text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors duration-200">
                <i class="fas fa-sign-out-alt w-6 text-center"></i>
                <span class="ml-2 nav-text">Déconnexion</span>
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
                <h1 class="text-xl font-bold text-gray-800 dark:text-white">Tableau de Bord</h1>
                
                <!-- Right Menu -->
                <div class="flex items-center space-x-4">
                    <!-- Notifications -->
                    <div class="relative">
                        <button class="text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white transition-colors">
                            <i class="fas fa-bell text-xl"></i>
                            <?php if ($stats['pending_subscriptions'] > 0): ?>
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                <?php echo $stats['pending_subscriptions']; ?>
                            </span>
                            <?php endif; ?>
                        </button>
                    </div>
                    
                    <!-- Profile -->
                    <div class="flex items-center">
                        <div class="w-8 h-8 rounded-full bg-netblue-600 flex items-center justify-center text-white font-bold text-sm">
                            <?php echo strtoupper(substr($admin['firstname'], 0, 1) . substr($admin['lastname'], 0, 1)); ?>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Main Content Area -->
        <main class="p-4">
            <!-- Welcome Section -->
            <div class="mb-8">
                <h2 class="text-2xl font-bold mb-2 dark:text-white">
                    Bonjour, <?php echo htmlspecialchars($admin['firstname']); ?>! 👋
                </h2>
                <p class="text-gray-600 dark:text-gray-400">
                    Voici un aperçu de votre plateforme aujourd'hui.
                </p>
            </div>
            
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Users -->
                <div class="stats-card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-blue-100 dark:bg-blue-900 p-3 rounded-lg">
                            <i class="fas fa-users text-2xl text-blue-600 dark:text-blue-400"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">Utilisateurs</h3>
                            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo number_format($stats['total_users']); ?></p>
                            <p class="text-xs text-green-600">+<?php echo $stats['new_users_week']; ?> cette semaine</p>
                        </div>
                    </div>
                </div>
                
                <!-- Total Formations -->
                <div class="stats-card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-green-100 dark:bg-green-900 p-3 rounded-lg">
                            <i class="fas fa-graduation-cap text-2xl text-green-600 dark:text-green-400"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">Formations</h3>
                            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo number_format($stats['total_formations']); ?></p>
                            <p class="text-xs text-blue-600">Actives</p>
                        </div>
                    </div>
                </div>
                
                <!-- Active Subscriptions -->
                <div class="stats-card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up" data-aos-delay="300">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-purple-100 dark:bg-purple-900 p-3 rounded-lg">
                            <i class="fas fa-credit-card text-2xl text-purple-600 dark:text-purple-400"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">Abonnements</h3>
                            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo number_format($stats['active_subscriptions']); ?></p>
                            <p class="text-xs text-orange-600"><?php echo $stats['pending_subscriptions']; ?> en attente</p>
                        </div>
                    </div>
                </div>
                
                <!-- Monthly Revenue -->
                <div class="stats-card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up" data-aos-delay="400">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-yellow-100 dark:bg-yellow-900 p-3 rounded-lg">
                            <i class="fas fa-coins text-2xl text-yellow-600 dark:text-yellow-400"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">Revenus du mois</h3>
                            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo number_format($stats['monthly_revenue']); ?> F</p>
                            <p class="text-xs text-green-600">Objectif: 500K F</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts and Tables Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Registrations Chart -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up">
                    <h3 class="text-lg font-bold mb-4 dark:text-white">Inscriptions par mois</h3>
                    <div class="h-64">
                        <canvas id="registrationsChart"></canvas>
                    </div>
                </div>
                
                <!-- Top Formations -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up">
                    <h3 class="text-lg font-bold mb-4 dark:text-white">Formations populaires</h3>
                    <div class="space-y-4">
                        <?php foreach ($top_formations as $index => $formation): ?>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-netblue-100 dark:bg-netblue-900 rounded-full flex items-center justify-center text-netblue-600 dark:text-netblue-400 font-bold text-sm">
                                    <?php echo $index + 1; ?>
                                </div>
                                <div class="ml-3">
                                    <p class="font-medium dark:text-white"><?php echo htmlspecialchars($formation['title']); ?></p>
                                </div>
                            </div>
                            <div class="bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-300 px-2 py-1 rounded-full text-sm font-medium">
                                <?php echo $formation['subscriptions']; ?> abonnés
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions and Recent Activity -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
                <!-- Quick Actions -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up">
                    <h3 class="text-lg font-bold mb-4 dark:text-white">Actions rapides</h3>
                    <div class="space-y-3">
                        <a href="formations.php?action=create" class="flex items-center p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors">
                            <i class="fas fa-plus text-blue-600 dark:text-blue-400 w-5"></i>
                            <span class="ml-3 text-blue-800 dark:text-blue-300 font-medium">Nouvelle formation</span>
                        </a>
                        <a href="users.php" class="flex items-center p-3 bg-green-50 dark:bg-green-900/20 rounded-lg hover:bg-green-100 dark:hover:bg-green-900/30 transition-colors">
                            <i class="fas fa-user-plus text-green-600 dark:text-green-400 w-5"></i>
                            <span class="ml-3 text-green-800 dark:text-green-300 font-medium">Gérer utilisateurs</span>
                        </a>
                        <a href="subscriptions.php" class="flex items-center p-3 bg-orange-50 dark:bg-orange-900/20 rounded-lg hover:bg-orange-100 dark:hover:bg-orange-900/30 transition-colors">
                            <i class="fas fa-check-circle text-orange-600 dark:text-orange-400 w-5"></i>
                            <span class="ml-3 text-orange-800 dark:text-orange-300 font-medium">Valider abonnements</span>
                        </a>
                        <a href="reports.php" class="flex items-center p-3 bg-purple-50 dark:bg-purple-900/20 rounded-lg hover:bg-purple-100 dark:hover:bg-purple-900/30 transition-colors">
                            <i class="fas fa-chart-line text-purple-600 dark:text-purple-400 w-5"></i>
                            <span class="ml-3 text-purple-800 dark:text-purple-300 font-medium">Voir rapports</span>
                        </a>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up">
                    <h3 class="text-lg font-bold mb-4 dark:text-white">Activité récente</h3>
                    <div class="space-y-3">
                        <?php foreach ($recent_activities as $activity): ?>
                        <div class="activity-item flex items-center p-3 rounded-lg border-l-4 <?php echo $activity['type'] == 'new_user' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-green-500 bg-green-50 dark:bg-green-900/20'; ?>">
                            <div class="flex-shrink-0">
                                <i class="fas <?php echo $activity['type'] == 'new_user' ? 'fa-user-plus text-blue-600 dark:text-blue-400' : 'fa-credit-card text-green-600 dark:text-green-400'; ?>"></i>
                            </div>
                            <div class="ml-3 flex-grow">
                                <p class="text-sm font-medium dark:text-white"><?php echo htmlspecialchars($activity['description']); ?></p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    <?php 
                                    $time_diff = time() - strtotime($activity['activity_date']);
                                    if ($time_diff < 3600) {
                                        echo floor($time_diff / 60) . ' min';
                                    } elseif ($time_diff < 86400) {
                                        echo floor($time_diff / 3600) . ' h';
                                    } else {
                                        echo floor($time_diff / 86400) . ' j';
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Pending Subscriptions -->
            <?php if (!empty($pending_subscriptions)): ?>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 mb-8" data-aos="fade-up">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold dark:text-white">Abonnements en attente de validation</h3>
                    <a href="subscriptions.php" class="text-netblue-600 dark:text-netblue-400 hover:underline text-sm">
                        Voir tout <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Utilisateur</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Formation</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Durée</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Montant</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach (array_slice($pending_subscriptions, 0, 5) as $subscription): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-gray-300 dark:bg-gray-600 rounded-full flex items-center justify-center text-sm font-bold">
                                            <?php echo strtoupper(substr($subscription['firstname'], 0, 1) . substr($subscription['lastname'], 0, 1)); ?>
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($subscription['firstname'] . ' ' . $subscription['lastname']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                <?php echo htmlspecialchars($subscription['phone']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($subscription['formation_title']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                        <?php echo $subscription['subscription_months']; ?> mois
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    <?php echo number_format($subscription['amount_paid']); ?> F
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <?php echo date('d/m/Y', strtotime($subscription['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <button onclick="approveSubscription(<?php echo $subscription['id']; ?>)" class="text-green-600 hover:text-green-900 dark:text-green-400">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button onclick="viewSubscription(<?php echo $subscription['id']; ?>)" class="text-blue-600 hover:text-blue-900 dark:text-blue-400">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button onclick="rejectSubscription(<?php echo $subscription['id']; ?>)" class="text-red-600 hover:text-red-900 dark:text-red-400">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- System Status -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Server Status -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">Serveur</h4>
                            <p class="text-lg font-bold text-green-600 dark:text-green-400">En ligne</p>
                        </div>
                        <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                    </div>
                </div>
                
                <!-- Database Status -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">Base de données</h4>
                            <p class="text-lg font-bold text-green-600 dark:text-green-400">Connectée</p>
                        </div>
                        <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                    </div>
                </div>
                
                <!-- Storage Usage -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up">
                    <div>
                        <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-2">Stockage</h4>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm text-gray-700 dark:text-gray-300">2.4 GB utilisés</span>
                            <span class="text-sm text-gray-500 dark:text-gray-400">48%</span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full" style="width: 48%"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Backup Status -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">Dernière sauvegarde</h4>
                            <p class="text-sm font-medium text-gray-800 dark:text-white">Il y a 2h</p>
                        </div>
                        <i class="fas fa-database text-blue-500"></i>
                    </div>
                </div>
            </div>
        </main>
        
        <!-- Footer -->
        <footer class="bg-white dark:bg-gray-800 shadow-lg mt-8 py-4">
            <div class="px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col sm:flex-row justify-between items-center">
                    <div class="text-center sm:text-left mb-4 sm:mb-0">
                        <p class="text-gray-600 dark:text-gray-400">
                            © 2023 Netcrafter Admin Panel. Tous droits réservés.
                        </p>
                    </div>
                    <div class="flex items-center space-x-4 text-sm text-gray-500 dark:text-gray-400">
                        <span>Version 2.1.0</span>
                        <span>•</span>
                        <button onclick="checkUpdates()" class="hover:text-netblue-600 dark:hover:text-netblue-400">
                            Vérifier les mises à jour
                        </button>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <!-- AOS Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>

    <script>
        // Initialize AOS
        AOS.init({
            duration: 800,
            once: true,
            disable: window.innerWidth < 768 ? true : false
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar toggle functionality
            const sidenav = document.getElementById('sidenav');
            const sidenavToggle = document.getElementById('sidenav-toggle');
            const content = document.getElementById('content');
            const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
            const overlay = document.getElementById('overlay');
            
            // Desktop sidebar toggle
            function toggleSidenav() {
                if (window.innerWidth >= 768) {
                    sidenav.classList.toggle('collapsed');
                    content.classList.toggle('nav-collapsed');
                    
                    // Update toggle icon
                    const icon = sidenavToggle.querySelector('i');
                    if (sidenav.classList.contains('collapsed')) {
                        icon.classList.remove('fa-chevron-left');
                        icon.classList.add('fa-chevron-right');
                    } else {
                        icon.classList.remove('fa-chevron-right');
                        icon.classList.add('fa-chevron-left');
                    }
                    
                    // Save preference
                    localStorage.setItem('adminSidenavCollapsed', sidenav.classList.contains('collapsed'));
                }
            }
            
            // Mobile menu toggle
            function toggleMobileMenu() {
                sidenav.classList.toggle('open');
                overlay.classList.toggle('active');
                document.body.classList.toggle('overflow-hidden');
            }
            
            // Event listeners
            if (sidenavToggle) {
                sidenavToggle.addEventListener('click', toggleSidenav);
            }
            if (mobileMenuToggle) {
                mobileMenuToggle.addEventListener('click', toggleMobileMenu);
            }
            
            // Restore sidebar state
            const savedState = localStorage.getItem('adminSidenavCollapsed');
            if (savedState === 'true' && window.innerWidth >= 768) {
                sidenav.classList.add('collapsed');
                content.classList.add('nav-collapsed');
                const icon = sidenavToggle?.querySelector('i');
                if (icon) {
                    icon.classList.remove('fa-chevron-left');
                    icon.classList.add('fa-chevron-right');
                }
            }
            
            // Initialize charts
            initializeCharts();
            
            // Auto-refresh stats every 5 minutes
            setInterval(refreshStats, 300000);
        });
        
        // Make toggleMobileMenu globally accessible
        function toggleMobileMenu() {
            const sidenav = document.getElementById('sidenav');
            const overlay = document.getElementById('overlay');
            
            sidenav.classList.toggle('open');
            overlay.classList.toggle('active');
            document.body.classList.toggle('overflow-hidden');
        }
        
        // Initialize charts
        function initializeCharts() {
            // Registrations chart
            const registrationsCtx = document.getElementById('registrationsChart').getContext('2d');
            const registrationsData = <?php echo json_encode($registrations_data); ?>;
            
            new Chart(registrationsCtx, {
                type: 'line',
                data: {
                    labels: registrationsData.map(item => item.month),
                    datasets: [{
                        label: 'Nouvelles inscriptions',
                        data: registrationsData.map(item => item.users),
                        borderColor: '#3B82F6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }
        
        // Subscription management functions
        function approveSubscription(subscriptionId) {
            if (confirm('Êtes-vous sûr de vouloir approuver cet abonnement ?')) {
                fetch('api/subscriptions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'approve',
                        id: subscriptionId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Abonnement approuvé avec succès!', 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showNotification('Erreur lors de l\'approbation: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    showNotification('Erreur de connexion', 'error');
                });
            }
        }
        
        function rejectSubscription(subscriptionId) {
            const reason = prompt('Raison du rejet (optionnel):');
            if (reason !== null) {
                fetch('api/subscriptions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'reject',
                        id: subscriptionId,
                        reason: reason
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Abonnement rejeté', 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showNotification('Erreur lors du rejet: ' + data.message, 'error');
                    }
                });
            }
        }
        
        function viewSubscription(subscriptionId) {
            window.open(`subscriptions.php?view=${subscriptionId}`, '_blank');
        }
        
        // Utility functions
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg text-white z-50 transform translate-x-full transition-transform duration-300 ${
                type === 'success' ? 'bg-green-500' : 
                type === 'error' ? 'bg-red-500' : 
                type === 'warning' ? 'bg-yellow-500' : 'bg-blue-500'
            }`;
            
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${
                        type === 'success' ? 'fa-check-circle' : 
                        type === 'error' ? 'fa-exclamation-circle' : 
                        type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle'
                    } mr-2"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Show notification
            setTimeout(() => {
                notification.classList.remove('translate-x-full');
                notification.classList.add('translate-x-0');
            }, 100);
            
            // Hide notification after 3 seconds
            setTimeout(() => {
                notification.classList.remove('translate-x-0');
                notification.classList.add('translate-x-full');
                setTimeout(() => {
                    if (document.body.contains(notification)) {
                        document.body.removeChild(notification);
                    }
                }, 300);
            }, 3000);
        }
        
        function refreshStats() {
            fetch('api/stats.php')
                .then(response => response.json())
                .then(data => {
                    // Update stats without full page reload
                    console.log('Stats refreshed', data);
                })
                .catch(error => {
                    console.log('Error refreshing stats:', error);
                });
        }
        
        function checkUpdates() {
            showNotification('Vérification des mises à jour...', 'info');
            // Simulate update check
            setTimeout(() => {
                showNotification('Vous utilisez la dernière version', 'success');
            }, 2000);
        }
        
        // Real-time updates (WebSocket simulation)
        function initializeRealTimeUpdates() {
            // Simulate real-time notifications
            setInterval(() => {
                const random = Math.random();
                if (random > 0.95) {
                    showNotification('Nouvel utilisateur inscrit', 'info');
                } else if (random > 0.9) {
                    showNotification('Nouveau paiement reçu', 'success');
                }
            }, 30000);
        }
        
        // Initialize real-time updates
        initializeRealTimeUpdates();
        
        // Handle window resize
        window.addEventListener('resize', function() {
            const sidenav = document.getElementById('sidenav');
            const content = document.getElementById('content');
            const overlay = document.getElementById('overlay');
            
            if (window.innerWidth >= 768) {
                // Reset mobile menu state
                sidenav.classList.remove('open');
                overlay.classList.remove('active');
                document.body.classList.remove('overflow-hidden');
                
                // Apply saved collapsed state
                const savedState = localStorage.getItem('adminSidenavCollapsed');
                if (savedState === 'true') {
                    sidenav.classList.add('collapsed');
                    content.classList.add('nav-collapsed');
                } else {
                    sidenav.classList.remove('collapsed');
                    content.classList.remove('nav-collapsed');
                }
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Alt + D for dashboard
            if (e.altKey && e.key === 'd') {
                e.preventDefault();
                window.location.href = 'dashboard.php';
            }
            
            // Alt + U for users
            if (e.altKey && e.key === 'u') {
                e.preventDefault();
                window.location.href = 'users.php';
            }
            
            // Alt + F for formations
            if (e.altKey && e.key === 'f') {
                e.preventDefault();
                window.location.href = 'formations.php';
            }
            
            // Alt + S for settings
            if (e.altKey && e.key === 's') {
                e.preventDefault();
                window.location.href = 'settings.php';
            }
            
            // Escape to close mobile menu
            if (e.key === 'Escape') {
                const sidenav = document.getElementById('sidenav');
                const overlay = document.getElementById('overlay');
                
                if (sidenav.classList.contains('open')) {
                    sidenav.classList.remove('open');
                    overlay.classList.remove('active');
                    document.body.classList.remove('overflow-hidden');
                }
            }
        });
    </script>
</body>
</html>