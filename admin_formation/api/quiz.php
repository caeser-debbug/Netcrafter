<?php
// api/admin/quiz.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();

require_once __DIR__ . '/../db.php';

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Échec de la connexion: " . $conn->connect_error);
    }
    $conn->set_charset("utf8");
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de connexion à la base de données']);
    exit();
}

// Vérification des permissions admin
function checkAdminAuth() {
    if (!isset($_SESSION['admin_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentification administrateur requise']);
        exit();
    }
    return $_SESSION['admin_id'];
}

function jsonResponse($data, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

// Router
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path_parts = explode('/', trim($path, '/'));

$endpoint = isset($path_parts[3]) ? $path_parts[3] : '';
$id = isset($path_parts[4]) ? intval($path_parts[4]) : 0;

switch ($method) {
    case 'GET':
        handleAdminGetRequest($endpoint, $id);
        break;
    case 'POST':
        handleAdminPostRequest($endpoint, $id);
        break;
    case 'PUT':
        handleAdminPutRequest($endpoint, $id);
        break;
    case 'DELETE':
        handleAdminDeleteRequest($endpoint, $id);
        break;
    default:
        jsonResponse(['error' => 'Méthode non supportée'], 405);
}

// === HANDLERS ADMIN GET ===
function handleAdminGetRequest($endpoint, $id) {
    checkAdminAuth();
    
    switch ($endpoint) {
        case 'quizzes':
            getAdminQuizzes();
            break;
        case 'quiz':
            getAdminQuizDetail($id);
            break;
        case 'questions':
            getQuizQuestions($id);
            break;
        case 'attempts':
            getQuizAttempts($id);
            break;
        case 'stats':
            getQuizStatistics();
            break;
        case 'analytics':
            getQuizAnalytics($id);
            break;
        default:
            jsonResponse(['error' => 'Endpoint non trouvé'], 404);
    }
}

function handleAdminPostRequest($endpoint, $id) {
    checkAdminAuth();
    
    switch ($endpoint) {
        case 'quiz':
            createAdminQuiz();
            break;
        case 'question':
            createQuizQuestion();
            break;
        case 'bulk-action':
            handleBulkAction();
            break;
        default:
            jsonResponse(['error' => 'Endpoint non trouvé'], 404);
    }
}

function handleAdminPutRequest($endpoint, $id) {
    checkAdminAuth();
    
    switch ($endpoint) {
        case 'quiz':
            updateAdminQuiz($id);
            break;
        case 'question':
            updateQuizQuestion($id);
            break;
        case 'toggle-status':
            toggleQuizStatus($id);
            break;
        default:
            jsonResponse(['error' => 'Endpoint non trouvé'], 404);
    }
}

function handleAdminDeleteRequest($endpoint, $id) {
    checkAdminAuth();
    
    switch ($endpoint) {
        case 'quiz':
            deleteAdminQuiz($id);
            break;
        case 'question':
            deleteQuizQuestion($id);
            break;
        default:
            jsonResponse(['error' => 'Endpoint non trouvé'], 404);
    }
}

// === FONCTIONS ADMIN ===

function getAdminQuizzes() {
    global $conn;
    
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 15;
    $search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
    $formation_filter = isset($_GET['formation']) ? intval($_GET['formation']) : 0;
    $status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
    $sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
    $order = isset($_GET['order']) ? $_GET['order'] : 'DESC';
    
    $offset = ($page - 1) * $limit;
    
    // Construction des conditions WHERE
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
        if ($status_filter === 'active') {
            $where_conditions[] = "fq.is_active = 1";
        } else {
            $where_conditions[] = "fq.is_active = 0";
        }
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    // Requête principale avec statistiques
    $query = "SELECT fq.*, f.title as formation_title,
              COUNT(DISTINCT qq.id) as total_questions,
              COUNT(DISTINCT qa.id) as total_attempts,
              AVG(qa.score) as avg_score,
              MAX(qa.completed_at) as last_attempt
              FROM formation_quizzes fq
              JOIN formations f ON fq.formation_id = f.id
              LEFT JOIN quiz_questions qq ON fq.id = qq.quiz_id
              LEFT JOIN quiz_attempts qa ON fq.id = qa.quiz_id
              $where_clause
              GROUP BY fq.id
              ORDER BY fq.$sort_by $order
              LIMIT ?, ?";
    
    $params[] = $offset;
    $params[] = $limit;
    $types .= "ii";
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $quizzes = [];
    while ($row = $result->fetch_assoc()) {
        $row['avg_score'] = $row['avg_score'] ? round($row['avg_score'], 1) : 0;
        $quizzes[] = $row;
    }
    
    // Compter le total
    $count_query = "SELECT COUNT(*) as total FROM formation_quizzes fq
                    JOIN formations f ON fq.formation_id = f.id
                    $where_clause";
    
    $count_params = array_slice($params, 0, -2);
    $count_types = substr($types, 0, -2);
    
    $count_stmt = $conn->prepare($count_query);
    if (!empty($count_params)) {
        $count_stmt->bind_param($count_types, ...$count_params);
    }
    $count_stmt->execute();
    $total = $count_stmt->get_result()->fetch_assoc()['total'];
    
    jsonResponse([
        'success' => true,
        'data' => $quizzes,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($total / $limit),
            'total_items' => intval($total),
            'items_per_page' => $limit
        ]
    ]);
}

