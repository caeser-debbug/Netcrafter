<?php
// admin/subscriptions.php
session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/../db.php';

// Connexion à la base de données
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Échec de la connexion: " . $conn->connect_error);
}
$conn->set_charset("utf8");

// Messages
$success_message = '';
$error_message = '';

// Traitement des actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'approve_subscription':
            approveSubscription();
            break;
        case 'reject_subscription':
            rejectSubscription();
            break;
        case 'extend_subscription':
            extendSubscription();
            break;
        case 'cancel_subscription':
            cancelSubscription();
            break;
        case 'bulk_action':
            bulkAction();
            break;
    }
    
    // Redirection pour éviter la re-soumission
    header("Location: subscriptions.php" . 
           ($success_message ? "?success=" . urlencode($success_message) : "") . 
           ($error_message ? "&error=" . urlencode($error_message) : ""));
    exit;
}

// Récupérer les messages depuis l'URL
if (isset($_GET['success'])) {
    $success_message = $_GET['success'];
}
if (isset($_GET['error'])) {
    $error_message = $_GET['error'];
}

function approveSubscription() {
    global $conn, $success_message, $error_message;
    
    $subscription_id = intval($_POST['subscription_id']);
    
    // Récupérer les détails de l'abonnement
    $get_subscription = "SELECT fs.*, f.title as formation_title FROM formation_subscriptions fs 
                        JOIN formations f ON fs.formation_id = f.id 
                        WHERE fs.id = ?";
    $stmt = $conn->prepare($get_subscription);
    $stmt->bind_param("i", $subscription_id);
    $stmt->execute();
    $subscription = $stmt->get_result()->fetch_assoc();
    
    if (!$subscription) {
        $error_message = "Abonnement non trouvé.";
        return;
    }
    
    // Calculer les dates de début et fin
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d', strtotime("+{$subscription['subscription_months']} months"));
    
    // Mettre à jour l'abonnement
    $update_query = "UPDATE formation_subscriptions SET 
                     status = 'active', 
                     start_date = ?, 
                     end_date = ?, 
                     updated_at = NOW() 
                     WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ssi", $start_date, $end_date, $subscription_id);
    
    if ($stmt->execute()) {
        $success_message = "Abonnement approuvé avec succès.";
        
        // Log de l'activité
        $log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'approve_subscription', ?)";
        $log_stmt = $conn->prepare($log_query);
        $description = "Approbation de l'abonnement ID: $subscription_id";
        $log_stmt->bind_param("is", $_SESSION['admin_id'], $description);
        $log_stmt->execute();
    } else {
        $error_message = "Erreur lors de l'approbation de l'abonnement.";
    }
}

function rejectSubscription() {
    global $conn, $success_message, $error_message;
    
    $subscription_id = intval($_POST['subscription_id']);
    $reason = trim($_POST['reason'] ?? '');
    
    $update_query = "UPDATE formation_subscriptions SET 
                     status = 'rejected', 
                     updated_at = NOW() 
                     WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("i", $subscription_id);
    
    if ($stmt->execute()) {
        $success_message = "Abonnement rejeté.";
        
        // Log de l'activité
        $log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'reject_subscription', ?)";
        $log_stmt = $conn->prepare($log_query);
        $description = "Rejet de l'abonnement ID: $subscription_id" . ($reason ? " - Raison: $reason" : "");
        $log_stmt->bind_param("is", $_SESSION['admin_id'], $description);
        $log_stmt->execute();
    } else {
        $error_message = "Erreur lors du rejet de l'abonnement.";
    }
}

