<?php
// formation/quiz-result.php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
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
$attempt_id = isset($_GET['attempt_id']) ? intval($_GET['attempt_id']) : 0;

if ($attempt_id <= 0) {
    header("Location: quiz.php");
    exit;
}

// Récupérer les détails de la tentative
$attempt_query = "SELECT qa.*, fq.title as quiz_title, fq.passing_score, fq.formation_id,
                  f.title as formation_title, f.cover_image,
                  u.firstname, u.lastname,
                  c.certificate_number, c.verification_code, c.certificate_url
                  FROM quiz_attempts qa
                  JOIN formation_quizzes fq ON qa.quiz_id = fq.id
                  JOIN formations f ON fq.formation_id = f.id
                  JOIN users u ON qa.user_id = u.id
                  LEFT JOIN certificates c ON qa.id = c.quiz_attempt_id
                  WHERE qa.id = ? AND qa.user_id = ?";
$stmt = $conn->prepare($attempt_query);
$stmt->bind_param("ii", $attempt_id, $user_id);
$stmt->execute();
$attempt_result = $stmt->get_result();
$attempt = $attempt_result->fetch_assoc();

if (!$attempt) {
    header("Location: quiz.php");
    exit;
}

// Récupérer les statistiques détaillées
$quiz_id = $attempt['quiz_id'];

// Récupérer toutes les questions avec les réponses de l'utilisateur
$questions_query = "SELECT qq.*, qa.answer_text, qa.is_correct
                   FROM quiz_questions qq
                   LEFT JOIN quiz_answers qa ON qq.id = qa.question_id
                   WHERE qq.quiz_id = ?
                   ORDER BY qq.order_number, qa.id";
$stmt = $conn->prepare($questions_query);
$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$questions_result = $stmt->get_result();

$questions = [];
$user_answers = json_decode($attempt['answers_data'], true) ?: [];

while ($row = $questions_result->fetch_assoc()) {
    $question_id = $row['id'];
    if (!isset($questions[$question_id])) {
        $questions[$question_id] = [
            'question' => $row,
            'answers' => [],
            'user_answers' => isset($user_answers[$question_id]) ? $user_answers[$question_id] : []
        ];
    }
    if ($row['answer_text']) {
        $questions[$question_id]['answers'][] = [
            'id' => $row['id'],
            'text' => $row['answer_text'],
            'is_correct' => $row['is_correct']
        ];
    }
}

// Calculer les statistiques détaillées
$total_questions = count($questions);
$correct_answers = 0;
$total_points = 0;
$earned_points = 0;

foreach ($questions as $question_data) {
    $question = $question_data['question'];
    $answers = $question_data['answers'];
    $user_question_answers = $question_data['user_answers'];
    
    // FIX: S'assurer que $user_question_answers est un tableau
    if (!is_array($user_question_answers)) {
        $user_question_answers = [];
    }
    
    $total_points += $question['points'];
    
    // Vérifier si la réponse de l'utilisateur est correcte
    $is_correct = false;
    
    if ($question['question_type'] === 'multiple_choice' || $question['question_type'] === 'true_false') {
        if (count($user_question_answers) === 1) {
            $selected_answer_id = $user_question_answers[0];
            foreach ($answers as $answer) {
                if ($answer['id'] == $selected_answer_id && $answer['is_correct']) {
                    $is_correct = true;
                    break;
                }
            }
        }
    } elseif ($question['question_type'] === 'multiple_select') {
        $correct_answer_ids = [];
        foreach ($answers as $answer) {
            if ($answer['is_correct']) {
                $correct_answer_ids[] = $answer['id'];
            }
        }
        
        // FIX: S'assurer que les deux sont des tableaux avant de les trier
        $user_answers_sorted = is_array($user_question_answers) ? $user_question_answers : [];
        sort($user_answers_sorted);
        sort($correct_answer_ids);
        
        if ($user_answers_sorted === $correct_answer_ids) {
            $is_correct = true;
        }
    } elseif ($question['question_type'] === 'short_answer') {
        // Pour les réponses courtes, considérer comme correct si une réponse a été donnée
        if (!empty($user_question_answers) && isset($user_question_answers[0]) && !empty($user_question_answers[0])) {
            $is_correct = true;
        }
    }
    
    if ($is_correct) {
        $correct_answers++;
        $earned_points += $question['points'];
    }
}

