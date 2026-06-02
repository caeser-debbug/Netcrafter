<?php
// admin_formation/export-quiz-stats.php
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

// Récupérer les paramètres de filtrage
$formation_filter = isset($_GET['formation']) ? intval($_GET['formation']) : 0;
$quiz_filter = isset($_GET['quiz']) ? intval($_GET['quiz']) : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Préparer les en-têtes pour le téléchargement CSV
$filename = 'quiz-statistics-' . date('Y-m-d-H-i-s') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Créer le flux de sortie
$output = fopen('php://output', 'w');

// BOM UTF-8 pour Excel
fwrite($output, "\xEF\xBB\xBF");

// En-têtes du CSV
fputcsv($output, [
    'Quiz',
    'Formation',
    'Catégorie',
    'Score requis (%)',
    'Temps limite (min)',
    'Max tentatives',
    'Total tentatives',
    'Tentatives réussies',
    'Taux de réussite (%)',
    'Score minimum',
    'Score maximum', 
    'Score moyen',
    'Temps moyen (secondes)',
    'Participants uniques',
    'Première tentative',
    'Dernière tentative',
    'Statut'
], ';');

// Requête pour récupérer les données détaillées
$export_query = "SELECT 
    fq.title as quiz_title,
    f.title as formation_title,
    c.name as category_name,
    fq.passing_score,
    fq.time_limit,
    fq.max_attempts,
    COUNT(qa.id) as total_attempts,
    COUNT(CASE WHEN qa.passed = 1 THEN qa.id END) as passed_attempts,
    CASE 
        WHEN COUNT(qa.id) > 0 THEN ROUND((COUNT(CASE WHEN qa.passed = 1 THEN qa.id END) / COUNT(qa.id)) * 100, 2)
        ELSE 0 
    END as success_rate,
    MIN(qa.score) as min_score,
    MAX(qa.score) as max_score,
    ROUND(AVG(qa.score), 2) as avg_score,
    ROUND(AVG(qa.time_taken), 0) as avg_time_taken,
    COUNT(DISTINCT qa.user_id) as unique_participants,
    MIN(qa.completed_at) as first_attempt,
    MAX(qa.completed_at) as last_attempt,
    CASE WHEN fq.is_active = 1 THEN 'Actif' ELSE 'Inactif' END as status
    FROM formation_quizzes fq
    JOIN formations f ON fq.formation_id = f.id
    LEFT JOIN formation_categories c ON f.category_id = c.id
    LEFT JOIN quiz_attempts qa ON fq.id = qa.quiz_id 
        AND qa.completed_at BETWEEN '$date_from 00:00:00' AND '$date_to 23:59:59'
    WHERE 1=1";

if ($formation_filter > 0) {
    $export_query .= " AND fq.formation_id = $formation_filter";
}

if ($quiz_filter > 0) {
    $export_query .= " AND fq.id = $quiz_filter";
}

$export_query .= " GROUP BY fq.id ORDER BY f.title, fq.title";

$export_result = $conn->query($export_query);

// Écrire les données
while ($row = $export_result->fetch_assoc()) {
    fputcsv($output, [
        $row['quiz_title'],
        $row['formation_title'],
        $row['category_name'] ?: 'N/A',
        $row['passing_score'],
        $row['time_limit'] ?: 'Illimité',
        $row['max_attempts'] ?: 'Illimité',
        $row['total_attempts'],
        $row['passed_attempts'],
        $row['success_rate'],
        $row['min_score'] ?: 'N/A',
        $row['max_score'] ?: 'N/A',
        $row['avg_score'] ?: 'N/A',
        $row['avg_time_taken'] ?: 'N/A',
        $row['unique_participants'],
        $row['first_attempt'] ? date('d/m/Y H:i', strtotime($row['first_attempt'])) : 'N/A',
        $row['last_attempt'] ? date('d/m/Y H:i', strtotime($row['last_attempt'])) : 'N/A',
        $row['status']
    ], ';');
}

