<?php
// formation/take-quiz.php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = "take-quiz.php" . (isset($_GET['quiz_id']) ? "?quiz_id=" . $_GET['quiz_id'] : "");
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
$quiz_id = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;

if ($quiz_id <= 0) {
    header("Location: quiz.php");
    exit;
}

// Messages
$success_message = '';
$error_message = '';

// Traitement de soumission de quiz
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_quiz'])) {
    $answers = $_POST['answers'] ?? [];
    $time_taken = intval($_POST['time_taken'] ?? 0);
    
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
            } elseif ($question['question_type'] === 'short_answer') {
                // Réponse courte - pour l'instant on considère comme correct si une réponse est donnée
                if (!empty($user_answers[0])) {
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
        $insert_attempt_query = "INSERT INTO quiz_attempts (user_id, quiz_id, score, passed, completed_at, answers_data, time_taken) VALUES (?, ?, ?, ?, NOW(), ?, ?)";
        $answers_json = json_encode($answers);
        $stmt = $conn->prepare($insert_attempt_query);
        $stmt->bind_param("iiissi", $user_id, $quiz_id, $score, $passed, $answers_json, $time_taken);
        
        if ($stmt->execute()) {
            $attempt_id = $conn->insert_id;
            
            // Récupérer l'ID de la formation
            $formation_query = "SELECT formation_id FROM formation_quizzes WHERE id = ?";
            $stmt = $conn->prepare($formation_query);
            $stmt->bind_param("i", $quiz_id);
            $stmt->execute();
            $formation_result = $stmt->get_result()->fetch_assoc();
            $formation_id = $formation_result['formation_id'];
            
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
            
            // Rediriger vers la page de résultats
            header("Location: quiz-result.php?attempt_id=" . $attempt_id);
            exit;
        }
    }
}

// Vérifier l'accès au quiz
$access_query = "SELECT fq.*, f.title as formation_title, f.id as formation_id,
                 (SELECT COUNT(*) FROM quiz_attempts qa WHERE qa.quiz_id = fq.id AND qa.user_id = ?) as user_attempts,
                 (SELECT COUNT(*) FROM formation_subscriptions fs WHERE fs.user_id = ? AND fs.formation_id = f.id AND fs.status = 'active' AND fs.end_date >= CURDATE()) as has_access
                 FROM formation_quizzes fq
                 JOIN formations f ON fq.formation_id = f.id
                 WHERE fq.id = ? AND fq.is_active = 1";
$stmt = $conn->prepare($access_query);
$stmt->bind_param("iii", $user_id, $user_id, $quiz_id);
$stmt->execute();
$quiz_result = $stmt->get_result();
$quiz = $quiz_result->fetch_assoc();

if (!$quiz) {
    header("Location: quiz.php?error=quiz_not_found");
    exit;
}

if (!$quiz['has_access']) {
    header("Location: formations.php?error=access_denied");
    exit;
}

// Vérifier le nombre de tentatives
if ($quiz['max_attempts'] > 0 && $quiz['user_attempts'] >= $quiz['max_attempts']) {
    header("Location: quiz.php?error=max_attempts_reached");
    exit;
}

