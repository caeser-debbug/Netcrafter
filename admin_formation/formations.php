<?php
// admin/formations.php
session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/db.php';

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
        case 'create_formation':
            createFormation();
            break;
        case 'update_formation':
            updateFormation();
            break;
        case 'delete_formation':
            deleteFormation();
            break;
        case 'toggle_status':
            toggleFormationStatus();
            break;
        case 'toggle_featured':
            toggleFeatured();
            break;
    }
}

function createFormation() {
    global $conn, $success_message, $error_message;
    
    $title = trim($_POST['title']);
    $category_id = intval($_POST['category_id']);
    $short_description = trim($_POST['short_description']);
    $description = trim($_POST['description']);
    $level = $_POST['level'];
    $duration = trim($_POST['duration']);
    $price_per_month = floatval($_POST['price_per_month']);
    $instructor_name = trim($_POST['instructor_name']);
    $requirements = trim($_POST['requirements']);
    $objectives = trim($_POST['objectives']);
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    
    // Validation
    if (empty($title) || $category_id <= 0 || empty($description) || empty($level) || $price_per_month < 0) {
        $error_message = "Veuillez remplir tous les champs obligatoires.";
        return;
    }
    
    // Gestion de l'upload d'image
    $cover_image = null;
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/formations/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $filename = 'formation_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $filepath)) {
                $cover_image = 'uploads/formations/' . $filename;
            }
        }
    }
    
    $insert_query = "INSERT INTO formations (category_id, title, short_description, description, level, duration, price_per_month, cover_image, instructor_name, requirements, objectives, is_featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("isssssdssssi", $category_id, $title, $short_description, $description, $level, $duration, $price_per_month, $cover_image, $instructor_name, $requirements, $objectives, $is_featured);
    
    if ($stmt->execute()) {
        $formation_id = $conn->insert_id;
        $success_message = "Formation créée avec succès.";
        
        // Log de l'activité
        $log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'create_formation', ?)";
        $log_stmt = $conn->prepare($log_query);
        $description = "Création de la formation: $title";
        $log_stmt->bind_param("is", $_SESSION['admin_id'], $description);
        $log_stmt->execute();
    } else {
        $error_message = "Erreur lors de la création de la formation.";
    }
}

function updateFormation() {
    global $conn, $success_message, $error_message;
    
    $formation_id = intval($_POST['formation_id']);
    $title = trim($_POST['title']);
    $category_id = intval($_POST['category_id']);
    $short_description = trim($_POST['short_description']);
    $description = trim($_POST['description']);
    $level = $_POST['level'];
    $duration = trim($_POST['duration']);
    $price_per_month = floatval($_POST['price_per_month']);
    $instructor_name = trim($_POST['instructor_name']);
    $requirements = trim($_POST['requirements']);
    $objectives = trim($_POST['objectives']);
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    
    // Gestion de l'upload d'image
    $cover_image_update = "";
    $cover_image_param = null;
    
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/formations/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $filename = 'formation_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $filepath)) {
                $cover_image_update = ", cover_image = ?";
                $cover_image_param = 'uploads/formations/' . $filename;
            }
        }
    }
    
    $update_query = "UPDATE formations SET category_id = ?, title = ?, short_description = ?, description = ?, level = ?, duration = ?, price_per_month = ?, instructor_name = ?, requirements = ?, objectives = ?, is_featured = ?, updated_at = NOW() $cover_image_update WHERE id = ?";
    
    $stmt = $conn->prepare($update_query);
    
    if ($cover_image_param) {
        $stmt->bind_param("isssssdsssisi", $category_id, $title, $short_description, $description, $level, $duration, $price_per_month, $instructor_name, $requirements, $objectives, $is_featured, $cover_image_param, $formation_id);
    } else {
        $stmt->bind_param("isssssdsssii", $category_id, $title, $short_description, $description, $level, $duration, $price_per_month, $instructor_name, $requirements, $objectives, $is_featured, $formation_id);
    }
    
    if ($stmt->execute()) {
        $success_message = "Formation mise à jour avec succès.";
        
        // Log de l'activité
        $log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'update_formation', ?)";
        $log_stmt = $conn->prepare($log_query);
        $description = "Modification de la formation ID: $formation_id";
        $log_stmt->bind_param("is", $_SESSION['admin_id'], $description);
        $log_stmt->execute();
    } else {
        $error_message = "Erreur lors de la mise à jour.";
    }
}

