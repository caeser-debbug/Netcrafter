<?php
// admin/api/formations.php
session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

require_once __DIR__ . '/../db.php';

// Connexion à la base de données
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données']);
    exit;
}
$conn->set_charset("utf8");

// Headers pour JSON
header('Content-Type: application/json');

// Récupérer l'action
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get':
        getFormationDetails();
        break;
    case 'get_modules':
        getFormationModules();
        break;
    case 'create_module':
        createModule();
        break;
    case 'update_module':
        updateModule();
        break;
    case 'delete_module':
        deleteModule();
        break;
    case 'get_videos':
        getModuleVideos();
        break;
    case 'get_video':
        getSingleVideo();
        break;
    case 'create_video':
        createVideo();
        break;
    case 'update_video':
        updateVideo();
        break;
    case 'delete_video':
        deleteVideo();
        break;
    case 'reorder_modules':
        reorderModules();
        break;
    case 'reorder_videos':
        reorderVideos();
        break;
    case 'stats':
        getFormationStats();
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action non valide']);
        break;
}

function getFormationDetails() {
    global $conn;
    
    $formation_id = intval($_GET['id'] ?? 0);
    
    if ($formation_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID formation invalide']);
        return;
    }
    
    $query = "SELECT f.*, c.name as category_name
              FROM formations f
              LEFT JOIN formation_categories c ON f.category_id = c.id
              WHERE f.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $formation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Formation non trouvée']);
        return;
    }
    
    $formation = $result->fetch_assoc();
    
    echo json_encode(['success' => true, 'formation' => $formation]);
}

function getFormationModules() {
    global $conn;
    
    $formation_id = intval($_GET['formation_id'] ?? 0);
    
    if ($formation_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID formation invalide']);
        return;
    }
    
    $query = "SELECT fm.*, 
              COUNT(fv.id) as video_count,
              SUM(CASE WHEN fv.duration IS NOT NULL THEN CAST(SUBSTRING_INDEX(fv.duration, ' ', 1) AS SIGNED) ELSE 0 END) as total_duration
              FROM formation_modules fm
              LEFT JOIN formation_videos fv ON fm.id = fv.module_id
              WHERE fm.formation_id = ?
              GROUP BY fm.id
              ORDER BY fm.order_number ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $formation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $modules = [];
    while ($row = $result->fetch_assoc()) {
        $modules[] = $row;
    }
    
    echo json_encode(['success' => true, 'modules' => $modules]);
}