// Récupérer les questions du quiz
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
    <title>Quiz: <?php echo htmlspecialchars($quiz['title']); ?> - Netcrafter</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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
        
        /* Question cards */
        .question-card {
            transition: all 0.3s ease;
        }
        
        .question-card.current {
            transform: scale(1.02);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.15);
            border-color: #3B82F6;
        }
        
        /* Answer options */
        .answer-option {
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .answer-option:hover {
            background-color: #F3F4F6;
            border-color: #3B82F6;
        }
        
        .answer-option.selected {
            background-color: #EBF8FF;
            border-color: #3B82F6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        /* Progress bar */
        .progress-bar {
            transition: width 0.3s ease;
        }
        
        /* Timer */
        .timer {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .timer.warning {
            color: #EF4444;
            animation: pulse 1s infinite;
        }
        
        /* Navigation buttons */
        .nav-button {
            transition: all 0.2s ease;
        }
        
        .nav-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Question indicator */
        .question-indicator {
            transition: all 0.2s ease;
        }
        
        .question-indicator.current {
            background-color: #3B82F6;
            color: white;
        }
        
        .question-indicator.answered {
            background-color: #10B981;
            color: white;
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
    <header class="bg-white dark:bg-gray-800 shadow-sm sticky top-0 z-20">
        <div class="max-w-7xl mx-auto px-4 py-3">
            <div class="flex items-center justify-between">
                <!-- Logo -->
                <div class="flex items-center">
                    <img src="../image/logo-n.png" alt="Netcrafter Logo" class="h-8 mr-3">
                    <div>
                        <h1 class="text-lg font-bold text-gray-800 dark:text-white"><?php echo htmlspecialchars($quiz['title']); ?></h1>
                        <p class="text-sm text-gray-600 dark:text-gray-400"><?php echo htmlspecialchars($quiz['formation_title']); ?></p>
                    </div>
                </div>
                
                <!-- Timer and Progress -->
                <div class="flex items-center space-x-6">
                    <?php if ($quiz['time_limit'] > 0): ?>
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-clock text-gray-600 dark:text-gray-400"></i>
                        <span id="timer" class="font-mono text-lg font-bold timer"><?php echo sprintf('%02d:%02d', $quiz['time_limit'], 0); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="flex items-center space-x-2">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Progression:</span>
                        <div class="w-32 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div id="progressBar" class="progress-bar bg-netblue-600 h-2 rounded-full" style="width: 0%"></div>
                        </div>
                        <span id="progressText" class="text-sm font-medium">0/<?php echo count($questions); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Messages -->
    <?php if (!empty($success_message)): ?>
    <div class="max-w-7xl mx-auto px-4 mt-4">
        <div class="p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
            <i class="fas fa-check-circle mr-2"></i>
            <?php echo htmlspecialchars($success_message); ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
    <div class="max-w-7xl mx-auto px-4 mt-4">
        <div class="p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Main Content -->
    <main class="max-w-5xl mx-auto px-4 py-6">
        <?php if (empty($questions)): ?>
        <!-- No Questions -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-12 text-center">
            <i class="fas fa-question-circle text-6xl text-gray-400 mb-4"></i>
            <h3 class="text-xl font-bold mb-2 dark:text-white">Aucune question disponible</h3>
            <p class="text-gray-600 dark:text-gray-400 mb-6">Ce quiz ne contient pas encore de questions.</p>
            <a href="quiz.php?formation_id=<?php echo $quiz['formation_id']; ?>" class="bg-netblue-600 hover:bg-netblue-700 text-white px-6 py-3 rounded-lg transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>Retour aux quiz
            </a>
        </div>
        <?php else: ?>
        <!-- Quiz Form -->
        <form id="quizForm" method="POST" action="">
            <input type="hidden" name="submit_quiz" value="1">
            <input type="hidden" name="time_taken" id="timeTaken" value="0">
            
            <!-- Quiz Info -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-center">
                    <div>
                        <div class="text-2xl font-bold text-netblue-600 dark:text-netblue-400"><?php echo count($questions); ?></div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Questions</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-purple-600 dark:text-purple-400"><?php echo $quiz['passing_score']; ?>%</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Score requis</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400"><?php echo $quiz['time_limit'] ?: '∞'; ?></div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Minutes</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-green-600 dark:text-green-400"><?php echo $quiz['user_attempts'] + 1; ?></div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Tentative</div>
                    </div>
                </div>
            </div>
            
            <!-- Question Navigator -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 mb-6">
                <div class="flex flex-wrap gap-2">
                    <?php for ($i = 0; $i < count($questions); $i++): ?>
                    <button type="button" onclick="goToQuestion(<?php echo $i; ?>)" class="question-indicator w-10 h-10 rounded-full border-2 border-gray-300 dark:border-gray-600 flex items-center justify-center text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors <?php echo $i === 0 ? 'current' : ''; ?>" id="indicator-<?php echo $i; ?>">
                        <?php echo $i + 1; ?>
                    </button>
                    <?php endfor; ?>
                </div>
            </div>
            
            <!-- Questions -->
            <div id="questionsContainer">
                <?php foreach ($questions as $index => $question): ?>
                <div class="question-card bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6 border-2 border-transparent <?php echo $index === 0 ? 'current' : 'hidden'; ?>" id="question-<?php echo $index; ?>">
                    <div class="flex items-start justify-between mb-4">
                        <h3 class="text-lg font-bold dark:text-white">
                            Question <?php echo $index + 1; ?> / <?php echo count($questions); ?>
                        </h3>
                        <span class="px-3 py-1 text-sm font-semibold rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300">
                            <?php echo $question['points']; ?> point<?php echo $question['points'] > 1 ? 's' : ''; ?>
                        </span>
                    </div>
                    
                    <div class="mb-6">
                        <p class="text-gray-700 dark:text-gray-300 text-lg leading-relaxed"><?php echo htmlspecialchars($question['question']); ?></p>
                    </div>
                    
                    <div class="space-y-3">
                        <?php if ($question['question_type'] === 'short_answer'): ?>
                        <!-- Short Answer -->
                        <textarea name="answers[<?php echo $question['id']; ?>][]" rows="3" placeholder="Votre réponse..." class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500 resize-none"></textarea>
                        
                        <?php else: ?>
                        <!-- Multiple Choice / True-False / Multiple Select -->
                        <?php foreach ($question['answers'] as $answer): ?>
                        <div class="answer-option border-2 border-gray-200 dark:border-gray-600 rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-700" onclick="selectAnswer(this, <?php echo $question['id']; ?>, <?php echo $answer['id']; ?>, '<?php echo $question['question_type']; ?>')">
                            <label class="flex items-center cursor-pointer">
                                <input type="<?php echo $question['question_type'] === 'multiple_select' ? 'checkbox' : 'radio'; ?>" name="answers[<?php echo $question['id']; ?>]<?php echo $question['question_type'] === 'multiple_select' ? '[]' : ''; ?>" value="<?php echo $answer['id']; ?>" class="<?php echo $question['question_type'] === 'multiple_select' ? 'rounded' : 'rounded-full'; ?> border-gray-300 text-netblue-600 shadow-sm focus:border-netblue-300 focus:ring focus:ring-netblue-200 focus:ring-opacity-50 mr-3">
                                <span class="text-gray-700 dark:text-gray-300 flex-1"><?php echo htmlspecialchars($answer['answer_text']); ?></span>
                            </label>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Navigation -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <button type="button" id="prevBtn" onclick="previousQuestion()" class="nav-button bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg transition-colors disabled:opacity-50" disabled>
                        <i class="fas fa-arrow-left mr-2"></i>Précédent
                    </button>
                    
                    <div class="flex items-center space-x-4">
                        <button type="button" onclick="saveAndExit()" class="nav-button bg-yellow-500 hover:bg-yellow-600 text-white px-6 py-3 rounded-lg transition-colors">
                            <i class="fas fa-save mr-2"></i>Sauvegarder et quitter
                        </button>
                        <button type="button" id="nextBtn" onclick="nextQuestion()" class="nav-button bg-netblue-600 hover:bg-netblue-700 text-white px-6 py-3 rounded-lg transition-colors">
                            Suivant<i class="fas fa-arrow-right ml-2"></i>
                        </button>
                        <button type="button" id="submitBtn" onclick="confirmSubmit()" class="nav-button bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg transition-colors hidden">
                            <i class="fas fa-check mr-2"></i>Terminer le quiz
                        </button>
                    </div>
                </div>
            </div>
        </form>
        <?php endif; ?>
    </main>
    
    <!-- Confirmation Modal -->
    <div id="confirmModal" class="fixed inset-0 z-50 hidden">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
            
            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 dark:bg-yellow-900 sm:mx-0 sm:h-10 sm:w-10">
                            <i class="fas fa-exclamation-triangle text-yellow-600 dark:text-yellow-400"></i>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                                Confirmer la soumission
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500 dark:text-gray-400" id="confirmMessage">
                                    Êtes-vous sûr de vouloir terminer ce quiz ? Une fois soumis, vous ne pourrez plus modifier vos réponses.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" onclick="submitQuiz()" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                        Oui, terminer
                    </button>
                    <button type="button" onclick="closeConfirmModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
                        Annuler
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Variables globales
        let currentQuestion = 0;
        let totalQuestions = <?php echo count($questions); ?>;
        let timeLimit = <?php echo $quiz['time_limit'] ? $quiz['time_limit'] * 60 : 0; ?>; // en secondes
        let timeRemaining = timeLimit;
        let startTime = Date.now();
        let timerInterval;
        let answers = {};
        
        document.addEventListener('DOMContentLoaded', function() {
            // Initialiser le quiz
            initializeQuiz();
            
            // Démarrer le timer si nécessaire
            if (timeLimit > 0) {
                startTimer();
            }
            
            // Prévenir la fermeture accidentelle
            window.addEventListener('beforeunload', function(e) {
                if (!isQuizSubmitted) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });
            
            // Désactiver le clic droit et certaines touches
            document.addEventListener('contextmenu', function(e) {
                e.preventDefault();
            });
            
            document.addEventListener('keydown', function(e) {
                // Désactiver F12, Ctrl+Shift+I, Ctrl+U, etc.
                if (e.key === 'F12' || 
                    (e.ctrlKey && e.shiftKey && e.key === 'I') ||
                    (e.ctrlKey && e.shiftKey && e.key === 'C') ||
                    (e.ctrlKey && e.key === 'u')) {
                    e.preventDefault();
                }
                
                // Navigation avec les flèches
                if (e.key === 'ArrowLeft' && currentQuestion > 0) {
                    previousQuestion();
                } else if (e.key === 'ArrowRight' && currentQuestion < totalQuestions - 1) {
                    nextQuestion();
                }
            });
        });
        
        let isQuizSubmitted = false;
        
        function initializeQuiz() {
            updateProgress();
            updateNavigationButtons();
            
            // Charger les réponses sauvegardées (si implémenté)
            loadSavedAnswers();
        }
        
        function startTimer() {
            timerInterval = setInterval(function() {
                timeRemaining--;
                updateTimerDisplay();
                
                if (timeRemaining <= 300) { // 5 minutes restantes
                    document.getElementById('timer').classList.add('warning');
                }
                
                if (timeRemaining <= 0) {
                    clearInterval(timerInterval);
                    showNotification('Temps écoulé ! Le quiz va être soumis automatiquement.', 'warning');
                    setTimeout(function() {
                        submitQuiz();
                    }, 3000);
                }
            }, 1000);
        }
        
        function updateTimerDisplay() {
            const minutes = Math.floor(timeRemaining / 60);
            const seconds = timeRemaining % 60;
            document.getElementById('timer').textContent = 
                String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
        }
        
        function selectAnswer(element, questionId, answerId, questionType) {
            const input = element.querySelector('input');
            
            if (questionType === 'multiple_select') {
                // Pour les questions à choix multiples
                input.checked = !input.checked;
                element.classList.toggle('selected', input.checked);
            } else {
                // Pour les questions à choix unique
                // Désélectionner les autres réponses de la même question
                const questionCard = element.closest('.question-card');
                const otherOptions = questionCard.querySelectorAll('.answer-option');
                otherOptions.forEach(option => {
                    option.classList.remove('selected');
                    option.querySelector('input').checked = false;
                });
                
                // Sélectionner la réponse actuelle
                input.checked = true;
                element.classList.add('selected');
            }
            
            // Mettre à jour l'indicateur de question
            updateQuestionIndicator();
            
            // Sauvegarder la réponse
            saveAnswer(questionId, answerId, questionType);
        }
        
        function saveAnswer(questionId, answerId, questionType) {
            if (!answers[questionId]) {
                answers[questionId] = [];
            }
            
            if (questionType === 'multiple_select') {
                const index = answers[questionId].indexOf(answerId);
                if (index > -1) {
                    answers[questionId].splice(index, 1);
                } else {
                    answers[questionId].push(answerId);
                }
            } else {
                answers[questionId] = [answerId];
            }
        }
        
        function updateQuestionIndicator() {
            const currentCard = document.getElementById(`question-${currentQuestion}`);
            const indicator = document.getElementById(`indicator-${currentQuestion}`);
            
            // Vérifier si la question a été répondue
            const hasAnswer = currentCard.querySelector('input:checked') || 
                             (currentCard.querySelector('textarea') && currentCard.querySelector('textarea').value.trim());
            
            if (hasAnswer) {
                indicator.classList.add('answered');
                indicator.classList.remove('current');
            } else {
                indicator.classList.remove('answered');
                if (currentQuestion === getCurrentQuestionIndex()) {
                    indicator.classList.add('current');
                }
            }
        }
        
        function getCurrentQuestionIndex() {
            const currentCard = document.querySelector('.question-card.current');
            return Array.from(document.querySelectorAll('.question-card')).indexOf(currentCard);
        }
        
        function goToQuestion(index) {
            if (index >= 0 && index < totalQuestions) {
                // Masquer la question actuelle
                document.getElementById(`question-${currentQuestion}`).classList.add('hidden');
                document.getElementById(`question-${currentQuestion}`).classList.remove('current');
                document.getElementById(`indicator-${currentQuestion}`).classList.remove('current');
                
                // Afficher la nouvelle question
                currentQuestion = index;
                document.getElementById(`question-${currentQuestion}`).classList.remove('hidden');
                document.getElementById(`question-${currentQuestion}`).classList.add('current');
                document.getElementById(`indicator-${currentQuestion}`).classList.add('current');
                
                updateProgress();
                updateNavigationButtons();
                updateQuestionIndicator();
            }
        }
        
        function nextQuestion() {
            if (currentQuestion < totalQuestions - 1) {
                goToQuestion(currentQuestion + 1);
            }
        }
        
        function previousQuestion() {
            if (currentQuestion > 0) {
                goToQuestion(currentQuestion - 1);
            }
        }
        
        function updateProgress() {
            const progress = ((currentQuestion + 1) / totalQuestions) * 100;
            document.getElementById('progressBar').style.width = progress + '%';
            document.getElementById('progressText').textContent = `${currentQuestion + 1}/${totalQuestions}`;
        }
        
        function updateNavigationButtons() {
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            const submitBtn = document.getElementById('submitBtn');
            
            prevBtn.disabled = currentQuestion === 0;
            
            if (currentQuestion === totalQuestions - 1) {
                nextBtn.classList.add('hidden');
                submitBtn.classList.remove('hidden');
            } else {
                nextBtn.classList.remove('hidden');
                submitBtn.classList.add('hidden');
            }
        }
        
        function confirmSubmit() {
            // Compter les questions répondues
            const answeredQuestions = countAnsweredQuestions();
            const unansweredQuestions = totalQuestions - answeredQuestions;
            
            let message = `Êtes-vous sûr de vouloir terminer ce quiz ?<br><br>`;
            message += `Questions répondues: ${answeredQuestions}/${totalQuestions}`;
            
            if (unansweredQuestions > 0) {
                message += `<br><span class="text-red-600">Questions non répondues: ${unansweredQuestions}</span>`;
            }
            
            message += `<br><br>Une fois soumis, vous ne pourrez plus modifier vos réponses.`;
            
            document.getElementById('confirmMessage').innerHTML = message;
            document.getElementById('confirmModal').classList.remove('hidden');
        }
        
        function closeConfirmModal() {
            document.getElementById('confirmModal').classList.add('hidden');
        }
        
        function countAnsweredQuestions() {
            let count = 0;
            for (let i = 0; i < totalQuestions; i++) {
                const questionCard = document.getElementById(`question-${i}`);
                const hasAnswer = questionCard.querySelector('input:checked') || 
                                 (questionCard.querySelector('textarea') && questionCard.querySelector('textarea').value.trim());
                if (hasAnswer) count++;
            }
            return count;
        }
        
        function submitQuiz() {
            isQuizSubmitted = true;
            
            // Calculer le temps pris
            const timeTaken = Math.floor((Date.now() - startTime) / 1000);
            document.getElementById('timeTaken').value = timeTaken;
            
            // Arrêter le timer
            if (timerInterval) {
                clearInterval(timerInterval);
            }
            
            // Afficher un message de chargement
            showLoadingModal();
            
            // Soumettre le formulaire
            document.getElementById('quizForm').submit();
        }
        
        function saveAndExit() {
            if (confirm('Êtes-vous sûr de vouloir sauvegarder et quitter ? Vos réponses seront perdues.')) {
                // Implémenter la sauvegarde locale si nécessaire
                window.location.href = 'quiz.php?formation_id=<?php echo $quiz['formation_id']; ?>';
            }
        }
        
        function loadSavedAnswers() {
            // Implémenter le chargement des réponses sauvegardées depuis localStorage si nécessaire
            const savedAnswers = localStorage.getItem(`quiz_${<?php echo $quiz_id; ?>}_answers`);
            if (savedAnswers) {
                try {
                    const parsedAnswers = JSON.parse(savedAnswers);
                    // Restaurer les réponses
                    Object.keys(parsedAnswers).forEach(questionId => {
                        const answers = parsedAnswers[questionId];
                        answers.forEach(answerId => {
                            const input = document.querySelector(`input[name="answers[${questionId}]"][value="${answerId}"], input[name="answers[${questionId}][]"][value="${answerId}"]`);
                            if (input) {
                                input.checked = true;
                                input.closest('.answer-option').classList.add('selected');
                            }
                        });
                    });
                    
                    // Mettre à jour les indicateurs
                    for (let i = 0; i < totalQuestions; i++) {
                        currentQuestion = i;
                        updateQuestionIndicator();
                    }
                    currentQuestion = 0;
                } catch (e) {
                    console.error('Erreur lors du chargement des réponses sauvegardées:', e);
                }
            }
        }
        
        function showLoadingModal() {
            const loadingModal = document.createElement('div');
            loadingModal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50';
            loadingModal.innerHTML = `
                <div class="bg-white dark:bg-gray-800 rounded-lg p-8 text-center">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-netblue-600 mx-auto mb-4"></div>
                    <p class="text-lg font-medium dark:text-white">Soumission en cours...</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">Veuillez patienter, ne fermez pas cette page.</p>
                </div>
            `;
            document.body.appendChild(loadingModal);
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
        
        // Sauvegarder automatiquement les réponses toutes les 30 secondes
        setInterval(function() {
            if (!isQuizSubmitted) {
                const formData = new FormData(document.getElementById('quizForm'));
                const answersData = {};
                
                for (let [key, value] of formData.entries()) {
                    if (key.startsWith('answers[')) {
                        const match = key.match(/answers\[(\d+)\]/);
                        if (match) {
                            const questionId = match[1];
                            if (!answersData[questionId]) {
                                answersData[questionId] = [];
                            }
                            answersData[questionId].push(value);
                        }
                    }
                }
                
                localStorage.setItem(`quiz_${<?php echo $quiz_id; ?>}_answers`, JSON.stringify(answersData));
            }
        }, 30000);
        
        // Gérer la visibilité de la page (détection si l'utilisateur change d'onglet)
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                console.log('Utilisateur a quitté l\'onglet');
                // Vous pouvez implémenter une logique de surveillance ici
            } else {
                console.log('Utilisateur est revenu sur l\'onglet');
            }
        });
        
        console.log('Quiz taking interface initialized successfully');
    </script>
</body>
</html>