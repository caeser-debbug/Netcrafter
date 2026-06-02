<?php
// admin/get_forum_stats.php
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

try {
    // Statistiques du forum
    $stats_query = "
        SELECT 
            COUNT(*) as total_topics,
            COUNT(CASE WHEN is_pinned = 1 THEN 1 END) as pinned_topics,
            COUNT(CASE WHEN is_locked = 1 THEN 1 END) as locked_topics,
            (SELECT COUNT(*) FROM forum_replies) as total_replies,
            (SELECT COUNT(*) FROM forum_topics ft WHERE EXISTS (SELECT 1 FROM forum_replies fr WHERE fr.topic_id = ft.id AND fr.is_solution = 1)) as resolved_topics,
            (SELECT COUNT(DISTINCT user_id) FROM forum_topics) as active_users,
            (SELECT MAX(created_at) FROM forum_topics) as last_topic_date,
            (SELECT MAX(created_at) FROM forum_replies) as last_reply_date
        FROM forum_topics
    ";
    
    $stats_result = $conn->query($stats_query);
    $stats = $stats_result->fetch_assoc();
    
    // Statistiques par formation
    $formation_stats_query = "
        SELECT 
            f.title as formation_title,
            COUNT(ft.id) as topic_count,
            COUNT(fr.id) as reply_count
        FROM formations f
        LEFT JOIN forum_topics ft ON f.id = ft.formation_id
        LEFT JOIN forum_replies fr ON ft.id = fr.topic_id
        WHERE f.status = 'active'
        GROUP BY f.id, f.title
        HAVING topic_count > 0
        ORDER BY topic_count DESC
        LIMIT 5
    ";
    
    $formation_stats_result = $conn->query($formation_stats_query);
    $formation_stats = [];
    while ($row = $formation_stats_result->fetch_assoc()) {
        $formation_stats[] = $row;
    }
    
    // Utilisateurs les plus actifs
    $active_users_query = "
        SELECT 
            u.firstname,
            u.lastname,
            COUNT(DISTINCT ft.id) as topic_count,
            COUNT(DISTINCT fr.id) as reply_count,
            (COUNT(DISTINCT ft.id) + COUNT(DISTINCT fr.id)) as total_activity
        FROM users u
        LEFT JOIN forum_topics ft ON u.id = ft.user_id
        LEFT JOIN forum_replies fr ON u.id = fr.user_id
        WHERE u.status = 'active'
        GROUP BY u.id, u.firstname, u.lastname
        HAVING total_activity > 0
        ORDER BY total_activity DESC
        LIMIT 5
    ";
    
    $active_users_result = $conn->query($active_users_query);
    $active_users = [];
    while ($row = $active_users_result->fetch_assoc()) {
        $active_users[] = [
            'name' => $row['firstname'] . ' ' . $row['lastname'],
            'topic_count' => (int)$row['topic_count'],
            'reply_count' => (int)$row['reply_count'],
            'total_activity' => (int)$row['total_activity']
        ];
    }
    
    // Activité récente (derniers 7 jours)
    $recent_activity_query = "
        SELECT 
            DATE(created_at) as activity_date,
            COUNT(*) as topic_count
        FROM forum_topics 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY activity_date DESC
    ";
    
    $recent_activity_result = $conn->query($recent_activity_query);
    $recent_activity = [];
    while ($row = $recent_activity_result->fetch_assoc()) {
        $recent_activity[] = [
            'date' => $row['activity_date'],
            'count' => (int)$row['topic_count']
        ];
    }
    
    // Sujets non résolus les plus anciens
    $unresolved_query = "
        SELECT 
            ft.id,
            ft.title,
            ft.created_at,
            u.firstname,
            u.lastname,
            COUNT(fr.id) as reply_count
        FROM forum_topics ft
        JOIN users u ON ft.user_id = u.id
        LEFT JOIN forum_replies fr ON ft.id = fr.topic_id
        WHERE NOT EXISTS (SELECT 1 FROM forum_replies fr2 WHERE fr2.topic_id = ft.id AND fr2.is_solution = 1)
        AND ft.is_locked = 0
        GROUP BY ft.id, ft.title, ft.created_at, u.firstname, u.lastname
        ORDER BY ft.created_at ASC
        LIMIT 5
    ";
    
    $unresolved_result = $conn->query($unresolved_query);
    $unresolved_topics = [];
    while ($row = $unresolved_result->fetch_assoc()) {
        $unresolved_topics[] = [
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'author' => $row['firstname'] . ' ' . $row['lastname'],
            'created_at' => $row['created_at'],
            'reply_count' => (int)$row['reply_count'],
            'days_old' => (int)((time() - strtotime($row['created_at'])) / (60 * 60 * 24))
        ];
    }
    
    // Calculer les taux
    $resolution_rate = $stats['total_topics'] > 0 ? 
        round(($stats['resolved_topics'] / $stats['total_topics']) * 100, 1) : 0;
    
    $avg_replies_per_topic = $stats['total_topics'] > 0 ? 
        round($stats['total_replies'] / $stats['total_topics'], 1) : 0;
    
    $response = [
        'success' => true,
        'stats' => [
            'total_topics' => (int)$stats['total_topics'],
            'pinned_topics' => (int)$stats['pinned_topics'],
            'locked_topics' => (int)$stats['locked_topics'],
            'total_replies' => (int)$stats['total_replies'],
            'resolved_topics' => (int)$stats['resolved_topics'],
            'active_users' => (int)$stats['active_users'],
            'resolution_rate' => $resolution_rate,
            'avg_replies_per_topic' => $avg_replies_per_topic,
            'last_topic_date' => $stats['last_topic_date'],
            'last_reply_date' => $stats['last_reply_date']
        ],
        'formation_stats' => $formation_stats,
        'active_users' => $active_users,
        'recent_activity' => $recent_activity,
        'unresolved_topics' => $unresolved_topics,
        'updated_at' => date('Y-m-d H:i:s')
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