function getAdminQuizDetail($quiz_id) {
    global $conn;
    
    if ($quiz_id <= 0) {
        jsonResponse(['error' => 'ID de quiz requis'], 400);
    }
    
    $query = "SELECT fq.*, f.title as formation_title, f.id as formation_id,
              COUNT(DISTINCT qq.id) as total_questions,
              COUNT(DISTINCT qa.id) as total_attempts,
              AVG(qa.score) as avg_score,
              SUM(CASE WHEN qa.passed = 1 THEN 1 ELSE 0 END) as passed_attempts
              FROM formation_quizzes fq
              JOIN formations f ON fq.formation_id = f.id
              LEFT JOIN quiz_questions qq ON fq.id = qq.quiz_id
              LEFT JOIN quiz_attempts qa ON fq.id = qa.quiz_id
              WHERE fq.id = ?
              GROUP BY fq.id";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        jsonResponse(['error' => 'Quiz non trouvé'], 404);
    }
    
    $quiz = $result->fetch_assoc();
    $quiz['avg_score'] = $quiz['avg_score'] ? round($quiz['avg_score'], 1) : 0;
    $quiz['success_rate'] = $quiz['total_attempts'] > 0 ? round(($quiz['passed_attempts'] / $quiz['total_attempts']) * 100, 1) : 0;
    
    // Récupérer les questions avec leurs réponses
    $questions_query = "SELECT qq.*, 
                        COUNT(DISTINCT qa.id) as total_answers
                        FROM quiz_questions qq
                        LEFT JOIN quiz_answers qa ON qq.id = qa.question_id
                        WHERE qq.quiz_id = ?
                        GROUP BY qq.id
                        ORDER BY qq.order_number";
    
    $questions_stmt = $conn->prepare($questions_query);
    $questions_stmt->bind_param("i", $quiz_id);
    $questions_stmt->execute();
    $questions_result = $questions_stmt->get_result();
    
    $quiz['questions'] = [];
    while ($question = $questions_result->fetch_assoc()) {
        // Récupérer les réponses pour chaque question
        $answers_query = "SELECT * FROM quiz_answers WHERE question_id = ? ORDER BY id";
        $answers_stmt = $conn->prepare($answers_query);
        $answers_stmt->bind_param("i", $question['id']);
        $answers_stmt->execute();
        $answers_result = $answers_stmt->get_result();
        
        $question['answers'] = [];
        while ($answer = $answers_result->fetch_assoc()) {
            $question['answers'][] = $answer;
        }
        
        $quiz['questions'][] = $question;
    }
    
    jsonResponse(['success' => true, 'data' => $quiz]);
}