function createModule() {
    global $conn;
    
    $formation_id = intval($_POST['formation_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $is_free = isset($_POST['is_free']) ? 1 : 0;
    $estimated_duration = trim($_POST['estimated_duration'] ?? '');
    
    if ($formation_id <= 0 || empty($title)) {
        echo json_encode(['success' => false, 'message' => 'Données manquantes']);
        return;
    }
    
    // Récupérer le prochain numéro d'ordre
    $order_query = "SELECT COALESCE(MAX(order_number), 0) + 1 as next_order FROM formation_modules WHERE formation_id = ?";
    $order_stmt = $conn->prepare($order_query);
    $order_stmt->bind_param("i", $formation_id);
    $order_stmt->execute();
    $next_order = $order_stmt->get_result()->fetch_assoc()['next_order'];
    
    $insert_query = "INSERT INTO formation_modules (formation_id, title, description, order_number, is_free, estimated_duration) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("isssis", $formation_id, $title, $description, $next_order, $is_free, $estimated_duration);
    
    if ($stmt->execute()) {
        $module_id = $conn->insert_id;
        
        // Log de l'activité
        $log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'create_module', ?)";
        $log_stmt = $conn->prepare($log_query);
        $description = "Création du module: $title (Formation ID: $formation_id)";
        $log_stmt->bind_param("is", $_SESSION['admin_id'], $description);
        $log_stmt->execute();
        
        echo json_encode(['success' => true, 'module_id' => $module_id, 'message' => 'Module créé avec succès']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la création du module']);
    }
}

function updateModule() {
    global $conn;
    
    $module_id = intval($_POST['module_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $is_free = isset($_POST['is_free']) ? 1 : 0;
    $estimated_duration = trim($_POST['estimated_duration'] ?? '');
    
    if ($module_id <= 0 || empty($title)) {
        echo json_encode(['success' => false, 'message' => 'Données manquantes']);
        return;
    }
    
    $update_query = "UPDATE formation_modules SET title = ?, description = ?, is_free = ?, estimated_duration = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ssisi", $title, $description, $is_free, $estimated_duration, $module_id);
    
    if ($stmt->execute()) {
        // Log de l'activité
        $log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'update_module', ?)";
        $log_stmt = $conn->prepare($log_query);
        $description = "Modification du module ID: $module_id";
        $log_stmt->bind_param("is", $_SESSION['admin_id'], $description);
        $log_stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Module mis à jour avec succès']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
    }
}

function deleteModule() {
    global $conn;
    
    $module_id = intval($_POST['module_id'] ?? 0);
    
    if ($module_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID module invalide']);
        return;
    }
    
    $conn->begin_transaction();
    
    try {
        // Supprimer d'abord les vidéos du module
        $delete_videos_query = "DELETE FROM formation_videos WHERE module_id = ?";
        $delete_videos_stmt = $conn->prepare($delete_videos_query);
        $delete_videos_stmt->bind_param("i", $module_id);
        $delete_videos_stmt->execute();
        
        // Supprimer le module
        $delete_module_query = "DELETE FROM formation_modules WHERE id = ?";
        $delete_module_stmt = $conn->prepare($delete_module_query);
        $delete_module_stmt->bind_param("i", $module_id);
        $delete_module_stmt->execute();
        
        $conn->commit();
        
        // Log de l'activité
        $log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'delete_module', ?)";
        $log_stmt = $conn->prepare($log_query);
        $description = "Suppression du module ID: $module_id";
        $log_stmt->bind_param("is", $_SESSION['admin_id'], $description);
        $log_stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Module supprimé avec succès']);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression: ' . $e->getMessage()]);
    }
}

function getModuleVideos() {
    global $conn;
    
    $module_id = intval($_GET['module_id'] ?? 0);
    
    if ($module_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID module invalide']);
        return;
    }
    
    $query = "SELECT * FROM formation_videos WHERE module_id = ? ORDER BY order_number ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $module_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $videos = [];
    while ($row = $result->fetch_assoc()) {
        $videos[] = $row;
    }
    
    echo json_encode(['success' => true, 'videos' => $videos]);
}

function getSingleVideo() {
    global $conn;

    $video_id = intval($_GET['id'] ?? 0);

    if ($video_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID vidéo invalide']);
        return;
    }

    $query = "SELECT * FROM formation_videos WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $video_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Vidéo non trouvée']);
        return;
    }

    $video = $result->fetch_assoc();
    echo json_encode(['success' => true, 'video' => $video]);
}

