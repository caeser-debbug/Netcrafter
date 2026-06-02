<?php
// admin/forum.php
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

// Traitement des actions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'toggle_pin':
            $topic_id = $_POST['topic_id'] ?? 0;
            $current_status = $_POST['current_status'] ?? 0;
            $new_status = $current_status ? 0 : 1;
            
            $update_query = "UPDATE forum_topics SET is_pinned = ? WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("ii", $new_status, $topic_id);
            
            if ($stmt->execute()) {
                $message = $new_status ? "Sujet épinglé avec succès" : "Sujet désépinglé avec succès";
                $message_type = "success";
            }
            break;
            
        case 'toggle_lock':
            $topic_id = $_POST['topic_id'] ?? 0;
            $current_status = $_POST['current_status'] ?? 0;
            $new_status = $current_status ? 0 : 1;
            
            $update_query = "UPDATE forum_topics SET is_locked = ? WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("ii", $new_status, $topic_id);
            
            if ($stmt->execute()) {
                $message = $new_status ? "Sujet verrouillé avec succès" : "Sujet déverrouillé avec succès";
                $message_type = "success";
            }
            break;
            
        case 'delete_topic':
            $topic_id = $_POST['topic_id'] ?? 0;
            
            // Supprimer d'abord les réponses
            $delete_replies = "DELETE FROM forum_replies WHERE topic_id = ?";
            $stmt = $conn->prepare($delete_replies);
            $stmt->bind_param("i", $topic_id);
            $stmt->execute();
            
            // Puis supprimer le sujet
            $delete_topic = "DELETE FROM forum_topics WHERE id = ?";
            $stmt = $conn->prepare($delete_topic);
            $stmt->bind_param("i", $topic_id);
            
            if ($stmt->execute()) {
                $message = "Sujet supprimé avec succès";
                $message_type = "success";
            }
            break;
            
        case 'delete_reply':
            $reply_id = $_POST['reply_id'] ?? 0;
            
            $delete_reply = "DELETE FROM forum_replies WHERE id = ?";
            $stmt = $conn->prepare($delete_reply);
            $stmt->bind_param("i", $reply_id);
            
            if ($stmt->execute()) {
                $message = "Réponse supprimée avec succès";
                $message_type = "success";
            }
            break;
            
        case 'mark_solution':
            $reply_id = $_POST['reply_id'] ?? 0;
            $topic_id = $_POST['topic_id'] ?? 0;
            
            // D'abord, retirer le statut de solution de toutes les réponses du topic
            $remove_solutions = "UPDATE forum_replies SET is_solution = 0 WHERE topic_id = ?";
            $stmt = $conn->prepare($remove_solutions);
            $stmt->bind_param("i", $topic_id);
            $stmt->execute();
            
            // Puis marquer la réponse comme solution
            $mark_solution = "UPDATE forum_replies SET is_solution = 1 WHERE id = ?";
            $stmt = $conn->prepare($mark_solution);
            $stmt->bind_param("i", $reply_id);
            
            if ($stmt->execute()) {
                $message = "Réponse marquée comme solution";
                $message_type = "success";
            }
            break;
    }
}

// Pagination et filtres
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

$search = $_GET['search'] ?? '';
$formation_filter = $_GET['formation'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Construction de la requête
$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($search)) {
    $where_conditions[] = "(ft.title LIKE ? OR ft.content LIKE ? OR u.firstname LIKE ? OR u.lastname LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $param_types .= 'ssss';
}

if (!empty($formation_filter)) {
    $where_conditions[] = "ft.formation_id = ?";
    $params[] = $formation_filter;
    $param_types .= 'i';
}