function deleteFormation() {
    global $conn, $success_message, $error_message;
    
    $formation_id = intval($_POST['formation_id']);
    
    // Vérifier s'il y a des abonnements actifs
    $check_query = "SELECT COUNT(*) as active_subs FROM formation_subscriptions WHERE formation_id = ? AND status = 'active' AND end_date >= CURDATE()";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $formation_id);
    $check_stmt->execute();
    $active_subs = $check_stmt->get_result()->fetch_assoc()['active_subs'];
    
    if ($active_subs > 0) {
        $error_message = "Impossible de supprimer cette formation car elle a des abonnements actifs.";
        return;
    }
    
    $delete_query = "DELETE FROM formations WHERE id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("i", $formation_id);
    
    if ($stmt->execute()) {
        $success_message = "Formation supprimée avec succès.";
        
        // Log de l'activité
        $log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'delete_formation', ?)";
        $log_stmt = $conn->prepare($log_query);
        $description = "Suppression de la formation ID: $formation_id";
        $log_stmt->bind_param("is", $_SESSION['admin_id'], $description);
        $log_stmt->execute();
    } else {
        $error_message = "Erreur lors de la suppression.";
    }
}

function toggleFormationStatus() {
    global $conn, $success_message, $error_message;
    
    $formation_id = intval($_POST['formation_id']);
    $current_status = $_POST['current_status'];
    $new_status = ($current_status === 'active') ? 'inactive' : 'active';
    
    $update_query = "UPDATE formations SET status = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("si", $new_status, $formation_id);
    
    if ($stmt->execute()) {
        $success_message = "Statut de la formation mis à jour.";
    } else {
        $error_message = "Erreur lors de la mise à jour du statut.";
    }
}

function toggleFeatured() {
    global $conn, $success_message, $error_message;
    
    $formation_id = intval($_POST['formation_id']);
    $current_featured = intval($_POST['current_featured']);
    $new_featured = $current_featured ? 0 : 1;
    
    $update_query = "UPDATE formations SET is_featured = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ii", $new_featured, $formation_id);
    
    if ($stmt->execute()) {
        $success_message = "Statut de mise en avant mis à jour.";
    } else {
        $error_message = "Erreur lors de la mise à jour.";
    }
}

// Paramètres de recherche et filtrage
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$order = isset($_GET['order']) ? $_GET['order'] : 'DESC';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Construction de la requête
$where_conditions = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where_conditions[] = "(f.title LIKE CONCAT('%', ?, '%') OR f.description LIKE CONCAT('%', ?, '%') OR f.instructor_name LIKE CONCAT('%', ?, '%'))";
    $params = array_merge($params, [$search, $search, $search]);
    $types .= "sss";
}

if ($category_filter > 0) {
    $where_conditions[] = "f.category_id = ?";
    $params[] = $category_filter;
    $types .= "i";
}

if ($status_filter !== 'all') {
    $where_conditions[] = "f.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Compter le total de formations
$count_query = "SELECT COUNT(*) as total FROM formations f $where_clause";
$count_stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_formations = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_formations / $per_page);

// Récupérer les formations
$formations_query = "SELECT f.*, c.name as category_name, c.icon as category_icon,
                     COUNT(DISTINCT fs.id) as total_subscriptions,
                     COUNT(DISTINCT CASE WHEN fs.status = 'active' AND fs.end_date >= CURDATE() THEN fs.id END) as active_subscriptions,
                     COUNT(DISTINCT fm.id) as total_modules,
                     COUNT(DISTINCT fv.id) as total_videos
                     FROM formations f
                     LEFT JOIN formation_categories c ON f.category_id = c.id
                     LEFT JOIN formation_subscriptions fs ON f.id = fs.formation_id
                     LEFT JOIN formation_modules fm ON f.id = fm.formation_id
                     LEFT JOIN formation_videos fv ON fm.id = fv.module_id
                     $where_clause
                     GROUP BY f.id
                     ORDER BY f.$sort_by $order
                     LIMIT ?, ?";

$params[] = $offset;
$params[] = $per_page;
$types .= "ii";

$formations_stmt = $conn->prepare($formations_query);
$formations_stmt->bind_param($types, ...$params);
$formations_stmt->execute();
$formations_result = $formations_stmt->get_result();

