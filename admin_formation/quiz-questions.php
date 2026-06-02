<?php
// admin_formation/quiz-questions.php
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

// Récupérer l'ID du quiz
$quiz_id = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;

// Gestion des requêtes AJAX pour récupérer les données
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'get_question_data' && isset($_GET['question_id'])) {
        $question_id = intval($_GET['question_id']);
        
        $query = "SELECT * FROM quiz_questions WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $question_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $question = $result->fetch_assoc();
        
        if ($question) {
            // Récupérer les réponses
            $answers_query = "SELECT * FROM quiz_answers WHERE question_id = ? ORDER BY id ASC";
            $answers_stmt = $conn->prepare($answers_query);
            $answers_stmt->bind_param("i", $question_id);
            $answers_stmt->execute();
            $answers_result = $answers_stmt->get_result();
            
            $answers = [];
            while ($answer = $answers_result->fetch_assoc()) {
                $answers[] = $answer;
            }
            
            $question['answers'] = $answers;
            
            header('Content-Type: application/json');
            echo json_encode($question);
            exit;
        }
        
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Question not found']);
        exit;
    }
    
    if ($_GET['action'] === 'get_answer_data' && isset($_GET['answer_id'])) {
        $answer_id = intval($_GET['answer_id']);
        
        $query = "SELECT * FROM quiz_answers WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $answer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $answer = $result->fetch_assoc();
        
        if ($answer) {
            header('Content-Type: application/json');
            echo json_encode($answer);
            exit;
        }
        
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Answer not found']);
        exit;
    }
}

if ($quiz_id <= 0) {
    header("Location: quiz.php");
    exit;
}

// Messages
$success_message = '';
$error_message = '';

// Traitement des actions POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_question':
            createQuestion();
            break;
        case 'update_question':
            updateQuestion();
            break;
        case 'delete_question':
            deleteQuestion();
            break;
        case 'create_answer':
            createAnswer();
            break;
        case 'update_answer':
            updateAnswer();
            break;
        case 'delete_answer':
            deleteAnswer();
            break;
        case 'reorder_questions':
            reorderQuestions();
            break;
    }
    
    // Redirection pour éviter la re-soumission
    header("Location: quiz-questions.php?quiz_id=" . $quiz_id . 
           ($success_message ? "&success=" . urlencode($success_message) : "") . 
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

function createQuestion() {
    global $conn, $success_message, $error_message, $quiz_id;
    
    $question = trim($_POST['question']);
    $question_type = $_POST['question_type'];
    $points = intval($_POST['points'] ?? 1);
    $order_number = intval($_POST['order_number'] ?? 1);
    
    if (empty($question)) {
        $error_message = "La question est requise.";
        return;
    }
    
    $insert_query = "INSERT INTO quiz_questions (quiz_id, question, question_type, points, order_number) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("issii", $quiz_id, $question, $question_type, $points, $order_number);
    
    if ($stmt->execute()) {
        $question_id = $conn->insert_id;
        $success_message = "Question créée avec succès.";
        
        // Créer les réponses si elles sont fournies
        if (isset($_POST['answers']) && is_array($_POST['answers'])) {
            $correct_answers = $_POST['correct_answers'] ?? [];
            foreach ($_POST['answers'] as $index => $answer_text) {
                if (!empty(trim($answer_text))) {
                    $answer_text_clean = trim($answer_text);
                    $is_correct = in_array($index, $correct_answers) ? 1 : 0;
                    $answer_query = "INSERT INTO quiz_answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)";
                    $answer_stmt = $conn->prepare($answer_query);
                    $answer_stmt->bind_param("isi", $question_id, $answer_text_clean, $is_correct);
                    $answer_stmt->execute();
                }
            }
        }
        
        // Log de l'activité
        $admin_id = $_SESSION['admin_id'];
        $action = 'create_question';
        $description_log = "Création d'une question pour le quiz ID: " . $quiz_id;
        
        $log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, ?, ?)";
        $log_stmt = $conn->prepare($log_query);
        $log_stmt->bind_param("iss", $admin_id, $action, $description_log);
        $log_stmt->execute();
    } else {
        $error_message = "Erreur lors de la création de la question: " . $conn->error;
    }
}

