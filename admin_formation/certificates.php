<?php
// admin/certificates.php
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
        case 'generate_certificate':
            generateCertificate();
            break;
        case 'revoke_certificate':
            revokeCertificate();
            break;
        case 'update_certificate':
            updateCertificate();
            break;
        case 'bulk_action':
            bulkAction();
            break;
    }
    
    // Redirection pour éviter la re-soumission
    header("Location: certificates.php" . 
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

function generateCertificate() {
    global $conn, $success_message, $error_message;
    
    $user_id = intval($_POST['user_id']);
    $formation_id = intval($_POST['formation_id']);
    $quiz_attempt_id = intval($_POST['quiz_attempt_id'] ?? 0);
    
    // Vérifier si un certificat n'existe pas déjà
    $check_query = "SELECT id FROM certificates WHERE user_id = ? AND formation_id = ? AND verified = 1";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $user_id, $formation_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        $error_message = "Un certificat valide existe déjà pour cet utilisateur et cette formation.";
        return;
    }
    
    // Générer le numéro de certificat unique
    $certificate_number = 'NC-' . date('Y') . '-' . str_pad($formation_id, 3, '0', STR_PAD_LEFT) . '-' . str_pad($user_id, 5, '0', STR_PAD_LEFT) . '-' . strtoupper(substr(md5(time() . $user_id . $formation_id), 0, 4));
    
    // Code de vérification
    $verification_code = strtoupper(substr(md5($certificate_number . time()), 0, 8));
    
    // URL du certificat (sera générée)
    $certificate_url = "certificates/generate.php?code=" . $verification_code;
    
    $insert_query = "INSERT INTO certificates (user_id, formation_id, quiz_attempt_id, certificate_number, certificate_url, verification_code, verified) VALUES (?, ?, ?, ?, ?, ?, 1)";
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("iiisss", $user_id, $formation_id, $quiz_attempt_id, $certificate_number, $certificate_url, $verification_code);
    
    if ($stmt->execute()) {
        $success_message = "Certificat généré avec succès.";
        
        // Log de l'activité
        $log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'generate_certificate', ?)";
        $log_stmt = $conn->prepare($log_query);
        $description = "Génération du certificat: $certificate_number";
        $log_stmt->bind_param("is", $_SESSION['admin_id'], $description);
        $log_stmt->execute();
    } else {
        $error_message = "Erreur lors de la génération du certificat.";
    }
}

function revokeCertificate() {
    global $conn, $success_message, $error_message;
    
    $certificate_id = intval($_POST['certificate_id']);
    $reason = trim($_POST['reason'] ?? '');
    
    $update_query = "UPDATE certificates SET verified = 0, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("i", $certificate_id);
    
    if ($stmt->execute()) {
        $success_message = "Certificat révoqué avec succès.";
        
        // Log de l'activité
        $log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'revoke_certificate', ?)";
        $log_stmt = $conn->prepare($log_query);
        $description = "Révocation du certificat ID: $certificate_id" . ($reason ? " - Raison: $reason" : "");
        $log_stmt->bind_param("is", $_SESSION['admin_id'], $description);
        $log_stmt->execute();
    } else {
        $error_message = "Erreur lors de la révocation du certificat.";
    }
}

function updateCertificate() {
    global $conn, $success_message, $error_message;
    
    $certificate_id = intval($_POST['certificate_id']);
    $verified = isset($_POST['verified']) ? 1 : 0;
    
    $update_query = "UPDATE certificates SET verified = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ii", $verified, $certificate_id);
    
    if ($stmt->execute()) {
        $success_message = "Certificat mis à jour avec succès.";
    } else {
        $error_message = "Erreur lors de la mise à jour du certificat.";
    }
}