function createVideo() {
    global $conn;
    
    $module_id = intval($_POST['module_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $preview_duration = intval($_POST['preview_duration'] ?? 60);
    
    if ($module_id <= 0 || empty($title)) {
        echo json_encode(['success' => false, 'message' => 'Données manquantes']);
        return;
    }
    
    // Gestion de l'upload vidéo
    $video_url = null;
    if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/formation/video/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['video_file']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['mp4', 'webm', 'avi', 'mov'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $filename = 'video_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['video_file']['tmp_name'], $filepath)) {
                $video_url = 'uploads/formation/video/' . $filename;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Format vidéo non supporté']);
            return;
        }
    }
    
    if (!$video_url) {
        echo json_encode(['success' => false, 'message' => 'Fichier vidéo requis']);
        return;
    }
    
    // Récupérer le prochain numéro d'ordre
    $order_query = "SELECT COALESCE(MAX(order_number), 0) + 1 as next_order FROM formation_videos WHERE module_id = ?";
    $order_stmt = $conn->prepare($order_query);
    $order_stmt->bind_param("i", $module_id);
    $order_stmt->execute();
    $next_order = $order_stmt->get_result()->fetch_assoc()['next_order'];
    
    $insert_query = "INSERT INTO formation_videos (module_id, title, description, video_url, duration, preview_duration, order_number) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("issssii", $module_id, $title, $description, $video_url, $duration, $preview_duration, $next_order);
    
    if ($stmt->execute()) {
        $video_id = $conn->insert_id;
        
        // Log de l'activité
        $log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'create_video', ?)";
        $log_stmt = $conn->prepare($log_query);
        $description = "Création de la vidéo: $title (Module ID: $module_id)";
        $log_stmt->bind_param("is", $_SESSION['admin_id'], $description);
        $log_stmt->execute();
        
        echo json_encode(['success' => true, 'video_id' => $video_id, 'message' => 'Vidéo créée avec succès']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la création de la vidéo']);
    }
}

function updateVideo() {
    global $conn;
    
    $video_id = intval($_POST['video_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $preview_duration = intval($_POST['preview_duration'] ?? 60);
    
    if ($video_id <= 0 || empty($title)) {
        echo json_encode(['success' => false, 'message' => 'Données manquantes']);
        return;
    }
    
    // Gestion de l'upload vidéo (optionnel pour la mise à jour)
    $video_url_update = "";
    $video_url_param = null;
    
    if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/formation/video/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['video_file']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['mp4', 'webm', 'avi', 'mov'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $filename = 'video_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['video_file']['tmp_name'], $filepath)) {
                $video_url_update = ", video_url = ?";
                $video_url_param = 'uploads/formation/video/' . $filename;
            }
        }
    }
    
    $update_query = "UPDATE formation_videos SET title = ?, description = ?, duration = ?, preview_duration = ?, updated_at = NOW() $video_url_update WHERE id = ?";
    
    $stmt = $conn->prepare($update_query);
    
    if ($video_url_param) {
        $stmt->bind_param("sssisi", $title, $description, $duration, $preview_duration, $video_url_param, $video_id);
    } else {
        $stmt->bind_param("sssii", $title, $description, $duration, $preview_duration, $video_id);
    }
    
    if ($stmt->execute()) {
        // Log de l'activité
        $log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'update_video', ?)";
        $log_stmt = $conn->prepare($log_query);
        $description = "Modification de la vidéo ID: $video_id";
        $log_stmt->bind_param("is", $_SESSION['admin_id'], $description);
        $log_stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Vidéo mise à jour avec succès']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
    }
}

function deleteVideo() {
    global $conn;
    
    $video_id = intval($_POST['video_id'] ?? 0);
    
    if ($video_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID vidéo invalide']);
        return;
    }
    
    // Récupérer les infos de la vidéo pour supprimer le fichier
    $video_query = "SELECT video_url FROM formation_videos WHERE id = ?";
    $video_stmt = $conn->prepare($video_query);
    $video_stmt->bind_param("i", $video_id);
    $video_stmt->execute();
    $video_result = $video_stmt->get_result();
    
    if ($video_result->num_rows > 0) {
        $video_data = $video_result->fetch_assoc();
        $video_file = '../' . $video_data['video_url'];
        
        // Supprimer la vidéo de la base
        $delete_query = "DELETE FROM formation_videos WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param("i", $video_id);
        
        if ($delete_stmt->execute()) {
            // Supprimer le fichier physique
            if (file_exists($video_file)) {
                unlink($video_file);
            }
            
            // Log de l'activité
            $log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'delete_video', ?)";
            $log_stmt = $conn->prepare($log_query);
            $description = "Suppression de la vidéo ID: $video_id";
            $log_stmt->bind_param("is", $_SESSION['admin_id'], $description);
            $log_stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Vidéo supprimée avec succès']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Vidéo non trouvée']);
    }
}