$formations = [];
while ($row = $formations_result->fetch_assoc()) {
    $formations[] = $row;
}

// Récupérer les catégories pour les filtres
$categories_query = "SELECT * FROM formation_categories WHERE status = 'active' ORDER BY name";
$categories_result = $conn->query($categories_query);
$categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $categories[] = $row;
}

// Statistiques générales
$stats_query = "SELECT 
    COUNT(*) as total_formations,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_formations,
    SUM(CASE WHEN is_featured = 1 THEN 1 ELSE 0 END) as featured_formations,
    AVG(price_per_month) as avg_price
    FROM formations";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr" class="light scroll">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Formations - Admin Netcrafter</title>
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

        /* Formation card hover effects */
        .formation-card {
            transition: all 0.3s ease;
        }
        
        .formation-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        /* Modal styles */
        .modal {
            backdrop-filter: blur(4px);
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
                    <a href="formations.php" class="flex items-center px-3 py-2 text-base rounded-lg bg-netblue-100 dark:bg-netblue-900/30 text-netblue-800 dark:text-netblue-300">
                        <i class="fas fa-graduation-cap w-6 text-center"></i>
                        <span class="ml-2 nav-text">Formations</span>
                        <span class="ml-auto bg-green-500 text-white text-xs rounded-full px-2 py-1 nav-text"><?php echo $stats['total_formations']; ?></span>
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
                        <span class="ml-2 nav-text">Quiz</span>
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
                <h1 class="text-xl font-bold text-gray-800 dark:text-white">Gestion des Formations</h1>
                
                <!-- Actions -->
                <div class="flex items-center space-x-3">
                    <button onclick="showCreateModal()" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded-lg transition-colors text-sm">
                        <i class="fas fa-plus mr-2"></i>Nouvelle Formation
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
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-blue-100 dark:bg-blue-900 p-3 rounded-lg">
                            <i class="fas fa-graduation-cap text-2xl text-blue-600 dark:text-blue-400"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">Total</h3>
                            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo number_format($stats['total_formations']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-green-100 dark:bg-green-900 p-3 rounded-lg">
                            <i class="fas fa-check-circle text-2xl text-green-600 dark:text-green-400"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">Actives</h3>
                            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo number_format($stats['active_formations']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-yellow-100 dark:bg-yellow-900 p-3 rounded-lg">
                            <i class="fas fa-star text-2xl text-yellow-600 dark:text-yellow-400"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">En vedette</h3>
                            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo number_format($stats['featured_formations']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up" data-aos-delay="300">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-purple-100 dark:bg-purple-900 p-3 rounded-lg">
                            <i class="fas fa-coins text-2xl text-purple-600 dark:text-purple-400"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">Prix moyen</h3>
                            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo number_format($stats['avg_price']); ?> F</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filters and Search -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 mb-8" data-aos="fade-up">
                <form method="GET" action="formations.php" class="flex flex-col md:flex-row gap-4">
                    <!-- Search -->
                    <div class="flex-1">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Rechercher une formation..." class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                    </div>
                    
                    <!-- Category Filter -->
                    <div>
                        <select name="category" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                            <option value="0">Toutes les catégories</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Status Filter -->
                    <div>
                        <select name="status" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>Tous les statuts</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Actives</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactives</option>
                        </select>
                    </div>
                    
                    <!-- Sort -->
                    <div>
                        <select name="sort" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                            <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Date de création</option>
                            <option value="title" <?php echo $sort_by === 'title' ? 'selected' : ''; ?>>Titre</option>
                            <option value="price_per_month" <?php echo $sort_by === 'price_per_month' ? 'selected' : ''; ?>>Prix</option>
                        </select>
                    </div>
                    
                    <!-- Submit -->
                    <button type="submit" class="bg-netblue-600 hover:bg-netblue-700 text-white px-6 py-2 rounded-lg transition-colors">
                        <i class="fas fa-search mr-2"></i>Filtrer
                    </button>
                    
                    <!-- Reset -->
                    <a href="formations.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition-colors text-center">
                        <i class="fas fa-refresh mr-2"></i>Reset
                    </a>
                </form>
            </div>
            
            <!-- Formations Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8" data-aos="fade-up">
                <?php if (empty($formations)): ?>
                <div class="col-span-full bg-white dark:bg-gray-800 rounded-xl shadow-lg p-12 text-center">
                    <i class="fas fa-graduation-cap text-6xl text-gray-400 mb-4"></i>
                    <h3 class="text-xl font-bold mb-2 dark:text-white">Aucune formation trouvée</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-6">Créez votre première formation ou modifiez vos critères de recherche.</p>
                    <button onclick="showCreateModal()" class="bg-netblue-600 hover:bg-netblue-700 text-white px-6 py-3 rounded-lg transition-colors">
                        <i class="fas fa-plus mr-2"></i>Créer une formation
                    </button>
                </div>
                <?php else: ?>
                <?php foreach ($formations as $formation): ?>
                <div class="formation-card bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
                    <!-- Formation Image -->
                    <div class="relative h-48 bg-gray-200 dark:bg-gray-700">
                        <?php if (!empty($formation['cover_image'])): ?>
                        <img src="../<?php echo htmlspecialchars($formation['cover_image']); ?>" alt="<?php echo htmlspecialchars($formation['title']); ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-netblue-400 to-netblue-600">
                            <i class="fas fa-graduation-cap text-4xl text-white"></i>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Status Badge -->
                        <div class="absolute top-3 left-3">
                            <span class="px-2 py-1 text-xs font-bold rounded-full <?php echo $formation['status'] === 'active' ? 'bg-green-500 text-white' : 'bg-gray-500 text-white'; ?>">
                                <?php echo ucfirst($formation['status']); ?>
                            </span>
                        </div>
                        
                        <!-- Featured Badge -->
                        <?php if ($formation['is_featured']): ?>
                        <div class="absolute top-3 right-3">
                            <span class="px-2 py-1 text-xs font-bold rounded-full bg-yellow-500 text-white">
                                <i class="fas fa-star mr-1"></i>Vedette
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Category Badge -->
                        <div class="absolute bottom-3 left-3">
                            <span class="px-2 py-1 text-xs font-bold rounded-full bg-netblue-600 text-white">
                                <i class="fas <?php echo htmlspecialchars($formation['category_icon']); ?> mr-1"></i>
                                <?php echo htmlspecialchars($formation['category_name']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Formation Details -->
                    <div class="p-6">
                        <h3 class="text-lg font-bold mb-2 dark:text-white line-clamp-2">
                            <?php echo htmlspecialchars($formation['title']); ?>
                        </h3>
                        
                        <p class="text-gray-600 dark:text-gray-400 text-sm mb-4 line-clamp-2">
                            <?php echo htmlspecialchars($formation['short_description'] ?? substr($formation['description'], 0, 100) . '...'); ?>
                        </p>
                        
                        <!-- Stats -->
                        <div class="grid grid-cols-2 gap-4 mb-4 text-sm">
                            <div class="flex items-center">
                                <i class="fas fa-users text-blue-500 mr-2"></i>
                                <span class="text-gray-600 dark:text-gray-400"><?php echo $formation['total_subscriptions']; ?> abonnés</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-play-circle text-green-500 mr-2"></i>
                                <span class="text-gray-600 dark:text-gray-400"><?php echo $formation['total_videos']; ?> vidéos</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-layer-group text-purple-500 mr-2"></i>
                                <span class="text-gray-600 dark:text-gray-400"><?php echo $formation['total_modules']; ?> modules</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-coins text-yellow-500 mr-2"></i>
                                <span class="text-gray-600 dark:text-gray-400"><?php echo number_format($formation['price_per_month']); ?> F</span>
                            </div>
                        </div>
                        
                        <!-- Level -->
                        <div class="mb-4">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full
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
                        
                        <!-- Actions -->
                        <div class="flex items-center space-x-2">
                            <button onclick="editFormation(<?php echo $formation['id']; ?>)" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded text-sm font-medium transition-colors">
                                <i class="fas fa-edit mr-1"></i>Modifier
                            </button>
                            
                            <button onclick="manageContent(<?php echo $formation['id']; ?>)" class="flex-1 bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded text-sm font-medium transition-colors">
                                <i class="fas fa-play mr-1"></i>Contenu
                            </button>
                            
                            <div class="flex space-x-1">
                                <button onclick="toggleStatus(<?php echo $formation['id']; ?>, '<?php echo $formation['status']; ?>')" class="text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200 p-2" title="Changer le statut">
                                    <i class="fas <?php echo $formation['status'] === 'active' ? 'fa-eye-slash' : 'fa-eye'; ?>"></i>
                                </button>
                                
                                <button onclick="toggleFeatured(<?php echo $formation['id']; ?>, <?php echo $formation['is_featured']; ?>)" class="text-yellow-600 hover:text-yellow-800 dark:text-yellow-400 dark:hover:text-yellow-200 p-2" title="Mettre en vedette">
                                    <i class="fas <?php echo $formation['is_featured'] ? 'fa-star' : 'fa-star-o'; ?>"></i>
                                </button>
                                
                                <?php if ($formation['active_subscriptions'] == 0): ?>
                                <button onclick="deleteFormation(<?php echo $formation['id']; ?>, '<?php echo htmlspecialchars($formation['title']); ?>')" class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-200 p-2" title="Supprimer">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="flex items-center justify-between bg-white dark:bg-gray-800 px-6 py-3 rounded-xl shadow-lg">
                <div class="flex-1 flex justify-between sm:hidden">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $order; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Précédent
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $order; ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Suivant
                    </a>
                    <?php endif; ?>
                </div>
                
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            Affichage de <span class="font-medium"><?php echo ($page-1) * $per_page + 1; ?></span> à <span class="font-medium"><?php echo min($page * $per_page, $total_formations); ?></span> sur <span class="font-medium"><?php echo $total_formations; ?></span> résultats
                        </p>
                    </div>
                    
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                            <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $order; ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $order; ?>" class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?php echo $i == $page ? 'z-10 bg-netblue-50 border-netblue-500 text-netblue-600' : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'; ?>">
                                <?php echo $i; ?>
                            </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $order; ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
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
    
    <!-- Create/Edit Formation Modal -->
    <div id="formationModal" class="fixed inset-0 z-50 hidden">
        <div class="modal flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeFormationModal()"></div>
            
            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full max-h-screen overflow-y-auto">
                <form id="formationForm" method="POST" enctype="multipart/form-data">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modal-title">
                                    Créer une formation
                                </h3>
                                
                                <div class="mt-6 space-y-6">
                                    <input type="hidden" name="action" id="formAction" value="create_formation">
                                    <input type="hidden" name="formation_id" id="formationId" value="">
                                    
                                    <!-- Title -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Titre de la formation *
                                        </label>
                                        <input type="text" name="title" id="formTitle" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                                    </div>
                                    
                                    <!-- Category and Level -->
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                Catégorie *
                                            </label>
                                            <select name="category_id" id="formCategory" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                                                <option value="">Sélectionner...</option>
                                                <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                Niveau *
                                            </label>
                                            <select name="level" id="formLevel" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                                                <option value="">Sélectionner...</option>
                                                <option value="debutant">Débutant</option>
                                                <option value="intermediaire">Intermédiaire</option>
                                                <option value="avance">Avancé</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <!-- Short Description -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Description courte
                                        </label>
                                        <textarea name="short_description" id="formShortDescription" rows="2" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500" placeholder="Description courte pour les cartes de formation"></textarea>
                                    </div>
                                    
                                    <!-- Description -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Description complète *
                                        </label>
                                        <textarea name="description" id="formDescription" rows="4" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500" placeholder="Description détaillée de la formation"></textarea>
                                    </div>
                                    
                                    <!-- Duration and Price -->
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                Durée estimée
                                            </label>
                                            <input type="text" name="duration" id="formDuration" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500" placeholder="Ex: 20 heures">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                Prix par mois (FCFA) *
                                            </label>
                                            <input type="number" name="price_per_month" id="formPrice" min="0" step="1000" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                                        </div>
                                    </div>
                                    
                                    <!-- Instructor -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Nom du formateur
                                        </label>
                                        <input type="text" name="instructor_name" id="formInstructor" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                                    </div>
                                    
                                    <!-- Requirements -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Prérequis
                                        </label>
                                        <textarea name="requirements" id="formRequirements" rows="3" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500" placeholder="Liste des prérequis nécessaires"></textarea>
                                    </div>
                                    
                                    <!-- Objectives -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Objectifs pédagogiques
                                        </label>
                                        <textarea name="objectives" id="formObjectives" rows="3" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500" placeholder="Ce que l'apprenant va acquérir"></textarea>
                                    </div>
                                    
                                    <!-- Cover Image -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Image de couverture
                                        </label>
                                        <input type="file" name="cover_image" id="formCoverImage" accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-netblue-50 file:text-netblue-700 hover:file:bg-netblue-100">
                                        <p class="text-xs text-gray-500 mt-1">JPG, PNG ou WebP. Max 5MB.</p>
                                    </div>
                                    
                                    <!-- Featured -->
                                    <div>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="is_featured" id="formFeatured" class="rounded border-gray-300 text-netblue-600 shadow-sm focus:border-netblue-300 focus:ring focus:ring-netblue-200 focus:ring-opacity-50">
                                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Formation en vedette</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-netblue-600 text-base font-medium text-white hover:bg-netblue-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                            <i class="fas fa-save mr-2"></i>Sauvegarder
                        </button>
                        <button type="button" onclick="closeFormationModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Annuler
                        </button>
                    </div>
                </form>
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
            
            // Form submission
            document.getElementById('formationForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sauvegarde...';
                submitBtn.disabled = true;
                
                // Submit the form
                this.submit();
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
        
        // Formation management functions
        function showCreateModal() {
            document.getElementById('modal-title').textContent = 'Créer une formation';
            document.getElementById('formAction').value = 'create_formation';
            document.getElementById('formationId').value = '';
            document.getElementById('formationForm').reset();
            document.getElementById('formationModal').classList.remove('hidden');
        }
        
        function editFormation(formationId) {
            // Fetch formation data and populate form
            fetch(`api/formations.php?action=get&id=${formationId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        populateFormationForm(data.formation);
                        document.getElementById('modal-title').textContent = 'Modifier la formation';
                        document.getElementById('formAction').value = 'update_formation';
                        document.getElementById('formationId').value = formationId;
                        document.getElementById('formationModal').classList.remove('hidden');
                    } else {
                        showNotification('Erreur lors du chargement des données', 'error');
                    }
                })
                .catch(error => {
                    showNotification('Erreur de connexion', 'error');
                });
        }
        
        function populateFormationForm(formation) {
            document.getElementById('formTitle').value = formation.title;
            document.getElementById('formCategory').value = formation.category_id;
            document.getElementById('formLevel').value = formation.level;
            document.getElementById('formShortDescription').value = formation.short_description || '';
            document.getElementById('formDescription').value = formation.description;
            document.getElementById('formDuration').value = formation.duration || '';
            document.getElementById('formPrice').value = formation.price_per_month;
            document.getElementById('formInstructor').value = formation.instructor_name || '';
            document.getElementById('formRequirements').value = formation.requirements || '';
            document.getElementById('formObjectives').value = formation.objectives || '';
            document.getElementById('formFeatured').checked = formation.is_featured == 1;
        }
        
        function closeFormationModal() {
            document.getElementById('formationModal').classList.add('hidden');
            document.getElementById('formationForm').reset();
        }
        
        function toggleStatus(formationId, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            const action = newStatus === 'active' ? 'activer' : 'désactiver';
            
            if (confirm(`Êtes-vous sûr de vouloir ${action} cette formation ?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="formation_id" value="${formationId}">
                    <input type="hidden" name="current_status" value="${currentStatus}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function toggleFeatured(formationId, currentFeatured) {
            const action = currentFeatured ? 'retirer de la vedette' : 'mettre en vedette';
            
            if (confirm(`Êtes-vous sûr de vouloir ${action} cette formation ?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="toggle_featured">
                    <input type="hidden" name="formation_id" value="${formationId}">
                    <input type="hidden" name="current_featured" value="${currentFeatured}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function deleteFormation(formationId, formationTitle) {
            if (confirm(`Êtes-vous sûr de vouloir supprimer définitivement la formation "${formationTitle}" ?\n\nCette action est irréversible !`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_formation">
                    <input type="hidden" name="formation_id" value="${formationId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function manageContent(formationId) {
            window.location.href = `formation-content.php?id=${formationId}`;
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
            }, 3000);
        }
        
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
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeFormationModal();
                
                const sidenav = document.getElementById('sidenav');
                const overlay = document.getElementById('overlay');
                
                if (sidenav.classList.contains('open')) {
                    sidenav.classList.remove('open');
                    overlay.classList.remove('active');
                    document.body.classList.remove('overflow-hidden');
                }
            }
            
            // Ctrl/Cmd + N for new formation
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                showCreateModal();
            }
        });
    </script>
</body>
</html>