function createAdminQuiz() {
    global $conn;
    
    $admin_id = $_SESSION['admin_id'];
    $input = json_decode(file_get_contents('php://input'), true);
    
    $formation_id = intval($input['formation_id'] ?? 0);
    $title = trim($input['title'] ?? '');
    $description = trim($input['description'] ?? '');
    $passing_score = intval($input['passing_score'] ?? 70);
    $time_limit = intval($input['time_limit'] ?? 0);
    $max_attempts = intval($input['max_attempts'] ?? 3);
    $is_active = isset($input['is_active']) ? 1 : 0;
    
    if ($formation_id <= 0 || empty($title)) {
        jsonResponse(['error' => 'Formation et titre requis'], 400);
    }
    
    $insert_query = "INSERT INTO formation_quizzes (formation_id, title, description, passing_score, time_limit, max_attempts, is_active) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("issiiii", $formation_id, $title, $description, $passing_score, $time_limit, $max_attempts, $is_active);
    
    if ($stmt->execute()) {
        $quiz_id = $conn->insert_id;
        
        // Log de l'activité
        $log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'create_quiz', ?)";
        $log_stmt = $conn->prepare($log_query);
        $description = "Création du quiz: $title";
        $log_stmt->bind_param("is", $admin_id, $description);
        $log_stmt->execute();
        
        jsonResponse([
            'success' => true,
            'data' => [
                'quiz_id' => $quiz_id,
                'message' => 'Quiz créé avec succès'
            ]
        ]);
    } else {
        jsonResponse(['error' => 'Erreur lors de la création du quiz'], 500);
    }
}