function updateQuestion() {
    global $conn, $success_message, $error_message;
    
    $question_id = intval($_POST['question_id']);
    $question = trim($_POST['question']);
    $question_type = $_POST['question_type'];
    $points = intval($_POST['points'] ?? 1);
    $order_number = intval($_POST['order_number'] ?? 1);
    
    if (empty($question)) {
        $error_message = "La question est requise.";
        return;
    }
    
    $update_query = "UPDATE quiz_questions SET question = ?, question_type = ?, points = ?, order_number = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ssiii", $question, $question_type, $points, $order_number, $question_id);
    
    if ($stmt->execute()) {
        // Gérer les réponses mises à jour
        if (isset($_POST['answers']) && is_array($_POST['answers'])) {
            // Supprimer toutes les réponses existantes
            $delete_answers_query = "DELETE FROM quiz_answers WHERE question_id = ?";
            $delete_stmt = $conn->prepare($delete_answers_query);
            $delete_stmt->bind_param("i", $question_id);
            $delete_stmt->execute();
            
            // Ajouter les nouvelles réponses
            $correct_answers = $_POST['correct_answers'] ?? [];
            foreach ($_POST['answers'] as $index => $answer_text) {
                if (!empty(trim($answer_text))) {
                    $answer_text_clean = trim($answer_text);
                    $is_correct = in_array($index, $correct_answers) ? 1 : 0;
                    $answer_query = "INSERT INTO quiz_answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)";
                    $answer_stmt = $conn->prepare($answer_query);
                    $answer_stmt->bind_param("isi", $question_id, $answer_text_clean, $is_correct);
                    $answer_stmt->execute();
                }
            }
        }
        
        $success_message = "Question mise à jour avec succès.";
        
        // Log de l'activité
        $admin_id = $_SESSION['admin_id'];
        $action = 'update_question';
        $description_log = "Modification de la question ID: " . $question_id;
        
        $log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, ?, ?)";
        $log_stmt = $conn->prepare($log_query);
        $log_stmt->bind_param("iss", $admin_id, $action, $description_log);
        $log_stmt->execute();
    } else {
        $error_message = "Erreur lors de la mise à jour de la question.";
    }
}

function deleteQuestion() {
    global $conn, $success_message, $error_message;
    
    $question_id = intval($_POST['question_id']);
    
    // Supprimer d'abord les réponses
    $delete_answers_query = "DELETE FROM quiz_answers WHERE question_id = ?";
    $stmt = $conn->prepare($delete_answers_query);
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    
    // Supprimer la question
    $delete_query = "DELETE FROM quiz_questions WHERE id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("i", $question_id);
    
    if ($stmt->execute()) {
        $success_message = "Question supprimée avec succès.";
        
        // Log de l'activité
        $admin_id = $_SESSION['admin_id'];
        $action = 'delete_question';
        $description_log = "Suppression de la question ID: " . $question_id;
        
        $log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, ?, ?)";
        $log_stmt = $conn->prepare($log_query);
        $log_stmt->bind_param("iss", $admin_id, $action, $description_log);
        $log_stmt->execute();
    } else {
        $error_message = "Erreur lors de la suppression de la question.";
    }
}

function createAnswer() {
    global $conn, $success_message, $error_message;
    
    $question_id = intval($_POST['question_id']);
    $answer_text = trim($_POST['answer_text']);
    $is_correct = isset($_POST['is_correct']) ? 1 : 0;
    
    if (empty($answer_text)) {
        $error_message = "Le texte de la réponse est requis.";
        return;
    }
    
    $insert_query = "INSERT INTO quiz_answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("isi", $question_id, $answer_text, $is_correct);
    
    if ($stmt->execute()) {
        $success_message = "Réponse ajoutée avec succès.";
    } else {
        $error_message = "Erreur lors de l'ajout de la réponse.";
    }
}

function updateAnswer() {
    global $conn, $success_message, $error_message;
    
    $answer_id = intval($_POST['answer_id']);
    $answer_text = trim($_POST['answer_text']);
    $is_correct = isset($_POST['is_correct']) ? 1 : 0;
    
    if (empty($answer_text)) {
        $error_message = "Le texte de la réponse est requis.";
        return;
    }
    
    $update_query = "UPDATE quiz_answers SET answer_text = ?, is_correct = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("sii", $answer_text, $is_correct, $answer_id);
    
    if ($stmt->execute()) {
        $success_message = "Réponse mise à jour avec succès.";
        
        // Log de l'activité
        $admin_id = $_SESSION['admin_id'];
        $action = 'update_answer';
        $description_log = "Modification de la réponse ID: " . $answer_id;
        
        $log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, ?, ?)";
        $log_stmt = $conn->prepare($log_query);
        $log_stmt->bind_param("iss", $admin_id, $action, $description_log);
        $log_stmt->execute();
    } else {
        $error_message = "Erreur lors de la mise à jour de la réponse.";
    }
}

function deleteAnswer() {
    global $conn, $success_message, $error_message;
    
    $answer_id = intval($_POST['answer_id']);
    
    $delete_query = "DELETE FROM quiz_answers WHERE id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("i", $answer_id);
    
    if ($stmt->execute()) {
        $success_message = "Réponse supprimée avec succès.";
        
        // Log de l'activité
        $admin_id = $_SESSION['admin_id'];
        $action = 'delete_answer';
        $description_log = "Suppression de la réponse ID: " . $answer_id;
        
        $log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, ?, ?)";
        $log_stmt = $conn->prepare($log_query);
        $log_stmt->bind_param("iss", $admin_id, $action, $description_log);
        $log_stmt->execute();
    } else {
        $error_message = "Erreur lors de la suppression de la réponse.";
    }
}

