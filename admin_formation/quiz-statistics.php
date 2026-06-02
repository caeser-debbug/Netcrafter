<?php
// admin_formation/quiz-statistics.php
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

// Paramètres de filtrage
$formation_filter = isset($_GET['formation']) ? intval($_GET['formation']) : 0;
$quiz_filter = isset($_GET['quiz']) ? intval($_GET['quiz']) : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Statistiques générales des quiz
$general_stats_query = "SELECT 
    COUNT(DISTINCT fq.id) as total_quizzes,
    COUNT(DISTINCT CASE WHEN fq.is_active = 1 THEN fq.id END) as active_quizzes,
    COUNT(qa.id) as total_attempts,
    COUNT(CASE WHEN qa.passed = 1 THEN qa.id END) as passed_attempts,
    AVG(qa.score) as avg_score,
    COUNT(DISTINCT qa.user_id) as unique_participants
    FROM formation_quizzes fq
    LEFT JOIN quiz_attempts qa ON fq.id = qa.quiz_id
    WHERE 1=1";

if ($formation_filter > 0) {
    $general_stats_query .= " AND fq.formation_id = $formation_filter";
}

if ($quiz_filter > 0) {
    $general_stats_query .= " AND fq.id = $quiz_filter";
}

$general_stats_query .= " AND (qa.completed_at IS NULL OR qa.completed_at BETWEEN '$date_from 00:00:00' AND '$date_to 23:59:59')";

$general_stats_result = $conn->query($general_stats_query);
$general_stats = $general_stats_result->fetch_assoc();

// Statistiques par quiz
$quiz_stats_query = "SELECT 
    fq.id,
    fq.title as quiz_title,
    f.title as formation_title,
    fq.passing_score,
    COUNT(qa.id) as total_attempts,
    COUNT(CASE WHEN qa.passed = 1 THEN qa.id END) as passed_attempts,
    AVG(qa.score) as avg_score,
    MIN(qa.score) as min_score,
    MAX(qa.score) as max_score,
    COUNT(DISTINCT qa.user_id) as unique_participants,
    AVG(qa.time_taken) as avg_time_taken
    FROM formation_quizzes fq
    JOIN formations f ON fq.formation_id = f.id
    LEFT JOIN quiz_attempts qa ON fq.id = qa.quiz_id 
        AND qa.completed_at BETWEEN '$date_from 00:00:00' AND '$date_to 23:59:59'
    WHERE fq.is_active = 1";

if ($formation_filter > 0) {
    $quiz_stats_query .= " AND fq.formation_id = $formation_filter";
}

if ($quiz_filter > 0) {
    $quiz_stats_query .= " AND fq.id = $quiz_filter";
}

$quiz_stats_query .= " GROUP BY fq.id ORDER BY f.title, fq.title";

$quiz_stats_result = $conn->query($quiz_stats_query);
$quiz_stats = [];
while ($row = $quiz_stats_result->fetch_assoc()) {
    $quiz_stats[] = $row;
}

// Top performers
$top_performers_query = "SELECT 
    u.firstname,
    u.lastname,
    u.phone,
    COUNT(qa.id) as total_attempts,
    COUNT(CASE WHEN qa.passed = 1 THEN qa.id END) as passed_attempts,
    AVG(qa.score) as avg_score,
    MAX(qa.score) as best_score
    FROM users u
    JOIN quiz_attempts qa ON u.id = qa.user_id
    JOIN formation_quizzes fq ON qa.quiz_id = fq.id
    WHERE qa.completed_at BETWEEN '$date_from 00:00:00' AND '$date_to 23:59:59'";

if ($formation_filter > 0) {
    $top_performers_query .= " AND fq.formation_id = $formation_filter";
}

if ($quiz_filter > 0) {
    $top_performers_query .= " AND fq.id = $quiz_filter";
}

$top_performers_query .= " GROUP BY u.id 
    HAVING COUNT(CASE WHEN qa.passed = 1 THEN qa.id END) > 0
    ORDER BY AVG(qa.score) DESC, COUNT(CASE WHEN qa.passed = 1 THEN qa.id END) DESC 
    LIMIT 10";

$top_performers_result = $conn->query($top_performers_query);
$top_performers = [];
while ($row = $top_performers_result->fetch_assoc()) {
    $top_performers[] = $row;
}

