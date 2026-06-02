<?php
// admin_formation/quiz.php
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

// Gestion des requêtes AJAX pour récupérer les données de quiz
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'get_quiz_data' && isset($_GET['quiz_id'])) {
        $quiz_id = intval($_GET['quiz_id']);
        
        $query = "SELECT * FROM formation_quizzes WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $quiz_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $quiz = $result->fetch_assoc();
        
        if ($quiz) {
            header('Content-Type: application/json');
            echo json_encode($quiz);
            exit;
        }
        
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Quiz not found']);
        exit;
    }
    
    if ($_GET['action'] === 'get_quiz_stats' && isset($_GET['quiz_id'])) {
        $quiz_id = intval($_GET['quiz_id']);
        
        // Récupérer les statistiques détaillées du quiz
        $stats_query = "SELECT 
            fq.title as quiz_title,
            f.title as formation_title,
            COUNT(qa.id) as total_attempts,
            SUM(CASE WHEN qa.passed = 1 THEN 1 ELSE 0 END) as passed_attempts,
            AVG(qa.score) as avg_score,
            MIN(qa.score) as min_score,
            MAX(qa.score) as max_score,
            (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = ?) as total_questions,
            AVG(qa.time_taken) as avg_time_taken
            FROM formation_quizzes fq
            JOIN formations f ON fq.formation_id = f.id
            LEFT JOIN quiz_attempts qa ON fq.id = qa.quiz_id
            WHERE fq.id = ?
            GROUP BY fq.id";
        
        $stmt = $conn->prepare($stats_query);
        $stmt->bind_param("ii", $quiz_id, $quiz_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats = $result->fetch_assoc();
        
        // Récupérer les tentatives récentes
        $recent_attempts_query = "SELECT 
            qa.*, 
            u.firstname, 
            u.lastname, 
            u.email
            FROM quiz_attempts qa
            JOIN users u ON qa.user_id = u.id
            WHERE qa.quiz_id = ?
            ORDER BY qa.completed_at DESC
            LIMIT 10";
        
        $stmt = $conn->prepare($recent_attempts_query);
        $stmt->bind_param("i", $quiz_id);
        $stmt->execute();
        $recent_result = $stmt->get_result();
        
        $recent_attempts = [];
        while ($row = $recent_result->fetch_assoc()) {
            $recent_attempts[] = $row;
        }
        
        // Récupérer la distribution des scores
        $score_distribution_query = "SELECT 
            CASE 
                WHEN score >= 90 THEN '90-100%'
                WHEN score >= 80 THEN '80-89%'
                WHEN score >= 70 THEN '70-79%'
                WHEN score >= 60 THEN '60-69%'
                WHEN score >= 50 THEN '50-59%'
                ELSE '0-49%'
            END as score_range,
            COUNT(*) as count
            FROM quiz_attempts 
            WHERE quiz_id = ?
            GROUP BY score_range
            ORDER BY score_range DESC";
        
        $stmt = $conn->prepare($score_distribution_query);
        $stmt->bind_param("i", $quiz_id);
        $stmt->execute();
        $distribution_result = $stmt->get_result();
        
        $score_distribution = [];
        while ($row = $distribution_result->fetch_assoc()) {
            $score_distribution[] = $row;
        }
        
        $response = [
            'stats' => $stats,
            'recent_attempts' => $recent_attempts,
            'score_distribution' => $score_distribution
        ];
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// Messages
$success_message = '';
$error_message = '';

// Traitement des actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_quiz':
            createQuiz();
            break;
        case 'update_quiz':
            updateQuiz();
            break;
        case 'delete_quiz':
            deleteQuiz();
            break;
        case 'toggle_quiz_status':
            toggleQuizStatus();
            break;
    }
    
    // Redirection pour éviter la re-soumission
    header("Location: quiz.php" . 
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

function createQuiz() {
    global $conn, $success_message, $error_message;
    
    $formation_id = intval($_POST['formation_id']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description'] ?? '');
    $passing_score = intval($_POST['passing_score'] ?? 70);
    $time_limit = intval($_POST['time_limit'] ?? 0);
    $max_attempts = intval($_POST['max_attempts'] ?? 3);
    
    if (empty($title)) {
        $error_message = "Le titre du quiz est requis.";
        return;
    }
    
    if ($formation_id <= 0) {
        $error_message = "Veuillez sélectionner une formation.";
        return;
    }
    
    $insert_query = "INSERT INTO formation_quizzes (formation_id, title, description, passing_score, time_limit, max_attempts) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("issiii", $formation_id, $title, $description, $passing_score, $time_limit, $max_attempts);
    
    if ($stmt->execute()) {
        $success_message = "Quiz créé avec succès.";
        
        // Log de l'activité
        $log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'create_quiz', ?)";
        $log_stmt = $conn->prepare($log_query);
        $description_log = "Création du quiz: $title (Formation ID: $formation_id)";
        $log_stmt->bind_param("is", $_SESSION['admin_id'], $description_log);
        $log_stmt->execute();
    } else {
        $error_message = "Erreur lors de la création du quiz: " . $conn->error;
    }
}

function updateQuiz() {
    global $conn, $success_message, $error_message;
    
    $quiz_id = intval($_POST['quiz_id']);
    $formation_id = intval($_POST['formation_id']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description'] ?? '');
    $passing_score = intval($_POST['passing_score'] ?? 70);
    $time_limit = intval($_POST['time_limit'] ?? 0);
    $max_attempts = intval($_POST['max_attempts'] ?? 3);
    
    if (empty($title)) {
        $error_message = "Le titre du quiz est requis.";
        return;
    }
    
    if ($formation_id <= 0) {
        $error_message = "Veuillez sélectionner une formation.";
        return;
    }
    
    $update_query = "UPDATE formation_quizzes SET formation_id = ?, title = ?, description = ?, passing_score = ?, time_limit = ?, max_attempts = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("issiiiii", $formation_id, $title, $description, $passing_score, $time_limit, $max_attempts, $quiz_id);
    
    if ($stmt->execute()) {
        $success_message = "Quiz mis à jour avec succès.";
        
        // Log de l'activité
        $log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'update_quiz', ?)";
        $log_stmt = $conn->prepare($log_query);
        $description_log = "Modification du quiz: $title (ID: $quiz_id)";
        $log_stmt->bind_param("is", $_SESSION['admin_id'], $description_log);
        $log_stmt->execute();
    } else {
        $error_message = "Erreur lors de la mise à jour du quiz.";
    }
}

function deleteQuiz() {
    global $conn, $success_message, $error_message;
    
    $quiz_id = intval($_POST['quiz_id']);
    
    // Supprimer d'abord les tentatives de quiz
    $delete_attempts_query = "DELETE FROM quiz_attempts WHERE quiz_id = ?";
    $stmt = $conn->prepare($delete_attempts_query);
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    
    // Supprimer les réponses des questions
    $delete_answers_query = "DELETE qa FROM quiz_answers qa 
                            JOIN quiz_questions qq ON qa.question_id = qq.id 
                            WHERE qq.quiz_id = ?";
    $stmt = $conn->prepare($delete_answers_query);
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    
    // Supprimer les questions
    $delete_questions_query = "DELETE FROM quiz_questions WHERE quiz_id = ?";
    $stmt = $conn->prepare($delete_questions_query);
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    
    // Supprimer le quiz
    $delete_query = "DELETE FROM formation_quizzes WHERE id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("i", $quiz_id);
    
    if ($stmt->execute()) {
        $success_message = "Quiz supprimé avec succès.";
        
        // Log de l'activité
        $log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'delete_quiz', ?)";
        $log_stmt = $conn->prepare($log_query);
        $description_log = "Suppression du quiz ID: $quiz_id";
        $log_stmt->bind_param("is", $_SESSION['admin_id'], $description_log);
        $log_stmt->execute();
    } else {
        $error_message = "Erreur lors de la suppression du quiz.";
    }
}

function toggleQuizStatus() {
    global $conn, $success_message, $error_message;
    
    $quiz_id = intval($_POST['quiz_id']);
    $is_active = intval($_POST['is_active']);
    
    $update_query = "UPDATE formation_quizzes SET is_active = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ii", $is_active, $quiz_id);
    
    if ($stmt->execute()) {
        $status_text = $is_active ? 'activé' : 'désactivé';
        $success_message = "Quiz $status_text avec succès.";
        
        // Log de l'activité
        $log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'toggle_quiz_status', ?)";
        $log_stmt = $conn->prepare($log_query);
        $description_log = "Changement de statut du quiz ID: $quiz_id vers " . ($is_active ? 'actif' : 'inactif');
        $log_stmt->bind_param("is", $_SESSION['admin_id'], $description_log);
        $log_stmt->execute();
    } else {
        $error_message = "Erreur lors de la modification du statut.";
    }
}