if (!empty($status_filter)) {
    switch ($status_filter) {
        case 'pinned':
            $where_conditions[] = "ft.is_pinned = 1";
            break;
        case 'locked':
            $where_conditions[] = "ft.is_locked = 1";
            break;
        case 'resolved':
            $where_conditions[] = "EXISTS (SELECT 1 FROM forum_replies fr WHERE fr.topic_id = ft.id AND fr.is_solution = 1)";
            break;
        case 'unresolved':
            $where_conditions[] = "NOT EXISTS (SELECT 1 FROM forum_replies fr WHERE fr.topic_id = ft.id AND fr.is_solution = 1)";
            break;
    }
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Requête pour compter le total
$count_query = "
    SELECT COUNT(*) as total 
    FROM forum_topics ft 
    JOIN users u ON ft.user_id = u.id 
    LEFT JOIN formations f ON ft.formation_id = f.id 
    $where_clause
";

if (!empty($params)) {
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param($param_types, ...$params);
    $count_stmt->execute();
    $total_result = $count_stmt->get_result();
} else {
    $total_result = $conn->query($count_query);
}

$total_topics = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_topics / $per_page);

// Requête principale pour récupérer les topics
$topics_query = "
    SELECT ft.*, 
           u.firstname, u.lastname,
           f.title as formation_title,
           (SELECT COUNT(*) FROM forum_replies fr WHERE fr.topic_id = ft.id) as reply_count,
           (SELECT COUNT(*) FROM forum_replies fr WHERE fr.topic_id = ft.id AND fr.is_solution = 1) as has_solution
    FROM forum_topics ft 
    JOIN users u ON ft.user_id = u.id 
    LEFT JOIN formations f ON ft.formation_id = f.id 
    $where_clause
    ORDER BY ft.is_pinned DESC, ft.updated_at DESC 
    LIMIT ? OFFSET ?
";

$params[] = $per_page;
$params[] = $offset;
$param_types .= 'ii';

