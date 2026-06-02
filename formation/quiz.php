<?php
// formation/quiz.php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = "quiz.php" . (isset($_GET['formation_id']) ? "?formation_id=" . $_GET['formation_id'] : "");
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

$user_id = $_SESSION['user_id'];
$formation_id = isset($_GET['formation_id']) ? intval($_GET['formation_id']) : 0;

// Messages
$success_message = '';
$error_message = '';

// Traitement de soumission de quiz
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_quiz'])) {
    $quiz_id = intval($_POST['quiz_id']);
    $answers = $_POST['answers'] ?? [];
    
    // Vérifier si l'utilisateur peut passer ce quiz
    $check_attempts_query = "SELECT COUNT(*) as attempts, fq.max_attempts 
                            FROM quiz_attempts qa 
                            RIGHT JOIN formation_quizzes fq ON qa.quiz_id = fq.id 
                            WHERE fq.id = ? AND (qa.user_id = ? OR qa.user_id IS NULL)
                            GROUP BY fq.id";
    $stmt = $conn->prepare($check_attempts_query);
    $stmt->bind_param("ii", $quiz_id, $user_id);
    $stmt->execute();
    $attempt_result = $stmt->get_result()->fetch_assoc();
    
    if ($attempt_result && $attempt_result['max_attempts'] > 0 && $attempt_result['attempts'] >= $attempt_result['max_attempts']) {
        $error_message = "Vous avez atteint le nombre maximum de tentatives pour ce quiz.";
    } else {
        // Calculer le score
        $total_points = 0;
        $earned_points = 0;
        
        // Récupérer toutes les questions du quiz
        $questions_query = "SELECT qq.*, qa.id as answer_id, qa.is_correct 
                           FROM quiz_questions qq 
                           LEFT JOIN quiz_answers qa ON qq.id = qa.question_id 
                           WHERE qq.quiz_id = ? 
                           ORDER BY qq.order_number, qa.id";
        $stmt = $conn->prepare($questions_query);
        $stmt->bind_param("i", $quiz_id);
        $stmt->execute();
        $questions_result = $stmt->get_result();
        
        $quiz_questions = [];
        while ($row = $questions_result->fetch_assoc()) {
            $question_id = $row['id'];
            if (!isset($quiz_questions[$question_id])) {
                $quiz_questions[$question_id] = [
                    'question' => $row,
                    'answers' => []
                ];
            }
            if ($row['answer_id']) {
                $quiz_questions[$question_id]['answers'][] = [
                    'id' => $row['answer_id'],
                    'is_correct' => $row['is_correct']
                ];
            }
        }
        
        // Calculer les points
        foreach ($quiz_questions as $question_id => $question_data) {
            $question = $question_data['question'];
            $question_answers = $question_data['answers'];
            $total_points += $question['points'];
            
            $user_answers = isset($answers[$question_id]) ? (array)$answers[$question_id] : [];
            
            if ($question['question_type'] === 'multiple_choice' || $question['question_type'] === 'true_false') {
                // Une seule réponse correcte
                if (count($user_answers) === 1) {
                    $selected_answer_id = $user_answers[0];
                    foreach ($question_answers as $answer) {
                        if ($answer['id'] == $selected_answer_id && $answer['is_correct']) {
                            $earned_points += $question['points'];
                            break;
                        }
                    }
                }
            } elseif ($question['question_type'] === 'multiple_select') {
                // Plusieurs réponses possibles
                $correct_answers = [];
                foreach ($question_answers as $answer) {
                    if ($answer['is_correct']) {
                        $correct_answers[] = $answer['id'];
                    }
                }
                
                sort($user_answers);
                sort($correct_answers);
                
                if ($user_answers === $correct_answers) {
                    $earned_points += $question['points'];
                }
            }
        }
        
        // Calculer le score en pourcentage
        $score = $total_points > 0 ? round(($earned_points / $total_points) * 100) : 0;
        
        // Vérifier si le quiz est réussi
        $quiz_info_query = "SELECT passing_score FROM formation_quizzes WHERE id = ?";
        $stmt = $conn->prepare($quiz_info_query);
        $stmt->bind_param("i", $quiz_id);
        $stmt->execute();
        $quiz_info = $stmt->get_result()->fetch_assoc();
        
        $passed = $score >= $quiz_info['passing_score'];
        
        // Enregistrer la tentative
        $insert_attempt_query = "INSERT INTO quiz_attempts (user_id, quiz_id, score, passed, completed_at, answers_data) VALUES (?, ?, ?, ?, NOW(), ?)";
        $answers_json = json_encode($answers);
        $stmt = $conn->prepare($insert_attempt_query);
        $stmt->bind_param("iiiss", $user_id, $quiz_id, $score, $passed, $answers_json);
        
        if ($stmt->execute()) {
            $attempt_id = $conn->insert_id;
            
            // Si le quiz est réussi, générer un certificat
            if ($passed) {
                $certificate_number = 'NC-' . date('Y') . '-' . str_pad($formation_id, 3, '0', STR_PAD_LEFT) . '-' . str_pad($user_id, 5, '0', STR_PAD_LEFT) . '-' . date('His');
                $verification_code = strtoupper(substr(md5($certificate_number . time()), 0, 8));
                
                $insert_certificate_query = "INSERT INTO certificates (user_id, formation_id, quiz_attempt_id, certificate_number, verification_code, certificate_url) VALUES (?, ?, ?, ?, ?, ?)";
                $certificate_url = "certificates/generate.php?code=" . $verification_code;
                $stmt = $conn->prepare($insert_certificate_query);
                $stmt->bind_param("iiisss", $user_id, $formation_id, $attempt_id, $certificate_number, $verification_code, $certificate_url);
                $stmt->execute();
                
                $success_message = "Félicitations ! Vous avez réussi le quiz avec un score de {$score}%. Votre certificat a été généré.";
            } else {
                $error_message = "Quiz terminé avec un score de {$score}%. Score requis: {$quiz_info['passing_score']}%. Vous pouvez réessayer.";
            }
        }
    }
}