// Données pour les graphiques
$daily_attempts_query = "SELECT 
    DATE(qa.completed_at) as attempt_date,
    COUNT(qa.id) as total_attempts,
    COUNT(CASE WHEN qa.passed = 1 THEN qa.id END) as passed_attempts
    FROM quiz_attempts qa
    JOIN formation_quizzes fq ON qa.quiz_id = fq.id
    WHERE qa.completed_at BETWEEN '$date_from 00:00:00' AND '$date_to 23:59:59'";

if ($formation_filter > 0) {
    $daily_attempts_query .= " AND fq.formation_id = $formation_filter";
}

if ($quiz_filter > 0) {
    $daily_attempts_query .= " AND fq.id = $quiz_filter";
}

$daily_attempts_query .= " GROUP BY DATE(qa.completed_at) ORDER BY attempt_date";

$daily_attempts_result = $conn->query($daily_attempts_query);
$daily_attempts = [];
while ($row = $daily_attempts_result->fetch_assoc()) {
    $daily_attempts[] = $row;
}

// Distribution des scores
$score_distribution_query = "SELECT 
    CASE 
        WHEN qa.score >= 90 THEN '90-100%'
        WHEN qa.score >= 80 THEN '80-89%'
        WHEN qa.score >= 70 THEN '70-79%'
        WHEN qa.score >= 60 THEN '60-69%'
        WHEN qa.score >= 50 THEN '50-59%'
        ELSE '0-49%'
    END as score_range,
    COUNT(qa.id) as count
    FROM quiz_attempts qa
    JOIN formation_quizzes fq ON qa.quiz_id = fq.id
    WHERE qa.completed_at BETWEEN '$date_from 00:00:00' AND '$date_to 23:59:59'";

if ($formation_filter > 0) {
    $score_distribution_query .= " AND fq.formation_id = $formation_filter";
}

if ($quiz_filter > 0) {
    $score_distribution_query .= " AND fq.id = $quiz_filter";
}

$score_distribution_query .= " GROUP BY score_range ORDER BY 
    CASE 
        WHEN score_range = '90-100%' THEN 1
        WHEN score_range = '80-89%' THEN 2
        WHEN score_range = '70-79%' THEN 3
        WHEN score_range = '60-69%' THEN 4
        WHEN score_range = '50-59%' THEN 5
        ELSE 6
    END";

$score_distribution_result = $conn->query($score_distribution_query);
$score_distribution = [];
while ($row = $score_distribution_result->fetch_assoc()) {
    $score_distribution[] = $row;
}

// Récupérer les formations pour les filtres
$formations_query = "SELECT id, title FROM formations WHERE status = 'active' ORDER BY title";
$formations_result = $conn->query($formations_query);
$formations = [];
while ($row = $formations_result->fetch_assoc()) {
    $formations[] = $row;
}

// Récupérer les quiz pour les filtres
$quizzes_query = "SELECT fq.id, fq.title, f.title as formation_title 
                 FROM formation_quizzes fq 
                 JOIN formations f ON fq.formation_id = f.id 
                 WHERE fq.is_active = 1";
if ($formation_filter > 0) {
    $quizzes_query .= " AND fq.formation_id = $formation_filter";
}
$quizzes_query .= " ORDER BY f.title, fq.title";