function reorderModules() {
    global $conn;
    
    $modules = json_decode($_POST['modules'] ?? '[]', true);
    
    if (empty($modules)) {
        echo json_encode(['success' => false, 'message' => 'Données manquantes']);
        return;
    }
    
    $conn->begin_transaction();
    
    try {
        foreach ($modules as $index => $module_id) {
            $update_query = "UPDATE formation_modules SET order_number = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $order = $index + 1;
            $update_stmt->bind_param("ii", $order, $module_id);
            $update_stmt->execute();
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Ordre des modules mis à jour']);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la réorganisation']);
    }
}

function reorderVideos() {
    global $conn;
    
    $videos = json_decode($_POST['videos'] ?? '[]', true);
    
    if (empty($videos)) {
        echo json_encode(['success' => false, 'message' => 'Données manquantes']);
        return;
    }
    
    $conn->begin_transaction();
    
    try {
        foreach ($videos as $index => $video_id) {
            $update_query = "UPDATE formation_videos SET order_number = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $order = $index + 1;
            $update_stmt->bind_param("ii", $order, $video_id);
            $update_stmt->execute();
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Ordre des vidéos mis à jour']);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la réorganisation']);
    }
}

function getFormationStats() {
    global $conn;
    
    $formation_id = intval($_GET['formation_id'] ?? 0);
    
    if ($formation_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID formation invalide']);
        return;
    }
    
    // Statistiques de la formation
    $stats_query = "SELECT 
        COUNT(DISTINCT fs.id) as total_subscriptions,
        COUNT(DISTINCT CASE WHEN fs.status = 'active' AND fs.end_date >= CURDATE() THEN fs.id END) as active_subscriptions,
        COUNT(DISTINCT fm.id) as total_modules,
        COUNT(DISTINCT fv.id) as total_videos,
        COUNT(DISTINCT c.id) as total_certificates,
        AVG(qa.score) as avg_quiz_score
        FROM formations f
        LEFT JOIN formation_subscriptions fs ON f.id = fs.formation_id
        LEFT JOIN formation_modules fm ON f.id = fm.formation_id
        LEFT JOIN formation_videos fv ON fm.id = fv.module_id
        LEFT JOIN certificates c ON f.id = c.formation_id
        LEFT JOIN formation_quizzes fq ON f.id = fq.formation_id
        LEFT JOIN quiz_attempts qa ON fq.id = qa.quiz_id
        WHERE f.id = ?";
    
    $stats_stmt = $conn->prepare($stats_query);
    $stats_stmt->bind_param("i", $formation_id);
    $stats_stmt->execute();
    $stats = $stats_stmt->get_result()->fetch_assoc();
    
    // Progression des utilisateurs
    $progress_query = "SELECT 
        u.firstname, u.lastname,
        COUNT(DISTINCT vp.video_id) as videos_watched,
        COUNT(DISTINCT fv.id) as total_videos,
        ROUND((COUNT(DISTINCT vp.video_id) / COUNT(DISTINCT fv.id)) * 100, 2) as progress_percentage
        FROM formation_subscriptions fs
        JOIN users u ON fs.user_id = u.id
        JOIN formation_modules fm ON fs.formation_id = fm.formation_id
        JOIN formation_videos fv ON fm.id = fv.module_id
        LEFT JOIN video_progress vp ON fv.id = vp.video_id AND vp.user_id = u.id AND vp.is_completed = 1
        WHERE fs.formation_id = ? AND fs.status = 'active'
        GROUP BY u.id
        ORDER BY progress_percentage DESC
        LIMIT 10";
    
    $progress_stmt = $conn->prepare($progress_query);
    $progress_stmt->bind_param("i", $formation_id);
    $progress_stmt->execute();
    $progress_result = $progress_stmt->get_result();
    
    $user_progress = [];
    while ($row = $progress_result->fetch_assoc()) {
        $user_progress[] = $row;
    }
    
    echo json_encode([
        'success' => true, 
        'stats' => $stats,
        'user_progress' => $user_progress
    ]);
}

$conn->close();
?>