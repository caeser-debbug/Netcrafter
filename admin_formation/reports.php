<?php
// admin/reports.php
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

// Période par défaut (30 derniers jours)
$period = $_GET['period'] ?? '30';
$start_date = date('Y-m-d', strtotime("-{$period} days"));
$end_date = date('Y-m-d');

// Si période personnalisée
if (isset($_GET['custom_start']) && isset($_GET['custom_end'])) {
    $start_date = $_GET['custom_start'];
    $end_date = $_GET['custom_end'];
    $period = 'custom';
}

// Statistiques générales
$general_stats = [];

// Total utilisateurs
$users_query = "SELECT COUNT(*) as total FROM users WHERE created_at BETWEEN ? AND ?";
$stmt = $conn->prepare($users_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$general_stats['new_users'] = $stmt->get_result()->fetch_assoc()['total'];

// Total utilisateurs actifs
$active_users_query = "SELECT COUNT(DISTINCT user_id) as total FROM formation_subscriptions WHERE created_at BETWEEN ? AND ?";
$stmt = $conn->prepare($active_users_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$general_stats['active_users'] = $stmt->get_result()->fetch_assoc()['total'];

// Total abonnements
$subscriptions_query = "SELECT COUNT(*) as total, SUM(amount_paid) as revenue FROM formation_subscriptions WHERE created_at BETWEEN ? AND ? AND status = 'active'";
$stmt = $conn->prepare($subscriptions_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$sub_result = $stmt->get_result()->fetch_assoc();
$general_stats['subscriptions'] = $sub_result['total'];
$general_stats['revenue'] = $sub_result['revenue'] ?? 0;

// Total certificats
$certificates_query = "SELECT COUNT(*) as total FROM certificates WHERE created_at BETWEEN ? AND ?";
$stmt = $conn->prepare($certificates_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$general_stats['certificates'] = $stmt->get_result()->fetch_assoc()['total'];

// Formations les plus populaires
$popular_formations_query = "
    SELECT f.title, f.id, COUNT(fs.id) as subscriptions, SUM(fs.amount_paid) as revenue
    FROM formations f
    LEFT JOIN formation_subscriptions fs ON f.id = fs.formation_id 
    WHERE fs.created_at BETWEEN ? AND ? AND fs.status = 'active'
    GROUP BY f.id, f.title
    ORDER BY subscriptions DESC
    LIMIT 10
";
$stmt = $conn->prepare($popular_formations_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$popular_formations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Évolution des inscriptions par jour
$daily_registrations_query = "
    SELECT DATE(created_at) as date, COUNT(*) as count
    FROM users 
    WHERE created_at BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY date ASC
";
$stmt = $conn->prepare($daily_registrations_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$daily_registrations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Évolution des revenus par jour
$daily_revenue_query = "
    SELECT DATE(created_at) as date, SUM(amount_paid) as revenue
    FROM formation_subscriptions 
    WHERE created_at BETWEEN ? AND ? AND status = 'active'
    GROUP BY DATE(created_at)
    ORDER BY date ASC
";
$stmt = $conn->prepare($daily_revenue_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$daily_revenue = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Répartition par méthode de paiement
$payment_methods_query = "
    SELECT payment_method, COUNT(*) as count, SUM(amount_paid) as revenue
    FROM formation_subscriptions 
    WHERE created_at BETWEEN ? AND ? AND status = 'active'
    GROUP BY payment_method
    ORDER BY count DESC
";
$stmt = $conn->prepare($payment_methods_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$payment_methods = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Top utilisateurs par nombre d'abonnements
$top_users_query = "
    SELECT u.firstname, u.lastname, u.phone, COUNT(fs.id) as subscriptions, SUM(fs.amount_paid) as total_spent
    FROM users u
    JOIN formation_subscriptions fs ON u.id = fs.user_id
    WHERE fs.created_at BETWEEN ? AND ? AND fs.status = 'active'
    GROUP BY u.id, u.firstname, u.lastname, u.phone
    ORDER BY subscriptions DESC
    LIMIT 10
";
$stmt = $conn->prepare($top_users_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$top_users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Statistiques des quiz
$quiz_stats_query = "
    SELECT 
        COUNT(DISTINCT qa.id) as total_attempts,
        COUNT(DISTINCT qa.user_id) as unique_users,
        AVG(qa.score) as avg_score,
        COUNT(CASE WHEN qa.passed = 1 THEN 1 END) as passed_attempts,
        COUNT(CASE WHEN qa.passed = 0 THEN 1 END) as failed_attempts
    FROM quiz_attempts qa
    WHERE qa.completed_at BETWEEN ? AND ?
";
$stmt = $conn->prepare($quiz_stats_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$quiz_stats = $stmt->get_result()->fetch_assoc();

// Activité du forum
$forum_stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM forum_topics WHERE created_at BETWEEN ? AND ?) as new_topics,
        (SELECT COUNT(*) FROM forum_replies WHERE created_at BETWEEN ? AND ?) as new_replies,
        (SELECT COUNT(DISTINCT user_id) FROM forum_topics WHERE created_at BETWEEN ? AND ?) as active_users
";
$stmt = $conn->prepare($forum_stats_query);
$stmt->bind_param("ssssss", $start_date, $end_date, $start_date, $end_date, $start_date, $end_date);
$stmt->execute();
$forum_stats = $stmt->get_result()->fetch_assoc();

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports - Netcrafter Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        /* Side Navigation */
        .sidenav {
            transition: all 0.3s ease;
            z-index: 1000;
        }
        
        .sidenav.collapsed {
            width: 70px;
        }
        
        .sidenav.collapsed .nav-text {
            opacity: 0;
            visibility: hidden;
        }
        
        .content-area {
            transition: margin-left 0.3s ease;
            margin-left: 280px;
        }
        
        .content-area.nav-collapsed {
            margin-left: 70px;
        }
        
        @media (max-width: 768px) {
            .sidenav {
                position: fixed;
                left: -280px;
                width: 280px;
                height: 100vh;
                z-index: 1000;
            }
            
            .sidenav.mobile-open {
                left: 0;
            }
            
            .content-area {
                margin-left: 0;
            }
            
            .mobile-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 999;
            }
            
            .mobile-overlay.active {
                display: block;
            }
        }

        /* Charts */
        .chart-container {
            position: relative;
            height: 300px;
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
<body class="bg-gray-50 text-gray-800">
    <!-- Mobile Overlay -->
    <div id="mobileOverlay" class="mobile-overlay" onclick="closeMobileMenu()"></div>
    
    <!-- Side Navigation -->
    <aside id="sidenav" class="sidenav fixed h-full bg-white shadow-lg">
        <!-- Logo and collapse button -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
            <div class="flex items-center">
                <img src="../image/logo-n.png" alt="Netcrafter Logo" class="h-8 mr-2">
                <span class="text-lg font-bold text-netblue-600 nav-text">ADMIN</span>
            </div>
            <button id="sidenavToggle" class="text-gray-500 hover:text-gray-700 focus:outline-none hidden md:block">
                <i class="fas fa-chevron-left"></i>
            </button>
        </div>
        
        <!-- Admin info -->
        <div class="px-4 py-4 border-b border-gray-200">
            <div class="flex items-center">
                <div class="w-10 h-10 rounded-full bg-netblue-600 flex items-center justify-center text-white font-bold text-lg">
                    <?php echo strtoupper(substr($admin['firstname'], 0, 1) . substr($admin['lastname'], 0, 1)); ?>
                </div>
                <div class="ml-3 nav-text">
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($admin['firstname'] . ' ' . $admin['lastname']); ?></p>
                    <p class="text-sm text-gray-500"><?php echo ucfirst($admin['role']); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Navigation Menu -->
        <nav class="mt-4 px-2">
            <ul class="space-y-1">
                <li>
                    <a href="dashboard.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-tachometer-alt w-6 text-center"></i>
                        <span class="ml-2 nav-text">Tableau de bord</span>
                    </a>
                </li>
                <li>
                    <a href="users.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-users w-6 text-center"></i>
                        <span class="ml-2 nav-text">Utilisateurs</span>
                    </a>
                </li>
                <li>
                    <a href="formations.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-graduation-cap w-6 text-center"></i>
                        <span class="ml-2 nav-text">Formations</span>
                    </a>
                </li>
                <li>
                    <a href="subscriptions.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-credit-card w-6 text-center"></i>
                        <span class="ml-2 nav-text">Abonnements</span>
                    </a>
                </li>
                <li>
                    <a href="quiz.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-question-circle w-6 text-center"></i>
                        <span class="ml-2 nav-text">Quizz</span>
                    </a>
                </li>
                <li>
                    <a href="certificates.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-certificate w-6 text-center"></i>
                        <span class="ml-2 nav-text">Certificats</span>
                    </a>
                </li>
                <li>
                    <a href="forum.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-comments w-6 text-center"></i>
                        <span class="ml-2 nav-text">Forum</span>
                    </a>
                </li>
                <li>
                    <a href="reports.php" class="flex items-center px-3 py-2 text-base rounded-lg bg-netblue-100 text-netblue-800">
                        <i class="fas fa-chart-bar w-6 text-center"></i>
                        <span class="ml-2 nav-text">Rapports</span>
                    </a>
                </li>
                <li class="pt-2 mt-2 border-t border-gray-200">
                    <a href="settings.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-cog w-6 text-center"></i>
                        <span class="ml-2 nav-text">Paramètres</span>
                    </a>
                </li>
                <li>
                    <a href="admins.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-shield-alt w-6 text-center"></i>
                        <span class="ml-2 nav-text">Administrateurs</span>
                    </a>
                </li>
            </ul>
        </nav>
        
        <!-- Logout -->
        <div class="absolute bottom-0 left-0 right-0 border-t border-gray-200 p-4 bg-white">
            <a href="logout.php" class="flex items-center justify-center px-3 py-2 rounded-lg text-red-600 hover:bg-red-50">
                <i class="fas fa-sign-out-alt w-6 text-center"></i>
                <span class="ml-2 nav-text">Déconnexion</span>
            </a>
        </div>
    </aside>
    
    <!-- Main Content -->
    <div id="content" class="content-area min-h-screen">
        <!-- Top Bar -->
        <header class="bg-white shadow-sm sticky top-0 z-20">
            <div class="flex items-center justify-between px-4 py-3">
                <!-- Mobile Menu Toggle -->
                <button id="mobileMenuToggle" class="md:hidden text-gray-700 focus:outline-none">
                    <i class="fas fa-bars text-2xl"></i>
                </button>
                
                <!-- Page Title -->
                <h1 class="text-xl font-bold text-gray-800">Rapports et Statistiques</h1>
                
                <!-- Period Filter -->
                <div class="flex items-center space-x-2">
                    <select id="periodSelect" onchange="changePeriod()" class="px-3 py-1 border border-gray-300 rounded-lg text-sm">
                        <option value="7" <?php echo $period === '7' ? 'selected' : ''; ?>>7 derniers jours</option>
                        <option value="30" <?php echo $period === '30' ? 'selected' : ''; ?>>30 derniers jours</option>
                        <option value="90" <?php echo $period === '90' ? 'selected' : ''; ?>>90 derniers jours</option>
                        <option value="365" <?php echo $period === '365' ? 'selected' : ''; ?>>1 an</option>
                        <option value="custom" <?php echo $period === 'custom' ? 'selected' : ''; ?>>Personnalisé</option>
                    </select>
                    <div class="w-8 h-8 rounded-full bg-netblue-600 flex items-center justify-center text-white font-bold text-sm">
                        <?php echo strtoupper(substr($admin['firstname'], 0, 1) . substr($admin['lastname'], 0, 1)); ?>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Custom Date Range (hidden by default) -->
        <div id="customDateRange" class="bg-white px-4 py-3 border-b <?php echo $period !== 'custom' ? 'hidden' : ''; ?>">
            <form method="GET" class="flex items-center space-x-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date de début</label>
                    <input type="date" name="custom_start" value="<?php echo $start_date; ?>" class="px-3 py-2 border border-gray-300 rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date de fin</label>
                    <input type="date" name="custom_end" value="<?php echo $end_date; ?>" class="px-3 py-2 border border-gray-300 rounded-lg">
                </div>
                <div class="pt-6">
                    <button type="submit" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-search mr-2"></i>Appliquer
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Main Content Area -->
        <main class="p-6">
            <!-- Overview Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-blue-100 rounded-full">
                            <i class="fas fa-users text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Nouveaux Utilisateurs</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($general_stats['new_users']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-green-100 rounded-full">
                            <i class="fas fa-user-check text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Utilisateurs Actifs</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($general_stats['active_users']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-purple-100 rounded-full">
                            <i class="fas fa-graduation-cap text-purple-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Abonnements</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($general_stats['subscriptions']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-yellow-100 rounded-full">
                            <i class="fas fa-dollar-sign text-yellow-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Revenus</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($general_stats['revenue']); ?> FCFA</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Daily Registrations Chart -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Évolution des Inscriptions</h3>
                    <div class="chart-container">
                        <canvas id="registrationsChart"></canvas>
                    </div>
                </div>

                <!-- Daily Revenue Chart -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Évolution des Revenus</h3>
                    <div class="chart-container">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Tables Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Popular Formations -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Formations Populaires</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b border-gray-200">
                                    <th class="text-left py-3 text-sm font-medium text-gray-500">Formation</th>
                                    <th class="text-right py-3 text-sm font-medium text-gray-500">Abonnements</th>
                                    <th class="text-right py-3 text-sm font-medium text-gray-500">Revenus</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($popular_formations as $formation): ?>
                                <tr>
                                    <td class="py-3 text-sm text-gray-900"><?php echo htmlspecialchars($formation['title']); ?></td>
                                    <td class="py-3 text-sm text-right text-gray-900"><?php echo number_format($formation['subscriptions']); ?></td>
                                    <td class="py-3 text-sm text-right text-gray-900"><?php echo number_format($formation['revenue']); ?> FCFA</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Top Users -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Utilisateurs les Plus Actifs</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b border-gray-200">
                                    <th class="text-left py-3 text-sm font-medium text-gray-500">Utilisateur</th>
                                    <th class="text-right py-3 text-sm font-medium text-gray-500">Abonnements</th>
                                    <th class="text-right py-3 text-sm font-medium text-gray-500">Total Dépensé</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($top_users as $user): ?>
                                <tr>
                                    <td class="py-3 text-sm text-gray-900">
                                        <?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($user['phone']); ?></div>
                                    </td>
                                    <td class="py-3 text-sm text-right text-gray-900"><?php echo number_format($user['subscriptions']); ?></td>
                                    <td class="py-3 text-sm text-right text-gray-900"><?php echo number_format($user['total_spent']); ?> FCFA</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Additional Stats -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Quiz Stats -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="font-semibold text-gray-900">Quiz</h4>
                        <i class="fas fa-question-circle text-netblue-600"></i>
                    </div>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Tentatives:</span>
                            <span class="font-medium"><?php echo number_format($quiz_stats['total_attempts'] ?? 0); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Score moyen:</span>
                            <span class="font-medium"><?php echo round($quiz_stats['avg_score'] ?? 0, 1); ?>%</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Taux de réussite:</span>
                            <span class="font-medium text-green-600">
                                <?php 
                                $success_rate = $quiz_stats['total_attempts'] > 0 ? 
                                    round(($quiz_stats['passed_attempts'] / $quiz_stats['total_attempts']) * 100, 1) : 0;
                                echo $success_rate;
                                ?>%
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Forum Stats -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="font-semibold text-gray-900">Forum</h4>
                        <i class="fas fa-comments text-netblue-600"></i>
                    </div>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Nouveaux sujets:</span>
                            <span class="font-medium"><?php echo number_format($forum_stats['new_topics'] ?? 0); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Nouvelles réponses:</span>
                            <span class="font-medium"><?php echo number_format($forum_stats['new_replies'] ?? 0); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Utilisateurs actifs:</span>
                            <span class="font-medium text-blue-600"><?php echo number_format($forum_stats['active_users'] ?? 0); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Certificates Stats -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="font-semibold text-gray-900">Certificats</h4>
                        <i class="fas fa-certificate text-netblue-600"></i>
                    </div>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Générés:</span>
                            <span class="font-medium"><?php echo number_format($general_stats['certificates']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Taux de certification:</span>
                            <span class="font-medium text-green-600">
                                <?php 
                                $cert_rate = $general_stats['active_users'] > 0 ? 
                                    round(($general_stats['certificates'] / $general_stats['active_users']) * 100, 1) : 0;
                                echo $cert_rate;
                                ?>%
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Payment Methods -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="font-semibold text-gray-900">Méthodes de Paiement</h4>
                        <i class="fas fa-credit-card text-netblue-600"></i>
                    </div>
                    <div class="space-y-2">
                        <?php foreach ($payment_methods as $method): ?>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600 capitalize"><?php echo htmlspecialchars($method['payment_method']); ?>:</span>
                            <span class="font-medium"><?php echo number_format($method['count']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Export Section -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Exporter les Données</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <button onclick="exportData('users')" class="flex items-center justify-center px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                        <i class="fas fa-users mr-2"></i>
                        Exporter Utilisateurs
                    </button>
                    <button onclick="exportData('subscriptions')" class="flex items-center justify-center px-4 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors">
                        <i class="fas fa-credit-card mr-2"></i>
                        Exporter Abonnements
                    </button>
                    <button onclick="exportData('revenue')" class="flex items-center justify-center px-4 py-3 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors">
                        <i class="fas fa-chart-line mr-2"></i>
                        Exporter Revenus
                    </button>
                </div>
            </div>
        </main>
        
        <!-- Footer -->
        <footer class="bg-white shadow-lg py-4 mt-8">
            <div class="px-6">
                <div class="flex flex-col sm:flex-row justify-between items-center">
                    <div class="text-center sm:text-left mb-4 sm:mb-0">
                        <p class="text-gray-600">© 2023 Netcrafter Admin Panel. Tous droits réservés.</p>
                    </div>
                    <div class="flex items-center space-x-4 text-sm text-gray-500">
                        <span>Version 2.1.0</span>
                        <span>•</span>
                        <span>Dernière mise à jour: <?php echo date('d/m/Y H:i'); ?></span>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <script>
        // Sidebar functionality
        document.addEventListener('DOMContentLoaded', function() {
            const sidenav = document.getElementById('sidenav');
            const sidenavToggle = document.getElementById('sidenavToggle');
            const content = document.getElementById('content');
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const mobileOverlay = document.getElementById('mobileOverlay');
            
            // Desktop sidebar toggle
            if (sidenavToggle) {
                sidenavToggle.addEventListener('click', function() {
                    sidenav.classList.toggle('collapsed');
                    content.classList.toggle('nav-collapsed');
                    
                    // Update icon
                    const icon = sidenavToggle.querySelector('i');
                    if (sidenav.classList.contains('collapsed')) {
                        icon.classList.remove('fa-chevron-left');
                        icon.classList.add('fa-chevron-right');
                    } else {
                        icon.classList.remove('fa-chevron-right');
                        icon.classList.add('fa-chevron-left');
                    }
                    
                    // Save state
                    localStorage.setItem('sidebarCollapsed', sidenav.classList.contains('collapsed'));
                });
            }
            
            // Mobile menu toggle
            if (mobileMenuToggle) {
                mobileMenuToggle.addEventListener('click', openMobileMenu);
            }
            
            // Restore sidebar state on desktop
            if (window.innerWidth >= 768) {
                const savedState = localStorage.getItem('sidebarCollapsed');
                if (savedState === 'true') {
                    sidenav.classList.add('collapsed');
                    content.classList.add('nav-collapsed');
                    const icon = sidenavToggle?.querySelector('i');
                    if (icon) {
                        icon.classList.remove('fa-chevron-left');
                        icon.classList.add('fa-chevron-right');
                    }
                }
            }
            
            // Initialize charts
            initCharts();
        });
        
        function openMobileMenu() {
            const sidenav = document.getElementById('sidenav');
            const mobileOverlay = document.getElementById('mobileOverlay');
            
            sidenav.classList.add('mobile-open');
            mobileOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeMobileMenu() {
            const sidenav = document.getElementById('sidenav');
            const mobileOverlay = document.getElementById('mobileOverlay');
            
            sidenav.classList.remove('mobile-open');
            mobileOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }
        
        // Period change handler
        function changePeriod() {
            const periodSelect = document.getElementById('periodSelect');
            const customDateRange = document.getElementById('customDateRange');
            
            if (periodSelect.value === 'custom') {
                customDateRange.classList.remove('hidden');
            } else {
                customDateRange.classList.add('hidden');
                // Redirect with new period
                window.location.href = `reports.php?period=${periodSelect.value}`;
            }
        }
        
        // Initialize charts
        function initCharts() {
            // Registrations chart
            const registrationsData = <?php echo json_encode($daily_registrations); ?>;
            const registrationsCtx = document.getElementById('registrationsChart').getContext('2d');
            
            new Chart(registrationsCtx, {
                type: 'line',
                data: {
                    labels: registrationsData.map(item => {
                        const date = new Date(item.date);
                        return date.toLocaleDateString('fr-FR', { month: 'short', day: 'numeric' });
                    }),
                    datasets: [{
                        label: 'Nouvelles inscriptions',
                        data: registrationsData.map(item => item.count),
                        borderColor: '#3B82F6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 2,
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
                                stepSize: 1
                            }
                        }
                    }
                }
            });
            
            // Revenue chart
            const revenueData = <?php echo json_encode($daily_revenue); ?>;
            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            
            new Chart(revenueCtx, {
                type: 'bar',
                data: {
                    labels: revenueData.map(item => {
                        const date = new Date(item.date);
                        return date.toLocaleDateString('fr-FR', { month: 'short', day: 'numeric' });
                    }),
                    datasets: [{
                        label: 'Revenus quotidiens',
                        data: revenueData.map(item => item.revenue),
                        backgroundColor: 'rgba(34, 197, 94, 0.8)',
                        borderColor: '#22C55E',
                        borderWidth: 1
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
                                callback: function(value) {
                                    return new Intl.NumberFormat('fr-FR').format(value) + ' FCFA';
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Export functionality
        function exportData(type) {
            const startDate = '<?php echo $start_date; ?>';
            const endDate = '<?php echo $end_date; ?>';
            
            showNotification('Génération de l\'export en cours...', 'info');
            
            // Create form for export
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'export_data.php';
            form.style.display = 'none';
            
            const typeInput = document.createElement('input');
            typeInput.type = 'hidden';
            typeInput.name = 'export_type';
            typeInput.value = type;
            
            const startInput = document.createElement('input');
            startInput.type = 'hidden';
            startInput.name = 'start_date';
            startInput.value = startDate;
            
            const endInput = document.createElement('input');
            endInput.type = 'hidden';
            endInput.name = 'end_date';
            endInput.value = endDate;
            
            form.appendChild(typeInput);
            form.appendChild(startInput);
            form.appendChild(endInput);
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
            
            setTimeout(() => {
                showNotification('Export généré avec succès!', 'success');
            }, 2000);
        }
        
        // Notification system
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
        
        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 768) {
                // Close mobile menu if open
                closeMobileMenu();
                
                // Restore desktop sidebar state
                const sidenav = document.getElementById('sidenav');
                const content = document.getElementById('content');
                const savedState = localStorage.getItem('sidebarCollapsed');
                
                if (savedState === 'true') {
                    sidenav.classList.add('collapsed');
                    content.classList.add('nav-collapsed');
                } else {
                    sidenav.classList.remove('collapsed');
                    content.classList.remove('nav-collapsed');
                }
            }
        });
        
        // Print functionality
        function printReport() {
            window.print();
        }
        
        // Auto-refresh data every 5 minutes
        setInterval(function() {
            console.log('Auto-refreshing report data...');
            // You can implement automatic data refresh here if needed
        }, 300000); // 5 minutes
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + P for print
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                printReport();
            }
            
            // Ctrl + E for export
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                exportData('users');
            }
            
            // Escape to close mobile menu
            if (e.key === 'Escape') {
                closeMobileMenu();
            }
        });
        
        // Initialize tooltips and other UI enhancements
        document.addEventListener('DOMContentLoaded', function() {
            // Add loading states to buttons
            const buttons = document.querySelectorAll('button');
            buttons.forEach(button => {
                button.addEventListener('click', function() {
                    const originalContent = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Chargement...';
                    this.disabled = true;
                    
                    setTimeout(() => {
                        this.innerHTML = originalContent;
                        this.disabled = false;
                    }, 2000);
                });
            });
        });
    </script>
</body>
</html>