function bulkAction() {
    global $conn, $success_message, $error_message;
    
    $bulk_action = $_POST['bulk_action'];
    $selected_certificates = $_POST['selected_certificates'] ?? [];
    
    if (empty($selected_certificates)) {
        $error_message = "Veuillez sélectionner au moins un certificat.";
        return;
    }
    
    $count = 0;
    foreach ($selected_certificates as $certificate_id) {
        $certificate_id = intval($certificate_id);
        
        switch ($bulk_action) {
            case 'verify':
                $update_query = "UPDATE certificates SET verified = 1, updated_at = NOW() WHERE id = ?";
                break;
            case 'revoke':
                $update_query = "UPDATE certificates SET verified = 0, updated_at = NOW() WHERE id = ?";
                break;
            default:
                continue 2;
        }
        
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("i", $certificate_id);
        if ($stmt->execute()) {
            $count++;
        }
    }
    
    if ($count > 0) {
        $success_message = "$count certificat(s) traité(s) avec succès.";
    }
}

// Paramètres de recherche et filtrage
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$formation_filter = isset($_GET['formation']) ? intval($_GET['formation']) : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'issue_date';
$order = isset($_GET['order']) ? $_GET['order'] : 'DESC';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Construction de la requête
$where_conditions = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where_conditions[] = "(u.firstname LIKE CONCAT('%', ?, '%') OR u.lastname LIKE CONCAT('%', ?, '%') OR c.certificate_number LIKE CONCAT('%', ?, '%') OR f.title LIKE CONCAT('%', ?, '%'))";
    $params = array_merge($params, [$search, $search, $search, $search]);
    $types .= "ssss";
}

if ($formation_filter > 0) {
    $where_conditions[] = "c.formation_id = ?";
    $params[] = $formation_filter;
    $types .= "i";
}

if ($status_filter !== 'all') {
    if ($status_filter === 'verified') {
        $where_conditions[] = "c.verified = 1";
    } else {
        $where_conditions[] = "c.verified = 0";
    }
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Compter le total de certificats
$count_query = "SELECT COUNT(*) as total FROM certificates c
                JOIN users u ON c.user_id = u.id
                JOIN formations f ON c.formation_id = f.id
                $where_clause";
$count_stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_certificates = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_certificates / $per_page);

// Récupérer les certificats
$certificates_query = "SELECT c.*, 
                       u.firstname, u.lastname, u.phone, u.email,
                       f.title as formation_title,
                       fc.name as category_name,
                       qa.score as quiz_score
                       FROM certificates c
                       JOIN users u ON c.user_id = u.id
                       JOIN formations f ON c.formation_id = f.id
                       LEFT JOIN formation_categories fc ON f.category_id = fc.id
                       LEFT JOIN quiz_attempts qa ON c.quiz_attempt_id = qa.id
                       $where_clause
                       ORDER BY c.$sort_by $order
                       LIMIT ?, ?";

$params[] = $offset;
$params[] = $per_page;
$types .= "ii";

$certificates_stmt = $conn->prepare($certificates_query);
$certificates_stmt->bind_param($types, ...$params);
$certificates_stmt->execute();
$certificates_result = $certificates_stmt->get_result();

$certificates = [];
while ($row = $certificates_result->fetch_assoc()) {
    $certificates[] = $row;
}

// Récupérer les formations pour les filtres
$formations_query = "SELECT id, title FROM formations WHERE status = 'active' ORDER BY title";
$formations_result = $conn->query($formations_query);
$formations = [];
while ($row = $formations_result->fetch_assoc()) {
    $formations[] = $row;
}

// Récupérer les utilisateurs éligibles (qui ont terminé des formations)
$eligible_users_query = "SELECT DISTINCT u.id, u.firstname, u.lastname, f.id as formation_id, f.title as formation_title
                        FROM users u
                        JOIN formation_subscriptions fs ON u.id = fs.user_id
                        JOIN formations f ON fs.formation_id = f.id
                        LEFT JOIN certificates c ON u.id = c.user_id AND f.id = c.formation_id AND c.verified = 1
                        WHERE fs.status = 'active' 
                        AND fs.end_date >= CURDATE()
                        AND c.id IS NULL
                        ORDER BY u.firstname, u.lastname, f.title";