function reorderQuestions() {
    global $conn, $success_message, $error_message;
    
    $question_orders = $_POST['question_orders'] ?? [];
    
    foreach ($question_orders as $question_id => $order) {
        $question_id_int = intval($question_id);
        $order_int = intval($order);
        $update_query = "UPDATE quiz_questions SET order_number = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ii", $order_int, $question_id_int);
        $stmt->execute();
    }
    
    $success_message = "Ordre des questions mis à jour avec succès.";
}

// Récupérer les informations du quiz
$quiz_query = "SELECT fq.*, f.title as formation_title FROM formation_quizzes fq 
               JOIN formations f ON fq.formation_id = f.id 
               WHERE fq.id = ?";
$stmt = $conn->prepare($quiz_query);
$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$quiz_result = $stmt->get_result();
$quiz = $quiz_result->fetch_assoc();

if (!$quiz) {
    header("Location: quiz.php");
    exit;
}

// Récupérer les questions avec leurs réponses
$questions_query = "SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY order_number ASC";
$stmt = $conn->prepare($questions_query);
$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$questions_result = $stmt->get_result();

$questions = [];
while ($question = $questions_result->fetch_assoc()) {
    // Récupérer les réponses pour cette question
    $answers_query = "SELECT * FROM quiz_answers WHERE question_id = ? ORDER BY id ASC";
    $answers_stmt = $conn->prepare($answers_query);
    $answers_stmt->bind_param("i", $question['id']);
    $answers_stmt->execute();
    $answers_result = $answers_stmt->get_result();
    
    $answers = [];
    while ($answer = $answers_result->fetch_assoc()) {
        $answers[] = $answer;
    }
    
    $question['answers'] = $answers;
    $questions[] = $question;
}

// Statistiques
$stats = [
    'total_questions' => count($questions),
    'total_points' => array_sum(array_column($questions, 'points')),
    'question_types' => []
];