function extendSubscription() {
    global $conn, $success_message, $error_message;
    
    $subscription_id = intval($_POST['subscription_id']);
    $additional_months = intval($_POST['additional_months']);
    
    if ($additional_months <= 0) {
        $error_message = "Nombre de mois invalide.";
        return;
    }
    
    // Récupérer la date de fin actuelle
    $get_end_date = "SELECT end_date FROM formation_subscriptions WHERE id = ?";
    $stmt = $conn->prepare($get_end_date);
    $stmt->bind_param("i", $subscription_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if (!$result) {
        $error_message = "Abonnement non trouvé.";
        return;
    }
    
    // Calculer la nouvelle date de fin
    $current_end_date = $result['end_date'];
    $new_end_date = date('Y-m-d', strtotime($current_end_date . " +$additional_months months"));
    
    $update_query = "UPDATE formation_subscriptions SET 
                     end_date = ?, 
                     updated_at = NOW() 
                     WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("si", $new_end_date, $subscription_id);
    
    if ($stmt->execute()) {
        $success_message = "Abonnement prolongé de $additional_months mois.";
        
        // Log de l'activité
        $log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'extend_subscription', ?)";
        $log_stmt = $conn->prepare($log_query);
        $description = "Prolongation de l'abonnement ID: $subscription_id de $additional_months mois";
        $log_stmt->bind_param("is", $_SESSION['admin_id'], $description);
        $log_stmt->execute();
    } else {
        $error_message = "Erreur lors de la prolongation de l'abonnement.";
    }
}

function cancelSubscription() {
    global $conn, $success_message, $error_message;
    
    $subscription_id = intval($_POST['subscription_id']);
    
    $update_query = "UPDATE formation_subscriptions SET 
                     status = 'cancelled', 
                     updated_at = NOW() 
                     WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("i", $subscription_id);
    
    if ($stmt->execute()) {
        $success_message = "Abonnement annulé.";
        
        // Log de l'activité
        $log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'cancel_subscription', ?)";
        $log_stmt = $conn->prepare($log_query);
        $description = "Annulation de l'abonnement ID: $subscription_id";
        $log_stmt->bind_param("is", $_SESSION['admin_id'], $description);
        $log_stmt->execute();
    } else {
        $error_message = "Erreur lors de l'annulation de l'abonnement.";
    }
}

function bulkAction() {
    global $conn, $success_message, $error_message;
    
    $bulk_action = $_POST['bulk_action'];
    $selected_subscriptions = $_POST['selected_subscriptions'] ?? [];
    
    if (empty($selected_subscriptions)) {
        $error_message = "Veuillez sélectionner au moins un abonnement.";
        return;
    }
    
    $count = 0;
    foreach ($selected_subscriptions as $subscription_id) {
        $subscription_id = intval($subscription_id);
        
        switch ($bulk_action) {
            case 'approve':
                $_POST['subscription_id'] = $subscription_id;
                approveSubscription();
                $count++;
                break;
            case 'reject':
                $_POST['subscription_id'] = $subscription_id;
                $_POST['reason'] = 'Action groupée';
                rejectSubscription();
                $count++;
                break;
            case 'cancel':
                $_POST['subscription_id'] = $subscription_id;
                cancelSubscription();
                $count++;
                break;
        }
    }
    
    if ($count > 0) {
        $success_message = "$count abonnement(s) traité(s) avec succès.";
    }
}

// Paramètres de recherche et filtrage
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$formation_filter = isset($_GET['formation']) ? intval($_GET['formation']) : 0;
$payment_filter = isset($_GET['payment']) ? $_GET['payment'] : 'all';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$order = isset($_GET['order']) ? $_GET['order'] : 'DESC';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Construction de la requête
$where_conditions = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where_conditions[] = "(u.firstname LIKE CONCAT('%', ?, '%') OR u.lastname LIKE CONCAT('%', ?, '%') OR u.phone LIKE CONCAT('%', ?, '%') OR f.title LIKE CONCAT('%', ?, '%'))";
    $params = array_merge($params, [$search, $search, $search, $search]);
    $types .= "ssss";
}

if ($status_filter !== 'all') {
    $where_conditions[] = "fs.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($formation_filter > 0) {
    $where_conditions[] = "fs.formation_id = ?";
    $params[] = $formation_filter;
    $types .= "i";
}

if ($payment_filter !== 'all') {
    $where_conditions[] = "fs.payment_method = ?";
    $params[] = $payment_filter;
    $types .= "s";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Compter le total d'abonnements
$count_query = "SELECT COUNT(*) as total FROM formation_subscriptions fs
                JOIN users u ON fs.user_id = u.id
                JOIN formations f ON fs.formation_id = f.id
                $where_clause";
$count_stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_subscriptions = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_subscriptions / $per_page);