$eligible_users = $conn->query($eligible_users_query);

// Statistiques générales
$stats_query = "SELECT 
    COUNT(*) as total_certificates,
    SUM(CASE WHEN verified = 1 THEN 1 ELSE 0 END) as verified_certificates,
    SUM(CASE WHEN verified = 0 THEN 1 ELSE 0 END) as revoked_certificates,
    COUNT(DISTINCT user_id) as unique_users,
    COUNT(DISTINCT formation_id) as formations_with_certificates
    FROM certificates";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr" class="light scroll">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Certificats - Admin Netcrafter</title>
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
        .certificate-row {
            transition: all 0.2s ease;
        }
        
        .certificate-row:hover {
            background-color: #f8fafc;
            transform: translateX(2px);
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
                    <a href="quiz.php" class="flex items-center px-3 py-2 text-base rounded-lg bg-netblue-100 dark:bg-netblue-900/30 text-netblue-800 dark:text-netblue-300">
                        <i class="fas fa-question-circle w-6 text-center"></i>
                        <span class="ml-2 nav-text">Quizz</span>
                        <span class="ml-auto bg-green-500 text-white text-xs rounded-full px-2 py-1 nav-text"><?php echo $stats['total_certificates']; ?></span>
                    </a>
                </li>
                <li>
                    <a href="certificates.php" class="flex items-center px-3 py-2 text-base rounded-lg bg-netblue-100 dark:bg-netblue-900/30 text-netblue-800 dark:text-netblue-300">
                        <i class="fas fa-certificate w-6 text-center"></i>
                        <span class="ml-2 nav-text">Certificats</span>
                        <span class="ml-auto bg-green-500 text-white text-xs rounded-full px-2 py-1 nav-text"><?php echo $stats['total_certificates']; ?></span>
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
                <h1 class="text-xl font-bold text-gray-800 dark:text-white">Gestion des Certificats</h1>
                
                <!-- Actions -->
                <div class="flex items-center space-x-3">
                    <button onclick="showGenerateModal()" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded-lg transition-colors text-sm">
                        <i class="fas fa-plus mr-2"></i>Générer Certificat
                    </button>
                    <button onclick="exportCertificates()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors text-sm">
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
                        <div class="flex-shrink-0 bg-green-100 dark:bg-green-900 p-3 rounded-lg">
                            <i class="fas fa-certificate text-2xl text-green-600 dark:text-green-400"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">Total</h3>
                            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo number_format($stats['total_certificates']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-blue-100 dark:bg-blue-900 p-3 rounded-lg">
                            <i class="fas fa-check-circle text-2xl text-blue-600 dark:text-blue-400"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">Vérifiés</h3>
                            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo number_format($stats['verified_certificates']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-red-100 dark:bg-red-900 p-3 rounded-lg">
                            <i class="fas fa-ban text-2xl text-red-600 dark:text-red-400"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">Révoqués</h3>
                            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo number_format($stats['revoked_certificates']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up" data-aos-delay="300">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-purple-100 dark:bg-purple-900 p-3 rounded-lg">
                            <i class="fas fa-users text-2xl text-purple-600 dark:text-purple-400"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">Utilisateurs</h3>
                            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo number_format($stats['unique_users']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filters and Search -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 mb-8" data-aos="fade-up">
                <form method="GET" action="certificates.php" class="flex flex-col lg:flex-row gap-4">
                    <!-- Search -->
                    <div class="flex-1">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Rechercher par nom, numéro de certificat ou formation..." class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
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
                    
                    <!-- Status Filter -->
                    <div>
                        <select name="status" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>Tous les statuts</option>
                            <option value="verified" <?php echo $status_filter === 'verified' ? 'selected' : ''; ?>>Vérifiés</option>
                            <option value="revoked" <?php echo $status_filter === 'revoked' ? 'selected' : ''; ?>>Révoqués</option>
                        </select>
                    </div>
                    
                    <!-- Sort -->
                    <div>
                        <select name="sort" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                            <option value="issue_date" <?php echo $sort_by === 'issue_date' ? 'selected' : ''; ?>>Date d'émission</option>
                            <option value="certificate_number" <?php echo $sort_by === 'certificate_number' ? 'selected' : ''; ?>>Numéro</option>
                            <option value="lastname" <?php echo $sort_by === 'lastname' ? 'selected' : ''; ?>>Nom utilisateur</option>
                        </select>
                    </div>
                    
                    <!-- Submit -->
                    <button type="submit" class="bg-netblue-600 hover:bg-netblue-700 text-white px-6 py-2 rounded-lg transition-colors">
                        <i class="fas fa-search mr-2"></i>Filtrer
                    </button>
                    
                    <!-- Reset -->
                    <a href="certificates.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition-colors text-center">
                        <i class="fas fa-refresh mr-2"></i>Reset
                    </a>
                </form>
            </div>
            
            <!-- Bulk Actions -->
            <?php if (!empty($certificates)): ?>
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
                                <option value="verify">Vérifier</option>
                                <option value="revoke">Révoquer</option>
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
            
            <!-- Certificates Table -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden" data-aos="fade-up">
                <?php if (empty($certificates)): ?>
                <div class="p-12 text-center">
                    <i class="fas fa-certificate text-6xl text-gray-400 mb-4"></i>
                    <h3 class="text-xl font-bold mb-2 dark:text-white">Aucun certificat trouvé</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-6">Modifiez vos critères de recherche ou générez le premier certificat.</p>
                    <button onclick="showGenerateModal()" class="bg-netblue-600 hover:bg-netblue-700 text-white px-6 py-3 rounded-lg transition-colors">
                        <i class="fas fa-plus mr-2"></i>Générer un certificat
                    </button>
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
                                    Certificat
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Score
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Statut
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Date d'émission
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach ($certificates as $certificate): ?>
                            <tr class="certificate-row">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="checkbox" name="selected_certificates[]" value="<?php echo $certificate['id']; ?>" class="certificate-checkbox rounded border-gray-300 text-netblue-600 shadow-sm">
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-gray-300 dark:bg-gray-600 rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0">
                                            <?php echo strtoupper(substr($certificate['firstname'], 0, 1) . substr($certificate['lastname'], 0, 1)); ?>
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($certificate['firstname'] . ' ' . $certificate['lastname']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                <?php echo htmlspecialchars($certificate['phone']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 dark:text-white font-medium">
                                        <?php echo htmlspecialchars($certificate['formation_title']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        <?php echo htmlspecialchars($certificate['category_name']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-mono text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars($certificate['certificate_number']); ?>
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        Code: <?php echo htmlspecialchars($certificate['verification_code']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($certificate['quiz_score']): ?>
                                    <div class="flex items-center">
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">
                                            <?php echo $certificate['quiz_score']; ?>%
                                        </span>
                                        <div class="ml-2 w-12 bg-gray-200 rounded-full h-2">
                                            <div class="bg-<?php echo $certificate['quiz_score'] >= 70 ? 'green' : 'red'; ?>-500 h-2 rounded-full" style="width: <?php echo $certificate['quiz_score']; ?>%"></div>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-gray-500 dark:text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $certificate['verified'] ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'; ?>">
                                        <?php echo $certificate['verified'] ? 'Vérifié' : 'Révoqué'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <?php echo date('d/m/Y', strtotime($certificate['issue_date'])); ?>
                                    <div class="text-xs">
                                        <?php echo date('H:i', strtotime($certificate['issue_date'])); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center space-x-2">
                                        <button onclick="viewCertificate('<?php echo $certificate['verification_code']; ?>')" class="text-blue-600 hover:text-blue-900 dark:text-blue-400" title="Voir le certificat">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        
                                        <button onclick="downloadCertificate('<?php echo $certificate['verification_code']; ?>')" class="text-green-600 hover:text-green-900 dark:text-green-400" title="Télécharger">
                                            <i class="fas fa-download"></i>
                                        </button>
                                        
                                        <?php if ($certificate['verified']): ?>
                                        <button onclick="revokeCertificate(<?php echo $certificate['id']; ?>)" class="text-red-600 hover:text-red-900 dark:text-red-400" title="Révoquer">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                        <?php else: ?>
                                        <button onclick="verifyCertificate(<?php echo $certificate['id']; ?>)" class="text-green-600 hover:text-green-900 dark:text-green-400" title="Vérifier">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <?php endif; ?>
                                        
                                        <button onclick="sendCertificate(<?php echo $certificate['id']; ?>)" class="text-purple-600 hover:text-purple-900 dark:text-purple-400" title="Envoyer par email">
                                            <i class="fas fa-envelope"></i>
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
                    <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&formation=<?php echo $formation_filter; ?>&status=<?php echo $status_filter; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $order; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Précédent
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&formation=<?php echo $formation_filter; ?>&status=<?php echo $status_filter; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $order; ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Suivant
                    </a>
                    <?php endif; ?>
                </div>
                
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            Affichage de <span class="font-medium"><?php echo ($page-1) * $per_page + 1; ?></span> à <span class="font-medium"><?php echo min($page * $per_page, $total_certificates); ?></span> sur <span class="font-medium"><?php echo $total_certificates; ?></span> résultats
                        </p>
                    </div>
                    
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                            <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&formation=<?php echo $formation_filter; ?>&status=<?php echo $status_filter; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $order; ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&formation=<?php echo $formation_filter; ?>&status=<?php echo $status_filter; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $order; ?>" class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?php echo $i == $page ? 'z-10 bg-netblue-50 border-netblue-500 text-netblue-600' : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600'; ?>">
                                <?php echo $i; ?>
                            </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&formation=<?php echo $formation_filter; ?>&status=<?php echo $status_filter; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $order; ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
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
    
    <!-- Generate Certificate Modal -->
    <div id="generateModal" class="fixed inset-0 z-50 hidden">
        <div class="modal flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeGenerateModal()"></div>
            
            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full max-h-screen overflow-y-auto">
                <form id="generateForm" method="POST">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-green-100 sm:mx-0 sm:h-10 sm:w-10">
                                <i class="fas fa-certificate text-green-600"></i>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                                    Générer un certificat
                                </h3>
                                
                                <div class="mt-6 space-y-4">
                                    <input type="hidden" name="action" value="generate_certificate">
                                    
                                    <!-- Utilisateur et Formation -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Sélectionner un utilisateur et une formation *
                                        </label>
                                        <select name="user_formation" id="userFormationSelect" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                                            <option value="">Choisir un utilisateur et une formation...</option>
                                            <?php while ($eligible = $eligible_users->fetch_assoc()): ?>
                                            <option value="<?php echo $eligible['id']; ?>|<?php echo $eligible['formation_id']; ?>">
                                                <?php echo htmlspecialchars($eligible['firstname'] . ' ' . $eligible['lastname'] . ' - ' . $eligible['formation_title']); ?>
                                            </option>
                                            <?php endwhile; ?>
                                        </select>
                                        <p class="text-xs text-gray-500 mt-1">Seuls les utilisateurs avec des abonnements actifs sans certificat sont affichés.</p>
                                    </div>
                                    
                                    <!-- Quiz Attempt (optionnel) -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Tentative de quiz (optionnel)
                                        </label>
                                        <select name="quiz_attempt_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                                            <option value="0">Aucune tentative de quiz</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Note -->
                                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-md p-4">
                                        <div class="flex">
                                            <i class="fas fa-info-circle text-blue-400 mt-1 mr-2"></i>
                                            <div class="text-sm text-blue-700 dark:text-blue-300">
                                                <strong>Information :</strong> Le certificat sera automatiquement généré avec un numéro unique et un code de vérification. L'utilisateur recevra une notification par email si configurée.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                            <i class="fas fa-certificate mr-2"></i>Générer
                        </button>
                        <button type="button" onclick="closeGenerateModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
                            Annuler
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Revoke Certificate Modal -->
    <div id="revokeModal" class="fixed inset-0 z-50 hidden">
        <div class="modal flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeRevokeModal()"></div>
            
            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form id="revokeForm" method="POST">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                <i class="fas fa-ban text-red-600"></i>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                                    Révoquer le certificat
                                </h3>
                                <div class="mt-4">
                                    <input type="hidden" name="action" value="revoke_certificate">
                                    <input type="hidden" name="certificate_id" id="revokeCertificateId">
                                    
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Raison de la révocation (optionnel)
                                    </label>
                                    <textarea name="reason" rows="3" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-red-500" placeholder="Expliquez pourquoi ce certificat est révoqué..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                            Révoquer
                        </button>
                        <button type="button" onclick="closeRevokeModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
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
            
            // Bulk selection functionality
            const selectAllCheckbox = document.getElementById('selectAll');
            const selectAllTableCheckbox = document.getElementById('selectAllTable');
            const certificateCheckboxes = document.querySelectorAll('.certificate-checkbox');
            const selectedCount = document.getElementById('selectedCount');
            
            function updateSelectedCount() {
                const selectedCheckboxes = document.querySelectorAll('.certificate-checkbox:checked');
                if (selectedCount) {
                    selectedCount.textContent = selectedCheckboxes.length;
                }
            }
            
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    certificateCheckboxes.forEach(checkbox => {
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
                    certificateCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                    if (selectAllCheckbox) {
                        selectAllCheckbox.checked = this.checked;
                    }
                    updateSelectedCount();
                });
            }
            
            certificateCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectedCount);
            });
            
            // Generate form handler
            document.getElementById('generateForm').addEventListener('submit', function(e) {
                const userFormationSelect = document.getElementById('userFormationSelect');
                const selectedValue = userFormationSelect.value;
                
                if (!selectedValue) {
                    e.preventDefault();
                    showNotification('Veuillez sélectionner un utilisateur et une formation', 'error');
                    return;
                }
                
                const [userId, formationId] = selectedValue.split('|');
                
                // Ajouter les champs cachés pour l'envoi
                const userIdInput = document.createElement('input');
                userIdInput.type = 'hidden';
                userIdInput.name = 'user_id';
                userIdInput.value = userId;
                
                const formationIdInput = document.createElement('input');
                formationIdInput.type = 'hidden';
                formationIdInput.name = 'formation_id';
                formationIdInput.value = formationId;
                
                this.appendChild(userIdInput);
                this.appendChild(formationIdInput);
                
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Génération...';
                submitBtn.disabled = true;
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
        
        // Certificate management functions
        function showGenerateModal() {
            document.getElementById('generateModal').classList.remove('hidden');
        }
        
        function closeGenerateModal() {
            document.getElementById('generateModal').classList.add('hidden');
            document.getElementById('generateForm').reset();
        }
        
        function revokeCertificate(certificateId) {
            document.getElementById('revokeCertificateId').value = certificateId;
            document.getElementById('revokeModal').classList.remove('hidden');
        }
        
        function closeRevokeModal() {
            document.getElementById('revokeModal').classList.add('hidden');
            document.getElementById('revokeForm').reset();
        }
        
        function verifyCertificate(certificateId) {
            if (confirm('Êtes-vous sûr de vouloir vérifier ce certificat ?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_certificate">
                    <input type="hidden" name="certificate_id" value="${certificateId}">
                    <input type="hidden" name="verified" value="1">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function viewCertificate(verificationCode) {
            // Ouvrir le certificat dans un nouvel onglet
            window.open(`../certificates/view.php?code=${verificationCode}`, '_blank');
        }
        
        function downloadCertificate(verificationCode) {
            // Télécharger le certificat
            const link = document.createElement('a');
            link.href = `../certificates/download.php?code=${verificationCode}`;
            link.download = `certificate_${verificationCode}.pdf`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            showNotification('Téléchargement du certificat...', 'info');
        }
        
        function sendCertificate(certificateId) {
            if (confirm('Envoyer le certificat par email à l\'utilisateur ?')) {
                fetch('api/certificates.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=send_certificate&certificate_id=${certificateId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Certificat envoyé par email avec succès', 'success');
                    } else {
                        showNotification('Erreur lors de l\'envoi: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    showNotification('Erreur de connexion', 'error');
                });
            }
        }
        
        // Bulk actions
        function executeBulkAction() {
            const bulkAction = document.getElementById('bulkAction').value;
            const selectedCheckboxes = document.querySelectorAll('.certificate-checkbox:checked');
            
            if (!bulkAction) {
                showNotification('Veuillez sélectionner une action', 'warning');
                return;
            }
            
            if (selectedCheckboxes.length === 0) {
                showNotification('Veuillez sélectionner au moins un certificat', 'warning');
                return;
            }
            
            const actionText = {
                'verify': 'vérifier',
                'revoke': 'révoquer'
            };
            
            if (confirm(`Êtes-vous sûr de vouloir ${actionText[bulkAction]} ${selectedCheckboxes.length} certificat(s) ?`)) {
                const form = document.getElementById('bulkForm');
                form.submit();
            }
        }
        
        // Export functionality
        function exportCertificates() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            
            const link = document.createElement('a');
            link.href = 'api/certificates.php?' + params.toString();
            link.download = 'certificats_' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            showNotification('Export en cours...', 'info');
        }
        
        // Certificate verification
        function verifyCertificateByCode() {
            const code = prompt('Entrez le code de vérification du certificat:');
            if (code) {
                fetch(`api/certificates.php?action=verify&code=${code}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(`Certificat valide !\n\nTitulaire: ${data.certificate.user_name}\nFormation: ${data.certificate.formation_title}\nDate d'émission: ${data.certificate.issue_date}`);
                        } else {
                            alert('Certificat non valide ou inexistant.');
                        }
                    })
                    .catch(error => {
                        showNotification('Erreur lors de la vérification', 'error');
                    });
            }
        }
        
        // Statistics functions
        function showCertificateStats() {
            fetch('api/certificates.php?action=stats')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const stats = data.stats;
                        const message = `Statistiques des certificats:
                        
Total: ${stats.total_certificates}
Vérifiés: ${stats.verified_certificates}
Révoqués: ${stats.revoked_certificates}
Utilisateurs uniques: ${stats.unique_users}
Formations avec certificats: ${stats.formations_with_certificates}

Taux de réussite: ${Math.round((stats.verified_certificates / stats.total_certificates) * 100)}%`;
                        
                        alert(message);
                    }
                })
                .catch(error => {
                    showNotification('Erreur lors du chargement des statistiques', 'error');
                });
        }
        
        // Bulk certificate generation
        function bulkGenerateCertificates() {
            if (confirm('Générer automatiquement des certificats pour tous les utilisateurs éligibles ?')) {
                fetch('api/certificates.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=bulk_generate'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(`${data.generated} certificats générés avec succès`, 'success');
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        showNotification('Erreur lors de la génération en masse: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    showNotification('Erreur de connexion', 'error');
                });
            }
        }
        
        // Certificate template management
        function manageTemplates() {
            window.location.href = 'certificate-templates.php';
        }
        
        // Advanced search
        function showAdvancedSearch() {
            // Implementation for advanced search modal
            showNotification('Recherche avancée en cours de développement', 'info');
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
        
        // Print certificate list
        function printCertificateList() {
            window.print();
        }
        
        // Certificate analytics
        function showCertificateAnalytics() {
            // Implementation for certificate analytics
            showNotification('Analyses des certificats en cours de développement', 'info');
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Escape to close modals
            if (e.key === 'Escape') {
                closeGenerateModal();
                closeRevokeModal();
                
                const sidenav = document.getElementById('sidenav');
                const overlay = document.getElementById('overlay');
                
                if (sidenav.classList.contains('open')) {
                    sidenav.classList.remove('open');
                    overlay.classList.remove('active');
                    document.body.classList.remove('overflow-hidden');
                }
            }
            
            // Ctrl/Cmd + G for generate certificate
            if ((e.ctrlKey || e.metaKey) && e.key === 'g') {
                e.preventDefault();
                showGenerateModal();
            }
            
            // Ctrl/Cmd + E for export
            if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
                e.preventDefault();
                exportCertificates();
            }
            
            // Ctrl/Cmd + V for verify certificate
            if ((e.ctrlKey || e.metaKey) && e.key === 'v') {
                e.preventDefault();
                verifyCertificateByCode();
            }
            
            // Ctrl/Cmd + S for statistics
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                showCertificateStats();
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
        
        // Auto-refresh certificates every 2 minutes
        setInterval(() => {
            if (document.visibilityState === 'visible') {
                fetch('api/certificates.php?action=check_new')
                    .then(response => response.json())
                    .then(data => {
                        if (data.new_certificates > 0) {
                            showNotification(`${data.new_certificates} nouveau(x) certificat(s) généré(s)`, 'info');
                        }
                    })
                    .catch(error => {
                        console.log('Error checking for new certificates:', error);
                    });
            }
        }, 120000);
        
        // Certificate validation on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Check for invalid certificates
            fetch('api/certificates.php?action=validate_all')
                .then(response => response.json())
                .then(data => {
                    if (data.invalid_certificates > 0) {
                        showNotification(`Attention: ${data.invalid_certificates} certificat(s) nécessitent une vérification`, 'warning');
                    }
                })
                .catch(error => {
                    console.log('Error validating certificates:', error);
                });
        });
        
        // Enhanced certificate preview
        function previewCertificate(verificationCode) {
            // Create a modal for certificate preview
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75';
            modal.innerHTML = `
                <div class="bg-white rounded-lg p-4 max-w-4xl max-h-screen overflow-auto">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold">Aperçu du certificat</h3>
                        <button onclick="this.closest('.fixed').remove()" class="text-gray-500 hover:text-gray-700">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    <iframe src="../certificates/preview.php?code=${verificationCode}" width="100%" height="600" frameborder="0"></iframe>
                </div>
            `;
            
            document.body.appendChild(modal);
        }
        
        // Certificate duplication check
        function checkDuplicates() {
            fetch('api/certificates.php?action=check_duplicates')
                .then(response => response.json())
                .then(data => {
                    if (data.duplicates.length > 0) {
                        let message = 'Certificats en double détectés:\n\n';
                        data.duplicates.forEach(dup => {
                            message += `- ${dup.user_name} pour ${dup.formation_title}\n`;
                        });
                        alert(message);
                    } else {
                        showNotification('Aucun doublon détecté', 'success');
                    }
                })
                .catch(error => {
                    showNotification('Erreur lors de la vérification des doublons', 'error');
                });
        }
        
        // Initialize tooltips for better UX
        function initTooltips() {
            const tooltipElements = document.querySelectorAll('[title]');
            tooltipElements.forEach(element => {
                element.addEventListener('mouseenter', function() {
                    const tooltip = document.createElement('div');
                    tooltip.className = 'absolute bg-gray-800 text-white text-xs rounded px-2 py-1 z-50 pointer-events-none';
                    tooltip.textContent = this.title;
                    
                    const rect = this.getBoundingClientRect();
                    tooltip.style.top = (rect.top - 30) + 'px';
                    tooltip.style.left = rect.left + 'px';
                    
                    document.body.appendChild(tooltip);
                    
                    this.addEventListener('mouseleave', function() {
                        if (document.body.contains(tooltip)) {
                            document.body.removeChild(tooltip);
                        }
                    });
                });
            });
        }
        
        // Call initialization functions
        initTooltips();
        
        console.log('Certificates Manager initialized successfully');
    </script>
</body>
</html>