$quizzes_result = $conn->query($quizzes_query);
$quizzes = [];
while ($row = $quizzes_result->fetch_assoc()) {
    $quizzes[] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr" class="light scroll">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques des Quiz - Admin Netcrafter</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        /* Custom styles similar to other admin pages */
        .stats-card {
            transition: all 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .chart-container {
            position: relative;
            height: 400px;
        }
        
        .progress-bar {
            transition: width 0.5s ease;
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
<body class="bg-gray-50 dark:bg-gray-900">
    <!-- Header -->
    <header class="bg-white dark:bg-gray-800 shadow-sm sticky top-0 z-20">
        <div class="max-w-7xl mx-auto px-4 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <img src="../image/logo-n.png" alt="Netcrafter Logo" class="h-8 mr-3">
                    <h1 class="text-xl font-bold text-gray-800 dark:text-white">Statistiques des Quiz</h1>
                </div>
                
                <div class="flex items-center space-x-3">
                    <a href="quiz.php" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>Retour aux quiz
                    </a>
                    <button onclick="exportStatistics()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-download mr-2"></i>Exporter
                    </button>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 py-6">
        <!-- Filters -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 mb-8">
            <form method="GET" action="quiz-statistics.php" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Formation</label>
                    <select name="formation" onchange="this.form.submit()" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white">
                        <option value="0">Toutes les formations</option>
                        <?php foreach ($formations as $formation): ?>
                        <option value="<?php echo $formation['id']; ?>" <?php echo $formation_filter == $formation['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($formation['title']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Quiz</label>
                    <select name="quiz" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white">
                        <option value="0">Tous les quiz</option>
                        <?php foreach ($quizzes as $quiz): ?>
                        <option value="<?php echo $quiz['id']; ?>" <?php echo $quiz_filter == $quiz['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($quiz['formation_title'] . ' - ' . $quiz['title']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Date début</label>
                    <input type="date" name="date_from" value="<?php echo $date_from; ?>" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Date fin</label>
                    <input type="date" name="date_to" value="<?php echo $date_to; ?>" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white">
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-search mr-2"></i>Filtrer
                    </button>
                </div>
            </form>
        </div>
        
        <!-- General Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-6 mb-8">
            <div class="stats-card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-question-circle text-blue-600 dark:text-blue-400 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo number_format($general_stats['total_quizzes']); ?></h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Quiz Total</p>
                    </div>
                </div>
            </div>
            
            <div class="stats-card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-green-100 dark:bg-green-900 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-check-circle text-green-600 dark:text-green-400 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo number_format($general_stats['active_quizzes']); ?></h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Quiz Actifs</p>
                    </div>
                </div>
            </div>
            
            <div class="stats-card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-users text-purple-600 dark:text-purple-400 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo number_format($general_stats['total_attempts']); ?></h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Tentatives</p>
                    </div>
                </div>
            </div>
            
            <div class="stats-card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-yellow-100 dark:bg-yellow-900 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-trophy text-yellow-600 dark:text-yellow-400 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo number_format($general_stats['passed_attempts']); ?></h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Réussites</p>
                    </div>
                </div>
            </div>
            
            <div class="stats-card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-indigo-100 dark:bg-indigo-900 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-chart-line text-indigo-600 dark:text-indigo-400 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo round($general_stats['avg_score'] ?? 0); ?>%</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Score Moyen</p>
                    </div>
                </div>
            </div>
            
            <div class="stats-card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-red-100 dark:bg-red-900 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-user-friends text-red-600 dark:text-red-400 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo number_format($general_stats['unique_participants']); ?></h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Participants</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts Row -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Daily Attempts Chart -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Tentatives par jour</h3>
                <div class="chart-container">
                    <canvas id="dailyAttemptsChart"></canvas>
                </div>
            </div>
            
            <!-- Score Distribution Chart -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Distribution des scores</h3>
                <div class="chart-container">
                    <canvas id="scoreDistributionChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Quiz Statistics Table -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 mb-8">
            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Statistiques par quiz</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="text-left py-3 px-4 font-medium text-gray-800 dark:text-white">Quiz</th>
                            <th class="text-left py-3 px-4 font-medium text-gray-800 dark:text-white">Formation</th>
                            <th class="text-center py-3 px-4 font-medium text-gray-800 dark:text-white">Tentatives</th>
                            <th class="text-center py-3 px-4 font-medium text-gray-800 dark:text-white">Réussites</th>
                            <th class="text-center py-3 px-4 font-medium text-gray-800 dark:text-white">Taux</th>
                            <th class="text-center py-3 px-4 font-medium text-gray-800 dark:text-white">Score Moy.</th>
                            <th class="text-center py-3 px-4 font-medium text-gray-800 dark:text-white">Temps Moy.</th>
                            <th class="text-center py-3 px-4 font-medium text-gray-800 dark:text-white">Participants</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($quiz_stats)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-8 text-gray-500 dark:text-gray-400">
                                <i class="fas fa-inbox text-3xl mb-2"></i>
                                <p>Aucune donnée disponible pour la période sélectionnée</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($quiz_stats as $quiz): ?>
                        <tr class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="py-3 px-4">
                                <div class="font-medium text-gray-800 dark:text-white"><?php echo htmlspecialchars($quiz['quiz_title']); ?></div>
                                <div class="text-xs text-gray-500">Score requis: <?php echo $quiz['passing_score']; ?>%</div>
                            </td>
                            <td class="py-3 px-4 text-gray-600 dark:text-gray-400"><?php echo htmlspecialchars($quiz['formation_title']); ?></td>
                            <td class="py-3 px-4 text-center font-medium"><?php echo number_format($quiz['total_attempts']); ?></td>
                            <td class="py-3 px-4 text-center font-medium text-green-600"><?php echo number_format($quiz['passed_attempts']); ?></td>
                            <td class="py-3 px-4 text-center">
                                <?php 
                                $success_rate = $quiz['total_attempts'] > 0 ? ($quiz['passed_attempts'] / $quiz['total_attempts']) * 100 : 0;
                                $rate_class = $success_rate >= 70 ? 'text-green-600' : ($success_rate >= 50 ? 'text-yellow-600' : 'text-red-600');
                                ?>
                                <span class="font-medium <?php echo $rate_class; ?>"><?php echo round($success_rate); ?>%</span>
                            </td>
                            <td class="py-3 px-4 text-center font-medium"><?php echo round($quiz['avg_score'] ?? 0); ?>%</td>
                            <td class="py-3 px-4 text-center">
                                <?php 
                                if ($quiz['avg_time_taken'] > 0) {
                                    $minutes = floor($quiz['avg_time_taken'] / 60);
                                    $seconds = $quiz['avg_time_taken'] % 60;
                                    echo sprintf('%02d:%02d', $minutes, $seconds);
                                } else {
                                    echo '--:--';
                                }
                                ?>
                            </td>
                            <td class="py-3 px-4 text-center font-medium"><?php echo number_format($quiz['unique_participants']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Top Performers -->
        <?php if (!empty($top_performers)): ?>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">
                <i class="fas fa-trophy text-yellow-500 mr-2"></i>Top Performers
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach (array_slice($top_performers, 0, 6) as $index => $performer): ?>
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3 <?php echo $index === 0 ? 'bg-yellow-500 text-white' : ($index === 1 ? 'bg-gray-400 text-white' : ($index === 2 ? 'bg-orange-500 text-white' : 'bg-blue-500 text-white')); ?>">
                                <?php echo $index + 1; ?>
                            </div>
                            <div>
                                <h4 class="font-medium text-gray-800 dark:text-white">
                                    <?php echo htmlspecialchars($performer['firstname'] . ' ' . $performer['lastname']); ?>
                                </h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($performer['phone']); ?></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-lg font-bold text-green-600 dark:text-green-400"><?php echo round($performer['avg_score']); ?>%</div>
                            <div class="text-xs text-gray-500">Score moyen</div>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-center text-xs">
                        <div>
                            <div class="font-medium text-gray-800 dark:text-white"><?php echo $performer['total_attempts']; ?></div>
                            <div class="text-gray-500">Tentatives</div>
                        </div>
                        <div>
                            <div class="font-medium text-green-600"><?php echo $performer['passed_attempts']; ?></div>
                            <div class="text-gray-500">Réussites</div>
                        </div>
                        <div>
                            <div class="font-medium text-blue-600"><?php echo round($performer['best_score']); ?>%</div>
                            <div class="text-gray-500">Meilleur</div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <script>
        // Chart.js configurations
        const chartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)',
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)',
                    }
                }
            }
        };

        // Daily Attempts Chart
        const dailyAttemptsData = <?php echo json_encode($daily_attempts); ?>;
        const dailyCtx = document.getElementById('dailyAttemptsChart').getContext('2d');
        
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: dailyAttemptsData.map(item => {
                    const date = new Date(item.attempt_date);
                    return date.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' });
                }),
                datasets: [
                    {
                        label: 'Total des tentatives',
                        data: dailyAttemptsData.map(item => item.total_attempts),
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Tentatives réussies',
                        data: dailyAttemptsData.map(item => item.passed_attempts),
                        borderColor: 'rgb(16, 185, 129)',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: chartOptions
        });

        // Score Distribution Chart
        const scoreDistributionData = <?php echo json_encode($score_distribution); ?>;
        const scoreCtx = document.getElementById('scoreDistributionChart').getContext('2d');
        
        new Chart(scoreCtx, {
            type: 'doughnut',
            data: {
                labels: scoreDistributionData.map(item => item.score_range),
                datasets: [{
                    data: scoreDistributionData.map(item => item.count),
                    backgroundColor: [
                        '#10B981', // 90-100%
                        '#34D399', // 80-89%
                        '#FBBF24', // 70-79%
                        '#FB923C', // 60-69%
                        '#F87171', // 50-59%
                        '#EF4444'  // 0-49%
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                }
            }
        });

        // Export functionality
        function exportStatistics() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            
            // Create a temporary link to download CSV
            const link = document.createElement('a');
            link.href = 'export-quiz-stats.php?' + params.toString();
            link.download = 'quiz-statistics.csv';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Auto-refresh data every 5 minutes
        setInterval(function() {
            if (document.visibilityState === 'visible') {
                location.reload();
            }
        }, 300000);

        console.log('Quiz Statistics Dashboard initialized successfully');
    </script>
</body>
</html>