function updateAdminQuiz($quiz_id) {
    global $conn;
    
    if ($quiz_id <= 0) {
        jsonResponse(['error' => 'ID de quiz requis'], 400);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $title = trim($input['title'] ?? '');
    $description = trim($input['description'] ?? '');
    $passing_score = intval($input['passing_score'] ?? 70);
    $time_limit = intval($input['time_limit'] ?? 0);
    $max_attempts = intval($input['max_attempts'] ?? 3);
    $is_active = isset($input['is_active']) ? 1 : 0;
    
    if (empty($title)) {
        jsonResponse(['error' => 'Titre requis'], 400);
    }
    
    $update_query = "UPDATE formation_quizzes SET 
                     title = ?, description = ?, passing_score = ?, 
                     time_limit = ?, max_attempts = ?, is_active = ?, 
                     updated_at = NOW() 
                     WHERE id = ?";
    
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ssiiiii", $title, $description, $passing_score, $time_limit, $max_attempts, $is_active, $quiz_id);
    
    if ($stmt->execute()) {
        jsonResponse(['success' => true, 'message' => 'Quiz mis à jour avec succès']);
    } else {
        jsonResponse(['error' => 'Erreur lors de la mise à jour'], 500);
    }
}

function deleteAdminQuiz($quiz_id) {
    global $conn;
    
    if ($quiz_id <= 0) {
        jsonResponse(['error' => 'ID de quiz requis'], 400);
    }
    
    $conn->begin_transaction();
    
    try {
        // Supprimer les réponses
        $delete_answers = "DELETE qa FROM quiz_answers qa 
                          JOIN quiz_questions qq ON qa.question_id = qq.id 
                          WHERE qq.quiz_id = ?";
        $stmt1 = $conn->prepare($delete_answers);
        $stmt1->bind_param("i", $quiz_id);
        $stmt1->execute();
        
        // Supprimer les questions
        $delete_questions = "DELETE FROM quiz_questions WHERE quiz_id = ?";
        $stmt2 = $conn->prepare($delete_questions);
        $stmt2->bind_param("i", $quiz_id);
        $stmt2->execute();
        
        // Supprimer les tentatives
        $delete_attempts = "DELETE FROM quiz_attempts WHERE quiz_id = ?";
        $stmt3 = $conn->prepare($delete_attempts);
        $stmt3->bind_param("i", $quiz_id);
        $stmt3->execute();
        
        // Supprimer le quiz
        $delete_quiz = "DELETE FROM formation_quizzes WHERE id = ?";
        $stmt4 = $conn->prepare($delete_quiz);
        $stmt4->bind_param("i", $quiz_id);
        $stmt4->execute();
        
        $conn->commit();
        jsonResponse(['success' => true, 'message' => 'Quiz supprimé avec succès']);
        
    } catch (Exception $e) {
        $conn->rollback();
        jsonResponse(['error' => 'Erreur lors de la suppression: ' . $e->getMessage()], 500);
    }
}

function createQuizQuestion() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $quiz_id = intval($input['quiz_id'] ?? 0);
    $question = trim($input['question'] ?? '');
    $question_type = $input['question_type'] ?? 'multiple_choice';
    $points = intval($input['points'] ?? 1);
    $order_number = intval($input['order_number'] ?? 1);
    $answers = $input['answers'] ?? [];
    $correct_answers = $input['correct_answers'] ?? [];
    
    if ($quiz_id <= 0 || empty($question) || empty($answers)) {
        jsonResponse(['error' => 'Données requises manquantes'], 400);
    }
    
    $conn->begin_transaction();
    
    try {
        // Insérer la question
        $insert_question = "INSERT INTO quiz_questions (quiz_id, question, question_type, points, order_number) 
                           VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_question);
        $stmt->bind_param("issii", $quiz_id, $question, $question_type, $points, $order_number);
        $stmt->execute();
        
        $question_id = $conn->insert_id;
        
        // Insérer les réponses
        foreach ($answers as $index => $answer_text) {
            if (!empty(trim($answer_text))) {
                $is_correct = in_array($index, $correct_answers) ? 1 : 0;
                
                $insert_answer = "INSERT INTO quiz_answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)";
                $stmt_answer = $conn->prepare($insert_answer);
                $stmt_answer->bind_param("isi", $question_id, trim($answer_text), $is_correct);
                $stmt_answer->execute();
            }
        }
        
        $conn->commit();
        jsonResponse([
            'success' => true,
            'data' => [
                'question_id' => $question_id,
                'message' => 'Question créée avec succès'
            ]
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        jsonResponse(['error' => 'Erreur lors de la création: ' . $e->getMessage()], 500);
    }
}

function updateQuizQuestion($question_id) {
    global $conn;
    
    if ($question_id <= 0) {
        jsonResponse(['error' => 'ID de question requis'], 400);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $question = trim($input['question'] ?? '');
    $question_type = $input['question_type'] ?? 'multiple_choice';
    $points = intval($input['points'] ?? 1);
    $order_number = intval($input['order_number'] ?? 1);
    $answers = $input['answers'] ?? [];
    $correct_answers = $input['correct_answers'] ?? [];
    
    if (empty($question)) {
        jsonResponse(['error' => 'Question requise'], 400);
    }
    
    $conn->begin_transaction();
    
    try {
        // Mettre à jour la question
        $update_question = "UPDATE quiz_questions SET 
                           question = ?, question_type = ?, points = ?, 
                           order_number = ?, updated_at = NOW() 
                           WHERE id = ?";
        $stmt = $conn->prepare($update_question);
        $stmt->bind_param("ssiii", $question, $question_type, $points, $order_number, $question_id);
        $stmt->execute();
        
        // Supprimer les anciennes réponses
        $delete_answers = "DELETE FROM quiz_answers WHERE question_id = ?";
        $stmt_delete = $conn->prepare($delete_answers);
        $stmt_delete->bind_param("i", $question_id);
        $stmt_delete->execute();
        
        // Insérer les nouvelles réponses
        foreach ($answers as $index => $answer_text) {
            if (!empty(trim($answer_text))) {
                $is_correct = in_array($index, $correct_answers) ? 1 : 0;
                
                $insert_answer = "INSERT INTO quiz_answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)";
                $stmt_answer = $conn->prepare($insert_answer);
                $stmt_answer->bind_param("isi", $question_id, trim($answer_text), $is_correct);
                $stmt_answer->execute();
            }
        }
        
        $conn->commit();
        jsonResponse(['success' => true, 'message' => 'Question mise à jour avec succès']);
        
    } catch (Exception $e) {
        $conn->rollback();
        jsonResponse(['error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()], 500);
    }
}

function deleteQuizQuestion($question_id) {
    global $conn;
    
    if ($question_id <= 0) {
        jsonResponse(['error' => 'ID de question requis'], 400);
    }
    
    $conn->begin_transaction();
    
    try {
        // Supprimer les réponses
        $delete_answers = "DELETE FROM quiz_answers WHERE question_id = ?";
        $stmt1 = $conn->prepare($delete_answers);
        $stmt1->bind_param("i", $question_id);
        $stmt1->execute();
        
        // Supprimer la question
        $delete_question = "DELETE FROM quiz_questions WHERE id = ?";
        $stmt2 = $conn->prepare($delete_question);
        $stmt2->bind_param("i", $question_id);
        $stmt2->execute();
        
        $conn->commit();
        jsonResponse(['success' => true, 'message' => 'Question supprimée avec succès']);
        
    } catch (Exception $e) {
        $conn->rollback();
        jsonResponse(['error' => 'Erreur lors de la suppression: ' . $e->getMessage()], 500);
    }
}

function getQuizAttempts($quiz_id) {
    global $conn;
    
    if ($quiz_id <= 0) {
        jsonResponse(['error' => 'ID de quiz requis'], 400);
    }
    
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
    $offset = ($page - 1) * $limit;
    
    $query = "SELECT qa.*, u.firstname, u.lastname, u.phone,
              fq.title as quiz_title, f.title as formation_title
              FROM quiz_attempts qa
              JOIN users u ON qa.user_id = u.id
              JOIN formation_quizzes fq ON qa.quiz_id = fq.id
              JOIN formations f ON fq.formation_id = f.id
              WHERE qa.quiz_id = ?
              ORDER BY qa.completed_at DESC
              LIMIT ?, ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $quiz_id, $offset, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $attempts = [];
    while ($row = $result->fetch_assoc()) {
        $attempts[] = $row;
    }
    
    // Compter le total
    $count_query = "SELECT COUNT(*) as total FROM quiz_attempts WHERE quiz_id = ?";
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param("i", $quiz_id);
    $count_stmt->execute();
    $total = $count_stmt->get_result()->fetch_assoc()['total'];
    
    jsonResponse([
        'success' => true,
        'data' => $attempts,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($total / $limit),
            'total_items' => intval($total),
            'items_per_page' => $limit
        ]
    ]);
}

function getQuizStatistics() {
    global $conn;
    
    $stats_query = "SELECT 
        COUNT(*) as total_quizzes,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_quizzes,
        COUNT(DISTINCT formation_id) as formations_with_quiz,
        (SELECT COUNT(*) FROM quiz_questions) as total_questions,
        (SELECT COUNT(*) FROM quiz_attempts) as total_attempts,
        (SELECT AVG(score) FROM quiz_attempts) as avg_score,
        (SELECT COUNT(*) FROM quiz_attempts WHERE passed = 1) as passed_attempts
        FROM formation_quizzes";
    
    $result = $conn->query($stats_query);
    $stats = $result->fetch_assoc();
    
    $stats['avg_score'] = $stats['avg_score'] ? round($stats['avg_score'], 1) : 0;
    $stats['success_rate'] = $stats['total_attempts'] > 0 ? 
        round(($stats['passed_attempts'] / $stats['total_attempts']) * 100, 1) : 0;
    
    // Statistiques par formation
    $formation_stats_query = "SELECT f.title, f.id,
        COUNT(DISTINCT fq.id) as quiz_count,
        COUNT(DISTINCT qa.id) as attempt_count,
        AVG(qa.score) as avg_score
        FROM formations f
        LEFT JOIN formation_quizzes fq ON f.id = fq.formation_id
        LEFT JOIN quiz_attempts qa ON fq.id = qa.quiz_id
        GROUP BY f.id
        HAVING quiz_count > 0
        ORDER BY attempt_count DESC
        LIMIT 10";
    
    $formation_result = $conn->query($formation_stats_query);
    $formation_stats = [];
    while ($row = $formation_result->fetch_assoc()) {
        $row['avg_score'] = $row['avg_score'] ? round($row['avg_score'], 1) : 0;
        $formation_stats[] = $row;
    }
    
    $stats['formation_stats'] = $formation_stats;
    
    jsonResponse(['success' => true, 'data' => $stats]);
}

function getQuizAnalytics($quiz_id) {
    global $conn;
    
    if ($quiz_id <= 0) {
        jsonResponse(['error' => 'ID de quiz requis'], 400);
    }
    
    // Statistiques générales du quiz
    $general_query = "SELECT 
        COUNT(*) as total_attempts,
        AVG(score) as avg_score,
        MIN(score) as min_score,
        MAX(score) as max_score,
        SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) as passed_count,
        AVG(time_taken) as avg_time_taken
        FROM quiz_attempts 
        WHERE quiz_id = ?";
    
    $stmt = $conn->prepare($general_query);
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $general_stats = $stmt->get_result()->fetch_assoc();
    
    // Distribution des scores
    $score_distribution_query = "SELECT 
        CASE 
            WHEN score >= 90 THEN 'Excellent (90-100%)'
            WHEN score >= 80 THEN 'Très bien (80-89%)'
            WHEN score >= 70 THEN 'Bien (70-79%)'
            WHEN score >= 60 THEN 'Passable (60-69%)'
            ELSE 'Insuffisant (<60%)'
        END as score_range,
        COUNT(*) as count
        FROM quiz_attempts 
        WHERE quiz_id = ?
        GROUP BY score_range
        ORDER BY MIN(score) DESC";
    
    $stmt = $conn->prepare($score_distribution_query);
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $score_distribution = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Tentatives par jour (30 derniers jours)
    $attempts_by_day_query = "SELECT 
        DATE(completed_at) as attempt_date,
        COUNT(*) as attempt_count,
        AVG(score) as avg_score
        FROM quiz_attempts 
        WHERE quiz_id = ? AND completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(completed_at)
        ORDER BY attempt_date ASC";
    
    $stmt = $conn->prepare($attempts_by_day_query);
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $attempts_by_day = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $analytics = [
        'general_stats' => $general_stats,
        'score_distribution' => $score_distribution,
        'attempts_by_day' => $attempts_by_day
    ];
    
    jsonResponse(['success' => true, 'data' => $analytics]);
}

function handleBulkAction() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $quiz_ids = $input['quiz_ids'] ?? [];
    
    if (empty($action) || empty($quiz_ids)) {
        jsonResponse(['error' => 'Action et IDs requis'], 400);
    }
    
    $count = 0;
    foreach ($quiz_ids as $quiz_id) {
        $quiz_id = intval($quiz_id);
        
        switch ($action) {
            case 'activate':
                $update_query = "UPDATE formation_quizzes SET is_active = 1 WHERE id = ?";
                break;
            case 'deactivate':
                $update_query = "UPDATE formation_quizzes SET is_active = 0 WHERE id = ?";
                break;
            case 'delete':
                // Pour la suppression en masse, on utilise la fonction de suppression
                try {
                    deleteAdminQuiz($quiz_id);
                    $count++;
                } catch (Exception $e) {
                    // Continuer avec les autres
                }
                continue 2;
            default:
                continue 2;
        }
        
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("i", $quiz_id);
        if ($stmt->execute()) {
            $count++;
        }
    }
    
    jsonResponse([
        'success' => true,
        'message' => "$count quiz(s) traité(s) avec succès"
    ]);
}

function toggleQuizStatus($quiz_id) {
    global $conn;
    
    if ($quiz_id <= 0) {
        jsonResponse(['error' => 'ID de quiz requis'], 400);
    }
    
    $toggle_query = "UPDATE formation_quizzes SET is_active = NOT is_active WHERE id = ?";
    $stmt = $conn->prepare($toggle_query);
    $stmt->bind_param("i", $quiz_id);
    
    if ($stmt->execute()) {
        // Récupérer le nouveau statut
        $status_query = "SELECT is_active FROM formation_quizzes WHERE id = ?";
        $status_stmt = $conn->prepare($status_query);
        $status_stmt->bind_param("i", $quiz_id);
        $status_stmt->execute();
        $new_status = $status_stmt->get_result()->fetch_assoc()['is_active'];
        
        jsonResponse([
            'success' => true,
            'data' => [
                'new_status' => $new_status,
                'message' => 'Statut mis à jour avec succès'
            ]
        ]);
    } else {
        jsonResponse(['error' => 'Erreur lors de la mise à jour du statut'], 500);
    }
}

$conn->close();
?>