// Récupérer les statistiques générales de l'utilisateur pour ce quiz
$stats_query = "SELECT 
                COUNT(*) as total_attempts,
                MAX(score) as best_score,
                AVG(score) as avg_score,
                COUNT(CASE WHEN passed = 1 THEN 1 END) as passed_attempts
                FROM quiz_attempts 
                WHERE user_id = ? AND quiz_id = ?";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("ii", $user_id, $quiz_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Récupérer les informations de l'utilisateur
$user_query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr" class="light scroll">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Résultats du Quiz: <?php echo htmlspecialchars($attempt['quiz_title']); ?> - Netcrafter</title>
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
        
        /* Result cards */
        .result-card {
            transition: all 0.3s ease;
        }
        
        .result-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        /* Progress circles */
        .progress-circle {
            transition: stroke-dasharray 0.5s ease;
        }
        
        /* Confetti animation */
        @keyframes confetti {
            0% { transform: rotateZ(15deg) rotateY(0deg) translate(0, 0); }
            25% { transform: rotateZ(5deg) rotateY(180deg) translate(-5px, -25px); }
            50% { transform: rotateZ(15deg) rotateY(360deg) translate(5px, -50px); }
            75% { transform: rotateZ(5deg) rotateY(540deg) translate(-5px, -75px); }
            100% { transform: rotateZ(15deg) rotateY(720deg) translate(0, -100px); }
        }
        
        .confetti {
            position: absolute;
            width: 10px;
            height: 10px;
            background: #f39c12;
            animation: confetti 3s ease-out infinite;
        }
        
        .confetti:nth-child(1) { left: 10%; animation-delay: 0s; background: #e74c3c; }
        .confetti:nth-child(2) { left: 20%; animation-delay: 0.5s; background: #f39c12; }
        .confetti:nth-child(3) { left: 30%; animation-delay: 1s; background: #2ecc71; }
        .confetti:nth-child(4) { left: 40%; animation-delay: 1.5s; background: #3498db; }
        .confetti:nth-child(5) { left: 50%; animation-delay: 2s; background: #9b59b6; }
        .confetti:nth-child(6) { left: 60%; animation-delay: 2.5s; background: #e67e22; }
        .confetti:nth-child(7) { left: 70%; animation-delay: 3s; background: #1abc9c; }
        .confetti:nth-child(8) { left: 80%; animation-delay: 3.5s; background: #e91e63; }
        .confetti:nth-child(9) { left: 90%; animation-delay: 4s; background: #ff5722; }
        
        /* Question review */
        .question-review {
            transition: all 0.3s ease;
        }
        
        .question-correct {
            border-left: 4px solid #10B981;
            background-color: #F0FDF4;
        }
        
        .question-incorrect {
            border-left: 4px solid #EF4444;
            background-color: #FEF2F2;
        }
        
        .question-unanswered {
            border-left: 4px solid #F59E0B;
            background-color: #FFFBEB;
        }
        
        /* Certificate animation */
        .certificate-bounce {
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 53%, 80%, 100% {
                transform: translate3d(0,0,0);
            }
            40%, 43% {
                transform: translate3d(0, -30px, 0);
            }
            70% {
                transform: translate3d(0, -15px, 0);
            }
            90% {
                transform: translate3d(0, -4px, 0);
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
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-white transition-colors duration-300">
    <!-- Header -->
    <header class="bg-white dark:bg-gray-800 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <img src="../image/logo-n.png" alt="Netcrafter Logo" class="h-8 mr-3">
                    <div>
                        <h1 class="text-xl font-bold text-gray-800 dark:text-white">Résultats du Quiz</h1>
                        <p class="text-sm text-gray-600 dark:text-gray-400"><?php echo htmlspecialchars($attempt['quiz_title']); ?></p>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <a href="quiz.php?formation_id=<?php echo $attempt['formation_id']; ?>" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>Retour aux quiz
                    </a>
                    <?php if ($attempt['certificate_number']): ?>
                    <a href="<?php echo htmlspecialchars($attempt['certificate_url']); ?>" target="_blank" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors certificate-bounce">
                        <i class="fas fa-certificate mr-2"></i>Voir le certificat
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Confetti Animation (si réussi) -->
    <?php if ($attempt['passed']): ?>
    <div class="fixed inset-0 pointer-events-none z-10">
        <div class="confetti"></div>
        <div class="confetti"></div>
        <div class="confetti"></div>
        <div class="confetti"></div>
        <div class="confetti"></div>
        <div class="confetti"></div>
        <div class="confetti"></div>
        <div class="confetti"></div>
        <div class="confetti"></div>
    </div>
    <?php endif; ?>
    
    <!-- Main Content -->
    <main class="max-w-6xl mx-auto px-4 py-6">
        <!-- Result Summary -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-8 mb-8" data-aos="fade-up">
            <div class="text-center mb-8">
                <?php if ($attempt['passed']): ?>
                <div class="w-24 h-24 mx-auto mb-4 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center">
                    <i class="fas fa-trophy text-4xl text-green-600 dark:text-green-400"></i>
                </div>
                <h2 class="text-3xl font-bold text-green-600 dark:text-green-400 mb-2">Félicitations !</h2>
                <p class="text-gray-600 dark:text-gray-400">Vous avez réussi le quiz avec brio</p>
                <?php else: ?>
                <div class="w-24 h-24 mx-auto mb-4 bg-red-100 dark:bg-red-900 rounded-full flex items-center justify-center">
                    <i class="fas fa-times-circle text-4xl text-red-600 dark:text-red-400"></i>
                </div>
                <h2 class="text-3xl font-bold text-red-600 dark:text-red-400 mb-2">Quiz non réussi</h2>
                <p class="text-gray-600 dark:text-gray-400">Vous pouvez réessayer pour améliorer votre score</p>
                <?php endif; ?>
            </div>
            
            <!-- Score Circle -->
            <div class="flex justify-center mb-8">
                <div class="relative w-48 h-48">
                    <svg class="w-48 h-48 transform -rotate-90">
                        <circle cx="96" cy="96" r="88" stroke="currentColor" stroke-width="8" fill="none" class="text-gray-200 dark:text-gray-700"></circle>
                        <circle cx="96" cy="96" r="88" stroke="currentColor" stroke-width="8" fill="none" 
                                stroke-dasharray="553.4" 
                                stroke-dashoffset="<?php echo 553.4 - (553.4 * ($attempt['score'] / 100)); ?>" 
                                class="<?php echo $attempt['passed'] ? 'text-green-500' : 'text-red-500'; ?> progress-circle">
                        </circle>
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-4xl font-bold <?php echo $attempt['passed'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'; ?>">
                            <?php echo $attempt['score']; ?>%
                        </span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Score obtenu</span>
                    </div>
                </div>
            </div>
            
            <!-- Key Metrics -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="text-center">
                    <div class="text-2xl font-bold text-netblue-600 dark:text-netblue-400"><?php echo $correct_answers; ?>/<?php echo $total_questions; ?></div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Bonnes réponses</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-purple-600 dark:text-purple-400"><?php echo $earned_points; ?>/<?php echo $total_points; ?></div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Points obtenus</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400"><?php echo $attempt['passing_score']; ?>%</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Score requis</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-600 dark:text-gray-400">
                        <?php 
                        if ($attempt['time_taken']) {
                            $minutes = floor($attempt['time_taken'] / 60);
                            $seconds = $attempt['time_taken'] % 60;
                            echo sprintf('%02d:%02d', $minutes, $seconds);
                        } else {
                            echo '--:--';
                        }
                        ?>
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Temps écoulé</div>
                </div>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="result-card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up" data-aos-delay="100">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-redo text-blue-600 dark:text-blue-400"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-800 dark:text-white">Tentatives</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Total des essais</p>
                    </div>
                </div>
                <div class="text-2xl font-bold text-blue-600 dark:text-blue-400"><?php echo $stats['total_attempts']; ?></div>
            </div>
            
            <div class="result-card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up" data-aos-delay="200">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-green-100 dark:bg-green-900 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-star text-green-600 dark:text-green-400"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-800 dark:text-white">Meilleur score</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Score maximal</p>
                    </div>
                </div>
                <div class="text-2xl font-bold text-green-600 dark:text-green-400"><?php echo round($stats['best_score']); ?>%</div>
            </div>
            
            <div class="result-card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up" data-aos-delay="300">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-chart-line text-purple-600 dark:text-purple-400"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-800 dark:text-white">Score moyen</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Moyenne générale</p>
                    </div>
                </div>
                <div class="text-2xl font-bold text-purple-600 dark:text-purple-400"><?php echo round($stats['avg_score']); ?>%</div>
            </div>
        </div>
        
        <!-- Certificate Section -->
        <?php if ($attempt['certificate_number']): ?>
        <div class="bg-gradient-to-r from-green-400 to-green-600 rounded-xl shadow-lg p-8 mb-8 text-white" data-aos="fade-up">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-16 h-16 bg-white bg-opacity-20 rounded-lg flex items-center justify-center mr-6">
                        <i class="fas fa-certificate text-3xl"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold mb-2">Certificat généré !</h3>
                        <p class="text-green-100">Votre certificat de réussite a été généré avec succès</p>
                        <p class="text-sm text-green-200 mt-1">
                            Numéro: <?php echo htmlspecialchars($attempt['certificate_number']); ?>
                        </p>
                        <p class="text-sm text-green-200">
                            Code de vérification: <?php echo htmlspecialchars($attempt['verification_code']); ?>
                        </p>
                    </div>
                </div>
                <div class="text-right">
                    <a href="<?php echo htmlspecialchars($attempt['certificate_url']); ?>" target="_blank" class="bg-white text-green-600 px-6 py-3 rounded-lg font-bold hover:bg-green-50 transition-colors inline-flex items-center">
                        <i class="fas fa-download mr-2"></i>Télécharger
                    </a>
                    <p class="text-sm text-green-200 mt-2">Format PDF</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Question Review Toggle -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 mb-8" data-aos="fade-up">
            <button onclick="toggleQuestionReview()" class="w-full flex items-center justify-between text-left">
                <div>
                    <h3 class="text-lg font-bold dark:text-white">Révision des questions</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Voir le détail de vos réponses</p>
                </div>
                <i id="reviewToggleIcon" class="fas fa-chevron-down text-gray-400 transition-transform"></i>
            </button>
        </div>
        
        <!-- Question Review (Hidden by default) -->
        <div id="questionReview" class="hidden space-y-6" data-aos="fade-up">
            <?php foreach ($questions as $index => $question_data): ?>
            <?php 
            $question = $question_data['question'];
            $answers = $question_data['answers'];
            $user_question_answers = $question_data['user_answers'];
            
            // FIX: S'assurer que $user_question_answers est un tableau
            if (!is_array($user_question_answers)) {
                $user_question_answers = [];
            }
            
            // Déterminer si la réponse est correcte
            $is_correct = false;
            $is_answered = !empty($user_question_answers);
            
            if ($question['question_type'] === 'multiple_choice' || $question['question_type'] === 'true_false') {
                if (count($user_question_answers) === 1) {
                    $selected_answer_id = $user_question_answers[0];
                    foreach ($answers as $answer) {
                        if ($answer['id'] == $selected_answer_id && $answer['is_correct']) {
                            $is_correct = true;
                            break;
                        }
                    }
                }
            } elseif ($question['question_type'] === 'multiple_select') {
                $correct_answer_ids = [];
                foreach ($answers as $answer) {
                    if ($answer['is_correct']) {
                        $correct_answer_ids[] = $answer['id'];
                    }
                }
                
                // FIX: S'assurer que les deux sont des tableaux avant de les trier
                $user_answers_sorted = is_array($user_question_answers) ? $user_question_answers : [];
                sort($user_answers_sorted);
                sort($correct_answer_ids);
                
                if ($user_answers_sorted === $correct_answer_ids) {
                    $is_correct = true;
                }
            } elseif ($question['question_type'] === 'short_answer') {
                if (!empty($user_question_answers) && isset($user_question_answers[0]) && !empty($user_question_answers[0])) {
                    $is_correct = true;
                }
            }
            
            $card_class = '';
            $status_icon = '';
            $status_text = '';
            
            if (!$is_answered) {
                $card_class = 'question-unanswered';
                $status_icon = 'fas fa-exclamation-circle text-yellow-600';
                $status_text = 'Non répondue';
            } elseif ($is_correct) {
                $card_class = 'question-correct';
                $status_icon = 'fas fa-check-circle text-green-600';
                $status_text = 'Correcte';
            } else {
                $card_class = 'question-incorrect';
                $status_icon = 'fas fa-times-circle text-red-600';
                $status_text = 'Incorrecte';
            }
            ?>
            
            <div class="question-review bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 <?php echo $card_class; ?>">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex-1">
                        <div class="flex items-center mb-2">
                            <h4 class="text-lg font-bold dark:text-white mr-3">
                                Question <?php echo array_search($question_data, $questions) + 1; ?>
                            </h4>
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300">
                                <?php echo $question['points']; ?> pt<?php echo $question['points'] > 1 ? 's' : ''; ?>
                            </span>
                        </div>
                        <p class="text-gray-700 dark:text-gray-300 mb-4"><?php echo htmlspecialchars($question['question']); ?></p>
                    </div>
                    <div class="flex items-center ml-4">
                        <i class="<?php echo $status_icon; ?> mr-2"></i>
                        <span class="text-sm font-medium"><?php echo $status_text; ?></span>
                    </div>
                </div>
                
                <div class="space-y-3">
                    <?php if ($question['question_type'] === 'short_answer'): ?>
                    <!-- Short Answer -->
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Votre réponse:</p>
                        <p class="text-gray-800 dark:text-white">
                            <?php echo (!empty($user_question_answers) && isset($user_question_answers[0]) && !empty($user_question_answers[0])) ? htmlspecialchars($user_question_answers[0]) : '<em>Aucune réponse</em>'; ?>
                        </p>
                    </div>
                    
                    <?php else: ?>
                    <!-- Multiple Choice Options -->
                    <?php foreach ($answers as $answer): ?>
                    <?php 
                    $is_selected = in_array($answer['id'], $user_question_answers);
                    $option_class = '';
                    $option_icon = '';
                    
                    if ($answer['is_correct']) {
                        $option_class = 'bg-green-50 dark:bg-green-900/20 border-green-500';
                        $option_icon = 'fas fa-check text-green-600';
                    } elseif ($is_selected) {
                        $option_class = 'bg-red-50 dark:bg-red-900/20 border-red-500';
                        $option_icon = 'fas fa-times text-red-600';
                    } else {
                        $option_class = 'bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600';
                        $option_icon = 'fas fa-circle text-gray-400';
                    }
                    ?>
                    
                    <div class="border-2 rounded-lg p-4 <?php echo $option_class; ?>">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-800 dark:text-white flex-1"><?php echo htmlspecialchars($answer['text']); ?></span>
                            <div class="flex items-center space-x-2">
                                <?php if ($is_selected): ?>
                                <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded-full">Votre choix</span>
                                <?php endif; ?>
                                <?php if ($answer['is_correct']): ?>
                                <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded-full">Bonne réponse</span>
                                <?php endif; ?>
                                <i class="<?php echo $option_icon; ?>"></i>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Action Buttons -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 text-center" data-aos="fade-up">
            <div class="flex flex-col sm:flex-row items-center justify-center space-y-4 sm:space-y-0 sm:space-x-4">
                <a href="quiz.php?formation_id=<?php echo $attempt['formation_id']; ?>" class="bg-netblue-600 hover:bg-netblue-700 text-white px-8 py-3 rounded-lg transition-colors font-medium">
                    <i class="fas fa-list mr-2"></i>Voir tous les quiz
                </a>
                
                <?php if (!$attempt['passed']): ?>
                <a href="take-quiz.php?quiz_id=<?php echo $attempt['quiz_id']; ?>" class="bg-green-600 hover:bg-green-700 text-white px-8 py-3 rounded-lg transition-colors font-medium">
                    <i class="fas fa-redo mr-2"></i>Réessayer le quiz
                </a>
                <?php endif; ?>
                
                <a href="formation-details.php?id=<?php echo $attempt['formation_id']; ?>" class="bg-gray-500 hover:bg-gray-600 text-white px-8 py-3 rounded-lg transition-colors font-medium">
                    <i class="fas fa-graduation-cap mr-2"></i>Retour à la formation
                </a>
                
                <button onclick="shareResult()" class="bg-purple-600 hover:bg-purple-700 text-white px-8 py-3 rounded-lg transition-colors font-medium">
                    <i class="fas fa-share mr-2"></i>Partager
                </button>
            </div>
        </div>
    </main>
    
    <!-- Share Modal -->
    <div id="shareModal" class="fixed inset-0 z-50 hidden">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeShareModal()"></div>
            
            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
                                Partager votre résultat
                            </h3>
                            
                            <div class="space-y-4">
                                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Texte à partager:</p>
                                    <p id="shareText" class="text-gray-800 dark:text-white">
                                        🎉 Je viens de <?php echo $attempt['passed'] ? 'réussir' : 'terminer'; ?> le quiz "<?php echo htmlspecialchars($attempt['quiz_title']); ?>" avec un score de <?php echo $attempt['score']; ?>% ! 
                                        <?php if ($attempt['passed']): ?>🏆<?php endif; ?> #Netcrafter #Formation
                                    </p>
                                    <button onclick="copyShareText()" class="mt-2 text-sm text-netblue-600 hover:text-netblue-800">
                                        <i class="fas fa-copy mr-1"></i>Copier le texte
                                    </button>
                                </div>
                                
                                <div class="flex space-x-3">
                                    <button onclick="shareOnFacebook()" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                                        <i class="fab fa-facebook-f mr-2"></i>Facebook
                                    </button>
                                    <button onclick="shareOnTwitter()" class="flex-1 bg-blue-400 hover:bg-blue-500 text-white px-4 py-2 rounded-lg transition-colors">
                                        <i class="fab fa-twitter mr-2"></i>Twitter
                                    </button>
                                    <button onclick="shareOnLinkedIn()" class="flex-1 bg-blue-800 hover:bg-blue-900 text-white px-4 py-2 rounded-lg transition-colors">
                                        <i class="fab fa-linkedin mr-2"></i>LinkedIn
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" onclick="closeShareModal()" class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
                        Fermer
                    </button>
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
            // Animate progress circle
            setTimeout(function() {
                const progressCircle = document.querySelector('.progress-circle');
                if (progressCircle) {
                    progressCircle.style.transition = 'stroke-dashoffset 2s ease';
                }
            }, 500);
            
            // Show confetti animation if passed
            <?php if ($attempt['passed']): ?>
            setTimeout(function() {
                showConfetti();
            }, 1000);
            <?php endif; ?>
            
            // Clear any saved quiz data
            localStorage.removeItem('quiz_<?php echo $attempt['quiz_id']; ?>_answers');
        });
        
        function toggleQuestionReview() {
            const reviewSection = document.getElementById('questionReview');
            const toggleIcon = document.getElementById('reviewToggleIcon');
            
            if (reviewSection.classList.contains('hidden')) {
                reviewSection.classList.remove('hidden');
                toggleIcon.classList.add('rotate-180');
                
                // Re-initialize AOS for the newly shown elements
                setTimeout(function() {
                    AOS.refresh();
                }, 100);
            } else {
                reviewSection.classList.add('hidden');
                toggleIcon.classList.remove('rotate-180');
            }
        }
        
        function showConfetti() {
            // Additional confetti animation
            const colors = ['#e74c3c', '#f39c12', '#2ecc71', '#3498db', '#9b59b6', '#e67e22'];
            
            for (let i = 0; i < 20; i++) {
                setTimeout(function() {
                    createConfettiPiece();
                }, i * 100);
            }
        }
        
        function createConfettiPiece() {
            const confetti = document.createElement('div');
            confetti.style.position = 'fixed';
            confetti.style.width = '10px';
            confetti.style.height = '10px';
            confetti.style.backgroundColor = '#f39c12';
            confetti.style.left = Math.random() * 100 + '%';
            confetti.style.top = '-10px';
            confetti.style.zIndex = '1000';
            confetti.style.pointerEvents = 'none';
            
            document.body.appendChild(confetti);
            
            const animation = confetti.animate([
                { transform: 'translateY(-10px) rotate(0deg)', opacity: 1 },
                { transform: 'translateY(100vh) rotate(360deg)', opacity: 0 }
            ], {
                duration: 3000,
                easing: 'ease-out'
            });
            
            animation.onfinish = function() {
                document.body.removeChild(confetti);
            };
        }
        
        function shareResult() {
            document.getElementById('shareModal').classList.remove('hidden');
        }
        
        function closeShareModal() {
            document.getElementById('shareModal').classList.add('hidden');
        }
        
        function copyShareText() {
            const shareText = document.getElementById('shareText').textContent;
            navigator.clipboard.writeText(shareText).then(function() {
                showNotification('Texte copié dans le presse-papiers !', 'success');
            });
        }
        
        function shareOnFacebook() {
            const shareText = document.getElementById('shareText').textContent;
            const url = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(window.location.href) + '&quote=' + encodeURIComponent(shareText);
            window.open(url, '_blank', 'width=600,height=400');
        }
        
        function shareOnTwitter() {
            const shareText = document.getElementById('shareText').textContent;
            const url = 'https://twitter.com/intent/tweet?text=' + encodeURIComponent(shareText) + '&url=' + encodeURIComponent(window.location.href);
            window.open(url, '_blank', 'width=600,height=400');
        }
        
        function shareOnLinkedIn() {
            const shareText = document.getElementById('shareText').textContent;
            const url = 'https://www.linkedin.com/sharing/share-offsite/?url=' + encodeURIComponent(window.location.href);
            window.open(url, '_blank', 'width=600,height=400');
        }
        
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
        
        // Print result functionality
        function printResult() {
            window.print();
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Escape to close modals
            if (e.key === 'Escape') {
                closeShareModal();
            }
            
            // Ctrl/Cmd + P to print
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                printResult();
            }
        });
        
        console.log('Quiz Result Page initialized successfully');
    </script>
</body>
</html>