foreach ($questions as $question) {
    $type = $question['question_type'];
    $stats['question_types'][$type] = ($stats['question_types'][$type] ?? 0) + 1;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr" class="light scroll">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Questions du Quiz: <?php echo htmlspecialchars($quiz['title']); ?> - Admin Netcrafter</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    
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

        /* Question cards */
        .question-card {
            transition: all 0.3s ease;
        }
        
        .question-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        /* Sortable */
        .sortable-ghost {
            opacity: 0.4;
        }
        
        .sortable-chosen {
            cursor: grabbing;
        }

        /* Answer options */
        .answer-option {
            transition: all 0.2s ease;
        }
        
        .answer-option.correct {
            background-color: #d1fae5;
            border-color: #10b981;
        }
        
        .answer-option.incorrect {
            background-color: #fee2e2;
            border-color: #ef4444;
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
                
                <!-- Breadcrumb -->
                <div class="flex items-center space-x-2 text-sm">
                    <a href="quiz.php" class="text-gray-500 hover:text-netblue-600 dark:hover:text-netblue-400">Quiz</a>
                    <i class="fas fa-chevron-right text-gray-400"></i>
                    <span class="text-gray-800 dark:text-white font-medium"><?php echo htmlspecialchars($quiz['title']); ?></span>
                </div>
                
                <!-- Actions -->
                <div class="flex items-center space-x-3">
                    <button onclick="showQuestionModal()" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded-lg transition-colors text-sm">
                        <i class="fas fa-plus mr-2"></i>Nouvelle Question
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
            <!-- Quiz Info -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 mb-8" data-aos="fade-up">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h1 class="text-2xl font-bold mb-2 dark:text-white"><?php echo htmlspecialchars($quiz['title']); ?></h1>
                        <div class="flex items-center space-x-4 text-sm text-gray-600 dark:text-gray-400">
                            <span><i class="fas fa-graduation-cap mr-1"></i><?php echo htmlspecialchars($quiz['formation_title']); ?></span>
                            <span><i class="fas fa-percentage mr-1"></i>Score requis: <?php echo $quiz['passing_score']; ?>%</span>
                            <?php if ($quiz['time_limit']): ?>
                            <span><i class="fas fa-clock mr-1"></i>Temps: <?php echo $quiz['time_limit']; ?> min</span>
                            <?php endif; ?>
                            <?php if ($quiz['max_attempts']): ?>
                            <span><i class="fas fa-redo mr-1"></i>Max: <?php echo $quiz['max_attempts']; ?> tentatives</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($quiz['description'])): ?>
                        <p class="text-gray-700 dark:text-gray-300 mt-3"><?php echo htmlspecialchars($quiz['description']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="ml-6">
                        <span class="px-3 py-1 text-sm font-semibold rounded-full <?php echo $quiz['is_active'] ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300'; ?>">
                            <?php echo $quiz['is_active'] ? 'Actif' : 'Inactif'; ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-blue-100 dark:bg-blue-900 p-3 rounded-lg">
                            <i class="fas fa-list-ol text-2xl text-blue-600 dark:text-blue-400"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">Questions</h3>
                            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo $stats['total_questions']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-purple-100 dark:bg-purple-900 p-3 rounded-lg">
                            <i class="fas fa-star text-2xl text-purple-600 dark:text-purple-400"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">Points Total</h3>
                            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo $stats['total_points']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-green-100 dark:bg-green-900 p-3 rounded-lg">
                            <i class="fas fa-list-ul text-2xl text-green-600 dark:text-green-400"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">QCM</h3>
                            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo $stats['question_types']['multiple_choice'] ?? 0; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up" data-aos-delay="300">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-yellow-100 dark:bg-yellow-900 p-3 rounded-lg">
                            <i class="fas fa-check-square text-2xl text-yellow-600 dark:text-yellow-400"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">Vrai/Faux</h3>
                            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo $stats['question_types']['true_false'] ?? 0; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Questions List -->
            <div class="space-y-6" data-aos="fade-up">
                <?php if (empty($questions)): ?>
                <!-- Empty State -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-12 text-center">
                    <i class="fas fa-question-circle text-6xl text-gray-400 mb-4"></i>
                    <h3 class="text-xl font-bold mb-2 dark:text-white">Aucune question créée</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-6">Commencez par créer la première question de ce quiz.</p>
                    <button onclick="showQuestionModal()" class="bg-netblue-600 hover:bg-netblue-700 text-white px-6 py-3 rounded-lg transition-colors">
                        <i class="fas fa-plus mr-2"></i>Créer la première question
                    </button>
                </div>
                <?php else: ?>
                <!-- Questions List -->
                <div id="questionsList" class="space-y-6">
                    <?php foreach ($questions as $index => $question): ?>
                    <div class="question-card bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden" data-question-id="<?php echo $question['id']; ?>">
                        <!-- Question Header -->
                        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                            <div class="flex items-start justify-between">
                                <div class="flex items-start space-x-4 flex-1">
                                    <div class="flex-shrink-0 bg-netblue-100 dark:bg-netblue-900 p-2 rounded-lg cursor-move drag-handle">
                                        <i class="fas fa-grip-vertical text-netblue-600 dark:text-netblue-400"></i>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center mb-2">
                                            <h3 class="text-lg font-bold dark:text-white mr-3">
                                                Question <?php echo $index + 1; ?>
                                            </h3>
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                                <?php 
                                                switch($question['question_type']) {
                                                    case 'multiple_choice': echo 'QCM'; break;
                                                    case 'multiple_select': echo 'Multi-sélection'; break;
                                                    case 'true_false': echo 'Vrai/Faux'; break;
                                                    case 'short_answer': echo 'Réponse courte'; break;
                                                    default: echo ucfirst($question['question_type']);
                                                }
                                                ?>
                                            </span>
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300 ml-2">
                                                <?php echo $question['points']; ?> pt<?php echo $question['points'] > 1 ? 's' : ''; ?>
                                            </span>
                                        </div>
                                        <p class="text-gray-700 dark:text-gray-300 font-medium"><?php echo htmlspecialchars($question['question']); ?></p>
                                    </div>
                                </div>
                                
                                <div class="flex items-center space-x-2 ml-4">
                                    <button onclick="editQuestion(<?php echo $question['id']; ?>)" class="text-blue-600 hover:text-blue-800 p-2" title="Modifier">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="addAnswer(<?php echo $question['id']; ?>)" class="text-green-600 hover:text-green-800 p-2" title="Ajouter une réponse">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <button onclick="deleteQuestion(<?php echo $question['id']; ?>)" class="text-red-600 hover:text-red-800 p-2" title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Answers -->
                        <div class="p-6">
                            <?php if (empty($question['answers'])): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-list text-2xl text-gray-400 mb-2"></i>
                                <p class="text-gray-500 dark:text-gray-400 mb-3">Aucune réponse ajoutée</p>
                                <button onclick="addAnswer(<?php echo $question['id']; ?>)" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm transition-colors">
                                    <i class="fas fa-plus mr-2"></i>Ajouter des réponses
                                </button>
                            </div>
                            <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach ($question['answers'] as $answer): ?>
                                <div class="answer-option flex items-center justify-between p-3 border rounded-lg <?php echo $answer['is_correct'] ? 'correct border-green-500 bg-green-50 dark:bg-green-900/20' : 'incorrect border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700'; ?>">
                                    <div class="flex items-center flex-1">
                                        <div class="flex-shrink-0 mr-3">
                                            <?php if ($answer['is_correct']): ?>
                                            <i class="fas fa-check-circle text-green-600 dark:text-green-400"></i>
                                            <?php else: ?>
                                            <i class="fas fa-times-circle text-red-600 dark:text-red-400"></i>
                                            <?php endif; ?>
                                        </div>
                                        <span class="text-gray-800 dark:text-white"><?php echo htmlspecialchars($answer['answer_text']); ?></span>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <button onclick="editAnswer(<?php echo $answer['id']; ?>)" class="text-blue-600 hover:text-blue-800 p-1" title="Modifier">
                                            <i class="fas fa-edit text-sm"></i>
                                        </button>
                                        <button onclick="deleteAnswer(<?php echo $answer['id']; ?>)" class="text-red-600 hover:text-red-800 p-1" title="Supprimer">
                                            <i class="fas fa-trash text-sm"></i>
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="mt-4 flex justify-between items-center text-sm text-gray-500 dark:text-gray-400">
                                <span><?php echo count($question['answers']); ?> réponse<?php echo count($question['answers']) > 1 ? 's' : ''; ?></span>
                                <button onclick="addAnswer(<?php echo $question['id']; ?>)" class="text-green-600 hover:text-green-800">
                                    <i class="fas fa-plus mr-1"></i>Ajouter une réponse
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Reorder Questions -->
                <?php if (count($questions) > 1): ?>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 text-center">
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        <i class="fas fa-hand-rock mr-2"></i>
                        Glissez-déposez les questions pour changer leur ordre
                    </p>
                    <button onclick="saveQuestionOrder()" class="bg-netblue-600 hover:bg-netblue-700 text-white px-6 py-2 rounded-lg transition-colors">
                        <i class="fas fa-save mr-2"></i>Sauvegarder l'ordre
                    </button>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <!-- Question Modal -->
    <div id="questionModal" class="fixed inset-0 z-50 hidden">
        <div class="modal flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeQuestionModal()"></div>
            
            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full max-h-screen overflow-y-auto">
                <form id="questionForm" method="POST">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="questionModalTitle">
                                    Créer une question
                                </h3>
                                
                                <div class="mt-6 space-y-4">
                                    <input type="hidden" name="action" id="questionAction" value="create_question">
                                    <input type="hidden" name="question_id" id="questionId" value="">
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Question *
                                        </label>
                                        <textarea name="question" id="questionText" rows="3" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500"></textarea>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                Type de question
                                            </label>
                                            <select name="question_type" id="questionType" onchange="handleQuestionTypeChange()" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                                                <option value="multiple_choice">QCM (choix unique)</option>
                                                <option value="multiple_select">Multi-sélection</option>
                                                <option value="true_false">Vrai/Faux</option>
                                                <option value="short_answer">Réponse courte</option>
                                            </select>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                Points
                                            </label>
                                            <input type="number" name="points" id="questionPoints" min="1" max="10" value="1" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                Ordre
                                            </label>
                                            <input type="number" name="order_number" id="questionOrder" min="1" value="<?php echo count($questions) + 1; ?>" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                                        </div>
                                    </div>
                                    
                                    <!-- Answers Section -->
                                    <div id="answersSection">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Réponses
                                        </label>
                                        <div id="answersList" class="space-y-3">
                                            <!-- Answers will be added dynamically -->
                                        </div>
                                        <button type="button" onclick="addAnswerField()" class="mt-3 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm transition-colors">
                                            <i class="fas fa-plus mr-2"></i>Ajouter une réponse
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-netblue-600 text-base font-medium text-white hover:bg-netblue-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                            <span id="questionSubmitIcon" class="fas fa-save mr-2"></span>
                            <span id="questionSubmitText">Sauvegarder</span>
                        </button>
                        <button type="button" onclick="closeQuestionModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
                            Annuler
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Answer Modal -->
    <div id="answerModal" class="fixed inset-0 z-50 hidden">
        <div class="modal flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeAnswerModal()"></div>
            
            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form id="answerForm" method="POST">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="answerModalTitle">
                                    Ajouter une réponse
                                </h3>
                                
                                <div class="mt-6 space-y-4">
                                    <input type="hidden" name="action" id="answerAction" value="create_answer">
                                    <input type="hidden" name="answer_id" id="answerId" value="">
                                    <input type="hidden" name="question_id" id="answerQuestionId" value="">
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Texte de la réponse *
                                        </label>
                                        <textarea name="answer_text" id="answerText" rows="3" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500"></textarea>
                                    </div>
                                    
                                    <div>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="is_correct" id="answerIsCorrect" class="rounded border-gray-300 text-netblue-600 shadow-sm focus:border-netblue-300 focus:ring focus:ring-netblue-200 focus:ring-opacity-50">
                                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Cette réponse est correcte</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-netblue-600 text-base font-medium text-white hover:bg-netblue-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                            <span id="answerSubmitIcon" class="fas fa-save mr-2"></span>
                            <span id="answerSubmitText">Sauvegarder</span>
                        </button>
                        <button type="button" onclick="closeAnswerModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
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

        // Variables globales
        let sortable;
        let answerFieldCount = 0;
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
            
            // Initialize sortable for questions reordering
            const questionsList = document.getElementById('questionsList');
            if (questionsList) {
                sortable = Sortable.create(questionsList, {
                    handle: '.drag-handle',
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    chosenClass: 'sortable-chosen',
                    onEnd: function(evt) {
                        showNotification('Ordre modifié. N\'oubliez pas de sauvegarder !', 'info');
                    }
                });
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
        
        // Question management functions
        function showQuestionModal() {
            isEditMode = false;
            document.getElementById('questionModalTitle').textContent = 'Créer une question';
            document.getElementById('questionAction').value = 'create_question';
            document.getElementById('questionId').value = '';
            document.getElementById('questionForm').reset();
            document.getElementById('questionOrder').value = <?php echo count($questions) + 1; ?>;
            document.getElementById('questionSubmitIcon').className = 'fas fa-save mr-2';
            document.getElementById('questionSubmitText').textContent = 'Sauvegarder';
            
            // Reset answers section
            document.getElementById('answersList').innerHTML = '';
            answerFieldCount = 0;
            
            // Add default answer fields based on question type
            handleQuestionTypeChange();
            
            document.getElementById('questionModal').classList.remove('hidden');
        }
        
        function editQuestion(questionId) {
            isEditMode = true;
            document.getElementById('questionModalTitle').textContent = 'Modifier la question';
            document.getElementById('questionAction').value = 'update_question';
            document.getElementById('questionId').value = questionId;
            document.getElementById('questionSubmitIcon').className = 'spinner mr-2';
            document.getElementById('questionSubmitText').textContent = 'Chargement...';
            
            // Show modal first
            document.getElementById('questionModal').classList.remove('hidden');
            
            // Construire l'URL avec les paramètres GET
            const url = `quiz-questions.php?action=get_question_data&question_id=${questionId}&quiz_id=<?php echo $quiz_id; ?>`;
            
            // Fetch question data via AJAX
            fetch(url)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        showNotification('Erreur: ' + data.error, 'error');
                        closeQuestionModal();
                        return;
                    }
                    
                    // Populate form fields
                    document.getElementById('questionText').value = data.question;
                    document.getElementById('questionType').value = data.question_type;
                    document.getElementById('questionPoints').value = data.points;
                    document.getElementById('questionOrder').value = data.order_number;
                    
                    // Reset answers section
                    document.getElementById('answersList').innerHTML = '';
                    answerFieldCount = 0;
                    
                    // Handle answers based on question type
                    if (data.question_type === 'short_answer') {
                        document.getElementById('answersSection').style.display = 'none';
                    } else {
                        document.getElementById('answersSection').style.display = 'block';
                        
                        if (data.question_type === 'true_false') {
                            // Find true/false answers
                            const trueAnswer = data.answers.find(a => a.answer_text.toLowerCase() === 'vrai');
                            const falseAnswer = data.answers.find(a => a.answer_text.toLowerCase() === 'faux');
                            
                            addAnswerField('Vrai', trueAnswer ? (trueAnswer.is_correct == 1) : false);
                            addAnswerField('Faux', falseAnswer ? (falseAnswer.is_correct == 1) : false);
                        } else {
                            // Add existing answers
                            if (data.answers && data.answers.length > 0) {
                                data.answers.forEach(answer => {
                                    addAnswerField(answer.answer_text, answer.is_correct == 1);
                                });
                            }
                            
                            // Add at least 2 empty fields if less than 2 answers
                            while (answerFieldCount < 2) {
                                addAnswerField();
                            }
                        }
                    }
                    
                    // Update button
                    document.getElementById('questionSubmitIcon').className = 'fas fa-save mr-2';
                    document.getElementById('questionSubmitText').textContent = 'Mettre à jour';
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Erreur lors du chargement de la question: ' + error.message, 'error');
                    closeQuestionModal();
                });
        }
        
        function closeQuestionModal() {
            document.getElementById('questionModal').classList.add('hidden');
            document.getElementById('questionForm').reset();
            isEditMode = false;
        }
        
        function deleteQuestion(questionId) {
            if (confirm('Êtes-vous sûr de vouloir supprimer cette question ?\n\nToutes les réponses seront également supprimées !')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_question">
                    <input type="hidden" name="question_id" value="${questionId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Answer management functions
        function addAnswer(questionId) {
            document.getElementById('answerModalTitle').textContent = 'Ajouter une réponse';
            document.getElementById('answerAction').value = 'create_answer';
            document.getElementById('answerId').value = '';
            document.getElementById('answerQuestionId').value = questionId;
            document.getElementById('answerForm').reset();
            document.getElementById('answerSubmitIcon').className = 'fas fa-save mr-2';
            document.getElementById('answerSubmitText').textContent = 'Sauvegarder';
            document.getElementById('answerModal').classList.remove('hidden');
        }
        
        function editAnswer(answerId) {
            document.getElementById('answerModalTitle').textContent = 'Modifier la réponse';
            document.getElementById('answerAction').value = 'update_answer';
            document.getElementById('answerId').value = answerId;
            document.getElementById('answerSubmitIcon').className = 'spinner mr-2';
            document.getElementById('answerSubmitText').textContent = 'Chargement...';
            
            // Show modal first
            document.getElementById('answerModal').classList.remove('hidden');
            
            // Construire l'URL avec les paramètres GET
            const url = `quiz-questions.php?action=get_answer_data&answer_id=${answerId}&quiz_id=<?php echo $quiz_id; ?>`;
            
            // Fetch answer data via AJAX
            fetch(url)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        showNotification('Erreur: ' + data.error, 'error');
                        closeAnswerModal();
                        return;
                    }
                    
                    // Populate form fields
                    document.getElementById('answerText').value = data.answer_text;
                    document.getElementById('answerIsCorrect').checked = data.is_correct == 1;
                    document.getElementById('answerQuestionId').value = data.question_id;
                    
                    // Update button
                    document.getElementById('answerSubmitIcon').className = 'fas fa-save mr-2';
                    document.getElementById('answerSubmitText').textContent = 'Mettre à jour';
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Erreur lors du chargement de la réponse: ' + error.message, 'error');
                    closeAnswerModal();
                });
        }
        
        function closeAnswerModal() {
            document.getElementById('answerModal').classList.add('hidden');
            document.getElementById('answerForm').reset();
        }
        
        function deleteAnswer(answerId) {
            if (confirm('Êtes-vous sûr de vouloir supprimer cette réponse ?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_answer">
                    <input type="hidden" name="answer_id" value="${answerId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Question type handling
        function handleQuestionTypeChange() {
            const questionType = document.getElementById('questionType').value;
            const answersSection = document.getElementById('answersSection');
            const answersList = document.getElementById('answersList');
            
            // Clear existing answers
            answersList.innerHTML = '';
            answerFieldCount = 0;
            
            if (questionType === 'short_answer') {
                answersSection.style.display = 'none';
            } else {
                answersSection.style.display = 'block';
                
                if (questionType === 'true_false') {
                    // Add True/False options
                    addAnswerField('Vrai', true);
                    addAnswerField('Faux', false);
                } else {
                    // Add default empty answer fields
                    addAnswerField();
                    addAnswerField();
                }
            }
        }
        
        function addAnswerField(defaultText = '', defaultCorrect = false) {
            const answersList = document.getElementById('answersList');
            const questionType = document.getElementById('questionType').value;
            const inputType = questionType === 'multiple_select' ? 'checkbox' : 'radio';
            const inputName = questionType === 'multiple_select' ? 'correct_answers[]' : 'correct_answers[]';
            
            const answerDiv = document.createElement('div');
            answerDiv.className = 'flex items-center space-x-3';
            answerDiv.innerHTML = `
                <input type="${inputType}" name="${inputName}" value="${answerFieldCount}" ${defaultCorrect ? 'checked' : ''} class="rounded border-gray-300 text-netblue-600 shadow-sm focus:border-netblue-300 focus:ring focus:ring-netblue-200 focus:ring-opacity-50">
                <input type="text" name="answers[]" value="${defaultText}" placeholder="Texte de la réponse" required class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                <button type="button" onclick="removeAnswerField(this)" class="text-red-600 hover:text-red-800 p-2" ${questionType === 'true_false' ? 'style="display:none;"' : ''}>
                    <i class="fas fa-trash"></i>
                </button>
            `;
            
            answersList.appendChild(answerDiv);
            answerFieldCount++;
        }
        
        function removeAnswerField(button) {
            const questionType = document.getElementById('questionType').value;
            if (questionType === 'true_false') {
                showNotification('Impossible de supprimer les réponses Vrai/Faux', 'warning');
                return;
            }
            
            button.parentElement.remove();
            
            // Ensure at least 2 answer fields for non true/false questions
            const answersList = document.getElementById('answersList');
            if (answersList.children.length < 2) {
                addAnswerField();
            }
        }
        
        // Question reordering
        function saveQuestionOrder() {
            const questionCards = document.querySelectorAll('.question-card');
            const questionOrders = {};
            
            questionCards.forEach((card, index) => {
                const questionId = card.getAttribute('data-question-id');
                questionOrders[questionId] = index + 1;
            });
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="action" value="reorder_questions">';
            
            for (const [questionId, order] of Object.entries(questionOrders)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `question_orders[${questionId}]`;
                input.value = order;
                form.appendChild(input);
            }
            
            document.body.appendChild(form);
            form.submit();
        }
        
        // Form submission handlers
        document.getElementById('questionForm').addEventListener('submit', function(e) {
            if (!validateQuestionForm()) {
                e.preventDefault();
                
                // Reset button state
                const submitButton = this.querySelector('button[type="submit"]');
                const icon = document.getElementById('questionSubmitIcon');
                const text = document.getElementById('questionSubmitText');
                
                submitButton.disabled = false;
                icon.className = 'fas fa-save mr-2';
                text.textContent = isEditMode ? 'Mettre à jour' : 'Sauvegarder';
                return;
            }
            
            const submitButton = this.querySelector('button[type="submit"]');
            const icon = document.getElementById('questionSubmitIcon');
            const text = document.getElementById('questionSubmitText');
            
            // Disable submit button and show loading
            submitButton.disabled = true;
            icon.className = 'spinner mr-2';
            text.textContent = isEditMode ? 'Mise à jour...' : 'Création...';
        });
        
        document.getElementById('answerForm').addEventListener('submit', function(e) {
            const submitButton = this.querySelector('button[type="submit"]');
            const icon = document.getElementById('answerSubmitIcon');
            const text = document.getElementById('answerSubmitText');
            
            // Disable submit button and show loading
            submitButton.disabled = true;
            icon.className = 'spinner mr-2';
            text.textContent = 'Sauvegarde...';
        });
        
        // Validation functions
        function validateQuestionForm() {
            const questionText = document.getElementById('questionText').value.trim();
            const questionType = document.getElementById('questionType').value;
            
            if (!questionText) {
                showNotification('Le texte de la question est requis', 'error');
                return false;
            }
            
            if (questionType !== 'short_answer') {
                const answers = document.querySelectorAll('#answersList input[name="answers[]"]');
                const correctAnswers = document.querySelectorAll('#answersList input[name="correct_answers[]"]:checked');
                
                let hasValidAnswers = false;
                answers.forEach(answer => {
                    if (answer.value.trim()) {
                        hasValidAnswers = true;
                    }
                });
                
                if (!hasValidAnswers) {
                    showNotification('Au moins une réponse est requise', 'error');
                    return false;
                }
                
                if (correctAnswers.length === 0) {
                    showNotification('Au moins une réponse correcte doit être sélectionnée', 'error');
                    return false;
                }
            }
            
            return true;
        }
        
        // Utility functions
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg text-white z-50 transform translate-x-full transition-transform duration-300 ${
                type === 'success' ? 'bg-green-500' : 
                type === 'error' ? 'bg-red-500' : 
                type === 'warning' ? 'bg-yellow-500' : 'bg-blue-500'}`;
            
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
        
        // Auto-save draft feature (optional)
        let autoSaveTimeout;
        function autoSaveDraft() {
            clearTimeout(autoSaveTimeout);
            autoSaveTimeout = setTimeout(() => {
                const questionText = document.getElementById('questionText').value;
                const questionType = document.getElementById('questionType').value;
                
                if (questionText.trim()) {
                    localStorage.setItem('quiz_question_draft', JSON.stringify({
                        question: questionText,
                        type: questionType,
                        timestamp: Date.now()
                    }));
                }
            }, 2000);
        }
        
        // Load draft on modal open
        function loadDraft() {
            const draft = localStorage.getItem('quiz_question_draft');
            if (draft && !isEditMode) {
                try {
                    const draftData = JSON.parse(draft);
                    const age = Date.now() - draftData.timestamp;
                    
                    // Only load draft if it's less than 1 hour old
                    if (age < 3600000) {
                        if (confirm('Un brouillon de question a été trouvé. Voulez-vous le charger ?')) {
                            document.getElementById('questionText').value = draftData.question;
                            document.getElementById('questionType').value = draftData.type;
                            handleQuestionTypeChange();
                        }
                    } else {
                        localStorage.removeItem('quiz_question_draft');
                    }
                } catch (e) {
                    localStorage.removeItem('quiz_question_draft');
                }
            }
        }
        
        // Add auto-save listeners
        document.getElementById('questionText').addEventListener('input', autoSaveDraft);
        document.getElementById('questionType').addEventListener('change', autoSaveDraft);
        
        // Clear draft on successful submission
        document.getElementById('questionForm').addEventListener('submit', function() {
            localStorage.removeItem('quiz_question_draft');
        });
        
        // Enhanced question modal show function
        const originalShowQuestionModal = showQuestionModal;
        showQuestionModal = function() {
            originalShowQuestionModal();
            setTimeout(loadDraft, 100);
        };
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Escape to close modals
            if (e.key === 'Escape') {
                closeQuestionModal();
                closeAnswerModal();
                
                const sidenav = document.getElementById('sidenav');
                const overlay = document.getElementById('overlay');
                
                if (sidenav.classList.contains('open')) {
                    sidenav.classList.remove('open');
                    overlay.classList.remove('active');
                    document.body.classList.remove('overflow-hidden');
                }
            }
            
            // Ctrl/Cmd + N for new question
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                showQuestionModal();
            }
            
            // Ctrl/Cmd + S to save order
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                const questionsList = document.getElementById('questionsList');
                if (questionsList && questionsList.children.length > 1) {
                    saveQuestionOrder();
                }
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
        
        // Debug function for troubleshooting
        function debugAjax(url) {
            console.log('Fetching URL:', url);
            fetch(url)
                .then(response => {
                    console.log('Response status:', response.status);
                    console.log('Response headers:', response.headers);
                    return response.text();
                })
                .then(text => {
                    console.log('Response text:', text);
                    try {
                        const json = JSON.parse(text);
                        console.log('Parsed JSON:', json);
                    } catch (e) {
                        console.log('Not valid JSON:', e);
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                });
        }
        
        console.log('Quiz Questions Manager with Edit functionality initialized successfully');
    </script>
</body>
</html>