// Ajouter une section séparée pour les détails des tentatives si demandé
if (isset($_GET['include_attempts']) && $_GET['include_attempts'] == '1') {
    // Ligne vide de séparation
    fputcsv($output, [], ';');
    fputcsv($output, ['=== DÉTAILS DES TENTATIVES ==='], ';');
    fputcsv($output, [], ';');
    
    // En-têtes pour les tentatives
    fputcsv($output, [
        'Utilisateur',
        'Téléphone',
        'Quiz',
        'Formation',
        'Score (%)',
        'Résultat',
        'Temps pris (min:sec)',
        'Date tentative',
        'Numéro tentative'
    ], ';');
    
    // Requête pour les tentatives détaillées
    $attempts_query = "SELECT 
        CONCAT(u.firstname, ' ', u.lastname) as user_name,
        u.phone,
        fq.title as quiz_title,
        f.title as formation_title,
        qa.score,
        CASE WHEN qa.passed = 1 THEN 'Réussi' ELSE 'Échoué' END as result,
        qa.time_taken,
        qa.completed_at,
        ROW_NUMBER() OVER (PARTITION BY qa.user_id, qa.quiz_id ORDER BY qa.completed_at) as attempt_number
        FROM quiz_attempts qa
        JOIN users u ON qa.user_id = u.id
        JOIN formation_quizzes fq ON qa.quiz_id = fq.id
        JOIN formations f ON fq.formation_id = f.id
        WHERE qa.completed_at BETWEEN '$date_from 00:00:00' AND '$date_to 23:59:59'";
    
    if ($formation_filter > 0) {
        $attempts_query .= " AND fq.formation_id = $formation_filter";
    }
    
    if ($quiz_filter > 0) {
        $attempts_query .= " AND fq.id = $quiz_filter";
    }
    
    $attempts_query .= " ORDER BY qa.completed_at DESC";
    
    $attempts_result = $conn->query($attempts_query);
    
    while ($attempt = $attempts_result->fetch_assoc()) {
        $time_formatted = 'N/A';
        if ($attempt['time_taken'] > 0) {
            $minutes = floor($attempt['time_taken'] / 60);
            $seconds = $attempt['time_taken'] % 60;
            $time_formatted = sprintf('%02d:%02d', $minutes, $seconds);
        }
        
        fputcsv($output, [
            $attempt['user_name'],
            $attempt['phone'],
            $attempt['quiz_title'],
            $attempt['formation_title'],
            $attempt['score'],
            $attempt['result'],
            $time_formatted,
            date('d/m/Y H:i:s', strtotime($attempt['completed_at'])),
            $attempt['attempt_number']
        ], ';');
    }
}

// Ajouter des métadonnées à la fin
fputcsv($output, [], ';');
fputcsv($output, ['=== MÉTADONNÉES ==='], ';');
fputcsv($output, ['Date d\'export', date('d/m/Y H:i:s')], ';');
fputcsv($output, ['Période analysée', "Du $date_from au $date_to"], ';');
fputcsv($output, ['Exporté par', $_SESSION['admin_username'] ?? 'Admin'], ';');

if ($formation_filter > 0) {
    $formation_name_query = "SELECT title FROM formations WHERE id = $formation_filter";
    $formation_name_result = $conn->query($formation_name_query);
    $formation_name = $formation_name_result->fetch_assoc()['title'];
    fputcsv($output, ['Filtre formation', $formation_name], ';');
}

if ($quiz_filter > 0) {
    $quiz_name_query = "SELECT title FROM formation_quizzes WHERE id = $quiz_filter";
    $quiz_name_result = $conn->query($quiz_name_query);
    $quiz_name = $quiz_name_result->fetch_assoc()['title'];
    fputcsv($output, ['Filtre quiz', $quiz_name], ';');
}

// Log de l'export
$log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'export_quiz_stats', ?)";
$log_stmt = $conn->prepare($log_query);
$description_log = "Export des statistiques de quiz pour la période du $date_from au $date_to";
$log_stmt->bind_param("is", $_SESSION['admin_id'], $description_log);
$log_stmt->execute();

fclose($output);
$conn->close();
?>