// Récupérer les abonnements
$subscriptions_query = "SELECT fs.*, 
                        u.firstname, u.lastname, u.phone, u.email,
                        f.title as formation_title, f.price_per_month,
                        c.name as category_name
                        FROM formation_subscriptions fs
                        JOIN users u ON fs.user_id = u.id
                        JOIN formations f ON fs.formation_id = f.id
                        LEFT JOIN formation_categories c ON f.category_id = c.id
                        $where_clause
                        ORDER BY fs.$sort_by $order
                        LIMIT ?, ?";

$params[] = $offset;
$params[] = $per_page;
$types .= "ii";

$subscriptions_stmt = $conn->prepare($subscriptions_query);
$subscriptions_stmt->bind_param($types, ...$params);
$subscriptions_stmt->execute();
$subscriptions_result = $subscriptions_stmt->get_result();

$subscriptions = [];
while ($row = $subscriptions_result->fetch_assoc()) {
    $subscriptions[] = $row;
}

// Récupérer les formations pour les filtres
$formations_query = "SELECT id, title FROM formations WHERE status = 'active' ORDER BY title";
$formations_result = $conn->query($formations_query);
$formations = [];
while ($row = $formations_result->fetch_assoc()) {
    $formations[] = $row;
}

// Statistiques générales
$stats_query = "SELECT 
    COUNT(*) as total_subscriptions,
    SUM(CASE WHEN status = 'active' AND end_date >= CURDATE() THEN 1 ELSE 0 END) as active_subscriptions,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_subscriptions,
    SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_subscriptions,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_subscriptions,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_subscriptions,
    SUM(CASE WHEN status IN ('active', 'expired') THEN amount_paid ELSE 0 END) as total_revenue,
    SUM(CASE WHEN status IN ('active', 'expired') AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) THEN amount_paid ELSE 0 END) as monthly_revenue
    FROM formation_subscriptions";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr" class="light scroll">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Abonnements - Admin Netcrafter</title>
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

        /* Table hover effects */
        .subscription-row {
            transition: all 0.2s ease;
        }
        
        .subscription-row:hover {
            background-color: #f8fafc;
            transform: translateX(2px);
        }

        /* Modal styles */
        .modal {
            backdrop-filter: blur(4px);
        }

        /* Status badges */
        .status-pending { @apply bg-yellow-100 text-yellow-800; }
        .status-active { @apply bg-green-100 text-green-800; }
        .status-expired { @apply bg-gray-100 text-gray-800; }
        .status-rejected { @apply bg-red-100 text-red-800; }
        .status-cancelled { @apply bg-orange-100 text-orange-800; }
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
        
        <!-- Navigation Menu -->
        <nav class="mt-4 px-2 overflow-y-auto" style="max-height: calc(100vh - 120px);">
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
                    <a href="subscriptions.php" class="flex items-center px-3 py-2 text-base rounded-lg bg-netblue-100 dark:bg-netblue-900/30 text-netblue-800 dark:text-netblue-300">
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
                    <a href="settings.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-cog w-6 text-center"></i>
                        <span class="ml-2 nav-text">Paramètres</span>
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
                <h1 class="text-xl font-bold text-gray-800 dark:text-white">Gestion des Abonnements</h1>
                
                <!-- Actions -->
                <div class="flex items-center space-x-3">
                    <button onclick="exportSubscriptions()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors text-sm">
                        <i class="fas fa-download mr-2"></i>Exporter
                    </button>
                </div>
            </div>
        </header>
        
        <!-- Messages -->
        <?php if (!empty($success_message)): ?>
        <div class="mx-4 mt-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
            <i class="fas fa-check-circle mr-2"></i>
            <?php echo htmlspecialchars($success_message); ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
        <div class="mx-4 mt-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>
        
        <!-- Main Content Area -->
        <main class="p-4">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-blue-100 dark:bg-blue-900 p-3 rounded-lg">
                            <i class="fas fa-credit-card text-2xl text-blue-600 dark:text-blue-400"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">Total</h3>
                            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo number_format($stats['total_subscriptions']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-green-100 dark:bg-green-900 p-3 rounded-lg">
                            <i class="fas fa-check-circle text-2xl text-green-600 dark:text-green-400"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">Actifs</h3>
                            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo number_format($stats['active_subscriptions']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-yellow-100 dark:bg-yellow-900 p-3 rounded-lg">
                            <i class="fas fa-clock text-2xl text-yellow-600 dark:text-yellow-400"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">En attente</h3>
                            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo number_format($stats['pending_subscriptions']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up" data-aos-delay="300">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-purple-100 dark:bg-purple-900 p-3 rounded-lg">
                            <i class="fas fa-coins text-2xl text-purple-600 dark:text-purple-400"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">Revenus</h3>
                            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo number_format($stats['total_revenue']); ?> F</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filters and Search -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 mb-8" data-aos="fade-up">
                <form method="GET" action="subscriptions.php" class="flex flex-col lg:flex-row gap-4">
                    <!-- Search -->
                    <div class="flex-1">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Rechercher par nom, téléphone ou formation..." class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                    </div>
                    
                    <!-- Status Filter -->
                    <div>
                        <select name="status" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>Tous les statuts</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>En attente</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Actifs</option>
                            <option value="expired" <?php echo $status_filter === 'expired' ? 'selected' : ''; ?>>Expirés</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Annulés</option>
                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejetés</option>
                        </select>
                    </div>
                    
                    <!-- Formation Filter -->
                    <div>
                        <select name="formation" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                            <option value="0">Toutes les formations</option>
                            <?php foreach ($formations as $formation): ?>
                            <option value="<?php echo $formation['id']; ?>" <?php echo $formation_filter == $formation['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($formation['title']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Payment Filter -->
                    <div>
                        <select name="payment" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                            <option value="all" <?php echo $payment_filter === 'all' ? 'selected' : ''; ?>>Tous les paiements</option>
                            <option value="nita" <?php echo $payment_filter === 'nita' ? 'selected' : ''; ?>>Nita Money</option>
                            <option value="amana" <?php echo $payment_filter === 'amana' ? 'selected' : ''; ?>>Amana Money</option>
                            <option value="zeyna" <?php echo $payment_filter === 'zeyna' ? 'selected' : ''; ?>>Zeyna Money</option>
                            <option value="niya" <?php echo $payment_filter === 'niya' ? 'selected' : ''; ?>>Niya Money</option>
                        </select>
                    </div>
                    
                    <!-- Sort -->
                    <div>
                        <select name="sort" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                            <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Date de création</option>
                            <option value="start_date" <?php echo $sort_by === 'start_date' ? 'selected' : ''; ?>>Date de début</option>
                            <option value="end_date" <?php echo $sort_by === 'end_date' ? 'selected' : ''; ?>>Date de fin</option>
                            <option value="amount_paid" <?php echo $sort_by === 'amount_paid' ? 'selected' : ''; ?>>Montant</option>
                        </select>
                    </div>
                    
                    <!-- Submit -->
                    <button type="submit" class="bg-netblue-600 hover:bg-netblue-700 text-white px-6 py-2 rounded-lg transition-colors">
                        <i class="fas fa-search mr-2"></i>Filtrer
                    </button>
                    
                    <!-- Reset -->
                    <a href="subscriptions.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition-colors text-center">
                        <i class="fas fa-refresh mr-2"></i>Reset
                    </a>
                </form>
            </div>
            
            <!-- Bulk Actions -->
            <?php if (!empty($subscriptions)): ?>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 mb-6" data-aos="fade-up">
                <form id="bulkForm" method="POST">
                    <input type="hidden" name="action" value="bulk_action">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <label class="flex items-center">
                                <input type="checkbox" id="selectAll" class="rounded border-gray-300 text-netblue-600 shadow-sm focus:border-netblue-300 focus:ring focus:ring-netblue-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Tout sélectionner</span>
                            </label>
                            
                            <select name="bulk_action" id="bulkAction" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                                <option value="">Actions groupées</option>
                                <option value="approve">Approuver</option>
                                <option value="reject">Rejeter</option>
                                <option value="cancel">Annuler</option>
                            </select>
                            
                            <button type="button" onclick="executeBulkAction()" class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg transition-colors text-sm">
                                <i class="fas fa-bolt mr-2"></i>Exécuter
                            </button>
                        </div>
                        
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            <span id="selectedCount">0</span> sélectionné(s)
                        </div>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            
            <!-- Subscriptions Table -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden" data-aos="fade-up">
                <?php if (empty($subscriptions)): ?>
                <div class="p-12 text-center">
                    <i class="fas fa-credit-card text-6xl text-gray-400 mb-4"></i>
                    <h3 class="text-xl font-bold mb-2 dark:text-white">Aucun abonnement trouvé</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-6">Modifiez vos critères de recherche ou attendez de nouveaux abonnements.</p>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left">
                                    <input type="checkbox" id="selectAllTable" class="rounded border-gray-300 text-netblue-600 shadow-sm">
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Utilisateur
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Formation
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Montant
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Durée
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Paiement
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Statut
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Dates
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach ($subscriptions as $subscription): ?>
                            <tr class="subscription-row">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="checkbox" name="selected_subscriptions[]" value="<?php echo $subscription['id']; ?>" class="subscription-checkbox rounded border-gray-300 text-netblue-600 shadow-sm">
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-gray-300 dark:bg-gray-600 rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0">
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
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 dark:text-white font-medium">
                                        <?php echo htmlspecialchars($subscription['formation_title']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo htmlspecialchars($subscription['category_name']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        <?php echo number_format($subscription['amount_paid']); ?> F
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        Prix: <?php echo number_format($subscription['price_per_month']); ?> F/mois
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                        <?php echo $subscription['subscription_months']; ?> mois
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php 
                                            switch($subscription['payment_method']) {
                                                case 'nita': echo 'bg-purple-100 text-purple-800'; break;
                                                case 'amana': echo 'bg-green-100 text-green-800'; break;
                                                case 'zeyna': echo 'bg-yellow-100 text-yellow-800'; break;
                                                case 'niya': echo 'bg-red-100 text-red-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?php echo strtoupper($subscription['payment_method']); ?>
                                        </span>
                                        <?php if (!empty($subscription['payment_proof'])): ?>
                                        <button onclick="viewPaymentProof('<?php echo $subscription['payment_proof']; ?>')" class="ml-2 text-blue-600 hover:text-blue-800" title="Voir le justificatif">
                                            <i class="fas fa-receipt text-sm"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full status-<?php echo $subscription['status']; ?>">
                                        <?php 
                                        switch($subscription['status']) {
                                            case 'pending': echo 'En attente'; break;
                                            case 'active': echo 'Actif'; break;
                                            case 'expired': echo 'Expiré'; break;
                                            case 'cancelled': echo 'Annulé'; break;
                                            case 'rejected': echo 'Rejeté'; break;
                                            default: echo ucfirst($subscription['status']);
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <div>Créé: <?php echo date('d/m/Y', strtotime($subscription['created_at'])); ?></div>
                                    <?php if ($subscription['start_date']): ?>
                                    <div>Début: <?php echo date('d/m/Y', strtotime($subscription['start_date'])); ?></div>
                                    <?php endif; ?>
                                    <?php if ($subscription['end_date']): ?>
                                    <div>Fin: <?php echo date('d/m/Y', strtotime($subscription['end_date'])); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center space-x-2">
                                        <?php if ($subscription['status'] === 'pending'): ?>
                                        <button onclick="approveSubscription(<?php echo $subscription['id']; ?>)" class="text-green-600 hover:text-green-900 dark:text-green-400" title="Approuver">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button onclick="rejectSubscription(<?php echo $subscription['id']; ?>)" class="text-red-600 hover:text-red-900 dark:text-red-400" title="Rejeter">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($subscription['status'] === 'active'): ?>
                                        <button onclick="extendSubscription(<?php echo $subscription['id']; ?>)" class="text-blue-600 hover:text-blue-900 dark:text-blue-400" title="Prolonger">
                                            <i class="fas fa-plus-circle"></i>
                                        </button>
                                        <button onclick="cancelSubscription(<?php echo $subscription['id']; ?>)" class="text-orange-600 hover:text-orange-900 dark:text-orange-400" title="Annuler">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                        <?php endif; ?>
                                        
                                        <button onclick="viewSubscriptionDetails(<?php echo $subscription['id']; ?>)" class="text-gray-600 hover:text-gray-900 dark:text-gray-400" title="Détails">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="flex items-center justify-between bg-white dark:bg-gray-800 px-6 py-3 rounded-xl shadow-lg mt-6">
                <div class="flex-1 flex justify-between sm:hidden">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&formation=<?php echo $formation_filter; ?>&payment=<?php echo $payment_filter; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $order; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Précédent
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&formation=<?php echo $formation_filter; ?>&payment=<?php echo $payment_filter; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $order; ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Suivant
                    </a>
                    <?php endif; ?>
                </div>
                
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            Affichage de <span class="font-medium"><?php echo ($page-1) * $per_page + 1; ?></span> à <span class="font-medium"><?php echo min($page * $per_page, $total_subscriptions); ?></span> sur <span class="font-medium"><?php echo $total_subscriptions; ?></span> résultats
                        </p>
                    </div>
                    
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                            <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&formation=<?php echo $formation_filter; ?>&payment=<?php echo $payment_filter; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $order; ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&formation=<?php echo $formation_filter; ?>&payment=<?php echo $payment_filter; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $order; ?>" class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?php echo $i == $page ? 'z-10 bg-netblue-50 border-netblue-500 text-netblue-600' : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'; ?>">
                                <?php echo $i; ?>
                            </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&formation=<?php echo $formation_filter; ?>&payment=<?php echo $payment_filter; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $order; ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
    <!-- Extend Subscription Modal -->
    <div id="extendModal" class="fixed inset-0 z-50 hidden">
        <div class="modal flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeExtendModal()"></div>
            
            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form id="extendForm" method="POST">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                                <i class="fas fa-plus-circle text-blue-600"></i>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                                    Prolonger l'abonnement
                                </h3>
                                <div class="mt-4">
                                    <input type="hidden" name="action" value="extend_subscription">
                                    <input type="hidden" name="subscription_id" id="extendSubscriptionId">
                                    
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Nombre de mois supplémentaires
                                    </label>
                                    <input type="number" name="additional_months" min="1" max="12" value="1" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                            Prolonger
                        </button>
                        <button type="button" onclick="closeExtendModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
                            Annuler
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Reject Subscription Modal -->
    <div id="rejectModal" class="fixed inset-0 z-50 hidden">
        <div class="modal flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeRejectModal()"></div>
            
            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form id="rejectForm" method="POST">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                <i class="fas fa-times text-red-600"></i>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                                    Rejeter l'abonnement
                                </h3>
                                <div class="mt-4">
                                    <input type="hidden" name="action" value="reject_subscription">
                                    <input type="hidden" name="subscription_id" id="rejectSubscriptionId">
                                    
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Raison du rejet (optionnel)
                                    </label>
                                    <textarea name="reason" rows="3" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-red-500" placeholder="Expliquez pourquoi cet abonnement est rejeté..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                            Rejeter
                        </button>
                        <button type="button" onclick="closeRejectModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
                            Annuler
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Payment Proof Modal -->
    <div id="proofModal" class="fixed inset-0 z-50 hidden">
        <div class="modal flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeProofModal()"></div>
            
            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                            Justificatif de paiement
                        </h3>
                        <button onclick="closeProofModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    
                    <div class="text-center">
                        <img id="proofImage" src="" alt="Justificatif de paiement" class="max-w-full h-auto rounded-lg shadow-lg">
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Subscription Details Modal -->
    <div id="detailsModal" class="fixed inset-0 z-50 hidden">
        <div class="modal flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeDetailsModal()"></div>
            
            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full max-h-screen overflow-y-auto">
                <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                            Détails de l'abonnement
                        </h3>
                        <button onclick="closeDetailsModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    
                    <div id="subscriptionDetails">
                        <!-- Content will be loaded dynamically -->
                    </div>
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
            // Sidebar functionality
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
                    
                    const icon = sidenavToggle.querySelector('i');
                    if (sidenav.classList.contains('collapsed')) {
                        icon.classList.remove('fa-chevron-left');
                        icon.classList.add('fa-chevron-right');
                    } else {
                        icon.classList.remove('fa-chevron-right');
                        icon.classList.add('fa-chevron-left');
                    }
                    
                    localStorage.setItem('adminSidenavCollapsed', sidenav.classList.contains('collapsed'));
                }
            }
            
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
            
            // Bulk selection functionality
            const selectAllCheckbox = document.getElementById('selectAll');
            const selectAllTableCheckbox = document.getElementById('selectAllTable');
            const subscriptionCheckboxes = document.querySelectorAll('.subscription-checkbox');
            const selectedCount = document.getElementById('selectedCount');
            
            function updateSelectedCount() {
                const selectedCheckboxes = document.querySelectorAll('.subscription-checkbox:checked');
                selectedCount.textContent = selectedCheckboxes.length;
            }
            
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    subscriptionCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                    if (selectAllTableCheckbox) {
                        selectAllTableCheckbox.checked = this.checked;
                    }
                    updateSelectedCount();
                });
            }
            
            if (selectAllTableCheckbox) {
                selectAllTableCheckbox.addEventListener('change', function() {
                    subscriptionCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                    if (selectAllCheckbox) {
                        selectAllCheckbox.checked = this.checked;
                    }
                    updateSelectedCount();
                });
            }
            
            subscriptionCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectedCount);
            });
            
            // Auto-hide messages
            const messages = document.querySelectorAll('.bg-green-100, .bg-red-100');
            messages.forEach(message => {
                setTimeout(() => {
                    message.style.opacity = '0';
                    setTimeout(() => {
                        message.remove();
                    }, 300);
                }, 5000);
            });
        });
        
        // Global functions
        function toggleMobileMenu() {
            const sidenav = document.getElementById('sidenav');
            const overlay = document.getElementById('overlay');
            
            sidenav.classList.toggle('open');
            overlay.classList.toggle('active');
            document.body.classList.toggle('overflow-hidden');
        }
        
        // Subscription management functions
        function approveSubscription(subscriptionId) {
            if (confirm('Êtes-vous sûr de vouloir approuver cet abonnement ?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="approve_subscription">
                    <input type="hidden" name="subscription_id" value="${subscriptionId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function rejectSubscription(subscriptionId) {
            document.getElementById('rejectSubscriptionId').value = subscriptionId;
            document.getElementById('rejectModal').classList.remove('hidden');
        }
        
        function closeRejectModal() {
            document.getElementById('rejectModal').classList.add('hidden');
            document.getElementById('rejectForm').reset();
        }
        
        function extendSubscription(subscriptionId) {
            document.getElementById('extendSubscriptionId').value = subscriptionId;
            document.getElementById('extendModal').classList.remove('hidden');
        }
        
        function closeExtendModal() {
            document.getElementById('extendModal').classList.add('hidden');
            document.getElementById('extendForm').reset();
        }
        
        function cancelSubscription(subscriptionId) {
            if (confirm('Êtes-vous sûr de vouloir annuler cet abonnement ?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="cancel_subscription">
                    <input type="hidden" name="subscription_id" value="${subscriptionId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function viewPaymentProof(proofPath) {
            document.getElementById('proofImage').src = '../' + proofPath;
            document.getElementById('proofModal').classList.remove('hidden');
        }
        
        function closeProofModal() {
            document.getElementById('proofModal').classList.add('hidden');
        }
        
        function viewSubscriptionDetails(subscriptionId) {
            // For now, show a simple modal with basic info
            // In a real implementation, you'd fetch details via AJAX
            const detailsContent = `
                <div class="space-y-4">
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <h4 class="font-medium mb-2">Informations générales</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">ID de l'abonnement: ${subscriptionId}</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                            <em>Les détails complets seraient chargés via une API en production.</em>
                        </p>
                    </div>
                </div>
            `;
            
            document.getElementById('subscriptionDetails').innerHTML = detailsContent;
            document.getElementById('detailsModal').classList.remove('hidden');
        }
        
        function closeDetailsModal() {
            document.getElementById('detailsModal').classList.add('hidden');
        }
        
        // Bulk actions
        function executeBulkAction() {
            const bulkAction = document.getElementById('bulkAction').value;
            const selectedCheckboxes = document.querySelectorAll('.subscription-checkbox:checked');
            
            if (!bulkAction) {
                showNotification('Veuillez sélectionner une action', 'warning');
                return;
            }
            
            if (selectedCheckboxes.length === 0) {
                showNotification('Veuillez sélectionner au moins un abonnement', 'warning');
                return;
            }
            
            const actionText = {
                'approve': 'approuver',
                'reject': 'rejeter',
                'cancel': 'annuler'
            };
            
            if (confirm(`Êtes-vous sûr de vouloir ${actionText[bulkAction]} ${selectedCheckboxes.length} abonnement(s) ?`)) {
                const form = document.getElementById('bulkForm');
                form.submit();
            }
        }
        
        // Export functionality
        function exportSubscriptions() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            
            const link = document.createElement('a');
            link.href = 'export_subscriptions.php?' + params.toString();
            link.download = 'abonnements_' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            showNotification('Export en cours...', 'info');
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
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.classList.remove('translate-x-full');
                notification.classList.add('translate-x-0');
            }, 100);
            
            setTimeout(() => {
                notification.classList.remove('translate-x-0');
                notification.classList.add('translate-x-full');
                setTimeout(() => {
                    if (document.body.contains(notification)) {
                        document.body.removeChild(notification);
                    }
                }, 300);
            }, 5000);
        }
        
        // Statistics functions
        function showStatistics() {
            const stats = {
                total: <?php echo $stats['total_subscriptions']; ?>,
                active: <?php echo $stats['active_subscriptions']; ?>,
                pending: <?php echo $stats['pending_subscriptions']; ?>,
                expired: <?php echo $stats['expired_subscriptions']; ?>,
                rejected: <?php echo $stats['rejected_subscriptions']; ?>,
                cancelled: <?php echo $stats['cancelled_subscriptions']; ?>,
                total_revenue: <?php echo $stats['total_revenue']; ?>,
                monthly_revenue: <?php echo $stats['monthly_revenue']; ?>
            };
            
            const message = `Statistiques des abonnements:
            
Total: ${stats.total.toLocaleString()}
Actifs: ${stats.active.toLocaleString()}
En attente: ${stats.pending.toLocaleString()}
Expirés: ${stats.expired.toLocaleString()}
Rejetés: ${stats.rejected.toLocaleString()}
Annulés: ${stats.cancelled.toLocaleString()}

Revenus totaux: ${stats.total_revenue.toLocaleString()} F
Revenus ce mois: ${stats.monthly_revenue.toLocaleString()} F`;
            
            alert(message);
        }
        
        // Advanced filtering
        function applyAdvancedFilters() {
            // This could open a modal with more filter options
            showNotification('Filtres avancés en cours de développement', 'info');
        }
        
        // Real-time updates simulation
        function startRealTimeUpdates() {
            setInterval(() => {
                // In a real app, this would check for new subscriptions
                const random = Math.random();
                if (random > 0.98) {
                    showNotification('Nouveau abonnement reçu', 'success');
                }
            }, 30000);
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Escape to close modals
            if (e.key === 'Escape') {
                closeRejectModal();
                closeExtendModal();
                closeProofModal();
                closeDetailsModal();
                
                const sidenav = document.getElementById('sidenav');
                const overlay = document.getElementById('overlay');
                
                if (sidenav.classList.contains('open')) {
                    sidenav.classList.remove('open');
                    overlay.classList.remove('active');
                    document.body.classList.remove('overflow-hidden');
                }
            }
            
            // Ctrl/Cmd + E for export
            if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
                e.preventDefault();
                exportSubscriptions();
            }
            
            // Ctrl/Cmd + S for statistics
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                showStatistics();
            }
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
            const sidenav = document.getElementById('sidenav');
            const content = document.getElementById('content');
            const overlay = document.getElementById('overlay');
            
            if (window.innerWidth >= 768) {
                sidenav.classList.remove('open');
                overlay.classList.remove('active');
                document.body.classList.remove('overflow-hidden');
                
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
        
        // Initialize real-time updates
        startRealTimeUpdates();
        
        // Auto-refresh page every 5 minutes to check for new subscriptions
        setInterval(() => {
            if (document.visibilityState === 'visible') {
                window.location.reload();
            }
        }, 300000);
        
        console.log('Subscriptions Manager initialized successfully');
    </script>
</body>
</html>