$stmt = $conn->prepare($topics_query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$topics_result = $stmt->get_result();

// Récupérer les formations pour le filtre
$formations_query = "SELECT id, title FROM formations WHERE status = 'active' ORDER BY title";
$formations_result = $conn->query($formations_query);

// Statistiques
$stats_query = "
    SELECT 
        COUNT(*) as total_topics,
        COUNT(CASE WHEN is_pinned = 1 THEN 1 END) as pinned_topics,
        COUNT(CASE WHEN is_locked = 1 THEN 1 END) as locked_topics,
        (SELECT COUNT(*) FROM forum_replies) as total_replies,
        (SELECT COUNT(*) FROM forum_topics ft WHERE EXISTS (SELECT 1 FROM forum_replies fr WHERE fr.topic_id = ft.id AND fr.is_solution = 1)) as resolved_topics
    FROM forum_topics
";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr" class="light scroll">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion du Forum - Netcrafter Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
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

        /* Forum specific styles */
        .topic-card {
            transition: all 0.3s ease;
        }
        
        .topic-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .status-badge {
            @apply px-2 py-1 text-xs font-medium rounded-full;
        }

        .status-pinned {
            @apply bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300;
        }

        .status-locked {
            @apply bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300;
        }

        .status-resolved {
            @apply bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300;
        }

        .status-unresolved {
            @apply bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300;
        }

        /* Action buttons */
        .action-btn {
            @apply w-8 h-8 flex items-center justify-center rounded-lg transition-colors duration-200;
        }

        .action-btn:hover {
            transform: scale(1.1);
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
                    <a href="dashboard.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-tachometer-alt w-6 text-center"></i>
                        <span class="ml-2 nav-text">Tableau de bord</span>
                    </a>
                </li>
                <li>
                    <a href="users.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-users w-6 text-center"></i>
                        <span class="ml-2 nav-text">Utilisateurs</span>
                    </a>
                </li>
                <li>
                    <a href="formations.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-graduation-cap w-6 text-center"></i>
                        <span class="ml-2 nav-text">Formations</span>
                    </a>
                </li>
                <li>
                    <a href="subscriptions.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-credit-card w-6 text-center"></i>
                        <span class="ml-2 nav-text">Abonnements</span>
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
                    <a href="forum.php" class="flex items-center px-3 py-2 text-base rounded-lg bg-netblue-100 dark:bg-netblue-900/30 text-netblue-800 dark:text-netblue-300">
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
                <h1 class="text-xl font-bold text-gray-800 dark:text-white">Gestion du Forum</h1>
                
                <!-- Right Menu -->
                <div class="flex items-center space-x-4">
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
            <!-- Messages -->
            <?php if ($message): ?>
            <div class="mb-6">
                <div class="alert alert-<?php echo $message_type; ?> bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-100 border border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-400 text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-700 px-4 py-3 rounded-lg" data-aos="fade-down">
                    <div class="flex items-center">
                        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?> mr-2"></i>
                        <span><?php echo htmlspecialchars($message); ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up">
                    <div class="flex items-center">
                        <div class="p-3 bg-blue-100 dark:bg-blue-900 rounded-full">
                            <i class="fas fa-comments text-blue-600 dark:text-blue-400 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Sujets</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo number_format($stats['total_topics']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="flex items-center">
                        <div class="p-3 bg-green-100 dark:bg-green-900 rounded-full">
                            <i class="fas fa-reply text-green-600 dark:text-green-400 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Réponses</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo number_format($stats['total_replies']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="flex items-center">
                        <div class="p-3 bg-yellow-100 dark:bg-yellow-900 rounded-full">
                            <i class="fas fa-thumbtack text-yellow-600 dark:text-yellow-400 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Épinglés</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo number_format($stats['pinned_topics']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up" data-aos-delay="300">
                    <div class="flex items-center">
                        <div class="p-3 bg-red-100 dark:bg-red-900 rounded-full">
                            <i class="fas fa-lock text-red-600 dark:text-red-400 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Verrouillés</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo number_format($stats['locked_topics']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up" data-aos-delay="400">
                    <div class="flex items-center">
                        <div class="p-3 bg-purple-100 dark:bg-purple-900 rounded-full">
                            <i class="fas fa-check-circle text-purple-600 dark:text-purple-400 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Résolus</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo number_format($stats['resolved_topics']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 mb-8" data-aos="fade-up">
                <form method="GET" class="space-y-4 lg:space-y-0 lg:flex lg:items-center lg:space-x-4">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Rechercher</label>
                        <input type="text" 
                               name="search" 
                               value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="Titre, contenu, ou auteur..."
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-netblue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div class="lg:w-64">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Formation</label>
                        <select name="formation" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-netblue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                            <option value="">Toutes les formations</option>
                            <?php while ($formation = $formations_result->fetch_assoc()): ?>
                                <option value="<?php echo $formation['id']; ?>" <?php echo $formation_filter == $formation['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($formation['title']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="lg:w-48">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Statut</label>
                        <select name="status" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-netblue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                            <option value="">Tous les statuts</option>
                            <option value="pinned" <?php echo $status_filter === 'pinned' ? 'selected' : ''; ?>>Épinglés</option>
                            <option value="locked" <?php echo $status_filter === 'locked' ? 'selected' : ''; ?>>Verrouillés</option>
                            <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Résolus</option>
                            <option value="unresolved" <?php echo $status_filter === 'unresolved' ? 'selected' : ''; ?>>Non résolus</option>
                        </select>
                    </div>
                    
                    <div class="lg:w-auto flex space-x-2">
                        <button type="submit" class="bg-netblue-600 hover:bg-netblue-700 text-white px-6 py-2 rounded-lg transition-colors">
                            <i class="fas fa-search mr-2"></i>
                            Filtrer
                        </button>
                        <a href="forum.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition-colors">
                            <i class="fas fa-times mr-2"></i>
                            Réinitialiser
                        </a>
                    </div>
                </form>
            </div>

            <!-- Topics List -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden" data-aos="fade-up">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Sujets du Forum (<?php echo number_format($total_topics); ?> résultats)
                        </h2>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            Page <?php echo $page; ?> sur <?php echo $total_pages; ?>
                        </div>
                    </div>
                </div>

                <?php if ($topics_result->num_rows > 0): ?>
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php while ($topic = $topics_result->fetch_assoc()): ?>
                    <div class="topic-card p-6 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <div class="flex items-start justify-between">
                            <div class="flex-1 min-w-0">
                                <!-- Topic Header -->
                                <div class="flex items-center space-x-2 mb-2">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white truncate">
                                        <?php echo htmlspecialchars($topic['title']); ?>
                                    </h3>
                                    
                                    <!-- Status Badges -->
                                    <?php if ($topic['is_pinned']): ?>
                                        <span class="status-badge status-pinned">
                                            <i class="fas fa-thumbtack mr-1"></i>
                                            Épinglé
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($topic['is_locked']): ?>
                                        <span class="status-badge status-locked">
                                            <i class="fas fa-lock mr-1"></i>
                                            Verrouillé
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($topic['has_solution'] > 0): ?>
                                        <span class="status-badge status-resolved">
                                            <i class="fas fa-check-circle mr-1"></i>
                                            Résolu
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge status-unresolved">
                                            <i class="fas fa-question-circle mr-1"></i>
                                            Non résolu
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Topic Content Preview -->
                                <p class="text-gray-600 dark:text-gray-400 mb-3 line-clamp-2">
                                    <?php echo htmlspecialchars(substr($topic['content'], 0, 200)) . (strlen($topic['content']) > 200 ? '...' : ''); ?>
                                </p>
                                
                                <!-- Topic Meta -->
                                <div class="flex flex-wrap items-center text-sm text-gray-500 dark:text-gray-400 space-x-4">
                                    <div class="flex items-center">
                                        <i class="fas fa-user mr-1"></i>
                                        <span><?php echo htmlspecialchars($topic['firstname'] . ' ' . $topic['lastname']); ?></span>
                                    </div>
                                    
                                    <?php if ($topic['formation_title']): ?>
                                    <div class="flex items-center">
                                        <i class="fas fa-graduation-cap mr-1"></i>
                                        <span><?php echo htmlspecialchars($topic['formation_title']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="flex items-center">
                                        <i class="fas fa-reply mr-1"></i>
                                        <span><?php echo $topic['reply_count']; ?> réponse(s)</span>
                                    </div>
                                    
                                    <div class="flex items-center">
                                        <i class="fas fa-eye mr-1"></i>
                                        <span><?php echo $topic['views']; ?> vue(s)</span>
                                    </div>
                                    
                                    <div class="flex items-center">
                                        <i class="fas fa-calendar mr-1"></i>
                                        <span><?php echo date('d/m/Y H:i', strtotime($topic['created_at'])); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="flex items-center space-x-2 ml-4">
                                <!-- View Topic -->
                                <button onclick="viewTopic(<?php echo $topic['id']; ?>)" 
                                        class="action-btn bg-blue-100 hover:bg-blue-200 text-blue-600 dark:bg-blue-900 dark:hover:bg-blue-800 dark:text-blue-400" 
                                        title="Voir le sujet">
                                    <i class="fas fa-eye"></i>
                                </button>
                                
                                <!-- Toggle Pin -->
                                <form method="POST" class="inline" onsubmit="return confirm('Êtes-vous sûr ?')">
                                    <input type="hidden" name="action" value="toggle_pin">
                                    <input type="hidden" name="topic_id" value="<?php echo $topic['id']; ?>">
                                    <input type="hidden" name="current_status" value="<?php echo $topic['is_pinned']; ?>">
                                    <button type="submit" 
                                            class="action-btn <?php echo $topic['is_pinned'] ? 'bg-yellow-100 hover:bg-yellow-200 text-yellow-600 dark:bg-yellow-900 dark:hover:bg-yellow-800 dark:text-yellow-400' : 'bg-gray-100 hover:bg-gray-200 text-gray-600 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-400'; ?>" 
                                            title="<?php echo $topic['is_pinned'] ? 'Désépingler' : 'Épingler'; ?>">
                                        <i class="fas fa-thumbtack"></i>
                                    </button>
                                </form>
                                
                                <!-- Toggle Lock -->
                                <form method="POST" class="inline" onsubmit="return confirm('Êtes-vous sûr ?')">
                                    <input type="hidden" name="action" value="toggle_lock">
                                    <input type="hidden" name="topic_id" value="<?php echo $topic['id']; ?>">
                                    <input type="hidden" name="current_status" value="<?php echo $topic['is_locked']; ?>">
                                    <button type="submit" 
                                            class="action-btn <?php echo $topic['is_locked'] ? 'bg-red-100 hover:bg-red-200 text-red-600 dark:bg-red-900 dark:hover:bg-red-800 dark:text-red-400' : 'bg-gray-100 hover:bg-gray-200 text-gray-600 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-400'; ?>" 
                                            title="<?php echo $topic['is_locked'] ? 'Déverrouiller' : 'Verrouiller'; ?>">
                                        <i class="fas fa-lock"></i>
                                    </button>
                                </form>
                                
                                <!-- Delete Topic -->
                                <form method="POST" class="inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce sujet ? Cette action est irréversible.')">
                                    <input type="hidden" name="action" value="delete_topic">
                                    <input type="hidden" name="topic_id" value="<?php echo $topic['id']; ?>">
                                    <button type="submit" 
                                            class="action-btn bg-red-100 hover:bg-red-200 text-red-600 dark:bg-red-900 dark:hover:bg-red-800 dark:text-red-400" 
                                            title="Supprimer le sujet">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                <div class="p-12 text-center">
                    <i class="fas fa-comments text-gray-400 text-6xl mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Aucun sujet trouvé</h3>
                    <p class="text-gray-500 dark:text-gray-400">
                        <?php if (!empty($search) || !empty($formation_filter) || !empty($status_filter)): ?>
                            Aucun sujet ne correspond à vos critères de recherche.
                        <?php else: ?>
                            Le forum est encore vide. Les sujets apparaîtront ici une fois créés par les utilisateurs.
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700 dark:text-gray-300">
                            Affichage de <?php echo (($page - 1) * $per_page) + 1; ?> à <?php echo min($page * $per_page, $total_topics); ?> 
                            sur <?php echo $total_topics; ?> résultats
                        </div>
                        
                        <div class="flex items-center space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&formation=<?php echo urlencode($formation_filter); ?>&status=<?php echo urlencode($status_filter); ?>" 
                                   class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700">
                                    <i class="fas fa-chevron-left mr-1"></i>
                                    Précédent
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&formation=<?php echo urlencode($formation_filter); ?>&status=<?php echo urlencode($status_filter); ?>" 
                                   class="px-3 py-2 text-sm font-medium <?php echo $i === $page ? 'text-netblue-600 bg-netblue-50 border-netblue-500 dark:bg-netblue-900 dark:text-netblue-300' : 'text-gray-500 bg-white border-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700'; ?> border rounded-lg">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&formation=<?php echo urlencode($formation_filter); ?>&status=<?php echo urlencode($status_filter); ?>" 
                                   class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700">
                                    Suivant
                                    <i class="fas fa-chevron-right ml-1"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
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
                        <a href="#" class="hover:text-netblue-600 dark:hover:text-netblue-400">Support</a>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <!-- Topic Details Modal -->
    <div id="topicModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white" id="modalTitle">Détails du sujet</h3>
                    <button onclick="closeTopicModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div id="modalContent" class="text-gray-700 dark:text-gray-300">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
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
        });
        
        // Make toggleMobileMenu globally accessible
        function toggleMobileMenu() {
            const sidenav = document.getElementById('sidenav');
            const overlay = document.getElementById('overlay');
            
            sidenav.classList.toggle('open');
            overlay.classList.toggle('active');
            document.body.classList.toggle('overflow-hidden');
        }

        // Topic modal functions
        function viewTopic(topicId) {
            // Show modal
            document.getElementById('topicModal').classList.remove('hidden');
            document.getElementById('modalContent').innerHTML = '<div class="flex justify-center py-8"><i class="fas fa-spinner fa-spin text-2xl text-netblue-600"></i></div>';
            
            // Load topic details via AJAX
            fetch(`get_topic_details.php?id=${topicId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        document.getElementById('modalTitle').textContent = data.topic.title;
                        document.getElementById('modalContent').innerHTML = generateTopicHTML(data.topic, data.replies);
                    } else {
                        document.getElementById('modalContent').innerHTML = `
                            <div class="text-center py-8">
                                <i class="fas fa-exclamation-triangle text-yellow-500 text-4xl mb-4"></i>
                                <p class="text-red-600 mb-4">${data.message || 'Erreur lors du chargement du sujet.'}</p>
                                <button onclick="viewTopic(${topicId})" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded-lg">
                                    <i class="fas fa-retry mr-2"></i>Réessayer
                                </button>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('modalContent').innerHTML = `
                        <div class="text-center py-8">
                            <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
                            <p class="text-red-600 mb-2">Erreur de connexion</p>
                            <p class="text-gray-600 text-sm mb-4">Impossible de charger les détails du sujet. Vérifiez que le fichier get_topic_details.php existe.</p>
                            <button onclick="viewTopic(${topicId})" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded-lg">
                                <i class="fas fa-retry mr-2"></i>Réessayer
                            </button>
                        </div>
                    `;
                });
        }

        function closeTopicModal() {
            document.getElementById('topicModal').classList.add('hidden');
        }

        function generateTopicHTML(topic, replies) {
            let html = `
                <div class="space-y-6">
                    <!-- Topic Content -->
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center space-x-2">
                                <span class="font-medium">${topic.author_name}</span>
                                <span class="text-sm text-gray-500">${topic.created_at}</span>
                            </div>
                            <div class="flex space-x-2">`;
                            
            if (topic.is_pinned) {
                html += `<span class="status-badge status-pinned"><i class="fas fa-thumbtack mr-1"></i>Épinglé</span>`;
            }
            if (topic.is_locked) {
                html += `<span class="status-badge status-locked"><i class="fas fa-lock mr-1"></i>Verrouillé</span>`;
            }
            
            html += `
                            </div>
                        </div>
                        <div class="prose dark:prose-invert max-w-none">
                            ${topic.content.replace(/\n/g, '<br>')}
                        </div>
                    </div>
                    
                    <!-- Replies -->
                    <div class="space-y-4">
                        <h4 class="font-semibold text-gray-900 dark:text-white">Réponses (${replies.length})</h4>`;
                        
            if (replies.length > 0) {
                replies.forEach(reply => {
                    html += `
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 ${reply.is_solution ? 'border-l-4 border-green-500' : ''}">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center space-x-2">
                                    <span class="font-medium">${reply.author_name}</span>
                                    <span class="text-sm text-gray-500">${reply.created_at}</span>
                                    ${reply.is_solution ? '<span class="status-badge status-resolved"><i class="fas fa-check-circle mr-1"></i>Solution</span>' : ''}
                                </div>
                                <div class="flex space-x-2">
                                    ${!reply.is_solution ? `
                                    <form method="POST" class="inline" onsubmit="return confirm('Marquer cette réponse comme solution ?')">
                                        <input type="hidden" name="action" value="mark_solution">
                                        <input type="hidden" name="reply_id" value="${reply.id}">
                                        <input type="hidden" name="topic_id" value="${reply.topic_id}">
                                        <button type="submit" class="text-green-600 hover:text-green-800 text-sm" title="Marquer comme solution">
                                            <i class="fas fa-check-circle"></i>
                                        </button>
                                    </form>
                                    ` : ''}
                                    <form method="POST" class="inline" onsubmit="return confirm('Supprimer cette réponse ?')">
                                        <input type="hidden" name="action" value="delete_reply">
                                        <input type="hidden" name="reply_id" value="${reply.id}">
                                        <button type="submit" class="text-red-600 hover:text-red-800 text-sm" title="Supprimer">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <div class="prose dark:prose-invert max-w-none">
                                ${reply.content.replace(/\n/g, '<br>')}
                            </div>
                        </div>`;
                });
            } else {
                html += '<p class="text-gray-500 dark:text-gray-400 text-center py-8">Aucune réponse pour le moment.</p>';
            }
            
            html += `
                    </div>
                </div>`;
            
            return html;
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

        // Close modal when clicking outside
        document.getElementById('topicModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeTopicModal();
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Escape to close modal
            if (e.key === 'Escape') {
                closeTopicModal();
                
                const sidenav = document.getElementById('sidenav');
                const overlay = document.getElementById('overlay');
                
                if (sidenav.classList.contains('open')) {
                    sidenav.classList.remove('open');
                    overlay.classList.remove('active');
                    document.body.classList.remove('overflow-hidden');
                }
            }
        });

        // Auto-refresh stats every 30 seconds
        setInterval(function() {
            fetch('get_forum_stats.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update stats without page reload
                        // Implementation depends on your needs
                        console.log('Stats updated:', data.stats);
                    }
                })
                .catch(error => console.error('Error updating stats:', error));
        }, 30000);
    </script>
</body>
</html>