// Paramètres de recherche et filtrage
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$formation_filter = isset($_GET['formation']) ? intval($_GET['formation']) : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Construction de la requête
$where_conditions = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where_conditions[] = "(fq.title LIKE CONCAT('%', ?, '%') OR fq.description LIKE CONCAT('%', ?, '%') OR f.title LIKE CONCAT('%', ?, '%'))";
    $params = array_merge($params, [$search, $search, $search]);
    $types .= "sss";
}

if ($formation_filter > 0) {
    $where_conditions[] = "fq.formation_id = ?";
    $params[] = $formation_filter;
    $types .= "i";
}

if ($status_filter !== 'all') {
    $is_active = $status_filter === 'active' ? 1 : 0;
    $where_conditions[] = "fq.is_active = ?";
    $params[] = $is_active;
    $types .= "i";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Récupérer les quiz avec statistiques
$quizzes_query = "SELECT fq.*, f.title as formation_title, c.name as category_name,
                  (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = fq.id) as total_questions,
                  (SELECT COUNT(*) FROM quiz_attempts qa WHERE qa.quiz_id = fq.id) as total_attempts,
                  (SELECT COUNT(*) FROM quiz_attempts qa WHERE qa.quiz_id = fq.id AND qa.passed = 1) as passed_attempts,
                  (SELECT AVG(qa.score) FROM quiz_attempts qa WHERE qa.quiz_id = fq.id) as avg_score
                  FROM formation_quizzes fq
                  JOIN formations f ON fq.formation_id = f.id
                  LEFT JOIN formation_categories c ON f.category_id = c.id
                  $where_clause
                  ORDER BY fq.$sort_by $order";

$quizzes_stmt = $conn->prepare($quizzes_query);
if (!empty($params)) {
    $quizzes_stmt->bind_param($types, ...$params);
}
$quizzes_stmt->execute();
$quizzes_result = $quizzes_stmt->get_result();

$quizzes = [];
while ($row = $quizzes_result->fetch_assoc()) {
    $quizzes[] = $row;
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
    COUNT(*) as total_quizzes,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_quizzes,
    (SELECT COUNT(*) FROM quiz_attempts) as total_attempts,
    (SELECT COUNT(*) FROM quiz_attempts WHERE passed = 1) as passed_attempts,
    (SELECT AVG(score) FROM quiz_attempts) as overall_avg_score
    FROM formation_quizzes";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr" class="light scroll">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Quiz - Admin Netcrafter</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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

        /* Quiz cards */
        .quiz-card {
            transition: all 0.3s ease;
        }
        
        .quiz-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        /* Modal styles */
        .modal {
            backdrop-filter: blur(4px);
        }

        /* Loading spinner */
        .spinner {
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3498db;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            display: inline-block;
        }

        @keyframes spin {
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
                <h1 class="text-xl font-bold text-gray-800 dark:text-white">Gestion des Quiz</h1>
                
                <!-- Actions -->
                <div class="flex items-center space-x-3">
                    <button onclick="showQuizModal()" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded-lg transition-colors text-sm">
                        <i class="fas fa-plus mr-2"></i>Nouveau Quiz
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
                            <i class="fas fa-question-circle text-2xl text-blue-600 dark:text-blue-400"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Quiz</h3>
                            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo number_format($stats['total_quizzes']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-green-100 dark:bg-green-900 p-3 rounded-lg">
                            <i class="fas fa-check-circle text-2xl text-green-600 dark:text-green-400"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">Quiz Actifs</h3>
                            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo number_format($stats['active_quizzes']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-purple-100 dark:bg-purple-900 p-3 rounded-lg">
                            <i class="fas fa-users text-2xl text-purple-600 dark:text-purple-400"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">Tentatives</h3>
                            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo number_format($stats['total_attempts']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up" data-aos-delay="300">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-yellow-100 dark:bg-yellow-900 p-3 rounded-lg">
                            <i class="fas fa-chart-line text-2xl text-yellow-600 dark:text-yellow-400"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">Score Moyen</h3>
                            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo round($stats['overall_avg_score'] ?? 0); ?>%</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 mb-8" data-aos="fade-up">
                <form method="GET" action="quiz.php" class="flex flex-col lg:flex-row gap-4">
                    <!-- Search -->
                    <div class="flex-1">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Rechercher un quiz..." class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
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
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Actifs</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactifs</option>
                        </select>
                    </div>
                    
                    <!-- Sort -->
                    <div>
                        <select name="sort" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                            <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Date de création</option>
                            <option value="title" <?php echo $sort_by === 'title' ? 'selected' : ''; ?>>Titre</option>
                            <option value="passing_score" <?php echo $sort_by === 'passing_score' ? 'selected' : ''; ?>>Score de passage</option>
                        </select>
                    </div>
                    
                    <!-- Submit -->
                    <button type="submit" class="bg-netblue-600 hover:bg-netblue-700 text-white px-6 py-2 rounded-lg transition-colors">
                        <i class="fas fa-search mr-2"></i>Filtrer
                    </button>
                    
                    <!-- Reset -->
                    <a href="quiz.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition-colors text-center">
                        <i class="fas fa-refresh mr-2"></i>Reset
                    </a>
                </form>
            </div>
            
            <!-- Quiz List -->
            <div class="space-y-6" data-aos="fade-up">
                <?php if (empty($quizzes)): ?>
                <!-- Empty State -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-12 text-center">
                    <i class="fas fa-question-circle text-6xl text-gray-400 mb-4"></i>
                    <h3 class="text-xl font-bold mb-2 dark:text-white">Aucun quiz trouvé</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-6">Commencez par créer le premier quiz pour vos formations.</p>
                    <button onclick="showQuizModal()" class="bg-netblue-600 hover:bg-netblue-700 text-white px-6 py-3 rounded-lg transition-colors">
                        <i class="fas fa-plus mr-2"></i>Créer le premier quiz
                    </button>
                </div>
                <?php else: ?>
                <!-- Quiz Cards -->
                <?php foreach ($quizzes as $quiz): ?>
                <div class="quiz-card bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
                    <div class="p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex-1">
                                <div class="flex items-center mb-2">
                                    <h3 class="text-lg font-bold dark:text-white mr-3"><?php echo htmlspecialchars($quiz['title']); ?></h3>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $quiz['is_active'] ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300'; ?>">
                                        <?php echo $quiz['is_active'] ? 'Actif' : 'Inactif'; ?>
                                    </span>
                                </div>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                    Formation: <span class="font-medium"><?php echo htmlspecialchars($quiz['formation_title']); ?></span>
                                    <?php if ($quiz['category_name']): ?>
                                    | Catégorie: <span class="font-medium"><?php echo htmlspecialchars($quiz['category_name']); ?></span>
                                    <?php endif; ?>
                                </p>
                                <?php if (!empty($quiz['description'])): ?>
                                <p class="text-gray-700 dark:text-gray-300 text-sm"><?php echo htmlspecialchars($quiz['description']); ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="flex items-center space-x-2 ml-4">
                                <button onclick="toggleQuizStatus(<?php echo $quiz['id']; ?>, <?php echo $quiz['is_active'] ? 0 : 1; ?>)" class="text-<?php echo $quiz['is_active'] ? 'orange' : 'green'; ?>-600 hover:text-<?php echo $quiz['is_active'] ? 'orange' : 'green'; ?>-800 p-2" title="<?php echo $quiz['is_active'] ? 'Désactiver' : 'Activer'; ?>">
                                    <i class="fas fa-<?php echo $quiz['is_active'] ? 'pause' : 'play'; ?>"></i>
                                </button>
                                <button onclick="editQuiz(<?php echo $quiz['id']; ?>)" class="text-blue-600 hover:text-blue-800 p-2" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="quiz-questions.php?quiz_id=<?php echo $quiz['id']; ?>" class="text-purple-600 hover:text-purple-800 p-2" title="Gérer les questions">
                                    <i class="fas fa-list-ul"></i>
                                </a>
                                <button onclick="viewQuizStats(<?php echo $quiz['id']; ?>)" class="text-green-600 hover:text-green-800 p-2" title="Statistiques">
                                    <i class="fas fa-chart-bar"></i>
                                </button>
                                <button onclick="deleteQuiz(<?php echo $quiz['id']; ?>, '<?php echo htmlspecialchars($quiz['title']); ?>')" class="text-red-600 hover:text-red-800 p-2" title="Supprimer">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Quiz Settings -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                            <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg text-center">
                                <div class="text-lg font-bold text-netblue-600 dark:text-netblue-400"><?php echo $quiz['passing_score']; ?>%</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Score requis</div>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg text-center">
                                <div class="text-lg font-bold text-purple-600 dark:text-purple-400"><?php echo $quiz['time_limit'] ?: '∞'; ?></div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Temps (min)</div>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg text-center">
                                <div class="text-lg font-bold text-yellow-600 dark:text-yellow-400"><?php echo $quiz['max_attempts'] ?: '∞'; ?></div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Tentatives max</div>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg text-center">
                                <div class="text-lg font-bold text-green-600 dark:text-green-400"><?php echo $quiz['total_questions']; ?></div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Questions</div>
                            </div>
                        </div>
                        
                        <!-- Quiz Statistics -->
                        <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                            <div class="flex items-center justify-between text-sm">
                                <div class="flex items-center space-x-6">
                                    <div>
                                        <span class="font-medium text-gray-800 dark:text-white"><?php echo $quiz['total_attempts']; ?></span>
                                        <span class="text-gray-500 dark:text-gray-400">tentatives</span>
                                    </div>
                                    <div>
                                        <span class="font-medium text-gray-800 dark:text-white"><?php echo $quiz['passed_attempts']; ?></span>
                                        <span class="text-gray-500 dark:text-gray-400">réussites</span>
                                    </div>
                                    <div>
                                        <span class="font-medium text-gray-800 dark:text-white"><?php echo round($quiz['avg_score'] ?? 0); ?>%</span>
                                        <span class="text-gray-500 dark:text-gray-400">score moyen</span>
                                    </div>
                                    <?php if ($quiz['total_attempts'] > 0): ?>
                                    <div>
                                        <span class="font-medium text-green-600 dark:text-green-400"><?php echo round(($quiz['passed_attempts'] / $quiz['total_attempts']) * 100); ?>%</span>
                                        <span class="text-gray-500 dark:text-gray-400">taux de réussite</span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="text-xs text-gray-400">
                                    Créé le <?php echo date('d/m/Y', strtotime($quiz['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <!-- Quiz Modal -->
    <div id="quizModal" class="fixed inset-0 z-50 hidden">
        <div class="modal flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeQuizModal()"></div>
            
            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form id="quizForm" method="POST">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="quizModalTitle">
                                    Créer un quiz
                                </h3>
                                
                                <div class="mt-6 space-y-4">
                                    <input type="hidden" name="action" id="quizAction" value="create_quiz">
                                    <input type="hidden" name="quiz_id" id="quizId" value="">
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Formation *
                                        </label>
                                        <select name="formation_id" id="quizFormationId" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                                            <option value="">Sélectionner une formation</option>
                                            <?php foreach ($formations as $formation): ?>
                                            <option value="<?php echo $formation['id']; ?>"><?php echo htmlspecialchars($formation['title']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Titre du quiz *
                                        </label>
                                        <input type="text" name="title" id="quizTitle" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Description
                                        </label>
                                        <textarea name="description" id="quizDescription" rows="3" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500"></textarea>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                Score de passage (%)
                                            </label>
                                            <input type="number" name="passing_score" id="quizPassingScore" min="0" max="100" value="70" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                Temps limite (min)
                                            </label>
                                            <input type="number" name="time_limit" id="quizTimeLimit" min="0" value="0" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                                            <p class="text-xs text-gray-500 mt-1">0 = pas de limite</p>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Nombre max de tentatives
                                        </label>
                                        <input type="number" name="max_attempts" id="quizMaxAttempts" min="0" value="3" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                                        <p class="text-xs text-gray-500 mt-1">0 = illimité</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-netblue-600 text-base font-medium text-white hover:bg-netblue-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                            <span id="quizSubmitIcon" class="fas fa-save mr-2"></span>
                            <span id="quizSubmitText">Sauvegarder</span>
                        </button>
                        <button type="button" onclick="closeQuizModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
                            Annuler
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Quiz Statistics Modal -->
    <div id="statsModal" class="fixed inset-0 z-50 hidden">
        <div class="modal flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeStatsModal()"></div>
            
            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-6xl sm:w-full max-h-screen overflow-y-auto">
                <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                            Statistiques du quiz
                        </h3>
                        <button onclick="closeStatsModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    
                    <div id="quizStatsContent">
                        <div class="flex items-center justify-center py-12">
                            <div class="spinner mr-3"></div>
                            <span>Chargement des statistiques...</span>
                        </div>
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

        // Variables globales
        let isEditMode = false;

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
        
        // Quiz management functions
        function showQuizModal() {
            isEditMode = false;
            document.getElementById('quizModalTitle').textContent = 'Créer un quiz';
            document.getElementById('quizAction').value = 'create_quiz';
            document.getElementById('quizId').value = '';
            document.getElementById('quizForm').reset();
            document.getElementById('quizSubmitIcon').className = 'fas fa-save mr-2';
            document.getElementById('quizSubmitText').textContent = 'Sauvegarder';
            document.getElementById('quizModal').classList.remove('hidden');
        }
        
        function editQuiz(quizId) {
            isEditMode = true;
            document.getElementById('quizModalTitle').textContent = 'Modifier le quiz';
            document.getElementById('quizAction').value = 'update_quiz';
            document.getElementById('quizId').value = quizId;
            document.getElementById('quizSubmitIcon').className = 'spinner mr-2';
            document.getElementById('quizSubmitText').textContent = 'Chargement...';
            
            // Show modal first
            document.getElementById('quizModal').classList.remove('hidden');
            
            // Fetch quiz data via AJAX
            fetch(`quiz.php?action=get_quiz_data&quiz_id=${quizId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        showNotification('Erreur: ' + data.error, 'error');
                        closeQuizModal();
                        return;
                    }
                    
                    // Populate form fields
                    document.getElementById('quizFormationId').value = data.formation_id;
                    document.getElementById('quizTitle').value = data.title;
                    document.getElementById('quizDescription').value = data.description || '';
                    document.getElementById('quizPassingScore').value = data.passing_score;
                    document.getElementById('quizTimeLimit').value = data.time_limit;
                    document.getElementById('quizMaxAttempts').value = data.max_attempts;
                    
                    // Update button
                    document.getElementById('quizSubmitIcon').className = 'fas fa-save mr-2';
                    document.getElementById('quizSubmitText').textContent = 'Mettre à jour';
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Erreur lors du chargement du quiz: ' + error.message, 'error');
                    closeQuizModal();
                });
        }
        
        function closeQuizModal() {
            document.getElementById('quizModal').classList.add('hidden');
            document.getElementById('quizForm').reset();
            isEditMode = false;
        }
        
        function deleteQuiz(quizId, quizTitle) {
            if (confirm(`Êtes-vous sûr de vouloir supprimer le quiz "${quizTitle}" ?\n\nToutes les questions et tentatives seront également supprimées !`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_quiz">
                    <input type="hidden" name="quiz_id" value="${quizId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function toggleQuizStatus(quizId, newStatus) {
            const statusText = newStatus ? 'activer' : 'désactiver';
            if (confirm(`Êtes-vous sûr de vouloir ${statusText} ce quiz ?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="toggle_quiz_status">
                    <input type="hidden" name="quiz_id" value="${quizId}">
                    <input type="hidden" name="is_active" value="${newStatus}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function viewQuizStats(quizId) {
            // Show loading state
            document.getElementById('quizStatsContent').innerHTML = `
                <div class="flex items-center justify-center py-12">
                    <div class="spinner mr-3"></div>
                    <span>Chargement des statistiques...</span>
                </div>
            `;
            document.getElementById('statsModal').classList.remove('hidden');
            
            // Fetch quiz statistics via AJAX
            fetch(`quiz.php?action=get_quiz_stats&quiz_id=${quizId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        showNotification('Erreur: ' + data.error, 'error');
                        closeStatsModal();
                        return;
                    }
                    
                    displayQuizStats(data);
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('quizStatsContent').innerHTML = `
                        <div class="text-center py-12">
                            <i class="fas fa-exclamation-triangle text-4xl text-red-500 mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Erreur de chargement</h3>
                            <p class="text-gray-600 dark:text-gray-400">Impossible de charger les statistiques du quiz.</p>
                            <button onclick="closeStatsModal()" class="mt-4 bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                                Fermer
                            </button>
                        </div>
                    `;
                });
        }
        
        function displayQuizStats(data) {
            const stats = data.stats;
            const recentAttempts = data.recent_attempts;
            const scoreDistribution = data.score_distribution;
            
            // Calculate success rate
            const successRate = stats.total_attempts > 0 ? 
                Math.round((stats.passed_attempts / stats.total_attempts) * 100) : 0;
            
            const statsHtml = `
                <div class="mb-6">
                    <h4 class="text-xl font-bold text-gray-900 dark:text-white mb-2">${stats.quiz_title}</h4>
                    <p class="text-gray-600 dark:text-gray-400">Formation: ${stats.formation_title}</p>
                </div>
                
                <!-- Main Statistics -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-blue-50 dark:bg-blue-900/20 p-6 rounded-lg text-center">
                        <div class="text-3xl font-bold text-blue-600 dark:text-blue-400">${stats.total_attempts || 0}</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Total Tentatives</div>
                    </div>
                    <div class="bg-green-50 dark:bg-green-900/20 p-6 rounded-lg text-center">
                        <div class="text-3xl font-bold text-green-600 dark:text-green-400">${successRate}%</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Taux de Réussite</div>
                    </div>
                    <div class="bg-purple-50 dark:bg-purple-900/20 p-6 rounded-lg text-center">
                        <div class="text-3xl font-bold text-purple-600 dark:text-purple-400">${Math.round(stats.avg_score || 0)}%</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Score Moyen</div>
                    </div>
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 p-6 rounded-lg text-center">
                        <div class="text-3xl font-bold text-yellow-600 dark:text-yellow-400">${stats.total_questions || 0}</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Questions</div>
                    </div>
                </div>
                
                <!-- Additional Stats -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <h5 class="font-medium text-gray-900 dark:text-white mb-2">Score Maximum</h5>
                        <div class="text-2xl font-bold text-gray-800 dark:text-gray-200">${Math.round(stats.max_score || 0)}%</div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <h5 class="font-medium text-gray-900 dark:text-white mb-2">Score Minimum</h5>
                        <div class="text-2xl font-bold text-gray-800 dark:text-gray-200">${Math.round(stats.min_score || 0)}%</div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <h5 class="font-medium text-gray-900 dark:text-white mb-2">Temps Moyen</h5>
                        <div class="text-2xl font-bold text-gray-800 dark:text-gray-200">${formatTime(stats.avg_time_taken || 0)}</div>
                    </div>
                </div>
                
                <!-- Score Distribution Chart -->
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg mb-8">
                    <h5 class="font-medium text-gray-900 dark:text-white mb-4">Distribution des Scores</h5>
                    <div class="h-64">
                        <canvas id="scoreChart"></canvas>
                    </div>
                </div>
                
                <!-- Recent Attempts -->
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg">
                    <h5 class="font-medium text-gray-900 dark:text-white mb-4">Tentatives Récentes</h5>
                    ${recentAttempts.length > 0 ? `
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Utilisateur</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Score</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Statut</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Temps</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    ${recentAttempts.map(attempt => `
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    ${attempt.firstname} ${attempt.lastname}
                                                </div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400">${attempt.email}</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">${attempt.score}%</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${attempt.passed ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                                                    ${attempt.passed ? 'Réussi' : 'Échoué'}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                ${formatDate(attempt.completed_at)}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                ${formatTime(attempt.time_taken)}
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    ` : `
                        <div class="text-center py-8">
                            <i class="fas fa-inbox text-4xl text-gray-400 mb-4"></i>
                            <p class="text-gray-500 dark:text-gray-400">Aucune tentative enregistrée pour ce quiz.</p>
                        </div>
                    `}
                </div>
            `;
            
            document.getElementById('quizStatsContent').innerHTML = statsHtml;
            
            // Create score distribution chart if there's data
            if (scoreDistribution.length > 0) {
                setTimeout(() => createScoreChart(scoreDistribution), 100);
            }
        }
        
        function createScoreChart(scoreDistribution) {
            const ctx = document.getElementById('scoreChart');
            if (!ctx) return;
            
            const labels = scoreDistribution.map(item => item.score_range);
            const data = scoreDistribution.map(item => item.count);
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Nombre de tentatives',
                        data: data,
                        backgroundColor: [
                            'rgba(34, 197, 94, 0.8)',   // 90-100%
                            'rgba(59, 130, 246, 0.8)',  // 80-89%
                            'rgba(168, 85, 247, 0.8)',  // 70-79%
                            'rgba(245, 158, 11, 0.8)',  // 60-69%
                            'rgba(249, 115, 22, 0.8)',  // 50-59%
                            'rgba(239, 68, 68, 0.8)'    // 0-49%
                        ],
                        borderColor: [
                            'rgba(34, 197, 94, 1)',
                            'rgba(59, 130, 246, 1)',
                            'rgba(168, 85, 247, 1)',
                            'rgba(245, 158, 11, 1)',
                            'rgba(249, 115, 22, 1)',
                            'rgba(239, 68, 68, 1)'
                        ],
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
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }
        
        function closeStatsModal() {
            document.getElementById('statsModal').classList.add('hidden');
        }
        
        // Form submission handler
        document.getElementById('quizForm').addEventListener('submit', function(e) {
            const submitButton = this.querySelector('button[type="submit"]');
            const icon = document.getElementById('quizSubmitIcon');
            const text = document.getElementById('quizSubmitText');
            
            // Disable submit button and show loading
            submitButton.disabled = true;
            icon.className = 'spinner mr-2';
            text.textContent = isEditMode ? 'Mise à jour...' : 'Création...';
        });
        
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
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('fr-FR', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        function formatTime(seconds) {
            if (!seconds || seconds === 0) return 'N/A';
            
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const secs = Math.floor(seconds % 60);
            
            if (hours > 0) {
                return `${hours}h ${minutes}m ${secs}s`;
            } else if (minutes > 0) {
                return `${minutes}m ${secs}s`;
            } else {
                return `${secs}s`;
            }
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Escape to close modals
            if (e.key === 'Escape') {
                closeQuizModal();
                closeStatsModal();
                
                const sidenav = document.getElementById('sidenav');
                const overlay = document.getElementById('overlay');
                
                if (sidenav.classList.contains('open')) {
                    sidenav.classList.remove('open');
                    overlay.classList.remove('active');
                    document.body.classList.remove('overflow-hidden');
                }
            }
            
            // Ctrl/Cmd + N for new quiz
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                showQuizModal();
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
        
        // Auto-save draft feature for quiz form
        let autoSaveTimeout;
        function autoSaveDraft() {
            clearTimeout(autoSaveTimeout);
            autoSaveTimeout = setTimeout(() => {
                const title = document.getElementById('quizTitle').value;
                const formationId = document.getElementById('quizFormationId').value;
                
                if (title.trim() && formationId) {
                    localStorage.setItem('quiz_draft', JSON.stringify({
                        formation_id: formationId,
                        title: title,
                        description: document.getElementById('quizDescription').value,
                        passing_score: document.getElementById('quizPassingScore').value,
                        time_limit: document.getElementById('quizTimeLimit').value,
                        max_attempts: document.getElementById('quizMaxAttempts').value,
                        timestamp: Date.now()
                    }));
                }
            }, 2000);
        }
        
        // Load draft on modal open
        function loadDraft() {
            const draft = localStorage.getItem('quiz_draft');
            if (draft && !isEditMode) {
                try {
                    const draftData = JSON.parse(draft);
                    const age = Date.now() - draftData.timestamp;
                    
                    // Only load draft if it's less than 1 hour old
                    if (age < 3600000) {
                        if (confirm('Un brouillon de quiz a été trouvé. Voulez-vous le charger ?')) {
                            document.getElementById('quizFormationId').value = draftData.formation_id;
                            document.getElementById('quizTitle').value = draftData.title;
                            document.getElementById('quizDescription').value = draftData.description;
                            document.getElementById('quizPassingScore').value = draftData.passing_score;
                            document.getElementById('quizTimeLimit').value = draftData.time_limit;
                            document.getElementById('quizMaxAttempts').value = draftData.max_attempts;
                        }
                    } else {
                        localStorage.removeItem('quiz_draft');
                    }
                } catch (e) {
                    localStorage.removeItem('quiz_draft');
                }
            }
        }
        
        // Add auto-save listeners
        document.getElementById('quizTitle').addEventListener('input', autoSaveDraft);
        document.getElementById('quizFormationId').addEventListener('change', autoSaveDraft);
        document.getElementById('quizDescription').addEventListener('input', autoSaveDraft);
        
        // Clear draft on successful submission
        document.getElementById('quizForm').addEventListener('submit', function() {
            localStorage.removeItem('quiz_draft');
        });
        
        // Enhanced quiz modal show function
        const originalShowQuizModal = showQuizModal;
        showQuizModal = function() {
            originalShowQuizModal();
            setTimeout(loadDraft, 100);
        };
        
        // Export quiz data function (bonus feature)
        function exportQuizData(quizId) {
            fetch(`quiz.php?action=get_quiz_stats&quiz_id=${quizId}`)
                .then(response => response.json())
                .then(data => {
                    const exportData = {
                        quiz_info: data.stats,
                        attempts: data.recent_attempts,
                        score_distribution: data.score_distribution,
                        export_date: new Date().toISOString()
                    };
                    
                    const dataStr = JSON.stringify(exportData, null, 2);
                    const dataBlob = new Blob([dataStr], {type: 'application/json'});
                    
                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(dataBlob);
                    link.download = `quiz_${quizId}_stats_${new Date().toISOString().split('T')[0]}.json`;
                    link.click();
                    
                    showNotification('Données exportées avec succès', 'success');
                })
                .catch(error => {
                    console.error('Export error:', error);
                    showNotification('Erreur lors de l\'export', 'error');
                });
        }
        
        console.log('Quiz Management with Edit and Statistics functionality initialized successfully');
    </script>
</body>
</html>