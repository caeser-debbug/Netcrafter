<?php
// admin/get_topic_details.php
session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

require_once __DIR__ . '/db.php';

// Connexion à la base de données
$conn = new mysqli($servername, $username, $password, $dbname);

// Vérification de la connexion
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données']);
    exit;
}
$conn->set_charset("utf8");

// Récupérer l'ID du sujet
$topic_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($topic_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID du sujet invalide']);
    exit;
}

try {
    // Récupérer les détails du sujet
    $topic_query = "
        SELECT ft.*, 
               u.firstname, u.lastname,
               f.title as formation_title
        FROM forum_topics ft 
        JOIN users u ON ft.user_id = u.id 
        LEFT JOIN formations f ON ft.formation_id = f.id 
        WHERE ft.id = ?
    ";
    
    $stmt = $conn->prepare($topic_query);
    $stmt->bind_param("i", $topic_id);
    $stmt->execute();
    $topic_result = $stmt->get_result();
    
    if ($topic_result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Sujet non trouvé']);
        exit;
    }
    
    $topic = $topic_result->fetch_assoc();
    
    // Incrémenter le nombre de vues
    $update_views = "UPDATE forum_topics SET views = views + 1 WHERE id = ?";
    $stmt = $conn->prepare($update_views);
    $stmt->bind_param("i", $topic_id);
    $stmt->execute();
    
    // Récupérer les réponses
    $replies_query = "
        SELECT fr.*, 
               u.firstname, u.lastname
        FROM forum_replies fr 
        JOIN users u ON fr.user_id = u.id 
        WHERE fr.topic_id = ? 
        ORDER BY fr.is_solution DESC, fr.created_at ASC
    ";
    
    $stmt = $conn->prepare($replies_query);
    $stmt->bind_param("i", $topic_id);
    $stmt->execute();
    $replies_result = $stmt->get_result();
    
    $replies = [];
    while ($reply = $replies_result->fetch_assoc()) {
        $replies[] = [
            'id' => $reply['id'],
            'topic_id' => $reply['topic_id'],
            'content' => $reply['content'],
            'is_solution' => (bool)$reply['is_solution'],
            'author_name' => $reply['firstname'] . ' ' . $reply['lastname'],
            'created_at' => date('d/m/Y H:i', strtotime($reply['created_at']))
        ];
    }
    
    // Préparer la réponse
    $response = [
        'success' => true,
        'topic' => [
            'id' => $topic['id'],
            'title' => $topic['title'],
            'content' => $topic['content'],
            'is_pinned' => (bool)$topic['is_pinned'],
            'is_locked' => (bool)$topic['is_locked'],
            'views' => $topic['views'] + 1, // Inclure la vue actuelle
            'author_name' => $topic['firstname'] . ' ' . $topic['lastname'],
            'formation_title' => $topic['formation_title'],
            'created_at' => date('d/m/Y H:i', strtotime($topic['created_at']))
        ],
        'replies' => $replies
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
} finally {
    $conn->close();
}
?>