// Récupérer les informations de l'utilisateur
$user_query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();

// Si formation_id est fourni, afficher les quiz de cette formation
if ($formation_id > 0) {
    // Vérifier que l'utilisateur est abonné à cette formation
    $subscription_query = "SELECT * FROM formation_subscriptions 
                          WHERE user_id = ? AND formation_id = ? 
                          AND status = 'active' AND end_date >= CURDATE()";
    $stmt = $conn->prepare($subscription_query);
    $stmt->bind_param("ii", $user_id, $formation_id);
    $stmt->execute();
    $subscription = $stmt->get_result()->fetch_assoc();
    
    if (!$subscription) {
        header("Location: formations.php?error=access_denied");
        exit;
    }
    
    // Récupérer les quiz de cette formation
    $quizzes_query = "SELECT fq.*, f.title as formation_title,
                     (SELECT COUNT(*) FROM quiz_attempts qa WHERE qa.quiz_id = fq.id AND qa.user_id = ?) as user_attempts,
                     (SELECT MAX(qa.score) FROM quiz_attempts qa WHERE qa.quiz_id = fq.id AND qa.user_id = ?) as best_score,
                     (SELECT COUNT(*) FROM quiz_attempts qa WHERE qa.quiz_id = fq.id AND qa.user_id = ? AND qa.passed = 1) as passed_attempts
                     FROM formation_quizzes fq
                     JOIN formations f ON fq.formation_id = f.id
                     WHERE fq.formation_id = ? AND fq.is_active = 1
                     ORDER BY fq.created_at ASC";
    $stmt = $conn->prepare($quizzes_query);
    $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $formation_id);
    $stmt->execute();
    $quizzes_result = $stmt->get_result();
    
    $quizzes = [];
    while ($row = $quizzes_result->fetch_assoc()) {
        $quizzes[] = $row;
    }
    
    // Récupérer les informations de la formation
    $formation_query = "SELECT f.*, c.name as category_name FROM formations f 
                       LEFT JOIN formation_categories c ON f.category_id = c.id 
                       WHERE f.id = ?";
    $stmt = $conn->prepare($formation_query);
    $stmt->bind_param("i", $formation_id);
    $stmt->execute();
    $formation = $stmt->get_result()->fetch_assoc();
} else {
    // Afficher tous les quiz disponibles pour les formations auxquelles l'utilisateur est abonné
    $quizzes_query = "SELECT fq.*, f.title as formation_title, c.name as category_name,
                     (SELECT COUNT(*) FROM quiz_attempts qa WHERE qa.quiz_id = fq.id AND qa.user_id = ?) as user_attempts,
                     (SELECT MAX(qa.score) FROM quiz_attempts qa WHERE qa.quiz_id = fq.id AND qa.user_id = ?) as best_score,
                     (SELECT COUNT(*) FROM quiz_attempts qa WHERE qa.quiz_id = fq.id AND qa.user_id = ? AND qa.passed = 1) as passed_attempts
                     FROM formation_quizzes fq
                     JOIN formations f ON fq.formation_id = f.id
                     LEFT JOIN formation_categories c ON f.category_id = c.id
                     JOIN formation_subscriptions fs ON f.id = fs.formation_id
                     WHERE fs.user_id = ? AND fs.status = 'active' AND fs.end_date >= CURDATE()
                     AND fq.is_active = 1
                     ORDER BY f.title, fq.created_at ASC";
    $stmt = $conn->prepare($quizzes_query);
    $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    $quizzes_result = $stmt->get_result();
    
    $quizzes = [];
    while ($row = $quizzes_result->fetch_assoc()) {
        $quizzes[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr" class="light scroll">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $formation_id ? 'Quiz - ' . htmlspecialchars($formation['title']) : 'Mes Quiz'; ?> - Netcrafter</title>
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
        
        /* Quiz cards */
        .quiz-card {
            transition: all 0.3s ease;
        }
        
        .quiz-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        /* Progress rings */
        .progress-ring {
            transition: stroke-dasharray 0.3s ease;
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
                    <a href="my-formations.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-graduation-cap w-6 text-center"></i>
                        <span class="ml-2 nav-text transition-opacity duration-300 truncate">Mes formations</span>
                    </a>
                </li>
                <li>
                    <a href="quiz.php" class="flex items-center px-3 py-2 text-base rounded-lg bg-netblue-100 dark:bg-netblue-900/30 text-netblue-800 dark:text-netblue-300">
                        <i class="fas fa-question-circle w-6 text-center"></i>
                        <span class="ml-2 nav-text transition-opacity duration-300 truncate">Quiz</span>
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
                <h1 class="text-xl font-bold text-gray-800 dark:text-white">
                    <?php echo $formation_id ? 'Quiz - ' . htmlspecialchars($formation['title']) : 'Mes Quiz'; ?>
                </h1>
                
                <!-- Right Menu -->
                <div class="flex items-center">
                    <?php if ($formation_id): ?>
                    <a href="formation-details.php?id=<?php echo $formation_id; ?>" class="bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-white px-4 py-2 rounded-lg transition-colors hidden md:block">
                        <i class="fas fa-arrow-left mr-2"></i>Retour à la formation
                    </a>
                    <?php else: ?>
                    <a href="formations.php" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded-lg transition-colors hidden md:block">
                        <i class="fas fa-search mr-2"></i>Explorer les formations
                    </a>
                    <?php endif; ?>
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
            <?php if ($formation_id && isset($formation)): ?>
            <!-- Formation Info -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6" data-aos="fade-up">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h2 class="text-2xl font-bold mb-2 dark:text-white"><?php echo htmlspecialchars($formation['title']); ?></h2>
                        <div class="flex items-center space-x-4 text-sm text-gray-600 dark:text-gray-400 mb-4">
                            <?php if ($formation['category_name']): ?>
                            <span><i class="fas fa-tag mr-1"></i><?php echo htmlspecialchars($formation['category_name']); ?></span>
                            <?php endif; ?>
                            <span><i class="fas fa-signal mr-1"></i><?php echo ucfirst($formation['level']); ?></span>
                            <span><i class="fas fa-question-circle mr-1"></i><?php echo count($quizzes); ?> quiz disponible<?php echo count($quizzes) > 1 ? 's' : ''; ?></span>
                        </div>
                        <?php if (!empty($formation['description'])): ?>
                        <p class="text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($formation['description']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Quiz List -->
            <div class="space-y-6" data-aos="fade-up">
                <?php if (empty($quizzes)): ?>
                <!-- Empty State -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-12 text-center">
                    <i class="fas fa-question-circle text-6xl text-gray-400 mb-4"></i>
                    <h3 class="text-xl font-bold mb-2 dark:text-white">Aucun quiz disponible</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-6">
                        <?php if ($formation_id): ?>
                            Aucun quiz n'est disponible pour cette formation pour le moment.
                        <?php else: ?>
                            Vous devez d'abord vous abonner à des formations pour accéder aux quiz.
                        <?php endif; ?>
                    </p>
                    <a href="formations.php" class="bg-netblue-600 hover:bg-netblue-700 text-white px-6 py-3 rounded-lg transition-colors">
                        <i class="fas fa-search mr-2"></i>Explorer les formations
                    </a>
                </div>
                <?php else: ?>
                <!-- Quiz Cards -->
                <?php foreach ($quizzes as $quiz): ?>
                <div class="quiz-card bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
                    <div class="p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex-1">
                                <h3 class="text-xl font-bold mb-2 dark:text-white"><?php echo htmlspecialchars($quiz['title']); ?></h3>
                                <div class="flex items-center space-x-4 text-sm text-gray-600 dark:text-gray-400 mb-3">
                                    <span><i class="fas fa-graduation-cap mr-1"></i><?php echo htmlspecialchars($quiz['formation_title']); ?></span>
                                    <?php if (!$formation_id && isset($quiz['category_name'])): ?>
                                    <span><i class="fas fa-tag mr-1"></i><?php echo htmlspecialchars($quiz['category_name']); ?></span>
                                    <?php endif; ?>
                                    <span><i class="fas fa-percentage mr-1"></i><?php echo $quiz['passing_score']; ?>% requis</span>
                                    <?php if ($quiz['time_limit']): ?>
                                    <span><i class="fas fa-clock mr-1"></i><?php echo $quiz['time_limit']; ?> min</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($quiz['description'])): ?>
                                <p class="text-gray-700 dark:text-gray-300 mb-4"><?php echo htmlspecialchars($quiz['description']); ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Progress Circle -->
                            <div class="ml-6 flex-shrink-0">
                                <div class="relative w-20 h-20">
                                    <svg class="w-20 h-20 transform -rotate-90">
                                        <circle cx="40" cy="40" r="36" stroke="currentColor" stroke-width="4" fill="none" class="text-gray-200 dark:text-gray-700"></circle>
                                        <circle cx="40" cy="40" r="36" stroke="currentColor" stroke-width="4" fill="none" 
                                                stroke-dasharray="226.19" 
                                                stroke-dashoffset="<?php echo $quiz['best_score'] ? 226.19 - (226.19 * ($quiz['best_score'] / 100)) : 226.19; ?>" 
                                                class="<?php echo $quiz['passed_attempts'] > 0 ? 'text-green-500' : ($quiz['best_score'] ? 'text-yellow-500' : 'text-gray-300'); ?> progress-ring">
                                        </circle>
                                    </svg>
                                    <div class="absolute inset-0 flex items-center justify-center">
                                        <span class="text-lg font-bold <?php echo $quiz['passed_attempts'] > 0 ? 'text-green-600 dark:text-green-400' : ($quiz['best_score'] ? 'text-yellow-600 dark:text-yellow-400' : 'text-gray-500'); ?>">
                                            <?php echo $quiz['best_score'] ?? 0; ?>%
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quiz Stats -->
                        <div class="grid grid-cols-3 gap-4 mb-4">
                            <div class="text-center bg-gray-50 dark:bg-gray-700 p-3 rounded-lg">
                                <div class="text-lg font-bold text-gray-800 dark:text-white"><?php echo $quiz['user_attempts']; ?></div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Tentatives</div>
                            </div>
                            <div class="text-center bg-gray-50 dark:bg-gray-700 p-3 rounded-lg">
                                <div class="text-lg font-bold <?php echo $quiz['best_score'] ? 'text-netblue-600 dark:text-netblue-400' : 'text-gray-500'; ?>">
                                    <?php echo $quiz['best_score'] ?? '-'; ?>%
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Meilleur score</div>
                            </div>
                            <div class="text-center bg-gray-50 dark:bg-gray-700 p-3 rounded-lg">
                                <div class="text-lg font-bold <?php echo $quiz['passed_attempts'] > 0 ? 'text-green-600 dark:text-green-400' : 'text-gray-500'; ?>">
                                    <?php echo $quiz['passed_attempts'] > 0 ? '✓' : '✗'; ?>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Réussi</div>
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                <?php if ($quiz['max_attempts'] > 0): ?>
                                    <?php $remaining = $quiz['max_attempts'] - $quiz['user_attempts']; ?>
                                    <?php if ($remaining > 0): ?>
                                        <span><?php echo $remaining; ?> tentative<?php echo $remaining > 1 ? 's' : ''; ?> restante<?php echo $remaining > 1 ? 's' : ''; ?></span>
                                    <?php else: ?>
                                        <span class="text-red-600 dark:text-red-400">Plus de tentatives disponibles</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span>Tentatives illimitées</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="flex items-center space-x-3">
                                <?php if ($quiz['user_attempts'] > 0): ?>
                                <button onclick="viewQuizHistory(<?php echo $quiz['id']; ?>)" class="text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200">
                                    <i class="fas fa-history mr-1"></i>Historique
                                </button>
                                <?php endif; ?>
                                
                                <?php if ($quiz['max_attempts'] == 0 || $quiz['user_attempts'] < $quiz['max_attempts']): ?>
                                <a href="take-quiz.php?quiz_id=<?php echo $quiz['id']; ?>" class="bg-netblue-600 hover:bg-netblue-700 text-white px-6 py-2 rounded-lg transition-colors">
                                    <i class="fas fa-play mr-2"></i>
                                    <?php echo $quiz['user_attempts'] > 0 ? 'Reprendre' : 'Commencer'; ?>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
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
    
    <!-- Quiz History Modal -->
    <div id="historyModal" class="fixed inset-0 z-50 hidden">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeHistoryModal()"></div>
            
            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full max-h-screen overflow-y-auto">
                <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                            Historique des tentatives
                        </h3>
                        <button onclick="closeHistoryModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    
                    <div id="historyContent">
                        <!-- Content will be loaded dynamically -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- AOS Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>

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
        
        // Make toggleMobileMenu function globally accessible
        function toggleMobileMenu() {
            const sidenav = document.getElementById('sidenav');
            const overlay = document.getElementById('overlay');
            
            sidenav.classList.toggle('open');
            overlay.classList.toggle('active');
            document.body.classList.toggle('overflow-hidden');
        }
        
        // Quiz history function
        function viewQuizHistory(quizId) {
            // For demonstration, show a sample history modal
            const historyContent = `
                <div class="space-y-4">
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-medium">Tentative #1</span>
                            <span class="text-sm text-gray-500">Il y a 2 jours</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-red-600 dark:text-red-400">Score: 65%</span>
                            <span class="text-red-600 dark:text-red-400">Échec</span>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-medium">Tentative #2</span>
                            <span class="text-sm text-gray-500">Il y a 1 jour</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-green-600 dark:text-green-400">Score: 85%</span>
                            <span class="text-green-600 dark:text-green-400">Réussi</span>
                        </div>
                    </div>
                    
                    <div class="text-center text-sm text-gray-500 dark:text-gray-400 mt-4">
                        <em>L'historique détaillé serait chargé via une API en production.</em>
                    </div>
                </div>
            `;
            
            document.getElementById('historyContent').innerHTML = historyContent;
            document.getElementById('historyModal').classList.remove('hidden');
        }
        
        function closeHistoryModal() {
            document.getElementById('historyModal').classList.add('hidden');
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
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Escape to close modals
            if (e.key === 'Escape') {
                closeHistoryModal();
                
                const sidenav = document.getElementById('sidenav');
                const overlay = document.getElementById('overlay');
                
                if (sidenav.classList.contains('open')) {
                    sidenav.classList.remove('open');
                    overlay.classList.remove('active');
                    document.body.classList.remove('overflow-hidden');
                }
            }
        });
        
        // Progress ring animation
        function animateProgressRings() {
            const progressRings = document.querySelectorAll('.progress-ring');
            progressRings.forEach(ring => {
                const value = parseFloat(ring.parentElement.querySelector('span').textContent);
                const circumference = 226.19;
                const offset = circumference - (value / 100) * circumference;
                
                // Animate from full circle to target
                ring.style.strokeDashoffset = circumference;
                setTimeout(() => {
                    ring.style.strokeDashoffset = offset;
                }, 500);
            });
        }
        
        // Initialize progress rings after page load
        window.addEventListener('load', animateProgressRings);
        
        console.log('User Quiz Page initialized successfully');
    </script>
</body>
</html>